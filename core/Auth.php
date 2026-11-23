<?php
declare(strict_types=1);

/**
 * Authentication, sessions, roles, CSRF and brute-force throttling.
 */
final class Auth
{
    public const ROLES = ['admin' => 'Owner', 'editor' => 'Editor'];

    private const CAPS = [
        'admin'  => ['pages', 'media', 'appearance', 'settings', 'users', 'backup', 'inbox', 'plugins'],
        'editor' => ['pages', 'media'],
    ];

    /** Passwords of 10+ characters that show up at the top of leaked-password lists. */
    private const COMMON_PASSWORDS = [
        'password123', 'password1234', 'password12', 'password!!', 'passw0rd123', 'iloveyou123', 'qwerty1234',
        'qwerty12345', 'qwerty123456', 'qwertyuiop', '1q2w3e4r5t', '1qaz2wsx3edc', 'zaq12wsxcde', 'abc1234567',
        'abcd123456', 'aa12345678', 'football123', 'baseball123', 'princess123', 'sunshine123', 'superman123',
        'welcome123', 'welcome1234', 'letmein123', 'admin12345', 'administrator', 'changeme123', 'trustno1234',
        'monkey12345', 'dragon12345', 'master12345', 'michael1234', 'computer123', 'whatever123', 'starwars123',
        'teenycms123', 'mypassword', 'mypassword1', 'secret1234', 'internet123', 'samsung123', 'google1234',
    ];

    private const COOKIE = 'teeny_sid';
    private const IDLE_TIMEOUT = 7200;      // 2 hours of inactivity
    private const ABSOLUTE_TIMEOUT = 43200; // 12 hours max session
    private const WINDOW = 900;             // failure counting window (15 min)
    private const USER_LIMIT = 5;           // failures per username
    private const IP_LIMIT = 10;            // failures per IP

