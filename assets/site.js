/* Teeny CMS: tiny public script: lightbox, light/dark toggle, form timing */
(() => {
  // Gallery lightbox
  const links = document.querySelectorAll('[data-lightbox]');
  if (links.length && typeof HTMLDialogElement === 'function') {
    const dlg = document.createElement('dialog');
    dlg.className = 'tc-lightbox';
    dlg.innerHTML = '<button type="button" aria-label="Close">×</button><img alt="">';
    document.body.appendChild(dlg);
    const img = dlg.querySelector('img');
    dlg.querySelector('button').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    links.forEach(a => a.addEventListener('click', e => {
      e.preventDefault();
      const thumb = a.querySelector('img');
      img.src = a.getAttribute('href');
      img.alt = thumb ? thumb.alt : '';
      dlg.showModal();
    }));
  }

  // Visitor light/dark toggle
  const toggle = document.querySelector('[data-scheme-toggle]');
  if (toggle) {
    const root = document.documentElement;
    const sync = () => {
      const alt = root.getAttribute('data-scheme') === 'alt';
      toggle.querySelector('[data-icon-default]').toggleAttribute('hidden', alt);
      toggle.querySelector('[data-icon-alt]').toggleAttribute('hidden', !alt);
      toggle.setAttribute('aria-label', toggle.dataset[alt ? 'labelAlt' : 'labelDefault']);
      toggle.title = toggle.getAttribute('aria-label');
    };
    toggle.addEventListener('click', () => {
      const alt = root.getAttribute('data-scheme') !== 'alt';
      if (alt) root.setAttribute('data-scheme', 'alt'); else root.removeAttribute('data-scheme');
      try { localStorage.setItem('tc-scheme', alt ? 'alt' : 'default'); } catch (e) {}
      sync();
    });
    sync();
  }

  // Sandboxed plugin widgets report their height; only frames on this page are trusted to resize.
  const widgets = Array.from(document.querySelectorAll("iframe.tc-widget"));
  if (widgets.length) {
    addEventListener("message", e => {
      const frame = widgets.find(w => w.contentWindow === e.source);
      const h = e.data && e.data.teenyWidget === 1 ? Number(e.data.height) : NaN;
      if (frame && Number.isFinite(h)) frame.height = String(Math.max(40, Math.min(2000, Math.round(h))));
    });
  }

  // Before / after sliders: the invisible range input drives the split (works with mouse, touch and keyboard).
  document.querySelectorAll('[data-compare]').forEach(fig => {
    const frame = fig.querySelector('.tc-compare__frame');
    const range = fig.querySelector('.tc-compare__range');
    if (!frame || !range) return;
    const set = () => frame.style.setProperty('--pos', range.value + '%');
    range.addEventListener('input', set);
    set();
  });

  // Tabs: accessible tab list with arrow-key support. Without JavaScript every panel simply stays visible.
  document.querySelectorAll('[data-tabs]').forEach(box => {
    const tabs = Array.from(box.querySelectorAll('[role="tab"]'));
    const panels = tabs.map(t => document.getElementById(t.getAttribute('aria-controls')));
    if (!tabs.length) return;
    const show = (i, focus) => {
      tabs.forEach((t, j) => {
        const on = i === j;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        if (panels[j]) panels[j].hidden = !on;
      });
      if (focus) tabs[i].focus();
    };
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => show(i, false));
      t.addEventListener('keydown', e => {
        const k = e.key;
        if (k === 'ArrowRight' || k === 'ArrowLeft' || k === 'Home' || k === 'End') {
          e.preventDefault();
          const n = k === 'Home' ? 0 : k === 'End' ? tabs.length - 1 : (i + (k === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
          show(n, true);
        }
      });
    });
    box.classList.add('is-ready');
    show(0, false);
  });

  // After sending a form, bring the confirmation into view
  if (/^#(contact|newsletter)-[a-z0-9]+$/.test(location.hash)) {
    const target = document.querySelector(location.hash);
    if (target) setTimeout(() => target.scrollIntoView({ block: 'center' }), 60);
  }

  // Contact forms: record how long the visitor spent (bots submit instantly)
  const started = Date.now();
  document.querySelectorAll('form.tc-form').forEach(form => {
    form.addEventListener('submit', () => {
      const t = form.querySelector('[data-tc-timer]');
      if (t) t.value = String(Math.round((Date.now() - started) / 1000));
    });
  });
})();
