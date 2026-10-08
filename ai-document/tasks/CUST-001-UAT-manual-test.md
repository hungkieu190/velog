# CUST-001-UAT: Kiểm thử thủ công quản lý khách hàng

## Current handoff
- Workstream: Tester
- Status: AWAITING_MANUAL_ACCEPTANCE
- Plan revision: 2
- Test round: 2 (T02 chờ test lại)
- Blueprint readiness: PASS
- Owner: Tester / Product Owner
- Task cha: CUST-001
- Điều kiện bắt đầu: BE đã SELF_REVIEWED_BACKEND; FE Round 2 đã được review code APPROVED.
- Latest report: T01 PASS; T02 lần đầu FAIL. Backend đã sửa và kiểm tra tự động đạt trên MySQL/MariaDB; chờ Tester test lại T02.
- Evidence: Kết quả do Tester ghi vào bảng dưới hoặc trả trong chat; ảnh/video tùy chọn.
- Next actor: Tester
- Next actor and exact next action: Reload Customers và test lại T02; sau đó T04/T06/T07/T09/T10/T13 và các bước chưa chạy.

## Chuẩn bị

1. Dùng WordPress local/test có plugin VeLog và asset Round 2. Không dùng dữ liệu thật. Ghi URL site, phiên bản WordPress/plugin, trình duyệt, ngày test và locale trong kết quả. Tải lại trang không dùng cache sau khi cập nhật plugin.
2. Chuẩn bị tài khoản `mf_velog_manager` (kiểm thử thao tác) và `mf_velog_technician` (kiểm thử từ chối quyền). Dùng hai profile trình duyệt để tránh nhầm phiên đăng nhập. Administrator dùng để đổi locale khi cần.
3. Khách hàng mẫu: tên `Nguyễn Thị Khách Hàng 測試`, phone `+66 81 234 5678`, email `customer.uat@example.com`. Tạo thêm khách hàng khác cùng thông tin để kiểm tra không tự gộp.
4. Chuỗi dài: `KhachHangCoTenRatDaiKhongCoKhoangTrangDeKiemTraXuongDong0123456789測試Nguyễn`; email `customer.long.unicode.test.0123456789@example.com`. Giữ tên dưới 200 ký tự, email hợp lệ dưới 254 ký tự.
5. Phân trang cần ít nhất 51 khách hàng khớp cùng bộ lọc. Dùng dữ liệu test sẵn có hoặc tạo bằng form các tên `UAT Customer 001` đến `UAT Customer 051`, email `uat001@example.com` đến `uat051@example.com`. Nếu chưa có dữ liệu, báo BLOCKED T11 để Backend Architect chuẩn bị; không yêu cầu Frontend Developer tạo fixtures.
6. T08 cần một khách hàng đang liên kết active vehicle qua luồng VEH-001. Nếu luồng này chưa sẵn sàng, ghi BLOCKED_BY_VEH-001, không coi là PASS hoặc tự bỏ bước.
7. T17 cần locale RTL thật (ví dụ Arabic) và T18 cần screen reader khả dụng. Thiếu môi trường/công cụ thì ghi BLOCKED kèm lý do. Ảnh/video không bắt buộc; mô tả quan sát thực tế là bắt buộc.com

## Test lại sau sửa T02

1. Reload trang VeLog > Customers (PHP backend đã sửa, không cần build CSS).
2. Nhập tên `Nguyễn Thị Khách Hàng 測試`, phone `+66 81 234 5678`, email `customer.uat@example.com`; bấm Create Customer.
3. Mong đợi: trở về Customers, hiện `Customer saved.`, có đúng một dòng khách hàng mới với dữ liệu vừa nhập.
4. Mở Edit, đổi phone thành `+66 81 234 5679`, lưu và reload; mong đợi phone mới được giữ nguyên.
5. Trả `T02 PASS` nếu cả tạo và sửa đạt; nếu lỗi, ghi thực tế và URL sau submit (không gửi nonce/token). Không bắt buộc ảnh.
6. Chạy các bước liên quan T04/T06/T07/T09/T10/T13 và tiếp tục phần còn lại. T01 đã PASS không bị xóa; T02 cũ giữ FAIL trong lịch sử đến khi có kết quả test lại.

## Thao tác và kết quả mong đợi

