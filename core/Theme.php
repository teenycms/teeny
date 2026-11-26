<?php
declare(strict_types=1);

/**
 * Themes live in /themes/{name}/ with a theme.json (name, description, defaults) and theme.css.
 * Users can customise colours, fonts, radius and width per theme without touching code.
 */
final class Theme
{
    public const ALIGNS = [
        'normal' => ['label' => 'Normal website', 'desc' => 'Menu across the top, content in a wide column'],
        'center' => ['label' => 'Centered', 'desc' => 'Narrow column, everything centered'],
        'left'   => ['label' => 'Left', 'desc' => 'Narrow column on the left'],
        'right'  => ['label' => 'Right', 'desc' => 'Narrow column on the right'],
    ];

    /** Self-hosted font stacks: no web font downloads, no external requests. */
    public const FONTS = [
        'classic'   => ['label' => 'Classic serif', 'stack' => '"Iowan Old Style", "Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif'],
        'didone'    => ['label' => 'Elegant serif', 'stack' => 'Didot, "Bodoni 72", "Bodoni MT", "Noto Serif Display", "Libre Bodoni", Georgia, serif'],
        'modern'    => ['label' => 'Modern sans', 'stack' => 'Inter, "Segoe UI Variable", "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif'],
        'humanist'  => ['label' => 'Humanist sans', 'stack' => 'Optima, Candara, "Gill Sans", "Gill Sans MT", "Noto Sans", "Segoe UI", sans-serif'],
        'geometric' => ['label' => 'Geometric sans', 'stack' => '"Avenir Next", Avenir, Futura, "Century Gothic", "Trebuchet MS", sans-serif'],
        'mono'      => ['label' => 'Monospace', 'stack' => 'ui-monospace, "SF Mono", "Cascadia Code", Menlo, Consolas, monospace'],
    ];

    /** Built-in fonts plus the Google Fonts this site saved (see Fonts). */
    public static function fonts(?array $site = null): array
    {
        return self::FONTS + Fonts::menu($site);
    }

    /** Closest built-in font for a custom font's kind (used when a style is saved as a shareable theme). */
    private const KIND_TO_BUILTIN = ['sans' => 'modern', 'serif' => 'classic', 'display' => 'didone', 'handwriting' => 'humanist', 'mono' => 'mono'];

    public const COLOR_KEYS = [
        'bg' => 'Background', 'surface' => 'Cards & panels', 'text' => 'Text', 'muted' => 'Soft text',
        'accent' => 'Accent', 'accentText' => 'Text on accent', 'border' => 'Lines',
    ];

    /** Themes that ship with Teeny and can't be removed. */
    public const BUILTIN = ['minimal', 'dark', 'luxury'];

    private static ?array $cache = null;

    public static function isBuiltin(string $id): bool
    {
        return in_array($id, self::BUILTIN, true);
    }

    /** Validate theme.json data and return a clean version. @throws RuntimeException */
    public static function normalizeManifest(mixed $m): array
    {
        if (!is_array($m)) throw new RuntimeException('theme.json is not valid JSON.');
        $name = Sanitizer::text($m['name'] ?? '', 40);
        if ($name === '') throw new RuntimeException('theme.json needs a "name".');
        $base = self::all()['minimal']['defaults'] ?? [];
        $defaults = self::clean(is_array($m['defaults'] ?? null) ? $m['defaults'] : [], $base);
        $out = [
            'name'        => $name,
            'description' => Sanitizer::text($m['description'] ?? '', 120),
            'author'      => Sanitizer::text($m['author'] ?? '', 60),
            'version'     => Sanitizer::text($m['version'] ?? '1.0', 20),
            'order'       => Sanitizer::int($m['order'] ?? 50, 0, 999, 50),
            'dark'        => !empty($m['dark']),
            'defaults'    => $defaults,
        ];
        if (is_array($m['alt'] ?? null)) {
            foreach (array_keys(self::COLOR_KEYS) as $k) {
                $out['alt'][$k] = Sanitizer::color($m['alt'][$k] ?? '', $defaults[$k]);
            }
        }
        return $out;
    }

