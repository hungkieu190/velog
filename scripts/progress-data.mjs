import fs from 'node:fs/promises';
import path from 'node:path';

export const owners = {
  DRAFT: 'Architect', READY: 'Builder', IN_PROGRESS: 'Builder', BLOCKED: null,
  READY_FOR_REVIEW: 'Architect', CHANGES_REQUESTED: 'Builder', AWAITING_MANUAL_ACCEPTANCE: 'User', DONE: 'Architect',
};
const handedOff = new Set(['READY', 'READY_FOR_REVIEW', 'CHANGES_REQUESTED', 'AWAITING_MANUAL_ACCEPTANCE', 'DONE']);

function section(markdown, heading) {
  const start = markdown.indexOf(`## ${heading}\n`);
  if (start < 0) return '';
  return markdown.slice(start + heading.length + 4).split(/\n## /)[0];
}

function field(markdown, key) {
  return markdown.match(new RegExp(`^- ${key}:\\s*(.*)$`, 'm'))?.[1]?.trim() || '';
}

export function parseTask(markdown, file) {
  const current = section(markdown, 'Current handoff');
  const status = field(current, 'Status');
  const declaredOwner = field(current, 'Next actor') || field(current, 'Next actor and exact next action').split(/[;:.]/)[0];
  const owner = owners[status] || declaredOwner || 'Unassigned';
  const promptSections = [...markdown.matchAll(/^### Chat handoff prompt\s*\n\s*```text\n([\s\S]*?)```/gm)];
  const latestPrompt = promptSections.at(-1)?.[1]?.trim() || '';
  const issues = [];
  if (!(status in owners)) issues.push(`Unknown status: ${status || '(missing)'}`);
  if (declaredOwner && owners[status] && declaredOwner !== owner) issues.push(`Status implies ${owner}; Next actor says ${declaredOwner}`);
  if (handedOff.has(status) && (!latestPrompt || !latestPrompt.includes(status))) issues.push('Missing or stale current handoff prompt');
  return {
    id: markdown.match(/^# ([^:]+):/m)?.[1] || path.basename(file, '.md'),
    title: markdown.match(/^# (.+)$/m)?.[1] || file, file, status, owner, declaredOwner,
    round: field(current, 'Latest round') || field(current, 'Latest implementation/review round'),
    nextAction: field(current, 'Next actor and exact next action'),
    findings: [...new Set(markdown.match(/\bF-\d{3,}\b/g) || [])],
    promptCount: (markdown.match(/^### Chat handoff prompt/gm) || []).length,
    validPromptCount: promptSections.length, latestPrompt,
    history: [...markdown.matchAll(/^## ((?:Planning report|Approved assignment|Implementation report|Fix report|Review|Final acceptance)[^\n]*)/gm)].map((match) => match[1]),
    issues,
  };
}

export function parseChecklist(markdown) {
  let phase = 'Other';
  const items = [];
  for (const line of markdown.split('\n')) {
    if (line.startsWith('## ')) phase = line.slice(3);
    const match = line.match(/^- \[([ xX])\] (.*)$/);
    if (match) items.push({ done: match[1].toLowerCase() === 'x', text: match[2], phase, taskId: match[2].match(/\b[A-Z]+-\d+\b/)?.[0], status: match[2].match(/Status:\s*([A-Z_]+)/)?.[1] });
  }
  const phases = [...new Set(items.map((item) => item.phase))].map((name) => {
    const group = items.filter((item) => item.phase === name);
    return { name, total: group.length, done: group.filter((item) => item.done).length };
  });
  const current = section(markdown, 'Current focus');
  return { items, phases, focusId: field(current, 'Task'), focusStatus: field(current, 'Status'), nextAction: field(current, 'Exact next action') };
}

export async function readProgress(projectRoot) {
  const checklistFile = path.join(projectRoot, 'ai-document/implementation-checklist.md');
  const checklist = parseChecklist(await fs.readFile(checklistFile, 'utf8'));
  const directory = path.join(projectRoot, 'ai-document/tasks');
  const names = (await fs.readdir(directory)).filter((file) => file.endsWith('.md')).sort();
  const tasks = [];
  let modified = (await fs.stat(checklistFile)).mtimeMs;
  for (const name of names) {
    const file = path.join(directory, name);
    const stat = await fs.lstat(file);
    if (!stat.isFile() || stat.isSymbolicLink()) throw new Error('Task source must be a regular file');
    modified = Math.max(modified, stat.mtimeMs);
    tasks.push(parseTask(await fs.readFile(file, 'utf8'), `ai-document/tasks/${name}`));
  }
  const issues = [];
  for (const item of checklist.items) {
    const task = tasks.find((entry) => entry.id === item.taskId);
    if (!task) { issues.push(`Checklist references missing task: ${item.taskId || item.text}`); continue; }
    if (item.status && item.status !== task.status) issues.push(`${task.id}: checklist ${item.status}, task ${task.status}`);
    if (item.done && task.status !== 'DONE') issues.push(`${task.id}: checkbox completed before acceptance`);
    if (!item.done && task.status === 'DONE') issues.push(`${task.id}: DONE task has unchecked checklist item`);
  }
  const focus = tasks.find((task) => task.id === checklist.focusId) || tasks.find((task) => task.status !== 'DONE') || tasks.at(-1) || null;
  if (focus && checklist.focusStatus && checklist.focusStatus !== focus.status) issues.push('Current focus status contradicts task status');
  const done = checklist.items.filter((item) => item.done).length;
  return {
    currentFocus: focus ? { ...focus, nextAction: focus.nextAction || checklist.nextAction } : null,
    summary: { total: checklist.items.length, done, open: checklist.items.length - done },
    phases: checklist.phases, tasks, openItems: checklist.items.filter((item) => !item.done),
    issues: [...issues, ...tasks.flatMap((task) => task.issues.map((issue) => `${task.id}: ${issue}`))],
    lastUpdated: new Date(modified).toISOString(), readAt: new Date().toISOString(),
    sources: ['ai-document/implementation-checklist.md', ...tasks.map((task) => task.file)],
  };
}
