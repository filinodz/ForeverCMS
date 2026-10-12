<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/armory_playerbots.php';

// Load Armory Configuration
$armoryConfigFile = $project_root . 'includes/armory_config.php';
$armoryConfig = [];
if (file_exists($armoryConfigFile)) {
    require_once $armoryConfigFile;
}

$soloPvPDisplay = $armoryConfig['solo_pvp_display'] ?? 'all';

// Build SQL filter for human-only players.
// Graceful fallback: when the Playerbots table (or its database) is not
// available, the filter is simply skipped and the normal leaderboard is
// shown — no errors or database details reach visitors.
$botWhereClause = '';
if ($soloPvPDisplay === 'humans_only' && isset($char_db)) {
    if (armory_playerbots_table_exists($char_db)) {
        $botWhereClause = " AND NOT EXISTS (
            SELECT 1 FROM `acore_playerbots`.`playerbots_account_type` pat 
            WHERE pat.account_id = c.account
        ) ";
    }
}

$search = '';
$search_error = '';

if (isset($_GET['search'])) {
    $search = trim((string)$_GET['search']);

    // Limit length
    $search = substr($search, 0, 12);

    // Minimum length
    if (strlen($search) > 0 && strlen($search) < 2) {
        $search_error = translate('solo_pvp_search_min', 'Please enter at least 2 characters.');
        $search = '';
    }
}

$players = [];
$dbErrorOccurred = false;

// --- Prepared Statement & DB Fetch Refactor ---
try {
    if ($search !== '') {
        $sql = "
            SELECT c.guid, c.name, c.race, c.class, c.level, c.gender, c.totalKills, g.name AS guild_name
            FROM characters c
            LEFT JOIN guild_member gm ON c.guid = gm.guid
            LEFT JOIN guild g ON gm.guildid = g.guildid
            WHERE c.name LIKE ? {$botWhereClause}
            ORDER BY c.level DESC, c.totalKills DESC
            LIMIT 50
        ";

        if ($stmt = $char_db->prepare($sql)) {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $players[] = [
                        'guid'       => (int)$row['guid'],
                        'name'       => (string)$row['name'],
                        'race'       => (int)$row['race'],
                        'class'      => (int)$row['class'],
                        'gender'     => (int)$row['gender'],
                        'level'      => (int)$row['level'],
                        'kills'      => (int)$row['totalKills'],
                        'guild_name' => $row['guild_name'] ?? translate('solo_pvp_no_guild', 'No Guild')
                    ];
                }
                $result->free();
            }
            $stmt->close();
        }
    } else {
        // Non-search query stays standard
        $sql = "
            SELECT c.guid, c.name, c.race, c.class, c.level, c.gender, c.totalKills, g.name AS guild_name
            FROM characters c
            LEFT JOIN guild_member gm ON c.guid = gm.guid
            LEFT JOIN guild g ON gm.guildid = g.guildid
            WHERE 1=1 {$botWhereClause}
            ORDER BY c.level DESC, c.totalKills DESC
            LIMIT 50
        ";
        $result = $char_db->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $players[] = [
                    'guid'       => (int)$row['guid'],
                    'name'       => (string)$row['name'],
                    'race'       => (int)$row['race'],
                    'class'      => (int)$row['class'],
                    'gender'     => (int)$row['gender'],
                    'level'      => (int)$row['level'],
                    'kills'      => (int)$row['totalKills'],
                    'guild_name' => $row['guild_name'] ?? translate('solo_pvp_no_guild', 'No Guild')
                ];
            }
            $result->free();
        }
    }
} catch (Throwable $e) {
    error_log("Solo PvP Query Exception: " . $e->getMessage());
    $dbErrorOccurred = true;
}
// Faction helper
function getFaction($race) {
    $alliance = [1, 3, 4, 7, 11, 22, 25, 29];
    return in_array((int)$race, $alliance, true) ? 'Alliance' : 'Horde';
}

