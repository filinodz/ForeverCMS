<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/armory_playerbots.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: {$base_path}login");
    exit;
}

$page_class = 'realm';
$page_title = translate('page_title_realm', 'Realm Configuration');
$page_meta_description = translate('page_description_realm', 'Realm Configuration for Sahtout WoW Server');
$page_meta_robots = 'noindex';
$page_body_class = 'min-h-screen text-[#d8d8d8] bg-[#05070b] bg-fixed';

$errors = [];
$success = false;
$playerbotsMissing = false;
$realmsFile = $project_root . 'includes/realm_config.php';

$defaultLogo = 'img/logos/realm1_logo.webp';

require_once $project_root . 'includes/realm_config.php';

// Select the realm explicitly on this page. Staff should not have to change the
// public site realm selector just to configure PvP, RP or Hardcore.
$requestedRealmId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['realm_id'])) {
    $requestedRealmId = (int)$_POST['realm_id'];
} elseif (isset($_GET['realm']) && ctype_digit((string)$_GET['realm'])) {
    $requestedRealmId = (int)$_GET['realm'];
} elseif (isset($current_realm['id'])) {
    $requestedRealmId = (int)$current_realm['id'];
}

$realmIndex = 0;
foreach ($realmlist as $realmKey => $realmEntry) {
    if ((int)$realmEntry['id'] === $requestedRealmId) {
        $realmIndex = $realmKey;
        break;
    }
}

$currentRealm = $realmlist[$realmIndex] ?? [
    'name' => 'Forever',
    'address' => '127.0.0.1',
    'check_address' => '127.0.0.1',
    'port' => 8085,
    'logo' => $defaultLogo
];

// Backward compatible: when an older config only defines 'address',
// use it as the status check address until the admin saves the new field.
$currentCheckAddress = $currentRealm['check_address'] ?? $currentRealm['address'] ?? '127.0.0.1';

/**
 * Accepts IPv4 addresses, IPv6 addresses (including URL-style brackets),
 * and hostnames/domain names (including "localhost" and internal names).
 * Uses PHP's native filter_var() validation instead of a permissive whitelist.
 */
function isValidRealmHost($value) {
    if (empty($value) || strlen($value) > 253) {
        return false;
    }
    $candidate = trim($value);
    // Allow an IPv6 literal wrapped in square brackets, e.g. [::1]
    if (strpos($candidate, '[') === 0 && substr($candidate, -1) === ']') {
        $candidate = substr($candidate, 1, strlen($candidate) - 2);
    }
    if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
        return true;
    }
    return filter_var($candidate, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
}

