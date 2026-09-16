<?php /** @var array $files */ ?>
<section class="page-head">
    <div>
        <h1>Archivos Excel</h1>
        <p class="muted">Archivos guardados y sus registros internos.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/upload')) ?>">Subir Excel</a>
</section>

<section class="card">
    <?php if (empty($files)): ?>
        <p class="muted">No hay archivos guardados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Archivo</th>
                    <th>Columnas</th>
                    <th>Respuesta</th>
                    <th>Registros</th>
                    <th>Procesados</th>
                    <th>Fecha</th>
                    <th class="right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <?php $cols = json_decode((string) $file['columnas_json'], true) ?: []; ?>
                    <tr>
                        <td><?= (int) $file['id'] ?></td>
                        <td><?= e($file['nombre_original']) ?></td>
                        <td class="muted"><?= e(truncate_text(implode(', ', $cols), 50)) ?></td>
                        <td><?= e($file['respuesta_columna']) ?></td>
                        <td><span class="badge"><?= (int) $file['total_filas'] ?></span></td>
                        <td><span class="badge badge-ok"><?= (int) $file['total_enviados'] ?></span></td>
                        <td><?= e($file['created_at']) ?></td>
                        <td class="right actions">
                            <a class="btn btn-sm btn-primary" href="<?= e(url('/files/' . (int) $file['id'])) ?>">Ver registros</a>
                            <a class="btn btn-sm" href="<?= e(url('/files/' . (int) $file['id'] . '/export')) ?>">Descargar</a>
                            <form method="post" action="<?= e(url('/files/' . (int) $file['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('¿Eliminar este archivo?');">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
