# SERV-001-UAT: Kiểm thử phiếu dịch vụ

## Code-first scheduling — 2026-10-21

- Debt: TEST_DEBT
- Scheduling: DEFERRED
- Implementation checkpoint: SERV-001 code complete with prior technical evidence; functional acceptance is not granted.
- Current instruction: CODE-001 completed the remaining application code. Keep tests/fixes deferred until Product Owner resumes that phase.

## Current handoff
- Workstream: Deferred test debt
- Status: DRAFT
- Plan revision: 2
- Blueprint readiness: PASS — revision 2 technically self-reviewed; LocalWP login/CSS and read-only account/data prerequisites reverified after restart.
- Owner: Developer (debt scheduling; future acceptance owner to be confirmed when testing resumes)
- Next actor: Developer
- Next actor and exact next action: Developer retains deferred consolidated test/fix debt under CODE-001; no current Tester action.
- Evidence: ai-document/evidence/SERV-001/round-1/.

## Môi trường đã khôi phục — 2026-10-21

Bạn đã bật lại site VeLog. Developer xác minh read-only: login **HTTP 200**, CSS phục vụ **HTTP 200** khớp production SHA-256 `32aa67f878a39d2290184b3149e387fada768417f8c4d7df7008f1802abc3eed`; xe 69 active/Customer 66 active, baseline chưa biết; Settings km/USD/scale 2; catalog loại dịch vụ vẫn trống; quyền manager/technician/subscriber đúng. Block HTTP 502 đã được gỡ. Xem `local-restored-http.json` và `local-restored-prerequisites.json` trong evidence. Không seed, sửa dữ liệu hoặc đổi password.

**Khi mở lại giai đoạn test mới bắt đầu T01**; xác minh lại prerequisites và reload cứng trình duyệt. Browser/RTL/screen reader do Tester xác minh; thiếu công cụ ghi BLOCKED đúng ca. Chưa có manual PASS cho SERV-001.

## Môi trường, tài khoản và dữ liệu nền

1. Site: https://velog.local/wp-admin/. WordPress 7.1.3, VeLog 0.1.0, MySQL 8.4.0. PHP CLI/isolated tests 8.3.6; PHP web chưa xác minh độc lập. Ghi rõ trình duyệt/locale/ngày và công cụ screen reader trong kết quả.
2. Manager `velog_uat_manager` ID 2; Technician `velog_uat_technician` ID 3; Subscriber `velog_vehicle_uat_denied` ID 4. Đã xác minh capabilities bằng read-only. Giữ nguyên role/password; dùng profile riêng. Credential vẫn ở các file private đã cung cấp cho VEH-001; không chép password vào báo cáo.
3. Xe test **69**, active, đang liên kết Customer A **66**, baseline chưa biết. Developer đã đọc và xác minh; không sửa/seed lại dữ liệu. VEH-001 đã được bạn nghiệm thu nên bộ pagination cũ có thể dùng xe này cho hành trình dịch vụ riêng. Trước khi test kiểm tra lại ID/trạng thái; khác dữ liệu ghi BLOCKED và trả Developer, không tự sửa dữ liệu thật.
4. Settings tại lúc chuẩn bị: **km / USD / 2 chữ số thập phân**. Ghi lại cấu hình trước các ca chuyển đơn vị/currency và khôi phục sau test. Nếu không đúng cấu hình nền này, ghi giá trị thực tế trước khi đổi bằng Settings.
5. Catalog loại dịch vụ ban đầu có **0** loại. **T01 tạo loại bằng chính chức năng cần kiểm thử**, không cần script SQL/backend fixture. Không có service nào được Developer tạo trên site thật. Ghi lại ID của loại và từng phiếu do bạn tạo trong bảng kết quả; chỉ thao tác dữ liệu có nhãn UAT.
6. Không cần user Technician thứ hai: manager tạo một nháp ở T02 để kiểm tra Technician không đọc nháp của người khác; Technician tạo nháp của mình để manager kiểm tra quyền xử lý.
7. Dữ liệu hỏng, SQL failure, subprocess concurrency và bộ pagination 51 nháp đã được kiểm bằng fixture disposable. Không gây hỏng dữ liệu hoặc tạo 51 phiếu trên site thật chỉ để lặp assertion. Nếu cần tái hiện kỹ thuật, trả Developer chuẩn bị môi trường disposable; không đánh dấu manual PASS thay cho thao tác chưa làm.

## Các ca kiểm thử

