<?php
// Ensure session is started
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../includes/paths.php';
if (session_status() === PHP_SESSION_NONE) {
    require_once $project_root . 'includes/session.php'; // Includes config.php and starts session
} else {
    require_once $project_root . 'includes/config.php'; // Include config for consistency
}

// Clear both server-side data and the ForeverCMS browser cookie.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();

// Redirect to login page
header("Location: {$base_path}login");
exit;
?>
