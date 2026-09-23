import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { spawnSync } from 'node:child_process';
import { root, safePath } from '../../scripts/files.mjs';
import { parseTask, readProgress } from '../../scripts/progress-data.mjs';
import { renderProgress } from '../../scripts/progress-view.mjs';

async function fixture() {
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'velog-workflow-'));
  for (const name of ['scripts', 'src', 'package.json', 'composer.json', 'composer.lock', 'velog.php', 'README.md', 'LICENSE', 'uninstall.php', 'bin', 'phpcs.xml', 'phpstan.neon', 'tests', 'languages']) {
    await fs.cp(path.join(root, name), path.join(directory, name), { recursive: true });
  }
  await fs.symlink(path.join(root, 'node_modules'), path.join(directory, 'node_modules'));
  await fs.symlink(path.join(root, 'vendor'), path.join(directory, 'vendor'));
  return directory;
}
function command(directory, script, ...args) {
  return spawnSync(process.execPath, [script, ...args], { cwd: directory, encoding: 'utf8', timeout: 120000 });
}

test('build ownership, maps, repeatability and safe failure', async () => {
  const directory = await fixture();
  try {
    const dev = command(directory, 'scripts/build.mjs', '--mode=development');
    assert.equal(dev.status, 0, dev.stderr);
    const map = JSON.parse(await fs.readFile(path.join(directory, 'assets/css/admin.css.map')));
    assert.ok(map.sources.some((name) => name.endsWith('src/css/admin.scss')));
    for (const source of map.sources) await fs.access(path.resolve(directory, 'assets/css', source));
    const css = await fs.readFile(path.join(directory, 'assets/css/admin.css'), 'utf8');
    assert.match(css, /\n  /);
    const jsMap = JSON.parse(await fs.readFile(path.join(directory, 'assets/js/admin.js.map')));
    for (const source of jsMap.sources) await fs.access(path.resolve(directory, 'assets/js', source));
    const source = path.join(directory, 'src/js/admin.js');
    const original = await fs.readFile(source, 'utf8');
    await fs.appendFile(source, '\nwindow.velogBuildFixture = 173;\n');
    assert.equal(command(directory, 'scripts/build.mjs').status, 0);
    assert.match(await fs.readFile(path.join(directory, 'assets/js/admin.js'), 'utf8'), /173/);
    await fs.unlink(path.join(directory, 'assets/js/admin.js'));
    assert.equal(command(directory, 'scripts/build.mjs').status, 0);
    await fs.access(path.join(directory, 'assets/js/admin.js'));
    await assert.rejects(fs.access(path.join(directory, 'assets/css/admin.css.map')));
    const production = await fs.readFile(path.join(directory, 'assets/css/admin.css'), 'utf8');
    assert.match(production, /\/\*!/);
    assert.match(production, /margin:20px 0/);
    const productionJs = await fs.readFile(path.join(directory, 'assets/js/admin.js'), 'utf8');
    assert.doesNotMatch(productionJs, /mf_velog_initialize_admin/);
    assert.match(productionJs, /GPL-2\.0-or-later/);
    assert.doesNotMatch(production, /\n  /);
    const before = await fs.readFile(path.join(directory, 'assets/js/admin.js'));
    await fs.writeFile(source, 'const = ;');
    assert.notEqual(command(directory, 'scripts/build.mjs').status, 0);
    assert.deepEqual(await fs.readFile(path.join(directory, 'assets/js/admin.js')), before);
    await fs.writeFile(source, original);
    await fs.unlink(source);
    assert.notEqual(command(directory, 'scripts/build.mjs').status, 0);
    await fs.writeFile(source, original);
    await fs.appendFile(path.join(directory, 'src/css/admin.scss'), '\n.fixture { background: url(missing.png); }');
    assert.notEqual(command(directory, 'scripts/build.mjs').status, 0);
    await assert.rejects(safePath(directory, '../escape'));
    await fs.symlink(os.tmpdir(), path.join(directory, 'outside'));
    await assert.rejects(safePath(directory, 'outside/test'));
    await fs.writeFile(path.join(directory, 'src/css/admin.scss'), await fs.readFile(path.join(root, 'src/css/admin.scss')));
    const manifest = path.join(directory, 'assets/.generated.json');
    const state = JSON.parse(await fs.readFile(manifest));
    state.files.push('assets/js/obsolete-fixture.js');
    await fs.writeFile(path.join(directory, 'assets/js/obsolete-fixture.js'), 'old');
    await fs.writeFile(manifest, JSON.stringify(state));
    assert.equal(command(directory, 'scripts/build.mjs').status, 0);
    await assert.rejects(fs.access(path.join(directory, 'assets/js/obsolete-fixture.js')));
    await fs.writeFile(manifest, JSON.stringify({ files: ['../package.json'] }));
    assert.notEqual(command(directory, 'scripts/build.mjs').status, 0);
  } finally { await fs.rm(directory, { recursive: true, force: true }); }
});

