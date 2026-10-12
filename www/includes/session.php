<?php
// Detect HTTPS properly (works with proxies & Cloudflare)
$is_https = false;
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    $is_https = true;
}

// Force correct session cookie params
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',        // current domain
    'secure' => $is_https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.use_only_cookies', 1);
session_name('forever_cms');

session_start();

require_once __DIR__ . '/paths.php';
// Include database configuration
require_once $project_root . 'includes/config.php';

// Initialize debug errors
$_SESSION['debug_errors'] = $_SESSION['debug_errors'] ?? [];

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 1800) { // Every 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Forever: a session belongs to a game account (auth.account.id, named "<bnetId>#1") of a Battle.net account.
 *   user_id      auth.account.id (characters.account, account_access, account_banned, user_currencies.account_id)
 *   account_name auth.account.username, checked against the database on protected pages
 *   bnet_id      auth.battlenet_accounts.id
 *   email        the login e-mail, lower case
 *   username     the name shown on the website (the part of the e-mail before the @)
 */
function forever_session_account($auth_db)
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['account_name'])) {
        return null;
    }
    $stmt = $auth_db->prepare('SELECT a.id, a.username, a.battlenet_account, b.email FROM account a '
        . 'JOIN battlenet_accounts b ON b.id = a.battlenet_account WHERE a.id = ? AND a.username = ?');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $_SESSION['user_id'], $_SESSION['account_name']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Fills the session after a successful login. */
function forever_login_session($auth_db, $site_db, array $account, array $bnet)
{
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    $_SESSION['user_id'] = (int)$account['id'];
    $_SESSION['account_name'] = $account['username'];
    $_SESSION['bnet_id'] = (int)$bnet['id'];
    $_SESSION['email'] = strtolower($bnet['email']);
    $_SESSION['username'] = forever_display_name($bnet['email']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['debug_errors'] = [];
    forever_ensure_site_user($site_db, (int)$account['id'], $account['username'], $bnet['email']);
}

function forever_end_session($base_path, $error)
{
    session_unset();
    session_destroy();
    header('Location: ' . $base_path . 'login?error=' . $error);
    exit();
}

// Define protected and admin pages
$protected_pages = ['account.php', 'buy_item.php'];
$admin_pages = ['dashboard.php', 'users.php', 'anews.php','characters.php','ashop.php','gm_cmd.php', 'logout.php','save_general.php'];
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_admin_page = (in_array($current_page, $admin_pages) && $current_dir === 'admin') || $current_dir === 'settings';
$is_protected_page = in_array($current_page, $protected_pages) && !$is_admin_page;

// Validate session for protected and admin pages
if ($is_protected_page || $is_admin_page) {
    if (empty($_SESSION['user_id']) || empty($_SESSION['account_name'])) {
        forever_end_session($base_path, 'invalid_session');
    }

    // Validate the session against the auth database
    $session_account = forever_session_account($auth_db);
    if (!$session_account) {
        forever_end_session($base_path, 'invalid_session');
    }
    $_SESSION['email'] = strtolower($session_account['email']);
    $_SESSION['username'] = forever_display_name($session_account['email']);

    // Load user role
    /** @var \mysqli_stmt|false $stmt */
    $stmt = $site_db->prepare("SELECT role FROM user_currencies WHERE account_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['role'] = $result->num_rows === 1 ? $result->fetch_assoc()['role'] : 'player';
    $stmt->close();

    // Load GM level (highest on any realm)
    $_SESSION['gmlevel'] = forever_gm_level($auth_db, (int)$_SESSION['user_id']);

    // Restrict admin pages to admin or moderator
    if ($is_admin_page && !in_array($_SESSION['role'], ['admin', 'moderator'])) {
        $_SESSION['debug_errors'] = ["Unauthorized access to admin page."];
        header('Location: ' . $base_path . 'login?error=unauthorized');
        exit();
    }
}

// For login, register, and activate pages, redirect to account if already logged in
$public_pages = ['login.php', 'register.php', 'activate.php','forgot_password.php', 'reset_password.php','resend_activation.php'];
if (in_array($current_page, $public_pages) && !empty($_SESSION['user_id'])) {
    if (forever_session_account($auth_db)) {
        header('Location: ' . $base_path . 'account');
        exit();
    }
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['debug_errors'] = [];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
