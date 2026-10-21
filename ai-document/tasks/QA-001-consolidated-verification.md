# QA-001: Consolidated technical verification and fixes

## Current handoff

- Status: IN_PROGRESS
- Workstream: Development
- Owner: Developer
- Next actor: Developer
- Blueprint readiness: PASS — bounded technical plan below and inspected runner isolation.
- Authority: Product Owner replied "ố kề,làm" to CODE-001 next action (resume consolidated test/fix).
- Next actor and exact next action: Resume from round-1/report.md; diagnose reminder SQL temporary-table failure and vehicle join plan, rerun benchmark and affected regressions, then wire ZIP-only retention checks.
- Scheduling: PAUSED_BY_OWNER
- Stop authority: Product Owner asked to save progress for next session and push the current checkpoint to Git. This is not completion or acceptance.
- Checkpoint Git authorization: Commit and push current VeLog work explicitly authorized; no deployment or publication authorized.
- Evidence: ../evidence/QA-001/round-1/

## Plan and boundaries

1. Establish current PHP/unit/static/workflow results without altering historical evidence.
2. Add tests/Unit/ReminderWorkflowTest.php for policy/schema/security boundaries, tests/fixtures/core-queue-verify.php and tests/fixtures/core-queue-worker.php for authoritative history/reminder/index/lifecycle/rollback and tests/workflow/core-queue-endpoint.sh for authenticated HTTP. Extend existing product-smoke task allowlist only, preserving disposable DB/WP cleanup and path guards. All source needed to fix resulting defects is in scope; no added dependencies.
3. Verify date/distance OR, unknown/zero, original units, local calendar, lifecycle/snooze/completion/replay, read-only history/filter/order/pagination/audit/privacy and derived index coherency. Include direct repository denial and failure injection, plus bounded batching/query checks. Add tests/fixtures/core-queue-benchmark.php, invoked explicitly with VELOG_QA_BENCHMARK=1 after HTTP checks, to record exact G-08 record counts and 5 warmup/30 timed samples for five operational reads. Bulk synthetic setup is limited to the disposable database and is not a write-throughput benchmark.
4. Run relevant existing SERV/CUST/VEH regressions and supported isolated WP versions. Maintain disposable fixture asset pins by reviewed generated hash only; do not alter live-site pins.
5. Preserve actual failures and final retest outcomes. Record runtime/commands/data limits and cleanup. PHP syntax/build/static success never implies manual acceptance.

No active-site data changes or credential resets. No commit, workspace release, deploy or external publication. A ZIP generated entirely inside the task-owned disposable directory may be used for installation/lifecycle tests and must be removed by the runner. Add tests/fixtures/qa-retention-verify.php for exact record/settings hashes before uninstall and after ZIP reinstall; tests stay outside the installed runtime. Visual/assistive-tool verification, Product Owner acceptance and G-07 product confirmation remain separate. Full G-08 benchmark and package lifecycle verification must have their actual dataset/environment established before claiming results; do not substitute small fixtures for them.

### Chat handoff prompt

```text
Status: IN_PROGRESS
Recipient: Developer
Intent: resume

Resume QA-001 from ai-document/evidence/QA-001/round-1/report.md when the Product Owner resumes work. First diagnose the reminder benchmark failure and rerun G-08; then repeat affected regressions and complete ZIP retention verification. Preserve manual/PHP 8.1 debt and do not infer acceptance.
```
