import { normalizeRole } from './progress-data.mjs';

export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
}

export function rolePresentation(focus) {
  if (!focus) return { role: null, motion: null, label: 'No active task' };

  const declared = focus.declaredOwner;
  if (focus.status === 'DONE') return { role: null, motion: null, label: 'Completed' };

  const labels = {
    DRAFT: 'Planning', READY: 'Ready to start', IN_PROGRESS: 'Implementation in progress',
    CHANGES_REQUESTED: 'Fixes requested', READY_FOR_REVIEW: 'Review queued',
    AWAITING_MANUAL_ACCEPTANCE: 'Manual test required', BLOCKED: 'Blocked',
  };

  if (!labels[focus.status] || !declared) {
    return { role: null, motion: null, label: 'State needs attention' };
  }

  const motion = declared === 'Backend Architect' ? 'drawing' : (declared === 'Frontend Developer' ? 'coding' : (declared === 'Tester' ? 'testing' : null));
  return { role: declared, motion, label: labels[focus.status] };
}

export function renderProgress(data) {
  const e = escapeHtml;
  const focus = data.currentFocus;
  const pres = rolePresentation(focus);

  function renderScene(owner) {
    const isActive = pres.role === owner;
    let classes = ['owner', owner.toLowerCase().replaceAll(' ', '-')];
    if (isActive) classes.push('active');
    if (isActive && pres.motion) classes.push(pres.motion);

    let svg = '';
    if (owner === 'Backend Architect') {
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
    } else if (owner === 'Frontend Developer') {
      svg = `<svg viewBox="0 0 160 110" class="scene frontend" aria-hidden="true" focusable="false">
        <g class="display" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
          <rect x="18" y="18" width="92" height="62" rx="4"/>
          <path d="M 50 92 L 78 92 M 64 80 L 64 92"/>
          <path class="code-line code-line-one" d="M 34 38 L 52 38" stroke="#146f50" stroke-width="3"/>
          <path class="code-line code-line-two" d="M 34 50 L 82 50" stroke="#146f50" stroke-width="3"/>
          <path class="code-line code-line-three" d="M 34 62 L 66 62" stroke="#146f50" stroke-width="3"/>
        </g>
        <g class="developer" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
          <circle cx="132" cy="30" r="10" fill="currentColor"/>
          <path d="M 124 42 C 114 58 116 78 120 92 M 140 42 C 148 58 148 78 146 92"/>
          <g class="typing-hand" style="transform-origin: 120px 55px;">
            <path d="M 124 48 L 104 66 L 88 70"/>
          </g>
        </g>
      </svg>`;
    } else {
      svg = `<svg viewBox="0 0 160 110" class="scene tester" aria-hidden="true" focusable="false">
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

    const descriptions = {
      'Backend Architect': 'Architecture, backend & review',
      'Frontend Developer': 'Frontend implementation',
      Tester: 'Manual test & accept',
    };
    return `<div class="${e(classes.join(' '))}">${svg}<span>${e(owner)}<small>${isActive ? e(pres.label) : descriptions[owner]}</small></span></div>`;
  }

  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VeLog · Project progress</title><link rel="stylesheet" href="/progress.css"><script type="module" src="/progress-client.mjs"></script></head><body>
  <header><a class="brand" href="/">VeLog<span> / Project progress</span></a><div class="header-actions"><button id="enable-sound" type="button">Enable sound</button><a href="/">Refresh ↻</a></div></header>
  <div id="handoff-alert" class="handoff-alert" role="status" aria-live="polite" hidden><strong id="handoff-alert-text"></strong><button id="dismiss-alert" type="button">Dismiss</button></div>
  <main><section class="focus"><p class="eyebrow">CURRENT FOCUS · ${e(focus?.id || 'No task')}</p><h1>${e(focus?.title || 'No active task')}</h1><p class="status">${e(focus?.status || 'No status')} <span> / ${e(focus?.workstream || 'Unspecified')} / ${e(focus?.owner || 'Unassigned')} / ${e(focus?.round || 'No round')}</span></p><p class="next">${e(focus?.nextAction || 'Create the next task.')}</p>
  <div class="caption">Recorded task state &middot; checks every 20s</div>
  ${pres.role ? '' : `<div class="attention-reason">${e(pres.label)}</div>`}
  <div class="owners">${['Backend Architect', 'Frontend Developer', 'Tester'].map(renderScene).join('')}</div></section>
  <aside><p class="eyebrow">CHECKLIST PROGRESS</p><div class="count">${data.summary.done}<span> / ${data.summary.total}</span></div><p>${data.summary.open} open items</p>${data.phases.map((phase) => `<div class="phase"><span>${e(phase.name)}</span><strong>${phase.done}/${phase.total}</strong><progress max="${phase.total || 1}" value="${phase.done}"></progress><ul class="phase-items">${phase.items.map(item => `<li class="${item.done ? 'done' : ''}">${e(item.text)}</li>`).join('')}</ul></div>`).join('')}</aside>
  ${data.issues.length ? `<section class="signals"><h2>Documentation signals</h2><ul>${data.issues.map((issue) => `<li>${e(issue)}</li>`).join('')}</ul></section>` : ''}
  <section class="wide"><h2>Task history</h2><div class="table-wrap"><table><thead><tr><th>Task</th><th>Status / owner</th><th>Latest round</th><th>Findings</th><th>Handoff prompts</th></tr></thead><tbody>${data.tasks.map((task) => `<tr><td><strong>${e(task.title)}</strong><small>${e(task.file)}</small></td><td>${e(task.status)}<small>${e(task.owner)}</small></td><td>${e(task.round)}</td><td>${e(task.findings.join(', ') || 'None recorded')}</td><td>${task.validPromptCount}/${task.promptCount} valid blocks</td></tr><tr><td colspan="5"><details><summary>Reports and latest handoff</summary><ul>${task.history.map((heading) => `<li>${e(heading)}</li>`).join('')}</ul><pre>${e(task.latestPrompt || 'No handoff recorded')}</pre></details></td></tr>`).join('')}</tbody></table></div></section>
  <section class="wide"><h2>Open work <span>Checklist order</span></h2><ol class="open-work">${data.openItems.map((item) => `<li>${e(item.text)}</li>`).join('')}</ol></section>
  </main><footer>Source updated ${e(data.lastUpdated)} · Checks every 20 seconds.<br>Read-only view of ${e(data.sources.join(', '))}. Task review and verification remain the acceptance authority.</footer></body></html>`;
}
