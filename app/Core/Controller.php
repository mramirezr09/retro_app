<?php

namespace App\Core;

abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $data['request'] = $this->request;
        $data['csrf'] = $this->request->csrfToken();
        $data['flash'] = $this->flashRead();
        echo View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        Response::redirect($path);
    }

    protected function json($data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function flash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    private function flashRead(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $flash;
    }

    protected function requireCsrf(): void
    {
        if (!$this->request->verifyCsrf()) {
            if ($this->request->isAjax()) {
                Response::json(['ok' => false, 'error' => 'Token CSRF invalido'], 419);
            }
            Response::abort(419, 'Token CSRF invalido');
        }
    }
}
