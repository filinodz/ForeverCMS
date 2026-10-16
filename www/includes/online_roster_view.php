<?php
if(!defined('ALLOWED_ACCESS'))exit('Forbidden');
$fr=($langCode??'en')==='fr';
$onlineLabels=$fr
 ? ['eyebrow'=>'AZEROTH, EN CE MOMENT','title'=>'Le royaume prend vie.','intro'=>'Retrouvez les aventuriers connectés et inspectez leur équipement dans l’armurerie.','refresh'=>'Actualiser','snapshot'=>'Relevé à','realm'=>'Choisir un royaume','players'=>'Joueurs','bots'=>'Bots','alliance'=>'Alliance','horde'=>'Horde','roster'=>'Les aventuriers','search'=>'Rechercher un nom, une race ou une classe…','all'=>'Tous','people'=>'Joueurs','type'=>'Type de personnage','factions'=>'Toutes les factions','classes'=>'Toutes les classes','results'=>'personnage(s)','none'=>'Aucun aventurier en ligne.','empty'=>'Le royaume est calme pour le moment. Les personnages apparaîtront ici dès leur connexion.','unavailable'=>'Le relevé est indisponible.','try'=>'Réessayez dans un instant ou choisissez un autre royaume.','directory'=>'Parcourir l’armurerie','noresults'=>'Aucun personnage ne correspond à ces filtres.','clear'=>'Effacer les filtres','balance'=>'Les forces en présence','humanonly'=>'Répartition des joueurs, sans les bots.','inspect'=>'Inspecter','level'=>'Niveau','live'=>'Connecté','map'=>'En Azeroth','online'=>'En ligne','realmempty'=>'Aucun royaume public configuré.']
 : ['eyebrow'=>'AZEROTH, RIGHT NOW','title'=>'The realm comes alive.','intro'=>'Find the adventurers online and take a closer look at their equipment in the Armory.','refresh'=>'Refresh','snapshot'=>'Snapshot at','realm'=>'Choose a realm','players'=>'Players','bots'=>'Bots','alliance'=>'Alliance','horde'=>'Horde','roster'=>'The adventurers','search'=>'Search a name, race or class…','all'=>'All','people'=>'Players','type'=>'Character type','factions'=>'All factions','classes'=>'All classes','results'=>'character(s)','none'=>'No adventurers online.','empty'=>'The realm is quiet for now. Characters will appear here as soon as they log in.','unavailable'=>'The roster is unavailable.','try'=>'Try again shortly or choose another realm.','directory'=>'Explore the Armory','noresults'=>'No characters match these filters.','clear'=>'Clear filters','balance'=>'The balance of power','humanonly'=>'Player population, excluding bots.','inspect'=>'Inspect','level'=>'Level','live'=>'Online','map'=>'In Azeroth','online'=>'Online','realmempty'=>'No public realm is configured.'];
