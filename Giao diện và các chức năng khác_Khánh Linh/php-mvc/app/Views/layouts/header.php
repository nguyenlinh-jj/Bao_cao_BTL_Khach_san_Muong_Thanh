<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? 'Mường Thanh Luxury Phú Quốc — Hệ Thống Đặt Phòng & Quản Lý Khách Sạn 5★') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&subset=vietnamese&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            serif: ['"Cormorant Garamond"', '"Times New Roman"', 'serif'],
            sans: ['"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
          }
        }
      }
    };
  </script>
  <style>
    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background-color: #FBF9F5;
      color: #0E1D33;
      -webkit-font-smoothing: antialiased;
    }
    h1, h2, .font-serif, .font-display {
      font-family: 'Cormorant Garamond', 'Times New Roman', serif !important;
    }
    .font-mono {
      font-family: 'JetBrains Mono', ui-monospace, monospace !important;
    }
    .tabular-nums {
      font-variant-numeric: tabular-nums;
    }
  </style>
</head>
<body class="min-h-screen flex flex-col bg-[#FBF9F5] text-[#0E1D33] antialiased">
<?php if (empty($hidePublicChrome)): ?>
<header class="sticky top-0 z-40 bg-[#0E1D33] text-white shadow-sm">
  <div class="border-b border-white/10 bg-[#091526] px-4 py-1.5 text-[11px] text-slate-300 hidden md:block">
    <div class="max-w-[1320px] mx-auto flex items-center justify-between gap-4">
      <span class="truncate">Khu phức hợp Bãi Trường, Xã Dương Tơ, TP. Phú Quốc, Kiên Giang &nbsp;|&nbsp; Hotline: <strong class="text-white tabular-nums">1900 1833 — (0297) 364 5555</strong></span>
      <span class="whitespace-nowrap tabular-nums">Nhận phòng: <?= htmlspecialchars($policies['checkin_time'] ?? '14:00') ?> · Trả phòng: <?= htmlspecialchars($policies['checkout_time'] ?? '12:00') ?></span>
    </div>
  </div>
  <div class="max-w-[1320px] mx-auto px-4 lg:px-6 h-[74px] flex items-center justify-between gap-4">
    <nav class="hidden lg:flex items-center gap-6 text-xs font-medium tracking-[0.12em] uppercase whitespace-nowrap">
      <a href="/rooms" class="text-slate-200 hover:text-[#C9A45C] transition-colors whitespace-nowrap">PHÒNG VÀ SUITE</a>
      <a href="/dining" class="text-slate-200 hover:text-[#C9A45C] transition-colors whitespace-nowrap">ẨM THỰC</a>
      <a href="/spa" class="text-slate-200 hover:text-[#C9A45C] transition-colors whitespace-nowrap">SPA &amp; SỨC KHỎE</a>
    </nav>

    <a href="/" class="flex flex-col items-center justify-center shrink-0">
      <span class="font-serif text-xl tracking-[0.22em] font-semibold text-[#E2C27D] whitespace-nowrap">MƯỜNG THANH</span>
      <span class="text-[9px] tracking-[0.28em] text-slate-300 uppercase whitespace-nowrap">LUXURY PHÚ QUỐC · ★★★★★</span>
    </a>

    <div class="flex items-center gap-3.5 text-xs font-medium tracking-[0.1em] uppercase whitespace-nowrap">
      <a href="/conferences" class="hidden md:inline text-slate-200 hover:text-[#C9A45C] transition-colors whitespace-nowrap">HỘI NGHỊ</a>
      <a href="/lookup" class="hidden md:inline text-slate-200 hover:text-[#C9A45C] transition-colors whitespace-nowrap">TRA CỨU</a>
      <?php if ($authUser): ?>
        <?php if ($authUser['role'] === 'customer'): ?>
          <a href="/my-bookings" class="px-3 py-1.5 border border-white/25 rounded text-white hover:border-[#C9A45C] whitespace-nowrap">Đơn của tôi</a>
        <?php elseif ($authUser['role'] === 'receptionist'): ?>
          <a href="/reception" class="px-3 py-1.5 border border-[#C9A45C] rounded text-[#E2C27D] whitespace-nowrap">Bàn Lễ Tân</a>
        <?php else: ?>
          <a href="/admin" class="px-3 py-1.5 border border-[#C9A45C] rounded text-[#E2C27D] whitespace-nowrap">Quản trị PMS</a>
        <?php endif; ?>
        <span class="hidden xl:inline text-[11px] normal-case text-slate-200 max-w-[150px] truncate"><?= htmlspecialchars($authUser['full_name']) ?></span>
        <a href="/logout" class="text-slate-300 hover:text-white whitespace-nowrap">ĐĂNG XUẤT</a>
      <?php else: ?>
        <a href="/login" class="text-slate-100 hover:text-[#C9A45C] whitespace-nowrap">ĐĂNG NHẬP</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php endif; ?>

<?php if (!empty($flash) && empty($hidePublicChrome)): ?>
  <div class="max-w-[1200px] mx-auto w-full px-6 pt-4">
    <div class="p-3.5 rounded-xl border text-xs <?= $flash['type'] === 'ok' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-700' ?>">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  </div>
<?php endif; ?>
<main class="flex-1">
