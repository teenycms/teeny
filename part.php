<?php
/**
 * Teeny CMS: sandboxed frame for interactive plugin sections.
 *
 * A plugin's widget.js only ever runs in this document, which is locked down twice:
 *  1. the page embeds it with <iframe sandbox="allow-scripts"> (no same-origin access), and
 *  2. this response sends "Content-Security-Policy: sandbox allow-scripts", so even when the
 *     URL is opened directly, the browser gives it a unique, empty origin.
 * The script therefore cannot read cookies, the admin session, the page around it, or call
 * the Studio. It also cannot contact other websites (connect-src 'none'), open pop-ups,
 * submit forms or navigate the page.
 */
declare(strict_types=1);

define('TEENY_DEPTH', 0);
require __DIR__ . '/core/bootstrap.php';

function widget_fail(int $code): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit;
}

if (!teeny_installed()) widget_fail(404);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');

// Only serve inside a frame (browsers send Sec-Fetch-Dest; older ones are still covered by the CSP sandbox).
$dest = (string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '');
if ($dest !== '' && $dest !== 'iframe') widget_fail(403);

$pageId = (string)($_GET['page'] ?? '');
$blockId = (string)($_GET['block'] ?? '');
if (!valid_id($pageId) || !preg_match('/^[a-z0-9]{6,16}$/', $blockId)) widget_fail(404);
$page = Pages::get($pageId);
if (!$page) widget_fail(404);

// Same visibility rules as the page itself.
$viewer = Auth::hasCookie() ? Auth::user() : null;
$site = site();
if ($viewer === null) {
    if ($page['status'] !== 'published' || !empty($site['maintenance']['on'])) widget_fail(404);
    if (($page['protect'] ?? '') !== '' && !Pages::unlocked($page)) widget_fail(403);
}

$block = null;
foreach ($page['blocks'] as $b) {
    if ($b['id'] === $blockId && str_starts_with((string)$b['type'], 'plugin-')) $block = $b;
}
if (!$block) widget_fail(404);
$id = substr($block['type'], 7);
$manifest = Plugins::enabled()[$id] ?? null;
if (!$manifest || !isset($manifest['widget']) || !is_file(Plugins::dir($id) . '/widget.js')) widget_fail(404);

// In a sandboxed document the origin is empty, so 'self' would match nothing. Instead the policy
// names this site's address and the exact script files allowed to run, and nothing else.
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/i', $host)) widget_fail(400);
$origin = (is_https() ? 'https://' : 'http://') . $host;
$scripts = $origin . url('assets/widget-frame.js') . ' ' . $origin . url('plugins/' . $id . '/widget.js');
header("Content-Security-Policy: sandbox allow-scripts; default-src 'none'; script-src $scripts; style-src $origin 'unsafe-inline'; img-src $origin data:; font-src $origin; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'self'");

$data = Plugins::viewData($block, $manifest['section']['fields']);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=60');
?><!doctype html>
<html lang="<?= e($site['lang'] ?? 'en') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($manifest['name']) ?></title>
<style><?= Theme::css($site) ?></style>
<link rel="stylesheet" href="<?= e(asset('assets/widget-base.css')) ?>">
<style><?= Plugins::css([$id], true) ?></style>
</head>
<body class="tc-widget-body">
<div class="tc-plugin tc-plugin--<?= e($id) ?>"><?= Plugins::html($id, $block) ?></div>
<script type="application/json" id="teeny-data"><?= json_for_html($data) ?></script>
<script src="<?= e(asset('assets/widget-frame.js')) ?>"></script>
<script src="<?= e(url('plugins/' . $id . '/widget.js')) ?>?v=<?= e(rawurlencode($manifest['version'])) ?>"></script>
</body>
</html>
