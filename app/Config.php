<?php
declare(strict_types=1);
namespace EdFund;
final class Config
{
    public static function load(): array
    {
        $root = dirname(__DIR__);
        $path = defined('EDFUND_CONFIG_FILE') ? EDFUND_CONFIG_FILE : (getenv('EDFUND_CONFIG') ?: $root.'/config/local.php');
        $real = realpath($path);
        if (!$real || Paths::publicPath($real)) {
            throw new \RuntimeException('Private configuration is missing. Run the CLI local setup.');
        }
        $config = require $real;
        if (!is_array($config) || !in_array($config['environment'] ?? '', ['local', 'production'], true)) {
            throw new \RuntimeException('Invalid environment configuration.');
        }
        if(Paths::publicPath($real,$config)){throw new \RuntimeException('Configuration must be outside every document root.');}
        if(defined('EDFUND_DOCUMENT_ROOT') && isset($config['document_root']) && realpath($config['document_root'])!==realpath(EDFUND_DOCUMENT_ROOT)){throw new \RuntimeException('Configured document root does not match the web entry point.');}
        $key = base64_decode($config['app_key'] ?? '', true);
        if ($key === false || strlen($key) !== 32) { throw new \RuntimeException('Invalid application key.'); }
        $url = parse_url($config['base_url'] ?? '');
        if (!$url || !isset($url['host'], $url['scheme']) || !in_array($url['scheme'], ['http','https'], true) || isset($url['user']) || !empty($url['path'])) {
            throw new \RuntimeException('Base URL must be an origin without a path.');
        }
        if ($config['environment'] === 'production' && $url['scheme'] !== 'https') { throw new \RuntimeException('Production requires HTTPS.'); }
        if ($config['environment'] === 'local' && !in_array($url['host'], ['127.0.0.1','localhost','[::1]'], true)) { throw new \RuntimeException('Local mode requires a loopback origin.'); }
        $storage = $config['storage_path'] ?? $root.'/storage';
        if(Paths::publicPath($storage,$config)){throw new \RuntimeException('Storage must be outside every document root.');}
        if (!is_dir($storage) && !mkdir($storage, 0700, true)) { throw new \RuntimeException('Cannot create private storage.'); }
        $storage = realpath($storage);
        if (!$storage || Paths::publicPath($storage,$config)) { throw new \RuntimeException('Storage must be outside public.'); }
        foreach (['sessions','logs'] as $directory) {
            $target = $storage.'/'.$directory;
            if (!is_dir($target) && !mkdir($target, 0700, true)) { throw new \RuntimeException('Cannot create private runtime directory.'); }
        }
        $config['storage_path'] = $storage;
        $config['key_bytes'] = $key;
        return $config;
    }
}