    /** Install a theme from validated package files. Returns the theme id. @throws RuntimeException */
    public static function installFiles(array $files): string
    {
        if (!isset($files['theme.json'], $files['theme.css'])) {
            throw new RuntimeException('A theme needs both theme.json and theme.css.');
        }
        $raw = json_decode($files['theme.json'], true);
        $manifest = self::normalizeManifest($raw);
        $id = slugify((string)(is_array($raw) && is_string($raw['id'] ?? null) ? $raw['id'] : $manifest['name']));
        if (!preg_match('/^[a-z0-9-]{2,40}$/', $id)) throw new RuntimeException('The theme needs a simple id (letters, numbers and dashes).');
        if (self::isBuiltin($id)) throw new RuntimeException('"' . $id . '" is the name of a built-in theme. Please give your theme a different id.');
        $files['theme.json'] = (string)json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $files['theme.css'] = "/* " . str_replace('*/', '', $manifest['name']) . ", installed with Teeny Studio */\n" . Sanitizer::css($files['theme.css']) . "\n";
        Packages::writeFolder(TEENY_ROOT . '/themes/' . $id, $files);
        self::$cache = null;
        return $id;
    }

    public static function remove(string $id): void
    {
        if (self::isBuiltin($id) || !isset(self::all()[$id])) throw new RuntimeException('This theme cannot be removed.');
        Packages::removeDir(TEENY_ROOT . '/themes/' . $id, TEENY_ROOT . '/themes');
        $site = site();
        if (($site['theme']['name'] ?? '') === $id) $site['theme']['name'] = 'minimal';
        unset($site['theme']['overrides'][$id]);
        Store::write('site', $site);
        site($site);
        self::$cache = null;
    }

    /** Save the active theme + the owner's customisations + custom CSS as a brand-new theme. */
    public static function saveCurrent(string $name, string $author): string
    {
        $name = Sanitizer::text($name, 40);
        if ($name === '') throw new RuntimeException('Please give your theme a name.');
        $site = site();
        $from = self::active($site);
        $src = self::all()[$from];
        $id = slugify($name) ?: 'my-theme';
        if (self::isBuiltin($id)) $id .= '-custom';
        $base = $id;
        for ($i = 2; isset(self::all()[$id]); $i++) $id = $base . '-' . $i;
        $manifest = [
            'name'        => $name,
            'description' => 'Made in Teeny Studio, based on ' . $src['name'],
            'author'      => Sanitizer::text($author, 60),
            'version'     => '1.0',
            'order'       => 50,
            'dark'        => !empty($src['dark']),
            'defaults'    => self::settings($site, $from),
        ];
        // Custom fonts live in this site's uploads, not in the theme, so a shared theme uses the closest built-in font.
        foreach (['headingFont', 'bodyFont'] as $fk) {
            $f = $manifest['defaults'][$fk];
            if (!isset(self::FONTS[$f])) $manifest['defaults'][$fk] = self::KIND_TO_BUILTIN[self::fonts($site)[$f]['kind'] ?? 'sans'] ?? 'modern';
        }
        if (is_array($src['alt'] ?? null)) $manifest['alt'] = self::altPalette($site);
        $css = (string)@file_get_contents(TEENY_ROOT . '/themes/' . $from . '/theme.css');
        $css = str_replace('.theme-' . $from, '.theme-' . $id, $css);
        $custom = Sanitizer::css($site['theme']['customCss'] ?? '');
        if ($custom !== '') $css .= "\n/* Custom CSS saved from Style */\n" . $custom . "\n";
        $files = ['theme.json' => json_encode($manifest), 'theme.css' => $css];
        foreach (['fonts', 'images'] as $sub) {
            foreach (glob(TEENY_ROOT . '/themes/' . $from . '/' . $sub . '/*') ?: [] as $f) {
                if (is_file($f) && !is_link($f)) $files[$sub . '/' . strtolower(basename($f))] = (string)file_get_contents($f);
            }
        }
        foreach ($files as $rel => $data) {
            if (!preg_match(Packages::ALLOWED['theme'], $rel)) unset($files[$rel]);
        }
        return self::installFiles($files);
    }

