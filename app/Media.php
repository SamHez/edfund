<?php
declare(strict_types=1);
namespace EdFund;
final class Media
{
    public function __construct(private readonly \PDO $db,private readonly array $config) {}
    public static function iniBytes(string $value): int
    {
        $v=trim($value);if($v==='-1'){return PHP_INT_MAX;}$n=(int)$v;return $n*match(strtolower(substr($v,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
    }
    public function limits(): array
    {
        $memory=self::iniBytes(ini_get('memory_limit'));$pixels=max(0,min(4000000,intdiv(max(0,$memory-memory_get_usage(true)-33554432),16)));
        return ['bytes'=>min(2097152,self::iniBytes(ini_get('upload_max_filesize')),max(0,self::iniBytes(ini_get('post_max_size'))-65536)),'pixels'=>$pixels,'quota'=>min(536870912,(int)($this->config['media_quota_bytes'] ?? 536870912))];
    }
    public function all(bool $private=false): array
    {
        $rows=$this->db->query('SELECT m.*,COALESCE(d.alt,\'\') AS alt,COALESCE(d.caption,\'\') AS caption,d.storage_key,d.deleted_at,d.created_at FROM cms_media m LEFT JOIN cms_media_details d ON d.media_id=m.id ORDER BY m.label')->fetchAll();
        foreach($rows as &$m){$m['thumbnail']=$m['storage_key']?'/admin/media-file/'.$m['id'].'/thumb.webp':$m['path'];if($private && $m['storage_key']){$m['path']='/admin/media-file/'.$m['id'].'/large.webp';$m['srcset']='';}unset($m['storage_key']);}unset($m);return $rows;
    }
    public function usable(string $id): array
    {
        $s=$this->db->prepare('SELECT m.*,d.deleted_at FROM cms_media m LEFT JOIN cms_media_details d ON d.media_id=m.id WHERE m.id=?');$s->execute([$id]);$m=$s->fetch();if(!$m || $m['deleted_at']){throw new \InvalidArgumentException('Choose an available picture from Media.');}return $m;
    }
    public function upload(array $file,int $owner): string
    {
        $limits=$this->limits();$name=$file['name'] ?? '';$tmp=$file['tmp_name'] ?? '';
        if(($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){throw new \InvalidArgumentException('Upload failed or exceeds the server upload limit.');}
        if(!is_string($name) || !is_string($tmp) || !is_uploaded_file($tmp) || !preg_match('/\A[\pL\pN _()-]+\.(jpe?g|png|webp)\z/ui',$name,$match)){throw new \InvalidArgumentException('Upload a JPEG, PNG or WebP with a simple filename and one extension.');}
        $bytes=filesize($tmp);if(!$bytes || $bytes>$limits['bytes']){throw new \InvalidArgumentException('Image exceeds the upload limit shown in Media.');}
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);$expected=match(strtolower($match[1])){'jpg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'};
        if($mime!==$expected || ($file['type'] ?? '')!==$mime){throw new \InvalidArgumentException('The image content, filename and MIME type do not match.');}
        $size=@getimagesize($tmp);if(!$size || $size['mime']!==$mime || $size[0]<1 || $size[1]<1 || $size[0]>6000 || $size[1]>6000 || $size[0]*$size[1]>$limits['pixels']){throw new \InvalidArgumentException('Image dimensions exceed the safe pixel limit. Resize the picture first.');}
        $root=$this->config['storage_path'].'/media';if(!is_dir($root) && !mkdir($root,0700,true)){throw new \RuntimeException('Private media storage unavailable.');}
        $lock=fopen($root.'/.upload.lock','c');if(!$lock || !flock($lock,LOCK_EX)){throw new \RuntimeException('Upload lock unavailable.');}
        $dir=null;
        try {
            $used=(int)$this->db->query('SELECT COALESCE(SUM(bytes),0) FROM cms_media_details')->fetchColumn();$reserve=$bytes+$size[0]*$size[1]*4+1048576;
            if($used+$reserve>$limits['quota'] || disk_free_space($root)<$reserve+33554432){throw new \InvalidArgumentException('Media storage is full. Contact the site maintainer.');}
            $image=match($mime){'image/jpeg'=>@imagecreatefromjpeg($tmp),'image/png'=>@imagecreatefrompng($tmp),'image/webp'=>@imagecreatefromwebp($tmp)};
            if(!$image){throw new \InvalidArgumentException('The image could not be decoded.');}
            try {
                if($mime==='image/jpeg' && function_exists('exif_read_data')){$exif=@exif_read_data($tmp);$orientation=(int)($exif['Orientation'] ?? 1);if(in_array($orientation,[2,4,5,7],true)){imageflip($image,IMG_FLIP_HORIZONTAL);} $angle=match($orientation){3,4=>180,5,6=>-90,7,8=>90,default=>0};if($angle){$rotated=imagerotate($image,$angle,0);imagedestroy($image);$image=$rotated;}}
                $key=bin2hex(random_bytes(24));$id=bin2hex(random_bytes(32));$candidate=$root.'/'.$key;if(!mkdir($candidate,0700)){throw new \RuntimeException('Private image directory unavailable.');}$dir=$candidate;
                if(!move_uploaded_file($tmp,$dir.'/original.bin')){throw new \RuntimeException('Cannot retain private original.');}chmod($dir.'/original.bin',0600);$total=filesize($dir.'/original.bin');$dimensions=[];
                foreach(['large'=>1600,'small'=>640,'thumb'=>200] as $variant=>$width){$w=min($width,imagesx($image));$h=max(1,(int)round(imagesy($image)*$w/imagesx($image)));$out=imagecreatetruecolor($w,$h);imagealphablending($out,false);imagesavealpha($out,true);imagecopyresampled($out,$image,0,0,0,0,$w,$h,imagesx($image),imagesy($image));
                    try{if(!imagewebp($out,$dir.'/'.$variant.'.webp',85)){throw new \RuntimeException('Cannot encode image derivative.');}}finally{imagedestroy($out);}chmod($dir.'/'.$variant.'.webp',0600);$total+=filesize($dir.'/'.$variant.'.webp');$dimensions[$variant]=[$w,$h];
                }
                $path='/media/'.$id.'/';$this->db->beginTransaction();
                $s=$this->db->prepare('INSERT INTO cms_media (id,path,label,width,height,srcset) VALUES (?,?,?,?,?,?)');$s->execute([$id,$path.'large.webp',mb_substr($name,0,255),...$dimensions['large'],($dimensions['small'][0]<$dimensions['large'][0]?$path.'small.webp '.$dimensions['small'][0].'w, '.$path.'large.webp '.$dimensions['large'][0].'w':'')]);
                $s=$this->db->prepare('INSERT INTO cms_media_details (media_id,caption,storage_key,bytes,created_at) VALUES (?,\'\',?,?,UTC_TIMESTAMP())');$s->execute([$id,$key,$total]);(new Auth($this->db,new Crypto($this->config['key_bytes'])))->audit('media.upload',$owner,$_SERVER['REMOTE_ADDR'] ?? 'cli');$this->db->commit();$dir=null;return $id;
            }finally{imagedestroy($image);}
        }finally{
            if($this->db->inTransaction()){$this->db->rollBack();}if($dir){foreach(glob($dir.'/*') as $f){unlink($f);}rmdir($dir);}flock($lock,LOCK_UN);fclose($lock);
        }
    }
    public static function ids(mixed $data,\PDO $db): array
    {
        $known=array_fill_keys($db->query('SELECT id FROM cms_media')->fetchAll(\PDO::FETCH_COLUMN),true);$ids=[];
        $walk=function($v) use (&$walk,&$ids,$known){if(is_array($v)){foreach($v as $child){$walk($child);}}elseif(is_string($v) && isset($known[$v])){$ids[$v]=true;}};$walk($data);return array_keys($ids);
    }
    public static function reference(\PDO $db,string $scope,string $key,array $data): void
    {
        $s=$db->prepare('INSERT IGNORE INTO cms_media_usage (media_id,scope,revision_key) VALUES (?,?,?)');foreach(self::ids($data,$db) as $id){$s->execute([$id,$scope,$key]);}
    }
    public function usage(string $id): array
    {
        $s=$this->db->prepare('SELECT scope,revision_key FROM cms_media_usage WHERE media_id=? LIMIT 100');$s->execute([$id]);$rows=$s->fetchAll();foreach($rows as &$r){
            if($r['scope']==='page'){$q=$this->db->prepare('SELECT d.label FROM cms_revisions r JOIN cms_documents d ON d.slug=r.document_slug WHERE r.id=?');$q->execute([$r['revision_key']]);$r['label']=($q->fetchColumn() ?: 'Page').' · revision #'.$r['revision_key'];}
            elseif($r['scope']==='post'){$q=$this->db->prepare('SELECT JSON_UNQUOTE(JSON_EXTRACT(content,\'$.title\')) FROM cms_post_revisions WHERE id=?');$q->execute([$r['revision_key']]);$r['label']=($q->fetchColumn() ?: 'Story').' · revision #'.$r['revision_key'];}
            else{$r['label']='Website page: '.$r['revision_key'];}
        }unset($r);return $rows;
    }
    public function edit(string $id,string $alt,string $caption,bool $delete,int $owner): void
    {
        if(mb_strlen($alt)>500 || mb_strlen($caption)>2000 || !mb_check_encoding($alt.$caption,'UTF-8') || str_contains($alt.$caption,"\0")){throw new \InvalidArgumentException('Image descriptions are too long or invalid.');}
        $this->db->beginTransaction();try{
            // Every revision writer locks selected media rows too, so deletion cannot race a new reference.
            $s=$this->db->prepare('SELECT id FROM cms_media WHERE id=? FOR UPDATE');$s->execute([$id]);if(!$s->fetchColumn()){throw new \InvalidArgumentException('Image not found.');}
            if($delete && $this->usage($id)){throw new \InvalidArgumentException('This image is used by content or revision history. It cannot be deleted.');}
            $s=$this->db->prepare('INSERT INTO cms_media_details (media_id,alt,caption,deleted_at,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE alt=VALUES(alt),caption=VALUES(caption),deleted_at=VALUES(deleted_at)');$s->execute([$id,$alt,$caption,$delete?gmdate('Y-m-d H:i:s'):null]);
            (new Auth($this->db,new Crypto($this->config['key_bytes'])))->audit($delete?'media.soft_delete':'media.metadata',$owner,$_SERVER['REMOTE_ADDR'] ?? 'cli');$this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
    public static function lockReferences(\PDO $db,array $data): void
    {
        $ids=self::ids($data,$db);sort($ids);$s=$db->prepare('SELECT d.deleted_at FROM cms_media m LEFT JOIN cms_media_details d ON d.media_id=m.id WHERE m.id=? FOR UPDATE');foreach($ids as $id){$s->execute([$id]);$deleted=$s->fetchColumn();if($deleted!==false && $deleted!==null){throw new \InvalidArgumentException('A selected picture has been removed from the library.');}}
    }
    public function serve(string $id,string $variant,bool $owner): void
    {
        if(!preg_match('/\A[a-f0-9]{64}\z/',$id) || !in_array($variant,['large.webp','small.webp','thumb.webp'],true)){http_response_code(404);return;}
        $s=$this->db->prepare('SELECT storage_key,deleted_at FROM cms_media_details WHERE media_id=?');$s->execute([$id]);$m=$s->fetch();
        if(!$m || !$m['storage_key'] || ($m['deleted_at'] && !$owner)){http_response_code(404);header('Cache-Control: no-store');return;}
        if(!$owner){$s=$this->db->prepare("SELECT 1 FROM cms_media_usage u WHERE u.media_id=? AND ((u.scope='page' AND EXISTS (SELECT 1 FROM cms_documents d WHERE CAST(d.published_id AS CHAR)=u.revision_key)) OR (u.scope='post' AND EXISTS (SELECT 1 FROM cms_posts p WHERE CAST(p.published_id AS CHAR)=u.revision_key AND p.trashed_at IS NULL))) LIMIT 1");$s->execute([$id]);if(!$s->fetchColumn()){http_response_code(404);header('Cache-Control: no-store');return;}}
        $file=$this->config['storage_path'].'/media/'.$m['storage_key'].'/'.$variant;if(!preg_match('/\A[a-f0-9]{48}\z/',$m['storage_key']) || !is_file($file)){http_response_code(404);return;}
        header('Content-Type: image/webp');header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename="'.$variant.'"');header('Content-Security-Policy: default-src \'none\'; sandbox');header('Cache-Control: '.($owner?'no-store, private':'no-cache'));header('Content-Length: '.filesize($file));if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='HEAD'){readfile($file);}
    }
    public function indexExisting(): void
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('CLI only.');}
        foreach($this->db->query('SELECT id,content FROM cms_revisions')->fetchAll() as $r){self::reference($this->db,'page',(string)$r['id'],json_decode($r['content'],true,32,JSON_THROW_ON_ERROR));}
        foreach($this->db->query('SELECT id,content FROM cms_post_revisions')->fetchAll() as $r){self::reference($this->db,'post',(string)$r['id'],json_decode($r['content'],true,32,JSON_THROW_ON_ERROR));}
        $root=dirname(__DIR__).'/public';foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS)) as $file){if($file->getExtension()!=='html'){continue;}$html=file_get_contents($file->getPathname());$s=$this->db->prepare('INSERT IGNORE INTO cms_media_usage (media_id,scope,revision_key) VALUES (?,\'static\',?)');foreach($this->db->query('SELECT id,path FROM cms_media')->fetchAll() as $m){if(str_contains($html,$m['path'])){$s->execute([$m['id'],substr($file->getPathname(),strlen($root))]);}}}
    }
}
