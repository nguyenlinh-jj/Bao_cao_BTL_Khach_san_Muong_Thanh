<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\CatalogRepository;
use App\Models\Refund;
use App\Models\RoomCategory;
use App\Services\BookingWorkflowService;
use DateTimeImmutable;
use PDO;
use Throwable;

class AdminController extends Controller
{
    public function dashboard(): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $pdo = Database::connection();
        $repo = new CatalogRepository();
        $catModel = new RoomCategory();

        $today = date('Y-m-d');
        $activeTab = (string) ($_GET['tab'] ?? 'dashboard');
        $rawBasis = (string) ($_GET['basis'] ?? 'stay_night');
        $basis = in_array($rawBasis, ['stay_night', 'booking_date', 'checkin_date'], true) ? $rawBasis : 'stay_night';
        $rawDays = (int) ($_GET['days'] ?? 14);
        $days = in_array($rawDays, [7, 14, 30], true) ? $rawDays : 14;
        $endDateParam = trim((string) ($_GET['end_date'] ?? ''));
        $endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateParam) ? $endDateParam : $today;

        $totalCollected = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE verification_status = 'verified'")->fetchColumn();
        $todayRevenueStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE verification_status = 'verified' AND DATE(recorded_at) = :d1");
        $todayRevenueStmt->execute(['d1' => $today]);
        $todayRevenueCollected = (float) $todayRevenueStmt->fetchColumn();

        $totalInvoiced = (float) $pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM invoices')->fetchColumn();
        $totalRooms = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE status NOT IN ('maintenance', 'out_of_order')")->fetchColumn();
        $activeStays = (int) $pdo->query("SELECT COUNT(*) FROM stays WHERE status = 'active'")->fetchColumn();
        $occupancyPct = $totalRooms > 0 ? (int) round(($activeStays / $totalRooms) * 100) : 0;

        $inHouseRow = $pdo->query("SELECT COALESCE(SUM(adults + children), 0) AS total_guests FROM bookings WHERE status = 'checked_in'")->fetch();
        $inHouseGuests = (int) ($inHouseRow['total_guests'] ?? 0);

        $adrRow = $pdo->query(
            "SELECT COALESCE(SUM(room_subtotal), 0) AS rsum, COALESCE(SUM(nights), 0) AS nsum
             FROM bookings WHERE status != 'cancelled'"
        )->fetch();
        $adrOverall = ((float) ($adrRow['nsum'] ?? 0)) > 0
            ? round(((float) $adrRow['rsum']) / ((float) $adrRow['nsum']))
            : 0.0;

        $depHeldRow = $pdo->query(
            "SELECT COALESCE(SUM(p.amount), 0) AS dep_sum
             FROM payments p
             JOIN bookings b ON p.booking_id = b.id
             WHERE p.verification_status = 'verified'
               AND b.status IN ('pending', 'confirmed', 'checked_in')"
        )->fetch();
        $depositHeld = (float) ($depHeldRow['dep_sum'] ?? 0);

        $srvRevRow = $pdo->query(
            "SELECT COALESCE(SUM(total_price - discount_amount), 0) AS srev
             FROM service_orders WHERE status = 'completed' OR (is_prebooked = 1 AND status != 'cancelled')"
        )->fetch();
        $totalServiceRevenueAll = max(0.0, (float) ($srvRevRow['srev'] ?? 0));

        // Today summary
        $todayArr = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE check_in_date = :d1 AND status IN ('pending', 'confirmed', 'checked_in')");
        $todayArr->execute(['d1' => $today]);
        $todayDep = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE check_out_date = :d1 AND status IN ('checked_in', 'checked_out')");
        $todayDep->execute(['d1' => $today]);
        $todayStay = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'checked_in' OR (status = 'confirmed' AND check_in_date <= :d1 AND check_out_date > :d2)");
        $todayStay->execute(['d1' => $today, 'd2' => $today]);
        $todayCancel = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled' AND DATE(updated_at) = :d1");
        $todayCancel->execute(['d1' => $today]);
        $todayOccupiedRoomsStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE status IN ('pending', 'confirmed', 'checked_in')
               AND check_in_date <= :d1 AND check_out_date > :d2"
        );
        $todayOccupiedRoomsStmt->execute(['d1' => $today, 'd2' => $today]);
        $todayBookedCount = (int) $todayOccupiedRoomsStmt->fetchColumn();
        $availableRoomsToday = max(0, $totalRooms - $todayBookedCount);

        $todaySummary = [
            'arrivals'              => (int) $todayArr->fetchColumn(),
            'departures'            => (int) $todayDep->fetchColumn(),
            'available_rooms_today' => $availableRoomsToday,
            'stayovers'             => (int) $todayStay->fetchColumn(),
            'cancellations'         => (int) $todayCancel->fetchColumn(),
        ];

        // Breakdowns (byCategory, bySource, byPackage)
        $byCategory = $pdo->query(
            "SELECT rc.name, rc.code,
                    COALESCE(SUM(b.nights * b.rooms_count), 0) AS room_nights,
                    COALESCE(SUM(b.room_subtotal), 0) AS room_revenue
             FROM bookings b
             JOIN room_categories rc ON b.category_id = rc.id
             WHERE b.status != 'cancelled'
             GROUP BY rc.id, rc.name, rc.code
             ORDER BY room_revenue DESC"
        )->fetchAll();

        $totalRoomRevAll = 0.0;
        foreach ($byCategory as $row) {
            $totalRoomRevAll += (float) $row['room_revenue'];
        }
        foreach ($byCategory as &$catBreak) {
            $rev = (float) $catBreak['room_revenue'];
            $nightsCnt = (int) $catBreak['room_nights'];
            $catBreak['share_pct'] = $totalRoomRevAll > 0 ? round(($rev / $totalRoomRevAll) * 100, 1) : 0;
            $catBreak['adr'] = $nightsCnt > 0 ? round($rev / $nightsCnt) : 0;
        }
        unset($catBreak);

        $bySource = $pdo->query(
            "SELECT CASE WHEN source = 'online' THEN 'Đặt trực tuyến (Website)' ELSE 'Tại quầy (Walk-in)' END AS label,
                    COUNT(*) AS booking_count,
                    COALESCE(SUM(nights * rooms_count), 0) AS room_nights,
                    COALESCE(SUM(room_subtotal), 0) AS room_revenue
             FROM bookings WHERE status != 'cancelled'
             GROUP BY source
             ORDER BY room_revenue DESC"
        )->fetchAll();
        foreach ($bySource as &$sb) {
            $rev = (float) $sb['room_revenue'];
            $n = (int) $sb['room_nights'];
            $sb['share_pct'] = $totalRoomRevAll > 0 ? round(($rev / $totalRoomRevAll) * 100, 1) : 0;
            $sb['adr'] = $n > 0 ? round($rev / $n) : 0;
        }
        unset($sb);

        $byPackage = $pdo->query(
            "SELECT rp.name AS label,
                    COUNT(*) AS booking_count,
                    COALESCE(SUM(b.nights * b.rooms_count), 0) AS room_nights,
                    COALESCE(SUM(b.room_subtotal), 0) AS room_revenue
             FROM bookings b
             JOIN rate_packages rp ON b.package_id = rp.id
             WHERE b.status != 'cancelled'
             GROUP BY rp.id, rp.name
             ORDER BY room_revenue DESC"
        )->fetchAll();
        foreach ($byPackage as &$pb) {
            $rev = (float) $pb['room_revenue'];
            $n = (int) $pb['room_nights'];
            $pb['share_pct'] = $totalRoomRevAll > 0 ? round(($rev / $totalRoomRevAll) * 100, 1) : 0;
            $pb['adr'] = $n > 0 ? round($rev / $n) : 0;
        }
        unset($pb);

        $sourceCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled'")->fetchColumn();

        // Real-time Analytics Time-Series (Current + Previous period)
        $endDt = new DateTimeImmutable($endDate);
        $startDt = $endDt->modify('-' . ($days - 1) . ' days');
        $prevEndDt = $startDt->modify('-1 day');
        $prevStartDt = $prevEndDt->modify('-' . ($days - 1) . ' days');

        $allActiveBookings = $pdo->query(
            "SELECT b.*, rc.name AS category_name, rc.code AS category_code,
                    COALESCE((SELECT SUM(so.total_price - so.discount_amount) FROM service_orders so WHERE so.booking_id = b.id AND (so.status = 'completed' OR (so.is_prebooked = 1 AND so.status != 'cancelled'))), 0) AS service_rev,
                    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.booking_id = b.id AND p.verification_status = 'verified'), 0) AS paid_rev
             FROM bookings b
             JOIN room_categories rc ON b.category_id = rc.id
             WHERE b.status != 'cancelled'"
        )->fetchAll();

        $paymentsByDateRows = $pdo->query(
            "SELECT DATE(recorded_at) AS pay_date, COALESCE(SUM(amount), 0) AS amt
             FROM payments
             WHERE verification_status = 'verified'
             GROUP BY DATE(recorded_at)"
        )->fetchAll();
        $paymentsByDate = [];
        foreach ($paymentsByDateRows as $pdr) {
            $paymentsByDate[(string) $pdr['pay_date']] = (float) $pdr['amt'];
        }

        $buildSeries = function (DateTimeImmutable $s, DateTimeImmutable $e, bool $hydrateContributing) use ($allActiveBookings, $paymentsByDate, $basis, $totalRooms, $repo): array {
            $points = [];
            $contributingIds = [];
            $catPeriodAgg = [];
            $cur = $s;
            while ($cur <= $e) {
                $dStr = $cur->format('Y-m-d');
                $roomRev = 0.0;
                $serviceRev = 0.0;
                $collectedRev = (float) ($paymentsByDate[$dStr] ?? 0.0);
                $roomNights = 0;
                $bookingsCount = 0;
                $dayBookings = [];

                foreach ($allActiveBookings as $bk) {
                    $cin = (string) $bk['check_in_date'];
                    $cout = (string) $bk['check_out_date'];
                    $nights = max(1, (int) $bk['nights']);
                    $rc = max(1, (int) $bk['rooms_count']);
                    $createdDay = substr((string) $bk['created_at'], 0, 10);
                    $effService = max((float) ($bk['service_rev'] ?? 0), (float) ($bk['addon_subtotal'] ?? 0));
                    $netRoomFull = max(0.0, (float) $bk['room_subtotal'] + (float) ($bk['extension_fee'] ?? 0) - (float) ($bk['discount_amount'] ?? 0));
                    $catName = (string) ($bk['category_name'] ?? '');

                    $matched = false;
                    $shareRoom = 0.0;
                    $shareSrv = 0.0;
                    $shareNights = 0;

                    if ($basis === 'stay_night') {
                        if ($dStr >= $cin && $dStr < $cout) {
                            $matched = true;
                            $shareRoom = $netRoomFull / $nights;
                            $shareSrv = $effService / $nights;
                            $shareNights = $rc;
                            if ($dStr === $cin) {
                                $bookingsCount++;
                            }
                        }
                    } elseif ($basis === 'booking_date') {
                        if ($createdDay === $dStr) {
                            $matched = true;
                            $shareRoom = $netRoomFull;
                            $shareSrv = $effService;
                            $shareNights = $nights * $rc;
                            $bookingsCount++;
                        }
                    } else { // checkin_date
                        if ($cin === $dStr) {
                            $matched = true;
                            $shareRoom = $netRoomFull;
                            $shareSrv = $effService;
                            $shareNights = $nights * $rc;
                            $bookingsCount++;
                        }
                    }

                    if ($matched) {
                        $roomRev += $shareRoom;
                        $serviceRev += $shareSrv;
                        $roomNights += $shareNights;
                        $contributingIds[(int) $bk['id']] = $bk;
                        $dayBookings[] = [
                            'booking_code' => (string) $bk['booking_code'],
                            'guest_name'   => (string) $bk['guest_name'],
                            'category'     => $catName,
                            'status'       => (string) $bk['status'],
                            'amount'       => round($shareRoom + $shareSrv),
                        ];
                        if (!isset($catPeriodAgg[$catName])) {
                            $catPeriodAgg[$catName] = ['name' => $catName, 'room_nights' => 0, 'room_revenue' => 0.0, 'total_revenue' => 0.0];
                        }
                        $catPeriodAgg[$catName]['room_nights'] += $shareNights;
                        $catPeriodAgg[$catName]['room_revenue'] += $shareRoom;
                        $catPeriodAgg[$catName]['total_revenue'] += ($shareRoom + $shareSrv);
                    }
                }

                $occPct = $totalRooms > 0 ? min(100, round(($roomNights / $totalRooms) * 100, 1)) : 0;
                $points[] = [
                    'date'              => $dStr,
                    'label'             => $cur->format('d/m'),
                    'bookings_count'    => $bookingsCount,
                    'room_nights'       => $roomNights,
                    'occupancy_pct'     => $occPct,
                    'room_revenue'      => round($roomRev),
                    'service_revenue'   => round($serviceRev),
                    'collected_revenue' => round($collectedRev),
                    'total_revenue'     => round($roomRev + $serviceRev),
                    'day_bookings'      => $dayBookings,
                ];
                $cur = $cur->modify('+1 day');
            }

            $sumRoomRev = array_sum(array_column($points, 'room_revenue'));
            $sumServiceRev = array_sum(array_column($points, 'service_revenue'));
            $sumCollectedRev = array_sum(array_column($points, 'collected_revenue'));
            $sumRoomNights = array_sum(array_column($points, 'room_nights'));
            $sumBookings = array_sum(array_column($points, 'bookings_count'));
            $avgOcc = count($points) > 0 && $totalRooms > 0
                ? round(($sumRoomNights / ($totalRooms * count($points))) * 100, 1)
                : 0.0;
            $adr = $sumRoomNights > 0 ? round($sumRoomRev / $sumRoomNights) : 0.0;

            usort($catPeriodAgg, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

            $hydratedContrib = [];
            foreach ($contributingIds as $bid => $rawRow) {
                if ($hydrateContributing) {
                    $detail = $repo->getBookingDetailsById((int) $bid, true);
                    if ($detail) {
                        $detail['service_rev'] = $rawRow['service_rev'] ?? 0;
                        $detail['paid_rev'] = $rawRow['paid_rev'] ?? 0;
                        $hydratedContrib[] = $detail;
                        continue;
                    }
                }
                $hydratedContrib[] = $rawRow;
            }

            return [
                'points'                => $points,
                'contributing_bookings' => $hydratedContrib,
                'by_category'           => array_values($catPeriodAgg),
                'summary' => [
                    'total_contracted' => round($sumRoomRev + $sumServiceRev),
                    'room_revenue'     => round($sumRoomRev),
                    'service_revenue'  => round($sumServiceRev),
                    'collected_rev'    => round($sumCollectedRev),
                    'room_nights'      => $sumRoomNights,
                    'bookings_count'   => $sumBookings,
                    'adr'              => $adr,
                    'occupancy_pct'    => $avgOcc,
                ],
            ];
        };

        $analytics = [
            'basis'       => $basis,
            'days'        => $days,
            'start_date'  => $startDt->format('Y-m-d'),
            'end_date'    => $endDt->format('Y-m-d'),
            'current'     => $buildSeries($startDt, $endDt, true),
            'previous'    => $buildSeries($prevStartDt, $prevEndDt, false),
        ];

        // Payments Overview (ONLY 3 unified methods: card, qr_bank, cash)
        $since30 = date('Y-m-d', strtotime('-30 days'));
        $payRows30Stmt = $pdo->prepare(
            "SELECT method, COALESCE(SUM(amount), 0) AS sum_amt, COUNT(*) AS cnt
             FROM payments
             WHERE verification_status = 'verified' AND DATE(recorded_at) >= :d1
             GROUP BY method"
        );
        $payRows30Stmt->execute(['d1' => $since30]);
        $methodTotals = ['card' => 0.0, 'qr_bank' => 0.0, 'cash' => 0.0];
        foreach ($payRows30Stmt->fetchAll() as $pr) {
            $m = (string) $pr['method'];
            if ($m === 'card') {
                $methodTotals['card'] += (float) $pr['sum_amt'];
            } elseif ($m === 'cash') {
                $methodTotals['cash'] += (float) $pr['sum_amt'];
            } else {
                $methodTotals['qr_bank'] += (float) $pr['sum_amt'];
            }
        }
        $sum30 = array_sum($methodTotals);
        $byMethod30d = [
            ['key' => 'qr_bank', 'label' => 'Mã QR / Chuyển khoản', 'amount' => $methodTotals['qr_bank'], 'pct' => $sum30 > 0 ? round(($methodTotals['qr_bank'] / $sum30) * 100, 1) : 0, 'color' => '#0E1D33'],
            ['key' => 'card', 'label' => 'Thẻ', 'amount' => $methodTotals['card'], 'pct' => $sum30 > 0 ? round(($methodTotals['card'] / $sum30) * 100, 1) : 0, 'color' => '#C9A45C'],
            ['key' => 'cash', 'label' => 'Tiền mặt', 'amount' => $methodTotals['cash'], 'pct' => $sum30 > 0 ? round(($methodTotals['cash'] / $sum30) * 100, 1) : 0, 'color' => '#10B981'],
        ];

        $pendingTransfers = $pdo->query(
            "SELECT p.*, b.booking_code, b.guest_name, b.guest_phone
             FROM payments p
             JOIN bookings b ON p.booking_id = b.id
             WHERE p.verification_status = 'pending'
             ORDER BY p.recorded_at DESC"
        )->fetchAll();
        $pendingTransferAmount = array_sum(array_map(fn($x) => (float) $x['amount'], $pendingTransfers));

        // Masked bookings + receivables + revenue audit rows
        $bIds = $pdo->query('SELECT id FROM bookings ORDER BY created_at DESC LIMIT 80')->fetchAll();
        $bookings = [];
        $receivables = [];
        $revenueAuditRows = [];
        foreach ($bIds as $row) {
            $det = $repo->getBookingDetailsById((int) $row['id'], true);
            if (!$det) {
                continue;
            }
            $bookings[] = $det;
            $invTotal = $det['invoice'] ? (float) $det['invoice']['total_amount'] : ((float) $det['total_estimated'] + (float) ($det['completed_services_total'] ?? 0));
            $paid = (float) ($det['total_paid'] ?? 0);
            $remain = max(0.0, $invTotal - $paid);
            if ($det['status'] !== 'cancelled' && $remain > 0) {
                $det['outstanding_amount'] = $remain;
                $det['computed_total'] = $invTotal;
                $receivables[] = $det;
            }
            if ($det['status'] !== 'cancelled') {
                $revenueAuditRows[] = $det;
            }
        }
        $uncollectedReceivables = array_sum(array_map(fn($r) => (float) $r['outstanding_amount'], $receivables));

        $refundRequests = (new Refund())->listAllDetailed(120);
        $pendingRefunds = array_values(array_filter($refundRequests, fn($rf) => in_array((string) ($rf['status'] ?? ''), ['pending', 'approved'], true)));
        $pendingRefundAmount = array_sum(array_map(fn($rf) => (float) ($rf['refund_amount'] ?? 0), $pendingRefunds));
        $totalRefundedProcessed = array_sum(array_map(
            fn($rf) => (string) ($rf['status'] ?? '') === 'processed' ? (float) ($rf['refund_amount'] ?? 0) : 0.0,
            $refundRequests
        ));

        $paymentsOverview = [
            'totalCollectedAll'      => $totalCollected,
            'pendingTransferAmount'  => $pendingTransferAmount,
            'pendingTransferCount'   => count($pendingTransfers),
            'uncollectedReceivables' => $uncollectedReceivables,
            'receivableCount'        => count($receivables),
            'byMethod30d'            => $byMethod30d,
            'pendingTransfers'       => $pendingTransfers,
            'receivables'            => $receivables,
            'refundRequests'         => $refundRequests,
            'pendingRefundCount'     => count($pendingRefunds),
            'pendingRefundAmount'    => $pendingRefundAmount,
            'totalRefundedProcessed' => $totalRefundedProcessed,
        ];

        // Room categories enriched
        $basePackages = $repo->getPackages();
        $categories = $pdo->query(
            'SELECT * FROM room_categories ORDER BY is_featured DESC, CASE WHEN featured_order > 0 THEN featured_order ELSE 999 END ASC, id ASC'
        )->fetchAll();
        $rooms = $pdo->query(
            "SELECT r.*, rc.code AS category_code, rc.name AS category_name,
                    (SELECT b.booking_code FROM bookings b WHERE b.assigned_room_id = r.id AND b.status IN ('confirmed', 'checked_in') ORDER BY b.id DESC LIMIT 1) AS active_booking_code,
                    (SELECT b.guest_name FROM bookings b WHERE b.assigned_room_id = r.id AND b.status IN ('confirmed', 'checked_in') ORDER BY b.id DESC LIMIT 1) AS active_guest_name
             FROM rooms r
             JOIN room_categories rc ON r.category_id = rc.id
             ORDER BY r.floor ASC, r.room_number ASC"
        )->fetchAll();

        foreach ($categories as &$cat) {
            $eval = $catModel->evaluateCategoryPricing($cat, $today);
            $cat['effective_base_price'] = $eval['effective_base_price'];
            $cat['has_active_promo'] = $eval['has_active_promo'];
            $cat['active_promo_label'] = $eval['active_promo_label'];
            $cat['promo_expired'] = $eval['promo_expired'];
            $cat['is_stop_sell'] = $eval['is_stop_sell'];
            $cat['stop_sell_note'] = $eval['stop_sell_note'];
            $cat['amenities'] = json_decode((string) ($cat['amenities_json'] ?? '[]'), true) ?: [];
            $cat['resolved_packages'] = $catModel->resolvePackagesForCategory($cat, $basePackages, $eval['effective_base_price']);
            $catRooms = array_values(array_filter($rooms, fn($rm) => (int) $rm['category_id'] === (int) $cat['id']));
            $cat['physical_rooms'] = $catRooms;
            $cat['room_count'] = count($catRooms);
        }
        unset($cat);

        // Rate overrides & maintenance logs
        $rateOverrides = $pdo->query(
            'SELECT rro.*, rc.name AS category_name, rc.code AS category_code, u.full_name AS created_by_name
             FROM room_rate_overrides rro
             JOIN room_categories rc ON rro.category_id = rc.id
             LEFT JOIN users u ON rro.created_by = u.id
             WHERE rro.is_active = 1
             ORDER BY rro.start_date DESC, rro.id DESC'
        )->fetchAll();

        $maintenanceLogs = $pdo->query(
            "SELECT rml.*, r.room_number, r.floor, rc.name AS category_name,
                    uc.full_name AS created_by_name, ur.full_name AS resolved_by_name
             FROM room_maintenance_logs rml
             JOIN rooms r ON rml.room_id = r.id
             JOIN room_categories rc ON r.category_id = rc.id
             LEFT JOIN users uc ON rml.created_by = uc.id
             LEFT JOIN users ur ON rml.resolved_by = ur.id
             ORDER BY CASE WHEN rml.status = 'active' THEN 0 ELSE 1 END, rml.created_at DESC LIMIT 80"
        )->fetchAll();

        // Services (Omit combo and prep_option in Admin Services tab per spec)
        $allServices = $repo->getServices(false);
        $adminServices = array_values(array_filter(
            $allServices,
            fn($s) => !in_array((string) $s['category'], ['combo', 'prep_option'], true)
        ));

        // Customers (strict CCCD masking, zero PII edit)
        $customersRaw = $pdo->query("SELECT id, full_name, email, phone, address, role, is_active, membership_tier, lock_reason, locked_at, created_at FROM users WHERE role = 'customer' ORDER BY id ASC")->fetchAll();
        $customers = [];
        foreach ($customersRaw as $cu) {
            $hist = array_values(array_filter($bookings, fn($bk) => (int) ($bk['customer_id'] ?? 0) === (int) $cu['id']));
            $rawIdStmt = $pdo->prepare("SELECT guest_id_number FROM bookings WHERE customer_id = :cid AND guest_id_number != '' ORDER BY id DESC LIMIT 1");
            $rawIdStmt->execute(['cid' => $cu['id']]);
            $latestId = (string) ($rawIdStmt->fetchColumn() ?: '');
            $cu['masked_id_number'] = CatalogRepository::maskIdNumber($latestId);
            $cu['bookings_count'] = count($hist);
            $cu['total_spent'] = array_sum(array_map(fn($bk) => (float) ($bk['total_paid'] ?? 0), $hist));
            $cu['bookings_history'] = $hist;
            $customers[] = $cu;
        }

        // Staff accounts
        $staff = $pdo->query("SELECT id, full_name, email, phone, role, is_active, membership_tier, lock_reason, locked_at, created_at FROM users WHERE role IN ('receptionist', 'admin') ORDER BY id ASC")->fetchAll();

        $policiesList = $repo->getPolicies();
        $policiesMap = $repo->getPolicyMap();
        $auditLogs = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 250')->fetchAll();
        $auditActors = array_values(array_unique(array_filter(array_map(fn($l) => (string) ($l['actor_name'] ?? ''), $auditLogs))));

        // Real-time 10-day room availability matrix (from operational rooms - overlapping active bookings - stop-sell overrides)
        $next10Dates = [];
        for ($di = 0; $di < 10; $di++) {
            $next10Dates[] = date('Y-m-d', strtotime($today . " +{$di} days"));
        }
        $availability10Days = [
            'dates' => $next10Dates,
            'rows'  => [],
        ];
        foreach ($categories as $catRow) {
            if ((int) ($catRow['is_active'] ?? 0) !== 1) {
                continue;
            }
            $cid = (int) $catRow['id'];
            $totalOp = count(array_filter($rooms, fn($rm) => (int) $rm['category_id'] === $cid && !in_array((string) ($rm['status'] ?? ''), ['maintenance', 'out_of_order'], true)));
            $cells = [];
            foreach ($next10Dates as $dt) {
                $hasStopSell = false;
                foreach ($rateOverrides as $ov) {
                    if ((int) $ov['category_id'] === $cid && !empty($ov['is_stop_sell']) && (string) $ov['start_date'] <= $dt && (string) $ov['end_date'] >= $dt) {
                        $hasStopSell = true;
                        break;
                    }
                }
                if ($hasStopSell) {
                    $cells[] = ['date' => $dt, 'available' => 0, 'is_stop_sell' => true];
                    continue;
                }
                $bookedCnt = 0;
                foreach ($allActiveBookings as $bk) {
                    if ((int) $bk['category_id'] === $cid && in_array((string) $bk['status'], ['pending', 'confirmed', 'checked_in'], true) && (string) $bk['check_in_date'] <= $dt && (string) $bk['check_out_date'] > $dt) {
                        $bookedCnt += 1;
                    }
                }
                $cells[] = ['date' => $dt, 'available' => max(0, $totalOp - $bookedCnt), 'is_stop_sell' => false];
            }
            $availability10Days['rows'][] = [
                'category_id'       => $cid,
                'name'              => (string) $catRow['name'],
                'code'              => (string) $catRow['code'],
                'total_operational' => $totalOp,
                'cells'             => $cells,
            ];
        }

        $this->render('admin/dashboard', [
            'pageTitle'              => 'Quản Trị Hệ Thống — Mường Thanh Luxury Phú Quốc',
            'hidePublicChrome'       => true,
            'activeTab'              => $activeTab,
            'adminUser'              => $admin,
            'totalCollected'         => $totalCollected,
            'todayRevenueCollected'  => $todayRevenueCollected,
            'totalInvoiced'          => $totalInvoiced,
            'occupancyPct'           => $occupancyPct,
            'totalRooms'             => $totalRooms,
            'activeStays'            => $activeStays,
            'inHouseGuests'          => $inHouseGuests,
            'adrOverall'             => $adrOverall,
            'depositHeld'            => $depositHeld,
            'totalServiceRevenueAll' => $totalServiceRevenueAll,
            'todaySummary'           => $todaySummary,
            'availability10Days'     => $availability10Days,
            'byCategory'             => $byCategory,
            'bySource'               => $bySource,
            'byPackage'              => $byPackage,
            'sourceCount'            => $sourceCount,
            'revenueAuditRows'       => $revenueAuditRows,
            'analytics'              => $analytics,
            'basis'                  => $basis,
            'days'                   => $days,
            'endDate'                => $endDate,
            'paymentsOverview'       => $paymentsOverview,
            'refundRequests'         => $refundRequests,
            'categories'             => $categories,
            'basePackages'           => $basePackages,
            'rooms'                  => $rooms,
            'rateOverrides'          => $rateOverrides,
            'maintenanceLogs'        => $maintenanceLogs,
            'services'               => $adminServices,
            'bookings'               => $bookings,
            'customers'              => $customers,
            'staff'                  => $staff,
            'policiesList'           => $policiesList,
            'policies'               => $policiesMap,
            'auditLogs'              => $auditLogs,
            'auditActors'            => $auditActors,
        ]);
    }

    public function createCategory(): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
            $basePrice = max(100000, (float) ($_POST['base_price'] ?? 2000000));
            $areaSqm = max(15, (int) ($_POST['area_sqm'] ?? 35));
            $maxAdults = max(1, (int) ($_POST['max_adults'] ?? 2));
            $maxChildren = max(0, (int) ($_POST['max_children'] ?? 1));
            $bedLabel = trim((string) ($_POST['bed_label'] ?? '1 giường King'));
            $viewLabel = trim((string) ($_POST['view_label'] ?? 'HƯỚNG BIỂN BÃI TRƯỜNG'));
            $groupType = ($_POST['group_type'] ?? 'room') === 'suite' ? 'suite' : 'room';
            $rawViewType = (string) ($_POST['view_type'] ?? 'ocean');
            $viewType = in_array($rawViewType, ['city', 'ocean', 'panorama'], true) ? $rawViewType : 'ocean';
            $rawBedType = (string) ($_POST['bed_type'] ?? 'king');
            $bedType = in_array($rawBedType, ['twin', 'king', 'family'], true) ? $rawBedType : 'king';
            $desc = trim((string) ($_POST['description'] ?? 'Hạng phòng nghỉ dưỡng tiêu chuẩn 5 sao tại Bãi Trường.'));
            $imageUrl = trim((string) ($_POST['image_url'] ?? '')) ?: '/assets/images/room_deluxe_ocean_king_1794856700781.jpg';
            $amenitiesRaw = trim((string) ($_POST['amenities'] ?? 'Wifi tốc độ cao, Smart TV 55 inch, Bồn tắm nằm'));
            $amenities = array_values(array_filter(array_map('trim', explode(',', $amenitiesRaw))));

            if ($code === '' || $name === '') {
                throw new \RuntimeException('Mã hạng phòng và Tên hạng phòng không được để trống.');
            }

            $pdo = Database::connection();
            $chk = $pdo->prepare('SELECT id FROM room_categories WHERE UPPER(code) = :c LIMIT 1');
            $chk->execute(['c' => $code]);
            if ($chk->fetch()) {
                throw new \RuntimeException("Mã hạng phòng {$code} đã tồn tại.");
            }

            $pdo->prepare(
                'INSERT INTO room_categories
                 (code, subtitle, name, group_type, view_label, view_type, bed_type, bed_label, area_sqm, max_adults, max_children, base_price, description, amenities_json, image_url, gallery_json, is_featured, featured_order, is_active)
                 VALUES (:c, :sub, :n, :gt, :vl, :vt, :bt, :bl, :area, :ma, :mc, :bp, :desc, :am, :img, :gal, 0, 0, 1)'
            )->execute([
                'c' => $code,
                'sub' => $subtitle ?: "{$code} · {$name}",
                'n' => $name,
                'gt' => $groupType,
                'vl' => $viewLabel,
                'vt' => $viewType,
                'bt' => $bedType,
                'bl' => $bedLabel,
                'area' => $areaSqm,
                'ma' => $maxAdults,
                'mc' => $maxChildren,
                'bp' => $basePrice,
                'desc' => $desc,
                'am' => json_encode($amenities, JSON_UNESCAPED_UNICODE),
                'img' => $imageUrl,
                'gal' => json_encode([$imageUrl], JSON_UNESCAPED_UNICODE),
            ]);

            $this->logAudit($pdo, $admin, 'CREATE_CATEGORY', 'room_category', $code, "Tạo hạng phòng mới {$name} ({$code}) · " . number_format($basePrice, 0, ',', '.') . 'đ.');
            $this->redirect('/admin?tab=room_categories', 'ok', "Đã thêm hạng phòng {$name}.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=room_categories', 'err', $e->getMessage());
        }
    }

    public function updateCategory(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM room_categories WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $id]);
            $cur = $stmt->fetch();
            if (!$cur) {
                throw new \RuntimeException('Không tìm thấy hạng phòng.');
            }

            $name = trim((string) ($_POST['name'] ?? $cur['name']));
            $subtitle = trim((string) ($_POST['subtitle'] ?? $cur['subtitle']));
            $basePrice = max(100000, (float) ($_POST['base_price'] ?? $cur['base_price']));
            $areaSqm = max(15, (int) ($_POST['area_sqm'] ?? $cur['area_sqm']));
            $maxAdults = max(1, (int) ($_POST['max_adults'] ?? $cur['max_adults']));
            $bedLabel = trim((string) ($_POST['bed_label'] ?? $cur['bed_label']));
            $viewLabel = trim((string) ($_POST['view_label'] ?? $cur['view_label']));
            $desc = trim((string) ($_POST['description'] ?? $cur['description']));
            $imageUrl = trim((string) ($_POST['image_url'] ?? '')) ?: (string) $cur['image_url'];

            $today = date('Y-m-d');
            $rawPromoType = isset($_POST['promo_discount_type']) ? (string) $_POST['promo_discount_type'] : (string) ($cur['promo_discount_type'] ?? 'none');
            $promoType = in_array($rawPromoType, ['none', 'percent', 'fixed'], true) ? $rawPromoType : 'none';
            $promoValue = max(0, (float) ($_POST['promo_discount_value'] ?? $cur['promo_discount_value']));
            $promoLabel = trim((string) ($_POST['promo_label'] ?? $cur['promo_label']));
            $promoStart = trim((string) ($_POST['promo_start_date'] ?? ($cur['promo_start_date'] ?? '')));
            $promoEnd = trim((string) ($_POST['promo_end_date'] ?? ($cur['promo_end_date'] ?? '')));
            if ($promoType === 'none') {
                $promoStart = '';
                $promoEnd = '';
            } else {
                if ($promoStart !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $promoStart)) {
                    throw new \RuntimeException('Ngày bắt đầu khuyến mãi không đúng định dạng.');
                }
                if ($promoEnd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $promoEnd)) {
                    throw new \RuntimeException('Ngày kết thúc khuyến mãi không đúng định dạng.');
                }
                if ($promoStart !== '' && $promoStart !== (string) ($cur['promo_start_date'] ?? '') && $promoStart < $today) {
                    throw new \RuntimeException('Ngày bắt đầu khuyến mãi không được là ngày đã qua.');
                }
                if ($promoEnd !== '' && $promoEnd !== (string) ($cur['promo_end_date'] ?? '') && $promoEnd < $today) {
                    throw new \RuntimeException('Ngày kết thúc khuyến mãi không được là ngày đã qua.');
                }
                if ($promoStart !== '' && $promoEnd !== '' && $promoEnd < $promoStart) {
                    throw new \RuntimeException('Ngày kết thúc khuyến mãi phải từ ngày bắt đầu trở đi.');
                }
            }
            $isFeatured = isset($_POST['is_featured']) ? (int) ($_POST['is_featured'] ? 1 : 0) : (int) $cur['is_featured'];
            $featuredOrder = max(0, (int) ($_POST['featured_order'] ?? $cur['featured_order']));

            $pkgConfigJson = $cur['package_config_json'];
            if (isset($_POST['package_config']) && is_array($_POST['package_config'])) {
                $pkgCodes = $pdo->query('SELECT code FROM rate_packages WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
                $normalizedCfg = [];
                foreach ($pkgCodes as $pCode) {
                    $codeKey = (string) $pCode;
                    $submitted = is_array($_POST['package_config'][$codeKey] ?? null) ? $_POST['package_config'][$codeKey] : [];
                    $customPrice = trim((string) ($submitted['price'] ?? ''));
                    $normalizedCfg[$codeKey] = [
                        'enabled' => !empty($submitted['enabled']),
                        'price'   => ($customPrice !== '' && (float) $customPrice > 0) ? (float) $customPrice : '',
                    ];
                }
                $pkgConfigJson = json_encode($normalizedCfg, JSON_UNESCAPED_UNICODE);
            }

            $amenitiesJson = $cur['amenities_json'];
            if (isset($_POST['amenities']) && is_string($_POST['amenities'])) {
                $arr = array_values(array_filter(array_map('trim', explode(',', $_POST['amenities']))));
                $amenitiesJson = json_encode($arr, JSON_UNESCAPED_UNICODE);
            }

            $pdo->prepare(
                'UPDATE room_categories
                 SET name = :n, subtitle = :sub, base_price = :bp, area_sqm = :area, max_adults = :ma,
                     bed_label = :bl, view_label = :vl, description = :desc, image_url = :img, amenities_json = :am,
                     promo_discount_type = :pdt, promo_discount_value = :pdv, promo_label = :plbl,
                     promo_start_date = :ps, promo_end_date = :pe,
                     is_featured = :feat, featured_order = :forder, package_config_json = :pconf
                 WHERE id = :id'
            )->execute([
                'n' => $name,
                'sub' => $subtitle,
                'bp' => $basePrice,
                'area' => $areaSqm,
                'ma' => $maxAdults,
                'bl' => $bedLabel,
                'vl' => $viewLabel,
                'desc' => $desc,
                'img' => $imageUrl,
                'am' => $amenitiesJson,
                'pdt' => $promoType,
                'pdv' => $promoValue,
                'plbl' => $promoLabel,
                'ps' => $promoStart !== '' ? $promoStart : null,
                'pe' => $promoEnd !== '' ? $promoEnd : null,
                'feat' => $isFeatured,
                'forder' => $featuredOrder,
                'pconf' => $pkgConfigJson,
                'id' => (int) $id,
            ]);

            $this->logAudit($pdo, $admin, 'UPDATE_CATEGORY', 'room_category', (string) $cur['code'], "Cập nhật hạng phòng {$name} (giá gốc " . number_format($basePrice, 0, ',', '.') . 'đ, khuyến mãi ' . $promoType . ').');
            $this->redirect('/admin?tab=room_categories', 'ok', "Đã lưu cấu hình hạng phòng {$name} và đồng bộ Trang chủ.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=room_categories', 'err', $e->getMessage());
        }
    }

    public function toggleCategory(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $pdo = Database::connection();
        $pdo->prepare('UPDATE room_categories SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => (int) $id]);
        $this->logAudit($pdo, $admin, 'TOGGLE_CATEGORY', 'room_category', $id, "Chuyển trạng thái hiển thị/ngừng bán hạng phòng #$id.");
        $this->redirect('/admin?tab=room_categories', 'ok', "Đã cập nhật trạng thái hiển thị hạng phòng #$id.");
    }

    public function deleteCategory(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $catId = (int) $id;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, code FROM room_categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $catId]);
        $cat = $stmt->fetch();
        if (!$cat) {
            $this->redirect('/admin?tab=room_categories', 'err', 'Không tìm thấy hạng phòng.');
        }

        $rStmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE category_id = :id');
        $rStmt->execute(['id' => $catId]);
        $roomsCnt = (int) $rStmt->fetchColumn();

        $bStmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE category_id = :id');
        $bStmt->execute(['id' => $catId]);
        $bookingsCnt = (int) $bStmt->fetchColumn();

        if ($roomsCnt > 0 || $bookingsCnt > 0) {
            $this->redirect('/admin?tab=room_categories', 'err', "Không thể xóa hạng phòng {$cat['name']} vì đang có {$roomsCnt} phòng vật lý và {$bookingsCnt} đơn đặt phòng liên kết. Hãy dùng nút 'Ngừng bán (Ẩn khỏi Trang chủ)'.");
        }

        $pdo->prepare('DELETE FROM room_rate_overrides WHERE category_id = :id')->execute(['id' => $catId]);
        $pdo->prepare('DELETE FROM room_categories WHERE id = :id')->execute(['id' => $catId]);
        $this->logAudit($pdo, $admin, 'DELETE_CATEGORY', 'room_category', (string) $cat['code'], "Xóa vĩnh viễn hạng phòng {$cat['name']} ({$cat['code']}).");
        $this->redirect('/admin?tab=room_categories', 'ok', "Đã xóa hạng phòng {$cat['name']}.");
    }

    public function quickRateOverride(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $startDate = trim((string) ($_POST['start_date'] ?? ''));
            $endDate = trim((string) ($_POST['end_date'] ?? ''));
            $isStopSell = !empty($_POST['is_stop_sell']) ? 1 : 0;
            $overridePrice = isset($_POST['override_price']) && trim((string) $_POST['override_price']) !== ''
                ? max(100000, (float) $_POST['override_price'])
                : null;
            $note = trim((string) ($_POST['note'] ?? ''));

            $today = date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $startDate) {
                throw new \RuntimeException('Khoảng ngày áp dụng không hợp lệ.');
            }
            if ($startDate < $today || $endDate < $today) {
                throw new \RuntimeException('Không thể thiết lập điều chỉnh giá hoặc đóng bán cho ngày đã qua.');
            }
            if (!$isStopSell && $overridePrice === null) {
                throw new \RuntimeException('Vui lòng nhập giá áp dụng mới hoặc chọn Đóng bán (Stop-sell).');
            }

            $pdo = Database::connection();
            $pdo->prepare(
                'INSERT INTO room_rate_overrides (category_id, start_date, end_date, override_price, is_stop_sell, note, is_active, created_by)
                 VALUES (:cid, :sd, :ed, :op, :ss, :note, 1, :uid)'
            )->execute([
                'cid' => (int) $id,
                'sd' => $startDate,
                'ed' => $endDate,
                'op' => $overridePrice,
                'ss' => $isStopSell,
                'note' => $note ?: ($isStopSell ? 'Đóng bán theo ngày' : 'Điều chỉnh giá nhanh'),
                'uid' => $admin['id'],
            ]);

            $this->logAudit($pdo, $admin, 'QUICK_RATE_OVERRIDE', 'room_category', $id, ($isStopSell ? 'Đóng bán (Stop-sell)' : 'Điều chỉnh giá nhanh') . " từ {$startDate} đến {$endDate}.");
            $this->redirect('/admin?tab=rooms_list', 'ok', 'Đã lưu cấu hình điều chỉnh giá / đóng bán theo ngày.');
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=room_categories', 'err', $e->getMessage());
        }
    }

    public function deleteRateOverride(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $pdo = Database::connection();
        $pdo->prepare('UPDATE room_rate_overrides SET is_active = 0 WHERE id = :id')->execute(['id' => (int) $id]);
        $this->logAudit($pdo, $admin, 'DELETE_RATE_OVERRIDE', 'rate_override', $id, "Gỡ bỏ lịch đóng bán / điều chỉnh giá #$id.");
        $this->redirect('/admin?tab=rooms_list', 'ok', "Đã gỡ lịch điều chỉnh #$id.");
    }

    public function createRoom(): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $roomNumber = strtoupper(trim((string) ($_POST['room_number'] ?? '')));
            $floor = max(1, (int) ($_POST['floor'] ?? 1));
            $catId = (int) ($_POST['category_id'] ?? 0);
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if ($roomNumber === '' || $catId <= 0) {
                throw new \RuntimeException('Vui lòng nhập số phòng và chọn hạng phòng.');
            }
            $pdo = Database::connection();
            $chk = $pdo->prepare('SELECT id FROM rooms WHERE room_number = :rn LIMIT 1');
            $chk->execute(['rn' => $roomNumber]);
            if ($chk->fetch()) {
                throw new \RuntimeException("Số phòng {$roomNumber} đã tồn tại.");
            }
            $pdo->prepare("INSERT INTO rooms (room_number, floor, category_id, status, clean_status, notes) VALUES (:rn, :fl, :cid, 'available', 'clean', :n)")
                ->execute(['rn' => $roomNumber, 'fl' => $floor, 'cid' => $catId, 'n' => $notes]);
            $this->logAudit($pdo, $admin, 'CREATE_ROOM', 'room', $roomNumber, "Thêm phòng vật lý {$roomNumber} (Tầng {$floor}, hạng #$catId).");
            $this->redirect('/admin?tab=rooms_list', 'ok', "Đã thêm phòng {$roomNumber}.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=rooms_list', 'err', $e->getMessage());
        }
    }

    public function lockRoom(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $today = date('Y-m-d');
            $lockType = ($_POST['lock_type'] ?? 'maintenance') === 'locked' ? 'locked' : 'maintenance';
            $startDate = trim((string) ($_POST['start_date'] ?? $today));
            $endDate = trim((string) ($_POST['end_date'] ?? date('Y-m-d', strtotime('+3 days'))));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $startDate) {
                throw new \RuntimeException('Khoảng ngày khóa/bảo trì phòng không hợp lệ.');
            }
            if ($startDate < $today || $endDate < $today) {
                throw new \RuntimeException('Không thể chọn ngày khóa/bảo trì phòng đã qua trong quá khứ.');
            }
            if (mb_strlen($reason) < 4) {
                throw new \RuntimeException('Vui lòng nhập lý do khóa phòng / bảo trì (tối thiểu 4 ký tự).');
            }
            $pdo = Database::connection();
            $rStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id LIMIT 1');
            $rStmt->execute(['id' => (int) $id]);
            $room = $rStmt->fetch();
            if (!$room || $room['status'] === 'occupied') {
                throw new \RuntimeException('Không thể khóa phòng đang có khách lưu trú.');
            }

            Database::transaction(function (PDO $tx) use ($admin, $room, $lockType, $startDate, $endDate, $reason) {
                $tx->prepare("UPDATE rooms SET status = 'maintenance', notes = :n WHERE id = :id")
                    ->execute(['n' => $reason, 'id' => (int) $room['id']]);
                $tx->prepare(
                    "INSERT INTO room_maintenance_logs (room_id, lock_type, start_date, end_date, reason, status, created_by)
                     VALUES (:rid, :lt, :sd, :ed, :rs, 'active', :uid)"
                )->execute([
                    'rid' => (int) $room['id'],
                    'lt' => $lockType,
                    'sd' => $startDate !== '' ? $startDate : null,
                    'ed' => $endDate !== '' ? $endDate : null,
                    'rs' => $reason,
                    'uid' => $admin['id'],
                ]);
                $this->logAudit($tx, $admin, 'LOCK_ROOM', 'room', (string) $room['room_number'], "Khóa phòng P.{$room['room_number']} ($lockType): $reason");
            });

            $this->redirect('/admin?tab=rooms_list', 'ok', "Đã khóa bảo trì Phòng {$room['room_number']}.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=rooms_list', 'err', $e->getMessage());
        }
    }

    public function unlockRoom(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $pdo = Database::connection();
            $rStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id LIMIT 1');
            $rStmt->execute(['id' => (int) $id]);
            $room = $rStmt->fetch();
            if (!$room) {
                throw new \RuntimeException('Không tìm thấy phòng.');
            }
            if ((string) $room['status'] === 'occupied') {
                throw new \RuntimeException('Phòng đang có khách lưu trú.');
            }
            Database::transaction(function (PDO $tx) use ($admin, $room) {
                // Sau khi kết thúc bảo trì, phòng bắt buộc phải chuyển sang Chờ dọn (dirty) để Buồng phòng vệ sinh & nghiệm thu sạch sẽ trước khi mở bán.
                $tx->prepare("UPDATE rooms SET status = 'dirty', clean_status = 'dirty', notes = 'Hoàn tất bảo trì — chờ buồng phòng vệ sinh & nghiệm thu' WHERE id = :id")
                    ->execute(['id' => (int) $room['id']]);
                $tx->prepare("UPDATE room_maintenance_logs SET status = 'resolved', resolved_by = :uid, resolved_at = NOW() WHERE room_id = :rid AND status = 'active'")
                    ->execute(['uid' => $admin['id'], 'rid' => (int) $room['id']]);
                $tx->prepare(
                    "INSERT INTO housekeeping_tasks (room_id, stay_id, booking_code, task_type, status, assigned_note)
                     VALUES (:rid, NULL, '', 'checkout_clean', 'pending', 'Kiểm tra & vệ sinh phòng sau bảo trì trước khi đón khách')"
                )->execute(['rid' => (int) $room['id']]);
                $this->logAudit($tx, $admin, 'UNLOCK_ROOM', 'room', (string) $room['room_number'], "Mở khóa bảo trì Phòng {$room['room_number']} — chuyển sang Chờ dọn & tạo phiếu vệ sinh hậu bảo trì.");
            });
            $this->redirect('/admin?tab=rooms_list', 'ok', "Đã mở khóa bảo trì Phòng {$room['room_number']} — phòng đã chuyển sang Chờ dọn để bộ phận Buồng phòng vệ sinh & nghiệm thu trước khi đón khách.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=rooms_list', 'err', $e->getMessage());
        }
    }

    public function updateRoomStatus(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $rawCleanStatus = (string) ($_POST['clean_status'] ?? 'clean');
        $cleanStatus = in_array($rawCleanStatus, ['clean', 'dirty', 'inspecting'], true) ? $rawCleanStatus : 'clean';
        $roomStatus = $cleanStatus === 'dirty' ? 'dirty' : 'available';
        $pdo = Database::connection();
        $pdo->prepare("UPDATE rooms SET clean_status = :cs, status = CASE WHEN status = 'occupied' THEN 'occupied' ELSE :rs END WHERE id = :id")
            ->execute(['cs' => $cleanStatus, 'rs' => $roomStatus, 'id' => (int) $id]);
        $this->logAudit($pdo, $admin, 'UPDATE_ROOM_CLEAN', 'room', $id, "Cập nhật trạng thái dọn phòng #$id thành $cleanStatus.");
        $this->redirect('/admin?tab=rooms_list', 'ok', "Đã cập nhật trạng thái dọn dẹp phòng #$id.");
    }

    public function createService(): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? 'dining_seafood'));
            if (in_array($category, ['combo', 'prep_option'], true)) {
                throw new \RuntimeException('Không hỗ trợ tạo danh mục Combo/Chuẩn bị trong phân hệ dịch vụ này.');
            }
            $unit = trim((string) ($_POST['unit_label'] ?? '/phần'));
            $price = max(10000, (float) ($_POST['price'] ?? 0));
            $promoPrice = isset($_POST['promo_price']) && trim((string) $_POST['promo_price']) !== '' ? (float) $_POST['promo_price'] : null;
            $promoTag = trim((string) ($_POST['promo_tag'] ?? ''));
            $promoStart = trim((string) ($_POST['promo_start_date'] ?? ''));
            $promoEnd = trim((string) ($_POST['promo_end_date'] ?? ''));
            $duration = trim((string) ($_POST['duration_label'] ?? ''));
            $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
            $desc = trim((string) ($_POST['description'] ?? ''));

            if ($code === '' || $name === '') {
                throw new \RuntimeException('Vui lòng nhập mã và tên dịch vụ.');
            }
            $today = date('Y-m-d');
            if ($promoStart !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $promoStart) || $promoStart < $today)) {
                throw new \RuntimeException('Ngày bắt đầu khuyến mãi dịch vụ không hợp lệ hoặc là ngày đã qua.');
            }
            if ($promoEnd !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $promoEnd) || $promoEnd < $today)) {
                throw new \RuntimeException('Ngày kết thúc khuyến mãi dịch vụ không hợp lệ hoặc là ngày đã qua.');
            }
            if ($promoStart !== '' && $promoEnd !== '' && $promoEnd < $promoStart) {
                throw new \RuntimeException('Ngày kết thúc khuyến mãi dịch vụ phải từ ngày bắt đầu trở đi.');
            }
            $pdo = Database::connection();
            $pdo->prepare(
                'INSERT INTO services (code, name, category, unit_label, price, promo_price, promo_tag, promo_start_date, promo_end_date, duration_label, image_url, description, is_active)
                 VALUES (:c, :n, :cat, :u, :p, :pp, :pt, :ps, :pe, :dur, :img, :d, 1)'
            )->execute([
                'c' => $code, 'n' => $name, 'cat' => $category, 'u' => $unit, 'p' => $price,
                'pp' => $promoPrice, 'pt' => $promoTag ?: null,
                'ps' => $promoStart !== '' ? $promoStart : null,
                'pe' => $promoEnd !== '' ? $promoEnd : null,
                'dur' => $duration, 'img' => $imageUrl, 'd' => $desc,
            ]);
            $this->logAudit($pdo, $admin, 'CREATE_SERVICE', 'service', $code, "Thêm dịch vụ {$name} (" . number_format($price, 0, ',', '.') . 'đ).');
            $this->redirect('/admin?tab=services', 'ok', "Đã thêm dịch vụ {$name}.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=services', 'err', $e->getMessage());
        }
    }

    public function updateService(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $pdo = Database::connection();
            $curStmt = $pdo->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
            $curStmt->execute(['id' => (int) $id]);
            $curSrv = $curStmt->fetch();
            if (!$curSrv) {
                throw new \RuntimeException('Không tìm thấy dịch vụ.');
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? 'dining_seafood'));
            $unit = trim((string) ($_POST['unit_label'] ?? '/phần'));
            $price = max(10000, (float) ($_POST['price'] ?? 0));
            $promoPrice = isset($_POST['promo_price']) && trim((string) $_POST['promo_price']) !== '' ? (float) $_POST['promo_price'] : null;
            $promoTag = trim((string) ($_POST['promo_tag'] ?? ''));
            $promoStart = trim((string) ($_POST['promo_start_date'] ?? ''));
            $promoEnd = trim((string) ($_POST['promo_end_date'] ?? ''));
            $duration = trim((string) ($_POST['duration_label'] ?? ''));
            $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
            $desc = trim((string) ($_POST['description'] ?? ''));

            $today = date('Y-m-d');
            if ($promoStart !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $promoStart)) {
                    throw new \RuntimeException('Ngày bắt đầu khuyến mãi dịch vụ không đúng định dạng.');
                }
                if ($promoStart !== (string) ($curSrv['promo_start_date'] ?? '') && $promoStart < $today) {
                    throw new \RuntimeException('Ngày bắt đầu khuyến mãi dịch vụ không được là ngày đã qua.');
                }
            }
            if ($promoEnd !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $promoEnd)) {
                    throw new \RuntimeException('Ngày kết thúc khuyến mãi dịch vụ không đúng định dạng.');
                }
                if ($promoEnd !== (string) ($curSrv['promo_end_date'] ?? '') && $promoEnd < $today) {
                    throw new \RuntimeException('Ngày kết thúc khuyến mãi dịch vụ không được là ngày đã qua.');
                }
            }
            if ($promoStart !== '' && $promoEnd !== '' && $promoEnd < $promoStart) {
                throw new \RuntimeException('Ngày kết thúc khuyến mãi dịch vụ phải từ ngày bắt đầu trở đi.');
            }

            $pdo->prepare(
                'UPDATE services
                 SET name = :n, category = :cat, unit_label = :u, price = :p, promo_price = :pp, promo_tag = :pt,
                     promo_start_date = :ps, promo_end_date = :pe, duration_label = :dur, image_url = :img, description = :d
                 WHERE id = :id'
            )->execute([
                'n' => $name, 'cat' => $category, 'u' => $unit, 'p' => $price,
                'pp' => $promoPrice, 'pt' => $promoTag ?: null,
                'ps' => $promoStart !== '' ? $promoStart : null,
                'pe' => $promoEnd !== '' ? $promoEnd : null,
                'dur' => $duration, 'img' => $imageUrl, 'd' => $desc, 'id' => (int) $id,
            ]);
            $this->logAudit($pdo, $admin, 'UPDATE_SERVICE', 'service', $id, "Cập nhật dịch vụ {$name} (" . number_format($price, 0, ',', '.') . 'đ).');
            $this->redirect('/admin?tab=services', 'ok', "Đã cập nhật dịch vụ {$name}.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=services', 'err', $e->getMessage());
        }
    }

    public function toggleService(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $pdo = Database::connection();
        $pdo->prepare('UPDATE services SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => (int) $id]);
        $this->logAudit($pdo, $admin, 'TOGGLE_SERVICE', 'service', $id, "Thay đổi trạng thái bán dịch vụ #$id.");
        $this->redirect('/admin?tab=services', 'ok', "Đã cập nhật trạng thái dịch vụ #$id.");
    }

    public function deleteService(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $srvId = (int) $id;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, code FROM services WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $srvId]);
        $srv = $stmt->fetch();
        if (!$srv) {
            $this->redirect('/admin?tab=services', 'err', 'Không tìm thấy dịch vụ.');
        }

        $oStmt = $pdo->prepare('SELECT COUNT(*) FROM service_orders WHERE service_id = :id');
        $oStmt->execute(['id' => $srvId]);
        $ordersCnt = (int) $oStmt->fetchColumn();
        if ($ordersCnt > 0) {
            $this->redirect('/admin?tab=services', 'err', "Không thể xóa dịch vụ {$srv['name']} vì đã phát sinh {$ordersCnt} lượt gọi dịch vụ trong hóa đơn. Hãy dùng nút 'Ngừng phục vụ'.");
        }

        $pdo->prepare('DELETE FROM services WHERE id = :id')->execute(['id' => $srvId]);
        $this->logAudit($pdo, $admin, 'DELETE_SERVICE', 'service', (string) $srv['code'], "Xóa vĩnh viễn dịch vụ {$srv['name']} ({$srv['code']}).");
        $this->redirect('/admin?tab=services', 'ok', "Đã xóa dịch vụ {$srv['name']}.");
    }

    public function toggleUser(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $targetId = (int) $id;
        $rawReturnTab = (string) ($_POST['return_tab'] ?? '');
        $returnTab = in_array($rawReturnTab, ['customers', 'staff'], true) ? $rawReturnTab : 'customers';

        if ($targetId === (int) $admin['id']) {
            $this->redirect("/admin?tab={$returnTab}", 'err', 'Không thể tự khóa tài khoản đang đăng nhập.');
        }

        $pdo = Database::connection();
        $uStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $uStmt->execute(['id' => $targetId]);
        $target = $uStmt->fetch();
        if (!$target) {
            $this->redirect("/admin?tab={$returnTab}", 'err', 'Không tìm thấy tài khoản.');
        }

        $nextActive = ((int) $target['is_active'] === 1) ? 0 : 1;
        $lockReason = trim((string) ($_POST['lock_reason'] ?? ''));

        if ($nextActive === 0) {
            if (mb_strlen($lockReason) < 4) {
                $this->redirect("/admin?tab={$returnTab}", 'err', 'Bắt buộc nhập lý do khóa tài khoản (tối thiểu 4 ký tự).');
            }
            $pdo->prepare('UPDATE users SET is_active = 0, lock_reason = :r, locked_at = NOW(), locked_by = :by WHERE id = :id')
                ->execute(['r' => $lockReason, 'by' => $admin['id'], 'id' => $targetId]);
            $this->logAudit($pdo, $admin, 'LOCK_USER', 'user', (string) $target['email'], "Khóa tài khoản {$target['full_name']}: {$lockReason}");
            $this->redirect("/admin?tab={$returnTab}", 'ok', "Đã khóa tài khoản {$target['full_name']}.");
        } else {
            $pdo->prepare("UPDATE users SET is_active = 1, lock_reason = '', locked_at = NULL, locked_by = NULL WHERE id = :id")
                ->execute(['id' => $targetId]);
            $this->logAudit($pdo, $admin, 'UNLOCK_USER', 'user', (string) $target['email'], "Mở khóa tài khoản {$target['full_name']}.");
            $this->redirect("/admin?tab={$returnTab}", 'ok', "Đã mở khóa tài khoản {$target['full_name']}.");
        }
    }

    public function createStaff(): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $role = ($_POST['role'] ?? 'receptionist') === 'admin' ? 'admin' : 'receptionist';
            $tier = trim((string) ($_POST['membership_tier'] ?? '')) ?: ($role === 'admin' ? 'Quản trị hệ thống' : 'Nhân viên Lễ tân');
            $password = (string) ($_POST['password'] ?? '123456');

            if ($fullName === '' || $email === '' || $phone === '' || strlen($password) < 6) {
                throw new \RuntimeException('Vui lòng nhập đầy đủ Họ tên, Email, SĐT và Mật khẩu (>= 6 ký tự).');
            }
            $phone = \App\Services\BookingWorkflowService::validatePhone10Digits($phone);

            $pdo = Database::connection();
            $chk = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = :e LIMIT 1');
            $chk->execute(['e' => $email]);
            if ($chk->fetch()) {
                throw new \RuntimeException("Email {$email} đã tồn tại.");
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare(
                'INSERT INTO users (full_name, email, phone, address, password_hash, role, is_active, membership_tier)
                 VALUES (:n, :e, :p, :a, :h, :r, 1, :t)'
            )->execute([
                'n' => $fullName, 'e' => $email, 'p' => $phone,
                'a' => 'Mường Thanh Luxury Phú Quốc', 'h' => $hash, 'r' => $role, 't' => $tier,
            ]);
            $this->logAudit($pdo, $admin, 'CREATE_STAFF', 'user', $email, "Tạo tài khoản nhân sự {$fullName} ({$role}).");
            $this->redirect('/admin?tab=staff', 'ok', "Đã tạo tài khoản nhân sự {$fullName}.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=staff', 'err', $e->getMessage());
        }
    }

    public function updateUserRole(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $targetId = (int) $id;
        if ($targetId === (int) $admin['id']) {
            $this->redirect('/admin?tab=staff', 'err', 'Không thể tự thay đổi quyền của chính mình.');
        }
        $role = ($_POST['role'] ?? 'receptionist') === 'admin' ? 'admin' : 'receptionist';
        $pdo = Database::connection();
        $pdo->prepare('UPDATE users SET role = :r WHERE id = :id')->execute(['r' => $role, 'id' => $targetId]);
        $this->logAudit($pdo, $admin, 'UPDATE_STAFF_ROLE', 'user', (string) $targetId, "Chuyển vai trò nhân sự #$targetId thành {$role}.");
        $this->redirect('/admin?tab=staff', 'ok', "Đã cập nhật quyền nhân sự #$targetId.");
    }

    public function savePoliciesBulk(): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $updates = $_POST['updates'] ?? [];
        if (!is_array($updates) || empty($updates)) {
            $this->redirect('/admin?tab=policies', 'err', 'Không có thay đổi chính sách nào.');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE hotel_policies SET policy_value = :v WHERE policy_key = :k');
        foreach ($updates as $k => $v) {
            $stmt->execute(['v' => trim((string) $v), 'k' => (string) $k]);
        }
        $this->logAudit($pdo, $admin, 'UPDATE_POLICIES', 'policy', 'bulk', 'Cập nhật cấu hình chính sách khách sạn & thanh toán VietQR.');
        $this->redirect('/admin?tab=policies', 'ok', 'Đã lưu toàn bộ chính sách & nội dung đồng bộ ra Trang chủ.');
    }

    public function broadcastNotification(): void
    {
        $admin = $this->authorizeWeb(['admin']);
        $title = trim((string) ($_POST['title'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($title === '' || $message === '') {
            $this->redirect('/admin?tab=policies', 'err', 'Vui lòng nhập tiêu đề và nội dung thông báo.');
        }
        $pdo = Database::connection();
        $workflow = new BookingWorkflowService();
        $custRows = $pdo->query("SELECT id FROM users WHERE role = 'customer' AND is_active = 1")->fetchAll();
        foreach ($custRows as $cr) {
            $workflow->createNotification($pdo, (int) $cr['id'], $title, $message, 'promo');
        }
        $this->logAudit($pdo, $admin, 'BROADCAST_NOTIFICATION', 'notification', 'all_customers', "Phát thông báo '{$title}' tới " . count($custRows) . ' Hội viên.');
        $this->redirect('/admin?tab=policies', 'ok', 'Đã gửi thông báo tới ' . count($custRows) . ' Hội viên.');
    }

    public function decideRefund(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $workflow = new BookingWorkflowService();
            $decision = ($_POST['decision'] ?? '') === 'rejected' ? 'rejected' : 'approved';
            $res = $workflow->decideRefundRequest(
                $admin,
                (int) $id,
                $decision,
                (string) ($_POST['decision_note'] ?? ''),
                (string) ($_POST['refund_method'] ?? 'bank_transfer')
            );
            $code = (string) ($res['refund_code'] ?? "#{$id}");
            $msg = $decision === 'approved'
                ? "Đã phê duyệt hồ sơ hoàn tiền {$code}. Kế toán/Lễ tân có thể tiến hành giải ngân."
                : "Đã từ chối hồ sơ hoàn tiền {$code}.";
            $this->redirect('/admin?tab=payments', 'ok', $msg);
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=payments', 'err', $e->getMessage());
        }
    }

    public function processRefund(string $id): void
    {
        $admin = $this->authorizeWeb(['admin']);
        try {
            $workflow = new BookingWorkflowService();
            $res = $workflow->processRefundPayout(
                $admin,
                (int) $id,
                (string) ($_POST['refund_method'] ?? 'bank_transfer'),
                (string) ($_POST['reference_no'] ?? ''),
                (string) ($_POST['decision_note'] ?? '')
            );
            $code = (string) ($res['refund_code'] ?? "#{$id}");
            $this->redirect('/admin?tab=payments', 'ok', "Đã xác nhận giải ngân hoàn tiền {$code}.");
        } catch (Throwable $e) {
            $this->redirect('/admin?tab=payments', 'err', $e->getMessage());
        }
    }

    private function logAudit(PDO $pdo, array $actor, string $action, string $entityType, string $entityId, string $details): void
    {
        $pdo->prepare(
            'INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details)
             VALUES (:uid, :name, :role, :act, :et, :eid, :det)'
        )->execute([
            'uid' => $actor['id'],
            'name' => $actor['full_name'],
            'role' => $actor['role'],
            'act' => $action,
            'et' => $entityType,
            'eid' => $entityId,
            'det' => $details,
        ]);
    }
}
