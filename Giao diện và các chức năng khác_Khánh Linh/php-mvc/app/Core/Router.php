<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public static function basePath(): string
    {
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($scriptDir === '' || $scriptDir === '/') {
            return '';
        }

        $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (str_starts_with($reqPath, $scriptDir)) {
            return $scriptDir;
        }

        if (str_ends_with($scriptDir, '/public')) {
            $parentDir = substr($scriptDir, 0, -7);
            if ($parentDir !== '' && str_starts_with($reqPath, $parentDir)) {
                return $parentDir;
            }
        }

        return $scriptDir;
    }

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function patch(string $path, array $handler): void
    {
        $this->routes['PATCH'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        if ($path === '' || $path === '/index.php') {
            $path = '/';
        }

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $regex = '#^' . preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$controllerClass, $action] = $handler;
                $controller = new $controllerClass();
                $controller->$action(...array_values($params));
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Endpoint không tồn tại'], JSON_UNESCAPED_UNICODE);
    }
}
