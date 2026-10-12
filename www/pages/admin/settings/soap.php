<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header("Location: {$base_path}login");
    exit;
}

$page_class = 'soap';
$page_title = translate('title_soap_settings', 'SOAP Settings');
$page_meta_description = translate('page_description_soap', 'SOAP Settings for Sahtout WoW Server');
$page_meta_robots = 'noindex';
$page_body_class = 'min-h-screen text-[#d8d8d8] bg-[#05070b] bg-fixed';

// Forever: game commands go through RA (the worldserver's remote console), not SOAP. This page keeps its
// address and field names from SahtoutCMS; it reads and writes includes/ra.conf.php.
//   soap_url  -> RA host        soap_user -> console account (game account name like 3#1)        soap_pass -> its password
// With no account here, the launcher's console account from localservers.json is used.
require_once $project_root . 'includes/ra_client.php';

// CSRF token is generated centrally in includes/session.php
$errors = [];
$success = false;
$soapConfigFile = $project_root . 'includes/ra.conf.php';
$ra_verify_message = '';

$ra_saved = forever_ra_config();
$ra_user = $ra_saved['user'];
$soapUrl = $ra_saved['host'];
$soapUser = $ra_saved['user'];
$soapPass = '';
// Keep the stored password in memory only; it must never be echoed into HTML.
$existingSoapPass = $ra_saved['pass'];
$raLocalServers = $ra_saved['localservers'];
$soap_status = forever_ra_configured() ? 'configured' : 'not_configured';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    $validCsrf = !empty($csrfToken) && hash_equals($csrfToken, (string)($_SESSION['csrf_token'] ?? ''));

    if (!$validCsrf) {
        // Reject the request entirely: do not process any POST data.
        $errors[] = translate('error_invalid_csrf', 'Invalid CSRF token.');
    } elseif (($_SESSION['role'] ?? '') !== 'admin') {
        $errors[] = translate('error_admin_only', 'Only administrators can change this.');
    } else {
        $soapUrl = trim($_POST['soap_url'] ?? '127.0.0.1');
        $soapUser = trim($_POST['soap_user'] ?? '');
        $soapPass = (string)($_POST['soap_pass'] ?? '');

        if ($soapUrl === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $soapUrl)) {
            $errors[] = translate('error_soap_url_required', 'RA host is required (an IP address or host name).');
        }

        if ($soapUser === '') {
            // no account of its own: the launcher's console account
            $soapPass = '';
        } else {
            if ($soapPass === '') {
                // Blank password on save means "keep the previously stored password".
                if ($existingSoapPass !== '' && $soapUser === (string)($ra_user ?? '')) {
                    $soapPass = $existingSoapPass;
                } else {
                    $errors[] = translate('error_soap_pass_required', 'The console account password is required.');
                }
            }

            // The console account must exist and have the RA level on all realms
            if (empty($errors)) {
                $stmt = $auth_db->prepare("SELECT a.id, MAX(aa.SecurityLevel) AS lvl FROM account a LEFT JOIN account_access aa ON aa.AccountID = a.id AND aa.RealmID = -1 WHERE a.username = ? GROUP BY a.id");
                $stmt->bind_param('s', $soapUser);
                $stmt->execute();
                $consoleAccount = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$consoleAccount) {
                    $errors[] = sprintf(translate('error_account_not_exist', 'Account %s does not exist in Auth DB.'), $soapUser);
                } elseif ((int)$consoleAccount['lvl'] < 3) {
                    $errors[] = sprintf(translate('error_account_not_gm_level_3', 'Account %s exists but is not GM level 3.'), $soapUser);
                }
            }
        }

        // Save settings if no errors
        if (empty($errors)) {
            $configContent = "<?php\n"
                . "if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }\n\n"
                . "// RA (worldserver remote console). The port of each realm is in realm_config.php.\n"
                . "// Leave the user empty to use the launcher's console account from localservers.json.\n"
                . "\$ra_host = " . var_export($soapUrl, true) . ";\n"
                . "\$ra_user = " . var_export($soapUser, true) . ";\n"
                . "\$ra_pass = " . var_export($soapPass, true) . ";\n"
                . "\$ra_localservers = " . var_export($raLocalServers, true) . ";\n"
                . "?>\n";

            if (file_put_contents($soapConfigFile, $configContent, LOCK_EX) === false) {
                $errors[] = sprintf(translate('error_config_file_write_failed', 'Failed to write config file: %s'), $soapConfigFile);
            } else {
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($soapConfigFile, true);
                }
                $success = true;
                $existingSoapPass = $soapPass;
            }
        }
    }
}

