<?php
declare(strict_types=1);

/**
 * Tiny logic-less template language for section plugins.
 *
 *   {{name}}            value of a field, always HTML-escaped
 *   {{{name}}}          raw HTML, only for "rich" fields (already cleaned); otherwise escaped
 *   {{#name}}…{{/name}} repeat for each item of a list field, or show if the field is not empty
 *   {{^name}}…{{/name}} show only if the field is empty
 *   {{@assets}}         URL of the plugin folder (for images shipped with the plugin)
 *   {{@index}}          1-based position inside a {{#list}}
 *
 * Templates can't run code. On top of that, every rendered result passes through
 * clean(), a strict allow-list that removes scripts, event handlers, styles,
 * frames, forms and dangerous links, whatever the template contains.
 */
final class Templater
{
    private const TOKEN = '~(\{\{\{\s*@?[A-Za-z][A-Za-z0-9_]*\s*\}\}\}|\{\{\s*[#^/]?\s*@?[A-Za-z][A-Za-z0-9_]*\s*\}\})~';

    private const TAGS = [
        'div', 'span', 'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's',
        'small', 'mark', 'sub', 'sup', 'a', 'img', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'figure', 'figcaption',
        'blockquote', 'cite', 'q', 'code', 'pre', 'time', 'section', 'article', 'header', 'footer', 'aside',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'details', 'summary', 'address', 'abbr',
        'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon',
    ];
    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'link', 'meta', 'base', 'template', 'noscript', 'video', 'audio', 'source',
        'track', 'canvas', 'math', 'foreignobject', 'use', 'image', 'animate', 'animatemotion', 'animatetransform',
        'set', 'title', 'head', 'html', 'body', 'dialog', 'portal', 'slot',
    ];
    private const ATTRS = [
        'class', 'title', 'role', 'lang', 'dir', 'width', 'height', 'colspan', 'rowspan', 'datetime', 'open', 'hidden',
        'alt', 'loading', 'decoding', 'target', 'rel', 'href', 'src', 'scope', 'abbr', 'start', 'reversed',
        // SVG presentation attributes
        'viewbox', 'xmlns', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'd', 'cx', 'cy',
        'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points', 'transform', 'opacity', 'fill-rule', 'clip-rule',
        'stroke-dasharray', 'stroke-dashoffset', 'preserveaspectratio', 'focusable',
    ];

    /** Parse a template into a tree. @throws RuntimeException with a friendly message on syntax errors. */
    public static function compile(string $tpl): array
    {
        if (strlen($tpl) > 100000) throw new RuntimeException('template.html is too large (max 100 KB).');
        $parts = preg_split(self::TOKEN, $tpl, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $root = [];
        $stack = [['name' => null, 'nodes' => &$root]];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                if ($part !== '') {
                    if (str_contains($part, '{{')) throw new RuntimeException('Unrecognised tag near: ' . substr(trim($part), 0, 40));
                    $stack[count($stack) - 1]['nodes'][] = ['t' => 'text', 'v' => $part];
                }
                continue;
            }
            $raw = str_starts_with($part, '{{{');
            $inner = trim($raw ? substr($part, 3, -3) : substr($part, 2, -2));
            $kind = in_array($inner[0], ['#', '^', '/'], true) ? $inner[0] : '';
            $name = trim($kind ? substr($inner, 1) : $inner);
            $top = &$stack[count($stack) - 1];
            if ($kind === '#' || $kind === '^') {
                $node = ['t' => $kind === '#' ? 'section' : 'inverted', 'name' => $name, 'nodes' => []];
                $top['nodes'][] = $node;
                $idx = count($top['nodes']) - 1;
                $stack[] = ['name' => $name, 'nodes' => &$top['nodes'][$idx]['nodes']];
            } elseif ($kind === '/') {
                if (count($stack) < 2 || $stack[count($stack) - 1]['name'] !== $name) {
                    throw new RuntimeException('Closing {{/' . $name . '}} does not match an opening tag.');
                }
                array_pop($stack);
            } else {
                $top['nodes'][] = ['t' => $raw ? 'raw' : 'var', 'name' => $name];
            }
            unset($top);
        }
        if (count($stack) !== 1) throw new RuntimeException('{{#' . $stack[count($stack) - 1]['name'] . '}} is never closed.');
        return $root;
    }

    /**
     * @param array $data   field values (strings, or lists of arrays)
     * @param array $types  field name => field type (to decide where {{{raw}}} is allowed)
     * @param array $ctx    ['assets' => url]
     */
    public static function render(array $tree, array $data, array $types, array $ctx = []): string
    {
        return self::clean(self::renderNodes($tree, [$data], $types, $ctx));
    }

    private static function renderNodes(array $nodes, array $scopes, array $types, array $ctx): string
    {
        $out = '';
        foreach ($nodes as $n) {
            switch ($n['t']) {
                case 'text':
                    $out .= $n['v'];
                    break;
                case 'var':
                case 'raw':
                    $v = self::lookup($n['name'], $scopes, $ctx);
                    $s = is_scalar($v) ? (string)$v : '';
                    $out .= ($n['t'] === 'raw' && ($types[$n['name']] ?? '') === 'rich') ? $s : e($s);
                    break;
                case 'section':
                    $v = self::lookup($n['name'], $scopes, $ctx);
                    if (is_array($v) && array_is_list($v)) {
                        foreach (array_values($v) as $i => $item) {
                            $item = is_array($item) ? $item : [];
                            $item['@index'] = $i + 1;
                            $out .= self::renderNodes($n['nodes'], array_merge($scopes, [$item]), $types, $ctx);
                        }
                    } elseif (!self::isEmpty($v)) {
                        $out .= self::renderNodes($n['nodes'], $scopes, $types, $ctx);
                    }
                    break;
                case 'inverted':
                    if (self::isEmpty(self::lookup($n['name'], $scopes, $ctx))) {
                        $out .= self::renderNodes($n['nodes'], $scopes, $types, $ctx);
                    }
                    break;
            }
        }
        return $out;
    }

    private static function lookup(string $name, array $scopes, array $ctx): mixed
    {
        if ($name === '@assets') return $ctx['assets'] ?? '';
        for ($i = count($scopes) - 1; $i >= 0; $i--) {
            if (array_key_exists($name, $scopes[$i])) return $scopes[$i][$name];
        }
        return '';
    }

    private static function isEmpty(mixed $v): bool
    {
        return $v === null || $v === '' || $v === [] || $v === 'no' || $v === false;
    }

    /** Strict allow-list clean-up of rendered plugin HTML. */
    public static function clean(string $html): string
    {
        if (trim($html) === '' || !class_exists('DOMDocument')) return '';
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementsByTagName('div')->item(0);
        if (!$root) return '';
        $ignored = [];
        self::walk($root, $ignored);
        $out = '';
        foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
        return $out;
    }

    /** @param array $problems collects human-readable issues (used when validating templates) */
    private static function walk(DOMNode $node, array &$problems): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) continue;
            if (!$child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->localName ?? $child->nodeName);
            if (in_array($tag, self::DROP, true)) {
                $problems[] = '<' . $tag . '> is not allowed';
                $node->removeChild($child);
                continue;
            }
            self::walk($child, $problems);
            if (!in_array($tag, self::TAGS, true)) {
                $problems[] = '<' . $tag . '> is not supported (its content is kept)';
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                $ok = in_array($name, self::ATTRS, true) || str_starts_with($name, 'aria-') || (str_starts_with($name, 'data-') && preg_match('/^data-[a-z0-9-]+$/', $name));
                if ($ok && $name === 'href' && $tag !== 'a') $ok = false;
                if ($ok && $name === 'src' && $tag !== 'img') $ok = false;
                if (!$ok) {
                    $problems[] = 'attribute "' . $name . '" on <' . $tag . '> is not allowed';
                    $child->removeAttribute($attr->nodeName);
                    continue;
                }
                $val = $attr->nodeValue ?? '';
                if ($name === 'href') {
                    $clean = Sanitizer::url($val);
                    if ($clean === '' && trim($val) !== '') $problems[] = 'link "' . substr($val, 0, 40) . '" is not allowed';
                    $clean === '' ? $child->removeAttribute('href') : $child->setAttribute('href', $clean);
                } elseif ($name === 'src') {
                    // Images may only come from this website (uploads or the plugin's own folder).
                    $v = trim($val);
                    if ($v === '' || preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $v)) {
                        if ($v !== '') $problems[] = 'image "' . substr($v, 0, 40) . '" must be on this website';
                        $child->removeAttribute('src');
                    }
                } elseif ($name === 'target') {
                    if ($val === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
                    else $child->removeAttribute('target');
                } elseif (preg_match('~(javascript|vbscript|data)\s*:~i', $val) && $name !== 'd') {
                    $problems[] = 'attribute "' . $name . '" contains a script link';
                    $child->removeAttribute($attr->nodeName);
                }
            }
        }
    }

    /**
     * Check a template before installing a plugin. Returns a list of problems (empty = fine).
     * Placeholders are replaced with harmless text so only the author's own markup is judged.
     */
    public static function problems(string $tpl): array
    {
        try {
            self::compile($tpl);
        } catch (RuntimeException $ex) {
            return [$ex->getMessage()];
        }
        if (preg_match('~<\s*/?\s*(script|style|iframe|object|embed|form)\b~i', $tpl, $m)) {
            return ['<' . strtolower($m[1]) . '> is not allowed in templates'];
        }
        $probe = (string)preg_replace(self::TOKEN, 'x', $tpl);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>' . $probe . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementsByTagName('div')->item(0);
        $problems = [];
        if ($root) self::walk($root, $problems);
        // Unsupported-but-harmless tags are only warnings; everything else blocks installation.
        return array_values(array_unique(array_filter($problems, static fn($p) => !str_contains($p, 'content is kept'))));
    }
}
