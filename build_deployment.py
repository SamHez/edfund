#!/usr/bin/env python3
"""Build the PHP CMS cPanel release without rebuilding HTML or touching client data."""
from pathlib import Path
import hashlib,json,os,shutil,subprocess,sys,tempfile,zipfile
from datetime import datetime,timezone
ROOT=Path(__file__).resolve().parent
OUTPUT=ROOT/'deployment';FILENAME='edfund-cpanel-ready.zip'
DOCS=['CMS-RELEASE-STATUS.md','CMS-CPANEL-RELEASE.md','CMS-EDITING-GUIDE.md','CMS-PHASE-4-OPERATIONS.md','CMS-PHASE-PROMPTS.md']
def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def files(root):return sorted(p for p in root.rglob('*') if p.is_file() and p.name!='.DS_Store' and '__pycache__' not in p.parts)
def copy_tree(source,dest):
 for path in files(source):
  if path.is_symlink():raise RuntimeError('Symlinks are not allowed in release inputs')
  target=dest/path.relative_to(source);target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(path,target)
def main():
 if len(sys.argv)!=1:raise SystemExit('Usage: python3 build_deployment.py')
 for command in [[sys.executable,'source/check_site.py'],[sys.executable,'source/check_seo.py']]:subprocess.run(command,cwd=ROOT,check=True)
 for name in DOCS:
  if not (ROOT/'docs'/name).is_file():raise RuntimeError('Missing release documentation: '+name)
 with tempfile.TemporaryDirectory(prefix='edfund-release-') as temporary:
  stage=Path(temporary);app=stage/'edfund-app';web=stage/'public_html';app.mkdir();web.mkdir()
  for name in ['app','bin','public','database/migrations']:copy_tree(ROOT/name,app/name)
  for name in ['composer.json','composer.lock']:shutil.copyfile(ROOT/name,app/name)
  (app/'config').mkdir()
  for name in ['production.example.php','local.example.php']:shutil.copyfile(ROOT/'config'/name,app/'config'/name)
  for name in DOCS:
   (app/'docs').mkdir(exist_ok=True);shutil.copyfile(ROOT/'docs'/name,app/'docs'/name)
  (app/'.htaccess').write_text('Require all denied\n')
  # Fresh locked production dependencies: never copy an arbitrary local vendor tree.
  subprocess.run(['composer','install','--no-dev','--prefer-dist','--no-interaction','--no-scripts','--no-plugins','--optimize-autoloader'],cwd=app,check=True)
  subprocess.run(['composer','check-platform-reqs','--no-dev'],cwd=app,check=True)
  copy_tree(ROOT/'public/assets',web/'assets')
  shutil.copyfile(ROOT/'release/index.php',web/'index.php');shutil.copyfile(ROOT/'release/edfund-paths.example.php',web/'edfund-paths.example.php');shutil.copyfile(ROOT/'public/.htaccess',web/'.htaccess')
  shutil.copyfile(ROOT/'docs/CMS-CPANEL-RELEASE.md',stage/'INSTALL.md')
  manifest={'format':1,'origin':'https://edfundfoundation.org','built_utc':datetime.now(timezone.utc).isoformat(),'source_commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),'source_dirty':bool(subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True).strip()),'app_files':{str(p.relative_to(app)):digest(p) for p in files(app)},'web_files':{str(p.relative_to(web)):digest(p) for p in files(web)}}
  (app/'RELEASE.json').write_text(json.dumps(manifest,indent=2,sort_keys=True)+'\n')
  for path in files(stage):
   rel=path.relative_to(stage)
   if path.suffix=='.php':subprocess.run(['php','-l',str(path)],stdout=subprocess.DEVNULL,check=True)
   if path.name in ['local.php','config.php','backup.key','migrations.local.php'] or any(x in rel.parts for x in ['.git','storage','uploads','node_modules','tests']):raise RuntimeError('Forbidden runtime/development file in archive: '+str(rel))
  OUTPUT.mkdir(exist_ok=True);pending=OUTPUT/(FILENAME+'.pending');destination=OUTPUT/FILENAME
  try:
   with zipfile.ZipFile(pending,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
    for path in files(stage):
     entry=zipfile.ZipInfo(path.relative_to(stage).as_posix(),(2020,1,1,0,0,0));entry.external_attr=0o100644<<16;entry.compress_type=zipfile.ZIP_DEFLATED;archive.writestr(entry,path.read_bytes())
   with zipfile.ZipFile(pending) as archive:
    assert archive.testzip() is None
    names=set(archive.namelist());assert {'public_html/index.php','public_html/.htaccess','edfund-app/vendor/autoload.php','edfund-app/RELEASE.json','INSTALL.md'}<=names
    assert not any(n.startswith('public_html/') and n.endswith('.html') for n in names)
    assert not any(n.startswith('edfund-data/') for n in names)
    for folder,key in [('edfund-app','app_files'),('public_html','web_files')]:
     for path,value in manifest[key].items():assert hashlib.sha256(archive.read(folder+'/'+path)).hexdigest()==value
   os.replace(pending,destination)
  finally:pending.unlink(missing_ok=True)
  checksum=digest(destination);(OUTPUT/(FILENAME+'.sha256')).write_text(checksum+'  '+FILENAME+'\n')
  (OUTPUT/'release-summary.json').write_text(json.dumps({'zip':FILENAME,'sha256':checksum,'bytes':destination.stat().st_size,'files':len(names),'source_commit':manifest['source_commit'],'source_dirty':manifest['source_dirty'],'deployed':False,'runtime':'PHP/MariaDB; no server-side Composer/Node/Python/worker'},indent=2)+'\n')
  print(f'Ready: {destination}\nSHA256: {checksum}\n{len(names)} files; {destination.stat().st_size/1048576:.2f} MiB\nExtract privately first; read INSTALL.md. Nothing was deployed.')
if __name__=='__main__':main()
