#!/usr/bin/env python3
"""Build edfund-database.sql: schema + the site's imported content, ready for phpMyAdmin.

A throw-away local database is migrated and filled with the repository's trusted content using the
normal CLI imports, then exported. The export deliberately has no owner account, sessions, audit rows,
analytics rows or credentials, and never drops existing tables. The local `edfund` database is not read.
"""
from pathlib import Path
import base64,json,os,secrets,shutil,subprocess,sys,tempfile
ROOT=Path(__file__).resolve().parent.parent
PHP=shutil.which('php') or sys.exit('PHP is required')
BIN='/Applications/XAMPP/xamppfiles/bin/'
DATA_EXCLUDED=['cms_users','cms_recovery_codes','cms_auth_attempts','cms_audit','cms_visit_daily','cms_visit_visitors']

def admin_connection():
    code="echo json_encode(require 'config/migrations.local.php');"
    return json.loads(subprocess.check_output([PHP,'-r',code],cwd=ROOT,text=True))

def main(output: Path):
    admin=admin_connection()
    socket=next((p.split('=',1)[1] for p in admin['dsn'].split(':',1)[1].split(';') if p.startswith('unix_socket=')),None)
    name='edfund_build_'+secrets.token_hex(5)
    with tempfile.TemporaryDirectory(prefix='edfund-sql-') as tmp:
        tmp=Path(tmp);os.chmod(tmp,0o700)
        defaults=tmp/'client.cnf'
        defaults.write_text('[client]\nuser=%s\npassword=%s\n%s'%(admin['user'],admin['password'],('socket=%s\n'%socket) if socket else ''));os.chmod(defaults,0o600)
        client=[BIN+'mysql','--defaults-extra-file='+str(defaults)]
        dump=[BIN+'mysqldump','--defaults-extra-file='+str(defaults)]
        dsn=admin['dsn'].replace('dbname=edfund','dbname='+name) if 'dbname=' in admin['dsn'] else admin['dsn']+';dbname='+name
        config=tmp/'config.php';migration=tmp/'migration.php'
        config.write_text("<?php\nreturn ["+
            "'environment'=>'local','base_url'=>'http://127.0.0.1:8099',"+
            "'app_key'=>%s,'storage_path'=>%s,'db'=>['dsn'=>%s,'user'=>%s,'password'=>%s]];\n"%tuple(__import__('json').dumps(v) for v in (base64.b64encode(secrets.token_bytes(32)).decode(),str(tmp/'storage'),dsn,admin['user'],admin['password'])))
        os.chmod(config,0o600)
        subprocess.run(client+['-e','CREATE DATABASE `%s` CHARACTER SET utf8mb4'%name],check=True)
        try:
            env=dict(os.environ,EDFUND_CONFIG=str(config))
            subprocess.run([PHP,'-r',"require 'vendor/autoload.php';$c=require getenv('EDFUND_CONFIG');$d=EdFund\\Database::connect($c['db']);echo count(EdFund\\Migrations::run($d)).' migrations applied'.PHP_EOL;"],cwd=ROOT,env=env,check=True)
            for command in ['content:import','posts:import','pages:import','media:index']:
                subprocess.run([PHP,'bin/cms.php',command],cwd=ROOT,env=env,check=True,stdout=subprocess.DEVNULL)
            ignores=['--ignore-table=%s.%s'%(name,t) for t in DATA_EXCLUDED]
            common=['--no-tablespaces','--skip-comments','--skip-add-locks','--skip-triggers','--default-character-set=utf8mb4','--hex-blob']
            structure=subprocess.check_output(dump+common+['--no-data','--skip-add-drop-table',name],text=True)
            data=subprocess.check_output(dump+common+['--no-create-info','--single-transaction','--complete-insert']+ignores+[name],text=True)
            header=('-- EdFund website database installer (phpMyAdmin > Import).\n'
                    '-- Creates the CMS tables and the current site content. It never drops existing tables.\n'
                    '-- It contains no passwords: you create the owner account in the browser at /admin/setup.\n'
                    'SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\';\n\n')
            footer='\nSET FOREIGN_KEY_CHECKS=1;\n'
            output.write_text(header+structure+'\n'+data+footer,encoding='utf-8')
        finally:
            subprocess.run(client+['-e','DROP DATABASE IF EXISTS `%s`'%name],check=False)
    text=output.read_text(encoding='utf-8')
    for forbidden in ['INSERT INTO `cms_users`','INSERT INTO `cms_audit`','INSERT INTO `cms_visit','DROP TABLE']:
        if forbidden in text:raise SystemExit('Forbidden content in export: '+forbidden)
    print('Wrote %s (%.2f MiB)'%(output,output.stat().st_size/1048576))

if __name__=='__main__':
    main(Path(sys.argv[1]) if len(sys.argv)>1 else ROOT/'deployment'/'edfund-database.sql')
