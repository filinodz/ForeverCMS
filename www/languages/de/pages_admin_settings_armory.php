<?php
return [
    // Page meta
    'page_title_armory_settings' => 'Waffenkammer-Einstellungen',
    'page_description_armory_settings' => 'Waffenkammer-Bestenlisten und Anzeigeeinstellungen konfigurieren',

    // Errors
    'err_fix_errors' => 'Bitte beheben Sie die folgenden Fehler:',
    'err_invalid_csrf' => 'Ungültiges CSRF-Token.',
    'err_invalid_display_setting' => 'Ungültiger Wert für "%s" übermittelt. Er muss entweder "all" oder "humans_only" sein.',
    'err_config_dir_not_writable' => 'Konfigurationsverzeichnis ist nicht beschreibbar: %s',
    'err_write_armory_config' => 'Waffenkammer-Konfigurationsdatei konnte nicht geschrieben werden.',

    // Success
    'msg_armory_saved' => 'Waffenkammer-Konfiguration erfolgreich gespeichert!',

    // Section titles
    'section_armory_config' => 'Spielerfilter der Bestenlisten',

    // Labels
    'label_solo_pvp' => 'Solo-PvP-Bestenliste',
    'label_arena_2v2' => 'Arena-2v2-Bestenliste',
    'label_arena_3v3' => 'Arena-3v3-Bestenliste',
    'label_arena_5v5' => 'Arena-5v5-Bestenliste',

    // Options — Solo PvP
    'opt_show_all' => 'Menschen & Bots anzeigen',
    'opt_show_all_desc' => 'Playerbots in die Solo-PvP-Bestenliste aufnehmen.',
    'opt_humans_only' => 'Nur menschliche Spieler',
    'opt_humans_only_desc' => 'Playerbots aus der Solo-PvP-Bestenliste herausfiltern.',

    // Options — Arena 2v2 / 3v3 / 5v5
    'opt_show_all_arena_2v2_desc' => 'Teams mit Playerbots in die 2v2-Bestenliste aufnehmen.',
    'opt_show_all_arena_3v3_desc' => 'Teams mit Playerbots in die 3v3-Bestenliste aufnehmen.',
    'opt_show_all_arena_5v5_desc' => 'Teams mit Playerbots in die 5v5-Bestenliste aufnehmen.',
    'opt_humans_only_arena_2v2_desc' => 'Teams, deren Kapitän ein Playerbot ist, aus der 2v2-Bestenliste ausblenden.',
    'opt_humans_only_arena_3v3_desc' => 'Teams, deren Kapitän ein Playerbot ist, aus der 3v3-Bestenliste ausblenden.',
    'opt_humans_only_arena_5v5_desc' => 'Teams, deren Kapitän ein Playerbot ist, aus der 5v5-Bestenliste ausblenden.',

    // Note & button
    'note_armory_config' => 'Diese Einstellungen steuern, ob Playerbots in den öffentlichen PvP- und Arena-Bestenlisten enthalten sind.',
    'btn_save_armory_settings' => 'Waffenkammer-Einstellungen speichern',
];
