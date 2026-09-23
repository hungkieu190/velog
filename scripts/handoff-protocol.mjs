/** Read-only task snapshot and immutable receipt validation. */
import { createHash } from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { safePath } from './files.mjs';
import { extractLatestPrompt, parseTask, parseChecklist, normalizeRole } from './progress-data.mjs';
export const SCHEMA_VERSION = 1;
export const MAX_SIGNAL_BYTES = 256 * 1024;
export const MAX_PROMPT_BYTES = 128 * 1024;
export const MAX_DOCUMENTS = 64;
export const VALID_STATUSES = new Set(['DRAFT','READY','IN_PROGRESS','BLOCKED','READY_FOR_REVIEW','CHANGES_REQUESTED','AWAITING_MANUAL_ACCEPTANCE','DONE']);
export const DISPATCHABLE_STATUSES = new Set(['READY','CHANGES_REQUESTED','READY_FOR_REVIEW']);
export const VALID_INTENTS = new Set(['work','correct_metadata']);
export const VALID_ROLES = new Set(['architect','builder']);
export const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
export const SHA256_RE = /^[0-9a-f]{64}$/i;
export const SIGNAL_FILE = 'ai-document/handoff-signal.json';
const CHECKLIST = 'ai-document/implementation-checklist.md', INDEX = 'ai-document/README.md', ROLES = 'ai-document/agent-roles.json';
export const sha256 = data => createHash('sha256').update(data).digest('hex');
export const emptySignal = () => ({schema_version:SCHEMA_VERSION,handoff:null});
export const isObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);
export function routeSignal(status, fromRole) {
  if (status === 'READY_FOR_REVIEW' && fromRole === 'builder') return 'architect';
  if (['READY','CHANGES_REQUESTED'].includes(status) && fromRole === 'architect') return 'builder';
  return null;
}
export async function validateDocPath(root, relative) {
  if (typeof relative !== 'string' || !/^(?:ai-document\/[^\\]+\.(?:md|json)|AGENTS\.md|rules\/[^\\]+\.md)$/.test(relative)) throw new Error(`Unsafe document path: ${relative}`);
  const absolute = await safePath(root, relative);
  if (!(await fs.lstat(absolute)).isFile()) throw new Error(`Document must be a regular file: ${relative}`);
  return absolute;
}
export async function readRoleMapping(root) {
  const roles = JSON.parse(await fs.readFile(await validateDocPath(root, ROLES),'utf8'));
  if (roles.schema_version !== 1 || roles.assignments?.codex?.role !== 'architect' || roles.assignments?.antigravity?.role !== 'builder') throw new Error('Invalid role mapping: expected codex=architect, antigravity=builder');
  return roles;
}
export function validateEnvelope(signal) {
  const errors=[];
  if (!isObject(signal)) return ['Signal must be an object'];
  if (signal.schema_version !== 1) errors.push('schema_version must be 1');
  if (Object.keys(signal).some(k=>!['schema_version','handoff'].includes(k))) errors.push('Unknown signal field');
  const h=signal.handoff;
  if (h===null) return errors;
  if (!isObject(h)) return [...errors,'handoff must be an object or null'];
  const fields=['id','previous_id','task_id','task_file','plan_revision','round','task_status','intent','from_role','to_role','sender_session_id','published_at','prompt_sha256','documents','parent_run_id','rejected_receipt_id'];
  for (const key of Object.keys(h)) if (!fields.includes(key)) errors.push(`Unknown handoff.${key}`);
  for (const key of ['id','task_id','task_file','task_status','intent','from_role','to_role','sender_session_id','published_at','prompt_sha256']) if (typeof h[key]!=='string'||!h[key]) errors.push(`handoff.${key} must be a non-empty string`);
  for (const key of ['id','sender_session_id']) if (!UUID_RE.test(h[key]||'')) errors.push(`handoff.${key} must be a UUID`);
  for (const key of ['previous_id','parent_run_id','rejected_receipt_id']) if ((key==='previous_id'||h[key]!==undefined)&&h[key]!==null&&!UUID_RE.test(h[key]||'')) errors.push(`handoff.${key} must be a UUID or null`);
  for (const key of ['plan_revision','round']) if (!Number.isSafeInteger(h[key])||h[key]<1) errors.push(`handoff.${key} must be a positive integer`);
  if (!VALID_STATUSES.has(h.task_status)) errors.push('Invalid handoff.task_status');
  if (!VALID_INTENTS.has(h.intent)) errors.push('Invalid handoff.intent');
  for (const key of ['from_role','to_role']) if (!VALID_ROLES.has(h[key])) errors.push(`Invalid handoff.${key}`);
  if (h.from_role===h.to_role) errors.push('handoff.from_role and to_role must differ');
  const date=new Date(h.published_at);
  if (!/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/.test(h.published_at||'')||!Number.isFinite(date.getTime())||date.toISOString().replace('.000Z','Z')!==h.published_at) errors.push('Invalid handoff.published_at UTC date');
  if (!SHA256_RE.test(h.prompt_sha256||'')) errors.push('handoff.prompt_sha256 must be SHA-256');
  if (h.intent==='work'&&DISPATCHABLE_STATUSES.has(h.task_status)&&routeSignal(h.task_status,h.from_role)!==h.to_role) errors.push('Routing does not match work authority');
  if (h.intent==='correct_metadata'&&(!UUID_RE.test(h.rejected_receipt_id||'')||h.previous_id!==h.rejected_receipt_id)) errors.push('Correction requires rejected_receipt_id equal to previous_id');
  if (!Array.isArray(h.documents)||h.documents.length>MAX_DOCUMENTS) errors.push(`documents must be an array of at most ${MAX_DOCUMENTS}`);
  else {
    const seen=new Set();
    for (const d of h.documents) {
      if (!isObject(d)||typeof d.path!=='string'||!SHA256_RE.test(d.sha256||'')) {errors.push('Invalid document path/SHA-256');continue;}
      if (seen.has(d.path)) errors.push(`Duplicate document: ${d.path}`);
      seen.add(d.path);
    }
    for (const p of [h.task_file,CHECKLIST,INDEX,ROLES]) if (!seen.has(p)) errors.push(`documents must include ${p===h.task_file?'task_file: ':''}${p}`);
  }
  if (Buffer.byteLength(JSON.stringify(signal))>MAX_SIGNAL_BYTES) errors.push('Signal size limit exceeded');
  return errors;
}
export async function readSignal(signalPath) {
  const root=path.dirname(path.dirname(signalPath));
  const absolute=await safePath(root,path.relative(root,signalPath));
  const stat=await fs.lstat(absolute);
  if (!stat.isFile()||stat.size>MAX_SIGNAL_BYTES) throw new Error('Signal must be a regular bounded file');
  return JSON.parse(await fs.readFile(absolute,'utf8'));
}
export async function validateDocumentHashes(root, documents) {
  const errors=[];
  for (const doc of documents) try {
    const bytes=await fs.readFile(await validateDocPath(root,doc.path));
    if (sha256(bytes)!==doc.sha256) errors.push(`Hash mismatch for ${doc.path}`);
  } catch(e) {errors.push(`Cannot validate ${doc.path}: ${e.message}`);}
  return {valid:!errors.length,errors};
}
const currentSection=(text, heading)=>text.split(`## ${heading}\n`)[1]?.split(/\n## /)[0]||'';
const field=(text,key)=>text.split('\n').find(l=>l.startsWith(`- ${key}: `))?.slice(key.length+4).trim()||'';
const role=value=>normalizeRole(value)?.toLowerCase()||null;
function error(result,code,file,key,observed,expected) {result.errors.push({code,path:file,field:key,observed:observed??null,expected});}
export const formatErrors = report => report.errors.map(e=>typeof e==='string'?e:`${e.code} ${e.path} ${e.field}: ${JSON.stringify(e.observed)}; expected ${e.expected}`).join('; ');
/** Validate the requested Markdown snapshot even before a receipt is published. */
export async function validateTaskSnapshot(root, taskFile) {
  const r={valid:false,task_id:null,receipt_id:null,snapshot:[],errors:[],limitations:[],prompt:'',metadata:null};
  const contents=new Map();
  async function read(file) {
    if(contents.has(file))return contents.get(file);
    try {const bytes=await fs.readFile(await validateDocPath(root,file));r.snapshot.push({path:file,sha256:sha256(bytes)});const text=bytes.toString('utf8');contents.set(file,text);return text;}
    catch(e){error(r,'DOCUMENT',file,'file',e.message,'readable safe regular file');return '';}
  }
  if (!/^ai-document\/tasks\/[^/]+\.md$/.test(taskFile||'')) {error(r,'TASK_PATH',taskFile,'task',taskFile,'ai-document/tasks/<name>.md');return r;}
  const task=await read(taskFile), checklist=await read(CHECKLIST), index=await read(INDEX);await read(ROLES);
  try {await readRoleMapping(root);}catch(e){error(r,'ROLE_MAPPING',ROLES,'assignments',e.message,'codex=architect, antigravity=builder');}
  const current=currentSection(task,'Current handoff'), parsed=parseTask(task,taskFile);
  r.task_id=parsed.id;
  const meta={status:parsed.status,revision:Number(field(current,'Plan revision')),round:Number(field(current,'Implementation round')),actor:role(field(current,'Handoff actor')),recipient:role(field(current,'Handoff recipient')),state:field(current,'Handoff state'),intent:field(current,'Handoff intent')};r.metadata=meta;
  for(const [name,value] of [['Plan revision',meta.revision],['Implementation round',meta.round]])if(!Number.isSafeInteger(value)||value<1)error(r,'NUMBER',taskFile,name,field(current,name),'positive integer');
  if(!VALID_STATUSES.has(meta.status))error(r,'STATUS',taskFile,'Status',meta.status,'known task status');
  for(const key of ['actor','recipient'])if(!meta[key])error(r,'ROLE',taskFile,key,meta[key],'Architect or Builder');
  if(!['preparing','published','correction_required','accepted'].includes(meta.state))error(r,'HANDOFF_STATE',taskFile,'Handoff state',meta.state,'known handoff state');
  if(!VALID_INTENTS.has(meta.intent))error(r,'INTENT',taskFile,'Handoff intent',meta.intent,'work or correct_metadata');
  const expectedRecipient=meta.status==='READY_FOR_REVIEW'?'architect':['READY','CHANGES_REQUESTED'].includes(meta.status)?'builder':null;
  if(expectedRecipient&&meta.recipient!==expectedRecipient)error(r,'RECIPIENT',taskFile,'Handoff recipient',meta.recipient,expectedRecipient);
  if(role(field(current,'Next actor'))!==meta.actor)error(r,'ACTOR',taskFile,'Next actor',field(current,'Next actor'),meta.actor);
  if(meta.intent==='work'&&meta.actor!==meta.recipient)error(r,'ACTOR',taskFile,'Handoff actor',meta.actor,meta.recipient);
  if(meta.intent==='correct_metadata'&&(meta.state!=='correction_required'||meta.actor===meta.recipient))error(r,'CORRECTION',taskFile,'Handoff state/actor',meta.state,'correction_required with actor different from work recipient');
  if(field(current,'Blueprint readiness')!=='PASS')error(r,'BLUEPRINT',taskFile,'Blueprint readiness',field(current,'Blueprint readiness'),'PASS');
  for(const key of ['Architect session reference','Builder session reference','Implementation contributors and reviewer independence check','User approval reference and approved scope','Latest report','Next actor and exact next action'])if(!field(current,key))error(r,'REQUIRED',taskFile,key,'','non-empty current field');
  if(field(current,'Architect session reference')===field(current,'Builder session reference'))error(r,'INDEPENDENCE',taskFile,'session references','same references','distinct contributor and reviewer references');
  const evidence=field(current,'Evidence').split(',').map(s=>s.trim()).filter(Boolean);
  if(!evidence.length)error(r,'EVIDENCE',taskFile,'Evidence','','comma-separated evidence paths');
  for(const file of evidence)await read(file);
  const extraction=extractLatestPrompt(task);r.prompt=extraction.prompt;
  if(!extraction.valid)error(r,'PROMPT',taskFile,'latest prompt',extraction.error,'canonical latest nonempty text fence');
  else {
    if(Buffer.byteLength(r.prompt)>MAX_PROMPT_BYTES)error(r,'PROMPT_SIZE',taskFile,'prompt',Buffer.byteLength(r.prompt),`<=${MAX_PROMPT_BYTES}`);
    const lines=r.prompt.split('\n');
    if(lines[0]!==`Status: ${meta.status}`)error(r,'PROMPT_STATUS',taskFile,'prompt first line',lines[0],`Status: ${meta.status}`);
    for(const [key,value] of [['Recipient',meta.actor],['Intent',meta.intent]]) {const declared=lines.find(l=>l.startsWith(key+': '))?.slice(key.length+2);if((key==='Recipient'?role(declared):declared)!==value)error(r,'PROMPT_FIELD',taskFile,key,declared,value);}
  }
  for(const [file,text] of [[CHECKLIST,checklist],[INDEX,index]]) {
    const section=currentSection(text,'Current focus');
    for(const [key,value] of [['Task',r.task_id],['Status',meta.status],['Next actor',meta.actor]]) {const actual=field(section,key);if((key==='Next actor'?role(actual):actual)!==value)error(r,'CURRENT_FOCUS',file,key,actual,value);}
  }
  const items=parseChecklist(checklist).items.filter(i=>i.taskId===r.task_id);
  if(!items.length)error(r,'CHECKLIST',CHECKLIST,'items',0,'at least one task criterion');
  for(const item of items)if(item.status!==meta.status||item.done!==(meta.status==='DONE'))error(r,'CHECKLIST',CHECKLIST,'criterion',item.text,`${meta.status}, checkbox matches acceptance`);
  if(task.includes('NOT VERIFIED'))r.limitations.push('Task declares NOT VERIFIED checks; validator success is not acceptance.');
  r.limitations.push('Prose accuracy and reviewer independence require human/independent review; labels are not authentication.');
  r.valid=!r.errors.length;return r;
}
export async function validateHandoffMetadata(root, signal) {
  const errors=validateEnvelope(signal);
  if(errors.length)return {valid:false,task_id:null,receipt_id:null,snapshot:[],errors:errors.map(e=>({code:'ENVELOPE',path:SIGNAL_FILE,field:'handoff',observed:e,expected:'valid envelope'})),limitations:[]};
  if(!signal.handoff)return {valid:true,task_id:null,receipt_id:null,snapshot:[],errors:[],limitations:[]};
  const h=signal.handoff,r=await validateTaskSnapshot(root,h.task_file);r.receipt_id=h.id;
  const expected={task_id:r.task_id,task_status:r.metadata?.status,plan_revision:r.metadata?.revision,round:r.metadata?.round,to_role:r.metadata?.actor,intent:r.metadata?.intent,prompt_sha256:sha256(r.prompt)};
  for(const [key,value]of Object.entries(expected))if(h[key]!==value)error(r,'RECEIPT_MISMATCH',SIGNAL_FILE,key,h[key],value);
  for(const doc of r.snapshot)if(!h.documents.some(d=>d.path===doc.path&&d.sha256===doc.sha256))error(r,'SNAPSHOT',doc.path,'sha256',h.documents.find(d=>d.path===doc.path)?.sha256,doc.sha256);
  const hashResult=await validateDocumentHashes(root,h.documents);for(const e of hashResult.errors)error(r,'HASH',SIGNAL_FILE,'documents',e,'unchanged snapshot');
  r.valid=!r.errors.length;return r;
}
