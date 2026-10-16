<?php
if (!defined('ALLOWED_ACCESS')) exit('Forbidden');

function forever_pages_catalog(): array
{
    return [
        'how_to_play'=>['label'=>'How to join us','url'=>'/how_to_play','mode'=>'replace'],
    ];
}

function forever_pages_schema($db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS website_pages (
        slug VARCHAR(80) NOT NULL, locale VARCHAR(8) NOT NULL, title VARCHAR(255) NOT NULL,
        html LONGTEXT NOT NULL, css LONGTEXT NOT NULL, project_json LONGTEXT NOT NULL,
        published_html LONGTEXT NULL, published_css LONGTEXT NULL, enabled TINYINT NOT NULL DEFAULT 0,
        revision INT NOT NULL DEFAULT 1, updated_by INT UNSIGNED NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(slug,locale)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS website_page_revisions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(80) NOT NULL, locale VARCHAR(8) NOT NULL,
        title VARCHAR(255) NOT NULL, html LONGTEXT NOT NULL, css LONGTEXT NOT NULL, project_json LONGTEXT NOT NULL,
        saved_by INT UNSIGNED NOT NULL, saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX page_history(slug,locale,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function forever_pages_clean_html(string $html): string
{
    $doc = new DOMDocument();
    $previous=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="forever-page-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);
    $xpath=new DOMXPath($doc);
    foreach(iterator_to_array($xpath->query('//script|//iframe|//object|//embed|//form|//input|//button|//textarea|//select|//meta|//link|//base|//svg|//math|//style|//noscript')) as $node) $node->parentNode?->removeChild($node);
    foreach($xpath->query('//*') as $node) {
        foreach(iterator_to_array($node->attributes) as $attr) {
            $name=strtolower($attr->name); $value=trim($attr->value);
            if(str_starts_with($name,'on')||in_array($name,['srcdoc','formaction'],true)) $node->removeAttribute($attr->name);
            elseif(in_array($name,['href','src','poster','xlink:href'],true)) {
                $plain=preg_replace('/[\x00-\x20]+/','',html_entity_decode($value,ENT_QUOTES,'UTF-8'));
                if(preg_match('/^(?:javascript|vbscript|data|file):/i',$plain)) $node->removeAttribute($attr->name);
            } elseif($name==='style' && preg_match('/expression|javascript|behavior|@import/i',$value)) $node->removeAttribute($attr->name);
        }
        if(strtolower($node->tagName)==='a') { $node->setAttribute('target','_top'); $node->setAttribute('rel','noopener'); }
    }
    $root=$doc->getElementById('forever-page-root');$out='';
    if($root) foreach($root->childNodes as $node) $out.=$doc->saveHTML($node);
    libxml_clear_errors();libxml_use_internal_errors($previous);
    return $out;
}

function forever_pages_clean_css(string $css): string
{
    if(preg_match('/<|expression\s*\(|javascript\s*:|behavior\s*:|@import/i',$css)) throw new InvalidArgumentException('Unsupported CSS. Use the visual style editor.');
    return $css;
}

function forever_pages_get($db,string $slug,string $locale): ?array
{
    $stmt=$db->prepare('SELECT * FROM website_pages WHERE slug=? AND locale=?');
    if(!$stmt) return null;
    $stmt->bind_param('ss',$slug,$locale);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row?:null;
}

function forever_pages_tokens(string $html): string
{
    global $forever_portal,$forever_launcher_url,$site_title_name,$social_links,$forever_public_address,$forever_client_build,$forever_website_url,$site_url;
    $tokens=['realmlist'=>$forever_portal??'trinity.actual.battle.net','launcher_url'=>$forever_launcher_url??'','site_title'=>$site_title_name??'Forever','discord_url'=>$social_links['discord']??''];
    $public=(string)($forever_public_address??parse_url($site_url??'',PHP_URL_HOST)??'');
    $ip=preg_match('/^[A-Za-z0-9.-]{1,253}$/D',$public)?gethostbyname($public):'';
    $tokens+=['public_address'=>$public,'connection_ip'=>filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)?$ip:'DNS unavailable — contact the server team','client_build'=>$forever_client_build??'70374','website_url'=>$forever_website_url??$site_url??'/','connection_kit_url'=>'/download/connection'];
    foreach($tokens as $key=>$value) $html=str_ireplace(['{{'.$key.'}}',rawurlencode('{{'.$key.'}}')],htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'),$html);
    return $html;
}

function forever_pages_boot(): void
{
    global $site_db,$langCode;
    $path=trim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/');$slug=$path?:'home';
    $catalog=forever_pages_catalog();if(!isset($catalog[$slug])||!isset($site_db)) return;
    if(isset($_GET['original']) && in_array($_SESSION['role']??'',['admin','moderator'],true)) return;
    $page=forever_pages_get($site_db,$slug,$langCode??'en');
    if(!$page||!$page['enabled']||$page['published_html']===null) return;
    $doc='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/tailwind.css"><link rel="stylesheet" href="/assets/css/forever-theme.css"><link rel="stylesheet" href="/node_modules/@fortawesome/fontawesome-free/css/all.min.css"><style>body{margin:0;padding:0;color:#dbe1eb;background:transparent!important;font-family:Arial,sans-serif}body::before,body::after{display:none!important}img{max-width:100%}a{color:#e8ca70}'.$page['published_css'].'</style></head><body class="how_to_play">'.forever_pages_tokens($page['published_html']).'</body></html>';
    $frame='<iframe class="forever-managed-page" title="'.htmlspecialchars($page['title'],ENT_QUOTES,'UTF-8').'" sandbox="allow-same-origin allow-top-navigation-by-user-activation allow-downloads" srcdoc="'.htmlspecialchars($doc,ENT_QUOTES,'UTF-8').'" style="width:100%;border:0;min-height:360px;display:block" onload="const frame=this,body=frame.contentDocument.body;const fit=()=>frame.style.height=Math.max(360,body.scrollHeight)+\'px\';fit();new ResizeObserver(fit).observe(body)"></iframe>';
    $replace=$catalog[$slug]['mode']==='replace';
    ob_start(static function($output) use($frame,$replace) {
        if($replace) return preg_replace_callback('~(<main\b[^>]*>).*?(</main>)~s',static fn($m)=>$m[1].$frame.$m[2],$output,1);
        return preg_replace_callback('~<main\b[^>]*>~',static fn($m)=>$m[0].$frame,$output,1);
    });
}
