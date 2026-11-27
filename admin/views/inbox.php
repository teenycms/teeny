<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$unread = count(array_filter($messages, static fn($m) => empty($m['read'])));
$actions = $unread ? '<form method="post">' . Auth::csrfField() . '<input type="hidden" name="action" value="readall"><button class="btn btn-ghost">' . icon('check') . ' Mark all as read</button></form>' : '';
$view = in_array($_GET['view'] ?? '', ['subscribers', 'rsvps'], true) ? $_GET['view'] : 'messages';
$subs = Inbox::subscribers();
$rsvps = Inbox::rsvps();
if ($view === 'rsvps') {
    $actions = $rsvps ? '<form method="post">' . Auth::csrfField() . '<input type="hidden" name="action" value="rsvp_export"><button class="btn btn-ghost">' . icon('backup') . ' Download CSV</button></form>' : '';
}
if ($view === 'subscribers') {
    $actions = $subs ? '<form method="post">' . Auth::csrfField() . '<input type="hidden" name="action" value="sub_export"><button class="btn btn-ghost">' . icon('backup') . ' Download CSV</button></form>' : '';
}
admin_header('Messages', 'messages', ['actions' => $actions]);
?>
<nav class="subtabs" aria-label="Messages views">
  <a href="<?= e(admin_url('messages')) ?>"<?= $view === 'messages' ? ' aria-current="page"' : '' ?>>Messages <span><?= count($messages) ?></span></a>
  <a href="<?= e(admin_url('messages', ['view' => 'subscribers'])) ?>"<?= $view === 'subscribers' ? ' aria-current="page"' : '' ?>>Subscribers <span><?= count($subs) ?></span></a>
  <?php if ($rsvps || $view === 'rsvps'): ?><a href="<?= e(admin_url('messages', ['view' => 'rsvps'])) ?>"<?= $view === 'rsvps' ? ' aria-current="page"' : '' ?>>RSVPs <span><?= count($rsvps) ?></span></a><?php endif; ?>
</nav>
<?php if ($view === 'rsvps'): ?>
  <?php if (!$rsvps): ?>
    <div class="card empty-state"><?= icon('inbox') ?><h3 class="card-title">No replies yet</h3><p class="muted">Add an <strong>RSVP</strong> section to a page (or start from the <strong>Event</strong> layout). Guests' replies appear here with a running total, and you can download them as a spreadsheet (CSV).</p></div>
  <?php else: ?>
  <div class="rsvp-totals">
    <?php foreach (Inbox::rsvpSummary() as $sum): ?>
      <div class="card rsvp-total">
        <p class="muted small"><?= e($sum['event']) ?><?= $sum['page'] !== '' && $sum['page'] !== $sum['event'] ? ' · ' . e($sum['page']) : '' ?></p>
        <p class="rsvp-total__num"><strong><?= (int)$sum['guests'] ?></strong> coming</p>
        <p class="muted small"><?= (int)$sum['coming'] ?> <?= $sum['coming'] === 1 ? 'reply' : 'replies' ?> said yes · <?= (int)$sum['notComing'] ?> can't make it</p>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card card-flush">
    <table class="table">
      <thead><tr><th>Name</th><th>Coming</th><th class="hide-sm">Note</th><th class="hide-sm">Replied</th><th class="t-right"></th></tr></thead>
      <tbody>
      <?php foreach (array_slice($rsvps, 0, 500) as $r): ?>
        <tr>
          <td><strong><?= e($r['name']) ?></strong><br><small><a href="mailto:<?= e(rawurlencode((string)$r['email'])) ?>"><?= e($r['email']) ?></a></small></td>
          <td><?= !empty($r['coming']) ? '<span class="badge badge-published">Yes' . ((int)$r['guests'] > 1 ? ' · ' . (int)$r['guests'] : '') . '</span>' : '<span class="badge">No</span>' ?></td>
          <td class="hide-sm"><small><?= e(str_limit((string)($r['note'] ?? ''), 120)) ?></small></td>
          <td class="hide-sm"><small><?= e(fmt_date((int)$r['time'])) ?></small><br><small class="muted"><?= e($r['event'] !== '' ? $r['event'] : $r['page']) ?></small></td>
          <td class="t-right"><form method="post" data-confirm="Delete the reply from <?= e($r['name']) ?>?"><?= Auth::csrfField() ?><input type="hidden" name="action" value="rsvp_delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="icon-btn danger" title="Delete"><?= icon('trash') ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (count($rsvps) > 500): ?><p class="card-intro muted">Showing the newest 500 of <?= count($rsvps) ?>. Download the CSV for the full list.</p><?php endif; ?>
  </div>
  <?php endif; ?>
