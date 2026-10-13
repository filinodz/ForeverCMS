<?php
// Integration test: refuses occupied test IDs, deletes only its own tracked creations.
define('ALLOWED_ACCESS',true);
require_once __DIR__.'/../www/includes/creator_studio.php';
mysqli_report(MYSQLI_REPORT_OFF);
$db=new mysqli('127.0.0.1','trinity','trinity','world',3317);$db->set_charset('utf8mb4');
if($db->connect_error)exit("Database unavailable\n");
creator_schema($db);$created=[];$payloads=[];$failed=false;
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function clone_payload($db,string $kind,int $base,int $id): array {
    $t=creator_template($db,$kind,$base);$p=['kind'=>$kind,'base_id'=>$base,'entry'=>$id,'tables'=>$t['tables'],'models'=>$t['extra']['models']??[],'effects'=>$t['extra']['effects']??[],'objectives'=>$t['extra']['objectives']??[]];
    if($kind==='item'){$p['tables']['forever_creator_item_sparse']['Display']='ForeverCreator integration test';$p['appearance_id']=$t['extra']['appearance']['appearance_id'];}
    elseif($kind==='quest')$p['tables']['quest_template']['LogTitle']='ForeverCreator integration test';
    else $p['tables']['creature_template']['name']='ForeverCreator integration test';
    return $p;
}
try {
    $specs=creator_specs();$ids=['item'=>903987,'creature'=>993987,'quest'=>993987,'teleporter'=>993988,'vendor'=>993987,'loot'=>993989,'spawn'=>9993987,'game_tele'=>993990];
    $kinds=in_array('--world-only',$argv,true)?['creature','quest','teleporter','vendor','loot','spawn','game_tele']:array_keys($specs);
    foreach($kinds as $kind){$s=$specs[$kind];$id=$ids[$kind];check(!creator_one($db,$s['table'],$s['key'],$id),'Test ID already occupied: '.$kind);check(!creator_query($db,"SELECT 1 FROM forever_creator_history WHERE kind='$kind' AND entry=$id")->fetch_row(),'Test history already occupied');}
    $destinations=creator_safe_destinations($db);check(count($destinations)>0,'No verified destinations');$tele=$destinations[0];
    $hotfixBefore=$db->query('SELECT COUNT(*) n FROM hotfixes.item')->fetch_assoc()['n'];
    foreach($kinds as $kind){$s=$specs[$kind];$id=$ids[$kind];
        if($s['default'])$p=clone_payload($db,$kind,$s['default'],$id);else $p=['kind'=>$kind,'entry'=>$id,'base_id'=>0,'tables'=>[$s['table']=>creator_defaults($db,$s['table'])]];
        if($kind==='teleporter')$p['destinations']=[['tele_id'=>$tele['id'],'label'=>'Verified test destination','cost'=>100,'min_level'=>10]];
        if($kind==='vendor'){$p['tables']['npc_vendor']=array_replace($p['tables']['npc_vendor'],['item'=>19019,'type'=>1]);$p['inventory']=[array_replace(creator_defaults($db,'npc_vendor'),['item'=>17182,'type'=>1,'slot'=>1])];}
        if($kind==='loot')$p['tables']['creature_loot_template']=array_replace($p['tables']['creature_loot_template'],['Item'=>19019,'Chance'=>100,'MinCount'=>1,'MaxCount'=>1]);
        if($kind==='spawn')$p['tables']['creature']=array_replace($p['tables']['creature'],['id'=>$ids['creature'],'map'=>$tele['map'],'position_x'=>$tele['position_x'],'position_y'=>$tele['position_y'],'position_z'=>$tele['position_z'],'orientation'=>$tele['orientation'],'spawnDifficulties'=>'0']);
        if($kind==='game_tele')$p['tables']['game_tele']=array_replace($p['tables']['game_tele'],['name'=>'ForeverCreator integration test','map'=>$tele['map'],'position_x'=>$tele['position_x'],'position_y'=>$tele['position_y'],'position_z'=>$tele['position_z'],'orientation'=>$tele['orientation']]);
        $export=creator_apply($db,$p,1,true);check(str_contains($export['sql'],'START TRANSACTION'),'Export is not transactional');check(!creator_one($db,$s['table'],$s['key'],$id),'Export mutated database');
        $r=creator_apply($db,$p,1);$created[$kind]=$r;check((bool)creator_one($db,$s['table'],$s['key'],$id),'Saved record missing');$p['history_id']=$r['id'];$p['revision']=$r['revision'];
        $r=creator_apply($db,$p,1);$created[$kind]=$r;check($r['revision']===2,'Update revision incorrect');
        try{creator_apply($db,$p,1);throw new RuntimeException('Stale revision accepted');}catch(Throwable $e){check(str_contains($e->getMessage(),'another window'),'Unexpected stale-update error: '.$e->getMessage());}
        $payloads[$kind]=$p;echo "$kind: export / create / read / update / revision guard passed\n";
    }
    check($hotfixBefore===$db->query('SELECT COUNT(*) n FROM hotfixes.item')->fetch_assoc()['n'],'Unexpected hotfixes mutation');
    if(in_array('item',$kinds,true)){
        $collision=clone_payload($db,'item',17182,19019);
        try{creator_apply($db,$collision,1);throw new RuntimeException('Original item overwritten');}catch(Throwable $e){check(str_contains($e->getMessage(),'existing item')||str_contains($e->getMessage(),'ID collision'),'Original-ID guard failed: '.$e->getMessage());}
        $modelItems=forever_model_test($db);check(count($modelItems)===6,'Actual character equipment appearances missing');
    }
    try{creator_position(['map'=>530,'position_x'=>0,'position_y'=>0,'position_z'=>0]);throw new RuntimeException('Outland accepted');}catch(Throwable $e){check(str_contains($e->getMessage(),'Classic'),'Wrong position validation error');}
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n");}
finally {
    foreach(array_reverse($created,true) as $kind=>$r){try{creator_delete($db,$r['id'],$r['revision'],1);check(!creator_one($db,$specs[$kind]['table'],$specs[$kind]['key'],$r['entry']),"Cleanup failed: $kind");creator_query($db,'DELETE FROM forever_creator_revisions WHERE creation_id='.(int)$r['id']);creator_query($db,'DELETE FROM forever_creator_history WHERE id='.(int)$r['id']);echo "$kind: owned-record deletion passed\n";}catch(Throwable $e){$failed=true;fwrite(STDERR,'CLEANUP: '.$e->getMessage()."\n");}}
}
exit($failed?1:0);
function forever_model_test($db): array {require_once __DIR__.'/../www/includes/forever_models.php';return forever_model_equipment($db,[0=>12064,3=>45,4=>2586,6=>44,7=>11508,15=>17182]);}
