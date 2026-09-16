<?php /** @var string $message */ /** @var string $trace */ ?>
<section class="card">
    <h1>Ocurrio un error</h1>
    <p class="error-text"><?= e($message) ?></p>
    <?php if (!empty($where)): ?>
        <p class="muted"><code><?= e($where) ?></code></p>
    <?php endif; ?>
    <?php if (!empty($trace)): ?>
        <pre class="trace"><?= e($trace) ?></pre>
    <?php endif; ?>
    <a class="btn" href="<?= e(url('/')) ?>">Volver al inicio</a>
</section>
