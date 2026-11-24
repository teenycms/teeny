# Changelog

All notable changes to Teeny CMS are listed here, newest first.
Each release says **which files to upload** when updating an existing site.
Never overwrite your `data/` and `uploads/` folders (or your own `config.php`).


---

## 1.4.5 · 2026-11-24

### Fixed
- **Text no longer turns into capitals when you save.** Some themes show small labels, button text and quote names in capital letters. Text typed into those places was saved in capitals too, so "Get started" became "GET STARTED". It's now saved exactly as you type it. Text that was already saved in capitals stays that way until you type it again.

### Files to upload
```
admin/assets/editor.js
core/bootstrap.php
```
---

## 1.4.4 · 2026-11-24

### Added
- **Try Teeny without a server.** An **Open in GitHub Codespaces** button starts a private test copy of Teeny in your browser, ready to install. Nothing to set up.
- **One command to run Teeny on your computer:** `php -S localhost:8000 router.php`, or a Docker command for people without PHP. The new `router.php` makes PHP's built-in server follow the same rules as a real host, so clean page addresses work and private folders stay private. It does nothing on a real web host.

### Files to upload
Nothing to upload for existing sites. `router.php` is only used on your own computer.

---

## 1.4.3 · 2026-11-24

### Changed
- **Tidier footer.** Footer links and social icons now each sit on their own row under the footer text, so the footer stays short and neat, also on phones.
- **Show first.** In the editor's **Footer** panel, choose whether the links or the social icons come first.

### Added
- **Reorder footer links and icons.** Use the ↑ and ↓ buttons next to each link or icon to change the order.

### Files to upload
```
admin/api.php
admin/index.php
admin/views/editor.php
admin/assets/editor.js
admin/assets/admin.css
assets/site.css
core/Renderer.php
core/bootstrap.php
```
---

## 1.4.2 · 2026-11-24

### Added
- **Footer links and social icons.** In the editor's **Footer** panel, add up to 6 links (for example Privacy or Contact) and up to 8 social media icons. They show under the footer text and follow the footer's position.
- **New footer shortcuts:** `{site}` shows your site name and stays correct if you rename the site, and `{teeny}` adds a "Teeny CMS" link. `{year}` still shows the current year.

### Changed
- **New sites start with this footer:** © {year} {site} · Built with {teeny}, which shows as "© 2026 Your Site · Built with Teeny CMS". You can change or remove it any time. Existing sites keep their footer.

### Files to upload
```
install.php                      (only if you still need it, for new installs)
admin/api.php
admin/index.php
admin/views/editor.php
admin/assets/editor.js
admin/assets/admin.css
assets/site.css
core/Renderer.php
core/bootstrap.php
```
---

## 1.4.1 · 2026-11-24

### Added
- **Help and GitHub links in the Studio footer.** The version number now links to its release notes, **Help** opens the wiki, and **GitHub** opens the project page, each in a new tab. Nothing is checked or sent in the background.

### Files to upload
```
admin/views/_layout.php
admin/assets/admin.css
core/bootstrap.php
```

---

## 1.4.0 · 2026-11-24

### Added
- **Popups**, a new kind of section in the editor's **Add** panel (group **Popups**). Three types:
  - **Centered box:** a box in the middle of the screen, with an optional image. On phones it slides up from the bottom.
  - **Bar:** a slim strip across the top or bottom of the screen.
  - **Corner card:** a small card at the bottom right or left.
- **When it appears:** right away, after 5, 10 or 30 seconds, after scrolling halfway down, or when the visitor moves to leave (on computers; on phones this waits for scrolling halfway instead).
- **How often:** every time the page opens, once per visit, once a day, once a week, once a month, or only once. This is remembered in the visitor's own browser, with no cookies and nothing sent to your server.
- Popups close with the × button, the Escape key, or a click outside a centered box. Keyboard focus stays inside a centered box while it's open and returns afterwards, and animations follow the visitor's "reduce motion" setting.

