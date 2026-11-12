<?php
declare(strict_types=1);
namespace EdFund;
final class Editor
{
    public static function screen(): string
    {
        return '<div id="editor" aria-busy="true"><div class="editor-toolbar"><label>Editing<select id="document"><option value="home">Homepage</option><option value="shared">Navigation, contact &amp; footer</option></select></label><button type="button" id="desktop">Desktop</button><button type="button" id="mobile">Mobile</button><button type="button" id="refresh">Preview</button><button type="button" id="save">Save draft</button><button type="button" id="publish">Publish</button><span id="save-status" role="status">Loading…</span></div><div id="editor-notice" role="alert"></div><div class="editor-grid"><aside class="editor-fields"><h2>Sections</h2><div id="section-list"></div><div class="editor-move"><button type="button" id="up">↑ Move up</button><button type="button" id="down">↓ Move down</button><button type="button" id="remove">Remove</button></div><label>Add approved section<select id="layout"><option value="">Choose a layout…</option></select></label><button type="button" id="add">Add section</button><hr><details id="page-seo"><summary>Search &amp; sharing settings</summary><label>SEO title<input id="seo-title" maxlength="250"></label><label>Meta description<textarea id="seo-description" maxlength="500"></textarea></label><label>Social picture<select id="seo-image"></select></label><label>Social picture description<input id="seo-image-alt" maxlength="500"></label><p class="help">Keep titles/descriptions unique. The homepage title starts ED Fund Uganda Foundation | Education.</p></details><hr><h2 id="field-title">Section fields</h2><label>Part of this section<select id="field-group"></select></label><div id="fields"></div><hr><h2>Revision history</h2><p class="help">Restoring creates a draft. Publish it when ready.</p><label>Revision<select id="revision"></select></label><button type="button" id="restore">Restore as draft</button><button type="button" id="reload">Reload saved content</button></aside><div class="preview-area"><p class="preview-caption">Actual website preview · click a section to edit it · unpublished changes stay private</p><iframe id="page-preview" title="Private website preview"></iframe></div></div><dialog id="media-picker"><div class="media-heading"><h2>Choose an existing picture</h2><button type="button" id="close-media">Close</button></div><label>Find picture<input id="media-search" type="search"></label><form id="page-upload"><label>Upload a new picture<input type="file" id="page-upload-file" accept="image/jpeg,image/png,image/webp" required></label><button>Upload and select</button><p id="page-upload-status" role="status"></p></form><div id="media-grid"></div></dialog></div><script src="/assets/js/editor.js?v='.substr(hash_file('sha256',dirname(__DIR__).'/public/assets/js/editor.js'),0,12).'" defer></script>';
    }
    public static function handle(array $config,string $path,string $method,int $owner): void
    {
        $c=new Content(Database::connect($config['db']),new Crypto($config['key_bytes']));
        if($path==='/admin/preview'){
            if($method!=='GET'){http_response_code(405);return;}
            $token=$_GET['token'] ?? '';if(!is_string($token) || !preg_match('/\A[a-f0-9]{32}\z/',$token) || !isset($_SESSION['previews'][$token]) || time()-$_SESSION['previews'][$token]['time']>600){http_response_code(404);echo 'Private preview expired. Refresh it in the editor.';return;}
            header('X-Frame-Options: SAMEORIGIN');header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; frame-src https://www.youtube-nocookie.com; form-action 'none'; frame-ancestors 'self'; base-uri 'none'");
            echo $_SESSION['previews'][$token]['html'];return;
        }
        header('Content-Type: application/json; charset=utf-8');
        try {
            $slug=$_GET['document'] ?? 'home';if(!is_string($slug) || !in_array($slug,array_column(Database::connect($config['db'])->query('SELECT slug FROM cms_documents')->fetchAll(),'slug'),true)){throw new \InvalidArgumentException('Unknown document.');}
            if($method==='GET'){echo json_encode(['state'=>$c->state($slug),'documents'=>Database::connect($config['db'])->query('SELECT slug,label FROM cms_documents ORDER BY slug')->fetchAll(),'templates'=>$c->templates(),'media'=>(new Media(Database::connect($config['db']),$config))->all(true)],JSON_THROW_ON_ERROR);return;}
            $raw=$_POST['data'] ?? '';if(!is_string($raw) || strlen($raw)>400000){throw new \InvalidArgumentException('Page data is too large.');}
            $request=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($request)){throw new \InvalidArgumentException('Invalid request.');}
            $action=$request['action'] ?? '';if(!is_string($action) || (isset($request['content']) && !is_array($request['content']))){throw new \InvalidArgumentException('Invalid action or page data.');}
            if($action==='preview'){
                $data=$c->validate($request['content'] ?? [],$slug);$page=(new Pages(Database::connect($config['db'])))->bySlug($slug);$html=$slug==='shared'?$c->page(null,$data,true):($page?(new Pages(Database::connect($config['db'])))->render($slug,$data,true):$c->page($data,null,true));if($page){$seo=new Seo(Database::connect($config['db']),$config);$html=$seo->render($html,$page['route'],$seo->pageData($slug,$data),null,null,true);}
                $html=str_replace('</head>','<link rel="stylesheet" href="/assets/css/preview.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/public/assets/css/preview.css'),0,12).'"><script src="/assets/js/preview.js?v='.substr(hash_file('sha256',dirname(__DIR__).'/public/assets/js/preview.js'),0,12).'" defer></script></head>',$html);
                $token=bin2hex(random_bytes(16));$_SESSION['previews'] ??= [];
                foreach($_SESSION['previews'] as $key=>$p){if(time()-$p['time']>600){unset($_SESSION['previews'][$key]);}}
                while(count($_SESSION['previews'])>=5){array_shift($_SESSION['previews']);}$_SESSION['previews'][$token]=['time'=>time(),'html'=>$html];echo json_encode(['url'=>'/admin/preview?token='.$token]);return;
            }
            if(!is_int($request['version'] ?? null) || (isset($request['revision']) && !is_int($request['revision']))){throw new \InvalidArgumentException('Invalid revision version.');}
            echo json_encode(['state'=>$c->change($slug,$request['version'],$action,$request['content'] ?? null,$request['revision'] ?? null,$owner)],JSON_THROW_ON_ERROR);
        }catch(ContentConflict $e){http_response_code(409);echo json_encode(['error'=>$e->getMessage()]);}
        catch(\InvalidArgumentException|\JsonException $e){http_response_code(422);echo json_encode(['error'=>$e instanceof \JsonException?'Invalid page data.':$e->getMessage()]);}
    }
}
