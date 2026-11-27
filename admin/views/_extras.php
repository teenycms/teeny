<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php

/** "Updates ready" notice at the top of the Style and Plugins pages. */
function extras_updates_notice(string $kind): void
{
    $ups = Extras::updates($kind);
    if (!$ups) return;
    $what = $kind === 'theme' ? 'theme' : 'plugin';
    $list = implode(', ', array_map(static fn($u) => $u['item']['name'] . ' ' . $u['item']['version'], $ups));
    ?>
    <div class="notice-update card">
      <span class="notice-update__icon" aria-hidden="true"><?= icon('upload') ?></span>
      <p><strong><?= count($ups) === 1 ? 'An update is ready for 1 ' . $what : 'Updates are ready for ' . count($ups) . ' ' . $what . 's' ?>:</strong> <?= e($list) ?>.<br><span class="muted small"><?= $kind === 'theme' ? 'Your colours and fonts for each theme are kept.' : 'Plugins that are on stay on, and your sections keep their content.' ?></span></p>
      <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="extras_update_all"><button class="btn btn-primary btn-sm"><?= count($ups) === 1 ? 'Update' : 'Update all' ?></button></form>
    </div>
    <?php
}

/** "Get more themes / plugins": official extras from the Teeny website, installed in one click. */
function extras_card(string $kind): void
{
    $cat = Extras::catalog(Extras::checksEnabled());
    $items = $cat[$kind === 'theme' ? 'themes' : 'plugins'] ?? [];
    $what = $kind === 'theme' ? 'themes' : 'plugins';
    $release = Extras::PAGE;
    ?>
    <section class="card extras" id="get-more-<?= e($what) ?>">
      <div class="card-head">
        <h3 class="card-title">Get more <?= e($what) ?></h3>
        <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="extras_refresh"><button class="btn btn-ghost btn-sm" title="Look for new <?= e($what) ?>">Check again</button></form>
      </div>
      <p class="muted small">Official <?= e($what) ?> made for Teeny. They download from teenycms.github.io and are checked file by file, exactly like an upload. You can also <a href="<?= e($release) ?>" target="_blank" rel="noopener">download them here</a> and upload the .zip yourself.</p>
      <?php if (!$items): ?>
        <p class="muted small"><?= $cat === null ? 'The list couldn’t be loaded yet. Click “Check again”, or download them from teenycms.github.io/extras instead.' : 'No official ' . e($what) . ' are listed right now.' ?></p>
      <?php else: ?>
      <ul class="extras-grid">
        <?php $ups = Extras::updates($kind); foreach ($items as $it): $done = Extras::installed($it); $up = $ups[$it['id']] ?? null; ?>
          <li class="extra">
            <?php if ($kind === 'theme'): $c = $it['colors']; ?>
              <span class="extra-swatch" aria-hidden="true" style="background:<?= e($c['bg']) ?>;color:<?= e($c['text']) ?>"><span class="extra-swatch__title">Aa</span><span class="extra-swatch__btn" style="background:<?= e($c['accent']) ?>"></span><span class="extra-swatch__line" style="background:<?= e($c['surface']) ?>"></span></span>
            <?php else: ?>
              <span class="plugin-icon" aria-hidden="true"><?= e(strtoupper(substr($it['name'], 0, 1))) ?></span>
            <?php endif; ?>
            <span class="extra-text"><strong><?= e($it['name']) ?><?php if ($it['version'] !== ''): ?> <span class="ver"><?= e($it['version']) ?></span><?php endif; ?></strong><?= $it['tag'] !== '' ? ' <small class="muted">' . e($it['tag']) . '</small>' : '' ?><small class="muted"><?= e($it['description']) ?></small></span>
            <?php if ($up): ?>
              <form method="post" class="extra-update"><?= Auth::csrfField() ?><input type="hidden" name="action" value="extra_update"><input type="hidden" name="id" value="<?= e($it['id']) ?>"><button class="btn btn-primary btn-sm" title="You have version <?= e($up['from']) ?>"><?= icon('upload') ?> Update to <?= e($it['version']) ?></button></form>
            <?php elseif ($done): ?>
              <span class="badge badge-published">Installed</span>
            <?php else: ?>
              <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="extra_install"><input type="hidden" name="id" value="<?= e($it['id']) ?>"><button class="btn btn-ghost btn-sm"><?= icon('upload') ?> Install</button></form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php
}
