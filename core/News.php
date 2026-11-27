<?php
declare(strict_types=1);

/**
 * News: a light blog, as a built-in add-on (off until an owner switches it on in Plugins).
 *
 * Posts are pages with kind "post" plus a date, cover image, summary and category, so they use
 * the same editor, storage, version history, backups and search. They live at /news/{slug};
 * the list is at /news, categories at /news/category/{slug} and the RSS feed at /news/feed.xml.
 * A post appears once it is published and its date has come (dates are YYYY-MM-DD, in UTC).
 */
final class News
{
    public const PER_PAGE = 10;
    public const MAX_CATEGORIES = 20;
    public const FEED_ITEMS = 20;

    public static function enabled(?array $site = null): bool
    {
        $site ??= site();
        return !empty($site['addons']['news']);
    }

    /** slug => label */
    public static function categories(?array $site = null): array
    {
        $site ??= site();
        $out = [];
        foreach (is_array($site['newsCategories'] ?? null) ? $site['newsCategories'] : [] as $slug => $label) {
            if (is_string($slug) && preg_match('/^[a-z0-9-]{1,40}$/', $slug) && is_string($label) && $label !== '') $out[$slug] = $label;
        }
        return array_slice($out, 0, self::MAX_CATEGORIES, true);
    }

    /* ---------- addresses ---------- */

    private static function pretty(): bool
    {
        return !empty(site()['prettyUrls']);
    }

    public static function url(string $slug = ''): string
    {
        if (self::pretty()) return url('news' . ($slug !== '' ? '/' . rawurlencode($slug) : ''));
        return url('?news=' . rawurlencode($slug));
    }

    public static function categoryUrl(string $category, int $page = 1): string
    {
        $base = self::pretty() ? url('news/category/' . rawurlencode($category)) : url('?news=&category=' . rawurlencode($category));
        return $page > 1 ? $base . (str_contains($base, '?') ? '&' : '?') . 'pg=' . $page : $base;
    }

    public static function listUrl(int $page = 1): string
    {
        $base = self::url();
        return $page > 1 ? $base . (str_contains($base, '?') ? '&' : '?') . 'pg=' . $page : $base;
    }

    public static function feedUrl(): string
    {
        return self::pretty() ? url('news/feed.xml') : url('?news=feed.xml');
    }

    /**
     * What the visitor asked for, or null when this isn't a News address.
     * @return array{type: string, slug?: string, category?: string, page: int}|null
     */
    public static function route(string $path): ?array
    {
        $page = max(1, min(999, (int)($_GET['pg'] ?? 1)));
        if (isset($_GET['news']) && is_string($_GET['news'])) {
            $q = $_GET['news'];
            $cat = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
            if ($q === 'feed.xml') return ['type' => 'feed', 'page' => 1];
            if ($q === '') return $cat !== '' ? ['type' => 'category', 'category' => $cat, 'page' => $page] : ['type' => 'list', 'page' => $page];
            return ['type' => 'post', 'slug' => $q, 'page' => 1];
        }
        if ($path === 'news') return ['type' => 'list', 'page' => $page];
        if ($path === 'news/feed.xml') return ['type' => 'feed', 'page' => 1];
        if (preg_match('~^news/category/([a-z0-9-]{1,40})$~', $path, $m)) return ['type' => 'category', 'category' => $m[1], 'page' => $page];
        if (preg_match('~^news/([a-z0-9-]{1,60})$~', $path, $m)) return ['type' => 'post', 'slug' => $m[1], 'page' => 1];
        return null;
    }

    /* ---------- posts ---------- */

    /** Is a post (index entry or full post) live for visitors right now? */
    public static function isLive(array $p): bool
    {
        return ($p['status'] ?? '') === 'published' && empty($p['protected']) && ($p['protect'] ?? '') === ''
            && (string)($p['date'] ?? '') <= date('Y-m-d');
    }

    /** Is this post published but waiting for its date? */
    public static function isScheduled(array $p): bool
    {
        return ($p['status'] ?? '') === 'published' && (string)($p['date'] ?? '') > date('Y-m-d');
    }

    /**
     * Posts from the index, newest first: id => meta.
     * @param bool $liveOnly only posts visitors can see now
     */
    public static function posts(bool $liveOnly = true, string $category = ''): array
    {
        $out = [];
        foreach (Pages::all() as $id => $m) {
            if (($m['kind'] ?? '') !== 'post') continue;
            if ($liveOnly && !self::isLive($m)) continue;
            if ($category !== '' && ($m['category'] ?? '') !== $category) continue;
            $out[(string)$id] = $m;
        }
        uasort($out, static fn($a, $b) => [($b['date'] ?? ''), ($b['created'] ?? 0)] <=> [($a['date'] ?? ''), ($a['created'] ?? 0)]);
        return $out;
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (Pages::all() as $id => $m) {
            if (($m['kind'] ?? '') === 'post' && ($m['slug'] ?? '') === $slug) return Pages::get((string)$id);
        }
        return null;
    }

    /** [newer, older] live neighbours of a post, as ['title', 'url'] or null. */
    public static function neighbours(array $post): array
    {
        $ids = array_keys(self::posts());
        $i = array_search($post['id'], $ids, true);
        if ($i === false) return [null, null];
        $all = self::posts();
        $make = static fn($id) => $id === null ? null : ['title' => $all[$id]['title'], 'url' => page_url(['id' => $id] + $all[$id])];
        return [$make($ids[$i - 1] ?? null), $make($ids[$i + 1] ?? null)];
    }

    /** "12 March 2026" */
    public static function niceDate(string $ymd): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return '';
        return date('j F Y', (int)mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]));
    }

    /* ---------- RSS ---------- */

    public static function feed(array $site): string
    {
        $abs = static fn(string $u) => absolute_url($u) ?: $u;
        $x = static fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $o = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
        $o .= '<title>' . $x(($site['title'] ?? 'News') . ' · News') . '</title><link>' . $x($abs(self::url())) . '</link>';
        $o .= '<description>' . $x((string)($site['description'] ?? '') ?: 'Latest news') . '</description>';
        $o .= '<language>' . $x((string)($site['lang'] ?? 'en')) . '</language>';
        if (absolute_url(self::feedUrl()) !== '') $o .= '<atom:link href="' . $x(absolute_url(self::feedUrl())) . '" rel="self" type="application/rss+xml"/>';
        foreach (array_slice(self::posts(), 0, self::FEED_ITEMS, true) as $id => $m) {
            $link = $abs(page_url(['id' => $id] + $m));
            $ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($m['date'] ?? '')) ? strtotime($m['date'] . ' 00:00:00 UTC') : (int)($m['created'] ?? time());
            $o .= '<item><title>' . $x($m['title']) . '</title><link>' . $x($link) . '</link><guid isPermaLink="true">' . $x($link) . '</guid>'
                . '<pubDate>' . gmdate('D, d M Y H:i:s', (int)$ts) . ' +0000</pubDate>'
                . (($m['summary'] ?? '') !== '' ? '<description>' . $x($m['summary']) . '</description>' : '')
                . ((($m['category'] ?? '') !== '' && isset(self::categories($site)[$m['category']])) ? '<category>' . $x(self::categories($site)[$m['category']]) . '</category>' : '')
                . '</item>';
        }
        return $o . '</channel></rss>';
    }
}
