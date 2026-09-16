<?php /** @var array $services */ ?>
<section class="page-head">
    <div>
        <h1>Ajustes</h1>
        <p class="muted">Las API keys se guardan en el archivo <code>.env</code> del proyecto.</p>
    </div>
</section>

<form method="post" action="<?= e(url('/settings')) ?>" class="form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

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
        </section>
    <?php endforeach; ?>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Guardar ajustes</button>
    </div>
</form>
