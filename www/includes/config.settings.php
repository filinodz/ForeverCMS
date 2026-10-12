<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

// Site Title (Editable from Admin Panel)
$site_title_name = 'Forever';

// Featured YouTube video (Editable from Admin Panel)
$youtube_embed_url = '';
$youtube_title = '';
$youtube_description = '';

// Logo
$site_logo = 'img/forever_logo.svg';

// Social links
$social_links = [
    'facebook' => '',
    'twitter' => '',
    'tiktok' => '',
    'youtube' => '',
    'discord' => 'https://discord.gg/4tqq7EQjs9',
    'twitch' => '',
    'kick' => '',
    'instagram' => '',
    'github' => 'https://github.com/advocaite/TrinityCore/tree/forever',
    'linkedin' => '',
];

// Optional manual Discord widget server ID. When empty, the home page resolves
// it automatically from the Discord invitation URL above.
$discord_widget_id = '1552885216970936393';

// Forever: what players need to connect (shown on the How to Play page).
// $forever_portal is the "portal" of the launcher's servers.json entry for this server.
$forever_portal = 'trinity.actual.battle.net';
$forever_launcher_url = 'https://github.com/advocaite/foreverlauncher-releases/releases/latest';
