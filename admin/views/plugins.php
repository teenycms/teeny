<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$plugins = Plugins::all();
$zip = Packages::zipAvailable();
$typeNames = ['text' => 'Text', 'textarea' => 'Paragraph', 'rich' => 'Formatted text', 'url' => 'Link', 'image' => 'Image', 'select' => 'Choice', 'list' => 'List'];
admin_header('Plugins', 'plugins');
?>
<p class="muted lead">Plugins add new kinds of sections to the editor, like opening hours, testimonials or a countdown. They are built safely: they can't run code on your server, and any interactive part runs in a sealed sandbox.</p>

<div class="plugins-layout">
  <div>
    <?php if (!$plugins): ?>
      <div class="card empty-state"><?= icon('plug') ?><h3 class="card-title">No plugins yet</h3><p class="muted">Upload a plugin .zip to get started.</p></div>
    <?php endif; ?>
    <div class="plugin-list">
    <?php foreach ($plugins as $id => $p): $m = $p['manifest']; $ok = $m && !$p['problems']; ?>
      <article class="plugin-card<?= $p['enabled'] && $ok ? ' is-on' : '' ?>">
        <header class="plugin-head">
          <span class="plugin-icon" aria-hidden="true"><?= e(strtoupper(substr($m['name'] ?? $id, 0, 1))) ?></span>
          <div class="plugin-title">
            <h3><?= e($m['name'] ?? $id) ?> <small class="muted"><?= e($m['version'] ?? '') ?></small></h3>
            <p class="muted"><?= e($m['description'] ?? '') ?><?= ($m['author'] ?? '') !== '' ? ' · by ' . e($m['author']) : '' ?></p>
          </div>
          <span class="badge <?= $p['enabled'] && $ok ? 'badge-published' : '' ?>"><?= $p['enabled'] && $ok ? 'On' : 'Off' ?></span>
        </header>

        <?php if ($m): ?>
          <div class="plugin-facts">
            <span><?= icon('pages') ?> Adds the section <strong>“<?= e($m['section']['label']) ?>”</strong></span>
            <span><?= icon('shield') ?> <?= isset($m['widget']) ? 'Interactive. Its JavaScript runs in a sealed sandbox' : 'No JavaScript' ?></span>
            <span><?= icon('lock') ?> No server code</span>
          </div>
          <details class="plugin-fields"><summary>Fields editors will fill in (<?= count($m['section']['fields']) ?>)</summary>
            <ul><?php foreach ($m['section']['fields'] as $f): ?><li><?= e($f['label']) ?> <small class="muted"><?= e($typeNames[$f['type']] ?? $f['type']) ?></small></li><?php endforeach; ?></ul>
          </details>
        <?php endif; ?>

        <?php if ($p['problems']): ?>
          <div class="alert alert-error small"><strong>This plugin can't be switched on:</strong><br><?= e(implode(' · ', array_slice($p['problems'], 0, 5))) ?></div>
        <?php endif; ?>

        <footer class="plugin-actions">
          <?php if ($ok): ?>
          <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= e($id) ?>">
            <?php if ($p['enabled']): ?>
              <button class="btn btn-ghost btn-sm" name="action" value="disable">Switch off</button>
            <?php else: ?>
              <button class="btn btn-primary btn-sm" name="action" value="enable">Switch on</button>
            <?php endif; ?>
          </form>
          <?php endif; ?>
          <?php if ($zip && $m): ?>
          <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn-ghost btn-sm" name="action" value="download"><?= icon('backup') ?> Download</button></form>
          <?php endif; ?>
          <form method="post" data-confirm="Remove “<?= e($m['name'] ?? $id) ?>”? Its sections disappear from your pages."><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn-ghost btn-sm danger" name="action" value="remove"><?= icon('trash') ?> Remove</button></form>
        </footer>
      </article>
    <?php endforeach; ?>
    </div>
  </div>

  <aside>
    <section class="card">
      <h3 class="card-title">Add a plugin</h3>
      <?php if ($zip): ?>
        <form method="post" enctype="multipart/form-data">
          <?= Auth::csrfField() ?><input type="hidden" name="action" value="upload">
          <label class="field"><span>Plugin file (.zip)</span><input type="file" name="package" accept=".zip,application/zip" required></label>
          <button class="btn btn-primary btn-block"><?= icon('upload') ?> Upload &amp; check</button>
        </form>
        <p class="hint">Every file is checked before anything is installed. New plugins start switched off so you can review them first.</p>
      <?php else: ?>
        <p class="muted small">Your server can't open zip files. Unzip the plugin on your computer and copy its folder into <code>plugins/</code> by FTP. It will appear here, switched off.</p>
      <?php endif; ?>
    </section>
    <section class="card">
      <h3 class="card-title">Make your own</h3>
      <p class="muted small">A plugin is a folder with <code>plugin.json</code> (name and fields), <code>template.html</code> (layout with <code>{{placeholders}}</code>) and <code>style.css</code>. Add <code>widget.js</code> for interactive parts. See “Make your own plugin” in the README. The three included plugins are good starting points.</p>
    </section>
    <?php $log = Activity::recent(8, 'plugin.'); if ($log): ?>
    <section class="card">
      <h3 class="card-title">Recent changes</h3>
      <ul class="activity">
        <?php foreach ($log as $a): ?>
          <li><span><?= e(['plugin.install' => 'Installed', 'plugin.enable' => 'Switched on', 'plugin.disable' => 'Switched off', 'plugin.remove' => 'Removed', 'plugin.update' => 'Updated'][$a['action']] ?? $a['action']) ?> <strong><?= e($a['detail']) ?></strong></span><small class="muted"><?= e($a['user']) ?> · <?= e(fmt_date((int)$a['time'])) ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php admin_footer();
