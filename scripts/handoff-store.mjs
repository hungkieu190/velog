/** Durable local journal. No silent recovery of malformed or uncertain state. */
import fs from 'node:fs/promises';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import { safePath } from './files.mjs';
import { isObject, UUID_RE, SHA256_RE, VALID_ROLES } from './handoff-protocol.mjs';
export const LEDGER_FILE='.cache/handoff/ledger.json';
export async function readJson(root,file,missing=null) {
  const absolute=await safePath(root,file);
  try {if(!(await fs.lstat(absolute)).isFile())throw new Error(`${file} must be regular`);return JSON.parse(await fs.readFile(absolute,'utf8'));}
  catch(e){if(e.code==='ENOENT')return missing;throw new Error(`Invalid ${file}: ${e.message}`);}
}
export async function atomicJson(root,file,value) {
  const absolute=await safePath(root,file);await fs.mkdir(path.dirname(absolute),{recursive:true});
  const tmp=absolute+'.'+randomUUID()+'.tmp';let handle;
  try {handle=await fs.open(tmp,'wx',0o600);await handle.writeFile(JSON.stringify(value,null,2)+'\n');await handle.sync();await handle.close();handle=null;await fs.rename(tmp,absolute);const directory=await fs.open(path.dirname(absolute),'r');try{await directory.sync();}finally{await directory.close();}}
  finally {if(handle)await handle.close();await fs.rm(tmp,{force:true});}
}
export async function acquireLock(root,file) {
  const absolute=await safePath(root,file);await fs.mkdir(path.dirname(absolute),{recursive:true});let handle;
  try {handle=await fs.open(absolute,'wx',0o600);await handle.writeFile(JSON.stringify({pid:process.pid,nonce:randomUUID(),startedAt:new Date().toISOString()}));await handle.sync();return {handle,lockPath:absolute,ino:(await handle.stat()).ino};}
  catch(e){if(handle){await handle.close();await fs.rm(absolute,{force:true});}if(e.code==='EEXIST')return null;throw e;}
}
export async function releaseLock(lock) {
  if(!lock)return;
  try {if((await fs.lstat(lock.lockPath)).ino!==lock.ino)throw new Error('Owned lock was replaced; refusing removal');await fs.unlink(lock.lockPath);}finally{await lock.handle.close();}
}
export async function readLedger(root) {
  const ledger=await readJson(root,LEDGER_FILE,[]);
  if(!Array.isArray(ledger))throw new Error('Invalid ledger: expected array');
  const receipts=new Set(),runs=new Set();
  for(const e of ledger){
    if(!isObject(e)||!UUID_RE.test(e.receiptId||'')||!SHA256_RE.test(e.contentHash||'')||!UUID_RE.test(e.runId||'')||!UUID_RE.test(e.activationId||'')||!VALID_ROLES.has(e.recipientRole)||!UUID_RE.test(e.recipientSession||'')||!['claimed','running','completed','failed','needs_attention','rejected'].includes(e.status)||!['work','correct_metadata'].includes(e.intent)||!isObject(e.handoff)||e.handoff.id!==e.receiptId||e.handoff.to_role!==e.recipientRole||!['PASS','FAIL'].includes(e.intake)||typeof e.acknowledged!=='boolean'||typeof e.cleanupVerified!=='boolean'||!Number.isFinite(Date.parse(e.startedAt))||(e.pid!==null&&(!Number.isInteger(e.pid)||e.pid<=0)))throw new Error('Invalid ledger entry schema; manual recovery required');
    if(receipts.has(e.receiptId)||runs.has(e.runId))throw new Error('Duplicate ledger identity');receipts.add(e.receiptId);runs.add(e.runId);
  }
  return ledger;
}
export const writeLedger=(root,ledger)=>atomicJson(root,LEDGER_FILE,ledger);
export function validateConfig(c) {
  if(!isObject(c)||typeof c.enabled!=='boolean')throw new Error('Config.enabled must be boolean');
  if(!c.enabled)return c;
  if(!UUID_RE.test(c.activationId||''))throw new Error('Config.activationId must be an explicit user activation UUID');
  if(typeof c.priorityTask!=='string'||!c.priorityTask)throw new Error('Config.priorityTask is required');
  if(!isObject(c.adapters)||Object.keys(c.adapters).some(k=>!['codex','antigravity'].includes(k)))throw new Error('Config.adapters uses codex/antigravity client keys');
  if(c.runDeadline!==undefined&&(!Number.isInteger(c.runDeadline)||c.runDeadline<1||c.runDeadline>900000))throw new Error('Config.runDeadline must be 1..900000 ms');
  for(const a of Object.values(c.adapters)){
    if(!isObject(a)||!path.isAbsolute(a.executable||'')||!UUID_RE.test(a.sessionId||''))throw new Error('Adapter requires absolute executable and pinned session UUID');
    if(a.sandbox!==undefined&&!['read-only','workspace-write'].includes(a.sandbox))throw new Error('Unsupported adapter sandbox');
  }
  return c;
}
