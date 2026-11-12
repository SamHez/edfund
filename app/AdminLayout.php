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
            'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'logout'=>'<path d="M9 3H4v18h5m0-9h12m-4-4 4 4-4 4"/>',
            default=>'<path d="M5 12h14m-5-5 5 5-5 5"/>'
        };
        return '<svg class="admin-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    }
    private static function e(string $v): string {return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function render(string $title,string $content,?array $user,string $token,string $error): void
    {
        $path=parse_url($_SERVER['REQUEST_URI'] ?? '/admin/',PHP_URL_PATH);
        $shared=$path==='/admin/pages' && ($_GET['document'] ?? '')==='shared';
        $heading=match($path){'/admin/'=>'Dashboard','/admin/posts'=>'Stories & Updates','/admin/media'=>'Media Library','/admin/pages'=>$shared?'Settings':'Pages',default=>$title};
        $hash=fn(string $file)=>substr(hash_file('sha256',dirname(__DIR__).'/public/assets/'.$file),0,12);
        $brand='<a class="brand" href="'.($user?'/admin/':'/').'"><img src="/assets/images/logo-web.png" alt="EdFund Foundation" width="500" height="282"></a>';
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.self::e($heading).' | EdFund administration</title><link rel="stylesheet" href="/assets/css/admin.css?v='.$hash('css/admin.css').'"><script src="/assets/js/admin.js?v='.$hash('js/admin.js').'" defer></script></head><body class="'.($user?'admin-app':'auth-app').'"><a class="skip-link" href="#main">Skip to content</a>';
        if($user){
            $items=[['dashboard','Dashboard','/admin/'],['pages','Pages','/admin/pages'],['stories','Stories & Updates','/admin/posts'],['media','Media Library','/admin/media'],['settings','Settings','/admin/pages?document=shared']];
            echo '<aside id="admin-sidebar" class="admin-sidebar" aria-label="Website administration"><button type="button" id="sidebar-close" class="icon-button" aria-label="Close navigation">×</button><div class="sidebar-brand">'.$brand.'<span class="sidebar-brand-caption">Website manager</span></div><nav aria-label="Admin">';
            foreach($items as [$key,$label,$url]){
                $active=match($key){'dashboard'=>$path==='/admin/','pages'=>$path==='/admin/pages'&&!$shared,'stories'=>in_array($path,['/admin/posts','/admin/post'],true),'media'=>$path==='/admin/media',default=>$shared};
                echo '<a data-admin-nav="'.$key.'" class="sidebar-link" href="'.$url.'"'.($active?' aria-current="page"':'').' aria-label="'.$label.'" title="'.$label.'">'.self::icon($key).'<span>'.$label.'</span></a>';
            }
            echo '</nav><div class="sidebar-bottom"><span class="sidebar-note">Education. Technology. Community.</span><button type="button" id="sidebar-collapse" class="sidebar-collapse" aria-expanded="true" aria-controls="admin-sidebar" aria-label="Collapse sidebar">'.self::icon('collapse').'<span>Collapse sidebar</span></button></div></aside><button type="button" id="sidebar-backdrop" class="sidebar-backdrop" aria-label="Close navigation" hidden></button><div class="admin-workspace"><header class="admin-topbar"><button type="button" id="sidebar-toggle" class="icon-button" aria-label="Open navigation" aria-controls="admin-sidebar" aria-expanded="false">'.self::icon('menu').'</button><h1 id="admin-page-title">'.self::e($heading).'</h1><div class="topbar-actions"><a class="visit-website" aria-label="View website (opens in a new tab)" href="/" target="_blank" rel="noopener">'.self::icon('external').'<span>View website</span></a><details class="account-menu"><summary aria-label="Account menu"><span class="account-avatar">'.self::icon('user').'</span><span class="account-name">'.self::e($user['name']).'</span>'.self::icon('chevron').'</summary><div class="account-dropdown"><p>Signed in as<strong>'.self::e($user['name']).'</strong></p><a href="/admin/security">'.self::icon('lock').'Account security</a><form action="/admin/logout" method="post">'.$token.'<button type="submit">'.self::icon('logout').'Sign out</button></form></div></details></div></header><main id="main" tabindex="-1" class="admin-content'.(in_array($path,['/admin/pages','/admin/post','/admin/media'],true)?' editor-main':'').'">';
        }else{
            echo '<header class="auth-header">'.$brand.'<a href="/">← Website</a></header><main id="main" tabindex="-1" class="auth-card"><p class="eyebrow">EdFund administration</p><h1>'.self::e($title).'</h1>';
        }
        if($error!==''){echo '<p class="alert" role="alert">'.self::e($error).'</p>';}
        echo $content.'</main>'.($user?'<footer class="admin-footer">EdFund Foundation <span>Website manager</span></footer></div>':'').'</body></html>';
    }
    public static function dashboard(string $name): string
    {
        $cards='';
        foreach([['pages','Edit your pages','Update text and pictures with your website preview beside you.','/admin/pages','Open page editor'],['stories','Share a story','Write an update, save a draft and publish when you’re ready.','/admin/posts','Manage stories'],['media','Manage your pictures','Upload images, add descriptions and see where they’re used.','/admin/media','Open media library']] as [$icon,$title,$text,$url,$label]){
            $cards.='<article class="dashboard-card"><span class="dashboard-icon">'.self::icon($icon).'</span><h3>'.$title.'</h3><p>'.$text.'</p><a href="'.$url.'">'.$label.' <span aria-hidden="true">→</span></a></article>';
        }
        return '<section class="dashboard-welcome"><div><p class="eyebrow">Your website, made simple</p><h2>Welcome back, '.self::e($name).'.</h2><p>Keep EdFund’s stories, pages and pictures up to date.</p><a class="button" href="/admin/pages?document=home">Edit homepage '.self::icon('arrow').'</a></div><div class="welcome-mark" aria-hidden="true">'.self::icon('pages').'</div></section><section aria-labelledby="dashboard-tasks"><div class="section-heading"><h2 id="dashboard-tasks">What would you like to do?</h2><p>Everything you need to manage your website.</p></div><div class="dashboard-grid">'.$cards.'</div></section><section class="dashboard-guidance"><div><span class="dashboard-icon">'.self::icon('settings').'</span><h2>A few helpful starting points</h2><p>Save drafts as you work. Changes only appear on the website after you publish.</p></div><div class="guidance-links"><a href="/admin/pages?document=shared"><span><strong>Website settings</strong><small>Navigation, contact details and footer</small></span>'.self::icon('arrow').'</a><a href="/admin/health"><span><strong>System status</strong><small>Technical checks for your site maintainer</small></span>'.self::icon('arrow').'</a></div></section>';
    }
}
