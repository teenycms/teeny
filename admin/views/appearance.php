<?php
declare(strict_types=1);
defined('TEENY_VERSION') || exit; // views only run through admin/index.php
$themes = Theme::all();
$active = Theme::active($site);
$align = (string)($site['theme']['align'] ?? 'normal');
admin_header('Style', 'style');
?>
<form method="post" class="appearance" data-appearance>
  <?= Auth::csrfField() ?>
  <input type="hidden" name="customised" value="<?= e($active) ?>" data-customised>

  <section class="card">
    <h3 class="card-title">Theme</h3>
    <p class="muted">Choose the overall style. Your customisations are remembered separately for each theme.</p>
    <div class="theme-grid theme-grid--lg">
      <?php foreach ($themes as $key => $t): $d = Theme::settings($site, $key); ?>
        <label class="theme-card">
          <input type="radio" name="theme" value="<?= e($key) ?>" <?= $key === $active ? 'checked' : '' ?> data-theme-radio>
          <span class="theme-swatch theme-swatch--lg" style="background:<?= e($d['bg']) ?>;color:<?= e($d['text']) ?>">
            <span class="sw-eyebrow" style="color:<?= e($d['muted']) ?>;font-family:<?= e(Theme::FONTS[$d['bodyFont']]['stack']) ?>">Welcome</span>
            <span class="sw-title" style="font-family:<?= e(Theme::FONTS[$d['headingFont']]['stack']) ?>">Aa Bb</span>
            <span class="sw-btn" style="background:<?= e($d['accent']) ?>;color:<?= e($d['accentText']) ?>;border-radius:<?= (int)$d['radius'] ?>px">Button</span>
          </span>
          <span class="theme-name"><?= e($t['name']) ?></span>
          <small class="muted"><?= e($t['description'] ?? '') ?></small>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <h3 class="card-title">Page alignment</h3>
    <p class="muted">How your content sits on the screen. Individual pages can override this in the editor.</p>
    <div class="align-grid align-grid--lg">
      <?php foreach (Theme::ALIGNS as $key => $a): ?>
        <label class="align-card">
          <input type="radio" name="align" value="<?= e($key) ?>" <?= $align === $key ? 'checked' : '' ?>>
          <span class="align-pic align-pic--<?= e($key) ?>"><i></i><i></i><i></i></span>
          <span class="align-name"><?= e($a['label']) ?></span>
          <small class="muted"><?= e($a['desc']) ?></small>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head">
      <h3 class="card-title">Customise</h3>
      <button class="btn btn-ghost btn-sm" type="submit" name="action" value="reset" data-confirm-click="Reset this theme's colours and fonts to its defaults?">Reset to defaults</button>
    </div>
    <?php foreach ($themes as $key => $t): $s = Theme::settings($site, $key); ?>
      <fieldset class="customise" data-customise="<?= e($key) ?>" <?= $key === $active ? '' : 'hidden disabled' ?>>
        <div class="customise-grid">
          <div>
            <p class="field-label">Colours</p>
            <div class="colors">
              <?php foreach (Theme::COLOR_KEYS as $ck => $label): ?>
                <label class="color-field">
                  <input type="color" name="t[<?= e($ck) ?>]" value="<?= e($s[$ck]) ?>" data-var="<?= e($ck) ?>">
                  <span><?= e($label) ?><small><?= e($s[$ck]) ?></small></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <p class="field-label">Typography &amp; shape</p>
            <label class="field"><span>Heading font</span>
              <select name="t[headingFont]" data-var="headingFont">
                <?php foreach (Theme::FONTS as $fk => $f): ?><option value="<?= e($fk) ?>" data-stack="<?= e($f['stack']) ?>" <?= $s['headingFont'] === $fk ? 'selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label class="field"><span>Body font</span>
              <select name="t[bodyFont]" data-var="bodyFont">
                <?php foreach (Theme::FONTS as $fk => $f): ?><option value="<?= e($fk) ?>" data-stack="<?= e($f['stack']) ?>" <?= $s['bodyFont'] === $fk ? 'selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label class="field range"><span>Text size <output><?= (int)$s['fontSize'] ?>px</output></span><input type="range" min="14" max="22" name="t[fontSize]" value="<?= (int)$s['fontSize'] ?>" data-var="fontSize"></label>
            <label class="field range"><span>Corner roundness <output><?= (int)$s['radius'] ?>px</output></span><input type="range" min="0" max="28" name="t[radius]" value="<?= (int)$s['radius'] ?>" data-var="radius"></label>
            <label class="field range"><span>Content width <output><?= (int)$s['width'] ?>px</output></span><input type="range" min="480" max="1400" step="20" name="t[width]" value="<?= (int)$s['width'] ?>" data-var="width"></label>
          </div>
          <div class="preview-wrap">
            <p class="field-label">Preview</p>
            <div class="mini-preview" data-preview>
              <p class="mp-eyebrow">Introducing</p>
              <p class="mp-title">Quietly beautiful</p>
              <p class="mp-text">This is how your text will look on the website. Calm, readable and yours.</p>
              <span class="mp-btn">Get in touch</span>
              <div class="mp-card">A card or panel</div>
            </div>
          </div>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h3 class="card-title">Light &amp; dark for visitors</h3>
    <label class="check switch-row"><input type="checkbox" name="visitorToggle" value="1" <?= !empty($site['theme']['visitorToggle']) ? 'checked' : '' ?>> <span><strong>Show a light/dark switch on the website</strong><br><small class="muted">Visitors can flip to a matching dark (or light) version of the theme. Their choice is remembered on their device only.</small></span></label>
  </section>

  <section class="card">
    <details>
      <summary class="card-title">Custom CSS <small class="muted">(advanced)</small></summary>
      <p class="muted">Add your own CSS rules. External files and scripts are not allowed. Available variables: <code>--tc-bg</code>, <code>--tc-text</code>, <code>--tc-accent</code>, <code>--tc-radius</code>, <code>--tc-wide</code> and more.</p>
      <textarea name="customCss" class="code-input" rows="10" spellcheck="false" placeholder=".tc-hero__title { letter-spacing: -0.02em; }"><?= e($site['theme']['customCss'] ?? '') ?></textarea>
    </details>
  </section>

  <div class="sticky-save">
    <a class="btn btn-ghost" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Visit site</a>
    <button class="btn btn-primary" type="submit" name="action" value="save">Save style</button>
  </div>
