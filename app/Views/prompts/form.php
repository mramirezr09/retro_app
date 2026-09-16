<?php /** @var array|null $prompt */ ?>
<section class="page-head">
    <div>
        <h1><?= $prompt ? 'Editar prompt' : 'Nuevo prompt' ?></h1>
        <p class="muted">Este texto se envia como mensaje de sistema (rol system).</p>
    </div>
    <a class="btn" href="<?= e(url('/prompts')) ?>">Volver</a>
</section>

<section class="card">
    <form method="post" action="<?= e($prompt ? url('/prompts/' . (int) $prompt['id']) : url('/prompts')) ?>" class="form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <label>
            <span>Nombre</span>
            <input type="text" name="nombre" required maxlength="150" value="<?= e($prompt['nombre'] ?? '') ?>" placeholder="Ej: Retroalimentacion de ensayo">
        </label>

        <label>
            <span>Materia</span>
            <input type="text" name="materia" maxlength="150" value="<?= e($prompt['materia'] ?? '') ?>" placeholder="Ej: Historia">
        </label>

        <label>
            <span>Contenido del prompt</span>
            <textarea name="contenido" rows="10" required placeholder="Eres un docente que da retroalimentacion constructiva..."><?= e($prompt['contenido'] ?? '') ?></textarea>
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Guardar</button>
            <a class="btn" href="<?= e(url('/prompts')) ?>">Cancelar</a>
        </div>
    </form>
</section>
