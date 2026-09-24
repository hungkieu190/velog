# WF-004 Round 3 — user-authorized contributor code review

## Authority and decision

Decision: CHANGES_REQUESTED. The user explicitly authorized this Codex contributor to review its own WF-004 code, superseding the previous independence prerequisite for this bounded review. This is NOT an independent review. The reviewer wrote the current controller draft and reports defects in that draft as well as Builder changes. Metadata WF4-I-005 is resolved; it is not reopened. No implementation was edited during review. No acceptance criterion is checked and no live project dispatch was enabled.

## Executed evidence

Node v24.21.0; `npm run test:workflow` exits 0: 60 tests, 60 pass, no failures/skips. See workflow.log and commands.json. This is fresh retained evidence, unlike the earlier unretained Builder claim. The suite combines helper/mock/build tests; it is not 60 live agent integration checks.

Disposable probe harnesses all exited 0 (meaning the observation harness finished, not that the implementation passed). See probes.mjs/probes.log/probe-results.json and adapter-logs/, integration-probes.mjs/integration-results.json/http-server.log, and validation-probes.mjs/validation-results.json. Fourteen fixture directories were removed. The deliberately orphaned descendant was confirmed alive when the adapter returned, then explicitly killed by the harness and confirmed non-running. The HTTP server exited 0 on SIGTERM. No real agent, production database or active-project controller was launched. Reviewed source hashes were unchanged; active signal remains null and local config absent (source-invariance.json).

Global `git diff --check` exits 2 for seven trailing-whitespace lines in the changed handoff-controller.test.mjs (238, 501, 503, 515, 521, 535, 578). Documentation-only checks previously passed; they do not cover this source whitespace. See diff-check.log.

## Findings and observed results

### WF4-F-001 — validation/publication: partial PASS, broader acceptance still open

Nine focused validation observations pass: existing unpublished task exits 0; missing task and missing receipt exit 1; bad argument exits 2; published matching receipt exits 0; revision mismatch, checklist contradiction, malformed newest prompt and unacknowledged replacement are rejected with intended errors and unchanged signal hashes. The earlier shared validation defects have meaningful improvements. Full concurrent publication, fault injection, required document closure and complete R1/R2 coverage are not established by these nine observations; do not mark all of F-001/AC1 accepted solely from them. The first draft of the revision-negative probe hit the acknowledgement gate; it was corrected to use an empty signal, rerun, and now records the intended plan_revision mismatch. Only the corrected run is represented by validation-results.json.

### WF4-F-002 — P1: false cleanup proof and unawaited spawn persistence

codex.mjs:104–106 invokes onSpawn without awaiting it; :133 settles through exit, and :257 reports cleanupVerified:true without checking descendants. The valid-control probe returns before an intentionally delayed onSpawn finishes and does not deliver the supplied exact prompt. The live-descendant-after-success case returns success=true and cleanupVerified=true while its inherited-group descendant is alive (PID retained in probe-results.json). The harness, not the adapter, stops that process. A successor could therefore overlap surviving work despite the controller's parent cleanup gate. Uncancelled delayed SIGKILL, unbounded stream buffering and async log-write failures remain static concerns, not fully exercised here. Dashboard shutdown still discards stop failures at progress-dashboard.mjs:54–56.

### WF4-F-003 — P1: agent intake failure cannot route a correction; P2: rejection blocker disappears

These defects are in this reviewer's controller draft. At handoff-controller.mjs:208–211, a successfully transported final result with intake=FAIL leaves entry.intake=PASS from structural validation and sets status=completed. The agent-intake-failure-not-journaled probe records journal PASS versus agent FAIL; publishHandoff then rejects the legitimate correction with 'Correction must return rejected receipt to its original sender'. Structural snapshot validity and the agent's intake decision must not be conflated. Preserve both facts and make correction authorization use the final validated decision.

At :108–110, an existing rejected receipt takes the dedup branch after blocked was reset. A second public poll changes blocked from the rejection explanation to null and clears pendingReceipt although no correction occurred. Historical errors remain, but the current blocker no longer describes the unresolved work. Persist and restore unresolved rejection state across unchanged polls/restarts. The structural-rejection correction probe separately shows B->A correction publication and one correction dispatch can work; it does not verify the complete agent-decision round trip.

### WF4-F-004 — P1: invalid Codex transport accepted as success

The changed thread.started/thread_id parsing correctly rejects an explicitly wrong UUID (negative control). However :225 only rejects a mismatch when an identity exists, and :220–221 silently skips malformed JSONL. Separate fake executable cases return success=true for missing identity, a malformed line, invalid intake/outcome enums, absent new_receipt_id and outcome=handoff naming a nonexistent successor. Local result checking covers only task/receipt. Schema :34 omits the nullable field from required. The supplied exact validated prompt is absent from captured stdin. These are reproduced errors, not missing real-CLI evidence.

### WF4-F-005 — P1: production dashboard cannot dispatch; unverified Antigravity transport still spawns

The actual copied dashboard entrypoint uses codex/antigravity keys at :47 while the controller expects architect/builder. With a valid fixture signal and enabled adapter configuration, HTTP returns 200/200/405, blocker appears in HTML/API, dispatchCount=0 and no ledger exists. Server shutdown without an active dispatch exits 0; active-run shutdown is not verified.

Antigravity dispatch treats executable discovery as proof of CLI compatibility. The unverified-antigravity-executable case actually spawns the fake executable using copied Codex arguments and returns success=true. The approved revision 3 contract requires unavailable/no spawn until a verified Antigravity interface exists. This must be fixed even if no actual Antigravity binary is installed.

### WF4-F-006 — P2: tests still miss critical failures and include a vacuous replay test

handoff-controller.test.mjs now creates a fresh fixture before each V3 test (:228). The 'completed receipt is never replayed' test (:277 onward) reads its empty ledger and asserts only inside a loop over completed rows, so it executes zero assertions. Seed a completed run via the public controller and assert a nonempty ledger before testing a new monitor. The V4 crash case still only reads a claimed row back; it does not exercise controller recovery. Aggregate green tests coexist with every reproduced defect above. Required operating/config/recovery/setup documentation and complete process/HTTP/live checks remain unverified.

## Acceptance assessment and limits

AC1: partial validation controls PASS; full matrix NOT VERIFIED. AC2: FAIL (cleanup and intake/recovery). AC3: FAIL on reproduced fake transport contract cases; real Codex adapter integration NOT VERIFIED. AC4: FAIL for unsupported transport spawning; real Antigravity/two-agent loop NOT VERIFIED. AC5: FAIL for dashboard wiring and incomplete tests/docs. All WF4-F-001–WF4-F-006 remain open with the partial improvements recorded above; no previous finding ID is repurposed.

Real Codex/Antigravity integration was deliberately not attempted before the deterministic transport/lifecycle failures are repaired. Full SIGINT/timeout/grandchild/sentinel, log-write failure, browser visuals and complete R1–R7 verification remain NOT VERIFIED. Test-count success is insufficient for acceptance. Builder must follow the existing revision 3 blueprint and the appended revision 4 supplement; review publication is manual, with dispatch disabled.
