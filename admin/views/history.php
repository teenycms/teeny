<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$all = Auth::users();
admin_header('History: ' . $page['title'], 'pages', ['actions' => '<a class="btn btn-primary" href="' . e(admin_url('edit', ['id' => $page['id']])) . '">' . icon('edit') . ' Open editor</a>']);
?>
<div class="card card-flush">
  <p class="card-intro muted">Teeny keeps the last <?= Pages::MAX_REVISIONS ?> versions of every page. Restoring a version keeps the current one in the history too, so nothing is lost.</p>
  <?php if (!$revisions): ?>
    <p class="empty">No earlier versions yet. They appear here after you save changes.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Saved</th><th>Title</th><th class="hide-sm">Sections</th><th class="hide-sm">By</th><th class="t-right"></th></tr></thead>
    <tbody>
    <?php foreach ($revisions as $r): ?>
      <tr>
        <td><?= e(fmt_date($r['updated'])) ?></td>
        <td><?= e($r['title']) ?></td>
        <td class="hide-sm"><?= (int)$r['blocks'] ?></td>
        <td class="hide-sm"><?= e($all[$r['author']]['name'] ?? 'Unknown') ?></td>
        <td class="t-right">
          <form method="post" data-confirm="Restore this version? Your current version will be kept in the history.">
            <?= Auth::csrfField() ?><input type="hidden" name="rev" value="<?= e($r['rev']) ?>">
            <button class="btn btn-ghost btn-sm"><?= icon('history') ?> Restore</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_footer();
