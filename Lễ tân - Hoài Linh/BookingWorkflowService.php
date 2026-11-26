<?php
/**
 * ============================================================================
 *  BTL WEB DEHA - NHÓM 8 | WEBSITE ĐẶT PHÒNG KHÁCH SẠN MƯỜNG THANH PHÚ QUỐC
 *  FOLDER: Lễ tân   |   NGƯỜI PHỤ TRÁCH: Lê Hoài Linh
 * ============================================================================
 *  TỆP GỐC  : app/Services/BookingWorkflowService.php  (2760 dòng)
 *  PHẦN GIỮ LẠI: 18/36 hàm thuộc mục công việc:
 *    Nghiệp vụ bàn Lễ tân: phòng trống, tạo đơn tại quầy, gán phòng, xác nhận, xử lý yêu cầu đổi/hủy, điều phối dịch vụ, check-in, điều chỉnh lưu trú, thanh toán, đối soát, sự cố, check-out, hoàn tiền
 *  KHAI BÁO : giữ nguyên const/thuộc tính của lớp (dòng 17-20)
 *  DÒNG GỐC : dòng khai báo 17-20, 181-231, 672-924, 925-957, 958-1016, 1083-1312, 1580-1592, 1593-1735, 1736-1857, 1858-2151, 2152-2284, 2285-2346, 2347-2367, 2368-2500, 2501-2513, 2514-2553, 2554-2611, 2612-2685, 2686-2751
 * ----------------------------------------------------------------------------
 *  CÁC HÀM TRONG TỆP NÀY:
 *    - getAvailablePhysicalRooms()   (dòng 181-231)
 *    - createWalkInBooking()   (dòng 672-924)
 *    - assignRoomOnly()   (dòng 925-957)
 *    - confirmAndAssignRoom()   (dòng 958-1016)
 *    - resolveChangeRequest()   (dòng 1083-1312)
 *    - ensureServiceCancelReasonColumn()   (dòng 1580-1592)
 *    - updateServiceOrderStatus()   (dòng 1593-1735)
 *    - checkIn()   (dòng 1736-1857)
 *    - modifyStay()   (dòng 1858-2151)
 *    - recordPayment()   (dòng 2152-2284)
 *    - verifyTransferPayment()   (dòng 2285-2346)
 *    - createIncident()   (dòng 2347-2367)
 *    - checkOutAndCreateHousekeeping()   (dòng 2368-2500)
 *    - computeBillableServiceNet()   (dòng 2501-2513)
 *    - recalculateOpenInvoice()   (dòng 2514-2553)
 *    - createCancellationRefundRequest()   (dòng 2554-2611)
 *    - decideRefundRequest()   (dòng 2612-2685)
 *    - processRefundPayout()   (dòng 2686-2751)
 *
 *  GHI CHÚ:
    Khung khai báo (thuộc tính $db, các model) giữ trong tệp này. Phần đặt phòng của khách ở folder Khách hàng; phần lõi/thông báo/audit ở folder lead.
 * ============================================================================
 */
