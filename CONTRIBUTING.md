# Contributing to Teeny

Thanks for wanting to help! Teeny is small on purpose, so every change is checked against one question: does it keep Teeny simple for the people who use it?

## Ways to help

- **Report a bug:** [open an issue](https://github.com/teenycms/teeny/issues/new/choose) with your Teeny version, PHP version and the steps that cause it.
- **Suggest an idea:** start a [discussion](https://github.com/teenycms/teeny/discussions). See what's planned on the [roadmap](https://github.com/orgs/teenycms/projects/1).
- **Fix something:** small fixes are always welcome as a pull request.
- **Make a theme or plugin:** see [Make your own theme](docs/THEMES.md) and [Make your own plugin](docs/PLUGINS.md). Official ones live in [teenycms/extras](https://github.com/teenycms/extras).
- **Improve the docs or wiki:** clearer words help everyone.

Found a security problem? Please don't open an issue. Follow [SECURITY.md](SECURITY.md) to report it privately.

## Before you start a bigger change

Open a discussion first for new features, new settings or anything that adds files. It saves you time if the idea doesn't fit, and we can agree on the simplest way to do it.

## Run Teeny on your computer

You need PHP 8.1 or newer. In the Teeny folder:

```
php -S localhost:8000 router.php
```

Then open <http://localhost:8000> and follow the installer. No PHP? Use [GitHub Codespaces](https://codespaces.new/teenycms/teeny?quickstart=1) and everything is set up for you in the browser.

Your test content goes into `data/` and `uploads/`. Don't commit those folders.

## House rules

- **Stay small.** The download must stay under 250 KB. No frameworks, no build step and no npm packages. Plain PHP, CSS and JavaScript.
- **No outside services.** Teeny never loads anything from another website (fonts, scripts, trackers) unless the owner turns it on.
- **Safe by default.** Clean every input, keep the strict content security policy, and never let plugins run code on the server.
- **Works on cheap hosting.** PHP 8.1+, no database, no shell commands, Apache, LiteSpeed, IIS and nginx.
- **Plain English.** Write words a non-technical person understands. Short sentences. No long dashes.
- **Works on phones.** Check every screen at 390 px wide, in light and dark.
- **Match the code around you.** Same naming, same layout, no new dependencies.

## Sending a pull request

1. **Fork** the repository and create a branch for your change.
2. Make your change and test it in a browser: install a fresh site, try it on a phone-sized window, and check the page editor still works.
3. Open a **pull request** and fill in the short checklist.
4. We'll review it, maybe ask for changes, and merge it when it's ready. Please be patient: this is a small project.

Keep each pull request to one change. Small ones get reviewed faster.

## Licence

By contributing, you agree that your work is shared under the same [licence](LICENSE) as the rest of Teeny.
