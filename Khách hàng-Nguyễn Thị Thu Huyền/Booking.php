<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Booking extends Model
{
    protected string $table = 'bookings';

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bookings WHERE UPPER(booking_code) = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($code))]);
        return $stmt->fetch() ?: null;
    }

    public function countOverlappingRooms(int $categoryId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): int
    {
        $sql = "SELECT COUNT(*) FROM bookings
                WHERE category_id = :cat_id
                  AND status IN ('pending', 'confirmed', 'checked_in')
                  AND check_in_date < :check_out
                  AND check_out_date > :check_in";
        $params = ['cat_id' => $categoryId, 'check_in' => $checkIn, 'check_out' => $checkOut];
        if ($excludeBookingId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeBookingId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
