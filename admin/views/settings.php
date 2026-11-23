<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$index = Pages::index();
admin_header('Preferences', 'preferences');
?>
<form method="post" class="settings">
  <?= Auth::csrfField() ?>
  <section class="card">
    <h3 class="card-title">General</h3>
    <div class="grid-2">
      <label class="field"><span>Site name</span><input name="title" maxlength="80" required value="<?= e($site['title'] ?? '') ?>"></label>
      <label class="field"><span>Tagline</span><input name="tagline" maxlength="160" value="<?= e($site['tagline'] ?? '') ?>" placeholder="A short line about your site"></label>
    </div>
    <label class="field"><span>Home page</span>
      <select name="home">
        <?php foreach ($index as $id => $m): ?>
          <option value="<?= e((string)$id) ?>" <?= ($site['home'] ?? '') === (string)$id ? 'selected' : '' ?>><?= e($m['title']) ?><?= $m['status'] !== 'published' ? ' (draft)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="hint">The footer is edited on the page itself: open any page in the editor and click the footer at the bottom.</p>
  </section>

  <section class="card">
    <h3 class="card-title">Logo &amp; icons</h3>
    <div class="grid-3">
      <?php media_field('logo', 'Logo (shown next to the site name)', (string)($site['logo'] ?? '')); ?>
      <?php media_field('favicon', 'Browser tab icon', (string)($site['favicon'] ?? ''), 'Square image, e.g. 512×512'); ?>
      <?php media_field('shareImage', 'Social share image', (string)($site['shareImage'] ?? ''), 'Shown when your link is shared. 1200×630 works best.'); ?>
    </div>
  </section>

  <section class="card">
    <h3 class="card-title">Search engines</h3>
    <label class="field"><span>Site description</span><textarea name="description" maxlength="300" rows="2"><?= e($site['description'] ?? '') ?></textarea><small class="hint">Used when a page has no description of its own.</small></label>
    <label class="field"><span>Website address</span><input name="siteUrl" type="url" placeholder="https://example.com" value="<?= e($site['siteUrl'] ?? '') ?>"><small class="hint">Needed for password reset links, the sitemap and social share previews.</small></label>
  </section>

  <section class="card" id="maintenance">
    <h3 class="card-title">Maintenance mode</h3>
    <p class="muted">Show visitors a “Coming soon” page while you work. You stay logged in and can see and edit the real site.</p>
    <label class="check switch-row"><input type="checkbox" name="maintenance_on" value="1" <?= !empty($site['maintenance']['on']) ? 'checked' : '' ?>> <strong>Turn on maintenance mode</strong></label>
    <div class="grid-2">
      <label class="field"><span>Heading</span><input name="maintenance_title" maxlength="120" value="<?= e($site['maintenance']['title'] ?? '') ?>" placeholder="Coming soon"></label>
      <label class="field"><span>Message</span><input name="maintenance_message" maxlength="500" value="<?= e($site['maintenance']['message'] ?? '') ?>" placeholder="We are putting the finishing touches on something new."></label>
    </div>
  </section>

  <section class="card" id="notifications">
    <h3 class="card-title">Messages &amp; statistics</h3>
    <label class="field"><span>Email me new contact form messages</span><input type="email" name="notifyEmail" maxlength="200" value="<?= e($site['notifyEmail'] ?? '') ?>" placeholder="you@example.com"><small class="hint">Optional. Messages are always saved in Messages. Email delivery depends on your host's mail setup.</small></label>
    <label class="check"><input type="checkbox" name="stats" value="1" <?= ($site['stats'] ?? true) !== false ? 'checked' : '' ?>> Count visitors (privacy-friendly: no cookies, no IP addresses stored)</label>
  </section>

  <?php
    $mail = Mailer::settings();
    $me = Auth::user();
    $checks = [
        ['ok' => trim((string)($site['siteUrl'] ?? '')) !== '', 'yes' => 'Website address is set', 'no' => 'Website address is empty. Reset links need it. Fill it in under Search engines above.'],
        ['ok' => filter_var((string)($me['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false, 'yes' => 'Your account has an email address', 'no' => 'Your account has no email address. Add one in Profile & security.'],
        $mail['method'] === 'smtp'
            ? ['ok' => $mail['smtpHost'] !== '', 'yes' => 'Sending through SMTP (' . $mail['smtpHost'] . ')', 'no' => 'Email isn’t set up yet. Fill in your SMTP details below, then send a test, so password reset emails can reach you.']
            : ['ok' => function_exists('mail'), 'yes' => 'Sending with your host’s built-in mail. If emails don’t arrive, use SMTP.', 'no' => 'Your host has switched off PHP mail. Use SMTP below.'],
    ];
    $mailLog = Activity::recent(6, 'email.');
  ?>
  <section class="card" id="email">
    <h3 class="card-title">Email</h3>
    <p class="muted">Used for password reset links and new message alerts. Send a test to check that emails reach you.</p>
    <ul class="mail-checks">
      <?php foreach ($checks as $c): ?>
        <li class="<?= $c['ok'] ? 'is-ok' : 'is-bad' ?>"><span aria-hidden="true"><?= $c['ok'] ? '✓' : '!' ?></span> <?= e($c['ok'] ? $c['yes'] : $c['no']) ?></li>
      <?php endforeach; ?>
    </ul>
    <div class="grid-2">
      <label class="field"><span>From name</span><input name="mail_fromName" maxlength="80" value="<?= e($mail['fromName']) ?>" placeholder="<?= e($site['title'] ?? 'My Site') ?>"></label>
      <label class="field"><span>From address</span><input type="email" name="mail_fromEmail" maxlength="200" value="<?= e($mail['fromEmail']) ?>" placeholder="<?= e(Mailer::fromAddress()) ?>"><small class="hint">Best as a real mailbox on your own domain. With SMTP, use the mailbox you sign in with.</small></label>
    </div>
    <fieldset class="field mail-method">
      <span>How to send</span>
      <label class="check"><input type="radio" name="mail_method" value="smtp" <?= $mail['method'] === 'smtp' ? 'checked' : '' ?>> <span><strong>SMTP, through a real email account</strong> (recommended). Works with Google Workspace, Gmail, Zoho or your host’s mailbox.</span></label>
      <label class="check"><input type="radio" name="mail_method" value="php" <?= $mail['method'] === 'php' ? 'checked' : '' ?>> <span>Your host’s built-in mail. No setup, but often blocked or sent to spam.</span></label>
    </fieldset>
    <div class="mail-smtp">
      <p class="hint">Find these in your host’s email settings (often called “Mail client setup” or “Connect devices”). Typical: port 587 with TLS, or port 465 with SSL.</p>
      <div class="grid-3">
        <label class="field"><span>SMTP server</span><input name="mail_smtpHost" maxlength="253" value="<?= e($mail['smtpHost']) ?>" placeholder="mail.example.com" autocomplete="off"></label>
        <label class="field"><span>Port</span><input type="number" name="mail_smtpPort" min="1" max="65535" value="<?= (int)$mail['smtpPort'] ?>"></label>
        <label class="field"><span>Security</span>
          <select name="mail_smtpSecure">
            <?php foreach (['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (not recommended)'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $mail['smtpSecure'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="grid-2">
        <label class="field"><span>Username</span><input name="mail_smtpUser" maxlength="200" value="<?= e($mail['smtpUser']) ?>" placeholder="you@example.com" autocomplete="off"></label>
        <label class="field"><span>Password</span><input type="password" name="mail_smtpPass" maxlength="500" value="" autocomplete="new-password" placeholder="<?= $mail['smtpPass'] !== '' ? '•••••••• (saved, leave empty to keep)' : '' ?>"></label>
      </div>
      <?php if ($mail['smtpPass'] !== ''): ?><label class="check small"><input type="checkbox" name="mail_smtpPass_clear" value="1"> Remove the saved SMTP password</label><?php endif; ?>
      <label class="check small"><input type="checkbox" name="mail_smtpNoVerify" value="1" <?= $mail['smtpNoVerify'] ? 'checked' : '' ?>> Don’t check the server’s certificate (only if your host’s certificate doesn’t match its mail server name)</label>
    </div>
    <div class="mail-test">
      <button class="btn" type="submit" name="action" value="mail_test">Save &amp; send test email</button>
      <span class="hint">Goes to <?= e((string)($me['email'] ?? '') ?: 'your account email') ?>.</span>
    </div>
    <?php if ($mailLog): ?>
      <div class="mail-log">
        <h4>Recent emails</h4>
        <ul>
          <?php foreach ($mailLog as $l): ?>
            <li class="<?= $l['action'] === 'email.sent' ? 'is-ok' : 'is-bad' ?>"><time><?= e(date('j M, H:i', (int)$l['time'])) ?></time> <strong><?= e(['email.sent' => 'Sent', 'email.failed' => 'Failed', 'email.skipped' => 'Not sent'][$l['action']] ?? $l['action']) ?></strong> <?= e($l['detail']) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3 class="card-title">Advanced</h3>
    <div class="grid-2">
      <label class="field"><span>Language code</span><input name="lang" maxlength="10" value="<?= e($site['lang'] ?? 'en') ?>" placeholder="en"></label>
      <label class="field"><span>Time zone</span>
        <select name="timezone">
          <?php foreach (timezone_identifiers_list() as $tz): ?>
            <option <?= ($site['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label class="check"><input type="checkbox" name="prettyUrls" value="1" <?= !empty($site['prettyUrls']) ? 'checked' : '' ?>> Clean page addresses (example.com/about). Untick this if your pages show "Not Found".</label>
  </section>

  <div class="sticky-save"><button class="btn btn-primary" type="submit">Save preferences</button></div>
</form>
<?php admin_footer();
