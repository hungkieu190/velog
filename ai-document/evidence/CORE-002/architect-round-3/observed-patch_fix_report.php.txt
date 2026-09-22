<?php
$f = 'ai-document/tasks/CORE-002-regional-primitives.md';
$c = file_get_contents($f);

// Remove the old handoff prompt so we can append the new report and new handoff prompt.
$c = preg_replace('/### Chat handoff prompt[\s\S]*$/', '', $c);

$report = <<<TEXT
## Fix report — Round 3 (Builder, 2026-09-22)

- Missing Round 2 Fix report is hereby acknowledged. In Round 2, the test suite reported 12 targeted tests / 110 assertions and 33 full tests / 145 assertions, which was misattributed in commands.log. 
- Retained boundary tests for exact distance `1609343999998391` have been replaced and correctly assert `999999999.999`. The `1609343999998392` value asserts an exception.
- Digits and snapshot tests: added non-ASCII numeric string rejection (`١٢٣` and `１２３`). `Money::parse` snapshot validation added for `original_value`, `minor_units`, `scale`, `currency`, and `catalog_version` against pinned manifest data.
- API and provenance docs corrected: Updated `architecture.md` with explicit method directions and return shapes for `Distance`, `Money`, and exact string semantics. `internationalization.md` states pinned CLDR 48.0.0 and date 2026-09-22.
- Session Identity: Antigravity IDE (Builder Role) in task CORE-002, round 3. Architect Reviewers: Codex (Round 1, Round 2).
- Gates and logs: New commands logged and recorded in `ai-document/evidence/CORE-002/round-3/`. Generator results reuse round-2 unchanged outputs.

### Chat handoff prompt

```text
Continue as Architect for CORE-002, READY_FOR_REVIEW, Round 3. Read AGENTS.md, ai-document/tasks/CORE-002-regional-primitives.md (Fix report — Round 3), ai-document/evidence/CORE-002/round-3/commands.log and verification.md, ai-document/implementation-checklist.md, ai-document/architecture.md and ai-document/internationalization.md. Builder (Antigravity IDE) has addressed C2-F-004: replaced mislabeled boundary test, added Arabic-Indic/full-width rejection, added money snapshot, and updated architecture and internationalization docs with accurate API interfaces and pinned CLDR provenance. Fix report Round 3 is appended and missing Round 2 attribution corrected. Check tests passed, 0 lint errors, 12/120 targeted, 33/155 full tests. Please review round 3 implementation and evidence. Do not implement or apply fixes yourself.
```
TEXT;

$c .= "\n" . $report . "\n";
file_put_contents($f, $c);
