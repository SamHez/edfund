<?php
declare(strict_types=1);
// Public bridge only. Secrets and application code live outside every web root.
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
header('X-Content-Type-Options: nosniff');
try {
    $paths=['application'=>dirname(__DIR__).'/edfund-app','configuration'=>dirname(__DIR__).'/edfund-data/config.php'];
    if(is_file(__DIR__.'/edfund-paths.php')){$paths=require __DIR__.'/edfund-paths.php';}
    if(!is_array($paths) || !isset($paths['application'],$paths['configuration']) || !is_string($paths['application']) || !is_string($paths['configuration'])){throw new RuntimeException('Invalid installation paths.');}
    $app=realpath($paths['application']);$config=realpath($paths['configuration']);$web=realpath(__DIR__);
    foreach([$app,$config] as $private){if(!$private || $private===$web || str_starts_with($private,$web.'/')){throw new RuntimeException('Private installation paths are unavailable.');}}
    define('EDFUND_DOCUMENT_ROOT',$web);define('EDFUND_CONFIG_FILE',$config);
    require $app.'/public/index.php';
} catch(Throwable $error) {
    http_response_code(503);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EdFund maintenance</title><h1>EdFund is temporarily unavailable</h1><p>Please contact the site maintainer.</p></html>';
}
