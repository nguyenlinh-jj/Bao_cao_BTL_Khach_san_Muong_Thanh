<div class="max-w-[1140px] mx-auto px-6 py-10 space-y-10">
  <div class="relative rounded-2xl overflow-hidden h-[320px] shadow-lg">
    <img src="/assets/images/dining_sonasea_buffet_1794856700253.jpg" alt="Nhà Hàng Sonasea" class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-gradient-to-t from-[#091526]/90 via-[#091526]/35 to-transparent"></div>
    <div class="absolute bottom-8 left-8 text-white">
      <p class="text-[11px] font-bold tracking-[0.2em] text-[#E2C27D] uppercase mb-2">HƯƠNG VỊ ĐẢO NGỌC 5 SAO</p>
      <h1 class="font-serif text-4xl font-semibold">Nhà Hàng Sonasea &amp; Rooftop Sky Lounge</h1>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <?php
    $groups = [
      'dining_other' => 'Ăn sáng &amp; Minibar', 'dining_set' => 'Set menu &amp; Tiệc', 'dining_seafood' => 'Hải sản tươi sống',
      'dining_appetizer' => 'Khai vị &amp; Gỏi', 'dining_viet' => 'Món Việt &amp; Wagyu', 'dining_drink' => 'Đồ uống &amp; Bar',
    ];
    foreach ($groups as $gKey => $gLabel):
      $items = array_filter($services, fn($s) => $s['category'] === $gKey);
    ?>
      <div class="bg-white border border-[#E6DFD1] rounded-xl p-5">
        <h3 class="font-serif text-lg font-semibold text-[#0E1D33] pb-3 mb-3 border-b border-[#EFE9DC]"><?= $gLabel ?></h3>
        <div class="space-y-3 text-xs">
          <?php foreach ($items as $it):
            $eff = (float) ($it['effective_price'] ?? $it['price']);
            $hasPromo = !empty($it['has_active_promo']) && $eff < (float) $it['price'];
          ?>
            <div class="flex justify-between gap-2">
              <div>
                <div class="font-bold text-[#0E1D33]">
                  <?= htmlspecialchars($it['name']) ?>
                  <?php if ($hasPromo && !empty($it['active_promo_tag'])): ?>
                    <span class="ml-1 px-1.5 py-0.5 bg-rose-600 text-white text-[9px] font-bold rounded uppercase"><?= htmlspecialchars($it['active_promo_tag']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($it['description']) ?></div>
              </div>
              <div class="text-right font-bold text-[#A8843E] tabular-nums shrink-0">
                <?php if ($hasPromo): ?>
                  <div class="text-[10px] text-slate-400 line-through font-normal"><?= number_format((float) $it['price'], 0, ',', '.') ?> đ</div>
                <?php endif; ?>
                <?= number_format($eff, 0, ',', '.') ?> đ
                <div class="text-[10px] text-slate-400 font-normal"><?= htmlspecialchars($it['unit_label']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
