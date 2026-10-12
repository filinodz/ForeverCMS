<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

/**
 * Forever CMS: game commands go through RA (the worldserver's remote console, worldserver.conf Ra.Enable),
 * one port per realm. This replaces the SOAP calls of SahtoutCMS; Forever ships with SOAP off and RA on.
 */

/** The settings of ra.conf.php. Read here, not as globals: this file is also loaded from inside functions. */
function forever_ra_config()
{
    static $config = null;
    if ($config === null) {
        $ra_host = '127.0.0.1';
        $ra_user = '';
        $ra_pass = '';
        $ra_localservers = '../../localservers.json';
        if (is_file(__DIR__ . '/ra.conf.php')) {
            include __DIR__ . '/ra.conf.php';
        }
        $config = ['host' => (string)$ra_host, 'user' => (string)$ra_user, 'pass' => (string)$ra_pass, 'localservers' => (string)$ra_localservers];
    }
    return $config;
}

/**
 * The RA login. When ra.conf.php has no user, the launcher's console account is used
 * (localservers.json "consoleUser" / "consolePassword", made by the repack's Setup).
 */
function forever_ra_credentials()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $config = forever_ra_config();
    $ra_host = $config['host'];
    $ra_user = $config['user'];
    $ra_pass = $config['pass'];
    $ra_localservers = $config['localservers'];

    $user = (string)($ra_user ?? '');
    $pass = (string)($ra_pass ?? '');
    if ($user === '' && !empty($ra_localservers)) {
        $path = $ra_localservers;
        if (!preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path)) {
            $path = __DIR__ . '/../' . $path;
        }
        if (is_readable($path)) {
            $json = file_get_contents($path);
            $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
            $servers = json_decode($json, true);
            if (is_array($servers)) {
                foreach ($servers as $server) {
                    if (!empty($server['consoleUser'])) {
                        $user = (string)$server['consoleUser'];
                        $pass = (string)($server['consolePassword'] ?? '');
                        break;
                    }
                }
            }
        }
    }

    return $cached = ['host' => (string)($ra_host ?? '127.0.0.1'), 'user' => $user, 'pass' => $pass];
}

function forever_ra_configured()
{
    return forever_ra_credentials()['user'] !== '';
}

/** Double quotes and line breaks would end the command or its quoted argument. */
function forever_ra_quote($text)
{
    return str_replace(['"', "\r", "\n"], ["'", ' ', ' '], (string)$text);
}

function forever_ra_read_until($socket, array $marks, $timeout)
{
    $buffer = '';
    $deadline = microtime(true) + $timeout;
    while (microtime(true) < $deadline) {
        foreach ($marks as $mark) {
            if (strpos($buffer, $mark) !== false) {
                return $buffer;
            }
        }
        $chunk = fread($socket, 4096);
        if ($chunk === false) {
            break;
        }
        if ($chunk === '') {
            if (feof($socket)) {
                break;
            }
            usleep(20000);
            continue;
        }
        $buffer .= $chunk;
    }
    return $buffer;
}

/**
 * Runs one console command (without the leading dot) on a realm's worldserver.
 * Returns ['success' => bool, 'output' => string, 'error' => string].
 */
function forever_ra_command($port, $command, $timeout = 15)
{
    $creds = forever_ra_credentials();
    $result = ['success' => false, 'output' => '', 'error' => ''];

    if ($creds['user'] === '') {
        $result['error'] = 'RA is not configured (no console account).';
        return $result;
    }
    $command = trim(str_replace(["\r", "\n"], ' ', (string)$command));
    $command = ltrim($command, '.');
    if ($command === '') {
        $result['error'] = 'Empty command.';
        return $result;
    }

    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($creds['host'], (int)$port, $errno, $errstr, 5);
    if (!$socket) {
        $result['error'] = 'The realm is offline (RA port ' . (int)$port . ' does not answer).';
        return $result;
    }
    stream_set_blocking($socket, false);

    try {
        forever_ra_read_until($socket, ['Username: '], $timeout);
        fwrite($socket, $creds['user'] . "\r\n");
        forever_ra_read_until($socket, ['Password: '], $timeout);
        fwrite($socket, $creds['pass'] . "\r\n");
        $login = forever_ra_read_until($socket, ['TC>', 'Authentication failed'], $timeout);
        if (strpos($login, 'TC>') === false) {
            $result['error'] = 'RA login failed (the console account needs GM level Ra.MinLevel on all realms).';
            return $result;
        }

        fwrite($socket, $command . "\r\n");
        $output = forever_ra_read_until($socket, ['TC>'], $timeout);
        fwrite($socket, "quit\r\n");

        if (strpos($output, 'TC>') === false) {
            $result['error'] = 'The realm did not answer in time.';
            $result['output'] = trim($output);
            return $result;
        }
        $result['success'] = true;
        $result['output'] = trim(str_replace('TC>', '', $output));
        return $result;
    } finally {
        fclose($socket);
    }
}

function forever_ra_online($port)
{
    $creds = forever_ra_credentials();
    $socket = @fsockopen($creds['host'], (int)$port, $errno, $errstr, 1);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}
