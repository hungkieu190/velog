import hashlib,json,os,shutil,socket,subprocess,tempfile,time,urllib.request,urllib.error
from pathlib import Path
root=Path.cwd();out=root/'ai-document/evidence/WF-004/architect-round-1';tmp=Path(tempfile.mkdtemp(prefix='wf004-http-'))
p=None
try:
 shutil.copytree(root/'scripts',tmp/'scripts');(tmp/'ai-document/tasks').mkdir(parents=True);(tmp/'.cache/handoff').mkdir(parents=True)
 files={'ai-document/tasks/TEST-001.md':'# TEST-001: Fixture\n## Current handoff\n- Status: READY\n- Next actor: Builder\n### Chat handoff prompt\n```text\nStatus: READY\nRead fixture only.\n```\n','ai-document/README.md':'# Fixture\n','ai-document/implementation-checklist.md':'# Checklist\n## Current focus\n- Task: TEST-001\n- Status: READY\n- Next actor: Builder\n## Tests\n- [ ] TEST-001 / AC1: Test. Status: READY.\n'}
 for f,s in files.items():(tmp/f).write_text(s)
 h={'id':'11111111-1111-4111-8111-111111111111','previous_id':None,'task_id':'TEST-001','task_file':'ai-document/tasks/TEST-001.md','plan_revision':1,'round':1,'task_status':'READY','intent':'work','from_role':'architect','to_role':'builder','sender_session_id':'fixture','published_at':'2026-09-23T00:00:00Z','prompt_sha256':hashlib.sha256(b'Status: READY\nRead fixture only.').hexdigest(),'documents':[{'path':f,'sha256':hashlib.sha256(s.encode()).hexdigest()} for f,s in files.items()]}
 (tmp/'ai-document/handoff-signal.json').write_text(json.dumps({'schema_version':1,'handoff':h}))
 (tmp/'.cache/handoff/config.json').write_text(json.dumps({'enabled':True,'priorityTask':'TEST-001','adapters':{'antigravity':{'executable':'/bin/false','sessionId':'fixture'}}}))
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 with (out/'http-server.log').open('w') as log:
  p=subprocess.Popen(['/home/ecommercelife/.nvm/versions/node/v24.21.0/bin/node','scripts/progress-dashboard.mjs',f'--port={port}'],cwd=tmp,stdout=log,stderr=log,start_new_session=True)
  base=f'http://127.0.0.1:{port}';deadline=time.monotonic()+8
  while True:
   try:
    with urllib.request.urlopen(base+'/api/handoff',timeout=1) as response:monitor=json.load(response)
    if monitor.get('blocked'):break
   except (OSError,urllib.error.URLError):pass
   if time.monotonic()>deadline:raise RuntimeError('owned HTTP server readiness timeout')
   time.sleep(.05)
  with urllib.request.urlopen(base,timeout=2) as response:html=response.read().decode();get_status=response.status
  with urllib.request.urlopen(urllib.request.Request(base,method='HEAD'),timeout=2) as response:head_status=response.status
  try:urllib.request.urlopen(urllib.request.Request(base,method='POST',data=b''),timeout=2);post_status=200
  except urllib.error.HTTPError as error:post_status=error.code
  result={'GET':get_status,'HEAD':head_status,'POST':post_status,'monitor':monitor,'blocking_error_visible_in_html':monitor.get('blocked','MISSING') in html,'ledger_created':(tmp/'.cache/handoff/ledger.json').exists()}
  (out/'http-monitor.json').write_text(json.dumps(monitor,indent=2));(out/'http-page.html').write_text(html)
  p.terminate();p.wait(timeout=5);result['server_exit']=p.returncode
finally:
 if p and p.poll() is None:p.kill();p.wait()
 shutil.rmtree(tmp)
result['fixture_removed']=not tmp.exists();result['owned_child_reaped']=p.poll() is not None
(out/'http-results.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
