<?php
return [
    // Page meta
    'page_title_armory_settings' => '英雄榜设置',
    'page_description_armory_settings' => '配置英雄榜排行榜与显示设置',

    // Errors
    'err_fix_errors' => '请修复以下错误：',
    'err_invalid_csrf' => '无效的CSRF令牌。',
    'err_invalid_display_setting' => '为“%s”提交的值无效。必须为“all”或“humans_only”。',
    'err_config_dir_not_writable' => '配置目录不可写：%s',
    'err_write_armory_config' => '无法写入英雄榜配置文件。',

    // Success
    'msg_armory_saved' => '英雄榜配置已成功保存！',

    // Section titles
    'section_armory_config' => '排行榜玩家筛选',

    // Labels
    'label_solo_pvp' => 'Solo PvP 排行榜',
    'label_arena_2v2' => '竞技场 2v2 排行榜',
    'label_arena_3v3' => '竞技场 3v3 排行榜',
    'label_arena_5v5' => '竞技场 5v5 排行榜',

    // Options — Solo PvP
    'opt_show_all' => '显示玩家与机器人',
    'opt_show_all_desc' => '在 Solo PvP 排行榜中包含 playerbots。',
    'opt_humans_only' => '仅真人玩家',
    'opt_humans_only_desc' => '从 Solo PvP 排行榜中过滤 playerbots。',

    // Options — Arena 2v2 / 3v3 / 5v5
    'opt_show_all_arena_2v2_desc' => '在 2v2 排行榜中包含有 playerbots 的队伍。',
    'opt_show_all_arena_3v3_desc' => '在 3v3 排行榜中包含有 playerbots 的队伍。',
    'opt_show_all_arena_5v5_desc' => '在 5v5 排行榜中包含有 playerbots 的队伍。',
    'opt_humans_only_arena_2v2_desc' => '从 2v2 排行榜中隐藏队长为 playerbot 的队伍。',
    'opt_humans_only_arena_3v3_desc' => '从 3v3 排行榜中隐藏队长为 playerbot 的队伍。',
    'opt_humans_only_arena_5v5_desc' => '从 5v5 排行榜中隐藏队长为 playerbot 的队伍。',

    // Note & button
    'note_armory_config' => '这些设置控制公开的 PvP 与竞技场排行榜中是否包含 Playerbots。',
    'btn_save_armory_settings' => '保存英雄榜设置',
];
