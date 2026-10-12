<?php 
define('ALLOWED_ACCESS', true);

// Include paths.php to access $project_root and $base_path
require_once __DIR__ . '/../includes/paths.php';

// Use $project_root for filesystem includes
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
$page_class = "how_to_play";
$page_title = $site_title_name . ' - ' . translate('how_to_play_title', 'How to Play');

// Forever: players do not edit a realmlist. Forever Launcher starts the client and points it at the
// server's portal (the "portal" of the launcher's servers.json); both come from config.settings.php.
$realmlistIP = $forever_portal ?? 'trinity.actual.battle.net';
$forever_launcher_url = $forever_launcher_url ?? 'https://github.com/advocaite/foreverlauncher-releases/releases/latest';

ob_start();
?>

<style>
    /* ============ CORE THEME VARIABLES ============ */
    :root {
        --gold-primary: #e8c46a;
        --gold-dark: #b07a26;
        --gold-deep: #6b4415;
        --iron-bg: rgba(11,12,22,.85);
    }

    html { scroll-behavior: smooth; }

    /* ============ BACKGROUND IMAGE ONLY ============ */
    body.how_to_play {
        background: url('<?php echo $base_path; ?>img/backgrounds/bg-howto.jpg') no-repeat center center fixed !important;
        background-size: cover !important;
        position: relative;
        min-height: 100vh;
    }
    
    /* Remove the dark overlay completely */
    body.how_to_play::before {
        display: none !important;
        content: none !important;
        background: none !important;
    }

    /* Remove any other overlays */
    body.how_to_play::after {
        display: none !important;
        content: none !important;
        background: none !important;
    }

    /* Make sure all content sits above the background */
    body.how_to_play .relative.z-10 {
        position: relative;
        z-index: 10;
    }

    /* ============ TYPOGRAPHY & HERO ============ */
    .wow-title {
        font-family: 'Cinzel', serif;
        font-weight: 900;
        background: linear-gradient(180deg, #fff7d6 0%, #f2cf5b 35%, #c9a227 62%, #8a6a14 100%);
        -webkit-background-clip: text; background-clip: text;
        color: transparent;
        filter: drop-shadow(0 4px 12px rgba(0,0,0,.9));
        letter-spacing: .02em;
    }

    .tag-pill {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .4rem 1.2rem;
        background: rgba(0,0,0,.6);
        border: 1px solid rgba(201,162,39,.4);
        border-radius: 14px;
        color: var(--gold-primary);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        text-shadow: 0 0 8px rgba(242,207,82,.3);
    }

    /* ============ STEP CARDS (PANELS) ============ */
    .wow-step-card {
        position: relative;
        background: rgba(11,12,22,.82);
        border: 1px solid rgba(232,196,106,.2);
        border-radius: 8px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.02), 0 16px 42px rgba(0,0,0,.34);
        transition: background .15s ease, border-color .15s ease;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .wow-step-card::before {
        display: none;
    }

    .wow-step-card:hover {
        background: #151625;
        border-color: rgba(232,196,106,.45);
    }

    /* Step Badge */
    .step-badge {
        position: absolute;
        top: 1.25rem; left: 1.25rem;
        z-index: 10;
        width: 38px; height: 38px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(180deg, #fff6da, #ffd9a4 50%, #ffa872);
        color: #1a1200;
        font-weight: 900; font-size: 1.1rem; font-family: 'Cinzel', serif;
        border-radius: 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,.6);
        text-shadow: 0 1px 0 rgba(255,255,255,.35);
    }

    /* Launcher-style step symbol: deterministic CSS geometry, no artwork. */
    .step-image-container {
        padding: 2.5rem 1.5rem 1rem 1.5rem;
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        flex-shrink: 0;
        height: 190px;
    }

    .step-symbol {
        width: 104px;
        height: 104px;
        display: grid;
        place-items: center;
        position: relative;
        transform: rotate(45deg);
        color: var(--gold-primary);
        background: rgba(232,196,106,.045);
        border: 2px solid rgba(232,196,106,.62);
        box-shadow: 0 0 28px rgba(232,196,106,.09), inset 0 0 26px rgba(0,0,0,.46);
    }

    .step-symbol::before {
        content: '';
        position: absolute;
        inset: 17px;
        border: 2px solid rgba(232,196,106,.5);
    }

    .step-symbol i {
        transform: rotate(-45deg);
        font-size: 1.65rem;
        text-shadow: 0 0 14px rgba(232,196,106,.25);
    }

    /* Text Content */
    .step-content {
        padding: 0.5rem 1.75rem 1.75rem 1.75rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        position: relative;
        z-index: 1;
    }

    .step-title {
        font-family: 'Cinzel', serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--gold-primary);
        margin-bottom: 0.75rem;
        letter-spacing: 0.02em;
        text-shadow: 0 2px 6px rgba(0,0,0,.9);
    }

    .step-content p {
        color: #d1d5db;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 0.5rem;
    }

    /* Code Box */
    .code-box {
        background: rgba(0,0,0,.7);
        border: 1px solid rgba(201,162,39,.35);
        font-family: 'Fira Code', 'Courier New', monospace;
        padding: 0.85rem 1.1rem;
        border-radius: 5px;
        margin-top: 0.75rem;
        position: relative;
        z-index: 1;
    }

    .code-box code {
        color: #55d078;
        font-size: 0.875rem;
    }

    .code-box code span {
        color: var(--gold-primary);
        font-weight: 700;
    }

    .copy-btn {
        padding: 0.4rem 0.85rem;
        background: rgba(255,255,255,.045);
        color: #ede6d6;
        border: 1px solid rgba(232,196,106,.2);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border-radius: 4px;
        transition: all 0.2s ease;
    }
    .copy-btn:hover { color: #fff3cc; border-color: rgba(232,196,106,.45); }

    /* ============ BUTTONS ============ */
    .btn-game {
        display: inline-flex; align-items: center; justify-content: center; gap: .55rem;
        padding: .85rem 1.5rem;
        font-weight: 800; font-size: .85rem; letter-spacing: .04em; text-transform: uppercase;
        border-radius: 5px;
        transition: background .15s ease, border-color .15s ease;
        text-decoration: none;
        width: 100%;
    }
    .btn-game:hover { transform: none; }

    .btn-gold {
        background: linear-gradient(180deg, #fff6da, #ffd9a4 50%, #ffa872);
        color: #2a1a06;
        text-shadow: 0 1px 0 rgba(255,255,255,.35);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.28), inset 0 -8px 14px rgba(0,0,0,.25);
    }
    .btn-gold:hover { filter: drop-shadow(0 0 12px rgba(242,207,82,.5)); }

    .btn-iron {
        background: rgba(0,0,0,.25);
        color: #ede6d6;
        border: 1px solid rgba(232,196,106,.2);
        box-shadow: none;
    }
    .btn-iron:hover { color: #fff3cc; border-color: rgba(232,196,106,.45); filter: none; }

    /* ============ CTA PANEL ============ */
    .cta-panel {
        position: relative;
        background: rgba(11,12,22,.82);
        border: 1px solid rgba(232,196,106,.25);
        border-radius: 8px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.02), 0 16px 42px rgba(0,0,0,.34);
        padding: 3.5rem 2rem;
    }
    .cta-panel::before {
        display: none;
    }

    /* ============ ANIMATIONS ============ */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-up { animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
    .fade-up-delay-1 { animation-delay: 0.1s; }
    .fade-up-delay-2 { animation-delay: 0.2s; }
    .fade-up-delay-3 { animation-delay: 0.3s; }
    .fade-up-delay-4 { animation-delay: 0.4s; }

    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .step-image-container { height: 170px; padding: 2rem 1rem 0.5rem 1rem; }
        .step-symbol { width: 88px; height: 88px; }
        .step-content { padding: 0.5rem 1.25rem 1.25rem 1.25rem; }
        .step-title { font-size: 1.15rem; }
        .step-badge { width: 32px; height: 32px; font-size: 0.95rem; top: 1rem; left: 1rem; }
    }
</style>
<?php
$page_head = ob_get_clean();
require_once $project_root . 'includes/header.php';
?>

<!-- Main Page Wrapper -->
<div class="relative z-10 min-h-screen flex flex-col">
    <main class="flex-1 flex flex-col items-center w-full px-4 py-12 md:py-20">
        <div class="container mx-auto max-w-6xl">
            
            <!-- Hero Header -->
            <div class="text-center mb-16 fade-up relative z-10">
                <div class="tag-pill mb-6">
                    <span class="w-2 h-2 bg-[#f2cf5b] animate-pulse" style="clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);"></span>
                    <span><?php echo translate('quickstart_guide', 'Quickstart Guide'); ?></span>
                </div>
                
                <h1 class="text-4xl md:text-6xl lg:text-7xl mb-6 wow-title">
                    <?php echo translate('how_to_play_title', 'How to Play'); ?>
                </h1>
                
                <p class="text-base md:text-lg text-gray-300 max-w-2xl mx-auto leading-relaxed drop-shadow-lg">
                    <?php echo translate('how_to_play_subtitle', 'Set up your access to Forever in four steps.'); ?>
                </p>
            </div>
            
            <!-- 2x2 Steps Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-10 relative z-10">
                
                <!-- STEP 1 -->
                <div class="wow-step-card fade-up fade-up-delay-1">
                    <div class="step-badge">1</div>
                    <div class="step-image-container" aria-hidden="true">
                        <div class="step-symbol"><i class="fa-solid fa-user-plus"></i></div>
                    </div>
                    
                    <div class="step-content">
                        <div class="flex-1">
                            <h2 class="step-title"><?php echo translate('step_1_title', 'Create an Account'); ?></h2>
                            <p><?php echo translate('step_1_desc', 'Register a free account using our website:'); ?></p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <a class="btn-game btn-gold" href="<?php echo $base_path; ?>register">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                                <?php echo translate('create_account', 'Create Account'); ?>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 2 -->
                <div class="wow-step-card fade-up fade-up-delay-2">
                    <div class="step-badge">2</div>
                    <div class="step-image-container" aria-hidden="true">
                        <div class="step-symbol"><i class="fa-solid fa-download"></i></div>
                    </div>
                    
                    <div class="step-content">
                        <div class="flex-1">
                            <h2 class="step-title"><?php echo translate('step_2_title', 'Download the Game'); ?></h2>
                            <p><?php echo translate('step_2_desc', 'Install the compatible client and then Forever Launcher, which prepares and starts the game for this server.'); ?></p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <div class="grid grid-cols-1 gap-3">
                                <a class="btn-game btn-iron !text-xs !py-2.5" href="<?php echo htmlspecialchars($forever_launcher_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    <?php echo translate('forever_launcher_download', 'Download Forever Launcher'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 3 -->
                <div class="wow-step-card fade-up fade-up-delay-3">
                    <div class="step-badge">3</div>
                    <div class="step-image-container" aria-hidden="true">
                        <div class="step-symbol"><i class="fa-solid fa-server"></i></div>
                    </div>
                    
                    <div class="step-content">
                        <div class="flex-1">
                            <h2 class="step-title"><?php echo translate('step_3_title', 'Set the Realmlist'); ?></h2>
                            <p><?php echo translate('step_3_desc_1', 'Open your World of Warcraft folder, go to Data/enUS or Data/enGB, and find realmlist.wtf.'); ?></p>
                            <p><?php echo translate('step_3_desc_2', 'Open it with Notepad and replace everything inside with:'); ?></p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <div class="code-box flex items-center justify-between gap-3">
                                <code class="overflow-x-auto select-all whitespace-nowrap">
                                    <span id="realmlist-text"><?php echo htmlspecialchars($realmlistIP); ?></span>
                                </code>
                                <button onclick="copyRealmlist()" 
                                        id="copy-btn"
                                        class="copy-btn flex items-center gap-1.5 flex-shrink-0">
                                    <svg id="copy-icon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span id="copy-status"><?php echo translate('copy', 'Copy'); ?></span>
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 mt-3 italic">
                                <?php echo translate('step_3_desc_3', 'Save the file and close it.'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 4 -->
                <div class="wow-step-card fade-up fade-up-delay-4">
                    <div class="step-badge">4</div>
                    <div class="step-image-container" aria-hidden="true">
                        <div class="step-symbol"><i class="fa-solid fa-play"></i></div>
                    </div>
                    
                    <div class="step-content">
                        <div class="flex-1">
                            <h2 class="step-title"><?php echo translate('step_4_title', 'Start Playing!'); ?></h2>
                            <p><?php echo translate('step_4_desc_1', 'Open Wow.exe (not Launcher.exe) and log in using your account credentials.'); ?></p>
                            <p><?php echo translate('step_4_desc_2', 'Enjoy your adventure on our server!'); ?></p>
                            <p class="mt-4 text-[#4ade80] text-sm font-semibold flex items-center gap-2" style="text-shadow: 0 0 8px rgba(74,222,128,.4);">
                                <span class="w-2 h-2 bg-[#4ade80] animate-pulse" style="clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);"></span>
                                <?php echo translate('ready_to_play', 'Ready to play! Enjoy your adventure.'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Bottom CTA Section -->
            <div class="mt-20 text-center fade-up relative z-10">
                <div class="cta-panel max-w-3xl mx-auto">
                    <h3 class="text-2xl md:text-3xl font-extrabold text-white mb-3" style="font-family:'Cinzel',serif; text-shadow: 0 2px 8px rgba(0,0,0,.8);">
                        <?php echo translate('cta_title', 'Ready to Begin Your Journey?'); ?>
                    </h3>
                    <p class="text-gray-300 text-sm md:text-base mb-8 max-w-xl mx-auto">
                        <?php echo translate('cta_desc', 'Create an account now and start your adventure in the world of Azeroth!'); ?>
                    </p>
                    <a class="btn-game btn-gold !w-auto !px-10 !py-4 text-base mx-auto" href="<?php echo $base_path; ?>register">
                        <span><?php echo translate('cta_button', 'Get Started Now'); ?></span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                </div>
            </div>
            
        </div>
    </main>
</div>

<script>
function copyRealmlist() {
    const ip = document.getElementById('realmlist-text').innerText;
    const textToCopy = 'set realmlist ' + ip;
    
    navigator.clipboard.writeText(textToCopy).then(() => {
        const statusSpan = document.getElementById('copy-status');
        const btn = document.getElementById('copy-btn');
        
        statusSpan.innerText = '<?php echo translate('copied', 'Copied!'); ?>';
        
        // Success state styling
        btn.style.background = 'linear-gradient(180deg, #4ade80 0%, #16a34a 100%)';
        btn.style.color = '#052e16';
        btn.style.borderColor = 'rgba(74,222,128,.5)';
        
        setTimeout(() => {
            statusSpan.innerText = '<?php echo translate('copy', 'Copy'); ?>';
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    });
}
</script>

<?php include($project_root . 'includes/footer.php'); ?>
