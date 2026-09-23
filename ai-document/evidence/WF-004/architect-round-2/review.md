# Independent Architect review — WF-004 Round 2

## Decision and independence

CHANGES_REQUESTED. WF4-I-004 is resolved: received task, checklist and README agree on READY_FOR_REVIEW/Architect. Intake PASS; this review addresses implementation, not another metadata return. Codex Architect is the original planning/review session and has no controller implementation contributions. Antigravity remains the declared Builder. No acceptance checkbox is checked. All six existing finding IDs remain OPEN with partial repairs noted below.

Received task, README and source hashes are preserved. No implementation files, active signal or local dispatch configuration were changed by this review. The active signal remains empty and no enabled local configuration was found. Tests/probes use owned disposable workspaces; no WordPress/database access or live agent launch on this project.

## Actual verification

- Node 24.21.0. Focused suite: exit 0, 36/36 PASS (focused.log).
- Full `npm run test:workflow`: exit 1, 54/56 PASS, 2 FAIL (workflow.log). Existing dashboard tests fail at workflow.test.mjs:87 and :105: historical validPromptCount is now 1/0 instead of 2/1.
- Global `git diff --check`: exit 2 (diff-check.log). Scoped architecture.md:47 whitespace remains; product-smoke.sh whitespace belongs to unrelated CORE-003.
- `node ai-document/evidence/WF-004/architect-round-2/probes.mjs`: exit 0 means the observation harness finished, not that its negative controls passed. See expected versus observed in probe-results.json. Positive publication/dispatch launches once. Twelve owned directories removed.
- HTTP probe: GET 200, HEAD 200, POST 405. Monitor blocker is now visible in HTML (fixed), but configured production wiring still cannot find the adapter. No ledger created. Server exited 0 and its fixture was removed. The first review harness attempt had a Python string-replacement error before server startup; corrected harness produced the saved http-results.json.
- Held controller lock now prevents dispatch; unknown parent now blocks; malformed JSON ledger now raises an explicit error. These are verified improvements.
- Real adapter V7, Antigravity V8, process-group timeout/descendant cleanup, browser visuals and live bidirectional work remain NOT VERIFIED. No need to launch a real model to establish the deterministic failures below. Historical CLI preflight is not acceptance evidence for these adapters.

## Findings

### WF4-F-001 — P1, OPEN: validation/publication still accepts conflicting state

At handoff-protocol.mjs:308 onward, round comparison has an empty body and plan revision is not compared. `progress.issues` is ignored; task issues become limitations. The README is hashed but not checked for current-state agreement. Probe with task revision/round 99, receipt revision/round 1 and DONE checklist/README publishes successfully and dispatches once. Hash equality proves bytes, not valid authorization.

validate-handoff.mjs validates the signal instead of the requested task: a missing task plus requested receipt with empty signal returns valid=true/exit 0; a valid `--task=ai-document/tasks/TEST-001-fixture.md` returns exit 1 because it compares the path to TEST-001. This prevents the specified pre-publication validation workflow.

publish-handoff.mjs:110–169 reads the current ID but never reads an acknowledgement ledger. Two sequential publications replace the unacknowledged first receipt. Locking only prevents a simultaneous race, not lost queued work. Required envelope strictness, owned exclusive temp/cleanup and structured error contract also remain incomplete by inspection. Actual task-status/prompt-hash validation, ancestor-safe paths and under-lock revalidation are useful partial repairs.

### WF4-F-002 — P1, OPEN: lifecycle remains incomplete

Held-lock and unknown-parent controls now pass; completion-scheduled monitor ticks improve serialization. However startMonitor awaits its initial tick at handoff-controller.mjs:423 before returning its stop handle. A delayed fake dispatch proves no handle is returned during the active initial run. Dashboard assigns `monitor` only after that await, so shutdown cannot abort that initial run. shutdown also discards stop errors without awaiting completion.

Adapters settle on `exit`, not stream `close`; log write errors inside an async event callback have no unified rejection path. TERM schedules an uncancelled delayed KILL; there is no confirmed descendant absence or bounded stream consumption. onSpawn persists asynchronously into a shared ledger temp and ignores errors. Parent 'completed' is therefore not proof of required cleanup. These lifecycle paths are static findings; full process-group fault verification remains NOT VERIFIED.

### WF4-F-003 — P1, OPEN: deduplication drops recoverable work; restart state is not durable

handoff-controller.mjs:219 returns on lastReceiptId before comparing the ledger content hash. Changing the same ID's round produces no tamper diagnostic (probe). Missing adapter sets lastReceiptId at :289; providing the adapter on the next cycle still produces zero launches (probe).

Malformed JSON now throws, but ledger/config object schemas and uncertain-run recovery are absent. Budgets still initialize to zero in ControllerState and only increment in memory; restart resets them. correct_metadata uses previous_id presence instead of verifying rejected_receipt_id and original sender authority; status-based envelope routing still conflicts with correction routing. These remaining state/routing claims are code-inspection findings.

### WF4-F-004 — P1, OPEN: Codex false success remains reproducible

Explicit session requirement and removal of resume -C are fixed by inspection. But codex.mjs:209 looks for session.started/session_id; retained CLI preflight uses thread.started/thread_id. Missing session identity is accepted. Malformed JSONL is skipped, and local result validation checks only task/receipt IDs.

The real adapter run against a disposable fake executable accepts WRONG-SESSION, a non-JSON line and intake/outcome='INVALID' as success=true (probe). It does not validate actual successor receipt/parent for outcome=handoff or execute the exact validated prompt. A successful terminal event alone is insufficient. Full result/schema, event identity and cleanup must pass before live V7 is meaningful.

### WF4-F-005 — P1, OPEN: dashboard dispatch wiring is still broken; Antigravity contract is invented

progress-dashboard.mjs:47 injects `{codex, antigravity}`, while controller :285 requests adapters[targetRole], where targetRole is architect/builder. Both roles are unavailable. Direct production-map probe and real HTTP server reproduce the blocker. HTML now renders that blocker correctly and HTTP read-only behavior passes.

antigravity.mjs:136 explicitly copies Codex arguments despite no verified Antigravity CLI interface. Discovering any executable permits spawning it with this unsupported contract. Required behavior is unavailable/no spawn until that interface is verified. Discovery still omits PATH search. Configured candidate handling and continued search after a default broken symlink are partial improvements, not live integration evidence.

### WF4-F-006 — P2, OPEN: coverage/reporting and operating docs remain incomplete

The submitted 36 tests are still the V1–V10 helper/stub suite, not all R1–R7 cases. V4 only reads back a claimed row and asserts 'claimed'; it never tests recovery. V3 lock coverage remains a lock-helper test. V7 only inspects schema; V10 tests protocol helpers rather than HTTP. The full suite now has two regressions caused by collapsing validPromptCount to latest-prompt validity (progress-data.mjs:57).

Preserve historical prompt counting separately from newest eligible prompt selection. Do not weaken regression expectations to hide this. Required config/activation/recovery documentation in architecture/build-and-release is absent, and scoped architecture whitespace remains. Correct the Builder completion claim; missing live checks must not be reassigned to Architect as though that transfer had been approved.

## Next action

Builder implements the appended Round 3 revision 3 correction blueprint in the task, including remaining revision 2 obligations. Keep active-project dispatch disabled. Submit one complete finding-to-test report with raw exits and explicit limitations. Architect independently reviews; no finding is closed solely by a passing test total.
