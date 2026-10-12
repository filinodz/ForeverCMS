<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

/**
 * Safely checks whether the Playerbots table used by the "humans_only"
 * filter exists (acore_playerbots.playerbots_account_type by default).
 *
 * Never throws and never exposes database details: any failure — including a
 * missing database, missing table or unusable connection — simply returns
 * false so callers can fall back gracefully (public pages) or show a warning
 * (admin settings page). Result is memoized per request.
 *
 * @param mysqli|mixed $char_db Active characters database connection.
 * @param string       $schema  Optional schema override (used by tests).
 * @param string       $table   Optional table override (used by tests).
 * @return bool
 */
function armory_playerbots_table_exists($char_db, string $schema = 'acore_playerbots', string $table = 'playerbots_account_type'): bool
{
    static $cache = [];
    $cacheKey = $schema . '.' . $table;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $exists = false;

    try {
        if ($char_db instanceof mysqli && empty($char_db->connect_error)) {
            $stmt = $char_db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $schema, $table);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result) {
                        $exists = $result->num_rows > 0;
                        $result->free();
                    }
                }
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        // Server-side logging only — no details are exposed to callers or pages.
        error_log('Armory Playerbots table probe failed: ' . $e->getMessage());
        $exists = false;
    }

    return $cache[$cacheKey] = $exists;
}
