# VeLog documentation index

Authoritative technical and workflow documentation for VeLog.

## Current focus
- Task: [CUST-001-UAT](tasks/CUST-001-UAT-manual-test.md), manual acceptance
- Workstream: Tester
- Status: AWAITING_MANUAL_ACCEPTANCE
- Next actor: Tester
- Exact next action: Execute T01–T20 on prepared LocalWP, retest T02, and record PASS/FAIL/BLOCKED with environment/tool details.
- [CUST-001-FE](tasks/CUST-001-FE-customer-presentation.md) round 3 review 2: APPROVED for code only; FE-R3-01 through FE-R3-05 resolved. FE, [parent CUST-001](tasks/CUST-001-customer-records.md) and UAT are AWAITING_MANUAL_ACCEPTANCE; Next actor Tester. [BE](tasks/CUST-001-BE-customer-backend.md) remains DONE as SELF_REVIEWED_BACKEND technical checkpoint only.
- Evidence: [LocalWP preparation](evidence/CUST-001-UAT/preparation-2026-10-15/report.md), [FE approval](evidence/CUST-001-FE/frontend-round-3/architect-review-r2.md). Two staff accounts, 51 pagination customers, customer 64 linked to active vehicle 65 verified. Passwords are outside the repository/public root; see UAT for the private path. Existing user/domain data preserved; no WordPress mail attempts. Browser/manual acceptance is not claimed; historical T01 PASS and T02 FAIL pending retest remain unchanged.
- G-10 retains independent private customer records; CUST-002 is BLOCKED/retired without acceptance. Staff authentication remains native WordPress. VEH-001 remains BLOCKED for its separate readiness revalidation.

## Documentation map
- [admin-design-system.md](admin-design-system.md): Native WordPress admin design baseline, component patterns, state contracts, and design review checklist.
- [wordpress-plugin-admin-ui skill](../.agents/skills/wordpress-plugin-admin-ui/SKILL.md): Project-local reference bundle supplied by the Product Owner.
- [local-environment.md](local-environment.md): Current LocalWP development and test URLs and local credential location.
- [AGENTS.md](../AGENTS.md): Main agent entry point, role assignment, and security essentials.
- [implementation-checklist.md](implementation-checklist.md): Project priority, phases, and acceptance tracking.
- [backend-frontend-workflow.md](backend-frontend-workflow.md): Current ownership, self-review, frontend review, task template, and handoff rules.
- [architect-builder-workflow.md](architect-builder-workflow.md): Historical compatibility notice only.
- [architecture.md](architecture.md): System architecture, namespaces, and security layers.
- [build-and-release.md](build-and-release.md): Asset build pipeline, dependencies, and packaging.
- [agent-roles.json](agent-roles.json): Static role assignment (Codex = Backend Architect, Antigravity = Frontend Developer, User = Tester / Product Owner).
- [product-plan.md](product-plan.md): Product roadmap and MVP boundaries.

## Rules and domain specifications
- Coding constraints: `rules/`
- Tasks: `ai-document/tasks/`
- Historical archives: `ai-document/history/`
- Evidence: `ai-document/evidence/`
