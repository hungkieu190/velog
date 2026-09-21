# WF-002: Animated role scenes in the progress dashboard

## Current handoff
- Status: DONE
- Plan revision: 1
- Architect session reference (planner): Current Codex planning conversation of 2026-09-21
- Builder session reference (implementer): Antigravity Builder
- Implementation contributors and reviewer independence check: User accepted the Builder's work directly.
- Related checklist items: WF-002 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d
- User approval reference and approved scope: User explicitly requested infinite animation and accepted the work ("ok done task này đi, k cần architect review nữa") on 2026-09-21.
- Latest round: Approved assignment — Round 1 (2026-09-21).
- Next actor: None
- Next actor and exact next action: None. Task is DONE.

## Problem and inspected behavior

scripts/progress-view.mjs renders three numbered owner labels from currentFocus.owner. scripts/progress-data.mjs maps status to workflow owner; it does not observe running agents. CHANGES_REQUESTED and READY currently identify Builder even when no implementation is running. DONE maps to Architect for acceptance ownership. The owner highlight therefore must not automatically imply work is happening.

The dashboard is server-rendered, read-only and refreshes every 20 seconds. Its stylesheet is src/css/progress.css, served directly by scripts/progress-dashboard.mjs. Existing CSP permits same-origin external CSS and no browser JavaScript. This design fits those constraints without a browser library, external image fetch or runtime asset build.

## Design proposal

- Visual thesis: a quiet illustrated workshop on the existing pale dashboard surface, with a small engineer's desk and mining scene making the workflow understandable at a glance.
- Content plan: preserve task title/status/next action; replace only the numbered owners strip with three illustrated roles, each showing role and exact state label; keep checklist/history unchanged. Add one compact shared caption: "Recorded task state · updates every 20s".
- Interaction thesis: a short drawing gesture for planning, a two-strike pickaxe sequence for implementation, and a subtle initial emphasis on the selected role. Idle roles do not perform work. No scroll effects or motion elsewhere.

Use three horizontal scenes on desktop, about 160–190px wide and 110px high per illustration; wrap/stack below 640px with no horizontal overflow at 320px. Keep role names and state labels as real HTML text outside artwork. Use one restrained green accent, existing ink/neutrals and a small muted helmet detail; distinguish characters by silhouette/tools rather than strong competing role colors. Avoid new enclosing card borders/shadows.

Architect: engineer at an angled blueprint desk, a visible plan grid, a pencil/forearm pivot and a small ruler. Drawing moves the hand over one plan line; a line is revealed by opacity/transform rather than animating expensive layout. Builder: helmeted miner, raised arm and pickaxe, angular rock; pivot forearm/tool about the shoulder, a slight body settle and two tiny dust shapes at impact. User: person with checklist/approval sheet, static while awaiting a decision. These are small clean vector scenes, not numbered circles or emoji stand-ins.

Use static inline SVG markup owned by the view, with groups/classes for limbs/tools/dust. No embedded script/style, external references, image URLs, foreignObject, dynamic SVG paths or raw Markdown interpolation. All styling/keyframes remain in progress.css. SVG is decorative (aria-hidden=true, focusable=false); accessible role/status comes from adjacent text. Prefer currentColor and CSS classes; use local viewBox coordinates and stable transform-origin/transform-box for each moving group.

### State contract

This depicts recorded workflow state, never live agent telemetry. Do not add "Live", "Online" or "Agent running" claims. Use currentFocus, not whichever task happens to be last in the table. Selecting an owner and animating work are separate decisions.

