<?php
// CLI-only installer. Credentials arrive on stdin, never command-line arguments.
function standalone_options(array $input): array {
    $o=$input+['host'=>'127.0.0.1','port'=>3306,'user'=>'','password'=>'','auth'=>'auth','world'=>'world','characters'=>'characters','site'=>'forever_site','realm_id'=>70,'realm_name'=>'Normal','realm_port'=>8085,'site_name'=>'ForeverCMS','site_url'=>'http://127.0.0.1:8080','portal'=>'127.0.0.1'];
    foreach(['auth','world','characters','site'] as $name) if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',(string)$o[$name])) throw new InvalidArgumentException('Use database names containing letters, numbers and underscores only.');
    if(in_array($o['site'],[$o['auth'],$o['world'],$o['characters']],true)) throw new InvalidArgumentException('The website needs its own database, separate from game data.');
    foreach(['port','realm_port'] as $key) if(!filter_var($o[$key],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]])) throw new InvalidArgumentException('Invalid port.');
    if(!filter_var($o['realm_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])) throw new InvalidArgumentException('Invalid realm ID.');
    foreach(['host','portal'] as $key) if(!preg_match('/^[A-Za-z0-9_.:-]{1,253}$/D',(string)$o[$key])) throw new InvalidArgumentException('Enter a hostname or IP address, not a URL.');
    $url=parse_url($o['site_url']); if(!$url||!in_array($url['scheme']??'',['http','https'],true)||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])) throw new InvalidArgumentException('Enter the website HTTP or HTTPS URL without credentials.');
    foreach(['site_name','realm_name','user'] as $key) if(trim((string)$o[$key])===''||strlen($o[$key])>128||preg_match('/[\x00-\x1f]/',$o[$key])) throw new InvalidArgumentException('Fill in the server, realm and database user names.');
    return $o;
}
function standalone_secret(array $o): string {
    $vars=[];
    foreach(['auth','world','char','site'] as $kind) {
        foreach(['host'=>'host','port'=>'port','user'=>'user','pass'=>'password'] as $suffix=>$key) $vars['db_'.$kind.'_'.$suffix]=$o[$key];
        if($kind!=='char') $vars['db_'.$kind.'_name']=$o[$kind];
    }
    $text="<?php\nif (!defined('ALLOWED_ACCESS')) exit('Forbidden');\n// Private local configuration. Never commit or share this file.\n";
    foreach($vars as $key=>$value) $text.='$'.$key.' = '.var_export($value,true).";\n";
    return $text;
}
function standalone_write(string $path,string $content): void {
    $tmp=$path.'.setup-'.bin2hex(random_bytes(5));
    if(file_put_contents($tmp,$content,LOCK_EX)===false) throw new RuntimeException('Cannot write configuration. Check folder permissions.');
    @chmod($tmp,0600);
    if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Cannot save configuration.');}
}
function standalone_install(array $o,string $root,bool $check): array {
    foreach(['mysqli','gmp','mbstring','openssl','dom','sodium'] as $extension) if(!extension_loaded($extension)) throw new RuntimeException('Enable the PHP extension: '.$extension);
    mysqli_report(MYSQLI_REPORT_OFF);
    $db=@new mysqli($o['host'],$o['user'],$o['password'],'',(int)$o['port']);
    if($db->connect_errno) throw new RuntimeException('Cannot connect to MySQL. Check host, port and credentials.');
    $db->set_charset('utf8mb4');
    foreach(['auth'=>['account','battlenet_accounts'],'characters'=>['characters'],'world'=>['creature_template']] as $kind=>$tables) foreach($tables as $table) {
        $st=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');$st->bind_param('ss',$o[$kind],$table);$st->execute();
        if((int)$st->get_result()->fetch_row()[0]!==1) throw new RuntimeException('Required ForeverCore table missing: '.$o[$kind].'.'.$table);$st->close();
    }
    if($check){$db->close();return ['ok'=>true,'message'=>'Connection and ForeverCore databases verified. No files or databases changed.'];}
    $backup=$root.'/setup/backups/standalone-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
    if(!mkdir($backup,0700,true)) throw new RuntimeException('Cannot create the configuration backup.');
    foreach(['config.database.php','config.secret.php','realm_config.php','config.settings.php'] as $file) if(is_file($root.'/www/includes/'.$file)&&!copy($root.'/www/includes/'.$file,$backup.'/'.$file)) throw new RuntimeException('Configuration backup failed.');
    $sql=file_get_contents($root.'/sql/forever_site.sql');
    if($sql===false||preg_match('/\b(?:DROP|TRUNCATE)\b/i',preg_replace('/--[^\n]*/','',$sql))) throw new RuntimeException('Invalid website schema.');
    $sql=str_replace('`forever_site`','`'.$o['site'].'`',$sql);
    if(!$db->multi_query($sql)) throw new RuntimeException('Cannot create the website schema. The database user needs permission on the website database.');
    do{if($r=$db->store_result())$r->free();if(!$db->more_results())break;if(!$db->next_result())throw new RuntimeException('Website schema import failed. Check database permissions; rerunning setup is safe.');}while(true);
    $site='`'.$o['site'].'`';$auth='`'.$o['auth'].'`';
    $sync="INSERT INTO $site.user_currencies (account_id,username,email,role) SELECT a.id,a.username,LOWER(COALESCE(b.email,a.email)),IF(COALESCE(MAX(aa.SecurityLevel),0)>=3 AND COALESCE(b.email,a.email) NOT LIKE '%@LAUNCHER','admin','player') FROM $auth.account a LEFT JOIN $auth.battlenet_accounts b ON b.id=a.battlenet_account LEFT JOIN $auth.account_access aa ON aa.AccountID=a.id GROUP BY a.id,a.username,b.email,a.email ON DUPLICATE KEY UPDATE username=VALUES(username),email=VALUES(email),role=IF(VALUES(role)='admin','admin',role)";
    if(!$db->query($sync)) throw new RuntimeException('Website schema is ready, but game accounts could not be linked. Check account table read permissions. No game accounts were modified.');
    $realm=[['id'=>(int)$o['realm_id'],'name'=>$o['realm_name'],'type'=>'normal','address'=>$o['portal'],'check_address'=>$o['portal'],'port'=>(int)$o['realm_port'],'ra_port'=>3443,'char_db'=>$o['characters'],'logo'=>'img/forever/realm-crest.svg','player_display'=>'separate','show_status'=>true]];
    standalone_write($root.'/www/includes/config.database.php',standalone_secret($o));
    standalone_write($root.'/www/includes/realm_config.php',"<?php\nif (!defined('ALLOWED_ACCESS')) exit('Forbidden');\n\$realmlist = ".var_export($realm,true).";\n");
    $settings=str_replace("\r\n","\n",file_get_contents($root.'/www/includes/config.settings.php'));
    foreach(['site_title_name'=>$o['site_name'],'forever_portal'=>$o['portal'],'forever_public_address'=>$o['portal'],'forever_website_url'=>rtrim($o['site_url'],'/'),'forever_launcher_url'=>''] as $key=>$value) {
        $assignment='$'.$key.' = '.var_export($value,true).';';$pattern='/^[ \t]*\$'.preg_quote($key,'/').'\s*=[^\r\n]*;[ \t]*$/m';
        if(preg_match($pattern,$settings))$settings=preg_replace_callback($pattern,static fn()=>$assignment,$settings);else { $closing=strrpos($settings,'?>');$settings=$closing===false?$settings."\n".$assignment."\n":substr_replace($settings,$assignment."\n",$closing,0); }
    }
    standalone_write($root.'/www/includes/config.settings.php',$settings);
    foreach(['logs','tmp/sessions'] as $dir)if(!is_dir($root.'/'.$dir))mkdir($root.'/'.$dir,0700,true);
    $db->close();return ['ok'=>true,'message'=>'ForeverCMS is configured. Start your web server or use Start-CMS.bat for a local preview. Existing GM level 3 accounts administer the website. Game databases, launchers and game accounts were not modified.'];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
    try{$o=standalone_options(json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR));$result=standalone_install($o,dirname(__DIR__),in_array('--check',$argv,true));echo json_encode($result,JSON_THROW_ON_ERROR);}
    catch(Throwable $e){echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);exit(1);}
}
