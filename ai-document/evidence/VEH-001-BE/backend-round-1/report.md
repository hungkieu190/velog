# VEH-001 implementation and technical self-review — round 1

Date: 2026-10-21.
Owner/contributor/reviewer: Codex, Developer.
Review mode: SELF_REVIEWED_DEVELOPMENT; intentionally non-independent under the explicit owner decision of 2026-10-21.
Technical result: PASS for BE and FE scope subdivisions. Parent/UAT remain BLOCKED on explicitly authorized live UAT preparation. No manual acceptance or browser result is claimed.

## Resulting behavior

Vehicles now have native private admin list/detail/create/edit/archive/restore, bounded identifier search/pagination, manager-only mutations and technician read-only operational views. Labels, notices, empty/error states and table/field markup use native WordPress classes. Vehicle-only Sass adds wrapping, logical spacing, keyboard-scrollable table layout and responsive constraints without changing existing customer/settings selectors. Existing generated admin CSS is enqueued only on the exact new vehicle hook in addition to prior allowlisted screens.

The vehicle schema includes optional make/model/color/year, private customer relation, preferred unit and exact original-unit/canonical-mm/date baseline tuples. Omitted legacy fields remain readable; no automatic rewrite/migration occurs. Unknown baseline is an empty tuple and remains distinct from a known zero. Site-local dates and years are used. Lowering or clearing a known reading requires a bounded plain-text manager reason. Finalized service history blocks baseline editing until SERV-001 provides neighbour-aware corrections; the present conservative guard blocks when any finalized service exists rather than guessing a relation that is not yet implemented. This is not service finalization implementation.

Repository extensions declare optional/binary uniqueness and a trusted write guard on the existing pinned coordinator transaction. Plate/jurisdiction and VIN are independently unique across active/archived vehicles, omitted complete tuples do not collide, and binary comparisons preserve explicit Unicode identity. Candidate integrity checks remain in place. The guard validates active private-customer assignment/restoration under the same lock as customer archive, including direct repository calls. Generic schemas without these optional declarations keep prior behavior. Empty-value validators for year and customer relation now accept their own canonical integer-zero sentinel on repeated repository validation; negative/forged IDs remain rejected.

HTTP uses POST-only allowlisted operations, capability checks before work, object/action nonces, strict scalar/UUID/version validation, escaped output and fixed safe notices. Create form request identity prevents replay duplication. Attempted values are bounded and actor-scoped outside navigation URLs. Missing/invalid detail never silently renders a create form. Query hydration is batched; customer summaries contain only id/name/state, and technician views omit mutation controls and audit.

## Owner/workflow amendment

The user explicitly made Codex the sole backend/frontend Developer and retained human Tester acceptance. AGENTS.md, role JSON, rule adapter, AI-agent rule, current workflow, setup kit and relevant active docs/tasks now express that model. Historical contributions/reviews remain evidence. The read-only dashboard accepts Developer, presents Developer/Tester for current work and keeps historical actor labels parseable; no dispatch or background automation was added.

## Actual verification

