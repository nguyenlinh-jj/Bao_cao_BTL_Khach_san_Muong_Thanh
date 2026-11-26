-- ============================================================================
-- CƠ SỞ DỮ LIỆU MYSQL: KHÁCH SẠN MƯỜNG THANH LUXURY PHÚ QUỐC (PHP 8 OOP MVC)
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS muong_thanh_pq CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE muong_thanh_pq;

SET @OLD_SQL_NOTES = @@SQL_NOTES;
SET SQL_NOTES = 0;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS password_resets, room_maintenance_logs, room_rate_overrides, room_incidents, notifications, audit_logs, conference_inquiries, reviews, housekeeping_tasks, booking_refunds, payments, invoices, service_orders, stays, voucher_usages, bookings, vouchers, hotel_policies, services, rooms, rate_packages, room_categories, users;
SET FOREIGN_KEY_CHECKS = 1;
SET SQL_NOTES = @OLD_SQL_NOTES;

-- 1. Người dùng & Phân quyền (Khách hàng, Lễ tân, Quản trị viên)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  id_number VARCHAR(12) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer', 'receptionist', 'admin') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  membership_tier VARCHAR(80) NOT NULL DEFAULT 'Hội viên Mường Thanh',
  lock_reason VARCHAR(255) NOT NULL DEFAULT '',
  locked_at DATETIME NULL,
  locked_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_locked_by FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 2. Hạng phòng (9 hạng phòng Luxury)
CREATE TABLE room_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  subtitle VARCHAR(180) NOT NULL,
  name VARCHAR(140) NOT NULL,
  group_type ENUM('room', 'suite') NOT NULL DEFAULT 'room',
  view_label VARCHAR(100) NOT NULL,
  view_type ENUM('city', 'ocean', 'panorama') NOT NULL,
  bed_type ENUM('twin', 'king', 'family') NOT NULL,
  bed_label VARCHAR(120) NOT NULL,
  area_sqm INT NOT NULL,
  max_adults INT NOT NULL,
  max_children INT NOT NULL DEFAULT 1,
  base_price DECIMAL(12,0) NOT NULL,
  description TEXT NOT NULL,
  amenities_json JSON NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  gallery_json JSON NOT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  featured_order INT NOT NULL DEFAULT 0,
  promo_discount_type ENUM('none', 'percent', 'fixed') NOT NULL DEFAULT 'none',
  promo_discount_value DECIMAL(12,0) NOT NULL DEFAULT 0,
  promo_label VARCHAR(120) NOT NULL DEFAULT '',
  promo_start_date DATE NULL,
  promo_end_date DATE NULL,
  package_config_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 3. Gói giá phòng (3 gói: Tiêu chuẩn, Buffet Sáng, VIP Trọn Gói)
CREATE TABLE rate_packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  badge_label VARCHAR(50) NOT NULL,
  extra_per_night DECIMAL(12,0) NOT NULL DEFAULT 0,
  description TEXT NOT NULL,
  inclusions_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 4. Phòng vật lý
CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_number VARCHAR(20) NOT NULL UNIQUE,
  floor INT NOT NULL,
  category_id INT NOT NULL,
  status ENUM('available', 'occupied', 'dirty', 'maintenance') NOT NULL DEFAULT 'available',
  clean_status ENUM('clean', 'dirty', 'inspecting') NOT NULL DEFAULT 'clean',
  notes VARCHAR(255) NOT NULL DEFAULT '',
  CONSTRAINT fk_rooms_category FOREIGN KEY (category_id) REFERENCES room_categories(id)
) ENGINE=InnoDB;

-- 5. Dịch vụ, Ẩm thực, Spa, Combo ưu đãi & Tùy chọn chuẩn bị trước
CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(50) NOT NULL,
  unit_label VARCHAR(40) NOT NULL,
  price DECIMAL(12,0) NOT NULL,
  promo_price DECIMAL(12,0) NULL,
  promo_tag VARCHAR(60) NULL,
  promo_start_date DATE NULL,
  promo_end_date DATE NULL,
  duration_label VARCHAR(80) NOT NULL DEFAULT '',
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 6. Mã giảm giá / Voucher (Mỗi tài khoản chỉ sử dụng tối đa 1 lần / mã)
CREATE TABLE vouchers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  discount_type ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(12,0) NOT NULL,
  max_discount_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  min_order_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  scope ENUM('all', 'room', 'service') NOT NULL DEFAULT 'all',
  description TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 7. Chính sách khách sạn
CREATE TABLE hotel_policies (
  policy_key VARCHAR(60) PRIMARY KEY,
  label VARCHAR(160) NOT NULL,
  policy_value VARCHAR(120) NOT NULL,
  description TEXT NOT NULL
) ENGINE=InnoDB;

-- 8. Đặt phòng (Lưu locked_nightly_rate + thông tin chuyến bay, giờ đến, voucher, combo/chuẩn bị trước)
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(30) NOT NULL UNIQUE,
  service_access_code VARCHAR(30) NULL,
  customer_id INT NULL,
  created_by_user_id INT NULL,
  source ENUM('online', 'walk_in') NOT NULL DEFAULT 'online',
  category_id INT NOT NULL,
  package_id INT NOT NULL,
  assigned_room_id INT NULL,
  check_in_date DATE NOT NULL,
  check_out_date DATE NOT NULL,
  nights INT NOT NULL,
  rooms_count INT NOT NULL DEFAULT 1,
  adults INT NOT NULL DEFAULT 2,
  children INT NOT NULL DEFAULT 0,
  guest_name VARCHAR(120) NOT NULL,
  guest_phone VARCHAR(30) NOT NULL,
  guest_email VARCHAR(160) NOT NULL DEFAULT '',
  guest_id_number VARCHAR(40) NOT NULL DEFAULT '',
  arrival_time VARCHAR(20) NOT NULL DEFAULT '',
  flight_number VARCHAR(40) NOT NULL DEFAULT '',
  special_requests TEXT NULL,
  voucher_code VARCHAR(40) NULL,
  discount_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  payment_plan VARCHAR(30) NOT NULL DEFAULT 'deposit_30',
  payment_method VARCHAR(30) NOT NULL DEFAULT 'bank_transfer',
  selected_items_json JSON NULL,
  locked_nightly_rate DECIMAL(12,0) NOT NULL,
  locked_package_extra DECIMAL(12,0) NOT NULL,
  locked_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 13.40,
  room_subtotal DECIMAL(12,0) NOT NULL,
  addon_subtotal DECIMAL(12,0) NOT NULL DEFAULT 0,
  extension_fee DECIMAL(12,0) NOT NULL DEFAULT 0,
  checkout_time_slot VARCHAR(20) NOT NULL DEFAULT '12:00',
  tax_amount DECIMAL(12,0) NOT NULL,
  total_estimated DECIMAL(12,0) NOT NULL,
  deposit_required DECIMAL(12,0) NOT NULL,
  status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') NOT NULL DEFAULT 'pending',
  change_request_status ENUM('none', 'date_change_pending', 'cancel_pending', 'reception_cancel_pending') NOT NULL DEFAULT 'none',
  change_request_payload JSON NULL,
  confirmed_by INT NULL,
  confirmed_at DATETIME NULL,
  cancelled_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES users(id),
  CONSTRAINT fk_bookings_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_bookings_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id),
  CONSTRAINT fk_bookings_category FOREIGN KEY (category_id) REFERENCES room_categories(id),
  CONSTRAINT fk_bookings_package FOREIGN KEY (package_id) REFERENCES rate_packages(id),
  CONSTRAINT fk_bookings_room FOREIGN KEY (assigned_room_id) REFERENCES rooms(id),
  INDEX idx_bookings_dates (status, check_in_date, check_out_date)
) ENGINE=InnoDB;

