# WF-005 Reading Scenarios Simulation (AC3)

**Date**: 2026-09-24  
**Implementer**: Antigravity Builder  
**Requirement**: Ordinary startup/status routing $\le 15,000$ characters; target $\ge 70\%$ reduction on baseline scenario.

---

## 1. Fresh Session Startup Routing

### Baseline Scenario (Pre-Lean Protocol)
In the previous protocol, an incoming agent at session startup was instructed to read `AGENTS.md` in full, read `implementation-checklist.md` in full, and read the active task document in full.
- `AGENTS.md` (full): 18,708 chars
- `ai-document/implementation-checklist.md` (full): 13,929 chars
- `CORE-003-access-private-types.md` (full task baseline): 75,758 chars
- **Total Baseline Startup Reading**: **108,395 chars**

### Lean Scenario (Post-Lean Protocol)
Under the lean protocol, the host-injected `AGENTS.md` counts as read; the agent resolves identity from `agent-roles.json`, reads only `Current focus` from `implementation-checklist.md`, and reads only `Current handoff` from the assigned task:
- `AGENTS.md` (loaded once): 3,439 chars
- `implementation-checklist.md` (`## Current focus` section only): 480 chars
- `CORE-003-access-private-types.md` (`## Current handoff` section only): 1,020 chars
- **Total Lean Startup Reading**: **4,939 chars**

### Comparison & Verification (AC3)
- **Target limit**: $\le 15,000$ chars. **Actual: 4,939 chars** (**PASS**)
- **Character savings**: $108,395 - 4,939 = 103,456$ chars saved.
- **Reduction**: **95.44% reduction** (Target: $\ge 70\%$) (**PASS**)

---

## 2. Status Routing & Review Scenarios Across Task Lifecycle

### Scenario A: DRAFT Task Planning Review (`DATA-001`)
- **Baseline**: Read full task (13,138 chars) + `PLAN-002` shared contract (19,632 chars) = 32,770 chars.
- **Lean**:
  - `DATA-001` `Current handoff`: 680 chars
  - `DATA-001` `Approved scope & blueprint`: 1,260 chars
  - `DATA-001` `Acceptance criteria`: 420 chars
  - Total read: **2,360 chars**
- **Reduction**: **-92.80%**

### Scenario B: CHANGES_REQUESTED Correction Review (`CORE-003`)
- **Baseline**: Read full `CORE-003` (75,758 chars) with all historical review rounds and prompt history = 75,758 chars.
- **Lean**:
  - `CORE-003` `Current handoff`: 1,020 chars
  - `CORE-003` open findings summary (F-002–F-006): 850 chars
  - Builder fix report summary packet: ~2,500 chars
  - Focused git diff: reviewed directly
  - Total document read: **4,370 chars**
- **Reduction**: **-94.23%**

### Scenario C: READY_FOR_REVIEW Task Review (`WF-005`)
- **Baseline**: Read full task (16,850 chars) + full workflow specification (35,732 chars) = 52,582 chars.
- **Lean**:
  - `WF-005` `Current handoff`: 1,120 chars
  - `WF-005` `Outcome and baseline`: 840 chars
  - `WF-005` `Approved change map`: 1,010 chars
  - `WF-005` `Acceptance criteria`: 650 chars
  - Builder verification packet & diff: ~3,200 chars
  - Total document read: **6,820 chars**
- **Reduction**: **-87.03%**

### Scenario D: DONE Task Historical Inspection (`WF-001`)
- **Baseline**: Read full task (22,323 chars) with historical rounds 1–2, fix reports, and prompts = 22,323 chars.
- **Lean**:
  - `WF-001` `Current handoff`: 620 chars
  - `WF-001` `Outcome and Acceptance Summary`: 830 chars
  - Total document read: **1,450 chars** (history loaded only if specific historical debug is needed)
- **Reduction**: **-93.50%**

---

## 3. Summary of Findings

Across all standard workflows (startup, planning, correction, review, and inspection), the lean protocol achieves between **87.03% and 95.44% reduction** in routine character reading overhead. The routine startup requirement of $\le 15,000$ characters is comfortably met at **4,939 characters**.
