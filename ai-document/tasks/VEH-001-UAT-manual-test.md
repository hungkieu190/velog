# VEH-001-UAT: Kiểm thử quản lý xe

## Approved correction — 2026-10-21

Tester requested a native Year dropdown and one system distance unit. Product Owner approved the proposed correction with "ok sửa". Revision 3 amendment in VEH-001-contract-v2.md overrides per-vehicle unit controls; no full manual acceptance was reported. Correction implementation/self-review and Tester acceptance are complete; prior round evidence remains historical.

## Current handoff
- Workstream: Tester
- Status: DONE
- Plan revision: 3
- Blueprint readiness: PASS — revision 3 code SELF_REVIEWED_DEVELOPMENT; kiểm thử tự động đạt, fixture cũ được giữ. Tester thực hiện kiểm thử lại; thiếu công cụ ghi BLOCKED.
- Owner: Tester / Product Owner
- Next actor: Developer
- Next actor and exact next action: Developer implements approved SERV-001 revision 2 after G-06 approval; VEH-001 is accepted DONE.
- Latest report: Tester explicitly accepted all vehicle tests/environments on 2026-10-21 and requested continuing development. VEH-001 is functionally DONE; SERV-001 G-06 and implementation were subsequently approved.
- Evidence: ai-document/evidence/VEH-001-BE/correction-round-2/report.md; original LocalWP preparation: ai-document/evidence/VEH-001-UAT/preparation-2026-10-21/report.md.

## Phạm vi chuẩn bị đã duyệt — 2026-10-21

Product Owner trả lời “OK làm luôn” cho đúng gói chuẩn bị đã trình bày: backup riêng, giữ hai staff cũ, tạo 3 Customer riêng, 51 Vehicle riêng và 1 subscriber test; credential/manifest nằm ngoài public/repo. Developer đã apply/verify; đây không phải nghiệm thu tính năng.

## Môi trường và dữ liệu đã chuẩn bị

1. Site: https://velog.local/wp-admin/; mở VeLog > Vehicles hoặc https://velog.local/wp-admin/admin.php?page=velog-vehicles. WordPress 7.1.3, VeLog 0.1.0, MySQL 8.4.0, PHP CLI 8.3.6. PHP web chưa xác minh độc lập. Login endpoint HTTP 200; CSS phục vụ từ site khớp production build, chưa kiểm tra render bằng browser.
2. Manager: `velog_uat_manager`, user ID 2, role `mf_velog_manager`. Technician: `velog_uat_technician`, user ID 3, role `mf_velog_technician`. Credential cũ: `/home/ecommercelife/.codex/private/velog/cust001-uat-credentials.json`. Không đổi role/password của staff cũ.
3. Subscriber dùng ca từ chối: `velog_vehicle_uat_denied`, user ID 4, role `subscriber`, không có VeLog capabilities. Credential: `/home/ecommercelife/.codex/private/velog/veh001-uat-manifest.json.credentials.json`. File 0600, thư mục 0700; không chép mật khẩu vào chat, kết quả hoặc ảnh. Dùng profile trình duyệt riêng cho từng role.
4. Customer A: `Vehicle UAT Owner A`, ID **66**, active. Customer B: `Vehicle UAT Owner B`, ID **67**, active. Customer Archived: `Vehicle UAT Owner Archived`, ID **68**, archived. Đây là hồ sơ private, không phải tài khoản WordPress. Dùng manager user ID **2** cho thử sai loại record ở T05.
5. Bộ T11: 51 xe active VIN `VELOG-VEH-UAT-PAGE-001`–`VELOG-VEH-UAT-PAGE-051`, ID **69–119**, liên kết Customer A **66**, metadata preferred unit km cũ được giữ để tương thích và không quyết định đơn vị hiển thị, baseline chưa biết. Tìm `VELOG-VEH-UAT-PAGE`, state Active: trang 1 có 50 dòng, trang 2 có 1 dòng. Backend đã xác minh; Tester vẫn phải kiểm tra UI/navigation thực tế.
6. Không sửa/archive/gán lại các xe **69–119** trước T11; dùng xe tự tạo ở T01/T02 cho T03–T10. T05 kết thúc bằng cách để xe chưa có Customer. T06 tạo xe riêng liên kết Customer B **67**; không dùng A **66** vì A đang có 51 xe active của bộ phân trang.
7. Tổng sau chuẩn bị: **4 WP users, 56 Customer, 52 Vehicle**. Ba tài khoản cũ, mọi role/password/usermeta cũ và 54 bản ghi domain cũ cùng meta được so sánh trước/sau và giữ nguyên. Không có lần gọi gửi mail WordPress. Giữ nguyên fixture CUST-001 Customer 64/Vehicle 65.
8. CSS SHA-256: `13a9bc27e6fd0d14795c64e43984eb15eea96b31eef90640ac4ca8ac6703b8f4`. Hard reload và kiểm Network theo T15. Dùng administrator sẵn có để đổi locale/scheme khi cần; chuẩn bị locale Arabic thật và screen reader. Thiếu công cụ thì ghi BLOCKED đúng ca.
9. Manifest: `/home/ecommercelife/.codex/private/velog/veh001-uat-manifest.json`; không chứa mật khẩu. Backup trước ghi được lưu riêng ngoài public/repo, xem report. Không chạy seed apply sau khi bắt đầu UAT: helper từ chối fixture đã thay đổi và không reset/xóa kết quả.

