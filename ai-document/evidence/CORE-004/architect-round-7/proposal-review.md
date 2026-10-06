# CORE-004 Architect proposal review — round 7

## Intake and decision

- Intake: PASS. Task, checklist, and README consistently identify `READY_FOR_REVIEW` and Architect as next actor. Builder round-7 proposal revision 5 and report exist. No escalated application code was changed.
- Decision: **REVISE PROPOSAL**. The implementation gate remains closed for CORE4-F-002, F-004, and F-005.
- Proposal revision 5 closes several prior planning gaps: it specifies the unconfigured envelope, negative option caches, production guard, JPY/USD/KWD sequence, line-length remediation, installed capability sniff, and a new evidence directory. The remaining defects below make the proposed implementation non-executable or unsafe.

## Blocking defects

### CORE4-F-002

1. The proposed PHP return declaration `?string|\WP_Error` is invalid syntax. PHP requires `string|null|\WP_Error`. An independent PHP syntax probe exits 255 at the union token.
2. The chosen first-insert query can still race on the unique `option_name` key. Proposal item 3 classifies every `$wpdb->query() === false` as `db_error`, so a duplicate-key race is not classified as `stale_version` as required. Specify the exact prepared SQL and exact duplicate detection, or choose a single query whose zero-row result represents conflict without suppressing unrelated SQL errors.

### CORE4-F-004

3. The proposed `currency_code` validator uses an arrow function with a statement block and `try/catch`; that is invalid PHP syntax. An independent syntax probe exits 255 with `unexpected token "{"`.
4. `currency_scale` still accepts any nonnegative integer independently of `currency_code`; it does not enforce catalog-derived scale. `RecordSchema` validators receive one field at a time, so the proposal must name a valid mechanism. For this fixed historical seed, use strict fixed validators for JPY and scale 0, or add a separate post-validation assertion before `RecordRepository::create()` without changing the production repository contract.
5. Proposal item 7 calls `RecordRepository::read()`, but no such public method exists. The repository public read API is `RecordRepository::get( string $type, int $id, WP_User $actor )`.
6. The HTTP plan is not integrated with the selected isolation runner. It hardcodes port 8080 while `product-smoke.sh` allocates a bounded random port, and it does not say how authenticated cookies and nonces are created. “403 (or WP die)” is not an exact oracle. Extend the existing CORE-004 `product-smoke.sh` path and fixture using its supplied port and isolated WordPress install; define exact expected response/status per case.

### CORE4-F-005

7. The cleanup proposal does not match `tests/workflow/product-smoke.sh`. That runner starts a private MariaDB datadir/socket and creates fixed database `velog_test` inside it; it does not create `velog_test_$$` on a shared server. Its existing cleanup stops the owned PHP and MariaDB PIDs and removes the owned `mktemp` directory.
8. The proposed `mysql -u root -e "DROP DATABASE ..."` omits the isolated socket. If implemented literally, it can connect to a system/default MariaDB instance and is therefore unsafe. Do not add this command. Extend or reuse the existing runner cleanup and its PID files/socket/datadir ownership.
9. `trap 'STATUS=$?; cleanup; exit $STATUS' EXIT INT TERM` does not preserve signal-specific 130/143 outcomes and combines signal and EXIT handling ambiguously. Use the established project pattern: EXIT performs cleanup once, INT exits 130, TERM exits 143, and the EXIT trap then preserves that status. Verify cleanup through the existing workflow cleanup controls.

## Repeated-defect status

| Stable finding | Status after revision 5 |
|---|---|
| CORE4-F-002 raw/CAS contract | Raw return concept is now clear, but the declared type is invalid and insert-race classification remains unresolved for another proposal round. |
| CORE4-F-004 canonical history oracle | KWD sequencing is now present; strict code/scale validation remains unresolved for a third proposal round, and the proposal names a nonexistent repository method. |
| CORE4-F-005 lint | Proposal coverage is now sufficient: all 20 line-length warnings, 6 capability warnings and 2 direct-query warnings have a planned disposition. Closure still requires implementation evidence with lint exit 0. |
| CORE4-F-005 cleanup | Still unresolved across five proposal revisions. Revision 5 introduces a potentially unsafe default-socket MySQL command instead of reusing the actual isolated runner lifecycle. |

Proposal reviews do not increment implementation-review failures. F-002/F-004/F-005 remain at two consecutive implementation review failures.

## Evidence inspected

- Builder round-7 proposal revision 5 and report.
- Architect round-6 decision and the current task verification contract.
- `tests/workflow/product-smoke.sh`, `RecordRepository.php`, `RecordSchema.php`, `CurrencyCatalog.php`, and the existing CORE-004 fixture.
- PHP syntax probes for both proposed constructs exited 255. No application or test code was changed by Architect.

## Required revision 6

Submit a read-only delta addressing only defects 1–9 above. Use valid PHP snippets, the real `RecordRepository::get()` API, the existing random-port/product-smoke lifecycle, and its private MariaDB socket/datadir cleanup. Do not repeat resolved sections. Do not implement F-002/F-004/F-005 until Architect records `APPROVED FOR IMPLEMENTATION`.

F-001, F-003, and F-006 remain unverified on the normal implementation path.
