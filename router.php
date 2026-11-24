<?php
/**
 * Teeny CMS: router for PHP's built-in web server, for trying Teeny on your own computer.
 *
 *     php -S localhost:8000 router.php
 *
 * It follows the same rules as .htaccess: clean page addresses work and private folders stay private.
 * On a real web host this file does nothing (Apache, LiteSpeed, nginx and IIS use their own rules).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$root = __DIR__;
$path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
$path = '/' . ltrim(str_replace('\\', '/', $path), '/');

$deny = static function (): bool {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    return true;
};

// Same blocks as .htaccess
$base = basename($path);
if (preg_match('~/\.~', $path)                                                          // hidden files and folders (.git, .htaccess)
    || preg_match('#\.(json|md|lock|tmp|log|ini|sh|bak|old|orig|swp|sql|zip|tar|gz)$|~$#i', $base)
    || preg_match('~^(config\.php|config\.sample\.php|web\.config|error_log|router\.php)$~i', $base)
    || preg_match('~^/(core|data|templates|examples|brand)(/|$)~', $path)
    || preg_match('~^/admin/views(/|$)~', $path)
    || preg_match('~^/(themes|plugins)/.*\.(php\d?|phtml|phar|json|html?|svg|txt|md)$~i', $path)
    || preg_match('~^/uploads/.*\.(php\d?|phtml|phar)$~i', $path)) {
    return $deny();
}

$file = realpath($root . $path);
if ($file !== false && !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root))) {
    return $deny();
}

$run = static function (string $script, string $scriptName): bool {
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    $_SERVER['SCRIPT_FILENAME'] = $script;
    $_SERVER['PHP_SELF'] = $scriptName;
    chdir(dirname($script));
    require $script;
    return true;
};

if ($file !== false && is_file($file)) {
    if (str_ends_with(strtolower($file), '.php')) return $run($file, $path);
    return false; // let the built-in server send images, styles and scripts
}
if ($file !== false && is_dir($file) && is_file($file . '/index.php')) {
    return $run($file . '/index.php', rtrim($path, '/') . '/index.php');
}

// Clean page addresses: everything else goes to index.php
return $run($root . '/index.php', '/index.php');