| ID | Thao tác đánh số | Mong đợi |
|---|---|---|
| T01 | 1. Manager mở VeLog > Service types. 2. Tạo `UAT Oil change`, active; ghi ID loại. 3. Thử tạo trùng tên và tên rỗng/markup. 4. Technician thử mở trang qua menu/URL. | Tạo hợp lệ có version; duplicate/dữ liệu sai bị từ chối. Technician không quản lý được loại; không có delete/native taxonomy/public route. |
| T02 | 1. Manager mở Services, tạo nháp xe 69, notes `UAT MANAGER PRIVATE DRAFT`, để ngày/reading/type/cost trống; ghi ID D0 và URL. 2. Technician mở list và URL D0. 3. Technician tạo nháp của mình D1 cho xe 69, notes `UAT TECH DRAFT`. 4. Manager mở và sửa notes D1; Technician reload. | Nháp chưa đủ dữ liệu vẫn lưu được với xe active; D0 không xuất hiện cho Technician, URL D0 không lộ nội dung hoặc form Create thay thế. Manager sửa nháp của Technician được; người tạo D1 vẫn là Technician. |
| T03 | 1. Technician mở D1, nhập ngày `2020-01-02`, reading `1000`, loại UAT Oil change, cost `12.30` USD. 2. Save draft rồi Finalize service. 3. Reload và ghi ID S1. 4. Technician tìm nút sửa; Manager mở lại. | S1 finalized, chụp Customer A và tên loại ở thời điểm chốt, reading 1000 km và cost 12.30 USD. Technician chỉ xem finalized; manager có correction form/reason/audit. Không lộ phone/email. |
| T04 | 1. Dùng D0 thử Finalize thiếu loại/ngày/reading. 2. Thử ngày tương lai, số âm, exponent, reading >3 chữ số thập phân, số sai locale, markup notes. 3. Thử xe ID không tồn tại hoặc ID Customer 66 thay vehicle_id. | Từ chối an toàn, không chốt nháp sai; unknown không bị biến thành zero. Có thông báo cố định, không lộ SQL. Lỗi gửi form trả về dữ liệu đã lưu; nếu nhập notes dài hãy giữ bản sao để không mất nội dung khi request bị từ chối. |
| T05 | 1. Tạo service cho xe 69 ngày `2020-01-04`, reading `1200`, chốt, ghi S2. 2. Tạo backdated `2020-01-03`, reading `1100`, chốt, ghi S3. 3. Filter Services vehicle ID 69. 4. Tạo/chốt thêm `2020-01-04`, reading `1300`, ghi S4. | Sau S3, current vẫn 1200 km từ S2. Sau S4, cùng ngày thì ID cao hơn là mới hơn, current 1300 km. Baseline chưa biết không so như zero. |
| T06 | 1. Manager sửa S4 thành ngày `2020-01-01`, reading `900`; thử reason trống rồi nhập `UAT correct original service date`. 2. Filter xe 69 và reload. 3. Xem audit S4. | Thiếu reason bị từ chối; có reason lưu được. Current tính lại là S2/1200 km ngày 2020-01-04. Audit giữ đầy đủ before/after/actor/time; ngày không bị dịch timezone. Không đổi creator hoặc customer snapshot. |
| T07 | 1. Technician tạo nháp ngày `2020-01-05`, reading `800`, loại active; thử Finalize với cả reason. 2. Manager mở nháp đó, thử thiếu reason, rồi nhập `UAT documented odometer discrepancy` và Finalize. | Technician không được tự vượt chronology. Manager chỉ được chốt ngoại lệ có reason; current theo phiếu/date mới nhất, không tự giả định reset công-tơ-mét vật lý. |
| T08 | 1. Manager mở Vehicles xe 69, đặt baseline ngày `2020-01-01`, reading `500`; lưu. 2. Thử baseline `2000` cùng ngày không reason, rồi khôi phục 500 nếu đã thay. 3. Reload Services filter xe 69. | Baseline hợp lệ vẫn sửa được dù có finalized service; baseline mâu thuẫn bị từ chối với hướng dẫn kiểm tra ngày/số đo hoặc nhập manager reason. Current vẫn theo finalized service mới hơn. Không còn chặn mọi xe chỉ vì một xe có lịch sử. |
| T09 | 1. Manager đổi tên loại thành `UAT Renamed Oil`, inactive. 2. Mở lại S1; thử chốt nháp mới chọn loại cũ. 3. Reactivate loại. 4. Đổi tên Customer A 66 thành tên test rồi mở S1; khôi phục tên Customer A. | S1 vẫn giữ tên loại/khách tại lúc chốt. Inactive type không cho chốt phiếu mới; không xóa lịch sử. Đổi thông tin khách/loại hiện tại không sửa snapshot cũ. Chỉ sửa Customer test 66, không dữ liệu thật. |
| T10 | 1. Giữ form S1 ở tab A với km/USD. 2. Tab B đổi Settings sang mi. 3. A gửi correction: phải bị từ chối do Settings đổi. 4. Reload S1: 1000 km hiển thị 621.371 mi. 5. Chỉ sửa notes với reason rồi lưu. 6. Khôi phục km; mở lại S1. | Form cũ không diễn giải 1000 thành 1000 mi. Mọi service dùng cùng đơn vị hệ thống; notes-only save giữ nguyên quãng đường, trả về 1000.000 km khi khôi phục. Không có unit selector theo phiếu/xe. |
| T11 | 1. Đổi Settings sang JPY, tạo nháp test cost `100`; thử `1.23`. 2. Đổi sang KWD, tạo nháp cost `1.234`; thử `1.2345`. 3. Mở lại S1 và nháp JPY. 4. Khôi phục USD và đơn vị gốc. | Precision đúng currency; amount sai bị từ chối. Chi phí S1 vẫn USD, nháp JPY vẫn JPY, không relabel/convert/sum. Blank cost khác zero; unknown hiển thị chưa ghi nhận. Ngày/reading/notes không đổi vì Settings. |
| T12 | 1. Manager archive xe 69 sau khi lưu trạng thái cần khôi phục. 2. Thử tạo/sửa/chốt nháp gắn xe 69. 3. Xem S1, manager sửa notes với reason. 4. Restore xe 69. | Chặn draft mutations/finalization trên xe archived. Finalized history vẫn đọc được, correction có reason được phép và không tự restore xe. Restore cuối ca không xóa lịch sử. |
| T13 | 1. Mở S1 ở tab A/B; A correction notes có reason rồi lưu, B gửi version cũ. 2. Với draft mới, giữ lại request Create và gửi lại. 3. Dùng DevTools sửa nonce/ID/version rồi gửi; Subscriber/anonymous mở Services/Service types. | Stale không ghi đè A; replay Create không nhân đôi. Nonce sai bị 403; ID/version sai bị từ chối an toàn. Subscriber/anonymous không đọc dữ liệu. Các case nonce đúng actor nhưng thiếu capability đã có HTTP disposable evidence; nếu cần tái hiện trả Developer. |
| T14 | 1. Filter theo xe 69 và draft/finalized, kiểm empty result. 2. Chỉ dùng Tab/Shift+Tab/Enter/Space trên form, dropdown, actions, filter, audit và vùng bảng. 3. Thử ở 320px với notes/tên loại Unicode dài; đổi admin color scheme. | Filter đúng và giữ khi điều hướng; label/focus rõ, không mắc kẹt. Controls không làm tràn toàn trang; bảng cuộn trong vùng, audit dài tự xuống dòng. Native WordPress controls giữ color scheme. |
| T15 | 1. Dùng locale RTL thật và screen reader kiểm form/type/table/notices. 2. Kiểm Network asset trên Services, Service types và Dashboard WordPress. 3. Khôi phục locale/scheme/Settings gốc. | Nhãn/trạng thái được đọc có nghĩa; RTL/focus/chuyển đổi decimal đúng locale. CSS VeLog tải trên allowlist, không trên Dashboard WordPress. Không có screenshot bắt buộc; thiếu công cụ ghi BLOCKED đúng ca. |

## Kết quả thực tế

| ID | Thực tế / ID phiếu / môi trường | PASS / FAIL / BLOCKED | Ghi chú |
|---|---|---|---|
| T01 | Chưa kiểm thử | | |
| T02 | Chưa kiểm thử | | |
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

## Quyết định

Trả `PASS SERV-001-UAT — T01–T15 đạt; môi trường: …; tôi nghiệm thu SERV-001`, hoặc `FAIL SERV-001-UAT — T…; thao tác: …; thực tế: …; mong đợi: …`, hoặc `BLOCKED SERV-001-UAT — T…; thiếu: …`. Tester một mình quyết định DONE. Nếu dữ liệu nền/prerequisite khác, Developer chuẩn bị lại theo phạm vi duyệt cụ thể; không tự reset các quan sát UAT.

### Chat handoff prompt

```text
Status: DRAFT
Recipient: Developer
Intent: deferred-test-fix

CODE-001 completed the approved application code queue. Keep SERV-001 acceptance as TEST_DEBT / DEFERRED until Product Owner resumes the consolidated test/fix phase. Preserve prior evidence and do not mark deferred tests PASS. No commit, deploy or release.
```
