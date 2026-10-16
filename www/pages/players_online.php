<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/forever.php';
require_once $project_root . 'includes/online_presence.php';

$page_class = 'players-online';
$page_title = translate('online_page_title', 'Players Online') . ' — ' . ($site_title_name ?? 'Forever');
$page_meta_description = translate('online_meta_description', 'See who is currently adventuring on the Forever realms and open their Armory profile.');

$public_realms = forever_public_realms();
$selected_realm = $public_realms[0] ?? null;
if (isset($_GET['realm']) && ctype_digit((string)$_GET['realm'])) {
    foreach ($public_realms as $realm) {
        if ((int)$realm['id'] === (int)$_GET['realm']) {
            $selected_realm = $realm;
            break;
        }
    }
}

$players = [];
$database_available = false;
if ($selected_realm) {
    $online_db = forever_char_db((int)$selected_realm['id']);
    if ($online_db) {
        $database_available = true;
        $bot_detection = forever_presence_bot_sql($online_db);
        $result = $online_db->query(
            'SELECT c.guid, c.name, c.race, c.class, c.gender, c.level, c.zone, c.map, ('
            . $bot_detection['sql'] . ') AS is_bot '
            . 'FROM characters c WHERE c.online = 1 AND c.deleteInfos_Account IS NULL '
            . 'ORDER BY is_bot ASC, c.level DESC, c.name ASC'
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $players[] = $row;
            }
            $result->free();
        } else {
            $database_available = false;
            error_log('Forever CMS: players online: ' . $online_db->error);
        }
    }
}

$class_icons = [
    1 => 'warrior.webp', 2 => 'paladin.webp', 3 => 'hunter.webp', 4 => 'rogue.webp',
    5 => 'priest.webp', 6 => 'deathknight.webp', 7 => 'shaman.webp', 8 => 'mage.webp',
    9 => 'warlock.webp', 11 => 'druid.webp',
];
$class_colors = [
    1 => '#c79c6e', 2 => '#f58cba', 3 => '#abd473', 4 => '#fff569', 5 => '#ffffff',
    6 => '#c41f3b', 7 => '#0070de', 8 => '#69ccf0', 9 => '#9482c9', 10 => '#00ff96',
    11 => '#ff7d0a', 12 => '#a330c9', 13 => '#33937f',
];
$map_names = [
    0 => translate('map_eastern_kingdoms', 'Eastern Kingdoms'),
    1 => translate('map_kalimdor', 'Kalimdor'),
    2853 => 'Deadwind Pass Forever',
    2921 => 'Naxxramas Forever',
    2980 => 'Dalaran City Forever',
];
$alliance_count = 0;
$horde_count = 0;
$bot_count = 0;
foreach ($players as $player) {
    if (!empty($player['is_bot'])) { $bot_count++; continue; }
    if (forever_is_alliance((int)$player['race'])) {
        $alliance_count++;
    } elseif (forever_is_horde((int)$player['race'])) {
        $horde_count++;
    }
}

$page_body_class = 'players-online-page';
$page_head = '<link rel="stylesheet" href="' . htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8') . 'assets/css/players-online.css?v=20261016-3">';
include_once $project_root . 'includes/header.php';
include $project_root . 'includes/online_roster_view.php';
include_once $project_root . 'includes/footer.php';
