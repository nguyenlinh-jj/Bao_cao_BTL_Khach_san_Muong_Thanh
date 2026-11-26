<?php
$depPct = htmlspecialchars($policies['deposit_percent'] ?? '30');
$freeChildAge = (int) ($policies['free_child_max_age'] ?? 6);
$childSurcharge = (float) ($policies['child_surcharge_price'] ?? 250000);
$extraBedPrice = (float) ($policies['extra_bed_price'] ?? 650000);
$totalCatCount = isset($categoriesCount) && (int) $categoriesCount > 0 ? (int) $categoriesCount : count($featured);
?>
<section class="relative min-h-[540px] lg:min-h-[600px] flex items-center justify-center text-white overflow-hidden">
  <img src="/assets/images/hero_phu_quoc_resort_1794856695945.jpg" alt="Mường Thanh Luxury Phú Quốc" class="absolute inset-0 w-full h-full object-cover" />
  <div class="absolute inset-0 bg-gradient-to-b from-[#091526]/70 via-[#0E1D33]/55 to-[#091526]/80"></div>
  <div class="relative z-10 max-w-4xl mx-auto px-6 text-center pb-12">
    <div class="inline-block px-4 py-1.5 mb-6 border border-[#C9A45C]/50 bg-[#0E1D33]/65 text-[#E2C27D] text-[11px] font-medium tracking-[0.22em] uppercase">
      KHÁCH SẠN NĂM SAO BÊN BIỂN PHÚ QUỐC
    </div>
    <h1 class="font-serif text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight leading-[1.12] mb-5">Đón Hoàng Hôn Ngay Bên Bờ Biển</h1>
    <p class="font-serif italic text-lg sm:text-xl text-slate-200 max-w-2xl mx-auto mb-8 leading-relaxed">
      Những ngày ngập tràn nắng ấm, làn gió biển dịu nhẹ và sự phục vụ tinh tế — kỳ nghỉ trọn vẹn của bạn bắt đầu từ đây.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-4">
      <a href="/rooms" class="px-7 py-3.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold tracking-[0.15em] uppercase rounded transition-colors">KHÁM PHÁ PHÒNG NGHỈ</a>
      <a href="/dining" class="px-7 py-3.5 bg-white/10 hover:bg-white/20 border border-white/40 text-white text-xs font-bold tracking-[0.15em] uppercase rounded transition-colors">ẨM THỰC &amp; DỊCH VỤ</a>
    </div>
  </div>
</section>

