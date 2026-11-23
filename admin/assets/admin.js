/* Teeny CMS: admin helpers (no dependencies) */
(() => {
  'use strict';
  const cfgEl = document.getElementById('teeny-config');
  const config = cfgEl ? JSON.parse(cfgEl.textContent) : {};

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  async function api(action, body, method) {
    const opts = { method: method || (body ? 'POST' : 'GET'), headers: { 'X-CSRF-Token': config.csrf }, credentials: 'same-origin' };
    if (body instanceof FormData) {
      body.append('_csrf', config.csrf);
      opts.body = body;
    } else if (body) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    let res, data;
    try {
      res = await fetch(config.api + '?action=' + encodeURIComponent(action), opts);
      data = await res.json();
    } catch (e) {
      return { ok: false, error: 'Network error. Please check your connection.' };
    }
    if (res.status === 401) data.error = data.error || 'Session expired.';
    return data;
  }

  let toastTimer;
  function toast(msg, type) {
    let el = $('[data-toast]');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      el.setAttribute('data-toast', '');
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.className = 'toast' + (type === 'error' ? ' toast-error' : '');
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.hidden = true; }, type === 'error' ? 6000 : 2600);
  }

  async function uploadFiles(files, onEach) {
    for (const file of files) {
      const fd = new FormData();
      fd.append('file', file);
      const r = await api('upload', fd);
      if (r.ok && r.item) onEach && onEach(r.item);
      else toast((file.name ? file.name + ': ' : '') + (r.error || 'Upload failed'), 'error');
    }
  }

  /* ---------- media picker modal ---------- */
  const modal = $('#media-modal');
  let pickCallback = null;

  function thumbEl(item) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'media-pick';
    b.title = item.original;
    const img = document.createElement('img');
    img.src = item.thumb;
    img.alt = item.alt || '';
    img.loading = 'lazy';
    b.appendChild(img);
    b.addEventListener('click', () => choose(item));
    return b;
  }

  async function openPicker(cb) {
    if (!modal) return;
    pickCallback = cb;
    modal.hidden = false;
    document.body.classList.add('modal-open');
    const grid = $('[data-media-grid]', modal);
    grid.innerHTML = '<p class="muted">Loading…</p>';
    const r = await api('media');
    grid.innerHTML = '';
    const items = r.ok ? r.items : [];
    items.forEach(it => grid.appendChild(thumbEl(it)));
    $('[data-media-empty]', modal).hidden = items.length > 0;
  }

  function closePicker() {
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('modal-open');
    pickCallback = null;
  }

  function choose(item) {
    const cb = pickCallback;
    closePicker();
    cb && cb(item);
  }

  if (modal) {
    $$('[data-close]', modal).forEach(el => el.addEventListener('click', closePicker));
    $('[data-remove]', modal).addEventListener('click', () => choose(null));
    const up = $('[data-upload]', modal);
    if (up) up.addEventListener('change', async () => {
      const grid = $('[data-media-grid]', modal);
      const files = Array.from(up.files);
      up.value = '';
      toast('Uploading ' + files.length + ' image(s)…');
      let last = null;
      await uploadFiles(files, item => { last = item; grid.prepend(thumbEl(item)); $('[data-media-empty]', modal).hidden = true; });
      if (last && files.length === 1) choose(last);
      else if (last) toast('Uploaded. Click an image to use it.');
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closePicker(); });
  }

  /* ---------- generic UI ---------- */
  document.addEventListener('submit', e => {
    const msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  document.addEventListener('click', e => {
    const c = e.target.closest('[data-confirm-click]');
    if (c && !window.confirm(c.getAttribute('data-confirm-click'))) e.preventDefault();
    // close user popovers when clicking elsewhere
    $$('details.pop[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  /* media fields in forms (settings: logo, favicon…) */
  $$('[data-media-field]').forEach(field => {
    const input = $('input[type=hidden]', field);
    const preview = $('.media-field-preview', field);
    $('[data-pick]', field).addEventListener('click', () => openPicker(item => {
      input.value = item ? item.ref : '';
      preview.innerHTML = '';
      if (item) { const img = document.createElement('img'); img.src = item.thumb; img.alt = ''; preview.appendChild(img); }
    }));
  });

  /* ---------- media library page ---------- */
  const library = $('[data-library]');
  if (library) {
    const tpl = $('#media-item-tpl');
    const empty = $('[data-library-empty]');
    const status = $('[data-upload-status]');
    const render = item => {
      const node = tpl.content.firstElementChild.cloneNode(true);
      $('img', node).src = item.thumb;
      $('img', node).alt = item.alt || '';
      $('small', node).textContent = item.w + '×' + item.h + ' · ' + item.size;
      const alt = $('.media-alt', node);
      alt.value = item.alt || '';
      let t;
      alt.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(async () => {
          const r = await api('media_alt', { name: item.name, alt: alt.value });
          if (r.ok) toast('Description saved');
        }, 700);
      });
      $('[data-delete]', node).addEventListener('click', async () => {
        if (!confirm('Delete this image? Pages using it will show nothing in its place.')) return;
        const r = await api('media_delete', { name: item.name });
        if (r.ok) { node.remove(); empty.hidden = library.children.length > 0; toast('Image deleted'); }
      });
      return node;
    };
    api('media').then(r => {
      (r.items || []).forEach(it => library.appendChild(render(it)));
      empty.hidden = (r.items || []).length > 0;
    });
    const doUpload = async files => {
      files = Array.from(files).filter(f => /^image\//.test(f.type));
      if (!files.length) return;
      status.textContent = 'Uploading ' + files.length + ' image(s)…';
      await uploadFiles(files, item => { library.prepend(render(item)); empty.hidden = true; });
      status.textContent = '';
      toast('Upload finished');
    };
    const input = $('[data-library-upload]');
    input.addEventListener('change', () => { doUpload(input.files); input.value = ''; });
    const dz = $('[data-dropzone]');
    ['dragenter', 'dragover'].forEach(ev => document.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => document.addEventListener(ev, e => { e.preventDefault(); if (ev === 'drop' || e.target === dz) dz.classList.remove('over'); }));
    document.addEventListener('drop', e => doUpload(e.dataTransfer.files));
  }

  /* ---------- appearance page ---------- */
  const appearance = $('[data-appearance]');
  if (appearance) {
    const customised = $('[data-customised]', appearance);
    const preview = fs => {
      const box = $('[data-preview]', fs);
      const val = n => { const el = $('[data-var="' + n + '"]', fs); return el ? el.value : ''; };
      const stack = n => { const el = $('[data-var="' + n + '"]', fs); return el ? el.selectedOptions[0].dataset.stack : ''; };
      box.style.setProperty('--p-bg', val('bg'));
      box.style.setProperty('--p-surface', val('surface'));
      box.style.setProperty('--p-text', val('text'));
      box.style.setProperty('--p-muted', val('muted'));
      box.style.setProperty('--p-accent', val('accent'));
      box.style.setProperty('--p-accent-text', val('accentText'));
      box.style.setProperty('--p-border', val('border'));
      box.style.setProperty('--p-heading', stack('headingFont'));
      box.style.setProperty('--p-body', stack('bodyFont'));
      box.style.setProperty('--p-radius', val('radius') + 'px');
      box.style.setProperty('--p-size', val('fontSize') + 'px');
      $$('input[type=color]', fs).forEach(c => { c.nextElementSibling.querySelector('small').textContent = c.value; });
      $$('input[type=range]', fs).forEach(r => { r.closest('label').querySelector('output').textContent = r.value + 'px'; });
    };
    $$('[data-customise]').forEach(fs => {
      preview(fs);
      fs.addEventListener('input', () => preview(fs));
    });
    $$('[data-theme-radio]', appearance).forEach(r => r.addEventListener('change', () => {
      $$('[data-customise]').forEach(fs => {
        const on = fs.dataset.customise === r.value;
        fs.hidden = !on;
        fs.disabled = !on;
      });
      customised.value = r.value;
    }));
  }

  /* ---------- copy buttons ---------- */
  $$('[data-copy]').forEach(btn => btn.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(btn.dataset.copy); toast('Copied'); }
    catch (e) { toast('Could not copy. Please select and copy it by hand.', 'error'); }
  }));

  /* ---------- download buttons (save text as a file, nothing leaves the browser) ---------- */
  $$('[data-download]').forEach(btn => btn.addEventListener('click', () => {
    const url = URL.createObjectURL(new Blob([btn.dataset.download.replace(/\n/g, '\r\n')], { type: 'text/plain' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = btn.dataset.filename || 'download.txt';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }));

  /* ---------- menu editor ---------- */
  const menuData = $('#menu-data');
  if (menuData) {
    const M = JSON.parse(menuData.textContent);
    const items = M.items.slice();
    const list = $('[data-menu-list]');
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let dragFrom = null;
    const render = () => {
      list.innerHTML = '';
      items.forEach((it, i) => {
        const li = document.createElement('li');
        li.className = 'menu-item';
        li.draggable = true;
        li.dataset.i = i;
        const pg = it.type === 'page' ? M.pages[it.id] : null;
        const meta = it.type === 'page'
          ? 'Page: ' + esc(pg ? pg.title : 'deleted page') + (pg && pg.status !== 'published' ? ' · <em>draft, hidden until published</em>' : '')
          : 'Link: ' + esc(it.url) + (it.newTab ? ' · opens in new tab' : '');
        li.innerHTML = `<span class="grip" aria-hidden="true">⋮⋮</span><div class="mi-body"><input class="mi-label" maxlength="60" aria-label="Menu label"><small class="muted">${meta}</small></div><div class="mi-actions"><button type="button" class="icon-btn" data-mv="-1" title="Move up" ${i === 0 ? 'disabled' : ''}>↑</button><button type="button" class="icon-btn" data-mv="1" title="Move down" ${i === items.length - 1 ? 'disabled' : ''}>↓</button><button type="button" class="icon-btn danger" data-rm title="Remove">✕</button></div>`;
        const inp = $('.mi-label', li);
        inp.value = it.label || '';
        inp.placeholder = it.type === 'page' ? (pg ? pg.title : '') : 'Label';
        inp.addEventListener('input', () => { it.label = inp.value; });
        $('[data-rm]', li).addEventListener('click', () => { items.splice(i, 1); render(); });
        $$('[data-mv]', li).forEach(b => b.addEventListener('click', () => {
          const j = i + Number(b.dataset.mv);
          [items[i], items[j]] = [items[j], items[i]];
          render();
        }));
        li.addEventListener('dragstart', e => { dragFrom = i; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(i)); });
        li.addEventListener('dragend', () => { dragFrom = null; render(); });
        li.addEventListener('dragover', e => { if (dragFrom === null) return; e.preventDefault(); li.classList.add('drop-target'); });
        li.addEventListener('dragleave', () => li.classList.remove('drop-target'));
        li.addEventListener('drop', e => {
          e.preventDefault();
          if (dragFrom === null || dragFrom === i) return;
          const [moved] = items.splice(dragFrom, 1);
          items.splice(i, 0, moved);
          dragFrom = null;
          render();
        });
        list.appendChild(li);
      });
      $('[data-menu-empty]').hidden = items.length > 0;
    };
    render();
    $('[data-add-page-btn]').addEventListener('click', () => {
      const sel = $('[data-add-page]');
      if (!sel.value) return;
      items.push({ type: 'page', id: sel.value, label: '' });
      render();
    });
    $('[data-add-link-btn]').addEventListener('click', () => {
      const label = $('[data-link-label]').value.trim();
      const url = $('[data-link-url]').value.trim();
      if (!label || !url) { toast('Please enter a label and an address', 'error'); return; }
      if (/^\s*(javascript|data|vbscript):/i.test(url)) { toast('That kind of link is not allowed', 'error'); return; }
      items.push({ type: 'link', label, url, newTab: $('[data-link-newtab]').checked });
      $('[data-link-label]').value = '';
      $('[data-link-url]').value = '';
      render();
    });
    $('[data-menu-form]').addEventListener('submit', () => {
      $('[data-menu-json]').value = JSON.stringify(items);
    });
  }

  window.Teeny = { config, api, toast, openPicker, uploadFiles, $, $$ };
})();
