<?php
declare(strict_types=1);
// Published-only frontend. No admin session, draft lookup or import.
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-cache');
try {
    $config=require __DIR__.'/bootstrap.php';
    if($config['environment']==='production' && !\EdFund\Preflight::patchFloorMet()){throw new RuntimeException('Runtime upgrade required.');}
    $db=\EdFund\Database::connect($config['db']);$c=new \EdFund\Content($db);
    if(preg_match('~\A/media/([a-f0-9]{64})/(large|small|thumb)\.webp\z~',$path,$match)){
        if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');return;}(new \EdFund\Media($db,$config))->serve($match[1],$match[2].'.webp',false);return;
    }
    $exists=(int)$db->query("SELECT COUNT(*) FROM cms_documents WHERE slug='home'")->fetchColumn();
    if(!$exists){if(!empty($postUnknown)){http_response_code(404);readfile(dirname(__DIR__).'/public/404.html');}else{readfile($publicPage);}return;}
    if(isset($storySlug)){
        $posts=new \EdFund\Posts($db,$config);$row=$posts->route($storySlug);
        if($row){
            if($row['trashed_at'] || !$row['published_id']){http_response_code($row['trashed_at']?410:404);readfile(dirname(__DIR__).'/public/404.html');return;}
            if($row['published_slug']!==$storySlug){header('Location: /stories-updates/'.$row['published_slug'].'/',true,301);return;}
            $s=$db->prepare('SELECT action FROM cms_post_revisions WHERE id=?');$s->execute([$row['published_id']]);
            if($s->fetchColumn()==='import' && $row['imported_html']){echo \EdFund\PostView::shared($db,$row['imported_html'],'/stories-updates/');return;}
            echo \EdFund\PostView::page($db,$config,$posts->revision((int)$row['published_id'],(int)$row['id']));return;
        }
        if(!empty($postUnknown)){http_response_code(404);readfile(dirname(__DIR__).'/public/404.html');return;}
    }
    if(basename(dirname($publicPage))==='public' && basename($publicPage)==='index.html'){echo $c->page();return;}
    $html=\EdFund\PostView::shared($db,file_get_contents($publicPage),$path);
    if($path==='/stories-updates/' || $path==='/stories-updates/index.html'){$html=\EdFund\PostView::updateCards($db,$html,false);}echo $html;
}catch(Throwable $e){
    $reference=bin2hex(random_bytes(6));if(isset($config['storage_path'])){error_log(json_encode(['time'=>gmdate('c'),'reference'=>$reference,'exception'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]).PHP_EOL,3,$config['storage_path'].'/logs/errors.log');}
    http_response_code(503);header('Cache-Control: no-store');echo '<h1>The website is temporarily unavailable</h1><p>Please try again later. Reference '.$reference.'</p>';
}
