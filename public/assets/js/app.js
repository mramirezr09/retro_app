(function () {
    'use strict';

    var panel = document.getElementById('ai-panel');
    if (!panel) {
        return;
    }

    var fileId = panel.dataset.fileId;
    var csrf = panel.dataset.csrf;
    var endpoint = panel.dataset.endpoint;
    var resetEndpoint = panel.dataset.resetEndpoint;

    var checkAll = document.getElementById('check-all');
    var selectedCount = document.getElementById('selected-count');
    var sendBtn = document.getElementById('send-btn');
    var resetBtn = document.getElementById('reset-btn');
    var promptSelect = document.getElementById('prompt-select');
    var serviceSelect = document.getElementById('service-select');
    var modelInput = document.getElementById('model-input');
    var progressWrap = document.getElementById('progress-wrap');
    var progressBar = document.getElementById('progress-bar');
    var progressText = document.getElementById('progress-text');

    function rowChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('.row-check'));
    }

    function selectedIds() {
        return rowChecks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
    }

    function refreshState() {
        var ids = selectedIds();
        selectedCount.textContent = ids.length + ' seleccionados';
        sendBtn.disabled = ids.length === 0 || promptSelect.value === '';
        resetBtn.disabled = ids.length === 0;
    }

    function updateStatusCell(id, status, tokens) {
        var row = document.getElementById('row-' + id);
        if (!row) { return; }
        row.className = row.className.replace(/status-\S+/g, '').trim() + ' status-' + status;
        var cell = row.querySelector('.cell-status');
        var html = '<span class="badge state-' + status + '">' + status + '</span>';
        if (tokens) {
            html += '<span class="muted small">' + tokens + ' tk</span>';
        }
        cell.innerHTML = html;
    }

    function updateFeedbackCell(id, feedback) {
        var row = document.getElementById('row-' + id);
        if (!row) { return; }
        var cell = row.querySelector('.cell-feedback');
        if (feedback) {
            cell.innerHTML = '<div class="feedback">' + escapeHtml(feedback).replace(/\n/g, '<br>') + '</div>';
        }
    }

    function updateErrorCell(id, error) {
        var row = document.getElementById('row-' + id);
        if (!row) { return; }
        var cell = row.querySelector('.cell-feedback');
        cell.innerHTML = '<span class="error-text">' + escapeHtml(error).replace(/\n/g, '<br>') + '</span>';
    }

    function updateProviderCell(id, result) {
        var row = document.getElementById('row-' + id);
        if (!row) { return; }
        var cell = row.querySelector('.cell-provider');
        if (!cell) { return; }
        var html = '<div class="provider-line">';
        if (result.servicio) {
            html += '<span class="muted small">' + escapeHtml(result.servicio) + '</span>';
        }
        if (result.modelo) {
            html += '<span class="muted small">' + escapeHtml(result.modelo) + '</span>';
        }
        if (result.fallback) {
            html += '<span class="attach-tag badge-fallback">fallback</span>';
        }
        if (result.intentos && result.intentos > 1) {
            html += '<span class="muted small">' + result.intentos + ' intentos</span>';
        }
        html += '</div>';
        cell.innerHTML = html;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('Respuesta invalida del servidor (' + response.status + ')');
            });
        });
    }

    function setProgress(done, total) {
        progressWrap.hidden = false;
        var percent = total === 0 ? 0 : Math.round((done / total) * 100);
        progressBar.style.width = percent + '%';
        progressText.textContent = done + ' / ' + total;
    }

    checkAll && checkAll.addEventListener('change', function () {
        rowChecks().forEach(function (c) { c.checked = checkAll.checked; });
        refreshState();
    });

    document.addEventListener('change', function (event) {
        if (event.target.classList && event.target.classList.contains('row-check')) {
            checkAll.checked = rowChecks().every(function (c) { return c.checked; });
            refreshState();
        }
    });

    promptSelect.addEventListener('change', refreshState);

    sendBtn.addEventListener('click', function () {
        var ids = selectedIds();
        if (ids.length === 0) { return; }
        if (!promptSelect.value) { alert('Seleccione un prompt.'); return; }

        sendBtn.disabled = true;
        resetBtn.disabled = true;
        var total = ids.length;
        var done = 0;
        setProgress(0, total);

        var payloadBase = {
            archivo_id: fileId,
            prompt_id: promptSelect.value,
            servicio: serviceSelect.value,
            modelo: modelInput.value.trim()
        };

        var delaySeconds = parseInt(panel.dataset.delay, 10);
        if (isNaN(delaySeconds) || delaySeconds < 0) { delaySeconds = 0; }

        var chain = Promise.resolve();
        ids.forEach(function (id, index) {
            chain = chain.then(function () {
                payloadBase.registros = [id];
                return post(endpoint, payloadBase).then(function (data) {
                    if (!data.ok) {
                        updateErrorCell(id, data.error || 'Error');
                        updateStatusCell(id, 'error');
                    } else {
                        (data.results || []).forEach(function (result) {
                            updateProviderCell(id, result);
                            if (result.ok) {
                                updateFeedbackCell(id, result.feedback);
                                updateStatusCell(id, 'enviado', result.tokens);
                            } else {
                                updateErrorCell(id, result.error || 'Error');
                                updateStatusCell(id, 'error');
                            }
                        });
                    }
                }).catch(function (error) {
                    updateErrorCell(id, error.message);
                    updateStatusCell(id, 'error');
                }).then(function () {
                    done += 1;
                    setProgress(done, total);
                });
            });

            if (index < ids.length - 1 && delaySeconds > 0) {
                chain = chain.then(function () {
                    return new Promise(function (resolve) {
                        var remaining = delaySeconds;
                        progressText.textContent = 'Esperando ' + remaining + 's antes del siguiente...';
                        var timer = setInterval(function () {
                            remaining -= 1;
                            if (remaining <= 0) {
                                clearInterval(timer);
                                resolve();
                            } else {
                                progressText.textContent = 'Esperando ' + remaining + 's antes del siguiente...';
                            }
                        }, 1000);
                    });
                });
            }
        });

        chain.then(function () {
            refreshState();
            progressText.textContent = 'Completado (' + total + ')';
        });
    });

    resetBtn.addEventListener('click', function () {
        var ids = selectedIds();
        if (ids.length === 0) { return; }
        if (!confirm('¿Reiniciar la retroalimentacion de los registros seleccionados?')) { return; }

        resetBtn.disabled = true;
        post(resetEndpoint, { archivo_id: fileId, registros: ids }).then(function (data) {
            if (data.ok) {
                (data.updated || []).forEach(function (id) {
                    updateFeedbackCell(id, '');
                    updateStatusCell(id, 'pendiente');
                });
            }
        }).finally(function () {
            refreshState();
        });
    });

    refreshState();
})();
