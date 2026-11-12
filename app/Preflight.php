<?php
declare(strict_types=1);
namespace EdFund;
final class Preflight
{
    public static function patchFloorMet(): bool
    {
        return match(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION) {
            '8.3'=>version_compare(PHP_VERSION,'8.3.36','>='),
            '8.4'=>version_compare(PHP_VERSION,'8.4.27','>='),
            '8.5'=>version_compare(PHP_VERSION,'8.5.12','>='),
            default=>false,
        };
    }
    public static function report(array $config): array
    {
        $gd=extension_loaded('gd')?gd_info():[];$extensions=[];
        foreach(['dom','pdo_mysql','fileinfo','gd','exif','mbstring','openssl','session'] as $extension) {$extensions[$extension]=extension_loaded($extension);}
        return [
            'PHP'=>PHP_VERSION,'Web/CLI handler'=>PHP_SAPI,'Environment'=>$config['environment'],
            'Minimum PHP 8.3'=>PHP_VERSION_ID>=80300?'pass':'fail',
            'October 2026 security patch baseline'=>self::patchFloorMet()?'met; still verify current vendor patches':'upgrade required before public hosting',
            'Extensions'=>$extensions,'JPEG'=>$gd['JPEG Support'] ?? false,'PNG'=>$gd['PNG Support'] ?? false,'WebP'=>$gd['WebP Support'] ?? false,
            'Memory limit'=>ini_get('memory_limit'),'Execution limit'=>ini_get('max_execution_time'),
            'Upload limit'=>ini_get('upload_max_filesize'),'POST limit'=>ini_get('post_max_size'),
            'Database'=>Database::connect($config['db'])->query('SELECT VERSION()')->fetchColumn(),
            'Private sessions'=>is_writable($config['storage_path'].'/sessions')?'ready':'not writable',
            'Password hashing'=>defined('PASSWORD_ARGON2ID')?'Argon2id':'bcrypt with 72-byte input limit',
            'Email password recovery'=>'disabled; private CLI recovery available',
        ];
    }
}
