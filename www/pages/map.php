<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/forever.php';

$fr = ($langCode ?? 'en') === 'fr';
$t = static fn(string $en, string $frText): string => $fr ? $frText : $en;
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$page_class = 'live-map';
$page_title = $t('Live World Map', 'Carte du monde en direct') . ' — ' . ($site_title_name ?? 'Forever');
$page_meta_description = $t('Follow the adventurers of WoW Forever across Azeroth in near real time.', 'Suivez les aventuriers de WoW Forever à travers Azeroth, presque en temps réel.');
$page_head = '<link rel="stylesheet" href="' . $e($base_path) . 'assets/css/live-map.css?v=20261019-2">';

$enabled = (bool)($live_map_enabled ?? true);
$realms = forever_public_realms();
$selected = $realms[0] ?? null;
foreach ($realms as $realm) {
    if ((int)$realm['id'] === (int)($_GET['realm'] ?? 0)) $selected = $realm;
}

$boot = [
    'lang' => $fr ? 'fr' : 'en',
    'base' => $base_path,
    'realm' => $selected ? (int)$selected['id'] : 0,
    'refresh' => 20,
    'labels' => [
        'world' => $t('World', 'Monde'),
        'online' => $t('online', 'en ligne'),
        'level' => $t('Level', 'Niveau'),
        'instance' => $t('In an instance', 'En instance'),
        'unknownZone' => $t('Unknown location', 'Lieu inconnu'),
        'empty' => $t('No adventurer matches these filters.', 'Aucun aventurier ne correspond à ces filtres.'),
        'nobody' => $t('Azeroth is quiet right now. Log in and you will appear here.', 'Azeroth est calme pour le moment. Connectez-vous et vous apparaîtrez ici.'),
        'updated' => $t('Updated', 'Mis à jour'),
        'error' => $t('The live feed is unavailable. Retrying…', 'Le flux en direct est indisponible. Nouvelle tentative…'),
        'armory' => $t('Open armory', 'Voir l’armurerie'),
        'enter' => $t('Click to open', 'Cliquer pour ouvrir'),
        'bot' => 'Bot',
        'players' => $t('players', 'joueurs'),
        'here' => $t('on this map', 'sur cette carte'),
    ],
];

