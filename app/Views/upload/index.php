<?php /** @var int $maxBytes */ /** @var array $extensions */ ?>
<section class="page-head">
    <div>
        <h1>Cargar archivos</h1>
        <p class="muted">Formatos permitidos: <?= e(implode(', ', $extensions)) ?>. Maximo <?= (int) round($maxBytes / 1048576) ?> MB.</p>
    </div>
</section>

<section class="card">
    <form method="post" action="<?= e(url('/upload/preview')) ?>" enctype="multipart/form-data" class="form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label>
            <span>Archivo</span>
            <input type="file" name="archivo" accept=".xlsx,.csv,.zip" required>
        </label>
        <p class="muted">Excel de respuestas (.xlsx, .csv) o un ZIP con una carpeta por alumno que contenga su archivo (PDF, Word o imagen).</p>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Ver vista previa</button>
        </div>
    </form>
</section>
