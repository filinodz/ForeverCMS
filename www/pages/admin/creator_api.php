<?php
define('ALLOWED_ACCESS',true);
require_once __DIR__.'/../../includes/paths.php';
require_once $project_root.'includes/session.php';
require_once $project_root.'includes/creator_studio.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(!isset($_SESSION['user_id'])||!in_array($_SESSION['role']??'',['admin','moderator'],true)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Please sign in with an administrator account.']);exit;}
session_write_close();
try{
    creator_schema($world_db);$action=(string)($_GET['action']??'bootstrap');$kind=(string)($_GET['kind']??'item');$spec=creator_specs()[$kind]??null;
    if(!$spec)throw new InvalidArgumentException('Unknown tool');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $p=json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR);
        if(!hash_equals($_SESSION['csrf_token']??'',(string)($p['csrf_token']??'')))throw new RuntimeException('Invalid security token. Refresh the page.');
        if($action==='save'||$action==='export')$data=creator_apply($world_db,$p['payload']??[],(int)$_SESSION['user_id'],$action==='export');
        elseif($action==='delete'){creator_delete($world_db,(int)($p['id']??0),(int)($p['revision']??0),(int)$_SESSION['user_id']);$data=['deleted'=>true];}
        else throw new RuntimeException('Unknown operation');
    }elseif($action==='bootstrap'){
        $fields=[];$tables=array_merge([$spec['table']],array_keys($spec['companion']));foreach($tables as $table)$fields[$table]=creator_columns($world_db,$table);
        $runtime=$world_db->query('SELECT * FROM forever_creator_runtime WHERE id=1');
        $extraTables=['models'=>'creature_template_model','effects'=>'forever_creator_item_effect','objectives'=>'quest_objectives'];if(in_array($kind,['vendor','loot'],true))$extraTables['inventory']=$spec['table'];$extraFields=[];$extraDefaults=[];foreach($extraTables as $key=>$table){$extraFields[$key]=creator_columns($world_db,$table);$extraDefaults[$key]=creator_defaults($world_db,$table);}
        $data=['spec'=>$spec,'fields'=>$fields,'defaults'=>array_combine($tables,array_map(static fn($t)=>creator_defaults($world_db,$t),$tables)),'runtime'=>$runtime?$runtime->fetch_assoc():null,'destinations'=>creator_safe_destinations($world_db),'extra_fields'=>$extraFields,'extra_defaults'=>$extraDefaults];
    }elseif($action==='template')$data=creator_template($world_db,$kind,(int)($_GET['id']??0));
    elseif($action==='math')$data=creator_item_math($world_db,$_GET);
    elseif($action==='icon'){$id=(int)($_GET['id']??0);$r=creator_query($world_db,"SELECT name FROM armory_icon WHERE fileDataId=$id LIMIT 1")->fetch_assoc();$data=['name'=>$r['name']??'inv_misc_questionmark'];}
    elseif($action==='spell_names')$data=creator_spell_names($world_db,explode(',',(string)($_GET['ids']??'')));
    elseif($action==='history'){$result=creator_query($world_db,'SELECT id,kind,entry,name,revision,state,created_at,updated_at FROM forever_creator_history ORDER BY updated_at DESC LIMIT 200');$data=$result->fetch_all(MYSQLI_ASSOC);}
    elseif($action==='edit'){$data=creator_history($world_db,(int)($_GET['id']??0));if(!$data)throw new RuntimeException('Creation not found');}
    elseif($action==='search'){
        $q=trim((string)($_GET['q']??''));$id=ctype_digit($q)?(int)$q:0;$like='%'.$world_db->real_escape_string($q).'%';$quoted="'".$like."'";
        if($kind==='item')$sql="SELECT s.ID id,s.Display name,s.OverallQualityID quality,i.IconFileDataID icon,s.InventoryType slot FROM forever_creator_source_item_sparse s JOIN forever_creator_source_item i ON i.ID=s.ID WHERE s.ID=$id OR s.Display LIKE $quoted ORDER BY s.ID=$id DESC,s.ID LIMIT 40";
        elseif(in_array($kind,['creature','teleporter'],true))$sql="SELECT t.entry id,t.name,t.subname,MIN(m.CreatureDisplayID) display FROM creature_template t LEFT JOIN creature_template_model m ON m.CreatureID=t.entry WHERE t.entry=$id OR t.name LIKE $quoted GROUP BY t.entry,t.name,t.subname ORDER BY t.entry=$id DESC,t.entry LIMIT 40";
        elseif($kind==='quest')$sql="SELECT ID id,LogTitle name FROM quest_template WHERE ID=$id OR LogTitle LIKE $quoted ORDER BY ID=$id DESC,ID LIMIT 40";
        else $sql="SELECT id,name,map FROM game_tele WHERE map IN(0,1) AND (id=$id OR name LIKE $quoted) LIMIT 40";
        $data=creator_query($world_db,$sql)->fetch_all(MYSQLI_ASSOC);
    }elseif($action==='appearances'){
        $q=trim((string)($_GET['q']??''));$id=ctype_digit($q)?(int)$q:0;$like="'%".$world_db->real_escape_string($q)."%'";
        if(($_GET['type']??'') === 'npc')$sql="SELECT t.entry item_id,t.name,MIN(m.CreatureDisplayID) display_id FROM creature_template t JOIN creature_template_model m ON m.CreatureID=t.entry WHERE t.entry=$id OR m.CreatureDisplayID=$id OR t.name LIKE $like GROUP BY t.entry,t.name ORDER BY t.entry LIMIT 40";
        else $sql="SELECT m.ItemID item_id,s.Display name,s.OverallQualityID quality,s.InventoryType slot,a.ID appearance_id,a.ItemDisplayInfoID display_id,a.DefaultIconFileDataID icon FROM forever_creator_source_item_modified_appearance m JOIN forever_creator_source_item_appearance a ON a.ID=m.ItemAppearanceID JOIN forever_creator_source_item_sparse s ON s.ID=m.ItemID WHERE m.ItemAppearanceModifierID=0 AND (m.ItemID=$id OR a.ItemDisplayInfoID=$id OR s.Display LIKE $like) ORDER BY m.ItemID LIMIT 40";
        $data=creator_query($world_db,$sql)->fetch_all(MYSQLI_ASSOC);
    }else throw new RuntimeException('Unknown request');
    echo json_encode(['ok'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);}
