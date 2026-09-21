# Verification Mapping - Round 5

| Finding | Case | Function/File | Result |
|---|---|---|---|
| F-003 | V1 | `tests/workflow/core001-smoke-controls.sh` / `velog_early_warning_detector` | Shared detector finds actual VeLog notice, ignores other domain, clean normal gate succeeds; contaminated normal output fails. |
| F-003 | V2 | `tests/workflow/core001-smoke-controls.sh` / `normal_gate` | Normal gate rejects nonzero wp_exit and missing/unset detector input. |
| F-004 | V3 | `tests/workflow/core001-smoke-controls.sh` / `reserve_work_dir` | Allocation creates different private dirs, cleanup removes only owned trees, sentinel survives; allocation failure exits nonzero without cleanup of unowned paths. |
| F-004 | V4 | `tests/workflow/core001-smoke-controls.sh` / `wait_ready` | Same readiness helper returns nonzero; dead-child case stops promptly; timeout finishes promptly. Caller cleanup stops/reaps fixture child. |
| F-004 | V5 | `tests/workflow/core001-smoke-controls.sh` / `wait_ready` | Ready path returns 0; injected failure preserved through cleanup; child reaped before directory removal. Signal exit preserved. |
| F-003 / F-004 | V6 | `tests/workflow/core001-smoke.sh` | Exact Xin Chào translation for both 6.4.3 and 6.7.2; normal gate passes; actual 6.7.2 warning fails that gate in replay; bounded startup and graceful shutdown confirmed. |
| Quality | V7 | `composer run lint`, `composer run test`, `bash -n`, `git diff --check` | Exit 0 for all. PHPUnit: 21 tests, 35 assertions. |
