<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class CatalogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public static function maskIdNumber(?string $raw): string
    {
        $clean = trim((string) $raw);
        if ($clean === '') {
            return '';
        }
        $len = strlen($clean);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }
        if ($len <= 8) {
            return substr($clean, 0, 2) . str_repeat('•', $len - 4) . substr($clean, -2);
        }
        return substr($clean, 0, 4) . str_repeat('•', $len - 8) . substr($clean, -4);
    }

    public function evaluateServicePricing(array $srv, ?string $refDate = null): array
    {
        $today = $refDate ?: date('Y-m-d');
        $price = (float) ($srv['price'] ?? 0);
        $promoPrice = isset($srv['promo_price']) && $srv['promo_price'] !== null ? (float) $srv['promo_price'] : null;
        $pStart = !empty($srv['promo_start_date']) ? (string) $srv['promo_start_date'] : '';
        $pEnd = !empty($srv['promo_end_date']) ? (string) $srv['promo_end_date'] : '';

        $hasPromo = $promoPrice !== null && $promoPrice > 0 && $promoPrice < $price;
        $inWindow = (!$pStart || $today >= $pStart) && (!$pEnd || $today <= $pEnd);
        $promoExpired = $hasPromo && $pEnd !== '' && $today > $pEnd;
        $hasActivePromo = $hasPromo && $inWindow;

        return [
            'effective_price'   => $hasActivePromo ? $promoPrice : $price,
            'has_active_promo'  => $hasActivePromo,
            'promo_expired'     => $promoExpired,
            'active_promo_tag'  => $hasActivePromo ? (string) ($srv['promo_tag'] ?? '') : '',
        ];
    }

    public function getPackages(): array
    {
        $rows = $this->db->query('SELECT * FROM rate_packages WHERE is_active = 1 ORDER BY extra_per_night ASC')->fetchAll();
        foreach ($rows as &$r) {
            $r['inclusions'] = isset($r['inclusions_json']) ? (json_decode((string) $r['inclusions_json'], true) ?: []) : [];
        }
        return $rows;
    }

    public function getServices(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM services';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY id ASC';
        $rows = $this->db->query($sql)->fetchAll();
        foreach ($rows as &$r) {
            $eval = $this->evaluateServicePricing($r);
            $r['effective_price'] = $eval['effective_price'];
            $r['has_active_promo'] = $eval['has_active_promo'];
            $r['promo_expired'] = $eval['promo_expired'];
            $r['active_promo_tag'] = $eval['active_promo_tag'];
        }
        return $rows;
    }

    public function getVouchers(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM vouchers';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY id ASC';
        return $this->db->query($sql)->fetchAll();
    }

    public function getAllVouchersWithUsage(): array
    {
        return $this->db->query(
            'SELECT v.*, (SELECT COUNT(*) FROM voucher_usages vu WHERE vu.voucher_id = v.id) AS used_count
             FROM vouchers v ORDER BY v.id DESC'
        )->fetchAll();
    }

    public function getPolicies(): array
    {
        return $this->db->query('SELECT * FROM hotel_policies')->fetchAll();
    }

    public function getPolicyMap(): array
    {
        $map = [];
        foreach ($this->getPolicies() as $p) {
            $map[$p['policy_key']] = (string) $p['policy_value'];
        }
        return $map;
    }

    public function getHotelPolicyValue(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT policy_value FROM hotel_policies WHERE policy_key = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string) $val : $default;
    }

    public function getNotificationsForUser(int $userId, int $limit = 40): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getUnreadNotificationsCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getBookingDetailsById(int $bookingId, bool $maskIdForAdmin = false): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, rc.name AS category_name, rc.subtitle AS category_subtitle, rc.image_url AS category_image,
                    rc.bed_label AS category_bed_label, rc.area_sqm AS category_area_sqm, rc.view_label AS category_view_label,
                    rc.max_adults AS category_max_adults, rc.max_children AS category_max_children,
                    rp.name AS package_name, r.room_number AS assigned_room_number,
                    u.address AS customer_address,
                    s.id AS stay_id, s.actual_check_in_at, s.actual_check_out_at
             FROM bookings b
             JOIN room_categories rc ON b.category_id = rc.id
             JOIN rate_packages rp ON b.package_id = rp.id
             LEFT JOIN rooms r ON b.assigned_room_id = r.id
             LEFT JOIN users u ON b.customer_id = u.id
             LEFT JOIN stays s ON s.booking_id = b.id
             WHERE b.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $bookingId]);
        $b = $stmt->fetch();
        if (!$b) {
            return null;
        }

        $b['masked_id_number'] = self::maskIdNumber($b['guest_id_number'] ?? '');
        if ($maskIdForAdmin) {
            $b['guest_id_number'] = $b['masked_id_number'];
        }

        $b['selected_items'] = !empty($b['selected_items_json'])
            ? (json_decode((string) $b['selected_items_json'], true) ?: [])
            : [];

        $soStmt = $this->db->prepare(
            'SELECT so.*, sv.category AS service_category,
                    ua.full_name AS accepted_by_name,
                    uc.full_name AS completed_by_name
             FROM service_orders so
             LEFT JOIN services sv ON so.service_id = sv.id
             LEFT JOIN users ua ON so.accepted_by = ua.id
             LEFT JOIN users uc ON so.completed_by = uc.id
             WHERE so.booking_id = :bid
             ORDER BY so.created_at DESC, so.id DESC'
        );
        $soStmt->execute(['bid' => $bookingId]);
        $b['service_orders'] = $soStmt->fetchAll();

        $completedGross = 0.0;
        $serviceDiscount = 0.0;
        foreach ($b['service_orders'] as $so) {
            if ($so['status'] === 'completed' && empty($so['is_prebooked'])) {
                $completedGross += (float) $so['total_price'];
                $serviceDiscount += (float) ($so['discount_amount'] ?? 0);
            }
        }
        $b['service_discount_total'] = $serviceDiscount;
        $b['completed_services_total'] = max(0.0, $completedGross - $serviceDiscount);

        $payStmt = $this->db->prepare('SELECT * FROM payments WHERE booking_id = :bid ORDER BY recorded_at DESC');
        $payStmt->execute(['bid' => $bookingId]);
        $b['payments'] = $payStmt->fetchAll();

        $invStmt = $this->db->prepare('SELECT * FROM invoices WHERE booking_id = :bid LIMIT 1');
        $invStmt->execute(['bid' => $bookingId]);
        $b['invoice'] = $invStmt->fetch() ?: null;

        $revStmt = $this->db->prepare('SELECT * FROM reviews WHERE booking_id = :bid LIMIT 1');
        $revStmt->execute(['bid' => $bookingId]);
        $b['review'] = $revStmt->fetch() ?: null;

        $paidSumStmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'verified'");
        $paidSumStmt->execute(['bid' => $bookingId]);
        $b['total_paid'] = (float) $paidSumStmt->fetchColumn();

        $pendingSumStmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid AND verification_status = 'pending'");
        $pendingSumStmt->execute(['bid' => $bookingId]);
        $b['total_pending_paid'] = (float) $pendingSumStmt->fetchColumn();

        $b['refund'] = (new Refund())->findByBookingId($bookingId);

        $histStmt = $this->db->prepare(
            "SELECT * FROM audit_logs
             WHERE entity_type IN ('booking', 'stay') AND entity_id = :code
             ORDER BY id DESC LIMIT 30"
        );
        $histStmt->execute(['code' => (string) $b['booking_code']]);
        $b['booking_history'] = $histStmt->fetchAll();

        $today = date('Y-m-d');
        $minChangeDays = max(0, (int) $this->getHotelPolicyValue('min_change_days', '3'));
        $daysUntilCheckin = (int) floor((strtotime((string) $b['check_in_date']) - strtotime($today)) / 86400);
        $isCheckinOverdue = in_array($b['status'], ['pending', 'confirmed'], true) && $b['check_in_date'] < $today;
        $isCheckoutOverdue = $b['status'] === 'checked_in' && $b['check_out_date'] < $today;
        $isOverdue = $isCheckinOverdue || $isCheckoutOverdue;

        $b['is_overdue'] = $isOverdue;
        $b['overdue_type'] = $isCheckoutOverdue ? 'checkout_overdue' : ($isCheckinOverdue ? 'checkin_overdue' : null);
        $b['days_until_checkin'] = $daysUntilCheckin;
        $b['min_change_days'] = $minChangeDays;
        $b['can_change_date'] = in_array($b['status'], ['pending', 'confirmed'], true)
            && !$isOverdue
            && $daysUntilCheckin >= $minChangeDays;

        return $b;
    }
}
