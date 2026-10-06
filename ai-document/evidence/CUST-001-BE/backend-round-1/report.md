# CUST-001-BE Backend Round 1 Self-Review

Date: 2026-10-06

Decision: `SELF_REVIEWED_BACKEND`; backend workstream `DONE`.

## Implemented

- Closed customer schema with Unicode name and contact-classified phone/email validators.
- Schema-derived search projections and reserved vehicle current-customer projection.
- Manager-only bounded customer query with prepared SQL, state filtering, stable ordering, and 50-row pagination.
- Versioned create/edit/archive/restore services using DATA-001 writes.
- Active-vehicle archive guard inside the DATA-001 write lock.
- Manager-only admin page, search, pagination, row actions, per-customer bulk actions, nonce checks, escaping, fixed notices, exact customer asset hook, and plugin wiring.
- Unit tests and disposable WordPress fixture for validation, projection normalization, Unicode CRUD/search, contact redaction, stale updates, archive guard, archive/restore, and denied technician mutation.

## Verification

- `composer run lint`: exit 0; PHPCS and PHPStan pass.
- `composer run test -- --filter 'Customer(Service|Query)Test'`: exit 0; 5 tests, 15 assertions.
- `composer run test`: exit 0; 90 tests, 327 assertions.
- `bash tests/workflow/product-smoke.sh --task=CUST-001 --wp-version=6.4.3`: exit 0.
- `bash tests/workflow/product-smoke.sh --task=CUST-001 --wp-version=6.7.2`: exit 0.
- `npm run test:workflow`: exit 0; 13 tests.
- `git diff --check`: exit 0.
- Disposable smoke directories and processes were removed by the runner.

## Review notes and limits

- The backend fixture exercises services and repository behavior in real WordPress. Browser presentation, keyboard/focus behavior, 320px layout, RTL, and visual bulk/list states remain assigned to CUST-001-FE.
- Customer query hydration is bounded to 50 IDs and uses authorized repository reads for each returned item. G-08 performance remains unverified until the accepted full dataset benchmark.
- No active LocalWP data was modified. No commit, push, deployment, or release was performed.
