<?php
declare(strict_types=1);
namespace EdFund;
final class PublishingAdmin
{
    public static function postsScreen(): string
    {
        return '<div id="posts-app"><p class="screen-intro">Write and publish news from EdFund. Drafts stay private until you publish.</p><div class="editor-toolbar"><button type="button" id="new-post">New story</button><a href="/stories-updates/">View published stories ↗</a><span id="post-status" role="status"></span></div><p id="post-error" role="alert"></p><div id="post-list"></div></div><script src="/assets/js/posts.js?v='.self::hash('js/posts.js').'" defer></script>';
    }
    public static function editorScreen(): string
    {
        $fields='';foreach(['title'=>'Title','card_title'=>'Listing title (optional)','excerpt'=>'Excerpt','slug'=>'Stable URL slug','category'=>'Category','author'=>'Author','published_at'=>'Publication date and time (UTC; blank means now on first publish)','featured_alt'=>'Featured image description','featured_caption'=>'Featured image caption'] as $key=>$label){$tag=in_array($key,['excerpt','featured_caption'],true)?'textarea':'input';$fields.='<label>'.$label.'<'.$tag.' id="post-'.$key.'"'.($tag==='input'?' type="text"':'').'></'.$tag.'></label>';}
        return '<div id="post-editor"><div class="editor-toolbar"><a href="/admin/posts">← Stories</a><button id="post-save" type="button">Save draft</button><button id="post-publish" type="button">Publish</button><button id="post-preview" type="button">Preview</button><button id="post-mobile" type="button">Mobile</button><button id="post-desktop" type="button">Desktop</button><span id="post-status" role="status">Loading…</span></div><p id="post-error" role="alert"></p><div class="editor-grid"><aside class="editor-fields">'.$fields.'<label>Featured picture<select id="post-featured_image"></select></label><img id="featured-preview" class="field-picture" alt="Selected featured picture"><p><a href="/admin/media" target="_blank" rel="noopener">Upload or manage pictures ↗</a> <button type="button" id="post-refresh-media">Refresh pictures</button></p><details><summary>Search and sharing details</summary><label>SEO title<input id="post-seo_title" maxlength="250"></label><label>Meta description<textarea id="post-seo_description" maxlength="500"></textarea></label></details><h2>Revision history</h2><label>Revision<select id="post-revision"></select></label><button type="button" id="post-restore">Restore as draft</button><button type="button" id="post-reload">Reload saved</button><hr><button type="button" id="post-trash">Move to trash</button></aside><div class="post-workspace"><h2>Story content</h2><p class="help">Use short text blocks, pictures and YouTube links. The preview shows the actual website.</p><div id="post-blocks"></div><label>Add content<select id="post-block-type"><option value="text">Formatted text</option><option value="image">Picture</option><option value="youtube">YouTube link</option></select></label><button type="button" id="post-add-block">Add block</button><div class="preview-area"><iframe id="post-frame" title="Private story preview"></iframe></div></div></div></div><link rel="stylesheet" href="/assets/vendor/quill-2.0.3/quill.snow.css"><script src="/assets/vendor/quill-2.0.3/quill.js" defer></script><script src="/assets/js/posts.js?v='.self::hash('js/posts.js').'" defer></script>';
    }
    public static function mediaScreen(): string
    {
        return '<div id="library-app"><p class="screen-intro">Keep your website pictures in one place. Select a picture to edit its description or see where it’s used.</p><p id="library-limits"></p><form id="library-upload"><label>Choose JPEG, PNG or WebP<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><button>Upload picture</button></form><p id="library-status" role="status"></p><p id="library-error" role="alert"></p><label>Find pictures<input type="search" id="library-search"></label><div class="library-layout"><div id="library-grid"></div><aside id="library-details"><p>Select a picture to edit its description or review its use.</p></aside></div></div><script src="/assets/js/media.js?v='.self::hash('js/media.js').'" defer></script>';
    }
    private static function hash(string $path): string {return substr(hash_file('sha256',dirname(__DIR__).'/public/assets/'.$path),0,12);}
    public static function preview(string $html): string
    {
        $html=str_replace('</head>','<script src="/assets/js/preview.js?v='.self::hash('js/preview.js').'" defer></script></head>',$html);$token=bin2hex(random_bytes(16));$_SESSION['previews'] ??= [];
        foreach($_SESSION['previews'] as $key=>$p){if(time()-$p['time']>600){unset($_SESSION['previews'][$key]);}}while(count($_SESSION['previews'])>=5){array_shift($_SESSION['previews']);}$_SESSION['previews'][$token]=['time'=>time(),'html'=>$html];return '/admin/preview?token='.$token;
    }
    public static function api(array $config,string $path,string $method,array $owner): void
    {
        $db=Database::connect($config['db']);$media=new Media($db,$config);$posts=new Posts($db,$config);
        if(preg_match('~\A/admin/media-file/([a-f0-9]{64})/(large|small|thumb)\.webp\z~',$path,$match)){if($method!=='GET'){http_response_code(405);return;}$media->serve($match[1],$match[2].'.webp',true);return;}
        if(str_starts_with($path,'/admin/media-file/')){http_response_code(404);return;}
        header('Content-Type: application/json; charset=utf-8');
        try {
            if($path==='/admin/media-data'){
                if($method==='GET'){$id=$_GET['id'] ?? '';echo json_encode(['media'=>$media->all(true),'limits'=>$media->limits(),'usage'=>is_string($id)&&$id!==''?$media->usage($id):[]],JSON_THROW_ON_ERROR);return;}
                if(($_POST['action'] ?? '')==='upload'){$id=$media->upload($_FILES['image'] ?? [],(int)$owner['id']);echo json_encode(['id'=>$id,'media'=>$media->all(true)],JSON_THROW_ON_ERROR);return;}
            }
            if($method==='GET'){$id=filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);echo json_encode($id?['state'=>$posts->state($id),'media'=>$media->all(true)]:['posts'=>array_map(fn($p)=>['id'=>(int)$p['id'],'title'=>$p['content']['title'],'version'=>(int)$p['version'],'trashed'=>$p['trashed_at']!==null,'published'=>$p['published_id']!==null,'slug'=>$p['published_slug']],$posts->listing())],JSON_THROW_ON_ERROR);return;}
            $raw=$_POST['data'] ?? '';if(!is_string($raw) || strlen($raw)>400000){throw new \InvalidArgumentException('Request is too large.');}$r=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($r) || !is_string($r['action'] ?? null)){throw new \InvalidArgumentException('Invalid request.');}
            if($path==='/admin/media-data'){
                if(!in_array($r['action'],['metadata','delete','restore'],true)){throw new \InvalidArgumentException('Unknown media action.');}$media->edit(Posts::text($r['id'] ?? null,64,'Picture'),Posts::text($r['alt'] ?? '',500,'Description'),Posts::text($r['caption'] ?? '',2000,'Caption'),$r['action']==='delete',(int)$owner['id']);echo json_encode(['media'=>$media->all(true)]);return;
            }
            if($r['action']==='create'){echo json_encode(['state'=>$posts->create((int)$owner['id'],$owner['name'])]);return;}
            if(!is_int($r['id'] ?? null) || $r['id']<1 || (isset($r['content']) && !is_array($r['content']))){throw new \InvalidArgumentException('Invalid post data.');}
            if($r['action']==='preview'){$posts->row($r['id']);$data=$posts->validate($r['content'] ?? []);echo json_encode(['url'=>self::preview(PostView::page($db,$config,$data,true))]);return;}
            if(!is_int($r['version'] ?? null) || (isset($r['revision']) && !is_int($r['revision'])) || (isset($r['rename']) && !is_bool($r['rename']))){throw new \InvalidArgumentException('Invalid post version.');}
            echo json_encode(['state'=>$posts->change($r['id'],$r['version'],$r['action'],$r['content'] ?? null,$r['revision'] ?? null,(int)$owner['id'],$r['rename'] ?? false)],JSON_THROW_ON_ERROR);
        }catch(ContentConflict $e){http_response_code(409);echo json_encode(['error'=>$e->getMessage()]);}
        catch(\InvalidArgumentException|\JsonException $e){http_response_code(422);echo json_encode(['error'=>$e instanceof \JsonException?'Invalid structured content.':$e->getMessage()]);}
    }
}
