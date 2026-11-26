<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Refund extends Model
{
    protected string $table = 'booking_refunds';

    public static function ensureTable(PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS booking_refunds (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    refund_code VARCHAR(30) NOT NULL UNIQUE,
                    booking_id INT NOT NULL,
                    customer_id INT NULL,
                    cancel_reason VARCHAR(255) NOT NULL DEFAULT '',
                    paid_verified_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
                    retained_fee DECIMAL(12,0) NOT NULL DEFAULT 0,
                    refund_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
                    status ENUM('pending', 'approved', 'rejected', 'processed') NOT NULL DEFAULT 'pending',
                    refund_method ENUM('bank_transfer', 'cash', 'card', 'qr_bank') NOT NULL DEFAULT 'bank_transfer',
                    reference_no VARCHAR(80) NOT NULL DEFAULT '',
                    decision_note VARCHAR(255) NOT NULL DEFAULT '',
                    requested_by INT NULL,
                    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    approved_by INT NULL,
                    decided_at DATETIME NULL,
                    processed_by INT NULL,
                    processed_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_refund_booking (booking_id),
                    INDEX idx_refund_status (status)
                ) ENGINE=InnoDB"
            );
        } catch (\Throwable $e) {
            // ignore if read-only or non-MySQL check
        }
        $ensured = true;
    }

    public function findByBookingId(int $bookingId): ?array
    {
        self::ensureTable($this->db);
        $stmt = $this->db->prepare(
            "SELECT r.*,
                    ur.full_name AS requested_by_name,
                    ua.full_name AS approved_by_name,
                    up.full_name AS processed_by_name
             FROM booking_refunds r
             LEFT JOIN users ur ON r.requested_by = ur.id
             LEFT JOIN users ua ON r.approved_by = ua.id
             LEFT JOIN users up ON r.processed_by = up.id
             WHERE r.booking_id = :bid
             LIMIT 1"
        );
        $stmt->execute(['bid' => $bookingId]);
        return $stmt->fetch() ?: null;
    }

    public function findDetailed(int $refundId): ?array
    {
        self::ensureTable($this->db);
        $stmt = $this->db->prepare(
            "SELECT r.*,
                    b.booking_code, b.guest_name, b.guest_phone, b.guest_email, b.status AS booking_status,
                    b.check_in_date, b.check_out_date, b.total_estimated, b.payment_method,
                    rc.name AS category_name,
                    ur.full_name AS requested_by_name,
                    ua.full_name AS approved_by_name,
                    up.full_name AS processed_by_name
             FROM booking_refunds r
             JOIN bookings b ON r.booking_id = b.id
             LEFT JOIN room_categories rc ON b.category_id = rc.id
             LEFT JOIN users ur ON r.requested_by = ur.id
             LEFT JOIN users ua ON r.approved_by = ua.id
             LEFT JOIN users up ON r.processed_by = up.id
             WHERE r.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $refundId]);
        return $stmt->fetch() ?: null;
    }

    public function listAllDetailed(int $limit = 120): array
    {
        self::ensureTable($this->db);
        $lim = max(1, min(500, $limit));
        $stmt = $this->db->query(
            "SELECT r.*,
                    b.booking_code, b.guest_name, b.guest_phone, b.guest_email, b.status AS booking_status,
                    b.check_in_date, b.check_out_date, b.total_estimated, b.payment_method,
                    rc.name AS category_name,
                    ur.full_name AS requested_by_name,
                    ua.full_name AS approved_by_name,
                    up.full_name AS processed_by_name
             FROM booking_refunds r
             JOIN bookings b ON r.booking_id = b.id
             LEFT JOIN room_categories rc ON b.category_id = rc.id
             LEFT JOIN users ur ON r.requested_by = ur.id
             LEFT JOIN users ua ON r.approved_by = ua.id
             LEFT JOIN users up ON r.processed_by = up.id
             ORDER BY FIELD(r.status, 'pending', 'approved', 'processed', 'rejected'), r.requested_at DESC, r.id DESC
             LIMIT {$lim}"
        );
        return $stmt->fetchAll();
    }
}
