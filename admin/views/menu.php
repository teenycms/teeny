<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$index = Pages::index();
$custom = Menu::custom();
// Start the editor from the current menu (custom, or the automatic one converted to items).
if ($custom === null) {
    $items = array_map(static fn($p) => ['type' => 'page', 'id' => $p['id'], 'label' => ''], Pages::nav());
} else {
    $items = $custom;
}
$pagesForJs = [];
foreach ($index as $id => $m) {
    $pagesForJs[(string)$id] = ['title' => $m['title'], 'status' => $m['status']];
}
admin_header('Navigation', 'navigation');
?>
<script type="application/json" id="menu-data"><?= json_for_html(['items' => $items, 'pages' => $pagesForJs]) ?></script>
<div class="grid-2 menu-grid">
  <section class="card">
    <div class="card-head">
      <h3 class="card-title">Navigation links</h3>
      <span class="badge <?= $custom === null ? '' : 'badge-published' ?>"><?= $custom === null ? 'Automatic' : 'Custom' ?></span>
    </div>
    <p class="muted"><?= $custom === null
        ? 'Right now your navigation is built automatically from pages marked “Show in navigation”. Rearrange or add links below and press Save to arrange it yourself.'
        : 'Drag items to reorder them. Draft pages are hidden from visitors until published.' ?></p>
    <ol class="menu-list" data-menu-list></ol>
    <p class="empty" data-menu-empty hidden>No links yet. Visitors won’t see a menu.</p>
    <form method="post" data-menu-form>
      <?= Auth::csrfField() ?>
      <input type="hidden" name="menu" data-menu-json>
      <div class="form-actions">
        <?php if ($custom !== null): ?><button class="btn btn-ghost" type="submit" name="action" value="auto" data-confirm-click="Go back to automatic navigation? Your custom links will be removed.">Use automatic navigation</button><?php endif; ?>
        <button class="btn btn-primary" type="submit" name="action" value="save">Save navigation</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h3 class="card-title">Add a link</h3>
    <p class="field-label">A page of your site</p>
    <div class="add-row">
      <select data-add-page>
        <?php foreach ($index as $id => $m): ?><option value="<?= e((string)$id) ?>"><?= e($m['title']) ?><?= $m['status'] !== 'published' ? ' (draft)' : '' ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-ghost" type="button" data-add-page-btn><?= icon('plus') ?> Add</button>
    </div>
    <hr>
    <p class="field-label">A link to another website</p>
    <label class="field"><span>Label</span><input data-link-label maxlength="60" placeholder="e.g. Shop"></label>
    <label class="field"><span>Address</span><input data-link-url placeholder="https://…" spellcheck="false"></label>
    <label class="check"><input type="checkbox" data-link-newtab> Open in a new tab</label>
    <button class="btn btn-ghost btn-block" type="button" data-add-link-btn><?= icon('link') ?> Add link</button>
  </section>
</div>
<?php admin_footer();
