import fs from 'fs';
import path from 'path';

let content = fs.readFileSync('tests/workflow/handoff-controller.test.mjs', 'utf8');

// Replace { antigravity: { executable: 'antigravity', sessionId: 's1' } }
// with { antigravity: { executable: '/bin/antigravity', sessionId: '00000000-0000-0000-0000-000000000000' } }
content = content.replace(/executable: 'antigravity'/g, "executable: '/bin/antigravity'");
content = content.replace(/sessionId: 's1'/g, "sessionId: '00000000-0000-0000-0000-000000000000'");

// Same for codex
content = content.replace(/executable: 'codex'/g, "executable: '/bin/codex'");

// Fix the random UUID for activationId in all config definitions to be explicit valid UUIDs
content = content.replace(/activationId: randomUUID\(\)/g, "activationId: '11111111-1111-1111-1111-111111111111'");

// Fix the manual ledger entry's contentHash
content = content.replace(/contentHash: 'abc',/g, "contentHash: '" + 'a'.repeat(64) + "',");
content = content.replace(/intent: 'work'/g, "intent: 'work',\n      acknowledged: true,\n      cleanupVerified: false,\n      intake: 'PASS'");

fs.writeFileSync('tests/workflow/handoff-controller.test.mjs', content);
