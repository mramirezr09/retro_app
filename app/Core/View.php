<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $viewFile = self::resolve($view);
        $content = self::evaluate($viewFile, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutFile = self::resolve($layout);
        $data['content'] = $content;
        return self::evaluate($layoutFile, $data);
    }

    public static function evaluate(string $__viewFile, array $__viewData): string
    {
        extract($__viewData, EXTR_SKIP);
        ob_start();
        try {
            include $__viewFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private static function resolve(string $view): string
    {
        $base = Config::get('paths.base') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR;
        $file = $base . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $view);
        }
        return $file;
    }
}
