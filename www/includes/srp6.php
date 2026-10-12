<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

/**
 * Game account SRP6 (auth.account salt/verifier, 32 bytes). Forever names game accounts "<bnetId>#<index>"
 * and gives them the Battle.net password cut to 16 characters.
 */
class SRP6
{
    private static $g = 7;
    private static $N_hex = '894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7';

    public static function GenerateSalt()
    {
        return random_bytes(32);
    }

    public static function calculateVerifier($username, $password, $salt)
    {
        $username = strtoupper($username);
        $password = strtoupper($password);

        $h1 = sha1($username . ':' . $password, true);
        $h2 = sha1($salt . $h1, true);
        $x = gmp_import($h2, 1, GMP_LSW_FIRST);

        $N = gmp_init(self::$N_hex, 16);
        $g = gmp_init(self::$g);
        $v = gmp_powm($g, $x, $N);

        $verifier = gmp_export($v, 1, GMP_LSW_FIRST);
        return str_pad($verifier, 32, "\0", STR_PAD_RIGHT);
    }

    public static function verifyPassword($username, $password, $salt, $storedVerifier)
    {
        $computedVerifier = self::calculateVerifier($username, $password, $salt);
        return hash_equals($computedVerifier, $storedVerifier);
    }
}

/**
 * Battle.net account SRP6 (auth.battlenet_accounts salt/verifier). The login name is the SHA-256 of the
 * upper-case e-mail, as upper-case hex.
 *   srp_version 1: 1024-bit group, x = SHA256(salt | SHA256(NAME:PASSWORD)), password upper-case, 16 characters
 *   srp_version 2: 2048-bit group, x = PBKDF2-HMAC-SHA512(NAME:password, salt, 15000 rounds), password as typed
 */
class BnetSRP6
{
    const VERSION = 2;
    const PBKDF2_ROUNDS = 15000;
    const MAX_PASSWORD_V1 = 16;

    private static $N1_hex = '86A7F6DEEB306CE519770FE37D556F29944132554DED0BD68205E27F3231FEF5A10108238A3150C59CAF7B0B6478691C13A6ACF5E1B5ADAFD4A943D4A21A142B800E8A55F8BFBAC700EB77A7235EE5A609E350EA9FC19F10D921C2FA832E4461B7125D38D254A0BE873DFC27858ACB3F8B9F258461E4373BC3A6C2A9634324AB';
    private static $N2_hex = 'AC6BDB41324A9A9BF166DE5E1389582FAF72B6651987EE07FC3192943DB56050A37329CBB4A099ED8193E0757767A13DD52312AB4B03310DCD7F48A9DA04FD50E8083969EDB767B0CF6095179A163AB3661A05FBD5FAAAE82918A9962F0B93B855F97993EC975EEAA80D740ADBF4FF747359D041D5C33EA71D281E446B14773BCA97B43A23FB801676BD207A436C6481F1D2B9078717461A5B9D32E688F87748544523B524B0D57D5EA77A2775D2ECFA032CFBDBF52FB3786160279004E57AE6AF874E7303CE53299CCC041C7BC308D82A5698F3A8D0C38271AE35F8E9DBFBB694B5C803D89F7AE435DE236D525F54759B65E372FCD68EF20FA7111F9E4AFF73';

    public static function GenerateSalt()
    {
        return random_bytes(32);
    }

    public static function srpUsername($email)
    {
        return strtoupper(hash('sha256', self::normalizeEmail($email)));
    }

    /** The server stores and compares e-mails in upper case (Latin letters only). */
    public static function normalizeEmail($email)
    {
        return strtoupper(trim($email));
    }

    public static function calculateVerifier($email, $password, $salt, $version = self::VERSION)
    {
        $name = self::srpUsername($email);

        if ((int)$version === 1) {
            $password = strtoupper(substr($password, 0, self::MAX_PASSWORD_V1));
            $x = gmp_import(hash('sha256', $salt . hash('sha256', $name . ':' . $password, true), true), 1, GMP_LSW_FIRST);
            $N = gmp_init(self::$N1_hex, 16);
            $length = 128;
        } else {
            $xBytes = hash_pbkdf2('sha512', $name . ':' . $password, $salt, self::PBKDF2_ROUNDS, 64, true);
            $x = gmp_import($xBytes, 1, GMP_MSW_FIRST);
            if (ord($xBytes[0]) & 0x80) {
                // the server reads the 64 bytes as a signed number
                $x = gmp_sub($x, gmp_pow(2, 512));
            }
            $N = gmp_init(self::$N2_hex, 16);
            $x = gmp_mod($x, gmp_sub($N, 1));
            $length = 256;
        }

        $v = gmp_powm(gmp_init(2), $x, $N);
        return str_pad(gmp_export($v, 1, GMP_LSW_FIRST), $length, "\0", STR_PAD_RIGHT);
    }

    public static function verifyPassword($email, $password, $salt, $storedVerifier, $version = self::VERSION)
    {
        $computed = self::calculateVerifier($email, $password, $salt, $version);
        return hash_equals(rtrim($storedVerifier, "\0"), rtrim($computed, "\0"));
    }
}
?>
