<?php
/**
 * ============================================================================
 *  BTL WEB DEHA - NHÓM 8 | WEBSITE ĐẶT PHÒNG KHÁCH SẠN MƯỜNG THANH PHÚ QUỐC
 *  FOLDER: lead   |   NGƯỜI PHỤ TRÁCH: Nguyễn Hoàng Khánh Linh
 * ============================================================================
 *  TỆP GỐC  : app/Services/BookingWorkflowService.php  (2760 dòng)
 *  PHẦN GIỮ LẠI: 11 / 36 hàm, thuộc mục công việc:
 *    Phần lõi dùng chung: khởi tạo và nâng cấp schema, kiểm tra dữ liệu đầu vào (mật khẩu, số điện thoại, CCCD), thông báo, ghi nhật ký, cập nhật hồ sơ khách
 *  DÒNG GỐC : 21-27, 29-47, 49-64, 66-77, 79-91, 93-98, 100-107, 109-122, 232-252, 572-670, 2752-2759
 * ----------------------------------------------------------------------------
 *  CÁC HÀM TRONG TỆP NÀY:
 *    - __construct()   (dòng 21-27)
 *    - ensureSchemaHardened()   (dòng 29-47)
 *    - ensureUsersIdNumberColumn()   (dòng 49-64)
 *    - ensureBookingChangeRequestEnum()   (dòng 66-77)
 *    - parseDateStrict()   (dòng 79-91)
 *    - validatePasswordStrength()   (dòng 93-98)
 *    - validatePhone10Digits()   (dòng 100-107)
 *    - validateCccd12Digits()   (dòng 109-122)
 *    - createNotification()   (dòng 232-252)
 *    - updateBookingCustomerInfo()   (dòng 572-670)
 *    - writeAudit()   (dòng 2752-2759)
 *
 *  GHI CHÚ:
    Phần nghiệp vụ đặt phòng ở folder Khách hàng; phần bàn Lễ tân ở folder Lễ tân.
 * ============================================================================
 */
class BookingWorkflowService
{
    public function __construct()
    {
        $this->db = Database::connection();
        $this->bookingModel = new Booking();
        $this->catalogRepo = new CatalogRepository();
        $this->ensureSchemaHardened($this->db);
    }

    private function ensureSchemaHardened(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $this->ensureBookingChangeRequestEnum($pdo);
        Refund::ensureTable($pdo);
        self::ensureUsersIdNumberColumn($pdo);
        try {
            $col = $pdo->query("SHOW COLUMNS FROM payments LIKE 'verification_status'")->fetch();
            if ($col && strtoupper((string) ($col['Default'] ?? '')) === 'VERIFIED') {
                $pdo->exec("ALTER TABLE payments MODIFY COLUMN verification_status ENUM('verified', 'pending', 'rejected') NOT NULL DEFAULT 'pending'");
            }
        } catch (\Throwable $e) {
            // ignore if non-MySQL
        }
        $done = true;
    }

