<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php

function admin_header(string $title, string $active, array $opts = []): void
{
    $u = Auth::user();
    $site = site();
    // Everyday work areas live in the top bar; site-wide tools live in the profile menu.
    $nav = [
        ['studio', 'Studio', null],
        ['pages', 'Pages', 'pages'],
        ['library', 'Library', 'media'],
        ['messages', 'Messages', 'inbox'],
        ['navigation', 'Navigation', 'appearance'],
        ['style', 'Style', 'appearance'],
    ];
    $tools = [
        ['plugins', 'Plugins', 'plug', 'plugins'],
        ['team', 'Team', 'users', 'users'],
        ['preferences', 'Preferences', 'settings', 'settings'],
        ['vault', 'Vault', 'backup', 'backup'],
    ];
    $name = (string)($u['name'] ?? '');
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = strtoupper(substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? substr((string)end($parts), 0, 1) : ''));
    $toolActive = in_array($active, ['plugins', 'team', 'preferences', 'vault', 'profile'], true);
    $flash = flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($site['title'] ?? 'Teeny') ?></title>
<link rel="icon" href="<?= e(asset('assets/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('admin/assets/admin.css')) ?>">
</head>
<body class="admin">
<script type="application/json" id="teeny-config"><?= json_for_html([
    'csrf' => Auth::csrf(),
    'api' => url('admin/api.php'),
    'canUpload' => Auth::can('media'),
]) ?></script>
<header class="appbar">
  <div class="appbar-inner">
    <a class="brand" href="<?= e(admin_url('studio')) ?>" aria-label="Teeny Studio home" title="Studio home"><?= teeny_logo(false, 'teeny-logo teeny-mark') ?></a>
    <nav class="appnav" aria-label="Studio sections">
      <?php foreach ($nav as [$key, $label, $cap]): if ($cap && !Auth::can($cap)) continue; ?>
        <a href="<?= e(admin_url($key)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($key === 'messages' && ($n = Inbox::unread()) > 0): ?><b class="nav-count"><?= $n > 99 ? '99+' : $n ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="appbar-right">
      <a class="btn btn-ghost btn-sm visit-btn" href="<?= e(url('')) ?>" target="_blank" rel="noopener">Visit site <?= icon('external') ?></a>
      <details class="pop me-menu">
        <summary class="avatar<?= $toolActive ? ' is-active' : '' ?>" aria-label="Profile and site tools" title="<?= e($name) ?>"><?= e($initials) ?></summary>
        <div class="pop-panel me-panel">
          <div class="me-head">
            <span class="avatar avatar-lg" aria-hidden="true"><?= e($initials) ?></span>
            <span><strong><?= e($name) ?></strong><small><?= e(Auth::ROLES[$u['role']] ?? '') ?> · @<?= e($u['username'] ?? '') ?></small></span>
          </div>
          <a href="<?= e(admin_url('profile')) ?>"<?= $active === 'profile' ? ' aria-current="page"' : '' ?>><?= icon('user') ?> Profile &amp; security</a>
          <?php $shown = array_filter($tools, static fn($t) => Auth::can($t[3])); if ($shown): ?>
            <p class="me-label">Site tools</p>
            <?php foreach ($shown as [$key, $label, $ic]): ?>
              <a href="<?= e(admin_url($key)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?> <?= e($label) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
          <form method="post" action="<?= e(admin_url('logout')) ?>">
            <?= Auth::csrfField() ?>
            <button type="submit"><?= icon('logout') ?> Sign out</button>
          </form>
        </div>
      </details>
    </div>
  </div>
</header>
<?php if (!empty($site['maintenance']['on'])): ?>
  <a class="maint-banner" href="<?= e(admin_url('preferences')) ?>#maintenance">Maintenance mode is on. Visitors see your “Coming soon” page. <u>Change</u></a>
<?php endif; ?>
<main class="workspace">
  <?php if (empty($opts['bare'])): ?>
  <div class="page-head">
    <div>
      <p class="eyebrow"><?= e($site['title'] ?? '') ?></p>
      <h1 class="page-title"><?= e($title) ?></h1>
    </div>
    <div class="page-actions"><?= $opts['actions'] ?? '' ?></div>
  </div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'ok' ?>" role="status"><?= e($flash['msg']) ?></div>
  <?php endif;
}

function admin_footer(): void
{
    ?>
</main>
<footer class="studio-foot">
  <span><strong>Teeny CMS</strong> <a href="<?= e(TEENY_REPO . '/releases/tag/v' . TEENY_VERSION) ?>" target="_blank" rel="noopener noreferrer" title="What's new in this version">v<?= e(TEENY_VERSION) ?></a></span>
  <span class="studio-foot__links"><a href="<?= e(TEENY_REPO . '/wiki') ?>" target="_blank" rel="noopener noreferrer">Help</a> · <a href="<?= e(TEENY_REPO) ?>" target="_blank" rel="noopener noreferrer">GitHub</a> · © <?= date('Y') ?> Teeny CMS · Released under the Apache License 2.0</span>
</footer>
<?php media_modal(); ?>
<script src="<?= e(asset('admin/assets/admin.js')) ?>"></script>
</body>
</html>
<?php
}

function media_modal(): void
{
    ?>
<div class="modal" id="media-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="media-modal-title">
    <header class="modal-head">
      <h2 id="media-modal-title">Choose from your library</h2>
      <div class="modal-actions">
        <?php if (Auth::can('media')): ?>
        <label class="btn btn-primary btn-sm"><?= icon('upload') ?> Upload<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden data-upload></label>
        <?php endif; ?>
        <button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('close') ?></button>
      </div>
    </header>
    <div class="modal-body">
      <div class="media-grid media-grid--pick" data-media-grid></div>
      <p class="empty" data-media-empty hidden>Your library is empty. Upload your first image.</p>
    </div>
    <footer class="modal-foot">
      <button class="btn btn-ghost" type="button" data-remove>Remove image</button>
    </footer>
  </div>
</div>
<?php
}

/** An image field for normal admin forms (logo, favicon…). */
function media_field(string $name, string $label, string $value, string $hint = ''): void
{
    $has = Media::exists($value);
    ?>
<div class="field media-field" data-media-field>
  <span><?= e($label) ?></span>
  <div class="media-field-row">
    <div class="media-field-preview"><?php if ($has): ?><img src="<?= e(url(Media::thumb($value))) ?>" alt=""><?php endif; ?></div>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($has ? $value : '') ?>">
    <button class="btn btn-ghost btn-sm" type="button" data-pick>Choose…</button>
  </div>
  <?php if ($hint): ?><small class="hint"><?= e($hint) ?></small><?php endif; ?>
</div>
<?php
}
