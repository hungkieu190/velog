# REM-001-TEST: Nợ kiểm thử hoãn lại

- Status: DRAFT
- Owner: Developer
- Next actor: Developer
- Debt: TEST_DEBT
- Scheduling: DEFERRED
- Authority: Product Owner 2026-10-21; chỉ chuẩn bị/chạy khi mở lại giai đoạn test/fix.
- Implementation evidence: ../evidence/CODE-001/report.md

## Phạm vi cần kiểm thử sau

1. Manager-only page/POST, nonces, invalid inputs, stale versions, request replay and denied actors.
2. Date-only/distance-only/OR triggers, unknown versus zero, exact unit/locale conversion and dates/timezones.
3. Future snooze, expiry without GET writes, resume, terminal completion actor/time, immutable vehicle.
4. Archive suspension/restore, baseline changes, finalization/correction and current-index coherence.
5. Failure/rollback, simultaneous writers, missing/duplicate/corrupt projections, caches and queue count/pagination.
6. Responsive/RTL/keyboard/screen-reader/long content; validate G-07 product semantics with owner.

## Chuẩn bị và kết quả

Chưa chạy case nào trong đợt CODE-001. Khi mở lại test, Developer chuẩn bị môi trường cô lập, tài khoản manager/technician/subscriber, dữ liệu synthetic, ma trận PHP/WP và các bước cụ thể trước khi chạy. Không sửa dữ liệu site đang dùng nếu chưa có phạm vi cho phép. Mỗi case ghi PASS, FAIL hoặc BLOCKED, kết quả thực tế và bằng chứng; mở lỗi liên kết cho FAIL. Không suy ra PASS từ build hoặc kiểm tra cú pháp.

| Case | Kết quả | Kết quả thực tế / bằng chứng / lỗi |
| --- | --- | --- |
| 01 | DEFERRED | Chưa thực hiện |
| 02 | DEFERRED | Chưa thực hiện |
| 03 | DEFERRED | Chưa thực hiện |
| 04 | DEFERRED | Chưa thực hiện |
| 05 | DEFERRED | Chưa thực hiện |
| 06 | DEFERRED | Chưa thực hiện |

### Chat handoff prompt

```text
Status: DRAFT
Recipient: Developer
Intent: deferred-test-fix

Keep REM-001-TEST deferred in ai-document/test-debt.md. Prepare concrete cases and prerequisites only when Product Owner resumes the consolidated test/fix phase; preserve actual PASS/FAIL/BLOCKED evidence without inferred acceptance. No commit, deploy or release.
```
