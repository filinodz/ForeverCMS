<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/forever_shop.php';
require_once $project_root . 'includes/ra_client.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'], true)) {
    header("Location: {$base_path}login");
    exit;
}

$page_class = 'ashop';
$action_names = [
    1 => 'Item', 2 => 'Gold (copper)', 3 => 'Spell / Mount', 4 => 'Character Service',
    5 => 'Profession', 6 => 'Level Boost', 7 => 'Title', 8 => 'Reputation',
    9 => 'Realm Transfer', 10 => 'Boost Token',
];

$groups = [];
$result = $world_db->query('SELECT ID, Name, IconFileDataID, Ordering FROM shop_group ORDER BY Ordering, ID');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $groups[(int)$row['ID']] = $row;
    }
    $result->free();
}

$redirect = function ($status, $message) use ($base_path) {
    header("Location: {$base_path}admin/ashop?status=" . rawurlencode($status) . '&message=' . rawurlencode($message));
    exit;
};
$reload_shop = function () {
    foreach (forever_realms() as $realm) {
        forever_realm_ra((int)$realm['id'], 'shop reload');
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $redirect('error', 'CSRF validation failed.');
    }
    $operation = (string)($_POST['operation'] ?? '');
    if ($operation === 'save') {
        $product_id = max(0, (int)($_POST['product_id'] ?? 0));
        $group_id = (int)($_POST['group_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $icon = max(0, (int)($_POST['icon_file_data_id'] ?? 0));
        $price = max(0, (int)($_POST['price'] ?? 0));
        $normal_price = max(0, (int)($_POST['normal_price'] ?? 0));
        $ordering = (int)($_POST['ordering'] ?? 0);
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        $action_type = (int)($_POST['action_type'] ?? 0);
        $param1 = (int)($_POST['param1'] ?? 0);
        $param2 = (int)($_POST['param2'] ?? 0);

        if (!isset($groups[$group_id]) || $name === '' || strlen($name) > 255 || !isset($action_names[$action_type])) {
            $redirect('error', 'Please check the required product fields.');
        }
        if ($action_type === 1 && !forever_item($world_db, $param1)) {
            $redirect('error', 'This item ID does not exist in world.armory_item.');
        }
        if ($action_type === 1 && $param2 < 1) {
            $param2 = 1;
        }

        $world_db->begin_transaction();
        try {
            if ($product_id === 0) {
                $row = $world_db->query('SELECT COALESCE(MAX(ID), 0) + 1 AS next_id FROM shop_product')->fetch_assoc();
                $product_id = max(1, (int)$row['next_id']);
                $stmt = $world_db->prepare('INSERT INTO shop_product (ID, GroupID, Name, Description, IconFileDataID, Price, NormalPrice, Ordering, Enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iissiiiii', $product_id, $group_id, $name, $description, $icon, $price, $normal_price, $ordering, $enabled);
            } else {
                $stmt = $world_db->prepare('UPDATE shop_product SET GroupID=?, Name=?, Description=?, IconFileDataID=?, Price=?, NormalPrice=?, Ordering=?, Enabled=? WHERE ID=?');
                $stmt->bind_param('issiiiiii', $group_id, $name, $description, $icon, $price, $normal_price, $ordering, $enabled, $product_id);
            }
            if (!$stmt || !$stmt->execute()) {
                throw new Exception($stmt ? $stmt->error : $world_db->error);
            }
            $stmt->close();

            // The admin UI intentionally manages one atomic action per product.
            // Existing multi-action products remain supported by the storefront;
            // saving one here converts it to one clearly defined action.
            $stmt = $world_db->prepare('DELETE FROM shop_product_action WHERE ProductID = ?');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $stmt->close();
            $index = 0;
            $stmt = $world_db->prepare('INSERT INTO shop_product_action (ProductID, `Index`, Action, Param1, Param2) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiiii', $product_id, $index, $action_type, $param1, $param2);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $stmt->close();
            $world_db->commit();
            $reload_shop();
            $redirect('success', 'Shared shop product saved and reloaded in game.');
        } catch (Throwable $e) {
            $world_db->rollback();
            error_log('Forever shared shop admin: ' . $e->getMessage());
            $redirect('error', 'The shared shop product could not be saved.');
        }
    } elseif ($operation === 'delete') {
        $product_id = max(0, (int)($_POST['product_id'] ?? 0));
        $world_db->begin_transaction();
        try {
            $stmt = $world_db->prepare('DELETE FROM shop_product_action WHERE ProductID = ?');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $world_db->prepare('DELETE FROM shop_product WHERE ID = ?');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $stmt->close();
            $world_db->commit();
            $reload_shop();
            $redirect('success', 'Product removed from both shops.');
        } catch (Throwable $e) {
            $world_db->rollback();
            $redirect('error', 'The product could not be removed.');
        }
    } elseif ($operation === 'reload') {
        $reload_shop();
        $redirect('success', 'The in-game shop cache has been reloaded.');
    }
}

$catalog = forever_shop_catalog($world_db, false);
$edit_id = max(0, (int)($_GET['edit'] ?? 0));
$edit = $edit_id && isset($catalog[$edit_id]) ? $catalog[$edit_id] : null;
$edit_action = $edit && $edit['actions'] ? $edit['actions'][0] : ['type' => 1, 'param1' => 0, 'param2' => 1];
$status = (string)($_GET['status'] ?? '');
$message = trim((string)($_GET['message'] ?? ''));

$page_title = 'Shared Shop Management';
$page_meta_description = 'ForeverCMS and in-game shared shop management';
$page_meta_robots = 'noindex';
$page_body_class = 'shop';
ob_start();
?>
<style>
body{background:#05070b;color:#d8d8d8}.shop-admin{max-width:1400px;margin:auto}.wow-panel{background:linear-gradient(160deg,rgba(20,25,36,.96),rgba(7,9,14,.96));border:1px solid rgba(242,207,91,.24);box-shadow:0 18px 55px rgba(0,0,0,.45)}
.shop-input{width:100%;background:#080b12;border:1px solid rgba(242,207,91,.25);color:#fff;padding:.72rem .85rem;outline:none}.shop-input:focus{border-color:#f2cf5b;box-shadow:0 0 0 2px rgba(242,207,91,.1)}select.shop-input option{background:#0b0f18;color:#fff}.shop-label{display:block;color:#f2cf5b;font-weight:700;font-size:.8rem;margin-bottom:.4rem;letter-spacing:.04em}.shop-table{width:100%;border-collapse:collapse}.shop-table th,.shop-table td{padding:.8rem;border-bottom:1px solid rgba(255,255,255,.07);text-align:left}.shop-table th{color:#f2cf5b;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em}.shop-table tr:hover td{background:rgba(242,207,91,.035)}
</style>
<?php $page_head = ob_get_clean(); include $project_root . 'includes/header.php'; ?>
<div class="flex relative min-h-screen">
<?php include $project_root . 'includes/admin_sidebar.php'; ?>
<main class="flex-1 p-4 md:p-8 lg:ml-[280px]">
<div class="shop-admin space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div><p class="text-xs tracking-[.25em] text-[#f2cf5b]">FOREVERCORE · FOREVERCMS</p><h1 class="text-3xl md:text-4xl font-bold text-white">Shared Shop</h1><p class="text-gray-400 mt-2">One catalog, one price and one Shop Points wallet for the website and the game.</p></div>
    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><input type="hidden" name="operation" value="reload"><button class="px-4 py-2 border border-sky-400/40 text-sky-300 bg-sky-500/10"><i class="fas fa-sync-alt mr-2"></i>Reload in game</button></form>
  </div>
  <?php if ($message !== ''): ?><div class="p-4 border <?php echo $status === 'success' ? 'border-green-500/40 bg-green-900/20 text-green-200' : 'border-red-500/40 bg-red-900/20 text-red-200'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

  <section class="wow-panel p-5 md:p-7">
    <h2 class="text-xl font-bold text-white mb-5"><?php echo $edit ? 'Edit product #' . (int)$edit['item_id'] : 'Add a shared product'; ?></h2>
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><input type="hidden" name="operation" value="save"><input type="hidden" name="product_id" value="<?php echo $edit ? (int)$edit['item_id'] : 0; ?>">
      <div><label class="shop-label">GROUP</label><select class="shop-input" name="group_id" required><?php foreach ($groups as $id=>$group): ?><option value="<?php echo $id; ?>" <?php echo $edit && $edit['group_id']===$id?'selected':''; ?>><?php echo htmlspecialchars($group['Name']); ?></option><?php endforeach; ?></select></div>
      <div class="xl:col-span-2"><label class="shop-label">PRODUCT NAME</label><input class="shop-input" name="name" maxlength="255" required value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>"></div>
      <div><label class="shop-label">SHOP POINTS</label><input class="shop-input" type="number" min="0" name="price" required value="<?php echo (int)($edit['point_cost'] ?? 0); ?>"></div>
      <div class="md:col-span-2 xl:col-span-3"><label class="shop-label">DESCRIPTION</label><input class="shop-input" name="description" maxlength="2000" value="<?php echo htmlspecialchars($edit['description'] ?? ''); ?>"></div>
      <div><label class="shop-label">NORMAL PRICE (SALE DISPLAY)</label><input class="shop-input" type="number" min="0" name="normal_price" value="<?php echo (int)($edit['normal_price'] ?? 0); ?>"></div>
      <div><label class="shop-label">ACTION</label><select class="shop-input" name="action_type" id="actionType"><?php foreach($action_names as $id=>$label): ?><option value="<?php echo $id; ?>" <?php echo (int)$edit_action['type']===$id?'selected':''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
      <div><label class="shop-label">PARAMETER 1</label><input class="shop-input" type="number" name="param1" id="param1" value="<?php echo (int)$edit_action['param1']; ?>"><p class="text-xs text-gray-500 mt-1" id="paramHint">Item ID for Item actions.</p></div>
      <div><label class="shop-label">PARAMETER 2</label><input class="shop-input" type="number" name="param2" value="<?php echo (int)$edit_action['param2']; ?>"><p class="text-xs text-gray-500 mt-1">Item count or action-specific value.</p></div>
      <div><label class="shop-label">ICON FILE DATA ID</label><input class="shop-input" type="number" min="0" name="icon_file_data_id" value="<?php echo (int)($edit['icon_file_data_id'] ?? 0); ?>"></div>
      <div><label class="shop-label">ORDER</label><input class="shop-input" type="number" name="ordering" value="<?php echo (int)($edit['ordering'] ?? 0); ?>"></div>
      <div class="flex items-center gap-3 pt-6"><input id="enabled" type="checkbox" name="enabled" value="1" <?php echo !$edit || $edit['enabled'] ? 'checked' : ''; ?>><label for="enabled" class="text-white">Enabled in both shops</label></div>
      <div class="md:col-span-2 xl:col-span-4 flex gap-3 pt-2"><button class="px-5 py-3 bg-[#f2cf5b] text-black font-bold"><i class="fas fa-save mr-2"></i>Save & reload</button><?php if($edit): ?><a class="px-5 py-3 border border-white/20 text-gray-300" href="<?php echo $base_path; ?>admin/ashop">Cancel</a><?php endif; ?></div>
    </form>
  </section>

  <section class="wow-panel overflow-x-auto">
    <table class="shop-table min-w-[900px]"><thead><tr><th>ID</th><th>Group</th><th>Product</th><th>Action</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach($catalog as $product): ?><tr><td>#<?php echo (int)$product['item_id']; ?></td><td><?php echo htmlspecialchars($product['category']); ?></td><td><strong class="text-white"><?php echo htmlspecialchars($product['name']); ?></strong><div class="text-xs text-gray-500 max-w-xl"><?php echo htmlspecialchars($product['description']); ?></div></td><td><?php echo htmlspecialchars(forever_shop_action_label($product['actions'])); ?></td><td class="text-[#f2cf5b] font-bold"><?php echo (int)$product['point_cost']; ?></td><td><?php echo $product['enabled']?'<span class="text-green-400">Enabled</span>':'<span class="text-gray-500">Hidden</span>'; ?></td><td class="whitespace-nowrap"><a class="text-sky-300 mr-3" href="?edit=<?php echo (int)$product['item_id']; ?>">Edit</a><form method="post" class="inline" onsubmit="return confirm('Delete this product from both shops?')"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><input type="hidden" name="operation" value="delete"><input type="hidden" name="product_id" value="<?php echo (int)$product['item_id']; ?>"><button class="text-red-400">Delete</button></form></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</div></main></div>
<script>const hints={1:'Item ID; Parameter 2 is the quantity.',2:'Copper amount; Parameter 2 is unused.',3:'Spell ID; Parameter 2 is unused.',4:'Service: 1 rename, 2 appearance, 3 race, 4 faction.',5:'Profession skill ID; Parameter 2 is the target value.',6:'Target level; Parameter 2 is unused.',7:'Title ID.',8:'Faction ID; Parameter 2 is reputation value.',9:'Destination realm ID.',10:'Boost token type.'};const at=document.getElementById('actionType'),hint=document.getElementById('paramHint');function h(){hint.textContent=hints[at.value]||''}at.addEventListener('change',h);h();</script>
</body></html>
