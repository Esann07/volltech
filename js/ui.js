/* VoltTech - small UI behaviours shared by the player app and the admin app.
   Replaces inline onclick/onsubmit/onchange attributes (blocked by the CSP).

   <form data-confirm="Really delete?">    asks before submitting
   <select data-autosubmit>                 submits its form when changed
*/
(function () {
    document.addEventListener('submit', function (e) {
        var msg = e.target && e.target.getAttribute && e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    document.addEventListener('change', function (e) {
        var el = e.target;
        if (el && el.hasAttribute && el.hasAttribute('data-autosubmit') && el.form) {
            el.form.submit();
        }
    });
})();
