<?php
/**
 * Teeny CMS: bootstrap.
 * Every entry point (index.php, admin/index.php, admin/api.php, install.php)
 * defines TEENY_DEPTH and then requires this file.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Teeny CMS needs PHP 8.1 or newer.');
}

define('TEENY_VERSION', '1.5.0');
define('TEENY_REPO', 'https://github.com/webzing/teeny'); // project home: releases, wiki and help
define('TEENY_ROOT', dirname(__DIR__));

// Optional config.php (see config.sample.php), for example to move data outside the web root.
if (is_file(TEENY_ROOT . '/config.php')) {
    require TEENY_ROOT . '/config.php';
}
if (!defined('TEENY_DATA')) define('TEENY_DATA', TEENY_ROOT . '/data');
if (!defined('TEENY_DEBUG')) define('TEENY_DEBUG', false);
if (!defined('TEENY_MAX_UPLOAD_MB')) define('TEENY_MAX_UPLOAD_MB', 8);
define('TEENY_UPLOADS', TEENY_ROOT . '/uploads');

// Never leak errors to visitors; log them in the protected data folder.
error_reporting(E_ALL);
ini_set('display_errors', TEENY_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(TEENY_DATA . '/logs')) {
    ini_set('error_log', TEENY_DATA . '/logs/error.log');
}
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('UTC');

// Work out the URL base path, so Teeny runs from a domain root or a sub-folder.
(function (): void {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = dirname($script);
    $depth = defined('TEENY_DEPTH') ? (int)TEENY_DEPTH : 0;
    for ($i = 0; $i < $depth; $i++) {
        $dir = dirname($dir);
    }
    $dir = str_replace('\\', '/', $dir);
    define('TEENY_BASE', rtrim($dir, '/'));
})();

require __DIR__ . '/helpers.php';
require __DIR__ . '/Store.php';
require __DIR__ . '/Sanitizer.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Media.php';
require __DIR__ . '/Blocks.php';
require __DIR__ . '/Pages.php';
require __DIR__ . '/Theme.php';
require __DIR__ . '/Renderer.php';
require __DIR__ . '/Menu.php';
require __DIR__ . '/Inbox.php';
require __DIR__ . '/Stats.php';
require __DIR__ . '/Fonts.php';
require __DIR__ . '/Search.php';
require __DIR__ . '/News.php';
require __DIR__ . '/Totp.php';
require __DIR__ . '/Activity.php';
require __DIR__ . '/Templater.php';
require __DIR__ . '/Packages.php';
require __DIR__ . '/Plugins.php';
require __DIR__ . '/Reset.php';
require __DIR__ . '/Mailer.php';

if (teeny_installed()) {
    $tz = (string)(site()['timezone'] ?? 'UTC');
    if (in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
}
