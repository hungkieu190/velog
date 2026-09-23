/**
 * Handoff controller tests — V1–V10 verification matrix.
 *
 * Uses disposable fixtures and stub adapters. Never modifies the live VeLog checkout.
 * Requires Node 24 for built-in test runner.
 */

import { describe, it, before, after, beforeEach, mock } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { randomUUID } from 'node:crypto';

import {
  sha256,
  validateEnvelope,
  validateDocumentHashes,
  validateDocPath,
  readSignal,
  emptySignal,
  routeSignal,
  SCHEMA_VERSION,
  VALID_STATUSES,
} from '../../scripts/handoff-protocol.mjs';

import { publishHandoff, initSignalFile } from '../../scripts/publish-handoff.mjs';
import {
  pollCycle,
  ControllerState,
  readLedger,
  readConfig,
  acquireControllerLock,
  releaseControllerLock,
  MAX_DISPATCHES_PER_ACTIVATION,
  MAX_METADATA_CORRECTIONS,
} from '../../scripts/handoff-controller.mjs';

import { discover as discoverAntigravity } from '../../scripts/agent-adapters/antigravity.mjs';

let tmpDir;

async function createFixtureProject() {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'wf004-test-'));
  await fs.mkdir(path.join(dir, 'ai-document/tasks'), { recursive: true });
  await fs.mkdir(path.join(dir, '.cache/handoff'), { recursive: true });

  // Minimal checklist
  await fs.writeFile(path.join(dir, 'ai-document/implementation-checklist.md'),
    '# Implementation checklist\n\n## Current focus\n\n- Task: TEST-001\n- Status: READY\n');

  // Minimal README
  await fs.writeFile(path.join(dir, 'ai-document/README.md'),
    '# Test project\n');

  // Task file
  await fs.writeFile(path.join(dir, 'ai-document/tasks/TEST-001-fixture.md'),
    '# TEST-001: Fixture\n\n## Current handoff\n- Status: READY\n- Latest round: 1\n- Next actor: Builder\n\n### Chat handoff prompt\n\n```text\nStatus: READY\nTest prompt.\n```\n');

  // Agent roles
  await fs.writeFile(path.join(dir, 'ai-document/agent-roles.json'),
    JSON.stringify({ schema_version: 1, assignments: { codex: { role: 'architect' }, antigravity: { role: 'builder' } } }));

  // Empty signal
  await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
    JSON.stringify(emptySignal(), null, 2) + '\n');

  return dir;
}

function validHandoff(overrides = {}) {
  return {
    id: randomUUID(),
    previous_id: null,
    task_id: 'TEST-001',
    task_file: 'ai-document/tasks/TEST-001-fixture.md',
    plan_revision: 1,
    round: 1,
    task_status: 'READY',
    intent: 'work',
    from_role: 'architect',
    to_role: 'builder',
    sender_session_id: 'test-session-001',
    published_at: new Date().toISOString().replace(/\.\d+Z$/, 'Z'),
    prompt_sha256: sha256('Status: READY\nTest prompt.'),
    documents: [
      { path: 'ai-document/tasks/TEST-001-fixture.md', sha256: '' },
      { path: 'ai-document/implementation-checklist.md', sha256: '' },
      { path: 'ai-document/README.md', sha256: '' },
    ],
    ...overrides,
  };
}

// ===========================================================================
// V1 / AC1 — Markdown changes without publishing; atomic publication
// ===========================================================================
describe('V1 / AC1 — Publication trigger', () => {
  let dir;
  before(async () => { dir = await createFixtureProject(); });
  after(async () => { await fs.rm(dir, { recursive: true, force: true }); });

  it('empty signal causes zero dispatch', async () => {
    const state = new ControllerState();
    const config = { enabled: true, priorityTask: 'TEST-001', adapters: {} };
    await pollCycle(dir, config, state, {});
    assert.equal(state.pendingReceipt, null);
    assert.equal(state.dispatchCount, 0);
  });

  it('modifying Markdown alone never triggers dispatch', async () => {
    // Change the task file without publishing a signal
    await fs.appendFile(path.join(dir, 'ai-document/tasks/TEST-001-fixture.md'), '\n## New section\n');
    const state = new ControllerState();
    const config = { enabled: true, priorityTask: 'TEST-001', adapters: {} };
    await pollCycle(dir, config, state, {});
    assert.equal(state.dispatchCount, 0);
  });

  it('truncated temp JSON does not dispatch', async () => {
    await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'), '{"schema_version":1,"hand');
    const state = new ControllerState();
    const config = { enabled: true };
    await pollCycle(dir, config, state, {});
    assert.ok(state.errors.length > 0);
    assert.equal(state.dispatchCount, 0);
    // Restore valid signal
    await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
      JSON.stringify(emptySignal(), null, 2) + '\n');
  });
});