### Fixed
- In the editor, an empty **Small label** in a Hero section showed its placeholder squeezed into a narrow column on the Luxury theme.

### Files to upload
```
admin/assets/editor.js
admin/assets/canvas.css
assets/site.js
assets/site.css
core/Blocks.php
core/Renderer.php
core/bootstrap.php
```
---

## 1.3.0 · 2026-11-24

### Added
- **Edit the footer right on the page.** In the editor, click the footer at the bottom of any page to type in it. A **Footer** panel opens with:
  - the footer text (type `{year}` to always show the current year)
  - **Position:** Auto (follows the page alignment), Left, Center or Right
  - **Hide the footer on this page**

  The text and position are the same on every page. Each page can hide the footer on its own, and leaving the text empty hides it everywhere. You can also open the panel from **Page → Footer settings**.
- Owners can change the footer text and position. Editors can hide the footer on the pages they edit.

### Changed
- The footer text has moved out of **Preferences → General**. Your existing footer is kept.

### Files to upload
```
admin/index.php
admin/api.php
admin/views/editor.php
admin/views/settings.php
admin/assets/editor.js
admin/assets/canvas.css
assets/site.css
core/Pages.php
core/Renderer.php
core/bootstrap.php
```
---

## 1.2.0 · 2026-11-23

A polish and security release.

### Security
- **Plugins can no longer sneak content into the editor.** A plugin's default values are now cleaned and limited to its own fields, the editor no longer lets them replace a section's type, and the editor's preview frame can't run scripts at all. **Please update if you install third-party plugins.**
- **Two-step codes keep their own attempt limit.** Entering the right password no longer resets the count of wrong codes.
- **Nobody can lock you out on purpose.** When a username is locked after wrong passwords, its owner can still sign in from a browser they've used successfully in the last 30 days.
- **Attempts are counted before they're checked**, so sending many requests at once doesn't get extra guesses (sign-in, two-step codes and page passwords).
- **"Forgot password" answers in the same time** whether or not the account exists.
- **Still GIF images are re-saved on upload**, like JPG, PNG and WebP, which removes anything hidden inside them.
- **Custom and plugin CSS:** escaped characters are decoded before checking, and plugin styles can't reach neighbouring sections.
- New optional `TEENY_FORCE_HTTPS` setting in `config.php` for sites behind a proxy that hides https from PHP.
- **More private files are blocked**: `web.config`, `error_log` files, backup and archive files (`.bak`, `.old`, `.zip`, `.sql` and similar), and the Studio's internal `admin/views` folder. Studio views also refuse to run if opened directly, even on servers that ignore `.htaccess`.
- **Very common passwords are refused** when choosing a new password.
- **Sign-in takes the same time** whether or not a username exists, so it can't be used to discover usernames.
- New [SECURITY.md](SECURITY.md) explains how Teeny protects your site and how to report a problem privately.

### Fixed
- **Phones:** long words or web addresses in headings and text no longer push the page sideways, and price lists stack neatly on small screens.
- **Phones:** the sign-in pages and the Style page no longer run off the right edge of the screen on narrow phones.
- **Older browsers:** the Dark theme's main headline no longer disappears on browsers without `color-mix()` support (for example older iPhones). Callouts and highlighted text get plain fallbacks too.
- **After updating, visitors always get the new version.** Pages cached by an older release are no longer served.
- The test email message no longer suggests switching to SMTP when SMTP is already in use.

### Changed
- **New Teeny CMS logo**, used in the Studio, on the sign-in pages, in the installer and as the browser-tab icon.
- **The Studio's top bar shows just the logo icon** in the top-left corner.
- Cleaner wording across the Studio, emails and example content.
- Pages now include `og:type`, `og:site_name`, a Twitter/X card and a `theme-color` that matches your theme, for nicer link previews and a matching browser bar on phones.
- Page names that clash with Teeny's folders (`plugins`, `examples`, `brand`, `part`) get "-page" added automatically.
- Removed leftover styles and code from the old Studio layout.