include_once $project_root . 'includes/header.php';
?>
<main class="lm-page">
  <div class="lm-wrap">
    <header class="lm-head">
      <div>
        <span class="lm-kicker">WoW Forever · <?= $e($t('Live world map', 'Carte du monde en direct')) ?></span>
        <h1><?= $fr ? 'Azeroth, <em>en direct.</em>' : 'Azeroth, <em>live.</em>' ?></h1>
        <p><?= $e($t('Follow every adventurer currently connected, from the docks of Ratchet to the peaks of Hyjal. Hover a zone to explore it, click a hero to find them.', 'Suivez chaque aventurier connecté, des quais de Cabestan aux sommets d’Hyjal. Survolez une zone pour l’explorer, cliquez sur un héros pour le retrouver.')) ?></p>
      </div>
      <dl class="lm-stats" aria-live="polite">
        <div><dt><?= $e($t('Online', 'En ligne')) ?></dt><dd id="lm-count-all">—</dd></div>
        <div class="is-alliance"><dt>Alliance</dt><dd id="lm-count-alliance">—</dd></div>
        <div class="is-horde"><dt><?= $e($t('Horde', 'Horde')) ?></dt><dd id="lm-count-horde">—</dd></div>
      </dl>
    </header>

    <?php if (!$enabled || !$selected): ?>
      <div class="lm-disabled"><span aria-hidden="true">◇</span><h2><?= $e($t('The live map is not available.', 'La carte en direct n’est pas disponible.')) ?></h2><p><?= $e($t('An administrator has disabled it or no realm is public.', 'Un administrateur l’a désactivée ou aucun royaume n’est public.')) ?></p></div>
    <?php else: ?>
    <div class="lm-layout">
      <section class="lm-stage" aria-label="<?= $e($t('World map', 'Carte du monde')) ?>">
        <div class="lm-toolbar">
          <nav class="lm-crumbs" id="lm-crumbs" aria-label="<?= $e($t('Map path', 'Chemin de la carte')) ?>"></nav>
          <div class="lm-live"><span class="lm-pulse" aria-hidden="true"></span><span id="lm-updated"><?= $e($t('Connecting…', 'Connexion…')) ?></span></div>
        </div>
        <div class="lm-frame" id="lm-frame">
          <div class="lm-viewport" id="lm-viewport">
            <div class="lm-canvas" id="lm-canvas">
              <img id="lm-image" alt="" draggable="false">
              <div class="lm-zones" id="lm-zones"></div>
              <div class="lm-markers" id="lm-markers"></div>
            </div>
          </div>
          <div class="lm-hover" id="lm-hover" hidden></div>
          <div class="lm-hint" aria-hidden="true"><?= $e($t('Use Ctrl + scroll to zoom the map', 'Utilisez Ctrl + molette pour zoomer')) ?></div>
          <div class="lm-zoom">
            <button type="button" data-zoom="1" aria-label="<?= $e($t('Zoom in', 'Zoomer')) ?>">+</button>
            <button type="button" data-zoom="-1" aria-label="<?= $e($t('Zoom out', 'Dézoomer')) ?>">−</button>
            <button type="button" data-zoom="0" aria-label="<?= $e($t('Reset view', 'Recentrer')) ?>">⟲</button>
          </div>
          <div class="lm-legend"><span class="is-alliance">Alliance</span><span class="is-horde"><?= $e($t('Horde', 'Horde')) ?></span><span class="is-bot">Bots</span></div>
        </div>
        <p class="lm-note"><?= $e($t('Positions are those last saved by the server (about every 90 seconds). Game masters are never shown.', 'Les positions sont celles enregistrées par le serveur (environ toutes les 90 secondes). Les maîtres du jeu ne sont jamais affichés.')) ?></p>
      </section>

      <aside class="lm-panel">
        <?php if (count($realms) > 1): ?>
        <label class="lm-field"><span><?= $e($t('Realm', 'Royaume')) ?></span>
          <select id="lm-realm"><?php foreach ($realms as $realm): ?><option value="<?= (int)$realm['id'] ?>" <?= (int)$realm['id'] === (int)$selected['id'] ? 'selected' : '' ?>><?= $e($realm['name']) ?></option><?php endforeach; ?></select>
        </label>
        <?php endif; ?>
        <label class="lm-field"><span><?= $e($t('Find an adventurer', 'Trouver un aventurier')) ?></span><input id="lm-search" type="search" maxlength="24" autocomplete="off" placeholder="<?= $e($t('Character name…', 'Nom du personnage…')) ?>"></label>
        <div class="lm-filters" role="group" aria-label="<?= $e($t('Faction', 'Faction')) ?>">
          <button type="button" data-faction="all" aria-pressed="true"><?= $e($t('All', 'Tous')) ?></button>
          <button type="button" data-faction="alliance" aria-pressed="false">Alliance</button>
          <button type="button" data-faction="horde" aria-pressed="false"><?= $e($t('Horde', 'Horde')) ?></button>
        </div>
        <label class="lm-toggle"><input type="checkbox" id="lm-bots" checked> <span><?= $e($t('Show bots', 'Afficher les bots')) ?></span></label>
        <div class="lm-zone-list" id="lm-zone-list"></div>
        <ol class="lm-roster" id="lm-roster" aria-live="polite"></ol>
      </aside>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php if ($enabled && $selected): ?>
<script id="lm-boot" type="application/json"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= $e($base_path) ?>assets/js/pages/live-map.js?v=20261019-2" defer></script>
<?php endif; ?>
<?php include_once $project_root . 'includes/footer.php'; ?>
