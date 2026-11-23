/* Teeny CMS: runs inside sandboxed widget frames.
   Gives plugins their data as window.teeny.data and tells the page how tall the widget is. */
(() => {
  let data = {};
  try { data = JSON.parse(document.getElementById('teeny-data').textContent || '{}'); } catch (e) {}
  const report = () => {
    const h = Math.ceil(document.documentElement.scrollHeight);
    try { parent.postMessage({ teenyWidget: 1, height: h }, '*'); } catch (e) {}
  };
  window.teeny = Object.freeze({ data: Object.freeze(data), resize: report });
  addEventListener('load', report);
  if (typeof ResizeObserver === 'function') new ResizeObserver(report).observe(document.documentElement);
})();
