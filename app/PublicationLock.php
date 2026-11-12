<?php
declare(strict_types=1);
namespace EdFund;
/** Serializes publication/import/restore, including cross-page SEO uniqueness checks. */
final class PublicationLock
{
    private string $name;
    private bool $held=false;
    public function __construct(private readonly \PDO $db)
    {
        $this->name='edfund-publication-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,20);$s=$db->prepare('SELECT GET_LOCK(?,10)');$s->execute([$this->name]);if((int)$s->fetchColumn()!==1){throw new ContentConflict('Another update is in progress. Try again.');}$this->held=true;
    }
    public function release(): void {if($this->held){$s=$this->db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$this->name]);$this->held=false;}}
    public function __destruct() {$this->release();}
}
