<?php
declare(strict_types=1);

/**
 * Pages: an index (data/pages.json.php) plus one file per page (data/pages/{id}.json.php).
 * Each save keeps the previous version in data/revisions/{id}/.
 */
final class Pages
{
    public const MAX_REVISIONS = 20;
    public const TEMPLATES = [
        'blank'     => ['label' => 'Blank page', 'desc' => 'Start from nothing'],
        'landing'   => ['label' => 'Landing page', 'desc' => 'Hero, features, testimonial, call to action'],
        'portfolio' => ['label' => 'Portfolio', 'desc' => 'Introduction, selected work, contact'],
        'docs'      => ['label' => 'Documentation', 'desc' => 'Headings, text and code examples'],
        'gallery'   => ['label' => 'Gallery', 'desc' => 'Image grid with captions'],
        'linkinbio' => ['label' => 'Link in bio', 'desc' => 'Profile photo and a list of links'],
        'shop'      => ['label' => 'Shop', 'desc' => 'Product catalog with order buttons, delivery and FAQ'],
        'product'   => ['label' => 'Product page', 'desc' => 'One product with photos, details and an order button'],
        'event'     => ['label' => 'Event', 'desc' => 'Wedding, concert or camp: details, schedule, tickets and RSVP'],
    ];

    public static function index(): array
    {
        $d = Store::read('pages', ['pages' => []]);
        $pages = is_array($d['pages'] ?? null) ? $d['pages'] : [];
        uasort($pages, static fn($a, $b) => [($a['order'] ?? 0), strtolower($a['title'] ?? '')] <=> [($b['order'] ?? 0), strtolower($b['title'] ?? '')]);
        return $pages;
    }

    public static function get(string $id): ?array
    {
        if (!valid_id($id)) return null;
        $p = Store::read('pages/' . $id);
        return is_array($p) ? $p : null;
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (self::index() as $id => $meta) {
            if (($meta['slug'] ?? '') === $slug) return self::get((string)$id);
        }
        return null;
    }

    public static function home(): ?array
    {
        $id = (string)(site()['home'] ?? '');
        $p = $id !== '' ? self::get($id) : null;
        if ($p) return $p;
        foreach (self::index() as $pid => $meta) {
            if (($meta['status'] ?? '') === 'published') return self::get((string)$pid);
        }
        return null;
    }

    /** Published pages shown in the site menu. */
    public static function nav(): array
    {
        $out = [];
        foreach (self::index() as $id => $m) {
            if (($m['status'] ?? '') === 'published' && !empty($m['nav'])) {
                $out[] = ['id' => (string)$id] + $m;
            }
        }
        return $out;
    }

    /** Sanitize and save a page. Returns the stored page. */
    public static function save(array $input, string $userId, bool $trustHash = false): array
    {
        $existing = isset($input['id']) && is_string($input['id']) ? self::get($input['id']) : null;
        $id = $existing['id'] ?? random_id(6);

        // Page password: kept unless explicitly changed or removed. Only a hash is stored.
        $protect = (string)($existing['protect'] ?? '');
        if ($trustHash && is_string($input['protect'] ?? null) && preg_match('/^\$(2y|argon2id?)\$/', $input['protect'])) {
            $protect = $input['protect'];
        }
        if (!empty($input['removePassword'])) $protect = '';
        $newPw = is_string($input['newPassword'] ?? null) ? $input['newPassword'] : '';
        if ($newPw !== '') {
            if (strlen($newPw) < 4 || strlen($newPw) > 100) {
                throw new RuntimeException('Page passwords must be 4 to 100 characters.');
            }
            $protect = password_hash($newPw, PASSWORD_DEFAULT);
        }

        $title = Sanitizer::text($input['title'] ?? '', 120);
        if ($title === '') $title = 'Untitled';
        $slug = slugify(is_string($input['slug'] ?? null) && $input['slug'] !== '' ? $input['slug'] : $title);
        if ($slug === '') $slug = 'page-' . substr($id, 0, 6);
        $slug = self::uniqueSlug($slug, $id);

        $layout = (string)($input['layout'] ?? '');
        $page = [
            'id'          => $id,
            'title'       => $title,
            'slug'        => $slug,
            'status'      => ($input['status'] ?? '') === 'published' ? 'published' : 'draft',
            'description' => Sanitizer::text($input['description'] ?? '', 300),
            'nav'         => !empty($input['nav']),
            'order'       => Sanitizer::int($input['order'] ?? 0, -999, 999, 0),
            'layout'      => array_key_exists($layout, Theme::ALIGNS) ? $layout : '',
            'hideHeader'  => !empty($input['hideHeader']),
            'hideFooter'  => !empty($input['hideFooter']),
            'protect'     => $protect,
            'blocks'      => Blocks::sanitize($input['blocks'] ?? []),
            'created'     => (int)($existing['created'] ?? time()),
            'updated'     => time(),
            'author'      => $userId,
        ];

        if ($existing) {
            self::addRevision($existing);
        }
        Store::write('pages/' . $id, $page);
        Store::update('pages', static function (array $d) use ($page): array {
            $d['pages'][$page['id']] = [
                'title' => $page['title'], 'slug' => $page['slug'], 'status' => $page['status'],
                'nav' => $page['nav'], 'order' => $page['order'], 'updated' => $page['updated'],
                'author' => $page['author'], 'protected' => $page['protect'] !== '',
            ];
            return $d;
        }, ['pages' => []]);
        return $page;
    }

