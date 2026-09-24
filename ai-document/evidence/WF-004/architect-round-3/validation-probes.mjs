import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { fixture, cleanup, options, documents, taskPath } from '../../../../tests/workflow/handoff-fixtures.mjs';
import { publishHandoff } from '../../../../scripts/publish-handoff.mjs';
import { sha256 } from '../../../../scripts/handoff-protocol.mjs';
const out=path.dirname(fileURLToPath(import.meta.url)),repo=path.resolve(out,'../../../..');
const root=await fixture(),results=[];
try {
 await fs.cp(path.join(repo,'scripts'),path.join(root,'scripts'),{recursive:true});
 const cases=[['valid-unpublished',[`--task=${taskPath}`,'--json'],0],['missing-task',['--task=ai-document/tasks/MISSING.md','--json'],1],['missing-receipt',[`--task=${taskPath}`,`--receipt=${randomUUID()}`,'--json'],1],['bad-argument',['--bogus'],2]];
 for(const [name,args,expected]of cases){const r=spawnSync(process.execPath,['scripts/validate-handoff.mjs',...args],{cwd:root,encoding:'utf8',timeout:5000});results.push({name,args,expected,exit:r.status,stdout:r.stdout,stderr:r.stderr,pass:r.status===expected});}
 const first=await publishHandoff(options(root));
 const r=spawnSync(process.execPath,['scripts/validate-handoff.mjs',`--task=${taskPath}`,`--receipt=${first.id}`,'--json'],{cwd:root,encoding:'utf8',timeout:5000});results.push({name:'valid-published',exit:r.status,expected:0,pass:r.status===0,stdout:r.stdout});
 for(const kind of ['revision','checklist','newest-prompt','unacknowledged']){
  await documents(root);
  await fs.writeFile(path.join(root,'ai-document/handoff-signal.json'),JSON.stringify(kind==='unacknowledged'?first.signal:{schema_version:1,handoff:null}));
  const before=sha256(await fs.readFile(path.join(root,'ai-document/handoff-signal.json')));
  if(kind==='revision'){const p=path.join(root,taskPath);await fs.writeFile(p,(await fs.readFile(p,'utf8')).replace('Plan revision: 1','Plan revision: 99'));}
  if(kind==='checklist'){const p=path.join(root,'ai-document/implementation-checklist.md');await fs.writeFile(p,(await fs.readFile(p,'utf8')).replaceAll('READY','DONE'));}
  if(kind==='newest-prompt')await fs.appendFile(path.join(root,taskPath),'\n### Chat handoff prompt (invalid)\n\n```text\nStatus: READY\n```\n');
  let error=null;try{await publishHandoff(options(root));}catch(e){error=e.message;}
  const after=sha256(await fs.readFile(path.join(root,'ai-document/handoff-signal.json')));
  results.push({name:kind,error,signalUnchanged:before===after,pass:Boolean(error)&&before===after&&({revision:/plan_revision/,checklist:/CURRENT_FOCUS|CHECKLIST/,'newest-prompt':/PROMPT/,unacknowledged:/acknowledg/}[kind]).test(error)});
 }
}finally{await cleanup(root);await fs.writeFile(path.join(out,'validation-results.json'),JSON.stringify({results,fixtureRemoved:true},null,2)+'\n');}
process.stdout.write(JSON.stringify(results.map(({stdout,stderr,...r})=>r),null,2)+'\n');
