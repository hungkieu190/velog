import assert from 'node:assert/strict';
import test from 'node:test';

import { normalizeRole } from '../../scripts/progress-data.mjs';
import { renderProgress, rolePresentation } from '../../scripts/progress-view.mjs';

test('normalizes current roles and migration aliases', () => {
  assert.equal(normalizeRole('Backend Architect (Codex)'), 'Backend Architect');
  assert.equal(normalizeRole('Frontend Developer (Antigravity)'), 'Frontend Developer');
  assert.equal(normalizeRole('Architect'), 'Backend Architect');
  assert.equal(normalizeRole('Builder'), 'Frontend Developer');
  assert.equal(normalizeRole('Tester'), 'Tester');
  assert.equal(normalizeRole('User'), 'Tester');
  assert.equal(normalizeRole('Product Owner'), 'Tester');
  assert.equal(normalizeRole('Frontend Developer ( )'), null);
  assert.equal(normalizeRole('Hacker'), null);
});

test('uses declared backend actor for any active implementation status', () => {
  const result = rolePresentation({
    status: 'IN_PROGRESS',
    owner: 'Backend Architect',
    declaredOwner: 'Backend Architect',
    declaredOwnerRaw: 'Backend Architect',
  });
  assert.deepEqual(result, {
    role: 'Backend Architect',
    motion: 'drawing',
    label: 'Implementation in progress',
  });
});

test('uses declared frontend actor for READY and review role for READY_FOR_REVIEW', () => {
  assert.deepEqual(rolePresentation({
    status: 'READY',
    declaredOwner: 'Frontend Developer',
    declaredOwnerRaw: 'Frontend Developer',
  }), { role: 'Frontend Developer', motion: 'coding', label: 'Ready to start' });

  assert.deepEqual(rolePresentation({
    status: 'READY_FOR_REVIEW',
    declaredOwner: 'Backend Architect',
    declaredOwnerRaw: 'Backend Architect',
  }), { role: 'Backend Architect', motion: 'drawing', label: 'Review queued' });
});

test('does not infer an actor when Next actor is missing or invalid', () => {
  assert.deepEqual(rolePresentation({ status: 'READY', declaredOwner: null, declaredOwnerRaw: '' }), {
    role: null,
    motion: null,
    label: 'State needs attention',
  });
  assert.deepEqual(rolePresentation({ status: 'READY', declaredOwner: null, declaredOwnerRaw: 'Builder22' }), {
    role: null,
    motion: null,
    label: 'State needs attention',
  });
});

test('DONE has no active actor', () => {
  assert.deepEqual(rolePresentation({ status: 'DONE', declaredOwner: 'Backend Architect' }), {
    role: null,
    motion: null,
    label: 'Completed',
  });
});

test('manual acceptance assigns the tester', () => {
  assert.deepEqual(rolePresentation({
    status: 'AWAITING_MANUAL_ACCEPTANCE',
    declaredOwner: 'Tester',
    declaredOwnerRaw: 'Tester',
  }), { role: 'Tester', motion: 'testing', label: 'Manual test required' });
});

test('renders the frontend coding scene and explicit assignment metadata', () => {
  const html = renderProgress({
    currentFocus: {
      id: 'CORE-004-FE',
      title: 'CORE-004-FE: Regional settings presentation verification',
      status: 'READY',
      workstream: 'Frontend',
      owner: 'Frontend Developer',
      declaredOwner: 'Frontend Developer',
      declaredOwnerRaw: 'Frontend Developer',
      round: 'Round 1',
      nextAction: 'Implement presentation.',
    },
    summary: { done: 0, total: 1, open: 1 },
    phases: [],
    issues: [],
    tasks: [],
    openItems: [],
    lastUpdated: '2026-10-05T00:00:00.000Z',
    sources: [],
  });
  assert.match(html, /READY <span> \/ Frontend \/ Frontend Developer \/ Round 1<\/span>/);
  assert.match(html, /owner frontend-developer active coding/);
  assert.match(html, /scene frontend/);
  assert.doesNotMatch(html, /scene builder/);
});
