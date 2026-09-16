<?php
/** @var array $file */
/** @var array $columns */
/** @var array $rows */
/** @var array $prompts */
/** @var array $services */
/** @var array $settings */
?>
<section class="page-head">
    <div>
        <h1><?= e($file['nombre_original']) ?></h1>
        <p class="muted">
            <?= count($rows) ?> registros · Columna de respuesta: <strong><?= e($file['respuesta_columna']) ?></strong>
        </p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('/files')) ?>">Volver</a>
        <a class="btn btn-primary" href="<?= e(url('/files/' . (int) $file['id'] . '/export')) ?>">Descargar XLSX</a>
    </div>
</section>

<section class="card" id="ai-panel"
         data-file-id="<?= (int) $file['id'] ?>"
         data-csrf="<?= e($csrf) ?>"
         data-endpoint="<?= e(url('/api/records/send')) ?>"
         data-reset-endpoint="<?= e(url('/api/records/reset')) ?>">
    <h2>Enviar a la IA</h2>
    <div class="panel-grid">
        <label>
            <span>Prompt</span>
            <select id="prompt-select" required>
                <option value="">Seleccione un prompt...</option>
                <?php foreach ($prompts as $prompt): ?>
                    <option value="<?= (int) $prompt['id'] ?>"><?= e($prompt['nombre']) ?><?= $prompt['materia'] ? ' · ' . e($prompt['materia']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Servicio</span>
            <select id="service-select">
                <?php foreach ($services as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Modelo (opcional)</span>
            <input type="text" id="model-input" placeholder="Ej: openai/gpt-4o-mini">
        </label>

        <div class="panel-actions">
            <button class="btn btn-primary" type="button" id="send-btn" disabled>Enviar seleccionados</button>
            <button class="btn" type="button" id="reset-btn" disabled>Reiniciar</button>
        </div>
    </div>

    <div class="progress-wrap" id="progress-wrap" hidden>
        <div class="progress"><div class="progress-bar" id="progress-bar"></div></div>
        <span class="progress-text" id="progress-text">0 / 0</span>
    </div>
</section>

<section class="card">
    <div class="table-toolbar">
        <label class="check-all"><input type="checkbox" id="check-all"> Seleccionar todo</label>
        <span class="badge" id="selected-count">0 seleccionados</span>
    </div>
    <div class="table-scroll tall">
        <table class="table table-rows" id="rows-table">
            <thead>
                <tr>
                    <th class="col-check"></th>
                    <th>#</th>
                    <?php foreach ($columns as $column): ?>
                        <th><?= e($column) ?></th>
                    <?php endforeach; ?>
                    <th>Retroalimentacion</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $isAnswer = $file['respuesta_columna'];
                    $status = (string) $row['estado'];
                    ?>
                    <tr id="row-<?= (int) $row['id'] ?>" data-id="<?= (int) $row['id'] ?>" class="status-<?= e($status) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="row-check" value="<?= (int) $row['id'] ?>">
                        </td>
                        <td><?= (int) $row['numero_fila'] ?></td>
                        <?php foreach ($columns as $column): ?>
                            <td class="<?= $column === $isAnswer ? 'cell-answer' : '' ?>">
                                <?= e(truncate_text((string) ($row['datos'][$column] ?? ''), 80)) ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="cell-feedback">
                            <?php if (!empty($row['retroalimentacion'])): ?>
                                <div class="feedback"><?= nl2br(e((string) $row['retroalimentacion'])) ?></div>
                            <?php elseif (!empty($row['error'])): ?>
                                <span class="error-text"><?= e((string) $row['error']) ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-status">
                            <span class="badge state-<?= e($status) ?>"><?= e($status) ?></span>
                            <?php if (!empty($row['tokens'])): ?>
                                <span class="muted small"><?= (int) $row['tokens'] ?> tk</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
