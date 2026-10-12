<?php
return [
    // Page meta
    'page_title_armory_settings' => 'Настройки Оружейной',
    'page_description_armory_settings' => 'Настройка таблиц лидеров Оружейной и параметров отображения',

    // Errors
    'err_fix_errors' => 'Пожалуйста, исправьте следующие ошибки:',
    'err_invalid_csrf' => 'Недействительный CSRF-токен.',
    'err_invalid_display_setting' => 'Отправлено недопустимое значение для "%s". Допустимы только "all" или "humans_only".',
    'err_config_dir_not_writable' => 'Каталог конфигурации недоступен для записи: %s',
    'err_write_armory_config' => 'Не удалось записать файл конфигурации Оружейной.',

    // Success
    'msg_armory_saved' => 'Настройки Оружейной успешно сохранены!',

    // Section titles
    'section_armory_config' => 'Фильтр игроков в таблицах лидеров',

    // Labels
    'label_solo_pvp' => 'Таблица лидеров Solo PvP',
    'label_arena_2v2' => 'Таблица лидеров Арены 2v2',
    'label_arena_3v3' => 'Таблица лидеров Арены 3v3',
    'label_arena_5v5' => 'Таблица лидеров Арены 5v5',

    // Options — Solo PvP
    'opt_show_all' => 'Показывать людей и ботов',
    'opt_show_all_desc' => 'Включать playerbots в таблицу лидеров Solo PvP.',
    'opt_humans_only' => 'Только игроки-люди',
    'opt_humans_only_desc' => 'Исключать playerbots из таблицы лидеров Solo PvP.',

    // Options — Arena 2v2 / 3v3 / 5v5
    'opt_show_all_arena_2v2_desc' => 'Включать команды с playerbots в таблицу 2v2.',
    'opt_show_all_arena_3v3_desc' => 'Включать команды с playerbots в таблицу 3v3.',
    'opt_show_all_arena_5v5_desc' => 'Включать команды с playerbots в таблицу 5v5.',
    'opt_humans_only_arena_2v2_desc' => 'Скрывать из таблицы 2v2 команды, чей капитан — playerbot.',
    'opt_humans_only_arena_3v3_desc' => 'Скрывать из таблицы 3v3 команды, чей капитан — playerbot.',
    'opt_humans_only_arena_5v5_desc' => 'Скрывать из таблицы 5v5 команды, чей капитан — playerbot.',

    // Note & button
    'note_armory_config' => 'Эти параметры определяют, включаются ли Playerbots в публичные таблицы лидеров PvP и Арены.',
    'btn_save_armory_settings' => 'Сохранить настройки Оружейной',
];
