<?php
declare(strict_types=1);

/**
 * Site search: a built-in add-on, off until an owner switches it on in Plugins.
 *
 * There's no database and no index to keep up to date: a search reads the page files,
 * so results are always current. Only live pages are searched; drafts and
 * password-protected pages never appear. Matching ignores case and accents in the query
 * as far as PHP's regular expressions allow, and every word must appear on the page.
 */
final class Search
{
    public const MAX_QUERY = 100;
    public const MAX_RESULTS = 30;

    /** Keys in a section that hold settings or addresses, not words visitors read. */
    private const SKIP_KEYS = ['id', 'type', 'url', 'image', 'avatar', 'logo', 'photo', 'style', 'side', 'level', 'kind', 'place',
        'trigger', 'often', 'featured', 'boxAlign', 'boxWidth', 'icon', 'network', 'phone', 'language', 'newTab', 'columns', 'date'];

    public static function enabled(?array $site = null): bool
    {
        $site ??= site();
        return !empty($site['addons']['search']);
    }

    /** Show a search icon in the site header (only while the add-on is on). */
    public static function inHeader(?array $site = null): bool
    {
        $site ??= site();
        return self::enabled($site) && !empty($site['addons']['searchHeader']);
    }

    public static function url(string $query = ''): string
    {
        return url('') . '?search=' . rawurlencode($query);
    }

    /** Clean what the visitor typed. */
    public static function query(mixed $raw): string
    {
        $q = is_string($raw) ? $raw : '';
        $q = trim((string)preg_replace('/\s+/u', ' ', strip_tags($q)));
        if (!preg_match('//u', $q)) return '';
        return substr($q, 0, self::MAX_QUERY);
    }

    /**
     * @return array<int, array{title: string, url: string, snippet: string}> snippet is safe HTML with <mark> around matches
     */
    public static function run(string $query): array
    {
        $words = self::words($query);
        if (!$words) return [];
        $hits = [];
        foreach (Pages::index() as $id => $meta) {
            if (($meta['status'] ?? '') !== 'published' || !empty($meta['protected'])) continue;
            $page = Pages::get((string)$id);
            if (!$page || ($page['protect'] ?? '') !== '') continue;
            $title = (string)($page['title'] ?? '');
            $body = self::pageText($page);
            $all = $title . "\n" . ($page['description'] ?? '') . "\n" . $body;
            $score = 0;
            foreach ($words as $w) {
                $re = '/' . preg_quote($w, '/') . '/iu';
                $inAll = preg_match_all($re, $all);
                if (!$inAll) continue 2; // every word must appear
                $score += $inAll + 5 * preg_match_all($re, $title);
            }
            $hits[] = ['score' => $score, 'title' => $title, 'url' => page_url($page), 'snippet' => self::snippet($body !== '' ? $body : (string)($page['description'] ?? ''), $words)];
        }
        usort($hits, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_map(static fn($h) => ['title' => $h['title'], 'url' => $h['url'], 'snippet' => $h['snippet']], array_slice($hits, 0, self::MAX_RESULTS));
    }

    /** Words to look for: at least 2 characters, at most 8 words. */
    public static function words(string $query): array
    {
        $out = [];
        foreach (preg_split('/\s+/u', $query) ?: [] as $w) {
            $w = trim($w, " \t\"'.,;:!?()[]{}");
            if ($w !== '' && preg_match('/^.{2,}$/u', $w) && !in_array(strtolower($w), $out, true)) $out[] = $w;
        }
        return array_slice($out, 0, 8);
    }

    /** All the readable text on a page, section by section. */
    public static function pageText(array $page): string
    {
        $parts = [];
        foreach (is_array($page['blocks'] ?? null) ? $page['blocks'] : [] as $b) {
            if (is_array($b)) self::collect($b, $parts);
        }
        return trim(implode("\n", $parts));
    }

    private static function collect(array $value, array &$parts): void
    {
        foreach ($value as $k => $v) {
            if (is_string($k) && in_array($k, self::SKIP_KEYS, true)) continue;
            if (is_array($v)) { self::collect($v, $parts); continue; }
            if (!is_string($v) || $v === '') continue;
            $text = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>'], ["\n", "\n", "\n"], $v)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($text !== '' && !preg_match('~^(https?://|mailto:|tel:|#|/)\S*$~i', $text)) $parts[] = $text;
        }
    }

    /** About 200 characters around the first match, escaped, with the words marked. */
    public static function snippet(string $text, array $words): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        if ($text === '') return '';
        $pos = null;
        foreach ($words as $w) {
            if (preg_match('/' . preg_quote($w, '/') . '/iu', $text, $m, PREG_OFFSET_CAPTURE)) { $pos = $pos === null ? $m[0][1] : min($pos, $m[0][1]); }
        }
        $start = max(0, (int)$pos - 70);
        // Move to a character boundary, then to the next space so no word is cut in half.
        while ($start > 0 && (ord($text[$start]) & 0xC0) === 0x80) $start--;
        if ($start > 0) { $sp = strpos($text, ' ', $start); $start = $sp !== false && $sp < (int)$pos ? $sp + 1 : $start; }
        $len = 210;
        $cut = substr($text, $start, $len);
        while ($cut !== '' && !preg_match('//u', $cut)) $cut = substr($cut, 0, -1); // drop a broken last character
        if ($start + $len < strlen($text)) { $sp = strrpos($cut, ' '); if ($sp !== false && $sp > 120) $cut = substr($cut, 0, $sp); $cut .= ' …'; }
        if ($start > 0) $cut = '… ' . $cut;
        $html = e($cut);
        foreach ($words as $w) {
            $html = (string)preg_replace('/(' . preg_quote(e($w), '/') . ')(?![^<]*>)/iu', '<mark>$1</mark>', $html);
        }
        return $html;
    }
}
