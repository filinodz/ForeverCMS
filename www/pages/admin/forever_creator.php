<?php
define('ALLOWED_ACCESS',true);
require_once __DIR__.'/../../includes/paths.php';
require_once $project_root.'includes/session.php';
require_once $project_root.'languages/language.php';
require_once $project_root.'includes/creator_studio.php';
if(!isset($_SESSION['user_id'])||!in_array($_SESSION['role']??'',['admin','moderator'],true)){header('Location: /login');exit;}
$page_class='forever-creator';$page_title='ForeverCreator';$page_meta_robots='noindex';$page_head='<link rel="stylesheet" href="/assets/css/creator-studio.css">';
include $project_root.'includes/header.php';
?>
<div class="flex relative min-h-screen"><?php include $project_root.'includes/admin_sidebar.php'; ?><main class="main-content-area flex-1 studio-main"><div class="studio-shell">
<header class="studio-heading"><div><span class="studio-eyebrow">FOREVERCMS / WORLD CONTENT</span><h1>ForeverCreator</h1><p>Your world, your creations. Start from a real server template, inspect the result and save it to ForeverRepack.</p></div><a class="studio-button" href="/admin/settings/page_manager">Page studio</a></header>
<nav class="creator-tabs" aria-label="Creation tools"><?php foreach(creator_specs() as $kind=>$spec):?><button type="button" data-kind="<?php echo $kind; ?>"><i class="fas <?php echo $spec['icon']; ?>" aria-hidden="true"></i> <?php echo $spec['label']; ?></button><?php endforeach;?></nav>
<div id="creatorNotice" class="studio-notice" role="status" aria-live="polite"></div>
<div class="studio-toolbar"><label>Template ID<input id="templateId" type="number" min="1" value="19019"></label><button type="button" class="studio-button" id="loadTemplate">Load template</button><button type="button" class="studio-button" id="findTemplate">Search templates</button><label>Creation ID<input id="creationId" type="number" min="1" value="900001"></label><button type="button" class="studio-button" id="newCreation">New creation</button><span class="studio-status" id="creatorRuntime">Checking world library…</span></div>
<div class="creator-workspace"><section class="creator-panel"><div id="creatorFields"></div><div class="studio-toolbar"><button class="studio-button primary" id="saveCreation" type="button">Save to world</button><button class="studio-button" id="exportCreation" type="button">Export SQL</button><span class="studio-help" id="saveHint">A server restart is required to load new templates.</span></div></section>
<aside class="creator-preview creator-panel" style="padding:0"><h2>LIVE GAME INSPECTION</h2><iframe id="creatorModel" title="Live 3D appearance preview" src="about:blank"></iframe><div class="preview-status" id="modelStatus">Load a template to inspect its appearance.</div><div id="creatorTooltip" class="creator-inspection"></div><div class="studio-toolbar" style="margin:15px"><button class="studio-button" id="chooseAppearance" type="button">Appearance library</button><label>Display ID<input id="displayId" type="number" min="1"></label></div></aside></div>
<section class="creator-panel" style="margin-top:24px"><header class="studio-heading"><div><span class="studio-eyebrow">WORLD / CREATION HISTORY</span><h2 style="margin-top:8px">Your creations</h2></div><button class="studio-button" type="button" id="refreshHistory">Refresh</button></header><div style="overflow:auto"><table class="creator-history"><thead><tr><th>Tool</th><th>ID</th><th>Name</th><th>State</th><th>Updated</th><th>Actions</th></tr></thead><tbody id="creationHistory"></tbody></table></div></section>
<div class="studio-credit">Made with love by FilinoDZ · ForeverCreator · Templates and creations stored in world</div>
</div></main></div>
<div class="creator-modal" id="creatorLibrary" role="dialog" aria-modal="true" aria-labelledby="libraryTitle" hidden><div class="creator-modal-inner"><header><h2 id="libraryTitle">Template library</h2><button class="studio-button" id="closeLibrary" type="button">Close</button></header><form id="librarySearchForm" class="studio-toolbar"><input class="studio-search grow" id="libraryQuery" placeholder="Search by name, item ID or display ID" autocomplete="off"><button class="studio-button primary" type="submit">Search</button></form><p class="studio-help" id="libraryStatus"></p><div class="creator-library" id="libraryResults"></div></div></div>
<script>window.ForeverCreatorStudio=<?php echo json_encode(['csrf'=>$_SESSION['csrf_token']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;</script><script src="/assets/js/admin/creator-studio.js"></script>
<?php include $project_root.'includes/footer.php'; ?>
