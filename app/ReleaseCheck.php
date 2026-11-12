<?php
declare(strict_types=1);
namespace EdFund;
/** CLI preflight. A passing local rehearsal is explicitly not deployment approval. */
final class ReleaseCheck
{
    public static function run(array $config,bool $local=false): array
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('Release check is CLI only.');}
        $checks=[];$check=function(string $name,bool $passed) use (&$checks){$checks[$name]=$passed;};$root=dirname(__DIR__);$db=Database::connect($config['db']);
        $localAllowed=$local && $config['environment']==='local' && in_array(parse_url($config['base_url'],PHP_URL_HOST),['localhost','127.0.0.1','[::1]'],true);
        $check('explicit_local_rehearsal_or_production',($local && $localAllowed) || (!$local && $config['environment']==='production'));
        $check('patched_php',Preflight::patchFloorMet() || $localAllowed);
        foreach(['dom','pdo_mysql','sodium','openssl','mbstring','fileinfo','gd','exif','session'] as $e){$check('extension_'.$e,extension_loaded($e));}
        $gd=extension_loaded('gd')?gd_info():[];foreach(['JPEG Support','PNG Support','WebP Support'] as $k){$check($k,($gd[$k] ?? false)===true);}
        $check('memory_at_least_128M',Media::iniBytes(ini_get('memory_limit'))>=134217728);
        $public=isset($config['document_root'])?realpath($config['document_root']):false;$check('explicit_existing_document_root',(bool)$public);
        $check('code_and_storage_outside_web_roots',!Paths::publicPath($root.'/app',$config) && !Paths::publicPath($config['storage_path'],$config));
        foreach(['sessions','logs','cache','media','backups'] as $directory){$path=$config['storage_path'].'/'.$directory;$check('private_writable_'.$directory,(is_dir($path) || @mkdir($path,0700,true)) && is_writable($path) && !Paths::publicPath($path,$config));}
        $check('backup_key_available_and_private',is_file(Paths::backupKey($config)) && filesize(Paths::backupKey($config))===32 && !Paths::publicPath(Paths::backupKey($config),$config));
        $privateConfig=defined('EDFUND_CONFIG_FILE')?EDFUND_CONFIG_FILE:(getenv('EDFUND_CONFIG') ?: $root.'/config/local.php');$check('private_configuration_permissions',is_file($privateConfig) && (fileperms($privateConfig)&0077)===0);$keyFile=Paths::backupKey($config);$check('private_backup_key_permissions',is_file($keyFile) && (fileperms($keyFile)&0077)===0);
        $engines=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME REGEXP '^cms_'")->fetchAll(\PDO::FETCH_COLUMN);$check('cms_tables_use_innodb',$engines && !array_filter($engines,fn($engine)=>strtoupper((string)$engine)!=='INNODB'));
        $migrationRows=$db->query('SELECT version,checksum FROM cms_migrations')->fetchAll(\PDO::FETCH_KEY_PAIR);$migrationFiles=glob($root.'/database/migrations/*.sql');$migrations=count($migrationRows)===count($migrationFiles);foreach($migrationFiles as $file){$migrations=$migrations && isset($migrationRows[basename($file)]) && hash_equals($migrationRows[basename($file)],hash_file('sha256',$file));}$check('migrations_applied_and_unchanged',$migrations);
        $runtimeSafe=true;foreach($db->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(\PDO::FETCH_COLUMN) as $grant){if(!preg_match('/\AGRANT (.+?) ON /',$grant,$match) || str_contains($grant,'WITH GRANT OPTION') || array_diff(array_map('trim',explode(',',$match[1])),['USAGE','SELECT','INSERT','UPDATE','DELETE'])){$runtimeSafe=false;}}$check('runtime_database_user_has_no_schema_admin_privileges',$runtimeSafe);
        $lock='edfund-preflight-'.bin2hex(random_bytes(8));$s=$db->prepare('SELECT GET_LOCK(?,1)');$s->execute([$lock]);$check('database_advisory_locks',(int)$s->fetchColumn()===1);$s=$db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);
        $check('published_pages_imported',(int)$db->query('SELECT COUNT(*) FROM cms_pages p JOIN cms_documents d ON d.slug=p.document_slug WHERE d.published_id IS NOT NULL')->fetchColumn()===9);
        $check('one_owner_with_mfa',(int)$db->query('SELECT COUNT(*) FROM cms_users')->fetchColumn()===1 && (int)$db->query('SELECT COUNT(*) FROM cms_users WHERE mfa_enabled_at IS NOT NULL')->fetchColumn()===1);
        $check('protected_staging_or_canonical_release',Seo::indexing($config)?$config['base_url']===Seo::ORIGIN:(!empty($config['staging_user']) && password_get_info($config['staging_password_hash'] ?? '')['algoName']!=='unknown') || $localAllowed);
        $legacy=[];if($public){foreach(['index.html','wp-config.php','wp-login.php','wp-admin','wp-includes'] as $path){if(file_exists($public.'/'.$path)){$legacy[]=$path;}}foreach(glob($public.'/*/index.html') as $file){$legacy[]=basename(dirname($file)).'/index.html';}}if($public){foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($public,\FilesystemIterator::SKIP_DOTS)) as $file){$relative=substr($file->getPathname(),strlen($public)+1);if(in_array(strtolower($file->getExtension()),['php','html'],true) && !in_array($relative,['index.php','edfund-paths.php','edfund-paths.example.php'],true)){$legacy[]=$relative;}}}$legacy=array_values(array_unique($legacy));$check('stale_public_entry_points_retired',$public && !$legacy);
        $overrides=$config['release_web_overrides'] ?? [];$check('approved_overrides_only_htaccess',is_array($overrides) && !array_diff(array_keys($overrides),['.htaccess']));$manifestFile=$root.'/RELEASE.json';$integrity=is_file($manifestFile);if($integrity){$manifest=json_decode(file_get_contents($manifestFile),true,64,JSON_THROW_ON_ERROR);foreach($manifest['app_files'] as $path=>$hash){$file=$root.'/'.$path;if(!is_file($file) || !hash_equals($hash,hash_file('sha256',$file))){$integrity=false;break;}}foreach($manifest['web_files'] as $path=>$hash){if($path==='.htaccess' && isset($overrides[$path])){$hash=$overrides[$path];}if(!is_string($hash) || !preg_match('/\A[a-f0-9]{64}\z/',$hash)){$integrity=false;break;}$file=$public?$public.'/'.$path:'';if(!$file || !is_file($file) || !hash_equals($hash,hash_file('sha256',$file))){$integrity=false;break;}}}$check('release_file_integrity',$integrity);
        return ['passed'=>!in_array(false,$checks,true),'production_ready'=>!$local && !in_array(false,$checks,true),'scope'=>$localAllowed?'loopback rehearsal only; production runtime/TLS/Apache/provider checks still required':'production preflight; host/TLS/HTTP smoke tests and backup restore still required','php'=>PHP_VERSION,'approved_web_overrides'=>array_keys(is_array($overrides)?$overrides:[]),'checks'=>$checks,'legacy_entries'=>$legacy];
    }
}
