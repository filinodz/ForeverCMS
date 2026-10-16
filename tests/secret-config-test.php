<?php
// Regression: a registration key must never replace standalone DB credentials.
$fixture=sys_get_temp_dir().'/forevercms-config-test-'.bin2hex(random_bytes(8));
mkdir($fixture,0700,true);
try {
    foreach(['forever.php','srp6.php'] as $name)copy(__DIR__.'/../www/includes/'.$name,$fixture.'/'.$name);
    $database="<?php\n\$db_auth_name='fixture_only';\n";
    file_put_contents($fixture.'/config.database.php',$database);
    define('ALLOWED_ACCESS',true);require $fixture.'/forever.php';
    $key=forever_secret_key();
    if(strlen($key)!==32)throw new RuntimeException('Invalid encryption key');
    if(forever_secret_key()!==$key)throw new RuntimeException('Key changed between calls');
    if(file_get_contents($fixture.'/config.database.php')!==$database)throw new RuntimeException('Database configuration was replaced');
    $sealed=forever_seal('test-only-registration-secret');
    if(forever_unseal($sealed)!=='test-only-registration-secret')throw new RuntimeException('Registration encryption failed');
    if(file_get_contents($fixture.'/config.database.php')!==$database)throw new RuntimeException('Registration touched database settings');
    $installer=file_get_contents(__DIR__.'/../setup/standalone.php');
    if(!str_contains($installer,"standalone_write(\$root.'/www/includes/config.database.php'"))throw new RuntimeException('Installer uses the wrong configuration file');
    echo "6 configuration/encryption isolation checks passed. No database was accessed.\n";
} finally {
    foreach(['config.database.php','config.secret.php','forever.php','srp6.php'] as $name)if(is_file($fixture.'/'.$name))unlink($fixture.'/'.$name);
    rmdir($fixture);
}