    public static function ensureUsersIdNumberColumn(PDO $pdo): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        try {
            $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'id_number'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE users ADD COLUMN id_number VARCHAR(12) NOT NULL DEFAULT '' AFTER phone");
            }
        } catch (\Throwable $e) {
            // ignore if non-MySQL
        }
        $checked = true;
    }

    private function ensureBookingChangeRequestEnum(PDO $pdo): void
    {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'change_request_status'")->fetch();
            $type = (string) ($col['Type'] ?? '');
            if ($type !== '' && !str_contains($type, 'reception_cancel_pending')) {
                $pdo->exec("ALTER TABLE bookings MODIFY COLUMN change_request_status ENUM('none', 'date_change_pending', 'cancel_pending', 'reception_cancel_pending') NOT NULL DEFAULT 'none'");
            }
        } catch (\Throwable $e) {
            // ignore if non-MySQL or already updated
        }
    }

    private function parseDateStrict(string $dateStr, string $label = 'Ngày'): DateTimeImmutable
    {
        $clean = trim($dateStr);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
            throw new RuntimeException("{$label} không đúng định dạng YYYY-MM-DD.");
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $clean);
        $errors = DateTimeImmutable::getLastErrors();
        if ($dt === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException("{$label} không hợp lệ.");
        }
        return $dt;
    }

    public static function validatePasswordStrength(string $password): void
    {
        if (strlen($password) < 6 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new RuntimeException('Mật khẩu phải từ 6 ký tự trở lên và bao gồm cả chữ cái lẫn chữ số.');
        }
    }

    public static function validatePhone10Digits(string $phone, string $label = 'Số điện thoại'): string
    {
        $clean = preg_replace('/\s+/', '', trim($phone)) ?? '';
        if (!preg_match('/^\d{10}$/', $clean)) {
            throw new RuntimeException("{$label} phải gồm chính xác 10 chữ số (chỉ nhập ký tự số 0–9).");
        }
        return $clean;
    }

    public static function validateCccd12Digits(string $cccd, bool $required = true, string $label = 'Số CCCD'): string
    {
        $clean = preg_replace('/\s+/', '', trim($cccd)) ?? '';
        if ($clean === '') {
            if ($required) {
                throw new RuntimeException("{$label} bắt buộc phải nhập (chính xác 12 chữ số).");
            }
            return '';
        }
        if (!preg_match('/^\d{12}$/', $clean)) {
            throw new RuntimeException("{$label} phải gồm chính xác 12 chữ số (chỉ nhập ký tự số 0–9).");
        }
        return $clean;
    }

    public function createNotification(
        PDO $pdo,
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?int $bookingId = null
    ): void {
        if ($userId <= 0) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, related_booking_id) VALUES (:uid, :t, :m, :tp, :bid)'
        )->execute([
            'uid' => $userId,
            't'   => $title,
            'm'   => $message,
            'tp'  => in_array($type, ['info', 'success', 'warning', 'promo'], true) ? $type : 'info',
            'bid' => $bookingId,
        ]);
    }

    public function updateBookingCustomerInfo(array $staff, int $bookingId, array $payload): array
    {
        return Database::transaction(function (PDO $pdo) use ($staff, $bookingId, $payload) {
            $b = $this->bookingModel->find($bookingId);
            if (!$b) {
                throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
            }
            if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Chỉ Lễ tân hoặc Quản trị viên mới được phép chỉnh sửa thông tin khách & đặt phòng.');
            }

            $guestName = trim((string) ($payload['guest_name'] ?? $b['guest_name']));
            $guestPhone = trim((string) ($payload['guest_phone'] ?? $b['guest_phone']));
            $guestEmail = trim((string) ($payload['guest_email'] ?? $b['guest_email']));
            $guestIdNumber = trim((string) ($payload['guest_id_number'] ?? $b['guest_id_number']));
            $arrivalTime = trim((string) ($payload['arrival_time'] ?? ($b['arrival_time'] ?? '')));
            $flightNumber = strtoupper(trim((string) ($payload['flight_number'] ?? ($b['flight_number'] ?? ''))));
            $specialRequests = trim((string) ($payload['special_requests'] ?? ($b['special_requests'] ?? '')));

            if ($guestName === '' || $guestPhone === '') {
                throw new RuntimeException('Họ tên và số điện thoại không được để trống.');
            }
            $guestPhone = self::validatePhone10Digits($guestPhone);
            $guestIdNumber = self::validateCccd12Digits($guestIdNumber, false);

            $adults = isset($payload['adults']) ? max(1, (int) $payload['adults']) : (int) $b['adults'];
            $children = isset($payload['children']) ? max(0, (int) $payload['children']) : (int) $b['children'];
            $catStmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :cid LIMIT 1');
            $catStmt->execute(['cid' => (int) $b['category_id']]);
            $catRow = $catStmt->fetch();
            if ($catRow) {
                self::validateGuestCapacity($catRow, 1, $adults, $children);
            }

            $newIn = trim((string) ($payload['check_in_date'] ?? $b['check_in_date']));
            $newOut = trim((string) ($payload['check_out_date'] ?? $b['check_out_date']));
            $nights = (int) $b['nights'];
            $roomSub = (float) $b['room_subtotal'];
            $taxAmount = (float) $b['tax_amount'];
            $totalEstimated = (float) $b['total_estimated'];
            $depositRequired = (float) $b['deposit_required'];

            if ($newIn !== $b['check_in_date'] || $newOut !== $b['check_out_date']) {
                if (!in_array($b['status'], ['pending', 'confirmed'], true)) {
                    throw new RuntimeException('Chỉ được đổi ngày nhận/trả khi đơn ở trạng thái Chờ xác nhận hoặc Đã xác nhận.');
                }
                $dIn = $this->parseDateStrict($newIn, 'Ngày nhận phòng');
                $dOut = $this->parseDateStrict($newOut, 'Ngày trả phòng');
                if ($dIn->format('Y-m-d') < date('Y-m-d')) {
                    throw new RuntimeException('Ngày nhận phòng mới không thể nằm trong quá khứ.');
                }
                $diffNights = (int) $dIn->diff($dOut)->format('%r%a');
                if ($diffNights <= 0) {
                    throw new RuntimeException('Ngày trả phòng phải sau ngày nhận phòng.');
                }
                $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE category_id = :cid AND status NOT IN ('maintenance', 'out_of_order')");
                $totalStmt->execute(['cid' => $b['category_id']]);
                $totalOp = (int) $totalStmt->fetchColumn();
                $booked = $this->bookingModel->countOverlappingRooms((int) $b['category_id'], $newIn, $newOut, $bookingId);
                if (($totalOp - $booked) < 1) {
                    throw new RuntimeException('Hạng phòng không còn đủ phòng trống cho ngày mới.');
                }
                $nights = $diffNights;
                $roomSub = ((float) $b['locked_nightly_rate'] + (float) $b['locked_package_extra']) * $nights;
                $taxable = max(0.0, $roomSub + (float) $b['addon_subtotal'] - (float) ($b['discount_amount'] ?? 0));
                $taxAmount = round($taxable * ((float) $b['locked_tax_rate'] / 100));
                $totalEstimated = $taxable + $taxAmount;
                $depositPct = max(10.0, min(100.0, (float) $this->catalogRepo->getHotelPolicyValue('deposit_percent', '30')));
                $depositRequired = round($totalEstimated * ($depositPct / 100));
            }

            $pdo->prepare(
                'UPDATE bookings
                 SET guest_name = :gn, guest_phone = :gp, guest_email = :ge, guest_id_number = :gid,
                     arrival_time = :arr, flight_number = :flt, special_requests = :req,
                     adults = :ad, children = :ch,
                     check_in_date = :cin, check_out_date = :cout, nights = :nights,
                     room_subtotal = :rsub, tax_amount = :tax, total_estimated = :tot, deposit_required = :dep
                 WHERE id = :id'
            )->execute([
                'gn' => $guestName, 'gp' => $guestPhone, 'ge' => $guestEmail, 'gid' => $guestIdNumber,
                'arr' => $arrivalTime, 'flt' => $flightNumber, 'req' => $specialRequests,
                'ad' => $adults, 'ch' => $children,
                'cin' => $newIn, 'cout' => $newOut, 'nights' => $nights,
                'rsub' => $roomSub, 'tax' => $taxAmount, 'tot' => $totalEstimated, 'dep' => $depositRequired,
                'id' => $bookingId,
            ]);

            $this->recalculateOpenInvoice($pdo, $bookingId);

            if (!empty($payload['sync_member_profile']) && !empty($b['customer_id'])) {
                $pdo->prepare('UPDATE users SET full_name = :fn, phone = :ph, email = :em WHERE id = :uid')
                    ->execute(['fn' => $guestName, 'ph' => $guestPhone, 'em' => $guestEmail, 'uid' => $b['customer_id']]);
            }

            $this->writeAudit($pdo, $staff, 'UPDATE_BOOKING_INFO', 'booking', $b['booking_code'], "Cập nhật thông tin khách lưu trú ($guestName · $guestPhone · {$adults}NL/{$children}TE · CCCD $guestIdNumber).");
            return $this->bookingModel->find($bookingId);
        });
    }

    private function writeAudit(PDO $pdo, array $actor, string $action, string $entityType, string $entityId, string $details): void
    {
        $pdo->prepare("INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details) VALUES (:uid, :name, :role, :act, :et, :eid, :det)")
            ->execute([
                'uid' => $actor['id'], 'name' => $actor['full_name'], 'role' => $actor['role'],
                'act' => $action, 'et' => $entityType, 'eid' => $entityId, 'det' => $details,
            ]);
    }
}
