# CORE-002: Exact regional value primitives

## Current handoff
- Status: DONE
- Plan revision: 2; correction blueprint Round 4
- Architect session reference: Codex planning conversation of 2026-09-21; independent review Round 4.
- Builder session reference: Antigravity IDE (Round 3 and Round 4).
- Implementation contributors and reviewer independence check: Antigravity Builder is the only implementation contributor. Codex Architect review is independent.
- Related checklist items: CORE-002 / AC1–AC4.
- Baseline branch and commit: main at 2aa3b8d.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 batch planning.
- Latest round: Independent Architect acceptance — Round 4; AC1–AC4 PASS; C2-F-001–C2-F-004 CLOSED.
- Latest report: ai-document/evidence/CORE-002/architect-round-4/review.md
- Evidence: ai-document/evidence/CORE-002/architect-round-4/
- Next actor: Architect
- Next actor and exact next action: Continue dependency-ordered planning with CORE-003 readiness; preserve CORE-002 accepted files and evidence.

## Problem and intended behavior

No shared distance, money, localized-number or calendar-date services exist. Future records must preserve their original unit/currency and use exact comparisons independent of shop preferences.

## Scope and references

- New files: `src/Common/Regional/DecimalInput.php`, `Distance.php`, `Money.php`, `CalendarDate.php`, `Formatter.php`.
- New tests: `tests/Unit/RegionalPrimitivesTest.php`.
- Documentation: `ai-document/internationalization.md` and `ai-document/architecture.md`.
- Excluded: settings options, UI, CPT, lifecycle changes, dependencies, exchange rates.

## Acceptance criteria

- [x] CORE-002 / AC1: Locale parsing rejects ambiguous/invalid values and preserves unknown versus zero. Status: DONE.
- [x] CORE-002 / AC2: Exact km/mi conversion, bounds and half-up behavior are proved without persisted binary floats. Status: DONE.
- [x] CORE-002 / AC3: Money identity/scale and date-only/UTC distinctions survive preference changes. Status: DONE.
- [x] CORE-002 / AC4: Unit fixtures and full quality checks pass; no settings or data writes introduced. Status: DONE.

## Findings and acceptance summary

- **C2-F-001–C2-F-004**: All CLOSED in Round 4.
- **Acceptance**: Accepted by independent Architect in Round 4 (2026-09-22). Retained review under [ai-document/evidence/CORE-002/architect-round-4/review.md](../evidence/CORE-002/architect-round-4/review.md).
- Verification: targeted 12/117, full 33/152 assertions pass, PHPCS/PHPStan exit 0, diff check exit 0.

## History and archives

- Full pre-lean task history and earlier implementation/review rounds are archived verbatim at: [ai-document/history/CORE-002/pre-lean.md](../history/CORE-002/pre-lean.md).

### Chat handoff prompt

```text
Status: DONE
Recipient: Architect
Intent: review

Continue as Architect for CORE-003, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/CORE-003-access-private-types.md, ai-document/tasks/PLAN-002-mvp-task-batch.md, ai-document/implementation-checklist.md, and accepted CORE-001/CORE-002 evidence. CORE-002 is DONE after independent Round 4 acceptance: AC1–AC4 PASS, C2-F-001–C2-F-004 CLOSED; targeted 12/117, lint, full 33/152 and diff check pass on PHP 8.3.6. PHP 8.1 and product UI/database/manual acceptance remain NOT VERIFIED. Resolve G-02 and CORE-003 technical readiness, reinspect current repository interfaces, and prepare an executable revision-2 blueprint before any Builder handoff. Do not implement application code, mark CORE-003 DONE, commit, deploy, or access the active database.
```