<?php admin_footer(); return; endif; ?>
<?php if ($view === 'subscribers'): ?>
  <?php if (!$subs): ?>
    <div class="card empty-state"><?= icon('inbox') ?><h3 class="card-title">No subscribers yet</h3><p class="muted">Add a <strong>Newsletter</strong> section to a page. Email addresses people leave appear here, and you can download them as a spreadsheet (CSV) for your email tool.</p></div>
  <?php else: ?>
  <div class="card card-flush">
    <table class="table">
      <thead><tr><th>Email</th><th class="hide-sm">Signed up</th><th class="hide-sm">Page</th><th class="t-right"></th></tr></thead>
      <tbody>
      <?php foreach (array_slice($subs, 0, 500) as $s): ?>
        <tr>
          <td><a href="mailto:<?= e(rawurlencode((string)$s['email'])) ?>"><?= e($s['email']) ?></a></td>
          <td class="hide-sm"><small><?= e(fmt_date((int)$s['time'])) ?></small></td>
          <td class="hide-sm"><small class="muted"><?= e($s['page'] ?? '') ?></small></td>
          <td class="t-right"><form method="post" data-confirm="Remove <?= e($s['email']) ?> from your subscribers?"><?= Auth::csrfField() ?><input type="hidden" name="action" value="sub_delete"><input type="hidden" name="email" value="<?= e($s['email']) ?>"><button class="icon-btn danger" title="Remove"><?= icon('trash') ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (count($subs) > 500): ?><p class="card-intro muted">Showing the newest 500 of <?= count($subs) ?>. Download the CSV for the full list.</p><?php endif; ?>
  </div>
  <?php endif; ?>
<?php admin_footer(); return; endif; ?>
<?php if (!$messages): ?>
  <div class="card empty-state">
    <?= icon('inbox') ?>
    <h3 class="card-title">No messages yet</h3>
    <p class="muted">Add a <strong>Contact form</strong> section to any page. Messages from visitors appear here<?= empty($site['notifyEmail']) ? '' : ' and are emailed to ' . e($site['notifyEmail']) ?>.</p>
    <?php if (empty($site['notifyEmail']) && Auth::can('settings')): ?><p><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('preferences')) ?>#notifications">Get an email for new messages</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <p class="muted inbox-count"><?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?><?= $unread ? ' · <strong>' . $unread . ' unread</strong>' : '' ?></p>
  <div class="inbox">
    <?php foreach ($messages as $m): $isNew = empty($m['read']); ?>
      <details class="msg<?= $isNew ? ' msg-new' : '' ?>">
        <summary>
          <span class="msg-dot" aria-hidden="true"></span>
          <span class="msg-from"><strong><?= e($m['name']) ?></strong><small><?= e($m['email']) ?></small></span>
          <span class="msg-preview"><?= e(str_limit(preg_replace('/\s+/', ' ', $m['message']) ?? '', 110)) ?></span>
          <time class="msg-time"><?= e(fmt_date((int)$m['time'])) ?></time>
        </summary>
        <div class="msg-body">
          <dl class="msg-meta">
            <div><dt>From</dt><dd><?= e($m['name']) ?> &lt;<?= e($m['email']) ?>&gt;</dd></div>
            <?php if (($m['phone'] ?? '') !== ''): ?><div><dt>Phone</dt><dd><?= e($m['phone']) ?></dd></div><?php endif; ?>
            <div><dt>Page</dt><dd><?= e($m['page']) ?></dd></div>
            <div><dt>Received</dt><dd><?= e(fmt_date((int)$m['time'])) ?></dd></div>
          </dl>
          <div class="msg-text"><?= nl2br(e($m['message'])) ?></div>
          <div class="msg-actions">
            <a class="btn btn-primary btn-sm" href="mailto:<?= e(rawurlencode($m['email'])) ?>?subject=<?= e(rawurlencode('Re: your message to ' . ($site['title'] ?? ''))) ?>"><?= icon('reply') ?> Reply by email</a>
            <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>"><input type="hidden" name="action" value="<?= $isNew ? 'read' : 'unread' ?>"><button class="btn btn-ghost btn-sm"><?= $isNew ? 'Mark as read' : 'Mark as unread' ?></button></form>
            <form method="post" data-confirm="Delete this message permanently?"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-ghost btn-sm danger"><?= icon('trash') ?> Delete</button></form>
          </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php admin_footer();
