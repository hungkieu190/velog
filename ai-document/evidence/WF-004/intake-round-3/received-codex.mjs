/**
 * Codex CLI agent adapter.
 *
 * Invokes Codex via fixed executable with shell=false, passing the prompt on stdin.
 * Uses explicit-session exec resume with JSONL, schema and final output.
 *
 * Shape from preflight:
 *   codex exec --sandbox read-only --json --output-schema <schema> -o <result> -C <dir> -
 *   resume: codex exec --sandbox read-only resume <session-id> --skip-git-repo-check --json --output-schema <schema> -o <result> -
 *
 * @module agent-adapters/codex
 */

import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';
import { randomUUID } from 'node:crypto';

/**
 * Build the result schema for structured output.
 *
 * @returns {Object}
 */
function resultSchema() {
  return {
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
}

/**
 * Dispatch a Codex session for the given handoff.
 *
 * @param {Object} options
 * @param {string} options.projectRoot
 * @param {Object} options.config     Adapter config: { executable, sessionId, sandbox }
 * @param {Object} options.handoff    The handoff envelope
 * @param {string} options.runId      Unique run identifier
 * @param {number} options.deadline   Timeout in ms
 * @returns {Promise<Object>}
 */
export async function dispatch({ projectRoot, config, handoff, runId, deadline, signal, onSpawn }) {
  const { executable = 'codex', sessionId, sandbox = 'read-only' } = config;
  
  if (!sessionId) {
    throw new Error('Codex adapter requires an explicit sessionId; ephemeral sessions are not permitted');
  }

  const logDir = path.join(projectRoot, '.cache/handoff/runs', runId);
  await fs.mkdir(logDir, { recursive: true });

  const schemaFile = path.join(logDir, 'schema.json');
  const resultFile = path.join(logDir, 'result.json');
  const stdoutLog = path.join(logDir, 'stdout.log');
  const stderrLog = path.join(logDir, 'stderr.log');

  await fs.writeFile(schemaFile, JSON.stringify(resultSchema(), null, 2));

  // Build arguments
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

    // Deadline timer
    const timer = setTimeout(abortFn, deadline);
    
    // AbortSignal support
    if (signal) {
      signal.addEventListener('abort', abortFn);
    }

    child.stdout.on('data', chunk => stdoutChunks.push(chunk));
    child.stderr.on('data', chunk => stderrChunks.push(chunk));

    child.on('error', err => {
      clearTimeout(timer);
      if (signal) signal.removeEventListener('abort', abortFn);
      reject(new Error(`Codex spawn error: ${err.message}`));
    });

    child.on('exit', async (code, sysSignal) => {
      clearTimeout(timer);
      if (signal) signal.removeEventListener('abort', abortFn);

      // Save logs
      await fs.writeFile(stdoutLog, Buffer.concat(stdoutChunks));
      await fs.writeFile(stderrLog, Buffer.concat(stderrChunks));

      if (code !== 0) {
        resolve({
          success: false,
          pid,
          exitCode: code,
          signal,
          sessionId: sessionId,
          cleanupVerified: true,
          error: `Codex exited with code ${code}${signal ? ` (signal ${signal})` : ''}`,
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
          success: false,
          pid,
          exitCode: code,
          sessionId: sessionId,
          cleanupVerified: true,
          error: `Cannot parse result: ${err.message}`,
          logDir,
        });
        return;
      }

      // Validate result explicitly
      if (result.receipt_id !== handoff.id) {
        resolve({
          success: false,
          pid,
          exitCode: code,
          sessionId: sessionId,
          cleanupVerified: true,
          error: `Result receipt_id mismatch: expected ${handoff.id}, got ${result.receipt_id}`,
          logDir,
          result,
        });
        return;
      }
      if (result.task_id !== handoff.task_id) {
        resolve({
          success: false,
          pid,
          exitCode: code,
          sessionId: sessionId,
          cleanupVerified: true,
          error: `Result task_id mismatch: expected ${handoff.task_id}, got ${result.task_id}`,
          logDir,
          result,
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
          if (ev.type === 'thread.started') {
            actualSessionId = ev.thread_id;
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
           success: false, pid, exitCode: code, sessionId, cleanupVerified: true,
           error: `Thread identity mismatch: expected ${sessionId}, got ${actualSessionId}`,
           logDir
         });
         return;
      }

      if (turnFailed) {
         resolve({
           success: false, pid, exitCode: code, sessionId, cleanupVerified: true,
           error: `Agent turn failed or action was denied`,
           logDir
         });
         return;
      }

      if (!foundTerminal) {
         resolve({
           success: false, pid, exitCode: code, sessionId, cleanupVerified: true,
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
        cleanupVerified: true,
        result,
        events,
        logDir,
      });
    });

    // Write prompt to stdin and close
    child.stdin.write(prompt);
    child.stdin.end();
  });
}

export { resultSchema };