    private static ?array $current = null;
    private static bool $resolved = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        if (PHP_VERSION_ID < 80400) {
            // Longer session IDs on older PHP. PHP 8.4+ deprecates these settings; its defaults are already strong enough.
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');
        }
        session_name(self::COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => TEENY_BASE . '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function hasCookie(): bool
    {
        return isset($_COOKIE[self::COOKIE]);
    }

    /* ---------- users ---------- */

    public static function users(): array
    {
        $data = Store::read('users', ['users' => []]);
        return is_array($data['users'] ?? null) ? $data['users'] : [];
    }

    public static function saveUsers(array $users): void
    {
        Store::write('users', ['users' => $users]);
    }

    public static function findByUsername(string $username): ?array
    {
        foreach (self::users() as $id => $u) {
            if (strcasecmp((string)$u['username'], $username) === 0) {
                return $u + ['id' => $id];
            }
        }
        return null;
    }

    /** Is this username already used by someone else? (case-insensitive) */
    public static function usernameTaken(string $username, string $exceptId = ''): bool
    {
        foreach (self::users() as $id => $u) {
            if ((string)$id !== $exceptId && strcasecmp((string)$u['username'], $username) === 0) return true;
        }
        return false;
    }

    /** Optional email address on an account ('' is allowed). */
    public static function emailProblem(string $email): ?string
    {
        if ($email === '') return null;
        if (strlen($email) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
        return null;
    }

    public static function emailTaken(string $email, string $exceptId = ''): bool
    {
        if ($email === '') return false;
        foreach (self::users() as $id => $u) {
            if ((string)$id !== $exceptId && strcasecmp((string)($u['email'] ?? ''), $email) === 0) return true;
        }
        return false;
    }

    /** Find an account by username or email address (used by "Forgot password"). */
    public static function findByLogin(string $login): ?array
    {
        $login = trim($login);
        if ($login === '') return null;
        foreach (self::users() as $id => $u) {
            if (strcasecmp((string)$u['username'], $login) === 0 || (($u['email'] ?? '') !== '' && strcasecmp((string)$u['email'], $login) === 0)) {
                return $u + ['id' => (string)$id];
            }
        }
        return null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    public static function passwordProblem(string $password): ?string
    {
        if (strlen($password) < 10) return 'Password must be at least 10 characters.';
        if (strlen($password) > 200) return 'Password is too long.';
        if (preg_match('/^(.)\1+$/', $password)) return 'Password is too simple.';
        $plain = strtolower($password);
        if (in_array($plain, self::COMMON_PASSWORDS, true) || preg_match('/^(0123456789|1234567890|9876543210|abcdefghij|qwertyuiop)/', $plain)) {
            return 'That password is too common. Please choose something harder to guess.';
        }
        return null;
    }

    public static function usernameProblem(string $username): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
            return 'Username must be 3 to 32 characters: letters, numbers, dot, dash or underscore.';
        }
        return null;
    }

    /* ---------- session state ---------- */

    public static function user(): ?array
    {
        if (self::$resolved) return self::$current;
        self::$resolved = true;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!self::hasCookie()) return null;
            self::start();
        }
        $uid = $_SESSION['uid'] ?? null;
        if (!is_string($uid)) return null;
        $users = self::users();
        $u = $users[$uid] ?? null;
        $now = time();
        if (
            !$u
            || ($u['sv'] ?? 0) !== ($_SESSION['sv'] ?? -1)
            || $now - (int)($_SESSION['seen'] ?? 0) > self::IDLE_TIMEOUT
            || $now - (int)($_SESSION['at'] ?? 0) > self::ABSOLUTE_TIMEOUT
            || ($_SESSION['ua'] ?? '') !== self::uaHash()
        ) {
            self::logout();
            return null;
        }
        if ($now - (int)$_SESSION['seen'] > 60) {
            $_SESSION['seen'] = $now;
        }
        return self::$current = $u + ['id' => $uid];
    }

    public static function can(string $cap): bool
    {
        $u = self::user();
        return $u !== null && in_array($cap, self::CAPS[$u['role'] ?? ''] ?? [], true);
    }

    /** @return true|string  true on success, otherwise an error message */
    public static function attempt(string $username, string $password): bool|string
    {
        self::start();
        $keys = self::throttleKeys($username);
        $user = self::findByUsername($username);
        // A locked username still lets its owner in from a device that signed in successfully before,
        // so nobody can keep the owner out just by typing wrong passwords on purpose.
        $skip = $user !== null && self::knownDevice($user) ? [$keys[1]] : [];
        // The attempt is counted before the slow password check, in one locked step, so a burst of
        // simultaneous requests can't get extra guesses.
        $wait = self::reserve($keys, $skip);
        if ($wait > 0) {
            return 'Too many failed attempts. Please wait ' . max(1, (int)ceil($wait / 60)) . ' minute(s) and try again.';
        }
        if ($user === null) {
            // Spend about the same time as a real check, so the response time doesn't reveal which usernames exist.
            self::hash($password);
            $hash = '';
            $ok = false;
        } else {
            $hash = (string)$user['hash'];
            $ok = password_verify($password, $hash);
        }
        if (!$ok) {
            usleep(random_int(150000, 400000));
            return 'Incorrect username or password.';
        }
        self::clearFailures($keys); // the separate two-step counter is not touched here

        if (password_needs_rehash((string)$hash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            $users = self::users();
            $users[$user['id']]['hash'] = self::hash($password);
            self::saveUsers($users);
        }

        if (!empty($user['totp'])) {
            // Password is right, but a second factor is required before the session is granted.
            session_regenerate_id(true);
            $_SESSION = [
                'pending2fa' => ['uid' => $user['id'], 'at' => time(), 'ua' => self::uaHash()],
                'csrf' => bin2hex(random_bytes(32)),
            ];
            return self::NEED_2FA;
        }
        self::completeLogin($user['id'], $user);
        return true;
    }

    public const NEED_2FA = '__need_2fa__';

    public static function pending2fa(): ?array
    {
        $p = $_SESSION['pending2fa'] ?? null;
        if (!is_array($p) || time() - (int)($p['at'] ?? 0) > 300 || ($p['ua'] ?? '') !== self::uaHash()) return null;
        return $p;
    }

    /** Second login step. @return true|string */
    public static function attemptSecondFactor(string $code): bool|string
    {
        $p = self::pending2fa();
        if (!$p) {
            unset($_SESSION['pending2fa']);
            return 'Your login timed out. Please enter your password again.';
        }
        $users = self::users();
        $u = $users[$p['uid']] ?? null;
        if (!$u || empty($u['totp'])) return 'Please log in again.';
        // Two-step codes have their own counters, which a correct password never resets.
        $keys = ['ip2:' . hash('sha256', client_ip()), '2fa:' . hash('sha256', strtolower((string)$u['username']))];
        $wait = self::reserve($keys);
        if ($wait > 0) return 'Too many failed attempts. Please wait ' . max(1, (int)ceil($wait / 60)) . ' minute(s).';

        $step = Totp::verify((string)$u['totp'], $code, (int)($u['totpLast'] ?? 0));
        $recoveryUsed = false;
        if ($step === null) {
            $h = hash('sha256', strtolower(trim($code)));
            $idx = array_search($h, $u['recovery'] ?? [], true);
            if ($idx !== false) {
                unset($u['recovery'][$idx]);
                $u['recovery'] = array_values($u['recovery']);
                $recoveryUsed = true;
            }
        }
        if ($step === null && !$recoveryUsed) {
            usleep(random_int(150000, 400000));
            return 'That code is not correct. Check your authenticator app and try again.';
        }
        self::clearFailures($keys);
        if ($step !== null) $u['totpLast'] = $step;
        $users[$p['uid']] = $u;
        self::saveUsers($users);
        self::completeLogin($p['uid'], $u);
        if ($recoveryUsed) {
            $_SESSION['flash'] = ['msg' => 'You used a recovery code. ' . count($u['recovery']) . ' left. Create new ones in your Profile if you are running low.', 'type' => 'ok'];
        }
        return true;
    }

    private static function completeLogin(string $uid, array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            'uid'  => $uid,
            'sv'   => $user['sv'] ?? 0,
            'at'   => time(),
            'seen' => time(),
            'ua'   => self::uaHash(),
            'csrf' => bin2hex(random_bytes(32)),
        ];
        self::$resolved = false;
        $users = self::users();
        $users[$uid]['lastLogin'] = time();
        // Remember this device (a hash of its connection and browser) for 30 days, see knownDevice().
        $devices = is_array($users[$uid]['devices'] ?? null) ? $users[$uid]['devices'] : [];
        $devices[self::deviceHash((string)$user['username'])] = time();
        $devices = array_filter($devices, static fn($t) => is_int($t) && $t > time() - 30 * 86400);
        arsort($devices);
        $users[$uid]['devices'] = array_slice($devices, 0, 8, true);
        self::saveUsers($users);
    }

    private static function deviceHash(string $username): string
    {
        return substr(hash('sha256', strtolower($username) . '|' . client_ip() . '|' . self::uaHash()), 0, 24);
    }

    /** Has this account signed in successfully from this connection and browser in the last 30 days? */
    private static function knownDevice(array $user): bool
    {
        $t = $user['devices'][self::deviceHash((string)$user['username'])] ?? 0;
        return is_int($t) && $t > time() - 30 * 86400;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Throw away the old session (and its data) and continue with a fresh, empty one,
            // so a sign-in form shown in this same request gets a working security token.
            $_SESSION = [];
            session_regenerate_id(true);
        }
        self::$current = null;
        self::$resolved = true;
    }

    private static function uaHash(): string
    {
        return substr(hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16);
    }

    /* ---------- CSRF ---------- */

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrf()) . '">';
    }

    public static function checkCsrf(): bool
    {
        // Reject cross-site requests when the browser tells us the origin.
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '' && $origin !== 'null') {
            $host = parse_url($origin, PHP_URL_HOST);
            $serverHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
            if (!is_string($host) || strcasecmp($host, (string)$serverHost) !== 0) return false;
        }
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return is_string($token) && is_string($_SESSION['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $token);
    }

    /* ---------- brute-force throttling ---------- */

    private static function throttleKeys(string $username): array
    {
        return ['ip:' . hash('sha256', client_ip()), 'u:' . hash('sha256', strtolower(trim($username)))];
    }

    /**
     * Check the lockout and count one attempt, in a single locked step. Returns the seconds to wait
     * (and counts nothing) if any key is locked; keys in $skipLock are counted but can't block.
     * A successful attempt calls clearFailures() afterwards.
     */
    private static function reserve(array $keys, array $skipLock = []): int
    {
        $wait = 0;
        Store::update('throttle', static function (array $t) use ($keys, $skipLock, &$wait): array {
            $now = time();
            foreach ($keys as $k) {
                if (in_array($k, $skipLock, true)) continue;
                $until = (int)($t[$k]['until'] ?? 0);
                if ($until > $now) $wait = max($wait, $until - $now);
            }
            return $wait > 0 ? $t : self::countFailure($t, $keys, $now);
        });
        return $wait;
    }

    private static function countFailure(array $t, array $keys, int $now): array
    {
        foreach ($t as $k => $v) {
            if ((int)($v['last'] ?? 0) < $now - 86400) unset($t[$k]);
        }
        foreach ($keys as $k) {
            $e = $t[$k] ?? ['n' => 0, 'first' => $now];
            if ($now - (int)$e['first'] > self::WINDOW && (int)($e['until'] ?? 0) < $now) {
                $e = ['n' => 0, 'first' => $now];
            }
            $e['n'] = (int)$e['n'] + 1;
            $e['last'] = $now;
            $limit = str_starts_with($k, 'ip') ? self::IP_LIMIT : self::USER_LIMIT;
            if ($e['n'] >= $limit) {
                // 15 min, growing up to 1 hour for repeated lockouts
                $e['until'] = $now + self::WINDOW * min(4, intdiv($e['n'], $limit));
            }
            $t[$k] = $e;
        }
        return $t;
    }

    /** Lift a sign-in lockout for a username (after a successful password reset). */
    public static function clearLockout(string $username): void
    {
        self::clearFailures([self::throttleKeys($username)[1]]);
    }

    private static function clearFailures(array $keys): void
    {
        Store::update('throttle', static function (array $t) use ($keys): array {
            foreach ($keys as $k) unset($t[$k]);
            return $t;
        });
    }
}