### Files to upload
Many files changed in this release, so the simplest way is to upload the whole update zip. It contains everything below and nothing from `data/` or `uploads/`.
```
.htaccess
web.config
config.sample.php
index.php
part.php
admin/index.php
admin/api.php
admin/views/                     (the whole folder)
admin/assets/admin.css
admin/assets/admin.js
admin/assets/editor.js
admin/assets/canvas.css
admin/assets/qr.js
assets/                          (the whole folder)
core/                            (the whole folder)
themes/dark/theme.css
themes/luxury/theme.css
themes/minimal/theme.css
themes/.htaccess
plugins/.htaccess
plugins/countdown/
plugins/opening-hours/plugin.json
uploads/.htaccess
templates/landing.json
templates/portfolio.json
```

---

## 1.1.0 · 2026-11-20

### Added
- **Rescue codes: reset a forgotten password without email.**
  - Right after installing, a welcome page asks you to save your password and shows your **rescue code**. You can copy it or download it as a text file.
  - If you forget your password, go to **Forgot password? → Use your rescue code**. Enter your username or email, the code and a new password.
  - Each code works once. After you use it, you get a new code straight away, and you're signed out on every device. Two-factor sign-in stays on.
  - Only a secure hash of the code is stored. Attempts are limited to 5 per hour, per visitor and per account. The page never says whether an account exists.
  - Make a new code any time in **Profile & security → Rescue code**. Existing accounts can create theirs there too.
- **The installer reminds you to save your username and password**, and explains the rescue code.

### Changed
- The Studio reminder now appears for anyone who couldn't get back in after forgetting their password, meaning they have no rescue code and reset emails can't reach them. It links to both fixes.

### Files to upload
```
install.php                      (only if you still need it, for new installs)
admin/index.php
admin/views/welcome.php          (new)
admin/views/recover.php          (new)
admin/views/forgot.php
admin/views/account.php
admin/views/dashboard.php
admin/assets/admin.css
admin/assets/admin.js
core/Reset.php
core/bootstrap.php
```

After updating, open **Profile & security** and click **Create my rescue code**.
---

## 1.0.5 · 2026-11-16

### Changed
- **SMTP is now the recommended way to send email.** It's listed first in Preferences → Email, and new sites start with it selected. Sites that already use the host's built-in mail keep their setting.
- **The Studio reminds owners to set up email** when password reset emails can't be sent yet, or when the last email failed. The reminder links straight to the email settings and disappears once email works.

### Files to upload
```
install.php                      (only if you still need it, for new installs)
admin/views/settings.php
admin/views/dashboard.php
admin/assets/admin.css
core/Reset.php
core/bootstrap.php
```
---

## 1.0.4 · 2026-11-10

### Fixed
- No more "session.sid_length is deprecated" notices in the error log on PHP 8.4 and newer.

### Files to upload
```
core/Auth.php
core/bootstrap.php
```

---

## 1.0.3 · 2026-10-28

