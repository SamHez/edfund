<?php
declare(strict_types=1);
namespace EdFund;
final class Frontend
{
    public function __construct(private readonly \PDO $db,private readonly array $config) {}
    private function emit(string $body,int $status=200): void {http_response_code($status);if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='HEAD'){echo $body;}}
    private function redirect(string $target): void {header('Location: '.$target,true,301);header('Cache-Control: no-cache');}
    private function error(int $code): void
    {
        header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');$html=file_get_contents(dirname(__DIR__).'/public/404.html');$html=(new Seo($this->db,$this->config))->render($html,'/404.html',null,null,null,true);$this->emit($html,$code);
    }
    public function run(string $path): void
    {
        header('Referrer-Policy: strict-origin-when-cross-origin');header('X-Robots-Tag: '.(Seo::indexing($this->config)?'index, follow':'noindex, nofollow, noarchive'));
        if(!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');$this->emit('Method not allowed.',405);return;}
        $host=strtolower($_SERVER['HTTP_HOST'] ?? '');$expected=strtolower(parse_url($this->config['base_url'],PHP_URL_HOST).(($p=parse_url($this->config['base_url'],PHP_URL_PORT))?':'.$p:''));
        if($this->config['environment']==='local') {if(!in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true) || $host!==$expected){$this->emit('Local access only.',403);return;}}
        else {
            if(!in_array($host,[$expected,'www.edfundfoundation.org','edfundfoundation.org'],true)){$this->emit('Invalid request host.',400);return;}
            if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off' || $host!==$expected){$this->redirect($this->config['base_url'].$path);return;}
            if(!Seo::indexing($this->config)){$u=$_SERVER['PHP_AUTH_USER'] ?? '';$pw=$_SERVER['PHP_AUTH_PW'] ?? '';$hash=$this->config['staging_password_hash'] ?? '';if(!$hash || !hash_equals($this->config['staging_user'] ?? '',$u) || !password_verify($pw,$hash)){header('WWW-Authenticate: Basic realm="EdFund staging"');header('Cache-Control: no-store');$this->emit('Protected staging. Sign in to continue.',401);return;}}
        }
        if(preg_match('~\A/media/([a-f0-9]{64})/(large|small|thumb)\.webp\z~',$path,$m)){(new Media($this->db,$this->config))->serve($m[1],$m[2].'.webp',false);return;}
        if($path==='/robots.txt'){header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-cache');$this->emit(Seo::indexing($this->config)?"User-agent: *\nDisallow: /admin\nDisallow: /storage\nSitemap: ".Seo::ORIGIN."/sitemap.xml\n":"User-agent: *\nDisallow: /\n");return;}
        if($path==='/sitemap.xml'){header('Content-Type: application/xml; charset=utf-8');header('Cache-Control: no-cache');$this->db->beginTransaction();try{$xml=(new Seo($this->db,$this->config))->sitemap();$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}$this->emit($xml);return;}
        if(str_contains($path,"\0") || str_contains($path,'\\') || preg_match('~(?:^|/)\.[^/]*~',$path)){$this->error(404);return;}
        $original=$path;$path=$path==='/index.php'?'/':preg_replace('~(?:/index\.html|/index\.php)$~','/',$path);$path=$path==='/index.html'?'/':$path;
        if(!str_ends_with($path,'/') && !str_contains(basename($path),'.')){$path.='/';}
        $s=$this->db->prepare('SELECT target_path FROM cms_redirects WHERE source_path=?');$s->execute([$path]);$alias=$s->fetchColumn();if($alias){$path=$alias;}
        $postRow=null;$postData=null;$page=(new Pages($this->db))->byRoute($path);
        if(preg_match('~\A/stories-updates/([a-z0-9]+(?:-[a-z0-9]+)*)/\z~',$path,$m)){$postRow=(new Posts($this->db,$this->config))->route($m[1]);if($postRow){if($postRow['trashed_at'] || !$postRow['published_id']){$this->error($postRow['trashed_at']?410:404);return;}$path='/stories-updates/'.$postRow['published_slug'].'/';$postData=(new Posts($this->db,$this->config))->revision((int)$postRow['published_id'],(int)$postRow['id']);}}
        if($original!==$path && ($page || $postRow || $alias)){$this->redirect($path);return;}
        if(!$page && !$postRow){
            // Compatibility for an installation before the explicit Phase 4 page import.
            $candidate=dirname(__DIR__).'/public'.($path==='/'?'/index.html':$path.'index.html');if(preg_match('~\A/(?:[a-z0-9-]+/)*\z~',$path) && is_file($candidate)){$c=new Content($this->db);$exists=(int)$this->db->query("SELECT COUNT(*) FROM cms_documents WHERE slug='home'")->fetchColumn();$html=$exists?($path==='/'?$c->page():PostView::shared($this->db,file_get_contents($candidate),$path)):file_get_contents($candidate);if($path==='/stories-updates/'){$html=PostView::updateCards($this->db,$html,false);}$this->emit($html);return;}$this->error(404);return;
        }
        if($page && !$page['published_id']){$this->error(404);return;}
        $eligible=PublicCache::eligible();header('Cache-Control: '.($eligible?'no-cache':'no-store, private'));header('Vary: Cookie, Authorization');$cache=new PublicCache($this->db,$this->config);
        $this->db->beginTransaction();try{
        if($postRow){$postRow=(new Posts($this->db,$this->config))->route($postRow['published_slug']);if(!$postRow || $postRow['trashed_at'] || !$postRow['published_id']){$this->db->commit();$this->error($postRow && $postRow['trashed_at']?410:404);return;}$postData=(new Posts($this->db,$this->config))->revision((int)$postRow['published_id'],(int)$postRow['id']);if($path!=='/stories-updates/'.$postRow['published_slug'].'/'){$this->db->commit();$this->redirect('/stories-updates/'.$postRow['published_slug'].'/');return;}}
        $key=$cache->key($path);
        if($eligible && ($html=$cache->get($key))!==null){$this->db->commit();header('X-EdFund-Cache: HIT');$this->emit($html);return;}
            $seo=new Seo($this->db,$this->config);
            if($postRow){$posts=new Posts($this->db,$this->config);$s=$this->db->prepare('SELECT action,created_at FROM cms_post_revisions WHERE id=?');$s->execute([$postRow['published_id']]);$r=$s->fetch();$html=$r['action']==='import' && $postRow['imported_html']?PostView::shared($this->db,$postRow['imported_html'],'/stories-updates/'):PostView::page($this->db,$this->config,$postData);$meta=['title'=>$postData['seo_title'] ?: $postData['title'].' | EdFund Uganda Foundation','description'=>$postData['seo_description'] ?: $postData['excerpt'],'image'=>$postData['featured_image'],'image_alt'=>$postData['featured_alt']];$modified=$r['action']==='import'?$postData['published_at']:$r['created_at'];$html=$seo->render($html,$path,$meta,$postData,$modified);}
            else{$html=(new Pages($this->db))->render($page['document_slug']);$html=$seo->render($html,$path,$seo->pageData($page['document_slug']),null,max($seo->modified($page['document_slug']),$seo->modified('shared')));}
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
        if($eligible){$cache->put($key,$html);}header('X-EdFund-Cache: '.($eligible?'MISS':'BYPASS'));$this->emit($html);
    }
}