namespace App\Services;
use App\Core\Database;
use App\Models\Booking;
use App\Models\CatalogRepository;
use App\Models\Refund;
use App\Models\RoomCategory;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class BookingWorkflowService
{
    private PDO $db;
    private Booking $bookingModel;
    private CatalogRepository $catalogRepo;

    public function getAvailablePhysicalRooms(
        int $categoryId,
        string $checkIn,
        string $checkOut,
        ?int $excludeBookingId = null,
        bool $requireCleanReady = false
    ): array {
        $today = date('Y-m-d');
        $mustBeReadyNow = $requireCleanReady || ($checkIn <= $today);

        $sql = "SELECT r.* FROM rooms r
                WHERE r.category_id = :cat_id
                  AND r.status NOT IN ('maintenance', 'out_of_order')";

        if ($mustBeReadyNow) {
            $sql .= " AND r.status = 'available' AND r.clean_status = 'clean'
                      AND r.id NOT IN (
                          SELECT s.room_id FROM stays s
                          WHERE s.status = 'active'";
            if ($excludeBookingId !== null) {
                $sql .= ' AND s.booking_id != :ex_stay_bid';
            }
            $sql .= ')';
        }

        $sql .= " AND r.id NOT IN (
                      SELECT b.assigned_room_id FROM bookings b
                      WHERE b.assigned_room_id IS NOT NULL
                        AND b.status IN ('pending', 'confirmed', 'checked_in')
                        AND b.check_in_date < :check_out
                        AND b.check_out_date > :check_in";

        $params = [
            'cat_id'    => $categoryId,
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
        ];
        if ($mustBeReadyNow && $excludeBookingId !== null) {
            $params['ex_stay_bid'] = $excludeBookingId;
        }
        if ($excludeBookingId !== null) {
            $sql .= ' AND b.id != :exclude_id';
            $params['exclude_id'] = $excludeBookingId;
        }
        $sql .= ") ORDER BY CASE WHEN r.status = 'available' AND r.clean_status = 'clean' THEN 0 ELSE 1 END, r.floor ASC, r.room_number ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }


    public function createWalkInBooking(array $staff, array $payload): array
    {
        return Database::transaction(function (PDO $pdo) use ($staff, $payload) {
            if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Bạn không có quyền tạo đơn tại quầy.');
            }
            $catId = (int) ($payload['category_id'] ?? 0);
            $pkgId = (int) ($payload['package_id'] ?? 0);
            $guestName = trim((string) ($payload['guest_name'] ?? ''));
            $guestPhone = trim((string) ($payload['guest_phone'] ?? ''));
            $checkInStr = trim((string) ($payload['check_in_date'] ?? ''));
            $checkOutStr = trim((string) ($payload['check_out_date'] ?? ''));

            $in = $this->parseDateStrict($checkInStr, 'Ngày nhận phòng');
            $out = $this->parseDateStrict($checkOutStr, 'Ngày trả phòng');
            if ($in->format('Y-m-d') < date('Y-m-d')) {
                throw new RuntimeException('Ngày nhận phòng tại quầy không thể chọn ngày trong quá khứ.');
            }
            $nights = (int) $in->diff($out)->format('%r%a');
            if ($catId <= 0 || $pkgId <= 0 || $nights <= 0 || $guestName === '' || $guestPhone === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ hạng phòng, ngày lưu trú và thông tin khách hàng.');
            }
            $guestPhone = self::validatePhone10Digits($guestPhone);

            $catStmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :id AND is_active = 1 LIMIT 1');
            $catStmt->execute(['id' => $catId]);
            $cat = $catStmt->fetch();
            $pkgStmt = $pdo->prepare('SELECT * FROM rate_packages WHERE id = :id AND is_active = 1 LIMIT 1');
            $pkgStmt->execute(['id' => $pkgId]);
            $pkg = $pkgStmt->fetch();
            if (!$cat || !$pkg) {
                throw new RuntimeException('Hạng phòng hoặc gói giá không hợp lệ.');
            }
            self::validateGuestCapacity($cat, 1, (int) ($payload['adults'] ?? 2), (int) ($payload['children'] ?? 0));

            $immediateCheckIn = !empty($payload['immediate_checkin']);
            if ($immediateCheckIn && $checkInStr !== date('Y-m-d')) {
                throw new RuntimeException('Check-in ngay tại quầy chỉ áp dụng cho ngày nhận phòng hôm nay.');
            }
            $availRooms = $this->getAvailablePhysicalRooms($catId, $checkInStr, $checkOutStr, null, $immediateCheckIn);
            if (empty($availRooms)) {
                throw new RuntimeException('Không còn phòng trống sẵn sàng trong khoảng ngày đã chọn.');
            }

            $requestedRoomId = !empty($payload['assigned_room_id']) ? (int) $payload['assigned_room_id'] : 0;
            $targetRoom = null;
            if ($requestedRoomId > 0) {
                foreach ($availRooms as $r) {
                    if ((int) $r['id'] === $requestedRoomId) {
                        $targetRoom = $r;
                        break;
                    }
                }
                if (!$targetRoom) {
                    throw new RuntimeException('Phòng vật lý được chọn không thuộc hạng phòng này, chưa sạch/chưa sẵn sàng hoặc đã bị trùng lịch.');
                }
            } else {
                foreach ($availRooms as $r) {
                    if ($r['status'] === 'available' && $r['clean_status'] === 'clean') {
                        $targetRoom = $r;
                        break;
                    }
                }
                if (!$targetRoom) {
                    $targetRoom = $availRooms[0];
                }
            }

            if ($immediateCheckIn && ($targetRoom['status'] !== 'available' || $targetRoom['clean_status'] !== 'clean')) {
                throw new RuntimeException("Phòng {$targetRoom['room_number']} hiện chưa sạch/sẵn sàng để check-in ngay.");
            }

            $cleanDigits = preg_replace('/\D+/', '', $guestPhone) ?? '';
            $guestEmail = trim((string) ($payload['guest_email'] ?? ''));
            $custRows = $pdo->query("SELECT id, phone, email FROM users WHERE role = 'customer'")->fetchAll();
            $linkedCustomerId = null;
            foreach ($custRows as $u) {
                $uDigits = preg_replace('/\D+/', '', (string) $u['phone']) ?? '';
                if (($cleanDigits !== '' && $uDigits === $cleanDigits) || ($guestEmail !== '' && strcasecmp((string) $u['email'], $guestEmail) === 0)) {
                    $linkedCustomerId = (int) $u['id'];
                    break;
                }
            }

            $catModel = new RoomCategory();
            $repo = $this->catalogRepo;
            $pricingEval = $catModel->evaluateCategoryPricing($cat, $checkInStr, $checkOutStr);
            $nightlyRate = (float) $pricingEval['effective_base_price'];
            $resolvedPkgs = $catModel->resolvePackagesForCategory($cat, [$pkg], $nightlyRate);
            $pkgResolved = $resolvedPkgs[0] ?? $pkg;
            $pkgExtra = (float) ($pkgResolved['resolved_extra_per_night'] ?? $pkg['extra_per_night']);
            $taxRate = max(0.0, (float) $repo->getHotelPolicyValue('tax_service_percent', '13.4'));
            $depositPct = max(10.0, min(100.0, (float) $repo->getHotelPolicyValue('deposit_percent', '30')));
            $roomSubtotal = max(0.0, ($nightlyRate + $pkgExtra) * $nights);

            $voucherCode = strtoupper(trim((string) ($payload['voucher_code'] ?? '')));
            $discountAmount = 0.0;
            $usedVoucherId = null;
            if ($voucherCode !== '') {
                $vRes = $this->validateVoucher($voucherCode, $roomSubtotal, $linkedCustomerId, 'room');
                $usedVoucherId = (int) $vRes['voucher']['id'];
                $voucherCode = (string) $vRes['voucher']['code'];
                $discountAmount = (float) $vRes['discount_amount'];
            } else {
                $voucherCode = null;
            }

            $taxable = max(0.0, $roomSubtotal - $discountAmount);
            $taxAmount = round($taxable * ($taxRate / 100));
            $totalEstimated = $taxable + $taxAmount;
            $depositRequired = round($totalEstimated * ($depositPct / 100));

            $rawMethod = (string) ($payload['payment_method'] ?? 'cash');
            $validMethod = in_array($rawMethod, ['cash', 'card', 'qr_bank', 'bank_transfer', 'qr_vnpay'], true) ? $rawMethod : 'cash';
            $isImmediateDeskVerifiedMethod = in_array($validMethod, ['cash', 'card'], true);

            $depositVal = max(0.0, (float) ($payload['deposit_amount'] ?? 0));
            if ($immediateCheckIn) {
                if ($depositVal < $depositRequired) {
                    $fmtReq = number_format($depositRequired, 0, ',', '.');
                    throw new RuntimeException("Nhận phòng ngay yêu cầu thu tối thiểu tiền cọc {$depositPct}% ({$fmtReq} đ).");
                }
                if (!$isImmediateDeskVerifiedMethod) {
                    throw new RuntimeException('Check-in ngay tại quầy chỉ chấp nhận thu cọc trực tiếp bằng Tiền mặt hoặc Thẻ/POS. Nếu khách chuyển khoản QR, vui lòng bỏ chọn Check-in ngay, đối soát tiền về ở mục Thanh toán rồi mới thực hiện Check-in.');
                }
            }

            $bookingCode = 'MT-PQ-' . date('ym') . random_int(1000, 9999);
            $accessCode = 'DV-' . substr($bookingCode, -6);
            $status = $immediateCheckIn ? 'checked_in' : 'confirmed';

            $pdo->prepare(
                "INSERT INTO bookings (
                    booking_code, customer_id, created_by_user_id, source, category_id, package_id, assigned_room_id,
                    check_in_date, check_out_date, nights, rooms_count, adults, children,
                    guest_name, guest_phone, guest_email, guest_id_number, special_requests,
                    voucher_code, discount_amount, payment_method,
                    locked_nightly_rate, locked_package_extra, locked_tax_rate,
                    room_subtotal, addon_subtotal, tax_amount, total_estimated, deposit_required,
                    status, service_access_code, confirmed_by, confirmed_at
                 ) VALUES (
                    :code, :cid, :uid, 'walk_in', :cat, :pkg, :rid,
                    :cin, :cout, :nights, 1, :adults, :children,
                    :gname, :gphone, :gemail, :gid, :req,
                    :vcode, :disc, :pm,
                    :rate, :extra, :trate,
                    :rsub, 0, :tax, :tot, :dep,
                    :st, :acode, :cby, NOW()
                 )"
            )->execute([
                'code'     => $bookingCode,
                'cid'      => $linkedCustomerId,
                'uid'      => $staff['id'],
                'cat'      => $cat['id'],
                'pkg'      => $pkg['id'],
                'rid'      => $targetRoom['id'],
                'cin'      => $checkInStr,
                'cout'     => $checkOutStr,
                'nights'   => $nights,
                'adults'   => max(1, (int) ($payload['adults'] ?? 2)),
                'children' => max(0, (int) ($payload['children'] ?? 0)),
                'gname'    => $guestName,
                'gphone'   => $guestPhone,
                'gemail'   => $guestEmail,
                'gid'      => self::validateCccd12Digits((string) ($payload['guest_id_number'] ?? ''), $immediateCheckIn),
                'req'      => trim((string) ($payload['special_requests'] ?? '')),
                'vcode'    => $voucherCode,
                'disc'     => $discountAmount,
                'pm'       => $validMethod,
                'rate'     => $nightlyRate,
                'extra'    => $pkgExtra,
                'trate'    => $taxRate,
                'rsub'     => $roomSubtotal,
                'tax'      => $taxAmount,
                'tot'      => $totalEstimated,
                'dep'      => $depositRequired,
                'st'       => $status,
                'acode'    => $accessCode,
                'cby'      => $staff['id'],
            ]);

            $bookingId = (int) $pdo->lastInsertId();

            if ($usedVoucherId !== null && $linkedCustomerId !== null) {
                $pdo->prepare(
                    'INSERT INTO voucher_usages (voucher_id, user_id, booking_id, discount_applied) VALUES (:vid, :uid, :bid, :disc)'
                )->execute([
                    'vid'  => $usedVoucherId,
                    'uid'  => $linkedCustomerId,
                    'bid'  => $bookingId,
                    'disc' => $discountAmount,
                ]);
            }

            if ($depositVal > 0) {
                $walkinVerif = $isImmediateDeskVerifiedMethod ? 'verified' : 'pending';
                $walkinNote = $walkinVerif === 'verified'
                    ? 'Thu tiền cọc đặt phòng tại quầy'
                    : 'Chuyển khoản QR tại quầy — chờ Lễ tân đối soát';
                $pdo->prepare(
                    "INSERT INTO payments (payment_code, booking_id, amount, method, payment_type, verification_status, reference_no, recorded_by, note)
                     VALUES (:pcode, :bid, :amt, :method, 'deposit', :vst, 'WALKIN', :uid, :note)"
                )->execute([
                    'pcode'  => 'PAY-' . date('ymd') . random_int(100, 999),
                    'bid'    => $bookingId,
                    'amt'    => $depositVal,
                    'method' => $validMethod,
                    'vst'    => $walkinVerif,
                    'uid'    => $staff['id'],
                    'note'   => $walkinNote,
                ]);
            }

            if ($immediateCheckIn) {
                $pdo->prepare(
                    "INSERT INTO stays (booking_id, room_id, actual_check_in_at, checked_in_by, status, notes)
                     VALUES (:bid, :rid, NOW(), :uid, 'active', 'Check-in trực tiếp tại quầy')"
                )->execute([
                    'bid' => $bookingId,
                    'rid' => $targetRoom['id'],
                    'uid' => $staff['id'],
                ]);
                $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = :id")->execute(['id' => $targetRoom['id']]);
            }

            $this->writeAudit(
                $pdo,
                $staff,
                'WALK_IN_BOOKING',
                'booking',
                $bookingCode,
                "Tạo đơn tại quầy {$bookingCode} cho {$guestName} (Phòng {$targetRoom['room_number']})."
            );

            if ($linkedCustomerId !== null) {
                $this->createNotification(
                    $pdo,
                    $linkedCustomerId,
                    "Đặt phòng tại quầy ({$bookingCode})",
                    "Lễ tân đã tạo đơn {$cat['name']} · Phòng {$targetRoom['room_number']} ({$checkInStr} → {$checkOutStr}).",
                    'success',
                    $bookingId
                );
            }

            return [
                'id'                   => $bookingId,
                'booking_code'         => $bookingCode,
                'assigned_room_number' => (string) $targetRoom['room_number'],
            ];
        });
    }


    public function assignRoomOnly(array $staff, int $bookingId, int $roomId): string
    {
        return Database::transaction(function (PDO $pdo) use ($staff, $bookingId, $roomId) {
            if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Bạn không có quyền gán phòng.');
            }
            $b = $this->bookingModel->find($bookingId);
            if (!$b) {
                throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
            }
            if (!in_array($b['status'], ['pending', 'confirmed'], true)) {
                throw new RuntimeException('Chỉ gán phòng cho đơn chưa check-in.');
            }
            $availRooms = $this->getAvailablePhysicalRooms((int) $b['category_id'], (string) $b['check_in_date'], (string) $b['check_out_date'], $bookingId);
            $foundRoom = null;
            foreach ($availRooms as $r) {
                if ((int) $r['id'] === $roomId) {
                    $foundRoom = $r;
                    break;
                }
            }
            if (!$foundRoom) {
                throw new RuntimeException('Phòng đã chọn không thuộc hạng phòng này hoặc bị trùng lịch lưu trú.');
            }

            $pdo->prepare('UPDATE bookings SET assigned_room_id = :rid WHERE id = :id')
                ->execute(['rid' => $roomId, 'id' => $bookingId]);

            $this->writeAudit($pdo, $staff, 'ASSIGN_ROOM', 'booking', (string) $b['booking_code'], "Gán phòng P.{$foundRoom['room_number']} cho đơn {$b['booking_code']}.");
            return (string) $foundRoom['room_number'];
        });
    }


    public function confirmAndAssignRoom(array $receptionist, int $bookingId, ?int $roomId = null): void
    {
        Database::transaction(function (PDO $pdo) use ($receptionist, $bookingId, $roomId) {
            $b = $this->bookingModel->find($bookingId);
            if (!$b || $b['status'] !== 'pending') {
                throw new RuntimeException('Đơn đặt phòng không hợp lệ để xác nhận.');
            }
            $avail = $this->getAvailablePhysicalRooms((int) $b['category_id'], $b['check_in_date'], $b['check_out_date'], $bookingId);
            if (empty($avail)) {
                throw new RuntimeException('Hết phòng khả dụng.');
            }
            if ($roomId !== null && $roomId > 0) {
                $chosen = null;
                foreach ($avail as $r) {
                    if ((int) $r['id'] === $roomId) {
                        $chosen = $r;
                        break;
                    }
                }
                if (!$chosen) {
                    throw new RuntimeException('Phòng vật lý được chọn không thuộc hạng phòng của đơn, chưa sẵn sàng hoặc đã bị trùng lịch.');
                }
            } else {
                $chosen = $avail[0];
            }
            $targetRoomId = (int) $chosen['id'];
            $accessCode = !empty($b['service_access_code']) ? (string) $b['service_access_code'] : ('DV-' . substr((string) $b['booking_code'], -6));
            $pdo->prepare("UPDATE bookings SET status = 'confirmed', assigned_room_id = :rid, service_access_code = :acode, confirmed_by = :uid, confirmed_at = NOW() WHERE id = :id")
                ->execute(['rid' => $targetRoomId, 'acode' => $accessCode, 'uid' => $receptionist['id'], 'id' => $bookingId]);

            if (!empty($b['customer_id'])) {
                $tpl = $this->catalogRepo->getHotelPolicyValue(
                    'notify_template_confirm',
                    'Đơn đặt phòng {booking_code} đã được xác nhận thành công. Quý khách dự kiến nhận Phòng {room_number}. Mã truy cập dịch vụ lưu trú là {access_code}.'
                );
                $msg = str_replace(
                    ['{guest_name}', '{booking_code}', '{check_in}', '{check_out}', '{service_code}', '{access_code}', '{room_number}'],
                    [(string) $b['guest_name'], (string) $b['booking_code'], (string) $b['check_in_date'], (string) $b['check_out_date'], $accessCode, $accessCode, (string) $chosen['room_number']],
                    $tpl
                );
                if (!str_contains($msg, (string) $chosen['room_number'])) {
                    $msg .= " Dự kiến bố trí Phòng {$chosen['room_number']}.";
                }
                if (!str_contains($msg, $accessCode)) {
                    $msg .= " Mã truy cập dịch vụ lưu trú: {$accessCode}.";
                }
                $this->createNotification(
                    $pdo,
                    (int) $b['customer_id'],
                    "Đặt phòng {$b['booking_code']} đã được xác nhận",
                    $msg,
                    'success',
                    $bookingId
                );
            }
            $this->writeAudit($pdo, $receptionist, 'CONFIRM_BOOKING', 'booking', $b['booking_code'], "Xác nhận và gán phòng {$chosen['room_number']} (#$targetRoomId).");
        });
    }


    public function resolveChangeRequest(array $staff, int $bookingId, string $decision, ?string $cancelReason = null, string $actionMode = ''): array
    {
        $this->ensureBookingChangeRequestEnum($this->db);
        return Database::transaction(function (PDO $pdo) use ($staff, $bookingId, $decision, $cancelReason, $actionMode) {
            $b = $this->bookingModel->find($bookingId);
            if (!$b) {
                throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
            }

            if ($decision === 'reject' || $actionMode === 'withdraw') {
                $wasReceptionProposal = ($b['change_request_status'] ?? 'none') === 'reception_cancel_pending';
                $pdo->prepare("UPDATE bookings SET change_request_status = 'none', change_request_payload = NULL WHERE id = :id")
                    ->execute(['id' => $bookingId]);
                if (!empty($b['customer_id'])) {
                    $this->createNotification(
                        $pdo,
                        (int) $b['customer_id'],
                        $wasReceptionProposal
                            ? "Thu hồi đề nghị hủy ({$b['booking_code']})"
                            : "Yêu cầu thay đổi ({$b['booking_code']}) chưa được chấp thuận",
                        $wasReceptionProposal
                            ? "Quầy Lễ tân đã thu hồi đề nghị hủy đơn {$b['booking_code']}. Đơn đặt phòng của Quý khách tiếp tục được giữ nguyên."
                            : 'Bộ phận Lễ tân chưa thể áp dụng yêu cầu thay đổi cho đơn này. Vui lòng liên hệ hotline 1900 1833 để được hỗ trợ.',
                        $wasReceptionProposal ? 'info' : 'warning',
                        $bookingId
                    );
                }
                $this->writeAudit(
                    $pdo,
                    $staff,
                    $wasReceptionProposal ? 'WITHDRAW_CANCEL_PROPOSAL' : 'REJECT_CHANGE_REQUEST',
                    'booking',
                    $b['booking_code'],
                    $wasReceptionProposal
                        ? "Lễ tân {$staff['full_name']} đã thu hồi đề nghị hủy đơn {$b['booking_code']}."
                        : 'Từ chối yêu cầu thay đổi/hủy từ khách.'
                );
                return ['proposed' => false, 'withdrawn' => $wasReceptionProposal];
            }

            if ($b['change_request_status'] === 'date_change_pending' && !empty($b['change_request_payload'])) {
                $p = json_decode((string) $b['change_request_payload'], true) ?: [];
                $newIn = (string) ($p['new_check_in_date'] ?? '');
                $newOut = (string) ($p['new_check_out_date'] ?? '');
                $dIn = $this->parseDateStrict($newIn, 'Ngày nhận phòng mới');
                $dOut = $this->parseDateStrict($newOut, 'Ngày trả phòng mới');
                if ($dIn->format('Y-m-d') < date('Y-m-d')) {
                    throw new RuntimeException('Ngày nhận phòng mới không thể nằm trong quá khứ.');
                }
                $newNights = (int) $dIn->diff($dOut)->format('%r%a');
                if ($newNights <= 0) {
                    throw new RuntimeException('Ngày lưu trú mới không hợp lệ.');
                }

                $targetCatId = !empty($p['new_category_id']) ? (int) $p['new_category_id'] : (int) $b['category_id'];
                $catStmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :cid AND is_active = 1 LIMIT 1');
                $catStmt->execute(['cid' => $targetCatId]);
                $targetCat = $catStmt->fetch();
                if (!$targetCat) {
                    throw new RuntimeException('Hạng phòng mới không tồn tại.');
                }
                self::validateGuestCapacity($targetCat, 1, (int) $b['adults'], (int) $b['children']);

                $catModel = new RoomCategory();
                $pricingEval = $catModel->evaluateCategoryPricing($targetCat, $newIn, $newOut);
                if (!empty($pricingEval['is_stop_sell'])) {
                    throw new RuntimeException("Hạng phòng {$targetCat['name']} đã đóng bán trong khoảng ngày mới.");
                }

                $catChanged = $targetCatId !== (int) $b['category_id'];
                $nextRate = $catChanged ? (float) $pricingEval['effective_base_price'] : (float) $b['locked_nightly_rate'];

                $availRooms = $this->getAvailablePhysicalRooms($targetCatId, $newIn, $newOut, $bookingId);
                if (empty($availRooms)) {
                    throw new RuntimeException('Không còn phòng trống cho ngày và hạng phòng mới.');
                }

                $roomSubtotal = ($nextRate + (float) $b['locked_package_extra']) * $newNights;
                $preDiscount = $roomSubtotal + (float) $b['addon_subtotal'];
                $discountAmt = min((float) ($b['discount_amount'] ?? 0), $preDiscount);
                $taxable = max(0.0, $preDiscount - $discountAmt);
                $taxAmount = round($taxable * ((float) $b['locked_tax_rate'] / 100));
                $totalEst = $taxable + $taxAmount;
                $depositPct = max(10.0, min(100.0, (float) $this->catalogRepo->getHotelPolicyValue('deposit_percent', '30')));
                $depositReq = round($totalEst * ($depositPct / 100));

                $nextRoomId = (int) $availRooms[0]['id'];
                if (!$catChanged && !empty($b['assigned_room_id'])) {
                    foreach ($availRooms as $r) {
                        if ((int) $r['id'] === (int) $b['assigned_room_id']) {
                            $nextRoomId = (int) $b['assigned_room_id'];
                            break;
                        }
                    }
                }

                $pdo->prepare(
                    "UPDATE bookings
                     SET category_id = :cat, locked_nightly_rate = :rate, check_in_date = :cin, check_out_date = :cout,
                         nights = :nights, assigned_room_id = :rid,
                         room_subtotal = :rsub, tax_amount = :tax, total_estimated = :tot, deposit_required = :dep,
                         change_request_status = 'none', change_request_payload = NULL
                     WHERE id = :id"
                )->execute([
                    'cat' => $targetCatId, 'rate' => $nextRate, 'cin' => $newIn, 'cout' => $newOut,
                    'nights' => $newNights, 'rid' => $nextRoomId,
                    'rsub' => $roomSubtotal, 'tax' => $taxAmount, 'tot' => $totalEst, 'dep' => $depositReq,
                    'id' => $bookingId,
                ]);

                $this->recalculateOpenInvoice($pdo, $bookingId);

                if (!empty($b['customer_id'])) {
                    $catLabel = !empty($p['new_category_name']) ? " · {$p['new_category_name']}" : '';
                    $this->createNotification(
                        $pdo,
                        (int) $b['customer_id'],
                        "Đã duyệt đổi lịch ({$b['booking_code']})",
                        "Lịch lưu trú mới: {$newIn} → {$newOut} ({$newNights} đêm){$catLabel}. Tiền cọc đã thanh toán được bảo lưu chuyển sang lịch mới; phần chênh lệch (nếu có) thanh toán khi nhận/trả phòng.",
                        'success',
                        $bookingId
                    );
                }
                $this->writeAudit($pdo, $staff, 'APPROVE_DATE_CHANGE', 'booking', $b['booking_code'], "Duyệt đổi lịch {$b['booking_code']} sang $newIn → $newOut.");
                return ['proposed' => false, 'withdrawn' => false];
            } else {
                if (!in_array($b['status'], ['pending', 'confirmed'], true)) {
                    throw new RuntimeException('Chỉ có thể hủy đơn chưa nhận phòng.');
                }
                $payloadArr = !empty($b['change_request_payload']) ? (json_decode((string) $b['change_request_payload'], true) ?: []) : [];
                $reasonText = trim((string) ($cancelReason ?? ''));
                if ($reasonText === '') {
                    $reasonText = trim((string) ($payloadArr['reason'] ?? ''));
                }
                if (mb_strlen($reasonText) < 3) {
                    throw new RuntimeException('Vui lòng nhập rõ lý do hủy đơn gửi khách hàng (tối thiểu 3 ký tự).');
                }

                $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'verified'");
                $paidStmt->execute(['bid' => $bookingId]);
                $totalPaid = (float) $paidStmt->fetchColumn();
                $cancelPolicy = self::calculateCancellationPolicy($b, $this->catalogRepo->getPolicyMap(), $totalPaid);

                $customerAlreadyRequested = ($b['change_request_status'] ?? 'none') === 'cancel_pending';
                $hasOnlineCustomer = !empty($b['customer_id']);
                $shouldPropose = $hasOnlineCustomer && !$customerAlreadyRequested && $actionMode !== 'finalize';

                if ($shouldPropose) {
                    $proposalPayload = json_encode([
                        'reason'           => $reasonText,
                        'proposed_by'      => (int) $staff['id'],
                        'proposed_by_name' => (string) $staff['full_name'],
                        'proposed_at'      => date('c'),
                        'total_paid'       => $totalPaid,
                        'retained_fee'     => $cancelPolicy['retained_fee'],
                        'refund_amount'    => $cancelPolicy['refund_amount'],
                        'policy_summary'   => $cancelPolicy['summary'],
                    ], JSON_UNESCAPED_UNICODE);

                    $pdo->prepare(
                        "UPDATE bookings
                         SET change_request_status = 'reception_cancel_pending', change_request_payload = :payload
                         WHERE id = :id"
                    )->execute(['payload' => $proposalPayload, 'id' => $bookingId]);

                    $this->createNotification(
                        $pdo,
                        (int) $b['customer_id'],
                        "Cần xác nhận: Lễ tân đề nghị hủy đơn ({$b['booking_code']})",
                        "Lý do hủy từ Lễ tân ({$staff['full_name']}): \"{$reasonText}\". {$cancelPolicy['summary']} Vui lòng mở mục Đặt phòng của tôi để bấm \"Đồng ý hủy đơn\" hoặc \"Không đồng ý / Giữ lại đơn\".",
                        'warning',
                        $bookingId
                    );
                    $this->writeAudit(
                        $pdo,
                        $staff,
                        'PROPOSE_CANCEL_BOOKING',
                        'booking',
                        $b['booking_code'],
                        "Lễ tân {$staff['full_name']} gửi đề nghị hủy đơn {$b['booking_code']} tới khách hàng xác nhận. Lý do: {$reasonText}."
                    );
                    return ['proposed' => true, 'withdrawn' => false];
                }

                // Giải phóng phòng vật lý đã gán (assigned_room_id = NULL) để có thể gán lại cho khách khác cùng hạng
                $pdo->prepare(
                    "UPDATE bookings
                     SET status = 'cancelled', assigned_room_id = NULL, cancelled_reason = :r,
                         change_request_status = 'none', change_request_payload = NULL
                     WHERE id = :id"
                )->execute(['r' => $reasonText, 'id' => $bookingId]);

                $refundRecord = $this->createCancellationRefundRequest(
                    $pdo,
                    $b,
                    $reasonText,
                    $totalPaid,
                    $cancelPolicy,
                    (int) ($staff['id'] ?? 0) ?: null
                );

                $refundNoticeSuffix = '';
                if ($refundRecord && (float) $refundRecord['refund_amount'] > 0) {
                    $rfFmt = number_format((float) $refundRecord['refund_amount'], 0, ',', '.');
                    $refundNoticeSuffix = " Yêu cầu hoàn tiền {$refundRecord['refund_code']} ({$rfFmt} đ) đã được tạo và chờ phê duyệt.";
                }

                if (!empty($b['customer_id'])) {
                    $this->createNotification(
                        $pdo,
                        (int) $b['customer_id'],
                        "Đơn {$b['booking_code']} đã được hủy",
                        "Lý do: {$reasonText}. Chính sách cọc: {$cancelPolicy['summary']}{$refundNoticeSuffix}",
                        'warning',
                        $bookingId
                    );
                }
                $this->writeAudit(
                    $pdo,
                    $staff,
                    'CANCEL_BOOKING',
                    'booking',
                    $b['booking_code'],
                    "Hủy đơn {$b['booking_code']} & giải phóng phòng gán: {$reasonText} ({$cancelPolicy['summary']}){$refundNoticeSuffix}"
                );
                return ['proposed' => false, 'withdrawn' => false, 'refund' => $refundRecord];
            }
        });
    }


    private function ensureServiceCancelReasonColumn(PDO $pdo): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $col = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'cancel_reason'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE service_orders ADD COLUMN cancel_reason VARCHAR(255) NOT NULL DEFAULT '' AFTER completed_at");
        }
        $checked = true;
    }


    public function updateServiceOrderStatus(
        array $staff,
        int $orderId,
        string $nextStatus,
        string $actorDepartment = 'reception',
        string $cancelReason = ''
    ): array {
        $this->ensureServiceCancelReasonColumn(Database::connection());
        return Database::transaction(function (PDO $pdo) use ($staff, $orderId, $nextStatus, $actorDepartment, $cancelReason) {
            if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Bạn không có quyền điều phối dịch vụ.');
            }
            if (!in_array($nextStatus, ['accepted', 'completed', 'cancelled'], true)) {
                throw new RuntimeException('Trạng thái dịch vụ không hợp lệ.');
            }
            if (!in_array($actorDepartment, ['reception', 'kitchen_service'], true)) {
                throw new RuntimeException('Bộ phận thao tác không hợp lệ.');
            }

            $stmt = $pdo->prepare(
                'SELECT so.*, b.customer_id, b.booking_code
                 FROM service_orders so
                 JOIN bookings b ON so.booking_id = b.id
                 WHERE so.id = :id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $orderId]);
            $so = $stmt->fetch();
            if (!$so) {
                throw new RuntimeException('Không tìm thấy yêu cầu dịch vụ.');
            }
            if ((int) $so['billed_to_invoice'] === 1) {
                throw new RuntimeException('Dịch vụ đã kết chuyển hóa đơn, không thể thay đổi trạng thái.');
            }

            $currentStatus = (string) $so['status'];

            if ($nextStatus === 'accepted') {
                if ($actorDepartment === 'kitchen_service') {
                    throw new RuntimeException('Bộ phận Bếp & Dịch vụ chỉ xác nhận Hoàn thành sau khi Lễ tân đã xác nhận thông tin khách đặt.');
                }
                if ($currentStatus !== 'pending') {
                    throw new RuntimeException('Chỉ có thể xác nhận thông tin khách đối với yêu cầu đang chờ Lễ tân.');
                }
                $pdo->prepare(
                    "UPDATE service_orders
                     SET status = 'accepted', handled_by = :uid, accepted_by = :aby, accepted_at = NOW()
                     WHERE id = :id"
                )->execute(['uid' => $staff['id'], 'aby' => $staff['id'], 'id' => $orderId]);

                if (!empty($so['customer_id'])) {
                    $this->createNotification(
                        $pdo,
                        (int) $so['customer_id'],
                        "Lễ tân đã xác nhận yêu cầu dịch vụ ({$so['booking_code']})",
                        "Yêu cầu {$so['service_name_snapshot']} × {$so['quantity']} đã được Lễ tân xác nhận thông tin và chuyển sang Bộ phận Bếp & Dịch vụ phục vụ.",
                        'info',
                        (int) $so['booking_id']
                    );
                }
                $this->writeAudit($pdo, $staff, 'ACCEPT_SERVICE_ORDER', 'service_order', (string) $orderId, "Lễ tân xác nhận thông tin khách cho {$so['service_name_snapshot']} × {$so['quantity']} (đơn {$so['booking_code']}).");
            } elseif ($nextStatus === 'completed') {
                if ($actorDepartment !== 'kitchen_service') {
                    throw new RuntimeException('Chỉ Bộ phận Bếp & Dịch vụ mới được phép xác nhận Hoàn thành đơn dịch vụ. Lễ tân chỉ xác nhận thông tin khách đặt.');
                }
                if ($currentStatus !== 'accepted') {
                    throw new RuntimeException('Đơn dịch vụ phải được Lễ tân xác nhận thông tin khách đặt trước khi Bộ phận Bếp & Dịch vụ đánh dấu Hoàn thành.');
                }

                $stayStmt = $pdo->prepare("SELECT id FROM stays WHERE booking_id = :bid AND status = 'active' LIMIT 1");
                $stayStmt->execute(['bid' => (int) $so['booking_id']]);
                $activeStayId = $stayStmt->fetchColumn() ?: null;
                $targetStayId = $so['stay_id'] ?: $activeStayId;

                $pdo->prepare(
                    "UPDATE service_orders
                     SET status = 'completed', stay_id = COALESCE(stay_id, :sid),
                         handled_by = :uid, completed_by = :cby, completed_at = NOW()
                     WHERE id = :id"
                )->execute([
                    'sid' => $targetStayId,
                    'uid' => $staff['id'],
                    'cby' => $staff['id'],
                    'id'  => $orderId,
                ]);

                $this->recalculateOpenInvoice($pdo, (int) $so['booking_id']);

                if (!empty($so['customer_id'])) {
                    $this->createNotification(
                        $pdo,
                        (int) $so['customer_id'],
                        "Dịch vụ đã hoàn tất phục vụ ({$so['booking_code']})",
                        "Bộ phận Bếp & Dịch vụ đã hoàn thành {$so['service_name_snapshot']} × {$so['quantity']}. Chi phí đã được cập nhật vào hóa đơn phòng.",
                        'success',
                        (int) $so['booking_id']
                    );
                }
                $this->writeAudit($pdo, $staff, 'COMPLETE_SERVICE_ORDER', 'service_order', (string) $orderId, "Bộ phận Bếp & Dịch vụ xác nhận hoàn thành {$so['service_name_snapshot']} × {$so['quantity']} (đơn {$so['booking_code']}).");
            } else {
                // cancelled
                if ($actorDepartment === 'kitchen_service') {
                    throw new RuntimeException('Chỉ Lễ tân mới được phép hủy yêu cầu dịch vụ theo thông tin khách.');
                }
                if (!in_array($currentStatus, ['pending', 'accepted'], true)) {
                    throw new RuntimeException('Chỉ được hủy dịch vụ chưa hoàn thành.');
                }
                $cleanReason = trim($cancelReason);
                if (mb_strlen($cleanReason) < 3) {
                    throw new RuntimeException('Vui lòng nhập lý do hủy dịch vụ (tối thiểu 3 ký tự) để Bộ phận Bếp/Dịch vụ và khách nắm thông tin.');
                }
                $pdo->prepare(
                    "UPDATE service_orders SET status = 'cancelled', cancel_reason = :rsn, discount_amount = 0, handled_by = :uid WHERE id = :id"
                )->execute(['rsn' => $cleanReason, 'uid' => $staff['id'], 'id' => $orderId]);

                $this->recalculateOpenInvoice($pdo, (int) $so['booking_id']);

                if (!empty($so['customer_id'])) {
                    $this->createNotification(
                        $pdo,
                        (int) $so['customer_id'],
                        "Yêu cầu dịch vụ đã được hủy ({$so['booking_code']})",
                        "Lễ tân đã hủy yêu cầu {$so['service_name_snapshot']} × {$so['quantity']} (Lý do: {$cleanReason}).",
                        'warning',
                        (int) $so['booking_id']
                    );
                }
                $this->writeAudit($pdo, $staff, 'CANCEL_SERVICE_ORDER', 'service_order', (string) $orderId, "Lễ tân hủy {$so['service_name_snapshot']} × {$so['quantity']} (đơn {$so['booking_code']}). Lý do: {$cleanReason}");
            }

            $outStmt = $pdo->prepare(
                'SELECT so.*, ua.full_name AS accepted_by_name, uc.full_name AS completed_by_name
                 FROM service_orders so
                 LEFT JOIN users ua ON so.accepted_by = ua.id
                 LEFT JOIN users uc ON so.completed_by = uc.id
                 WHERE so.id = :id LIMIT 1'
            );
            $outStmt->execute(['id' => $orderId]);
            return $outStmt->fetch() ?: [];
        });
    }


    public function checkIn(
        array $receptionist,
        int $bookingId,
        int $roomId,
        string $idNumber,
        float $depositTopup = 0.0,
        string $topupMethod = 'cash',
        string $topupRef = ''
    ): void {
        Database::transaction(function (PDO $pdo) use ($receptionist, $bookingId, $roomId, $idNumber, $depositTopup, $topupMethod, $topupRef) {
            if (!in_array($receptionist['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Bạn không có quyền thực hiện Check-in.');
            }
            $bStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
            $bStmt->execute(['id' => $bookingId]);
            $b = $bStmt->fetch();
            if (!$b || $b['status'] !== 'confirmed') {
                throw new RuntimeException('Booking phải ở trạng thái Confirmed trước khi Check-in.');
            }

            $today = date('Y-m-d');
            if ((string) $b['check_out_date'] <= $today) {
                throw new RuntimeException("Đơn đã quá ngày trả phòng dự kiến ({$b['check_out_date']}), không thể Check-in. Vui lòng cập nhật lịch hoặc hủy đơn.");
            }
            if ((string) $b['check_in_date'] > $today) {
                throw new RuntimeException("Đơn có ngày nhận phòng dự kiến là {$b['check_in_date']} (chưa tới ngày Check-in). Vui lòng cập nhật ngày nhận phòng trước nếu khách đến sớm.");
            }

            if ($roomId <= 0) {
                throw new RuntimeException('Vui lòng chọn số phòng vật lý hợp lệ để Check-in.');
            }
            if (!empty($b['assigned_room_id']) && (int) $b['assigned_room_id'] !== $roomId) {
                throw new RuntimeException('Phòng chọn Check-in không khớp với phòng đã phân bổ cho đơn. Vui lòng gán lại phòng đúng trước khi Check-in hoặc chọn đúng phòng đã gán.');
            }

            $rStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id FOR UPDATE');
            $rStmt->execute(['id' => $roomId]);
            $room = $rStmt->fetch();
            if (!$room) {
                throw new RuntimeException('Không tìm thấy phòng vật lý đã chọn.');
            }
            if ((int) $room['category_id'] !== (int) $b['category_id']) {
                throw new RuntimeException("Phòng {$room['room_number']} không thuộc đúng hạng phòng đã đặt của đơn.");
            }
            if ($room['status'] !== 'available' || $room['clean_status'] !== 'clean') {
                throw new RuntimeException("Phòng {$room['room_number']} chưa sạch hoặc chưa sẵn sàng để Check-in.");
            }

            $stayChk = $pdo->prepare("SELECT id FROM stays WHERE (booking_id = :bid OR room_id = :rid) AND status = 'active' LIMIT 1");
            $stayChk->execute(['bid' => $bookingId, 'rid' => $roomId]);
            if ($stayChk->fetch()) {
                throw new RuntimeException("Đơn đặt phòng hoặc Phòng {$room['room_number']} đang có phiên lưu trú hoạt động.");
            }

            $ovStmt = $pdo->prepare(
                "SELECT booking_code FROM bookings
                 WHERE assigned_room_id = :rid AND id != :bid
                   AND status IN ('pending', 'confirmed', 'checked_in')
                   AND check_in_date < :cout AND check_out_date > :cin
                 LIMIT 1"
            );
            $ovStmt->execute([
                'rid'  => $roomId,
                'bid'  => $bookingId,
                'cout' => $b['check_out_date'],
                'cin'  => $b['check_in_date'],
            ]);
            if ($conflictCode = $ovStmt->fetchColumn()) {
                throw new RuntimeException("Phòng {$room['room_number']} bị trùng lịch với đơn {$conflictCode} trong khoảng {$b['check_in_date']} → {$b['check_out_date']}.");
            }

            if ($depositTopup > 0) {
                $validMethod = in_array($topupMethod, ['cash', 'card', 'qr_bank', 'bank_transfer', 'qr_vnpay'], true) ? $topupMethod : 'cash';
                if (!in_array($validMethod, ['cash', 'card'], true)) {
                    throw new RuntimeException('Thu bổ sung cọc ngay khi Check-in chỉ hỗ trợ Tiền mặt hoặc Thẻ/POS. Với chuyển khoản QR, vui lòng ghi nhận tại mục Thanh toán và Đối soát xác nhận đã nhận tiền trước.');
                }
                $pdo->prepare(
                    "INSERT INTO payments (payment_code, booking_id, amount, method, payment_type, verification_status, reference_no, recorded_by, note)
                     VALUES (:pcode, :bid, :amt, :method, 'deposit', 'verified', :ref, :uid, 'Thu bổ sung tiền cọc khi Check-in')"
                )->execute([
                    'pcode'  => 'PAY-' . date('ymd') . random_int(100, 999),
                    'bid'    => $bookingId,
                    'amt'    => $depositTopup,
                    'method' => $validMethod,
                    'ref'    => trim($topupRef) !== '' ? trim($topupRef) : 'CHECKIN-DEP',
                    'uid'    => $receptionist['id'],
                ]);
            }

            $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'verified'");
            $paidStmt->execute(['bid' => $bookingId]);
            $paidSum = (float) $paidStmt->fetchColumn();
            $depReq = (float) ($b['deposit_required'] ?? 0);
            if ($paidSum < $depReq) {
                $missing = number_format($depReq - $paidSum, 0, ',', '.');
                throw new RuntimeException("Khách chưa có đủ tiền cọc đã xác minh theo yêu cầu (còn thiếu {$missing} đ — lưu ý giao dịch chuyển khoản chờ đối soát chưa được tính). Vui lòng đối soát hoặc thu cọc tại quầy trước khi Check-in.");
            }

            $finalIdNumber = trim($idNumber) !== '' ? trim($idNumber) : (string) $b['guest_id_number'];
            $finalIdNumber = self::validateCccd12Digits($finalIdNumber, true);
            $accessCode = !empty($b['service_access_code']) ? (string) $b['service_access_code'] : ('DV-' . substr((string) $b['booking_code'], -6));
            $pdo->prepare("UPDATE bookings SET status = 'checked_in', assigned_room_id = :rid, service_access_code = :acode, guest_id_number = :gid WHERE id = :id")
                ->execute(['rid' => $roomId, 'acode' => $accessCode, 'gid' => $finalIdNumber, 'id' => $bookingId]);
            $pdo->prepare("INSERT INTO stays (booking_id, room_id, actual_check_in_at, checked_in_by, status) VALUES (:bid, :rid, NOW(), :uid, 'active')")
                ->execute(['bid' => $bookingId, 'rid' => $roomId, 'uid' => $receptionist['id']]);
            $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = :id")->execute(['id' => $roomId]);

            if (!empty($b['customer_id'])) {
                $this->createNotification(
                    $pdo,
                    (int) $b['customer_id'],
                    "Chào mừng nhận Phòng {$room['room_number']} ({$b['booking_code']})",
                    "Quý khách đã nhận phòng thành công! Sử dụng mã dịch vụ {$accessCode} để gọi món ăn uống và đặt lịch Pearl Spa tại phòng.",
                    'success',
                    $bookingId
                );
            }

            $this->writeAudit($pdo, $receptionist, 'CHECK_IN', 'stay', $b['booking_code'], "Check-in vào phòng {$room['room_number']}.");
        });
    }


    public function modifyStay(array $staff, int $bookingId, array $payload): void
    {
        Database::transaction(function (PDO $pdo) use ($staff, $bookingId, $payload) {
            if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Bạn không có quyền điều chỉnh lưu trú.');
            }
            $bStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
            $bStmt->execute(['id' => $bookingId]);
            $b = $bStmt->fetch();
            $stayStmt = $pdo->prepare("SELECT * FROM stays WHERE booking_id = :bid AND status = 'active' LIMIT 1 FOR UPDATE");
            $stayStmt->execute(['bid' => $bookingId]);
            $stay = $stayStmt->fetch();
            if (!$b || !$stay || $b['status'] !== 'checked_in') {
                throw new RuntimeException('Chỉ áp dụng đổi phòng hoặc gia hạn cho khách đang lưu trú.');
            }

            $today = date('Y-m-d');
            $catModel = new RoomCategory();
            $repo = $this->catalogRepo;
            $pkgStmt = $pdo->prepare('SELECT * FROM rate_packages WHERE id = :pid LIMIT 1');
            $pkgStmt->execute(['pid' => (int) $b['package_id']]);
            $pkgRow = $pkgStmt->fetch() ?: null;

            $action = (string) ($payload['action'] ?? 'extend_stay');
            if ($action === 'change_room') {
                $newRoomId = (int) ($payload['new_room_id'] ?? 0);
                $oldRoomId = (int) $stay['room_id'];
                if ($newRoomId <= 0 || $newRoomId === $oldRoomId) {
                    throw new RuntimeException('Vui lòng chọn số phòng mới khác với phòng hiện tại.');
                }
                if ($today >= (string) $b['check_out_date']) {
                    throw new RuntimeException('Đơn đã đến hoặc quá ngày trả phòng dự kiến, vui lòng gia hạn ngày trả phòng trước khi đổi phòng.');
                }

                $rStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id FOR UPDATE');
                $rStmt->execute(['id' => $newRoomId]);
                $nextRoom = $rStmt->fetch();
                if (!$nextRoom || $nextRoom['status'] !== 'available' || $nextRoom['clean_status'] !== 'clean') {
                    throw new RuntimeException('Phòng mới chưa sẵn sàng hoặc chưa dọn sạch.');
                }
                $activeStayConflict = $pdo->prepare("SELECT id FROM stays WHERE room_id = :rid AND status = 'active' AND booking_id != :bid LIMIT 1");
                $activeStayConflict->execute(['rid' => $newRoomId, 'bid' => $bookingId]);
                if ($activeStayConflict->fetch()) {
                    throw new RuntimeException("Phòng {$nextRoom['room_number']} hiện đang có khách lưu trú.");
                }

                $overlapStart = max($today, (string) $b['check_in_date']);
                $ovStmt = $pdo->prepare(
                    "SELECT booking_code FROM bookings
                     WHERE assigned_room_id = :rid AND id != :bid
                       AND status IN ('pending', 'confirmed', 'checked_in')
                       AND check_in_date < :cout AND check_out_date > :cin
                     LIMIT 1"
                );
                $ovStmt->execute([
                    'rid'  => $newRoomId,
                    'bid'  => $bookingId,
                    'cout' => $b['check_out_date'],
                    'cin'  => $overlapStart,
                ]);
                if ($conflictCode = $ovStmt->fetchColumn()) {
                    throw new RuntimeException("Phòng {$nextRoom['room_number']} đã có lịch đặt trùng ({$conflictCode}) trong thời gian lưu trú còn lại.");
                }

                $newCatId = (int) $nextRoom['category_id'];
                $oldCatId = (int) $b['category_id'];
                $priceDelta = 0.0;
                $catChangeDesc = '';
                $newRoomSub = (float) $b['room_subtotal'];
                $newLockedRate = (float) $b['locked_nightly_rate'];
                $newLockedExtra = (float) $b['locked_package_extra'];

                if ($newCatId !== $oldCatId) {
                    $cStmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :cid AND is_active = 1 LIMIT 1');
                    $cStmt->execute(['cid' => $newCatId]);
                    $nextCat = $cStmt->fetch();
                    if (!$nextCat) {
                        throw new RuntimeException('Hạng phòng của phòng mới không hoạt động.');
                    }
                    self::validateGuestCapacity($nextCat, 1, (int) $b['adults'], (int) $b['children']);

                    $eval = $catModel->evaluateCategoryPricing($nextCat, $overlapStart, (string) $b['check_out_date']);
                    $nextBase = (float) $eval['effective_base_price'];
                    $nextExtra = $newLockedExtra;
                    if ($pkgRow) {
                        $resolvedPkgs = $catModel->resolvePackagesForCategory($nextCat, [$pkgRow], $nextBase);
                        if (!empty($resolvedPkgs[0])) {
                            $nextExtra = (float) ($resolvedPkgs[0]['resolved_extra_per_night'] ?? $pkgRow['extra_per_night']);
                        }
                    }

                    $dRemStart = $this->parseDateStrict($overlapStart, 'Ngày đổi phòng');
                    $dOut = $this->parseDateStrict((string) $b['check_out_date'], 'Ngày trả phòng');
                    $remNights = max(1, (int) $dRemStart->diff($dOut)->format('%r%a'));

                    $oldNightlyUnit = $newLockedRate + $newLockedExtra;
                    $newNightlyUnit = $nextBase + $nextExtra;
                    $priceDelta = ($newNightlyUnit - $oldNightlyUnit) * $remNights;
                    $newRoomSub = max(0.0, $newRoomSub + $priceDelta);
                    $newLockedRate = $nextBase;
                    $newLockedExtra = $nextExtra;
                    $catChangeDesc = " · Đổi hạng sang {$nextCat['name']} ({$remNights} đêm còn lại: " . ($priceDelta >= 0 ? '+' : '') . number_format($priceDelta, 0, ',', '.') . 'đ)';
                }

                $reason = trim((string) ($payload['reason'] ?? ''));
                if ($reason === '') {
                    $reason = 'Điều chuyển phòng tại quầy Lễ tân';
                }

                $taxable = max(0.0, $newRoomSub + (float) ($b['extension_fee'] ?? 0) + (float) $b['addon_subtotal'] - (float) ($b['discount_amount'] ?? 0));
                $taxAmt = round($taxable * ((float) $b['locked_tax_rate'] / 100));
                $totEst = $taxable + $taxAmt;

                $pdo->prepare("UPDATE rooms SET status = 'dirty', clean_status = 'dirty' WHERE id = :id")->execute(['id' => $oldRoomId]);
                $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = :id")->execute(['id' => $newRoomId]);
                $pdo->prepare("UPDATE stays SET room_id = :rid, notes = TRIM(CONCAT(COALESCE(notes, ''), :snote)) WHERE id = :sid")
                    ->execute([
                        'rid'   => $newRoomId,
                        'snote' => ($stay['notes'] !== '' ? ' | ' : '') . "Đổi sang P.{$nextRoom['room_number']} ({$today}): {$reason}",
                        'sid'   => $stay['id'],
                    ]);
                $pdo->prepare(
                    'UPDATE bookings
                     SET assigned_room_id = :rid, category_id = :cid,
                         locked_nightly_rate = :rate, locked_package_extra = :extra,
                         room_subtotal = :rsub, tax_amount = :tax, total_estimated = :tot
                     WHERE id = :bid'
                )->execute([
                    'rid'   => $newRoomId,
                    'cid'   => $newCatId,
                    'rate'  => $newLockedRate,
                    'extra' => $newLockedExtra,
                    'rsub'  => $newRoomSub,
                    'tax'   => $taxAmt,
                    'tot'   => $totEst,
                    'bid'   => $bookingId,
                ]);
                $pdo->prepare("INSERT INTO housekeeping_tasks (room_id, stay_id, booking_code, task_type, status, assigned_note) VALUES (:rid, :sid, :code, 'checkout_clean', 'pending', :hnote)")
                    ->execute(['rid' => $oldRoomId, 'sid' => $stay['id'], 'code' => $b['booking_code'], 'hnote' => "Khách chuyển phòng sang P.{$nextRoom['room_number']} — cần dọn phòng cũ"]);

                $this->recalculateOpenInvoice($pdo, $bookingId);

                $this->writeAudit($pdo, $staff, 'CHANGE_ROOM', 'stay', $b['booking_code'], "Đổi phòng sang P.{$nextRoom['room_number']} ({$reason}){$catChangeDesc}.");
                if (!empty($b['customer_id'])) {
                    $deltaInfo = $priceDelta != 0.0
                        ? ' Chênh lệch tiền phòng còn lại: ' . ($priceDelta >= 0 ? '+' : '') . number_format($priceDelta, 0, ',', '.') . ' đ.'
                        : '';
                    $this->createNotification($pdo, (int) $b['customer_id'], "Đổi phòng thành công — P.{$nextRoom['room_number']}", "Lễ tân đã chuyển phòng của quý khách ({$b['booking_code']}) sang Phòng {$nextRoom['room_number']} (Lý do: {$reason}).{$deltaInfo}", 'info', $bookingId);
                }
            } else {
                $targetOut = trim((string) ($payload['new_check_out_date'] ?? $b['check_out_date']));
                $dIn = $this->parseDateStrict((string) $b['check_in_date'], 'Ngày nhận phòng');
                $dOut = $this->parseDateStrict($targetOut, 'Ngày trả phòng gia hạn');
                $newNights = (int) $dIn->diff($dOut)->format('%r%a');
                if ($targetOut < $b['check_out_date'] || $newNights < (int) $b['nights']) {
                    throw new RuntimeException('Ngày trả phòng gia hạn không hợp lệ.');
                }

                $slot = (string) ($payload['checkout_time_slot'] ?? ($b['checkout_time_slot'] ?? '12:00'));
                if (!in_array($slot, ['12:00', '15:00', '18:00'], true)) {
                    $slot = '12:00';
                }
                $targetRoomId = !empty($payload['new_room_id']) ? (int) $payload['new_room_id'] : (int) $stay['room_id'];
                if ($targetOut === (string) $b['check_out_date'] && $slot === (string) ($b['checkout_time_slot'] ?? '12:00') && $targetRoomId === (int) $stay['room_id']) {
                    throw new RuntimeException('Chưa có thay đổi nào về ngày trả phòng, khung giờ trả muộn hoặc phòng lưu trú.');
                }

                $overlapStart = max($today, (string) $b['check_in_date']);
                $ovStmt = $pdo->prepare(
                    "SELECT booking_code FROM bookings
                     WHERE assigned_room_id = :rid AND id != :bid
                       AND status IN ('pending', 'confirmed', 'checked_in')
                       AND check_in_date < :cout AND check_out_date > :cin
                     LIMIT 1"
                );
                $ovStmt->execute([
                    'rid'  => $targetRoomId,
                    'bid'  => $bookingId,
                    'cout' => $targetOut,
                    'cin'  => $overlapStart,
                ]);
                if ($conflictCode = $ovStmt->fetchColumn()) {
                    throw new RuntimeException("Phòng đang chọn bị trùng lịch với đơn {$conflictCode} đến ngày {$targetOut}. Vui lòng chọn phòng trống khác.");
                }

                $activeCatId = (int) $b['category_id'];
                $lockedRate = (float) $b['locked_nightly_rate'];
                $lockedExtra = (float) $b['locked_package_extra'];
                $oldNightlyUnit = $lockedRate + $lockedExtra;
                $addedNights = max(0, $newNights - (int) $b['nights']);
                $roomSub = (float) $b['room_subtotal'] + ($oldNightlyUnit * $addedNights);
                $effectiveUnitForLateFee = $oldNightlyUnit;

                if ($targetRoomId !== (int) $stay['room_id']) {
                    $rStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id FOR UPDATE');
                    $rStmt->execute(['id' => $targetRoomId]);
                    $nextRoom = $rStmt->fetch();
                    if (!$nextRoom || $nextRoom['status'] !== 'available' || $nextRoom['clean_status'] !== 'clean') {
                        throw new RuntimeException('Phòng chuyển đổi khi gia hạn chưa sẵn sàng.');
                    }
                    $stayChk = $pdo->prepare("SELECT id FROM stays WHERE room_id = :rid AND status = 'active' AND booking_id != :bid LIMIT 1");
                    $stayChk->execute(['rid' => $targetRoomId, 'bid' => $bookingId]);
                    if ($stayChk->fetch()) {
                        throw new RuntimeException("Phòng {$nextRoom['room_number']} hiện đang có khách lưu trú.");
                    }

                    $newCatId = (int) $nextRoom['category_id'];
                    if ($newCatId !== $activeCatId) {
                        $cStmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :cid AND is_active = 1 LIMIT 1');
                        $cStmt->execute(['cid' => $newCatId]);
                        $nextCat = $cStmt->fetch();
                        if (!$nextCat) {
                            throw new RuntimeException('Hạng phòng chuyển đổi không hoạt động.');
                        }
                        self::validateGuestCapacity($nextCat, 1, (int) $b['adults'], (int) $b['children']);
                        $eval = $catModel->evaluateCategoryPricing($nextCat, $overlapStart, $targetOut);
                        $nextBase = (float) $eval['effective_base_price'];
                        $nextExtra = $lockedExtra;
                        if ($pkgRow) {
                            $resolvedPkgs = $catModel->resolvePackagesForCategory($nextCat, [$pkgRow], $nextBase);
                            if (!empty($resolvedPkgs[0])) {
                                $nextExtra = (float) ($resolvedPkgs[0]['resolved_extra_per_night'] ?? $pkgRow['extra_per_night']);
                            }
                        }
                        $dRemStart = $this->parseDateStrict($overlapStart, 'Ngày chuyển');
                        $oldRemNights = max(0, (int) $dRemStart->diff($this->parseDateStrict((string) $b['check_out_date'], 'Ngày cũ'))->format('%r%a'));
                        $newRemNights = max(1, (int) $dRemStart->diff($dOut)->format('%r%a'));
                        $newNightlyUnit = $nextBase + $nextExtra;
                        $alreadyStayedSub = max(0.0, (float) $b['room_subtotal'] - ($oldNightlyUnit * $oldRemNights));
                        $roomSub = $alreadyStayedSub + ($newNightlyUnit * $newRemNights);
                        $activeCatId = $newCatId;
                        $lockedRate = $nextBase;
                        $lockedExtra = $nextExtra;
                        $effectiveUnitForLateFee = $newNightlyUnit;
                    }

                    $oldRoomId = (int) $stay['room_id'];
                    $pdo->prepare("UPDATE rooms SET status = 'dirty', clean_status = 'dirty' WHERE id = :id")->execute(['id' => $oldRoomId]);
                    $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = :id")->execute(['id' => $targetRoomId]);
                    $pdo->prepare('UPDATE stays SET room_id = :rid WHERE id = :sid')->execute(['rid' => $targetRoomId, 'sid' => $stay['id']]);
                    $pdo->prepare('UPDATE bookings SET assigned_room_id = :rid WHERE id = :bid')->execute(['rid' => $targetRoomId, 'bid' => $bookingId]);
                    $pdo->prepare("INSERT INTO housekeeping_tasks (room_id, stay_id, booking_code, task_type, status, assigned_note) VALUES (:rid, :sid, :code, 'checkout_clean', 'pending', 'Đổi phòng khi gia hạn lưu trú')")
                        ->execute(['rid' => $oldRoomId, 'sid' => $stay['id'], 'code' => $b['booking_code']]);
                }

                $lateFee = 0.0;
                if ($slot === '15:00') {
                    $lateFee = round($effectiveUnitForLateFee * 0.30);
                } elseif ($slot === '18:00') {
                    $lateFee = round($effectiveUnitForLateFee * 0.50);
                }

                // Không cộng dồn extension_fee cũ khi cập nhật lại giờ trả phòng để tránh tính phí trùng lặp
                $totalExtFee = $lateFee;
                $taxable = max(0.0, $roomSub + $totalExtFee + (float) $b['addon_subtotal'] - (float) ($b['discount_amount'] ?? 0));
                $taxAmt = round($taxable * ((float) $b['locked_tax_rate'] / 100));
                $totEst = $taxable + $taxAmt;
                $depositPct = max(10.0, min(100.0, (float) $repo->getHotelPolicyValue('deposit_percent', '30')));
                $depReq = round($totEst * ($depositPct / 100));

                $pdo->prepare(
                    'UPDATE bookings
                     SET category_id = :cid, locked_nightly_rate = :rate, locked_package_extra = :extra,
                         check_out_date = :cout, nights = :n, room_subtotal = :rsub,
                         extension_fee = :efee, checkout_time_slot = :slot,
                         tax_amount = :tax, total_estimated = :tot, deposit_required = :dep
                     WHERE id = :id'
                )->execute([
                    'cid'  => $activeCatId,
                    'rate' => $lockedRate,
                    'extra'=> $lockedExtra,
                    'cout' => $targetOut,
                    'n'    => $newNights,
                    'rsub' => $roomSub,
                    'efee' => $totalExtFee,
                    'slot' => $slot,
                    'tax'  => $taxAmt,
                    'tot'  => $totEst,
                    'dep'  => $depReq,
                    'id'   => $bookingId,
                ]);

                $this->recalculateOpenInvoice($pdo, $bookingId);

                $deltaRoom = max(0.0, $roomSub - (float) $b['room_subtotal']);
                $feeDisplay = number_format($deltaRoom + $lateFee, 0, ',', '.');
                $this->writeAudit($pdo, $staff, 'EXTEND_STAY', 'stay', $b['booking_code'], "Gia hạn đến $targetOut ($slot) · +{$feeDisplay}đ.");
                if (!empty($b['customer_id'])) {
                    $this->createNotification($pdo, (int) $b['customer_id'], "Gia hạn lưu trú ({$b['booking_code']})", "Kỳ nghỉ của quý khách được gia hạn đến {$targetOut} (Trả phòng lúc {$slot}).", 'info', $bookingId);
                }
            }
        });
    }


    public function recordPayment(
        array $staff,
        int $bookingId,
        float $amount,
        string $method = 'qr_bank',
        string $paymentType = 'settlement',
        string $referenceNo = '',
        string $note = '',
        bool $isExtraAdjustment = false,
        string $mismatchNote = ''
    ): array {
        return Database::transaction(function (PDO $pdo) use ($staff, $bookingId, $amount, $method, $paymentType, $referenceNo, $note, $isExtraAdjustment, $mismatchNote) {
            if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Bạn không có quyền ghi nhận thanh toán.');
            }
            if ($amount <= 0) {
                throw new RuntimeException('Số tiền thanh toán phải lớn hơn 0.');
            }
            $b = $this->bookingModel->find($bookingId);
            if (!$b) {
                throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
            }

            $invStmt = $pdo->prepare('SELECT * FROM invoices WHERE booking_id = :bid LIMIT 1');
            $invStmt->execute(['bid' => $bookingId]);
            $invoiceRow = $invStmt->fetch() ?: null;
            $invoiceId = $invoiceRow ? (int) $invoiceRow['id'] : null;

            $srvRowsStmt = $pdo->prepare(
                "SELECT total_price, discount_amount, is_prebooked, status FROM service_orders WHERE booking_id = :bid"
            );
            $srvRowsStmt->execute(['bid' => $bookingId]);
            $billableServiceGross = 0.0;
            $billableServiceDiscount = 0.0;
            $inStayServiceGross = 0.0;
            $inStayServiceDiscount = 0.0;
            foreach ($srvRowsStmt->fetchAll() as $so) {
                $isBillable = ($so['status'] === 'completed' || (!empty($so['is_prebooked']) && $so['status'] !== 'cancelled'));
                if ($isBillable) {
                    $billableServiceGross += (float) $so['total_price'];
                    $billableServiceDiscount += (float) ($so['discount_amount'] ?? 0);
                }
                if ($so['status'] === 'completed' && empty($so['is_prebooked'])) {
                    $inStayServiceGross += (float) $so['total_price'];
                    $inStayServiceDiscount += (float) ($so['discount_amount'] ?? 0);
                }
            }
            $serviceNet = max(0.0, $billableServiceGross - $billableServiceDiscount);
            $taxRate = (float) ($b['locked_tax_rate'] ?? 13.4);
            $surcharges = $invoiceRow ? (float) ($invoiceRow['surcharges'] ?? 0) : 0.0;
            if ($invoiceRow) {
                $grandTotal = (float) $invoiceRow['total_amount'];
            } else {
                $taxable = max(0.0, (float) $b['room_subtotal'] + (float) ($b['extension_fee'] ?? 0) + $serviceNet + $surcharges - (float) ($b['discount_amount'] ?? 0));
                $grandTotal = $taxable + round($taxable * ($taxRate / 100));
            }

            $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'verified'");
            $paidStmt->execute(['bid' => $bookingId]);
            $paidBefore = (float) $paidStmt->fetchColumn();
            $remainingBefore = max(0.0, $grandTotal - $paidBefore);

            if ($remainingBefore <= 0 && !$isExtraAdjustment) {
                throw new RuntimeException('Hóa đơn của đặt phòng này đã tất toán đầy đủ (Còn phải thu = 0 đ). Nếu cần thu bổ sung điều chỉnh, vui lòng tích xác nhận khoản thu điều chỉnh.');
            }

            $inStayNet = max(0.0, $inStayServiceGross - $inStayServiceDiscount);
            $serviceDueWithTax = $inStayNet > 0 ? ($inStayNet + round($inStayNet * ($taxRate / 100))) : 0.0;
            $expectedTarget = ($paymentType === 'partial' && $serviceDueWithTax > 0)
                ? min($remainingBefore > 0 ? $remainingBefore : $serviceDueWithTax, $serviceDueWithTax)
                : $remainingBefore;
            $variance = abs($amount - $expectedTarget);

            if (!$isExtraAdjustment && $remainingBefore > 0 && $variance > 1000 && mb_strlen(trim($mismatchNote)) < 4) {
                $expFmt = number_format($expectedTarget, 0, ',', '.');
                throw new RuntimeException("Số tiền thu chênh lệch so với công nợ ({$expFmt} đ). Vui lòng nhập lý do chênh lệch cụ thể (tối thiểu 4 ký tự).");
            }

            $validMethod = in_array($method, ['cash', 'card', 'qr_bank', 'bank_transfer', 'qr_vnpay'], true) ? $method : 'qr_bank';
            $validType = in_array($paymentType, ['deposit', 'partial', 'settlement'], true) ? $paymentType : 'settlement';
            $verifStatus = in_array($validMethod, ['cash', 'card'], true) ? 'verified' : 'pending';

            $payCode = 'PAY-' . date('ymd') . random_int(100, 999);
            $cleanNote = trim($note) !== '' ? trim($note) : ($verifStatus === 'verified' ? 'Thu tiền tại quầy Lễ tân' : 'Ghi nhận chuyển khoản QR — chờ đối soát sao kê');
            if (trim($mismatchNote) !== '') {
                $cleanNote .= ' [CHÊNH LỆCH: ' . trim($mismatchNote) . ']';
            }

            $pdo->prepare(
                "INSERT INTO payments (payment_code, booking_id, invoice_id, amount, method, payment_type, verification_status, reference_no, recorded_by, note)
                 VALUES (:code, :bid, :iid, :amt, :m, :ptype, :vst, :ref, :uid, :note)"
            )->execute([
                'code'  => $payCode,
                'bid'   => $bookingId,
                'iid'   => $invoiceId ?: null,
                'amt'   => $amount,
                'm'     => $validMethod,
                'ptype' => $validType,
                'vst'   => $verifStatus,
                'ref'   => trim($referenceNo),
                'uid'   => $staff['id'],
                'note'  => $cleanNote,
            ]);

            $this->recalculateOpenInvoice($pdo, $bookingId);

            if (!empty($b['customer_id'])) {
                $fmt = number_format($amount, 0, ',', '.');
                $this->createNotification(
                    $pdo,
                    (int) $b['customer_id'],
                    $verifStatus === 'verified'
                        ? "Xác nhận thanh toán {$fmt} đ ({$b['booking_code']})"
                        : "Ghi nhận giao dịch {$fmt} đ chờ đối soát ({$b['booking_code']})",
                    $verifStatus === 'verified'
                        ? "Lễ tân đã ghi nhận giao dịch {$payCode} ({$fmt} đ · phương thức {$validMethod})."
                        : "Giao dịch chuyển khoản {$payCode} ({$fmt} đ) đã được tạo và đang chờ Lễ tân đối soát sao kê.",
                    $verifStatus === 'verified' ? 'success' : 'info',
                    $bookingId
                );
            }
            $auditSuffix = trim($mismatchNote) !== '' ? ' · Lý do chênh lệch: ' . trim($mismatchNote) : '';
            $this->writeAudit($pdo, $staff, 'RECORD_PAYMENT', 'payment', $payCode, "Ghi nhận " . number_format($amount, 0, ',', '.') . "đ ({$validMethod}, trạng thái {$verifStatus}) cho đơn {$b['booking_code']}.{$auditSuffix}");

            return [
                'payment_code'        => $payCode,
                'amount'              => $amount,
                'method'              => $validMethod,
                'verification_status' => $verifStatus,
            ];
        });
    }


    public function verifyTransferPayment(array $staff, int $paymentId, string $decision, string $note = '', string $referenceNo = ''): void
    {
        if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
            throw new RuntimeException('Bạn không có quyền đối soát thanh toán.');
        }
        $cleanDecision = trim($decision);
        if (!in_array($cleanDecision, ['verified', 'rejected'], true)) {
            throw new RuntimeException('Quyết định đối soát không hợp lệ (chỉ chấp nhận verified hoặc rejected).');
        }
        $cleanNote = trim($note);
        $cleanRef = trim($referenceNo);
        if ($cleanDecision === 'rejected' && mb_strlen($cleanNote) < 3) {
            throw new RuntimeException('Vui lòng nhập lý do từ chối / chưa khớp sao kê (tối thiểu 3 ký tự).');
        }

        Database::transaction(function (PDO $pdo) use ($staff, $paymentId, $cleanDecision, $cleanNote, $cleanRef) {
            $stmt = $pdo->prepare(
                'SELECT p.*, b.customer_id, b.booking_code
                 FROM payments p
                 JOIN bookings b ON p.booking_id = b.id
                 WHERE p.id = :id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $paymentId]);
            $pay = $stmt->fetch();
            if (!$pay) {
                throw new RuntimeException('Không tìm thấy giao dịch.');
            }
            if ((string) ($pay['verification_status'] ?? '') !== 'pending') {
                throw new RuntimeException('Giao dịch này đã được đối soát trước đó (' . $pay['verification_status'] . '), không thể xác minh hoặc đổi trạng thái lại.');
            }

            $status = $cleanDecision;
            $nextRef = $cleanRef !== '' ? $cleanRef : (string) ($pay['reference_no'] ?? '');
            $statusNote = $status === 'verified'
                ? ($cleanNote !== '' ? "{$cleanNote} · Đối soát bởi {$staff['full_name']}" : trim(($pay['note'] ?: 'Chuyển khoản QR') . " · Đã khớp sao kê ({$staff['full_name']})"))
                : "[CHƯA KHỚP: {$cleanNote}] · Kiểm tra bởi {$staff['full_name']}";
            $pdo->prepare('UPDATE payments SET verification_status = :st, reference_no = :ref, note = :note WHERE id = :id')
                ->execute(['st' => $status, 'ref' => $nextRef, 'note' => $statusNote, 'id' => $paymentId]);
            $this->recalculateOpenInvoice($pdo, (int) $pay['booking_id']);

            if (!empty($pay['customer_id'])) {
                $this->createNotification(
                    $pdo,
                    (int) $pay['customer_id'],
                    "Kết quả đối soát thanh toán ({$pay['payment_code']})",
                    $status === 'verified'
                        ? "Giao dịch {$pay['payment_code']} cho đơn {$pay['booking_code']} đã được Lễ tân xác nhận hợp lệ."
                        : "Giao dịch {$pay['payment_code']} cho đơn {$pay['booking_code']} chưa được xác nhận (Lý do: {$cleanNote}). Vui lòng liên hệ quầy Lễ tân.",
                    $status === 'verified' ? 'success' : 'warning',
                    (int) $pay['booking_id']
                );
            }
            $auditMsg = "Đối soát thanh toán: $status"
                . ($nextRef !== '' ? " · Ref: $nextRef" : '')
                . ($cleanNote !== '' ? " · Ghi chú: $cleanNote" : '')
                . '.';
            $this->writeAudit($pdo, $staff, $status === 'verified' ? 'VERIFY_TRANSFER' : 'REJECT_TRANSFER', 'payment', (string) $pay['payment_code'], $auditMsg);
        });
    }


    public function createIncident(array $staff, int $roomId, ?int $bookingId, string $content, string $severity, string $resolutionPlan): void
    {
        Database::transaction(function (PDO $pdo) use ($staff, $roomId, $bookingId, $content, $severity, $resolutionPlan) {
            $rStmt = $pdo->prepare('SELECT room_number FROM rooms WHERE id = :id LIMIT 1');
            $rStmt->execute(['id' => $roomId]);
            $roomNum = (string) $rStmt->fetchColumn();
            if ($roomNum === '') {
                throw new RuntimeException('Phòng không tồn tại.');
            }
            $sev = in_array($severity, ['LOW', 'MEDIUM', 'HIGH'], true) ? $severity : 'LOW';
            $pdo->prepare(
                "INSERT INTO room_incidents (room_id, booking_id, room_number, content, severity, resolution_plan, status, reported_by)
                 VALUES (:rid, :bid, :rnum, :cnt, :sev, :plan, 'open', :uid)"
            )->execute([
                'rid' => $roomId, 'bid' => $bookingId, 'rnum' => $roomNum,
                'cnt' => trim($content), 'sev' => $sev, 'plan' => trim($resolutionPlan), 'uid' => $staff['id'],
            ]);
            $this->writeAudit($pdo, $staff, 'CREATE_INCIDENT', 'room', $roomNum, "Ghi nhận phản ánh sự cố Phòng $roomNum ($sev).");
        });
    }


    public function checkOutAndCreateHousekeeping(
        array $receptionist,
        int $bookingId,
        float $surcharges = 0,
        string $note = '',
        float $settlementAmount = 0,
        string $settlementMethod = 'cash',
        string $incidentContent = '',
        string $incidentSeverity = 'LOW',
        string $incidentResolution = '',
        bool $cancelUncompletedServices = false,
        string $settlementReference = 'CHECKOUT',
        string $cancelServicesReason = ''
    ): void {
        $this->ensureServiceCancelReasonColumn(Database::connection());
        Database::transaction(function (PDO $pdo) use ($receptionist, $bookingId, $surcharges, $note, $settlementAmount, $settlementMethod, $incidentContent, $incidentSeverity, $incidentResolution, $cancelUncompletedServices, $settlementReference, $cancelServicesReason) {
            if (!in_array($receptionist['role'], ['receptionist', 'admin'], true)) {
                throw new RuntimeException('Bạn không có quyền thực hiện Check-out.');
            }
            $bStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
            $bStmt->execute(['id' => $bookingId]);
            $b = $bStmt->fetch();
            $stayStmt = $pdo->prepare("SELECT * FROM stays WHERE booking_id = :bid AND status = 'active' LIMIT 1 FOR UPDATE");
            $stayStmt->execute(['bid' => $bookingId]);
            $stay = $stayStmt->fetch();
            if (!$b || !$stay) {
                throw new RuntimeException('Không tìm thấy phiên lưu trú đang hoạt động.');
            }

            $pendingSrvStmt = $pdo->prepare(
                "SELECT id, service_name_snapshot, order_group_code FROM service_orders
                 WHERE booking_id = :bid AND is_prebooked = 0 AND status IN ('pending', 'accepted')"
            );
            $pendingSrvStmt->execute(['bid' => $bookingId]);
            $uncompleted = $pendingSrvStmt->fetchAll();
            if (!empty($uncompleted)) {
                if (!$cancelUncompletedServices) {
                    $cnt = count($uncompleted);
                    throw new RuntimeException("Còn {$cnt} yêu cầu dịch vụ chưa hoàn thành. Vui lòng đánh dấu Hoàn thành ở mục Dịch vụ hoặc tích xác nhận hủy kèm lý do khi trả phòng.");
                }
                $cleanCancelReason = trim($cancelServicesReason);
                if (mb_strlen($cleanCancelReason) < 3) {
                    throw new RuntimeException('Vui lòng nhập lý do hủy các dịch vụ chưa thực hiện khi trả phòng (tối thiểu 3 ký tự).');
                }
                $pdo->prepare(
                    "UPDATE service_orders SET status = 'cancelled', cancel_reason = :rsn, discount_amount = 0, handled_by = :uid
                     WHERE booking_id = :bid AND is_prebooked = 0 AND status IN ('pending', 'accepted')"
                )->execute(['rsn' => $cleanCancelReason, 'uid' => $receptionist['id'], 'bid' => $bookingId]);
                $this->writeAudit($pdo, $receptionist, 'CHECKOUT_CANCEL_PENDING_SERVICES', 'booking', $b['booking_code'], 'Hủy ' . count($uncompleted) . " dịch vụ chưa phục vụ khi trả phòng. Lý do: {$cleanCancelReason}");
            }

            if ($settlementAmount > 0) {
                $validMethod = in_array($settlementMethod, ['cash', 'card', 'qr_bank', 'bank_transfer', 'qr_vnpay'], true) ? $settlementMethod : 'cash';
                if (!in_array($validMethod, ['cash', 'card'], true)) {
                    throw new RuntimeException('Thu tất toán trực tiếp ngay tại màn hình Check-out chỉ áp dụng Tiền mặt hoặc Thẻ/POS. Nếu khách thanh toán bằng chuyển khoản QR, vui lòng ghi nhận ở mục Thanh toán và Đối soát xác nhận đã nhận tiền trước khi bấm Check-out.');
                }
                $refClean = trim($settlementReference) !== '' ? trim($settlementReference) : 'CHECKOUT';
                $pdo->prepare(
                    "INSERT INTO payments (payment_code, booking_id, amount, method, payment_type, verification_status, reference_no, recorded_by, note)
                     VALUES (:pcode, :bid, :amt, :method, 'settlement', 'verified', :ref, :uid, 'Thanh toán tất toán khi Check-out')"
                )->execute([
                    'pcode'  => 'PAY-' . date('ymd') . random_int(100, 999),
                    'bid'    => $bookingId,
                    'amt'    => $settlementAmount,
                    'method' => $validMethod,
                    'ref'    => $refClean,
                    'uid'    => $receptionist['id'],
                ]);
            }

            $serviceTotal = $this->computeBillableServiceNet($pdo, $bookingId);
            $extensionFee = (float) ($b['extension_fee'] ?? 0);
            $discountAmount = (float) ($b['discount_amount'] ?? 0);
            $taxable = max(0.0, (float) $b['room_subtotal'] + $extensionFee + $serviceTotal + $surcharges - $discountAmount);
            $taxAmount = round($taxable * ((float) $b['locked_tax_rate'] / 100));
            $totalAmount = $taxable + $taxAmount;

            $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'verified'");
            $paidStmt->execute(['bid' => $bookingId]);
            $paidAmount = (float) $paidStmt->fetchColumn();

            if ($paidAmount < $totalAmount) {
                $missing = number_format($totalAmount - $paidAmount, 0, ',', '.');
                throw new RuntimeException("Khách còn thiếu {$missing} đ (tính theo giao dịch đã xác minh) trên tổng hóa đơn, vui lòng thu đủ hoặc đối soát xác nhận giao dịch chuyển khoản trước khi Check-out.");
            }

            $invCode = 'INV-' . str_replace('MT-PQ-', 'MT-', $b['booking_code']);
            $pdo->prepare(
                "INSERT INTO invoices (invoice_code, booking_id, stay_id, room_charges, service_charges, extension_fee, surcharges, surcharge_note, discount_amount, tax_amount, total_amount, paid_amount, payment_status, issued_by)
                 VALUES (:icode, :bid, :sid, :rc, :sc, :efee, :sur, :snote, :disc, :tax, :tot, :paid, 'paid', :uid)"
            )->execute([
                'icode' => $invCode, 'bid' => $bookingId, 'sid' => $stay['id'],
                'rc' => $b['room_subtotal'], 'sc' => $serviceTotal, 'efee' => $extensionFee, 'sur' => $surcharges,
                'snote' => $note, 'disc' => $discountAmount, 'tax' => $taxAmount, 'tot' => $totalAmount,
                'paid' => $paidAmount, 'uid' => $receptionist['id'],
            ]);

            $pdo->prepare("UPDATE service_orders SET billed_to_invoice = 1 WHERE booking_id = :bid AND (status = 'completed' OR (is_prebooked = 1 AND status != 'cancelled'))")->execute(['bid' => $bookingId]);
            $pdo->prepare("UPDATE stays SET status = 'checked_out', actual_check_out_at = NOW(), checked_out_by = :uid WHERE id = :id")->execute(['uid' => $receptionist['id'], 'id' => $stay['id']]);
            $pdo->prepare("UPDATE bookings SET status = 'checked_out' WHERE id = :id")->execute(['id' => $bookingId]);
            $pdo->prepare("UPDATE rooms SET status = 'dirty', clean_status = 'dirty' WHERE id = :id")->execute(['id' => $stay['room_id']]);
            $pdo->prepare("INSERT INTO housekeeping_tasks (room_id, stay_id, booking_code, status, assigned_note) VALUES (:rid, :sid, :code, 'pending', 'Dọn phòng sau Check-out')")
                ->execute(['rid' => $stay['room_id'], 'sid' => $stay['id'], 'code' => $b['booking_code']]);

            if (trim($incidentContent) !== '') {
                $rNumStmt = $pdo->prepare('SELECT room_number FROM rooms WHERE id = :id LIMIT 1');
                $rNumStmt->execute(['id' => $stay['room_id']]);
                $rNum = (string) $rNumStmt->fetchColumn();
                $sev = in_array($incidentSeverity, ['LOW', 'MEDIUM', 'HIGH'], true) ? $incidentSeverity : 'LOW';
                $pdo->prepare(
                    "INSERT INTO room_incidents (room_id, booking_id, room_number, content, severity, resolution_plan, status, reported_by)
                     VALUES (:rid, :bid, :rnum, :cnt, :sev, :plan, 'open', :uid)"
                )->execute([
                    'rid' => $stay['room_id'], 'bid' => $bookingId, 'rnum' => $rNum,
                    'cnt' => trim($incidentContent), 'sev' => $sev, 'plan' => trim($incidentResolution), 'uid' => $receptionist['id'],
                ]);
            }

            if (!empty($b['customer_id'])) {
                $this->createNotification(
                    $pdo,
                    (int) $b['customer_id'],
                    "Cảm ơn quý khách đã lưu trú ({$b['booking_code']})",
                    "Thủ tục trả phòng hoàn tất và hóa đơn {$invCode} đã được phát hành. Quý khách có thể xem hóa đơn và gửi đánh giá kỳ nghỉ.",
                    'success',
                    $bookingId
                );
            }

            $this->writeAudit($pdo, $receptionist, 'CHECK_OUT', 'stay', $b['booking_code'], "Hoàn tất Check-out, xuất $invCode.");
        });
    }


    private function computeBillableServiceNet(PDO $pdo, int $bookingId): float
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total_price), 0) AS gross_amt, COALESCE(SUM(discount_amount), 0) AS disc_amt
             FROM service_orders
             WHERE booking_id = :bid
               AND (status = 'completed' OR (is_prebooked = 1 AND status != 'cancelled'))"
        );
        $stmt->execute(['bid' => $bookingId]);
        $row = $stmt->fetch() ?: ['gross_amt' => 0, 'disc_amt' => 0];
        return max(0.0, (float) $row['gross_amt'] - (float) $row['disc_amt']);
    }


    private function recalculateOpenInvoice(PDO $pdo, int $bookingId): void
    {
        $invStmt = $pdo->prepare('SELECT * FROM invoices WHERE booking_id = :bid LIMIT 1');
        $invStmt->execute(['bid' => $bookingId]);
        $inv = $invStmt->fetch();
        if (!$inv) {
            return;
        }
        $b = $this->bookingModel->find($bookingId);
        if (!$b) {
            return;
        }

        $serviceTotal = $this->computeBillableServiceNet($pdo, $bookingId);
        $roomCharges = (float) $b['room_subtotal'];
        $extFee = (float) ($b['extension_fee'] ?? 0);
        $surcharges = (float) ($inv['surcharges'] ?? 0);
        $discountAmt = (float) ($b['discount_amount'] ?? 0);
        $taxable = max(0.0, $roomCharges + $extFee + $serviceTotal + $surcharges - $discountAmt);
        $taxAmount = round($taxable * ((float) $b['locked_tax_rate'] / 100));
        $totalAmount = $taxable + $taxAmount;

        $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'verified'");
        $paidStmt->execute(['bid' => $bookingId]);
        $paidAmount = (float) $paidStmt->fetchColumn();

        $payStatus = $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');
        $pdo->prepare(
            'UPDATE invoices
             SET room_charges = :rc, service_charges = :sc, extension_fee = :efee,
                 discount_amount = :disc, tax_amount = :tax, total_amount = :tot,
                 paid_amount = :paid, payment_status = :pst
             WHERE id = :id'
        )->execute([
            'rc' => $roomCharges, 'sc' => $serviceTotal, 'efee' => $extFee,
            'disc' => $discountAmt, 'tax' => $taxAmount, 'tot' => $totalAmount,
            'paid' => $paidAmount, 'pst' => $payStatus, 'id' => $inv['id'],
        ]);
    }


    private function createCancellationRefundRequest(
        PDO $pdo,
        array $booking,
        string $cancelReason,
        float $totalVerifiedPaid,
        array $cancelPolicy,
        ?int $requestedByUserId
    ): ?array {
        Refund::ensureTable($pdo);
        $bookingId = (int) $booking['id'];
        if ($totalVerifiedPaid <= 0) {
            return null;
        }

        $existingStmt = $pdo->prepare('SELECT * FROM booking_refunds WHERE booking_id = :bid LIMIT 1');
        $existingStmt->execute(['bid' => $bookingId]);
        $existing = $existingStmt->fetch();
        if ($existing) {
            return $existing;
        }

        $retainedFee = max(0.0, (float) ($cancelPolicy['retained_fee'] ?? 0));
        $refundAmount = max(0.0, (float) ($cancelPolicy['refund_amount'] ?? 0));
        $rawMethod = (string) ($booking['payment_method'] ?? 'bank_transfer');
        $refundMethod = in_array($rawMethod, ['bank_transfer', 'cash', 'card', 'qr_bank'], true) ? $rawMethod : 'bank_transfer';
        $initialStatus = $refundAmount > 0 ? 'pending' : 'rejected';
        $initialNote = $refundAmount > 0
            ? 'Yêu cầu hoàn tiền tự động phát sinh khi hủy đơn — chờ phê duyệt & chi hoàn'
            : 'Toàn bộ số tiền cọc đã thanh toán được khấu trừ phí hủy muộn theo chính sách';

        $refundCode = 'RF-' . date('ym') . random_int(1000, 9999);
        $pdo->prepare(
            "INSERT INTO booking_refunds (
                refund_code, booking_id, customer_id, cancel_reason,
                paid_verified_amount, retained_fee, refund_amount,
                status, refund_method, decision_note, requested_by, decided_at
             ) VALUES (
                :rcode, :bid, :cid, :rsn,
                :paid, :ret, :ref,
                :st, :rmeth, :dnote, :req_by, " . ($initialStatus === 'rejected' ? 'NOW()' : 'NULL') . "
             )"
        )->execute([
            'rcode'  => $refundCode,
            'bid'    => $bookingId,
            'cid'    => !empty($booking['customer_id']) ? (int) $booking['customer_id'] : null,
            'rsn'    => mb_substr(trim($cancelReason), 0, 250),
            'paid'   => $totalVerifiedPaid,
            'ret'    => $retainedFee,
            'ref'    => $refundAmount,
            'st'     => $initialStatus,
            'rmeth'  => $refundMethod,
            'dnote'  => $initialNote,
            'req_by' => $requestedByUserId,
        ]);

        return (new Refund())->findByBookingId($bookingId);
    }


    public function decideRefundRequest(array $staff, int $refundId, string $decision, string $note = '', string $refundMethod = 'bank_transfer'): array
    {
        Refund::ensureTable($this->db);
        if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
            throw new RuntimeException('Bạn không có quyền phê duyệt hoàn tiền.');
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Quyết định xử lý hoàn tiền không hợp lệ.');
        }
        $cleanNote = trim($note);
        if ($decision === 'rejected' && mb_strlen($cleanNote) < 3) {
            throw new RuntimeException('Vui lòng nhập lý do từ chối hoàn tiền (tối thiểu 3 ký tự).');
        }
        $validMethod = in_array($refundMethod, ['bank_transfer', 'cash', 'card', 'qr_bank'], true) ? $refundMethod : 'bank_transfer';

        return Database::transaction(function (PDO $pdo) use ($staff, $refundId, $decision, $cleanNote, $validMethod) {
            $stmt = $pdo->prepare(
                'SELECT r.*, b.booking_code, b.customer_id
                 FROM booking_refunds r
                 JOIN bookings b ON r.booking_id = b.id
                 WHERE r.id = :id LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $refundId]);
            $rf = $stmt->fetch();
            if (!$rf) {
                throw new RuntimeException('Không tìm thấy yêu cầu hoàn tiền.');
            }
            if ((string) $rf['status'] !== 'pending') {
                throw new RuntimeException('Yêu cầu hoàn tiền này đã được xử lý trước đó (' . $rf['status'] . ').');
            }

            $finalNote = $cleanNote !== ''
                ? $cleanNote
                : ($decision === 'approved' ? "Đã phê duyệt hoàn tiền bởi {$staff['full_name']}" : "Từ chối hoàn tiền bởi {$staff['full_name']}");

            $pdo->prepare(
                "UPDATE booking_refunds
                 SET status = :st, refund_method = :rmeth, decision_note = :dnote, approved_by = :uid, decided_at = NOW()
                 WHERE id = :id"
            )->execute([
                'st'    => $decision,
                'rmeth' => $validMethod,
                'dnote' => $finalNote,
                'uid'   => $staff['id'],
                'id'    => $refundId,
            ]);

            $fmtAmt = number_format((float) $rf['refund_amount'], 0, ',', '.');
            if (!empty($rf['customer_id'])) {
                $this->createNotification(
                    $pdo,
                    (int) $rf['customer_id'],
                    $decision === 'approved'
                        ? "Đã phê duyệt hoàn tiền {$fmtAmt} đ ({$rf['booking_code']})"
                        : "Yêu cầu hoàn tiền ({$rf['booking_code']}) chưa được phê duyệt",
                    $decision === 'approved'
                        ? "Hồ sơ hoàn tiền {$rf['refund_code']} ({$fmtAmt} đ) đã được phê duyệt và chuẩn bị giải ngân. Ghi chú: {$finalNote}"
                        : "Hồ sơ hoàn tiền {$rf['refund_code']} không được chấp thuận. Lý do: {$finalNote}",
                    $decision === 'approved' ? 'info' : 'warning',
                    (int) $rf['booking_id']
                );
            }
            $this->writeAudit(
                $pdo,
                $staff,
                $decision === 'approved' ? 'APPROVE_REFUND' : 'REJECT_REFUND',
                'refund',
                (string) $rf['refund_code'],
                ($decision === 'approved' ? 'Phê duyệt' : 'Từ chối') . " hoàn tiền {$fmtAmt} đ cho đơn {$rf['booking_code']}: {$finalNote}."
            );
            return (new Refund())->findDetailed($refundId) ?: [];
        });
    }


    public function processRefundPayout(array $staff, int $refundId, string $refundMethod, string $referenceNo, string $note = ''): array
    {
        Refund::ensureTable($this->db);
        if (!in_array($staff['role'], ['receptionist', 'admin'], true)) {
            throw new RuntimeException('Bạn không có quyền xác nhận chi hoàn tiền.');
        }
        $cleanRef = trim($referenceNo);
        if (mb_strlen($cleanRef) < 3) {
            throw new RuntimeException('Vui lòng nhập mã giao dịch chuyển khoản / số phiếu chi hoàn tiền (tối thiểu 3 ký tự).');
        }
        $validMethod = in_array($refundMethod, ['bank_transfer', 'cash', 'card', 'qr_bank'], true) ? $refundMethod : 'bank_transfer';
        $cleanNote = trim($note);

        return Database::transaction(function (PDO $pdo) use ($staff, $refundId, $validMethod, $cleanRef, $cleanNote) {
            $stmt = $pdo->prepare(
                'SELECT r.*, b.booking_code, b.customer_id
                 FROM booking_refunds r
                 JOIN bookings b ON r.booking_id = b.id
                 WHERE r.id = :id LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $refundId]);
            $rf = $stmt->fetch();
            if (!$rf) {
                throw new RuntimeException('Không tìm thấy yêu cầu hoàn tiền.');
            }
            if ((string) $rf['status'] !== 'approved') {
                throw new RuntimeException('Chỉ xác nhận đã chi tiền cho hồ sơ hoàn tiền đang ở trạng thái Đã phê duyệt (approved).');
            }

            $finalNote = $cleanNote !== '' ? $cleanNote : (string) ($rf['decision_note'] ?: "Đã chi hoàn tiền qua {$validMethod}");
            $pdo->prepare(
                "UPDATE booking_refunds
                 SET status = 'processed', refund_method = :rmeth, reference_no = :ref,
                     decision_note = :dnote, processed_by = :uid, processed_at = NOW()
                 WHERE id = :id"
            )->execute([
                'rmeth' => $validMethod,
                'ref'   => $cleanRef,
                'dnote' => $finalNote,
                'uid'   => $staff['id'],
                'id'    => $refundId,
            ]);

            $fmtAmt = number_format((float) $rf['refund_amount'], 0, ',', '.');
            if (!empty($rf['customer_id'])) {
                $this->createNotification(
                    $pdo,
                    (int) $rf['customer_id'],
                    "Hoàn tất chi hoàn tiền {$fmtAmt} đ ({$rf['booking_code']})",
                    "Khách sạn đã hoàn trả {$fmtAmt} đ cho đơn {$rf['booking_code']} (Mã HS: {$rf['refund_code']} · Mã GD: {$cleanRef} · Phương thức: {$validMethod}).",
                    'success',
                    (int) $rf['booking_id']
                );
            }
            $this->writeAudit(
                $pdo,
                $staff,
                'PROCESS_REFUND',
                'refund',
                (string) $rf['refund_code'],
                "Đã giải ngân hoàn tiền {$fmtAmt} đ cho đơn {$rf['booking_code']} (Ref: {$cleanRef}, {$validMethod})."
            );
            return (new Refund())->findDetailed($refundId) ?: [];
        });
    }
}
