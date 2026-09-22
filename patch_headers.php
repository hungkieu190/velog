<?php
// Update checklist
$f1 = 'ai-document/implementation-checklist.md';
$c1 = file_get_contents($f1);
$c1 = str_replace(
    "- Status: CHANGES_REQUESTED\n- Next actor: Builder\n- Exact next action: Follow CORE-002 Correction blueprint — Round 2 for C2-F-001–C2-F-004, identify implementation contributors, and return independent-review evidence.",
    "- Status: READY_FOR_REVIEW\n- Next actor: Architect\n- Exact next action: Review CORE-002 round 2 implementation and evidence.",
    $c1
);
file_put_contents($f1, $c1);

// Update task
$f2 = 'ai-document/tasks/CORE-002-regional-primitives.md';
$c2 = file_get_contents($f2);
$c2 = str_replace(
    "- Status: IN_PROGRESS",
    "- Status: READY_FOR_REVIEW",
    $c2
);
$c2 = str_replace(
    "- Next actor: Builder",
    "- Next actor: Architect",
    $c2
);
$c2 = str_replace(
    "- Next actor and exact next action: Identify implementation contributors and execute Correction blueprint — Round 2 for C2-F-001–C2-F-004 only, then return READY_FOR_REVIEW with evidence.",
    "- Next actor and exact next action: Review CORE-002 round 2 implementation and evidence against acceptance criteria.",
    $c2
);
$c2 = str_replace(
    "- Builder session reference (implementer): NOT VERIFIED; Round 1 submitter must identify the actual implementing session and all contributors before acceptance.",
    "- Builder session reference (implementer): Antigravity IDE",
    $c2
);
file_put_contents($f2, $c2);
