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

/** Stock client appearances contain item metadata only, never character data. */
function forever_stock_model_appearances(): array
{
    static $catalogue = null;
    if ($catalogue === null) {
        $path = __DIR__ . '/../assets/data/item-appearances-forever.json';
        $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;
        $catalogue = is_array($data) && ($data['schema'] ?? 0) === 1
            ? ($data['appearances'] ?? []) : [];
    }
    return $catalogue;
}

/** Prefer installed server exports; stock gear also works without Creator tables. */
function forever_model_appearances($db,array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (!$ids) return [];
    $out = [];
    $stock = forever_stock_model_appearances();
    foreach ($ids as $id) {
        foreach ($stock[$id] ?? [] as $modifier => $values) {
            $out[$id . ':' . (int)$modifier] = [
                'ItemID' => $id, 'ItemAppearanceModifierID' => (int)$modifier,
                'ItemDisplayInfoID' => (int)$values[0], 'InventoryType' => (int)$values[1],
                'DefaultIconFileDataID' => (int)$values[2], 'ItemAppearanceID' => (int)$values[3],
            ];
        }
    }
    if (!$db) return $out;
    try {
        $result = $db->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND TABLE_NAME LIKE 'forever_creator_%'");
        if (!$result) return $out;
        $tables = [];
        while ($row = $result->fetch_assoc()) $tables[$row['TABLE_NAME']] = true;
        $sources = [];
        foreach (['item_modified_appearance', 'item_appearance', 'item_sparse'] as $name) {
            $parts = [];
            foreach (['forever_creator_', 'forever_creator_source_'] as $priority => $prefix) {
                $table = $prefix . $name;
                if (isset($tables[$table])) $parts[] = 'SELECT *, ' . $priority . ' AS _cms_priority FROM `' . $table . '`';
            }
            if (!$parts) return $out;
            $sources[] = '(' . implode(' UNION ALL ', $parts) . ')';
        }
        [$modified, $appearance, $sparse] = $sources;
        $list = implode(',', $ids);
        $result = $db->query("SELECT m.ItemID,m.ItemAppearanceID,m.ItemAppearanceModifierID,a.ItemDisplayInfoID,a.DefaultIconFileDataID,i.InventoryType
            FROM $modified m JOIN $appearance a ON a.ID=m.ItemAppearanceID JOIN $sparse i ON i.ID=m.ItemID
            WHERE m.ItemID IN ($list) ORDER BY m.ItemID,m.ItemAppearanceModifierID,m._cms_priority,a._cms_priority,i._cms_priority,m.OrderIndex");
        $seen = [];
        if ($result) while ($row = $result->fetch_assoc()) {
            $key = (int)$row['ItemID'] . ':' . (int)$row['ItemAppearanceModifierID'];
            if (!isset($seen[$key]) && (int)$row['ItemDisplayInfoID'] > 0) {
                $out[$key] = $row;
                $seen[$key] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('ForeverCMS appearance export unavailable: ' . $e->getMessage());
    }
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
