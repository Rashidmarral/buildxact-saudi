<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/site'): void
    {
        extract($data);

        $viewFile = BASE_PATH . "/app/Views/{$view}.php";
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo "View not found: {$view}";
            return;
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = BASE_PATH . "/app/Views/{$layout}.php";
        require $layoutFile;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function money(float $amount, string $currency = 'SAR'): string
    {
        return number_format($amount, 2) . ' ' . $currency;
    }

    public static function old(string $key, string $default = ''): string
    {
        return self::e($_SESSION['old'][$key] ?? $default);
    }
}
