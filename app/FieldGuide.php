<?php
declare(strict_types=1);
namespace EdFund;

/**
 * Presentation hints for the page editor. Template bindings stay developer-controlled;
 * this class only derives human labels, help text and input types from the bound markup.
 */
final class FieldGuide
{
    private const KIND_NAMES=[
        'hero'=>'Hero Slideshow','banner'=>'Page Banner','kpi'=>'Impact Figures','mission'=>'Mission & Vision',
        'why'=>'Why Uganda & Reconstruction','topbar'=>'Contact Bar','header'=>'Navigation Menu','footer'=>'Footer','seo'=>'Search & Sharing',
    ];
    private const KIND_LABELS=[
        'hero'=>'Slideshow','banner'=>'Page banner','kpi'=>'Figures','mission'=>'Statements','why'=>'Chapters','topbar'=>'Header bar',
        'header'=>'Menu','footer'=>'Footer','text-image'=>'Text & image','gallery'=>'Pictures','youtube'=>'Video','team'=>'People',
        'cta'=>'Call to action','stories'=>'Stories','comparison'=>'Before & after','seo'=>'Search',
    ];
    private const KIND_FALLBACK=[
        'text-image'=>'Text & Image','gallery'=>'Picture Gallery','youtube'=>'Video','team'=>'Team Profiles','cta'=>'Call to Action',
        'stories'=>'Stories & Updates','comparison'=>'Before & After',
    ];
    private const DESCRIPTIONS=[
        'kpi'=>'A row of key numbers, each with a short label.',
        'text-image'=>'A heading and text beside a picture.',
        'comparison'=>'Two photos showing a change, with captions.',
        'team'=>'A profile with photo, role and biography.',
        'mission'=>'Two short statements shown side by side.',
        'cta'=>'A highlighted invitation with a button.',
        'gallery'=>'A grid of pictures with captions.',
        'youtube'=>'A YouTube video with a title and description.',
    ];
    private const ITEM_NOUNS=['kpi'=>'Figure','gallery'=>'Picture','stories'=>'Story','team'=>'Profile','youtube'=>'Video','comparison'=>'Photo','cta'=>'Card','text-image'=>'Item','why'=>'Item'];
    private const LINK_HELP='Use a page on this site (like /contact/), a full https:// address, an email link (mailto:) or a phone link (tel:).';

    public static function sectionName(string $kind,string $label): string
    {
        if(isset(self::KIND_NAMES[$kind])){return self::KIND_NAMES[$kind];}
        $label=trim($label);
        if($kind==='stories'){return $label==='Stories & Updates'?'Latest Stories':'Story List';}
        if(preg_match('/improveeducation/i',$label)){return 'Call to Action';}
        if($label==='' || preg_match('/\A(?:Section \d+|Image description|Link destination)\z/i',$label)){return self::KIND_FALLBACK[$kind] ?? 'Section';}
        $clean=rtrim($label,'. ');
        if(preg_match('/\A[\p{L}\p{N} ’\'&-]+\z/u',$clean) && count(explode(' ',$clean))<=4){
            $small=['a','an','and','of','the','in','on','to','for','with'];$words=explode(' ',mb_strtolower($clean));
            foreach($words as $i=>$w){if($i===0 || !in_array($w,$small,true)){$words[$i]=mb_strtoupper(mb_substr($w,0,1)).mb_substr($w,1);}}
            // Preserve intentional capitals such as acronyms and names.
            return implode(' ',array_map(fn($w,$o)=>preg_match('/\p{Lu}.*\p{Lu}/u',$o)?$o:$w,$words,explode(' ',$clean)));
        }
        return $clean;
    }
    public static function kindLabel(string $kind): string {return self::KIND_LABELS[$kind] ?? 'Section';}
    public static function description(string $kind): string {return self::DESCRIPTIONS[$kind] ?? '';}

