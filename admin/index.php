<?php
/**
 * Teeny CMS: admin controller.
 */
declare(strict_types=1);

define('TEENY_DEPTH', 1);
require dirname(__DIR__) . '/core/bootstrap.php';

if (!teeny_installed()) {
    redirect(url('install.php'));
}

send_security_headers('admin');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
Auth::start();

require __DIR__ . '/views/_layout.php';

$p = is_string($_GET['p'] ?? null) ? $_GET['p'] : 'studio';

/* ---------- login / logout ---------- */

if ($p === 'login') {
    if (Auth::user()) redirect(admin_url('studio'));
    $error = null;
    $username = '';
    if (is_post()) {
        $username = trim((string)($_POST['username'] ?? ''));
        if (!Auth::checkCsrf()) {
            $error = 'Your session expired. Please try again.';
        } else {
            $r = Auth::attempt(substr($username, 0, 64), substr((string)($_POST['password'] ?? ''), 0, 200));
            if ($r === true) redirect(admin_url('studio'));
            if ($r === Auth::NEED_2FA) redirect(admin_url('login2fa'));
            $error = $r;
        }
    }
    require __DIR__ . '/views/login.php';
    exit;
}

if ($p === 'login2fa') {
    if (Auth::user()) redirect(admin_url('studio'));
    if (!Auth::pending2fa()) redirect(admin_url('login'));
    $error = null;
    if (is_post()) {
        if (!Auth::checkCsrf()) {
            $error = 'Your session expired. Please try again.';
        } else {
            $r = Auth::attemptSecondFactor(substr((string)($_POST['code'] ?? ''), 0, 20));
            if ($r === true) redirect(admin_url('studio'));
            $error = $r;
            if (!Auth::pending2fa()) redirect(admin_url('login'));
        }
    }
    require __DIR__ . '/views/login-2fa.php';
    exit;
}

if ($p === 'forgot') {
    if (Auth::user()) redirect(admin_url('studio'));
    $sent = false;
    $error = null;
    if (is_post()) {
        if (!Auth::checkCsrf()) {
            $error = 'Your session expired. Please try again.';
        } else {
            // Same answer, in about the same time, whether or not the account exists: the page is sent
            // first and the email afterwards where the server allows it; otherwise every reply is padded.
            $sent = true;
            $login = (string)($_POST['login'] ?? '');
            if (function_exists('fastcgi_finish_request')) {
                require __DIR__ . '/views/forgot.php';
                session_write_close();
                fastcgi_finish_request();
                Reset::request($login);
                exit;
            }
            $started = microtime(true);
            Reset::request($login);
            $left = 2.5 - (microtime(true) - $started);
            if ($left > 0) usleep((int)($left * 1000000));
        }
    }
    require __DIR__ . '/views/forgot.php';
    exit;
}

if ($p === 'recover') {
    header('Referrer-Policy: no-referrer');
    if (Auth::user()) redirect(admin_url('studio'));
    $error = null;
    $recovered = null;
    if (is_post()) {
        if (!Auth::checkCsrf()) {
            $error = 'Your session expired. Please try again.';
        } else {
            $r = Reset::useRescueCode(
                (string)($_POST['login'] ?? ''),
                substr((string)($_POST['code'] ?? ''), 0, 60),
                substr((string)($_POST['password'] ?? ''), 0, 200),
                substr((string)($_POST['password2'] ?? ''), 0, 200)
            );
            if (is_array($r)) $recovered = $r;
            else $error = $r;
        }
    }
    require __DIR__ . '/views/recover.php';
    exit;
}

if ($p === 'reset') {
    header('Referrer-Policy: no-referrer');
    if (Auth::user()) redirect(admin_url('studio'));
    $token = is_string($_REQUEST['token'] ?? null) ? $_REQUEST['token'] : '';
    $resetUser = Reset::userFor($token);
    $error = null;
    $done = false;
    if (is_post() && $resetUser) {
        if (!Auth::checkCsrf()) {
            $error = 'Your session expired. Please try again.';
        } else {
            $r = Reset::complete($token, substr((string)($_POST['password'] ?? ''), 0, 200), substr((string)($_POST['password2'] ?? ''), 0, 200));
            if ($r === true) $done = true;
            else $error = $r;
        }
    }
    require __DIR__ . '/views/reset.php';
    exit;
}

$user = Auth::user();
if (!$user) {
    redirect(admin_url('login'));
}

