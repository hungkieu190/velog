# CORE-004-FE Architect review — Round 2

Date: 2026-10-06.

Decision: `CHANGES_REQUESTED`. The source, generated asset, build, whitespace, boundary, focus, narrow-layout, and cleanup checks pass. Two required browser cases remain unverified for this revision.

## Passed checks

- The presentation diff is limited to `src/css/admin.scss` and generated `assets/css/admin.css`.
- `git diff --check` exits 0.
- `npm run production` exits 0 and rebuilds four production files.
- `npm run test:workflow` exits 0 with 18/18 tests passing.
- The submitted focus screenshots show visible focus on the distance and currency controls.
- The submitted 320px screenshot shows the form stacked without visible horizontal page overflow.
- No `/tmp/velog-fe-verify-*` directory or matching PHP/MariaDB process remained during review.

## Required corrections

1. The report labels its RTL check as an RTL locale check, but it only injects `document.body.classList.add('rtl')`. Activate a real RTL WordPress locale in the disposable site and retain the locale, document `lang`/`dir`, body class, and presentation result. Class injection alone does not verify WordPress RTL behavior.
2. The 320px screenshot contains ordinary English labels and descriptions. It does not demonstrate the required long translated content case. Use a visibly long translated label/description or other controlled long-content fixture, record the viewport and document widths or overflow result, and retain evidence.
3. Copy the final browser screenshots into `ai-document/evidence/CORE-004-FE/frontend-round-3/` so the task evidence is repository-owned. External IDE brain paths are not durable project evidence.

Do not change backend files. Preserve the accepted Sass/CSS changes unless a concrete presentation correction is required.
