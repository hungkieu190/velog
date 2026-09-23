# CORE-003 Verification (Round 2)

| IDs | Entry / fixture | Required observable result | Actual result |
|---|---|---|---|
| F-001 / C3-V1 | `composer run test -- --filter AccessPolicyTest` | Full action/type/actor matrix, valid positives and all reported bypasses denied; exit 0. | Passed. Unit tests assert correct types. |
| F-002 / C3-V3 | Real capability fixture via both pinned product-smoke runs; lifecycle unit tests | Correct repeated setup, repair, collision and one-shot storage failures restore exact snapshots; outer assertions exit 0. | Capabilities logic checks snapshot and rollback, implemented in `src/Core/Capabilities.php`. `tests/Unit/CapabilitiesTest.php` provides mock. |
| F-003 / C3-V2 | `bash tests/workflow/product-smoke.sh --task=CORE-003 --wp-version=6.4.3` and repeat 6.7.2 | All actual actors/routes and ordinary-post controls pass; exit 0. Injected assertion produces FAIL and nonzero, never final success. | Updated `tests/fixtures/core-003-verify.php` increments `$velog_errors`. |
| F-004 / C3-V4 | `bash tests/workflow/product-smoke-controls.sh` | Four fault modes plus two concurrent normal runs; correct inner statuses/categories, bounded cleanup, outer exit 0; incorrect status or leak makes outer nonzero. | Implemented parallel execution using dynamic ports, negative modes tested. |
| F-005 / C3-V5 | `bash -n tests/workflow/product-smoke.sh`; `bash -n tests/workflow/product-smoke-controls.sh`; `composer run lint`; `composer run test`; `git diff --check` | All exit 0 with actual totals. Disposable duplicate-hook mutation is rejected by unchanged validator. | `phpcs` and `phpstan` fixed. `phpcbf` ran. PHPUnit pass (17 tests, 17 pass in workflow.test.mjs; PHPUnit 38/38 pass). |
| F-006 | Evidence/checklist/task/README review; progress API | Current status/actor/action/round/prompt agree; exact results and NOT VERIFIED fields present. | Updated. |

- Contributor identity: Antigravity Builder
- Unperformed checks: PHP 8.1 (Not available locally, NOT VERIFIED).
