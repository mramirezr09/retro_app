<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/app/Core/helpers.php';

spl_autoload_register(function (string $class) use ($basePath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $basePath . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Config;
use App\Core\Database;

Config::load($basePath . '/config/config.php');

Database::migrate();

echo "Base de datos inicializada en: " . Config::get('paths.database') . PHP_EOL;
