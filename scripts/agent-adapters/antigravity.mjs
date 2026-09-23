/**
 * Antigravity IDE agent adapter.
 *
 * Discovery: `/usr/local/bin/antigravity-ide` was found as a broken symlink
 * to `/opt/antigravity-ide/Antigravity-IDE/antigravity-ide` (target missing).
 * No `agy` or `antigravity` executable found in PATH.
 *
 * This adapter cannot perform live dispatch until the CLI is available.
 * The same adapter contract as codex.mjs is implemented for stub testing
 * and future integration.
 *
 * @module agent-adapters/antigravity
 */

import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';

/**
 * Check if the Antigravity CLI is available.
 *
 * @param {string} [configuredExecutable]
 * @returns {Promise<{available: boolean, executable: string|null, error: string|null}>}
 */
export async function discover(configuredExecutable) {
  const candidates = [
    ...(configuredExecutable ? [configuredExecutable] : []),
    '/usr/local/bin/antigravity-ide',
    '/usr/local/bin/agy',
    '/usr/local/bin/antigravity',
  ];

  for (const candidate of candidates) {
    try {
      const stat = await fs.lstat(candidate);
      if (stat.isSymbolicLink()) {
        const target = await fs.readlink(candidate);
        try {
          await fs.access(candidate, fs.constants.X_OK);
          return { available: true, executable: candidate, error: null };
        } catch {
          if (candidate === configuredExecutable) {
            return {
              available: false,
              executable: null,
              error: `Configured executable ${candidate} is a broken symlink to ${target}`,
            };
          }
          // Continue if not configured explicitly
          continue;
        }
      }
      await fs.access(candidate, fs.constants.X_OK);
      return { available: true, executable: candidate, error: null };
    } catch {
      // Not found, try next
    }
  }

  return {
    available: false,
    executable: null,
    error: 'No Antigravity CLI executable found. Checked: ' + candidates.join(', '),
  };
}

/**
 * Dispatch an Antigravity session for the given handoff.
 *
 * Currently reports the exact blocker since the CLI is unavailable.
 *
 * @param {Object} options
 * @param {string} options.projectRoot
 * @param {Object} options.config     Adapter config
 * @param {Object} options.handoff    The handoff envelope
 * @param {string} options.runId      Unique run identifier
 * @param {number} options.deadline   Timeout in ms
 * @returns {Promise<Object>}
 */
