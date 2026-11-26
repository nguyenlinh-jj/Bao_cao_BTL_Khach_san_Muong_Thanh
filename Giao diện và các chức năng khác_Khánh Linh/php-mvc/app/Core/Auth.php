<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function loginUser(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        self::updateSessionUser($user);
    }

    public static function updateSessionUser(array $user): void
    {
        self::startSession();
        $_SESSION['auth_user'] = [
            'id'              => (int) $user['id'],
            'full_name'       => $user['full_name'],
            'email'           => $user['email'],
            'phone'           => $user['phone'],
            'id_number'       => $user['id_number'] ?? '',
            'address'         => $user['address'] ?? '',
            'role'            => $user['role'],
            'membership_tier' => $user['membership_tier'] ?? 'Hội viên Mường Thanh',
        ];
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['auth_user'] ?? null;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
    }

    public static function setFlash(string $type, string $msg): void
    {
        self::startSession();
        $_SESSION['flash_msg'] = ['type' => $type, 'msg' => $msg];
    }

    public static function pullFlash(): ?array
    {
        self::startSession();
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);
        return $flash;
    }

    public static function requireRole(array $allowedRoles): array
    {
        $user = self::user();
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Vui lòng đăng nhập để thực hiện thao tác.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!in_array($user['role'], $allowedRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Bạn không có quyền truy cập chức năng này.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        return $user;
    }
}
