import test from 'node:test';
import assert from 'node:assert';
import { rolePresentation } from '../../scripts/progress-view.mjs';
import { normalizeRole } from '../../scripts/progress-data.mjs';

test('normalizeRole normalizes correct roles', () => {
  assert.equal(normalizeRole('Architect (Codex)'), 'Architect');
  assert.equal(normalizeRole('Builder (Antigravity)'), 'Builder');
  assert.equal(normalizeRole('User'), 'User');
  assert.equal(normalizeRole('Architect'), 'Architect');
  assert.equal(normalizeRole('Hacker><script>'), null);
  assert.equal(normalizeRole(' Builder '), 'Builder');
  assert.equal(normalizeRole('Builder ( )'), null);
  assert.equal(normalizeRole('Builder ()'), null);
  assert.equal(normalizeRole('Unknown'), null);
});

test('W3-V1: rolePresentation maps DRAFT correctly', () => {
  const focus = { status: 'DRAFT', owner: 'Architect' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: 'Architect', motion: 'drawing', label: 'Planning' });
});

test('W3-V1: rolePresentation maps READY correctly', () => {
  const focus = { status: 'READY', owner: 'Builder', declaredOwner: 'Builder', declaredOwnerRaw: 'Builder (Antigravity)' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: 'Builder', motion: 'mining', label: 'Ready to start' });
});

test('W3-V1: rolePresentation maps READY_FOR_REVIEW correctly', () => {
  const focus = { status: 'READY_FOR_REVIEW', owner: 'Architect', declaredOwner: 'Architect', declaredOwnerRaw: 'Architect (Codex)' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: 'Architect', motion: 'drawing', label: 'Review queued' });
});

test('W3-V1: rolePresentation maps DONE correctly', () => {
  const focus = { status: 'DONE', owner: 'Architect' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: null, motion: null, label: 'Completed' });
});

test('W3-V1: rolePresentation maps BLOCKED with valid declared owner', () => {
  const focus = { status: 'BLOCKED', owner: null, declaredOwner: 'User', declaredOwnerRaw: 'User' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: 'User', motion: null, label: 'Blocked' });
});

test('rolePresentation maps missing focus correctly', () => {
  const res = rolePresentation(null);
  assert.deepEqual(res, { role: null, motion: null, label: 'No active task' });
});

test('W3-V2: rolePresentation detects contradiction (READY_FOR_REVIEW vs Builder)', () => {
  const focus = { status: 'READY_FOR_REVIEW', owner: 'Architect', declaredOwner: 'Builder', declaredOwnerRaw: 'Builder' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: null, motion: null, label: 'State needs attention' });
});

test('W3-V2: rolePresentation handles malicious owner', () => {
  const focus = { status: 'DRAFT', owner: 'Architect', declaredOwner: null, declaredOwnerRaw: 'Hacker"><script>' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: null, motion: null, label: 'State needs attention' });
});

test('W3-V2: rolePresentation handles unknown status', () => {
  const focus = { status: 'UNKNOWN', owner: 'Builder', declaredOwner: 'Builder', declaredOwnerRaw: 'Builder' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: null, motion: null, label: 'State needs attention' });
});
