<?php
// Start output buffering to catch any stray output
ob_start();

// Set error handling to log errors instead of displaying them.
// Logging goes to the server-side PHP error log only: no request data, session
// data, CSRF tokens or credentials are ever written to a log file again
// (the previous 'claim_log.txt' file was written inside the web root).
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Capture all errors and warnings
set_error_handler(function($severity, $message, $file, $line) {
    // Server-side only, and never any POST/session/token values
    error_log(sprintf('pingback/claim.php PHP error [%d]: %s in %s on line %d', $severity, $message, $file, $line));
    return true; // Suppress display of errors
});
require_once __DIR__ . '/../../includes/paths.php';
define('ALLOWED_ACCESS', true);

// Load configuration, session, and translation system
try {
    require_once $project_root . 'includes/config.php';
    require_once $project_root . 'includes/session.php';
    require_once $project_root . 'languages/language.php';
} catch (Exception $e) {
    // Log server-side only; never return internal paths/details to the client
    error_log('pingback/claim.php: required includes failed: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Internal server error."]);
    exit;
}

// Define fallback translation function
$translate = function($key, $default) {
    static $translations = null;
    if ($translations === null) {
        try {
            if (function_exists('translate')) {
                $translations = true; // Translation system is loaded
                return translate($key, $default);
            }
            $translations = false;
        } catch (Exception $e) {
            $translations = false;
        }
    }
    return $translations === true ? translate($key, $default) : $default;
};

// Check database connection
if (!$site_db || !$site_db instanceof mysqli || $site_db->connect_error) {
    error_log('pingback/claim.php: site database connection failed.');
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $translate('err_db_connection_failed', 'Database connection failed.')]);
    exit;
}

// Only POST requests can carry the CSRF token; reject every other request
// method (GET/HEAD/... can never satisfy the token check below anyway)
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $translate('err_invalid_csrf', 'Invalid CSRF token.')]);
    exit;
}

// Validate CSRF token and POST data (never trust a non-scalar POST value)
$user_id = (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) ? (int)$_POST['user_id'] : 0;
$site_id = (isset($_POST['site_id']) && is_string($_POST['site_id'])) ? $_POST['site_id'] : null;
$csrf_token = (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) ? $_POST['csrf_token'] : null;

// Validate the session CSRF token (generated centrally in includes/session.php)
if (!is_string($_SESSION['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], (string)$csrf_token)) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $translate('err_invalid_csrf', 'Invalid CSRF token.')]);
    exit;
}

// Ensure the authenticated session user can only claim rewards for their own account
if (empty($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== $user_id) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $translate('err_claim_forbidden', 'You can only claim rewards for your own account.')]);
    exit;
}

if (!$user_id) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $translate('err_invalid_user_id', 'Invalid user ID.')]);
    exit;
}

