/** Serial, durable local handoff monitor. HTTP never invokes this controller. */
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import {
  readSignal, validateEnvelope, validateHandoffMetadata, formatErrors, routeSignal, sha256,
} from './handoff-protocol.mjs';
import {
  readJson, readLedger, writeLedger, validateConfig, acquireLock, releaseLock, LEDGER_FILE,
} from './handoff-store.mjs';

const SIGNAL_FILE = 'ai-document/handoff-signal.json';
const CONFIG_FILE = '.cache/handoff/config.json';
const CONTROLLER_LOCK = '.cache/handoff/controller.lock';
const DEFAULT_POLL_INTERVAL = 30_000;
const DEFAULT_RUN_DEADLINE = 15 * 60_000;
const MAX_DISPATCHES_PER_ACTIVATION = 6;
const MAX_METADATA_CORRECTIONS = 2;

export const readConfig = async root => validateConfig(await readJson(root, CONFIG_FILE, { enabled: false }));
export { readLedger };
export const acquireControllerLock = root => acquireLock(root, CONTROLLER_LOCK);
export const releaseControllerLock = releaseLock;

export class ControllerState {
  constructor() {
    this.mode = 'observe';
    this.lastPoll = null;
    this.lastReceiptId = null;
    this.pendingReceipt = null;
    this.currentRun = null;
    this.dispatchCount = 0;
    this.correctionCount = 0;
    this.errors = [];
    this.blocked = null;
    this.cleanupUncertain = false;
    this.stopped = false;
  }

  toJSON() {
    return {
      mode: this.mode, lastPoll: this.lastPoll, lastReceiptId: this.lastReceiptId,
      pendingReceipt: this.pendingReceipt ? {
        id: this.pendingReceipt.id, taskId: this.pendingReceipt.task_id,
        status: this.pendingReceipt.task_status, target: this.pendingReceipt.to_role,
      } : null,
      currentRun: this.currentRun, dispatchCount: this.dispatchCount,
      correctionCount: this.correctionCount, errors: this.errors.slice(-5), blocked: this.blocked,
    };
  }
}

function block(state, message, error = false) {
  state.blocked = message;
  if (error) state.errors = [...state.errors.slice(-19), message];
}

/** One public cycle, protected by the same repository lease as the monitor. */
export async function pollCycle(root, config, state, adapters) {
  if (state.cycle || state.stopped) return;
  state.cycle = true;
  let lease;
  try {
    if (!state.lease) {
      lease = await acquireControllerLock(root);
      if (!lease) {
        block(state, 'Another controller holds the lock; dispatch is disabled.');
        return;
      }
    }
    await runCycle(root, config, state, adapters);
  } catch (error) {
    block(state, error.message, true);
  } finally {
    state.cycle = false;
    if (lease && !state.cleanupUncertain) await releaseControllerLock(lease);
    else if (lease) await lease.handle.close(); // Preserve the lease for explicit recovery.
  }
}

