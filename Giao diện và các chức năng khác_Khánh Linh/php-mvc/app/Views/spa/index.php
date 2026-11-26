<div class="max-w-[1140px] mx-auto px-6 py-10 space-y-10">
  <div class="relative rounded-2xl overflow-hidden h-[320px] shadow-lg">
    <img src="/assets/images/spa_pearl_wellness_1794856701728.jpg" alt="Pearl Spa" class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-gradient-to-t from-[#091526]/90 via-[#091526]/35 to-transparent"></div>
    <div class="absolute bottom-8 left-8 text-white">
      <p class="text-[11px] font-bold tracking-[0.2em] text-[#E2C27D] uppercase mb-2">TÁI TẠO NĂNG LƯỢNG THÂN — TÂM — TRÍ</p>
      <h1 class="font-serif text-4xl font-semibold">Pearl Spa &amp; Chăm Sóc Sức Khỏe</h1>
    </div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php foreach ($services as $srv):
      $eff = (float) ($srv['effective_price'] ?? $srv['price']);
      $hasPromo = !empty($srv['has_active_promo']) && $eff < (float) $srv['price'];
    ?>
      <div class="bg-white border border-[#E6DFD1] rounded-xl p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-start justify-between gap-2 mb-2">
            <h3 class="font-serif text-xl font-semibold text-[#0E1D33]"><?= htmlspecialchars($srv['name']) ?></h3>
            <?php if ($hasPromo && !empty($srv['active_promo_tag'])): ?>
              <span class="px-2 py-0.5 bg-rose-600 text-white text-[10px] font-bold rounded uppercase shrink-0"><?= htmlspecialchars($srv['active_promo_tag']) ?></span>
            <?php endif; ?>
          </div>
          <p class="text-xs text-slate-600 mb-5 leading-relaxed"><?= htmlspecialchars($srv['description']) ?></p>
        </div>
        <div class="pt-4 border-t border-[#EFE9DC] text-xs font-bold text-[#A8843E] tabular-nums">
          <?php if ($hasPromo): ?>
            <span class="text-slate-400 line-through font-normal mr-2"><?= number_format((float) $srv['price'], 0, ',', '.') ?> đ</span>
          <?php endif; ?>
          Từ <?= number_format($eff, 0, ',', '.') ?> đ <?= htmlspecialchars($srv['unit_label']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
