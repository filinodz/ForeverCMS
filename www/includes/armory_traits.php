<?php
if(!defined('ALLOWED_ACCESS'))exit('Forbidden');

function armory_trait_query($db,string $sql): array
{
    $r=$db->query($sql);return $r?$r->fetch_all(MYSQLI_ASSOC):[];
}
function armory_spell_details($world,array $ids): array
{
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)return [];$list=implode(',',$ids);
    $sql="SELECT s.ID,s.Name,m.iconFileDataId,COALESCE(i.name,'inv_misc_questionmark') icon FROM forever_armory_source_spell_name s LEFT JOIN (SELECT SpellID,MAX(SpellIconFileDataID) iconFileDataId FROM forever_armory_source_spell_misc WHERE DifficultyID=0 GROUP BY SpellID) m ON m.SpellID=s.ID LEFT JOIN armory_icon i ON i.fileDataId=m.iconFileDataId WHERE s.ID IN($list)";
    $out=[];foreach(armory_trait_query($world,$sql) as $r)$out[(int)$r['ID']]=$r;return $out;
}
function armory_character_traits($world,$characters,int $guid,int $class): array
{
    $ready=$world->query('SELECT 1 FROM forever_armory_source_trait_node LIMIT 1');
    if(!$ready||!$ready->fetch_row())return ['ready'=>false,'configs'=>[],'spells'=>[],'skills'=>[]];
    $configs=armory_trait_query($characters,"SELECT * FROM character_trait_config WHERE guid=$guid ORDER BY type,combatConfigFlags DESC,traitConfigId");
    $ranks=armory_trait_query($characters,"SELECT * FROM character_trait_entry WHERE guid=$guid");$byConfig=[];foreach($ranks as $r)$byConfig[(int)$r['traitConfigId']][(int)$r['traitNodeEntryId']]=(int)$r['rank'];
    $mask=1<<max(0,$class-1);
    $classTrees=armory_trait_query($world,"SELECT DISTINCT x.TraitTreeID FROM forever_armory_source_skill_line_x_trait_tree x JOIN forever_armory_source_skill_line s ON s.ID=x.SkillLineID JOIN forever_armory_source_skill_race_class_info c ON c.SkillID=s.ID WHERE s.CategoryID=7 AND (c.ClassMask & $mask)<>0");
    $classIds=array_map(static fn($r)=>(int)$r['TraitTreeID'],$classTrees);
    if(!$configs&&$classIds)$configs=[['traitConfigId'=>0,'type'=>1,'name'=>'Class talents','combatConfigFlags'=>1]];
    $panels=[];$spellIds=[];
    foreach($configs as $config){$cid=(int)$config['traitConfigId'];$type=(int)$config['type'];$ids=$type===1?$classIds:[];
        if($type===3){$system=(int)($config['traitSystemId']??0);$ids=array_map(static fn($r)=>(int)$r['ID'],armory_trait_query($world,"SELECT ID FROM forever_armory_source_trait_tree WHERE TraitSystemID=$system"));}
        if($type===2){$skill=(int)($config['skillLineId']??0);$ids=array_map(static fn($r)=>(int)$r['TraitTreeID'],armory_trait_query($world,"SELECT TraitTreeID FROM forever_armory_source_skill_line_x_trait_tree WHERE SkillLineID=$skill"));}
        $list=implode(',',array_filter($ids));if(!$list)continue;
        $nodes=armory_trait_query($world,"SELECT n.ID nodeId,n.PosX,n.PosY,n.TraitTreeID,n.Type,n.Flags,x.TraitNodeEntryID,e.MaxRanks,d.SpellID,d.OverrideName,d.OverrideSubtext,d.OverrideDescription,d.OverrideIcon,t.TitleText FROM forever_armory_source_trait_node n JOIN forever_armory_source_trait_node_x_trait_node_entry x ON x.TraitNodeID=n.ID JOIN forever_armory_source_trait_node_entry e ON e.ID=x.TraitNodeEntryID JOIN forever_armory_source_trait_definition d ON d.ID=e.TraitDefinitionID JOIN forever_armory_source_trait_tree t ON t.ID=n.TraitTreeID WHERE n.TraitTreeID IN($list) ORDER BY n.TraitTreeID,n.PosY,n.PosX,x.`Index` LIMIT 1200");
        $points=0;foreach($nodes as &$node){$node['rank']=$byConfig[$cid][(int)$node['TraitNodeEntryID']]??0;$points+=$node['rank'];$spellIds[]=(int)$node['SpellID'];}unset($node);
        if($nodes)$panels[]=['id'=>$cid,'name'=>$config['name']?:($type===3?'Legacy':($type===2?'Profession talents':'Class talents')),'active'=>$type===1&&((int)($config['combatConfigFlags']??0)&1)!==0,'points'=>$points,'nodes'=>$nodes];
    }
    $known=armory_trait_query($characters,"SELECT spell FROM character_spell WHERE guid=$guid AND active=1 AND disabled=0 ORDER BY spell LIMIT 1500");foreach($known as $r)$spellIds[]=(int)$r['spell'];
    $spells=armory_spell_details($world,$spellIds);
    // Override icons are still actual client FileDataIDs, never guessed icons.
    $overrideIds=[];foreach($panels as $panel)foreach($panel['nodes'] as $n)if((int)$n['OverrideIcon']>0)$overrideIds[]=(int)$n['OverrideIcon'];$icons=[];
    if($overrideIds)foreach(armory_trait_query($world,'SELECT fileDataId,name FROM armory_icon WHERE fileDataId IN('.implode(',',array_unique($overrideIds)).')') as $r)$icons[(int)$r['fileDataId']]=$r['name'];
    foreach($panels as &$panel)foreach($panel['nodes'] as &$node){$s=$spells[(int)$node['SpellID']]??[];$node['name']=$node['OverrideName']?:($s['Name']??'Spell #'.$node['SpellID']);$node['icon']=$icons[(int)$node['OverrideIcon']]??$s['icon']??'inv_misc_questionmark';}unset($panel,$node);
    $book=[];foreach($known as $r){$id=(int)$r['spell'];$book[]=$spells[$id]??['ID'=>$id,'Name'=>'Spell #'.$id,'icon'=>'inv_misc_questionmark'];}
    $skills=[];foreach(armory_trait_query($characters,"SELECT skill,value,max FROM character_skills WHERE guid=$guid ORDER BY skill") as $row)$skills[]=$row;
    $skillIds=implode(',',array_map(static fn($r)=>(int)$r['skill'],$skills));$skillNames=[];
    if($skillIds)foreach(armory_trait_query($world,"SELECT s.ID,s.DisplayName,COALESCE(i.name,'inv_misc_questionmark') icon FROM forever_armory_source_skill_line s LEFT JOIN armory_icon i ON i.fileDataId=s.SpellIconFileID WHERE s.ID IN($skillIds)") as $r)$skillNames[(int)$r['ID']]=$r;
    foreach($skills as &$skill){$skill['name']=$skillNames[(int)$skill['skill']]['DisplayName']??'Skill #'.$skill['skill'];$skill['icon']=$skillNames[(int)$skill['skill']]['icon']??'inv_misc_questionmark';}unset($skill);
    return ['ready'=>true,'configs'=>$panels,'spells'=>$book,'skills'=>$skills];
}
