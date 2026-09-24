/** Contributor review probes; disposable fake executables, never live agents. */
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { randomUUID } from 'node:crypto';
import { spawn } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';
import { fixture, cleanup, config, options, documents, sessionId, write } from '../../../../tests/workflow/handoff-fixtures.mjs';
import { dispatch as codex } from '../../../../scripts/agent-adapters/codex.mjs';
import { dispatch as antigravity } from '../../../../scripts/agent-adapters/antigravity.mjs';
import { publishHandoff } from '../../../../scripts/publish-handoff.mjs';
import { ControllerState, pollCycle } from '../../../../scripts/handoff-controller.mjs';
const out = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(out, '../../../..');
const observations = [];
const roots = [];
async function make() { const root = await fixture(); roots.push(root); return root; }
async function alive(pid) {
  try { const stat = await fs.readFile(`/proc/${pid}/stat`, 'utf8'); return !['Z', 'X'].includes(stat.slice(stat.lastIndexOf(')') + 2).split(' ')[0]); }
  catch { return false; }
}
async function fake(root, code) {
  const executable = path.join(root, 'fake-cli.mjs');
  await fs.writeFile(executable, `#!${process.execPath}\n${code}\n`, { mode: 0o700 });
  return executable;
}
async function adapterCase(name, variant = {}) {
  const root = await make();
  const handoff = { id: randomUUID(), task_id: 'TEST-001', task_file: 'ai-document/tasks/TEST-001-fixture.md', task_status: 'READY', intent: 'work', from_role: 'architect', plan_revision: 1, round: 1 };
  const result = { receipt_id: handoff.id, task_id: handoff.task_id, intake: 'PASS', outcome: 'needs_user', new_receipt_id: null, ...variant.result };
  if (variant.missingResult) delete result.new_receipt_id;
  const events = [];
  if (!variant.noIdentity) events.push(JSON.stringify({ type: 'thread.started', thread_id: variant.wrongIdentity ? randomUUID() : sessionId }));
  if (variant.badLine) events.push('NOT JSON');
  events.push(JSON.stringify({ type: 'turn.completed' }));
  const executable = await fake(root, `import fs from 'node:fs';
import { spawn } from 'node:child_process';
let input='';for await(const chunk of process.stdin)input+=chunk;
fs.writeFileSync('input.txt',input);
${variant.descendant ? "const child=spawn(process.execPath,['-e','setInterval(()=>{},1000)'],{stdio:'ignore'});fs.writeFileSync('descendant.pid',String(child.pid));child.unref();" : ''}
fs.writeFileSync(process.argv[process.argv.indexOf('-o')+1],${JSON.stringify(JSON.stringify(result))});
process.stdout.write(${JSON.stringify(events.join('\n') + '\n')});`);
  let persisted = false;
  let spawned = false;
  const response = await (variant.antigravity ? antigravity : codex)({ projectRoot: root, config: { executable, sessionId }, handoff, prompt: 'EXACT_APPROVED_PROMPT_SENTINEL', runId: randomUUID(), deadline: 2000,
    onSpawn: async () => { spawned = true; await delay(150); persisted = true; } });
  const row = { name, success: response.success, cleanupVerified: response.cleanupVerified ?? null, error: response.error ?? null, spawned, persistenceAwaited: persisted, exactPromptDelivered: (await fs.readFile(path.join(root, 'input.txt'), 'utf8')).includes('EXACT_APPROVED_PROMPT_SENTINEL') };
  if (variant.descendant) {
    const pid = Number(await fs.readFile(path.join(root, 'descendant.pid'), 'utf8'));
    row.descendantPid = pid;
    row.descendantAliveOnSuccess = await alive(pid);
    if (row.descendantAliveOnSuccess) process.kill(pid, 'SIGKILL');
    for (let i = 0; i < 50 && await alive(pid); i++) await delay(10);
    row.harnessStoppedDescendant = !(await alive(pid));
  }
  await delay(160);
  await fs.cp(path.join(root, '.cache/handoff/runs'), path.join(out, 'adapter-logs', name), { recursive: true });
  observations.push(row);
}
try {
  await adapterCase('valid-control');
  await adapterCase('wrong-identity-control', { wrongIdentity: true });
  await adapterCase('missing-identity', { noIdentity: true });
  await adapterCase('malformed-jsonl', { badLine: true });
  await adapterCase('invalid-enums', { result: { intake: 'INVALID', outcome: 'INVALID' } });
  await adapterCase('missing-required-nullable-field', { missingResult: true });
  await adapterCase('nonexistent-successor', { result: { outcome: 'handoff', new_receipt_id: randomUUID() } });
  await adapterCase('live-descendant-after-success', { descendant: true });
  await adapterCase('unverified-antigravity-executable', { antigravity: true });
  // A rejected receipt A->B is corrected by routing metadata back B->A.
  const root = await make();
  const first = await publishHandoff(options(root));
  await fs.appendFile(path.join(root, 'ai-document/tasks/TEST-001-fixture.md'), '\nChanged after publication.\n');
  const state = Object.assign(new ControllerState(), { mode: 'dispatch' });
  await pollCycle(root, config(), state, { builder: { dispatch: async () => { throw new Error('Must not launch invalid intake'); } } });
  await documents(root, { status: 'READY', intent: 'correct_metadata', actor: 'Architect', recipient: 'Builder' });
  const correction = await publishHandoff(options(root, { intent: 'correct_metadata', fromRole: 'builder', toRole: 'architect', rejectedReceiptId: first.id }));
  let launches = 0;
  await pollCycle(root, config(), state, { architect: { dispatch: async () => { launches++; return { success: true, cleanupVerified: true, result: { intake: 'PASS', outcome: 'needs_user', new_receipt_id: null } }; } } });
  await documents(root, { status: 'READY', actor: 'Builder', recipient: 'Builder' });
  let resumed;
  try { await publishHandoff(options(root)); resumed = { published: true }; }
  catch (error) { resumed = { published: false, error: error.message }; }
  observations.push({ name: 'correction-run-intake-ledger', correction: correction.id, launches, blocked: state.blocked, resumed, ledger: JSON.parse(await fs.readFile(path.join(root, '.cache/handoff/ledger.json'), 'utf8')).map(e => ({ status: e.status, intake: e.intake, intent: e.intent })) });
} finally {
  for (const root of roots) await cleanup(root);
  await fs.writeFile(path.join(out, 'probe-results.json'), JSON.stringify({ observations, cleanup: { fixtureDirectoriesRemoved: roots.length } }, null, 2) + '\n');
  process.stdout.write(JSON.stringify(observations, null, 2) + '\n');
}
