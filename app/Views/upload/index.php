<?php /** @var int $maxBytes */ /** @var array $extensions */ ?>
<section class="page-head">
    <div>
        <h1>Subir Excel de respuestas</h1>
        <p class="muted">Formatos permitidos: <?= e(implode(', ', $extensions)) ?>. Maximo <?= (int) round($maxBytes / 1048576) ?> MB.</p>
    </div>
</section>

<section class="card">
    <form method="post" action="<?= e(url('/upload/preview')) ?>" enctype="multipart/form-data" class="form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label>
            <span>Archivo</span>
            <input type="file" name="archivo" accept=".xlsx,.csv" required>
        </label>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Ver vista previa</button>
        </div>
    </form>
</section>
