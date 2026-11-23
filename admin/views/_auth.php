<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php

/** Minimal, light layout for sign-in pages: "Teeny Studio" on the left, a bordered form on the right. */
function auth_open(string $title, string $heading, string $intro = ''): void
{
    $site = site();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($site['title'] ?? 'Teeny CMS') ?></title>
<link rel="icon" href="<?= e(asset('assets/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('admin/assets/admin.css')) ?>">
</head>
<body class="signin">
<main class="signin-wrap">
  <section class="signin-side">
    <?= teeny_logo(false, 'signin-mark') ?>
    <p class="signin-word">Teeny<span>Studio</span></p>
    <p class="signin-site"><?= e($site['title'] ?? '') ?></p>
  </section>
  <section class="signin-main">
    <div class="signin-box">
      <h1><?= e($heading) ?></h1>
      <?php if ($intro !== ''): ?><p class="signin-intro"><?= e($intro) ?></p><?php endif; ?>
<?php
}

function auth_close(string $footHtml = ''): void
{
    ?>
    </div>
    <?php if ($footHtml !== ''): ?><p class="signin-foot"><?= $footHtml ?></p><?php endif; ?>
  </section>
</main>
</body>
</html>
<?php
}
