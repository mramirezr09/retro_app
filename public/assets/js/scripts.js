(function () {
    'use strict';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', 'readonly');
            area.style.position = 'fixed';
            area.style.top = '-1000px';
            area.style.left = '-1000px';
            document.body.appendChild(area);
            area.focus();
            area.select();
            try {
                var ok = document.execCommand('copy');
                document.body.removeChild(area);
                ok ? resolve() : reject(new Error('copy failed'));
            } catch (error) {
                document.body.removeChild(area);
                reject(error);
            }
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('.copy-script') : null;
        if (!button) {
            return;
        }
        var source = document.getElementById(button.dataset.source);
        if (!source) {
            return;
        }
        var text = source.value != null ? source.value : source.textContent;
        var original = button.textContent;
        copyText(text).then(function () {
            button.textContent = 'Copiado!';
            setTimeout(function () { button.textContent = original; }, 1500);
        }).catch(function () {
            button.textContent = 'Error al copiar';
            setTimeout(function () { button.textContent = original; }, 1800);
        });
    });
})();