// Image paths
function factionIcon($race) {
    global $base_path;
    $faction = getFaction($race);
    return $base_path . "img/accountimg/faction/" . strtolower($faction) . ".png";
}
function raceIcon($race, $gender) {
    global $base_path;
    $genderFolder = ((int)$gender === 0) ? 'male' : 'female';
    $raceMap = [
        1 => 'human', 2 => 'orc', 3 => 'dwarf', 4 => 'nightelf',
        5 => 'undead', 6 => 'tauren', 7 => 'gnome', 8 => 'troll',
        9 => 'goblin', 10 => 'bloodelf', 11 => 'draenei',
        22 => 'worgen', 25 => 'pandaren_alliance', 26 => 'pandaren_horde',
        29 => 'voidelf'
    ];
    $raceInt = (int)$race;
    $raceName = $raceMap[$raceInt] ?? 'unknown';
    return $base_path . "img/accountimg/race/{$genderFolder}/{$raceName}.png";
}
function classIcon($class) {
    global $base_path;
    $classMap = [
        1 => 'warrior', 2 => 'paladin', 3 => 'hunter', 4 => 'rogue',
        5 => 'priest', 6 => 'deathknight', 7 => 'shaman', 8 => 'mage',
        9 => 'warlock', 10 => 'monk', 11 => 'druid', 12 => 'demonhunter'
    ];
    $classInt = (int)$class;
    $className = $classMap[$classInt] ?? 'unknown';
    return $base_path . "img/accountimg/class/{$className}.webp";
}

// Site settings & translations
require_once $project_root . 'includes/config.settings.php';

// Page configuration
$page_title = $site_title_name ." ". translate('solo_pvp_page_title', 'Top 50 Players');
$page_class = 'armory';

