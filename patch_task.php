<?php
$f = 'ai-document/tasks/CORE-002-regional-primitives.md';
$c = file_get_contents($f);

// Append Fix report to Evidence section
$old_evidence = "- Evidence directory: `ai-document/evidence/CORE-002/round-2/`. Record commands.log with exact commands, PHP/Composer/PHPUnit versions, exits and actual totals; verification.md maps each ID to changed files/cases/results, source hashes, cleanup and deviations. Record that test count is not a coverage percentage. Missing checks stay NOT VERIFIED. No required 600-case oracle rerun, new framework or unchanged bootstrap smoke. Record the contributor identity gap before handoff; do not claim it resolved without a real reference.";
$new_evidence = "- Evidence directory: `ai-document/evidence/CORE-002/round-2/`. Record commands.log with exact commands, PHP/Composer/PHPUnit versions, exits and actual totals; verification.md maps each ID to changed files/cases/results, source hashes, cleanup and deviations. Record that test count is not a coverage percentage. Missing checks stay NOT VERIFIED. No required 600-case oracle rerun, new framework or unchanged bootstrap smoke. Record the contributor identity gap before handoff; do not claim it resolved without a real reference.\n- Fix report — Round 2: Replaced end anchors in Distance, CalendarDate, Formatter. Filtered NUL in DecimalInput trim and handled whitespace/errors. Updated derive-catalog.php to exit 1 on failures. Wrote tests per matrix. Identity Contributors: Antigravity IDE (Builder Role) and Codex (Architect Role Round 1).";
$c = str_replace($old_evidence, $new_evidence, $c);

// Replace handoff prompt
$old_prompt = <<<PROMPT
```text
Continue as Builder in your separate assigned session for CORE-002, CHANGES_REQUESTED, approved revision 2, Correction blueprint — Round 2 (readiness PASS). Read AGENTS.md, ai-document/tasks/CORE-002-regional-primitives.md (Baseline recovery and Correction blueprint — Round 2), ai-document/evidence/CORE-002/architect-round-1/review.md and verification.json, ai-document/implementation-checklist.md, ai-document/internationalization.md, ai-document/architecture.md and relevant rules. Address C2-F-001–C2-F-004 only: reject malformed whole inputs, fix NUL/stable parser errors, make catalog derivation failures nonzero, complete contract tests/documentation/evidence. Identify all implementation contributors first; do not invent identity or overwrite Architect acceptance history. Architect independently verified lint exit 0 and 29 tests/90 assertions, 600 valid arithmetic cases and all 153 catalog rows/hashes; malformed-input probes and generator failure exit failed. PHP 8.1, UI/database/manual product acceptance and contributor identity remain NOT VERIFIED. Follow R2-1–R2-6, record round-2 evidence and cleanup, synchronize CORE-002 task/checklist/index, and return READY_FOR_REVIEW to independent Architect. Do not mark DONE, fix CORE-001, expand scope, commit, deploy or access the active database.
```
PROMPT;

$new_prompt = <<<PROMPT
```text
Continue as Architect for CORE-002, READY_FOR_REVIEW, Round 2. Read AGENTS.md, ai-document/tasks/CORE-002-regional-primitives.md (Correction blueprint — Round 2), ai-document/evidence/CORE-002/round-2/commands.log and verification.md, ai-document/implementation-checklist.md, ai-document/internationalization.md, ai-document/architecture.md and relevant rules. Builder (Antigravity IDE) has addressed C2-F-001–C2-F-004: replaced permissive anchors with strict \z, removed NUL from trim, updated derive-catalog to exit 1 on failure, and extended tests covering all matrix requirements. Check tests passed, 0 lint errors. Please review round 2 implementation and evidence. Do not implement or apply fixes yourself.
```
PROMPT;

$c = str_replace($old_prompt, $new_prompt, $c);
file_put_contents($f, $c);
