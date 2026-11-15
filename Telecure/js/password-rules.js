/* TeleCure - live password rules checklist.
 * Usage: <input type="password" data-password-rules="listElementId"> + <ul id="listElementId" class="pw-rules"></ul>
 * Rules must match passwordPolicyError() in includes/config.php (the server is the final judge). */
(function () {
    var RULES = [
        { text: 'At least 8 characters',                       test: function (v) { return v.length >= 8; } },
        { text: 'At least one number (0-9)',                   test: function (v) { return /[0-9]/.test(v); } },
        { text: 'At least one special character (! @ # $ %)',  test: function (v) { return /[^A-Za-z0-9\s]/.test(v); } }
    ];

    function init(input) {
        var list = document.getElementById(input.getAttribute('data-password-rules'));
        if (!list) return;
        list.innerHTML = '';
        var items = RULES.map(function (r) {
            var li = document.createElement('li');
            li.innerHTML = '<i class="fas fa-circle"></i> ' + r.text;
            list.appendChild(li);
            return li;
        });

        function update() {
            var v = input.value, allOk = true;
            RULES.forEach(function (r, i) {
                var ok = r.test(v);
                if (!ok) allOk = false;
                items[i].className = ok ? 'ok' : (v.length ? 'bad' : '');
                items[i].querySelector('i').className = 'fas ' + (ok ? 'fa-check-circle' : (v.length ? 'fa-times-circle' : 'fa-circle'));
            });
            input.setCustomValidity(v.length === 0 || allOk ? '' : 'Password must have 8+ characters, a number and a special character.');
        }
        input.addEventListener('input', update);
        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[data-password-rules]').forEach(init);
    });
})();
