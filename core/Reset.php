<?php
declare(strict_types=1);

/**
 * "Forgot password" by email.
 *
 * - The reply is always the same, so nobody can find out which usernames or emails exist.
 * - Reset links contain a long random token. Only its SHA-256 hash is stored, it works once,
 *   and it expires after 1 hour. Asking for a new link cancels the previous one.
 * - Links are built from the site address saved in Preferences (set during installation),
 *   never from the request's Host header, so a forged request can't send a link to another domain.
 * - Requests are rate-limited per visitor and per account.
 * - Resetting a password signs the account out everywhere. Two-factor sign-in stays on.
 */
final class Reset
{
    private const TTL = 3600;
    private const PER_IP_HOUR = 5;
    private const PER_ACCOUNT_HOUR = 3;

    /* ---------- Rescue codes: reset a forgotten password without email ----------
     * Each account can have one rescue code (shown once, e.g. right after installing). Only a slow
     * password hash of it is stored. Using it sets a new password, signs out everywhere, and
     * replaces the code with a fresh one. Attempts are rate-limited per visitor and per account,
     * and the answer never says whether the username exists. Two-factor sign-in stays on.
     */
    private const RESCUE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O or 1/I look-alikes
    private const RESCUE_PER_IP_HOUR = 5;
    private const RESCUE_PER_ACCOUNT_HOUR = 5;

    /** Create (or replace) the rescue code for an account. Returns it formatted, e.g. "ABCD-EFGH-JKLM-NPQR-STUV". */
    public static function newRescueCode(string $uid): string
    {
        $raw = '';
        for ($i = 0; $i < 20; $i++) $raw .= self::RESCUE_ALPHABET[random_int(0, 31)]; // 100 bits
        $users = Auth::users();
        if (!isset($users[$uid])) throw new RuntimeException('Unknown account.');
        $users[$uid]['rescue'] = ['hash' => Auth::hash($raw), 'created' => time()];
        Auth::saveUsers($users);
        return implode('-', str_split($raw, 4));
    }

    public static function hasRescueCode(array $user): bool
    {
        return !empty($user['rescue']['hash']);
    }

    private static function normalizeRescue(string $code): string
    {
        return substr((string)preg_replace('/[^A-Z0-9]/', '', strtoupper($code)), 0, 40);
    }

    /**
     * Set a new password using a rescue code.
     * @return array{username:string,code:string}|string  the new rescue code on success, or an error message
     */
    public static function useRescueCode(string $login, string $code, string $password, string $repeat): array|string
    {
        $generic = 'That username or email and rescue code don’t match.';
        if (!self::allow('rip:' . hash('sha256', client_ip()), self::RESCUE_PER_IP_HOUR)) {
            return 'Too many tries. Please wait an hour and try again.';
        }
        $login = substr(trim($login), 0, 200);
        $user = $login !== '' ? Auth::findByLogin($login) : null;
        $code = self::normalizeRescue($code);
        if (!$user || !self::hasRescueCode($user)) {
            Auth::hash($code); // costs about the same as checking a real code, so timing doesn't reveal accounts
            return $generic;
        }
        if (!self::allow('ru:' . $user['id'], self::RESCUE_PER_ACCOUNT_HOUR)) {
            return 'Too many tries. Please wait an hour and try again.';
        }
        if (strlen($code) !== 20 || !password_verify($code, (string)$user['rescue']['hash'])) {
            Activity::log('account.rescue_failed', 'Wrong rescue code for @' . $user['username']);
            return $generic;
        }
        if ($err = Auth::passwordProblem($password)) return $err;
        if ($password !== $repeat) return 'The two passwords do not match.';

        $users = Auth::users();
        $users[$user['id']]['hash'] = Auth::hash($password);
        $users[$user['id']]['sv'] = (int)($users[$user['id']]['sv'] ?? 0) + 1; // sign out everywhere
        Auth::saveUsers($users);
        Store::update('resets', static function (array $d) use ($user): array {
            $d['items'] = array_filter($d['items'] ?? [], static fn($r) => ($r['uid'] ?? '') !== $user['id']);
            return $d;
        }, ['items' => []]);
        Auth::clearLockout((string)$user['username']);
        $new = self::newRescueCode((string)$user['id']); // the used code stops working
        Activity::log('account.reset', '@' . $user['username'] . ' set a new password with a rescue code');
        return ['username' => (string)$user['username'], 'code' => $new];
    }

    /** Can this site send reset emails at all? */
    public static function available(): bool
    {
        return self::baseUrl() !== '' && Mailer::configured();
    }

    /** Why reset emails can't be sent right now ('' when they can). Shown to owners in Preferences. */
    public static function problem(): string
    {
        if (self::baseUrl() === '') return 'The website address is empty. Fill it in under Preferences → Search engines.';
        if (!Mailer::configured()) return Mailer::settings()['method'] === 'smtp' ? 'SMTP sending isn’t set up yet. Fill in the SMTP details in Preferences → Email.' : 'PHP mail() is switched off on this server. Use SMTP instead.';
        return '';
    }

