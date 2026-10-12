<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Define supported languages and default
$defaultLang = 'en';
$supported = ['en', 'fr', 'es', 'de', 'ru','pt', 'cn'];

// Set language
if (isset($_GET['lang']) && in_array($_GET['lang'], $supported)) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang']) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    $_SESSION['lang'] = in_array($browserLang, $supported) ? $browserLang : $defaultLang;
}
$langCode = $_SESSION['lang'] ?? $defaultLang;

// Load global language file
$globalLangFile = __DIR__ . "/{$langCode}/global.php";
$lang = (file_exists($globalLangFile) && is_readable($globalLangFile)) ? include $globalLangFile : [];

// Convert script path to translation filename
$basePath = realpath(__DIR__ . '/../');
$scriptPath = realpath($_SERVER['SCRIPT_FILENAME']);
$relativePath = str_replace($basePath, '', $scriptPath);
$relativePath = trim($relativePath, '/\\');
$pageLangFileName = str_replace(['/', '\\'], '_', $relativePath);
$pageLangFile = __DIR__ . "/{$langCode}/{$pageLangFileName}";

// Load page-specific language file
if (file_exists($pageLangFile) && is_readable($pageLangFile)) {
    $pageLang = include $pageLangFile;
    if (is_array($pageLang)) {
        $lang = array_merge($lang, $pageLang);
    }
}

// Forever: texts that differ from SahtoutCMS (e-mail login instead of user names, Classic instead of 3.3.5,
// realms, RA instead of SOAP) are in one file per language and win over the global and page texts.
// A language without that file, or without a key, uses the English one.
foreach (array_unique(['en', $langCode]) as $foreverLangCode) {
    $foreverLangFile = __DIR__ . "/{$foreverLangCode}/forever.php";
    if (file_exists($foreverLangFile) && is_readable($foreverLangFile)) {
        $foreverLang = include $foreverLangFile;
        if (is_array($foreverLang)) {
            $lang = array_merge($lang, $foreverLang);
        }
    }
}

// Translation function
function translate($key, $default = '', ...$args) {
    global $lang;
    $string = $lang[$key] ?? $default;
    if (!empty($args)) {
        return vsprintf($string, $args); // Replace %d with provided arguments
    }
    return $string;
}
?>