$currentPlayerDisplay = $currentRealm['player_display'] ?? 'all';
$currentShowStatus = !array_key_exists('show_status', $currentRealm) || (bool)$currentRealm['show_status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    $validCsrf = !empty($csrfToken) && hash_equals($csrfToken, (string)($_SESSION['csrf_token'] ?? ''));

    if (!$validCsrf) {
        // Reject the request entirely: do not process any POST data.
        $errors[] = translate('err_invalid_csrf', 'Invalid CSRF token.');
    } else {
        $realmName = trim($_POST['realm_name'] ?? '');
        $realmAddress = trim($_POST['realm_address'] ?? '');
        $realmCheckAddress = trim($_POST['realm_check_address'] ?? $currentCheckAddress);
        $realmPort = (int) ($_POST['realm_port'] ?? 0);
        $realmRaPort = (int)($_POST['realm_ra_port'] ?? ($currentRealm['ra_port'] ?? 3443));
        $realmType = strtolower(trim((string)($_POST['realm_type'] ?? ($currentRealm['type'] ?? 'normal'))));
        $realmCharDb = trim((string)($_POST['realm_char_db'] ?? ($currentRealm['char_db'] ?? 'characters')));
        $playerDisplay = $_POST['player_display'] ?? 'all';
        $showStatus = isset($_POST['show_status']);
        $logo_path = $currentRealm['logo'] ?? $defaultLogo;

        if (!in_array($playerDisplay, ['all', 'separate'], true)) {
            $playerDisplay = 'all';
        }

        if (empty($realmName)) {
            $errors[] = translate('err_realm_name_required', 'Realm Name is required.');
        }
        if (empty($realmAddress)) {
            $errors[] = translate('err_realm_ip_required', 'Realm Address / Host is required.');
        } elseif (!isValidRealmHost($realmAddress)) {
            $errors[] = translate('err_realm_ip_invalid', 'Realm Address / Host is invalid.');
        }
        if (empty($realmCheckAddress)) {
            $errors[] = translate('err_realm_check_address_required', 'Status Check Address is required.');
        } elseif (!isValidRealmHost($realmCheckAddress)) {
            $errors[] = translate('err_realm_check_address_invalid', 'Status Check Address contains invalid characters.');
        }
        if ($realmPort <= 0 || $realmPort > 65535) {
            $errors[] = translate('err_realm_port_invalid', 'Realm Port must be a valid number (1-65535).');
        }
        if ($realmRaPort <= 0 || $realmRaPort > 65535) {
            $errors[] = translate('err_realm_ra_port_invalid', 'Remote administration port must be between 1 and 65535.');
        }
        if (!in_array($realmType, ['normal', 'pvp', 'rp', 'hardcore'], true)) {
            $errors[] = translate('err_realm_type_invalid', 'Realm type is invalid.');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $realmCharDb)) {
            $errors[] = translate('err_realm_database_invalid', 'Characters database name may only contain letters, numbers and underscores.');
        } else {
            $dbCheck = $site_db->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            if ($dbCheck) {
                $dbCheck->bind_param('s', $realmCharDb);
                $dbCheck->execute();
                $dbCheck->bind_result($databaseExists);
                $dbCheck->fetch();
                $dbCheck->close();
                if (!(int)$databaseExists) {
                    $errors[] = sprintf(translate('err_realm_database_missing', 'Characters database "%s" does not exist.'), $realmCharDb);
                }
            }
        }

   if (empty($errors)) {
    // Handle logo upload securely
    if (isset($_FILES['realm_logo']) && $_FILES['realm_logo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['realm_logo']['tmp_name'];
        $file_name  = $_FILES['realm_logo']['name'];
        $file_size  = $_FILES['realm_logo']['size'];
        $file_ext   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowedExt = ['png', 'jpg', 'jpeg', 'webp'];
        $max_size   = 2 * 1024 * 1024;

        if ($file_size > $max_size) {
            $errors[] = translate('error_realm_logo_too_large', 'Realm logo size exceeds 2MB.');
        } elseif (!in_array($file_ext, $allowedExt, true)) {
            $errors[] = translate('error_invalid_realm_logo_type', 'Invalid file type. Only PNG, JPG, or WebP allowed.');
        } else {
            $imageInfo    = @getimagesize($file_tmp);
            $detectedMime = $imageInfo['mime'] ?? '';

            if (!in_array($detectedMime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                $errors[] = translate('error_invalid_realm_logo_type', 'Invalid file type. Only PNG, JPG, or WebP allowed.');
            } else {
                $upload_dir = $project_root . 'img/logos/';
                if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true)) {
                    $errors[] = translate('error_realm_logo_upload_failed', 'Upload directory is not accessible or writable.');
                } else {
                    // Cache buster filename to prevent browser cache stalls & file collision
                    $new_file_name = 'realm_logo_' . time() . '.' . $file_ext;
                    $destination   = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $destination)) {
                        $logo_path = "img/logos/{$new_file_name}";
                    } else {
                        $errors[] = translate('error_realm_logo_upload_failed', 'Failed to upload realm logo.');
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $newRealmList = array_values($realmlist);
        $newRealmList[$realmIndex] = array_merge($currentRealm, [
            'name'           => $realmName,
            'address'        => $realmAddress,
            'check_address'  => $realmCheckAddress,
            'port'           => $realmPort,
            'ra_port'        => $realmRaPort,
            'type'           => $realmType,
            'char_db'        => $realmCharDb,
            'logo'           => $logo_path,
            'player_display' => $playerDisplay,
            'show_status'    => $showStatus
        ]);

        $configPhp  = "<?php\n";
        $configPhp .= "if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }\n\n";
        $configPhp .= "// The Forever realms. \"id\" is auth.realmlist.id, \"char_db\" the realm's characters database,\n";
        $configPhp .= "// \"ra_port\" its worldserver's Ra.Port, \"port\" its WorldServerPort.\n";
        $configPhp .= '$realmlist = ' . var_export($newRealmList, true) . ";\n";

        $configDir = dirname($realmsFile);
        $tmpConfigFile = $configDir . '/.realm_config_' . bin2hex(random_bytes(8)) . '.tmp';

        if (file_put_contents($tmpConfigFile, $configPhp, LOCK_EX) === false) {
            @unlink($tmpConfigFile);
            $errors[] = sprintf(translate('err_write_realm_config', 'Cannot write realm configuration file: %s'), $realmsFile);
        } else {
            $renamed = false;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (PHP_OS_FAMILY === 'Windows' && file_exists($realmsFile)) {
                    @unlink($realmsFile);
                }
                if (@rename($tmpConfigFile, $realmsFile)) {
                    $renamed = true;
                    break;
                }
                usleep(50000);
            }

            if (file_exists($tmpConfigFile)) {
                @unlink($tmpConfigFile);
            }

            if (!$renamed) {
                $errors[] = sprintf(translate('err_write_realm_config', 'Cannot write realm configuration file: %s'), $realmsFile);
            } else {
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($realmsFile, true);
                }

                $success = true;
                $realmlist = $newRealmList;
                $currentRealm = $newRealmList[$realmIndex];
                $currentCheckAddress = $currentRealm['check_address'];
                $currentPlayerDisplay = $currentRealm['player_display'];
                $currentShowStatus = (bool)$currentRealm['show_status'];
                $playerbotsMissing = !armory_playerbots_table_exists($char_db ?? null);
            }
        }
    }
}
    }
}
ob_start();
?>
    <style>
        /* Only keep what Tailwind CANNOT do */

        /* Page background (was inline on <body>) */
        body {
            background-image:
                radial-gradient(1000px 700px at -10% 35%, rgba(59,130,246,.14), transparent 65%),
                radial-gradient(800px 600px at -5% 85%, rgba(124,58,237,.10), transparent 70%),
                linear-gradient(180deg, #0a0e16 0%, #060810 45%, #03040a 100%);
        }

        /* Font families */
        * { font-family: 'Inter', sans-serif; }
        .wow-title, .section-title, .form-label { font-family: 'Cinzel', serif; }
        
        /* Panel with gold corners AND inner border */
        .panel-gold-corners {
            position: relative;
        }
        
        /* Outer gold corner decorations */
        .panel-gold-corners::after {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
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
        
        /* Inner border inset */
        .panel-gold-corners::before {
            content: '';
            position: absolute;
            inset: 5px;
            border: 1px solid rgba(201,162,39,.14);
            pointer-events: none;
        }
        
        /* Custom clip-path for buttons */
        .btn-clip {
            clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
        }
        .realm-switcher { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; }
        .realm-switch {
            position:relative; display:flex; align-items:center; gap:.8rem; min-width:0; padding:1rem;
            color:#9aa8ba; background:rgba(7,10,16,.82); border:1px solid rgba(133,151,176,.16);
            text-decoration:none; transition:border-color .2s ease, transform .2s ease, background .2s ease;
        }
        .realm-switch:hover { transform:translateY(-2px); border-color:rgba(242,207,82,.42); }
        .realm-switch.active { color:#fff1b2; border-color:rgba(242,207,82,.72); background:linear-gradient(145deg,rgba(201,162,39,.16),rgba(8,11,18,.9)); box-shadow:0 12px 30px rgba(0,0,0,.28); }
        .realm-switch img { width:42px; height:42px; object-fit:contain; }
        .realm-switch small { display:block; margin-top:.2rem; color:#68778b; font-size:.67rem; text-transform:uppercase; letter-spacing:.09em; }
        .realm-switch .visibility-dot { position:absolute; top:.6rem; right:.6rem; width:7px; height:7px; border-radius:50%; background:#566170; }
        .realm-switch .visibility-dot.public { background:#55d68a; box-shadow:0 0 10px rgba(85,214,138,.65); }
        .realm-select { appearance:none; background:#0a0e16; color:#e5e7eb; color-scheme:dark; }
        .realm-select option { color:#e5e7eb; background:#0a0e16; }
        @media (max-width:1100px) { .realm-switcher { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:560px) { .realm-switcher { grid-template-columns:1fr; } }
    </style>
<?php
$page_head = ob_get_clean();

include $project_root . 'includes/header.php';
?>

    <div class="flex relative min-h-screen">
        
        <!-- Sidebar -->
        <?php include $project_root . 'includes/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content-area flex-1 p-3 sm:p-4 md:p-6 lg:p-8 transition-all duration-300 lg:ml-[280px]">
            <div class="max-w-[1400px] mx-auto px-1 sm:px-4 md:px-6 lg:px-8 xl:px-10">
                <div class="space-y-4 md:space-y-6 lg:space-y-8">
                    
                    <h1 class="wow-title text-2xl md:text-3xl lg:text-4xl font-black 
                               bg-gradient-to-b from-[#fff7d6] via-[#f2cf5b] via-[#c9a227] to-[#8a6a14] 
                               bg-clip-text text-transparent drop-shadow-[0_3px_6px_rgba(0,0,0,.85)]">
                        <?php echo translate('page_title_realm', 'Realm Configuration'); ?>
                    </h1>

                    <!-- Settings Navbar -->
                    <?php include $project_root . 'pages/admin/settings/settings_navbar.php'; ?>

                    <section aria-label="<?php echo htmlspecialchars(translate('realm_choose_to_edit', 'Choose a realm to edit'), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div>
                                <h2 class="text-[#f2cf5b] font-bold tracking-wide"><?php echo translate('realm_choose_to_edit', 'Choose a realm to edit'); ?></h2>
                                <p class="text-xs text-[#728096] mt-1"><?php echo translate('realm_choose_to_edit_desc', 'Every realm has its own visibility, database and connection settings.'); ?></p>
                            </div>
                            <span class="text-[10px] uppercase tracking-[.16em] text-[#68778b]"><?php echo count($realmlist); ?> realms</span>
                        </div>
                        <div class="realm-switcher">
                            <?php foreach ($realmlist as $configuredRealm):
                                $realmIsActive = (int)$configuredRealm['id'] === (int)$currentRealm['id'];
                                $realmEditUrl = $base_path . 'admin/settings/realm?realm=' . (int)$configuredRealm['id'] . '&lang=' . urlencode($langCode);
                            ?>
                                <a class="realm-switch <?php echo $realmIsActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($realmEditUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="visibility-dot <?php echo !empty($configuredRealm['show_status']) ? 'public' : ''; ?>"></span>
                                    <img src="<?php echo htmlspecialchars($base_path . ($configuredRealm['logo'] ?? $defaultLogo), ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                    <span class="min-w-0">
                                        <strong class="block truncate"><?php echo htmlspecialchars($configuredRealm['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <small>#<?php echo (int)$configuredRealm['id']; ?> · <?php echo htmlspecialchars(strtoupper($configuredRealm['type'] ?? 'normal'), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($configuredRealm['char_db'] ?? 'characters', ENT_QUOTES, 'UTF-8'); ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- Success / Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="bg-[#e74c3c]/15 border border-[#e74c3c]/40 text-[#e74c3c] 
                                    p-4 rounded-sm flex items-start gap-3">
                            <i class="fas fa-exclamation-circle text-xl mt-0.5"></i>
                            <div>
                                <strong><?php echo translate('err_fix_errors', 'Please fix the following errors:'); ?></strong>
                                <?php foreach ($errors as $err): ?>
                                    <div class="text-sm mt-1">• <?php echo htmlspecialchars($err); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="bg-[#2ecc71]/15 border border-[#2ecc71]/40 text-[#2ecc71] 
                                    p-4 rounded-sm flex items-center gap-3">
                            <i class="fas fa-check-circle text-xl"></i>
                            <span><?php echo translate('msg_realm_saved', 'Realm configuration saved successfully!'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success && $playerbotsMissing): ?>
                        <!-- Admin/moderator-only: the optional Playerbots database/table is not detected.
                             This page already blocks guests/normal users via the role check at the top. -->
                        <div class="bg-amber-500/15 border border-amber-500/40 text-amber-400 
                                    p-4 rounded-sm flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-xl mt-0.5"></i>
                            <div>
                                <strong><?php echo htmlspecialchars(translate('playerbots_db_table_missing', 'Playerbots database/table not detected. Bot statistics are unavailable until acore_playerbots.playerbots_account_type is available.'), ENT_QUOTES); ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Realm Configuration Form -->
                    <div class="relative bg-gradient-to-b from-[#161920]/92 to-[#080a0e]/90 
                                border border-[#c9a227]/[0.22] 
                                shadow-[0_12px_32px_rgba(0,0,0,.55),inset_0_0_60px_rgba(0,0,0,.45)]
                                p-4 md:p-6 lg:p-8 panel-gold-corners">
                        
                        <h2 class="section-title text-lg md:text-xl mb-4 md:mb-6 flex items-center gap-3 
                                   text-[#f2cf5b] font-bold drop-shadow-[0_0_12px_rgba(201,162,39,.35),0_2px_4px_rgba(0,0,0,.8)]">
                            <i class="fas fa-server text-[#f2cf5b]"></i>
                            <?php echo translate('section_realm_config', 'Realm Configuration'); ?>
                        </h2>

                        <form method="POST" enctype="multipart/form-data" class="space-y-4 md:space-y-6 max-w-3xl mx-auto">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="realm_id" value="<?php echo (int)$currentRealm['id']; ?>">
                            <!-- Current Logo Preview -->
                            <div>
                                <label class="form-label text-[#f2cf5b] font-bold text-sm 
                                               tracking-wider block mb-2 
                                               drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_current_logo', 'Current Logo'); ?>
                                </label>
                                <div class="flex justify-center p-4 bg-[#0a0e16]/50 border border-[rgba(201,162,39,.15)] rounded-sm">
                                    <img src="<?php echo $base_path . htmlspecialchars($currentRealm['logo'] ?? $defaultLogo); ?>" 
                                         alt="Realm Logo" 
                                         class="max-h-[80px] max-w-full object-contain">
                                </div>
                            </div>

                            <!-- Realm Name -->
                            <div>
                                <label for="realm_name" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                                 tracking-wider block mb-2 
                                                                 drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_realm_name', 'Realm Name'); ?>
                                </label>
                                <input type="text" id="realm_name" name="realm_name" maxlength="40" 
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] 
                                              bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm 
                                              focus:border-[#f2cf5b] focus:shadow-[0_0_10px_rgba(242,207,82,.2)] 
                                              focus:bg-[#0f141e]/90 outline-none transition-all duration-200 
                                              placeholder:text-[#96aac8]/40"
                                       placeholder="<?php echo translate('placeholder_realm_name', 'Enter realm name'); ?>" 
                                       value="<?php echo htmlspecialchars($_POST['realm_name'] ?? $currentRealm['name']); ?>" 
                                       required>
                            </div>

                            <!-- Realm Address / Host (player-facing) -->
                            <div>
                                <label for="realm_address" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                              tracking-wider block mb-2 
                                                              drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_realm_ip', 'Realm Address / Host'); ?>
                                </label>
                                <input type="text" id="realm_address" name="realm_address" 
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] 
                                              bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm 
                                              focus:border-[#f2cf5b] focus:shadow-[0_0_10px_rgba(242,207,82,.2)] 
                                              focus:bg-[#0f141e]/90 outline-none transition-all duration-200 
                                              placeholder:text-[#96aac8]/40"
                                       placeholder="<?php echo translate('placeholder_realm_address', 'play.example.com'); ?>" 
                                       value="<?php echo htmlspecialchars($_POST['realm_address'] ?? $currentRealm['address']); ?>" 
                                       required>
                            </div>

                            <!-- Status Check Address (internal only) -->
                            <div>
                                <label for="realm_check_address" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                              tracking-wider block mb-2 
                                                              drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_realm_check_address', 'Status Check Address'); ?>
                                </label>
                                <input type="text" id="realm_check_address" name="realm_check_address" 
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] 
                                              bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm 
                                              focus:border-[#f2cf5b] focus:shadow-[0_0_10px_rgba(242,207,82,.2)] 
                                              focus:bg-[#0f141e]/90 outline-none transition-all duration-200 
                                              placeholder:text-[#96aac8]/40"
                                       placeholder="<?php echo translate('placeholder_realm_check_address', '127.0.0.1'); ?>" 
                                       value="<?php echo htmlspecialchars($_POST['realm_check_address'] ?? $currentCheckAddress); ?>" 
                                       required>
                                <p class="text-[#6a7a8a] text-xs mt-1.5">
                                    <?php echo translate('note_realm_check_address', 'Used internally by the website to check if the server is online. Usually 127.0.0.1 when the website and game server run on the same machine.'); ?>
                                </p>
                            </div>

                            <!-- Realm Port -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                              <div>
                                <label for="realm_port" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                               tracking-wider block mb-2 
                                                               drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_realm_port', 'Realm Port'); ?>
                                </label>
                                <input type="number" id="realm_port" name="realm_port" 
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] 
                                              bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm 
                                              focus:border-[#f2cf5b] focus:shadow-[0_0_10px_rgba(242,207,82,.2)] 
                                              focus:bg-[#0f141e]/90 outline-none transition-all duration-200 
                                              placeholder:text-[#96aac8]/40"
                                       placeholder="8085" 
                                       min="1" max="65535" step="1"
                                       value="<?php echo htmlspecialchars($_POST['realm_port'] ?? $currentRealm['port']); ?>" 
                                       required>
                              </div>
                              <div>
                                <label for="realm_ra_port" class="form-label text-[#f2cf5b] font-bold text-sm tracking-wider block mb-2"><?php echo translate('label_realm_ra_port', 'Remote Administration Port'); ?></label>
                                <input type="number" id="realm_ra_port" name="realm_ra_port" min="1" max="65535" step="1"
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm focus:border-[#f2cf5b] outline-none"
                                       value="<?php echo (int)($_POST['realm_ra_port'] ?? ($currentRealm['ra_port'] ?? 3443)); ?>" required>
                              </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="realm_type" class="form-label text-[#f2cf5b] font-bold text-sm tracking-wider block mb-2"><?php echo translate('label_realm_type', 'Realm Type'); ?></label>
                                    <select id="realm_type" name="realm_type" class="realm-select w-full px-4 py-3 border border-[#c9a227]/30 rounded-sm focus:border-[#f2cf5b] outline-none">
                                        <?php $selectedType = $_POST['realm_type'] ?? ($currentRealm['type'] ?? 'normal'); foreach (['normal' => 'Normal', 'pvp' => 'PvP', 'rp' => 'Roleplay', 'hardcore' => 'Hardcore'] as $typeValue => $typeLabel): ?>
                                            <option value="<?php echo $typeValue; ?>" <?php echo $selectedType === $typeValue ? 'selected' : ''; ?>><?php echo $typeLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="realm_char_db" class="form-label text-[#f2cf5b] font-bold text-sm tracking-wider block mb-2"><?php echo translate('label_realm_database', 'Characters Database'); ?></label>
                                    <input type="text" id="realm_char_db" name="realm_char_db" maxlength="64" pattern="[A-Za-z0-9_]+"
                                           class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm focus:border-[#f2cf5b] outline-none"
                                           value="<?php echo htmlspecialchars($_POST['realm_char_db'] ?? ($currentRealm['char_db'] ?? 'characters'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>

                            <!-- Player Display -->
                            <div>
                                <label class="form-label text-[#f2cf5b] font-bold text-sm tracking-wider block mb-2">
                                    <?php echo translate('label_realm_visibility', 'Public realm visibility'); ?>
                                </label>
                                <label class="flex items-start gap-3 p-4 bg-[#0a0e16]/50 border border-[rgba(201,162,39,.15)] rounded-sm cursor-pointer hover:border-[#c9a227]/40 transition-all duration-200">
                                    <input type="checkbox"
                                           name="show_status"
                                           value="1"
                                           class="mt-1 accent-[#c9a227]"
                                           <?php echo (($_SERVER['REQUEST_METHOD'] === 'POST' ? isset($_POST['show_status']) : $currentShowStatus)) ? 'checked' : ''; ?>>
                                    <div>
                                        <div class="text-[#e5e7eb] font-semibold text-sm">
                                            <?php echo translate('realm_visibility_show', 'Show this realm publicly'); ?>
                                        </div>
                                        <div class="text-[#6a7a8a] text-xs mt-1">
                                            <?php echo translate('realm_visibility_show_desc', 'When disabled, this realm is hidden from the home-page status panel and the public realm selector. Staff can still select and manage it.'); ?>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Player Display -->
                            <div>
                                <label class="form-label text-[#f2cf5b] font-bold text-sm 
                                              tracking-wider block mb-2 
                                              drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_player_display', 'Player Display'); ?>
                                </label>

                                <div class="space-y-3">
                                    <!-- All Players -->
                                    <label class="flex items-start gap-3 p-4 
                                                  bg-[#0a0e16]/50 border border-[rgba(201,162,39,.15)] 
                                                  rounded-sm cursor-pointer hover:border-[#c9a227]/40 
                                                  transition-all duration-200">
                                        <input
                                            type="radio"
                                            name="player_display"
                                            value="all"
                                            class="mt-1 accent-[#c9a227]"
                                            <?php echo (($_POST['player_display'] ?? $currentPlayerDisplay) === 'all') ? 'checked' : ''; ?>
                                        >

                                        <div>
                                            <div class="text-[#e5e7eb] font-semibold text-sm">
                                                <?php echo translate('player_display_all', 'All Players'); ?>
                                            </div>

                                            <div class="text-[#6a7a8a] text-xs mt-1">
                                                <?php echo translate(
                                                    'player_display_all_desc',
                                                    'Show humans and bots together as one player count.'
                                                ); ?>
                                            </div>
                                        </div>
                                    </label>

                                    <!-- Humans / Bots -->
                                    <label class="flex items-start gap-3 p-4 
                                                  bg-[#0a0e16]/50 border border-[rgba(201,162,39,.15)] 
                                                  rounded-sm cursor-pointer hover:border-[#c9a227]/40 
                                                  transition-all duration-200">
                                        <input
                                            type="radio"
                                            name="player_display"
                                            value="separate"
                                            class="mt-1 accent-[#c9a227]"
                                            <?php echo (($_POST['player_display'] ?? $currentPlayerDisplay) === 'separate') ? 'checked' : ''; ?>
                                        >

                                        <div>
                                            <div class="text-[#e5e7eb] font-semibold text-sm">
                                                <?php echo translate('player_display_separate', 'Humans / Bots'); ?>
                                            </div>

                                            <div class="text-[#6a7a8a] text-xs mt-1">
                                                <?php echo translate(
                                                    'player_display_separate_desc',
                                                    'Show real players and playerbots as separate counts.'
                                                ); ?>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Realm Logo Upload -->
                            <div>
                                <label for="realm_logo" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                               tracking-wider block mb-2 
                                                               drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_realm_logo', 'Realm Logo'); ?>
                                </label>
                                <div class="border-2 border-dashed border-[#c9a227]/20 
                                            bg-[#0a0e16]/50 hover:border-[#c9a227]/40 
                                            hover:bg-[#0f141e]/70 cursor-pointer transition-all duration-300 
                                            p-8 text-center rounded-sm" 
                                     id="uploadArea">
                                    <input type="file" id="realm_logo" name="realm_logo" 
                                           class="absolute w-px h-px p-0 -m-px overflow-hidden clip-[rect(0,0,0,0)] border-0" 
                                           accept="image/png,image/jpeg,image/webp">
                                    <div id="uploadPlaceholder">
                                        <i class="fas fa-cloud-upload-alt text-3xl text-[#c9a227]/40 block mb-2"></i>
                                        <p class="text-sm text-gray-400"><?php echo translate('placeholder_realm_logo', 'Click or drag to upload a new logo'); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">PNG, JPG, or WebP (max 2MB)</p>
                                    </div>
                                    <div id="file-name" class="text-sm text-[#f2cf5b] hidden mt-2 font-semibold"></div>
                                </div>
                                <div class="text-[#6a7a8a] text-xs mt-1">
                                    <?php echo translate('note_realm_logo', 'Upload a new logo for your realm. Leave empty to keep the current logo.'); ?>
                                </div>
                            </div>

                            <!-- Info Note -->
                            <div class="text-[#6a7a8a] text-sm p-3 bg-[#0a0e16]/50 border border-[rgba(201,162,39,0.1)] rounded-sm">
                                <i class="fas fa-info-circle text-[#f2cf5b] mr-2"></i>
                                <?php echo translate('note_realm_config', 'This configures the settings for a single realm.'); ?>
                            </div>

                            <!-- Save Button -->
                            <div class="pt-4 border-t border-[rgba(201,162,39,.1)] flex justify-end">
                                <button type="submit" class="btn-clip inline-flex items-center gap-2 px-6 py-3 
                                                             font-extrabold text-xs uppercase tracking-wider
                                                             bg-gradient-to-b from-[#f6d478] via-[#c9a227] to-[#8a6a14] 
                                                             text-[#1a1200] shadow-[inset_0_0_0_1px_rgba(255,255,255,.28),inset_0_-8px_14px_rgba(0,0,0,.25)]
                                                             hover:scale-105 transition-transform duration-200">
                                    <i class="fas fa-save"></i>
                                    <?php echo translate('btn_save_realm', 'Save Realm Configuration'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('realm_logo');
            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
            const fileName = document.getElementById('file-name');

            if (uploadArea && fileInput) {
                uploadArea.addEventListener('click', () => fileInput.click());

                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('border-[#f2cf5b]', 'bg-[#c9a227]/10');
                });

                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('border-[#f2cf5b]', 'bg-[#c9a227]/10');
                });

                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('border-[#f2cf5b]', 'bg-[#c9a227]/10');
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        fileInput.dispatchEvent(new Event('change'));
                    }
                });

                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        const maxSize = 2 * 1024 * 1024;
                        const validExtensions = ['png', 'jpg', 'jpeg', 'webp'];
                        const ext = file.name.split('.').pop().toLowerCase();

                        if (!validExtensions.includes(ext)) {
                            alert('Invalid file type. Please upload PNG, JPG, or WebP.');
                            this.value = '';
                            return;
                        }
                        if (file.size > maxSize) {
                            alert('File size exceeds 2MB limit.');
                            this.value = '';
                            return;
                        }

                        fileName.textContent = 'Selected: ' + file.name;
                        fileName.classList.remove('hidden');
                        uploadPlaceholder.style.display = 'none';
                    } else {
                        fileName.classList.add('hidden');
                        uploadPlaceholder.style.display = 'block';
                    }
                });
            }
        });
    </script>
</body>
</html>
