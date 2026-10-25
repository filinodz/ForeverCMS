<?php
// Isolated page/feed fixtures: no real database, account or server is accessed.
if (($argv[1] ?? '') === '--fixture') {
    $fixture = $argv[2];
    $scenario = $argv[3];
    $_SERVER['REQUEST_METHOD'] = $scenario === 'post' ? 'POST' : 'GET';
    $_GET['realm'] = 999; // An unadvertised realm must never be selected.
    register_shutdown_function(static function () {
        fwrite(STDERR, 'STATUS:' . (http_response_code() ?: 200));
    });
    require $fixture . '/pages/' . (str_starts_with($scenario, 'page-') ? 'map.php' : 'map_api.php');
    exit;
}

$fixture = sys_get_temp_dir() . '/forevercms-map-test-' . bin2hex(random_bytes(8));
foreach (['pages', 'includes', 'languages'] as $folder) mkdir($fixture . '/' . $folder, 0700, true);
copy(__DIR__ . '/../www/pages/map_api.php', $fixture . '/pages/map_api.php');
copy(__DIR__ . '/../www/pages/map.php', $fixture . '/pages/map.php');
file_put_contents($fixture . '/includes/paths.php', '<?php $project_root=dirname(__DIR__)."/"; $base_path="/";');
file_put_contents($fixture . '/includes/session.php', '<?php session_start();');
file_put_contents($fixture . '/includes/config.settings.php', '<?php $live_map_enabled=!in_array($scenario,["disabled","page-disabled"],true); $live_map_show_bots=$scenario!=="hide-bots";');
file_put_contents($fixture . '/includes/online_presence.php', '<?php function forever_presence_bot_sql($db){return ["sql"=>"0"]; }');
file_put_contents($fixture . '/languages/language.php', '<?php $langCode=$scenario==="page-fr"?"fr":"en";');
file_put_contents($fixture . '/includes/header.php', '<!doctype html><html><title><?= htmlspecialchars($page_title) ?></title><body>');
file_put_contents($fixture . '/includes/footer.php', '</body></html>');
file_put_contents($fixture . '/includes/forever.php', <<<'PHP'
<?php
class MapFixtureRows {
    function __construct(private array $rows) {}
    function fetch_all($mode) { return $this->rows; }
    function fetch_row() { return array_shift($this->rows); }
    function free() {}
}
class MapFixtureDB {
    public string $error='fixture database unavailable';
    function __construct(private bool $staff=false) {}
    function query($sql) {
        global $scenario;
        if (!str_starts_with($sql,'SELECT ')) throw new RuntimeException('Map attempted a database write');
        if ($this->staff) return $scenario==='staff-error' ? false : new MapFixtureRows([[99]]);
        if ($scenario==='query-error') return false;
        $base=['race'=>1,'class'=>1,'gender'=>0,'level'=>60,'map'=>0,'zone'=>12,'position_x'=>1.234,'position_y'=>-2.345];
        return new MapFixtureRows([
            $base+['guid'=>1,'account'=>10,'name'=>'Fixture hero','is_bot'=>0],
            $base+['guid'=>2,'account'=>99,'name'=>'Hidden staff','is_bot'=>0],
            $base+['guid'=>3,'account'=>11,'name'=>'Fixture bot','is_bot'=>1],
        ]);
    }
}
if (!defined('MYSQLI_ASSOC')) define('MYSQLI_ASSOC',1);
$auth_db=new MapFixtureDB(true);
function forever_public_realms() { global $scenario; return $scenario==='no-realms'?[]:[['id'=>7,'name'=>'Public test realm']]; }
function forever_char_db($id) { global $scenario; if ($id!==7) throw new RuntimeException('Private realm accessed'); return $scenario==='db-error'?null:new MapFixtureDB; }
function forever_race_name($id) { return 'Human'; }
function forever_class_name($id) { return 'Warrior'; }
function forever_race_icon_file($id,$gender) { return 'male/human.png'; }
function forever_is_alliance($id) { return true; }
function forever_is_horde($id) { return false; }
PHP);

$checks = 0;
function map_check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function map_fixture(string $scenario): array {
    global $fixture;
    $process = proc_open([PHP_BINARY, '-n', __FILE__, '--fixture', $fixture, $scenario],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || !preg_match('/STATUS:(\d+)$/', $error, $match)) {
        throw new RuntimeException($scenario . ': ' . $output . $error);
    }
    return [(int)$match[1], $output];
}
try {
    [$status, $body] = map_fixture('normal');
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    map_check($status === 200 && $data['realm']['id'] === 7, 'Only a public realm is accessed');
    map_check(array_column($data['players'], 'guid') === [1, 3], 'Staff positions are excluded');
    map_check(!str_contains($body, 'account') && !str_contains($body, 'Hidden staff'), 'Private account fields are omitted');
    map_check($data['players'][0]['x'] === 1.2 && $data['players'][0]['y'] === -2.3, 'Saved positions are serialized correctly');
    [$status, $body] = map_fixture('hide-bots');
    map_check(array_column(json_decode($body, true)['players'], 'guid') === [1], 'Server-side bot exclusion applies');
    foreach (['post' => 405, 'disabled' => 403, 'no-realms' => 404, 'db-error' => 503, 'query-error' => 503, 'staff-error' => 503] as $scenario => $expected) {
        [$status, $body] = map_fixture($scenario);
        map_check($status === $expected && !str_contains($body, 'players'), $scenario . ' returns no player locations');
    }
    foreach (['page-en' => 'Live World Map', 'page-fr' => 'Carte du monde en direct'] as $scenario => $label) {
        [$status, $body] = map_fixture($scenario);
        map_check(str_contains($body, $label) && str_contains($body, 'id="lm-boot"'), $scenario . ' renders its map boot data');
    }
    [$status, $body] = map_fixture('page-disabled');
    map_check(!str_contains($body, 'id="lm-boot"') && !str_contains($body, 'live-map.js'), 'Disabled page starts no live feed');
    echo "$checks live map page/API checks passed. No real database was accessed.\n";
} finally {
    foreach (['pages', 'includes', 'languages'] as $folder) {
        foreach (glob($fixture . '/' . $folder . '/*') as $path) unlink($path);
        rmdir($fixture . '/' . $folder);
    }
    rmdir($fixture);
}
