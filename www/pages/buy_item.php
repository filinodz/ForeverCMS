<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php'; // Include paths.php
require_once $project_root . 'includes/session.php';
require_once $project_root . 'includes/forever_items.php';
require_once $project_root . 'includes/ra_client.php';

/*
 * Forever: purchases are delivered by the worldserver itself, through RA (its remote console), on the realm
 * chosen in the header:
 *   items and sets   send items <name> "subject" "text" id:1 ...   (in-game mail, character online or not)
 *   gold             send money <name> "subject" "text" <copper>
 *   level boost      character level <name> <level>
 *   services         character rename / customize / changefaction / changerace, reset spells / talents
 * SahtoutCMS wrote items into the characters database with a stored procedure; Forever's item tables are the
 * server's business, so nothing is written there. When the realm is stopped, gold, levels and services are
 * written to the (then unused) characters database like SahtoutCMS did, and items have to wait for the realm.
 */

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: {$base_path}login");
    exit;
}

function shop_redirect($base_path, $status)
{
    header("Location: {$base_path}shop?category=All&status=" . rawurlencode($status));
    exit;
}

// Validate CSRF token (token is generated in includes/session.php and posted
// as a hidden field by the purchase form in pages/shop.php)
if (empty($_POST['csrf_token']) || !is_string($_POST['csrf_token'])
    || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log("CSRF token validation failed: User ID: " . ($_SESSION['user_id'] ?? 'unknown'));
    shop_redirect($base_path, 'error');
}

// Check cooldown first
$cooldown_duration = 5; // 5 seconds
if (isset($_SESSION['last_purchase_time']) && (time() - $_SESSION['last_purchase_time']) < $cooldown_duration) {
    shop_redirect($base_path, 'cooldown_active');
}

// Validate POST data
if (!isset($_POST['item_id'], $_POST['character_id']) || !ctype_digit((string)$_POST['item_id']) || !ctype_digit((string)$_POST['character_id'])) {
    shop_redirect($base_path, 'error');
}

$item_id = (int)$_POST['item_id'];
$character_guid = (int)$_POST['character_id'];
$account_id = (int)$_SESSION['user_id'];
$realm_id = (int)$current_realm['id'];
$realm_online = forever_ra_configured() && forever_ra_online((int)$current_realm['ra_port']);

/** Runs a console command on the purchase's realm; $expect is a text the answer must contain ('' = any answer but an error). */
$run = function ($command, $expect = '') use ($realm_id) {
    $result = forever_realm_ra($realm_id, $command);
    $failed = !$result['success']
        || ($expect !== '' && stripos($result['output'], $expect) === false)
        || ($expect === '' && preg_match('/not found|does not exist|doesn\'t exist|incorrect|invalid|syntax|no such|unknown command|error/i', $result['output']));
    if ($failed) {
        error_log("Shop: RA command failed: {$command} -> " . ($result['error'] ?: $result['output']));
        throw new Exception('delivery_failed');
    }
};

$site_transaction_started = false;

