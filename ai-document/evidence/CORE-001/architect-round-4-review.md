# CORE-001 independent Architect review — Round 4

Decision: CHANGES_REQUESTED. Reviewer: current coordinating Architect session, distinct from recorded Builder session 866ba911-4817-495a-90db-c2e198e686cc; no implementation performed by reviewer.

## Actual checks

- composer run lint: exit 0, PHPCS pass, PHPStan 6/6 files no errors.
- composer run test: exit 0, 21 tests / 35 assertions; PHP 8.3.6, PHPUnit 10.5.64.
- bash -n tests/workflow/core001-smoke.sh: exit 0.
- git diff --check: exit 0.
- Warning-detector reproduction: extracted the actual final PHP Notice containing _load_textdomain_just_in_time from isolated-smoke.log. Feeding it to grep -qi 'doing_it_wrong.*load_plugin_textdomain' returns 1 (missed); feeding the SAME line to grep -qiE 'doing_it_wrong|_load_textdomain_just_in_time' returns 0 (detected). This is an independent read-only reproduction of F-003's remaining test defect.
- Smoke script and appended logs independently inspected; no database started or active-site connection made by reviewer. Smoke was not independently rerun because identified verification/isolation defects must be corrected first.

## Accepted progress

The final logged run uses PHP 8.3.6 and WP 6.4.3/6.7.2. The isolated plugin copy receives a real generated Vietnamese catalog; both versions output Xin Chào for Hello World. The WP 6.7.2 negative control is installed before bootstrap and genuinely produces the early-translation notice. Deactivation/reactivation are recorded on both versions; the second version reuses the first version's database, so its initial activation is correctly logged as already active. Final database shutdown completes before removal, and final cleanup reports exit 0. Earlier failed runs and Round 3 corrections remain preserved. These are substantive accepted evidence, not grounds to repeat already-fixed requirements.

## Stable findings

- F-001 CLOSED, unchanged.
- F-002 CLOSED, unchanged.
- F-003 PARTIAL / OPEN (P2): actual translation and early control now work, but the normal absence detector is invalid. Its pattern expects doing_it_wrong followed by load_plugin_textdomain, while the actual notice contains _load_textdomain_just_in_time and neither expected combination. Therefore a normal run producing exactly the known warning still prints SUCCESS. Negative control uses a different, broader pattern and does not validate the normal gate. Use the SAME VeLog-specific detector for both; assert it identifies the negative event and rejects normal output containing that event. Current final log visibly has no early notice in normal output, but the reusable acceptance gate is false-negative and must be fixed. Do not widen scope into other i18n changes.
- F-004 PARTIAL / OPEN (P2): tracked PID and graceful wait fix the prior shutdown race, as logs confirm. Directory ownership remains unsafe: /tmp/velog-core001-smoke-$RANDOM has limited names and mkdir -p accepts an existing directory; cleanup later recursively deletes it. This does not meet the already-requested unique owned directory requirement. Use mktemp -d under /tmp or atomic mkdir with collision retry; never adopt pre-existing content. The readiness loop also runs forever if the DB child fails; bound it with timeout and child-liveness checks, preserving cleanup/nonzero failure. Verify an induced startup failure and ensure only the run's owned directory is removed. No claim that a collision occurred in the submitted successful run.

## AC verdicts

| AC | Verdict | Evidence |
|---|---|---|
| AC1 | PASS | Prior real-entry regression and current action/filter/idempotency tests pass. |
| AC2 | FAIL — remaining verification defect | Actual catalog/init/path and negative control accepted; normal-warning detector fails its own real-warning fixture (F-003). |
| AC3 | PASS | PHP quality/tests pass; no new product code or dependency change in this fix scope. |
| AC4 | FAIL — remaining harness defects | Actual matrix/lifecycle/shutdown evidence accepted; unique ownership/bounded startup not satisfied (F-004), diagnostic gate incomplete. |

Task remains CHANGES_REQUESTED; no acceptance checkbox checked. PLAN-F-004 runtime wiring evidence now exists, but overall CORE-001 acceptance waits for remaining harness fixes. Header/report mismatch (header CHANGES_REQUESTED vs report READY_FOR_REVIEW) is noted and reconciled to the review decision; next Builder handoff must synchronize it. No application implementation, commit, deployment or active-site DB changes. Continue only the bounded smoke fixes before issuing the next product task.
