<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
/** Shown once after installing: save your password and rescue code. */
$site = site();
$siteUrl = (string)($site['siteUrl'] ?? '');
$recoverUrl = $siteUrl !== '' ? $siteUrl . '/admin/?p=recover' : admin_url('recover');
$fileText = "Teeny CMS rescue code\n"
    . "Website:  " . ($site['title'] ?? '') . ($siteUrl !== '' ? " ($siteUrl)" : '') . "\n"
    . "Username: " . $user['username'] . "\n"
    . "Rescue code: " . $rescueCode . "\n\n"
    . "Forgot your password? Open " . $recoverUrl . "\n"
    . "(or click \"Forgot password?\" on the sign-in page, then \"Use your rescue code\"),\n"
    . "enter your username and this code, and choose a new password.\n"
    . "Each code works once. You'll get a new one after using it.\n";
admin_header('Welcome', '', ['bare' => true]);
?>
<div class="welcome">
  <p class="eyebrow">Installed</p>
  <h1 class="display">Your site is ready.</h1>
  <p class="muted">Before you start, take a minute to save your sign-in details. You only see this page once.</p>

  <ol class="welcome-steps">
    <li>
      <h3>Save your password</h3>
      <p>Your username is <strong>@<?= e($user['username']) ?></strong>. If your browser offered to save the password, accept it. You can also keep it in a password manager or somewhere safe offline.</p>
    </li>
    <li>
      <h3>Save your rescue code</h3>
      <p>If you ever forget your password, this code lets you choose a new one from the sign-in page (<em>Forgot password? → Use your rescue code</em>). It works even if your site can’t send email.</p>
      <div class="rescue-box">
        <code class="rescue-code"><?= e($rescueCode) ?></code>
        <div class="btn-row">
          <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($rescueCode) ?>"><?= icon('copy') ?> Copy</button>
          <button type="button" class="btn btn-ghost btn-sm" data-download="<?= e($fileText) ?>" data-filename="teeny-rescue-code.txt">Download as a text file</button>
        </div>
      </div>
      <p class="hint">Keep it private, like a password. You can make a new one any time in Profile &amp; security.</p>
    </li>
    <li>
      <h3>Set up email <span class="muted">(recommended)</span></h3>
      <p>With email set up, <em>Forgot password?</em> can also send you a reset link, and you get alerts for new messages. You can do this later in <a class="text-link" href="<?= e(admin_url('preferences')) ?>#email">Preferences → Email</a>.</p>
    </li>
  </ol>

  <form method="post" class="welcome-done">
    <?= Auth::csrfField() ?>
    <label class="check"><input type="checkbox" required> I’ve saved my password and my rescue code</label>
    <button class="btn btn-primary btn-lg" type="submit">Go to my Studio</button>
  </form>
</div>
<?php admin_footer();
