<?php
declare(strict_types=1);

/**
 * Flat-file JSON storage.
 *
 * Every file is saved as "name.json.php" and starts with a PHP guard line, so even if
 * the server ignores .htaccess (e.g. nginx), requesting the file runs the guard and
 * returns an empty 404 instead of exposing the JSON. Writes are atomic (temp file + rename)
 * and read-modify-write cycles are protected with flock().
 */
final class Store
{
    private const GUARD = "<?php http_response_code(404); exit; ?>\n";
    /** Writes that never change what visitors see, so the page cache can stay. */
    private const NO_CACHE_CLEAR = ['throttle', 'users', 'inbox', 'formlimit', 'media', 'stats/', 'activity', 'subscribers', 'resets', 'resetlimit'];

    public static function path(string $name): string
    {
        if (!preg_match('~^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$~', $name)) {
            throw new InvalidArgumentException('Invalid storage name.');
        }
        return TEENY_DATA . '/' . $name . '.json.php';
    }

    public static function read(string $name, mixed $default = null): mixed
    {
        $file = self::path($name);
        if (!is_file($file)) return $default;
        $raw = @file_get_contents($file);
        if ($raw === false) return $default;
        $nl = strpos($raw, "\n");
        $json = ($nl !== false && str_starts_with($raw, '<?php')) ? substr($raw, $nl + 1) : $raw;
        $data = json_decode($json, true);
        return is_array($data) ? $data : $default;
    }

    public static function write(string $name, array $data): void
    {
        $file = self::path($name);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create data folder. Check folder permissions.');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tmp = $file . '.' . random_id(4) . '.tmp';
        if (@file_put_contents($tmp, self::GUARD . $json, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write to the data folder. Check folder permissions.');
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot save data file.');
        }
        foreach (self::NO_CACHE_CLEAR as $prefix) {
            if ($name === $prefix || (str_ends_with($prefix, '/') && str_starts_with($name, $prefix))) return;
        }
        Cache::clear();
    }

    /** Locked read → modify → write. The callback receives the current data and returns the new data. */
    public static function update(string $name, callable $fn, array $default = []): array
    {
        $lockDir = TEENY_DATA . '/locks';
        if (!is_dir($lockDir)) @mkdir($lockDir, 0750, true);
        $lock = @fopen($lockDir . '/' . str_replace('/', '_', $name) . '.lock', 'c');
        if ($lock) flock($lock, LOCK_EX);
        try {
            $data = $fn(self::read($name, $default));
            self::write($name, $data);
            return $data;
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public static function delete(string $name): void
    {
        $file = self::path($name);
        if (is_file($file)) @unlink($file);
        Cache::clear();
    }

    /** Names of entries inside a sub-folder, e.g. list('revisions/abc'). */
    public static function list(string $dir): array
    {
        self::path($dir); // validates
        $out = [];
        foreach (glob(TEENY_DATA . '/' . $dir . '/*.json.php') ?: [] as $f) {
            $out[] = basename($f, '.json.php');
        }
        sort($out);
        return $out;
    }

    public static function removeDir(string $dir): void
    {
        self::path($dir);
        $full = TEENY_DATA . '/' . $dir;
        if (!is_dir($full)) return;
        foreach (glob($full . '/*') ?: [] as $f) {
            if (is_file($f)) @unlink($f);
        }
        @rmdir($full);
    }
}

/** Rendered HTML cache for anonymous visitors. Cleared whenever content changes. */
final class Cache
{
    private static function dir(): string
    {
        return TEENY_DATA . '/cache';
    }

    private static function file(string $key): string
    {
        // The version is part of the key, so pages cached by an older release are never served after an update.
        return self::dir() . '/' . hash('sha256', TEENY_VERSION . '|' . $key) . '.html';
    }

    public static function get(string $key): ?string
    {
        $f = self::file($key);
        return is_file($f) ? (string)file_get_contents($f) : null;
    }

    public static function put(string $key, string $html): void
    {
        if (!is_dir(self::dir())) @mkdir(self::dir(), 0750, true);
        @file_put_contents(self::file($key), $html, LOCK_EX);
    }

    public static function clear(): void
    {
        foreach (glob(self::dir() . '/*.html') ?: [] as $f) {
            @unlink($f);
        }
    }
}
