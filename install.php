<?php
/**
 * Teeny CMS: one-time installer.
 * Locks itself once an admin account exists. You may delete this file after installing.
 */
declare(strict_types=1);

define('TEENY_DEPTH', 0);
require __DIR__ . '/core/bootstrap.php';

send_security_headers('admin');
header('Cache-Control: no-store');
Auth::start();

$installed = teeny_installed();

$checks = [
    ['PHP 8.1 or newer', PHP_VERSION_ID >= 80100, true, 'You have PHP ' . PHP_VERSION],
    ['JSON extension', function_exists('json_encode'), true, ''],
    ['DOM extension (safe text cleaning)', class_exists('DOMDocument'), true, 'Ask your host to enable php-xml / dom'],
    ['Data folder is writable', is_dir(TEENY_DATA) ? is_writable(TEENY_DATA) : is_writable(dirname(TEENY_DATA)), true, 'Set the "data" folder permission to 755 or 775'],
    ['Uploads folder is writable', is_writable(TEENY_UPLOADS), true, 'Set the "uploads" folder permission to 755 or 775'],
    ['GD image library (resizing)', Media::gdAvailable(), false, 'Optional. Images are stored without resizing.'],
    ['Themes & plugins folders are writable', is_writable(TEENY_ROOT . '/themes') && is_writable(TEENY_ROOT . '/plugins'), false, 'Optional. Needed to upload themes and plugins from the Studio.'],
    ['Zip support (theme & plugin uploads)', class_exists('ZipArchive'), false, 'Optional. Without it, add themes and plugins by FTP.'],
    ['Argon2id password hashing', defined('PASSWORD_ARGON2ID'), false, 'Optional. Bcrypt will be used instead.'],
];
$canInstall = !in_array(false, array_map(static fn($c) => $c[1] || !$c[2], $checks), true);

$errors = [];
$old = ['title' => 'My Site', 'username' => 'admin', 'name' => '', 'template' => 'landing', 'theme' => 'luxury', 'align' => 'normal', 'pretty' => '1'];

