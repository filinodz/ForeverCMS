<?php
return [
    // Page meta
    'page_title_armory_settings' => 'Armory Settings',
    'page_description_armory_settings' => 'Configure Armory Leaderboards and Display Settings',

    // Errors
    'err_fix_errors' => 'Please fix the following errors:',
    'err_invalid_csrf' => 'Invalid CSRF token.',
    'err_invalid_display_setting' => 'Invalid value submitted for "%s". It must be either "all" or "humans_only".',
    'err_config_dir_not_writable' => 'Config directory is not writable: %s',
    'err_write_armory_config' => 'Cannot write armory configuration file.',

    // Success
    'msg_armory_saved' => 'Armory configuration saved successfully!',

    // Section titles
    'section_armory_config' => 'Leaderboard Player Filter',

    // Labels
    'label_solo_pvp' => 'Solo PvP Leaderboard',
    'label_arena_2v2' => 'Arena 2v2 Leaderboard',
    'label_arena_3v3' => 'Arena 3v3 Leaderboard',
    'label_arena_5v5' => 'Arena 5v5 Leaderboard',

    // Options — Solo PvP
    'opt_show_all' => 'Show Humans & Bots',
    'opt_show_all_desc' => 'Include playerbots on the Solo PvP leaderboard.',
    'opt_humans_only' => 'Human Players Only',
    'opt_humans_only_desc' => 'Filter out playerbots from the Solo PvP leaderboard.',

    // Options — Arena 2v2 / 3v3 / 5v5
    'opt_show_all_arena_2v2_desc' => 'Include teams with playerbots in 2v2 standings.',
    'opt_show_all_arena_3v3_desc' => 'Include teams with playerbots in 3v3 standings.',
    'opt_show_all_arena_5v5_desc' => 'Include teams with playerbots in 5v5 standings.',
    'opt_humans_only_arena_2v2_desc' => 'Hide teams whose captain is a playerbot from 2v2 standings.',
    'opt_humans_only_arena_3v3_desc' => 'Hide teams whose captain is a playerbot from 3v3 standings.',
    'opt_humans_only_arena_5v5_desc' => 'Hide teams whose captain is a playerbot from 5v5 standings.',

    // Note & button
    'note_armory_config' => 'These settings control whether Playerbots are included in the public PvP and Arena leaderboards.',
    'btn_save_armory_settings' => 'Save Armory Settings',
];
