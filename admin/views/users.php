<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
admin_header('Team', 'team');
?>
<div class="grid-2 users-grid">
  <section class="card card-flush">
    <table class="table">
      <thead><tr><th>Member</th><th>Role</th><th class="hide-sm">Last login</th><th class="t-right"></th></tr></thead>
      <tbody>
      <?php foreach ($users as $id => $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong><div class="t-sub">@<?= e($u['username']) ?><?= $id === $user['id'] ? ' · you' : '' ?></div></td>
          <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?></span><?php if (!empty($u['totp'])): ?> <span class="badge badge-published" title="Two-factor login is on"><?= icon('shield') ?> 2FA</span><?php endif; ?></td>
          <td class="hide-sm"><small><?= e(fmt_date((int)($u['lastLogin'] ?? 0))) ?></small></td>
          <td class="t-right">
            <details class="pop">
              <summary class="icon-btn" title="Edit"><?= icon('edit') ?></summary>
              <div class="pop-panel">
                <form method="post">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= e((string)$id) ?>">
                  <label class="field"><span>Name</span><input name="name" maxlength="80" value="<?= e($u['name']) ?>"></label>
                  <?php if ($id !== $user['id']): ?>
                  <label class="field"><span>Username</span><input name="username" required pattern="[a-zA-Z0-9._\-]{3,32}" maxlength="32" value="<?= e($u['username']) ?>" autocomplete="off"></label>
                  <?php else: ?>
                  <p class="hint">Change your own username in <a href="<?= e(admin_url('profile')) ?>">Profile &amp; security</a>.</p>
                  <?php endif; ?>
                  <label class="field"><span>Email <small>(for password resets)</small></span><input type="email" name="email" maxlength="200" value="<?= e($u['email'] ?? '') ?>" autocomplete="off"></label>
                  <label class="field"><span>Role</span>
                    <select name="role"><?php foreach (Auth::ROLES as $rk => $rl): ?><option value="<?= e($rk) ?>" <?= $u['role'] === $rk ? 'selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?></select>
                  </label>
                  <label class="field"><span>New password <small>(leave empty to keep)</small></span><input type="password" name="password" minlength="10" autocomplete="new-password"></label>
                  <button class="btn btn-primary btn-sm btn-block">Save</button>
                </form>
                <?php if (!empty($u['totp'])): ?>
                <form method="post" data-confirm="Turn off two-factor login for <?= e($u['name']) ?>? Use this if they lost their phone and recovery codes.">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="reset2fa"><input type="hidden" name="id" value="<?= e((string)$id) ?>">
                  <button class="btn btn-ghost btn-sm btn-block"><?= icon('shield') ?> Turn off two-factor</button>
                </form>
                <?php endif; ?>
                <?php if ($id !== $user['id']): ?>
                <form method="post" data-confirm="Remove <?= e($u['name']) ?> from the team? They will no longer be able to sign in.">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$id) ?>">
                  <button class="btn btn-ghost btn-sm btn-block danger"><?= icon('trash') ?> Remove from team</button>
                </form>
                <?php endif; ?>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h3 class="card-title">Add a team member</h3>
    <form method="post" autocomplete="off">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="create">
      <label class="field"><span>Name</span><input name="name" maxlength="80"></label>
      <label class="field"><span>Username</span><input name="username" required pattern="[a-zA-Z0-9._\-]{3,32}" autocomplete="off"></label>
      <label class="field"><span>Email <small>(optional, for password resets)</small></span><input type="email" name="email" maxlength="200" autocomplete="off"></label>
      <label class="field"><span>Password <small>(min. 10 characters)</small></span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label class="field"><span>Role</span>
        <select name="role"><option value="editor">Editor</option><option value="admin">Owner</option></select>
      </label>
      <button class="btn btn-primary btn-block">Add to team</button>
    </form>
    <div class="role-help">
      <p><strong>Owner:</strong> everything: pages, library, messages, navigation, style, team, preferences and the vault.</p>
      <p><strong>Editor:</strong> writes and edits pages and manages the library. Cannot delete pages or change preferences.</p>
    </div>
  </section>
</div>
<?php admin_footer();
