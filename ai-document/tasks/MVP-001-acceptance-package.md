# MVP-001: MVP integration, performance and local package acceptance

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference: Codex planning conversation of 2026-09-21.
- Builder session reference: Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session.
- Related checklist items: MVP-001 / AC1–AC4 in [implementation-checklist.md](../implementation-checklist.md).
- Baseline branch and commit: `main` at `2aa3b8d`.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Developer
- Next actor and exact next action: Keep packaging, integration, performance and acceptance as deferred debt until Product Owner resumes testing.
- Debt: TEST_DEBT
- Scheduling: DEFERRED
- Current authority: 2026-10-21 code-first owner decision supersedes historical prerequisite/test gates below.

## Problem and intended behavior

Passing individual task tests does not establish the complete customer-to-service-to-reminder journey or a runtime-complete installable package. The approved MVP needs end-to-end, privacy, performance, and user evidence.

## Scope and references

- Allowed files:
  - New `tests/fixtures/mvp-001-verify.php`: deterministic synthetic journey, permissions, retention, dataset seed/query timings in isolated WP.
  - Extend `tests/workflow/product-smoke.sh` MVP mode.
  - New `ai-document/walkthroughs/MVP-001.md`; evidence under `ai-document/evidence/MVP-001/`.
  - Documentation and release checks. No new dependencies, automatic version bump, or production data changes.
- Reference documentation: [PLAN-002](PLAN-002-mvp-task-batch.md), [product-plan.md](../product-plan.md), [testing-strategy.md](../testing-strategy.md), [build-and-release.md](../build-and-release.md).

## Implementation blueprint

- Revision: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE (draft pending all MVP prerequisites DONE and gate G-08).
- Installable package verification from actual release ZIP; isolated WP instance; minimum PHP 8.1, WP 6.4.3 and 6.7.2.
- Full staff journey: manager configures settings -> creates customer & vehicle -> technician creates/finalizes service -> manager reasoned correction -> owner reassignment -> reminders -> archive/restore -> retention across lifecycle.
- Performance verification: G-08 dataset (1000/2000/20000/5000 records), p95 <= 2s warm on agreed reference machine.
- User manual acceptance required before final DONE decision.

## Acceptance criteria

- [ ] MVP-001 / AC1: Actual local ZIP contains complete runtime and installs/runs without development environment fallback. Status: DRAFT.
- [ ] MVP-001 / AC2: Complete international staff journey and negative authorization/retention cases pass across required runtime matrix. Status: DRAFT.
- [ ] MVP-001 / AC3: Agreed performance dataset/targets and query correctness are evidenced without hidden shortcuts. Status: DRAFT.
- [ ] MVP-001 / AC4: User manual acceptance and production limitations are explicitly recorded before final DONE decision. Status: DRAFT.

## Verification matrix

| Case | AC | Input / Scenario | Expected result |
|---|---|---|---|
| V1 | AC1 | Clean build environment and existing version declarations | Packaging and runtime autoload verified; exits 0 |
| V2 | AC2 | Fresh ZIP-only site; manager/technician/subscriber; locale/unit/currency | End-to-end journey passes; denied mutations do not alter data; snapshots retained |
| V3 | AC3 | G-08 seeded dataset; 5 warmup + 30 samples | Correct pages/totals and warm p95 <= 2s |
| V4 | AC4 | User performs independent setup and walkthrough journey | Dated explicit user manual acceptance |

## History and archives

- Full pre-lean draft details, pseudocode, and extended verification instructions are archived verbatim at: [ai-document/history/MVP-001/pre-lean.md](../history/MVP-001/pre-lean.md) (SHA-256: `8f9a5a9b9d032b431e302da104f1844422c5a09039adfd107c149258ec615515`).

### Chat handoff prompt

```text
Status: DRAFT
Recipient: Developer
Intent: deferred-test-fix

Keep MVP-001 as TEST_DEBT / DEFERRED under ai-document/test-debt.md. After Product Owner resumes testing, prepare integration, privacy, performance and package verification from CODE-001 implementation evidence. Do not infer acceptance, create a release, commit or deploy without authorization.
```
