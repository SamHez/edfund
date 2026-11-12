<?php
declare(strict_types=1);
// Development server only: php -S 127.0.0.1:8089 -t public router.php
$public=__DIR__.'/public';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/');
if (str_contains($path,"\0") || preg_match('~(?:^|/)\.[^/]*~',$path) || str_contains($path,'\\')) {http_response_code(404);exit('Page not found.');}
if ($path==='/admin' || str_starts_with($path,'/admin/')) {require $public.'/index.php';return true;}
$file=realpath($public.$path);
if ($file && str_starts_with($file,$public.'/') && is_dir($file)) {$file=realpath($file.'/index.html');}
if ($path==='/') {$file=$public.'/index.html';}
$types=['html'=>'text/html; charset=utf-8','css'=>'text/css; charset=utf-8','js'=>'text/javascript; charset=utf-8','json'=>'application/json','xml'=>'application/xml','txt'=>'text/plain','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon','ttf'=>'font/ttf','woff2'=>'font/woff2'];
$extension=$file ? strtolower(pathinfo($file,PATHINFO_EXTENSION)) : '';
if ($file && str_starts_with($file,$public.'/') && is_file($file) && isset($types[$extension])) {
    header('Content-Type: '.$types[$extension]);header('X-Content-Type-Options: nosniff');readfile($file);return true;
}
http_response_code(404);header('Content-Type: text/html; charset=utf-8');readfile($public.'/404.html');return true;
