# CUST-002-UAT: Nghiệm thu khách hàng WordPress native

> RETIRED BY G-10 (2026-10-15). The specifications and old approvals below are historical; they do not authorize native-user implementation. BLOCKED is used because the workflow has no cancelled status. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md.

## Current handoff

- Workstream: Manual acceptance
- Status: BLOCKED
- Plan revision: 1
- Blueprint readiness: INCOMPLETE — G-10 retires this native-user direction; no further implementation or UAT.
- Owner: Tester
- Contributors and review mode: Backend Architect soạn ca kiểm thử; Tester thực hiện và quyết định nghiệm thu.
- Related checklist items: CUST-002 / AC1–AC4.
- Latest report: Native-user direction withdrawn by the Product Owner on 2026-10-15. Unaccepted runtime/test additions were removed after backup; earlier evidence is historical, not feature acceptance.
- Evidence: `ai-document/tasks/CUST-002-BE-native-customer-backend.md`; chưa có bằng chứng chạy ứng dụng.
- Temporary resource cleanup: Không tạo tài nguyên tạm.
- Next actor: Backend Architect
- Next actor and exact next action: Do not resume CUST-002. Follow CUST-001 restoration under G-10; this document is historical only.

## Môi trường và điều kiện bắt buộc

- LocalWP: https://velog.local/wp-admin/ ; tài khoản local nằm trong `.env.velog-local`, không đưa mật khẩu vào báo cáo.
- phpMyAdmin: http://localhost:10078/?username=root&db=local ; chỉ dùng đối chiếu khi ca kiểm thử cho phép, không sửa bảng bằng tay.
- Backend Architect ghi phiên bản thực tế của WordPress/PHP/database/VeLog, revision source, trình duyệt, locale, timezone và cấu hình cache trước handoff. Thông tin URL không chứng minh site hoặc thông tin đăng nhập đã được kiểm tra.
- Chuẩn bị tài khoản manager, technician, subscriber và bốn customer ở pending/invited/active/archived. Tài khoản admin local không thay thế kiểm thử quyền manager.
- Chuẩn bị bộ dữ liệu riêng có nhãn `CUST002-UAT`: tên Unicode dài, số điện thoại quốc tế, login/email duy nhất, email trùng với staff/archived customer, hơn 50 khách hàng, khách có/không có xe active.
- Email thử chỉ đi vào công cụ mail capture của LocalWP sau khi đã xác minh công cụ và tên miền test; không gửi email thật. Email mẫu dùng miền `.test`.
- Migration, concurrency và lỗi database chạy trên site/backup dùng một lần được cấp phép riêng; không chạy migration trên database LocalWP đang làm việc chỉ vì có URL phpMyAdmin.
- Trước khi READY, Backend Architect bổ sung nhãn nút, URL/action thực tế, fixture tạo dữ liệu và hướng dẫn hoàn nguyên. Bảng dưới là khung theo thiết kế đề xuất, không phải hướng dẫn cho tính năng đã tồn tại. Nếu thiếu điều kiện, ghi BLOCKED và trả phần chuẩn bị cho Backend Architect.

## Ca kiểm thử dự kiến

Các mong đợi về lifecycle/ownership còn phụ thuộc B2/B3; đồng bộ theo quyết định đã duyệt trước khi giao test.

