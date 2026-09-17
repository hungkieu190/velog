import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import config from './release.config.mjs';
import buildConfig from './build.config.mjs';
import { build } from './build.mjs';
import { root, safePath, listFiles, run, withLock } from './files.mjs';

export async function release() {
  return withLock('release.lock', async () => {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(config.slug)) throw new Error('Invalid plugin slug');
    const plugin = await fs.readFile(await safePath(root, 'velog.php'), 'utf8');
    const version = plugin.match(/^\s*\* Version:\s*(\S+)/m)?.[1];
    if (!version || !/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/.test(version)) throw new Error('Invalid plugin version');
    const constant = plugin.match(/define\(\s*'VELOG_VERSION',\s*'([^']+)'/)[1];
    const pkg = JSON.parse(await fs.readFile(await safePath(root, 'package.json'), 'utf8'));
    const readme = await fs.readFile(await safePath(root, 'README.md'), 'utf8');
    if (constant !== version || pkg.version !== version || readme.match(/^Stable tag:\s*(\S+)/m)?.[1] !== version) throw new Error('Version declarations do not match');
    const composer = JSON.parse(await fs.readFile(await safePath(root, 'composer.json'), 'utf8'));
    const lock = JSON.parse(await fs.readFile(await safePath(root, 'composer.lock'), 'utf8'));
    if (lock.packages.length || Object.keys(composer.require).some((name) => name !== 'php')) {
      throw new Error('New runtime dependencies require an explicitly reviewed packaging policy');
    }
    await run('php', ['-r', 'if (!class_exists("ZipArchive")) { fwrite(STDERR, "PHP ZipArchive is required\\n"); exit(1); }']);
    await run('composer', ['validate', '--no-check-publish', '--no-interaction']);
    // Keep existing quality gates, now using actual exit codes, and include unit tests.
    await run('npm', ['run', 'check']);
    await run('composer', ['run', 'test']);
    // Retain the exact production bytes while the build lock is held.
    // A concurrent later development build cannot change this package's assets.
    const productionBytes = new Map();
    const outputs = await build('production', (files) => {
      for (const [name, bytes] of files) productionBytes.set(name, Buffer.from(bytes));
    });
    const releases = await safePath(root, 'release');
    await fs.mkdir(releases, { recursive: true });
    const archive = path.join(releases, `.${config.slug}-${version}-${process.pid}.zip`);
    const finalStage = await safePath(releases, config.slug);
    const finalZip = await safePath(releases, `${config.slug}-${version}.zip`);
    const backup = await safePath(releases, `.previous-${process.pid}`);
    // Validate every final destination before allocating temporary staging.
    const staging = await fs.mkdtemp(path.join(releases, '.stage-'));
    let backedUp = false;
    try {
      const copy = async (relative) => {
        const source = await safePath(root, relative);
        if (!(await fs.stat(source)).isFile()) throw new Error(`Missing regular file: ${relative}`);
        const destination = await safePath(staging, relative);
        await fs.mkdir(path.dirname(destination), { recursive: true });
        await fs.copyFile(source, destination);
      };
      for (const file of config.files) await copy(file);
      for (const [directory, extensions] of Object.entries(config.directories)) {
        for (const file of await listFiles(await safePath(root, directory))) {
          if (extensions.includes(path.extname(file))) await copy(path.relative(root, file));
        }
      }
      for (const file of outputs) {
        if (file.endsWith('.map')) throw new Error('Development source map in production build');
        const destination = await safePath(staging, file);
        await fs.mkdir(path.dirname(destination), { recursive: true });
        await fs.writeFile(destination, productionBytes.get(file));
      }
      // No installation or network access: this project has zero third-party runtime packages.
      // Composer generates its own production autoloader against staged PHP source.
      await copy('composer.json');
      await copy('composer.lock');
      await run('composer', ['dump-autoload', '--no-dev', '--optimize', '--no-scripts', '--no-plugins', '--no-interaction'], {
        cwd: staging, env: { ...process.env, COMPOSER_DISABLE_NETWORK: '1' },
      });
      await fs.unlink(path.join(staging, 'composer.json'));
      await fs.unlink(path.join(staging, 'composer.lock'));
      for (const required of [...config.required, ...Object.values(buildConfig.javascript), ...Object.values(buildConfig.stylesheets)]) {
        if (!(await fs.stat(await safePath(staging, required))).isFile()) throw new Error(`Missing package file: ${required}`);
      }
      await run('php', ['-r', 'define("ABSPATH", __DIR__ . "/"); require "vendor/autoload.php"; foreach (["Plugin", "Loader", "Activator", "Deactivator"] as $class) { if (!class_exists("MF\\\\VeLog\\\\Core\\\\" . $class)) { exit(1); } }'], { cwd: staging });
      const files = await listFiles(staging);
      if (files.some((file) => /(?:\.map$|\/node_modules\/|\/src\/(?:css|js)\/|\/\.env)/.test(file))) throw new Error('Forbidden package content');
      await run('php', [path.join(root, 'scripts/archive.php'), staging, archive, config.slug]);
      try { await fs.rename(finalStage, backup); backedUp = true; }
      catch (error) { if (error.code !== 'ENOENT') throw error; }
      await fs.rename(staging, finalStage);
      try { await fs.rename(archive, finalZip); }
      catch (error) {
        await fs.rm(finalStage, { recursive: true, force: true });
        if (backedUp) { await fs.rename(backup, finalStage); backedUp = false; }
        throw error;
      }
      if (backedUp) await fs.rm(backup, { recursive: true, force: true });
      process.stdout.write(`Release verified: ${files.length} runtime files\n${finalStage}\n${finalZip}\n`);
    } catch (error) {
      if (backedUp) {
        try { await fs.access(finalStage); }
        catch (missing) {
          if (missing.code === 'ENOENT') { await fs.rename(backup, finalStage); backedUp = false; }
          else throw missing;
        }
      }
      throw error;
    } finally {
      await fs.rm(staging, { recursive: true, force: true });
      await fs.rm(archive, { force: true });
    }
  });
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) release().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
