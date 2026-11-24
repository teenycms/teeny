<?php
declare(strict_types=1);

/**
 * Turns pages and blocks into HTML. All values are escaped here; rich text was already
 * cleaned by the Sanitizer when it was saved. The editor (admin/assets/editor.js) mirrors
 * this markup so what you edit is what visitors see.
 */
final class Renderer
{
    /** Page being rendered (used by forms to post back to the same URL). */
    public static ?array $page = null;
    /** Contact form state after a submission: ['block' => id, 'sent' => bool, 'error' => string, 'old' => array] */
    public static array $form = [];

    public static function blocks(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            $html .= self::block($b) . "\n";
        }
        return $html;
    }

    public static function block(array $b): string
    {
        return self::placement(self::inner($b), $b);
    }

    /** Add the section's position/width classes to its outer element. */
    public static function placement(string $html, array $b): string
    {
        $cls = '';
        $a = (string)($b['boxAlign'] ?? '');
        $w = (string)($b['boxWidth'] ?? '');
        if ($a !== '' && isset(Blocks::PLACEMENT_ALIGN[$a])) $cls .= ' tc-a-' . $a;
        if ($w !== '' && isset(Blocks::PLACEMENT_WIDTH[$w])) $cls .= ' tc-w-' . $w;
        $pos = $cls === '' ? false : strpos($html, 'class="tc-block');
        return $pos === false ? $html : substr_replace($html, 'class="tc-block' . $cls, $pos, 15);
    }

    private static function inner(array $b): string
    {
        return match ($b['type'] ?? '') {
            'hero'    => self::hero($b),
            'profile' => self::profile($b),
            'heading' => self::heading($b),
            'text'    => $b['html'] !== '' ? '<div class="tc-block tc-text tc-prose">' . $b['html'] . '</div>' : '',
            'quote'   => self::quote($b),
            'code'    => self::code($b),
            'image'   => self::image($b),
            'gallery' => self::gallery($b),
            'embed'   => self::embed($b),
            'button'  => self::button($b),
            'links'   => self::links($b),
            'columns' => self::columns($b),
            'faq'     => self::faq($b),
            'pricing' => self::pricing($b),
            'social'  => self::social($b),
            'contact' => self::contact($b),
            'split'      => self::split($b),
            'steps'      => self::steps($b),
            'tabs'       => self::tabs($b),
            'callout'    => self::callout($b),
            'table'      => self::table($b),
            'events'     => self::events($b),
            'stats'      => self::stats($b),
            'cta'        => self::cta($b),
            'logos'      => self::logos($b),
            'team'       => self::team($b),
            'products'   => self::products($b),
            'compare'    => self::compare($b),
            'announce'   => self::announce($b),
            'newsletter' => self::newsletter($b),
            'popup'      => self::popup($b),
            'divider' => '<div class="tc-block tc-divider tc-divider--' . e($b['style']) . '" role="separator"><span></span></div>',
            default   => str_starts_with((string)($b['type'] ?? ''), 'plugin-') ? Plugins::render($b) : '',
        };
    }

    private static function img(string $ref, string $alt, string $class = '', bool $thumb = false, bool $lazy = true): string
    {
        if (!Media::exists($ref)) return '';
        $src = url($thumb ? Media::thumb($ref) : $ref);
        return '<img' . ($class ? ' class="' . $class . '"' : '') . ' src="' . e($src) . '" alt="' . e($alt) . '"' . ($lazy ? ' loading="lazy" decoding="async"' : '') . '>';
    }

    private static function hero(array $b): string
    {
        $style = $b['style'];
        $hasImg = $style !== 'none' && Media::exists($b['image']);
        $cls = 'tc-block tc-hero tc-hero--' . ($hasImg ? $style : 'none');
        $h = '<section class="' . e($cls) . '">';
        if ($hasImg && $style === 'cover') $h .= self::img($b['image'], '', 'tc-hero__bg', false, false);
        $h .= '<div class="tc-hero__inner"><div class="tc-hero__content">';
        if ($b['eyebrow'] !== '') $h .= '<p class="tc-eyebrow">' . e($b['eyebrow']) . '</p>';
        if ($b['title'] !== '') $h .= '<h1 class="tc-hero__title">' . e($b['title']) . '</h1>';
        if ($b['text'] !== '') $h .= '<p class="tc-hero__text">' . e($b['text']) . '</p>';
        if ($b['button'] !== '') $h .= '<p class="tc-actions"><a class="tc-btn tc-btn--solid" href="' . e($b['url'] ?: '#') . '">' . e($b['button']) . '</a></p>';
        $h .= '</div>';
        if ($hasImg && $style === 'side') $h .= '<figure class="tc-hero__media">' . self::img($b['image'], $b['title'], '', false, false) . '</figure>';
        return $h . '</div></section>';
    }

    private static function profile(array $b): string
    {
        $h = '<section class="tc-block tc-profile">';
        $h .= self::img($b['avatar'], $b['name'], 'tc-profile__avatar', true, false);
        if ($b['name'] !== '') $h .= '<h1 class="tc-profile__name">' . e($b['name']) . '</h1>';
        if ($b['bio'] !== '') $h .= '<p class="tc-profile__bio">' . e($b['bio']) . '</p>';
        return $h . '</section>';
    }

    private static function heading(array $b): string
    {
        if ($b['text'] === '') return '';
        $tag = in_array($b['level'], ['h1', 'h2', 'h3'], true) ? $b['level'] : 'h2';
        $id = slugify($b['text']);
        return '<div class="tc-block tc-heading tc-heading--' . $tag . '"><' . $tag . ($id ? ' id="' . e($id) . '"' : '') . '>' . e($b['text']) . '</' . $tag . '></div>';
    }

    private static function quote(array $b): string
    {
        if ($b['text'] === '') return '';
        return '<blockquote class="tc-block tc-quote"><p class="tc-quote__text">' . e($b['text']) . '</p>'
            . ($b['cite'] !== '' ? '<cite class="tc-quote__cite">' . e($b['cite']) . '</cite>' : '') . '</blockquote>';
    }

    private static function code(array $b): string
    {
        if ($b['code'] === '') return '';
        return '<div class="tc-block tc-code">' . ($b['lang'] !== '' ? '<span class="tc-code__lang">' . e($b['lang']) . '</span>' : '')
            . '<pre><code>' . e($b['code']) . '</code></pre></div>';
    }

    private static function image(array $b): string
    {
        $img = self::img($b['src'], $b['alt']);
        if ($img === '') return '';
        return '<figure class="tc-block tc-image tc-image--' . e($b['size']) . '">' . $img
            . ($b['caption'] !== '' ? '<figcaption>' . e($b['caption']) . '</figcaption>' : '') . '</figure>';
    }

    private static function gallery(array $b): string
    {
        $items = '';
        foreach ($b['images'] as $it) {
            if (!Media::exists($it['src'])) continue;
            $items .= '<figure class="tc-gallery__item"><a href="' . e(url($it['src'])) . '" data-lightbox>'
                . self::img($it['src'], $it['alt'] ?: $it['caption'], '', true) . '</a>'
                . ($it['caption'] !== '' ? '<figcaption>' . e($it['caption']) . '</figcaption>' : '') . '</figure>';
        }
        if ($items === '') return '';
        return '<div class="tc-block tc-gallery tc-cols-' . e($b['columns']) . '">' . $items . '</div>';
    }

    private static function embed(array $b): string
    {
        $src = Blocks::embedSrc($b['url']);
        if ($src === '') return '';
        return '<figure class="tc-block tc-embed"><div class="tc-embed__frame"><iframe src="' . e($src) . '" title="' . e($b['caption'] ?: 'Video') . '" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen"></iframe></div>'
            . ($b['caption'] !== '' ? '<figcaption>' . e($b['caption']) . '</figcaption>' : '') . '</figure>';
    }

    private static function button(array $b): string
    {
        if ($b['text'] === '') return '';
        return '<p class="tc-block tc-button"><a class="tc-btn tc-btn--' . e($b['style']) . '" href="' . e($b['url'] ?: '#') . '">' . e($b['text']) . '</a></p>';
    }

    private static function links(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            if ($it['label'] === '') continue;
            $h .= '<a class="tc-link" href="' . e($it['url'] ?: '#') . '"><span>' . e($it['label']) . '</span></a>';
        }
        return $h === '' ? '' : '<nav class="tc-block tc-links" aria-label="Links">' . $h . '</nav>';
    }

    private static function columns(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            $h .= '<div class="tc-col">' . ($it['title'] !== '' ? '<h3 class="tc-col__title">' . e($it['title']) . '</h3>' : '')
                . ($it['text'] !== '' ? '<p class="tc-col__text">' . e($it['text']) . '</p>' : '') . '</div>';
        }
        $n = max(1, min(4, count($b['items'])));
        return $h === '' ? '' : '<div class="tc-block tc-columns tc-cols-' . $n . '">' . $h . '</div>';
    }

    private static function faq(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            if ($it['q'] === '') continue;
            $h .= '<details class="tc-faq__item"><summary class="tc-faq__q">' . e($it['q']) . '</summary><div class="tc-faq__a">' . e($it['a']) . '</div></details>';
        }
        return $h === '' ? '' : '<div class="tc-block tc-faq">' . $h . '</div>';
    }

    private static function pricing(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            $featured = $it['featured'] === 'yes';
            $h .= '<div class="tc-plan' . ($featured ? ' tc-plan--featured' : '') . '">';
            if ($it['name'] !== '') $h .= '<h3 class="tc-plan__name">' . e($it['name']) . '</h3>';
            if ($it['price'] !== '') $h .= '<p class="tc-plan__price"><span class="tc-plan__amount">' . e($it['price']) . '</span> <span class="tc-plan__period">' . e($it['period']) . '</span></p>';
            $features = array_filter(array_map('trim', explode("\n", $it['features'])), static fn($f) => $f !== '');
            if ($features) {
                $h .= '<ul class="tc-plan__features">';
                foreach ($features as $f) $h .= '<li>' . e($f) . '</li>';
                $h .= '</ul>';
            }
            if ($it['button'] !== '') $h .= '<p class="tc-plan__action"><a class="tc-btn tc-btn--' . ($featured ? 'solid' : 'outline') . '" href="' . e($it['url'] ?: '#') . '">' . e($it['button']) . '</a></p>';
            $h .= '</div>';
        }
        $n = max(1, min(4, count($b['items'])));
        return $h === '' ? '' : '<div class="tc-block tc-pricing tc-cols-' . $n . '">' . $h . '</div>';
    }

    private static function social(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            if ($it['url'] === '') continue;
            $label = Blocks::SOCIAL[$it['network']]['label'] ?? 'Link';
            $h .= '<a class="tc-social__link" href="' . e($it['url']) . '" aria-label="' . e($label) . '" title="' . e($label) . '" rel="me noopener">' . Blocks::socialSvg($it['network']) . '</a>';
        }
        return $h === '' ? '' : '<nav class="tc-block tc-social tc-social--' . e($b['size']) . '" aria-label="Social media">' . $h . '</nav>';
    }

    private static function contact(array $b): string
    {
        $id = $b['id'];
        $state = (self::$form['block'] ?? '') === $id ? self::$form : [];
        $h = '<div class="tc-block tc-contact" id="contact-' . e($id) . '">';
        if (!empty($state['sent'])) {
            return $h . '<p class="tc-form-msg tc-form-msg--ok" role="status">' . e($b['success']) . '</p></div>';
        }
        if (!empty($state['error'])) {
            $h .= '<p class="tc-form-msg tc-form-msg--err" role="alert">' . e($state['error']) . '</p>';
        }
        $old = $state['old'] ?? [];
        $action = self::$page ? page_url(self::$page) : url('');
        $h .= '<form class="tc-form" method="post" action="' . e($action) . '#contact-' . e($id) . '">'
            . '<input type="hidden" name="_teeny_form" value="contact"><input type="hidden" name="_block" value="' . e($id) . '">'
            . '<input type="hidden" name="_t" value="" data-tc-timer>'
            . '<div class="tc-hp" aria-hidden="true"><label>Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label></div>'
            . '<div class="tc-form-row">'
            . '<label class="tc-field"><span>Name</span><input name="name" required maxlength="100" autocomplete="name" value="' . e($old['name'] ?? '') . '"></label>'
            . '<label class="tc-field"><span>Email</span><input type="email" name="email" required maxlength="200" autocomplete="email" value="' . e($old['email'] ?? '') . '"></label>'
            . '</div>';
        if ($b['phone'] === 'yes') {
            $h .= '<label class="tc-field"><span>Phone</span><input type="tel" name="phone" maxlength="40" autocomplete="tel" value="' . e($old['phone'] ?? '') . '"></label>';
        }
        $h .= '<label class="tc-field"><span>Message</span><textarea name="message" required rows="5" maxlength="5000">' . e($old['message'] ?? '') . '</textarea></label>'
            . '<p class="tc-form-actions"><button class="tc-btn tc-btn--solid" type="submit">' . e($b['button'] ?: 'Send message') . '</button></p>'
            . '</form></div>';
        return $h;
    }

    /* ---------- more sections ---------- */

    public const CALLOUT_ICONS = [
        'tip'     => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.4 1 1.1 1 1.8V16h5v-.3c0-.7.4-1.4 1-1.8A6 6 0 0 0 12 3z"/>',
        'note'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.5"/>',
        'warning' => '<path d="M12 3.5 2.5 20h19z"/><path d="M12 10v4.5M12 17v.5"/>',
        'success' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9.5"/>',
    ];

    private static function split(array $b): string
    {
        $img = self::img($b['image'], $b['alt'] ?: $b['title']);
        $h = '<section class="tc-block tc-split tc-split--' . e($b['side']) . ($img === '' ? ' tc-split--noimg' : '') . '">';
        if ($img !== '') $h .= '<figure class="tc-split__media">' . $img . '</figure>';
        $h .= '<div class="tc-split__body">';
        if ($b['title'] !== '') $h .= '<h2 class="tc-split__title">' . e($b['title']) . '</h2>';
        if ($b['html'] !== '') $h .= '<div class="tc-split__text tc-prose">' . $b['html'] . '</div>';
        if ($b['button'] !== '') $h .= '<p class="tc-actions"><a class="tc-btn tc-btn--solid" href="' . e($b['url'] ?: '#') . '">' . e($b['button']) . '</a></p>';
        return $h . '</div></section>';
    }

    private static function steps(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $i => $it) {
            if ($it['title'] === '' && $it['text'] === '') continue;
            $h .= '<li class="tc-step"><span class="tc-step__num" aria-hidden="true">' . ($i + 1) . '</span><div class="tc-step__body">'
                . ($it['title'] !== '' ? '<h3 class="tc-step__title">' . e($it['title']) . '</h3>' : '')
                . ($it['text'] !== '' ? '<p class="tc-step__text">' . e($it['text']) . '</p>' : '') . '</div></li>';
        }
        return $h === '' ? '' : '<ol class="tc-block tc-steps tc-cols-' . max(1, min(4, count($b['items']))) . '">' . $h . '</ol>';
    }

    private static function tabs(array $b): string
    {
        $items = array_values(array_filter($b['items'], static fn($it) => $it['label'] !== ''));
        if (!$items) return '';
        $base = 'tabs-' . $b['id'];
        $list = '';
        $panels = '';
        foreach ($items as $i => $it) {
            $tid = $base . '-t' . $i;
            $pid = $base . '-p' . $i;
            $list .= '<button type="button" class="tc-tabs__tab" role="tab" id="' . $tid . '" aria-controls="' . $pid . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"' . ($i === 0 ? '' : ' tabindex="-1"') . '>' . e($it['label']) . '</button>';
            $panels .= '<div class="tc-tabs__panel" role="tabpanel" id="' . $pid . '" aria-labelledby="' . $tid . '" tabindex="0"><h3 class="tc-tabs__heading">' . e($it['label']) . '</h3><div class="tc-tabs__text">' . e($it['text']) . '</div></div>';
        }
        return '<div class="tc-block tc-tabs' . ($b['mono'] === 'yes' ? ' tc-tabs--mono' : '') . '" data-tabs><div class="tc-tabs__list" role="tablist">' . $list . '</div>' . $panels . '</div>';
    }

    private static function callout(array $b): string
    {
        if ($b['title'] === '' && $b['text'] === '') return '';
        $icon = self::CALLOUT_ICONS[$b['kind']] ?? self::CALLOUT_ICONS['note'];
        return '<aside class="tc-block tc-callout tc-callout--' . e($b['kind']) . '" role="note"><span class="tc-callout__icon"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $icon . '</svg></span><div class="tc-callout__body">'
            . ($b['title'] !== '' ? '<p class="tc-callout__title">' . e($b['title']) . '</p>' : '')
            . ($b['text'] !== '' ? '<p class="tc-callout__text">' . e($b['text']) . '</p>' : '') . '</div></aside>';
    }

    /** Table cell: "yes" → ✓, "no" → ✕, otherwise escaped text. */
    public static function cell(string $v): string
    {
        $v = trim($v);
        $l = strtolower($v);
        if (in_array($l, ['yes', 'y', '✓', 'true'], true)) return '<span class="tc-yes" role="img" aria-label="Yes">✓</span>';
        if (in_array($l, ['no', 'n', '-', '—', 'false'], true)) return '<span class="tc-no" role="img" aria-label="No">✕</span>';
        return e($v);
    }

    private static function table(array $b): string
    {
        $rows = array_values(array_filter(array_map('trim', explode("\n", $b['rows'])), static fn($r) => $r !== ''));
        $head = $b['header'] !== '' ? array_map('trim', explode('|', $b['header'])) : [];
        if (!$rows && !$head) return '';
        $h = '<div class="tc-block tc-table-wrap' . ($b['firstcol'] === 'yes' ? ' tc-table--firstcol' : '') . '">';
        if ($b['title'] !== '') $h .= '<p class="tc-table__title">' . e($b['title']) . '</p>';
        $h .= '<div class="tc-table-scroll"><table class="tc-table">';
        if ($head) {
            $h .= '<thead><tr>';
            foreach ($head as $c) $h .= '<th scope="col">' . e($c) . '</th>';
            $h .= '</tr></thead>';
        }
        $h .= '<tbody>';
        foreach (array_slice($rows, 0, 200) as $r) {
            $h .= '<tr>';
            foreach (array_slice(explode('|', $r), 0, 12) as $i => $c) {
                $h .= ($i === 0 && $b['firstcol'] === 'yes') ? '<th scope="row">' . e(trim($c)) . '</th>' : '<td>' . self::cell($c) . '</td>';
            }
            $h .= '</tr>';
        }
        return $h . '</tbody></table></div></div>';
    }

    private static function events(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            if ($it['title'] === '') continue;
            $h .= '<div class="tc-event"><div class="tc-event__date">' . ($it['date'] !== '' ? '<span>' . e($it['date']) . '</span>' : '') . ($it['time'] !== '' ? '<small>' . e($it['time']) . '</small>' : '') . '</div>'
                . '<div class="tc-event__body"><h3 class="tc-event__title">' . e($it['title']) . '</h3>' . ($it['place'] !== '' ? '<p class="tc-event__place">' . e($it['place']) . '</p>' : '') . '</div>'
                . ($it['url'] !== '' ? '<a class="tc-btn tc-btn--outline tc-event__btn" href="' . e($it['url']) . '">' . e($b['linkText'] ?: 'Details') . '</a>' : '') . '</div>';
        }
        return $h === '' ? '' : '<div class="tc-block tc-events">' . $h . '</div>';
    }

    private static function stats(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            if ($it['value'] === '' && $it['label'] === '') continue;
            $h .= '<div class="tc-stat"><span class="tc-stat__value">' . e($it['value']) . '</span><span class="tc-stat__label">' . e($it['label']) . '</span></div>';
        }
        return $h === '' ? '' : '<div class="tc-block tc-stats tc-cols-' . max(1, min(6, count($b['items']))) . '">' . $h . '</div>';
    }

    /**
     * Popup: stays hidden in the page and is shown by assets/site.js according to its trigger and
     * "how often" setting. Without JavaScript it simply never appears. Everything is escaped.
     */
    private static function popup(array $b): string
    {
        if ($b['title'] === '' && $b['text'] === '' && $b['button'] === '') return '';
        $kind = isset(Blocks::POPUP_KIND[$b['kind']]) ? $b['kind'] : 'modal';
        $place = (string)$b['place'];
        if ($kind === 'bar') $place = $place === 'bottom' ? 'bottom' : 'top';
        elseif ($kind === 'corner') $place = $place === 'left' ? 'left' : 'right';
        else $place = 'center';
        $id = preg_replace('/[^a-z0-9]/', '', (string)$b['id']);
        $titleId = 'tc-pop-' . $id . '-t';
        $label = $b['title'] !== '' ? ' aria-labelledby="' . $titleId . '"' : ' aria-label="Message"';
        $h = '<div class="tc-popup tc-popup--' . $kind . ' tc-popup--' . $place . '" data-popup="' . $id . '"'
            . ' data-trigger="' . e(isset(Blocks::POPUP_TRIGGER[$b['trigger']]) ? $b['trigger'] : 'delay5') . '"'
            . ' data-often="' . e(isset(Blocks::POPUP_OFTEN[$b['often']]) ? $b['often'] : 'week') . '"'
            . ' role="dialog"' . ($kind === 'modal' ? ' aria-modal="true"' : '') . $label . ' hidden>';
        if ($kind === 'modal') $h .= '<div class="tc-popup__backdrop" data-popup-close></div>';
        $h .= '<div class="tc-popup__box">';
        if ($kind === 'modal' && $b['image'] !== '') $h .= self::img($b['image'], $b['alt'], 'tc-popup__img');
        $h .= '<div class="tc-popup__body">';
        if ($b['title'] !== '') $h .= '<p class="tc-popup__title" id="' . $titleId . '">' . e($b['title']) . '</p>';
        if ($b['text'] !== '') $h .= '<p class="tc-popup__text">' . nl2br(e($b['text']), false) . '</p>';
        $h .= '</div>';
        if ($b['button'] !== '') $h .= '<a class="tc-btn tc-btn--solid tc-popup__btn" href="' . e($b['url'] ?: '#') . '" data-popup-done>' . e($b['button']) . '</a>';
        $h .= '<button type="button" class="tc-popup__close" data-popup-close aria-label="Close">'
            . '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>';
        return $h . '</div></div>';
    }

    private static function cta(array $b): string
    {
        if ($b['title'] === '' && $b['button'] === '') return '';
        return '<section class="tc-block tc-cta tc-cta--' . e($b['style']) . '"><div class="tc-cta__inner">'
            . ($b['title'] !== '' ? '<h2 class="tc-cta__title">' . e($b['title']) . '</h2>' : '')
            . ($b['text'] !== '' ? '<p class="tc-cta__text">' . e($b['text']) . '</p>' : '')
            . ($b['button'] !== '' ? '<p class="tc-actions"><a class="tc-btn tc-cta__btn" href="' . e($b['url'] ?: '#') . '">' . e($b['button']) . '</a></p>' : '')
            . '</div></section>';
    }

    private static function logos(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            $img = self::img($it['image'], $it['alt']);
            if ($img === '') continue;
            $h .= $it['url'] !== '' ? '<a class="tc-logo" href="' . e($it['url']) . '" title="' . e($it['alt']) . '">' . $img . '</a>' : '<span class="tc-logo">' . $img . '</span>';
        }
        if ($h === '') return '';
        return '<div class="tc-block tc-logos' . ($b['mono'] === 'yes' ? ' tc-logos--mono' : '') . '">' . ($b['title'] !== '' ? '<p class="tc-logos__title">' . e($b['title']) . '</p>' : '') . '<div class="tc-logos__row">' . $h . '</div></div>';
    }

    private static function team(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            if ($it['name'] === '') continue;
            $name = $it['url'] !== '' ? '<a href="' . e($it['url']) . '">' . e($it['name']) . '</a>' : e($it['name']);
            $h .= '<div class="tc-member">' . self::img($it['photo'], $it['name'], 'tc-member__photo', true)
                . '<h3 class="tc-member__name">' . $name . '</h3>' . ($it['role'] !== '' ? '<p class="tc-member__role">' . e($it['role']) . '</p>' : '') . '</div>';
        }
        return $h === '' ? '' : '<div class="tc-block tc-team tc-cols-' . e($b['columns']) . '">' . $h . '</div>';
    }

    private static function products(array $b): string
    {
        $h = '';
        foreach ($b['items'] as $it) {
            if ($it['name'] === '') continue;
            $img = self::img($it['image'], $it['name'], '', true);
            $h .= '<article class="tc-product">' . ($img !== '' ? '<figure class="tc-product__media">' . $img . '</figure>' : '')
                . '<div class="tc-product__body"><h3 class="tc-product__name">' . e($it['name']) . '</h3>'
                . ($it['price'] !== '' ? '<p class="tc-product__price">' . e($it['price']) . '</p>' : '')
                . ($it['desc'] !== '' ? '<p class="tc-product__desc">' . e($it['desc']) . '</p>' : '')
                . ($it['button'] !== '' ? '<p class="tc-product__action"><a class="tc-btn tc-btn--solid" href="' . e($it['url'] ?: '#') . '">' . e($it['button']) . '</a></p>' : '')
                . '</div></article>';
        }
        return $h === '' ? '' : '<div class="tc-block tc-products tc-cols-' . e($b['columns']) . '">' . $h . '</div>';
    }

    private static function compare(array $b): string
    {
        if (!Media::exists($b['before']) || !Media::exists($b['after'])) return '';
        return '<figure class="tc-block tc-compare" data-compare><div class="tc-compare__frame">'
            . '<img class="tc-compare__img" src="' . e(url($b['after'])) . '" alt="' . e($b['afterLabel'] ?: 'After') . '" loading="lazy">'
            . '<div class="tc-compare__before"><img class="tc-compare__img" src="' . e(url($b['before'])) . '" alt="' . e($b['beforeLabel'] ?: 'Before') . '" loading="lazy"></div>'
            . '<span class="tc-compare__handle" aria-hidden="true"></span>'
            . ($b['beforeLabel'] !== '' ? '<span class="tc-compare__label tc-compare__label--before">' . e($b['beforeLabel']) . '</span>' : '')
            . ($b['afterLabel'] !== '' ? '<span class="tc-compare__label tc-compare__label--after">' . e($b['afterLabel']) . '</span>' : '')
            . '<input class="tc-compare__range" type="range" min="0" max="100" value="50" aria-label="Drag to compare ' . e($b['beforeLabel'] ?: 'before') . ' and ' . e($b['afterLabel'] ?: 'after') . '">'
            . '</div>' . ($b['caption'] !== '' ? '<figcaption>' . e($b['caption']) . '</figcaption>' : '') . '</figure>';
    }

    private static function announce(array $b): string
    {
        if ($b['text'] === '') return '';
        return '<div class="tc-block tc-announce tc-announce--' . e($b['style']) . '"><p class="tc-announce__inner"><span class="tc-announce__text">' . e($b['text']) . '</span>'
            . ($b['linkText'] !== '' && $b['url'] !== '' ? ' <a class="tc-announce__link" href="' . e($b['url']) . '">' . e($b['linkText']) . ' →</a>' : '') . '</p></div>';
    }

    private static function newsletter(array $b): string
    {
        $id = $b['id'];
        $state = (self::$form['block'] ?? '') === $id ? self::$form : [];
        $h = '<div class="tc-block tc-newsletter" id="newsletter-' . e($id) . '">';
        if ($b['title'] !== '') $h .= '<h2 class="tc-newsletter__title">' . e($b['title']) . '</h2>';
        if ($b['text'] !== '') $h .= '<p class="tc-newsletter__text">' . e($b['text']) . '</p>';
        if (!empty($state['sent'])) {
            return $h . '<p class="tc-form-msg tc-form-msg--ok" role="status">' . e($b['success']) . '</p></div>';
        }
        if (!empty($state['error'])) $h .= '<p class="tc-form-msg tc-form-msg--err" role="alert">' . e($state['error']) . '</p>';
        $action = self::$page ? page_url(self::$page) : url('');
        $h .= '<form class="tc-form tc-newsletter__form" method="post" action="' . e($action) . '#newsletter-' . e($id) . '">'
            . '<input type="hidden" name="_teeny_form" value="newsletter"><input type="hidden" name="_block" value="' . e($id) . '"><input type="hidden" name="_t" value="" data-tc-timer>'
            . '<div class="tc-hp" aria-hidden="true"><label>Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label></div>'
            . '<label class="tc-newsletter__field"><span class="tc-sr">Email address</span><input type="email" name="email" required maxlength="200" autocomplete="email" placeholder="you@example.com" value="' . e($state['old']['email'] ?? '') . '"></label>'
            . '<button class="tc-btn tc-btn--solid" type="submit">' . e($b['button'] ?: 'Subscribe') . '</button></form>';
        return $h . '</div>';
    }

    /* ---------- full documents ---------- */

    public static function header(array $site, ?array $current): string
    {
        $h = '<header class="tc-header"><div class="tc-header__inner">';
        $brand = '';
        if (Media::exists((string)($site['logo'] ?? ''))) {
            $brand .= '<img class="tc-brand__logo" src="' . e(url($site['logo'])) . '" alt="">';
        }
        $brand .= '<span class="tc-brand__name">' . e($site['title'] ?? 'My Site') . '</span>';
        $h .= '<a class="tc-brand" href="' . e(url('')) . '">' . $brand . '</a>';
        $nav = Menu::items($current);
        if ($nav) {
            $h .= '<nav class="tc-nav" aria-label="Main">';
            foreach ($nav as $it) {
                $h .= '<a href="' . e($it['url']) . '"' . ($it['current'] ? ' aria-current="page"' : '')
                    . ($it['newTab'] ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . e($it['label']) . '</a>';
            }
            $h .= '</nav>';
        }
        return $h . '</div></header>';
    }

    /** Footer positions: '' follows the page alignment. */
    public const FOOTER_ALIGN = ['' => 'Auto', 'left' => 'Left', 'center' => 'Center', 'right' => 'Right'];

    /**
     * The site footer (one text for the whole site, edited on any page in the editor).
     * Each page can hide it. In the editor it's always shown, so it can be clicked and edited.
     */
    public static function footer(array $site, ?array $page = null, bool $editor = false, bool $canEdit = false): string
    {
        $raw = (string)($site['footer'] ?? '');
        $align = (string)($site['footerAlign'] ?? '');
        $cls = 'tc-footer' . (isset(self::FOOTER_ALIGN[$align]) && $align !== '' ? ' tc-footer--' . $align : '');
        $extra = self::footerExtras($site);
        if ($editor) {
            return '<footer class="' . $cls . ' tce-footer" data-footer><div class="tc-footer__inner">'
                . '<div class="tc-footer__text" data-footer-text data-ph="Footer text" contenteditable="' . ($canEdit ? 'true' : 'false') . '" spellcheck="true">' . e($raw) . '</div>'
                . '<div class="tc-footer__extra" data-footer-extra contenteditable="false">' . $extra . '</div></div></footer>';
        }
        if (!empty($page['hideFooter']) || (trim($raw) === '' && $extra === '')) return '';
        return '<footer class="' . $cls . '"><div class="tc-footer__inner">'
            . (trim($raw) !== '' ? '<div class="tc-footer__text">' . self::footerText($raw, $site) . '</div>' : '')
            . ($extra !== '' ? '<div class="tc-footer__extra">' . $extra . '</div>' : '')
            . '</div></footer>';
    }

    /**
     * Footer text placeholders: {year} → this year, {site} → the site name, {teeny} → a "Teeny CMS" link.
     * The text is escaped first; only the fixed Teeny link is added as HTML.
     */
    public static function footerText(string $raw, array $site): string
    {
        return strtr(e($raw), [
            '{year}'  => date('Y'),
            '{site}'  => e($site['title'] ?? ''),
            '{teeny}' => '<a href="https://teenycms.github.io" rel="noopener">Teeny CMS</a>',
        ]);
    }

    /** The footer's links and social icons (empty string when there are none). */
    public static function footerExtras(array $site): string
    {
        $links = '';
        foreach (is_array($site['footerLinks'] ?? null) ? $site['footerLinks'] : [] as $l) {
            if (!is_array($l) || ($l['label'] ?? '') === '' || ($l['url'] ?? '') === '') continue;
            $links .= '<a href="' . e($l['url']) . '">' . e($l['label']) . '</a>';
        }
        $icons = '';
        foreach (is_array($site['footerSocial'] ?? null) ? $site['footerSocial'] : [] as $s) {
            if (!is_array($s) || ($s['url'] ?? '') === '' || !isset(Blocks::SOCIAL[$s['network'] ?? ''])) continue;
            $label = Blocks::SOCIAL[$s['network']]['label'];
            $icons .= '<a href="' . e($s['url']) . '" aria-label="' . e($label) . '" title="' . e($label) . '" rel="me noopener">' . Blocks::socialSvg($s['network']) . '</a>';
        }
        return ($links !== '' ? '<nav class="tc-footer__links" aria-label="Footer">' . $links . '</nav>' : '')
            . ($icons !== '' ? '<nav class="tc-footer__social" aria-label="Social media">' . $icons . '</nav>' : '');
    }

    /** Clean footer links and social icons from the editor (both lists are short and fully checked). */
    public static function cleanFooterLists(mixed $links, mixed $social): array
    {
        $outLinks = [];
        foreach (array_slice(is_array($links) ? array_values($links) : [], 0, 6) as $l) {
            if (!is_array($l)) continue;
            $label = Sanitizer::text($l['label'] ?? '', 40);
            $url = Sanitizer::url(is_string($l['url'] ?? null) ? $l['url'] : '');
            if ($label !== '' || $url !== '') $outLinks[] = ['label' => $label, 'url' => $url];
        }
        $outSocial = [];
        foreach (array_slice(is_array($social) ? array_values($social) : [], 0, 8) as $s) {
            if (!is_array($s) || !isset(Blocks::SOCIAL[(string)($s['network'] ?? '')])) continue;
            $outSocial[] = ['network' => (string)$s['network'], 'url' => Sanitizer::url(is_string($s['url'] ?? null) ? $s['url'] : '')];
        }
        return [$outLinks, $outSocial];
    }

    /**
     * @param array $opts editor => bool (canvas mode), status => int, notice => string
     */
    public static function document(array $site, ?array $page, string $main, array $opts = []): string
    {
        $editor = !empty($opts['editor']);
        $theme = Theme::active($site);
        $align = Theme::align($site, $page);
        $titleParts = [];
        if ($page && ($site['home'] ?? '') !== $page['id']) $titleParts[] = $page['title'];
        $titleParts[] = $site['title'] ?? 'My Site';
        if ($page && ($site['home'] ?? '') === $page['id'] && !empty($site['tagline'])) $titleParts[] = $site['tagline'];
        $title = $opts['title'] ?? implode(' · ', $titleParts);
        $desc = $page['description'] ?? '' ?: ($site['description'] ?? '');
        $favicon = Media::exists((string)($site['favicon'] ?? '')) ? url($site['favicon']) : asset('assets/favicon.svg');
        $hideHeader = !empty($page['hideHeader']);

        $o = '<!doctype html><html lang="' . e($site['lang'] ?? 'en') . '"><head><meta charset="utf-8">';
        $o .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $o .= '<title>' . e($title) . '</title>';
        if ($desc !== '') $o .= '<meta name="description" content="' . e($desc) . '">';
        $o .= '<meta property="og:type" content="website"><meta property="og:site_name" content="' . e($site['title'] ?? 'My Site') . '">';
        $o .= '<meta property="og:title" content="' . e($title) . '">';
        if ($desc !== '') $o .= '<meta property="og:description" content="' . e($desc) . '">';
        $hasShareImage = false;
        if ($page && !$editor) {
            $canon = absolute_url(page_url($page));
            if ($canon !== '') $o .= '<link rel="canonical" href="' . e($canon) . '"><meta property="og:url" content="' . e($canon) . '">';
            $share = (string)($site['shareImage'] ?? '');
            if (Media::exists($share) && absolute_url(url($share)) !== '') {
                $o .= '<meta property="og:image" content="' . e(absolute_url(url($share))) . '">';
                $hasShareImage = true;
            }
        }
        $o .= '<meta name="twitter:card" content="' . ($hasShareImage ? 'summary_large_image' : 'summary') . '">';
        $o .= '<meta name="theme-color" content="' . e(Theme::settings($site)['bg']) . '">';
        if ($editor || ($page['status'] ?? '') !== 'published' || !empty($opts['noindex'])) $o .= '<meta name="robots" content="noindex">';
        $o .= '<link rel="icon" href="' . e($favicon) . '">';
        $toggle = !$editor && Theme::visitorToggle($site);
        if ($toggle) $o .= '<script src="' . e(asset('assets/scheme.js')) . '"></script>';
        $o .= '<link rel="stylesheet" href="' . e(asset('assets/site.css')) . '">';
        $o .= '<link rel="stylesheet" href="' . e(asset('themes/' . $theme . '/theme.css')) . '">';
        if ($editor) $o .= '<link rel="stylesheet" href="' . e(asset('admin/assets/canvas.css')) . '">';
        $o .= '<style>' . Theme::css($site) . '</style>';
        $pluginCss = Plugins::css($editor ? array_keys(Plugins::enabled()) : ($page ? Plugins::usedBy($page) : []));
        if ($pluginCss !== '') $o .= '<style>' . $pluginCss . '</style>';
        $o .= '</head><body class="tc theme-' . e($theme) . ' align-' . e($align) . ($hideHeader ? ' tc-no-header' : '') . (!empty($page['hideFooter']) ? ' tc-no-footer' : '') . '">';
        if (!empty($opts['notice'])) $o .= '<div class="tc-preview-bar">' . e($opts['notice']) . '</div>';
        $o .= '<div class="tc-site">';
        $o .= self::header($site, $page);
        $o .= '<main class="tc-main" id="' . ($editor ? 'tce-root' : 'main') . '">' . $main . '</main>';
        $o .= self::footer($site, $page, $editor, !empty($opts['footerEditable']));
        $o .= '</div>';
        if ($toggle) {
            $dark = Theme::isDark($site);
            $sun = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>';
            $moon = '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>';
            $o .= '<button type="button" class="tc-scheme-toggle" data-scheme-toggle data-label-default="' . ($dark ? 'Switch to light mode' : 'Switch to dark mode') . '" data-label-alt="' . ($dark ? 'Switch to dark mode' : 'Switch to light mode') . '" aria-label="' . ($dark ? 'Switch to light mode' : 'Switch to dark mode') . '">'
                . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" data-icon-default>' . ($dark ? $sun : $moon) . '</svg>'
                . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" data-icon-alt hidden>' . ($dark ? $moon : $sun) . '</svg></button>';
        }
        if (!$editor) $o .= '<script src="' . e(asset('assets/site.js')) . '" defer></script>';
        return $o . '</body></html>';
    }

    public static function page(array $site, array $page, array $opts = []): string
    {
        self::$page = $page;
        $main = self::blocks($page['blocks'] ?? []);
        if (trim($main) === '') {
            $main = '<div class="tc-block tc-text tc-prose"><p class="tc-muted">This page is empty.</p></div>';
        }
        return self::document($site, $page, $main, $opts);
    }

    /** Password form shown instead of a protected page. */
    public static function gate(array $site, array $page, string $error = ''): string
    {
        self::$page = $page;
        $main = '<section class="tc-block tc-hero tc-hero--none"><div class="tc-hero__inner"><div class="tc-hero__content tc-gate">'
            . '<p class="tc-eyebrow">Private page</p><h1 class="tc-hero__title">' . e($page['title']) . '</h1>'
            . '<p class="tc-hero__text">This page is protected. Please enter the password to continue.</p>'
            . ($error !== '' ? '<p class="tc-form-msg tc-form-msg--err" role="alert">' . e($error) . '</p>' : '')
            . '<form class="tc-form" method="post" action="' . e(page_url($page)) . '">' . Auth::csrfField()
            . '<input type="hidden" name="_teeny_form" value="unlock">'
            . '<label class="tc-field"><span>Password</span><input type="password" name="password" required autofocus autocomplete="current-password" maxlength="100"></label>'
            . '<p class="tc-form-actions"><button class="tc-btn tc-btn--solid" type="submit">Unlock</button></p></form>'
            . '</div></div></section>';
        $copy = $page;
        $copy['description'] = '';
        return self::document($site, $copy, $main, ['noindex' => true]);
    }

    /** "Coming soon" page shown to visitors while maintenance mode is on. */
    public static function maintenance(array $site): string
    {
        $m = $site['maintenance'] ?? [];
        $main = '<section class="tc-block tc-hero tc-hero--none"><div class="tc-hero__inner"><div class="tc-hero__content">'
            . '<p class="tc-eyebrow">' . e($site['title'] ?? '') . '</p>'
            . '<h1 class="tc-hero__title">' . e(($m['title'] ?? '') ?: 'Coming soon') . '</h1>'
            . '<p class="tc-hero__text">' . e(($m['message'] ?? '') ?: 'We are putting the finishing touches on something new. Please check back shortly.') . '</p>'
            . '</div></div></section>';
        $s = $site;
        $s['menu'] = [];
        return self::document($s, ['id' => '', 'title' => '', 'status' => 'published', 'hideHeader' => true, 'layout' => 'center'], $main, ['title' => ($m['title'] ?? '') ?: (($site['title'] ?? '') . ' · Coming soon'), 'noindex' => true]);
    }

    public static function notFound(array $site): string
    {
        $main = '<section class="tc-block tc-hero tc-hero--none"><div class="tc-hero__inner"><div class="tc-hero__content">'
            . '<p class="tc-eyebrow">404</p><h1 class="tc-hero__title">Page not found</h1>'
            . '<p class="tc-hero__text">The page you are looking for has moved or no longer exists.</p>'
            . '<p class="tc-actions"><a class="tc-btn tc-btn--solid" href="' . e(url('')) . '">Back to home</a></p></div></div></section>';
        return self::document($site, null, $main, ['title' => 'Page not found · ' . ($site['title'] ?? ''), 'noindex' => true]);
    }
}
