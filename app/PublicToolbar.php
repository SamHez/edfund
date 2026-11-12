<?php
declare(strict_types=1);
namespace EdFund;

final class PublicToolbar
{
    private function __construct(private readonly \PDO $db,private readonly array $user,private readonly string $csrf) {}
    public static function fromSession(\PDO $db,array $config): ?self
    {
        if(!OwnerSession::present()){return null;}
        self::privateHeaders();
        OwnerSession::start($config,false);
        try{
            $user=OwnerSession::user(new Auth($db,new Crypto($config['key_bytes'])));
            return $user?new self($db,$user,$_SESSION['csrf']):null;
        }finally{session_write_close();} // Never hold the owner lock while rendering a page.
    }
    public static function privateHeaders(): void
    {
        header('Cache-Control: no-store, private, max-age=0');header('Pragma: no-cache');header('Expires: 0');header('Vary: Cookie, Authorization');
    }
    private static function e(string $value): string {return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public function decorate(string $html,string $path): string
    {
        $page=(new Pages($this->db))->byRoute($path);$edit='';$label='';
        if($page){$edit='/admin/pages?document='.rawurlencode($page['document_slug']);$label=match($page['document_slug']){'home'=>'Edit Home','about-us'=>'Edit About',default=>'Edit '.(new Content($this->db))->document($page['document_slug'])['label']};}
        elseif(preg_match('~\A/stories-updates/([a-z0-9]+(?:-[a-z0-9]+)*)/\z~',$path,$m)){
            $s=$this->db->prepare('SELECT p.id FROM cms_post_routes r JOIN cms_posts p ON p.id=r.post_id WHERE r.slug=? AND p.published_id IS NOT NULL AND p.trashed_at IS NULL');$s->execute([$m[1]]);$id=$s->fetchColumn();
            if($id){$edit='/admin/post?id='.(int)$id;$label='Edit Story';}
        }
        $token='<input type="hidden" name="csrf" value="'.self::e($this->csrf).'">';
        $icon=AdminLayout::icon(...);
        $toolbar='<div id="edfund-admin-toolbar" data-public-route="'.self::e($path).'"><nav aria-label="Website administration"><a class="toolbar-dashboard" href="/admin/" aria-label="EdFund Dashboard">'.$icon('dashboard').'<span>EdFund <span class="toolbar-wide">/ Dashboard</span></span></a>'.($edit?'<a id="toolbar-edit" href="'.self::e($edit).'">'.$icon('pages').'<span>'.self::e($label).'</span></a>':'').'<form action="/admin/new-story" method="post">'.$token.'<button type="submit">'.$icon('stories').'<span>Create <span class="toolbar-wide">New </span>Story</span></button></form><a class="toolbar-media" href="/admin/media">'.$icon('media').'<span>Media Library</span></a><details class="toolbar-account"><summary aria-label="Account menu">'.$icon('user').'<span>Account</span>'.$icon('chevron').'</summary><div class="toolbar-dropdown"><p>Signed in as<strong>'.self::e($this->user['name']).'</strong></p><a href="/admin/security">Account security</a><a class="toolbar-mobile-media" href="/admin/media">Media Library</a><form action="/admin/logout" method="post">'.$token.'<button type="submit">Sign out</button></form></div></details></nav></div>';
        // Only the private owner response is annotated. Anonymous HTML and cached
        // snapshots retain their exact markup; no template or content is updated.
        $dom=PostView::dom($html);$body=$dom->getElementsByTagName('body')->item(0);$head=$dom->getElementsByTagName('head')->item(0);if(!$body || !$head){return $html;}
        $body->setAttribute('class',trim($body->getAttribute('class').' edfund-owner-view'));
        if($page){$doc=(new Content($this->db))->document($page['document_slug']);$data=(new Content($this->db))->revision((int)$doc['published_id'],$page['document_slug']);$main=$dom->getElementById('main');
            if($main){$children=array_values(array_filter(iterator_to_array($main->childNodes),fn($n)=>$n instanceof \DOMElement));if(count($children)===count($data['sections'])){foreach($children as $index=>$child){$child->setAttribute('data-cms-section',$data['sections'][$index]['id']);}}}
        }
        $fragment=Content::dom($toolbar);foreach(iterator_to_array($fragment->getElementById('cms-root')->childNodes) as $node){$body->insertBefore($dom->importNode($node,true),$body->firstChild);}
        foreach(['css/public-toolbar.css'=>'link','js/public-toolbar.js'=>'script'] as $file=>$tag){$asset=$dom->createElement($tag);$url='/assets/'.$file.'?v='.substr(hash_file('sha256',dirname(__DIR__).'/public/assets/'.$file),0,12);if($tag==='link'){$asset->setAttribute('rel','stylesheet');$asset->setAttribute('href',$url);}else{$asset->setAttribute('src',$url);$asset->setAttribute('defer','');}$head->appendChild($asset);}
        return PostView::output($dom);
    }
}
