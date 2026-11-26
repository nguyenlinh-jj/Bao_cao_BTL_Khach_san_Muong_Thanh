<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\BookingWorkflowService;
use PDO;
use Throwable;

class AuthController extends Controller
{
    public function __construct()
    {
        Auth::startSession();
    }

    private function ensurePasswordResetsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                identifier VARCHAR(160) NOT NULL,
                code VARCHAR(6) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_pr_user_code (user_id, code)
            ) ENGINE=InnoDB'
        );
    }

    public function showLogin(): void
    {
        Auth::startSession();
        $forgotState = $_SESSION['forgot_reset_state'] ?? null;
        $forgotManualStep2 = !empty($_SESSION['forgot_manual_step2']) ? (string) $_SESSION['forgot_manual_step2'] : '';
        $registerOld = is_array($_SESSION['auth_register_old'] ?? null) ? $_SESSION['auth_register_old'] : [];
        $loginOld = is_array($_SESSION['auth_login_old'] ?? null) ? $_SESSION['auth_login_old'] : [];
        unset($_SESSION['forgot_manual_step2'], $_SESSION['auth_register_old'], $_SESSION['auth_login_old']);

        $rawMode = (string) ($_GET['mode'] ?? '');
        $mode = in_array($rawMode, ['login', 'register', 'forgot'], true)
            ? $rawMode
            : ((!empty($forgotState) || $forgotManualStep2 !== '') ? 'forgot' : 'login');

        $this->render('auth/login', [
            'pageTitle'         => 'Đăng nhập / Đăng ký Hội viên',
            'initialMode'       => $mode,
            'forgotResetState'  => $forgotState,
            'forgotManualIdent' => $forgotManualStep2,
            'registerOld'       => $registerOld,
            'loginOld'          => $loginOld,
        ]);
    }

    public function login(): void
    {
        Auth::startSession();
        $rawIdentifier = trim((string) ($_POST['identifier'] ?? ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $_SESSION['auth_login_old'] = ['identifier' => $rawIdentifier];

        if ($rawIdentifier === '' || $password === '') {
            $this->redirect('/login', 'err', 'Vui lòng nhập Email / Số điện thoại và Mật khẩu.');
        }

        $pdo = Database::connection();
        $user = null;

        if (str_contains($rawIdentifier, '@')) {
            $email = strtolower($rawIdentifier);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->redirect('/login', 'err', 'Địa chỉ Email đăng nhập không đúng định dạng.');
            }
            $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
        } else {
            try {
                $phone = BookingWorkflowService::validatePhone10Digits($rawIdentifier, 'Số điện thoại đăng nhập');
            } catch (Throwable $e) {
                $this->redirect('/login', 'err', $e->getMessage());
            }
            $stmt = $pdo->prepare("SELECT * FROM users WHERE REPLACE(phone, ' ', '') = :phone LIMIT 1");
            $stmt->execute(['phone' => $phone]);
            $user = $stmt->fetch();
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->redirect('/login', 'err', 'Email / Số điện thoại hoặc mật khẩu không chính xác.');
        }
        if ((int) $user['is_active'] !== 1) {
            $reason = trim((string) ($user['lock_reason'] ?? ''));
            $msg = $reason !== '' ? "Tài khoản đã bị khóa: {$reason}" : 'Tài khoản này đang bị khóa bởi Quản trị viên.';
            $this->redirect('/login', 'err', $msg);
        }

        unset($_SESSION['auth_login_old']);
        Auth::loginUser($user);
        $this->logAuthAudit($user, 'USER_LOGIN', 'auth', (string) $user['id'], $this->buildLoginPurpose($user));

        if ($user['role'] === 'receptionist') {
            $this->redirect('/reception', 'ok', 'Chào mừng Lễ tân ' . $user['full_name']);
        }
        if ($user['role'] === 'admin') {
            $this->redirect('/admin', 'ok', 'Chào mừng Quản trị viên ' . $user['full_name']);
        }
        $this->redirect('/my-bookings', 'ok', 'Đăng nhập thành công!');
    }

    public function register(): void
    {
        Auth::startSession();
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phoneRaw = trim((string) ($_POST['phone'] ?? ''));
        $idNumberRaw = trim((string) ($_POST['id_number'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $_SESSION['auth_register_old'] = [
            'full_name' => $fullName,
            'email'     => $email,
            'phone'     => $phoneRaw,
            'id_number' => $idNumberRaw,
        ];

        $errors = [];
        if (mb_strlen($fullName) < 2) {
            $errors[] = 'Họ và tên phải có ít nhất 2 ký tự.';
        }
        $phone = '';
        try {
            $phone = BookingWorkflowService::validatePhone10Digits($phoneRaw);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
        $idNumber = '';
        try {
            $idNumber = BookingWorkflowService::validateCccd12Digits($idNumberRaw, true);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Địa chỉ email không đúng định dạng.';
        }
        try {
            BookingWorkflowService::validatePasswordStrength($password);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Mật khẩu xác nhận không trùng khớp.';
        }
        if (!empty($errors)) {
            $this->redirect('/login?mode=register', 'err', implode(' ', $errors));
        }

        $pdo = Database::connection();
        BookingWorkflowService::ensureUsersIdNumberColumn($pdo);

        $exists = $pdo->prepare("SELECT id, email, phone, id_number FROM users WHERE LOWER(email) = :email OR REPLACE(phone, ' ', '') = :phone OR (id_number != '' AND id_number = :idn) LIMIT 1");
        $exists->execute(['email' => $email, 'phone' => $phone, 'idn' => $idNumber]);
        if ($dup = $exists->fetch()) {
            if (strtolower((string) $dup['email']) === $email) {
                $this->redirect('/login?mode=register', 'err', 'Email này đã được đăng ký.');
            }
            if ((string) ($dup['id_number'] ?? '') === $idNumber) {
                $this->redirect('/login?mode=register', 'err', 'Số CCCD này đã được đăng ký cho một tài khoản khác.');
            }
            $this->redirect('/login?mode=register', 'err', 'Số điện thoại này đã được đăng ký.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (full_name, email, phone, id_number, address, password_hash, role, membership_tier) VALUES (:n, :e, :p, :idn, :a, :h, 'customer', 'Hội viên Mường Thanh')")
            ->execute(['n' => $fullName, 'e' => $email, 'p' => $phone, 'idn' => $idNumber, 'a' => $address, 'h' => $hash]);

        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :t, :m, 'success')")
            ->execute([
                'uid' => $newId,
                't'   => 'Chào mừng gia nhập Hội viên Mường Thanh',
                'm'   => 'Tài khoản hội viên của quý khách đã sẵn sàng. Nhập mã WELCOME để được ưu đãi ngay cho lần đặt phòng đầu tiên.',
            ]);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $newId]);
        $newUser = $stmt->fetch();
        unset($_SESSION['auth_register_old']);
        Auth::loginUser($newUser);
        $this->logAuthAudit($newUser, 'CUSTOMER_REGISTER', 'user', (string) $newId, "Khách hàng {$fullName} ({$email}) đăng ký tài khoản mới.");

        $this->redirect('/my-bookings', 'ok', 'Đăng ký tài khoản Hội viên Mường Thanh thành công!');
    }

    /**
     * Đăng ký / Đăng nhập Hội viên trực tiếp ở Bước 2 của luồng đặt phòng mà không làm mất lựa chọn phòng.
     */
    public function inlineAuth(): void
    {
        Auth::startSession();
        $raw = file_get_contents('php://input');
        $data = $raw ? (json_decode($raw, true) ?: $_POST) : $_POST;
        $action = (string) ($data['action'] ?? 'login');
        $pdo = Database::connection();
        BookingWorkflowService::ensureUsersIdNumberColumn($pdo);

        if ($action === 'register') {
            $fullName = trim((string) ($data['full_name'] ?? ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $phoneRaw = trim((string) ($data['phone'] ?? ''));
            $idNumberRaw = trim((string) ($data['id_number'] ?? ''));
            $address = trim((string) ($data['address'] ?? ''));
            $password = (string) ($data['password'] ?? '');
            $confirmPassword = array_key_exists('confirm_password', $data) ? (string) $data['confirm_password'] : $password;

            $fieldErrors = [];
            if (mb_strlen($fullName) < 2) {
                $fieldErrors['full_name'] = 'Họ và tên phải có ít nhất 2 ký tự.';
            }
            $phone = '';
            try {
                $phone = BookingWorkflowService::validatePhone10Digits($phoneRaw);
            } catch (Throwable $e) {
                $fieldErrors['phone'] = $e->getMessage();
            }
            $idNumber = '';
            try {
                $idNumber = BookingWorkflowService::validateCccd12Digits($idNumberRaw, true);
            } catch (Throwable $e) {
                $fieldErrors['id_number'] = $e->getMessage();
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = 'Email không đúng định dạng.';
            }
            try {
                BookingWorkflowService::validatePasswordStrength($password);
            } catch (Throwable $e) {
                $fieldErrors['password'] = $e->getMessage();
            }
            if ($password !== $confirmPassword) {
                $fieldErrors['confirm_password'] = 'Mật khẩu xác nhận không trùng khớp.';
            }
            if (!empty($fieldErrors)) {
                $this->json([
                    'error'  => implode(' ', array_values($fieldErrors)),
                    'errors' => $fieldErrors,
                ], 400);
            }

            $exists = $pdo->prepare("SELECT id, email, phone, id_number FROM users WHERE LOWER(email) = :email OR REPLACE(phone, ' ', '') = :phone OR (id_number != '' AND id_number = :idn) LIMIT 1");
            $exists->execute(['email' => $email, 'phone' => $phone, 'idn' => $idNumber]);
            if ($dup = $exists->fetch()) {
                if ((string) ($dup['id_number'] ?? '') === $idNumber) {
                    $this->json(['error' => 'Số CCCD này đã tồn tại trên hệ thống Hội viên. Vui lòng chuyển sang tab "Đã có tài khoản".'], 400);
                }
                $this->json(['error' => 'Email hoặc Số điện thoại này đã tồn tại tài khoản Hội viên. Vui lòng chuyển sang tab "Đã có tài khoản".'], 400);
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (full_name, email, phone, id_number, address, password_hash, role, membership_tier) VALUES (:n, :e, :p, :idn, :a, :h, 'customer', 'Hội viên Mường Thanh')")
                ->execute(['n' => $fullName, 'e' => $email, 'p' => $phone, 'idn' => $idNumber, 'a' => $address, 'h' => $hash]);

            $newId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :t, :m, 'success')")
                ->execute([
                    'uid' => $newId,
                    't'   => 'Chào mừng gia nhập Hội viên Mường Thanh',
                    'm'   => 'Tài khoản hội viên của quý khách đã sẵn sàng. Nhập mã WELCOME để được ưu đãi ngay cho lần đặt phòng đầu tiên.',
                ]);

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute(['id' => $newId]);
            $user = $stmt->fetch();
            Auth::loginUser($user);
            $this->logAuthAudit($user, 'CUSTOMER_REGISTER', 'user', (string) $newId, "Khách hàng {$fullName} đăng ký tài khoản trực tiếp khi đặt phòng.");

            $this->json(['user' => Auth::user()]);
        }

        // login by email or 10-digit phone
        $rawIdentifier = trim((string) ($data['email'] ?? ($data['identifier'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if ($rawIdentifier === '' || $password === '') {
            $this->json(['error' => 'Vui lòng nhập Email/SĐT và Mật khẩu.'], 400);
        }

        if (str_contains($rawIdentifier, '@')) {
            $email = strtolower($rawIdentifier);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->json(['error' => 'Địa chỉ Email không đúng định dạng.'], 400);
            }
            $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = :id1 LIMIT 1');
            $stmt->execute(['id1' => $email]);
            $user = $stmt->fetch();
        } else {
            try {
                $phone = BookingWorkflowService::validatePhone10Digits($rawIdentifier, 'Số điện thoại đăng nhập');
            } catch (Throwable $e) {
                $this->json(['error' => $e->getMessage()], 400);
            }
            $stmt = $pdo->prepare("SELECT * FROM users WHERE REPLACE(phone, ' ', '') = :id2 LIMIT 1");
            $stmt->execute(['id2' => $phone]);
            $user = $stmt->fetch();
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->json(['error' => 'Thông tin đăng nhập không chính xác.'], 401);
        }
        if ((int) $user['is_active'] !== 1) {
            $reason = trim((string) ($user['lock_reason'] ?? ''));
            $msg = $reason !== '' ? "Tài khoản đã bị khóa: {$reason}" : 'Tài khoản này đang bị khóa bởi Quản trị viên.';
            $this->json(['error' => $msg], 403);
        }

        Auth::loginUser($user);
        $this->logAuthAudit($user, 'USER_LOGIN', 'auth', (string) $user['id'], $this->buildLoginPurpose($user));
        $this->json(['user' => Auth::user()]);
    }

    public function requestPasswordReset(): void
    {
        Auth::startSession();
        $raw = file_get_contents('php://input');
        $isJson = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') ||
            str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $data = $raw ? (json_decode($raw, true) ?: $_POST) : $_POST;
        $identifier = trim((string) ($data['identifier'] ?? ''));

        if ($identifier === '') {
            if ($isJson) {
                $this->json(['error' => 'Vui lòng nhập Email hoặc Số điện thoại (10 chữ số) đã đăng ký.'], 400);
            }
            $this->redirect('/login?mode=forgot', 'err', 'Vui lòng nhập Email hoặc Số điện thoại (10 chữ số) đã đăng ký.');
        }

        if (str_contains($identifier, '@')) {
            if (!filter_var(strtolower($identifier), FILTER_VALIDATE_EMAIL)) {
                $msg = 'Địa chỉ Email không đúng định dạng.';
                if ($isJson) {
                    $this->json(['error' => $msg], 400);
                }
                $this->redirect('/login?mode=forgot', 'err', $msg);
            }
        } else {
            try {
                $identifier = BookingWorkflowService::validatePhone10Digits($identifier, 'Số điện thoại');
            } catch (Throwable $e) {
                if ($isJson) {
                    $this->json(['error' => $e->getMessage()], 400);
                }
                $this->redirect('/login?mode=forgot', 'err', $e->getMessage());
            }
        }

        $pdo = Database::connection();
        $this->ensurePasswordResetsTable($pdo);

        $lowerId = strtolower($identifier);
        $digits = preg_replace('/\D+/', '', $identifier) ?? '';
        $users = $pdo->query('SELECT * FROM users')->fetchAll();
        $matched = null;
        foreach ($users as $u) {
            $uEmail = strtolower(trim((string) $u['email']));
            $uPhoneDigits = preg_replace('/\D+/', '', (string) $u['phone']) ?? '';
            if ($uEmail === $lowerId || ($digits !== '' && $uPhoneDigits === $digits) || trim((string) $u['phone']) === $identifier) {
                $matched = $u;
                break;
            }
        }

        if (!$matched) {
            $msg = 'Không tìm thấy tài khoản nào khớp với Email hoặc Số điện thoại vừa nhập.';
            if ($isJson) {
                $this->json(['error' => $msg], 404);
            }
            $this->redirect('/login?mode=forgot', 'err', $msg);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresUnix = time() + 15 * 60;
        $expiresAt = date('Y-m-d H:i:s', $expiresUnix);

        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
            ->execute(['uid' => (int) $matched['id']]);
        $pdo->prepare('INSERT INTO password_resets (user_id, identifier, code, expires_at) VALUES (:uid, :ident, :code, DATE_ADD(NOW(), INTERVAL 15 MINUTE))')
            ->execute([
                'uid'   => (int) $matched['id'],
                'ident' => $identifier,
                'code'  => $code,
            ]);

        $this->logAuthAudit($matched, 'REQUEST_PASSWORD_RESET', 'user', (string) $matched['id'], "Tạo mã xác nhận 6 số đặt lại mật khẩu cho {$matched['email']}.");

        if ($isJson) {
            $this->json([
                'code'               => $code,
                'expires_at'         => $expiresAt,
                'expires_unix'       => $expiresUnix,
                'expires_in_seconds' => 900,
                'account'            => [
                    'full_name' => (string) $matched['full_name'],
                    'email'     => (string) $matched['email'],
                ],
            ]);
        }

        $_SESSION['forgot_reset_state'] = [
            'user_id'      => (int) $matched['id'],
            'identifier'   => $identifier,
            'code'         => $code,
            'expires_at'   => $expiresAt,
            'expires_unix' => $expiresUnix,
            'full_name'    => (string) $matched['full_name'],
        ];
        $this->redirect('/login?mode=forgot', 'ok', "Đã tạo mã xác nhận 6 số ({$code}). Vui lòng nhập mã và đặt mật khẩu mới bên dưới trong vòng 15 phút.");
    }

    public function resetPasswordWithCode(): void
    {
        Auth::startSession();
        $raw = file_get_contents('php://input');
        $isJson = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') ||
            str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $data = $raw ? (json_decode($raw, true) ?: $_POST) : $_POST;

        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($identifier === '' && !empty($_SESSION['forgot_reset_state']['identifier'])) {
            $identifier = trim((string) $_SESSION['forgot_reset_state']['identifier']);
        }
        $code = preg_replace('/\D+/', '', (string) ($data['code'] ?? '')) ?? '';
        $newPassword = (string) ($data['new_password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');

        $keepStep2 = function () use ($identifier): void {
            if (empty($_SESSION['forgot_reset_state'])) {
                $_SESSION['forgot_manual_step2'] = $identifier !== '' ? $identifier : '1';
            }
        };

        $errors = [];
        if ($identifier === '') {
            $errors['identifier'] = 'Vui lòng nhập Email hoặc Số điện thoại (10 chữ số) của tài khoản.';
        } elseif (!str_contains($identifier, '@')) {
            try {
                $identifier = BookingWorkflowService::validatePhone10Digits($identifier, 'Số điện thoại');
            } catch (Throwable $e) {
                $errors['identifier'] = $e->getMessage();
            }
        } elseif (!filter_var(strtolower($identifier), FILTER_VALIDATE_EMAIL)) {
            $errors['identifier'] = 'Địa chỉ Email không đúng định dạng.';
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            $errors['code'] = 'Mã xác nhận phải gồm đúng 6 chữ số.';
        }
        try {
            BookingWorkflowService::validatePasswordStrength($newPassword);
        } catch (Throwable $e) {
            $errors['new_password'] = $e->getMessage();
        }
        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Mật khẩu xác nhận không trùng khớp.';
        }
        if (!empty($errors)) {
            $msg = implode(' ', array_values($errors));
            if ($isJson) {
                $this->json(['error' => $msg, 'errors' => $errors], 400);
            }
            $keepStep2();
            $this->redirect('/login?mode=forgot', 'err', $msg);
        }

        $pdo = Database::connection();
        $this->ensurePasswordResetsTable($pdo);

        $lowerId = strtolower($identifier);
        $digits = preg_replace('/\D+/', '', $identifier) ?? '';
        $users = $pdo->query('SELECT * FROM users')->fetchAll();
        $matched = null;
        foreach ($users as $u) {
            $uEmail = strtolower(trim((string) $u['email']));
            $uPhoneDigits = preg_replace('/\D+/', '', (string) $u['phone']) ?? '';
            if ($uEmail === $lowerId || ($digits !== '' && $uPhoneDigits === $digits) || trim((string) $u['phone']) === $identifier) {
                $matched = $u;
                break;
            }
        }
        if (!$matched) {
            $msg = 'Tài khoản không tồn tại.';
            if ($isJson) {
                $this->json(['error' => $msg], 404);
            }
            $keepStep2();
            $this->redirect('/login?mode=forgot', 'err', $msg);
        }

        $prStmt = $pdo->prepare(
            'SELECT *, CASE WHEN expires_at >= NOW() THEN 1 ELSE 0 END AS is_not_expired
             FROM password_resets
             WHERE user_id = :uid AND used_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $prStmt->execute(['uid' => (int) $matched['id']]);
        $prRow = $prStmt->fetch();

        $verifiedByDb = $prRow && trim((string) $prRow['code']) === $code && (int) ($prRow['is_not_expired'] ?? 0) === 1;
        $sessionState = is_array($_SESSION['forgot_reset_state'] ?? null) ? $_SESSION['forgot_reset_state'] : null;
        $verifiedBySession = !$prRow && $sessionState
            && (int) ($sessionState['user_id'] ?? 0) === (int) $matched['id']
            && hash_equals(trim((string) ($sessionState['code'] ?? '')), $code)
            && time() <= (int) ($sessionState['expires_unix'] ?? 0);

        if (!$verifiedByDb && !$verifiedBySession) {
            if ($prRow && trim((string) $prRow['code']) === $code && (int) ($prRow['is_not_expired'] ?? 0) === 0) {
                $msg = 'Mã OTP đã hết hạn (quá 15 phút). Vui lòng nhấn "Cấp lại mã OTP mới".';
            } elseif ($prRow && trim((string) $prRow['code']) !== $code) {
                $msg = 'Mã OTP 6 chữ số không khớp với mã vừa cấp mới nhất. Vui lòng kiểm tra lại.';
            } else {
                $msg = 'Chưa tìm thấy mã OTP hợp lệ cho tài khoản này hoặc mã đã được sử dụng. Vui lòng cấp mã OTP mới.';
            }
            if ($isJson) {
                $this->json(['error' => $msg], 400);
            }
            $keepStep2();
            $this->redirect('/login?mode=forgot', 'err', $msg);
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :uid')
            ->execute(['h' => $hash, 'uid' => (int) $matched['id']]);
        if ($prRow) {
            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :prid')
                ->execute(['prid' => (int) $prRow['id']]);
        }
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
            ->execute(['uid' => (int) $matched['id']]);
        $this->logAuthAudit($matched, 'PASSWORD_RESET', 'user', (string) $matched['id'], "Đặt lại mật khẩu bằng mã xác nhận 6 số thành công.");

        unset($_SESSION['forgot_reset_state'], $_SESSION['forgot_manual_step2']);

        if ($isJson) {
            $this->json(['ok' => true, 'message' => 'Đặt lại mật khẩu thành công. Quý khách có thể đăng nhập bằng mật khẩu mới ngay bây giờ.']);
        }
        $this->redirect('/login?mode=login', 'ok', 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập bằng mật khẩu mới.');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/', 'ok', 'Đã đăng xuất khỏi hệ thống.');
    }

    private function buildLoginPurpose(array $user): string
    {
        $rolePurpose = match ($user['role'] ?? 'customer') {
            'admin'        => 'Truy cập hệ thống Quản trị viên (Admin Console)',
            'receptionist' => 'Truy cập bàn nghiệp vụ Lễ tân (PMS)',
            default        => 'Truy cập cổng Hội viên Khách hàng',
        };
        return "{$user['full_name']} ({$user['email']}) đăng nhập — {$rolePurpose}.";
    }

    private function logAuthAudit(array $user, string $action, string $entityType, string $entityId, string $details): void
    {
        Database::connection()->prepare(
            'INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details)
             VALUES (:uid, :name, :role, :act, :et, :eid, :det)'
        )->execute([
            'uid'  => $user['id'],
            'name' => $user['full_name'],
            'role' => $user['role'],
            'act'  => $action,
            'et'   => $entityType,
            'eid'  => $entityId,
            'det'  => $details,
        ]);
    }
}