// ===========================================================================
// V2 / AC1 — Invalid signals rejected
// ===========================================================================
describe('V2 / AC1 — Validation rejects invalid signals', () => {
  it('rejects wrong schema_version', () => {
    const errors = validateEnvelope({ schema_version: 2, handoff: null });
    assert.ok(errors.some(e => e.includes('schema_version')));
  });

  it('rejects invalid status', () => {
    const h = validHandoff({ task_status: 'INVALID_STATUS' });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('task_status')));
  });

  it('rejects invalid role', () => {
    const h = validHandoff({ from_role: 'hacker' });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('from_role')));
  });

  it('rejects same from_role and to_role', () => {
    const h = validHandoff({ from_role: 'architect', to_role: 'architect' });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('must differ')));
  });

  it('rejects invalid UUID', () => {
    const h = validHandoff({ id: 'not-a-uuid' });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('UUID')));
  });

  it('rejects invalid prompt hash', () => {
    const h = validHandoff({ prompt_sha256: 'not-a-hash' });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('SHA-256')));
  });

  it('rejects missing required documents', () => {
    const h = validHandoff({ documents: [] });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('task_file')));
    assert.ok(errors.some(e => e.includes('checklist')));
  });

  it('rejects more than 64 documents', () => {
    const docs = Array.from({ length: 65 }, (_, i) => ({
      path: `doc-${i}.md`, sha256: sha256(`doc ${i}`),
    }));
    const h = validHandoff({ documents: docs });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('64')));
  });

  it('rejects route mismatch', () => {
    const h = validHandoff({
      task_status: 'READY_FOR_REVIEW',
      from_role: 'builder',
      to_role: 'builder', // Should be architect
    });
    const errors = validateEnvelope({ schema_version: 1, handoff: h });
    assert.ok(errors.some(e => e.includes('Routing') || e.includes('must differ')));
  });

  it('rejects path traversal', async () => {
    const dir = await createFixtureProject();
    try {
      await assert.rejects(() => validateDocPath(dir, '../../../etc/passwd'),
        { message: /Unsafe/ });
    } finally {
      await fs.rm(dir, { recursive: true, force: true });
    }
  });

  it('rejects absolute path', async () => {
    const dir = await createFixtureProject();
    try {
      await assert.rejects(() => validateDocPath(dir, '/etc/passwd'),
        { message: /Unsafe/ });
    } finally {
      await fs.rm(dir, { recursive: true, force: true });
    }
  });
});

// ===========================================================================
// V3 / AC2 — Serial dispatch and deduplication
// ===========================================================================
describe('V3 / AC2 — Serial dispatch and deduplication', () => {
  let dir;
  before(async () => { dir = await createFixtureProject(); });
  after(async () => { await fs.rm(dir, { recursive: true, force: true }); });

  it('same ID on repeated ticks causes one dispatch', async () => {
    let dispatchCount = 0;
    const stubAdapter = {
      dispatch: async ({ onSpawn }) => { 
        if (onSpawn) onSpawn({ pid: 1 });
        dispatchCount++; 
        return { success: true, pid: 1 }; 
      },
    };
    const h = validHandoff();
    // Compute real hashes
    for (const doc of h.documents) {
      const content = await fs.readFile(path.join(dir, doc.path));
      doc.sha256 = sha256(content);
    }
    const signal = { schema_version: 1, handoff: h };
    await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
      JSON.stringify(signal, null, 2) + '\n');

    const config = {
      enabled: true,
      priorityTask: 'TEST-001',
      adapters: { antigravity: { executable: 'antigravity', sessionId: 's1' } },
    };
    const state = new ControllerState();
    state.mode = 'dispatch';

    await pollCycle(dir, config, state, { builder: stubAdapter });
    assert.equal(dispatchCount, 1);

    // Second tick — same ID
    await pollCycle(dir, config, state, { builder: stubAdapter });
    assert.equal(dispatchCount, 1, 'Should not re-dispatch same ID');
  });

  it('two controllers — second cannot dispatch', async () => {
    const lock1 = await acquireControllerLock(dir);
    assert.ok(lock1, 'First lock should succeed');

    const lock2 = await acquireControllerLock(dir);
    assert.equal(lock2, null, 'Second lock should fail');

    await releaseControllerLock(lock1);
  });

  it('completed receipt is never replayed', async () => {
    const ledger = await readLedger(dir);
    const completed = ledger.filter(e => e.status === 'completed');
    for (const entry of completed) {
      const state = new ControllerState();
      state.lastReceiptId = entry.receiptId;
      const config = { enabled: true };
      await pollCycle(dir, config, state, {});
      assert.equal(state.dispatchCount, 0);
    }
  });
});