| Command / environment | Exit and result | Evidence |
| --- | --- | --- |
| composer run lint | 0; full PHPCS and PHPStan pass | lint-verified.txt |
| composer run test | 0; 100 tests, 394 assertions | unit-final.txt |
| Node 24.21.0 --test tests/workflow/*.test.mjs | 0; 22/22 pass | workflow-final.txt |
| Node 24.21.0 / npm production build | 0; four generated files; only admin CSS changed | build.txt; source-sha256.txt |
| product-smoke VEH-001, WP 6.4.3 / MariaDB 10.11.14 | 0; domain/HTTP, role, rollback/SQL-failure and subprocess concurrency assertions | wp643.txt |
| product-smoke VEH-001, WP 6.7.2 / MariaDB 10.11.14 | 0; same vehicle matrix | wp672-final.txt |
| product-smoke VEH-001, copied WP 7.1.3 / isolated MySQL 8.4.0 | 0; vehicle matrix, HTTP and concurrency | wp713-mysql84.txt |
| Same target lane with UAT seed regression | 0; read-only plan, exact create/verify/reapply, private credentials, non-owned-data preservation and drift refusal | uat-preparation-regression.txt |
| product-smoke CUST-001, WP 6.7.2 / MariaDB | 0; customer domain/HTTP/privacy/batch plus disposable provisioning regression | customer-regression-final.txt |
| DATA-001 run.php --wp-version=6.7.2 | 0; V1–V5 harness/negative control/cleanup; V5 is the existing microdataset | data001-summary.txt; data001/ |
| Read-only LocalWP inventory and new UAT plan | 0; WP 7.1.3, PHP CLI 8.3.6, MySQL 8.4.0, existing test staff roles and one vehicle; plan writes=false | local-inventory.json; local-runtime.json; local-preparation-plan.json |
| PHP and shell syntax, git diff --check, source/generated/security review and cleanup | 0 | syntax.txt; final-checks.txt; cleanup.txt |

Integration PHP was 8.3.6. PHP 8.1/minimum-runtime and LocalWP web PHP were not independently exercised. The old PHPStan upgrade notice is informational; dependencies were not changed. The unit suite's intentional capability rollback negative case prints an existing critical-failure diagnostic while its assertion runner passes. DATA-001's expected negative control likewise emits failure text; the harness verifies that negative outcome and exits 0 overall. Its prior live-handle COMMIT, coordinator-1213 and persistent-cache exclusions remain NOT VERIFIED; full G-08 performance is not established by this microdataset.

## Failures found and corrected

- First unit run exposed the need for a site-calendar wp_date double in the pure unit environment; the unit fixture was corrected without substituting UTC behavior in production.
- Initial PHPCS/PHPStan runs found formatting, nonce-read annotations and type-narrowing issues. They were corrected; final full gates pass. Input/domain checks and object/action nonce enforcement remain active.
- First vehicle runtime run found that explicit customer unassignment normalized to 0 then failed the second validation pass. The canonical-sentinel validator and regression tests were corrected; the same year-clear case was fixed and covered, including HTTP create with year blank.
- First CUST-001 regression reached and passed the customer domain/HTTP assertions but the existing provisioning helper rejected the new full CSS hash. The exact new hash is allowed only on the helper's restricted disposable path; its live CUST-001 preparation remains pinned to the historical asset. The full regression then passed. No live CUST-001 seed was run.

Earlier failed attempts are retained under their original filenames; successful results above identify the closing runs. No failure is represented as a successful command.

## UAT preparation boundary

[Preparation proposal](../preparation-proposal-2026-10-21.md) specifies the reviewable live package: reuse the two existing staff accounts; create three separate customer fixtures, 51 separate vehicles and one subscriber test actor; private backup/manifest/password/lock files, no mail and no existing-data changes. Implementation is ready and tested only on disposable sites. Live plan succeeded without writes; live apply and backup export have not run. Parent and UAT stay BLOCKED, Next actor Developer, until that separate bounded write scope is approved and verified. Tester supplies browser/RTL/screen-reader tools and alone records manual PASS/FAIL/BLOCKED.

## Source review and cleanup

Reviewed shared storage additions, direct and service write routes, capabilities/nonces, exact input types, binary optional uniqueness, projection/cardinality checks, state/audit/version behavior, customer contact boundaries, generated CSS scope and readonly dashboard escaping/actor consistency. Evidence hashes identify the reviewed application/test/tooling bytes.

All owned disposable sites/databases/PHP processes are absent; failed-run copied diagnostics were removed after retaining their assertion outcomes. No scratch files were placed in application source. Reusable fixtures remain under tests/. Existing uncommitted documentation work was preserved. No active-site data write, browser test, external message, commit, push, deploy or release occurred.

Generated admin CSS SHA-256: 13a9bc27e6fd0d14795c64e43984eb15eea96b31eef90640ac4ca8ac6703b8f4.
