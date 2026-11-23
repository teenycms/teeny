<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
require __DIR__ . '/_auth.php';
auth_open('Verification', 'Two-step verification', 'Open your authenticator app and enter the 6-digit code.');
?>
      <?php if (!empty($error)): ?><div class="signin-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(admin_url('login2fa')) ?>" autocomplete="off">
        <?= Auth::csrfField() ?>
        <label class="signin-field"><span>Code</span><input name="code" class="signin-otp" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="20" placeholder="123 456"></label>
        <button class="signin-btn" type="submit">Verify</button>
      </form>
      <p class="signin-note">Lost your phone? Enter one of your recovery codes instead (it looks like <code>abcd-efgh</code>).</p>
<?php auth_close('<a href="' . e(admin_url('login')) . '">← Start over</a>');
