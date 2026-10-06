# CORE-004 Architect proposal review — round 9

## Intake and decision

- Intake: PASS. Task, checklist, and README consistently identify `READY_FOR_REVIEW` and Architect as next actor. Builder round-9 proposal revision 7 and report exist. No escalated application code was changed.
- Decision: **REVISE PROPOSAL**. The implementation gate remains closed for CORE4-F-002, F-004, and F-005.
- Revision 7 resolves stable errno inspection, array-based repository access, explicit nonzero checks, application-owned 403 responses, and reuse of the existing random port/server. The remaining test design defects below would produce false or non-executable evidence.

## Blocking defects

1. **F-004 subscriber nonce acquisition:** A subscriber cannot fetch `admin.php?page=velog-settings`; `RegionalSettingsPage::render()` denies users without `mf_velog_manage_settings`. Therefore `sub_page.html` cannot contain the nonce. In the isolated product-smoke site, install a test-only MU-plugin AJAX endpoint that returns `wp_create_nonce( 'velog_save_settings' )` for the currently authenticated cookie session. Fetch separate manager and subscriber nonces from that endpoint. Remove it with the owned disposable site.
2. **F-004 browser login and helper invocation:** Define the exact login flow, including initial cookie acquisition, POST with `-b/-c`, redirect/status validation, and proof that each cookie represents the expected user. Invoke the helper with explicit arguments such as `$PORT`, `$WP_DIR`, and the owned output directory; do not rely on unexported parent shell variables.
3. **F-004 exact raw-byte oracle:** `wp option get --format=json` deserializes and normalizes the option; comparing that output does not prove raw row bytes are unchanged. Read `option_value` directly through `$wpdb` and emit base64, or query through the private MariaDB socket. Compare the exact encoded raw bytes before and after every denied/malformed request.
4. **F-004 redirect oracle:** A non-followed curl 302 response exposes its destination in the `Location` header, not reliably in the response body. Capture headers with `-D` and assert the exact fixed `Location` query/error code. Keep the response body assertion separate.
5. **F-004 repository oracle completeness:** Check `get_user_by()` returns `WP_User` before cloning/passing it. The explicit snapshot checks still omit the required nonempty audit envelope and initial audit entry identity. Add failing checks for `audit`, plus the already listed fields, version and author.
6. **F-002 error-classification oracle:** Revision 7 implements errno 1062, but still does not describe the required negative control proving an unrelated SQL error maps to `db_error`. Name the duplicate-race test and a separate injected non-1062 query failure; both must assert exact error codes and unchanged data.
7. **F-005 evidence path collision:** `builder-round-9` already contains proposal revision 7 and its report. Stop reserving the next Builder proposal directory. Use the stable path `ai-document/evidence/CORE-004/implementation-round-3/` for the future third implementation attempt, regardless of how many proposal revisions occur.

## Repeated-defect status

- CORE4-F-004 real HTTP oracle remains unresolved across revisions 5–7: authentication improved, but the denied actor still cannot obtain the proposed nonce and the row/redirect checks are not byte/status accurate.
- CORE4-F-005 evidence-directory collision repeated after explicit round-8 correction. The stable implementation-round path above removes the moving-target problem.
- CORE4-F-002 implementation mechanism is acceptable at proposal level once its duplicate/nonduplicate failure oracles are named.
- Proposal reviews do not increment implementation-review failures. F-002/F-004/F-005 remain at two consecutive implementation review failures.

## Evidence inspected

- Builder round-9 proposal revision 7 and report.
- `RegionalSettingsPage::render()` capability boundary and nonce action.
- `RecordRepository::get()` snapshot shape.
- Existing `product-smoke.sh` server, random port and disposable site lifecycle.
- Current Builder round-9 evidence directory contents. No application or test code was changed by Architect.

## Required revision 8

Submit a read-only delta addressing only defects 1–7. Preserve all resolved decisions. Do not implement F-002/F-004/F-005 until Architect records `APPROVED FOR IMPLEMENTATION`.

F-001, F-003, and F-006 remain unverified on the normal implementation path.
