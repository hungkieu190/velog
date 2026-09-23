/**
 * Handoff controller — 30-second polling loop with serial dispatch.
 *
 * Reads ai-document/handoff-signal.json every 30 seconds, validates the signal,
 * dispatches to the appropriate agent adapter, and manages the run lifecycle.
 *
 * Starts in observe-only mode if local config is missing/disabled.
 * Never makes HTTP-triggered mutations. Never marks DONE or closes findings.
 *
 * @module handoff-controller
 */

import fs from 'node:fs/promises';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import {
  readSignal,
  validateHandoffMetadata,
  routeSignal,
  sha256,
  SCHEMA_VERSION,
} from './handoff-protocol.mjs';

const SIGNAL_FILE = 'ai-document/handoff-signal.json';
const CONFIG_FILE = '.cache/handoff/config.json';
const LEDGER_FILE = '.cache/handoff/ledger.json';
const CONTROLLER_LOCK = '.cache/handoff/controller.lock';

const DEFAULT_POLL_INTERVAL = 30_000;
const DEFAULT_RUN_DEADLINE = 15 * 60_000;
const MAX_DISPATCHES_PER_ACTIVATION = 6;
const MAX_METADATA_CORRECTIONS = 2;

/**
 * @typedef {Object} ControllerConfig
 * @property {boolean} enabled
 * @property {string|null} priorityTask
 * @property {Object} adapters
 * @property {Object} adapters.codex
 * @property {string} adapters.codex.executable
 * @property {string} adapters.codex.sessionId
 * @property {Object} adapters.antigravity
 * @property {string} adapters.antigravity.executable
 * @property {string} adapters.antigravity.sessionId
 * @property {number} runDeadline
 */

/**
 * @typedef {Object} LedgerEntry
 * @property {string} receiptId
 * @property {string} contentHash
 * @property {string} runId
 * @property {string} recipientRole
 * @property {string} recipientSession
 * @property {string} status  'claimed' | 'running' | 'completed' | 'failed' | 'needs_attention'
 * @property {number} pid
 * @property {string} startedAt
 * @property {string} [completedAt]
 * @property {Object} [result]
 */

/**
 * Read controller configuration from .cache/handoff/config.json.
 *
 * @param {string} projectRoot
 * @returns {Promise<ControllerConfig|null>}
 */
export async function readConfig(projectRoot) {
  try {
    const content = await fs.readFile(path.join(projectRoot, CONFIG_FILE), 'utf8');
    return JSON.parse(content);
  } catch {
    return null;
  }
}

/**
 * Read the dispatch ledger.
 *
 * @param {string} projectRoot
 * @returns {Promise<LedgerEntry[]>}
 */
export async function readLedger(projectRoot) {
  try {
    const content = await fs.readFile(path.join(projectRoot, LEDGER_FILE), 'utf8');
    if (!content.trim()) return [];
    return JSON.parse(content);
  } catch (err) {
    if (err.code === 'ENOENT') return [];
    throw new Error(`Corrupt or unreadable ledger: ${err.message}`);
  }
}

/**
 * Write the dispatch ledger atomically.
 *
 * @param {string} projectRoot
 * @param {LedgerEntry[]} ledger
 */
async function writeLedger(projectRoot, ledger) {
  const dir = path.join(projectRoot, '.cache/handoff');
  await fs.mkdir(dir, { recursive: true });
  const ledgerPath = path.join(projectRoot, LEDGER_FILE);
  const tmpPath = ledgerPath + '.tmp';
  const handle = await fs.open(tmpPath, 'w');
  try {
    await handle.writeFile(JSON.stringify(ledger, null, 2) + '\n');
    await handle.sync();
  } finally {
    await handle.close();
  }
  await fs.rename(tmpPath, ledgerPath);
}

/**
 * Acquire the controller lock. Returns a handle or null if locked by another.
 *
 * @param {string} projectRoot
 * @returns {Promise<{handle: import('node:fs/promises').FileHandle, lockPath: string}|null>}
 */
export async function acquireControllerLock(projectRoot) {
  const dir = path.join(projectRoot, '.cache/handoff');
  await fs.mkdir(dir, { recursive: true });
  const lockPath = path.join(projectRoot, CONTROLLER_LOCK);
  try {
    const handle = await fs.open(lockPath, 'wx');
    // Write PID for identification
    await handle.writeFile(JSON.stringify({ pid: process.pid, startedAt: new Date().toISOString() }));
    await handle.sync();
    return { handle, lockPath };
  } catch (err) {
    if (err.code === 'EEXIST') return null;
    throw err;
  }
}

/**
 * Release the controller lock.
 *
 * @param {{handle: import('node:fs/promises').FileHandle, lockPath: string}} lock
 */