| Recorded state | Selected role | Visible label | Motion |
|---|---|---|---|
| DRAFT | Architect | Planning | Engineer draws a short blueprint sequence; label represents recorded planning stage only |
| READY | Builder | Ready to start | Miner rests with pickaxe, no striking |
| IN_PROGRESS | Builder | Implementation in progress | Miner performs two deliberate pickaxe strikes |
| CHANGES_REQUESTED | Builder | Fixes requested | Miner rests; do not imply fixes are underway until IN_PROGRESS |
| READY_FOR_REVIEW | Architect | Review queued | Engineer holds blueprint, no drawing; status does not prove reviewer has started |
| AWAITING_MANUAL_ACCEPTANCE | User | Awaiting your decision | Checklist scene selected, static |
| BLOCKED | Recognized declared owner if any | Blocked | All scenes static; preserve next-action text explaining blocker |
| DONE | No working role | Completed | All scenes static; completed caption, no engineer-work inference from historical owner |
| Missing/unknown status or contradictory current owner | No working role | State needs attention (No active task if empty) | All scenes static; preserve existing documentation signals |

Suppress work animation for a current-focus status/owner contradiction, not for an unrelated historical task warning. Inspect both focus.owner and focus.declaredOwner: the parser derives owner from status and may retain a conflicting declaredOwner; comparing the derived owner alone would miss that conflict. A missing declaredOwner is not itself a contradiction. Keep the parser's existing meaning and JSON schema unchanged. Reject unknown owner strings for class selection through a fixed role allowlist; escape text through existing escapeHtml. Preserve existing documentation-signal reporting.

### Motion and accessibility

Use CSS transform/opacity only for moving parts. Each work sequence lasts at most 4.5 seconds with two gestures and then settles in a readable pose; no infinite animation and no rapid flashing. The existing page refresh can replay the finite scene, but no heartbeat/real-time signal is claimed. This bounded sequence avoids introducing a persistent pause preference or a new client-side script. If continuous looping is requested later, scope an accessible persistent pause mechanism first.

Under prefers-reduced-motion: reduce, disable all scene, entrance and status-transition animation, including descendants/pseudo-elements; final static poses must remain legible and selected role/status obvious. Visible text and underline/weight indicate selection, not color alone. The illustration is not a button and must not add fake focus targets or hover-only information. Keep focus indicators on existing links/controls. Check long labels and zoom at 200%.

## Scope and implementation blueprint

- Revision: 1; AC1–AC4.
- Blueprint readiness: PASS — Architect confirmed revision 1 covers AC1–AC4/V1–V7, inspected files/callers/CSP, bounded resource cleanup and explicit user approval. This is assignment readiness, not implementation acceptance.
- Allowed files: scripts/progress-view.mjs (static scenes, role-state presentation helper, strip rendering); src/css/progress.css (scoped role styles/keyframes/responsiveness/reduced motion); new tests/workflow/progress-roles.test.mjs (state/security regression cases); this task/report, evidence and walkthrough; checklist handoff entry when dispatched.
- Excluded: scripts/progress-data.mjs owner semantics, scripts/progress-dashboard.mjs routes/CSP/refresh, package.json/dependencies, runtime PHP, generated assets, build/release logic and CORE-001 code/evidence/status. Do not redesign other dashboard sections.
- Authoritative documentation: this task owns the bounded UI behavior; add a short link to it in ai-document/architecture.md after approval/implementation rather than duplicating the state table. No product-feature documentation is needed.
- Rationale: static vector groups plus the existing external stylesheet support the requested scenes under current CSP, maintain read-only behavior and require no plugin runtime rebuild. Do not use image generation for these code-native vector assets.

### Ordered implementation steps

1. After approval/READY, record Builder identity/baseline and preserve concurrent work. Reinspect the three dashboard files and existing dashboard test; resolve any changed interfaces before editing.
2. Add a pure role-presentation helper beside renderProgress, driven by status, recognized owner and current-task consistency. It returns allowlisted selected role, motion kind and readable label; do not infer work solely from .active.
3. Replace the owners markup with static SVG scenes and text. Preserve task title, next action, read-only caption, existing escapeHtml and the rest of the dashboard. Keep no-focus fallback safe.
4. Add scoped CSS for silhouettes, short motion, selected/idle state and narrow screens. Work motion only activates for permitted state+role; all idle limbs/dust are static and reduced motion overrides everything.
5. Run the matrix below and inspect actual browser frames at initial, mid-strike/drawing and settled times. Use fixture data in an owned temporary dashboard copy for other statuses; do not modify live CORE-001/task Markdown to create screenshots.
6. Append Builder report/evidence, disclose NOT VERIFIED checks and return independent review handoff without accepting the work.

