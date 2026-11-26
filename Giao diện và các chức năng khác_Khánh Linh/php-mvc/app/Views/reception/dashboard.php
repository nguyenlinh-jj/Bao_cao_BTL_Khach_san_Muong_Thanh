<?php
$tab = (string) ($activeTab ?? 'dashboard');
$todayStr = (string) ($today ?? date('Y-m-d'));
$rackDateStr = (string) ($rackDate ?? $todayStr);
$esc = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmt = fn($n) => number_format((float) $n, 0, ',', '.') . ' đ';

$bookingsList = $bookings ?? [];
$roomsList = $rooms ?? [];
$serviceOrdersList = $serviceOrders ?? [];
$housekeepingList = $housekeeping ?? [];
$incidentsList = $incidents ?? [];
$pendingPaymentsList = $pendingPayments ?? [];
$refundRequestsList = $refundRequests ?? [];
$customersList = $customers ?? [];
$conferencesList = $conferenceInquiries ?? [];
$categoriesList = $categories ?? [];
$packagesList = $packages ?? [];
$servicesList = $services ?? [];
$vouchersList = $vouchers ?? [];
$policiesMap = $policies ?? [];
$notifsList = $notifications ?? [];
$unreadNotifCount = (int) ($unreadCount ?? 0);

// Helper matching receptionUtils::computeBookingFolioGrandTotal
$computeFolioGrandTotal = function (array $b): float {
    if (!empty($b['invoice']) && isset($b['invoice']['total_amount'])) {
        return (float) $b['invoice']['total_amount'];
    }
    $completedServicesGross = 0.0;
    if (!empty($b['service_orders']) && is_array($b['service_orders'])) {
        foreach ($b['service_orders'] as $so) {
            $isPre = !empty($so['is_prebooked']);
            if ($so['status'] === 'completed' || ($isPre && $so['status'] !== 'cancelled')) {
                $completedServicesGross += (float) ($so['total_price'] ?? 0);
            }
        }
    }
    $serviceDiscount = (float) ($b['service_discount_total'] ?? 0);
    $serviceNet = max(0.0, $completedServicesGross - $serviceDiscount);
    $roomSub = (float) ($b['room_subtotal'] ?? 0);
    $extFee = (float) ($b['extension_fee'] ?? 0);
    $roomDiscount = (float) ($b['discount_amount'] ?? 0);
    $taxRate = (float) ($b['locked_tax_rate'] ?? 13.4);
    $taxable = max(0.0, $roomSub + $extFee + $serviceNet - $roomDiscount);
    $tax = round($taxable * ($taxRate / 100));
    return $taxable + $tax;
};

// Compute stats matching ReceptionDashboardPage.tsx lines 441-474
$arrivingTodayCount = 0;
$departingTodayCount = 0;
$activeStaysCount = 0;
$pendingBookingsCount = 0;
$changeRequestsCount = 0;
$newTodayCount = 0;
$cancelledTodayCount = 0;
$changeRequestsList = [];
$arrivalsDeparturesToday = [];

foreach ($bookingsList as $bk) {
    $st = (string) ($bk['status'] ?? 'pending');
    $cin = (string) ($bk['check_in_date'] ?? '');
    $cout = (string) ($bk['check_out_date'] ?? '');
    $createdDate = substr((string) ($bk['created_at'] ?? ''), 0, 10);

    if ($st === 'confirmed' && $cin === $todayStr) {
        $arrivingTodayCount++;
    }
    if ($st === 'checked_in' && $cout === $todayStr) {
        $departingTodayCount++;
    }
    if ($st === 'checked_in') {
        $activeStaysCount++;
    }
    if ($st === 'pending') {
        $pendingBookingsCount++;
    }
    if (($bk['change_request_status'] ?? 'none') !== 'none') {
        $changeRequestsCount++;
        $changeRequestsList[] = $bk;
    }
    if ($createdDate === $todayStr) {
        $newTodayCount++;
    }
    if ($st === 'cancelled' && substr((string) ($bk['updated_at'] ?? $bk['created_at'] ?? ''), 0, 10) === $todayStr) {
        $cancelledTodayCount++;
    }
    if (
        ($st === 'confirmed' && $cin <= $todayStr) ||
        ($st === 'checked_in' && $cout <= $todayStr) ||
        ($st === 'pending' && $cin === $todayStr)
    ) {
        $arrivalsDeparturesToday[] = $bk;
    }
}

$occupiedRoomsCount = count(array_filter($roomsList, fn($r) => ($r['status'] ?? '') === 'occupied'));
$availableRoomsCount = count(array_filter($roomsList, fn($r) => ($r['status'] ?? '') === 'available'));
$dirtyRoomsCount = count(array_filter($roomsList, fn($r) => ($r['status'] ?? '') === 'dirty' || ($r['clean_status'] ?? '') === 'dirty'));
$maintRoomsCount = count(array_filter($roomsList, fn($r) => ($r['status'] ?? '') === 'maintenance'));
$totalRoomsCount = count($roomsList);
$occupancyPct = $totalRoomsCount > 0 ? (int) round(($occupiedRoomsCount / $totalRoomsCount) * 100) : 0;

$pendingServiceOrdersCount = count(array_filter($serviceOrdersList, fn($s) => ($s['status'] ?? '') === 'pending'));
$acceptedServiceOrdersCount = count(array_filter($serviceOrdersList, fn($s) => ($s['status'] ?? '') === 'accepted'));
$pendingTransfersCount = count(array_filter($pendingPaymentsList, fn($p) => ($p['verification_status'] ?? 'pending') === 'pending'));
$pendingRefundsCount = count(array_filter($refundRequestsList, fn($rf) => in_array((string) ($rf['status'] ?? ''), ['pending', 'approved'], true)));
$openIncidentsCount = count(array_filter($incidentsList, fn($i) => ($i['status'] ?? '') === 'open'));
$newConferencesCount = count(array_filter($conferencesList, fn($c) => ($c['status'] ?? '') === 'new'));

$urgentCount = $pendingBookingsCount + $changeRequestsCount + $pendingServiceOrdersCount + $pendingTransfersCount + $pendingRefundsCount + $dirtyRoomsCount + $openIncidentsCount + $newConferencesCount;

// Room Rack helpers
$findRoomBookingOnDate = function (int $roomId, string $date) use ($bookingsList): ?array {
    foreach ($bookingsList as $b) {
        if (
            (int) ($b['assigned_room_id'] ?? 0) === $roomId &&
            in_array((string) ($b['status'] ?? ''), ['checked_in', 'confirmed'], true) &&
            (string) $b['check_in_date'] <= $date &&
            (string) $b['check_out_date'] >= $date
        ) {
            return $b;
        }
    }
    return null;
};

$rackPrevDate = date('Y-m-d', strtotime($rackDateStr . ' -1 day'));
$rackNextDate = date('Y-m-d', strtotime($rackDateStr . ' +1 day'));

$rackSummary = ['occupied' => 0, 'arriving' => 0, 'departing' => 0, 'cleanAvail' => 0, 'dirtyNeedClean' => 0];
$roomRackByFloor = [];
foreach ($roomsList as $rm) {
    $fl = (string) ($rm['floor'] ?? '1');
    if (!isset($roomRackByFloor[$fl])) {
        $roomRackByFloor[$fl] = [];
    }
    $roomRackByFloor[$fl][] = $rm;

    $bOnDate = $findRoomBookingOnDate((int) $rm['id'], $rackDateStr);
    if (($rm['status'] ?? '') === 'dirty' || ($rm['clean_status'] ?? '') === 'dirty') {
        $rackSummary['dirtyNeedClean']++;
    } elseif ($bOnDate && ($bOnDate['status'] ?? '') === 'checked_in' && ($bOnDate['check_out_date'] ?? '') === $rackDateStr) {
        $rackSummary['departing']++;
    } elseif ($bOnDate && ($bOnDate['status'] ?? '') === 'checked_in') {
        $rackSummary['occupied']++;
    } elseif ($bOnDate && ($bOnDate['status'] ?? '') === 'confirmed') {
        $rackSummary['arriving']++;
    } elseif (($rm['status'] ?? '') === 'available' && ($rm['clean_status'] ?? '') === 'clean') {
        $rackSummary['cleanAvail']++;
    }
}
ksort($roomRackByFloor, SORT_NUMERIC);

$bookingsGroupOpen = in_array($tab, ['bookings_list', 'room_rack', 'conferences'], true);

$statusBadgeMap = [
    'pending'     => ['Chờ xác nhận', 'bg-amber-50 text-amber-800 border-amber-200'],
    'confirmed'   => ['Đã xác nhận', 'bg-sky-50 text-sky-800 border-sky-200'],
    'checked_in'  => ['Đang ở', 'bg-emerald-50 text-emerald-800 border-emerald-200'],
    'checked_out' => ['Đã trả phòng', 'bg-slate-100 text-slate-700 border-slate-200'],
    'cancelled'   => ['Đã hủy', 'bg-rose-50 text-rose-700 border-rose-200'],
];
$payMethodLabels = [
    'cash'          => 'Tiền mặt tại quầy',
    'card'          => 'Thẻ / Máy POS',
    'qr_bank'       => 'Chuyển khoản QR',
    'bank_transfer' => 'Chuyển khoản NH',
    'qr_vnpay'      => 'Ví / QR VNPAY',
];

