# WF-004 Round 4 — contributor review

Decision: CHANGES_REQUESTED. The automatic two-application handoff loop is not operational. The user requested a final practical review and a direction change report instead of another repetitive fix round. No correction implementation is dispatched; Architect retains the next action for a user-approved direction decision. This is contributor review under the recorded user exception, not independent acceptance.

## Reproduced blockers

- WF4-F-002, P1: scripts/agent-adapters/codex.mjs:166–175 calls onSpawn only after exit, whereas stdin is delivered at 456–459. All eight Codex probe cases recorded inputExistedBeforePersistence=true. Awaiting persistence after work has completed does not protect crash recovery or establish durable ownership before execution.
- WF4-F-003, P1: agent FAIL now reaches the journal correctly, but its run becomes failed. The metadata correction publishes successfully, then scripts/handoff-controller.mjs:151–156 permits only rejected correction parents or completed parents. Actual correction launches=0 with the parent completion blocker. The agent-decision correction loop remains broken. Structural rejection correction remains a passing control.
- WF4-F-004, P1: scripts/agent-adapters/codex.mjs:438–453 returns success=true for outcome=handoff with a nonexistent new_receipt_id. The probe supplies a random nonexistent successor and receives success=true / cleanupVerified=true. This is a false completion claim, not proof of unauthorized successor dispatch.
- WF4-F-005 / AC4, operational blocker: Antigravity available() always returns false and dispatch() never launches. That is the required safe behavior for an unverified interface, but it means the approved two-agent loop cannot run. The copied actual dashboard entrypoint returns the explicit interface blocker with dispatchCount=0. Do not remove this guard to obtain a green demo.

Additional static F-002 concerns: deadline only sends TERM; KILL escalation is reached only after exit, so a TERM-resistant process has no enforced terminal deadline. Already-aborted signals are not checked, output buffers are unbounded, and asynchronous stdin/stream errors have no error listeners. These cases were inspected, not executed in this bounded review.

## Fresh verification

Node v24.21.0: npm run test:workflow completed with 60/60 passes, zero failed/skipped, duration approximately 46 seconds. See workflow.log. The successful shell wrapper printed the tail; the runner's own summary establishes the result. Existing green coverage still misses reproduced blockers.

probes.mjs and integration-probes.mjs each exited 0; these are observation harness exits, not acceptance-test passes. Raw results are probe-results.json and integration-results.json. Fixtures use fake executables and public controller/publisher calls, never live agents. Integration was rerun after adding correction-dispatch observation and correcting the HTML blocker search; the final saved results represent that run. Each harness removes its fixture directories in finally. The descendant was no longer alive after adapter completion; the historical harnessStoppedDescendant field means absence verified, not that the harness had to kill it. HTTP GET/HEAD/POST returned 200/200/405; server SIGTERM exit 0. This only verifies idle/blocked shutdown.

Improvements confirmed: exact prompt delivery; wrong/missing thread rejection; malformed JSONL, invalid enums and missing nullable field rejection; one inherited-group descendant cleaned; unsupported Antigravity does not spawn; agent FAIL persisted; unresolved rejection stays visible; dashboard reaches the correct role adapter and renders its blocker. The repeated rejection cause degrades to [object Object], so detailed diagnostics still need work.

NOT VERIFIED: real Codex/Antigravity integration, a functioning bidirectional loop, full R1–R7/concurrent/fault matrix, active shutdown, operating/recovery/setup documentation. Prior nine validation controls were not rerun here. No PHP changes or PHP gate execution. No implementation files changed by this review. Signal is null, local controller config absent, active project dispatch disabled. git diff --check passed before final review documentation; final check recorded separately.

## Direction recommendation

Stop further automatic cross-application adapter implementation for now. Retain the read-only progress dashboard and manual copy/paste handoffs between the existing Architect and Builder sessions, then return to CORE-003 after the user approves reprioritization. Preserve the partial implementation and findings. This proposal is not accepted scope and does not mark WF-004 DONE. If automation is revisited, require a small real session-resume/interface proof for both clients before investing in the controller again.

All WF4-F-001–WF4-F-006 stay open and AC1–AC5 stay unchecked. No new Builder revision or publication is authorized.