if (!$installed && $canInstall && is_post()) {
    if (!Auth::checkCsrf()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    $in = [
        'title'    => Sanitizer::text($_POST['title'] ?? '', 80),
        'username' => trim((string)($_POST['username'] ?? '')),
        'name'     => Sanitizer::text($_POST['name'] ?? '', 80),
        'email'    => strtolower(trim((string)($_POST['email'] ?? ''))),
        'template' => (string)($_POST['template'] ?? ''),
        'theme'    => (string)($_POST['theme'] ?? ''),
        'align'    => (string)($_POST['align'] ?? ''),
        'pretty'   => !empty($_POST['pretty']) ? '1' : '',
    ];
    $old = $in + $old;
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    if ($in['title'] === '') $errors[] = 'Please enter a site name.';
    if ($p = Auth::usernameProblem($in['username'])) $errors[] = $p;
    if ($p = Auth::passwordProblem($pass)) $errors[] = $p;
    if ($p = Auth::emailProblem($in['email'])) $errors[] = $p;
    if ($pass !== $pass2) $errors[] = 'The two passwords do not match.';
    if (!isset(Pages::TEMPLATES[$in['template']])) $in['template'] = 'blank';
    if (!isset(Theme::all()[$in['theme']])) $in['theme'] = 'minimal';
    if (!isset(Theme::ALIGNS[$in['align']])) $in['align'] = 'normal';

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $detectedUrl = preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/i', $host) ? (is_https() ? 'https://' : 'http://') . $host . TEENY_BASE : '';

    if (!$errors) {
        try {
            foreach (['', '/pages', '/revisions', '/logs', '/cache', '/locks'] as $sub) {
                if (!is_dir(TEENY_DATA . $sub)) @mkdir(TEENY_DATA . $sub, 0750, true);
            }
            // Make sure the data folder is protected even if it was moved by config.php.
            if (!is_file(TEENY_DATA . '/.htaccess')) {
                @file_put_contents(TEENY_DATA . '/.htaccess', "Require all denied\n");
            }
            if (!is_file(TEENY_DATA . '/index.html')) @file_put_contents(TEENY_DATA . '/index.html', '');

            // Recovery mode: content already exists (only the user file was removed): just add a new owner.
            $existingSite = Store::read('site');
            if (is_array($existingSite)) {
                $rid = random_id(6);
                Auth::saveUsers([$rid => [
                    'username' => $in['username'],
                    'name'     => $in['name'] !== '' ? $in['name'] : $in['username'],
                    'email'    => $in['email'],
                    'role'     => 'admin',
                    'hash'     => Auth::hash($pass),
                    'sv'       => 1,
                    'created'  => time(),
                ]]);
                $rescue = Reset::newRescueCode($rid);
                if (Auth::attempt($in['username'], $pass) === true) {
                    $_SESSION['rescue_show'] = $rescue;
                    flash('New owner account created. Your existing pages and settings were kept.');
                    redirect(admin_url('welcome'));
                }
                redirect(admin_url('login'));
            }

            $tpl = Pages::templateDefaults($in['template']);
            $site = [
                'title'       => $in['title'],
                'tagline'     => '',
                'description' => '',
                'lang'        => 'en',
                'timezone'    => 'UTC',
                'footer'      => '© {year} {site} · Built with {teeny}', // {site} follows the site name, {teeny} is a Teeny CMS link
                'footerLinks' => [], 'footerSocial' => [],
                'home'        => '',
                'prettyUrls'  => $in['pretty'] === '1',
                'siteUrl'     => $detectedUrl,
                'logo'        => '', 'favicon' => '', 'shareImage' => '',
                'theme'       => ['name' => $in['theme'], 'align' => $in['align'], 'overrides' => new stdClass(), 'customCss' => ''],
                'mail'        => ['method' => 'smtp'], // new sites start on SMTP; Preferences → Email asks for the details
                'installed'   => time(),
                'version'     => TEENY_VERSION,
            ];
            $site = json_decode((string)json_encode($site), true);
            Store::write('site', $site);
            site($site);

            $uid = random_id(6);
            Auth::saveUsers([$uid => [
                'username' => $in['username'],
                'name'     => $in['name'] !== '' ? $in['name'] : $in['username'],
                    'email'    => $in['email'],
                'role'     => 'admin',
                'hash'     => Auth::hash($pass),
                'sv'       => 1,
                'created'  => time(),
            ]]);

            $home = Pages::save([
                'title'      => 'Home',
                'slug'       => 'home',
                'status'     => 'published',
                'nav'        => true,
                'order'      => 0,
                'hideHeader' => !empty($tpl['hideHeader']),
                'blocks'     => Pages::fromTemplate($in['template']),
            ], $uid);
            $site['home'] = $home['id'];
            Store::write('site', $site);
            site($site);

            $rescue = Reset::newRescueCode($uid);
            if (Auth::attempt($in['username'], $pass) === true) {
                $_SESSION['rescue_show'] = $rescue; // shown once on the welcome screen
                redirect(admin_url('welcome'));
            }
            redirect(admin_url('login'));
        } catch (Throwable $ex) {
            error_log('Teeny install: ' . $ex->getMessage());
            $errors[] = 'Installation failed: ' . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Install Teeny CMS</title>
<link rel="icon" href="<?= e(asset('assets/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('admin/assets/admin.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-wrap auth-wrap--wide">
  <div class="auth-brand"><?= teeny_logo() ?><span class="wordmark-sub">CMS</span></div>

  <?php if ($installed): ?>
    <div class="card">
      <h1 class="card-title">Already installed</h1>
      <p class="muted">Teeny CMS is already set up on this website. The installer is locked.</p>
      <p><a class="btn btn-primary" href="<?= e(admin_url('login')) ?>">Sign in</a></p>
    </div>
  <?php else: ?>
    <form method="post" class="card install" autocomplete="off">
      <?= Auth::csrfField() ?>
      <h1 class="card-title">Set up your website</h1>
      <p class="muted">This takes about a minute. Everything can be changed later.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <details class="checks" <?= $canInstall ? '' : 'open' ?>>
        <summary><?= $canInstall ? 'Server check passed' : 'Server check: please fix the items below' ?></summary>
        <ul>
          <?php foreach ($checks as [$label, $ok, $required, $hint]): ?>
            <li class="<?= $ok ? 'ok' : ($required ? 'bad' : 'warn') ?>">
              <span><?= $ok ? '✓' : ($required ? '✕' : '!') ?></span> <?= e($label) ?>
              <?php if (!$ok && $hint): ?><small><?= e($hint) ?></small><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>

      <?php if ($canInstall): ?>
      <fieldset>
        <legend>1. Your site</legend>
        <label class="field"><span>Site name</span><input name="title" required maxlength="80" value="<?= e($old['title']) ?>"></label>
      </fieldset>

      <fieldset>
        <legend>2. Your owner account</legend>
        <div class="grid-2">
          <label class="field"><span>Username</span><input name="username" required pattern="[a-zA-Z0-9._\-]{3,32}" value="<?= e($old['username']) ?>" autocomplete="username"></label>
          <label class="field"><span>Your name</span><input name="name" maxlength="80" value="<?= e($old['name']) ?>"></label>
          <label class="field"><span>Email <small>(optional, lets you reset a forgotten password)</small></span><input type="email" name="email" maxlength="200" value="<?= e($old['email'] ?? '') ?>" autocomplete="email"></label>
          <label class="field"><span>Password <small>(min. 10 characters)</small></span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
          <label class="field"><span>Repeat password</span><input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
        </div>
        <p class="install-note"><strong>Save your username and password now</strong>, for example in your browser’s password manager when it offers. After installing you’ll also get a <strong>rescue code</strong> that lets you set a new password if you ever forget it, even without email.</p>
      </fieldset>

      <fieldset>
        <legend>3. What are you building?</legend>
        <div class="choice-grid">
          <?php foreach (Pages::TEMPLATES as $key => $t): ?>
            <label class="choice">
              <input type="radio" name="template" value="<?= e($key) ?>" <?= $old['template'] === $key ? 'checked' : '' ?>>
              <span class="choice-body"><strong><?= e($t['label']) ?></strong><small><?= e($t['desc']) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>4. Pick a look</legend>
        <div class="theme-grid">
          <?php foreach (Theme::all() as $key => $t): $d = $t['defaults']; ?>
            <label class="theme-card">
              <input type="radio" name="theme" value="<?= e($key) ?>" <?= $old['theme'] === $key ? 'checked' : '' ?>>
              <span class="theme-swatch" style="background:<?= e($d['bg']) ?>;color:<?= e($d['text']) ?>;font-family:<?= e(Theme::FONTS[$d['headingFont']]['stack']) ?>">
                <span class="sw-title">Aa</span>
                <span class="sw-line" style="background:<?= e($d['accent']) ?>"></span>
              </span>
              <span class="theme-name"><?= e($t['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="field-label">Page alignment</p>
        <div class="align-grid">
          <?php foreach (Theme::ALIGNS as $key => $a): ?>
            <label class="align-card">
              <input type="radio" name="align" value="<?= e($key) ?>" <?= $old['align'] === $key ? 'checked' : '' ?>>
              <span class="align-pic align-pic--<?= e($key) ?>"><i></i><i></i><i></i></span>
              <span class="align-name"><?= e($a['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <label class="check"><input type="checkbox" name="pretty" value="1" <?= $old['pretty'] ? 'checked' : '' ?>> Use clean page addresses (example.com/about). Untick this if your pages show "Not Found" after installing.</label>
      </fieldset>

      <button class="btn btn-primary btn-lg" type="submit">Install Teeny CMS</button>
      <?php endif; ?>
    </form>
  <?php endif; ?>
  <p class="auth-foot">Teeny CMS<?= $installed ? "" : " v" . e(TEENY_VERSION) ?> · Apache License 2.0</p>
</main>
</body>
</html>