Ghi URL, WP/VeLog/trình duyệt, ngày, locale LTR/RTL và screen reader trong kết quả. Ảnh/video tùy chọn. Tester đã phản hồi Year khó nhập và đơn vị cần dùng Settings; đã duyệt sửa với “ok sửa”. Tester đã xác nhận đạt toàn bộ ca/môi trường và yêu cầu tiếp tục code ngày 2026-10-21. Bảng kết quả ghi PASS từ xác nhận này; không tự bổ sung chi tiết trình duyệt/công cụ chưa được cung cấp.

## Thứ tự kiểm thử lại revision 3

Bắt đầu T04 (Year), T07/T09 (đơn vị hệ thống), rồi T01/T08/T10/T12–T15 và các ca còn lại để hoàn tất nghiệm thu. Các form mở trước bản sửa phải reload. Giữ Settings ban đầu để khôi phục sau test; chỉ sửa xe test riêng, không seed lại hoặc xóa dữ liệu đang kiểm thử. Thiếu/hỏng Settings chỉ kiểm trên môi trường disposable.

## Các ca kiểm thử

| ID | Thao tác đánh số | Mong đợi |
|---|---|---|
| T01 | 1. Manager mở VeLog > Vehicles. 2. Tạo xe biển `กข 123 UAT`, jurisdiction `TH`, VIN trống, customer A; baseline trống, kiểm tra đơn vị lấy từ Settings. 3. Mở chi tiết và reload. | Tạo thành công, Unicode giữ nguyên, customer đúng, baseline hiển thị chưa biết; không tự tạo WP user. |
| T02 | 1. Tạo xe chỉ VIN `VELOG-VEH-UAT-002`. 2. Tạo thêm xe có cả biển/jurisdiction và VIN. 3. Tạo nhiều xe VIN trống có biển khác nhau. | Cả ba loại được chấp nhận; identifier trống không gây xung đột giả. |
| T03 | 1. Tạo lại VIN/biển cùng chuẩn hóa (đổi hoa/thường/space theo policy). 2. Thử cùng biển khác jurisdiction. 3. Archive xe gốc rồi thử lại. 4. Edit giữ identifier của chính nó. | Trùng cùng khóa bị từ chối kể cả archived; khác jurisdiction được phép; giữ identifier chính nó được phép. |
| T04 | 1. Thử thiếu cả biển/VIN, thiếu một phần cặp biển/jurisdiction, markup, VIN >64. 2. Kiểm tra Year là dropdown có mục để trống và các năm giảm dần từ năm hiện tại +1 đến 1886; chọn năm, lưu, reload, rồi xóa lựa chọn. 3. Dùng DevTools sửa option thành 1885 hoặc năm hiện tại +2 rồi gửi. 4. Lặp trên Edit. | Năm hợp lệ được lưu/giữ lựa chọn, để trống được phép; năm giả và dữ liệu sai bị từ chối an toàn, có thông báo, dữ liệu cũ giữ nguyên. |
| T05 | 1. Với xe tự tạo ở T01, gán Customer A 66 rồi đổi sang B 67. 2. Thử ID không tồn tại/manager user ID 2 không phải Customer/Customer Archived ID 68. 3. Để không có customer. | Gán active đúng; từ chối liên kết không hợp lệ; cho phép chưa gán; không lộ phone/email trong Vehicles. |
| T06 | 1. Tạo xe riêng VIN `VELOG-VEH-UAT-ARCHIVE-CASE` liên kết B 67; bảo đảm các xe T05 đã bỏ liên kết B. 2. Thử archive B tại Customers. 3. Archive xe riêng này rồi archive B. 4. Restore xe khi B archived. 5. Restore B rồi restore xe. | Active link chặn archive Customer; sau archive xe thì cho phép; restore xe bị từ chối tới khi Customer active hoặc quan hệ được sửa hợp lệ. |
| T07 | 1. Manager ghi lại đơn vị Settings ban đầu, đặt km. 2. Tạo baseline 0/ngày hôm nay và 100/ngày hôm qua. 3. Chuyển Settings sang mi, mở form mới, tạo xe riêng baseline 1/ngày hôm qua. 4. Thử ngày tương lai, số âm, quá 3 số thập phân, thiếu ngày. 5. Khôi phục Settings ban đầu. | 0 khác chưa biết; không có bộ chọn đơn vị riêng cho xe hoặc baseline; đơn vị ghi mới và hiển thị theo Settings, ngày không dịch; dữ liệu sai bị từ chối. |
| T08 | 1. Edit baseline đã biết thành thấp hơn, để reason trống rồi gửi. 2. Nhập reason và gửi lại. 3. Thử xóa baseline với/không reason. | Giảm/xóa cần manager reason; dữ liệu và audit giữ thay đổi có lý do. Không mô phỏng physical reset. |
| T09 | 1. Đặt Settings km, tạo xe riêng baseline 100 km và ghi ngày. 2. Giữ form Edit ở tab A, đổi Settings sang mi ở tab B. 3. Tab A lưu: phải bị từ chối do đơn vị đã đổi; không diễn giải lại 100 thành 100 mi. 4. Reload: form/list/detail hiển thị 62.137 mi; chỉ đổi model rồi lưu. 5. Dùng Technician xem list/detail: cùng đơn vị mi. 6. Đổi Settings về km: số đo vẫn đúng 100.000 km, ngày giữ nguyên. 7. Kiểm tra mọi xe khác cùng đơn vị Settings; thử gửi thêm baseline_unit/preferred_odometer_unit=km khi Settings là mi: không được ghi đè cấu hình. 8. Khôi phục Settings ban đầu. | Tất cả màn hình xe dùng cùng cấu hình hệ thống; quy đổi hiển thị đúng. Lưu model không làm tròn ngược thành quãng đường khác, không yêu cầu reason giả; original tuple/canonical lịch sử giữ nguyên. Form cũ bị từ chối với hướng dẫn reload; đơn vị gửi giả không có hiệu lực. Thiếu/hỏng Settings được kiểm bằng fixture disposable, không làm hỏng cấu hình site chính. |
| T10 | 1. Mở cùng xe ở tab A/B. 2. A lưu đổi model. 3. B chưa reload lưu đổi color. 4. Gửi lại Create đã gửi. | B bị stale conflict, A không mất dữ liệu; replay Create không nhân đôi hồ sơ. |
| T11 | 1. Tìm bằng biển/VIN/jurisdiction, lọc active/archived. 2. Tìm `VELOG-VEH-UAT-PAGE`, chọn Active; dùng bộ 51 xe ID 69–119 đi trang 1 →2 →1. 3. Tìm chuỗi không khớp. | Search đúng, tối đa 50 dòng/trang, filter giữ nguyên, không mất/lặp dòng khi dữ liệu không đổi, empty state rõ. |
| T12 | 1. Technician mở list/detail. 2. Thử URL edit và POST mutation với nonce hợp lệ được chuẩn bị trong môi trường test. 3. Subscriber/anonymous thử list/detail. | Technician chỉ xem, không có form mutation hay contact; mọi ghi bị từ chối; subscriber/anonymous không đọc được dữ liệu private. Developer hỗ trợ negative fixture khi thiếu nonce đúng actor, không coi login redirect là bằng chứng đủ. |
| T13 | 1. Manager đổi hidden nonce thành invalid rồi gửi update/archive. 2. Thử vehicle_id/version sai. 3. Thử detail ID không tồn tại. | 403 hoặc thông báo lỗi an toàn; bản ghi không đổi; không fallback âm thầm sang Create, không lộ SQL. |
| T14 | 1. Chỉ dùng Tab/Shift+Tab/Enter/Space qua form/search/list/actions/pagination. 2. Lặp ở 320px với chuỗi Unicode dài và empty/error/success. | Labels/focus rõ, thứ tự hợp lý, không mắc kẹt; trang không tràn ngang, bảng có thể cuộn trong vùng nhưng mọi action tới được. |
| T15 | 1. Đổi locale Arabic thật, lặp T11/T14. 2. Đổi admin color scheme. 3. Dùng screen reader đọc form/table/notices. 4. Kiểm Network trên Vehicles và Dashboard. 5. Khôi phục locale/scheme. | RTL/focus/control phù hợp, labels/status đọc có nghĩa; asset VeLog chỉ trên allowlisted screens, không tải trên Dashboard WordPress. Thiếu công cụ ghi BLOCKED. |

