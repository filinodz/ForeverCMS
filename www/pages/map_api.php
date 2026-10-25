<?php
// Live map feed: online characters of one public realm with their last saved
// world position. Read-only; GM/staff accounts and deleted characters are never
// listed. Positions are as fresh as the realm's PlayerSaveInterval.
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/forever.php';
require_once $project_root . 'includes/online_presence.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_write_close();

function map_api_reply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') map_api_reply(405, ['error' => 'method']);
if (!($live_map_enabled ?? true)) map_api_reply(403, ['error' => 'disabled']);

$realms = forever_public_realms();
$realm = $realms[0] ?? null;
foreach ($realms as $candidate) {
    if ((int)$candidate['id'] === (int)($_GET['realm'] ?? 0)) $realm = $candidate;
}
if (!$realm) map_api_reply(404, ['error' => 'realm']);

$db = forever_char_db((int)$realm['id']);
if (!$db) map_api_reply(503, ['error' => 'database']);

$bots = forever_presence_bot_sql($db);
$result = $db->query(
    'SELECT c.guid, c.account, c.name, c.race, c.class, c.gender, c.level, c.map, c.zone, '
    . 'c.position_x, c.position_y, (' . $bots['sql'] . ') AS is_bot '
    . 'FROM characters c WHERE c.online = 1 AND c.deleteInfos_Account IS NULL LIMIT 2000'
);
if (!$result) {
    error_log('Forever CMS live map: ' . $db->error);
    map_api_reply(503, ['error' => 'database']);
}
$rows = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

// Staff never appear on the public map.
$staff = [];
$accounts = array_values(array_unique(array_map(static fn($row) => (int)$row['account'], $rows)));
if ($accounts) {
    $access = $auth_db->query('SELECT DISTINCT AccountID FROM account_access WHERE SecurityLevel > 0 AND AccountID IN (' . implode(',', $accounts) . ')');
    // Do not disclose positions when the staff exclusion cannot be checked.
    if (!$access) map_api_reply(503, ['error' => 'database']);
    while ($row = $access->fetch_row()) $staff[(int)$row[0]] = true;
}

$showBots = (bool)($live_map_show_bots ?? true);
$players = [];
foreach ($rows as $row) {
    $bot = !empty($row['is_bot']);
    if (isset($staff[(int)$row['account']]) || ($bot && !$showBots)) continue;
    $race = (int)$row['race'];
    $players[] = [
        'guid' => (int)$row['guid'],
        'name' => $row['name'],
        'level' => (int)$row['level'],
        'race' => $race,
        'raceName' => forever_race_name($race),
        'class' => (int)$row['class'],
        'className' => forever_class_name((int)$row['class']),
        'gender' => (int)$row['gender'],
        'icon' => forever_race_icon_file($race, (int)$row['gender']),
        'faction' => forever_is_alliance($race) ? 'alliance' : (forever_is_horde($race) ? 'horde' : 'neutral'),
        'map' => (int)$row['map'],
        'zone' => (int)$row['zone'],
        'x' => round((float)$row['position_x'], 1),
        'y' => round((float)$row['position_y'], 1),
        'bot' => $bot,
    ];
}
usort($players, static fn($a, $b) => [$a['bot'], -$a['level'], $a['name']] <=> [$b['bot'], -$b['level'], $b['name']]);

map_api_reply(200, ['realm' => ['id' => (int)$realm['id'], 'name' => $realm['name']], 'updated' => time(), 'players' => $players]);
