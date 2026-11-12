<?php
declare(strict_types=1);
namespace EdFund;
final class YouTube
{
    public static function id(string $url,bool $allowStoredId=true): string
    {
        if($allowStoredId && preg_match('/\A[A-Za-z0-9_-]{11}\z/',$url)){return $url;}
        if(strlen($url)>1000 || preg_match('/[\x00-\x20\\\\]/',$url)){throw new \InvalidArgumentException('Enter a valid HTTPS YouTube link.');}
        $p=parse_url($url);$host=strtolower($p['host'] ?? '');$path=$p['path'] ?? '';
        if(($p['scheme'] ?? '')!=='https' || isset($p['user'],$p['pass']) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port']!==443)){throw new \InvalidArgumentException('Use an HTTPS YouTube link without account credentials or a custom port.');}
        $id='';if($host==='youtu.be'){$id=trim($path,'/');}
        elseif(in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'],true)){
            if($path==='/watch'){parse_str($p['query'] ?? '',$q);$id=is_string($q['v'] ?? null)?$q['v']:'';}
            elseif(preg_match('~\A/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})/?\z~',$path,$m)){$id=$m[1];}
        }
        if(!preg_match('/\A[A-Za-z0-9_-]{11}\z/',$id)){throw new \InvalidArgumentException('Use a youtube.com or youtu.be video link.');}return $id;
    }
}