$jsonOpts = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$bootPayload = [
    'tab'             => $tab,
    'today'           => $todayStr,
    'rackDate'        => $rackDateStr,
    'bookings'        => $bookingsList,
    'rooms'           => $roomsList,
    'pendingPayments' => $pendingPaymentsList,
    'refundRequests'  => $refundRequestsList,
    'serviceOrders'   => $serviceOrdersList,
    'bankInfo'        => $bankInfo ?? [
        'bankName'      => (string) ($policiesMap['bank_name'] ?? 'BIDV - Ngân hàng TMCP Đầu tư và Phát triển Việt Nam'),
        'accountNo'     => (string) ($policiesMap['bank_account_no'] ?? '75010008889999'),
        'accountHolder' => (string) ($policiesMap['bank_account_holder'] ?? 'MUONG THANH LUXURY PHU QUOC'),
    ],
    'categories'      => $categoriesList,
    'packages'        => $packagesList,
    'services'        => $servicesList,
    'vouchers'        => $vouchersList,
    'policies'        => $policiesMap,
    'customers'       => $customersList,
];
?>
<div class="min-h-screen flex flex-col bg-[#FBF9F5] text-[#0E1D33]">
  <!-- TOPBAR LỄ TÂN -->
  <header class="sticky top-0 z-30 bg-[#0E1D33] text-white border-b border-white/10 h-14 px-4 lg:px-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 min-w-0">
      <a href="/" class="flex items-center gap-2 shrink-0">
        <span class="font-serif text-lg tracking-[0.18em] font-semibold text-[#E2C27D]">MƯỜNG THANH</span>
      </a>
      <span class="text-white/25">|</span>
      <span class="text-xs font-bold tracking-[0.18em] uppercase text-slate-200 truncate">
        LỄ TÂN · PMS CONSOLE
      </span>
    </div>

    <div class="flex items-center gap-2.5 text-xs">
      <!-- Notification Bell -->
      <div class="relative">
        <button type="button" onclick="toggleNotifPopover()"
                class="relative p-2 rounded-lg border border-white/15 hover:border-[#C9A45C] text-slate-200 transition-colors"
                title="Thông báo lễ tân">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <?php if ($unreadNotifCount > 0): ?>
            <span id="notifBadge" class="absolute -top-1.5 -right-1.5 px-1.5 py-0.2 rounded-full bg-rose-500 text-white text-[10px] font-bold leading-4 tabular-nums">
              <?= $unreadNotifCount ?>
            </span>
          <?php endif; ?>
        </button>

        <div id="notifPopover" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white text-[#0E1D33] rounded-2xl shadow-2xl border border-[#E6DFD1] overflow-hidden z-50">
          <div class="px-4 py-3 bg-[#FAF7F0] border-b border-[#E6DFD1] flex items-center justify-between">
            <span class="font-bold text-xs uppercase tracking-wider text-[#0E1D33]">Thông báo nội bộ (<?= count($notifsList) ?>)</span>
            <?php if ($unreadNotifCount > 0): ?>
              <button type="button" onclick="markAllNotifsRead()" class="text-[11px] font-semibold text-[#A8843E] hover:underline">
                Đánh dấu đã đọc
              </button>
            <?php endif; ?>
          </div>
          <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 text-xs">
            <?php if (empty($notifsList)): ?>
              <div class="p-6 text-center text-slate-400">Chưa có thông báo ca trực mới.</div>
            <?php else: ?>
              <?php foreach (array_slice($notifsList, 0, 12) as $nf): ?>
                <div class="p-3.5 <?= empty($nf['is_read']) ? 'bg-amber-50/40' : '' ?>">
                  <div class="font-bold text-[#0E1D33]"><?= $esc($nf['title']) ?></div>
                  <div class="text-slate-600 mt-0.5 leading-relaxed"><?= $esc($nf['message']) ?></div>
                  <div class="text-[10px] text-slate-400 mt-1 tabular-nums"><?= $esc(substr((string) ($nf['created_at'] ?? ''), 0, 16)) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <a href="/" class="px-3 py-1.5 rounded-lg border border-white/20 text-slate-200 hover:border-[#C9A45C] whitespace-nowrap">
        Trang khách
      </a>
      <?php if (($staffUser['role'] ?? '') === 'admin'): ?>
        <a href="/admin" class="hidden sm:inline-block px-3 py-1.5 rounded-lg border border-white/20 text-slate-200 hover:border-[#C9A45C] whitespace-nowrap">
          Quản trị
        </a>
      <?php endif; ?>
      <span class="hidden md:inline text-slate-300 font-medium truncate max-w-[140px]">
        <?= $esc($staffUser['full_name'] ?? 'Lễ tân') ?>
      </span>
      <a href="/logout" class="px-3 py-1.5 rounded-lg bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] font-bold whitespace-nowrap">
        Đăng xuất
      </a>
    </div>
  </header>

  <div class="flex-1 flex flex-col lg:flex-row min-h-0">
    <!-- SIDEBAR TRÁI (#0E1D33) -->
    <aside class="lg:w-64 bg-[#0E1D33] text-slate-200 flex flex-col justify-between shrink-0 border-r border-white/10">
      <div class="p-4 space-y-5">
        <div class="text-[10px] font-bold tracking-[0.2em] uppercase text-slate-400 px-2">
          DANH MỤC TÁC VỤ LỄ TÂN
        </div>

        <nav class="space-y-1.5 text-xs font-medium">
          <a href="/reception?tab=dashboard"
             class="w-full px-3.5 py-2.5 rounded-xl flex items-center gap-2.5 transition-colors <?= $tab === 'dashboard' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold' : 'text-slate-200 hover:bg-white/10' ?>">
            <span>Bảng điều khiển</span>
          </a>

          <!-- Group Đặt phòng -->
          <div class="space-y-1">
            <a href="/reception?tab=bookings_list"
               class="w-full px-3.5 py-2.5 rounded-xl flex items-center justify-between transition-colors <?= $bookingsGroupOpen ? 'bg-white/10 text-[#E2C27D] font-bold' : 'text-slate-200 hover:bg-white/10' ?>">
              <span>Đặt phòng</span>
              <span class="px-1.5 py-0.5 rounded-full bg-[#C9A45C] text-[#0E1D33] text-[10px] font-bold tabular-nums">
                <?= $pendingBookingsCount + $changeRequestsCount + $newConferencesCount ?>
              </span>
            </a>

            <div class="pl-5 space-y-1">
              <a href="/reception?tab=bookings_list"
                 class="block w-full px-3 py-2 rounded-lg text-left transition-colors <?= $tab === 'bookings_list' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                Danh sách đặt phòng
              </a>
              <a href="/reception?tab=room_rack"
                 class="w-full px-3 py-2 rounded-lg text-left flex items-center justify-between transition-colors <?= $tab === 'room_rack' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                <span>Sơ đồ phòng &amp; Sự cố</span>
                <?php if ($dirtyRoomsCount > 0 || $openIncidentsCount > 0): ?>
                  <span class="px-1.5 py-0.5 rounded bg-amber-400/20 text-amber-300 text-[10px] font-bold tabular-nums">
                    <?= $dirtyRoomsCount + $openIncidentsCount ?>
                  </span>
                <?php endif; ?>
              </a>
              <a href="/reception?tab=conferences"
                 class="w-full px-3 py-2 rounded-lg text-left flex items-center justify-between transition-colors <?= $tab === 'conferences' ? 'bg-white/15 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                <span>Yêu cầu Hội nghị &amp; Đoàn</span>
                <?php if ($newConferencesCount > 0): ?>
                  <span class="px-1.5 py-0.5 rounded bg-amber-400/20 text-amber-300 text-[10px] font-bold tabular-nums">
                    <?= $newConferencesCount ?>
                  </span>
                <?php endif; ?>
              </a>
            </div>
          </div>

          <a href="/reception?tab=services"
             class="w-full px-3.5 py-2.5 rounded-xl flex items-center justify-between transition-colors <?= $tab === 'services' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold' : 'text-slate-200 hover:bg-white/10' ?>">
            <span>Dịch vụ</span>
            <?php if ($pendingServiceOrdersCount > 0): ?>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold tabular-nums <?= $tab === 'services' ? 'bg-[#0E1D33] text-[#E2C27D]' : 'bg-[#C9A45C] text-[#0E1D33]' ?>">
                <?= $pendingServiceOrdersCount ?>
              </span>
            <?php endif; ?>
          </a>

          <a href="/reception?tab=payments"
             class="w-full px-3.5 py-2.5 rounded-xl flex items-center justify-between transition-colors <?= $tab === 'payments' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold' : 'text-slate-200 hover:bg-white/10' ?>">
            <span>Thanh toán</span>
            <?php if (($pendingTransfersCount + $pendingRefundsCount) > 0): ?>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold tabular-nums <?= $tab === 'payments' ? 'bg-[#0E1D33] text-[#E2C27D]' : 'bg-rose-500 text-white' ?>">
                <?= $pendingTransfersCount + $pendingRefundsCount ?>
              </span>
            <?php endif; ?>
          </a>

          <a href="/reception?tab=customers"
             class="w-full px-3.5 py-2.5 rounded-xl flex items-center gap-2.5 transition-colors <?= $tab === 'customers' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold' : 'text-slate-200 hover:bg-white/10' ?>">
            <span>Khách hàng</span>
          </a>
        </nav>
      </div>

      <div class="p-4 border-t border-white/10">
        <button type="button" onclick="openWalkInModal(null)"
                class="w-full py-2.5 px-4 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-xl text-xs font-bold inline-flex items-center justify-center gap-1.5 shadow-sm">
          + Tạo đơn tại quầy
        </button>
      </div>
    </aside>

    <!-- MAIN WORKSPACE -->
    <main class="flex-1 p-5 lg:p-7 space-y-6 overflow-x-hidden">
      <?php if (!empty($flash)): ?>
        <div id="pmsFlash" class="px-4 py-3 rounded-xl text-xs border flex items-center justify-between gap-3 <?= ($flash['type'] ?? 'ok') === 'ok' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
          <span class="font-medium"><?= $esc($flash['msg'] ?? '') ?></span>
          <button type="button" onclick="document.getElementById('pmsFlash').remove()" class="font-bold text-sm leading-none">&times;</button>
        </div>
      <?php endif; ?>

      <!-- ========================================================== -->
      <!-- 1. DASHBOARD -->
      <!-- ========================================================== -->
      <?php if ($tab === 'dashboard'): ?>
        <div class="space-y-6">
          <div class="bg-[#0E1D33] rounded-2xl p-6 text-white shadow-md space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h1 class="font-serif text-2xl sm:text-3xl font-semibold">
                  Xin chào, <?= $esc($staffUser['full_name'] ?? 'Lễ tân') ?>!
                </h1>
                <p class="text-xs text-slate-300 mt-1">
                  Ca trực trực tiếp · còn <strong class="text-[#E2C27D]"><?= $urgentCount ?> việc cần xử lý</strong>
                </p>
              </div>
              <a href="/reception?tab=dashboard"
                 class="px-3.5 py-2 rounded-lg bg-white/10 hover:bg-white/15 border border-white/15 text-xs font-semibold inline-flex items-center gap-1.5">
                Làm mới ca trực
              </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
              <a href="/reception?tab=bookings_list&status=confirmed"
                 class="bg-white text-[#0E1D33] rounded-xl p-4 flex items-center gap-3.5 hover:shadow-md transition-shadow">
                <div class="w-10 h-10 rounded-full bg-[#FAF7F0] flex items-center justify-center shrink-0 text-[#A8843E] font-bold">IN</div>
                <div>
                  <div class="text-xs text-slate-500">Nhận phòng hôm nay</div>
                  <div class="text-2xl font-bold tabular-nums"><?= $arrivingTodayCount ?></div>
                </div>
              </a>

              <a href="/reception?tab=bookings_list&status=checked_in"
                 class="bg-white text-[#0E1D33] rounded-xl p-4 flex items-center gap-3.5 hover:shadow-md transition-shadow">
                <div class="w-10 h-10 rounded-full bg-[#FAF7F0] flex items-center justify-center shrink-0 text-[#A8843E] font-bold">OUT</div>
                <div>
                  <div class="text-xs text-slate-500">Trả phòng hôm nay</div>
                  <div class="text-2xl font-bold tabular-nums"><?= $departingTodayCount ?></div>
                </div>
              </a>

              <a href="/reception?tab=room_rack"
                 class="bg-white text-[#0E1D33] rounded-xl p-4 flex items-center gap-3.5 hover:shadow-md transition-shadow">
                <div class="w-10 h-10 rounded-full bg-[#FAF7F0] flex items-center justify-center shrink-0 text-[#A8843E] font-bold">STAY</div>
                <div>
                  <div class="text-xs text-slate-500">Khách đang ở</div>
                  <div class="text-2xl font-bold tabular-nums"><?= $activeStaysCount ?></div>
                </div>
              </a>

              <a href="/reception?tab=room_rack"
                 class="bg-white text-[#0E1D33] rounded-xl p-4 flex items-center gap-3.5 hover:shadow-md transition-shadow">
                <div class="w-10 h-10 rounded-full bg-[#FAF7F0] flex items-center justify-center shrink-0 text-[#A8843E] font-bold">%</div>
                <div>
                  <div class="text-xs text-slate-500">Công suất phòng</div>
                  <div class="text-2xl font-bold tabular-nums"><?= $occupancyPct ?>%</div>
                </div>
              </a>
            </div>
          </div>

          <!-- ĐẶT PHÒNG HÔM NAY -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-xs font-bold tracking-[0.16em] uppercase text-[#0E1D33]">ĐẶT PHÒNG HÔM NAY</h2>
              <a href="/reception?tab=bookings_list" class="text-xs font-semibold text-[#A8843E] hover:underline">
                Báo cáo đặt phòng &rarr;
              </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 text-center py-2">
              <div>
                <div class="text-2xl font-bold text-[#0E1D33] tabular-nums"><?= $arrivingTodayCount ?></div>
                <div class="text-xs text-slate-500 mt-0.5">lượt đến</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-[#0E1D33] tabular-nums"><?= $departingTodayCount ?></div>
                <div class="text-xs text-slate-500 mt-0.5">lượt đi</div>
              </div>
              <a href="/reception?tab=room_rack" class="block hover:bg-[#FAF7F0] rounded-xl py-1 transition-colors" title="Xem chi tiết trên Sơ đồ phòng">
                <div class="text-2xl font-bold text-[#0E1D33] tabular-nums"><?= (int) ($availableRoomsToday ?? $availableRoomsCount) ?></div>
                <div class="text-xs text-slate-500 mt-0.5">phòng trống</div>
              </a>
              <div>
                <div class="text-2xl font-bold text-[#0E1D33] tabular-nums"><?= $activeStaysCount ?></div>
                <div class="text-xs text-slate-500 mt-0.5">khách ở qua đêm</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-[#0E1D33] tabular-nums"><?= $cancelledTodayCount ?></div>
                <div class="text-xs text-slate-500 mt-0.5">hủy phòng</div>
              </div>
            </div>
          </div>

          <!-- BOTTOM 2 COLUMNS -->
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- KHÁCH ĐẾN / ĐI HÔM NAY -->
            <div class="lg:col-span-7 bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xs font-bold tracking-[0.16em] uppercase text-[#0E1D33]">KHÁCH ĐẾN / ĐI HÔM NAY</h2>
                <input type="text" id="dashGuestSearchInput" oninput="filterDashGuests()"
                       placeholder="Tìm theo mã, tên, SĐT..."
                       class="w-full sm:w-64 px-3 py-1.5 border border-slate-200 rounded-lg text-xs" />
              </div>

              <?php if (empty($arrivalsDeparturesToday)): ?>
                <div class="py-10 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-xl">
                  Không có lượt khách đến / trả phòng nào cần xử lý hôm nay.
                </div>
              <?php else: ?>
                <div id="dashGuestsList" class="divide-y divide-slate-100 text-xs">
                  <?php foreach ($arrivalsDeparturesToday as $b):
                    $isDepart = ($b['status'] ?? '') === 'checked_in';
                    $bId = (int) $b['id'];
                  ?>
                    <div class="py-3 flex flex-wrap items-center justify-between gap-3 dash-guest-row"
                         data-search="<?= $esc(mb_strtolower($b['booking_code'] . ' ' . $b['guest_name'] . ' ' . $b['guest_phone'])) ?>">
                      <div class="space-y-0.5">
                        <div class="flex items-center gap-2 flex-wrap">
                          <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $isDepart ? 'bg-amber-50 text-amber-800' : 'bg-sky-50 text-sky-800' ?>">
                            <?= $isDepart ? 'Sắp trả phòng' : 'Khách đến' ?>
                          </span>
                          <strong class="text-[#0E1D33]"><?= $esc($b['guest_name']) ?></strong>
                          <span class="font-mono text-slate-500"><?= $esc($b['booking_code']) ?></span>
                        </div>
                        <div class="text-slate-500 tabular-nums">
                          <?= $esc($b['category_name']) ?> · <?= !empty($b['assigned_room_number']) ? 'P.' . $esc($b['assigned_room_number']) : 'Chưa gán phòng' ?> ·
                          <?= $esc(date('d/m/Y', strtotime($b['check_in_date']))) ?> &rarr; <?= $esc(date('d/m/Y', strtotime($b['check_out_date']))) ?>
                        </div>
                      </div>
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <?php if ($b['status'] === 'pending'): ?>
                          <form method="POST" action="/reception/bookings/<?= $bId ?>/confirm">
                            <input type="hidden" name="return_tab" value="dashboard" />
                            <button type="submit" class="px-2.5 py-1.5 bg-[#0E1D33] text-white rounded-lg font-semibold">Xác nhận</button>
                          </form>
                        <?php endif; ?>
                        <?php if ($b['status'] === 'confirmed'): ?>
                          <button type="button" onclick="openAssignModal(<?= $bId ?>)"
                                  class="px-2.5 py-1.5 border border-slate-300 rounded-lg font-semibold text-slate-700">Gán phòng</button>
                          <button type="button" onclick="openCheckInModal(<?= $bId ?>)"
                                  class="px-2.5 py-1.5 bg-[#C9A45C] text-[#0E1D33] rounded-lg font-bold">Nhận phòng</button>
                        <?php endif; ?>
                        <?php if ($b['status'] === 'checked_in'): ?>
                          <button type="button" onclick="openCheckOutModal(<?= $bId ?>)"
                                  class="px-2.5 py-1.5 bg-[#C9A45C] text-[#0E1D33] rounded-lg font-bold">Trả phòng</button>
                        <?php endif; ?>
                        <button type="button" onclick="openDetailModal(<?= $bId ?>)"
                                class="px-2.5 py-1.5 border border-slate-200 rounded-lg font-semibold text-slate-600">Chi tiết</button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- CẦN XỬ LÝ NGAY -->
            <div class="lg:col-span-5 bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-3">
              <h2 class="text-xs font-bold tracking-[0.16em] uppercase text-[#0E1D33]">CẦN XỬ LÝ NGAY</h2>
              <div class="space-y-2 text-xs">
                <a href="/reception?tab=bookings_list&status=pending"
                   class="w-full p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#C9A45C] flex items-center justify-between">
                  <span>Đơn đặt phòng chờ xác nhận</span>
                  <strong class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 tabular-nums"><?= $pendingBookingsCount ?></strong>
                </a>
                <a href="/reception?tab=bookings_list"
                   class="w-full p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#C9A45C] flex items-center justify-between">
                  <span>Yêu cầu đổi ngày / hủy phòng từ khách</span>
                  <strong class="px-2 py-0.5 rounded bg-sky-100 text-sky-800 tabular-nums"><?= $changeRequestsCount ?></strong>
                </a>
                <a href="/reception?tab=services"
                   class="w-full p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#C9A45C] flex items-center justify-between">
                  <span>Đơn dịch vụ Spa / Ẩm thực chờ lễ tân xác nhận</span>
                  <strong class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 tabular-nums"><?= $pendingServiceOrdersCount ?></strong>
                </a>
                <a href="/reception?tab=payments"
                   class="w-full p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#C9A45C] flex items-center justify-between">
                  <span>Chuyển khoản QR chờ đối soát sao kê</span>
                  <strong class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 tabular-nums"><?= $pendingTransfersCount ?></strong>
                </a>
                <a href="/reception?tab=room_rack"
                   class="w-full p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#C9A45C] flex items-center justify-between">
                  <span>Phòng chờ dọn &amp; Phản ánh sự cố đang mở</span>
                  <strong class="px-2 py-0.5 rounded bg-slate-200 text-[#0E1D33] tabular-nums"><?= $dirtyRoomsCount + $openIncidentsCount ?></strong>
                </a>
                <a href="/reception?tab=conferences"
                   class="w-full p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#C9A45C] flex items-center justify-between">
                  <span>Yêu cầu tư vấn Hội nghị &amp; Sảnh tiệc mới</span>
                  <strong class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 tabular-nums"><?= $newConferencesCount ?></strong>
                </a>
              </div>
            </div>
          </div>

          <!-- TÌNH TRẠNG PHÒNG HÔM NAY -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
              <h2 class="text-xs font-bold tracking-[0.16em] uppercase text-[#0E1D33]">TÌNH TRẠNG PHÒNG HÔM NAY</h2>
              <a href="/reception?tab=room_rack" class="text-xs font-semibold text-[#A8843E] hover:underline">
                Mở Sơ đồ phòng &rarr;
              </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div class="p-3 rounded-xl border bg-emerald-50 text-emerald-700 border-emerald-200 flex items-center justify-between">
                <span class="font-semibold">Trống sạch</span>
                <strong class="text-lg tabular-nums"><?= $availableRoomsCount ?></strong>
              </div>
              <div class="p-3 rounded-xl border bg-rose-50 text-rose-700 border-rose-200 flex items-center justify-between">
                <span class="font-semibold">Đang có khách</span>
                <strong class="text-lg tabular-nums"><?= $occupiedRoomsCount ?></strong>
              </div>
              <div class="p-3 rounded-xl border bg-amber-50 text-amber-800 border-amber-200 flex items-center justify-between">
                <span class="font-semibold">Chờ dọn phòng</span>
                <strong class="text-lg tabular-nums"><?= $dirtyRoomsCount ?></strong>
              </div>
              <div class="p-3 rounded-xl border bg-slate-100 text-slate-700 border-slate-300 flex items-center justify-between">
                <span class="font-semibold">Đang bảo trì</span>
                <strong class="text-lg tabular-nums"><?= $maintRoomsCount ?></strong>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ========================================================== -->
      <!-- 2. BOOKINGS LIST (Danh sách đặt phòng) -->
      <!-- ========================================================== -->
      <?php if ($tab === 'bookings_list'): ?>
        <?php $initialBookingFilter = (string) ($_GET['status'] ?? 'all'); ?>
        <div class="space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Danh sách đặt phòng</h1>
            <button type="button" onclick="openWalkInModal(null)"
                    class="px-4 py-2 bg-[#0E1D33] hover:bg-[#172E4E] text-white text-xs font-bold rounded-lg inline-flex items-center gap-1.5">
              + Tạo đơn tại quầy
            </button>
          </div>

          <!-- 5 Summary Strip -->
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <button type="button" onclick="setBookingStatusFilter('pending')"
                    class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 text-left hover:border-[#C9A45C]">
              <div class="text-[11px] text-slate-500">Chờ xác nhận</div>
              <div class="text-xl font-bold text-[#0E1D33] tabular-nums mt-0.5"><?= $pendingBookingsCount ?></div>
            </button>
            <button type="button" onclick="setBookingStatusFilter('confirmed')"
                    class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 text-left hover:border-[#C9A45C]">
              <div class="text-[11px] text-slate-500">Nhận phòng hôm nay</div>
              <div class="text-xl font-bold text-[#0E1D33] tabular-nums mt-0.5"><?= $arrivingTodayCount ?></div>
            </button>
            <button type="button" onclick="setBookingStatusFilter('checked_in')"
                    class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 text-left hover:border-[#C9A45C]">
              <div class="text-[11px] text-slate-500">Đang ở</div>
              <div class="text-xl font-bold text-[#0E1D33] tabular-nums mt-0.5"><?= $activeStaysCount ?></div>
            </button>
            <button type="button" onclick="setBookingStatusFilter('checked_in')"
                    class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 text-left hover:border-[#C9A45C]">
              <div class="text-[11px] text-slate-500">Trả phòng hôm nay</div>
              <div class="text-xl font-bold text-[#0E1D33] tabular-nums mt-0.5"><?= $departingTodayCount ?></div>
            </button>
            <a href="/reception?tab=payments"
               class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 text-left hover:border-[#C9A45C]">
              <div class="text-[11px] text-slate-500">Chuyển khoản chờ đối soát</div>
              <div class="text-xl font-bold text-[#0E1D33] tabular-nums mt-0.5"><?= $pendingTransfersCount ?></div>
            </a>
          </div>

          <!-- Change / Cancel Request Queue -->
          <?php if (!empty($changeRequestsList)): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 space-y-2.5 text-xs">
              <div class="font-bold text-amber-900 uppercase tracking-wider">
                Yêu cầu đổi lịch / hủy phòng từ khách hàng (<?= count($changeRequestsList) ?>)
              </div>
              <?php foreach ($changeRequestsList as $cr):
                $crPayload = !empty($cr['change_request_payload']) ? (json_decode((string) $cr['change_request_payload'], true) ?: []) : [];
                $crId = (int) $cr['id'];
                $crSt = (string) ($cr['change_request_status'] ?? 'none');
              ?>
                <div class="bg-white border border-amber-200 rounded-xl p-3 flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <strong><?= $esc($cr['booking_code']) ?> · <?= $esc($cr['guest_name']) ?></strong>
                    <span class="text-amber-800 font-semibold">
                      (<?= $crSt === 'date_change_pending' ? 'Đổi ngày / hạng phòng' : ($crSt === 'reception_cancel_pending' ? 'Lễ tân đã gửi đề nghị hủy · Chờ khách xác nhận' : 'Khách yêu cầu hủy đơn') ?>)
                    </span>
                    <div class="text-slate-600 mt-0.5">
                      <?php if ($crSt === 'date_change_pending'): ?>
                        Đề xuất mới: <?= $esc($crPayload['new_check_in_date'] ?? '') ?> &rarr; <?= $esc($crPayload['new_check_out_date'] ?? '') ?>
                        <?= !empty($crPayload['new_category_name']) ? ' · ' . $esc($crPayload['new_category_name']) : '' ?>
                      <?php elseif ($crSt === 'reception_cancel_pending'): ?>
                        Lý do Lễ tân gửi khách: <?= $esc($crPayload['reason'] ?? 'Đề nghị hủy từ Lễ tân') ?>
                        <?= !empty($crPayload['proposed_by_name']) ? ' · Người gửi: ' . $esc($crPayload['proposed_by_name']) : '' ?>
                      <?php else: ?>
                        Lý do hủy: <?= $esc($crPayload['reason'] ?? 'Khách yêu cầu') ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <?php if ($crSt === 'date_change_pending'): ?>
                      <form method="POST" action="/reception/bookings/<?= $crId ?>/resolve-request">
                        <input type="hidden" name="return_tab" value="bookings_list" />
                        <input type="hidden" name="decision" value="approve" />
                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg font-bold">Phê duyệt</button>
                      </form>
                      <form method="POST" action="/reception/bookings/<?= $crId ?>/resolve-request">
                        <input type="hidden" name="return_tab" value="bookings_list" />
                        <input type="hidden" name="decision" value="reject" />
                        <button type="submit" class="px-3 py-1.5 border border-rose-300 text-rose-700 rounded-lg font-bold">Từ chối</button>
                      </form>
                    <?php elseif ($crSt === 'reception_cancel_pending'): ?>
                      <form method="POST" action="/reception/bookings/<?= $crId ?>/resolve-request">
                        <input type="hidden" name="return_tab" value="bookings_list" />
                        <input type="hidden" name="decision" value="reject" />
                        <input type="hidden" name="action_mode" value="withdraw" />
                        <button type="submit" class="px-3 py-1.5 border border-slate-300 bg-white text-[#0E1D33] rounded-lg font-bold">Rút đề nghị hủy</button>
                      </form>
                    <?php else: ?>
                      <button type="button" onclick='openCancelBookingModal(<?= $crId ?>, <?= $esc(json_encode($crPayload['reason'] ?? 'Khách yêu cầu hủy đặt phòng', JSON_HEX_TAG | JSON_UNESCAPED_UNICODE)) ?>)'
                              class="px-3 py-1.5 bg-rose-600 text-white rounded-lg font-bold">Duyệt hủy...</button>
                      <form method="POST" action="/reception/bookings/<?= $crId ?>/resolve-request">
                        <input type="hidden" name="return_tab" value="bookings_list" />
                        <input type="hidden" name="decision" value="reject" />
                        <button type="submit" class="px-3 py-1.5 border border-rose-300 text-rose-700 rounded-lg font-bold">Từ chối</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Search & Filter Row -->
          <div class="flex flex-wrap items-center gap-3">
            <input type="text" id="bookingSearchInput" value="<?= $esc($search ?? '') ?>" oninput="filterBookingsTable()"
                   placeholder="Tìm theo mã đặt phòng, tên khách, SĐT, số phòng..."
                   class="flex-1 min-w-[220px] max-w-sm px-3.5 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
            <select id="bookingStatusFilterSelect" onchange="filterBookingsTable()"
                    class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-medium">
              <option value="all" <?= $initialBookingFilter === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
              <option value="pending" <?= $initialBookingFilter === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
              <option value="confirmed" <?= $initialBookingFilter === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
              <option value="checked_in" <?= $initialBookingFilter === 'checked_in' ? 'selected' : '' ?>>Đang lưu trú</option>
              <option value="overdue" <?= $initialBookingFilter === 'overdue' ? 'selected' : '' ?>>Quá hạn</option>
              <option value="checked_out" <?= $initialBookingFilter === 'checked_out' ? 'selected' : '' ?>>Đã trả phòng</option>
              <option value="cancelled" <?= $initialBookingFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
            </select>
            <button type="button" onclick="openWalkInModal(null)"
                    class="px-4 py-2 bg-[#0E1D33] text-white text-xs font-bold rounded-lg">
              + Tạo đơn tại quầy
            </button>
          </div>

          <!-- Bookings Table -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[10px] font-bold uppercase tracking-wider text-slate-500">
                  <tr>
                    <th class="py-3 px-4">MÃ</th>
                    <th class="py-3 px-3">KHÁCH</th>
                    <th class="py-3 px-3">HẠNG PHÒNG</th>
                    <th class="py-3 px-3">NHẬN / TRẢ</th>
                    <th class="py-3 px-3">TRẠNG THÁI</th>
                    <th class="py-3 px-3">TIỀN CỌC / TỔNG</th>
                    <th class="py-3 px-4">THAO TÁC</th>
                  </tr>
                </thead>
                <tbody id="bookingsTableBody" class="divide-y divide-slate-100">
                  <?php foreach ($bookingsList as $b):
                    $bId = (int) $b['id'];
                    $st = (string) ($b['status'] ?? 'pending');
                    $isOverdue = !empty($b['is_overdue']);
                    $overdueType = $b['overdue_type'] ?? null;
                    if ($isOverdue) {
                        $effLabel = $overdueType === 'checkout_overdue' ? 'Quá hạn trả phòng' : 'Quá hạn nhận phòng';
                        $effCls = 'bg-rose-50 text-rose-700 border-rose-200';
                    } else {
                        $sb = $statusBadgeMap[$st] ?? [$st, 'bg-slate-100 text-slate-700 border-slate-200'];
                        $effLabel = $sb[0];
                        $effCls = $sb[1];
                    }
                    $grandTotal = $computeFolioGrandTotal($b);
                    $paid = (float) ($b['total_paid'] ?? 0);
                    $remaining = max(0.0, $grandTotal - $paid);
                    $depositReq = (float) ($b['deposit_required'] ?? 0);
                    $depositShort = max(0.0, $depositReq - $paid);
                    $searchText = mb_strtolower($b['booking_code'] . ' ' . $b['guest_name'] . ' ' . $b['guest_phone'] . ' ' . ($b['assigned_room_number'] ?? ''));
                  ?>
                    <tr class="hover:bg-[#FCFBF7] booking-row"
                        data-status="<?= $esc($st) ?>"
                        data-overdue="<?= $isOverdue ? '1' : '0' ?>"
                        data-search="<?= $esc($searchText) ?>">
                      <td class="py-3 px-4">
                        <div class="font-mono font-bold text-[#0E1D33]"><?= $esc($b['booking_code']) ?></div>
                        <div class="text-[11px] text-slate-400"><?= ($b['source'] ?? 'online') === 'walk_in' ? 'tại quầy' : 'online' ?></div>
                      </td>
                      <td class="py-3 px-3">
                        <div class="font-semibold text-[#0E1D33]"><?= $esc($b['guest_name']) ?></div>
                        <div class="text-[11px] text-slate-500 tabular-nums"><?= $esc($b['guest_phone']) ?></div>
                      </td>
                      <td class="py-3 px-3">
                        <div class="font-medium text-[#0E1D33]"><?= $esc($b['category_name']) ?></div>
                        <div class="text-[11px] text-[#A8843E] font-semibold">
                          <?= !empty($b['assigned_room_number']) ? 'P.' . $esc($b['assigned_room_number']) : 'Chưa gán phòng' ?>
                        </div>
                      </td>
                      <td class="py-3 px-3 tabular-nums">
                        <div><?= $esc($b['check_in_date']) ?></div>
                        <div>
                          <?= $esc($b['check_out_date']) ?>
                          <?php if (!empty($b['checkout_time_slot']) && $b['checkout_time_slot'] !== '12:00'): ?>
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 text-[10px] font-bold"><?= $esc($b['checkout_time_slot']) ?></span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="py-3 px-3">
                        <span class="px-2.5 py-1 rounded-full border text-[11px] font-semibold whitespace-nowrap <?= $effCls ?>">
                          • <?= $esc($effLabel) ?>
                        </span>
                        <?php if (($b['change_request_status'] ?? 'none') === 'reception_cancel_pending'): ?>
                          <div class="text-[10px] font-semibold text-amber-800 mt-1">Chờ khách xác nhận hủy</div>
                        <?php elseif (($b['change_request_status'] ?? 'none') === 'cancel_pending'): ?>
                          <div class="text-[10px] font-semibold text-rose-700 mt-1">Khách yêu cầu hủy</div>
                        <?php elseif (($b['change_request_status'] ?? 'none') === 'date_change_pending'): ?>
                          <div class="text-[10px] font-semibold text-sky-700 mt-1">Khách yêu cầu đổi lịch</div>
                        <?php endif; ?>
                      </td>
                      <td class="py-3 px-3 tabular-nums">
                        <?php if ($st === 'checked_out' && $remaining <= 0): ?>
                          <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold whitespace-nowrap">
                            • Đã tất toán 100%
                          </span>
                        <?php else: ?>
                          <div class="space-y-0.5">
                            <?php if ($depositShort > 0 && ($st === 'pending' || $st === 'confirmed')): ?>
                              <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-semibold inline-block whitespace-nowrap">
                                • Cần cọc 30% (<?= $fmt($depositReq) ?>)
                              </span>
                            <?php else: ?>
                              <span class="px-2 py-0.5 rounded-full bg-sky-50 text-sky-800 border border-sky-200 font-semibold inline-block whitespace-nowrap">
                                • Đã cọc <?= $fmt($paid) ?>
                              </span>
                            <?php endif; ?>
                            <div class="text-[11px] text-rose-700 font-semibold">Còn thu: <?= $fmt($remaining) ?></div>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="py-3 px-4">
                        <div class="flex flex-wrap gap-1.5">
                          <button type="button" onclick="openInvoiceModal(<?= $bId ?>)"
                                  class="px-2.5 py-1 border border-slate-300 bg-white rounded-lg font-semibold text-[#0E1D33] hover:border-[#0E1D33]">
                            Hóa đơn
                          </button>
                          <button type="button" onclick="openDetailModal(<?= $bId ?>)"
                                  class="px-2.5 py-1 border border-slate-200 bg-white rounded-lg font-semibold text-slate-700 hover:border-[#0E1D33]">
                            Chi tiết / Sửa
                          </button>
                          <?php if ($st === 'pending'): ?>
                            <form method="POST" action="/reception/bookings/<?= $bId ?>/confirm" class="inline">
                              <input type="hidden" name="return_tab" value="bookings_list" />
                              <button type="submit" class="px-2.5 py-1 bg-[#0E1D33] text-white rounded-lg font-semibold">Xác nhận</button>
                            </form>
                            <button type="button" onclick="openAssignModal(<?= $bId ?>)"
                                    class="px-2.5 py-1 border border-slate-300 rounded-lg font-semibold">Gán phòng</button>
                          <?php elseif ($st === 'confirmed' && !$isOverdue): ?>
                            <button type="button" onclick="openAssignModal(<?= $bId ?>)"
                                    class="px-2.5 py-1 border border-slate-300 rounded-lg font-semibold">Gán phòng</button>
                            <button type="button" onclick="openCheckInModal(<?= $bId ?>)"
                                    class="px-2.5 py-1 bg-sky-100 text-sky-900 rounded-lg font-bold">Nhận phòng</button>
                          <?php elseif ($st === 'checked_in'): ?>
                            <button type="button" onclick="openModifyStayModal(<?= $bId ?>, 'extend_stay')"
                                    class="px-2.5 py-1 border border-[#C9A45C] bg-[#FAF7F0] text-[#0E1D33] rounded-lg font-semibold">
                              Gia hạn / Đổi phòng
                            </button>
                            <button type="button" onclick="openCheckOutModal(<?= $bId ?>)"
                                    class="px-2.5 py-1 bg-[#C9A45C] text-[#0E1D33] rounded-lg font-bold">Trả phòng</button>
                          <?php endif; ?>
                          <?php if ($st === 'pending' || $st === 'confirmed'): ?>
                            <?php if (($b['change_request_status'] ?? 'none') === 'reception_cancel_pending'): ?>
                              <form method="POST" action="/reception/bookings/<?= $bId ?>/resolve-request" class="inline">
                                <input type="hidden" name="return_tab" value="bookings_list" />
                                <input type="hidden" name="decision" value="reject" />
                                <input type="hidden" name="action_mode" value="withdraw" />
                                <button type="submit" class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-300 rounded-lg font-semibold hover:bg-amber-100">Rút đề nghị hủy</button>
                              </form>
                            <?php else: ?>
                              <button type="button" onclick="openCancelBookingModal(<?= $bId ?>, 'Hủy đơn từ quầy Lễ tân')"
                                      class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg font-semibold hover:bg-rose-100">Hủy...</button>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ========================================================== -->
      <!-- 3. ROOM RACK, HOUSEKEEPING & INCIDENTS -->
      <!-- ========================================================== -->
      <?php if ($tab === 'room_rack'): ?>
        <div class="space-y-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Sơ đồ phòng (Room Rack)</h1>
              <p class="text-xs text-slate-500 mt-0.5">Nhấn vào phòng để thao tác nhanh theo từng ngày cụ thể.</p>
            </div>

            <form method="GET" action="/reception" class="flex flex-wrap items-center gap-2 text-xs">
              <input type="hidden" name="tab" value="room_rack" />
              <a href="/reception?tab=room_rack&rack_date=<?= $esc($rackPrevDate) ?>"
                 class="px-3 py-2 bg-white border border-slate-200 rounded-lg font-semibold">&lsaquo; Hôm trước</a>
              <input type="date" name="rack_date" value="<?= $esc($rackDateStr) ?>" onchange="this.form.submit()"
                     class="px-3 py-2 bg-white border border-slate-200 rounded-lg font-semibold tabular-nums" />
              <a href="/reception?tab=room_rack&rack_date=<?= $esc($rackNextDate) ?>"
                 class="px-3 py-2 bg-white border border-slate-200 rounded-lg font-semibold">Hôm sau &rsaquo;</a>
              <a href="/reception?tab=room_rack&rack_date=<?= $esc($todayStr) ?>"
                 class="px-3 py-2 bg-white border border-slate-200 rounded-lg font-semibold">Hôm nay</a>
              <button type="button" onclick="openWalkInModal(null)"
                      class="px-4 py-2 bg-[#0E1D33] text-white rounded-lg font-bold">+ Tạo đơn tại quầy</button>
            </form>
          </div>

          <!-- 5 Summary Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center font-bold">●</span>
              <div><div class="text-[11px] text-slate-500">Đang có khách</div><div class="text-lg font-bold text-[#0E1D33] tabular-nums"><?= $rackSummary['occupied'] ?></div></div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center font-bold">●</span>
              <div><div class="text-[11px] text-slate-500">Khách sắp đến</div><div class="text-lg font-bold text-[#0E1D33] tabular-nums"><?= $rackSummary['arriving'] ?></div></div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold">●</span>
              <div><div class="text-[11px] text-slate-500">Khách sắp trả</div><div class="text-lg font-bold text-[#0E1D33] tabular-nums"><?= $rackSummary['departing'] ?></div></div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">●</span>
              <div><div class="text-[11px] text-slate-500">Trống sạch</div><div class="text-lg font-bold text-[#0E1D33] tabular-nums"><?= $rackSummary['cleanAvail'] ?></div></div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-3.5 flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-bold">●</span>
              <div><div class="text-[11px] text-slate-500">Vừa trả - cần dọn</div><div class="text-lg font-bold text-[#0E1D33] tabular-nums"><?= $rackSummary['dirtyNeedClean'] ?></div></div>
            </div>
          </div>

          <!-- Legend -->
          <div class="flex flex-wrap gap-4 text-[11px] text-slate-600">
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded border border-rose-300 bg-rose-100"></span> Đang có khách</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded border border-sky-300 bg-sky-100"></span> Khách sắp đến</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded border border-amber-300 bg-amber-100"></span> Cần dọn / Chờ nghiệm thu</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded border border-emerald-300 bg-emerald-100"></span> Trống sạch</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded border border-slate-300 bg-slate-200"></span> Bảo trì / Khóa</span>
          </div>

          <!-- Floor-by-Floor Room Cards -->
          <div class="space-y-4">
            <?php foreach ($roomRackByFloor as $floor => $floorRooms): ?>
              <div class="flex flex-col sm:flex-row gap-3 items-start">
                <div class="w-20 pt-3 text-xs font-bold text-slate-500 shrink-0">Tầng <?= $esc($floor) ?></div>
                <div class="flex-1 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 w-full">
                  <?php foreach ($floorRooms as $rm):
                    $rId = (int) $rm['id'];
                    $bookingOnDate = $findRoomBookingOnDate($rId, $rackDateStr);
                    $hasOpenInc = false;
                    foreach ($incidentsList as $incRow) {
                        if ((int) ($incRow['room_id'] ?? 0) === $rId && ($incRow['status'] ?? '') === 'open') {
                            $hasOpenInc = true;
                            break;
                        }
                    }
                    $cardStyle = 'bg-emerald-50/70 border-emerald-200 text-emerald-950';
                    $statusText = 'Trống sạch';
                    if (($rm['status'] ?? '') === 'maintenance') {
                        $cardStyle = 'bg-slate-100 border-slate-300 text-slate-700';
                        $statusText = 'Bảo trì / Khóa';
                    } elseif (($rm['status'] ?? '') === 'dirty' || ($rm['clean_status'] ?? '') === 'dirty') {
                        $cardStyle = 'bg-amber-50 border-amber-300 text-amber-900';
                        $statusText = 'Vừa trả — cần dọn';
                    } elseif (($rm['clean_status'] ?? '') === 'inspecting') {
                        $cardStyle = 'bg-sky-50 border-sky-300 text-sky-900';
                        $statusText = 'Chờ nghiệm thu';
                    } elseif ($bookingOnDate && ($bookingOnDate['status'] ?? '') === 'checked_in') {
                        $cardStyle = 'bg-rose-50 border-rose-300 text-rose-950';
                        $statusText = ($bookingOnDate['check_out_date'] ?? '') === $rackDateStr ? 'Khách sắp trả' : 'Đang có khách';
                    } elseif ($bookingOnDate && ($bookingOnDate['status'] ?? '') === 'confirmed') {
                        $cardStyle = 'bg-sky-50 border-sky-300 text-sky-950';
                        $statusText = 'Khách sắp đến';
                    }
                  ?>
                    <button type="button" onclick="openRackRoomModal(<?= $rId ?>)"
                            class="p-3.5 rounded-xl border text-left transition-all hover:shadow-md <?= $cardStyle ?>">
                      <div class="flex items-center justify-between">
                        <span class="text-lg font-bold tabular-nums"><?= $esc($rm['room_number']) ?></span>
                        <?php if ($hasOpenInc): ?>
                          <span class="px-1.5 py-0.5 rounded bg-rose-600 text-white text-[9px] font-bold">Sự cố</span>
                        <?php endif; ?>
                      </div>
                      <div class="text-[11px] opacity-75 truncate"><?= $esc($rm['category_name']) ?></div>
                      <?php if ($bookingOnDate): ?>
                        <div class="mt-1.5">
                          <div class="text-xs font-bold truncate"><?= $esc($bookingOnDate['guest_name']) ?></div>
                          <div class="text-[10px] opacity-75 tabular-nums">
                            <?= $esc(substr((string) $bookingOnDate['check_in_date'], 5)) ?> &rarr; <?= $esc(substr((string) $bookingOnDate['check_out_date'], 5)) ?>
                          </div>
                        </div>
                      <?php else: ?>
                        <div class="mt-2 text-xs font-semibold"><?= $esc($statusText) ?></div>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Housekeeping Queue -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 class="font-serif text-xl font-semibold text-[#0E1D33]">Yêu cầu dọn phòng &amp; Nghiệm thu buồng phòng</h2>
                <p class="text-xs text-slate-500">Theo dõi trạng thái dọn phòng sau Check-out / Đổi phòng và xác nhận phòng sạch sẵn sàng đón khách.</p>
              </div>
              <button type="button" onclick="openHousekeepingModal(<?= (int) ($roomsList[0]['id'] ?? 0) ?>)"
                      class="px-3.5 py-2 bg-[#0E1D33] text-white text-xs font-bold rounded-lg">
                + Tạo yêu cầu dọn / kiểm tra
              </button>
            </div>

            <?php if (empty($housekeepingList)): ?>
              <div class="py-6 text-center text-xs text-slate-400">Chưa có nhiệm vụ dọn phòng nào.</div>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="border-b border-slate-200 text-[10px] font-bold uppercase text-slate-400">
                      <th class="py-2 pr-3">Phòng</th>
                      <th class="py-2 px-3">Loại yêu cầu</th>
                      <th class="py-2 px-3">Ghi chú</th>
                      <th class="py-2 px-3">Trạng thái</th>
                      <th class="py-2 pl-3">Xử lý nhanh</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <?php foreach (array_slice($housekeepingList, 0, 15) as $ht):
                      $htId = (int) $ht['id'];
                      $htSt = (string) ($ht['status'] ?? 'pending');
                      $htType = (string) ($ht['task_type'] ?? 'checkout_clean');
                      $htTypeLabel = $htType === 'checkout_clean' ? 'Dọn sau trả/đổi phòng' : ($htType === 'maintenance' ? 'Bảo trì kỹ thuật' : 'Dọn phòng định kỳ');
                      $htCls = $htSt === 'verified' ? 'bg-emerald-50 text-emerald-700' : ($htSt === 'completed' ? 'bg-sky-50 text-sky-700' : ($htSt === 'in_progress' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-700'));
                      $htLabel = $htSt === 'pending' ? 'Chờ dọn' : ($htSt === 'in_progress' ? 'Đang dọn' : ($htSt === 'completed' ? 'Chờ nghiệm thu' : 'Đã sạch sẵn sàng'));
                    ?>
                      <tr>
                        <td class="py-2.5 pr-3 font-bold text-[#0E1D33]">P.<?= $esc($ht['room_number']) ?> (<?= $esc($ht['category_name']) ?>)</td>
                        <td class="py-2.5 px-3"><?= $esc($htTypeLabel) ?></td>
                        <td class="py-2.5 px-3 text-slate-600"><?= $esc($ht['assigned_note'] ?? '') ?></td>
                        <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $htCls ?>"><?= $htLabel ?></span></td>
                        <td class="py-2.5 pl-3">
                          <div class="flex flex-wrap gap-1.5">
                            <?php if ($htSt === 'pending'): ?>
                              <form method="POST" action="/reception/housekeeping/<?= $htId ?>/verify" class="inline">
                                <input type="hidden" name="return_tab" value="room_rack" />
                                <input type="hidden" name="next_status" value="in_progress" />
                                <button type="submit" class="px-2.5 py-1 bg-amber-100 text-amber-900 rounded font-semibold">Bắt đầu dọn</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($htSt === 'pending' || $htSt === 'in_progress'): ?>
                              <form method="POST" action="/reception/housekeeping/<?= $htId ?>/verify" class="inline">
                                <input type="hidden" name="return_tab" value="room_rack" />
                                <input type="hidden" name="next_status" value="completed" />
                                <button type="submit" class="px-2.5 py-1 bg-sky-100 text-sky-900 rounded font-semibold">Đã dọn xong</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($htSt !== 'verified'): ?>
                              <form method="POST" action="/reception/housekeeping/<?= $htId ?>/verify" class="inline">
                                <input type="hidden" name="return_tab" value="room_rack" />
                                <input type="hidden" name="next_status" value="verified" />
                                <button type="submit" class="px-2.5 py-1 bg-emerald-600 text-white rounded font-semibold">Nghiệm thu sạch</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Guest Feedback & Incidents -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 class="font-serif text-xl font-semibold text-[#0E1D33]">Phản ánh &amp; Sự cố của khách (Trong lưu trú &amp; Check-out)</h2>
                <p class="text-xs text-slate-500">Toàn bộ phản ánh được lưu vào CSDL MySQL và đồng bộ trực tiếp tới Quản trị viên (Admin).</p>
              </div>
              <button type="button" onclick="openIncidentModal(null)"
                      class="px-3.5 py-2 bg-[#C9A45C] text-[#0E1D33] text-xs font-bold rounded-lg">
                + Ghi nhận phản ánh / sự cố
              </button>
            </div>

            <?php if (empty($incidentsList)): ?>
              <div class="py-6 text-center text-xs text-slate-400">Chưa có phản ánh sự cố nào được ghi nhận.</div>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="border-b border-slate-200 text-[10px] font-bold uppercase text-slate-400">
                      <th class="py-2 pr-3">Phòng</th>
                      <th class="py-2 px-3">Nội dung phản ánh / Sự cố</th>
                      <th class="py-2 px-3">Mức độ</th>
                      <th class="py-2 px-3">Phương án xử lý</th>
                      <th class="py-2 px-3">Trạng thái</th>
                      <th class="py-2 pl-3">Thao tác</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <?php foreach ($incidentsList as $inc):
                      $incId = (int) $inc['id'];
                      $sev = (string) ($inc['severity'] ?? 'LOW');
                      $sevCls = $sev === 'HIGH' ? 'bg-rose-100 text-rose-800' : ($sev === 'MEDIUM' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700');
                      $incSt = (string) ($inc['status'] ?? 'open');
                    ?>
                      <tr>
                        <td class="py-2.5 pr-3 font-bold text-[#0E1D33]">P.<?= $esc($inc['room_number']) ?></td>
                        <td class="py-2.5 px-3">
                          <div class="text-[#0E1D33] font-medium"><?= $esc($inc['content']) ?></div>
                          <div class="text-[11px] text-slate-400 tabular-nums">
                            Ghi nhận: <?= $esc($inc['reported_by_name'] ?: 'Lễ tân') ?> · <?= $esc(substr((string) ($inc['created_at'] ?? ''), 0, 16)) ?>
                          </div>
                        </td>
                        <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $sevCls ?>"><?= $esc($sev) ?></span></td>
                        <td class="py-2.5 px-3 text-slate-600"><?= $esc($inc['resolution_plan'] ?: '—') ?></td>
                        <td class="py-2.5 px-3">
                          <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $incSt === 'resolved' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>">
                            <?= $incSt === 'resolved' ? 'Đã xử lý' : 'Đang mở' ?>
                          </span>
                        </td>
                        <td class="py-2.5 pl-3">
                          <form method="POST" action="/reception/incidents/<?= $incId ?>/status">
                            <input type="hidden" name="return_tab" value="room_rack" />
                            <input type="hidden" name="status" value="<?= $incSt === 'open' ? 'resolved' : 'open' ?>" />
                            <button type="submit" class="px-2.5 py-1 border border-slate-300 rounded font-semibold text-[#0E1D33] hover:bg-slate-50">
                              <?= $incSt === 'open' ? 'Đánh dấu Đã xử lý' : 'Mở lại' ?>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- ========================================================== -->
      <!-- 4. SERVICES DISPATCH -->
      <!-- ========================================================== -->
      <?php if ($tab === 'services'): ?>
        <div class="space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Quản lý &amp; Điều phối Dịch vụ</h1>
              <p class="text-xs text-slate-500 mt-0.5">
                Tiếp nhận yêu cầu khách đặt từ Web, đăng ký dịch vụ bổ sung tại quầy và theo dõi bộ phận liên quan hoàn thành.
              </p>
            </div>
            <button type="button" onclick="openDeskServiceModal(null)"
                    class="px-4 py-2.5 bg-[#0E1D33] hover:bg-[#172E4E] text-white text-xs font-bold rounded-lg">
              + Đăng ký dịch vụ tại quầy
            </button>
          </div>

          <!-- Role Switcher + Search/Filter -->
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-xl border border-[#E6DFD1] bg-white p-1 text-xs">
              <button type="button" id="srvRoleBtnReception" onclick="setServiceRoleView('reception')"
                      class="px-3.5 py-1.5 rounded-lg font-bold inline-flex items-center gap-2 bg-[#0E1D33] text-white">
                <span>Quầy Lễ tân (Xác nhận thông tin khách)</span>
                <?php if ($pendingServiceOrdersCount > 0): ?>
                  <span class="px-1.5 py-0.5 rounded-full bg-[#C9A45C] text-[#0E1D33] text-[10px] font-bold tabular-nums"><?= $pendingServiceOrdersCount ?></span>
                <?php endif; ?>
              </button>
              <button type="button" id="srvRoleBtnDept" onclick="setServiceRoleView('department')"
                      class="px-3.5 py-1.5 rounded-lg font-bold inline-flex items-center gap-2 text-slate-600">
                <span>Bộ phận Bếp &amp; Dịch vụ (Xác nhận hoàn thành)</span>
                <?php if ($acceptedServiceOrdersCount > 0): ?>
                  <span class="px-1.5 py-0.5 rounded-full bg-emerald-500 text-white text-[10px] font-bold tabular-nums"><?= $acceptedServiceOrdersCount ?></span>
                <?php endif; ?>
              </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
              <input type="text" id="serviceSearchInput" oninput="filterServicesTable()"
                     placeholder="Tìm phòng, mã đơn, tên món..."
                     class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg" />
              <select id="serviceStatusSelect" onchange="filterServicesTable()"
                      class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg">
                <option value="all">Tất cả trạng thái</option>
                <option value="pending">Chờ xác nhận</option>
                <option value="accepted">Đã xác nhận / Đang phục vụ</option>
                <option value="completed">Hoàn thành</option>
                <option value="cancelled">Đã hủy (kèm lý do)</option>
              </select>
            </div>
          </div>

          <div id="srvPolicyReception" class="px-4 py-2.5 rounded-xl border text-xs bg-[#FAF7F0] border-[#E6DFD1] text-[#0E1D33]">
            <strong>Phân quyền Quầy Lễ tân:</strong> Lễ tân chỉ kiểm tra, <strong>xác nhận thông tin khách đặt</strong> và chuyển yêu cầu sang Bộ phận Bếp &amp; Dịch vụ (không tự bấm Hoàn thành).
          </div>
          <div id="srvPolicyDept" class="hidden px-4 py-2.5 rounded-xl border text-xs bg-emerald-50/70 border-emerald-200 text-emerald-950">
            <strong>Phân quyền Bộ phận Bếp &amp; Dịch vụ:</strong> Chỉ thực hiện và bấm <strong>Xác nhận Hoàn thành</strong> đối với các yêu cầu đã được Lễ tân xác nhận thông tin khách.
          </div>

          <!-- 4 Group Pills -->
          <div class="flex flex-wrap gap-2 text-xs" id="srvGroupPills">
            <?php
            $srvGroups = [
                'all'        => 'Tất cả nhóm',
                'spa'        => 'Spa & Trị liệu',
                'dining'     => 'Ăn uống & Nhà hàng',
                'utilities'  => 'Tiện ích & Chuẩn bị',
                'experience' => 'Trải nghiệm & Combo',
            ];
            foreach ($srvGroups as $gk => $gl):
            ?>
              <button type="button" onclick="setServiceGroupFilter('<?= $gk ?>')" data-srv-group="<?= $gk ?>"
                      class="srv-group-btn px-3 py-1.5 rounded-lg border font-semibold <?= $gk === 'all' ? 'bg-[#C9A45C] text-[#0E1D33] border-[#C9A45C]' : 'bg-white text-slate-600 border-[#E6DFD1]' ?>">
                <?= $gl ?>
              </button>
            <?php endforeach; ?>
          </div>

          <!-- Service Orders Table -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[10px] font-bold uppercase text-slate-500">
                  <tr>
                    <th class="py-3 px-4">PHÒNG / MÃ ĐƠN</th>
                    <th class="py-3 px-3">DỊCH VỤ / NHÓM</th>
                    <th class="py-3 px-3">THỜI GIAN &amp; GHI CHÚ</th>
                    <th class="py-3 px-3">THÀNH TIỀN</th>
                    <th class="py-3 px-3">TRẠNG THÁI</th>
                    <th class="py-3 px-4">THAO TÁC</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php if (empty($serviceOrdersList)): ?>
                    <tr><td colspan="6" class="py-10 text-center text-slate-400">Chưa có yêu cầu dịch vụ nào.</td></tr>
                  <?php else: ?>
                    <?php foreach ($serviceOrdersList as $so):
                      $soId = (int) $so['id'];
                      $soCat = (string) ($so['service_category'] ?? '');
                      $grpKey = 'utilities';
                      $grpLabel = 'Tiện ích & Chuẩn bị';
                      if ($soCat === 'spa') {
                          $grpKey = 'spa';
                          $grpLabel = 'Spa & Trị liệu';
                      } elseif (str_starts_with($soCat, 'dining_')) {
                          $grpKey = 'dining';
                          $grpLabel = 'Ăn uống & Nhà hàng';
                      } elseif ($soCat === 'experience' || $soCat === 'combo') {
                          $grpKey = 'experience';
                          $grpLabel = 'Trải nghiệm & Combo';
                      }
                      $soSt = (string) ($so['status'] ?? 'pending');
                      $soCls = $soSt === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                          : ($soSt === 'accepted' ? 'bg-sky-50 text-sky-800 border-sky-200'
                          : ($soSt === 'cancelled' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-800 border-amber-200'));
                      $soLabel = $soSt === 'pending' ? 'Chờ Lễ tân xác nhận'
                          : ($soSt === 'accepted' ? 'Đã xác nhận · Chờ Bếp/DV'
                          : ($soSt === 'completed' ? 'Hoàn thành' : 'Đã hủy'));
                      $soCancelReason = trim((string) ($so['cancel_reason'] ?? ''));
                      $soSearch = mb_strtolower(($so['room_number'] ?? '') . ' ' . $so['booking_code'] . ' ' . $so['guest_name'] . ' ' . $so['service_name_snapshot'] . ' ' . $soCancelReason);
                    ?>
                      <tr class="hover:bg-[#FCFBF7] srv-row"
                          data-status="<?= $esc($soSt) ?>"
                          data-group="<?= $esc($grpKey) ?>"
                          data-search="<?= $esc($soSearch) ?>">
                        <td class="py-3 px-4">
                          <div class="font-bold text-[#0E1D33]">
                            <?= !empty($so['room_number']) ? 'P.' . $esc($so['room_number']) : 'Chưa gán phòng' ?> ·
                            <span class="font-mono"><?= $esc($so['booking_code']) ?></span>
                          </div>
                          <div class="text-[11px] text-slate-500"><?= $esc($so['guest_name']) ?></div>
                        </td>
                        <td class="py-3 px-3">
                          <div class="font-semibold text-[#0E1D33]"><?= $esc($so['service_name_snapshot']) ?> &times; <?= (int) $so['quantity'] ?></div>
                          <div class="text-[11px] text-[#A8843E]">
                            <?= $esc($grpLabel) ?> · <?= ($so['requested_by_role'] ?? 'customer') === 'receptionist' ? 'Tại quầy' : 'Khách đặt Web' ?>
                          </div>
                        </td>
                        <td class="py-3 px-3">
                          <div class="font-medium text-slate-700"><?= $esc($so['requested_time'] ?: 'Ngay bây giờ') ?></div>
                          <?php if (!empty($so['guest_note'])): ?>
                            <div class="mt-1 px-2 py-1 rounded bg-[#FAF7F0] border border-[#E6DFD1] text-[11px] text-slate-600"><?= $esc($so['guest_note']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="py-3 px-3 font-bold text-[#0E1D33] tabular-nums"><?= $fmt($so['total_price']) ?></td>
                        <td class="py-3 px-3">
                          <span class="px-2.5 py-1 rounded-full border text-[11px] font-semibold inline-block <?= $soCls ?>"><?= $soLabel ?></span>
                          <?php if (!empty($so['accepted_by_name'])): ?>
                            <div class="text-[10px] text-slate-500 mt-1">Lễ tân duyệt: <?= $esc($so['accepted_by_name']) ?></div>
                          <?php endif; ?>
                          <?php if (!empty($so['completed_by_name'])): ?>
                            <div class="text-[10px] text-emerald-700">Bếp/DV xong: <?= $esc($so['completed_by_name']) ?></div>
                          <?php endif; ?>
                          <?php if ($soSt === 'cancelled'): ?>
                            <div class="mt-1.5 px-2.5 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-[11px] text-rose-800">
                              <strong>Lý do hủy (gửi Bếp/DV):</strong> <?= $esc($soCancelReason !== '' ? $soCancelReason : 'Hủy trước khi phục vụ') ?>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                          <div class="srv-actions-reception flex flex-wrap items-center gap-1.5">
                            <?php if ($soSt === 'pending'): ?>
                              <form method="POST" action="/reception/service-orders/<?= $soId ?>/status" class="inline">
                                <input type="hidden" name="return_tab" value="services" />
                                <input type="hidden" name="status" value="accepted" />
                                <input type="hidden" name="actor_department" value="reception" />
                                <button type="submit" class="px-2.5 py-1 bg-[#0E1D33] text-white rounded-lg font-semibold whitespace-nowrap">Xác nhận thông tin khách</button>
                              </form>
                            <?php elseif ($soSt === 'accepted'): ?>
                              <span class="px-2.5 py-1 rounded-lg bg-sky-50 text-sky-800 border border-sky-200 text-[11px] font-medium">Chờ Bộ phận Bếp &amp; Dịch vụ hoàn thành</span>
                            <?php endif; ?>
                            <?php if (($soSt === 'pending' || $soSt === 'accepted') && empty($so['billed_to_invoice'])): ?>
                              <button type="button"
                                      onclick="openCancelServiceModal(<?= $soId ?>)"
                                      class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg font-semibold whitespace-nowrap">
                                Hủy
                              </button>
                            <?php endif; ?>
                            <?php if ($soSt === 'completed'): ?><span class="text-[11px] text-emerald-700 font-semibold">Đã chốt phục vụ</span><?php endif; ?>
                            <?php if ($soSt === 'cancelled'): ?><span class="text-[11px] text-rose-700 font-medium">Đã hủy</span><?php endif; ?>
                          </div>

                          <div class="srv-actions-department hidden flex-wrap items-center gap-1.5">
                            <?php if ($soSt === 'pending'): ?>
                              <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-medium">Chờ Lễ tân xác nhận thông tin khách</span>
                            <?php elseif ($soSt === 'accepted'): ?>
                              <form method="POST" action="/reception/service-orders/<?= $soId ?>/status" class="inline">
                                <input type="hidden" name="return_tab" value="services" />
                                <input type="hidden" name="status" value="completed" />
                                <input type="hidden" name="actor_department" value="kitchen_service" />
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold whitespace-nowrap">Xác nhận Hoàn thành</button>
                              </form>
                            <?php elseif ($soSt === 'completed'): ?>
                              <span class="text-[11px] text-emerald-700 font-semibold">Đã chốt phục vụ</span>
                            <?php else: ?>
                              <span class="text-[11px] text-rose-700 font-medium">Đã hủy · Không thực hiện</span>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ========================================================== -->
      <!-- 5. PAYMENTS & FOLIO MANAGEMENT -->
      <!-- ========================================================== -->
      <?php if ($tab === 'payments'):
        $recTotalFolio = 0.0;
        $recTotalPaid = 0.0;
        foreach ($bookingsList as $rbk) {
            if (($rbk['status'] ?? '') === 'cancelled') continue;
            $recTotalFolio += $computeFolioGrandTotal($rbk);
            $recTotalPaid += (float) ($rbk['total_paid'] ?? 0);
        }
        $recTotalReceivable = max(0.0, $recTotalFolio - $recTotalPaid);
        $txPendingCnt = 0; $txVerifiedCnt = 0; $txRejectedCnt = 0; $txPendingAmt = 0.0;
        foreach ($pendingPaymentsList as $tx) {
            $vs = (string) ($tx['verification_status'] ?? 'pending');
            if ($vs === 'verified') $txVerifiedCnt++;
            elseif ($vs === 'rejected') $txRejectedCnt++;
            else { $txPendingCnt++; $txPendingAmt += (float) ($tx['amount'] ?? 0); }
        }
      ?>
        <div class="space-y-6">
          <div>
            <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Thanh toán &amp; Đối soát Hóa đơn</h1>
            <p class="text-xs text-slate-500 mt-0.5">
              Đối soát chuyển khoản QR / sao kê giao dịch của khách hàng, kiểm tra công nợ chi tiết theo từng hóa đơn và thu tiền tại quầy.
            </p>
          </div>

          <!-- 4-Card Shift Reconciliation Summary -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-4 space-y-1">
              <div class="text-[11px] font-semibold text-slate-500">Tổng giá trị hóa đơn lưu trú</div>
              <div class="font-serif text-xl font-bold text-[#0E1D33] tabular-nums"><?= $fmt($recTotalFolio) ?></div>
              <div class="text-[11px] text-slate-500">Đã gồm phòng, DV phát sinh &amp; 13.4% thuế/phí</div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-4 space-y-1">
              <div class="text-[11px] font-semibold text-slate-500">Đã thu &amp; khớp doanh thu</div>
              <div class="font-serif text-xl font-bold text-emerald-700 tabular-nums"><?= $fmt($recTotalPaid) ?></div>
              <div class="text-[11px] text-slate-500 tabular-nums"><?= $txVerifiedCnt ?> giao dịch đã khớp sao kê / tiền mặt</div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-4 space-y-1">
              <div class="text-[11px] font-semibold text-slate-500">Chờ đối soát QR &amp; Chưa khớp</div>
              <div class="font-serif text-xl font-bold text-amber-700 tabular-nums"><?= $fmt($txPendingAmt) ?></div>
              <div class="text-[11px] text-slate-500 tabular-nums"><?= $txPendingCnt ?> chờ kiểm tra · <?= $txRejectedCnt ?> không khớp</div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-4 space-y-1">
              <div class="text-[11px] font-semibold text-slate-500">Công nợ còn phải thu</div>
              <div class="font-serif text-xl font-bold text-rose-700 tabular-nums"><?= $fmt($recTotalReceivable) ?></div>
              <div class="text-[11px] text-slate-500">Tổng phần chênh lệch cần thu thêm</div>
            </div>
          </div>

          <!-- Detailed Transaction & QR Reconciliation Table -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="font-serif text-xl font-semibold text-[#0E1D33]">
                  Đối soát giao dịch &amp; chuyển khoản (<?= $txPendingCnt ?> chờ kiểm tra)
                </h2>
                <p class="text-[11px] text-slate-500 mt-0.5">
                  Kiểm tra số bút toán ngân hàng (FT/Trace) và đối chiếu mức cọc / tổng hóa đơn trước khi xác nhận Đã nhận tiền. Bấm vào từng dòng hoặc <strong>Chi tiết đối soát</strong> để kiểm tra đầy đủ.
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <div class="inline-flex bg-[#FAF7F0] border border-[#E6DFD1] rounded-lg p-1 gap-1">
                  <button type="button" onclick="setTransferFilter('all')" data-tx-flt="all" class="tx-flt-btn px-2.5 py-1 rounded-md font-semibold whitespace-nowrap bg-[#0E1D33] text-white">Tất cả (<?= count($pendingPaymentsList) ?>)</button>
                  <button type="button" onclick="setTransferFilter('pending')" data-tx-flt="pending" class="tx-flt-btn px-2.5 py-1 rounded-md font-semibold whitespace-nowrap text-slate-600 hover:text-[#0E1D33]">Chờ đối soát (<?= $txPendingCnt ?>)</button>
                  <button type="button" onclick="setTransferFilter('verified')" data-tx-flt="verified" class="tx-flt-btn px-2.5 py-1 rounded-md font-semibold whitespace-nowrap text-slate-600 hover:text-[#0E1D33]">Đã khớp (<?= $txVerifiedCnt ?>)</button>
                  <button type="button" onclick="setTransferFilter('rejected')" data-tx-flt="rejected" class="tx-flt-btn px-2.5 py-1 rounded-md font-semibold whitespace-nowrap text-slate-600 hover:text-[#0E1D33]">Không khớp (<?= $txRejectedCnt ?>)</button>
                </div>
                <input type="text" id="transferSearchInput" oninput="filterTransfersTable()"
                       placeholder="Tìm mã GD, booking, khách..."
                       class="px-3 py-1.5 border border-slate-200 rounded-lg w-52" />
              </div>
            </div>

            <?php if (empty($pendingPaymentsList)): ?>
              <p class="text-xs text-slate-400 py-4">Không có giao dịch chuyển khoản nào cần đối soát.</p>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="border-b border-slate-200 text-[10px] font-bold uppercase text-slate-400">
                      <th class="py-2 pr-3">Mã GD / Thời gian</th>
                      <th class="py-2 px-3">Booking / Khách &amp; Công nợ đơn</th>
                      <th class="py-2 px-3">Loại thu / Phương thức &amp; Tham chiếu</th>
                      <th class="py-2 px-3 text-right">Số tiền GD</th>
                      <th class="py-2 px-3">Trạng thái đối soát</th>
                      <th class="py-2 pl-3">Thao tác đối soát</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <?php
                      $bookingByIdMap = [];
                      foreach ($bookingsList as $bkItem) {
                          $bookingByIdMap[(int) $bkItem['id']] = $bkItem;
                      }
                      foreach ($pendingPaymentsList as $p):
                      $pId = (int) $p['id'];
                      $pBkId = (int) ($p['booking_id'] ?? 0);
                      $linkedBk = $bookingByIdMap[$pBkId] ?? null;
                      $orderTotalVal = $linkedBk
                          ? $computeFolioGrandTotal($linkedBk)
                          : (float) ($p['invoice_total_amount'] ?? $p['total_estimated'] ?? 0);
                      $verifiedOnOrder = (float) ($p['booking_verified_paid'] ?? ($linkedBk['total_paid'] ?? 0));
                      $depReqVal = (float) ($p['deposit_required'] ?? ($linkedBk['deposit_required'] ?? 0));
                      $pVerif = (string) ($p['verification_status'] ?? 'pending');
                      $pVerifCls = $pVerif === 'verified' ? 'bg-emerald-50 text-emerald-700' : ($pVerif === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800');
                      $pVerifLbl = $pVerif === 'verified' ? 'Đã nhận tiền' : ($pVerif === 'rejected' ? 'Không khớp' : 'Chờ đối soát');
                      $payTypeLbl = ($p['payment_type'] ?? '') === 'deposit' ? 'Đặt cọc 30%' : (($p['payment_type'] ?? '') === 'settlement' ? 'Tất toán' : 'Thu bổ sung');
                      $matchHint = '';
                      if ($pVerif === 'pending') {
                          $pAmt = (float) ($p['amount'] ?? 0);
                          if ($orderTotalVal > 0 && $pAmt >= max(0, $orderTotalVal - $verifiedOnOrder)) {
                              $matchHint = 'Khớp tất toán đơn';
                          } elseif ($depReqVal > 0 && ($verifiedOnOrder + $pAmt) >= $depReqVal) {
                              $matchHint = 'Đủ điều kiện cọc 30%';
                          } else {
                              $matchHint = 'Thanh toán một phần';
                          }
                      }
                      $txSearch = mb_strtolower(($p['payment_code'] ?? '') . ' ' . ($p['booking_code'] ?? '') . ' ' . ($p['guest_name'] ?? '') . ' ' . ($p['reference_no'] ?? '') . ' ' . ($p['note'] ?? ''));
                    ?>
                      <tr onclick="selectInspectedPayment(<?= $pId ?>)" class="cursor-pointer hover:bg-[#FCFBF7] tx-row transition-colors" data-pid="<?= $pId ?>" data-verif="<?= $esc($pVerif) ?>" data-search="<?= $esc($txSearch) ?>">
                        <td class="py-2.5 pr-3">
                          <div class="font-mono font-bold text-[#0E1D33]"><?= $esc($p['payment_code']) ?></div>
                          <div class="text-[11px] text-slate-500 tabular-nums"><?= $esc(substr((string) ($p['recorded_at'] ?? ''), 0, 16)) ?></div>
                        </td>
                        <td class="py-2.5 px-3">
                          <div><strong class="font-mono"><?= $esc($p['booking_code']) ?></strong> · <?= $esc($p['guest_name']) ?></div>
                          <div class="text-[11px] text-slate-500 tabular-nums">
                            <?= !empty($p['room_number']) ? 'P.' . $esc($p['room_number']) : 'Chưa gán phòng' ?> · Tổng đơn: <strong class="text-[#0E1D33]"><?= $fmt($orderTotalVal) ?></strong> · Đã khớp: <strong class="text-emerald-700"><?= $fmt($verifiedOnOrder) ?></strong>
                          </div>
                        </td>
                        <td class="py-2.5 px-3">
                          <div class="flex flex-wrap items-center gap-1.5">
                            <span class="px-1.5 py-0.5 rounded bg-[#FAF7F0] border border-[#E6DFD1] text-[10px] font-bold text-[#0E1D33]"><?= $esc($payTypeLbl) ?></span>
                            <span><?= $esc($payMethodLabels[$p['method']] ?? $p['method']) ?></span>
                            <span class="font-mono text-slate-600">· <?= $esc($p['reference_no'] ?: '—') ?></span>
                          </div>
                          <?php if (!empty($p['note'])): ?>
                            <div class="text-[11px] text-slate-500 mt-0.5"><?= $esc($p['note']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="py-2.5 px-3 text-right tabular-nums">
                          <div class="font-bold text-[#0E1D33]"><?= $fmt($p['amount']) ?></div>
                          <?php if ($matchHint !== ''): ?>
                            <div class="text-[10px] font-semibold text-sky-700"><?= $esc($matchHint) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap <?= $pVerifCls ?>"><?= $pVerifLbl ?></span></td>
                        <td class="py-2.5 pl-3">
                          <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button" onclick="event.stopPropagation(); selectInspectedPayment(<?= $pId ?>)" data-inspect-btn="<?= $pId ?>" class="px-2.5 py-1 rounded text-[11px] font-semibold border border-slate-300 bg-white text-[#0E1D33] hover:border-[#0E1D33] whitespace-nowrap">Chi tiết đối soát</button>
                            <?php if ($pVerif === 'pending'): ?>
                              <button type="button" onclick="event.stopPropagation(); openVerifyPaymentModal(<?= $pId ?>, 'verified')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-semibold whitespace-nowrap">Đã nhận tiền</button>
                              <button type="button" onclick="event.stopPropagation(); openVerifyPaymentModal(<?= $pId ?>, 'rejected')" class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 rounded font-semibold whitespace-nowrap">Không khớp</button>
                            <?php endif; ?>
                            <?php if ($linkedBk): ?>
                              <button type="button" onclick="event.stopPropagation(); openInvoiceModal(<?= $pBkId ?>)" class="px-2 py-1 border border-slate-200 bg-white hover:border-[#0E1D33] text-[#0E1D33] rounded text-[11px] font-semibold whitespace-nowrap">Hóa đơn</button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- Detailed Reconciliation Inspector Panel -->
              <div id="reconcileInspectorPanel" class="mt-4"></div>
            <?php endif; ?>
          </div>

          <!-- Invoices & Collection Table -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="font-serif text-xl font-semibold text-[#0E1D33]">Hóa đơn &amp; Công nợ lưu trú</h2>
                <p class="text-[11px] text-slate-500 mt-0.5">Bấm Chi tiết để xem bảng kê đầy đủ hoặc Thu tiền để thanh toán toàn bộ / thanh toán riêng phần dịch vụ phát sinh.</p>
              </div>
              <div class="flex flex-wrap gap-2 text-xs">
                <input type="text" id="paymentSearchInput" oninput="filterPaymentsTable()"
                       placeholder="Tìm mã đơn, khách, phòng..."
                       class="px-3 py-1.5 border border-slate-200 rounded-lg" />
                <select id="paymentFilterSelect" onchange="filterPaymentsTable()"
                        class="px-3 py-1.5 border border-slate-200 rounded-lg bg-white">
                  <option value="all">Tất cả hóa đơn</option>
                  <option value="unpaid">Còn công nợ chưa thu</option>
                  <option value="checked_in">Đang lưu trú</option>
                  <option value="paid">Đã tất toán</option>
                </select>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[10px] font-bold uppercase text-slate-500">
                  <tr>
                    <th class="py-2.5 px-3">Mã / Phòng</th>
                    <th class="py-2.5 px-3">Khách hàng</th>
                    <th class="py-2.5 px-3 text-right">Tổng hóa đơn (gồm DV)</th>
                    <th class="py-2.5 px-3 text-right">Đã thu &amp; Đối soát</th>
                    <th class="py-2.5 px-3 text-right">Còn phải thu</th>
                    <th class="py-2.5 px-3">Thao tác</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($bookingsList as $b):
                    if (($b['status'] ?? '') === 'cancelled') continue;
                    $bId = (int) $b['id'];
                    $grandTotal = $computeFolioGrandTotal($b);
                    $paid = (float) ($b['total_paid'] ?? 0);
                    $rem = max(0.0, $grandTotal - $paid);
                    $inStaySrvCnt = 0;
                    foreach (($b['service_orders'] ?? []) as $soItem) {
                        if (($soItem['status'] ?? '') !== 'cancelled' && empty($soItem['is_prebooked'])) $inStaySrvCnt++;
                    }
                    $pendingTxCnt = 0; $rejectedTxCnt = 0;
                    foreach (($b['payments'] ?? []) as $pmItem) {
                        $pvs = (string) ($pmItem['verification_status'] ?? 'pending');
                        if ($pvs === 'pending') $pendingTxCnt++;
                        elseif ($pvs === 'rejected') $rejectedTxCnt++;
                    }
                    $pSearch = mb_strtolower($b['booking_code'] . ' ' . $b['guest_name'] . ' ' . ($b['assigned_room_number'] ?? ''));
                  ?>
                    <tr class="hover:bg-[#FCFBF7] payment-row"
                        data-status="<?= $esc($b['status']) ?>"
                        data-rem="<?= $rem > 0 ? 'unpaid' : 'paid' ?>"
                        data-search="<?= $esc($pSearch) ?>">
                      <td class="py-3 px-3">
                        <div class="font-mono font-bold text-[#0E1D33]"><?= $esc($b['booking_code']) ?></div>
                        <div class="text-[11px] text-[#A8843E] font-semibold"><?= !empty($b['assigned_room_number']) ? 'P.' . $esc($b['assigned_room_number']) : 'Chưa gán' ?></div>
                      </td>
                      <td class="py-3 px-3">
                        <div class="font-semibold text-[#0E1D33]"><?= $esc($b['guest_name']) ?></div>
                        <div class="text-[11px] text-slate-500 tabular-nums"><?= $esc($b['guest_phone']) ?></div>
                      </td>
                      <td class="py-3 px-3 text-right tabular-nums">
                        <div class="font-bold text-[#0E1D33]"><?= $fmt($grandTotal) ?></div>
                        <div class="text-[10px] text-slate-500"><?= (int) $b['nights'] ?> đêm<?= $inStaySrvCnt > 0 ? ' + ' . $inStaySrvCnt . ' DV' : '' ?> (gồm thuế)</div>
                      </td>
                      <td class="py-3 px-3 text-right tabular-nums">
                        <div class="font-semibold text-emerald-700"><?= $fmt($paid) ?></div>
                        <?php if ($pendingTxCnt > 0): ?><div class="text-[10px] text-amber-700 font-semibold"><?= $pendingTxCnt ?> GD chờ đối soát</div><?php endif; ?>
                        <?php if ($rejectedTxCnt > 0): ?><div class="text-[10px] text-rose-700 font-semibold"><?= $rejectedTxCnt ?> GD không khớp</div><?php endif; ?>
                      </td>
                      <td class="py-3 px-3 text-right font-bold tabular-nums">
                        <span class="<?= $rem > 0 ? 'text-rose-700' : 'text-emerald-700' ?>"><?= $fmt($rem) ?></span>
                      </td>
                      <td class="py-3 px-3">
                        <div class="flex gap-1.5">
                          <button type="button" onclick="openInvoiceModal(<?= $bId ?>)"
                                  class="px-2.5 py-1 border border-slate-300 bg-white rounded-lg font-semibold whitespace-nowrap">Chi tiết</button>
                          <button type="button" onclick="openCollectModal(<?= $bId ?>)"
                                  class="px-2.5 py-1 bg-[#C9A45C] text-[#0E1D33] rounded-lg font-bold whitespace-nowrap">Thu tiền</button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Refund Workflow Table -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 class="font-serif text-xl font-semibold text-[#0E1D33]">
                  Quản lý Hoàn tiền khi Hủy đặt phòng (<?= $pendingRefundsCount ?> chờ xử lý)
                </h2>
                <p class="text-[11px] text-slate-500 mt-0.5">
                  Phê duyệt yêu cầu hoàn cọc theo đúng số tiền đã đối soát thực tế, sau đó xác nhận giải ngân kèm mã bút toán chi.
                </p>
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[10px] font-bold uppercase text-slate-500">
                  <tr>
                    <th class="py-2.5 px-3">Mã hoàn / Đơn</th>
                    <th class="py-2.5 px-3">Khách hàng &amp; Lý do hủy</th>
                    <th class="py-2.5 px-3 text-right">Đã thu / Phí giữ / Số hoàn</th>
                    <th class="py-2.5 px-3">Trạng thái</th>
                    <th class="py-2.5 px-3">Thao tác hoàn tiền</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php if (empty($refundRequestsList)): ?>
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">Chưa có hồ sơ hoàn tiền nào.</td></tr>
                  <?php else: foreach ($refundRequestsList as $rf):
                    $rfId = (int) $rf['id'];
                    $rfSt = (string) ($rf['status'] ?? 'pending');
                    $rfCls = $rfSt === 'processed'
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : ($rfSt === 'approved'
                            ? 'bg-sky-50 text-sky-700 border-sky-200'
                            : ($rfSt === 'rejected'
                                ? 'bg-rose-50 text-rose-700 border-rose-200'
                                : 'bg-amber-50 text-amber-800 border-amber-200'));
                    $rfLbl = $rfSt === 'processed'
                        ? 'Đã hoàn tiền'
                        : ($rfSt === 'approved'
                            ? 'Đã duyệt · Chờ chi'
                            : ($rfSt === 'rejected' ? 'Từ chối hoàn' : 'Chờ phê duyệt'));
                  ?>
                    <tr class="hover:bg-[#FCFBF7]">
                      <td class="py-2.5 px-3 whitespace-nowrap">
                        <div class="font-mono font-bold text-[#0E1D33]"><?= $esc($rf['refund_code']) ?></div>
                        <div class="text-[11px] font-mono text-slate-500"><?= $esc($rf['booking_code']) ?></div>
                      </td>
                      <td class="py-2.5 px-3">
                        <div class="font-semibold text-[#0E1D33]"><?= $esc($rf['guest_name']) ?> · <span class="tabular-nums text-slate-500"><?= $esc($rf['guest_phone']) ?></span></div>
                        <div class="text-[11px] text-slate-600 mt-0.5"><?= $esc($rf['cancel_reason'] ?: 'Hủy đặt phòng') ?></div>
                      </td>
                      <td class="py-2.5 px-3 text-right tabular-nums whitespace-nowrap">
                        <div class="text-[11px] text-slate-500">Thu: <?= $fmt($rf['paid_verified_amount']) ?> · Giữ: <?= $fmt($rf['retained_fee']) ?></div>
                        <div class="font-bold text-emerald-700"><?= $fmt($rf['refund_amount']) ?></div>
                      </td>
                      <td class="py-2.5 px-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold <?= $rfCls ?>"><?= $rfLbl ?></span>
                        <?php if (!empty($rf['reference_no'])): ?>
                          <div class="text-[10px] font-mono text-slate-500 mt-0.5">Mã chi: <?= $esc($rf['reference_no']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="py-2.5 px-3 min-w-[270px]">
                        <?php if ($rfSt === 'pending'): ?>
                          <form method="POST" action="/reception/refunds/<?= $rfId ?>/decide" class="flex flex-wrap items-center gap-1.5">
                            <input type="hidden" name="return_tab" value="payments" />
                            <select name="refund_method" class="px-2 py-1 border border-slate-300 rounded bg-white text-[11px]">
                              <option value="bank_transfer">Chuyển khoản NH</option>
                              <option value="qr_bank">Chuyển khoản QR</option>
                              <option value="cash">Tiền mặt</option>
                              <option value="card">Hoàn qua thẻ</option>
                            </select>
                            <input type="text" name="decision_note" placeholder="Ghi chú duyệt / từ chối..." class="flex-1 min-w-[120px] px-2 py-1 border border-slate-300 rounded text-[11px]" />
                            <button type="submit" name="decision" value="approved" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-semibold whitespace-nowrap">Duyệt hoàn</button>
                            <button type="submit" name="decision" value="rejected" class="px-2.5 py-1 border border-rose-300 text-rose-700 hover:bg-rose-50 rounded font-semibold whitespace-nowrap">Từ chối</button>
                          </form>
                        <?php elseif ($rfSt === 'approved'): ?>
                          <form method="POST" action="/reception/refunds/<?= $rfId ?>/process" class="flex flex-wrap items-center gap-1.5">
                            <input type="hidden" name="return_tab" value="payments" />
                            <select name="refund_method" class="px-2 py-1 border border-slate-300 rounded bg-white text-[11px]">
                              <option value="bank_transfer" <?= ($rf['refund_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Chuyển khoản NH</option>
                              <option value="qr_bank" <?= ($rf['refund_method'] ?? '') === 'qr_bank' ? 'selected' : '' ?>>Chuyển khoản QR</option>
                              <option value="cash" <?= ($rf['refund_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Tiền mặt</option>
                              <option value="card" <?= ($rf['refund_method'] ?? '') === 'card' ? 'selected' : '' ?>>Hoàn qua thẻ</option>
                            </select>
                            <input type="text" name="reference_no" required placeholder="Mã bút toán chi (FT/PC)..." class="w-36 px-2 py-1 border border-slate-300 rounded font-mono text-[11px]" />
                            <button type="submit" class="px-2.5 py-1 bg-[#0E1D33] text-white rounded font-semibold whitespace-nowrap">Xác nhận đã chi</button>
                          </form>
                        <?php else: ?>
                          <span class="text-[11px] text-slate-500"><?= $esc($rf['decision_note'] ?: 'Đã hoàn tất xử lý') ?></span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ========================================================== -->
      <!-- 6. CUSTOMERS CRM DIRECTORY -->
      <!-- ========================================================== -->
      <?php if ($tab === 'customers'): ?>
        <div class="space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Hồ sơ Khách hàng</h1>
              <p class="text-xs text-slate-500 mt-0.5">
                Tổng hợp khách hàng đặt phòng trực tuyến (Hội viên) và khách đăng ký trực tiếp tại quầy cùng lịch sử lưu trú.
              </p>
            </div>
            <input type="text" id="customerSearchInput" oninput="filterCustomersTable()"
                   placeholder="Tìm tên khách, SĐT, email, CCCD..."
                   class="w-full sm:w-72 px-3.5 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
          </div>

          <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[10px] font-bold uppercase text-slate-500">
                  <tr>
                    <th class="py-3 px-4">Khách hàng</th>
                    <th class="py-3 px-3">Liên hệ / CCCD</th>
                    <th class="py-3 px-3">Hạng thẻ / Phân loại</th>
                    <th class="py-3 px-3 text-right">Số lần đặt</th>
                    <th class="py-3 px-3 text-right">Tổng chi tiêu</th>
                    <th class="py-3 px-4">Hồ sơ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($customersList as $idx => $c):
                    $cSearch = mb_strtolower($c['full_name'] . ' ' . $c['phone'] . ' ' . $c['email'] . ' ' . $c['guest_id_number']);
                  ?>
                    <tr class="hover:bg-[#FCFBF7] cust-row" data-search="<?= $esc($cSearch) ?>">
                      <td class="py-3 px-4 font-bold text-[#0E1D33]"><?= $esc($c['full_name']) ?></td>
                      <td class="py-3 px-3">
                        <div class="tabular-nums"><?= $esc($c['phone']) ?></div>
                        <div class="text-[11px] text-slate-500">
                          <?= $esc($c['email'] ?: '—') ?> <?= !empty($c['guest_id_number']) ? '· CCCD: ' . $esc($c['guest_id_number']) : '' ?>
                        </div>
                      </td>
                      <td class="py-3 px-3">
                        <span class="px-2 py-0.5 rounded bg-[#FAF7F0] border border-[#E6DFD1] text-[#A8843E] font-semibold"><?= $esc($c['membership_tier']) ?></span>
                      </td>
                      <td class="py-3 px-3 text-right font-semibold tabular-nums"><?= (int) $c['bookings_count'] ?></td>
                      <td class="py-3 px-3 text-right font-bold text-[#0E1D33] tabular-nums"><?= $fmt($c['total_spent']) ?></td>
                      <td class="py-3 px-4">
                        <button type="button" onclick="openCustomerModal(<?= $idx ?>)"
                                class="px-3 py-1 border border-slate-300 rounded-lg font-semibold hover:border-[#0E1D33]">
                          Lịch sử (<?= count($c['bookings']) ?>)
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ========================================================== -->
      <!-- 7. CONFERENCE INQUIRIES -->
      <!-- ========================================================== -->
      <?php if ($tab === 'conferences'): ?>
        <div class="space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Yêu cầu Hội nghị &amp; Khách đoàn</h1>
              <p class="text-xs text-slate-500 mt-0.5">Tiếp nhận và theo dõi tiến độ tư vấn đặt sảnh tiệc, phòng họp từ khách hàng trên website.</p>
            </div>
            <input type="text" id="confSearchInput" oninput="filterConferencesTable()"
                   placeholder="Tìm tên sảnh, người liên hệ, SĐT..."
                   class="w-full sm:w-72 px-3.5 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
          </div>

          <div class="flex flex-wrap gap-1.5 text-xs">
            <?php
            $confTabs = [
                'all'       => 'Tất cả (' . count($conferencesList) . ')',
                'new'       => 'Mới tiếp nhận',
                'contacted' => 'Đã liên hệ',
                'confirmed' => 'Đã chốt sảnh',
                'cancelled' => 'Đã hủy',
            ];
            foreach ($confTabs as $ck => $cl):
            ?>
              <button type="button" onclick="setConfFilter('<?= $ck ?>')" data-conf-tab="<?= $ck ?>"
                      class="conf-tab-btn px-3 py-1.5 rounded-lg font-semibold border whitespace-nowrap <?= $ck === 'all' ? 'bg-[#0E1D33] text-white border-[#0E1D33]' : 'bg-white text-slate-600 border-[#E6DFD1] hover:border-[#0E1D33]' ?>">
                <?= $cl ?>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[10px] font-bold uppercase text-slate-500">
                  <tr>
                    <th class="py-3 px-4">Sảnh / Phòng họp</th>
                    <th class="py-3 px-3">Người liên hệ</th>
                    <th class="py-3 px-3">Ngày tổ chức</th>
                    <th class="py-3 px-3 text-right">Số khách</th>
                    <th class="py-3 px-3">Ghi chú yêu cầu</th>
                    <th class="py-3 px-3">Trạng thái</th>
                    <th class="py-3 px-4 text-right">Cập nhật xử lý</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php if (empty($conferencesList)): ?>
                    <tr><td colspan="7" class="py-10 text-center text-slate-400">Chưa có yêu cầu hội nghị nào.</td></tr>
                  <?php else: ?>
                    <?php foreach ($conferencesList as $ci):
                      $ciId = (int) $ci['id'];
                      $ciSt = (string) ($ci['status'] ?? 'new');
                      $ciLbl = $ciSt === 'new' ? 'Mới tiếp nhận' : ($ciSt === 'contacted' ? 'Đã liên hệ' : ($ciSt === 'confirmed' ? 'Đã chốt sảnh' : 'Đã hủy'));
                      $ciCls = $ciSt === 'new' ? 'text-amber-800 bg-amber-50 border-amber-200'
                          : ($ciSt === 'contacted' ? 'text-sky-800 bg-sky-50 border-sky-200'
                          : ($ciSt === 'confirmed' ? 'text-emerald-800 bg-emerald-50 border-emerald-200' : 'text-slate-600 bg-slate-100 border-slate-200'));
                      $ciSearch = mb_strtolower($ci['venue_name'] . ' ' . $ci['contact_name'] . ' ' . $ci['contact_phone'] . ' ' . ($ci['notes'] ?? ''));
                    ?>
                      <tr class="hover:bg-[#FCFBF7] conf-row" data-status="<?= $esc($ciSt) ?>" data-search="<?= $esc($ciSearch) ?>">
                        <td class="py-3 px-4 font-bold text-[#0E1D33]">
                          <div><?= $esc($ci['venue_name']) ?></div>
                          <div class="text-[11px] font-normal text-slate-400 tabular-nums">Gửi lúc <?= $esc(substr((string) ($ci['created_at'] ?? ''), 0, 16)) ?></div>
                        </td>
                        <td class="py-3 px-3">
                          <div class="font-semibold text-[#0E1D33]"><?= $esc($ci['contact_name']) ?></div>
                          <div class="text-[11px] text-slate-500 tabular-nums">
                            <?= $esc($ci['contact_phone']) ?><?= !empty($ci['contact_email']) ? ' · ' . $esc($ci['contact_email']) : '' ?>
                          </div>
                        </td>
                        <td class="py-3 px-3 tabular-nums font-medium text-[#0E1D33] whitespace-nowrap"><?= $esc(date('d/m/Y', strtotime($ci['event_date']))) ?></td>
                        <td class="py-3 px-3 text-right font-semibold tabular-nums"><?= (int) $ci['guest_count'] ?> khách</td>
                        <td class="py-3 px-3 text-slate-600 max-w-xs"><?= $esc($ci['notes'] ?: '—') ?></td>
                        <td class="py-3 px-3"><span class="px-2 py-0.5 rounded border text-[11px] font-semibold whitespace-nowrap <?= $ciCls ?>"><?= $ciLbl ?></span></td>
                        <td class="py-3 px-4 text-right">
                          <div class="inline-flex items-center gap-1.5">
                            <?php if ($ciSt === 'new'): ?>
                              <form method="POST" action="/reception/conferences/<?= $ciId ?>/status" class="inline">
                                <input type="hidden" name="return_tab" value="conferences" />
                                <input type="hidden" name="status" value="contacted" />
                                <button type="submit" class="px-2.5 py-1 bg-[#0E1D33] text-white rounded-lg font-semibold whitespace-nowrap">Đã liên hệ</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($ciSt === 'new' || $ciSt === 'contacted'): ?>
                              <form method="POST" action="/reception/conferences/<?= $ciId ?>/status" class="inline">
                                <input type="hidden" name="return_tab" value="conferences" />
                                <input type="hidden" name="status" value="confirmed" />
                                <button type="submit" class="px-2.5 py-1 bg-[#C9A45C] text-[#0E1D33] rounded-lg font-bold whitespace-nowrap">Chốt sảnh</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($ciSt !== 'cancelled' && $ciSt !== 'confirmed'): ?>
                              <form method="POST" action="/reception/conferences/<?= $ciId ?>/status" class="inline">
                                <input type="hidden" name="return_tab" value="conferences" />
                                <input type="hidden" name="status" value="cancelled" />
                                <button type="submit" class="px-2.5 py-1 border border-slate-300 text-slate-600 rounded-lg font-semibold whitespace-nowrap">Hủy</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- ====================================================================== -->
<!-- MODALS (100% Parity with React ReceptionModals & ReceptionCheckInOut) -->
<!-- ====================================================================== -->

<!-- 1. WALK-IN MODAL -->
<div id="walkInModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-[#E6DFD1] my-6 max-h-[92vh] overflow-y-auto space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 class="font-serif text-2xl font-semibold text-[#0E1D33]">Tạo đặt phòng tại quầy</h3>
        <p class="text-xs text-slate-500 mt-0.5">Nhập số điện thoại trước: khách cũ sẽ được điền sẵn. Danh sách phòng ưu tiên phòng không trùng lịch.</p>
      </div>
      <button type="button" onclick="closeModal('walkInModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <form method="POST" action="/reception/walk-in" class="space-y-3.5 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Số điện thoại *</label>
          <input type="tel" required name="guest_phone" id="wiPhone" oninput="lookupWalkInCustomer(this.value)" placeholder="VD: 0912345678"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg tabular-nums" />
          <div id="wiLookupMsg" class="text-[11px] text-emerald-700 font-semibold mt-1 hidden"></div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Họ tên khách *</label>
          <input type="text" required name="guest_name" id="wiName" placeholder="Nhập họ tên khách"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Email</label>
          <input type="email" name="guest_email" id="wiEmail" placeholder="khach@gmail.com"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Giấy tờ (CCCD / Hộ chiếu)</label>
          <input type="text" name="guest_id_number" id="wiIdNum" placeholder="Số CCCD / Hộ chiếu"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg tabular-nums" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Hạng phòng *</label>
          <select name="category_id" id="wiCatId" onchange="syncWalkInCatalog()"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
          <div id="wiStopSellNote" class="text-[11px] text-amber-700 font-semibold mt-1 hidden"></div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Gói giá *</label>
          <select name="package_id" id="wiPkgId" onchange="recalcWalkInPricing()"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Nhận phòng *</label>
          <input type="date" required name="check_in_date" id="wiCheckIn" min="<?= $esc($todayStr) ?>" onchange="syncWalkInDates()"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Trả phòng *</label>
          <input type="date" required name="check_out_date" id="wiCheckOut" min="<?= $esc(date('Y-m-d', strtotime($todayStr . ' +1 day'))) ?>" onchange="syncWalkInRoomsAndPricing()"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Người lớn</label>
          <input type="number" name="adults" id="wiAdults" min="1" max="10" value="2" oninput="validateWalkInCapacity()" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Trẻ em</label>
          <input type="number" name="children" id="wiChildren" min="0" max="10" value="0" oninput="validateWalkInCapacity()" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Mã ưu đãi</label>
          <select name="voucher_code" id="wiVoucher" onchange="recalcWalkInPricing()"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <option value="">Mặc định (Không áp dụng)</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Phòng cụ thể</label>
          <select name="assigned_room_id" id="wiRoomId" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">
            Tiền thu ngay (cọc / thanh toán) <span id="wiDepLabel" class="text-[#A8843E]"></span>
          </label>
          <input type="number" name="deposit_amount" id="wiDepositAmount" min="0" step="1000" oninput="updateWalkInDepHint()"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold tabular-nums" />
          <div id="wiDepHint" class="text-[11px] mt-1"></div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Phương thức thu *</label>
          <select name="payment_method" id="wiPayMethod" onchange="syncWalkInRoomsAndPricing()" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <option value="cash">Tiền mặt tại quầy</option>
            <option value="card">Thẻ / Máy POS</option>
            <option value="qr_bank">Chuyển khoản QR (Chờ đối soát)</option>
          </select>
        </div>
      </div>

      <label class="flex items-center gap-2 pt-1 font-medium text-[#0E1D33] cursor-pointer">
        <input type="checkbox" name="immediate_checkin" value="1" id="wiImmediateCheckIn" checked onchange="syncWalkInRoomsAndPricing()" class="rounded border-slate-300" />
        <span>Nhận phòng ngay (khách đến hôm nay, phòng sạch — chỉ áp dụng Tiền mặt / Thẻ POS)</span>
      </label>
      <div id="wiQrNote" class="hidden p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[11px]">
        Lưu ý: Chuyển khoản QR cần được kiểm tra sao kê ở mục <strong>Thanh toán &rarr; Đối soát</strong> trước khi nhận phòng.
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Ghi chú</label>
        <textarea name="special_requests" rows="2" placeholder="Yêu cầu đặc biệt của khách (giờ đến, tầng cao, giường phụ...)"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>

      <div id="wiCapWarn" class="hidden p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-semibold"></div>
      <div id="wiPricingSummary" class="p-3.5 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] space-y-1"></div>

      <div class="flex items-center justify-end gap-2.5 pt-2">
        <button type="button" onclick="closeModal('walkInModal')" class="px-5 py-2.5 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" id="wiSubmitBtn" class="px-6 py-2.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase disabled:opacity-40 disabled:cursor-not-allowed">TẠO ĐƠN</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. ASSIGN ROOM MODAL -->
<div id="assignRoomModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between">
      <div>
        <h3 id="assignModalTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Gán phòng</h3>
        <p id="assignModalSub" class="text-xs text-slate-500 mt-1 tabular-nums"></p>
      </div>
      <button type="button" onclick="closeModal('assignRoomModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>
    <form id="assignRoomForm" method="POST" action="" class="space-y-4 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <div id="assignRoomLoading" class="py-3 text-slate-500 hidden">Đang kiểm tra phòng trống...</div>
      <select name="assigned_room_id" id="assignRoomSelect" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl bg-white text-sm font-medium text-[#0E1D33]"></select>
      <div id="assignRoomEmpty" class="hidden p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-700">
        Không còn phòng trống nào thuộc hạng phòng này trong khoảng ngày đã đặt.
      </div>
      <div class="flex items-center gap-2.5 pt-1">
        <button type="button" onclick="closeModal('assignRoomModal')" class="px-5 py-2.5 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" id="assignSubmitBtn" class="px-6 py-2.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase">LƯU</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. CHECK-IN MODAL -->
<div id="checkInModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-[#E6DFD1] my-6 space-y-4">
    <div class="flex items-start justify-between">
      <div>
        <h3 id="ciTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Kiểm tra Đặt cọc &amp; Gán phòng</h3>
        <p class="text-xs text-slate-600 mt-1 leading-relaxed">
          Theo quy định nghiệp vụ Mường Thanh, khách hàng <strong>luôn phải đặt cọc tối thiểu 30% và được gán số phòng cụ thể</strong> trước khi xác nhận Nhận phòng (Check-in).
        </p>
      </div>
      <button type="button" onclick="closeModal('checkInModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div id="ciDepositBox" class="p-4 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] space-y-2 text-xs"></div>
    <div id="ciPrepNoteBox" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1"></div>

    <form id="checkInForm" method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <input type="hidden" name="deposit_topup_amount" id="ciTopupAmount" value="0" />
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Gán phòng nhận khách *</label>
        <select name="room_id" id="ciRoomSelect" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white font-medium text-[#0E1D33]"></select>
        <div id="ciAssignWarn" class="hidden p-3 mt-1 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-[11px]"></div>
        <div id="ciRoomEmpty" class="hidden p-3 mt-1 bg-rose-50 border border-rose-200 rounded-lg text-rose-700">
          Chưa có phòng trống &amp; đã dọn sạch thuộc hạng phòng này. Vui lòng nghiệm thu dọn phòng trước!
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Số CCCD / Hộ chiếu *</label>
          <input type="text" required name="guest_id_number" id="ciGuestIdNum" placeholder="Nhập CCCD / Hộ chiếu"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg tabular-nums" />
        </div>
        <div id="ciTopupMethodWrap" class="hidden">
          <label class="block font-semibold text-slate-700 mb-1">Phương thức thu cọc tại quầy *</label>
          <select name="deposit_topup_method" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <option value="cash">Tiền mặt tại quầy</option>
            <option value="card">Thẻ / Máy POS</option>
          </select>
          <div class="text-[10px] text-slate-500 mt-0.5">Nếu khách chuyển khoản QR, vui lòng thu &amp; đối soát ở mục Thanh toán trước khi Check-in.</div>
        </div>
      </div>

      <div id="ciTopupRefWrap" class="hidden">
        <label class="block font-semibold text-slate-700 mb-1">Mã tham chiếu thu cọc (nếu có)</label>
        <input type="text" name="deposit_reference" placeholder="Mã biên lai / Số bill POS..." class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
      </div>

      <div class="flex items-center gap-2.5 pt-2">
        <button type="button" onclick="closeModal('checkInModal')" class="px-5 py-2.5 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" id="ciSubmitBtn" class="flex-1 px-5 py-2.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase">
          XÁC NHẬN NHẬN PHÒNG
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 4. MODIFY STAY MODAL (EXTEND / CHANGE ROOM) -->
<div id="modifyStayModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between">
      <div>
        <h3 id="msTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Gia hạn lưu trú</h3>
        <p id="msSub" class="text-xs text-slate-500 mt-0.5"></p>
      </div>
      <button type="button" onclick="closeModal('modifyStayModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div class="grid grid-cols-2 gap-2 text-xs">
      <button type="button" id="msTabExtend" onclick="switchModifyMode('extend_stay')"
              class="py-2 px-3 rounded-lg font-bold border bg-[#0E1D33] text-white border-[#0E1D33]">Gia hạn ngày / giờ</button>
      <button type="button" id="msTabChange" onclick="switchModifyMode('change_room')"
              class="py-2 px-3 rounded-lg font-bold border bg-[#FAF7F0] text-[#0E1D33] border-[#E6DFD1]">Đổi sang phòng khác</button>
    </div>

    <form id="modifyStayForm" method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <input type="hidden" name="action" id="msAction" value="extend_stay" />

      <div id="msExtendFields" class="space-y-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Ngày trả mới *</label>
          <input type="date" name="new_check_out_date" id="msNewCheckOut" min="<?= $esc($todayStr) ?>" onchange="recalcModifyPreview()" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Thời gian / Giờ trả phòng cụ thể *</label>
          <select name="checkout_time_slot" id="msTimeSlot" onchange="recalcModifyPreview()" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <option value="12:00">12:00 trưa (Giờ trả phòng tiêu chuẩn)</option>
            <option value="15:00">15:00 chiều (Trả phòng muộn +30% giá phòng)</option>
            <option value="18:00">18:00 tối (Trả phòng muộn +50% giá phòng)</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Gán / Giữ phòng lưu trú khi gia hạn</label>
          <select name="new_room_id" id="msExtRoomSelect" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
        </div>
        <div id="msFeePreview" class="p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] flex justify-between items-center"></div>
      </div>

      <div id="msChangeRoomFields" class="hidden space-y-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Chọn phòng mới (đã dọn sạch) *</label>
          <select name="new_room_id" id="msChangeRoomSelect" disabled onchange="recalcModifyChangeRoomPreview()" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
        </div>
        <div id="msChangePreview" class="hidden p-3 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] text-[11px] text-slate-700"></div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Lý do *</label>
        <input type="text" required name="reason" id="msReason" placeholder="Nhập lý do gia hạn / đổi phòng..." class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
      </div>

      <div class="flex items-center gap-2.5 pt-2">
        <button type="button" onclick="closeModal('modifyStayModal')" class="px-5 py-2.5 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" class="px-6 py-2.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase">LƯU</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. CHECK-OUT MODAL -->
<div id="checkOutModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-[#E6DFD1] my-6 max-h-[92vh] overflow-y-auto space-y-5">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3.5">
      <div>
        <h3 id="coTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Tất toán &amp; Trả phòng</h3>
        <p id="coSub" class="text-xs text-slate-600 mt-1 leading-relaxed">
          Thực hiện lần lượt các bước dưới đây để chốt hóa đơn chính xác và giải phóng phòng.
        </p>
      </div>
      <button type="button" onclick="closeModal('checkOutModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div class="space-y-2">
      <div class="flex items-center justify-between gap-2 text-xs">
        <span class="font-bold text-[#0E1D33]">Bước 1. Kiểm tra bảng kê chi phí đã tính vào hóa đơn</span>
        <span class="text-[11px] text-slate-500">Chỉ cộng tiền phòng, phụ phí &amp; dịch vụ ĐÃ hoàn thành</span>
      </div>
      <div id="coFolioBox" class="p-4 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] space-y-2 text-xs"></div>
    </div>

    <form id="checkOutForm" method="POST" action="" class="space-y-3.5 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <input type="hidden" name="settlement_amount" id="coSettlementAmt" value="0" />

      <div id="coPendingSrvWarn" class="hidden p-4 rounded-xl bg-amber-50 border border-amber-200 space-y-3">
        <div>
          <div id="coPendingSrvTitle" class="font-bold text-amber-950"></div>
          <p class="text-[11px] text-amber-900 mt-0.5 leading-relaxed">
            Các yêu cầu sau chưa được Bộ phận Bếp &amp; Dịch vụ bấm <strong>Hoàn thành</strong> nên hiện CHƯA có trong tổng tiền ở Bước 1:
          </p>
        </div>
        <div id="coPendingSrvList" class="bg-white/80 border border-amber-200 rounded-lg p-2.5 space-y-1 text-[11px] text-[#0E1D33]"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-amber-950">
          <div class="p-2.5 rounded-lg bg-white/70 border border-amber-200/80">
            <strong>Cách 1 (Khách ĐÃ dùng dịch vụ):</strong> Bấm ĐÓNG, sang tab <strong>Dịch vụ &rarr; Bộ phận Bếp &amp; DV</strong> đánh dấu Hoàn thành rồi mở lại để thu tiền.
          </div>
          <div class="p-2.5 rounded-lg bg-white/70 border border-amber-200/80">
            <strong>Cách 2 (Khách KHÔNG dùng nữa):</strong> Tích chọn bên dưới và nhập lý do hủy gửi về Bếp &amp; Dịch vụ trước khi trả phòng.
          </div>
        </div>
        <label class="flex items-start gap-2 font-semibold text-[#0E1D33] cursor-pointer pt-0.5">
          <input type="checkbox" name="cancel_uncompleted_services" value="1" id="coCancelPendingChk" onchange="toggleCoCancelReason(); updateCheckOutSubmitState();" class="mt-0.5 rounded border-amber-400" />
          <span>Hủy các dịch vụ chưa phục vụ này (không tính tiền vào hóa đơn)</span>
        </label>
        <div id="coCancelPendingReasonWrap" class="hidden space-y-2 pt-1">
          <div class="flex flex-wrap gap-1.5">
            <button type="button" onclick="setCoCancelPreset('Khách trả phòng sớm không sử dụng')" class="px-2.5 py-1 rounded-md bg-white border border-amber-300 text-[11px] text-amber-950 hover:border-[#0E1D33]">Khách trả phòng sớm không sử dụng</button>
            <button type="button" onclick="setCoCancelPreset('Khách đổi ý hủy trước khi phục vụ')" class="px-2.5 py-1 rounded-md bg-white border border-amber-300 text-[11px] text-amber-950 hover:border-[#0E1D33]">Khách đổi ý hủy trước khi phục vụ</button>
            <button type="button" onclick="setCoCancelPreset('Bếp / Spa quá tải không kịp phục vụ')" class="px-2.5 py-1 rounded-md bg-white border border-amber-300 text-[11px] text-amber-950 hover:border-[#0E1D33]">Bếp / Spa quá tải không kịp phục vụ</button>
          </div>
          <div>
            <label class="block font-semibold text-[#0E1D33] mb-1">Lý do hủy gửi tới Bộ phận Bếp &amp; Dịch vụ * (tối thiểu 3 ký tự)</label>
            <input type="text" name="cancel_services_reason" id="coCancelServicesReason" oninput="updateCheckOutSubmitState()"
                   placeholder="VD: Khách trả phòng sớm, không kịp phục vụ món ăn..."
                   class="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-xs" />
          </div>
        </div>
      </div>

      <h4 id="coStep3Title" class="text-xs font-bold text-[#0E1D33]">Bước 2. Nhập phụ phí phát sinh (nếu có) &amp; xác nhận tất toán</h4>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Phụ phí minibar / hư hỏng (chưa thuế)</label>
          <input type="number" name="surcharges" id="coSurcharges" min="0" step="1000" value="0" oninput="recalcCheckOutFolio()"
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg tabular-nums" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Ghi chú phụ phí (in lên hóa đơn)</label>
          <input type="text" name="surcharge_note" id="coSurchargeNote" placeholder="VD: 2 nước suối minibar, làm mất thẻ từ..."
                 class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
      </div>

      <div id="coCollectBox" class="hidden p-4 rounded-xl bg-[#FAF7F0] border border-[#C9A45C] space-y-2.5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label id="coPayMethodLbl" class="block font-bold text-[#0E1D33] mb-1">Hình thức thu số tiền còn lại *</label>
            <select name="settlement_method" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white font-semibold text-[#0E1D33]">
              <option value="cash">Tiền mặt tại quầy</option>
              <option value="card">Thẻ / Máy POS</option>
            </select>
            <div class="text-[10px] text-slate-500 mt-0.5">Nếu thu qua Chuyển khoản QR, hãy thu &amp; đối soát ở mục Thanh toán trước khi Trả phòng.</div>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Mã tham chiếu / Số bill POS</label>
            <input type="text" name="settlement_reference" placeholder="VD: POS-9921 hoặc mã sao kê QR" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
          </div>
        </div>
        <label class="flex items-center gap-2 font-semibold text-[#0E1D33] cursor-pointer pt-1">
          <input type="checkbox" id="coCollectedChk" onchange="updateCheckOutSubmitState()" class="rounded border-slate-400" />
          <span id="coCollectedLbl">Tôi xác nhận đã thu đủ số tiền còn lại của hóa đơn này</span>
        </label>
      </div>

      <div id="coPaidOkBox" class="hidden p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold">
        Hóa đơn đã được thanh toán đủ trước đó (0 đ còn lại). Có thể xác nhận trả phòng ngay.
      </div>

      <div class="border border-slate-200 rounded-xl p-3 space-y-2">
        <label class="flex items-center gap-2 font-semibold text-[#0E1D33] cursor-pointer">
          <input type="checkbox" id="coHasIncChk" onchange="toggleCheckOutIncident(this.checked)" class="rounded border-slate-300" />
          <span>Ghi nhận phản ánh / sự cố phòng từ khách khi trả phòng (nếu có)</span>
        </label>
        <div id="coIncFields" class="hidden grid grid-cols-1 gap-2 pt-1">
          <textarea name="incident_content" id="coIncContent" rows="2" placeholder="Nội dung khách phản ánh hoặc sự cố phát hiện..."
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
          <div class="grid grid-cols-2 gap-2">
            <select name="incident_severity" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
              <option value="LOW">Mức độ: Thấp</option>
              <option value="MEDIUM">Mức độ: Trung bình</option>
              <option value="HIGH">Mức độ: Nghiêm trọng</option>
            </select>
            <input type="text" name="incident_resolution" placeholder="Phương án xử lý đề xuất..." class="px-3 py-2 border border-slate-300 rounded-lg" />
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2.5 pt-2">
        <button type="button" onclick="closeModal('checkOutModal')" class="px-5 py-2.5 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase whitespace-nowrap">ĐÓNG</button>
        <button type="submit" id="coSubmitBtn" class="flex-1 px-6 py-2.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase disabled:opacity-40 whitespace-nowrap">
          XÁC NHẬN THANH TOÁN &amp; TRẢ PHÒNG
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 6. BOOKING DETAIL / EDIT MODAL -->
<div id="detailModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-3xl w-full p-6 shadow-2xl border border-[#E6DFD1] my-6 max-h-[92vh] overflow-y-auto space-y-5">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 id="dtTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Chi tiết &amp; Sửa thông tin</h3>
        <p id="dtSub" class="text-xs text-slate-500 mt-0.5"></p>
      </div>
      <button type="button" onclick="closeModal('detailModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <form id="detailForm" method="POST" action="" class="space-y-4 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Họ tên khách *</label>
          <input type="text" required name="guest_name" id="dtName" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Số điện thoại *</label>
          <input type="tel" required name="guest_phone" id="dtPhone" class="w-full px-3 py-2 border border-slate-300 rounded-lg tabular-nums" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Email</label>
          <input type="email" name="guest_email" id="dtEmail" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">CCCD / Hộ chiếu</label>
          <input type="text" name="guest_id_number" id="dtIdNum" class="w-full px-3 py-2 border border-slate-300 rounded-lg tabular-nums" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Giờ dự kiến đến</label>
          <input type="text" name="arrival_time" id="dtArrival" placeholder="14:00" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Mã chuyến bay</label>
          <input type="text" name="flight_number" id="dtFlight" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Nhận phòng</label>
          <input type="date" name="check_in_date" id="dtCheckIn" min="<?= $esc($todayStr) ?>" onchange="syncDetailDates()" class="w-full px-3 py-2 border border-slate-300 rounded-lg disabled:bg-slate-50" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Trả phòng</label>
          <input type="date" name="check_out_date" id="dtCheckOut" min="<?= $esc(date('Y-m-d', strtotime($todayStr . ' +1 day'))) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg disabled:bg-slate-50" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Người lớn</label>
          <input type="number" name="adults" id="dtAdults" min="1" oninput="validateDetailCapacity()" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Trẻ em</label>
          <input type="number" name="children" id="dtChildren" min="0" oninput="validateDetailCapacity()" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
      </div>

      <div id="dtCapWarn" class="hidden p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-semibold"></div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Yêu cầu đặc biệt / Ghi chú phòng</label>
        <textarea name="special_requests" id="dtRequests" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>

      <label id="dtSyncWrap" class="hidden flex items-center gap-2 text-slate-700 cursor-pointer">
        <input type="checkbox" name="sync_customer_profile" value="1" checked class="rounded border-slate-300" />
        <span>Đồng bộ họ tên &amp; SĐT lên tài khoản Hội viên khách hàng</span>
      </label>

      <div id="dtHistorySection" class="hidden pt-3 border-t border-[#EFE9DC] space-y-2">
        <div id="dtHistoryTitle" class="text-[11px] font-bold uppercase tracking-wider text-[#A8843E]"></div>
        <div id="dtHistoryList" class="max-h-40 overflow-y-auto space-y-1.5 pr-1"></div>
      </div>

      <div class="flex justify-end gap-2.5 pt-2">
        <button type="button" onclick="closeModal('detailModal')" class="px-5 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" id="dtSubmitBtn" class="px-6 py-2 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase disabled:opacity-40 disabled:cursor-not-allowed">LƯU THAY ĐỔI</button>
      </div>
    </form>
  </div>
</div>

<!-- 7. DESK SERVICE MODAL -->
<div id="deskServiceModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-[#E6DFD1] my-6 space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 class="font-serif text-2xl font-semibold text-[#0E1D33]">Đăng ký dịch vụ tại quầy</h3>
        <p class="text-xs text-slate-500 mt-0.5">Đơn đăng ký tại quầy được xác nhận ngay và cộng trực tiếp vào hóa đơn phòng (đã tách 5% phí + 8% VAT).</p>
      </div>
      <button type="button" onclick="closeModal('deskServiceModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <form method="POST" action="/reception/service-orders" class="space-y-4 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Chọn phòng / Booking khách *</label>
        <select name="booking_id" id="dsBookingId" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1.5">Nhóm dịch vụ chính</label>
        <div class="grid grid-cols-2 gap-2" id="dsMainGroupBtns">
          <button type="button" onclick="setDeskMainGroup('spa')" data-ds-main="spa" class="ds-main-btn px-3 py-2 rounded-lg border text-left font-semibold bg-[#0E1D33] text-white border-[#0E1D33]">Spa &amp; Trị liệu</button>
          <button type="button" onclick="setDeskMainGroup('dining')" data-ds-main="dining" class="ds-main-btn px-3 py-2 rounded-lg border text-left font-semibold bg-[#FAF7F0] text-[#0E1D33] border-[#E6DFD1]">Ăn uống &amp; Nhà hàng</button>
          <button type="button" onclick="setDeskMainGroup('utilities')" data-ds-main="utilities" class="ds-main-btn px-3 py-2 rounded-lg border text-left font-semibold bg-[#FAF7F0] text-[#0E1D33] border-[#E6DFD1]">Tiện ích &amp; Chuẩn bị</button>
          <button type="button" onclick="setDeskMainGroup('experience')" data-ds-main="experience" class="ds-main-btn px-3 py-2 rounded-lg border text-left font-semibold bg-[#FAF7F0] text-[#0E1D33] border-[#E6DFD1]">Trải nghiệm &amp; Combo</button>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2">
          <label class="block font-semibold text-slate-700 mb-1">Dịch vụ / Món cụ thể *</label>
          <select name="service_id" id="dsServiceId" required onchange="recalcDeskServicePrice()" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Số lượng *</label>
          <input type="number" name="quantity" id="dsQty" min="1" max="20" value="1" oninput="recalcDeskServicePrice()" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold tabular-nums" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Thời gian phục vụ</label>
          <input type="text" name="requested_time" value="Ngay bây giờ" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Ghi chú cho Bếp / Spa / Buồng</label>
          <input type="text" name="guest_note" placeholder="Ít cay, kỹ thuật viên nữ..." class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
      </div>

      <div id="dsPriceSummary" class="p-3.5 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] space-y-1"></div>

      <div class="flex justify-end gap-2.5 pt-2">
        <button type="button" onclick="closeModal('deskServiceModal')" class="px-5 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" class="px-6 py-2 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase">XÁC NHẬN ĐĂNG KÝ</button>
      </div>
    </form>
  </div>
</div>

<!-- 7B. CANCEL SERVICE ORDER MODAL (MANDATORY REASON FOR KITCHEN/SERVICE) -->
<div id="cancelServiceModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 id="csTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Hủy yêu cầu dịch vụ</h3>
        <p id="csSub" class="text-xs text-slate-500 mt-0.5"></p>
      </div>
      <button type="button" onclick="closeModal('cancelServiceModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-950 leading-relaxed">
      Lý do hủy sẽ được lưu trực tiếp lên phiếu và hiển thị tại màn hình <strong>Bộ phận Bếp &amp; Dịch vụ</strong> để bếp/spa dừng chế biến kịp thời, đồng thời gửi thông báo cho khách hàng.
    </div>

    <div class="space-y-1.5">
      <div class="text-[11px] font-semibold text-slate-500">Chọn nhanh lý do thường gặp:</div>
      <div class="flex flex-wrap gap-1.5">
        <button type="button" onclick="setCsPreset('Khách yêu cầu hủy trước khi chế biến / phục vụ')" class="px-2.5 py-1 rounded-md bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#0E1D33] text-[11px] text-[#0E1D33]">Khách yêu cầu hủy trước khi chế biến</button>
        <button type="button" onclick="setCsPreset('Hết nguyên liệu / quá tải giờ cao điểm')" class="px-2.5 py-1 rounded-md bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#0E1D33] text-[11px] text-[#0E1D33]">Hết nguyên liệu / quá tải</button>
        <button type="button" onclick="setCsPreset('Khách đổi sang món / khung giờ khác')" class="px-2.5 py-1 rounded-md bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#0E1D33] text-[11px] text-[#0E1D33]">Khách đổi món / khung giờ khác</button>
        <button type="button" onclick="setCsPreset('Khách trả phòng sớm không sử dụng nữa')" class="px-2.5 py-1 rounded-md bg-[#FAF7F0] border border-[#E6DFD1] hover:border-[#0E1D33] text-[11px] text-[#0E1D33]">Khách trả phòng sớm</button>
      </div>
    </div>

    <form id="cancelServiceForm" method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="services" />
      <input type="hidden" name="status" value="cancelled" />
      <input type="hidden" name="actor_department" value="reception" />
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Lý do hủy gửi Bếp &amp; Dịch vụ * (tối thiểu 3 ký tự)</label>
        <textarea required minlength="3" rows="3" name="cancel_reason" id="csReason"
                  placeholder="Nhập lý do chi tiết để Bộ phận Bếp / Spa nắm thông tin..."
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal('cancelServiceModal')" class="px-4 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase whitespace-nowrap">Đóng</button>
        <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold uppercase whitespace-nowrap">Xác nhận hủy &amp; gửi Bếp/DV</button>
      </div>
    </form>
  </div>
</div>

<!-- 7C. VERIFY / RECONCILE PAYMENT MODAL -->
<div id="verifyPaymentModal" class="hidden fixed inset-0 z-[70] bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 id="vpTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Đối soát giao dịch</h3>
        <p id="vpSub" class="text-xs text-slate-500 mt-0.5 tabular-nums"></p>
      </div>
      <button type="button" onclick="closeModal('verifyPaymentModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div id="vpInfoBox" class="p-3.5 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] text-xs space-y-1.5"></div>

    <div id="vpRejectPresets" class="hidden flex-wrap gap-1.5">
      <button type="button" onclick="setVpPreset('Chưa thấy tiền về tài khoản khách sạn')" class="px-2.5 py-1 rounded-md bg-rose-50 border border-rose-200 text-[11px] text-rose-800 hover:border-rose-400">Chưa thấy tiền về tài khoản</button>
      <button type="button" onclick="setVpPreset('Số tiền chuyển khoản không khớp hóa đơn')" class="px-2.5 py-1 rounded-md bg-rose-50 border border-rose-200 text-[11px] text-rose-800 hover:border-rose-400">Số tiền không khớp hóa đơn</button>
      <button type="button" onclick="setVpPreset('Sai nội dung / mã tham chiếu giao dịch')" class="px-2.5 py-1 rounded-md bg-rose-50 border border-rose-200 text-[11px] text-rose-800 hover:border-rose-400">Sai mã tham chiếu GD</button>
    </div>

    <form id="verifyPaymentForm" method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="payments" />
      <input type="hidden" name="decision" id="vpDecision" value="verified" />
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Mã bút toán sao kê ngân hàng (FT / Trace)</label>
        <input type="text" name="reference_no" id="vpRefNo" placeholder="VD: FT263218890123 / BIDV-1425..." class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono" />
      </div>
      <div>
        <label id="vpNoteLabel" class="block font-semibold text-slate-700 mb-1">Ghi chú đối soát</label>
        <textarea rows="2" name="note" id="vpNote" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal('verifyPaymentModal')" class="px-4 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase whitespace-nowrap">Đóng</button>
        <button type="submit" id="vpSubmitBtn" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-bold uppercase whitespace-nowrap">Xác nhận</button>
      </div>
    </form>
  </div>
</div>

<!-- 8. COLLECT PAYMENT MODAL -->
<div id="collectPaymentModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-[#E6DFD1] my-6 max-h-[92vh] overflow-y-auto space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 id="cpTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Thu tiền tại quầy</h3>
        <p id="cpSub" class="text-xs text-slate-500 mt-0.5"></p>
      </div>
      <button type="button" onclick="closeModal('collectPaymentModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div id="cpSummaryBox" class="p-3.5 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] space-y-1 text-xs"></div>
    <div id="cpServiceBreakdown" class="hidden p-3.5 rounded-xl bg-white border border-[#E6DFD1] space-y-1.5 text-[11px]"></div>

    <form id="collectPaymentForm" method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />

      <div id="cpPaidBanner" class="hidden p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-[11px] space-y-2">
        <div class="font-bold">✓ Đã tất toán đầy đủ trong hóa đơn (0 đ công nợ còn lại).</div>
        <label class="flex items-start gap-2 cursor-pointer text-[#0E1D33] font-semibold">
          <input type="checkbox" name="is_extra_adjustment" value="1" id="cpExtraAdjChk" onchange="checkCollectVariance()" class="mt-0.5 rounded border-emerald-400" />
          <span>Xác nhận thu bổ sung / phụ thu ngoài hóa đơn (bắt buộc nhập lý do bên dưới)</span>
        </label>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Số tiền thu *</label>
        <input type="number" name="amount" id="cpAmount" required min="1000" step="1000" oninput="checkCollectVariance()" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold tabular-nums" />
        <div id="cpQuickFillBtns" class="flex flex-wrap gap-1.5 mt-1.5"></div>
      </div>

      <div id="cpVarianceBox" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-300 space-y-2 text-[11px]">
        <div id="cpVarianceMsg" class="font-bold text-amber-900"></div>
        <div>
          <label class="block font-semibold text-amber-950 mb-1">Ghi chú giải trình chênh lệch (bắt buộc ≥ 4 ký tự) *</label>
          <textarea name="mismatch_note" id="cpMismatchNote" rows="2" placeholder="VD: Khách thanh toán trước một phần, cấn trừ cọc dịch vụ, hoặc điều chỉnh phụ thu..." class="w-full px-2.5 py-1.5 border border-amber-300 rounded-lg bg-white"></textarea>
        </div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Hình thức thu *</label>
        <select name="method" id="cpMethod" onchange="document.getElementById('cpQrHint').classList.toggle('hidden', this.value !== 'qr_bank')" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
          <option value="cash">Tiền mặt tại quầy (Xác nhận ngay)</option>
          <option value="card">Thẻ / Máy POS (Xác nhận ngay)</option>
          <option value="qr_bank">Chuyển khoản QR (Vào hàng chờ đối soát)</option>
        </select>
        <div id="cpQrHint" class="hidden text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2 mt-1.5">
          Giao dịch chuyển khoản QR sẽ ở trạng thái <strong>Chờ đối soát</strong> và chỉ cộng vào số đã thanh toán sau khi bấm <strong>Đã nhận tiền</strong>.
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Loại thanh toán</label>
          <select name="payment_type" id="cpType" onchange="checkCollectVariance()" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <option value="settlement">Tất toán / Thanh toán</option>
            <option value="partial">Trả riêng dịch vụ / Một phần</option>
            <option value="deposit">Đặt cọc bổ sung</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Mã tham chiếu</label>
          <input type="text" name="reference_no" placeholder="POS / Sao kê..." class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
        </div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Ghi chú biên nhận</label>
        <input type="text" name="note" id="cpNote" value="Thu tiền tại quầy Lễ tân" class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal('collectPaymentModal')" class="px-4 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" id="cpSubmitBtn" class="px-5 py-2 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase disabled:opacity-40 disabled:cursor-not-allowed">XÁC NHẬN THU TIỀN</button>
      </div>
    </form>
  </div>
</div>

<!-- 9. INVOICE MODAL -->
<div id="invoiceModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-2xl w-full p-7 shadow-2xl border border-[#E6DFD1] my-6 max-h-[92vh] overflow-y-auto space-y-5">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-4">
      <div>
        <div class="text-[10px] font-bold tracking-[0.22em] uppercase text-[#A8843E]">MƯỜNG THANH LUXURY PHÚ QUỐC ★★★★★</div>
        <h3 id="invTitle" class="font-serif text-2xl font-semibold text-[#0E1D33] mt-1">HÓA ĐƠN / BẢNG KÊ CHI PHÍ LƯU TRÚ</h3>
        <p id="invSub" class="text-xs text-slate-500 mt-0.5 tabular-nums"></p>
      </div>
      <button type="button" onclick="closeModal('invoiceModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div id="invBody" class="space-y-4 text-xs"></div>

    <div class="flex justify-end gap-2.5 pt-2 border-t border-[#EFE9DC]">
      <button type="button" onclick="window.print()" class="px-4 py-2 border border-slate-300 text-[#0E1D33] rounded-lg font-bold uppercase">In hóa đơn</button>
      <button type="button" onclick="closeModal('invoiceModal')" class="px-5 py-2 bg-[#0E1D33] text-white rounded-lg font-bold uppercase">Đóng</button>
    </div>
  </div>
</div>

<!-- 10. HOUSEKEEPING REQUEST MODAL -->
<div id="housekeepingModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">Yêu cầu dọn / kiểm tra phòng</h3>
        <p class="text-xs text-slate-500 mt-0.5">Điều phối nhân viên buồng phòng hoặc bộ phận kỹ thuật kiểm tra phòng.</p>
      </div>
      <button type="button" onclick="closeModal('housekeepingModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <form method="POST" action="/reception/housekeeping/create" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="room_rack" />
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Phòng *</label>
        <select name="room_id" id="hkRoomId" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
          <?php foreach ($roomsList as $rm): ?>
            <option value="<?= (int) $rm['id'] ?>">P.<?= $esc($rm['room_number']) ?> — <?= $esc($rm['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Loại yêu cầu *</label>
        <select name="task_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
          <option value="stay_refresh">Dọn phòng định kỳ khi khách đang ở</option>
          <option value="checkout_clean">Tổng vệ sinh &amp; nghiệm thu sẵn sàng</option>
          <option value="maintenance">Khóa bảo trì / Kiểm tra kỹ thuật</option>
        </select>
      </div>
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Mức độ ưu tiên *</label>
        <select name="priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
          <option value="HIGH">Khẩn cấp (Khách sắp nhận phòng / yêu cầu gấp)</option>
          <option value="MEDIUM" selected>Bình thường</option>
          <option value="LOW">Thấp</option>
        </select>
      </div>
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Ghi chú cho nhân viên buồng phòng</label>
        <textarea name="note" rows="3" placeholder="Ví dụ: Thay khăn, kiểm tra điều hòa phòng 201..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal('housekeepingModal')" class="px-4 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" class="px-5 py-2 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase">GỬI YÊU CẦU</button>
      </div>
    </form>
  </div>
</div>

<!-- 11. INCIDENT MODAL -->
<div id="incidentModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">Ghi nhận phản ánh / sự cố</h3>
        <p class="text-xs text-slate-500 mt-0.5">Lưu vết sự cố kỹ thuật hoặc phản ánh của khách hàng trong quá trình lưu trú / trả phòng.</p>
      </div>
      <button type="button" onclick="closeModal('incidentModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <form method="POST" action="/reception/incidents" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="room_rack" />
      <input type="hidden" name="booking_id" id="incBookingId" value="" />
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Phòng *</label>
        <select name="room_id" id="incRoomId" required onchange="syncIncidentRoomGuest()" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
          <?php foreach ($roomsList as $rm): ?>
            <option value="<?= (int) $rm['id'] ?>">P.<?= $esc($rm['room_number']) ?> — <?= $esc($rm['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="incActiveGuestNote" class="text-[11px] text-[#A8843E] font-medium mt-1 hidden"></div>
      </div>
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Nội dung phản ánh / sự cố *</label>
        <textarea name="content" required rows="3" placeholder="Ví dụ: Điều hòa chạy ồn, khách phản ánh vòi sen nước yếu..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Mức độ nghiêm trọng</label>
        <select name="severity" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
          <option value="LOW">Thấp (Lưu ý nhỏ / Bảo trì thường)</option>
          <option value="MEDIUM" selected>Trung bình (Cần xử lý trong ngày)</option>
          <option value="HIGH">Nghiêm trọng (Ảnh hưởng trực tiếp trải nghiệm / An toàn)</option>
        </select>
      </div>
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Phương án xử lý / Ghi chú</label>
        <textarea name="resolution_plan" rows="2" placeholder="Đã cử kỹ thuật kiểm tra, đổi phòng, tặng voucher..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal('incidentModal')" class="px-4 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" class="px-5 py-2 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] rounded-lg font-bold uppercase">+ GHI NHẬN</button>
      </div>
    </form>
  </div>
</div>

<!-- 12. ROOM RACK CONTEXTUAL ACTION DRAWER MODAL -->
<div id="rackDrawerModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 id="rdTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Phòng</h3>
        <p id="rdSub" class="text-xs text-slate-500 mt-0.5"></p>
      </div>
      <button type="button" onclick="closeModal('rackDrawerModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>
    <div id="rdInfoBox" class="p-3.5 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] text-xs space-y-1"></div>
    <div id="rdActions" class="grid grid-cols-1 gap-2 text-xs"></div>
  </div>
</div>

<!-- 13. CUSTOMER BOOKING HISTORY MODAL -->
<div id="customerModal" class="hidden fixed inset-0 z-50 bg-black/55 flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 id="cmTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Khách hàng</h3>
        <p id="cmSub" class="text-xs text-slate-500 mt-0.5 tabular-nums"></p>
      </div>
      <button type="button" onclick="closeModal('customerModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>
    <div id="cmList" class="max-h-80 overflow-y-auto divide-y divide-slate-100 text-xs"></div>
  </div>
</div>

<!-- 14. CANCEL BOOKING MODAL (NHẬP LÝ DO GỬI KHÁCH & HIỂN THỊ CHÍNH SÁCH HOÀN CỌC) -->
<div id="cancelBookingModal" class="hidden fixed inset-0 z-[70] bg-black/55 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[#E6DFD1] space-y-4">
    <div class="flex items-start justify-between border-b border-[#EFE9DC] pb-3">
      <div>
        <h3 id="cbTitle" class="font-serif text-2xl font-semibold text-[#0E1D33]">Hủy đặt phòng</h3>
        <p id="cbSub" class="text-xs text-slate-500 mt-0.5"></p>
      </div>
      <button type="button" onclick="closeModal('cancelBookingModal')" class="text-slate-400 hover:text-slate-600 text-lg font-bold px-2">&times;</button>
    </div>

    <div id="cbFlowHint" class="p-3 rounded-xl border text-[11px] leading-relaxed"></div>
    <div id="cbPolicyBox" class="p-3.5 rounded-xl border text-[11px] space-y-1"></div>

    <form id="cancelBookingForm" method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="return_tab" value="<?= $esc($tab) ?>" />
      <input type="hidden" name="decision" value="approve" />
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Lý do hủy (sẽ gửi thông báo tới khách hàng) *</label>
        <textarea name="cancel_reason" id="cbReason" required minlength="3" rows="3" placeholder="VD: Khách thay đổi lịch trình / Bảo trì khẩn cấp / Theo yêu cầu của khách..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal('cancelBookingModal')" class="px-4 py-2 border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold uppercase">ĐÓNG</button>
        <button type="submit" id="cbSubmitBtn" class="px-5 py-2 bg-rose-700 hover:bg-rose-800 text-white rounded-lg font-bold uppercase">XÁC NHẬN HỦY &amp; GỬI KHÁCH</button>
      </div>
    </form>
  </div>
</div>

<script>
const PMS = <?= json_encode($bootPayload, $jsonOpts) ?>;
const PAY_LABELS = {
  cash: 'Tiền mặt tại quầy',
  card: 'Thẻ / Máy POS',
  qr_bank: 'Chuyển khoản QR',
  bank_transfer: 'Chuyển khoản NH',
  qr_vnpay: 'Ví / QR VNPAY'
};

function fmtVND(n) {
  return Math.round(Number(n || 0)).toLocaleString('vi-VN') + ' đ';
}

function escHtml(s) {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function addDaysISO(iso, days) {
  const d = new Date(iso + 'T00:00:00Z');
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

function diffDaysISO(a, b) {
  const d1 = new Date(a + 'T00:00:00Z').getTime();
  const d2 = new Date(b + 'T00:00:00Z').getTime();
  return Math.round((d2 - d1) / 86400000);
}

function computeTax(base, rate) {
  const r = Number(rate ?? 13.4);
  const tax = Math.round(base * (r / 100));
  const serviceCharge = Math.round(base * (5 / 100));
  const vat = tax - serviceCharge;
  return { totalTax: tax, serviceCharge, vat, grandTotal: base + tax };
}

function findBooking(id) {
  return (PMS.bookings || []).find(b => Number(b.id) === Number(id)) || null;
}

function computeFolioTotal(b) {
  if (b.invoice && b.invoice.total_amount !== undefined) return Number(b.invoice.total_amount);
  const billableSrv = (b.service_orders || []).filter(so => so.status === 'completed' || (Number(so.is_prebooked) === 1 && so.status !== 'cancelled'));
  const srvGross = billableSrv.reduce((s, so) => s + Number(so.total_price || 0), 0);
  const srvNet = Math.max(0, srvGross - Number(b.service_discount_total || 0));
  const taxable = Math.max(0, Number(b.room_subtotal || 0) + Number(b.extension_fee || 0) + srvNet - Number(b.discount_amount || 0));
  return computeTax(taxable, Number(b.locked_tax_rate || 13.4)).grandTotal;
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('hidden');
}

// Notifications Popover
function toggleNotifPopover() {
  document.getElementById('notifPopover')?.classList.toggle('hidden');
}

async function markAllNotifsRead() {
  try {
    await fetch('/reception/notifications/read', { method: 'POST' });
    const badge = document.getElementById('notifBadge');
    if (badge) badge.remove();
  } catch (e) {}
}

// Dashboard Search Filter
function filterDashGuests() {
  const q = (document.getElementById('dashGuestSearchInput')?.value || '').trim().toLowerCase();
  document.querySelectorAll('.dash-guest-row').forEach(row => {
    const s = row.getAttribute('data-search') || '';
    row.style.display = (!q || s.includes(q)) ? '' : 'none';
  });
}

// Bookings Table Filters
function setBookingStatusFilter(st) {
  const sel = document.getElementById('bookingStatusFilterSelect');
  if (sel) sel.value = st;
  filterBookingsTable();
}

function filterBookingsTable() {
  const q = (document.getElementById('bookingSearchInput')?.value || '').trim().toLowerCase();
  const st = document.getElementById('bookingStatusFilterSelect')?.value || 'all';
  document.querySelectorAll('.booking-row').forEach(row => {
    const rStatus = row.getAttribute('data-status') || '';
    const rOverdue = row.getAttribute('data-overdue') === '1';
    const rSearch = row.getAttribute('data-search') || '';
    const matchQ = !q || rSearch.includes(q);
    let matchSt = true;
    if (st === 'overdue') matchSt = rOverdue;
    else if (st !== 'all') matchSt = rStatus === st;
    row.style.display = (matchQ && matchSt) ? '' : 'none';
  });
}

// Services Dispatch Filters & Role Toggle
let currentServiceGroup = 'all';
function setServiceRoleView(role) {
  const isRec = role === 'reception';
  const btnRec = document.getElementById('srvRoleBtnReception');
  const btnDept = document.getElementById('srvRoleBtnDept');
  if (btnRec && btnDept) {
    btnRec.className = isRec
      ? 'px-3.5 py-1.5 rounded-lg font-bold inline-flex items-center gap-2 bg-[#0E1D33] text-white'
      : 'px-3.5 py-1.5 rounded-lg font-bold inline-flex items-center gap-2 text-slate-600';
    btnDept.className = !isRec
      ? 'px-3.5 py-1.5 rounded-lg font-bold inline-flex items-center gap-2 bg-[#0E1D33] text-white'
      : 'px-3.5 py-1.5 rounded-lg font-bold inline-flex items-center gap-2 text-slate-600';
  }
  document.getElementById('srvPolicyReception')?.classList.toggle('hidden', !isRec);
  document.getElementById('srvPolicyDept')?.classList.toggle('hidden', isRec);
  document.querySelectorAll('.srv-actions-reception').forEach(el => {
    el.classList.toggle('hidden', !isRec);
    el.classList.toggle('flex', isRec);
  });
  document.querySelectorAll('.srv-actions-department').forEach(el => {
    el.classList.toggle('hidden', isRec);
    el.classList.toggle('flex', !isRec);
  });
}

function setServiceGroupFilter(grp) {
  currentServiceGroup = grp;
  document.querySelectorAll('.srv-group-btn').forEach(btn => {
    const active = btn.getAttribute('data-srv-group') === grp;
    btn.className = 'srv-group-btn px-3 py-1.5 rounded-lg border font-semibold ' +
      (active ? 'bg-[#C9A45C] text-[#0E1D33] border-[#C9A45C]' : 'bg-white text-slate-600 border-[#E6DFD1]');
  });
  filterServicesTable();
}

function filterServicesTable() {
  const q = (document.getElementById('serviceSearchInput')?.value || '').trim().toLowerCase();
  const st = document.getElementById('serviceStatusSelect')?.value || 'all';
  document.querySelectorAll('.srv-row').forEach(row => {
    const rSt = row.getAttribute('data-status') || '';
    const rGrp = row.getAttribute('data-group') || '';
    const rSearch = row.getAttribute('data-search') || '';
    const show = (!q || rSearch.includes(q)) && (st === 'all' || rSt === st) && (currentServiceGroup === 'all' || rGrp === currentServiceGroup);
    row.style.display = show ? '' : 'none';
  });
}

// Payments Table Filters & Transfer Reconciliation Filters
let currentTransferFilter = 'all';
function setTransferFilter(st) {
  currentTransferFilter = st;
  document.querySelectorAll('.tx-flt-btn').forEach(btn => {
    const active = btn.getAttribute('data-tx-flt') === st;
    btn.className = 'tx-flt-btn px-2.5 py-1 rounded-md font-semibold whitespace-nowrap ' +
      (active ? 'bg-[#0E1D33] text-white' : 'text-slate-600 hover:text-[#0E1D33]');
  });
  filterTransfersTable();
}

function filterTransfersTable() {
  const q = (document.getElementById('transferSearchInput')?.value || '').trim().toLowerCase();
  document.querySelectorAll('.tx-row').forEach(row => {
    const verif = row.getAttribute('data-verif') || 'pending';
    const rSearch = row.getAttribute('data-search') || '';
    const show = (currentTransferFilter === 'all' || verif === currentTransferFilter) && (!q || rSearch.includes(q));
    row.style.display = show ? '' : 'none';
  });
}

function filterPaymentsTable() {
  const q = (document.getElementById('paymentSearchInput')?.value || '').trim().toLowerCase();
  const flt = document.getElementById('paymentFilterSelect')?.value || 'all';
  document.querySelectorAll('.payment-row').forEach(row => {
    const rSt = row.getAttribute('data-status') || '';
    const rRem = row.getAttribute('data-rem') || '';
    const rSearch = row.getAttribute('data-search') || '';
    let matchF = true;
    if (flt === 'unpaid') matchF = rRem === 'unpaid';
    else if (flt === 'checked_in') matchF = rSt === 'checked_in';
    else if (flt === 'paid') matchF = rRem === 'paid';
    row.style.display = (matchF && (!q || rSearch.includes(q))) ? '' : 'none';
  });
}

// Customers Search
function filterCustomersTable() {
  const q = (document.getElementById('customerSearchInput')?.value || '').trim().toLowerCase();
  document.querySelectorAll('.cust-row').forEach(row => {
    const s = row.getAttribute('data-search') || '';
    row.style.display = (!q || s.includes(q)) ? '' : 'none';
  });
}

// Conference Inquiries Filters
let currentConfFilter = 'all';
function setConfFilter(st) {
  currentConfFilter = st;
  document.querySelectorAll('.conf-tab-btn').forEach(btn => {
    const active = btn.getAttribute('data-conf-tab') === st;
    btn.className = 'conf-tab-btn px-3 py-1.5 rounded-lg font-semibold border whitespace-nowrap ' +
      (active ? 'bg-[#0E1D33] text-white border-[#0E1D33]' : 'bg-white text-slate-600 border-[#E6DFD1] hover:border-[#0E1D33]');
  });
  filterConferencesTable();
}

function filterConferencesTable() {
  const q = (document.getElementById('confSearchInput')?.value || '').trim().toLowerCase();
  document.querySelectorAll('.conf-row').forEach(row => {
    const rSt = row.getAttribute('data-status') || '';
    const rSearch = row.getAttribute('data-search') || '';
    const show = (currentConfFilter === 'all' || rSt === currentConfFilter) && (!q || rSearch.includes(q));
    row.style.display = show ? '' : 'none';
  });
}

// ------------------------------------------------------------------
// WALK-IN MODAL LOGIC
// ------------------------------------------------------------------
let wiPreferredRoomId = null;
function openWalkInModal(initialRoomId) {
  wiPreferredRoomId = initialRoomId ? Number(initialRoomId) : null;
  document.getElementById('wiPhone').value = '';
  document.getElementById('wiName').value = '';
  document.getElementById('wiEmail').value = '';
  document.getElementById('wiIdNum').value = '';
  document.getElementById('wiLookupMsg').classList.add('hidden');
  const wiCin = document.getElementById('wiCheckIn');
  const wiCout = document.getElementById('wiCheckOut');
  wiCin.min = PMS.today;
  wiCin.value = PMS.today;
  const minOut = addDaysISO(PMS.today, 1);
  wiCout.min = minOut;
  wiCout.value = minOut;
  document.getElementById('wiAdults').value = '2';
  document.getElementById('wiChildren').value = '0';

  const catSel = document.getElementById('wiCatId');
  catSel.innerHTML = (PMS.categories || []).map(c => {
    const p = Number(c.effective_base_price ?? c.base_price ?? 0);
    return `<option value="${c.id}">${escHtml(c.name)} (${fmtVND(p)}/đêm${c.is_stop_sell ? ' · Đóng bán' : ''})</option>`;
  }).join('');

  if (wiPreferredRoomId) {
    const rm = (PMS.rooms || []).find(r => Number(r.id) === wiPreferredRoomId);
    if (rm) catSel.value = String(rm.category_id);
  }

  const vSel = document.getElementById('wiVoucher');
  vSel.innerHTML = '<option value="">Mặc định (Không áp dụng)</option>' +
    (PMS.vouchers || []).filter(v => Number(v.is_active) === 1 && v.scope !== 'service')
      .map(v => `<option value="${escHtml(v.code)}">${escHtml(v.code)} — ${escHtml(v.title)}</option>`).join('');

  syncWalkInCatalog();
  validateWalkInCapacity();
  openModal('walkInModal');
}

function validateWalkInCapacity() {
  const catId = Number(document.getElementById('wiCatId').value);
  const cat = (PMS.categories || []).find(c => Number(c.id) === catId);
  const adults = Number(document.getElementById('wiAdults').value || 0);
  const children = Number(document.getElementById('wiChildren').value || 0);
  const warn = document.getElementById('wiCapWarn');
  const btn = document.getElementById('wiSubmitBtn');
  if (!cat || !warn || !btn) return;
  const maxAd = Number(cat.max_adults || 2);
  const maxCh = Number(cat.max_children ?? 1);
  const maxTot = maxAd + maxCh;
  if (adults < 1 || children < 0) {
    warn.textContent = '⚠ Số lượng khách không hợp lệ (tối thiểu 1 người lớn).';
    warn.classList.remove('hidden');
    btn.disabled = true;
  } else if (adults > maxAd || (adults + children) > maxTot) {
    warn.textContent = `⚠ Hạng phòng ${cat.name} chỉ chứa tối đa ${maxAd} người lớn và tổng ${maxTot} khách (đang nhập ${adults} NL, ${children} TE).`;
    warn.classList.remove('hidden');
    btn.disabled = true;
  } else {
    warn.classList.add('hidden');
    btn.disabled = false;
  }
}

function syncWalkInCatalog() {
  validateWalkInCapacity();
  const catId = Number(document.getElementById('wiCatId').value);
  const cat = (PMS.categories || []).find(c => Number(c.id) === catId) || PMS.categories[0];
  const stopNote = document.getElementById('wiStopSellNote');
  if (cat && cat.is_stop_sell) {
    stopNote.textContent = 'Lưu ý: Hạng phòng đang đánh dấu đóng bán (' + (cat.stop_sell_note || 'kiểm tra trước khi tạo đơn') + ').';
    stopNote.classList.remove('hidden');
  } else {
    stopNote.classList.add('hidden');
  }

  const pkgs = (cat && cat.resolved_packages && cat.resolved_packages.length) ? cat.resolved_packages : (PMS.packages || []);
  const pkgSel = document.getElementById('wiPkgId');
  pkgSel.innerHTML = pkgs.map(p => {
    const extra = Number(p.resolved_extra_per_night ?? p.extra_per_night ?? 0);
    const disabled = p.enabled === false ? 'disabled' : '';
    const extraLbl = extra > 0 ? `+${fmtVND(extra)}` : (extra < 0 ? fmtVND(extra) : 'Tiêu chuẩn');
    return `<option value="${p.id}" ${disabled}>${escHtml(p.name)} (${extraLbl}${p.enabled === false ? ' · Không áp dụng' : ''})</option>`;
  }).join('');
  const firstEnabled = pkgs.find(p => p.enabled !== false);
  if (firstEnabled) pkgSel.value = String(firstEnabled.id);

  syncWalkInRoomsAndPricing();
}

function syncWalkInDates() {
  const cinEl = document.getElementById('wiCheckIn');
  if (cinEl.value && cinEl.value < PMS.today) cinEl.value = PMS.today;
  const cin = cinEl.value || PMS.today;
  const coutEl = document.getElementById('wiCheckOut');
  const minOut = addDaysISO(cin, 1);
  coutEl.min = minOut;
  if (!coutEl.value || coutEl.value <= cin) coutEl.value = minOut;
  syncWalkInRoomsAndPricing();
}

function syncWalkInRoomsAndPricing() {
  const catId = Number(document.getElementById('wiCatId').value);
  const cin = document.getElementById('wiCheckIn').value;
  const cout = document.getElementById('wiCheckOut').value;
  const immEl = document.getElementById('wiImmediateCheckIn');
  const payMethod = document.getElementById('wiPayMethod')?.value || 'cash';
  if (payMethod === 'qr_bank' && immEl && immEl.checked) {
    immEl.checked = false;
  }
  const imm = !!immEl?.checked;
  document.getElementById('wiQrNote')?.classList.toggle('hidden', payMethod !== 'qr_bank');

  const eligible = (PMS.rooms || []).filter(r => {
    if (Number(r.category_id) !== catId || ['maintenance', 'out_of_order'].includes(r.status)) return false;
    if ((imm || cin <= PMS.today) && (r.status !== 'available' || r.clean_status !== 'clean')) return false;
    const overlap = (PMS.bookings || []).some(b =>
      Number(b.assigned_room_id) === Number(r.id) &&
      ['pending', 'confirmed', 'checked_in'].includes(b.status) &&
      b.check_in_date < cout && b.check_out_date > cin
    );
    return !overlap;
  });

  const rmSel = document.getElementById('wiRoomId');
  rmSel.innerHTML = '<option value="">Tự động / gán sau</option>' +
    eligible.map(r => `<option value="${r.id}">P.${escHtml(r.room_number)} · Tầng ${escHtml(r.floor)} (${r.status === 'available' && r.clean_status === 'clean' ? 'Trống sạch' : escHtml(r.status)})</option>`).join('');
  if (wiPreferredRoomId && eligible.some(r => Number(r.id) === wiPreferredRoomId)) {
    rmSel.value = String(wiPreferredRoomId);
  } else if (eligible.length > 0) {
    rmSel.value = String(eligible[0].id);
  }
  recalcWalkInPricing();
}

let wiLastReqDeposit = 0;
function recalcWalkInPricing() {
  const catId = Number(document.getElementById('wiCatId').value);
  const pkgId = Number(document.getElementById('wiPkgId').value);
  const cat = (PMS.categories || []).find(c => Number(c.id) === catId) || PMS.categories[0];
  const pkgs = (cat && cat.resolved_packages) ? cat.resolved_packages : (PMS.packages || []);
  const pkg = pkgs.find(p => Number(p.id) === pkgId) || pkgs[0];

  const cin = document.getElementById('wiCheckIn').value;
  const cout = document.getElementById('wiCheckOut').value;
  const nights = Math.max(1, diffDaysISO(cin, cout));

  const baseNight = Number(cat?.effective_base_price ?? cat?.base_price ?? 0);
  const pkgExtra = Number(pkg?.resolved_extra_per_night ?? pkg?.extra_per_night ?? 0);
  const unit = Math.max(0, baseNight + pkgExtra);
  const roomSub = unit * nights;

  const vCode = (document.getElementById('wiVoucher').value || '').toUpperCase();
  const v = (PMS.vouchers || []).find(x => x.code.toUpperCase() === vCode && Number(x.is_active) === 1);
  let discount = 0;
  if (v && v.scope !== 'service' && roomSub >= Number(v.min_order_amount || 0)) {
    discount = v.discount_type === 'percent' ? Math.round(roomSub * (Number(v.discount_value) / 100)) : Number(v.discount_value);
    if (Number(v.max_discount_amount) > 0) discount = Math.min(discount, Number(v.max_discount_amount));
    discount = Math.min(discount, roomSub);
  }

  const taxRate = Number(PMS.policies?.tax_service_percent ?? 13.4);
  const depPct = Number(PMS.policies?.deposit_percent ?? 30);
  const taxable = Math.max(0, roomSub - discount);
  const tb = computeTax(taxable, taxRate);
  const depReq = Math.round(tb.grandTotal * (depPct / 100));
  wiLastReqDeposit = depReq;

  document.getElementById('wiDepLabel').textContent = `Cọc ${depPct}% (${fmtVND(depReq)})`;
  document.getElementById('wiDepositAmount').value = String(depReq);
  updateWalkInDepHint();

  document.getElementById('wiPricingSummary').innerHTML = `
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="text-[#0E1D33] tabular-nums">
        ${nights} đêm &times; ${fmtVND(unit)} + phí/thuế ${fmtVND(tb.totalTax)}
        ${discount > 0 ? ` - ưu đãi ${fmtVND(discount)}` : ''} = <strong class="text-base">${fmtVND(tb.grandTotal)}</strong>
      </div>
      <div class="font-bold text-[#A8843E] tabular-nums">Cọc ${depPct}% quy định: ${fmtVND(depReq)}</div>
    </div>
    <p class="text-[11px] text-slate-500">Công thức phí &amp; thuế <strong>${taxRate}%</strong> chuẩn 5 sao: Phí phục vụ 5% (${fmtVND(tb.serviceCharge)}) + Thuế GTGT VAT 8% (${fmtVND(tb.vat)}).</p>
  `;
}

function updateWalkInDepHint() {
  const val = Number(document.getElementById('wiDepositAmount').value || 0);
  const depPct = Number(PMS.policies?.deposit_percent ?? 30);
  const hint = document.getElementById('wiDepHint');
  if (val >= wiLastReqDeposit) {
    hint.className = 'text-[11px] text-emerald-700 font-semibold mt-1';
    hint.textContent = `✓ Đã khớp tối thiểu ${depPct}% tiền cọc quy định (${fmtVND(wiLastReqDeposit)})`;
  } else {
    hint.className = 'text-[11px] text-amber-700 mt-1';
    hint.textContent = `Cần tối thiểu ${fmtVND(wiLastReqDeposit)} nếu Check-in ngay.`;
  }
}

let wiLookupTimer = null;
function lookupWalkInCustomer(rawPhone) {
  const digits = rawPhone.replace(/\D+/g, '');
  const msgEl = document.getElementById('wiLookupMsg');
  if (digits.length < 9) {
    msgEl.classList.add('hidden');
    return;
  }
  clearTimeout(wiLookupTimer);
  wiLookupTimer = setTimeout(async () => {
    try {
      const res = await fetch('/reception/customers/lookup?phone=' + encodeURIComponent(rawPhone));
      const data = await res.json();
      if (data.customer) {
        const nm = document.getElementById('wiName');
        const em = document.getElementById('wiEmail');
        const idn = document.getElementById('wiIdNum');
        if (!nm.value) nm.value = data.customer.full_name || '';
        if (!em.value) em.value = data.customer.email || '';
        if (!idn.value) idn.value = data.customer.guest_id_number || '';
        msgEl.textContent = `Khách quen (${data.customer.membership_tier} · ${data.customer.bookings_count} đơn trước đây)`;
        msgEl.classList.remove('hidden');
      } else {
        msgEl.classList.add('hidden');
      }
    } catch (e) {}
  }, 250);
}

// ------------------------------------------------------------------
// ASSIGN ROOM MODAL
// ------------------------------------------------------------------
async function openAssignModal(bookingId) {
  const b = findBooking(bookingId);
  if (!b) return;
  document.getElementById('assignModalTitle').textContent = 'Gán phòng · ' + b.booking_code;
  document.getElementById('assignModalSub').textContent = `Phòng còn trống ${b.check_in_date} → ${b.check_out_date} (${b.category_name})`;
  const form = document.getElementById('assignRoomForm');
  form.action = b.status === 'pending'
    ? '/reception/bookings/' + bookingId + '/confirm'
    : '/reception/bookings/' + bookingId + '/assign-room';
  document.getElementById('assignSubmitBtn').textContent = b.status === 'pending' ? 'XÁC NHẬN & GÁN PHÒNG' : 'LƯU';

  const sel = document.getElementById('assignRoomSelect');
  const emptyEl = document.getElementById('assignRoomEmpty');
  const loadEl = document.getElementById('assignRoomLoading');
  sel.innerHTML = '';
  emptyEl.classList.add('hidden');
  loadEl.classList.remove('hidden');
  openModal('assignRoomModal');

  try {
    const res = await fetch('/reception/bookings/' + bookingId + '/available-rooms');
    const data = await res.json();
    const list = data.rooms || [];
    if (list.length === 0) {
      sel.classList.add('hidden');
      emptyEl.classList.remove('hidden');
    } else {
      sel.classList.remove('hidden');
      sel.innerHTML = list.map(r => `<option value="${r.id}">P.${escHtml(r.room_number)} - tầng ${escHtml(r.floor)} (${escHtml(r.category_name || b.category_name)}) · ${r.clean_status === 'clean' ? 'Sẵn sàng' : 'Cần dọn'}</option>`).join('');
      if (b.assigned_room_id && list.some(r => Number(r.id) === Number(b.assigned_room_id))) {
        sel.value = String(b.assigned_room_id);
      }
    }
  } catch (e) {
    emptyEl.classList.remove('hidden');
  } finally {
    loadEl.classList.add('hidden');
  }
}

// ------------------------------------------------------------------
// CHECK-IN MODAL
// ------------------------------------------------------------------
async function openCheckInModal(bookingId) {
  const b = findBooking(bookingId);
  if (!b) return;
  document.getElementById('ciTitle').textContent = 'Kiểm tra Đặt cọc & Gán phòng · ' + b.booking_code;
  document.getElementById('checkInForm').action = '/reception/bookings/' + bookingId + '/check-in';
  document.getElementById('ciGuestIdNum').value = b.guest_id_number || '';

  const depReq = Number(b.deposit_required || 0);
  const paid = Number(b.total_paid || 0);
  const missing = Math.max(0, depReq - paid);
  document.getElementById('ciTopupAmount').value = String(missing);

  document.getElementById('ciDepositBox').innerHTML = `
    <div class="flex justify-between"><span class="text-slate-600">Tổng giá trị đơn dự kiến:</span><span class="font-bold text-[#0E1D33] tabular-nums">${fmtVND(b.total_estimated)}</span></div>
    <div class="flex justify-between"><span class="text-slate-600">Mức đặt cọc bắt buộc (30%):</span><span class="font-bold text-[#0E1D33] tabular-nums">${fmtVND(depReq)}</span></div>
    <div class="flex justify-between"><span class="text-slate-600">Đã thu trước đó:</span><span class="font-bold text-emerald-700 tabular-nums">${fmtVND(paid)}</span></div>
    <div class="pt-2 border-t border-[#E6DFD1] flex justify-between font-bold">
      ${missing > 0
        ? `<span class="text-rose-700">Cần thu cọc ngay tại quầy:</span><span class="text-rose-700 tabular-nums">${fmtVND(missing)}</span>`
        : `<span class="text-emerald-700">Trạng thái tiền cọc:</span><span class="text-emerald-700">✓ Đã đủ 30% cọc quy định</span>`}
    </div>
  `;

  document.getElementById('ciTopupMethodWrap').classList.toggle('hidden', missing <= 0);
  document.getElementById('ciTopupRefWrap').classList.toggle('hidden', missing <= 0);
  document.getElementById('ciSubmitBtn').textContent = missing > 0 ? 'XÁC NHẬN THU CỌC & NHẬN PHÒNG' : 'XÁC NHẬN NHẬN PHÒNG';

  const prepBox = document.getElementById('ciPrepNoteBox');
  const prepItems = b.selected_items || [];
  if (prepItems.length > 0 || b.special_requests) {
    prepBox.innerHTML = `<div class="font-bold">Lưu ý chuẩn bị phòng khi Check-in:</div>` +
      (prepItems.length ? `<div>Combo / Chuẩn bị kèm: ${prepItems.map(i => `${escHtml(i.name)} (&times;${i.quantity})`).join(', ')}</div>` : '') +
      (b.special_requests ? `<div>Ghi chú khách: ${escHtml(b.special_requests)}</div>` : '');
    prepBox.classList.remove('hidden');
  } else {
    prepBox.classList.add('hidden');
  }

  const sel = document.getElementById('ciRoomSelect');
  const emptyEl = document.getElementById('ciRoomEmpty');
  const warnEl = document.getElementById('ciAssignWarn');
  const submitBtn = document.getElementById('ciSubmitBtn');
  sel.innerHTML = '';
  emptyEl.classList.add('hidden');
  warnEl.classList.add('hidden');
  submitBtn.disabled = false;
  openModal('checkInModal');

  try {
    const res = await fetch('/reception/bookings/' + bookingId + '/available-rooms?ready_only=1');
    const data = await res.json();
    const ready = (data.rooms || []).filter(r => r.status === 'available' && r.clean_status === 'clean');
    if (ready.length === 0) {
      sel.classList.add('hidden');
      emptyEl.classList.remove('hidden');
      submitBtn.disabled = true;
    } else if (b.assigned_room_id) {
      const assignedMatch = ready.find(r => Number(r.id) === Number(b.assigned_room_id));
      if (assignedMatch) {
        sel.classList.remove('hidden');
        sel.innerHTML = `<option value="${assignedMatch.id}">P.${escHtml(assignedMatch.room_number)} - tầng ${escHtml(assignedMatch.floor)} (${escHtml(assignedMatch.category_name || b.category_name)} · Đã phân bổ &amp; sạch)</option>`;
        sel.value = String(assignedMatch.id);
      } else {
        sel.classList.add('hidden');
        warnEl.innerHTML = `⚠ Phòng <strong>P.${escHtml(b.assigned_room_number || b.assigned_room_id)}</strong> đã được gán cho đơn này hiện <strong>chưa dọn sạch hoặc chưa sẵn sàng</strong>. Vui lòng nghiệm thu dọn sạch hoặc dùng nút <strong>Đổi/Gán phòng</strong> sang phòng khác trước khi Check-in.`;
        warnEl.classList.remove('hidden');
        submitBtn.disabled = true;
      }
    } else {
      sel.classList.remove('hidden');
      sel.innerHTML = ready.map(r => `<option value="${r.id}">P.${escHtml(r.room_number)} - tầng ${escHtml(r.floor)} (${escHtml(r.category_name || b.category_name)})</option>`).join('');
    }
  } catch (e) {
    emptyEl.classList.remove('hidden');
    submitBtn.disabled = true;
  }
}

// ------------------------------------------------------------------
// MODIFY STAY MODAL (EXTEND / CHANGE ROOM)
// ------------------------------------------------------------------
let activeMsBooking = null;
function openModifyStayModal(bookingId, initialMode) {
  const b = findBooking(bookingId);
  if (!b) return;
  activeMsBooking = b;
  document.getElementById('modifyStayForm').action = '/reception/bookings/' + bookingId + '/modify-stay';
  document.getElementById('msSub').innerHTML = `Khách: <strong>${escHtml(b.guest_name)}</strong> · Hiện tại: P.${escHtml(b.assigned_room_number || '')}`;

  const outEl = document.getElementById('msNewCheckOut');
  const minExtend = b.check_out_date >= PMS.today ? b.check_out_date : PMS.today;
  outEl.min = minExtend;
  outEl.value = addDaysISO(minExtend, 1);
  document.getElementById('msTimeSlot').value = '12:00';
  document.getElementById('msReason').value = '';

  const otherClean = (PMS.rooms || []).filter(r => {
    if (r.status !== 'available' || r.clean_status !== 'clean' || Number(r.id) === Number(b.assigned_room_id)) return false;
    const overlap = (PMS.bookings || []).some(ob =>
      Number(ob.id) !== Number(b.id) &&
      Number(ob.assigned_room_id) === Number(r.id) &&
      ['pending', 'confirmed', 'checked_in'].includes(ob.status) &&
      ob.check_in_date < b.check_out_date && ob.check_out_date > PMS.today
    );
    return !overlap;
  });
  document.getElementById('msExtRoomSelect').innerHTML = `<option value="">P.${escHtml(b.assigned_room_number || '')} (Giữ nguyên phòng đang ở)</option>` +
    otherClean.map(r => `<option value="${r.id}">Chuyển sang P.${escHtml(r.room_number)} - tầng ${escHtml(r.floor)} (${escHtml(r.category_name)})</option>`).join('');
  document.getElementById('msChangeRoomSelect').innerHTML = otherClean.length === 0
    ? '<option value="">Không còn phòng sạch trống khác không trùng lịch</option>'
    : otherClean.map(r => `<option value="${r.id}" data-cat-id="${r.category_id}">P.${escHtml(r.room_number)} · Tầng ${escHtml(r.floor)} — ${escHtml(r.category_name)}${Number(r.category_id) !== Number(b.category_id) ? ' (Khác hạng)' : ''}</option>`).join('');

  switchModifyMode(initialMode || 'extend_stay');
  openModal('modifyStayModal');
}

function recalcModifyChangeRoomPreview() {
  const box = document.getElementById('msChangePreview');
  const sel = document.getElementById('msChangeRoomSelect');
  if (!box || !sel || !activeMsBooking) return;
  const rid = Number(sel.value || 0);
  if (!rid) {
    box.classList.add('hidden');
    return;
  }
  const rm = (PMS.rooms || []).find(r => Number(r.id) === rid);
  if (!rm) {
    box.classList.add('hidden');
    return;
  }
  if (Number(rm.category_id) === Number(activeMsBooking.category_id)) {
    box.innerHTML = `Cùng hạng phòng <strong>${escHtml(activeMsBooking.category_name)}</strong>: Không phát sinh chênh lệch giá phòng; phòng cũ tự động tạo phiếu dọn buồng.`;
  } else {
    box.innerHTML = `<strong>Chuyển đổi hạng phòng (${escHtml(activeMsBooking.category_name)} &rarr; ${escHtml(rm.category_name)}):</strong> Hệ thống sẽ tự động tính lại phần chênh lệch đơn giá cho các đêm lưu trú còn lại từ hôm nay và cập nhật hóa đơn mở.`;
  }
  box.classList.remove('hidden');
}

function switchModifyMode(mode) {
  const isExt = mode === 'extend_stay';
  document.getElementById('msAction').value = mode;
  document.getElementById('msTitle').textContent = (isExt ? 'Gia hạn lưu trú' : 'Đổi phòng lưu trú') + ' · ' + (activeMsBooking?.booking_code || '');

  document.getElementById('msTabExtend').className = 'py-2 px-3 rounded-lg font-bold border ' + (isExt ? 'bg-[#0E1D33] text-white border-[#0E1D33]' : 'bg-[#FAF7F0] text-[#0E1D33] border-[#E6DFD1]');
  document.getElementById('msTabChange').className = 'py-2 px-3 rounded-lg font-bold border ' + (!isExt ? 'bg-[#0E1D33] text-white border-[#0E1D33]' : 'bg-[#FAF7F0] text-[#0E1D33] border-[#E6DFD1]');

  document.getElementById('msExtendFields').classList.toggle('hidden', !isExt);
  document.getElementById('msChangeRoomFields').classList.toggle('hidden', isExt);
  document.getElementById('msExtRoomSelect').disabled = !isExt;
  document.getElementById('msChangeRoomSelect').disabled = isExt;
  if (isExt) recalcModifyPreview();
  else recalcModifyChangeRoomPreview();
}

function recalcModifyPreview() {
  if (!activeMsBooking) return;
  const b = activeMsBooking;
  const nightlyUnit = (Number(b.locked_nightly_rate || 0) + Number(b.locked_package_extra || 0));
  const newOut = document.getElementById('msNewCheckOut').value;
  const slot = document.getElementById('msTimeSlot').value;
  const addedNights = Math.max(0, diffDaysISO(b.check_out_date, newOut));
  const lateFee = slot === '15:00' ? Math.round(nightlyUnit * 0.3) : (slot === '18:00' ? Math.round(nightlyUnit * 0.5) : 0);
  const sub = addedNights * nightlyUnit + lateFee;
  const withTax = computeTax(sub, Number(b.locked_tax_rate || 13.4)).grandTotal;
  document.getElementById('msFeePreview').innerHTML = `
    <span class="text-slate-600">Phí gia hạn dự kiến (${addedNights} đêm + khung giờ ${escHtml(slot)}, gồm thuế phí):</span>
    <strong class="text-sm text-[#0E1D33] tabular-nums">+${fmtVND(withTax)}</strong>
  `;
}

// ------------------------------------------------------------------
// CHECK-OUT MODAL
// ------------------------------------------------------------------
let activeCoBooking = null;
let coPendingCount = 0;
let coRemainingDue = 0;

function openCheckOutModal(bookingId) {
  const b = findBooking(bookingId);
  if (!b) return;
  activeCoBooking = b;
  document.getElementById('coTitle').textContent = 'Tất toán & Trả phòng · ' + b.booking_code;
  document.getElementById('coSub').innerHTML = `Khách: <strong class="text-[#0E1D33]">${escHtml(b.guest_name)}</strong> · Phòng <strong class="text-[#0E1D33]">P.${escHtml(b.assigned_room_number || '')}</strong> · Thực hiện lần lượt các bước dưới đây để chốt hóa đơn chính xác và giải phóng phòng.`;
  document.getElementById('checkOutForm').action = '/reception/bookings/' + bookingId + '/check-out';
  document.getElementById('coSurcharges').value = String(b.invoice?.surcharges || 0);
  document.getElementById('coSurchargeNote').value = b.invoice?.surcharge_note || '';
  document.getElementById('coCollectedChk').checked = false;
  document.getElementById('coCancelPendingChk').checked = false;
  const crInput = document.getElementById('coCancelServicesReason');
  if (crInput) crInput.value = '';
  toggleCoCancelReason();
  document.getElementById('coHasIncChk').checked = false;
  toggleCheckOutIncident(false);

  const uncompleted = (b.service_orders || []).filter(so => !Number(so.is_prebooked) && (so.status === 'pending' || so.status === 'accepted'));
  coPendingCount = uncompleted.length;
  const warnBox = document.getElementById('coPendingSrvWarn');
  const step3Title = document.getElementById('coStep3Title');
  if (uncompleted.length > 0) {
    document.getElementById('coPendingSrvTitle').textContent = `Bước 2. Xử lý ${uncompleted.length} dịch vụ chưa hoàn thành (hiện CHƯA tính tiền)`;
    document.getElementById('coPendingSrvList').innerHTML = uncompleted.map(so => `
      <div class="flex justify-between gap-2">
        <span>• <strong>${escHtml(so.service_name_snapshot)}</strong> &times; ${so.quantity} <span class="text-slate-500">(${so.status === 'accepted' ? 'Bếp/DV đang làm' : 'Chờ lễ tân xác nhận'})</span></span>
        <span class="tabular-nums font-medium">${fmtVND(so.total_price)}</span>
      </div>
    `).join('');
    warnBox.classList.remove('hidden');
    if (step3Title) step3Title.textContent = 'Bước 3. Nhập phụ phí phát sinh (nếu có) & xác nhận tất toán';
  } else {
    warnBox.classList.add('hidden');
    if (step3Title) step3Title.textContent = 'Bước 2. Nhập phụ phí phát sinh (nếu có) & xác nhận tất toán';
  }

  recalcCheckOutFolio();
  openModal('checkOutModal');
}

function toggleCoCancelReason() {
  const checked = !!document.getElementById('coCancelPendingChk')?.checked;
  const wrap = document.getElementById('coCancelPendingReasonWrap');
  const inp = document.getElementById('coCancelServicesReason');
  if (wrap) wrap.classList.toggle('hidden', !checked);
  if (inp) inp.required = checked;
}

function setCoCancelPreset(txt) {
  const inp = document.getElementById('coCancelServicesReason');
  if (inp) {
    inp.value = txt;
    updateCheckOutSubmitState();
  }
}

function recalcCheckOutFolio() {
  if (!activeCoBooking) return;
  const b = activeCoBooking;
  const surcharges = Math.max(0, Number(document.getElementById('coSurcharges').value || 0));
  const billable = (b.service_orders || []).filter(so => so.status === 'completed' || (Number(so.is_prebooked) === 1 && so.status !== 'cancelled'));
  const srvNet = billable.reduce((s, so) => s + Math.max(0, Number(so.total_price || 0) - Number(so.discount_amount || 0)), 0);
  const extFee = Number(b.extension_fee || 0);
  const roomCharges = Number(b.room_subtotal || 0);
  const roomDiscount = Number(b.discount_amount || 0);
  const taxRate = Number(b.locked_tax_rate || 13.4);

  const taxableBase = Math.max(0, roomCharges + extFee + srvNet + surcharges - roomDiscount);
  const tax = computeTax(taxableBase, taxRate);
  const paidBefore = Number(b.total_paid || 0);
  const unverifiedList = (b.payments || []).filter(p => (p.verification_status || 'pending') === 'pending');
  const unverifiedAmt = unverifiedList.reduce((s, p) => s + Number(p.amount || 0), 0);
  const rem = Math.max(0, tax.grandTotal - paidBefore);
  coRemainingDue = rem;
  document.getElementById('coSettlementAmt').value = String(rem);

  document.getElementById('coFolioBox').innerHTML = `
    <div class="flex justify-between gap-2"><span class="text-slate-700">1. Tiền phòng lưu trú (${b.nights} đêm)</span><span class="font-semibold text-[#0E1D33] tabular-nums">${fmtVND(roomCharges)}</span></div>
    ${extFee > 0 ? `<div class="flex justify-between gap-2"><span class="text-slate-700">2. Phí gia hạn / Trả phòng muộn (${escHtml(b.checkout_time_slot || '12:00')})</span><span class="font-semibold text-[#0E1D33] tabular-nums">${fmtVND(extFee)}</span></div>` : ''}
    <div class="space-y-1 pt-0.5">
      <div class="flex justify-between gap-2"><span class="text-slate-700">${extFee > 0 ? '3.' : '2.'} Dịch vụ đã tính tiền (${billable.length} mục đã hoàn thành / đặt kèm)</span><span class="font-semibold text-[#0E1D33] tabular-nums">${fmtVND(srvNet)}</span></div>
      ${billable.length > 0 ? `<div class="pl-3 border-l border-[#E6DFD1] space-y-1 text-[11px] text-slate-600">${billable.map(so => `<div class="flex justify-between gap-2"><span>${escHtml(so.service_name_snapshot)} &times; ${so.quantity} (${Number(so.is_prebooked) ? 'Đặt trước kèm phòng' : 'Bếp/DV đã hoàn thành'})</span><span class="tabular-nums">${fmtVND(Math.max(0, Number(so.total_price) - Number(so.discount_amount || 0)))}</span></div>`).join('')}</div>` : '<div class="text-[11px] text-slate-500 pl-3">Chưa có dịch vụ hoàn thành nào được tính vào hóa đơn.</div>'}
    </div>
    ${surcharges > 0 ? `<div class="flex justify-between gap-2"><span class="text-slate-700">Phụ phí minibar / phát sinh tại phòng <span class="text-slate-500">(+ 5% phí phục vụ &amp; 8% VAT)</span></span><span class="font-semibold text-[#0E1D33] tabular-nums">${fmtVND(surcharges)}</span></div>` : ''}
    ${roomDiscount > 0 ? `<div class="flex justify-between gap-2 text-emerald-700"><span>Giảm giá Voucher (${escHtml(b.voucher_code)})</span><span class="tabular-nums">-${fmtVND(roomDiscount)}</span></div>` : ''}
    <div class="pt-1.5 border-t border-[#E6DFD1]/80 space-y-1 text-[11px] text-slate-600">
      <div class="flex justify-between gap-2"><span>Phí phục vụ tiêu chuẩn (5% trên tiền trước thuế)</span><span class="tabular-nums font-medium text-[#0E1D33]">${fmtVND(tax.serviceCharge)}</span></div>
      <div class="flex justify-between gap-2"><span>Thuế GTGT VAT (8% sau phí phục vụ)</span><span class="tabular-nums font-medium text-[#0E1D33]">${fmtVND(tax.vat)}</span></div>
    </div>
    <div class="pt-2 border-t border-[#E6DFD1] flex justify-between font-bold text-sm text-[#0E1D33]"><span>Tổng hóa đơn cần thanh toán</span><span class="tabular-nums">${fmtVND(tax.grandTotal)}</span></div>
    <div class="flex justify-between font-semibold text-emerald-700"><span>Đã cọc / Đã thanh toán trước (được tính trừ)</span><span class="tabular-nums">-${fmtVND(paidBefore)}</span></div>
    ${unverifiedList.length > 0 ? `<div class="px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-[11px] text-amber-900">Lưu ý: Khách có <strong>${unverifiedList.length} giao dịch chuyển khoản (${fmtVND(unverifiedAmt)})</strong> đang chờ đối soát ở mục Thanh toán — hãy kiểm tra sao kê trước khi xác nhận tất toán.</div>` : ''}
    <div class="pt-2 border-t border-[#E6DFD1] flex justify-between items-baseline font-bold text-base"><span class="${rem > 0 ? 'text-rose-700' : 'text-emerald-700'}">Số tiền còn phải thu ngay khi trả phòng</span><span class="tabular-nums ${rem > 0 ? 'text-rose-700' : 'text-emerald-700'}">${fmtVND(rem)}</span></div>
  `;

  document.getElementById('coCollectBox').classList.toggle('hidden', rem <= 0);
  document.getElementById('coPaidOkBox')?.classList.toggle('hidden', rem > 0);
  document.getElementById('coPayMethodLbl').textContent = `Hình thức thu số tiền còn lại (${fmtVND(rem)}) *`;
  document.getElementById('coCollectedLbl').textContent = `Tôi xác nhận đã thu đủ ${fmtVND(rem)} từ khách hàng`;
  updateCheckOutSubmitState();
}

function toggleCheckOutIncident(checked) {
  document.getElementById('coIncFields').classList.toggle('hidden', !checked);
  document.getElementById('coIncContent').required = checked;
}

function updateCheckOutSubmitState() {
  const mustConfirmPay = coRemainingDue > 0 && !document.getElementById('coCollectedChk').checked;
  const cancelChecked = !!document.getElementById('coCancelPendingChk').checked;
  const reasonVal = (document.getElementById('coCancelServicesReason')?.value || '').trim();
  const mustResolvePending = coPendingCount > 0 && (!cancelChecked || reasonVal.length < 3);
  document.getElementById('coSubmitBtn').disabled = mustConfirmPay || mustResolvePending;
}

// ------------------------------------------------------------------
// BOOKING DETAIL / EDIT MODAL
// ------------------------------------------------------------------
let activeDetailBooking = null;
function syncDetailDates() {
  const cinEl = document.getElementById('dtCheckIn');
  const coutEl = document.getElementById('dtCheckOut');
  if (!cinEl || !coutEl) return;
  if (cinEl.value && cinEl.value < PMS.today) cinEl.value = PMS.today;
  const minOut = addDaysISO(cinEl.value || PMS.today, 1);
  coutEl.min = minOut;
  if (!coutEl.value || coutEl.value <= cinEl.value) coutEl.value = minOut;
}

function validateDetailCapacity() {
  if (!activeDetailBooking) return;
  const b = activeDetailBooking;
  const cat = (PMS.categories || []).find(c => Number(c.id) === Number(b.category_id));
  const rc = Math.max(1, Number(b.rooms_count || 1));
  const adults = Number(document.getElementById('dtAdults').value || 0);
  const children = Number(document.getElementById('dtChildren').value || 0);
  const warn = document.getElementById('dtCapWarn');
  const btn = document.getElementById('dtSubmitBtn');
  if (!cat || !warn || !btn) return;
  const maxAd = Number(cat.max_adults || 2) * rc;
  const maxCh = Number(cat.max_children ?? 1);
  const maxTot = (Number(cat.max_adults || 2) + maxCh) * rc;
  if (adults < 1 || children < 0) {
    warn.textContent = '⚠ Số lượng khách không hợp lệ (tối thiểu 1 người lớn).';
    warn.classList.remove('hidden');
    btn.disabled = true;
  } else if (adults > maxAd || (adults + children) > maxTot) {
    warn.textContent = `⚠ Vượt sức chứa (${rc} phòng ${b.category_name} tối đa ${maxAd} người lớn, tổng ${maxTot} khách).`;
    warn.classList.remove('hidden');
    btn.disabled = true;
  } else {
    warn.classList.add('hidden');
    btn.disabled = false;
  }
}

function openDetailModal(bookingId) {
  const b = findBooking(bookingId);
  if (!b) return;
  activeDetailBooking = b;
  document.getElementById('detailForm').action = '/reception/bookings/' + bookingId + '/update-info';
  document.getElementById('dtTitle').textContent = 'Chi tiết & Sửa thông tin · ' + b.booking_code;
  document.getElementById('dtSub').innerHTML = `${escHtml(b.category_name)} · ${escHtml(b.package_name)} · Phòng: <strong>${b.assigned_room_number ? 'P.' + escHtml(b.assigned_room_number) : 'Chưa gán'}</strong>`;

  document.getElementById('dtName').value = b.guest_name || '';
  document.getElementById('dtPhone').value = b.guest_phone || '';
  document.getElementById('dtEmail').value = b.guest_email || '';
  document.getElementById('dtIdNum').value = b.guest_id_number || '';
  document.getElementById('dtArrival').value = b.arrival_time || '';
  document.getElementById('dtFlight').value = b.flight_number || '';

  const canChangeDates = b.status === 'pending' || b.status === 'confirmed';
  const cinEl = document.getElementById('dtCheckIn');
  const coutEl = document.getElementById('dtCheckOut');
  cinEl.min = PMS.today;
  cinEl.value = b.check_in_date || '';
  const minOutRef = (cinEl.value && cinEl.value >= PMS.today) ? cinEl.value : PMS.today;
  coutEl.min = addDaysISO(minOutRef, 1);
  coutEl.value = b.check_out_date || '';
  cinEl.disabled = !canChangeDates;
  coutEl.disabled = !canChangeDates;

  document.getElementById('dtAdults').value = b.adults || 2;
  document.getElementById('dtChildren').value = b.children || 0;
  document.getElementById('dtRequests').value = b.special_requests || '';
  document.getElementById('dtSyncWrap').classList.toggle('hidden', !b.customer_id);
  validateDetailCapacity();

  const hist = b.booking_history || [];
  const histSec = document.getElementById('dtHistorySection');
  if (hist.length > 0) {
    document.getElementById('dtHistoryTitle').textContent = `Lịch sử thao tác & thay đổi (${hist.length})`;
    document.getElementById('dtHistoryList').innerHTML = hist.map(h => `
      <div class="p-2.5 rounded-lg bg-[#FAF7F0] border border-[#EFE9DC] flex flex-wrap justify-between gap-2">
        <div><span class="font-semibold text-[#0E1D33]">${escHtml(h.actor_name)}</span> <span class="text-slate-600">— ${escHtml(h.details)}</span></div>
        <span class="text-[11px] text-slate-400 tabular-nums">${escHtml(String(h.created_at || '').slice(0, 16))}</span>
      </div>
    `).join('');
    histSec.classList.remove('hidden');
  } else {
    histSec.classList.add('hidden');
  }

  openModal('detailModal');
}

// ------------------------------------------------------------------
// DESK SERVICE MODAL
// ------------------------------------------------------------------
let dsCurrentGroup = 'spa';
function openDeskServiceModal(initialBookingId) {
  const activeBks = (PMS.bookings || []).filter(b => b.status === 'checked_in' || (b.status === 'confirmed' && !b.is_overdue));
  const bSel = document.getElementById('dsBookingId');
  bSel.innerHTML = activeBks.length === 0
    ? '<option value="">Chưa có phòng đang ở / đã xác nhận</option>'
    : activeBks.map(b => `<option value="${b.id}">${b.assigned_room_number ? 'P.' + escHtml(b.assigned_room_number) : 'Chưa gán'} · ${escHtml(b.booking_code)} · ${escHtml(b.guest_name)}</option>`).join('');
  if (initialBookingId) bSel.value = String(initialBookingId);
  document.getElementById('dsQty').value = '1';
  setDeskMainGroup('spa');
  openModal('deskServiceModal');
}

function setDeskMainGroup(grp) {
  dsCurrentGroup = grp;
  document.querySelectorAll('.ds-main-btn').forEach(btn => {
    const active = btn.getAttribute('data-ds-main') === grp;
    btn.className = 'ds-main-btn px-3 py-2 rounded-lg border text-left font-semibold ' +
      (active ? 'bg-[#0E1D33] text-white border-[#0E1D33]' : 'bg-[#FAF7F0] text-[#0E1D33] border-[#E6DFD1] hover:border-[#C9A45C]');
  });

  const filtered = (PMS.services || []).filter(s => {
    if (Number(s.is_active) === 0) return false;
    const cat = String(s.category || '');
    if (grp === 'spa') return cat === 'spa';
    if (grp === 'dining') return cat.startsWith('dining_');
    if (grp === 'utilities') return cat === 'addon' || cat === 'prep_option';
    if (grp === 'experience') return cat === 'experience' || cat === 'combo';
    return false;
  });

  const sSel = document.getElementById('dsServiceId');
  sSel.innerHTML = filtered.map(s => {
    const p = Number(s.effective_price ?? s.promo_price ?? s.price ?? 0);
    return `<option value="${s.id}" data-price="${p}">${escHtml(s.name)} (${fmtVND(p)}/${escHtml(s.unit_label)})</option>`;
  }).join('');
  recalcDeskServicePrice();
}

function recalcDeskServicePrice() {
  const sel = document.getElementById('dsServiceId');
  const opt = sel.options[sel.selectedIndex];
  const unitPrice = opt ? Number(opt.getAttribute('data-price') || 0) : 0;
  const qty = Math.max(1, Number(document.getElementById('dsQty').value || 1));
  const sub = unitPrice * qty;
  const tb = computeTax(sub, 13.4);
  document.getElementById('dsPriceSummary').innerHTML = `
    <div class="flex justify-between"><span class="text-slate-600">Đơn giá dịch vụ (${qty} &times; ${fmtVND(unitPrice)}):</span><span class="font-semibold tabular-nums">${fmtVND(sub)}</span></div>
    <div class="flex justify-between text-slate-500"><span>Phí phục vụ 5% + Thuế VAT 8% (13.4%):</span><span class="tabular-nums">+${fmtVND(tb.totalTax)}</span></div>
    <div class="flex justify-between text-sm font-bold text-[#0E1D33] pt-1 border-t border-[#E6DFD1]"><span>Tổng cộng vào hóa đơn:</span><span class="text-[#A8843E] tabular-nums">${fmtVND(tb.grandTotal)}</span></div>
  `;
}

// ------------------------------------------------------------------
// CANCEL BOOKING MODAL (NHẬP LÝ DO + HIỂN THỊ CHÍNH SÁCH HOÀN CỌC)
// ------------------------------------------------------------------
function openCancelBookingModal(bookingId, defaultReason) {
  const b = findBooking(bookingId);
  if (!b) return;
  document.getElementById('cancelBookingForm').action = '/reception/bookings/' + bookingId + '/resolve-request';
  const willRequireCustomerConfirm = Boolean(b.customer_id) && b.change_request_status !== 'cancel_pending';
  document.getElementById('cbTitle').textContent = willRequireCustomerConfirm
    ? 'Gửi đề nghị hủy tới khách · ' + b.booking_code
    : 'Hủy đặt phòng · ' + b.booking_code;
  document.getElementById('cbSub').innerHTML = `Khách: <strong>${escHtml(b.guest_name)}</strong> (${escHtml(b.guest_phone)}) · ${escHtml(b.check_in_date)} → ${escHtml(b.check_out_date)}`;
  document.getElementById('cbReason').value = defaultReason || '';

  const flowEl = document.getElementById('cbFlowHint');
  const submitBtn = document.getElementById('cbSubmitBtn');
  if (flowEl && submitBtn) {
    if (willRequireCustomerConfirm) {
      flowEl.className = 'p-3 rounded-xl border border-sky-200 bg-sky-50 text-sky-900 text-[11px] leading-relaxed';
      flowEl.innerHTML = '<strong>Xác nhận 2 chiều với khách hàng:</strong> Đơn chưa có yêu cầu tự hủy từ khách. Khi bấm gửi, hệ thống sẽ chuyển đơn sang trạng thái <strong>Chờ khách xác nhận hủy</strong> và gửi thông báo kèm lý do tới Cổng khách hàng để khách chọn <em>Đồng ý hủy</em> hoặc <em>Không đồng ý · Giữ lại đơn</em>.';
      submitBtn.textContent = 'GỬI ĐỀ NGHỊ HỦY TỚI KHÁCH';
    } else {
      flowEl.className = 'p-3 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-[11px] leading-relaxed';
      flowEl.innerHTML = b.change_request_status === 'cancel_pending'
        ? 'Khách hàng đã gửi yêu cầu hủy trước đó — xác nhận này sẽ hoàn tất hủy đơn, giải phóng phòng và gửi thông báo.'
        : 'Đơn khách tại quầy không có tài khoản trực tuyến — xác nhận này sẽ hủy đơn trực tiếp và giải phóng phòng gán.';
      submitBtn.textContent = 'XÁC NHẬN HỦY & GỬI KHÁCH';
    }
  }

  const freeDays = Number(PMS.policies?.free_cancel_days ?? 14);
  const latePct = Number(PMS.policies?.late_cancel_fee_percent ?? 50);
  const paid = Number(b.total_paid || 0);
  const nights = Math.max(1, Number(b.nights || 1));
  const firstNight = Math.round(Number(b.room_subtotal || 0) / nights);
  const daysUntil = diffDaysISO(PMS.today, b.check_in_date);
  const isFree = daysUntil >= freeDays;
  const penalty = isFree ? 0 : Math.min(paid, Math.round(firstNight * (latePct / 100)));
  const refund = Math.max(0, paid - penalty);

  const box = document.getElementById('cbPolicyBox');
  if (isFree) {
    box.className = 'p-3.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-[11px] space-y-1';
    box.innerHTML = `<div class="font-bold">✓ Hủy miễn phí (trước ≥ ${freeDays} ngày, còn ${Math.max(0, daysUntil)} ngày)</div>`
      + `<div>• Đã thu của khách: <strong class="tabular-nums">${fmtVND(paid)}</strong> · Phí hủy giữ lại: <strong>0 đ</strong></div>`
      + `<div>• Số tiền hoàn lại cho khách: <strong class="tabular-nums text-emerald-700">${fmtVND(refund)}</strong> (sẽ hoàn tất hoặc bảo lưu theo thỏa thuận).</div>`
      + (b.assigned_room_number ? `<div>• Phòng <strong>P.${escHtml(b.assigned_room_number)}</strong> sẽ được giải phóng ngay để gán cho đơn khác.</div>` : '');
  } else {
    box.className = 'p-3.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-[11px] space-y-1';
    box.innerHTML = `<div class="font-bold">⚠ Hủy muộn (còn ${Math.max(0, daysUntil)} ngày &lt; ${freeDays} ngày quy định)</div>`
      + `<div>• Đã thu của khách: <strong class="tabular-nums">${fmtVND(paid)}</strong> · Giữ lại ${latePct}% đêm đầu: <strong class="tabular-nums text-rose-700">${fmtVND(penalty)}</strong></div>`
      + `<div>• Số tiền hoàn lại cho khách: <strong class="tabular-nums text-emerald-700">${fmtVND(refund)}</strong>.</div>`
      + (b.assigned_room_number ? `<div>• Phòng <strong>P.${escHtml(b.assigned_room_number)}</strong> sẽ được giải phóng ngay để gán cho đơn khác.</div>` : '');
  }

  openModal('cancelBookingModal');
}

// ------------------------------------------------------------------
// COLLECT PAYMENT MODAL
// ------------------------------------------------------------------
let cpState = { rem: 0, srvDue: 0 };

function openCollectModal(bookingId) {
  const b = findBooking(bookingId);
  if (!b) return;
  const grandTotal = computeFolioTotal(b);
  const paid = Number(b.total_paid || 0);
  const rem = Math.max(0, grandTotal - paid);
  const taxRate = Number(b.locked_tax_rate || 13.4);

  const nonPrebookedOrders = (b.service_orders || []).filter(so => so.status !== 'cancelled' && !Number(so.is_prebooked));
  const srvRaw = nonPrebookedOrders.reduce((s, so) => s + Number(so.total_price || 0), 0);
  const srvDisc = Number(b.service_discount_total || 0);
  const srvNet = Math.max(0, srvRaw - srvDisc);
  const srvTax = computeTax(srvNet, taxRate).totalTax;
  const srvDue = srvNet + srvTax;
  cpState = { rem, srvDue };

  document.getElementById('collectPaymentForm').action = '/reception/bookings/' + bookingId + '/payments';
  document.getElementById('cpTitle').textContent = 'Thu tiền tại quầy · ' + b.booking_code;
  document.getElementById('cpSub').innerHTML = `Khách: <strong>${escHtml(b.guest_name)}</strong> ${b.assigned_room_number ? '· P.' + escHtml(b.assigned_room_number) : ''}`;
  document.getElementById('cpSummaryBox').innerHTML = `
    <div class="flex justify-between"><span class="text-slate-600">Tổng dự kiến hóa đơn (gồm ${taxRate}% phí/thuế):</span><span class="font-bold text-[#0E1D33] tabular-nums">${fmtVND(grandTotal)}</span></div>
    <div class="flex justify-between"><span class="text-emerald-700">Đã thu trước đó:</span><span class="font-bold text-emerald-700 tabular-nums">-${fmtVND(paid)}</span></div>
    <div class="flex justify-between text-sm font-bold ${rem > 0 ? 'text-rose-700' : 'text-emerald-700'} pt-1 border-t border-[#E6DFD1]"><span>Còn phải thu:</span><span class="tabular-nums">${fmtVND(rem)}</span></div>
  `;

  const srvBox = document.getElementById('cpServiceBreakdown');
  if (nonPrebookedOrders.length > 0 && rem > 0) {
    srvBox.innerHTML = `
      <div class="font-bold uppercase tracking-wider text-[#A8843E]">Chi tiết dịch vụ phát sinh (${nonPrebookedOrders.length} lượt · áp dụng ${taxRate}% phí &amp; VAT)</div>
      <div class="space-y-1 max-h-28 overflow-y-auto pr-1">
        ${nonPrebookedOrders.map(so => `
          <div class="flex justify-between text-slate-700">
            <span>${escHtml(so.service_name_snapshot)} &times; ${Number(so.quantity)} <span class="text-slate-400">(${escHtml(so.status)})</span></span>
            <span class="tabular-nums font-medium">${fmtVND(so.total_price)}</span>
          </div>
        `).join('')}
      </div>
      ${srvDisc > 0 ? `<div class="flex justify-between text-emerald-700"><span>Giảm giá Voucher dịch vụ:</span><span class="tabular-nums">-${fmtVND(srvDisc)}</span></div>` : ''}
      <div class="flex justify-between text-slate-600 pt-1 border-t border-[#EFE9DC]">
        <span>Tạm tính DV: ${fmtVND(srvNet)} + Phí 5% &amp; VAT 8% (${fmtVND(srvTax)})</span>
        <strong class="text-[#0E1D33] tabular-nums">${fmtVND(srvDue)}</strong>
      </div>
    `;
    srvBox.classList.remove('hidden');
  } else if (nonPrebookedOrders.length > 0 && rem <= 0) {
    srvBox.innerHTML = `<div class="text-emerald-800 font-medium">Các dịch vụ phát sinh (${nonPrebookedOrders.length} lượt, tổng ${fmtVND(srvDue)} gồm ${taxRate}% thuế/phí) đã được thanh toán gộp trong hóa đơn.</div>`;
    srvBox.classList.remove('hidden');
  } else {
    srvBox.classList.add('hidden');
  }

  const paidBanner = document.getElementById('cpPaidBanner');
  const extraChk = document.getElementById('cpExtraAdjChk');
  extraChk.checked = false;
  paidBanner.classList.toggle('hidden', rem > 0);

  document.getElementById('cpAmount').value = String(rem > 0 ? rem : 0);
  document.getElementById('cpType').value = 'settlement';
  document.getElementById('cpMismatchNote').value = '';
  document.getElementById('cpNote').value = 'Thu tiền tại quầy Lễ tân';
  const cpMeth = document.getElementById('cpMethod');
  if (cpMeth) {
    cpMeth.value = 'cash';
    document.getElementById('cpQrHint')?.classList.add('hidden');
  }

  let quickHtml = '';
  if (rem > 0) {
    quickHtml += `<button type="button" onclick="fillCollectAmount(${rem}, 'settlement', 'Thanh toán phần còn lại tại quầy')" class="px-2 py-1 rounded bg-[#FAF7F0] border border-[#C9A45C] text-[11px] font-semibold text-[#0E1D33]">Thu đủ còn lại (${fmtVND(rem)})</button>`;
    if (srvDue > 0) {
      const capSrv = Math.min(rem, srvDue);
      quickHtml += `<button type="button" onclick="fillCollectAmount(${capSrv}, 'partial', 'Thu riêng chi phí dịch vụ phát sinh tại quầy')" class="px-2 py-1 rounded bg-white border border-slate-300 text-[11px] font-semibold text-slate-700">Thu riêng dịch vụ (${fmtVND(capSrv)})</button>`;
    }
  }
  document.getElementById('cpQuickFillBtns').innerHTML = quickHtml;
  checkCollectVariance();
  openModal('collectPaymentModal');
}

function checkCollectVariance() {
  const amt = Number(document.getElementById('cpAmount').value || 0);
  const type = document.getElementById('cpType').value;
  const extraChk = document.getElementById('cpExtraAdjChk').checked;
  const varBox = document.getElementById('cpVarianceBox');
  const varMsg = document.getElementById('cpVarianceMsg');
  const noteEl = document.getElementById('cpMismatchNote');
  const btn = document.getElementById('cpSubmitBtn');

  if (cpState.rem <= 0) {
    if (!extraChk) {
      varBox.classList.add('hidden');
      noteEl.required = false;
      btn.disabled = true;
      return;
    }
    varMsg.textContent = `⚠ Hóa đơn đã hết dư nợ (0 đ). Vui lòng nhập lý do thu bổ sung ${fmtVND(amt)}:`;
    varBox.classList.remove('hidden');
    noteEl.required = true;
    btn.disabled = amt < 1000;
    return;
  }

  btn.disabled = amt < 1000;
  const expected = (type === 'partial' && cpState.srvDue > 0) ? Math.min(cpState.rem, cpState.srvDue) : cpState.rem;
  const diff = Math.abs(amt - expected);
  if (diff > 1000) {
    varMsg.textContent = `⚠ Số tiền nhập (${fmtVND(amt)}) chênh lệch ${fmtVND(diff)} so với mức dự kiến (${fmtVND(expected)}).`;
    varBox.classList.remove('hidden');
    noteEl.required = true;
  } else {
    varBox.classList.add('hidden');
    noteEl.required = false;
  }
}

function fillCollectAmount(amt, type, note) {
  document.getElementById('cpAmount').value = String(amt);
  document.getElementById('cpType').value = type;
  document.getElementById('cpNote').value = note;
  checkCollectVariance();
}

// ------------------------------------------------------------------
// INVOICE MODAL
// ------------------------------------------------------------------
function openInvoiceModal(bookingId) {
  const b = findBooking(bookingId);
  if (!b) return;
  const grandTotal = computeFolioTotal(b);
  const paid = Number(b.total_paid || 0);
  const rem = Math.max(0, grandTotal - paid);

  const billableSrv = (b.service_orders || []).filter(so => so.status === 'completed' || (Number(so.is_prebooked) === 1 && so.status !== 'cancelled'));
  const srvNet = billableSrv.reduce((s, so) => s + Math.max(0, Number(so.total_price || 0) - Number(so.discount_amount || 0)), 0);
  const roomCharges = Number(b.room_subtotal || 0);
  const extFee = Number(b.extension_fee || 0);
  const surcharges = Number(b.invoice?.surcharges || 0);
  const disc = Number(b.discount_amount || 0);
  const taxRate = Number(b.locked_tax_rate || 13.4);
  const taxable = Math.max(0, roomCharges + extFee + srvNet + surcharges - disc);
  const tb = computeTax(taxable, taxRate);

  document.getElementById('invSub').textContent = `Mã đơn: ${b.booking_code} · Phòng: ${b.assigned_room_number ? 'P.' + b.assigned_room_number : 'Chưa gán'} · Khách: ${b.guest_name}`;
  document.getElementById('invBody').innerHTML = `
    <div class="grid grid-cols-2 gap-3 p-4 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1]">
      <div><span class="text-slate-500">Khách hàng:</span> <strong class="text-[#0E1D33]">${escHtml(b.guest_name)}</strong></div>
      <div><span class="text-slate-500">SĐT:</span> <strong class="tabular-nums">${escHtml(b.guest_phone)}</strong></div>
      <div><span class="text-slate-500">Hạng phòng:</span> <strong>${escHtml(b.category_name)}</strong></div>
      <div><span class="text-slate-500">Gói giá:</span> <strong>${escHtml(b.package_name)}</strong></div>
      <div><span class="text-slate-500">Lưu trú:</span> <strong class="tabular-nums">${escHtml(b.check_in_date)} &rarr; ${escHtml(b.check_out_date)} (${b.nights} đêm)</strong></div>
      <div><span class="text-slate-500">Khách:</span> <strong>${b.adults} người lớn · ${b.children} trẻ em</strong></div>
    </div>

    <div class="border border-[#E6DFD1] rounded-xl overflow-hidden">
      <table class="w-full text-left border-collapse">
        <thead class="bg-[#FAF7F0] text-[10px] font-bold uppercase text-slate-500 border-b border-[#E6DFD1]">
          <tr><th class="py-2.5 px-3">Khoản mục chi phí</th><th class="py-2.5 px-3 text-right">Thành tiền</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr><td class="py-2.5 px-3">Tiền phòng (${b.nights} đêm &times; ${fmtVND(Number(b.locked_nightly_rate) + Number(b.locked_package_extra))})</td><td class="py-2.5 px-3 text-right font-semibold tabular-nums">${fmtVND(roomCharges)}</td></tr>
          ${extFee > 0 ? `<tr><td class="py-2.5 px-3">Phí gia hạn / Trả phòng muộn (${escHtml(b.checkout_time_slot || '12:00')})</td><td class="py-2.5 px-3 text-right font-semibold tabular-nums">${fmtVND(extFee)}</td></tr>` : ''}
          ${billableSrv.map(so => `<tr><td class="py-2.5 px-3">DV: ${escHtml(so.service_name_snapshot)} &times; ${so.quantity}</td><td class="py-2.5 px-3 text-right font-semibold tabular-nums">${fmtVND(Math.max(0, Number(so.total_price) - Number(so.discount_amount || 0)))}</td></tr>`).join('')}
          ${surcharges > 0 ? `<tr><td class="py-2.5 px-3">Phụ phí (${escHtml(b.invoice?.surcharge_note || 'Phát sinh')})</td><td class="py-2.5 px-3 text-right font-semibold tabular-nums">${fmtVND(surcharges)}</td></tr>` : ''}
          ${disc > 0 ? `<tr class="text-emerald-700"><td class="py-2.5 px-3">Ưu đãi Voucher (${escHtml(b.voucher_code)})</td><td class="py-2.5 px-3 text-right font-semibold tabular-nums">-${fmtVND(disc)}</td></tr>` : ''}
          <tr><td class="py-2.5 px-3 text-slate-600">Phí phục vụ (5%) &amp; Thuế GTGT VAT (8%) [${taxRate}%]</td><td class="py-2.5 px-3 text-right font-semibold tabular-nums">${fmtVND(tb.totalTax)}</td></tr>
          <tr class="bg-[#FAF7F0] font-bold text-sm text-[#0E1D33]"><td class="py-3 px-3">TỔNG CỘNG HÓA ĐƠN</td><td class="py-3 px-3 text-right tabular-nums">${fmtVND(grandTotal)}</td></tr>
          <tr class="text-emerald-700 font-semibold"><td class="py-2.5 px-3">Đã thanh toán / Đặt cọc</td><td class="py-2.5 px-3 text-right tabular-nums">-${fmtVND(paid)}</td></tr>
          <tr class="font-bold text-sm ${rem > 0 ? 'text-rose-700' : 'text-emerald-700'}"><td class="py-2.5 px-3">CÒN PHẢI THU</td><td class="py-2.5 px-3 text-right tabular-nums">${fmtVND(rem)}</td></tr>
        </tbody>
      </table>
    </div>

    ${(b.payments || []).length > 0 ? `
      <div class="space-y-1.5">
        <div class="font-bold text-[11px] uppercase tracking-wider text-slate-500">Lịch sử giao dịch thanh toán</div>
        ${(b.payments || []).map(p => {
          const vs = p.verification_status || 'pending';
          const vsLbl = vs === 'verified' ? 'Đã khớp' : (vs === 'rejected' ? 'Không khớp' : 'Chờ đối soát');
          return `
            <div class="flex justify-between items-start gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-200 tabular-nums">
              <div>
                <div><strong class="font-mono">${escHtml(p.payment_code)}</strong> · ${escHtml(PAY_LABELS[p.method] || p.method)} (${escHtml(vsLbl)})</div>
                ${p.note ? `<div class="text-[11px] text-slate-500 mt-0.5">${escHtml(p.note)}</div>` : ''}
              </div>
              <strong class="text-[#0E1D33] shrink-0">${fmtVND(p.amount)}</strong>
            </div>
          `;
        }).join('')}
      </div>
    ` : ''}

    ${b.refund ? `
      <div class="p-3.5 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] space-y-1 text-xs">
        <div class="flex justify-between font-bold text-[#0E1D33]">
          <span>Hồ sơ hoàn tiền ${escHtml(b.refund.refund_code)}</span>
          <span>${escHtml(b.refund.status === 'processed' ? 'Đã hoàn tiền' : (b.refund.status === 'approved' ? 'Đã duyệt · Chờ chi' : (b.refund.status === 'rejected' ? 'Từ chối' : 'Chờ phê duyệt')))}</span>
        </div>
        <div class="flex justify-between"><span>Đã thu hợp lệ:</span><span class="tabular-nums">${fmtVND(b.refund.paid_verified_amount)}</span></div>
        <div class="flex justify-between"><span>Phí hủy giữ lại:</span><span class="tabular-nums text-rose-700">-${fmtVND(b.refund.retained_fee)}</span></div>
        <div class="flex justify-between font-bold"><span>Số tiền hoàn:</span><span class="tabular-nums text-emerald-700">${fmtVND(b.refund.refund_amount)}</span></div>
      </div>
    ` : ''}
  `;
  openModal('invoiceModal');
}

// ------------------------------------------------------------------
// SERVICE CANCEL REASON & PAYMENT RECONCILE MODALS
// ------------------------------------------------------------------
function openCancelServiceModal(orderId) {
  const so = (PMS.serviceOrders || []).find(s => Number(s.id) === Number(orderId));
  if (!so) return;
  document.getElementById('cancelServiceForm').action = '/reception/service-orders/' + orderId + '/status';
  document.getElementById('csTitle').textContent = 'Hủy yêu cầu dịch vụ · ' + (so.booking_code || '');
  document.getElementById('csSub').innerHTML = `<strong>${escHtml(so.service_name_snapshot)}</strong> &times; ${so.quantity} · ${so.room_number ? 'P.' + escHtml(so.room_number) : 'Chưa gán phòng'} · Khách: <strong>${escHtml(so.guest_name || '')}</strong>`;
  document.getElementById('csReason').value = '';
  openModal('cancelServiceModal');
}

function setCsPreset(txt) {
  const el = document.getElementById('csReason');
  if (el) el.value = txt;
}

let inspectedPaymentId = null;

function selectInspectedPayment(paymentId) {
  inspectedPaymentId = Number(paymentId);
  document.querySelectorAll('.tx-row').forEach(row => {
    const isSel = Number(row.getAttribute('data-pid')) === inspectedPaymentId;
    row.classList.toggle('bg-[#FAF7F0]', isSel);
  });
  document.querySelectorAll('[data-inspect-btn]').forEach(btn => {
    const isSel = Number(btn.getAttribute('data-inspect-btn')) === inspectedPaymentId;
    btn.className = 'px-2.5 py-1 rounded text-[11px] font-semibold border whitespace-nowrap ' +
      (isSel ? 'bg-[#0E1D33] text-white border-[#0E1D33]' : 'bg-white text-[#0E1D33] border-slate-300 hover:border-[#0E1D33]');
  });
  renderReconcileInspector();
}

function renderReconcileInspector() {
  const host = document.getElementById('reconcileInspectorPanel');
  if (!host) return;
  const list = PMS.pendingPayments || [];
  if (list.length === 0) {
    host.innerHTML = '';
    return;
  }
  const p = list.find(x => Number(x.id) === Number(inspectedPaymentId))
    || list.find(x => (x.verification_status || 'pending') === 'pending')
    || list[0];
  if (!p) {
    host.innerHTML = '';
    return;
  }
  inspectedPaymentId = Number(p.id);
  const verif = p.verification_status || 'pending';
  const linked = findBooking(p.booking_id);
  const orderTotal = linked ? computeFolioTotal(linked) : Number(p.invoice_total_amount || p.total_estimated || 0);
  const allOrderPayments = (linked && linked.payments) ? linked.payments : list.filter(x => Number(x.booking_id) === Number(p.booking_id));
  const verifiedExcl = allOrderPayments
    .filter(pm => Number(pm.id) !== Number(p.id) && (pm.verification_status || 'pending') === 'verified')
    .reduce((s, pm) => s + Number(pm.amount || 0), 0);
  const pAmt = Number(p.amount || 0);
  const projectedTotal = verifiedExcl + pAmt;
  const remainingBefore = Math.max(0, orderTotal - verifiedExcl);
  const depReq = Number(p.deposit_required ?? linked?.deposit_required ?? 0);
  const payTypeLbl = p.payment_type === 'deposit' ? 'Đặt cọc 30%' : (p.payment_type === 'settlement' ? 'Tất toán' : 'Thu bổ sung');
  const expectedMemo = (p.booking_code || '') + (p.payment_type === 'deposit' ? ' COC' : '');
  const bankInfo = PMS.bankInfo || { bankName: 'BIDV', accountNo: '75010008889999', accountHolder: 'MUONG THANH LUXURY PHU QUOC' };

  let diagCls = 'bg-sky-50 border-sky-200 text-sky-900';
  let diagMsg = `Thanh toán một phần — cộng vào đơn đạt ${fmtVND(projectedTotal)} / ${fmtVND(orderTotal)} tổng hóa đơn.`;
  if (orderTotal > 0 && projectedTotal > orderTotal + 1000) {
    diagCls = 'bg-rose-50 border-rose-200 text-rose-900';
    diagMsg = `Cảnh báo vượt tổng hóa đơn ${fmtVND(projectedTotal - orderTotal)} — cần kiểm tra lại số tiền trên sao kê trước khi xác nhận.`;
  } else if (orderTotal > 0 && Math.abs(pAmt - remainingBefore) <= 1000) {
    diagCls = 'bg-emerald-50 border-emerald-200 text-emerald-900';
    diagMsg = 'Khớp 100% phần còn phải thu trên hóa đơn (đủ điều kiện tất toán toàn bộ đơn).';
  } else if (p.payment_type === 'deposit' && depReq > 0 && pAmt + 1000 >= depReq) {
    diagCls = 'bg-emerald-50 border-emerald-200 text-emerald-900';
    diagMsg = `Khớp đủ mức tiền cọc 30% yêu cầu (${fmtVND(depReq)}).`;
  } else if (depReq > 0 && projectedTotal < depReq) {
    diagCls = 'bg-amber-50 border-amber-200 text-amber-900';
    diagMsg = `Chưa đủ mức cọc tối thiểu 30% (${fmtVND(depReq)}) — sau giao dịch này vẫn còn thiếu ${fmtVND(depReq - projectedTotal)}.`;
  }

  const verifBadge = verif === 'verified'
    ? '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Đã nhận tiền</span>'
    : (verif === 'rejected'
      ? '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">Không khớp sao kê</span>'
      : '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900">Chờ đối soát</span>');

  const otherPaymentsHtml = allOrderPayments.length === 0
    ? '<div class="text-slate-400">Chưa có giao dịch khác.</div>'
    : allOrderPayments.map(pm => {
      const pst = pm.verification_status || 'pending';
      const pstCls = pst === 'verified' ? 'bg-emerald-50 text-emerald-700' : (pst === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800');
      const pstLbl = pst === 'verified' ? 'Đã khớp' : (pst === 'rejected' ? 'Không khớp' : 'Chờ đối soát');
      return `
        <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg border ${Number(pm.id) === Number(p.id) ? 'bg-[#FAF7F0] border-[#C9A45C]' : 'bg-slate-50 border-slate-200'}">
          <div class="min-w-0 truncate">
            <span class="font-mono font-bold text-[#0E1D33]">${escHtml(pm.payment_code)}</span> · ${escHtml(PAY_LABELS[pm.method] || pm.method)}
            ${pm.reference_no ? `<span class="font-mono text-slate-500">· ${escHtml(pm.reference_no)}</span>` : ''}
          </div>
          <div class="flex items-center gap-2 shrink-0 tabular-nums">
            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold ${pstCls}">${pstLbl}</span>
            <strong class="text-[#0E1D33]">${fmtVND(pm.amount)}</strong>
          </div>
        </div>
      `;
    }).join('');

  host.innerHTML = `
    <div class="bg-[#FAF7F0] border border-[#E6DFD1] rounded-2xl p-5 space-y-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#A8843E]">CHI TIẾT ĐỐI SOÁT GIAO DỊCH</div>
          <h3 class="font-serif text-xl font-semibold text-[#0E1D33] mt-0.5">
            <span class="font-mono">${escHtml(p.payment_code)}</span> · Booking <span class="font-mono">${escHtml(p.booking_code)}</span> (${escHtml(p.guest_name)})
          </h3>
        </div>
        <div class="flex items-center gap-2">
          ${verifBadge}
          ${linked ? `<button type="button" onclick="openInvoiceModal(${Number(linked.id)})" class="px-3 py-1 rounded-lg bg-white border border-[#E6DFD1] hover:border-[#0E1D33] text-[11px] font-semibold text-[#0E1D33]">Xem hóa đơn đầy đủ</button>` : ''}
        </div>
      </div>

      <div class="p-3 rounded-xl border text-xs font-medium ${diagCls}">
        <strong>Chẩn đoán đối soát tự động:</strong> ${escHtml(diagMsg)}
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 text-xs">
        <div class="bg-white border border-[#E6DFD1] rounded-xl p-4 space-y-2 tabular-nums">
          <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">1. Chứng từ giao dịch &amp; Tài khoản nhận (VietQR)</div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Số tiền cần kiểm trên sao kê:</span><strong class="text-base text-[#0E1D33]">${fmtVND(pAmt)}</strong></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Loại giao dịch / Phương thức:</span><strong class="text-[#0E1D33]">${escHtml(payTypeLbl)} · ${escHtml(PAY_LABELS[p.method] || p.method)}</strong></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Mã tham chiếu / Bút toán (FT):</span><span class="font-mono font-bold text-[#0E1D33]">${escHtml(p.reference_no || 'Chưa có')}</span></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Nội dung CK kỳ vọng:</span><span class="font-mono font-semibold text-[#A8843E]">${escHtml(expectedMemo)}</span></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Thời gian ghi nhận:</span><span>${escHtml(String(p.recorded_at || '').slice(0, 16))}${p.recorded_by_name ? ' · ' + escHtml(p.recorded_by_name) : ''}</span></div>
          <div class="pt-2 border-t border-slate-100 space-y-1 text-[11px] text-slate-600">
            <div><strong class="text-[#0E1D33]">Tài khoản nhận của khách sạn:</strong> ${escHtml(bankInfo.bankName)}</div>
            <div>STK: <strong class="font-mono text-[#0E1D33]">${escHtml(bankInfo.accountNo)}</strong> · Chủ TK: <strong class="text-[#0E1D33]">${escHtml(bankInfo.accountHolder)}</strong></div>
          </div>
          ${p.note ? `<div class="p-2 rounded-lg bg-slate-50 border border-slate-200 text-[11px] text-slate-700"><strong>Ghi chú hiện tại:</strong> ${escHtml(p.note)}</div>` : ''}
        </div>

        <div class="bg-white border border-[#E6DFD1] rounded-xl p-4 space-y-2 tabular-nums">
          <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">2. Công nợ đơn đặt phòng &amp; Các lần thu khác</div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Lưu trú / Phòng:</span><strong class="text-[#0E1D33]">${escHtml(p.check_in_date || linked?.check_in_date || '—')} &rarr; ${escHtml(p.check_out_date || linked?.check_out_date || '—')} ${p.room_number ? '· P.' + escHtml(p.room_number) : ''}</strong></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Tổng giá trị hóa đơn (Folio):</span><strong class="text-[#0E1D33]">${fmtVND(orderTotal)}</strong></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Mức cọc 30% theo quy định:</span><strong class="text-[#0E1D33]">${fmtVND(depReq)}</strong></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Các khoản đã khớp khác trên đơn:</span><strong class="text-emerald-700">${fmtVND(verifiedExcl)}</strong></div>
          <div class="flex justify-between gap-2"><span class="text-slate-500">Tổng đã thu nếu khớp giao dịch này:</span><strong class="text-sky-700">${fmtVND(projectedTotal)} (Còn lại: ${fmtVND(Math.max(0, orderTotal - projectedTotal))})</strong></div>
          <div class="pt-2 border-t border-slate-100 space-y-1.5">
            <div class="text-[11px] font-semibold text-slate-600">Tất cả giao dịch của đơn ${escHtml(p.booking_code)} (${allOrderPayments.length})</div>
            <div class="space-y-1 max-h-32 overflow-y-auto">${otherPaymentsHtml}</div>
          </div>
        </div>
      </div>

      ${verif === 'pending' ? `
        <form method="POST" action="/reception/payments/${Number(p.id)}/verify" class="bg-white border border-[#E6DFD1] rounded-xl p-4 space-y-3 text-xs">
          <input type="hidden" name="return_tab" value="payments" />
          <input type="hidden" name="decision" id="inspDecisionInput" value="verified" />
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Số bút toán sao kê ngân hàng (FT / Trace)</label>
              <input type="text" name="reference_no" value="${escHtml(p.reference_no || '')}" placeholder="VD: FT263218890123 / BIDV-1425..." class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Ghi chú / Lý do không khớp (tối thiểu 3 ký tự nếu Không khớp)</label>
              <input type="text" name="note" id="inspNoteInput" value="Đã đối chiếu khớp sao kê ngân hàng" placeholder="VD: Đã khớp sao kê BIDV lúc 14:25 hoặc Chưa nhận được tiền..." class="w-full px-3 py-2 border border-slate-300 rounded-lg" />
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="submit" onclick="document.getElementById('inspDecisionInput').value='verified';" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold whitespace-nowrap">Xác nhận Đã nhận tiền</button>
            <button type="button" onclick="openVerifyPaymentModal(${Number(p.id)}, 'rejected')" class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg font-bold whitespace-nowrap">Không khớp sao kê...</button>
          </div>
        </form>
      ` : `
        <div class="p-3 rounded-xl bg-slate-100 border border-slate-200 text-xs text-slate-600">
          Giao dịch đã được đối soát hoàn tất (<strong>${verif === 'verified' ? 'Đã nhận tiền' : 'Không khớp sao kê'}</strong>) và không thể xác minh lại.
        </div>
      `}
    </div>
  `;
}

function openVerifyPaymentModal(paymentId, decision) {
  const p = (PMS.pendingPayments || []).find(x => Number(x.id) === Number(paymentId));
  if (!p) return;
  selectInspectedPayment(paymentId);
  const isVerif = decision === 'verified';
  const linked = findBooking(p.booking_id);
  const orderTotal = linked ? computeFolioTotal(linked) : Number(p.invoice_total_amount || p.total_estimated || 0);
  const verifiedBefore = Number(p.booking_verified_paid ?? linked?.total_paid ?? 0);
  const depReq = Number(p.deposit_required ?? linked?.deposit_required ?? 0);
  const afterTotal = verifiedBefore + (isVerif ? Number(p.amount || 0) : 0);
  const remainingAfter = Math.max(0, orderTotal - afterTotal);

  document.getElementById('verifyPaymentForm').action = '/reception/payments/' + paymentId + '/verify';
  document.getElementById('vpDecision').value = decision;
  document.getElementById('vpRefNo').value = p.reference_no || '';
  document.getElementById('vpTitle').textContent = isVerif ? 'Xác nhận đã nhận tiền & khớp sao kê' : 'Đánh dấu giao dịch không khớp sao kê';
  document.getElementById('vpSub').innerHTML = `Mã GD: <strong class="font-mono">${escHtml(p.payment_code)}</strong> · Booking <strong class="font-mono">${escHtml(p.booking_code)}</strong> · ${escHtml(p.guest_name)}`;
  document.getElementById('vpInfoBox').innerHTML = `
    <div class="flex justify-between"><span class="text-slate-600">Số tiền giao dịch đang đối soát:</span><strong class="text-base text-[#0E1D33] tabular-nums">${fmtVND(p.amount)}</strong></div>
    <div class="flex justify-between"><span class="text-slate-600">Phương thức / Tham chiếu gốc:</span><span class="font-mono text-[#0E1D33]">${escHtml(PAY_LABELS[p.method] || p.method)} · ${escHtml(p.reference_no || '—')}</span></div>
    <div class="pt-1.5 border-t border-[#E6DFD1] grid grid-cols-2 gap-2 text-[11px] tabular-nums">
      <div><span class="text-slate-500">Tổng hóa đơn đơn này: </span><strong class="text-[#0E1D33]">${fmtVND(orderTotal)}</strong></div>
      <div><span class="text-slate-500">Yêu cầu cọc 30%: </span><strong class="text-[#0E1D33]">${fmtVND(depReq)}</strong></div>
      <div><span class="text-slate-500">Đã khớp trước GD này: </span><strong class="text-emerald-700">${fmtVND(verifiedBefore)}</strong></div>
      <div><span class="text-slate-500">Còn thu sau đối soát: </span><strong class="${remainingAfter === 0 ? 'text-emerald-700' : 'text-rose-700'}">${fmtVND(remainingAfter)}</strong></div>
    </div>
  `;
  const presetsEl = document.getElementById('vpRejectPresets');
  presetsEl.classList.toggle('hidden', isVerif);
  presetsEl.classList.toggle('flex', !isVerif);
  const noteEl = document.getElementById('vpNote');
  const noteLbl = document.getElementById('vpNoteLabel');
  const btn = document.getElementById('vpSubmitBtn');
  if (isVerif) {
    noteLbl.textContent = 'Ghi chú đối soát (ngân hàng / ca trực)';
    noteEl.required = false;
    noteEl.minLength = 0;
    noteEl.value = 'Đã đối chiếu khớp sao kê ngân hàng';
    btn.className = 'px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold uppercase whitespace-nowrap';
    btn.textContent = 'Xác nhận đã nhận tiền';
  } else {
    noteLbl.textContent = 'Lý do không khớp sao kê * (tối thiểu 3 ký tự)';
    noteEl.required = true;
    noteEl.minLength = 3;
    noteEl.value = '';
    noteEl.placeholder = 'Nhập nguyên nhân chưa khớp để khách hàng & kế toán kiểm tra lại...';
    btn.className = 'px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold uppercase whitespace-nowrap';
    btn.textContent = 'Xác nhận không khớp';
  }
  openModal('verifyPaymentModal');
}

function setVpPreset(txt) {
  const el = document.getElementById('vpNote');
  if (el) el.value = txt;
}

if (document.getElementById('reconcileInspectorPanel')) {
  renderReconcileInspector();
}

// ------------------------------------------------------------------
// HOUSEKEEPING & INCIDENT & RACK DRAWER & CUSTOMER HISTORY
// ------------------------------------------------------------------
function openHousekeepingModal(roomId) {
  if (roomId) document.getElementById('hkRoomId').value = String(roomId);
  openModal('housekeepingModal');
}

function openIncidentModal(roomId) {
  if (roomId) document.getElementById('incRoomId').value = String(roomId);
  syncIncidentRoomGuest();
  openModal('incidentModal');
}

function syncIncidentRoomGuest() {
  const rId = Number(document.getElementById('incRoomId').value);
  const activeBk = (PMS.bookings || []).find(b => Number(b.assigned_room_id) === rId && b.status === 'checked_in');
  document.getElementById('incBookingId').value = activeBk ? String(activeBk.id) : '';
  const noteEl = document.getElementById('incActiveGuestNote');
  if (activeBk) {
    noteEl.textContent = `Khách đang ở: ${activeBk.guest_name} (${activeBk.booking_code})`;
    noteEl.classList.remove('hidden');
  } else {
    noteEl.classList.add('hidden');
  }
}

function openRackRoomModal(roomId) {
  const rm = (PMS.rooms || []).find(r => Number(r.id) === Number(roomId));
  if (!rm) return;
  const rackDate = PMS.rackDate || PMS.today;
  const bOnDate = (PMS.bookings || []).find(b =>
    Number(b.assigned_room_id) === Number(rm.id) &&
    ['checked_in', 'confirmed'].includes(b.status) &&
    b.check_in_date <= rackDate && b.check_out_date >= rackDate
  );

  document.getElementById('rdTitle').textContent = `Phòng ${rm.room_number} · Tầng ${rm.floor}`;
  document.getElementById('rdSub').textContent = rm.category_name || '';

  const infoBox = document.getElementById('rdInfoBox');
  if (bOnDate) {
    infoBox.innerHTML = `
      <div class="font-bold text-[#0E1D33]">${escHtml(bOnDate.guest_name)} (${escHtml(bOnDate.booking_code)})</div>
      <div class="text-slate-600 tabular-nums">${escHtml(bOnDate.check_in_date)} &rarr; ${escHtml(bOnDate.check_out_date)}</div>
      <div class="text-[#A8843E] font-semibold">Trạng thái: ${escHtml(bOnDate.status)}</div>
    `;
  } else {
    infoBox.innerHTML = `Trạng thái phòng: <strong>${escHtml(rm.status)}</strong>`;
  }

  let btns = '';
  if (bOnDate && bOnDate.status === 'checked_in') {
    btns += `<button type="button" onclick="closeModal('rackDrawerModal'); openModifyStayModal(${bOnDate.id}, 'extend_stay')" class="w-full py-2.5 px-3 border border-[#C9A45C] bg-[#FAF7F0] rounded-lg font-bold text-[#0E1D33]">Gia hạn lưu trú / Đổi phòng</button>`;
    btns += `<button type="button" onclick="closeModal('rackDrawerModal'); openDeskServiceModal(${bOnDate.id})" class="w-full py-2.5 px-3 border border-slate-300 bg-white rounded-lg font-semibold text-[#0E1D33]">Đăng ký dịch vụ tại quầy cho phòng này</button>`;
    btns += `<button type="button" onclick="closeModal('rackDrawerModal'); openCheckOutModal(${bOnDate.id})" class="w-full py-2.5 px-3 bg-[#C9A45C] rounded-lg font-bold text-[#0E1D33]">Thanh toán &amp; Trả phòng</button>`;
  } else if (bOnDate && bOnDate.status === 'confirmed') {
    btns += `<button type="button" onclick="closeModal('rackDrawerModal'); openCheckInModal(${bOnDate.id})" class="w-full py-2.5 px-3 bg-[#C9A45C] rounded-lg font-bold text-[#0E1D33]">Nhận phòng (Check-in)</button>`;
  } else if (!bOnDate && rm.status === 'available') {
    btns += `<button type="button" onclick="closeModal('rackDrawerModal'); openWalkInModal(${rm.id})" class="w-full py-2.5 px-3 bg-[#0E1D33] text-white rounded-lg font-bold">+ Tạo đơn tại quầy cho Phòng ${escHtml(rm.room_number)}</button>`;
  }

  btns += `<button type="button" onclick="closeModal('rackDrawerModal'); openHousekeepingModal(${rm.id})" class="w-full py-2.5 px-3 border border-slate-300 bg-white rounded-lg font-semibold text-[#0E1D33]">Yêu cầu dọn phòng / Kiểm tra kỹ thuật</button>`;
  btns += `<button type="button" onclick="closeModal('rackDrawerModal'); openIncidentModal(${rm.id})" class="w-full py-2.5 px-3 border border-rose-200 bg-rose-50 rounded-lg font-semibold text-rose-800">Ghi nhận phản ánh / sự cố phòng</button>`;

  document.getElementById('rdActions').innerHTML = btns;
  openModal('rackDrawerModal');
}

function openCustomerModal(idx) {
  const c = (PMS.customers || [])[idx];
  if (!c) return;
  document.getElementById('cmTitle').textContent = c.full_name || 'Khách hàng';
  document.getElementById('cmSub').textContent = `${c.phone || ''} · ${c.email || 'Chưa có email'} · ${c.membership_tier || ''}`;
  document.getElementById('cmList').innerHTML = (c.bookings || []).length === 0
    ? '<div class="py-6 text-center text-slate-400">Khách hàng chưa có đơn đặt phòng nào.</div>'
    : c.bookings.map(bk => `
      <div class="py-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <div class="font-mono font-bold text-[#0E1D33]">${escHtml(bk.booking_code)}</div>
          <div class="text-slate-600">${escHtml(bk.category_name)}</div>
          <div class="text-[11px] text-slate-400 tabular-nums">${escHtml(bk.check_in_date)} &rarr; ${escHtml(bk.check_out_date)}</div>
        </div>
        <div class="text-right">
          <div class="font-bold text-[#0E1D33] tabular-nums">${fmtVND(bk.total_estimated)}</div>
          <span class="text-[11px] font-semibold text-slate-500">${escHtml(bk.status)}</span>
        </div>
      </div>
    `).join('');
  openModal('customerModal');
}

// Run initial filter if landing directly with ?status=...
if (document.getElementById('bookingStatusFilterSelect')) {
  filterBookingsTable();
}
</script>