// Try the settings on the realm chosen in the header (a fresh PHP process reads the saved file on the next request)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$success) {
    if ($soap_status === 'configured') {
        $ra_check = forever_realm_ra((int)$current_realm['id'], 'server info', 5);
        $ra_verify_message = $ra_check['success']
            ? sprintf(translate('ra_verify_ok', 'RA answers on realm %s.'), $current_realm['name'])
            : sprintf(translate('ra_verify_failed', 'Realm %s: %s'), $current_realm['name'], $ra_check['error']);
    }
} else {
    header("Location: {$base_path}admin/settings/soap?saved=1");
    exit;
}
if (isset($_GET['saved'])) {
    $success = true;
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
        
        /* Status badge */
        .status-badge.configured {
            background: rgba(46, 204, 113, 0.15);
            border-color: rgba(46, 204, 113, 0.4);
            color: #2ecc71;
        }
        .status-badge.not-configured {
            background: rgba(231, 76, 60, 0.15);
            border-color: rgba(231, 76, 60, 0.4);
            color: #e74c3c;
        }
        
        /* Info box toggle - Tailwind can't handle max-height transitions easily */
        .info-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        .info-content.open {
            max-height: 300px;
            padding: 0 1.25rem 1.25rem;
        }
        .info-title .fa-chevron-down {
            transition: transform 0.3s ease;
        }
        .info-title.open .fa-chevron-down {
            transform: rotate(180deg);
        }
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
                        <?php echo translate('title_soap_settings', 'SOAP Settings'); ?>
                    </h1>

                    <!-- Settings Navbar -->
                    <?php include $project_root . 'pages/admin/settings/settings_navbar.php'; ?>

                    <!-- Status Badge -->
                    <div class="flex items-center gap-3">
                        <span class="text-gray-400 text-sm font-semibold"><?php echo translate('status', 'Status:'); ?></span>
                        <span class="status-badge <?php echo $soap_status === 'configured' ? 'configured' : 'not-configured'; ?> 
                                     inline-flex items-center gap-2 px-4 py-1.5 font-bold text-sm border rounded-sm">
                            <i class="fas <?php echo $soap_status === 'configured' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo $soap_status === 'configured' 
                                ? translate('status_soap_configured', 'SOAP Configured') 
                                : translate('status_soap_not_configured', 'SOAP Not Configured'); ?>
                        </span>
                    </div>

                    <!-- Success / Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="bg-[#e74c3c]/15 border border-[#e74c3c]/40 text-[#e74c3c] 
                                    p-4 rounded-sm flex items-start gap-3">
                            <i class="fas fa-exclamation-circle text-xl mt-0.5"></i>
                            <div>
                                <strong><?php echo translate('error_box_title', 'Please fix the following errors:'); ?></strong>
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
                            <span><?php echo translate('success_soap_settings_saved', 'SOAP settings saved successfully!'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($ra_verify_message !== ''): ?>
                        <div class="bg-black/30 border border-[#c9a227]/30 text-gray-200 p-4 rounded-sm flex items-center gap-3">
                            <i class="fas fa-plug text-xl text-[#f2cf5b]"></i>
                            <span><?php echo htmlspecialchars($ra_verify_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- SOAP Settings Form -->
                    <div class="relative bg-gradient-to-b from-[#161920]/92 to-[#080a0e]/90 
                                border border-[#c9a227]/[0.22] 
                                shadow-[0_12px_32px_rgba(0,0,0,.55),inset_0_0_60px_rgba(0,0,0,.45)]
                                p-4 md:p-6 lg:p-8 panel-gold-corners">
                        
                        <h2 class="section-title text-lg md:text-xl mb-4 md:mb-6 flex items-center gap-3 
                                   text-[#f2cf5b] font-bold drop-shadow-[0_0_12px_rgba(201,162,39,.35),0_2px_4px_rgba(0,0,0,.8)]">
                            <i class="fas fa-code text-[#f2cf5b]"></i>
                            <?php echo translate('header_soap_settings', 'SOAP Settings'); ?>
                        </h2>

                        <form method="POST" class="space-y-4 md:space-y-6 max-w-3xl mx-auto">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div>
                                <label for="soap_url" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                             tracking-wider block mb-2 
                                                             drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_soap_url', 'SOAP URL'); ?>
                                </label>
                                <input type="text" id="soap_url" name="soap_url" 
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] 
                                              bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm 
                                              focus:border-[#f2cf5b] focus:shadow-[0_0_10px_rgba(242,207,82,.2)] 
                                              focus:bg-[#0f141e]/90 outline-none transition-all duration-200 
                                              placeholder:text-[#96aac8]/40"
                                       placeholder="<?php echo translate('placeholder_soap_url', 'e.g., http://127.0.0.1:7878'); ?>" 
                                       value="<?php echo htmlspecialchars($soapUrl); ?>" 
                                       required>
                                <div class="text-[#6a7a8a] text-xs mt-1"><?php echo translate('help_soap_url', 'The URL where your WoW server\'s SOAP service is running.'); ?></div>
                            </div>

                            <div>
                                <label for="soap_user" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                             tracking-wider block mb-2 
                                                             drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_soap_user', 'GM Account Username'); ?>
                                </label>
                                <input type="text" id="soap_user" name="soap_user" maxlength="64"
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] 
                                              bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm 
                                              focus:border-[#f2cf5b] focus:shadow-[0_0_10px_rgba(242,207,82,.2)] 
                                              focus:bg-[#0f141e]/90 outline-none transition-all duration-200 
                                              placeholder:text-[#96aac8]/40"
                                       placeholder="<?php echo translate('placeholder_soap_user', 'Must be GM level 3'); ?>" 
                                       value="<?php echo htmlspecialchars($soapUser); ?>">
                                <div class="text-[#6a7a8a] text-xs mt-1"><?php echo translate('help_soap_user', 'The account must have GM level 3 in the database.'); ?></div>
                            </div>

                            <div>
                                <label for="soap_pass" class="form-label text-[#f2cf5b] font-bold text-sm 
                                                             tracking-wider block mb-2 
                                                             drop-shadow-[0_0_12px_rgba(201,162,39,.15),0_2px_4px_rgba(0,0,0,.8)]">
                                    <?php echo translate('label_soap_pass', 'SOAP Password'); ?>
                                </label>
                                <input type="password" id="soap_pass" name="soap_pass" maxlength="16" 
                                       class="w-full px-4 py-3 text-[0.95rem] text-[#e5e7eb] 
                                              bg-[#0a0e16]/80 border border-[#c9a227]/30 rounded-sm 
                                              focus:border-[#f2cf5b] focus:shadow-[0_0_10px_rgba(242,207,82,.2)] 
                                              focus:bg-[#0f141e]/90 outline-none transition-all duration-200 
                                              placeholder:text-[#96aac8]/40"
                                       placeholder="<?php echo translate('placeholder_soap_pass', 'SOAP password = Account password'); ?>"
                                       >
                                <div class="text-[#6a7a8a] text-xs mt-1"><?php echo translate('help_soap_pass', 'This is the password for the GM account above.'); ?></div>
                            </div>

                            <!-- Save Button -->
                            <div class="pt-4 border-t border-[rgba(201,162,39,.1)] flex justify-end">
                                <button type="submit" class="btn-clip inline-flex items-center gap-2 px-6 py-3 
                                                             font-extrabold text-xs uppercase tracking-wider
                                                             bg-gradient-to-b from-[#f6d478] via-[#c9a227] to-[#8a6a14] 
                                                             text-[#1a1200] shadow-[inset_0_0_0_1px_rgba(255,255,255,.28),inset_0_-8px_14px_rgba(0,0,0,.25)]
                                                             hover:scale-105 transition-transform duration-200">
                                    <i class="fas fa-save"></i>
                                    <?php echo translate('button_save_verify_soap', 'Save & Verify SOAP'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Information Box -->
                    <div class="bg-[rgba(201,162,39,0.05)] border border-[rgba(201,162,39,0.15)] rounded-sm overflow-hidden">
                        <div class="info-title px-5 py-3 text-[#f2cf5b] font-bold cursor-pointer 
                                    flex items-center justify-between font-['Cinzel'] text-sm 
                                    hover:bg-[rgba(201,162,39,0.05)] transition-all duration-300"
                             onclick="toggleInfo(this)">
                            <span><i class="fas fa-info-circle text-[#f2cf5b] mr-2"></i><?php echo translate('info_box_title', 'Important Steps'); ?></span>
                            <i class="fas fa-chevron-down text-[#f2cf5b]"></i>
                        </div>
                        <div class="info-content px-5">
                            <ul class="list-none p-0 m-0">
                                <li class="py-1.5 text-[#b8c8ff] text-sm border-b border-[rgba(201,162,39,0.05)] last:border-b-0">
                                    <?php echo translate('info_step_1', 'Make sure the GM account exists in your Auth DB and has GM level 3 in <code>account_access</code> with <code>RealmID = -1</code>.'); ?>
                                </li>
                                <li class="py-1.5 text-[#b8c8ff] text-sm border-b border-[rgba(201,162,39,0.05)] last:border-b-0">
                                    <?php echo translate('info_step_2', 'Open your <code>worldserver.conf</code> file and set: <strong>SOAP.Enabled = 1</strong>'); ?>
                                </li>
                                <li class="py-1.5 text-[#b8c8ff] text-sm border-b border-[rgba(201,162,39,0.05)] last:border-b-0">
                                    <?php echo translate('info_step_3', 'Ensure the SOAP port in <code>soap_url</code> is correct and accessible.'); ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleInfo(element) {
            const content = element.parentElement.querySelector('.info-content');
            const icon = element.querySelector('.fa-chevron-down');
            
            if (content) {
                content.classList.toggle('open');
                element.classList.toggle('open');
            }
        }

        // Auto-expand on page load if there are errors
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($errors)): ?>
                const infoTitle = document.querySelector('.info-title');
                if (infoTitle) {
                    toggleInfo(infoTitle);
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>