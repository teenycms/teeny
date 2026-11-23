<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$index = Pages::index();
$homeId = (string)($site['home'] ?? '');
$all = Auth::users();
admin_header('Pages', 'pages', ['actions' => '<a class="btn btn-primary" href="' . e(admin_url('new')) . '">' . icon('plus') . ' New page</a>']);
?>
<div class="card card-flush">
  <?php if (!$index): ?>
    <p class="empty">No pages yet. <a href="<?= e(admin_url('new')) ?>">Create your first page</a>.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Title</th><th>Status</th><th class="hide-sm">In navigation</th><th class="hide-sm">Last edited</th><th class="t-right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($index as $id => $m): $id = (string)$id; ?>
      <tr>
        <td>
          <a class="t-title" href="<?= e(admin_url('edit', ['id' => $id])) ?>"><?= e($m['title']) ?></a>
          <?php if ($id === $homeId): ?><span class="badge badge-home"><?= icon('star') ?> Home</span><?php endif; ?>
          <?php if (!empty($m['protected'])): ?><span class="badge" title="Password-protected"><?= icon('lock') ?> Private</span><?php endif; ?>
          <div class="t-sub">/<?= e($id === $homeId ? '' : $m['slug']) ?></div>
        </td>
        <td><span class="badge badge-<?= e($m['status']) ?>"><?= e(status_label($m['status'])) ?></span></td>
        <td class="hide-sm"><?= !empty($m['nav']) ? 'Yes' : '<span class="muted">No</span>' ?></td>
        <td class="hide-sm"><small><?= e(fmt_date((int)($m['updated'] ?? 0))) ?><br><span class="muted"><?= e($all[$m['author'] ?? '']['name'] ?? '') ?></span></small></td>
        <td class="t-right">
          <div class="row-actions">
            <a class="icon-btn" title="Edit" href="<?= e(admin_url('edit', ['id' => $id])) ?>"><?= icon('edit') ?></a>
            <a class="icon-btn" title="View" target="_blank" rel="noopener" href="<?= e(page_url(['id' => $id] + $m)) ?>"><?= icon('eye') ?></a>
            <a class="icon-btn" title="History" href="<?= e(admin_url('history', ['id' => $id])) ?>"><?= icon('history') ?></a>
            <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="icon-btn" title="Duplicate"><?= icon('copy') ?></button></form>
            <?php if (Auth::can('settings')): ?>
              <?php if ($id !== $homeId): ?>
              <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="home"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="icon-btn" title="Make home page"><?= icon('star') ?></button></form>
              <form method="post" data-confirm="Delete “<?= e($m['title']) ?>”? This cannot be undone."><?= Auth::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="icon-btn danger" title="Delete"><?= icon('trash') ?></button></form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_footer();
