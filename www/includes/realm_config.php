<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

// The four Forever realms. "id" is auth.realmlist.id, "char_db" the realm's characters database,
// "ra_port" its worldserver's Ra.Port, "port" its WorldServerPort.
$realmlist = array (
  0 =>
  array (
    'id' => 70,
    'name' => 'Forever',
    'type' => 'Normal',
    'address' => '127.0.0.1',
    'check_address' => '127.0.0.1',
    'port' => 8085,
    'ra_port' => 3443,
    'char_db' => 'characters',
    'logo' => 'img/forever/realm-crest.svg',
    'player_display' => 'all',
    'show_status' => true,
  ),
  1 =>
  array (
    'id' => 71,
    'name' => 'Forever PvP',
    'type' => 'PvP',
    'address' => '127.0.0.1',
    'check_address' => '127.0.0.1',
    'port' => 8095,
    'ra_port' => 3444,
    'char_db' => 'characters_pvp',
    'logo' => 'img/forever/realm-crest.svg',
    'player_display' => 'all',
    'show_status' => true,
  ),
  2 =>
  array (
    'id' => 72,
    'name' => 'Forever RP',
    'type' => 'RP',
    'address' => '127.0.0.1',
    'check_address' => '127.0.0.1',
    'port' => 8105,
    'ra_port' => 3445,
    'char_db' => 'characters_rp',
    'logo' => 'img/forever/realm-crest.svg',
    'player_display' => 'all',
    'show_status' => true,
  ),
  3 =>
  array (
    'id' => 73,
    'name' => 'Forever Hardcore',
    'type' => 'Hardcore',
    'address' => '127.0.0.1',
    'check_address' => '127.0.0.1',
    'port' => 8115,
    'ra_port' => 3446,
    'char_db' => 'characters_hc',
    'logo' => 'img/forever/realm-crest.svg',
    'player_display' => 'all',
    'show_status' => true,
  ),
);
