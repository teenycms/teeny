<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
admin_header('New page', 'pages');
?>
<form method="post" action="<?= e(admin_url('pages')) ?>" class="card narrow">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="action" value="create">
  <label class="field"><span>Page title</span><input name="title" required maxlength="120" placeholder="e.g. About me" autofocus></label>
  <p class="field-label">Start from</p>
  <div class="choice-grid">
    <?php foreach (Pages::templates() as $key => $t): ?>
      <label class="choice">
        <input type="radio" name="template" value="<?= e($key) ?>" <?= $key === 'blank' ? 'checked' : '' ?>>
        <span class="choice-body"><strong><?= e($t['label']) ?></strong><small><?= e($t['desc']) ?></small></span>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="form-actions">
    <a class="btn btn-ghost" href="<?= e(admin_url('pages')) ?>">Cancel</a>
    <button class="btn btn-primary" type="submit">Create &amp; open editor</button>
  </div>
</form>
<?php admin_footer();
