<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php

$pageLinks = [];
foreach (Pages::index() as $pid => $m) {
    $pageLinks[] = ['title' => $m['title'], 'url' => page_url(['id' => (string)$pid] + $m)];
}
$isHome = ($site['home'] ?? '') === $page['id'];
$isPost = ($page['kind'] ?? '') === 'post';
$newsPreview = [];
if (News::enabled($site)) {
    foreach (array_slice(News::posts(), 0, 12, true) as $nid => $nm) {
        $newsPreview[] = ['title' => $nm['title'], 'date' => News::niceDate((string)($nm['date'] ?? '')), 'summary' => (string)($nm['summary'] ?? ''), 'category' => (string)($nm['category'] ?? ''),
            'cover' => Media::exists((string)($nm['cover'] ?? '')) ? url(Media::thumb((string)$nm['cover'])) : ''];
    }
}
$editorData = [
    'page'      => Pages::forEditor($page),
    'customMenu' => Menu::custom() !== null,
    // The Search section is offered only while the Search add-on is on (Plugins)
    'schema'    => array_diff_key(Blocks::schema(), Search::enabled($site) ? [] : ['search' => 1], News::enabled($site) ? [] : ['news' => 1]),
    'newsPreview' => $newsPreview,
    'coverThumb' => $isPost && Media::exists((string)($page['cover'] ?? '')) ? url(Media::thumb((string)$page['cover'])) : '',
    'calloutIcons' => Renderer::CALLOUT_ICONS,
    'placement' => ['align' => Blocks::PLACEMENT_ALIGN, 'width' => Blocks::PLACEMENT_WIDTH, 'title' => Blocks::TITLE_SIZE, 'titled' => Blocks::TITLED],
    'isHome'    => $isHome,
    'aligns'    => array_map(static fn($a) => $a['label'], Theme::ALIGNS),
    'siteAlign' => Theme::align($site),
    'viewUrl'   => page_url($page),
    'canvasUrl' => admin_url('canvas', ['id' => $page['id']]),
    'pageLinks' => $pageLinks,
    'baseUrl'   => url(''),
    'pretty'    => !empty($site['prettyUrls']),
    'footer'    => ['text' => (string)($site['footer'] ?? ''), 'align' => (string)($site['footerAlign'] ?? ''), 'site' => (string)($site['title'] ?? ''), 'order' => ($site['footerOrder'] ?? '') === 'social' ? 'social' : 'links',
                    'links' => is_array($site['footerLinks'] ?? null) ? $site['footerLinks'] : [], 'social' => is_array($site['footerSocial'] ?? null) ? $site['footerSocial'] : []],
    'social'    => array_map(static fn($k) => ['label' => Blocks::SOCIAL[$k]['label'], 'svg' => Blocks::socialSvg($k)], array_combine(array_keys(Blocks::SOCIAL), array_keys(Blocks::SOCIAL))),
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Editing: <?= e($page['title']) ?></title>
<link rel="icon" href="<?= e(asset('assets/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('admin/assets/admin.css')) ?>">
</head>
<body class="editor-page">
<script type="application/json" id="teeny-config"><?= json_for_html(['csrf' => Auth::csrf(), 'api' => url('admin/api.php'), 'canUpload' => Auth::can('media')]) ?></script>
<script type="application/json" id="editor-data"><?= json_for_html($editorData) ?></script>

<header class="ed-top">
  <a class="icon-btn" href="<?= e(admin_url($isPost ? 'news' : 'pages')) ?>" title="<?= $isPost ? 'Back to news' : 'Back to pages' ?>" data-leave><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></a>
  <div class="ed-title">
    <strong data-title><?= e($page['title']) ?></strong>
    <small data-save-state>All changes saved</small>
  </div>
  <div class="ed-devices" role="group" aria-label="Preview size">
    <button type="button" class="active" data-device="desktop" title="Desktop"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/></svg></button>
    <button type="button" data-device="tablet" title="Tablet"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M11 18h2"/></svg></button>
    <button type="button" data-device="mobile" title="Mobile"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="7" y="3" width="10" height="18" rx="1.5"/><path d="M11 18h2"/></svg></button>
  </div>
  <div class="ed-actions">
    <button type="button" class="icon-btn" data-undo title="Undo (Ctrl+Z)" disabled><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg></button>
    <button type="button" class="icon-btn" data-redo title="Redo (Ctrl+Shift+Z)" disabled><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/></svg></button>
    <a class="btn btn-ghost btn-sm hide-sm" data-view href="<?= e(page_url($page)) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> View</a>
    <label class="status-switch" title="<?= $isHome ? 'The home page is always live' : 'Make this page live, or keep it as a draft' ?>">
      <input type="checkbox" data-publish <?= $page['status'] === 'published' ? 'checked' : '' ?> <?= $isHome ? 'disabled' : '' ?>>
      <span class="status-track"></span><span class="status-text" data-status-text><?= e(status_label($page['status'])) ?></span>
    </label>
    <button type="button" class="btn btn-primary btn-sm" data-save>Save</button>
  </div>
</header>

<div class="ed-body">
  <aside class="ed-panel">
    <nav class="ed-tabs" role="tablist">
      <button type="button" class="active" data-tab="add">Add</button>
      <button type="button" data-tab="block">Section</button>
      <button type="button" data-tab="page">Page</button>
    </nav>

    <section class="ed-pane" data-pane="add">
      <div data-palette></div>
      <p class="pane-label">On this page <small class="muted">drag to reorder</small></p>
      <ol class="outline" data-outline></ol>
    </section>

    <section class="ed-pane" data-pane="block" hidden>
      <div data-inspector><p class="empty">Click a section on the page to edit its settings.</p></div>
    </section>

    <section class="ed-pane" data-pane="page" hidden>
      <label class="field"><span><?= $isPost ? 'Post title' : 'Page title' ?></span><input data-prop="title" maxlength="120"></label>
      <label class="field"><span><?= $isPost ? 'Post address' : 'Page address' ?></span>
        <span class="slug-input"><span class="slug-prefix"><?= e(url('')) ?><?= $isPost ? (empty($site['prettyUrls']) ? '?news=' : 'news/') : (empty($site['prettyUrls']) ? '?page=' : '') ?></span><input data-prop="slug" maxlength="60" <?= $isHome ? 'disabled title="The home page lives at your main address"' : '' ?>></span>
      </label>
      <?php if ($isPost): $postCats = News::categories($site); ?>
      <label class="field"><span>Date</span><input type="date" data-prop="date"><small class="hint">A future date keeps a live post hidden until that day.</small></label>
      <?php if ($postCats): ?>
      <label class="field"><span>Category</span><select data-prop="category"><option value="">No category</option><?php foreach ($postCats as $cs => $cl): ?><option value="<?= e($cs) ?>"><?= e($cl) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <div class="field post-cover-field"><span>Cover image</span>
        <div class="post-cover-preview" data-cover-preview></div>
        <div class="btn-row"><button type="button" class="btn btn-ghost btn-sm" data-cover-pick>Choose image</button><button type="button" class="btn btn-ghost btn-sm" data-cover-clear>Remove</button></div>
        <small class="hint">Shown at the top of the post, on news cards, and when the post is shared.</small>
      </div>
      <label class="field"><span>Summary</span><textarea data-prop="summary" rows="3" maxlength="300" placeholder="One or two sentences for news cards and the RSS feed"></textarea></label>
      <?php endif; ?>
      <label class="field"><span>Description <small>(for search engines)</small></span><textarea data-prop="description" rows="3" maxlength="300"></textarea></label>
      <?php if ($isPost): ?>
      <?php elseif (Menu::custom() === null): ?>
      <label class="check"><input type="checkbox" data-prop="nav"> Show in navigation</label>
      <label class="field"><span>Navigation order</span><input type="number" data-prop="order" min="-999" max="999"></label>
      <?php else: ?>
      <p class="hint">Your navigation is arranged on the <?= Auth::can('appearance') ? '<a href="' . e(admin_url('navigation')) . '" data-leave>Navigation</a>' : 'Navigation' ?> page.</p>
      <?php endif; ?>
      <div class="field protect-field">
        <span><?= icon('lock') ?> Password protection</span>
        <p class="hint" data-protect-state></p>
        <input type="password" data-protect-input maxlength="100" placeholder="Set a new password (min. 4 characters)" autocomplete="new-password">
        <button type="button" class="btn btn-ghost btn-sm" data-protect-remove hidden>Remove password</button>
      </div>
      <label class="field"><span>Alignment for this page</span>
        <select data-prop="layout"><option value="">Site default (<?= e(Theme::ALIGNS[Theme::align($site)]['label']) ?>)</option><?php foreach (Theme::ALIGNS as $k => $a): ?><option value="<?= e($k) ?>"><?= e($a['label']) ?></option><?php endforeach; ?></select>
      </label>
      <label class="check"><input type="checkbox" data-prop="hideHeader"> Hide the site header on this page <small class="muted">(nice for link-in-bio)</small></label>
      <button type="button" class="btn btn-ghost btn-sm btn-block" data-show-footer>Footer settings</button>
      <hr>
      <a class="btn btn-ghost btn-sm btn-block" href="<?= e(admin_url('history', ['id' => $page['id']])) ?>" data-leave><?= icon('history') ?> Version history</a>
    </section>

    <section class="ed-pane" data-pane="footer" hidden>
      <p class="pane-label">Footer</p>
      <p class="hint">The footer is the same on every page. Click it at the bottom of the page to type, or use the box below.</p>
      <?php if (Auth::can('appearance')): ?>
      <label class="field"><span>Footer text</span><input data-footer-input maxlength="300" placeholder="© {year} {site} · Built with {teeny}"><small class="hint">Shortcuts: <code>{year}</code> this year, <code>{site}</code> your site name, <code>{teeny}</code> a “Teeny CMS” link. Remove anything you don’t want.</small></label>
      <div class="field"><span>Position</span>
        <div class="seg" role="radiogroup" aria-label="Footer position" data-footer-align>
          <?php foreach (Renderer::FOOTER_ALIGN as $k => $label): ?><button type="button" role="radio" data-v="<?= e($k) ?>"><?= e($label) ?></button><?php endforeach; ?>
        </div>
        <small class="hint">Auto follows the page alignment.</small>
      </div>
      <div class="field"><span>Links</span>
        <div class="footer-rows" data-footer-links></div>
        <button type="button" class="btn btn-ghost btn-sm" data-footer-add-link>+ Add link</button>
        <small class="hint">For example Privacy, Contact or Instagram. Up to 6.</small>
      </div>
      <div class="field"><span>Social icons</span>
        <div class="footer-rows" data-footer-social></div>
        <button type="button" class="btn btn-ghost btn-sm" data-footer-add-social>+ Add icon</button>
        <small class="hint">Up to 8.</small>
      </div>
      <div class="field"><span>Show first</span>
        <div class="seg" role="radiogroup" aria-label="Show first" data-footer-order>
          <button type="button" role="radio" data-v="links">Links</button><button type="button" role="radio" data-v="social">Social icons</button>
        </div>
        <small class="hint">Links and icons each get their own row under the footer text.</small>
      </div>
      <p class="hint">Leave the text, links and icons empty to hide the footer on every page.</p>
      <?php else: ?>
      <p class="hint">Only owners can change the footer text and position.</p>
      <?php endif; ?>
      <label class="check"><input type="checkbox" data-prop="hideFooter"> Hide the footer on this page</label>
    </section>
  </aside>

  <main class="ed-stage">
    <div class="ed-frame-wrap" data-frame-wrap>
      <iframe id="ed-canvas" sandbox="allow-same-origin" src="<?= e(admin_url('canvas', ['id' => $page['id']])) ?>" title="Page editor"></iframe>
    </div>
  </main>
</div>
<div class="toast" data-toast hidden></div>
<?php media_modal(); ?>
<script src="<?= e(asset('admin/assets/admin.js')) ?>"></script>
<script src="<?= e(asset('admin/assets/editor.js')) ?>"></script>
</body>
</html>
