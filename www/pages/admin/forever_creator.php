<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/forever_creator.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','moderator'], true)) {
    header("Location: {$base_path}login"); exit;
}
forever_creator_ensure_schema($site_db);

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_GET['api'] === 'item') {
            $id=(int)($_GET['id'] ?? 0); $item=forever_creator_item($world_db,$id);
            if(!$item) throw new RuntimeException('Item not found in Forever hotfixes.');
            echo json_encode(['ok'=>true,'item'=>$item],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
        }
        if ($_GET['api'] === 'icon') {
            $id=(int)($_GET['id'] ?? 0); $result=$world_db->query('SELECT name FROM armory_icon WHERE fileDataId='.$id.' LIMIT 1');
            $name=$result ? (string)($result->fetch_assoc()['name'] ?? '') : '';
            echo json_encode(['ok'=>true,'name'=>$name]); exit;
        }
        throw new RuntimeException('Unknown request.');
    } catch(Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit; }
}

$flash=''; $flashType='success';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (empty($_POST['csrf_token']) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''),(string)$_POST['csrf_token'])) throw new RuntimeException('Invalid security token. Refresh the page and try again.');
        $operation=(string)($_POST['operation'] ?? 'save');
        if($operation==='delete') {
            forever_creator_delete_item($world_db,$site_db,(int)($_POST['item_id'] ?? 0));
            $_SESSION['creator_flash']='Item deleted from Forever databases and creation history.';
        } else {
            $saved=forever_creator_save_item($world_db,$site_db,$_POST,(int)$_SESSION['user_id']);
            $_SESSION['creator_flash']='Item #'.$saved['item_id'].' saved in hotfixes and the Armory index. Restart worldserver before testing in game.';
        }
        header('Location: '.$base_path.'admin/forever_creator'); exit;
    } catch(Throwable $e) { $flash=$e->getMessage(); $flashType='error'; }
}
if(isset($_SESSION['creator_flash'])) { $flash=(string)$_SESSION['creator_flash']; unset($_SESSION['creator_flash']); }

$editPayload=null;
if(isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $editHistory=forever_creator_history_item($site_db,(int)$_GET['edit']);
    if($editHistory) $editPayload=$editHistory['payload'];
}
$history=[];
$historyResult=$site_db->query('SELECT id,item_id,base_item_id,name,quality,icon_file_data_id,created_by,created_at,updated_at FROM creator_items ORDER BY updated_at DESC LIMIT 100');
if($historyResult) while($row=$historyResult->fetch_assoc()) $history[]=$row;

