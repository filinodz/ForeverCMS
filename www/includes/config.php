<?php
if (!defined('ALLOWED_ACCESS')) exit('Direct access not allowed.');

// Forever repack defaults: the repack's own MySQL (port 3317) and its "trinity" user.
// Setup-CMS.bat creates the website database and gives this user access to it.

$db_auth_host = '127.0.0.1';
$db_auth_port = '3317';
$db_auth_user = 'trinity';
$db_auth_pass = 'trinity';
$db_auth_name = 'auth';

$db_world_host = '127.0.0.1';
$db_world_port = '3317';
$db_world_user = 'trinity';
$db_world_pass = 'trinity';
$db_world_name = 'world';

// One characters database per realm: the names are in realm_config.php ("char_db").
// $db_char_name is the database of the realm the visitor is looking at.
$db_char_host = '127.0.0.1';
$db_char_port = '3317';
$db_char_user = 'trinity';
$db_char_pass = 'trinity';

$db_site_host = '127.0.0.1';
$db_site_port = '3317';
$db_site_user = 'trinity';
$db_site_pass = 'trinity';
$db_site_name = 'forever_site';

// Standalone setup stores local credentials in an ignored, private file.
if (is_file(__DIR__ . '/config.database.php')) require __DIR__ . '/config.database.php';
require_once __DIR__ . '/realm_config.php';
require_once __DIR__ . '/forever.php';

mysqli_report(MYSQLI_REPORT_OFF);

$auth_db  = @new mysqli($db_auth_host,  $db_auth_user,  $db_auth_pass,  $db_auth_name,  (int)$db_auth_port);
$world_db = @new mysqli($db_world_host, $db_world_user, $db_world_pass, $db_world_name, (int)$db_world_port);
$site_db  = @new mysqli($db_site_host,  $db_site_user,  $db_site_pass,  $db_site_name,  (int)$db_site_port);

if ($auth_db->connect_error || $world_db->connect_error || $site_db->connect_error) {
    error_log('Forever CMS: database connection failed: ' . ($auth_db->connect_error ?: ($world_db->connect_error ?: $site_db->connect_error)));
    http_response_code(503);
    die('The website cannot reach its databases. Check MySQL availability and the private database configuration. The server administrator can run Install-Standalone.bat to configure the connection.');
}

$auth_db->set_charset('utf8mb4');
$world_db->set_charset('utf8mb4');
$site_db->set_charset('utf8mb4');

$current_realm = forever_current_realm();
$db_char_name = $current_realm['char_db'];
$char_db = forever_char_db((int)$current_realm['id']);
if (!$char_db) {
    http_response_code(503);
    die('The website cannot reach the characters database of realm ' . htmlspecialchars($current_realm['name']) . '.');
}
?>