| ID | Thao tác cụ thể | Kết quả mong đợi |
|---|---|---|
| T01 | Đăng nhập manager, mở VeLog > Customers | Có form, search/filter, bulk action và bảng; không hiển thị lỗi PHP; nhãn và controls đọc được. |
| T02 | Tạo khách hàng mẫu, tìm lại bằng tên rồi mở Edit; sửa phone thành `+66 81 234 5679`, bấm Update Customer và tải lại | Có thông báo thành công; tên Unicode/email giữ nguyên; phone mới được lưu đúng sau tải lại. |
| T03 | Tạo khách hàng thứ hai cùng tên/phone/email | Có hai bản ghi riêng (đối chiếu ID trong link Edit), không ghi đè/gộp tự động. |
| T04 | Lần lượt gửi tên rỗng, email `invalid-email`, tên `<b>Alice</b>`; thử cả tạo mới và sửa bản ghi mẫu | Dữ liệu không hợp lệ bị từ chối bằng validation/notice an toàn; không có bản ghi mới sai hoặc cập nhật sai. Lỗi đọc được, không có markup thực thi; bản ghi cũ giữ nguyên. |
| T05 | Tìm lần lượt bằng một phần tên Unicode, phone, email; chọn Active rồi Archived | Kết quả khớp từ khóa và trạng thái; dữ liệu liên hệ hiển thị đúng cho manager. |
| T06 | Mở cùng link Edit ở hai tab A/B; A đổi tên và lưu; B chưa reload, đổi phone và lưu | B nhận lỗi stale/conflict; dữ liệu A không bị ghi đè. Tải lại để xác nhận. |
| T07 | Archive một khách hàng không có active vehicle; lọc Archived rồi Restore | Trạng thái đổi đúng; tên/phone/email được giữ nguyên; xuất hiện lại ở Active. |
| T08 | Với khách hàng có active vehicle, bấm Archive | Bị từ chối với hướng dẫn xử lý liên kết; cả customer và vehicle không bị sửa. Thiếu luồng VEH-001: BLOCKED_BY_VEH-001. |
| T09 | Chọn hai khách hàng active rồi bulk Archive và Restore. Với lượt lỗi hỗn hợp: chọn hai dòng ở tab A; đổi phiên bản một dòng ở tab B rồi Apply tại A | Lượt hợp lệ cập nhật từng bản ghi. Lượt hỗn hợp báo đúng số thành công/thất bại; dòng stale không bị ghi đè; dòng hợp lệ vẫn được xử lý. |
| T10 | Tìm `NO_MATCH_UAT_20261008`; sau đó thử dữ liệu lỗi T04 và dữ liệu đúng T02 | Empty state, error notice/validation và success notice đều đọc được, không bị cắt hoặc đè controls; có thể tiếp tục thao tác. |
| T11 | Với ít nhất 51 kết quả, mở trang 1 → 2 → 1, thử Next/Previous và số trang; lặp lại với bộ lọc/từ khóa vẫn khớp >50 kết quả | Tối đa 50 dòng/trang; không lặp/mất dòng khi dữ liệu không thay đổi; bộ lọc được giữ; trang hiện tại phân biệt rõ, link và focus dùng được. |
| T12 | Đăng nhập technician; mở trực tiếp `/wp-admin/admin.php?page=velog-customers` và link Edit đã sao chép từ manager | Bị từ chối, không lộ name/phone/email trong UI customer; không thể tạo/sửa/archive/restore qua trang này. |
| T13 | Chỉ trên site test: manager mở form Edit; trong DevTools Elements đổi giá trị hidden `velog_customer_nonce` thành `invalid`, rồi submit | Request bị từ chối; tải lại bằng phiên hợp lệ để xác nhận bản ghi không đổi. Không gửi hoặc chia sẻ nonce thật trong báo cáo. |
| T14 | Không dùng chuột: Tab/Shift+Tab qua form tạo, form sửa, Search/filter, bulk select/Apply, từng checkbox, Edit/Archive/Restore và pagination; Enter/Space để kích hoạt controls thích hợp | Tất cả controls tới được theo thứ tự hợp lý, focus nhìn rõ, không mắc kẹt; thao tác tương đương dùng chuột. |
| T15 | DevTools responsive width 320 CSS px; tạo/tìm bản ghi chuỗi dài; cuộn xuống tận bảng và pagination; thử Edit, Archive/Restore | Toàn trang không tràn ngang. Bảng có thể cuộn ngang bên trong nhưng mọi cột/actions phải tới được; chữ dài không làm mất controls; nút không chồng nhau. Không chỉ quan sát phần form đầu trang. |
| T16 | Ở 320px, Tab qua các action ngoài vùng bảng đang nhìn thấy, qua pagination; lặp lại empty/error/success T10 | Control được focus phải nhìn thấy và thao tác được; focus không bị cắt, notice và trạng thái rỗng vẫn đọc được. |
| T17 | Chuyển locale WordPress/người dùng sang Arabic thật, tải lại admin; lặp T10, T11, T14–T16 rồi khôi phục locale | Form/bảng/actions/pagination đúng hướng RTL, không chồng/cắt; keyboard và cuộn bảng vẫn dùng được. Ghi locale thực tế, không chỉ thêm CSS `rtl`. |
| T18 | (a) DevTools Network: tắt cache, reload Customers, lọc `admin.css`; reload Dashboard. (b) Dùng screen reader duyệt nhãn form, checkbox, lỗi, bảng và pagination | (a) Asset VeLog `.../plugins/velog/assets/css/admin.css` tải trên Customers và không tải trên Dashboard; asset WordPress khác không tính. VeLog Overview/Settings được phép tải. (b) Tên/nhãn/trạng thái/control được đọc có ý nghĩa và có thể điều hướng; ghi rõ lỗi/nhãn thiếu nếu gặp. |

