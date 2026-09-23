/** Read-only validator. --task is a relative file path, not a task identifier. */
import path from 'node:path';
import { root } from './files.mjs';
import { validateTaskSnapshot, validateHandoffMetadata, readSignal, SIGNAL_FILE, UUID_RE } from './handoff-protocol.mjs';
const args=process.argv.slice(2), values=new Map();
try {
  for(const arg of args){const match=arg.match(/^--(task|receipt)=(.+)$/);const key=match?.[1]||(arg==='--json'?'json':null);if(!key||values.has(key))throw new Error('Invalid or duplicate argument');values.set(key,match?.[2]||true);}
  if(!values.get('task')||!values.get('json')||(values.has('receipt')&&!UUID_RE.test(values.get('receipt'))))throw new Error('Usage: --task=ai-document/tasks/<file>.md [--receipt=<uuid>] --json');
}catch(e){process.stderr.write(e.message+'\n');process.exit(2);}
try {
  let result=await validateTaskSnapshot(root,values.get('task'));
  if(values.has('receipt')){
    let signal;try{signal=await readSignal(path.join(root,SIGNAL_FILE));}catch(e){if(e.code!=='ENOENT')throw e;}
    if(signal?.handoff?.id===values.get('receipt')&&signal.handoff.task_file===values.get('task'))result=await validateHandoffMetadata(root,signal);
    else{result.valid=false;result.errors.push({code:'RECEIPT_NOT_FOUND',path:SIGNAL_FILE,field:'id/task_file',observed:signal?.handoff?.id||null,expected:values.get('receipt')});}
  }
  process.stdout.write(JSON.stringify(result,null,2)+'\n');process.exitCode=result.valid?0:1;
}catch(e){process.stderr.write(e.message+'\n');process.exitCode=2;}