<section class="relative z-30 max-w-[1040px] mx-auto px-4 -mt-14">
  <form method="GET" action="/rooms" class="bg-white border border-[#E6DFD1] rounded-xl shadow-xl">
    <input type="hidden" name="filter" value="search" />
    <div class="px-6 py-3 border-b border-[#EFE9DC] flex flex-wrap items-center justify-between gap-2">
      <span class="font-serif italic text-base text-[#0E1D33]">Lên kế hoạch cho kỳ nghỉ</span>
      <span class="text-[11px] font-semibold tracking-[0.16em] text-[#A8843E] uppercase">GIÁ TỐT NHẤT KHI ĐẶT TRỰC TIẾP</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-12 items-stretch">
      <div class="md:col-span-3 px-5 py-4 border-b md:border-b-0 md:border-r border-[#EFE9DC]">
        <label class="block text-[10px] font-bold tracking-[0.14em] text-[#A8843E] uppercase mb-1.5">NGÀY NHẬN PHÒNG</label>
        <input type="date" id="homeCheckIn" name="check_in" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($checkIn) ?>" class="w-full text-sm font-medium text-[#0E1D33] bg-transparent tabular-nums" />
      </div>
      <div class="md:col-span-3 px-5 py-4 border-b md:border-b-0 md:border-r border-[#EFE9DC]">
        <label class="block text-[10px] font-bold tracking-[0.14em] text-[#A8843E] uppercase mb-1.5">NGÀY TRẢ PHÒNG</label>
        <input type="date" id="homeCheckOut" name="check_out" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= htmlspecialchars($checkOut) ?>" class="w-full text-sm font-medium text-[#0E1D33] bg-transparent tabular-nums" />
      </div>
      <div class="md:col-span-3 px-5 py-4 border-b md:border-b-0 md:border-r border-[#EFE9DC] flex gap-2">
        <div class="flex-1">
          <label class="block text-[10px] font-bold text-[#A8843E] uppercase mb-1">PHÒNG</label>
          <input type="number" name="rooms_count" min="1" max="1" value="1" readonly title="Mỗi đơn đặt tối đa 1 phòng" class="w-full text-sm font-medium bg-transparent text-slate-500 cursor-not-allowed tabular-nums" />
        </div>
        <div class="flex-1">
          <label class="block text-[10px] font-bold text-[#A8843E] uppercase mb-1">NGƯỜI LỚN</label>
          <input type="number" name="adults" min="1" value="2" class="w-full text-sm font-medium bg-transparent tabular-nums" />
        </div>
        <div class="flex-1">
          <label class="block text-[10px] font-bold text-[#A8843E] uppercase mb-1">TRẺ EM</label>
          <input type="number" name="children" min="0" value="0" class="w-full text-sm font-medium bg-transparent tabular-nums" />
        </div>
      </div>
      <div class="md:col-span-3 flex">
        <button type="submit" class="w-full bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold tracking-[0.14em] uppercase px-6 py-4 md:rounded-br-xl transition-colors">
          KIỂM TRA PHÒNG TRỐNG
        </button>
      </div>
    </div>
    <div class="px-6 py-2.5 bg-[#FAF7F0] border-t border-[#EFE9DC] rounded-b-xl text-[11px] text-slate-600 tabular-nums">
      Trẻ em dưới <?= $freeChildAge ?> tuổi được miễn phí lưu trú cùng bố mẹ. Trẻ em từ <?= $freeChildAge ?>–12 tuổi phụ thu <?= number_format($childSurcharge, 0, ',', '.') ?> đ/đêm và giường phụ <?= number_format($extraBedPrice, 0, ',', '.') ?> đ/đêm (thanh toán tại khách sạn).
    </div>
  </form>
</section>

<section class="max-w-[1200px] mx-auto px-6 py-20 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
  <div class="lg:col-span-6">
    <p class="text-[11px] font-bold tracking-[0.2em] text-[#A8843E] uppercase mb-3">CÂU CHUYỆN KHÁCH SẠN</p>
    <h2 class="font-serif text-3xl sm:text-4xl lg:text-[42px] font-semibold leading-[1.15] text-[#0E1D33] mb-5">Nơi Giao Thoa Hoàng Hôn Bãi Trường &amp; Lòng Hiếu Khách Phương Bắc</h2>
    <div class="w-16 h-0.5 bg-[#C9A45C] mb-6"></div>
    <p class="text-sm leading-relaxed text-slate-600 mb-4">
      Tọa lạc tại vị trí đắc địa nhất Bãi Trường — bờ biển ngắm hoàng hôn đẹp nhất đảo ngọc Phú Quốc, Mường Thanh Luxury Phú Quốc mang đến không gian nghỉ dưỡng 5 sao tiêu chuẩn quốc tế kết hợp trọn vẹn giữa nét đẹp thiên nhiên nhiệt đới và sự nồng hậu, tinh tế.
    </p>
    <p class="text-sm leading-relaxed text-slate-600 mb-8">
      Hơn 500 phòng nghỉ sang trọng, hồ bơi vô cực sát biển, chuỗi ẩm thực phong phú cùng trung tâm hội nghị Grand Ballroom sức chứa 1.000 khách sẵn sàng đáp ứng mọi nhu cầu nghỉ dưỡng và hội nghị thượng đỉnh.
    </p>
    <div class="pt-6 border-t border-[#E6DFD1] grid grid-cols-3 gap-6">
      <div><div class="font-serif text-3xl font-semibold text-[#A8843E] tabular-nums">500+</div><div class="text-xs text-slate-600 mt-1">Phòng &amp; Suite 5★</div></div>
      <div><div class="font-serif text-3xl font-semibold text-[#A8843E] tabular-nums">180°</div><div class="text-xs text-slate-600 mt-1">View Biển Hoàng Hôn</div></div>
      <div><div class="font-serif text-3xl font-semibold text-[#A8843E] tabular-nums"><?= $depPct ?>%</div><div class="text-xs text-slate-600 mt-1">Cọc Linh Hoạt</div></div>
    </div>
  </div>
  <div class="lg:col-span-6 grid grid-cols-2 gap-4 items-center">
    <div class="rounded-xl overflow-hidden shadow-lg aspect-[4/3] -mt-8">
      <img src="/assets/images/room_deluxe_twin_city_1794856696423.jpg" alt="Deluxe Twin" class="w-full h-full object-cover" />
    </div>
    <div class="rounded-xl overflow-hidden shadow-lg aspect-[4/3] mt-8">
      <img src="/assets/images/room_deluxe_ocean_king_1794856700781.jpg" alt="Deluxe Ocean" class="w-full h-full object-cover" />
    </div>
  </div>
</section>

<section class="bg-white py-20 border-y border-[#EFE9DC]">
  <div class="max-w-[1200px] mx-auto px-6">
    <div class="text-center max-w-2xl mx-auto mb-12">
      <p class="text-[11px] font-bold tracking-[0.2em] text-[#A8843E] uppercase mb-2">HẠNG PHÒNG NGHỈ DƯỠNG BIỂN</p>
      <h2 class="font-serif text-3xl sm:text-4xl font-semibold text-[#0E1D33] mb-3">Không Gian Thượng Lưu Tinh Tế</h2>
      <div class="w-12 h-0.5 bg-[#C9A45C] mx-auto mb-4"></div>
      <p class="text-sm text-slate-600">
        Khám phá bộ sưu tập <?= $totalCatCount ?> hạng phòng nghỉ sang trọng, từ Deluxe ban công phố biển đến các căn Penthouse Presidential Suite xa hoa.
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
      <?php foreach ($featured as $cat):
        $effPrice = (float) ($cat['effective_base_price'] ?? $cat['base_price']);
        $basePrice = (float) $cat['base_price'];
        $hasPromo = !empty($cat['has_active_promo']) && $effPrice < $basePrice;
        $isStopSell = !empty($cat['is_stop_sell']);
      ?>
        <div class="bg-[#FAF7F0] border border-[#E6DFD1] rounded-xl overflow-hidden flex flex-col hover:shadow-lg transition-shadow">
          <div class="relative aspect-[4/3] overflow-hidden">
            <img src="<?= htmlspecialchars($cat['image_url']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="w-full h-full object-cover" />
            <div class="absolute top-3 left-3 px-2.5 py-1 bg-[#0E1D33]/90 text-white text-[10px] font-semibold tracking-wider uppercase rounded">
              <?= htmlspecialchars($cat['view_label']) ?>
            </div>
            <?php if ($isStopSell): ?>
              <span class="absolute top-3 right-3 px-2.5 py-1 bg-rose-600 text-white text-[10px] font-bold uppercase rounded">Đang đóng bán</span>
            <?php elseif ($hasPromo): ?>
              <span class="absolute top-3 right-3 px-2.5 py-1 bg-rose-600 text-white text-[10px] font-bold uppercase rounded"><?= htmlspecialchars($cat['active_promo_label'] ?: 'ƯU ĐÃI') ?></span>
            <?php endif; ?>
            <div class="absolute bottom-3 right-3 px-3 py-1 bg-white/95 text-[#0E1D33] text-xs font-bold rounded shadow-xs tabular-nums">
              <?php if ($hasPromo): ?>
                <span class="line-through text-slate-400 font-normal mr-1.5"><?= number_format($basePrice, 0, ',', '.') ?> đ</span>
              <?php endif; ?>
              Từ <?= number_format($effPrice, 0, ',', '.') ?> đ / đêm
            </div>
          </div>
          <div class="p-5 flex-1 flex flex-col">
            <p class="text-[10px] font-bold tracking-[0.14em] text-[#A8843E] uppercase mb-1"><?= htmlspecialchars($cat['subtitle']) ?></p>
            <h3 class="font-serif text-xl font-semibold text-[#0E1D33] mb-2"><?= htmlspecialchars($cat['name']) ?></h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4 flex-1"><?= htmlspecialchars($cat['description']) ?></p>
            <div class="pt-3 border-t border-[#E6DFD1] flex flex-wrap items-center gap-3 text-[11px] text-slate-600 mb-4">
              <span><?= (int) $cat['area_sqm'] ?>m²</span>
              <span>·</span>
              <span><?= (int) $cat['max_adults'] ?> Khách</span>
              <span>·</span>
              <span class="truncate max-w-[150px]"><?= htmlspecialchars($cat['bed_label']) ?></span>
            </div>
            <?php if ($isStopSell): ?>
              <span class="block text-center py-2.5 bg-slate-200 text-slate-500 text-[11px] font-bold uppercase rounded">TẠM NGỪNG NHẬN ĐẶT</span>
            <?php else: ?>
              <a href="/rooms?category_id=<?= (int) $cat['id'] ?>" class="block text-center py-2.5 bg-[#0E1D33] hover:bg-[#172E4E] text-white text-[11px] font-bold tracking-[0.12em] uppercase rounded transition-colors">
                XEM CHI TIẾT &amp; ĐẶT PHÒNG (CỌC <?= $depPct ?>%)
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-10">
      <a href="/rooms" class="inline-flex items-center gap-2 px-7 py-3.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold tracking-[0.14em] uppercase rounded transition-colors">
        XEM ĐẦY ĐỦ <?= $totalCatCount ?> HẠNG PHÒNG &amp; 3 GÓI GIÁ →
      </a>
    </div>
  </div>
</section>

<section class="max-w-[1200px] mx-auto px-6 pt-20">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <div class="relative rounded-2xl overflow-hidden aspect-[16/10] group shadow-lg">
      <img src="/assets/images/dining_sonasea_buffet_1794856700253.jpg" alt="Nhà Hàng Buffet &amp; Hải Sản Sonasea" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
      <div class="absolute inset-0 bg-gradient-to-t from-[#091526]/90 via-[#091526]/40 to-transparent"></div>
      <div class="absolute bottom-0 left-0 right-0 p-7 text-white">
        <p class="text-[11px] font-bold tracking-[0.18em] text-[#E2C27D] uppercase mb-1.5">ẨM THỰC 5 SAO</p>
        <h3 class="font-serif text-2xl sm:text-3xl font-semibold mb-2">Nhà Hàng Buffet &amp; Hải Sản Sonasea</h3>
        <p class="text-xs text-slate-200 mb-5 max-w-md leading-relaxed">
          Thưởng thức hải sản tươi sống Phú Quốc cùng buffet sáng hơn 60 món Á - Âu nhìn ra hồ bơi vô cực.
        </p>
        <a href="/dining" class="inline-block px-5 py-2.5 bg-white/15 hover:bg-white/25 border border-white/40 text-white text-[11px] font-bold tracking-[0.14em] uppercase rounded transition-colors">
          KHÁM PHÁ MENU
        </a>
      </div>
    </div>

    <div class="relative rounded-2xl overflow-hidden aspect-[16/10] group shadow-lg">
      <img src="/assets/images/spa_pearl_wellness_1794856701728.jpg" alt="Pearl Spa &amp; Xông Hơi Đá Muối" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
      <div class="absolute inset-0 bg-gradient-to-t from-[#091526]/90 via-[#091526]/40 to-transparent"></div>
      <div class="absolute bottom-0 left-0 right-0 p-7 text-white">
        <p class="text-[11px] font-bold tracking-[0.18em] text-[#E2C27D] uppercase mb-1.5">CHĂM SÓC SỨC KHỎE</p>
        <h3 class="font-serif text-2xl sm:text-3xl font-semibold mb-2">Pearl Spa &amp; Xông Hơi Đá Muối</h3>
        <p class="text-xs text-slate-200 mb-5 max-w-md leading-relaxed">
          Liệu trình trị liệu tinh dầu ngọc trai và xông hơi đá muối khoáng nóng Himalaya thư giãn tuyệt đối.
        </p>
        <a href="/spa" class="inline-block px-5 py-2.5 bg-white/15 hover:bg-white/25 border border-white/40 text-white text-[11px] font-bold tracking-[0.14em] uppercase rounded transition-colors">
          KHÁM PHÁ LIỆU TRÌNH
        </a>
      </div>
    </div>
  </div>
</section>
<script>
(() => {
  const ci = document.getElementById('homeCheckIn');
  const co = document.getElementById('homeCheckOut');
  if (!ci || !co) return;
  const syncDates = () => {
    const today = '<?= date('Y-m-d') ?>';
    if (ci.value && ci.value < today) ci.value = today;
    const d = new Date((ci.value || today) + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() + 1);
    const minOut = d.toISOString().slice(0, 10);
    co.min = minOut;
    if (!co.value || co.value <= ci.value) co.value = minOut;
  };
  ci.addEventListener('change', syncDates);
  syncDates();
})();
</script>