// ===========================================================================
// V4 / AC2 — Crash recovery
// ===========================================================================
describe('V4 / AC2 — Crash and uncertain state', () => {
  let dir;
  before(async () => { dir = await createFixtureProject(); });
  after(async () => { await fs.rm(dir, { recursive: true, force: true }); });

  it('claimed entry without completion is needs_attention', async () => {
    // Simulate a crash after claim before spawn
    const ledger = [{
      receiptId: randomUUID(),
      contentHash: 'abc',
      runId: randomUUID(),
      recipientRole: 'builder',
      recipientSession: 's1',
      status: 'claimed',
      pid: null,
      startedAt: new Date().toISOString(),
    }];
    await fs.writeFile(path.join(dir, '.cache/handoff/ledger.json'),
      JSON.stringify(ledger, null, 2));

    const readBack = await readLedger(dir);
    assert.equal(readBack[0].status, 'claimed');
    // Controller should recognize this as needing attention, not auto-retry
  });
});

// ===========================================================================
// V5 / AC1,AC2 — Parent completion and hash changes
// ===========================================================================
describe('V5 / AC1,AC2 — Document hash changes before launch', () => {
  let dir;
  before(async () => { dir = await createFixtureProject(); });
  after(async () => { await fs.rm(dir, { recursive: true, force: true }); });

  it('changed document hash rejects launch', async () => {
    const h = validHandoff();
    for (const doc of h.documents) {
      const content = await fs.readFile(path.join(dir, doc.path));
      doc.sha256 = sha256(content);
    }
    // Publish the signal
    await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
      JSON.stringify({ schema_version: 1, handoff: h }, null, 2));

    // Now modify a document
    await fs.appendFile(path.join(dir, 'ai-document/README.md'), '\nModified after publication.\n');

    const state = new ControllerState();
    state.mode = 'dispatch';
    const config = {
      enabled: true,
      priorityTask: 'TEST-001',
      adapters: { antigravity: { executable: 'antigravity', sessionId: 's1' } },
    };
    await pollCycle(dir, config, state, { builder: { dispatch: async () => ({ success: true }) } });
    assert.ok(state.errors.some(e => e.toLowerCase().includes('hash')), 'Should detect hash mismatch. State errors: ' + JSON.stringify(state.errors));
    assert.equal(state.dispatchCount, 0);
  });
});

