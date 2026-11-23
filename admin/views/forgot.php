<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
require __DIR__ . '/_auth.php';
auth_open('Forgot password', 'Forgot your password?', $sent ? '' : 'Enter your username or email address and we’ll email you a link to choose a new password.');
?>
      <?php if ($sent): ?>
        <div class="signin-ok" role="status">
          <p><strong>Check your email.</strong></p>
          <p>If an account matches what you entered and it has an email address, we’ve sent a reset link. It works once and expires in 1 hour.</p>
        </div>
        <p class="signin-note">No email after a few minutes? Check your spam folder. You can also <a href="<?= e(admin_url('recover')) ?>">use your rescue code</a> instead, or ask an owner of this site to set a new password for you under <strong>Team</strong>.</p>
      <?php else: ?>
        <?php if (!empty($error)): ?><div class="signin-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(admin_url('forgot')) ?>">
          <?= Auth::csrfField() ?>
          <label class="signin-field"><span>Username or email</span><input name="login" required autofocus maxlength="200" autocomplete="username"></label>
          <button class="signin-btn" type="submit">Send reset link</button>
        </form>
        <p class="signin-note">Have a rescue code? <a href="<?= e(admin_url('recover')) ?>">Use your rescue code</a> to choose a new password without email.</p>
      <?php endif; ?>
<?php auth_close('<a href="' . e(admin_url('login')) . '">← Back to sign in</a>');
