<?php
/** @var array|null $script */
?>
<section class="page-head">
    <div>
        <h1><?= $script ? 'Editar script' : 'Nuevo script' ?></h1>
        <p class="muted">Edita el contenido y copialo para pegarlo en la consola (F12) de la plataforma real de los foros.</p>
    </div>
    <a class="btn" href="<?= e(url('/scripts')) ?>">Volver</a>
</section>

<section class="card">
    <form method="post" action="<?= e($script ? url('/scripts/' . (int) $script['id']) : url('/scripts')) ?>" class="form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <label>
            <span>Nombre</span>
            <input type="text" name="nombre" required maxlength="150" value="<?= e($script['nombre'] ?? '') ?>" placeholder="Ej: extraer_foro.js">
        </label>

        <label>
            <span>Descripcion</span>
            <input type="text" name="descripcion" maxlength="255" value="<?= e($script['descripcion'] ?? '') ?>" placeholder="Ej: Extrae respuestas de un foro de Moodle">
        </label>

        <label>
            <span>Contenido del script</span>
            <textarea name="contenido" id="script-content" rows="22" class="code-area" spellcheck="false" required placeholder="(function () { ... })();"><?= e($script['contenido'] ?? '') ?></textarea>
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Guardar</button>
            <button class="btn copy-script" type="button" data-source="script-content">Copiar</button>
            <a class="btn" href="<?= e(url('/scripts')) ?>">Cancelar</a>
        </div>
    </form>
</section>