async function runCycle(root, config, state, adapters) {
  state.lastPoll = new Date().toISOString();
  state.blocked = null;
  validateConfig(config);
  const ledger = await readLedger(root);
  const activation = ledger.filter(e => e.activationId === config.activationId);
  state.dispatchCount = activation.filter(e => e.status !== 'rejected').length;
  state.correctionCount = 0;
  for (const entry of activation.slice().reverse()) {
    if (entry.intent !== 'correct_metadata') break;
    state.correctionCount++;
  }
  const uncertain = ledger.find(e => ['claimed', 'running', 'needs_attention'].includes(e.status) || !e.cleanupVerified);
  if (uncertain) {
    block(state, `Run ${uncertain.runId} requires manual recovery: verify its process, session and cleanup before reconciliation.`);
    return;
  }
  const signal = await readSignal(path.join(root, SIGNAL_FILE));
  const envelopeErrors = validateEnvelope(signal);
  if (envelopeErrors.length) {
    block(state, envelopeErrors.join('; '), true);
    return;
  }
  if (!signal.handoff) { state.pendingReceipt = null; return; }
  const h = signal.handoff;
  const hash = sha256(JSON.stringify(signal));
  const existing = ledger.find(e => e.receiptId === h.id);
  // Compare immutable identity before deduplication or current Markdown validation.
  if (existing) {
    if (existing.contentHash !== hash) block(state, `Receipt ${h.id} tampering rejected`, true);
    else {
      state.lastReceiptId = h.id;
      state.pendingReceipt = null;
      // Restore any durable blocker from a previously rejected/failed entry so it
      // is visible on unchanged polls and survives restarts.
      if (existing.status === 'rejected' || existing.status === 'failed' ||
          existing.status === 'needs_attention') {
        const cause = existing.errors ? existing.errors.join('; ') : (existing.result?.error || existing.status);
        block(state, `Unresolved prior run (${existing.status}) for receipt ${h.id}: ${cause}`, true);
      }
    }
    return;
  }
  state.pendingReceipt = h;
  const validation = await validateHandoffMetadata(root, signal);
  if (!config.enabled || state.mode !== 'dispatch') {
    if (!validation.valid) block(state, formatErrors(validation), true);
    return;
  }
  if (h.task_id !== config.priorityTask) {
    block(state, `Receipt for ${h.task_id} deferred; priority task is ${config.priorityTask}`);
    return;
  }
  const target = h.intent === 'correct_metadata' ? h.to_role : routeSignal(h.task_status, h.from_role);
  if (!target) { block(state, `Status ${h.task_status} does not route to dispatch`); return; }
  // Adapter lookup uses role keys directly (architect/builder) — not executable names.
  const adapterConfig = config.adapters?.[target];
  const adapter = adapters?.[target];
  if (!adapter || !adapterConfig) { block(state, `Adapter for role '${target}' is not configured or unavailable`); return; }
  if (h.intent === 'correct_metadata') {
    const rejected = ledger.find(e => e.receiptId === h.rejected_receipt_id);
    if (!rejected || rejected.intake !== 'FAIL' || !rejected.acknowledged ||
      rejected.handoff.from_role !== h.to_role || rejected.recipientRole !== h.from_role ||
      rejected.recipientSession !== h.sender_session_id || rejected.runId !== h.parent_run_id ||
      rejected.handoff.task_id !== h.task_id || rejected.handoff.task_status !== h.task_status) {
      block(state, 'Correction authority does not match the rejected receipt and original sender', true);
      return;
    }
  }
  if (h.parent_run_id) {
    const parent = ledger.find(e => e.runId === h.parent_run_id);
    const rejectedCorrection = h.intent === 'correct_metadata' && parent?.status === 'rejected';
    if (!parent || (!rejectedCorrection && parent.status !== 'completed') || !parent.cleanupVerified ||
      parent.receiptId !== h.previous_id || parent.recipientSession !== h.sender_session_id ||
      parent.recipientRole !== h.from_role ||
      (!rejectedCorrection && (parent.result?.result?.outcome !== 'handoff' ||
        parent.result.result.new_receipt_id !== h.id))) {
      block(state, `Parent run ${h.parent_run_id} has no verified completion/cleanup and matching successor`);
      return;
    }
  } else if (h.previous_id || ledger.length) {
    block(state, 'Successor requires a known parent run', true);
    return;
  }
  const entry = {
    receiptId: h.id, contentHash: hash, runId: randomUUID(), activationId: config.activationId,
    recipientRole: target, recipientSession: adapterConfig.sessionId, status: 'claimed',
    intent: h.intent, handoff: h,
    // Structural intake = signal/Markdown validation result (pre-dispatch).
    // Agent intake = the dispatched agent's self-reported decision (post-dispatch).
    structuralIntake: validation.valid ? 'PASS' : 'FAIL',
    agentIntake: null,  // Set after dispatch completes.
    // Legacy field: kept for compatibility; mirrors structuralIntake until agent responds.
    intake: validation.valid ? 'PASS' : 'FAIL',
    acknowledged: true, cleanupVerified: false, pid: null, startedAt: new Date().toISOString(),
  };
  if (!validation.valid) {
    entry.status = 'rejected';
    entry.cleanupVerified = true;
    entry.errors = validation.errors;
    ledger.push(entry);
    await writeLedger(root, ledger);
    block(state, `Intake rejected; metadata correction required. ${formatErrors(validation)}`, true);
    return;
  }
  if (state.dispatchCount >= MAX_DISPATCHES_PER_ACTIVATION ||
    (h.intent === 'correct_metadata' && state.correctionCount >= MAX_METADATA_CORRECTIONS)) {
    block(state, 'Dispatch/correction limit reached; explicit user activation is required.');
    return;
  }
  if (adapter.available) {
    const availability = await adapter.available(adapterConfig);
    if (!availability.available) { block(state, availability.error); return; }
  }
  // No awaits between the final snapshot validation and the durable claim except its write.
  const finalSignal = await readSignal(path.join(root, SIGNAL_FILE));
  const finalValidation = await validateHandoffMetadata(root, finalSignal);
  if (sha256(JSON.stringify(finalSignal)) !== hash || !finalValidation.valid) {
    block(state, 'Snapshot changed before launch; no dispatch', true);
    return;
  }
  if (state.stopped) return;
  ledger.push(entry);
  await writeLedger(root, ledger);
  state.dispatchCount++;
  if (h.intent === 'correct_metadata') state.correctionCount++;
  state.currentRun = { runId: entry.runId, receiptId: h.id, role: target };
  state.activeAbortController = new AbortController();
  if (state.stopped) state.activeAbortController.abort();
  let writes = Promise.resolve();
  try {
    const result = await adapter.dispatch({
      projectRoot: root, config: adapterConfig, handoff: h, prompt: finalValidation.prompt,
      runId: entry.runId, deadline: config.runDeadline || DEFAULT_RUN_DEADLINE,
      signal: state.activeAbortController.signal,
      onSpawn(info) {
        writes = writes.then(async () => {
          entry.pid = info.pid;
          entry.startIdentity = info.startIdentity;
          entry.status = 'running';
          await writeLedger(root, ledger);
        });
        return writes;
      },
    });
    await writes;
    // Record the agent's reported intake/outcome separately from structural validation.
    entry.agentIntake = result.agentIntake ?? null;
    entry.agentOutcome = result.agentOutcome ?? null;
    // Update legacy intake to reflect the authoritative final decision.
    if (entry.agentIntake) entry.intake = entry.agentIntake;
    entry.cleanupVerified = result.cleanupVerified === true;
    // Determine status based on cleanup and final transport outcome.
    if (!entry.cleanupVerified) {
      entry.status = 'needs_attention';
    } else if (!result.success) {
      // Failed but cleanup verified — persist as failed with cause.
      entry.status = 'failed';
    } else {
      entry.status = 'completed';
    }
    entry.result = result;
    if (!result.success) block(state, result.error || 'Dispatch failed', true);
  } catch (error) {
    // Unknown adapter failure cannot prove that a spawned process was reaped.
    entry.status = 'needs_attention';
    entry.result = { error: error.message };
    block(state, error.message, true);
  } finally {
    state.cleanupUncertain = !entry.cleanupVerified;
    entry.completedAt = new Date().toISOString();
    try { await writeLedger(root, ledger); }
    catch (error) { state.cleanupUncertain = true; throw error; }
    finally { state.currentRun = null; state.activeAbortController = null; }
    state.lastReceiptId = h.id;
    state.pendingReceipt = null;
  }
}

