<?php /** @var array $stats */ /** @var array $files */ /** @var array $prompts */ ?>
<section class="page-head">
    <div>
        <h1>Panel de control</h1>
        <p class="muted">Procesa respuestas de alumnos y genera retroalimentacion con IA.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/upload')) ?>">Subir Excel</a>
</section>

<section class="stats">
    <div class="stat"><span class="stat-value"><?= (int) $stats['archivos'] ?></span><span class="stat-label">Archivos</span></div>
    <div class="stat"><span class="stat-value"><?= (int) $stats['prompts'] ?></span><span class="stat-label">Prompts</span></div>
    <div class="stat"><span class="stat-value"><?= (int) $stats['registros'] ?></span><span class="stat-label">Registros</span></div>
    <div class="stat"><span class="stat-value"><?= (int) $stats['enviados'] ?></span><span class="stat-label">Procesados</span></div>
    <div class="stat"><span class="stat-value"><?= (int) $stats['pendientes'] ?></span><span class="stat-label">Pendientes</span></div>
</section>

<div class="grid-2">
    <section class="card">
        <h2>Ultimos archivos</h2>
        <?php if (empty($files)): ?>
            <p class="muted">Aun no hay archivos.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($files as $file): ?>
                    <li>
                        <a href="<?= e(url('/files/' . (int) $file['id'])) ?>"><?= e($file['nombre_original']) ?></a>
                        <span class="badge"><?= (int) $file['total_filas'] ?> registros</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Prompts recientes</h2>
        <?php if (empty($prompts)): ?>
            <p class="muted">Aun no hay prompts.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($prompts as $prompt): ?>
                    <li>
                        <a href="<?= e(url('/prompts/' . (int) $prompt['id'] . '/edit')) ?>"><?= e($prompt['nombre']) ?></a>
                        <span class="badge"><?= e($prompt['materia'] ?: 'sin materia') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
