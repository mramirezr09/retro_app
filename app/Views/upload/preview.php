<?php
/** @var string $stored */
/** @var string $nombreOriginal */
/** @var array $headers */
/** @var array $rows */
/** @var int $totalRows */
?>
<section class="page-head">
    <div>
        <h1>Vista previa</h1>
        <p class="muted">
            <?= e($nombreOriginal) ?> · <?= (int) $totalRows ?> filas · <?= count($headers) ?> columnas detectadas.
            Se muestran las primeras <?= count($rows) ?> filas.
        </p>
    </div>
    <a class="btn" href="<?= e(url('/upload')) ?>">Cancelar</a>
</section>

<form method="post" action="<?= e(url('/upload/save')) ?>" class="form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="archivo" value="<?= e($stored) ?>">
    <input type="hidden" name="nombre_original" value="<?= e($nombreOriginal) ?>">

    <section class="card">
        <h2>1. Columnas a conservar</h2>
        <p class="muted">Desmarca las columnas que no quieras guardar. La columna de respuesta siempre se conserva para el envio.</p>
        <div class="chip-grid">
            <?php foreach ($headers as $header): ?>
                <label class="chip">
                    <input type="checkbox" name="columnas[]" value="<?= e($header) ?>" checked>
                    <span><?= e($header) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>2. Columna con la respuesta del alumno</h2>
        <p class="muted">Este texto se enviara como mensaje del usuario (rol user).</p>
        <div class="chip-grid">
            <?php foreach ($headers as $index => $header): ?>
                <label class="chip chip-radio">
                    <input type="radio" name="respuesta_columna" value="<?= e($header) ?>" <?= $index === count($headers) - 1 ? 'checked' : '' ?> required>
                    <span><?= e($header) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>3. Muestra de datos</h2>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php foreach ($headers as $header): ?>
                            <th><?= e($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <?php foreach ($headers as $header): ?>
                                <td><?= e(truncate_text((string) ($row[$header] ?? ''), 60)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="form-actions sticky">
        <button class="btn btn-primary" type="submit">Guardar en base de datos</button>
    </div>
</form>
