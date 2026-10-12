<?php
if (!defined('ALLOWED_ACCESS')) {
    exit('Direct access not allowed.');
}

/**
 * Resolve the Discord server ID used by the home-page widget.
 *
 * A manually configured widget ID wins. When it is empty, the server ID is
 * resolved from the Discord invitation URL configured in General Settings.
 * Only discord.com and discord.gg are contacted, and the result is cached.
 */
function forever_discord_widget_id(string $discordUrl, string $manualId = ''): string
{
    $manualId = trim($manualId);
    if (preg_match('/^\d{17,20}$/', $manualId)) {
        return $manualId;
    }

    $discordUrl = trim($discordUrl);
    if ($discordUrl === '') {
        return '';
    }

    if (preg_match('~discord(?:app)?\.com/widget\?[^#]*\bid=(\d{17,20})~i', $discordUrl, $matches)) {
        return $matches[1];
    }

    $parts = parse_url($discordUrl);
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = trim((string)($parts['path'] ?? ''), '/');
    if ($host === 'discord.gg') {
        $inviteCode = explode('/', $path, 2)[0] ?? '';
    } elseif (in_array($host, ['discord.com', 'www.discord.com', 'discordapp.com', 'www.discordapp.com'], true)) {
        $segments = explode('/', $path);
        $inviteCode = (($segments[0] ?? '') === 'invite') ? ($segments[1] ?? '') : '';
    } else {
        return '';
    }

    if (!preg_match('/^[A-Za-z0-9_-]{2,64}$/', $inviteCode)) {
        return '';
    }

    $cacheDir = dirname(__DIR__) . '/cache';
    $cacheFile = $cacheDir . '/discord-widget-' . hash('sha256', strtolower($discordUrl)) . '.json';
    if (is_file($cacheFile)) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && (int)($cached['expires'] ?? 0) > time()) {
            $cachedId = (string)($cached['guild_id'] ?? '');
            return preg_match('/^\d{17,20}$/', $cachedId) ? $cachedId : '';
        }
    }

    $guildId = '';
    $apiUrl = 'https://discord.com/api/v10/invites/' . rawurlencode($inviteCode) . '?with_counts=true&with_expiration=true';
    $payload = false;
    $status = 0;
    if (function_exists('curl_init')) {
        $curl = curl_init($apiUrl);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'ForeverCMS/1.0',
        ]);
        $payload = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: ForeverCMS/1.0\r\n",
            ],
        ]);
        $payload = @file_get_contents($apiUrl, false, $context);
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $statusMatch)) {
                $status = (int)$statusMatch[1];
                break;
            }
        }
    }

    if ($status === 200 && is_string($payload)) {
        $decoded = json_decode($payload, true);
        $candidate = (string)($decoded['guild']['id'] ?? '');
        if (preg_match('/^\d{17,20}$/', $candidate)) {
            $guildId = $candidate;
        }
    }

    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        @file_put_contents($cacheFile, json_encode([
            'guild_id' => $guildId,
            'expires' => time() + ($guildId !== '' ? 21600 : 900),
        ], JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    return $guildId;
}

