<?php
define('ALLOWED_ACCESS', true);
require __DIR__ . '/../www/includes/forever_pages.php';
$site_title_name = 'Example Realm';
$forever_portal = 'example.invalid';
$forever_public_address = '127.0.0.1';
$forever_client_build = '99999';
$forever_launcher_url = 'https://example.invalid/launcher';
$social_links = [];
$checks = 0;
function check_guide(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++; echo "PASS $label\n";
}
foreach (['en', 'fr'] as $locale) {
    $html = forever_pages_clean_html(file_get_contents(__DIR__ . '/../www/includes/join-guide/' . $locale . '.html'));
    $rendered = forever_pages_tokens($html);
    check_guide(str_contains($rendered, 'Example Realm'), "$locale: configured website identity resolves");
    check_guide(str_contains($rendered, '/register?lang='.$locale), "$locale: registration uses the selected language");
    check_guide(!str_contains($rendered, '99999') && !str_contains($rendered, 'example.invalid'), "$locale: no server address, fixed client build or launcher URL");
    check_guide(!preg_match('/servegame|Start-PlayerSetup|Setup-PlayerConnection|\.exe|battle\.net|ForeverHub/i', $rendered), "$locale: generic guide has no personal connection tool or installer");
    check_guide(!str_contains($rendered, '{{') && !str_contains(strtolower($rendered), '%7b%7b'), "$locale: no unresolved layout tokens");
}
$site_title_name = 'Test <script>alert(1)</script> & "quotes"';
$rendered = forever_pages_tokens('{{site_title}}');
check_guide(!str_contains($rendered, '<script>') && str_contains($rendered, '&lt;script&gt;'), 'Server identity is HTML escaped');
check_guide(!str_contains(forever_pages_clean_html('<p onclick="bad()">safe</p><script>bad()</script>'), 'onclick'), 'Page Studio strips executable event attributes');
check_guide(!str_contains(file_get_contents(__DIR__.'/../www/includes/realm_status.php'), '$realmAddress'), 'Realm cards no longer render repeated connection addresses');
check_guide(strlen(forever_pages_clean_css(file_get_contents(__DIR__ . '/../www/assets/css/forever-join.css'))) > 100, 'Guide CSS remains compatible with Page Studio');
echo "$checks join guide checks passed. No database or live settings changed.\n";
