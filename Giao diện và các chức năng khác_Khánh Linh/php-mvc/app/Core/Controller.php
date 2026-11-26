<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        if (!isset($data['policies'])) {
            $data['policies'] = (new \App\Models\CatalogRepository())->getPolicyMap();
        }
        extract($data, EXTR_SKIP);
        $authUser = Auth::user();
        $flash = Auth::pullFlash();

        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(404);
            echo "Không tìm thấy view: $view";
            exit;
        }

        ob_start();
        require __DIR__ . '/../Views/layouts/header.php';
        require $viewFile;
        require __DIR__ . '/../Views/layouts/footer.php';
        $html = ob_get_clean();

        $base = Router::basePath();
        if ($base !== '' && is_string($html)) {
            $html = preg_replace_callback(
                '/\b(href|action|src)=(["\'])\/(?!\/)/i',
                static fn(array $m): string => $m[1] . '=' . $m[2] . $base . '/',
                $html
            ) ?? $html;

            $html = preg_replace_callback(
                '/(["\'`])\/(api\/|admin|reception|bookings|room-services|my-|login|register|logout|forgot-password|rooms|dining|spa|conferences|lookup|assets\/)/',
                static fn(array $m): string => $m[1] . $base . '/' . $m[2],
                $html
            ) ?? $html;

            $escapedBase = str_replace('/', '\\/', $base);
            $html = str_replace('"\\/assets\\/', '"' . $escapedBase . '\\/assets\\/', $html);
        }

        echo $html;
    }

    protected function redirect(string $path, ?string $flashType = null, ?string $flashMsg = null): void
    {
        if ($flashType && $flashMsg) {
            Auth::setFlash($flashType, $flashMsg);
        }
        $base = Router::basePath();
        if ($base !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_starts_with($path, $base . '/')) {
            $path = $base . $path;
        }
        header('Location: ' . $path);
        exit;
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function authorizeWeb(array $roles): array
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login', 'err', 'Vui lòng đăng nhập để tiếp tục.');
        }
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            $this->redirect('/', 'err', 'Tài khoản của bạn không có quyền truy cập khu vực này.');
        }
        return $user;
    }
}
