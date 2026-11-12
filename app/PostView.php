<?php
declare(strict_types=1);
namespace EdFund;
final class PostView
{
    public static function dom(string $html): \DOMDocument {$d=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);try{$d->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}return $d;}
    public static function output(\DOMDocument $d): string {return preg_replace('/<\?xml encoding="UTF-8"\s*\?>/','',$d->saveHTML());}
    public static function replace(\DOMDocument $dom,\DOMNode $node,string $html): void
    {
        $fragment=Content::dom($html);$root=$fragment->getElementById('cms-root');foreach(iterator_to_array($node->childNodes) as $c){$node->removeChild($c);}foreach($root->childNodes as $c){$node->appendChild($dom->importNode($c,true));}
    }
    public static function shared(\PDO $db,string $html,string $path): string
    {
        $c=new Content($db);$shared=$c->document('shared');$data=$c->revision((int)$shared['published_id'],'shared');$d=self::dom($html);$x=new \DOMXPath($d);
        foreach($data['sections'] as $s){$kind=$c->template($s['template'])['kind'];if($kind==='seo'){continue;}$query=match($kind){'topbar'=>'//div[contains(concat(" ",normalize-space(@class)," ")," topbar ")]','header'=>'//header','footer'=>'//footer'};$target=$x->query($query)->item(0);if(!$target){continue;}$f=Content::dom($c->section($s));$node=$f->getElementById('cms-root')->firstChild;if($kind==='header'){$fx=new \DOMXPath($f);foreach($fx->query('//nav//a') as $a){$a->removeAttribute('aria-current');if($a->getAttribute('href')===$path){$a->setAttribute('aria-current','page');}}}$target->parentNode->replaceChild($d->importNode($node,true),$target);}
        return self::output($d);
    }
    public static function page(\PDO $db,array $config,array $data,bool $preview=false): string
    {
        $posts=new Posts($db,$config);$html=(new Content($db))->page();$d=self::dom($html);$x=new \DOMXPath($d);$url='https://edfundfoundation.org/stories-updates/'.$data['slug'].'/';$e=Posts::e(...);$date=$data['published_at']?gmdate('j F Y',strtotime($data['published_at'])):'Unpublished draft';
        $body='<section class="page-banner photo-banner">'.($data['featured_image']?$posts->image($data['featured_image'],$data['featured_alt'],$preview):'').'<div class="container"><p class="banner-breadcrumb"><a href="/">Home</a> / <a href="/stories-updates/">Stories &amp; Updates</a></p><h1>'.$e($data['title']).'</h1><p>'.$e($date).' · '.$e($data['author']).'</p></div></section><section class="section"><div class="container"><article class="article">'.($data['featured_image']?'<figure>'.$posts->image($data['featured_image'],$data['featured_alt'],$preview).'<figcaption>'.$e($data['featured_caption']).'</figcaption></figure>':'').$posts->body($data,$preview).'<a class="text-link" href="/stories-updates/">← All stories &amp; updates</a></article></div></section>';
        self::replace($d,$d->getElementById('main'),$body);$title=$data['seo_title'] ?: $data['title'].' | EdFund Uganda Foundation';$description=$data['seo_description'] ?: $data['excerpt'];self::replace($d,$x->query('//title')->item(0),$e($title));
        foreach(['//meta[@name="description"]'=> $description,'//meta[@property="og:title"]'=>$title,'//meta[@property="og:description"]'=>$description,'//meta[@property="og:url"]'=>$url] as $query=>$v){$node=$x->query($query)->item(0);if($node){$node->setAttribute('content',$v);}}
        $x->query('//link[@rel="canonical"]')->item(0)->setAttribute('href',$url);$x->query('//meta[@property="og:type"]')->item(0)->setAttribute('content','article');
        foreach(iterator_to_array($x->query('//link[@rel="preload" and @as="image"] | //script[@type="application/ld+json"]')) as $node){$node->parentNode->removeChild($node);}
        if($data['featured_image']){$m=(new Media($db,$config))->usable($data['featured_image']);$x->query('//meta[@property="og:image"]')->item(0)->setAttribute('content','https://edfundfoundation.org'.$m['path']);$x->query('//meta[@property="og:image:alt"]')->item(0)->setAttribute('content',$data['featured_alt']);}
        $schema=['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$data['title'],'description'=>$description,'url'=>$url,'author'=>['@type'=>$data['author']==='EdFund Uganda Foundation'?'Organization':'Person','name'=>$data['author']],'publisher'=>['@type'=>'Organization','name'=>'EdFund Uganda Foundation']];if($data['published_at']){$schema['datePublished']=str_replace(' ','T',$data['published_at']).'Z';}$script=$d->createElement('script');$script->setAttribute('type','application/ld+json');$script->appendChild($d->createTextNode(json_encode($schema,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)));$d->getElementsByTagName('head')->item(0)->appendChild($script);
        foreach($x->query('//nav//a') as $a){$a->removeAttribute('aria-current');if($a->getAttribute('href')==='/stories-updates/'){$a->setAttribute('aria-current','page');}}
        return (new Seo($db,$config))->render(self::output($d),'/stories-updates/'.$data['slug'].'/',['title'=>$title,'description'=>$description,'image'=>$data['featured_image'],'image_alt'=>$data['featured_alt']],$data,null,$preview);
    }
    public static function updateCards(\PDO $db,string $html,bool $home): string
    {
        // Keep the exact imported card markup until an editor changes publication state.
        $changed=(int)$db->query("SELECT COUNT(*) FROM cms_posts p LEFT JOIN cms_post_revisions r ON r.id=p.published_id WHERE (p.published_id IS NOT NULL AND r.action<>'import') OR (p.imported_path IS NOT NULL AND (p.trashed_at IS NOT NULL OR p.published_id IS NULL))")->fetchColumn();if(!$changed){return $html;}
        $d=self::dom($html);$x=new \DOMXPath($d);$target=$x->query('//*[contains(concat(" ",normalize-space(@class)," ")," story-list ")]')->item(0);if(!$target){return $html;}$config=Config::load();self::replace($d,$target,(new Posts($db,$config))->cards($home?3:0,$home));return self::output($d);
    }
}
