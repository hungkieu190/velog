# CUST-001-UAT: Kiểm thử thủ công quản lý khách hàng

> G-10 restores this private customer-record contract. CUST-002 is retired. This update does not accept the feature or overwrite prior Tester results.

## Current handoff
- Workstream: Tester
- Status: AWAITING_MANUAL_ACCEPTANCE
- Plan revision: 2
- Test round: 2 (T02 chờ test lại)
- Blueprint readiness: PASS — code approved and LocalWP accounts/data verified; browser/tool prerequisites and manual acceptance belong to Tester.
- Owner: Tester / Product Owner
- Task cha: CUST-001
- Điều kiện bắt đầu: BE round 3 SELF_REVIEWED_BACKEND; FE round 3 review 2 APPROVED; tài khoản/dữ liệu LocalWP đã chuẩn bị và xác minh. Tester cung cấp trình duyệt, locale RTL thật và screen reader; thiếu công cụ thì ghi BLOCKED.
- Latest report: LocalWP preparation verified; see ai-document/evidence/CUST-001-UAT/preparation-2026-10-15/report.md. No new manual results; historical T01 PASS and T02 FAIL pending retest are preserved.
- Evidence: Kết quả do Tester ghi vào bảng dưới hoặc trả trong chat; ảnh/video tùy chọn.
- Next actor: Tester
- Next actor and exact next action: Execute CUST-001-UAT T01–T20 on the prepared LocalWP site, retest T02, and report actual PASS/FAIL/BLOCKED; only Tester may accept the feature.

## Kiểm kê LocalWP trước chuẩn bị — 2026-10-15 (lịch sử, chỉ đọc)

- Site trả HTTP 200; WordPress đang nạp 7.1.3, MySQL 8.4.0. PHP CLI kiểm tra là 8.3.6; cấu hình LocalWP khai báo PHP web 8.2.29, chưa xác minh trực tiếp runtime web.
- Có 1 hồ sơ Customer; chưa có bộ `UAT Customer 001`–`051`.
- Chưa có tài khoản mang role `mf_velog_manager` hoặc `mf_velog_technician`; admin hiện có không thay thế ca kiểm tra quyền technician.
- Chưa có xe active liên kết Customer để chuẩn bị T08. Màn hình VEH-001 chưa được nghiệm thu; Backend Architect phải chuẩn bị fixture liên kết qua repository sau khi được phép, không yêu cầu FE tự tạo.
- Đây là BLOCKED về chuẩn bị, không phải kết quả FAIL/PASS của tính năng. Backend Architect phụ trách chuẩn bị trước khi giao Tester. Tại thời điểm kiểm kê này chưa tạo dữ liệu/tài khoản mới; kết quả sau chuẩn bị được ghi bên dưới.

## Chuẩn bị

