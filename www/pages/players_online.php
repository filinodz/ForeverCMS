<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/forever.php';

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
        $result = $online_db->query(
            'SELECT guid, name, race, class, gender, level, zone, map '
            . 'FROM characters WHERE online = 1 AND deleteInfos_Account IS NULL '
            . 'ORDER BY level DESC, name ASC'
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
$alliance_count = 0;
$horde_count = 0;
foreach ($players as $player) {
    if (forever_is_alliance((int)$player['race'])) {
        $alliance_count++;
    } elseif (forever_is_horde((int)$player['race'])) {
        $horde_count++;
    }
}

ob_start();
?>
<style>
    body.players-online-page {
        min-height: 100vh;
        padding-top: 112px;
        color: #dce6f3;
        background:
            radial-gradient(circle at 15% 25%, rgba(22, 83, 145, .22), transparent 34rem),
            radial-gradient(circle at 88% 18%, rgba(181, 116, 28, .15), transparent 30rem),
            linear-gradient(180deg, #080c13 0%, #05070c 58%, #030408 100%);
        background-attachment: fixed;
    }
    .online-shell { position: relative; isolation: isolate; }
    .online-shell::before {
        content: ''; position: absolute; inset: -28px -18px auto; height: 290px; z-index: -1;
        background: linear-gradient(90deg, transparent, rgba(201,162,39,.07), transparent);
        border-top: 1px solid rgba(242,207,82,.12); border-bottom: 1px solid rgba(242,207,82,.08);
        transform: skewY(-1.3deg);
    }
    .online-kicker { color: #d9ae43; letter-spacing: .32em; font-size: .72rem; font-weight: 800; }
    .online-title { font-family: 'Cinzel', serif; color: #fff4c7; text-shadow: 0 0 24px rgba(242,207,82,.24); }
    .realm-tabs { display: flex; gap: .55rem; overflow-x: auto; padding: .35rem; scrollbar-width: thin; }
    .realm-tab {
        white-space: nowrap; padding: .72rem 1rem; border: 1px solid rgba(201,162,39,.2);
        color: #8fa1b8; background: rgba(7,11,18,.76); font-size: .78rem; font-weight: 800;
        letter-spacing: .07em; text-transform: uppercase; transition: .2s ease;
    }
    .realm-tab:hover, .realm-tab.active { color: #fff1b2; border-color: rgba(242,207,82,.66); background: rgba(201,162,39,.12); }
    .online-stat {
        background: linear-gradient(145deg, rgba(19,26,38,.88), rgba(7,10,16,.9));
        border: 1px solid rgba(201,162,39,.16); box-shadow: inset 0 1px rgba(255,255,255,.025);
    }
    .player-card {
        position: relative; overflow: hidden; display: flex; align-items: center; gap: 1rem;
        min-height: 112px; padding: 1rem; background: linear-gradient(135deg, rgba(17,23,34,.95), rgba(7,10,16,.96));
        border: 1px solid rgba(122,143,170,.15); box-shadow: 0 14px 30px rgba(0,0,0,.22); transition: .22s ease;
    }
    .player-card::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--class-color); box-shadow: 0 0 16px var(--class-color); }
    .player-card:hover { transform: translateY(-3px); border-color: rgba(242,207,82,.38); box-shadow: 0 18px 36px rgba(0,0,0,.38); }
    .race-frame { width: 68px; height: 68px; flex: 0 0 68px; display: grid; place-items: center; background: #080b11; border: 1px solid rgba(242,207,82,.32); transform: rotate(45deg); }
    .race-frame img { width: 52px; height: 52px; object-fit: contain; transform: rotate(-45deg); }
    .class-icon { width: 22px; height: 22px; object-fit: cover; border: 1px solid rgba(255,255,255,.16); border-radius: 50%; }
    .online-dot { width: 7px; height: 7px; border-radius: 50%; background: #57db87; box-shadow: 0 0 10px #57db87; }
    .empty-online { background: rgba(8,12,19,.84); border: 1px dashed rgba(201,162,39,.25); }
    @media (max-width: 768px) { body.players-online-page { padding-top: 96px; } .race-frame { width: 58px; height: 58px; flex-basis: 58px; } .race-frame img { width: 44px; height: 44px; } }
</style>
<?php
$page_head = ob_get_clean();
$page_body_class = 'players-online-page';
include_once $project_root . 'includes/header.php';
?>

<main class="online-shell relative z-10 max-w-7xl mx-auto px-4 md:px-8 py-10 md:py-14">
    <header class="text-center max-w-3xl mx-auto mb-8">
        <div class="online-kicker mb-3"><?php echo translate('online_kicker', 'LIVE REALM ROSTER'); ?></div>
        <h1 class="online-title text-4xl md:text-6xl font-black mb-3"><?php echo translate('online_page_title', 'Players Online'); ?></h1>
        <p class="text-[#8fa1b8] leading-relaxed"><?php echo translate('online_intro', 'See who is exploring Azeroth right now. Select a character to open their complete Armory profile.'); ?></p>
    </header>

    <?php if (count($public_realms) > 1): ?>
        <nav class="realm-tabs justify-start md:justify-center mb-7" aria-label="<?php echo htmlspecialchars(translate('online_choose_realm', 'Choose a realm'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($public_realms as $realm): ?>
                <a class="realm-tab <?php echo $selected_realm && (int)$realm['id'] === (int)$selected_realm['id'] ? 'active' : ''; ?>"
                   href="<?php echo htmlspecialchars($base_path . 'players_online?realm=' . (int)$realm['id'] . '&lang=' . urlencode($langCode), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($realm['name'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <section class="grid grid-cols-3 gap-3 md:gap-5 max-w-3xl mx-auto mb-9">
        <div class="online-stat p-4 text-center">
            <div class="text-2xl md:text-3xl font-black text-[#f2cf5b]"><?php echo count($players); ?></div>
            <div class="text-[10px] md:text-xs uppercase tracking-wider text-[#8290a3] mt-1"><?php echo translate('online_total', 'Online'); ?></div>
        </div>
        <div class="online-stat p-4 text-center">
            <div class="text-2xl md:text-3xl font-black text-[#77a9ff]"><?php echo $alliance_count; ?></div>
            <div class="text-[10px] md:text-xs uppercase tracking-wider text-[#8290a3] mt-1"><?php echo translate('faction_alliance', 'Alliance'); ?></div>
        </div>
        <div class="online-stat p-4 text-center">
            <div class="text-2xl md:text-3xl font-black text-[#ef6b63]"><?php echo $horde_count; ?></div>
            <div class="text-[10px] md:text-xs uppercase tracking-wider text-[#8290a3] mt-1"><?php echo translate('faction_horde', 'Horde'); ?></div>
        </div>
    </section>

    <?php if (!$selected_realm || !$database_available): ?>
        <div class="empty-online max-w-2xl mx-auto p-10 text-center text-[#91a0b4]">
            <i class="fas fa-server text-3xl text-[#f2cf5b] mb-4"></i>
            <p><?php echo translate('online_unavailable', 'The realm roster is temporarily unavailable.'); ?></p>
        </div>
    <?php elseif (!$players): ?>
        <div class="empty-online max-w-2xl mx-auto p-10 text-center text-[#91a0b4]">
            <i class="fas fa-moon text-3xl text-[#f2cf5b] mb-4"></i>
            <p><?php echo translate('online_empty', 'No adventurers are connected to this realm right now.'); ?></p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($players as $player):
                $class_id = (int)$player['class'];
                $class_name = forever_class_name($class_id);
                $race_name = forever_race_name((int)$player['race']);
                $class_color = $class_colors[$class_id] ?? '#c9a227';
                $race_icon = $base_path . 'img/accountimg/race/' . forever_race_icon_file((int)$player['race'], (int)$player['gender']);
                $armory_url = $base_path . 'character?guid=' . (int)$player['guid'] . '&realm=' . (int)$selected_realm['id'] . '&lang=' . urlencode($langCode);
            ?>
                <a href="<?php echo htmlspecialchars($armory_url, ENT_QUOTES, 'UTF-8'); ?>" class="player-card group" style="--class-color: <?php echo $class_color; ?>;">
                    <span class="race-frame">
                        <img src="<?php echo htmlspecialchars($race_icon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($race_name, ENT_QUOTES, 'UTF-8'); ?>">
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2 mb-1">
                            <span class="online-dot"></span>
                            <strong class="text-lg truncate group-hover:text-[#fff0ac] transition-colors"><?php echo htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </span>
                        <span class="flex items-center gap-2 text-sm" style="color: <?php echo $class_color; ?>;">
                            <?php if (isset($class_icons[$class_id])): ?>
                                <img class="class-icon" src="<?php echo htmlspecialchars($base_path . 'img/accountimg/class/' . $class_icons[$class_id], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                            <?php endif; ?>
                            <?php echo htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <span class="block text-xs text-[#7f8ea2] mt-1"><?php echo translate('label_level', 'Level'); ?> <?php echo (int)$player['level']; ?> · <?php echo htmlspecialchars($race_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                    <i class="fas fa-chevron-right text-[#c9a227]/60 group-hover:text-[#f2cf5b] group-hover:translate-x-1 transition-all"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include_once $project_root . 'includes/footer.php'; ?>