-- 9. Lịch sử dùng Voucher (Ràng buộc mỗi tài khoản dùng 1 lần/mã)
CREATE TABLE voucher_usages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  voucher_id INT NOT NULL,
  user_id INT NOT NULL,
  booking_id INT NOT NULL,
  discount_applied DECIMAL(12,0) NOT NULL,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_voucher_user (voucher_id, user_id),
  CONSTRAINT fk_vu_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id),
  CONSTRAINT fk_vu_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_vu_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB;

-- 10. Phiên lưu trú thực tế (Tạo khi Lễ tân Check-in)
CREATE TABLE stays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL UNIQUE,
  room_id INT NOT NULL,
  actual_check_in_at DATETIME NOT NULL,
  actual_check_out_at DATETIME NULL,
  checked_in_by INT NOT NULL,
  checked_out_by INT NULL,
  status ENUM('active', 'checked_out') NOT NULL DEFAULT 'active',
  notes VARCHAR(255) NOT NULL DEFAULT '',
  CONSTRAINT fk_stays_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_stays_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_stays_checked_in_by FOREIGN KEY (checked_in_by) REFERENCES users(id),
  CONSTRAINT fk_stays_checked_out_by FOREIGN KEY (checked_out_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 11. Yêu cầu & Chi phí dịch vụ (Cờ is_prebooked + billed_to_invoice chống tính trùng lặp + tách quyền Lễ tân / Bếp-Dịch vụ)
CREATE TABLE service_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  stay_id INT NULL,
  order_group_code VARCHAR(40) NULL,
  service_id INT NOT NULL,
  service_name_snapshot VARCHAR(160) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price_snapshot DECIMAL(12,0) NOT NULL,
  total_price DECIMAL(12,0) NOT NULL,
  voucher_code VARCHAR(40) NULL,
  discount_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  requested_time VARCHAR(80) NOT NULL DEFAULT '',
  guest_note VARCHAR(255) NOT NULL DEFAULT '',
  requested_by_role ENUM('customer', 'receptionist') NOT NULL DEFAULT 'customer',
  status ENUM('pending', 'accepted', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  is_prebooked TINYINT(1) NOT NULL DEFAULT 0,
  billed_to_invoice TINYINT(1) NOT NULL DEFAULT 0,
  handled_by INT NULL,
  accepted_by INT NULL,
  completed_by INT NULL,
  accepted_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancel_reason VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_so_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_so_stay FOREIGN KEY (stay_id) REFERENCES stays(id),
  CONSTRAINT fk_so_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_so_handled_by FOREIGN KEY (handled_by) REFERENCES users(id),
  CONSTRAINT fk_so_accepted_by FOREIGN KEY (accepted_by) REFERENCES users(id),
  CONSTRAINT fk_so_completed_by FOREIGN KEY (completed_by) REFERENCES users(id),
  INDEX idx_so_booking_status (booking_id, status)
) ENGINE=InnoDB;

-- 12. Hóa đơn thanh toán (Bất biến sau Check-out)
CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_code VARCHAR(30) NOT NULL UNIQUE,
  booking_id INT NOT NULL UNIQUE,
  stay_id INT NOT NULL,
  room_charges DECIMAL(12,0) NOT NULL,
  service_charges DECIMAL(12,0) NOT NULL,
  extension_fee DECIMAL(12,0) NOT NULL DEFAULT 0,
  surcharges DECIMAL(12,0) NOT NULL DEFAULT 0,
  surcharge_note VARCHAR(255) NOT NULL DEFAULT '',
  discount_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,0) NOT NULL,
  total_amount DECIMAL(12,0) NOT NULL,
  paid_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  payment_status ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
  issued_by INT NOT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_inv_stay FOREIGN KEY (stay_id) REFERENCES stays(id),
  CONSTRAINT fk_inv_issued_by FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 13. Giao dịch thanh toán (Cọc, một phần, tất toán)
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_code VARCHAR(30) NOT NULL UNIQUE,
  booking_id INT NOT NULL,
  invoice_id INT NULL,
  amount DECIMAL(12,0) NOT NULL,
  method ENUM('cash', 'card', 'qr_bank', 'bank_transfer', 'qr_vnpay') NOT NULL,
  payment_type ENUM('deposit', 'partial', 'settlement') NOT NULL,
  verification_status ENUM('verified', 'pending', 'rejected') NOT NULL DEFAULT 'pending',
  reference_no VARCHAR(80) NOT NULL DEFAULT '',
  recorded_by INT NOT NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) NOT NULL DEFAULT '',
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_pay_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id),
  INDEX idx_pay_booking_verif (booking_id, verification_status)
) ENGINE=InnoDB;

-- 13b. Yêu cầu & quy trình hoàn tiền khi hủy đặt phòng
CREATE TABLE IF NOT EXISTS booking_refunds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refund_code VARCHAR(30) NOT NULL UNIQUE,
  booking_id INT NOT NULL,
  customer_id INT NULL,
  cancel_reason VARCHAR(255) NOT NULL DEFAULT '',
  paid_verified_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  retained_fee DECIMAL(12,0) NOT NULL DEFAULT 0,
  refund_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
  status ENUM('pending', 'approved', 'rejected', 'processed') NOT NULL DEFAULT 'pending',
  refund_method ENUM('bank_transfer', 'cash', 'card', 'qr_bank') NOT NULL DEFAULT 'bank_transfer',
  reference_no VARCHAR(80) NOT NULL DEFAULT '',
  decision_note VARCHAR(255) NOT NULL DEFAULT '',
  requested_by INT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_by INT NULL,
  decided_at DATETIME NULL,
  processed_by INT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_refund_booking (booking_id),
  CONSTRAINT fk_ref_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_ref_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ref_req_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ref_app_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ref_proc_by FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_refund_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Nhiệm vụ buồng phòng sau trả phòng
