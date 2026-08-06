<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/site'): void
    {
        View::render($view, $data, $layout);
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function all(): array
    {
        return $_POST;
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

    protected function withOld(array $data): void
    {
        $_SESSION['old'] = $data;
    }

    protected function clearOld(): void
    {
        unset($_SESSION['old']);
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!Csrf::verify($token)) {
            http_response_code(419);
            die('Your session has expired. Please go back and try again.');
        }
    }
}
