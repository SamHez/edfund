<?php
declare(strict_types=1);
namespace EdFund;
final class Passwords
{
    public static function algorithm(): string|int { return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT; }
    public static function options(): array { return defined('PASSWORD_ARGON2ID') ? ['memory_cost'=>65536,'time_cost'=>3,'threads'=>1] : ['cost'=>12]; }
    public static function validate(string $value): void
    {
        $max = defined('PASSWORD_ARGON2ID') ? 128 : 72;
        if (str_contains($value, "\0") || !mb_check_encoding($value,'UTF-8') || mb_strlen($value,'UTF-8')<4 || strlen($value)>$max) { throw new \InvalidArgumentException("Use a password of at least 4 characters and no more than {$max} bytes."); }
    }
    public static function hash(string $value): string { self::validate($value); return password_hash($value,self::algorithm(),self::options()); }
}
