<?php
if (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }

/**
 * Forever CMS: what differs from the AzerothCore 3.3.5 servers SahtoutCMS was written for.
 *
 *  - Players log in with a Battle.net account (auth.battlenet_accounts, e-mail + SRP6 v2). Each one owns a
 *    game account (auth.account, named "<bnetId>#1") that the characters belong to. The website session
 *    keeps the game account id as user_id, so characters, bans and GM levels are looked up the usual way.
 *  - Several realms share one auth database; every realm has its own characters database and RA port.
 *  - The server databases are only read and written the way the server itself does: no extra tables,
 *    triggers or procedures. Everything the website owns is in the site database.
 */

require_once __DIR__ . '/srp6.php';

const FOREVER_PASSWORD_MIN = 6;
const FOREVER_PASSWORD_MAX = 16;   // the game account keeps 16 characters at most
const FOREVER_PENDING_HOURS = 48;

// ---------------------------------------------------------------- realms

function forever_realms()
{
    global $realmlist;
    if (!isset($realmlist) || !is_array($realmlist)) {
        require __DIR__ . '/realm_config.php';
    }
    return is_array($realmlist) ? array_values($realmlist) : [];
}

function forever_realm($realm_id)
{
    foreach (forever_realms() as $realm) {
        if ((int)$realm['id'] === (int)$realm_id) {
            return $realm;
        }
    }
    return null;
}

/** The realm the visitor is looking at: ?realm=ID, then the session, then the first realm. */
function forever_current_realm()
{
    static $current = null;
    if ($current !== null) {
        return $current;
    }
    $realms = forever_realms();
    if (!$realms) {
        return $current = ['id' => 0, 'name' => 'Forever', 'char_db' => 'characters', 'ra_port' => 3443, 'port' => 8085];
    }
    $wanted = null;
    if (isset($_GET['realm']) && ctype_digit((string)$_GET['realm'])) {
        $wanted = forever_realm((int)$_GET['realm']);
        if ($wanted && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['realm_id'] = (int)$wanted['id'];
        }
    }
    if (!$wanted && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['realm_id'])) {
        $wanted = forever_realm((int)$_SESSION['realm_id']);
    }
    return $current = $wanted ?: $realms[0];
}

/** A connection to a realm's characters database (one per database and request). */
function forever_char_db($realm_id = null)
{
    global $db_char_host, $db_char_port, $db_char_user, $db_char_pass;
    static $connections = [];

    $realm = $realm_id === null ? forever_current_realm() : forever_realm($realm_id);
    if (!$realm) {
        return null;
    }
    $name = $realm['char_db'];
    if (!isset($connections[$name])) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @new mysqli($db_char_host, $db_char_user, $db_char_pass, $name, (int)$db_char_port);
        if ($db->connect_error) {
            error_log('Forever CMS: characters database ' . $name . ': ' . $db->connect_error);
            return null;
        }
        $db->set_charset('utf8mb4');
        $connections[$name] = $db;
    }
    return $connections[$name];
}

function forever_realm_ra($realm_id, $command, $timeout = 15)
{
    require_once __DIR__ . '/ra_client.php';
    $realm = forever_realm($realm_id);
    if (!$realm || empty($realm['ra_port'])) {
        return ['success' => false, 'output' => '', 'error' => 'This realm has no RA port in realm_config.php.'];
    }
    return forever_ra_command((int)$realm['ra_port'], $command, $timeout);
}

// ---------------------------------------------------------------- accounts

function forever_valid_password($password)
{
    $length = strlen((string)$password);
    return $length >= FOREVER_PASSWORD_MIN && $length <= FOREVER_PASSWORD_MAX;
}

function forever_display_name($email)
{
    $local = strtolower(strstr((string)$email, '@', true) ?: (string)$email);
    return $local === '' ? 'player' : ucfirst($local);
}

