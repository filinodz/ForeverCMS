<?php
// Forever CMS: request router for PHP's built-in web server (php -S ... -t www router.php).
// Does what www/.htaccess does under Apache: pretty URLs to pages/*.php, everything else to index.php.

/**
 * Finds what a request path stands for.
 * Returns a PHP file to run, false for a static file the built-in server should send, or null when it answered itself.
 */
function forever_route($root, $path)
{
    if (preg_match('~^/model-assets/(.+)$~',$path,$modelMatch)) {
        $_GET['asset']=$modelMatch[1];
        return $root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'model_asset.php';
    }
    if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
        http_response_code(400);
        echo 'Bad request';
        return null;
    }

    // folders that hold code, settings or logs, not pages (the language flags are the only files served from them)
    if (preg_match('#^/(includes|languages|logs|cache|vendor|sql)(/|$)#i', $path) && !preg_match('#^/languages/flags/[A-Za-z0-9_\-]+\.png$#', $path)) {
        http_response_code(403);
        echo 'Forbidden';
        return null;
    }

    $file = realpath($root . str_replace('/', DIRECTORY_SEPARATOR, $path));
    if ($file !== false && strncmp($file, $root, strlen($root)) === 0) {
        if (is_dir($file)) {
            if ($path !== '/' && substr($path, -1) !== '/') {
                $query = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
                header('Location: ' . $path . '/' . ($query !== '' ? '?' . $query : ''), true, 301);
                return null;
            }
            if (is_file($file . DIRECTORY_SEPARATOR . 'index.php')) {
                return $file . DIRECTORY_SEPARATOR . 'index.php';
            }
        } elseif (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
            return $file;
        } else {
            return false;
        }
    }

    // /login -> pages/login.php, /admin/settings/general -> pages/admin/settings/general.php
    $route = trim($path, '/');
    if ($route !== '' && preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+){0,4}$#', $route)) {
        $page = $root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $route) . '.php';
        if (is_file($page)) {
            return $page;
        }
    }

    // a missing file is a plain 404; anything else goes to the home page like the .htaccess fallback
    if (preg_match('#\.[A-Za-z0-9]{1,5}$#', $path)) {
        http_response_code(404);
        echo 'Not found';
        return null;
    }
    $_GET['page'] = $route;
    return $root . DIRECTORY_SEPARATOR . 'index.php';
}

define('FOREVER_WWW', __DIR__ . DIRECTORY_SEPARATOR . 'www');
define('FOREVER_TARGET', forever_route(FOREVER_WWW, '/' . ltrim(str_replace('\\', '/', rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))), '/')));

if (FOREVER_TARGET === false) {
    return false;
}
if (FOREVER_TARGET === null) {
    return true;
}

// The page runs here, in the global scope, as it would under Apache (its functions use "global").
$_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace('\\', '/', substr(FOREVER_TARGET, strlen(FOREVER_WWW))), '/');
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = FOREVER_TARGET;
chdir(dirname(FOREVER_TARGET));
require FOREVER_TARGET;
return true;