if (is_post() && !Auth::checkCsrf()) {
    http_response_code(400);
    exit('Security check failed (the page may have been open too long). Please go back, refresh and try again.');
}

if ($p === 'logout') {
    if (is_post()) Auth::logout();
    redirect(admin_url('login'));
}

// After installing: show the new rescue code once, until the owner confirms they saved it.
if ($p === 'welcome') {
    $rescueCode = (string)($_SESSION['rescue_show'] ?? '');
    if (is_post() || $rescueCode === '') {
        unset($_SESSION['rescue_show']);
        if (is_post()) flash('Welcome to Teeny CMS! Your site is ready. For extra safety you can now delete install.php.');
        redirect(admin_url('studio'));
    }
    require __DIR__ . '/views/welcome.php';
    exit;
}
unset($_SESSION['rescue_show']); // never keep a shown code around once the owner moves on

function require_cap(string $cap): void
{
    if (!Auth::can($cap)) {
        http_response_code(403);
        admin_header('Not allowed', '');
        echo '<div class="card"><h2 class="card-title">Not allowed</h2><p class="muted">Your account does not have permission to open this section.</p></div>';
        admin_footer();
        exit;
    }
}

function post_str(string $key, int $max = 500): string
{
    return Sanitizer::text($_POST[$key] ?? '', $max);
}

$site = site();

