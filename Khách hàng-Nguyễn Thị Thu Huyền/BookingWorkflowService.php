<?php
/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thị Thu Huyền
 *    Nghiệp vụ đặt phòng của khách: kiểm tra sức chứa, chính sách hủy, mã giảm giá, tạo đơn, yêu cầu đổi/hủy, đặt dịch vụ lưu trú
 *  CÁC HÀM TRONG TỆP NÀY:
 *    - validateGuestCapacity()   
 *    - calculateCancellationPolicy()   
 *    - validateVoucher()   
 *    - createCustomerBooking()   
 *    - requestCustomerChange()   
 *    - respondToReceptionCancel()   
 *    - orderRoomServicesBatch()   
 */
class BookingWorkflowService
{
    public static function validateGuestCapacity(array $cat, int $roomsCount, int $adults, int $children): void
    {
        $rc = max(1, $roomsCount);
        $ad = max(1, $adults);
        $ch = max(0, $children);
        $maxAdultsPerRoom = max(1, (int) ($cat['max_adults'] ?? 2));
        $maxChildrenPerRoom = max(0, (int) ($cat['max_children'] ?? 1));
        $maxAdultsAllowed = $maxAdultsPerRoom * $rc;
        $maxTotalPerRoom = $maxAdultsPerRoom + $maxChildrenPerRoom;
        $maxTotalAllowed = $maxTotalPerRoom * $rc;

        if ($ad > $maxAdultsAllowed || ($ad + $ch) > $maxTotalAllowed) {
            $catName = (string) ($cat['name'] ?? 'Hạng phòng này');
            throw new RuntimeException(
                "{$catName} chứa tối đa {$maxAdultsPerRoom} người lớn và {$maxTotalPerRoom} tổng khách mỗi phòng ({$maxAdultsAllowed} người lớn / {$maxTotalAllowed} khách cho {$rc} phòng). Vui lòng giảm số khách hoặc tăng số lượng phòng."
            );
        }
    }

    public static function calculateCancellationPolicy(array $booking, array $policiesMap, float $totalPaid): array
    {
        $freeDays = max(0, (int) ($policiesMap['free_cancel_days'] ?? 14));
        $lateFeePct = max(0.0, min(100.0, (float) ($policiesMap['late_cancel_fee_percent'] ?? 50)));
        $todayTs = strtotime(date('Y-m-d'));
        $cinTs = strtotime((string) ($booking['check_in_date'] ?? date('Y-m-d')));
        $daysUntilCheckin = (int) floor(($cinTs - $todayTs) / 86400);
        $isFreeCancel = $daysUntilCheckin >= $freeDays;

        $firstNightGross = ((float) ($booking['locked_nightly_rate'] ?? 0) + (float) ($booking['locked_package_extra'] ?? 0))
            * max(1, (int) ($booking['rooms_count'] ?? 1));
        $taxRate = (float) ($booking['locked_tax_rate'] ?? 13.4);
        $firstNightWithTax = round($firstNightGross * (1 + $taxRate / 100));

        $calculatedPenalty = $isFreeCancel ? 0.0 : round($firstNightWithTax * ($lateFeePct / 100));
        $retainedFee = $totalPaid > 0 ? min($totalPaid, $calculatedPenalty) : 0.0;
        $refundAmount = max(0.0, $totalPaid - $retainedFee);

        if ($totalPaid <= 0) {
            $summary = 'Đơn chưa ghi nhận tiền cọc/thanh toán, không phát sinh hoàn trả.';
        } elseif ($isFreeCancel) {
            $summary = 'Hủy trước hạn ' . $freeDays . ' ngày (còn ' . $daysUntilCheckin . ' ngày): Hoàn 100% tiền cọc ' . number_format($refundAmount, 0, ',', '.') . ' đ trong 3–5 ngày làm việc.';
        } else {
            $summary = 'Hủy muộn (< ' . $freeDays . ' ngày, còn ' . $daysUntilCheckin . ' ngày): Khấu trừ ' . $lateFeePct . '% đêm đầu = ' . number_format($retainedFee, 0, ',', '.') . ' đ; hoàn lại ' . number_format($refundAmount, 0, ',', '.') . ' đ trong 3–5 ngày làm việc.';
        }

        return [
            'days_until_checkin' => $daysUntilCheckin,
            'free_cancel_days'   => $freeDays,
            'late_fee_percent'   => $lateFeePct,
            'is_free_cancel'     => $isFreeCancel,
            'retained_fee'       => $retainedFee,
            'refund_amount'      => $refundAmount,
            'total_paid'         => $totalPaid,
            'summary'            => $summary,
        ];
    }

