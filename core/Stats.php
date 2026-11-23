<?php
declare(strict_types=1);

/**
 * Privacy-friendly visitor statistics.
 * No cookies, no IP addresses stored. Unique visitors are counted with a hash of
 * (daily random salt + IP + browser) that is thrown away every day, so visitors
 * cannot be tracked across days. Bots and logged-in users are not counted.
 */
final class Stats
{
    private const BOTS = '~bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|curl|wget|python|java/|go-http|httpclient|monitor|uptime|headless|lighthouse|scan~i';

    public static function enabled(): bool
    {
        return (site()['stats'] ?? true) !== false;
    }

    public static function record(string $slug): void
    {
        if (!self::enabled() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || preg_match(self::BOTS, $ua)) return;
        if (($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? '') !== '') return; // prefetch
        try {
            $day = date('Y-m-d');
            $isNew = false;
            Store::update('stats/today', static function (array $t) use ($day, $ua, &$isNew): array {
                if (($t['day'] ?? '') !== $day) $t = ['day' => $day, 'salt' => random_id(16), 'v' => []];
                $h = substr(hash('sha256', $t['salt'] . '|' . client_ip() . '|' . $ua), 0, 12);
                if (!isset($t['v'][$h]) && count($t['v']) < 50000) {
                    $t['v'][$h] = 1;
                    $isNew = true;
                }
                return $t;
            });
            $ref = '';
            $refHost = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
            $ownHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
            if (is_string($refHost) && strcasecmp($refHost, (string)$ownHost) !== 0 && preg_match('/^[a-z0-9.-]{3,100}$/i', $refHost)) {
                $ref = preg_replace('/^www\./i', '', strtolower($refHost));
            }
            $key = $slug === '' ? '/' : '/' . $slug;
            Store::update('stats/' . date('Y-m'), static function (array $d) use ($day, $key, $ref, $isNew): array {
                $e = $d['days'][$day] ?? ['views' => 0, 'visitors' => 0, 'pages' => [], 'refs' => []];
                $e['views']++;
                if ($isNew) $e['visitors']++;
                if (isset($e['pages'][$key]) || count($e['pages']) < 200) $e['pages'][$key] = ($e['pages'][$key] ?? 0) + 1;
                if ($ref !== '' && (isset($e['refs'][$ref]) || count($e['refs']) < 100)) $e['refs'][$ref] = ($e['refs'][$ref] ?? 0) + 1;
                $d['days'][$day] = $e;
                return $d;
            });
        } catch (Throwable $ex) {
            error_log('Teeny stats: ' . $ex->getMessage());
        }
    }

    /** Totals and daily series for the last $days days. */
    public static function summary(int $days = 30): array
    {
        $months = [];
        $series = [];
        $pages = [];
        $refs = [];
        $tot = ['views' => 0, 'visitors' => 0, 'today' => 0, 'todayVisitors' => 0];
        for ($i = $days - 1; $i >= 0; $i--) {
            $ts = strtotime("-$i days");
            $day = date('Y-m-d', $ts);
            $month = date('Y-m', $ts);
            $months[$month] ??= Store::read('stats/' . $month, ['days' => []]);
            $e = $months[$month]['days'][$day] ?? null;
            $series[] = ['day' => $day, 'views' => (int)($e['views'] ?? 0), 'visitors' => (int)($e['visitors'] ?? 0)];
            if (!$e) continue;
            $tot['views'] += (int)$e['views'];
            $tot['visitors'] += (int)$e['visitors'];
            if ($i === 0) {
                $tot['today'] = (int)$e['views'];
                $tot['todayVisitors'] = (int)$e['visitors'];
            }
            foreach ($e['pages'] ?? [] as $k => $n) $pages[$k] = ($pages[$k] ?? 0) + $n;
            foreach ($e['refs'] ?? [] as $k => $n) $refs[$k] = ($refs[$k] ?? 0) + $n;
        }
        arsort($pages);
        arsort($refs);
        return ['series' => $series, 'totals' => $tot, 'pages' => array_slice($pages, 0, 8, true), 'refs' => array_slice($refs, 0, 8, true)];
    }
}
