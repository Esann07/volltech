/* VoltTech - applies the saved light/dark theme before first paint.
   Loaded synchronously in <head> (an external file, because the site's
   Content-Security-Policy does not allow inline scripts). */
(function () {
    try {
        var t = localStorage.getItem('vt-theme');
        if (t === 'light' || t === 'dark') {
            document.documentElement.setAttribute('data-theme', t);
        }
    } catch (e) { /* storage unavailable: keep the default theme */ }
})();
