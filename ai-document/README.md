# VeLog documentation index

Authoritative technical and workflow documentation for VeLog.

## Current focus
- Task: [CUST-001-FE](tasks/CUST-001-FE-customer-presentation.md)
- Workstream: Frontend
- Status: BLOCKED
- Next actor: Backend Architect
- Exact next action: Fix HTML contract defect (missing type="text" on search input) in CustomerPage.php, then return to Frontend Developer.
- Delivery split: [PLAN-003](tasks/PLAN-003-backend-frontend-delivery.md).
- Lean workflow: [WF-005](tasks/WF-005-lean-architect-workflow.md) (DONE after independent review).
- Closed workflow removal: [WF-004](tasks/WF-004-json-handoff-controller.md) (DONE by direct user acceptance).

## Documentation map
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
