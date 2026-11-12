<?php
declare(strict_types=1);
// Called only with an isolated fixture config; never mutates the real edfund DB.
require dirname(__DIR__).'/vendor/autoload.php';
use EdFund\{Config,Database,Content,ContentImport,ContentConflict};
$config=Config::load();$db=Database::connect($config['db']);$name=$db->query('SELECT DATABASE()')->fetchColumn();
if(!preg_match('/\Aedfund_test_[a-f0-9]{12}\z/',$name)){throw new RuntimeException('Tests require an isolated fixture.');}
function check(bool $value,string $message): void{if(!$value){throw new RuntimeException($message);}echo 'PASS '.$message.PHP_EOL;}
$c=new Content($db);$import=new ContentImport($db);check($import->run(),'explicit one-time HTML import');
$state=$c->state('home');$shared=$c->state('shared');$original=$c->page();
file_put_contents($config['storage_path'].'/imported.html',$original);
check(!$import->run() && $c->state('home')===$state,'repeat import leaves all revisions and content unchanged');
$owner=(int)$db->query('SELECT id FROM cms_users LIMIT 1')->fetchColumn();$data=$state['content'];
$section=&$data['sections'][1];$t=$c->template($section['template']);$key=array_key_first(array_filter($t['fields'],fn($f)=>$f['type']==='heading'));
$section['values'][$key]='A private draft <script>alert(1)</script> & an opportunity';
$draft=$c->change('home',$state['version'],'save',$data,null,$owner);
check($c->page()===$original,'draft saves cannot change the published homepage');
$preview=$c->page($data,null,true);check(str_contains($preview,'&lt;script&gt;alert(1)&lt;/script&gt;')&&!str_contains($preview,'<script>alert(1)</script>'),'submitted markup renders as escaped text');
try{$c->change('home',$state['version'],'save',$data,null,$owner);throw new RuntimeException('Stale version accepted');}catch(ContentConflict $expected){check(true,'stale concurrent edit rejected');}
$published=$c->change('home',$draft['version'],'publish',$data,null,$owner);check(str_contains($c->page(),'A private draft'),'explicit atomic publishing changes the public page');
$restored=$c->change('home',$published['version'],'restore',null,$state['published'],$owner);check(str_contains($c->page(),'A private draft') && $restored['content']===$state['content'],'restoration creates a new draft without changing live content');
$again=$c->change('home',$restored['version'],'publish',$restored['content'],null,$owner);check($c->page()===$original,'publishing the restored revision reproduces the original homepage');
$removed=$state['content'];array_splice($removed['sections'],1,1);$without=$c->change('home',$again['version'],'publish',$removed,null,$owner);$restoreRemoved=$c->change('home',$without['version'],'restore',null,$state['published'],$owner);$c->change('home',$restoreRemoved['version'],'publish',$restoreRemoved['content'],null,$owner);check($c->page()===$original,'removed original sections remain restorable');
foreach(['javascript:alert(1)','//evil.test/','/\\evil.test','https://user:pass@example.org/'] as $url){check(!Content::url($url),'unsafe link scheme or destination rejected');}
$bad=$state['content'];$template=$c->template($bad['sections'][1]['template']);$image=array_key_first(array_filter($template['fields'],fn($f)=>$f['type']==='image'));$bad['sections'][1]['values'][$image]='../../config/local.php';
try{$c->validate($bad,'home');throw new RuntimeException('Invalid image accepted');}catch(InvalidArgumentException $expected){check(true,'images restricted to registered media IDs');}
$bad=$state['content'];$bad['sections'][0]['template']=str_repeat('a',64);try{$c->validate($bad,'home');throw new RuntimeException('Arbitrary template accepted');}catch(InvalidArgumentException $expected){check(true,'unknown layout refused');}
$bad=$state['content'];array_shift($bad['sections']);try{$c->validate($bad,'home');throw new RuntimeException('Missing hero accepted');}catch(InvalidArgumentException $expected){check(true,'page structure and single H1 protected');}
foreach($c->templates() as $t){if((int)$t['approved']!==1){continue;}$added=$state['content'];$added['sections'][]=['id'=>bin2hex(random_bytes(12)),'template'=>$t['id'],'values'=>$t['defaults']];$c->validate($added,'home');check(str_contains($c->page($added),$t['kind']==='team'?'team-card':'<section'),'approved '.$t['kind'].' layout validates and renders');}
$before=$c->state('home');$import->registerLayouts();check(!$import->run() && $c->state('home')===$before,'code/layout update and repeated import preserve client revisions');
$adminConfig=require dirname(__DIR__).'/config/migrations.local.php';$adminConfig['dsn']=preg_replace('/dbname=edfund(?=;|$)/','dbname='.$name,$adminConfig['dsn']);$verify=Database::connect($adminConfig);check($verify->query('SELECT content FROM legacy_sentinel')->fetchColumn()==='preserve existing content','pre-existing database content preserved');