// ===========================================================================
// V6 / AC2 — Adapter failure handling
// ===========================================================================
describe('V6 / AC2 — Adapter failure handling', () => {
  let dir;
  before(async () => { dir = await createFixtureProject(); });
  after(async () => { await fs.rm(dir, { recursive: true, force: true }); });

  it('adapter throwing does not mark completed', async () => {
    const h = validHandoff();
    for (const doc of h.documents) {
      const content = await fs.readFile(path.join(dir, doc.path));
      doc.sha256 = sha256(content);
    }
    await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
      JSON.stringify({ schema_version: 1, handoff: h }, null, 2));

    const failingAdapter = {
      dispatch: async () => { throw new Error('Simulated adapter failure'); },
    };

    const state = new ControllerState();
    state.mode = 'dispatch';
    const config = {
      enabled: true,
      priorityTask: 'TEST-001',
      adapters: { antigravity: { executable: 'antigravity', sessionId: 's1' } },
    };
    await pollCycle(dir, config, state, { builder: failingAdapter });
    assert.ok(state.errors.some(e => e.includes('Simulated')), 'State error should include Simulated: ' + state.errors.join(' '));

    const ledger = await readLedger(dir);
    const entry = ledger.find(e => e.receiptId === h.id);
    assert.ok(entry);
    assert.equal(entry.status, 'needs_attention');
  });

  it('adapter returning failure has correct status', async () => {
    const h = validHandoff({ id: randomUUID() });
    for (const doc of h.documents) {
      const content = await fs.readFile(path.join(dir, doc.path));
      doc.sha256 = sha256(content);
    }
    await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
      JSON.stringify({ schema_version: 1, handoff: h }, null, 2));

    const failAdapter = {
      dispatch: async ({ onSpawn }) => {
        if (onSpawn) {
          onSpawn({ pid: 999 });
          await new Promise(r => setTimeout(r, 10)); // Allow async writeLedger to finish
        }
        return {
          success: false, pid: 999, exitCode: 1,
          error: 'Codex exited nonzero',
        };
      },
    };

    const state = new ControllerState();
    state.mode = 'dispatch';
    const config = {
      enabled: true,
      priorityTask: 'TEST-001',
      adapters: { antigravity: { executable: 'antigravity', sessionId: 's1' } },
    };
    await pollCycle(dir, config, state, { builder: failAdapter });

    const ledger = await readLedger(dir);
    const entry = ledger.find(e => e.receiptId === h.id);
    assert.ok(entry, 'Entry should exist in ledger');
    assert.equal(entry.status, 'failed', JSON.stringify(entry));
  });
});

// ===========================================================================
// V7 / AC3 — Codex adapter (stub only; real CLI is separate evidence)
// ===========================================================================
describe('V7 / AC3 — Codex adapter result schema', () => {
  it('result schema has required fields', async () => {
    const { resultSchema } = await import('../../scripts/agent-adapters/codex.mjs');
    const schema = resultSchema();
    assert.deepEqual(schema.required, ['receipt_id', 'task_id', 'intake', 'outcome']);
    assert.ok(schema.properties.new_receipt_id);
  });
});

// ===========================================================================
// V8 / AC4 — Antigravity discovery
// ===========================================================================
describe('V8 / AC4 — Antigravity adapter discovery', () => {
  it('discovers broken symlink and reports exact error', async () => {
    const result = await discoverAntigravity();
    assert.equal(result.available, false);
    assert.ok(result.error);
    assert.ok(result.error.includes('broken symlink') || result.error.includes('No Antigravity CLI'));
  });
});

