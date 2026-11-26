<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\CatalogRepository;
use App\Services\BookingWorkflowService;
use Throwable;

class CustomerController extends Controller
{
    private BookingWorkflowService $workflow;
    private CatalogRepository $repo;

    public function __construct()
    {
        $this->workflow = new BookingWorkflowService();
        $this->repo = new CatalogRepository();
    }

    public function myBookings(): void
    {
        $user = $this->authorizeWeb(['customer']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM bookings WHERE customer_id = :cid ORDER BY created_at DESC');
        $stmt->execute(['cid' => $user['id']]);
        $ids = $stmt->fetchAll();

        $bookings = [];
        foreach ($ids as $r) {
            $bookings[] = $this->repo->getBookingDetailsById((int) $r['id']);
        }

        $categories = $pdo->query('SELECT id, code, name, base_price, max_adults, max_children FROM room_categories WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
        $notifications = $this->repo->getNotificationsForUser((int) $user['id']);
        $unreadCount = $this->repo->getUnreadNotificationsCount((int) $user['id']);
        $policiesMap = $this->repo->getPolicyMap();
        $activeTab = (string) ($_GET['tab'] ?? 'bookings');
        $allowedTabs = ['bookings', 'services', 'invoices', 'notifications', 'profile'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'bookings';
        }

        $this->render('customer/portal', [
            'pageTitle'     => 'Cổng Thông Tin Khách Hàng — Mường Thanh Luxury Phú Quốc',
            'mode'          => 'my_bookings',
            'activeTab'     => $activeTab,
            'bookings'      => $bookings,
            'services'      => $this->repo->getServices(),
            'categories'    => $categories,
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'policies'      => $policiesMap,
            'minChangeDays' => (int) ($policiesMap['min_change_days'] ?? 3),
        ]);
    }

    public function showRoomServices(): void
    {
        $user = $this->authorizeWeb(['customer']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM bookings WHERE customer_id = :cid ORDER BY created_at DESC');
        $stmt->execute(['cid' => $user['id']]);

        $bookings = [];
        $eligibleBookings = [];
        foreach ($stmt->fetchAll() as $r) {
            $detail = $this->repo->getBookingDetailsById((int) $r['id']);
            if (!$detail) {
                continue;
            }
            $bookings[] = $detail;
            $isEligible = $detail['status'] === 'checked_in'
                || ($detail['status'] === 'confirmed' && empty($detail['is_overdue']));
            if ($isEligible) {
                $eligibleBookings[] = $detail;
            }
        }

        $requestedBid = (int) ($_GET['booking_id'] ?? 0);
        $codeParam = strtoupper(trim((string) ($_GET['code'] ?? '')));
        $selectedBooking = null;

        foreach ($eligibleBookings as $eb) {
            if ($requestedBid > 0 && (int) $eb['id'] === $requestedBid) {
                $selectedBooking = $eb;
                break;
            }
            if ($codeParam !== '' && (strtoupper((string) ($eb['service_access_code'] ?? '')) === $codeParam || strtoupper((string) $eb['booking_code']) === $codeParam)) {
                $selectedBooking = $eb;
                break;
            }
        }
        if (!$selectedBooking && !empty($eligibleBookings)) {
            $selectedBooking = $eligibleBookings[0];
        }

        $allVouchers = $this->repo->getVouchers(true);
        $serviceVouchers = array_values(array_filter($allVouchers, fn($v) => in_array($v['scope'] ?? 'all', ['service', 'all'], true)));

        $this->render('customer/room_services', [
            'pageTitle'        => 'Dịch Vụ Tại Phòng & Ẩm Thực — Mường Thanh Luxury Phú Quốc',
            'bookings'         => $bookings,
            'eligibleBookings' => $eligibleBookings,
            'selectedBooking'  => $selectedBooking,
            'services'         => $this->repo->getServices(),
            'serviceVouchers'  => $serviceVouchers,
            'submittedGroup'   => (string) ($_GET['submitted'] ?? ''),
        ]);
    }

    public function submitRoomServices(): void
    {
        $raw = file_get_contents('php://input');
        $isJson = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') ||
            str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $payload = $raw ? (json_decode($raw, true) ?: $_POST) : $_POST;

        if ($isJson) {
            $user = Auth::requireRole(['customer', 'receptionist', 'admin']);
            try {
                $bookingId = (int) ($payload['booking_id'] ?? 0);
                $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
                $reqTime = trim((string) ($payload['requested_time'] ?? '')) ?: 'Ngay bây giờ';
                $result = $this->workflow->orderRoomServicesBatch(
                    $user,
                    $bookingId,
                    $items,
                    $reqTime,
                    (string) ($payload['guest_note'] ?? ''),
                    (string) ($payload['voucher_code'] ?? '')
                );
                $this->json(['result' => $result], 201);
            } catch (Throwable $e) {
                $this->json(['error' => $e->getMessage()], 400);
            }
        }

        $user = $this->authorizeWeb(['customer']);
        $bookingId = (int) ($payload['booking_id'] ?? 0);
        $quantities = $payload['quantities'] ?? [];
        $items = [];
        if (is_array($quantities)) {
            foreach ($quantities as $sid => $qty) {
                $q = (int) $qty;
                if ($q > 0) {
                    $items[] = ['service_id' => (int) $sid, 'quantity' => $q];
                }
            }
        }
        try {
            $reqTime = trim((string) ($payload['requested_time'] ?? '')) ?: 'Ngay bây giờ';
            $res = $this->workflow->orderRoomServicesBatch(
                $user,
                $bookingId,
                $items,
                $reqTime,
                (string) ($payload['guest_note'] ?? ''),
                (string) ($payload['voucher_code'] ?? '')
            );
            $this->redirect("/room-services?booking_id={$bookingId}&submitted=" . urlencode($res['order_group_code']), 'ok', "Đã gửi yêu cầu dịch vụ {$res['order_group_code']} tới bộ phận Lễ tân!");
        } catch (Throwable $e) {
            $this->redirect("/room-services?booking_id={$bookingId}", 'err', $e->getMessage());
        }
    }

    public function validateVoucher(): void
    {
        $raw = file_get_contents('php://input');
        $data = $raw ? (json_decode($raw, true) ?: $_POST) : $_POST;
        $user = Auth::user();
        $rawScope = (string) ($data['scope'] ?? 'room');
        $scope = in_array($rawScope, ['room', 'service'], true) ? $rawScope : 'room';

        try {
            $res = $this->workflow->validateVoucher(
                (string) ($data['code'] ?? ''),
                (float) ($data['order_amount'] ?? 0),
                $user ? (int) $user['id'] : null,
                $scope
            );
            $this->json([
                'valid'           => true,
                'voucher'         => $res['voucher'],
                'discount_amount' => $res['discount_amount'],
            ]);
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function createBooking(): void
    {
        $raw = file_get_contents('php://input');
        $isJson = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') ||
            str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        $payload = $raw ? (json_decode($raw, true) ?: $_POST) : $_POST;

        if ($isJson) {
            $user = Auth::requireRole(['customer']);
            try {
                $booking = $this->workflow->createCustomerBooking($user, $payload);
                $full = $this->repo->getBookingDetailsById((int) $booking['id']);
                $this->json(['booking' => $full], 201);
            } catch (Throwable $e) {
                $this->json(['error' => $e->getMessage()], 400);
            }
        }

        $user = $this->authorizeWeb(['customer']);
        try {
            $booking = $this->workflow->createCustomerBooking($user, $payload);
            $this->redirect('/my-bookings', 'ok', "Đặt phòng thành công! Mã đặt phòng của bạn: {$booking['booking_code']}. Đang chờ Lễ tân xác nhận.");
        } catch (Throwable $e) {
            $this->redirect('/rooms', 'err', $e->getMessage());
        }
    }

    public function requestChange(string $id): void
    {
        $user = $this->authorizeWeb(['customer']);
        try {
            $this->workflow->requestCustomerChange($user, (int) $id, $_POST);
            $this->redirect('/my-bookings?tab=bookings', 'ok', 'Đã gửi yêu cầu tới quầy Lễ tân.');
        } catch (Throwable $e) {
            $this->redirect('/my-bookings?tab=bookings', 'err', $e->getMessage());
        }
    }

    public function respondCancel(string $id): void
    {
        $user = Auth::user();
        $verifyPhone = trim((string) ($_POST['verify_phone'] ?? ''));
        $lookupCode = trim((string) ($_POST['lookup_code'] ?? ''));
        $backUrl = $lookupCode !== ''
            ? '/lookup?code=' . rawurlencode($lookupCode) . '&phone=' . rawurlencode($verifyPhone)
            : '/my-bookings?tab=bookings';

        if (!$user && $verifyPhone === '') {
            $this->redirect('/login', 'err', 'Vui lòng đăng nhập hoặc tra cứu kèm số điện thoại để xác nhận.');
        }

        try {
            $decision = (string) ($_POST['decision'] ?? '');
            $customerNote = (string) ($_POST['customer_note'] ?? '');
            $this->workflow->respondToReceptionCancel($user ?: null, (int) $id, $decision, $customerNote, $verifyPhone);
            $msg = $decision === 'accept'
                ? 'Quý khách đã xác nhận đồng ý hủy đơn. Phòng đã được giải phóng và Lễ tân đã nhận thông báo.'
                : 'Quý khách đã từ chối đề nghị hủy — đơn đặt phòng tiếp tục được giữ nguyên và đã báo về Lễ tân.';
            $this->redirect($backUrl, 'ok', $msg);
        } catch (Throwable $e) {
            $this->redirect($backUrl, 'err', $e->getMessage());
        }
    }

    public function orderService(string $id): void
    {
        $user = $this->authorizeWeb(['customer', 'receptionist', 'admin']);
        $back = $user['role'] === 'customer' ? '/my-bookings?tab=services' : '/reception';
        try {
            $items = [
                ['service_id' => (int) ($_POST['service_id'] ?? 0), 'quantity' => max(1, (int) ($_POST['quantity'] ?? 1))],
            ];
            $reqTime = trim((string) ($_POST['requested_time'] ?? '')) ?: 'Ngay bây giờ';
            $res = $this->workflow->orderRoomServicesBatch(
                $user,
                (int) $id,
                $items,
                $reqTime,
                (string) ($_POST['guest_note'] ?? ''),
                (string) ($_POST['voucher_code'] ?? '')
            );
            $this->redirect($back, 'ok', "Đã gửi yêu cầu dịch vụ ({$res['order_group_code']}).");
        } catch (Throwable $e) {
            $this->redirect($back, 'err', $e->getMessage());
        }
    }

    public function submitReview(string $id): void
    {
        $user = $this->authorizeWeb(['customer']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id AND customer_id = :cid LIMIT 1');
        $stmt->execute(['id' => (int) $id, 'cid' => $user['id']]);
        $b = $stmt->fetch();

        if (!$b || $b['status'] !== 'checked_out') {
            $this->redirect('/my-bookings', 'err', 'Chỉ có thể đánh giá sau khi đã trả phòng.');
        }

        $existsStmt = $pdo->prepare('SELECT id FROM reviews WHERE booking_id = :bid LIMIT 1');
        $existsStmt->execute(['bid' => $b['id']]);
        if ($existsStmt->fetch()) {
            $this->redirect('/my-bookings', 'err', 'Đơn đặt phòng này đã được đánh giá trước đó.');
        }

        $overall = max(1, min(5, (int) ($_POST['overall_rating'] ?? 5)));
        $r = max(1, min(5, (int) ($_POST['room_rating'] ?? $overall)));
        $s = max(1, min(5, (int) ($_POST['service_rating'] ?? $overall)));
        $title = trim((string) ($_POST['title'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($comment === '') {
            $this->redirect('/my-bookings', 'err', 'Vui lòng nhập cảm nhận của bạn.');
        }

        $pdo->prepare('INSERT INTO reviews (booking_id, customer_id, category_id, room_rating, service_rating, overall_rating, title, comment) VALUES (:bid, :cid, :cat, :rr, :sr, :or, :tt, :cmt)')
            ->execute([
                'bid' => $b['id'], 'cid' => $user['id'], 'cat' => $b['category_id'],
                'rr' => $r, 'sr' => $s, 'or' => $overall, 'tt' => $title, 'cmt' => $comment,
            ]);

        $this->redirect('/my-bookings', 'ok', 'Cảm ơn quý khách đã gửi đánh giá kỳ nghỉ!');
    }

    public function updateProfile(): void
    {
        $user = $this->authorizeWeb(['customer']);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phoneRaw = trim((string) ($_POST['phone'] ?? ''));
        $idNumberRaw = trim((string) ($_POST['id_number'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if (mb_strlen($fullName) < 2 || $phoneRaw === '') {
            $this->redirect('/my-bookings?tab=profile', 'err', 'Họ và tên (≥ 2 ký tự) và số điện thoại không được để trống.');
        }

        try {
            $phone = BookingWorkflowService::validatePhone10Digits($phoneRaw);
            $idNumber = BookingWorkflowService::validateCccd12Digits($idNumberRaw, true);
        } catch (Throwable $e) {
            $this->redirect('/my-bookings?tab=profile', 'err', $e->getMessage());
        }

        $pdo = Database::connection();
        BookingWorkflowService::ensureUsersIdNumberColumn($pdo);

        $dup = $pdo->prepare("SELECT id, phone, id_number FROM users WHERE (REPLACE(phone, ' ', '') = :ph OR (id_number != '' AND id_number = :idn)) AND id != :uid LIMIT 1");
        $dup->execute(['ph' => $phone, 'idn' => $idNumber, 'uid' => (int) $user['id']]);
        if ($dupRow = $dup->fetch()) {
            $msg = ((string) ($dupRow['id_number'] ?? '') === $idNumber)
                ? 'Số CCCD này đã được sử dụng bởi tài khoản khác.'
                : 'Số điện thoại này đã được sử dụng bởi tài khoản khác.';
            $this->redirect('/my-bookings?tab=profile', 'err', $msg);
        }

        $pdo->prepare('UPDATE users SET full_name = :fn, phone = :ph, id_number = :idn, address = :ad WHERE id = :uid')
            ->execute(['fn' => $fullName, 'ph' => $phone, 'idn' => $idNumber, 'ad' => $address, 'uid' => (int) $user['id']]);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :uid LIMIT 1');
        $stmt->execute(['uid' => (int) $user['id']]);
        Auth::updateSessionUser($stmt->fetch());

        $this->redirect('/my-bookings?tab=profile', 'ok', 'Đã cập nhật thông tin hồ sơ cá nhân lên hệ thống MySQL.');
    }

    public function changePassword(): void
    {
        $user = $this->authorizeWeb(['customer']);
        $current = (string) ($_POST['current_password'] ?? '');
        $next = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        try {
            BookingWorkflowService::validatePasswordStrength($next);
        } catch (Throwable $e) {
            $this->redirect('/my-bookings?tab=profile', 'err', $e->getMessage());
        }
        if ($next !== $confirm) {
            $this->redirect('/my-bookings?tab=profile', 'err', 'Mật khẩu xác nhận không trùng khớp.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :uid LIMIT 1');
        $stmt->execute(['uid' => (int) $user['id']]);
        $hash = (string) $stmt->fetchColumn();
        if (!password_verify($current, $hash)) {
            $this->redirect('/my-bookings?tab=profile', 'err', 'Mật khẩu hiện tại không chính xác.');
        }

        $newHash = password_hash($next, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :uid')
            ->execute(['h' => $newHash, 'uid' => (int) $user['id']]);
        $this->workflow->createNotification(
            $pdo,
            (int) $user['id'],
            'Đổi mật khẩu thành công',
            'Mật khẩu tài khoản Hội viên của quý khách vừa được cập nhật.',
            'info'
        );

        $this->redirect('/my-bookings?tab=profile', 'ok', 'Đã thay đổi mật khẩu tài khoản thành công.');
    }

    public function markNotificationsRead(): void
    {
        $user = $this->authorizeWeb(['customer']);
        $pdo = Database::connection();
        $notifId = !empty($_POST['notification_id']) ? (int) $_POST['notification_id'] : null;
        if ($notifId) {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :nid AND user_id = :uid')
                ->execute(['nid' => $notifId, 'uid' => (int) $user['id']]);
        } else {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid')
                ->execute(['uid' => (int) $user['id']]);
        }
        $this->redirect('/my-bookings?tab=notifications', 'ok', 'Đã đánh dấu thông báo đã đọc.');
    }
}
