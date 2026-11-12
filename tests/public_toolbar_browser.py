#!/usr/bin/env python3
"""Public owner toolbar, existing session authorization and cache isolation."""
from pathlib import Path
import json,os,shutil,socket,subprocess,time
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php')
def run(*args,env=None):return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
f=Path(run('tests/fixture.php','create',port));info=json.loads((f/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(f/'config.php'));server=None
try:
 run('-r',"require 'vendor/autoload.php';$c=EdFund\\Config::load();$d=EdFund\\Database::connect($c['db']);(new EdFund\\ContentImport($d))->run();(new EdFund\\PostsImport($d,$c))->run();(new EdFund\\Pages($d))->import();",env=env)
 log=(f/'server.log').open('w');server=subprocess.Popen([PHP,'-d','display_errors=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log);base=f'http://127.0.0.1:{port}'
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True);owner=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');page=owner.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));anon=browser.new_context()
  def login():
   page.goto(base+'/admin/login');page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click();page.wait_for_url(base+'/admin/')
  routes={'/':'home','/about-us/':'about-us','/our-work/':'our-work','/our-team/':'our-team','/volunteer/':'volunteer','/contact/':'contact','/gallery/':'gallery','/privacy-policy/':'privacy-policy','/stories-updates/':'stories-updates'}
  for route in routes:
   response=anon.request.get(base+route);assert response.status==200;assert 'edfund-admin-toolbar' not in response.text();assert 'public-toolbar.css' not in response.text();assert 'set-cookie' not in response.headers
  assert anon.request.get(base+'/about-us/').headers['x-edfund-cache']=='HIT'
  login();cookies=owner.cookies();assert len(cookies)==1 and cookies[0]['path']=='/' and cookies[0]['httpOnly'] and cookies[0]['sameSite']=='Strict'
  for route,slug in routes.items():
   response=page.goto(base+route);assert response.status==200 and 'no-store' in response.headers['cache-control'] and 'private' in response.headers['cache-control'];assert response.headers['x-edfund-cache']=='BYPASS' and 'cookie' in response.headers['vary'].lower()
   assert page.locator('#edfund-admin-toolbar').count()==1;edit=page.locator('#toolbar-edit');assert edit.get_attribute('href')=='/admin/pages?document='+slug
   before=BeautifulSoup(anon.request.get(base+route).text(),'html.parser');actual=BeautifulSoup(response.text(),'html.parser');assert actual.select_one('main').get_text(' ',strip=True)==before.select_one('main').get_text(' ',strip=True)
   assert [(n.get('src'),n.get('alt')) for n in actual.select('main img')]==[(n.get('src'),n.get('alt')) for n in before.select('main img')]
   edit.click();page.wait_for_function("() => document.querySelector('#editor').getAttribute('aria-busy')==='false'");assert page.locator('#document').input_value()==slug;assert page.locator('#editor-return').get_attribute('data-public-route')==route
   page.wait_for_function("() => document.querySelector('#page-preview').contentDocument?.querySelector('main')");assert not page.frame_locator('#page-preview').locator('#edfund-admin-toolbar').count();page.locator('#editor-return').click();page.wait_for_url(base+route)
  assert page.request.get(base+'/admin/pages?document=about-us&return_to=https://example.invalid/').status==200
  # Real browsing -> section context -> draft/publish -> return to the same public page.
  page.goto(base+'/');page.locator('#why-tab-3').click();page.locator('#why-uganda').scroll_into_view_if_needed();page.evaluate("scrollTo({top:document.getElementById('why-uganda').getBoundingClientRect().top+scrollY-160,behavior:'instant'})");position=page.evaluate('scrollY');selected=page.locator('#why-uganda').get_attribute('data-cms-section');page.locator('#toolbar-edit').click();page.wait_for_function("() => document.querySelector('#editor').getAttribute('aria-busy')==='false'");assert page.locator('.section-choice[aria-pressed=true]').inner_text().startswith('5.'),page.locator('.section-choice[aria-pressed=true]').inner_text()
  page.wait_for_function("() => document.querySelector('#page-preview').contentDocument?.querySelector('#why-tab-3')?.getAttribute('aria-selected')==='true'")
  page.on('dialog',lambda d:d.accept());field=page.locator('#fields input').first;old=field.input_value();field.fill(old+' — toolbar workflow');page.locator('#save').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Draft saved')");assert '— toolbar workflow' not in BeautifulSoup(anon.request.get(base+'/').text(),'html.parser').get_text()
  page.locator('#publish').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Published')");page.locator('#editor-return').click();page.wait_for_load_state('load');assert abs(page.evaluate('scrollY')-position)<80;assert page.locator('#why-tab-3').get_attribute('aria-selected')=='true';assert '— toolbar workflow' in BeautifulSoup(anon.request.get(base+'/').text(),'html.parser').get_text()
  # Imported and newly published stories get direct story editing, including renamed URLs.
  story='/stories-updates/community-digital-training/';response=page.goto(base+story);assert response.headers['x-edfund-cache']=='BYPASS';assert page.locator('#toolbar-edit').inner_text()=='Edit Story';page.locator('#toolbar-edit').click();page.wait_for_selector('#post-title');page.wait_for_function("() => document.querySelector('#post-status').textContent.includes('version')");assert page.locator('#editor-return').get_attribute('data-public-route')==story
  page.locator('#post-title').fill('Toolbar story workflow');page.locator('#post-slug').fill('toolbar-story-workflow');page.locator('#post-publish').click();page.wait_for_function("() => document.querySelector('#post-status').textContent.startsWith('Published')");page.wait_for_function("() => document.querySelector('#editor-return').dataset.publicRoute==='/stories-updates/toolbar-story-workflow/'");page.locator('#editor-return').click();page.wait_for_url('**/stories-updates/toolbar-story-workflow/');assert page.locator('#toolbar-edit').inner_text()=='Edit Story'
  # Creation is a protected POST, never a state-changing GET or unprotected link.
  csrf=page.locator('#edfund-admin-toolbar [name=csrf]').first.get_attribute('value');assert page.request.get(base+'/admin/new-story',max_redirects=0).status==405;assert page.request.post(base+'/admin/new-story',form={}).status==403;assert page.request.post(base+'/admin/new-story',form={'csrf':csrf},headers={'Origin':'https://example.invalid'}).status==403
  page.locator('#edfund-admin-toolbar form[action="/admin/new-story"] button').click();page.wait_for_url('**/admin/post?id=*');page.wait_for_function("() => document.querySelector('#post-title').value==='Untitled story'")
  # Fixed positioning, responsive controls, keyboard account access and no layout overflow.
  previews=ROOT/'docs/previews';previews.mkdir(exist_ok=True)
  for width in [1440,820,390,320]:
   page.set_viewport_size({'width':width,'height':850});page.goto(base+'/about-us/');page.evaluate('scrollTo(0,500)');assert page.locator('#edfund-admin-toolbar').bounding_box()['y']==0;assert page.locator('.site-header').bounding_box()['y']>=39;assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),width
   assert page.locator('#toolbar-edit').is_visible();page.locator('.toolbar-account summary').click();assert page.get_by_role('button',name='Sign out',exact=True).is_visible();page.keyboard.press('Escape');assert page.locator('.toolbar-account summary').evaluate('(e)=>document.activeElement===e')
   if width in [1440,390]:page.screenshot(path=str(previews/f'public-toolbar-{width}.png'))
  # The personalized response and CSRF token never enter shared cache files.
  for cache in (f/'runtime/cache').glob('*.html'):assert 'edfund-admin-toolbar' not in cache.read_text() and csrf not in cache.read_text()
  anon.request.get(base+'/about-us/');r=anon.request.get(base+'/about-us/');assert r.headers['x-edfund-cache']=='HIT' and 'edfund-admin-toolbar' not in r.text()
  page.locator('.toolbar-account summary').click();page.get_by_role('button',name='Sign out',exact=True).click();page.wait_for_url('**/admin/login');page.goto(base+'/about-us/');assert not page.locator('#edfund-admin-toolbar').count();assert not owner.cookies()[0]['value']==cookies[0]['value']
  # Expired/partial sessions cannot display owner controls; a revoked version is rejected too.
  for mode in ['idle','absolute','pending']:
   invalid=browser.new_context();sid=run('tests/fixture.php','toolbar-session',f,mode);invalid.add_cookies([{'name':'edfund_owner','value':sid,'url':base+'/','httpOnly':True,'sameSite':'Strict'}]);r=invalid.request.get(base+'/');assert 'edfund-admin-toolbar' not in r.text() and 'no-store' in r.headers['cache-control'];invalid.close()
  login();run('tests/fixture.php','revoke-owner-session',f);r=page.request.get(base+'/');assert 'edfund-admin-toolbar' not in r.text();assert page.request.get(base+'/admin/pages',max_redirects=0).status==303
  # An existing /admin-only password session upgrades without a new login.
  legacy=browser.new_context();sid=run('tests/fixture.php','toolbar-session',f,'password');legacy.add_cookies([{'name':'edfund_owner','value':sid,'domain':'127.0.0.1','path':'/admin','httpOnly':True,'sameSite':'Strict'}]);assert 'edfund-admin-toolbar' not in legacy.request.get(base+'/').text();assert legacy.request.get(base+'/admin/').status==200;assert [c['path'] for c in legacy.cookies()]==['/'];assert 'edfund-admin-toolbar' in legacy.request.get(base+'/').text();legacy.close()
  assert not errors,errors;browser.close()
 print('PASS public toolbar on every page/story; owner-only root session; cache isolation; section/chapter/scroll context; draft/publish/return; renamed story return; CSRF-protected create/logout; expired/revoked/partial denial; legacy cookie upgrade; responsive fixed layout',flush=True)
finally:
 if server:server.terminate();server.wait(timeout=5)
 print(run('tests/fixture.php','destroy',f),flush=True)
