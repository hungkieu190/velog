export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
}

export function rolePresentation(focus) {
  if (!focus) return { role: null, motion: null, label: 'No active task' };

  if (focus.status === 'BLOCKED') {
    const valid = ['Architect', 'Builder', 'User'].includes(focus.declaredOwner);
    const motion = focus.declaredOwner === 'Architect' ? 'drawing' : (focus.declaredOwner === 'Builder' ? 'mining' : null);
    return { role: valid ? focus.declaredOwner : null, motion: valid ? motion : null, label: 'Blocked' };
  }

  if (focus.status === 'DONE') return { role: null, motion: null, label: 'Completed' };

  const table = {
    DRAFT: { role: 'Architect', label: 'Planning', motion: 'drawing' },
    READY: { role: 'Builder', label: 'Ready to start', motion: 'mining' },
    IN_PROGRESS: { role: 'Builder', label: 'Implementation in progress', motion: 'mining' },
    CHANGES_REQUESTED: { role: 'Builder', label: 'Fixes requested', motion: 'mining' },
    READY_FOR_REVIEW: { role: 'Architect', label: 'Review queued', motion: 'drawing' },
    AWAITING_MANUAL_ACCEPTANCE: { role: 'User', label: 'Awaiting your decision', motion: null },
  };

  const state = table[focus.status];
  if (!state) return { role: null, motion: null, label: 'State needs attention' };

  if (focus.declaredOwner && focus.declaredOwner !== state.role) {
    return { role: null, motion: null, label: 'State needs attention' };
  }

  return { role: state.role, motion: state.motion, label: state.label };
}

