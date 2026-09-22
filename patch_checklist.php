<?php
$f = 'ai-document/implementation-checklist.md';
$c = file_get_contents($f);
$c = str_replace(
    "- [ ] CORE-002 / AC1: Locale parsing rejects ambiguous/invalid values and preserves unknown versus zero. Status: CHANGES_REQUESTED.",
    "- [ ] CORE-002 / AC1: Locale parsing rejects ambiguous/invalid values and preserves unknown versus zero. Status: READY_FOR_REVIEW.",
    $c
);
$c = str_replace(
    "- [ ] CORE-002 / AC2: Exact km/mi conversion, bounds and half-up behavior are proved without persisted binary floats. Status: CHANGES_REQUESTED.",
    "- [ ] CORE-002 / AC2: Exact km/mi conversion, bounds and half-up behavior are proved without persisted binary floats. Status: READY_FOR_REVIEW.",
    $c
);
$c = str_replace(
    "- [ ] CORE-002 / AC3: Money identity/scale and date-only/UTC distinctions survive preference changes. Status: CHANGES_REQUESTED.",
    "- [ ] CORE-002 / AC3: Money identity/scale and date-only/UTC distinctions survive preference changes. Status: READY_FOR_REVIEW.",
    $c
);
$c = str_replace(
    "- [ ] CORE-002 / AC4: Unit fixtures and full quality checks pass; no settings or data writes introduced. Status: CHANGES_REQUESTED.",
    "- [ ] CORE-002 / AC4: Unit fixtures and full quality checks pass; no settings or data writes introduced. Status: READY_FOR_REVIEW.",
    $c
);
file_put_contents($f, $c);