### Critical-path pseudocode

```text
role_presentation(focus):
  if absent: return no selected worker, no motion, No active task
  validate recognized status and status-compatible owner
  if invalid: return no selected worker, no motion, State needs attention
  if DONE: return no selected worker, no motion, Completed
  select role from approved state table (BLOCKED uses recognized declared owner)
  motion = drawing for DRAFT; mining for IN_PROGRESS; none otherwise
  return selected role, fixed label, motion
render:
  render trusted scene markup; escape all document-derived text
  emit motion class from fixed allowlist only
CSS:
  run permitted short sequence; reduced-motion always overrides to static
```

### Failure/resource lifecycle

Rendering missing or inconsistent data must show the safe static fallback; no exception or invented work state. Never fetch assets remotely or add inline styles to bypass CSP. For a live preview, start a dedicated localhost server on a free port, record its PID, stop only that child and confirm shutdown. Fixture directories use mkdtemp and finally cleanup; never copy real credentials or alter live task data. Failed rendering/tests/browser checks prevent completion, not hidden by switching the current focus to an easier state. Missing browser access remains NOT VERIFIED.

## Acceptance criteria

- AC1: Numbered roles are replaced by recognizable engineer/miner/user scenes with clear text/selection and responsive composition.
- AC2: All states in the table render honestly; mining occurs only for consistent IN_PROGRESS and drawing only for consistent DRAFT; queued/blocked/completed/invalid states remain static.
- AC3: Reduced motion, decorative SVG accessibility, keyboard/zoom and existing escaping/CSP/read-only behavior are preserved.
- AC4: Relevant regression checks and actual browser evidence demonstrate scene timing, mobile layout and status switching; no runtime build/dependency or unrelated changes.

## Verification matrix

| Case | AC | Command / fixture | Expected result |
|---|---|---|---|
| V1 | AC2 | node --test tests/workflow/progress-roles.test.mjs; table-driven render fixtures for every status and missing focus | Exit 0; exactly permitted selection/motion classes and text; no working role for DONE/invalid/missing |
| V2 | AC2/AC3 | Same tests: IN_PROGRESS with Architect contradiction, BLOCKED unknown owner, malicious owner/title/status | Exit 0; static fallback, no injected attributes/elements, existing escape behavior retained |
| V3 | AC3 | node --test --test-name-pattern=dashboard tests/workflow/workflow.test.mjs | Exit 0; existing contradiction/escaping dashboard regression still passes |
| V4 | AC1/AC4 | npm run progress -- --port=4187 (or recorded free port); curl --fail http://127.0.0.1:4187/ and /progress.css | Server reachable; fetches exit 0; actual HTML/CSS show scene; no CSP violations/remote requests; stop owned server afterwards |
| V5 | AC1/AC2 | Browser fixtures at DRAFT and IN_PROGRESS: record 0s, ~1s, ~3s and >=5s; then READY_FOR_REVIEW/CHANGES_REQUESTED/DONE | Distinct drawing/striking visible in first 4.5s, settled afterwards; queued/completed scenes never perform work |
| V6 | AC1/AC3 | Browser 1440px and 320px widths, 200% zoom, reduced-motion emulation, keyboard navigation | No strip overflow/clipped text, obvious selected role, all animation disabled for reduced motion, no decorative focus targets |
| V7 | AC4 | git diff --check; inspect changed paths and generated-assets baseline | Exit 0; allowed scope only, no generated runtime files changed; npm run production is not required for local progress.css |

