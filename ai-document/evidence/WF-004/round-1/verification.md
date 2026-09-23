# WF-004 Round 1: Builder Implementation Evidence

## Contributor
Antigravity Builder

## Environment
- Node v24.21.0 (via `nvm use 24`)
- npm v11.19.0

## Evidence Logs

- Full test suite output log: `ai-document/evidence/WF-004/round-1/commands.log` (MISSING RAW LOG - only the abbreviated transcript output is available below)
- V7/V8 real integration logs: MISSING. Real CLI probes were not executed due to missing executable and pending Architect staging.
- No other raw log files were generated in this round.

## Test Results (V1–V10)

```
> velog@0.1.0 test:workflow
> node --test tests/workflow/*.test.mjs

▶ V1 / AC1 — Publication trigger
  ✔ empty signal causes zero dispatch (3.910653ms)
  ✔ modifying Markdown alone never triggers dispatch (1.539883ms)
  ✔ truncated temp JSON does not dispatch (4.51431ms)
✔ V1 / AC1 — Publication trigger (21.385952ms)
...
▶ V10 / AC5 — Protocol and routing basics
  ✔ empty signal is valid (0.192833ms)
  ✔ sha256 produces consistent hex digest (0.265527ms)
  ✔ document hash validation catches mismatch (3.304558ms)
  ✔ initSignalFile creates file only if missing (3.027748ms)
✔ V10 / AC5 — Protocol and routing basics (7.317108ms)
...
ℹ tests 56
ℹ suites 10
ℹ pass 56
ℹ fail 0
ℹ cancelled 0
ℹ skipped 0
ℹ todo 0
ℹ duration_ms 38576.790508
```

## Antigravity Adapter Discovery (AC4)

```
$ /usr/local/bin/antigravity-ide --help
bash: line 1: /usr/local/bin/antigravity-ide: No such file or directory
$ file /usr/local/bin/antigravity-ide
/usr/local/bin/antigravity-ide: broken symbolic link to /opt/antigravity-ide/Antigravity-IDE/antigravity-ide
```

Result: Antigravity CLI is unavailable due to a broken symlink. This blocker is successfully reported by the adapter discovery module.

## Matrix Status
- V1-V6, V9, V10: PASSED (verified via automated stub tests).
- V7 (Codex adapter sandbox probe): NOT VERIFIED (stub tested, real CLI integration pending Architect preflight/staging).
- V8 (Antigravity loop): NOT VERIFIED (blocked by missing executable `/usr/local/bin/antigravity-ide`).
