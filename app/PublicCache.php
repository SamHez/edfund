<?php
declare(strict_types=1);
namespace EdFund;
final class PublicCache
{
    private string $directory;
    public function __construct(private readonly \PDO $db,private readonly array $config){$this->directory=$config['storage_path'].'/cache';}
    public static function eligible(): bool {return empty($_SERVER['HTTP_COOKIE']) && empty($_SERVER['HTTP_AUTHORIZATION']) && empty($_SERVER['QUERY_STRING']) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true);}
    public function key(string $route): string
    {
        $documents=$this->db->query('SELECT slug,published_id FROM cms_documents ORDER BY slug')->fetchAll();$posts=$this->db->query('SELECT id,published_id,published_slug,trashed_at FROM cms_posts ORDER BY id')->fetchAll();$code=[];
        foreach(array_merge(glob(__DIR__.'/*.php'),[dirname(__DIR__).'/public/assets/css/styles.css',dirname(__DIR__).'/public/assets/js/main.js']) as $file){$code[]=hash_file('sha256',$file);}
        return hash('sha256',json_encode([$route,$documents,$posts,$this->db->query('SELECT document_slug,seo_defaults FROM cms_pages ORDER BY document_slug')->fetchAll(),$code,Seo::indexing($this->config)],JSON_THROW_ON_ERROR));
    }
    public function get(string $key): ?string
    {
        if(!preg_match('/\A[a-f0-9]{64}\z/',$key)){return null;}$file=$this->directory.'/'.$key.'.html';if(!is_file($file) || filemtime($file)<time()-300){return null;}$content=@file_get_contents($file);return $content===false?null:$content;
    }
    public function put(string $key,string $html): void
    {
        if(strlen($html)>1048576){return;}if(!is_dir($this->directory) && !@mkdir($this->directory,0700,true)){return;}$lock=@fopen($this->directory.'/.lock','c');if(!$lock){return;}
        try{if(!flock($lock,LOCK_EX)){return;}$file=$this->directory.'/'.$key.'.html';$tmp=$this->directory.'/'.bin2hex(random_bytes(12)).'.pending';$h=@fopen($tmp,'x');if(!$h){return;}chmod($tmp,0600);try{$written=fwrite($h,$html);fflush($h);}finally{fclose($h);}if($written===strlen($html)){if(!@rename($tmp,$file)){@unlink($tmp);}}else{@unlink($tmp);}
            $files=glob($this->directory.'/*.html');usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));$total=0;foreach($files as $i=>$path){$total+=filesize($path);if($i>=64 || $total>33554432 || filemtime($path)<time()-300){@unlink($path);}}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
