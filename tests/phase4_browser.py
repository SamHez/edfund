#!/usr/bin/env python3
"""Phase 4 pages/SEO/cache/restore workflows; only disposable database fixtures."""
from pathlib import Path
import base64,hashlib,hmac,io,json,os,shutil,socket,struct,subprocess,time
from bs4 import BeautifulSoup
from PIL import Image
import xml.etree.ElementTree as ET
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php')
def run(*args,env=None):return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
f=Path(run('tests/fixture.php','create',port));info=json.loads((f/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(f/'config.php'));server=None;p=None;browser=None
try:
 print(run('tests/phase4_core.php',env=env),flush=True)
 log=(f/'server.log').open('w');server=subprocess.Popen([PHP,'-d','display_errors=0','-d','opcache.enable_cli=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log);base=f'http://127.0.0.1:{port}'
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 p=sync_playwright().start();browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True);ctx=browser.new_context(viewport={'width':1500,'height':1000});page=ctx.new_page();anonymous=browser.new_context();anon=anonymous.request;errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 # Preserve all manual main text, image order/attributes and anchors, including team biographies.
 routes=['/']+['/'+x.parent.name+'/' for x in sorted((ROOT/'public').glob('*/index.html'))]
 titles=[];descriptions=[];images=[]
 for route in routes:
  r=anon.get(base+route);assert r.status==200,(route,r.status,r.text()[:100]);actual=BeautifulSoup(r.text(),'html.parser');expected=BeautifulSoup((ROOT/'public'/('index.html' if route=='/' else route.strip('/')+'/index.html')).read_text(),'html.parser')
  a=actual.select_one('main');e=expected.select_one('main');assert a.get_text(' ',strip=True)==e.get_text(' ',strip=True),(route,'text changed')
  attrs=lambda m:[[i.get(k,'') for k in ['src','srcset','sizes','alt','width','height']] for i in m.select('img')]
  assert attrs(a)==attrs(e),(route,'image attributes changed');assert [n.get('id') for n in a.select('[id]')]==[n.get('id') for n in e.select('[id]')]
  assert len(a.select('h1'))==1;assert all(i.get('alt') for i in a.select('img:not([aria-hidden=true])'))
  titles.append(actual.title.string);descriptions.append(actual.select_one('meta[name=description]')['content']);images.append(actual.select_one('meta[property="og:image"]')['content'])
  assert actual.select_one('link[rel=canonical]')['href']=='https://edfundfoundation.org'+route
  assert actual.select_one('meta[property="og:title"]')['content']==actual.title.string==actual.select_one('meta[name="twitter:title"]')['content']
  schema=json.loads(actual.select_one('script[type="application/ld+json"]').string);types=[g['@type'] for g in schema['@graph']];assert all(t in types for t in ['NGO','WebSite','WebPage']);assert ('BreadcrumbList' in types)==(route!='/');assert 'noindex' in r.headers['x-robots-tag']
 assert len(set(titles))==len(titles) and len(set(descriptions))==len(descriptions) and len(set(images))>=3,(titles,descriptions,images)
 sitemap=anon.get(base+'/sitemap.xml');assert sitemap.status==200;xml=ET.fromstring(sitemap.text());assert len(xml)==12 and all(u.find('{http://www.sitemaps.org/schemas/sitemap/0.9}lastmod') is not None for u in xml);assert 'Disallow: /' in anon.get(base+'/robots.txt').text()
 for old,new in [('/about-us','/about-us/'),('/about-us/index.html','/about-us/'),('/index.php','/'),('/new-about-us/','/about-us/'),('/ourteam/','/our-team/'),('/new-contact-us/','/contact/')]:
  r=anon.get(base+old,max_redirects=0);assert r.status==301 and r.headers['location']==new,(old,r.status)
 assert anon.get(base+'/unknown-page/').status==404 and anon.get(base+'/config/local.php').status==404
 r=anon.get(base+'/about-us/');assert r.headers['x-edfund-cache']=='HIT';assert anon.get(base+'/about-us/?preview=1').headers['x-edfund-cache']=='BYPASS';assert anon.get(base+'/about-us/',headers={'Cookie':'test=1'}).headers['x-edfund-cache']=='BYPASS'
 print('PASS exact manual content/images/anchors, unique synchronized metadata, structured data, sitemap, canonical redirects, private paths and bounded cache routing',flush=True)
 # Actual owner/editor workflow.
 page.goto(base+'/admin/login');page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click();assert page.url.endswith('/admin/');page.on('dialog',lambda d:d.accept());page.add_init_script("new MutationObserver(()=>{const d=document.getElementById('edfund-confirm');if(d&&d.open)d.querySelector('#edfund-confirm-ok').click()}).observe(document,{subtree:true,attributes:true,childList:true})");page.evaluate("new MutationObserver(()=>{const d=document.getElementById('edfund-confirm');if(d&&d.open)d.querySelector('#edfund-confirm-ok').click()}).observe(document,{subtree:true,attributes:true,childList:true})")
 page.goto(base+'/admin/pages');assert page.locator('.pe-page-card').count()==9;page.goto(base+'/admin/pages?document=our-team');page.wait_for_selector('.pe-row');page.locator('.pe-row-main').nth(1).click()
 for person in ['Rebecca Day','John C. Giebink','Dhizaala Stephen','Robert Ssebuliba']:
  part=page.locator('details.pe-part',has=page.locator('summary',has_text=person)).first;assert part.count()==1,person
  assert part.locator('.pe-field',has_text='Card: Image Description').locator('input').first.input_value()==person,person
  assert part.locator('.pe-field',has_text='Card: Headline').locator('input').first.input_value()==person,person
 assert not page.get_by_text('#team-profile-',exact=False).count()
 page.goto(base+'/admin/pages?document=about-us');page.wait_for_selector('.pe-row')
 def csrf():return page.locator('input[name=csrf]').first.get_attribute('value')
 def api(slug,body):return page.request.post(base+'/admin/content?document='+slug,form={'csrf':csrf(),'data':json.dumps(body)})
 result=page.request.get(base+'/admin/content?document=about-us').json();state=result['state'];templates=result['templates'];section=state['content']['sections'][1];key=next(k for k,v in templates[section['template']]['fields'].items() if v['type']=='heading');page.locator('.pe-row-main').nth(1).click();page.locator('#f-'+section['id']+'-'+key).fill('Our shared purpose');page.locator('#pe-seo-open').click();page.locator('#seo-title').fill('About EdFund | Our shared purpose');page.locator('#seo-description').fill('Meet the foundation helping children learn in Kamuli, Uganda.');page.locator('#pe-save').click();page.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('Draft saved')")
 assert 'Our shared purpose' not in anon.get(base+'/about-us/').text();page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument?.querySelector('main')?.textContent.includes('Our shared purpose')");preview=page.locator('#pe-frame').get_attribute('src');assert anon.get(base+preview,max_redirects=0).status==303;anonymous.clear_cookies() # A denied admin request now creates a root-scoped preauth cookie; use a cookie-free visitor for cache checks.
 page.locator('#pe-publish').click();page.locator('#pe-confirm-ok').click();page.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('All changes saved')");r=anon.get(base+'/about-us/');assert r.headers['x-edfund-cache']=='MISS' and 'Our shared purpose' in r.text() and '<title>About EdFund | Our shared purpose</title>' in r.text(),(r.status,r.headers.get('x-edfund-cache'),BeautifulSoup(r.text(),'html.parser').title.string,'Our shared purpose' in r.text());assert anon.get(base+'/about-us/').headers['x-edfund-cache']=='HIT'
 page.locator('#pe-more summary').click();page.locator('#pe-open-history').click();page.locator('.pe-rev[data-revision="'+str(state['published'])+'"]').get_by_role('button',name='Restore as Draft').click();page.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('Draft saved')");assert 'Our shared purpose' in anon.get(base+'/about-us/').text();page.locator('#pe-publish').click();page.locator('#pe-confirm-ok').click();page.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('All changes saved')");assert 'Our shared purpose' not in anon.get(base+'/about-us/').text()
 # Each remaining page preview uses its own body, not the homepage. Keep current shared text intact in fixtures too.
 for slug in ['our-work','our-team','volunteer','contact','gallery','privacy-policy','stories-updates']:
  result=page.request.get(base+'/admin/content?document='+slug).json();st=result['state'];r=api(slug,{'action':'preview','content':st['content']});assert r.status==200,r.text();html=page.request.get(base+r.json()['url']).text();assert 'data-cms-section=' in html and 'noindex' in html
 shared=page.request.get(base+'/admin/content?document=shared').json()['state'];shared['content']['sections'][0]['values']=dict(shared['content']['sections'][0]['values']);values=shared['content']['sections'][0]['values'];k=next(k for k,v in values.items() if v=='Kamuli, Uganda');values[k]='Kamuli, Eastern Uganda';r=api('shared',{'action':'publish','version':shared['version'],'content':shared['content']});assert r.status==200,r.text()
 assert all('Kamuli, Eastern Uganda' in anon.get(base+x).text() for x in ['/', '/contact/','/our-team/','/stories-updates/community-digital-training/'])
 page.screenshot(path=str(f/'phase4-editor.png'))
 # New real uploaded image must survive encrypted restoration with owner credentials, revisions and app key.
 image=io.BytesIO();Image.new('RGB',(800,600),'#14776e').save(image,format='JPEG');r=page.request.post(base+'/admin/media-data',multipart={'csrf':csrf(),'action':'upload','image':{'name':'restore-check.jpg','mimeType':'image/jpeg','buffer':image.getvalue()}});assert r.status==200,r.text()
 # Publish a real owner-created post after all page mappings exist; sitemap and both listings follow it.
 def post_api(body):return page.request.post(base+'/admin/posts-data',form={'csrf':csrf(),'data':json.dumps(body)})
 new=post_api({'action':'create'}).json()['state'];post=new['content'];post.update({'title':'Phase 4 owner workflow','slug':'phase-4-owner-workflow','excerpt':'A unique newly published story from the owner.','featured_image':r.json()['id'],'featured_alt':'A school photograph','author':'EdFund Uganda Foundation','seo_title':'Owner publishing verification | EdFund','seo_description':'Owner can publish a story without changing application code.'});post['blocks']=[{'type':'text','delta':{'ops':[{'insert':'A published update from Kamuli.\n'}]}}]
 saved=post_api({'action':'save','id':new['id'],'version':new['version'],'content':post});assert saved.status==200,saved.text();assert '/stories-updates/phase-4-owner-workflow/' not in anon.get(base+'/sitemap.xml').text();published=post_api({'action':'publish','id':new['id'],'version':saved.json()['state']['version'],'content':post});assert published.status==200,published.text()
 for route in ['/', '/stories-updates/','/sitemap.xml']:
  assert '/stories-updates/phase-4-owner-workflow/' in anon.get(base+route).text(),route
 article=anon.get(base+'/stories-updates/phase-4-owner-workflow/');assert article.status==200;schema=json.loads(BeautifulSoup(article.text(),'html.parser').select_one('script[type="application/ld+json"]').string);assert any(x['@type']=='BlogPosting' and x.get('datePublished') and x.get('dateModified') for x in schema['@graph'])
 trashed=post_api({'action':'trash','id':new['id'],'version':published.json()['state']['version']});assert trashed.status==200;assert anon.get(base+'/stories-updates/phase-4-owner-workflow/').status==410 and 'phase-4-owner-workflow' not in anon.get(base+'/sitemap.xml').text()
 run('tests/fixture.php','legacy-factor-data',f) # Historical encrypted records remain recoverable without enabling a factor flow.
 print(run('tests/phase4_backup.php',env=env),flush=True)
 # Desktop/mobile interaction and no-JS fallbacks on migrated pages.
 for width in [1500,390,320]:
  page.set_viewport_size({'width':width,'height':900})
  for route in routes:
   page.goto(base+route);assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,route)
  page.goto(base+'/');page.locator('[data-hero-next]').click();page.locator('#why-tab-3').click();page.locator('.response-project .kitchen-photo-link').first.click();assert page.locator('#why-lightbox').evaluate('(e)=>e.open');page.keyboard.press('Escape');page.goto(base+'/our-team/');page.locator('.team-card a').first.click();assert page.locator('#team-profile-dialog').evaluate('(e)=>e.open');page.keyboard.press('Escape')
 nojs=browser.new_context(java_script_enabled=False);np=nojs.new_page();np.goto(base+'/our-team/');assert np.locator('.team-profile-fallback').count()>0;nojs.close();assert not errors,errors
 print('PASS actual About edit/preview/draft/publish/restore, all page previews, site-wide shared publication, anonymous preview denial, encrypted media/owner recovery and 1500/390/320px interactions',flush=True)
finally:
 if browser:browser.close()
 if p:p.stop()
 if server:server.terminate();server.wait(timeout=5)
 print(run('tests/fixture.php','destroy',f),flush=True)