try {
switch ($p) {

    /* ---------- dashboard ---------- */
    case 'studio':
        require __DIR__ . '/views/dashboard.php';
        break;

    /* ---------- pages ---------- */
    case 'pages':
        require_cap('pages');
        if (is_post()) {
            $action = (string)($_POST['action'] ?? '');
            $id = (string)($_POST['id'] ?? '');
            if ($action === 'create') {
                $tpl = (string)($_POST['template'] ?? 'blank');
                $defaults = Pages::templateDefaults($tpl);
                $page = Pages::save([
                    'title'      => post_str('title', 120) ?: 'New page',
                    'status'     => 'draft',
                    'nav'        => true,
                    'order'      => count(Pages::index()),
                    'hideHeader' => !empty($defaults['hideHeader']),
                    'blocks'     => Pages::fromTemplate($tpl),
                ], $user['id']);
                redirect(admin_url('edit', ['id' => $page['id']]));
            }
            if ($action === 'duplicate' && ($copy = Pages::duplicate($id, $user['id']))) {
                flash('Page duplicated as a draft.');
            }
            if ($action === 'delete') {
                require_cap('settings');
                if (Pages::get($id)) {
                    Pages::delete($id);
                    flash('Page deleted.');
                }
            }
            if ($action === 'home') {
                require_cap('settings');
                $pg = Pages::get($id);
                if ($pg) {
                    $site['home'] = $id;
                    Store::write('site', $site);
                    site($site);
                    if ($pg['status'] !== 'published') {
                        $pg['status'] = 'published';
                        Pages::save($pg, $user['id']);
                    }
                    flash('“' . $pg['title'] . '” is now your home page.');
                }
            }
            redirect(admin_url('pages'));
        }
        require __DIR__ . '/views/pages.php';
        break;

    case 'new':
        require_cap('pages');
        require __DIR__ . '/views/page-new.php';
        break;

    case 'edit':
        require_cap('pages');
        $page = Pages::get((string)($_GET['id'] ?? ''));
        if (!$page) redirect(admin_url('pages'));
        require __DIR__ . '/views/editor.php';
        break;

    case 'canvas':
        require_cap('pages');
        $page = Pages::get((string)($_GET['id'] ?? ''));
        if (!$page) { http_response_code(404); exit; }
        echo Renderer::document($site, $page, '', ['editor' => true, 'footerEditable' => Auth::can('appearance')]);
        break;

    case 'history':
        require_cap('pages');
        $page = Pages::get((string)($_GET['id'] ?? ''));
        if (!$page) redirect(admin_url('pages'));
        if (is_post()) {
            if (Pages::restore($page['id'], (string)($_POST['rev'] ?? ''), $user['id'])) {
                flash('Earlier version restored. The version you replaced was kept in the history.');
            }
            redirect(admin_url('history', ['id' => $page['id']]));
        }
        $revisions = Pages::revisions($page['id']);
        require __DIR__ . '/views/history.php';
        break;

    /* ---------- media ---------- */
    case 'library':
        require_cap('media');
        require __DIR__ . '/views/media.php';
        break;

    /* ---------- plugins ---------- */
    case 'plugins':
        require_cap('plugins');
        if (is_post()) {
            $action = (string)($_POST['action'] ?? '');
            $id = (string)($_POST['id'] ?? '');
            try {
                if ($action === 'upload') {
                    $newId = Plugins::install(Packages::readUpload($_FILES['package'] ?? [], 'plugin'));
                    $name = Plugins::all()[$newId]['manifest']['name'] ?? $newId;
                    Activity::log('plugin.install', $name);
                    flash('“' . $name . '” was checked and installed. Review it below, then switch it on.');
                } elseif ($action === 'enable') {
                    Plugins::enable($id);
                    Activity::log('plugin.enable', Plugins::all()[$id]['manifest']['name'] ?? $id);
                    flash('Plugin switched on. Its section is now in the editor’s Add panel under “Plugins”.');
                } elseif ($action === 'disable') {
                    Plugins::disable($id);
                    Activity::log('plugin.disable', Plugins::all()[$id]['manifest']['name'] ?? $id);
                    flash('Plugin switched off. Its sections are hidden from your pages until you switch it on again.');
                } elseif ($action === 'download') {
                    if (!isset(Plugins::all()[$id])) throw new RuntimeException('Plugin not found.');
                    Packages::sendZip(Packages::zipFolder(Plugins::dir($id), $id), 'teeny-plugin-' . $id . '.zip');
                } elseif ($action === 'remove') {
                    $name = Plugins::all()[$id]['manifest']['name'] ?? $id;
                    Plugins::remove($id);
                    Activity::log('plugin.remove', $name);
                    flash('Plugin removed.');
                }
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'error');
            }
            redirect(admin_url('plugins'));
        }
        require __DIR__ . '/views/plugins.php';
        break;

    /* ---------- appearance ---------- */
    case 'style':
        require_cap('appearance');
        if (is_post() && isset($_POST['theme_action'])) {
            $ta = (string)$_POST['theme_action'];
            $tid = (string)($_POST['id'] ?? '');
            try {
                if ($ta === 'upload') {
                    $tid = Theme::installFiles(Packages::readUpload($_FILES['package'] ?? [], 'theme'));
                    Activity::log('theme.install', Theme::all()[$tid]['name'] ?? $tid);
                    flash('Theme “' . (Theme::all()[$tid]['name'] ?? $tid) . '” was checked and installed. Pick it under Theme to use it.');
                } elseif ($ta === 'save') {
                    $tid = Theme::saveCurrent((string)($_POST['name'] ?? ''), (string)$user['name']);
                    Activity::log('theme.save', Theme::all()[$tid]['name'] ?? $tid);
                    flash('Your style was saved as the theme “' . (Theme::all()[$tid]['name'] ?? $tid) . '”.');
                } elseif ($ta === 'remove') {
                    $name = Theme::all()[$tid]['name'] ?? $tid;
                    Theme::remove($tid);
                    Activity::log('theme.remove', $name);
                    flash('Theme removed.');
                } elseif ($ta === 'download') {
                    if (!isset(Theme::all()[$tid])) throw new RuntimeException('Theme not found.');
                    Packages::sendZip(Packages::zipFolder(TEENY_ROOT . '/themes/' . $tid, $tid), 'teeny-theme-' . $tid . '.zip');
                }
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'error');
            }
            redirect(admin_url('style') . '#themes');
        }
        if (is_post()) {
            $themes = Theme::all();
            $name = (string)($_POST['theme'] ?? '');
            if (!isset($themes[$name])) $name = Theme::active($site);
            $align = (string)($_POST['align'] ?? 'normal');
            $site['theme']['name'] = $name;
            $site['theme']['align'] = isset(Theme::ALIGNS[$align]) ? $align : 'normal';
            $overrides = is_array($site['theme']['overrides'] ?? null) ? $site['theme']['overrides'] : [];
            if (($_POST['action'] ?? '') === 'reset') {
                unset($overrides[$name]);
                flash('Theme settings reset to the ' . $themes[$name]['name'] . ' defaults.');
            } else {
                // Only customise the theme whose controls were shown on screen.
                $shown = (string)($_POST['customised'] ?? '');
                if (isset($themes[$shown])) {
                    $overrides[$shown] = Theme::clean(is_array($_POST['t'] ?? null) ? $_POST['t'] : [], $themes[$shown]['defaults']);
                }
                $site['theme']['customCss'] = Sanitizer::css((string)($_POST['customCss'] ?? ''));
                $site['theme']['visitorToggle'] = !empty($_POST['visitorToggle']);
                flash('Style saved.');
            }
            $site['theme']['overrides'] = $overrides;
            Store::write('site', $site);
            redirect(admin_url('style'));
        }
        require __DIR__ . '/views/appearance.php';
        break;

    /* ---------- menu ---------- */
    case 'navigation':
        require_cap('appearance');
        if (is_post()) {
            if (($_POST['action'] ?? '') === 'auto') {
                unset($site['menu']);
                flash('Navigation switched back to automatic.');
            } else {
                $items = json_decode((string)($_POST['menu'] ?? '[]'), true);
                $site['menu'] = Menu::sanitize($items);
                flash('Navigation saved.');
            }
            Store::write('site', $site);
            redirect(admin_url('navigation'));
        }
        require __DIR__ . '/views/menu.php';
        break;

    /* ---------- inbox ---------- */
    case 'messages':
        require_cap('inbox');
        if (is_post() && ($_POST['action'] ?? '') === 'sub_export') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
            echo Inbox::subscribersCsv();
            exit;
        }
        if (is_post() && ($_POST['action'] ?? '') === 'sub_delete') {
            Inbox::removeSubscriber((string)($_POST['email'] ?? ''));
            flash('Subscriber removed.');
            redirect(admin_url('messages', ['view' => 'subscribers']));
        }
        if (is_post()) {
            $action = (string)($_POST['action'] ?? '');
            $id = (string)($_POST['id'] ?? '');
            Inbox::update(static function (array $list) use ($action, $id): array {
                foreach ($list as $i => $m) {
                    if ($action === 'readall' || ($m['id'] === $id && $action === 'read')) $list[$i]['read'] = true;
                    if ($m['id'] === $id && $action === 'unread') $list[$i]['read'] = false;
                    if ($m['id'] === $id && $action === 'delete') unset($list[$i]);
                }
                return $list;
            });
            if ($action === 'delete') flash('Message deleted.');
            redirect(admin_url('messages'));
        }
        $messages = Inbox::all();
        require __DIR__ . '/views/inbox.php';
        break;

    /* ---------- settings ---------- */
    case 'preferences':
        require_cap('settings');
        if (is_post()) {
            $site['title'] = post_str('title', 80) ?: 'My Site';
            $site['tagline'] = post_str('tagline', 160);
            $site['description'] = post_str('description', 300);
            $site['lang'] = preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', (string)($_POST['lang'] ?? '')) ? (string)$_POST['lang'] : 'en';
            $tz = (string)($_POST['timezone'] ?? 'UTC');
            $site['timezone'] = in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
            $home = (string)($_POST['home'] ?? '');
            if (Pages::get($home)) $site['home'] = $home;
            $site['prettyUrls'] = !empty($_POST['prettyUrls']);
            $siteUrl = Sanitizer::url((string)($_POST['siteUrl'] ?? ''));
            $site['siteUrl'] = preg_match('~^https?://[^/\s]+(/[^\s]*)?$~i', $siteUrl) ? rtrim($siteUrl, '/') : '';
            foreach (['logo', 'favicon', 'shareImage'] as $k) {
                $v = (string)($_POST[$k] ?? '');
                $site[$k] = Media::validRef($v) ? $v : '';
            }
            $notify = trim((string)($_POST['notifyEmail'] ?? ''));
            $site['notifyEmail'] = filter_var($notify, FILTER_VALIDATE_EMAIL) ? $notify : '';
            $site['stats'] = !empty($_POST['stats']);
            $site['maintenance'] = [
                'on'      => !empty($_POST['maintenance_on']),
                'title'   => post_str('maintenance_title', 120),
                'message' => Sanitizer::text($_POST['maintenance_message'] ?? '', 500, true),
            ];
            // Email sending (Preferences → Email). The SMTP password is never sent back to the browser:
            // an empty box keeps the saved one.
            $oldMail = is_array($site['mail'] ?? null) ? $site['mail'] : [];
            $oneLine = static fn(string $k, int $max): string => substr(trim((string)preg_replace('/[\x00-\x1f\x7f]+/', '', (string)($_POST[$k] ?? ''))), 0, $max);
            $fromEmail = $oneLine('mail_fromEmail', 200);
            $smtpHost = strtolower($oneLine('mail_smtpHost', 253));
            $smtpPort = (int)($_POST['mail_smtpPort'] ?? 587);
            $smtpPass = (string)preg_replace('/[\r\n\0]+/', '', (string)($_POST['mail_smtpPass'] ?? ''));
            $site['mail'] = [
                'method'       => ($_POST['mail_method'] ?? '') === 'smtp' ? 'smtp' : 'php',
                'fromName'     => $oneLine('mail_fromName', 80),
                'fromEmail'    => filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : '',
                'smtpHost'     => preg_match('/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/', $smtpHost) ? $smtpHost : '',
                'smtpPort'     => $smtpPort >= 1 && $smtpPort <= 65535 ? $smtpPort : 587,
                'smtpSecure'   => in_array($_POST['mail_smtpSecure'] ?? '', ['tls', 'ssl', 'none'], true) ? (string)$_POST['mail_smtpSecure'] : 'tls',
                'smtpUser'     => $oneLine('mail_smtpUser', 200),
                'smtpPass'     => !empty($_POST['mail_smtpPass_clear']) ? '' : ($smtpPass !== '' ? substr($smtpPass, 0, 500) : (string)($oldMail['smtpPass'] ?? '')),
                'smtpNoVerify' => !empty($_POST['mail_smtpNoVerify']),
            ];
            Store::write('site', $site);
            site($site);
            if (($_POST['action'] ?? '') === 'mail_test') {
                $to = (string)(Auth::user()['email'] ?? '');
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    flash('Preferences saved. Add an email address to your account under Profile & security first, then send the test again.', 'error');
                } elseif (Mailer::send($to, 'Test email from ' . ($site['title'] ?? 'your website'), "This is a test email from your website's Studio.\n\nIf you can read this, emails such as password reset links can reach you.\n\nTeeny CMS\n")) {
                    Activity::log('email.sent', 'Test email sent to ' . $to);
                    flash('Preferences saved. A test email was sent to ' . $to . '. If it isn’t in your inbox within a few minutes, check your spam folder.' . ($site['mail']['method'] === 'smtp' ? '' : ' If it never arrives, switch to SMTP.'));
                } else {
                    Activity::log('email.failed', 'Test email: ' . Mailer::$lastError);
                    flash('Preferences saved, but the test email could not be sent. ' . Mailer::$lastError, 'error');
                }
                redirect(admin_url('preferences') . '#email');
            }
            flash($site['maintenance']['on'] ?'Preferences saved. Maintenance mode is ON, so visitors see the “Coming soon” page.' : 'Preferences saved.');
            redirect(admin_url('preferences'));
        }
        require __DIR__ . '/views/settings.php';
        break;

    /* ---------- users ---------- */
    case 'team':
        require_cap('users');
        $users = Auth::users();
        if (is_post()) {
            $action = (string)($_POST['action'] ?? '');
            $id = (string)($_POST['id'] ?? '');
            $adminCount = count(array_filter($users, static fn($u) => ($u['role'] ?? '') === 'admin'));
            if ($action === 'create') {
                $username = trim((string)($_POST['username'] ?? ''));
                $pass = (string)($_POST['password'] ?? '');
                $role = isset(Auth::ROLES[$_POST['role'] ?? '']) ? (string)$_POST['role'] : 'editor';
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $err = Auth::usernameProblem($username) ?? Auth::passwordProblem($pass) ?? Auth::emailProblem($email);
                if (!$err && Auth::findByUsername($username)) $err = 'That username is already taken.';
                if (!$err && Auth::emailTaken($email)) $err = 'Another team member already uses that email address.';
                if ($err) {
                    flash($err, 'error');
                } else {
                    $users[random_id(6)] = [
                        'username' => $username, 'name' => post_str('name', 80) ?: $username, 'email' => $email,
                        'role' => $role, 'hash' => Auth::hash($pass), 'sv' => 1, 'created' => time(),
                    ];
                    Auth::saveUsers($users);
                    flash('“' . $username . '” joined the team.');
                }
            } elseif (isset($users[$id])) {
                if ($action === 'update') {
                    $role = isset(Auth::ROLES[$_POST['role'] ?? '']) ? (string)$_POST['role'] : $users[$id]['role'];
                    if ($users[$id]['role'] === 'admin' && $role !== 'admin' && $adminCount <= 1) {
                        flash('The site needs at least one owner.', 'error');
                    } else {
                        // Username and email (owners can rename other members; your own username is changed in Profile).
                        $newUsername = trim((string)($_POST['username'] ?? $users[$id]['username']));
                        $email = strtolower(trim((string)($_POST['email'] ?? ($users[$id]['email'] ?? ''))));
                        if ($id !== $user['id'] && $newUsername !== $users[$id]['username']) {
                            $err = Auth::usernameProblem($newUsername) ?? (Auth::usernameTaken($newUsername, $id) ? 'That username is already taken.' : null);
                            if ($err) {
                                flash($err, 'error');
                                redirect(admin_url('team'));
                            }
                            Activity::log('account.rename', '@' . $users[$id]['username'] . ' → @' . $newUsername);
                            $users[$id]['username'] = $newUsername;
                        }
                        if ($err = Auth::emailProblem($email) ?? (Auth::emailTaken($email, $id) ? 'Another team member already uses that email address.' : null)) {
                            flash($err, 'error');
                            redirect(admin_url('team'));
                        }
                        $users[$id]['email'] = $email;
                        $oldRole = $users[$id]['role'];
                        $users[$id]['role'] = $role;
                        $users[$id]['name'] = post_str('name', 80) ?: $users[$id]['username'];
                        $pass = (string)($_POST['password'] ?? '');
                        if ($pass !== '') {
                            if ($err = Auth::passwordProblem($pass)) {
                                flash($err, 'error');
                                redirect(admin_url('team'));
                            }
                            $users[$id]['hash'] = Auth::hash($pass);
                        }
                        if ($pass !== '' || $role !== $oldRole) {
                            $users[$id]['sv'] = (int)($users[$id]['sv'] ?? 0) + ($id === $user['id'] ? 0 : 1);
                        }
                        Auth::saveUsers($users);
                        flash('Team member updated.');
                    }
                } elseif ($action === 'reset2fa') {
                    unset($users[$id]['totp'], $users[$id]['totpLast'], $users[$id]['recovery']);
                    if ($id !== $user['id']) $users[$id]['sv'] = (int)($users[$id]['sv'] ?? 0) + 1;
                    Auth::saveUsers($users);
                    flash('Two-factor login turned off for ' . $users[$id]['name'] . '. They can turn it on again in their Profile.');
                } elseif ($action === 'delete') {
                    if ($id === $user['id']) {
                        flash('You cannot delete your own account.', 'error');
                    } elseif ($users[$id]['role'] === 'admin' && $adminCount <= 1) {
                        flash('The site needs at least one owner.', 'error');
                    } else {
                        unset($users[$id]);
                        Auth::saveUsers($users);
                        flash('Removed from the team.');
                    }
                }
            }
            redirect(admin_url('team'));
        }
        require __DIR__ . '/views/users.php';
        break;

    /* ---------- own account ---------- */
    case 'profile':
        $action = is_post() ? (string)($_POST['action'] ?? 'profile') : '';
        if ($action === 'rescue_new') {
            $me = Auth::users()[$user['id']] ?? [];
            if (!password_verify((string)($_POST['current_password'] ?? ''), (string)($me['hash'] ?? ''))) {
                flash('Your password is incorrect.', 'error');
            } else {
                $_SESSION['rescue_profile'] = Reset::newRescueCode((string)$user['id']);
                Activity::log('account.rescue_new', '@' . $user['username'] . ' created a new rescue code');
                flash('New rescue code created. The old one no longer works.');
            }
            redirect(admin_url('profile') . '#rescue');
        }
        if (str_starts_with($action, 'totp_')) {
            $users = Auth::users();
            $me = $users[$user['id']];
            if ($action === 'totp_start') {
                $_SESSION['totp_setup'] = Totp::secret();
            } elseif ($action === 'totp_cancel') {
                unset($_SESSION['totp_setup']);
            } elseif ($action === 'totp_confirm') {
                $secret = (string)($_SESSION['totp_setup'] ?? '');
                $step = $secret !== '' ? Totp::verify($secret, (string)($_POST['code'] ?? '')) : null;
                if ($step === null) {
                    flash('That code did not match. Make sure the time on your phone is correct and try the newest code.', 'error');
                } else {
                    [$plain, $hashes] = Totp::recoveryCodes();
                    $me['totp'] = $secret;
                    $me['totpLast'] = $step;
                    $me['recovery'] = $hashes;
                    $users[$user['id']] = $me;
                    Auth::saveUsers($users);
                    unset($_SESSION['totp_setup']);
                    $_SESSION['recovery_show'] = $plain;
                    flash('Two-factor login is on. Save your recovery codes now.');
                }
            } elseif ($action === 'totp_disable' || $action === 'totp_recovery') {
                if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$me['hash'])) {
                    flash('Your password is incorrect.', 'error');
                } elseif ($action === 'totp_disable') {
                    unset($me['totp'], $me['totpLast'], $me['recovery']);
                    $users[$user['id']] = $me;
                    Auth::saveUsers($users);
                    flash('Two-factor login turned off.');
                } else {
                    [$plain, $hashes] = Totp::recoveryCodes();
                    $me['recovery'] = $hashes;
                    $users[$user['id']] = $me;
                    Auth::saveUsers($users);
                    $_SESSION['recovery_show'] = $plain;
                    flash('New recovery codes created. The old ones no longer work.');
                }
            }
            redirect(admin_url('profile') . '#two-factor');
        }
        if (is_post()) {
            $users = Auth::users();
            $me = &$users[$user['id']];
            $currentOk = password_verify((string)($_POST['current_password'] ?? ''), (string)$me['hash']);

            // Changing your username or sign-in email needs your current password.
            $newUsername = trim((string)($_POST['username'] ?? $me['username']));
            $newEmail = strtolower(trim((string)($_POST['email'] ?? ($me['email'] ?? ''))));
            $renaming = $newUsername !== $me['username'];
            $emailChanging = $newEmail !== strtolower((string)($me['email'] ?? ''));
            if ($renaming || $emailChanging) {
                if (!$currentOk) {
                    flash('Enter your current password to change your username or email.', 'error');
                    redirect(admin_url('profile'));
                }
                if ($renaming && ($err = Auth::usernameProblem($newUsername) ?? (Auth::usernameTaken($newUsername, $user['id']) ? 'That username is already taken.' : null))) {
                    flash($err, 'error');
                    redirect(admin_url('profile'));
                }
                if ($emailChanging && ($err = Auth::emailProblem($newEmail) ?? (Auth::emailTaken($newEmail, $user['id']) ? 'Another team member already uses that email address.' : null))) {
                    flash($err, 'error');
                    redirect(admin_url('profile'));
                }
                if ($renaming) {
                    Activity::log('account.rename', '@' . $me['username'] . ' → @' . $newUsername . ' (own account)');
                    $me['username'] = $newUsername;
                }
                $me['email'] = $newEmail;
            }

            $me['name'] = post_str('name', 80) ?: $me['username'];
            $new = (string)($_POST['new_password'] ?? '');
            if ($new !== '') {
                if (!$currentOk) {
                    flash('Your current password is incorrect.', 'error');
                    redirect(admin_url('profile'));
                }
                if ($err = Auth::passwordProblem($new)) {
                    flash($err, 'error');
                    redirect(admin_url('profile'));
                }
                if ($new !== (string)($_POST['new_password2'] ?? '')) {
                    flash('The new passwords do not match.', 'error');
                    redirect(admin_url('profile'));
                }
                $me['hash'] = Auth::hash($new);
                // Sign out every other session that used the old password.
                $me['sv'] = (int)($me['sv'] ?? 0) + 1;
                $_SESSION['sv'] = $me['sv'];
                session_regenerate_id(true);
            }
            unset($me);
            Auth::saveUsers($users);
            flash('Account updated.');
            redirect(admin_url('profile'));
        }
        require __DIR__ . '/views/account.php';
        break;

    /* ---------- backup ---------- */
    case 'vault':
        require_cap('backup');
        if (is_post() && ($_POST['action'] ?? '') === 'export') {
            $pages = [];
            foreach (Pages::index() as $id => $m) {
                if ($pg = Pages::get((string)$id)) $pages[] = $pg;
            }
            $bundle = [
                'teeny'    => TEENY_VERSION,
                'exported' => date('c'),
                'site'     => $site,
                'pages'    => $pages,
                'media'    => Store::read('media', ['items' => []])['items'] ?? [],
            ];
            unset($bundle['site']['mail']['smtpPass']); // never put the mail password in a downloadable file
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="teeny-site-copy-' . date('Y-m-d') . '.json"');
            echo json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (is_post() && ($_POST['action'] ?? '') === 'media_zip') {
            if (!class_exists('ZipArchive')) {
                flash('Your server does not support zip files. Download the "uploads" folder by FTP instead.', 'error');
                redirect(admin_url('vault'));
            }
            $tmp = tempnam(sys_get_temp_dir(), 'teeny');
            $zip = new ZipArchive();
            $zip->open($tmp, ZipArchive::OVERWRITE);
            foreach (glob(TEENY_UPLOADS . '/*.{jpg,png,gif,webp}', GLOB_BRACE) ?: [] as $f) {
                $zip->addFile($f, 'uploads/' . basename($f));
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="teeny-library-' . date('Y-m-d') . '.zip"');
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp);
            @unlink($tmp);
            exit;
        }
        if (is_post() && ($_POST['action'] ?? '') === 'import') {
            $f = $_FILES['backup'] ?? null;
            $raw = (is_array($f) && ($f['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) && $f['size'] < 10485760)
                ? (string)file_get_contents($f['tmp_name']) : '';
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['teeny'], $data['site'], $data['pages']) || !is_array($data['pages'])) {
                flash('That file is not a Teeny site copy.', 'error');
                redirect(admin_url('vault'));
            }
            // Replace pages
            foreach (array_keys(Pages::index()) as $id) Pages::delete((string)$id);
            $map = [];
            foreach (array_slice($data['pages'], 0, 500) as $pg) {
                if (!is_array($pg)) continue;
                $oldId = (string)($pg['id'] ?? '');
                $pg['id'] = valid_id($oldId) ? $oldId : null;
                $saved = Pages::save($pg, $user['id'], true);
                $map[$oldId] = $saved['id'];
            }
            // Restore safe site settings
            $in = is_array($data['site']) ? $data['site'] : [];
            $keep = $site;
            foreach (['title' => 80, 'tagline' => 160, 'description' => 300, 'footer' => 300] as $k => $max) {
                if (isset($in[$k])) $keep[$k] = Sanitizer::text($in[$k], $max);
            }
            if (isset($in['footerAlign']) && isset(Renderer::FOOTER_ALIGN[(string)$in['footerAlign']])) $keep['footerAlign'] = (string)$in['footerAlign'];
            foreach (['logo', 'favicon', 'shareImage'] as $k) {
                $keep[$k] = Media::validRef($in[$k] ?? '') ? $in[$k] : '';
            }
            if (isset($in['theme']['name']) && isset(Theme::all()[$in['theme']['name']])) $keep['theme']['name'] = $in['theme']['name'];
            if (isset($in['theme']['align']) && isset(Theme::ALIGNS[$in['theme']['align']])) $keep['theme']['align'] = $in['theme']['align'];
            $keep['theme']['overrides'] = [];
            foreach ((array)($in['theme']['overrides'] ?? []) as $tn => $ov) {
                if (isset(Theme::all()[$tn]) && is_array($ov)) $keep['theme']['overrides'][$tn] = Theme::clean($ov, Theme::all()[$tn]['defaults']);
            }
            $keep['theme']['customCss'] = Sanitizer::css($in['theme']['customCss'] ?? '');
            $keep['home'] = $map[(string)($in['home'] ?? '')] ?? (string)(array_key_first(Pages::index()) ?? '');
            $keep['theme']['visitorToggle'] = !empty($in['theme']['visitorToggle']);
            unset($keep['menu']);
            if (is_array($in['menu'] ?? null)) {
                $menu = [];
                foreach ($in['menu'] as $it) {
                    if (is_array($it) && ($it['type'] ?? '') === 'page') $it['id'] = $map[(string)($it['id'] ?? '')] ?? '';
                    $menu[] = $it;
                }
                site($keep);
                $keep['menu'] = Menu::sanitize($menu);
            }
            Store::write('site', $keep);
            flash('Site restored from your saved copy: ' . count($map) . ' page(s). Upload your images again (or copy the uploads folder) if they are missing.');
            redirect(admin_url('vault'));
        }
        require __DIR__ . '/views/backup.php';
        break;

    default:
        redirect(admin_url('studio'));
}
} catch (RuntimeException $ex) {
    error_log('Teeny admin: ' . $ex->getMessage());
    flash($ex->getMessage(), 'error');
    redirect(admin_url('studio'));
}
