<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
admin_header('Library', 'library', ['actions' => '<label class="btn btn-primary">' . icon('upload') . ' Upload<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden data-library-upload></label>']);
?>
<div class="dropzone" data-dropzone>
  <?= icon('upload') ?>
  <p><strong>Drop images here</strong> or use the Upload button.</p>
  <small class="muted">JPG, PNG, GIF or WebP · up to <?= (int)TEENY_MAX_UPLOAD_MB ?> MB each<?= Media::gdAvailable() ? ' · large images are resized automatically' : '' ?></small>
</div>
<div class="upload-status" data-upload-status></div>
<div class="media-grid media-grid--library" data-library></div>
<p class="empty" data-library-empty hidden>Your library is empty.</p>

<template id="media-item-tpl">
  <figure class="media-item">
    <div class="media-thumb"><img alt="" loading="lazy"></div>
    <figcaption>
      <input class="media-alt" placeholder="Describe this image…" maxlength="200" aria-label="Image description">
      <div class="media-meta"><small class="muted"></small>
        <button class="icon-btn danger" type="button" title="Delete" data-delete><?= icon('trash') ?></button>
      </div>
    </figcaption>
  </figure>
</template>
<?php admin_footer();
