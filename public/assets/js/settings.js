(function () {
    'use strict';

    var form = document.getElementById('settings-form');
    if (!form) {
        return;
    }

    var endpoint = form.dataset.testEndpoint;
    var csrf = form.dataset.csrf;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function resultFor(button) {
        var box = button.closest('.test-box');
        return box ? box.querySelector('.test-result') : null;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('.test-model') : null;
        if (!button) {
            return;
        }

        var service = button.dataset.service;
        var box = button.closest('.test-box');
        var result = resultFor(button);
        var messageInput = box ? box.querySelector('.test-message') : null;
        var modelInput = form.querySelector('[name="' + service + '_modelo"]');

        var original = button.textContent;
        button.disabled = true;
        button.textContent = 'Probando...';
        if (result) {
            result.className = 'test-result muted';
            result.textContent = 'Enviando...';
        }

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify({
                servicio: service,
                modelo: modelInput ? modelInput.value.trim() : '',
                mensaje: messageInput ? messageInput.value : ''
            })
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('Respuesta invalida del servidor (' + response.status + ')');
            });
        }).then(function (data) {
            if (!result) {
                return;
            }
            var meta = [];
            if (data.modelo) { meta.push('modelo: ' + data.modelo); }
            if (data.segundos != null) { meta.push(data.segundos + 's'); }
            if (data.tokens) { meta.push(data.tokens + ' tokens'); }

            if (data.ok) {
                result.className = 'test-result test-ok';
                result.innerHTML = '<strong>OK</strong>'
                    + (meta.length ? ' <span class="muted small">(' + escapeHtml(meta.join(' · ')) + ')</span>' : '')
                    + '<div class="feedback">' + escapeHtml(data.text).replace(/\n/g, '<br>') + '</div>';
            } else {
                result.className = 'test-result test-error';
                result.innerHTML = '<strong>Error</strong>'
                    + (meta.length ? ' <span class="muted small">(' + escapeHtml(meta.join(' · ')) + ')</span>' : '')
                    + '<div class="error-text">' + escapeHtml(data.error || 'Error desconocido') + '</div>';
            }
        }).catch(function (error) {
            if (result) {
                result.className = 'test-result test-error';
                result.innerHTML = '<strong>Error</strong><div class="error-text">' + escapeHtml(error.message) + '</div>';
            }
        }).finally(function () {
            button.disabled = false;
            button.textContent = original;
        });
    });
})();
