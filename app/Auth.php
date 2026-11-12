<?php
declare(strict_types=1);
namespace EdFund;
final class Auth
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=3,p=1$V0dvSGx3bGtDZG9aMlE2Sg$hxt5oKh/ktvqoo0akDzpNTxTr2wt59teR7zsWAkq2jA';
    private const DUMMY_BCRYPT = '$2y$12$rzmRsL3en8jgSvJLd5lHj.zfRvacvU/QPxKa5ZyvgRQgXUzybdV.C';
    private static function passwordMatches(string $password, string $hash): bool
    {
        $max = (password_get_info($hash)['algoName'] ?? '') === 'bcrypt' ? 72 : 128;
        return strlen($password)<=$max && !str_contains($password, "\0") && password_verify($password,$hash);
    }
    public function __construct(public readonly \PDO $db, private readonly Crypto $crypto) {}
    public function user(int $id, bool $lock=false): ?array
    {
        $s=$this->db->prepare('SELECT * FROM cms_users WHERE id=?'.($lock?' FOR UPDATE':''));$s->execute([$id]);return $s->fetch() ?: null;
    }
    public function audit(string $event, ?int $id, string $source): void
    {
        $s=$this->db->prepare('INSERT INTO cms_audit (user_id,event,source_hash,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');$s->execute([$id,$event,$this->crypto->digest('ip:'.$source)]);
    }
    private function buckets(string $purpose, string $identity, string $source): array
    {
        return [$this->crypto->digest($purpose.':account:'.$identity), $this->crypto->digest($purpose.':ip:'.$source)];
    }
    public function throttled(string $purpose, string $identity, string $source): bool
    {
        foreach ($this->buckets($purpose,$identity,$source) as $index=>$bucket) {
            $s=$this->db->prepare('SELECT COUNT(*) FROM cms_auth_attempts WHERE bucket=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)');$s->execute([$bucket]);
            if ((int)$s->fetchColumn()>=($index===0?6:30)) {return true;}
        }
        return false;
    }
    private function reserveAttempt(string $purpose, string $identity, string $source): ?array
    {
        // Reserve attempts before expensive verification. Concurrent requests cannot
        // all observe an empty failure window and bypass the configured limit.
        $lock='edfund-limit-'.substr(hash('sha256',(string)$this->db->query('SELECT DATABASE()')->fetchColumn().':'.$purpose),0,24);
        $s=$this->db->prepare('SELECT GET_LOCK(?,5)');$s->execute([$lock]);
        if ((int)$s->fetchColumn()!==1) {return null;}
        try {
            if ($this->throttled($purpose,$identity,$source)) {return null;}
            $ids=[];$s=$this->db->prepare('INSERT INTO cms_auth_attempts (bucket,created_at) VALUES (?,UTC_TIMESTAMP())');
            foreach ($this->buckets($purpose,$identity,$source) as $bucket) {$s->execute([$bucket]);$ids[]=(int)$this->db->lastInsertId();}
            return $ids;
        } finally {$s=$this->db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
    }
    private function releaseSuccessfulAttempt(array $ids): void
    {
        $s=$this->db->prepare('DELETE FROM cms_auth_attempts WHERE id=?');foreach($ids as $id){$s->execute([$id]);}
    }
    public function passwordLogin(string $name,string $password,string $source): ?array
    {
        // One owner: all spelling/case variations share the same account limit.
        $ticket=$this->reserveAttempt('password','owner',$source);if($ticket===null){return null;}
        $user=$this->db->query('SELECT * FROM cms_users ORDER BY id LIMIT 1')->fetch();
        $validName=mb_check_encoding($name,'UTF-8') && mb_strlen($name,'UTF-8')<=100;
        $nameMatches=$user && $validName && hash_equals(mb_strtolower(trim($user['name']),'UTF-8'),mb_strtolower(trim($name),'UTF-8'));
        // A real password verification also runs for a nonexistent account.
        $hash=$user['password_hash'] ?? (defined('PASSWORD_ARGON2ID') ? self::DUMMY_HASH : self::DUMMY_BCRYPT);
        $valid=self::passwordMatches($password,$hash);
        if (!$nameMatches || !$valid) {$this->audit('login.password_failed',null,$source);return null;}
        if (password_needs_rehash($user['password_hash'],Passwords::algorithm(),Passwords::options())) {
            $s=$this->db->prepare('UPDATE cms_users SET password_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND password_hash=? AND session_version=?');$s->execute([Passwords::hash($password),$user['id'],$user['password_hash'],$user['session_version']]);if($s->rowCount()!==1){return null;}
        }
        $this->releaseSuccessfulAttempt($ticket);
        return $user;
    }
    public function prune(): void
    {
        $this->db->exec('DELETE FROM cms_auth_attempts WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) LIMIT 1000');
        $this->db->exec('DELETE FROM cms_audit WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY) LIMIT 1000');
    }
}
