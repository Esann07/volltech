/* VoltTech — light/dark theme toggle (presentation only).
   The saved choice is applied before first paint by a tiny inline
   script in header.php, so there is no flash of the wrong theme. */
(function () {
    var KEY = 'vt-theme';
    var root = document.documentElement;
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-toggle]');
        if (!btn) { return; }
        var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem(KEY, next); } catch (err) { /* storage unavailable: still works for this page */ }
    });
})();
