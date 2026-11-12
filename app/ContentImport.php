<?php
declare(strict_types=1);
namespace EdFund;
/** Explicit CLI-only snapshot of trusted repository HTML. Never called by a request or deployment. */
final class ContentImport
{
    public function __construct(private readonly \PDO $db) {}
    private function media(): void
    {
        $root=dirname(__DIR__).'/public';$s=$this->db->prepare('INSERT IGNORE INTO cms_media (id,path,label,width,height,srcset) VALUES (?,?,?,?,?,?)');
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/assets/images',\FilesystemIterator::SKIP_DOTS)) as $file){
            if(!in_array(strtolower($file->getExtension()),['jpg','jpeg','png','webp'],true) || !$file->isFile()){continue;}
            $path=substr($file->getPathname(),strlen($root));$size=getimagesize($file->getPathname());if(!$size){continue;}$srcset='';$mobile=preg_replace('/\.(webp|png|jpe?g)$/','-mobile.$1',$path);
            if(!str_contains($path,'-mobile.') && is_file($root.$mobile)){$m=getimagesize($root.$mobile);$srcset=$mobile.' '.$m[0].'w, '.$path.' '.$size[0].'w';}
            $s->execute([hash('sha256',$path),$path,basename($path),$size[0],$size[1],$srcset]);
        }
    }
    private function template(string $html,string $kind,string $label,bool $approved=false): array
    {
        $dom=Content::dom($html);$xp=new \DOMXPath($dom);$fields=[];$values=[];$group=$label;$headings=[];
        $nodes=iterator_to_array($xp->query('//*[@id="cms-root"]//* | //*[@id="cms-root"]//text()'));
        foreach($nodes as $node){
            if($node instanceof \DOMElement && preg_match('/^h[1-6]$/',$node->tagName)){$level=(int)substr($node->tagName,1);foreach(array_keys($headings) as $l){if($l>=$level){unset($headings[$l]);}}$headings[$level]=trim($node->textContent);$group=implode(' / ',$headings);}
            if($xp->query('ancestor-or-self::script|ancestor-or-self::style|ancestor-or-self::svg|ancestor-or-self::*[@aria-hidden="true"]',$node)->length){continue;}
            if($node instanceof \DOMText){
                if(trim($node->nodeValue)==='' || $xp->query('ancestor::button[not(@role="tab")]|ancestor::*[contains(@class,"navigation-status")]|ancestor::*[contains(@class,"hero-counter")]',$node)->length){continue;}
                $tag=$node->parentNode->nodeName;$type=$xp->query('ancestor::h1|ancestor::h2|ancestor::h3|ancestor::h4',$node)->length?'heading':'text';
                $f=['path'=>$node->getNodePath(),'type'=>$type,'label'=>ucfirst($tag==='p'?'Text':($tag==='figcaption'?'Caption':($type==='heading'?'Heading':'Text'))),'group'=>$group,'max'=>$type==='heading'?300:5000];$v=$node->nodeValue;
                $key='f'.count($fields);$fields[$key]=$f;$values[$key]=$v;continue;
            }
            if(!$node instanceof \DOMElement){continue;}
            $attrs=[];
            if($node->tagName==='img'){$attrs=['src'=>'image','alt'=>'text'];}
            if($node->tagName==='a' && $node->hasAttribute('href') && !str_starts_with($node->getAttribute('href'),'/assets/images/')){$attrs['href']='url';}
            foreach(['data-caption'=>'text','data-video-id'=>'youtube','data-video-title'=>'text'] as $a=>$type){if($node->hasAttribute($a)){$attrs[$a]=$type;}}
            if($kind==='seo' && $node->tagName==='meta' && in_array($node->getAttribute('name') ?: $node->getAttribute('property'),['description','og:title','og:description','og:image:alt'],true)){$attrs['content']='text';}
            foreach($attrs as $attr=>$type){$v=$node->getAttribute($attr);if($type==='image'){$v=hash('sha256',$v);}
                $fieldLabel=match($attr){'src'=>'Picture','alt'=>'Image description','href'=>'Link destination','data-caption'=>'Lightbox caption','data-video-id'=>'YouTube video ID','data-video-title'=>'Video title','content'=>$node->getAttribute('name') ?: $node->getAttribute('property'),default=>$attr};
                $key='f'.count($fields);$fields[$key]=['path'=>$node->getNodePath(),'attribute'=>$attr,'type'=>$type,'label'=>$fieldLabel,'group'=>$group,'max'=>$type==='image'?64:($type==='youtube'?11:($type==='url'?1000:5000))];$values[$key]=$v;
            }
        }
        $html=Content::inner($dom->getElementById('cms-root'));
        $id=hash('sha256',$kind.$html.json_encode($fields));$s=$this->db->prepare('INSERT IGNORE INTO cms_templates (id,kind,label,html,fields,defaults,approved) VALUES (?,?,?,?,?,?,?)');$s->execute([$id,$kind,$label,$html,json_encode($fields,JSON_THROW_ON_ERROR),json_encode($values,JSON_THROW_ON_ERROR),$approved?1:0]);if($approved){$s=$this->db->prepare('UPDATE cms_templates SET approved=1,label=? WHERE id=?');$s->execute([$label,$id]);}
        return ['id'=>bin2hex(random_bytes(12)),'template'=>$id,'values'=>$values];
    }
    public function registerLayouts(): void
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('Layout registration is CLI only.');}
        $own=!$this->db->inTransaction();if($own){$this->db->beginTransaction();}
        try {
        $this->db->exec('UPDATE cms_templates SET approved=2 WHERE approved=1');
        $labels=['home'=>['Homepage slideshow','Who We Are, mission and vision','Impact figures','From Kamuli, in motion','Why Uganda and reconstruction','Three connected paths','Featured technology','Stories & Updates','Project gallery','Get involved','Final call to action'],'shared'=>['Contact bar','Navigation','Footer','Homepage search and sharing details']];
        foreach($labels as $slug=>$names){$q=$this->db->prepare('SELECT content FROM cms_revisions WHERE document_slug=? AND action=? ORDER BY id LIMIT 1');$q->execute([$slug,'import']);$json=$q->fetchColumn();if(!$json){continue;}$initial=json_decode($json,true,32,JSON_THROW_ON_ERROR);foreach($initial['sections'] as $i=>$section){$q=$this->db->prepare('UPDATE cms_templates SET label=? WHERE id=?');$q->execute([$names[$i] ?? 'Section',$section['template']]);}}

        $raw=file_get_contents(dirname(__DIR__).'/public/index.html');$dom=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
        try{$dom->loadHTML('<?xml encoding="UTF-8">'.$raw,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
        $xp=new \DOMXPath($dom);$main=$dom->getElementById('main');$sections=[];foreach($main->childNodes as $n){if($n instanceof \DOMElement){$sections[]=$n;}}
        $mission=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," who-principles ")]')->item(0);
        $project=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," response-project ")]')->item(0);
        $teamDom=Content::dom(file_get_contents(dirname(__DIR__).'/public/our-team/index.html'));$tx=new \DOMXPath($teamDom);$team=$tx->query('//*[contains(concat(" ",normalize-space(@class)," ")," team-card ")]')->item(0);
        $teamHtml=$team ? $teamDom->saveHTML($team) : '';
        // New team blocks use the existing card styling with a plain biography field.
        $teamHtml=preg_replace('~<a\b[^>]*>(.*?)</a>~s','$1',$teamHtml);
        $layouts=[
            'text-image'=>['Text and picture',$dom->saveHTML($sections[6])],
            'mission'=>['Mission and vision','<section class="section"><div class="container">'.$dom->saveHTML($mission).'</div></section>'],
            'kpi'=>['Impact figures',$dom->saveHTML($sections[2])],
            'comparison'=>['Before and after','<section class="section"><div class="container">'.$dom->saveHTML($project).'</div></section>'],
            'gallery'=>['Picture gallery',$dom->saveHTML($sections[8])],
            'youtube'=>['YouTube videos',$dom->saveHTML($sections[3])],
            'team'=>['Team member','<section class="section"><div class="container"><h2>Meet our people</h2><div class="team-grid">'.$teamHtml.'</div><p>Describe this team member’s role and contribution.</p></div></section>'],
            'cta'=>['Call to action',$dom->saveHTML($sections[10])]
        ];
        foreach($layouts as $kind=>[$label,$html]){
            // Additional sections must not duplicate original DOM IDs or internal anchor targets.
            $fragment=Content::dom($html);$fx=new \DOMXPath($fragment);foreach($fx->query('//*[@id and not(@id="cms-root")] | //*[@aria-labelledby] | //*[@aria-controls]') as $n){foreach(['id','aria-labelledby','aria-controls'] as $a){$n->removeAttribute($a);}}
            $this->template(Content::inner($fragment->getElementById('cms-root')),$kind,$label,true);
        }
        if($own){$this->db->commit();}
        }catch(\Throwable $e){if($own && $this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }
    public function run(): bool
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('Import is CLI only.');}
        $s=$this->db->prepare('SELECT GET_LOCK(?,10)');$lock='edfund-import-'.substr(hash('sha256',(string)$this->db->query('SELECT DATABASE()')->fetchColumn()),0,20);$s->execute([$lock]);if((int)$s->fetchColumn()!==1){throw new \RuntimeException('Import is locked.');}
        try {
            if((int)$this->db->query('SELECT COUNT(*) FROM cms_documents')->fetchColumn()>0){return false;}
            $this->db->beginTransaction();$this->media();$raw=file_get_contents(dirname(__DIR__).'/public/index.html');
            $dom=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);try{$dom->loadHTML('<?xml encoding="UTF-8">'.$raw,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
            $xp=new \DOMXPath($dom);$main=$dom->getElementById('main');if(!$main){throw new \RuntimeException('Current homepage has no main content.');}
            $types=[['hero','Homepage slideshow'],['text-image','Who We Are, mission and vision'],['kpi','Impact figures'],['youtube','From Kamuli, in motion'],['why','Why Uganda and reconstruction'],['text-image','Three connected paths'],['text-image','Featured technology'],['stories','Stories & Updates'],['gallery','Project gallery'],['cta','Get involved'],['cta','Final call to action']];$home=[];$elements=[];
            foreach($main->childNodes as $n){if($n instanceof \DOMElement){$elements[]=$n;}}
            if(count($elements)!==count($types)){throw new \RuntimeException('Homepage structure changed: review import mappings before importing.');}
            foreach($elements as $i=>$node){$home[]=$this->template($dom->saveHTML($node),$types[$i][0],$types[$i][1]);}
            while($main->firstChild){$main->removeChild($main->firstChild);}$main->appendChild($dom->createComment('CMS:main'));
            $shared=[];foreach(['topbar'=>'Contact bar','header'=>'Navigation','footer'=>'Footer'] as $kind=>$label){
                $query=$kind==='topbar'?'//div[contains(concat(" ",normalize-space(@class)," ")," topbar ")]':($kind==='header'?'//header':'//footer');$node=$xp->query($query)->item(0);
                $shared[]=$this->template($dom->saveHTML($node),$kind,$label);$node->parentNode->replaceChild($dom->createComment('CMS:'.$kind),$node);
            }
            $head=$dom->getElementsByTagName('head')->item(0);$shared[]=$this->template(Content::inner($head),'seo','Homepage search and sharing details');while($head->firstChild){$head->removeChild($head->firstChild);}$head->appendChild($dom->createComment('CMS:seo'));
            $shell=$dom->saveHTML();$shell=preg_replace('/<\?xml encoding="UTF-8"\s*\?>/','',$shell);
            foreach(['home'=>['Home',$home,$shell],'shared'=>['Shared content',$shared,'']] as $slug=>[$label,$sections,$layout]){
                $s=$this->db->prepare('INSERT INTO cms_documents (slug,label,shell,source_hash,updated_at) VALUES (?,?,?,?,UTC_TIMESTAMP())');$s->execute([$slug,$label,$layout,hash('sha256',$raw)]);
                $s=$this->db->prepare('INSERT INTO cms_revisions (document_slug,content,action,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');$s->execute([$slug,json_encode(['sections'=>$sections],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'import']);$id=$this->db->lastInsertId();$s=$this->db->prepare('UPDATE cms_documents SET draft_id=?,published_id=? WHERE slug=?');$s->execute([$id,$id,$slug]);
            }
            $this->registerLayouts();
            $c=new Content($this->db);$c->validate(['sections'=>$home],'home');$c->validate(['sections'=>$shared],'shared');$this->db->commit();return true;
        }finally{if($this->db->inTransaction()){$this->db->rollBack();}$s=$this->db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
    }
}