CREATE TABLE housekeeping_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  stay_id INT NULL,
  booking_code VARCHAR(30) NOT NULL DEFAULT '',
  task_type ENUM('checkout_clean', 'stay_refresh', 'maintenance') NOT NULL DEFAULT 'checkout_clean',
  status ENUM('pending', 'in_progress', 'completed', 'verified') NOT NULL DEFAULT 'pending',
  assigned_note VARCHAR(255) NOT NULL DEFAULT '',
  completed_by INT NULL,
  verified_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_hk_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_hk_stay FOREIGN KEY (stay_id) REFERENCES stays(id) ON DELETE SET NULL,
  CONSTRAINT fk_hk_completed_by FOREIGN KEY (completed_by) REFERENCES users(id),
  CONSTRAINT fk_hk_verified_by FOREIGN KEY (verified_by) REFERENCES users(id),
  INDEX idx_hk_room_status (room_id, status)
) ENGINE=InnoDB;

-- 15. Đánh giá sau lưu trú
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL UNIQUE,
  customer_id INT NOT NULL,
  category_id INT NOT NULL,
  room_rating TINYINT NOT NULL,
  service_rating TINYINT NOT NULL,
  overall_rating TINYINT NOT NULL,
  title VARCHAR(180) NOT NULL DEFAULT '',
  comment TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rev_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_rev_customer FOREIGN KEY (customer_id) REFERENCES users(id),
  CONSTRAINT fk_rev_category FOREIGN KEY (category_id) REFERENCES room_categories(id)
) ENGINE=InnoDB;

-- 16. Yêu cầu hội nghị & tiệc đoàn
CREATE TABLE conference_inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venue_name VARCHAR(120) NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  contact_phone VARCHAR(30) NOT NULL,
  contact_email VARCHAR(160) NOT NULL DEFAULT '',
  event_date DATE NOT NULL,
  guest_count INT NOT NULL DEFAULT 30,
  notes TEXT NULL,
  status ENUM('new', 'contacted', 'confirmed', 'cancelled') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 17. Nhật ký hệ thống bất biến
CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NULL,
  actor_name VARCHAR(120) NOT NULL,
  actor_role VARCHAR(30) NOT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id VARCHAR(60) NOT NULL,
  details TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 18. Thông báo cho khách hàng (Xác nhận phòng, dịch vụ, hóa đơn, khuyến mãi)
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('info', 'success', 'warning', 'promo') NOT NULL DEFAULT 'info',
  related_booking_id INT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_notif_booking FOREIGN KEY (related_booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 19. Phản ánh & Sự cố phòng (Trong lưu trú & khi Check-out)
CREATE TABLE room_incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  booking_id INT NULL,
  room_number VARCHAR(20) NOT NULL,
  content TEXT NOT NULL,
  severity ENUM('LOW', 'MEDIUM', 'HIGH') NOT NULL DEFAULT 'LOW',
  resolution_plan TEXT NOT NULL,
  status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
  reported_by INT NULL,
  resolved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_inc_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_inc_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_inc_reported_by FOREIGN KEY (reported_by) REFERENCES users(id),
  CONSTRAINT fk_inc_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id),
  INDEX idx_inc_room_status (room_id, status)
) ENGINE=InnoDB;

-- 20. Điều chỉnh giá nhanh / Đóng bán theo khoảng ngày (Stop-sell)
CREATE TABLE room_rate_overrides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,0',
  override_price DECIMAL(12,0) NULL,
  is_stop_sell TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rro_category FOREIGN KEY (category_id) REFERENCES room_categories(id),
  CONSTRAINT fk_rro_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_rro_cat_dates (category_id, is_active, start_date, end_date)
) ENGINE=InnoDB;

-- 21. Lịch sử khóa phòng & bảo trì vật lý
CREATE TABLE room_maintenance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  lock_type ENUM('maintenance', 'locked') NOT NULL DEFAULT 'maintenance',
  start_date DATE NULL,
  end_date DATE NULL,
  reason VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('active', 'resolved') NOT NULL DEFAULT 'active',
  created_by INT NULL,
  resolved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_rml_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_rml_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_rml_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_rml_room_status (room_id, status)
) ENGINE=InnoDB;

-- 22. Mã xác thực đặt lại mật khẩu 6 số
CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  identifier VARCHAR(160) NOT NULL,
  code VARCHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pr_user_code (user_id, code)
) ENGINE=InnoDB;

-- ============================================================================
-- DỮ LIỆU MẪU ĐẦY ĐỦ (Mật khẩu mặc định của các tài khoản demo: 123456)
-- ============================================================================
INSERT INTO users (id, full_name, email, phone, id_number, address, password_hash, role, membership_tier) VALUES
(1, 'Lê Minh Khách', 'khach@muongthanh.vn', '0909123456', '079092001234', '128 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh', '$2y$12$iVx9202YEp4gmoZuDmG2z.WMFKQu55a/xAJE4a6KbGRAL1Pfj7UHW', 'customer', 'Hội viên Mường Thanh'),
(2, 'Phạm Ngọc Lan', 'lan.pham@gmail.com', '0918456789', '001195004321', '45 Trần Phú, Nha Trang, Khánh Hòa', '$2y$12$iVx9202YEp4gmoZuDmG2z.WMFKQu55a/xAJE4a6KbGRAL1Pfj7UHW', 'customer', 'Hội viên Vàng'),
(3, 'Nguyễn Thảo Linh', 'letan@muongthanh.vn', '02973645551', '', 'Mường Thanh Luxury Phú Quốc', '$2y$12$iVx9202YEp4gmoZuDmG2z.WMFKQu55a/xAJE4a6KbGRAL1Pfj7UHW', 'receptionist', 'Nhân viên Lễ tân'),
(4, 'Trần Hoàng Quản Trị', 'admin@muongthanh.vn', '02973645550', '', 'Mường Thanh Luxury Phú Quốc', '$2y$12$iVx9202YEp4gmoZuDmG2z.WMFKQu55a/xAJE4a6KbGRAL1Pfj7UHW', 'admin', 'Quản trị hệ thống'),
(5, 'Bộ phận Bếp & Dịch vụ', 'bep.dichvu@muongthanh.vn', '02973645552', '', 'Mường Thanh Luxury Phú Quốc', '$2y$12$iVx9202YEp4gmoZuDmG2z.WMFKQu55a/xAJE4a6KbGRAL1Pfj7UHW', 'receptionist', 'Bộ phận Bếp & Dịch vụ');