    public static function all(): array
    {
        $themes = &self::$cache;
        if ($themes !== null) return $themes;
        $themes = [];
        foreach (glob(TEENY_ROOT . '/themes/*/theme.json') ?: [] as $file) {
            $name = basename(dirname($file));
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $name)) continue;
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || !is_array($data['defaults'] ?? null)) continue;
            $themes[$name] = $data;
        }
        uasort($themes, static fn($a, $b) => ($a['order'] ?? 9) <=> ($b['order'] ?? 9));
        return $themes;
    }

    public static function active(?array $site = null): string
    {
        $site ??= site();
        $name = (string)($site['theme']['name'] ?? 'minimal');
        return isset(self::all()[$name]) ? $name : (string)(array_key_first(self::all()) ?? 'minimal');
    }

    public static function align(?array $site = null, ?array $page = null): string
    {
        $site ??= site();
        $a = (string)($page['layout'] ?? '');
        if ($a === '' || !isset(self::ALIGNS[$a])) $a = (string)($site['theme']['align'] ?? 'normal');
        return isset(self::ALIGNS[$a]) ? $a : 'normal';
    }

    /** Defaults of the active theme merged with the user's overrides. */
    public static function settings(?array $site = null, ?string $name = null): array
    {
        $site ??= site();
        $name ??= self::active($site);
        $d = self::all()[$name]['defaults'] ?? [];
        $o = $site['theme']['overrides'][$name] ?? [];
        return self::clean(is_array($o) ? $o + $d : $d, $d);
    }

    public static function clean(array $in, array $defaults): array
    {
        $out = [];
        foreach (array_keys(self::COLOR_KEYS) as $k) {
            $out[$k] = Sanitizer::color($in[$k] ?? '', (string)($defaults[$k] ?? '#000000'));
        }
        foreach (['headingFont', 'bodyFont'] as $k) {
            $f = (string)($in[$k] ?? '');
            $out[$k] = isset(self::fonts()[$f]) ? $f : (string)($defaults[$k] ?? 'modern');
            if (!isset(self::fonts()[$out[$k]])) $out[$k] = 'modern';
        }
        $out['radius']   = Sanitizer::int($in['radius'] ?? null, 0, 28, (int)($defaults['radius'] ?? 6));
        $out['width']    = Sanitizer::int($in['width'] ?? null, 480, 1400, (int)($defaults['width'] ?? 1080));
        $out['fontSize'] = Sanitizer::int($in['fontSize'] ?? null, 14, 22, (int)($defaults['fontSize'] ?? 17));
        return $out;
    }

    /** Visitors may switch between the theme's normal and alternate (light/dark) palette. */
    public static function visitorToggle(?array $site = null): bool
    {
        $site ??= site();
        return !empty($site['theme']['visitorToggle']) && is_array(self::all()[self::active($site)]['alt'] ?? null);
    }

    /** Is the active theme's main palette a dark one? */
    public static function isDark(?array $site = null): bool
    {
        return !empty(self::all()[self::active($site ?? site())]['dark']);
    }

    public static function altPalette(?array $site = null): array
    {
        $theme = self::all()[self::active($site ?? site())] ?? [];
        $alt = is_array($theme['alt'] ?? null) ? $theme['alt'] : [];
        $out = [];
        foreach (array_keys(self::COLOR_KEYS) as $k) $out[$k] = Sanitizer::color($alt[$k] ?? '', '#808080');
        return $out;
    }

    /** CSS custom properties + the user's custom CSS. Safe to print inside <style>. */
    public static function css(?array $site = null): string
    {
        $site ??= site();
        $s = self::settings($site);
        $vars = [
            '--tc-bg' => $s['bg'], '--tc-surface' => $s['surface'], '--tc-text' => $s['text'],
            '--tc-muted' => $s['muted'], '--tc-accent' => $s['accent'], '--tc-accent-text' => $s['accentText'],
            '--tc-border' => $s['border'],
            '--tc-font-heading' => self::fonts($site)[$s['headingFont']]['stack'],
            '--tc-font-body' => self::fonts($site)[$s['bodyFont']]['stack'],
            '--tc-radius' => $s['radius'] . 'px',
            '--tc-wide' => $s['width'] . 'px',
            '--tc-font-size' => $s['fontSize'] . 'px',
        ];
        $css = Fonts::faceCss([$s['headingFont'], $s['bodyFont']], $site) . ':root{';
        foreach ($vars as $k => $v) $css .= $k . ':' . $v . ';';
        $css .= '}';
        if (self::visitorToggle($site)) {
            $alt = self::altPalette($site);
            $css .= ':root[data-scheme="alt"]{';
            foreach (['bg' => 'bg', 'surface' => 'surface', 'text' => 'text', 'muted' => 'muted', 'accent' => 'accent', 'accentText' => 'accent-text', 'border' => 'border'] as $k => $var) {
                $css .= '--tc-' . $var . ':' . $alt[$k] . ';';
            }
            $css .= '}:root[data-scheme="alt"] body.tc{color-scheme:' . (self::isDark($site) ? 'light' : 'dark') . '}';
        }
        $custom = Sanitizer::css($site['theme']['customCss'] ?? '');
        return $custom !== '' ? $css . "\n/* custom */\n" . $custom : $css;
    }
}
