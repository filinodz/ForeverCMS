<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'includes/forever_shop.php';
require_once $project_root . 'includes/ra_client.php';

if (empty($_SESSION['user_id'])) {
    header("Location: {$base_path}login");
    exit;
}

function shop_redirect($base_path, $status)
{
    header("Location: {$base_path}shop?category=All&status=" . rawurlencode($status));
    exit;
}

if (empty($_POST['csrf_token']) || !is_string($_POST['csrf_token'])
    || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    shop_redirect($base_path, 'error');
}
if (isset($_SESSION['last_purchase_time']) && time() - (int)$_SESSION['last_purchase_time'] < 5) {
    shop_redirect($base_path, 'cooldown_active');
}
if (!isset($_POST['item_id'], $_POST['character_id'])
    || !ctype_digit((string)$_POST['item_id']) || !ctype_digit((string)$_POST['character_id'])) {
    shop_redirect($base_path, 'error');
}

$product_id = (int)$_POST['item_id'];
$character_guid = (int)$_POST['character_id'];
$account_id = (int)$_SESSION['user_id'];
$realm_id = (int)$current_realm['id'];
$auth_transaction_started = false;

try {
    // Same product and actions as the ForeverCore in-game shop.
    $product = forever_shop_product($world_db, $product_id);
    if (!$product || !$product['enabled']) {
        throw new Exception('Item not found');
    }
    if (!$product['web_purchasable']) {
        throw new Exception('ingame_only');
    }

    $attachments = [];
    foreach ($product['actions'] as $action) {
        $entry = (int)$action['param1'];
        $count = max(1, (int)$action['param2']);
        if ((int)$action['type'] !== 1 || $entry <= 0 || !forever_item($world_db, $entry)) {
            throw new Exception('invalid_item_configuration');
        }
        $attachments[] = ['entry' => $entry, 'count' => $count];
    }
    if (!$attachments) {
        throw new Exception('invalid_item_configuration');
    }

    $stmt = $char_db->prepare('SELECT name FROM characters WHERE guid = ? AND account = ? AND deleteInfos_Account IS NULL');
    if (!$stmt) {
        throw new Exception('Database query error');
    }
    $stmt->bind_param('ii', $character_guid, $account_id);
    $stmt->execute();
    $character = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$character || !preg_match('/^[\p{L}]{2,12}$/u', $character['name'])) {
        throw new Exception('character_not_found');
    }
    $character_name = $character['name'];

    if (!forever_ra_configured() || !forever_ra_online((int)$current_realm['ra_port'])) {
        throw new Exception('realm_offline');
    }

    if (!$auth_db->begin_transaction()) {
        throw new Exception('Database query error');
    }
    $auth_transaction_started = true;

    // Missing wallet rows are valid zero-point accounts. This is the wallet
    // ForeverCore debits, so balances stay identical in game and on the CMS.
    $stmt = $auth_db->prepare('INSERT IGNORE INTO account_shop_points (AccountID, Points) VALUES (?, 0)');
    if (!$stmt) {
        throw new Exception('Database query error');
    }
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $stmt->close();

    $price = (int)$product['point_cost'];
    $stmt = $auth_db->prepare('UPDATE account_shop_points SET Points = Points - ? WHERE AccountID = ? AND Points >= ?');
    if (!$stmt) {
        throw new Exception('Database query error');
    }
    $stmt->bind_param('iii', $price, $account_id, $price);
    $stmt->execute();
    $paid = $stmt->affected_rows === 1 || $price === 0;
    $stmt->close();
    if (!$paid) {
        throw new Exception('insufficient_funds');
    }

    $subject = forever_ra_quote('Shop: ' . $product['name']);
    $body = forever_ra_quote('Thank you for your purchase of ' . $product['name'] . '!');
    foreach (array_chunk($attachments, 12) as $chunk) {
        $list = implode(' ', array_map(function ($item) {
            return (int)$item['entry'] . ':' . (int)$item['count'];
        }, $chunk));
        $command = "send items {$character_name} \"{$subject}\" \"{$body}\" {$list}";
        $result = forever_realm_ra($realm_id, $command);
        if (!$result['success'] || stripos($result['output'], 'Mail sent') === false) {
            error_log('Forever CMS shop delivery failed: ' . ($result['error'] ?: $result['output']));
            throw new Exception('delivery_failed');
        }
    }

    $stmt = $auth_db->prepare('INSERT INTO shop_purchase_log (AccountID, ProductID, Price, CharacterGUID) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('Database query error');
    }
    $stmt->bind_param('iiii', $account_id, $product_id, $price, $character_guid);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Database query error');
    }
    $stmt->close();

    if (!$auth_db->commit()) {
        $auth_transaction_started = false;
        error_log("Forever CMS shop: delivered but payment commit failed (account {$account_id}, product {$product_id})");
        throw new Exception('Database query error');
    }
    $auth_transaction_started = false;

    $details = "Purchased {$product['name']} (Forever shop product {$product_id}) for {$character_name} on {$current_realm['name']}";
    $stmt = $site_db->prepare('INSERT INTO website_activity_log (account_id, character_name, action, timestamp, details) VALUES (?, ?, ?, UNIX_TIMESTAMP(), ?)');
    if ($stmt) {
        $action_name = 'Purchase Shared Shop Item';
        $stmt->bind_param('isss', $account_id, $character_name, $action_name, $details);
        $stmt->execute();
        $stmt->close();
    }

    $_SESSION['last_purchase_time'] = time();
    shop_redirect($base_path, 'success');
} catch (Exception $e) {
    if ($auth_transaction_started) {
        $auth_db->rollback();
    }
    $error = $e->getMessage();
    error_log("Forever CMS purchase error: {$error}; product {$product_id}; character {$character_guid}; account {$account_id}");
    $known = ['insufficient_funds', 'character_not_found', 'Database query error', 'invalid_item_configuration',
        'realm_offline', 'delivery_failed', 'ingame_only'];
    shop_redirect($base_path, in_array($error, $known, true) ? $error : 'error');
}