1. Sau khi FE correction được review và Backend Architect xác nhận đủ prerequisites, dùng LocalWP tại `https://velog.local/wp-admin/`, plugin VeLog theo bản khôi phục G-10 và asset Round 3. Tham khảo [môi trường local](../local-environment.md); không ghi mật khẩu vào kết quả. Không dùng dữ liệu thật. Ghi URL site, phiên bản WordPress/plugin, trình duyệt, ngày test và locale trong kết quả. Tải lại trang không dùng cache sau khi cập nhật plugin.
2. Dùng tài khoản `velog_uat_manager` với role `mf_velog_manager` (kiểm thử thao tác) và `velog_uat_technician` với role `mf_velog_technician` (kiểm thử từ chối quyền). Mật khẩu xem file riêng ở phần bàn giao bên dưới. Dùng hai profile trình duyệt để tránh nhầm phiên đăng nhập. Administrator dùng để đổi locale khi cần.Kinh
3. Khách hàng mẫu: tên `Nguyễn Thị Khách Hàng 測試`, phone `+66 81 234 5678`, email `customer.uat@example.com`. Tạo thêm khách hàng khác cùng thông tin để kiểm tra không tự gộp.
4. Chuỗi dài: `KhachHangCoTenRatDaiKhongCoKhoangTrangDeKiemTraXuongDong0123456789測試Nguyễn`; email `customer.long.unicode.test.0123456789@example.com`. Giữ tên dưới 200 ký tự, email hợp lệ dưới 254 ký tự.
5. T11 đã có 51 khách hàng `UAT Customer 001`–`051` (ID 13–63), email `uat001@example.com`–`uat051@example.com`. Tìm `UAT Customer ` với Active. Không tạo thêm bộ trùng tên hoặc thay đổi bộ này trước T11. Nếu dữ liệu không đủ, báo BLOCKED để Backend Architect kiểm tra.
6. T08 cần một khách hàng đang liên kết xe active được Backend Architect chuẩn bị qua repository; ghi ID customer/vehicle trước khi bàn giao. Chỉ kiểm tra thao tác Archive tại Customers, không yêu cầu màn hình VEH-001 và không nghiệm thu Vehicle. Nếu chưa có fixture, ghi BLOCKED và trả Backend Architect chuẩn bị; không tự bỏ bước.
7. T17 cần locale RTL thật (ví dụ Arabic) và T18 cần screen reader khả dụng. Thiếu môi trường/công cụ thì ghi BLOCKED kèm lý do. Ảnh/video không bắt buộc; mô tả quan sát thực tế là bắt buộc.

## Phạm vi chuẩn bị dữ liệu đã duyệt — 2026-10-15

Product Owner đã duyệt thực hiện bằng yêu cầu “vậy là đến bạn xử lý à, làm đi thôi”. Phạm vi ghi LocalWP chỉ gồm các fixtures dưới đây; không mở rộng sang dữ liệu khác.

1. Tạo mới hai tài khoản test `velog_uat_manager` và `velog_uat_technician` với đúng role tương ứng. Không đổi role/mật khẩu tài khoản có sẵn, không tạo tài khoản cho Customer, không gửi email. Mật khẩu ngẫu nhiên chỉ lưu trong file local ngoài repository và public web root với quyền 0600; không ghi vào task/evidence.
2. Tạo 51 hồ sơ giả `UAT Customer 001`–`051`, contact dùng dữ liệu mẫu `example.com` hoặc để trống; giữ ID/version trong manifest để chạy lại không nhân đôi. Không sửa hồ sơ hiện có.
3. Tạo riêng `UAT Linked Customer` và một xe active có VIN test riêng, liên kết qua repository đúng schema/khóa dùng chung. Ghi ID để Tester thực hiện T08; fixture không phải nghiệm thu VEH-001.
4. Nếu username/VIN/manifest đụng dữ liệu chưa xác định quyền sở hữu, dừng và báo xung đột; không tự gộp/ghi đè/xóa. Giữ dữ liệu sau test, không tự cleanup customer/xe.
5. Chạy lại kiểm kê chỉ đọc, ghi tên tài khoản/ID fixture và đường dẫn file mật khẩu local trước khi giao Tester. Phạm vi này đã thực hiện và xác minh theo phần bàn giao bên dưới.

## Approved preparation implementation scope

- Backend Architect owns `tests/fixtures/cust-001-uat-seed.php`, `tests/workflow/cust-001-uat-seed.sh`, the CUST-001-only invocation in `tests/workflow/product-smoke.sh`, synchronized task/checklist/README/environment documentation, and evidence under `ai-document/evidence/CUST-001-UAT/preparation-2026-10-15/`.
- CLI-only plan/apply/verify; exact site/actor checks, private credentials, ownership manifest, conflict refusal, stable request IDs, repository writes, blocked mail, and non-owned-data preservation checks. Test on disposable WordPress before authorized LocalWP apply after a private database backup. No runtime PHP, JS or CSS changes.

## Bàn giao dữ liệu đã chuẩn bị — 2026-10-15