export async function releaseControllerLock(lock) {
  await lock.handle.close();
  await fs.unlink(lock.lockPath);
}

/**
 * State object for the controller monitor. Exposed for dashboard display.
 */
export class ControllerState {
  constructor() {
    this.mode = 'observe';        // 'observe' | 'dispatch'
    this.lastPoll = null;
    this.lastReceiptId = null;
    this.pendingReceipt = null;
    this.currentRun = null;
    this.dispatchCount = 0;
    this.correctionCount = 0;
    this.errors = [];
    this.blocked = null;
  }

  toJSON() {
    return {
      mode: this.mode,
      lastPoll: this.lastPoll,
      lastReceiptId: this.lastReceiptId,
      pendingReceipt: this.pendingReceipt ? {
        id: this.pendingReceipt.id,
        taskId: this.pendingReceipt.task_id,
        status: this.pendingReceipt.task_status,
        target: this.pendingReceipt.to_role,
      } : null,
      currentRun: this.currentRun,
      dispatchCount: this.dispatchCount,
      correctionCount: this.correctionCount,
      errors: this.errors.slice(-5),
      blocked: this.blocked,
    };
  }
}

/**
 * Single poll cycle. Returns state updates without managing the timer.
 *
 * @param {string} projectRoot
 * @param {ControllerConfig} config
 * @param {ControllerState} state
 * @param {Object} adapters   Map of role → adapter module
 * @returns {Promise<void>}
 */
export async function pollCycle(projectRoot, config, state, adapters) {
  state.lastPoll = new Date().toISOString();

  // Read signal
  let signal;
  try {
    signal = await readSignal(path.join(projectRoot, SIGNAL_FILE));
  } catch (err) {
    state.errors.push(`Signal read error: ${err.message}`);
    return;
  }

  // Validate full handoff metadata
  const validationResult = await validateHandoffMetadata(projectRoot, signal);
  if (!validationResult.valid) {
    state.errors.push(`Signal validation: ${validationResult.errors.join('; ')}`);
    return;
  }

  // No handoff — nothing to do
  if (!signal.handoff) {
    state.pendingReceipt = null;
    return;
  }

  const h = signal.handoff;

  // Same ID as last seen — no new work
  if (h.id === state.lastReceiptId) {
    return;
  }

  // Check for same ID with different content (tampering)
  const ledger = await readLedger(projectRoot);
  const existing = ledger.find(e => e.receiptId === h.id);
  if (existing) {
    const signalHash = sha256(JSON.stringify(signal));
    if (existing.contentHash !== signalHash) {
      state.errors.push(`Receipt ${h.id} has different content than ledger (tampering rejected)`);
      return;
    }
    // Already processed, skip
    state.lastReceiptId = h.id;
    return;
  }

  // New receipt
  state.pendingReceipt = h;

  // Observe-only mode
  if (!config.enabled || state.mode === 'observe') {
    return;
  }

  // Check dispatch limits
  if (state.dispatchCount >= MAX_DISPATCHES_PER_ACTIVATION) {
    state.blocked = `Dispatch limit reached (${MAX_DISPATCHES_PER_ACTIVATION}). User can explicitly resume.`;
    return;
  }

  if (h.intent === 'correct_metadata' && state.correctionCount >= MAX_METADATA_CORRECTIONS) {
    state.blocked = `Metadata correction limit reached (${MAX_METADATA_CORRECTIONS}). User attention required.`;
    return;
  }

  // Priority task check
  if (config.priorityTask && h.task_id !== config.priorityTask) {
    state.blocked = `Receipt for ${h.task_id} deferred; priority task is ${config.priorityTask}`;
    return;
  }

  // Route check
  let targetRole;
  if (h.intent === 'correct_metadata') {
    // A metadata correction must route to the exact target and preserve original status
    targetRole = h.to_role;
    // We also require that there's a valid rejected receipt
    if (!h.previous_id) {
      state.errors.push(`correct_metadata requires previous_id for routing`);
      state.lastReceiptId = h.id;
      return;
    }
  } else {
    targetRole = routeSignal(h.task_status, h.from_role);
  }
  
  if (!targetRole) {
    state.errors.push(`Status ${h.task_status} from ${h.from_role} does not route to dispatch`);
    state.lastReceiptId = h.id;
    return;
  }

  // Adapter availability
  const adapterConfig = config.adapters?.[targetRole === 'builder' ? 'antigravity' : 'codex'];
  const adapter = adapters?.[targetRole];
  if (!adapter || !adapterConfig?.executable) {
    state.blocked = `Adapter for ${targetRole} is not configured or unavailable`;
    state.lastReceiptId = h.id;
    return;
  }

  // Parent completion gate
  if (h.parent_run_id) {
    const parentEntry = ledger.find(e => e.runId === h.parent_run_id);
    if (!parentEntry) {
      state.blocked = `Parent run ${h.parent_run_id} is unknown`;
      return;
    }
    if (parentEntry.status !== 'completed') {
      state.blocked = `Parent run ${h.parent_run_id} is not durably completed (status: ${parentEntry.status})`;
      return;
    }
  }

  // Persist claim before spawn
  const runId = randomUUID();
  const contentHash = sha256(JSON.stringify(signal));
  const entry = {
    receiptId: h.id,
    contentHash,
    runId,
    recipientRole: targetRole,
    recipientSession: adapterConfig.sessionId,
    status: 'claimed',
    pid: null,
    startedAt: new Date().toISOString(),
  };
  ledger.push(entry);
  await writeLedger(projectRoot, ledger);

  // Mark current run
  state.currentRun = { runId, receiptId: h.id, role: targetRole };
  state.lastReceiptId = h.id;

  // Dispatch via adapter
  try {
    entry.status = 'running';
    await writeLedger(projectRoot, ledger);

    state.activeAbortController = new AbortController();

    const result = await adapter.dispatch({
      projectRoot,
      config: adapterConfig,
      handoff: h,
      runId,
      deadline: config.runDeadline || DEFAULT_RUN_DEADLINE,
      signal: state.activeAbortController.signal,
      onSpawn: (info) => {
        entry.pid = info.pid;
        writeLedger(projectRoot, ledger).catch(() => {});
      }
    });

    entry.pid = result.pid || entry.pid || null;
    entry.status = result.success ? 'completed' : 'failed';
    entry.completedAt = new Date().toISOString();
    entry.result = result;
    await writeLedger(projectRoot, ledger);

    state.dispatchCount++;
    if (h.intent === 'correct_metadata') state.correctionCount++;

  } catch (err) {
    entry.status = 'needs_attention';
    entry.completedAt = new Date().toISOString();
    entry.result = { error: err.message };
    await writeLedger(projectRoot, ledger);
    state.errors.push(`Dispatch failed: ${err.message}`);
  } finally {
    state.currentRun = null;
  }
}