try {
    // Fetch item details, including set metadata.
    $stmt = $site_db->prepare("SELECT name, point_cost, token_cost, stock, gold_amount, category, level_boost, at_login_flags, is_item, is_set, entry, itemset_id FROM shop_items WHERE item_id = ?");
    if (!$stmt) {
        throw new Exception('Database query error');
    }
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$item) {
        throw new Exception('Item not found');
    }
    $category = strtolower($item['category']);
    $gold_in_copper = (int)$item['gold_amount'] * 10000;
    $at_login_flags = (int)$item['at_login_flags'];

    // The character must belong to the account on this realm
    $stmt = $char_db->prepare("SELECT online, money, name, level FROM characters WHERE guid = ? AND account = ? AND deleteInfos_Account IS NULL");
    if (!$stmt) {
        throw new Exception('Database query error');
    }
    $stmt->bind_param('ii', $character_guid, $account_id);
    $stmt->execute();
    $character = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$character) {
        throw new Exception('character_not_found');
    }
    $character_name = $character['name'];
    if (!preg_match('/^[\p{L}]{2,12}$/u', $character_name)) {
        throw new Exception('character_not_found');
    }

    // What has to be delivered
    $is_gold = $category === 'gold' && $gold_in_copper > 0;
    $is_level = $category === 'service' && $item['level_boost'] !== null;
    $is_service = $category === 'service' && !$is_level && $at_login_flags > 0;
    $set_entries = [];
    if ((int)$item['is_set'] === 1) {
        $set_entries = array_keys(forever_itemset_entries($world_db, (int)$item['itemset_id']));
        if (empty($item['itemset_id']) || empty($set_entries)) {
            throw new Exception('invalid_item_configuration');
        }
    }
    $is_single_item = !$is_gold && !$is_level && !$is_service && !$set_entries && (int)$item['is_item'] === 1 && $item['entry'] !== null;
    if ($is_single_item && !forever_item($world_db, (int)$item['entry'])) {
        throw new Exception('invalid_item_configuration');
    }
    if (!$is_gold && !$is_level && !$is_service && !$set_entries && !$is_single_item) {
        throw new Exception('invalid_item_configuration');
    }

    if ($is_gold && $gold_in_copper > 4294967295) {
        throw new Exception('Gold amount too large');
    }
    if ($is_level && (int)$character['level'] >= (int)$item['level_boost']) {
        throw new Exception('level_too_high');
    }
    // items are mailed by the worldserver; without it they cannot be made
    if (!$realm_online && ($set_entries || $is_single_item)) {
        throw new Exception('realm_offline');
    }
    // a stopped realm leaves no character online; a stale flag means the database is not safe to write
    if (!$realm_online && (int)$character['online'] === 1) {
        throw new Exception('character_online');
    }

    if (!$site_db->begin_transaction()) {
        throw new Exception('Database query error');
    }
    $site_transaction_started = true;

    // 1. ATOMICALLY update user currency & verify sufficient balance
    $stmt = $site_db->prepare("UPDATE user_currencies SET points = points - ?, tokens = tokens - ?, last_updated = NOW() WHERE account_id = ? AND points >= ? AND tokens >= ?");
    $stmt->bind_param('iiiii', $item['point_cost'], $item['token_cost'], $account_id, $item['point_cost'], $item['token_cost']);
    $stmt->execute();
    $paid = $stmt->affected_rows === 1 || ((int)$item['point_cost'] === 0 && (int)$item['token_cost'] === 0);
    $stmt->close();
    if (!$paid) {
        throw new Exception('insufficient_funds');
    }

    // 2. ATOMICALLY update stock
    if ($item['stock'] !== null) {
        $stmt = $site_db->prepare("UPDATE shop_items SET stock = stock - 1, last_updated = NOW() WHERE item_id = ? AND stock IS NOT NULL AND stock > 0");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $in_stock = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$in_stock) {
            throw new Exception('out_of_stock');
        }
    }

    // Record the purchase
    $stmt = $site_db->prepare("INSERT INTO purchases (account_id, item_id, realm_id, character_guid, character_name, point_cost, token_cost) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiiisii', $account_id, $item_id, $realm_id, $character_guid, $character_name, $item['point_cost'], $item['token_cost']);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Database query error');
    }
    $stmt->close();

    // 3. Deliver. Anything that fails here throws, and the payment above is rolled back.
    $subject = forever_ra_quote('Shop: ' . $item['name']);
    $body = forever_ra_quote('Thank you for your purchase of ' . $item['name'] . '!');
    $action = 'Purchase Item';
    $details = '';

    if ($is_gold) {
        if ($realm_online) {
            $run("send money {$character_name} \"{$subject}\" \"{$body}\" {$gold_in_copper}", 'Mail sent');
        } else {
            $stmt = $char_db->prepare("UPDATE characters SET money = money + ? WHERE guid = ? AND online = 0");
            $stmt->bind_param('ii', $gold_in_copper, $character_guid);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new Exception('delivery_failed');
            }
            $stmt->close();
        }
        $action = 'Purchase Gold';
        $details = "Purchased {$item['gold_amount']} gold for {$character_name} on {$current_realm['name']}";
    } elseif ($is_level) {
        $level = (int)$item['level_boost'];
        if ($realm_online) {
            $run("character level {$character_name} {$level}");
        } else {
            $stmt = $char_db->prepare("UPDATE characters SET level = ?, xp = 0 WHERE guid = ? AND online = 0");
            $stmt->bind_param('ii', $level, $character_guid);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new Exception('delivery_failed');
            }
            $stmt->close();
        }
        $action = 'Purchase Level Boost';
        $details = "Leveled {$character_name} to level {$level} on {$current_realm['name']} via item {$item['name']} (ID: {$item_id})";
    } elseif ($is_service) {
        // at_login flag => [console command, name]; the server asks for the change at the character's next login
        $services = [
            1 => ['character rename', 'Rename'],
            2 => ['reset spells', 'Reset Spells'],
            4 => ['reset talents', 'Reset Talents'],
            8 => ['character customize', 'Customize'],
            64 => ['character changefaction', 'Faction Change'],
            128 => ['character changerace', 'Race Change'],
        ];
        $supported = array_sum(array_keys($services));
        if (($at_login_flags & ~$supported) !== 0) {
            throw new Exception('invalid_item_configuration');
        }
        $actions = [];
        if ($realm_online) {
            foreach ($services as $flag => $service) {
                if ($at_login_flags & $flag) {
                    $run($service[0] . ' ' . $character_name);
                    $actions[] = $service[1];
                }
            }
        } else {
            $stmt = $char_db->prepare("UPDATE characters SET at_login = at_login | ? WHERE guid = ? AND online = 0");
            $stmt->bind_param('ii', $at_login_flags, $character_guid);
            if (!$stmt->execute()) {
                throw new Exception('delivery_failed');
            }
            $stmt->close();
            foreach ($services as $flag => $service) {
                if ($at_login_flags & $flag) {
                    $actions[] = $service[1];
                }
            }
        }
        $action = 'Purchase Character Customization';
        $details = 'Applied ' . implode(', ', $actions) . " for {$character_name} on {$current_realm['name']} via item {$item['name']} (ID: {$item_id})";
    } elseif ($set_entries) {
        // one mail holds 12 items at most
        foreach (array_chunk($set_entries, 12) as $chunk) {
            $list = implode(' ', array_map(function ($entry) { return (int)$entry . ':1'; }, $chunk));
            $run("send items {$character_name} \"{$subject}\" \"{$body}\" {$list}", 'Mail sent');
        }
        $action = 'Purchase Item Set';
        $details = "Purchased set {$item['name']} (ID: {$item_id}, ItemSet: {$item['itemset_id']}) with " . count($set_entries) . " items, mailed to {$character_name} on {$current_realm['name']}";
    } else {
        $entry = (int)$item['entry'];
        $run("send items {$character_name} \"{$subject}\" \"{$body}\" {$entry}:1", 'Mail sent');
        $details = "Purchased item {$item['name']} (ID: {$item_id}, Entry: {$entry}), mailed to {$character_name} on {$current_realm['name']}";
    }

    $stmt = $site_db->prepare("INSERT INTO website_activity_log (account_id, character_name, action, timestamp, details) VALUES (?, ?, ?, UNIX_TIMESTAMP(), ?)");
    if ($stmt) {
        $stmt->bind_param('isss', $account_id, $character_name, $action, $details);
        $stmt->execute();
        $stmt->close();
    }

    if (!$site_db->commit()) {
        // the delivery is done; the payment is not recorded. Say so in the log, it needs a human.
        error_log("Shop: delivered but could not commit: account {$account_id}, item {$item_id}, {$details}");
        $site_transaction_started = false;
        throw new Exception('Database query error');
    }
    $site_transaction_started = false;

    // Set last purchase time in session ONLY after successful commit
    $_SESSION['last_purchase_time'] = time();
    shop_redirect($base_path, 'success');
} catch (Exception $e) {
    if ($site_transaction_started) {
        $site_db->rollback();
    }

    $error = $e->getMessage();
    error_log("Purchase Error: $error, Item ID: $item_id, Character GUID: $character_guid, User ID: $account_id, Realm: $realm_id");
    $known = ['out_of_stock', 'insufficient_funds', 'character_not_found', 'Database query error', 'Gold amount too large', 'Total money exceeds limit',
        'character_online', 'level_too_high', 'invalid_item_configuration', 'realm_offline', 'delivery_failed'];
    shop_redirect($base_path, in_array($error, $known, true) ? $error : 'error');
}
