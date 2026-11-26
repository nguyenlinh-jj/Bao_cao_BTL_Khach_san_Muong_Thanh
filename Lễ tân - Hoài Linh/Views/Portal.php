<?php
$isLookup = ($mode ?? '') === 'lookup';
$activeTab = $activeTab ?? 'bookings';
$unreadCount = (int) ($unreadCount ?? 0);
$minChangeDays = (int) ($minChangeDays ?? 3);

$statusBadgeMap = [
    'pending'          => ['label' => 'Chờ xác nhận', 'cls' => 'bg-amber-50 text-amber-800 border-amber-200'],
    'confirmed'        => ['label' => 'Đã xác nhận', 'cls' => 'bg-sky-50 text-sky-800 border-sky-200'],
    'checked_in'       => ['label' => 'Đang lưu trú', 'cls' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
    'checked_out'      => ['label' => 'Đã trả phòng', 'cls' => 'bg-slate-100 text-slate-700 border-slate-300'],
    'cancelled'        => ['label' => 'Đã hủy', 'cls' => 'bg-rose-50 text-rose-700 border-rose-200'],
    'checkin_overdue'  => ['label' => 'Đã quá ngày', 'cls' => 'bg-rose-100 text-rose-800 border-rose-300'],
    'checkout_overdue' => ['label' => 'Quá hạn trả phòng', 'cls' => 'bg-rose-100 text-rose-800 border-rose-300'],
];

function mtPortalStatusMeta(array $b, array $map): array {
    if (!empty($b['overdue_type']) && isset($map[$b['overdue_type']])) {
        return $map[$b['overdue_type']];
    }
    return $map[$b['status']] ?? ['label' => $b['status'], 'cls' => 'bg-slate-100 text-slate-700 border-slate-200'];
}

$overdueBookings = array_values(array_filter($bookings, fn($b) => !empty($b['is_overdue'])));
$receptionCancelProposals = array_values(array_filter(
    $bookings,
    fn($b) => ($b['change_request_status'] ?? 'none') === 'reception_cancel_pending' && in_array($b['status'], ['pending', 'confirmed'], true)
));
$eligibleServiceBookings = array_values(array_filter(
    $bookings,
    fn($b) => $b['status'] === 'checked_in' || ($b['status'] === 'confirmed' && empty($b['is_overdue']))
));
$bookingsWithServices = array_values(array_filter($bookings, fn($b) => !empty($b['service_orders'])));

$confirmedSvcSpend = 0.0;
$pendingSvcOrdersCount = 0;
foreach ($bookings as $b) {
    $confirmedSvcSpend += (float) ($b['completed_services_total'] ?? 0);
    foreach ($b['service_orders'] ?? [] as $so) {
        if ($so['status'] === 'pending') $pendingSvcOrdersCount++;
    }
}

$serviceGroupLabels = [
    'spa'              => 'SPA & TRỊ LIỆU THƯ GIÃN',
    'dining_other'     => 'ẨM THỰC & THỨC UỐNG',
    'dining_set'       => 'ẨM THỰC & THỨC UỐNG',
    'dining_seafood'   => 'ẨM THỰC & THỨC UỐNG',
    'dining_appetizer' => 'ẨM THỰC & THỨC UỐNG',
    'dining_viet'      => 'ẨM THỰC & THỨC UỐNG',
    'dining_drink'     => 'ẨM THỰC & THỨC UỐNG',
    'addon'            => 'TIỆN ÍCH PHÒNG',
    'prep_option'      => 'TIỆN ÍCH PHÒNG',
    'combo'            => 'TRẢI NGHIỆM & COMBO',
    'experience'       => 'TRẢI NGHIỆM & COMBO',
];

$bookingJsonMap = [];
foreach ($bookings as $b) {
    $bookingJsonMap[(int) $b['id']] = $b;
}
?>

<style>
@media print {
  header, footer, nav, #portalSidebar, .no-print { display: none !important; }
  #invoiceModalBackdrop { position: static !important; background: transparent !important; padding: 0 !important; }
  #invoiceModalCard { box-shadow: none !important; border: none !important; max-width: 100% !important; }
}
</style>

<div class="max-w-[1280px] mx-auto px-4 lg:px-6 py-8">
  <?php if ($isLookup): ?>
    <div class="space-y-6">
      <div>
        <p class="text-[11px] font-bold tracking-[0.2em] text-[#A8843E] uppercase mb-1">TRA CỨU ĐẶT PHÒNG</p>
        <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Tra Cứu Đặt Phòng & Hóa Đơn</h1>
      </div>

      <form method="GET" action="/lookup" class="bg-white border border-[#E6DFD1] rounded-xl p-5 grid grid-cols-1 md:grid-cols-3 gap-4 items-end text-xs">
        <div><label class="block font-semibold mb-1">Mã đặt phòng</label><input type="text" name="code" required value="<?= htmlspecialchars($codeQuery ?? '') ?>" placeholder="MT-PQ-261102" class="w-full px-3 py-2 border rounded-lg font-mono uppercase" /></div>
        <div><label class="block font-semibold mb-1">Số điện thoại</label><input type="tel" name="phone" required value="<?= htmlspecialchars($phoneQuery ?? '') ?>" placeholder="0909123456" class="w-full px-3 py-2 border rounded-lg" /></div>
        <button type="submit" class="py-2.5 bg-[#0E1D33] text-white font-bold uppercase rounded-lg">Tra Cứu Đơn</button>
      </form>

      <?php if (empty($bookings) && !empty($codeQuery)): ?>
        <div class="bg-white border border-[#E6DFD1] rounded-xl p-8 text-center text-xs text-slate-500">
          Không tìm thấy đặt phòng khớp mã <strong><?= htmlspecialchars($codeQuery) ?></strong> và số điện thoại <strong><?= htmlspecialchars($phoneQuery ?? '') ?></strong>.
        </div>
      <?php endif; ?>

      <?php foreach ($bookings as $b): $st = mtPortalStatusMeta($b, $statusBadgeMap);
        $bPayload = !empty($b['change_request_payload']) ? (json_decode((string) $b['change_request_payload'], true) ?: []) : [];
      ?>
        <div class="bg-white border border-[#E6DFD1] rounded-xl p-6 space-y-4">
          <div class="flex flex-wrap justify-between items-center gap-4">
            <div class="space-y-1 text-xs">
              <div class="flex items-center gap-2">
                <span class="font-mono text-sm font-bold text-[#0E1D33]"><?= htmlspecialchars($b['booking_code']) ?></span>
                <span class="px-2 py-0.5 text-[11px] font-semibold rounded border <?= $st['cls'] ?>"><?= htmlspecialchars($st['label']) ?></span>
                <?php if (!empty($b['assigned_room_number'])): ?>
                  <span class="px-2 py-0.5 text-[11px] font-bold bg-[#0E1D33] text-white rounded">Phòng <?= htmlspecialchars($b['assigned_room_number']) ?></span>
                <?php endif; ?>
              </div>
              <div class="font-serif text-2xl font-semibold text-[#0E1D33]"><?= htmlspecialchars($b['category_name']) ?></div>
              <div class="text-slate-500 tabular-nums"><?= htmlspecialchars($b['check_in_date']) ?> → <?= htmlspecialchars($b['check_out_date']) ?> (<?= (int)$b['nights'] ?> đêm)</div>
            </div>
            <div class="flex items-center gap-3">
              <button type="button" onclick="mtOpenInvoiceModal(<?= (int)$b['id'] ?>)" class="px-4 py-2 bg-[#0E1D33] text-white text-xs font-bold rounded-lg">Xem Hóa Đơn & Chi Tiết</button>
            </div>
          </div>

          <?php if (($b['status'] ?? '') === 'cancelled' && !empty($b['refund'])):
            $rf = $b['refund'];
            $rfSt = (string) ($rf['status'] ?? 'pending');
            $rfMap = [
              'pending'   => ['Chờ duyệt hoàn tiền', 'bg-amber-50 border-amber-200 text-amber-900'],
              'approved'  => ['Đã duyệt · Chờ giải ngân', 'bg-sky-50 border-sky-200 text-sky-900'],
              'rejected'  => ['Từ chối hoàn tiền', 'bg-rose-50 border-rose-200 text-rose-900'],
              'processed' => ['Đã hoàn tiền thành công', 'bg-emerald-50 border-emerald-200 text-emerald-900'],
            ];
            [$rfLbl, $rfCls] = $rfMap[$rfSt] ?? [$rfSt, 'bg-slate-50 border-slate-200 text-slate-700'];
          ?>
            <div class="p-3.5 rounded-xl border text-xs space-y-1 <?= $rfCls ?>">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>Hồ sơ hoàn tiền <span class="font-mono"><?= htmlspecialchars((string) $rf['refund_code']) ?></span>: <?= htmlspecialchars($rfLbl) ?></strong>
                <strong class="tabular-nums">Số tiền hoàn: <?= number_format((float) $rf['refund_amount'], 0, ',', '.') ?> đ</strong>
              </div>
              <div class="text-[11px] opacity-90">
                Đã thu hợp lệ: <?= number_format((float) $rf['paid_verified_amount'], 0, ',', '.') ?> đ ·
                Phí giữ lại: <?= number_format((float) $rf['retained_fee'], 0, ',', '.') ?> đ
                <?php if (!empty($rf['reference_no'])): ?> · Mã tham chiếu hoàn: <span class="font-mono font-bold"><?= htmlspecialchars((string) $rf['reference_no']) ?></span><?php endif; ?>
              </div>
              <?php if (!empty($rf['decision_note'])): ?>
                <div class="text-[11px]">Ghi chú xử lý: <?= htmlspecialchars((string) $rf['decision_note']) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if (($b['change_request_status'] ?? 'none') === 'reception_cancel_pending'): ?>
            <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 text-xs space-y-2.5">
              <div class="font-bold uppercase text-amber-900">Lễ tân đề nghị hủy đơn — Vui lòng xác nhận đồng ý hay giữ lại đơn</div>
              <div class="text-slate-700">
                <strong>Lý do hủy từ Lễ tân:</strong> <?= htmlspecialchars((string) ($bPayload['reason'] ?? 'Lễ tân đề nghị hủy đặt phòng')) ?>
                <?php if (!empty($bPayload['proposed_by_name'])): ?>
                  <span class="text-slate-500"> (Nhân viên: <?= htmlspecialchars((string) $bPayload['proposed_by_name']) ?>)</span>
                <?php endif; ?>
              </div>
              <?php if (!empty($bPayload['policy_summary'])): ?>
                <div class="text-[11px] text-slate-600"><?= htmlspecialchars((string) $bPayload['policy_summary']) ?></div>
              <?php endif; ?>
              <form method="POST" action="/bookings/<?= (int) $b['id'] ?>/respond-cancel" class="space-y-2">
                <input type="hidden" name="lookup_code" value="<?= htmlspecialchars($codeQuery ?? $b['booking_code']) ?>" />
                <input type="hidden" name="verify_phone" value="<?= htmlspecialchars($phoneQuery ?? $b['guest_phone']) ?>" />
                <input type="text" name="customer_note" placeholder="Ghi chú phản hồi gửi Lễ tân (không bắt buộc)..." class="w-full px-3 py-1.5 border border-amber-200 rounded-lg bg-white text-xs" />
                <div class="flex flex-wrap gap-2">
                  <button type="submit" name="decision" value="accept" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold">Xác nhận đồng ý hủy đơn</button>
                  <button type="submit" name="decision" value="decline" class="px-4 py-2 bg-white border border-[#0E1D33] text-[#0E1D33] rounded-lg font-bold">Không đồng ý · Giữ lại đơn</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <!-- CỔNG THÔNG TIN KHÁCH HÀNG: SIDEBAR 260PX + NỘI DUNG 5 TAB -->
    <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6 items-start">
      <!-- SIDEBAR TRÁI -->
      <aside id="portalSidebar" class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-5 lg:sticky lg:top-24">
        <div class="border-b border-[#EFE9DC] pb-4">
          <div class="text-[10px] font-bold tracking-[0.18em] text-[#A8843E] uppercase">TÀI KHOẢN HỘI VIÊN</div>
          <div class="font-serif text-2xl font-semibold text-[#0E1D33] mt-1"><?= htmlspecialchars($authUser['full_name']) ?></div>
          <div class="text-xs text-slate-500 mt-0.5 truncate"><?= htmlspecialchars($authUser['email']) ?></div>
        </div>

        <nav class="space-y-1.5 text-xs font-semibold">
          <a href="/my-bookings?tab=bookings" class="flex items-center justify-between px-3.5 py-2.5 rounded-lg <?= $activeTab === 'bookings' ? 'bg-[#0E1D33] text-white' : 'text-slate-700 hover:bg-[#FAF7F0]' ?>">
            <span>Đặt phòng của tôi</span>
            <span class="text-[10px] opacity-80"><?= count($bookings) ?></span>
          </a>
          <a href="/my-bookings?tab=services" class="flex items-center justify-between px-3.5 py-2.5 rounded-lg <?= $activeTab === 'services' ? 'bg-[#0E1D33] text-white' : 'text-slate-700 hover:bg-[#FAF7F0]' ?>">
            <span>Dịch vụ lưu trú</span>
            <span class="text-[10px] opacity-80"><?= count($bookingsWithServices) ?></span>
          </a>
          <a href="/my-bookings?tab=invoices" class="flex items-center justify-between px-3.5 py-2.5 rounded-lg <?= $activeTab === 'invoices' ? 'bg-[#0E1D33] text-white' : 'text-slate-700 hover:bg-[#FAF7F0]' ?>">
            <span>Hóa đơn</span>
          </a>
          <a href="/my-bookings?tab=notifications" class="flex items-center justify-between px-3.5 py-2.5 rounded-lg <?= $activeTab === 'notifications' ? 'bg-[#0E1D33] text-white' : 'text-slate-700 hover:bg-[#FAF7F0]' ?>">
            <span>Thông báo</span>
            <?php if ($unreadCount > 0): ?>
              <span class="px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-bold"><?= $unreadCount ?></span>
            <?php endif; ?>
          </a>
          <a href="/my-bookings?tab=profile" class="flex items-center justify-between px-3.5 py-2.5 rounded-lg <?= $activeTab === 'profile' ? 'bg-[#0E1D33] text-white' : 'text-slate-700 hover:bg-[#FAF7F0]' ?>">
            <span>Hồ sơ</span>
          </a>
        </nav>

        <div class="border-t border-[#EFE9DC] pt-3">
          <a href="/logout" class="block px-3.5 py-2 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50">Đăng xuất</a>
        </div>
      </aside>

      <!-- NỘI DUNG CHÍNH BÊN PHẢI -->
      <div class="space-y-6 min-w-0">

        <!-- TAB 1: ĐẶT PHÒNG CỦA TÔI -->
        <?php if ($activeTab === 'bookings'): ?>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Đặt phòng của tôi</h1>
              <p class="text-xs text-slate-500 mt-0.5">Theo dõi trạng thái thời gian thực, hóa đơn chi tiết, đổi ngày/hạng phòng hoặc đánh giá sau lưu trú.</p>
            </div>
            <a href="/rooms" class="px-4 py-2 bg-[#C9A45C] text-[#0E1D33] text-xs font-bold uppercase rounded-lg">+ Đặt phòng mới</a>
          </div>

          <?php if (!empty($overdueBookings)): ?>
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-xs text-rose-800 space-y-1">
              <div class="font-bold uppercase">⚠ Cảnh báo thời gian thực: Có <?= count($overdueBookings) ?> đơn đặt phòng đã quá ngày dự kiến</div>
              <div>Các đơn đã quá ngày nhận phòng không thể tự đổi lịch hoặc hủy trực tuyến. Vui lòng liên hệ quầy Lễ tân (1900 1833) để được hỗ trợ xử lý.</div>
            </div>
          <?php endif; ?>

          <?php if (!empty($receptionCancelProposals)): ?>
            <div class="space-y-3">
              <?php foreach ($receptionCancelProposals as $rcp):
                $rcpPayload = !empty($rcp['change_request_payload']) ? (json_decode((string) $rcp['change_request_payload'], true) ?: []) : [];
              ?>
                <div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-5 text-xs space-y-3 shadow-xs">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <span class="text-[10px] font-bold uppercase tracking-widest text-amber-800">THÔNG BÁO XÁC NHẬN HỦY ĐƠN TỪ LỄ TÂN</span>
                      <div class="font-serif text-xl font-semibold text-[#0E1D33]">
                        Đơn <span class="font-mono"><?= htmlspecialchars($rcp['booking_code']) ?></span> · <?= htmlspecialchars($rcp['category_name']) ?> (<?= htmlspecialchars($rcp['check_in_date']) ?> → <?= htmlspecialchars($rcp['check_out_date']) ?>)
                      </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-amber-200/80 text-amber-950 font-bold text-[11px]">Chờ bạn xác nhận</span>
                  </div>
                  <div class="p-3.5 bg-white border border-amber-200 rounded-xl space-y-1">
                    <div>
                      <strong class="text-[#0E1D33]">Lý do Lễ tân đề nghị hủy:</strong>
                      <span class="text-slate-800"> <?= htmlspecialchars((string) ($rcpPayload['reason'] ?? 'Lễ tân đề nghị hủy đặt phòng')) ?></span>
                    </div>
                    <?php if (!empty($rcpPayload['proposed_by_name'])): ?>
                      <div class="text-slate-500">Nhân viên gửi đề nghị: <?= htmlspecialchars((string) $rcpPayload['proposed_by_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($rcpPayload['policy_summary'])): ?>
                      <div class="text-slate-700 pt-1 border-t border-amber-100"><strong>Chính sách cọc &amp; hoàn tiền:</strong> <?= htmlspecialchars((string) $rcpPayload['policy_summary']) ?></div>
                    <?php endif; ?>
                  </div>
                  <form method="POST" action="/bookings/<?= (int) $rcp['id'] ?>/respond-cancel" class="space-y-2.5">
                    <input type="text" name="customer_note" placeholder="Nhập ghi chú phản hồi cho Lễ tân (VD: Tôi đồng ý hủy hoặc Tôi vẫn muốn giữ phòng)..." class="w-full px-3 py-2 border border-amber-200 rounded-lg bg-white text-xs" />
                    <div class="flex flex-wrap gap-2">
                      <button type="submit" name="decision" value="accept" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold">Xác nhận đồng ý hủy đơn</button>
                      <button type="submit" name="decision" value="decline" class="px-4 py-2 bg-white border border-[#0E1D33] text-[#0E1D33] hover:bg-[#FAF7F0] rounded-lg font-bold">Không đồng ý · Giữ lại đơn</button>
                    </div>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-[#EFE9DC] flex flex-wrap items-center justify-between gap-3 bg-[#FCFBF8]">
              <input type="text" id="bookingSearchInp" oninput="mtFilterBookingsTable(this.value)" placeholder="Tìm theo mã đơn, tên hạng phòng, số phòng..." class="w-full sm:w-80 px-3 py-2 text-xs border border-[#E6DFD1] rounded-lg bg-white" />
              <div class="text-[11px] text-slate-500">Chính sách đổi lịch: gửi trước ngày nhận phòng tối thiểu <strong><?= $minChangeDays ?> ngày</strong>.</div>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse" id="myBookingsTable">
                <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[11px] uppercase text-slate-500">
                  <tr>
                    <th class="py-3 px-4">Mã</th>
                    <th class="py-3 px-4">Phòng</th>
                    <th class="py-3 px-4">Ngày ở</th>
                    <th class="py-3 px-4">Trạng thái</th>
                    <th class="py-3 px-4 text-right">Tổng</th>
                    <th class="py-3 px-4 text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php if (empty($bookings)): ?>
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Quý khách chưa có đơn đặt phòng nào.</td></tr>
                  <?php else: ?>
                    <?php foreach ($bookings as $b):
                      $st = mtPortalStatusMeta($b, $statusBadgeMap);
                      $svcAdd = (float) ($b['completed_services_total'] ?? 0);
                      $invGross = !empty($b['invoice']) ? (float) $b['invoice']['total_amount'] : ((float) $b['total_estimated'] + $svcAdd);
                      $canAction = in_array($b['status'], ['pending', 'confirmed'], true) && empty($b['is_overdue']) && $b['change_request_status'] === 'none';
                    ?>
                      <tr class="booking-row hover:bg-[#FCFBF8]" data-search="<?= htmlspecialchars(strtolower($b['booking_code'] . ' ' . $b['category_name'] . ' ' . ($b['assigned_room_number'] ?? ''))) ?>">
                        <td class="py-3.5 px-4 font-mono font-bold text-[#0E1D33] whitespace-nowrap">
                          <?= htmlspecialchars($b['booking_code']) ?>
                        </td>
                        <td class="py-3.5 px-4">
                          <div class="font-semibold text-[#0E1D33]"><?= htmlspecialchars($b['category_name']) ?></div>
                          <?php if (!empty($b['assigned_room_number'])): ?>
                            <div class="text-[11px] text-[#A8843E] font-medium">P.<?= htmlspecialchars($b['assigned_room_number']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 tabular-nums whitespace-nowrap">
                          <?= htmlspecialchars($b['check_in_date']) ?> → <?= htmlspecialchars($b['check_out_date']) ?>
                          <div class="text-[11px] text-slate-400"><?= (int) $b['nights'] ?> đêm</div>
                        </td>
                        <td class="py-3.5 px-4">
                          <span class="inline-block px-2 py-0.5 text-[11px] font-semibold rounded border <?= $st['cls'] ?>"><?= htmlspecialchars($st['label']) ?></span>
                          <?php if ($b['change_request_status'] !== 'none'): ?>
                            <div class="text-[10px] font-semibold mt-1 <?= $b['change_request_status'] === 'reception_cancel_pending' ? 'text-rose-700' : 'text-amber-700' ?>">
                              <?= $b['change_request_status'] === 'date_change_pending'
                                  ? '⏳ Đang chờ duyệt đổi lịch'
                                  : ($b['change_request_status'] === 'reception_cancel_pending'
                                      ? '⚠ Lễ tân đề nghị hủy · Chờ bạn xác nhận'
                                      : '⏳ Đang chờ duyệt hủy') ?>
                            </div>
                          <?php endif; ?>
                          <?php if (($b['status'] ?? '') === 'cancelled' && !empty($b['refund'])):
                            $rf = $b['refund'];
                            $rfSt = (string) ($rf['status'] ?? 'pending');
                            $rfLbl = $rfSt === 'processed' ? 'Đã hoàn tiền' : ($rfSt === 'approved' ? 'Đã duyệt hoàn' : ($rfSt === 'rejected' ? 'Không hoàn' : 'Chờ duyệt hoàn'));
                            $rfTxtCls = $rfSt === 'processed' ? 'text-emerald-700' : ($rfSt === 'approved' ? 'text-sky-700' : ($rfSt === 'rejected' ? 'text-rose-700' : 'text-amber-700'));
                          ?>
                            <div class="text-[10px] font-semibold mt-1 <?= $rfTxtCls ?>">
                              Hoàn tiền <?= htmlspecialchars((string) $rf['refund_code']) ?>: <?= $rfLbl ?> (<?= number_format((float) $rf['refund_amount'], 0, ',', '.') ?> đ)
                            </div>
                          <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 text-right font-bold tabular-nums text-[#0E1D33] whitespace-nowrap">
                          <?= number_format($invGross, 0, ',', '.') ?> đ
                        </td>
                        <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1">
                          <button type="button" onclick="mtOpenInvoiceModal(<?= (int) $b['id'] ?>)" class="px-2.5 py-1 border border-[#E6DFD1] hover:border-[#0E1D33] bg-white rounded text-[11px] font-semibold text-[#0E1D33]">Hóa đơn</button>

                          <?php if ($b['change_request_status'] === 'reception_cancel_pending' && in_array($b['status'], ['pending', 'confirmed'], true)): ?>
                            <button type="button" onclick="mtOpenRespondCancelModal(<?= (int) $b['id'] ?>)" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded text-[11px] font-bold">Xác nhận hủy?</button>
                          <?php endif; ?>

                          <?php if ($canAction): ?>
                            <?php if (!empty($b['can_change_date'])): ?>
                              <button type="button" onclick="mtOpenChangeModal(<?= (int) $b['id'] ?>)" class="px-2.5 py-1 border border-[#E6DFD1] hover:border-[#0E1D33] bg-white rounded text-[11px] font-semibold text-[#0E1D33]">Đổi ngày</button>
                            <?php else: ?>
                              <span title="Phải đổi trước ngày nhận phòng ít nhất <?= $minChangeDays ?> ngày" class="inline-block px-2.5 py-1 border border-slate-200 bg-slate-100 rounded text-[11px] text-slate-400 cursor-not-allowed">Đổi ngày</span>
                            <?php endif; ?>
                            <button type="button" onclick="mtOpenCancelModal(<?= (int) $b['id'] ?>)" class="px-2.5 py-1 border border-rose-200 text-rose-700 hover:bg-rose-50 bg-white rounded text-[11px] font-semibold">Hủy</button>
                          <?php endif; ?>

                          <?php if ($b['status'] === 'checked_out'): ?>
                            <?php if (empty($b['review'])): ?>
                              <button type="button" onclick="mtOpenReviewModal(<?= (int) $b['id'] ?>)" class="px-2.5 py-1 border border-[#C9A45C] bg-[#FAF6EC] text-[#0E1D33] rounded text-[11px] font-bold">Đánh giá</button>
                            <?php else: ?>
                              <span class="inline-block px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[10px] font-semibold">✓ Đã đánh giá</span>
                            <?php endif; ?>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- TAB 2: DỊCH VỤ LƯU TRÚ -->
        <?php if ($activeTab === 'services'): ?>
          <div>
            <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Dịch vụ lưu trú & Gọi món tại phòng</h1>
            <p class="text-xs text-slate-500 mt-0.5">Sử dụng Mã truy cập dịch vụ để đặt ẩm thực, Pearl Spa và tiện ích trực tiếp từ phòng.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-4">
              <div class="text-[10px] font-bold uppercase text-slate-500">Đơn đủ điều kiện đặt dịch vụ</div>
              <div class="font-serif text-3xl font-bold text-[#0E1D33] mt-1 tabular-nums"><?= count($eligibleServiceBookings) ?></div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-4">
              <div class="text-[10px] font-bold uppercase text-slate-500">Chi phí dịch vụ đã xác nhận</div>
              <div class="font-serif text-3xl font-bold text-[#A8843E] mt-1 tabular-nums"><?= number_format($confirmedSvcSpend, 0, ',', '.') ?> đ</div>
            </div>
            <div class="bg-white border border-[#E6DFD1] rounded-xl p-4">
              <div class="text-[10px] font-bold uppercase text-slate-500">Yêu cầu đang chờ xác nhận</div>
              <div class="font-serif text-3xl font-bold text-amber-700 mt-1 tabular-nums"><?= $pendingSvcOrdersCount ?></div>
            </div>
          </div>

          <!-- Gọi dịch vụ tại phòng -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-4">
            <h2 class="font-serif text-2xl font-semibold text-[#0E1D33]">Gọi dịch vụ tại phòng</h2>
            <?php if (empty($eligibleServiceBookings)): ?>
              <div class="p-6 border border-dashed border-[#E6DFD1] rounded-xl text-center text-xs text-slate-500">
                Hiện bạn chưa có đơn đặt phòng đang lưu trú hoặc đã xác nhận sắp tới.
              </div>
            <?php else: ?>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($eligibleServiceBookings as $eb):
                  $accCode = $eb['service_access_code'] ?: ('DV-' . substr((string) $eb['booking_code'], -6));
                ?>
                  <div class="border border-[#E6DFD1] bg-[#FCFBF8] rounded-xl p-4 space-y-3 text-xs">
                    <div class="flex items-start justify-between gap-2">
                      <div>
                        <span class="font-mono font-bold text-sm text-[#0E1D33]"><?= htmlspecialchars($eb['booking_code']) ?></span>
                        <?php if (!empty($eb['assigned_room_number'])): ?>
                          <span class="ml-1.5 px-2 py-0.5 bg-[#0E1D33] text-white rounded text-[10px] font-bold">Phòng <?= htmlspecialchars($eb['assigned_room_number']) ?></span>
                        <?php endif; ?>
                        <div class="font-semibold text-[#0E1D33] mt-1"><?= htmlspecialchars($eb['category_name']) ?></div>
                        <div class="text-slate-500"><?= htmlspecialchars($eb['check_in_date']) ?> → <?= htmlspecialchars($eb['check_out_date']) ?></div>
                      </div>
                      <button type="button" onclick="mtToggleServiceQR(<?= (int) $eb['id'] ?>)" class="px-2.5 py-1 border border-[#E6DFD1] bg-white rounded text-[11px] font-semibold">Mã QR</button>
                    </div>

                    <div class="flex items-center justify-between bg-white border border-[#EFE9DC] rounded-lg px-3 py-2">
                      <span class="text-slate-500">Mã truy cập dịch vụ:</span>
                      <strong class="font-mono text-sm text-[#A8843E]"><?= htmlspecialchars($accCode) ?></strong>
                    </div>

                    <div id="svcQrBox_<?= (int) $eb['id'] ?>" class="hidden bg-white border border-[#EFE9DC] rounded-lg p-3 text-center space-y-1">
                      <svg viewBox="0 0 80 80" class="w-20 h-20 mx-auto text-[#0E1D33]" fill="currentColor">
                        <rect x="4" y="4" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="11" y="11" width="10" height="10"/>
                        <rect x="52" y="4" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="59" y="11" width="10" height="10"/>
                        <rect x="4" y="52" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="11" y="59" width="10" height="10"/>
                        <rect x="35" y="10" width="8" height="8"/><rect x="35" y="26" width="8" height="12"/><rect x="36" y="46" width="14" height="10"/><rect x="54" y="54" width="20" height="20" fill="none" stroke="currentColor" stroke-width="4"/>
                      </svg>
                      <div class="font-mono text-[10px] text-slate-500">Quét mã phòng <?= htmlspecialchars($eb['assigned_room_number'] ?: $accCode) ?></div>
                    </div>

                    <a href="/room-services?booking_id=<?= (int) $eb['id'] ?>" class="block w-full text-center py-2.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] font-bold uppercase rounded-lg">
                      GỌI MÓN & DỊCH VỤ TẠI PHÒNG →
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Yêu cầu dịch vụ đã gửi: STRICTLY 1 UNIFIED CARD PER BOOKING -->
          <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-4">
            <h2 class="font-serif text-2xl font-semibold text-[#0E1D33]">Yêu cầu dịch vụ đã gửi</h2>
            <p class="text-xs text-slate-500">Tất cả món ăn và dịch vụ của từng mã đặt phòng được tổng hợp trong 1 thẻ chi tiết duy nhất.</p>

            <?php if (empty($bookingsWithServices)): ?>
              <div class="p-6 border border-dashed border-[#E6DFD1] rounded-xl text-center text-xs text-slate-500">Bạn chưa gửi yêu cầu dịch vụ nào.</div>
            <?php else: ?>
              <div class="space-y-4">
                <?php foreach ($bookingsWithServices as $bs):
                  $orders = $bs['service_orders'] ?? [];
                  $grossAll = 0.0;
                  $discAll = (float) ($bs['service_discount_total'] ?? 0);
                  $grouped = [];
                  foreach ($orders as $so) {
                    if ($so['status'] !== 'cancelled') {
                      $grossAll += (float) $so['total_price'];
                    }
                    $catKey = $serviceGroupLabels[$so['service_category'] ?? ''] ?? 'DỊCH VỤ KHÁC';
                    $grouped[$catKey][] = $so;
                  }
                  $netAll = max(0.0, $grossAll - $discAll);
                ?>
                  <div class="border border-[#E6DFD1] rounded-xl overflow-hidden text-xs">
                    <div class="bg-[#FAF7F0] px-4 py-3 flex flex-wrap items-center justify-between gap-3 border-b border-[#EFE9DC]">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono font-bold text-sm text-[#0E1D33]"><?= htmlspecialchars($bs['booking_code']) ?></span>
                        <?php if (!empty($bs['assigned_room_number'])): ?>
                          <span class="px-2 py-0.5 bg-[#0E1D33] text-white rounded text-[10px] font-bold">Phòng <?= htmlspecialchars($bs['assigned_room_number']) ?></span>
                        <?php endif; ?>
                        <span class="text-slate-600"><?= count($orders) ?> hạng mục dịch vụ</span>
                      </div>
                      <div class="flex items-center gap-3">
                        <span class="font-bold text-[#A8843E] tabular-nums"><?= number_format($netAll, 0, ',', '.') ?> đ</span>
                        <button type="button" onclick="mtToggleSvcGroup(<?= (int) $bs['id'] ?>)" class="px-2.5 py-1 border border-[#E6DFD1] bg-white rounded text-[11px] font-semibold">Chi tiết ▾</button>
                      </div>
                    </div>

                    <div id="svcGroupBody_<?= (int) $bs['id'] ?>" class="p-4 space-y-4 bg-white">
                      <?php foreach ($grouped as $gTitle => $gItems): ?>
                        <div>
                          <div class="text-[10px] font-bold tracking-wider uppercase text-[#A8843E] mb-1.5"><?= htmlspecialchars($gTitle) ?></div>
                          <div class="border border-[#EFE9DC] rounded-lg overflow-hidden">
                            <table class="w-full text-left text-xs border-collapse">
                              <thead class="bg-[#FCFBF8] border-b border-[#EFE9DC] text-[10px] uppercase text-slate-500">
                                <tr>
                                  <th class="py-2 px-3">Dịch vụ</th>
                                  <th class="py-2 px-3">Thời gian</th>
                                  <th class="py-2 px-3 text-center">SL</th>
                                  <th class="py-2 px-3">Trạng thái</th>
                                  <th class="py-2 px-3 text-right">Thành tiền</th>
                                </tr>
                              </thead>
                              <tbody class="divide-y divide-slate-100">
                                <?php foreach ($gItems as $item): ?>
                                  <tr>
                                    <td class="py-2 px-3">
                                      <div class="font-semibold text-[#0E1D33]"><?= htmlspecialchars($item['service_name_snapshot']) ?></div>
                                      <?php if (!empty($item['guest_note'])): ?>
                                        <div class="text-[10px] text-slate-400">Ghi chú: <?= htmlspecialchars($item['guest_note']) ?></div>
                                      <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-3 text-slate-500"><?= htmlspecialchars($item['requested_time'] ?: '—') ?></td>
                                    <td class="py-2 px-3 text-center tabular-nums"><?= (int) $item['quantity'] ?></td>
                                    <td class="py-2 px-3">
                                      <?php
                                        $sMap = [
                                          'pending'   => ['Chờ xác nhận', 'text-amber-700'],
                                          'accepted'  => ['Đang phục vụ', 'text-sky-700'],
                                          'completed' => ['Hoàn thành', 'text-emerald-700'],
                                          'cancelled' => ['Đã hủy', 'text-rose-600'],
                                        ];
                                        [$sLbl, $sCls] = $sMap[$item['status']] ?? [$item['status'], 'text-slate-600'];
                                      ?>
                                      <span class="font-semibold <?= $sCls ?>"><?= $sLbl ?></span>
                                    </td>
                                    <td class="py-2 px-3 text-right font-semibold tabular-nums"><?= number_format((float) $item['total_price'], 0, ',', '.') ?> đ</td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      <?php endforeach; ?>

                      <?php if ($discAll > 0): ?>
                        <div class="flex justify-between text-emerald-700 font-semibold border-t border-[#EFE9DC] pt-2">
                          <span>Giảm giá Voucher dịch vụ:</span>
                          <span class="tabular-nums">-<?= number_format($discAll, 0, ',', '.') ?> đ</span>
                        </div>
                      <?php endif; ?>
                      <div class="flex justify-between font-bold text-sm text-[#0E1D33] border-t border-[#EFE9DC] pt-2">
                        <span>Tổng dịch vụ thực tính cho đơn <?= htmlspecialchars($bs['booking_code']) ?>:</span>
                        <span class="tabular-nums text-[#A8843E]"><?= number_format($netAll, 0, ',', '.') ?> đ</span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- TAB 3: HÓA ĐƠN -->
        <?php if ($activeTab === 'invoices'): ?>
          <div>
            <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Hóa đơn thanh toán</h1>
            <p class="text-xs text-slate-500 mt-0.5">Xem chi tiết hóa đơn lưu trú, dịch vụ phát sinh, thuế VAT và lịch sử giao dịch cho từng đơn đặt phòng.</p>
          </div>

          <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-[#FAF7F0] border-b border-[#E6DFD1] text-[11px] uppercase text-slate-500">
                <tr>
                  <th class="py-3 px-4">Mã hóa đơn / Booking</th>
                  <th class="py-3 px-4">Phòng</th>
                  <th class="py-3 px-4 text-right">Tổng hóa đơn</th>
                  <th class="py-3 px-4 text-right">Đã thanh toán</th>
                  <th class="py-3 px-4">Trạng thái</th>
                  <th class="py-3 px-4 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($bookings as $b):
                  $inv = $b['invoice'] ?? null;
                  $tot = $inv ? (float) $inv['total_amount'] : ((float) $b['total_estimated'] + (float) ($b['completed_services_total'] ?? 0));
                  $paid = $inv ? (float) $inv['paid_amount'] : (float) $b['total_paid'];
                  $pStat = $inv ? $inv['payment_status'] : ($paid >= $tot ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'));
                  $pCls = $pStat === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($pStat === 'partial' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-slate-100 text-slate-600 border-slate-300');
                  $pLbl = $pStat === 'paid' ? 'Đã tất toán' : ($pStat === 'partial' ? 'Đã đặt cọc' : 'Chưa thanh toán');
                ?>
                  <tr class="hover:bg-[#FCFBF8]">
                    <td class="py-3.5 px-4">
                      <div class="font-mono font-bold text-[#0E1D33]"><?= htmlspecialchars($inv ? $inv['invoice_code'] : $b['booking_code']) ?></div>
                      <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($b['booking_code']) ?></div>
                    </td>
                    <td class="py-3.5 px-4 font-semibold"><?= htmlspecialchars($b['category_name']) ?> <?= !empty($b['assigned_room_number']) ? '(P.' . htmlspecialchars($b['assigned_room_number']) . ')' : '' ?></td>
                    <td class="py-3.5 px-4 text-right font-bold tabular-nums"><?= number_format($tot, 0, ',', '.') ?> đ</td>
                    <td class="py-3.5 px-4 text-right font-semibold text-emerald-700 tabular-nums"><?= number_format($paid, 0, ',', '.') ?> đ</td>
                    <td class="py-3.5 px-4"><span class="px-2 py-0.5 text-[10px] font-semibold rounded border <?= $pCls ?>"><?= $pLbl ?></span></td>
                    <td class="py-3.5 px-4 text-right">
                      <button type="button" onclick="mtOpenInvoiceModal(<?= (int) $b['id'] ?>)" class="px-3 py-1 bg-[#0E1D33] text-white text-[11px] font-semibold rounded">Xem chi tiết</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <!-- TAB 4: THÔNG BÁO -->
        <?php if ($activeTab === 'notifications'): ?>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Thông báo</h1>
              <p class="text-xs text-slate-500 mt-0.5">Cập nhật xác nhận phòng, trạng thái dịch vụ và ưu đãi mới nhất từ Mường Thanh Luxury Phú Quốc.</p>
            </div>
            <?php if ($unreadCount > 0): ?>
              <form method="POST" action="/my-notifications/read">
                <button type="submit" class="px-3.5 py-2 border border-[#E6DFD1] bg-white hover:border-[#0E1D33] text-xs font-semibold rounded-lg">Đánh dấu tất cả đã đọc</button>
              </form>
            <?php endif; ?>
          </div>

          <div class="space-y-3">
            <?php if (empty($notifications)): ?>
              <div class="bg-white border border-[#E6DFD1] rounded-xl p-8 text-center text-xs text-slate-500">Chưa có thông báo nào.</div>
            <?php else: ?>
              <?php foreach ($notifications as $n): ?>
                <div class="bg-white border <?= empty($n['is_read']) ? 'border-[#C9A45C] bg-[#FCFBF8]' : 'border-[#E6DFD1]' ?> rounded-xl p-4 flex flex-wrap justify-between items-start gap-3 text-xs">
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <?php if (empty($n['is_read'])): ?>
                        <span class="w-2 h-2 rounded-full bg-[#C9A45C]"></span>
                      <?php endif; ?>
                      <strong class="text-sm text-[#0E1D33]"><?= htmlspecialchars($n['title']) ?></strong>
                    </div>
                    <p class="text-slate-600 leading-relaxed"><?= htmlspecialchars($n['message']) ?></p>
                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($n['created_at']) ?></div>
                  </div>
                  <?php if (empty($n['is_read'])): ?>
                    <form method="POST" action="/my-notifications/read">
                      <input type="hidden" name="notification_id" value="<?= (int) $n['id'] ?>" />
                      <button type="submit" class="px-2.5 py-1 border border-[#E6DFD1] bg-white rounded text-[11px] font-semibold">Đã đọc</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- TAB 5: HỒ SƠ CÁ NHÂN -->
        <?php if ($activeTab === 'profile'): ?>
          <div>
            <h1 class="font-serif text-3xl font-semibold text-[#0E1D33]">Hồ sơ cá nhân</h1>
            <p class="text-xs text-slate-500 mt-0.5">Thông tin tài khoản Hội viên được đồng bộ trực tiếp vào cơ sở dữ liệu MySQL.</p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <form method="POST" action="/my-profile" class="bg-white border border-[#E6DFD1] rounded-2xl p-6 space-y-4 text-xs">
              <h2 class="font-serif text-2xl font-semibold text-[#0E1D33]">Thông tin Hội viên</h2>
              <div>
                <label class="block font-semibold text-slate-600 mb-1">Họ và tên *</label>
                <input type="text" name="full_name" required value="<?= htmlspecialchars($authUser['full_name']) ?>" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
              </div>
              <div>
                <label class="block font-semibold text-slate-600 mb-1">Email (Không thay đổi)</label>
                <input type="email" readonly value="<?= htmlspecialchars($authUser['email']) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed" />
              </div>
              <div>
                <label class="block font-semibold text-slate-600 mb-1">Số điện thoại *</label>
                <input type="tel" name="phone" required value="<?= htmlspecialchars($authUser['phone']) ?>" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
              </div>
              <div>
                <label class="block font-semibold text-slate-600 mb-1">Địa chỉ liên hệ</label>
                <input type="text" name="address" value="<?= htmlspecialchars($authUser['address'] ?? '') ?>" placeholder="VD: 128 Nguyễn Huệ, Quận 1, TP. HCM" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
              </div>
              <button type="submit" class="px-5 py-2.5 bg-[#0E1D33] text-white font-bold uppercase rounded-lg">Lưu thay đổi</button>
            </form>

            <form method="POST" action="/my-password" class="bg-white border border-[#E6DFD1] rounded-2xl p-6 space-y-4 text-xs">
              <h2 class="font-serif text-2xl font-semibold text-[#0E1D33]">Đổi mật khẩu</h2>
              <div>
                <label class="block font-semibold text-slate-600 mb-1">Mật khẩu hiện tại *</label>
                <input type="text" name="current_password" required autocomplete="off" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
              </div>
              <div>
                <label class="block font-semibold text-slate-600 mb-1">Mật khẩu mới * (Tối thiểu 6 ký tự gồm cả chữ cái và chữ số)</label>
                <input type="text" name="new_password" required minlength="6" autocomplete="off" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
              </div>
              <div>
                <label class="block font-semibold text-slate-600 mb-1">Xác nhận mật khẩu mới *</label>
                <input type="text" name="confirm_password" required minlength="6" autocomplete="off" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
              </div>
              <button type="submit" class="px-5 py-2.5 bg-[#C9A45C] text-[#0E1D33] font-bold uppercase rounded-lg">Cập nhật mật khẩu</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- MODAL 1: HÓA ĐƠN LƯU TRÚ CHI TIẾT + QR + IN HÓA ĐƠN -->
<div id="invoiceModalBackdrop" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4 overflow-y-auto">
  <div id="invoiceModalCard" class="bg-white rounded-2xl max-w-2xl w-full overflow-hidden shadow-2xl border border-[#E6DFD1] my-8">
    <div id="invoiceModalBody" class="p-6 sm:p-8 space-y-5 text-xs"></div>
  </div>
</div>

<!-- MODAL 2: YÊU CẦU ĐỔI NGÀY / HẠNG PHÒNG -->
<div id="changeModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-2xl border border-[#E6DFD1]">
    <div class="px-6 py-4 border-b border-[#EFE9DC] flex items-center justify-between">
      <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">Yêu cầu đổi ngày lưu trú / hạng phòng</h3>
      <button type="button" onclick="mtCloseModal('changeModal')" class="text-slate-400 hover:text-[#0E1D33]">✕</button>
    </div>
    <form id="changeModalForm" method="POST" action="" class="p-6 space-y-3.5 text-xs">
      <input type="hidden" name="type" value="date_change" />
      <div class="grid grid-cols-2 gap-3">
        <div><label class="block font-semibold mb-1">Ngày nhận phòng mới</label><input type="date" id="cm_in" name="new_check_in_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg" /></div>
        <div><label class="block font-semibold mb-1">Ngày trả phòng mới</label><input type="date" id="cm_out" name="new_check_out_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required class="w-full px-3 py-2 border rounded-lg" /></div>
      </div>
      <div>
        <label class="block font-semibold mb-1">Hạng phòng muốn đổi</label>
        <select id="cm_cat" name="new_category_id" onchange="mtValidateChangeModal()" class="w-full px-3 py-2 border rounded-lg bg-white">
          <?php foreach ($categories ?? [] as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (<?= number_format((float) $cat['base_price'], 0, ',', '.') ?> đ/đêm · Tối đa <?= (int) ($cat['max_adults'] ?? 2) ?> NL, <?= (int) ($cat['max_children'] ?? 1) ?> TE)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="cmCapacityWarn" class="hidden p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-medium"></div>
      <div class="p-3 bg-[#FAF6EC] border border-[#E6DFD1] rounded-lg text-[11px] text-slate-700 space-y-1">
        <div class="font-bold text-[#A8843E] uppercase text-[10px]">Chính sách bảo lưu cọc &amp; phản hồi</div>
        <div>• Tiền cọc đã thanh toán được <strong>bảo lưu 100%</strong> sang ngày mới/hạng phòng mới (nếu phát sinh chênh lệch giá sẽ bù/cân đối tại quầy).</div>
        <div>• Sau khi gửi, <strong>Lễ tân sẽ chủ động liên hệ lại</strong> qua SĐT/Email trong <strong>15–30 phút</strong> (tối đa 2 giờ) để xác nhận giữ phòng. Hỗ trợ gấp: <strong class="tabular-nums">1900 1833</strong>.</div>
      </div>
      <div>
        <label class="block font-semibold mb-1">Lý do thay đổi</label>
        <input type="text" name="reason" placeholder="Thay đổi lịch trình chuyến bay đến Phú Quốc..." class="w-full px-3 py-2 border rounded-lg" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="mtCloseModal('changeModal')" class="px-4 py-2 border rounded-lg font-semibold">Đóng</button>
        <button type="submit" id="cmSubmitBtn" class="px-5 py-2 bg-[#C9A45C] text-[#0E1D33] font-bold uppercase rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">GỬI YÊU CẦU</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 3: YÊU CẦU HỦY ĐẶT PHÒNG -->
<div id="cancelModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-2xl border border-[#E6DFD1]">
    <div class="px-6 py-4 border-b border-[#EFE9DC] flex items-center justify-between">
      <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">Yêu cầu hủy đặt phòng</h3>
      <button type="button" onclick="mtCloseModal('cancelModal')" class="text-slate-400 hover:text-[#0E1D33]">✕</button>
    </div>
    <form id="cancelModalForm" method="POST" action="" class="p-6 space-y-3.5 text-xs">
      <input type="hidden" name="type" value="cancel" />
      <p class="text-slate-600">Yêu cầu hủy sẽ được chuyển tới quầy Lễ tân kiểm tra chính sách hoàn/hủy cho mã đơn <strong id="can_code"></strong>.</p>
      <div id="can_policy_box" class="p-3 rounded-xl border text-[11px] space-y-1"></div>
      <div class="p-2.5 bg-[#FAF6EC] border border-[#E6DFD1] rounded-lg text-[11px] text-slate-700">
        <strong>Phản hồi Lễ tân:</strong> Lễ tân sẽ chủ động gọi điện/nhắn tin xác nhận hoàn cọc trong <strong>15–30 phút</strong> (tối đa 2 giờ). Hotline: <strong class="tabular-nums">1900 1833</strong>.
      </div>
      <div>
        <label class="block font-semibold mb-1">Lý do hủy đặt phòng *</label>
        <input type="text" id="can_reason" name="reason" required minlength="3" placeholder="Thay đổi kế hoạch công tác / gia đình..." class="w-full px-3 py-2 border rounded-lg" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="mtCloseModal('cancelModal')" class="px-4 py-2 border rounded-lg font-semibold">Đóng</button>
        <button type="submit" class="px-5 py-2 bg-rose-700 text-white font-bold uppercase rounded-lg">Xác nhận gửi hủy</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 4: ĐÁNH GIÁ KỲ NGHỈ -->
<div id="reviewModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-2xl border border-[#E6DFD1]">
    <div class="px-6 py-4 border-b border-[#EFE9DC] flex items-center justify-between">
      <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">Đánh giá kỳ nghỉ (<span id="rev_code"></span>)</h3>
      <button type="button" onclick="mtCloseModal('reviewModal')" class="text-slate-400 hover:text-[#0E1D33]">✕</button>
    </div>
    <form id="reviewModalForm" method="POST" action="" class="p-6 space-y-3.5 text-xs">
      <div>
        <label class="block font-semibold mb-1">Mức độ hài lòng</label>
        <select name="overall_rating" class="w-full px-3 py-2 border rounded-lg bg-white">
          <option value="5">★★★★★ — Xuất sắc (5/5)</option>
          <option value="4">★★★★☆ — Rất hài lòng (4/5)</option>
          <option value="3">★★★☆☆ — Hài lòng (3/5)</option>
          <option value="2">★★☆☆☆ — Chưa tốt (2/5)</option>
          <option value="1">★☆☆☆☆ — Kém (1/5)</option>
        </select>
      </div>
      <div>
        <label class="block font-semibold mb-1">Tiêu đề</label>
        <input type="text" name="title" required placeholder="Kỳ nghỉ tuyệt vời..." class="w-full px-3 py-2 border rounded-lg" />
      </div>
      <div>
        <label class="block font-semibold mb-1">Cảm nhận của bạn</label>
        <textarea name="comment" rows="3" required placeholder="Phòng sạch đẹp, nhân viên thân thiện, bữa sáng ngon..." class="w-full px-3 py-2 border rounded-lg"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="mtCloseModal('reviewModal')" class="px-4 py-2 border rounded-lg font-semibold">Đóng</button>
        <button type="submit" class="px-5 py-2 bg-[#C9A45C] text-[#0E1D33] font-bold uppercase rounded-lg">GỬI ĐÁNH GIÁ</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 5: XÁC NHẬN ĐỀ NGHỊ HỦY TỪ LỄ TÂN -->
<div id="respondCancelModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-[#E6DFD1]">
    <div class="px-6 py-4 border-b border-[#EFE9DC] flex items-center justify-between">
      <div>
        <div class="text-[10px] font-bold uppercase tracking-widest text-amber-700">XÁC NHẬN ĐỀ NGHỊ HỦY PHÒNG TỪ LỄ TÂN</div>
        <h3 class="font-serif text-2xl font-semibold text-[#0E1D33]">Đơn <span id="rcm_code"></span></h3>
      </div>
      <button type="button" onclick="mtCloseModal('respondCancelModal')" class="text-slate-400 hover:text-[#0E1D33]">✕</button>
    </div>
    <form id="respondCancelForm" method="POST" action="" class="p-6 space-y-4 text-xs">
      <div id="rcm_info" class="p-4 bg-[#FAF7F0] border border-[#E6DFD1] rounded-xl space-y-1.5"></div>
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Ghi chú phản hồi gửi Lễ tân (không bắt buộc)</label>
        <textarea rows="2" name="customer_note" id="rcm_note" placeholder="VD: Tôi đồng ý hủy theo trao đổi qua điện thoại / Tôi vẫn giữ phòng theo lịch cũ..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
      </div>
      <div class="flex flex-wrap justify-end gap-2 pt-2">
        <button type="submit" name="decision" value="decline" class="px-4 py-2 border border-[#0E1D33] text-[#0E1D33] font-bold rounded-lg">Không đồng ý · Giữ lại đơn</button>
        <button type="submit" name="decision" value="accept" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg">Xác nhận đồng ý hủy đơn</button>
      </div>
    </form>
  </div>
</div>

<script>
const MT_BOOKINGS = <?= json_encode($bookingJsonMap, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const MT_CATEGORIES = <?= json_encode($categories ?? [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const MT_POLICIES = <?= json_encode($policies ?? [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const MT_TODAY = '<?= date('Y-m-d') ?>';
let mtActiveChangeBooking = null;
const fmtVND = (n) => Math.round(Number(n) || 0).toLocaleString('vi-VN') + ' đ';
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

function mtCloseModal(id) {
  document.getElementById(id).classList.add('hidden');
}

function mtFilterBookingsTable(q) {
  const kw = q.trim().toLowerCase();
  document.querySelectorAll('#myBookingsTable .booking-row').forEach((tr) => {
    tr.style.display = !kw || (tr.dataset.search || '').includes(kw) ? '' : 'none';
  });
}

function mtToggleServiceQR(id) {
  const el = document.getElementById('svcQrBox_' + id);
  if (el) el.classList.toggle('hidden');
}

function mtToggleSvcGroup(id) {
  const el = document.getElementById('svcGroupBody_' + id);
  if (el) el.classList.toggle('hidden');
}

function mtValidateChangeModal() {
  const warn = document.getElementById('cmCapacityWarn');
  const btn = document.getElementById('cmSubmitBtn');
  if (!warn || !btn || !mtActiveChangeBooking) return;
  const catId = Number(document.getElementById('cm_cat').value);
  const cat = MT_CATEGORIES.find(c => Number(c.id) === catId);
  if (!cat) {
    warn.classList.add('hidden');
    btn.disabled = false;
    return;
  }
  const rc = Math.max(1, Number(mtActiveChangeBooking.rooms_count || 1));
  const adults = Number(mtActiveChangeBooking.adults || 1);
  const children = Number(mtActiveChangeBooking.children || 0);
  const maxAdults = Number(cat.max_adults || 2) * rc;
  const maxTotal = (Number(cat.max_adults || 2) + Number(cat.max_children ?? 1)) * rc;

  if (adults > maxAdults || (adults + children) > maxTotal) {
    warn.innerHTML = '⚠ Hạng phòng <strong>' + esc(cat.name) + '</strong> (' + rc + ' phòng) chỉ chứa tối đa <strong>' + maxAdults + ' người lớn</strong> và tổng <strong>' + maxTotal + ' khách</strong>. Đơn của bạn đang có ' + adults + ' NL' + (children > 0 ? ', ' + children + ' TE' : '') + '. Vui lòng chọn hạng phòng có sức chứa lớn hơn.';
    warn.classList.remove('hidden');
    btn.disabled = true;
  } else {
    warn.classList.add('hidden');
    btn.disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const ci = document.getElementById('cm_in');
  const co = document.getElementById('cm_out');
  if (ci && co) {
    ci.addEventListener('change', () => {
      if (ci.value && ci.value < MT_TODAY) ci.value = MT_TODAY;
      const d = new Date((ci.value || MT_TODAY) + 'T00:00:00Z');
      d.setUTCDate(d.getUTCDate() + 1);
      const minOut = d.toISOString().slice(0, 10);
      co.min = minOut;
      if (!co.value || co.value <= ci.value) co.value = minOut;
    });
  }
});

function mtOpenChangeModal(bid) {
  const b = MT_BOOKINGS[bid];
  if (!b) return;
  mtActiveChangeBooking = b;
  document.getElementById('changeModalForm').action = '/bookings/' + bid + '/request-change';
  const ci = document.getElementById('cm_in');
  const co = document.getElementById('cm_out');
  ci.value = b.check_in_date >= MT_TODAY ? b.check_in_date : MT_TODAY;
  const d = new Date(ci.value + 'T00:00:00Z');
  d.setUTCDate(d.getUTCDate() + 1);
  const minOut = d.toISOString().slice(0, 10);
  co.min = minOut;
  co.value = b.check_out_date > ci.value ? b.check_out_date : minOut;
  document.getElementById('cm_cat').value = String(b.category_id);
  mtValidateChangeModal();
  document.getElementById('changeModal').classList.remove('hidden');
}

function mtOpenCancelModal(bid) {
  const b = MT_BOOKINGS[bid];
  if (!b) return;
  document.getElementById('cancelModalForm').action = '/bookings/' + bid + '/request-change';
  document.getElementById('can_code').textContent = b.booking_code;
  document.getElementById('can_reason').value = '';

  const freeDays = Number(MT_POLICIES.free_cancel_days ?? 14);
  const latePct = Number(MT_POLICIES.late_cancel_fee_percent ?? 50);
  const paid = Number(b.total_paid || 0);
  const nights = Math.max(1, Number(b.nights || 1));
  const firstNight = Math.round(Number(b.room_subtotal || 0) / nights);
  const ciDate = new Date(b.check_in_date + 'T00:00:00');
  const todayDate = new Date(MT_TODAY + 'T00:00:00');
  const daysUntil = Math.round((ciDate - todayDate) / 86400000);
  const isFree = daysUntil >= freeDays;
  const penalty = isFree ? 0 : Math.min(paid, Math.round(firstNight * (latePct / 100)));
  const refund = Math.max(0, paid - penalty);

  const box = document.getElementById('can_policy_box');
  if (isFree) {
    box.className = 'p-3 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-[11px] space-y-1';
    box.innerHTML = '<div class="font-bold">✓ Đủ điều kiện Hủy Miễn Phí (trước ≥ ' + freeDays + ' ngày, hiện còn ' + Math.max(0, daysUntil) + ' ngày)</div>'
      + '<div>• Đã cọc/thanh toán: <strong class="tabular-nums">' + fmtVND(paid) + '</strong> · Phí giữ lại: <strong>0 đ</strong></div>'
      + '<div>• Số tiền dự kiến hoàn trả: <strong class="tabular-nums text-emerald-700">' + fmtVND(refund) + '</strong> (trong 3–5 ngày làm việc).</div>';
  } else {
    box.className = 'p-3 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-[11px] space-y-1';
    box.innerHTML = '<div class="font-bold">⚠ Hủy muộn (còn ' + Math.max(0, daysUntil) + ' ngày &lt; ' + freeDays + ' ngày quy định)</div>'
      + '<div>• Đã cọc/thanh toán: <strong class="tabular-nums">' + fmtVND(paid) + '</strong> · Giữ lại ' + latePct + '% đêm đầu: <strong class="tabular-nums text-rose-700">' + fmtVND(penalty) + '</strong></div>'
      + '<div>• Số tiền dự kiến hoàn lại: <strong class="tabular-nums text-emerald-700">' + fmtVND(refund) + '</strong> (hoặc liên hệ Lễ tân để bảo lưu dời ngày miễn phí).</div>';
  }

  document.getElementById('cancelModal').classList.remove('hidden');
}

function mtOpenReviewModal(bid) {
  const b = MT_BOOKINGS[bid];
  if (!b) return;
  document.getElementById('reviewModalForm').action = '/bookings/' + bid + '/review';
  document.getElementById('rev_code').textContent = b.booking_code;
  document.getElementById('reviewModal').classList.remove('hidden');
}

function mtOpenRespondCancelModal(bid) {
  const b = MT_BOOKINGS[bid];
  if (!b) return;
  let parsed = {};
  try {
    parsed = b.change_request_payload ? (typeof b.change_request_payload === 'string' ? JSON.parse(b.change_request_payload) : b.change_request_payload) : {};
  } catch (e) {
    parsed = {};
  }
  document.getElementById('respondCancelForm').action = '/bookings/' + bid + '/respond-cancel';
  document.getElementById('rcm_code').textContent = b.booking_code;
  document.getElementById('rcm_note').value = '';
  document.getElementById('rcm_info').innerHTML =
    '<div><strong class="text-[#0E1D33]">Lý do hủy từ Lễ tân:</strong> <span class="text-slate-800">' + esc(parsed.reason || 'Lễ tân đề nghị hủy đặt phòng') + '</span></div>'
    + (parsed.proposed_by_name ? '<div class="text-slate-500">Nhân viên đề nghị: ' + esc(parsed.proposed_by_name) + '</div>' : '')
    + (parsed.policy_summary ? '<div class="text-slate-700 pt-1 border-t border-[#E6DFD1]"><strong>Chính sách cọc &amp; hoàn tiền:</strong> ' + esc(parsed.policy_summary) + '</div>' : '');
  document.getElementById('respondCancelModal').classList.remove('hidden');
}

function mtOpenInvoiceModal(bid) {
  const b = MT_BOOKINGS[bid];
  if (!b) return;
  const inv = b.invoice || null;
  const roomCharges = inv ? Number(inv.room_charges) : Number(b.room_subtotal);
  const prebookedTotal = (b.selected_items || []).reduce((sum, it) => sum + Number(it.total_price || 0), 0);
  const completedInStay = (b.service_orders || []).filter(so => !Number(so.is_prebooked) && so.status === 'completed');
  const inStayGross = completedInStay.reduce((s, so) => s + Number(so.total_price || 0), 0);
  const serviceDiscount = Number(b.service_discount_total || 0);
  const serviceCharges = inv ? Number(inv.service_charges) : Math.max(0, prebookedTotal + inStayGross - serviceDiscount);
  const surcharges = inv ? Number(inv.surcharges || 0) : 0;
  const extensionFee = inv ? Number(inv.extension_fee || 0) : Number(b.extension_fee || 0);
  const discount = inv ? Number(inv.discount_amount || 0) : Number(b.discount_amount || 0);
  const taxable = Math.max(0, roomCharges + extensionFee + serviceCharges + surcharges - discount);
  const taxTotal = inv ? Number(inv.tax_amount) : Math.round(taxable * (Number(b.locked_tax_rate || 13.4) / 100));
  const svc5 = Math.round(taxable * 0.05);
  const vat8 = Math.max(0, taxTotal - svc5);
  const totalAmount = inv ? Number(inv.total_amount) : (taxable + taxTotal);
  const paidAmount = inv ? Number(inv.paid_amount) : Number(b.total_paid || 0);
  const balance = Math.max(0, totalAmount - paidAmount);
  const payments = b.payments || [];

  const inStayRows = completedInStay.map(so => `
    <div class="flex justify-between py-1.5 border-b border-slate-100">
      <span>Dịch vụ: ${esc(so.service_name_snapshot)} × ${Number(so.quantity)}</span>
      <span class="tabular-nums font-medium">${fmtVND(so.total_price)}</span>
    </div>
  `).join('');

  const payRows = payments.length === 0
    ? '<tr><td colspan="4" class="py-3 text-center text-slate-400">Chưa có giao dịch thanh toán nào.</td></tr>'
    : payments.map(p => {
      const vs = p.verification_status || 'pending';
      const vsLbl = vs === 'verified' ? 'Đã khớp' : (vs === 'rejected' ? 'Không khớp' : 'Chờ đối soát');
      return `
        <tr>
          <td class="py-1.5 text-slate-600 tabular-nums">${esc(p.recorded_at)}</td>
          <td class="py-1.5">${esc(p.method)} <span class="text-[10px] text-slate-400">(${esc(vsLbl)})</span></td>
          <td class="py-1.5 font-mono text-[#0E1D33]">${esc(p.payment_code)}</td>
          <td class="py-1.5 text-right font-semibold tabular-nums">${fmtVND(p.amount)}</td>
        </tr>
      `;
    }).join('');

  const rf = b.refund || null;
  const rfStMap = { pending: 'Chờ duyệt hoàn tiền', approved: 'Đã duyệt · Chờ chi', rejected: 'Từ chối hoàn', processed: 'Đã hoàn tiền thành công' };
  const refundHtml = rf ? `
    <div class="p-4 rounded-xl border border-[#E6DFD1] bg-[#FAF7F0] space-y-1.5">
      <div class="flex justify-between items-center">
        <span class="text-[10px] font-bold text-[#A8843E] uppercase">HỒ SƠ HOÀN TIỀN HỦY PHÒNG (${esc(rf.refund_code)})</span>
        <span class="font-bold text-[#0E1D33]">${esc(rfStMap[rf.status] || rf.status)}</span>
      </div>
      <div class="flex justify-between"><span>Đã thanh toán hợp lệ:</span><strong class="tabular-nums">${fmtVND(rf.paid_verified_amount)}</strong></div>
      <div class="flex justify-between"><span>Phí hủy giữ lại:</span><strong class="tabular-nums text-rose-700">-${fmtVND(rf.retained_fee)}</strong></div>
      <div class="flex justify-between border-t border-[#E6DFD1] pt-1 font-bold"><span>Số tiền hoàn trả:</span><strong class="tabular-nums text-emerald-700">${fmtVND(rf.refund_amount)}</strong></div>
      ${rf.reference_no ? `<div class="text-[11px] text-slate-600">Mã bút toán hoàn tiền: <strong class="font-mono">${esc(rf.reference_no)}</strong></div>` : ''}
      ${rf.decision_note ? `<div class="text-[11px] text-slate-600">Ghi chú: ${esc(rf.decision_note)}</div>` : ''}
    </div>
  ` : '';

  document.getElementById('invoiceModalBody').innerHTML = `
    <div class="flex flex-wrap justify-between items-start gap-4 border-b border-[#EFE9DC] pb-4">
      <div>
        <div class="text-[11px] font-bold tracking-[0.15em] text-[#A8843E] uppercase">MƯỜNG THANH LUXURY PHÚ QUỐC</div>
        <div class="text-[11px] text-slate-500 mt-0.5">Khu phức hợp Bãi Trường, Dương Tơ, Phú Quốc · ĐT: 1900 1833</div>
      </div>
      <div class="text-right">
        <div class="font-serif text-2xl font-semibold text-[#0E1D33]">HÓA ĐƠN LƯU TRÚ</div>
        <div class="font-mono font-bold text-[#A8843E]">${esc(inv ? inv.invoice_code : b.booking_code)}</div>
        <div class="text-[10px] text-slate-400">Lập ngày ${esc(inv ? inv.issued_at : b.created_at)}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-[#FAF7F0] border border-[#EFE9DC] rounded-xl p-4">
      <div class="space-y-1">
        <div class="text-[10px] font-bold text-[#A8843E] uppercase">THÔNG TIN KHÁCH</div>
        <div class="font-bold text-[#0E1D33]">${esc(b.guest_name)}</div>
        <div class="text-slate-600">${esc(b.guest_email)}</div>
        <div class="text-slate-600 tabular-nums">${esc(b.guest_phone)}</div>
      </div>
      <div class="space-y-1">
        <div class="text-[10px] font-bold text-[#A8843E] uppercase">THÔNG TIN LƯU TRÚ</div>
        <div class="font-bold text-[#0E1D33]">${esc(b.category_name)}</div>
        <div class="text-slate-600">${b.assigned_room_number ? 'Phòng số: ' + esc(b.assigned_room_number) + ' · ' : ''}${Number(b.nights)} đêm</div>
        <div class="text-slate-600 tabular-nums">Nhận: ${esc(b.check_in_date)} · Trả: ${esc(b.check_out_date)}</div>
      </div>
    </div>

    <div>
      <div class="flex justify-between py-2 border-b border-slate-200 font-bold text-[10px] uppercase text-slate-400">
        <span>KHOẢN MỤC</span><span>THÀNH TIỀN</span>
      </div>
      <div class="flex justify-between py-1.5 border-b border-slate-100">
        <span>Tiền phòng & Gói (${esc(b.package_name)})</span>
        <span class="tabular-nums font-semibold">${fmtVND(roomCharges)}</span>
      </div>
      ${(b.selected_items || []).map(it => `
        <div class="flex justify-between py-1.5 border-b border-slate-100">
          <span>${esc(it.name)} × ${Number(it.quantity)}</span>
          <span class="tabular-nums font-medium">${fmtVND(it.total_price)}</span>
        </div>
      `).join('')}
      ${inStayRows}
      ${extensionFee > 0 ? `<div class="flex justify-between py-1.5 border-b border-slate-100"><span>Phí gia hạn / Trả phòng muộn (${esc(b.checkout_time_slot || '12:00')})</span><span class="tabular-nums font-medium">${fmtVND(extensionFee)}</span></div>` : ''}
      ${serviceDiscount > 0 ? `<div class="flex justify-between py-1.5 border-b border-slate-100 text-emerald-700 font-semibold"><span>Ưu đãi Voucher dịch vụ</span><span class="tabular-nums">-${fmtVND(serviceDiscount)}</span></div>` : ''}
      ${discount > 0 ? `<div class="flex justify-between py-1.5 border-b border-slate-100 text-emerald-700 font-semibold"><span>Giảm giá Voucher (${esc(b.voucher_code)})</span><span class="tabular-nums">-${fmtVND(discount)}</span></div>` : ''}
      ${surcharges > 0 ? `<div class="flex justify-between py-1.5 border-b border-slate-100"><span>Phụ phí phát sinh (${esc(inv?.surcharge_note || '')})</span><span class="tabular-nums">${fmtVND(surcharges)}</span></div>` : ''}
      <div class="flex justify-between py-1.5 border-b border-slate-100 text-slate-600"><span>Phí dịch vụ (5%)</span><span class="tabular-nums">${fmtVND(svc5)}</span></div>
      <div class="flex justify-between py-1.5 border-b border-slate-100 text-slate-600"><span>Thuế GTGT (8%)</span><span class="tabular-nums">${fmtVND(vat8)}</span></div>
    </div>

    <div class="bg-[#FAF7F0] border border-[#EFE9DC] rounded-xl p-4 grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
      <div class="sm:col-span-4 flex flex-col items-center">
        <div class="bg-white p-2 rounded border border-[#E6DFD1]">
          <svg viewBox="0 0 80 80" class="w-20 h-20 text-[#0E1D33]" fill="currentColor">
            <rect x="4" y="4" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="11" y="11" width="10" height="10"/>
            <rect x="52" y="4" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="59" y="11" width="10" height="10"/>
            <rect x="4" y="52" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="11" y="59" width="10" height="10"/>
            <rect x="35" y="10" width="8" height="8"/><rect x="35" y="24" width="8" height="14"/><rect x="10" y="36" width="16" height="8"/>
            <rect x="34" y="44" width="16" height="12"/><rect x="56" y="36" width="14" height="10"/><rect x="54" y="54" width="20" height="20" fill="none" stroke="currentColor" stroke-width="4"/>
          </svg>
        </div>
        <span class="text-[10px] text-slate-500 mt-1">Quét để xác thực hóa đơn</span>
      </div>
      <div class="sm:col-span-8 space-y-1.5">
        <div class="flex justify-between items-baseline">
          <span class="font-bold text-[#0E1D33]">Tổng cộng</span>
          <span class="font-serif text-2xl font-bold text-[#0E1D33] tabular-nums">${fmtVND(totalAmount)}</span>
        </div>
        <div class="flex justify-between text-emerald-700 font-semibold"><span>Đã thanh toán</span><span class="tabular-nums">${fmtVND(paidAmount)}</span></div>
        <div class="flex justify-between font-bold text-[#0E1D33] border-t border-[#E6DFD1] pt-1"><span>Còn lại</span><span class="tabular-nums">${fmtVND(balance)}</span></div>
      </div>
    </div>

    <div>
      <div class="text-[10px] font-bold text-[#A8843E] uppercase mb-2">LỊCH SỬ GIAO DỊCH</div>
      <table class="w-full text-left border-collapse">
        <thead class="text-[10px] text-slate-400 border-b border-slate-200">
          <tr><th class="py-1.5">Thời gian</th><th class="py-1.5">Phương thức</th><th class="py-1.5">Mã GD</th><th class="py-1.5 text-right">Số tiền</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">${payRows}</tbody>
      </table>
    </div>

    ${refundHtml}

    ${balance > 0 && b.status !== 'cancelled' ? `<div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[11px]">Số dư còn lại: <strong>${fmtVND(balance)}</strong> (Thanh toán tại quầy lễ tân khi nhận/trả phòng).</div>` : ''}

    <div class="no-print flex justify-end gap-2 pt-2">
      <button type="button" onclick="mtCloseModal('invoiceModalBackdrop')" class="px-4 py-2 border border-[#E6DFD1] rounded-lg font-semibold">ĐÓNG</button>
      <button type="button" onclick="window.print()" class="px-5 py-2 bg-[#C9A45C] text-[#0E1D33] font-bold uppercase rounded-lg">IN HÓA ĐƠN</button>
    </div>
  `;
  document.getElementById('invoiceModalBackdrop').classList.remove('hidden');
}
</script>
