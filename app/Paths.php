<?php
declare(strict_types=1);
namespace EdFund;
/** Account paths come only from trusted configuration, never a request parameter. */
final class Paths
{
    public static function publicRoots(array $config=[]): array
    {
        $roots=[dirname(__DIR__).'/public'];
        if(defined('EDFUND_DOCUMENT_ROOT')){$roots[]=EDFUND_DOCUMENT_ROOT;}
        if(isset($config['document_root'])){$roots[]=$config['document_root'];}
        foreach($config['additional_public_roots'] ?? [] as $root){$roots[]=$root;}
        return array_values(array_unique(array_filter(array_map(fn($p)=>is_string($p)?realpath($p):false,$roots))));
    }
    public static function publicPath(string $path,array $config=[]): bool
    {
        $real=realpath($path);if(!$real){$parent=realpath(dirname($path));if(!$parent){throw new \RuntimeException('The private path parent must exist.');}$real=$parent.'/'.basename($path);}
        foreach(self::publicRoots($config) as $root){if($real===$root || str_starts_with($real,$root.'/')){return true;}}return false;
    }
    public static function backupKey(array $config): string
    {
        $path=$config['backup_key_file'] ?? dirname(__DIR__).'/config/backup.key';if(!is_string($path) || self::publicPath($path,$config)){throw new \RuntimeException('Backup key must be outside every configured document root.');}return $path;
    }
}
