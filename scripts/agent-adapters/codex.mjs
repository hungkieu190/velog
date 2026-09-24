/**
 * Codex CLI agent adapter.
 *
 * Invokes Codex via fixed executable with shell=false, passing the exact validated prompt on stdin.
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
    required: ['receipt_id', 'task_id', 'intake', 'outcome', 'new_receipt_id'],
    additionalProperties: false,
  };
}

/**
 * Kill owned process group and wait for verified absence.
 * Returns true when confirmed gone, false if uncertain.
 *
 * @param {number} pid
 * @param {number} graceMs
 * @returns {Promise<boolean>}
 */
async function killGroupAndVerify(pid, graceMs = 5000) {
  try { process.kill(-pid, 'SIGTERM'); } catch { /* already gone */ }
  const deadline = Date.now() + graceMs;
  while (Date.now() < deadline) {
    await new Promise(r => setTimeout(r, 100));
    try { process.kill(-pid, 0); } catch { return true; }
  }
  try { process.kill(-pid, 'SIGKILL'); } catch { /* ignore */ }
  await new Promise(r => setTimeout(r, 500));
  try { process.kill(-pid, 0); return false; } catch { return true; }
}

/**
 * Dispatch a Codex session for the given handoff.
 *
 * @param {Object} options
 * @param {string} options.projectRoot
 * @param {Object} options.config     Adapter config: { executable, sessionId, sandbox }
 * @param {Object} options.handoff    The handoff envelope
 * @param {string} options.prompt     Exact validated prompt from finalValidation.prompt
 * @param {string} options.runId      Unique run identifier
 * @param {number} options.deadline   Timeout in ms
 * @param {AbortSignal} options.signal
 * @param {Function} options.onSpawn  Called with {pid, startIdentity} after spawn; may return a Promise
 * @returns {Promise<Object>}
 */