</form>

<section class="card" id="themes">
  <div class="card-head">
    <h3 class="card-title">Your themes</h3>
    <span class="muted small"><?= count($themes) ?> installed</span>
  </div>
  <p class="muted">Save your current look as a theme, share it as a .zip, or add a theme someone made for you. Themes contain only design files (styles, fonts and images), so they can't run code.</p>
  <div class="theme-manage">
    <ul class="theme-rows">
      <?php foreach ($themes as $key => $t): $d = Theme::settings($site, $key); ?>
        <li>
          <span class="theme-dot" style="background:<?= e($d['bg']) ?>;box-shadow:inset 0 0 0 7px <?= e($d['bg']) ?>, inset 0 0 0 20px <?= e($d['accent']) ?>"></span>
          <span class="tr-name"><strong><?= e($t['name']) ?></strong><small class="muted"><?= Theme::isBuiltin($key) ? 'Built in' : e(trim(($t['author'] ?? '') !== '' ? 'by ' . $t['author'] : 'Custom theme')) ?><?= $key === $active ? ' · in use' : '' ?></small></span>
          <span class="tr-actions">
            <?php if (Packages::zipAvailable()): ?>
            <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="theme_action" value="download"><input type="hidden" name="id" value="<?= e($key) ?>"><button class="icon-btn" title="Download as .zip"><?= icon('backup') ?></button></form>
            <?php endif; ?>
            <?php if (!Theme::isBuiltin($key)): ?>
            <form method="post" data-confirm="Remove the theme “<?= e($t['name']) ?>”?"><?= Auth::csrfField() ?><input type="hidden" name="theme_action" value="remove"><input type="hidden" name="id" value="<?= e($key) ?>"><button class="icon-btn danger" title="Remove"><?= icon('trash') ?></button></form>
            <?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="theme-forms">
      <form method="post" class="mini-form">
        <?= Auth::csrfField() ?><input type="hidden" name="theme_action" value="save">
        <p class="field-label">Save my current style as a theme</p>
        <div class="add-row"><input name="name" maxlength="40" required placeholder="e.g. Autumn Studio"><button class="btn btn-ghost">Save</button></div>
        <small class="hint">Includes your colours, fonts, shapes and custom CSS. Save your style above first.</small>
      </form>
      <?php if (Packages::zipAvailable()): ?>
      <form method="post" enctype="multipart/form-data" class="mini-form">
        <?= Auth::csrfField() ?><input type="hidden" name="theme_action" value="upload">
        <p class="field-label">Add a theme (.zip)</p>
        <div class="add-row"><input type="file" name="package" accept=".zip,application/zip" required><button class="btn btn-ghost"><?= icon('upload') ?> Upload</button></div>
        <small class="hint">Checked before installing: only theme.json, theme.css, fonts and images are accepted.</small>
      </form>
      <?php else: ?>
        <p class="hint">To add a theme, copy its folder into <code>themes/</code> by FTP (your server can't open zip files).</p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php admin_footer();
