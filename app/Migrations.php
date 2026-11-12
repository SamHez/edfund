<?php
declare(strict_types=1);
namespace EdFund;
final class Migrations
{
    public static function run(\PDO $db): array
    {
        $lock = 'edfund-migrate-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,20);
        $stmt=$db->prepare('SELECT GET_LOCK(?,10)');$stmt->execute([$lock]);
        if ((int)$stmt->fetchColumn()!==1) { throw new \RuntimeException('Migration lock unavailable.'); }
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS cms_migrations (version VARCHAR(100) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $applied = $db->query('SELECT version, checksum FROM cms_migrations')->fetchAll(\PDO::FETCH_KEY_PAIR);$done=[];
            foreach (glob(dirname(__DIR__).'/database/migrations/*.sql') as $file) {
                $version = basename($file);$sql=file_get_contents($file);$checksum=hash('sha256',$sql);
                if (isset($applied[$version])) {
                    if (!hash_equals($applied[$version],$checksum)) { throw new \RuntimeException('An applied migration changed: '.$version); }
                    continue;
                }
                foreach (explode(';',$sql) as $query) { if (trim($query)!=='') {$db->exec($query);} }
                $stmt=$db->prepare('INSERT INTO cms_migrations (version,checksum,applied_at) VALUES (?,?,UTC_TIMESTAMP())');$stmt->execute([$version,$checksum]);$done[]=$version;
            }
            return $done;
        } finally { $stmt=$db->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$lock]); }
    }
}
