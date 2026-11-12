<?php
declare(strict_types=1);
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
// Set protections even when configuration/database bootstrap fails.
header('Cache-Control: no-store, private, max-age=0');header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');header('Content-Type: text/html; charset=utf-8');
try {
    $config=require __DIR__.'/bootstrap.php';
    if ($config['environment']==='production' && !\EdFund\Preflight::patchFloorMet()) {throw new RuntimeException('Runtime security baseline requires update.');}
    \EdFund\Web::headers();
    (new \EdFund\Web($config))->run($path);
} catch (Throwable $error) {
    $reference=bin2hex(random_bytes(6));
    if (isset($config['storage_path'])) {
        $record=json_encode(['time'=>gmdate('c'),'reference'=>$reference,'exception'=>get_class($error),'file'=>basename($error->getFile()),'line'=>$error->getLine()]);
        error_log($record.PHP_EOL,3,$config['storage_path'].'/logs/errors.log');
    }
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Administration unavailable</title><h1>Administration is temporarily unavailable</h1><p>Please contact your site maintainer and quote reference '.htmlspecialchars($reference,ENT_QUOTES,'UTF-8').'.</p></html>';
}