/** Return the handle before starting the initial asynchronous dispatch. */
export async function startMonitor(root, options = {}) {
  const state = new ControllerState();
  const lease = await acquireControllerLock(root);
  state.lease = lease;
  let timer;
  let active = Promise.resolve();
  let stopping;
  async function tick() {
    if (state.stopped) return;
    try {
      const config = await readConfig(root);
      state.mode = config.enabled && lease ? 'dispatch' : 'observe';
      if (!lease) block(state, 'Another controller holds the lock; monitor is observe-only.');
      else await pollCycle(root, config, state, options.adapters || {});
    } catch (error) { block(state, error.message, true); }
    finally {
      if (!state.stopped) timer = (options.clock || setTimeout)(schedule, options.interval || DEFAULT_POLL_INTERVAL);
    }
  }
  function schedule() { active = tick(); }
  const stop = () => {
    if (stopping) return stopping;
    state.stopped = true;
    clearTimeout(timer);
    state.activeAbortController?.abort('Monitor stopping');
    stopping = (async () => {
      await active;
      if (lease) {
        if (state.cleanupUncertain) {
          await lease.handle.close();
          throw new Error('Cleanup uncertain; controller lease retained for manual recovery');
        }
        await releaseControllerLock(lease);
      }
    })();
    return stopping;
  };
  // Microtask starts after the caller has received its resolved handle.
  timer = setTimeout(schedule, 0);
  return { state, stop };
}

export {
  SIGNAL_FILE, CONFIG_FILE, LEDGER_FILE, CONTROLLER_LOCK, DEFAULT_POLL_INTERVAL,
  DEFAULT_RUN_DEADLINE, MAX_DISPATCHES_PER_ACTIVATION, MAX_METADATA_CORRECTIONS,
};
