<?php
declare(strict_types=1);

/**
 * Section plugins. A plugin adds a new section type to the editor and is made of:
 *   plugin.json    name + the section's fields (same field types as built-in sections)
 *   template.html  layout using {{placeholders}}: logic-less, escaped, cleaned (see Templater)
 *   style.css      styles, automatically confined to the plugin's own section
 *   widget.js      optional; runs ONLY inside a sandboxed iframe with no access to the site
 *   widget.css     optional styles for the widget frame
 *   images/        optional pictures used by the template ({{@assets}}/images/…)
 *
 * Plugins never run server-side code. New plugins stay switched off until an Owner enables them.
 */
final class Plugins
{
    public const FIELD_TYPES = ['text', 'textarea', 'rich', 'url', 'image', 'select', 'list'];
    private const ITEM_TYPES = ['text', 'textarea', 'url', 'image', 'select'];
    private const ID = '/^[a-z0-9][a-z0-9-]{1,39}$/';

    private static ?array $enabledCache = null;
    private static array $templates = [];

    public static function dir(string $id = ''): string
    {
        return TEENY_ROOT . '/plugins' . ($id !== '' ? '/' . $id : '');
    }

    /* ---------- manifest ---------- */

    /** @throws RuntimeException */
    public static function normalizeManifest(mixed $m, ?string $expectId = null): array
    {
        if (!is_array($m)) throw new RuntimeException('plugin.json is not valid JSON.');
        $id = (string)($m['id'] ?? '');
        if (!preg_match(self::ID, $id)) throw new RuntimeException('plugin.json needs an "id" made of lowercase letters, numbers and dashes (2 to 40 characters).');
        if ($expectId !== null && $id !== $expectId) throw new RuntimeException('The plugin id "' . $id . '" must match its folder name "' . $expectId . '".');
        $name = Sanitizer::text($m['name'] ?? '', 60);
        if ($name === '') throw new RuntimeException('plugin.json needs a "name".');
        $sec = is_array($m['section'] ?? null) ? $m['section'] : [];
        $fieldsIn = is_array($sec['fields'] ?? null) ? $sec['fields'] : [];
        if (!$fieldsIn) throw new RuntimeException('plugin.json needs "section" → "fields" with at least one field.');
        if (count($fieldsIn) > 20) throw new RuntimeException('A plugin section can have at most 20 fields.');
        $fields = [];
        foreach ($fieldsIn as $key => $def) {
            $fields[self::fieldName($key)] = self::fieldDef($def, self::FIELD_TYPES, (string)$key);
        }
        $out = [
            'id'          => $id,
            'name'        => $name,
            'description' => Sanitizer::text($m['description'] ?? '', 200),
            'version'     => Sanitizer::text($m['version'] ?? '1.0', 20),
            'author'      => Sanitizer::text($m['author'] ?? '', 80),
            'homepage'    => preg_match('~^https?://~i', (string)($m['homepage'] ?? '')) ? Sanitizer::url((string)$m['homepage']) : '',
            'section'     => [
                'label'    => Sanitizer::text($sec['label'] ?? $name, 40) ?: $name,
                'desc'     => Sanitizer::text($sec['desc'] ?? ($m['description'] ?? ''), 80),
                'fields'   => $fields,
                'defaults' => Blocks::cleanDefaults($fields, $sec['defaults'] ?? []), // only its own fields, cleaned
            ],
        ];
        if (isset($m['widget'])) {
            $w = is_array($m['widget']) ? $m['widget'] : [];
            $out['widget'] = ['height' => Sanitizer::int($w['height'] ?? 240, 40, 1600, 240)];
        }
        return $out;
    }

    private static function fieldName(mixed $key): string
    {
        $key = (string)$key;
        if (!preg_match('/^[a-z][a-z0-9_]{0,30}$/', $key)) throw new RuntimeException('Field name "' . substr($key, 0, 30) . '" must start with a letter and use lowercase letters, numbers or _.');
        return $key;
    }

