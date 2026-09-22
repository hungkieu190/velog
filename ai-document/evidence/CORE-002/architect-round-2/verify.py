"""Read-only runtime probes and isolated generator verification for Architect review."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent
BASE = ROOT / 'ai-document/evidence/CORE-002'
PHP = r'''require 'tests/bootstrap.php'; $out=[]; foreach(json_decode(stream_get_contents(STDIN),true) as $c){try{$v=call_user_func_array(['MF\\VeLog\\Common\\Regional\\'.$c[0],$c[1]],$c[2]);$out[]=['value'=>$v];}catch(Throwable $e){$out[]=['error'=>get_class($e),'message'=>$e->getMessage()];}} echo json_encode($out);'''

def run_calls(calls):
    p = subprocess.run(['php', '-r', PHP], cwd=ROOT, input=json.dumps(calls), capture_output=True, text=True, check=True)
    return json.loads(p.stdout)

cases = []
for suffix in ['\n', '\r\n', '\x00', 'x']:
    for cls, method, args, code in [
        ('CalendarDate', 'parse', ['2024-02-29' + suffix], 'invalid_date'),
        ('Distance', 'to_decimal', ['500' + suffix, 'km', 3], 'invalid_number'),
        ('Formatter', 'decimal', ['1234.50' + suffix, '.'], 'invalid_number'),
    ]:
        cases.append(([cls, method, args], {'error': 'InvalidArgumentException', 'message': code}))
for val in ['\x00123', '123\x00', '12\x003', None, [], 123, '١٢٣', '１２３']:
    cases.append((['DecimalInput', 'parse', [val, '.', 2]], {'error':'InvalidArgumentException', 'message':'invalid_number'}))
cases += [
    (['Distance','to_decimal',['500','km',3]], {'value':'0.001'}),
    (['Distance','to_decimal',['1609343999998391','mi',3]], {'value':'999999999.999'}),
    (['Distance','to_decimal',['1609343999998392','mi',3]], {'error':'InvalidArgumentException','message':'out_of_range'}),
    (['DecimalInput','parse',[' \t\r\n\v\f123 \t\r\n\v\f','.',2]], {'value':'123'}),
]
observed = run_calls([c for c,e in cases])
probes = [{'call':c,'expected':e,'actual':a,'pass':a==e} for (c,e),a in zip(cases,observed)]
original_catalog = run_calls([['CurrencyCatalog','all',[]]])[0]['value']
manifest = json.loads((BASE/'planning/currency-source-verification.json').read_text())
source_hashes = {s['url'].rsplit('/',1)[1]: hashlib.sha256((BASE/'round-1/cache'/s['url'].rsplit('/',1)[1]).read_bytes()).hexdigest()==s['sha256'] for s in manifest['sources']}
old = json.loads((BASE/'architect-round-1/reviewed-files.json').read_text())
retained = {name: hashlib.sha256((ROOT/name).read_bytes()).hexdigest()==old[name] for name in ['src/Common/Regional/DecimalMath.php','src/Common/Regional/Money.php','src/Common/Regional/CurrencyCatalog.php','src/Common/Regional/CurrencyCatalogData.php']}
results = []
for mode in ['valid','hash','count','missing-source','malformed-json','output-failure']:
    with tempfile.TemporaryDirectory(prefix='velog-c2-r2-') as temp:
        base = Path(temp)
        rd = base/'ai-document/evidence/CORE-002/round-1'
        rd.mkdir(parents=True)
        shutil.copytree(BASE/'round-1/cache', rd/'cache')
        shutil.copy(BASE/'round-1/derive-catalog.php',rd)
        plan = rd.parent/'planning'; plan.mkdir()
        m = json.loads(json.dumps(manifest))
        if mode=='hash': (rd/'cache/currencyData.json').write_text('corrupt')
        if mode=='count': m['selectable_count'] += 1
        if mode=='missing-source':
            (rd/'cache/currencyData.json').unlink()
            m['sources'][0]['url'] = (base/'missing/currencyData.json').as_uri()
        if mode=='malformed-json':
            (rd/'cache/currencyData.json').write_text('{')
            m['sources'][0]['sha256'] = hashlib.sha256(b'{').hexdigest()
        if mode=='output-failure':
            (base/'src').mkdir()
            (base/'src/Common').write_text('owned blocking regular file')
        (plan/'currency-source-verification.json').write_text(json.dumps(m))
        p = subprocess.run(['php',str(rd/'derive-catalog.php')],capture_output=True,text=True,timeout=15)
        output = base/'src/Common/Regional/CurrencyCatalogData.php'
        result = {'case':mode,'exit':p.returncode,'stdout':p.stdout,'stderr':p.stderr,'output_exists':output.exists()}
        if mode=='valid':
            script = "define('ABSPATH', '/isolated/'); require $argv[1]; echo json_encode(MF\\VeLog\\Common\\Regional\\CurrencyCatalogData::get_catalog());"
            q = subprocess.run(['php','-r',script,str(output)],capture_output=True,text=True,check=True)
            result['catalog_matches_runtime'] = json.loads(q.stdout)==original_catalog
            license_lines=(rd/'cache/LICENSE').read_text().strip().splitlines()
            result['license_lines_present'] = all(line.strip() in output.read_text() for line in license_lines if line.strip())
            result['pass']=p.returncode==0 and result['catalog_matches_runtime'] and result['license_lines_present']
        else:
            result['pass']=p.returncode==1 and 'Catalog generated successfully' not in p.stdout and not output.exists()
    result['temporary_directory_removed']=not base.exists()
    results.append(result)
report={'runtime_probes':probes,'source_hashes':source_hashes,'unchanged_from_round_1':retained,'generator_cases':results}
(OUT/'verification.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({'probes_pass':sum(r['pass'] for r in probes),'probes_total':len(probes),'hashes':source_hashes,'retained':retained,'generator_cases':[{k:v for k,v in r.items() if k not in ['stdout','stderr']} for r in results]},indent=2))
raise SystemExit(0 if all(r['pass'] for r in probes+results) and all(source_hashes.values()) and all(r['temporary_directory_removed'] for r in results) else 1)