$e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$t=static fn($key)=>htmlspecialchars($onlineLabels[$key],ENT_QUOTES,'UTF-8');
$humanCount=count($players)-$bot_count;
$factionTotal=$alliance_count+$horde_count;
$alliancePercent=$factionTotal?round($alliance_count*100/$factionTotal):50;
$selectedId=(int)($selected_realm['id']??0);
$refreshUrl=$base_path.'players_online?realm='.$selectedId.'&lang='.urlencode($langCode);
$classNamesFr=[1=>'Guerrier',2=>'Paladin',3=>'Chasseur',4=>'Voleur',5=>'Prêtre',6=>'Chevalier de la mort',7=>'Chaman',8=>'Mage',9=>'Démoniste',11=>'Druide'];
$raceNamesFr=[1=>'Humain',2=>'Orc',3=>'Nain',4=>'Elfe de la nuit',5=>'Mort-vivant',6=>'Tauren',7=>'Gnome',8=>'Troll',10=>'Elfe de sang',11=>'Draeneï'];
$classLabel=static fn($id)=>$fr?($classNamesFr[$id]??forever_class_name($id)):forever_class_name($id);
$raceLabel=static fn($id)=>$fr?($raceNamesFr[$id]??forever_race_name($id)):forever_race_name($id);
$rosterClasses=[];foreach($players as $p)$rosterClasses[(int)$p['class']]=$classLabel((int)$p['class']);asort($rosterClasses);
?>
<main class="online-directory">
 <header class="od-hero">
  <div class="od-hero-copy"><span class="od-eyebrow"><span class="od-dot"></span><?= $t('eyebrow') ?></span>
   <h1><?= $t('title') ?></h1><p><?= $t('intro') ?></p>
   <div class="od-hero-meta"><span><?= $e($selected_realm['name']??$onlineLabels['realmempty']) ?></span><span class="od-snapshot"><?= $t('snapshot') ?> <time id="rosterSnapshot"><?= gmdate('H:i') ?> UTC</time></span></div>
  </div>
  <div class="od-population"><strong><?= $humanCount ?></strong><span><?= $t('players') ?> <i class="od-dot"></i></span></div>
 </header>
 <?php if(count($public_realms)>1): ?><nav class="od-realms" aria-label="<?= $t('realm') ?>">
 <?php foreach($public_realms as $realm): ?><a href="<?= $e($base_path.'players_online?realm='.(int)$realm['id'].'&lang='.urlencode($langCode)) ?>" class="od-realm <?= (int)$realm['id']===$selectedId?'is-active':'' ?>" <?= (int)$realm['id']===$selectedId?'aria-current="page"':'' ?>><?= $e($realm['name']) ?><span><?= $e($realm['type']??'') ?></span></a><?php endforeach; ?>
 </nav><?php endif; ?>
 <section class="od-metrics" aria-label="<?= $t('online') ?>">
  <?php foreach([['players',$humanCount,'gold'],['bots',$bot_count,'slate'],['alliance',$alliance_count,'blue'],['horde',$horde_count,'red']] as [$label,$number,$tone]): ?><div class="od-metric od-<?= $tone ?>"><span><?= $t($label) ?></span><strong><?= (int)$number ?></strong><i aria-hidden="true"></i></div><?php endforeach; ?>
 </section>
 <div class="od-layout">
  <section class="od-roster" aria-labelledby="rosterTitle">
   <div class="od-section-head"><h2 id="rosterTitle"><?= $t('roster') ?></h2><a class="od-refresh" href="<?= $e($refreshUrl) ?>" id="rosterRefresh"><span aria-hidden="true">↻</span> <?= $t('refresh') ?></a></div>
   <div class="od-toolbar">
    <label class="od-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><span class="sr-only"><?= $t('search') ?></span><input id="rosterSearch" type="search" placeholder="<?= $t('search') ?>" autocomplete="off"></label>
    <div class="od-filter-row"><div class="od-segments" role="group" aria-label="<?= $t('type') ?>"><?php foreach(['all'=>'all','player'=>'people','bot'=>'bots'] as $kind=>$label): ?><button type="button" data-presence-filter="<?= $kind ?>" aria-pressed="<?= $kind==='all'?'true':'false' ?>"><?= $t($label) ?></button><?php endforeach; ?></div>
     <label class="sr-only" for="rosterFaction"><?= $t('factions') ?></label><select id="rosterFaction"><option value="all"><?= $t('factions') ?></option><option value="alliance"><?= $t('alliance') ?></option><option value="horde"><?= $t('horde') ?></option></select>
     <label class="sr-only" for="rosterClass"><?= $t('classes') ?></label><select id="rosterClass"><option value="all"><?= $t('classes') ?></option><?php foreach($rosterClasses as $id=>$name): ?><option value="<?= (int)$id ?>"><?= $e($name) ?></option><?php endforeach; ?></select>
    </div>
   </div>
   <p class="od-result-count" role="status" aria-live="polite"><span id="rosterCount"><?= count($players) ?></span> <?= $t('results') ?></p>
   <?php if(!$selected_realm||!$database_available||!$players): ?>
    <div class="od-empty"><svg viewBox="0 0 64 64" aria-hidden="true"><path d="M18 53V22h28v31M12 53h40M24 22v-9h16v9M28 13V7h8v6M27 53V39h10v14M25 30h2m10 0h2"/></svg><h3><?= $t(!$database_available?'unavailable':'none') ?></h3><p><?= $t(!$database_available?'try':'empty') ?></p><a href="<?= $e($base_path.'armory/?realm='.$selectedId.'&lang='.urlencode($langCode)) ?>"><?= $t('directory') ?> <span aria-hidden="true">→</span></a></div>
   <?php endif; ?>
   <div id="rosterGrid" class="od-grid">
    <?php foreach($players as $player):
     $classId=(int)$player['class'];$className=$classLabel($classId);$raceName=$raceLabel((int)$player['race']);$color=$class_colors[$classId]??'#c9b078';$faction=forever_is_alliance((int)$player['race'])?'alliance':'horde';$kind=empty($player['is_bot'])?'player':'bot';
     $portrait=$base_path.'img/accountimg/race/'.forever_race_icon_file((int)$player['race'],(int)$player['gender']);$location=$map_names[(int)$player['map']]??$onlineLabels['map'];
     $url=$base_path.'character?guid='.(int)$player['guid'].'&realm='.$selectedId.'&lang='.urlencode($langCode);
    ?><a href="<?= $e($url) ?>" class="od-player" style="--player-color:<?= $e($color) ?>" data-presence-type="<?= $kind ?>" data-player-faction="<?= $faction ?>" data-player-class="<?= $classId ?>" data-player-search="<?= $e($player['name'].' '.$className.' '.$raceName.' '.$onlineLabels[$faction]) ?>">
     <span class="od-portrait"><img src="<?= $e($portrait) ?>" alt="<?= $e($raceName) ?>" loading="lazy"><span class="od-level" title="<?= $t('level') ?>"><?= (int)$player['level'] ?></span></span>
     <span class="od-player-info"><span class="od-player-heading"><strong><?= $e($player['name']) ?></strong><?php if($kind==='bot'): ?><small class="od-bot">BOT</small><?php else: ?><i class="od-dot" title="<?= $t('live') ?>"></i><?php endif; ?></span>
      <span class="od-player-class"><?php if(isset($class_icons[$classId])): ?><img src="<?= $e($base_path.'img/accountimg/class/'.$class_icons[$classId]) ?>" alt="" loading="lazy"><?php endif; ?><?= $e($className) ?></span><span class="od-player-race"><?= $e($raceName) ?> <i>·</i> <span class="od-faction-<?= $faction ?>"><?= $t($faction) ?></span></span>
     </span><span class="od-inspect" aria-hidden="true">↗</span><span class="od-player-foot"><span><?= $e($location) ?></span><span><?= $t('inspect') ?> →</span></span>
    </a><?php endforeach; ?>
   </div>
   <div id="rosterNoResults" class="od-no-results" hidden><p><?= $t('noresults') ?></p><button type="button" id="rosterClear"><?= $t('clear') ?></button></div>
  </section>
  <aside class="od-sidebar"><div class="od-balance"><span class="od-eyebrow">ALLIANCE / HORDE</span><h2><?= $t('balance') ?></h2><div class="od-faction-totals"><span class="od-faction-alliance"><?= $alliance_count ?><small><?= $t('alliance') ?></small></span><span class="od-faction-horde"><?= $horde_count ?><small><?= $t('horde') ?></small></span></div><div class="od-balance-bar" aria-hidden="true"><span style="width:<?= $alliancePercent ?>%"></span></div><p><?= $t('humanonly') ?></p></div><a class="od-armory-link" href="<?= $e($base_path.'armory/?realm='.$selectedId.'&lang='.urlencode($langCode)) ?>"><span><?= $t('directory') ?></span><strong>→</strong></a></aside>
 </div>
</main>
<script src="<?= $e($base_path) ?>assets/js/pages/players-online.js?v=20261016-1" defer></script>
