<?php
/**
 * ============================================================================
 *  BTL WEB DEHA - NHÓM 8 | WEBSITE ĐẶT PHÒNG KHÁCH SẠN MƯỜNG THANH PHÚ QUỐC
 *  FOLDER: Lễ tân   |   NGƯỜI PHỤ TRÁCH: Lê Hoài Linh
 * ============================================================================
 *  TỆP GỐC  : app/Controllers/ReceptionController.php  (802 dòng)
 *  PHẦN GIỮ LẠI: 21/25 hàm thuộc mục công việc:
 *    Bảng điều khiển, đặt phòng (danh sách + sơ đồ phòng), dịch vụ (quản lý và điều phối), thanh toán và đối soát hóa đơn; sự cố phòng và buồng phòng
 *  KHAI BÁO : giữ nguyên const/thuộc tính của lớp (dòng 17-29)
 *  DÒNG GỐC : dòng khai báo 17-29, 43-263, 264-278, 279-290, 291-305, 306-328, 408-426, 427-445, 446-456, 457-482, 483-501, 502-525, 526-542, 543-564, 565-582, 583-606, 607-624, 625-665, 666-716, 717-759, 794-801, 36-42
 * ----------------------------------------------------------------------------
 *  CÁC HÀM TRONG TỆP NÀY:
 *    - dashboard()   (dòng 43-263)
 *    - createWalkIn()   (dòng 264-278)
 *    - confirmBooking()   (dòng 279-290)
 *    - assignRoom()   (dòng 291-305)
 *    - availableRooms()   (dòng 306-328)
 *    - resolveRequest()   (dòng 408-426)
 *    - checkIn()   (dòng 427-445)
 *    - modifyStay()   (dòng 446-456)
 *    - createDeskServiceOrder()   (dòng 457-482)
 *    - updateServiceOrder()   (dòng 483-501)
 *    - recordPayment()   (dòng 502-525)
 *    - verifyPayment()   (dòng 526-542)
 *    - decideRefund()   (dòng 543-564)
 *    - processRefund()   (dòng 565-582)
 *    - checkOut()   (dòng 583-606)
 *    - createIncident()   (dòng 607-624)
 *    - updateIncidentStatus()   (dòng 625-665)
 *    - createHousekeepingTask()   (dòng 666-716)
 *    - verifyHousekeeping()   (dòng 717-759)
 *    - markNotificationsRead()   (dòng 794-801)
 *    - targetUrl()   (dòng 36-42)
 *
 *  GHI CHÚ:
    Khung khai báo (const VALID_TABS, các thuộc tính) giữ trong tệp này. 4 hàm còn lại (__construct, lookupCustomer, updateBookingInfo, updateConferenceStatus) thuộc folder lead.
 * ============================================================================
 */
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Booking;
use App\Models\CatalogRepository;
use App\Models\Refund;
use App\Models\RoomCategory;
use App\Services\BookingWorkflowService;
use Throwable;

class ReceptionController extends Controller
{
    private BookingWorkflowService $workflow;
    private CatalogRepository $repo;

    private const VALID_TABS = [
        'dashboard',
        'bookings_list',
        'room_rack',
        'conferences',
        'services',
        'payments',
        'customers',
    ];

    public function dashboard(): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        $pdo = Database::connection();

        $rawTab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'dashboard';
        $activeTab = in_array($rawTab, self::VALID_TABS, true) ? $rawTab : 'dashboard';

