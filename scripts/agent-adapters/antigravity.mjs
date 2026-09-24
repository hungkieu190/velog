/**
 * Antigravity IDE agent adapter.
 *
 * Discovery: `/usr/local/bin/antigravity-ide` was found as a broken symlink
 * to `/opt/antigravity-ide/Antigravity-IDE/antigravity-ide` (target missing).
 * No `agy` or `antigravity` executable found in PATH.
 *
 * This adapter MUST NOT spawn until the Antigravity CLI interface has been
 * independently verified. Discovery of an executable path does not imply
 * CLI argument compatibility. Return an explicit unsupported-interface blocker.
 *
 * @module agent-adapters/antigravity
 */

import fs from 'node:fs/promises';

/**
 * Check if the Antigravity CLI is present (existence, not interface compatibility).
 *
 * @param {string} [configuredExecutable]
 * @returns {Promise<{present: boolean, executable: string|null, error: string|null}>}
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
          return { present: true, executable: candidate, error: null };
        } catch {
          if (candidate === configuredExecutable) {
            return {
              present: false,
              executable: null,
              error: `Configured executable ${candidate} is a broken symlink to ${target}`,
            };
          }
          continue;
        }
      }
      await fs.access(candidate, fs.constants.X_OK);
      return { present: true, executable: candidate, error: null };
    } catch {
      // Not found or inaccessible, try next.
    }
  }

  return {
    present: false,
    executable: null,
    error: 'No Antigravity CLI executable found. Checked: ' + candidates.join(', '),
  };
}

/**
 * Report adapter availability. The Antigravity interface is not yet verified,
 * so this always returns unavailable to prevent spawning.
 *
 * @param {Object} _config
 * @returns {Promise<{available: boolean, error: string}>}
 */
export async function available(_config) {
  const discovery = await discover(_config?.executable);
  if (!discovery.present) {
    return {
      available: false,
      error: `Antigravity CLI not present: ${discovery.error}`,
    };
  }
  // Executable exists but its CLI interface (argument schema, event protocol,
  // session-resume contract) has not been independently verified.
  // Do NOT spawn until verification is complete.
  return {
    available: false,
    error: `Antigravity executable found at ${discovery.executable} but its CLI interface is not verified. Dispatch is blocked until interface verification is complete. This is not a missing-binary error.`,
  };
}

/**
 * Dispatch is not supported until the Antigravity CLI interface is verified.
 * Returns an explicit unsupported-interface blocker without spawning.
 *
 * @param {Object} _options
 * @returns {Promise<Object>}
 */
export async function dispatch(_options) {
  const { config } = _options || {};
  const discovery = await discover(config?.executable);
  const execInfo = discovery.present
    ? `executable present at ${discovery.executable} but interface unverified`
    : `no executable found (${discovery.error})`;

  return {
    success: false,
    pid: null,
    exitCode: null,
    sessionId: config?.sessionId ?? null,
    cleanupVerified: true, // Nothing was spawned, so nothing to clean up.
    agentIntake: null,
    agentOutcome: null,
    error: `Antigravity adapter: dispatch blocked — ${execInfo}. Interface must be verified before this adapter may spawn.`,
    logDir: null,
  };
}
