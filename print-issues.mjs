import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { readProgress, parseTask } from './scripts/progress-data.mjs';

async function run() {
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'velog-progress-'));
  try {
    await fs.mkdir(path.join(directory, 'ai-document/tasks'), { recursive: true });
    await fs.writeFile(path.join(directory, 'ai-document/implementation-checklist.md'), '# Checklist\n## Current focus\n- Task: WF-999\n- Status: CHANGES_REQUESTED\n- Exact next action: Builder\n## Phase 1\n- [ ] WF-999: Example. Status: CHANGES_REQUESTED.\n');
    const markdown = '# WF-999: <script>alert(1)</script>\n## Current handoff\n- Status: DONE\n- Next actor: Builder\n- Latest round: Review 3\n- Next actor and exact next action: Review F-003\n### Chat handoff prompt\n```\ninvalid bare block\n```\n### Chat handoff prompt\n```javascript\nStatus: IN_PROGRESS\n```\n### Chat handoff prompt\n```text\nStatus: IN_PROGRESS\n```';
    
    await fs.writeFile(path.join(directory, 'ai-document/tasks/task.md'), markdown);
    const data = await readProgress(directory);
    console.log(data.issues);
  } finally {
    await fs.rm(directory, { recursive: true, force: true });
  }
}
run();
