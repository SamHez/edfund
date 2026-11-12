<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use EdFund\{Crypto,Passwords,Preflight};
function check(bool $value,string $name): void {if(!$value){throw new RuntimeException($name);}echo 'PASS '.$name.PHP_EOL;}
$crypto=new Crypto(random_bytes(32));$secret=base64_encode(random_bytes(32));$sealed=$crypto->seal($secret);
check($crypto->open($sealed)===$secret && !str_contains($sealed,$secret),'legacy encrypted-data compatibility');
$raw=base64_decode(substr($sealed,3));$raw[29]=chr(ord($raw[29])^1);
try{$crypto->open('v1:'.base64_encode($raw));throw new LogicException('Tamper accepted');}catch(RuntimeException $expected){check(true,'tampered ciphertext rejected');}
try{(new Crypto(random_bytes(32)))->open($sealed);throw new LogicException('Wrong key accepted');}catch(RuntimeException $expected){check(true,'wrong encryption key rejected');}
$hash=Passwords::hash('A long unique owner passphrase.');check(password_verify('A long unique owner passphrase.',$hash),'native password hashing');
try{Passwords::hash('123');throw new LogicException('Weak passphrase accepted');}catch(InvalidArgumentException $expected){check(true,'short passwords rejected');}
check(!Preflight::patchFloorMet() || PHP_VERSION_ID>=80336,'public hosting patch policy is explicit');

$four=Passwords::hash('1234');check(password_verify('1234',$four),'four-character password accepted and hashed');
try{Passwords::hash('éé');throw new LogicException('Two multibyte characters accepted');}catch(InvalidArgumentException $expected){check(true,'password minimum counts characters, not UTF-8 bytes');}
