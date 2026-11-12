<?php
declare(strict_types=1);
// Published-only frontend. No admin session, preview token, draft lookup or import here.
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-cache');
try {
    $config=require __DIR__.'/bootstrap.php';
    if($config['environment']==='production' && !\EdFund\Preflight::patchFloorMet()){throw new RuntimeException('Runtime upgrade required.');}
    $db=\EdFund\Database::connect($config['db']);$c=new \EdFund\Content($db);
    $exists=(int)$db->query("SELECT COUNT(*) FROM cms_documents WHERE slug='home'")->fetchColumn();
    if(!$exists){readfile($publicPage);return;}
    if(basename(dirname($publicPage))==='public' && basename($publicPage)==='index.html'){echo $c->page();return;}
    // Existing page bodies remain untouched until their Phase 4 migration.
    $shared=$c->document('shared');$data=$c->revision((int)$shared['published_id'],'shared');
    $dom=new DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
    try{$dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($publicPage),LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    $xp=new DOMXPath($dom);
    foreach($data['sections'] as $s){$kind=$c->template($s['template'])['kind'];if($kind==='seo'){continue;}
        $query=match($kind){'topbar'=>'//div[contains(concat(" ",normalize-space(@class)," ")," topbar ")]','header'=>'//header','footer'=>'//footer'};
        $target=$xp->query($query)->item(0);if(!$target){continue;}$fragment=\EdFund\Content::dom($c->section($s));$node=$fragment->getElementById('cms-root')->firstChild;
        if($kind==='header'){$fx=new DOMXPath($fragment);foreach($fx->query('//nav//a') as $a){$a->removeAttribute('aria-current');if($a->getAttribute('href')===$path){$a->setAttribute('aria-current','page');}}}
        $target->parentNode->replaceChild($dom->importNode($node,true),$target);
    }
    echo preg_replace('/<\?xml encoding="UTF-8"\s*\?>/','',$dom->saveHTML());
}catch(Throwable $e){
    $reference=bin2hex(random_bytes(6));if(isset($config['storage_path'])){error_log(json_encode(['time'=>gmdate('c'),'reference'=>$reference,'exception'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]).PHP_EOL,3,$config['storage_path'].'/logs/errors.log');}
    http_response_code(503);header('Cache-Control: no-store');echo '<h1>The website is temporarily unavailable</h1><p>Please try again later. Reference '.$reference.'</p>';
}
