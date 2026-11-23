<?php
declare(strict_types=1);

/**
 * Time-based one-time passwords (RFC 6238) for two-factor login.
 * Works with Google Authenticator, Microsoft Authenticator, Authy, 1Password, etc.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;

    public static function secret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper((string)preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin((int)strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }

    public static function code(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('J', $counter), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $num = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string)($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Checks a code within ±30 seconds. Returns the matching time step, or null.
     * Codes at or before $lastUsed are refused so a code cannot be used twice.
     */
    public static function verify(string $secret, string $code, int $lastUsed = 0): ?int
    {
        $code = (string)preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6 || $secret === '') return null;
        $now = intdiv(time(), self::PERIOD);
        for ($i = -1; $i <= 1; $i++) {
            $step = $now + $i;
            if ($step > $lastUsed && hash_equals(self::code($secret, $step), $code)) return $step;
        }
        return null;
    }

    public static function uri(string $issuer, string $account, string $secret): string
    {
        $issuer = str_replace(':', '', $issuer);
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }

    /** 8 one-time recovery codes like "k3f9-2x7q". Returns [plain codes, hashes]. */
    public static function recoveryCodes(): array
    {
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $c = substr(self::base32Encode(random_bytes(5)), 0, 8);
            $c = strtolower(substr($c, 0, 4) . '-' . substr($c, 4, 4));
            $plain[] = $c;
            $hashes[] = hash('sha256', $c);
        }
        return [$plain, $hashes];
    }
}
