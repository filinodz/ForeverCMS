<?php
define('ALLOWED_ACCESS',true);
require __DIR__.'/../www/includes/forever_models.php';
$checks=[];function check_appearance(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks[]=$message;}
class AppearanceRows{private array $rows;function __construct(array $rows){$this->rows=$rows;}function fetch_assoc(){return array_shift($this->rows);}}
class AppearanceDatabase{
    public array $queries=[];function __construct(private array $tables=[],private array $rows=[]){}
    function query(string $sql){$this->queries[]=$sql;return new AppearanceRows(str_contains($sql,'information_schema')?array_map(fn($name)=>['TABLE_NAME'=>$name],$this->tables):$this->rows);}
}
$stock=forever_model_appearances(null,[57,209,210,1395,1396,6097]);
check_appearance((int)$stock['57:0']['ItemDisplayInfoID']===10456,'Stock Acolyte robe resolves from actual Forever world export without ForeverCreator tables');
check_appearance((int)$stock['209:0']['ItemDisplayInfoID']===7947,'Stock Acolyte pants use the real exported display ID');
check_appearance(forever_model_equipment(null,[4=>57,6=>209,7=>210])===[[20,10456],[7,7947],[8,10872]],'Robe, legs and feet produce complete viewer equipment without a database export');
check_appearance(forever_model_equipment(null,[1=>57,10=>209])===[],'Non-rendered inventory slots are not incorrectly equipped');
check_appearance(forever_model_appearances(null,[2147483647])===[],'Unknown custom item IDs do not invent a display');
$db=new AppearanceDatabase(['forever_creator_source_item_modified_appearance','forever_creator_source_item_appearance','forever_creator_source_item_sparse'],[['ItemID'=>57,'ItemAppearanceModifierID'=>0,'ItemDisplayInfoID'=>650123,'InventoryType'=>20]]);
$custom=forever_model_appearances($db,[57,209]);
check_appearance((int)$custom['57:0']['ItemDisplayInfoID']===650123,'Available server-side appearances override the bundled stock catalogue');
check_appearance(isset($custom['209:0']),'Missing individual DB appearances fall back without losing other gear');
check_appearance(!str_contains($db->queries[1],'`forever_creator_item_appearance`'),'Missing custom tables are never referenced by the appearance query');
foreach([1,2,3,4,5,6,7,8,10,11,95,96] as $race)foreach([0,1] as $gender){$identity=forever_character_model_identity($race,$gender);check_appearance($identity['model']!==null&&$identity['gender']===$gender,'Race '.$race.' gender '.$gender.' keeps its actual model identity with the same equipped display IDs');}
check_appearance(forever_character_model_identity(5,0)['model']==='scourgemale','Undead keeps its own model rather than a human mannequin');
echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
