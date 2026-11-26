# PHẦN LÕI, GIAO DIỆN VÀ PHẦN DÙNG CHUNG - Nguyễn Hoàng Khánh Linh (nhóm trưởng)

**Nhóm 8 - Website đặt phòng khách sạn Mường Thanh Luxury Phú Quốc**

---

## Công việc theo phân công

| Mục | Nội dung trong folder này |
|---|---|
| Xây dựng cấu trúc hệ thống | `Router.php`, `Controller.php`, `Database.php`, `Model.php`, `Auth.php`, `index.php`, `config.php`, các tệp `.htaccess` |
| Giao diện đăng ký / đăng nhập | `AuthController.php`, `view-auth-login.php` |
| Giao diện hiển thị của cả hệ thống | 12 tệp `view-*.php`; riêng trang quản trị chỉ làm **phần tĩnh** (khung, sidebar, bảng), phần form và JavaScript do folder Amin làm |
| Hồ sơ khách hàng và yêu cầu hội nghị trong giao diện Lễ tân | `ReceptionController.php` (4 hàm), `HomeController.php` (2 hàm), phần hồ sơ khách trong `BookingWorkflowService.php` |
| Phần dùng chung | `CatalogRepository.php`, `muong_thanh_pq.sql`, phần lõi của `BookingWorkflowService.php` |

Phần Word, slide và sơ đồ UC/AD không nằm trong folder code này.

---

## Tệp trong folder (28 tệp, 12.704 dòng)

### Lõi hệ thống

| Tệp | Dòng | Nội dung |
|---|---|---|
| `Router.php` | 73 | Định tuyến `get/post/patch/dispatch`, khớp tham số `{id}`, tiền tố thư mục khi chạy trong thư mục con |
| `Controller.php` | 84 | `render`, `redirect`, `json`, `authorizeWeb` |
| `Database.php` | 56 | Kết nối PDO đọc `config.php`, `transaction()` |
| `Model.php` | 31 | Model cơ sở: `find`, `all` |
| `Auth.php` | 93 | Session, đăng nhập, đăng xuất, flash, `requireRole` |
| `index.php` | 110 | Autoload + đăng ký đủ 71 route của bốn module |
| `index-root.php`, `config.php`, `.htaccess`, `public-htaccess.txt` | 38 | Chuyển hướng vào `public/`, rewrite URL, tham số ứng dụng |
| `muong_thanh_pq.sql` | 655 | 23 bảng, dữ liệu mẫu: 9 hạng phòng, 30 phòng, 30 dịch vụ, tài khoản mẫu |

### Đăng nhập, đăng ký

| Tệp | Dòng | Nội dung |
|---|---|---|
| `AuthController.php` | 585 | Đăng nhập bằng email hoặc số điện thoại, đăng ký, quên mật khẩu, đăng xuất, ghi audit |
| `view-auth-login.php` | 504 | Form đăng nhập / đăng ký / quên mật khẩu dạng tab |

### Giao diện hiển thị (12 tệp)

| Tệp | Dòng | Tệp | Dòng |
|---|---|---|---|
| `view-admin-dashboard-tinh.php` | 1.191 | `view-home-index.php` | 212 |
| `view-reception-dashboard.php` | 4.292 | `view-rooms-index.php` | 1.651 |
| `view-auth-login.php` | 504 | `view-customer-portal.php` | 1.115 |
| `view-layouts-header.php` | 91 | `view-customer-room-services.php` | 499 |
| `view-layouts-footer.php` | 73 | `view-dining-index.php` | 50 |
| `view-conferences-index.php` | 35 | `view-spa-index.php` | 34 |

### Phần nghiệp vụ dùng chung và hồ sơ khách

| Tệp | Dòng | Nội dung |
|---|---|---|
| `CatalogRepository.php` | 242 | Truy vấn danh mục, dịch vụ, gói, chính sách, chi tiết đơn, che CCCD |
| `ReceptionController.php` | 119 | 4 hàm: `__construct`, `lookupCustomer` (tra cứu khách quen), `updateBookingInfo` (sửa thông tin khách và đơn), `updateConferenceStatus` |
| `HomeController.php` | 37 | 2 hàm: `conferences`, `saveConferenceInquiry` (yêu cầu hội nghị) |
| `BookingWorkflowService.php` | 234 | 11 hàm lõi: khởi tạo và nâng cấp schema, kiểm tra mật khẩu / số điện thoại / CCCD, thông báo, ghi nhật ký, cập nhật hồ sơ khách |

Ba tệp `ReceptionController.php`, `HomeController.php`, `BookingWorkflowService.php` là **bản trích**, đầu tệp
ghi rõ hàm nào giữ lại, số dòng trong tệp gốc và phần còn lại thuộc folder nào.

`view-admin-dashboard-tinh.php` (1.191 dòng) là phần tĩnh của trang quản trị: khung, sidebar 11 tab, thanh lọc,
các khối thẻ và bảng. Phần còn lại của trang (24 khối form, nút hành động, JavaScript) do folder Amin làm ở tệp
`view-admin-dashboard-tuong-tac.php` (724 dòng). Hai tệp đánh dấu dòng gốc bằng `<!-- === dòng a-b === -->`
nên ghép lại được đúng tệp gốc.

---

