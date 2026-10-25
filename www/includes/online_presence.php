<?php
if (!defined('ALLOWED_ACCESS')) exit('Forbidden');

function forever_presence_table($db, string $schema, string $table): bool {
    static $cache = [];
    $key = spl_object_id($db) . ':' . $schema . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $s = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');
        if (!$s) return $cache[$key] = false;
        $s->bind_param('ss', $schema, $table); $s->execute();
        $exists = (bool)$s->get_result()->fetch_row()[0]; $s->close();
        return $cache[$key] = $exists;
    } catch (Throwable $e) { return $cache[$key] = false; }
}

/** Prefer character-level V2 records; never classify bots by name or a shared pool account. */
function forever_presence_bot_sql($db, string $legacySchema = 'acore_playerbots'): array {
    $schema = (string)$db->query('SELECT DATABASE()')->fetch_row()[0];
    if (forever_presence_table($db, $schema, 'playerbot_v2_character')) {
        return ['sql' => 'EXISTS (SELECT 1 FROM playerbot_v2_character pv WHERE pv.character_guid_low=c.guid)', 'available' => true];
    }
    foreach (['playerbot', $legacySchema] as $candidate) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $candidate)) continue;
        if (forever_presence_table($db, $candidate, 'playerbot_accounts')) {
            return ['sql' => "EXISTS (SELECT 1 FROM `$candidate`.playerbot_accounts pa WHERE pa.account_id=c.account AND pa.is_bot_account=1)", 'available' => true];
        }
        if (forever_presence_table($db, $candidate, 'playerbots_account_type')) {
            return ['sql' => "EXISTS (SELECT 1 FROM `$candidate`.playerbots_account_type pa WHERE pa.account_id=c.account)", 'available' => true];
        }
    }
    return ['sql' => '0', 'available' => false];
}