1. Mở `https://velog.local/wp-admin/`. Môi trường: LocalWP, WordPress 7.1.3, VeLog 0.1.0, MySQL 8.4.0. PHP CLI 8.3.6; web PHP cấu hình 8.2.29, runtime web chưa xác minh độc lập. Chỉ tài khoản staff dùng hệ thống đăng nhập WordPress; Customer vẫn là hồ sơ riêng.
2. Hai tài khoản mới: `velog_uat_manager` (user ID 2, role `mf_velog_manager`) và `velog_uat_technician` (user ID 3, role `mf_velog_technician`). Mật khẩu tại `/home/ecommercelife/.codex/private/velog/cust001-uat-credentials.json` (file 0600, thư mục 0700, ngoài public/repository). Không chép mật khẩu vào kết quả, chat hoặc ảnh.
3. T11: tìm `UAT Customer `, trạng thái Active. Có đúng 51 hồ sơ `UAT Customer 001`–`051`, ID 13–63; truy vấn backend đã xác nhận trang 1 có 50 dòng, trang 2 có 1 dòng. Tester vẫn phải kiểm tra UI và navigation thực tế. Không sửa/archive bộ dữ liệu phân trang trước T11.
4. T08: `UAT Linked Customer`, customer ID 64; vehicle ID 65 đang active, VIN fixture `VELOG-UAT-CUST001-20261015`. Bấm Archive tại Customers phải bị từ chối. Không cần màn hình Vehicle. Không sửa/xóa cặp fixture này trước T08.
5. Customer có sẵn và thông tin đăng nhập/role tài khoản cũ đã được so sánh trước/sau và giữ nguyên. Tổng hiện tại: 53 Customer, 1 xe active có liên kết; không gửi mail qua WordPress. Manifest không chứa mật khẩu. Không chạy lại seed sau khi bắt đầu UAT vì helper sẽ từ chối dữ liệu đã thay đổi; không có cơ chế reset/xóa fixtures.
6. CSS cần kiểm tra là Round 3, SHA-256 `a651d52dd770433291508b939c5c1792c05953f2ebff253cb3a77bfcf267be89`. Hard reload, kiểm tra asset trên Network theo T18. Chuẩn bị locale Arabic thật và screen reader cho T17/T18; thiếu thì trả BLOCKED đúng ca, không bỏ qua.
7. Evidence: `ai-document/evidence/CUST-001-UAT/preparation-2026-10-15/report.md`, `local-verify.json`, `local-inventory-after.json`. Đây là xác minh chuẩn bị tự động, không phải kết quả UAT. Giữ lịch sử T01 PASS, T02 FAIL và test lại T02; chỉ ghi DONE sau nghiệm thu rõ ràng của Tester.

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
| T03 | Tạo khách hàng thứ hai cùng tên/phone/email; tạo thêm một khách chỉ có tên, để trống phone/email | Các hồ sơ có ID riêng, không tự gộp; hồ sơ chỉ có tên lưu được. Form không yêu cầu username, mật khẩu hoặc kích hoạt tài khoản. |
| T04 | Lần lượt gửi tên rỗng, email `invalid-email`, tên `<b>Alice</b>`; thử cả tạo mới và sửa bản ghi mẫu | Dữ liệu không hợp lệ bị từ chối bằng validation/notice an toàn; không có bản ghi mới sai hoặc cập nhật sai. Lỗi đọc được, không có markup thực thi; bản ghi cũ giữ nguyên. |
| T05 | Tìm lần lượt bằng một phần tên Unicode, phone, email; chọn Active rồi Archived; bấm Clear search | Kết quả khớp từ khóa/trạng thái; Clear search về danh sách mặc định. URL chỉ chứa `customer_search`, không chứa từ khóa/phone/email. |
| T06 | Mở cùng link Edit ở hai tab A/B; A đổi tên và lưu; B chưa reload, đổi phone và lưu | B nhận lỗi stale/conflict; dữ liệu A không bị ghi đè. Tải lại để xác nhận. |
| T07 | Archive một khách hàng không có active vehicle; lọc Archived rồi Restore | Trạng thái đổi đúng; tên/phone/email được giữ nguyên; xuất hiện lại ở Active. |
| T08 | Với khách hàng có active vehicle, bấm Archive | Bị từ chối với hướng dẫn xử lý liên kết; cả customer và vehicle không bị sửa. Thiếu fixture liên kết: BLOCKED, Backend Architect chuẩn bị. |
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

