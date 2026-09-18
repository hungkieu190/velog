# Workflow requirements

- R1: Separate Architect planning/review from Builder implementation; User approves scope. Only Architect accepts tasks and checks project items.
- R2: Every handoff includes a self-contained prompt in both task and chat, with exact files, status, scope, criteria/findings, checks, NOT VERIFIED checks, and next action.
- R3: Implement a read-only local npm run progress dashboard reading checklist/tasks, including ownership, phases, history, findings, mismatches, prompt coverage, timestamps, and exact next action.
- R4: Frontend authoring belongs under src/js/ and src/css/; build tools under scripts/; production outputs under assets/. Backend PHP remains under src/.
- R5: dev builds once with maps; production builds once with minification, notices, safe cleanup and validation; release awaits a fresh production build.
- R6: Explicit runtime allowlist, validated version, safe staging, top-level velog/ ZIP directory, no development files/secrets; preserve required Composer autoloading.
- R7: Track lockfiles and production assets; ignore maps/dependencies/release output without losing existing unrelated ignore rules.
- R8: Verify installation, builds, ownership, packaging, failure behavior, repeatability, ignores, dashboard, and isolated WordPress installation. Missing evidence stays NOT VERIFIED.

Full acceptance requirements are preserved in source-build-release-workflow.md sections 4–12 and architect-builder-workflow.md. No abbreviated requirement overrides those specifications.

## Approved international MVP requirements — PLAN-001 revision 3

- PR1: One shop per installation; private staff workflows for customer/vehicle records, service history and maintenance queue.
- PR2: Configurable km/mi, currency/precision and locale/timezone presentation; preserve historical units and currencies. Follow internationalization.md for scope and verification.
- PR3: Explicit manager/technician permissions and object-level checks; customers have no login by default.
- PR4: Archived linked records retain service history; finalized corrections are attributed and reasoned.
- PR5: Date/recorded-distance maintenance thresholds, snooze and completion; no automatic email/recurrence in MVP.
- PR6: Photos, sharing, QR/PDF and notifications are deferred. Additional unit dimensions are supported when corresponding future fields are introduced.

These are approved product requirements, not passing runtime checks. Detailed implementation gates are recorded in product-plan.md and each task.
