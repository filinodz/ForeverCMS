<?php
if(!defined('ALLOWED_ACCESS'))exit('Forbidden');

function creator_schema($db): void
{
    creator_query($db,"CREATE TABLE IF NOT EXISTS forever_creator_history (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,kind VARCHAR(20) NOT NULL,entry BIGINT UNSIGNED NOT NULL,name VARCHAR(255) NOT NULL,payload_json LONGTEXT NOT NULL,owned_json LONGTEXT NOT NULL,revision INT NOT NULL DEFAULT 1,state VARCHAR(16) NOT NULL DEFAULT 'active',created_by INT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY creation(kind,entry)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    creator_query($db,"CREATE TABLE IF NOT EXISTS forever_creator_revisions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,creation_id INT UNSIGNED NOT NULL,payload_json LONGTEXT NOT NULL,action VARCHAR(16) NOT NULL,author INT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX revisions(creation_id,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function creator_query($db,string $sql)
{
    $result=$db->query($sql);if($result===false)throw new RuntimeException('Database operation failed: '.$db->error);return $result;
}
function creator_item_math($db,array $p): array
{
    $level=(int)($p['level']??0);$quality=(int)($p['quality']??1);$class=(int)($p['class']??0);$sub=(int)($p['subclass']??0);$slot=(int)($p['slot']??0);$q=$quality===7?3:$quality;
    $result=['ready'=>false,'budget'=>0,'armor'=>0,'dps'=>0,'socket_cost'=>0,'damage_table_available'=>true];
    $random=creator_one($db,'forever_creator_source_rand_prop_points','ID',$level);
    if(!$random)throw new RuntimeException('Item calculation library is not ready, or this item level is unavailable. Start the updated worldserver once.');
    $result['ready']=true;$index=in_array($slot,[1,4,5,7,15,17,20,25],true)?0:($slot===26?($sub===19?3:0):(in_array($slot,[13,21,22],true)?3:(in_array($slot,[3,6,8,10,12],true)?1:(in_array($slot,[2,9,11,14,16,23],true)?2:($slot===28?4:-1)))));
    $prefix=$quality===2?'GoodF':(in_array($quality,[3,7],true)?'SuperiorF':(in_array($quality,[4,5,6],true)?'EpicF':null));
    if($prefix!==null&&$index>=0)$result['budget']=(float)($random[$prefix.($index+1)]??0);
    if($class===4&&$q>=0&&$q<=6){
        if($sub===6){$shield=creator_one($db,'forever_creator_source_item_armor_shield','ID',$level);$result['armor']=(int)floor((float)($shield['Quality'.($q+1)]??0)+.5);}
        elseif($sub>=1&&$sub<=4){$aq=creator_one($db,'forever_creator_source_item_armor_quality','ID',$level);$total=creator_one($db,'forever_creator_source_item_armor_total','ID',$level);$location=creator_one($db,'forever_creator_source_armor_location','ID',$slot===20?5:$slot);$name=[1=>'Cloth',2=>'Leather',3=>'Mail',4=>'Plate'][$sub];$modifier=[1=>'Clothmodifier',2=>'Leathermodifier',3=>'Chainmodifier',4=>'Platemodifier'][$sub];$result['armor']=(int)floor((float)($aq['Qualitymod'.($q+1)]??0)*(float)($total[$name]??0)*(float)($location[$modifier]??0)+.5);}
    }
    if($class===2&&$q>=0&&$q<=6){$caster=!empty($p['caster']);$table=null;
        if($slot===24)$table='ammo';elseif($slot===17)$table=$caster?'two_hand_caster':'two_hand';elseif(in_array($slot,[13,21,22],true))$table=$caster?'one_hand_caster':'one_hand';elseif(in_array($slot,[15,25,26],true)){if($sub===19)$table='one_hand_caster';elseif(in_array($sub,[2,3,18],true))$table=$caster?'two_hand_caster':'two_hand';}
        if($table){$damage=creator_one($db,'forever_creator_source_item_damage_'.$table,'ID',$level);$result['damage_table_available']=(bool)$damage;$result['dps']=(float)($damage['Quality'.($q+1)]??0);}
    }
    $repack=dirname(__DIR__,3);foreach([$repack.'/data/gt/ItemSocketCostPerLevel.txt',dirname($repack).'/data/gt/ItemSocketCostPerLevel.txt'] as $path)if(is_file($path)){$handle=fopen($path,'r');while(($line=fgets($handle))!==false){$parts=explode("\t",trim($line));if((int)$parts[0]===$level){$result['socket_cost']=(float)($parts[1]??0);break;}}fclose($handle);break;}
    return $result;
}
function creator_spell_names($db,array $ids): array
{
    $ids=array_slice(array_values(array_unique(array_filter(array_map('intval',$ids)))),0,20);if(!$ids)return [];
    $where=implode(' OR ',array_map(static fn($id)=>"effects LIKE '%:$id:%'",$ids));$result=creator_query($db,"SELECT effects FROM armory_item WHERE $where LIMIT 200");$names=[];
    while($row=$result->fetch_assoc())foreach(explode('|',$row['effects']) as $effect){$parts=explode(':',$effect,3);if(count($parts)===3&&in_array((int)$parts[1],$ids,true)&&$parts[2]!=='')$names[(int)$parts[1]]=$parts[2];}
    return $names;
}
function creator_specs(): array
{
    return [
        'item'=>['label'=>'Items','icon'=>'fa-khanda','table'=>'forever_creator_item','key'=>'ID','name'=>'Display','source'=>'forever_creator_source_item','companion'=>['forever_creator_item_sparse'=>'ID','forever_creator_item_search_name'=>'ID'],'default'=>19019],
        'creature'=>['label'=>'Creatures & NPCs','icon'=>'fa-dragon','table'=>'creature_template','key'=>'entry','name'=>'name','companion'=>['creature_template_difficulty'=>'Entry','creature_classic_stats'=>'entry','creature_classic_level'=>'entry','creature_template_addon'=>'entry'],'default'=>352],
        'quest'=>['label'=>'Quests','icon'=>'fa-scroll','table'=>'quest_template','key'=>'ID','name'=>'LogTitle','companion'=>['quest_template_addon'=>'ID','quest_classic_level'=>'ID','quest_classic_money'=>'ID','quest_template_classic_level'=>'ID'],'default'=>783],
        'teleporter'=>['label'=>'Teleporter builder','icon'=>'fa-compass','table'=>'creature_template','key'=>'entry','name'=>'name','companion'=>[],'default'=>352],
        'vendor'=>['label'=>'Vendor inventory','icon'=>'fa-coins','table'=>'npc_vendor','key'=>'entry','name'=>'item','companion'=>[],'default'=>0],
        'loot'=>['label'=>'Loot tables','icon'=>'fa-gem','table'=>'creature_loot_template','key'=>'Entry','name'=>'Item','companion'=>[],'default'=>0],
        'spawn'=>['label'=>'NPC placement','icon'=>'fa-map-marker-alt','table'=>'creature','key'=>'guid','name'=>'id','companion'=>[],'default'=>0],
        'game_tele'=>['label'=>'Named destinations','icon'=>'fa-location-arrow','table'=>'game_tele','key'=>'id','name'=>'name','companion'=>[],'default'=>0],
    ];
}
function creator_columns($db,string $table): array
{
    $stmt=$db->prepare('SELECT COLUMN_NAME AS name,COLUMN_TYPE AS type,DATA_TYPE AS data_type,IS_NULLABLE AS nullable,COLUMN_DEFAULT AS def,COLUMN_KEY AS key_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $stmt->bind_param('s',$table);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
function creator_defaults($db,string $table): array
{
    $row=[];foreach(creator_columns($db,$table) as $field)$row[$field['name']]=$field['def']??($field['nullable']==='YES'?null:(str_contains($field['type'],'text')||str_contains($field['type'],'char')?'':0));return $row;
}
function creator_one($db,string $table,string $key,int $id): ?array
{
    if(!preg_match('/^[a-z0-9_]+$/i',$table.$key))throw new RuntimeException('Invalid identifier');
    $result=creator_query($db,"SELECT * FROM `$table` WHERE `$key`=$id LIMIT 1");return $result->fetch_assoc()?:null;
}
function creator_history($db,int $id): ?array
{
    $row=creator_one($db,'forever_creator_history','id',$id);if($row){$row['payload']=json_decode($row['payload_json'],true,64,JSON_THROW_ON_ERROR);$row['owned']=json_decode($row['owned_json'],true,64,JSON_THROW_ON_ERROR);}return $row;
}
function creator_template($db,string $kind,int $id): array
{
    $spec=creator_specs()[$kind]??null;if(!$spec)throw new InvalidArgumentException('Unknown creation tool');
    $tables=[];$source=$spec['source']??$spec['table'];$row=creator_one($db,$source,$spec['key'],$id);
    if(!$row)throw new InvalidArgumentException($kind==='item'?'This item is not in the world template library. Start the updated worldserver once, then search the library.':'Template not found in world.');
    $tables[$spec['table']]=$row;
    foreach($spec['companion'] as $table=>$key){$source=$kind==='item'?str_replace('forever_creator_','forever_creator_source_',$table):$table;$tables[$table]=creator_one($db,$source,$key,$id)??creator_defaults($db,$table);}
    $extra=[];
    if($kind==='item'){
        $appearance=creator_query($db,"SELECT m.ID AS modified_id,m.ItemAppearanceID,a.ID AS appearance_id,a.ItemDisplayInfoID,a.DefaultIconFileDataID FROM forever_creator_source_item_modified_appearance m JOIN forever_creator_source_item_appearance a ON a.ID=m.ItemAppearanceID WHERE m.ItemID=$id AND m.ItemAppearanceModifierID=0 ORDER BY m.OrderIndex LIMIT 1")->fetch_assoc();
        $extra['appearance']=$appearance?:null;
        $extra['armory']=creator_one($db,'armory_item','id',$id);
        $extra['effects']=creator_query($db,"SELECT e.* FROM forever_creator_source_item_x_item_effect x JOIN forever_creator_source_item_effect e ON e.ID=x.ItemEffectID WHERE x.ItemID=$id ORDER BY e.LegacySlotIndex LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    }elseif(in_array($kind,['creature','teleporter'],true)){
        $extra['models']=creator_query($db,"SELECT * FROM creature_template_model WHERE CreatureID=$id ORDER BY Idx")->fetch_all(MYSQLI_ASSOC);
    }elseif($kind==='quest')$extra['objectives']=creator_query($db,"SELECT * FROM quest_objectives WHERE QuestID=$id ORDER BY `Order`")->fetch_all(MYSQLI_ASSOC);
    return ['tables'=>$tables,'extra'=>$extra,'base_id'=>$id];
}
function creator_safe_destinations($db): array
{
    $names=['Stormwind','Ironforge','Darnassus','Orgrimmar','ThunderBluff','Undercity','BootyBay','Ratchet','Gadgetzan','Everlook','Azshara','Felwood','Feralas','UnGoroCrater','Winterspring','SearingGorge','BurningSteppes','BlastedLands','WesternPlaguelands','EasternPlaguelands','Silithus','TheDeadmines','TheWailingCaverns','ShadowFangKeep','TheStockade','Gnomeregan','RazorfenKraul','ScarletMonastery','RazorfenDowns','Uldaman','ZulFarrak','Maraudon','SunkenTemple','BlackrockDepths','LowerBlackrockSpire','UpperBlackrockSpire','DireMaul','Scholomance','Stratholme','MoltenCore','BlackwingLair','OnyxiasLair','ZulGurub','RuinsOfAhnQiraj','TempleOfAhnQiraj','GMIsland'];
    $sql="SELECT * FROM game_tele WHERE map IN(0,1) AND name IN('".implode("','",$names)."') ORDER BY name";
    $rows=creator_query($db,$sql)->fetch_all(MYSQLI_ASSOC);return array_values(array_filter($rows,static function($r){try{creator_position($r);return true;}catch(Throwable $e){return false;}}));
}
function creator_position(array $row): void
{
    $map=(int)($row['map']??-1);$x=(float)($row['position_x']??0);$y=(float)($row['position_y']??0);$z=(float)($row['position_z']??0);
    if(!in_array($map,[0,1],true)||!is_finite($x)||!is_finite($y)||!is_finite($z)||abs($z)>10000)throw new InvalidArgumentException('Choose a verified Classic destination on map 0 or 1.');
    $tile=sprintf('%04d_%02d_%02d.map',$map,(int)floor(32-$x/(533.333333333)),(int)floor(32-$y/(533.333333333)));
    $repack=dirname(__DIR__,3);$candidates=[$repack.'/data/maps/'.$tile,dirname($repack).'/data/maps/'.$tile];
    foreach($candidates as $path)if(is_file($path))return;
    throw new InvalidArgumentException('No terrain file is installed for this position. Select a destination available in this repack.');
}
function creator_validate_row($db,string $table,array $row): array
{
    $fields=creator_columns($db,$table);if(!$fields)throw new RuntimeException('Table '.$table.' is unavailable');$clean=[];
    foreach($fields as $f){$name=$f['name'];if(!array_key_exists($name,$row))continue;$v=$row[$name];
        if($v===null){if($f['nullable']!=='YES')throw new InvalidArgumentException($name.' cannot be empty');$clean[$name]=null;continue;}
        if(in_array($f['data_type'],['tinyint','smallint','mediumint','int','bigint','float','double','decimal'],true)){
            if(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException($name.' must be a finite number');
            $bits=['tinyint'=>8,'smallint'=>16,'mediumint'=>24,'int'=>32,'bigint'=>64];if(isset($bits[$f['data_type']])){$b=$bits[$f['data_type']];$unsigned=str_contains($f['type'],'unsigned');$min=$unsigned?0:-pow(2,$b-1);$max=$unsigned?pow(2,$b)-1:pow(2,$b-1)-1;if((float)$v<$min||(float)$v>$max||floor((float)$v)!=(float)$v)throw new InvalidArgumentException($name.' is out of range');$v=(string)$v;}
        }else{if(!is_scalar($v))throw new InvalidArgumentException($name.' must be text');$v=(string)$v;if(preg_match('/varchar\((\d+)\)/',$f['type'],$m)&&mb_strlen($v)>(int)$m[1])throw new InvalidArgumentException($name.' is too long');}
        $clean[$name]=$v;
    }return $clean;
}
function creator_sql_value($db,$v): string{return $v===null?'NULL':"'".$db->real_escape_string((string)$v)."'";}
function creator_insert_sql($db,string $table,array $row): string
{
    $row=creator_validate_row($db,$table,$row);$columns='`'.implode('`,`',array_keys($row)).'`';return "INSERT INTO `$table` ($columns) VALUES (".implode(',',array_map(static fn($v)=>creator_sql_value($db,$v),$row)).')';
}
function creator_owned($db,string $table,array $row): array
{
    if($table==='creature_loot_template')return ['table'=>$table,'keys'=>array_intersect_key($row,array_flip(['Entry','ItemType','Item','GroupId']))];
    $where=[];foreach(creator_columns($db,$table) as $f)if($f['key_type']==='PRI'&&array_key_exists($f['name'],$row))$where[$f['name']]=$row[$f['name']];if(!$where)throw new RuntimeException('A primary key is required for '.$table);return ['table'=>$table,'keys'=>$where];
}
function creator_where($db,array $owned): string
{
    $terms=[];foreach($owned['keys'] as $key=>$value)$terms[]='`'.$key.'`='.creator_sql_value($db,$value);return implode(' AND ',$terms);
}
function creator_next_id($db,string $table,string $key): int
{
    $id=(int)creator_query($db,"SELECT COALESCE(MAX(`$key`),0)+1 n FROM `$table`")->fetch_assoc()['n'];
    if(str_starts_with($table,'forever_creator_')){
        $source=str_replace('forever_creator_','forever_creator_source_',$table);
        if(creator_columns($db,$source))$id=max($id,(int)creator_query($db,"SELECT COALESCE(MAX(`$key`),0)+1 n FROM `$source`")->fetch_assoc()['n']);
    }
    // Some existing core-only records use very large internal effect IDs.
    // Allocate in a verified free range supported by the world authoring bridge
    // instead of blindly copying MAX(source)+1 above the bridge's safety limit.
    if(str_starts_with($table,'forever_creator_')&&$id>2000000){$id=900000;$source=str_replace('forever_creator_','forever_creator_source_',$table);while(creator_one($db,$table,$key,$id)||creator_one($db,$source,$key,$id)){if(++$id>2000000)throw new RuntimeException('No free internal ID is available');}}
    return $id;
}
function creator_build($db,array $p): array
{
    $kind=$p['kind']??'';$spec=creator_specs()[$kind]??null;if(!$spec)throw new InvalidArgumentException('Unknown tool');
    $id=(int)($p['entry']??0);if($id<1||($kind==='item'&&$id>2000000))throw new InvalidArgumentException('Choose a valid positive ID (custom item IDs: up to 2000000).');
    $rows=[];$add=static function($table,$row)use(&$rows){$rows[]=['table'=>$table,'row'=>$row];};$tables=$p['tables']??[];$base=(int)($p['base_id']??0);
    if(in_array($kind,['item','creature','quest','teleporter'],true)){
        if(!$base||$base===$id)throw new InvalidArgumentException('Load a template with a different ID first.');
        $template=creator_template($db,$kind,$base);
        foreach($template['tables'] as $table=>$row){$key=$table===$spec['table']?$spec['key']:$spec['companion'][$table];$row=array_replace($row,$tables[$table]??[]);$row[$key]=$id;if(isset($row['VerifiedBuild']))$row['VerifiedBuild']=0;if($kind!=='item'&&str_contains($table,'addon')){$row['PathId']=0;}
            if(in_array($kind,['creature','teleporter'],true)&&$table==='creature_template'){$row['AIName']=$kind==='teleporter'?'SmartAI':'';$row['ScriptName']='';}
            $add($table,$row);
        }
        if(in_array($kind,['creature','teleporter'],true)){
            $models=$p['models']??$template['extra']['models'];if(!$models)throw new InvalidArgumentException('Choose at least one NPC display.');
            foreach($models as $i=>$model){$r=creator_defaults($db,'creature_template_model');$r=array_replace($r,$model);$r['CreatureID']=$id;$r['Idx']=$i;$r['VerifiedBuild']=0;$display=(int)$r['CreatureDisplayID'];if($display<=0||!creator_query($db,"SELECT 1 FROM creature_template_model WHERE CreatureDisplayID=$display LIMIT 1")->fetch_row())throw new InvalidArgumentException('Choose an NPC display that exists in the world library');$add('creature_template_model',$r);}
        }
        if($kind==='item'){
            $main=$rows[0]['row'];$sparse=$rows[1]['row'];$calculation=creator_item_math($db,['level'=>$sparse['ItemLevel'],'quality'=>$sparse['OverallQualityID'],'class'=>$main['ClassID'],'subclass'=>$main['SubclassID'],'slot'=>$sparse['InventoryType'],'caster'=>((int)($sparse['Flags2']??0)&512)!==0]);
            if(!$calculation['damage_table_available'])throw new InvalidArgumentException('No weapon damage data exists for this item level.');
            if((int)$sparse['OverallQualityID']>7||(int)$main['ClassID']>19||(int)$sparse['InventoryType']>28||(int)$sparse['ItemDelay']<0||(float)$sparse['DmgVariance']<0||(float)$sparse['DmgVariance']>2)throw new InvalidArgumentException('Invalid item quality, class, slot, speed or damage variance.');
            // The two client-facing name/slot records must agree with the complete sparse definition.
            $rows[0]['row']['InventoryType']=$sparse['InventoryType'];foreach($rows[2]['row'] as $field=>$value)if(array_key_exists($field,$sparse))$rows[2]['row'][$field]=$sparse[$field];
            $appearanceId=(int)($p['appearance_id']??($template['extra']['appearance']['appearance_id']??0));$displayId=(int)($p['display_id']??($template['extra']['appearance']['ItemDisplayInfoID']??0));
            if($appearanceId){$appearance=creator_one($db,'forever_creator_source_item_appearance','ID',$appearanceId);if(!$appearance)throw new InvalidArgumentException('Appearance not found in the world library');}
            elseif($displayId>0){$appearance=creator_query($db,"SELECT * FROM forever_creator_source_item_appearance WHERE ItemDisplayInfoID=$displayId LIMIT 1")->fetch_assoc();if(!$appearance)throw new InvalidArgumentException('Display ID is not present in the server appearance library. Select a verified appearance.');$appearanceId=(int)$appearance['ID'];}
            if($appearanceId){$r=creator_defaults($db,'forever_creator_item_modified_appearance');$r['ID']=creator_next_id($db,'forever_creator_item_modified_appearance','ID');$r['ItemID']=$id;$r['ItemAppearanceID']=$appearanceId;$add('forever_creator_item_modified_appearance',$r);}
            $effects=$p['effects']??$template['extra']['effects'];$eid=creator_next_id($db,'forever_creator_item_effect','ID');$lid=creator_next_id($db,'forever_creator_item_x_item_effect','ID');foreach(array_slice($effects,0,5) as $i=>$effect){if(!(int)($effect['SpellID']??0))continue;$r=array_replace(creator_defaults($db,'forever_creator_item_effect'),$effect);$r['ID']=$eid++;$r['LegacySlotIndex']=$i;$r['VerifiedBuild']=0;$add('forever_creator_item_effect',$r);$link=creator_defaults($db,'forever_creator_item_x_item_effect');$link['ID']=$lid++;$link['ItemID']=$id;$link['ItemEffectID']=$r['ID'];$add('forever_creator_item_x_item_effect',$link);}
            // Keep the website index in the same transaction as the new world
            // records, using the actual core formulas (not arbitrary preview values).
            $index=creator_defaults($db,'armory_item');$index=array_replace($index,['id'=>$id,'name'=>$sparse['Display'],'quality'=>$sparse['OverallQualityID'],'itemLevel'=>$sparse['ItemLevel'],'requiredLevel'=>$sparse['RequiredLevel'],'class'=>$main['ClassID'],'subclass'=>$main['SubclassID'],'inventoryType'=>$sparse['InventoryType'],'bonding'=>$sparse['Bonding'],'armor'=>$calculation['armor'],'delay'=>$sparse['ItemDelay'],'description'=>$sparse['Description'],'iconFileDataId'=>$main['IconFileDataID'],'sellPrice'=>$sparse['SellPrice'],'allowableClass'=>$sparse['AllowableClass'],'itemSet'=>$sparse['ItemSet'],'requiredSkill'=>$sparse['RequiredSkill'],'requiredSkillRank'=>$sparse['RequiredSkillRank'],'flags'=>$sparse['Flags1']]);
            $avg=$calculation['dps']*(float)$sparse['ItemDelay']/1000;$variance=(float)$sparse['DmgVariance'];$index['dmgMin']=(1-.5*$variance)*$avg;$index['dmgMax']=floor($avg*(1+.5*$variance)+.5);$values=[];
            for($i=1;$i<=10;++$i){$type=(int)$sparse['StatModifierBonusStat'.$i];$value=(int)round((float)$sparse['StatPercentEditor'.$i]*$calculation['budget']*.0001-(float)$sparse['StatPercentageOfSocket'.$i]*$calculation['socket_cost']);if($type>=0&&$value)$values[]=$type.':'.$value;}$index['stats']=implode(',',$values);
            $names=creator_spell_names($db,array_column($effects,'SpellID'));$index['effects']=implode('|',array_map(static fn($e)=>(int)$e['TriggerType'].':'.(int)$e['SpellID'].':'.str_replace(['|',':'],'',$names[(int)$e['SpellID']]??''),array_filter(array_slice($effects,0,5),static fn($e)=>(int)($e['SpellID']??0)>0)));$add('armory_item',$index);
        }
        if($kind==='quest'){
            $oid=creator_next_id($db,'quest_objectives','ID');foreach(array_slice($p['objectives']??[],0,20) as $i=>$objective){$r=array_replace(creator_defaults($db,'quest_objectives'),$objective);$r['ID']=$oid++;$r['QuestID']=$id;$r['Order']=$i;$r['StorageIndex']=$i;$r['VerifiedBuild']=0;$type=(int)$r['Type'];$object=(int)$r['ObjectID'];if($type===0&&!creator_one($db,'creature_template','entry',$object))throw new InvalidArgumentException('Objective creature #'.$object.' does not exist');if($type===1&&!creator_one($db,'armory_item','id',$object))throw new InvalidArgumentException('Objective item #'.$object.' does not exist');$add('quest_objectives',$r);}
            foreach(['giver'=>'creature_queststarter','ender'=>'creature_questender'] as $field=>$table){$npc=(int)($p[$field]??0);if($npc){if(!creator_one($db,'creature_template','entry',$npc))throw new InvalidArgumentException('Quest NPC #'.$npc.' does not exist');$add($table,['id'=>$npc,'quest'=>$id,'VerifiedBuild'=>0]);}}
            $quest=$rows[0]['row'];foreach($quest as $field=>$value)if(preg_match('/^Reward(?:Choice)?Item(?:ID)?[1-6]$/',$field)&&(int)$value&&!creator_one($db,'armory_item','id',(int)$value))throw new InvalidArgumentException($field.' references an unknown item');
        }
        if($kind==='teleporter'){
            if(empty($p['destinations']))throw new InvalidArgumentException('Add at least one destination');
            if(count($p['destinations'])>50)throw new InvalidArgumentException('Maximum 50 destinations');
            $menu=$id;$rows[0]['row']['npcflag']=1;$add('gossip_menu',['MenuID'=>$menu,'TextID'=>1,'VerifiedBuild'=>0]);$add('creature_template_gossip',['CreatureID'=>$id,'MenuID'=>$menu,'VerifiedBuild'=>0]);
            foreach($p['destinations'] as $i=>$destination){$tele=creator_one($db,'game_tele','id',(int)($destination['tele_id']??0));if(!$tele)throw new InvalidArgumentException('Destination does not exist');creator_position($tele);
                $o=creator_defaults($db,'gossip_menu_option');$o['MenuID']=$menu;$o['OptionID']=$i;$o['GossipOptionID']=-($menu*100+$i);$o['OptionText']=trim((string)($destination['label']??$tele['name']));$o['BoxMoney']=max(0,(int)($destination['cost']??0));$o['BoxText']=$o['BoxMoney']?'Travel to '.$tele['name'].'?':null;$o['OptionNpc']=0;$add('gossip_menu_option',$o);
                $s=creator_defaults($db,'smart_scripts');$s=array_replace($s,['entryorguid'=>$id,'source_type'=>0,'id'=>$i,'event_type'=>62,'event_chance'=>100,'event_param1'=>$menu,'event_param2'=>$i,'action_type'=>62,'action_param1'=>$tele['map'],'target_type'=>7,'target_x'=>$tele['position_x'],'target_y'=>$tele['position_y'],'target_z'=>$tele['position_z'],'target_o'=>$tele['orientation'],'comment'=>'ForeverCreator teleporter: '.$o['OptionText']]);$add('smart_scripts',$s);
                $level=(int)($destination['min_level']??0);if($level>0){$c=creator_defaults($db,'conditions');$c=array_replace($c,['SourceTypeOrReferenceId'=>15,'SourceGroup'=>$menu,'SourceEntry'=>$i,'ConditionTypeOrReference'=>27,'ConditionValue1'=>$level,'ConditionValue2'=>3,'Comment'=>'ForeverCreator level gate']);$add('conditions',$c);}
            }
        }
    }else{
        $table=$spec['table'];$row=array_replace(creator_defaults($db,$table),$tables[$table]??[]);$row[$spec['key']]=$id;
        if(in_array($kind,['spawn','game_tele'],true))creator_position($row);
        if($kind==='spawn'&&!creator_one($db,'creature_template','entry',(int)$row['id']))throw new InvalidArgumentException('NPC template does not exist');
        if($kind==='vendor'){if(!creator_one($db,'creature_template','entry',$id))throw new InvalidArgumentException('Vendor NPC does not exist');if((int)$row['type']===1&&!creator_one($db,'armory_item','id',(int)$row['item']))throw new InvalidArgumentException('Vendor item does not exist');}
        if($kind==='loot'){if(!creator_one($db,'armory_item','id',(int)$row['Item']))throw new InvalidArgumentException('Loot item does not exist');if((float)$row['Chance']<0||(float)$row['Chance']>100||(int)$row['MinCount']>(int)$row['MaxCount'])throw new InvalidArgumentException('Invalid loot chance or count');}
        $add($table,$row);
        if(in_array($kind,['vendor','loot'],true)){
            if(count($p['inventory']??[])>99)throw new InvalidArgumentException('Maximum 100 inventory or loot rows per creation.');
            foreach($p['inventory']??[] as $index=>$member){$r=array_replace(creator_defaults($db,$table),$member);$r[$spec['key']]=$id;
                $item=(int)($kind==='vendor'?$r['item']:$r['Item']);
                if(!creator_one($db,'armory_item','id',$item)&&!creator_one($db,'forever_creator_item','ID',$item))throw new InvalidArgumentException('Inventory item #'.$item.' does not exist.');
                if($kind==='vendor'&&(int)$r['type']!==1)throw new InvalidArgumentException('Inventory helper supports item rows (type 1).');
                if($kind==='loot'&&((float)$r['Chance']<0||(float)$r['Chance']>100||(int)$r['MinCount']>(int)$r['MaxCount']))throw new InvalidArgumentException('Invalid loot chance or count');
                $add($table,$r);
            }
        }
    }
    $name=(string)($kind==='item'?($tables['forever_creator_item_sparse']['Display']??'Custom item'):($rows[0]['row'][$spec['name']]??($kind.' #'.$id)));
    if(trim($name)==='')throw new InvalidArgumentException('A name or title is required');
    $owned=[];$sql=[];$seen=[];
    foreach($rows as $row){$key=creator_owned($db,$row['table'],$row['row']);$signature=json_encode($key);if(isset($seen[$signature]))throw new InvalidArgumentException('Duplicate record in '.$row['table'].'; use different item IDs or slots.');$seen[$signature]=true;$owned[]=$key;$sql[]=creator_insert_sql($db,$row['table'],$row['row']);}
    return ['kind'=>$kind,'entry'=>$id,'name'=>$name,'owned'=>$owned,'sql'=>$sql,'payload'=>$p];
}
function creator_apply($db,array $payload,int $author,bool $export=false): array
{
    $lock=creator_query($db,"SELECT GET_LOCK('forever_creator_authoring',10) AS acquired")->fetch_assoc();
    if(!(int)$lock['acquired'])throw new RuntimeException('Another creation is being saved. Try again shortly.');
    try{
        $historyId=(int)($payload['history_id']??0);$history=$historyId?creator_history($db,$historyId):null;
        if($historyId&&!$history)throw new RuntimeException('Creation history not found');
        if($history&&(int)($payload['revision']??0)!==(int)$history['revision'])throw new RuntimeException('This creation changed in another window. Reload it.');
        $plan=creator_build($db,$payload);
        if(!$history&&creator_query($db,"SELECT 1 FROM forever_creator_history WHERE kind=".creator_sql_value($db,$plan['kind'])." AND entry=".$plan['entry']." LIMIT 1")->fetch_row())throw new RuntimeException('This creation ID has a history. Open it from History to edit or restore it.');
        if($history&&($history['kind']!==$plan['kind']||(int)$history['entry']!==$plan['entry']))throw new RuntimeException('The identity of an existing creation cannot be changed. Use New creation.');
        foreach($plan['owned'] as $owned){$where=creator_where($db,$owned);$found=creator_query($db,'SELECT 1 FROM `'.$owned['table'].'` WHERE '.$where.' LIMIT 1')->fetch_row();if($found){$permitted=false;foreach($history['owned']??[] as $old)if($old['table']===$owned['table']&&$old['keys']==$owned['keys'])$permitted=true;if(!$permitted)throw new RuntimeException('ID collision in '.$owned['table'].'. Choose another ID.');}}
        if($plan['kind']==='item'&&!$history&&(creator_one($db,'forever_creator_source_item','ID',$plan['entry'])||creator_one($db,'armory_item','id',$plan['entry'])))throw new RuntimeException('This ID belongs to an existing item. Choose a free ID.');
        $delete=[];foreach($history['owned']??[] as $old)$delete[]='DELETE FROM `'.$old['table'].'` WHERE '.creator_where($db,$old);
        $script="-- ForeverCreator world export\nUSE `world`;\nSTART TRANSACTION;\n".implode(";\n",array_merge($delete,$plan['sql'])).";\nCOMMIT;\n-- Restart the updated worldserver to load the custom records.\n";
        if($export)return ['sql'=>$script];
        $db->begin_transaction();try{foreach($delete as $sql)creator_query($db,$sql);foreach($plan['sql'] as $sql)creator_query($db,$sql);
            $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$owned=json_encode($plan['owned'],JSON_THROW_ON_ERROR);$kind=$plan['kind'];$entry=$plan['entry'];$name=$plan['name'];$revision=(int)($history['revision']??0)+1;
            $st=$db->prepare("INSERT INTO forever_creator_history(kind,entry,name,payload_json,owned_json,revision,created_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),payload_json=VALUES(payload_json),owned_json=VALUES(owned_json),revision=VALUES(revision),state='active'");$st->bind_param('sisssii',$kind,$entry,$name,$json,$owned,$revision,$author);if(!$st->execute())throw new RuntimeException($st->error);$st->close();$saved=creator_query($db,"SELECT id FROM forever_creator_history WHERE kind=".creator_sql_value($db,$kind)." AND entry=$entry")->fetch_assoc();$hid=(int)$saved['id'];
            $st=$db->prepare("INSERT INTO forever_creator_revisions(creation_id,payload_json,action,author) VALUES (?,?,'save',?)");$st->bind_param('isi',$hid,$json,$author);if(!$st->execute())throw new RuntimeException($st->error);$st->close();$db->commit();return ['id'=>$hid,'revision'=>$revision,'entry'=>$entry,'sql'=>$script];
        }catch(Throwable $e){$db->rollback();throw $e;}
    }finally{$db->query("SELECT RELEASE_LOCK('forever_creator_authoring')");}
}
function creator_delete($db,int $id,int $revision,int $author): void
{
    $lock=creator_query($db,"SELECT GET_LOCK('forever_creator_authoring',10) AS acquired")->fetch_assoc();if(!(int)$lock['acquired'])throw new RuntimeException('Another creation is being saved. Try again shortly.');
    try{
    $history=creator_history($db,$id);if(!$history||$history['state']!=='active')throw new RuntimeException('Active creation not found');if((int)$history['revision']!==$revision)throw new RuntimeException('The creation changed. Reload before deleting.');
    $db->begin_transaction();try{foreach($history['owned'] as $owned)creator_query($db,'DELETE FROM `'.$owned['table'].'` WHERE '.creator_where($db,$owned));creator_query($db,"UPDATE forever_creator_history SET state='deleted',revision=revision+1 WHERE id=$id");$json=$history['payload_json'];$st=$db->prepare("INSERT INTO forever_creator_revisions(creation_id,payload_json,action,author) VALUES (?,?,'delete',?)");$st->bind_param('isi',$id,$json,$author);$st->execute();$st->close();$db->commit();}catch(Throwable $e){$db->rollback();throw $e;}
    }finally{$db->query("SELECT RELEASE_LOCK('forever_creator_authoring')");}
}
