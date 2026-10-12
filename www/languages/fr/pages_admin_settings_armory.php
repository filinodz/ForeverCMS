<?php
return [
    // Page meta
    'page_title_armory_settings' => 'Paramètres de l\'armurerie',
    'page_description_armory_settings' => 'Configurer les classements de l\'armurerie et les paramètres d\'affichage',

    // Errors
    'err_fix_errors' => 'Veuillez corriger les erreurs suivantes :',
    'err_invalid_csrf' => 'Jeton CSRF invalide.',
    'err_invalid_display_setting' => 'Valeur invalide soumise pour "%s". Elle doit être soit "all", soit "humans_only".',
    'err_config_dir_not_writable' => 'Le répertoire de configuration n\'est pas accessible en écriture : %s',
    'err_write_armory_config' => 'Impossible d\'écrire le fichier de configuration de l\'armurerie.',

    // Success
    'msg_armory_saved' => 'Configuration de l\'armurerie enregistrée avec succès !',

    // Section titles
    'section_armory_config' => 'Filtre de joueurs des classements',

    // Labels
    'label_solo_pvp' => 'Classement PvP Solo',
    'label_arena_2v2' => 'Classement d\'arène 2v2',
    'label_arena_3v3' => 'Classement d\'arène 3v3',
    'label_arena_5v5' => 'Classement d\'arène 5v5',

    // Options — Solo PvP
    'opt_show_all' => 'Afficher les humains et les bots',
    'opt_show_all_desc' => 'Inclure les playerbots dans le classement PvP Solo.',
    'opt_humans_only' => 'Joueurs humains uniquement',
    'opt_humans_only_desc' => 'Exclure les playerbots du classement PvP Solo.',

    // Options — Arena 2v2 / 3v3 / 5v5
    'opt_show_all_arena_2v2_desc' => 'Inclure les équipes avec des playerbots dans le classement 2v2.',
    'opt_show_all_arena_3v3_desc' => 'Inclure les équipes avec des playerbots dans le classement 3v3.',
    'opt_show_all_arena_5v5_desc' => 'Inclure les équipes avec des playerbots dans le classement 5v5.',
    'opt_humans_only_arena_2v2_desc' => 'Masquer les équipes dont le capitaine est un playerbot du classement 2v2.',
    'opt_humans_only_arena_3v3_desc' => 'Masquer les équipes dont le capitaine est un playerbot du classement 3v3.',
    'opt_humans_only_arena_5v5_desc' => 'Masquer les équipes dont le capitaine est un playerbot du classement 5v5.',

    // Note & button
    'note_armory_config' => 'Ces paramètres déterminent si les Playerbots sont inclus dans les classements PvP et Arène publics.',
    'btn_save_armory_settings' => 'Enregistrer les paramètres de l\'armurerie',
];
