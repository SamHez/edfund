<?php
declare(strict_types=1);
namespace EdFund;
use OTPHP\TOTP;
final class Mfa
{
    public static function generate(): string { return TOTP::generate(new Clock(),20)->getSecret(); }
    public static function otp(string $secret): TOTP { return TOTP::createFromSecret($secret,new Clock()); }
    public static function matchedStep(string $secret, string $code, int $lastStep, ?int $now=null): ?int
    {
        if (!preg_match('/\A[0-9]{6}\z/', $code)) { return null; }
        $step = intdiv($now ?? time(),30); $totp = self::otp($secret);
        foreach ([$step,$step-1,$step+1] as $candidate) {
            if ($candidate > $lastStep && hash_equals($totp->at($candidate*30),$code)) { return $candidate; }
        }
        return null;
    }
    public static function recoveryCodes(): array
    {
        return array_map(fn()=>strtoupper(implode('-',str_split(bin2hex(random_bytes(12)),6))),range(1,10));
    }
    public static function normalizeRecovery(string $value): string
    {
        $value = strtolower(str_replace('-','',trim($value)));
        return preg_match('/\A[a-f0-9]{24}\z/',$value) ? $value : '';
    }
}
