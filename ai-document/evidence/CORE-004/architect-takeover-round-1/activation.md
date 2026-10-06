# CORE-004 Architect takeover activation

## Authority and trigger

- Activated: 2026-10-05 under the user-approved mandatory Architect takeover policy.
- Findings: CORE4-F-002, CORE4-F-004, CORE4-F-005.
- Trigger evidence: the same underlying defects remained unresolved after Builder implementation rounds 1 and 2, reviewed at `ai-document/evidence/CORE-004/architect-round-1/review.md` and `ai-document/evidence/CORE-004/architect-round-2/review.md`.
- Prior proposal revisions 1–8 are advisory history. Further Builder proposal or implementation cycles for these findings stop.

## Scope and independence exception

Architect may edit the application, tests, workflow fixtures, PHPCS configuration, and documentation required to close F-002/F-004/F-005. F-001/F-003/F-006 remain outside takeover scope and stay on the normal Builder/independent-review path.

The Architect will implement, inspect its own diff, execute all applicable positive/negative, security, concurrency, lint, test, runtime, UI, and cleanup gates, and record evidence under `ai-document/evidence/CORE-004/architect-takeover-round-1/`. Closure must be labeled `SELF_ACCEPTED_UNDER_ARCHITECT_TAKEOVER` and must not be described as independent review. Failed evidence keeps the finding open under Architect ownership.

## Initial implementation blueprint

- F-002: strict raw option read/validation, exact binary CAS, duplicate errno classification, canonical request validation, error propagation, and option-cache consistency.
- F-004: real two-process public save race; public `RecordRepository::create()` historical seed; exact raw-envelope preservation through km/mi, JPY/USD/KWD and locale changes; authenticated HTTP capability/nonce/malformed-input controls in the isolated product-smoke environment.
- F-005: resolve all current PHPCS/PHPStan findings, preserve narrow documented direct-query suppressions, run the complete quality/runtime matrix, record manual UI and NOT VERIFIED cases, and prove owned cleanup behavior.

## Exit from takeover

After takeover findings pass verification and are self-accepted, return F-001/F-003/F-006 to Builder unless they have independently reached the same two-review threshold. The task reaches manual acceptance or DONE only when all remaining acceptance criteria and findings are resolved.