    public function validateVoucher(string $code, float $orderAmount, ?int $userId = null, string $scope = 'room'): array
    {
        $cleanCode = strtoupper(trim($code));
        if ($cleanCode === '') {
            throw new RuntimeException('Vui lòng nhập mã giảm giá.');
        }

        $stmt = $this->db->prepare('SELECT * FROM vouchers WHERE UPPER(code) = :c AND is_active = 1 LIMIT 1');
        $stmt->execute(['c' => $cleanCode]);
        $voucher = $stmt->fetch();
        if (!$voucher) {
            throw new RuntimeException('Mã giảm giá không tồn tại hoặc đã hết hiệu lực.');
        }

        $voucherScope = (string) ($voucher['scope'] ?? 'all');
        if ($voucherScope !== 'all' && $voucherScope !== $scope) {
            $label = $voucherScope === 'service' ? 'dịch vụ lưu trú tại phòng' : 'đặt phòng';
            throw new RuntimeException("Mã {$voucher['code']} chỉ áp dụng cho {$label}.");
        }

        if ($scope === 'room' && $userId !== null && $userId > 0) {
            $usedStmt = $this->db->prepare('SELECT id FROM voucher_usages WHERE voucher_id = :vid AND user_id = :uid LIMIT 1');
            $usedStmt->execute(['vid' => $voucher['id'], 'uid' => $userId]);
            if ($usedStmt->fetch()) {
                throw new RuntimeException("Tài khoản của quý khách đã sử dụng mã {$voucher['code']} trước đó (mỗi mã chỉ dùng 1 lần).");
            }
        }

        $minOrder = (float) $voucher['min_order_amount'];
        if ($orderAmount < $minOrder) {
            $formattedMin = number_format($minOrder, 0, ',', '.');
            throw new RuntimeException("Mã {$voucher['code']} áp dụng cho đơn từ {$formattedMin} đ trở lên.");
        }

        if ($voucher['discount_type'] === 'percent') {
            $discount = round($orderAmount * ((float) $voucher['discount_value'] / 100));
            $maxCap = (float) $voucher['max_discount_amount'];
            if ($maxCap > 0 && $discount > $maxCap) {
                $discount = $maxCap;
            }
        } else {
            $discount = (float) $voucher['discount_value'];
        }
        $discount = min($discount, $orderAmount);

        return [
            'voucher'         => $voucher,
            'discount_amount' => $discount,
        ];
    }

