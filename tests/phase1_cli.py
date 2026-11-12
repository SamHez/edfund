#!/usr/bin/env python3
"""Private owner-bootstrap and concurrent-throttle checks on an isolated DB only."""
from pathlib import Path
import json,os,pty,select,shutil,subprocess,time
ROOT=Path(__file__).resolve().parent.parent
PHP=shutil.which('php')
def action(name,directory):
    return subprocess.check_output([PHP,'tests/fixture.php',name,str(directory)],cwd=ROOT,text=True).strip()
fixture=Path(subprocess.check_output([PHP,'tests/fixture.php','create','18089'],cwd=ROOT,text=True).strip())
try:
    info=json.loads((fixture/'fixture.json').read_text());action('empty-owner-for-cli-test',fixture)
    env=dict(os.environ,EDFUND_CONFIG=str(fixture/'config.php'))
    master,slave=pty.openpty();child=subprocess.Popen([PHP,'bin/cms.php','owner:create'],cwd=ROOT,env=env,stdin=slave,stdout=slave,stderr=slave);os.close(slave);buffer=b''
    for prompt,response in [(b'Owner name:',info['name']),(b'New passphrase','1234'),(b'Confirm passphrase','1234')]:
        deadline=time.monotonic()+20
        while prompt not in buffer:
            assert time.monotonic()<deadline,'Private owner creation did not reach its next prompt'
            ready,_,_=select.select([master],[],[],.2)
            if ready:buffer+=os.read(master,4096)
        buffer=b'';os.write(master,(response+'\n').encode())
    child.wait(timeout=20);os.close(master);assert child.returncode==0
    assert 'verified' in action('owner-name-password-policy',fixture)
    second=subprocess.run([PHP,'bin/cms.php','owner:create'],cwd=ROOT,env=env,capture_output=True,text=True);assert second.returncode==1 and 'already exists' in second.stderr
    workers=[subprocess.Popen([PHP,'tests/fixture.php','concurrent-bad-password',str(fixture)],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL) for _ in range(8)]
    for worker in workers:assert worker.wait(timeout=30)==0
    assert action('attempt-count',fixture)=='6','Concurrent requests exceeded the account attempt limit'
    print('PASS name-only owner creation/four-character login, email/wrong-name refusal, second-owner refusal and atomic six-attempt limit under eight concurrent requests')
finally:
    action('destroy',fixture);shutil.rmtree(fixture)
