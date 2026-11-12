<?php
declare(strict_types=1);
// Development only. Apache/cPanel cutover belongs to Phase 5.
$public=__DIR__.'/public';$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/');
if(str_contains($path,"\0") || preg_match('~(?:^|/)\.[^/]*~',$path) || str_contains($path,'\\')){http_response_code(404);exit('Page not found.');}
if($path==='/admin' || str_starts_with($path,'/admin/')){require $public.'/index.php';return true;}
$file=realpath($public.$path);$types=['css'=>'text/css; charset=utf-8','js'=>'text/javascript; charset=utf-8','json'=>'application/json','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon','ttf'=>'font/ttf','woff2'=>'font/woff2'];$ext=$file?strtolower(pathinfo($file,PATHINFO_EXTENSION)):'';
if($file && str_starts_with($file,$public.'/') && is_file($file) && isset($types[$ext])){header('Content-Type: '.$types[$ext]);header('X-Content-Type-Options: nosniff');readfile($file);return true;}
require __DIR__.'/app/public.php';return true;
