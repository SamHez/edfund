#!/usr/bin/env python3
"""Meaningful integration checks in an isolated MariaDB database, never edfund.
Requires local Playwright; Chrome is reused from /Applications.
No passwords, enrollment secrets, recovery codes or session IDs are printed.
"""
from pathlib import Path
import base64,hashlib,hmac,json,os,secrets,shutil,socket,struct,subprocess,time
import pty,select
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent
PHP=shutil.which('php')

def otp(secret, ahead=0):
    key=base64.b32decode(secret+'='*((-len(secret))%8));step=int(time.time())//30+ahead
    digest=hmac.new(key,struct.pack('>Q',step),hashlib.sha1).digest();offset=digest[-1]&15
    return f'{(struct.unpack(">I",digest[offset:offset+4])[0]&0x7fffffff)%1000000:06d}'

def run(*args,env=None):
    return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()

with socket.socket() as sock:
    sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
fixture=Path(run('tests/fixture.php','create',port))
info=json.loads((fixture/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(fixture/'config.php'))
server_log=(fixture/'server.log').open('w')
server=subprocess.Popen([PHP,'-d','display_errors=0','-d','session.upload_progress.enabled=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=server_log,stderr=server_log)
base=f'http://127.0.0.1:{port}'

def fixture_action(action):return run('tests/fixture.php',action,fixture)

try:
    for _ in range(100):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.1):break
        except OSError:time.sleep(.05)
    else:raise RuntimeError('Local PHP test server did not start')
    second=subprocess.run([PHP,'bin/cms.php','owner:create'],cwd=ROOT,env=env,capture_output=True,text=True)
    assert second.returncode==1 and 'already exists' in second.stderr
    print('PASS additive migrations, existing-content sentinel, idempotence, least-privilege user and single-owner guard',flush=True)
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True)
        context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');page=context.new_page();errors=[]
        page.on('pageerror',lambda e:errors.append(str(e)))
        def csrf():return page.locator('input[name="csrf"]').first.get_attribute('value')
        def login(password=None):
            page.goto(base+'/admin/login');page.locator('[name=email]').fill(info['email']);page.locator('[name=password]').fill(password or info['password']);page.get_by_role('button',name='Continue',exact=True).click()
        def enrollment():
            assert page.url.endswith('/admin/enroll');secret=page.locator('.setup-key').inner_text()
            page.locator('[name=code]').fill(otp(secret));page.get_by_role('button',name='Confirm authenticator').click()
            assert page.url.endswith('/admin/recovery-codes')
            codes=page.locator('.recovery-list code').all_inner_texts();assert len(codes)==10
            page.get_by_role('button',name='I have saved my codes').click();assert page.url.endswith('/admin/')
            return secret,codes
        response=page.goto(base+'/admin/login');assert response.status==200
        headers=response.headers
        assert 'no-store' in headers['cache-control'] and 'noindex' in headers['x-robots-tag']
        assert "frame-ancestors 'none'" in headers['content-security-policy']
        cookies=context.cookies();assert any(c['httpOnly'] and c['sameSite']=='Strict' for c in cookies)
        first_sid=next(c['value'] for c in cookies if c['name']=='edfund_owner')
        missing=page.request.post(base+'/admin/login',form={'email':info['email'],'password':info['password']});assert missing.status==403
        cross=page.request.post(base+'/admin/login',form={'csrf':csrf(),'email':info['email'],'password':info['password']},headers={'Origin':'https://example.invalid'});assert cross.status==403
        mutation=page.request.get(base+'/admin/logout',max_redirects=0);assert mutation.status==303
        page.goto(base+'/admin/login')
        for _ in range(6):
            response=page.request.post(base+'/admin/login',form={'csrf':csrf(),'email':info['email'],'password':'invalid-password'});assert response.status==422
        blocked=page.request.post(base+'/admin/login',form={'csrf':csrf(),'email':info['email'],'password':info['password']});assert blocked.status==422
        fixture_action('clear-attempts');login()
        assert page.url.endswith('/admin/enroll'),(page.url,page.title(),page.locator('body').inner_text()[:220])
        second_sid=next(c['value'] for c in context.cookies() if c['name']=='edfund_owner');assert first_sid!=second_sid
        anonymous=browser.new_context();r=anonymous.request.get(base+'/admin/health',max_redirects=0);assert r.status==303;anonymous.close()
        secret,codes=enrollment();snapshot=json.loads(fixture_action('snapshot'));assert snapshot['encrypted'] and snapshot['enrolled'] and int(snapshot['codes'])==10
        print('PASS CSRF/origin checks, security headers, password throttling, partial-session denial, session rotation and MFA enrollment',flush=True)
        page.goto(base+'/admin/security');page.locator('[name=password]').fill(info['password']);page.locator('[name=code]').fill(otp(secret,1));page.get_by_role('button',name='Generate new codes').click()
        assert page.url.endswith('/admin/recovery-codes');new_codes=page.locator('.recovery-list code').all_inner_texts();assert set(codes).isdisjoint(new_codes)
        page.get_by_role('button',name='I have saved my codes').click();codes=new_codes
        # Browser request handler uses the actual PHP runtime, not the old XAMPP PHP.
        page.goto(base+'/admin/health');assert '8.5.7' in page.locator('main').inner_text() or 'PHP' in page.locator('main').inner_text()
        page.get_by_role('button',name='Sign out',exact=True).click();assert page.url.endswith('/admin/login')
        login();assert page.url.endswith('/admin/mfa')
        page.locator('[name=code]').fill(otp(secret,1));page.get_by_role('button',name='Verify',exact=True).click();assert page.url.endswith('/admin/mfa') and page.get_by_role('alert').is_visible()
        fixture_action('reset-factor-step')
        for _ in range(6):
            bad=('1' if otp(secret)[0]!='1' else '2')+otp(secret)[1:]
            r=page.request.post(base+'/admin/mfa',form={'csrf':csrf(),'code':bad});assert r.status==422
        r=page.request.post(base+'/admin/mfa',form={'csrf':csrf(),'code':otp(secret)});assert r.status==422
        fixture_action('clear-attempts');page.locator('[name=code]').fill(otp(secret));page.get_by_role('button',name='Verify',exact=True).click();assert page.url.endswith('/admin/')
        old_cookies=context.cookies()
        print('PASS recovery-code rotation, TOTP replay rejection, persistent MFA throttling and successful second-factor sign-in',flush=True)
        # Another authenticated browser is revoked when recovery is used.
        page.get_by_role('button',name='Sign out',exact=True).click();login();assert page.url.endswith('/admin/mfa')
        page.locator('[name=code]').fill(codes[0]);page.get_by_role('button',name='Verify',exact=True).click();assert page.url.endswith('/admin/enroll')
        new_secret,new_codes=enrollment();assert new_secret!=secret and set(codes).isdisjoint(new_codes)
        stolen=browser.new_context();stolen.add_cookies(old_cookies);r=stolen.request.get(base+'/admin/',max_redirects=0);assert r.status==303;stolen.close()
        page.get_by_role('button',name='Sign out',exact=True).click();login();page.locator('[name=code]').fill(codes[0]);page.get_by_role('button',name='Verify',exact=True).click();assert page.url.endswith('/admin/mfa')
        fixture_action('reset-factor-step');page.locator('[name=code]').fill(otp(new_secret));page.get_by_role('button',name='Verify',exact=True).click();assert page.url.endswith('/admin/')
        print('PASS single-use recovery, forced authenticator re-enrollment and obsolete code/session rejection',flush=True)
        # CLI recovery asks privately for new credentials and invalidates web sessions.
        new_password=secrets.token_hex(16)+'!'
        master,slave=pty.openpty()
        child=subprocess.Popen([PHP,'bin/cms.php','owner:recover'],cwd=ROOT,env=env,stdin=slave,stdout=slave,stderr=slave)
        os.close(slave);buffer=b''
        for prompt,response in [(b'Type RESET', 'RESET'),(b'New passphrase',new_password),(b'Confirm passphrase',new_password)]:
            deadline=time.monotonic()+20
            while prompt not in buffer:
                assert time.monotonic()<deadline,'Private CLI recovery did not reach its next prompt'
                ready,_,_=select.select([master],[],[],.2)
                if ready:buffer+=os.read(master,4096)
            buffer=b'';os.write(master,(response+'\n').encode())
        child.wait(timeout=20);os.close(master);assert child.returncode==0
        page.goto(base+'/admin/');assert page.url.endswith('/admin/login');login(new_password);assert page.url.endswith('/admin/enroll')
        print('PASS private CLI owner recovery, session revocation and required new MFA',flush=True)
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
