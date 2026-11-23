/* Countdown: runs inside Teeny's sandboxed widget frame.
   window.teeny.data holds this section's fields; window.teeny.resize() re-measures the frame. */
(() => {
  const d = window.teeny.data;
  const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?$/.exec(String(d.date || '').trim());
  const grid = document.querySelector('.cd__grid');
  const done = document.querySelector('.cd__done');
  if (!m) { grid.textContent = 'Please enter the date as YYYY-MM-DD HH:MM'; return; }
  const target = new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0)).getTime();
  const out = unit => document.querySelector('[data-unit="' + unit + '"]');
  const pad = n => String(n).padStart(2, '0');

  function tick() {
    let s = Math.max(0, Math.floor((target - Date.now()) / 1000));
    if (s === 0) {
      grid.hidden = true;
      done.hidden = false;
      window.teeny.resize();
      return;
    }
    out('d').textContent = Math.floor(s / 86400); s %= 86400;
    out('h').textContent = pad(Math.floor(s / 3600)); s %= 3600;
    out('m').textContent = pad(Math.floor(s / 60));
    out('s').textContent = pad(s % 60);
    setTimeout(tick, 1000 - (Date.now() % 1000));
  }
  tick();
})();
