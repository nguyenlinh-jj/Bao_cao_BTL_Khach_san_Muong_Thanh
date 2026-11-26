<?php
$comboServices = array_values(array_filter($services, fn($s) => $s['category'] === 'combo'));
$prepServices = array_values(array_filter($services, fn($s) => $s['category'] === 'prep_option'));

$wizardBootData = [
    'categories'         => $categories,
    'allCategoriesCount' => $allCategoriesCount ?? count($categories),
    'searchFilterActive' => $searchFilterActive ?? false,
    'packages'           => $packages,
    'comboServices'      => $comboServices,
    'prepServices'       => $prepServices,
    'vouchers'           => $vouchers ?? [],
    'policies'           => $policies ?? [],
    'authUser'           => $authUser,
    'initial'            => [
        'checkIn'    => $checkIn,
        'checkOut'   => $checkOut,
        'adults'     => $adults,
        'children'   => $children,
        'roomsCount' => $roomsCount,
        'categoryId' => (int) ($preselectedCategoryId ?? 0),
    ],
];
?>
<div id="mtRoomsApp" class="max-w-[1280px] mx-auto px-4 lg:px-6 py-8">
  <!-- Header khi chưa vào Wizard -->
  <div id="catalogHeader" class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-[11px] font-bold tracking-[0.2em] text-[#A8843E] uppercase mb-2">
        <?php if (!empty($searchFilterActive)): ?>
          PHÒNG ĐỀ XUẤT PHÙ HỢP · <?= count($categories) ?> / <?= (int)($allCategoriesCount ?? count($categories)) ?> HẠNG PHÒNG ĐÁP ỨNG NHU CẦU
        <?php else: ?>
          PHÒNG VÀ SUITE · ĐẦY ĐỦ <?= count($categories) ?> HẠNG PHÒNG 5 SAO BÃI TRƯỜNG
        <?php endif; ?>
      </p>
      <h1 class="font-serif text-3xl sm:text-4xl font-semibold text-[#0E1D33] mb-1.5">
        <?= !empty($searchFilterActive) ? 'Kết Quả Kiểm Tra Phòng Trống Theo Số Khách' : 'Bộ Sưu Tập Phòng Nghỉ & Suite Mường Thanh Luxury' ?>
      </h1>
      <p class="text-sm text-slate-600">
        Kiểm tra trực tiếp từ CSDL MySQL từ <strong id="hdrDates"><?= htmlspecialchars($checkIn) ?> → <?= htmlspecialchars($checkOut) ?></strong> ·
        <?= (int)$roomsCount ?> phòng · <?= (int)$adults ?> người lớn<?= $children > 0 ? ", $children trẻ em" : '' ?>.
      </p>
    </div>
    <?php if (!empty($searchFilterActive)): ?>
      <a href="/rooms" class="px-4 py-2 border border-[#E6DFD1] bg-white hover:bg-[#FAF6EC] text-[#0E1D33] text-xs font-bold uppercase rounded-lg">
        Xem đầy đủ tất cả <?= (int)($allCategoriesCount ?? count($categories)) ?> hạng phòng
      </a>
    <?php endif; ?>
  </div>

  <!-- Thanh Tiến Trình 4 Bước Wizard (Ẩn ở màn hình danh sách phòng) -->
  <div id="wizardProgressBar" class="hidden mb-8 bg-[#0E1D33] text-white rounded-2xl px-6 py-4 shadow-md">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2 sm:gap-5 flex-wrap text-xs font-semibold tracking-wider uppercase">
        <div id="stepPill1" class="flex items-center gap-2 text-[#E2C27D]">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold bg-[#C9A45C] text-[#0E1D33]">1</span>
          <span>PHÒNG</span>
        </div>
        <span class="text-slate-600 hidden sm:inline">────────</span>
        <div id="stepPill2" class="flex items-center gap-2 text-slate-400">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold border border-slate-500">2</span>
          <span>THÔNG TIN</span>
        </div>
        <span class="text-slate-600 hidden sm:inline">────────</span>
        <div id="stepPill3" class="flex items-center gap-2 text-slate-400">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold border border-slate-500">3</span>
          <span>THANH TOÁN</span>
        </div>
        <span class="text-slate-600 hidden sm:inline">────────</span>
        <div id="stepPill4" class="flex items-center gap-2 text-slate-400">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold border border-slate-500">4</span>
          <span>ĐÃ XÁC NHẬN</span>
        </div>
      </div>
      <button type="button" id="btnExitWizard" class="text-xs text-slate-300 hover:text-white underline">
        ← Quay lại danh sách phòng
      </button>
    </div>
  </div>

  <!-- BỐ CỤC CHÍNH 2 CỘT (Danh sách/Step 1-3 bên trái + Sidebar Tóm tắt/Kỳ nghỉ bên phải) -->
  <div id="mainBookingGrid" class="grid grid-cols-1 lg:grid-cols-12 gap-7 items-start">
    <!-- CỘT TRÁI -->
    <div class="lg:col-span-8 space-y-6">

      <!-- VIEW 0: BỘ LỌC TÌM KIẾM & DANH SÁCH HẠNG PHÒNG -->
      <div id="catalogListView" class="space-y-6">
        <div id="step0FilterBar" class="bg-white border border-[#E6DFD1] rounded-2xl p-4 sm:p-5 shadow-xs space-y-4 text-xs">
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div>
              <label class="block text-[10px] font-bold uppercase text-[#A8843E] mb-1">Nhận phòng</label>
              <input type="date" id="fltCheckIn" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($checkIn) ?>"
                onchange="window.__mtApp.setField('checkIn', this.value)"
                class="w-full px-2.5 py-2 border border-[#E6DFD1] rounded-lg bg-[#FCFBF8] font-medium tabular-nums" />
            </div>
            <div>
              <label class="block text-[10px] font-bold uppercase text-[#A8843E] mb-1">Trả phòng</label>
              <input type="date" id="fltCheckOut" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= htmlspecialchars($checkOut) ?>"
                onchange="window.__mtApp.setField('checkOut', this.value)"
                class="w-full px-2.5 py-2 border border-[#E6DFD1] rounded-lg bg-[#FCFBF8] font-medium tabular-nums" />
            </div>
            <div>
              <label class="block text-[10px] font-bold uppercase text-[#A8843E] mb-1">Số phòng</label>
              <input type="number" id="fltRoomsCount" min="1" max="1" value="1" readonly
                title="Mỗi đơn đặt áp dụng 1 phòng duy nhất"
                class="w-full px-2.5 py-2 border border-[#E6DFD1] rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed font-medium tabular-nums" />
            </div>
            <div>
              <label class="block text-[10px] font-bold uppercase text-[#A8843E] mb-1">Người lớn</label>
              <input type="number" id="fltAdults" min="1" max="30" value="<?= (int) $adults ?>"
                oninput="window.__mtApp.setField('adults', Math.max(1, Number(this.value) || 1))"
                class="w-full px-2.5 py-2 border border-[#E6DFD1] rounded-lg bg-[#FCFBF8] font-medium tabular-nums" />
            </div>
            <div>
              <label class="block text-[10px] font-bold uppercase text-[#A8843E] mb-1">Trẻ em</label>
              <input type="number" id="fltChildren" min="0" max="20" value="<?= (int) $children ?>"
                oninput="window.__mtApp.setField('children', Math.max(0, Number(this.value) || 0))"
                class="w-full px-2.5 py-2 border border-[#E6DFD1] rounded-lg bg-[#FCFBF8] font-medium tabular-nums" />
            </div>
          </div>

          <div class="pt-3 border-t border-[#EFE9DC] flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5" id="fltTypeButtons">
              <button type="button" data-ftype="all" onclick="window.__mtApp.setFilter('type', 'all')" class="px-3 py-1.5 rounded-lg font-bold border border-[#0E1D33] bg-[#0E1D33] text-white">Tất cả</button>
              <button type="button" data-ftype="room" onclick="window.__mtApp.setFilter('type', 'room')" class="px-3 py-1.5 rounded-lg font-bold border border-[#E6DFD1] bg-white text-slate-600 hover:border-[#C9A45C]">Phòng Tiêu Chuẩn</button>
              <button type="button" data-ftype="suite" onclick="window.__mtApp.setFilter('type', 'suite')" class="px-3 py-1.5 rounded-lg font-bold border border-[#E6DFD1] bg-white text-slate-600 hover:border-[#C9A45C]">Phòng Suite</button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <select id="fltViewSelect" onchange="window.__mtApp.setFilter('view', this.value)" class="px-3 py-1.5 border border-[#E6DFD1] rounded-lg bg-white font-medium text-[#0E1D33]">
                <option value="all">Mọi hướng nhìn</option>
                <option value="city">Hướng Phố</option>
                <option value="ocean">Hướng Biển</option>
                <option value="panorama">Panorama / Tổng thống</option>
              </select>
              <select id="fltSortSelect" onchange="window.__mtApp.setFilter('sort', this.value)" class="px-3 py-1.5 border border-[#E6DFD1] rounded-lg bg-white font-medium text-[#0E1D33]">
                <option value="default">Sắp xếp: Mặc định</option>
                <option value="price_asc">Giá thấp đến cao</option>
                <option value="price_desc">Giá cao đến thấp</option>
              </select>
            </div>
          </div>
        </div>

        <?php if (empty($categories)): ?>
          <div class="bg-white border border-[#E6DFD1] rounded-xl p-10 text-center space-y-3">
            <p class="font-serif text-2xl text-[#0E1D33]">Không có hạng phòng phù hợp số lượng khách hoặc đã hết phòng trống</p>
            <p class="text-xs text-slate-600">Quý khách có thể điều chỉnh số khách mỗi phòng (1 phòng/đơn) hoặc xem toàn bộ <?= (int)($allCategoriesCount ?? 9) ?> hạng phòng của khách sạn.</p>
            <a href="/rooms" class="inline-block px-5 py-2.5 bg-[#C9A45C] text-[#0E1D33] text-xs font-bold uppercase rounded-lg">Xem toàn bộ <?= (int)($allCategoriesCount ?? 9) ?> hạng phòng</a>
          </div>
        <?php else: ?>
          <div id="roomCardsContainer" class="space-y-6"></div>
        <?php endif; ?>
      </div>

      <!-- STEP 1: CHI TIẾT PHÒNG, 4 ẢNH THUMBNAIL, GÓI NGHỈ DƯỠNG & CHÍNH SÁCH -->
      <div id="wizardStep1" class="hidden space-y-6"></div>

      <!-- STEP 2: THÔNG TIN LƯU TRÚ & HỘI VIÊN, VOUCHER, GIỜ ĐẾN/CHUYẾN BAY, COMBO & CHUẨN BỊ TRƯỚC -->
      <div id="wizardStep2" class="hidden space-y-6"></div>

      <!-- STEP 3: THANH TOÁN ĐẶT CỌC / 100% & PHƯƠNG THỨC CHUYỂN KHOẢN -->
      <div id="wizardStep3" class="hidden space-y-6"></div>
    </div>

    <!-- CỘT PHẢI: SIDEBAR -->
    <aside id="bookingSidebar" class="lg:col-span-4 sticky top-24"></aside>
  </div>

  <!-- STEP 4: PHIẾU XÁC NHẬN ĐẶT PHÒNG HOÀN TẤT (FULL WIDTH) -->
  <div id="wizardStep4" class="hidden"></div>
</div>

<script>
(() => {
  const BOOT = <?= json_encode($wizardBootData, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  const TODAY_STR = '<?= date('Y-m-d') ?>';

  const state = {
    step: 0, // 0 = danh sách phòng, 1..4 = các bước đặt phòng
    selectedCatId: null,
    selectedPackageId: BOOT.packages[0] ? Number(BOOT.packages[0].id) : 1,
    activePhotoIdx: 0,
    checkIn: BOOT.initial.checkIn >= TODAY_STR ? BOOT.initial.checkIn : TODAY_STR,
    checkOut: BOOT.initial.checkOut,
    roomsCount: 1,
    adults: Number(BOOT.initial.adults) || 2,
    children: Number(BOOT.initial.children) || 0,
    filterType: 'all', // 'all' | 'room' | 'suite'
    filterView: 'all', // 'all' | 'city' | 'ocean' | 'panorama'
    sortBy: 'default', // 'default' | 'price_asc' | 'price_desc'
    authUser: BOOT.authUser || null,
    inlineAuthTab: 'register', // 'register' | 'login' | 'forgot'
    forgotIdentifier: '',
    forgotCodeDisplay: '',
    forgotExpiresAt: '',
    forgotDeadlineMs: 0,
    forgotAccountName: '',
    forgotDoneMsg: '',
    guestIdNumber: '',
    arrivalTime: '',
    flightNumber: '',
    specialRequests: '',
    voucherInput: '',
    appliedVoucher: null, // { voucher, discount_amount }
    voucherError: '',
    addonQuantities: {}, // { service_id: qty }
    paymentPlan: 'deposit_30', // 'deposit_30' | 'full_100'
    paymentMethod: 'bank_transfer', // 'bank_transfer' | 'card'
    cardHolder: '',
    cardNumber: '',
    cardExpiry: '',
    cardCvv: '',
    agreedTerms: true,
    isSubmitting: false,
    submitError: '',
    confirmedBooking: null,
  };

  const fmtVND = (n) => Math.round(Number(n) || 0).toLocaleString('vi-VN') + ' đ';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  function formatCountdownMs(deadlineMs) {
    const rem = Math.max(0, Math.floor((Number(deadlineMs || 0) - Date.now()) / 1000));
    const m = String(Math.floor(rem / 60)).padStart(2, '0');
    const s = String(rem % 60).padStart(2, '0');
    return { rem, text: rem > 0 ? `${m}:${s}` : '00:00 (Hết hạn)' };
  }

  let otpTimerHandle = null;
  function startInlineOtpTicker() {
    if (otpTimerHandle) clearInterval(otpTimerHandle);
    if (!state.forgotDeadlineMs) return;
    const tick = () => {
      const el = document.getElementById('iaOtpCountdown');
      if (!el) return;
      const { rem, text } = formatCountdownMs(state.forgotDeadlineMs);
      el.textContent = text;
      if (rem <= 0) {
        el.classList.add('text-rose-700');
        clearInterval(otpTimerHandle);
      }
    };
    setTimeout(tick, 0);
    otpTimerHandle = setInterval(tick, 1000);
  }

  function nextDayStr(dateStr) {
    const d = new Date((dateStr || TODAY_STR) + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() + 1);
    return d.toISOString().slice(0, 10);
  }
  if (!state.checkOut || state.checkOut <= state.checkIn) {
    state.checkOut = nextDayStr(state.checkIn);
  }

  function checkGuestCapacity(cat) {
    if (!cat) return { ok: true, msg: '' };
    const rc = Math.max(1, Number(state.roomsCount) || 1);
    const adults = Number(state.adults) || 0;
    const children = Number(state.children) || 0;
    const maxAdPerRoom = Number(cat.max_adults || 2);
    const maxChPerRoom = cat.max_children !== undefined && cat.max_children !== null ? Number(cat.max_children) : 1;
    const maxAdults = maxAdPerRoom * rc;
    const maxTotal = (maxAdPerRoom + maxChPerRoom) * rc;

    if (adults < 1 || children < 0) {
      return { ok: false, msg: 'Số lượng khách không hợp lệ (tối thiểu 1 người lớn).' };
    }
    if (adults > maxAdults || (adults + children) > maxTotal) {
      return {
        ok: false,
        msg: `Quá sức chứa (${rc} phòng ${cat.name} tối đa ${maxAdults} người lớn, tổng ${maxTotal} khách; bạn đang chọn ${adults} NL${children > 0 ? ', ' + children + ' TE' : ''}). Vui lòng giảm số khách hoặc tăng số phòng.`
      };
    }
    return { ok: true, msg: '' };
  }

  function calcNights() {
    const d1 = new Date(state.checkIn + 'T00:00:00');
    const d2 = new Date(state.checkOut + 'T00:00:00');
    const diff = Math.round((d2 - d1) / 86400000);
    return diff > 0 ? diff : 1;
  }

  function getSelectedCat() {
    return BOOT.categories.find(c => Number(c.id) === Number(state.selectedCatId)) || null;
  }

  function getPackagesForCat(cat) {
    if (cat && Array.isArray(cat.resolved_packages) && cat.resolved_packages.length > 0) {
      return cat.resolved_packages;
    }
    return BOOT.packages;
  }

  function getSelectedPkg() {
    const cat = getSelectedCat();
    const list = getPackagesForCat(cat);
    return list.find(p => Number(p.id) === Number(state.selectedPackageId)) || list[0];
  }

  function getSelectedAddonItems() {
    const allAddons = [...BOOT.comboServices, ...BOOT.prepServices];
    const items = [];
    for (const s of allAddons) {
      const qty = Number(state.addonQuantities[s.id] || 0);
      if (qty > 0) {
        const unitPrice = Number(s.effective_price ?? s.price);
        items.push({
          service_id: Number(s.id),
          code: s.code,
          name: s.name,
          category: s.category,
          unit_label: s.unit_label,
          quantity: qty,
          unit_price: unitPrice,
          total_price: unitPrice * qty,
        });
      }
    }
    return items;
  }

  function computePricing() {
    const cat = getSelectedCat();
    const pkg = getSelectedPkg();
    const nights = calcNights();
    const rc = Math.max(1, Number(state.roomsCount) || 1);
    const nightly = cat ? Number(cat.effective_base_price || cat.base_price) : 0;
    const pkgExtra = pkg ? Number(pkg.resolved_extra_per_night ?? pkg.extra_per_night ?? 0) : 0;
    const unitNightly = pkg && pkg.resolved_nightly_price ? Number(pkg.resolved_nightly_price) : (nightly + pkgExtra);
    const roomSub = unitNightly * nights * rc;

    const addonItems = getSelectedAddonItems();
    const addonSub = addonItems.reduce((sum, i) => sum + i.total_price, 0);
    const preDiscount = roomSub + addonSub;

    let discountAmount = 0;
    if (state.appliedVoucher && state.appliedVoucher.voucher) {
      const v = state.appliedVoucher.voucher;
      if (preDiscount >= Number(v.min_order_amount || 0)) {
        if (v.discount_type === 'percent') {
          discountAmount = Math.round(preDiscount * (Number(v.discount_value) / 100));
          const maxCap = Number(v.max_discount_amount || 0);
          if (maxCap > 0 && discountAmount > maxCap) discountAmount = maxCap;
        } else {
          discountAmount = Number(v.discount_value);
        }
        discountAmount = Math.min(discountAmount, preDiscount);
      }
    }

    const pol = BOOT.policies || {};
    const taxRatePct = Number(pol.tax_service_percent || 13.4);
    const depPct = Number(pol.deposit_percent || 30);
    const taxable = Math.max(0, preDiscount - discountAmount);
    const taxTotal = Math.round(taxable * (taxRatePct / 100));
    const serviceCharge5 = Math.round(taxable * 0.05);
    const vat8 = Math.max(0, taxTotal - serviceCharge5);
    const total = taxable + taxTotal;
    const deposit30 = Math.round(total * (depPct / 100));
    const dueNow = state.paymentPlan === 'full_100' ? total : deposit30;
    const remainingAtHotel = Math.max(0, total - dueNow);

    return {
      nights, rc, nightly, pkgExtra, unitNightly, roomSub, addonItems, addonSub,
      preDiscount, discountAmount, taxable, taxTotal, serviceCharge5, vat8,
      total, deposit30, depPct, taxRatePct, dueNow, remainingAtHotel,
    };
  }

  function updateHeaderAndStepper() {
    const hdr = document.getElementById('catalogHeader');
    const bar = document.getElementById('wizardProgressBar');
    const grid = document.getElementById('mainBookingGrid');
    const s4 = document.getElementById('wizardStep4');

    if (state.step === 0) {
      hdr.classList.remove('hidden');
      bar.classList.add('hidden');
      grid.classList.remove('hidden');
      s4.classList.add('hidden');
    } else if (state.step >= 1 && state.step <= 3) {
      hdr.classList.add('hidden');
      bar.classList.remove('hidden');
      grid.classList.remove('hidden');
      s4.classList.add('hidden');
    } else {
      hdr.classList.add('hidden');
      bar.classList.remove('hidden');
      grid.classList.add('hidden');
      s4.classList.remove('hidden');
      document.getElementById('btnExitWizard').classList.add('hidden');
    }

    if (state.step >= 1 && state.step <= 3) {
      document.getElementById('btnExitWizard').classList.remove('hidden');
    }

    for (let i = 1; i <= 4; i++) {
      const el = document.getElementById('stepPill' + i);
      const circle = el.querySelector('span:first-child');
      if (i < state.step) {
        el.className = 'flex items-center gap-2 text-emerald-300';
        circle.className = 'w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold bg-emerald-500 text-white';
        circle.textContent = '✓';
      } else if (i === state.step) {
        el.className = 'flex items-center gap-2 text-[#E2C27D]';
        circle.className = 'w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold bg-[#C9A45C] text-[#0E1D33]';
        circle.textContent = String(i);
      } else {
        el.className = 'flex items-center gap-2 text-slate-400';
        circle.className = 'w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold border border-slate-500';
        circle.textContent = String(i);
      }
    }

    document.getElementById('catalogListView').classList.toggle('hidden', state.step !== 0);
    document.getElementById('wizardStep1').classList.toggle('hidden', state.step !== 1);
    document.getElementById('wizardStep2').classList.toggle('hidden', state.step !== 2);
    document.getElementById('wizardStep3').classList.toggle('hidden', state.step !== 3);
  }

  function syncFilterControlsUI() {
    const ci = document.getElementById('fltCheckIn');
    const co = document.getElementById('fltCheckOut');
    const rc = document.getElementById('fltRoomsCount');
    const ad = document.getElementById('fltAdults');
    const ch = document.getElementById('fltChildren');
    if (ci) { ci.min = TODAY_STR; if (ci.value !== state.checkIn) ci.value = state.checkIn; }
    if (co) { co.min = nextDayStr(state.checkIn); if (co.value !== state.checkOut) co.value = state.checkOut; }
    if (rc && Number(rc.value) !== state.roomsCount) rc.value = String(state.roomsCount);
    if (ad && Number(ad.value) !== state.adults) ad.value = String(state.adults);
    if (ch && Number(ch.value) !== state.children) ch.value = String(state.children);

    document.querySelectorAll('#fltTypeButtons button[data-ftype]').forEach((btn) => {
      const active = btn.getAttribute('data-ftype') === state.filterType;
      btn.className = 'px-3 py-1.5 rounded-lg font-bold border transition-colors ' +
        (active ? 'border-[#0E1D33] bg-[#0E1D33] text-white' : 'border-[#E6DFD1] bg-white text-slate-600 hover:border-[#C9A45C]');
    });

    const hdrDates = document.getElementById('hdrDates');
    if (hdrDates) hdrDates.textContent = `${state.checkIn} → ${state.checkOut}`;
  }

  function renderCatalogCards() {
    const container = document.getElementById('roomCardsContainer');
    if (!container) return;
    syncFilterControlsUI();

    let list = [...BOOT.categories];
    if (state.filterType === 'suite') {
      list = list.filter(c => /suite|presidential/i.test(`${c.code || ''} ${c.name || ''} ${c.subtitle || ''}`));
    } else if (state.filterType === 'room') {
      list = list.filter(c => !/suite|presidential/i.test(`${c.code || ''} ${c.name || ''} ${c.subtitle || ''}`));
    }
    if (state.filterView === 'city') {
      list = list.filter(c => /phố|city/i.test(c.view_label || ''));
    } else if (state.filterView === 'ocean') {
      list = list.filter(c => /biển|ocean|sea/i.test(c.view_label || ''));
    } else if (state.filterView === 'panorama') {
      list = list.filter(c => /panorama|tổng thống|360/i.test(`${c.view_label || ''} ${c.name || ''}`));
    }
    if (state.sortBy === 'price_asc') {
      list.sort((a, b) => Number(a.effective_base_price || a.base_price) - Number(b.effective_base_price || b.base_price));
    } else if (state.sortBy === 'price_desc') {
      list.sort((a, b) => Number(b.effective_base_price || b.base_price) - Number(a.effective_base_price || a.base_price));
    }

    if (list.length === 0) {
      container.innerHTML = `
        <div class="bg-white border border-[#E6DFD1] rounded-xl p-10 text-center space-y-2">
          <p class="font-serif text-2xl text-[#0E1D33]">Không tìm thấy hạng phòng khớp bộ lọc hiện tại</p>
          <p class="text-xs text-slate-500">Vui lòng thử bỏ chọn bộ lọc hướng nhìn hoặc chọn "Tất cả" hạng phòng.</p>
          <button type="button" onclick="window.__mtApp.resetFilters()" class="mt-2 px-4 py-2 bg-[#C9A45C] text-[#0E1D33] text-xs font-bold uppercase rounded-lg">Đặt lại bộ lọc</button>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map(cat => {
      const effPrice = Number(cat.effective_base_price || cat.base_price);
      const basePrice = Number(cat.base_price);
      const hasPromo = Boolean(cat.has_active_promo) && effPrice < basePrice;
      const isStopSell = Boolean(cat.is_stop_sell);
      const avail = Number(cat.available_count || 0);
      const notEnoughRooms = avail < Math.max(1, Number(state.roomsCount) || 1);
      const cap = checkGuestCapacity(cat);
      const canBook = !isStopSell && !notEnoughRooms && cap.ok;
      const maxCh = cat.max_children !== undefined && cat.max_children !== null ? Number(cat.max_children) : 1;

      const amenitiesHtml = (cat.amenities || []).map(a =>
        `<span class="px-2 py-0.5 bg-[#FAF7F0] border border-[#EFE9DC] rounded text-[11px] text-slate-700">✓ ${esc(a)}</span>`
      ).join('');

      return `
        <div class="bg-white rounded-xl overflow-hidden border transition-all border-[#E6DFD1] ${isStopSell || notEnoughRooms ? 'opacity-75' : 'hover:border-[#C9A45C]'}">
          <div class="grid grid-cols-1 md:grid-cols-12">
            <div class="md:col-span-5 relative min-h-[240px]">
              <img src="${esc(cat.image_url)}" alt="${esc(cat.name)}" class="w-full h-full object-cover" />
              <span class="absolute top-3 left-3 px-2.5 py-1 bg-[#0E1D33]/90 text-white text-[10px] font-bold tracking-wider uppercase rounded">${esc(cat.view_label)}</span>
              ${hasPromo ? `<span class="absolute top-3 right-3 px-2.5 py-1 bg-[#C9A45C] text-[#0E1D33] text-[10px] font-bold uppercase rounded shadow">${esc(cat.active_promo_label || 'ƯU ĐÃI')}</span>` : ''}
              <span class="absolute bottom-3 left-3 px-2.5 py-1 ${isStopSell ? 'bg-rose-600 text-white' : 'bg-white/95 text-[#0E1D33]'} text-[11px] font-bold rounded shadow">${isStopSell ? 'Đang đóng bán' : 'Còn ' + avail + ' phòng trống'}</span>
            </div>
            <div class="md:col-span-7 p-5 flex flex-col justify-between">
              <div>
                <p class="text-[10px] font-bold tracking-wider text-[#A8843E] uppercase mb-1">${esc(cat.subtitle)}</p>
                <h3 class="font-serif text-2xl font-semibold text-[#0E1D33] mb-1.5">${esc(cat.name)}</h3>
                <p class="text-xs text-slate-600 mb-3">${esc(cat.description)}</p>
                <div class="grid grid-cols-3 gap-2 text-xs text-slate-700 bg-[#FAF7F0] border border-[#EFE9DC] p-2.5 rounded-lg mb-3">
                  <div><span class="text-slate-400">Diện tích:</span> <strong>${Number(cat.area_sqm)}m²</strong></div>
                  <div><span class="text-slate-400">Sức chứa:</span> <strong>${Number(cat.max_adults)} NL, ${maxCh} TE</strong></div>
                  <div><span class="text-slate-400">Giường:</span> <strong>${esc(cat.bed_label)}</strong></div>
                </div>
                <div class="flex flex-wrap gap-1.5 mb-3">${amenitiesHtml}</div>
                ${!cap.ok ? `<div class="p-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-medium mb-3">${esc(cap.msg)}</div>` : ''}
                ${cap.ok && notEnoughRooms && !isStopSell ? `<div class="p-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-medium mb-3">Chỉ còn ${avail} phòng trống (bạn đang chọn ${state.roomsCount} phòng).</div>` : ''}
              </div>
              <div class="pt-3 border-t border-[#EFE9DC] flex flex-wrap items-center justify-between gap-3">
                <div>
                  <div class="text-[10px] font-bold text-slate-400 uppercase">GIÁ TIÊU CHUẨN TỪ:</div>
                  ${hasPromo ? `<div class="text-xs text-slate-400 line-through tabular-nums">${fmtVND(basePrice)}</div>` : ''}
                  <div class="font-serif text-2xl font-bold text-[#A8843E] tabular-nums">${fmtVND(effPrice)} <span class="text-xs font-sans font-normal text-slate-500">/ đêm</span></div>
                </div>
                ${isStopSell ? `<span class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-slate-200 text-slate-500">TẠM NGỪNG BÁN</span>` : `
                <button type="button" onclick="window.__mtApp.selectCategory(${cat.id})" ${canBook ? '' : 'disabled'}
                  class="px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors ${canBook ? 'bg-[#0E1D33] hover:bg-[#162C4D] text-white' : 'bg-slate-200 text-slate-500 cursor-not-allowed'}">
                  ${!cap.ok ? 'QUÁ SỨC CHỨA' : (notEnoughRooms ? 'KHÔNG ĐỦ PHÒNG' : 'CHỌN PHÒNG & TIẾP TỤC →')}
                </button>`}
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderStep1() {
    const el = document.getElementById('wizardStep1');
    const cat = getSelectedCat();
    if (!cat) return;
    const pol = BOOT.policies || {};
    const effPrice = Number(cat.effective_base_price || cat.base_price);
    const pkgs = getPackagesForCat(cat);
    const gallery = (cat.gallery && cat.gallery.length) ? cat.gallery : [cat.image_url];
    const activeImg = gallery[state.activePhotoIdx] || cat.image_url;
    const cap = checkGuestCapacity(cat);
    const maxCh = cat.max_children !== undefined && cat.max_children !== null ? Number(cat.max_children) : 1;

    el.innerHTML = `
      <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 sm:p-6 space-y-5">
        <div class="relative rounded-xl overflow-hidden aspect-[16/9] bg-slate-100">
          <img src="${esc(activeImg)}" alt="${esc(cat.name)}" class="w-full h-full object-cover" />
          <span class="absolute top-3 left-3 px-3 py-1 bg-[#0E1D33]/90 text-white text-[11px] font-bold uppercase rounded">${esc(cat.view_label)}</span>
        </div>
        <div class="grid grid-cols-4 gap-3">
          ${gallery.slice(0, 4).map((imgUrl, idx) => `
            <button type="button" onclick="window.__mtApp.setPhoto(${idx})"
              class="rounded-lg overflow-hidden aspect-[16/10] border-2 ${idx === state.activePhotoIdx ? 'border-[#C9A45C]' : 'border-transparent opacity-75 hover:opacity-100'}">
              <img src="${esc(imgUrl)}" class="w-full h-full object-cover" />
            </button>
          `).join('')}
        </div>
        <div>
          <h2 class="font-serif text-3xl font-semibold text-[#0E1D33] mb-4">${esc(cat.name)}</h2>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 bg-[#FAF7F0] border border-[#EFE9DC] rounded-xl p-4 text-xs">
            <div><div class="text-[10px] font-bold text-slate-400 uppercase">DIỆN TÍCH</div><div class="font-semibold mt-0.5">${Number(cat.area_sqm)} m²</div></div>
            <div><div class="text-[10px] font-bold text-slate-400 uppercase">GIƯỜNG</div><div class="font-semibold mt-0.5">${esc(cat.bed_label)}</div></div>
            <div><div class="text-[10px] font-bold text-slate-400 uppercase">SỨC CHỨA / PHÒNG</div><div class="font-semibold mt-0.5">${Number(cat.max_adults)} NL, ${maxCh} TE</div></div>
            <div><div class="text-[10px] font-bold text-slate-400 uppercase">HƯỚNG NHÌN</div><div class="font-semibold mt-0.5">${esc(cat.view_label)}</div></div>
            <div><div class="text-[10px] font-bold text-slate-400 uppercase">NHẬN / TRẢ PHÒNG</div><div class="font-semibold mt-0.5">${esc(pol.checkin_time || '14:00')} / ${esc(pol.checkout_time || '12:00')}</div></div>
          </div>
          ${!cap.ok ? `<div class="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">⚠ ${esc(cap.msg)}</div>` : ''}
          <p class="text-xs text-slate-600 leading-relaxed mt-4">${esc(cat.description)}</p>
        </div>
      </div>

      <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 sm:p-6 space-y-4">
        <h3 class="font-serif text-2xl font-semibold text-[#0E1D33]">Chọn gói nghỉ dưỡng</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          ${pkgs.map(p => {
            const pSel = Number(state.selectedPackageId) === Number(p.id);
            const nightly = p.resolved_nightly_price ? Number(p.resolved_nightly_price) : (effPrice + Number(p.extra_per_night));
            const incHtml = (p.inclusions || []).map(inc => `<li class="flex items-start gap-1.5 text-[11px] text-slate-600"><span class="text-emerald-600">✓</span><span>${esc(inc)}</span></li>`).join('');
            return `
              <button type="button" onclick="window.__mtApp.selectPackage(${p.id})"
                class="text-left p-4 rounded-xl border flex flex-col justify-between transition-all ${pSel ? 'border-[#C9A45C] ring-2 ring-[#C9A45C]/30 bg-[#FCFBF8]' : 'border-[#E6DFD1] hover:border-[#C9A45C]/60'}">
                <div>
                  <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="w-4 h-4 rounded-full border flex items-center justify-center ${pSel ? 'border-[#0E1D33] bg-[#0E1D33]' : 'border-slate-300'}">
                      ${pSel ? '<span class="w-1.5 h-1.5 rounded-full bg-white"></span>' : ''}
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800">${esc(p.badge_label)}</span>
                  </div>
                  <div class="font-bold text-sm text-[#0E1D33] mb-1">${esc(p.name)}</div>
                  <p class="text-[11px] text-slate-500 mb-3">${esc(p.description)}</p>
                  <ul class="space-y-1 mb-4">${incHtml}</ul>
                </div>
                <div class="pt-3 border-t border-[#EFE9DC] flex items-center justify-between text-xs">
                  <span class="text-slate-500">Giá mỗi đêm:</span>
                  <strong class="font-serif text-base text-[#A8843E] tabular-nums">${fmtVND(nightly)}</strong>
                </div>
              </button>
            `;
          }).join('')}
        </div>
      </div>

      <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 sm:p-6 space-y-2">
        <h3 class="font-serif text-2xl font-semibold text-[#0E1D33]">Chính sách đặt/hủy</h3>
        <p class="text-xs text-slate-600">Số đêm tối thiểu 1, tối đa 14 · Đặt trước tối đa 365 ngày · Nhận phòng ${esc(pol.checkin_time || '14:00')}, trả phòng ${esc(pol.checkout_time || '12:00')}.</p>
        <p class="text-xs text-slate-600">Đặt cọc ${esc(pol.deposit_percent || '30')}% khi đặt phòng. Hủy miễn phí trước ${esc(pol.free_cancel_days || '14')} ngày nhận phòng; sau đó phí ${esc(pol.late_cancel_fee_percent || '50')}% giá đêm đầu.</p>
      </div>
    `;
  }

  function renderStep2() {
    const el = document.getElementById('wizardStep2');
    const isCustomerLoggedIn = state.authUser && state.authUser.role === 'customer';
    const isStaffLoggedIn = state.authUser && state.authUser.role !== 'customer';

    let memberSectionHtml = '';
    if (isCustomerLoggedIn) {
      memberSectionHtml = `
        <div class="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-5 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <div class="text-xs font-bold text-emerald-800 flex items-center gap-1.5">
                ✓ Đã xác thực Hội viên: ${esc(state.authUser.full_name)}
              </div>
              <p class="text-[11px] text-emerald-700 mt-0.5">Thông tin cố định theo tài khoản Hội viên đã đăng ký để bảo vệ lịch sử đơn & voucher.</p>
            </div>
            <a href="/logout" class="text-[11px] font-semibold text-[#0E1D33] underline">Đổi tài khoản khác</a>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Họ và tên khách hàng (Đã khóa)</label>
              <input type="text" readonly value="${esc(state.authUser.full_name)}" class="w-full px-3 py-2 border border-emerald-200 rounded-lg bg-slate-100 text-slate-700 cursor-not-allowed" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Số điện thoại (Đã khóa)</label>
              <input type="text" readonly value="${esc(state.authUser.phone)}" class="w-full px-3 py-2 border border-emerald-200 rounded-lg bg-slate-100 text-slate-700 cursor-not-allowed" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Email nhận hóa đơn & xác nhận (Đã khóa)</label>
              <input type="email" readonly value="${esc(state.authUser.email)}" class="w-full px-3 py-2 border border-emerald-200 rounded-lg bg-slate-100 text-slate-700 cursor-not-allowed" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Số CCCD / Hộ chiếu (Tùy chọn điền trước)</label>
              <input type="text" id="inpGuestIdNumber" value="${esc(state.guestIdNumber)}" oninput="window.__mtApp.setField('guestIdNumber', this.value)" placeholder="VD: 079092001234" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
            </div>
          </div>
        </div>
      `;
    } else {
      const tab = state.inlineAuthTab || 'register';
      const eyeBtn = (id) => `
        <button type="button" onclick="window.__mtApp.togglePwd('${id}', this)" aria-label="Hiện/ẩn mật khẩu"
          class="absolute inset-y-0 right-0 px-2.5 flex items-center text-slate-400 hover:text-[#0E1D33]">
          <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
        </button>`;
      memberSectionHtml = `
        <div class="bg-[#FCF8EE] border border-[#E2C27D] rounded-2xl p-5 space-y-4">
          ${isStaffLoggedIn ? `<div class="p-2.5 rounded-lg bg-amber-100 text-amber-900 text-xs">Bạn đang đăng nhập tài khoản Nhân viên (${esc(state.authUser.role)}). Vui lòng đăng nhập hoặc đăng ký tài khoản Khách hàng để đặt phòng.</div>` : ''}
          <div>
            <div class="font-serif text-xl font-semibold text-[#0E1D33]">♛ Tài khoản Hội viên đặt phòng</div>
            <p class="text-xs text-slate-600 mt-0.5">Đăng ký mới, đăng nhập hoặc khôi phục mật khẩu ngay tại bước này mà không làm mất lựa chọn phòng.</p>
          </div>
          <div class="inline-flex flex-wrap rounded-lg border border-[#E6DFD1] bg-white p-1 text-xs">
            <button type="button" onclick="window.__mtApp.setInlineAuthTab('register')" class="px-3.5 py-1.5 rounded-md font-semibold ${tab === 'register' ? 'bg-[#0E1D33] text-white' : 'text-slate-600'}">Đăng ký Hội viên mới</button>
            <button type="button" onclick="window.__mtApp.setInlineAuthTab('login')" class="px-3.5 py-1.5 rounded-md font-semibold ${tab === 'login' || tab === 'forgot' ? 'bg-[#0E1D33] text-white' : 'text-slate-600'}">Đã có tài khoản</button>
          </div>
          <div id="inlineAuthErr" class="hidden p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs"></div>
          ${tab === 'register' ? `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
              <div>
                <label class="block font-semibold mb-1">Họ và tên khách hàng *</label>
                <input type="text" id="iaName" oninput="window.__mtApp.validateStep2RegField('full_name')" placeholder="VD: Lê Minh Khách" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
                <p id="iaErrName" class="hidden text-[11px] text-rose-600 mt-1"></p>
              </div>
              <div>
                <label class="block font-semibold mb-1">Số điện thoại *</label>
                <input type="tel" id="iaPhone" oninput="window.__mtApp.validateStep2RegField('phone')" placeholder="0912345678" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
                <p id="iaErrPhone" class="hidden text-[11px] text-rose-600 mt-1"></p>
              </div>
              <div>
                <label class="block font-semibold mb-1">Email nhận hóa đơn &amp; xác nhận *</label>
                <input type="email" id="iaEmail" oninput="window.__mtApp.validateStep2RegField('email')" placeholder="khachhang@gmail.com" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
                <p id="iaErrEmail" class="hidden text-[11px] text-rose-600 mt-1"></p>
              </div>
              <div>
                <label class="block font-semibold mb-1">Số CCCD / Hộ chiếu / Số thẻ</label>
                <input type="text" id="iaIdNum" value="${esc(state.guestIdNumber)}" oninput="window.__mtApp.setField('guestIdNumber', this.value)" placeholder="Điền hoặc xuất trình tại quầy" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
              </div>
              <div>
                <label class="block font-semibold mb-1">Mật khẩu * (≥ 6 ký tự, gồm chữ và số)</label>
                <div class="relative">
                  <input type="password" id="iaPass" oninput="window.__mtApp.validateStep2RegField('password')" class="w-full px-3 py-2 pr-10 border border-[#E6DFD1] rounded-lg bg-white" />
                  ${eyeBtn('iaPass')}
                </div>
                <p id="iaErrPass" class="hidden text-[11px] text-rose-600 mt-1"></p>
              </div>
              <div>
                <label class="block font-semibold mb-1">Xác nhận lại mật khẩu *</label>
                <div class="relative">
                  <input type="password" id="iaPass2" oninput="window.__mtApp.validateStep2RegField('confirm_password')" class="w-full px-3 py-2 pr-10 border border-[#E6DFD1] rounded-lg bg-white" />
                  ${eyeBtn('iaPass2')}
                </div>
                <p id="iaErrPass2" class="hidden text-[11px] text-rose-600 mt-1"></p>
              </div>
            </div>
            <button type="button" onclick="window.__mtApp.submitInlineAuth('register')" class="w-full py-3 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold uppercase tracking-wider rounded-xl">
              ✓ ĐĂNG KÝ HỘI VIÊN &amp; TIẾP TỤC ĐẶT PHÒNG
            </button>
          ` : tab === 'login' ? `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
              <div><label class="block font-semibold mb-1">Email hoặc Số điện thoại *</label><input type="text" id="iaLoginId" value="khach@muongthanh.vn" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" /></div>
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="block font-semibold">Mật khẩu *</label>
                  <button type="button" onclick="window.__mtApp.setInlineAuthTab('forgot')" class="text-[11px] font-semibold text-[#A8843E] hover:underline">Quên mật khẩu?</button>
                </div>
                <div class="relative">
                  <input type="password" id="iaLoginPass" value="123456" class="w-full px-3 py-2 pr-10 border border-[#E6DFD1] rounded-lg bg-white" />
                  ${eyeBtn('iaLoginPass')}
                </div>
              </div>
            </div>
            <button type="button" onclick="window.__mtApp.submitInlineAuth('login')" class="w-full py-3 bg-[#0E1D33] hover:bg-[#162C4D] text-white text-xs font-bold uppercase tracking-wider rounded-xl">
              ĐĂNG NHẬP HỘI VIÊN &amp; TIẾP TỤC ĐẶT PHÒNG
            </button>
          ` : `
            <div class="space-y-3 text-xs">
              <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-[#E6DFD1]">
                <strong class="text-[#0E1D33]">Khôi phục mật khẩu Hội viên (Cấp mã 6 số)</strong>
                <button type="button" onclick="window.__mtApp.setInlineAuthTab('login')" class="text-[11px] font-semibold text-[#A8843E] hover:underline">← Quay lại Đăng nhập</button>
              </div>
              ${state.forgotDoneMsg ? `<div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold">${esc(state.forgotDoneMsg)}</div>` : ''}
              <div class="flex flex-col sm:flex-row gap-2">
                <input type="text" id="iaFgIdentifier" value="${esc(state.forgotIdentifier)}" placeholder="Nhập Email hoặc Số điện thoại đã đăng ký *" class="flex-1 px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
                <button type="button" onclick="window.__mtApp.requestStep2ForgotCode()" class="px-4 py-2 bg-[#0E1D33] text-white font-bold uppercase rounded-lg whitespace-nowrap">Lấy mã 6 số</button>
              </div>
              ${state.forgotCodeDisplay ? `
                <div class="p-3 bg-emerald-50 border border-emerald-300 rounded-xl text-center space-y-1">
                  <div class="text-[10px] font-bold uppercase text-emerald-800">Mã xác nhận cho ${esc(state.forgotAccountName || 'Hội viên')}</div>
                  <div class="font-mono text-2xl font-bold tracking-[0.26em] text-[#0E1D33] tabular-nums select-all">${esc(state.forgotCodeDisplay)}</div>
                  <div class="text-[11px] text-emerald-700">
                    Mã có hiệu lực trong <strong id="iaOtpCountdown" class="font-mono font-bold tabular-nums">${esc(formatCountdownMs(state.forgotDeadlineMs).text)}</strong>. Nhập mã 6 số và đặt mật khẩu mới bên dưới:
                  </div>
                  <div class="pt-1">
                    <button type="button" onclick="const c=document.getElementById('iaFgCode'); if(c){c.value='${esc(state.forgotCodeDisplay)}';} document.getElementById('iaFgNewPass')?.focus();" class="px-2.5 py-1 rounded bg-white border border-emerald-400 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100">
                      Điền nhanh mã OTP này ↓
                    </button>
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                  <div>
                    <label class="block font-semibold mb-1">Mã 6 số *</label>
                    <input type="text" id="iaFgCode" maxlength="24" inputmode="numeric" oninput="this.value=this.value.replace(/\D+/g,'').slice(0,6)" placeholder="000000" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white font-mono" />
                  </div>
                  <div>
                    <label class="block font-semibold mb-1">Mật khẩu mới *</label>
                    <div class="relative">
                      <input type="password" id="iaFgNewPass" placeholder="≥ 6 ký tự, chữ & số" class="w-full px-3 py-2 pr-10 border border-[#E6DFD1] rounded-lg bg-white" />
                      ${eyeBtn('iaFgNewPass')}
                    </div>
                  </div>
                  <div>
                    <label class="block font-semibold mb-1">Xác nhận MK mới *</label>
                    <div class="relative">
                      <input type="password" id="iaFgNewPass2" placeholder="Nhập lại mật khẩu" class="w-full px-3 py-2 pr-10 border border-[#E6DFD1] rounded-lg bg-white" />
                      ${eyeBtn('iaFgNewPass2')}
                    </div>
                  </div>
                </div>
                <button type="button" onclick="window.__mtApp.submitStep2ForgotReset()" class="w-full py-2.5 bg-[#C9A45C] text-[#0E1D33] font-bold uppercase rounded-xl">
                  Xác nhận đặt lại mật khẩu
                </button>
              ` : ''}
            </div>
          `}
        </div>
      `;
    }

    const voucherChips = BOOT.vouchers
      .filter(v => (v.scope || 'all') !== 'service')
      .map(v => {
        const applied = state.appliedVoucher && state.appliedVoucher.voucher && state.appliedVoucher.voucher.code === v.code;
        return `<button type="button" onclick="window.__mtApp.applyVoucher('${esc(v.code)}')"
          class="px-2.5 py-1 rounded border text-[11px] font-mono font-bold transition-colors ${applied ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white border-[#E2C27D] text-[#A8843E] hover:bg-[#FAF6EC]'}">
          🏷 ${esc(v.code)}
        </button>`;
      }).join('');

    const combosHtml = BOOT.comboServices.map(c => {
      const qty = Number(state.addonQuantities[c.id] || 0);
      const isChecked = qty > 0;
      const origPrice = Number(c.price);
      const effPrice = Number(c.effective_price ?? c.price);
      const hasPromo = Boolean(c.has_active_promo) && effPrice < origPrice;
      const promoLabel = c.active_promo_tag || (hasPromo ? c.promo_tag : '');
      return `
        <div class="p-4 rounded-xl border transition-all flex items-start justify-between gap-4 ${isChecked ? 'border-[#C9A45C] bg-[#FCFBF8]' : 'border-[#E6DFD1] bg-white'}">
          <div class="flex items-start gap-3">
            <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="window.__mtApp.toggleCombo(${c.id}, this.checked)" class="mt-1 w-4 h-4 accent-[#0E1D33]" />
            <div>
              <div class="flex items-center gap-2">
                <strong class="text-xs text-[#0E1D33]">${esc(c.name)}</strong>
                ${promoLabel ? `<span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 text-[10px] font-bold">${esc(promoLabel)}</span>` : ''}
              </div>
              <p class="text-[11px] text-slate-500 mt-0.5">${esc(c.description)}</p>
            </div>
          </div>
          <div class="text-right shrink-0">
            ${hasPromo ? `<div class="text-[10px] text-slate-400 line-through tabular-nums">${fmtVND(origPrice)}</div>` : ''}
            <div class="text-xs font-bold text-[#0E1D33] tabular-nums">${fmtVND(effPrice)}</div>
          </div>
        </div>
      `;
    }).join('');

    const prepsHtml = BOOT.prepServices.map(p => {
      const qty = Number(state.addonQuantities[p.id] || 0);
      const price = Number(p.effective_price ?? p.price);
      return `
        <div class="p-3.5 rounded-xl border border-[#E6DFD1] bg-white flex items-center justify-between gap-4">
          <div>
            <div class="text-xs font-semibold text-[#0E1D33]">${esc(p.name)}</div>
            <div class="text-[11px] text-slate-500">${esc(p.description)} · <strong class="text-[#A8843E] tabular-nums">${fmtVND(price)}</strong> ${esc(p.unit_label)}</div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button type="button" onclick="window.__mtApp.stepAddon(${p.id}, -1)" class="w-7 h-7 rounded border border-[#E6DFD1] bg-[#FAF7F0] font-bold">−</button>
            <span class="w-6 text-center text-xs font-bold tabular-nums">${qty}</span>
            <button type="button" onclick="window.__mtApp.stepAddon(${p.id}, 1)" class="w-7 h-7 rounded border border-[#E6DFD1] bg-[#FAF7F0] font-bold">+</button>
          </div>
        </div>
      `;
    }).join('');

    el.innerHTML = `
      <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 sm:p-6 space-y-5">
        <div>
          <h2 class="font-serif text-3xl font-semibold text-[#0E1D33]">Thông tin người lưu trú & Hội viên</h2>
          <p class="text-xs text-slate-600 mt-1">Vui lòng kiểm tra thông tin Hội viên, áp dụng mã giảm giá và chọn dịch vụ chuẩn bị trước.</p>
        </div>

        ${memberSectionHtml}

        <!-- Mã giảm giá & Voucher -->
        <div class="bg-[#FAF7F0] border border-[#E6DFD1] rounded-2xl p-5 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-xs font-bold text-[#A8843E] uppercase">🏷 MÃ GIẢM GIÁ & VOUCHER NGHỈ DƯỠNG</div>
            <span class="text-[11px] text-slate-500">Áp dụng 1 lần / tài khoản vào tổng tiền phòng & đặt cọc</span>
          </div>
          <div class="flex flex-wrap gap-1.5">${voucherChips}</div>
          <div class="flex gap-2">
            <input type="text" id="inpVoucherCode" value="${esc(state.voucherInput)}" oninput="window.__mtApp.setField('voucherInput', this.value)" placeholder="NHẬP MÃ GIẢM GIÁ (VD: MUONGTHANH10, SUITEVIP...)" class="flex-1 px-3 py-2 text-xs border border-[#E6DFD1] rounded-lg bg-white font-mono uppercase" />
            <button type="button" onclick="window.__mtApp.applyVoucher()" class="px-4 py-2 bg-[#0E1D33] text-white text-xs font-bold uppercase rounded-lg">Áp dụng</button>
            ${state.appliedVoucher ? `<button type="button" onclick="window.__mtApp.clearVoucher()" class="px-3 py-2 border border-rose-200 text-rose-700 text-xs font-semibold rounded-lg bg-white">Gỡ mã</button>` : ''}
          </div>
          ${state.appliedVoucher ? `<div class="text-xs text-emerald-700 font-semibold">✓ Đã áp dụng mã ${esc(state.appliedVoucher.voucher.code)}: Giảm ${fmtVND(state.appliedVoucher.discount_amount)}</div>` : ''}
          ${state.voucherError ? `<div class="text-xs text-rose-600">${esc(state.voucherError)}</div>` : ''}
        </div>

        <!-- Giờ đến dự kiến, Chuyến bay & Yêu cầu đặc biệt (Không có tự chọn phòng vật lý) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Giờ đến dự kiến</label>
            <input type="time" value="${esc(state.arrivalTime)}" oninput="window.__mtApp.setField('arrivalTime', this.value)" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Chuyến bay (nếu cần đưa đón)</label>
            <input type="text" value="${esc(state.flightNumber)}" oninput="window.__mtApp.setField('flightNumber', this.value)" placeholder="VD: VJ123" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white uppercase" />
          </div>
        </div>

        <div class="p-3 bg-[#FAF7F0] border border-[#EFE9DC] rounded-xl text-xs text-slate-700">
          Khách: <strong>${state.adults} người lớn${state.children > 0 ? `, ${state.children} trẻ em` : ''}</strong>. Trẻ dưới ${esc((BOOT.policies || {}).free_child_max_age || '6')} tuổi miễn phí; trẻ lớn hơn và giường phụ áp dụng phụ phí theo chính sách khách sạn. Lễ tân sẽ sắp xếp phòng vật lý phù hợp khi xác nhận đơn.
        </div>

        <div class="text-xs">
          <label class="block font-semibold text-slate-700 mb-1">Yêu cầu đặc biệt (Tùy chọn)</label>
          <textarea rows="2" oninput="window.__mtApp.setField('specialRequests', this.value)" placeholder="VD: Phòng tầng cao, gần thang máy, phòng không hút thuốc, kỷ niệm ngày cưới..." class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white">${esc(state.specialRequests)}</textarea>
        </div>

        <!-- Combo dịch vụ ưu đãi -->
        <div class="space-y-3 pt-2">
          <div>
            <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">Combo dịch vụ ưu đãi</h3>
            <p class="text-[11px] text-slate-500">Đặt kèm combo ngay lúc giữ phòng để hưởng mức giá tiết kiệm hơn 8%–12%.</p>
          </div>
          <div class="space-y-2.5">${combosHtml}</div>
        </div>

        <!-- Tùy chọn cần chuẩn bị trước (Không có gia hạn) -->
        <div class="space-y-3 pt-2">
          <div>
            <h3 class="font-serif text-xl font-semibold text-[#0E1D33]">Tùy chọn cần chuẩn bị trước</h3>
            <p class="text-[11px] text-slate-500">Các dịch vụ hỗ trợ đón tiễn và chuẩn bị phòng sẵn sàng cho suốt kỳ nghỉ.</p>
          </div>
          <div class="space-y-2.5">${prepsHtml}</div>
        </div>

        <!-- Lưu ý dịch vụ lẻ -->
        <div class="bg-[#F9F6EE] border border-[#E6DFD1] rounded-xl p-4 text-xs text-slate-700">
          <div class="font-bold text-[#A8843E] uppercase mb-1">★ LƯU Ý VỀ DỊCH VỤ LẺ: ẨM THỰC, SPA & TRẢI NGHIỆM</div>
          <p class="leading-relaxed">Các món ẩm thực gọi lẻ, đồ uống tại Rooftop Sky Lounge và liệu trình đơn lẻ tại Pearl Spa không đặt trước tại bước này. Sau khi nhận phòng (Check-in), Quý khách sử dụng Mã QR tại phòng hoặc mục <strong>"Đơn của tôi"</strong> để gọi phục vụ bất cứ lúc nào.</p>
        </div>
      </div>
    `;
  }

  function renderStep3() {
    const el = document.getElementById('wizardStep3');
    const p = computePricing();
    const pol = BOOT.policies || {};
    const bankName = pol.bank_name || 'Vietcombank — CN Phú Quốc';
    const bankAcc = pol.bank_account_no || '0121 000 889 999';
    const bankHolder = pol.bank_account_holder || 'KHÁCH SẠN MƯỜNG THANH LUXURY PHÚ QUỐC';
    const isDep = state.paymentPlan === 'deposit_30';
    const isBank = state.paymentMethod === 'bank_transfer';

    el.innerHTML = `
      <div class="bg-white border border-[#E6DFD1] rounded-2xl p-5 sm:p-6 space-y-6">
        <div>
          <h2 class="font-serif text-3xl font-semibold text-[#0E1D33]">Thanh toán & Đặt cọc giữ phòng</h2>
          <p class="text-xs text-slate-600 mt-1">Lựa chọn mức thanh toán cọc ${p.depPct}% hoặc 100% và kiểm tra kỹ chi tiết hóa đơn bên phải.</p>
        </div>

        <div class="space-y-3">
          <div class="text-xs font-bold uppercase text-[#A8843E]">1. Chọn mức thanh toán</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <button type="button" onclick="window.__mtApp.setPaymentPlan('deposit_30')"
              class="text-left p-4 rounded-xl border ${isDep ? 'border-[#C9A45C] ring-2 ring-[#C9A45C]/30 bg-[#FCFBF8]' : 'border-[#E6DFD1] bg-white'}">
              <div class="font-bold text-sm text-[#0E1D33] mb-1">Đặt cọc ${p.depPct}% giữ phòng</div>
              <div class="text-slate-500 mb-2">Phần còn lại thanh toán khi làm thủ tục Check-out tại khách sạn.</div>
              <div class="font-serif text-xl font-bold text-[#A8843E] tabular-nums">${fmtVND(p.deposit30)}</div>
            </button>
            <button type="button" onclick="window.__mtApp.setPaymentPlan('full_100')"
              class="text-left p-4 rounded-xl border ${!isDep ? 'border-[#C9A45C] ring-2 ring-[#C9A45C]/30 bg-[#FCFBF8]' : 'border-[#E6DFD1] bg-white'}">
              <div class="font-bold text-sm text-[#0E1D33] mb-1">Thanh toán toàn bộ 100%</div>
              <div class="text-slate-500 mb-2">Nhận phòng nhanh ưu tiên, không phải thanh toán tiền phòng tại quầy.</div>
              <div class="font-serif text-xl font-bold text-[#0E1D33] tabular-nums">${fmtVND(p.total)}</div>
            </button>
          </div>
        </div>

        <div class="space-y-3">
          <div class="text-xs font-bold uppercase text-[#A8843E]">2. Phương thức thanh toán</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <button type="button" onclick="window.__mtApp.setPaymentMethod('bank_transfer')"
              class="text-left p-4 rounded-xl border ${isBank ? 'border-[#C9A45C] ring-2 ring-[#C9A45C]/30 bg-[#FCFBF8]' : 'border-[#E6DFD1] bg-white'}">
              <div class="font-bold text-[#0E1D33]">Chuyển khoản ngân hàng (VietQR)</div>
              <div class="text-slate-500 mt-1">${esc(bankName)} · STK: <strong>${esc(bankAcc)}</strong></div>
            </button>
            <button type="button" onclick="window.__mtApp.setPaymentMethod('card')"
              class="text-left p-4 rounded-xl border ${!isBank ? 'border-[#C9A45C] ring-2 ring-[#C9A45C]/30 bg-[#FCFBF8]' : 'border-[#E6DFD1] bg-white'}">
              <div class="font-bold text-[#0E1D33]">Thẻ tín dụng / Ghi nợ quốc tế</div>
              <div class="text-slate-500 mt-1">Visa, Mastercard, JCB (Thanh toán trực tuyến mô phỏng)</div>
            </button>
          </div>

          ${isBank ? `
            <div class="bg-[#FAF7F0] border border-[#E6DFD1] rounded-xl p-4 grid grid-cols-1 sm:grid-cols-12 gap-4 items-center text-xs">
              <div class="sm:col-span-4 flex flex-col items-center justify-center bg-white p-3 rounded-lg border border-[#EFE9DC]">
                <svg viewBox="0 0 80 80" class="w-24 h-24 text-[#0E1D33]" fill="currentColor">
                  <rect x="4" y="4" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="11" y="11" width="10" height="10"/>
                  <rect x="52" y="4" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="59" y="11" width="10" height="10"/>
                  <rect x="4" y="52" width="24" height="24" fill="none" stroke="currentColor" stroke-width="4"/><rect x="11" y="59" width="10" height="10"/>
                  <rect x="35" y="10" width="8" height="8"/><rect x="35" y="24" width="8" height="14"/><rect x="10" y="36" width="16" height="8"/>
                  <rect x="34" y="44" width="16" height="12"/><rect x="56" y="36" width="14" height="10"/><rect x="54" y="54" width="20" height="20" fill="none" stroke="currentColor" stroke-width="4"/>
                </svg>
                <span class="text-[10px] font-mono text-slate-500 mt-1">VietQR · ${esc(bankName)}</span>
              </div>
              <div class="sm:col-span-8 space-y-1.5">
                <div>Ngân hàng: <strong>${esc(bankName)}</strong></div>
                <div>Chủ tài khoản: <strong>${esc(bankHolder)}</strong></div>
                <div>Số tài khoản: <strong class="font-mono text-[#0E1D33]">${esc(bankAcc)}</strong></div>
                <div>Số tiền cần thanh toán ngay: <strong class="text-base text-[#A8843E] tabular-nums">${fmtVND(p.dueNow)}</strong></div>
                <div class="text-[11px] text-slate-500">Nội dung CK: <span class="font-mono font-semibold text-[#0E1D33]">DATPHONG ${esc(state.authUser?.phone || '')}</span></div>
              </div>
            </div>
          ` : `
            <div class="bg-[#FAF7F0] border border-[#E6DFD1] rounded-xl p-4 space-y-3 text-xs">
              <div class="flex items-center justify-between">
                <div class="font-bold text-[#0E1D33] uppercase text-[11px]">Thông tin thẻ thanh toán quốc tế (Mô phỏng)</div>
                <span class="px-2 py-0.5 rounded bg-white border border-[#E6DFD1] font-mono text-[10px] text-slate-600">VISA · MASTERCARD · JCB</span>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block font-semibold text-slate-700 mb-1">Tên chủ thẻ (In hoa không dấu) *</label>
                  <input type="text" value="${esc(state.cardHolder)}" oninput="window.__mtApp.setField('cardHolder', this.value)" placeholder="VD: LE MINH KHACH" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white uppercase" />
                </div>
                <div>
                  <label class="block font-semibold text-slate-700 mb-1">Số thẻ *</label>
                  <input type="text" value="${esc(state.cardNumber)}" oninput="window.__mtApp.setField('cardNumber', this.value)" placeholder="4242 4242 4242 4242" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white font-mono" />
                </div>
                <div>
                  <label class="block font-semibold text-slate-700 mb-1">Ngày hết hạn (MM/YY)</label>
                  <input type="text" value="${esc(state.cardExpiry)}" oninput="window.__mtApp.setField('cardExpiry', this.value)" placeholder="12/28" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white font-mono" />
                </div>
                <div>
                  <label class="block font-semibold text-slate-700 mb-1">Mã bảo mật (CVV/CVC)</label>
                  <input type="password" value="${esc(state.cardCvv)}" oninput="window.__mtApp.setField('cardCvv', this.value)" placeholder="•••" class="w-full px-3 py-2 border border-[#E6DFD1] rounded-lg bg-white font-mono" />
                </div>
              </div>
              <p class="text-[11px] text-slate-500">Số tiền thanh toán ngay <strong class="text-[#A8843E]">${fmtVND(p.dueNow)}</strong> được ghi nhận qua cổng thanh toán trực tuyến và lưu trữ trực tiếp vào CSDL MySQL.</p>
            </div>
          `}
        </div>

        <label class="flex items-start gap-2.5 text-xs text-slate-700 pt-2">
          <input type="checkbox" ${state.agreedTerms ? 'checked' : ''} onchange="window.__mtApp.setField('agreedTerms', this.checked)" class="mt-0.5 w-4 h-4 accent-[#0E1D33]" />
          <span>Tôi đã đọc, kiểm tra đầy đủ chi tiết dịch vụ & thuế trên hóa đơn dự kiến và đồng ý với Chính sách đặt/hủy của Khách sạn Mường Thanh Luxury Phú Quốc.</span>
        </label>

        ${state.submitError ? `<div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">${esc(state.submitError)}</div>` : ''}
      </div>
    `;
  }

  function renderStep4() {
    const el = document.getElementById('wizardStep4');
    const b = state.confirmedBooking;
    if (!b) return;
    const disc = Number(b.discount_amount || 0);
    const preTax = Math.max(0, Number(b.room_subtotal) + Number(b.addon_subtotal || 0) - disc);
    const svc5 = Math.round(preTax * 0.05);
    const vat8 = Math.max(0, Number(b.tax_amount) - svc5);
    const remaining = Math.max(0, Number(b.total_estimated) - Number(b.total_paid || 0));

    el.innerHTML = `
      <div class="max-w-3xl mx-auto bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-xl">
        <div class="bg-[#0E1D33] text-white px-6 py-7 text-center space-y-2">
          <div class="w-12 h-12 mx-auto rounded-full bg-emerald-500/20 border border-emerald-400 flex items-center justify-center text-emerald-300 text-2xl">✓</div>
          <p class="text-[11px] font-bold tracking-[0.2em] text-[#E2C27D] uppercase">ĐƠN ĐẶT PHÒNG ĐÃ ĐƯỢC GHI NHẬN TRÊN HỆ THỐNG MYSQL</p>
          <h2 class="font-serif text-3xl sm:text-4xl font-semibold">Phiếu Xác Nhận Đặt Phòng</h2>
          <div class="inline-block px-4 py-1.5 rounded-full bg-white/10 border border-white/20 text-xs mt-1">
            Mã đặt phòng: <strong class="font-mono text-[#E2C27D] text-sm">${esc(b.booking_code)}</strong> · <span class="text-amber-200">Chờ Lễ tân đối soát & xác nhận</span>
          </div>
        </div>

        <div class="p-6 sm:p-8 space-y-6 text-xs">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-[#FAF7F0] border border-[#EFE9DC] rounded-xl p-4">
            <div class="space-y-1.5">
              <div class="text-[10px] font-bold text-[#A8843E] uppercase">THÔNG TIN KHÁCH LƯU TRÚ</div>
              <div>Họ tên: <strong>${esc(b.guest_name)}</strong></div>
              <div>Điện thoại: <strong>${esc(b.guest_phone)}</strong></div>
              <div>Email: <strong>${esc(b.guest_email)}</strong></div>
              ${b.guest_id_number ? `<div>CCCD / Hộ chiếu: <strong>${esc(b.guest_id_number)}</strong></div>` : ''}
            </div>
            <div class="space-y-1.5">
              <div class="text-[10px] font-bold text-[#A8843E] uppercase">CHI TIẾT KỲ NGHỈ</div>
              <div>Hạng phòng: <strong>${esc(b.category_name)}</strong> (${Number(b.rooms_count)} phòng)</div>
              <div>Gói dịch vụ: <strong>${esc(b.package_name)}</strong></div>
              <div>Lưu trú: <strong>${esc(b.check_in_date)} → ${esc(b.check_out_date)}</strong> (${Number(b.nights)} đêm)</div>
              ${b.arrival_time || b.flight_number ? `<div>Giờ đến / Chuyến bay: <strong>${esc(b.arrival_time || '—')} · ${esc(b.flight_number || '—')}</strong></div>` : ''}
            </div>
          </div>

          <div class="border border-[#E6DFD1] rounded-xl p-5 space-y-2">
            <div class="font-bold text-[#0E1D33] uppercase text-[11px] mb-2">CHI TIẾT HÓA ĐƠN DỰ KIẾN</div>
            <div class="flex justify-between"><span>Tiền phòng & gói nghỉ dưỡng:</span><span class="tabular-nums font-medium">${fmtVND(b.room_subtotal)}</span></div>
            ${(b.selected_items || []).map(it => `
              <div class="flex justify-between text-slate-700"><span>${esc(it.name)} × ${Number(it.quantity)}</span><span class="tabular-nums">${fmtVND(it.total_price)}</span></div>
            `).join('')}
            ${disc > 0 ? `<div class="flex justify-between text-emerald-700 font-semibold"><span>Giảm giá Voucher (${esc(b.voucher_code)}):</span><span class="tabular-nums">-${fmtVND(disc)}</span></div>` : ''}
            <div class="flex justify-between text-slate-600"><span>Phí dịch vụ (5%):</span><span class="tabular-nums">${fmtVND(svc5)}</span></div>
            <div class="flex justify-between text-slate-600"><span>Thuế GTGT VAT (8%):</span><span class="tabular-nums">${fmtVND(vat8)}</span></div>
            <div class="border-t border-[#E6DFD1] pt-2 mt-2 flex justify-between font-bold text-sm text-[#0E1D33]">
              <span>Tổng cộng đơn đặt phòng:</span><span class="tabular-nums">${fmtVND(b.total_estimated)}</span>
            </div>
            <div class="flex justify-between text-emerald-700 font-bold"><span>Đã ghi nhận thanh toán trực tuyến:</span><span class="tabular-nums">${fmtVND(b.total_paid)}</span></div>
            <div class="flex justify-between text-slate-600"><span>Số tiền còn lại thanh toán tại khách sạn:</span><span class="tabular-nums font-semibold">${fmtVND(remaining)}</span></div>
          </div>

          <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
            <a href="/my-bookings" class="px-6 py-3 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold uppercase tracking-wider rounded-xl">
              QUẢN LÝ ĐƠN ĐẶT PHÒNG & DỊCH VỤ MÃ QR →
            </a>
            <a href="/" class="px-6 py-3 border border-[#E6DFD1] bg-white hover:bg-[#FAF7F0] text-[#0E1D33] text-xs font-bold uppercase tracking-wider rounded-xl">
              QUAY LẠI TRANG CHỦ
            </a>
          </div>
        </div>
      </div>
    `;
  }

  function renderSidebar() {
    const aside = document.getElementById('bookingSidebar');
    const cat = getSelectedCat();
    const p = computePricing();

    // TRẠNG THÁI CHƯA CHỌN HẠNG PHÒNG (Giống Hình 2)
    if (!cat && state.step === 0) {
      aside.innerHTML = `
        <div class="bg-white border border-[#E6DFD1] rounded-2xl p-6 shadow-md space-y-5">
          <div class="flex items-center justify-between border-b border-[#EFE9DC] pb-3">
            <h3 class="font-serif text-2xl font-semibold text-[#0E1D33]">Tóm tắt đặt phòng</h3>
          </div>
          <div class="space-y-1 text-xs">
            <div class="flex items-center justify-between">
              <strong class="font-mono text-[#0E1D33]">${esc(state.checkIn)} → ${esc(state.checkOut)}</strong>
              <span class="px-2 py-0.5 rounded bg-[#FAF7F0] border border-[#E6DFD1] text-[11px]">${p.nights} đêm</span>
            </div>
            <div class="text-slate-500">${state.roomsCount} phòng · ${state.adults} người lớn${state.children > 0 ? `, ${state.children} trẻ em` : ''}</div>
          </div>
          <div class="border border-dashed border-[#D9CCB3] bg-[#FCFBF8] rounded-xl p-6 text-center space-y-2.5">
            <div class="w-11 h-11 mx-auto rounded-full bg-white border border-[#EFE9DC] flex items-center justify-center text-[#A8843E] text-lg">🛏</div>
            <div class="font-bold text-xs text-[#0E1D33]">Chưa chọn hạng phòng nào</div>
            <p class="text-[11px] text-slate-500 leading-relaxed">
              Quý khách vui lòng nhấn nút <strong>"XEM CHI TIẾT & BẢNG GIÁ GÓI"</strong> trên từng phòng bên trái để chọn gói dịch vụ và tiếp tục đặt phòng.
            </p>
          </div>
          <div class="flex items-center justify-between border-t border-[#EFE9DC] pt-3 text-xs">
            <span class="text-slate-600">Tạm tính:</span>
            <strong class="text-lg font-bold text-[#0E1D33] tabular-nums">0 đ</strong>
          </div>
          <button type="button" disabled class="w-full py-3 bg-slate-200 text-slate-400 text-xs font-bold uppercase tracking-wider rounded-xl cursor-not-allowed">
            VUI LÒNG CHỌN PHÒNG
          </button>
        </div>
      `;
      return;
    }

    if (!cat) return;
    const pkg = getSelectedPkg();

    const perPersonBlock = `
      <div class="bg-[#FAF7F0] border border-[#EFE9DC] rounded-xl p-3 text-[11px] space-y-1">
        <div class="flex justify-between">
          <span class="text-slate-600">Chi phí cụ thể / người (${state.adults} khách):</span>
          <strong class="tabular-nums text-[#0E1D33]">${fmtVND(p.total / Math.max(1, state.adults))} / người</strong>
        </div>
        <div class="flex justify-between text-slate-500">
          <span>Trung bình mỗi đêm / người:</span>
          <span class="tabular-nums">${fmtVND(p.total / Math.max(1, state.adults) / p.nights)} / đêm</span>
        </div>
      </div>
    `;

    // Chi tiết hóa đơn cụ thể (đặc biệt chi tiết ở Bước 2 & 3)
    const addonsLines = p.addonItems.map(it => `
      <div class="flex justify-between text-slate-700 text-[11px]">
        <span>${esc(it.name)} × ${it.quantity}</span>
        <span class="tabular-nums font-medium">${fmtVND(it.total_price)}</span>
      </div>
    `).join('');

    const taxBreakdownLines = state.step >= 2 ? `
      <div class="flex justify-between text-slate-500 text-[11px]"><span>Phí dịch vụ khách sạn (5%)</span><span class="tabular-nums">${fmtVND(p.serviceCharge5)}</span></div>
      <div class="flex justify-between text-slate-500 text-[11px]"><span>Thuế GTGT VAT (8%)</span><span class="tabular-nums">${fmtVND(p.vat8)}</span></div>
    ` : `
      <div class="flex justify-between text-slate-500 text-[11px]"><span>Phí dịch vụ &amp; thuế (${p.taxRatePct}%)</span><span class="tabular-nums">${fmtVND(p.taxTotal)}</span></div>
    `;

    const cap = checkGuestCapacity(cat);
    const overCapBox = !cap.ok ? `<div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-semibold">⚠ ${esc(cap.msg)}</div>` : '';

    let ctaButtonsHtml = '';
    if (state.step === 0 || state.step === 1) {
      const canNext1 = cap.ok;
      ctaButtonsHtml = `
        <button type="button" onclick="window.__mtApp.goStep(${state.step === 0 ? 1 : 2})" ${canNext1 ? '' : 'disabled'}
          class="w-full py-3 ${canNext1 ? 'bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33]' : 'bg-slate-200 text-slate-400 cursor-not-allowed'} text-xs font-bold uppercase tracking-wider rounded-xl shadow">
          ${!canNext1 ? 'VƯỢT QUÁ SỨC CHỨA PHÒNG' : (state.step === 0 ? 'TIẾP TỤC' : 'TIẾP TỤC: NHẬP THÔNG TIN →')}
        </button>
      `;
    } else if (state.step === 2) {
      const canAuth = state.authUser && state.authUser.role === 'customer';
      const canNext = canAuth && cap.ok;
      ctaButtonsHtml = `
        <div class="space-y-2">
          <button type="button" onclick="window.__mtApp.goStep(3)" ${canNext ? '' : 'disabled'}
            class="w-full py-3 ${canNext ? 'bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33]' : 'bg-slate-200 text-slate-400 cursor-not-allowed'} text-xs font-bold uppercase tracking-wider rounded-xl">
            ${!cap.ok ? 'VƯỢT QUÁ SỨC CHỨA PHÒNG' : (canAuth ? 'TIẾP TỤC: THANH TOÁN →' : 'VUI LÒNG ĐĂNG KÝ / ĐĂNG NHẬP HỘI VIÊN')}
          </button>
          <button type="button" onclick="window.__mtApp.goStep(1)" class="w-full py-2 border border-[#E6DFD1] text-slate-600 text-xs font-semibold rounded-xl">
            ← Quay lại Bước 1 (Chọn phòng / gói)
          </button>
        </div>
      `;
    } else if (state.step === 3) {
      ctaButtonsHtml = `
        <div class="space-y-2">
          <button type="button" onclick="window.__mtApp.submitBooking()" ${state.isSubmitting ? 'disabled' : ''}
            class="w-full py-3 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold uppercase tracking-wider rounded-xl shadow">
            ${state.isSubmitting ? 'ĐANG XỬ LÝ...' : 'XÁC NHẬN ĐẶT PHÒNG & THANH TOÁN'}
          </button>
          <button type="button" onclick="window.__mtApp.goStep(2)" class="w-full py-2 border border-[#E6DFD1] text-slate-600 text-xs font-semibold rounded-xl">
            ← Quay lại Bước 2 (Thông tin &amp; Combo)
          </button>
        </div>
      `;
    }

    aside.innerHTML = `
      <div class="bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-lg">
        <div class="bg-[#0E1D33] text-white px-5 py-4 flex items-center justify-between">
          <h3 class="font-serif text-2xl font-semibold">Kỳ nghỉ của bạn</h3>
          ${state.step === 0 ? `<button type="button" onclick="window.__mtApp.clearCategory()" class="text-[11px] text-slate-300 hover:text-white underline">Bỏ chọn</button>` : ''}
        </div>
        <div class="p-5 space-y-4 text-xs">
          <div>
            <div class="font-bold text-sm text-[#0E1D33]">${esc(cat.name)}</div>
            <div class="text-[#A8843E] font-semibold mt-0.5">${esc(pkg ? pkg.name : '')} · ${state.roomsCount} phòng</div>
          </div>

          ${state.step <= 1 ? `
            <div class="grid grid-cols-2 gap-2.5">
              <div><label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nhận phòng</label><input type="date" min="${TODAY_STR}" value="${esc(state.checkIn)}" onchange="window.__mtApp.setField('checkIn', this.value)" class="w-full px-2.5 py-1.5 border border-[#E6DFD1] rounded-lg" /></div>
              <div><label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Trả phòng</label><input type="date" min="${nextDayStr(state.checkIn)}" value="${esc(state.checkOut)}" onchange="window.__mtApp.setField('checkOut', this.value)" class="w-full px-2.5 py-1.5 border border-[#E6DFD1] rounded-lg" /></div>
              <div><label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Người lớn</label><input type="number" min="1" value="${state.adults}" onchange="window.__mtApp.setField('adults', Number(this.value))" class="w-full px-2.5 py-1.5 border border-[#E6DFD1] rounded-lg" /></div>
              <div><label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Trẻ em</label><input type="number" min="0" value="${state.children}" onchange="window.__mtApp.setField('children', Number(this.value))" class="w-full px-2.5 py-1.5 border border-[#E6DFD1] rounded-lg" /></div>
            </div>
            ${overCapBox}
          ` : `
            <div class="text-slate-600 font-mono text-[11px] bg-[#FAF7F0] p-2.5 rounded-lg border border-[#EFE9DC]">
              ${esc(state.checkIn)} → ${esc(state.checkOut)} (${p.nights} đêm · ${state.roomsCount} phòng)
            </div>
            ${overCapBox}
          `}

          <div class="border-t border-[#EFE9DC] pt-3 space-y-1.5">
            <div class="text-[10px] font-bold uppercase text-slate-400">${state.step >= 3 ? 'CHI TIẾT HÓA ĐƠN DỰ KIẾN' : 'CHI PHÍ PHÒNG & DỊCH VỤ'}</div>
            <div class="flex justify-between">
              <span>${fmtVND(p.unitNightly)} × ${p.nights} đêm × ${p.rc} phòng</span>
              <span class="tabular-nums font-medium">${fmtVND(p.roomSub)}</span>
            </div>
            ${addonsLines}
            ${p.discountAmount > 0 ? `<div class="flex justify-between text-emerald-700 font-semibold"><span>Voucher (${esc(state.appliedVoucher.voucher.code)})</span><span class="tabular-nums">-${fmtVND(p.discountAmount)}</span></div>` : ''}
            ${taxBreakdownLines}
          </div>

          <div class="border-t border-[#EFE9DC] pt-3 space-y-1">
            <div class="flex items-baseline justify-between">
              <span class="font-bold text-sm text-[#0E1D33]">Tổng cộng</span>
              <span class="font-serif text-2xl font-bold text-[#0E1D33] tabular-nums">${fmtVND(p.total)}</span>
            </div>
            ${state.step === 3 ? `
              <div class="flex justify-between text-[#A8843E] font-bold"><span>Thanh toán ngay (${state.paymentPlan === 'full_100' ? '100%' : 'Cọc ' + p.depPct + '%'}):</span><span class="tabular-nums">${fmtVND(p.dueNow)}</span></div>
              <div class="flex justify-between text-slate-500"><span>Còn lại thanh toán tại KS:</span><span class="tabular-nums">${fmtVND(p.remainingAtHotel)}</span></div>
            ` : `
              <div class="flex justify-between text-[#A8843E] font-semibold"><span>Tiền cọc (${p.depPct}%)</span><span class="tabular-nums">${fmtVND(p.deposit30)}</span></div>
            `}
          </div>

          ${perPersonBlock}
          ${ctaButtonsHtml}
          <div class="text-center text-[11px] text-emerald-700">✓ Miễn phí hủy đến ${esc((BOOT.policies || {}).free_cancel_days || '14')} ngày trước khi nhận phòng</div>
        </div>
      </div>
    `;
  }

  function renderAll() {
    updateHeaderAndStepper();
    if (state.step === 0) renderCatalogCards();
    if (state.step === 1) renderStep1();
    if (state.step === 2) renderStep2();
    if (state.step === 3) renderStep3();
    if (state.step === 4) renderStep4();
    if (state.step < 4) renderSidebar();
  }

  function step2SetErr(inputId, errId, msg) {
    const inp = document.getElementById(inputId);
    const err = document.getElementById(errId);
    if (!inp || !err) return !msg;
    if (msg) {
      inp.classList.add('border-rose-500', 'bg-rose-50/30');
      inp.classList.remove('border-[#E6DFD1]');
      err.textContent = msg;
      err.classList.remove('hidden');
      return false;
    }
    inp.classList.remove('border-rose-500', 'bg-rose-50/30');
    inp.classList.add('border-[#E6DFD1]');
    err.textContent = '';
    err.classList.add('hidden');
    return true;
  }

  window.__mtApp = {
    selectCategory(id) {
      const cat = BOOT.categories.find(c => Number(c.id) === Number(id));
      if (!cat) return;
      const cap = checkGuestCapacity(cat);
      if (!cap.ok) return;
      state.selectedCatId = Number(id);
      const pkgs = getPackagesForCat(cat);
      if (pkgs.length > 0) {
        state.selectedPackageId = Number(pkgs[0].id);
      }
      state.activePhotoIdx = 0;
      state.step = 1;
      renderAll();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    clearCategory() {
      state.selectedCatId = null;
      renderAll();
    },
    selectPackage(pkgId) {
      state.selectedPackageId = Number(pkgId);
      renderAll();
    },
    setPhoto(idx) {
      state.activePhotoIdx = idx;
      renderStep1();
    },
    setFilter(kind, value) {
      if (kind === 'type') state.filterType = value;
      if (kind === 'view') state.filterView = value;
      if (kind === 'sort') state.sortBy = value;
      renderCatalogCards();
    },
    resetFilters() {
      state.filterType = 'all';
      state.filterView = 'all';
      state.sortBy = 'default';
      const vs = document.getElementById('fltViewSelect');
      const ss = document.getElementById('fltSortSelect');
      if (vs) vs.value = 'all';
      if (ss) ss.value = 'default';
      renderCatalogCards();
    },
    goStep(nextStep) {
      const cat = getSelectedCat();
      if (nextStep >= 1 && !cat) return;
      if (nextStep >= 2 && cat && !checkGuestCapacity(cat).ok) return;
      if (nextStep === 3 && (!state.authUser || state.authUser.role !== 'customer')) return;
      state.step = nextStep;
      renderAll();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    setField(key, val) {
      if (key === 'checkIn') {
        let ci = String(val || TODAY_STR);
        if (ci < TODAY_STR) ci = TODAY_STR;
        state.checkIn = ci;
        if (!state.checkOut || state.checkOut <= ci) state.checkOut = nextDayStr(ci);
        renderAll();
        return;
      }
      if (key === 'checkOut') {
        let co = String(val || nextDayStr(state.checkIn));
        if (co <= state.checkIn) co = nextDayStr(state.checkIn);
        state.checkOut = co;
        renderAll();
        return;
      }
      if (key === 'roomsCount') {
        state.roomsCount = 1;
        renderAll();
        return;
      }
      if (key === 'adults') {
        state.adults = Math.max(1, Number(val) || 1);
        renderAll();
        return;
      }
      if (key === 'children') {
        state.children = Math.max(0, Number(val) || 0);
        renderAll();
        return;
      }
      state[key] = val;
    },
    setInlineAuthTab(tab) {
      state.inlineAuthTab = tab;
      state.forgotDoneMsg = '';
      renderStep2();
    },
    togglePwd(inputId, btn) {
      const inp = document.getElementById(inputId);
      if (!inp) return;
      const isHidden = inp.type === 'password';
      inp.type = isHidden ? 'text' : 'password';
      if (btn) {
        const eo = btn.querySelector('.eye-open');
        const ec = btn.querySelector('.eye-closed');
        if (eo) eo.classList.toggle('hidden', isHidden);
        if (ec) ec.classList.toggle('hidden', !isHidden);
      }
    },
    validateStep2RegField(field) {
      const nEl = document.getElementById('iaName');
      const phEl = document.getElementById('iaPhone');
      const emEl = document.getElementById('iaEmail');
      const p1El = document.getElementById('iaPass');
      const p2El = document.getElementById('iaPass2');
      if (!nEl) return true;

      const checkName = () => step2SetErr('iaName', 'iaErrName', nEl.value.trim().length < 2 ? 'Họ và tên phải có ít nhất 2 ký tự.' : '');
      const checkPhone = () => {
        const d = phEl.value.replace(/\D+/g, '');
        return step2SetErr('iaPhone', 'iaErrPhone', (d.length < 9 || d.length > 15) ? 'Số điện thoại cần 9–15 chữ số.' : '');
      };
      const checkEmail = () => step2SetErr('iaEmail', 'iaErrEmail', !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emEl.value.trim()) ? 'Email không đúng định dạng.' : '');
      const checkPass = () => {
        const pw = p1El.value;
        let msg = '';
        if (pw.length < 6) msg = 'Mật khẩu phải có ít nhất 6 ký tự.';
        else if (!/[A-Za-z]/.test(pw) || !/\d/.test(pw)) msg = 'Mật khẩu phải gồm cả chữ cái và chữ số.';
        const ok = step2SetErr('iaPass', 'iaErrPass', msg);
        if (p2El && p2El.value.length > 0) checkPass2();
        return ok;
      };
      const checkPass2 = () => {
        let msg = '';
        if (!p2El.value) msg = 'Vui lòng nhập lại mật khẩu để xác nhận.';
        else if (p2El.value !== p1El.value) msg = 'Mật khẩu xác nhận không trùng khớp.';
        return step2SetErr('iaPass2', 'iaErrPass2', msg);
      };

      if (field === 'full_name') return checkName();
      if (field === 'phone') return checkPhone();
      if (field === 'email') return checkEmail();
      if (field === 'password') return checkPass();
      if (field === 'confirm_password') return checkPass2();
      return [checkName(), checkPhone(), checkEmail(), checkPass(), checkPass2()].every(Boolean);
    },
    async requestStep2ForgotCode() {
      const errBox = document.getElementById('inlineAuthErr');
      errBox.classList.add('hidden');
      const ident = (document.getElementById('iaFgIdentifier')?.value || '').trim();
      state.forgotIdentifier = ident;
      state.forgotDoneMsg = '';
      if (!ident) {
        errBox.textContent = 'Vui lòng nhập Email hoặc Số điện thoại đã đăng ký.';
        errBox.classList.remove('hidden');
        return;
      }
      try {
        const res = await fetch('/forgot-password/request', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ identifier: ident }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Không tìm thấy tài khoản.');
        state.forgotCodeDisplay = data.code;
        state.forgotExpiresAt = data.expires_at;
        state.forgotDeadlineMs = Date.now() + (Number(data.expires_in_seconds || 900) * 1000);
        state.forgotAccountName = data.account?.full_name || '';
        renderStep2();
        startInlineOtpTicker();
      } catch (err) {
        errBox.textContent = err.message;
        errBox.classList.remove('hidden');
      }
    },
    async submitStep2ForgotReset() {
      const errBox = document.getElementById('inlineAuthErr');
      errBox.classList.add('hidden');
      const code = (document.getElementById('iaFgCode')?.value || '').replace(/\D+/g, '').slice(0, 6);
      const newPassword = document.getElementById('iaFgNewPass')?.value || '';
      const confirmPassword = document.getElementById('iaFgNewPass2')?.value || '';
      try {
        const res = await fetch('/forgot-password/reset', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            identifier: state.forgotIdentifier,
            code,
            new_password: newPassword,
            confirm_password: confirmPassword,
          }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Không thể đặt lại mật khẩu.');
        state.forgotCodeDisplay = '';
        state.forgotDoneMsg = data.message || 'Đặt lại mật khẩu thành công!';
        state.inlineAuthTab = 'login';
        renderStep2();
      } catch (err) {
        errBox.textContent = err.message;
        errBox.classList.remove('hidden');
      }
    },
    async submitInlineAuth(action) {
      const errBox = document.getElementById('inlineAuthErr');
      errBox.classList.add('hidden');
      let body = { action };
      if (action === 'register') {
        if (!window.__mtApp.validateStep2RegField('all')) {
          errBox.textContent = 'Vui lòng kiểm tra các trường báo đỏ ở trên trước khi đăng ký.';
          errBox.classList.remove('hidden');
          return;
        }
        state.guestIdNumber = document.getElementById('iaIdNum').value.trim();
        body = {
          action: 'register',
          full_name: document.getElementById('iaName').value.trim(),
          phone: document.getElementById('iaPhone').value.trim(),
          email: document.getElementById('iaEmail').value.trim(),
          password: document.getElementById('iaPass').value,
          confirm_password: document.getElementById('iaPass2').value,
        };
      } else {
        body = {
          action: 'login',
          identifier: document.getElementById('iaLoginId').value.trim(),
          password: document.getElementById('iaLoginPass').value,
        };
      }
      try {
        const res = await fetch('/api/auth/inline', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Xác thực thất bại.');
        state.authUser = data.user;
        renderAll();
      } catch (err) {
        errBox.textContent = err.message;
        errBox.classList.remove('hidden');
      }
    },
    async applyVoucher(codeOverride) {
      const code = (codeOverride || state.voucherInput || '').trim().toUpperCase();
      state.voucherInput = code;
      state.voucherError = '';
      if (!code) {
        state.voucherError = 'Vui lòng nhập mã giảm giá.';
        renderStep2();
        return;
      }
      const p = computePricing();
      try {
        const res = await fetch('/api/vouchers/validate', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ code, order_amount: p.preDiscount, scope: 'room' }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Không thể áp dụng mã.');
        state.appliedVoucher = data;
        renderAll();
      } catch (err) {
        state.appliedVoucher = null;
        state.voucherError = err.message;
        renderAll();
      }
    },
    clearVoucher() {
      state.appliedVoucher = null;
      state.voucherInput = '';
      state.voucherError = '';
      renderAll();
    },
    toggleCombo(serviceId, checked) {
      state.addonQuantities[serviceId] = checked ? 1 : 0;
      renderAll();
    },
    stepAddon(serviceId, delta) {
      const cur = Number(state.addonQuantities[serviceId] || 0);
      state.addonQuantities[serviceId] = Math.max(0, cur + delta);
      renderAll();
    },
    setPaymentPlan(plan) {
      state.paymentPlan = plan;
      renderStep3();
      renderSidebar();
    },
    setPaymentMethod(method) {
      state.paymentMethod = method;
      renderStep3();
    },
    async submitBooking() {
      if (!state.agreedTerms) {
        state.submitError = 'Vui lòng xác nhận đồng ý với chính sách đặt/hủy phòng trước khi hoàn tất.';
        renderStep3();
        return;
      }
      state.isSubmitting = true;
      state.submitError = '';
      renderSidebar();

      const cat = getSelectedCat();
      const pkg = getSelectedPkg();
      const payload = {
        category_id: Number(cat.id),
        package_id: Number(pkg.id),
        check_in_date: state.checkIn,
        check_out_date: state.checkOut,
        rooms_count: state.roomsCount,
        adults: state.adults,
        children: state.children,
        guest_id_number: state.guestIdNumber,
        arrival_time: state.arrivalTime,
        flight_number: state.flightNumber,
        special_requests: state.specialRequests,
        voucher_code: state.appliedVoucher ? state.appliedVoucher.voucher.code : '',
        selected_addons: getSelectedAddonItems().map(i => ({ service_id: i.service_id, quantity: i.quantity })),
        payment_plan: state.paymentPlan,
        payment_method: state.paymentMethod,
        card_holder: state.paymentMethod === 'card' ? state.cardHolder.trim() : '',
        card_last4: state.paymentMethod === 'card' ? state.cardNumber.replace(/\s+/g, '').slice(-4) : '',
      };

      try {
        const res = await fetch('/bookings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Không thể tạo đơn đặt phòng.');
        state.confirmedBooking = data.booking;
        state.step = 4;
        state.isSubmitting = false;
        renderAll();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } catch (err) {
        state.isSubmitting = false;
        state.submitError = err.message;
        renderStep3();
        renderSidebar();
      }
    },
  };

  document.getElementById('btnExitWizard').addEventListener('click', () => {
    state.step = 0;
    renderAll();
  });

  const initialCatId = Number(BOOT.initial?.categoryId || 0);
  if (initialCatId > 0) {
    const preCat = BOOT.categories.find(c => Number(c.id) === initialCatId && !c.is_stop_sell);
    if (preCat) {
      state.selectedCatId = Number(preCat.id);
      const catPkgs = getPackagesForCat(preCat);
      if (catPkgs.length > 0) {
        state.selectedPackageId = Number(catPkgs[0].id);
      }
      state.step = 1;
    }
  }

  renderAll();
})();
</script>
