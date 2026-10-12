<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

// RA (worldserver remote console). The port of each realm is in realm_config.php.
// Leave the user empty to use the launcher's console account from localservers.json.
$ra_host = '127.0.0.1';
$ra_user = '';
$ra_pass = '';
$ra_localservers = '../../localservers.json';
?>
