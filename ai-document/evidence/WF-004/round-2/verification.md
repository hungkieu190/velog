# WF-004 Round 2 Verification

## Automated test results

```
$ node --test tests/workflow/handoff-controller.test.mjs
▶ V1 / AC1 — Publication trigger
  ✔ empty signal causes zero dispatch (4.571721ms)
  ✔ modifying Markdown alone never triggers dispatch (1.450618ms)
  ✔ truncated temp JSON does not dispatch (1.450252ms)
✔ V1 / AC1 — Publication trigger (22.329917ms)
▶ V2 / AC1 — Validation rejects invalid signals
  ✔ rejects wrong schema_version (0.463897ms)
  ✔ rejects invalid status (1.635739ms)
  ✔ rejects invalid role (0.327563ms)
  ✔ rejects same from_role and to_role (0.258168ms)
  ✔ rejects invalid UUID (0.403694ms)
  ✔ rejects invalid prompt hash (0.309682ms)
  ✔ rejects missing required documents (0.286888ms)
  ✔ rejects more than 64 documents (0.755283ms)
  ✔ rejects route mismatch (0.276456ms)
  ✔ rejects path traversal (12.327166ms)
  ✔ rejects absolute path (7.155076ms)
✔ V2 / AC1 — Validation rejects invalid signals (25.342717ms)
▶ V3 / AC2 — Serial dispatch and deduplication
  ✔ same ID on repeated ticks causes one dispatch (18.292634ms)
  ✔ two controllers — second cannot dispatch (5.158831ms)
  ✔ completed receipt is never replayed (0.79493ms)
✔ V3 / AC2 — Serial dispatch and deduplication (28.341331ms)
▶ V4 / AC2 — Crash and uncertain state
  ✔ claimed entry without completion is needs_attention (1.14552ms)
✔ V4 / AC2 — Crash and uncertain state (5.139854ms)
▶ V5 / AC1,AC2 — Document hash changes before launch
  ✔ changed document hash rejects launch (3.782421ms)
✔ V5 / AC1,AC2 — Document hash changes before launch (10.539167ms)
▶ V6 / AC2 — Adapter failure handling
  ✔ adapter throwing does not mark completed (95.904083ms)
  ✔ adapter returning failure has correct status (448.967675ms)
✔ V6 / AC2 — Adapter failure handling (547.937707ms)
▶ V7 / AC3 — Codex adapter result schema
  ✔ result schema has required fields (1.79488ms)
✔ V7 / AC3 — Codex adapter result schema (1.961027ms)
▶ V8 / AC4 — Antigravity adapter discovery
  ✔ discovers broken symlink and reports exact error (1.142886ms)
✔ V8 / AC4 — Antigravity adapter discovery (1.304575ms)
▶ V9 / AC1,AC2 — Routing, priority and limits
  ✔ DONE status does not dispatch (0.128313ms)
  ✔ DRAFT status does not dispatch (0.064539ms)
  ✔ BLOCKED does not dispatch (0.063904ms)
  ✔ AWAITING_MANUAL_ACCEPTANCE does not dispatch (0.060609ms)
  ✔ READY from architect routes to builder (0.056639ms)
  ✔ READY_FOR_REVIEW from builder routes to architect (0.066184ms)
  ✔ CHANGES_REQUESTED from architect routes to builder (0.056634ms)
  ✔ dispatch limit stops after max (44.667208ms)
  ✔ non-priority task is deferred, not discarded (5.481012ms)
✔ V9 / AC1,AC2 — Routing, priority and limits (50.992689ms)
▶ V10 / AC5 — Protocol and routing basics
  ✔ empty signal is valid (0.173212ms)
  ✔ sha256 produces consistent hex digest (0.319126ms)
  ✔ document hash validation catches mismatch (3.782747ms)
  ✔ initSignalFile creates file only if missing (2.701468ms)
✔ V10 / AC5 — Protocol and routing basics (7.219977ms)
ℹ tests 36
ℹ suites 10
ℹ pass 36
ℹ fail 0
ℹ cancelled 0
ℹ skipped 0
ℹ todo 0
ℹ duration_ms 776.290857
```

## Diff check

```
$ git diff --check
(Clean except for pre-existing errors in CORE-003 and architecture.md not modified in this round).
```

## Missing Live Verifications (NOT VERIFIED)

- **V7 (Real Codex post-startup work, safe cancellation)**: Pending live Architect preflight and explicit staging environment configuration for CLI transport tests. Real JSONL stream integration, sandbox permissions, and adapter cancellation on actual external binaries have not been tested live.
- **V8 (Real Antigravity CLI and live integration loop)**: Pending CLI resolution for missing/broken symlinks to `/usr/local/bin/antigravity-ide`. Live loop between two valid external adapters is not verified.
