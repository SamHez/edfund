#!/usr/bin/env python3
"""Phase 2 workflows against an isolated database and real Chrome; never edits edfund."""
from pathlib import Path
import base64,hashlib,hmac,json,os,shutil,socket,struct,subprocess,time
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent
PHP=shutil.which('php')
def run(*args,env=None):return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()
def otp(secret):
 key=base64.b32decode(secret+'='*((-len(secret))%8));digest=hmac.new(key,struct.pack('>Q',int(time.time())//30),hashlib.sha1).digest();offset=digest[-1]&15
 return f'{(struct.unpack(">I",digest[offset:offset+4])[0]&0x7fffffff)%1000000:06d}'
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
fixture=Path(run('tests/fixture.php','create',port));info=json.loads((fixture/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(fixture/'config.php'))
server=None
try:
 print(run('tests/phase2_content.php',env=env),flush=True)
 original=BeautifulSoup((ROOT/'public/index.html').read_text(),'html.parser');rendered=BeautifulSoup((fixture/'runtime/imported.html').read_text(),'html.parser')
 for tag in ['h1','h2','h3','h4','p','figcaption','a','img']:
  def contents(s):return [(n.get_text(),n.get('src'),n.get('srcset'),n.get('sizes'),n.get('alt'),n.get('href'),n.get('data-caption')) for n in s.find_all(tag)]
  assert contents(original)==contents(rendered),f'Import changed {tag}'
 print('PASS exact current headings, narrative, captions, destinations, image order and responsive attributes',flush=True)
 log=(fixture/'server.log').open('w');server=subprocess.Popen([PHP,'-d','display_errors=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log);base=f'http://127.0.0.1:{port}'
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 p=sync_playwright().start()
 browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True)
 context=browser.new_context(viewport={'width':1600,'height':1100},reduced_motion='reduce');page=context.new_page();errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 anonymous=browser.new_context();anon=anonymous.request
 for path in ['/admin/pages','/admin/content','/admin/preview?token='+'a'*32]:assert anon.get(base+path,max_redirects=0).status==303
 page.goto(base+'/admin/login');page.locator('[name=email]').fill(info['email']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click()
 secret=page.locator('.setup-key').inner_text();page.locator('[name=code]').fill(otp(secret));page.get_by_role('button',name='Confirm authenticator').click();page.get_by_role('button',name='I have saved my codes').click()
 page.goto(base+'/admin/pages');page.wait_for_selector('.section-choice');page.wait_for_function("() => document.querySelector('#page-preview').contentDocument?.querySelector('.hero-slider')")
 def csrf():return page.locator('input[name=csrf]').first.get_attribute('value')
 def api(body,slug='home',token=None):return page.request.post(base+'/admin/content?document='+slug,form={'csrf':token or csrf(),'data':json.dumps(body)})
 state=page.request.get(base+'/admin/content').json()['state'];initial=state['content'];assert len(initial['sections'])==11
 missing=page.request.post(base+'/admin/content',form={'data':'{}'});assert missing.status==403
 before=anon.get(base+'/').text()
 preview=page.frame_locator('#page-preview');preview.locator('.who-section h2').click();page.wait_for_function("() => document.querySelector('#field-title').textContent.includes('Who We Are')")
 page.locator('#field-group').select_option(label='Who We Are');heading=page.locator('#fields input').first;heading.fill('Learning starts here');page.wait_for_timeout(1000)
 page.wait_for_function("() => document.querySelector('#page-preview').contentDocument?.querySelector('.who-section h2')?.textContent==='Learning starts here'")
 assert 'Learning starts here' not in anon.get(base+'/').text()
 page.locator('#save').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Draft saved')")
 draft=page.request.get(base+'/admin/content').json()['state'];assert draft['draft']!=draft['published']
 # Editing again after Save must use the new revision's field bindings.
 page.locator('#fields input').first.fill('Learning starts here — updated after saving')
 # Image picker updates actual preview, then stays private until publication.
 groups=page.locator('#field-group option').all_text_contents();page.locator('#field-group').select_option(label=groups[0]);page.get_by_role('button',name='Choose picture',exact=True).first.click();page.locator('#media-search').fill('school-courtyard.webp');page.locator('#media-grid button').first.click();assert not page.locator('#media-picker').is_visible()
 # Reorder and restore the position through real UI controls.
 page.locator('#down').click();page.wait_for_timeout(850);assert page.locator('.section-choice').nth(2).get_attribute('aria-pressed')=='true';page.locator('#up').click()
 # Add an approved layout with the existing visual styles.
 page.locator('#layout').select_option(label='Call to action');page.locator('#add').click();assert page.locator('.section-choice').count()==12
 page.locator('#mobile').click();page.wait_for_timeout(250);assert page.locator('#page-preview').evaluate('(e)=>Math.round(e.getBoundingClientRect().width)')==390
 page.locator('#desktop').click();page.wait_for_timeout(250);assert page.locator('#page-preview').evaluate('(e)=>e.getBoundingClientRect().width')>390
 page.on('dialog',lambda dialog:dialog.accept())
 page.locator('#publish').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Published')")
 live=anon.get(base+'/').text();assert 'Learning starts here — updated after saving' in live and len(BeautifulSoup(live,'html.parser').select('.final-cta'))==2
 # An old tab cannot silently overwrite this publication.
 stale=api({'action':'save','version':state['version'],'content':initial});assert stale.status==409
 # Authenticated tokenized iframe cannot be reused anonymously, indexed or cached.
 url=page.locator('#page-preview').get_attribute('src');private=page.request.get(base+url);assert private.status==200 and 'no-store' in private.headers['cache-control'] and 'noindex' in private.headers['x-robots-tag'] and "frame-ancestors 'self'" in private.headers['content-security-policy'];assert anon.get(base+url,max_redirects=0).status==303
 # Ignore forged preview selection from any window other than the actual frame.
 chosen=page.locator('#field-title').inner_text();page.evaluate("id=>window.postMessage({type:'edfund:select',id},location.origin)",initial['sections'][0]['id']);page.wait_for_timeout(50);assert page.locator('#field-title').inner_text()==chosen
 # Restore import, verify it is only a draft, then explicitly republish.
 page.locator('#revision').select_option(str(state['history'][-1]['id']));page.locator('#restore').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Restored')");assert 'Learning starts here' in anon.get(base+'/').text()
 page.locator('#publish').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Published')");assert anon.get(base+'/').text()==before
 # Shared content has independent revisions and publication, used on other page bodies too.
 shared=page.request.get(base+'/admin/content?document=shared').json()['state'];shared_data=shared['content'];key=next(k for k,v in shared_data['sections'][0]['values'].items() if v=='Kamuli, Uganda');shared_data['sections'][0]['values'][key]='Kamuli, Eastern Uganda'
 saved=api({'action':'save','version':shared['version'],'content':shared_data},'shared').json()['state'];assert 'Kamuli, Eastern Uganda' not in anon.get(base+'/about-us/').text()
 assert api({'action':'publish','version':saved['version'],'content':shared_data},'shared').status==200;assert 'Kamuli, Eastern Uganda' in anon.get(base+'/about-us/').text()
 # Invalid fields never affect publication.
 invalid=page.request.get(base+'/admin/content').json()['state'];broken=json.loads(json.dumps(invalid['content']));broken['sections'][1]['values']['arbitrary']='bad'
 assert api({'action':'publish','version':invalid['version'],'content':broken}).status==422
 # Full frontend interactions on database-rendered HTML.
 page.goto(base+'/');page.locator('[data-hero-next]').click()
 page.locator('#why-tab-3').click();page.locator('.response-project .kitchen-photo-link').first.click();assert page.locator('#why-lightbox').evaluate('(e)=>e.open');assert page.locator('#why-lightbox .why-viewer-thumbnails button').count()==4;page.keyboard.press('Escape')
 page.locator('#why-tab-0').click();page.locator('.why-chapter').first.locator('.why-photo-link').first.click();assert page.locator('#why-lightbox .why-viewer-thumbnails button').count()==3;page.keyboard.press('Escape')
 page.goto(base+'/our-team/');page.locator('.team-card a').first.click();assert page.locator('#team-profile-dialog').evaluate('(e)=>e.open');page.keyboard.press('Escape')
 page.goto(base+'/');page.locator('.video-play').first.click();assert 'youtube-nocookie.com/embed/SexHEvWnk2w' in page.locator('.video-frame iframe').first.get_attribute('src')
 for width in [390,320]:
  page.set_viewport_size({'width':width,'height':850});page.goto(base+'/');assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
  page.locator('.menu-toggle').click();assert page.locator('.menu-toggle').get_attribute('aria-expanded')=='true'
  page.locator('#why-tab-3').click();page.locator('.response-project .kitchen-photo-link').first.click();assert page.locator('#why-lightbox').evaluate('(e)=>e.open');page.keyboard.press('Escape')
 page.set_viewport_size({'width':1600,'height':1100});page.goto(base+'/admin/pages');page.wait_for_selector('.section-choice');page.wait_for_timeout(800);page.screenshot(path=str(fixture/'editor-desktop.png'))
 assert not errors,errors
 print('PASS click-to-select, edited preview, existing-media picker, ordering, approved layouts, desktop/mobile preview, drafts, publishing, conflict refusal, private previews, revision restore, shared settings and frontend/mobile interactions',flush=True)
 browser.close()
except Exception:
 if 'page' in locals() and not page.is_closed():
  try: page.screenshot(path=str(fixture/'failure.png'));print('Browser notice:',page.locator('#editor-notice').text_content() if page.locator('#editor-notice').count() else '(public page)',flush=True);print('Browser errors:',errors,flush=True)
  except Exception: pass
 raise
finally:
 if 'browser' in locals():browser.close()
 if 'p' in locals():p.stop()
 if server:server.terminate();server.wait(timeout=5)
 print(run('tests/fixture.php','destroy',fixture),flush=True)
