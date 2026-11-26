<?php
/**
 *  NGƯỜI PHỤ TRÁCH: Nguyễn Thị Thu Huyền
 *    Trang công khai và luồng đặt phòng của khách: trang chủ, danh sách phòng, nhà hàng, spa, tra cứu đơn
 *  CÁC HÀM TRONG TỆP NÀY:
 *    - index() 
 *    - rooms()  
 *    - dining()   
 *    - spa()   
 *    - lookup() 
 */
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