test('dashboard surfaces contradictions and escapes untrusted Markdown', async () => {
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'velog-progress-'));
  try {
    await fs.mkdir(path.join(directory, 'ai-document/tasks'), { recursive: true });
    await fs.writeFile(path.join(directory, 'ai-document/implementation-checklist.md'), '# Checklist\n## Current focus\n- Task: WF-999\n- Status: CHANGES_REQUESTED\n- Next actor: Builder\n- Exact next action: Builder action\n## Phase 1\n- [ ] WF-999: Example. Status: CHANGES_REQUESTED.\n');
    const markdown = '# WF-999: <script>alert(1)</script>\n## Current handoff\n- Status: DONE\n- Next actor: User\n- Latest round: Review 3\n- Next actor and exact next action: Review F-003\n### Chat handoff prompt\n```\ninvalid bare block\n```\n### Chat handoff prompt\n```javascript\nStatus: IN_PROGRESS\n```\n### Chat handoff prompt\n```text\nStatus: IN_PROGRESS\n```';
    const task = parseTask(markdown, 'task.md');
    assert.equal(task.owner, 'Architect');
    assert.equal(task.promptCount, 3);
    assert.equal(task.validPromptCount, 2);
    assert.equal(task.latestPrompt, 'Status: IN_PROGRESS');
    assert.equal(task.issues.length, 2);
    await fs.writeFile(path.join(directory, 'ai-document/tasks/task.md'), markdown);
    const data = await readProgress(directory);
    assert.ok(data.issues.length >= 4);
    assert.ok(data.issues.some(i => i.includes('Checklist next actor says Builder but task expects User')));
    assert.equal(data.summary.open, 1);
    const html = renderProgress(data);
    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /&lt;script&gt;/);
  } finally { await fs.rm(directory, { recursive: true, force: true }); }
});

test('W3-V3: prompt extraction does not fall back to older sections', () => {
  const markdown = '# Task\n## Current handoff\n- Status: READY\n- Next actor: Builder\n### Chat handoff prompt\n```text\nStatus: READY\n```\n### Chat handoff prompt\n```javascript\nconsole.log(1);\n```';
  const task = parseTask(markdown, 'task.md');
  assert.equal(task.promptCount, 2);
  assert.equal(task.validPromptCount, 1);
  assert.equal(task.latestPrompt, '');
});

test('W3-V4: prompt declaration exact token match', () => {
  const markdown = '# Task\n## Current handoff\n- Status: READY_FOR_REVIEW\n- Next actor: Architect\n### Chat handoff prompt\n```\nStatus: READY; and later READY_FOR_REVIEW\n```';
  const task = parseTask(markdown, 'task.md');
  assert.ok(task.issues.some(i => i.includes('Prompt declares READY but task is READY_FOR_REVIEW')));
});

