import { test } from 'node:test';
import assert from 'node:assert/strict';
import { completionEvent } from '../../scripts/progress-client.mjs';

test('completion handoffs identify Builder and Architect from the recipient', () => {
  const builder = completionEvent({
    id: 'DATA-001',
    status: 'READY_FOR_REVIEW',
    latestPrompt: 'Status: READY_FOR_REVIEW\nRecipient: Architect\nIntent: review',
  });
  assert.equal(builder.actor, 'Builder');
  assert.match(builder.text, /Builder finished DATA-001/);

  const architect = completionEvent({
    id: 'DATA-001',
    status: 'CHANGES_REQUESTED',
    latestPrompt: 'Status: CHANGES_REQUESTED\nRecipient: Builder\nIntent: work',
  });
  assert.equal(architect.actor, 'Architect');
  assert.notEqual(architect.key, builder.key);
});

test('completion event rejects stale and unstructured prompts', () => {
  assert.equal(completionEvent(null), null);
  assert.equal(completionEvent({ id: 'DATA-001', status: 'READY_FOR_REVIEW', latestPrompt: 'Status: IN_PROGRESS\nRecipient: Architect' }), null);
  assert.equal(completionEvent({ id: 'DATA-001', status: 'READY_FOR_REVIEW', latestPrompt: 'Recipient: Architect' }), null);
});