    private static function uniqueSlug(string $slug, string $id): string
    {
        $base = $slug;
        if (in_array($base, TEENY_RESERVED_SLUGS, true)) $base .= '-page';
        $taken = [];
        foreach (self::index() as $pid => $m) {
            if ((string)$pid !== $id) $taken[$m['slug'] ?? ''] = true;
        }
        $candidate = $base;
        for ($i = 2; isset($taken[$candidate]); $i++) {
            $candidate = substr($base, 0, 55) . '-' . $i;
        }
        return $candidate;
    }

    public static function delete(string $id): void
    {
        if (!valid_id($id)) return;
        Store::delete('pages/' . $id);
        Store::removeDir('revisions/' . $id);
        Store::update('pages', static function (array $d) use ($id): array {
            unset($d['pages'][$id]);
            return $d;
        }, ['pages' => []]);
        $site = site();
        if (($site['home'] ?? '') === $id) {
            $site['home'] = (string)(array_key_first(self::index()) ?? '');
            Store::write('site', $site);
            site($site);
        }
    }

    public static function duplicate(string $id, string $userId): ?array
    {
        $p = self::get($id);
        if (!$p) return null;
        unset($p['id']);
        $p['title'] .= ' (copy)';
        $p['slug'] = $p['slug'] . '-copy';
        $p['status'] = 'draft';
        foreach ($p['blocks'] as &$b) $b['id'] = random_id(4);
        unset($b);
        return self::save($p, $userId, true);
    }

    /** Page data safe to hand to the browser editor (never the password hash). */
    public static function forEditor(array $page): array
    {
        $page['protected'] = ($page['protect'] ?? '') !== '';
        unset($page['protect']);
        return $page;
    }

    /** Has this visitor entered the right password for a protected page (this session)? */
    public static function unlocked(array $page): bool
    {
        if (($page['protect'] ?? '') === '') return true;
        if (!Auth::hasCookie()) return false;
        Auth::start();
        $tag = substr(hash('sha256', $page['protect']), 0, 16);
        return ($_SESSION['unlocked'][$page['id']] ?? '') === $tag;
    }

    /** @return true|string */
    public static function unlock(array $page, string $password): bool|string
    {
        Auth::start();
        $key = 'pg:' . hash('sha256', client_ip() . '|' . $page['id']);
        // Check the lockout and count this try in one locked step, before the slow password check,
        // so simultaneous requests can't get extra guesses.
        $locked = false;
        Store::update('throttle', static function (array $t) use ($key, &$locked): array {
            $now = time();
            if ((int)($t[$key]['until'] ?? 0) > $now) { $locked = true; return $t; }
            $e = $t[$key] ?? ['n' => 0, 'first' => $now];
            if ($now - (int)$e['first'] > 900) $e = ['n' => 0, 'first' => $now];
            $e['n']++;
            $e['last'] = $now;
            if ($e['n'] >= 6) $e['until'] = $now + 900;
            $t[$key] = $e;
            return $t;
        });
        if ($locked) return 'Too many attempts. Please try again in a few minutes.';
        if (password_verify($password, (string)$page['protect'])) {
            Store::update('throttle', static function (array $t) use ($key): array { unset($t[$key]); return $t; });
            session_regenerate_id(true);
            $_SESSION['unlocked'][$page['id']] = substr(hash('sha256', $page['protect']), 0, 16);
            return true;
        }
        usleep(random_int(150000, 350000));
        return 'Incorrect password.';
    }

    /* ---------- revisions ---------- */

    private static function addRevision(array $page): void
    {
        $name = 'revisions/' . $page['id'] . '/' . time() . '-' . random_id(2);
        Store::write($name, $page);
        $all = Store::list('revisions/' . $page['id']);
        while (count($all) > self::MAX_REVISIONS) {
            Store::delete('revisions/' . $page['id'] . '/' . array_shift($all));
        }
    }

    public static function revisions(string $id): array
    {
        if (!valid_id($id)) return [];
        $out = [];
        foreach (array_reverse(Store::list('revisions/' . $id)) as $rev) {
            $data = Store::read('revisions/' . $id . '/' . $rev, []);
            $out[] = ['rev' => $rev, 'updated' => (int)($data['updated'] ?? 0), 'title' => $data['title'] ?? '', 'blocks' => count($data['blocks'] ?? []), 'author' => $data['author'] ?? ''];
        }
        return $out;
    }

    public static function restore(string $id, string $rev, string $userId): bool
    {
        if (!valid_id($id) || !preg_match('/^\d{9,11}-[a-f0-9]{4}$/', $rev)) return false;
        $data = Store::read('revisions/' . $id . '/' . $rev);
        if (!is_array($data)) return false;
        $data['id'] = $id;
        self::save($data, $userId);
        return true;
    }

    /* ---------- templates ---------- */

    public static function fromTemplate(string $template): array
    {
        if (!isset(self::TEMPLATES[$template]) || $template === 'blank') return [];
        $file = TEENY_ROOT . '/templates/' . $template . '.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        return is_array($data['blocks'] ?? null) ? Blocks::sanitize($data['blocks']) : [];
    }

    public static function templateDefaults(string $template): array
    {
        $file = TEENY_ROOT . '/templates/' . $template . '.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        return is_array($data) ? $data : [];
    }
}
