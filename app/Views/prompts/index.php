<?php /** @var array $prompts */ ?>
<section class="page-head">
    <div>
        <h1>Prompts de retroalimentacion</h1>
        <p class="muted">Define las instrucciones que se enviaran como mensaje de sistema a la IA.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/prompts/create')) ?>">Nuevo prompt</a>
</section>

<section class="card">
    <?php if (empty($prompts)): ?>
        <p class="muted">No hay prompts registrados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Materia</th>
                    <th>Contenido</th>
                    <th>Actualizado</th>
                    <th class="right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prompts as $prompt): ?>
                    <tr>
                        <td><?= (int) $prompt['id'] ?></td>
                        <td><?= e($prompt['nombre']) ?></td>
                        <td><?= e($prompt['materia']) ?></td>
                        <td class="muted"><?= e(truncate_text((string) $prompt['contenido'], 90)) ?></td>
                        <td><?= e($prompt['updated_at']) ?></td>
                        <td class="right actions">
                            <a class="btn btn-sm" href="<?= e(url('/prompts/' . (int) $prompt['id'] . '/edit')) ?>">Editar</a>
                            <form method="post" action="<?= e(url('/prompts/' . (int) $prompt['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('¿Eliminar este prompt?');">
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
