<?php
declare(strict_types=1);

/** A short record of who changed themes and plugins, and when. */
final class Activity
{
    private const MAX = 300;

    public static function log(string $action, string $detail = ''): void
    {
        $u = Auth::user();
        $entry = [
            'time' => time(),
            'user' => (string)($u['name'] ?? 'System'),
            'action' => Sanitizer::text($action, 60),
            'detail' => Sanitizer::text($detail, 200),
        ];
        Store::update('activity', static function (array $d) use ($entry): array {
            $list = is_array($d['items'] ?? null) ? $d['items'] : [];
            array_unshift($list, $entry);
            $d['items'] = array_slice($list, 0, self::MAX);
            return $d;
        }, ['items' => []]);
    }

    public static function recent(int $n = 20, ?string $prefix = null): array
    {
        $items = Store::read('activity', ['items' => []])['items'] ?? [];
        if ($prefix !== null) $items = array_filter($items, static fn($e) => str_starts_with((string)$e['action'], $prefix));
        return array_slice(array_values($items), 0, $n);
    }
}