        $today = date('Y-m-d');
        $rawRackDate = trim((string) ($_GET['rack_date'] ?? $today));
        $rackDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawRackDate) ? $rawRackDate : $today;

        $search = trim((string) ($_GET['search'] ?? ''));
        $sql = 'SELECT id FROM bookings WHERE 1=1';
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (booking_code LIKE :s1 OR guest_name LIKE :s2 OR guest_phone LIKE :s3)';
            $like = "%$search%";
            $params = ['s1' => $like, 's2' => $like, 's3' => $like];
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 120';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = [];
        foreach ($stmt->fetchAll() as $row) {
            $detail = $this->repo->getBookingDetailsById((int) $row['id']);
            if ($detail !== null) {
                $bookings[] = $detail;
            }
        }

        $rooms = $pdo->query(
            'SELECT r.*, rc.name AS category_name, rc.code AS category_code, rc.base_price
             FROM rooms r
             JOIN room_categories rc ON r.category_id = rc.id
             ORDER BY r.floor ASC, r.room_number ASC'
        )->fetchAll();

        $serviceOrders = $pdo->query(
            "SELECT so.*, b.booking_code, b.guest_name, r.room_number,
                    sv.category AS service_category,
                    ua.full_name AS accepted_by_name,
                    uc.full_name AS completed_by_name
             FROM service_orders so
             JOIN bookings b ON so.booking_id = b.id
             LEFT JOIN services sv ON so.service_id = sv.id
             LEFT JOIN rooms r ON b.assigned_room_id = r.id
             LEFT JOIN users ua ON so.accepted_by = ua.id
             LEFT JOIN users uc ON so.completed_by = uc.id
             WHERE b.status IN ('pending', 'confirmed', 'checked_in', 'checked_out')
             ORDER BY FIELD(so.status, 'pending', 'accepted', 'completed', 'cancelled'), so.created_at DESC, so.id DESC"
        )->fetchAll();

        $housekeeping = $pdo->query(
            'SELECT ht.*, r.room_number, rc.name AS category_name
             FROM housekeeping_tasks ht
             JOIN rooms r ON ht.room_id = r.id
             JOIN room_categories rc ON r.category_id = rc.id
             ORDER BY FIELD(ht.status, "pending", "in_progress", "completed", "verified"), ht.created_at DESC, ht.id DESC'
        )->fetchAll();

        $incidents = $pdo->query(
            'SELECT ri.*, u.full_name AS reported_by_name
             FROM room_incidents ri
             LEFT JOIN users u ON ri.reported_by = u.id
             ORDER BY FIELD(ri.status, "open", "resolved"), ri.created_at DESC, ri.id DESC'
        )->fetchAll();

        $pendingPayments = $pdo->query(
            "SELECT p.*, b.booking_code, b.guest_name, b.guest_phone, b.total_estimated, b.deposit_required,
                    b.payment_plan, b.check_in_date, b.check_out_date, b.status AS booking_status, r.room_number,
                    u.full_name AS recorded_by_name,
                    inv.invoice_code, inv.total_amount AS invoice_total_amount, inv.payment_status AS invoice_payment_status,
                    COALESCE((SELECT SUM(p2.amount) FROM payments p2 WHERE p2.booking_id = b.id AND p2.verification_status = 'verified'), 0) AS booking_verified_paid,
                    COALESCE((SELECT SUM(p3.amount) FROM payments p3 WHERE p3.booking_id = b.id AND p3.verification_status = 'pending'), 0) AS booking_pending_paid
             FROM payments p
             JOIN bookings b ON p.booking_id = b.id
             LEFT JOIN rooms r ON b.assigned_room_id = r.id
             LEFT JOIN users u ON p.recorded_by = u.id
             LEFT JOIN invoices inv ON inv.booking_id = b.id
             ORDER BY FIELD(p.verification_status, 'pending', 'rejected', 'verified'), p.recorded_at DESC, p.id DESC
             LIMIT 150"
        )->fetchAll();

        $conferenceInquiries = $pdo->query(
            'SELECT * FROM conference_inquiries ORDER BY FIELD(status, "new", "contacted", "confirmed", "cancelled"), created_at DESC, id DESC'
        )->fetchAll();

        // Build CRM Customers list merging member users and walk-in bookings
        $memberUsers = $pdo->query(
            "SELECT id, full_name, email, phone, address, membership_tier
             FROM users WHERE role = 'customer' AND is_active = 1
             ORDER BY id ASC"
        )->fetchAll();

        $custMap = [];
        foreach ($memberUsers as $u) {
            $key = 'u-' . $u['id'];
            $custMap[$key] = [
                'key'                 => $key,
                'customer_id'         => (int) $u['id'],
                'full_name'           => (string) $u['full_name'],
                'phone'               => (string) $u['phone'],
                'email'               => (string) $u['email'],
                'address'             => (string) ($u['address'] ?? ''),
                'guest_id_number'     => '',
                'membership_tier'     => (string) ($u['membership_tier'] ?: 'Hội viên Mường Thanh'),
                'bookings_count'      => 0,
                'total_spent'         => 0.0,
                'latest_booking_code' => null,
                'bookings'            => [],
            ];
        }

        foreach ($bookings as $b) {
            $normPhone = preg_replace('/\s+/', '', (string) ($b['guest_phone'] ?? ''));
            $key = !empty($b['customer_id']) ? ('u-' . $b['customer_id']) : ('p-' . ($normPhone !== '' ? $normPhone : $b['booking_code']));
            if (!isset($custMap[$key])) {
                $custMap[$key] = [
                    'key'                 => $key,
                    'customer_id'         => null,
                    'full_name'           => (string) ($b['guest_name'] ?? ''),
                    'phone'               => (string) ($b['guest_phone'] ?? ''),
                    'email'               => (string) ($b['guest_email'] ?? ''),
                    'address'             => '',
                    'guest_id_number'     => (string) ($b['guest_id_number'] ?? ''),
                    'membership_tier'     => 'Khách tại quầy',
                    'bookings_count'      => 0,
                    'total_spent'         => 0.0,
                    'latest_booking_code' => null,
                    'bookings'            => [],
                ];
            }
            $custMap[$key]['bookings_count'] += 1;
            if (($b['status'] ?? '') !== 'cancelled') {
                $custMap[$key]['total_spent'] += (float) ($b['total_estimated'] ?? 0);
            }
            if ($custMap[$key]['guest_id_number'] === '' && !empty($b['guest_id_number'])) {
                $custMap[$key]['guest_id_number'] = (string) $b['guest_id_number'];
            }
            if ($custMap[$key]['latest_booking_code'] === null) {
                $custMap[$key]['latest_booking_code'] = (string) $b['booking_code'];
            }
            $custMap[$key]['bookings'][] = [
                'id'              => (int) $b['id'],
                'booking_code'    => (string) $b['booking_code'],
                'category_name'   => (string) ($b['category_name'] ?? ''),
                'check_in_date'   => (string) $b['check_in_date'],
                'check_out_date'  => (string) $b['check_out_date'],
                'total_estimated' => (float) ($b['total_estimated'] ?? 0),
                'status'          => (string) $b['status'],
            ];
        }
        $customers = array_values($custMap);
        usort($customers, fn($a, $b) => $b['total_spent'] <=> $a['total_spent']);

        $rawCategories = $pdo->query('SELECT * FROM room_categories WHERE is_active = 1 ORDER BY base_price ASC')->fetchAll();
        $packages = $this->repo->getPackages();
        $catModel = new RoomCategory();
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $categories = [];
        foreach ($rawCategories as $cat) {
            $eval = $catModel->evaluateCategoryPricing($cat, $today, $tomorrow);
            $cat['effective_base_price'] = $eval['effective_base_price'];
            $cat['is_stop_sell'] = $eval['is_stop_sell'];
            $cat['stop_sell_note'] = $eval['stop_sell_note'];
            $cat['resolved_packages'] = $catModel->resolvePackagesForCategory($cat, $packages, (float) $eval['effective_base_price']);
            $categories[] = $cat;
        }

        $policies = $this->repo->getPolicyMap();
        $bankInfo = [
            'bankName'      => (string) ($policies['bank_name'] ?? 'BIDV - Ngân hàng TMCP Đầu tư và Phát triển Việt Nam'),
            'accountNo'     => (string) ($policies['bank_account_no'] ?? '75010008889999'),
            'accountHolder' => (string) ($policies['bank_account_holder'] ?? 'MUONG THANH LUXURY PHU QUOC'),
        ];
        $operationalRoomsCount = count(array_filter($rooms, fn($r) => !in_array((string) ($r['status'] ?? ''), ['maintenance', 'out_of_order'], true)));
        $todayBookedStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE status IN ('pending', 'confirmed', 'checked_in')
               AND check_in_date <= :d1 AND check_out_date > :d2"
        );
        $todayBookedStmt->execute(['d1' => $today, 'd2' => $today]);
        $availableRoomsToday = max(0, $operationalRoomsCount - (int) $todayBookedStmt->fetchColumn());

        $refundRequests = (new Refund())->listAllDetailed(120);
        $vouchers = $this->repo->getVouchers(true);
        $services = $this->repo->getServices(true);
        $notifications = $this->repo->getNotificationsForUser((int) $staff['id'], 30);
        $unreadCount = $this->repo->getUnreadNotificationsCount((int) $staff['id']);

        $this->render('reception/dashboard', [
            'pageTitle'           => 'Bàn Lễ Tân · PMS Console',
            'hidePublicChrome'    => true,
            'staffUser'           => $staff,
            'activeTab'           => $activeTab,
            'rackDate'            => $rackDate,
            'today'               => $today,
            'availableRoomsToday' => $availableRoomsToday,
            'bankInfo'            => $bankInfo,
            'bookings'            => $bookings,
            'rooms'               => $rooms,
            'serviceOrders'       => $serviceOrders,
            'housekeeping'        => $housekeeping,
            'incidents'           => $incidents,
            'pendingPayments'     => $pendingPayments,
            'refundRequests'      => $refundRequests,
            'customers'           => $customers,
            'conferenceInquiries' => $conferenceInquiries,
            'categories'          => $categories,
            'packages'            => $packages,
            'services'            => $services,
            'vouchers'            => $vouchers,
            'policies'            => $policies,
            'notifications'       => $notifications,
            'unreadCount'         => $unreadCount,
            'search'              => $search,
        ]);
    }


    public function createWalkIn(): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $res = $this->workflow->createWalkInBooking($staff, $_POST);
            $this->redirect(
                $this->targetUrl('bookings_list'),
                'ok',
                "Đã tạo đặt phòng tại quầy {$res['booking_code']} (Phòng {$res['assigned_room_number']})."
            );
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }


    public function confirmBooking(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $roomId = !empty($_POST['assigned_room_id']) ? (int) $_POST['assigned_room_id'] : null;
            $this->workflow->confirmAndAssignRoom($staff, (int) $id, $roomId);
            $this->redirect($this->targetUrl('bookings_list'), 'ok', "Đã xác nhận đặt phòng #$id thành công.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }


    public function assignRoom(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $roomId = (int) ($_POST['assigned_room_id'] ?? 0);
            if ($roomId <= 0) {
                throw new \RuntimeException('Vui lòng chọn phòng vật lý.');
            }
            $roomNum = $this->workflow->assignRoomOnly($staff, (int) $id, $roomId);
            $this->redirect($this->targetUrl('bookings_list'), 'ok', "Đã cập nhật gán Phòng {$roomNum} cho đặt phòng #$id.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }


    public function availableRooms(string $id): void
    {
        $this->authorizeApi(['receptionist', 'admin']);
        $b = (new Booking())->find((int) $id);
        if (!$b) {
            $this->json(['error' => 'Không tìm thấy đơn đặt phòng.'], 404);
        }
        $requireReady = !empty($_GET['ready_only']);
        $rooms = $this->workflow->getAvailablePhysicalRooms(
            (int) $b['category_id'],
            (string) $b['check_in_date'],
            (string) $b['check_out_date'],
            (int) $b['id'],
            $requireReady
        );
        $pdo = Database::connection();
        $catName = (string) ($pdo->query('SELECT name FROM room_categories WHERE id = ' . (int) $b['category_id'])->fetchColumn() ?: '');
        foreach ($rooms as &$r) {
            $r['category_name'] = $catName;
        }
        $this->json(['rooms' => $rooms]);
    }


    public function resolveRequest(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $decision = ($_POST['decision'] ?? 'approve') === 'reject' ? 'reject' : 'approve';
            $cancelReason = isset($_POST['cancel_reason']) ? (string) $_POST['cancel_reason'] : null;
            $actionMode = trim((string) ($_POST['action_mode'] ?? ''));
            $res = $this->workflow->resolveChangeRequest($staff, (int) $id, $decision, $cancelReason, $actionMode);
            $msg = !empty($res['proposed'])
                ? "Đã gửi đề nghị hủy kèm lý do tới khách hàng xác nhận cho đơn #{$id}."
                : (!empty($res['withdrawn'])
                    ? "Đã thu hồi đề nghị hủy đơn #{$id} và giữ lại đơn đặt phòng."
                    : "Đã xử lý yêu cầu đặt phòng #{$id}.");
            $this->redirect($this->targetUrl('bookings_list'), 'ok', $msg);
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }


    public function checkIn(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $this->workflow->checkIn(
                $staff,
                (int) $id,
                (int) ($_POST['room_id'] ?? 0),
                (string) ($_POST['guest_id_number'] ?? ''),
                (float) ($_POST['deposit_topup_amount'] ?? 0),
                (string) ($_POST['deposit_topup_method'] ?? 'cash'),
                (string) ($_POST['deposit_reference'] ?? '')
            );
            $this->redirect($this->targetUrl('bookings_list'), 'ok', "Check-in hoàn tất cho đặt phòng #$id.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }


    public function modifyStay(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $this->workflow->modifyStay($staff, (int) $id, $_POST);
            $this->redirect($this->targetUrl('bookings_list'), 'ok', "Đã cập nhật lưu trú (Đổi phòng / Gia hạn) cho đơn #$id.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }


    public function createDeskServiceOrder(): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $bookingId = (int) ($_POST['booking_id'] ?? 0);
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
            $requestedTime = trim((string) ($_POST['requested_time'] ?? 'Ngay bây giờ'));
            $guestNote = trim((string) ($_POST['guest_note'] ?? ''));
            $voucherCode = trim((string) ($_POST['voucher_code'] ?? ''));

            $res = $this->workflow->orderRoomServicesBatch(
                $staff,
                $bookingId,
                [['service_id' => $serviceId, 'quantity' => $quantity]],
                $requestedTime,
                $guestNote,
                $voucherCode,
                true
            );
            $this->redirect($this->targetUrl('services'), 'ok', "Lễ tân đã ghi nhận thông tin đặt dịch vụ {$res['order_group_code']} và chuyển Bộ phận Bếp & Dịch vụ thực hiện.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('services'), 'err', $e->getMessage());
        }
    }


    public function updateServiceOrder(string $orderId): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $status = (string) ($_POST['status'] ?? '');
            $actorDepartment = (string) ($_POST['actor_department'] ?? 'reception');
            $cancelReason = (string) ($_POST['cancel_reason'] ?? '');
            $this->workflow->updateServiceOrderStatus($staff, (int) $orderId, $status, $actorDepartment, $cancelReason);
            $msg = match ($status) {
                'accepted'  => "Lễ tân đã xác nhận thông tin khách đặt cho dịch vụ #$orderId.",
                'completed' => "Bộ phận Bếp & Dịch vụ đã xác nhận Hoàn thành dịch vụ #$orderId.",
                default     => "Lễ tân đã hủy yêu cầu dịch vụ #$orderId và gửi lý do tới Bộ phận Bếp/Dịch vụ & khách hàng.",
            };
            $this->redirect($this->targetUrl('services'), 'ok', $msg);
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('services'), 'err', $e->getMessage());
        }
    }


    public function recordPayment(string $bookingId): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $res = $this->workflow->recordPayment(
                $staff,
                (int) $bookingId,
                (float) ($_POST['amount'] ?? 0),
                (string) ($_POST['method'] ?? 'qr_bank'),
                (string) ($_POST['payment_type'] ?? 'settlement'),
                (string) ($_POST['reference_no'] ?? ''),
                (string) ($_POST['note'] ?? 'Thu tiền tại quầy Lễ tân'),
                !empty($_POST['is_extra_adjustment']),
                (string) ($_POST['mismatch_note'] ?? '')
            );
            $msg = ($res['verification_status'] ?? 'pending') === 'verified'
                ? 'Đã ghi nhận giao dịch thanh toán và cập nhật hóa đơn.'
                : 'Đã tạo giao dịch chuyển khoản chờ đối soát sao kê (chưa cộng vào số tiền đã thu cho tới khi bấm Đã nhận tiền).';
            $this->redirect($this->targetUrl('payments'), 'ok', $msg);
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('payments'), 'err', $e->getMessage());
        }
    }


    public function verifyPayment(string $paymentId): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $this->workflow->verifyTransferPayment(
                $staff,
                (int) $paymentId,
                (string) ($_POST['decision'] ?? 'verified'),
                (string) ($_POST['note'] ?? ''),
                (string) ($_POST['reference_no'] ?? '')
            );
            $this->redirect($this->targetUrl('payments'), 'ok', 'Đã cập nhật đối soát thanh toán.');
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('payments'), 'err', $e->getMessage());
        }
    }


    public function decideRefund(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $decision = ($_POST['decision'] ?? '') === 'rejected' ? 'rejected' : 'approved';
            $res = $this->workflow->decideRefundRequest(
                $staff,
                (int) $id,
                $decision,
                (string) ($_POST['decision_note'] ?? ''),
                (string) ($_POST['refund_method'] ?? 'bank_transfer')
            );
            $code = (string) ($res['refund_code'] ?? "#{$id}");
            $msg = $decision === 'approved'
                ? "Đã phê duyệt hồ sơ hoàn tiền {$code}. Có thể xác nhận chi hoàn ngay khi chuyển khoản xong."
                : "Đã từ chối hồ sơ hoàn tiền {$code}.";
            $this->redirect($this->targetUrl('payments'), 'ok', $msg);
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('payments'), 'err', $e->getMessage());
        }
    }


    public function processRefund(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $res = $this->workflow->processRefundPayout(
                $staff,
                (int) $id,
                (string) ($_POST['refund_method'] ?? 'bank_transfer'),
                (string) ($_POST['reference_no'] ?? ''),
                (string) ($_POST['decision_note'] ?? '')
            );
            $code = (string) ($res['refund_code'] ?? "#{$id}");
            $this->redirect($this->targetUrl('payments'), 'ok', "Đã xác nhận giải ngân hoàn tiền {$code} và gửi thông báo tới khách hàng.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('payments'), 'err', $e->getMessage());
        }
    }


    public function checkOut(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $this->workflow->checkOutAndCreateHousekeeping(
                $staff,
                (int) $id,
                (float) ($_POST['surcharges'] ?? 0),
                (string) ($_POST['surcharge_note'] ?? ''),
                (float) ($_POST['settlement_amount'] ?? 0),
                (string) ($_POST['settlement_method'] ?? 'cash'),
                (string) ($_POST['incident_content'] ?? ''),
                (string) ($_POST['incident_severity'] ?? 'LOW'),
                (string) ($_POST['incident_resolution'] ?? ''),
                !empty($_POST['cancel_uncompleted_services']),
                (string) ($_POST['settlement_reference'] ?? 'CHECKOUT'),
                (string) ($_POST['cancel_services_reason'] ?? '')
            );
            $this->redirect($this->targetUrl('bookings_list'), 'ok', "Hoàn tất Check-out! Phòng đã chuyển sang trạng thái Chờ dọn.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }


    public function createIncident(): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $this->workflow->createIncident(
                $staff,
                (int) ($_POST['room_id'] ?? 0),
                !empty($_POST['booking_id']) ? (int) $_POST['booking_id'] : null,
                (string) ($_POST['content'] ?? ''),
                (string) ($_POST['severity'] ?? 'LOW'),
                (string) ($_POST['resolution_plan'] ?? '')
            );
            $this->redirect($this->targetUrl('room_rack'), 'ok', 'Đã ghi nhận phản ánh / sự cố phòng.');
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('room_rack'), 'err', $e->getMessage());
        }
    }


    public function updateIncidentStatus(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $nextStatus = ($_POST['status'] ?? 'resolved') === 'open' ? 'open' : 'resolved';
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM room_incidents WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $id]);
            $inc = $stmt->fetch();
            if (!$inc) {
                throw new \RuntimeException('Không tìm thấy phản ánh sự cố.');
            }
            $plan = isset($_POST['resolution_plan']) ? trim((string) $_POST['resolution_plan']) : (string) ($inc['resolution_plan'] ?? '');
            $pdo->prepare(
                "UPDATE room_incidents
                 SET status = :st, resolution_plan = :plan, resolved_by = :uid,
                     resolved_at = CASE WHEN :st2 = 'resolved' THEN NOW() ELSE NULL END
                 WHERE id = :id"
            )->execute([
                'st'   => $nextStatus,
                'st2'  => $nextStatus,
                'plan' => $plan,
                'uid'  => $staff['id'],
                'id'   => (int) $id,
            ]);
            $pdo->prepare(
                "INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details)
                 VALUES (:uid, :name, :role, 'RESOLVE_INCIDENT', 'room', :eid, :det)"
            )->execute([
                'uid'  => $staff['id'],
                'name' => $staff['full_name'],
                'role' => $staff['role'],
                'eid'  => (string) $inc['room_number'],
                'det'  => "Cập nhật sự cố Phòng {$inc['room_number']} -> {$nextStatus}.",
            ]);
            $this->redirect($this->targetUrl('room_rack'), 'ok', 'Đã cập nhật trạng thái phản ánh / sự cố.');
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('room_rack'), 'err', $e->getMessage());
        }
    }


    public function createHousekeepingTask(): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $roomId = (int) ($_POST['room_id'] ?? 0);
            $rawType = (string) ($_POST['task_type'] ?? 'stay_refresh');
            $validType = in_array($rawType, ['checkout_clean', 'stay_refresh', 'maintenance'], true) ? $rawType : 'stay_refresh';
            $rawPriority = (string) ($_POST['priority'] ?? 'MEDIUM');
            $priority = in_array($rawPriority, ['LOW', 'MEDIUM', 'HIGH'], true) ? $rawPriority : 'MEDIUM';
            $noteBody = trim((string) ($_POST['note'] ?? ''));
            $fullNote = "[Ưu tiên: {$priority}] " . ($noteBody !== '' ? $noteBody : 'Lễ tân gửi yêu cầu kiểm tra / vệ sinh buồng phòng.');

            Database::transaction(function ($pdo) use ($staff, $roomId, $validType, $fullNote) {
                $rStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id LIMIT 1');
                $rStmt->execute(['id' => $roomId]);
                $room = $rStmt->fetch();
                if (!$room) {
                    throw new \RuntimeException('Không tìm thấy phòng.');
                }
                $pdo->prepare(
                    "INSERT INTO housekeeping_tasks (room_id, stay_id, booking_code, task_type, status, assigned_note)
                     VALUES (:rid, NULL, '', :ttype, 'pending', :note)"
                )->execute([
                    'rid'   => $roomId,
                    'ttype' => $validType,
                    'note'  => $fullNote,
                ]);
                if ($validType === 'maintenance') {
                    $pdo->prepare("UPDATE rooms SET status = 'maintenance', notes = :n WHERE id = :id")
                        ->execute(['n' => $fullNote, 'id' => $roomId]);
                } elseif ($room['status'] === 'available') {
                    $pdo->prepare("UPDATE rooms SET status = 'dirty', clean_status = 'dirty', notes = :n WHERE id = :id")
                        ->execute(['n' => $fullNote, 'id' => $roomId]);
                }
                $pdo->prepare(
                    "INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details)
                     VALUES (:uid, :name, :role, 'CREATE_HOUSEKEEPING', 'room', :eid, :det)"
                )->execute([
                    'uid'  => $staff['id'],
                    'name' => $staff['full_name'],
                    'role' => $staff['role'],
                    'eid'  => (string) $room['room_number'],
                    'det'  => "Tạo yêu cầu buồng phòng {$room['room_number']}: {$fullNote}",
                ]);
            });
            $this->redirect($this->targetUrl('room_rack'), 'ok', 'Đã gửi yêu cầu buồng phòng / bảo trì thành công.');
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('room_rack'), 'err', $e->getMessage());
        }
    }


    public function verifyHousekeeping(string $taskId): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        $nextStatus = (string) ($_POST['next_status'] ?? 'verified');
        if (!in_array($nextStatus, ['in_progress', 'completed', 'verified'], true)) {
            $this->redirect($this->targetUrl('room_rack'), 'err', 'Trạng thái dọn phòng không hợp lệ.');
            return;
        }
        Database::transaction(function ($pdo) use ($staff, $taskId, $nextStatus) {
            $stmt = $pdo->prepare('SELECT ht.*, r.room_number FROM housekeeping_tasks ht JOIN rooms r ON ht.room_id = r.id WHERE ht.id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $taskId]);
            $task = $stmt->fetch();
            if (!$task) {
                return;
            }
            if ($nextStatus === 'in_progress') {
                $pdo->prepare("UPDATE housekeeping_tasks SET status = 'in_progress' WHERE id = :id")
                    ->execute(['id' => (int) $taskId]);
            } elseif ($nextStatus === 'completed') {
                $pdo->prepare("UPDATE housekeeping_tasks SET status = 'completed', completed_by = :uid WHERE id = :id")
                    ->execute(['uid' => $staff['id'], 'id' => (int) $taskId]);
                $pdo->prepare("UPDATE rooms SET clean_status = 'inspecting' WHERE id = :rid")
                    ->execute(['rid' => $task['room_id']]);
            } else {
                $pdo->prepare("UPDATE housekeeping_tasks SET status = 'verified', verified_by = :uid WHERE id = :id")
                    ->execute(['uid' => $staff['id'], 'id' => (int) $taskId]);
                $pdo->prepare("UPDATE rooms SET status = 'available', clean_status = 'clean', notes = '' WHERE id = :rid")
                    ->execute(['rid' => $task['room_id']]);
                $pdo->prepare(
                    "INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details)
                     VALUES (:uid, :name, :role, 'ROOM_READY', 'room', :eid, :det)"
                )->execute([
                    'uid'  => $staff['id'],
                    'name' => $staff['full_name'],
                    'role' => $staff['role'],
                    'eid'  => (string) $task['room_number'],
                    'det'  => "Nghiệm thu dọn phòng {$task['room_number']} hoàn tất — phòng Sẵn sàng phục vụ.",
                ]);
            }
        });
        $this->redirect($this->targetUrl('room_rack'), 'ok', 'Đã cập nhật trạng thái dọn phòng & nghiệm thu.');
    }


    public function markNotificationsRead(): void
    {
        $staff = $this->authorizeApi(['receptionist', 'admin']);
        Database::connection()
            ->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0')
            ->execute(['uid' => (int) $staff['id']]);
        $this->json(['ok' => true]);
    }

    private function targetUrl(string $defaultTab = 'dashboard'): string
    {
        $rawTab = isset($_POST['return_tab']) ? (string) $_POST['return_tab'] : (string) ($_GET['return_tab'] ?? $defaultTab);
        $tab = in_array($rawTab, self::VALID_TABS, true) ? $rawTab : $defaultTab;
        return '/reception?tab=' . rawurlencode($tab);
    }
}
