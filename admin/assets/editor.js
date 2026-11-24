/* Teeny CMS: visual block editor (no dependencies)
 * The canvas is an iframe that loads the real theme CSS. Blocks are rendered here with the
 * same markup as core/Renderer.php, plus inline-editing hooks. The server sanitizes on save. */
(() => {
  'use strict';
  const T = window.Teeny;
  const { $, $$ } = T;
  const D = JSON.parse($('#editor-data').textContent);
  const S = D.schema;
  const page = D.page;
  page.blocks = Array.isArray(page.blocks) ? page.blocks : [];

  let cdoc = null, root = null, selected = null;
  // The site footer: one text for every page, edited in place (see "footer" further down).
  const FT = Object.assign({ text: '', align: '' }, D.footer || {});
  FT.links = Array.isArray(FT.links) ? FT.links.map(l => ({ label: String(l.label || ''), url: String(l.url || '') })) : [];
  FT.social = Array.isArray(FT.social) ? FT.social.map(s => ({ network: String(s.network || 'website'), url: String(s.url || '') })) : [];
  let footerDirty = false;
  const footerInput = document.querySelector('[data-footer-input]');
  let dirty = false, saving = false, version = 0;
  const undoStack = [], redoStack = [];
  let lastSnap = JSON.stringify(page.blocks), snapTimer = null;

  const frame = $('#ed-canvas');
  const PO = (() => { const d = document.createElement('div'); try { d.contentEditable = 'plaintext-only'; } catch (e) { return false; } return d.contentEditable === 'plaintext-only'; })();
  const CE = PO ? 'plaintext-only' : 'true';

  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // Values used inside class names, tag names and data-id attributes: letters, numbers, - and _ only.
  const tok = s => String(s == null ? '' : s).replace(/[^a-z0-9_-]/gi, '');
  const TAGS = /^(h[1-6]|p|span|div|li|small|strong|em|blockquote|figcaption|pre|code|b|i)$/;
  const uid = () => Array.from(crypto.getRandomValues(new Uint8Array(4)), b => b.toString(16).padStart(2, '0')).join('');
  const clone = o => JSON.parse(JSON.stringify(o));
  const mediaUrl = ref => D.baseUrl + ref;
  const IMG_IC = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="1.5"/><circle cx="9" cy="10" r="1.8"/><path d="m21 16-5-5-9 9"/></svg>';

  /* ---------- data helpers ---------- */
  const getPath = (obj, path) => path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj);
  function setPath(obj, path, val) {
    const keys = path.split('.');
    let o = obj;
    for (let i = 0; i < keys.length - 1; i++) o = o[keys[i]];
    o[keys[keys.length - 1]] = val;
  }
  const current = () => page.blocks.find(b => b.id === selected) || null;
  const indexOf = id => page.blocks.findIndex(b => b.id === id);
  function fieldDef(type, path) {
    const parts = path.split('.');
    let def = S[type].fields[parts[0]];
    if (parts.length === 3 && def && def.type === 'list') def = def.item[parts[2]];
    return def;
  }
  function blankItem(listDef) {
    const it = {};
    Object.entries(listDef.item).forEach(([k, d]) => { it[k] = d.type === 'select' ? Object.keys(d.options)[0] : ''; });
    return it;
  }
  function summary(b) {
    const def = S[b.type];
    if (!def) return 'switched-off plugin';
    for (const [k, f] of Object.entries(def.fields)) {
      let v = b[k];
      if (f.type === 'rich') v = String(v || '').replace(/<[^>]+>/g, ' ');
      if (f.type === 'list' && Array.isArray(v) && v.length) return v.length + ' item' + (v.length > 1 ? 's' : '');
      if (typeof v === 'string' && ['text', 'textarea', 'rich', 'code'].includes(f.type) && v.trim()) {
        v = v.replace(/\s+/g, ' ').trim();
        return v.length > 38 ? v.slice(0, 38) + '…' : v;
      }
    }
    return '';
  }

  /* ---------- canvas markup (mirrors core/Renderer.php) ---------- */
  function ed(tag, path, val, cls, ph, kind) {
    if (!TAGS.test(String(tag))) tag = 'div';
    const attr = kind === 'multi' ? ' data-multi' : kind === 'code' ? ' data-code data-multi' : '';
    return `<${tag} class="${cls || ''}" data-field="${path}" data-ph="${esc(ph)}"${attr} contenteditable="${CE}" spellcheck="${kind === 'code' ? 'false' : 'true'}">${esc(val)}</${tag}>`;
  }
  function pick(path, ref, cls, label, alt) {
    if (ref) return `<img class="${cls || ''}" src="${esc(mediaUrl(ref))}" alt="${esc(alt || '')}" data-pick="${path}" title="Click to change the image">`;
    return `<button type="button" class="tce-ph ${cls || ''}" data-pick="${path}" contenteditable="false">${IMG_IC}<span>${esc(label || 'Choose image')}</span></button>`;
  }
  const addBtn = (list, label) => `<button type="button" class="tce-add-item" data-add="${list}" contenteditable="false">+ ${esc(label)}</button>`;

  const R = {
    hero(b) {
      const st = b.style;
      const cls = st === 'cover' ? (b.image ? 'cover' : 'none') : st;
      let h = `<section class="tc-block tc-hero tc-hero--${cls}">`;
      if (st === 'cover' && b.image) h += `<img class="tc-hero__bg" src="${esc(mediaUrl(b.image))}" alt="">`;
      h += '<div class="tc-hero__inner"><div class="tc-hero__content">';
      h += ed('p', 'eyebrow', b.eyebrow, 'tc-eyebrow', 'Small label (optional)');
      h += ed('h1', 'title', b.title, 'tc-hero__title', 'Your headline');
      h += ed('p', 'text', b.text, 'tc-hero__text', 'A short introduction', 'multi');
      h += `<p class="tc-actions">${ed('span', 'button', b.button, 'tc-btn tc-btn--solid', 'Button text')}</p>`;
      if (st === 'cover' && !b.image) h += pick('image', '', 'tce-ph--inline', 'Choose background image');
      h += '</div>';
      if (st === 'side') h += `<figure class="tc-hero__media">${pick('image', b.image, '', 'Choose image', b.title)}</figure>`;
      return h + '</div></section>';
    },
    profile: b => `<section class="tc-block tc-profile">${pick('avatar', b.avatar, 'tc-profile__avatar', 'Photo', b.name)}${ed('h1', 'name', b.name, 'tc-profile__name', 'Your name')}${ed('p', 'bio', b.bio, 'tc-profile__bio', 'A short bio', 'multi')}</section>`,
    heading: b => `<div class="tc-block tc-heading tc-heading--${tok(b.level)}">${ed(b.level, 'text', b.text, '', 'Heading')}</div>`,
    text: b => `<div class="tc-block tc-text tc-prose" data-field="html" data-rich contenteditable="true">${b.html || '<p><br></p>'}</div>`,
    quote: b => `<blockquote class="tc-block tc-quote">${ed('p', 'text', b.text, 'tc-quote__text', 'Quote', 'multi')}${ed('cite', 'cite', b.cite, 'tc-quote__cite', 'Author')}</blockquote>`,
    code: b => `<div class="tc-block tc-code">${ed('span', 'lang', b.lang, 'tc-code__lang', 'Label')}<pre>${ed('code', 'code', b.code, '', 'Paste code here', 'code')}</pre></div>`,
    image: b => `<figure class="tc-block tc-image tc-image--${tok(b.size)}">${pick('src', b.src, '', 'Choose image', b.alt)}${ed('figcaption', 'caption', b.caption, '', 'Add a caption (optional)')}</figure>`,
    gallery(b) {
      const items = b.images.map((it, i) => `<figure class="tc-gallery__item">${pick(`images.${i}.src`, it.src, '', 'Choose image', it.alt)}${ed('figcaption', `images.${i}.caption`, it.caption, '', 'Caption')}</figure>`).join('');
      return `<div class="tc-block tc-gallery tc-cols-${tok(b.columns)}">${items}</div>${addBtn('images', 'Add image')}`;
    },
    embed(b) {
      const ok = /youtu\.?be|vimeo\.com/.test(b.url || '');
      return `<figure class="tc-block tc-embed"><div class="tc-embed__frame"><div class="tce-embed-ph" contenteditable="false">${ok ? '▶&nbsp; ' + esc(b.url) : 'Paste a YouTube or Vimeo link in the section settings'}</div></div>${ed('figcaption', 'caption', b.caption, '', 'Caption (optional)')}</figure>`;
    },
    button: b => `<p class="tc-block tc-button">${ed('span', 'text', b.text, 'tc-btn tc-btn--' + b.style, 'Button text')}</p>`,
    links(b) {
      const items = b.items.map((it, i) => `<span class="tc-link">${ed('span', `items.${i}.label`, it.label, '', 'Link label')}</span>`).join('');
      return `<nav class="tc-block tc-links">${items}</nav>${addBtn('items', 'Add link')}`;
    },
    columns(b) {
      const n = Math.max(1, Math.min(4, b.items.length));
      const items = b.items.map((it, i) => `<div class="tc-col">${ed('h3', `items.${i}.title`, it.title, 'tc-col__title', 'Title')}${ed('p', `items.${i}.text`, it.text, 'tc-col__text', 'Text', 'multi')}</div>`).join('');
      return `<div class="tc-block tc-columns tc-cols-${n}">${items}</div>${b.items.length < 4 ? addBtn('items', 'Add column') : ''}`;
    },
    divider: b => `<div class="tc-block tc-divider tc-divider--${tok(b.style)}" role="separator"><span></span></div>`,
    faq(b) {
      const items = b.items.map((it, i) => `<div class="tc-faq__item tce-open">${ed('div', `items.${i}.q`, it.q, 'tc-faq__q', 'Question')}${ed('div', `items.${i}.a`, it.a, 'tc-faq__a', 'Answer', 'multi')}</div>`).join('');
      return `<div class="tc-block tc-faq">${items}</div>${addBtn('items', 'Add question')}`;
    },
    pricing(b) {
      const n = Math.max(1, Math.min(4, b.items.length));
      const items = b.items.map((it, i) => {
        const f = it.featured === 'yes';
        return `<div class="tc-plan${f ? ' tc-plan--featured' : ''}">${ed('h3', `items.${i}.name`, it.name, 'tc-plan__name', 'Plan name')}<p class="tc-plan__price">${ed('span', `items.${i}.price`, it.price, 'tc-plan__amount', '$0')} ${ed('span', `items.${i}.period`, it.period, 'tc-plan__period', '/ month')}</p>${ed('div', `items.${i}.features`, it.features, 'tc-plan__features tce-lines', 'One feature per line', 'multi')}<p class="tc-plan__action">${ed('span', `items.${i}.button`, it.button, 'tc-btn tc-btn--' + (f ? 'solid' : 'outline'), 'Button')}</p></div>`;
      }).join('');
      return `<div class="tc-block tc-pricing tc-cols-${n}">${items}</div>${b.items.length < 4 ? addBtn('items', 'Add plan') : ''}`;
    },
    social(b) {
      const items = b.items.map(it => { const s = D.social[it.network] || D.social.website; return `<span class="tc-social__link" title="${esc(s.label)}">${s.svg}</span>`; }).join('');
      return `<nav class="tc-block tc-social tc-social--${tok(b.size)}">${items || '<span class="tce-hint">Add profiles in the section settings</span>'}</nav>${addBtn('items', 'Add profile')}`;
    },
    contact(b) {
      return `<div class="tc-block tc-contact"><div class="tc-form tce-form" contenteditable="false"><div class="tc-form-row"><label class="tc-field"><span>Name</span><input disabled></label><label class="tc-field"><span>Email</span><input disabled></label></div>${b.phone === 'yes' ? '<label class="tc-field"><span>Phone</span><input disabled></label>' : ''}<label class="tc-field"><span>Message</span><textarea rows="4" disabled></textarea></label></div><p class="tc-form-actions">${ed('span', 'button', b.button, 'tc-btn tc-btn--solid', 'Send message')}</p><p class="tce-hint" contenteditable="false">Messages arrive in Messages. Change the thank-you text in the section settings.</p></div>`;
    },

    /* --- more sections (mirror core/Renderer.php) --- */
    split(b) {
      return `<section class="tc-block tc-split tc-split--${tok(b.side)}"><figure class="tc-split__media">${pick('image', b.image, '', 'Choose image', b.alt)}</figure><div class="tc-split__body">${ed('h2', 'title', b.title, 'tc-split__title', 'Title')}<div class="tc-split__text tc-prose" data-field="html" data-rich contenteditable="true">${b.html || '<p><br></p>'}</div><p class="tc-actions">${ed('span', 'button', b.button, 'tc-btn tc-btn--solid', 'Button text')}</p></div></section>`;
    },
    steps(b) {
      const items = b.items.map((it, i) => `<li class="tc-step"><span class="tc-step__num" aria-hidden="true" contenteditable="false">${i + 1}</span><div class="tc-step__body">${ed('h3', `items.${i}.title`, it.title, 'tc-step__title', 'Step title')}${ed('p', `items.${i}.text`, it.text, 'tc-step__text', 'Describe this step', 'multi')}</div></li>`).join('');
      return `<ol class="tc-block tc-steps tc-cols-${Math.max(1, Math.min(4, b.items.length))}">${items}</ol>${b.items.length < 8 ? addBtn('items', 'Add step') : ''}`;
    },
    tabs(b) {
      // In the editor every panel is shown so all of them can be edited.
      const list = b.items.map((it, i) => `<span class="tc-tabs__tab" aria-selected="${i === 0}">${ed('span', `items.${i}.label`, it.label, '', 'Tab name')}</span>`).join('');
      const panels = b.items.map((it, i) => `<div class="tc-tabs__panel tce-tab-panel"><p class="tce-hint" contenteditable="false">${esc(it.label || 'Tab ' + (i + 1))}</p>${ed('div', `items.${i}.text`, it.text, 'tc-tabs__text', 'Tab content', b.mono === 'yes' ? 'code' : 'multi')}</div>`).join('');
      return `<div class="tc-block tc-tabs is-ready${b.mono === 'yes' ? ' tc-tabs--mono' : ''}"><div class="tc-tabs__list">${list}</div>${panels}</div>${b.items.length < 8 ? addBtn('items', 'Add tab') : ''}`;
    },
    callout(b) {
      return `<aside class="tc-block tc-callout tc-callout--${tok(b.kind)}"><span class="tc-callout__icon" contenteditable="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${D.calloutIcons[b.kind] || ''}</svg></span><div class="tc-callout__body">${ed('p', 'title', b.title, 'tc-callout__title', 'Title')}${ed('p', 'text', b.text, 'tc-callout__text', 'Text', 'multi')}</div></aside>`;
    },
    table(b) {
      const cell = v => { const l = v.trim().toLowerCase(); return ['yes', 'y', '✓', 'true'].includes(l) ? '<span class="tc-yes">✓</span>' : ['no', 'n', '-', '—', 'false'].includes(l) ? '<span class="tc-no">✕</span>' : esc(v.trim()); };
      const head = b.header ? b.header.split('|') : [];
      const rows = (b.rows || '').split('\n').filter(r => r.trim());
      const body = rows.map(r => '<tr>' + r.split('|').slice(0, 12).map((c, i) => i === 0 && b.firstcol === 'yes' ? `<th>${esc(c.trim())}</th>` : `<td>${cell(c)}</td>`).join('') + '</tr>').join('');
      return `<div class="tc-block tc-table-wrap${b.firstcol === 'yes' ? ' tc-table--firstcol' : ''}">${ed('p', 'title', b.title, 'tc-table__title', 'Table title (optional)')}<div class="tc-table-scroll" contenteditable="false"><table class="tc-table">${head.length ? '<thead><tr>' + head.map(c => `<th>${esc(c.trim())}</th>`).join('') + '</tr></thead>' : ''}<tbody>${body}</tbody></table></div><p class="tce-hint" contenteditable="false">Edit the rows in the section settings: one row per line, cells separated with |</p></div>`;
    },
    events(b) {
      const items = b.items.map((it, i) => `<div class="tc-event"><div class="tc-event__date">${ed('span', `items.${i}.date`, it.date, '', 'Date')}${ed('small', `items.${i}.time`, it.time, '', 'Time')}</div><div class="tc-event__body">${ed('h3', `items.${i}.title`, it.title, 'tc-event__title', 'Event name')}${ed('p', `items.${i}.place`, it.place, 'tc-event__place', 'Place')}</div>${it.url ? `<span class="tc-btn tc-btn--outline tc-event__btn">${esc(b.linkText || 'Details')}</span>` : ''}</div>`).join('');
      return `<div class="tc-block tc-events">${items}</div>${addBtn('items', 'Add event')}`;
    },
    stats(b) {
      const items = b.items.map((it, i) => `<div class="tc-stat">${ed('span', `items.${i}.value`, it.value, 'tc-stat__value', '0')}${ed('span', `items.${i}.label`, it.label, 'tc-stat__label', 'Label')}</div>`).join('');
      return `<div class="tc-block tc-stats tc-cols-${Math.max(1, Math.min(6, b.items.length))}">${items}</div>${b.items.length < 6 ? addBtn('items', 'Add number') : ''}`;
    },
    cta(b) {
      return `<section class="tc-block tc-cta tc-cta--${tok(b.style)}"><div class="tc-cta__inner">${ed('h2', 'title', b.title, 'tc-cta__title', 'Title')}${ed('p', 'text', b.text, 'tc-cta__text', 'A short line of text', 'multi')}<p class="tc-actions">${ed('span', 'button', b.button, 'tc-btn tc-cta__btn', 'Button text')}</p></div></section>`;
    },
    logos(b) {
      const items = b.items.map((it, i) => `<span class="tc-logo">${pick(`items.${i}.image`, it.image, '', 'Logo', it.alt)}</span>`).join('');
      return `<div class="tc-block tc-logos${b.mono === 'yes' ? ' tc-logos--mono' : ''}">${ed('p', 'title', b.title, 'tc-logos__title', 'Title (optional)')}<div class="tc-logos__row">${items}</div></div>${addBtn('items', 'Add logo')}`;
    },
    team(b) {
      const items = b.items.map((it, i) => `<div class="tc-member">${pick(`items.${i}.photo`, it.photo, 'tc-member__photo', 'Photo', it.name)}${ed('h3', `items.${i}.name`, it.name, 'tc-member__name', 'Name')}${ed('p', `items.${i}.role`, it.role, 'tc-member__role', 'Role')}</div>`).join('');
      return `<div class="tc-block tc-team tc-cols-${tok(b.columns)}">${items}</div>${addBtn('items', 'Add person')}`;
    },
    products(b) {
      const items = b.items.map((it, i) => `<article class="tc-product"><figure class="tc-product__media">${pick(`items.${i}.image`, it.image, '', 'Product image', it.name)}</figure><div class="tc-product__body">${ed('h3', `items.${i}.name`, it.name, 'tc-product__name', 'Product name')}${ed('p', `items.${i}.price`, it.price, 'tc-product__price', 'Price')}${ed('p', `items.${i}.desc`, it.desc, 'tc-product__desc', 'Short description', 'multi')}<p class="tc-product__action">${ed('span', `items.${i}.button`, it.button, 'tc-btn tc-btn--solid', 'Button')}</p></div></article>`).join('');
      return `<div class="tc-block tc-products tc-cols-${tok(b.columns)}">${items}</div>${addBtn('items', 'Add product')}`;
    },
    compare(b) {
      return `<figure class="tc-block tc-compare tce-compare"><div class="tce-compare-pair"><div>${pick('before', b.before, '', 'Before image', b.beforeLabel)}${ed('span', 'beforeLabel', b.beforeLabel, 'tc-compare__label', 'Before')}</div><div>${pick('after', b.after, '', 'After image', b.afterLabel)}${ed('span', 'afterLabel', b.afterLabel, 'tc-compare__label', 'After')}</div></div>${ed('figcaption', 'caption', b.caption, '', 'Caption (optional)')}<p class="tce-hint" contenteditable="false">On the live page visitors drag a handle across the two images.</p></figure>`;
    },
    announce(b) {
      return `<div class="tc-block tc-announce tc-announce--${tok(b.style)}"><p class="tc-announce__inner">${ed('span', 'text', b.text, 'tc-announce__text', 'Your announcement')} ${ed('span', 'linkText', b.linkText, 'tc-announce__link', 'Link text')}</p></div>`;
    },
    newsletter(b) {
      return `<div class="tc-block tc-newsletter">${ed('h2', 'title', b.title, 'tc-newsletter__title', 'Title')}${ed('p', 'text', b.text, 'tc-newsletter__text', 'A short line of text', 'multi')}<div class="tc-form tc-newsletter__form tce-form"><label class="tc-newsletter__field" contenteditable="false"><input disabled placeholder="you@example.com"></label>${ed('span', 'button', b.button, 'tc-btn tc-btn--solid', 'Subscribe')}</div><p class="tce-hint" contenteditable="false">Sign-ups appear in Messages → Subscribers.</p></div>`;
    },
    popup(b) {
      // Shown here as a small preview card; visitors see it over the page, at the moment and as often as chosen.
      const f = S.popup.fields;
      const opt = k => esc((f[k].options || {})[b[k]] || '');
      return `<div class="tc-block tce-popup tce-popup--${tok(b.kind)}"><p class="tce-popup__meta" contenteditable="false"><b>Popup</b> ${opt('kind')} · ${opt('trigger')} · ${opt('often')}</p>`
        + `${ed('p', 'title', b.title, 'tce-popup__title', 'Title')}${ed('p', 'text', b.text, 'tce-popup__text', 'Message', 'multi')}${ed('span', 'button', b.button, 'tc-btn tc-btn--solid', 'Button text')}`
        + `<p class="tce-hint" contenteditable="false">Visitors see this over the page. It takes no space on the page itself.</p></div>`;
    },
  };

  const BAR_IC = {
    up: '<path d="m6 15 6-6 6 6"/>', down: '<path d="m6 9 6 6 6-6"/>',
    dup: '<rect x="8" y="8" width="12" height="12" rx="1.5"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
    del: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>', add: '<path d="M12 5v14M5 12h14"/>',
  };
  const barBtn = (act, title) => `<button type="button" data-act="${act}" title="${title}"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${BAR_IC[act]}</svg></button>`;

  /* Section placement (mirrors Renderer::placement) */
  function place(html, b) {
    let cls = '';
    if (b.boxAlign && b.boxAlign in D.placement.align) cls += ' tc-a-' + b.boxAlign;
    if (b.boxWidth && b.boxWidth in D.placement.width) cls += ' tc-w-' + b.boxWidth;
    return cls ? html.replace('class="tc-block', 'class="tc-block' + cls) : html;
  }

  const AL_IC = {
    left: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 10h10M4 14h16M4 18h10"/></svg>',
    center: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M7 10h10M4 14h16M7 18h10"/></svg>',
    right: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M10 10h10M4 14h16M10 18h10"/></svg>',
  };
  function placementControl(b) {
    const wrap = document.createElement('div');
    wrap.className = 'placement';
    const seg = (key, title, opts, icons) => `<div class="field"><span>${title}</span><div class="seg" role="radiogroup" aria-label="${title}" data-key="${key}">` +
      Object.entries(opts).map(([v, l]) => `<button type="button" role="radio" aria-checked="${(b[key] || '') === v}" data-v="${v}" title="${esc(l)}">${icons[v] || esc(v === '' ? 'Auto' : l.replace(' width', ''))}</button>`).join('') + `</div></div>`;
    wrap.innerHTML = '<p class="pane-label">Placement</p>' + seg('boxAlign', 'Position', D.placement.align, AL_IC) + seg('boxWidth', 'Width', D.placement.width, {}) +
      '<p class="hint">Choose Narrow or Medium to make the section smaller, then use Position to move it left, center or right.</p>';
    wrap.addEventListener('click', e => {
      const btn = e.target.closest('[data-v]');
      if (!btn) return;
      const key = btn.closest('[data-key]').dataset.key;
      if (btn.dataset.v) b[key] = btn.dataset.v; else delete b[key];
      rerender(b);
      wrap.replaceWith(placementControl(b));
      markDirty();
    });
    return wrap;
  }

  function makeBlockEl(b) {
    const el = cdoc.createElement('div');
    el.className = 'tce-block';
    el.dataset.id = b.id;
    const def = S[b.type];
    let body;
    if (!def) {
      body = `<div class="tce-empty tce-missing">This section comes from a plugin that is switched off or removed. It is hidden on your site and will be removed when you save.</div>`;
    } else if (R[b.type]) {
      body = place(R[b.type](b), b);
    } else {
      // Plugin section: show a cleaned preview rendered by the server.
      body = `<div class="tce-plugin-preview" data-plugin-preview contenteditable="false">${previewCache[b.id] || '<p class="tce-hint">Loading preview…</p>'}</div>`;
      pluginPreview(b);
    }
    el.innerHTML = `<div class="tce-bar" contenteditable="false"><span class="tce-label">${esc(def ? def.label : 'Unavailable')}</span>${barBtn('up', 'Move up')}${barBtn('down', 'Move down')}${barBtn('dup', 'Duplicate')}${barBtn('add', 'Add section below')}${barBtn('del', 'Delete')}</div>` + body;
    return el;
  }

  const previewCache = {}, previewTimers = {};
  function pluginPreview(b) {
    clearTimeout(previewTimers[b.id]);
    previewTimers[b.id] = setTimeout(async () => {
      const r = await T.api('render_block', { block: b });
      previewCache[b.id] = r && r.ok ? r.html : `<p class="tce-hint">${esc((r && r.error) || 'Preview unavailable')}</p>`;
      const box = root && root.querySelector(`.tce-block[data-id="${tok(b.id)}"] [data-plugin-preview]`);
      if (box) box.innerHTML = previewCache[b.id];
    }, 250);
  }

  function renderAll() {
    if (!root) return;
    root.innerHTML = '';
    if (!page.blocks.length) {
      root.innerHTML = '<div class="tce-empty">This page is empty.<br>Choose a section from the <strong>Add</strong> panel to begin.</div>';
    }
    page.blocks.forEach(b => root.appendChild(makeBlockEl(b)));
    markSelected();
    renderOutline();
  }

  function rerender(b) {
    if (!root) return;
    const old = root.querySelector(`.tce-block[data-id="${tok(b.id)}"]`);
    if (!old) return renderAll();
    old.replaceWith(makeBlockEl(b));
    markSelected();
    renderOutline();
  }

  function markSelected() {
    if (!root) return;
    $$('.tce-block', root).forEach(el => el.classList.toggle('is-selected', el.dataset.id === selected));
    $$('[data-outline] li').forEach(li => li.classList.toggle('active', li.dataset.id === selected));
  }

  /* ---------- state changes ---------- */
  function markDirty() {
    dirty = true;
    version++;
    $('[data-save-state]').textContent = 'Unsaved changes';
    $('[data-save]').classList.add('pulse');
    clearTimeout(snapTimer);
    snapTimer = setTimeout(snapshot, 400);
  }

  function snapshot() {
    clearTimeout(snapTimer);
    const cur = JSON.stringify(page.blocks);
    if (cur === lastSnap) return;
    undoStack.push(lastSnap);
    if (undoStack.length > 100) undoStack.shift();
    lastSnap = cur;
    redoStack.length = 0;
    updateUndoButtons();
  }

  function updateUndoButtons() {
    $('[data-undo]').disabled = !undoStack.length;
    $('[data-redo]').disabled = !redoStack.length;
  }

  function undo(redo) {
    snapshot();
    const from = redo ? redoStack : undoStack;
    const to = redo ? undoStack : redoStack;
    if (!from.length) return;
    to.push(lastSnap);
    lastSnap = from.pop();
    page.blocks = JSON.parse(lastSnap);
    renderAll();
    buildInspector();
    updateUndoButtons();
    dirty = true; version++;
    $('[data-save-state]').textContent = 'Unsaved changes';
    $('[data-save]').classList.add('pulse');
  }

  function select(id, showBlockTab) {
    selected = id;
    selectFooter(false);
    markSelected();
    buildInspector();
    if (showBlockTab && id) showTab('block');
  }

  function addBlock(type) {
    const b = Object.assign({}, clone(S[type].defaults), { id: uid(), type }); // defaults can never replace the id or type
    const at = selected ? indexOf(selected) + 1 : page.blocks.length;
    page.blocks.splice(at < 0 ? page.blocks.length : at, 0, b);
    renderAll();
    select(b.id, true);
    markDirty();
    const el = root.querySelector(`.tce-block[data-id="${tok(b.id)}"]`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function blockAction(act, id) {
    const i = indexOf(id);
    if (i < 0) return;
    if (act === 'up' && i > 0) [page.blocks[i - 1], page.blocks[i]] = [page.blocks[i], page.blocks[i - 1]];
    else if (act === 'down' && i < page.blocks.length - 1) [page.blocks[i + 1], page.blocks[i]] = [page.blocks[i], page.blocks[i + 1]];
    else if (act === 'dup') {
      const c = clone(page.blocks[i]);
      c.id = uid();
      page.blocks.splice(i + 1, 0, c);
      selected = c.id;
    } else if (act === 'del') {
      page.blocks.splice(i, 1);
      if (selected === id) selected = null;
      T.toast('Section deleted. Press Undo to bring it back.');
    } else if (act === 'add') {
      select(id);
      showTab('add');
      return;
    } else return;
    renderAll();
    buildInspector();
    markDirty();
    const el = selected && root.querySelector(`.tce-block[data-id="${selected}"]`);
    if (el && act !== 'del') el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  /* ---------- inline editing ---------- */
  function readField(el) {
    if (el.hasAttribute('data-rich')) {
      return el.textContent.trim() === '' ? '' : el.innerHTML;
    }
    let t = el.innerText;
    if (el.hasAttribute('data-code')) return t.replace(/\n$/, '');
    if (!el.hasAttribute('data-multi')) t = t.replace(/\s*\n\s*/g, ' ');
    return t.replace(/\n$/, '');
  }

  function onCanvasInput(e) {
    const ft = e.target.closest && e.target.closest('[data-footer-text]');
    if (ft) {
      if (ft.textContent === '') ft.innerHTML = '';
      FT.text = ft.textContent.replace(/\s*\n\s*/g, ' ').slice(0, 300);
      syncFooter(true);
      footerChanged();
      return;
    }
    const el = e.target.closest && e.target.closest('[data-field]');
    const blockEl = el && el.closest('.tce-block');
    if (!el || !blockEl) return;
    const b = page.blocks.find(x => x.id === blockEl.dataset.id);
    if (!b) return;
    if (!el.hasAttribute('data-rich') && el.textContent === '') el.innerHTML = '';
    const path = el.dataset.field;
    const val = readField(el);
    setPath(b, path, val);
    const inp = $(`[data-inspector] [data-path="${CSS.escape(path)}"]`);
    if (inp && b.id === selected && document.activeElement !== inp) inp.value = val;
    markDirty();
    renderOutlineLabel(b);
  }

  /* rich text toolbar inside the canvas */
  let rt = null, rtTarget = null, rtHide = null;
  function buildRichToolbar() {
    rt = cdoc.createElement('div');
    rt.className = 'tce-rt';
    rt.setAttribute('contenteditable', 'false');
    const cmds = [['bold', '<b>B</b>', 'Bold'], ['italic', '<i>I</i>', 'Italic'], ['link', 'Link', 'Add or remove link'], ['h2', 'H2', 'Large heading'], ['h3', 'H3', 'Small heading'], ['p', '¶', 'Paragraph'], ['ul', '•&nbsp;List', 'Bullet list'], ['ol', '1.&nbsp;List', 'Numbered list'], ['quote', '“ ”', 'Quote'], ['clear', 'Clear', 'Clear formatting']];
    rt.innerHTML = cmds.map(([c, l, t]) => `<button type="button" data-cmd="${c}" title="${t}">${l}</button>`).join('');
    rt.addEventListener('mousedown', e => e.preventDefault());
    rt.addEventListener('click', e => {
      const btn = e.target.closest('[data-cmd]');
      if (!btn || !rtTarget) return;
      const c = btn.dataset.cmd;
      const ex = (cmd, val) => cdoc.execCommand(cmd, false, val);
      if (c === 'bold' || c === 'italic') ex(c);
      else if (c === 'link') {
        const sel = cdoc.getSelection();
        const inLink = sel && sel.anchorNode && sel.anchorNode.parentElement && sel.anchorNode.parentElement.closest('a');
        const url = window.prompt('Link address, for example https://example.com, /about or mailto:you@example.com\n(leave empty to remove the link)', inLink ? inLink.getAttribute('href') : 'https://');
        if (url === null) return;
        if (url.trim() === '' ) ex('unlink');
        else if (/^\s*(javascript|data|vbscript):/i.test(url)) { T.toast('That kind of link is not allowed', 'error'); return; }
        else ex('createLink', url.trim());
      } else if (c === 'h2' || c === 'h3' || c === 'p') ex('formatBlock', '<' + c + '>');
      else if (c === 'quote') ex('formatBlock', '<blockquote>');
      else if (c === 'ul') ex('insertUnorderedList');
      else if (c === 'ol') ex('insertOrderedList');
      else if (c === 'clear') { ex('removeFormat'); ex('unlink'); ex('formatBlock', '<p>'); }
      rtTarget.dispatchEvent(new Event('input', { bubbles: true }));
      placeRichToolbar();
    });
    cdoc.body.appendChild(rt);
  }
  function placeRichToolbar() {
    if (!rt || !rtTarget) return;
    const r = rtTarget.getBoundingClientRect();
    const top = Math.max(4, r.top + cdoc.defaultView.scrollY - rt.offsetHeight - 8);
    rt.style.top = top + 'px';
    rt.style.left = Math.max(8, r.left + cdoc.defaultView.scrollX) + 'px';
  }

  function onCanvasClick(e) {
    const a = e.target.closest('a');
    if (a) e.preventDefault();
    if (e.target.closest('[data-footer]')) {
      if (selected) select(null);
      selectFooter(true);
      showTab('footer');
      return;
    }
    const blockEl = e.target.closest('.tce-block');
    const actBtn = e.target.closest('[data-act]');
    if (actBtn && blockEl) {
      e.preventDefault();
      blockAction(actBtn.dataset.act, blockEl.dataset.id);
      return;
    }
    if (blockEl && blockEl.dataset.id !== selected) select(blockEl.dataset.id, true);
    else if (blockEl) showTab('block');
    const pk = e.target.closest('[data-pick]');
    if (pk && blockEl) {
      e.preventDefault();
      pickImage(blockEl.dataset.id, pk.dataset.pick);
      return;
    }
    const add = e.target.closest('[data-add]');
    if (add && blockEl) {
      const b = page.blocks.find(x => x.id === blockEl.dataset.id);
      const def = S[b.type].fields[add.dataset.add];
      if (b[add.dataset.add].length >= (def.max || 50)) return;
      b[add.dataset.add].push(blankItem(def));
      rerender(b);
      buildInspector();
      markDirty();
    }
  }

  function pickImage(blockId, path) {
    T.openPicker(item => {
      const b = page.blocks.find(x => x.id === blockId);
      if (!b) return;
      setPath(b, path, item ? item.ref : '');
      // fill in empty alt text from the media library description
      if (item && item.alt) {
        const altPath = path.replace(/(src|image|avatar)$/, 'alt');
        if (altPath !== path && getPath(b, altPath) === '') setPath(b, altPath, item.alt);
      }
      // choosing an image for a hero with no image style switches to "beside"
      if (b.type === 'hero' && path === 'image' && item && b.style === 'none') b.style = 'side';
      rerender(b);
      if (b.id === selected) buildInspector();
      markDirty();
    });
  }

  function canvasKeys(e) {
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
    const el = e.target.closest && (e.target.closest('[data-field]') || e.target.closest('[data-footer-text]'));
    if (e.key === 'Enter' && el && !el.hasAttribute('data-multi') && !el.hasAttribute('data-rich')) e.preventDefault();
  }

  function canvasPaste(e) {
    const el = e.target.closest && (e.target.closest('[data-field]') || e.target.closest('[data-footer-text]'));
    if (!el) return;
    e.preventDefault();
    let text = (e.clipboardData || window.clipboardData).getData('text/plain');
    if (!el.hasAttribute('data-multi') && !el.hasAttribute('data-rich')) text = text.replace(/\s*\n\s*/g, ' ');
    cdoc.execCommand('insertText', false, text);
  }

  function initCanvas() {
    if (cdoc) return;
    cdoc = frame.contentDocument;
    root = cdoc.getElementById('tce-root');
    if (!root) { cdoc = null; return; }
    cdoc.addEventListener('click', onCanvasClick);
    cdoc.addEventListener('input', onCanvasInput);
    cdoc.addEventListener('keydown', canvasKeys);
    cdoc.addEventListener('paste', canvasPaste);
    cdoc.addEventListener('submit', e => e.preventDefault());
    cdoc.addEventListener('focusin', e => {
      const r = e.target.closest && e.target.closest('[data-rich]');
      clearTimeout(rtHide);
      if (r) { rtTarget = r; rt.classList.add('show'); placeRichToolbar(); }
    });
    cdoc.addEventListener('focusout', () => {
      rtHide = setTimeout(() => { rt.classList.remove('show'); rtTarget = null; }, 150);
    });
    buildRichToolbar();
    applyPageLook();
    renderAll();
  }

  frame.addEventListener('load', initCanvas);
  if (frame.contentDocument && frame.contentDocument.readyState === 'complete') initCanvas();

  /* ---------- side panel: tabs ---------- */
  function showTab(name) {
    $$('.ed-tabs [data-tab]').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    $$('[data-pane]').forEach(p => { p.hidden = p.dataset.pane !== name; });
  }
  $$('.ed-tabs [data-tab]').forEach(b => b.addEventListener('click', () => showTab(b.dataset.tab)));

  /* palette */
  (function buildPalette() {
    const groups = {};
    Object.entries(S).forEach(([type, def]) => { (groups[def.group] = groups[def.group] || []).push([type, def]); });
    const box = $('[data-palette]');
    box.innerHTML = Object.entries(groups).map(([g, items]) => `<p class="pane-label">${esc(g)}</p><div class="palette">${items.map(([type, def]) => `<button type="button" class="palette-item" data-type="${type}"><strong>${esc(def.label)}</strong><small>${esc(def.desc)}</small></button>`).join('')}</div>`).join('');
    box.addEventListener('click', e => { const it = e.target.closest('[data-type]'); if (it) addBlock(it.dataset.type); });
  })();

  /* outline with drag & drop */
  function renderOutline() {
    const ol = $('[data-outline]');
    ol.innerHTML = page.blocks.map(b => `<li draggable="true" data-id="${tok(b.id)}" class="${b.id === selected ? 'active' : ''}"><span class="grip" aria-hidden="true">⋮⋮</span><span class="ol-text"><strong>${esc((S[b.type] || { label: 'Unavailable' }).label)}</strong><small>${esc(summary(b))}</small></span></li>`).join('') || '<li class="muted ol-empty">No sections yet</li>';
  }
  function renderOutlineLabel(b) {
    const li = $(`[data-outline] li[data-id="${tok(b.id)}"] small`);
    if (li) li.textContent = summary(b);
  }
  (function outlineEvents() {
    const ol = $('[data-outline]');
    let dragId = null;
    ol.addEventListener('click', e => {
      const li = e.target.closest('li[data-id]');
      if (!li) return;
      select(li.dataset.id);
      const el = root && root.querySelector(`.tce-block[data-id="${li.dataset.id}"]`);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    ol.addEventListener('dragstart', e => { const li = e.target.closest('li[data-id]'); if (!li) return; dragId = li.dataset.id; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', dragId); });
    ol.addEventListener('dragend', () => { dragId = null; $$('li', ol).forEach(l => l.classList.remove('dragging', 'drop-before', 'drop-after')); });
    ol.addEventListener('dragover', e => {
      const li = e.target.closest('li[data-id]');
      if (!li || !dragId) return;
      e.preventDefault();
      const after = e.clientY > li.getBoundingClientRect().top + li.offsetHeight / 2;
      $$('li', ol).forEach(l => l.classList.remove('drop-before', 'drop-after'));
      li.classList.add(after ? 'drop-after' : 'drop-before');
    });
    ol.addEventListener('drop', e => {
      const li = e.target.closest('li[data-id]');
      if (!li || !dragId || li.dataset.id === dragId) return;
      e.preventDefault();
      const after = li.classList.contains('drop-after');
      const from = indexOf(dragId);
      const [moved] = page.blocks.splice(from, 1);
      let to = indexOf(li.dataset.id) + (after ? 1 : 0);
      page.blocks.splice(to, 0, moved);
      renderAll();
      markDirty();
    });
  })();

  /* ---------- inspector ---------- */
  const linkList = document.createElement('datalist');
  linkList.id = 'page-links';
  linkList.innerHTML = D.pageLinks.map(l => `<option value="${esc(l.url)}">${esc(l.title)}</option>`).join('');
  document.body.appendChild(linkList);

  function control(b, path, def) {
    const wrap = document.createElement('div');
    wrap.className = 'field';
    const label = `<span>${esc(def.label)}</span>`;
    const val = getPath(b, path);
    const onVal = (v, full) => {
      setPath(b, path, v);
      const el = root && root.querySelector(`.tce-block[data-id="${tok(b.id)}"] [data-field="${path}"]`);
      if (!full && el && !el.hasAttribute('data-rich')) { el.textContent = v; }
      else rerender(b);
      renderOutlineLabel(b);
      markDirty();
    };
    switch (def.type) {
      case 'text':
      case 'url': {
        wrap.innerHTML = `<label>${label}<input type="text" maxlength="${def.max || 2000}" data-path="${esc(path)}" ${def.type === 'url' ? 'list="page-links" placeholder="https://… or choose a page" spellcheck="false"' : ''}></label>`;
        const inp = $('input', wrap);
        inp.value = val || '';
        inp.addEventListener('input', () => onVal(inp.value, def.type === 'url'));
        if (def.type === 'url') wrap.insertAdjacentHTML('beforeend', '<small class="hint">Web address, a page of your site, mailto: or tel: link.</small>');
        break;
      }
      case 'textarea':
      case 'code': {
        wrap.innerHTML = `<label>${label}<textarea rows="${def.type === 'code' ? 8 : 3}" data-path="${esc(path)}" ${def.type === 'code' ? 'class="code-input" spellcheck="false"' : ''}></textarea></label>`;
        const ta = $('textarea', wrap);
        ta.value = val || '';
        ta.addEventListener('input', () => onVal(ta.value));
        break;
      }
      case 'rich':
        if (!R[b.type]) {
          wrap.innerHTML = `<label>${label}<textarea rows="5" data-path="${esc(path)}"></textarea></label><small class="hint">Basic formatting allowed: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;a href&gt;, lists.</small>`;
          const ta = $('textarea', wrap);
          ta.value = val || '';
          ta.addEventListener('input', () => onVal(ta.value, true));
          break;
        }
        wrap.innerHTML = `${label}<p class="hint">Type directly on the page. Select words to make them bold, italic or a link, or to add headings and lists.</p>`;
        break;
      case 'select': {
        wrap.innerHTML = `<label>${label}<select data-path="${esc(path)}">${Object.entries(def.options).map(([k, l]) => `<option value="${esc(k)}">${esc(l)}</option>`).join('')}</select></label>`;
        const sel = $('select', wrap);
        sel.value = val;
        sel.addEventListener('change', () => onVal(sel.value, true));
        break;
      }
      case 'image': {
        wrap.className = 'field insp-image';
        wrap.innerHTML = `${label}<div class="insp-image-row"><div class="insp-thumb">${val ? `<img src="${esc(mediaUrl(val))}" alt="">` : IMG_IC}</div><div class="insp-image-btns"><button type="button" class="btn btn-ghost btn-sm" data-choose>${val ? 'Change' : 'Choose'}…</button>${val ? '<button type="button" class="btn btn-ghost btn-sm" data-clear>Remove</button>' : ''}</div></div>`;
        $('[data-choose]', wrap).addEventListener('click', () => pickImage(b.id, path));
        const clr = $('[data-clear]', wrap);
        if (clr) clr.addEventListener('click', () => { setPath(b, path, ''); rerender(b); buildInspector(); markDirty(); });
        break;
      }
      case 'list': {
        wrap.className = 'field insp-list';
        wrap.innerHTML = `<span>${esc(def.label)}</span>`;
        const items = val || [];
        items.forEach((it, i) => {
          const box = document.createElement('div');
          box.className = 'insp-item';
          box.innerHTML = `<div class="insp-item-head"><strong>${i + 1}</strong><span class="insp-item-actions"><button type="button" class="icon-btn" data-mv="-1" title="Move up" ${i === 0 ? 'disabled' : ''}>↑</button><button type="button" class="icon-btn" data-mv="1" title="Move down" ${i === items.length - 1 ? 'disabled' : ''}>↓</button><button type="button" class="icon-btn danger" data-rm title="Remove">✕</button></span></div>`;
          Object.entries(def.item).forEach(([k, sub]) => box.appendChild(control(b, `${path}.${i}.${k}`, sub)));
          $('[data-rm]', box).addEventListener('click', () => { items.splice(i, 1); rerender(b); buildInspector(); markDirty(); });
          $$('[data-mv]', box).forEach(btn => btn.addEventListener('click', () => {
            const j = i + Number(btn.dataset.mv);
            [items[i], items[j]] = [items[j], items[i]];
            rerender(b); buildInspector(); markDirty();
          }));
          wrap.appendChild(box);
        });
        if (items.length < (def.max || 50)) {
          const add = document.createElement('button');
          add.type = 'button';
          add.className = 'btn btn-ghost btn-sm btn-block';
          add.textContent = '+ Add ' + (def.label.toLowerCase().replace(/s$/, ''));
          add.addEventListener('click', () => { items.push(blankItem(def)); rerender(b); buildInspector(); markDirty(); });
          wrap.appendChild(add);
        }
        break;
      }
    }
    return wrap;
  }

  function buildInspector() {
    const box = $('[data-inspector]');
    const b = current();
    if (!b) { box.innerHTML = '<p class="empty">Click a section on the page to edit its settings.</p>'; return; }
    const def = S[b.type];
    if (!def) {
      box.innerHTML = '<p class="empty">This section comes from a plugin that is switched off. Delete it, or ask an owner to switch the plugin on.</p>';
      return;
    }
    const scroll = box.parentElement.scrollTop;
    box.innerHTML = `<div class="insp-head"><strong>${esc(def.label)}</strong><small class="muted">${esc(def.desc)}</small></div>`;
    if (b.type !== 'popup') box.appendChild(placementControl(b)); // a popup floats over the page, so it has no placement
    const fieldsLabel = document.createElement('p');
    fieldsLabel.className = 'pane-label';
    fieldsLabel.textContent = 'Content';
    box.appendChild(fieldsLabel);
    Object.entries(def.fields).forEach(([name, f]) => box.appendChild(control(b, name, f)));
    const actions = document.createElement('div');
    actions.className = 'insp-actions';
    actions.innerHTML = '<button type="button" class="btn btn-ghost btn-sm" data-a="up">↑ Up</button><button type="button" class="btn btn-ghost btn-sm" data-a="down">↓ Down</button><button type="button" class="btn btn-ghost btn-sm" data-a="dup">Duplicate</button><button type="button" class="btn btn-ghost btn-sm danger" data-a="del">Delete</button>';
    actions.addEventListener('click', e => { const a = e.target.closest('[data-a]'); if (a) blockAction(a.dataset.a, b.id); });
    box.appendChild(actions);
    box.parentElement.scrollTop = scroll;
  }

  /* ---------- page settings ---------- */
  function applyPageLook() {
    if (!cdoc) return;
    const align = page.layout || D.siteAlign;
    cdoc.body.className = cdoc.body.className.replace(/\balign-\w+/, 'align-' + align);
    cdoc.body.classList.toggle('tc-no-header', !!page.hideHeader);
    cdoc.body.classList.toggle('tc-no-footer', !!page.hideFooter);
    syncFooter();
  }

  /* ---------- footer (one text for the whole site; each page can hide it) ---------- */
  function footerEl() { return cdoc ? cdoc.querySelector('[data-footer]') : null; }
  function selectFooter(on) { const el = footerEl(); if (el) el.classList.toggle('is-selected', !!on); }
  function syncFooter(fromCanvas) {
    const el = footerEl();
    if (el) {
      ['left', 'center', 'right'].forEach(a => el.classList.toggle('tc-footer--' + a, FT.align === a));
      const t = el.querySelector('[data-footer-text]');
      if (!fromCanvas && t && t.textContent !== FT.text) t.textContent = FT.text;
      const x = el.querySelector('[data-footer-extra]');
      if (x) x.innerHTML = footerExtrasHtml();
    }
    if (footerInput && document.activeElement !== footerInput) footerInput.value = FT.text;
    $$('[data-footer-align] [data-v]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.v === FT.align)));
  }
  function footerChanged() { footerDirty = true; markDirty(); }

  // Footer links and social icons (mirrors Renderer::footerExtras; the server cleans everything again on save)
  const SOCIAL = D.social || {};
  function footerExtrasHtml() {
    const links = FT.links.filter(l => l.label && l.url).map(l => `<a href="#">${esc(l.label)}</a>`).join('');
    const icons = FT.social.filter(s => s.url && SOCIAL[s.network]).map(s => `<a href="#" title="${esc(SOCIAL[s.network].label)}">${SOCIAL[s.network].svg}</a>`).join('');
    return (links ? `<nav class="tc-footer__links">${links}</nav>` : '') + (icons ? `<nav class="tc-footer__social">${icons}</nav>` : '');
  }
  const linkBox = $('[data-footer-links]');
  const socialBox = $('[data-footer-social]');
  const removeBtn = '<button type="button" class="icon-btn footer-row__del" data-del aria-label="Remove" title="Remove">×</button>';
  function buildFooterLists() {
    if (linkBox) linkBox.innerHTML = FT.links.map((l, i) => `<div class="footer-row" data-i="${i}"><input data-k="label" maxlength="40" placeholder="Label" aria-label="Link label" value="${esc(l.label)}"><input data-k="url" maxlength="500" list="page-links" placeholder="https://… or /page" aria-label="Link address" value="${esc(l.url)}">${removeBtn}</div>`).join('');
    if (socialBox) socialBox.innerHTML = FT.social.map((s, i) => `<div class="footer-row" data-i="${i}"><select data-k="network" aria-label="Network">${Object.entries(SOCIAL).map(([k, v]) => `<option value="${esc(k)}"${k === s.network ? ' selected' : ''}>${esc(v.label)}</option>`).join('')}</select><input data-k="url" maxlength="500" placeholder="https://…" aria-label="Profile address" value="${esc(s.url)}">${removeBtn}</div>`).join('');
    const addLink = $('[data-footer-add-link]'), addSocial = $('[data-footer-add-social]');
    if (addLink) addLink.disabled = FT.links.length >= 6;
    if (addSocial) addSocial.disabled = FT.social.length >= 8;
  }
  [[linkBox, 'links'], [socialBox, 'social']].forEach(([box, list]) => {
    if (!box) return;
    const update = e => {
      const row = e.target.closest('.footer-row');
      if (!row || !e.target.dataset.k) return;
      FT[list][+row.dataset.i][e.target.dataset.k] = e.target.value;
      syncFooter();
      footerChanged();
    };
    box.addEventListener('input', update);
    box.addEventListener('change', update);
    box.addEventListener('click', e => {
      if (!e.target.closest('[data-del]')) return;
      FT[list].splice(+e.target.closest('.footer-row').dataset.i, 1);
      buildFooterLists();
      syncFooter();
      footerChanged();
    });
  });
  const addLinkBtn = $('[data-footer-add-link]');
  if (addLinkBtn) addLinkBtn.addEventListener('click', () => {
    if (FT.links.length >= 6) return;
    FT.links.push({ label: '', url: '' });
    buildFooterLists();
    $('.footer-row:last-child input', linkBox).focus();
  });
  const addSocialBtn = $('[data-footer-add-social]');
  if (addSocialBtn) addSocialBtn.addEventListener('click', () => {
    if (FT.social.length >= 8) return;
    FT.social.push({ network: 'instagram', url: '' });
    buildFooterLists();
    $('.footer-row:last-child input', socialBox).focus();
  });
  buildFooterLists();
  if (footerInput) footerInput.addEventListener('input', () => {
    FT.text = footerInput.value.replace(/\s*\n\s*/g, ' ').slice(0, 300);
    syncFooter(false);
    footerChanged();
  });
  $$('[data-footer-align] [data-v]').forEach(b => b.addEventListener('click', () => { FT.align = b.dataset.v; syncFooter(); footerChanged(); }));
  $$('[data-show-footer]').forEach(b => b.addEventListener('click', () => {
    if (selected) select(null);
    selectFooter(true);
    showTab('footer');
    const el = footerEl();
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }));
  syncFooter();
  $$('[data-prop]').forEach(inp => {
    const p = inp.dataset.prop;
    if (inp.type === 'checkbox') inp.checked = !!page[p];
    else inp.value = page[p] == null ? '' : page[p];
    inp.addEventListener(inp.tagName === 'SELECT' || inp.type === 'checkbox' ? 'change' : 'input', () => {
      page[p] = inp.type === 'checkbox' ? inp.checked : inp.type === 'number' ? (parseInt(inp.value, 10) || 0) : inp.value;
      if (p === 'title') $('[data-title]').textContent = inp.value || 'Untitled';
      if (p === 'layout' || p === 'hideHeader' || p === 'hideFooter') applyPageLook();
      markDirty();
    });
    if (p === 'slug') inp.addEventListener('blur', () => {
      inp.value = inp.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
      page.slug = inp.value;
    });
  });

  /* password protection */
  const protectInput = $('[data-protect-input]');
  const protectRemove = $('[data-protect-remove]');
  function syncProtect() {
    const st = $('[data-protect-state]');
    if (page.removePassword) st.textContent = 'The password will be removed when you save.';
    else if (page.newPassword) st.textContent = 'The new password applies when you save.';
    else st.textContent = page.protected ? 'Protected. Visitors must enter a password to see this page.' : 'Anyone can see this page.';
    protectRemove.hidden = !page.protected || !!page.removePassword;
  }
  protectInput.addEventListener('input', () => {
    page.newPassword = protectInput.value;
    page.removePassword = false;
    syncProtect();
    markDirty();
  });
  protectRemove.addEventListener('click', () => {
    page.removePassword = true;
    page.newPassword = '';
    protectInput.value = '';
    syncProtect();
    markDirty();
  });
  syncProtect();

  const publish = $('[data-publish]');
  publish.addEventListener('change', () => {
    page.status = publish.checked ? 'published' : 'draft';
    $('[data-status-text]').textContent = publish.checked ? 'Live' : 'Draft';
    markDirty();
  });

  /* ---------- saving ---------- */
  async function save() {
    if (saving) return;
    saving = true;
    snapshot();
    const v = version;
    const btn = $('[data-save]');
    btn.disabled = true;
    $('[data-save-state]').textContent = 'Saving…';
    const payload = { page };
    const sentFooter = footerDirty;
    if (sentFooter) payload.footer = { text: FT.text, align: FT.align, links: FT.links, social: FT.social };
    footerDirty = false;
    const r = await T.api('save_page', payload);
    saving = false;
    btn.disabled = false;
    if (!(r && r.ok)) footerDirty = footerDirty || sentFooter;
    if (r && r.ok) {
      page.slug = r.page.slug;
      page.status = r.page.status;
      page.protected = !!r.page.protected;
      delete page.newPassword;
      delete page.removePassword;
      protectInput.value = '';
      syncProtect();
      const slugInp = $('[data-prop="slug"]');
      if (document.activeElement !== slugInp) slugInp.value = page.slug;
      $('[data-view]').href = r.url;
      if (v === version) {
        dirty = false;
        btn.classList.remove('pulse');
        $('[data-save-state]').textContent = 'Saved at ' + r.saved;
      } else {
        $('[data-save-state]').textContent = 'Unsaved changes';
      }
      T.toast(page.status === 'published' ? 'Saved. This page is live.' : 'Saved as draft');
    } else {
      $('[data-save-state]').textContent = 'Not saved';
      T.toast((r && r.error) || 'Could not save.', 'error');
    }
  }
  $('[data-save]').addEventListener('click', save);
  $('[data-undo]').addEventListener('click', () => undo(false));
  $('[data-redo]').addEventListener('click', () => undo(true));

  document.addEventListener('keydown', e => {
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
    const typing = /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName);
    if (mod && e.key.toLowerCase() === 'z' && !typing) { e.preventDefault(); undo(e.shiftKey); }
  });

  window.addEventListener('beforeunload', e => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* device preview */
  $$('[data-device]').forEach(btn => btn.addEventListener('click', () => {
    $$('[data-device]').forEach(b => b.classList.toggle('active', b === btn));
    $('[data-frame-wrap]').dataset.device = btn.dataset.device;
  }));

  renderOutline();
  updateUndoButtons();
})();
