<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
require __DIR__ . '/_auth.php';
if ($done) {
    auth_open('Password changed', 'Password changed', 'Your new password is ready. For your safety you’ve been signed out on every device.');
    echo '<a class="signin-btn signin-btn--link" href="' . e(admin_url('login')) . '">Sign in</a>';
    auth_close();
    return;
}
if (!$resetUser) {
    auth_open('Link expired', 'This link has expired', 'Reset links work once and only for 1 hour. You can ask for a new one.');
    echo '<a class="signin-btn signin-btn--link" href="' . e(admin_url('forgot')) . '">Send a new link</a>';
    auth_close('<a href="' . e(admin_url('login')) . '">← Back to sign in</a>');
    return;
}
auth_open('Choose a new password', 'Choose a new password', 'For @' . $resetUser['username'] . '. Use at least 10 characters.');
?>
      <?php if (!empty($error)): ?><div class="signin-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(admin_url('reset')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="text" name="username" value="<?= e($resetUser['username']) ?>" autocomplete="username" hidden>
        <label class="signin-field"><span>New password</span><input type="password" name="password" required minlength="10" maxlength="200" autofocus autocomplete="new-password"></label>
        <label class="signin-field"><span>Repeat new password</span><input type="password" name="password2" required minlength="10" maxlength="200" autocomplete="new-password"></label>
        <button class="signin-btn" type="submit">Save new password</button>
      </form>
<?php auth_close('<a href="' . e(admin_url('login')) . '">← Back to sign in</a>');
