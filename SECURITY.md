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
