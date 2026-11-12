<?php
declare(strict_types=1);
// Phase 1 handles only /admin; the public website stays on its current HTML.
$path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
if ($path!=='/admin' && !str_starts_with($path,'/admin/')) {http_response_code(404);exit('Page not found.');}
require_once dirname(__DIR__).'/app/http.php';