| ID | Thao tác theo thứ tự | Kết quả mong đợi |
|---|---|---|
| T01 | Đăng nhập manager → VeLog → Customers → tạo `cust002-uat-01`, `cust002-uat-01@example.test`, tên `Nguyễn An 試験`, phone `+84 90 123 4567` → mở lại bản ghi | Một native WP_User, role customer, state pending; dữ liệu được giữ đúng, chưa gửi email; không có customer CPT mới |
| T02 | Tạo khách thiếu login; thiếu email; email sai; login bị sanitize thay đổi; lần lượt thử email hợp lệ dài 100 và 101 ký tự được chuẩn bị sẵn | Dữ liệu không hợp lệ bị từ chối rõ; không cắt email hoặc tạo tài khoản dở dang; giá trị ở biên được xử lý theo schema core |
| T03 | Dùng login/email của staff rồi customer archived để tạo khách khác; sửa khách mà giữ nguyên email của chính nó | Trùng bị từ chối, không gộp/đổi role staff; giữ email chính mình được phép |
| T04 | Mở cùng customer ở hai tab → lưu thay đổi phone tab B → lưu bản cũ tab A | Tab A báo stale; dữ liệu tab B và audit/version không bị ghi đè |
| T05 | Manager xác minh và mời → mở email trong mail capture → dùng native reset link đặt mật khẩu → đăng nhập bằng username rồi email | Chuyển invited rồi active sau reset hợp lệ; email được capture không đồng nghĩa đã phát tới hộp thư thật; manager không thấy mật khẩu/reset key |
| T06 | Thử đăng nhập pending/invited/archived và active; khi active đang đăng nhập, archive bằng phiên manager rồi tải lại phiên customer | Chỉ active được xác thực; cookie cũ sau archive bị từ chối; admin và tài khoản không thuộc customer vẫn hoạt động bình thường |
| T07 | Mời lại, thử link cũ/link hết hạn; thay email bằng manager rồi thử link cũ; thử reset khi pending/archived | Không kích hoạt bằng link cũ hoặc trạng thái không hợp lệ; không lộ thông tin nội bộ; việc mời lại có kết quả rõ |
| T08 | Archive khách không có xe active → restore → xem ID, thông tin và state | Giữ nguyên native ID/lịch sử; trở về pending theo đề xuất; không tự đăng nhập lại bằng phiên cũ |
| T09 | Thử archive khách có xe active → reassign/archive xe theo luồng đã duyệt → thử archive lại; thử gán xe cho pending/invited/archived | Có xe active thì từ chối không đổi dữ liệu; sau xử lý liên kết được archive; eligibility theo B2 đã duyệt. Thiếu VEH fixture: BLOCKED |
| T10 | Technician, subscriber, customer và anonymous mở trực tiếp trang quản lý/link sửa; manager gửi form có nonce sai qua DevTools | Không lộ contact hoặc cho phép mutation; dữ liệu không đổi; manager vẫn thao tác hợp lệ với nonce đúng |
| T11 | Mở native REST users, author URL và sitemap bằng anonymous/customer; kiểm tra native profile theo tài khoản được chuẩn bị | Không lộ customer qua endpoint công khai, không sửa meta/state/role để vượt quyền; quyền admin native được kiểm tra riêng |
| T12 | Tìm tên/phone/email, lọc state, trang 1 → 2 → 1 với >50 kết quả; thử bulk có một bản ghi stale | Kết quả ổn định, tối đa 50/trang; contacts chỉ cho phép đúng quyền; từng kết quả bulk chính xác; không gửi contact qua URL |
| T13 | Trên bản sao dùng một lần: chạy dry-run bộ legacy có thiếu login/email và trùng email, xem báo cáo rồi chạy lại | Không đổi source/native/relation, không gửi email; chỉ rõ từng conflict, không tự sinh login/gộp tài khoản |
| T14 | Trên bản sao: manager sửa mapping → duyệt → apply từng batch → chạy resume → đối chiếu liên kết và cutover | Không tạo user trùng; archived được giữ; legacy active thành pending; audit cũ giữ nguyên; conflict chưa xử lý chặn cutover; không nhầm CPT ID với user ID |
| T15 | Dùng keyboard và screen reader đi qua form, errors, state, bảng, actions và pagination; lặp với empty/error/success | Nhãn được đọc rõ, focus nhìn thấy, không mắc kẹt; thông báo không mất hoặc bị đè |
| T16 | Đặt viewport 320 CSS px, dùng dữ liệu Unicode dài; chuyển locale Arabic thực rồi lặp T12/T15; khôi phục locale | Các controls tới được, RTL đúng, bảng cuộn trong vùng hợp lý; toàn trang không tràn làm mất thao tác |
| T17 | DevTools Network tắt cache → reload Customers rồi Dashboard; thử trang invitation/migration khi đã có UI | Asset VeLog tải đúng phạm vi trang cho phép, không lỗi; không tải source SCSS hoặc lộ dữ liệu nhạy cảm |

## Bảng kết quả bắt buộc

Ghi ngày, revision, phiên bản môi trường, tài khoản/role, locale, viewport và công cụ hỗ trợ thực tế. Ảnh/video là tùy chọn; không gửi mật khẩu, nonce, cookie hoặc reset link.

| ID | Thực tế quan sát | PASS / FAIL / BLOCKED | Ghi chú |
|---|---|---|---|
| T01 | Chưa chạy | | |
| T02 | Chưa chạy | | |
| T03 | Chưa chạy | | |
| T04 | Chưa chạy | | |
| T05 | Chưa chạy | | |
| T06 | Chưa chạy | | |
| T07 | Chưa chạy | | |
| T08 | Chưa chạy | | |
| T09 | Chưa chạy | | |
| T10 | Chưa chạy | | |
| T11 | Chưa chạy | | |
| T12 | Chưa chạy | | |
| T13 | Chưa chạy | | |
| T14 | Chưa chạy | | |
| T15 | Chưa chạy | | |
| T16 | Chưa chạy | | |
| T17 | Chưa chạy | | |

Tester trả `PASS` khi toàn bộ ca bắt buộc đạt; hoặc `FAIL: <ID>, bước, thực tế, mong đợi`; hoặc `BLOCKED: <ID>, điều kiện thiếu`. Ca chưa chạy/blocked không được tính PASS. Kiểm thử tự động về XML-RPC, application password, race và failure injection phải có evidence backend trước handoff; chúng không thay thế các ca manual trên. Chỉ Tester quyết định DONE sau nghiệm thu rõ ràng.

### Chat handoff prompt

```text
Status: BLOCKED
Recipient: Backend Architect
Intent: work

G-10 retires CUST-002 without acceptance. Preserve historical evidence and follow CUST-001 restoration; do not implement native customer accounts, invitation/reset hooks, or migration. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md. No active-site database changes, commit, deploy, or release.
```
