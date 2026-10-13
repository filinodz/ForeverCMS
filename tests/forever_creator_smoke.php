<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../www/includes/forever_creator.php';

mysqli_report(MYSQLI_REPORT_OFF);
$world = new mysqli('127.0.0.1', 'trinity', 'trinity', 'world', 3317);
$site = new mysqli('127.0.0.1', 'trinity', 'trinity', 'forever_site', 3317);
if ($world->connect_error || $site->connect_error) {
    fwrite(STDERR, "Database connection failed.\n"); exit(1);
}
$testId = 3999999;
forever_creator_ensure_schema($site);
if (forever_creator_item($world, $testId) || forever_creator_history_item($site, $testId)) {
    fwrite(STDERR, "Reserved smoke-test item ID is already in use.\n"); exit(1);
}

$payload = [
    'item_id'=>$testId, 'base_item_id'=>286977, 'name'=>'ForeverCreator Smoke Test',
    'description'=>'Temporary integration test', 'quality'=>4, 'item_level'=>80,
    'required_level'=>60, 'class_id'=>2, 'subclass_id'=>7, 'inventory_type'=>13,
    'bonding'=>1, 'material'=>1, 'sheathe_type'=>3, 'icon_file_data_id'=>135312,
    'stackable'=>1, 'max_count'=>1, 'buy_price'=>10000, 'sell_price'=>1000,
    'vendor_stack'=>1, 'allowable_class'=>-1, 'allowable_race1'=>-1, 'allowable_race2'=>-1,
    'required_skill'=>0, 'required_skill_rank'=>0, 'required_ability'=>0, 'item_set'=>0,
    'lock_id'=>0, 'bag_family'=>0, 'container_slots'=>0, 'socket_bonus'=>0,
    'delay'=>1900, 'damage_type'=>0, 'damage_variance'=>0,
    'preview_damage_min'=>82, 'preview_damage_max'=>153, 'preview_armor'=>0,
    'flags1'=>0, 'flags2'=>0, 'flags3'=>0, 'flags4'=>0, 'flags5'=>0,
    'socket_type1'=>2, 'socket_type2'=>4, 'socket_type3'=>8,
    'stats'=>[['type'=>4,'value'=>12,'socket'=>0],['type'=>7,'value'=>16,'socket'=>0]],
    'effects'=>[['trigger'=>1,'spell'=>133,'charges'=>0,'cooldown'=>-1,'category_cooldown'=>-1,'text'=>'Smoke-test effect']]
];

try {
    forever_creator_save_item($world, $site, $payload, 1);
    if (!forever_creator_item($world, $testId) || !forever_creator_history_item($site, $testId)) {
        throw new RuntimeException('Created item was not readable from both stores.');
    }
    forever_creator_delete_item($world, $site, $testId);
    if (forever_creator_item($world, $testId) || forever_creator_history_item($site, $testId)) {
        throw new RuntimeException('Smoke-test cleanup was incomplete.');
    }
    echo "ForeverCreator create/read/delete smoke test passed.\n";
} catch (Throwable $e) {
    try { if (forever_creator_history_item($site, $testId)) forever_creator_delete_item($world, $site, $testId); } catch (Throwable $ignored) {}
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
}
