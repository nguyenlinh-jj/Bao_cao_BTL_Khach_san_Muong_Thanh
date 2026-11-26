<?php
$catTabs = [
    'dining'     => 'Ăn uống',
    'utility'    => 'Tiện ích',
    'spa'        => 'Spa',
    'experience' => 'Trải nghiệm',
];

$diningSubGroups = [
    'dining_other'     => 'KHÁC',
    'dining_set'       => 'SET MENU',
    'dining_seafood'   => 'HẢI SẢN',
    'dining_appetizer' => 'KHAI VỊ',
    'dining_viet'      => 'MÓN VIỆT',
    'dining_drink'     => 'ĐỒ UỐNG',
];

$clientServices = [];
foreach ($services as $s) {
    $clientServices[(int) $s['id']] = [
        'id'          => (int) $s['id'],
        'code'        => $s['code'],
        'name'        => $s['name'],
        'category'    => $s['category'],
        'unit_label'  => $s['unit_label'],
        'price'       => (float) ($s['effective_price'] ?? $s['price']),
        'orig_price'  => (float) $s['price'],
        'promo_tag'   => !empty($s['has_active_promo']) ? ($s['active_promo_tag'] ?? '') : '',
        'description' => $s['description'],
    ];
}
?>

<div class="max-w-[1280px] mx-auto px-4 lg:px-6 py-8 space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#E6DFD1] pb-5">
    <div>
      <a href="/my-bookings?tab=services" class="text-xs font-semibold text-[#A8843E] hover:underline">← Quay lại giao diện Khách hàng</a>
      <h1 class="font-serif text-3xl sm:text-4xl font-semibold text-[#0E1D33] mt-1">Gọi Món & Dịch Vụ Tại Phòng</h1>
      <p class="text-xs text-slate-500 mt-1">Chọn thực đơn ẩm thực Sonasea, liệu trình Pearl Spa và trải nghiệm đảo ngọc giao tận phòng hoặc đặt trước kỳ nghỉ.</p>
    </div>

    <?php if (!empty($eligibleBookings)): ?>
      <form method="GET" action="/room-services" class="flex items-center gap-2 text-xs">
        <label class="font-semibold text-slate-600">Đơn phòng áp dụng:</label>
        <select name="booking_id" onchange="this.form.submit()" class="px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white font-semibold text-[#0E1D33]">
          <?php foreach ($eligibleBookings as $eb): ?>
            <option value="<?= (int) $eb['id'] ?>" <?= $selectedBooking && (int) $selectedBooking['id'] === (int) $eb['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($eb['booking_code']) ?>
              <?= !empty($eb['assigned_room_number']) ? ' · P.' . htmlspecialchars($eb['assigned_room_number']) : '' ?>
              <?= !empty($eb['service_access_code']) ? ' (' . htmlspecialchars($eb['service_access_code']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>

  <?php if (empty($selectedBooking)): ?>
    <div class="bg-white border border-[#E6DFD1] rounded-2xl p-10 text-center space-y-3">
      <div class="font-serif text-2xl text-[#0E1D33]">Chưa có đặt phòng đủ điều kiện gọi dịch vụ</div>
      <p class="text-xs text-slate-500 max-w-md mx-auto">Chức năng gọi dịch vụ tại phòng áp dụng cho đơn đặt phòng đã được Lễ tân xác nhận hoặc đang lưu trú (chưa quá hạn).</p>
      <a href="/my-bookings" class="inline-block px-5 py-2.5 bg-[#0E1D33] text-white text-xs font-bold uppercase rounded-lg">Quay lại Đơn của tôi</a>
    </div>
  <?php else: ?>
    <!-- Confirmation state if submitted -->
    <div id="rsSuccessBanner" class="<?= $submittedGroup !== '' ? '' : 'hidden' ?> bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center space-y-3">
      <div class="text-xs font-bold uppercase tracking-widest text-emerald-700">ĐÃ GỬI YÊU CẦU TỚI LỄ TÂN</div>
      <h2 class="font-serif text-2xl font-semibold text-[#0E1D33]">
        Mã nhóm dịch vụ: <span id="rsSuccessCode" class="font-mono text-emerald-800"><?= htmlspecialchars($submittedGroup) ?></span>
      </h2>
      <p class="text-xs text-slate-600">Yêu cầu của quý khách đã được ghi nhận vào hệ thống và đồng bộ về bàn Lễ tân để xử lý phục vụ.</p>
      <div class="pt-1 flex justify-center gap-3">
        <a href="/my-bookings?tab=services" class="px-5 py-2.5 bg-[#0E1D33] text-white text-xs font-bold uppercase rounded-lg">Quay lại giao diện Khách hàng</a>
        <a href="/room-services?booking_id=<?= (int) $selectedBooking['id'] ?>" class="px-5 py-2.5 border border-[#E6DFD1] bg-white text-[#0E1D33] text-xs font-bold uppercase rounded-lg">Đặt thêm dịch vụ khác</a>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-7 items-start">
      <!-- LEFT: DANH MỤC DỊCH VỤ CHIA THEO MỤC -->
      <div class="lg:col-span-8 space-y-5">
        <div class="flex flex-wrap gap-2 bg-white border border-[#E6DFD1] p-2 rounded-xl">
          <?php $firstTab = true; foreach ($catTabs as $tKey => $tLabel): ?>
            <button type="button" onclick="rsSelectTab('<?= $tKey ?>')" id="rsTabBtn_<?= $tKey ?>"
              class="flex-1 min-w-[110px] py-2.5 px-4 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors <?= $firstTab ? 'bg-[#0E1D33] text-white' : 'text-slate-600 hover:bg-[#FAF7F0]' ?>">
              <?= htmlspecialchars($tLabel) ?>
            </button>
          <?php $firstTab = false; endforeach; ?>
        </div>

        <!-- TAB 1: ĂN UỐNG (6 NHÓM CON) -->
        <div id="rsPanel_dining" class="space-y-6">
          <?php foreach ($diningSubGroups as $subKey => $subLabel):
            $groupItems = array_values(array_filter($services, fn($s) => $s['category'] === $subKey));
            if (empty($groupItems)) continue;
          ?>
            <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
              <div class="text-xs font-bold tracking-[0.16em] text-[#A8843E] uppercase border-b border-[#EFE9DC] pb-2"><?= htmlspecialchars($subLabel) ?></div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                <?php foreach ($groupItems as $item):
                  $sid = (int) $item['id'];
                  $effPrice = (float) ($item['effective_price'] ?? $item['price']);
                  $hasPromo = !empty($item['has_active_promo']) && $effPrice < (float) $item['price'];
                  $promoBadge = $hasPromo ? (string) ($item['active_promo_tag'] ?? '') : '';
                ?>
                  <div class="p-3.5 border border-[#EFE9DC] rounded-xl flex flex-col justify-between gap-3 bg-[#FCFBF8]">
                    <div>
                      <div class="flex items-start justify-between gap-2">
                        <h4 class="font-semibold text-sm text-[#0E1D33]"><?= htmlspecialchars($item['name']) ?></h4>
                        <?php if ($promoBadge !== ''): ?>
                          <span class="px-1.5 py-0.5 bg-rose-50 text-rose-700 text-[10px] font-bold rounded shrink-0"><?= htmlspecialchars($promoBadge) ?></span>
                        <?php endif; ?>
                      </div>
                      <p class="text-[11px] text-slate-500 mt-1"><?= htmlspecialchars($item['description']) ?></p>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-[#EFE9DC]">
                      <div class="tabular-nums">
                        <?php if ($hasPromo): ?>
                          <span class="text-[10px] text-slate-400 line-through mr-1"><?= number_format((float)$item['price'], 0, ',', '.') ?>đ</span>
                        <?php endif; ?>
                        <strong class="text-xs text-[#A8843E]"><?= number_format($effPrice, 0, ',', '.') ?> đ</strong>
                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($item['unit_label']) ?></span>
                      </div>
                      <div class="flex items-center gap-2">
                        <button type="button" onclick="rsStepQty(<?= $sid ?>, -1)" class="w-7 h-7 rounded border border-[#E6DFD1] bg-white font-bold text-[#0E1D33]">−</button>
                        <span id="rsQty_<?= $sid ?>" class="w-5 text-center text-xs font-bold tabular-nums">0</span>
                        <button type="button" onclick="rsStepQty(<?= $sid ?>, 1)" class="w-7 h-7 rounded border border-[#0E1D33] bg-[#0E1D33] text-white font-bold">+</button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- TAB 2: TIỆN ÍCH (addon + prep_option) -->
        <div id="rsPanel_utility" class="hidden bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
          <div class="text-xs font-bold tracking-[0.16em] text-[#A8843E] uppercase border-b border-[#EFE9DC] pb-2">TIỆN ÍCH & CHUẨN BỊ PHÒNG</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <?php foreach ($services as $item):
              if (!in_array($item['category'], ['addon', 'prep_option'], true)) continue;
              $sid = (int) $item['id'];
              $effPrice = (float) ($item['effective_price'] ?? $item['price']);
              $hasPromo = !empty($item['has_active_promo']) && $effPrice < (float) $item['price'];
              $promoBadge = $hasPromo ? (string) ($item['active_promo_tag'] ?? '') : '';
            ?>
              <div class="p-3.5 border border-[#EFE9DC] rounded-xl flex flex-col justify-between gap-3 bg-[#FCFBF8]">
                <div>
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="font-semibold text-sm text-[#0E1D33]"><?= htmlspecialchars($item['name']) ?></h4>
                    <?php if ($promoBadge !== ''): ?>
                      <span class="px-1.5 py-0.5 bg-rose-50 text-rose-700 text-[10px] font-bold rounded shrink-0"><?= htmlspecialchars($promoBadge) ?></span>
                    <?php endif; ?>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-1"><?= htmlspecialchars($item['description']) ?></p>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-[#EFE9DC]">
                  <div class="tabular-nums">
                    <?php if ($hasPromo): ?>
                      <span class="text-[10px] text-slate-400 line-through mr-1"><?= number_format((float)$item['price'], 0, ',', '.') ?>đ</span>
                    <?php endif; ?>
                    <strong class="text-xs text-[#A8843E]"><?= number_format($effPrice, 0, ',', '.') ?> đ</strong>
                    <span class="text-[10px] text-slate-400"><?= htmlspecialchars($item['unit_label']) ?></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <button type="button" onclick="rsStepQty(<?= $sid ?>, -1)" class="w-7 h-7 rounded border border-[#E6DFD1] bg-white font-bold">−</button>
                    <span id="rsQty_<?= $sid ?>" class="w-5 text-center text-xs font-bold tabular-nums">0</span>
                    <button type="button" onclick="rsStepQty(<?= $sid ?>, 1)" class="w-7 h-7 rounded border border-[#0E1D33] bg-[#0E1D33] text-white font-bold">+</button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- TAB 3: SPA -->
        <div id="rsPanel_spa" class="hidden bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
          <div class="text-xs font-bold tracking-[0.16em] text-[#A8843E] uppercase border-b border-[#EFE9DC] pb-2">PEARL SPA & THƯ GIÃN</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <?php foreach ($services as $item):
              if (!in_array($item['category'], ['spa'], true)) continue;
              $sid = (int) $item['id'];
              $effPrice = (float) ($item['effective_price'] ?? $item['price']);
              $hasPromo = !empty($item['has_active_promo']) && $effPrice < (float) $item['price'];
              $promoBadge = $hasPromo ? (string) ($item['active_promo_tag'] ?? '') : '';
            ?>
              <div class="p-3.5 border border-[#EFE9DC] rounded-xl flex flex-col justify-between gap-3 bg-[#FCFBF8]">
                <div>
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="font-semibold text-sm text-[#0E1D33]"><?= htmlspecialchars($item['name']) ?></h4>
                    <?php if ($promoBadge !== ''): ?>
                      <span class="px-1.5 py-0.5 bg-rose-50 text-rose-700 text-[10px] font-bold rounded shrink-0"><?= htmlspecialchars($promoBadge) ?></span>
                    <?php endif; ?>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-1"><?= htmlspecialchars($item['description']) ?></p>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-[#EFE9DC]">
                  <div class="tabular-nums">
                    <?php if ($hasPromo): ?>
                      <span class="text-[10px] text-slate-400 line-through mr-1"><?= number_format((float)$item['price'], 0, ',', '.') ?>đ</span>
                    <?php endif; ?>
                    <strong class="text-xs text-[#A8843E]"><?= number_format($effPrice, 0, ',', '.') ?> đ</strong>
                    <span class="text-[10px] text-slate-400"><?= htmlspecialchars($item['unit_label']) ?></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <button type="button" onclick="rsStepQty(<?= $sid ?>, -1)" class="w-7 h-7 rounded border border-[#E6DFD1] bg-white font-bold">−</button>
                    <span id="rsQty_<?= $sid ?>" class="w-5 text-center text-xs font-bold tabular-nums">0</span>
                    <button type="button" onclick="rsStepQty(<?= $sid ?>, 1)" class="w-7 h-7 rounded border border-[#0E1D33] bg-[#0E1D33] text-white font-bold">+</button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- TAB 4: TRẢI NGHIỆM -->
        <div id="rsPanel_experience" class="hidden bg-white border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
          <div class="text-xs font-bold tracking-[0.16em] text-[#A8843E] uppercase border-b border-[#EFE9DC] pb-2">TRẢI NGHIỆM & COMBO ĐẢO NGỌC</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <?php foreach ($services as $item):
              if (!in_array($item['category'], ['experience', 'combo'], true)) continue;
              $sid = (int) $item['id'];
              $effPrice = (float) ($item['effective_price'] ?? $item['price']);
              $hasPromo = !empty($item['has_active_promo']) && $effPrice < (float) $item['price'];
              $promoBadge = $hasPromo ? (string) ($item['active_promo_tag'] ?? '') : '';
            ?>
              <div class="p-3.5 border border-[#EFE9DC] rounded-xl flex flex-col justify-between gap-3 bg-[#FCFBF8]">
                <div>
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="font-semibold text-sm text-[#0E1D33]"><?= htmlspecialchars($item['name']) ?></h4>
                    <?php if ($promoBadge !== ''): ?>
                      <span class="px-1.5 py-0.5 bg-rose-50 text-rose-700 text-[10px] font-bold rounded shrink-0"><?= htmlspecialchars($promoBadge) ?></span>
                    <?php endif; ?>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-1"><?= htmlspecialchars($item['description']) ?></p>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-[#EFE9DC]">
                  <div class="tabular-nums">
                    <?php if ($hasPromo): ?>
                      <span class="text-[10px] text-slate-400 line-through mr-1"><?= number_format((float)$item['price'], 0, ',', '.') ?>đ</span>
                    <?php endif; ?>
                    <strong class="text-xs text-[#A8843E]"><?= number_format($effPrice, 0, ',', '.') ?> đ</strong>
                    <span class="text-[10px] text-slate-400"><?= htmlspecialchars($item['unit_label']) ?></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <button type="button" onclick="rsStepQty(<?= $sid ?>, -1)" class="w-7 h-7 rounded border border-[#E6DFD1] bg-white font-bold">−</button>
                    <span id="rsQty_<?= $sid ?>" class="w-5 text-center text-xs font-bold tabular-nums">0</span>
                    <button type="button" onclick="rsStepQty(<?= $sid ?>, 1)" class="w-7 h-7 rounded border border-[#0E1D33] bg-[#0E1D33] text-white font-bold">+</button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT: THANH ĐƠN DỊCH VỤ ĐÃ CHỌN & XÁC NHẬN -->
      <aside class="lg:col-span-4 sticky top-24">
        <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-lg">
          <div class="bg-[#0E1D33] text-white px-5 py-4 space-y-1">
            <div class="text-[10px] tracking-[0.18em] text-[#E2C27D] uppercase font-bold">ĐƠN DỊCH VỤ TẠI PHÒNG</div>
            <div class="flex items-center justify-between">
              <span class="font-mono font-bold text-sm"><?= htmlspecialchars($selectedBooking['booking_code']) ?></span>
              <?php if (!empty($selectedBooking['assigned_room_number'])): ?>
                <span class="px-2 py-0.5 bg-[#C9A45C] text-[#0E1D33] rounded text-[11px] font-bold">Phòng <?= htmlspecialchars($selectedBooking['assigned_room_number']) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($selectedBooking['service_access_code'])): ?>
              <div class="text-[11px] text-slate-300">Mã truy cập DV: <strong class="font-mono text-white"><?= htmlspecialchars($selectedBooking['service_access_code']) ?></strong></div>
            <?php endif; ?>
          </div>

          <div class="p-5 space-y-4 text-xs">
            <div id="rsCartList" class="space-y-2 max-h-[240px] overflow-y-auto">
              <div class="p-4 border border-dashed border-[#E6DFD1] rounded-xl text-center text-slate-500">
                Chưa chọn dịch vụ nào. Nhấn <strong>+</strong> bên trái để thêm món hoặc liệu trình.
              </div>
            </div>

            <!-- Voucher dịch vụ -->
            <div class="bg-[#FAF7F0] border border-[#EFE9DC] rounded-xl p-3.5 space-y-2">
              <div class="text-[10px] font-bold uppercase text-[#A8843E]">MÃ ƯU ĐÃI DỊCH VỤ</div>
              <?php if (!empty($serviceVouchers)): ?>
                <div class="flex flex-wrap gap-1.5">
                  <?php foreach ($serviceVouchers as $sv): ?>
                    <button type="button" onclick="rsApplyVoucher('<?= htmlspecialchars($sv['code']) ?>')"
                      class="px-2 py-0.5 bg-white border border-[#E2C27D] rounded text-[10px] font-mono font-bold text-[#A8843E] hover:bg-[#FAF6EC]">
                      <?= htmlspecialchars($sv['code']) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="flex gap-1.5">
                <input type="text" id="rsVoucherInput" placeholder="SPA50, FOOD15..." class="flex-1 px-2.5 py-1.5 border border-[#E6DFD1] rounded bg-white font-mono uppercase text-[11px]" />
                <button type="button" onclick="rsApplyVoucher()" class="px-3 py-1.5 bg-[#0E1D33] text-white rounded text-[11px] font-bold">Áp dụng</button>
              </div>
              <div id="rsVoucherMsg" class="hidden text-[11px]"></div>
            </div>

            <div class="grid grid-cols-1 gap-2.5">
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Ghi chú &amp; thời gian mong muốn phục vụ (nếu cần)</label>
                <textarea id="rsGuestNote" rows="2" placeholder="VD: Phục vụ lúc 19:00, không hành, ít cay, đặt lịch Spa 2 khách..." class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white"></textarea>
              </div>
            </div>

            <div class="border-t border-[#EFE9DC] pt-3 space-y-1">
              <div class="flex justify-between text-slate-600"><span>Tạm tính dịch vụ:</span><span id="rsGrossTotal" class="tabular-nums font-medium">0 đ</span></div>
              <div id="rsDiscRow" class="hidden flex justify-between text-emerald-700 font-semibold"><span>Giảm giá Voucher:</span><span id="rsDiscTotal" class="tabular-nums">0 đ</span></div>
              <div class="flex justify-between items-baseline font-bold text-sm text-[#0E1D33] pt-1">
                <span>Tổng chi phí dự kiến:</span>
                <span id="rsNetTotal" class="font-serif text-2xl text-[#A8843E] tabular-nums">0 đ</span>
              </div>
            </div>

            <div id="rsSubmitErr" class="hidden p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px]"></div>

            <button type="button" id="rsSubmitBtn" onclick="rsSubmitOrder()"
              class="w-full py-3 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] font-bold uppercase tracking-wider rounded-xl shadow">
              GỬI YÊU CẦU TỚI LỄ TÂN
            </button>
          </div>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const SERVICES = <?= json_encode($clientServices, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  const BOOKING_ID = <?= $selectedBooking ? (int) $selectedBooking['id'] : 0 ?>;
  const quantities = {};
  let appliedVoucher = null;

  const fmtVND = (n) => Math.round(Number(n) || 0).toLocaleString('vi-VN') + ' đ';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  window.rsSelectTab = (key) => {
    ['dining', 'utility', 'spa', 'experience'].forEach((k) => {
      const p = document.getElementById('rsPanel_' + k);
      const b = document.getElementById('rsTabBtn_' + k);
      if (p) p.classList.toggle('hidden', k !== key);
      if (b) {
        b.className = 'flex-1 min-w-[110px] py-2.5 px-4 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors ' +
          (k === key ? 'bg-[#0E1D33] text-white' : 'text-slate-600 hover:bg-[#FAF7F0]');
      }
    });
  };

  window.rsStepQty = (sid, delta) => {
    const next = Math.max(0, (quantities[sid] || 0) + delta);
    quantities[sid] = next;
    const el = document.getElementById('rsQty_' + sid);
    if (el) el.textContent = String(next);
    renderCart();
  };

  function calcGross() {
    let sum = 0;
    Object.keys(quantities).forEach((sid) => {
      const qty = quantities[sid] || 0;
      if (qty > 0 && SERVICES[sid]) {
        sum += SERVICES[sid].price * qty;
      }
    });
    return sum;
  }

  function renderCart() {
    const list = document.getElementById('rsCartList');
    if (!list) return;
    const entries = Object.keys(quantities)
      .filter((sid) => quantities[sid] > 0 && SERVICES[sid])
      .map((sid) => ({ srv: SERVICES[sid], qty: quantities[sid] }));

    if (entries.length === 0) {
      list.innerHTML = '<div class="p-4 border border-dashed border-[#E6DFD1] rounded-xl text-center text-slate-500">Chưa chọn dịch vụ nào. Nhấn <strong>+</strong> bên trái để thêm món hoặc liệu trình.</div>';
    } else {
      list.innerHTML = entries.map(({ srv, qty }) => `
        <div class="flex items-center justify-between gap-2 p-2.5 bg-[#FAF7F0] border border-[#EFE9DC] rounded-lg">
          <div class="min-w-0">
            <div class="font-semibold text-[#0E1D33] truncate">${esc(srv.name)}</div>
            <div class="text-[10px] text-slate-500">${fmtVND(srv.price)} × ${qty}</div>
          </div>
          <div class="text-right font-bold tabular-nums text-[#0E1D33] shrink-0">${fmtVND(srv.price * qty)}</div>
        </div>
      `).join('');
    }

    const gross = calcGross();
    let disc = 0;
    if (appliedVoucher && appliedVoucher.voucher) {
      const v = appliedVoucher.voucher;
      if (gross >= Number(v.min_order_amount || 0)) {
        if (v.discount_type === 'percent') {
          disc = Math.round(gross * (Number(v.discount_value) / 100));
          if (Number(v.max_discount_amount) > 0 && disc > Number(v.max_discount_amount)) disc = Number(v.max_discount_amount);
        } else {
          disc = Number(v.discount_value);
        }
        disc = Math.min(disc, gross);
      }
    }

    document.getElementById('rsGrossTotal').textContent = fmtVND(gross);
    const discRow = document.getElementById('rsDiscRow');
    if (disc > 0) {
      discRow.classList.remove('hidden');
      document.getElementById('rsDiscTotal').textContent = '-' + fmtVND(disc);
    } else {
      discRow.classList.add('hidden');
    }
    document.getElementById('rsNetTotal').textContent = fmtVND(Math.max(0, gross - disc));
  }

  window.rsApplyVoucher = async (overrideCode) => {
    const inp = document.getElementById('rsVoucherInput');
    const msg = document.getElementById('rsVoucherMsg');
    const code = (overrideCode || inp.value || '').trim().toUpperCase();
    inp.value = code;
    const gross = calcGross();
    if (!code) return;
    try {
      const res = await fetch('/api/vouchers/validate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ code, order_amount: gross, scope: 'service' }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Mã không hợp lệ.');
      appliedVoucher = data;
      msg.className = 'text-[11px] text-emerald-700 font-semibold';
      msg.textContent = '✓ Đã áp dụng mã ' + data.voucher.code + ' (-' + fmtVND(data.discount_amount) + ')';
      msg.classList.remove('hidden');
      renderCart();
    } catch (err) {
      appliedVoucher = null;
      msg.className = 'text-[11px] text-rose-600';
      msg.textContent = err.message;
      msg.classList.remove('hidden');
      renderCart();
    }
  };

  window.rsSubmitOrder = async () => {
    const errEl = document.getElementById('rsSubmitErr');
    errEl.classList.add('hidden');
    const items = Object.keys(quantities)
      .filter((sid) => quantities[sid] > 0)
      .map((sid) => ({ service_id: Number(sid), quantity: quantities[sid] }));

    if (items.length === 0) {
      errEl.textContent = 'Vui lòng chọn ít nhất 1 món hoặc dịch vụ trước khi gửi yêu cầu.';
      errEl.classList.remove('hidden');
      return;
    }

    const btn = document.getElementById('rsSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'ĐANG GỬI YÊU CẦU...';

    try {
      const res = await fetch('/room-services', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          booking_id: BOOKING_ID,
          items,
          requested_time: 'Ngay bây giờ',
          guest_note: document.getElementById('rsGuestNote').value.trim(),
          voucher_code: appliedVoucher ? appliedVoucher.voucher.code : '',
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Không thể gửi yêu cầu dịch vụ.');

      Object.keys(quantities).forEach((k) => {
        quantities[k] = 0;
        const el = document.getElementById('rsQty_' + k);
        if (el) el.textContent = '0';
      });
      appliedVoucher = null;
      document.getElementById('rsVoucherInput').value = '';
      renderCart();

      document.getElementById('rsSuccessCode').textContent = data.result.order_group_code;
      document.getElementById('rsSuccessBanner').classList.remove('hidden');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      errEl.textContent = err.message;
      errEl.classList.remove('hidden');
    } finally {
      btn.disabled = false;
      btn.textContent = 'GỬI YÊU CẦU TỚI LỄ TÂN';
    }
  };
})();
</script>
