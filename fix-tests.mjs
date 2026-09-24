import fs from 'fs';

let content = fs.readFileSync('tests/workflow/handoff-controller.test.mjs', 'utf8');

// Replace single-line configs
content = content.replace(/const config = \{ enabled: true, priorityTask: 'TEST-001', adapters: \{\} \};/g, "const config = { enabled: true, priorityTask: 'TEST-001', adapters: {}, activationId: randomUUID() };");

content = content.replace(/const config = \{ enabled: true \};/g, "const config = { enabled: true, activationId: randomUUID() };");

// Replace multi-line configs
content = content.replace(/const config = \{\n      enabled: true,\n      priorityTask: 'TEST-001',\n      adapters: \{ antigravity: \{ executable: 'antigravity', sessionId: 's1' \} \},\n    \};/g, "const config = {\n      enabled: true,\n      priorityTask: 'TEST-001',\n      adapters: { antigravity: { executable: 'antigravity', sessionId: 's1' } },\n      activationId: randomUUID(),\n    };");

// Fix the manual ledger entry around line 302
content = content.replace(/status: 'claimed',\n      pid: null,\n      startedAt: new Date\(\)\.toISOString\(\),\n    \}\];/, "status: 'claimed',\n      pid: null,\n      startedAt: new Date().toISOString(),\n      activationId: randomUUID(),\n      acknowledged: true,\n      cleanupVerified: false,\n      intake: 'PASS',\n      handoff: { id: 'dummy-which-is-replaced-later', to_role: 'builder' } // will be patched\n    }];");
content = content.replace(/receiptId: randomUUID\(\),/, "receiptId: 'dummy-which-is-replaced-later',");

fs.writeFileSync('tests/workflow/handoff-controller.test.mjs', content);
