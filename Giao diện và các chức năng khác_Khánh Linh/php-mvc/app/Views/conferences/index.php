<section class="relative h-[300px] flex items-center justify-center text-white overflow-hidden">
  <img src="/assets/images/conference_grand_ballroom_1794856701626.jpg" alt="Hội nghị" class="absolute inset-0 w-full h-full object-cover" />
  <div class="absolute inset-0 bg-[#091526]/75"></div>
  <div class="relative z-10 text-center max-w-2xl px-6">
    <h1 class="font-serif text-4xl font-semibold mb-3">Không gian cho những sự kiện đáng nhớ</h1>
    <p class="text-sm text-slate-200">Sảnh tiệc sang trọng, trang thiết bị âm thanh ánh sáng hiện đại và đội ngũ tổ chức chuyên nghiệp.</p>
  </div>
</section>
<section class="max-w-[1140px] mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-2 gap-8">
  <div class="space-y-4">
    <div class="bg-white border border-[#E6DFD1] rounded-xl p-5"><h3 class="font-bold text-xs uppercase mb-1">SẢNH NGỌC TRAI</h3><p class="text-xs text-[#A8843E] font-semibold mb-2">300 khách (tiệc) · 400 khách (hội nghị) · 420 m²</p><p class="text-xs text-slate-600">Màn hình LED P2.5, âm thanh hội trường, sân khấu di động.</p></div>
    <div class="bg-white border border-[#E6DFD1] rounded-xl p-5"><h3 class="font-bold text-xs uppercase mb-1">PHÒNG HỌP HOÀNG HÔN</h3><p class="text-xs text-[#A8843E] font-semibold mb-2">40 khách · 85 m²</p><p class="text-xs text-slate-600">Ánh sáng tự nhiên hướng biển, máy chiếu 4K.</p></div>
    <div class="bg-white border border-[#E6DFD1] rounded-xl p-5"><h3 class="font-bold text-xs uppercase mb-1">PHÒNG VIP BIỂN XANH</h3><p class="text-xs text-[#A8843E] font-semibold mb-2">12 khách · 38 m²</p><p class="text-xs text-slate-600">Bàn họp gỗ nguyên khối, phục vụ cà phê specialty.</p></div>
  </div>
  <form method="POST" action="/conferences/inquiries" class="bg-white border border-[#E6DFD1] rounded-xl p-6 space-y-3 text-xs">
    <h3 class="font-serif text-2xl font-semibold text-[#0E1D33]">Liên Hệ Đặt Sảnh &amp; Phòng Đoàn</h3>
    <div class="p-3 bg-[#FAF6EC] border border-[#E6DFD1] rounded-lg text-[11px] text-[#0E1D33] leading-relaxed">
      <strong class="text-[#A8843E] uppercase tracking-wider">Quy trình phản hồi:</strong> Ngay khi quý khách gửi yêu cầu, <strong>Lễ tân &amp; Bộ phận Sự kiện sẽ chủ động liên hệ lại</strong> qua điện thoại/Zalo trong <strong>15–30 phút</strong> (tối đa 2 giờ làm việc) để tư vấn báo giá chi tiết và giữ sảnh. Hỗ trợ gấp: <strong class="tabular-nums">1900 1833</strong>.
    </div>
    <select name="venue_name" class="w-full px-3 py-2 border rounded-lg bg-white">
      <option value="SẢNH NGỌC TRAI">SẢNH NGỌC TRAI (420 m²)</option>
      <option value="PHÒNG HỌP HOÀNG HÔN">PHÒNG HỌP HOÀNG HÔN (85 m²)</option>
      <option value="PHÒNG VIP BIỂN XANH">PHÒNG VIP BIỂN XANH (38 m²)</option>
    </select>
    <input type="text" name="contact_name" required placeholder="Họ tên người liên hệ *" class="w-full px-3 py-2 border rounded-lg" />
    <input type="tel" name="contact_phone" required placeholder="Số điện thoại *" class="w-full px-3 py-2 border rounded-lg" />
    <div>
      <label class="block text-[11px] font-semibold text-slate-600 mb-1">Ngày tổ chức dự kiến *</label>
      <input type="date" name="event_date" min="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg" />
    </div>
    <input type="number" name="guest_count" min="1" value="100" placeholder="Số khách dự kiến" class="w-full px-3 py-2 border rounded-lg" />
    <textarea name="notes" rows="2" placeholder="Yêu cầu sự kiện (thực đơn, âm thanh, khung giờ mong muốn)..." class="w-full px-3 py-2 border rounded-lg"></textarea>
    <button type="submit" class="w-full py-2.5 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] font-bold uppercase rounded-lg transition-colors">Gửi Yêu Cầu Đặt Sảnh</button>
  </form>
</section>
