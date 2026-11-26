<?php
/**
 * ============================================================================
 *  BTL WEB DEHA - NHÓM 8 | WEBSITE ĐẶT PHÒNG KHÁCH SẠN MƯỜNG THANH PHÚ QUỐC
 *  FOLDER: lead   |   NGƯỜI PHỤ TRÁCH: Nguyễn Hoàng Khánh Linh
 * ============================================================================
 *  TỆP GỐC  : app/Controllers/ReceptionController.php  (802 dòng)
 *  PHẦN GIỮ LẠI: 4 / 25 hàm, thuộc mục công việc:
 *    Hồ sơ khách hàng và yêu cầu hội nghị trong giao diện Lễ tân (khởi tạo controller, tra cứu khách quen, sửa thông tin khách và đơn, đổi trạng thái hội nghị)
 *  DÒNG GỐC : 30-34, 329-395, 397-406, 760-792
 * ----------------------------------------------------------------------------
 *  CÁC HÀM TRONG TỆP NÀY:
 *    - __construct()   (dòng 30-34)
 *    - lookupCustomer()   (dòng 329-395)
 *    - updateBookingInfo()   (dòng 397-406)
 *    - updateConferenceStatus()   (dòng 760-792)
 *
 *  GHI CHÚ:
    19 hàm nghiệp vụ còn lại thuộc folder Lễ tân.
 * ============================================================================
 */
class ReceptionController extends Controller
{
    public function __construct()
    {
        $this->workflow = new BookingWorkflowService();
        $this->repo = new CatalogRepository();
    }

    public function lookupCustomer(): void
    {
        $this->authorizeApi(['receptionist', 'admin']);
        $rawDigits = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? '')) ?? '';
        if (strlen($rawDigits) < 7) {
            $this->json(['customer' => null]);
        }

        $pdo = Database::connection();
        $usersList = $pdo->query("SELECT id, full_name, email, phone, address, membership_tier FROM users WHERE role = 'customer'")->fetchAll();
        $matchedUser = null;
        foreach ($usersList as $u) {
            $uDigits = preg_replace('/\D+/', '', (string) $u['phone']) ?? '';
            if ($uDigits === $rawDigits) {
                $matchedUser = $u;
                break;
            }
        }

        $recentBookings = $pdo->query(
            'SELECT id, customer_id, guest_name, guest_phone, guest_email, guest_id_number
             FROM bookings ORDER BY created_at DESC LIMIT 120'
        )->fetchAll();
        $matchedBooking = null;
        foreach ($recentBookings as $b) {
            $bDigits = preg_replace('/\D+/', '', (string) $b['guest_phone']) ?? '';
            if ($bDigits === $rawDigits) {
                $matchedBooking = $b;
                break;
            }
        }

        if ($matchedUser) {
            $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE customer_id = :uid');
            $cntStmt->execute(['uid' => (int) $matchedUser['id']]);
            $count = (int) $cntStmt->fetchColumn();
            $this->json([
                'customer' => [
                    'customer_id'     => (int) $matchedUser['id'],
                    'full_name'       => (string) $matchedUser['full_name'],
                    'phone'           => (string) $matchedUser['phone'],
                    'email'           => (string) $matchedUser['email'],
                    'address'         => (string) ($matchedUser['address'] ?? ''),
                    'guest_id_number' => (string) ($matchedBooking['guest_id_number'] ?? ''),
                    'membership_tier' => (string) ($matchedUser['membership_tier'] ?: 'Hội viên Mường Thanh'),
                    'bookings_count'  => $count,
                ],
            ]);
        }

        if ($matchedBooking) {
            $this->json([
                'customer' => [
                    'customer_id'     => $matchedBooking['customer_id'] ? (int) $matchedBooking['customer_id'] : null,
                    'full_name'       => (string) $matchedBooking['guest_name'],
                    'phone'           => (string) $matchedBooking['guest_phone'],
                    'email'           => (string) $matchedBooking['guest_email'],
                    'address'         => '',
                    'guest_id_number' => (string) ($matchedBooking['guest_id_number'] ?? ''),
                    'membership_tier' => 'Khách tại quầy',
                    'bookings_count'  => 1,
                ],
            ]);
        }

        $this->json(['customer' => null]);
    }

    public function updateBookingInfo(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $this->workflow->updateBookingCustomerInfo($staff, (int) $id, $_POST);
            $this->redirect($this->targetUrl('bookings_list'), 'ok', "Đã cập nhật thông tin khách & đơn đặt phòng #$id.");
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('bookings_list'), 'err', $e->getMessage());
        }
    }

    public function updateConferenceStatus(string $id): void
    {
        $staff = $this->authorizeWeb(['receptionist', 'admin']);
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM conference_inquiries WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                throw new \RuntimeException('Không tìm thấy yêu cầu đặt sảnh.');
            }
            $rawStatus = (string) ($_POST['status'] ?? $existing['status']);
            if (!in_array($rawStatus, ['new', 'contacted', 'confirmed', 'cancelled'], true)) {
                throw new \RuntimeException('Trạng thái xử lý yêu cầu hội nghị không hợp lệ.');
            }
            $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : (string) ($existing['notes'] ?? '');
            $pdo->prepare('UPDATE conference_inquiries SET status = :st, notes = :n WHERE id = :id')
                ->execute(['st' => $rawStatus, 'n' => $notes, 'id' => (int) $id]);
            $pdo->prepare(
                "INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details)
                 VALUES (:uid, :name, :role, 'UPDATE_CONFERENCE_INQUIRY', 'conference_inquiry', :eid, :det)"
            )->execute([
                'uid'  => $staff['id'],
                'name' => $staff['full_name'],
                'role' => $staff['role'],
                'eid'  => (string) $id,
                'det'  => "{$staff['full_name']} cập nhật yêu cầu sảnh \"{$existing['venue_name']}\" ({$existing['contact_name']}) sang trạng thái \"{$rawStatus}\".",
            ]);
            $this->redirect($this->targetUrl('conferences'), 'ok', 'Đã cập nhật trạng thái xử lý yêu cầu hội nghị.');
        } catch (Throwable $e) {
            $this->redirect($this->targetUrl('conferences'), 'err', $e->getMessage());
        }
    }
}
