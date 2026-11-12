<?php
declare(strict_types=1);
namespace EdFund;
final class Posts
{
    public function __construct(private readonly \PDO $db,private readonly array $config) {}
    public static function text(mixed $v,int $max,string $label): string
    {
        if(!is_string($v) || !mb_check_encoding($v,'UTF-8') || mb_strlen($v)>$max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$v)){throw new \InvalidArgumentException($label.' is invalid or too long.');}return $v;
    }
    public function validate(array $data,bool $publish=false): array
    {
        $keys=['title','card_title','excerpt','slug','category','author','published_at','featured_image','featured_alt','featured_caption','seo_title','seo_description','blocks'];
        if(array_diff(array_keys($data),$keys) || array_diff($keys,array_keys($data))){throw new \InvalidArgumentException('Invalid post fields.');}
        foreach(['title'=>200,'card_title'=>200,'excerpt'=>600,'slug'=>150,'category'=>80,'author'=>150,'published_at'=>19,'featured_image'=>64,'featured_alt'=>500,'featured_caption'=>2000,'seo_title'=>250,'seo_description'=>500] as $key=>$max){$data[$key]=self::text($data[$key],$max,ucfirst(str_replace('_',' ',$key)));}
        if(!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',$data['slug']) || in_array($data['slug'],['index','index-html','page','feed','admin'],true)){throw new \InvalidArgumentException('Use a simple, stable lowercase story slug.');}
        if(trim($data['title'])===''){throw new \InvalidArgumentException('Enter a post title.');}
        if($data['published_at']!==''){$date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$data['published_at'],new \DateTimeZone('UTC'));if(!$date || $date->format('Y-m-d H:i:s')!==$data['published_at'] || $date->getTimestamp()>time()){throw new \InvalidArgumentException('Publication time must be a real past or current UTC date; scheduling is unavailable.');}}
        $media=new Media($this->db,$this->config);if($data['featured_image']!==''){$media->usable($data['featured_image']);}
        if($publish && (trim($data['author'])==='' || trim($data['excerpt'])==='' || $data['featured_image']==='' || trim($data['featured_alt'])==='' || $data['title']==='Untitled story')){throw new \InvalidArgumentException('Before publishing, enter a title, excerpt, author and featured picture with an image description.');}
        if(!is_array($data['blocks']) || !array_is_list($data['blocks']) || count($data['blocks'])<1 || count($data['blocks'])>40){throw new \InvalidArgumentException('Use between 1 and 40 content blocks.');}
        foreach($data['blocks'] as &$block){
            if(!is_array($block) || !is_string($block['type'] ?? null)){throw new \InvalidArgumentException('Invalid content block.');}
            $expected=match($block['type']){'text'=>['type','delta'],'image'=>['type','media','alt','caption'],'youtube'=>['type','video','title','poster'],default=>throw new \InvalidArgumentException('Use text, picture or YouTube blocks.')};
            if(array_diff(array_keys($block),$expected) || array_diff($expected,array_keys($block))){throw new \InvalidArgumentException('Invalid block fields.');}
            if($block['type']==='text'){if(!is_array($block['delta'])){throw new \InvalidArgumentException('Invalid formatted text.');}$block['delta']=RichText::validate($block['delta']);}
            elseif($block['type']==='image'){$media->usable(self::text($block['media'],64,'Picture'));self::text($block['alt'],500,'Image description');self::text($block['caption'],2000,'Caption');if($publish && trim($block['alt'])===''){throw new \InvalidArgumentException('Describe every picture before publishing.');}}
            else{$block['video']=YouTube::id(self::text($block['video'],1000,'YouTube link'));self::text($block['title'],200,'Video title');$media->usable(self::text($block['poster'],64,'Video poster'));if(trim($block['title'])===''){throw new \InvalidArgumentException('Enter a video title.');}}
        }unset($block);if(strlen(json_encode($data,JSON_THROW_ON_ERROR))>350000){throw new \InvalidArgumentException('Post is too large.');}return $data;
    }
    public function row(int $id,bool $lock=false): array
    {
        $s=$this->db->prepare('SELECT * FROM cms_posts WHERE id=?'.($lock?' FOR UPDATE':''));$s->execute([$id]);return $s->fetch() ?: throw new \InvalidArgumentException('Post not found.');
    }
    public function revision(int $id,int $post): array
    {
        $s=$this->db->prepare('SELECT content FROM cms_post_revisions WHERE id=? AND post_id=?');$s->execute([$id,$post]);$raw=$s->fetchColumn();return $raw?json_decode($raw,true,32,JSON_THROW_ON_ERROR):throw new \InvalidArgumentException('Post revision not found.');
    }
    public function state(int $id): array
    {
        $row=$this->row($id);$s=$this->db->prepare('SELECT id,action,created_at FROM cms_post_revisions WHERE post_id=? ORDER BY id DESC LIMIT 50');$s->execute([$id]);
        return ['id'=>$id,'version'=>(int)$row['version'],'draft'=>(int)$row['draft_id'],'published'=>(int)$row['published_id'],'published_slug'=>$row['published_slug'],'trashed'=>$row['trashed_at']!==null,'content'=>$this->revision((int)$row['draft_id'],$id),'history'=>$s->fetchAll()];
    }
    public function listing(bool $public=false): array
    {
        $sql=$public?'SELECT p.*,r.content FROM cms_posts p JOIN cms_post_revisions r ON r.id=p.published_id WHERE p.trashed_at IS NULL ORDER BY JSON_UNQUOTE(JSON_EXTRACT(r.content,\'$.published_at\')) DESC,p.id DESC':'SELECT p.*,r.content FROM cms_posts p JOIN cms_post_revisions r ON r.id=p.draft_id ORDER BY p.updated_at DESC,p.id DESC';$rows=$this->db->query($sql)->fetchAll();foreach($rows as &$r){$r['content']=json_decode($r['content'],true,32,JSON_THROW_ON_ERROR);}unset($r);return $rows;
    }
    public function create(int $owner,string $author): array
    {
        $data=['title'=>'Untitled story','card_title'=>'','excerpt'=>'','slug'=>'story-'.bin2hex(random_bytes(6)),'category'=>'Updates','author'=>self::text($author,150,'Author'),'published_at'=>'','featured_image'=>'','featured_alt'=>'','featured_caption'=>'','seo_title'=>'','seo_description'=>'','blocks'=>[['type'=>'text','delta'=>['ops'=>[['insert'=>"\n"]]]]]];
        $this->db->beginTransaction();try{$s=$this->db->prepare('INSERT INTO cms_posts (updated_at) VALUES (UTC_TIMESTAMP())');$s->execute();$id=(int)$this->db->lastInsertId();$revision=$this->insert($id,$data,'create',$owner);$s=$this->db->prepare('UPDATE cms_posts SET draft_id=? WHERE id=?');$s->execute([$revision,$id]);$this->audit('post.create',$owner);$this->db->commit();return $this->state($id);}catch(\Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
    private function audit(string $event,int $owner): void {(new Auth($this->db,new Crypto($this->config['key_bytes'])))->audit($event,$owner,$_SERVER['REMOTE_ADDR'] ?? 'cli');}
    private function insert(int $id,array $data,string $action,?int $owner): int
    {
        $s=$this->db->prepare('INSERT INTO cms_post_revisions (post_id,content,action,owner_id,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())');$s->execute([$id,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$action,$owner]);$revision=(int)$this->db->lastInsertId();Media::reference($this->db,'post',(string)$revision,$data);return $revision;
    }
    public function change(int $id,int $version,string $action,?array $data,?int $restore,int $owner,bool $rename=false): array
    {
        if(!in_array($action,['save','publish','restore','trash','untrash'],true)){throw new \InvalidArgumentException('Unknown post action.');}
        $lock='edfund-post-routes-'.substr(hash('sha256',(string)$this->db->query('SELECT DATABASE()')->fetchColumn()),0,20);$s=$this->db->prepare('SELECT GET_LOCK(?,10)');$s->execute([$lock]);if((int)$s->fetchColumn()!==1){throw new ContentConflict('Another post is publishing. Try again.');}
        $this->db->beginTransaction();try{
            $row=$this->row($id,true);if((int)$row['version']!==$version){throw new ContentConflict('This post changed in another tab. Reload before saving; unsaved fields remain here.');}
            if(in_array($action,['trash','untrash'],true)){$data=$this->revision((int)$row['draft_id'],$id);}
            else{$data=$this->validate($action==='restore'?$this->revision($restore ?? 0,$id):($data ?? []),$action==='publish');Media::lockReferences($this->db,$data);}
            if($action==='publish'){
                if($row['trashed_at']){throw new \InvalidArgumentException('Restore the post from trash before publishing.');}
                if($row['published_slug'] && $row['published_slug']!==$data['slug'] && !$rename){throw new \InvalidArgumentException('Confirm the deliberate slug change. The previous URL will redirect.');}
                $s=$this->db->prepare('SELECT post_id FROM cms_post_routes WHERE slug=?');$s->execute([$data['slug']]);$reserved=$s->fetchColumn();if($reserved && (int)$reserved!==$id){throw new ContentConflict('That slug belongs to another story or its previous URL.');}
                $s=$this->db->prepare('INSERT IGNORE INTO cms_post_routes (slug,post_id) VALUES (?,?)');$s->execute([$data['slug'],$id]);$data['published_at']=$data['published_at'] ?: ($row['published_id']?$this->revision((int)$row['published_id'],$id)['published_at']:gmdate('Y-m-d H:i:s'));
            }
            $revision=$this->insert($id,$data,$action,$owner);$updates=['version=version+1','draft_id=?','updated_at=UTC_TIMESTAMP()'];$values=[$revision];
            if($action==='publish'){$updates[]='published_id=?';$values[]=$revision;$updates[]='published_slug=?';$values[]=$data['slug'];}
            if($action==='trash'){$updates[]='trashed_at=UTC_TIMESTAMP()';}if($action==='untrash'){$updates[]='trashed_at=NULL';$updates[]='published_id=NULL';}
            $values[]=$id;$s=$this->db->prepare('UPDATE cms_posts SET '.implode(',',$updates).' WHERE id=?');$s->execute($values);$this->audit('post.'.$action,$owner);$this->db->commit();return $this->state($id);
        }catch(\Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}finally{$s=$this->db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
    }
    public function route(string $slug): ?array
    {
        $s=$this->db->prepare('SELECT p.* FROM cms_post_routes r JOIN cms_posts p ON p.id=r.post_id WHERE r.slug=?');$s->execute([$slug]);return $s->fetch() ?: null;
    }
    public function image(string $id,string $alt,bool $preview=false,string $sizes='100vw'): string
    {
        $s=$this->db->prepare('SELECT m.*,d.storage_key FROM cms_media m LEFT JOIN cms_media_details d ON d.media_id=m.id WHERE m.id=?');$s->execute([$id]);$m=$s->fetch();if(!$m){return '';}$path=$preview && $m['storage_key']?'/admin/media-file/'.$id.'/large.webp':$m['path'];$srcset=$preview && $m['storage_key']?'':$m['srcset'];
        return '<img src="'.self::e($path).'"'.($srcset?' srcset="'.self::e($srcset).'"':'').' sizes="'.self::e($sizes).'" width="'.$m['width'].'" height="'.$m['height'].'" alt="'.self::e($alt).'" loading="lazy" decoding="async">';
    }
    public static function e(string $v): string {return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public function body(array $data,bool $preview=false): string
    {
        $html='';foreach($data['blocks'] as $block){if($block['type']==='text'){$html.=RichText::html($block['delta']);}
            elseif($block['type']==='image'){$html.='<figure>'.$this->image($block['media'],$block['alt'],$preview).'<figcaption>'.self::e($block['caption']).'</figcaption></figure>';}
            else{$id=YouTube::id($block['video']);$html.='<article class="video-card"><div class="video-frame" data-video-id="'.$id.'" data-video-title="'.self::e($block['title']).'"><a class="video-play" href="https://www.youtube.com/watch?v='.$id.'">'.$this->image($block['poster'],$block['title'],$preview).'<span class="play-icon" aria-hidden="true">▶</span><span class="video-platform">Play video</span></a></div><h3>'.self::e($block['title']).'</h3></article>';}}
        return $html;
    }
    public function cards(int $limit=0,bool $home=false): string
    {
        $rows=$this->listing(true);if($limit){$rows=array_slice($rows,0,$limit);}$html='';$heading=$home?'h3':'h2';
        foreach($rows as $row){$d=$row['content'];$url='/stories-updates/'.$row['published_slug'].'/';$html.='<article class="story-card reveal"><a class="story-photo" href="'.$url.'" aria-label="Read: '.self::e($d['card_title'] ?: $d['title']).'">'.$this->image($d['featured_image'],$d['featured_alt'],false,'(max-width:700px) 100vw, 33vw').'</a><div class="story-copy"><p class="story-meta"><span>'.self::e($d['category']).'</span><time datetime="'.substr($d['published_at'],0,10).'">'.gmdate('j F Y',strtotime($d['published_at'])).'</time></p><'.$heading.'><a href="'.$url.'">'.self::e($d['card_title'] ?: $d['title']).'</a></'.$heading.'><p>'.self::e($d['excerpt']).'</p><a class="text-link" href="'.$url.'">Read the story ↗</a></div></article>';}
        return $html ?: '<p>No stories have been published yet.</p>';
    }
}
