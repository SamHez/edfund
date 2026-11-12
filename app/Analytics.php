<?php
declare(strict_types=1);
namespace EdFund;

/**
 * Cookie-free visit counting. Only daily per-page totals and a salted, one-way daily visitor
 * hash are stored; no IP address, user agent or identifier survives and nothing is shared.
 * Failures never affect a public page.
 */
final class Analytics
{
    private const BOT='/bot|crawl|spider|slurp|bing|facebookexternalhit|preview|monitor|uptime|curl|wget|python|headless|lighthouse/i';

    public static function record(\PDO $db,Crypto $crypto,string $path): void
    {
        try{
            $agent=(string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET' || ($_SERVER['HTTP_DNT'] ?? '')==='1' || $agent==='' || preg_match(self::BOT,$agent) || strlen($path)>191){return;}
            $day=gmdate('Y-m-d');
            $visitor=substr($crypto->digest('visit|'.$day.'|'.($_SERVER['REMOTE_ADDR'] ?? '').'|'.$agent),0,32);
            $db->prepare('INSERT INTO cms_visit_daily (day,path,views) VALUES (?,?,1) ON DUPLICATE KEY UPDATE views=views+1')->execute([$day,$path]);
            $db->prepare('INSERT IGNORE INTO cms_visit_visitors (day,visitor) VALUES (?,?)')->execute([$day,$visitor]);
            if(random_int(1,200)===1){
                $db->exec('DELETE FROM cms_visit_visitors WHERE day < DATE_SUB(UTC_DATE(),INTERVAL 40 DAY)');
                $db->exec('DELETE FROM cms_visit_daily WHERE day < DATE_SUB(UTC_DATE(),INTERVAL 400 DAY)');
            }
        }catch(\Throwable $e){error_log("analytics: ".get_class($e).": ".$e->getMessage());}
    }

    /** Null when the analytics tables are not installed yet. */
    public static function summary(\PDO $db,int $days=30): ?array
    {
        try{
            $since=gmdate('Y-m-d',strtotime('-'.($days-1).' days'));
            $q=$db->prepare('SELECT COALESCE(SUM(views),0) FROM cms_visit_daily WHERE day>=?');$q->execute([$since]);$views=(int)$q->fetchColumn();
            $q=$db->prepare('SELECT COUNT(*) FROM cms_visit_visitors WHERE day>=?');$q->execute([$since]);$visitors=(int)$q->fetchColumn();
            $q=$db->prepare('SELECT path,SUM(views) v FROM cms_visit_daily WHERE day>=? GROUP BY path ORDER BY v DESC,path LIMIT 5');$q->execute([$since]);$top=$q->fetchAll();
            $q=$db->prepare("SELECT COALESCE(SUM(views),0) FROM cms_visit_daily WHERE day>=? AND path LIKE '/stories-updates/_%'");$q->execute([$since]);$stories=(int)$q->fetchColumn();
            $q=$db->prepare('SELECT day,SUM(views) v FROM cms_visit_daily WHERE day>=? GROUP BY day');$q->execute([gmdate('Y-m-d',strtotime('-13 days'))]);$byDay=$q->fetchAll(\PDO::FETCH_KEY_PAIR);
            $series=[];for($i=13;$i>=0;$i--){$d=gmdate('Y-m-d',strtotime('-'.$i.' days'));$series[]=['day'=>$d,'views'=>(int)($byDay[$d] ?? 0)];}
            return ['views'=>$views,'visitors'=>$visitors,'top'=>$top,'stories'=>$stories,'series'=>$series,'days'=>$days];
        }catch(\Throwable){return null;}
    }
}
