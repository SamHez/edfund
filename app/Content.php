<?php
declare(strict_types=1);
namespace EdFund;
/** Trusted template bindings, never user-supplied HTML, XPath or template paths. */
final class Content
{
    private array $templateCache=[];
    private ?array $mediaCache=null;
    public function __construct(private readonly \PDO $db, private readonly ?Crypto $crypto=null) {}
    public static function dom(string $html): \DOMDocument
    {
        $dom=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
        try {$dom->loadHTML('<?xml encoding="UTF-8"><div id="cms-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);}
        finally {libxml_clear_errors();libxml_use_internal_errors($old);}return $dom;
    }
    public static function inner(\DOMNode $node): string {$html='';foreach($node->childNodes as $child){$html.=$node->ownerDocument->saveHTML($child);}return $html;}
    public function template(string $id): array
    {
        if(isset($this->templateCache[$id])){return $this->templateCache[$id];}
        $s=$this->db->prepare('SELECT * FROM cms_templates WHERE id=?');$s->execute([$id]);$t=$s->fetch();
        if(!$t){throw new \InvalidArgumentException('Unknown layout.');}
        $t['fields']=json_decode($t['fields'],true,32,JSON_THROW_ON_ERROR);$t['defaults']=json_decode($t['defaults'],true,32,JSON_THROW_ON_ERROR);return $this->templateCache[$id]=$t;
    }
    public function media(): array {return $this->mediaCache ??= $this->db->query('SELECT * FROM cms_media ORDER BY label')->fetchAll();}
    public function templates(): array
    {
        $result=[];
        foreach($this->db->query('SELECT id FROM cms_templates')->fetchAll(\PDO::FETCH_COLUMN) as $id){
            $t=$this->template($id);$dom=self::dom($t['html']);$xp=new \DOMXPath($dom);$groups=[];$headings=[];$group=$t['label'];
            foreach($xp->query('//*[@id="cms-root"]//* | //*[@id="cms-root"]//text()') as $node){
                if($node instanceof \DOMElement && preg_match('/^h[1-6]$/',$node->tagName)){$level=(int)substr($node->tagName,1);foreach(array_keys($headings) as $l){if($l>=$level){unset($headings[$l]);}}$headings[$level]=trim($node->textContent);$group=implode(' / ',$headings);}
                $groups[$node->getNodePath()]=$group;
            }
            foreach($t['fields'] as &$field){$field['group']=$groups[$field['path']] ?? $t['label'];unset($field['path']);}unset($field);
            unset($t['html']);$result[$id]=$t;
        }return $result;
    }
    public function validate(array $data,string $slug): array
    {
        if(array_keys($data)!==['sections'] || !is_array($data['sections']) || !array_is_list($data['sections']) || count($data['sections'])<1 || count($data['sections'])>30){throw new \InvalidArgumentException('Use between 1 and 30 sections.');}
        $this->document($slug);$q=$this->db->prepare('SELECT id FROM cms_revisions WHERE document_slug=? AND action=? ORDER BY id LIMIT 1');$q->execute([$slug,'import']);$initial=$this->revision((int)$q->fetchColumn(),$slug);
        $allowed=array_column($initial['sections'],'template');
        $ids=[];$seenTemplates=[];$hero=0;$h1=0;$media=array_column($this->media(),null,'id');
        foreach($data['sections'] as $i=>&$section){
            if(!is_array($section) || count($section)!==3 || !isset($section['id'],$section['template'],$section['values']) || !is_string($section['id']) || !preg_match('/\A[a-f0-9]{24}\z/',$section['id']) || isset($ids[$section['id']]) || !is_string($section['template']) || !is_array($section['values'])){throw new \InvalidArgumentException('Invalid section structure.');}
            $ids[$section['id']]=true;$t=$this->template($section['template']);
            if(!in_array($t['id'],$allowed,true) && (!$t['approved'] || $slug!=='home')){throw new \InvalidArgumentException('This layout is not approved for this page.');}
            if(!$t['approved'] && isset($seenTemplates[$t['id']])){throw new \InvalidArgumentException('Use the approved layouts when adding a section.');}$seenTemplates[$t['id']]=true;
            $hero+=($t['kind']==='hero');$h1+=substr_count(strtolower($t['html']),'<h1');
            if(count($section['values'])!==count($t['fields'])){throw new \InvalidArgumentException('Section fields have changed. Reload the editor.');}
            foreach($t['fields'] as $key=>$f){
                $v=$section['values'][$key] ?? null;
                if(!is_string($v) || !mb_check_encoding($v,'UTF-8') || mb_strlen($v)>($f['max'] ?? 5000) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$v)){throw new \InvalidArgumentException($f['label'].': invalid or oversized value.');}
                if($f['type']==='image' && !isset($media[$v])){throw new \InvalidArgumentException('Choose an image from the existing media library.');}
                if($f['type']==='url' && !self::url($v)){throw new \InvalidArgumentException($f['label'].': enter a local link, HTTPS URL, email or telephone link.');}
                if($f['type']==='youtube' && !preg_match('/\A[A-Za-z0-9_-]{11}\z/',$v)){throw new \InvalidArgumentException('Use an eleven-character YouTube video ID.');}
                if($f['type']==='heading' && trim($v)===''){throw new \InvalidArgumentException('Headings cannot be empty.');}
            }
        }unset($section);
        if($slug==='home' && ($hero!==1 || $h1!==1 || $this->template($data['sections'][0]['template'])['kind']!=='hero')){throw new \InvalidArgumentException('Keep the single hero first, with one page heading.');}
        if($slug==='shared' && array_column($data['sections'],'template')!==$allowed){throw new \InvalidArgumentException('Keep the shared header and footer structure.');}
        if(strlen(json_encode($data,JSON_THROW_ON_ERROR))>400000){throw new \InvalidArgumentException('Page is too large.');}return $data;
    }
    public static function url(string $v): bool
    {
        if(preg_match('/[\x00-\x20\x7f\\\\<>"\']/', $v)){return false;}
        if(str_starts_with($v,'/') && !str_starts_with($v,'//')){return !str_contains($v,'/../');}
        if(preg_match('/\A#[A-Za-z][\w:.-]*\z/',$v)){return true;}
        if(str_starts_with($v,'mailto:')){return (bool)filter_var(substr($v,7),FILTER_VALIDATE_EMAIL);}
        if(preg_match('/\Atel:\+?[0-9()-]{5,25}\z/',$v)){return true;}
        $p=parse_url($v);return $p && ($p['scheme'] ?? '')==='https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) && (bool)filter_var($v,FILTER_VALIDATE_URL);
    }
    public function document(string $slug): array
    {
        $s=$this->db->prepare('SELECT * FROM cms_documents WHERE slug=?');$s->execute([$slug]);return $s->fetch() ?: throw new \InvalidArgumentException('Content is not imported. Run the private content:import command.');
    }
    public function revision(int $id,string $slug): array
    {
        $s=$this->db->prepare('SELECT content FROM cms_revisions WHERE id=? AND document_slug=?');$s->execute([$id,$slug]);$json=$s->fetchColumn();if(!$json){throw new \InvalidArgumentException('Revision not found.');}return json_decode($json,true,32,JSON_THROW_ON_ERROR);
    }
    public function state(string $slug): array
    {
        $d=$this->document($slug);$s=$this->db->prepare('SELECT id,action,created_at FROM cms_revisions WHERE document_slug=? ORDER BY id DESC LIMIT 50');$s->execute([$slug]);
        return ['version'=>(int)$d['version'],'draft'=>(int)$d['draft_id'],'published'=>(int)$d['published_id'],'content'=>$this->revision((int)$d['draft_id'],$slug),'history'=>$s->fetchAll()];
    }
    public function change(string $slug,int $version,string $action,?array $data,?int $revision,int $owner): array
    {
        if(!in_array($action,['save','publish','restore'],true)){throw new \InvalidArgumentException('Unknown action.');}
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT version FROM cms_documents WHERE slug=? FOR UPDATE');$s->execute([$slug]);$current=$s->fetchColumn();
            if($current===false || (int)$current!==$version){throw new ContentConflict('A newer version exists. Reload to review it; your unsaved fields have not been overwritten.');}
            $data=$this->validate($action==='restore'?$this->revision($revision ?? 0,$slug):($data ?? []),$slug);
            $s=$this->db->prepare('INSERT INTO cms_revisions (document_slug,content,action,owner_id,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())');$s->execute([$slug,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$action,$owner]);$id=(int)$this->db->lastInsertId();
            $s=$this->db->prepare('UPDATE cms_documents SET version=version+1,draft_id=?,'.($action==='publish'?'published_id=?,':'').'updated_at=UTC_TIMESTAMP() WHERE slug=?');$s->execute($action==='publish'?[$id,$id,$slug]:[$id,$slug]);
            $s=$this->db->prepare('INSERT INTO cms_audit (user_id,event,source_hash,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');$s->execute([$owner,'content.'.$slug.'.'.$action,$this->crypto?->digest('ip:'.($_SERVER['REMOTE_ADDR'] ?? 'cli')) ?? hash('sha256','cli')]);$this->db->commit();return $this->state($slug);
        }catch(\Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
    public function section(array $section,bool $preview=false): string
    {
        $t=$this->template($section['template']);$dom=self::dom($t['html']);$xp=new \DOMXPath($dom);$media=array_column($this->media(),null,'id');
        foreach($t['fields'] as $key=>$f){$node=$xp->query($f['path'])->item(0);if(!$node){throw new \RuntimeException('Template binding no longer exists.');}$v=$section['values'][$key];
            if($f['type']==='image'){
                $m=$media[$v];$node->setAttribute('src',$m['path']);
                // Retain the exact imported responsive markup until a different image is selected.
                if($v!==$t['defaults'][$key]){$node->setAttribute('width',(string)$m['width']);$node->setAttribute('height',(string)$m['height']);if($m['srcset']){$node->setAttribute('srcset',$m['srcset']);}else{$node->removeAttribute('srcset');}}
                $p=$node->parentNode;if($p instanceof \DOMElement && $p->tagName==='a' && (str_contains($p->getAttribute('class'),'lightbox') || $p->hasAttribute('data-why-gallery') || $p->hasAttribute('data-project-title') || str_starts_with($p->getAttribute('href'),'/assets/images/'))){$p->setAttribute('href',$m['path']);}
            }elseif($f['attribute'] ?? false){$node->setAttribute($f['attribute'],$v);}else{$node->nodeValue=$v;}
        }
        $root=$dom->getElementById('cms-root');if($preview){foreach($root->childNodes as $child){if($child instanceof \DOMElement){$child->setAttribute('data-cms-section',$section['id']);}}}return self::inner($root);
    }
    public function fragments(array $data,bool $preview=false): string {return implode("\n",array_map(fn($s)=>$this->section($s,$preview),$data['sections']));}
    public function page(?array $home=null,?array $shared=null,bool $preview=false): string
    {
        // A transaction gives the request a consistent pair of publication pointers.
        $own=!$this->db->inTransaction();if($own){$this->db->beginTransaction();}
        try {$h=$this->document('home');$s=$this->document('shared');$home ??= $this->revision((int)$h['published_id'],'home');$shared ??= $this->revision((int)$s['published_id'],'shared');
            $parts=[];foreach($shared['sections'] as $section){$parts[$this->template($section['template'])['kind']]=$this->section($section,$preview);}
            $html=strtr($h['shell'],['<!--CMS:main-->'=>$this->fragments($home,$preview),'<!--CMS:topbar-->'=>$parts['topbar'],'<!--CMS:header-->'=>$parts['header'],'<!--CMS:footer-->'=>$parts['footer'],'<!--CMS:seo-->'=>$parts['seo']]);
            // Code assets can change without reseeding any client fields or template snapshot.
            foreach(['css/styles.css','js/main.js'] as $asset){$hash=substr(hash_file('sha256',dirname(__DIR__).'/public/assets/'.$asset),0,12);$html=preg_replace_callback('~(/assets/'.preg_quote($asset,'~').'\?v=)[a-f0-9]+~',fn($m)=>$m[1].$hash,$html);}
            if($own){$this->db->commit();}return $html;
        }catch(\Throwable $e){if($own && $this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
}
