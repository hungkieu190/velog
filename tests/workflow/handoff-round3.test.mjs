import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fixture, cleanup, options, config, taskPath, write } from './handoff-fixtures.mjs';
import { publishHandoff } from '../../scripts/publish-handoff.mjs';
import { validateHandoffMetadata } from '../../scripts/handoff-protocol.mjs';
import { ControllerState, pollCycle, startMonitor } from '../../scripts/handoff-controller.mjs';
const dispatchState = () => Object.assign(new ControllerState(), { mode: 'dispatch' });
const successful = { dispatch: async () => ({ success: true, cleanupVerified: true, result: { intake: 'PASS', outcome: 'needs_user', new_receipt_id: null } }) };
test('R1 rejects conflicting revision and checklist before publication', async () => {
 const root=await fixture();try { await write(root, taskPath, (await fs.readFile(path.join(root,taskPath),'utf8')).replace('Plan revision: 1','Plan revision: 99'));await assert.rejects(publishHandoff(options(root)),/revision/i); } finally {await cleanup(root);}
});
test('R2 unacknowledged receipt cannot be overwritten', async () => {
 const root=await fixture();try { const first=await publishHandoff(options(root));await assert.rejects(publishHandoff(options(root)),/acknowledg/i);assert.equal(JSON.parse(await fs.readFile(path.join(root,'ai-document/handoff-signal.json'))).handoff.id,first.id); }finally{await cleanup(root);}
});
test('R3 repaired adapter retains the same receipt; tampering is detected after dispatch', async () => {
 const root=await fixture();try { const p=await publishHandoff(options(root)),s=dispatchState(),c=config();await pollCycle(root,c,s,{});let launches=0;await pollCycle(root,c,s,{builder:{dispatch:async()=>{launches++;return successful.dispatch();}}});assert.equal(launches,1);p.signal.handoff.round=99;await write(root,'ai-document/handoff-signal.json',JSON.stringify(p.signal));await pollCycle(root,c,s,{});assert.match(s.errors.join(' '),/tamper/i); }finally{await cleanup(root);}
});
test('R2 monitor returns a cancellation handle during initial dispatch', async () => {
 const root=await fixture();let monitor;try {await publishHandoff(options(root));await write(root,'.cache/handoff/config.json',JSON.stringify(config()));let started;const begun=new Promise(r=>started=r);monitor=await startMonitor(root,{interval:10000,adapters:{builder:{dispatch:async({signal})=>{started();await new Promise(r=>signal.addEventListener('abort',r,{once:true}));return {success:false,cleanupVerified:true};}}}});await begun;await monitor.stop();assert.equal(monitor.state.currentRun,null);}finally{if(monitor)await monitor.stop();await cleanup(root);}
});
