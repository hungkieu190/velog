# CUST-001-UAT: Kiểm thử thủ công quản lý khách hàng

## Current handoff
- Workstream: Tester
- Status: BLOCKED
- Plan revision: 1
- Test round: 0
- Blueprint readiness: PASS
- Owner: Tester / Product Owner
- Task cha: CUST-001
- Điều kiện bắt đầu: CUST-001-BE đã `SELF_REVIEWED_BACKEND`; CUST-001-FE phải được Backend Architect review code và chấp thuận.
- Latest report: Chưa kiểm thử.
- Evidence: `ai-document/evidence/CUST-001-UAT/manual-round-1/`.
- Next actor: Backend Architect
- Next actor and exact next action: Review CUST-001-FE; nếu code frontend đạt, chuyển CUST-001 và task này sang AWAITING_MANUAL_ACCEPTANCE và giao Tester thực hiện các bước bên dưới.

## Mục tiêu kiểm thử

Xác nhận bằng thao tác thực tế rằng manager có thể tạo, tìm, sửa, archive và restore khách hàng; dữ liệu liên hệ không lộ cho technician; lỗi nhập liệu, stale update và liên kết vehicle được xử lý đúng; giao diện dùng được bằng bàn phím, màn hình hẹp và RTL.

## Môi trường và tài khoản

- Dùng site WordPress test/local có plugin VeLog phiên bản đang review. Không dùng dữ liệu production.
- Một tài khoản administrator hoặc `mf_velog_manager`.
- Một tài khoản `mf_velog_technician`.
- Chuẩn bị hai tab trình duyệt đăng nhập manager để kiểm tra stale update.
- Dữ liệu mẫu:
  - Tên: `Nguyễn Thị Khách Hàng 測試`
  - Điện thoại: `+66 81 234 5678`
  - Email: `customer.uat@example.com`
  - Chuỗi dài: `KhachHangCoTenRatDaiKhongCoKhoangTrangDeKiemTraXuongDong0123456789`

## Các trường hợp kiểm thử

| Bước | Thao tác | Kết quả mong đợi |
|---|---|---|
| 1 | Đăng nhập manager, mở **VeLog > Customers** | Trang tải thành công; có form tạo khách hàng, tìm kiếm, bảng danh sách và bulk action; không có lỗi PHP/JavaScript hiển thị |
| 2 | Tạo khách hàng bằng bộ dữ liệu Unicode mẫu | Hiển thị thông báo thành công; bản ghi xuất hiện đúng tên, phone, email và trạng thái `active` |
| 3 | Tạo khách hàng thứ hai có cùng tên hoặc phone/email | Hệ thống cho phép tạo; hai bản ghi phân biệt bằng ID, không ghi đè nhau |
| 4 | Thử tên rỗng, email sai định dạng, chuỗi chứa markup như `<b>Alice</b>` | Không tạo/cập nhật bản ghi; hiển thị lỗi an toàn; dữ liệu cũ không thay đổi |
| 5 | Tìm theo một phần tên Unicode, phone và email; lọc `active`/`archived` | Chỉ trả đúng bản ghi phù hợp; trạng thái và thứ tự ổn định; không vượt quá 50 dòng mỗi trang |
| 6 | Mở cùng khách hàng ở hai tab; tab A cập nhật trước, tab B gửi dữ liệu cũ | Tab A thành công; tab B bị từ chối stale update và không ghi đè dữ liệu mới |
| 7 | Archive khách hàng chưa có active vehicle, sau đó restore | Archive chuyển sang `archived`; restore về `active`; dữ liệu name/phone/email được giữ nguyên |
| 8 | Khi VEH-001 có luồng liên kết, gắn một active vehicle với khách hàng rồi thử archive | Archive bị từ chối và yêu cầu reassign/archive vehicle trước; customer và vehicle không bị thay đổi |
| 9 | Chọn nhiều khách hàng và chạy bulk archive/restore, gồm ít nhất một bản ghi không hợp lệ cho thao tác | Mỗi khách hàng được xử lý riêng; thông báo đúng số thành công/thất bại; lỗi một bản ghi không làm báo sai các bản ghi khác |
| 10 | Đăng nhập technician và thử mở/truy cập trực tiếp trang customer | Technician không thấy phone/email và không thể tạo, sửa, archive hoặc restore customer |
| 11 | Dùng Tab/Shift+Tab qua form, search, row action, bulk action và pagination | Thứ tự focus hợp lý; focus nhìn thấy rõ; thao tác không cần chuột |
| 12 | Thu cửa sổ về 320px và dùng tên/chuỗi dài mẫu | Không có cuộn ngang toàn trang, nội dung không bị cắt, action vẫn dùng được |
| 13 | Chuyển WordPress sang locale RTL thật, ví dụ Arabic | Form, bảng, notice, action và pagination đúng hướng, không chồng lấn hoặc mất nội dung |
| 14 | Mở Dashboard hoặc admin page không thuộc VeLog | Asset customer/VeLog không được tải ngoài các page hook đã cho phép |

## Ghi kết quả thực tế

| Bước | Kết quả thực tế | PASS/FAIL | Bằng chứng/ghi chú |
|---|---|---|---|
| 1–14 | Tester điền sau khi thực hiện | | |

## Tiêu chí chấp nhận

- Tất cả bước bắt buộc phải PASS. Bước 8 chỉ được đánh `BLOCKED_BY_VEH-001` nếu VEH-001 chưa có UI liên kết; trường hợp đó CUST-001 chưa được dùng để tuyên bố hành trình customer-vehicle hoàn chỉnh.
- Không có lỗi làm lộ phone/email cho technician hoặc cho phép mutation trái quyền.
- Backend Architect review code không thay thế kết quả trong task này.

## Cách trả kết quả

- Thành công: `PASS CUST-001-UAT — đã test trên <môi trường>, các bước 1–14 đạt`.
- Thất bại: `FAIL CUST-001-UAT — bước <số>; thực tế: <kết quả>; mong đợi: <kết quả>` và đính kèm ảnh/video nếu cần.

### Chat handoff prompt

```text
Status: BLOCKED
Recipient: Backend Architect
Intent: review

CUST-001-UAT đã được chuẩn bị bằng tiếng Việt nhưng chưa được phép chạy. Hãy review code CUST-001-FE trước. Nếu frontend đạt, chuyển CUST-001 và CUST-001-UAT sang AWAITING_MANUAL_ACCEPTANCE, đặt Next actor là Tester, rồi gửi nguyên prompt kiểm thử UAT cho Tester. Không đánh dấu CUST-001 DONE nếu chưa có phản hồi `PASS CUST-001-UAT` rõ ràng.
```
