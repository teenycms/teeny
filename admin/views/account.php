<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$me = Auth::users()[$user['id']] ?? $user;
$has2fa = !empty($me['totp']);
$setup = (string)($_SESSION['totp_setup'] ?? '');
$recoveryShow = $_SESSION['recovery_show'] ?? null;
unset($_SESSION['recovery_show']);
$rescueShow = (string)($_SESSION['rescue_profile'] ?? '');
unset($_SESSION['rescue_profile']);
admin_header('Profile', 'profile');
?>
<div class="account-grid">
<form method="post" class="card" autocomplete="off">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="action" value="profile">
  <h3 class="card-title">About you</h3>
  <p class="muted">Signed in as <strong>@<?= e($user['username']) ?></strong> · <?= e(Auth::ROLES[$user['role']] ?? '') ?></p>
  <label class="field"><span>Display name</span><input name="name" maxlength="80" value="<?= e($user['name']) ?>"></label>
  <div class="grid-2">
    <label class="field"><span>Username <small>(used to sign in)</small></span><input name="username" required pattern="[a-zA-Z0-9._\-]{3,32}" maxlength="32" value="<?= e($me['username']) ?>" autocomplete="username"></label>
    <label class="field"><span>Email <small>(for password resets)</small></span><input type="email" name="email" maxlength="200" value="<?= e($me['email'] ?? '') ?>" placeholder="you@example.com" autocomplete="email"></label>
  </div>
  <small class="hint">Changing your username or email needs your current password below. 3 to 32 letters, numbers, dots, dashes or underscores.</small>
  <hr>
  <label class="field"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password"></label>
  <p class="field-label">Change password <small>(optional)</small></p>
  <div class="grid-2">
    <label class="field"><span>New password</span><input type="password" name="new_password" minlength="10" autocomplete="new-password"></label>
    <label class="field"><span>Repeat new password</span><input type="password" name="new_password2" minlength="10" autocomplete="new-password"></label>
  </div>
  <small class="hint">Changing your password signs you out on every other device.</small>
  <div class="form-actions"><button class="btn btn-primary">Save</button></div>
</form>

<section class="card" id="two-factor">
  <div class="card-head">
    <h3 class="card-title">Two-factor login</h3>
    <span class="badge <?= $has2fa ? 'badge-published' : '' ?>"><?= icon('shield') ?> <?= $has2fa ? 'On' : 'Off' ?></span>
  </div>

  <?php if (is_array($recoveryShow)): ?>
    <div class="recovery">
      <p><strong>Your recovery codes.</strong> Each one works once if you lose your phone. Store them somewhere safe (print them or save them in a password manager). They will not be shown again.</p>
      <ol class="recovery-codes"><?php foreach ($recoveryShow as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?></ol>
      <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e(implode("\n", $recoveryShow)) ?>"><?= icon('copy') ?> Copy codes</button>
    </div>
  <?php endif; ?>

  <?php if ($has2fa): ?>
    <p class="muted">When you sign in you'll be asked for a 6-digit code from your authenticator app. You have <strong><?= count($me['recovery'] ?? []) ?></strong> recovery codes left.</p>
    <form method="post" class="inline-form">
      <?= Auth::csrfField() ?>
      <label class="field"><span>Confirm with your password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
      <div class="btn-row">
        <button class="btn btn-ghost btn-sm" name="action" value="totp_recovery">New recovery codes</button>
        <button class="btn btn-ghost btn-sm danger" name="action" value="totp_disable" data-confirm-click="Turn off two-factor login? Your account will only be protected by your password.">Turn off two-factor</button>
      </div>
    </form>
  <?php elseif ($setup !== ''): $uri = Totp::uri((string)($site['title'] ?? 'Teeny CMS'), (string)$user['username'], $setup); ?>
    <ol class="setup-steps">
      <li>Install an authenticator app on your phone, for example <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> or <strong>Authy</strong>.</li>
      <li>In the app, add an account and scan this code:
        <div class="qr-wrap"><div class="qr" data-qr="<?= e($uri) ?>" role="img" aria-label="QR code for your authenticator app"></div></div>
        <details class="manual-key"><summary>Can't scan? Enter this key instead</summary><code class="secret"><?= e(trim(chunk_split($setup, 4, ' '))) ?></code></details>
      </li>
      <li>Type the 6-digit code the app shows:</li>
    </ol>
    <form method="post" class="inline-form">
      <?= Auth::csrfField() ?>
      <div class="add-row">
        <input name="code" class="otp-input" required inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="123 456" autofocus>
        <button class="btn btn-primary" name="action" value="totp_confirm">Turn on</button>
      </div>
    </form>
    <form method="post"><?= Auth::csrfField() ?><button class="linkish muted small" name="action" value="totp_cancel">Cancel setup</button></form>
    <script src="<?= e(asset('admin/assets/qr.js')) ?>"></script>
  <?php else: ?>
    <p class="muted">Add a second step when you sign in: after your password, you'll enter a code from an app on your phone. Even if someone learns your password, they can't sign in without your phone.</p>
    <form method="post"><?= Auth::csrfField() ?><button class="btn btn-primary" name="action" value="totp_start"><?= icon('shield') ?> Set up two-factor login</button></form>
  <?php endif; ?>
</section>

<?php $hasRescue = Reset::hasRescueCode($me); ?>
<section class="card" id="rescue">
  <div class="card-head">
    <h3 class="card-title">Rescue code</h3>
    <span class="badge <?= $hasRescue ? 'badge-published' : '' ?>"><?= icon('lock') ?> <?= $hasRescue ? 'Saved' : 'None' ?></span>
  </div>
  <?php if ($rescueShow !== ''): ?>
    <div class="recovery">
      <p><strong>Your new rescue code.</strong> Save it somewhere safe now. It won’t be shown again.</p>
      <div class="rescue-box">
        <code class="rescue-code"><?= e($rescueShow) ?></code>
        <div class="btn-row">
          <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($rescueShow) ?>"><?= icon('copy') ?> Copy</button>
          <button type="button" class="btn btn-ghost btn-sm" data-download="<?= e("Teeny CMS rescue code\nWebsite:  " . ($site['title'] ?? '') . "\nUsername: " . $me['username'] . "\nRescue code: " . $rescueShow . "\n\nForgot your password? On the sign-in page click \"Forgot password?\", then \"Use your rescue code\".\nEach code works once.\n") ?>" data-filename="teeny-rescue-code.txt">Download</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <p class="muted">If you forget your password, a rescue code lets you choose a new one from the sign-in page (<em>Forgot password? → Use your rescue code</em>). It works even when your site can’t send email.
    <?= $hasRescue ? 'You created your current code on ' . e(date('j M Y', (int)($me['rescue']['created'] ?? 0))) . '. Making a new one stops the old one from working.' : 'You don’t have one yet.' ?></p>
  <form method="post" class="inline-form">
    <?= Auth::csrfField() ?>
    <label class="field"><span>Confirm with your password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
    <button class="btn <?= $hasRescue ? 'btn-ghost btn-sm' : 'btn-primary' ?>" name="action" value="rescue_new"><?= $hasRescue ? 'Make a new rescue code' : 'Create my rescue code' ?></button>
  </form>
</section>
</div>
<?php admin_footer();
