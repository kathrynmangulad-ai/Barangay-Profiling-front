




(function () {
    'use strict';

    




    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        var input = document.querySelector(btn.getAttribute('data-password-toggle'));
        if (!input) { return; }

        btn.addEventListener('click', function () {
            var reveal = (input.type === 'password');
            input.type = reveal ? 'text' : 'password';
            btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            btn.setAttribute('title', reveal ? 'Hide password' : 'Show password');
             
            try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
        });
    });

    




    document.querySelectorAll('[data-auto-dismiss]').forEach(function (el) {
        var delay = parseInt(el.getAttribute('data-auto-dismiss'), 10);
        if (isNaN(delay) || delay <= 0) { delay = 5000; }

        window.setTimeout(function () {
            el.classList.add('is-hiding');
            window.setTimeout(function () {
                if (el.parentNode) { el.parentNode.removeChild(el); }
            }, 240);
        }, delay);
    });

    var RULES = {
        len:   function (v) { return v.length >= 8; },
        lower: function (v) { return /[a-z]/.test(v); },
        upper: function (v) { return /[A-Z]/.test(v); },
        num:   function (v) { return /[0-9]/.test(v); },
        sym:   function (v) { return /[^A-Za-z0-9]/.test(v); }
    };
    var LEVELS = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'];

    document.querySelectorAll('input[data-strength]').forEach(function (input) {
        var field = input.closest('.field');
        if (!field) { return; }

        var bar   = field.querySelector('[data-strength-bar]');
        var label = field.querySelector('[data-strength-label]');
        var items = field.querySelectorAll('[data-req]');
        var total = items.length || 1;

        function evaluate() {
            var value = input.value;
            var passed = 0;

            items.forEach(function (li) {
                var rule = RULES[li.getAttribute('data-req')];
                var ok = rule ? rule(value) : true;
                if (!value) {
                    // Field is empty: show a neutral (not-yet-evaluated) state.
                    li.setAttribute('data-ok', 'none');
                } else {
                    li.setAttribute('data-ok', ok ? 'true' : 'false');
                }
                if (ok) { passed++; }
            });

            if (bar) {
                bar.style.width = ((passed / total) * 100) + '%';
                bar.setAttribute('data-level', String(passed));
            }
            if (label) {
                label.textContent = 'Password strength: ' + (value ? LEVELS[passed] : '—');
                label.setAttribute('data-level', String(passed));
            }
        }

        input.addEventListener('input', evaluate);
        evaluate();
    });

    


    document.querySelectorAll('input[data-match-to]').forEach(function (input) {
        var other  = document.querySelector(input.getAttribute('data-match-to'));
        var field  = input.closest('.field');
        var status = field ? field.querySelector('[data-match-msg]') : null;

        function check() {
            if (!status) { return; }
            if (!input.value) {
                status.textContent = '';
                status.removeAttribute('data-state');
                input.removeAttribute('aria-invalid');
                return;
            }
            var ok = !!other && (input.value === other.value);
            status.textContent = ok ? 'Passwords match.' : 'Passwords do not match.';
            status.setAttribute('data-state', ok ? 'ok' : 'bad');
            input.setAttribute('aria-invalid', ok ? 'false' : 'true');
        }

        input.addEventListener('input', check);
        if (other) { other.addEventListener('input', check); }
        check();
    });
})();
