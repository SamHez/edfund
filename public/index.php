<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
if($path==='/admin' || str_starts_with($path,'/admin/')){require dirname(__DIR__).'/app/http.php';return;}
require dirname(__DIR__).'/app/public.php';
