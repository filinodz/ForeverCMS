<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

/**
 * Forever CMS: item data. SahtoutCMS reads world.item_template (3.3.5); Forever's items live in client data,
 * and the repack exports what a website needs to world.armory_item / world.armory_icon (".armory export" on a
 * worldserver; the repack's own armory fills them on first start). Rows are turned into the item_template
 * shape the tooltip code expects.
 */

const FOREVER_ICON_CDN = 'https://wow.zamimg.com/images/wow/icons';

function forever_icon_url($icon, $size = 'large')
{
    $icon = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$icon));
    return FOREVER_ICON_CDN . '/' . $size . '/' . ($icon !== '' ? $icon : 'inv_misc_questionmark') . '.jpg';
}

/** world.armory_item row (with "icon") -> item_template-like row for generateTooltip(). */
function forever_item_row(array $row)
{
    $item = [
        'entry' => (int)$row['id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? '',
        'icon' => $row['icon'] ?? '',
        'Quality' => (int)$row['quality'],
        'Flags' => (int)($row['flags'] ?? 0),
        'ItemLevel' => (int)$row['itemLevel'],
        'RequiredLevel' => max(0, (int)$row['requiredLevel']),
        'SellPrice' => (int)($row['sellPrice'] ?? 0),
        'MaxDurability' => 0,
        'class' => (int)$row['class'],
        'subclass' => (int)$row['subclass'],
        'InventoryType' => (int)$row['inventoryType'],
        'bonding' => (int)$row['bonding'],
        'AllowableClass' => (int)($row['allowableClass'] ?? -1),
        'itemset' => (int)($row['itemSet'] ?? 0),
        'dmg_min1' => round((float)$row['dmgMin']),
        'dmg_max1' => round((float)$row['dmgMax']),
        'delay' => (int)$row['delay'],
        'armor' => (int)$row['armor'],
        'holy_res' => 0, 'fire_res' => 0, 'nature_res' => 0, 'frost_res' => 0, 'shadow_res' => 0, 'arcane_res' => 0,
        'socketBonus' => 0,
        'forever_effects' => [],
    ];

    // stats: "type:value,type:value"; 50 is armor and 51-56 are resistances in the export
    $resistances = [51 => 'fire_res', 52 => 'frost_res', 53 => 'holy_res', 54 => 'shadow_res', 55 => 'nature_res', 56 => 'arcane_res'];
    $slot = 1;
    foreach (array_filter(explode(',', (string)($row['stats'] ?? ''))) as $pair) {
        $parts = explode(':', $pair);
        if (count($parts) !== 2) {
            continue;
        }
        $type = (int)$parts[0];
        $value = (int)$parts[1];
        if ($type === 50) {
            $item['armor'] += $value;
        } elseif (isset($resistances[$type])) {
            $item[$resistances[$type]] += $value;
        } elseif ($slot <= 10) {
            $item["stat_type$slot"] = $type;
            $item["stat_value$slot"] = $value;
            $slot++;
        }
    }

    // effects: "trigger:spellId:text|trigger:spellId:text" (0 use, 1 equip, 2 chance on hit)
    foreach (array_filter(explode('|', (string)($row['effects'] ?? ''))) as $effect) {
        $parts = explode(':', $effect, 3);
        if (count($parts) === 3 && $parts[2] !== '' && in_array((int)$parts[0], [0, 1, 2], true)) {
            $item['forever_effects'][] = ['trigger' => (int)$parts[0], 'spell' => (int)$parts[1], 'text' => $parts[2]];
        }
    }

    return $item;
}

/** Items by id, as item_template-like rows keyed by id. Unknown ids are left out. */
function forever_items($world_db, array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $items = [];
    if (!$ids || !$world_db) {
        return $items;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $world_db->prepare("SELECT a.*, i.name AS icon FROM armory_item a LEFT JOIN armory_icon i ON i.fileDataId = a.iconFileDataId WHERE a.id IN ($placeholders)");
    if (!$stmt) {
        error_log('Forever CMS: armory_item: ' . $world_db->error);
        return $items;
    }
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[(int)$row['id']] = forever_item_row($row);
    }
    $stmt->close();
    return $items;
}

function forever_item($world_db, $id)
{
    return forever_items($world_db, [(int)$id])[(int)$id] ?? null;
}

/** Item ids of an item set, in id order. */
function forever_itemset_entries($world_db, $itemset_id)
{
    $entries = [];
    $stmt = $world_db->prepare('SELECT id, name FROM armory_item WHERE itemSet = ? ORDER BY id');
    if (!$stmt) {
        return $entries;
    }
    $itemset_id = (int)$itemset_id;
    $stmt->bind_param('i', $itemset_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $entries[(int)$row['id']] = $row['name'];
    }
    $stmt->close();
    return $entries;
}