/**
 * Start the polling monitor. Returns a stop function.
 *
 * @param {string} projectRoot
 * @param {Object} options
 * @param {number} [options.interval]  Poll interval in ms
 * @param {Object} [options.adapters]  Map of role → adapter
 * @param {Function} [options.clock]   Injectable timer (for testing)
 * @returns {Promise<{state: ControllerState, stop: Function}>}
 */
export async function startMonitor(projectRoot, options = {}) {
  const interval = options.interval || DEFAULT_POLL_INTERVAL;
  const adapters = options.adapters || {};
  const clockFn = options.clock || setTimeout;

  const state = new ControllerState();
  const config = await readConfig(projectRoot);

  if (!config || !config.enabled) {
    state.mode = 'observe';
  } else {
    state.mode = 'dispatch';
  }

  // Acquire lock
  const lock = await acquireControllerLock(projectRoot);
  if (!lock) {
    state.errors.push('Another controller holds the lock; monitor is observe-only.');
    state.mode = 'observe';
  }
  
  let timerId;
  let isRunning = false;
  let isStopped = false;

  const tick = async () => {
    if (isStopped) return;
    if (isRunning) return;
    
    isRunning = true;
    try {
      const latestConfig = await readConfig(projectRoot);
      if (lock && latestConfig?.enabled && state.mode === 'observe' && state.errors.length === 0) {
        state.mode = 'dispatch';
      }
      await pollCycle(projectRoot, latestConfig || { enabled: false }, state, adapters);
    } catch (err) {
      state.errors.push(`Poll error: ${err.message}`);
    } finally {
      isRunning = false;
      if (!isStopped) {
        timerId = clockFn(tick, interval);
      }
    }
  };

  // Initial poll
  await tick();

  const stop = async () => {
    isStopped = true;
    clearTimeout(timerId);
    
    if (state.activeAbortController) {
      state.activeAbortController.abort('Monitor stopping');
    }
    
    // Wait for active dispatch cycle to complete
    while (isRunning) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    
    if (lock) {
      await releaseControllerLock(lock);
    }
  };

  return { state, stop };
}

export {
  SIGNAL_FILE,
  CONFIG_FILE,
  LEDGER_FILE,
  CONTROLLER_LOCK,
  DEFAULT_POLL_INTERVAL,
  DEFAULT_RUN_DEADLINE,
  MAX_DISPATCHES_PER_ACTIVATION,
  MAX_METADATA_CORRECTIONS,
};
