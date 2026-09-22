<?php
$f = 'ai-document/tasks/CORE-002-regional-primitives.md';
$c = file_get_contents($f);
$c = str_replace(
    "Status: CHANGES_REQUESTED",
    "Status: IN_PROGRESS",
    $c
);
file_put_contents($f, $c);
