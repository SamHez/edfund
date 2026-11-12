<?php
declare(strict_types=1);
namespace EdFund;

final class Editor
{
    private static function e(string $v): string {return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private static function asset(string $file): string {return '/assets/'.$file.'?v='.substr(hash_file('sha256',dirname(__DIR__).'/public/assets/'.$file),0,12);}
    private static function icon(string $paths): string
    {
        return '<svg class="pe-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    }

    /** Page list: the starting point for every page edit. */
    public static function index(array $config): string
    {
        $db=Database::connect($config['db']);
        $rows=$db->query("SELECT d.slug,d.label,d.draft_id,d.published_id,p.route FROM cms_documents d LEFT JOIN cms_pages p ON p.document_slug=d.slug WHERE d.slug<>'shared' ORDER BY d.slug='home' DESC,d.label")->fetchAll();
        $items='';
        foreach($rows as $r){
            $draft=(int)$r['draft_id']!==(int)$r['published_id'];$route=(string)($r['route'] ?? '/');
            $items.='<li class="pe-page-card" data-search="'.self::e(strtolower($r['label'].' '.$route)).'"><div class="pe-page-top"><span class="pe-page-icon">'.self::icon('<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>').'</span><h3>'.self::e($r['label']).'</h3></div>'
                .'<span class="pe-badge '.($draft?'is-draft':'is-published').'">'.($draft?'Unpublished changes':'Published').'</span>'
                .'<div class="pe-page-actions"><a class="button" href="/admin/pages?document='.self::e(rawurlencode($r['slug'])).'">Edit page</a><a class="pe-text-link" href="'.self::e($route).'">View live</a></div></li>';
        }
        return '<link rel="stylesheet" href="'.self::e(self::asset('css/page-editor.css')).'"><section class="pe-pages" aria-label="Pages"><div class="list-toolbar"><label class="list-search"><span class="pe-sr-only">Search pages</span><input type="search" id="pe-page-search" placeholder="Search pages…" autocomplete="off"></label></div><ul class="pe-page-grid" id="pe-page-grid">'.$items.'</ul><p class="pe-empty" id="pe-page-empty" hidden>No pages match your search.</p></section>'
            .'<script src="'.self::e(self::asset('js/pages-list.js')).'" defer></script>';
    }

    /** Three-area editing workspace; every dynamic part is drawn by editor.js from validated JSON. */
    public static function screen(string $slug,string $label): string
    {
        $shared=$slug==='shared';
        $back=$shared?['/admin/','Back to Dashboard']:['/admin/pages','Back to Pages'];
        $arrow=self::icon('<path d="m15 18-6-6 6-6"/>');
        $up=self::icon('<path d="m6 15 6-6 6 6"/>');
        return '<link rel="stylesheet" href="'.self::e(self::asset('css/page-editor.css')).'">'
        .'<div id="editor" class="pe" data-slug="'.self::e($slug).'" aria-busy="true">'
        .'<header class="pe-toolbar" role="toolbar" aria-label="Page editor actions">'
            .'<a class="pe-back" href="'.self::e($back[0]).'">'.$arrow.'<span>'.self::e($back[1]).'</span></a>'
            .'<div class="pe-title"><h2 id="pe-page-name">'.self::e($label).'</h2><span id="pe-publication" class="pe-badge" role="status">Loading…</span></div>'
            .'<div class="pe-segment" role="group" aria-label="Preview width"><button type="button" id="pe-desktop" aria-pressed="true">Desktop</button><button type="button" id="pe-mobile" aria-pressed="false">Mobile</button></div>'
            .'<div class="pe-actions">'
                .'<span id="pe-status" class="pe-savestate" role="status" aria-live="polite" aria-atomic="true">Loading…</span>'
                .'<button type="button" class="pe-btn" id="pe-save" disabled>Save Draft</button>'
                .'<button type="button" class="pe-btn" id="pe-preview-tab">Preview</button>'
                .'<button type="button" class="pe-btn primary" id="pe-publish" disabled>Publish</button>'
                .'<details class="pe-more" id="pe-more"><summary aria-label="More options">'.self::icon('<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>').'<span class="pe-more-label">More</span></summary>'
                    .'<div class="pe-menu"><button type="button" id="pe-open-history">Revision History</button><button type="button" id="pe-reload">Reload Saved Version</button><a id="pe-view-live" href="/" target="_blank" rel="noopener">View Live Page</a></div></details>'
            .'</div>'
        .'</header>'
        .'<div id="editor-notice" class="pe-alert" role="alert"></div>'
        .'<div class="pe-tabs" role="tablist" aria-label="Editor area"><button type="button" role="tab" data-view="sections">Sections</button><button type="button" role="tab" data-view="preview" aria-selected="true">Preview</button><button type="button" role="tab" data-view="settings">Settings</button></div>'
        .'<div class="pe-body" data-view="preview">'
            .'<aside class="pe-sections" id="pe-sections" aria-labelledby="pe-sections-title"><h3 id="pe-sections-title">Page Sections</h3><ol id="pe-section-list" class="pe-list"></ol>'
                .'<button type="button" class="pe-add" id="pe-add-section">'.self::icon('<path d="M12 5v14M5 12h14"/>').'Add Section</button>'
                .'<div class="pe-extra"><h3>Page Settings</h3><ul class="pe-list"><li class="pe-row" id="pe-seo-row"><button type="button" class="pe-row-main" id="pe-seo-open"><span class="pe-row-name">Search &amp; Sharing</span><span class="pe-row-type">Title, description, share picture</span></button></li></ul></div>'
                .'<p class="pe-sr-only" id="pe-announce" role="status" aria-live="polite"></p></aside>'
            .'<section class="pe-preview" aria-label="Website preview"><div class="pe-stage" id="pe-stage"><div class="pe-device" id="pe-device"><iframe id="pe-frame" title="Website preview. Click a section to edit it." tabindex="0"></iframe></div></div><p class="pe-hint" id="pe-hint">Click any section to edit it. Changes show here instantly and stay private until you publish.</p></section>'
            .'<aside class="pe-settings" id="pe-settings" aria-label="Section settings"><button type="button" class="pe-mobile-back" id="pe-close-settings">'.$arrow.'Back to preview</button><div id="pe-settings-body"></div></aside>'
        .'</div>'
        .'<dialog class="pe-dialog" id="pe-add-dialog" aria-labelledby="pe-add-title"><div class="pe-dialog-head"><h2 id="pe-add-title">Add Section</h2><button type="button" class="pe-x" data-close aria-label="Close">×</button></div><p class="pe-help">The new section is added below the one you have selected.</p><div class="pe-choices" id="pe-add-choices"></div></dialog>'
        .'<dialog class="pe-dialog pe-wide" id="pe-history-dialog" aria-labelledby="pe-history-title"><div class="pe-dialog-head"><h2 id="pe-history-title">Revision History</h2><button type="button" class="pe-x" data-close aria-label="Close">×</button></div><p class="pe-help">Restoring an earlier version creates a new draft. Nothing changes on the website until you publish it.</p><ol class="pe-history" id="pe-history-list"></ol></dialog>'
        .'<dialog class="pe-dialog pe-narrow" id="pe-confirm" aria-labelledby="pe-confirm-title"><h2 id="pe-confirm-title"></h2><p id="pe-confirm-text"></p><div class="pe-dialog-actions"><button type="button" class="pe-btn" id="pe-confirm-cancel">Cancel</button><button type="button" class="pe-btn primary" id="pe-confirm-ok">Confirm</button></div></dialog>'
        .'<dialog class="pe-dialog pe-wide" id="pe-media" aria-labelledby="pe-media-title"><div class="pe-dialog-head"><h2 id="pe-media-title">Change Image</h2><button type="button" class="pe-x" data-close aria-label="Close">×</button></div>'
            .'<label class="pe-field">Search pictures<input id="pe-media-search" type="search" placeholder="Type a file name"></label>'
            .'<form id="pe-upload" class="pe-upload"><label class="pe-field">Upload a new picture<input type="file" id="pe-upload-file" accept="image/jpeg,image/png,image/webp" required></label><button class="pe-btn">Upload and Use</button><p id="pe-upload-status" role="status"></p></form>'
            .'<div class="pe-media-grid" id="pe-media-grid"></div></dialog>'
        .'<div class="pe-toasts" id="pe-toasts" role="status" aria-live="polite"></div>'
        .'</div><script src="'.self::e(self::asset('js/editor.js')).'" defer></script>';
    }

    private static function previewAssets(): string
    {
        return '<link rel="stylesheet" href="'.self::asset('css/preview.css').'"><script src="'.self::asset('js/preview.js').'" defer></script>';
    }

    public static function handle(array $config,string $path,string $method,int $owner): void
    {
        $db=Database::connect($config['db']);$c=new Content($db,new Crypto($config['key_bytes']));
        if($path==='/admin/preview'){
            if($method!=='GET'){http_response_code(405);return;}
            $token=$_GET['token'] ?? '';
            if(!is_string($token) || !preg_match('/\A[a-f0-9]{32}\z/',$token) || !isset($_SESSION['previews'][$token]) || time()-$_SESSION['previews'][$token]['time']>600){http_response_code(404);echo 'Private preview expired. Refresh it in the editor.';return;}
            header('X-Frame-Options: SAMEORIGIN');
            header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; frame-src https://www.youtube-nocookie.com; form-action 'none'; frame-ancestors 'self'; base-uri 'none'");
            header('Cache-Control: private, no-store');
            echo $_SESSION['previews'][$token]['html'];return;
        }
        header('Content-Type: application/json; charset=utf-8');
        try {
            $slug=$_GET['document'] ?? 'home';
            if(!is_string($slug) || !in_array($slug,array_column($db->query('SELECT slug FROM cms_documents')->fetchAll(),'slug'),true)){throw new \InvalidArgumentException('Unknown document.');}
            if($method==='GET'){
                $pages=new Pages($db);$page=$pages->bySlug($slug);$label=$db->prepare('SELECT label FROM cms_documents WHERE slug=?');$label->execute([$slug]);
                echo json_encode([
                    'state'=>$c->state($slug),
                    'page'=>['slug'=>$slug,'label'=>$slug==='shared'?'Website Settings':(string)$label->fetchColumn(),'route'=>$page['route'] ?? null],
                    'templates'=>$c->templates(),
                    'media'=>(new Media($db,$config))->all(true),
                ],JSON_THROW_ON_ERROR);return;
            }
            $raw=$_POST['data'] ?? '';if(!is_string($raw) || strlen($raw)>400000){throw new \InvalidArgumentException('Page data is too large.');}
            $request=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($request)){throw new \InvalidArgumentException('Invalid request.');}
            $action=$request['action'] ?? '';
            if(!is_string($action) || (isset($request['content']) && !is_array($request['content']))){throw new \InvalidArgumentException('Invalid action or page data.');}
            if($action==='section'){
                // Renders one section for in-place preview updates; invalid data is reported, never rendered.
                $data=$c->validate($request['content'] ?? [],$slug);$id=$request['section'] ?? '';
                if(!is_string($id) || !preg_match('/\A[a-f0-9]{24}\z/',$id)){throw new \InvalidArgumentException('Invalid section.');}
                foreach($data['sections'] as $section){
                    if($section['id']!==$id){continue;}
                    $html=$c->section($section,true);
                    // Story cards come from published posts and header/footer markup is stateful: reload those instead.
                    $reload=str_contains($html,'story-card') || $slug==='shared';
                    echo json_encode($reload?['reload'=>true]:['html'=>$html],JSON_THROW_ON_ERROR);return;
                }
                throw new \InvalidArgumentException('Section not found.');
            }
            if($action==='preview'){
                $data=$c->validate($request['content'] ?? [],$slug);$pages=new Pages($db);$page=$pages->bySlug($slug);
                $html=$slug==='shared'?$c->page(null,$data,true):($page?$pages->render($slug,$data,true):$c->page($data,null,true));
                if($page){$seo=new Seo($db,$config);$html=$seo->render($html,$page['route'],$seo->pageData($slug,$data),null,null,true);}
                // A clean preview opens in its own tab without the editor's selection tools.
                if(empty($request['clean'])){$html=str_replace('</head>',self::previewAssets().'</head>',$html);}
                $token=bin2hex(random_bytes(16));$_SESSION['previews'] ??= [];
                foreach($_SESSION['previews'] as $key=>$p){if(time()-$p['time']>600){unset($_SESSION['previews'][$key]);}}
                while(count($_SESSION['previews'])>=5){array_shift($_SESSION['previews']);}
                $_SESSION['previews'][$token]=['time'=>time(),'html'=>$html];echo json_encode(['url'=>'/admin/preview?token='.$token]);return;
            }
            if(!is_int($request['version'] ?? null) || (isset($request['revision']) && !is_int($request['revision']))){throw new \InvalidArgumentException('Invalid revision version.');}
            echo json_encode(['state'=>$c->change($slug,$request['version'],$action,$request['content'] ?? null,$request['revision'] ?? null,$owner)],JSON_THROW_ON_ERROR);
        }catch(ContentConflict $e){http_response_code(409);echo json_encode(['error'=>$e->getMessage()]);}
        catch(\InvalidArgumentException|\JsonException $e){http_response_code(422);echo json_encode(['error'=>$e instanceof \JsonException?'Invalid page data.':$e->getMessage()]);}
    }
}