export async function dispatch({ projectRoot, config, handoff, prompt, runId, deadline, signal, onSpawn }) {
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

  // Build arguments — use explicit session resume
  const args = [
    'exec',
    '--sandbox', sandbox,
    'resume', sessionId,
    '--skip-git-repo-check',
    '--json',
    '--output-schema', schemaFile,
    '-o', resultFile,
    '-',
  ];

  // Use the exact validated prompt supplied by the controller.
  // If none supplied (e.g. in tests), fall back to minimal envelope context.
  const stdinPayload = prompt || [
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

  let settled = false;
  let childPid = null;
  let timer = null;
  let abortListener = null;
  const stdoutChunks = [];
  const stderrChunks = [];

  return new Promise((resolve, reject) => {
    const child = spawn(executable, args, {
      cwd: projectRoot,
      stdio: ['pipe', 'pipe', 'pipe'],
      shell: false,
      detached: true,
      env: { ...process.env },
    });

    childPid = child.pid;

    // Abort/cleanup helper — escalating TERM→KILL with descendant verification.
    async function abort() {
      if (childPid == null) return;
      const pid = childPid;
      try { process.kill(-pid, 'SIGTERM'); } catch { /* ignore */ }
    }

    function settleError(err) {
      if (settled) return;
      settled = true;
      if (timer) { clearTimeout(timer); timer = null; }
      if (signal && abortListener) signal.removeEventListener('abort', abortListener);
      reject(err);
    }

    // Deadline timer
    timer = setTimeout(() => {
      abort().catch(() => {});
    }, deadline);

    // AbortSignal support
    if (signal) {
      abortListener = () => abort().catch(() => {});
      signal.addEventListener('abort', abortListener);
    }

    child.stdout.on('data', chunk => stdoutChunks.push(chunk));
    child.stderr.on('data', chunk => stderrChunks.push(chunk));

    child.on('error', err => {
      settleError(new Error(`Codex spawn error: ${err.message}`));
    });

    child.on('exit', async (code, sysSignal) => {
      if (timer) { clearTimeout(timer); timer = null; }
      if (signal && abortListener) signal.removeEventListener('abort', abortListener);

      // Await onSpawn persistence before saving logs or resolving.
      // The controller's onSpawn writes the ledger entry; we must not resolve
      // before that write completes so the run ID is durable.
      if (onSpawn) {
        try {
          const spawnResult = onSpawn({ pid: childPid, startIdentity: sessionId });
          if (spawnResult && typeof spawnResult.then === 'function') {
            await spawnResult;
          }
        } catch (err) {
          // Persistence failure — cannot claim the run was recorded.
          const cleaned = await killGroupAndVerify(childPid);
          if (settled) return;
          settled = true;
          resolve({
            success: false,
            pid: childPid,
            exitCode: code,
            sessionId,
            cleanupVerified: cleaned,
            error: `onSpawn persistence failed: ${err.message}`,
            logDir,
          });
          return;
        }
      }

      // Save logs (best-effort; propagate write failures to caller).
      try {
        await fs.writeFile(stdoutLog, Buffer.concat(stdoutChunks));
        await fs.writeFile(stderrLog, Buffer.concat(stderrChunks));
      } catch (writeErr) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: `Log write failed: ${writeErr.message}`,
          logDir,
        });
        return;
      }

      if (code !== 0) {
        // Process exited non-zero — group may still contain descendants.
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sysSignal,
          sessionId,
          cleanupVerified: cleaned,
          error: `Codex exited with code ${code}${sysSignal ? ` (signal ${sysSignal})` : ''}`,
          logDir,
        });
        return;
      }

      // Parse JSONL events from stdout — strict: malformed lines fail.
      const events = [];
      let foundTerminal = false;
      let actualSessionId = null;
      let turnFailed = false;
      let malformedLine = null;

      const stdoutStr = Buffer.concat(stdoutChunks).toString('utf8');
      for (const line of stdoutStr.split('\n')) {
        if (!line.trim()) continue;
        try {
          const ev = JSON.parse(line);
          events.push(ev);
          if (ev.type === 'thread.started') {
            actualSessionId = ev.thread_id ?? null;
          } else if (ev.type === 'turn.completed') {
            foundTerminal = true;
          } else if (ev.type === 'turn.failed' || ev.type === 'action.denied') {
            turnFailed = true;
          }
        } catch {
          malformedLine = line.slice(0, 200);
        }
      }

      // Malformed JSONL is a hard failure — cannot trust event stream.
      if (malformedLine !== null) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: `Malformed JSONL in stdout: ${malformedLine}`,
          logDir,
        });
        return;
      }

      // Required: thread identity must be present and must match.
      if (!actualSessionId) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: 'Missing thread identity in stdout (no thread.started event with thread_id)',
          logDir,
        });
        return;
      }

      if (actualSessionId !== sessionId) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: `Thread identity mismatch: expected ${sessionId}, got ${actualSessionId}`,
          logDir,
        });
        return;
      }

      if (turnFailed) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: 'Agent turn failed or action was denied',
          logDir,
        });
        return;
      }

      if (!foundTerminal) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: 'Missing terminal event (turn.completed)',
          logDir,
        });
        return;
      }

      // Parse result file.
      let result;
      try {
        const resultContent = await fs.readFile(resultFile, 'utf8');
        result = JSON.parse(resultContent);
      } catch (err) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: `Cannot parse result: ${err.message}`,
          logDir,
        });
        return;
      }

      // Validate all required result fields and enums.
      const VALID_INTAKES = new Set(['PASS', 'FAIL']);
      const VALID_OUTCOMES = new Set(['handoff', 'needs_user', 'blocked']);

      const resultErrors = [];
      if (typeof result.receipt_id !== 'string') resultErrors.push('receipt_id missing or not a string');
      else if (result.receipt_id !== handoff.id) resultErrors.push(`receipt_id mismatch: expected ${handoff.id}, got ${result.receipt_id}`);
      if (typeof result.task_id !== 'string') resultErrors.push('task_id missing or not a string');
      else if (result.task_id !== handoff.task_id) resultErrors.push(`task_id mismatch: expected ${handoff.task_id}, got ${result.task_id}`);
      if (!VALID_INTAKES.has(result.intake)) resultErrors.push(`invalid intake enum: ${result.intake}`);
      if (!VALID_OUTCOMES.has(result.outcome)) resultErrors.push(`invalid outcome enum: ${result.outcome}`);
      if (!('new_receipt_id' in result)) resultErrors.push('new_receipt_id field missing');
      else if (result.outcome === 'handoff' && typeof result.new_receipt_id !== 'string') {
        resultErrors.push('new_receipt_id must be a string when outcome is handoff');
      }

      if (resultErrors.length) {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          error: `Invalid result fields: ${resultErrors.join('; ')}`,
          logDir,
          result,
        });
        return;
      }

      // intake=FAIL: transport succeeded but agent rejected the handoff.
      // Do NOT set success=true; the controller must route a correction, not a successor.
      if (result.intake === 'FAIL') {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          agentIntake: 'FAIL',
          agentOutcome: result.outcome,
          error: `Agent intake FAIL: outcome=${result.outcome}`,
          logDir,
          result,
        });
        return;
      }

      // outcome=needs_user or blocked: transport succeeded but agent cannot proceed.
      // Legitimate result — not a successor-authorization.
      if (result.outcome !== 'handoff') {
        const cleaned = await killGroupAndVerify(childPid);
        if (settled) return;
        settled = true;
        resolve({
          success: false,
          pid: childPid,
          exitCode: code,
          sessionId,
          cleanupVerified: cleaned,
          agentIntake: result.intake,
          agentOutcome: result.outcome,
          error: `Agent outcome is ${result.outcome}, not handoff; no successor authorized`,
          logDir,
          result,
        });
        return;
      }

      // Success path — verify descendant cleanup before reporting cleanupVerified.
      const cleaned = await killGroupAndVerify(childPid);
      if (settled) return;
      settled = true;
      resolve({
        success: true,
        pid: childPid,
        exitCode: code,
        sessionId,
        cleanupVerified: cleaned,
        agentIntake: result.intake,
        agentOutcome: result.outcome,
        result,
        events,
        logDir,
      });
    });

    // Write exact prompt to stdin and close.
    try {
      child.stdin.write(stdinPayload);
      child.stdin.end();
    } catch (err) {
      settleError(new Error(`stdin write failed: ${err.message}`));
    }
  });
}

export { resultSchema };
