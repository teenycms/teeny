/* Teeny CMS: apply the visitor's saved light/dark choice before the page paints */
try { if (localStorage.getItem('tc-scheme') === 'alt') document.documentElement.setAttribute('data-scheme', 'alt'); } catch (e) {}
