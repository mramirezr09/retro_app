<?php
/** @var string $content */
/** @var string|null $title */
/** @var array|null $flash */
$pageTitle = $title ?? 'RetroApp';
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isActive = function (string $prefix) use ($current): string {
    if ($prefix === '/') {
        return $current === '/' || $current === '' ? 'active' : '';
    }
    return str_starts_with($current, $prefix) ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · RetroApp</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e(url('/')) ?>">Retro<span>App</span></a>
        <nav class="nav">
            <a class="<?= $isActive('/') ?>" href="<?= e(url('/')) ?>">Inicio</a>
            <a class="<?= $isActive('/upload') ?>" href="<?= e(url('/upload')) ?>">Subir Excel</a>
            <a class="<?= $isActive('/files') ?>" href="<?= e(url('/files')) ?>">Archivos</a>
            <a class="<?= $isActive('/prompts') ?>" href="<?= e(url('/prompts')) ?>">Prompts</a>
            <a class="<?= $isActive('/settings') ?>" href="<?= e(url('/settings')) ?>">Ajustes</a>
        </nav>
    </div>
</header>

<main class="container">
    <?php if (!empty($flash)): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>

<footer class="footer">
    RetroApp · <?= date('Y') ?>
</footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
