#!/usr/bin/env python3
"""Password-only owner security and public profile checks, isolated MariaDB only."""
from pathlib import Path
import json,os,shutil,socket,subprocess,time,pty,select
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php')
def run(*args,env=None):return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
fixture=Path(run('tests/fixture.php','create',port));info=json.loads((fixture/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(fixture/'config.php'));server_log=(fixture/'server.log').open('w')
server=subprocess.Popen([PHP,'-d','display_errors=0','-d','session.upload_progress.enabled=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=server_log,stderr=server_log);base=f'http://127.0.0.1:{port}'
def fixture_action(action):return run('tests/fixture.php',action,fixture)
try:
    for _ in range(100):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.1):break
        except OSError:time.sleep(.05)
    second=subprocess.run([PHP,'bin/cms.php','owner:create'],cwd=ROOT,env=env,capture_output=True,text=True);assert second.returncode==1 and 'already exists' in second.stderr
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True)
        context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def csrf():return page.locator('input[name="csrf"]').first.get_attribute('value')
        def login(password=None):
            page.goto(base+'/admin/login');page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(password or info['password']);page.get_by_role('button',name='Continue',exact=True).click()
        response=page.goto(base+'/admin/login');assert response.status==200 and not page.locator('[name=email]').count()
        assert 'no-store' in response.headers['cache-control'] and 'noindex' in response.headers['x-robots-tag'] and "frame-ancestors 'none'" in response.headers['content-security-policy']
        first=next(c['value'] for c in context.cookies() if c['name']=='edfund_owner');assert all(c['httpOnly'] and c['sameSite']=='Strict' for c in context.cookies())
        assert page.request.post(base+'/admin/login',form={'name':info['name'],'password':info['password']}).status==403
        assert page.request.post(base+'/admin/login',form={'csrf':csrf(),'name':info['name'],'password':info['password']},headers={'Origin':'https://example.invalid'}).status==403
        for _ in range(6):assert page.request.post(base+'/admin/login',form={'csrf':csrf(),'name':info['name'],'password':'invalid-password'}).status==422
        assert page.request.post(base+'/admin/login',form={'csrf':csrf(),'name':info['name'],'password':info['password']}).status==422
        fixture_action('clear-attempts');login();assert page.url.endswith('/admin/') and not page.locator('.setup-key,[name=code]').count()
        assert first!=next(c['value'] for c in context.cookies() if c['name']=='edfund_owner')
        anonymous=browser.new_context()
        for path in ['/admin/health','/admin/content','/admin/posts-data','/admin/media-data']:
            assert anonymous.request.get(base+path,max_redirects=0).status==303
        anonymous.close();assert page.request.get(base+'/admin/logout',max_redirects=0).status==405
        assert page.request.get(base+'/admin/security').status==404
        print('PASS password-only dashboard access, session rotation, CSRF/origin, throttling, protected APIs and no authenticator controls',flush=True)
        # Neither old partial sessions nor previously completed MFA sessions are promoted.
        for action in ['legacy-pending-session','legacy-factor-session']:
            legacy=browser.new_context();legacy.add_cookies([{'name':'edfund_owner','value':fixture_action(action),'url':base+'/admin/','httpOnly':True,'sameSite':'Strict'}]);r=legacy.request.get(base+'/admin/health',max_redirects=0);assert r.status==303 and r.headers['location']=='/admin/login';legacy.close()
        fresh=browser.new_context()
        for path in ['/admin/enroll','/admin/mfa','/admin/recovery-codes']:
            r=fresh.request.get(base+path,max_redirects=0);assert r.status==303 and r.headers['location']=='/admin/login'
        fresh.close();fixture_action('legacy-factor-data');page.locator('.sh-account summary').click();page.get_by_role('button',name='Sign out').click();login();assert page.url.endswith('/admin/')
        print('PASS retired enroll/MFA/recovery URLs and old sessions require a fresh password; legacy enrolled owners can sign in directly',flush=True)
        # Private password recovery revokes earlier sessions without another factor step.
        new_password='1234';master,slave=pty.openpty();child=subprocess.Popen([PHP,'bin/cms.php','owner:recover'],cwd=ROOT,env=env,stdin=slave,stdout=slave,stderr=slave);os.close(slave);buffer=b''
        for prompt,response in [(b'Type RESET','RESET'),(b'New passphrase',new_password),(b'Confirm passphrase',new_password)]:
            deadline=time.monotonic()+20
            while prompt not in buffer:
                assert time.monotonic()<deadline,'Private recovery prompt timed out'
                ready,_,_=select.select([master],[],[],.2)
                if ready:buffer+=os.read(master,4096)
            buffer=b'';os.write(master,(response+'\n').encode())
        child.wait(timeout=20);os.close(master);assert child.returncode==0
        page.goto(base+'/admin/');assert page.url.endswith('/admin/login');login();assert page.url.endswith('/admin/login');login(new_password);assert page.url.endswith('/admin/')
        print('PASS private four-character password reset, obsolete password refusal and session revocation without MFA',flush=True)
        # Public team profiles are independent of the CMS login/database.
        page.goto(base+'/our-team/',wait_until='networkidle');assert page.locator('.team-profile-open').count()==4
        names=['Rebecca Day','John C. Giebink','Dhizaala Stephen','Robert Ssebuliba']
        profiles=json.loads((ROOT/'source/team-profiles.json').read_text())['members']
        for width in [1440,390,320]:
            page.set_viewport_size({'width':width,'height':1000})
            for i,name in enumerate(names):
                link=page.locator('.team-profile-open').nth(i);link.click();dialog=page.locator('#team-profile-dialog');assert dialog.evaluate('(el)=>el.matches(":modal")')
                assert dialog.locator('h2').inner_text()==name
                assert dialog.locator('.team-profile-biography p').all_inner_texts()==profiles[name]['paragraphs']
                assert dialog.evaluate('(el)=>el.scrollWidth<=el.clientWidth')
                if width in [1440,390] and i==0:page.screenshot(path=str(fixture/f'team-modal-{width}.png'))
                dialog.evaluate('(el)=>el.scrollTop=el.scrollHeight')
                close_box=dialog.locator('[data-team-close]').bounding_box();dialog_box=dialog.bounding_box()
                assert close_box['y']>=dialog_box['y'] and close_box['y']+close_box['height']<=1000
                page.keyboard.press('Escape');assert not dialog.is_visible();assert link.evaluate('(el)=>document.activeElement===el')
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
        fallback=browser.new_context(java_script_enabled=False);fp=fallback.new_page();fp.goto(base+'/our-team/');fp.locator('.team-profile-fallback summary').first.click();assert fp.locator('.team-profile-biography p').first.is_visible();fallback.close()
        for path in ['/config/local.php','/.git/config','/app/Auth.php','/storage/sessions/','/vendor/autoload.php','/../config/local.php']:
            r=page.request.get(base+path);assert r.status==404
        assert not errors,errors
        preview=ROOT/'docs/previews';preview.mkdir(exist_ok=True)
        for width in [1440,390]:shutil.copy2(fixture/f'team-modal-{width}.png',preview/f'team-recovered-modal-{width}.png')
        browser.close()
        print('PASS four exact recovered biographies, accessible desktop/mobile dialogs, no-JS fallback and private-path denial',flush=True)
    print('ALL PHASE 1 INTEGRATION CHECKS PASSED',flush=True)
finally:
    server.terminate()
    try:server.wait(timeout=5)
    except subprocess.TimeoutExpired:server.kill();server.wait()
    server_log.close()
    shutil.copy2(fixture/'server.log',Path('/tmp/edfund-phase1-last-server.log'))
    fixture_action('destroy')
    shutil.rmtree(fixture)
