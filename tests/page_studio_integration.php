<?php
// Local-only API test. Uses an ephemeral admin session and refuses existing page content.
define('ALLOWED_ACCESS',true);require __DIR__.'/../www/includes/forever_pages.php';
$runtime=getenv('FOREVER_CMS');if(!$runtime||!is_dir($runtime.'/tmp/sessions'))throw new RuntimeException('Set FOREVER_CMS to the installed CMS');
$auth=new mysqli('127.0.0.1','trinity','trinity','auth',3317);$account=$auth->query('SELECT username FROM account WHERE id=1')->fetch_assoc();if(!$account)throw new RuntimeException('Test admin account missing');
$sid=bin2hex(random_bytes(24));$csrf=bin2hex(random_bytes(32));session_save_path($runtime.'/tmp/sessions');session_name('forever_cms');session_id($sid);session_start();$sid=session_id();$_SESSION=['user_id'=>1,'account_name'=>$account['username'],'role'=>'admin','csrf_token'=>$csrf,'last_regeneration'=>time(),'lang'=>'en'];session_write_close();
mysqli_report(MYSQLI_REPORT_OFF);$db=new mysqli('127.0.0.1','trinity','trinity','forever_site',3317);$db->set_charset('utf8mb4');forever_pages_schema($db);
$slug='how_to_play';$locale='en';$revision=0;$ours=false;$failed=false;
function page_request(string $path,?array $body=null): array {global $sid;$h=curl_init('http://127.0.0.1:8080'.$path);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>'forever_cms='.$sid]);if($body!==null)curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($body)]);$out=curl_exec($h);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);return ['status'=>$status,'body'=>$out,'json'=>json_decode($out,true)];}
function page_save(string $action,?int $restore=null): array {global $csrf,$revision;$r=page_request('/admin/settings/page_manager?api=page&slug=how_to_play&locale=en',['action'=>$action,'csrf_token'=>$csrf,'revision'=>$revision,'title'=>'Managed integration test','html'=>'<section><h1>Managed integration test</h1><p>{{realmlist}}</p><script>alert(1)</script></section>','css'=>'section > p{color:#dfc477}','project'=>[],'restore_id'=>$restore]);if(empty($r['json']['ok']))throw new RuntimeException('Page API HTTP '.$r['status'].': '.($r['body']?:'Empty response'));$revision=$r['json']['revision'];return $r['json'];}
try{
    if(forever_pages_get($db,$slug,$locale))throw new RuntimeException('An English How to join us layout already exists; test refused to overwrite it.');
    $r=page_save('draft');$ours=true;if($r['enabled']||str_contains($r['html'],'<script'))throw new RuntimeException('Draft / sanitization failed');
    $first=(int)$db->query("SELECT id FROM website_page_revisions WHERE slug='how_to_play' AND locale='en' ORDER BY id LIMIT 1")->fetch_assoc()['id'];
    $r=page_save('publish');if(!$r['enabled'])throw new RuntimeException('Publish failed');$public=page_request('/how_to_play?lang=en');if(!str_contains($public['body'],'forever-managed-page')||!str_contains($public['body'],'Managed integration test'))throw new RuntimeException('Published layout is not on the public page');
    $r=page_request('/admin/settings/page_manager?api=page&slug=how_to_play&locale=en',['action'=>'draft','csrf_token'=>$csrf,'revision'=>0]);if(!empty($r['json']['ok']))throw new RuntimeException('Stale page revision accepted');
    page_save('restore',$first);page_save('disable');$public=page_request('/how_to_play?lang=en');if(str_contains($public['body'],'forever-managed-page'))throw new RuntimeException('Original page restoration failed');
    echo "Page studio draft / publish / public rendering / revision guard / restore / original-page switch passed.\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n");}
finally{
    if($ours){$page=forever_pages_get($db,$slug,$locale);if((int)$page['revision']===$revision&&$page['title']==='Managed integration test'){$db->query("DELETE FROM website_page_revisions WHERE slug='how_to_play' AND locale='en'");$db->query("DELETE FROM website_pages WHERE slug='how_to_play' AND locale='en'");}else{$failed=true;fwrite(STDERR,"Page changed outside the test; cleanup deliberately refused.\n");}}
    $sessionFile=$runtime.'/tmp/sessions/sess_'.$sid;if(is_file($sessionFile))unlink($sessionFile);
}
exit($failed?1:0);