Tests address meaningful workflow truthfulness and escaping, not exact SVG path snapshots or arbitrary pixel comparisons. Browser screenshots alone cannot prove animation; include frame sequence or short recording, plus observed duration. Existing 20-second refresh behavior is preserved and documented, not presented as instant synchronization.

## Evidence and Builder pre-handoff

Use ai-document/evidence/WF-002/round-1/ for commands.log, state-matrix.md and browser frames/recording; ai-document/walkthroughs/WF-002.md for visual checks. Record exact versions, viewport/motion settings, server PID/port/cleanup, actual exits/test totals and a map AC -> changes -> observed evidence. Preserve contributor references and deviations. No implementation/visual verification has occurred in this planning session; all new UI/runtime checks are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for WF-002, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/WF-002-animated-role-strip.md, scripts/progress-view.mjs, src/css/progress.css and scripts/progress-data.mjs. The user requested a planning engineer and mining Builder in the progress owner strip. Confirm the concrete finite-motion/state-table proposal before READY; then issue a separate Builder assignment using Implementation blueprint revision 1 and AC1–AC4/V1–V7. Source/CSP/refresh behavior were inspected; new UI, animation and browser checks are NOT VERIFIED. CORE-001 Round 5 is concurrent work and must remain untouched. No application implementation by Architect, role switching, dependencies, runtime builds, commit or deployment.
```

## Approved assignment — Round 1

User approved the concrete design and requested a Builder prompt on 2026-09-21. Blueprint revision 1 is ready for implementation. All earlier design discussion remains historical; the current handoff and this assignment govern status. CORE-001 remains separate active work, and PLAN-002 feature drafts are not included.

Architect preparation: inspected view, stylesheet, data parser, server CSP/refresh and existing dashboard regression. Documentation handoff/whitespace checks pass; new implementation tests, animation, browser, reduced-motion and responsive evidence remain NOT VERIFIED. Record receiving Builder identity at preflight; no application code has been implemented by this Architect.

### Chat handoff prompt

```text
Act as Builder in Antigravity for WF-002, READY, revision 1. Read AGENTS.md, ai-document/tasks/WF-002-animated-role-strip.md (Scope and implementation blueprint, readiness PASS), ai-document/build-and-release.md, scripts/progress-view.mjs, src/css/progress.css, scripts/progress-data.mjs and scripts/progress-dashboard.mjs.

The user approved engineer/miner/user scenes for the progress owner strip. Implement AC1–AC4 exactly: Architect draws in DRAFT; Builder swings a pickaxe in IN_PROGRESS; queued, blocked, completed and inconsistent states remain static. Follow the full state table, finite motion <=4.5 seconds, reduced-motion support, mobile layout and accessible labels. Preserve CSP, read-only behavior and 20-second refresh. Account for declaredOwner conflicts, not only the parser-derived owner.

Allowed implementation files: scripts/progress-view.mjs, src/css/progress.css and tests/workflow/progress-roles.test.mjs, plus specified documentation/evidence. No new dependencies, runtime asset build or unrelated redesign. Keep CORE-001 changes untouched; if this is its Builder session, finish that task's current report/handoff before starting WF-002.

Record your identity/baseline, follow the ordered blueprint and run V1–V7, including real browser animation frames and reduced-motion checks. Source inspection and documentation checks passed; new UI/tests/browser results are NOT VERIFIED. Save evidence under ai-document/evidence/WF-002/round-1/, append implementation report and independent Architect prompt, then set READY_FOR_REVIEW with consistent checklist metadata. Do not accept your own work, mark DONE, commit, deploy or change the active database.
```

## User Acceptance (2026-09-21)

User explicitly requested continuous loop animations overriding the finite motion constraint, and directly accepted the Builder's work without requiring an Architect review ("ok done task này đi, k cần architect review nữa"). 

**Status**: DONE
