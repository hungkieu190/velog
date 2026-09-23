import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
export const taskPath = 'ai-document/tasks/TEST-001-fixture.md';
export const sessionId = '11111111-1111-4111-8111-111111111111';
export const config = () => ({ enabled: true, activationId: randomUUID(), priorityTask: 'TEST-001', adapters: { codex: { executable: process.execPath, sessionId }, antigravity: { executable: process.execPath, sessionId } } });
export async function fixture() {
  const root = await fs.mkdtemp(path.join(os.tmpdir(), 'wf004-r3-'));
  await fs.mkdir(path.join(root, 'ai-document/tasks'), { recursive: true });
  await fs.mkdir(path.join(root, '.cache/handoff'), { recursive: true });
  await write(root, 'ai-document/agent-roles.json', JSON.stringify({ schema_version: 1, assignments: { codex: { role: 'architect' }, antigravity: { role: 'builder' } } }));
  await write(root, 'ai-document/handoff-signal.json', '{"schema_version":1,"handoff":null}');
  await documents(root);
  return root;
}
export const write = (root, file, value) => fs.writeFile(path.join(root, file), value);
export async function documents(root, { status = 'READY', intent = 'work', actor = status === 'READY_FOR_REVIEW' ? 'Architect' : 'Builder', recipient = actor } = {}) {
  const prompt = `Status: ${status}\nRecipient: ${actor}\nIntent: ${intent}\nRead the fixture and execute only its approved work.`;
  await write(root, taskPath, `# TEST-001: Fixture\n\n## Current handoff\n- Status: ${status}\n- Plan revision: 1\n- Implementation round: 1\n- Latest round: Fixture 1\n- Next actor: ${actor}\n- Handoff state: ${intent === 'correct_metadata' ? 'correction_required' : 'published'}\n- Handoff actor: ${actor}\n- Handoff recipient: ${recipient}\n- Handoff intent: ${intent}\n- Blueprint readiness: PASS\n- Architect session reference: independent fixture architect\n- Builder session reference: separate fixture builder\n- Implementation contributors and reviewer independence check: fixture Builder; separate reviewer required\n- User approval reference and approved scope: disposable fixture only\n- Latest report: fixture report below\n- Evidence: ai-document/evidence.md\n- Next actor and exact next action: Follow fixture.\n\n### Chat handoff prompt\n\n\`\`\`text\n${prompt}\n\`\`\`\n`);
  await write(root, 'ai-document/evidence.md', 'Fixture evidence. Live CLI NOT VERIFIED.\n');
  await write(root, 'ai-document/implementation-checklist.md', `# Checklist\n\n## Current focus\n- Task: TEST-001\n- Status: ${status}\n- Next actor: ${actor}\n\n## Criteria\n- [ ] TEST-001 / AC1: Fixture. Status: ${status}.\n`);
  await write(root, 'ai-document/README.md', `# Fixture\n\n## Current focus\n- Task: TEST-001\n- Status: ${status}\n- Next actor: ${actor}\n`);
  return prompt;
}
export function options(root, extra = {}) { return { projectRoot: root, taskId: 'TEST-001', taskFile: taskPath, planRevision: 1, round: 1, taskStatus: 'READY', intent: 'work', fromRole: 'architect', toRole: 'builder', senderSessionId: sessionId, ...extra }; }
export const cleanup = root => fs.rm(root, { recursive: true, force: true });
