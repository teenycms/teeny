<?php
declare(strict_types=1);

/**
 * Custom fonts from Google Fonts, saved on this site.
 *
 * The owner types a font name in Style. Teeny downloads that font's files once into
 * uploads/fonts/<slug>/ and serves them from the site itself, so visitors never contact
 * Google (no tracking, no consent banner needed) and the Teeny download stays small.
 * Only fonts.googleapis.com and fonts.gstatic.com are ever contacted, and every file must
 * be a real WOFF2 font.
 */
final class Fonts
{
    public const MAX = 8;
    private const MAX_FILE = 800 * 1024;
    private const MAX_FAMILY = 3 * 1024 * 1024;
    private const SUBSETS = ['latin', 'latin-ext'];

    /** What the font looks like, so the right fallback is used while it loads. */
    public const KINDS = [
        'sans'        => ['label' => 'Sans serif', 'fallback' => 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif'],
        'serif'       => ['label' => 'Serif', 'fallback' => 'Georgia, "Times New Roman", serif'],
        'display'     => ['label' => 'Display (for headings)', 'fallback' => 'Georgia, serif'],
        'handwriting' => ['label' => 'Handwriting', 'fallback' => '"Segoe Print", "Bradley Hand", cursive'],
        'mono'        => ['label' => 'Monospace', 'fallback' => 'ui-monospace, Menlo, Consolas, monospace'],
    ];

    /** A few well-made Google Fonts to suggest (any Google Font name works). */
    public const SUGGESTIONS = [
        'Playfair Display' => 'display', 'Fraunces' => 'serif', 'Lora' => 'serif', 'Merriweather' => 'serif',
        'DM Serif Display' => 'display', 'Cormorant Garamond' => 'serif',
        'Inter' => 'sans', 'Figtree' => 'sans', 'Manrope' => 'sans', 'DM Sans' => 'sans', 'Poppins' => 'sans',
        'Montserrat' => 'sans', 'Space Grotesk' => 'sans', 'Nunito' => 'sans', 'Work Sans' => 'sans',
        'Caveat' => 'handwriting', 'Dancing Script' => 'handwriting', 'JetBrains Mono' => 'mono',
    ];

    /** Saved fonts: slug => ['family', 'kind', 'faces' => [['style', 'weight', 'file', 'range']]]. */
    public static function all(?array $site = null): array
    {
        $site ??= site();
        $out = [];
        foreach (is_array($site['fonts'] ?? null) ? $site['fonts'] : [] as $slug => $f) {
            $clean = self::cleanOne((string)$slug, $f);
            if ($clean) $out[(string)$slug] = $clean;
        }
        return $out;
    }

    /** Validate one stored font entry (also used when restoring a site copy). */
    public static function cleanOne(string $slug, mixed $f): ?array
    {
        if (!preg_match('/^[a-z0-9-]{1,50}$/', $slug) || !is_array($f)) return null;
        $family = (string)($f['family'] ?? '');
        if (!self::validName($family)) return null;
        $kind = isset(self::KINDS[$f['kind'] ?? '']) ? (string)$f['kind'] : 'sans';
        $faces = [];
        foreach (array_slice(is_array($f['faces'] ?? null) ? $f['faces'] : [], 0, 24) as $face) {
            if (!is_array($face)) continue;
            $file = (string)($face['file'] ?? '');
            if (!preg_match('/^[a-f0-9]{16}\.woff2$/', $file)) continue;
            $style = ($face['style'] ?? '') === 'italic' ? 'italic' : 'normal';
            $weight = preg_match('/^\d{3}( \d{3})?$/', (string)($face['weight'] ?? '')) ? (string)$face['weight'] : '400';
            $range = preg_match('/^[U+0-9A-Fa-f, ?-]{1,1000}$/', (string)($face['range'] ?? '')) ? (string)$face['range'] : '';
            $faces[] = ['style' => $style, 'weight' => $weight, 'file' => $file, 'range' => $range];
        }
        return $faces ? ['family' => $family, 'kind' => $kind, 'faces' => $faces] : null;
    }

    public static function validName(string $name): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]{0,48}[A-Za-z0-9]$/', $name);
    }

    public static function key(string $slug): string
    {
        return 'g-' . $slug;
    }

    /** Font menu entries for Theme::fonts(): key => ['label', 'stack', 'kind']. */
    public static function menu(?array $site = null): array
    {
        $out = [];
        foreach (self::all($site) as $slug => $f) {
            $out[self::key($slug)] = [
                'label' => $f['family'],
                'stack' => '"' . $f['family'] . '", ' . self::KINDS[$f['kind']]['fallback'],
                'kind'  => $f['kind'],
            ];
        }
        return $out;
    }

    /** @font-face rules for the given font keys (only fonts whose files are present). */
    public static function faceCss(array $keys, ?array $site = null): string
    {
        $css = '';
        foreach (self::all($site) as $slug => $f) {
            if (!in_array(self::key($slug), $keys, true)) continue;
            foreach ($f['faces'] as $face) {
                if (!is_file(self::dir($slug) . '/' . $face['file'])) continue;
                $css .= '@font-face{font-family:"' . $f['family'] . '";font-style:' . $face['style'] . ';font-weight:' . $face['weight']
                    . ';font-display:swap;src:url("' . url('uploads/fonts/' . $slug . '/' . $face['file']) . '") format("woff2")'
                    . ($face['range'] !== '' ? ';unicode-range:' . $face['range'] : '') . '}';
            }
        }
        return $css;
    }

    public static function dir(string $slug): string
    {
        return TEENY_UPLOADS . '/fonts/' . $slug;
    }

    /** Can this server download fonts at all? */
    public static function canDownload(): bool
    {
        return function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Download a Google Font and save it on this site. Returns the font's slug.
     * @throws RuntimeException with a message that can be shown to the owner
     */
    public static function add(string $family, string $kind): string
    {
        $family = trim(preg_replace('/\s+/', ' ', $family));
        if (!self::validName($family)) throw new RuntimeException('Type the font name as it appears on Google Fonts, for example “Playfair Display”.');
        if (!isset(self::KINDS[$kind])) $kind = self::SUGGESTIONS[$family] ?? 'sans';
        $site = site();
        $fonts = self::all($site);
        $slug = slugify($family);
        if ($slug === '' || strlen($slug) > 50) throw new RuntimeException('That font name is not valid.');
        if (!isset($fonts[$slug]) && count($fonts) >= self::MAX) throw new RuntimeException('You can add up to ' . self::MAX . ' fonts. Remove one first.');
        if (!self::canDownload()) throw new RuntimeException('Your web host doesn’t let Teeny download files, so fonts can’t be added here. Ask your host to enable “allow_url_fopen” or cURL.');

        // Ask Google for regular, bold and italic. Some fonts only have one style, so fall back step by step.
        $q = str_replace(' ', '+', $family);
        $css = null;
        foreach ([':ital,wght@0,400;0,700;1,400', ':wght@400;700', ''] as $axes) {
            [$status, $body] = self::get('https://fonts.googleapis.com/css2?family=' . $q . $axes . '&display=swap');
            if ($status === 200 && str_contains($body, '@font-face')) { $css = $body; break; }
            if ($status === 0) throw new RuntimeException('Teeny couldn’t reach Google Fonts. Check that your host allows outgoing connections, then try again.');
        }
        if ($css === null) throw new RuntimeException('Google Fonts has no font called “' . $family . '”. Check the spelling at fonts.google.com.');

        // Each block looks like: /* latin */ @font-face { font-style: normal; font-weight: 400; src: url(https://fonts.gstatic.com/....woff2) format('woff2'); unicode-range: ...; }
        preg_match_all('~/\*\s*([a-z0-9-]+)\s*\*/\s*@font-face\s*\{([^}]*)\}~i', $css, $blocks, PREG_SET_ORDER);
        $wanted = [];
        foreach ($blocks as $b) {
            if (!in_array(strtolower($b[1]), self::SUBSETS, true)) continue;
            if (!preg_match('~src:\s*url\((https://fonts\.gstatic\.com/[A-Za-z0-9/_.\-]+\.woff2)\)~', $b[2], $u)) continue;
            preg_match('~font-style:\s*(normal|italic)~', $b[2], $st);
            preg_match('~font-weight:\s*(\d{3}(?: \d{3})?)~', $b[2], $wt);
            preg_match('~unicode-range:\s*([U+0-9A-Fa-f, ?-]+)~', $b[2], $rg);
            $wanted[] = ['url' => $u[1], 'style' => $st[1] ?? 'normal', 'weight' => $wt[1] ?? '400', 'range' => trim($rg[1] ?? '')];
        }
        if (!$wanted) throw new RuntimeException('“' . $family . '” has no files for Latin text, so Teeny can’t use it.');

        // Download each file once (variable fonts reuse one file for several weights).
        $tmpDir = TEENY_UPLOADS . '/fonts/.tmp-' . bin2hex(random_bytes(4));
        if (!@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) throw new RuntimeException('Could not save the font. Check that the uploads folder is writable.');
        $files = []; $total = 0; $faces = [];
        try {
            foreach ($wanted as $w) {
                if (!isset($files[$w['url']])) {
                    [$status, $data] = self::get($w['url']);
                    if ($status !== 200 || strlen($data) < 100 || strlen($data) > self::MAX_FILE || substr($data, 0, 4) !== 'wOF2') {
                        throw new RuntimeException('Downloading “' . $family . '” failed. Please try again in a minute.');
                    }
                    $total += strlen($data);
                    if ($total > self::MAX_FAMILY) throw new RuntimeException('“' . $family . '” is too large to add.');
                    $name = substr(hash('sha256', $data), 0, 16) . '.woff2';
                    if (@file_put_contents($tmpDir . '/' . $name, $data) === false) throw new RuntimeException('Could not save the font. Check that the uploads folder is writable.');
                    $files[$w['url']] = $name;
                }
                $faces[] = ['style' => $w['style'], 'weight' => $w['weight'], 'file' => $files[$w['url']], 'range' => $w['range']];
            }
            // Swap the new files in only after everything downloaded.
            $dir = self::dir($slug);
            self::removeDir($dir);
            if (!@rename($tmpDir, $dir)) throw new RuntimeException('Could not save the font. Check that the uploads folder is writable.');
        } catch (Throwable $ex) {
            self::removeDir($tmpDir);
            throw $ex instanceof RuntimeException ? $ex : new RuntimeException('Could not add the font. Please try again.');
        }

        Store::update('site', static function (array $s) use ($slug, $family, $kind, $faces) {
            $s['fonts'] = is_array($s['fonts'] ?? null) ? $s['fonts'] : [];
            $s['fonts'][$slug] = ['family' => $family, 'kind' => $kind, 'faces' => $faces];
            return $s;
        });
        site(Store::read('site', []));
        Cache::clear();
        return $slug;
    }

    /** Remove a saved font. Themes that used it fall back to their own fonts. */
    public static function remove(string $slug): void
    {
        if (!preg_match('/^[a-z0-9-]{1,50}$/', $slug)) return;
        self::removeDir(self::dir($slug));
        Store::update('site', static function (array $s) use ($slug) {
            if (is_array($s['fonts'] ?? null)) unset($s['fonts'][$slug]);
            return $s;
        });
        site(Store::read('site', []));
        Cache::clear();
    }

    /** Fonts used by any theme's saved settings (so the owner knows before removing one). */
    public static function inUse(string $slug, ?array $site = null): bool
    {
        $site ??= site();
        $key = self::key($slug);
        foreach (is_array($site['theme']['overrides'] ?? null) ? $site['theme']['overrides'] : [] as $o) {
            if (is_array($o) && (($o['headingFont'] ?? '') === $key || ($o['bodyFont'] ?? '') === $key)) return true;
        }
        return false;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir) || !str_starts_with(str_replace('\\', '/', $dir), str_replace('\\', '/', TEENY_UPLOADS . '/fonts/'))) return;
        foreach (glob($dir . '/*') ?: [] as $f) if (is_file($f)) @unlink($f);
        @rmdir($dir);
    }

    /** GET a URL on one of Google's two font hosts. Returns [status, body]; status 0 means no connection. */
    private static function get(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, ['fonts.googleapis.com', 'fonts.gstatic.com'], true) || !str_starts_with($url, 'https://')) return [0, ''];
        // A current browser identity makes Google return WOFF2 files.
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT => $ua, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_MAXFILESIZE => self::MAX_FILE + 1024,
            ]);
            $body = curl_exec($ch);
            $status = $body === false ? 0 : (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return [$status, is_string($body) ? $body : ''];
        }
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => 0, 'ignore_errors' => true, 'header' => "User-Agent: $ua\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $body = @file_get_contents($url, false, $ctx, 0, self::MAX_FILE + 1024);
        if ($body === false) return [0, ''];
        $status = 0;
        foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $status = (int)$m[1];
        return [$status, $body];
    }
}