INSERT INTO room_categories (id, code, subtitle, name, group_type, view_label, view_type, bed_type, bed_label, area_sqm, max_adults, max_children, base_price, description, amenities_json, image_url, gallery_json, is_featured) VALUES
(1, 'DTCV', 'DTCV · DELUXE 2 GIƯỜNG ĐƠN — HƯỚNG PHỐ', 'Deluxe Twin City View', 'room', 'HƯỚNG PHỐ DƯƠNG TƠ', 'city', 'twin', '2 giường đơn (1.2m x 2.0m)', 37, 2, 1, 1950000, 'Không gian trang nhã với nội thất gỗ cao cấp, 2 giường đơn êm ái, ban công nhìn toàn cảnh phố biển Phú Quốc sôi động.', '["Ban công riêng", "Bồn tắm nằm", "Smart TV 55 inch", "Wifi tốc độ cao", "Minibar miễn phí"]', '/assets/images/room_deluxe_twin_city_1794856696423.jpg', '["/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 1),
(2, 'DDCV', 'DDCV · DELUXE GIƯỜNG ĐÔI — HƯỚNG PHỐ', 'Deluxe Double City View', 'room', 'HƯỚNG PHỐ DƯƠNG TƠ', 'city', 'king', '1 giường King (2.0m x 2.0m)', 37, 2, 1, 2100000, 'Lựa chọn lý tưởng cho các cặp đôi với giường King size êm ái, ánh sáng ấm cúng và tầm nhìn thành phố biển lãng mạn về đêm.', '["Ban công riêng", "Bồn tắm nằm", "Smart TV 55 inch", "Wifi tốc độ cao", "Bàn làm việc gỗ tự nhiên"]', '/assets/images/room_deluxe_twin_city_1794856696423.jpg', '["/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 1),
(3, 'DKOV', 'DKOV · DELUXE GIƯỜNG ĐÔI — HƯỚNG TRỰC DIỆN BIỂN', 'Deluxe King Ocean View', 'room', 'HƯỚNG BIỂN BÃI TRƯỜNG', 'ocean', 'king', '1 giường King siêu lớn (2.2m x 2.0m)', 42, 2, 1, 2850000, 'Hạng phòng biểu tượng đón trọn khoảnh khắc hoàng hôn Bãi Trường lộng lẫy từ ban công kính rộng mở sát biển.', '["Ban công hướng biển", "Ghế thư giãn ngoài trời", "Bồn tắm nằm", "Máy pha cà phê viên nén", "Smart TV 55 inch"]', '/assets/images/room_deluxe_ocean_king_1794856700781.jpg', '["/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 1),
(4, 'DTOV', 'DTOV · DELUXE 2 GIƯỜNG ĐƠN — HƯỚNG BIỂN', 'Deluxe Twin Ocean View', 'room', 'HƯỚNG BIỂN BÃI TRƯỜNG', 'ocean', 'twin', '2 giường đơn (1.2m x 2.0m)', 42, 2, 1, 2750000, 'Thiết kế thoáng đãng hướng đại dương xanh thẳm với hai giường đơn chuẩn 5 sao và ban công đón gió biển Bãi Trường.', '["Ban công hướng biển", "Bồn tắm nằm", "Smart TV 55 inch", "Wifi tốc độ cao", "Áo choàng tắm cao cấp"]', '/assets/images/room_deluxe_ocean_king_1794856700781.jpg', '["/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 0),
(5, 'PDKV', 'PDKV · PREMIER DELUXE GIA ĐÌNH — HƯỚNG HỒ BƠI & BIỂN', 'Premier Deluxe Family', 'room', 'HƯỚNG BIỂN & HỒ BƠI', 'ocean', 'family', '1 giường King + 1 giường đơn', 48, 3, 2, 3450000, 'Không gian rộng rãi dành riêng cho gia đình với bộ giường kết hợp tiện nghi, nhìn xuống hồ bơi vô cực và bờ biển.', '["Ban công rộng", "Phòng tắm đôi", "Khu vực tiếp khách", "Smart TV 65 inch", "Ưu tiên nhận phòng sớm"]', '/assets/images/room_deluxe_ocean_king_1794856700781.jpg', '["/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 0),
(6, 'JROV', 'JROV · JUNIOR SUITE — HOÀNG HÔN BÃI TRƯỜNG', 'Junior Suite Ocean Sunset', 'suite', 'HƯỚNG HOÀNG HÔN BIỂN', 'ocean', 'king', '1 giường King (2.2m x 2.0m)', 62, 2, 2, 4600000, 'Phân khu phòng ngủ và phòng khách tinh tế với cửa kính tràn viền đón ánh hoàng hôn rực rỡ bậc nhất đảo ngọc.', '["Phòng khách riêng", "Ban công góc toàn cảnh", "Xông hơi đá muối miễn phí", "Bồn tắm ngâm hoa", "Trái cây chào mừng"]', '/assets/images/room_grand_suite_ocean_1794856700851.jpg', '["/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 0),
(7, 'EXOV', 'EXOV · EXECUTIVE SUITE — TRỰC DIỆN ĐẠI DƯƠNG', 'Executive Suite Seafront', 'suite', 'TRỰC DIỆN BIỂN', 'ocean', 'king', '1 giường King + Phòng khách', 85, 3, 2, 6200000, 'Đẳng cấp doanh nhân và nghỉ dưỡng thượng lưu với phòng ăn riêng, quầy bar mini thượng hạng và đặc quyền Sky Lounge.', '["Đặc quyền Sky Lounge", "Phòng khách & bàn ăn", "Bồn sục Jacuzzi hướng biển", "Xe đưa đón sân bay riêng", "Xông hơi đá muối miễn phí"]', '/assets/images/room_grand_suite_ocean_1794856700851.jpg', '["/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 0),
(8, 'GRSU', 'GRSU · GRAND SUITE PANORAMA — 2 PHÒNG NGỦ', 'Grand Suite Panorama', 'suite', 'TOÀN CẢNH 180° BÃI TRƯỜNG', 'panorama', 'family', '2 phòng ngủ (1 King + 2 Đơn)', 110, 4, 2, 8900000, 'Căn hộ nghỉ dưỡng 2 phòng ngủ sang trọng bậc nhất với tầm nhìn 180 độ ôm trọn đường bờ biển Bãi Trường.', '["2 phòng ngủ biệt lập", "Phòng khách 50m²", "Bếp nhỏ & quầy rượu vang", "Butler phục vụ theo yêu cầu", "Miễn phí Pearl Spa 60 phút"]', '/assets/images/room_grand_suite_ocean_1794856700851.jpg', '["/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 0),
(9, 'PRSU', 'PRSU · PRESIDENTIAL PENTHOUSE SUITE — TẦNG THƯỢNG', 'Presidential Penthouse Suite', 'suite', 'TẦNG THƯỢNG PANORAMA', 'panorama', 'family', '2 phòng ngủ Master + Jacuzzi', 180, 4, 2, 15500000, 'Tuyệt tác penthouse tầng thượng với bể sục Jacuzzi ngoài trời, phòng họp riêng và dịch vụ quản gia riêng 24/7.', '["Quản gia riêng 24/7", "Jacuzzi sân thượng riêng", "Phòng ăn 8 khách", "Xe Limousine đưa đón", "Trọn gói Buffet & Pearl Spa"]', '/assets/images/room_grand_suite_ocean_1794856700851.jpg', '["/assets/images/room_grand_suite_ocean_1794856700851.jpg", "/assets/images/room_deluxe_ocean_king_1794856700781.jpg", "/assets/images/room_deluxe_twin_city_1794856696423.jpg", "/assets/images/hero_phu_quoc_resort_1794856695945.jpg"]', 0);

INSERT INTO rate_packages (id, code, name, badge_label, extra_per_night, description, inclusions_json) VALUES
(1, 'PKG_STD', 'Gói Phòng Tiêu Chuẩn', 'Tiết kiệm', 0, 'Giá tốt nhất, miễn phí trà cà phê và hồ bơi vô cực', '["Miễn phí nước suối, trà & cà phê hằng ngày", "Sử dụng hồ bơi vô cực & phòng gym tiêu chuẩn", "Wifi tốc độ cao"]'),
(2, 'PKG_BB', 'Gói Kèm Buffet Sáng', '+350.000 đ', 350000, 'Bao gồm buffet sáng quốc tế hơn 60 món tại nhà hàng Sonasea', '["Miễn phí buffet sáng quốc tế hơn 60 món tại Sonasea", "Tặng kèm thức uống chào mừng khi nhận phòng", "Hồ bơi vô cực & bãi biển riêng"]'),
(3, 'PKG_VIP', 'Gói Nghỉ Dưỡng VIP Trọn Gói', '+950.000 đ', 950000, 'Buffet sáng + 60 phút Pearl Spa + Xe đón tiễn sân bay', '["Trọn gói Buffet sáng & Xe đưa đón sân bay 2 chiều", "Miễn phí 60 phút liệu trình chăm sóc tại Pearl Spa", "Trái cây tươi & Set trà chiều phục vụ tại phòng"]');

INSERT INTO rooms (id, room_number, floor, category_id, status, clean_status, notes) VALUES
(1, '201', 2, 1, 'available', 'clean', ''),
(2, '202', 2, 1, 'occupied', 'clean', 'Khách đoàn walk-in'),
(3, '203', 2, 1, 'available', 'clean', ''),
(4, '204', 2, 1, 'available', 'clean', ''),
(5, '301', 3, 2, 'available', 'clean', ''),
(6, '302', 3, 2, 'dirty', 'dirty', 'Khách vừa trả phòng — chờ dọn'),
(7, '303', 3, 2, 'available', 'clean', ''),
(8, '304', 3, 2, 'available', 'clean', ''),
(9, '401', 4, 3, 'available', 'clean', 'Gán sẵn cho khách MT-PQ-261101'),
(10, '402', 4, 3, 'available', 'clean', ''),
(11, '403', 4, 3, 'available', 'clean', ''),
(12, '404', 4, 3, 'maintenance', 'clean', 'Bảo trì định kỳ'),
(13, '501', 5, 4, 'available', 'clean', ''),
(14, '502', 5, 4, 'available', 'clean', ''),
(15, '503', 5, 4, 'available', 'clean', ''),
(16, '601', 6, 5, 'available', 'clean', ''),
(17, '602', 6, 5, 'available', 'clean', ''),
(18, '603', 6, 5, 'available', 'clean', ''),
(27, '701', 7, 3, 'available', 'clean', ''),
(28, '702', 7, 5, 'available', 'clean', ''),
(19, '801', 8, 6, 'occupied', 'clean', 'Khách Lê Minh Khách đang lưu trú'),
(20, '802', 8, 6, 'available', 'clean', ''),
(21, '803', 8, 6, 'available', 'clean', ''),
(22, '901', 9, 7, 'available', 'clean', ''),
(23, '902', 9, 7, 'available', 'clean', ''),
(24, '1001', 10, 8, 'available', 'clean', ''),
(25, '1002', 10, 8, 'available', 'clean', ''),
(29, '1101', 11, 7, 'available', 'clean', ''),
(30, '1102', 11, 8, 'available', 'clean', ''),
(26, '1201', 12, 9, 'available', 'clean', 'Penthouse tầng thượng');

INSERT INTO services (id, code, name, category, unit_label, price, promo_price, promo_tag, description) VALUES
(1, 'SRV-BRK', 'Ăn sáng thêm', 'dining_other', '/suất', 180000, NULL, NULL, 'Buffet sáng Á - Âu cho 1 khách (06:30 - 10:00).'),
(2, 'SRV-MNB', 'Đồ uống minibar', 'dining_other', '/set', 120000, NULL, NULL, 'Nước suối, nước ngọt, bia và snack tại phòng.'),
(3, 'SRV-ROM', 'Bữa tối hải sản lãng mạn', 'dining_set', '/cặp', 1200000, NULL, NULL, 'Tôm hùm nướng, ghẹ Hàm Ninh, cá mú hấp và rượu vang cho 2 khách.'),
(4, 'SRV-SUN', 'Set menu Hoàng hôn 4 món', 'dining_set', '/suất', 590000, NULL, NULL, 'Khai vị gỏi cá trích, súp hải sản, cá chẽm áp chảo, tráng miệng.'),
(5, 'SRV-LOB', 'Tôm hùm nướng bơ tỏi', 'dining_seafood', '/phần', 980000, NULL, NULL, 'Tôm hùm Phú Quốc tươi sống (500g) nướng bơ tỏi thảo mộc.'),
(6, 'SRV-CRB', 'Ghẹ Hàm Ninh hấp bia', 'dining_seafood', '/phần', 520000, NULL, NULL, 'Ghẹ tươi Hàm Ninh (2 con) hấp sả bia, chấm muối tiêu chanh.'),
(7, 'SRV-FSH', 'Cá chẽm áp chảo sốt chanh dây', 'dining_seafood', '/phần', 380000, NULL, NULL, 'Phi lê cá chẽm tươi, sốt chanh dây Phú Quốc và rau củ nướng.'),
(8, 'SRV-SQD', 'Mực nhồi thịt Phú Quốc', 'dining_seafood', '/phần', 320000, NULL, NULL, 'Mực ống tươi nhồi thịt băm, nấm hương, chiên vàng sốt me.'),
(9, 'SRV-SLD', 'Gỏi cá trích Phú Quốc', 'dining_appetizer', '/phần', 190000, NULL, NULL, 'Đặc sản đảo ngọc với cá trích tươi, dừa nạo, rau rừng và nước chấm đậu phộng.'),
(10, 'SRV-BNQ', 'Bún quậy Phú Quốc', 'dining_viet', '/tô', 120000, NULL, NULL, 'Bún tươi quậy chả tôm, chả cá, mực tươi và nước dùng ngọt thanh.'),
(11, 'SRV-PHO', 'Phở bò Wagyu', 'dining_viet', '/tô', 260000, 221000, 'PQ15 · GIẢM 15%', 'Nước dùng ninh xương 12 giờ, thịt bò Wagyu thái mỏng.'),
(12, 'SRV-FRC', 'Cơm chiên hải sản', 'dining_viet', '/phần', 180000, NULL, NULL, 'Cơm chiên tôm, mực, trứng muối và hành lá.'),
(13, 'SRV-COC', 'Nước dừa tươi', 'dining_drink', '/trái', 60000, NULL, NULL, 'Dừa xiêm Phú Quốc ướp lạnh.'),
(14, 'SRV-CKT', 'Cocktail Hoàng Hôn', 'dining_drink', '/ly', 350000, 280000, 'SUNSET20 · -20%', 'Rượu rum nhiệt đới, chanh dây, dứa và siro lựu.'),
(15, 'SPA-OIL', 'Massage Toàn Thân Tinh Dầu', 'spa', '/khách', 850000, NULL, NULL, 'Liệu trình 60 hoặc 90 phút kết hợp kỹ thuật bấm huyệt cổ truyền và tinh dầu sả chanh tự nhiên.'),
(16, 'SPA-PRL', 'Ủ Bột Ngọc Trai Dưỡng Sáng', 'spa', '/khách', 950000, NULL, NULL, 'Sử dụng tinh chất bột ngọc trai Phú Quốc nguyên chất giúp tẩy tế bào chết và nuôi dưỡng làn da căng mọng.'),
(17, 'SPA-SLT', 'Xông Hơi Đá Muối Himalaya', 'spa', '/lượt', 450000, NULL, NULL, 'Hệ thống phòng xông hơi ướt (Hamam) và xông khô đá muối khoáng nóng.'),
(18, 'ADD-AIR', 'Xe đưa đón sân bay Phú Quốc (2 chiều)', 'addon', '/chuyến', 450000, NULL, NULL, 'Xe 7 chỗ đời mới đón tiễn riêng tại Sân bay Quốc tế Phú Quốc.'),
(19, 'ADD-BED', 'Giường phụ (Extra Bed)', 'addon', '/đêm', 650000, NULL, NULL, 'Giường phụ tiêu chuẩn 5 sao đặt trong phòng.'),
(20, 'ADD-DEC', 'Trang trí phòng kỷ niệm & Rượu vang', 'addon', '/gói', 890000, NULL, NULL, 'Set hoa hồng, bánh ngọt chào mừng và 1 chai vang sủi cao cấp.'),
(21, 'CMB-ROM', 'Combo Lãng mạn', 'combo', '/combo', 3300000, 2904000, '-12%', 'Trang trí phòng, bữa tối hải sản cho hai người và liệu trình spa đôi.'),
(22, 'CMB-FAM', 'Combo Gia đình', 'combo', '/combo', 1010000, 909000, '-10%', 'Giường phụ, 2 suất ăn sáng thêm và đưa đón sân bay.'),
(23, 'CMB-SPA', 'Combo Spa thư giãn', 'combo', '/combo', 1190000, 1071000, '-10%', 'Massage đá nóng và xông hơi & jacuzzi.'),
(24, 'CMB-ISLAND', 'Combo Khám phá đảo', 'combo', '/combo', 3000000, 2760000, '-8%', 'Tour 4 đảo, câu mực đêm cho 2 khách và thuê xe máy 1 ngày.'),
(25, 'PREP-AIR', 'Đưa đón sân bay', 'prep_option', '/chuyến', 350000, NULL, NULL, 'Xe 7 chỗ đón tiễn riêng tại Sân bay Quốc tế Phú Quốc theo giờ bay.'),
(26, 'PREP-BED', 'Giường phụ', 'prep_option', '/đêm', 300000, NULL, NULL, 'Giường phụ tiêu chuẩn 5 sao chuẩn bị sẵn tại phòng.'),
(27, 'PREP-LCO', 'Trả phòng muộn (đến 16:00)', 'prep_option', '/lần', 500000, NULL, NULL, 'Ưu tiên giữ phòng thư giãn đến 16:00 ngày trả phòng.'),
(28, 'EXP-SUN', 'Du thuyền ngắm hoàng hôn Phú Quốc', 'experience', '/khách', 1450000, 1250000, 'ƯU ĐÃI -14%', 'Du thuyền 2 giờ trên vịnh Phú Quốc, phục vụ cocktail & trái cây nhiệt đới.'),
(29, 'EXP-ISL', 'Tour 4 đảo & Cáp treo Hòn Thơm', 'experience', '/khách', 1650000, NULL, NULL, 'Trọn gói cano cao tốc tham quan Mây Rút, Móng Tay, Gầm Ghì & Cáp treo.'),
(30, 'EXP-BUG', 'Thuê xe đạp đôi dạo biển Bãi Trường', 'experience', '/giờ', 150000, NULL, NULL, 'Xe đạp đôi cao cấp dạo đường bờ biển riêng lúc bình minh hoặc hoàng hôn.');

INSERT INTO vouchers (code, title, discount_type, discount_value, max_discount_amount, min_order_amount, scope, description, is_active) VALUES
('MUONGTHANH10', 'Ưu đãi Hội viên 10%', 'percent', 10, 1500000, 1500000, 'all', 'Giảm 10% (tối đa 1.500.000đ) áp dụng đặt phòng & dịch vụ.', 1),
('LUXURY20', 'Ưu đãi Đẳng Cấp 20%', 'percent', 20, 3000000, 4000000, 'room', 'Giảm 20% (tối đa 3.000.000đ) cho đơn phòng từ 4.000.000đ.', 1),
('SUITEVIP', 'Đặc quyền Hạng Suite -15%', 'percent', 15, 2500000, 3500000, 'room', 'Giảm 15% cho đặt phòng cao cấp & Suite (tối đa 2.500.000đ).', 1),
('SUMMER2026', 'Nghỉ Dưỡng Hè Phú Quốc', 'fixed', 500000, 500000, 2000000, 'room', 'Giảm trực tiếp 500.000đ cho kỳ nghỉ từ 2.000.000đ.', 1),
('WELCOME', 'Chào Mừng Hội Viên Mới', 'fixed', 300000, 300000, 1000000, 'room', 'Tặng ngay 300.000đ cho lần đặt phòng đầu tiên.', 1),
('SPA50', 'Ưu đãi Pearl Spa -50.000đ', 'fixed', 50000, 50000, 300000, 'service', 'Giảm trực tiếp 50.000đ khi đặt dịch vụ Spa & thư giãn từ 300.000đ.', 1),
('FOOD15', 'Ẩm thực tại phòng -15%', 'percent', 15, 500000, 200000, 'service', 'Giảm 15% (tối đa 500.000đ) cho thực đơn gọi món & đồ uống tại phòng.', 1);

INSERT INTO hotel_policies (policy_key, label, policy_value, description) VALUES
('checkin_time', 'Giờ nhận phòng tiêu chuẩn', '14:00', 'Nhận phòng từ 14:00 hàng ngày.'),
('checkout_time', 'Giờ trả phòng tiêu chuẩn', '12:00', 'Trả phòng trước 12:00 trưa.'),
('deposit_percent', 'Tỷ lệ đặt cọc (%)', '30', 'Đặt cọc 30% tổng chi phí dự kiến.'),
('tax_service_percent', 'Phí dịch vụ & Thuế VAT (%)', '13.4', 'Gồm 5% phí dịch vụ + 8% VAT.'),
('free_cancel_days', 'Số ngày miễn phí hủy', '14', 'Miễn phí hủy trước 14 ngày nhận phòng.'),
('min_change_days', 'Số ngày tối thiểu trước khi đổi lịch', '3', 'Yêu cầu đổi ngày hoặc đổi hạng phòng phải gửi trước ngày nhận phòng ít nhất 3 ngày.'),
('late_cancel_fee_percent', 'Phí hủy muộn sau thời hạn (%)', '50', 'Thu phí 50% giá đêm đầu nếu hủy sau thời hạn miễn phí.'),
('free_child_max_age', 'Độ tuổi trẻ em được miễn phí', '6', 'Trẻ em dưới 6 tuổi được miễn phí lưu trú cùng bố mẹ.'),
('child_surcharge_price', 'Phụ phí trẻ em (6-11 tuổi)', '250000', 'Áp dụng tại khách sạn cho trẻ từ 6 đến 11 tuổi.'),
('extra_bed_price', 'Phí giường phụ (Extra Bed)', '650000', 'Giường phụ tiêu chuẩn 5 sao kèm ăn sáng.'),
('bank_name', 'Ngân hàng thanh toán QR', 'Vietcombank — CN Phú Quốc', 'Tên ngân hàng hiển thị trên VietQR và hướng dẫn chuyển khoản.'),
('bank_account_no', 'Số tài khoản nhận thanh toán', '0121 000 889 999', 'Số tài khoản ngân hàng chính thức của khách sạn.'),
('bank_account_holder', 'Chủ tài khoản ngân hàng', 'KHÁCH SẠN MƯỜNG THANH LUXURY PHÚ QUỐC', 'Tên chủ tài khoản hiển thị khi chuyển khoản.'),
('privacy_policy_text', 'Chính sách bảo mật dữ liệu', 'Mường Thanh Luxury Phú Quốc cam kết bảo mật tuyệt đối thông tin CCCD, hộ chiếu và dữ liệu thanh toán của Quý khách theo quy chuẩn 5 sao và pháp luật Việt Nam.', 'Hiển thị tại chân trang và phiếu xác nhận.'),
('member_benefits_text', 'Đặc quyền Hội viên Mường Thanh', 'Ưu tiên nhận phòng sớm / trả phòng muộn theo tình trạng phòng thực tế, tích lũy hạng thẻ Hội viên Vàng và cập nhật thông báo ưu đãi sớm nhất.', 'Hiển thị tại trang chủ và cổng Hội viên.'),
('notify_template_confirm', 'Mẫu thông báo xác nhận đặt phòng', 'Đơn đặt phòng {booking_code} đã được xác nhận thành công. Mã truy cập dịch vụ lưu trú của quý khách là {access_code}.', 'Mẫu thông báo tự động gửi khách hàng.'),
('notify_template_promo', 'Mẫu thông báo ưu đãi mới', 'Mường Thanh Luxury Phú Quốc trân trọng gửi tới Quý Hội viên chương trình ưu đãi lưu trú và ẩm thực đặc biệt trong tháng.', 'Mẫu phát thông báo từ trang Quản trị.');

UPDATE room_categories SET featured_order = 1 WHERE id = 1;
UPDATE room_categories SET featured_order = 2 WHERE id = 2;
UPDATE room_categories SET featured_order = 3 WHERE id = 3;

INSERT INTO room_maintenance_logs (room_id, lock_type, start_date, end_date, reason, status, created_by)
VALUES (12, 'maintenance', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Bảo trì định kỳ điều hòa & thiết bị phòng 404', 'active', 4);

-- Dữ liệu mẫu quy trình liên kết giữa 3 tác nhân
INSERT INTO bookings (id, booking_code, service_access_code, customer_id, created_by_user_id, source, category_id, package_id, assigned_room_id, check_in_date, check_out_date, nights, adults, children, guest_name, guest_phone, guest_email, guest_id_number, arrival_time, flight_number, special_requests, payment_plan, payment_method, selected_items_json, locked_nightly_rate, locked_package_extra, locked_tax_rate, room_subtotal, addon_subtotal, tax_amount, total_estimated, deposit_required, status, confirmed_by, confirmed_at) VALUES
(1, 'MT-PQ-261101', 'DV-261101', 1, 1, 'online', 3, 2, 9, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 2, 2, 0, 'Lê Minh Khách', '0909123456', 'khach@muongthanh.vn', '079092001234', '14:00', 'VN1823', 'Ưu tiên phòng tầng cao view biển', 'deposit_30', 'qr_vnpay', '[]', 2850000, 350000, 13.40, 6400000, 0, 857600, 7257600, 2177280, 'confirmed', 3, NOW()),
(2, 'MT-PQ-261102', 'DV-261102', 1, 1, 'online', 6, 3, 19, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 3, 2, 1, 'Lê Minh Khách', '0909123456', 'khach@muongthanh.vn', '079092001234', '13:30', 'VJ321', 'Thêm 2 gối lông vũ', 'deposit_30', 'bank_transfer', '[]', 4600000, 950000, 13.40, 16650000, 0, 2231100, 18881100, 5664330, 'checked_in', 3, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 'MT-PQ-261103', NULL, 2, 2, 'online', 1, 2, NULL, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 2, 2, 0, 'Phạm Ngọc Lan', '0918456789', 'lan.pham@gmail.com', '001195004321', '15:00', '', 'Phòng không hút thuốc', 'deposit_30', 'bank_transfer', '[]', 1950000, 350000, 13.40, 4600000, 0, 616400, 5216400, 1564920, 'pending', NULL, NULL),
(4, 'MT-PQ-261104', 'DV-261104', 1, 1, 'online', 2, 1, 5, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), 2, 2, 0, 'Lê Minh Khách', '0909123456', 'khach@muongthanh.vn', '079092001234', '14:00', '', '', 'deposit_30', 'qr_vnpay', '[]', 2100000, 0, 13.40, 4200000, 0, 562800, 4762800, 1428840, 'checked_out', 3, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(5, 'MT-PQ-261105', 'DV-261105', NULL, 3, 'walk_in', 1, 1, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 2, 0, 'Võ Quốc Anh', '0933888777', 'quocanh.vo@gmail.com', '079088009876', '14:00', '', 'Khách đặt trực tiếp tại quầy lễ tân', 'deposit_30', 'cash', '[]', 1950000, 0, 13.40, 3900000, 0, 522600, 4422600, 1326780, 'checked_in', 3, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO stays (id, booking_id, room_id, actual_check_in_at, actual_check_out_at, checked_in_by, checked_out_by, status, notes) VALUES
(1, 2, 19, DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, 3, NULL, 'active', 'Khách VIP nhận phòng đúng giờ'),
(2, 4, 5, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), 3, 3, 'checked_out', 'Trả phòng đúng hạn'),
(3, 5, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, 3, NULL, 'active', 'Walk-in check-in tại quầy');

INSERT INTO service_orders (booking_id, stay_id, order_group_code, service_id, service_name_snapshot, quantity, unit_price_snapshot, total_price, requested_time, guest_note, requested_by_role, status, is_prebooked, billed_to_invoice, handled_by, accepted_by, completed_by, accepted_at, completed_at) VALUES
(2, 1, 'SRV-261102-A', 5, 'Tôm hùm nướng bơ tỏi', 1, 980000, 980000, '19:00 Hôm nay', 'Ít tỏi, giao tại phòng 801', 'customer', 'completed', 0, 0, 5, 3, 5, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 1, 'SRV-261102-A', 15, 'Massage Toàn Thân Tinh Dầu', 2, 850000, 1700000, '16:30 Hôm nay', 'Đặt cho 2 khách phòng 801', 'customer', 'pending', 0, 0, NULL, NULL, NULL, NULL, NULL),
(4, 2, 'SRV-261104-A', 6, 'Ghẹ Hàm Ninh hấp bia', 1, 520000, 520000, '19:30', '', 'customer', 'completed', 0, 1, 5, 3, 5, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 2, 'SRV-261104-A', 9, 'Gỏi cá trích Phú Quốc', 1, 190000, 190000, '19:30', '', 'customer', 'completed', 0, 1, 5, 3, 5, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO invoices (id, invoice_code, booking_id, stay_id, room_charges, service_charges, surcharges, surcharge_note, discount_amount, tax_amount, total_amount, paid_amount, payment_status, issued_by, issued_at) VALUES
(1, 'INV-MT-261104', 4, 2, 4200000, 710000, 0, '', 0, 657940, 5567940, 5567940, 'paid', 3, DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO payments (payment_code, booking_id, invoice_id, amount, method, payment_type, verification_status, reference_no, recorded_by, note) VALUES
('PAY-261101', 1, NULL, 2177280, 'qr_vnpay', 'deposit', 'verified', 'VNPAY88231', 3, 'Đã xác nhận tiền cọc 30% giữ phòng'),
('PAY-261102', 2, NULL, 5664330, 'bank_transfer', 'deposit', 'verified', 'VCB991042', 3, 'Đặt cọc 30% trước khi đến'),
('PAY-261103', 3, NULL, 1564920, 'qr_bank', 'deposit', 'pending', 'VIETQR-984210', 2, 'Chuyển khoản cọc 30% trực tuyến — chờ Lễ tân đối soát'),
('PAY-261104A', 4, 1, 1428840, 'qr_vnpay', 'deposit', 'verified', 'VNPAY44100', 3, 'Tiền cọc đặt phòng'),
('PAY-261104B', 4, 1, 4139100, 'card', 'settlement', 'verified', 'VISA-9932', 3, 'Thanh toán phần còn lại khi check-out'),
('PAY-261105', 5, NULL, 1500000, 'cash', 'deposit', 'verified', 'CASH-DESK-01', 3, 'Khách đặt cọc tiền mặt tại quầy');

INSERT INTO housekeeping_tasks (room_id, stay_id, booking_code, task_type, status, assigned_note, completed_by, verified_by) VALUES
(5, 2, 'MT-PQ-261104', 'checkout_clean', 'verified', 'Đã dọn phòng và kiểm tra minibar sau check-out', 3, 3),
(6, NULL, 'MT-PQ-261098', 'checkout_clean', 'pending', 'Khách trả phòng sớm — cần dọn vệ sinh và kiểm tra sẵn sàng', NULL, NULL);

INSERT INTO room_incidents (room_id, booking_id, room_number, content, severity, resolution_plan, status, reported_by) VALUES
(5, NULL, '301', 'Khách phản ánh điều khiển điều hòa hơi yếu pin & cần thêm 2 khăn tắm lớn.', 'LOW', 'Đã cử nhân viên buồng phòng thay pin remote và bổ sung khăn tắm.', 'open', 3),
(2, NULL, '202', 'Két sắt mini khóa số bấm chậm, cần kỹ thuật kiểm tra bảo dưỡng.', 'MEDIUM', 'Chuyển phiếu kỹ thuật kiểm tra khi khách ra ngoài.', 'open', 3);

INSERT INTO conference_inquiries (venue_name, contact_name, contact_phone, contact_email, event_date, guest_count, notes, status) VALUES
('Grand Ballroom Bãi Trường', 'Nguyễn Minh Tuấn', '0908112233', 'tuan.nguyen@vietcorp.vn', DATE_ADD(CURDATE(), INTERVAL 18 DAY), 220, 'Hội nghị khách hàng thường niên kết hợp Gala Dinner sát biển, cần màn hình LED P3 và âm thanh ánh sáng.', 'new');

INSERT INTO reviews (booking_id, customer_id, category_id, room_rating, service_rating, overall_rating, title, comment) VALUES
(4, 1, 2, 5, 5, 5, 'Kỳ nghỉ tuyệt vời bên bờ biển Phú Quốc', 'Phòng rất sạch sẽ, nội thất gỗ sang trọng và đồ hải sản tại nhà hàng Sonasea cực kỳ tươi ngon.');

INSERT INTO notifications (user_id, title, message, type, related_booking_id, is_read) VALUES
(1, 'Xác nhận đặt phòng MT-PQ-261101 thành công', 'Lễ tân đã xác nhận đặt phòng và dự kiến bố trí Phòng 401. Mã dịch vụ lưu trú của bạn là DV-261101.', 'success', 1, 0),
(1, 'Chào mừng nhận phòng 801 (MT-PQ-261102)', 'Quý khách có thể sử dụng Mã dịch vụ DV-261102 để gọi món ăn uống và đặt lịch Pearl Spa trực tiếp tại phòng.', 'info', 2, 0),
(1, 'Ưu đãi hội viên dịch vụ tại phòng', 'Nhập mã FOOD15 (giảm 15% ẩm thực tại phòng) hoặc SPA50 (giảm 50.000đ Pearl Spa) khi đặt dịch vụ lưu trú.', 'info', NULL, 0);

INSERT INTO audit_logs (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details) VALUES
(4, 'Trần Hoàng Quản Trị', 'admin', 'SYSTEM_SEED', 'system', 'init', 'Khởi tạo dữ liệu nền Mường Thanh Luxury Phú Quốc (9 hạng phòng, 30 phòng vật lý, 30 dịch vụ & trải nghiệm, 7 voucher).'),
(3, 'Nguyễn Thảo Linh', 'receptionist', 'CONFIRM_BOOKING', 'booking', 'MT-PQ-261101', 'Xác nhận đặt phòng MT-PQ-261101 và gán phòng 401.'),
(3, 'Nguyễn Thảo Linh', 'receptionist', 'CHECK_IN', 'stay', 'MT-PQ-261102', 'Thực hiện check-in cho khách Lê Minh Khách vào phòng 801.'),
(3, 'Nguyễn Thảo Linh', 'receptionist', 'CHECK_OUT_SETTLED', 'invoice', 'INV-MT-261104', 'Hoàn tất check-out, xuất hóa đơn INV-MT-261104 và thu đủ tiền phòng 301.');
