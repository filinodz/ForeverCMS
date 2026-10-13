<?php
define('ALLOWED_ACCESS',true);require __DIR__.'/../www/includes/forever_pages.php';
$clean=forever_pages_clean_html('<h1>Join us</h1><script>alert(1)</script><a href="java&#x09;script:alert(1)" onclick="alert(1)">Link</a><img src="/img/test.png" onerror="alert(1)"><form><input></form><p>{{realmlist}}</p>');
if(str_contains($clean,'script:')||str_contains($clean,'<script')||str_contains($clean,'onerror')||str_contains($clean,'onclick')||str_contains($clean,'<form')||!str_contains($clean,'<h1>Join us</h1>'))throw new RuntimeException('Page HTML sanitizer failed');
try{forever_pages_clean_css('</style><script>alert(1)</script>');throw new RuntimeException('CSS injection accepted');}catch(InvalidArgumentException $e){}
$forever_portal='example.test';$forever_launcher_url='https://example.test/launcher';$site_title_name='Forever';$social_links=['discord'=>'https://discord.gg/example'];
if(forever_pages_tokens('{{realmlist}}')!=='example.test')throw new RuntimeException('Realmlist token failed');
echo "Page HTML / CSS injection guards and dynamic settings tokens passed.\n";