Giao dịch đồng thời và lỗi SQL được BE kiểm chứng trên môi trường disposable; không yêu cầu Tester gây hỏng site chính. Nếu cần kiểm tra ranh giới service_history_locked, Developer phải cung cấp fixture finalized-service trên site disposable; không nghiệm thu SERV-001 trong task này.

## Kết quả thực tế

| ID | Thực tế | PASS / FAIL / BLOCKED | Ghi chú |
|---|---|---|---|
| T01 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T02 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T03 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T04 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T05 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T06 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T07 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T08 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T09 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T10 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T11 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T12 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T13 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T14 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |
| T15 | Tester xác nhận đạt toàn bộ các ca/môi trường ngày 2026-10-21 | PASS | Không tự suy diễn chi tiết trình duyệt/công cụ |

## Quyết định

Trả `PASS VEH-001-UAT — T01–T15 đạt; môi trường: …; tôi nghiệm thu VEH-001`, hoặc `FAIL VEH-001-UAT — T…; thao tác: …; thực tế: …; mong đợi: …`, hoặc `BLOCKED VEH-001-UAT — T…; thiếu: …`. Chỉ Tester quyết định DONE; code/build pass không thay manual.

## Tester acceptance — 2026-10-21

Tester explicitly accepted all testing environments and instructed continuing implementation on 2026-10-21: "đã pass hết các môi trường kiểm thử; làm tiếp code". VEH-001 T01–T15 accepted; no unreported per-browser observations are invented.

### Chat handoff prompt

```text
Status: DONE
Recipient: Developer
Intent: work

Continue approved SERV-001 revision 2 under ai-document/service-workflow-contract.md; VEH-001 is accepted DONE by Tester. Preserve prior evidence and data. No commit, deploy or release.
```
