<?php
declare(strict_types=1);

/**
 * Site navigation. Automatic (pages marked "show in menu") until the owner saves a custom menu,
 * which can mix pages and links to other websites in any order.
 */
final class Menu
{
    public const MAX_ITEMS = 30;

    public static function custom(): ?array
    {
        $m = site()['menu'] ?? null;
        return is_array($m) ? $m : null;
    }

    /** @return array<int, array{label:string,url:string,current:bool,newTab:bool}> */
    public static function items(?array $current): array
    {
        $out = [];
        $custom = self::custom();
        if ($custom === null) {
            foreach (Pages::nav() as $p) {
                $out[] = ['label' => $p['title'], 'url' => page_url($p), 'current' => $current && $current['id'] === $p['id'], 'newTab' => false];
            }
            return $out;
        }
        $index = Pages::index();
        foreach ($custom as $it) {
            if (($it['type'] ?? '') === 'page') {
                $m = $index[$it['id'] ?? ''] ?? null;
                if (!$m || ($m['status'] ?? '') !== 'published') continue;
                $out[] = [
                    'label' => ($it['label'] ?? '') !== '' ? $it['label'] : $m['title'],
                    'url' => page_url(['id' => $it['id']] + $m),
                    'current' => $current && $current['id'] === $it['id'],
                    'newTab' => false,
                ];
            } elseif (($it['type'] ?? '') === 'link' && ($it['url'] ?? '') !== '') {
                $out[] = ['label' => $it['label'], 'url' => $it['url'], 'current' => false, 'newTab' => !empty($it['newTab'])];
            }
        }
        return $out;
    }

    public static function sanitize(mixed $items): array
    {
        $out = [];
        $index = Pages::index();
        foreach (array_slice(is_array($items) ? array_values($items) : [], 0, self::MAX_ITEMS) as $it) {
            if (!is_array($it)) continue;
            $type = (string)($it['type'] ?? '');
            $label = Sanitizer::text($it['label'] ?? '', 60);
            if ($type === 'page') {
                $id = (string)($it['id'] ?? '');
                if (isset($index[$id])) $out[] = ['type' => 'page', 'id' => $id, 'label' => $label];
            } elseif ($type === 'link') {
                $url = Sanitizer::url((string)($it['url'] ?? ''));
                if ($label !== '' && $url !== '') $out[] = ['type' => 'link', 'label' => $label, 'url' => $url, 'newTab' => !empty($it['newTab'])];
            }
        }
        return $out;
    }
}
