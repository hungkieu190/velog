import test from 'node:test';
import assert from 'node:assert';
import { rolePresentation } from '../../scripts/progress-view.mjs';

test('rolePresentation maps DRAFT correctly', () => {
  const focus = { status: 'DRAFT', owner: 'Architect' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: 'Architect', motion: 'drawing', label: 'Planning' });
});

test('rolePresentation maps IN_PROGRESS correctly', () => {
  const focus = { status: 'IN_PROGRESS', owner: 'Builder' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: 'Builder', motion: 'mining', label: 'Implementation in progress' });
});

test('rolePresentation maps DONE correctly', () => {
  const focus = { status: 'DONE', owner: 'Architect' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: null, motion: null, label: 'Completed' });
});

test('rolePresentation maps BLOCKED with valid declared owner', () => {
  const focus = { status: 'BLOCKED', owner: null, declaredOwner: 'Architect' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: 'Architect', motion: 'drawing', label: 'Blocked' });
});

test('rolePresentation maps missing focus correctly', () => {
  const res = rolePresentation(null);
  assert.deepEqual(res, { role: null, motion: null, label: 'No active task' });
});

test('rolePresentation detects contradiction (Architect vs Builder)', () => {
  const focus = { status: 'IN_PROGRESS', owner: 'Builder', declaredOwner: 'Architect' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: null, motion: null, label: 'State needs attention' });
});

test('rolePresentation handles malicious owner', () => {
  const focus = { status: 'DRAFT', owner: 'Architect', declaredOwner: 'Hacker"><script>' };
  const res = rolePresentation(focus);
  assert.deepEqual(res, { role: null, motion: null, label: 'State needs attention' });
});
