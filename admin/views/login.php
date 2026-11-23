<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
require __DIR__ . '/_auth.php';
auth_open('Sign in', 'Sign in', 'Welcome back. Sign in to edit your website.');
?>
      <?php if (!empty($error)): ?><div class="signin-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(admin_url('login')) ?>">
        <?= Auth::csrfField() ?>
        <label class="signin-field"><span>Username</span><input name="username" required autofocus autocomplete="username" maxlength="64" value="<?= e($username ?? '') ?>"></label>
        <label class="signin-field">
          <span>Password <a href="<?= e(admin_url('forgot')) ?>">Forgot password?</a></span>
          <input type="password" name="password" required autocomplete="current-password" maxlength="200">
        </label>
        <button class="signin-btn" type="submit">Sign in</button>
      </form>
<?php auth_close('<a href="' . e(url('')) . '">← Back to the website</a>');