export function renderProgress(data) {
  const e = escapeHtml;
  const focus = data.currentFocus;
  const pres = rolePresentation(focus);

  function renderScene(owner) {
    const isActive = pres.role === owner;
    let classes = ['owner', owner.toLowerCase()];
    if (isActive) classes.push('active');
    if (isActive && pres.motion) classes.push(pres.motion);

    let svg = '';
    if (owner === 'Architect') {
      svg = `<svg viewBox="0 0 160 110" class="scene architect" aria-hidden="true" focusable="false">
        <g class="desk" stroke="currentColor" fill="none" stroke-width="2">
          <path d="M 20 80 L 140 80 L 120 40 L 40 40 Z"/>
          <path d="M 50 45 L 110 45 M 45 55 L 115 55 M 35 65 L 125 65 M 30 75 L 130 75" stroke-opacity="0.3"/>
          <path d="M 60 40 L 40 80 M 80 40 L 65 80 M 100 40 L 90 80" stroke-opacity="0.3"/>
        </g>
        <path class="drawn-line" d="M 50 60 L 110 60" stroke="#146f50" stroke-width="3" stroke-linecap="round"/>
        <g class="engineer" stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round">
          <circle cx="80" cy="20" r="10" fill="currentColor"/>
          <path d="M 70 30 C 60 40 50 50 50 60" />
          <path d="M 90 30 C 100 40 110 50 110 60" />
          <g class="arm" style="transform-origin: 95px 40px;">
            <path d="M 95 40 L 80 60"/>
            <path d="M 80 60 L 70 65" stroke="#146f50" stroke-width="3"/>
          </g>
        </g>
      </svg>`;
    } else if (owner === 'Builder') {
      svg = `<svg viewBox="0 0 160 110" class="scene builder" aria-hidden="true" focusable="false">
        <g class="rock" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M 10 90 L 40 70 L 60 90 Z"/>
          <path d="M 20 75 L 30 65 L 50 75 Z" stroke-opacity="0.5"/>
        </g>
        <g class="dust" fill="currentColor">
          <circle cx="35" cy="65" r="2" />
          <circle cx="45" cy="70" r="2" />
        </g>
        <g class="miner" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
          <path class="body" d="M 120 40 C 110 60 100 80 100 90 M 130 50 C 140 70 140 90 140 90"/>
          <path class="helmet" d="M 115 30 C 115 15 135 15 135 30 Z" fill="#cfe9de" stroke="currentColor"/>
          <g class="arm" style="transform-origin: 120px 45px;">
            <path d="M 120 45 L 80 50"/>
            <path d="M 70 40 C 60 45 60 55 70 60" stroke-width="3"/>
            <path d="M 90 30 L 60 70" stroke-width="3"/>
          </g>
        </g>
      </svg>`;
    } else {
      svg = `<svg viewBox="0 0 160 110" class="scene user" aria-hidden="true" focusable="false">
        <g class="person" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="80" cy="25" r="12" fill="currentColor"/>
          <path d="M 65 40 C 60 60 60 90 60 90 M 95 40 C 100 60 100 90 100 90"/>
          <path d="M 65 45 L 80 60 L 95 45"/>
        </g>
        <g class="checklist" fill="#f0e0c2" stroke="currentColor" stroke-width="2">
          <rect x="65" y="55" width="30" height="40" rx="2"/>
          <path d="M 70 65 L 85 65 M 70 75 L 85 75 M 70 85 L 80 85" stroke-opacity="0.5"/>
        </g>
      </svg>`;
    }

    return `<div class="${e(classes.join(' '))}">${svg}<span>${e(owner)}<small>${isActive ? e(pres.label) : ['Plan & review', 'Implement & verify', 'Decide & accept'][['Architect', 'Builder', 'User'].indexOf(owner)]}</small></span></div>`;
  }

  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="20"><title>VeLog · Project progress</title><link rel="stylesheet" href="/progress.css"></head><body>
  <header><a class="brand" href="/">VeLog<span> / Project progress</span></a><a href="/">Refresh ↻</a></header>
  <main><section class="focus"><p class="eyebrow">CURRENT FOCUS · ${e(focus?.id || 'No task')}</p><h1>${e(focus?.title || 'No active task')}</h1><p class="status">${e(focus?.status || 'No status')} <span> / ${e(focus?.round || 'No round')}</span></p><p class="next">${e(focus?.nextAction || 'Create the next task.')}</p>
  <div class="caption">Recorded task state &middot; updates every 20s</div>
  <div class="owners">${['Architect', 'Builder', 'User'].map(renderScene).join('')}</div></section>
  <aside><p class="eyebrow">CHECKLIST PROGRESS</p><div class="count">${data.summary.done}<span> / ${data.summary.total}</span></div><p>${data.summary.open} open items</p>${data.phases.map((phase) => `<div class="phase"><span>${e(phase.name)}</span><strong>${phase.done}/${phase.total}</strong><progress max="${phase.total || 1}" value="${phase.done}"></progress><ul class="phase-items">${phase.items.map(item => `<li class="${item.done ? 'done' : ''}">${e(item.text)}</li>`).join('')}</ul></div>`).join('')}</aside>
  ${data.issues.length ? `<section class="signals"><h2>Documentation signals</h2><ul>${data.issues.map((issue) => `<li>${e(issue)}</li>`).join('')}</ul></section>` : ''}
  <section class="wide"><h2>Task history</h2><div class="table-wrap"><table><thead><tr><th>Task</th><th>Status / owner</th><th>Latest round</th><th>Findings</th><th>Handoff prompts</th></tr></thead><tbody>${data.tasks.map((task) => `<tr><td><strong>${e(task.title)}</strong><small>${e(task.file)}</small></td><td>${e(task.status)}<small>${e(task.owner)}</small></td><td>${e(task.round)}</td><td>${e(task.findings.join(', ') || 'None recorded')}</td><td>${task.validPromptCount}/${task.promptCount} valid blocks</td></tr><tr><td colspan="5"><details><summary>Reports and latest handoff</summary><ul>${task.history.map((heading) => `<li>${e(heading)}</li>`).join('')}</ul><pre>${e(task.latestPrompt || 'No handoff recorded')}</pre></details></td></tr>`).join('')}</tbody></table></div></section>
  <section class="wide"><h2>Open work <span>Checklist order</span></h2><ol class="open-work">${data.openItems.map((item) => `<li>${e(item.text)}</li>`).join('')}</ol></section>
  </main><footer>Source updated ${e(data.lastUpdated)} · Refreshes every 20 seconds.<br>Read-only view of ${e(data.sources.join(', '))}. Task review and verification remain the acceptance authority.</footer></body></html>`;
}
