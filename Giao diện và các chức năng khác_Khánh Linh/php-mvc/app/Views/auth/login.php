<?php
$activeMode = in_array($initialMode ?? 'login', ['login', 'register', 'forgot'], true) ? $initialMode : 'login';
$resetState = is_array($forgotResetState ?? null) ? $forgotResetState : null;
$manualIdent = trim((string) ($forgotManualIdent ?? ''));
$showForgotStep2 = (bool) $resetState || $manualIdent !== '';
$regOld = is_array($registerOld ?? null) ? $registerOld : [];
$lgOld = is_array($loginOld ?? null) ? $loginOld : [];
$loginIdentifierVal = array_key_exists('identifier', $lgOld) ? (string) $lgOld['identifier'] : 'khach@muongthanh.vn';
$forgotIdentPrefill = $resetState ? (string) ($resetState['identifier'] ?? '') : ($manualIdent !== '1' ? $manualIdent : '');
?>
<div class="max-w-lg mx-auto my-12 bg-white border border-[#E6DFD1] rounded-2xl overflow-hidden shadow-xl">
  <div class="bg-[#0E1D33] text-white px-6 py-5">
    <p class="text-[10px] tracking-[0.2em] text-[#C9A45C] uppercase">MƯỜNG THANH LUXURY PHÚ QUỐC</p>
    <h1 class="font-serif text-2xl font-semibold mt-0.5">Tài Khoản Hội Viên &amp; Hệ Thống</h1>
  </div>

  <!-- Chỉ giữ 2 tab chính: Đăng Nhập và Đăng Ký; Quên mật khẩu chỉ mở khi nhấn từ Đăng Nhập -->
  <div class="grid grid-cols-2 border-b border-[#EFE9DC] bg-[#FAF7F0] text-xs font-bold uppercase tracking-wider">
    <button type="button" id="authTabBtn_login" onclick="switchAuthTab('login')"
      class="py-3.5 px-2 text-center border-b-2 transition-colors <?= $activeMode !== 'register' ? 'border-[#C9A45C] bg-white text-[#0E1D33]' : 'border-transparent text-slate-500 hover:text-[#0E1D33]' ?>">
      Đăng Nhập
    </button>
    <button type="button" id="authTabBtn_register" onclick="switchAuthTab('register')"
      class="py-3.5 px-2 text-center border-b-2 transition-colors <?= $activeMode === 'register' ? 'border-[#C9A45C] bg-white text-[#0E1D33]' : 'border-transparent text-slate-500 hover:text-[#0E1D33]' ?>">
      Đăng Ký
    </button>
  </div>

  <div class="p-6">
    <!-- TAB 1: ĐĂNG NHẬP -->
    <div id="authPanel_login" class="<?= $activeMode === 'login' ? '' : 'hidden' ?>">
      <form id="authLoginForm" method="POST" action="/login" novalidate class="space-y-4">
        <div class="text-[11px] bg-[#FAF6EC] border border-[#E6DFD1] p-3 rounded-lg space-y-1 text-slate-700">
          <div class="font-bold text-[#A8843E] uppercase text-[10px] tracking-wider mb-1">Tài khoản trải nghiệm nhanh</div>
          <div><strong>Khách hàng:</strong> khach@muongthanh.vn (hoặc 0909123456) / 123456</div>
          <div><strong>Lễ tân:</strong> letan@muongthanh.vn / 123456</div>
          <div><strong>Quản trị viên:</strong> admin@muongthanh.vn / 123456</div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Email hoặc Số điện thoại (10 chữ số) *</label>
          <input type="text" id="loginIdentifier" name="identifier" required value="<?= htmlspecialchars($loginIdentifierVal) ?>"
            placeholder="khach@muongthanh.vn hoặc 0909123456"
            class="w-full px-3.5 py-2.5 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
          <p id="errLoginIdentifier" class="hidden text-[11px] text-rose-600 mt-1"></p>
        </div>
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-semibold text-[#0E1D33]">Mật khẩu *</label>
            <button type="button" onclick="switchAuthTab('forgot')" class="text-[11px] font-semibold text-[#A8843E] hover:underline">
              Quên mật khẩu?
            </button>
          </div>
          <div class="relative">
            <input type="password" id="loginPassword" name="password" required value="123456" class="w-full px-3.5 py-2.5 pr-11 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
            <button type="button" onclick="togglePwdVisibility('loginPassword', this)" aria-label="Hiện/ẩn mật khẩu"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-[#0E1D33]">
              <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
            </button>
          </div>
          <p id="errLoginPassword" class="hidden text-[11px] text-rose-600 mt-1"></p>
        </div>
        <button type="submit" class="w-full py-3 bg-[#0E1D33] hover:bg-[#172E4E] text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
          Đăng Nhập Hệ Thống
        </button>
        <p class="text-center text-xs text-slate-500">
          Chưa có tài khoản Hội viên?
          <button type="button" onclick="switchAuthTab('register')" class="font-bold text-[#A8843E] hover:underline">Đăng ký ngay</button>
        </p>
      </form>
    </div>

    <!-- TAB 2: ĐĂNG KÝ HỘI VIÊN (VALIDATION TỨC THÌ ĐỒNG THỜI) -->
    <div id="authPanel_register" class="<?= $activeMode === 'register' ? '' : 'hidden' ?>">
      <form id="authRegisterForm" method="POST" action="/register" novalidate class="space-y-3.5">
        <div class="text-[11px] bg-[#FAF7F0] border border-[#EFE9DC] rounded-lg px-3 py-2 text-slate-600">
          Vui lòng nhập đầy đủ thông tin bên dưới. Số điện thoại phải đúng <strong>10 chữ số</strong> và số CCCD bắt buộc đúng <strong>12 chữ số</strong>.
        </div>

        <div>
          <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Họ và tên *</label>
          <input type="text" id="regFullName" name="full_name" value="<?= htmlspecialchars((string) ($regOld['full_name'] ?? '')) ?>" placeholder="VD: Nguyễn Văn An"
            class="w-full px-3.5 py-2 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
          <p id="errRegFullName" class="hidden text-[11px] text-rose-600 mt-1"></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Số điện thoại * <span class="font-normal text-slate-500">(10 chữ số)</span></label>
            <input type="tel" id="regPhone" name="phone" maxlength="10" inputmode="numeric" value="<?= htmlspecialchars((string) ($regOld['phone'] ?? '')) ?>" placeholder="0901234567"
              class="w-full px-3.5 py-2 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C] tabular-nums" />
            <p id="errRegPhone" class="hidden text-[11px] text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Số CCCD * <span class="font-normal text-slate-500">(12 chữ số)</span></label>
            <input type="text" id="regIdNumber" name="id_number" maxlength="12" inputmode="numeric" value="<?= htmlspecialchars((string) ($regOld['id_number'] ?? '')) ?>" placeholder="079092001234"
              class="w-full px-3.5 py-2 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C] tabular-nums" />
            <p id="errRegIdNumber" class="hidden text-[11px] text-rose-600 mt-1"></p>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Email *</label>
          <input type="email" id="regEmail" name="email" value="<?= htmlspecialchars((string) ($regOld['email'] ?? '')) ?>" placeholder="ten@domain.com"
            class="w-full px-3.5 py-2 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
          <p id="errRegEmail" class="hidden text-[11px] text-rose-600 mt-1"></p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Mật khẩu * <span class="font-normal text-slate-500">(Tối thiểu 6 ký tự gồm cả chữ và số)</span></label>
          <div class="relative">
            <input type="password" id="regPassword" name="password" placeholder="VD: MuongThanh2026"
              class="w-full px-3.5 py-2 pr-11 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
            <button type="button" onclick="togglePwdVisibility('regPassword', this)" aria-label="Hiện/ẩn mật khẩu"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-[#0E1D33]">
              <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
            </button>
          </div>
          <p id="errRegPassword" class="hidden text-[11px] text-rose-600 mt-1"></p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Xác nhận mật khẩu *</label>
          <div class="relative">
            <input type="password" id="regConfirmPassword" name="confirm_password" placeholder="Nhập lại mật khẩu giống ở trên"
              class="w-full px-3.5 py-2 pr-11 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
            <button type="button" onclick="togglePwdVisibility('regConfirmPassword', this)" aria-label="Hiện/ẩn mật khẩu xác nhận"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-[#0E1D33]">
              <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
            </button>
          </div>
          <p id="errRegConfirmPassword" class="hidden text-[11px] text-rose-600 mt-1"></p>
        </div>

        <button type="submit" class="w-full py-3 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
          Tạo Tài Khoản Hội Viên
        </button>
        <p class="text-center text-xs text-slate-500">
          Đã có tài khoản?
          <button type="button" onclick="switchAuthTab('login')" class="font-bold text-[#0E1D33] hover:underline">Đăng nhập</button>
        </p>
      </form>
    </div>

    <!-- KHÔI PHỤC MẬT KHẨU (CHỈ HIỆN KHI NHẤN "QUÊN MẬT KHẨU?" Ở ĐĂNG NHẬP) -->
    <div id="authPanel_forgot" class="<?= $activeMode === 'forgot' ? '' : 'hidden' ?> space-y-4">
      <div class="flex items-center justify-between gap-2 pb-2 border-b border-[#EFE9DC]">
        <div>
          <h2 class="font-serif text-lg font-semibold text-[#0E1D33]">Khôi phục mật khẩu</h2>
          <p class="text-[11px] text-slate-500">Xác minh tài khoản để nhận mã OTP 6 chữ số và đặt lại mật khẩu mới.</p>
        </div>
        <button type="button" onclick="switchAuthTab('login')" class="text-xs font-semibold text-[#A8843E] hover:underline whitespace-nowrap shrink-0">
          ← Quay lại Đăng nhập
        </button>
      </div>

      <form id="authForgotRequestForm" method="POST" action="/forgot-password/request" novalidate class="space-y-3">
        <div class="text-xs font-bold uppercase tracking-wider text-[#A8843E]">Bước 1: Xác minh tài khoản &amp; nhận mã OTP 6 số</div>
        <div>
          <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Email hoặc Số điện thoại (10 chữ số) đã đăng ký *</label>
          <input type="text" id="fgReqIdentifier" name="identifier" required value="<?= htmlspecialchars($forgotIdentPrefill) ?>"
            placeholder="VD: khach@muongthanh.vn hoặc 0909123456"
            class="w-full px-3.5 py-2 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
          <p id="errFgReqIdentifier" class="hidden text-[11px] text-rose-600 mt-1"></p>
        </div>
        <button type="submit" class="w-full py-2.5 bg-[#0E1D33] hover:bg-[#172E4E] text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
          <?= $resetState ? 'Cấp Lại Mã OTP Mới' : 'Lấy Mã Xác Nhận 6 Số' ?>
        </button>
      </form>

      <?php if ($resetState):
        $expUnix = isset($resetState['expires_unix'])
          ? (int) $resetState['expires_unix']
          : max(time() + 60, (int) (strtotime((string) ($resetState['expires_at'] ?? '+15 minutes')) ?: (time() + 900)));
        $remInit = max(0, $expUnix - time());
        $remMins = str_pad((string) intdiv($remInit, 60), 2, '0', STR_PAD_LEFT);
        $remSecs = str_pad((string) ($remInit % 60), 2, '0', STR_PAD_LEFT);
      ?>
        <div class="p-4 bg-emerald-50 border border-emerald-300 rounded-xl text-center space-y-1.5">
          <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">
            Mã OTP xác nhận cho <?= htmlspecialchars($resetState['full_name'] ?? 'Hội viên') ?>
          </div>
          <div class="font-mono text-3xl font-bold tracking-[0.28em] text-[#0E1D33] py-1 tabular-nums select-all" id="otpDisplayCode"><?= htmlspecialchars($resetState['code']) ?></div>
          <div class="text-[11px] text-emerald-700">
            Mã có hiệu lực trong
            <strong id="otpCountdown" data-remaining-seconds="<?= $remInit ?>" class="font-mono font-bold text-emerald-900 tabular-nums"><?= $remMins ?>:<?= $remSecs ?></strong>.
            Vui lòng nhập mã OTP này vào Bước 2 bên dưới.
          </div>
          <div class="pt-1">
            <button type="button" onclick="fillGeneratedOtp('<?= htmlspecialchars($resetState['code'], ENT_QUOTES) ?>')"
              class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-white border border-emerald-400 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100 transition-colors">
              Điền nhanh mã OTP này ↓
            </button>
          </div>
        </div>
      <?php else: ?>
        <div class="text-right">
          <button type="button" id="toggleForgotStep2Btn" onclick="toggleManualForgotStep2()"
            class="text-[11px] font-semibold text-[#A8843E] hover:underline">
            Đã có mã OTP 6 số? Nhập mã ngay ↓
          </button>
        </div>
      <?php endif; ?>

      <div id="forgotStep2Container" class="<?= $showForgotStep2 ? '' : 'hidden' ?> border-t border-[#EFE9DC] pt-4">
        <form id="authForgotResetForm" method="POST" action="/forgot-password/reset" novalidate class="space-y-3">
          <div class="text-xs font-bold uppercase tracking-wider text-[#A8843E]">Bước 2: Nhập mã OTP &amp; đặt mật khẩu mới</div>
          <div>
            <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Email hoặc Số điện thoại tài khoản *</label>
            <input type="text" id="fgIdentifier" name="identifier" value="<?= htmlspecialchars($forgotIdentPrefill) ?>"
              <?= $resetState ? 'readonly' : '' ?>
              placeholder="VD: khach@muongthanh.vn hoặc 0909123456"
              class="w-full px-3.5 py-2 text-sm border border-[#E6DFD1] rounded-lg <?= $resetState ? 'bg-slate-50 text-slate-700' : 'bg-white' ?> focus:outline-none focus:border-[#C9A45C]" />
            <p id="errFgIdentifier" class="hidden text-[11px] text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Mã OTP xác nhận (6 chữ số) *</label>
            <input type="text" id="fgCode" name="code" maxlength="24" inputmode="numeric" autocomplete="one-time-code"
              placeholder="Nhập 6 chữ số OTP vừa nhận"
              class="w-full px-3.5 py-2 text-sm font-mono tracking-[0.22em] border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
            <p id="errFgCode" class="hidden text-[11px] text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Mật khẩu mới * <span class="font-normal text-slate-500">(Tối thiểu 6 ký tự gồm cả chữ và số)</span></label>
            <div class="relative">
              <input type="password" id="fgNewPassword" name="new_password" placeholder="Nhập mật khẩu mới"
                class="w-full px-3.5 py-2 pr-11 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
              <button type="button" onclick="togglePwdVisibility('fgNewPassword', this)" aria-label="Hiện/ẩn mật khẩu mới"
                class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-[#0E1D33]">
                <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
              </button>
            </div>
            <p id="errFgNewPassword" class="hidden text-[11px] text-rose-600 mt-1"></p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-[#0E1D33] mb-1">Xác nhận mật khẩu mới *</label>
            <div class="relative">
              <input type="password" id="fgConfirmPassword" name="confirm_password" placeholder="Nhập lại mật khẩu mới"
                class="w-full px-3.5 py-2 pr-11 text-sm border border-[#E6DFD1] rounded-lg focus:outline-none focus:border-[#C9A45C]" />
              <button type="button" onclick="togglePwdVisibility('fgConfirmPassword', this)" aria-label="Hiện/ẩn xác nhận mật khẩu"
                class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-[#0E1D33]">
                <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
              </button>
            </div>
            <p id="errFgConfirmPassword" class="hidden text-[11px] text-rose-600 mt-1"></p>
          </div>
          <button type="submit" class="w-full py-3 bg-[#C9A45C] hover:bg-[#B8934A] text-[#0E1D33] text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
            Xác Nhận Đặt Lại Mật Khẩu
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  window.switchAuthTab = (tab) => {
    ['login', 'register', 'forgot'].forEach((t) => {
      const panel = document.getElementById('authPanel_' + t);
      if (panel) panel.classList.toggle('hidden', t !== tab);
    });
    const loginBtn = document.getElementById('authTabBtn_login');
    const regBtn = document.getElementById('authTabBtn_register');
    if (loginBtn) {
      const loginActive = tab === 'login' || tab === 'forgot';
      loginBtn.className = 'py-3.5 px-2 text-center border-b-2 transition-colors ' +
        (loginActive ? 'border-[#C9A45C] bg-white text-[#0E1D33]' : 'border-transparent text-slate-500 hover:text-[#0E1D33]');
    }
    if (regBtn) {
      regBtn.className = 'py-3.5 px-2 text-center border-b-2 transition-colors ' +
        (tab === 'register' ? 'border-[#C9A45C] bg-white text-[#0E1D33]' : 'border-transparent text-slate-500 hover:text-[#0E1D33]');
    }
  };

  window.toggleManualForgotStep2 = () => {
    const box = document.getElementById('forgotStep2Container');
    if (!box) return;
    box.classList.toggle('hidden');
    const reqVal = (document.getElementById('fgReqIdentifier')?.value || '').trim();
    const fgId = document.getElementById('fgIdentifier');
    if (fgId && !fgId.value.trim() && reqVal) fgId.value = reqVal;
  };

  window.fillGeneratedOtp = (codeVal) => {
    const fgCodeEl = document.getElementById('fgCode');
    if (!fgCodeEl) return;
    fgCodeEl.value = String(codeVal || '').replace(/\D+/g, '').slice(0, 6);
    fgCodeEl.dispatchEvent(new Event('input', { bubbles: true }));
    const fgNew = document.getElementById('fgNewPassword');
    if (fgNew) fgNew.focus();
  };

  window.togglePwdVisibility = (inputId, btn) => {
    const inp = document.getElementById(inputId);
    if (!inp) return;
    const isHidden = inp.type === 'password';
    inp.type = isHidden ? 'text' : 'password';
    if (btn) {
      const eyeOpen = btn.querySelector('.eye-open');
      const eyeClosed = btn.querySelector('.eye-closed');
      if (eyeOpen) eyeOpen.classList.toggle('hidden', isHidden);
      if (eyeClosed) eyeClosed.classList.toggle('hidden', !isHidden);
    }
  };

  function setFieldState(inputEl, errEl, msg) {
    if (!inputEl || !errEl) return !msg;
    if (msg) {
      inputEl.classList.add('border-rose-500', 'bg-rose-50/30');
      inputEl.classList.remove('border-[#E6DFD1]');
      errEl.textContent = msg;
      errEl.classList.remove('hidden');
      return false;
    } else {
      inputEl.classList.remove('border-rose-500', 'bg-rose-50/30');
      inputEl.classList.add('border-[#E6DFD1]');
      errEl.textContent = '';
      errEl.classList.add('hidden');
      return true;
    }
  }

  function validateEmailOrPhone10(raw) {
    const v = String(raw || '').trim();
    if (!v) return 'Vui lòng nhập Email hoặc Số điện thoại (10 chữ số).';
    if (v.includes('@')) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'Địa chỉ Email không đúng định dạng.';
    }
    const clean = v.replace(/\s+/g, '');
    return /^\d{10}$/.test(clean) ? '' : 'Số điện thoại phải gồm chính xác 10 chữ số (hoặc nhập Email hợp lệ).';
  }

  function validatePasswordRule(pw) {
    if (pw.length < 6) return 'Mật khẩu phải có ít nhất 6 ký tự.';
    if (!/[A-Za-z]/.test(pw) || !/\d/.test(pw)) return 'Mật khẩu phải gồm cả chữ cái và chữ số.';
    return '';
  }

  function keepDigitsOnly(el, maxLen) {
    if (!el) return;
    const cleaned = el.value.replace(/\D+/g, '').slice(0, maxLen);
    if (el.value !== cleaned) el.value = cleaned;
  }

  const loginForm = document.getElementById('authLoginForm');
  if (loginForm) {
    const lId = document.getElementById('loginIdentifier');
    const lPw = document.getElementById('loginPassword');
    const checkLoginId = () => setFieldState(lId, document.getElementById('errLoginIdentifier'), validateEmailOrPhone10(lId.value));
    const checkLoginPw = () => setFieldState(lPw, document.getElementById('errLoginPassword'), lPw.value === '' ? 'Vui lòng nhập mật khẩu.' : '');
    lId.addEventListener('blur', checkLoginId);
    lId.addEventListener('input', () => {
      if (!document.getElementById('errLoginIdentifier').classList.contains('hidden')) checkLoginId();
    });
    lPw.addEventListener('input', () => {
      if (!document.getElementById('errLoginPassword').classList.contains('hidden')) checkLoginPw();
    });
    loginForm.addEventListener('submit', (e) => {
      if (![checkLoginId(), checkLoginPw()].every(Boolean)) e.preventDefault();
    });
  }

  const regForm = document.getElementById('authRegisterForm');
  if (regForm) {
    const fName = document.getElementById('regFullName');
    const fPhone = document.getElementById('regPhone');
    const fIdNum = document.getElementById('regIdNumber');
    const fEmail = document.getElementById('regEmail');
    const fPwd = document.getElementById('regPassword');
    const fConfirm = document.getElementById('regConfirmPassword');

    const validators = {
      full_name: () => {
        const v = fName.value.trim();
        return setFieldState(fName, document.getElementById('errRegFullName'), v.length < 2 ? 'Họ và tên phải có ít nhất 2 ký tự.' : '');
      },
      phone: () => {
        keepDigitsOnly(fPhone, 10);
        return setFieldState(fPhone, document.getElementById('errRegPhone'), !/^\d{10}$/.test(fPhone.value) ? 'Số điện thoại phải gồm chính xác 10 chữ số.' : '');
      },
      id_number: () => {
        keepDigitsOnly(fIdNum, 12);
        return setFieldState(fIdNum, document.getElementById('errRegIdNumber'), !/^\d{12}$/.test(fIdNum.value) ? 'Số CCCD bắt buộc phải gồm chính xác 12 chữ số.' : '');
      },
      email: () => {
        const v = fEmail.value.trim();
        return setFieldState(fEmail, document.getElementById('errRegEmail'), !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? 'Địa chỉ email không đúng định dạng.' : '');
      },
      password: () => {
        const ok1 = setFieldState(fPwd, document.getElementById('errRegPassword'), validatePasswordRule(fPwd.value));
        if (fConfirm.value.length > 0) validators.confirm_password();
        return ok1;
      },
      confirm_password: () => {
        let msg = '';
        if (!fConfirm.value) msg = 'Vui lòng nhập lại mật khẩu để xác nhận.';
        else if (fConfirm.value !== fPwd.value) msg = 'Mật khẩu xác nhận không trùng khớp.';
        return setFieldState(fConfirm, document.getElementById('errRegConfirmPassword'), msg);
      }
    };

    fName.addEventListener('input', validators.full_name);
    fName.addEventListener('blur', validators.full_name);
    fPhone.addEventListener('input', validators.phone);
    fPhone.addEventListener('blur', validators.phone);
    fIdNum.addEventListener('input', validators.id_number);
    fIdNum.addEventListener('blur', validators.id_number);
    fEmail.addEventListener('input', validators.email);
    fEmail.addEventListener('blur', validators.email);
    fPwd.addEventListener('input', validators.password);
    fPwd.addEventListener('blur', validators.password);
    fConfirm.addEventListener('input', validators.confirm_password);
    fConfirm.addEventListener('blur', validators.confirm_password);

    regForm.addEventListener('submit', (e) => {
      const results = [
        validators.full_name(),
        validators.phone(),
        validators.id_number(),
        validators.email(),
        validators.password(),
        validators.confirm_password(),
      ];
      if (results.includes(false)) {
        e.preventDefault();
      }
    });
  }

  const fgReqForm = document.getElementById('authForgotRequestForm');
  if (fgReqForm) {
    const reqInp = document.getElementById('fgReqIdentifier');
    const checkReq = () => setFieldState(reqInp, document.getElementById('errFgReqIdentifier'), validateEmailOrPhone10(reqInp.value));
    reqInp.addEventListener('blur', checkReq);
    fgReqForm.addEventListener('submit', (e) => {
      if (!checkReq()) e.preventDefault();
    });
  }

  const fgForm = document.getElementById('authForgotResetForm');
  if (fgForm) {
    const fgId = document.getElementById('fgIdentifier');
    const fgCode = document.getElementById('fgCode');
    const fgNew = document.getElementById('fgNewPassword');
    const fgConfirm = document.getElementById('fgConfirmPassword');

    const fgValidators = {
      identifier: () => setFieldState(fgId, document.getElementById('errFgIdentifier'), validateEmailOrPhone10(fgId.value)),
      code: () => {
        keepDigitsOnly(fgCode, 6);
        return setFieldState(fgCode, document.getElementById('errFgCode'), !/^\d{6}$/.test(fgCode.value) ? 'Mã OTP xác nhận phải gồm đúng 6 chữ số.' : '');
      },
      new_password: () => {
        const ok = setFieldState(fgNew, document.getElementById('errFgNewPassword'), validatePasswordRule(fgNew.value));
        if (fgConfirm.value.length > 0) fgValidators.confirm_password();
        return ok;
      },
      confirm_password: () => {
        let msg = '';
        if (!fgConfirm.value) msg = 'Vui lòng xác nhận mật khẩu mới.';
        else if (fgConfirm.value !== fgNew.value) msg = 'Mật khẩu xác nhận không trùng khớp.';
        return setFieldState(fgConfirm, document.getElementById('errFgConfirmPassword'), msg);
      }
    };

    fgId.addEventListener('blur', fgValidators.identifier);
    fgCode.addEventListener('input', fgValidators.code);
    fgNew.addEventListener('input', fgValidators.new_password);
    fgConfirm.addEventListener('input', fgValidators.confirm_password);

    fgForm.addEventListener('submit', (e) => {
      const results = [fgValidators.identifier(), fgValidators.code(), fgValidators.new_password(), fgValidators.confirm_password()];
      if (results.includes(false)) e.preventDefault();
    });
  }

  const cdEl = document.getElementById('otpCountdown');
  if (cdEl) {
    let rem = Math.max(0, Number(cdEl.getAttribute('data-remaining-seconds') || 900));
    const renderCd = () => {
      if (rem <= 0) {
        cdEl.textContent = '00:00 (Hết hạn — vui lòng cấp lại mã)';
        cdEl.classList.remove('text-emerald-900');
        cdEl.classList.add('text-rose-700');
        return;
      }
      const m = String(Math.floor(rem / 60)).padStart(2, '0');
      const s = String(rem % 60).padStart(2, '0');
      cdEl.textContent = `${m}:${s}`;
    };
    renderCd();
    const timer = setInterval(() => {
      rem -= 1;
      renderCd();
      if (rem <= 0) clearInterval(timer);
    }, 1000);
  }
})();
</script>