## Ghi kết quả

- Môi trường/URL: …
- WordPress / VeLog / trình duyệt: …
- Ngày test / locale LTR và RTL / screen reader: …
- Bản đang test: CUST-001-FE Round 2 (CSS SHA-256 `a48ba01f7e1a3539e0b3eb7f3d1e816e873ceb39fd08e6e8ddabd9ece3dd453a`).

| ID | Thực tế quan sát | PASS / FAIL / BLOCKED | Ghi chú (ảnh tùy chọn) |
|---|---|---|---|
| T01 | Người dùng xác nhận Done. | PASS | Kết quả do Tester cung cấp. |
| T02 | Nhập dữ liệu, bấm tạo khách hàng nhưng admin không có phản hồi; phần sửa chưa thực hiện. | FAIL lần 1; chờ retest | Backend triage: `../evidence/CUST-001-BE/uat-t02-triage/report.md`. |
| T03 | Chưa kiểm thử | | |
| T04 | Chưa kiểm thử | | |
| T05 | Chưa kiểm thử | | |
| T06 | Chưa kiểm thử | | |
| T07 | Chưa kiểm thử | | |
| T08 | Chưa kiểm thử | | |
| T09 | Chưa kiểm thử | | |
| T10 | Chưa kiểm thử | | |
| T11 | Chưa kiểm thử | | |
| T12 | Chưa kiểm thử | | |
| T13 | Chưa kiểm thử | | |
| T14 | Chưa kiểm thử | | |
| T15 | Chưa kiểm thử | | |
| T16 | Chưa kiểm thử | | |
| T17 | Chưa kiểm thử | | |
| T18 | Chưa kiểm thử | | |

## Quyết định của Tester

- PASS: tất cả T01–T18 đã thực hiện và đạt. Tester quyết định chuyển CUST-001-UAT, CUST-001-FE và CUST-001 sang DONE; agent chỉ đồng bộ sau xác nhận đó.
- FAIL: ghi ID bước, thao tác, thực tế, mong đợi. Backend Architect phân loại BE/FE để sửa; sau review chuyển lại Tester kiểm thử.
- BLOCKED: ghi rõ dữ liệu, tài khoản, công cụ hoặc phụ thuộc thiếu. Không đánh PASS cho bước chưa chạy. Nếu còn bước BLOCKED thì chưa nghiệm thu toàn bộ; việc giảm phạm vi cần quyết định riêng của Product Owner.
- Không bắt buộc chụp ảnh. Không cần Frontend Developer test thay. Build/code review không thay thế quyết định manual của Tester.

Có thể trả kết quả trong chat theo mẫu:

`PASS CUST-001-UAT — môi trường: …; T01–T18 đạt; tôi xác nhận chuyển CUST-001, CUST-001-FE, CUST-001-UAT sang DONE.`

`FAIL CUST-001-UAT — T…; thao tác: …; thực tế: …; mong đợi: …; môi trường: ….`

`BLOCKED CUST-001-UAT — T…; thiếu: …; các bước đã chạy/kết quả: ….`

### Chat handoff prompt

```text
Status: AWAITING_MANUAL_ACCEPTANCE
Recipient: Tester
Intent: accept

T02 backend correction is SELF_REVIEWED_BACKEND. Reload Customers and repeat T02: create a Unicode customer, confirm the success notice and saved row, edit the phone, save, and reload to confirm persistence. Then run T04, T06, T07, T09, T10, and T13 for related regressions. T01 remains PASS; the original T02 FAIL remains recorded until your retest. Evidence: ai-document/evidence/CUST-001-BE/backend-round-2/report.md. Report PASS/FAIL with actual results; only Tester may authorize DONE after the remaining UAT cases are accepted.
```