    private static function baseUrl(): string
    {
        $u = rtrim((string)(site()['siteUrl'] ?? ''), '/');
        return preg_match('~^https?://[a-z0-9.-]+(:\d{1,5})?(/[^\s]*)?$~i', $u) ? $u : '';
    }

    /** Handle a request. Always returns quietly; the caller shows the same message every time. */
    public static function request(string $login): void
    {
        $login = substr(trim($login), 0, 200);
        $user = $login !== '' ? Auth::findByLogin($login) : null;
        // Owners can see in Activity (Preferences → Email) why an email did or didn't go out. Visitors never can.
        if (!self::allow('ip:' . hash('sha256', client_ip()), self::PER_IP_HOUR)) {
            Activity::log('email.skipped', 'Password reset: too many requests from one visitor in the last hour');
            return;
        }
        if (!$user) {
            usleep(random_int(150000, 350000)); // similar timing whether or not the account exists
            Activity::log('email.skipped', 'Password reset: no account matches the username or email that was typed');
            return;
        }
        if (!self::allow('u:' . $user['id'], self::PER_ACCOUNT_HOUR)) {
            Activity::log('email.skipped', 'Password reset for @' . $user['username'] . ': too many requests in the last hour');
            return;
        }
        $email = (string)($user['email'] ?? '');
        if ($email === '') {
            Activity::log('email.skipped', 'Password reset for @' . $user['username'] . ': this account has no email address');
            return;
        }
        if ($why = self::problem()) {
            Activity::log('email.skipped', 'Password reset for @' . $user['username'] . ': ' . $why);
            return;
        }

        $token = bin2hex(random_bytes(32));
        Store::update('resets', static function (array $d) use ($token, $user): array {
            $now = time();
            $items = [];
            foreach ($d['items'] ?? [] as $h => $r) {
                if (($r['expires'] ?? 0) > $now && ($r['uid'] ?? '') !== $user['id']) $items[$h] = $r; // drop expired + older links for this account
            }
            $items[hash('sha256', $token)] = ['uid' => $user['id'], 'expires' => $now + self::TTL];
            return ['items' => $items];
        }, ['items' => []]);

        $site = site();
        $link = self::baseUrl() . '/admin/?p=reset&token=' . $token;
        $noBreaks = static fn(string $s) => trim((string)preg_replace('/[\r\n]+/', ' ', $s));
        $siteName = $noBreaks((string)($site['title'] ?? 'your website'));
        $body = "Hello " . $noBreaks((string)$user['name']) . ",\n\n"
            . "Someone asked to reset the password for your account (@" . $user['username'] . ") on " . $siteName . ".\n\n"
            . "To choose a new password, open this link within the next hour:\n" . $link . "\n\n"
            . "The link works once. If you didn't ask for this, you can ignore this email; your password stays the same.\n\n"
            . "Teeny CMS\n";
        if (Mailer::send($email, 'Reset your password for ' . $siteName, $body)) {
            Activity::log('email.sent', 'Password reset link sent to @' . $user['username']);
        } else {
            Activity::log('email.failed', 'Password reset for @' . $user['username'] . ': ' . Mailer::$lastError);
        }
    }

    /** The account a token belongs to, or null if it's unknown, used or expired. */
    public static function userFor(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $r = (Store::read('resets', ['items' => []])['items'] ?? [])[hash('sha256', $token)] ?? null;
        if (!is_array($r) || (int)($r['expires'] ?? 0) < time()) return null;
        $u = Auth::users()[$r['uid'] ?? ''] ?? null;
        return $u ? $u + ['id' => (string)$r['uid']] : null;
    }

    /** @return true|string */
    public static function complete(string $token, string $password, string $repeat): bool|string
    {
        $user = self::userFor($token);
        if (!$user) return 'This reset link has expired or was already used. Please ask for a new one.';
        if ($err = Auth::passwordProblem($password)) return $err;
        if ($password !== $repeat) return 'The two passwords do not match.';
        $users = Auth::users();
        $users[$user['id']]['hash'] = Auth::hash($password);
        $users[$user['id']]['sv'] = (int)($users[$user['id']]['sv'] ?? 0) + 1; // sign out everywhere
        Auth::saveUsers($users);
        Store::update('resets', static function (array $d) use ($user): array {
            $d['items'] = array_filter($d['items'] ?? [], static fn($r) => ($r['uid'] ?? '') !== $user['id']);
            return $d;
        }, ['items' => []]);
        Auth::clearLockout((string)$user['username']);
        Activity::log('account.reset', '@' . $user['username'] . ' set a new password with a reset link');
        return true;
    }

    private static function allow(string $key, int $limit): bool
    {
        $ok = true;
        Store::update('resetlimit', static function (array $d) use ($key, $limit, &$ok): array {
            $now = time();
            foreach ($d as $k => $hits) {
                $d[$k] = array_values(array_filter((array)$hits, static fn($t) => $t > $now - 3600));
                if (!$d[$k]) unset($d[$k]);
            }
            if (count($d[$key] ?? []) >= $limit) {
                $ok = false;
                return $d;
            }
            $d[$key][] = $now;
            return $d;
        });
        return $ok;
    }
}
