<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$index = Pages::index();
$live = count(array_filter($index, static fn($m) => ($m['status'] ?? '') === 'published'));
$imageCount = count(Media::all());
$recent = $index;
uasort($recent, static fn($a, $b) => ($b['updated'] ?? 0) <=> ($a['updated'] ?? 0));
$recent = array_slice($recent, 0, 4, true);
$allUsers = Auth::users();
$themeName = Theme::active();
$theme = Theme::all()[$themeName] ?? [];
$look = Theme::settings();
$homeId = (string)($site['home'] ?? '');
$hour = (int)date('G');
$greeting = $hour < 5 ? 'Working late' : ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'));
$first = explode(' ', trim((string)$user['name']))[0] ?: $user['username'];
$maintenance = !empty($site['maintenance']['on']);
admin_header('Studio', 'studio', ['bare' => true]);
?>
<section class="studio-hero">
  <div class="studio-greet">
    <p class="eyebrow"><?= e(date('l, j F')) ?></p>
    <h1 class="display"><?= e($greeting) ?>, <?= e($first) ?>.</h1>
    <p class="muted">Your studio for <strong><?= e($site['title'] ?? '') ?></strong>. What would you like to make today?</p>
    <div class="studio-actions">
      <a class="btn btn-primary" href="<?= e(admin_url('new')) ?>"><?= icon('plus') ?> New page</a>
      <?php if ($homeId !== '' && isset($index[$homeId])): ?>
        <a class="btn btn-ghost" href="<?= e(admin_url('edit', ['id' => $homeId])) ?>"><?= icon('edit') ?> Edit home page</a>
      <?php endif; ?>
    </div>
  </div>

  <?php $lookFonts = Fonts::faceCss([$look['headingFont'], $look['bodyFont']], $site); if ($lookFonts !== ''): ?><style><?= $lookFonts ?></style><?php endif; ?>
  <a class="site-card" href="<?= e(url('')) ?>" target="_blank" rel="noopener" style="--sc-bg:<?= e($look['bg']) ?>;--sc-text:<?= e($look['text']) ?>;--sc-muted:<?= e($look['muted']) ?>;--sc-accent:<?= e($look['accent']) ?>;--sc-accent-text:<?= e($look['accentText']) ?>;--sc-border:<?= e($look['border']) ?>;--sc-heading:<?= e(Theme::fonts()[$look['headingFont']]['stack']) ?>;--sc-body:<?= e(Theme::fonts()[$look['bodyFont']]['stack']) ?>;--sc-radius:<?= (int)$look['radius'] ?>px">
    <span class="sc-chrome"><i></i><i></i><i></i><span class="sc-url"><?= e(preg_replace('~^https?://~', '', (string)($site['siteUrl'] ?? '')) ?: 'your website') ?></span></span>
    <span class="sc-body">
      <span class="sc-status <?= $maintenance ? 'is-maint' : '' ?>"><?= $maintenance ? 'Coming soon mode' : 'Live' ?></span>
      <span class="sc-title"><?= e($site['title'] ?? '') ?></span>
      <span class="sc-tag"><?= e(($site['tagline'] ?? '') ?: 'A quietly beautiful website') ?></span>
      <span class="sc-btn">Visit site ↗</span>
    </span>
    <span class="sc-foot"><?= e($theme['name'] ?? '') ?> theme · <?= e(Theme::ALIGNS[Theme::align()]['label']) ?></span>
  </a>
</section>

<?php
// Remind people who couldn't get back in if they forgot their password: no rescue code, and no working reset email.
$meFull = Auth::users()[$user['id']] ?? [];
if (!Reset::hasRescueCode($meFull)):
    $mailProblem = Reset::problem();
    $lastMail = Activity::recent(1, 'email.')[0] ?? null;
    if ($mailProblem === '' && $lastMail && $lastMail['action'] === 'email.failed') $mailProblem = 'The last email could not be sent.';
    if ($mailProblem === '' && empty($meFull['email'])) $mailProblem = 'Your account has no email address.';
    if ($mailProblem !== ''): ?>
  <div class="alert alert-info studio-notice"><strong>Make sure you can get back in if you forget your password.</strong> Reset emails can’t reach you yet. <?= e($mailProblem) ?>
    <a class="text-link" href="<?= e(admin_url('profile')) ?>#rescue">Create a rescue code →</a>
    <?php if (Auth::can('settings')): ?><a class="text-link" href="<?= e(admin_url('preferences')) ?>#email">Set up email →</a><?php endif; ?></div>
<?php endif; endif; ?>

<div class="glance">
  <a href="<?= e(admin_url('pages')) ?>"><b><?= count($index) ?></b><span>Pages</span><small><?= $live ?> live</small></a>
  <a href="<?= e(admin_url('library')) ?>"><b><?= $imageCount ?></b><span>Images</span><small>in your library</small></a>
  <?php if (Auth::can('inbox')): $unreadN = Inbox::unread(); ?>
  <a href="<?= e(admin_url('messages')) ?>" class="<?= $unreadN ? 'has-new' : '' ?>"><b><?= $unreadN ?></b><span>New messages</span><small><?= count(Inbox::all()) ?> in total</small></a>
  <?php endif; ?>
  <?php if (Auth::can('users')): ?>
  <a href="<?= e(admin_url('team')) ?>"><b><?= count($allUsers) ?></b><span>Team</span><small>owners &amp; editors</small></a>
  <?php endif; ?>
</div>

