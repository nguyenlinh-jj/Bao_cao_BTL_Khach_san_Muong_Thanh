# MODULE LỄ TÂN - Lê Hoài Linh

**Nhóm 8 - Website đặt phòng khách sạn Mường Thanh Luxury Phú Quốc**
Bản code nộp: `php-mvc` (30 tệp PHP, 23 bảng CSDL, 71 route)

---

## Công việc theo phân công

| Mục | Nội dung |
|---|---|
| Bảng điều khiển | Trang tổng hợp đơn, phòng, khách đang ở, việc trong ngày |
| Đặt phòng | Danh sách đặt phòng và sơ đồ phòng; tạo đơn tại quầy, xác nhận, gán phòng, xử lý yêu cầu đổi/hủy, check-in, điều chỉnh lưu trú, check-out |
| Dịch vụ | Quản lý và điều phối dịch vụ (tiếp nhận, hoàn tất, hủy) |
| Thanh toán | Ghi nhận thanh toán, đối soát chuyển khoản, duyệt và chi hoàn tiền |
| Phát sinh ngoài danh sách | Sự cố phòng, phiếu buồng phòng |


---

## Tệp trong folder

| Tệp | Dòng | Nội dung |
|---|---|---|
| `ReceptionController.php` | 737 | 21 hàm (kèm khai báo const/thuộc tính của lớp): nhận request, kiểm quyền `authorizeWeb(['receptionist','admin'])`, gọi service, trả view hoặc JSON |
| `BookingWorkflowService.php` | 1.867 | 18 hàm nghiệp vụ bàn Lễ tân (bản trích từ service 2.760 dòng dùng chung) |
| `Refund.php` | 118 | Model hồ sơ hoàn tiền: danh sách chờ duyệt, chi tiết, tạo bảng khi thiếu |

thuộc folder nào. Bản trích không chạy độc lập, dùng để đọc và đối chiếu với tệp gốc.

---



## Cách đọc

1. Mở đầu `ReceptionController.php` để thấy 21 hàm giữ lại kèm số dòng trong tệp gốc.
2. Mỗi hàm ứng với một route `/reception/...` trong `lead\index.php`.
3. Luồng nghiệp vụ chi tiết (khóa phòng, tính tiền, đối soát) nằm trong các hàm cùng tên ở
   `BookingWorkflowService.php` trong folder này.
4. Giao diện tương ứng: `lead\view-reception-dashboard.php`.
