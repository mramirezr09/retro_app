<?php

declare(strict_types=1);

define('RETRO_APP_START', microtime(true));

$basePath = dirname(__DIR__);

spl_autoload_register(function (string $class) use ($basePath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require $basePath . '/app/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;

Config::load($basePath . '/config/config.php');
Env::load(Config::get('paths.env'));

$debug = filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
Config::set('app.debug', $debug);

if (Config::get('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

try {
    Database::connection();
} catch (Throwable $e) {
    Response::abort(500, 'Error de base de datos: ' . $e->getMessage());
}

$request = new Request();
$router = new Router();

require $basePath . '/routes/web.php';

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    if ($request->isAjax()) {
        Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
    http_response_code(500);
    echo View::render('errors/error', [
        'message' => $e->getMessage(),
        'where'   => $e->getFile() . ':' . $e->getLine(),
        'trace'   => Config::get('app.debug') ? $e->getTraceAsString() : '',
    ]);
}
