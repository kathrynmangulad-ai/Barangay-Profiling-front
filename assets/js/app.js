









(function () {
    'use strict';

    var body = document.body;
    var mqMobile = window.matchMedia('(max-width: 1024px)');
    function isMobile() { return mqMobile.matches; }

     
    var toggleBtn = document.querySelector('[data-sidebar-toggle]');
    var collapseBtn = document.querySelector('[data-sidebar-collapse]');
    var backdrop = document.querySelector('[data-sidebar-backdrop]');
    var sidebar = document.getElementById('sidebar');

    function openDrawer() {
        body.classList.add('sidebar-open');
        if (backdrop) { backdrop.hidden = false; }
        if (toggleBtn) { toggleBtn.setAttribute('aria-expanded', 'true'); }
    }
    function closeDrawer() {
        body.classList.remove('sidebar-open');
        if (backdrop) { backdrop.hidden = true; }
        if (toggleBtn) { toggleBtn.setAttribute('aria-expanded', 'false'); }
    }
    function persistCollapse() {
        try {
            localStorage.setItem('bms.sidebar',
                body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
        } catch (err) {   }
    }

    try {
        if (localStorage.getItem('bms.sidebar') === 'collapsed') {
            body.classList.add('sidebar-collapsed');
        }
    } catch (err) {   }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            if (isMobile()) {
                if (body.classList.contains('sidebar-open')) { closeDrawer(); }
                else { openDrawer(); }
            } else {
                body.classList.toggle('sidebar-collapsed');
                persistCollapse();
            }
        });
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            body.classList.toggle('sidebar-collapsed');
            persistCollapse();
        });
    }
    if (backdrop) { backdrop.addEventListener('click', closeDrawer); }

    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            if (isMobile() && e.target.closest('a')) { closeDrawer(); }
        });
    }

    function handleViewport() {
        if (!isMobile()) { closeDrawer(); }
    }
    if (mqMobile.addEventListener) { mqMobile.addEventListener('change', handleViewport); }
    else if (mqMobile.addListener) { mqMobile.addListener(handleViewport); }

     
    function closeDropdown(dd) {
        dd.classList.remove('is-open');
        var m = dd.querySelector('[data-dropdown-menu]');
        var b = dd.querySelector('[data-dropdown-toggle]');
        if (m) { m.hidden = true; }
        if (b) { b.setAttribute('aria-expanded', 'false'); }
    }
    function closeAllDropdowns(except) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-dropdown].is-open'), function (dd) {
            if (dd !== except) { closeDropdown(dd); }
        });
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-dropdown]'), function (dd) {
        var btn = dd.querySelector('[data-dropdown-toggle]');
        var menu = dd.querySelector('[data-dropdown-menu]');
        if (!btn || !menu) { return; }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = dd.classList.toggle('is-open');
            menu.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            closeAllDropdowns(dd);
        });
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
    });
    document.addEventListener('click', function () { closeAllDropdowns(null); });

     
    Array.prototype.forEach.call(document.querySelectorAll('[data-toast]'), function (toast) {
        var close = toast.querySelector('[data-toast-close]');
        function remove() {
            toast.classList.add('is-hiding');
            window.setTimeout(function () {
                if (toast.parentNode) { toast.parentNode.removeChild(toast); }
            }, 240);
        }
        if (close) { close.addEventListener('click', remove); }
        window.setTimeout(remove, 6500);
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-auto-dismiss]'), function (el) {
        var delay = parseInt(el.getAttribute('data-auto-dismiss'), 10);
        if (isNaN(delay) || delay <= 0) { delay = 5000; }

        window.setTimeout(function () {
            el.classList.add('is-hiding');
            window.setTimeout(function () {
                if (el.parentNode) { el.parentNode.removeChild(el); }
            }, 240);
        }, delay);
    });

     
    var confirmEl = document.getElementById('confirmDialog');
    var confirmTitle = document.getElementById('confirmTitle');
    var confirmDesc = document.getElementById('confirmDesc');

    function ask(message, title, onAccept) {
        if (!confirmEl) {
            if (window.confirm(message || 'Are you sure?')) { onAccept(); }
            return;
        }
        var acceptBtn = confirmEl.querySelector('[data-confirm-accept]');
        var cancelEls = Array.prototype.slice.call(confirmEl.querySelectorAll('[data-confirm-cancel]'));
        var lastFocused = document.activeElement;

        confirmTitle.textContent = title || 'Please confirm';
        confirmDesc.textContent = message || 'This action cannot be undone.';
        confirmEl.hidden = false;
        document.body.classList.add('modal-open');

        function cleanup() {
            confirmEl.hidden = true;
            document.body.classList.remove('modal-open');
            acceptBtn.removeEventListener('click', onOk);
            cancelEls.forEach(function (c) { c.removeEventListener('click', onCancel); });
            document.removeEventListener('keydown', onKey);
            if (lastFocused && typeof lastFocused.focus === 'function') { lastFocused.focus(); }
        }
        function onOk() { cleanup(); onAccept(); }
        function onCancel() { cleanup(); }
        function onKey(e) { if (e.key === 'Escape') { onCancel(); } }

        acceptBtn.addEventListener('click', onOk);
        cancelEls.forEach(function (c) { c.addEventListener('click', onCancel); });
        document.addEventListener('keydown', onKey);
        acceptBtn.focus();
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-confirm]') : null;
        if (!el || el.tagName === 'FORM') { return; }
        e.preventDefault();
        ask(el.getAttribute('data-confirm'), el.getAttribute('data-confirm-title'), function () {
            if (el.tagName === 'A' && el.getAttribute('href')) {
                if (el.getAttribute('target') === '_blank') {
                    window.open(el.getAttribute('href'), '_blank');
                } else {
                    window.location.href = el.getAttribute('href');
                }
            } else if (el.form) {
                el.form.dataset.confirmed = '1';
                if (el.form.requestSubmit) { el.form.requestSubmit(); } else { el.form.submit(); }
            }
        });
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.hasAttribute && form.hasAttribute('data-confirm') && form.dataset.confirmed !== '1') {
            e.preventDefault();
            ask(form.getAttribute('data-confirm'), form.getAttribute('data-confirm-title'), function () {
                form.dataset.confirmed = '1';
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            });
        }
    });

     
    


    Array.prototype.forEach.call(document.querySelectorAll('[data-toggle]'), function (btn) {
        var sel = btn.getAttribute('data-toggle');
        if (!sel) { return; }
        var panel = document.querySelector(sel);
        if (!panel) { return; }
        btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            panel.hidden = !panel.hidden;
            btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            if (!panel.hidden) {
                var first = panel.querySelector('input:not([type=hidden]), select, textarea');
                if (first && first.focus) { first.focus(); }
            }
        });
    });

     
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllDropdowns(null);
            if (body.classList.contains('sidebar-open')) { closeDrawer(); }
        }
    });
})();
