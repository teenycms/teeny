<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
require __DIR__ . '/_auth.php';
if ($recovered) {
    auth_open('Password changed', 'Password changed', 'Your new password is ready, and you’ve been signed out on every device.');
?>
      <div class="signin-ok" role="status">
        <p><strong>Your old rescue code no longer works.</strong> Here is your new one. Save it now, it won’t be shown again:</p>
        <div class="rescue-box"><code class="rescue-code"><?= e($recovered['code']) ?></code></div>
        <p>You can also make a new code any time in Profile &amp; security.</p>
      </div>
      <a class="signin-btn signin-btn--link" href="<?= e(admin_url('login')) ?>">Sign in</a>
<?php
    auth_close();
    return;
}
auth_open('Use a rescue code', 'Use a rescue code', 'Enter your username or email, the rescue code you saved when you installed (or created in Profile & security), and a new password.');
?>
      <?php if (!empty($error)): ?><div class="signin-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(admin_url('recover')) ?>" autocomplete="off">
        <?= Auth::csrfField() ?>
        <label class="signin-field"><span>Username or email</span><input name="login" required autofocus maxlength="200" autocomplete="username" value="<?= e(substr((string)($_POST['login'] ?? ''), 0, 200)) ?>"></label>
        <label class="signin-field"><span>Rescue code</span><input name="code" required maxlength="60" autocomplete="off" spellcheck="false" autocapitalize="characters" placeholder="ABCD-EFGH-JKLM-NPQR-STUV"></label>
        <label class="signin-field"><span>New password</span><input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password"></label>
        <label class="signin-field"><span>Repeat new password</span><input type="password" name="password2" required minlength="10" maxlength="200" autocomplete="new-password"></label>
        <button class="signin-btn" type="submit">Set new password</button>
      </form>
      <p class="signin-note">No rescue code? Ask an owner of this site to set a new password for you under <strong>Team</strong>.</p>
<?php auth_close('<a href="' . e(admin_url('login')) . '">← Back to sign in</a>');
