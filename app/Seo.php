<?php
declare(strict_types=1);
namespace EdFund;
final class Seo
{
    public const ORIGIN='https://edfundfoundation.org';
    public function __construct(private readonly \PDO $db,private readonly array $config) {}
    public static function indexing(array $config): bool {return ($config['environment'] ?? '')==='production' && ($config['public_indexing'] ?? false)===true;}
    public function defaults(string $slug): ?array {$p=(new Pages($this->db))->bySlug($slug);return $p?json_decode($p['seo_defaults'],true,16,JSON_THROW_ON_ERROR):null;}
    public function pageData(string $slug,?array $data=null): ?array
    {
        $defaults=$this->defaults($slug);if(!$defaults){return null;}$c=new Content($this->db);$d=$c->document($slug);$data ??= $c->revision((int)$d['published_id'],$slug);return $data['seo'] ?? $defaults;
    }
    public function validate(array $seo,string $slug): array
    {
        if(array_diff(array_keys($seo),['title','description','image','image_alt']) || count($seo)!==4){throw new \InvalidArgumentException('Invalid SEO fields.');}
        foreach(['title'=>250,'description'=>500,'image'=>64,'image_alt'=>500] as $k=>$max){Posts::text($seo[$k] ?? null,$max,'SEO '.str_replace('_',' ',$k));}
        if(trim($seo['title'])==='' || trim($seo['description'])===''){throw new \InvalidArgumentException('Enter a unique title and description.');}
        if($slug==='home' && !str_starts_with($seo['title'],'ED Fund Uganda Foundation | Education')){throw new \InvalidArgumentException('Keep the homepage title starting ED Fund Uganda Foundation | Education.');}
        if($seo['image']!==''){(new Media($this->db,$this->config))->usable($seo['image']);if(trim($seo['image_alt'])===''){throw new \InvalidArgumentException('Describe the social sharing picture.');}}return $seo;
    }
    public function unique(string $title,string $description,?string $slug=null,?int $post=null): void
    {
        foreach((new Pages($this->db))->rows() as $p){if($p['document_slug']===$slug || !$p['published_id']){continue;}$seo=$this->pageData($p['document_slug']);if(mb_strtolower(trim($seo['title']))===mb_strtolower(trim($title)) || mb_strtolower(trim($seo['description']))===mb_strtolower(trim($description))){throw new \InvalidArgumentException('Another published page uses this SEO title or description. Make it unique.');}}
        foreach((new Posts($this->db,$this->config))->listing(true) as $row){if((int)$row['id']===$post){continue;}$d=$row['content'];if(mb_strtolower(trim($d['seo_title'] ?: $d['title'].' | EdFund Uganda Foundation'))===mb_strtolower(trim($title)) || mb_strtolower(trim($d['seo_description'] ?: $d['excerpt']))===mb_strtolower(trim($description))){throw new \InvalidArgumentException('Another published story uses this SEO title or description. Make it unique.');}}
    }
    public function modified(string $slug): string
    {
        $s=$this->db->prepare('SELECT r.created_at,r.action,p.source_modified_at FROM cms_documents d JOIN cms_revisions r ON r.id=d.published_id LEFT JOIN cms_pages p ON p.document_slug=d.slug WHERE d.slug=?');$s->execute([$slug]);$r=$s->fetch();return $r?($r['action']==='import'?($r['source_modified_at'] ?: $r['created_at']):$r['created_at']):gmdate('Y-m-d H:i:s');
    }
    public function render(string $html,string $route,?array $metadata=null,?array $post=null,?string $modified=null,bool $preview=false): string
    {
        $d=PostView::dom($html);$x=new \DOMXPath($d);$head=$d->getElementsByTagName('head')->item(0);$metadata ??= ['title'=>$x->query('//title')->item(0)?->textContent ?? 'EdFund Uganda Foundation','description'=>$x->query('//meta[@name="description"]')->item(0)?->getAttribute('content') ?? '','image'=>'','image_alt'=>''];$title=$metadata['title'];$description=$metadata['description'];$url=self::ORIGIN.$route;
        $titleNode=$x->query('//title')->item(0);if(!$titleNode){$titleNode=$d->createElement('title');$head->appendChild($titleNode);}PostView::replace($d,$titleNode,Posts::e($title));
        $meta=function(string $key,string $value,bool $property=false) use ($d,$x,$head){$attr=$property?'property':'name';$nodes=$x->query('//meta[@'.$attr.'="'.$key.'"]');$node=$nodes->item(0);foreach(iterator_to_array($nodes) as $i=>$n){if($i){$n->parentNode->removeChild($n);}}if(!$node){$node=$d->createElement('meta');$node->setAttribute($attr,$key);$head->appendChild($node);}$node->setAttribute('content',$value);};
        $meta('description',$description);$meta('robots',!$preview&&self::indexing($this->config)?'index,follow,max-image-preview:large':'noindex,nofollow,noarchive');
        foreach(['og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:type'=>$post?'article':'website','og:site_name'=>'EdFund Uganda Foundation'] as $k=>$v){$meta($k,$v,true);}
        $meta('twitter:card','summary_large_image');$meta('twitter:title',$title);$meta('twitter:description',$description);
        $canon=$x->query('//link[@rel="canonical"]')->item(0);if(!$canon){$canon=$d->createElement('link');$canon->setAttribute('rel','canonical');$head->appendChild($canon);}$canon->setAttribute('href',$url);
        $image='';$imageAlt=$metadata['image_alt'];if($metadata['image']!==''){$s=$this->db->prepare('SELECT path FROM cms_media WHERE id=?');$s->execute([$metadata['image']]);$path=$s->fetchColumn();if($path){$image=self::ORIGIN.$path;}}
        if(!$image){$old=$x->query('//meta[@property="og:image"]')->item(0);$image=$old?->getAttribute('content') ?? '';}$meta('og:image',$image,true);$meta('og:image:alt',$imageAlt,true);$meta('twitter:image',$image);$meta('twitter:image:alt',$imageAlt);
        foreach(iterator_to_array($x->query('//script[@type="application/ld+json"]')) as $n){$n->parentNode->removeChild($n);}
        $email=$x->query('//div[contains(@class,"topbar")]//a[starts-with(@href,"mailto:")]')->item(0)?->getAttribute('href');$phone=$x->query('//div[contains(@class,"topbar")]//a[starts-with(@href,"tel:")]')->item(0)?->textContent;$logo=$x->query('//header//img')->item(0)?->getAttribute('src');$social=[];foreach($x->query('//div[@class="socials"]/a') as $a){if(str_starts_with($a->getAttribute('href'),'https://')){$social[]=$a->getAttribute('href');}}
        $org=['@type'=>'NGO','@id'=>self::ORIGIN.'/#organisation','name'=>'EdFund Uganda Foundation','url'=>self::ORIGIN.'/'];if($email){$org['email']=substr($email,7);}if($phone){$org['telephone']=trim($phone);}if($logo){$org['logo']=self::ORIGIN.$logo;}if($social){$org['sameAs']=array_values(array_unique($social));}
        $website=['@type'=>'WebSite','@id'=>self::ORIGIN.'/#website','url'=>self::ORIGIN.'/','name'=>'EdFund Uganda Foundation','publisher'=>['@id'=>$org['@id']]];
        $webpage=['@type'=>'WebPage','@id'=>$url.'#webpage','url'=>$url,'name'=>$title,'description'=>$description,'isPartOf'=>['@id'=>$website['@id']],'inLanguage'=>'en'];if($image){$webpage['primaryImageOfPage']=['@type'=>'ImageObject','url'=>$image,'caption'=>$imageAlt];}if($modified){$webpage['dateModified']=str_replace(' ','T',$modified).'Z';}
        $graph=[$org,$website,$webpage];if($route!=='/'){$crumb=[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>self::ORIGIN.'/']];if($post){$crumb[]=['@type'=>'ListItem','position'=>2,'name'=>'Stories & Updates','item'=>self::ORIGIN.'/stories-updates/'];}$crumb[]=['@type'=>'ListItem','position'=>count($crumb)+1,'name'=>$post['title'] ?? ($x->query('//main//h1')->item(0)?->textContent ?: $title),'item'=>$url];$graph[]=['@type'=>'BreadcrumbList','itemListElement'=>$crumb];}
        if($post){$article=['@type'=>'BlogPosting','@id'=>$url.'#article','mainEntityOfPage'=>['@id'=>$webpage['@id']],'headline'=>$post['title'],'description'=>$description,'url'=>$url,'author'=>['@type'=>$post['author']==='EdFund Uganda Foundation'?'Organization':'Person','name'=>$post['author']],'publisher'=>['@id'=>$org['@id']]];if($post['published_at']){$article['datePublished']=str_replace(' ','T',$post['published_at']).'Z';}if($modified){$article['dateModified']=str_replace(' ','T',$modified).'Z';}if($image){$article['image']=[$image];}$graph[]=$article;}
        $script=$d->createElement('script');$script->setAttribute('type','application/ld+json');$script->appendChild($d->createTextNode(json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)));$head->appendChild($script);
        foreach($x->query('//nav//a') as $a){$a->removeAttribute('aria-current');if($a->getAttribute('href')===($post?'/stories-updates/':$route)){$a->setAttribute('aria-current','page');}}
        return PostView::output($d);
    }
    public function sitemap(): string
    {
        $urls=[];$shared=$this->modified('shared');foreach((new Pages($this->db))->rows() as $p){if($p['published_id']){$urls[$p['route']]=max($this->modified($p['document_slug']),$shared);}}
        foreach((new Posts($this->db,$this->config))->listing(true) as $p){$s=$this->db->prepare('SELECT created_at,action FROM cms_post_revisions WHERE id=?');$s->execute([$p['published_id']]);$r=$s->fetch();$modified=$r['action']==='import'?$p['content']['published_at']:$r['created_at'];$urls['/stories-updates/'.$p['published_slug'].'/']=max($modified,$shared);}
        ksort($urls);$xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($urls as $path=>$time){$xml.='<url><loc>'.htmlspecialchars(self::ORIGIN.$path,ENT_XML1,'UTF-8').'</loc><lastmod>'.str_replace(' ','T',$time).'Z</lastmod></url>';}return $xml.'</urlset>';
    }
}
