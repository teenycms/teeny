/* Image slider: runs inside Teeny's sandboxed widget frame.
   Swipe, arrow buttons, dots and keyboard arrows; window.teeny.resize() keeps the frame the right height. */
(() => {
  const track = document.querySelector('.sl__track');
  const slides = [...document.querySelectorAll('.sl__slide')];
  const root = document.querySelector('.sl');
  // Arrow buttons are added here (templates contain no buttons).
  const mk = (cls, label, text) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'sl__nav ' + cls; b.setAttribute('aria-label', label); b.textContent = text; root.appendChild(b); return b; };
  const prev = mk('sl__nav--prev', 'Previous photo', '\u2039');
  const next = mk('sl__nav--next', 'Next photo', '\u203a');
  const dots = document.querySelector('.sl__dots');
  if (!track || slides.length === 0) return;
  let index = 0;
  const go = i => { index = Math.max(0, Math.min(slides.length - 1, i)); track.scrollTo({ left: slides[index].offsetLeft, behavior: 'smooth' }); };
  const buttons = slides.map((_, i) => { const b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', 'Photo ' + (i + 1)); b.addEventListener('click', () => go(i)); dots.appendChild(b); return b; });
  const update = () => {
    index = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
    buttons.forEach((b, i) => b.setAttribute('aria-current', String(i === index)));
    prev.hidden = index === 0; next.hidden = index === slides.length - 1;
  };
  if (slides.length < 2) { prev.hidden = next.hidden = true; dots.hidden = true; }
  prev.addEventListener('click', () => go(index - 1));
  next.addEventListener('click', () => go(index + 1));
  track.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
  document.addEventListener('keydown', e => { if (e.key === 'ArrowLeft') go(index - 1); if (e.key === 'ArrowRight') go(index + 1); });
  document.querySelectorAll('img').forEach(img => img.addEventListener('load', () => window.teeny.resize()));
  update();
  window.teeny.resize();
})();
