#!/usr/bin/env python3
"""Built-in analytics on an isolated database: counting rules, privacy and dashboard display."""
from pathlib import Path
import json,os,shutil,socket,subprocess,time
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php')
def run(*a,env=None):return subprocess.check_output([PHP,*map(str,a)],cwd=ROOT,env=env,text=True).strip()
with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
fixture=Path(run('tests/fixture.php','create',port));info=json.loads((fixture/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(fixture/'config.php'));server=None
try:
 run('tests/phase2_content.php',env=env)
 log=(fixture/'server.log').open('w');server=subprocess.Popen([PHP,'-d','display_errors=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log);base=f'http://127.0.0.1:{port}'
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 p=sync_playwright().start();b=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True)
 human={'User-Agent':'Mozilla/5.0 (Macintosh) AppleWebKit/605 Safari/605'}
 visitor=b.new_context().request
 for _ in range(3):assert visitor.get(base+'/',headers=human).status==200
 b.new_context().request.get(base+'/',headers={'User-Agent':'Googlebot/2.1'})
 b.new_context().request.get(base+'/',headers={**human,'DNT':'1'})
 c=b.new_context(viewport={'width':1440,'height':900});page=c.new_page()
 page.goto(base+'/admin/login');page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click()
 page.goto(base+'/');page.goto(base+'/admin/')
 assert page.locator('.stat-tile',has_text='Page views').locator('.stat-value').inner_text()=='3',page.locator('.stat-grid').inner_text()
 assert page.locator('.stat-tile',has_text='Visitors').locator('.stat-value').inner_text()=='1'
 assert page.locator('.top-pages li').first.inner_text().startswith('/')
 db=subprocess.check_output([PHP,'-r',"$c=require 'app/bootstrap.php';$d=EdFund\\Database::connect($c['db']);echo json_encode([$d->query('SELECT COUNT(*) FROM cms_visit_visitors')->fetchColumn(),$d->query('SELECT visitor FROM cms_visit_visitors')->fetchColumn()]);"],cwd=ROOT,env=env,text=True)
 count,hash_=json.loads(db);assert int(count)==1 and len(hash_)==32 and '127.0.0.1' not in hash_
 assert page.locator('link[rel=icon]').get_attribute('href')=='/assets/images/ed-fav.png'
 print('PASS cookie-free counting, bot/DNT/owner exclusion, hashed visitors, dashboard figures and favicon',flush=True)
 b.close()
finally:
 if 'p' in locals():p.stop()
 if server:server.terminate()
 print(run('tests/fixture.php','destroy',fixture))
