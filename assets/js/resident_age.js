(function () {
    'use strict';

    var birth = document.getElementById('birth_date');
    var age   = document.getElementById('age');
    if (!birth || !age) { return; }

    function computeAge() {
        var value = birth.value;
        if (!value) { age.value = ''; return; }

        var parts = value.split('-');
        if (parts.length !== 3) { age.value = ''; return; }

        var b = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(b.getTime())) { age.value = ''; return; }

        var today = new Date();
        var years = today.getFullYear() - b.getFullYear();
        var month = today.getMonth() - b.getMonth();
        if (month < 0 || (month === 0 && today.getDate() < b.getDate())) { years--; }

        age.value = (years >= 0 && years <= 150) ? String(years) : '';
    }

    birth.addEventListener('change', computeAge);
    birth.addEventListener('input', computeAge);
    computeAge();
})();
