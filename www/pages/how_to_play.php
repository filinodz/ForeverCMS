<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/forever_pages.php';
$page_class = 'how_to_play';
$page_title = $site_title_name . ' — ' . (($langCode ?? 'en') === 'fr' ? 'Nous rejoindre' : 'How to join');
$page_head = '<link rel="stylesheet" href="/assets/css/forever-join.css">';
require_once $project_root . 'includes/header.php';
$guideLocale = ($langCode ?? 'en') === 'fr' ? 'fr' : 'en';
?>
<main class="fj-page">
<?php echo forever_pages_tokens(file_get_contents($project_root . 'includes/join-guide/' . $guideLocale . '.html')); ?>
</main>
<?php require_once $project_root . 'includes/footer.php'; ?>
