<?php
/**
 * Teeny CMS: public site.
 */
declare(strict_types=1);

define('TEENY_DEPTH', 0);
require __DIR__ . '/core/bootstrap.php';

if (!teeny_installed()) {
    redirect(url('install.php'));
}

send_security_headers('site');
$site = site();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Resolve the requested slug from ?page= or the clean URL path.
$slug = '';
if (isset($_GET['page']) && is_string($_GET['page'])) {
    $slug = $_GET['page'];
} else {
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $path = rawurldecode($path);
    if (TEENY_BASE !== '' && str_starts_with($path, TEENY_BASE)) {
        $path = substr($path, strlen(TEENY_BASE));
    }
    $slug = trim($path, '/');
    if ($slug === 'index.php') $slug = '';
}

if ($slug === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    if (empty($site['maintenance']['on'])) {
        foreach (Pages::index() as $id => $m) {
            if (($m['status'] ?? '') !== 'published' || !empty($m['protected'])) continue;
            $loc = absolute_url(page_url(['id' => (string)$id] + $m));
            if ($loc === '') continue;
            echo '  <url><loc>' . e($loc) . '</loc><lastmod>' . date('Y-m-d', (int)($m['updated'] ?? time())) . '</lastmod></url>' . "\n";
        }
        if (News::enabled($site)) {
            $list = absolute_url(News::url());
            if ($list !== '') echo '  <url><loc>' . e($list) . '</loc></url>' . "\n";
            foreach (News::posts() as $id => $m) {
                $loc = absolute_url(page_url(['id' => (string)$id] + $m));
                if ($loc !== '') echo '  <url><loc>' . e($loc) . '</loc><lastmod>' . date('Y-m-d', (int)($m['updated'] ?? time())) . '</lastmod></url>' . "\n";
            }
        }
    }
    echo '</urlset>';
    exit;
}

if ($slug === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /admin/\n";
    $sm = absolute_url(url('sitemap.xml'));
    if ($sm !== '') echo "Sitemap: $sm\n";
    exit;
}

$viewer = Auth::hasCookie() ? Auth::user() : null;

// Maintenance mode: visitors see a "coming soon" page, logged-in users see the real site.
$maintenance = !empty($site['maintenance']['on']);
if ($maintenance && $viewer === null) {
    http_response_code(503);
    header('Retry-After: 3600');
    header('Cache-Control: no-store');
    echo Renderer::maintenance($site);
    exit;
}

// Site search (a built-in add-on, switched on in Plugins). Never cached.
if (isset($_GET['search']) && Search::enabled($site) && $method === 'GET') {
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
    echo Renderer::searchPage($site, Search::query($_GET['search']));
    exit;
}

// News (a built-in add-on, switched on in Plugins): the list, categories and the RSS feed. Never cached,
// so scheduled posts appear on their day. Single posts continue below like any page.
$newsRoute = News::enabled($site) ? News::route($slug) : null;
if ($newsRoute && $newsRoute['type'] === 'feed') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo News::feed($site);
    exit;
}
if ($newsRoute && in_array($newsRoute['type'], ['list', 'category'], true)) {
    $cat = (string)($newsRoute['category'] ?? '');
    if ($cat !== '' && !isset(News::categories($site)[$cat])) {
        http_response_code(404);
        echo Renderer::notFound($site);
        exit;
    }
    header('Cache-Control: no-cache');
    header('Content-Type: text/html; charset=utf-8');
    echo Renderer::newsList($site, $cat, (int)$newsRoute['page']);
    exit;
}

// Only plain anonymous GET requests use the page cache.
$queryKeys = array_keys($_GET);
$plainQuery = $queryKeys === [] || $queryKeys === ['page'];
$cacheable = $viewer === null && $method === 'GET' && $plainQuery && !$maintenance;

if ($cacheable) {
    $cached = Cache::get('page:' . $slug);
    if ($cached !== null) {
        Stats::record($slug);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Teeny-Cache: hit');
        echo $cached;
        exit;
    }
}

$page = null;
if ($newsRoute && $newsRoute['type'] === 'post') {
    $page = News::bySlug((string)$newsRoute['slug']);
} elseif ($slug === '') {
    $page = Pages::home();
} elseif (valid_slug($slug)) {
    $page = Pages::bySlug($slug);
}

$visible = $page && ($page['status'] === 'published' || $viewer !== null);
// A post with a future date stays hidden from visitors until that day.
if ($visible && ($page['kind'] ?? '') === 'post' && $viewer === null && (string)($page['date'] ?? '') > date('Y-m-d')) $visible = false;
if (!$visible) {
    http_response_code(404);
    echo Renderer::notFound($site);
    exit;
}

$protected = ($page['protect'] ?? '') !== '';
$formName = $method === 'POST' ? (string)($_POST['_teeny_form'] ?? '') : '';

// Password-protected page
if ($protected && $viewer === null) {
    header('Cache-Control: no-store, private');
    $error = '';
    if ($formName === 'unlock') {
        Auth::start();
        if (!Auth::checkCsrf()) {
            $error = 'Your session expired. Please try again.';
        } else {
            $r = Pages::unlock($page, substr((string)($_POST['password'] ?? ''), 0, 100));
            if ($r === true) redirect(page_url($page));
            $error = $r;
        }
    }
    if (!Pages::unlocked($page)) {
        if ($method === 'POST' && $formName !== 'unlock') http_response_code(403);
        echo Renderer::gate($site, $page, $error);
        exit;
    }
}

// Contact form and newsletter submissions
if ($formName === 'contact' || $formName === 'newsletter' || $formName === 'rsvp') {
    $state = match ($formName) { 'contact' => Inbox::submit($page, $_POST), 'newsletter' => Inbox::subscribe($page, $_POST), default => Inbox::rsvp($page, $_POST) };
    if (!empty($state['sent'])) {
        redirect(page_url($page) . (str_contains(page_url($page), '?') ? '&' : '?') . 'sent=' . rawurlencode($state['block']) . '#' . $formName . '-' . rawurlencode($state['block']));
    }
    Renderer::$form = $state;
    header('Cache-Control: no-store');
} elseif (isset($_GET['sent']) && is_string($_GET['sent']) && preg_match('/^[a-z0-9]{6,16}$/', $_GET['sent'])) {
    Renderer::$form = ['block' => $_GET['sent'], 'sent' => true];
    header('Cache-Control: no-store');
}

$opts = [];
if ($page['status'] !== 'published') {
    $opts['notice'] = 'Draft preview. Only signed-in users can see this page.';
} elseif (($page['kind'] ?? '') === 'post' && News::isScheduled($page)) {
    $opts['notice'] = 'Scheduled: visitors will see this post from ' . News::niceDate((string)$page['date']) . '.';
} elseif ($maintenance) {
    $opts['notice'] = 'Maintenance mode is on. Visitors currently see the “Coming soon” page.';
} elseif ($protected && $viewer !== null) {
    $opts['notice'] = 'This page is password-protected. You see it because you are logged in.';
}
if ($protected) $opts['noindex'] = true;
if ($opts || $viewer !== null) header('Cache-Control: no-store, private');

$html = Renderer::page($site, $page, $opts);
header('Content-Type: text/html; charset=utf-8');
if ($viewer === null && $method === 'GET') {
    Stats::record($slug);
}
if ($cacheable && $page['status'] === 'published' && !$protected && Renderer::$form === []) {
    Cache::put('page:' . $slug, $html);
}
echo $html;
