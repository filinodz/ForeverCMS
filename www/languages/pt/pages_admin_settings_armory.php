<?php
return [
    // Page meta
    'page_title_armory_settings' => 'Configurações do Arsenal',
    'page_description_armory_settings' => 'Configurar as classificações do Arsenal e as configurações de exibição',

    // Errors
    'err_fix_errors' => 'Por favor, corrija os seguintes erros:',
    'err_invalid_csrf' => 'Token CSRF inválido.',
    'err_invalid_display_setting' => 'Valor inválido enviado para "%s". Deve ser "all" ou "humans_only".',
    'err_config_dir_not_writable' => 'O diretório de configuração não tem permissão de escrita: %s',
    'err_write_armory_config' => 'Não foi possível gravar o arquivo de configuração do Arsenal.',

    // Success
    'msg_armory_saved' => 'Configuração do Arsenal salva com sucesso!',

    // Section titles
    'section_armory_config' => 'Filtro de jogadores das classificações',

    // Labels
    'label_solo_pvp' => 'Classificação PvP Solo',
    'label_arena_2v2' => 'Classificação de Arena 2v2',
    'label_arena_3v3' => 'Classificação de Arena 3v3',
    'label_arena_5v5' => 'Classificação de Arena 5v5',

    // Options — Solo PvP
    'opt_show_all' => 'Mostrar humanos e bots',
    'opt_show_all_desc' => 'Incluir playerbots na classificação PvP Solo.',
    'opt_humans_only' => 'Apenas jogadores humanos',
    'opt_humans_only_desc' => 'Filtrar playerbots da classificação PvP Solo.',

    // Options — Arena 2v2 / 3v3 / 5v5
    'opt_show_all_arena_2v2_desc' => 'Incluir equipes com playerbots na classificação 2v2.',
    'opt_show_all_arena_3v3_desc' => 'Incluir equipes com playerbots na classificação 3v3.',
    'opt_show_all_arena_5v5_desc' => 'Incluir equipes com playerbots na classificação 5v5.',
    'opt_humans_only_arena_2v2_desc' => 'Ocultar da classificação 2v2 as equipes cujo capitão é um playerbot.',
    'opt_humans_only_arena_3v3_desc' => 'Ocultar da classificação 3v3 as equipes cujo capitão é um playerbot.',
    'opt_humans_only_arena_5v5_desc' => 'Ocultar da classificação 5v5 as equipes cujo capitão é um playerbot.',

    // Note & button
    'note_armory_config' => 'Essas configurações controlam se os Playerbots são incluídos nas classificações públicas de PvP e Arena.',
    'btn_save_armory_settings' => 'Salvar configurações do Arsenal',
];
