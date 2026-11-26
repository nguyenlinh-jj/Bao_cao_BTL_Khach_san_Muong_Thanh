<?php
$tab = (string) ($activeTab ?? 'dashboard');
$basis = (string) ($basis ?? 'stay_night');
$days = (int) ($days ?? 14);
$endDate = (string) ($endDate ?? date('Y-m-d'));
$pendingTxNavCount = (int) ($paymentsOverview['pendingTransferCount'] ?? 0);
$fmt = fn($n) => number_format((float) $n, 0, ',', '.') . ' đ';
$esc = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$pol = $policies ?? [];

$statusLabels = [
  'pending'     => ['Chờ xác nhận', 'bg-amber-50 text-amber-700 border-amber-200'],
  'confirmed'   => ['Đã xác nhận', 'bg-sky-50 text-sky-700 border-sky-200'],
  'checked_in'  => ['Đang ở', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
  'checked_out' => ['Đã trả phòng', 'bg-slate-100 text-slate-700 border-slate-200'],
  'cancelled'   => ['Đã hủy', 'bg-rose-50 text-rose-700 border-rose-200'],
];
$srvCatLabels = [
  'dining_seafood'   => 'Hải sản tươi sống',
  'dining_set'       => 'Set menu & Tiệc',
  'dining_appetizer' => 'Khai vị & Gỏi',
  'dining_viet'      => 'Món Việt & Wagyu',
  'dining_drink'     => 'Đồ uống & Bar',
  'dining_other'     => 'Ăn sáng & Minibar',
  'spa'              => 'Pearl Spa & Trị liệu',
  'addon'            => 'Đưa đón & Tiện ích',
  'experience'       => 'Tour & Trải nghiệm',
];

$curAn = $analytics['current'] ?? ['points' => [], 'summary' => []];
$prevAn = $analytics['previous'] ?? ['points' => [], 'summary' => []];
$anSum = $curAn['summary'] ?? [];
$prevSum = $prevAn['summary'] ?? [];
$pctDelta = function ($cur, $prev) {
  if ((float) $prev <= 0) return $cur > 0 ? '+100%' : '0%';
  $d = round((($cur - $prev) / $prev) * 100, 1);
  return ($d >= 0 ? '+' : '') . $d . '%';
};
$anPoints = $curAn['points'] ?? [];
$maxBarRev = max(1, ...array_map(fn($pt) => (float) ($pt['total_revenue'] ?? 0), $anPoints ?: [['total_revenue' => 1]]));
?>
<div class="min-h-screen flex flex-col bg-[#F7F4EC] text-[#0E1D33]">
  <!-- TOPBAR PMS -->
  <header class="sticky top-0 z-30 h-14 bg-[#0E1D33] text-white border-b border-white/10 px-4 lg:px-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 min-w-0">
      <a href="/" class="flex items-center gap-2 shrink-0">
        <span class="font-serif text-lg tracking-[0.18em] font-semibold text-[#E2C27D]">MƯỜNG THANH</span>
      </a>
      <span class="text-white/25">|</span>
      <span class="text-xs font-bold tracking-[0.18em] uppercase text-slate-200 truncate">QUẢN TRỊ VIÊN · PMS CONSOLE</span>
    </div>
    <div class="flex items-center gap-3 text-xs">
      <a href="/reception" class="hidden sm:inline px-3 py-1.5 rounded-lg border border-white/20 text-slate-200 hover:border-[#C9A45C]">Lễ tân</a>
      <a href="/" class="px-3 py-1.5 rounded-lg border border-white/20 text-slate-200 hover:border-[#C9A45C]">Trang khách</a>
      <span class="hidden md:inline text-slate-300 font-medium"><?= $esc($adminUser['full_name'] ?? 'Admin') ?></span>
      <a href="/logout" class="px-3 py-1.5 rounded-lg bg-[#C9A45C] text-[#0E1D33] font-bold">Đăng xuất</a>
    </div>
  </header>

  <div class="flex-1 flex min-h-0">
    <!-- SIDEBAR TRÁI (#0E1D33) -->
    <aside class="w-64 shrink-0 bg-[#0E1D33] text-slate-200 border-r border-white/10 hidden lg:flex flex-col justify-between">
      <div>
        <div class="px-4 py-3.5 text-[10px] font-bold tracking-[0.2em] uppercase text-[#E2C27D]/90 border-b border-white/10 flex items-center justify-between">
          <span>DANH MỤC QUẢN TRỊ</span>
          <span class="px-1.5 py-0.5 rounded bg-white/10 text-[9px] tracking-wider text-slate-300">5★ PMS</span>
        </div>

        <nav class="p-3 space-y-4 text-xs overflow-y-auto">
          <!-- Nhóm 1: Tổng quan -->
          <div class="space-y-1">
            <div class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Tổng quan</div>
            <a href="/admin?tab=dashboard" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'dashboard' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
              <span>Bảng điều khiển</span>
            </a>
            <a href="/admin?tab=booking_performance" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'booking_performance' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M7 16v-5M12 16V7M17 16v-8"/></svg>
              <span>Hiệu quả đặt phòng</span>
            </a>
          </div>

          <!-- Nhóm 2: Vận hành & Doanh thu -->
          <div class="space-y-1 pt-2 border-t border-white/10">
            <div class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Vận hành &amp; Doanh thu</div>
            <a href="/admin?tab=room_categories" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'room_categories' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l9 4.5-9 4.5-9-4.5L12 3zm-9 9l9 4.5 9-4.5M3 16.5L12 21l9-4.5"/></svg>
              <span>Hạng phòng</span>
            </a>
            <a href="/admin?tab=rooms_list" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'rooms_list' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 18v-6a2 2 0 012-2h14a2 2 0 012 2v6M3 14h18M6 10V7a2 2 0 012-2h8a2 2 0 012 2v3"/></svg>
              <span>Danh sách phòng</span>
            </a>
            <a href="/admin?tab=services" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'services' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3v7a3 3 0 003 3v8M7 3v7M10 3v7a3 3 0 01-3 3M17 3v18m0-12a3 3 0 013 3v9"/></svg>
              <span>Dịch vụ</span>
            </a>
            <a href="/admin?tab=bookings" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'bookings' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path stroke-linecap="round" d="M8 2v4M16 2v4M3 10h18M9 15l2 2 4-4"/></svg>
              <span>Đặt phòng</span>
            </a>
            <a href="/admin?tab=payments" class="px-3 py-2 rounded-xl flex items-center justify-between gap-2 whitespace-nowrap transition-colors <?= $tab === 'payments' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <span class="inline-flex items-center gap-2.5">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20M6 15h4"/></svg>
                <span>Thanh toán</span>
              </span>
              <?php if ($pendingTxNavCount > 0): ?>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold tabular-nums <?= $tab === 'payments' ? 'bg-[#0E1D33] text-[#E2C27D]' : 'bg-rose-500 text-white' ?>">
                  <?= $pendingTxNavCount ?>
                </span>
              <?php endif; ?>
            </a>
          </div>

          <!-- Nhóm 3: Hệ thống -->
          <div class="space-y-1 pt-2 border-t border-white/10">
            <div class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Hệ thống</div>
            <a href="/admin?tab=customers" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'customers' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 00-8 0M12 11a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM20 19a3 3 0 00-3-3M17 4.5a3 3 0 010 5.5M4 19a3 3 0 013-3M7 4.5a3 3 0 000 5.5"/></svg>
              <span>Khách hàng</span>
            </a>
            <a href="/admin?tab=staff" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'staff' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6l7-3zm-2.5 9l1.8 1.8 3.5-3.6"/></svg>
              <span>Nhân sự &amp; quyền hạn</span>
            </a>
            <a href="/admin?tab=policies" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'policies' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16M9 4v4M15 10v4M10 16v4"/></svg>
              <span>Chính sách &amp; nội dung</span>
            </a>
            <a href="/admin?tab=audit" class="px-3 py-2 rounded-xl flex items-center gap-2.5 whitespace-nowrap transition-colors <?= $tab === 'audit' ? 'bg-[#C9A45C] text-[#0E1D33] font-bold shadow-xs' : 'text-slate-200 hover:bg-white/10 hover:text-white' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h4"/></svg>
              <span>Nhật ký hệ thống</span>
            </a>
          </div>
        </nav>
      </div>

      <div class="p-3.5 border-t border-white/10 space-y-2 text-[11px]">
        <div>
          <div class="font-semibold text-[#E2C27D]">Mường Thanh Luxury Phú Quốc</div>
          <div class="text-slate-400 mt-0.5">Hệ thống Quản trị &amp; Điều hành PMS</div>
        </div>
        <a href="/reception" class="inline-flex items-center justify-between w-full px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-slate-200 font-medium transition-colors">
          <span>Mở Bàn làm việc Lễ tân</span>
          <span>→</span>
        </a>
      </div>
    </aside>

    <!-- NỘI DUNG CHÍNH -->
    <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-7 space-y-6">
      <!-- Mobile Nav Pills -->
      <div class="flex lg:hidden flex-wrap gap-1.5 text-xs">
        <?php
        $navItems = [
          'dashboard' => 'Tổng quan', 'room_categories' => 'Hạng phòng', 'rooms_list' => 'DS Phòng',
          'services' => 'Dịch vụ', 'bookings' => 'Đặt phòng', 'booking_performance' => 'Hiệu quả ĐP',
          'payments' => 'Thanh toán', 'customers' => 'Khách hàng', 'staff' => 'Nhân sự',
          'policies' => 'Chính sách', 'audit' => 'Nhật ký',
        ];
        foreach ($navItems as $k => $lbl):
        ?>
          <a href="/admin?tab=<?= $k ?>" class="px-3 py-1.5 rounded-lg border <?= $tab === $k ? 'bg-[#0E1D33] text-white border-[#0E1D33]' : 'bg-white border-[#E6DFD1]' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($flash)): ?>
        <div class="p-3.5 rounded-xl border text-xs <?= $flash['type'] === 'ok' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-700' ?>">
          <?= $esc($flash['msg']) ?>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'dashboard'): ?>
        <!-- HERO + 6 KPI -->
        <div class="bg-[#0E1D33] text-white rounded-2xl p-6 space-y-5 shadow-md">
          <div>
            <h1 class="font-serif text-3xl font-semibold text-white">Xin chào, <?= $esc($adminUser['full_name'] ?? 'Quản trị viên') ?>!</h1>
            <p class="text-xs text-[#E2C27D] mt-1">Tổng quan hoạt động & vận hành thời gian thực · Mường Thanh Luxury Phú Quốc (PHP OOP + MySQL)</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 text-[#0E1D33]">
            <div class="bg-white rounded-xl p-4">
              <div class="text-[11px] font-bold uppercase text-slate-500">Doanh thu hôm nay</div>
              <div class="font-serif text-2xl font-bold mt-1 tabular-nums"><?= $fmt($todayRevenueCollected) ?></div>
              <div class="text-[11px] text-slate-500 mt-0.5">Tổng thực thu tích lũy: <?= $fmt($totalCollected) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4">
              <div class="text-[11px] font-bold uppercase text-slate-500">Công suất phòng</div>
              <div class="font-serif text-2xl font-bold mt-1 tabular-nums text-emerald-700"><?= (int) $occupancyPct ?>%</div>
              <div class="text-[11px] text-slate-500 mt-0.5"><?= (int) $activeStays ?>/<?= (int) $totalRooms ?> phòng đang vận hành</div>
            </div>
            <div class="bg-white rounded-xl p-4">
              <div class="text-[11px] font-bold uppercase text-slate-500">Khách đang lưu trú</div>
              <div class="font-serif text-2xl font-bold mt-1 tabular-nums"><?= (int) $inHouseGuests ?> khách</div>
              <div class="text-[11px] text-slate-500 mt-0.5"><?= (int) $activeStays ?> phòng đang check-in</div>
            </div>
            <div class="bg-white rounded-xl p-4">
              <div class="text-[11px] font-bold uppercase text-slate-500">Giá phòng TB (ADR)</div>
              <div class="font-serif text-2xl font-bold mt-1 tabular-nums text-[#A8843E]"><?= $fmt($adrOverall) ?></div>
              <div class="text-[11px] text-slate-500 mt-0.5">Bình quân mỗi đêm phòng bán được</div>
            </div>
            <div class="bg-white rounded-xl p-4">
              <div class="text-[11px] font-bold uppercase text-slate-500">Tiền cọc đang giữ</div>
              <div class="font-serif text-2xl font-bold mt-1 tabular-nums"><?= $fmt($depositHeld) ?></div>
              <div class="text-[11px] text-slate-500 mt-0.5">Từ các đơn chưa tất toán check-out</div>
            </div>
            <div class="bg-white rounded-xl p-4">
              <div class="text-[11px] font-bold uppercase text-slate-500">Doanh thu dịch vụ</div>
              <div class="font-serif text-2xl font-bold mt-1 tabular-nums text-emerald-700"><?= $fmt($totalServiceRevenueAll) ?></div>
              <div class="text-[11px] text-slate-500 mt-0.5">Ẩm thực, Pearl Spa & Đặt kèm phòng</div>
            </div>
          </div>
        </div>

        <!-- ĐẶT PHÒNG HÔM NAY -->
        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-bold tracking-[0.15em] uppercase text-slate-600">ĐẶT PHÒNG HÔM NAY</h2>
            <a href="/admin?tab=booking_performance" class="text-xs font-bold text-[#A8843E] hover:underline">Báo cáo đặt phòng →</a>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 text-center">
            <div class="p-3 rounded-xl bg-[#FAF7F0]"><div class="text-2xl font-bold tabular-nums"><?= (int) ($todaySummary['arrivals'] ?? 0) ?></div><div class="text-xs text-slate-500 mt-0.5">lượt đến</div></div>
            <div class="p-3 rounded-xl bg-[#FAF7F0]"><div class="text-2xl font-bold tabular-nums"><?= (int) ($todaySummary['departures'] ?? 0) ?></div><div class="text-xs text-slate-500 mt-0.5">lượt đi</div></div>
            <div class="p-3 rounded-xl bg-[#FAF7F0]"><div class="text-2xl font-bold tabular-nums text-emerald-700"><?= (int) ($todaySummary['available_rooms_today'] ?? 0) ?></div><div class="text-xs text-slate-500 mt-0.5">phòng trống</div></div>
            <div class="p-3 rounded-xl bg-[#FAF7F0]"><div class="text-2xl font-bold tabular-nums"><?= (int) ($todaySummary['stayovers'] ?? 0) ?></div><div class="text-xs text-slate-500 mt-0.5">khách ở qua đêm</div></div>
            <div class="p-3 rounded-xl bg-[#FAF7F0]"><div class="text-2xl font-bold tabular-nums text-rose-700"><?= (int) ($todaySummary['cancellations'] ?? 0) ?></div><div class="text-xs text-slate-500 mt-0.5">hủy phòng</div></div>
          </div>
        </div>

        <!-- HẠNG PHÒNG CÒN TRỐNG (10 NGÀY TỚI) -->
        <?php
        $availDates = $availability10Days['dates'] ?? [];
        $availRows = $availability10Days['rows'] ?? [];
        $dayVn = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        ?>
        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="text-xs font-bold tracking-[0.15em] uppercase text-slate-600">HẠNG PHÒNG CÒN TRỐNG (10 NGÀY TỚI)</h2>
              <p class="text-[11px] text-slate-500 mt-0.5">Tính theo số phòng vật lý đang vận hành trừ phòng bảo trì, đơn giữ chỗ/lưu trú và lịch Stop-sell thực tế</p>
            </div>
            <a href="/admin?tab=rooms_list" class="text-xs font-bold text-[#A8843E] hover:underline">Lịch giá &amp; phòng trống →</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="text-[10px] uppercase text-slate-500 border-b border-[#EFE9DC]">
                <tr>
                  <th class="py-2.5 pr-4 min-w-[200px]">Hạng phòng</th>
                  <?php foreach ($availDates as $di => $dt):
                    $ts = strtotime($dt);
                    $w = (int) date('w', $ts);
                    $isWknd = ($w === 0 || $w === 6);
                  ?>
                    <th class="py-2 px-2 text-center min-w-[58px] <?= $di === 0 ? 'bg-[#FAF3E3] text-[#0E1D33] rounded-t-lg' : ($isWknd ? 'bg-[#FCF8EE] text-[#A8843E]' : '') ?>">
                      <div><?= $di === 0 ? 'HÔM NAY' : $dayVn[$w] ?></div>
                      <div class="font-mono font-bold text-[11px]"><?= date('d/m', $ts) ?></div>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($availRows)): ?>
                  <tr><td colspan="<?= count($availDates) + 1 ?>" class="py-6 text-center text-slate-500">Chưa có hạng phòng đang mở bán.</td></tr>
                <?php else: foreach ($availRows as $ar): ?>
                  <tr>
                    <td class="py-2.5 pr-4 font-semibold text-[#0E1D33]">
                      <?= $esc($ar['name']) ?>
                      <span class="ml-1 text-[10px] font-mono text-slate-400">(<?= (int) $ar['total_operational'] ?>P)</span>
                    </td>
                    <?php foreach ($ar['cells'] as $di => $cell):
                      $av = (int) ($cell['available'] ?? 0);
                      $ss = !empty($cell['is_stop_sell']);
                    ?>
                      <td class="py-2.5 px-2 text-center tabular-nums font-bold <?= $di === 0 ? 'bg-[#FAF3E3]/60' : '' ?> <?= $ss ? 'text-rose-700 bg-rose-50/70' : ($av === 0 ? 'text-rose-600' : ($av <= 1 ? 'text-amber-700' : 'text-[#0E1D33]')) ?>">
                        <?= $ss ? 'Stop' : $av ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- HẠNG PHÒNG / KÊNH ĐẶT / GÓI GIÁ -->
        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="inline-flex rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] p-1 text-xs">
              <button type="button" onclick="switchBreakdown('cat')" id="bdBtnCat" class="px-3.5 py-1.5 rounded-lg bg-[#0E1D33] text-white font-semibold">Hạng phòng</button>
              <button type="button" onclick="switchBreakdown('src')" id="bdBtnSrc" class="px-3.5 py-1.5 rounded-lg text-slate-600 font-semibold">Kênh đặt</button>
              <button type="button" onclick="switchBreakdown('pkg')" id="bdBtnPkg" class="px-3.5 py-1.5 rounded-lg text-slate-600 font-semibold">Gói giá</button>
            </div>
            <span class="text-xs text-slate-500">Cơ sở tổng hợp: <strong><?= (int) $sourceCount ?></strong> chứng từ đặt phòng</span>
          </div>

          <div id="bdPanelCat" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="text-[10px] uppercase text-slate-500 border-b border-[#EFE9DC]">
                <tr><th class="py-2">Hạng phòng</th><th class="py-2 text-right">Đêm phòng</th><th class="py-2">Tỷ trọng doanh thu</th><th class="py-2 text-right">Doanh thu phòng</th><th class="py-2 text-right">ADR</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($byCategory)): ?>
                  <tr><td colspan="5" class="py-5 text-center text-slate-400">Chưa có dữ liệu doanh thu theo hạng phòng.</td></tr>
                <?php else: foreach ($byCategory as $row): ?>
                  <tr>
                    <td class="py-2.5 font-semibold"><?= $esc($row['name']) ?></td>
                    <td class="py-2.5 text-right tabular-nums"><?= (int) $row['room_nights'] ?></td>
                    <td class="py-2.5"><div class="flex items-center gap-2"><div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-[#0E1D33] rounded-full" style="width: <?= min(100, (float) $row['share_pct']) ?>%"></div></div><span class="tabular-nums w-11 text-right"><?= $row['share_pct'] ?>%</span></div></td>
                    <td class="py-2.5 text-right font-bold tabular-nums"><?= $fmt($row['room_revenue']) ?></td>
                    <td class="py-2.5 text-right text-[#A8843E] font-semibold tabular-nums"><?= $fmt($row['adr']) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <div id="bdPanelSrc" class="hidden overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="text-[10px] uppercase text-slate-500 border-b border-[#EFE9DC]">
                <tr><th class="py-2">Kênh đặt phòng</th><th class="py-2 text-right">Số đơn</th><th class="py-2 text-right">Đêm phòng</th><th class="py-2">Tỷ trọng doanh thu</th><th class="py-2 text-right">Doanh thu phòng</th><th class="py-2 text-right">ADR</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($bySource)): ?>
                  <tr><td colspan="6" class="py-5 text-center text-slate-400">Chưa có dữ liệu doanh thu theo kênh đặt phòng.</td></tr>
                <?php else: foreach ($bySource as $row): ?>
                  <tr>
                    <td class="py-2.5 font-semibold"><?= $esc($row['label']) ?></td>
                    <td class="py-2.5 text-right tabular-nums"><?= (int) $row['booking_count'] ?></td>
                    <td class="py-2.5 text-right tabular-nums"><?= (int) $row['room_nights'] ?></td>
                    <td class="py-2.5"><div class="flex items-center gap-2"><div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-[#C9A45C] rounded-full" style="width: <?= min(100, (float) $row['share_pct']) ?>%"></div></div><span class="tabular-nums w-11 text-right"><?= $row['share_pct'] ?>%</span></div></td>
                    <td class="py-2.5 text-right font-bold tabular-nums"><?= $fmt($row['room_revenue']) ?></td>
                    <td class="py-2.5 text-right text-[#A8843E] font-semibold tabular-nums"><?= $fmt($row['adr']) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <div id="bdPanelPkg" class="hidden overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="text-[10px] uppercase text-slate-500 border-b border-[#EFE9DC]">
                <tr><th class="py-2">Gói giá</th><th class="py-2 text-right">Số đơn</th><th class="py-2 text-right">Đêm phòng</th><th class="py-2">Tỷ trọng doanh thu</th><th class="py-2 text-right">Doanh thu phòng</th><th class="py-2 text-right">ADR</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($byPackage)): ?>
                  <tr><td colspan="6" class="py-5 text-center text-slate-400">Chưa có dữ liệu doanh thu theo gói giá.</td></tr>
                <?php else: foreach ($byPackage as $row): ?>
                  <tr>
                    <td class="py-2.5 font-semibold"><?= $esc($row['label']) ?></td>
                    <td class="py-2.5 text-right tabular-nums"><?= (int) $row['booking_count'] ?></td>
                    <td class="py-2.5 text-right tabular-nums"><?= (int) $row['room_nights'] ?></td>
                    <td class="py-2.5"><div class="flex items-center gap-2"><div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-600 rounded-full" style="width: <?= min(100, (float) $row['share_pct']) ?>%"></div></div><span class="tabular-nums w-11 text-right"><?= $row['share_pct'] ?>%</span></div></td>
                    <td class="py-2.5 text-right font-bold tabular-nums"><?= $fmt($row['room_revenue']) ?></td>
                    <td class="py-2.5 text-right text-[#A8843E] font-semibold tabular-nums"><?= $fmt($row['adr']) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- CƠ SỞ DỮ LIỆU & BẢNG KÊ CHỨNG TỪ DOANH THU THỰC TẾ -->
        <div class="bg-[#FAF7F0] border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800">CƠ SỞ DỮ LIỆU &amp; BẢNG KÊ CHỨNG TỪ DOANH THU THỰC TẾ</h3>
              <p class="text-[11px] text-slate-600 mt-0.5">Toàn bộ số liệu tổng hợp trực tiếp từ đơn đặt phòng, dịch vụ và hóa đơn MySQL, khớp giữa Khách hàng, Lễ tân và Quản trị viên.</p>
            </div>
            <button type="button" onclick="document.getElementById('auditTrailBox').classList.toggle('hidden')" class="px-3 py-1.5 bg-white border border-[#E6DFD1] rounded-lg text-xs font-semibold">Thu gọn / Mở rộng</button>
          </div>
          <div id="auditTrailBox" class="overflow-x-auto bg-white rounded-xl border border-[#EFE9DC]">
            <table class="w-full text-left text-xs">
              <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#EFE9DC]">
                <tr><th class="py-2.5 px-3">Mã đơn</th><th class="py-2.5 px-3">Ngày ở</th><th class="py-2.5 px-3">Khách hàng</th><th class="py-2.5 px-3">Phòng</th><th class="py-2.5 px-3 text-right">Tiền phòng</th><th class="py-2.5 px-3 text-right">Dịch vụ</th><th class="py-2.5 px-3 text-right">Thuế phí</th><th class="py-2.5 px-3 text-right">Tổng ghi nhận</th><th class="py-2.5 px-3">Trạng thái</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach (array_slice($revenueAuditRows, 0, 20) as $r):
                  $svcTot = (float) ($r['completed_services_total'] ?? 0) + (float) ($r['addon_subtotal'] ?? 0);
                  $grand = $r['invoice'] ? (float) $r['invoice']['total_amount'] : ((float) $r['total_estimated'] + (float) ($r['completed_services_total'] ?? 0));
                  [$stLbl, $stCls] = $statusLabels[$r['status']] ?? [$r['status'], 'bg-slate-100 text-slate-700 border-slate-200'];
                ?>
                  <tr>
                    <td class="py-2.5 px-3 font-mono font-bold"><?= $esc($r['booking_code']) ?></td>
                    <td class="py-2.5 px-3 tabular-nums"><?= $esc($r['check_in_date']) ?> → <?= $esc($r['check_out_date']) ?></td>
                    <td class="py-2.5 px-3"><?= $esc($r['guest_name']) ?></td>
                    <td class="py-2.5 px-3"><?= $r['assigned_room_number'] ? 'P.' . $esc($r['assigned_room_number']) : 'Chưa gán' ?></td>
                    <td class="py-2.5 px-3 text-right tabular-nums"><?= $fmt($r['room_subtotal']) ?></td>
                    <td class="py-2.5 px-3 text-right tabular-nums"><?= $fmt($svcTot) ?></td>
                    <td class="py-2.5 px-3 text-right tabular-nums"><?= $fmt($r['tax_amount']) ?></td>
                    <td class="py-2.5 px-3 text-right font-bold tabular-nums"><?= $fmt($grand) ?></td>
                    <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold <?= $stCls ?>"><?= $esc($stLbl) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'room_categories'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Hạng phòng & Bảng giá</h1>
            <p class="text-xs text-slate-500"><?= count($categories) ?> hạng phòng · <?= count($rooms) ?> phòng vật lý</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <input type="text" id="catSearchInp" oninput="filterCategories(this.value)" placeholder="Tìm tên hoặc mã hạng phòng..." class="px-3 py-2 text-xs border border-[#E6DFD1] rounded-xl bg-white" />
            <button type="button" onclick="document.getElementById('newCatModal').classList.remove('hidden')" class="px-4 py-2 bg-[#0E1D33] text-white text-xs font-bold rounded-xl">+ Thêm hạng phòng</button>
          </div>
        </div>

        <div class="space-y-4">
          <?php foreach ($categories as $cat):
            $effPrice = (float) ($cat['effective_base_price'] ?? $cat['base_price']);
            $hasPromo = !empty($cat['has_active_promo']);
            $isStopSell = !empty($cat['is_stop_sell']);
            $catRooms = $cat['physical_rooms'] ?? [];
            $resolvedPkgs = $cat['resolved_packages'] ?? $basePackages;
          ?>
            <div class="cat-card bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-sm" data-search="<?= $esc(strtolower($cat['name'] . ' ' . $cat['code'])) ?>">
              <div class="p-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-start gap-3 flex-1 min-w-[260px]">
                  <button type="button" onclick="document.getElementById('catBody<?= (int) $cat['id'] ?>').classList.toggle('hidden')" class="px-2.5 py-1 rounded-lg border border-[#E6DFD1] text-xs font-bold">⌄</button>
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="font-serif text-xl font-semibold"><?= $esc($cat['name']) ?></h3>
                      <span class="px-2 py-0.5 rounded bg-[#FAF7F0] border border-[#E6DFD1] text-[10px] font-mono font-bold text-[#A8843E]"><?= $esc($cat['code']) ?></span>
                      <?php if ((int) $cat['is_featured'] === 1): ?>
                        <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-[10px] font-bold text-amber-800">★ Slider Trang chủ #<?= (int) $cat['featured_order'] ?></span>
                      <?php endif; ?>
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= (int) $cat['is_active'] === 1 ? ($isStopSell ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700') : 'bg-slate-100 text-slate-600' ?>">
                        <?= (int) $cat['is_active'] !== 1 ? 'Đang ẩn' : ($isStopSell ? 'Đóng bán hôm nay' : 'Đang mở bán') ?>
                      </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1"><?= $esc($cat['description']) ?></p>
                    <p class="text-[11px] text-slate-600 mt-1"><?= $esc($cat['bed_label']) ?> · <?= (int) $cat['area_sqm'] ?>m² · <?= (int) $cat['max_adults'] ?> khách · <?= count($catRooms) ?> phòng</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <?php if ($hasPromo): ?><div class="text-[11px] text-slate-400 line-through tabular-nums"><?= $fmt($cat['base_price']) ?></div><?php endif; ?>
                    <div class="font-serif text-xl font-bold text-[#A8843E] tabular-nums"><?= $fmt($effPrice) ?></div>
                  </div>
                  <img src="<?= $esc($cat['image_url']) ?>" alt="" class="w-24 h-14 object-cover rounded-lg border border-[#E6DFD1]" />
                  <div class="relative">
                    <button type="button" onclick="toggleActionMenu(<?= (int) $cat['id'] ?>)" class="px-3 py-1.5 rounded-lg border border-[#E6DFD1] bg-[#FAF7F0] text-xs font-bold">•••</button>
                    <div id="catMenu<?= (int) $cat['id'] ?>" class="hidden absolute right-0 mt-1 w-64 bg-white border border-[#E6DFD1] rounded-xl shadow-xl z-20 p-2 space-y-1 text-xs">
                      <button type="button" onclick="openQuickRateModal(<?= (int) $cat['id'] ?>, '<?= $esc($cat['name']) ?>', <?= (int) $cat['base_price'] ?>)" class="w-full text-left px-3 py-2 rounded-lg hover:bg-[#FAF7F0] font-semibold">Cập nhật giá / Đóng bán nhanh</button>
                      <button type="button" onclick="openAddRoomForCat(<?= (int) $cat['id'] ?>)" class="w-full text-left px-3 py-2 rounded-lg hover:bg-[#FAF7F0]">+ Thêm phòng cho hạng này</button>
                      <form method="POST" action="/admin/categories/<?= (int) $cat['id'] ?>/toggle">
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-lg hover:bg-amber-50 text-amber-800 font-semibold"><?= (int) $cat['is_active'] === 1 ? 'Ngừng bán (Ẩn khỏi Trang chủ)' : 'Mở bán lại trên Trang chủ' ?></button>
                      </form>
                      <form method="POST" action="/admin/categories/<?= (int) $cat['id'] ?>/delete" onsubmit="return confirm('Xóa vĩnh viễn hạng phòng <?= $esc($cat['name']) ?>? Chỉ thực hiện được khi chưa có phòng vật lý hoặc đơn đặt phòng liên kết.');">
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-lg hover:bg-rose-50 text-rose-700 font-semibold border-t border-slate-100 mt-1 pt-2">Xóa hạng phòng vĩnh viễn</button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Accordion Cấu hình giá, Khuyến mãi có thời hạn & Hiển thị trang chủ -->
              <form id="catBody<?= (int) $cat['id'] ?>" method="POST" action="/admin/categories/<?= (int) $cat['id'] ?>/update" novalidate onsubmit="return validateCatPromoForm(this)" class="border-t border-[#EFE9DC] bg-[#FCFBF8] p-4 space-y-4 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div class="bg-white border border-[#E6DFD1] rounded-xl p-3 space-y-2">
                    <div class="text-[10px] font-bold uppercase text-slate-500">1. Thông tin & Giá gốc niêm yết</div>
                    <input type="text" name="name" value="<?= $esc($cat['name']) ?>" class="w-full px-2.5 py-1.5 border rounded-lg font-semibold" placeholder="Tên hạng phòng" />
                    <div class="grid grid-cols-2 gap-2">
                      <input type="number" name="base_price" step="50000" value="<?= (int) $cat['base_price'] ?>" class="w-full px-2.5 py-1.5 border rounded-lg tabular-nums font-bold" />
                      <input type="text" name="bed_label" value="<?= $esc($cat['bed_label']) ?>" class="w-full px-2.5 py-1.5 border rounded-lg" />
                    </div>
                    <div class="grid grid-cols-3 gap-1.5">
                      <input type="number" name="area_sqm" min="15" value="<?= (int) $cat['area_sqm'] ?>" class="px-2 py-1 border rounded-lg tabular-nums" title="Diện tích m²" />
                      <input type="number" name="max_adults" min="1" value="<?= (int) $cat['max_adults'] ?>" class="px-2 py-1 border rounded-lg tabular-nums" title="Số khách tối đa" />
                      <input type="text" name="view_label" value="<?= $esc($cat['view_label']) ?>" class="px-2 py-1 border rounded-lg" title="Hướng nhìn" />
                    </div>
                    <input type="text" name="image_url" value="<?= $esc($cat['image_url']) ?>" class="w-full px-2.5 py-1.5 border rounded-lg text-[11px]" placeholder="URL ảnh đại diện" />
                    <textarea name="description" rows="2" class="w-full px-2.5 py-1.5 border rounded-lg text-[11px]" placeholder="Mô tả hạng phòng"><?= $esc($cat['description']) ?></textarea>
                    <input type="text" name="amenities" value="<?= $esc(implode(', ', $cat['amenities'] ?? [])) ?>" class="w-full px-2.5 py-1.5 border rounded-lg text-[11px]" placeholder="Tiện nghi (cách nhau dấu phẩy)" />
                  </div>

                  <div class="bg-white border border-[#E6DFD1] rounded-xl p-3 space-y-2">
                    <div class="text-[10px] font-bold uppercase text-slate-500">2. Khuyến mãi có thời hạn (Tự hoàn giá khi hết)</div>
                    <div class="grid grid-cols-2 gap-2">
                      <select name="promo_discount_type" class="px-2 py-1.5 border rounded-lg bg-white">
                        <option value="none" <?= ($cat['promo_discount_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>Không giảm</option>
                        <option value="percent" <?= ($cat['promo_discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Giảm %</option>
                        <option value="fixed" <?= ($cat['promo_discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Giảm VNĐ</option>
                      </select>
                      <input type="number" name="promo_discount_value" value="<?= (int) ($cat['promo_discount_value'] ?? 0) ?>" class="px-2.5 py-1.5 border rounded-lg tabular-nums" placeholder="Giá trị" />
                    </div>
                    <input type="text" name="promo_label" value="<?= $esc($cat['promo_label'] ?? '') ?>" class="w-full px-2.5 py-1.5 border rounded-lg" placeholder="Nhãn VD: Ưu đãi Thu -15%" />
                    <?php
                      $todayAdm = date('Y-m-d');
                      $catPStart = (string) ($cat['promo_start_date'] ?? '');
                      $catPEndMin = ($catPStart !== '' && $catPStart >= $todayAdm) ? $catPStart : $todayAdm;
                    ?>
                    <div class="grid grid-cols-2 gap-2">
                      <input type="date" name="promo_start_date" min="<?= $todayAdm ?>" data-initial="<?= $esc($catPStart) ?>" value="<?= $esc($catPStart) ?>" onchange="syncCatPromoDates(this)" class="px-2 py-1 border rounded-lg text-[11px]" />
                      <input type="date" name="promo_end_date" min="<?= $esc($catPEndMin) ?>" data-initial="<?= $esc($cat['promo_end_date'] ?? '') ?>" value="<?= $esc($cat['promo_end_date'] ?? '') ?>" onchange="syncCatPromoDates(this)" class="px-2 py-1 border rounded-lg text-[11px]" />
                    </div>
                  </div>

                  <div class="bg-white border border-[#E6DFD1] rounded-xl p-3 space-y-2 flex flex-col justify-between">
                    <div class="space-y-2">
                      <div class="text-[10px] font-bold uppercase text-slate-500">3. Hiển thị Trang chủ & Gói giá</div>
                      <label class="flex items-center gap-2 font-semibold">
                        <input type="checkbox" name="is_featured" value="1" <?= (int) $cat['is_featured'] === 1 ? 'checked' : '' ?> class="accent-[#0E1D33]" />
                        <span>Ưu tiên hiển thị trên Trang chủ</span>
                      </label>
                      <div class="flex items-center gap-2">
                        <span>Thứ tự:</span>
                        <input type="number" name="featured_order" min="0" value="<?= (int) ($cat['featured_order'] ?? 0) ?>" class="w-20 px-2 py-1 border rounded-lg tabular-nums" />
                      </div>
                    </div>
                    <button type="submit" class="w-full py-2 bg-[#0E1D33] hover:bg-[#162C4D] text-white font-bold rounded-lg">Lưu & Đồng bộ Trang chủ</button>
                  </div>
                </div>

                <!-- Gói giá -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <?php
                  $pkgCfg = json_decode((string) ($cat['package_config_json'] ?? '{}'), true) ?: [];
                  foreach ($basePackages as $bp):
                    $pCode = $bp['code'];
                    $pEnabled = isset($pkgCfg[$pCode]['enabled']) ? (bool) $pkgCfg[$pCode]['enabled'] : true;
                    $pPrice = isset($pkgCfg[$pCode]['price']) && (float) $pkgCfg[$pCode]['price'] > 0
                      ? (float) $pkgCfg[$pCode]['price']
                      : ($effPrice + (float) $bp['extra_per_night']);
                  ?>
                    <div class="bg-[#FFFBEB] border border-[#FDE68A] rounded-xl p-3 flex items-center justify-between gap-2">
                      <div>
                        <label class="flex items-center gap-1.5 font-bold text-[#0E1D33]">
                          <input type="checkbox" name="package_config[<?= $esc($pCode) ?>][enabled]" value="1" <?= $pEnabled ? 'checked' : '' ?> class="accent-[#0E1D33]" />
                          <span><?= $esc($bp['name']) ?></span>
                        </label>
                      </div>
                      <input type="number" step="50000" name="package_config[<?= $esc($pCode) ?>][price]" value="<?= (int) $pPrice ?>" class="w-28 px-2 py-1 border rounded bg-white text-right font-bold tabular-nums" />
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                  <span class="text-slate-500">Phòng thuộc hạng này (<?= count($catRooms) ?>):</span>
                  <?php foreach ($catRooms as $cr): ?>
                    <a href="/admin?tab=rooms_list" class="px-2 py-0.5 rounded border text-[11px] font-bold <?= $cr['status'] === 'maintenance' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?>"><?= $esc($cr['room_number']) ?></a>
                  <?php endforeach; ?>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($tab === 'rooms_list'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Danh sách phòng vật lý & Khóa / Bảo trì</h1>
            <p class="text-xs text-slate-500">Quản lý dọn phòng, khóa bảo trì, đóng bán (Stop-sell) theo ngày trong cùng giao diện</p>
          </div>
          <button type="button" onclick="document.getElementById('addRoomModal').classList.remove('hidden')" class="px-4 py-2 bg-[#0E1D33] text-white text-xs font-bold rounded-xl">+ Thêm phòng mới</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <?php foreach ($rooms as $rm):
            $isMaint = $rm['status'] === 'maintenance';
            $isOcc = $rm['status'] === 'occupied';
            $isDirty = $rm['clean_status'] === 'dirty';
          ?>
            <div class="bg-white border <?= $isMaint ? 'border-rose-300 bg-rose-50/40' : ($isOcc ? 'border-sky-300' : 'border-[#E6DFD1]') ?> rounded-xl p-4 flex flex-col justify-between gap-3 text-xs">
              <div>
                <div class="flex items-center justify-between">
                  <strong class="font-serif text-xl">P.<?= $esc($rm['room_number']) ?></strong>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isMaint ? 'bg-rose-100 text-rose-700' : ($isOcc ? 'bg-sky-100 text-sky-700' : ($isDirty ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700')) ?>">
                    <?= $isMaint ? 'Bảo trì / Khóa' : ($isOcc ? 'Đang có khách' : ($isDirty ? 'Chờ dọn' : 'Sẵn sàng')) ?>
                  </span>
                </div>
                <div class="text-slate-500 mt-0.5">Tầng <?= (int) $rm['floor'] ?> · <?= $esc($rm['category_name']) ?></div>
                <?php if (!empty($rm['active_booking_code'])): ?>
                  <div class="mt-2 p-2 rounded bg-sky-50 text-sky-900 font-medium"><?= $esc($rm['active_booking_code']) ?> · <?= $esc($rm['active_guest_name']) ?></div>
                <?php elseif (!empty($rm['notes'])): ?>
                  <div class="mt-2 text-[11px] text-slate-600"><?= $esc($rm['notes']) ?></div>
                <?php endif; ?>
              </div>

              <div class="flex flex-wrap gap-1.5 pt-2 border-t border-[#EFE9DC]">
                <?php if ($isMaint): ?>
                  <form method="POST" action="/admin/rooms/<?= (int) $rm['id'] ?>/unlock">
                    <button type="submit" title="Mở khóa phòng và chuyển sang Chờ dọn để Buồng phòng vệ sinh trước khi bán" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-semibold">Mở khóa &amp; Chuyển dọn</button>
                  </form>
                <?php elseif (!$isOcc): ?>
                  <button type="button" onclick="openLockRoomModal(<?= (int) $rm['id'] ?>, '<?= $esc($rm['room_number']) ?>')" class="px-2.5 py-1 border border-rose-300 text-rose-700 rounded-lg font-semibold">Khóa / Bảo trì</button>
                  <form method="POST" action="/admin/rooms/<?= (int) $rm['id'] ?>/status">
                    <input type="hidden" name="clean_status" value="<?= $isDirty ? 'clean' : 'dirty' ?>" />
                    <button type="submit" class="px-2.5 py-1 border border-[#E6DFD1] rounded-lg font-semibold"><?= $isDirty ? 'Đánh dấu Sạch' : 'Đánh dấu Chờ dọn' ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Stop-sell + Lịch sử bảo trì phòng (tích hợp bên dưới danh sách phòng) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
            <h3 class="font-serif text-xl font-semibold">Hạng phòng đang đóng bán (Stop-sell) & Điều chỉnh giá theo ngày</h3>
            <div class="space-y-2 text-xs">
              <?php if (empty($rateOverrides)): ?>
                <p class="text-slate-500">Chưa có cấu hình đóng bán hoặc giá đặc biệt theo ngày nào.</p>
              <?php else: foreach ($rateOverrides as $ro): ?>
                <div class="p-3 rounded-xl border border-[#EFE9DC] bg-[#FAF7F0] flex items-center justify-between gap-2">
                  <div>
                    <div class="font-bold"><?= $esc($ro['category_name']) ?> (<?= $esc($ro['category_code']) ?>)</div>
                    <div class="text-slate-600"><?= $esc($ro['start_date']) ?> → <?= $esc($ro['end_date']) ?> · <?= !empty($ro['is_stop_sell']) ? '<span class="text-rose-700 font-bold">Stop-sell</span>' : 'Giá: ' . $fmt($ro['override_price']) ?></div>
                    <div class="text-[11px] text-slate-500"><?= $esc($ro['note']) ?></div>
                  </div>
                  <form method="POST" action="/admin/rate-overrides/<?= (int) $ro['id'] ?>/delete">
                    <button type="submit" class="px-2.5 py-1 border border-rose-200 text-rose-700 rounded-lg font-semibold bg-white">Gỡ bỏ</button>
                  </form>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
            <h3 class="font-serif text-xl font-semibold">Lịch sử khóa phòng & Bảo trì chi tiết</h3>
            <div class="space-y-2 max-h-[340px] overflow-y-auto text-xs">
              <?php foreach ($maintenanceLogs as $ml): ?>
                <div class="p-3 rounded-xl border border-[#EFE9DC] flex items-start justify-between gap-2">
                  <div>
                    <div class="font-bold">Phòng <?= $esc($ml['room_number']) ?> · <?= $esc($ml['category_name']) ?></div>
                    <div class="text-slate-600">Nguyên nhân: <?= $esc($ml['reason']) ?></div>
                    <div class="text-[11px] text-slate-500"><?= $esc($ml['start_date'] ?: '—') ?> → <?= $esc($ml['end_date'] ?: '—') ?> · Bởi <?= $esc($ml['created_by_name'] ?? 'Admin') ?></div>
                  </div>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $ml['status'] === 'active' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' ?>"><?= $ml['status'] === 'active' ? 'Đang khóa' : 'Đã mở lại' ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      <?php elseif ($tab === 'services'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Quản lý Dịch vụ, Ẩm thực & Spa</h1>
            <p class="text-xs text-slate-500">Đồng bộ trực tiếp ra trang Ẩm thực, Pearl Spa và QR Dịch vụ tại phòng · <?= count($services) ?> dịch vụ</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <input type="text" id="srvSearchInp" oninput="filterServicesTable()" placeholder="Tìm mã hoặc tên dịch vụ..." class="px-3 py-2 text-xs border border-[#E6DFD1] rounded-xl bg-white" />
            <select id="srvCatFilter" onchange="filterServicesTable()" class="px-3 py-2 text-xs border border-[#E6DFD1] rounded-xl bg-white">
              <option value="">Tất cả nhóm</option>
              <?php foreach ($srvCatLabels as $ck => $cl): ?>
                <option value="<?= $esc($ck) ?>"><?= $esc($cl) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" onclick="openServiceModal(null)" class="px-4 py-2 bg-[#0E1D33] text-white text-xs font-bold rounded-xl">+ Thêm dịch vụ</button>
          </div>
        </div>

        <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr><th class="py-3 px-4">Mã / Tên dịch vụ</th><th class="py-3 px-4">Nhóm</th><th class="py-3 px-4 text-right">Giá niêm yết</th><th class="py-3 px-4 text-right">Giá ưu đãi</th><th class="py-3 px-4">Thời hạn KM</th><th class="py-3 px-4">Trạng thái</th><th class="py-3 px-4 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($services as $sv):
                $svJson = $esc(json_encode($sv, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE));
              ?>
                <tr class="srv-row" data-cat="<?= $esc($sv['category']) ?>" data-search="<?= $esc(strtolower($sv['code'] . ' ' . $sv['name'])) ?>">
                  <td class="py-3 px-4"><strong><?= $esc($sv['name']) ?></strong><div class="text-[11px] font-mono text-slate-500"><?= $esc($sv['code']) ?> · <?= $esc($sv['unit_label']) ?></div></td>
                  <td class="py-3 px-4"><?= $esc($srvCatLabels[$sv['category']] ?? $sv['category']) ?></td>
                  <td class="py-3 px-4 text-right tabular-nums"><?= $fmt($sv['price']) ?></td>
                  <td class="py-3 px-4 text-right tabular-nums font-bold text-[#A8843E]">
                    <?= !empty($sv['promo_price']) ? $fmt($sv['promo_price']) : '—' ?>
                    <?php if (!empty($sv['active_promo_tag'])): ?>
                      <div class="text-[10px] font-normal text-rose-600"><?= $esc($sv['active_promo_tag']) ?></div>
                    <?php elseif (!empty($sv['promo_expired'])): ?>
                      <div class="text-[10px] font-normal text-slate-400">Hết hạn KM</div>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-4 text-[11px] text-slate-600"><?= !empty($sv['promo_start_date']) || !empty($sv['promo_end_date']) ? $esc(($sv['promo_start_date'] ?: '...') . ' → ' . ($sv['promo_end_date'] ?: '...')) : 'Không giới hạn' ?></td>
                  <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= (int) $sv['is_active'] === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= (int) $sv['is_active'] === 1 ? 'Đang mở bán' : 'Đang ẩn' ?></span></td>
                  <td class="py-3 px-4 text-right">
                    <div class="inline-flex justify-end gap-1.5 flex-wrap">
                      <button type="button" onclick='openQuickSrvModal(<?= $svJson ?>)' class="px-2.5 py-1 border border-[#E2C27D] bg-[#FAF6EC] text-[#0E1D33] rounded-lg font-semibold">Giá nhanh</button>
                      <button type="button" onclick='openServiceModal(<?= $svJson ?>)' class="px-2.5 py-1 border rounded-lg font-semibold">Sửa</button>
                      <form method="POST" action="/admin/services/<?= (int) $sv['id'] ?>/toggle" class="inline">
                        <button type="submit" class="px-2.5 py-1 border rounded-lg font-semibold"><?= (int) $sv['is_active'] === 1 ? 'Ẩn' : 'Mở' ?></button>
                      </form>
                      <form method="POST" action="/admin/services/<?= (int) $sv['id'] ?>/delete" class="inline" onsubmit="return confirm('Xóa vĩnh viễn dịch vụ <?= $esc($sv['name']) ?>? Chỉ xóa được khi chưa phát sinh trong hóa đơn.');">
                        <button type="submit" class="px-2.5 py-1 border border-rose-200 text-rose-700 rounded-lg font-semibold hover:bg-rose-50">Xóa</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'bookings'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Quản lý Đặt phòng (Chế độ Giám sát)</h1>
            <p class="text-xs text-slate-500">Admin xem danh sách và chi tiết hóa đơn; thông tin CCCD được che ẩn để bảo mật dữ liệu khách hàng</p>
          </div>
          <input type="text" oninput="filterBookingsTable(this.value)" placeholder="Tìm mã đơn, tên khách, SĐT..." class="px-3 py-2 text-xs border border-[#E6DFD1] rounded-xl bg-white" />
        </div>

        <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr><th class="py-3 px-4">Mã đơn</th><th class="py-3 px-4">Khách hàng & CCCD</th><th class="py-3 px-4">Hạng phòng</th><th class="py-3 px-4">Ngày ở</th><th class="py-3 px-4 text-right">Tổng / Đã thu</th><th class="py-3 px-4">Trạng thái</th><th class="py-3 px-4 text-right">Xem</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($bookings as $bk):
                [$stLbl, $stCls] = $statusLabels[$bk['status']] ?? [$bk['status'], 'bg-slate-100 text-slate-700 border-slate-200'];
                $bkJson = $esc(json_encode($bk, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE));
              ?>
                <tr class="bk-row" data-search="<?= $esc(strtolower($bk['booking_code'] . ' ' . $bk['guest_name'] . ' ' . $bk['guest_phone'])) ?>">
                  <td class="py-3 px-4 font-mono font-bold"><?= $esc($bk['booking_code']) ?></td>
                  <td class="py-3 px-4"><strong><?= $esc($bk['guest_name']) ?></strong> · <?= $esc($bk['guest_phone']) ?><div class="text-[11px] font-mono text-slate-500">CCCD: <?= $esc($bk['masked_id_number'] ?: 'Chưa cập nhật') ?></div></td>
                  <td class="py-3 px-4"><?= $esc($bk['category_name']) ?><div class="text-[11px] text-slate-500"><?= $bk['assigned_room_number'] ? 'Phòng ' . $esc($bk['assigned_room_number']) : 'Chưa gán phòng' ?></div></td>
                  <td class="py-3 px-4 tabular-nums"><?= $esc($bk['check_in_date']) ?> → <?= $esc($bk['check_out_date']) ?></td>
                  <td class="py-3 px-4 text-right tabular-nums"><strong><?= $fmt($bk['total_estimated']) ?></strong><div class="text-[11px] text-emerald-700">Đã thu: <?= $fmt($bk['total_paid']) ?></div></td>
                  <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold <?= $stCls ?>"><?= $esc($stLbl) ?></span></td>
                  <td class="py-3 px-4 text-right"><button type="button" onclick='openBookingDetailModal(<?= $bkJson ?>)' class="px-3 py-1 bg-[#0E1D33] text-white rounded-lg font-semibold">Chi tiết</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'booking_performance'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Hiệu quả đặt phòng & Tốc độ đặt phòng</h1>
            <p class="text-xs text-slate-500">Tính toán thời gian thực từ dữ liệu MySQL · Kỳ <?= $esc($analytics['start_date']) ?> → <?= $esc($analytics['end_date']) ?></p>
          </div>
          <form method="GET" action="/admin" class="flex flex-wrap items-center gap-2 text-xs">
            <input type="hidden" name="tab" value="booking_performance" />
            <select name="basis" onchange="this.form.submit()" class="px-3 py-1.5 border border-[#E6DFD1] rounded-lg bg-white font-semibold">
              <option value="stay_night" <?= $basis === 'stay_night' ? 'selected' : '' ?>>Theo đêm lưu trú thực tế</option>
              <option value="booking_date" <?= $basis === 'booking_date' ? 'selected' : '' ?>>Theo ngày tạo đơn</option>
              <option value="checkin_date" <?= $basis === 'checkin_date' ? 'selected' : '' ?>>Theo ngày nhận phòng</option>
            </select>
            <select name="days" onchange="this.form.submit()" class="px-3 py-1.5 border border-[#E6DFD1] rounded-lg bg-white font-semibold">
              <option value="7" <?= $days === 7 ? 'selected' : '' ?>>7 ngày</option>
              <option value="14" <?= $days === 14 ? 'selected' : '' ?>>14 ngày</option>
              <option value="30" <?= $days === 30 ? 'selected' : '' ?>>30 ngày</option>
            </select>
            <input type="date" name="end_date" value="<?= $esc($endDate) ?>" onchange="this.form.submit()" class="px-3 py-1.5 border border-[#E6DFD1] rounded-lg bg-white font-semibold" />
          </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-4">
            <div class="text-[11px] font-bold uppercase text-slate-500">Tổng giá trị hợp đồng đã đặt</div>
            <div class="font-serif text-2xl font-bold mt-1 tabular-nums"><?= $fmt($anSum['total_contracted'] ?? 0) ?></div>
            <div class="text-[11px] text-slate-500 mt-0.5">So với kỳ trước: <strong><?= $pctDelta($anSum['total_contracted'] ?? 0, $prevSum['total_contracted'] ?? 0) ?></strong></div>
          </div>
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-4">
            <div class="text-[11px] font-bold uppercase text-slate-500">Tổng thực thu (Payments)</div>
            <div class="font-serif text-2xl font-bold mt-1 tabular-nums text-emerald-700"><?= $fmt($anSum['collected_rev'] ?? 0) ?></div>
            <div class="text-[11px] text-slate-500 mt-0.5">Kỳ trước: <?= $fmt($prevSum['collected_rev'] ?? 0) ?></div>
          </div>
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-4">
            <div class="text-[11px] font-bold uppercase text-slate-500">ADR (Giá bình quân / đêm)</div>
            <div class="font-serif text-2xl font-bold mt-1 tabular-nums text-[#A8843E]"><?= $fmt($anSum['adr'] ?? 0) ?></div>
            <div class="text-[11px] text-slate-500 mt-0.5"><?= (int) ($anSum['room_nights'] ?? 0) ?> đêm phòng</div>
          </div>
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-4">
            <div class="text-[11px] font-bold uppercase text-slate-500">Công suất sử dụng phòng</div>
            <div class="font-serif text-2xl font-bold mt-1 tabular-nums"><?= $anSum['occupancy_pct'] ?? 0 ?>%</div>
            <div class="text-[11px] text-slate-500 mt-0.5"><?= (int) ($anSum['bookings_count'] ?? 0) ?> lượt đặt mới</div>
          </div>
        </div>

        <!-- Biểu đồ doanh thu theo ngày (Click để xem chi tiết từng đơn đóng góp trong ngày) -->
        <?php
        $anPointsJson = $esc(json_encode($anPoints, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE));
        $periodByCat = $curAn['by_category'] ?? [];
        $periodContrib = $curAn['contributing_bookings'] ?? [];
        $safeMaxRev = max(1, (float) ($maxBarRev ?? 0));
        ?>
        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 class="font-serif text-xl font-semibold">Biểu đồ doanh thu từng ngày (Phòng + Dịch vụ)</h3>
              <p class="text-[11px] text-slate-500">Nhấn vào cột ngày bất kỳ để kiểm tra chi tiết các đơn đặt phòng đóng góp doanh thu trong ngày đó</p>
            </div>
          </div>
          <div class="flex items-end gap-2 h-52 pt-3 overflow-x-auto">
            <?php foreach ($anPoints as $idx => $pt):
              $ptRev = (float) ($pt['total_revenue'] ?? 0);
              $hPct = $ptRev > 0 ? max(6, min(100, round(($ptRev / $safeMaxRev) * 100))) : 3;
            ?>
              <button type="button" onclick="showDayBookings(<?= (int) $idx ?>)" class="flex-1 min-w-[36px] flex flex-col items-center gap-1.5 h-full group focus:outline-none">
                <span class="text-[10px] tabular-nums text-slate-600 shrink-0"><?= $ptRev > 0 ? number_format($ptRev / 1000000, 1) . 'M' : '0' ?></span>
                <div class="w-full flex-1 flex items-end justify-center min-h-[96px]">
                  <div class="w-full max-w-[28px] <?= $ptRev > 0 ? 'bg-[#0E1D33] group-hover:bg-[#C9A45C]' : 'bg-slate-200' ?> transition-colors rounded-t" style="height: <?= $hPct ?>%" title="<?= $esc($pt['date']) ?>: <?= $fmt($ptRev) ?>"></div>
                </div>
                <span class="text-[10px] font-mono text-slate-500 group-hover:text-[#0E1D33] group-hover:font-bold shrink-0"><?= $esc($pt['label']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
          <div id="dayPerfDetailBox" class="hidden p-4 rounded-xl bg-[#FAF7F0] border border-[#E6DFD1] text-xs space-y-2"></div>
        </div>
        <script>window.__anPointsData = <?= json_encode($anPoints, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;</script>

        <!-- Tốc độ đặt phòng chi tiết từng ngày + Cơ cấu hạng phòng -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
          <div class="xl:col-span-2 bg-white border border-[#E6DFD1] rounded-2xl p-5 overflow-x-auto">
            <h3 class="font-serif text-xl font-semibold mb-3">Tốc độ đặt phòng tổng thể &amp; Chi tiết từng ngày</h3>
            <table class="w-full text-left text-xs">
              <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
                <tr><th class="py-2.5 px-3">Ngày</th><th class="py-2.5 px-3 text-right">Số đơn</th><th class="py-2.5 px-3 text-right">Đêm phòng</th><th class="py-2.5 px-3 text-right">Công suất</th><th class="py-2.5 px-3 text-right">Doanh thu phòng</th><th class="py-2.5 px-3 text-right">Doanh thu DV</th><th class="py-2.5 px-3 text-right">Tổng hợp đồng</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($anPoints as $idx => $pt): ?>
                  <tr onclick="showDayBookings(<?= (int) $idx ?>)" class="cursor-pointer hover:bg-[#FAF7F0]">
                    <td class="py-2 px-3 font-mono font-semibold"><?= $esc($pt['date']) ?></td>
                    <td class="py-2 px-3 text-right tabular-nums"><?= (int) $pt['bookings_count'] ?></td>
                    <td class="py-2 px-3 text-right tabular-nums"><?= (int) $pt['room_nights'] ?></td>
                    <td class="py-2 px-3 text-right tabular-nums"><?= $pt['occupancy_pct'] ?>%</td>
                    <td class="py-2 px-3 text-right tabular-nums"><?= $fmt($pt['room_revenue']) ?></td>
                    <td class="py-2 px-3 text-right tabular-nums"><?= $fmt($pt['service_revenue']) ?></td>
                    <td class="py-2 px-3 text-right font-bold tabular-nums"><?= $fmt($pt['total_revenue']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3 overflow-x-auto">
            <h3 class="font-serif text-xl font-semibold">Doanh thu theo Hạng phòng trong kỳ</h3>
            <?php if (empty($periodByCat)): ?>
              <p class="text-xs text-slate-500">Chưa có doanh thu phát sinh trong kỳ đã chọn.</p>
            <?php else: ?>
              <div class="space-y-2.5 text-xs">
                <?php foreach ($periodByCat as $pbc): ?>
                  <div class="p-3 rounded-xl bg-[#FAF7F0] border border-[#EFE9DC]">
                    <div class="font-bold text-[#0E1D33]"><?= $esc($pbc['name']) ?></div>
                    <div class="flex justify-between text-[11px] text-slate-600 mt-1">
                      <span><?= (int) $pbc['room_nights'] ?> đêm phòng</span>
                      <strong class="text-[#A8843E] tabular-nums"><?= $fmt($pbc['total_revenue']) ?></strong>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Cơ sở dữ liệu & Nguồn gốc số liệu trong kỳ -->
        <div class="bg-[#FAF7F0] border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800">CƠ SỞ DỮ LIỆU &amp; NGUỒN GỐC SỐ LIỆU TRONG KỲ (<?= count($periodContrib) ?> ĐƠN)</h3>
              <p class="text-[11px] text-slate-600 mt-0.5">Toàn bộ chỉ số Tổng giá trị hợp đồng, ADR và Công suất được cộng dồn trực tiếp từ các đơn đặt phòng thực tế bên dưới</p>
            </div>
          </div>
          <div class="bg-white border border-[#EFE9DC] rounded-xl overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#EFE9DC]">
                <tr><th class="py-2.5 px-3">Mã đơn</th><th class="py-2.5 px-3">Khách hàng</th><th class="py-2.5 px-3">Hạng phòng</th><th class="py-2.5 px-3">Ngày ở</th><th class="py-2.5 px-3 text-right">Tiền phòng</th><th class="py-2.5 px-3 text-right">Dịch vụ</th><th class="py-2.5 px-3">Trạng thái</th><th class="py-2.5 px-3 text-right">Xem</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($periodContrib)): ?>
                  <tr><td colspan="8" class="py-6 text-center text-slate-500">Không có đơn đặt phòng nào thuộc kỳ báo cáo này.</td></tr>
                <?php else: foreach ($periodContrib as $cb):
                  $cbSrv = max((float) ($cb['service_rev'] ?? 0), (float) ($cb['addon_subtotal'] ?? 0));
                  [$stLbl, $stCls] = $statusLabels[$cb['status']] ?? [$cb['status'], 'bg-slate-100 text-slate-700 border-slate-200'];
                  $matchedFull = null;
                  foreach ($bookings as $bf) { if ((int) $bf['id'] === (int) $cb['id']) { $matchedFull = $bf; break; } }
                ?>
                  <tr>
                    <td class="py-2 px-3 font-mono font-bold"><?= $esc($cb['booking_code']) ?></td>
                    <td class="py-2 px-3"><?= $esc($cb['guest_name']) ?></td>
                    <td class="py-2 px-3"><?= $esc($cb['category_name']) ?></td>
                    <td class="py-2 px-3 tabular-nums"><?= $esc($cb['check_in_date']) ?> → <?= $esc($cb['check_out_date']) ?></td>
                    <td class="py-2 px-3 text-right tabular-nums"><?= $fmt($cb['room_subtotal']) ?></td>
                    <td class="py-2 px-3 text-right tabular-nums"><?= $fmt($cbSrv) ?></td>
                    <td class="py-2 px-3"><span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold <?= $stCls ?>"><?= $esc($stLbl) ?></span></td>
                    <td class="py-2 px-3 text-right">
                      <?php if ($matchedFull): ?>
                        <button type="button" onclick='openBookingDetailModal(<?= $esc(json_encode($matchedFull, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE)) ?>)' class="px-2.5 py-1 bg-[#0E1D33] text-white rounded-lg font-semibold">Chi tiết</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'payments'): ?>
        <div>
          <h1 class="font-serif text-3xl font-semibold">Tổng quan Thanh toán & Công nợ</h1>
          <p class="text-xs text-slate-500">Thống kê 3 phương thức thanh toán thống nhất: Thẻ, Mã QR / Chuyển khoản, Tiền mặt</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5">
            <div class="text-xs font-bold uppercase text-slate-500">Tổng thực thu (Verified)</div>
            <div class="font-serif text-3xl font-bold text-emerald-700 mt-1 tabular-nums"><?= $fmt($paymentsOverview['totalCollectedAll']) ?></div>
          </div>
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5">
            <div class="text-xs font-bold uppercase text-slate-500">Chuyển khoản chờ đối soát</div>
            <div class="font-serif text-3xl font-bold text-amber-700 mt-1 tabular-nums"><?= $fmt($paymentsOverview['pendingTransferAmount']) ?></div>
            <div class="text-xs text-slate-500 mt-0.5"><?= (int) $paymentsOverview['pendingTransferCount'] ?> giao dịch</div>
          </div>
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5">
            <div class="text-xs font-bold uppercase text-slate-500">Công nợ khách chưa thanh toán</div>
            <div class="font-serif text-3xl font-bold text-rose-700 mt-1 tabular-nums"><?= $fmt($paymentsOverview['uncollectedReceivables']) ?></div>
            <div class="text-xs text-slate-500 mt-0.5"><?= (int) $paymentsOverview['receivableCount'] ?> hồ sơ còn dư nợ</div>
          </div>
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5">
            <div class="text-xs font-bold uppercase text-slate-500">Hoàn tiền hủy phòng</div>
            <div class="font-serif text-3xl font-bold text-sky-700 mt-1 tabular-nums"><?= $fmt($paymentsOverview['pendingRefundAmount'] ?? 0) ?></div>
            <div class="text-xs text-slate-500 mt-0.5 tabular-nums"><?= (int) ($paymentsOverview['pendingRefundCount'] ?? 0) ?> chờ duyệt/chi · Đã hoàn: <?= $fmt($paymentsOverview['totalRefundedProcessed'] ?? 0) ?></div>
          </div>
        </div>

        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-4">
          <h3 class="font-serif text-xl font-semibold">THEO PHƯƠNG THỨC (30 NGÀY QUA)</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <?php foreach ($paymentsOverview['byMethod30d'] as $pm): ?>
              <div class="p-4 rounded-xl border border-[#EFE9DC] bg-[#FAF7F0]">
                <div class="flex items-center justify-between font-bold mb-1">
                  <span><?= $esc($pm['label']) ?></span>
                  <span class="tabular-nums"><?= $pm['pct'] ?>%</span>
                </div>
                <div class="font-serif text-2xl font-bold text-[#0E1D33] tabular-nums"><?= $fmt($pm['amount']) ?></div>
                <div class="mt-2 h-2 bg-slate-200 rounded-full overflow-hidden"><div class="h-full" style="width: <?= $pm['pct'] ?>%; background: <?= $pm['color'] ?>"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Chuyển khoản đang chờ lễ tân đối soát -->
        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3 overflow-x-auto">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 class="font-serif text-xl font-semibold">Chuyển khoản đang chờ lễ tân đối soát</h3>
              <p class="text-[11px] text-slate-500">Các giao dịch chuyển khoản VietQR đang chờ Quầy Lễ tân xác nhận trên bảng điều hành</p>
            </div>
            <input type="text" oninput="filterPendingTransfers(this.value)" placeholder="Tìm mã đơn, tên khách, mã GD..." class="px-3 py-1.5 text-xs border border-[#E6DFD1] rounded-xl bg-white" />
          </div>
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr><th class="py-2.5 px-3">Mã GD / Thời gian</th><th class="py-2.5 px-3">Mã đơn &amp; Khách</th><th class="py-2.5 px-3">Tham chiếu</th><th class="py-2.5 px-3 text-right">Số tiền</th><th class="py-2.5 px-3 text-right">Trạng thái</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (empty($paymentsOverview['pendingTransfers'])): ?>
                <tr><td colspan="5" class="py-5 text-center text-slate-500">Không có giao dịch chuyển khoản nào đang chờ đối soát.</td></tr>
              <?php else: foreach ($paymentsOverview['pendingTransfers'] as $ptx): ?>
                <tr class="ptx-row" data-search="<?= $esc(strtolower($ptx['payment_code'] . ' ' . $ptx['booking_code'] . ' ' . $ptx['guest_name'] . ' ' . $ptx['guest_phone'] . ' ' . ($ptx['reference_no'] ?? ''))) ?>">
                  <td class="py-2.5 px-3"><strong class="font-mono"><?= $esc($ptx['payment_code']) ?></strong><div class="text-[10px] text-slate-500"><?= $esc($ptx['recorded_at']) ?></div></td>
                  <td class="py-2.5 px-3"><strong class="font-mono"><?= $esc($ptx['booking_code']) ?></strong> · <?= $esc($ptx['guest_name']) ?> (<?= $esc($ptx['guest_phone']) ?>)</td>
                  <td class="py-2.5 px-3 font-mono text-slate-600"><?= $esc($ptx['reference_no'] ?: ($ptx['note'] ?: 'Chờ đối soát')) ?></td>
                  <td class="py-2.5 px-3 text-right font-bold text-amber-700 tabular-nums"><?= $fmt($ptx['amount']) ?></td>
                  <td class="py-2.5 px-3 text-right"><a href="/reception?search=<?= urlencode($ptx['booking_code']) ?>" class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 font-semibold">Đối soát tại Lễ tân →</a></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3 overflow-x-auto">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-serif text-xl font-semibold">Danh sách công nợ chưa tất toán</h3>
            <input type="text" oninput="filterReceivables(this.value)" placeholder="Tìm mã đơn, khách hàng..." class="px-3 py-1.5 text-xs border border-[#E6DFD1] rounded-xl bg-white" />
          </div>
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr><th class="py-2.5 px-3">Mã đơn</th><th class="py-2.5 px-3">Khách hàng</th><th class="py-2.5 px-3 text-right">Tổng hóa đơn</th><th class="py-2.5 px-3 text-right">Đã thanh toán</th><th class="py-2.5 px-3 text-right">Còn phải thu</th><th class="py-2.5 px-3 text-right">Hóa đơn</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (empty($paymentsOverview['receivables'])): ?>
                <tr><td colspan="6" class="py-5 text-center text-slate-500">Không còn hồ sơ dư nợ chưa tất toán.</td></tr>
              <?php else: foreach ($paymentsOverview['receivables'] as $rc):
                $rcJson = $esc(json_encode($rc, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE));
              ?>
                <tr class="rec-row" data-search="<?= $esc(strtolower($rc['booking_code'] . ' ' . $rc['guest_name'] . ' ' . $rc['guest_phone'])) ?>">
                  <td class="py-2.5 px-3 font-mono font-bold"><?= $esc($rc['booking_code']) ?></td>
                  <td class="py-2.5 px-3"><?= $esc($rc['guest_name']) ?> · <?= $esc($rc['guest_phone']) ?></td>
                  <td class="py-2.5 px-3 text-right tabular-nums"><?= $fmt($rc['computed_total']) ?></td>
                  <td class="py-2.5 px-3 text-right text-emerald-700 tabular-nums"><?= $fmt($rc['total_paid']) ?></td>
                  <td class="py-2.5 px-3 text-right font-bold text-rose-700 tabular-nums"><?= $fmt($rc['outstanding_amount']) ?></td>
                  <td class="py-2.5 px-3 text-right"><button type="button" onclick='openBookingDetailModal(<?= $rcJson ?>)' class="px-2.5 py-1 bg-[#0E1D33] text-white rounded-lg font-semibold">Chi tiết</button></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Quản lý & Giải ngân Hoàn tiền Hủy phòng -->
        <?php $adminRefunds = $paymentsOverview['refundRequests'] ?? []; ?>
        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3 overflow-x-auto">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 class="font-serif text-xl font-semibold">Quản lý Yêu cầu Hoàn tiền Hủy phòng (<?= count($adminRefunds) ?>)</h3>
              <p class="text-[11px] text-slate-500">Quy trình chuẩn: Yêu cầu hoàn (Chờ duyệt) → Phê duyệt (hoặc Từ chối) → Giải ngân &amp; lưu mã bút toán thực chi.</p>
            </div>
          </div>
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr>
                <th class="py-2.5 px-3">Mã hoàn / Đơn</th>
                <th class="py-2.5 px-3">Khách hàng &amp; Lý do hủy</th>
                <th class="py-2.5 px-3 text-right">Đã thu / Phí giữ / Hoàn</th>
                <th class="py-2.5 px-3">Trạng thái</th>
                <th class="py-2.5 px-3">Xử lý hoàn tiền</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (empty($adminRefunds)): ?>
                <tr><td colspan="5" class="py-5 text-center text-slate-500">Chưa có hồ sơ hoàn tiền hủy phòng nào.</td></tr>
              <?php else: foreach ($adminRefunds as $rf):
                $rfId = (int) $rf['id'];
                $rfSt = (string) ($rf['status'] ?? 'pending');
                $rfBadge = $rfSt === 'processed'
                  ? ['Đã hoàn tiền', 'bg-emerald-50 text-emerald-700 border-emerald-200']
                  : ($rfSt === 'approved'
                    ? ['Đã duyệt · Chờ chi', 'bg-sky-50 text-sky-700 border-sky-200']
                    : ($rfSt === 'rejected'
                      ? ['Từ chối hoàn', 'bg-rose-50 text-rose-700 border-rose-200']
                      : ['Chờ phê duyệt', 'bg-amber-50 text-amber-800 border-amber-200']));
              ?>
                <tr>
                  <td class="py-2.5 px-3 whitespace-nowrap">
                    <div class="font-mono font-bold text-[#0E1D33]"><?= $esc($rf['refund_code']) ?></div>
                    <div class="text-[11px] font-mono text-slate-500"><?= $esc($rf['booking_code']) ?></div>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="font-semibold"><?= $esc($rf['guest_name']) ?> · <span class="tabular-nums text-slate-500"><?= $esc($rf['guest_phone']) ?></span></div>
                    <div class="text-[11px] text-slate-600 mt-0.5"><?= $esc($rf['cancel_reason'] ?: 'Hủy đặt phòng') ?></div>
                  </td>
                  <td class="py-2.5 px-3 text-right tabular-nums whitespace-nowrap">
                    <div class="text-[11px] text-slate-500">Thu: <?= $fmt($rf['paid_verified_amount']) ?> · Giữ: <?= $fmt($rf['retained_fee']) ?></div>
                    <div class="font-bold text-emerald-700"><?= $fmt($rf['refund_amount']) ?></div>
                  </td>
                  <td class="py-2.5 px-3 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-full border text-[10px] font-semibold <?= $rfBadge[1] ?>"><?= $rfBadge[0] ?></span>
                    <?php if (!empty($rf['reference_no'])): ?>
                      <div class="text-[10px] font-mono text-slate-500 mt-0.5">Mã chi: <?= $esc($rf['reference_no']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3 min-w-[260px]">
                    <?php if ($rfSt === 'pending'): ?>
                      <form method="POST" action="/admin/refunds/<?= $rfId ?>/decide" class="flex flex-wrap items-center gap-1.5">
                        <input type="hidden" name="return_tab" value="payments" />
                        <select name="refund_method" class="px-2 py-1 border rounded bg-white text-[11px]">
                          <option value="bank_transfer">Chuyển khoản NH</option>
                          <option value="qr_bank">Chuyển khoản QR</option>
                          <option value="cash">Tiền mặt</option>
                          <option value="card">Hoàn qua thẻ</option>
                        </select>
                        <input type="text" name="decision_note" placeholder="Ghi chú duyệt / từ chối..." class="flex-1 min-w-[120px] px-2 py-1 border rounded text-[11px]" />
                        <button type="submit" name="decision" value="approved" class="px-2.5 py-1 bg-emerald-600 text-white rounded font-semibold">Duyệt</button>
                        <button type="submit" name="decision" value="rejected" class="px-2.5 py-1 border border-rose-300 text-rose-700 rounded font-semibold">Từ chối</button>
                      </form>
                    <?php elseif ($rfSt === 'approved'): ?>
                      <form method="POST" action="/admin/refunds/<?= $rfId ?>/process" class="flex flex-wrap items-center gap-1.5">
                        <input type="hidden" name="return_tab" value="payments" />
                        <select name="refund_method" class="px-2 py-1 border rounded bg-white text-[11px]">
                          <option value="bank_transfer" <?= ($rf['refund_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Chuyển khoản NH</option>
                          <option value="qr_bank" <?= ($rf['refund_method'] ?? '') === 'qr_bank' ? 'selected' : '' ?>>Chuyển khoản QR</option>
                          <option value="cash" <?= ($rf['refund_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Tiền mặt</option>
                          <option value="card" <?= ($rf['refund_method'] ?? '') === 'card' ? 'selected' : '' ?>>Hoàn qua thẻ</option>
                        </select>
                        <input type="text" name="reference_no" required placeholder="Mã bút toán chi (FT/PC)..." class="w-36 px-2 py-1 border rounded font-mono text-[11px]" />
                        <button type="submit" class="px-2.5 py-1 bg-[#0E1D33] text-white rounded font-semibold">Xác nhận đã chi</button>
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

      <?php elseif ($tab === 'customers'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Hồ sơ Khách hàng & Hội viên</h1>
            <p class="text-xs text-slate-500">Số CCCD/Hộ chiếu được che ẩn (0790••••1234) và không hỗ trợ sửa dữ liệu định danh khách từ trang Quản trị</p>
          </div>
          <input type="text" oninput="filterCustomersTable(this.value)" placeholder="Tìm tên, SĐT, email khách hàng..." class="px-3 py-2 text-xs border border-[#E6DFD1] rounded-xl bg-white" />
        </div>

        <div class="bg-[#FFFBEB] border border-[#FDE68A] rounded-2xl p-4 text-xs text-amber-950 space-y-1">
          <div class="font-bold uppercase tracking-wider text-[11px] text-[#A8843E]">🔒 Lưu ý quy định bảo mật &amp; Các trường hợp khóa tài khoản khách hàng</div>
          <p>Admin chỉ xem tổng quan lịch sử lưu trú, không chỉnh sửa thông tin cá nhân và không xem toàn văn CCCD/Hộ chiếu. Thao tác <strong>Khóa tài khoản</strong> (bắt buộc nhập lý do) chỉ áp dụng trong các trường hợp: (1) Khách hủy phòng hoặc đặt giữ chỗ ảo liên tiếp nhiều lần gây giữ phòng sai lệch; (2) No-show không thanh toán theo cam kết; (3) Phát hiện gian lận mã ưu đãi / chứng từ chuyển khoản giả mạo; (4) Chủ tài khoản yêu cầu tạm khóa bảo mật.</p>
        </div>

        <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr><th class="py-3 px-4">Khách hàng</th><th class="py-3 px-4">Liên hệ</th><th class="py-3 px-4">CCCD / Hộ chiếu (Che ẩn)</th><th class="py-3 px-4 text-right">Số đơn / Chi tiêu</th><th class="py-3 px-4">Trạng thái</th><th class="py-3 px-4 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($customers as $cu):
                $histJson = $esc(json_encode($cu['bookings_history'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE));
              ?>
                <tr class="cust-row" data-search="<?= $esc(strtolower($cu['full_name'] . ' ' . $cu['phone'] . ' ' . $cu['email'])) ?>">
                  <td class="py-3 px-4"><strong><?= $esc($cu['full_name']) ?></strong><div class="text-[11px] text-[#A8843E] font-semibold"><?= $esc($cu['membership_tier']) ?></div></td>
                  <td class="py-3 px-4"><?= $esc($cu['phone']) ?><div class="text-[11px] text-slate-500"><?= $esc($cu['email']) ?></div></td>
                  <td class="py-3 px-4 font-mono text-slate-600"><?= $esc($cu['masked_id_number'] ?: 'Chưa có') ?></td>
                  <td class="py-3 px-4 text-right tabular-nums"><strong><?= (int) $cu['bookings_count'] ?> đơn</strong><div class="text-[11px] text-emerald-700"><?= $fmt($cu['total_spent']) ?></div></td>
                  <td class="py-3 px-4">
                    <?php if ((int) $cu['is_active'] === 1): ?>
                      <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold">Hoạt động</span>
                    <?php else: ?>
                      <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[10px] font-bold">Đã khóa</span>
                      <div class="text-[10px] text-rose-600 mt-0.5"><?= $esc($cu['lock_reason']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-4 text-right flex justify-end gap-1.5">
                    <button type="button" onclick='openCustHistoryModal("<?= $esc($cu['full_name']) ?>", <?= $histJson ?>)' class="px-2.5 py-1 border rounded-lg font-semibold">Lịch sử</button>
                    <?php if ((int) $cu['is_active'] === 1): ?>
                      <button type="button" onclick="openLockUserModal(<?= (int) $cu['id'] ?>, '<?= $esc($cu['full_name']) ?>', 'customers')" class="px-2.5 py-1 border border-rose-200 text-rose-700 rounded-lg font-semibold">Khóa</button>
                    <?php else: ?>
                      <form method="POST" action="/admin/users/<?= (int) $cu['id'] ?>/toggle">
                        <input type="hidden" name="return_tab" value="customers" />
                        <button type="submit" class="px-2.5 py-1 border border-emerald-200 text-emerald-700 rounded-lg font-semibold">Mở khóa</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'staff'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Nhân sự & Phân quyền</h1>
            <p class="text-xs text-slate-500">Quản lý Lễ tân, Bếp/Dịch vụ và Quản trị viên (Khóa tài khoản bắt buộc nêu lý do)</p>
          </div>
          <button type="button" onclick="document.getElementById('newStaffModal').classList.remove('hidden')" class="px-4 py-2 bg-[#0E1D33] text-white text-xs font-bold rounded-xl">+ Thêm nhân sự</button>
        </div>

        <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr><th class="py-3 px-4">Nhân sự</th><th class="py-3 px-4">Liên hệ</th><th class="py-3 px-4">Bộ phận / Vai trò</th><th class="py-3 px-4">Trạng thái</th><th class="py-3 px-4 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($staff as $st): ?>
                <tr>
                  <td class="py-3 px-4"><strong><?= $esc($st['full_name']) ?></strong><div class="text-[11px] text-slate-500"><?= $esc($st['membership_tier']) ?></div></td>
                  <td class="py-3 px-4"><?= $esc($st['email']) ?><div class="text-[11px] text-slate-500"><?= $esc($st['phone']) ?></div></td>
                  <td class="py-3 px-4">
                    <form method="POST" action="/admin/users/<?= (int) $st['id'] ?>/role" class="inline-flex gap-1.5">
                      <select name="role" class="px-2 py-1 border rounded-lg bg-white text-xs" <?= (int) $st['id'] === (int) $adminUser['id'] ? 'disabled' : '' ?>>
                        <option value="receptionist" <?= $st['role'] === 'receptionist' ? 'selected' : '' ?>>Lễ tân / Bếp & DV</option>
                        <option value="admin" <?= $st['role'] === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
                      </select>
                      <?php if ((int) $st['id'] !== (int) $adminUser['id']): ?>
                        <button type="submit" class="px-2 py-1 border rounded-lg font-semibold">Lưu</button>
                      <?php endif; ?>
                    </form>
                  </td>
                  <td class="py-3 px-4">
                    <?php if ((int) $st['is_active'] === 1): ?>
                      <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold">Hoạt động</span>
                    <?php else: ?>
                      <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[10px] font-bold">Đã khóa</span>
                      <div class="text-[10px] text-rose-600 mt-0.5"><?= $esc($st['lock_reason']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <?php if ((int) $st['id'] === (int) $adminUser['id']): ?>
                      <span class="text-slate-400">Đang đăng nhập</span>
                    <?php elseif ((int) $st['is_active'] === 1): ?>
                      <button type="button" onclick="openLockUserModal(<?= (int) $st['id'] ?>, '<?= $esc($st['full_name']) ?>', 'staff')" class="px-2.5 py-1 border border-rose-200 text-rose-700 rounded-lg font-semibold">Khóa</button>
                    <?php else: ?>
                      <form method="POST" action="/admin/users/<?= (int) $st['id'] ?>/toggle">
                        <input type="hidden" name="return_tab" value="staff" />
                        <button type="submit" class="px-2.5 py-1 border border-emerald-200 text-emerald-700 rounded-lg font-semibold">Mở khóa</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'policies'): ?>
        <div>
          <h1 class="font-serif text-3xl font-semibold">Chính sách, Thanh toán & Phát thông báo</h1>
          <p class="text-xs text-slate-500">Cập nhật tại đây sẽ đồng bộ ngay ra Trang chủ, Trang đặt phòng và Footer của khách sạn</p>
        </div>

        <form method="POST" action="/admin/policies/bulk" class="space-y-5 text-xs">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
              <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">1. Chính sách đặt / hủy / trẻ em & nhận trả</h3>
              <div class="grid grid-cols-2 gap-3">
                <div><label class="block font-semibold mb-1">Giờ nhận phòng</label><input type="text" name="updates[checkin_time]" value="<?= $esc($pol['checkin_time'] ?? '14:00') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Giờ trả phòng</label><input type="text" name="updates[checkout_time]" value="<?= $esc($pol['checkout_time'] ?? '12:00') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Tỷ lệ đặt cọc (%)</label><input type="number" name="updates[deposit_percent]" value="<?= $esc($pol['deposit_percent'] ?? '30') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Thuế & Phí DV (%)</label><input type="text" name="updates[tax_service_percent]" value="<?= $esc($pol['tax_service_percent'] ?? '13.4') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Miễn phí hủy trước (ngày)</label><input type="number" name="updates[free_cancel_days]" value="<?= $esc($pol['free_cancel_days'] ?? '14') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Phí hủy muộn (%)</label><input type="number" name="updates[late_cancel_fee_percent]" value="<?= $esc($pol['late_cancel_fee_percent'] ?? '50') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Trẻ miễn phí tối đa (tuổi)</label><input type="number" name="updates[free_child_max_age]" value="<?= $esc($pol['free_child_max_age'] ?? '6') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Phụ phí trẻ 6-11 tuổi (đ)</label><input type="number" name="updates[child_surcharge_price]" value="<?= $esc($pol['child_surcharge_price'] ?? '250000') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Phụ phí giường phụ (đ)</label><input type="number" name="updates[extra_bed_price]" value="<?= $esc($pol['extra_bed_price'] ?? '650000') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
                <div><label class="block font-semibold mb-1">Đổi lịch tối thiểu trước (ngày)</label><input type="number" name="updates[min_change_days]" value="<?= $esc($pol['min_change_days'] ?? '3') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
              </div>
            </div>

            <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
              <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">2. Thông tin Ngân hàng nhận thanh toán QR</h3>
              <div><label class="block font-semibold mb-1">Tên ngân hàng</label><input type="text" name="updates[bank_name]" value="<?= $esc($pol['bank_name'] ?? 'Vietcombank — CN Phú Quốc') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
              <div><label class="block font-semibold mb-1">Số tài khoản</label><input type="text" name="updates[bank_account_no]" value="<?= $esc($pol['bank_account_no'] ?? '0121 000 889 999') ?>" class="w-full px-3 py-2 border rounded-lg font-mono" /></div>
              <div><label class="block font-semibold mb-1">Chủ tài khoản</label><input type="text" name="updates[bank_account_holder]" value="<?= $esc($pol['bank_account_holder'] ?? 'KHÁCH SẠN MƯỜNG THANH LUXURY PHÚ QUỐC') ?>" class="w-full px-3 py-2 border rounded-lg" /></div>
            </div>

            <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
              <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">3. Chính sách bảo mật & Quyền lợi Hội viên</h3>
              <div><label class="block font-semibold mb-1">Chính sách bảo mật (Footer & Phiếu XN)</label><textarea rows="3" name="updates[privacy_policy_text]" class="w-full px-3 py-2 border rounded-lg"><?= $esc($pol['privacy_policy_text'] ?? '') ?></textarea></div>
              <div><label class="block font-semibold mb-1">Đặc quyền Hội viên</label><textarea rows="3" name="updates[member_benefits_text]" class="w-full px-3 py-2 border rounded-lg"><?= $esc($pol['member_benefits_text'] ?? '') ?></textarea></div>
            </div>

            <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
              <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">4. Mẫu thông báo hệ thống</h3>
              <div><label class="block font-semibold mb-1">Mẫu xác nhận đặt phòng</label><textarea rows="2" name="updates[notify_template_confirm]" class="w-full px-3 py-2 border rounded-lg"><?= $esc($pol['notify_template_confirm'] ?? '') ?></textarea></div>
              <div><label class="block font-semibold mb-1">Mẫu thông báo ưu đãi</label><textarea rows="2" name="updates[notify_template_promo]" class="w-full px-3 py-2 border rounded-lg"><?= $esc($pol['notify_template_promo'] ?? '') ?></textarea></div>
            </div>
          </div>
          <button type="submit" class="px-6 py-3 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] font-bold uppercase rounded-xl shadow">Lưu & Đồng bộ toàn hệ thống</button>
        </form>

        <form method="POST" action="/admin/notifications/broadcast" class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3 text-xs">
          <h3 class="font-serif text-xl font-semibold">Phát thông báo khuyến mãi tới toàn bộ Hội viên</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" name="title" required placeholder="Tiêu đề thông báo..." class="px-3 py-2 border rounded-lg" />
            <input type="text" name="message" required placeholder="Nội dung chi tiết gửi tới Hội viên..." class="md:col-span-2 px-3 py-2 border rounded-lg" />
          </div>
          <button type="submit" class="px-5 py-2 bg-[#0E1D33] text-white font-bold rounded-xl">Gửi thông báo ngay</button>
        </form>

      <?php elseif ($tab === 'audit'): ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="font-serif text-3xl font-semibold">Nhật ký hoạt động hệ thống (Audit Logs)</h1>
            <p class="text-xs text-slate-500">Lưu vết bất biến mọi đăng nhập và thao tác quản trị, vận hành trong MySQL</p>
          </div>
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <select id="auditRoleFilter" onchange="filterAuditRows()" class="px-3 py-2 border border-[#E6DFD1] rounded-xl bg-white">
              <option value="">Tất cả vai trò</option>
              <option value="admin">Quản trị viên (admin)</option>
              <option value="receptionist">Lễ tân / Bếp (receptionist)</option>
              <option value="customer">Khách hàng (customer)</option>
            </select>
            <select id="auditActorFilter" onchange="filterAuditRows()" class="px-3 py-2 border border-[#E6DFD1] rounded-xl bg-white">
              <option value="">Tất cả người thực hiện</option>
              <?php foreach ($auditActors ?? [] as $actor): ?>
                <option value="<?= $esc(strtolower($actor)) ?>"><?= $esc($actor) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="auditSearchInp" oninput="filterAuditRows()" placeholder="Lọc theo hành động, đối tượng..." class="px-3 py-2 border border-[#E6DFD1] rounded-xl bg-white" />
          </div>
        </div>
        <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#FAF7F0] text-[10px] uppercase text-slate-500 border-b border-[#E6DFD1]">
              <tr><th class="py-3 px-4">Thời gian</th><th class="py-3 px-4">Người thực hiện</th><th class="py-3 px-4">Hành động</th><th class="py-3 px-4">Đối tượng</th><th class="py-3 px-4">Chi tiết</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($auditLogs as $log):
                $act = (string) ($log['action'] ?? '');
                $isLoginLog = ($act === 'USER_LOGIN' || $act === 'CUSTOMER_REGISTER');
              ?>
                <tr class="audit-row" data-role="<?= $esc(strtolower((string) ($log['actor_role'] ?? ''))) ?>" data-actor="<?= $esc(strtolower((string) ($log['actor_name'] ?? ''))) ?>" data-search="<?= $esc(strtolower($act . ' ' . $log['actor_name'] . ' ' . $log['entity_id'] . ' ' . $log['details'])) ?>">
                  <td class="py-2.5 px-4 font-mono text-slate-500 tabular-nums"><?= $esc($log['created_at']) ?></td>
                  <td class="py-2.5 px-4 font-semibold"><?= $esc($log['actor_name']) ?> <span class="text-[10px] text-slate-400">(<?= $esc($log['actor_role']) ?>)</span></td>
                  <td class="py-2.5 px-4 font-mono font-bold">
                    <span class="px-2 py-0.5 rounded <?= $isLoginLog ? 'bg-sky-50 text-sky-800 border border-sky-200' : 'bg-[#FAF7F0] text-[#0E1D33] border border-[#EFE9DC]' ?>"><?= $esc($act) ?></span>
                  </td>
                  <td class="py-2.5 px-4"><?= $esc($log['entity_type']) ?> · <?= $esc($log['entity_id']) ?></td>
                  <td class="py-2.5 px-4 text-slate-700"><?= $esc($log['details']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MODALS DÙNG CHUNG -->
<div id="newCatModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form method="POST" action="/admin/categories" class="bg-white rounded-2xl max-w-lg w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <div class="flex justify-between items-center"><h3 class="font-serif text-xl font-semibold">Thêm hạng phòng mới</h3><button type="button" onclick="this.closest('#newCatModal').classList.add('hidden')">✕</button></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Mã hạng *</label><input type="text" name="code" required placeholder="VD: LUXV" class="w-full px-3 py-2 border rounded-lg font-mono uppercase" /></div>
      <div><label class="block font-semibold mb-1">Giá gốc / đêm *</label><input type="number" name="base_price" required step="50000" value="2500000" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">Tên hạng phòng *</label><input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg" /></div>
    <div class="grid grid-cols-3 gap-2">
      <div><label class="block font-semibold mb-1">Diện tích (m²)</label><input type="number" name="area_sqm" value="40" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Người lớn</label><input type="number" name="max_adults" value="2" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Giường</label><input type="text" name="bed_label" value="1 giường King" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">URL ảnh</label><input type="text" name="image_url" value="/assets/images/room_deluxe_ocean_king_1794856700781.jpg" class="w-full px-3 py-2 border rounded-lg" /></div>
    <div><label class="block font-semibold mb-1">Mô tả</label><textarea name="description" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
    <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('#newCatModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button><button type="submit" class="px-5 py-2 bg-[#0E1D33] text-white font-bold rounded-lg">Tạo hạng phòng</button></div>
  </form>
</div>

<div id="quickRateModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form id="quickRateForm" method="POST" action="" onsubmit="return validateRangeForm(this, 'qrStart', 'qrEnd', false)" class="bg-white rounded-2xl max-w-md w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <div class="flex justify-between items-center"><h3 class="font-serif text-xl font-semibold">Giá / Đóng bán nhanh: <span id="qrCatName"></span></h3><button type="button" onclick="this.closest('#quickRateModal').classList.add('hidden')">✕</button></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Từ ngày *</label><input type="date" id="qrStart" name="start_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required onchange="syncRangeModalDates('qrStart', 'qrEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Đến ngày *</label><input type="date" id="qrEnd" name="end_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required onchange="syncRangeModalDates('qrStart', 'qrEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">Giá áp dụng (để trống nếu chỉ đóng bán)</label><input type="number" step="50000" id="qrPriceInp" name="override_price" class="w-full px-3 py-2 border rounded-lg" /></div>
    <label class="flex items-center gap-2 font-semibold text-rose-700"><input type="checkbox" name="is_stop_sell" value="1" class="accent-rose-600" /><span>Đóng bán hạng phòng (Stop-sell) trong khoảng ngày này</span></label>
    <div><label class="block font-semibold mb-1">Ghi chú</label><input type="text" name="note" placeholder="VD: Cao điểm cuối tuần / Bảo trì khu" class="w-full px-3 py-2 border rounded-lg" /></div>
    <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('#quickRateModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button><button type="submit" class="px-5 py-2 bg-[#0E1D33] text-white font-bold rounded-lg">Áp dụng</button></div>
  </form>
</div>

<div id="addRoomModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form method="POST" action="/admin/rooms" class="bg-white rounded-2xl max-w-md w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <div class="flex justify-between items-center"><h3 class="font-serif text-xl font-semibold">Thêm phòng vật lý mới</h3><button type="button" onclick="this.closest('#addRoomModal').classList.add('hidden')">✕</button></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Số phòng *</label><input type="text" name="room_number" required placeholder="VD: 405" class="w-full px-3 py-2 border rounded-lg uppercase" /></div>
      <div><label class="block font-semibold mb-1">Tầng *</label><input type="number" name="floor" required min="1" value="4" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">Thuộc hạng phòng *</label>
      <select name="category_id" id="addRoomCatSel" class="w-full px-3 py-2 border rounded-lg bg-white">
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $esc($c['name']) ?> (<?= $esc($c['code']) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div><label class="block font-semibold mb-1">Ghi chú</label><input type="text" name="notes" class="w-full px-3 py-2 border rounded-lg" /></div>
    <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('#addRoomModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button><button type="submit" class="px-5 py-2 bg-[#0E1D33] text-white font-bold rounded-lg">Thêm phòng</button></div>
  </form>
</div>

<div id="lockRoomModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form id="lockRoomForm" method="POST" action="" onsubmit="return validateRangeForm(this, 'lrStart', 'lrEnd', false)" class="bg-white rounded-2xl max-w-md w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <div class="flex justify-between items-center"><h3 class="font-serif text-xl font-semibold">Khóa / Bảo trì Phòng <span id="lrNum"></span></h3><button type="button" onclick="this.closest('#lockRoomModal').classList.add('hidden')">✕</button></div>
    <div><label class="block font-semibold mb-1">Loại khóa</label><select name="lock_type" class="w-full px-3 py-2 border rounded-lg bg-white"><option value="maintenance">Bảo trì kỹ thuật</option><option value="locked">Khóa phòng nội bộ</option></select></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Từ ngày</label><input type="date" id="lrStart" name="start_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" onchange="syncRangeModalDates('lrStart', 'lrEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Dự kiến xong</label><input type="date" id="lrEnd" name="end_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" onchange="syncRangeModalDates('lrStart', 'lrEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">Nguyên nhân chi tiết * (tối thiểu 4 ký tự)</label><textarea name="reason" required minlength="4" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
    <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('#lockRoomModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button><button type="submit" class="px-5 py-2 bg-rose-700 text-white font-bold rounded-lg">Xác nhận khóa phòng</button></div>
  </form>
</div>

<div id="lockUserModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form id="lockUserForm" method="POST" action="" class="bg-white rounded-2xl max-w-md w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <input type="hidden" name="return_tab" id="luReturnTab" value="customers" />
    <div class="flex justify-between items-center"><h3 class="font-serif text-xl font-semibold text-rose-800">Khóa tài khoản: <span id="luName"></span></h3><button type="button" onclick="this.closest('#lockUserModal').classList.add('hidden')">✕</button></div>
    <div><label class="block font-semibold mb-1">Lý do khóa tài khoản * (Hiển thị khi tài khoản đăng nhập)</label><textarea name="lock_reason" required minlength="4" rows="3" placeholder="VD: Vi phạm chính sách đặt phòng / Nghỉ việc..." class="w-full px-3 py-2 border rounded-lg"></textarea></div>
    <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('#lockUserModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button><button type="submit" class="px-5 py-2 bg-rose-700 text-white font-bold rounded-lg">Xác nhận khóa</button></div>
  </form>
</div>

<div id="serviceModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form id="serviceForm" method="POST" action="/admin/services" novalidate onsubmit="return validateRangeForm(this, 'smStart', 'smEnd', true)" class="bg-white rounded-2xl max-w-lg w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <div class="flex justify-between items-center"><h3 id="srvModalTitle" class="font-serif text-xl font-semibold">Thêm / Sửa dịch vụ</h3><button type="button" onclick="this.closest('#serviceModal').classList.add('hidden')">✕</button></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Mã DV *</label><input type="text" name="code" id="smCode" required class="w-full px-3 py-2 border rounded-lg font-mono uppercase" /></div>
      <div><label class="block font-semibold mb-1">Nhóm</label><select name="category" id="smCat" class="w-full px-3 py-2 border rounded-lg bg-white"><?php foreach ($srvCatLabels as $ck => $cl): ?><option value="<?= $ck ?>"><?= $esc($cl) ?></option><?php endforeach; ?></select></div>
    </div>
    <div><label class="block font-semibold mb-1">Tên dịch vụ *</label><input type="text" name="name" id="smName" required class="w-full px-3 py-2 border rounded-lg" /></div>
    <div class="grid grid-cols-3 gap-2">
      <div><label class="block font-semibold mb-1">Đơn vị</label><input type="text" name="unit_label" id="smUnit" value="/phần" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Giá gốc *</label><input type="number" name="price" id="smPrice" required step="10000" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Giá ưu đãi</label><input type="number" name="promo_price" id="smPromoPrice" step="10000" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div class="grid grid-cols-3 gap-2">
      <div><label class="block font-semibold mb-1">Nhãn KM</label><input type="text" name="promo_tag" id="smPromoTag" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Từ ngày</label><input type="date" name="promo_start_date" id="smStart" min="<?= date('Y-m-d') ?>" onchange="syncRangeModalDates('smStart', 'smEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Đến ngày</label><input type="date" name="promo_end_date" id="smEnd" min="<?= date('Y-m-d') ?>" onchange="syncRangeModalDates('smStart', 'smEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">Mô tả</label><textarea name="description" id="smDesc" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
    <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('#serviceModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button><button type="submit" class="px-5 py-2 bg-[#0E1D33] text-white font-bold rounded-lg">Lưu dịch vụ</button></div>
  </form>
</div>

<div id="quickSrvModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form id="quickSrvForm" method="POST" action="" novalidate onsubmit="return validateRangeForm(this, 'qsStart', 'qsEnd', true)" class="bg-white rounded-2xl max-w-md w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <div class="flex justify-between items-center"><h3 class="font-serif text-xl font-semibold">Cập nhật giá nhanh: <span id="qsNameTitle"></span></h3><button type="button" onclick="this.closest('#quickSrvModal').classList.add('hidden')">✕</button></div>
    <input type="hidden" name="code" id="qsCode" />
    <input type="hidden" name="name" id="qsName" />
    <input type="hidden" name="category" id="qsCat" />
    <input type="hidden" name="unit_label" id="qsUnit" />
    <input type="hidden" name="description" id="qsDesc" />
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Giá niêm yết *</label><input type="number" name="price" id="qsPrice" required step="10000" class="w-full px-3 py-2 border rounded-lg font-bold" /></div>
      <div><label class="block font-semibold mb-1">Giá ưu đãi (để trống = về giá gốc)</label><input type="number" name="promo_price" id="qsPromoPrice" step="10000" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">Nhãn ưu đãi</label><input type="text" name="promo_tag" id="qsPromoTag" placeholder="VD: -15% HÈ" class="w-full px-3 py-2 border rounded-lg" /></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Từ ngày</label><input type="date" name="promo_start_date" id="qsStart" min="<?= date('Y-m-d') ?>" onchange="syncRangeModalDates('qsStart', 'qsEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Đến ngày</label><input type="date" name="promo_end_date" id="qsEnd" min="<?= date('Y-m-d') ?>" onchange="syncRangeModalDates('qsStart', 'qsEnd')" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div class="flex justify-between items-center pt-1">
      <button type="button" onclick="clearQuickSrvPromo()" class="text-[11px] font-semibold text-rose-700 underline">Xóa khuyến mãi (về giá gốc)</button>
      <div class="flex gap-2">
        <button type="button" onclick="this.closest('#quickSrvModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button>
        <button type="submit" class="px-5 py-2 bg-[#C9A45C] text-[#0E1D33] font-bold rounded-lg">Áp dụng ngay</button>
      </div>
    </div>
  </form>
</div>

<div id="newStaffModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <form method="POST" action="/admin/staff" class="bg-white rounded-2xl max-w-md w-full p-5 space-y-3 text-xs border border-[#E6DFD1]">
    <div class="flex justify-between items-center"><h3 class="font-serif text-xl font-semibold">Tạo tài khoản nhân sự</h3><button type="button" onclick="this.closest('#newStaffModal').classList.add('hidden')">✕</button></div>
    <div><label class="block font-semibold mb-1">Họ và tên *</label><input type="text" name="full_name" required class="w-full px-3 py-2 border rounded-lg" /></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Email *</label><input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg" /></div>
      <div><label class="block font-semibold mb-1">Số điện thoại *</label><input type="tel" name="phone" required class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="block font-semibold mb-1">Vai trò</label><select name="role" class="w-full px-3 py-2 border rounded-lg bg-white"><option value="receptionist">Lễ tân / Bếp & DV</option><option value="admin">Quản trị viên</option></select></div>
      <div><label class="block font-semibold mb-1">Mật khẩu *</label><input type="password" name="password" required minlength="6" value="123456" class="w-full px-3 py-2 border rounded-lg" /></div>
    </div>
    <div><label class="block font-semibold mb-1">Chức danh / Bộ phận</label><input type="text" name="membership_tier" placeholder="VD: Nhân viên Lễ tân" class="w-full px-3 py-2 border rounded-lg" /></div>
    <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('#newStaffModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg">Hủy</button><button type="submit" class="px-5 py-2 bg-[#0E1D33] text-white font-bold rounded-lg">Tạo nhân sự</button></div>
  </form>
</div>

<div id="bkDetailModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-2xl w-full overflow-hidden border border-[#E6DFD1] text-xs">
    <div class="bg-[#0E1D33] text-white px-5 py-4 flex justify-between items-center">
      <div><div class="text-[10px] uppercase tracking-widest text-[#E2C27D]">CHI TIẾT ĐẶT PHÒNG & HÓA ĐƠN</div><h3 id="bkDetTitle" class="font-serif text-xl font-semibold"></h3></div>
      <button type="button" onclick="document.getElementById('bkDetailModal').classList.add('hidden')" class="text-slate-300 hover:text-white text-lg">✕</button>
    </div>
    <div id="bkDetBody" class="p-5 space-y-4 max-h-[75vh] overflow-y-auto"></div>
  </div>
</div>

<div id="custHistModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-xl w-full overflow-hidden border border-[#E6DFD1] text-xs">
    <div class="bg-[#0E1D33] text-white px-5 py-4 flex justify-between items-center">
      <h3 class="font-serif text-xl font-semibold">Lịch sử đặt phòng: <span id="chName"></span></h3>
      <button type="button" onclick="document.getElementById('custHistModal').classList.add('hidden')" class="text-slate-300 hover:text-white text-lg">✕</button>
    </div>
    <div id="chBody" class="p-5 space-y-2 max-h-[70vh] overflow-y-auto"></div>
  </div>
</div>

<script>
const ADMIN_TODAY = '<?= date('Y-m-d') ?>';
const ADMIN_PLUS3 = '<?= date('Y-m-d', strtotime('+3 days')) ?>';
const fmtVnd = (n) => Math.round(Number(n) || 0).toLocaleString('vi-VN') + ' đ';
const escH = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

function showAdminInlineAlert(msg) {
  let box = document.getElementById('adminClientDateErr');
  if (!box) {
    box = document.createElement('div');
    box.id = 'adminClientDateErr';
    box.className = 'fixed top-4 right-4 z-[9999] max-w-md bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-xl shadow-lg text-xs font-semibold flex items-center gap-3';
    document.body.appendChild(box);
  }
  box.innerHTML = `<span>${escH(msg)}</span><button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-900 font-bold">✕</button>`;
  clearTimeout(window.__admErrTimer);
  window.__admErrTimer = setTimeout(() => box?.remove(), 5000);
}

function syncRangeModalDates(startId, endId) {
  const sEl = document.getElementById(startId);
  const eEl = document.getElementById(endId);
  if (!sEl || !eEl) return;
  sEl.min = ADMIN_TODAY;
  if (sEl.value && sEl.value < ADMIN_TODAY && sEl.value !== (sEl.dataset.initial || '')) {
    sEl.value = ADMIN_TODAY;
  }
  const minEnd = (sEl.value && sEl.value >= ADMIN_TODAY) ? sEl.value : ADMIN_TODAY;
  eEl.min = minEnd;
  if (eEl.value && sEl.value && eEl.value < sEl.value) {
    eEl.value = sEl.value;
  }
}

function syncCatPromoDates(inp) {
  const form = inp?.closest('form');
  if (!form) return;
  const sEl = form.querySelector('input[name="promo_start_date"]');
  const eEl = form.querySelector('input[name="promo_end_date"]');
  if (!sEl || !eEl) return;
  sEl.min = ADMIN_TODAY;
  if (sEl.value && sEl.value < ADMIN_TODAY && sEl.value !== (sEl.dataset.initial || '')) {
    sEl.value = ADMIN_TODAY;
  }
  const minEnd = (sEl.value && sEl.value >= ADMIN_TODAY) ? sEl.value : ADMIN_TODAY;
  eEl.min = minEnd;
  if (eEl.value && sEl.value && eEl.value < sEl.value) {
    eEl.value = sEl.value;
  }
}

function validateCatPromoForm(form) {
  const typeSel = form.querySelector('select[name="promo_discount_type"]');
  const sEl = form.querySelector('input[name="promo_start_date"]');
  const eEl = form.querySelector('input[name="promo_end_date"]');
  if (!sEl || !eEl) return true;
  if (typeSel && typeSel.value === 'none') {
    sEl.value = '';
    eEl.value = '';
    return true;
  }
  const s = sEl.value || '';
  const e = eEl.value || '';
  if (s && s < ADMIN_TODAY && s !== (sEl.dataset.initial || '')) {
    showAdminInlineAlert('Ngày bắt đầu khuyến mãi không được là ngày đã qua.');
    return false;
  }
  if (e && e < ADMIN_TODAY && e !== (eEl.dataset.initial || '')) {
    showAdminInlineAlert('Ngày kết thúc khuyến mãi không được là ngày đã qua.');
    return false;
  }
  if (s && e && e < s) {
    showAdminInlineAlert('Ngày kết thúc khuyến mãi phải từ ngày bắt đầu trở đi.');
    return false;
  }
  return true;
}

function validateRangeForm(form, startId, endId, isOptional) {
  const sEl = document.getElementById(startId);
  const eEl = document.getElementById(endId);
  if (!sEl || !eEl) return true;
  const s = sEl.value || '';
  const e = eEl.value || '';
  if (!isOptional && (!s || !e)) {
    showAdminInlineAlert('Vui lòng chọn đầy đủ ngày bắt đầu và ngày kết thúc.');
    return false;
  }
  if (s && s < ADMIN_TODAY && s !== (sEl.dataset.initial || '')) {
    showAdminInlineAlert('Ngày bắt đầu áp dụng không được là ngày đã qua.');
    return false;
  }
  if (e && e < ADMIN_TODAY && e !== (eEl.dataset.initial || '')) {
    showAdminInlineAlert('Ngày kết thúc áp dụng không được là ngày đã qua.');
    return false;
  }
  if (s && e && e < s) {
    showAdminInlineAlert('Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.');
    return false;
  }
  return true;
}

function switchBreakdown(key) {
  ['cat', 'src', 'pkg'].forEach(k => {
    const p = document.getElementById('bdPanel' + k.charAt(0).toUpperCase() + k.slice(1));
    const b = document.getElementById('bdBtn' + k.charAt(0).toUpperCase() + k.slice(1));
    if (p) p.classList.toggle('hidden', k !== key);
    if (b) b.className = k === key ? 'px-3.5 py-1.5 rounded-lg bg-[#0E1D33] text-white font-semibold' : 'px-3.5 py-1.5 rounded-lg text-slate-600 font-semibold';
  });
}

function toggleActionMenu(id) {
  const el = document.getElementById('catMenu' + id);
  if (el) el.classList.toggle('hidden');
}

function openQuickRateModal(catId, name, price) {
  document.getElementById('quickRateForm').action = '/admin/categories/' + catId + '/quick-rate';
  document.getElementById('qrCatName').textContent = name;
  document.getElementById('qrPriceInp').value = price || '';
  const qs = document.getElementById('qrStart');
  const qe = document.getElementById('qrEnd');
  if (qs) { qs.min = ADMIN_TODAY; qs.value = ADMIN_TODAY; qs.dataset.initial = ''; }
  if (qe) { qe.min = ADMIN_TODAY; qe.value = ADMIN_PLUS3; qe.dataset.initial = ''; }
  document.getElementById('quickRateModal').classList.remove('hidden');
}

function openAddRoomForCat(catId) {
  document.getElementById('addRoomCatSel').value = String(catId);
  document.getElementById('addRoomModal').classList.remove('hidden');
}

function openLockRoomModal(roomId, roomNum) {
  document.getElementById('lockRoomForm').action = '/admin/rooms/' + roomId + '/lock';
  document.getElementById('lrNum').textContent = roomNum;
  const ls = document.getElementById('lrStart');
  const le = document.getElementById('lrEnd');
  if (ls) { ls.min = ADMIN_TODAY; ls.value = ADMIN_TODAY; ls.dataset.initial = ''; }
  if (le) { le.min = ADMIN_TODAY; le.value = ADMIN_PLUS3; le.dataset.initial = ''; }
  document.getElementById('lockRoomModal').classList.remove('hidden');
}

function openLockUserModal(userId, name, returnTab) {
  document.getElementById('lockUserForm').action = '/admin/users/' + userId + '/toggle';
  document.getElementById('luName').textContent = name;
  document.getElementById('luReturnTab').value = returnTab;
  document.getElementById('lockUserModal').classList.remove('hidden');
}

function openServiceModal(srv) {
  const f = document.getElementById('serviceForm');
  const smS = document.getElementById('smStart');
  const smE = document.getElementById('smEnd');
  if (!srv) {
    document.getElementById('srvModalTitle').textContent = 'Thêm dịch vụ mới';
    f.action = '/admin/services';
    document.getElementById('smCode').readOnly = false;
    document.getElementById('smCode').value = '';
    document.getElementById('smName').value = '';
    document.getElementById('smPrice').value = '';
    document.getElementById('smPromoPrice').value = '';
    document.getElementById('smPromoTag').value = '';
    smS.value = '';
    smE.value = '';
    smS.dataset.initial = '';
    smE.dataset.initial = '';
    document.getElementById('smDesc').value = '';
  } else {
    document.getElementById('srvModalTitle').textContent = 'Sửa dịch vụ: ' + srv.name;
    f.action = '/admin/services/' + srv.id + '/update';
    document.getElementById('smCode').readOnly = true;
    document.getElementById('smCode').value = srv.code || '';
    document.getElementById('smCat').value = srv.category || 'dining_seafood';
    document.getElementById('smName').value = srv.name || '';
    document.getElementById('smUnit').value = srv.unit_label || '/phần';
    document.getElementById('smPrice').value = srv.price || '';
    document.getElementById('smPromoPrice').value = srv.promo_price || '';
    document.getElementById('smPromoTag').value = srv.promo_tag || '';
    smS.value = srv.promo_start_date || '';
    smE.value = srv.promo_end_date || '';
    smS.dataset.initial = srv.promo_start_date || '';
    smE.dataset.initial = srv.promo_end_date || '';
    document.getElementById('smDesc').value = srv.description || '';
  }
  syncRangeModalDates('smStart', 'smEnd');
  document.getElementById('serviceModal').classList.remove('hidden');
}

function openBookingDetailModal(bk) {
  document.getElementById('bkDetTitle').textContent = bk.booking_code + ' · ' + bk.guest_name;
  const inv = bk.invoice || null;
  const roomSub = inv ? Number(inv.room_charges || 0) : Number(bk.room_subtotal || 0);
  const srvSub = inv ? Number(inv.service_charges || 0) : (Number(bk.addon_subtotal || 0) + Number(bk.completed_services_total || 0));
  const extFee = inv ? Number(inv.extension_fee || 0) : Number(bk.extension_fee || 0);
  const surcharges = inv ? Number(inv.surcharges || 0) : 0;
  const disc = inv ? Number(inv.discount_amount || 0) : Number(bk.discount_amount || 0);
  const taxAmt = inv ? Number(inv.tax_amount || 0) : Number(bk.tax_amount || 0);
  const total = inv ? Number(inv.total_amount) : (Number(bk.total_estimated) + Number(bk.completed_services_total || 0));
  const paid = inv ? Number(inv.paid_amount || 0) : Number(bk.total_paid || 0);
  const remain = Math.max(0, total - paid);
  document.getElementById('bkDetBody').innerHTML = `
    <div class="grid grid-cols-2 gap-4 bg-[#FAF7F0] p-4 rounded-xl border border-[#EFE9DC]">
      <div><div>Khách hàng: <strong>${escH(bk.guest_name)}</strong></div><div>SĐT: <strong>${escH(bk.guest_phone)}</strong></div><div>CCCD (Che ẩn): <strong class="font-mono">${escH(bk.masked_id_number || 'Chưa cập nhật')}</strong></div></div>
      <div><div>Hạng phòng: <strong>${escH(bk.category_name)}</strong></div><div>Phòng gán: <strong>${bk.assigned_room_number ? 'P.' + escH(bk.assigned_room_number) : 'Chưa gán'}</strong></div><div>Ngày ở: <strong>${escH(bk.check_in_date)} → ${escH(bk.check_out_date)}</strong></div></div>
    </div>
    <div class="border border-[#E6DFD1] rounded-xl p-4 space-y-1.5">
      <div class="font-bold uppercase text-[10px] text-[#A8843E]">TÓM TẮT THANH TOÁN</div>
      <div class="flex justify-between"><span>Tiền phòng & gói:</span><span class="tabular-nums">${fmtVnd(roomSub)}</span></div>
      <div class="flex justify-between"><span>Dịch vụ đã sử dụng / đặt kèm:</span><span class="tabular-nums">${fmtVnd(srvSub)}</span></div>
      ${extFee > 0 ? `<div class="flex justify-between"><span>Phí gia hạn / Trả muộn:</span><span class="tabular-nums">${fmtVnd(extFee)}</span></div>` : ''}
      ${surcharges > 0 ? `<div class="flex justify-between"><span>Phụ phí phát sinh:</span><span class="tabular-nums">${fmtVnd(surcharges)}</span></div>` : ''}
      ${disc > 0 ? `<div class="flex justify-between text-emerald-700"><span>Giảm giá Voucher:</span><span class="tabular-nums">-${fmtVnd(disc)}</span></div>` : ''}
      <div class="flex justify-between"><span>Thuế phí:</span><span class="tabular-nums">${fmtVnd(taxAmt)}</span></div>
      <div class="flex justify-between font-bold border-t pt-2"><span>Tổng hóa đơn:</span><span class="tabular-nums">${fmtVnd(total)}</span></div>
      <div class="flex justify-between text-emerald-700 font-bold"><span>Đã thu:</span><span class="tabular-nums">${fmtVnd(paid)}</span></div>
      <div class="flex justify-between text-rose-700 font-bold"><span>Còn lại:</span><span class="tabular-nums">${fmtVnd(remain)}</span></div>
    </div>
  `;
  document.getElementById('bkDetailModal').classList.remove('hidden');
}

function openCustHistoryModal(name, list) {
  document.getElementById('chName').textContent = name;
  document.getElementById('chBody').innerHTML = (!list || !list.length)
    ? '<p class="text-slate-500">Khách hàng chưa có đơn đặt phòng nào.</p>'
    : list.map(b => `
      <div class="p-3 border rounded-xl bg-[#FAF7F0] flex justify-between items-center">
        <div><strong class="font-mono">${escH(b.booking_code)}</strong> · ${escH(b.category_name)}<div class="text-slate-500">${escH(b.check_in_date)} → ${escH(b.check_out_date)} · CCCD: ${escH(b.masked_id_number || '—')}</div></div>
        <div class="text-right font-bold tabular-nums">${fmtVnd(b.total_estimated)}</div>
      </div>
    `).join('');
  document.getElementById('custHistModal').classList.remove('hidden');
}

function openQuickSrvModal(srv) {
  document.getElementById('quickSrvForm').action = '/admin/services/' + srv.id + '/update';
  document.getElementById('qsNameTitle').textContent = srv.name || '';
  document.getElementById('qsCode').value = srv.code || '';
  document.getElementById('qsName').value = srv.name || '';
  document.getElementById('qsCat').value = srv.category || 'dining_seafood';
  document.getElementById('qsUnit').value = srv.unit_label || '/phần';
  document.getElementById('qsDesc').value = srv.description || '';
  document.getElementById('qsPrice').value = srv.price || '';
  document.getElementById('qsPromoPrice').value = srv.promo_price || '';
  document.getElementById('qsPromoTag').value = srv.promo_tag || '';
  const qs = document.getElementById('qsStart');
  const qe = document.getElementById('qsEnd');
  qs.value = srv.promo_start_date || '';
  qe.value = srv.promo_end_date || '';
  qs.dataset.initial = srv.promo_start_date || '';
  qe.dataset.initial = srv.promo_end_date || '';
  syncRangeModalDates('qsStart', 'qsEnd');
  document.getElementById('quickSrvModal').classList.remove('hidden');
}

function clearQuickSrvPromo() {
  document.getElementById('qsPromoPrice').value = '';
  document.getElementById('qsPromoTag').value = '';
  document.getElementById('qsStart').value = '';
  document.getElementById('qsEnd').value = '';
  syncRangeModalDates('qsStart', 'qsEnd');
}

function showDayBookings(idx) {
  const pts = window.__anPointsData || [];
  const pt = pts[idx];
  const box = document.getElementById('dayPerfDetailBox');
  if (!pt || !box) return;
  const rows = pt.day_bookings || [];
  box.classList.remove('hidden');
  box.innerHTML = `
    <div class="flex justify-between items-center">
      <strong class="text-[#0E1D33]">Chi tiết đóng góp ngày ${escH(pt.date)} (${rows.length} đơn · Tổng ${fmtVnd(pt.total_revenue)})</strong>
      <button type="button" onclick="document.getElementById('dayPerfDetailBox').classList.add('hidden')" class="text-slate-500 hover:text-slate-800">Đóng</button>
    </div>
    ${rows.length === 0 ? '<p class="text-slate-500">Không có đơn phát sinh trong ngày này.</p>' : rows.map(r => `
      <div class="flex justify-between items-center p-2 bg-white rounded-lg border border-[#EFE9DC]">
        <div><strong class="font-mono">${escH(r.booking_code)}</strong> · ${escH(r.guest_name)} (${escH(r.category)})</div>
        <div class="font-bold tabular-nums text-[#A8843E]">${fmtVnd(r.amount)}</div>
      </div>
    `).join('')}
  `;
}

function filterCategories(q) {
  const kw = q.trim().toLowerCase();
  document.querySelectorAll('.cat-card').forEach(el => {
    el.classList.toggle('hidden', kw !== '' && !el.dataset.search.includes(kw));
  });
}
function filterServicesTable() {
  const kw = (document.getElementById('srvSearchInp')?.value || '').trim().toLowerCase();
  const cat = (document.getElementById('srvCatFilter')?.value || '').trim();
  document.querySelectorAll('.srv-row').forEach(el => {
    const matchKw = kw === '' || (el.dataset.search || '').includes(kw);
    const matchCat = cat === '' || (el.dataset.cat || '') === cat;
    el.classList.toggle('hidden', !(matchKw && matchCat));
  });
}
function filterBookingsTable(q) {
  const kw = q.trim().toLowerCase();
  document.querySelectorAll('.bk-row').forEach(el => {
    el.classList.toggle('hidden', kw !== '' && !el.dataset.search.includes(kw));
  });
}
function filterPendingTransfers(q) {
  const kw = q.trim().toLowerCase();
  document.querySelectorAll('.ptx-row').forEach(el => {
    el.classList.toggle('hidden', kw !== '' && !el.dataset.search.includes(kw));
  });
}
function filterReceivables(q) {
  const kw = q.trim().toLowerCase();
  document.querySelectorAll('.rec-row').forEach(el => {
    el.classList.toggle('hidden', kw !== '' && !el.dataset.search.includes(kw));
  });
}
function filterCustomersTable(q) {
  const kw = q.trim().toLowerCase();
  document.querySelectorAll('.cust-row').forEach(el => {
    el.classList.toggle('hidden', kw !== '' && !el.dataset.search.includes(kw));
  });
}
function filterAuditRows() {
  const kw = (document.getElementById('auditSearchInp')?.value || '').trim().toLowerCase();
  const role = (document.getElementById('auditRoleFilter')?.value || '').trim().toLowerCase();
  const actor = (document.getElementById('auditActorFilter')?.value || '').trim().toLowerCase();
  document.querySelectorAll('.audit-row').forEach(el => {
    const matchKw = kw === '' || (el.dataset.search || '').includes(kw);
    const matchRole = role === '' || (el.dataset.role || '') === role;
    const matchActor = actor === '' || (el.dataset.actor || '') === actor;
    el.classList.toggle('hidden', !(matchKw && matchRole && matchActor));
  });
}
</script>
