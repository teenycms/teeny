<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
admin_header('Vault', 'vault');
?>
<p class="muted lead">Keep a safe copy of your website on your own computer, or bring one back.</p>
<div class="grid-2">
  <section class="card">
    <h3 class="card-title">Save a copy</h3>
    <p class="muted">One small file with all your pages, preferences and style choices. Team members and passwords are never included.</p>
    <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="export"><button class="btn btn-primary"><?= icon('backup') ?> Download site copy (.json)</button></form>
    <form method="post" class="mt"><?= Auth::csrfField() ?><input type="hidden" name="action" value="media_zip"><button class="btn btn-ghost"><?= icon('media') ?> Download library images (.zip)</button></form>
    <?php if (!class_exists('ZipArchive')): ?><small class="hint">Your server can't create zip files. Copy the <code>uploads</code> folder by FTP to keep your images.</small><?php endif; ?>
  </section>
  <section class="card">
    <h3 class="card-title">Bring back a copy</h3>
    <p class="muted">Replaces <strong>all current pages</strong> and preferences with the saved copy. Your team stays as it is.</p>
    <form method="post" enctype="multipart/form-data" data-confirm="Bring back this copy? All current pages will be replaced.">
      <?= Auth::csrfField() ?><input type="hidden" name="action" value="import">
      <label class="field"><span>Site copy file (.json)</span><input type="file" name="backup" accept="application/json,.json" required></label>
      <button class="btn btn-ghost danger">Restore from copy</button>
    </form>
  </section>
</div>
<?php admin_footer();
