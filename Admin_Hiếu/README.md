# MODULE ADMIN - Nguyễn Trung Hiếu

**Nhóm 8 - Website đặt phòng khách sạn Mường Thanh Luxury Phú Quốc**
---
## Công việc theo phân công riêng

Các chức năng trong giao diện Admin: đặt phòng, dịch vụ lưu trú, hóa đơn, thông báo và chỉnh sửa hồ sơ.
Phần vẽ sơ đồ UC/AD và làm Word không nằm trong folder code này.
---
## Tệp trong folder

| Tệp | Dòng | Nội dung |
|---|---|---|
| `AdminController.php` | 1.299 | 22 method xử lý toàn bộ nghiệp vụ quản trị, mỗi method ứng với 1 route `/admin/...` |
| `view-admin-dashboard.php` | 1.791 | Toàn bộ giao diện quản trị: khung trang, sidebar 11 tab, thanh lọc, các khối thẻ, bảng dữ liệu, 24 khối form CRUD và JavaScript điều khiển trang |

Nhóm chức năng trong `AdminController.php`:

| Nhóm | Method |
|---|---|
| Trang quản trị | `dashboard()` - doanh thu, công suất, ADR, đơn, khách, phòng, sổ hoàn tiền, nhật ký |
| Đặt phòng | danh sách đơn trong `dashboard()`, `toggleUser()`, `createStaff()`, `updateUserRole()` |
| Dịch vụ lưu trú | `createService()`, `updateService()`, `toggleService()`, `deleteService()` |
| Hóa đơn và thanh toán | `decideRefund()`, `processRefund()`, phần doanh thu trong `dashboard()` |
| Thông báo | `broadcastNotification()` |
| Hạng phòng, phòng, chính sách (phát sinh ngoài danh sách) | `createCategory()`, `updateCategory()`, `toggleCategory()`, `deleteCategory()`, `quickRateOverride()`, `deleteRateOverride()`, `createRoom()`, `lockRoom()`, `unlockRoom()`, `updateRoomStatus()`, `savePoliciesBulk()` |

Mọi method đều mở đầu bằng `authorizeWeb(['admin'])` và ghi lại thao tác vào bảng `audit_logs`.