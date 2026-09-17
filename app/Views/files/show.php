<?php
/** @var array $file */
/** @var array $columns */
/** @var array $rows */
/** @var array $prompts */
/** @var array $services */
/** @var array $settings */
/** @var array $processing */
?>
<section class="page-head">
    <div>
        <h1><?= e($file['nombre_original']) ?></h1>
        <p class="muted">
            <?= count($rows) ?> registros · Columna de respuesta: <strong><?= e($file['respuesta_columna']) ?></strong>
            <?php if (!empty($file['imagenes_columna'])): ?>
                · Imagenes: <strong><?= e($file['imagenes_columna']) ?></strong>
            <?php endif; ?>
            <?php if (!empty($file['documentos_columna'])): ?>
                · Documentos: <strong><?= e($file['documentos_columna']) ?></strong>
            <?php endif; ?>
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
         data-reset-endpoint="<?= e(url('/api/records/reset')) ?>"
         data-delay="<?= (int) $processing['delay'] ?>">
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
                        <?php
                        $isImageCol = !empty($file['imagenes_columna']) && $column === $file['imagenes_columna'];
                        $isDocCol = !empty($file['documentos_columna']) && $column === $file['documentos_columna'];
                        ?>
                        <th>
                            <?= e($column) ?>
                            <?php if ($isImageCol): ?><span class="attach-tag attach-img">imagenes</span><?php endif; ?>
                            <?php if ($isDocCol): ?><span class="attach-tag attach-doc">documentos</span><?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                    <th>Retroalimentacion</th>
                    <th>IA</th>
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
                            <?php
                            $isImageCol = !empty($file['imagenes_columna']) && $column === $file['imagenes_columna'];
                            $isDocCol = !empty($file['documentos_columna']) && $column === $file['documentos_columna'];
                            $isAttachCol = $isImageCol || $isDocCol;
                            ?>
                            <td class="<?= $column === $isAnswer ? 'cell-answer' : '' ?>">
                                <?php if ($isAttachCol): ?>
                                    <?php
                                    $raw = (string) ($row['datos'][$column] ?? '');
                                    $links = array_values(array_filter(array_map('trim', preg_split('/[|\n\r]+/', $raw)), fn ($l) => $l !== ''));
                                    ?>
                                    <?php if (empty($links)): ?>
                                        <span class="muted">—</span>
                                    <?php else: ?>
                                        <div class="attachments">
                                            <?php foreach ($links as $link): ?>
                                                <?php if (preg_match('#^https?://#i', $link)): ?>
                                                    <a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(truncate_text((string) basename((string) parse_url($link, PHP_URL_PATH)), 30)) ?></a>
                                                <?php else: ?>
                                                    <span><?= e(truncate_text($link, 30)) ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= e(truncate_text((string) ($row['datos'][$column] ?? ''), 80)) ?>
                                <?php endif; ?>
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
                        <td class="cell-provider">
                            <?php if (!empty($row['servicio']) || !empty($row['modelo'])): ?>
                                <div class="provider-line">
                                    <span class="muted small"><?= e((string) ($row['servicio'] ?? '')) ?></span>
                                    <?php if (!empty($row['modelo'])): ?>
                                        <span class="muted small"><?= e(truncate_text((string) $row['modelo'], 28)) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['usado_fallback'])): ?>
                                        <span class="attach-tag badge-fallback">fallback</span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['intentos']) && (int) $row['intentos'] > 1): ?>
                                        <span class="muted small"><?= (int) $row['intentos'] ?> intentos</span>
                                    <?php endif; ?>
                                </div>
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
