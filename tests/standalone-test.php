<?php
require __DIR__.'/../setup/standalone.php';
$base=['user'=>'cms_user','password'=>"not-a-real-password'\\",'site_name'=>'Test server'];
$options=standalone_options($base);
if($options['site']!=='forever_site')throw new RuntimeException('Default website schema missing');
foreach([['site'=>'auth'],['auth'=>'auth; DROP DATABASE world'],['port'=>0],['portal'=>'https://example.invalid'],['site_url'=>'https://u:p@example.invalid'],['realm_id'=>-1]] as $bad){
    $rejected=false;try{standalone_options(array_replace($base,$bad));}catch(InvalidArgumentException $e){$rejected=true;}
    if(!$rejected)throw new RuntimeException('Unsafe configuration accepted');
}
define('ALLOWED_ACCESS',true);$config=standalone_secret($options);$path=tempnam(sys_get_temp_dir(),'forevercms-config-test-');file_put_contents($path,$config);
try{require $path;if($db_site_pass!==$base['password'])throw new RuntimeException('PHP secret quoting failed');}finally{unlink($path);}
echo "8 standalone configuration checks passed; no database was accessed.\n";
