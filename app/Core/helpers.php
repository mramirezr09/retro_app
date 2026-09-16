<?php

use App\Core\Config;

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        static $base = null;
        if ($base === null) {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
            $dir = str_replace('\\', '/', dirname($script));
            $dir = rtrim($dir, '/');
            $base = (($dir === '.' || $dir === '' || $dir === '/') ? '' : $dir);
        }
        return $base;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        return base_url() . ($path === '/' ? '/' : $path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url() . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = Config::get('paths.storage');
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}

if (!function_exists('old')) {
    function old(array $data, string $key, $default = '')
    {
        return $data[$key] ?? $default;
    }
}

if (!function_exists('truncate_text')) {
    function truncate_text(?string $text, int $limit = 80): string
    {
        $text = trim((string) $text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 1) . '…';
    }
}
