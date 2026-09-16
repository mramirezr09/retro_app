<?php

namespace App\Core;

class Env
{
    private static array $data = [];
    private static bool $loaded = false;
    private static string $path = '';

    public static function load(string $path): void
    {
        self::$path = $path;
        self::$data = [];

        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (strlen($value) >= 2) {
                    $first = $value[0];
                    $last = $value[strlen($value) - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }
                self::$data[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function ensureLoaded(): void
    {
        if (!self::$loaded) {
            $config = require dirname(__DIR__, 2) . '/config/config.php';
            self::load($config['paths']['env']);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::ensureLoaded();
        return self::$data[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        self::ensureLoaded();
        self::$data[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::ensureLoaded();
        unset(self::$data[$key]);
    }

    public static function all(): array
    {
        self::ensureLoaded();
        return self::$data;
    }

    public static function save(): void
    {
        self::ensureLoaded();
        $lines = [];
        foreach (self::$data as $key => $value) {
            $lines[] = $key . '=' . $value;
        }
        $content = implode(PHP_EOL, $lines) . PHP_EOL;

        $written = @file_put_contents(self::$path, $content, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException(
                'No se pudo escribir el archivo .env en ' . self::$path . '. Verifique los permisos de escritura.'
            );
        }
    }
}
