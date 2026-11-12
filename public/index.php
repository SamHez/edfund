<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
if($path==='/admin' || str_starts_with($path,'/admin/')){require dirname(__DIR__).'/app/http.php';return;}
// The development router supplies only a resolved, existing public HTML page.
if(!isset($publicPage)){
    if(in_array($path,['/','/index.php','/index.html'],true)){$publicPage=__DIR__.'/index.html';}
    else{http_response_code(404);exit('Page not found.');}
}
require dirname(__DIR__).'/app/public.php';
