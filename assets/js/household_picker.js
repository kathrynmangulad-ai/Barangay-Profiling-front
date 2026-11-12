/* Filter a household <select> as the user types a household number or the
   head's name. Non-matching options are hidden (and disabled, for browsers
   that ignore `hidden` on options). The placeholder option always stays. */
(function () {
    document.querySelectorAll('[data-filter-select]').forEach(function (box) {
        var select = document.querySelector(box.getAttribute('data-filter-select'));
        if (!select) { return; }
        var hint = box.getAttribute('data-filter-hint') ? document.querySelector(box.getAttribute('data-filter-hint')) : null;
        var options = Array.prototype.slice.call(select.options).filter(function (o) { return o.value !== '0' && o.value !== ''; });

        function apply() {
            var q = box.value.trim().toLowerCase();
            var shown = 0, firstMatch = null;
            options.forEach(function (o) {
                var match = q === '' || o.text.toLowerCase().indexOf(q) !== -1;
                o.hidden = !match;
                o.disabled = !match;
                if (match) { shown++; if (!firstMatch) { firstMatch = o; } }
            });
            Array.prototype.forEach.call(select.querySelectorAll('optgroup'), function (g) {
                var any = Array.prototype.some.call(g.options, function (o) { return !o.hidden; });
                g.hidden = !any;
            });
            // Jump to the first match when nothing is chosen yet or the chosen option was filtered out.
            var cur = select.selectedOptions[0];
            if (q !== '' && (!cur || cur.hidden || cur.value === '0' || cur.value === '')) {
                select.value = firstMatch ? firstMatch.value : '0';
            }
            if (hint) {
                hint.textContent = q === ''
                    ? options.length + ' household' + (options.length === 1 ? '' : 's')
                    : shown + ' of ' + options.length + ' households match';
            }
        }
        box.addEventListener('input', apply);
        apply();
    });
})();
