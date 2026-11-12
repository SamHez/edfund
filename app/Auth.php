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
            $s=$this->db->prepare('UPDATE cms_users SET password_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([Passwords::hash($password),$user['id']]);
        }
        $this->releaseSuccessfulAttempt($ticket);
        return $user;
    }
    public function enroll(int $id,string $secret,string $code,string $source): ?array
    {
        $ticket=$this->reserveAttempt('mfa',(string)$id,$source);if($ticket===null){return null;}
        $this->db->beginTransaction();
        try {
            $user=$this->user($id,true);
            $step=Mfa::matchedStep($secret,$code,-1);
            if (!$user || $user['mfa_enabled_at']!==null || $step===null) {$this->db->rollBack();return null;}
            $s=$this->db->prepare('UPDATE cms_users SET mfa_secret=?,mfa_enabled_at=UTC_TIMESTAMP(),last_totp_step=?,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$this->crypto->seal($secret),$step,$id]);
            $codes=$this->replaceRecoveryCodes($id);$this->audit('mfa.enrolled',$id,$source);$this->releaseSuccessfulAttempt($ticket);$this->db->commit();return $codes;
        } catch (\Throwable $e) {if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
    public function verifyFactor(int $id,string $code,string $source): ?string
    {
        $ticket=$this->reserveAttempt('mfa',(string)$id,$source);if($ticket===null){return null;}
        $this->db->beginTransaction();
        try {
            $user=$this->user($id,true);$result=null;
            if ($user && $user['mfa_enabled_at']!==null) {
                $step=Mfa::matchedStep($this->crypto->open($user['mfa_secret']),trim($code),(int)$user['last_totp_step']);
                if ($step!==null) {
                    $s=$this->db->prepare('UPDATE cms_users SET last_totp_step=? WHERE id=?');$s->execute([$step,$id]);$result='totp';
                } else {
                    $normal=Mfa::normalizeRecovery($code);
                    if ($normal!=='') {
                        $s=$this->db->prepare('SELECT id FROM cms_recovery_codes WHERE user_id=? AND code_hash=? AND used_at IS NULL FOR UPDATE');$s->execute([$id,$this->crypto->digest('recovery:'.$normal)]);$recovery=$s->fetchColumn();
                        if ($recovery) {
                            $s=$this->db->prepare('UPDATE cms_recovery_codes SET used_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$recovery]);
                            // Recovery requires new authenticator enrollment and revokes prior sessions.
                            $s=$this->db->prepare('UPDATE cms_users SET mfa_secret=NULL,mfa_enabled_at=NULL,last_totp_step=-1,session_version=session_version+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$id]);$result='recovery';
                        }
                    }
                }
            }
            if ($result===null) {$this->db->rollBack();$this->audit('login.factor_failed',$id,$source);return null;}
            $this->audit('login.'.$result,$id,$source);$this->releaseSuccessfulAttempt($ticket);$this->db->commit();return $result;
        } catch (\Throwable $e) {if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
    public function replaceRecoveryCodes(int $id): array
    {
        $codes=Mfa::recoveryCodes();$s=$this->db->prepare('DELETE FROM cms_recovery_codes WHERE user_id=?');$s->execute([$id]);
        $s=$this->db->prepare('INSERT INTO cms_recovery_codes (user_id,code_hash,created_at) VALUES (?,?,UTC_TIMESTAMP())');
        foreach ($codes as $code) {$s->execute([$id,$this->crypto->digest('recovery:'.Mfa::normalizeRecovery($code))]);}
        return $codes;
    }
    public function regenerateCodes(int $id,string $password,string $code,string $source): ?array
    {
        $ticket=$this->reserveAttempt('mfa',(string)$id,$source);if($ticket===null){return null;}
        $this->db->beginTransaction();
        try {
            $user=$this->user($id,true);
            $step=$user && $user['mfa_secret'] ? Mfa::matchedStep($this->crypto->open($user['mfa_secret']),$code,(int)$user['last_totp_step']) : null;
            if (!$user || !self::passwordMatches($password,$user['password_hash']) || $step===null) {$this->db->rollBack();return null;}
            $s=$this->db->prepare('UPDATE cms_users SET last_totp_step=? WHERE id=?');$s->execute([$step,$id]);$codes=$this->replaceRecoveryCodes($id);$this->audit('recovery.rotated',$id,$source);$this->releaseSuccessfulAttempt($ticket);$this->db->commit();return $codes;
        } catch (\Throwable $e) {if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
    public function remainingCodes(int $id): int
    {
        $s=$this->db->prepare('SELECT COUNT(*) FROM cms_recovery_codes WHERE user_id=? AND used_at IS NULL');$s->execute([$id]);return (int)$s->fetchColumn();
    }
    public function prune(): void
    {
        $this->db->exec('DELETE FROM cms_auth_attempts WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) LIMIT 1000');
        $this->db->exec('DELETE FROM cms_audit WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY) LIMIT 1000');
    }
}