ob_start();
?>
<style>
        body {
            background: url('<?php echo htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8'); ?>img/backgrounds/bg-armory.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            padding-top: 112px;
            margin: 0;
            position: relative;
        }
        
        .arena-content {
            position: relative;
            z-index: 1;
        }
        
        .glass-container {
            background: rgba(5, 7, 11, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(201,162,39,.22);
            border-radius: 0;
            padding: 2.5rem 2.5rem;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.8), inset 0 0 60px rgba(0,0,0,.25);
            position: relative;
        }
        
        .glass-container::before {
            content: ''; position: absolute; inset: 5px;
            border: 1px solid rgba(201,162,39,.14);
            pointer-events: none;
        }
        
        .glass-container::after {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background:
                linear-gradient(#e8c552,#e8c552) left top / 18px 2px,
                linear-gradient(#e8c552,#e8c552) left top / 2px 18px,
                linear-gradient(#e8c552,#e8c552) right top / 18px 2px,
                linear-gradient(#e8c552,#e8c552) right top / 2px 18px,
                linear-gradient(#e8c552,#e8c552) left bottom / 18px 2px,
                linear-gradient(#e8c552,#e8c552) left bottom / 2px 18px,
                linear-gradient(#e8c552,#e8c552) right bottom / 18px 2px,
                linear-gradient(#e8c552,#e8c552) right bottom / 2px 18px;
            background-repeat: no-repeat;
        }
        
        .table-container {
            scrollbar-width: thin;
            scrollbar-color: #f2cf5b #1f2937;
            font-family: 'Arial', sans-serif;
            border-radius: 0;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            display: block;
            width: 100%;
        }
        
        .table-container::-webkit-scrollbar { width: 8px; height: 8px; }
        .table-container::-webkit-scrollbar-track { background: #1f2937; border-radius: 4px; }
        .table-container::-webkit-scrollbar-thumb { background: #f2cf5b; border-radius: 4px; }
        .table-container table { min-width: 700px; width: 100%; border-collapse: collapse; }
        
        .table-container th, .table-container td { padding: 12px 8px; white-space: nowrap; }
        
        @media (max-width: 640px) {
            .table-container th, .table-container td { padding: 10px 6px; font-size: 0.75rem; }
        }

        .rank-1 {
            background: linear-gradient(135deg, rgba(242, 207, 82, 0.35), rgba(201, 162, 39, 0.25), rgba(242, 207, 82, 0.35)) !important;
            border-left: 4px solid #f2cf5b;
            box-shadow: inset 0 0 30px rgba(242, 207, 82, 0.15);
        }
        .rank-1 td { color: #fff5d6 !important; text-shadow: 0 0 20px rgba(242, 207, 82, 0.3); }
        .rank-1 td:first-child { color: #f2cf5b !important; font-size: 1.2em; text-shadow: 0 0 30px rgba(242, 207, 82, 0.5); }
        .rank-1:hover {
            background: linear-gradient(135deg, rgba(242, 207, 82, 0.5), rgba(201, 162, 39, 0.4), rgba(242, 207, 82, 0.5)) !important;
            filter: brightness(1.1);
            transition: all 0.3s ease-in-out;
            cursor: pointer;
            box-shadow: 0 0 40px rgba(242, 207, 82, 0.2);
        }

        .rank-2 {
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.3), rgba(160, 160, 160, 0.2), rgba(192, 192, 192, 0.3)) !important;
            border-left: 4px solid #c0c0c0;
            box-shadow: inset 0 0 30px rgba(192, 192, 192, 0.1);
        }
        .rank-2 td { color: #f0f0f0 !important; text-shadow: 0 0 20px rgba(192, 192, 192, 0.2); }
        .rank-2 td:first-child { color: #c0c0c0 !important; font-size: 1.1em; text-shadow: 0 0 30px rgba(192, 192, 192, 0.4); }
        .rank-2:hover {
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.45), rgba(160, 160, 160, 0.35), rgba(192, 192, 192, 0.45)) !important;
            filter: brightness(1.1);
            transition: all 0.3s ease-in-out;
            cursor: pointer;
            box-shadow: 0 0 40px rgba(192, 192, 192, 0.15);
        }

        .rank-3 {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.35), rgba(180, 100, 30, 0.25), rgba(205, 127, 50, 0.35)) !important;
            border-left: 4px solid #cd7f32;
            box-shadow: inset 0 0 30px rgba(205, 127, 50, 0.1);
        }
        .rank-3 td { color: #f5e6d3 !important; text-shadow: 0 0 20px rgba(205, 127, 50, 0.2); }
        .rank-3 td:first-child { color: #cd7f32 !important; font-size: 1.05em; text-shadow: 0 0 30px rgba(205, 127, 50, 0.4); }
        .rank-3:hover {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.5), rgba(180, 100, 30, 0.4), rgba(205, 127, 50, 0.5)) !important;
            filter: brightness(1.1);
            transition: all 0.3s ease-in-out;
            cursor: pointer;
            box-shadow: 0 0 40px rgba(205, 127, 50, 0.15);
        }
        
        .top5 { background: linear-gradient(to right, rgba(20, 30, 60, 0.7), rgba(10, 20, 50, 0.6)) !important; }
        .top5:hover {
            background: linear-gradient(to right, rgba(30, 50, 90, 0.8), rgba(20, 40, 80, 0.7)) !important;
            filter: brightness(1.15);
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        
        .player-row:hover {
            background: rgba(242, 207, 82, 0.15) !important;
            transition: background-color 0.2s ease-in-out;
            cursor: pointer;
        }
        .player-row { background: rgba(0, 0, 0, 0.4); transition: all 0.2s ease-in-out; }
        
        #search-btn {
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #f2cf5b, #c9a227);
            border: 2px solid #f2cf5b;
            color: #1a1200;
            font-weight: 700;
            text-shadow: 0 1px 0 rgba(255,255,255,0.2);
        }
        
        #search-btn:hover {
            transform: scale(1.05);
            background: linear-gradient(135deg, #f6d478, #d4b040);
            box-shadow: 0 0 30px rgba(242, 207, 82, 0.5);
            border-color: #f6d478;
        }
        
        #search-btn i { color: #1a1200; }
        
        .reset-btn {
            background: rgba(10, 14, 22, 0.6);
            border: 2px solid rgba(201, 162, 39, 0.3);
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .reset-btn:hover {
            transform: scale(1.05);
            border-color: #f2cf5b;
            background: rgba(242, 207, 82, 0.1);
            color: #f2cf5b;
            box-shadow: 0 0 20px rgba(242, 207, 82, 0.15);
        }
        
        .player-link { color: #ffffff; text-decoration: none; transition: all 0.2s ease; }
        .player-link:hover { text-decoration: underline; color: #f2cf5b; }
        
        .wow-title {
            font-family: 'Cinzel', serif;
            font-weight: 900;
            background: linear-gradient(180deg, #fff7d6 0%, #f2cf5b 35%, #c9a227 62%, #8a6a14 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,.9));
            letter-spacing: .02em;
        }
        
        .medal-gold { color: #f2cf5b; font-size: 1.4em; filter: drop-shadow(0 0 10px rgba(242, 207, 82, 0.5)); }
        .medal-silver { color: #c0c0c0; font-size: 1.3em; filter: drop-shadow(0 0 10px rgba(192, 192, 192, 0.4)); }
        .medal-bronze { color: #cd7f32; font-size: 1.2em; filter: drop-shadow(0 0 10px rgba(205, 127, 50, 0.4)); }
        
        @media (max-width: 767px) {
            body { padding-top: 96px; }
            .glass-container { padding: 1.5rem 0.75rem; }
        }
    </style>
<?php
$page_head = ob_get_clean();

require_once $project_root . 'includes/header.php';
?>
<div class="arena-content min-h-screen flex items-start justify-center px-4 md:px-8 py-8">
    <div class="container mx-auto max-w-7xl px-2 sm:px-4">
        <!-- Main Container -->
        <div class="glass-container">
            
            <!-- Title -->
            <h1 class="wow-title text-3xl md:text-5xl font-bold text-center mb-6">
                <?php echo translate('solo_pvp_title', 'Top 50 Players'); ?>
            </h1>

            <!-- Navigation -->
            <?php include_once $project_root . 'includes/arenanavbar.php'; ?>

            <!-- Search Error -->
            <?php if (!empty($search_error)): ?>
                <div class="mb-4 text-center text-red-400 font-semibold text-sm">
                    <?php echo htmlspecialchars($search_error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($dbErrorOccurred): ?>
                <div class="mb-4 text-center text-red-400 font-semibold text-sm">
                    <?php echo translate('solo_pvp_db_error', 'Unable to retrieve leaderboard data at this time.'); ?>
                </div>
            <?php endif; ?>

            <!-- Search Form -->
            <form method="get" class="mb-8 flex flex-col sm:flex-row justify-center items-center gap-3">
                <input 
                    type="text" 
                    name="search" 
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="<?php echo translate('solo_pvp_search_placeholder', 'Search character name...'); ?>"
                    maxlength="12"
                    class="w-full sm:w-80 px-4 py-2.5 rounded-lg bg-black/60 text-white border-2 border-[rgba(201,162,39,0.4)] focus:outline-none focus:border-[#f2cf5b] focus:shadow-[0_0_15px_rgba(242,207,82,0.2)] transition-all duration-300 placeholder:text-gray-400 text-sm"
                >
                <button 
                    type="submit"
                    id="search-btn"
                    class="px-6 py-2.5 rounded-lg transition-all duration-300 shadow-lg shadow-[rgba(242,207,82,0.3)] flex items-center gap-2"
                >
                    <i class="fas fa-search"></i> <?php echo translate('solo_pvp_search_btn', 'Search'); ?>
                </button>

                <?php if ($search !== ''): ?>
                    <a href="<?php echo htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8'); ?>armory/solo_pvp" class="reset-btn px-5 py-2.5 rounded-lg transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-times"></i> <?php echo translate('solo_pvp_reset_btn', 'Reset'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <div class="table-container overflow-x-auto border border-[rgba(201,162,39,0.15)] shadow-2xl">
                <table class="w-full text-sm md:text-base text-center min-w-[700px]">
                    <thead class="bg-gradient-to-r from-[rgba(201,162,39,0.9)] to-[rgba(160,130,30,0.9)] text-amber-100 uppercase text-xs md:text-sm">
                        <tr>
                            <th class="py-4 px-3 md:px-6 font-bold whitespace-nowrap"><?php echo translate('solo_pvp_rank', 'Rank'); ?></th>
                            <th class="py-4 px-3 md:px-6 font-bold text-left whitespace-nowrap"><?php echo translate('solo_pvp_name', 'Name'); ?></th>
                            <th class="py-4 px-3 md:px-6 font-bold text-left hidden sm:table-cell whitespace-nowrap"><?php echo translate('solo_pvp_guild', 'Guild'); ?></th>
                            <th class="py-4 px-3 md:px-6 font-bold whitespace-nowrap"><?php echo translate('solo_pvp_faction', 'Faction'); ?></th>
                            <th class="py-4 px-3 md:px-6 font-bold hidden md:table-cell whitespace-nowrap"><?php echo translate('solo_pvp_race', 'Race'); ?></th>
                            <th class="py-4 px-3 md:px-6 font-bold hidden md:table-cell whitespace-nowrap"><?php echo translate('solo_pvp_class', 'Class'); ?></th>
                            <th class="py-4 px-3 md:px-6 font-bold whitespace-nowrap"><?php echo translate('solo_pvp_level', 'Level'); ?></th>
                            <th class="py-4 px-3 md:px-6 font-bold whitespace-nowrap"><?php echo translate('solo_pvp_kills', 'PvP Kills'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($players) === 0): ?>
                            <tr>
                                <td colspan="8" class="py-8 px-4 text-lg text-[#f2cf5b] font-bold text-center">
                                    <i class="fas fa-users-slash text-3xl block mb-3 text-[rgba(201,162,39,0.3)]"></i>
                                    <?php echo translate('solo_pvp_no_players', 'No players found.'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $rank = 1;
                            $playerCount = count($players);
                            foreach ($players as $p):
                                $guid = $p['guid'];
                                $charUrl = htmlspecialchars($base_path . 'character?guid=' . $guid, ENT_QUOTES, 'UTF-8');
                                $pName = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
                                $guildName = htmlspecialchars($p['guild_name'], ENT_QUOTES, 'UTF-8');

                                if ($rank === 1) {
                                    $rowClass = 'rank-1';
                                    $rankDisplay = '<span class="medal-gold">🥇</span>';
                                } elseif ($rank === 2) {
                                    $rowClass = 'rank-2';
                                    $rankDisplay = '<span class="medal-silver">🥈</span>';
                                } elseif ($rank === 3) {
                                    $rowClass = 'rank-3';
                                    $rankDisplay = '<span class="medal-bronze">🥉</span>';
                                } elseif ($rank <= 5 && $playerCount >= 5) {
                                    $rowClass = 'top5';
                                    $rankDisplay = '#' . $rank;
                                } else {
                                    $rowClass = 'player-row';
                                    $rankDisplay = '#' . $rank;
                                }
                            ?>
                                <tr class="<?php echo $rowClass; ?> transition-all duration-200 border-b border-gray-700/30 last:border-0" onclick="window.location='<?php echo $charUrl; ?>';" style="cursor:pointer;">
                                    <td class="py-3.5 px-3 md:px-6 font-bold text-[#f2cf5b] whitespace-nowrap"><?php echo $rankDisplay; ?></td>
                                    <td class="py-3.5 px-3 md:px-6 text-left whitespace-nowrap">
                                        <a href="<?php echo $charUrl; ?>" class="player-link font-semibold hover:text-[#f2cf5b] transition-colors duration-200">
                                            <?php echo $pName; ?>
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-3 md:px-6 text-left hidden sm:table-cell text-gray-300 whitespace-nowrap"><?php echo $guildName; ?></td>
                                    <td class="py-3.5 px-3 md:px-6 whitespace-nowrap">
                                        <img src="<?php echo htmlspecialchars(factionIcon($p['race']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo translate('solo_pvp_faction_alt', 'Faction'); ?>" class="inline-block w-6 h-6 rounded-full shadow-md">
                                    </td>
                                    <td class="py-3.5 px-3 md:px-6 hidden md:table-cell whitespace-nowrap">
                                        <img src="<?php echo htmlspecialchars(raceIcon($p['race'], $p['gender']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo translate('solo_pvp_race_alt', 'Race'); ?>" class="inline-block w-6 h-6 rounded-full shadow-md">
                                    </td>
                                    <td class="py-3.5 px-3 md:px-6 hidden md:table-cell whitespace-nowrap">
                                        <img src="<?php echo htmlspecialchars(classIcon($p['class']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo translate('solo_pvp_class_alt', 'Class'); ?>" class="inline-block w-6 h-6 rounded-full shadow-md">
                                    </td>
                                    <td class="py-3.5 px-3 md:px-6 font-bold text-[#f2cf5b] whitespace-nowrap"><?php echo $p['level']; ?></td>
                                    <td class="py-3.5 px-3 md:px-6 font-extrabold text-[#2ecc71] text-base md:text-lg whitespace-nowrap"><?php echo $p['kills']; ?></td>
                                </tr>
                            <?php 
                                $rank++;
                            endforeach; 
                            ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer note -->
            <div class="mt-6 text-center text-gray-400 text-xs md:text-sm">
                <i class="fas fa-mouse-pointer mr-2 text-[rgba(201,162,39,0.4)]"></i>
                <?php echo translate('solo_pvp_footer', 'Click on any row to view character details.'); ?>
            </div>
        </div>
    </div>
</div>

<?php include_once $project_root . 'includes/footer.php'; ?>