/* Teeny CMS: tiny public script: lightbox, light/dark toggle, form timing, popups */
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

  // Popups: each one waits for its trigger and is remembered in this browser only ("how often"),
  // with no cookies and nothing sent to the server. Never more than one centered box at a time.
  const popups = Array.from(document.querySelectorAll('[data-popup]'));
  if (popups.length) {
    const WAIT = { day: 864e5, week: 6048e5, month: 2592e6 };
    const key = p => 'tc-popup-' + p.dataset.popup;
    const storage = often => { try { return often === 'visit' ? sessionStorage : localStorage; } catch (e) { return null; } };
    const due = p => {
      const often = p.dataset.often;
      if (often === 'always') return true;
      let last = null;
      try { const st = storage(often); last = st ? st.getItem(key(p)) : null; } catch (e) { return true; }
      if (last === null) return true;
      if (often === 'visit' || often === 'once') return false;
      return Date.now() - Number(last) > (WAIT[often] || WAIT.week);
    };
    const remember = p => {
      if (p.dataset.often === 'always') return;
      try { const st = storage(p.dataset.often); if (st) st.setItem(key(p), String(Date.now())); } catch (e) {}
    };
    const reduced = () => window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    const open = [];
    let modal = null, lastFocus = null;
    const focusables = p => Array.from(p.querySelectorAll('.tc-popup__box a[href], .tc-popup__box button'));

    const show = p => {
      if (!p.hidden || !due(p)) return;
      const isModal = p.classList.contains('tc-popup--modal');
      if (isModal && modal) return;
      p.hidden = false;
      open.push(p);
      void p.offsetWidth;            // apply the hidden-state styles first, so the fade-in can run
      p.classList.add('is-open');
      if (isModal) {
        modal = p;
        lastFocus = document.activeElement;
        document.documentElement.classList.add('tc-popup-lock');
        const f = focusables(p);
        const first = f.find(x => x.classList.contains('tc-popup__btn')) || f[0];
        if (first) first.focus({ preventScroll: true });
      }
    };
    const hide = p => {
      if (p.hidden) return;
      remember(p);
      p.classList.remove('is-open');
      open.splice(open.indexOf(p), 1);
      setTimeout(() => { p.hidden = true; }, reduced() ? 0 : 220);
      if (p === modal) {
        modal = null;
        document.documentElement.classList.remove('tc-popup-lock');
        if (lastFocus && document.contains(lastFocus)) lastFocus.focus({ preventScroll: true });
      }
    };

    document.addEventListener('keydown', e => {
      if (!open.length) return;
      if (e.key === 'Escape') { hide(modal || open[open.length - 1]); return; }
      if (e.key === 'Tab' && modal) {   // keep keyboard focus inside the centered box
        const f = focusables(modal);
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        else if (!modal.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
      }
    });

    const fine = window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches;
    popups.forEach(p => {
      p.addEventListener('click', e => {
        if (e.target.closest('[data-popup-close]')) { e.preventDefault(); hide(p); }
        else if (e.target.closest('[data-popup-done]')) hide(p);   // remembered as seen; the link still opens normally
      });
      if (!due(p)) return;
      const t = p.dataset.trigger || 'delay5';
      const later = s => setTimeout(() => show(p), s * 1000);
      if (t === 'load') later(0.4);
      else if (t.indexOf('delay') === 0) later(parseInt(t.slice(5), 10) || 5);
      else if (t === 'exit' && fine) {
        const onOut = e => { if (!e.relatedTarget && e.clientY <= 0) { document.removeEventListener('mouseout', onOut); show(p); } };
        document.addEventListener('mouseout', onOut);
      } else {
        // "scroll", and "exit" on touch screens (which have no mouse to leave with): halfway down the page
        const check = () => {
          const max = document.documentElement.scrollHeight - innerHeight;
          if (max > 0 && scrollY / max < 0.5) return;
          removeEventListener('scroll', check);
          show(p);
        };
        addEventListener('scroll', check, { passive: true });
        if (document.documentElement.scrollHeight - innerHeight <= 0) setTimeout(check, 5000);  // short page: show after 5 seconds
      }
    });
  }
})();
