<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use EdFund\{Crypto,Mfa,Passwords,Preflight};
function check(bool $value,string $name): void {if(!$value){throw new RuntimeException($name);}echo 'PASS '.$name.PHP_EOL;}
$crypto=new Crypto(random_bytes(32));$secret=Mfa::generate();$sealed=$crypto->seal($secret);
check($crypto->open($sealed)===$secret && !str_contains($sealed,$secret),'MFA secret authenticated encryption');
$raw=base64_decode(substr($sealed,3));$raw[29]=chr(ord($raw[29])^1);
try{$crypto->open('v1:'.base64_encode($raw));throw new LogicException('Tamper accepted');}catch(RuntimeException $expected){check(true,'tampered ciphertext rejected');}
try{(new Crypto(random_bytes(32)))->open($sealed);throw new LogicException('Wrong key accepted');}catch(RuntimeException $expected){check(true,'wrong encryption key rejected');}
$now=1760000010;$code=Mfa::otp($secret)->at($now);$step=Mfa::matchedStep($secret,$code,-1,$now);
check($step===intdiv($now,30),'TOTP verifies through maintained OTPHP library');
check(Mfa::matchedStep($secret,$code,$step,$now)===null,'TOTP replay refused');
check(Mfa::matchedStep($secret,'<script>',-1,$now)===null,'malformed TOTP rejected');
$codes=Mfa::recoveryCodes();check(count(array_unique($codes))===10 && strlen(Mfa::normalizeRecovery($codes[0]))===24,'ten independent high-entropy recovery codes');
check(Mfa::normalizeRecovery('../invalid')==='','malformed recovery input rejected');
$hash=Passwords::hash('A long unique owner passphrase.');check(password_verify('A long unique owner passphrase.',$hash),'native password hashing');
try{Passwords::hash('short');throw new LogicException('Weak passphrase accepted');}catch(InvalidArgumentException $expected){check(true,'short passwords rejected');}
check(!Preflight::patchFloorMet() || PHP_VERSION_ID>=80336,'public hosting patch policy is explicit');
