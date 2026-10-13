<?php
// Manual server integration test: seed -> restart Normal worldserver -> verify -> clean -> restart.
define('ALLOWED_ACCESS',true);require __DIR__.'/../www/includes/creator_studio.php';
mysqli_report(MYSQLI_REPORT_OFF);$db=new mysqli('127.0.0.1','trinity','trinity','world',3317);$db->set_charset('utf8mb4');creator_schema($db);
$id=903988;$name='ForeverCreatorBridgeProbe';$mode=$argv[1]??'';
$history=creator_query($db,"SELECT id FROM forever_creator_history WHERE kind='item' AND entry=$id")->fetch_assoc();
if($mode==='seed'){
    if($history||creator_one($db,'armory_item','id',$id)||creator_one($db,'forever_creator_item','ID',$id))throw new RuntimeException('Probe ID already occupied');
    $t=creator_template($db,'item',19019);$p=['kind'=>'item','entry'=>$id,'base_id'=>19019,'tables'=>$t['tables'],'effects'=>$t['extra']['effects'],'appearance_id'=>$t['extra']['appearance']['appearance_id']];$p['tables']['forever_creator_item_sparse']['Display']=$name;$r=creator_apply($db,$p,1);echo "Seeded probe #$id; restart Normal worldserver.\n";
}elseif($mode==='verify'){
    if(!$history)throw new RuntimeException('Seed the probe first');$root=getenv('FOREVER_REPACK_PATH');if(!$root||!is_file($root.'/cms/www/includes/ra_client.php'))throw new RuntimeException('Set FOREVER_REPACK_PATH to the repack folder');require $root.'/cms/www/includes/ra_client.php';
    $r=forever_ra_command(3443,'lookup item '.$name,15);if(!$r['success']||!str_contains($r['output'],(string)$id))throw new RuntimeException('The running server did not find the probe: '.$r['error'].' '.$r['output']);echo "Running worldserver recognizes custom world item #$id.\n";
    $r=forever_ra_command(3443,'armory export',30);if(!$r['success'])throw new RuntimeException('Armory export failed: '.$r['error']);$row=creator_one($db,'armory_item','id',$id);if(!$row||$row['name']!==$name||(int)$row['allowableClass']!==-1||(int)$row['inventoryType']!==13||(float)$row['dmgMin']<=0||!str_contains($row['effects'],'21992'))throw new RuntimeException('Loaded custom item differs from the saved template');echo "Server-calculated name / class mask / slot / damage / spell effect verified.\n";
}elseif($mode==='clean'){
    if(!$history)throw new RuntimeException('No probe history to clean');$h=creator_history($db,(int)$history['id']);if($h['name']!==$name)throw new RuntimeException('Probe name mismatch; refusing deletion');creator_delete($db,(int)$h['id'],(int)$h['revision'],1);creator_query($db,'DELETE FROM forever_creator_revisions WHERE creation_id='.(int)$h['id']);creator_query($db,'DELETE FROM forever_creator_history WHERE id='.(int)$h['id']);echo "Probe removed. Restart Normal worldserver to unload it.\n";
}else throw new RuntimeException('Use seed, verify or clean');
