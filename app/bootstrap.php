<?php
declare(strict_types=1);
if (PHP_VERSION_ID < 80300) { throw new RuntimeException('PHP 8.3 or newer is required.'); }
require dirname(__DIR__).'/vendor/autoload.php';
date_default_timezone_set('UTC');
return \EdFund\Config::load();