test('W3-V4: unverified status mentions', () => {
  const markdown = '# Task\n## Current handoff\n- Status: READY_FOR_REVIEW\n- Next actor: Architect\n### Chat handoff prompt\n```\nREADY_FOR_REVIEWING\n```';
  const task = parseTask(markdown, 'task.md');
  assert.ok(task.issues.some(i => i.includes('Missing or stale current handoff prompt')));
});

test('release package boundaries and failures preserve the last successful archive', async () => {
  const directory = await fixture();
  try {
    const first = command(directory, 'scripts/release.mjs');
    assert.equal(first.status, 0, first.stdout + first.stderr);
    const archive = path.join(directory, 'release/velog-0.1.0.zip');
    const originalArchive = await fs.readFile(archive);
    const stage = path.join(directory, 'release/velog');
    await fs.access(path.join(stage, 'vendor/autoload.php'));
    await assert.rejects(fs.access(path.join(stage, 'src/js')));
    await assert.rejects(fs.access(path.join(stage, 'vendor/phpunit')));
    const versionFile = path.join(directory, 'package.json');
    const originalVersion = await fs.readFile(versionFile, 'utf8');
    await fs.writeFile(versionFile, originalVersion.replace('"version": "0.1.0"', '"version": "9.9.9"'));
    assert.notEqual(command(directory, 'scripts/release.mjs').status, 0);
    await fs.writeFile(versionFile, originalVersion);
    const source = path.join(directory, 'src/js/admin.js');
    const originalSource = await fs.readFile(source);
    // Sass error reaches the build stage after the quality gate.
    const stylesheet = path.join(directory, 'src/css/admin.scss');
    const originalStylesheet = await fs.readFile(stylesheet);
    await fs.writeFile(stylesheet, '$invalid: ; .broken {');
    assert.notEqual(command(directory, 'scripts/release.mjs').status, 0);
    await fs.writeFile(stylesheet, originalStylesheet);
    const license = path.join(directory, 'LICENSE');
    const originalLicense = await fs.readFile(license);
    await fs.unlink(license);
    assert.notEqual(command(directory, 'scripts/release.mjs').status, 0);
    await fs.symlink(path.join(root, 'LICENSE'), license);
    assert.notEqual(command(directory, 'scripts/release.mjs').status, 0);
    await fs.unlink(license);
    await fs.writeFile(license, originalLicense);
    const configuration = path.join(directory, 'scripts/release.config.mjs');
    const originalConfiguration = await fs.readFile(configuration, 'utf8');
    await fs.writeFile(configuration, originalConfiguration.replace("slug: 'velog'", "slug: '../escape'"));
    assert.notEqual(command(directory, 'scripts/release.mjs').status, 0);
    await fs.writeFile(configuration, originalConfiguration);
    assert.deepEqual(await fs.readFile(archive), originalArchive);
    assert.equal((await fs.readdir(path.join(directory, 'release'))).filter((name) => name.startsWith('.stage-')).length, 0);
    await fs.unlink(path.join(directory, 'assets/js/admin.js'));
    const second = command(directory, 'scripts/release.mjs');
    assert.equal(second.status, 0, second.stdout + second.stderr);
    await fs.access(path.join(stage, 'assets/js/admin.js'));
    assert.deepEqual(await fs.readFile(source), originalSource);
  } finally { await fs.rm(directory, { recursive: true, force: true }); }
});

test('quality gate propagates tool startup failure', async () => {
  const directory = await fixture();
  try {
    const mock = path.join(directory, 'mock-bin');
    await fs.mkdir(mock);
    await fs.writeFile(path.join(mock, 'composer'), '#!/bin/sh\nexit 17\n', { mode: 0o755 });
    const result = spawnSync('bash', ['bin/check.sh'], { cwd: directory, encoding: 'utf8', env: { ...process.env, PATH: `${mock}:${process.env.PATH}` } });
    assert.equal(result.status, 17);
    assert.doesNotMatch(result.stdout, /Quality checks passed/);
  } finally { await fs.rm(directory, { recursive: true, force: true }); }
});


