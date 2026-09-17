import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/** Reject traversal, symlinks and paths outside a narrowly allowed root. */
export async function safePath(base, relative) {
  if (!relative || path.isAbsolute(relative) || relative.split(/[\\/]/).some((part) => part === '..' || part === '.')) {
    throw new Error(`Unsafe relative path: ${relative}`);
  }
  const resolved = path.resolve(base, relative);
  if (!resolved.startsWith(`${path.resolve(base)}${path.sep}`)) throw new Error(`Path escapes root: ${relative}`);
  const parts = path.relative(base, resolved).split(path.sep);
  let current = base;
  const baseStat = await fs.lstat(base);
  if (baseStat.isSymbolicLink()) throw new Error(`Symlink rejected: ${base}`);
  for (const part of parts) {
    current = path.join(current, part);
    try {
      if ((await fs.lstat(current)).isSymbolicLink()) throw new Error(`Symlink rejected: ${current}`);
    } catch (error) {
      if (error.code !== 'ENOENT') throw error;
    }
  }
  return resolved;
}

export async function listFiles(directory) {
  const files = [];
  for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
    const name = path.join(directory, entry.name);
    if (entry.isSymbolicLink()) throw new Error(`Symlink rejected: ${name}`);
    if (entry.isDirectory()) files.push(...await listFiles(name));
    else if (entry.isFile()) files.push(name);
    else throw new Error(`Unsupported file type: ${name}`);
  }
  return files.sort();
}

export async function run(command, args, options = {}) {
  await new Promise((resolve, reject) => {
    const child = spawn(command, args, { cwd: root, stdio: 'inherit', ...options });
    child.on('error', reject);
    child.on('exit', (code, signal) => code === 0 ? resolve() : reject(new Error(`${command} failed (${signal || code})`)));
  });
}

/** A lock prevents two writers from replacing the same build/release outputs. */
export async function withLock(name, callback) {
  const cache = await safePath(root, '.cache');
  await fs.mkdir(cache, { recursive: true });
  const lock = await safePath(cache, name);
  const handle = await fs.open(lock, 'wx');
  try {
    return await callback();
  } finally {
    await handle.close();
    await fs.unlink(lock);
  }
}
