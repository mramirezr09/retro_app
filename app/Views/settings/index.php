<?php /** @var array $services */ /** @var array $processing */ ?>
<section class="page-head">
    <div>
        <h1>Ajustes</h1>
        <p class="muted">Las API keys se guardan en el archivo <code>.env</code> del proyecto.</p>
    </div>
</section>

<form method="post" action="<?= e(url('/settings')) ?>" class="form"
      id="settings-form"
      data-test-endpoint="<?= e(url('/api/settings/test')) ?>"
      data-csrf="<?= e($csrf) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <section class="card">
        <h2>Procesamiento con IA</h2>
        <p class="muted">Si una respuesta no es valida (error o menos palabras del minimo), se reintenta y luego se usa el otro servicio como respaldo.</p>
        <div class="form-row">
            <label>
                <span>Espera entre registros (segundos)</span>
                <input type="number" name="ai_delay_segundos" min="0" step="1" value="<?= (int) $processing['delay'] ?>">
            </label>
            <label>
                <span>Espera entre reintentos (segundos)</span>
                <input type="number" name="ai_retry_segundos" min="0" step="1" value="<?= (int) $processing['retry'] ?>">
            </label>
        </div>
        <div class="form-row">
            <label>
                <span>Intentos por servicio</span>
                <input type="number" name="ai_intentos" min="1" step="1" value="<?= (int) $processing['intentos'] ?>">
                <small class="muted">Con 2 intentos por servicio se hacen 4 intentos totales (2 principal + 2 alterno).</small>
            </label>
            <label>
                <span>Minimo de palabras para considerar valida la retroalimentacion</span>
                <input type="number" name="ai_min_palabras" min="0" step="1" value="<?= (int) $processing['min_palabras'] ?>">
            </label>
        </div>
    </section>

    <?php foreach ($services as $key => $service): ?>
        <?php $data = $service['data']; ?>
        <section class="card">
            <h2><?= e($service['label']) ?></h2>

            <div class="form-row">
                <label>
                    <span>Modelo</span>
                    <input type="text" name="<?= e($key) ?>_modelo" value="<?= e($data['modelo'] ?? '') ?>" placeholder="proveedor/modelo">
                </label>

                <?php if ($key === 'openrouter'): ?>
                    <label>
                        <span>Base URL</span>
                        <input type="text" name="<?= e($key) ?>_base_url" value="<?= e($data['base_url'] ?? '') ?>" placeholder="https://openrouter.ai/api/v1">
                    </label>
                <?php else: ?>
                    <label>
                        <span>Ruta del binario opencode (opcional)</span>
                        <input type="text" name="<?= e($key) ?>_path" value="<?= e($data['opencode_path'] ?? '') ?>" placeholder="Se autodetecta en PATH">
                        <small class="muted">Ej.: /usr/local/bin/opencode (Linux) o C:\ruta\opencode.exe (Windows). Debe ser ejecutable por el usuario del servidor web (p. ej. www-data).</small>
                    </label>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label>
                    <span>API key (variable <?= e($service['key_env']) ?>)</span>
                    <input type="password" name="<?= e($key) ?>_api_key" placeholder="<?= $service['has_key'] ? e('Guardada: ' . $service['key_masked']) : 'Sin configurar' ?>" autocomplete="new-password">
                    <small class="muted">Deje vacio para conservar la actual.</small>
                </label>
                <label class="check">
                    <input type="checkbox" name="<?= e($key) ?>_clear_key" value="1">
                    <span>Eliminar la API key guardada</span>
                </label>
            </div>

            <div class="test-box" data-service="<?= e($key) ?>">
                <label>
                    <span>Probar conexion y modelo</span>
                    <input type="text" class="test-message" value="Responde unicamente con la palabra OK." placeholder="Mensaje de prueba">
                </label>
                <div class="form-actions">
                    <button type="button" class="btn test-model" data-service="<?= e($key) ?>">Probar modelo</button>
                    <span class="test-result muted"></span>
                </div>
                <small class="muted">Usa el modelo escrito arriba y la API key guardada. Si cambio la API key, guarde primero los ajustes.</small>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Guardar ajustes</button>
    </div>
</form>