### Added
- **Email settings** in Preferences → Email:
  - A status check shows whether your website address is set, whether your account has an email address, and how emails are sent.
  - **Send test email** sends a message to your own address and tells you right away if it failed, and why.
  - **SMTP sending**, through a real mailbox (your host's email account, Gmail, Zoho and others). This is much more reliable than the host's built-in mail. The password is never shown again after saving, and it is left out of Vault downloads.
  - **Recent emails** lists the last few emails: sent, failed (with the reason), or not sent (for example "this account has no email address" or "too many requests").
- Password reset emails and new message alerts now use these settings.

### Fixed
- Password reset emails were skipped without any notice when the website address was empty (sites installed before 1.0.2). The reason now shows in Preferences → Email.

### Files to upload
```
admin/index.php
admin/views/settings.php
admin/assets/admin.css
core/Mailer.php                  (new)
core/Reset.php
core/Inbox.php
core/bootstrap.php
```

After updating, open **Preferences → Email** and click **Save & send test email**.
---

## 1.0.2 · 2026-10-23

### Added
- **Forgot password.**
  - A "Forgot password?" link on the sign-in page emails a reset link to the account's email address.
  - Links work once and expire after 1 hour.
  - The reply is the same whether or not an account exists, and requests are rate-limited.
  - Links always use the site address from Preferences.
- **Change your username.** In Profile & security, after confirming your current password. Owners can rename other team members in Team. Page history and two-factor sign-in keep working, and every rename is logged.
- **Email address on accounts** (optional), used for password resets. It can be set in the installer, in Profile & security, and in Team.

### Changed
- **New sign-in pages.** Sign in, two-step verification, forgot password and reset now use a minimal, light design, with "Teeny Studio" on the left and a simple bordered form.
- **The installer saves your website address** automatically, so reset emails link to the right place.
- **New Teeny CMS logo**, used in the Studio, on the sign-in pages and as the browser-tab icon.

### Files to upload
```
admin/index.php
admin/views/_auth.php            (new)
admin/views/login.php
admin/views/login-2fa.php
admin/views/forgot.php           (new)
admin/views/reset.php            (new)
admin/views/account.php
admin/views/users.php
admin/assets/admin.css
core/Auth.php
core/Reset.php                   (new)
core/Store.php
core/bootstrap.php
core/helpers.php
admin/views/_layout.php
assets/favicon.svg
install.php                      (only if you still keep it on your server)
```

After updating, go to **Profile & security** and add your email address. Then check that **Preferences → Search engines → Website address** shows your site's address (for example `https://your-site.com`). Password-reset emails need both.

---

## 1.0.1 · 2026-10-13

### Fixed
- **Team page:** the edit panel (name, role, new password) was cut off by its card. It now opens in full, in both wide and narrow layouts.

### Files to upload
```
admin/assets/admin.css
core/bootstrap.php        (new version number, so browsers load the fresh styles automatically)
CHANGELOG.md              (optional)
README.md                 (optional)
```

---

## 1.0.0 · 2026-10-10

First public release.

### Highlights
- **Flat-file CMS.** Content is stored as JSON in `data/`. No database, no build step, no external services.
- **Teeny Studio.** A calm editing area with Studio (dashboard), Pages, Library, Messages, Navigation and Style, plus a profile menu for Plugins, Team, Preferences and the Vault.
- **Visual editor.**
  - Click any text on the page and type.
  - 31 section types, including hero, image + text, steps, tabs, callout, table, events, numbers, call to action, logo strip, team, products, before/after slider, announcement, contact form and newsletter.
  - Every section has Position (left, center, right) and Width (narrow, medium, wide, full).
- **Look and layout.**
  - Three themes (Minimal, Dark, Luxury) with colour, font and shape customisation.
  - Four page alignments, plus a light/dark switch for visitors.
- **Custom themes.** Save your current style as a theme, download and share themes as .zip files, and upload new ones. Every file is checked.
- **Section plugins.**
  - New section types made from JSON, templates and CSS.
  - Interactive parts run in a sealed sandbox.
  - Three examples included: Opening hours, Testimonials and Countdown.
- **Messages.** Contact form inbox with optional email alerts, and newsletter subscribers with CSV download.
- **Pages.** Password-protected pages, maintenance ("Coming soon") mode, a custom navigation editor, version history (20 saves per page) and a sitemap.
- **Privacy-friendly visitor stats.** No cookies and no IP addresses stored.
- **Security.**
  - Sign-in and sessions:
    - Argon2id passwords
    - lockout after repeated failed sign-ins
    - optional two-factor sign-in with QR setup and recovery codes
  - Request and content protection:
    - CSRF tokens on every form
    - strict Content-Security-Policy
    - an allow-list cleaner for rich text
  - Files and add-ons:
    - protected data files
    - uploads that are checked file by file
    - safe theme and plugin packages
- **Team roles.** Owners and Editors.
- **Vault.** Save and restore a copy of your site.

### Install
Upload the folder to your website and open `install.php`. See [README.md](README.md).
