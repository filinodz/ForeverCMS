<?php
define('ALLOWED_ACCESS',true);
require_once __DIR__.'/../../../includes/paths.php';
require_once $project_root.'includes/session.php';
require_once $project_root.'languages/language.php';
require_once $project_root.'includes/config.settings.php';
require_once $project_root.'includes/forever_pages.php';
if(!isset($_SESSION['user_id'])||!in_array($_SESSION['role']??'',['admin','moderator'],true)){http_response_code(403);exit('Access denied');}
forever_pages_schema($site_db);
$catalog=forever_pages_catalog();$slug=(string)($_GET['slug']??'how_to_play');$locale=(string)($_GET['locale']??($langCode??'en'));
if(!isset($catalog[$slug])||!in_array($locale,['en','fr'],true)){http_response_code(400);exit('Invalid page or language');}
if(isset($_GET['api'])){
    header('Content-Type: application/json; charset=utf-8');
    try{
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $p=json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR);
            if(!hash_equals($_SESSION['csrf_token']??'',(string)($p['csrf_token']??''))) throw new RuntimeException('Invalid security token');
            $current=forever_pages_get($site_db,$slug,$locale);
            if((int)($p['revision']??0)!==(int)($current['revision']??0)) throw new RuntimeException('This page was changed in another window. Reload it before saving.');
            $action=$p['action']??'draft';
            if(!in_array($action,['draft','publish','disable','restore'],true))throw new RuntimeException('Invalid action');
            $html=forever_pages_clean_html((string)($p['html']??''));$css=forever_pages_clean_css((string)($p['css']??''));$project=json_encode($p['project']??[],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$title=substr(trim((string)($p['title']??$catalog[$slug]['label'])),0,255);
            if(strlen($html)+strlen($css)+strlen($project)>3000000)throw new RuntimeException('Page size exceeds 3 MB');
            if($action==='restore'){
                $rid=(int)($p['restore_id']??0);$st=$site_db->prepare('SELECT * FROM website_page_revisions WHERE id=? AND slug=? AND locale=?');$st->bind_param('iss',$rid,$slug,$locale);$st->execute();$r=$st->get_result()->fetch_assoc();$st->close();if(!$r)throw new RuntimeException('Revision not found');
                $html=$r['html'];$css=$r['css'];$project=$r['project_json'];$title=$r['title'];
            }
            $publishedHtml=$action==='publish'?$html:($current['published_html']??null);$publishedCss=$action==='publish'?$css:($current['published_css']??null);$enabled=$action==='publish'?1:($action==='disable'?0:(int)($current['enabled']??0));$revision=(int)($current['revision']??0)+1;$author=(int)$_SESSION['user_id'];
            $site_db->begin_transaction();
            try{
                $st=$site_db->prepare('INSERT INTO website_pages (slug,locale,title,html,css,project_json,published_html,published_css,enabled,revision,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),html=VALUES(html),css=VALUES(css),project_json=VALUES(project_json),published_html=VALUES(published_html),published_css=VALUES(published_css),enabled=VALUES(enabled),revision=VALUES(revision),updated_by=VALUES(updated_by)');
                $st->bind_param('ssssssssiii',$slug,$locale,$title,$html,$css,$project,$publishedHtml,$publishedCss,$enabled,$revision,$author);if(!$st->execute())throw new RuntimeException($st->error);$st->close();
                $st=$site_db->prepare('INSERT INTO website_page_revisions (slug,locale,title,html,css,project_json,saved_by) VALUES (?,?,?,?,?,?,?)');$st->bind_param('ssssssi',$slug,$locale,$title,$html,$css,$project,$author);if(!$st->execute())throw new RuntimeException($st->error);$st->close();$site_db->commit();
            }catch(Throwable $e){$site_db->rollback();throw $e;}
            echo json_encode(['ok'=>true,'revision'=>$revision,'enabled'=>$enabled,'html'=>$html,'css'=>$css,'project'=>json_decode($project,true)]);exit;
        }
        echo json_encode(['ok'=>true,'page'=>forever_pages_get($site_db,$slug,$locale)]);exit;
    }catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);exit;}
}
$page=forever_pages_get($site_db,$slug,$locale);$revisions=[];$st=$site_db->prepare('SELECT id,saved_at FROM website_page_revisions WHERE slug=? AND locale=? ORDER BY id DESC LIMIT 30');$st->bind_param('ss',$slug,$locale);$st->execute();$revisions=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
$page_class='page_manager';$page_title='Page studio';$page_meta_robots='noindex';$page_head='<link rel="stylesheet" href="/assets/vendor/grapesjs/grapes.min.css"><link rel="stylesheet" href="/assets/css/creator-studio.css">';
include $project_root.'includes/header.php';
?>
<div class="flex relative min-h-screen"><?php include $project_root.'includes/admin_sidebar.php'; ?><main class="main-content-area flex-1 studio-main">
<header class="studio-heading"><div><span class="studio-eyebrow">FOREVERCMS / HOW TO JOIN US</span><h1>Page studio</h1><p>Edit the complete How to join us page for your own server: every heading, paragraph, image, link and layout. Other application screens are not replaced by this editor.</p></div><a class="studio-button" href="<?php echo htmlspecialchars($catalog[$slug]['url'].'?lang='.$locale,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Open page</a></header>
<form method="get" class="studio-toolbar"><label>Page<select name="slug" onchange="this.form.submit()"><?php foreach($catalog as $key=>$entry):?><option value="<?php echo htmlspecialchars($key,ENT_QUOTES,'UTF-8'); ?>" <?php echo $slug===$key?'selected':''; ?>><?php echo htmlspecialchars($entry['label'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach;?></select></label><label>Content language<select name="locale" onchange="this.form.submit()"><option value="en" <?php echo $locale==='en'?'selected':''; ?>>English</option><option value="fr" <?php echo $locale==='fr'?'selected':''; ?>>Français</option></select></label><label class="grow">Title<input id="pageTitle" value="<?php echo htmlspecialchars($page['title']??$catalog[$slug]['label'],ENT_QUOTES,'UTF-8'); ?>"></label><span class="studio-status" id="pageState"><?php echo !empty($page['enabled'])?'Published':'Original page'; ?></span></form>
<div class="studio-toolbar"><button class="studio-button" id="saveDraft">Save draft</button><button class="studio-button primary" id="publishPage">Publish</button><button class="studio-button" id="originalPage">Use original page</button><button class="studio-button" id="importOriginal">Import current layout</button><select id="pageRevision"><option value="">Restore a saved revision…</option><?php foreach($revisions as $rev):?><option value="<?php echo (int)$rev['id']; ?>"><?php echo htmlspecialchars($rev['saved_at'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach;?></select><span id="editorStatus" role="status"></span></div>
<p class="studio-help"><?php echo $catalog[$slug]['mode']==='replace'?'This layout replaces the page content.':'This layout is shown above the page. Its functional forms and live data remain available.'; ?> Use <code>{{realmlist}}</code>, <code>{{launcher_url}}</code>, <code>{{discord_url}}</code> or <code>{{site_title}}</code> to keep settings up to date.</p>
<div id="pageEditor" style="height:760px;border:1px solid #453b24"></div>
<div class="studio-credit">GrapesJS 0.22.13 · BSD-3-Clause · Free visual editor · Made with love by FilinoDZ</div>
</main></div><script src="/assets/vendor/grapesjs/grapes.min.js"></script><script>
window.ForeverPageStudio=<?php echo json_encode(['slug'=>$slug,'locale'=>$locale,'url'=>$catalog[$slug]['url'],'mode'=>$catalog[$slug]['mode'],'csrf'=>$_SESSION['csrf_token'],'page'=>$page,'launcherUrl'=>$forever_launcher_url??''],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
</script><script src="/assets/js/admin/page-studio.js"></script><?php include $project_root.'includes/footer.php'; ?>