    private static function fieldDef(mixed $def, array $types, string $key): array
    {
        if (!is_array($def)) throw new RuntimeException('Field "' . $key . '" must be an object.');
        $type = (string)($def['type'] ?? '');
        if (!in_array($type, $types, true)) throw new RuntimeException('Field "' . $key . '" has an unsupported type "' . substr($type, 0, 20) . '". Use one of: ' . implode(', ', $types) . '.');
        $out = ['type' => $type, 'label' => Sanitizer::text($def['label'] ?? ucfirst($key), 60) ?: ucfirst($key)];
        if (in_array($type, ['text', 'textarea'], true)) $out['max'] = Sanitizer::int($def['max'] ?? ($type === 'text' ? 200 : 2000), 1, 5000, 200);
        if ($type === 'select') {
            $opts = [];
            foreach (is_array($def['options'] ?? null) ? $def['options'] : [] as $k => $label) {
                if (count($opts) >= 50) break;
                if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string)$k)) $opts[(string)$k] = Sanitizer::text($label, 60) ?: (string)$k;
            }
            if (!$opts) throw new RuntimeException('Select field "' . $key . '" needs "options".');
            $out['options'] = $opts;
        }
        if ($type === 'list') {
            $items = is_array($def['item'] ?? null) ? $def['item'] : [];
            if (!$items || count($items) > 10) throw new RuntimeException('List field "' . $key . '" needs an "item" with 1 to 10 fields.');
            $out['item'] = [];
            foreach ($items as $k => $sub) $out['item'][self::fieldName($k)] = self::fieldDef($sub, self::ITEM_TYPES, $key . '.' . $k);
            $out['max'] = Sanitizer::int($def['max'] ?? 20, 1, 50, 20);
        }
        return $out;
    }

    /** Validate everything in a plugin folder's files. Returns [manifest, problems]. */
    public static function inspectFiles(array $files, ?string $expectId = null): array
    {
        if (!isset($files['plugin.json'], $files['template.html'])) throw new RuntimeException('A plugin needs plugin.json and template.html.');
        $manifest = self::normalizeManifest(json_decode($files['plugin.json'], true), $expectId);
        $problems = Templater::problems($files['template.html']);
        if (isset($files['widget.js']) && !isset($manifest['widget'])) $problems[] = 'widget.js is included but plugin.json has no "widget" setting.';
        if (isset($manifest['widget']) && !isset($files['widget.js'])) $problems[] = 'plugin.json has a "widget" setting but there is no widget.js.';
        if (isset($files['widget.js']) && strlen($files['widget.js']) > 300000) $problems[] = 'widget.js is too large (max 300 KB).';
        return [$manifest, $problems];
    }

    /* ---------- state ---------- */

    private static function state(): array
    {
        $d = Store::read('plugins', ['items' => []]);
        return is_array($d['items'] ?? null) ? $d['items'] : [];
    }

    private static function folderFiles(string $id): array
    {
        $files = [];
        foreach (['plugin.json', 'template.html', 'style.css', 'widget.js', 'widget.css'] as $f) {
            $p = self::dir($id) . '/' . $f;
            if (is_file($p) && !is_link($p) && filesize($p) < 400000) $files[$f] = (string)file_get_contents($p);
        }
        return $files;
    }

    /** Every plugin folder found, valid or not (for the Plugins screen). */
    public static function all(): array
    {
        $state = self::state();
        $out = [];
        foreach (glob(self::dir() . '/*/plugin.json') ?: [] as $file) {
            $id = basename(dirname($file));
            if (!preg_match(self::ID, $id)) continue;
            $row = ['id' => $id, 'enabled' => !empty($state[$id]['enabled']), 'state' => $state[$id] ?? [], 'manifest' => null, 'problems' => []];
            try {
                [$row['manifest'], $row['problems']] = self::inspectFiles(self::folderFiles($id), $id);
            } catch (RuntimeException $ex) {
                $row['problems'] = [$ex->getMessage()];
            }
            $out[$id] = $row;
        }
        ksort($out);
        return $out;
    }

    /** Enabled, valid plugins: id => manifest. */
    public static function enabled(): array
    {
        if (self::$enabledCache !== null) return self::$enabledCache;
        self::$enabledCache = [];
        foreach (self::state() as $id => $st) {
            if (empty($st['enabled']) || !preg_match(self::ID, (string)$id) || !is_file(self::dir((string)$id) . '/plugin.json')) continue;
            try {
                $m = self::normalizeManifest(json_decode((string)file_get_contents(self::dir((string)$id) . '/plugin.json'), true), (string)$id);
                self::$enabledCache[(string)$id] = $m;
            } catch (RuntimeException) {
                // broken plugin: silently skipped on the website, reported on the Plugins screen
            }
        }
        return self::$enabledCache;
    }

    /** Section definitions for Blocks::schema(). */
    public static function schemas(): array
    {
        $out = [];
        foreach (self::enabled() as $id => $m) {
            $out['plugin-' . $id] = [
                'label'    => $m['section']['label'],
                'group'    => 'Plugins',
                'desc'     => $m['section']['desc'] ?: $m['name'],
                'fields'   => $m['section']['fields'],
                'defaults' => $m['section']['defaults'],
                'plugin'   => $id,
                'widget'   => isset($m['widget']),
            ];
        }
        return $out;
    }

    /* ---------- install / enable / remove ---------- */

    /** @param bool $keepState keep the plugin switched on if it was (only for checksum-verified official updates) */
    public static function install(array $files, bool $keepState = false): string
    {
        [$manifest, $problems] = self::inspectFiles($files);
        if ($problems) throw new RuntimeException('This plugin was not installed: ' . implode('; ', array_slice($problems, 0, 5)) . '.');
        $id = $manifest['id'];
        if (isset($files['style.css'])) $files['style.css'] = Sanitizer::css($files['style.css']) . "\n";
        if (isset($files['widget.css'])) $files['widget.css'] = Sanitizer::css($files['widget.css']) . "\n";
        $wasEnabled = !empty(self::state()[$id]['enabled']);
        Packages::writeFolder(self::dir($id), $files);
        self::saveState($id, ['enabled' => $keepState && $wasEnabled, 'installed' => time(), 'version' => $manifest['version']]);
        if ($wasEnabled && !$keepState) Activity::log('plugin.update', $manifest['name'] . ' ' . $manifest['version'] . ' (switched off for review)');
        return $id;
    }

    public static function enable(string $id): void
    {
        $all = self::all();
        $row = $all[$id] ?? null;
        if (!$row || !$row['manifest']) throw new RuntimeException('This plugin could not be read: ' . implode('; ', $row['problems'] ?? ['not found']));
        if ($row['problems']) throw new RuntimeException('Fix these problems before switching the plugin on: ' . implode('; ', array_slice($row['problems'], 0, 5)));
        self::saveState($id, ['enabled' => true, 'version' => $row['manifest']['version']]);
    }

    public static function disable(string $id): void
    {
        self::saveState($id, ['enabled' => false]);
    }

    public static function remove(string $id): void
    {
        if (!preg_match(self::ID, $id) || !is_dir(self::dir($id))) throw new RuntimeException('Plugin not found.');
        Packages::removeDir(self::dir($id), self::dir());
        Store::update('plugins', static function (array $d) use ($id): array {
            unset($d['items'][$id]);
            return $d;
        }, ['items' => []]);
        self::$enabledCache = null;
    }

    private static function saveState(string $id, array $changes): void
    {
        Store::update('plugins', static function (array $d) use ($id, $changes): array {
            $d['items'][$id] = $changes + ($d['items'][$id] ?? []);
            unset($d['items'][$id]['css']);
            return $d;
        }, ['items' => []]);
        self::$enabledCache = null;
    }

    /* ---------- rendering ---------- */

    private static function template(string $id): array
    {
        if (!isset(self::$templates[$id])) {
            try {
                self::$templates[$id] = Templater::compile((string)@file_get_contents(self::dir($id) . '/template.html'));
            } catch (RuntimeException) {
                self::$templates[$id] = [];
            }
        }
        return self::$templates[$id];
    }

    /** Field values prepared for the template: images become URLs, missing images become ''. */
    public static function viewData(array $block, array $fields): array
    {
        $out = [];
        foreach ($fields as $k => $def) {
            $v = $block[$k] ?? '';
            if ($def['type'] === 'image') {
                $out[$k] = (is_string($v) && Media::exists($v)) ? url($v) : '';
            } elseif ($def['type'] === 'list') {
                $out[$k] = [];
                foreach (is_array($v) ? $v : [] as $item) {
                    $out[$k][] = self::viewData(is_array($item) ? $item : [], $def['item']);
                }
            } else {
                $out[$k] = is_scalar($v) ? (string)$v : '';
            }
        }
        return $out;
    }

    private static function types(array $fields): array
    {
        $t = [];
        foreach ($fields as $k => $def) $t[$k] = $def['type'];
        return $t;
    }

    /** Static HTML of a plugin section (also used inside widget frames and editor previews). */
    public static function html(string $id, array $block): string
    {
        $m = self::enabled()[$id] ?? null;
        if (!$m) return '';
        $fields = $m['section']['fields'];
        return Templater::render(self::template($id), self::viewData($block, $fields), self::types($fields), ['assets' => url('plugins/' . $id)]);
    }

    public static function render(array $block, bool $preview = false): string
    {
        $id = substr((string)($block['type'] ?? ''), 7);
        $m = self::enabled()[$id] ?? null;
        if (!$m) return '';
        $cls = 'tc-block tc-plugin tc-plugin--' . e($id);
        if (isset($m['widget']) && !$preview) {
            $page = Renderer::$page;
            if (!$page) return '';
            $src = url('part.php') . '?' . http_build_query(['page' => $page['id'], 'block' => $block['id']]);
            return '<div class="' . $cls . '"><iframe class="tc-widget" src="' . e($src) . '" sandbox="allow-scripts" loading="lazy" referrerpolicy="no-referrer" title="' . e($m['name']) . '" height="' . (int)$m['widget']['height'] . '"></iframe></div>';
        }
        $inner = self::html($id, $block);
        if ($preview && isset($m['widget'])) {
            $inner .= '<p class="tce-hint">Interactive part runs on the live page (inside a secure sandbox).</p>';
        }
        return '<div class="' . $cls . '">' . $inner . '</div>';
    }

    /** Scoped CSS for the given plugin ids (inlined in the page head). */
    public static function css(array $ids, bool $widget = false): string
    {
        $out = '';
        foreach (array_unique($ids) as $id) {
            if (!isset(self::enabled()[$id])) continue;
            $scope = '.tc-plugin--' . $id;
            $css = (string)@file_get_contents(self::dir($id) . '/style.css');
            if ($css !== '') $out .= Sanitizer::scopeCss($css, $scope);
            if ($widget) {
                $w = (string)@file_get_contents(self::dir($id) . '/widget.css');
                if ($w !== '') $out .= Sanitizer::css($w) . "\n";
            }
        }
        return $out;
    }

    /** Plugin ids used by a page's sections. */
    public static function usedBy(array $page): array
    {
        $ids = [];
        foreach ($page['blocks'] ?? [] as $b) {
            $t = (string)($b['type'] ?? '');
            if (str_starts_with($t, 'plugin-')) $ids[] = substr($t, 7);
        }
        return array_values(array_unique($ids));
    }
}
