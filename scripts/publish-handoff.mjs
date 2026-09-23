/** Publish the final immutable snapshot only after validation and intake acknowledgement. */
import fs from 'node:fs/promises';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import { root } from './files.mjs';
import { validateTaskSnapshot, validateHandoffMetadata, formatErrors, readSignal, emptySignal, sha256, SIGNAL_FILE, MAX_SIGNAL_BYTES } from './handoff-protocol.mjs';
import { acquireLock, releaseLock, atomicJson, readLedger } from './handoff-store.mjs';
export { SIGNAL_FILE };
export const LOCK_FILE='.cache/handoff-publish.lock';
export async function publishHandoff(options) {
  const projectRoot=options.projectRoot||root;
  const lock=await acquireLock(projectRoot,LOCK_FILE);if(!lock)throw new Error('Publication lock held');
  try {
    let current;
    try{current=await readSignal(path.join(projectRoot,SIGNAL_FILE));}catch(e){if(e.code!=='ENOENT')throw e;current=emptySignal();}
    const snapshot=await validateTaskSnapshot(projectRoot,options.taskFile);
    if(!snapshot.valid)throw new Error(formatErrors(snapshot));
    if(options.prompt!==undefined&&options.prompt!==snapshot.prompt)throw new Error('Prompt differs from exact task bytes');
    let parentRunId=options.parentRunId;
    if(current.handoff){
      const ledger=await readLedger(projectRoot);
      const acknowledged=ledger.find(e=>e.receiptId===current.handoff.id&&e.contentHash===sha256(JSON.stringify(current))&&e.acknowledged);
      if(!acknowledged)throw new Error('Existing receipt has no durable intake acknowledgement');
      if(acknowledged.recipientRole!==options.fromRole||acknowledged.recipientSession!==options.senderSessionId)throw new Error('Acknowledgement does not authorize this sender/session');
      if(options.intent==='correct_metadata'){
        if(acknowledged.intake!=='FAIL'||options.rejectedReceiptId!==current.handoff.id||options.toRole!==current.handoff.from_role)throw new Error('Correction must return rejected receipt to its original sender');
      }else if(!['running','completed'].includes(acknowledged.status)||acknowledged.intake!=='PASS')throw new Error('Acknowledged run does not authorize successor work');
      if(parentRunId&&parentRunId!==acknowledged.runId)throw new Error('Parent run mismatch');
      parentRunId=acknowledged.runId;
    }else if(parentRunId||options.rejectedReceiptId)throw new Error('First publication cannot claim a parent/rejected receipt');
    const id=randomUUID();
    const h={id,previous_id:current.handoff?.id||null,task_id:options.taskId,task_file:options.taskFile,plan_revision:options.planRevision,round:options.round,task_status:options.taskStatus,intent:options.intent,from_role:options.fromRole,to_role:options.toRole,sender_session_id:options.senderSessionId,published_at:new Date().toISOString().replace(/\.\d+Z$/,'Z'),prompt_sha256:sha256(snapshot.prompt),documents:snapshot.snapshot};
    if(parentRunId)h.parent_run_id=parentRunId;
    if(options.rejectedReceiptId)h.rejected_receipt_id=options.rejectedReceiptId;
    const signal={schema_version:1,handoff:h};
    const final=await validateHandoffMetadata(projectRoot,signal);if(!final.valid)throw new Error(formatErrors(final));
    if(Buffer.byteLength(JSON.stringify(signal,null,2)+'\n')>MAX_SIGNAL_BYTES)throw new Error('Signal size limit exceeded');
    await atomicJson(projectRoot,SIGNAL_FILE,signal);
    return {id,signal};
  }finally{await releaseLock(lock);}
}
export async function initSignalFile(projectRoot=root) {
  const lock=await acquireLock(projectRoot,LOCK_FILE);if(!lock)throw new Error('Publication lock held');
  try{try{await readSignal(path.join(projectRoot,SIGNAL_FILE));}catch(e){if(e.code!=='ENOENT')throw e;await atomicJson(projectRoot,SIGNAL_FILE,emptySignal());}}finally{await releaseLock(lock);}
}
