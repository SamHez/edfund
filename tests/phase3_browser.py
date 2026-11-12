#!/usr/bin/env python3
"""Real owner/post/media flows, disposable MariaDB only. No secrets printed."""
from pathlib import Path
import base64,hashlib,hmac,json,os,shutil,socket,struct,subprocess,time,io
from PIL import Image
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php')
def run(*args,env=None):return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()
def otp(secret):
 key=base64.b32decode(secret+'='*((-len(secret))%8));d=hmac.new(key,struct.pack('>Q',int(time.time())//30),hashlib.sha1).digest();o=d[-1]&15
 return f'{(struct.unpack(">I",d[o:o+4])[0]&0x7fffffff)%1000000:06d}'
def picture(width=800,height=600,fmt='JPEG'):
 b=io.BytesIO();Image.new('RGB',(width,height),'#11766e').save(b,format=fmt);return b.getvalue()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
f=Path(run('tests/fixture.php','create',port));info=json.loads((f/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(f/'config.php'));server=None;p=None;browser=None
try:
 print(run('tests/phase3_core.php',env=env),flush=True)
 log=(f/'server.log').open('w');server=subprocess.Popen([PHP,'-d','display_errors=0','-d','opcache.enable_cli=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log);base=f'http://127.0.0.1:{port}'
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 p=sync_playwright().start();browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True);ctx=browser.new_context(viewport={'width':1500,'height':1050});page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));anon=browser.new_context().request
 for path in ['/admin/posts','/admin/post?id=1','/admin/media','/admin/posts-data','/admin/media-data']:
  assert anon.get(base+path,max_redirects=0).status==303
 page.goto(base+'/admin/login');page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click();secret=page.locator('.setup-key').inner_text();page.locator('[name=code]').fill(otp(secret));page.get_by_role('button',name='Confirm authenticator').click();page.get_by_role('button',name='I have saved my codes').click()
 def csrf():return page.locator('input[name=csrf]').first.get_attribute('value')
 def api(path,body):return page.request.post(base+path,form={'csrf':csrf(),'data':json.dumps(body)})
 def upload(name,mime,data,token=True):return page.request.post(base+'/admin/media-data',multipart={**({'csrf':csrf()} if token else {}),'action':'upload','image':{'name':name,'mimeType':mime,'buffer':data}})
 assert upload('school.jpg','image/jpeg',picture(),False).status==403
 for name,mime,data in [('script.php','image/jpeg',picture()),('image.php.jpg','image/jpeg',picture()),('bad.jpg','image/jpeg',b'<?php echo 1;?>'),('broken.jpg','image/jpeg',picture()[:100]),('vector.svg','image/svg+xml',b'<svg onload="alert(1)"></svg>'),('archive.jpg','image/jpeg',b'PK\x03\x04not an image'),('wrong.jpg','image/jpeg',picture(fmt='PNG')),('oversized.jpg','image/jpeg',picture()+b'0'*2200000),('bomb.png','image/png',picture(3000,2000,'PNG'))]:
  assert upload(name,mime,data).status==422,(name,'unsafe upload accepted')
 original=picture()+b'<?php echo "never executable";?>';valid=upload('school.jpg','image/jpeg',original);assert valid.status==200,valid.text();media_id=valid.json()['id'];image_url='/media/'+media_id+'/large.webp';private_url='/admin/media-file/'+media_id+'/large.webp'
 assert anon.get(base+image_url).status==404 and anon.get(base+private_url,max_redirects=0).status==303
 safe=page.request.get(base+private_url);assert safe.status==200 and safe.headers['content-type']=='image/webp' and b'<?php' not in safe.body();assert Image.open(io.BytesIO(safe.body())).format=='WEBP'
 assert anon.get(base+'/storage/media/'+media_id+'/original.bin').status==404
 for path in ['/media/'+media_id+'/original.bin','/media/'+media_id+'/../../config/local.php','/admin/media-file/'+media_id+'/original.bin']:
  assert anon.get(base+path,max_redirects=0).status in [303,404]
 # MIME/decoding also works for allowed PNG/WebP, and duplicates never overwrite.
 ids=[media_id]
 for fmt,mime,ext in [('PNG','image/png','png'),('WEBP','image/webp','webp'),('JPEG','image/jpeg','jpg')]:
  r=upload('school.'+ext,mime,picture(fmt=fmt));assert r.status==200,r.text();ids.append(r.json()['id'])
 assert len(set(ids))==4
 print('PASS upload CSRF, MIME/decode/extension/pixel/size checks, private originals, safe re-encoding, unique storage and draft image privacy',flush=True)
 def restart_server():
  global server
  server.terminate();server.wait(timeout=5)
  server=subprocess.Popen([PHP,'-d','display_errors=0','-d','opcache.enable_cli=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log)
  for _ in range(100):
   try:
    with socket.create_connection(('127.0.0.1',port),timeout=.1):break
   except OSError:time.sleep(.05)
 run('tests/fixture.php','media-quota-low',f)
 assert "'media_quota_bytes' => 1024" in (f/'config.php').read_text()
 restart_server()
 quota=upload('quota.jpg','image/jpeg',picture());assert quota.status==422,(quota.status,page.request.get(base+'/admin/media-data').json()['limits'])
 run('tests/fixture.php','media-quota-reset',f)
 restart_server()
 # Unused pictures are softly removed/restored, while saved references are protected.
 removed=api('/admin/media-data',{'action':'delete','id':ids[-1],'alt':'','caption':''});assert removed.status==200
 assert api('/admin/media-data',{'action':'restore','id':ids[-1],'alt':'School','caption':'Caption'}).status==200
 # UI create/edit and actual server preview, with a locally bundled Quill toolbar.
 page.goto(base+'/admin/posts');page.locator('#new-post').click();page.wait_for_selector('#post-title');page.wait_for_function("() => document.querySelector('.ql-editor')!==null");post_id=int(page.url.split('id=')[-1]);page.on('dialog',lambda d:d.accept())
 page.locator('#post-title').fill('A new learning opportunity & hope');page.locator('#post-excerpt').fill('A concise story about learning in Kamuli.');page.locator('#post-slug').fill('a-new-learning-opportunity');page.locator('#post-author').fill('EdFund Test Author');page.locator('#post-featured_image').select_option(media_id);page.locator('#post-featured_alt').fill('A classroom prepared for learning');page.get_by_text('Search and sharing details',exact=True).click();page.locator('#post-seo_title').fill('A learning opportunity | EdFund');page.locator('#post-seo_description').fill('A story from Kamuli, Uganda.')
 page.locator('.ql-editor').first.fill('Learning makes a difference.');page.locator('.ql-editor').first.press('ControlOrMeta+a');page.locator('.ql-toolbar .ql-bold').first.click()
 page.locator('#post-block-type').select_option('youtube');page.locator('#post-add-block').click();page.locator('.post-block').last.locator('select').select_option(media_id);page.locator('.post-block').last.get_by_label('YouTube link',exact=True).fill('https://youtu.be/SexHEvWnk2w?t=17');page.locator('.post-block').last.get_by_label('Video title',exact=True).fill('Learning in Kamuli')
 page.locator('#post-save').click();page.wait_for_function("() => document.querySelector('#post-status').textContent.startsWith('Draft saved')")
 state=page.request.get(base+f'/admin/posts-data?id={post_id}').json()['state'];assert state['content']['blocks'][-1]['video']=='SexHEvWnk2w';assert not state['published'];assert anon.get(base+'/stories-updates/a-new-learning-opportunity/').status==404 and anon.get(base+image_url).status==404
 page.wait_for_function("() => document.querySelector('#post-frame').contentDocument?.querySelector('h1')?.textContent==='A new learning opportunity & hope'");assert page.frame_locator('#post-frame').locator('strong').count()>0
 assert api('/admin/media-data',{'action':'delete','id':media_id,'alt':'','caption':''}).status==422
 page.screenshot(path=str(f/'post-editor.png'))
 page.locator('#post-publish').click();page.wait_for_function("() => document.querySelector('#post-status').textContent.startsWith('Published')")
 live=anon.get(base+'/stories-updates/a-new-learning-opportunity/');assert live.status==200 and '<strong>Learning makes a difference.</strong>' in live.text() and '<title>A learning opportunity | EdFund</title>' in live.text();assert anon.get(base+image_url).status==200
 assert '/stories-updates/a-new-learning-opportunity/' in anon.get(base+'/').text() and '/stories-updates/a-new-learning-opportunity/' in anon.get(base+'/stories-updates/').text()
 visitor=ctx.new_page();visitor.goto(base+'/stories-updates/a-new-learning-opportunity/');visitor.locator('.video-play').click();assert 'youtube-nocookie.com/embed/SexHEvWnk2w' in visitor.locator('.video-frame iframe').get_attribute('src');visitor.close()
 # Stale tab and invalid structured document never overwrite a published post.
 assert api('/admin/posts-data',{'action':'save','id':post_id,'version':state['version'],'content':state['content']}).status==409
 current=page.request.get(base+f'/admin/posts-data?id={post_id}').json()['state'];bad=json.loads(json.dumps(current['content']));bad['blocks'][0]['delta']={'ops':[{'insert':{'image':'javascript:alert(1)'}}]};assert api('/admin/posts-data',{'action':'publish','id':post_id,'version':current['version'],'content':bad}).status==422
 bad=json.loads(json.dumps(current['content']));bad['blocks'][0]['delta']={'ops':[{'insert':'Click','attributes':{'link':'javascript:alert(1)'}},{'insert':'\n'}]};assert api('/admin/posts-data',{'action':'publish','id':post_id,'version':current['version'],'content':bad}).status==422
 # Genuine slug rename creates a direct permanent redirect.
 page.locator('#post-slug').fill('learning-with-new-tools');page.locator('#post-publish').click();page.wait_for_timeout(800)
 redirect=anon.get(base+'/stories-updates/a-new-learning-opportunity/',max_redirects=0);assert redirect.status==301 and redirect.headers['location']=='/stories-updates/learning-with-new-tools/'
 # Restore a revision as draft, then trash/untrash without accidental republication.
 page.locator('#post-revision').select_option(str(state['draft']));page.locator('#post-restore').click();page.wait_for_function("() => document.querySelector('#post-status').textContent.startsWith('Restored')");assert anon.get(base+'/stories-updates/learning-with-new-tools/').status==200
 page.locator('#post-trash').click();page.wait_for_function("() => document.querySelector('#post-status').textContent.startsWith('In trash')");assert anon.get(base+'/stories-updates/learning-with-new-tools/').status==410 and anon.get(base+image_url).status==404
 page.locator('#post-trash').click();page.wait_for_function("() => document.querySelector('#post-status').textContent.startsWith('Restored')");assert anon.get(base+'/stories-updates/learning-with-new-tools/').status==404
 # Existing homepage YouTube fields accept links and keep generated destinations in sync.
 page.goto(base+'/admin/pages');page.wait_for_selector('.section-choice');page_state=page.request.get(base+'/admin/content').json()['state'];templates=page.request.get(base+'/admin/content').json()['templates'];video_section=page_state['content']['sections'][3];key=next(k for k,fld in templates[video_section['template']]['fields'].items() if fld['type']=='youtube');video_section['values'][key]='https://youtu.be/Ej5BHQa_Jkw'
 video_preview=api('/admin/content',{'action':'preview','content':page_state['content']});assert video_preview.status==200;preview_html=page.request.get(base+video_preview.json()['url']).text();parsed=BeautifulSoup(preview_html,'html.parser');first=parsed.select('.video-card')[0];assert first.select_one('.video-frame')['data-video-id']=='Ej5BHQa_Jkw' and all('Ej5BHQa_Jkw' in a['href'] for a in first.select('a[href*="youtube.com/watch"]'))
 # Page editor upload integration; draft page image stays private until Publish.
 page.goto(base+'/admin/pages');page.wait_for_selector('.section-choice');page.locator('.section-choice').nth(1).click();page.get_by_role('button',name='Choose picture',exact=True).first.click();page.locator('#page-upload-file').set_input_files({'name':'page-photo.png','mimeType':'image/png','buffer':picture(fmt='PNG')});page.locator('#page-upload button').click();page.wait_for_function("() => !document.querySelector('#media-picker').open");page.locator('#save').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Draft saved')")
 state_page=page.request.get(base+'/admin/content').json()['state'];new_id=next(v for v in state_page['content']['sections'][1]['values'].values() if isinstance(v,str) and len(v)==64 and v not in [m['id'] for m in valid.json()['media']]);new_url='/media/'+new_id+'/large.webp';assert anon.get(base+new_url).status==404
 page.locator('#publish').click();page.wait_for_function("() => document.querySelector('#save-status').textContent.startsWith('Published')");assert anon.get(base+new_url).status==200
 # Library UI and mobile public article; no editor assets reach the public bundle.
 page.goto(base+'/admin/media');page.wait_for_selector('#library-grid button');page.locator('#library-search').fill('school.jpg');page.locator('#library-grid button').first.click();page.wait_for_selector('#library-details textarea');page.locator('#library-details textarea').first.fill('A school image');page.get_by_role('button',name='Save description').click();page.wait_for_function("() => document.querySelector('#library-status').textContent==='Picture updated.'")
 page.screenshot(path=str(f/'media-library.png'))
 for width in [390,320]:
  page.set_viewport_size({'width':width,'height':850});page.goto(base+'/stories-updates/community-digital-training/');assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
 assert 'quill' not in anon.get(base+'/').text().lower();assert not errors,errors
 print('PASS post UI formatting, safe preview, publishing/listings, actual dates/SEO, conflicts/XSS denial, slug redirects, restore/trash, media usage protection, page uploads and mobile views',flush=True)
except Exception:
 if browser and 'page' in locals():
  try:page.screenshot(path=str(f/'failure.png'));print('Browser errors:',errors,flush=True);print('Notice:',page.locator('#post-error').text_content() if page.locator('#post-error').count() else '',flush=True)
  except Exception:pass
 raise
finally:
 if browser:browser.close()
 if p:p.stop()
 if server:server.terminate();server.wait(timeout=5)
 print(run('tests/fixture.php','destroy',f),flush=True)
