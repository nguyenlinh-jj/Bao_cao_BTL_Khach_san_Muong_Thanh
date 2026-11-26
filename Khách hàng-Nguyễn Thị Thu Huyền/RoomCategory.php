<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class RoomCategory extends Model
{
    protected string $table = 'room_categories';

    public function evaluateCategoryPricing(array $cat, ?string $checkIn = null, ?string $checkOut = null): array
    {
        $today = date('Y-m-d');
        $refStart = $checkIn ?: $today;
        $refEnd = $checkOut ?: date('Y-m-d', strtotime($refStart . ' +1 day'));
        $basePrice = (float) ($cat['base_price'] ?? 0);

        $stmt = $this->db->prepare(
            'SELECT * FROM room_rate_overrides
             WHERE category_id = :cid AND is_active = 1
               AND start_date < :ref_end AND end_date >= :ref_start
             ORDER BY is_stop_sell DESC, id DESC'
        );
        $stmt->execute([
            'cid'       => (int) $cat['id'],
            'ref_end'   => $refEnd,
            'ref_start' => $refStart,
        ]);
        $overrides = $stmt->fetchAll();

        $stopSell = null;
        $rateOverride = null;
        foreach ($overrides as $ov) {
            if ((int) ($ov['is_stop_sell'] ?? 0) === 1 && $stopSell === null) {
                $stopSell = $ov;
            }
            if ($ov['override_price'] !== null && (float) $ov['override_price'] > 0 && $rateOverride === null) {
                $rateOverride = $ov;
            }
        }

        if ($stopSell !== null) {
            return [
                'effective_base_price' => $rateOverride ? (float) $rateOverride['override_price'] : $basePrice,
                'has_active_promo'     => false,
                'active_promo_label'   => '',
                'promo_expired'        => false,
                'is_stop_sell'         => true,
                'stop_sell_note'       => (string) ($stopSell['note'] ?: 'Tạm đóng bán theo cấu hình quản trị'),
                'override_applied'     => $rateOverride !== null,
            ];
        }

        if ($rateOverride !== null) {
            return [
                'effective_base_price' => (float) $rateOverride['override_price'],
                'has_active_promo'     => false,
                'active_promo_label'   => (string) ($rateOverride['note'] ?? ''),
                'promo_expired'        => false,
                'is_stop_sell'         => false,
                'stop_sell_note'       => '',
                'override_applied'     => true,
            ];
        }

        $promoType = (string) ($cat['promo_discount_type'] ?? 'none');
        $promoValue = (float) ($cat['promo_discount_value'] ?? 0);
        $pStart = !empty($cat['promo_start_date']) ? (string) $cat['promo_start_date'] : '';
        $pEnd = !empty($cat['promo_end_date']) ? (string) $cat['promo_end_date'] : '';

        $hasPromoConfig = $promoType !== 'none' && $promoValue > 0;
        $inWindow = (!$pStart || $refStart >= $pStart) && (!$pEnd || $refStart <= $pEnd);
        $promoExpired = $hasPromoConfig && $pEnd !== '' && $refStart > $pEnd;

        $effectivePrice = $basePrice;
        $hasActivePromo = false;
        $activeLabel = '';

        if ($hasPromoConfig && $inWindow) {
            $hasActivePromo = true;
            if ($promoType === 'percent') {
                $effectivePrice = max(100000, round($basePrice * (1 - min(90, $promoValue) / 100)));
                $activeLabel = !empty($cat['promo_label']) ? (string) $cat['promo_label'] : ('-' . (int) $promoValue . '%');
            } else {
                $effectivePrice = max(100000, $basePrice - $promoValue);
                $activeLabel = !empty($cat['promo_label'])
                    ? (string) $cat['promo_label']
                    : ('-' . number_format($promoValue, 0, ',', '.') . ' đ');
            }
        }

        return [
            'effective_base_price' => $effectivePrice,
            'has_active_promo'     => $hasActivePromo,
            'active_promo_label'   => $activeLabel,
            'promo_expired'        => $promoExpired,
            'is_stop_sell'         => false,
            'stop_sell_note'       => '',
            'override_applied'     => false,
        ];
    }

    public function resolvePackagesForCategory(array $cat, array $basePackages, float $effectivePrice): array
    {
        $config = [];
        if (!empty($cat['package_config_json'])) {
            $decoded = json_decode((string) $cat['package_config_json'], true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        $result = [];
        foreach ($basePackages as $pkg) {
            $code = (string) ($pkg['code'] ?? '');
            $override = $config[$code] ?? ($config[(string) $pkg['id']] ?? null);
            $enabled = true;
            if (is_array($override) && array_key_exists('enabled', $override)) {
                $enabled = (bool) $override['enabled'];
            }
            if (!$enabled) {
                continue;
            }
            $defaultPrice = $effectivePrice + (float) ($pkg['extra_per_night'] ?? 0);
            $overridePriceVal = is_array($override) ? (float) ($override['price'] ?? ($override['custom_price'] ?? 0)) : 0.0;
            $customNightly = $overridePriceVal > 0 ? $overridePriceVal : $defaultPrice;
            $pkg['resolved_nightly_price'] = $customNightly;
            $pkg['resolved_extra_per_night'] = max(0.0, $customNightly - $effectivePrice);
            $result[] = $pkg;
        }
        return !empty($result) ? $result : $basePackages;
    }

    public function getActiveWithAvailability(string $checkIn, string $checkOut): array
    {
        $categories = $this->db->query(
            'SELECT * FROM room_categories WHERE is_active = 1 ORDER BY is_featured DESC, CASE WHEN featured_order > 0 THEN featured_order ELSE 999 END ASC, base_price ASC'
        )->fetchAll();
        $bookingModel = new Booking();
        $repo = new CatalogRepository();
        $basePackages = $repo->getPackages();

        foreach ($categories as &$cat) {
            $cat['amenities'] = json_decode((string) $cat['amenities_json'], true) ?: [];
            $gallery = isset($cat['gallery_json']) ? (json_decode((string) $cat['gallery_json'], true) ?: []) : [];
            if (empty($gallery)) {
                $gallery = [$cat['image_url']];
            }
            $cat['gallery'] = $gallery;

            $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM rooms WHERE category_id = :cid AND status NOT IN ('maintenance', 'out_of_order')");
            $stmtTotal->execute(['cid' => $cat['id']]);
            $totalOp = (int) $stmtTotal->fetchColumn();
            $booked = $bookingModel->countOverlappingRooms((int) $cat['id'], $checkIn, $checkOut);
            $avail = max(0, $totalOp - $booked);

            $eval = $this->evaluateCategoryPricing($cat, $checkIn, $checkOut);
            $cat['effective_base_price'] = $eval['effective_base_price'];
            $cat['has_active_promo'] = $eval['has_active_promo'];
            $cat['active_promo_label'] = $eval['active_promo_label'];
            $cat['promo_expired'] = $eval['promo_expired'];
            $cat['is_stop_sell'] = $eval['is_stop_sell'];
            $cat['stop_sell_note'] = $eval['stop_sell_note'];
            $cat['available_count'] = $eval['is_stop_sell'] ? 0 : $avail;
            $cat['resolved_packages'] = $this->resolvePackagesForCategory($cat, $basePackages, $eval['effective_base_price']);
        }
        return $categories;
    }
}
