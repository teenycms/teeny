<?php
declare(strict_types=1);

/**
 * Input cleaning. Everything that reaches storage passes through here.
 * Rich text uses a strict allow-list built on PHP's DOM extension (no external libraries).
 */
final class Sanitizer
{
    /** Allowed tags => allowed attributes. Anything else is unwrapped (text kept). */
    private const TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'a' => ['href', 'title', 'target'], 'ul' => [], 'ol' => [], 'li' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
        'mark' => [], 'small' => [], 'sub' => [], 'sup' => [], 'hr' => [],
    ];

    /** Removed completely, including their content. */
    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'template',
        'svg', 'math', 'noscript', 'textarea', 'select', 'option', 'form', 'input', 'button',
        'link', 'meta', 'base', 'title', 'head', 'video', 'audio', 'source', 'canvas', 'img', 'picture',
    ];

    public static function html(string $html): string
    {
        $html = self::utf8($html);
        if (trim($html) === '' || strlen($html) > 200000) return '';
        if (!class_exists('DOMDocument')) {
            // Extremely rare: fall back to plain text with line breaks.
            return nl2br(e(strip_tags($html)), false);
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementsByTagName('div')->item(0);
        if (!$root) return '';
        self::clean($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) continue;
            if (!$child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            self::clean($child);
            if (!isset(self::TAGS[$tag])) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                if (!in_array($name, self::TAGS[$tag], true)) {
                    $child->removeAttribute($attr->nodeName);
                }
            }
            if ($tag === 'a') {
                $href = self::url($child->getAttribute('href'));
                $href === '' ? $child->removeAttribute('href') : $child->setAttribute('href', $href);
                if ($child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $child->removeAttribute('target');
                }
            }
        }
    }

    /** Allows http(s), mailto, tel and site-relative links. Everything else (javascript:, data:, …) becomes ''. */
    public static function url(string $url): string
    {
        $url = trim(self::utf8($url));
        $url = (string)preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if ($url === '' || strlen($url) > 2000) return '';
        if (preg_match('~^(https?://|mailto:|tel:)~i', $url)) return $url;
        if (str_starts_with($url, '//')) return '';
        if (preg_match('~^[/#?]~', $url)) return $url;
        // relative path like "about" or "docs/intro": must not contain a scheme
        if (!preg_match('~^[^/?#]*:~', $url)) return $url;
        return '';
    }

    public static function text(mixed $value, int $max = 200, bool $multiline = false): string
    {
        $s = self::utf8(is_scalar($value) ? (string)$value : '');
        $s = strip_tags($s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
        if (!$multiline) {
            $s = (string)preg_replace('/\s*\n\s*/', ' ', $s);
        } else {
            $s = (string)preg_replace("/\n{3,}/", "\n\n", $s);
        }
        return trim(str_limit(trim($s), $max));
    }

    /** Raw text kept verbatim (escaped on output), for code blocks. */
    public static function code(mixed $value, int $max = 20000): string
    {
        $s = self::utf8(is_scalar($value) ? (string)$value : '');
        $s = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $s);
        return str_limit($s, $max);
    }

    /** Custom CSS from the admin. Strips anything able to break out of <style> or load remote code. */
    public static function css(mixed $value): string
    {
        $css = self::utf8(is_string($value) ? $value : '');
        $css = str_limit($css, 30000);
        // Decode CSS escapes first (e.g. "u\72l(" is "url("), so they can't hide anything from the checks below.
        $css = (string)preg_replace_callback('~\\\\([0-9a-fA-F]{1,6})\s?~', static function (array $m): string {
            $cp = hexdec($m[1]);
            if ($cp <= 0 || $cp > 0x10FFFF || ($cp >= 0xD800 && $cp <= 0xDFFF)) return '';
            // UTF-8 encode by hand (mbstring isn't available on every host)
            if ($cp < 0x80) return chr($cp);
            if ($cp < 0x800) return chr(0xC0 | $cp >> 6) . chr(0x80 | $cp & 0x3F);
            if ($cp < 0x10000) return chr(0xE0 | $cp >> 12) . chr(0x80 | $cp >> 6 & 0x3F) . chr(0x80 | $cp & 0x3F);
            return chr(0xF0 | $cp >> 18) . chr(0x80 | $cp >> 12 & 0x3F) . chr(0x80 | $cp >> 6 & 0x3F) . chr(0x80 | $cp & 0x3F);
        }, $css);
        $css = (string)preg_replace('~\\\\(.)~su', '$1', $css);
        $css = str_replace(['<', '\\'], '', $css); // '<' is what could close a <style> tag; '>' is a valid CSS combinator
        $css = (string)preg_replace('~@import[^;]*;?~i', '', $css);
        $css = (string)preg_replace('~(expression|javascript|vbscript|behavior|-moz-binding)\s*[:(]~i', '', $css);
        $css = (string)preg_replace('~(-webkit-)?image-set\s*\(~i', '(', $css); // makes the rule invalid, so it's ignored
        // url() only for same-site relative files
        $css = (string)preg_replace_callback('~url\(\s*([\'"]?)(.*?)\1\s*\)~i', static function (array $m): string {
            $u = trim($m[2]);
            return (preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $u)) ? 'none' : 'url("' . str_replace(['"', '\\'], '', $u) . '")';
        }, $css);
        return trim($css);
    }

    /**
     * Clean CSS and confine every rule to one wrapper (e.g. ".tc-plugin--countdown"),
     * so a plugin can only style its own section, never the rest of the site.
     * Use "&" or ":scope" in a selector to target the wrapper itself.
     */
    public static function scopeCss(mixed $value, string $scope): string
    {
        $css = (string)preg_replace('~/\*.*?\*/~s', '', self::css($value));
        return self::scopeBlock($css, $scope);
    }

    private static function scopeBlock(string $css, string $scope): string
    {
        $out = '';
        $len = strlen($css);
        $i = 0;
        while ($i < $len) {
            $open = strpos($css, '{', $i);
            if ($open === false) break;
            $selector = trim(substr($css, $i, $open - $i));
            // find the matching closing brace
            $depth = 1;
            $j = $open + 1;
            while ($j < $len && $depth > 0) {
                if ($css[$j] === '{') $depth++;
                elseif ($css[$j] === '}') $depth--;
                $j++;
            }
            $body = substr($css, $open + 1, max(0, $j - $open - 2));
            $i = $j;
            if ($selector === '') continue;
            if (preg_match('~^@(media|supports|container)\b~i', $selector)) {
                $out .= $selector . '{' . self::scopeBlock($body, $scope) . "}\n";
            } elseif (preg_match('~^@(-webkit-)?keyframes\s+[\w-]+$|^@font-face$~i', $selector)) {
                $out .= $selector . '{' . $body . "}\n";
            } elseif ($selector[0] === '@') {
                continue; // other at-rules (@page, @layer, @property…) are dropped
            } else {
                $parts = [];
                foreach (explode(',', $selector) as $sel) {
                    $sel = trim($sel);
                    if ($sel === '') continue;
                    $sel = (string)preg_replace('~^(:root|html|body)\b~i', '&', $sel);
                    $sel = str_replace(':scope', '&', $sel);
                    if (preg_match('/&\s*[~+]/', $sel)) continue; // "& ~ x" / "& + x" would reach neighbouring sections
                    $parts[] = str_contains($sel, '&') ? str_replace('&', $scope, $sel) : $scope . ' ' . $sel;
                }
                if ($parts && !str_contains($body, '{')) $out .= implode(', ', $parts) . '{' . trim($body) . "}\n";
            }
        }
        return $out;
    }

    public static function color(mixed $value, string $default): string
    {
        $v = is_string($value) ? trim($value) : '';
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) ? strtolower($v) : $default;
    }

    public static function int(mixed $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) return $default;
        return max($min, min($max, (int)$value));
    }

    public static function utf8(string $s): string
    {
        return preg_match('//u', $s) ? $s : '';
    }
}