function forever_bnet_by_email($auth_db, $email)
{
    $normalized = BnetSRP6::normalizeEmail($email);
    $stmt = $auth_db->prepare('SELECT id, email, srp_version, salt, verifier, locked, joindate, last_login FROM battlenet_accounts WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $normalized);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function forever_bnet_by_id($auth_db, $bnet_id)
{
    $stmt = $auth_db->prepare('SELECT id, email, srp_version, salt, verifier, locked, joindate, last_login FROM battlenet_accounts WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $bnet_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** The first game account of a Battle.net account (Forever makes exactly one, "<id>#1"). */
function forever_game_account($auth_db, $bnet_id)
{
    $stmt = $auth_db->prepare('SELECT id, username, email, battlenet_account, joindate, last_login, last_ip, online, expansion, locked '
        . 'FROM account WHERE battlenet_account = ? ORDER BY battlenet_index LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $bnet_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function forever_account_by_id($auth_db, $account_id)
{
    $stmt = $auth_db->prepare('SELECT a.id, a.username, a.battlenet_account, a.joindate, a.last_login, a.last_ip, a.online, a.expansion, a.locked, '
        . 'COALESCE(b.email, a.email) AS email FROM account a LEFT JOIN battlenet_accounts b ON b.id = a.battlenet_account WHERE a.id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function forever_email_taken($auth_db, $email)
{
    return forever_bnet_by_email($auth_db, $email) !== null;
}

/**
 * Checks an e-mail and password the way the login server does.
 * Returns ['bnet' => row, 'account' => row] or null.
 */
function forever_check_login($auth_db, $email, $password)
{
    $bnet = forever_bnet_by_email($auth_db, $email);
    if (!$bnet) {
        // same work as a real check, so a missing account answers as slowly as a wrong password
        BnetSRP6::calculateVerifier($email, (string)$password, str_repeat("\0", 32));
        return null;
    }
    if (!BnetSRP6::verifyPassword($bnet['email'], (string)$password, $bnet['salt'], $bnet['verifier'], $bnet['srp_version'])) {
        return null;
    }
    $account = forever_game_account($auth_db, (int)$bnet['id']);
    if (!$account) {
        return null;
    }
    return ['bnet' => $bnet, 'account' => $account];
}

/**
 * Creates the Battle.net account and its game account, like the server's "bnetaccount create".
 * Returns the game account id, or 0 with $error set.
 */
function forever_create_account($auth_db, $email, $password, &$error = null)
{
    $email = BnetSRP6::normalizeEmail($email);
    if (forever_email_taken($auth_db, $email)) {
        $error = 'email_exists';
        return 0;
    }

    $bnet_salt = BnetSRP6::GenerateSalt();
    $bnet_verifier = BnetSRP6::calculateVerifier($email, $password, $bnet_salt);
    $version = BnetSRP6::VERSION;

    $auth_db->begin_transaction();
    try {
        $stmt = $auth_db->prepare('INSERT INTO battlenet_accounts (email, srp_version, salt, verifier) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('siss', $email, $version, $bnet_salt, $bnet_verifier);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $bnet_id = (int)$stmt->insert_id;
        $stmt->close();

        $username = $bnet_id . '#1';
        $game_salt = SRP6::GenerateSalt();
        $game_verifier = SRP6::calculateVerifier($username, substr($password, 0, FOREVER_PASSWORD_MAX), $game_salt);
        $stmt = $auth_db->prepare('INSERT INTO account (username, salt, verifier, email, reg_mail, joindate, battlenet_account, battlenet_index) '
            . 'VALUES (?, ?, ?, ?, ?, NOW(), ?, 1)');
        $stmt->bind_param('sssssi', $username, $game_salt, $game_verifier, $email, $email, $bnet_id);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $account_id = (int)$stmt->insert_id;
        $stmt->close();

        // the server keeps one character counter per realm and account
        $stmt = $auth_db->prepare('INSERT IGNORE INTO realmcharacters (realmid, acctid, numchars) SELECT id, ?, 0 FROM realmlist');
        $stmt->bind_param('i', $account_id);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $stmt->close();

        $auth_db->commit();
        return $account_id;
    } catch (Throwable $e) {
        $auth_db->rollback();
        error_log('Forever CMS: account creation failed: ' . $e->getMessage());
        $error = 'database';
        return 0;
    }
}

/**
 * Writes new salts and verifiers for the Battle.net account and its game accounts.
 * $email is the e-mail the account has after this call (the Battle.net verifier depends on it).
 */
function forever_set_credentials($auth_db, $bnet_id, $email, $password)
{
    $email = BnetSRP6::normalizeEmail($email);
    $bnet_salt = BnetSRP6::GenerateSalt();
    $bnet_verifier = BnetSRP6::calculateVerifier($email, $password, $bnet_salt);
    $version = BnetSRP6::VERSION;

    $auth_db->begin_transaction();
    try {
        // a new password or e-mail also ends the client's remembered login
        $stmt = $auth_db->prepare('UPDATE battlenet_accounts SET email = ?, srp_version = ?, salt = ?, verifier = ?, LoginTicket = NULL, LoginTicketExpiry = NULL WHERE id = ?');
        $stmt->bind_param('sissi', $email, $version, $bnet_salt, $bnet_verifier, $bnet_id);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $stmt->close();

        $accounts = [];
        $stmt = $auth_db->prepare('SELECT id, username FROM account WHERE battlenet_account = ?');
        $stmt->bind_param('i', $bnet_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        $stmt->close();

        foreach ($accounts as $account) {
            $game_salt = SRP6::GenerateSalt();
            $game_verifier = SRP6::calculateVerifier($account['username'], substr($password, 0, FOREVER_PASSWORD_MAX), $game_salt);
            $stmt = $auth_db->prepare('UPDATE account SET salt = ?, verifier = ?, email = ?, session_key_auth = NULL, session_key_bnet = NULL WHERE id = ?');
            $stmt->bind_param('sssi', $game_salt, $game_verifier, $email, $account['id']);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $stmt->close();
        }

        $stmt = $auth_db->prepare('DELETE FROM battlenet_launcher_tokens WHERE account_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $bnet_id);
            $stmt->execute();
            $stmt->close();
        }

        $auth_db->commit();
        return true;
    } catch (Throwable $e) {
        $auth_db->rollback();
        error_log('Forever CMS: credential update failed: ' . $e->getMessage());
        return false;
    }
}

/** Highest GM level of a game account on any realm (account_access.RealmID -1 means all realms). */
function forever_gm_level($auth_db, $account_id)
{
    $stmt = $auth_db->prepare('SELECT MAX(SecurityLevel) AS lvl FROM account_access WHERE AccountID = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['lvl'] ?? 0);
}

function forever_active_ban($auth_db, $account_id)
{
    $stmt = $auth_db->prepare('SELECT bandate, unbandate, bannedby, banreason FROM account_banned '
        . 'WHERE id = ? AND active = 1 AND (unbandate > UNIX_TIMESTAMP() OR unbandate = bandate) ORDER BY bandate DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ---------------------------------------------------------------- website side of an account

/**
 * Makes sure the account has its website row (points, tokens, role, avatar) and that the row's e-mail is current.
 * SahtoutCMS did this with triggers on the auth database; Forever accounts are also made by the game, the
 * launcher and the Discord bot, so the row is made when the website first meets the account.
 */
function forever_ensure_site_user($site_db, $account_id, $username, $email)
{
    $email = strtolower((string)$email);
    $stmt = $site_db->prepare('INSERT INTO user_currencies (account_id, username, email, points, tokens) VALUES (?, ?, ?, 0, 0) '
        . 'ON DUPLICATE KEY UPDATE username = VALUES(username), email = VALUES(email)');
    if (!$stmt) {
        error_log('Forever CMS: user_currencies: ' . $site_db->error);
        return false;
    }
    $stmt->bind_param('iss', $account_id, $username, $email);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/** Website rows for accounts that were made outside the website (admin lists join on them). */
function forever_sync_site_users($auth_db, $site_db)
{
    $result = $auth_db->query('SELECT a.id, a.username, COALESCE(b.email, a.email) AS email FROM account a LEFT JOIN battlenet_accounts b ON b.id = a.battlenet_account');
    if (!$result) {
        return;
    }
    while ($row = $result->fetch_assoc()) {
        forever_ensure_site_user($site_db, (int)$row['id'], $row['username'], $row['email']);
    }
}

// ---------------------------------------------------------------- registrations waiting for their e-mail link

/** The key that protects the passwords of registrations waiting for activation; made on first use. */
function forever_secret_key()
{
    $file = __DIR__ . '/config.secret.php';
    if (is_file($file)) {
        $key = include $file;
        if (is_string($key) && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2) {
            return hex2bin($key);
        }
    }
    $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    file_put_contents($file, "<?php\nif (!defined('ALLOWED_ACCESS')) { exit('Forbidden'); }\nreturn '" . bin2hex($key) . "';\n", LOCK_EX);
    return $key;
}

function forever_seal($text)
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox((string)$text, $nonce, forever_secret_key());
}

function forever_unseal($sealed)
{
    $sealed = (string)$sealed;
    if (strlen($sealed) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $nonce = substr($sealed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $text = sodium_crypto_secretbox_open(substr($sealed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, forever_secret_key());
    return $text === false ? null : $text;
}

function forever_purge_pending($site_db)
{
    $site_db->query('DELETE FROM pending_accounts WHERE created_at < NOW() - INTERVAL ' . (int)FOREVER_PENDING_HOURS . ' HOUR');
}

// ---------------------------------------------------------------- characters

function forever_class_name($class)
{
    static $names = [1 => 'Warrior', 2 => 'Paladin', 3 => 'Hunter', 4 => 'Rogue', 5 => 'Priest', 6 => 'Death Knight', 7 => 'Shaman',
        8 => 'Mage', 9 => 'Warlock', 10 => 'Monk', 11 => 'Druid', 12 => 'Demon Hunter', 13 => 'Evoker'];
    return $names[(int)$class] ?? 'Unknown';
}

function forever_race_name($race)
{
    static $names = [1 => 'Human', 2 => 'Orc', 3 => 'Dwarf', 4 => 'Night Elf', 5 => 'Undead', 6 => 'Tauren', 7 => 'Gnome', 8 => 'Troll',
        9 => 'Goblin', 10 => 'Blood Elf', 11 => 'Draenei', 22 => 'Worgen', 95 => 'High Order', 96 => 'Windshaper'];
    return $names[(int)$race] ?? 'Unknown';
}

function forever_is_alliance($race)
{
    return in_array((int)$race, [1, 3, 4, 7, 11, 22, 25, 29, 30, 32, 34, 37, 52, 85, 86, 95], true);
}

function forever_is_horde($race)
{
    return !forever_is_alliance($race) && (int)$race !== 24;
}

/** File name used by the account and armory race icon renderers. */
function forever_race_icon_file($race, $gender = 0)
{
    $race = (int)$race;
    if ($race === 95) {
        return 'skyborne/high-order.svg';
    }
    if ($race === 96) {
        return 'skyborne/windshaper.svg';
    }

    static $icons = [
        1 => 'human.png', 2 => 'orc.png', 3 => 'dwarf.png', 4 => 'nightelf.png',
        5 => 'undead.png', 6 => 'tauren.png', 7 => 'gnome.png', 8 => 'troll.png',
        9 => 'goblin.png', 10 => 'bloodelf.png', 11 => 'draenei.png', 22 => 'worgen.png',
        25 => 'pandaren_alliance.png', 26 => 'pandaren_horde.png', 29 => 'voidelf.png',
    ];
    return (($gender == 1) ? 'female/' : 'male/') . ($icons[$race] ?? 'default.png');
}

/** Characters of an account on one realm; deleted characters waiting for restoration are left out. */
function forever_account_characters($char_db, $account_id)
{
    $characters = [];
    if (!$char_db) {
        return $characters;
    }
    $stmt = $char_db->prepare('SELECT guid, name, race, class, gender, level, money, online, totaltime, zone, map FROM characters '
        . 'WHERE account = ? AND deleteInfos_Account IS NULL ORDER BY level DESC, name');
    if (!$stmt) {
        return $characters;
    }
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $characters[] = $row;
    }
    $stmt->close();
    return $characters;
}
