<?php
// Update checklist
$f1 = 'ai-document/implementation-checklist.md';
$c1 = file_get_contents($f1);
$c1 = str_replace(
    "- Status: IN_PROGRESS\n- Next actor: Builder\n- Exact next action: Execute CORE-002 Correction blueprint — Round 3.",
    "- Status: READY_FOR_REVIEW\n- Next actor: Architect\n- Exact next action: Review CORE-002 round 3 implementation and evidence.",
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
    "- Next actor and exact next action: Execute Correction blueprint — Round 3 for C2-F-004.",
    "- Next actor and exact next action: Review CORE-002 round 3 implementation and evidence against acceptance criteria.",
    $c2
);
file_put_contents($f2, $c2);
