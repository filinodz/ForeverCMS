<?php
// Forever: there is no client to download from the website. The game comes from the Battle.net app and
// Forever Launcher from its release page; both are explained on the How to Play page.
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
header('Location: ' . $base_path . 'how_to_play', true, 302);
exit;
