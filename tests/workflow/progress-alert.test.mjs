import assert from 'node:assert/strict';
import test from 'node:test';

import { completionEvent } from '../../scripts/progress-client.mjs';

test('handoff alerts use the new role names', () => {
  const frontend = completionEvent({
    id: 'CORE-004-FE',
    status: 'READY_FOR_REVIEW',
    latestPrompt: 'Status: READY_FOR_REVIEW\nRecipient: Backend Architect\nIntent: review',
  });
  assert.equal(frontend.actor, 'Frontend Developer');
  assert.match(frontend.text, /Frontend Developer finished CORE-004-FE/);

  const backend = completionEvent({
    id: 'CORE-004-FE',
    status: 'CHANGES_REQUESTED',
    latestPrompt: 'Status: CHANGES_REQUESTED\nRecipient: Frontend Developer\nIntent: work',
  });
  assert.equal(backend.actor, 'Backend Architect');
  assert.match(backend.text, /Backend Architect finished CORE-004-FE/);
});

test('handoff alerts reject stale and old-role prompts', () => {
  assert.equal(completionEvent({ id: 'CORE-004-FE', status: 'READY_FOR_REVIEW', latestPrompt: 'Status: IN_PROGRESS\nRecipient: Backend Architect' }), null);
  assert.equal(completionEvent({ id: 'CORE-004-FE', status: 'READY_FOR_REVIEW', latestPrompt: 'Status: READY_FOR_REVIEW\nRecipient: Architect' }), null);
});

test('manual test handoff targets Tester', () => {
  const event = completionEvent({
    id: 'CUST-001-UAT',
    status: 'AWAITING_MANUAL_ACCEPTANCE',
    latestPrompt: 'Status: AWAITING_MANUAL_ACCEPTANCE\nRecipient: Tester\nIntent: accept',
  });
  assert.equal(event.actor, 'Backend Architect');
  assert.match(event.text, /Tester is next/);
});
