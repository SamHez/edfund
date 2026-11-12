#!/usr/bin/env python3
"""Rehearse the no-terminal cPanel install from the finished ZIP, exactly as START-HERE.md describes.

Unzips into a fake account home, imports edfund-database.sql into a throw-away database (like phpMyAdmin),
edits only the three settings in edfund-data/config.php, then serves public_html over a local
HTTPS-pretending wrapper and checks setup, login, indexing and SEO. Never touches the real edfund database.
"""
from pathlib import Path
import json,os,secrets,shutil,socket,subprocess,sys,tempfile,time,zipfile,re
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent;PHP=shutil.which('php')
ZIP=ROOT/'deployment/edfund-cpanel-ready.zip'
BIN='/Applications/XAMPP/xamppfiles/bin/'
admin=json.loads(subprocess.check_output([PHP,'-r',"echo json_encode(require 'config/migrations.local.php');"],cwd=ROOT,text=True))
socket_path=next((p.split('=',1)[1] for p in admin['dsn'].split(':',1)[1].split(';') if p.startswith('unix_socket=')),None)
dbname='edfund_rehearsal_'+secrets.token_hex(4);dbuser='edr_'+secrets.token_hex(4);dbpass=secrets.token_hex(16)
with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
tmp=Path(tempfile.mkdtemp(prefix='edfund-cpanel-'));os.chmod(tmp,0o700)
cnf=tmp/'client.cnf';cnf.write_text('[client]\nuser=%s\npassword=%s\n%s'%(admin['user'],admin['password'],('socket=%s\n'%socket_path) if socket_path else ''));os.chmod(cnf,0o600)
sql=lambda q:subprocess.run([BIN+'mysql','--defaults-extra-file='+str(cnf),'-e',q],check=True)
server=None
try:
    home=tmp/'home';home.mkdir()
    # Step 4: upload + extract into the account home.
    with zipfile.ZipFile(ZIP) as z:z.extractall(home)
    assert {p.name for p in home.iterdir()}>={'public_html','edfund-app','edfund-data','START-HERE.md','edfund-database.sql'}
    # The developer machine's PHP (8.5.7) is older than the production security floor, so the rehearsal copy
    # only (never the ZIP) skips that guard. A real host with a patched PHP needs no change.
    guard=home/'edfund-app/app/Preflight.php';code=guard.read_text();assert 'public static function patchFloorMet(): bool\n    {' in code
    guard.write_text(code.replace('public static function patchFloorMet(): bool\n    {','public static function patchFloorMet(): bool\n    {return true;',1))
    # Steps 2-3: create database/user, import the SQL as phpMyAdmin would.
    sql('CREATE DATABASE `%s` CHARACTER SET utf8mb4'%dbname)
    sql("CREATE USER '%s'@'localhost' IDENTIFIED BY '%s'"%(dbuser,dbpass));sql("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'localhost'"%(dbname,dbuser))
    with open(home/'edfund-database.sql','rb') as f:
        subprocess.run([BIN+'mysql','--defaults-extra-file='+str(cnf),dbname],stdin=f,check=True)
    tables=int(subprocess.check_output([BIN+'mysql','--defaults-extra-file='+str(cnf),'-N','-e',"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='%s'"%dbname],text=True))
    assert tables==18,tables
    # Re-importing must not erase anything.
    again=subprocess.run([BIN+'mysql','--defaults-extra-file='+str(cnf),dbname],stdin=open(home/'edfund-database.sql','rb'),capture_output=True)
    assert again.returncode!=0,'second import should stop on existing tables'
    # Step 5: the only edits are the star lines. A unix socket stands in for "localhost" on XAMPP.
    config=home/'edfund-data/config.php';text=config.read_text()
    text=text.replace("'cpanelusername_edfund';          // database name","'%s';          // database name"%dbname).replace("$DB_USER     = 'cpanelusername_edfund'","$DB_USER     = '%s'"%dbuser).replace('PASTE_THE_DATABASE_PASSWORD_HERE',dbpass).replace('TYPE_A_LONG_PRIVATE_PHRASE_HERE','rehearsal-setup-phrase-'+secrets.token_hex(4))
    text=text.replace("'mysql:host=localhost;dbname='","'mysql:unix_socket=%s;dbname='"%socket_path) if socket_path else text
    config.write_text(text);setup_key=re.search(r"\$SETUP_KEY\s*=\s*'([^']+)'",text).group(1)
    # Local stand-in for Apache+HTTPS: sets the HTTPS/host variables a real cPanel host provides.
    wrapper=tmp/'router.php'
    wrapper.write_text("<?php\n$_SERVER['HTTPS']='on';$_SERVER['HTTP_HOST']='edfundfoundation.org';if(isset($_SERVER['HTTP_ORIGIN'])){$_SERVER['HTTP_ORIGIN']='https://edfundfoundation.org';}\n"
        "$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$f=__DIR__.'/home/public_html'.$path;\n"
        "if($path!=='/' && is_file($f) && preg_match('~\\.(css|js|webp|png|jpe?g|ico|ttf|svg|woff2?)$~',$f)){$t=['css'=>'text/css','js'=>'text/javascript','webp'=>'image/webp','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','ico'=>'image/x-icon','ttf'=>'font/ttf','svg'=>'image/svg+xml'];header('Content-Type: '.($t[pathinfo($f,PATHINFO_EXTENSION)] ?? 'application/octet-stream'));readfile($f);return true;}\n"
        "chdir(__DIR__.'/home/public_html');require __DIR__.'/home/public_html/index.php';return true;\n")
    log=(tmp/'server.log').open('w')
    server=subprocess.Popen([PHP,'-S','127.0.0.1:%d'%port,str(wrapper)],cwd=tmp,stdout=log,stderr=log)
    for _ in range(100):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.1):break
        except OSError:time.sleep(.05)
    base='http://127.0.0.1:%d'%port
    p=sync_playwright().start();b=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True)
    human={'User-Agent':'Mozilla/5.0 (Macintosh) AppleWebKit/605 Safari/605'}
    anon=b.new_context(extra_http_headers=human).request
    # The live site, indexable.
    home_page=anon.get(base+'/headers',headers=human) if False else anon.get(base+'/')
    assert home_page.status==200,(home_page.status,home_page.text()[:300],(tmp/'server.log').read_text()[-600:])
    html=home_page.text()
    for needle in ['<title>ED Fund Uganda Foundation | Education','rel="canonical" href="https://edfundfoundation.org/"','name="robots" content="index,follow','application/ld+json','rel="apple-touch-icon"','"@type":"NGO"']:assert needle in html,needle
    assert home_page.headers.get('x-robots-tag','').startswith('index'),home_page.headers
    robots=anon.get(base+'/robots.txt');assert robots.status==200 and 'Sitemap: https://edfundfoundation.org/sitemap.xml' in robots.text() and 'Disallow: /admin' in robots.text() and not re.search(r'^Disallow:\s*/\s*$',robots.text(),re.M),robots.text()
    sitemap=anon.get(base+'/sitemap.xml');assert sitemap.status==200 and sitemap.text().count('<loc>')>=9,sitemap.text()[:300]
    assert anon.get(base+'/favicon.ico').status==200 and anon.get(base+'/assets/images/icon-192.png').status==200
    for route in ['/about-us/','/our-work/','/our-team/','/stories-updates/','/gallery/','/volunteer/','/contact/','/privacy-policy/']:
        r=anon.get(base+route);assert r.status==200 and r.text().count('<h1')==1 and 'index,follow' in r.text(),route
    assert anon.get(base+'/this-page-does-not-exist/').status==404
    # Private folders are not reachable (they are outside public_html), and setup stays closed without the key.
    assert anon.get(base+'/edfund-data/config.php').status in (404,403)
    # Step 6: create the owner in the browser.
    ctx=b.new_context(viewport={'width':1280,'height':900});page=ctx.new_page()
    page.goto(base+'/admin/setup')
    page.locator('[name=setup_key]').fill('wrong-key-value-12345');page.locator('[name=name]').fill('Site owner');page.locator('[name=password]').fill('rehearsal-pass');page.locator('[name=confirm]').fill('rehearsal-pass');page.get_by_role('button',name='Create account').click()
    assert 'setup key does not match' in page.locator('.alert').inner_text()
    page.locator('[name=setup_key]').fill(setup_key);page.locator('[name=name]').fill('Site owner');page.locator('[name=password]').fill('rehearsal-pass');page.locator('[name=confirm]').fill('rehearsal-pass');page.get_by_role('button',name='Create account').click()
    page.wait_for_url('**/admin/login')
    assert page.request.get(base+'/admin/setup').status==404,'setup must close once an owner exists'
    page.locator('[name=name]').fill('Site owner');page.locator('[name=password]').fill('rehearsal-pass');page.get_by_role('button',name='Continue',exact=True).click()
    page.wait_for_url('**/admin/')
    assert page.locator('.analytics').is_visible()
    page.goto(base+'/admin/pages');assert page.locator('.pe-page-card').count()>=9
    page.goto(base+'/admin/pages?document=home');page.wait_for_selector('#pe-section-list .pe-row, #editor-notice:not(:empty)');print('NOTICE',page.locator('#editor-notice').inner_text());assert page.locator('#pe-section-list .pe-row').count()==11,page.locator('#pe-section-list .pe-row-name').all_text_contents()
    # The app key and storage were created privately beside config.php.
    assert (home/'edfund-data/app.key').is_file() and (home/'edfund-data/storage').is_dir()
    # A visit was counted for the dashboard.
    page.goto(base+'/admin/');assert 'Page views' in page.content()
    print('PASS cPanel rehearsal: extract, SQL import (18 tables, no overwrite), three-line config, indexable live site, sitemap/robots, owner setup closing itself, admin login',flush=True)
    b.close();p.stop()
finally:
    if server:server.terminate()
    subprocess.run([BIN+'mysql','--defaults-extra-file='+str(cnf),'-e','DROP DATABASE IF EXISTS `%s`'%dbname],check=False)
    subprocess.run([BIN+'mysql','--defaults-extra-file='+str(cnf),'-e',"DROP USER IF EXISTS '%s'@'localhost'"%dbuser],check=False)
    shutil.rmtree(tmp,ignore_errors=True)