// Validate user_id and fetch username
// (wrapped so that driver/database failures can never escape to the client)
try {
    $stmt = $site_db->prepare("SELECT account_id, username FROM user_currencies WHERE account_id = ?");
    if (!$stmt) {
        throw new Exception('failed to prepare user_currencies query: ' . $site_db->error);
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception('failed to execute user_currencies query: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        ob_end_clean();
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => $translate('err_user_not_found', 'User ID not found in user_currencies.')]);
        exit;
    }
    $user = $result->fetch_assoc();
    $username = $user['username'];
    $stmt->close();
} catch (Exception $e) {
    // mysqli_sql_exception extends Exception, so driver errors are caught as well
    error_log('pingback/claim.php: user lookup failed: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $translate('err_processing_failed', 'An internal error occurred. Please try again later.')]);
    exit;
}

// Start transaction
$site_db->begin_transaction();
try {
    $expiration_period = 24 * 3600; // 24 hours
    $expiration_time = time() - $expiration_period;

    // Validate site_id if provided
    $internal_site_ids = [];
    if ($site_id) {
        $stmt = $site_db->prepare("SELECT id FROM vote_sites WHERE callback_file_name = ?");
        if (!$stmt) {
            throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
        }
        $stmt->bind_param("s", $site_id);
        if (!$stmt->execute()) {
            throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
        }
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $site_db->rollback();
            ob_end_clean();
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => sprintf($translate('err_site_not_found', 'Site ID not found in vote_sites: %s.'), $site_id)]);
            exit;
        }
        $site = $result->fetch_assoc();
        $internal_site_ids[] = (int)$site['id'];
        $stmt->close();
    } else {
        // Fetch all site IDs if no specific site_id is provided
        $result = $site_db->query("SELECT id FROM vote_sites WHERE uses_callback = 1");
        if (!$result) {
            throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
        }
        while ($row = $result->fetch_assoc()) {
            $internal_site_ids[] = (int)$row['id'];
        }
    }

    // The IN (...) list is built exclusively from vote_sites.id values read from
    // the database and forced to integers; every value is still bound as a
    // parameter, so no user input ever reaches the SQL text.
    $internal_site_ids = array_map('intval', $internal_site_ids);

    // Never build an empty "IN ()" clause (invalid SQL): nothing can be claimed
    if (empty($internal_site_ids)) {
        $site_db->rollback();
        ob_end_clean();
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => sprintf($translate('err_no_unclaimed_votes', 'No unclaimed votes available for user: %s.'), $username)]);
        exit;
    }

    // Check for unclaimed votes (locking read: rows stay locked until commit)
    $where_clause = $site_id ? "site_id = ?" : "site_id IN (" . implode(',', array_fill(0, count($internal_site_ids), '?')) . ")";
    $stmt = $site_db->prepare("
        SELECT COUNT(*) as vote_count
        FROM vote_log
        WHERE user_id = ? AND $where_clause AND reward_status = 0 AND vote_timestamp >= ?
        FOR UPDATE
    ");
    if (!$stmt) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
    }
    $bind_params = array_merge([$user_id], $site_id ? [$internal_site_ids[0]] : $internal_site_ids, [$expiration_time]);
    $stmt->bind_param(str_repeat('i', count($bind_params)), ...$bind_params);
    if (!$stmt->execute()) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $vote_count = $row['vote_count'];
    $stmt->close();

    if ($vote_count === 0) {
        $site_db->rollback();
        ob_end_clean();
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => sprintf($translate('err_no_unclaimed_votes', 'No unclaimed votes available for user: %s.'), $username)]);
        exit;
    }

    // Move expired votes to vote_log_history
    $stmt = $site_db->prepare("
        INSERT INTO vote_log_history (site_id, user_id, ip_address, vote_timestamp, reward_status, claimed_timestamp)
        SELECT site_id, user_id, ip_address, vote_timestamp, 0, NULL
        FROM vote_log
        WHERE user_id = ? AND $where_clause AND reward_status = 0 AND vote_timestamp < ?
    ");
    if (!$stmt) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
    }
    $bind_params = array_merge([$user_id], $site_id ? [$internal_site_ids[0]] : $internal_site_ids, [$expiration_time]);
    $stmt->bind_param(str_repeat('i', count($bind_params)), ...$bind_params);
    if (!$stmt->execute()) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
    }
    $stmt->close();

    // Delete expired votes
    $stmt = $site_db->prepare("
        DELETE FROM vote_log
        WHERE user_id = ? AND $where_clause AND reward_status = 0 AND vote_timestamp < ?
    ");
    if (!$stmt) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
    }
    $bind_params = array_merge([$user_id], $site_id ? [$internal_site_ids[0]] : $internal_site_ids, [$expiration_time]);
    $stmt->bind_param(str_repeat('i', count($bind_params)), ...$bind_params);
    if (!$stmt->execute()) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
    }
    $stmt->close();

    // Fetch unclaimed, non-expired votes (same locking read: the rows returned
    // here are the exact rows that will be credited and deleted below)
    $stmt = $site_db->prepare("
        SELECT vl.id, vl.site_id, vl.user_id, vl.ip_address, vl.vote_timestamp, vl.reward_status, vs.reward_points
        FROM vote_log vl
        JOIN vote_sites vs ON vl.site_id = vs.id
        WHERE vl.user_id = ? AND $where_clause AND vl.reward_status = 0 AND vl.vote_timestamp >= ?
        FOR UPDATE
    ");
    if (!$stmt) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
    }
    $bind_params = array_merge([$user_id], $site_id ? [$internal_site_ids[0]] : $internal_site_ids, [$expiration_time]);
    $stmt->bind_param(str_repeat('i', count($bind_params)), ...$bind_params);
    if (!$stmt->execute()) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
    }
    $result = $stmt->get_result();
    $unclaimed_votes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_points = 0;
    foreach ($unclaimed_votes as $vote) {
        $total_points += (int)$vote['reward_points'];

        // Insert into vote_log_history
        $stmt = $site_db->prepare("
            INSERT INTO vote_log_history (site_id, user_id, ip_address, vote_timestamp, reward_status, reward_points, claimed_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
        }
        $claimed_timestamp = time();
        $reward_status = 1; // Claimed
        $stmt->bind_param("iisiiii", $vote['site_id'], $vote['user_id'], $vote['ip_address'], $vote['vote_timestamp'], $reward_status, $vote['reward_points'], $claimed_timestamp);
        if (!$stmt->execute()) {
            throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
        }
        $stmt->close();
    }

    // Update user points
    if ($total_points > 0) {
        $stmt = $site_db->prepare("UPDATE user_currencies SET points = points + ? WHERE account_id = ?");
        if (!$stmt) {
            throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
        }
        $stmt->bind_param("ii", $total_points, $user_id);
        if (!$stmt->execute()) {
            throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
        }
        $stmt->close();
    }

    // Delete claimed votes
    $stmt = $site_db->prepare("
        DELETE FROM vote_log
        WHERE user_id = ? AND $where_clause AND reward_status = 0 AND vote_timestamp >= ?
    ");
    if (!$stmt) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $site_db->error));
    }
    $bind_params = array_merge([$user_id], $site_id ? [$internal_site_ids[0]] : $internal_site_ids, [$expiration_time]);
    $stmt->bind_param(str_repeat('i', count($bind_params)), ...$bind_params);
    if (!$stmt->execute()) {
        throw new Exception(sprintf($translate('err_database_generic', 'Database error: %s'), $stmt->error));
    }
    $stmt->close();

    // Commit transaction
    $site_db->commit();
    ob_end_clean();
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "success",
        "message" => sprintf($translate('msg_reward_claimed', 'Reward successfully claimed for %s. +%d points have been added to your account.'), $username, $total_points),
        "points_awarded" => $total_points
    ]);
} catch (Exception $e) {
    $site_db->rollback();
    // Internal details stay server-side; the client only gets a generic message
    error_log('pingback/claim.php: error while claiming rewards: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $translate('err_processing_failed', 'An internal error occurred. Please try again later.')]);
}

// Close database connections
$site_db->close();
if (isset($auth_db)) $auth_db->close();
if (isset($world_db)) $world_db->close();
if (isset($char_db)) $char_db->close();

// Ensure no output after JSON
ob_end_flush();
exit;
?>