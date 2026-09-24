import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { fixture, cleanup, config, options, documents, write } from '../../../../tests/workflow/handoff-fixtures.mjs';
import { publishHandoff } from '../../../../scripts/publish-handoff.mjs';
import { ControllerState, pollCycle } from '../../../../scripts/handoff-controller.mjs';
const out=path.dirname(fileURLToPath(import.meta.url)),repo=path.resolve(out,'../../../..');
const results=[];
const roots=[];
let server;
try {
  const root=await fixture();roots.push(root);
  const first=await publishHandoff(options(root));
  const state=Object.assign(new ControllerState(),{mode:'dispatch'});
  await pollCycle(root,config(),state,{builder:{dispatch:async()=>({success:true,cleanupVerified:true,result:{receipt_id:first.id,task_id:'TEST-001',intake:'FAIL',outcome:'blocked',new_receipt_id:null}})}});
  const ledger=JSON.parse(await fs.readFile(path.join(root,'.cache/handoff/ledger.json'),'utf8'));
  await documents(root,{status:'READY',intent:'correct_metadata',actor:'Architect',recipient:'Builder'});
  let correction;
  try {await publishHandoff(options(root,{intent:'correct_metadata',fromRole:'builder',toRole:'architect',rejectedReceiptId:first.id}));correction={published:true};}
  catch(e){correction={published:false,error:e.message};}
  results.push({name:'agent-intake-failure-not-journaled',journalIntake:ledger[0].intake,agentIntake:ledger[0].result.result.intake,status:ledger[0].status,correction});

  const root2=await fixture();roots.push(root2);
  await publishHandoff(options(root2));await fs.appendFile(path.join(root2,'ai-document/tasks/TEST-001-fixture.md'),'\nChanged.\n');
  const s=Object.assign(new ControllerState(),{mode:'dispatch'}),c=config(),adapters={builder:{dispatch:async()=>{throw Error('Invalid intake must not dispatch');}}};
  await pollCycle(root2,c,s,adapters);const firstBlock=s.blocked;
  await pollCycle(root2,c,s,adapters);
  results.push({name:'rejected-receipt-next-poll',firstBlock,secondBlock:s.blocked,pending:s.pendingReceipt});

  const webRoot=await fixture();roots.push(webRoot);
  await fs.cp(path.join(repo,'scripts'),path.join(webRoot,'scripts'),{recursive:true});
  await fs.mkdir(path.join(webRoot,'src/css'),{recursive:true});
  await fs.copyFile(path.join(repo,'src/css/progress.css'),path.join(webRoot,'src/css/progress.css'));
  await publishHandoff(options(webRoot));await write(webRoot,'.cache/handoff/config.json',JSON.stringify(config()));
  const finder=net.createServer();finder.listen(0,'127.0.0.1');await once(finder,'listening');const port=finder.address().port;await new Promise(r=>finder.close(r));
  server=spawn(process.execPath,['scripts/progress-dashboard.mjs',`--port=${port}`],{cwd:webRoot,stdio:['ignore','pipe','pipe']});
  let log='';server.stdout.on('data',d=>log+=d);server.stderr.on('data',d=>log+=d);
  const closed=once(server,'close');
  const url=`http://127.0.0.1:${port}`;
  let monitor;
  for(let n=0;n<100;n++) {try {monitor=await (await fetch(url+'/api/handoff')).json();if(monitor.blocked)break;}catch{}await delay(20);}
  const get=await fetch(url+'/'),head=await fetch(url+'/',{method:'HEAD'}),post=await fetch(url+'/',{method:'POST'});
  const html=await get.text();
  let ledgerExists=true;try{await fs.access(path.join(webRoot,'.cache/handoff/ledger.json'));}catch{ledgerExists=false;}
  server.kill('SIGTERM');const [code,signal]=await closed;server=null;
  results.push({name:'actual-dashboard-entrypoint',get:get.status,head:head.status,post:post.status,monitor,blockerVisibleInHtml:html.includes('Adapter for builder'),ledgerExists,exit:code,signal});
  await fs.writeFile(path.join(out,'http-server.log'),log);
} finally {
  if(server)server.kill('SIGKILL');
  for(const root of roots)await cleanup(root);
  await fs.writeFile(path.join(out,'integration-results.json'),JSON.stringify({results,cleanup:{fixtureDirectoriesRemoved:roots.length}},null,2)+'\n');
  process.stdout.write(JSON.stringify(results,null,2)+'\n');
}
