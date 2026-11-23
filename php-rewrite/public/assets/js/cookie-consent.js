(function () {
    var STORAGE_KEY = 'iro_cookie_consent';

    document.addEventListener('DOMContentLoaded', function () {
        var acceptBtn = document.getElementById('iro-cookie-accept');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                try {
                    localStorage.setItem(STORAGE_KEY, '1');
                } catch (e) {}
                document.documentElement.classList.add('iro-cookie-consent-done');
            });
        }

        document.querySelectorAll('[data-iro-cookie-reset]').forEach(function (el) {
            el.addEventListener('click', function () {
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) {}
                document.documentElement.classList.remove('iro-cookie-consent-done');
            });
        });
    });
})();
