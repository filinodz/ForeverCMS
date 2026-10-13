<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

function forever_creator_ensure_schema($site_db)
{
    return $site_db->query("CREATE TABLE IF NOT EXISTS creator_items (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        item_id INT UNSIGNED NOT NULL,
        base_item_id INT UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        quality TINYINT UNSIGNED NOT NULL DEFAULT 1,
        icon_file_data_id INT NOT NULL DEFAULT 0,
        payload_json LONGTEXT NOT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uk_creator_item_id (item_id),
        KEY idx_creator_updated (updated_at), KEY idx_creator_author (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function forever_creator_clamp($value, $min, $max)
{
    return max($min, min($max, (int)$value));
}

function forever_creator_text($value, $max)
{
    $value = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function forever_creator_columns($db, $schema, $table)
{
    static $cache = [];
    $key = $schema . '.' . $table;
    if (isset($cache[$key])) return $cache[$key];
    $stmt = $db->prepare('SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    if (!$stmt) throw new RuntimeException('Unable to inspect ' . $key . '.');
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $columns = [];
    while ($row = $result->fetch_assoc()) $columns[$row['COLUMN_NAME']] = $row;
    $stmt->close();
    if (!$columns) throw new RuntimeException('Required table ' . $key . ' was not found.');
    return $cache[$key] = $columns;
}

function forever_creator_literal($db, $value, $dataType)
{
    if ($value === null) return 'NULL';
    if (in_array($dataType, ['tinyint','smallint','mediumint','int','bigint','decimal','float','double','bit'], true)) {
        if (!is_numeric($value)) $value = 0;
        return (string)(0 + $value);
    }
    return "'" . $db->real_escape_string((string)$value) . "'";
}

function forever_creator_clone_id($db, $schema, $table, $idColumn, $sourceId, $targetId, $optional = false)
{
    try { $columns = forever_creator_columns($db, $schema, $table); }
    catch (Throwable $e) { if ($optional) return false; throw $e; }
    if (!isset($columns[$idColumn])) {
        if ($optional) return false;
        throw new RuntimeException("$schema.$table has no $idColumn column.");
    }
    $names = array_keys($columns);
    $quoted = implode(',', array_map(fn($c) => '`' . $c . '`', $names));
    $select = implode(',', array_map(fn($c) => $c === $idColumn ? (string)(int)$targetId : '`' . $c . '`', $names));
    $db->query("DELETE FROM `$schema`.`$table` WHERE `$idColumn`=" . (int)$targetId);
    if (!$db->query("INSERT INTO `$schema`.`$table` ($quoted) SELECT $select FROM `$schema`.`$table` WHERE `$idColumn`=" . (int)$sourceId)) {
        if ($optional) return false;
        throw new RuntimeException("Could not clone $schema.$table: " . $db->error);
    }
    if ($db->affected_rows < 1 && !$optional) throw new RuntimeException("Base item $sourceId is missing from $schema.$table.");
    return true;
}

function forever_creator_update_id($db, $schema, $table, $idColumn, $targetId, array $values)
{
    $columns = forever_creator_columns($db, $schema, $table);
    $sets = [];
    foreach ($values as $column => $value) {
        if ($column === $idColumn || $column === 'VerifiedBuild' || !isset($columns[$column])) continue;
        $sets[] = '`' . $column . '`=' . forever_creator_literal($db, $value, $columns[$column]['DATA_TYPE']);
    }
    if ($sets && !$db->query("UPDATE `$schema`.`$table` SET " . implode(',', $sets) . " WHERE `$idColumn`=" . (int)$targetId)) {
        throw new RuntimeException("Could not update $schema.$table: " . $db->error);
    }
}

function forever_creator_item($db, $itemId)
{
    $itemId = (int)$itemId;
    $sql = "SELECT i.*, s.*, a.name AS icon_name FROM hotfixes.item i
            JOIN hotfixes.item_sparse s ON s.ID=i.ID AND s.VerifiedBuild=i.VerifiedBuild
            LEFT JOIN world.armory_icon a ON a.fileDataId=i.IconFileDataID
            WHERE i.ID=$itemId ORDER BY i.VerifiedBuild DESC LIMIT 1";
    $result = $db->query($sql);
    if (!$result) return null;
    $row = $result->fetch_assoc();
    if (!$row) return null;
    $effects = [];
    $effectResult = $db->query("SELECT e.* FROM hotfixes.item_x_item_effect x JOIN hotfixes.item_effect e ON e.ID=x.ItemEffectID AND e.VerifiedBuild=x.VerifiedBuild WHERE x.ItemID=$itemId ORDER BY e.LegacySlotIndex,e.ID LIMIT 5");
    if ($effectResult) while ($effect = $effectResult->fetch_assoc()) $effects[] = $effect;
    $row['_effects'] = $effects;
    return $row;
}

function forever_creator_history_item($site_db, $itemId)
{
    $stmt = $site_db->prepare('SELECT * FROM creator_items WHERE item_id=?');
    $itemId = (int)$itemId;
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $row['payload'] = json_decode($row['payload_json'], true) ?: [];
    return $row ?: null;
}

function forever_creator_payload(array $post)
{
    $payload = [
        'item_id' => max(1, (int)($post['item_id'] ?? 0)),
        'base_item_id' => max(1, (int)($post['base_item_id'] ?? 0)),
        'name' => forever_creator_text($post['name'] ?? '', 255),
        'description' => forever_creator_text($post['description'] ?? '', 1024),
        'quality' => forever_creator_clamp($post['quality'] ?? 1, 0, 7),
        'item_level' => forever_creator_clamp($post['item_level'] ?? 1, 0, 65535),
        'required_level' => forever_creator_clamp($post['required_level'] ?? 0, -128, 127),
        'class_id' => forever_creator_clamp($post['class_id'] ?? 0, -2147483648, 2147483647),
        'subclass_id' => forever_creator_clamp($post['subclass_id'] ?? 0, 0, 255),
        'inventory_type' => forever_creator_clamp($post['inventory_type'] ?? 0, -128, 127),
        'bonding' => forever_creator_clamp($post['bonding'] ?? 0, 0, 255),
        'material' => forever_creator_clamp($post['material'] ?? 0, 0, 255),
        'sheathe_type' => forever_creator_clamp($post['sheathe_type'] ?? 0, 0, 255),
        'icon_file_data_id' => (int)($post['icon_file_data_id'] ?? 0),
        'stackable' => max(1, (int)($post['stackable'] ?? 1)),
        'max_count' => (int)($post['max_count'] ?? 0),
        'buy_price' => max(0, (int)($post['buy_price'] ?? 0)),
        'sell_price' => max(0, (int)($post['sell_price'] ?? 0)),
        'vendor_stack' => max(1, (int)($post['vendor_stack'] ?? 1)),
        'allowable_class' => forever_creator_clamp($post['allowable_class'] ?? -1, -32768, 32767),
        'allowable_race1' => (int)($post['allowable_race1'] ?? -1),
        'allowable_race2' => (int)($post['allowable_race2'] ?? -1),
        'required_skill' => forever_creator_clamp($post['required_skill'] ?? 0, 0, 65535),
        'required_skill_rank' => forever_creator_clamp($post['required_skill_rank'] ?? 0, 0, 65535),
        'required_ability' => max(0, (int)($post['required_ability'] ?? 0)),
        'item_set' => forever_creator_clamp($post['item_set'] ?? 0, 0, 65535),
        'lock_id' => forever_creator_clamp($post['lock_id'] ?? 0, 0, 65535),
        'bag_family' => max(0, (int)($post['bag_family'] ?? 0)),
        'container_slots' => forever_creator_clamp($post['container_slots'] ?? 0, 0, 255),
        'socket_bonus' => forever_creator_clamp($post['socket_bonus'] ?? 0, 0, 65535),
        'delay' => forever_creator_clamp($post['delay'] ?? 0, 0, 65535),
        'damage_type' => forever_creator_clamp($post['damage_type'] ?? 0, 0, 255),
        'damage_variance' => (float)($post['damage_variance'] ?? 0),
        'preview_damage_min' => max(0, (float)($post['preview_damage_min'] ?? 0)),
        'preview_damage_max' => max(0, (float)($post['preview_damage_max'] ?? 0)),
        'preview_armor' => max(0, (int)($post['preview_armor'] ?? 0)),
    ];
    for ($i=1; $i<=5; $i++) $payload['flags' . $i] = (int)($post['flags' . $i] ?? 0);
    for ($i=1; $i<=3; $i++) $payload['socket_type' . $i] = forever_creator_clamp($post['socket_type' . $i] ?? 0, 0, 255);
    $payload['stats'] = [];
    foreach (($post['stats'] ?? []) as $stat) {
        if (count($payload['stats']) >= 10) break;
        $type = (int)($stat['type'] ?? 0); $value = (int)($stat['value'] ?? 0); $socket = (float)($stat['socket'] ?? 0);
        if ($type || $value || $socket) $payload['stats'][] = ['type'=>$type,'value'=>$value,'socket'=>$socket];
    }
    $payload['effects'] = [];
    foreach (($post['effects'] ?? []) as $effect) {
        if (count($payload['effects']) >= 5) break;
        $spell = (int)($effect['spell'] ?? 0);
        if (!$spell) continue;
        $payload['effects'][] = [
            'trigger'=>forever_creator_clamp($effect['trigger'] ?? 0,0,255), 'spell'=>$spell,
            'charges'=>forever_creator_clamp($effect['charges'] ?? 0,-32768,32767),
            'cooldown'=>(int)($effect['cooldown'] ?? -1), 'category_cooldown'=>(int)($effect['category_cooldown'] ?? -1),
            'text'=>forever_creator_text($effect['text'] ?? '',255)
        ];
    }
    if ($payload['name'] === '') throw new InvalidArgumentException('Item name is required.');
    if ($payload['item_id'] === $payload['base_item_id']) throw new InvalidArgumentException('The new item ID must differ from the base item ID.');
    if ($payload['preview_damage_max'] < $payload['preview_damage_min']) throw new InvalidArgumentException('Maximum damage cannot be lower than minimum damage.');
    return $payload;
}

function forever_creator_remove_effects($db, array $historyPayload)
{
    $ids = array_values(array_filter(array_map('intval', $historyPayload['_creator_effect_ids'] ?? [])));
    if ($ids) $db->query('DELETE FROM hotfixes.item_effect WHERE ID IN (' . implode(',', $ids) . ')');
}

function forever_creator_save_item($db, $site_db, array $post, $authorId)
{
    $p = forever_creator_payload($post);
    if (!forever_creator_item($db, $p['base_item_id'])) throw new InvalidArgumentException('The selected base item does not exist in Forever hotfixes.');
    $history = forever_creator_history_item($site_db, $p['item_id']);
    $exists = forever_creator_item($db, $p['item_id']);
    if ($exists && !$history) throw new InvalidArgumentException('This item ID already belongs to the server. Choose a free custom ID.');
    $oldPayload = $history['payload'] ?? [];
    $db->begin_transaction();
    try {
        forever_creator_remove_effects($db, $oldPayload);
        $db->query('DELETE FROM hotfixes.item_x_item_effect WHERE ItemID=' . $p['item_id']);
        forever_creator_clone_id($db,'hotfixes','item','ID',$p['base_item_id'],$p['item_id']);
        forever_creator_clone_id($db,'hotfixes','item_sparse','ID',$p['base_item_id'],$p['item_id']);
        forever_creator_clone_id($db,'hotfixes','item_search_name','ID',$p['base_item_id'],$p['item_id']);
        forever_creator_clone_id($db,'world','item_template_addon','Id',$p['base_item_id'],$p['item_id'],true);

        $itemValues = ['ClassID'=>$p['class_id'],'SubclassID'=>$p['subclass_id'],'InventoryType'=>$p['inventory_type'],'Material'=>$p['material'],'SheatheType'=>$p['sheathe_type'],'IconFileDataID'=>$p['icon_file_data_id']];
        forever_creator_update_id($db,'hotfixes','item','ID',$p['item_id'],$itemValues);
        $sparse = [
            'Display'=>$p['name'],'Display1'=>$p['name'],'Display2'=>$p['name'],'Display3'=>$p['name'],'Description'=>$p['description'],
            'OverallQualityID'=>$p['quality'],'ItemLevel'=>$p['item_level'],'RequiredLevel'=>$p['required_level'],
            'InventoryType'=>$p['inventory_type'],'Bonding'=>$p['bonding'],'Material'=>$p['material'],'SheatheType'=>$p['sheathe_type'],
            'Stackable'=>$p['stackable'],'MaxCount'=>$p['max_count'],'BuyPrice'=>$p['buy_price'],'SellPrice'=>$p['sell_price'],'VendorStackCount'=>$p['vendor_stack'],
            'AllowableClass'=>$p['allowable_class'],'AllowableRace1'=>$p['allowable_race1'],'AllowableRace2'=>$p['allowable_race2'],
            'RequiredSkill'=>$p['required_skill'],'RequiredSkillRank'=>$p['required_skill_rank'],'RequiredAbility'=>$p['required_ability'],
            'ItemSet'=>$p['item_set'],'LockID'=>$p['lock_id'],'BagFamily'=>$p['bag_family'],'ContainerSlots'=>$p['container_slots'],
            'SocketMatchEnchantmentId'=>$p['socket_bonus'],'ItemDelay'=>$p['delay'],'DamageDamageType'=>$p['damage_type'],'DmgVariance'=>$p['damage_variance'],
            'Flags1'=>$p['flags1'],'Flags2'=>$p['flags2'],'Flags3'=>$p['flags3'],'Flags4'=>$p['flags4'],'Flags5'=>$p['flags5'],
            'SocketType1'=>$p['socket_type1'],'SocketType2'=>$p['socket_type2'],'SocketType3'=>$p['socket_type3']
        ];
        for ($i=1;$i<=10;$i++) {
            $stat = $p['stats'][$i-1] ?? ['type'=>0,'value'=>0,'socket'=>0];
            $sparse['StatModifierBonusStat'.$i]=$stat['type']; $sparse['StatPercentEditor'.$i]=$stat['value']; $sparse['StatPercentageOfSocket'.$i]=$stat['socket'];
        }
        forever_creator_update_id($db,'hotfixes','item_sparse','ID',$p['item_id'],$sparse);
        forever_creator_update_id($db,'hotfixes','item_search_name','ID',$p['item_id'],[
            'Display'=>$p['name'],'OverallQualityID'=>$p['quality'],'ItemLevel'=>$p['item_level'],'RequiredLevel'=>$p['required_level'],
            'AllowableClass'=>$p['allowable_class'],'AllowableRace1'=>$p['allowable_race1'],'AllowableRace2'=>$p['allowable_race2'],
            'RequiredSkill'=>$p['required_skill'],'RequiredSkillRank'=>$p['required_skill_rank'],'RequiredAbility'=>$p['required_ability'],
            'Flags1'=>$p['flags1'],'Flags2'=>$p['flags2'],'Flags3'=>$p['flags3'],'Flags4'=>$p['flags4'],'Flags5'=>$p['flags5']
        ]);

        $effectIds = [];
        if ($p['effects']) {
            $nextEffect = (int)$db->query('SELECT COALESCE(MAX(ID),0)+1 n FROM hotfixes.item_effect')->fetch_assoc()['n'];
            $nextLink = (int)$db->query('SELECT COALESCE(MAX(ID),0)+1 n FROM hotfixes.item_x_item_effect')->fetch_assoc()['n'];
            foreach ($p['effects'] as $index=>$effect) {
                $effectId=$nextEffect++; $linkId=$nextLink++; $effectIds[]=$effectId;
                $db->query("INSERT INTO hotfixes.item_effect (ID,LegacySlotIndex,TriggerType,Charges,CoolDownMSec,CategoryCoolDownMSec,SpellCategoryID,SpellID,ChrSpecializationID,PlayerConditionID,VerifiedBuild) VALUES ($effectId,$index,".(int)$effect['trigger'].','.(int)$effect['charges'].','.(int)$effect['cooldown'].','.(int)$effect['category_cooldown'].",0,".(int)$effect['spell'].",0,0,0)");
                if ($db->errno) throw new RuntimeException('Could not create item effect: '.$db->error);
                $db->query("INSERT INTO hotfixes.item_x_item_effect (ID,ItemEffectID,ItemID,VerifiedBuild) VALUES ($linkId,$effectId,".$p['item_id'].",0)");
                if ($db->errno) throw new RuntimeException('Could not link item effect: '.$db->error);
            }
        } else {
            $links = $db->query('SELECT ItemEffectID,VerifiedBuild FROM hotfixes.item_x_item_effect WHERE ItemID=' . $p['base_item_id']);
            if ($links) {
                $nextLink = (int)$db->query('SELECT COALESCE(MAX(ID),0)+1 n FROM hotfixes.item_x_item_effect')->fetch_assoc()['n'];
                while ($link=$links->fetch_assoc()) $db->query("INSERT INTO hotfixes.item_x_item_effect (ID,ItemEffectID,ItemID,VerifiedBuild) VALUES (".$nextLink++.','.(int)$link['ItemEffectID'].','.$p['item_id'].','.(int)$link['VerifiedBuild'].')');
            }
        }
        $p['_creator_effect_ids']=$effectIds;

        $statText=[]; foreach($p['stats'] as $stat) if($stat['type'] || $stat['value']) $statText[]=$stat['type'].':'.$stat['value'];
        $effectText=[]; foreach($p['effects'] as $effect) $effectText[]=$effect['trigger'].':'.$effect['spell'].':'.str_replace(['|',':'],['/',' - '],$effect['text']);
        $name=$db->real_escape_string($p['name']); $desc=$db->real_escape_string($p['description']);
        $stats=$db->real_escape_string(implode(',',$statText)); $effects=$db->real_escape_string(implode('|',$effectText));
        $db->query("INSERT INTO world.armory_item (id,name,quality,itemLevel,requiredLevel,class,subclass,inventoryType,bonding,armor,dmgMin,dmgMax,delay,stats,effects,description,iconFileDataId,sellPrice,allowableClass,itemSet,requiredSkill,requiredSkillRank,flags) VALUES
            (".$p['item_id'].",'$name',".$p['quality'].','.$p['item_level'].','.$p['required_level'].','.$p['class_id'].','.$p['subclass_id'].','.$p['inventory_type'].','.$p['bonding'].','.$p['preview_armor'].','.$p['preview_damage_min'].','.$p['preview_damage_max'].','.$p['delay'].",'$stats','$effects','$desc',".$p['icon_file_data_id'].','.$p['sell_price'].','.$p['allowable_class'].','.$p['item_set'].','.$p['required_skill'].','.$p['required_skill_rank'].','.$p['flags1'].") ON DUPLICATE KEY UPDATE name=VALUES(name),quality=VALUES(quality),itemLevel=VALUES(itemLevel),requiredLevel=VALUES(requiredLevel),class=VALUES(class),subclass=VALUES(subclass),inventoryType=VALUES(inventoryType),bonding=VALUES(bonding),armor=VALUES(armor),dmgMin=VALUES(dmgMin),dmgMax=VALUES(dmgMax),delay=VALUES(delay),stats=VALUES(stats),effects=VALUES(effects),description=VALUES(description),iconFileDataId=VALUES(iconFileDataId),sellPrice=VALUES(sellPrice),allowableClass=VALUES(allowableClass),itemSet=VALUES(itemSet),requiredSkill=VALUES(requiredSkill),requiredSkillRank=VALUES(requiredSkillRank),flags=VALUES(flags)");
        if ($db->errno) throw new RuntimeException('Could not update the Armory item index: '.$db->error);
        $json=json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new RuntimeException('Could not serialize the item history.');
        $stmt=$db->prepare('INSERT INTO forever_site.creator_items (item_id,base_item_id,name,quality,icon_file_data_id,payload_json,created_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE base_item_id=VALUES(base_item_id),name=VALUES(name),quality=VALUES(quality),icon_file_data_id=VALUES(icon_file_data_id),payload_json=VALUES(payload_json),created_by=VALUES(created_by)');
        if (!$stmt) throw new RuntimeException('Could not prepare item history: '.$db->error);
        $stmt->bind_param('iisiisi',$p['item_id'],$p['base_item_id'],$p['name'],$p['quality'],$p['icon_file_data_id'],$json,$authorId);
        if(!$stmt->execute()) throw new RuntimeException('Could not save the item history: '.$stmt->error);
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
    return $p;
}

function forever_creator_delete_item($db, $site_db, $itemId)
{
    $history=forever_creator_history_item($site_db,$itemId);
    if(!$history) throw new InvalidArgumentException('Only items created by ForeverCreator can be deleted here.');
    $db->begin_transaction();
    try {
        forever_creator_remove_effects($db,$history['payload']);
        $db->query('DELETE FROM hotfixes.item_x_item_effect WHERE ItemID='.(int)$itemId);
        foreach ([['hotfixes','item_search_name','ID'],['hotfixes','item_sparse','ID'],['hotfixes','item','ID'],['world','item_template_addon','Id']] as $table) $db->query("DELETE FROM `{$table[0]}`.`{$table[1]}` WHERE `{$table[2]}`=".(int)$itemId);
        $db->query('DELETE FROM world.armory_item WHERE id='.(int)$itemId);
        $db->query('DELETE FROM forever_site.creator_items WHERE item_id='.(int)$itemId);
        if ($db->errno) throw new RuntimeException('Could not delete the item history: '.$db->error);
        $db->commit();
    } catch(Throwable $e) { $db->rollback(); throw $e; }
}

