</main>
<?php if (empty($hidePublicChrome)): ?>
<?php
$catCountFooter = isset($categoriesCount) && (int) $categoriesCount > 0 ? (int) $categoriesCount : 9;
?>
<footer class="bg-[#FAF7F0] border-t border-[#E6DFD1] text-[#0E1D33] mt-20">
  <div class="max-w-[1200px] mx-auto px-6 py-14 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
    <div>
      <h3 class="font-serif text-xl font-semibold tracking-wider text-[#0E1D33]">MƯỜNG THANH LUXURY</h3>
      <p class="text-[11px] font-semibold tracking-[0.2em] text-[#A8843E] mt-1 mb-4">PHÚ QUỐC RESORT &amp; SPA ★★★★★</p>
      <p class="text-xs leading-relaxed text-slate-600 mb-4">
        Tổ hợp nghỉ dưỡng tiêu chuẩn 5 sao quốc tế tọa lạc bên bờ cát vàng mịn màng của Bãi Trường — nơi ngắm hoàng hôn rực rỡ và thơ mộng nhất đảo ngọc Phú Quốc.
      </p>
      <p class="text-xs text-slate-700">
        <span class="text-[#A8843E] font-medium">Hotline 24/7:</span>
        <strong class="tabular-nums">1900 1833 — (0297) 364 5555</strong>
      </p>
    </div>

    <div>
      <h4 class="font-serif text-base font-semibold text-[#A8843E] mb-4">Khám Phá Dịch Vụ</h4>
      <ul class="space-y-2.5 text-xs text-slate-600">
        <li><a href="/rooms" class="hover:text-[#0E1D33] transition-colors"><?= $catCountFooter ?> Hạng phòng &amp; Suite hướng biển &amp; phố</a></li>
        <li><a href="/dining" class="hover:text-[#0E1D33] transition-colors">Nhà hàng Sonasea &amp; Rooftop Sky Lounge</a></li>
        <li><a href="/spa" class="hover:text-[#0E1D33] transition-colors">Pearl Spa &amp; Massage trị liệu tinh dầu</a></li>
        <li><a href="/conferences" class="hover:text-[#0E1D33] transition-colors">Trung tâm hội nghị &amp; Grand Ballroom</a></li>
        <li><a href="/lookup" class="hover:text-[#0E1D33] transition-colors">Cổng khách hàng &amp; Đặt dịch vụ phòng QR</a></li>
      </ul>
    </div>

    <div>
      <h4 class="font-serif text-base font-semibold text-[#A8843E] mb-4">Thông Tin Liên Hệ</h4>
      <ul class="space-y-2.5 text-xs text-slate-600 leading-relaxed">
        <li><strong class="text-[#0E1D33]">Địa chỉ:</strong> Khu phức hợp Bãi Trường, Xã Dương Tơ, TP. Phú Quốc, Kiên Giang</li>
        <li><strong class="text-[#0E1D33]">Email:</strong> info@phuquoc.muongthanh.vn</li>
        <li class="tabular-nums">
          <strong class="text-[#0E1D33]">Giờ nhận phòng:</strong> <?= htmlspecialchars($policies['checkin_time'] ?? '14:00') ?> ·
          <strong class="text-[#0E1D33]">Trả phòng:</strong> <?= htmlspecialchars($policies['checkout_time'] ?? '12:00') ?>
        </li>
        <?php if (!empty($policies['member_benefits_text'])): ?>
          <li class="text-[11px] text-slate-600 pt-1">
            <strong class="text-[#0E1D33]">Quyền lợi hội viên:</strong> <?= htmlspecialchars($policies['member_benefits_text']) ?>
          </li>
        <?php endif; ?>
      </ul>
    </div>

    <div>
      <h4 class="font-serif text-base font-semibold text-[#A8843E] mb-4">Hội Viên &amp; Hỗ Trợ</h4>
      <div class="flex flex-wrap items-center gap-3 text-xs text-slate-700 mb-3">
        <a href="/login" class="hover:text-[#0E1D33] underline decoration-[#C9A45C] font-semibold">Đăng nhập / Đăng ký Hội viên</a>
        <span>·</span>
        <a href="/lookup" class="hover:text-[#0E1D33] underline decoration-[#C9A45C]">Tra cứu đơn</a>
      </div>
      <p class="text-[11px] text-slate-500 leading-relaxed">
        <?= htmlspecialchars($policies['privacy_policy_text'] ?? 'Mường Thanh Luxury Phú Quốc cam kết bảo mật tuyệt đối thông tin cá nhân và dữ liệu lưu trú của Quý khách theo tiêu chuẩn 5 sao.') ?>
      </p>
    </div>
  </div>

  <div class="border-t border-[#E6DFD1] py-5 px-6">
    <div class="max-w-[1200px] mx-auto text-[11px] text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
      <span>© 2026 Mường Thanh Luxury Phú Quốc. Bản quyền thuộc về Tập đoàn Khách sạn Mường Thanh.</span>
      <span class="flex items-center gap-3">
        <span>Hệ thống khách sạn 5 sao Bãi Trường</span>
        <a href="/login" class="text-slate-400 hover:text-[#0E1D33] underline decoration-slate-300">Cổng nội bộ</a>
      </span>
    </div>
  </div>
</footer>
<?php endif; ?>
</body>
</html>
