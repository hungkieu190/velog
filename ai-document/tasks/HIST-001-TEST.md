# HIST-001-TEST: Nợ kiểm thử hoãn lại

- Status: DRAFT
- Owner: Developer
- Next actor: Developer
- Debt: TEST_DEBT
- Scheduling: DEFERRED
- Authority: Product Owner 2026-10-21; chỉ chuẩn bị/chạy khi mở lại giai đoạn test/fix.
- Implementation evidence: ../evidence/CODE-001/report.md

## Phạm vi cần kiểm thử sau

1. Finalized-only history, private contacts, technician/manager/subscriber denial boundaries.
2. Inclusive date/type/text filters, same-day ID ordering, 0/50/51 rows and totals.
3. Snapshot immutability after rename/archive/reassignment, correction reason/actor/before-after, Unicode and original currencies.
4. System unit/locale changes, unknown/zero/baseline current source and 10000-row bound.
5. Malformed/duplicate/missing projections, concurrent reads/writes and no per-row relation lookups.
6. Responsive/RTL/keyboard/screen-reader/long content and asset loading.

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

Keep HIST-001-TEST deferred in ai-document/test-debt.md. Prepare concrete cases and prerequisites only when Product Owner resumes the consolidated test/fix phase; preserve actual PASS/FAIL/BLOCKED evidence without inferred acceptance. No commit, deploy or release.
```