export async function dispatch({ projectRoot, config, handoff, runId, deadline, signal, onSpawn }) {
  const { executable: configuredExec, sessionId, sandbox = 'read-only' } = config;
  
  if (!sessionId) {
    throw new Error('Antigravity adapter requires an explicit sessionId; ephemeral sessions are not permitted');
  }

  const discovery = await discover(configuredExec);

  if (!discovery.available) {
    return {
      success: false,
      pid: null,
      exitCode: null,
      sessionId: sessionId,
      error: `Antigravity CLI unavailable: ${discovery.error}`,
      logDir: null,
    };
  }

  const { executable } = discovery;
  const logDir = path.join(projectRoot, '.cache/handoff/runs', runId);
  await fs.mkdir(logDir, { recursive: true });

  const schemaFile = path.join(logDir, 'schema.json');
  const resultFile = path.join(logDir, 'result.json');
  const stdoutLog = path.join(logDir, 'stdout.log');
  const stderrLog = path.join(logDir, 'stderr.log');

  const resultSchema = {
    type: 'object',
    properties: {
      receipt_id: { type: 'string' },
      task_id: { type: 'string' },
      intake: { type: 'string', enum: ['PASS', 'FAIL'] },
      outcome: { type: 'string', enum: ['handoff', 'needs_user', 'blocked'] },
      new_receipt_id: { type: ['string', 'null'] },
    },
    required: ['receipt_id', 'task_id', 'intake', 'outcome'],
    additionalProperties: false,
  };
  await fs.writeFile(schemaFile, JSON.stringify(resultSchema, null, 2));

  // Build prompt from handoff
  const prompt = [
    `Receipt ID: ${handoff.id}`,
    `Task: ${handoff.task_id} (${handoff.task_file})`,
    `Status: ${handoff.task_status}`,
    `Intent: ${handoff.intent}`,
    `From: ${handoff.from_role}`,
    `Plan revision: ${handoff.plan_revision}, Round: ${handoff.round}`,
    '',
    'Read the task file and referenced documents. Follow the mandatory incoming handoff validation.',
    'Return structured result with receipt_id, task_id, intake, outcome and new_receipt_id.',
  ].join('\n');

  // Exactly identical args contract to Codex
  const args = [
    'exec',
    '--sandbox', sandbox,
    'resume', sessionId,
    '--skip-git-repo-check',
    '--json',
    '--output-schema', schemaFile,
    '-o', resultFile,
    '-'
  ];

  return new Promise((resolve, reject) => {
    const stdoutChunks = [];
    const stderrChunks = [];

    const child = spawn(executable, args, {
      cwd: projectRoot,
      stdio: ['pipe', 'pipe', 'pipe'],
      shell: false,
      detached: true,
      env: { ...process.env },
    });

    const pid = child.pid;
    if (onSpawn) {
      onSpawn({ pid, startIdentity: sessionId });
    }

    const abortFn = () => {
      try { process.kill(-child.pid, 'SIGTERM'); } catch { /* ignore */ }
      setTimeout(() => {
        try { process.kill(-child.pid, 'SIGKILL'); } catch { /* ignore */ }
      }, 5000).unref();
    };

    const timer = setTimeout(abortFn, deadline);
    
    if (signal) {
      signal.addEventListener('abort', abortFn);
    }

    child.stdout.on('data', chunk => stdoutChunks.push(chunk));
    child.stderr.on('data', chunk => stderrChunks.push(chunk));

    child.on('error', err => {
      clearTimeout(timer);
      if (signal) signal.removeEventListener('abort', abortFn);
      reject(new Error(`Antigravity spawn error: ${err.message}`));
    });

    child.on('exit', async (code, sysSignal) => {
      clearTimeout(timer);
      if (signal) signal.removeEventListener('abort', abortFn);

      await fs.writeFile(stdoutLog, Buffer.concat(stdoutChunks));
      await fs.writeFile(stderrLog, Buffer.concat(stderrChunks));

      if (code !== 0) {
        resolve({
          success: false,
          pid,
          exitCode: code,
          signal: sysSignal,
          sessionId: sessionId,
          error: `Antigravity exited with code ${code}${sysSignal ? ` (signal ${sysSignal})` : ''}`,
          logDir,
        });
        return;
      }

      // Parse result
      let result;
      try {
        const resultContent = await fs.readFile(resultFile, 'utf8');
        result = JSON.parse(resultContent);
      } catch (err) {
        resolve({
          success: false, pid, exitCode: code, sessionId,
          error: `Cannot parse result: ${err.message}`,
          logDir,
        });
        return;
      }

      // Validate result explicitly
      if (result.receipt_id !== handoff.id) {
        resolve({
          success: false, pid, exitCode: code, sessionId,
          error: `Result receipt_id mismatch: expected ${handoff.id}, got ${result.receipt_id}`,
          logDir, result,
        });
        return;
      }
      if (result.task_id !== handoff.task_id) {
        resolve({
          success: false, pid, exitCode: code, sessionId,
          error: `Result task_id mismatch: expected ${handoff.task_id}, got ${result.task_id}`,
          logDir, result,
        });
        return;
      }

      // Parse JSONL events from stdout
      const events = [];
      let foundTerminal = false;
      let actualSessionId = null;
      let turnFailed = false;

      const stdoutStr = Buffer.concat(stdoutChunks).toString('utf8');
      for (const line of stdoutStr.split('\n')) {
        if (!line.trim()) continue;
        try {
          const ev = JSON.parse(line);
          events.push(ev);
          if (ev.type === 'session.started') {
            actualSessionId = ev.session_id;
          } else if (ev.type === 'turn.completed') {
            foundTerminal = true;
          } else if (ev.type === 'turn.failed' || ev.type === 'action.denied') {
            turnFailed = true;
          }
        } catch {
          // Non-JSON line, skip
        }
      }
      
      if (actualSessionId && actualSessionId !== sessionId) {
         resolve({
           success: false, pid, exitCode: code, sessionId,
           error: `Thread identity mismatch: expected ${sessionId}, got ${actualSessionId}`,
           logDir
         });
         return;
      }

      if (turnFailed) {
         resolve({
           success: false, pid, exitCode: code, sessionId,
           error: `Agent turn failed or action was denied`,
           logDir
         });
         return;
      }

      if (!foundTerminal) {
         resolve({
           success: false, pid, exitCode: code, sessionId,
           error: `Missing terminal event (turn.completed)`,
           logDir
         });
         return;
      }

      resolve({
        success: true,
        pid,
        exitCode: code,
        sessionId: sessionId,
        result,
        events,
        logDir,
      });
    });

    child.stdin.write(prompt);
    child.stdin.end();
  });
}
