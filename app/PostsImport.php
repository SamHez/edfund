<?php
declare(strict_types=1);
namespace EdFund;
final class PostsImport
{
    public function __construct(private readonly \PDO $db,private readonly array $config) {}
    private static function parse(string $html): \DOMDocument {$d=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);try{$d->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}return $d;}
    private static function runs(\DOMNode $node,array $attrs=[]): array
    {
        $ops=[];foreach($node->childNodes as $child){if($child instanceof \DOMText){if($child->nodeValue!==''){$ops[]=['insert'=>$child->nodeValue]+($attrs?['attributes'=>$attrs]:[]);}continue;}if(!$child instanceof \DOMElement){continue;}$next=$attrs;
            if(in_array($child->tagName,['b','strong'],true)){$next['bold']=true;}if(in_array($child->tagName,['i','em'],true)){$next['italic']=true;}if($child->tagName==='a'){$next['link']=$child->getAttribute('href');}if($child->tagName==='br'){$ops[]=['insert'=>"\n"];continue;}$ops=array_merge($ops,self::runs($child,$next));}return $ops;
    }
    public function run(): int
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('Import is CLI only.');}
        $root=dirname(__DIR__).'/public';$home=self::parse((new Content($this->db))->page());$hx=new \DOMXPath($home);$cards=[];
        foreach($hx->query('//*[contains(concat(" ",normalize-space(@class)," ")," story-card ")]') as $card){$a=$hx->query('.//h3/a',$card)->item(0);if(!$a){continue;}$cards[$a->getAttribute('href')]=$card;}
        $s=$this->db->prepare('SELECT GET_LOCK(?,10)');$lock='edfund-post-import-'.substr(hash('sha256',(string)$this->db->query('SELECT DATABASE()')->fetchColumn()),0,20);$s->execute([$lock]);if((int)$s->fetchColumn()!==1){throw new \RuntimeException('Post import is locked.');}
        $count=0;$this->db->beginTransaction();try{
            foreach(glob($root.'/stories-updates/*/index.html') as $file){$slug=basename(dirname($file));$path='/stories-updates/'.$slug.'/';$s=$this->db->prepare('SELECT id FROM cms_posts WHERE imported_path=?');$s->execute([$path]);if($s->fetchColumn()){continue;}
                $s=$this->db->prepare('SELECT post_id FROM cms_post_routes WHERE slug=?');$s->execute([$slug]);if($s->fetchColumn()){throw new \RuntimeException('Import slug already reserved; no existing story was overwritten.');}
                $html=file_get_contents($file);$d=self::parse($html);$x=new \DOMXPath($d);$article=$x->query('//*[contains(concat(" ",normalize-space(@class)," ")," article ")]')->item(0);if(!$article){throw new \RuntimeException('Review the current story structure before importing.');}$figure=$x->query('./figure',$article)->item(0);$image=$x->query('.//img',$figure)->item(0);$caption=$x->query('./figcaption',$figure)->item(0);$ops=[];
                foreach($article->childNodes as $n){if(!$n instanceof \DOMElement || !in_array($n->tagName,['h2','h3','p','ul','ol'],true)){continue;}
                    if(in_array($n->tagName,['ul','ol'],true)){foreach($n->childNodes as $li){if($li instanceof \DOMElement){$ops=array_merge($ops,self::runs($li),[['insert'=>"\n",'attributes'=>['list'=>$n->tagName==='ol'?'ordered':'bullet']]]);}}}
                    else{$ops=array_merge($ops,self::runs($n),[['insert'=>"\n"]+($n->tagName[0]==='h'?['attributes'=>['header'=>(int)substr($n->tagName,1)]]:[])]);}}
                $card=$cards[$path] ?? null;$excerpt=$card?$hx->query('.//div/p[not(@class)]',$card)->item(0)?->textContent:'';$category=$card?$hx->query('.//p[@class="story-meta"]/span',$card)->item(0)?->textContent:'Updates';$date=$card?$hx->query('.//time',$card)->item(0)?->getAttribute('datetime'):null;
                if(!$date){throw new \RuntimeException('Current story has no recorded publication date; review before import.');}
                $data=['title'=>$x->query('//h1')->item(0)->textContent,'card_title'=>$card?$hx->query('.//h3/a',$card)->item(0)->textContent:'','excerpt'=>$excerpt ?? '','slug'=>$slug,'category'=>$category ?? 'Updates','author'=>'EdFund Uganda Foundation','published_at'=>$date.' 00:00:00','featured_image'=>hash('sha256',$image->getAttribute('src')),'featured_alt'=>$image->getAttribute('alt'),'featured_caption'=>$caption?->textContent ?? '','seo_title'=>$x->query('//title')->item(0)->textContent,'seo_description'=>$x->query('//meta[@name="description"]')->item(0)->getAttribute('content'),'blocks'=>[['type'=>'text','delta'=>['ops'=>$ops]]]];
                $data=(new Posts($this->db,$this->config))->validate($data,true);$s=$this->db->prepare('INSERT INTO cms_posts (published_slug,imported_path,imported_html,updated_at) VALUES (?,?,?,UTC_TIMESTAMP())');$s->execute([$slug,$path,$html]);$id=(int)$this->db->lastInsertId();
                $s=$this->db->prepare('INSERT INTO cms_post_revisions (post_id,content,action,created_at) VALUES (?, ?,\'import\',UTC_TIMESTAMP())');$s->execute([$id,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$revision=(int)$this->db->lastInsertId();$s=$this->db->prepare('UPDATE cms_posts SET draft_id=?,published_id=? WHERE id=?');$s->execute([$revision,$revision,$id]);$s=$this->db->prepare('INSERT INTO cms_post_routes (slug,post_id) VALUES (?,?)');$s->execute([$slug,$id]);Media::reference($this->db,'post',(string)$revision,$data);$count++;
            }$this->db->commit();return $count;
        }finally{if($this->db->inTransaction()){$this->db->rollBack();}$s=$this->db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
    }
}