## Kiểm tra bổ sung theo design system và tìm kiếm riêng tư

| ID | Thao tác cụ thể | Kết quả mong đợi |
|---|---|---|
| T19 | Manager tìm bằng email mẫu; sao chép URL kết quả, mở bằng profile administrator khác đã đăng nhập; sửa mã `customer_search` thành một UUID khác và mở lại bằng manager | URL không có email/phone/từ khóa. Tài khoản khác hoặc mã không còn hiệu lực nhận lỗi tìm kiếm; không tự trả danh sách toàn bộ. Không gửi token thật trong báo cáo. |
| T20 | Đổi admin color scheme giữa Default và một scheme khác trên tài khoản test; vào Customers, Tab qua input, Search/Clear search, nút và phân trang; sau đó khôi phục scheme | Controls và focus giữ ngôn ngữ WordPress, màu scheme hoạt động; trạng thái hiện tại có chỉ dấu ngoài màu. Không còn tiêu đề dạng panel trang trí, viền/focus màu VeLog ép cứng lên controls. |

## Ghi kết quả

- Môi trường/URL: …
- WordPress / VeLog / trình duyệt: …
- Ngày test / locale LTR và RTL / screen reader: …
- Bản cần test sau khi chuẩn bị đủ: backend round 3 và CUST-001-FE round 3 review 2 APPROVED. CSS SHA-256 `a651d52dd770433291508b939c5c1792c05953f2ebff253cb3a77bfcf267be89`; browser asset loading vẫn phải được Tester kiểm tra. Round 2 và T01/T02 cũ chỉ là lịch sử.

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
| T19 | Chưa kiểm thử | | |
| T20 | Chưa kiểm thử | | |

## Quyết định của Tester

- PASS: tất cả T01–T20 đã thực hiện và đạt. Tester quyết định chuyển CUST-001-UAT, CUST-001-FE và CUST-001 sang DONE; agent chỉ đồng bộ sau xác nhận đó.
- FAIL: ghi ID bước, thao tác, thực tế, mong đợi. Backend Architect phân loại BE/FE để sửa; sau review chuyển lại Tester kiểm thử.
- BLOCKED: ghi rõ dữ liệu, tài khoản, công cụ hoặc phụ thuộc thiếu. Không đánh PASS cho bước chưa chạy. Nếu còn bước BLOCKED thì chưa nghiệm thu toàn bộ; việc giảm phạm vi cần quyết định riêng của Product Owner.
- Không bắt buộc chụp ảnh. Không cần Frontend Developer test thay. Build/code review không thay thế quyết định manual của Tester.

Có thể trả kết quả trong chat theo mẫu:

`PASS CUST-001-UAT — môi trường: …; T01–T20 đạt; tôi xác nhận chuyển CUST-001, CUST-001-FE, CUST-001-UAT sang DONE.`

`FAIL CUST-001-UAT — T…; thao tác: …; thực tế: …; mong đợi: …; môi trường: ….`

`BLOCKED CUST-001-UAT — T…; thiếu: …; các bước đã chạy/kết quả: ….`

### Chat handoff prompt

```text
Status: AWAITING_MANUAL_ACCEPTANCE
Recipient: Tester
Intent: accept

Run T01–T20 in ai-document/tasks/CUST-001-UAT-manual-test.md on https://velog.local/wp-admin/. Retest T02; preserve historical T01 PASS/T02 FAIL. Backend round 3 is SELF_REVIEWED_BACKEND; FE round 3 review 2 is APPROVED for code only. LocalWP accounts and fixtures are verified; see ai-document/evidence/CUST-001-UAT/preparation-2026-10-15/report.md and the UAT task for private credentials and fixture IDs. Record actual PASS/FAIL/BLOCKED, environment and missing RTL/screen-reader prerequisites. Only explicit Tester acceptance permits DONE. No commit, deploy or release.
```
