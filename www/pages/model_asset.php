<?php
// Fixed-origin model asset gateway. It supplies the public Armory and admin 3D
// viewers with the same assets; arbitrary URLs and local paths are never accepted.
$asset=(string)($_GET['asset']??'');
if(str_starts_with($asset,'v3/'))$asset=substr($asset,3);
if(!preg_match('~^(live|classic)/(meta/[a-z0-9_/-]+\.json|(?:mo3|bone|anim)/[0-9_/-]+\.(?:mo3|bone|anim)|textures/[0-9_/-]+\.(?:png|jpg|webp))$~i',$asset)||str_contains($asset,'..')||strlen($asset)>160){http_response_code(400);exit;}
$root=dirname(__DIR__,2).'/cache/models';if(!is_dir($root))@mkdir($root,0755,true);
$file=$root.'/'.hash('sha256','v3:'.$asset);$extension=pathinfo($asset,PATHINFO_EXTENSION);
$types=['json'=>'application/json','png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp','mo3'=>'application/octet-stream','bone'=>'application/octet-stream','anim'=>'application/octet-stream'];
$relative=substr($asset,strpos($asset,'/')+1);$bundled=__DIR__.'/../3dmodels/wow-data/'.$relative;
if(is_file($bundled)){header('Content-Type: '.$types[$extension]);header('Cache-Control: public, max-age=86400');header('X-Content-Type-Options: nosniff');readfile($bundled);exit;}
if(is_file($file)&&filemtime($file)>time()-2592000){header('Content-Type: '.$types[$extension]);header('Cache-Control: public, max-age=86400');header('X-Content-Type-Options: nosniff');readfile($file);exit;}
$limit=30*1024*1024;$data='';$status=0;$error='';
// Astral's public model archive matches the bundled renderer and preserves
// geometry that the current Wowhead endpoint no longer serves. Both origins
// are fixed; callers cannot turn this gateway into an arbitrary web proxy.
foreach(['https://armory.playastral.io/data/'.$relative,'https://wow.zamimg.com/modelviewer/'.$asset] as $url){
    $data='';$handle=curl_init($url);
    curl_setopt_array($handle,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'ForeverCMS/1.5 (WoW model preview)',CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$data,$limit){if(strlen($data)+strlen($chunk)>$limit)return 0;$data.=$chunk;return strlen($chunk);}]);
    // Retain peer/hostname verification and use Windows' trusted roots.
    if(PHP_OS_FAMILY==='Windows'&&defined('CURLSSLOPT_NATIVE_CA'))curl_setopt($handle,CURLOPT_SSL_OPTIONS,CURLSSLOPT_NATIVE_CA);
    $ok=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
    if($ok&&$status===200&&$data!=='')break;
}
if(!$ok||$status!==200||$data===''){if($error!=='')error_log('ForeverCMS model gateway: '.$relative.' '.$error);http_response_code($status===404?404:502);header('Cache-Control: no-store');exit;}
if($extension==='json'){
    $metadata=json_decode($data,true);if(!is_array($metadata)){http_response_code(502);exit;}
    // Recent CDN NPC metadata nests humanoid identity in Character; this
    // renderer expects those fields at the top level, like the archive does.
    if(isset($metadata['Character'])&&is_array($metadata['Character']))foreach(['Race','Gender','RaceFlags','ChrModelFlags','ChrModelId'] as $key)if(isset($metadata['Character'][$key]))$metadata[$key]=$metadata['Character'][$key];
    if(isset($metadata['StateKits']))$metadata['stateKits']=$metadata['StateKits'];
    $data=json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}
if(in_array($extension,['png','jpg','webp'],true)&&!@getimagesizefromstring($data)){http_response_code(502);exit;}
$tmp=$file.'.'.bin2hex(random_bytes(5)).'.tmp';if(file_put_contents($tmp,$data,LOCK_EX)!==false)@rename($tmp,$file);
header('Content-Type: '.$types[$extension]);header('Cache-Control: public, max-age=86400');header('X-Content-Type-Options: nosniff');echo $data;
