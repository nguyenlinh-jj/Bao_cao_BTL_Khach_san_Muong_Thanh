# MODULE KHÁCH HÀNG - Nguyễn Thị Thu Huyền

**Nhóm 8 - Website đặt phòng khách sạn Mường Thanh Luxury Phú Quốc**
## Công việc theo phân công
| Mục | Nội dung |
|---|---|
| Đặt phòng | Chọn hạng phòng và gói, chọn ngày, tính giá, mã giảm giá, tạo đơn, yêu cầu đổi hoặc hủy |
| Dịch vụ lưu trú | Xem danh mục dịch vụ, đặt dịch vụ trong thời gian lưu trú, theo dõi trạng thái |
| Hóa đơn | Đơn đặt phòng, dịch vụ đã dùng, hóa đơn và thanh toán |
| Thông báo và hồ sơ | Thông báo của khách, chỉnh sửa hồ sơ cá nhân, đổi mật khẩu |
| Khác | Tra cứu đơn theo mã hoặc số điện thoại, đánh giá sau lưu trú |
## Tệp trong folder
| Tệp |Nội dung |
|---|---|
| `CustomerController.php` | 13 hàm: đơn của tôi, dịch vụ tại phòng, mã giảm giá, đặt phòng, đổi/hủy, đặt dịch vụ, đánh giá, hồ sơ, mật khẩu, thông báo |
| `HomeController.php` | 5 hàm: trang chủ, danh sách phòng, nhà hàng, spa, tra cứu đơn (bản trích 5/7 hàm) |
| `BookingWorkflowService.php` | 7 hàm nghiệp vụ đặt phòng: sức chứa, chính sách hủy, mã giảm giá, tạo đơn, đổi/hủy, đặt dịch vụ |
| `RoomCategory.php` | Tính giá hạng phòng theo ngày, phụ thu gói, phòng trống theo khoảng ngày |
| `Booking.php` | Truy vấn cơ bản trên bảng bookings |

Hai tệp `HomeController.php` và `BookingWorkflowService.php` là bản trích, đầu tệp ghi rõ hàm nào giữ lại
và phần còn lại thuộc folder nào.
