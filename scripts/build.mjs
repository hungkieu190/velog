import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';
import * as sass from 'sass';
import config from './build.config.mjs';
import { root, safePath, withLock } from './files.mjs';

function owned(relative) {
  return /^assets\/(js|css|images|fonts)\/[\w./-]+$/.test(relative) && !relative.includes('..');
}

export async function build(mode = 'production', onBuilt = null) {
  if (config.manifest !== 'assets/.generated.json') throw new Error('Invalid manifest destination');
  if (!['development', 'production'].includes(mode)) throw new Error(`Unknown mode: ${mode}`);
  return withLock('build.lock', async () => {
    const production = mode === 'production';
    const outputs = new Map();
    const add = (name, content) => {
      if (!owned(name) || outputs.has(name)) throw new Error(`Invalid or duplicate output: ${name}`);
      outputs.set(name, content);
    };
    for (const [source, output] of Object.entries(config.javascript)) {
      const entry = await safePath(root, source);
      await fs.access(entry);
      const result = await esbuild.build({
        entryPoints: [entry], outfile: await safePath(root, output), bundle: true, write: false,
        minify: production, sourcemap: production ? false : 'linked', sourcesContent: true,
        legalComments: 'inline', target: config.target, format: config.format, external: config.external,
      });
      for (const file of result.outputFiles) add(path.relative(root, file.path), file.contents);
    }
    for (const [source, output] of Object.entries(config.stylesheets)) {
      const entry = await safePath(root, source);
      const compiled = await sass.compileAsync(entry, { style: 'expanded', sourceMap: !production, sourceMapIncludeSources: true });
      let css = compiled.css;
      if (production) css = (await esbuild.transform(css, { loader: 'css', minify: true, legalComments: 'inline', target: config.target })).code;
      else {
        compiled.sourceMap.sources = compiled.sourceMap.sources.map((url) => path.relative(path.dirname(path.join(root, output)), fileURLToPath(url)));
        compiled.sourceMap.file = path.basename(output);
        add(`${output}.map`, JSON.stringify(compiled.sourceMap));
        css += `\n/*# sourceMappingURL=${path.basename(output)}.map */\n`;
      }
      add(output, css);
    }
    for (const [source, output] of Object.entries(config.static)) add(output, await fs.readFile(await safePath(root, source)));
    for (const [name, content] of outputs) {
      if (!name.endsWith('.css')) continue;
      for (const match of String(content).matchAll(/url\(\s*['"]?([^)'"\s]+)['"]?\s*\)/g)) {
        if (/^(data:|https?:|#|\/\/)/i.test(match[1])) continue;
        const resource = path.normalize(path.join(path.dirname(name), decodeURIComponent(match[1].split(/[?#]/)[0])));
        if (!outputs.has(resource)) throw new Error(`CSS resource is not a configured output: ${name} -> ${resource}`);
      }
    }
    const manifestPath = await safePath(root, config.manifest);
    let previous = [];
    try { previous = JSON.parse(await fs.readFile(manifestPath, 'utf8')).files; }
    catch (error) { if (error.code !== 'ENOENT') throw error; }
    if (!Array.isArray(previous) || previous.some((name) => !owned(name))) throw new Error('Invalid generated-file manifest');
    // Reject all unsafe destinations before writing or deleting any output.
    for (const name of new Set([...previous, ...outputs.keys()])) await safePath(root, name);
    const cache = await safePath(root, '.cache');
    const staging = await fs.mkdtemp(path.join(cache, 'build-'));
    try {
      for (const [name, content] of outputs) {
        const temporary = path.join(staging, name);
        await fs.mkdir(path.dirname(temporary), { recursive: true });
        await fs.writeFile(temporary, content);
      }
      for (const name of outputs.keys()) {
        const destination = await safePath(root, name);
        await fs.mkdir(path.dirname(destination), { recursive: true });
        await fs.rename(path.join(staging, name), destination);
      }
      for (const name of previous.filter((name) => !outputs.has(name))) await fs.rm(await safePath(root, name), { force: true });
      await fs.mkdir(path.dirname(manifestPath), { recursive: true });
      await fs.writeFile(manifestPath, `${JSON.stringify({ mode, files: [...outputs.keys()].sort() }, null, 2)}\n`);
    } finally { await fs.rm(staging, { recursive: true, force: true }); }
    process.stdout.write(`Built ${outputs.size} files (${mode}).\n`);
    if (onBuilt) await onBuilt(outputs);
    return [...outputs.keys()];
  });
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  const unknown = process.argv.slice(2).filter((arg) => !/^--mode=(development|production)$/.test(arg));
  if (unknown.length) throw new Error(`Unknown arguments: ${unknown.join(', ')}`);
  build(process.argv.find((arg) => arg.startsWith('--mode='))?.split('=')[1]).catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exitCode = 1;
  });
}
