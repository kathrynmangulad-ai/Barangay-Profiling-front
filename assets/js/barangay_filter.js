// Live-search helper for the barangay <select> on users.php (secretary tab).
// The filter input narrows the option list as you type; a single remaining
// match is auto-selected so the field behaves like an autocomplete.
document.querySelectorAll('.brgy-filter').forEach(function (filter) {
    var form = filter.closest('form');
    var select = form ? form.querySelector('.brgy-select') : null;
    if (!select) { return; }

    function showAll() {
        Array.prototype.forEach.call(select.options, function (opt) { opt.hidden = false; });
    }

    filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        var visible = [];

        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.value === '') { return; } // keep the placeholder visible
            var match = opt.textContent.toLowerCase().indexOf(q) !== -1;
            opt.hidden = !match;
            if (match) { visible.push(opt); }
        });

        if (q !== '' && visible.length === 1) {
            select.value = visible[0].value; // auto-select the only match
        }
    });

    // Picking from the list directly clears the filter and restores all options.
    select.addEventListener('change', function () {
        filter.value = '';
        showAll();
    });
});
