#!/usr/bin/env python3
"""Admin navigation and presentation checks in an isolated database."""
from pathlib import Path
import json,os,shutil,socket,subprocess,time
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php')
def run(*args,env=None):return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
fixture=Path(run('tests/fixture.php','create',port));info=json.loads((fixture/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(fixture/'config.php'));server=None
try:
 run('-r',"require 'vendor/autoload.php';$c=EdFund\\Config::load();$d=EdFund\\Database::connect($c['db']);(new EdFund\\ContentImport($d))->run();(new EdFund\\PostsImport($d,$c))->run();(new EdFund\\Pages($d))->import();",env=env)
 log=(fixture/'server.log').open('w');server=subprocess.Popen([PHP,'-d','display_errors=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log);base=f'http://127.0.0.1:{port}'
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True);context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(base+'/admin/login');assert not page.locator('#admin-sidebar').count();page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click();page.wait_for_url(base+'/admin/')
  nav=page.get_by_role('navigation',name='Admin',exact=True);assert nav.get_by_role('link').all_text_contents()==['Dashboard','Pages','Stories & Updates','Media Library','Settings'];assert not nav.get_by_text('Sign out').count();assert not nav.get_by_text('Account security').count()
  assert page.locator('[aria-current=page]').get_attribute('data-admin-nav')=='dashboard';page.locator('.sh-account summary').click();assert page.get_by_role('button',name='Sign out',exact=True).is_visible();page.keyboard.press('Escape');assert not page.locator('.sh-account').evaluate('(e)=>e.open')
  assert not page.locator('#sidebar-collapse').count();assert page.locator('.sh-side').evaluate('(e)=>e.getBoundingClientRect().width')<100;page.locator('[data-admin-nav=pages]').hover();assert page.locator('[data-admin-nav=pages]').evaluate('(e)=>getComputedStyle(e,"::after").content')=='"Pages"';assert page.locator('[data-admin-nav=website]').get_attribute('href')=='/' and not page.locator('[data-admin-nav=website]').get_attribute('target');assert page.locator('.sh-visit').get_attribute('href')=='/' and not page.locator('.sh-visit').get_attribute('target');assert page.locator('.sh-top').evaluate('(e)=>e.getBoundingClientRect().width')>=1430;assert page.locator('.sh-logo img').is_visible()
  previews=ROOT/'docs/previews';previews.mkdir(exist_ok=True);page.screenshot(path=str(previews/'admin-dashboard-desktop.png'),full_page=True)
  for path,key in [('/admin/pages','pages'),('/admin/posts','stories'),('/admin/media','media'),('/admin/pages?document=shared','settings')]:
   page.goto(base+path);page.wait_for_timeout(700);assert page.locator('[aria-current=page]').get_attribute('data-admin-nav')==key
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),path
  pending=[]
  page.route('**/admin/content?document=home',lambda route:pending.append(route) if route.request.method=='GET' else route.continue_())
  page.goto(base+'/admin/pages?document=home');page.wait_for_function("() => document.querySelector('#editor').getAttribute('aria-busy')==='true'");assert page.locator('.pe-body').evaluate('(e)=>e.inert');assert page.locator('#pe-save').is_disabled();assert len(pending)==1;pending[0].continue_();page.unroute('**/admin/content?document=home')
  assert page.locator('#admin-page-title').inner_text()=='Pages';assert page.locator('[aria-current=page]').get_attribute('data-admin-nav')=='pages';page.wait_for_function("() => document.querySelector('#editor').getAttribute('aria-busy')==='false' && document.querySelectorAll('#pe-section-list .pe-row').length===11");page.screenshot(path=str(previews/'admin-pages-desktop.png'))
  page.goto(base+'/admin/media');page.wait_for_selector('#library-grid img');page.locator('#library-search').fill('no-matching-picture-1a9f');assert page.locator('.empty-state').inner_text().startswith('No pictures match');page.locator('#library-search').fill('');page.locator('#library-grid button').first.click();assert page.locator('#library-grid button[aria-pressed=true]').count()==1;assert page.locator('#library-grid button[aria-pressed=true]').evaluate('(e)=>document.activeElement===e')
  # Fail an API request to verify a readable error rather than a permanent spinner.
  page.route('**/admin/media-data',lambda route:route.fulfill(status=503,content_type='application/json',body='{"error":"Pictures unavailable. Please reload."}'));page.reload();page.wait_for_function("() => document.querySelector('#library-error').textContent.includes('Pictures unavailable')");assert page.locator('#library-grid').get_attribute('aria-busy')=='false';page.unroute('**/admin/media-data')
  for width in [1024,820,390,320]:
   page.set_viewport_size({'width':width,'height':850})
   for path in ['/admin/','/admin/pages','/admin/posts','/admin/media','/admin/health']:
    page.goto(base+path);page.wait_for_timeout(500);assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,path)
   if width<=900:
    page.locator('#sidebar-toggle').click();assert page.locator('#sidebar-toggle').get_attribute('aria-expanded')=='true';assert page.locator('.admin-workspace').evaluate('(e)=>e.inert');page.locator('#sidebar-close').focus();page.keyboard.press('Shift+Tab');assert page.locator('[data-admin-nav=website]').evaluate('(e)=>document.activeElement===e');page.locator('#sidebar-close').click();assert page.locator('#sidebar-toggle').get_attribute('aria-expanded')=='false'
    page.locator('#sidebar-toggle').click();page.keyboard.press('Escape');assert page.locator('#sidebar-toggle').evaluate('(e)=>document.activeElement===e')
    page.locator('#sidebar-toggle').click();page.get_by_role('navigation',name='Admin',exact=True).get_by_role('link',name='Settings',exact=True).click();page.wait_for_url('**/admin/pages?document=shared');assert not page.locator('body').evaluate('(e)=>e.classList.contains("sidebar-open")')
  page.set_viewport_size({'width':390,'height':850});page.goto(base+'/admin/');page.screenshot(path=str(previews/'admin-dashboard-mobile.png'),full_page=True);page.locator('#sidebar-toggle').click();page.screenshot(path=str(previews/'admin-navigation-mobile.png'))
  page.keyboard.press('Escape');page.locator('.sh-account summary').click();page.get_by_role('button',name='Sign out',exact=True).click();page.wait_for_url('**/admin/login');assert page.request.get(base+'/admin/pages',max_redirects=0).status==303
  # Navigation remains available without JavaScript.
  fallback=browser.new_context(java_script_enabled=False,viewport={'width':390,'height':850});fp=fallback.new_page();fp.goto(base+'/admin/login');fp.locator('[name=name]').fill(info['name']);fp.locator('[name=password]').fill(info['password']);fp.get_by_role('button',name='Continue').click();assert fp.get_by_role('navigation',name='Admin',exact=True).is_visible();assert fp.evaluate('document.documentElement.scrollWidth<=innerWidth');fallback.close()
  assert not errors,errors;browser.close()
 print('PASS admin navigation, account/logout, persisted collapse, settings selection, responsive screens at 1440/1024/820/390/320px, mobile focus/Escape, no-JS navigation, media states and API error feedback',flush=True)
finally:
 if server:server.terminate();server.wait(timeout=5)
 print(run('tests/fixture.php','destroy',fixture),flush=True)
