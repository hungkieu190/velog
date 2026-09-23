import fs from 'node:fs/promises';
import path from 'node:path';

export const owners = {
  DRAFT: 'Architect', READY: 'Builder', IN_PROGRESS: 'Builder', BLOCKED: null,
  READY_FOR_REVIEW: 'Architect', CHANGES_REQUESTED: 'Builder', AWAITING_MANUAL_ACCEPTANCE: 'User', DONE: 'Architect',
};
const handedOff = new Set(['READY', 'READY_FOR_REVIEW', 'CHANGES_REQUESTED', 'AWAITING_MANUAL_ACCEPTANCE', 'DONE']);

export function normalizeRole(role) {
  if (!role) return null;
  const match = role.trim().match(/^(Architect|Builder|User)(?:\s+\([^)]*\S[^)]*\))?$/);
  return match ? match[1] : null;
}

function section(markdown, heading) {
  const start = markdown.indexOf(`## ${heading}\n`);
  if (start < 0) return '';
  return markdown.slice(start + heading.length + 4).split(/\n## /)[0];
}

function field(markdown, key) {
  return markdown.match(new RegExp(`^- ${key}:\\s*(.*)$`, 'm'))?.[1]?.trim() || '';
}

function promptSections(markdown) {
  return [...markdown.matchAll(/^### Chat handoff prompt[^\n]*(?:\n|$)/gm)].map((match, index, matches) => {
    const end = matches[index + 1]?.index ?? markdown.length;
    const text = markdown.slice(match.index, end).split(/\n(?=##? |### )/)[0];
    const newline = text.indexOf('\n');
    return { heading: newline < 0 ? text : text.slice(0, newline), content: newline < 0 ? '' : text.slice(newline + 1) };
  });
}

function promptBlock(section) {
  if (section.heading !== '### Chat handoff prompt') return null;
  const block = section.content.match(/^\s*```(?:text)?\n([\s\S]*?)^```[ \t]*(?:\n|$)/m);
  // Remove only the fence's separating newline; preserve prompt whitespace bytes.
  return block ? block[1].replace(/\n$/, '') : null;
}

export function extractLatestPrompt(markdown) {
  const sections = promptSections(markdown);
  if (!sections.length) return { prompt: '', valid: false, error: 'No handoff prompt found' };
  const latest = sections.at(-1);
  const prompt = promptBlock(latest);
  if (prompt === null || !prompt.trim()) return { prompt: '', valid: false, error: 'Malformed or empty newest handoff prompt heading/fence' };
  return { prompt, valid: true, error: null };
}

export function parseTask(markdown, file) {
  const current = section(markdown, 'Current handoff');
  const status = field(current, 'Status');
  const declaredOwnerRaw = field(current, 'Next actor');
  const declaredOwner = normalizeRole(declaredOwnerRaw);
  const owner = owners[status] || declaredOwner || 'Unassigned';
  const rawSections = [...markdown.matchAll(/(?:^|\n)### Chat handoff prompt/g)];

  const extraction = extractLatestPrompt(markdown);
  const latestPrompt = extraction.valid ? extraction.prompt : '';
  const validPromptCount = promptSections(markdown).filter(entry => promptBlock(entry) !== null).length;

  const issues = [];
  if (!extraction.valid && extraction.error !== 'No handoff prompt found' && handedOff.has(status)) {
    issues.push(`Prompt extraction error: ${extraction.error}`);
  }
  if (!(status in owners)) issues.push(`Unknown status: ${status || '(missing)'}`);
  if (declaredOwnerRaw && !declaredOwner) issues.push(`Invalid Next actor: ${declaredOwnerRaw}`);
  if (declaredOwner && owners[status] && declaredOwner !== owner) issues.push(`Status implies ${owner}; Next actor says ${declaredOwnerRaw}`);

  if (handedOff.has(status)) {
    let unverified = true;
    if (latestPrompt) {
      const firstLine = latestPrompt.split('\n').find(line => line.trim().length > 0) || '';
      const statusMatch = firstLine.match(/^(?:Status:|Current status is)\s*([A-Z_]+)\b/);
      if (statusMatch) {
        unverified = false;
        const parsedStatus = statusMatch[1];
        if (parsedStatus !== status) {
          if (parsedStatus in owners) {
             issues.push(`Prompt declares ${parsedStatus} but task is ${status}`);
          } else {
             unverified = true; // Unknown token
          }
        }
      }
    }
    if (unverified) issues.push('Missing or stale current handoff prompt');
  }
  return {
    id: markdown.match(/^# ([^:]+):/m)?.[1] || path.basename(file, '.md'),
    title: markdown.match(/^# (.+)$/m)?.[1] || file, file, status, owner, declaredOwner, declaredOwnerRaw,
    round: field(current, 'Latest round') || field(current, 'Latest implementation/review round'),
    nextAction: field(current, 'Next actor and exact next action'),
    findings: [...new Set(markdown.match(/\bF-\d{3,}\b/g) || [])],
    promptCount: rawSections.length,
    validPromptCount: validPromptCount, latestPrompt,
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
    return { name, total: group.length, done: group.filter((item) => item.done).length, items: group };
  });
  const current = section(markdown, 'Current focus');
  return { items, phases, focusId: field(current, 'Task'), focusStatus: field(current, 'Status'), nextAction: field(current, 'Exact next action'), nextActorRaw: field(current, 'Next actor') };
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
  if (focus && checklist.nextActorRaw) {
    const focusChecklistNextActor = normalizeRole(checklist.nextActorRaw);
    if (!focusChecklistNextActor) {
      issues.push(`Checklist next actor is invalid: ${checklist.nextActorRaw}`);
    } else {
      let expectedRole;
      let unresolvedReason = null;
      if (focus.declaredOwnerRaw) {
        if (focus.declaredOwner) {
          expectedRole = focus.declaredOwner;
        } else {
          unresolvedReason = `task declaration is invalid (${focus.declaredOwnerRaw})`;
        }
      } else {
        if (focus.status === 'BLOCKED') {
          unresolvedReason = 'task is BLOCKED and has no explicit declaration';
        } else if (focus.status === 'DONE') {
          unresolvedReason = 'task is DONE and requires no next actor';
        } else {
          expectedRole = owners[focus.status];
        }
      }

      if (unresolvedReason) {
        issues.push(`Checklist specifies ${checklist.nextActorRaw} but ${unresolvedReason}`);
      } else if (expectedRole && focusChecklistNextActor !== expectedRole) {
        issues.push(`Checklist next actor says ${checklist.nextActorRaw} but task expects ${expectedRole}`);
      }
    }
  }
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
