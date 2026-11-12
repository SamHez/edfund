<?php
declare(strict_types=1);
namespace EdFund;
final class Pages
{
    public function __construct(private readonly \PDO $db) {}
    public function rows(): array {return $this->db->query('SELECT p.*,d.label,d.published_id,d.draft_id FROM cms_pages p JOIN cms_documents d ON d.slug=p.document_slug ORDER BY p.route')->fetchAll();}
    public function bySlug(string $slug): ?array {$s=$this->db->prepare('SELECT * FROM cms_pages WHERE document_slug=?');$s->execute([$slug]);return $s->fetch() ?: null;}
    public function byRoute(string $path): ?array {$s=$this->db->prepare('SELECT p.*,d.published_id FROM cms_pages p JOIN cms_documents d ON d.slug=p.document_slug WHERE p.route=?');$s->execute([$path]);return $s->fetch() ?: null;}
    public function import(): int
    {
        if(PHP_SAPI!=='cli'){throw new \RuntimeException('Import is CLI only.');}$import=new ContentImport($this->db);$c=new Content($this->db);$root=dirname(__DIR__).'/public';$count=0;
        $lock=new PublicationLock($this->db);$this->db->beginTransaction();try{
            $files=['home'=>$root.'/index.html'];foreach(glob($root.'/*/index.html') as $file){$files[basename(dirname($file))]=$file;}
            foreach($files as $slug=>$file){if($this->bySlug($slug)){continue;}$raw=$slug==='home'?$c->page():file_get_contents($file);$d=PostView::dom($raw);$x=new \DOMXPath($d);$main=$d->getElementById('main');$h1=$x->query('//main//h1');if(!$main || $h1->length!==1){throw new \RuntimeException('Review the page structure before importing: '.$slug);}
                if($slug!=='home'){
                    $s=$this->db->prepare('SELECT COUNT(*) FROM cms_documents WHERE slug=?');$s->execute([$slug]);if((int)$s->fetchColumn()){throw new \RuntimeException('A document exists without page mapping; review it before import.');}
                    $sections=[];$i=0;foreach(iterator_to_array($main->childNodes) as $node){if(!$node instanceof \DOMElement){continue;}$kind=$i===0?'banner':($slug==='our-team'?'team':($slug==='gallery'?'gallery':($slug==='stories-updates'?'stories':'text-image')));$heading=$x->query('.//h1|.//h2|.//h3',$node)->item(0)?->textContent;$sections[]=$import->template($d->saveHTML($node),$kind,mb_substr(trim($heading ?: 'Section '.($i+1)),0,150));$i++;}
                    PostView::replace($d,$main,'<!--CMS:main-->');$s=$this->db->prepare('INSERT INTO cms_documents (slug,label,shell,source_hash,updated_at) VALUES (?,?,?,?,UTC_TIMESTAMP())');$label=match($slug){'about-us'=>'About Us','our-work'=>'Our Work','our-team'=>'Our Team','stories-updates'=>'Stories & Updates','privacy-policy'=>'Privacy Policy',default=>ucfirst($slug)};$s->execute([$slug,$label,PostView::output($d),hash('sha256',$raw)]);
                    $s=$this->db->prepare('INSERT INTO cms_revisions (document_slug,content,action,created_at) VALUES (?,?,\'import\',UTC_TIMESTAMP())');$data=['sections'=>$sections];$s->execute([$slug,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);$id=(int)$this->db->lastInsertId();$s=$this->db->prepare('UPDATE cms_documents SET draft_id=?,published_id=? WHERE slug=?');$s->execute([$id,$id,$slug]);Media::reference($this->db,'page',(string)$id,$data);$count++;
                }
                // Read metadata from the intact source, before main was replaced by the shell marker.
                $d=PostView::dom($raw);$x=new \DOMXPath($d);$title=$x->query('//title')->item(0)?->textContent ?? '';$description=$x->query('//meta[@name="description"]')->item(0)?->getAttribute('content') ?? '';
                $image=$slug==='home'?$x->query('//meta[@property="og:image"]')->item(0)?->getAttribute('content'):'';$path=$image?parse_url($image,PHP_URL_PATH):'';$img=$x->query('//main//img[not(@aria-hidden="true") and not(ancestor::section[contains(concat(" ",normalize-space(@class)," ")," page-banner ")])]')->item(0) ?? $x->query('//main//img[not(@aria-hidden="true")]')->item(0);$path=$path ?: $img?->getAttribute('src');$id=$path?hash('sha256',$path):'';$q=$this->db->prepare('SELECT id FROM cms_media WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn()){$id='';}
                $alt=$slug==='home'?$x->query('//meta[@property="og:image:alt"]')->item(0)?->getAttribute('content'):($img?->getAttribute('alt') ?? '');
                $seo=['title'=>$title,'description'=>$description,'image'=>$id,'image_alt'=>$alt ?: ''];$s=$this->db->prepare('INSERT INTO cms_pages (document_slug,route,seo_defaults,source_modified_at) VALUES (?,?,?,?)');$s->execute([$slug,$slug==='home'?'/':'/'.$slug.'/',json_encode($seo,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),gmdate('Y-m-d H:i:s',min(time(),filemtime($file)))]);
            }
            $aliases=['/new-about-us/'=>'/about-us/','/ourteam/'=>'/our-team/','/meet-the-team/'=>'/our-team/','/our-blog/'=>'/stories-updates/','/new-contact-us/'=>'/contact/','/contact-us/'=>'/contact/','/become-a-volunteer/'=>'/volunteer/'];
            foreach($this->db->query('SELECT id,imported_path,imported_html FROM cms_posts WHERE imported_html IS NOT NULL')->fetchAll() as $post){$d=PostView::dom($post['imported_html']);$x=new \DOMXPath($d);foreach($x->query('//p[@class="source-note"]//a') as $a){$url=parse_url($a->getAttribute('href'));if(($url['host'] ?? '')==='edfundfoundation.org'){$aliases[rtrim($url['path'],'/').'/']=$post['imported_path'];}}}
            $s=$this->db->prepare('INSERT IGNORE INTO cms_redirects (source_path,target_path,provenance,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');foreach($aliases as $from=>$to){if($from===$to || $this->byRoute($from)){continue;}$s->execute([$from,$to,'Verified current navigation/source-story equivalent']);}
            $this->db->commit();return $count;
        }catch(\Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}finally{$lock->release();}
    }
    public function render(string $slug,?array $data=null,bool $preview=false,?array $shared=null): string
    {
        $c=new Content($this->db);$page=$this->bySlug($slug);if(!$page){throw new \InvalidArgumentException('Page has not been imported.');}$doc=$c->document($slug);$data ??= $c->revision((int)$doc['published_id'],$slug);
        if($slug==='home'){$html=$c->page($data,$shared,$preview);}else{$html=str_replace('<!--CMS:main-->',$c->fragments($data,$preview),$doc['shell']);$html=PostView::shared($this->db,$html,$page['route']);if($slug==='stories-updates'){$html=PostView::updateCards($this->db,$html,false);}}
        return $html;
    }
}
