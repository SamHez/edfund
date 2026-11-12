<?php
declare(strict_types=1);
namespace EdFund;

/** Shared presentation only; authorization and all content operations remain in Web. */
final class AdminLayout
{
    public static function icon(string $name): string
    {
        $paths=match($name){
            'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'pages'=>'<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
            'stories'=>'<path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h4"/>',
            'media'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 5-5 4 4 4-6 5 7"/>',
            'settings'=>'<path d="M4 7h16M4 17h16M8 4v6M16 14v6"/>',
            'external'=>'<path d="M14 3h7v7M21 3 10 14M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/>',
            'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
            'collapse'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 3v18m7-12-3 3 3 3"/>',
            'chevron'=>'<path d="m8 10 4 4 4-4"/>',
            'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
            'plus'=>'<path d="M12 5v14M5 12h14"/>',
            'status'=>'<path d="M3 12h4l3-8 4 16 3-8h4"/>',
            'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'logout'=>'<path d="M9 3H4v18h5m0-9h12m-4-4 4 4-4 4"/>',
            default=>'<path d="M5 12h14m-5-5 5 5-5 5"/>'
        };
        return '<svg class="admin-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    }
    private static function e(string $v): string {return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    /** Shared owner shell (white top bar + compact sidebar) used by the admin and the public site. */
    public static function shell(array $o): string
    {
        $e=fn(string $v)=>self::e($v);
        $token=$o['token'];$active=$o['active'] ?? '';
        $menu='';foreach($o['pages'] as $p){$menu.='<a href="/admin/pages?document='.$e(rawurlencode($p['slug'])).'">'.$e($p['label']).'</a>';}
        $contextual=$o['edit'] ?? null;
        $center='<div class="sh-center" aria-label="Quick actions">'
            .'<details class="sh-menu-wrap"><summary class="sh-pill sh-edit">'.self::icon('pages').'<span>Edit page</span>'.self::icon('chevron').'</summary><div class="sh-menu">'.$menu.'</div></details>'
            .'<form action="/admin/new-story" method="post" class="sh-form">'.$token.'<button type="submit" class="sh-pill sh-new">'.self::icon('plus').'<span>New post</span></button></form>'
            .'<a class="sh-pill sh-visit" href="/" aria-label="Visit website">'.self::icon('external').'<span>Visit website</span></a></div>';
        $mobile='<div class="sh-account-mobile"><a href="/admin/pages">'.self::icon('pages').'Edit page</a><form action="/admin/new-story" method="post">'.$token.'<button type="submit">'.self::icon('plus').'New post</button></form><a href="/">'.self::icon('external').'Visit website</a></div>';
        $title=isset($o['heading'])?'<p id="admin-page-title" class="sh-title">'.$e($o['heading']).'</p>':'';
        $top='<header class="sh-top" id="edfund-admin-toolbar" data-public-route="'.$e($o['route'] ?? '').'"><button type="button" id="sidebar-toggle" class="sh-icon-btn sh-burger" aria-label="Open navigation" aria-controls="admin-sidebar" aria-expanded="false">'.self::icon('menu').'</button>'
            .'<a class="sh-logo" href="/admin/"><img src="/assets/images/logo-web.png" alt="EdFund Foundation" width="500" height="282"></a>'.$title.$center
            .'<div class="sh-right">'.($contextual?'<a id="toolbar-edit" class="sh-pill sh-edit-here" href="'.$e($contextual['url']).'">'.self::icon('pages').'<span>'.$e($contextual['label']).'</span></a>':'').($o['context'] ?? '').'<details class="sh-account"><summary aria-label="Account menu"><span class="sh-avatar">'.self::icon('user').'</span><span class="sh-name">'.$e($o['user']).'</span>'.self::icon('chevron').'</summary><div class="sh-dropdown"><p>Signed in as<strong>'.$e($o['user']).'</strong></p>'.$mobile.'<a href="/admin/health">'.self::icon('status').'System status</a><form action="/admin/logout" method="post">'.$token.'<button type="submit">'.self::icon('logout').'Sign out</button></form></div></details></div></header>';
        $items=[['dashboard','Dashboard','/admin/'],['pages','Pages','/admin/pages'],['stories','Stories & Updates','/admin/posts'],['media','Media Library','/admin/media'],['settings','Settings','/admin/pages?document=shared']];
        $side='<aside id="admin-sidebar" class="sh-side" aria-label="Website administration"><button type="button" id="sidebar-close" class="sh-icon-btn sh-close" aria-label="Close navigation">×</button><nav aria-label="Admin">';
        foreach($items as [$key,$label,$url]){$side.='<a data-admin-nav="'.$key.'" class="sh-link" href="'.$url.'"'.($active===$key?' aria-current="page"':'').' aria-label="'.$label.'">'.self::icon($key).'<span>'.$label.'</span></a>';}
        $side.='</nav><div class="sh-side-bottom"><a class="sh-link" data-admin-nav="website" href="/" aria-label="Visit website">'.self::icon('external').'<span>Visit website</span></a></div></aside><button type="button" id="sidebar-backdrop" class="sh-backdrop" aria-label="Close navigation" hidden></button>';
        return $top.$side;
    }
    public static function render(string $title,string $content,?array $user,string $token,string $error,?array $context=null,array $pages=[]): void
    {
        $path=parse_url($_SERVER['REQUEST_URI'] ?? '/admin/',PHP_URL_PATH);
        $shared=$path==='/admin/pages' && ($_GET['document'] ?? '')==='shared';
        $heading=match($path){'/admin/'=>'Dashboard','/admin/posts'=>'Stories & Updates','/admin/media'=>'Media Library','/admin/pages'=>$shared?'Settings':'Pages',default=>$title};
        $hash=fn(string $file)=>substr(hash_file('sha256',dirname(__DIR__).'/public/assets/'.$file),0,12);
        $contextLink=$context?'<a id="editor-return" class="sh-context" data-context="'.$context['kind'].'" data-page-routes="'.self::e(json_encode($context['routes'],JSON_THROW_ON_ERROR)).'" data-public-route="'.self::e($context['url']).'" href="'.self::e($context['url']).'">'.self::icon('external').'<span>'.self::e($context['label']).'</span></a>':'';
        $brand='<a class="brand" href="'.($user?'/admin/':'/').'"><img src="/assets/images/logo-web.png" alt="EdFund Foundation" width="500" height="282"></a>';
        $shell=$user?'<link rel="stylesheet" href="/assets/css/admin-shell.css?v='.$hash('css/admin-shell.css').'"><script src="/assets/js/admin-shell.js?v='.$hash('js/admin-shell.js').'" defer></script>':'';
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="icon" href="/assets/images/ed-fav.png" type="image/png"><title>'.self::e($heading).' | EdFund administration</title><link rel="stylesheet" href="/assets/css/admin.css?v='.$hash('css/admin.css').'">'.$shell.'<script src="/assets/js/admin.js?v='.$hash('js/admin.js').'" defer></script></head><body class="'.($user?'admin-app':'auth-app').($user && $path==='/admin/pages' && isset($_GET['document'])?' has-page-editor':'').'"><a class="skip-link" href="#main">Skip to content</a>';
        if($user){
            $active=match(true){$path==='/admin/'=>'dashboard',$path==='/admin/pages'&&!$shared=>'pages',in_array($path,['/admin/posts','/admin/post'],true)=>'stories',$path==='/admin/media'=>'media',$shared=>'settings',default=>''};
            echo self::shell(['token'=>$token,'pages'=>$pages,'user'=>$user['name'],'heading'=>$heading,'active'=>$active,'context'=>$contextLink]);
            echo '<div class="admin-workspace"><main id="main" tabindex="-1" class="admin-content'.(in_array($path,['/admin/pages','/admin/post','/admin/media'],true)?' editor-main':'').'">';
        }else{
            echo '<main id="main" tabindex="-1" class="auth-card"><a class="auth-logo" href="/" target="_blank" rel="noopener" aria-label="EdFund Foundation website (opens in a new tab)"><img src="/assets/images/logo-web.png" alt="EdFund Foundation" width="500" height="282"></a><p class="eyebrow">EdFund administration</p><h1>'.self::e($title).'</h1>';
        }
        if($error!==''){echo '<p class="alert" role="alert">'.self::e($error).'</p>';}
        echo $content.($context?'<script src="/assets/js/editing-context.js?v='.$hash('js/editing-context.js').'" defer></script>':'').'</main>'.($user?'<footer class="admin-footer">EdFund Foundation <span>Website manager</span></footer></div>':'').'</body></html>';
    }
    public static function dashboard(string $name,?array $stats=null): string
    {
        $cards='';
        foreach([['pages','Edit your pages','Update text and pictures with your website preview beside you.','/admin/pages','Open page editor','teal'],['stories','Publish a story','Write an update, save a draft and publish when you’re ready.','/admin/posts','Manage stories','blue'],['settings','Website settings','Navigation, contact details and footer shown on every page.','/admin/pages?document=shared','Open settings','amber']] as [$icon,$title,$text,$url,$label,$tone]){
            $cards.='<a class="action-card tone-'.$tone.'" href="'.$url.'"><span class="action-head"><span class="action-icon">'.self::icon($icon).'</span><h3>'.$title.'</h3></span><p>'.$text.'</p><span class="action-link">'.$label.' <span aria-hidden="true">→</span></span></a>';
        }
        $num=fn(int $n)=>number_format($n);
        if($stats===null){
            $body='<p class="analytics-note">Visit counting starts after the latest database update is applied (<code>php bin/cms.php migrate</code>).</p>';
        }else{
            $top=$stats['top'][0]['path'] ?? '—';
            $tiles='';
            foreach([['Page views',$num($stats['views'])],['Visitors',$num($stats['visitors'])],['Top page',self::e($top)],['Stories read',$num($stats['stories'])]] as [$label,$value]){
                $tiles.='<div class="stat-tile"><span class="stat-label">'.$label.'</span><strong class="stat-value'.($label==='Top page'?' is-text':'').'">'.$value.'</strong></div>';
            }
            $max=max(1,...array_column($stats['series'],'views'));$bars='';
            foreach($stats['series'] as $p){$bars.='<span class="bar" style="height:'.max(4,(int)round($p['views']/$max*100)).'%" title="'.self::e(gmdate('j M',strtotime($p['day']))).': '.$p['views'].' views"></span>';}
            $list='';foreach($stats['top'] as $t){$list.='<li><span>'.self::e($t['path']).'</span><strong>'.$num((int)$t['v']).'</strong></li>';}
            $body='<div class="stat-grid">'.$tiles.'</div><div class="analytics-chart" role="img" aria-label="Page views per day for the last 14 days"><div class="chart-bars" aria-hidden="true">'.$bars.'</div><p>Last 14 days</p></div>'.($list?'<ol class="top-pages">'.$list.'</ol>':'<p class="analytics-note">No visits recorded yet. Counts appear as visitors browse the website.</p>');
        }
        return '<section class="action-grid" aria-label="Quick actions">'.$cards.'</section>'
            .'<div class="dashboard-split"><section class="analytics" aria-labelledby="analytics-title"><div class="analytics-head"><div><h2 id="analytics-title">Site visits &amp; analytics</h2><p>Last '.($stats['days'] ?? 30).' days · built in, no cookies, no third parties.</p></div></div>'.$body.'</section>'
            .'<section class="dashboard-side" aria-label="Library and status"><article class="side-card side-media"><span class="dashboard-icon">'.self::icon('media').'</span><h3>Manage your pictures</h3><p>Upload images, add descriptions and see where they’re used.</p><a href="/admin/media">Open media library <span aria-hidden="true">→</span></a></article>'
            .'<article class="side-card"><h3>Need a hand?</h3><p>Check that everything is running smoothly from the profile menu, under <strong>System status</strong>.</p><a href="/admin/health">View system status <span aria-hidden="true">→</span></a></article></section></div>';
    }
}