test('manifest destination cannot overwrite backend source', async () => {
  const directory = await fixture();
  try {
    const configuration = path.join(directory, 'scripts/build.config.mjs');
    await fs.writeFile(configuration, (await fs.readFile(configuration, 'utf8')).replace('assets/.generated.json', 'src/Core/Generated.php'));
    const plugin = path.join(directory, 'src/Core/Plugin.php');
    const original = await fs.readFile(plugin);
    const result = command(directory, 'scripts/build.mjs');
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /Invalid manifest destination/);
    assert.deepEqual(await fs.readFile(plugin), original);
  } finally { await fs.rm(directory, { recursive: true, force: true }); }
});

test('rejected release destination leaves no temporary staging', async () => {
  const directory = await fixture();
  try {
    await fs.mkdir(path.join(directory, 'release'));
    await fs.symlink(os.tmpdir(), path.join(directory, 'release/velog'));
    const result = command(directory, 'scripts/release.mjs');
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /Symlink rejected/);
    assert.deepEqual(await fs.readdir(path.join(directory, 'release')), ['velog']);
  } finally { await fs.rm(directory, { recursive: true, force: true }); }
});

test('W3-V2: status-derived fallback and actor conflict', async () => {
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'velog-progress-'));
  try {
    await fs.mkdir(path.join(directory, 'ai-document/tasks'), { recursive: true });
    await fs.writeFile(path.join(directory, 'ai-document/implementation-checklist.md'), '# Checklist\n## Current focus\n- Task: WF-111\n- Status: READY_FOR_REVIEW\n- Next actor: Builder\n');
    const markdown = '# WF-111: Task\n## Current handoff\n- Status: READY_FOR_REVIEW\n';
    await fs.writeFile(path.join(directory, 'ai-document/tasks/WF-111-task.md'), markdown);
    const data = await readProgress(directory);
    assert.ok(data.issues.some(i => i.includes('Checklist next actor says Builder but task expects Architect')));

    await fs.writeFile(path.join(directory, 'ai-document/implementation-checklist.md'), '# Checklist\n## Current focus\n- Task: WF-111\n- Status: READY_FOR_REVIEW\n- Next actor: Architect\n');
    const data2 = await readProgress(directory);
    assert.ok(!data2.issues.some(i => i.includes('Checklist next actor says')));

    await fs.writeFile(path.join(directory, 'ai-document/implementation-checklist.md'), '# Checklist\n## Current focus\n- Task: WF-111\n- Status: BLOCKED\n- Next actor: Builder\n');
    const markdownBlocked = '# WF-111: Task\n## Current handoff\n- Status: BLOCKED\n';
    await fs.writeFile(path.join(directory, 'ai-document/tasks/WF-111-task.md'), markdownBlocked);
    const data3 = await readProgress(directory);
    assert.equal(data3.currentFocus.owner, 'Unassigned');
    assert.ok(data3.issues.some(i => i.includes('Checklist specifies Builder but task is BLOCKED and has no explicit declaration')));

    await fs.writeFile(path.join(directory, 'ai-document/implementation-checklist.md'), '# Checklist\n## Current focus\n- Task: WF-111\n- Status: READY_FOR_REVIEW\n- Next actor: Architect\n');
    const markdownInvalid = '# WF-111: Task\n## Current handoff\n- Status: READY_FOR_REVIEW\n- Next actor: Architect22\n';
    await fs.writeFile(path.join(directory, 'ai-document/tasks/WF-111-task.md'), markdownInvalid);
    const data4 = await readProgress(directory);
    assert.ok(data4.issues.some(i => i.includes('Checklist specifies Architect but task declaration is invalid (Architect22)')));
  } finally { await fs.rm(directory, { recursive: true, force: true }); }
});
