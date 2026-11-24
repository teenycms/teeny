# Security

Teeny CMS is built to be safe on ordinary shared hosting. This page explains how it protects your site and how to report a problem.

## Reporting a vulnerability

Please **don't open a public issue** for security problems.

Use **GitHub → Security → Report a vulnerability** on this repository. This sends a private report to the maintainers. Include:

- what an attacker could do, and who they need to be (a visitor, an editor or an owner)
- the steps or a small example that shows it
- the Teeny CMS version (shown at the bottom of the Studio)

You'll get a reply as soon as possible. Fixes are released as a new version, with the files to upload listed in the [changelog](CHANGELOG.md).

## Supported versions

Only the latest release gets security fixes. Updating is quick: upload the files listed in the changelog. Your `data/` and `uploads/` folders are never touched.

## How Teeny protects your site

**Accounts and sign-in**
- Passwords are hashed with Argon2id (or bcrypt where Argon2id isn't available), and re-hashed automatically when a stronger setting is available. Very common passwords are refused.
- Sign-in, two-step codes, password resets, rescue codes and page passwords are all rate-limited, and repeated failures lock that username and visitor out for a while.
- Optional two-factor sign-in (authenticator app), with one-time recovery codes.
- Sessions use secure, HttpOnly, SameSite cookies. They end after 2 hours of inactivity or 12 hours in total, and are signed out everywhere after a password change.
- Reset links and rescue codes work once. Only their hashes are stored, and the pages never reveal whether an account exists.

**Roles**
- Owners manage everything. Editors can write pages and manage images, but can't delete pages, change settings, manage the team or install anything. Every Studio page and API action checks the role on the server.

**Requests and content**
- Every form and API call is protected against cross-site request forgery.
- Text from editors, visitors and plugins is cleaned with strict allow-lists before it's shown. Links can't run scripts.
- A Content-Security-Policy blocks inline and third-party scripts. Other security headers (HSTS on HTTPS, nosniff, frame protection, referrer policy) are sent on every page.
- Teeny sends no CORS headers, so other websites can't read its responses.

**Files**
- Content is stored in `data/` as files that refuse to run or display if opened directly, behind server rules that block the folder.
- Uploads must be real JPG, PNG, GIF or WebP images. They're checked, re-saved and renamed, and nothing in `uploads/` can run as code.
- Theme and plugin packages are checked file by file (no PHP, no hidden files, no path tricks, size limits). Plugins can't run code on your server, and any interactive part runs in a sealed sandbox.
- Vault backups never include team accounts (so no password hashes) or the SMTP password.

## Keeping your site safe

- Use **https**. If your site is behind Cloudflare, turn on **SSL/TLS → Edge Certificates → Always Use HTTPS**.
- Turn on **two-factor sign-in** for every owner (Profile & security).
- Save your **rescue code**, and set up email in **Preferences → Email**.
- Delete `install.php` after installing.
- Keep Teeny CMS updated, and only install themes and plugins you trust.
- If your server runs nginx, add the rules from the [guide](docs/GUIDE.md#extra-nginx-servers).

### Extra hardening (optional)

- **Move the data folder outside the web root.** Copy `config.sample.php` to `config.php`, move `data` one level up (for example to `/home/you/teeny-data`), then set:
  ```php
  define('TEENY_DATA', dirname(__DIR__) . '/teeny-data');
  ```
- **nginx users:** `.htaccess` files are ignored, so add this to your server block:
  ```nginx
  location ~ ^/(core|data|templates|examples|brand|admin/views)/ { deny all; }
  location ~ /\.(?!well-known/) { deny all; }
  location ~ (^|/)(error_log|config\.php|config\.sample\.php|web\.config)$ { deny all; }
  location ~ ^/uploads/.*\.(php\d?|phtml|phar|pl|py|cgi|sh|html?|js|svg)$ { deny all; }
  location ~ ^/(themes|plugins)/.*\.(php\d?|phtml|phar|json|html?|svg|txt|md)$ { deny all; }
  location ~ \.(json|md|log|bak|old|orig|sql|zip|tar|gz)$ { deny all; }
  location / { try_files $uri $uri/ /index.php?$query_string; }
  ```
  Put these rules **above** your PHP `location` block. If Teeny lives in a subfolder (for example `/site`), add the folder to the start of each path: `^/site/(core|data|…)`.

## Technical details

The exact limits and rules, for anyone reviewing Teeny.

- **Passwords**
  - Hashed with Argon2id (or bcrypt where Argon2id is unavailable). Never stored in plain text.
  - Minimum 10 characters.
- **Brute-force protection:** a username is locked for 15 minutes after 5 failed logins, and an IP address after 10. Repeated failures make the lock longer.
- **Sessions**
  - Cookies are HttpOnly, SameSite and Secure on HTTPS.
  - The session ID changes at login.
  - You are logged out after 2 hours idle, and every session ends after 12 hours.
  - Changing your password logs out all your other devices.
- **Two-factor login (optional per user)**
  - Uses standard TOTP codes (RFC 6238). A code can't be used twice.
  - Every user gets 8 one-time recovery codes, stored only as hashes.
  - An administrator can turn off 2FA for a user who loses their phone.
- **Contact form spam protection**
  - A hidden honeypot field and a timing check catch bots.
  - Each visitor can send at most 5 messages per hour, and the whole site accepts at most 200 per day.
  - Messages with many links are rejected, and all input is stripped of HTML.
- **Password-protected pages**
  - Passwords are stored only as hashes and never sent to the browser.
  - Each page allows 6 wrong attempts per 15 minutes.
  - Protected pages are never cached, indexed or listed in the sitemap.
- **Privacy-friendly stats:** a daily-rotating random salt is used for counting, so raw IP addresses are never stored. Bots and logged-in users are not counted.
- **Themes and plugins (safe by design)**
  - **Uploads are checked file by file.** Only allowed file types are accepted. Anything that tries to write outside its folder, symbolic links and "zip bombs" are rejected, and images and fonts must be genuine. The package only goes live after every check passes.
  - **No server code.** Plugins can't contain or run PHP.
  - **Safe templates.** Placeholders are always escaped, and the result is cleaned: no scripts, event handlers, inline styles, frames, forms or `javascript:` links.
  - **Contained styles.** Plugin CSS is cleaned and limited to its own section.
  - **Sandboxed JavaScript.** Plugin JavaScript only runs in a sandboxed frame, enforced both by the page and by a security header. It can't read cookies, the page or the Studio, and it can't contact any website.
  - **Owners stay in control.** Only Owners can install, switch on or remove themes and plugins. New plugins start switched off, and every change is logged.
- **CSRF protection:** a secret token and an origin check guard every form and save.
- **XSS protection**
  - All output is escaped.
  - Rich text passes a strict allow-list cleaner, so scripts, event handlers, `javascript:` links, iframes and styles are removed.
  - A strict Content-Security-Policy blocks inline scripts.
- **Protected data**
  - JSON files are stored as `.json.php` with a guard line. Even on a server that ignores `.htaccess`, opening one in a browser returns an empty 404.
  - The `data`, `core` and `templates` folders are also blocked by `.htaccess` / `web.config`.
- **Uploads**
  - Only real JPG, PNG, GIF and WebP images are accepted, checked by content and not just by name.
  - Files with embedded code are rejected.
  - Images get random names and are re-encoded when GD is available.
  - Nothing can run from the `uploads` folder. SVG is not allowed.
- **Clickjacking and other headers:** `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, and HSTS on HTTPS.
- **Errors:** never shown to visitors. They are logged to `data/logs/error.log`.
