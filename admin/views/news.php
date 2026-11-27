<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$posts = News::posts(false);
$cats = News::categories($site);
$all = Auth::users();
admin_header('News', 'news', ['actions' => '<a class="btn btn-ghost" href="' . e(News::url()) . '" target="_blank" rel="noopener">' . icon('eye') . ' View news</a>']);
?>
<div class="news-layout">
  <div>
    <form method="post" class="card news-new">
      <?= Auth::csrfField() ?><input type="hidden" name="action" value="create">
      <div class="add-row">
        <input name="title" required maxlength="120" placeholder="Title of your new post" aria-label="Post title">
        <?php if ($cats): ?>
        <select name="category" aria-label="Category"><option value="">No category</option><?php foreach ($cats as $slug => $label): ?><option value="<?= e($slug) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
        <?php endif; ?>
        <button class="btn btn-primary"><?= icon('plus') ?> New post</button>
      </div>
    </form>

    <div class="card card-flush">
      <?php if (!$posts): ?>
        <p class="empty">No posts yet. Give your first post a title above.</p>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Post</th><th>Status</th><th class="hide-sm">Date</th><th class="t-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($posts as $id => $m): $id = (string)$id;
          $state = $m['status'] !== 'published' ? ['draft', 'Draft'] : (News::isScheduled($m) ? ['scheduled', 'Scheduled'] : ['published', 'Live']); ?>
          <tr>
            <td>
              <a class="t-title" href="<?= e(admin_url('edit', ['id' => $id])) ?>"><?= e($m['title']) ?></a>
              <?php if (!empty($m['protected'])): ?><span class="badge" title="Password-protected"><?= icon('lock') ?> Private</span><?php endif; ?>
              <div class="t-sub"><?= ($m['category'] ?? '') !== '' && isset($cats[$m['category']]) ? e($cats[$m['category']]) . ' · ' : '' ?>/news/<?= e($m['slug']) ?></div>
            </td>
            <td><span class="badge badge-<?= e($state[0]) ?>"><?= e($state[1]) ?></span></td>
            <td class="hide-sm"><small><?= e(News::niceDate((string)($m['date'] ?? ''))) ?><br><span class="muted"><?= e($all[$m['author'] ?? '']['name'] ?? '') ?></span></small></td>
            <td class="t-right">
              <div class="row-actions">
                <a class="icon-btn" title="Edit" href="<?= e(admin_url('edit', ['id' => $id])) ?>"><?= icon('edit') ?></a>
                <a class="icon-btn" title="View" target="_blank" rel="noopener" href="<?= e(page_url(['id' => $id] + $m)) ?>"><?= icon('eye') ?></a>
                <a class="icon-btn" title="History" href="<?= e(admin_url('history', ['id' => $id])) ?>"><?= icon('history') ?></a>
                <?php if (Auth::can('settings')): ?>
                <form method="post" data-confirm="Delete the post “<?= e($m['title']) ?>”? This cannot be undone."><?= Auth::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="icon-btn danger" title="Delete"><?= icon('trash') ?></button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <section class="card">
      <h3 class="card-title">Categories</h3>
      <p class="muted small">Group your posts, for example Events or Offers. Visitors can filter by category on the news page.</p>
      <?php if ($cats): ?>
      <ul class="cat-list">
        <?php foreach ($cats as $slug => $label): ?>
          <li><span><?= e($label) ?></span>
            <form method="post" data-confirm="Remove the category “<?= e($label) ?>”? Its posts stay, without a category."><?= Auth::csrfField() ?><input type="hidden" name="action" value="cat_remove"><input type="hidden" name="slug" value="<?= e($slug) ?>"><button class="icon-btn danger" title="Remove"><?= icon('trash') ?></button></form>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (count($cats) < News::MAX_CATEGORIES): ?>
      <form method="post" class="add-row"><?= Auth::csrfField() ?><input type="hidden" name="action" value="cat_add"><input name="label" required maxlength="40" placeholder="New category" aria-label="New category"><button class="btn btn-ghost btn-sm">Add</button></form>
      <?php endif; ?>
    </section>
    <section class="card">
      <h3 class="card-title">Good to know</h3>
      <ul class="muted small tips">
        <li>Posts use the same editor as pages. Set the date, cover image, summary and category in the editor's <strong>Page</strong> tab.</li>
        <li>Give a post a <strong>future date</strong> and make it live: it appears on that day by itself.</li>
        <li>Show your newest posts on any page with the <strong>Latest news</strong> section.</li>
        <li>Readers can follow your news at <a href="<?= e(News::feedUrl()) ?>" target="_blank" rel="noopener">the RSS feed</a>.</li>
      </ul>
    </section>
  </aside>
</div>
<?php admin_footer();