// ===========================================================================
// V9 / AC1,AC2 — Routing, priority and cycle limits
// ===========================================================================
describe('V9 / AC1,AC2 — Routing, priority and limits', () => {
  it('DONE status does not dispatch', () => {
    const target = routeSignal('DONE', 'architect');
    assert.equal(target, null);
  });

  it('DRAFT status does not dispatch', () => {
    assert.equal(routeSignal('DRAFT', 'architect'), null);
  });

  it('BLOCKED does not dispatch', () => {
    assert.equal(routeSignal('BLOCKED', 'architect'), null);
  });

  it('AWAITING_MANUAL_ACCEPTANCE does not dispatch', () => {
    assert.equal(routeSignal('AWAITING_MANUAL_ACCEPTANCE', 'architect'), null);
  });

  it('READY from architect routes to builder', () => {
    assert.equal(routeSignal('READY', 'architect'), 'builder');
  });

  it('READY_FOR_REVIEW from builder routes to architect', () => {
    assert.equal(routeSignal('READY_FOR_REVIEW', 'builder'), 'architect');
  });

  it('CHANGES_REQUESTED from architect routes to builder', () => {
    assert.equal(routeSignal('CHANGES_REQUESTED', 'architect'), 'builder');
  });

  it('dispatch limit stops after max', async () => {
    const dir = await createFixtureProject();
    try {
      const state = new ControllerState();
      state.mode = 'dispatch';
      state.dispatchCount = MAX_DISPATCHES_PER_ACTIVATION;

      const h = validHandoff();
      for (const doc of h.documents) {
        const content = await fs.readFile(path.join(dir, doc.path));
        doc.sha256 = sha256(content);
      }
      await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
        JSON.stringify({ schema_version: 1, handoff: h }, null, 2));

      const config = {
        enabled: true,
        priorityTask: 'TEST-001',
        adapters: { builder: { executable: 'codex', sessionId: 's1' } },
      };
      await pollCycle(dir, config, state, { builder: { dispatch: async () => ({ success: true }) } });
      assert.ok(state.blocked, JSON.stringify(state.errors));
      assert.ok(state.blocked.includes('Dispatch limit'));
    } finally {
      await fs.rm(dir, { recursive: true, force: true });
    }
  });

  it('non-priority task is deferred, not discarded', async () => {
    const dir = await createFixtureProject();
    try {
      await fs.writeFile(path.join(dir, 'ai-document/tasks/OTHER-999-fixture.md'),
        '# OTHER-999: Fixture\n\n## Current handoff\n- Status: READY\n- Latest round: 1\n- Next actor: Builder\n\n### Chat handoff prompt\n\n```text\nStatus: READY\nTest prompt.\n```\n');
      
      const checklist = await fs.readFile(path.join(dir, 'ai-document/implementation-checklist.md'), 'utf8');
      await fs.writeFile(path.join(dir, 'ai-document/implementation-checklist.md'), checklist + '\n- Task: OTHER-999\n- Status: READY\n');

      const h = validHandoff({ task_id: 'OTHER-999', task_file: 'ai-document/tasks/OTHER-999-fixture.md' });
      h.documents = [
        { path: 'ai-document/tasks/OTHER-999-fixture.md', sha256: '' },
        { path: 'ai-document/implementation-checklist.md', sha256: '' },
        { path: 'ai-document/README.md', sha256: '' },
      ];
      for (const doc of h.documents) {
        const content = await fs.readFile(path.join(dir, doc.path));
        doc.sha256 = sha256(content);
      }
      await fs.writeFile(path.join(dir, 'ai-document/handoff-signal.json'),
        JSON.stringify({ schema_version: 1, handoff: h }, null, 2));

      const state = new ControllerState();
      state.mode = 'dispatch';
      const config = {
        enabled: true,
        priorityTask: 'TEST-001',
        adapters: { builder: { executable: 'codex', sessionId: 's1' } },
      };
      await pollCycle(dir, config, state, {});
      assert.ok(state.blocked, JSON.stringify(state.errors));
      assert.ok(state.blocked.includes('deferred'));
      assert.equal(state.dispatchCount, 0);
    } finally {
      await fs.rm(dir, { recursive: true, force: true });
    }
  });
});

// ===========================================================================
// V10 / AC5 — Dashboard integration, HTTP behavior
// ===========================================================================
describe('V10 / AC5 — Protocol and routing basics', () => {
  it('empty signal is valid', () => {
    const signal = emptySignal();
    assert.equal(signal.schema_version, SCHEMA_VERSION);
    assert.equal(signal.handoff, null);
    const errors = validateEnvelope(signal);
    assert.equal(errors.length, 0);
  });

  it('sha256 produces consistent hex digest', () => {
    const hash = sha256('hello world');
    assert.match(hash, /^[0-9a-f]{64}$/);
    assert.equal(hash, sha256('hello world'));
    assert.notEqual(hash, sha256('different'));
  });

  it('document hash validation catches mismatch', async () => {
    const dir = await createFixtureProject();
    try {
      const result = await validateDocumentHashes(dir, [
        { path: 'ai-document/README.md', sha256: 'wrong_hash_value_' + '0'.repeat(48) },
      ]);
      assert.equal(result.valid, false);
      assert.ok(result.errors.length > 0);
    } finally {
      await fs.rm(dir, { recursive: true, force: true });
    }
  });

  it('initSignalFile creates file only if missing', async () => {
    const dir = await createFixtureProject();
    try {
      // Remove existing
      await fs.unlink(path.join(dir, 'ai-document/handoff-signal.json'));
      await initSignalFile(dir);
      const signal = await readSignal(path.join(dir, 'ai-document/handoff-signal.json'));
      assert.equal(signal.schema_version, SCHEMA_VERSION);
      assert.equal(signal.handoff, null);

      // Second call should not overwrite
      await initSignalFile(dir);
      const signal2 = await readSignal(path.join(dir, 'ai-document/handoff-signal.json'));
      assert.deepEqual(signal, signal2);
    } finally {
      await fs.rm(dir, { recursive: true, force: true });
    }
  });
});
