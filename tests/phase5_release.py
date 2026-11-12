#!/usr/bin/env python3
"""Rehearse the actual ZIP through an isolated Apache/CGI document root and DB.
Never changes a global server config or the real edfund database.
"""
from pathlib import Path
import base64,hashlib,hmac,io,json,os,shutil,socket,struct,subprocess,time,zipfile
from PIL import Image
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php');HTTPD=shutil.which('httpd');CGI=shutil.which('php-cgi')
def run(*args,env=None,cwd=ROOT):return subprocess.check_output([PHP,*map(str,args)],cwd=cwd,env=env,text=True).strip()
def otp(secret):
 key=base64.b32decode(secret+'='*((-len(secret))%8));d=hmac.new(key,struct.pack('>Q',int(time.time())//30),hashlib.sha1).digest();o=d[-1]&15
 return f'{(struct.unpack(">I",d[o:o+4])[0]&0x7fffffff)%1000000:06d}'
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
f=Path(run('tests/fixture.php','create',port));info=json.loads((f/'fixture.json').read_text());stage=f/'extracted';server=None;p=None;browser=None
try:
 with zipfile.ZipFile(ROOT/'deployment/edfund-cpanel-ready.zip') as archive:
  assert archive.testzip() is None
  for name in archive.namelist():assert not name.startswith('/') and '..' not in Path(name).parts
  archive.extractall(stage)
 app=stage/'edfund-app';web=stage/'public_html';data=stage/'edfund-data';data.mkdir(mode=0o700);manifest=json.loads((app/'RELEASE.json').read_text())
 for prefix,group in [(app,'app_files'),(web,'web_files')]:
  for name,expected in manifest[group].items():assert hashlib.sha256((prefix/name).read_bytes()).hexdigest()==expected
 assert not list(web.rglob('*.html')) and not (app/'config/local.php').exists() and not (app/'storage').exists()
 # A fixture helper generates private config from existing fixture credentials, never printing them.
 helper=f/'prepare.php';helper.write_text('''<?php
 $c=require $argv[1];$c['storage_path']=$argv[2].'/storage';$c['backup_key_file']=$argv[2].'/backup.key';$c['document_root']=$argv[3];file_put_contents($argv[2].'/config.php','<?php return '.var_export($c,true).';');chmod($argv[2].'/config.php',0600);
 ''');run(helper,f/'config.php',data,web);env=dict(os.environ,EDFUND_CONFIG=str(data/'config.php'));cli=app/'bin/cms.php'
 for command in ['migrate','content:import','posts:import','pages:import','media:index','backup:key']:
  migration=f/'migration.php';migration.write_text("<?php $c=require "+json.dumps(str(ROOT/'config/migrations.local.php'))+";$c['dsn']=preg_replace('/dbname=edfund(?=;|$)/','dbname="+info['database']+"',$c['dsn']);return $c;")
  env['EDFUND_MIGRATION_CONFIG']=str(migration);print(run(cli,command,env=env),flush=True)
 assert run(cli,'pages:import',env=env).startswith('0 ')
 mismatch=dict(env,EDFUND_MIGRATION_CONFIG=str(ROOT/'config/migrations.local.php'));denied=subprocess.run([PHP,str(cli),'migrate'],env=mismatch,capture_output=True,text=True);assert denied.returncode==1 and 'same database' in denied.stderr
 grants=f/'grant-scope.php';grants.write_text('''<?php require $argv[1].'/vendor/autoload.php';$c=EdFund\\Config::load();$db=EdFund\\Database::connect($c['db']);foreach($db->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN) as $grant){if(str_contains($grant,'ON `edfund`.')){throw new RuntimeException('Cross-database local grant detected');}}echo 'Local grants scoped to fixture database';''');assert 'scoped' in run(grants,app,env=env)

 # Prove the public bridge defines the actual web root for private-path validation.
 probe=f/'paths.php';probe.write_text('''<?php require $argv[1].'/vendor/autoload.php';$c=EdFund\\Config::load();if(!EdFund\\Paths::publicPath($argv[2].'/assets',$c)){throw new RuntimeException('Actual public root missed');}$c['storage_path']=$argv[2].'/bad-storage';file_put_contents($argv[3],'<?php return '.var_export($c,true).';');putenv('EDFUND_CONFIG='.$argv[3]);try{EdFund\\Config::load();}catch(RuntimeException $e){echo 'Unsafe public storage refused';exit;}throw new RuntimeException('Public storage accepted');''');assert 'refused' in run(probe,app,web,data/'unsafe.php',env=env)
 # Private Apache instance; PHP CGI runs only as this test process user. No TLS claims.
 modules=Path(HTTPD).resolve().parents[1]/'lib/httpd/modules';config=f/'httpd.conf'
 names=['mpm_prefork','authz_core','authz_host','authn_core','authn_file','auth_basic','unixd','log_config','mime','dir','alias','actions','cgi','env','headers','rewrite']
 lines=[f'ServerRoot "{f}"',f'PidFile "{f}/httpd.pid"',f'Listen 127.0.0.1:{port}',f'ServerName 127.0.0.1:{port}']+[f'LoadModule {name}_module "{modules}/mod_{name}.so"' for name in names]
 lines += [f'ErrorLog "{f}/apache-error.log"','LogLevel warn',f'DocumentRoot "{web}"',f'<Directory "{web}">','AllowOverride All','Options +ExecCGI -Indexes','Require all granted','</Directory>',f'<Directory "{Path(CGI).resolve().parent}">','Options +ExecCGI','Require all granted','</Directory>',f'ScriptAlias /__phpcgi "{Path(CGI).resolve()}"','Action application/x-httpd-php /__phpcgi','AddHandler application/x-httpd-php .php','TypesConfig /dev/null','AddType text/css .css','AddType application/javascript .js','AddType image/webp .webp','AddType image/png .png','AddType image/jpeg .jpg .jpeg','AddType image/svg+xml .svg','AddType font/ttf .ttf']
 config.write_text('\n'.join(lines)+'\n');subprocess.run([HTTPD,'-t','-f',str(config)],check=True);log=(f/'apache-output.log').open('w');server=subprocess.Popen([HTTPD,'-f',str(config),'-DFOREGROUND'],stdout=log,stderr=log,start_new_session=True)
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 p=sync_playwright().start();browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True);context=browser.new_context(viewport={'width':1440,'height':1000});page=context.new_page();anon=browser.new_context().request;base=f'http://127.0.0.1:{port}';errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 for path in ['/','/about-us/','/our-work/','/our-team/','/gallery/','/volunteer/','/contact/','/stories-updates/','/privacy-policy/','/stories-updates/community-digital-training/']:
  r=anon.get(base+path);assert r.status==200,(path,r.status,r.text()[:200]);assert 'noindex' in r.headers['x-robots-tag']
 assert anon.get(base+'/assets/css/styles.css').status==200
 for path in ['/config/local.php','/edfund-app/config/production.example.php','/edfund-data/config.php','/vendor/autoload.php','/storage/logs/errors.log','/unknown/']:
  assert anon.get(base+path).status==404,(path,'private access')
 for old,new in [('/index.html','/'),('/about-us/index.html','/about-us/'),('/ourteam/','/our-team/')]:
  r=anon.get(base+old,max_redirects=0);assert r.status==301 and r.headers['location']==new,(old,r.status,r.text()[:150])
 assert anon.get(base+'/sitemap.xml').status==200
 challenge=web/'.well-known/acme-challenge/test-token';challenge.parent.mkdir(parents=True);challenge.write_text('provider-validation');assert anon.get(base+'/.well-known/acme-challenge/test-token').text()=='provider-validation'
 print('PASS extracted ZIP, bundled dependencies, explicit imports, actual Apache .htaccess/bridge routing, canonical redirects and private path denial',flush=True)
 page.goto(base+'/admin/login');page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click();secret=page.locator('.setup-key').inner_text();page.locator('[name=code]').fill(otp(secret));page.get_by_role('button',name='Confirm authenticator').click();page.get_by_role('button',name='I have saved my codes').click();page.on('dialog',lambda d:d.accept());page.goto(base+'/admin/pages');page.wait_for_selector('.section-choice')
 token=lambda:page.locator('input[name=csrf]').first.get_attribute('value')
 def api(slug,body):return page.request.post(base+'/admin/content?document='+slug,form={'csrf':token(),'data':json.dumps(body)})
 state=page.request.get(base+'/admin/content').json()['state'];original=json.loads(json.dumps(state['content']));templates=page.request.get(base+'/admin/content').json()['templates'];section=state['content']['sections'][1];key=next(k for k,v in templates[section['template']]['fields'].items() if v['type']=='heading');section['values'][key]='A client edit surviving release updates'
 saved=api('home',{'action':'save','version':state['version'],'content':state['content']});assert saved.status==200,saved.text();assert 'A client edit surviving' not in anon.get(base+'/').text();preview=api('home',{'action':'preview','content':state['content']});assert preview.status==200;assert anon.get(base+preview.json()['url'],max_redirects=0).status==303
 published=api('home',{'action':'publish','version':saved.json()['state']['version'],'content':state['content']});assert published.status==200,published.text();assert 'A client edit surviving' in anon.get(base+'/').text()
 image=io.BytesIO();Image.new('RGB',(600,400),'#17776e').save(image,format='JPEG');r=page.request.post(base+'/admin/media-data',multipart={'csrf':token(),'action':'upload','image':{'name':'release-test.jpg','mimeType':'image/jpeg','buffer':image.getvalue()}});assert r.status==200,r.text();media_id=r.json()['id'];private_image=page.request.get(base+'/admin/media-file/'+media_id+'/large.webp');assert private_image.status==200;image_hash=hashlib.sha256(private_image.body()).hexdigest();assert anon.get(base+'/media/'+media_id+'/large.webp').status==404
 # A post can be published through the Apache deployment without server build tools.
 def post_api(body):return page.request.post(base+'/admin/posts-data',form={'csrf':token(),'data':json.dumps(body)})
 new=post_api({'action':'create'}).json()['state'];post=new['content'];post.update({'title':'Release publishing rehearsal','slug':'release-publishing-rehearsal','excerpt':'A temporary release-verification story.','author':'EdFund Uganda Foundation','featured_image':media_id,'featured_alt':'School photograph','seo_title':'Release verification story | EdFund','seo_description':'Temporary owner publishing verification.'});post['blocks']=[{'type':'text','delta':{'ops':[{'insert':'Learning in Kamuli','attributes':{'bold':True}},{'insert':'\n'}]}},{'type':'youtube','video':'https://youtu.be/SexHEvWnk2w','title':'Learning in Kamuli','poster':media_id}]
 saved_post=post_api({'action':'save','id':new['id'],'version':new['version'],'content':post});assert saved_post.status==200,saved_post.text();assert anon.get(base+'/stories-updates/release-publishing-rehearsal/').status==404
 live_post=post_api({'action':'publish','id':new['id'],'version':saved_post.json()['state']['version'],'content':post});assert live_post.status==200,live_post.text()
 for path in ['/', '/stories-updates/', '/sitemap.xml']:assert '/stories-updates/release-publishing-rehearsal/' in anon.get(base+path).text()
 assert anon.get(base+'/media/'+media_id+'/large.webp').status==200
 article=anon.get(base+'/stories-updates/release-publishing-rehearsal/');assert '<strong>Learning in Kamuli</strong>' in article.text() and 'BlogPosting' in article.text()
 post=live_post.json()['state']['content'];post['slug']='release-renamed-story';renamed=post_api({'action':'publish','id':new['id'],'version':live_post.json()['state']['version'],'content':post,'rename':True});assert renamed.status==200,renamed.text();redirect=anon.get(base+'/stories-updates/release-publishing-rehearsal/',max_redirects=0);assert redirect.status==301 and redirect.headers['location']=='/stories-updates/release-renamed-story/'
 trashed=post_api({'action':'trash','id':new['id'],'version':renamed.json()['state']['version']});assert trashed.status==200;assert anon.get(base+'/stories-updates/release-renamed-story/').status==410 and 'release-renamed-story' not in anon.get(base+'/sitemap.xml').text();assert anon.get(base+'/media/'+media_id+'/large.webp').status==404
 # CLI package-integrity + authenticated owner checks pass in explicit local rehearsal only.
 check=json.loads(run(cli,'release:check','--local-rehearsal',env=env));assert check['passed'] and not check['production_ready'],check
 production=subprocess.run([PHP,str(cli),'release:check'],env=env,capture_output=True,text=True);assert production.returncode==1 and not json.loads(production.stdout)['production_ready']
 # Release gate rejects stale executable files, tampered payloads and unreviewed provider edits.
 stale=web/'legacy.php';stale.write_text('<?php echo "must not execute";');assert anon.get(base+'/legacy.php').status==404
 result=subprocess.run([PHP,str(cli),'release:check','--local-rehearsal'],env=env,capture_output=True,text=True);assert result.returncode==1 and not json.loads(result.stdout)['checks']['stale_public_entry_points_retired'];stale.unlink()
 css=web/'assets/css/styles.css';original_css=css.read_bytes();css.write_bytes(original_css+b'\n/*tamper test*/');result=subprocess.run([PHP,str(cli),'release:check','--local-rehearsal'],env=env,capture_output=True,text=True);assert result.returncode==1 and not json.loads(result.stdout)['checks']['release_file_integrity'];css.write_bytes(original_css)
 ht=web/'.htaccess';original_ht=ht.read_bytes();ht.write_bytes(original_ht+b'\n# reviewed provider rule rehearsal\n');result=subprocess.run([PHP,str(cli),'release:check','--local-rehearsal'],env=env,capture_output=True,text=True);assert result.returncode==1
 overrides=f/'provider-override.php';overrides.write_text('''<?php $c=require $argv[1];if($argv[2]==='enable'){$c['release_web_overrides']=['.htaccess'=>hash_file('sha256',$argv[3])];}else{unset($c['release_web_overrides']);}file_put_contents($argv[1],'<?php return '.var_export($c,true).';');''');run(overrides,data/'config.php','enable',ht);assert json.loads(run(cli,'release:check','--local-rehearsal',env=env))['passed'];ht.write_bytes(original_ht);run(overrides,data/'config.php','disable',ht)

 # Re-extract/switch CODE only. External data, DB, credentials and media survive.
 upgrade=stage/'edfund-app-next';shutil.copytree(app,upgrade);(web/'edfund-paths.php').write_text('<?php return '+"['application'=>"+json.dumps(str(upgrade))+",'configuration'=>"+json.dumps(str(data/'config.php'))+"];\n")
 assert 'A client edit surviving' in anon.get(base+'/').text();assert hashlib.sha256(page.request.get(base+'/admin/media-file/'+media_id+'/large.webp').body()).hexdigest()==image_hash;assert run(upgrade/'bin/cms.php','pages:import',env=env).startswith('0 ')
 current=page.request.get(base+'/admin/content').json()['state'];restored=api('home',{'action':'restore','version':current['version'],'revision':state['published']});assert restored.status==200;assert 'A client edit surviving' in anon.get(base+'/').text();republished=api('home',{'action':'publish','version':restored.json()['state']['version'],'content':restored.json()['state']['content']});assert republished.status==200;assert 'A client edit surviving' not in anon.get(base+'/').text()
 # Rehearse the public bridge rollback without rolling back client data.
 (web/'edfund-paths.php').unlink();assert anon.get(base+'/').status==200;assert hashlib.sha256(page.request.get(base+'/admin/media-file/'+media_id+'/large.webp').body()).hexdigest()==image_hash
 backup=run(cli,'backup:create',env=env);assert 'Encrypted private backup:' in backup
 # Reuse strict isolated restore checks against the release vendor/app, with current private storage.
 restore_test=f/'backup-test.php';restore_test.write_text((ROOT/'tests/phase4_backup.php').read_text().replace("require dirname(__DIR__).'/vendor/autoload.php';","require "+json.dumps(str(app/'vendor/autoload.php'))+";").replace("dirname(__DIR__).'/config/migrations.local.php'",json.dumps(str(ROOT/'config/migrations.local.php'))).replace("dirname(__DIR__).'/bin/cms.php'",json.dumps(str(cli))).replace("$key=$root.'/backup.key';", "$key=$root.'/restore-rehearsal.key';"));print(run(restore_test,env=env),flush=True)
 for width in [390,320]:
  page.set_viewport_size({'width':width,'height':850});page.goto(base+'/');assert page.evaluate('document.documentElement.scrollWidth<=innerWidth');page.locator('[data-hero-next]').click();page.locator('#why-tab-3').click();page.locator('.response-project .kitchen-photo-link').first.click();assert page.locator('#why-lightbox').evaluate('(e)=>e.open');page.keyboard.press('Escape');page.goto(base+'/our-team/');page.locator('.team-card a').first.click();assert page.locator('#team-profile-dialog').evaluate('(e)=>e.open');page.keyboard.press('Escape')
 assert not errors,errors
 print('PASS Apache/CGI owner MFA, draft/preview/publication/restoration, image upload, fail-closed production preflight, content/media survival across code update and rollback, encrypted recovery and mobile interactions',flush=True)
 (ROOT/'deployment/phase5-validation.json').write_text(json.dumps({'checked_utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'archive_sha256':hashlib.sha256((ROOT/'deployment/edfund-cpanel-ready.zip').read_bytes()).hexdigest(),'source_commit':manifest['source_commit'],'apache_version':subprocess.check_output([HTTPD,'-v'],text=True).splitlines()[0],'php_version':run('-r','echo PHP_VERSION;'),'scope':'isolated loopback Apache/CGI; not production TLS/hosting','passed':True,'deployed':False,'checks':['zip/integrity/secrets exclusions','bundled vendor/no server build tools','explicit repeat-safe migration/import','Apache htaccess/bridge/redirect/private paths','owner MFA/page+post draft/preview/publish/restore', 'preflight rejects stale PHP/tampered/unapproved payloads', 'provider TLS challenge routing','safe image upload/private media','code update+rollback preserves content/media','encrypted backup/isolated restore','390/320px hero/Why Uganda/team interactions']},indent=2)+'\n')
finally:
 if browser:browser.close()
 if p:p.stop()
 if server:server.terminate();server.wait(timeout=10)
 print(run('tests/fixture.php','destroy',f),flush=True)
