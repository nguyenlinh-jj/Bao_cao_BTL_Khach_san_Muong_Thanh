<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\CatalogRepository;
use App\Models\RoomCategory;

class HomeController extends Controller
{
    public function index(): void
    {
        $today = date('Y-m-d');
        $rawIn = trim((string) ($_GET['check_in'] ?? ''));
        $rawOut = trim((string) ($_GET['check_out'] ?? ''));
        $checkIn = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawIn) && $rawIn >= $today)
            ? $rawIn
            : date('Y-m-d', strtotime('+1 day'));
        $checkOut = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawOut) && $rawOut > $checkIn)
            ? $rawOut
            : date('Y-m-d', strtotime($checkIn . ' +2 days'));

        $catModel = new RoomCategory();
        $categories = $catModel->getActiveWithAvailability($checkIn, $checkOut);
        $featured = array_values(array_filter($categories, fn($c) => (int) $c['is_featured'] === 1));
        usort($featured, function ($a, $b) {
            $oa = (int) ($a['featured_order'] ?? 0) ?: 999;
            $ob = (int) ($b['featured_order'] ?? 0) ?: 999;
            return $oa <=> $ob;
        });
        $featured = array_slice($featured, 0, 3);

        $this->render('home/index', [
            'pageTitle'       => 'Mường Thanh Luxury Phú Quốc — Khách sạn 5 sao bên bờ biển Bãi Trường',
            'featured'        => $featured ?: array_slice($categories, 0, 3),
            'categoriesCount' => count($categories),
            'checkIn'         => $checkIn,
            'checkOut'        => $checkOut,
        ]);
    }

    public function rooms(): void
    {
        $today = date('Y-m-d');
        $rawIn = trim((string) ($_GET['check_in'] ?? ''));
        $rawOut = trim((string) ($_GET['check_out'] ?? ''));
        $checkIn = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawIn) && $rawIn >= $today)
            ? $rawIn
            : date('Y-m-d', strtotime('+1 day'));
        $checkOut = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawOut) && $rawOut > $checkIn)
            ? $rawOut
            : date('Y-m-d', strtotime($checkIn . ' +2 days'));
        $adults = max(1, (int) ($_GET['adults'] ?? 2));
        $children = max(0, (int) ($_GET['children'] ?? 0));
        $roomsCount = 1;
        $searchFilterActive = (($_GET['filter'] ?? '') === 'search');
        $preselectedCategoryId = max(0, (int) ($_GET['category_id'] ?? 0));

        $catModel = new RoomCategory();
        $repo = new CatalogRepository();

        $allCategories = $catModel->getActiveWithAvailability($checkIn, $checkOut);
        $displayedCategories = $allCategories;
        if ($searchFilterActive) {
            $displayedCategories = array_values(array_filter($allCategories, function ($c) use ($adults, $children, $roomsCount) {
                $totalCapPerRoom = (int) $c['max_adults'] + (int) ($c['max_children'] ?? 1);
                $fitsAdults = ((int) $c['max_adults'] * $roomsCount) >= $adults;
                $fitsTotal = ($totalCapPerRoom * $roomsCount) >= ($adults + $children);
                $enoughRooms = (int) $c['available_count'] >= $roomsCount && empty($c['is_stop_sell']);
                return $fitsAdults && $fitsTotal && $enoughRooms;
            }));
        }

        $this->render('rooms/index', [
            'pageTitle'             => 'Phòng & Suite — Đặt Phòng Mường Thanh Luxury Phú Quốc',
            'categories'            => $displayedCategories,
            'categoriesCount'       => count($allCategories),
            'allCategoriesCount'    => count($allCategories),
            'searchFilterActive'    => $searchFilterActive,
            'preselectedCategoryId' => $preselectedCategoryId,
            'packages'              => $repo->getPackages(),
            'services'              => $repo->getServices(),
            'vouchers'              => $repo->getVouchers(true),
            'policies'              => $repo->getPolicyMap(),
            'checkIn'               => $checkIn,
            'checkOut'              => $checkOut,
            'adults'                => $adults,
            'children'              => $children,
            'roomsCount'            => $roomsCount,
        ]);
    }

    public function dining(): void
    {
        $repo = new CatalogRepository();
        $catCount = (int) Database::connection()->query('SELECT COUNT(*) FROM room_categories WHERE is_active = 1')->fetchColumn();
        $this->render('dining/index', [
            'pageTitle'       => 'Ẩm Thực — Nhà Hàng Sonasea & Rooftop Sky Lounge',
            'services'        => $repo->getServices(),
            'categoriesCount' => $catCount,
        ]);
    }

    public function spa(): void
    {
        $repo = new CatalogRepository();
        $catCount = (int) Database::connection()->query('SELECT COUNT(*) FROM room_categories WHERE is_active = 1')->fetchColumn();
        $this->render('spa/index', [
            'pageTitle'       => 'Pearl Spa & Chăm Sóc Sức Khỏe',
            'services'        => array_filter($repo->getServices(), fn($s) => $s['category'] === 'spa'),
            'categoriesCount' => $catCount,
        ]);
    }

    public function conferences(): void
    {
        $catCount = (int) Database::connection()->query('SELECT COUNT(*) FROM room_categories WHERE is_active = 1')->fetchColumn();
        $this->render('conferences/index', [
            'pageTitle'       => 'Hội Nghị & Sự Kiện — Grand Ballroom',
            'categoriesCount' => $catCount,
        ]);
    }

    public function saveConferenceInquiry(): void
    {
        $contactName = trim((string) ($_POST['contact_name'] ?? ''));
        $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        if ($contactName === '' || $contactPhone === '') {
            $this->redirect('/conferences', 'err', 'Vui lòng nhập đầy đủ tên người liên hệ và số điện thoại.');
        }
        $today = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate) || $eventDate < $today) {
            $this->redirect('/conferences', 'err', 'Ngày tổ chức sự kiện không hợp lệ hoặc đã qua. Vui lòng chọn từ hôm nay trở đi.');
        }

        Database::connection()->prepare(
            'INSERT INTO conference_inquiries (venue_name, contact_name, contact_phone, contact_email, event_date, guest_count, notes)
             VALUES (:v, :n, :p, :e, :d, :g, :no)'
        )->execute([
            'v'  => trim((string) ($_POST['venue_name'] ?? '')) ?: 'Sảnh Ngọc Trai',
            'n'  => $contactName,
            'p'  => $contactPhone,
            'e'  => trim((string) ($_POST['contact_email'] ?? '')),
            'd'  => $eventDate,
            'g'  => max(10, (int) ($_POST['guest_count'] ?? 50)),
            'no' => trim((string) ($_POST['notes'] ?? '')),
        ]);
        $this->redirect('/conferences', 'ok', 'Đã tiếp nhận yêu cầu đặt sảnh hội nghị! Lễ tân & Bộ phận Sự kiện đã nhận thông tin và sẽ chủ động liên hệ tư vấn qua Điện thoại / Email trong 15–30 phút (tối đa 2 giờ). Hotline hỗ trợ gấp: 1900 1833.');
    }

    public function lookup(): void
    {
        $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
        $phone = trim((string) ($_GET['phone'] ?? ''));
        $booking = null;
        $repo = new CatalogRepository();

        if ($code !== '') {
            $stmt = Database::connection()->prepare('SELECT id, guest_phone FROM bookings WHERE UPPER(booking_code) = :c LIMIT 1');
            $stmt->execute(['c' => $code]);
            $row = $stmt->fetch();
            if ($row && str_replace(' ', '', $row['guest_phone']) === str_replace(' ', '', $phone)) {
                $booking = $repo->getBookingDetailsById((int) $row['id']);
            }
        }

        $catCount = (int) Database::connection()->query('SELECT COUNT(*) FROM room_categories WHERE is_active = 1')->fetchColumn();
        $this->render('customer/portal', [
            'pageTitle'       => 'Tra Cứu Đặt Phòng & Hóa Đơn',
            'mode'            => 'lookup',
            'bookings'        => $booking ? [$booking] : [],
            'services'        => $repo->getServices(),
            'codeQuery'       => $code,
            'phoneQuery'      => $phone,
            'categoriesCount' => $catCount,
        ]);
    }
}
