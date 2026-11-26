<?php
declare(strict_types=1);

$appConfig = require __DIR__ . '/../config/config.php';
date_default_timezone_set((string) ($appConfig['app']['timezone'] ?? 'Asia/Ho_Chi_Minh'));

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\HomeController;
use App\Controllers\ReceptionController;
use App\Core\Router;

$router = new Router();

// Trang công khai & Đặt phòng
$router->get('/', [HomeController::class, 'index']);
$router->get('/rooms', [HomeController::class, 'rooms']);
$router->get('/dining', [HomeController::class, 'dining']);
$router->get('/spa', [HomeController::class, 'spa']);
$router->get('/conferences', [HomeController::class, 'conferences']);
$router->post('/conferences/inquiries', [HomeController::class, 'saveConferenceInquiry']);
$router->get('/lookup', [HomeController::class, 'lookup']);

// Xác thực tài khoản
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/api/auth/inline', [AuthController::class, 'inlineAuth']);
$router->post('/forgot-password/request', [AuthController::class, 'requestPasswordReset']);
$router->post('/forgot-password/reset', [AuthController::class, 'resetPasswordWithCode']);
$router->get('/logout', [AuthController::class, 'logout']);

// Nghiệp vụ Khách hàng & Đặt phòng 4 bước
$router->get('/my-bookings', [CustomerController::class, 'myBookings']);
$router->post('/my-profile', [CustomerController::class, 'updateProfile']);
$router->post('/my-password', [CustomerController::class, 'changePassword']);
$router->post('/my-notifications/read', [CustomerController::class, 'markNotificationsRead']);
$router->get('/room-services', [CustomerController::class, 'showRoomServices']);
$router->post('/room-services', [CustomerController::class, 'submitRoomServices']);
$router->post('/api/vouchers/validate', [CustomerController::class, 'validateVoucher']);
$router->post('/bookings', [CustomerController::class, 'createBooking']);
$router->post('/bookings/{id}/request-change', [CustomerController::class, 'requestChange']);
$router->post('/bookings/{id}/respond-cancel', [CustomerController::class, 'respondCancel']);
$router->post('/bookings/{id}/service-orders', [CustomerController::class, 'orderService']);
$router->post('/bookings/{id}/review', [CustomerController::class, 'submitReview']);

// Nghiệp vụ Lễ tân
$router->get('/reception', [ReceptionController::class, 'dashboard']);
$router->post('/reception/walk-in', [ReceptionController::class, 'createWalkIn']);
$router->post('/reception/bookings/{id}/confirm', [ReceptionController::class, 'confirmBooking']);
$router->post('/reception/bookings/{id}/assign-room', [ReceptionController::class, 'assignRoom']);
$router->post('/reception/bookings/{id}/resolve-request', [ReceptionController::class, 'resolveRequest']);
$router->post('/reception/bookings/{id}/update-info', [ReceptionController::class, 'updateBookingInfo']);
$router->post('/reception/bookings/{id}/check-in', [ReceptionController::class, 'checkIn']);
$router->post('/reception/bookings/{id}/modify-stay', [ReceptionController::class, 'modifyStay']);
$router->get('/reception/bookings/{id}/available-rooms', [ReceptionController::class, 'availableRooms']);
$router->get('/reception/customers/lookup', [ReceptionController::class, 'lookupCustomer']);
$router->post('/reception/service-orders', [ReceptionController::class, 'createDeskServiceOrder']);
$router->post('/reception/service-orders/{id}/status', [ReceptionController::class, 'updateServiceOrder']);
$router->post('/reception/bookings/{id}/payments', [ReceptionController::class, 'recordPayment']);
$router->post('/reception/payments/{id}/verify', [ReceptionController::class, 'verifyPayment']);
$router->post('/reception/refunds/{id}/decide', [ReceptionController::class, 'decideRefund']);
$router->post('/reception/refunds/{id}/process', [ReceptionController::class, 'processRefund']);
$router->post('/reception/bookings/{id}/check-out', [ReceptionController::class, 'checkOut']);
$router->post('/reception/incidents', [ReceptionController::class, 'createIncident']);
$router->post('/reception/incidents/{id}/status', [ReceptionController::class, 'updateIncidentStatus']);
$router->post('/reception/housekeeping/create', [ReceptionController::class, 'createHousekeepingTask']);
$router->post('/reception/housekeeping/{id}/verify', [ReceptionController::class, 'verifyHousekeeping']);
$router->post('/reception/conferences/{id}/status', [ReceptionController::class, 'updateConferenceStatus']);
$router->post('/reception/notifications/read', [ReceptionController::class, 'markNotificationsRead']);

// Nghiệp vụ Quản trị viên
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->post('/admin/categories', [AdminController::class, 'createCategory']);
$router->post('/admin/categories/{id}/update', [AdminController::class, 'updateCategory']);
$router->post('/admin/categories/{id}/toggle', [AdminController::class, 'toggleCategory']);
$router->post('/admin/categories/{id}/delete', [AdminController::class, 'deleteCategory']);
$router->post('/admin/categories/{id}/quick-rate', [AdminController::class, 'quickRateOverride']);
$router->post('/admin/rate-overrides/{id}/delete', [AdminController::class, 'deleteRateOverride']);
$router->post('/admin/rooms', [AdminController::class, 'createRoom']);
$router->post('/admin/rooms/{id}/lock', [AdminController::class, 'lockRoom']);
$router->post('/admin/rooms/{id}/unlock', [AdminController::class, 'unlockRoom']);
$router->post('/admin/rooms/{id}/status', [AdminController::class, 'updateRoomStatus']);
$router->post('/admin/services', [AdminController::class, 'createService']);
$router->post('/admin/services/{id}/update', [AdminController::class, 'updateService']);
$router->post('/admin/services/{id}/toggle', [AdminController::class, 'toggleService']);
$router->post('/admin/services/{id}/delete', [AdminController::class, 'deleteService']);
$router->post('/admin/users/{id}/toggle', [AdminController::class, 'toggleUser']);
$router->post('/admin/users/{id}/role', [AdminController::class, 'updateUserRole']);
$router->post('/admin/staff', [AdminController::class, 'createStaff']);
$router->post('/admin/policies/bulk', [AdminController::class, 'savePoliciesBulk']);
$router->post('/admin/notifications/broadcast', [AdminController::class, 'broadcastNotification']);
$router->post('/admin/refunds/{id}/decide', [AdminController::class, 'decideRefund']);
$router->post('/admin/refunds/{id}/process', [AdminController::class, 'processRefund']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
