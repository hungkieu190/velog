# <ID>-UAT: Kiểm thử chấp nhận <tên tính năng>

## Bàn giao hiện tại
- Workstream: Tester
- Status: <BLOCKED|READY|IN_PROGRESS|AWAITING_MANUAL_ACCEPTANCE|DONE>
- Owner: Tester / Product Owner
- Task cha: <ID>
- Điều kiện bắt đầu: Backend đã `SELF_REVIEWED_BACKEND`; frontend đã được Backend Architect review code nếu có giao diện.
- Next actor: <Backend Architect|Tester>
- Hành động tiếp theo chính xác:

## Mục tiêu kiểm thử

Mô tả bằng tiếng Việt hành vi người dùng cần xác nhận. Không dùng kết quả test tự động thay cho thao tác thực tế.

## Môi trường và tài khoản

- URL/site cần dùng.
- Phiên bản WordPress/PHP liên quan nếu cần.
- Vai trò tài khoản: manager, technician, subscriber hoặc anonymous.
- Dữ liệu chuẩn bị và cách hoàn nguyên dữ liệu test.

## Các trường hợp kiểm thử

| Bước | Thao tác | Dữ liệu test | Kết quả mong đợi | Kết quả thực tế | PASS/FAIL |
|---|---|---|---|---|---|

Phải bao gồm luồng chính, dữ liệu không hợp lệ, phân quyền, stale/concurrent khi liên quan, phục hồi lỗi, và kiểm tra giao diện responsive/RTL/keyboard/focus/long-content/asset-scope khi có UI.

## Cách báo kết quả

- Nếu tất cả bước đạt: `PASS <ID>-UAT` và ghi chú ngắn về môi trường đã test.
- Nếu có bước không đạt: `FAIL <ID>-UAT`, số bước lỗi, kết quả thực tế, kết quả mong đợi, và ảnh/video nếu giúp tái hiện.
- Tester không sửa code trong task UAT. Backend Architect nhận kết quả FAIL, phân loại BE/FE và mở lại đúng workstream.

### Chat handoff prompt

```text
Status: AWAITING_MANUAL_ACCEPTANCE
Recipient: Tester
Intent: accept

Thực hiện task <ID>-UAT theo đúng các bước tiếng Việt trong tài liệu. Trả `PASS <ID>-UAT` nếu tất cả kết quả đúng như mong đợi; nếu không, trả `FAIL <ID>-UAT` kèm số bước lỗi, kết quả thực tế, kết quả mong đợi và ảnh/video khi cần.
```
