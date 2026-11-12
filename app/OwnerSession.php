<?php
declare(strict_types=1);
namespace EdFund;

/** One session and authorization policy for the admin and public toolbar. */
final class OwnerSession
{
    public const NAME='edfund_owner';
    public static function present(): bool {return isset($_COOKIE[self::NAME]);}
    public static function start(array $config,bool $create=true): bool
    {
        if(!$create && !self::present()){return false;}
        ini_set('display_errors','0');ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');
        session_save_path($config['storage_path'].'/sessions');session_name(self::NAME);session_cache_limiter('');ini_set('session.gc_maxlifetime','28800');
        $https=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Strict']);
        if(!session_start()){throw new \RuntimeException('Session unavailable.');}
        // Upgrade the previous /admin cookie on the next admin visit. Expire it to
        // avoid duplicate same-name cookies; retain the existing verified session.
        if($create){self::cookie(session_id(),0,'/');self::cookie('',time()-3600,'/admin');}
        $now=time();
        if(isset($_SESSION['started']) && ($now-(int)$_SESSION['started']>28800 || $now-(int)($_SESSION['seen'] ?? 0)>1800)){self::clear();}
        $_SESSION['started'] ??= $now;$_SESSION['seen']=$now;$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        return true;
    }
    public static function clear(): void
    {
        $_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['started']=time();$_SESSION['seen']=time();
    }
    public static function user(Auth $auth): ?array
    {
        if(($_SESSION['auth_method'] ?? '')!=='password' || !isset($_SESSION['owner_id'],$_SESSION['version'])){return null;}
        $user=$auth->user((int)$_SESSION['owner_id']);
        return $user && (int)$user['session_version']===(int)$_SESSION['version']?$user:null;
    }
    private static function cookie(string $value,int $expires,string $path): void
    {
        setcookie(self::NAME,$value,['expires'=>$expires,'path'=>$path,'secure'=>isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
    }
    public static function logout(): void
    {
        $_SESSION=[];session_destroy();self::cookie('',time()-3600,'/');self::cookie('',time()-3600,'/admin');
    }
}