<div class="studio-grid">
  <section>
    <div class="section-head">
      <h2 class="section-title">Pick up where you left off</h2>
      <a class="text-link" href="<?= e(admin_url('pages')) ?>">All pages →</a>
    </div>
    <?php if (!$recent): ?>
      <p class="empty card">No pages yet. <a href="<?= e(admin_url('new')) ?>">Create your first page</a>.</p>
    <?php else: ?>
      <div class="recent-grid">
        <?php foreach ($recent as $id => $m): $id = (string)$id; ?>
          <a class="recent-card" href="<?= e(admin_url('edit', ['id' => $id])) ?>">
            <span class="rc-top">
              <span class="badge badge-<?= e($m['status']) ?>"><?= e(status_label($m['status'])) ?></span>
              <?php if ($id === $homeId): ?><span class="badge badge-home"><?= icon('star') ?> Home</span><?php endif; ?>
              <?php if (!empty($m['protected'])): ?><span class="badge"><?= icon('lock') ?></span><?php endif; ?>
            </span>
            <strong><?= e($m['title']) ?></strong>
            <small class="muted">Edited <?= e(fmt_date((int)($m['updated'] ?? 0))) ?></small>
            <span class="rc-open">Open editor →</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section>
    <div class="section-head"><h2 class="section-title">Shortcuts</h2></div>
    <div class="shortcuts">
      <a href="<?= e(admin_url('library')) ?>"><?= icon('upload') ?><span><strong>Add images</strong><small>Upload to your library</small></span></a>
      <?php if (Auth::can('appearance')): ?>
      <a href="<?= e(admin_url('style')) ?>"><?= icon('brush') ?><span><strong>Change the style</strong><small>Theme, colours, alignment</small></span></a>
      <a href="<?= e(admin_url('navigation')) ?>"><?= icon('menu') ?><span><strong>Arrange navigation</strong><small>Links at the top of your site</small></span></a>
      <?php endif; ?>
      <?php if (Auth::can('plugins')): ?>
      <a href="<?= e(admin_url('plugins')) ?>"><?= icon('plug') ?><span><strong>Add features</strong><small>Plugins for new kinds of sections</small></span></a>
      <?php endif; ?>
      <a href="<?= e(admin_url('profile')) ?>#two-factor"><?= icon('shield') ?><span><strong>Protect your sign-in</strong><small>Two-factor codes from your phone</small></span></a>
    </div>
  </section>
</div>

<?php if (Stats::enabled()): $st = Stats::summary(30); $max = max(1, ...array_column($st['series'], 'views')); ?>
<section class="card stats-card">
  <div class="card-head">
    <h3 class="card-title">Visitors <small class="muted">last 30 days</small></h3>
    <div class="stat-pills">
      <span><b><?= number_format($st['totals']['todayVisitors']) ?></b> today</span>
      <span><b><?= number_format($st['totals']['visitors']) ?></b> visitors</span>
      <span><b><?= number_format($st['totals']['views']) ?></b> page views</span>
    </div>
  </div>
  <?php if ($st['totals']['views'] === 0): ?>
    <p class="empty">No visits recorded yet. They appear here once people open your site (your own visits while signed in are not counted).</p>
  <?php else: ?>
  <svg class="chart" viewBox="0 0 600 150" preserveAspectRatio="none" role="img" aria-label="Page views per day for the last 30 days">
    <?php foreach ($st['series'] as $i => $d):
      $h = $d['views'] / $max * 130; $hv = $d['visitors'] / $max * 130; $x = $i * 20 + 3; ?>
      <g><title><?= e(date('D j M', strtotime($d['day']))) ?>: <?= $d['views'] ?> views, <?= $d['visitors'] ?> visitors</title>
        <rect class="bar" x="<?= $x ?>" y="<?= round(140 - $h, 1) ?>" width="14" height="<?= round(max($h, 1), 1) ?>" rx="2"/>
        <rect class="bar-v" x="<?= $x ?>" y="<?= round(140 - $hv, 1) ?>" width="14" height="<?= round(max($hv, $d['visitors'] ? 1 : 0), 1) ?>" rx="2"/>
        <rect x="<?= $x - 3 ?>" y="0" width="20" height="150" fill="transparent"/></g>
    <?php endforeach; ?>
    <line x1="0" y1="140.5" x2="600" y2="140.5" class="axis"/>
  </svg>
  <div class="chart-legend"><span><i class="lg-v"></i>Visitors</span><span><i class="lg-p"></i>Page views</span><span class="muted"><?= e(date('j M', strtotime($st['series'][0]['day']))) ?> to today</span></div>
  <div class="grid-2 top-lists">
    <div><p class="field-label">Most visited</p><ul class="toplist"><?php foreach ($st['pages'] as $path => $n): ?><li><span><?= e($path) ?></span><b><?= number_format($n) ?></b></li><?php endforeach; ?></ul></div>
    <div><p class="field-label">Where visitors came from</p><?php if ($st['refs']): ?><ul class="toplist"><?php foreach ($st['refs'] as $host => $n): ?><li><span><?= e($host) ?></span><b><?= number_format($n) ?></b></li><?php endforeach; ?></ul><?php else: ?><p class="muted small">Visitors arriving from other websites will be listed here.</p><?php endif; ?></div>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (Auth::can('settings') && is_file(TEENY_ROOT . '/install.php')): ?>
  <div class="alert alert-info">Tip: for extra safety, delete <code>install.php</code> from your server. It is already locked, so this is optional.</div>
<?php endif; ?>
<?php admin_footer();
