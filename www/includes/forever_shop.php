<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

require_once __DIR__ . '/forever_items.php';

/**
 * Read the exact catalog used by ForeverCore's in-game shop.
 *
 * world.shop_product is deliberately the only catalog source.  The old
 * forever_site.shop_items table is kept for upgrade compatibility, but it is
 * no longer read by the storefront or the checkout.
 */
function forever_shop_catalog($world_db, $enabled_only = true)
{
    $catalog = [];
    $sql = "SELECT g.ID AS group_id, g.Name AS group_name, g.Ordering AS group_order,
                   p.ID AS product_id, p.Name AS product_name, p.Description,
                   p.IconFileDataID, p.Price, p.NormalPrice, p.Ordering AS product_order,
                   p.Enabled, a.`Index` AS action_index, a.Action, a.Param1, a.Param2
            FROM shop_group g
            JOIN shop_product p ON p.GroupID = g.ID
            LEFT JOIN shop_product_action a ON a.ProductID = p.ID
            " . ($enabled_only ? "WHERE p.Enabled = 1" : "") . "
            ORDER BY g.Ordering, g.ID, p.Ordering, p.ID, a.`Index`";
    $result = $world_db->query($sql);
    if (!$result) {
        error_log('Forever CMS: shop catalog: ' . $world_db->error);
        return $catalog;
    }

    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['product_id'];
        if (!isset($catalog[$id])) {
            $catalog[$id] = [
                'item_id' => $id,
                'group_id' => (int)$row['group_id'],
                'category' => $row['group_name'],
                'group_order' => (int)$row['group_order'],
                'name' => $row['product_name'],
                'description' => $row['Description'],
                'icon_file_data_id' => (int)$row['IconFileDataID'],
                'point_cost' => (int)$row['Price'],
                'normal_price' => (int)$row['NormalPrice'],
                'ordering' => (int)$row['product_order'],
                'token_cost' => 0,
                'stock' => null,
                'level_boost' => null,
                'at_login_flags' => 0,
                'is_set' => 0,
                'itemset_id' => null,
                'sit_entry' => null,
                'image' => null,
                'enabled' => (int)$row['Enabled'],
                'actions' => [],
                'web_purchasable' => true,
            ];
        }
        if ($row['Action'] !== null) {
            $action = [
                'index' => (int)$row['action_index'],
                'type' => (int)$row['Action'],
                'param1' => (int)$row['Param1'],
                'param2' => (int)$row['Param2'],
            ];
            $catalog[$id]['actions'][] = $action;
            if ($action['type'] !== 1) {
                $catalog[$id]['web_purchasable'] = false;
            } elseif ($catalog[$id]['sit_entry'] === null) {
                $catalog[$id]['sit_entry'] = $action['param1'];
            }
        }
    }
    $result->free();

    foreach ($catalog as &$product) {
        if (!$product['actions']) {
            $product['web_purchasable'] = false;
        }
        $product['image'] = forever_shop_fallback_image($product);
    }
    unset($product);
    return $catalog;
}

/**
 * Local artwork for rewards that do not have an item icon in item_sparse.
 * Every returned file ships with ForeverCMS, so services never render a
 * broken placeholder when the external WoW icon CDN is unavailable.
 */
function forever_shop_fallback_image(array $product)
{
    foreach ($product['actions'] ?? [] as $action) {
        switch ((int)$action['type']) {
            case 2:
                return 'img/shopimg/gold/1000_gold.jpg';
            case 4:
                switch ((int)$action['param1']) {
                    case 1: return 'img/shopimg/services/rename.jpg';
                    case 2: return 'img/shopimg/services/gender.jpg';
                    case 3: return 'img/shopimg/services/race_change.jpg';
                    case 4: return 'img/shopimg/services/faction_change.jpg';
                }
                return 'img/shopimg/icons/category_service.gif';
            case 5:
                return 'img/shopimg/icons/category_stuff.jpg';
            case 6:
                return 'img/shopimg/services/level_boost_70.avif';
        }
    }

    switch (strtolower((string)($product['category'] ?? ''))) {
        case 'services':
        case 'service': return 'img/shopimg/icons/category_service.gif';
        case 'mounts':
        case 'mount': return 'img/shopimg/icons/category_mount.jpg';
        case 'companions':
        case 'pet': return 'img/shopimg/icons/category_pet.jpg';
        case 'gold': return 'img/shopimg/icons/category_gold.webp';
        default: return 'img/shopimg/icons/category_stuff.jpg';
    }
}

function forever_shop_product($world_db, $product_id)
{
    $catalog = forever_shop_catalog($world_db, false);
    return $catalog[(int)$product_id] ?? null;
}

/** The wallet shared with the in-game shop. Missing rows mean a zero balance. */
function forever_shop_points($auth_db, $account_id)
{
    $stmt = $auth_db->prepare('SELECT Points FROM account_shop_points WHERE AccountID = ?');
    if (!$stmt) {
        return 0;
    }
    $account_id = (int)$account_id;
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['Points'] : 0;
}

/** Human-readable explanation for products that ForeverCore must execute in game. */
function forever_shop_action_label(array $actions)
{
    $labels = [];
    foreach ($actions as $action) {
        switch ((int)$action['type']) {
            case 1: $labels[] = 'Item'; break;
            case 2: $labels[] = 'Gold'; break;
            case 3: $labels[] = 'Mount / Spell'; break;
            case 4: $labels[] = 'Character Service'; break;
            case 5: $labels[] = 'Profession'; break;
            case 6: $labels[] = 'Level Boost'; break;
            case 7: $labels[] = 'Title'; break;
            case 8: $labels[] = 'Reputation'; break;
            case 9: $labels[] = 'Realm Transfer'; break;
            case 10: $labels[] = 'Boost Token'; break;
            default: $labels[] = 'In-game Reward'; break;
        }
    }
    return implode(' + ', array_values(array_unique($labels)));
}