    private static function classes(\DOMNode $node): string
    {
        $all='';for($n=$node;$n instanceof \DOMElement;$n=$n->parentNode){$all.=' '.strtolower($n->getAttribute('class'));}return ' '.$all.' ';
    }
    private static function has(string $classes,string $needle): bool {return str_contains($classes,$needle);}

    /** Returns hints keyed by field key: title, help, control, group (optional override), item, pair/paired. */
    public static function describe(\DOMXPath $xp,array $fields,string $kind,array $groups): array
    {
        $hints=[];$linkLabels=[];
        foreach($fields as $key=>$f){
            $node=$xp->query($f['path'])->item(0);if(!$node){continue;}
            $el=$node instanceof \DOMElement?$node:$node->parentNode;$tag=strtolower($el->nodeName);$own=strtolower($el->getAttribute('class'));$all=self::classes($el);
            $h=['title'=>'Text','help'=>'','control'=>'text','role'=>'text'];
            $type=$f['type'];$attribute=$f['attribute'] ?? '';
            if($type==='image'){$h=['title'=>'Image','help'=>'','control'=>'image','role'=>'image'];}
            elseif($attribute==='alt'){$h=['title'=>'Image Description','help'=>'Describe what the picture shows. Screen readers and search engines use this.','control'=>'text','role'=>'alt'];}
            elseif($attribute==='data-caption'){$h=['title'=>'Pop-up Caption','help'=>'Shown under the picture when visitors open it larger.','control'=>'textarea','role'=>'caption'];}
            elseif($attribute==='data-video-title'){$h=['title'=>'Video Title','help'=>'Used by screen readers for the video player.','control'=>'text','role'=>'video-title'];}
            elseif($type==='youtube'){$h=['title'=>'YouTube Link','help'=>'Paste the video’s YouTube address, for example https://www.youtube.com/watch?v=…','control'=>'text','role'=>'youtube'];}
            elseif($type==='url'){
                $button=self::has($all,' button ');
                $h=['title'=>$button?'Button Link':(self::has($all,' text-link ')?'Link Address':'Link Address'),'help'=>self::LINK_HELP,'control'=>'link','role'=>'url','element'=>spl_object_id($el)];
                if($node instanceof \DOMElement && preg_match('/\A#team-profile-/',$node->getAttribute('href'))){$h['internal']=true;}
                if($tag==='a' && self::has($all,' story-photo ')){$h['title']='Photo Link';}elseif($tag==='a' && self::has($all,' card-photo ')){$h['title']='Photo Link';}
            }else{
                $h['role']='text';$h['control']='text';
                $a=null;for($n=$node->parentNode;$n instanceof \DOMElement;$n=$n->parentNode){if(strtolower($n->nodeName)==='a'){$a=$n;break;}}
                $headingParent=null;for($n=$node->parentNode;$n instanceof \DOMElement;$n=$n->parentNode){if(preg_match('/\Ah[1-6]\z/',strtolower($n->nodeName))){$headingParent=strtolower($n->nodeName);break;}}
                if(self::has($all,' eyebrow ')){$h=['title'=>'Section Eyebrow','help'=>'The small label above the headline.','control'=>'text','role'=>'eyebrow'];}
                elseif(self::has($all,' banner-breadcrumb ')){$h=['title'=>$tag==='a'?'Breadcrumb Text':'Current Page Name','help'=>'','control'=>'text','role'=>'breadcrumb'];}
                elseif($headingParent==='h1' || ($kind==='hero' && $headingParent!==null)){
                    $h1=null;for($n=$el;$n;$n=$n->parentNode){if(preg_match('/\Ah[1-6]\z/',strtolower($n->nodeName))){$h1=$n;break;}}
                    $split=$h1 && $xp->query('.//br',$h1)->length>0;
                    $title=$kind==='banner'?'Page Title':'Headline';if($split){$title.=$tag==='em'?' (Second Line)':' (First Line)';}
                    $h=['title'=>$title,'help'=>$kind==='banner'?'The main title at the top of the page.':'','control'=>'text','role'=>'heading'];
                }
                elseif($headingParent==='h2' || $f['type']==='heading' && $tag==='h2'){$h=['title'=>'Headline','help'=>'','control'=>'text','role'=>'heading'];}
                elseif($headingParent!==null || $f['type']==='heading'){$h=['title'=>'Heading','help'=>'','control'=>'text','role'=>'heading'];}
                elseif($tag==='time'){$h=['title'=>'Date','help'=>'Shown as written, for example 16 September 2026.','control'=>'short','role'=>'date'];}
                elseif(self::has($all,' story-meta ')){$h=['title'=>'Category','help'=>'','control'=>'short','role'=>'category'];}
                elseif(self::has($all,' gallery-caption ')){$h=['title'=>$tag==='small'?'Caption Detail':'Caption Title','help'=>'','control'=>'text','role'=>'caption'];}
                elseif($tag==='figcaption'){$h=['title'=>'Caption','help'=>'Shown beneath the picture.','control'=>'textarea','role'=>'caption'];}
                elseif(self::has($all,' kpi-source ')){$h=['title'=>'Source Note','help'=>'','control'=>'text','role'=>'source'];}
                elseif($kind==='kpi' && $tag==='strong'){$h=['title'=>'Figure','help'=>'The big number, for example 2,500+','control'=>'short','role'=>'figure'];}
                elseif($kind==='kpi' && $tag==='span'){$h=['title'=>'Figure Label','help'=>'What the number measures.','control'=>'text','role'=>'figure-label'];}
                elseif(self::has($all,' team-profile-role ')){$h=['title'=>'Role','help'=>'','control'=>'text','role'=>'role'];}
                elseif(self::has($all,' team-profile-biography ')){$h=['title'=>'Biography','help'=>'','control'=>'textarea','role'=>'biography'];}
                elseif(self::has($all,' team-profile-cta ')){$h=['title'=>'Profile Button Text','help'=>'','control'=>'text','role'=>'cta'];}
                elseif($tag==='summary'){$h=['title'=>'Heading','help'=>'','control'=>'text','role'=>'heading'];}
                elseif(self::has($all,' hero-location ')){$h=['title'=>'Location Line','help'=>'Shown at the bottom of the slideshow.','control'=>'text','role'=>'location'];}
                elseif(self::has($all,' why-tab-label ')){$h=['title'=>'Tab Label','help'=>'','control'=>'text','role'=>'label'];}
                elseif(self::has($all,' why-tab-number ')){$h=['title'=>'Tab Number','help'=>'','control'=>'short','role'=>'label'];}
                elseif(self::has($all,' why-chapter-kicker ')){$h=['title'=>'Chapter Label','help'=>'','control'=>'text','role'=>'label'];}
                elseif(self::has($all,' card-number ')){$h=['title'=>'Card Number','help'=>'','control'=>'short','role'=>'label'];}
                elseif(self::has($all,' video-platform ')){$h=['title'=>'Video Platform Label','help'=>'','control'=>'text','role'=>'label'];}
                elseif(self::has($all,' video-note ')){$h=['title'=>'Video Note','help'=>'','control'=>'textarea','role'=>'text'];}
                elseif($tag==='dt'){$h=['title'=>'Detail Label','help'=>'','control'=>'text','role'=>'label'];}
                elseif($tag==='dd'){$h=['title'=>'Detail','help'=>'','control'=>'text','role'=>'text'];}
                elseif($tag==='li'){$h=['title'=>'List Item','help'=>'','control'=>'text','role'=>'text'];}
                elseif($a && self::has($all,' button ')){$h=['title'=>'Button Label','help'=>'','control'=>'text','role'=>'link-label'];}
                elseif($a){$h=['title'=>'Link Text','help'=>'','control'=>'text','role'=>'link-label'];}
                elseif($kind==='hero' && $tag==='p'){$h=['title'=>'Supporting Text','help'=>'A sentence or two under the headline.','control'=>'textarea','role'=>'text'];}
                elseif($kind==='banner' && $tag==='p'){$h=['title'=>'Introduction','help'=>'','control'=>'textarea','role'=>'text'];}
                elseif($tag==='p'){$h=['title'=>'Text','help'=>'','control'=>'textarea','role'=>'text'];}
                else{$h=['title'=>'Text','help'=>'','control'=>'text','role'=>'text'];}
                if($a){$h['anchor']=spl_object_id($a);}
            }
            // Hero text and images belong to a numbered slide rather than the nearest heading.
            for($n=$el;$n instanceof \DOMElement;$n=$n->parentNode){
                if(self::has(' '.strtolower($n->getAttribute('class')).' ',' hero-slide ')){$i=0;for($p=$n->previousSibling;$p;$p=$p->previousSibling){if($p instanceof \DOMElement && self::has(' '.strtolower($p->getAttribute('class')).' ',' hero-slide ')){$i++;}}$h['group']='Slide '.($i+1);break;}
            }
            if(!isset($h['group']) && $kind==='hero'){$h['group']='Slideshow Footer';}
            // A team member's card and full profile belong together, grouped under the person's name.
            for($n=$el;$n instanceof \DOMElement;$n=$n->parentNode){
                $cls=' '.strtolower($n->getAttribute('class')).' ';$card=self::has($cls,' team-card ');$profile=self::has($cls,' team-profile-fallback ');
                if($card || $profile){
                    $heading=$xp->query('.//*[self::h1 or self::h2 or self::h3]',$n)->item(0);
                    if($heading){$h['group']=trim(preg_replace('/\s+/u',' ',$heading->textContent));}
                    $h['title']=($card?'Card: ':'Full profile: ').$h['title'];break;
                }
            }
            $hints[$key]=$h;
        }
        // Pair each link destination with the visible text inside the same link.
        foreach($hints as $key=>$h){if(isset($h['anchor'])){$linkLabels[$h['anchor']][]=$key;}}
        foreach(array_keys($hints) as $key){
            $h=$hints[$key];
            if($h['role']==='url' && isset($linkLabels[$h['element']])){
                $label=$linkLabels[$h['element']][0];$hints[$key]['paired']=true;$hints[$label]['pair']=$key;
                if($h['title']==='Link Address' && $hints[$label]['title']==='Button Label'){$hints[$key]['title']='Button Link';}
                if($hints[$label]['role']==='heading'){$hints[$key]['title']='Title Link';}
                if($hints[$label]['role']==='breadcrumb'){$hints[$key]['title']='Breadcrumb Link';}
            }
            unset($hints[$key]['element'],$hints[$key]['anchor']);
        }
        // Sections that repeat a card, picture or figure get numbered items; others only disambiguate repeated labels.
        $repeating=in_array($kind,['kpi','gallery','stories','team','youtube','comparison'],true);
        $state=[];
        foreach(array_keys($hints) as $key){
            $h=$hints[$key];if(!empty($h['paired'])){continue;}
            $group=$h['group'] ?? ($groups[$key] ?? '');$st=&$state[$group];$st??=['item'=>1,'first'=>null,'seen'=>[]];
            $title=$h['title'];
            if($repeating){
                if($st['first']===null){$st['first']=$title;}
                elseif($title===$st['first'] && isset($st['seen'][$title])){$st['item']++;$st['seen']=[];}
            }
            $st['seen'][$title]=($st['seen'][$title] ?? 0)+1;$count=$st['seen'][$title];
            $hints[$key]['item']=$repeating?$st['item']:1;
            if($count>1 && !$repeating){$hints[$key]['title']=$title.' '.$count;}
            elseif($count>1){$hints[$key]['title']=$title.' '.$count;}
            unset($st);
        }
        foreach(array_keys($hints) as $key){
            if(!empty($hints[$key]['paired'])){$label=null;foreach($hints as $k=>$o){if(($o['pair'] ?? null)===$key){$label=$k;}}$hints[$key]['item']=$label?($hints[$label]['item'] ?? 1):1;}
            $hints[$key]['noun']=self::ITEM_NOUNS[$kind] ?? 'Item';
        }
        return $hints;
    }
}