    public function createCustomerBooking(array $customer, array $payload): array
    {
        return Database::transaction(function (PDO $pdo) use ($customer, $payload) {
            $in = $this->parseDateStrict((string) ($payload['check_in_date'] ?? ''), 'Ngày nhận phòng');
            $out = $this->parseDateStrict((string) ($payload['check_out_date'] ?? ''), 'Ngày trả phòng');
            $todayStr = date('Y-m-d');
            if ($in->format('Y-m-d') < $todayStr) {
                throw new RuntimeException('Không thể chọn ngày nhận phòng trong quá khứ.');
            }
            $nights = (int) $in->diff($out)->format('%r%a');
            if ($nights <= 0) {
                throw new RuntimeException('Ngày trả phòng phải sau ngày nhận phòng.');
            }

            $catStmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :id AND is_active = 1');
            $catStmt->execute(['id' => (int) $payload['category_id']]);
            $cat = $catStmt->fetch();

            $pkgStmt = $pdo->prepare('SELECT * FROM rate_packages WHERE id = :id AND is_active = 1');
            $pkgStmt->execute(['id' => (int) $payload['package_id']]);
            $pkg = $pkgStmt->fetch();

            if (!$cat || !$pkg) {
                throw new RuntimeException('Hạng phòng hoặc gói giá không hợp lệ.');
            }

            $rawRoomsCount = (int) ($payload['rooms_count'] ?? 1);
            if ($rawRoomsCount > 1) {
                throw new RuntimeException('Hệ thống hiện giới hạn tối đa 1 phòng mỗi mã đặt phòng để đảm bảo phân bổ phòng vật lý và hóa đơn lưu trú chính xác. Quý khách vui lòng đặt từng đơn riêng nếu cần nhiều phòng.');
            }
            $reqRooms = 1;
            $reqAdults = max(1, (int) ($payload['adults'] ?? 2));
            $reqChildren = max(0, (int) ($payload['children'] ?? 0));
            self::validateGuestCapacity($cat, $reqRooms, $reqAdults, $reqChildren);

            $catModel = new RoomCategory();
            $repo = $this->catalogRepo;
            $pricingEval = $catModel->evaluateCategoryPricing($cat, $payload['check_in_date'], $payload['check_out_date']);
            if (!empty($pricingEval['is_stop_sell'])) {
                $reason = $pricingEval['stop_sell_note'] ?: 'đang tạm ngừng bán';
                throw new RuntimeException("Hạng phòng {$cat['name']} đã đóng bán trong khoảng ngày này ($reason).");
            }

            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE category_id = :cid AND status NOT IN ('maintenance', 'out_of_order')");
            $totalStmt->execute(['cid' => $cat['id']]);
            $totalOperational = (int) $totalStmt->fetchColumn();
            $booked = $this->bookingModel->countOverlappingRooms((int) $cat['id'], $payload['check_in_date'], $payload['check_out_date']);

            if (($totalOperational - $booked) < $reqRooms) {
                throw new RuntimeException('Hạng phòng này không còn đủ phòng trống trong khoảng ngày đã chọn.');
            }

            // Combo & Tùy chọn chuẩn bị trước
            $rawAddons = $payload['selected_addons'] ?? [];
            if (is_string($rawAddons)) {
                $rawAddons = json_decode($rawAddons, true) ?: [];
            }
            $addonSubtotal = 0.0;
            $selectedItems = [];
            $addonOrdersToInsert = [];

            if (is_array($rawAddons) && !empty($rawAddons)) {
                $srvStmt = $pdo->prepare('SELECT * FROM services WHERE id = :id AND is_active = 1 LIMIT 1');
                foreach ($rawAddons as $item) {
                    $sid = (int) ($item['service_id'] ?? 0);
                    $qty = max(0, (int) ($item['quantity'] ?? 0));
                    if ($sid <= 0 || $qty <= 0) {
                        continue;
                    }
                    $srvStmt->execute(['id' => $sid]);
                    $srv = $srvStmt->fetch();
                    if (!$srv) {
                        continue;
                    }
                    $srvEval = $repo->evaluateServicePricing($srv, $payload['check_in_date']);
                    $unitPrice = (float) $srvEval['effective_price'];
                    $lineTotal = $unitPrice * $qty;
                    $addonSubtotal += $lineTotal;
                    $selectedItems[] = [
                        'service_id' => (int) $srv['id'],
                        'code'       => $srv['code'],
                        'name'       => $srv['name'],
                        'category'   => $srv['category'],
                        'unit_label' => $srv['unit_label'],
                        'quantity'   => $qty,
                        'unit_price' => $unitPrice,
                        'total_price'=> $lineTotal,
                    ];
                    $addonOrdersToInsert[] = [
                        'service_id' => (int) $srv['id'],
                        'name'       => $srv['name'],
                        'quantity'   => $qty,
                        'unit_price' => $unitPrice,
                        'total_price'=> $lineTotal,
                        'category'   => $srv['category'],
                    ];
                }
            }

            $nightlyRate = (float) $pricingEval['effective_base_price'];
            $resolvedPkgs = $catModel->resolvePackagesForCategory($cat, [$pkg], $nightlyRate);
            $pkgResolved = $resolvedPkgs[0] ?? $pkg;
            $pkgExtra = (float) ($pkgResolved['resolved_extra_per_night'] ?? $pkg['extra_per_night']);
            $taxRate = max(0.0, (float) $repo->getHotelPolicyValue('tax_service_percent', '13.4'));
            $depositPct = max(10.0, min(100.0, (float) $repo->getHotelPolicyValue('deposit_percent', '30')));
            $roomSub = ($nightlyRate + $pkgExtra) * $nights * $reqRooms;
            $preDiscount = $roomSub + $addonSubtotal;

            // Áp dụng Voucher 1 lần / tài khoản
            $voucherCode = strtoupper(trim((string) ($payload['voucher_code'] ?? '')));
            $discountAmount = 0.0;
            $usedVoucherId = null;

            if ($voucherCode !== '') {
                $vResult = $this->validateVoucher($voucherCode, $preDiscount, (int) $customer['id'], 'room');
                $usedVoucherId = (int) $vResult['voucher']['id'];
                $voucherCode = (string) $vResult['voucher']['code'];
                $discountAmount = (float) $vResult['discount_amount'];
            } else {
                $voucherCode = null;
            }

            $taxable = max(0.0, $preDiscount - $discountAmount);
            $taxAmount = round($taxable * ($taxRate / 100));
            $totalEstimated = $taxable + $taxAmount;
            $deposit = round($totalEstimated * ($depositPct / 100));

            $paymentPlan = ($payload['payment_plan'] ?? 'deposit_30') === 'full_100' ? 'full_100' : 'deposit_30';
            $rawMethod = (string) ($payload['payment_method'] ?? 'bank_transfer');
            $paymentMethod = in_array($rawMethod, ['bank_transfer', 'qr_bank', 'card', 'qr_vnpay', 'cash'], true) ? $rawMethod : 'bank_transfer';
            $paidNow = $paymentPlan === 'full_100' ? $totalEstimated : $deposit;

            // Hồ sơ thành viên khóa theo tài khoản đã đăng nhập (chỉ giữ CCCD & giờ đến / chuyến bay / ghi chú do khách nhập)
            $guestName = (string) $customer['full_name'];
            $guestPhone = (string) $customer['phone'];
            $guestEmail = (string) $customer['email'];
            $rawGuestId = trim((string) ($payload['guest_id_number'] ?? ''));
            if ($rawGuestId === '') {
                $rawGuestId = trim((string) ($customer['id_number'] ?? ''));
            }
            $guestIdNumber = self::validateCccd12Digits($rawGuestId, false);

            $bookingCode = 'MT-PQ-' . date('ym') . random_int(1000, 9999);

            $stmt = $pdo->prepare(
                "INSERT INTO bookings (
                    booking_code, customer_id, created_by_user_id, source, category_id, package_id,
                    check_in_date, check_out_date, nights, rooms_count, adults, children,
                    guest_name, guest_phone, guest_email, guest_id_number,
                    arrival_time, flight_number, special_requests,
                    voucher_code, discount_amount, payment_plan, payment_method, selected_items_json,
                    locked_nightly_rate, locked_package_extra, locked_tax_rate,
                    room_subtotal, addon_subtotal, tax_amount, total_estimated, deposit_required, status
                ) VALUES (
                    :code, :cid, :uid, 'online', :cat_id, :pkg_id,
                    :in_date, :out_date, :nights, :rcount, :adults, :children,
                    :gname, :gphone, :gemail, :gid,
                    :arrival, :flight, :req,
                    :vcode, :disc, :pplan, :pmethod, :sitems,
                    :rate, :extra, :tax_rate, :room_sub, :addon_sub, :tax_amt, :total, :dep, 'pending'
                )"
            );
            $stmt->execute([
                'code'     => $bookingCode,
                'cid'      => $customer['id'],
                'uid'      => $customer['id'],
                'cat_id'   => $cat['id'],
                'pkg_id'   => $pkg['id'],
                'in_date'  => $payload['check_in_date'],
                'out_date' => $payload['check_out_date'],
                'nights'   => $nights,
                'rcount'   => $reqRooms,
                'adults'   => max(1, (int) ($payload['adults'] ?? 2)),
                'children' => max(0, (int) ($payload['children'] ?? 0)),
                'gname'    => $guestName,
                'gphone'   => $guestPhone,
                'gemail'   => $guestEmail,
                'gid'      => $guestIdNumber,
                'arrival'  => trim((string) ($payload['arrival_time'] ?? '')),
                'flight'   => strtoupper(trim((string) ($payload['flight_number'] ?? ''))),
                'req'      => trim((string) ($payload['special_requests'] ?? '')),
                'vcode'    => $voucherCode,
                'disc'     => $discountAmount,
                'pplan'    => $paymentPlan,
                'pmethod'  => $paymentMethod,
                'sitems'   => json_encode($selectedItems, JSON_UNESCAPED_UNICODE),
                'rate'     => $nightlyRate,
                'extra'    => $pkgExtra,
                'tax_rate' => $taxRate,
                'room_sub' => $roomSub,
                'addon_sub'=> $addonSubtotal,
                'tax_amt'  => $taxAmount,
                'total'    => $totalEstimated,
                'dep'      => $deposit,
            ]);

            $bookingId = (int) $pdo->lastInsertId();

            if ($usedVoucherId !== null) {
                $pdo->prepare(
                    'INSERT INTO voucher_usages (voucher_id, user_id, booking_id, discount_applied) VALUES (:vid, :uid, :bid, :disc)'
                )->execute([
                    'vid'  => $usedVoucherId,
                    'uid'  => $customer['id'],
                    'bid'  => $bookingId,
                    'disc' => $discountAmount,
                ]);
            }

            foreach ($addonOrdersToInsert as $ao) {
                $noteLabel = $ao['category'] === 'combo' ? 'Combo dịch vụ ưu đãi (Đặt trước)' : 'Chuẩn bị trước khi nhận phòng';
                $pdo->prepare(
                    "INSERT INTO service_orders (
                        booking_id, stay_id, service_id, service_name_snapshot, quantity, unit_price_snapshot, total_price,
                        requested_time, guest_note, requested_by_role, status, is_prebooked
                     ) VALUES (:bid, NULL, :sid, :sname, :qty, :up, :tot, 'Khi nhận phòng', :note, 'customer', 'accepted', 1)"
                )->execute([
                    'bid'   => $bookingId,
                    'sid'   => $ao['service_id'],
                    'sname' => $ao['name'],
                    'qty'   => $ao['quantity'],
                    'up'    => $ao['unit_price'],
                    'tot'   => $ao['total_price'],
                    'note'  => $noteLabel,
                ]);
            }

            if ($paidNow > 0) {
                $payType = $paymentPlan === 'full_100' ? 'settlement' : 'deposit';
                $cardSuffix = '';
                if ($paymentMethod === 'card') {
                    $cardLast4 = trim((string) ($payload['card_last4'] ?? ''));
                    $cardHolder = trim((string) ($payload['card_holder'] ?? ''));
                    $cardSuffix = $cardLast4 !== '' ? " (Thẻ ****{$cardLast4}" . ($cardHolder !== '' ? " · {$cardHolder}" : '') . ')' : ' (Thẻ quốc tế)';
                }
                $payNote = ($paymentPlan === 'full_100'
                    ? 'Thanh toán trực tuyến 100% khi đặt phòng'
                    : "Đặt cọc {$depositPct}% giữ phòng trực tuyến") . $cardSuffix . ' — chờ Lễ tân đối soát';
                $pdo->prepare(
                    "INSERT INTO payments (payment_code, booking_id, amount, method, payment_type, verification_status, reference_no, recorded_by, note)
                     VALUES (:pcode, :bid, :amt, :method, :ptype, 'pending', :ref, :uid, :note)"
                )->execute([
                    'pcode'  => 'PAY-' . date('ymd') . random_int(100, 999),
                    'bid'    => $bookingId,
                    'amt'    => $paidNow,
                    'method' => $paymentMethod,
                    'ptype'  => $payType,
                    'ref'    => 'ONLINE-' . $bookingCode,
                    'uid'    => $customer['id'],
                    'note'   => $payNote,
                ]);
            }

            $this->createNotification(
                $pdo,
                (int) $customer['id'],
                "Tiếp nhận đặt phòng {$bookingCode}",
                "Đơn đặt phòng {$cat['name']} ({$payload['check_in_date']} → {$payload['check_out_date']}) đã được tạo và đang chờ Lễ tân xác nhận.",
                'info',
                $bookingId
            );

            $this->writeAudit($pdo, $customer, 'CREATE_BOOKING', 'booking', $bookingCode, "Khách tạo đơn $bookingCode (Gói {$pkg['name']}, thanh toán $paymentPlan).");
            return $this->bookingModel->find($bookingId);
        });
    }

    public function requestCustomerChange(array $customer, int $bookingId, array $payload): void
    {
        Database::transaction(function (PDO $pdo) use ($customer, $bookingId, $payload) {
            $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id AND customer_id = :cid LIMIT 1');
            $stmt->execute(['id' => $bookingId, 'cid' => (int) $customer['id']]);
            $b = $stmt->fetch();

            if (!$b || !in_array($b['status'], ['pending', 'confirmed'], true)) {
                throw new RuntimeException('Không thể gửi yêu cầu thay đổi hoặc hủy cho đơn này.');
            }

            $today = date('Y-m-d');
            if ($b['check_in_date'] < $today) {
                throw new RuntimeException('Đơn đặt phòng đã quá ngày nhận phòng, không thể gửi yêu cầu đổi ngày hoặc hủy. Vui lòng liên hệ Lễ tân.');
            }

            $type = ($payload['type'] ?? 'cancel') === 'date_change' ? 'date_change' : 'cancel';
            if ($type === 'date_change') {
                $polStmt = $pdo->prepare("SELECT policy_value FROM hotel_policies WHERE policy_key = 'min_change_days' LIMIT 1");
                $polStmt->execute();
                $minDays = max(0, (int) ($polStmt->fetchColumn() ?: 3));
                $daysUntil = (int) floor((strtotime((string) $b['check_in_date']) - strtotime($today)) / 86400);
                if ($daysUntil < $minDays) {
                    throw new RuntimeException("Theo chính sách khách sạn, yêu cầu đổi ngày/hạng phòng phải gửi trước ngày nhận phòng ít nhất {$minDays} ngày (hiện chỉ còn {$daysUntil} ngày).");
                }

                $newIn = trim((string) ($payload['new_check_in_date'] ?? ''));
                $newOut = trim((string) ($payload['new_check_out_date'] ?? ''));
                if ($newIn === '' || $newOut === '' || $newIn < $today || $newOut <= $newIn) {
                    throw new RuntimeException('Ngày nhận phòng mới phải từ hôm nay trở đi và ngày trả phòng phải sau ngày nhận phòng.');
                }

                $newCatId = !empty($payload['new_category_id']) ? (int) $payload['new_category_id'] : (int) $b['category_id'];
                $catStmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :cid AND is_active = 1 LIMIT 1');
                $catStmt->execute(['cid' => $newCatId]);
                $newCatRow = $catStmt->fetch();
                if (!$newCatRow) {
                    throw new RuntimeException('Hạng phòng muốn đổi không hợp lệ.');
                }
                self::validateGuestCapacity($newCatRow, (int) ($b['rooms_count'] ?? 1), (int) $b['adults'], (int) $b['children']);
                $newCatName = (string) $newCatRow['name'];

                $reqPayload = [
                    'new_check_in_date'  => $newIn,
                    'new_check_out_date' => $newOut,
                    'new_category_id'    => $newCatId,
                    'new_category_name'  => $newCatName,
                    'reason'             => trim((string) ($payload['reason'] ?? '')),
                ];

                $pdo->prepare("UPDATE bookings SET change_request_status = 'date_change_pending', change_request_payload = :p WHERE id = :id")
                    ->execute(['p' => json_encode($reqPayload, JSON_UNESCAPED_UNICODE), 'id' => $bookingId]);
                $this->writeAudit($pdo, $customer, 'REQUEST_DATE_CHANGE', 'booking', $b['booking_code'], "Khách yêu cầu đổi lịch sang $newIn → $newOut" . ($newCatName ? " ($newCatName)" : '') . '.');
            } else {
                $cancelReasonInput = trim((string) ($payload['reason'] ?? ''));
                if (mb_strlen($cancelReasonInput) < 3) {
                    throw new RuntimeException('Vui lòng nhập rõ lý do hủy đặt phòng (tối thiểu 3 ký tự).');
                }
                $reqPayload = ['reason' => $cancelReasonInput];
                $pdo->prepare("UPDATE bookings SET change_request_status = 'cancel_pending', change_request_payload = :p WHERE id = :id")
                    ->execute(['p' => json_encode($reqPayload, JSON_UNESCAPED_UNICODE), 'id' => $bookingId]);
                $this->writeAudit($pdo, $customer, 'REQUEST_CANCEL', 'booking', $b['booking_code'], "Khách gửi yêu cầu hủy đơn {$b['booking_code']}: {$cancelReasonInput}");
            }
        });
    }

    public function respondToReceptionCancel(?array $customer, int $bookingId, string $decision, string $customerNote = '', string $verifyPhone = ''): void
    {
        if (!in_array($decision, ['accept', 'decline'], true)) {
            throw new RuntimeException('Phản hồi xác nhận hủy không hợp lệ.');
        }
        $this->ensureSchemaHardened($this->db);
        Database::transaction(function (PDO $pdo) use ($customer, $bookingId, $decision, $customerNote, $verifyPhone) {
            $b = $this->bookingModel->find($bookingId);
            if (!$b) {
                throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
            }
            $isOwner = $customer && !empty($b['customer_id']) && (int) $b['customer_id'] === (int) $customer['id'];
            $matchesPhone = $verifyPhone !== '' && str_replace(' ', '', (string) $b['guest_phone']) === str_replace(' ', '', $verifyPhone);
            if (!$isOwner && !$matchesPhone) {
                throw new RuntimeException('Bạn không có quyền xác nhận thao tác cho đơn đặt phòng này.');
            }
            if (($b['change_request_status'] ?? 'none') !== 'reception_cancel_pending') {
                throw new RuntimeException('Đơn đặt phòng này hiện không có đề nghị hủy nào cần xác nhận.');
            }
            if (!in_array($b['status'], ['pending', 'confirmed'], true)) {
                throw new RuntimeException('Chỉ áp dụng xác nhận hủy cho đơn chưa nhận phòng.');
            }

            $payload = !empty($b['change_request_payload']) ? (json_decode((string) $b['change_request_payload'], true) ?: []) : [];
            $proposalReason = trim((string) ($payload['reason'] ?? 'Lễ tân đề nghị hủy theo thỏa thuận'));
            $cleanGuestNote = trim($customerNote);
            $actor = $customer ?: ['id' => null, 'full_name' => (string) $b['guest_name'], 'role' => 'customer'];

            $staffRows = $pdo->query("SELECT id FROM users WHERE role IN ('receptionist', 'admin') AND is_active = 1")->fetchAll();

            if ($decision === 'accept') {
                $finalReason = $cleanGuestNote !== ''
                    ? "{$proposalReason} (Khách xác nhận đồng ý hủy: {$cleanGuestNote})"
                    : "{$proposalReason} (Khách đã xác nhận đồng ý hủy)";

                $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'verified'");
                $paidStmt->execute(['bid' => $bookingId]);
                $totalPaid = (float) $paidStmt->fetchColumn();
                $cancelPolicy = self::calculateCancellationPolicy($b, $this->catalogRepo->getPolicyMap(), $totalPaid);

                $pdo->prepare(
                    "UPDATE bookings
                     SET status = 'cancelled', assigned_room_id = NULL, cancelled_reason = :r,
                         change_request_status = 'none', change_request_payload = NULL
                     WHERE id = :id"
                )->execute(['r' => $finalReason, 'id' => $bookingId]);

                $requestedBy = !empty($payload['proposed_by']) ? (int) $payload['proposed_by'] : ($customer ? (int) $customer['id'] : null);
                $refundRecord = $this->createCancellationRefundRequest(
                    $pdo,
                    $b,
                    $finalReason,
                    $totalPaid,
                    $cancelPolicy,
                    $requestedBy
                );
                $refundSuffix = ($refundRecord && (float) $refundRecord['refund_amount'] > 0)
                    ? " Đã tạo phiếu hoàn tiền {$refundRecord['refund_code']} (" . number_format((float) $refundRecord['refund_amount'], 0, ',', '.') . " đ)."
                    : '';

                $this->writeAudit(
                    $pdo,
                    $actor,
                    'CUSTOMER_CONFIRMED_CANCEL',
                    'booking',
                    (string) $b['booking_code'],
                    "Khách hàng {$b['guest_name']} đã xác nhận ĐỒNG Ý HỦY đơn {$b['booking_code']}. Lý do: {$proposalReason}" . ($cleanGuestNote !== '' ? " · Ghi chú khách: {$cleanGuestNote}" : '') . ".{$refundSuffix}"
                );
                foreach ($staffRows as $st) {
                    $this->createNotification(
                        $pdo,
                        (int) $st['id'],
                        "Khách xác nhận ĐỒNG Ý HỦY ({$b['booking_code']})",
                        "{$b['guest_name']} đã đồng ý hủy đơn {$b['booking_code']} theo lý do \"{$proposalReason}\"." . ($cleanGuestNote !== '' ? " Ghi chú khách: \"{$cleanGuestNote}\"." : '') . " Phòng đã tự động giải phóng.{$refundSuffix}",
                        'info',
                        $bookingId
                    );
                }
                if (!empty($b['customer_id'])) {
                    $this->createNotification(
                        $pdo,
                        (int) $b['customer_id'],
                        "Đã hoàn tất hủy đơn ({$b['booking_code']})",
                        trim("Đơn {$b['booking_code']} đã được hủy theo xác nhận của Quý khách. {$cancelPolicy['summary']}{$refundSuffix}"),
                        'warning',
                        $bookingId
                    );
                }
            } else {
                $pdo->prepare(
                    "UPDATE bookings
                     SET change_request_status = 'none', change_request_payload = NULL
                     WHERE id = :id"
                )->execute(['id' => $bookingId]);

                $this->writeAudit(
                    $pdo,
                    $actor,
                    'CUSTOMER_DECLINED_CANCEL',
                    'booking',
                    (string) $b['booking_code'],
                    "Khách hàng {$b['guest_name']} TỪ CHỐI đề nghị hủy đơn {$b['booking_code']} và muốn giữ lại đơn." . ($cleanGuestNote !== '' ? " Phản hồi: {$cleanGuestNote}" : '')
                );
                foreach ($staffRows as $st) {
                    $this->createNotification(
                        $pdo,
                        (int) $st['id'],
                        "Khách TỪ CHỐI HỦY — Giữ lại đơn ({$b['booking_code']})",
                        "{$b['guest_name']} không đồng ý hủy đơn {$b['booking_code']} (lý do trước đó: \"{$proposalReason}\")." . ($cleanGuestNote !== '' ? " Phản hồi khách: \"{$cleanGuestNote}\"." : '') . " Vui lòng giữ nguyên đơn hoặc liên hệ trực tiếp khách.",
                        'warning',
                        $bookingId
                    );
                }
                if (!empty($b['customer_id'])) {
                    $this->createNotification(
                        $pdo,
                        (int) $b['customer_id'],
                        "Đã từ chối đề nghị hủy ({$b['booking_code']})",
                        "Quý khách đã chọn giữ lại đơn đặt phòng {$b['booking_code']}. Lễ tân đã nhận được thông báo.",
                        'success',
                        $bookingId
                    );
                }
            }
        });
    }

    public function orderRoomServicesBatch(
        array $user,
        int $bookingId,
        array $items,
        string $requestedTime = 'Ngay bây giờ',
        string $guestNote = '',
        string $voucherCode = '',
        bool $autoAccept = false
    ): array {
        return Database::transaction(function (PDO $pdo) use ($user, $bookingId, $items, $requestedTime, $guestNote, $voucherCode, $autoAccept) {
            $b = $this->bookingModel->find($bookingId);
            if (!$b) {
                throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
            }
            if ($user['role'] === 'customer' && (int) $b['customer_id'] !== (int) $user['id']) {
                throw new RuntimeException('Bạn không có quyền đặt dịch vụ cho đơn đặt phòng này.');
            }
            $today = date('Y-m-d');
            $isCheckedIn = $b['status'] === 'checked_in';
            $isConfirmedUpcoming = $b['status'] === 'confirmed' && $b['check_in_date'] >= $today;
            if (!$isCheckedIn && !$isConfirmedUpcoming) {
                throw new RuntimeException('Chỉ gọi dịch vụ khi đơn đã được xác nhận hoặc đang lưu trú.');
            }

            $stayStmt = $pdo->prepare("SELECT id FROM stays WHERE booking_id = :bid AND status = 'active' LIMIT 1");
            $stayStmt->execute(['bid' => $bookingId]);
            $stayId = $stayStmt->fetchColumn() ?: null;

            $repo = $this->catalogRepo;
            $srvStmt = $pdo->prepare('SELECT * FROM services WHERE id = :sid AND is_active = 1 LIMIT 1');
            $resolvedItems = [];
            $grossTotal = 0.0;
            foreach ($items as $it) {
                $sid = (int) ($it['service_id'] ?? 0);
                $qty = max(0, (int) ($it['quantity'] ?? 0));
                if ($sid <= 0 || $qty <= 0) {
                    continue;
                }
                $srvStmt->execute(['sid' => $sid]);
                $srv = $srvStmt->fetch();
                if (!$srv) {
                    continue;
                }
                $srvEval = $repo->evaluateServicePricing($srv);
                $up = (float) $srvEval['effective_price'];
                $lineTotal = $up * $qty;
                $grossTotal += $lineTotal;
                $resolvedItems[] = [
                    'service_id' => (int) $srv['id'],
                    'name'       => (string) $srv['name'],
                    'quantity'   => $qty,
                    'unit_price' => $up,
                    'total_price'=> $lineTotal,
                ];
            }

            if (empty($resolvedItems)) {
                throw new RuntimeException('Vui lòng chọn ít nhất 1 món hoặc dịch vụ.');
            }

            $cleanVoucher = strtoupper(trim($voucherCode));
            $discountAmount = 0.0;
            if ($cleanVoucher !== '') {
                $vRes = $this->validateVoucher($cleanVoucher, $grossTotal, (int) $user['id'], 'service');
                $cleanVoucher = (string) $vRes['voucher']['code'];
                $discountAmount = (float) $vRes['discount_amount'];
            } else {
                $cleanVoucher = null;
            }

            $requestedTime = trim($requestedTime) !== '' ? trim($requestedTime) : 'Ngay bây giờ';
            $groupCode = 'SRV-' . date('His') . '-' . random_int(100, 999);
            $isStaff = $user['role'] !== 'customer';
            $roleLabel = $isStaff ? 'receptionist' : 'customer';
            $initialStatus = ($isStaff && $autoAccept) ? 'accepted' : 'pending';
            $handledBy = $initialStatus === 'accepted' ? (int) $user['id'] : null;
            $acceptedBy = $initialStatus === 'accepted' ? (int) $user['id'] : null;
            $acceptedAtExpr = $initialStatus === 'accepted' ? 'NOW()' : 'NULL';

            $ins = $pdo->prepare(
                "INSERT INTO service_orders (
                    booking_id, stay_id, order_group_code, service_id, service_name_snapshot,
                    quantity, unit_price_snapshot, total_price, voucher_code, discount_amount,
                    requested_time, guest_note, requested_by_role, status, is_prebooked,
                    handled_by, accepted_by, accepted_at
                 ) VALUES (
                    :bid, :sid, :gcode, :srvid, :sname,
                    :qty, :up, :tot, :vcode, :disc,
                    :rt, :note, :role, :status, 0,
                    :hby, :aby, {$acceptedAtExpr}
                 )"
            );
            foreach ($resolvedItems as $idx => $ri) {
                $ins->execute([
                    'bid'    => $bookingId,
                    'sid'    => $stayId ?: null,
                    'gcode'  => $groupCode,
                    'srvid'  => $ri['service_id'],
                    'sname'  => $ri['name'],
                    'qty'    => $ri['quantity'],
                    'up'     => $ri['unit_price'],
                    'tot'    => $ri['total_price'],
                    'vcode'  => $idx === 0 ? $cleanVoucher : null,
                    'disc'   => $idx === 0 ? $discountAmount : 0.0,
                    'rt'     => trim($requestedTime) !== '' ? trim($requestedTime) : 'Ngay bây giờ',
                    'note'   => trim($guestNote),
                    'role'   => $roleLabel,
                    'status' => $initialStatus,
                    'hby'    => $handledBy,
                    'aby'    => $acceptedBy,
                ]);
            }

            $netTotal = max(0.0, $grossTotal - $discountAmount);
            if (!empty($b['customer_id'])) {
                $statusDesc = $initialStatus === 'accepted'
                    ? 'đã được Lễ tân ghi nhận thông tin và chuyển Bộ phận Bếp & Dịch vụ thực hiện.'
                    : 'đang chờ Lễ tân xác nhận thông tin khách đặt.';
                $this->createNotification(
                    $pdo,
                    (int) $b['customer_id'],
                    "Đã ghi nhận yêu cầu dịch vụ ({$b['booking_code']})",
                    "Đơn dịch vụ {$groupCode} (" . count($resolvedItems) . " mục, tổng " . number_format($netTotal, 0, ',', '.') . " đ) {$statusDesc}",
                    'info',
                    $bookingId
                );
            }
            $this->writeAudit($pdo, $user, 'ORDER_SERVICE_BATCH', 'service_order', $groupCode, "Đặt " . count($resolvedItems) . " dịch vụ cho đơn {$b['booking_code']} (trạng thái {$initialStatus}, tạm tính " . number_format($netTotal, 0, ',', '.') . " đ).");

            return [
                'order_group_code' => $groupCode,
                'items_count'      => count($resolvedItems),
                'gross_total'      => $grossTotal,
                'discount_amount'  => $discountAmount,
                'net_total'        => $netTotal,
                'status'           => $initialStatus,
            ];
        });
    }
}
