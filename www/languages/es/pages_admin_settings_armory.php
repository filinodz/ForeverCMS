<?php
return [
    // Page meta
    'page_title_armory_settings' => 'Configuración de la armería',
    'page_description_armory_settings' => 'Configurar las clasificaciones de la armería y los ajustes de visualización',

    // Errors
    'err_fix_errors' => 'Por favor, corrija los siguientes errores:',
    'err_invalid_csrf' => 'Token CSRF inválido.',
    'err_invalid_display_setting' => 'Valor no válido enviado para "%s". Debe ser "all" o "humans_only".',
    'err_config_dir_not_writable' => 'El directorio de configuración no tiene permisos de escritura: %s',
    'err_write_armory_config' => 'No se puede escribir el archivo de configuración de la armería.',

    // Success
    'msg_armory_saved' => '¡Configuración de la armería guardada con éxito!',

    // Section titles
    'section_armory_config' => 'Filtro de jugadores de las clasificaciones',

    // Labels
    'label_solo_pvp' => 'Clasificación PvP Solo',
    'label_arena_2v2' => 'Clasificación de Arena 2v2',
    'label_arena_3v3' => 'Clasificación de Arena 3v3',
    'label_arena_5v5' => 'Clasificación de Arena 5v5',

    // Options — Solo PvP
    'opt_show_all' => 'Mostrar humanos y bots',
    'opt_show_all_desc' => 'Incluir playerbots en la clasificación PvP Solo.',
    'opt_humans_only' => 'Solo jugadores humanos',
    'opt_humans_only_desc' => 'Filtrar los playerbots de la clasificación PvP Solo.',

    // Options — Arena 2v2 / 3v3 / 5v5
    'opt_show_all_arena_2v2_desc' => 'Incluir equipos con playerbots en la clasificación 2v2.',
    'opt_show_all_arena_3v3_desc' => 'Incluir equipos con playerbots en la clasificación 3v3.',
    'opt_show_all_arena_5v5_desc' => 'Incluir equipos con playerbots en la clasificación 5v5.',
    'opt_humans_only_arena_2v2_desc' => 'Ocultar los equipos cuyo capitán es un playerbot de la clasificación 2v2.',
    'opt_humans_only_arena_3v3_desc' => 'Ocultar los equipos cuyo capitán es un playerbot de la clasificación 3v3.',
    'opt_humans_only_arena_5v5_desc' => 'Ocultar los equipos cuyo capitán es un playerbot de la clasificación 5v5.',

    // Note & button
    'note_armory_config' => 'Estos ajustes controlan si los Playerbots se incluyen en las clasificaciones públicas de PvP y Arena.',
    'btn_save_armory_settings' => 'Guardar la configuración de la armería',
];
