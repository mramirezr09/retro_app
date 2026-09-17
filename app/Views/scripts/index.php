<?php
/** @var array $scripts */
?>
<section class="page-head">
    <div>
        <h1>Scripts</h1>
        <p class="muted">Guarda, edita y copia scripts para ejecutar en la plataforma real de los foros.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/scripts/create')) ?>">Nuevo script</a>
</section>

<section class="card">
    <?php if (empty($scripts)): ?>
        <p class="muted">No hay scripts registrados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Descripcion</th>
                    <th>Actualizado</th>
                    <th class="right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scripts as $script): ?>
                    <tr>
                        <td><?= (int) $script['id'] ?></td>
                        <td><?= e($script['nombre']) ?></td>
                        <td class="muted"><?= e(truncate_text((string) $script['descripcion'], 100)) ?></td>
                        <td><?= e($script['updated_at']) ?></td>
                        <td class="right actions">
                            <button class="btn btn-sm copy-script" type="button" data-source="script-source-<?= (int) $script['id'] ?>">Copiar</button>
                            <a class="btn btn-sm" href="<?= e(url('/scripts/' . (int) $script['id'] . '/edit')) ?>">Editar</a>
                            <form method="post" action="<?= e(url('/scripts/' . (int) $script['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('¿Eliminar este script?');">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php foreach ($scripts as $script): ?>
            <textarea id="script-source-<?= (int) $script['id'] ?>" class="script-source" aria-hidden="true" readonly><?= e((string) $script['contenido']) ?></textarea>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
