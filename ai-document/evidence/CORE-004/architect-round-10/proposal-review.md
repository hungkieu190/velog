# CORE-004 Architect proposal review — round 10

## Intake and decision

- Intake: PASS. Task, checklist, and README consistently identify `READY_FOR_REVIEW` and Architect as next actor. Builder round-10 proposal revision 8 and report exist. No escalated application code was changed.
- Decision: **APPROVED FOR IMPLEMENTATION**. The implementation gate is now open for CORE4-F-002, F-004, and F-005.

## Resolution of blocking defects

1. **F-004 subscriber nonce acquisition:** Resolved. The test-only MU-plugin correctly simulates the real `admin-ajax.php` environment to fetch session-bound nonces.
2. **F-004 browser login and helper invocation:** Resolved. The explicit `$PORT` and `$WP_DIR` arguments, combined with the `wordpress_logged_in` grep, prove that the bash runner correctly authenticates before testing.
3. **F-004 exact raw-byte oracle:** Resolved. The `wp eval` script securely fetches the un-deserialized bytes and encodes them as `base64` for exact shell comparison.
4. **F-004 redirect oracle:** Resolved. The `-D headers.txt` pattern accurately traps the `Location:` header independently of the response body.
5. **F-004 repository oracle completeness:** Resolved. The `WP_User` guard and audit envelope assertions remove ambiguity over historical record lineage.
6. **F-002 error-classification oracle:** Resolved. Strictly injecting `1062` and `1146` via mocked `mysqli` guarantees the correct `db_error` and `stale_version` classifications.
7. **F-005 evidence path collision:** Resolved. Implementation evidence is definitively bound to `ai-document/evidence/CORE-004/implementation-round-3/`.

## Open findings and implementation path

- **CORE4-F-001** (conditional SQL CAS missing), **CORE4-F-003** (asset path real scoping missing), and **CORE4-F-006** (top-level menu callback/capability missing) remain unverified findings.
- **CORE4-F-002**, **CORE4-F-004**, and **CORE4-F-005** are approved based on the cumulative read-only proposal revisions 1 through 8. 
- All constraints from previous revisions (e.g., avoiding disabled `assert()`, utilizing errno 1062, ensuring valid PHP type signatures) must be strictly implemented.

## Required next steps

Builder must implement application code, unit tests, and the `product-smoke.sh` integrations exactly as described in the approved proposals.
Store all execution output, diffs, lint checks, test logs, and manual UI walkthrough observations in the approved `ai-document/evidence/CORE-004/implementation-round-3/` directory.

After generating all required evidence and verifying everything passes, the Builder will synchronize task/checklist/README to `READY_FOR_REVIEW` and hand back to the Architect for the third implementation review.
