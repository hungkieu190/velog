export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
}

export function renderProgress(data) {
  const e = escapeHtml;
  const focus = data.currentFocus;
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="20"><title>VeLog · Project progress</title><link rel="stylesheet" href="/progress.css"></head><body>
  <header><a class="brand" href="/">VeLog<span> / Project progress</span></a><a href="/">Refresh ↻</a></header>
  <main><section class="focus"><p class="eyebrow">CURRENT FOCUS · ${e(focus?.id || 'No task')}</p><h1>${e(focus?.title || 'No active task')}</h1><p class="status">${e(focus?.status || 'No status')} <span> / ${e(focus?.round || 'No round')}</span></p><p class="next">${e(focus?.nextAction || 'Create the next task.')}</p>
  <div class="owners">${['Architect', 'Builder', 'User'].map((owner, index) => `<div class="owner ${owner.toLowerCase()} ${focus?.owner === owner ? 'active' : ''}"><b>${index + 1}</b><span>${owner}<small>${focus?.owner === owner ? 'Current owner' : ['Plan & review', 'Implement & verify', 'Decide & accept'][index]}</small></span></div>`).join('')}</div></section>
  <aside><p class="eyebrow">CHECKLIST PROGRESS</p><div class="count">${data.summary.done}<span> / ${data.summary.total}</span></div><p>${data.summary.open} open items</p>${data.phases.map((phase) => `<div class="phase"><span>${e(phase.name)}</span><strong>${phase.done}/${phase.total}</strong><progress max="${phase.total || 1}" value="${phase.done}"></progress><ul class="phase-items">${phase.items.map(item => `<li class="${item.done ? 'done' : ''}">${e(item.text)}</li>`).join('')}</ul></div>`).join('')}</aside>
  ${data.issues.length ? `<section class="signals"><h2>Documentation signals</h2><ul>${data.issues.map((issue) => `<li>${e(issue)}</li>`).join('')}</ul></section>` : ''}
  <section class="wide"><h2>Task history</h2><div class="table-wrap"><table><thead><tr><th>Task</th><th>Status / owner</th><th>Latest round</th><th>Findings</th><th>Handoff prompts</th></tr></thead><tbody>${data.tasks.map((task) => `<tr><td><strong>${e(task.title)}</strong><small>${e(task.file)}</small></td><td>${e(task.status)}<small>${e(task.owner)}</small></td><td>${e(task.round)}</td><td>${e(task.findings.join(', ') || 'None recorded')}</td><td>${task.validPromptCount}/${task.promptCount} valid blocks</td></tr><tr><td colspan="5"><details><summary>Reports and latest handoff</summary><ul>${task.history.map((heading) => `<li>${e(heading)}</li>`).join('')}</ul><pre>${e(task.latestPrompt || 'No handoff recorded')}</pre></details></td></tr>`).join('')}</tbody></table></div></section>
  <section class="wide"><h2>Open work <span>Checklist order</span></h2><ol class="open-work">${data.openItems.map((item) => `<li>${e(item.text)}</li>`).join('')}</ol></section>
  </main><footer>Source updated ${e(data.lastUpdated)} · Refreshes every 20 seconds.<br>Read-only view of ${e(data.sources.join(', '))}. Task review and verification remain the acceptance authority.</footer></body></html>`;
}
