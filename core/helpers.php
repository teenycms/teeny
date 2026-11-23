<?php
declare(strict_types=1);

const TEENY_RESERVED_SLUGS = [
    'admin', 'core', 'data', 'uploads', 'themes', 'templates', 'assets',
    'install', 'index', 'sitemap', 'robots', 'config', 'favicon',
    'plugins', 'examples', 'brand', 'part',
];

/** Escape anything for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function url(string $path = ''): string
{
    return TEENY_BASE . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url($path) . '?v=' . TEENY_VERSION;
}

function admin_url(string $page = '', array $query = []): string
{
    if ($page !== '') {
        $query = ['p' => $page] + $query;
    }
    return url('admin/') . ($query ? '?' . http_build_query($query) : '');
}

function page_url(array $page): string
{
    $site = site();
    if (($site['home'] ?? '') === ($page['id'] ?? null)) {
        return url('');
    }
    $slug = rawurlencode((string)($page['slug'] ?? ''));
    return !empty($site['prettyUrls']) ? url($slug) : url('?page=' . $slug);
}

/** Absolute URL, only when the owner configured a canonical site address. */
function absolute_url(string $path): string
{
    $base = rtrim((string)(site()['siteUrl'] ?? ''), '/');
    if ($base === '') return '';
    $rel = substr($path, strlen(TEENY_BASE));
    return $base . '/' . ltrim($rel, '/');
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** JSON that is safe to place inside an HTML <script type="application/json"> tag. */
function json_for_html(mixed $data): string
{
    return (string)json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function random_id(int $bytes = 6): string
{
    return bin2hex(random_bytes($bytes));
}

function slugify(string $text): string
{
    $s = strtolower(trim($text));
    $s = (string)preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr(trim($s, '-'), 0, 60), '-');
}

function valid_slug(string $slug): bool
{
    return strlen($slug) <= 60 && (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function valid_id(string $id): bool
{
    return (bool)preg_match('/^[a-f0-9]{12}$/', $id);
}

function is_https(): bool
{
    // Behind a proxy that hides https from PHP, set TEENY_FORCE_HTTPS in config.php (see config.sample.php).
    if (defined('TEENY_FORCE_HTTPS') && TEENY_FORCE_HTTPS) return true;
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Only REMOTE_ADDR is trusted, because forwarded headers can be forged. */
function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function teeny_installed(): bool
{
    return is_file(Store::path('site')) && is_file(Store::path('users'));
}

/** Site settings (cached per request). Pass an array to replace the cache after saving. */
function site(?array $replace = null): array
{
    static $site = null;
    if ($replace !== null) {
        $site = $replace;
    }
    if ($site === null) {
        $site = Store::read('site', []);
    }
    return $site;
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['msg' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

function str_limit(string $s, int $max): string
{
    if (strlen($s) <= $max) return $s;
    if (function_exists('mb_substr')) return mb_substr($s, 0, $max, 'UTF-8');
    preg_match('/^.{0,' . min($max, 60000) . '}/us', $s, $m);
    return $m[0] ?? '';
}

function fmt_date(int $ts): string
{
    return $ts > 0 ? date('j M Y, H:i', $ts) : 'Never';
}

function fmt_bytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
}

function send_security_headers(string $context): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    $common = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
    if ($context === 'admin') {
        header("Content-Security-Policy: $common; img-src 'self' data: blob:; frame-src 'self'; connect-src 'self'");
    } else {
        header("Content-Security-Policy: $common; img-src 'self' data:; frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com");
    }
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/** Small inline SVG icon set used by the admin. */
function icon(string $name): string
{
    static $paths = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
        'pages'    => '<path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/>',
        'media'    => '<rect x="3" y="4" width="18" height="16" rx="1.5"/><circle cx="9" cy="10" r="1.8"/><path d="m21 16-5-5-9 9"/>',
        'brush'    => '<path d="M18.4 3.6a2 2 0 0 1 2.9 2.9L12 15.8 8.2 12z"/><path d="M8 13c-2.5 0-4 1.6-4 4 0 1.4-.6 2.3-1.5 3 3.9.6 7.5-.4 7.5-4.5z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.4 3.2-5.5 6.5-5.5s5.9 2.1 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 14.8c1.6.8 2.7 2.5 3 5.2"/>',
        'backup'   => '<path d="M12 3v12m0 0-4-4m4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 4-6.5 8-6.5s7.2 2.5 8 6.5"/>',
        'logout'   => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'copy'     => '<rect x="8" y="8" width="12" height="12" rx="1.5"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'star'     => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'up'       => '<path d="m6 15 6-6 6 6"/>',
        'down'     => '<path d="m6 9 6 6 6-6"/>',
        'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
        'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'upload'   => '<path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'inbox'    => '<path d="M3 13.5 5.5 5h13l2.5 8.5V19a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M3 13.5h5l1.5 2.5h5l1.5-2.5h5"/>',
        'shield'   => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8.3 7.5 9.5 4.3-1.2 7.5-5 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
        'lock'     => '<rect x="5" y="10.5" width="14" height="10" rx="1.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'reply'    => '<path d="M10 8 5 12.5l5 4.5"/><path d="M5 12.5h9a5 5 0 0 1 5 5V19"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'plug'     => '<path d="M9 3v5M15 3v5"/><path d="M6 8h12v3a6 6 0 0 1-12 0z"/><path d="M12 17v4"/>',
        'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

/** Friendly page status label: "Live" for published pages, "Draft" otherwise. */
function status_label(string $status): string
{
    return $status === 'published' ? 'Live' : 'Draft';
}

/** The Teeny CMS logo as inline SVG (from brand/logo.svg): the "t" mark, optionally with the "teeny" wordmark. */
function teeny_logo(bool $wordmark = true, string $class = 'teeny-logo'): string
{
    $mark = '<path fill="#312f3b" d="M15,0h34c8.3,0,15,6.7,15,15v34c0,8.3-6.7,15-15,15H15C6.7,64,0,57.3,0,49V15C0,6.7,6.7,0,15,0z"/><path fill="#7a9cb4" d="M48.5,13.2l0.3-1.8c0.1-0.8-0.1-1.5-0.6-2.1c-0.5-0.6-1.2-0.9-2-0.9H20c-1.3,0-2.3,0.9-2.5,2.1l-0.3,1.8 c-0.1,0.8,0.1,1.5,0.6,2.1c0.5,0.6,1.2,0.9,2,0.9h9.1l-6.6,37.4c-0.1,0.8,0.1,1.5,0.6,2.1c0.5,0.6,1.1,0.8,1.9,0.9h1.9 c1.3,0,2.3-0.9,2.5-2.1l6.7-38.3H46C47.2,15.3,48.3,14.4,48.5,13.2z"/><circle fill="#7a9cb4" cx="38.4" cy="43" r="3.6"/><path fill="#3c6b82" d="M27.5,46.6l-5.3,6c-0.2,0.8,0,1.6,0.5,2.2c0.5,0.6,1.2,0.9,2,0.9h2c1.6,0,1.9-0.8,2.7-2l5.4-7.1H27.5z"/>';
    if (!$wordmark) {
        return '<svg class="' . e($class) . '" viewBox="0 0 64 64" role="img" aria-label="Teeny CMS">' . $mark . '</svg>';
    }
    $word = '<g fill="currentColor"><path d="M103.6,11.9l0.2-1.4c0.1-0.6-0.1-1.2-0.4-1.6s-0.9-0.7-1.5-0.7H81.4c-1,0-1.8,0.7-2,1.7l-0.2,1.4c-0.1,0.6,0.1,1.2,0.4,1.6 s0.9,0.7,1.5,0.7h7.1l-5.1,29c-0.1,0.6,0.1,1.2,0.4,1.6s0.9,0.7,1.5,0.7h1.5c1,0,1.8-0.7,2-1.7l5.2-29.7h7.8 C102.6,13.5,103.4,12.8,103.6,11.9z"/><path d="M114.6,16.9c-3.5,0-6.6,1.3-9.3,3.8c-2.6,2.4-4.3,5.7-5,9.7c-0.9,5-0.4,8.6,1.4,11.2c1.9,2.6,4.7,3.9,8.5,4 c2.3,0,4.4-0.5,6.4-1.5c1.9-1,3.6-2.5,4.9-4.4c0.3-0.5,0.4-1,0.3-1.6c-0.1-0.5-0.5-1-0.9-1.3l-1.1-0.7c-0.3-0.2-0.7-0.3-1-0.3 c-0.6,0-1.2,0.3-1.6,0.8c-0.9,1.2-1.9,2.1-3,2.7c-1.1,0.6-2.4,0.9-3.7,0.9c-2.7,0-3.7-1.1-4.3-2c-0.8-1.1-1-3-0.8-5.4h16.1 c1,0,1.8-0.7,2-1.7l0.1-0.3c0.8-4.3,0.4-7.5-1.1-10C121.3,19,119.1,16.9,114.6,16.9z M118.6,27.4h-11.7c0.6-1.3,1.4-2.4,2.4-3.3 c1.5-1.3,3.1-1.9,5.1-1.9c2.2,0,3.1,0.8,3.5,1.5C118.4,24.6,118.6,25.8,118.6,27.4z"/><path d="M142.6,16.9c-3.5,0-6.6,1.3-9.3,3.8c-2.6,2.4-4.3,5.7-5,9.7c-0.9,5-0.4,8.6,1.4,11.2c1.9,2.6,4.7,3.9,8.5,4 c2.3,0,4.4-0.5,6.4-1.5c1.9-1,3.6-2.5,4.9-4.4c0.3-0.5,0.4-1,0.3-1.6s-0.5-1-0.9-1.3l-1.1-0.7c-0.3-0.2-0.7-0.3-1-0.3 c-0.6,0-1.2,0.3-1.6,0.8c-0.9,1.2-1.9,2.1-3,2.7c-1.1,0.6-2.4,0.9-3.7,0.9c-2.7,0-3.7-1.1-4.3-2c-0.8-1.1-1-3-0.8-5.4h16.1 c1,0,1.8-0.7,2-1.7l0.1-0.3c0.8-4.3,0.4-7.5-1.1-10C149.3,19,147,16.9,142.6,16.9z M134.8,27.4c0.6-1.3,1.4-2.4,2.4-3.3 c1.5-1.3,3.1-1.9,5.1-1.9c2.2,0,3.1,0.8,3.5,1.5c0.6,0.8,0.8,2.1,0.8,3.7H134.8z"/><path d="M178.5,20.3c-1-1.5-3.1-3.4-7.2-3.4c-2.1,0-4.1,0.5-5.8,1.4c-0.1,0.1-0.3,0.1-0.4,0.2c-0.1-0.1-0.1-0.2-0.2-0.3 c-0.4-0.5-0.9-0.7-1.5-0.7h-1.4c-1,0-1.8,0.7-2,1.7l-4.1,23.4c-0.1,0.6,0.1,1.2,0.4,1.6s0.9,0.7,1.5,0.7h1.4c1,0,1.8-0.7,2-1.7 l2.6-14.6c0.3-1.9,1.2-3.4,2.5-4.6c1.3-1.2,2.9-1.8,4.7-1.8c1.9,0,2.6,0.7,2.9,1.2c0.6,1,0.8,2.5,0.4,4.5l-2.6,14.6 c-0.1,0.6,0.1,1.2,0.4,1.6s0.9,0.7,1.5,0.7h1.4c1,0,1.8-0.7,2-1.7l2.6-14.7C180.3,25.1,179.9,22.4,178.5,20.3z"/><path d="M210.2,18.5c-0.4-0.6-1-1-1.7-1H207c-0.7,0-1.4,0.4-1.7,1L195.6,35l-3.2-15.9c-0.2-0.9-1-1.6-2-1.6h-1.6 c-0.6,0-1.2,0.3-1.5,0.7c-0.4,0.5-0.5,1.1-0.4,1.7l4.5,22.3l-2.9,4.9c-1,1.7-1.7,2.5-2.2,2.8c-0.5,0.4-0.9,0.6-1.3,0.6 c-0.3,0-0.5,0-0.6-0.1c-0.1,0-0.3-0.1-0.5-0.3c-0.3-0.3-0.8-0.4-1.2-0.4c-0.1,0-0.2,0-0.3,0c-0.5,0.1-1,0.4-1.3,0.8l-0.9,1.3 c-0.6,0.9-0.4,2.1,0.5,2.8c0.7,0.5,1.4,0.8,2.1,1c0.6,0.2,1.3,0.2,2.1,0.2c1.8,0,3.4-0.6,4.8-1.8c1.3-1,2.5-2.5,3.7-4.6l16.9-29 C210.6,19.9,210.6,19.1,210.2,18.5z"/></g>';
    return '<svg class="' . e($class) . '" viewBox="0 0 220 64" role="img" aria-label="Teeny CMS">' . $mark . $word . '</svg>';
}
