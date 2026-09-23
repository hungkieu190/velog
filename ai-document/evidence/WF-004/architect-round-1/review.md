# Independent Architect review — WF-004 Round 1

## Identity and scope

Codex Architect, original planning/review session, no controller or adapter implementation contributions. Antigravity Builder is the declared implementation contributor. Intake correction 2 was accepted for substantive review; historical intake notes are not code review findings. Reviewed submitted revision 1 against its original requirements. The separately approved deterministic-validation addendum is prospective implementation scope, not a retroactive reason for failure. All findings below violate revision 1 requirements.

Source hashes and received task are retained; a final hash comparison found no scripts/tests changed during review. Existing unrelated dirty files were preserved. No live agent was launched on the VeLog checkout and no WordPress/database work occurred.

## Actual verification

- Node v24.21.0; npm 11.19.0.
- Focused handoff suite: exit 0, 36/36 tests, 10 suites (focused.log).
- Full npm run test:workflow: exit 0, 56/56 tests, 10 suites (workflow.log).
- git diff --check: exit 2. architecture.md line 47 and unrelated CORE-003 product-smoke.sh whitespace; see diff-check.log. Do not conceal overall failure or clean unrelated files under this task.
- Independent disposable probes: probes.mjs / probes.log / probe-results.json. These are inspection experiments, not fixes to product tests. They expose failures despite the passing shipped suite. Ten owned fixture directories were removed.
- Actual Codex adapter invoked installed CLI in a disposable directory with a random explicit session ID: argument parsing failed before model execution, exit 2, unexpected -C. Live review/resume through this adapter is FAILED at startup; downstream V7 behavior remains NOT VERIFIED.
- Fake CLI with wrong session/task, intake FAIL, malformed output and turn.failed was accepted as success by the real Codex adapter. No real model used in this negative control.
- Owned disposable HTTP server: GET/HEAD 200, POST 405; /api/handoff reported dispatch mode but adapter unavailable despite configured executable/session. HTML omitted that error. Server exited 0, child reaped and fixture removed (http-results.json, http-monitor.json, http-server.log).
- Actual Antigravity discover() reports the documented broken /usr/local/bin/antigravity-ide symlink. This validates that observation only; its search is incomplete and does not prove all possible configured/PATH CLI locations unavailable. V8 live loop remains NOT VERIFIED.
- No browser visual test, real model-driven adapter turn, timeout/process-group fault test or successful live two-agent loop performed.

## Findings

### WF4-F-001 — P1 — Publication does not validate the actual assignment or protect pending handoffs (AC1)

handoff-protocol.mjs validateEnvelope validates fields but not document meaning; readRoleMapping is unused in dispatch. publish-handoff.mjs lines 93–152 hashes caller-provided prompt/documents without extracting the actual latest prompt or checking task/checklist/index/blueprint agreement. Probe published and dispatched READY revision 1 while task/checklist said DONE, task revision was 99 and the prompt differed. Sequential publication overwrites a receipt that has never been acknowledged; previous_id comparison only protects competing reads. Hashes are checked before the publication lock, not again at the final commit boundary. validateDocPath checks only the final lstat; a symlinked ancestor accepted a file outside the root. Revision 1 explicitly requires document/role consistency, no symlink escape and one unacknowledged signal.

### WF4-F-002 — P1 — Monitor has no enforced single-run ownership or parent-completion gate (AC2)

startMonitor at handoff-controller.mjs:349 never acquires the available controller lock. Probe launched work while another controller held it. setInterval callbacks can overlap; pollCycle has no currentRun guard. While first adapter remained pending, a second receipt launched a second adapter (max concurrent=2). parent_run_id is never resolved/checked; a nonexistent/failed-parent reference still launched. stop() only clears the timer; it cannot cancel/await an active detached agent, and adapters do not expose cancellation. PID is written only after dispatch resolves, too late for crash recovery. These are functional ownership defects, not merely missing tests.

### WF4-F-003 — P1 — Ledger corruption, deduplication and routing can lose or repeat work (AC1/AC2)

readLedger catches all errors and returns []; corrupting the ledger replayed a previously completed receipt. Same-id fast return at lines 218–220 bypasses content-hash validation, so same-id changed bytes produced no tamper error. Claimed/running records are skipped rather than surfaced as uncertain; failed work does not prevent newly published children from advancing. Adapter-unavailable paths set lastReceiptId and can suppress a later retry after configuration is repaired. Limits live only in ControllerState and reset on restart. correct_metadata uses status-only routeSignal; an Architect correction to Builder under READY_FOR_REVIEW was rejected rather than delivered. No independent handling of immediate correction actor exists.

### WF4-F-004 — P1 — Codex adapter cannot resume and accepts failed/wrong runs as successful (AC3/AC2)

codex.mjs:63–75 places -C after exec resume; installed CLI rejects it (actual exit 2). cwd is already supplied to spawn, and the retained preflight resume invocation did not use -C there. Missing sessionId silently starts an ephemeral new session, contrary to pinned-session requirements. Lines 162–194 validate only receipt_id; malformed JSONL is ignored, actual thread identity/terminal success and task_id/intake/outcome are not enforced. Probe returned success true for wrong session/task and turn.failed. Reported sessionId is copied from configuration, not verified from CLI events. Async exit handling can complete before stream closure; timeout escalation is not tied to a cancellable/reaped run and output buffering is unbounded. Real V7 must be performed after these defects are fixed; the prior standalone CLI preflight cannot certify this adapter.

### WF4-F-005 — P1 — npm run progress is not wired to working adapters; Antigravity remains an unsafe placeholder (AC4/AC5)

progress-dashboard.mjs:45 calls startMonitor(root) without adapter modules; startMonitor defaults to {}. The actual HTTP fixture showed configured enabled dispatch permanently blocked with no ledger/run. progress-view.mjs never renders handoffMonitor, so users cannot see the error on the page. Antigravity discovery returns at the first broken candidate, does not inspect the configured executable/PATH and treats IDE discovery as CLI evidence. Its dispatch ignores config.executable, accepts arbitrary config.args without a verified CLI contract, does not consume stdout or parse a structured/session result, and equates exit 0 with success. It must remain unavailable until an interface is verified, not become executable merely because a candidate path appears.

### WF4-F-006 — P2 — Test names and summaries overstate required coverage; operating docs are incomplete (AC1–AC5)

V4 test only rereads a claimed ledger row; it never tests recovery or needs_attention. V3 lock test calls the lock helper, not the monitor that ignores it. V10 tests hashing/init helpers, not HTTP or monitor integration. Codex test only checks schema field names, not dispatch. No meaningful tests for overlapping polls, parent completion, corrupt ledger, failed terminal events, bounded cancellation or correct_metadata round-trip. 36/36 and 56/56 are real passing counts, not V1–V10 acceptance. Builder raw logs are absent as disclosed. architecture/build docs do not document the actual controller/configuration/recovery; Architect policy edits are not Builder S8 runtime evidence. Keep historical claims with an explicit correction rather than overwriting them.

## Acceptance decision

CHANGES_REQUESTED. No AC checkbox accepted. AC1/AC2 FAIL; AC3 FAIL at actual CLI startup with downstream live behavior unverified; AC4 NOT VERIFIED with unsafe placeholder defects; AC5 FAIL despite HTTP read-only positive checks. Keep live dispatch disabled. Blueprint revision 2 in the task maps every finding to concrete fixes and negative controls. No new product dependencies, publication or application PHP scope is authorized.