$page_class='forever-creator'; $page_title='ForeverCreator'; $page_meta_robots='noindex';
$page_body_class='min-h-screen text-[#d8d8d8] bg-[#05070b] bg-fixed';
ob_start();
?>
<style>
body{background-image:radial-gradient(900px 620px at 92% 8%,rgba(34,88,153,.13),transparent 70%),radial-gradient(850px 620px at 0 72%,rgba(153,102,30,.09),transparent 68%),linear-gradient(180deg,#0a0e16,#05070b 52%,#020306)}
.fc-shell{max-width:1580px;margin:0 auto}.fc-brand{font-family:Cinzel,serif;color:#f4d77a;text-shadow:0 2px 16px rgba(201,162,39,.24)}
.fc-panel{background:linear-gradient(155deg,rgba(18,24,35,.96),rgba(6,9,14,.97));border:1px solid rgba(161,178,201,.14);box-shadow:0 18px 48px rgba(0,0,0,.32)}
.fc-section{border-top:1px solid rgba(255,255,255,.065);padding-top:1.25rem;margin-top:1.25rem}.fc-section:first-child{border-top:0;padding-top:0;margin-top:0}
.fc-section-title{font-family:Cinzel,serif;color:#e9c65e;font-size:.82rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase;margin-bottom:.85rem;display:flex;align-items:center;gap:.6rem}.fc-section-title:after{content:'';height:1px;flex:1;background:linear-gradient(90deg,rgba(201,162,39,.28),transparent)}
.fc-label{display:block;color:#9daabd;font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.38rem}.fc-input,.fc-select,.fc-textarea{width:100%;color:#edf1f7;background:#080c13;border:1px solid rgba(135,155,181,.2);padding:.68rem .75rem;outline:none;transition:.18s}.fc-input:focus,.fc-select:focus,.fc-textarea:focus{border-color:rgba(242,207,82,.68);box-shadow:0 0 0 3px rgba(242,207,82,.06)}.fc-select{appearance:none;color-scheme:dark}.fc-select option{background:#090d15;color:#fff}.fc-textarea{min-height:88px;resize:vertical}
.fc-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:.8rem}.fc-col-2{grid-column:span 2}.fc-col-3{grid-column:span 3}.fc-col-4{grid-column:span 4}.fc-col-6{grid-column:span 6}.fc-col-8{grid-column:span 8}.fc-col-12{grid-column:span 12}
.fc-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.72rem 1rem;font-size:.72rem;font-weight:900;letter-spacing:.075em;text-transform:uppercase;border:1px solid transparent;transition:.2s;cursor:pointer}.fc-btn:hover{transform:translateY(-1px)}.fc-btn-gold{color:#171000;background:linear-gradient(#f5d679,#c99f2d 56%,#8c6714);box-shadow:inset 0 1px rgba(255,255,255,.25)}.fc-btn-dark{color:#cdd7e6;background:#0b1018;border-color:rgba(139,158,184,.24)}.fc-btn-danger{color:#ff8e8e;background:rgba(173,42,42,.12);border-color:rgba(230,72,72,.32)}
.fc-stat-row,.fc-effect-row{display:grid;gap:.55rem;margin-bottom:.55rem}.fc-stat-row{grid-template-columns:1.35fr .8fr .8fr}.fc-effect-row{grid-template-columns:.75fr 1fr .65fr .8fr 1.4fr}
.wow-inspect{position:sticky;top:116px;padding:9px;background:linear-gradient(145deg,#50452e,#17140d 30%,#090a0d 70%,#5b4a28);box-shadow:0 28px 64px rgba(0,0,0,.58),0 0 0 1px #806b3b}.wow-inspect-inner{position:relative;min-height:500px;padding:1.25rem;background:radial-gradient(circle at 50% 0,rgba(57,79,104,.22),transparent 40%),linear-gradient(rgba(4,6,9,.97),rgba(8,10,13,.98));border:1px solid #b5974d;box-shadow:inset 0 0 0 2px #17130b,inset 0 0 42px #000}.wow-inspect-inner:before,.wow-inspect-inner:after{content:'◆';position:absolute;color:#b99645;font-size:.62rem}.wow-inspect-inner:before{left:5px;top:3px}.wow-inspect-inner:after{right:5px;bottom:3px}
.wow-icon-frame{width:72px;height:72px;padding:4px;flex:0 0 auto;background:#090b0e;border:2px solid var(--quality,#fff);box-shadow:0 0 18px color-mix(in srgb,var(--quality) 36%,transparent),inset 0 0 12px #000}.wow-icon-frame img{width:100%;height:100%;object-fit:cover;background:#05070b}.wow-tooltip{font-family:'Trebuchet MS',Arial,sans-serif;color:#fff;font-size:13px;line-height:1.42;text-shadow:1px 1px #000}.wow-item-name{font-size:17px;font-weight:800;color:var(--quality,#fff)}.wow-muted{color:#a5acb7}.wow-yellow{color:#ffd100}.wow-green{color:#1eff00}.wow-require{color:#ff7979}.wow-description{color:#ffd100;font-style:italic;margin-top:.55rem}.wow-rule{height:1px;background:linear-gradient(90deg,rgba(201,162,39,.42),transparent);margin:.8rem 0}
.fc-tabs{display:flex;gap:.45rem;overflow:auto;padding-bottom:.2rem}.fc-tab{white-space:nowrap;color:#8290a4;background:#090d14;border:1px solid rgba(132,150,177,.16);padding:.6rem .8rem;font-size:.67rem;font-weight:850;text-transform:uppercase;letter-spacing:.07em}.fc-tab.active,.fc-tab:hover{color:#f6dda0;border-color:rgba(242,207,82,.52);background:rgba(201,162,39,.09)}
.fc-history{width:100%;border-collapse:collapse}.fc-history th,.fc-history td{padding:.8rem;text-align:left;border-bottom:1px solid rgba(255,255,255,.06)}.fc-history th{color:#77869b;font-size:.65rem;text-transform:uppercase;letter-spacing:.08em}.fc-history tr:hover td{background:rgba(242,207,82,.025)}
.fc-quality-dot{width:10px;height:10px;border-radius:50%;display:inline-block;box-shadow:0 0 8px currentColor}.fc-notice{padding:.85rem 1rem;border:1px solid}.fc-notice.success{color:#7de2a5;background:rgba(46,159,93,.09);border-color:rgba(74,205,124,.25)}.fc-notice.error{color:#ff9292;background:rgba(180,43,43,.1);border-color:rgba(231,71,71,.3)}
details.fc-advanced>summary{cursor:pointer;list-style:none;color:#d1b45f;font-weight:800;font-size:.78rem;text-transform:uppercase;letter-spacing:.08em}details.fc-advanced>summary::-webkit-details-marker{display:none}
@media(max-width:1280px){.fc-workspace{grid-template-columns:1fr!important}.wow-inspect{position:relative;top:0}.wow-inspect-inner{min-height:360px}.fc-preview-column{order:-1}}
@media(max-width:800px){.fc-col-2,.fc-col-3,.fc-col-4,.fc-col-6,.fc-col-8{grid-column:span 12}.fc-stat-row,.fc-effect-row{grid-template-columns:1fr 1fr}.fc-history{min-width:760px}}
</style>
<?php $page_head=ob_get_clean(); include $project_root.'includes/header.php'; ?>

<div class="flex relative min-h-screen">
<?php include $project_root.'includes/admin_sidebar.php'; ?>
<main class="main-content-area flex-1 p-3 sm:p-5 lg:p-8 transition-all duration-300 lg:ml-[280px]">
<div class="fc-shell space-y-5">
  <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
    <div><div class="text-[10px] uppercase tracking-[.28em] text-[#77869a] mb-2">Forever content studio</div><h1 class="fc-brand text-3xl md:text-5xl font-black">ForeverCreator</h1><p class="text-[#8290a4] mt-2 max-w-3xl">Create production-ready items from the actual ForeverCore hotfix schema, inspect the result live, and manage every custom item from one place.</p></div>
    <div class="text-xs text-[#6e7b8e] lg:text-right"><strong class="text-[#d9bd68] block">Connected to ForeverRepack</strong>hotfixes.item · item_sparse · Armory index</div>
  </header>
  <?php if($flash): ?><div class="fc-notice <?php echo $flashType; ?>"><?php echo htmlspecialchars($flash,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?>

  <nav class="fc-tabs"><button type="button" class="fc-tab active" data-scroll="identity">Identity</button><button type="button" class="fc-tab" data-scroll="classification">Classification</button><button type="button" class="fc-tab" data-scroll="combat">Combat</button><button type="button" class="fc-tab" data-scroll="stats">Stats</button><button type="button" class="fc-tab" data-scroll="effects">Effects</button><button type="button" class="fc-tab" data-scroll="advanced">Advanced</button><button type="button" class="fc-tab" data-scroll="history">History</button></nav>

  <form method="post" id="creatorForm" class="grid fc-workspace gap-5" style="grid-template-columns:minmax(0,1fr) 390px">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>"><input type="hidden" name="operation" value="save">
    <div class="fc-panel p-4 md:p-6">
      <section class="fc-section" id="identity"><h2 class="fc-section-title"><i class="fas fa-fingerprint"></i> Item identity & template</h2>
        <div class="fc-grid">
          <label class="fc-col-3"><span class="fc-label">New custom item ID</span><input class="fc-input" name="item_id" type="number" min="1" value="900000" required></label>
          <label class="fc-col-3"><span class="fc-label">Base item ID</span><input class="fc-input" name="base_item_id" id="baseItemId" type="number" min="1" value="286977" required></label>
          <div class="fc-col-3 flex items-end"><button class="fc-btn fc-btn-dark w-full" type="button" id="loadTemplate"><i class="fas fa-download"></i> Load template</button></div>
          <label class="fc-col-3"><span class="fc-label">Icon FileDataID</span><input class="fc-input" name="icon_file_data_id" id="iconFileDataId" type="number" value="135312"></label>
          <label class="fc-col-12"><span class="fc-label">Item name</span><input class="fc-input" name="name" value="Forever Blade" maxlength="255" required></label>
          <label class="fc-col-12"><span class="fc-label">Description / flavour text</span><textarea class="fc-textarea" name="description" maxlength="1024">Forged for an adventure without end.</textarea></label>
        </div>
      </section>
      <section class="fc-section" id="classification"><h2 class="fc-section-title"><i class="fas fa-layer-group"></i> Classification</h2>
        <div class="fc-grid">
          <label class="fc-col-3"><span class="fc-label">Quality</span><select class="fc-select" name="quality"><option value="0">Poor</option><option value="1">Common</option><option value="2">Uncommon</option><option value="3">Rare</option><option value="4" selected>Epic</option><option value="5">Legendary</option><option value="6">Artifact</option><option value="7">Heirloom</option></select></label>
          <label class="fc-col-3"><span class="fc-label">Item class</span><select class="fc-select" name="class_id"><option value="0">Consumable</option><option value="1">Container</option><option value="2" selected>Weapon</option><option value="3">Gem</option><option value="4">Armor</option><option value="5">Reagent</option><option value="7">Trade Goods</option><option value="9">Recipe</option><option value="12">Quest</option><option value="15">Miscellaneous</option></select></label>
          <label class="fc-col-3"><span class="fc-label">Subclass ID</span><input class="fc-input" name="subclass_id" type="number" min="0" max="255" value="7"></label>
          <label class="fc-col-3"><span class="fc-label">Inventory slot</span><select class="fc-select" name="inventory_type"><option value="0">Non-equipable</option><option value="1">Head</option><option value="2">Neck</option><option value="3">Shoulder</option><option value="5">Chest</option><option value="6">Waist</option><option value="7">Legs</option><option value="8">Feet</option><option value="9">Wrist</option><option value="10">Hands</option><option value="11">Finger</option><option value="12">Trinket</option><option value="13" selected>One-Hand</option><option value="14">Shield</option><option value="15">Ranged</option><option value="16">Back</option><option value="17">Two-Hand</option><option value="18">Bag</option><option value="20">Robe</option><option value="21">Main Hand</option><option value="22">Off Hand</option><option value="23">Holdable</option></select></label>
          <label class="fc-col-3"><span class="fc-label">Item level</span><input class="fc-input" name="item_level" type="number" min="0" max="65535" value="80"></label>
          <label class="fc-col-3"><span class="fc-label">Required level</span><input class="fc-input" name="required_level" type="number" min="-128" max="127" value="60"></label>
          <label class="fc-col-3"><span class="fc-label">Binding</span><select class="fc-select" name="bonding"><option value="0">None</option><option value="1" selected>Binds when picked up</option><option value="2">Binds when equipped</option><option value="3">Binds when used</option><option value="4">Quest item</option><option value="6">Binds to account</option></select></label>
          <label class="fc-col-3"><span class="fc-label">Allowable classes mask</span><input class="fc-input" name="allowable_class" type="number" value="-1"></label>
        </div>
      </section>
      <section class="fc-section" id="combat"><h2 class="fc-section-title"><i class="fas fa-khanda"></i> Combat, requirements & economy</h2>
        <div class="fc-grid">
          <label class="fc-col-3"><span class="fc-label">Preview min damage</span><input class="fc-input" name="preview_damage_min" type="number" step="0.01" min="0" value="82"></label><label class="fc-col-3"><span class="fc-label">Preview max damage</span><input class="fc-input" name="preview_damage_max" type="number" step="0.01" min="0" value="153"></label><label class="fc-col-3"><span class="fc-label">Weapon delay (ms)</span><input class="fc-input" name="delay" type="number" min="0" max="65535" value="1900"></label><label class="fc-col-3"><span class="fc-label">Preview armor</span><input class="fc-input" name="preview_armor" type="number" min="0" value="0"></label>
          <label class="fc-col-3"><span class="fc-label">Damage type</span><select class="fc-select" name="damage_type"><option value="0">Physical</option><option value="1">Holy</option><option value="2">Fire</option><option value="3">Nature</option><option value="4">Frost</option><option value="5">Shadow</option><option value="6">Arcane</option></select></label><label class="fc-col-3"><span class="fc-label">Damage variance</span><input class="fc-input" name="damage_variance" type="number" step="0.01" value="0"></label><label class="fc-col-3"><span class="fc-label">Buy price (copper)</span><input class="fc-input" name="buy_price" type="number" min="0" value="0"></label><label class="fc-col-3"><span class="fc-label">Sell price (copper)</span><input class="fc-input" name="sell_price" type="number" min="0" value="0"></label>
          <label class="fc-col-3"><span class="fc-label">Required skill</span><input class="fc-input" name="required_skill" type="number" min="0" max="65535" value="0"></label><label class="fc-col-3"><span class="fc-label">Skill rank</span><input class="fc-input" name="required_skill_rank" type="number" min="0" max="65535" value="0"></label><label class="fc-col-3"><span class="fc-label">Required ability</span><input class="fc-input" name="required_ability" type="number" min="0" value="0"></label><label class="fc-col-3"><span class="fc-label">Race masks 1 / 2</span><span class="flex gap-2"><input class="fc-input" name="allowable_race1" type="number" value="-1"><input class="fc-input" name="allowable_race2" type="number" value="-1"></span></label>
        </div>
      </section>
      <section class="fc-section" id="stats"><h2 class="fc-section-title"><i class="fas fa-chart-line"></i> Item stats (10 slots)</h2><div class="text-[11px] text-[#68778c] mb-3">Stat type uses ForeverCore modifier IDs. Value writes the exact StatPercentEditor field and is reflected immediately in the inspection preview.</div>
        <?php for($i=0;$i<10;$i++): ?><div class="fc-stat-row"><select class="fc-select" name="stats[<?php echo $i; ?>][type]"><option value="0">No stat</option><option value="3">Agility</option><option value="4">Strength</option><option value="5">Intellect</option><option value="6">Spirit</option><option value="7">Stamina</option><option value="12">Defense Rating</option><option value="31">Hit Rating</option><option value="32">Critical Strike Rating</option><option value="35">Resilience Rating</option><option value="36">Haste Rating</option><option value="37">Expertise Rating</option><option value="38">Attack Power</option><option value="45">Spell Power</option><option value="50">Armor</option></select><input class="fc-input" name="stats[<?php echo $i; ?>][value]" type="number" value="0" placeholder="Value"><input class="fc-input" name="stats[<?php echo $i; ?>][socket]" type="number" step="0.01" value="0" placeholder="Socket %"></div><?php endfor; ?>
      </section>
      <section class="fc-section" id="effects"><h2 class="fc-section-title"><i class="fas fa-bolt"></i> Spell effects (5 slots)</h2>
        <?php for($i=0;$i<5;$i++): ?><div class="fc-effect-row"><select class="fc-select" name="effects[<?php echo $i; ?>][trigger]"><option value="0">Use</option><option value="1">Equip</option><option value="2">Chance on hit</option></select><input class="fc-input" name="effects[<?php echo $i; ?>][spell]" type="number" value="0" placeholder="Spell ID"><input class="fc-input" name="effects[<?php echo $i; ?>][charges]" type="number" value="0" placeholder="Charges"><span class="flex gap-2"><input class="fc-input" name="effects[<?php echo $i; ?>][cooldown]" type="number" value="-1" title="Cooldown ms"><input class="fc-input" name="effects[<?php echo $i; ?>][category_cooldown]" type="number" value="-1" title="Category cooldown ms"></span><input class="fc-input" name="effects[<?php echo $i; ?>][text]" maxlength="255" placeholder="Tooltip effect text"></div><?php endfor; ?>
      </section>
      <section class="fc-section" id="advanced"><details class="fc-advanced" open><summary><i class="fas fa-sliders-h mr-2"></i> Advanced ForeverCore fields</summary><div class="fc-grid mt-4">
        <label class="fc-col-2"><span class="fc-label">Stack size</span><input class="fc-input" name="stackable" type="number" min="1" value="1"></label><label class="fc-col-2"><span class="fc-label">Max count</span><input class="fc-input" name="max_count" type="number" value="0"></label><label class="fc-col-2"><span class="fc-label">Vendor stack</span><input class="fc-input" name="vendor_stack" type="number" min="1" value="1"></label><label class="fc-col-2"><span class="fc-label">Material</span><input class="fc-input" name="material" type="number" min="0" max="255" value="1"></label><label class="fc-col-2"><span class="fc-label">Sheathe type</span><input class="fc-input" name="sheathe_type" type="number" min="0" max="255" value="3"></label><label class="fc-col-2"><span class="fc-label">Item set</span><input class="fc-input" name="item_set" type="number" min="0" max="65535" value="0"></label>
        <label class="fc-col-2"><span class="fc-label">Lock ID</span><input class="fc-input" name="lock_id" type="number" min="0" max="65535" value="0"></label><label class="fc-col-2"><span class="fc-label">Bag family</span><input class="fc-input" name="bag_family" type="number" min="0" value="0"></label><label class="fc-col-2"><span class="fc-label">Container slots</span><input class="fc-input" name="container_slots" type="number" min="0" max="255" value="0"></label><label class="fc-col-2"><span class="fc-label">Socket bonus</span><input class="fc-input" name="socket_bonus" type="number" min="0" max="65535" value="0"></label><?php for($i=1;$i<=3;$i++): ?><label class="fc-col-2"><span class="fc-label">Socket <?php echo $i; ?> type</span><select class="fc-select" name="socket_type<?php echo $i; ?>"><option value="0">None</option><option value="1">Meta</option><option value="2">Red</option><option value="4">Yellow</option><option value="8">Blue</option></select></label><?php endfor; ?>
        <?php for($i=1;$i<=5;$i++): ?><label class="fc-col-2"><span class="fc-label">Flags <?php echo $i; ?></span><input class="fc-input" name="flags<?php echo $i; ?>" type="number" value="0"></label><?php endfor; ?>
      </div></details></section>
      <div class="fc-section flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3"><p class="text-xs text-[#6f7d90]"><i class="fas fa-database text-[#c9a227] mr-1"></i> Save writes directly to hotfixes + Armory and keeps an editable history.</p><button class="fc-btn fc-btn-gold" type="submit"><i class="fas fa-save"></i> Save to Forever database</button></div>
    </div>

    <aside class="fc-preview-column"><div class="wow-inspect"><div class="wow-inspect-inner"><div class="text-[9px] text-[#8e805e] uppercase tracking-[.18em] mb-4">Live game inspection</div><div class="flex gap-4 items-start mb-4"><div class="wow-icon-frame"><img id="previewIcon" src="https://wow.zamimg.com/images/wow/icons/large/inv_sword_39.jpg" alt=""></div><div class="min-w-0"><div id="previewName" class="wow-item-name">Forever Blade</div><div id="previewId" class="text-[10px] text-[#737d8a] mt-1">Item #900000</div><div id="previewItemLevel" class="wow-yellow text-xs mt-2">Item Level 80</div></div></div><div id="previewTooltip" class="wow-tooltip"></div><div class="wow-rule"></div><div class="text-[10px] text-[#5f6a78]">The preview reads every visible form value in real time. Client display may still require a worldserver restart and cache refresh.</div></div></div></aside>
  </form>

  <section class="fc-panel p-4 md:p-6" id="history"><div class="flex flex-wrap items-end justify-between gap-3 mb-4"><div><h2 class="fc-section-title !mb-1"><i class="fas fa-history"></i> Creation history</h2><p class="text-xs text-[#6d7a8d]">Only entries created here can be edited or removed, protecting original Forever items.</p></div><span class="text-xs text-[#8e9bad]"><?php echo count($history); ?> saved item(s)</span></div><div class="overflow-x-auto"><table class="fc-history"><thead><tr><th>Item</th><th>Base</th><th>Quality</th><th>Created</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
  <?php if(!$history): ?><tr><td colspan="6" class="text-center text-[#68778a] py-8">No custom items yet.</td></tr><?php endif; ?>
  <?php $qualityColors=['#9d9d9d','#fff','#1eff00','#0070dd','#a335ee','#ff8000','#e6cc80','#e6cc80']; foreach($history as $row): ?><tr><td><strong style="color:<?php echo $qualityColors[(int)$row['quality']] ?? '#fff'; ?>"><?php echo htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8'); ?></strong><div class="text-[10px] text-[#66758a]">#<?php echo (int)$row['item_id']; ?></div></td><td class="text-[#8996a8]">#<?php echo (int)$row['base_item_id']; ?></td><td><span class="fc-quality-dot" style="color:<?php echo $qualityColors[(int)$row['quality']] ?? '#fff'; ?>;background:currentColor"></span></td><td class="text-xs text-[#7d899a]"><?php echo htmlspecialchars($row['created_at'],ENT_QUOTES,'UTF-8'); ?></td><td class="text-xs text-[#7d899a]"><?php echo htmlspecialchars($row['updated_at'],ENT_QUOTES,'UTF-8'); ?></td><td class="whitespace-nowrap"><a class="fc-btn fc-btn-dark" href="?edit=<?php echo (int)$row['item_id']; ?>#identity"><i class="fas fa-edit"></i> Edit</a><form method="post" class="inline" onsubmit="return confirm('Delete this custom item from hotfixes, Armory and ForeverCreator history?')"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>"><input type="hidden" name="operation" value="delete"><input type="hidden" name="item_id" value="<?php echo (int)$row['item_id']; ?>"><button class="fc-btn fc-btn-danger" type="submit"><i class="fas fa-trash"></i> Delete</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
  <footer class="text-center text-[10px] uppercase tracking-[.12em] text-[#5d6878] pb-4">ForeverCreator · Made with love by FilinoDZ</footer>
</div></main></div>

<script>
const form=document.getElementById('creatorForm'), q=n=>form.elements[n], qualityColors=['#9d9d9d','#ffffff','#1eff00','#0070dd','#a335ee','#ff8000','#e6cc80','#e6cc80'];
const statNames={3:'Agility',4:'Strength',5:'Intellect',6:'Spirit',7:'Stamina',12:'Defense Rating',31:'Hit Rating',32:'Critical Strike Rating',35:'Resilience Rating',36:'Haste Rating',37:'Expertise Rating',38:'Attack Power',45:'Spell Power',50:'Armor'};
const bindingNames={1:'Binds when picked up',2:'Binds when equipped',3:'Binds when used',4:'Quest Item',6:'Binds to account'};
const invNames={0:'',1:'Head',2:'Neck',3:'Shoulder',5:'Chest',6:'Waist',7:'Legs',8:'Feet',9:'Wrist',10:'Hands',11:'Finger',12:'Trinket',13:'One-Hand',14:'Shield',15:'Ranged',16:'Back',17:'Two-Hand',18:'Bag',20:'Robe',21:'Main Hand',22:'Off Hand',23:'Holdable'};
const classNames={0:'Consumable',1:'Container',2:'Weapon',3:'Gem',4:'Armor',5:'Reagent',7:'Trade Goods',9:'Recipe',12:'Quest',15:'Miscellaneous'};
let iconName='inv_sword_39',iconTimer;
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function val(name){return q(name)?.value??''} function num(name){return Number(val(name))||0}
function money(c){c=Math.max(0,Number(c)||0);return `${Math.floor(c/10000)}g ${Math.floor(c%10000/100)}s ${c%100}c`;}
function updatePreview(){const quality=Math.max(0,Math.min(7,num('quality'))),color=qualityColors[quality];document.querySelector('.wow-inspect').style.setProperty('--quality',color);document.getElementById('previewName').textContent=val('name')||'Unnamed Item';document.getElementById('previewId').textContent=`Item #${val('item_id')||'—'}`;document.getElementById('previewItemLevel').textContent=`Item Level ${num('item_level')}`;const icon=document.getElementById('previewIcon');icon.src=`https://wow.zamimg.com/images/wow/icons/large/${iconName||'inv_misc_questionmark'}.jpg`;icon.onerror=()=>{icon.onerror=null;icon.src='https://wow.zamimg.com/images/wow/icons/large/inv_misc_questionmark.jpg'};
 let lines=[];const bind=bindingNames[num('bonding')];if(bind)lines.push(`<div class="wow-yellow">${bind}</div>`);const inv=invNames[num('inventory_type')]||'',cls=classNames[num('class_id')]||`Class ${num('class_id')}`;lines.push(`<div class="flex justify-between gap-3"><span>${esc(inv)}</span><span>${esc(cls)}</span></div>`);if(num('preview_damage_max')>0){const speed=num('delay')/1000,dps=speed>0?((num('preview_damage_min')+num('preview_damage_max'))/2/speed).toFixed(1):'0';lines.push(`<div class="flex justify-between"><span>${num('preview_damage_min')} - ${num('preview_damage_max')} Damage</span><span>Speed ${speed.toFixed(2)}</span></div><div>(${dps} damage per second)</div>`)}if(num('preview_armor')>0)lines.push(`<div>${num('preview_armor')} Armor</div>`);if(num('required_level')>0)lines.push(`<div class="wow-require">Requires Level ${num('required_level')}</div>`);
 for(let i=0;i<10;i++){const t=Number(form.elements[`stats[${i}][type]`].value),v=Number(form.elements[`stats[${i}][value]`].value);if(t&&v)lines.push(`<div class="wow-green">+${v} ${esc(statNames[t]||`Stat ${t}`)}</div>`)}for(let i=0;i<3;i++){const t=Number(q(`socket_type${i+1}`).value);if(t)lines.push(`<div class="wow-muted">○ ${['','Meta','Red','','Yellow','','','','Blue'][t]||`Socket ${t}`}</div>`)}for(let i=0;i<5;i++){const spell=Number(form.elements[`effects[${i}][spell]`].value),text=form.elements[`effects[${i}][text]`].value;if(spell)lines.push(`<div class="wow-green">${['Use','Equip','Chance on hit'][Number(form.elements[`effects[${i}][trigger]`].value)]||'Effect'}: ${esc(text||`Spell #${spell}`)}</div>`)}if(num('sell_price'))lines.push(`<div class="wow-muted mt-2">Sell Price: ${money(num('sell_price'))}</div>`);if(val('description'))lines.push(`<div class="wow-description">“${esc(val('description'))}”</div>`);document.getElementById('previewTooltip').innerHTML=lines.join('');}
async function resolveIcon(){clearTimeout(iconTimer);iconTimer=setTimeout(async()=>{try{const r=await fetch(`?api=icon&id=${encodeURIComponent(val('icon_file_data_id'))}`),j=await r.json();iconName=j.name||'inv_misc_questionmark';updatePreview()}catch(e){iconName='inv_misc_questionmark';updatePreview()}},250)}
function setField(name,value){const el=q(name);if(el&&value!==undefined&&value!==null)el.value=value}
function applyRaw(x){setField('name',x.Display);setField('description',x.Description);setField('quality',x.OverallQualityID);setField('item_level',x.ItemLevel);setField('required_level',x.RequiredLevel);setField('class_id',x.ClassID);setField('subclass_id',x.SubclassID);setField('inventory_type',x.InventoryType);setField('bonding',x.Bonding);setField('material',x.Material);setField('sheathe_type',x.SheatheType);setField('icon_file_data_id',x.IconFileDataID);setField('stackable',Math.max(1,Number(x.Stackable)||1));['MaxCount','BuyPrice','SellPrice','VendorStackCount','AllowableClass','AllowableRace1','AllowableRace2','RequiredSkill','RequiredSkillRank','RequiredAbility','ItemSet','LockID','BagFamily','ContainerSlots','SocketMatchEnchantmentId','ItemDelay','DamageDamageType','DmgVariance','Flags1','Flags2','Flags3','Flags4','Flags5','SocketType1','SocketType2','SocketType3'].forEach(k=>setField(({MaxCount:'max_count',BuyPrice:'buy_price',SellPrice:'sell_price',VendorStackCount:'vendor_stack',AllowableClass:'allowable_class',AllowableRace1:'allowable_race1',AllowableRace2:'allowable_race2',RequiredSkill:'required_skill',RequiredSkillRank:'required_skill_rank',RequiredAbility:'required_ability',ItemSet:'item_set',LockID:'lock_id',BagFamily:'bag_family',ContainerSlots:'container_slots',SocketMatchEnchantmentId:'socket_bonus',ItemDelay:'delay',DamageDamageType:'damage_type',DmgVariance:'damage_variance',Flags1:'flags1',Flags2:'flags2',Flags3:'flags3',Flags4:'flags4',Flags5:'flags5',SocketType1:'socket_type1',SocketType2:'socket_type2',SocketType3:'socket_type3'})[k],x[k]));for(let i=0;i<10;i++){form.elements[`stats[${i}][type]`].value=x[`StatModifierBonusStat${i+1}`]||0;form.elements[`stats[${i}][value]`].value=x[`StatPercentEditor${i+1}`]||0;form.elements[`stats[${i}][socket]`].value=x[`StatPercentageOfSocket${i+1}`]||0}iconName=x.icon_name||'inv_misc_questionmark';updatePreview()}
function applyPayload(p){Object.entries(p).forEach(([k,v])=>{if(!['stats','effects','_creator_effect_ids'].includes(k))setField(k,v)});(p.stats||[]).forEach((s,i)=>{if(i<10){form.elements[`stats[${i}][type]`].value=s.type||0;form.elements[`stats[${i}][value]`].value=s.value||0;form.elements[`stats[${i}][socket]`].value=s.socket||0}});(p.effects||[]).forEach((e,i)=>{if(i<5)Object.entries(e).forEach(([k,v])=>{const el=form.elements[`effects[${i}][${k}]`];if(el)el.value=v})});resolveIcon();updatePreview()}
document.getElementById('loadTemplate').addEventListener('click',async()=>{const btn=document.getElementById('loadTemplate');btn.disabled=true;btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Loading';try{const r=await fetch(`?api=item&id=${encodeURIComponent(val('base_item_id'))}`),j=await r.json();if(!j.ok)throw new Error(j.error);applyRaw(j.item)}catch(e){alert(e.message)}finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-download"></i> Load template'}});
form.addEventListener('input',e=>{if(e.target.name==='icon_file_data_id')resolveIcon();updatePreview()});form.addEventListener('change',updatePreview);document.querySelectorAll('[data-scroll]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.scroll)?.scrollIntoView({behavior:'smooth',block:'start'})));form.addEventListener('submit',e=>{if(!confirm(`Save item #${val('item_id')} directly to the Forever databases?`))e.preventDefault()});
<?php if($editPayload): ?>applyPayload(<?php echo json_encode($editPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>);<?php else: ?>updatePreview();<?php endif; ?>
</script>
<?php include $project_root.'includes/footer.php'; ?>
