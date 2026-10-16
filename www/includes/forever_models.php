<?php
if(!defined('ALLOWED_ACCESS'))exit('Forbidden');

/** Skyborne fallback is explicitly recorded in Forever 70425 ChrRaces.db2:
 * races 95/96 use model AND texture fallback race 10 for both sexes.
 * Do not pass unsupported race 95 to the external renderer or invent its face.
 */
function forever_character_model_identity(int $race,int $gender): array
{
    $names=[1=>'human',2=>'orc',3=>'dwarf',4=>'nightelf',5=>'scourge',6=>'tauren',7=>'gnome',8=>'troll',10=>'bloodelf',11=>'draenei'];
    $skyborne=in_array($race,[95,96],true);$renderRace=$skyborne?10:$race;$gender=$gender===1?1:0;
    return ['race'=>$renderRace,'gender'=>$gender,'model'=>isset($names[$renderRace])?$names[$renderRace].($gender?'female':'male'):null,'skyborne'=>$skyborne];
}

/** Actual display identifiers exported from this repack's DB2 data. */
function forever_model_appearances($db,array $ids): array
{
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)return [];
    $list=implode(',',$ids);
    $sql="SELECT m.ItemID,m.ItemAppearanceID,m.ItemAppearanceModifierID,a.ItemDisplayInfoID,a.DefaultIconFileDataID,i.InventoryType
        FROM (SELECT * FROM forever_creator_item_modified_appearance UNION ALL SELECT * FROM forever_creator_source_item_modified_appearance) m
        JOIN (SELECT * FROM forever_creator_item_appearance UNION ALL SELECT * FROM forever_creator_source_item_appearance) a ON a.ID=m.ItemAppearanceID
        JOIN (SELECT * FROM forever_creator_item_sparse UNION ALL SELECT * FROM forever_creator_source_item_sparse) i ON i.ID=m.ItemID
        WHERE m.ItemID IN ($list) ORDER BY m.ItemID,m.ItemAppearanceModifierID,m.OrderIndex";
    $result=$db->query($sql);$out=[];if($result)while($row=$result->fetch_assoc()){$key=(int)$row['ItemID'].':'.(int)$row['ItemAppearanceModifierID'];if(!isset($out[$key]))$out[$key]=$row;}
    return $out;
}

function forever_model_equipment($db,array $items): array
{
    $appearances=forever_model_appearances($db,array_values($items));
    $slots=[0=>1,2=>3,3=>4,4=>5,5=>6,6=>7,7=>8,8=>9,9=>10,14=>16,15=>21,16=>22,17=>26,18=>19];
    $out=[];
    foreach($items as $slot=>$entry){$a=$appearances[(int)$entry.':0']??null;if(!$a||!isset($slots[(int)$slot]))continue;$renderSlot=(int)$a['InventoryType']===20?20:$slots[(int)$slot];if((int)$a['ItemDisplayInfoID']>0)$out[]=[$renderSlot,(int)$a['ItemDisplayInfoID']];}
    return $out;
}
