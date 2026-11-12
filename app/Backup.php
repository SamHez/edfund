<?php
declare(strict_types=1);
namespace EdFund;
/** CLI-only, authenticated native Sodium secretstream; never a release archive. */
final class Backup
{
    private const MAGIC="EDFUND4\n";
    public function __construct(private readonly ?\PDO $db,private readonly array $config) {}
    public static function tables(): array
    {
        $tables=['cms_migrations'];foreach(glob(dirname(__DIR__).'/database/migrations/*.sql') as $file){preg_match_all('/CREATE TABLE IF NOT EXISTS (cms_[a-z_]+)/',file_get_contents($file),$m);$tables=array_merge($tables,$m[1]);}sort($tables);return array_values(array_unique($tables));
    }
    public static function keyFile(string $path): void
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('CLI only.');}$file=@fopen($path,'x');if(!$file){throw new \RuntimeException('Backup key exists or cannot be created; it was not overwritten.');}chmod($path,0600);try{self::write($file,sodium_crypto_secretstream_xchacha20poly1305_keygen());}finally{fclose($file);}
    }
    private static function key(string $path): string
    {
        $key=@file_get_contents($path);if($key===false || strlen($key)!==32){throw new \RuntimeException('A private 32-byte backup key file is required.');}return $key;
    }
    private static function write($h,string $bytes): void {$offset=0;while($offset<strlen($bytes)){$n=fwrite($h,substr($bytes,$offset));if($n===false || $n===0){throw new \RuntimeException('Backup storage write failed.');}$offset+=$n;}}
    private static function read($h,int $count): string
    {
        $bytes='';while(strlen($bytes)<$count){$part=fread($h,$count-strlen($bytes));if($part===false || $part===''){throw new \RuntimeException('Backup is truncated.');}$bytes.=$part;}return $bytes;
    }
    public function create(string $keyPath): string
    {
        if(PHP_SAPI!=='cli' || !$this->db){throw new \RuntimeException('Backup creation requires a CLI database connection.');}$key=self::key($keyPath);$root=$this->config['storage_path'].'/backups';if(!is_dir($root) && !mkdir($root,0700,true)){throw new \RuntimeException('Private backup directory unavailable.');}
        $file=$root.'/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.efb';$pending=$file.'.pending';$h=null;$state=null;$mediaLock=null;$lock=new PublicationLock($this->db);
        try{$h=fopen($pending,'x');if(!$h){throw new \RuntimeException('Cannot open private backup storage.');}chmod($pending,0600);[$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);sodium_memzero($key);self::write($h,self::MAGIC.$header);
        $total=0;$push=function(array $record,bool $final=false) use ($h,&$state,&$total){$json=json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$total+=strlen($json);if($total>2147483648){throw new \RuntimeException('Backup exceeds the 2 GiB recovery bound.');}if(strlen($json)>2097152){throw new \RuntimeException('A backup record exceeds the safe bound.');}$cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$json,'edfund-backup-v1',$final?SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL:SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);self::write($h,pack('N',strlen($cipher)).$cipher);};
            $mediaRoot=$this->config['storage_path'].'/media';if(!is_dir($mediaRoot)){mkdir($mediaRoot,0700,true);}$mediaLock=fopen($mediaRoot.'/.upload.lock','c');if(!$mediaLock || !flock($mediaLock,LOCK_SH)){throw new \RuntimeException('Media backup lock unavailable.');}
            $this->db->beginTransaction();$tables=$this->db->query("SHOW TABLES LIKE 'cms\\_%'")->fetchAll(\PDO::FETCH_COLUMN);$allowed=self::tables();foreach($tables as $t){if(!in_array($t,$allowed,true)){throw new \RuntimeException('Unknown CMS table; review backup coverage before continuing.');}}
            $config=$this->config;unset($config['key_bytes']);$push(['type'=>'manifest','format'=>1,'created_at'=>gmdate('c'),'source_database'=>$this->db->query('SELECT DATABASE()')->fetchColumn(),'tables'=>$tables,'config'=>$config]);
            foreach($tables as $table){$push(['type'=>'table','name'=>$table]);$query=$this->db->query('SELECT * FROM `'.$table.'`');while($row=$query->fetch(\PDO::FETCH_ASSOC)){$push(['type'=>'row','table'=>$table,'row'=>$row]);}}
            $keys=$this->db->query('SELECT storage_key FROM cms_media_details WHERE storage_key IS NOT NULL')->fetchAll(\PDO::FETCH_COLUMN);
            foreach($keys as $storageKey){if(!preg_match('/\A[a-f0-9]{48}\z/',$storageKey)){throw new \RuntimeException('Invalid media storage key.');}foreach(['original.bin','large.webp','small.webp','thumb.webp'] as $name){$path=$mediaRoot.'/'.$storageKey.'/'.$name;if(!is_file($path) || is_link($path)){throw new \RuntimeException('Referenced media file missing or unsafe.');}$push(['type'=>'file','path'=>$storageKey.'/'.$name,'size'=>filesize($path),'sha256'=>hash_file('sha256',$path)]);$in=fopen($path,'rb');try{while(!feof($in)){$chunk=fread($in,65536);if($chunk===false){throw new \RuntimeException('Media read failed.');}if($chunk!==''){$push(['type'=>'chunk','data'=>base64_encode($chunk)]);}}}finally{fclose($in);}$push(['type'=>'end_file']);}}
            $push(['type'=>'end'],true);$this->db->commit();fflush($h);fclose($h);$h=null;if(!rename($pending,$file)){throw new \RuntimeException('Cannot finalize encrypted backup.');}$files=glob($root.'/*.efb');usort($files,fn($a,$b)=>$a===$file?-1:($b===$file?1:(filemtime($b)<=>filemtime($a) ?: strcmp($b,$a))));$keep=max(2,min(30,(int)($this->config['backup_keep'] ?? 7)));foreach(array_slice($files,$keep) as $old){if(!is_link($old) && preg_match('/\A[0-9]{8}-[0-9]{6}-[a-f0-9]{12}\.efb\z/',basename($old))){@unlink($old);}}return $file;
        }finally{if($this->db->inTransaction()){$this->db->rollBack();}if(is_resource($h)){fclose($h);}if(is_file($pending)){unlink($pending);}if($mediaLock){flock($mediaLock,LOCK_UN);fclose($mediaLock);}$lock->release();if(isset($state)){sodium_memzero($state);}}
    }
    /** Decrypt/authenticate the entire stream before any database write. */
    public static function inspect(string $file,string $keyPath,string $directory): array
    {
        $key=self::key($keyPath);$h=fopen($file,'rb');$spool=fopen($directory.'/rows.jsonl','x');chmod($directory.'/rows.jsonl',0600);$manifest=null;$active=null;$total=0;$final=false;
        try{
            if(self::read($h,8)!==self::MAGIC){throw new \RuntimeException('Not an EdFund encrypted backup.');}$state=sodium_crypto_secretstream_xchacha20poly1305_init_pull(self::read($h,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),$key);sodium_memzero($key);
            while(!feof($h)){$len=unpack('N',self::read($h,4))[1];if($len<17 || $len>2097200){throw new \RuntimeException('Invalid backup frame length.');}$result=sodium_crypto_secretstream_xchacha20poly1305_pull($state,self::read($h,$len),'edfund-backup-v1');if($result===false){throw new \RuntimeException('Backup authentication failed.');}[$json,$tag]=$result;$r=json_decode($json,true,32,JSON_THROW_ON_ERROR);$total+=strlen($json);if($total>2147483648){throw new \RuntimeException('Backup exceeds the 2 GiB restore limit.');}
                if(!$manifest && ($r['type'] ?? '')!=='manifest'){throw new \RuntimeException('Manifest must be first.');}
                if(($r['type'] ?? '')==='manifest'){if($manifest || ($r['format'] ?? 0)!==1){throw new \RuntimeException('Invalid backup manifest.');}$manifest=$r;}
                elseif(($r['type'] ?? '')==='file'){
                    if($active || !preg_match('~\A[a-f0-9]{48}/(?:original\.bin|large\.webp|small\.webp|thumb\.webp)\z~',$r['path']) || !is_int($r['size']) || $r['size']<0 || $r['size']>67108864 || !preg_match('/\A[a-f0-9]{64}\z/',$r['sha256'])){throw new \RuntimeException('Invalid media entry.');}
                    $target=$directory.'/media/'.$r['path'];$parent=dirname($target);if(!is_dir($parent)){mkdir($parent,0700,true);}$out=fopen($target,'x');chmod($target,0600);$active=['handle'=>$out,'path'=>$target,'record'=>$r,'bytes'=>0];
                }elseif(($r['type'] ?? '')==='chunk'){if(!$active || !is_string($r['data']) || strlen($r['data'])>90000 || ($bytes=base64_decode($r['data'],true))===false){throw new \RuntimeException('Invalid media chunk.');}$active['bytes']+=strlen($bytes);if($active['bytes']>$active['record']['size']){throw new \RuntimeException('Media size mismatch.');}self::write($active['handle'],$bytes);}
                elseif(($r['type'] ?? '')==='end_file'){if(!$active){throw new \RuntimeException('Unexpected media ending.');}fclose($active['handle']);if($active['bytes']!==$active['record']['size'] || !hash_equals($active['record']['sha256'],hash_file('sha256',$active['path']))){throw new \RuntimeException('Media integrity mismatch.');}$active=null;}
                elseif(in_array($r['type'] ?? '',['table','row'],true)){if(!$manifest || !in_array($r['table'] ?? $r['name'],self::tables(),true)){throw new \RuntimeException('Unsupported backup table.');}self::write($spool,$json."\n");}
                elseif(($r['type'] ?? '')==='end'){if($active || $tag!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL || fread($h,1)!==''){throw new \RuntimeException('Invalid backup ending.');}$final=true;break;}
                else{throw new \RuntimeException('Unsupported backup record.');}
                if($tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL){throw new \RuntimeException('Unexpected final tag.');}
            }
            if(!$final || !$manifest){throw new \RuntimeException('Incomplete backup.');}return $manifest;
        }finally{if($active && is_resource($active['handle'])){fclose($active['handle']);}fclose($h);fclose($spool);if(isset($state)){sodium_memzero($state);}}
    }
    public function restore(string $file,string $keyPath,string $targetConfigPath,string $output): string
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('Restore is CLI only.');}$real=realpath($targetConfigPath);$public=dirname(__DIR__).'/public';if(!$real || str_starts_with($real,$public.'/')){throw new \RuntimeException('Private target configuration required.');}$targetConfig=require $real;if(!is_array($targetConfig) || !isset($targetConfig['db'])){throw new \RuntimeException('Invalid target configuration.');}$target=Database::connect($targetConfig['db']);$name=$target->query('SELECT DATABASE()')->fetchColumn();$source=$this->db?->query('SELECT DATABASE()')->fetchColumn();
        if($name==='edfund' || $name===$source || $target->query('SHOW TABLES')->fetchAll()){throw new \RuntimeException('Restore requires a different, existing, EMPTY database. edfund and nonempty targets are refused.');}
        $parent=realpath(dirname($output));if(!$parent || $parent===$public || str_starts_with($parent,$public.'/') || file_exists($output) || is_link($output)){throw new \RuntimeException('Choose a new private output folder outside public.');}if(!mkdir($output,0700)){throw new \RuntimeException('Cannot create private restore folder.');}$output=realpath($output);
        $manifest=self::inspect($file,$keyPath,$output);if($name===$manifest['source_database']){throw new \RuntimeException('Do not restore over the source database.');}
        // Validate the backed migration chain against code before applying any DDL.
        $in=fopen($output.'/rows.jsonl','r');try{while(($line=fgets($in))!==false){$r=json_decode($line,true,32,JSON_THROW_ON_ERROR);if(($r['type'] ?? '')==='row' && $r['table']==='cms_migrations'){$p=dirname(__DIR__).'/database/migrations/'.basename($r['row']['version']);if(!is_file($p) || !hash_equals($r['row']['checksum'],hash_file('sha256',$p))){throw new \RuntimeException('Backup/code migration mismatch.');}}}}finally{fclose($in);}
        Migrations::run($target);$target->exec('SET FOREIGN_KEY_CHECKS=0');$target->beginTransaction();
        try{$in=fopen($output.'/rows.jsonl','r');$columns=[];while(($line=fgets($in))!==false){$r=json_decode($line,true,32,JSON_THROW_ON_ERROR);if($r['type']!=='row' || $r['table']==='cms_migrations'){continue;}$table=$r['table'];$columns[$table] ??= $target->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(\PDO::FETCH_COLUMN);if(array_keys($r['row'])!==$columns[$table]){throw new \RuntimeException('Backup columns differ from the target schema.');}$q=$target->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$columns[$table]).'`) VALUES ('.implode(',',array_fill(0,count($columns[$table]),'?')).')');$q->execute(array_values($r['row']));}fclose($in);
            $target->exec('UPDATE cms_users SET session_version=session_version+1');
            foreach($target->query('SELECT slug,draft_id,published_id FROM cms_documents')->fetchAll() as $d){$content=new Content($target);$content->revision((int)$d['draft_id'],$d['slug']);if($d['published_id']){$content->revision((int)$d['published_id'],$d['slug']);}}
            $checks=['SELECT COUNT(*) FROM cms_posts p LEFT JOIN cms_post_revisions d ON d.id=p.draft_id AND d.post_id=p.id LEFT JOIN cms_post_revisions r ON r.id=p.published_id AND r.post_id=p.id WHERE d.id IS NULL OR (p.published_id IS NOT NULL AND r.id IS NULL)','SELECT COUNT(*) FROM cms_post_routes r LEFT JOIN cms_posts p ON p.id=r.post_id WHERE p.id IS NULL','SELECT COUNT(*) FROM cms_pages p LEFT JOIN cms_documents d ON d.slug=p.document_slug WHERE d.slug IS NULL','SELECT COUNT(*) FROM cms_media_details d LEFT JOIN cms_media m ON m.id=d.media_id WHERE m.id IS NULL','SELECT COUNT(*) FROM cms_media_usage u LEFT JOIN cms_media m ON m.id=u.media_id WHERE m.id IS NULL','SELECT COUNT(*) FROM cms_post_revisions r LEFT JOIN cms_posts p ON p.id=r.post_id WHERE p.id IS NULL','SELECT COUNT(*) FROM cms_recovery_codes r LEFT JOIN cms_users u ON u.id=r.user_id WHERE u.id IS NULL'];foreach($checks as $sql){if((int)$target->query($sql)->fetchColumn()){throw new \RuntimeException('Restored relationship check failed.');}}
            $target->commit();
        }catch(\Throwable $e){if($target->inTransaction()){$target->rollBack();}throw $e;}finally{$target->exec('SET FOREIGN_KEY_CHECKS=1');}
        $restored=$manifest['config'];$restored['db']=$targetConfig['db'];$restored['storage_path']=$output;unset($restored['key_bytes']);$restored['environment']=$targetConfig['environment'] ?? 'local';$restored['base_url']=$targetConfig['base_url'] ?? 'http://127.0.0.1:8089';$restored['public_indexing']=false;
        $path=$output.'/restored-config.php';$h=fopen($path,'x');chmod($path,0600);self::write($h,"<?php\nreturn ".var_export($restored,true).";\n");fclose($h);unlink($output.'/rows.jsonl');return $path;
    }
}
