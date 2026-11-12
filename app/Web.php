<?php
declare(strict_types=1);
namespace EdFund;
final class Web
{
    private readonly Auth $auth;
    private string $source;
    public function __construct(private readonly array $config)
    {
        $this->source=$_SERVER['REMOTE_ADDR'] ?? '';
        $host=parse_url($config['base_url'],PHP_URL_HOST);$port=parse_url($config['base_url'],PHP_URL_PORT);
        $expected=strtolower($host.($port ? ':'.$port : ''));
        if (strtolower($_SERVER['HTTP_HOST'] ?? '')!==$expected) {$this->abort(400,'Invalid request host.');}
        if ($config['environment']==='local' && !in_array($this->source,['127.0.0.1','::1'],true)) {$this->abort(403,'Local access only.');}
        $https=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
        if ($config['environment']==='production' && !$https) {$this->abort(400,'HTTPS is required.');}
        ini_set('display_errors','0');ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');
        session_save_path($config['storage_path'].'/sessions');session_name('edfund_owner');session_cache_limiter('');ini_set('session.gc_maxlifetime','28800');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/admin','secure'=>$https,'httponly'=>true,'samesite'=>'Strict']);
        if (!session_start()) {throw new \RuntimeException('Session unavailable.');}
        $now=time();
        if (isset($_SESSION['started']) && ($now-$_SESSION['started']>28800 || $now-($_SESSION['seen'] ?? 0)>1800)) {$this->clear();}
        $_SESSION['started'] ??= $now;$_SESSION['seen']=$now;$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $this->auth=new Auth(Database::connect($config['db']),new Crypto($config['key_bytes']));
        if (random_int(1,50)===1) {$this->auth->prune();}
    }
    public static function headers(): void
    {
        header('Cache-Control: no-store, private, max-age=0');header('Pragma: no-cache');header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self'; connect-src 'self'; font-src 'self'; frame-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        header('X-Frame-Options: DENY');header('Content-Type: text/html; charset=utf-8');
    }
    private function clear(): void {$_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['started']=time();$_SESSION['seen']=time();}
    private function redirect(string $path): never {header('Location: '.$path,true,303);exit;}
    private function abort(int $status,string $message): never {http_response_code($status);echo htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');exit;}
    private function rotate(): void {session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));}
    private function csrf(): void
    {
        if (($_SERVER['HTTP_ORIGIN'] ?? $this->config['base_url'])!==$this->config['base_url'] || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) {$this->abort(403,'This form expired. Reload the page and try again.');}
    }
    private function input(string $key,int $max=200): string { $value=$_POST[$key] ?? '';return is_string($value) && strlen($value)<=$max ? $value : ''; }
    private function pending(): array
    {
        $user=$this->auth->user((int)($_SESSION['pending_id'] ?? 0));
        if (!$user || time()-(int)($_SESSION['pending_since'] ?? 0)>600 || (int)$user['session_version']!==(int)($_SESSION['pending_version'] ?? 0)) {$this->clear();$this->redirect('/admin/login');}
        return $user;
    }
    private function signedIn(): array
    {
        $user=$this->auth->user((int)($_SESSION['owner_id'] ?? 0));
        if (!$user || !$user['mfa_enabled_at'] || (int)$user['session_version']!==(int)($_SESSION['version'] ?? 0)) {$this->clear();$this->redirect('/admin/login');}
        return $user;
    }
    private function finish(int $id): void
    {
        $user=$this->auth->user($id);$this->clear();$_SESSION['owner_id']=$id;$_SESSION['version']=(int)$user['session_version'];
    }
    private function formToken(): string {return '<input type="hidden" name="csrf" value="'.self::e($_SESSION['csrf']).'">';}
    private static function e(string $value): string {return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private function render(string $title,string $content,?array $user=null,string $error=''): void
    {
        $token=$this->formToken();$nav=$user ? '<nav aria-label="Admin"><a href="/admin/">Dashboard</a><a href="/admin/pages">Pages</a><a href="/admin/posts">Posts</a><a href="/admin/media">Media</a><a href="/admin/pages?document=shared">Settings</a><a href="/admin/security">Account security</a><a href="/">Visit website ↗</a><form action="/admin/logout" method="post">'.$token.'<button class="text-button">Sign out</button></form></nav>' : '<a href="/">← Website</a>';
        $alert=$error!=='' ? '<p class="alert" role="alert">'.self::e($error).'</p>' : '';
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.self::e($title).' | EdFund administration</title><link rel="stylesheet" href="/assets/css/admin.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/public/assets/css/admin.css'),0,12).'"></head><body><header><a class="brand" href="/admin/"><img src="/assets/images/logo-web.png" alt="EdFund Foundation" width="500" height="282"></a>'.$nav.'</header><main id="main" class="'.(in_array($title,['Pages','Story editor','Media'],true)?'editor-main':'').'"><p class="eyebrow">EdFund administration</p><h1>'.self::e($title).'</h1>'.$alert.$content.'</main></body></html>';
    }
    public function run(string $path): void
    {
        $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>(($path==='/admin/media-data'?3145728:(in_array($path,['/admin/content','/admin/posts-data'],true)?1500000:16384)))) {$this->abort(413,'Request too large.');}
        if (!in_array($method,['GET','POST'],true)) {header('Allow: GET, POST');$this->abort(405,'Method not allowed.');}
        if ($method==='POST') {$this->csrf();}
        if ($path==='/admin') {$this->redirect('/admin/');}
        if ($path==='/admin/login') {
            if (isset($_SESSION['owner_id'])) {$this->signedIn();$this->redirect('/admin/');}
            $error='';
            if ($method==='POST') {
                $user=$this->auth->passwordLogin($this->input('email'),$this->input('password',128),$this->source);
                if ($user) {$this->clear();$_SESSION['pending_id']=(int)$user['id'];$_SESSION['pending_since']=time();$_SESSION['pending_version']=(int)$user['session_version'];$this->redirect($user['mfa_enabled_at']?'/admin/mfa':'/admin/enroll');}
                http_response_code(422);$error='Sign-in could not be completed. Check your details or wait 15 minutes before trying again.';
            }
            $this->render('Sign in','<p>Use your owner account to manage EdFund.</p><form method="post">'.$this->formToken().'<label>Email<input type="email" name="email" maxlength="190" autocomplete="username" required></label><label>Password<input type="password" name="password" maxlength="128" autocomplete="current-password" required></label><button>Continue</button></form><p class="help">Your authenticator or a recovery code is required after your password. For a forgotten password, use the private account recovery procedure.</p>',null,$error);return;
        }
        if ($path==='/admin/mfa') {
            $user=$this->pending();if (!$user['mfa_enabled_at']) {$this->redirect('/admin/enroll');}$error='';
            if ($method==='POST') {
                $result=$this->auth->verifyFactor((int)$user['id'],$this->input('code',64),$this->source);
                if ($result==='totp') {$this->finish((int)$user['id']);$this->redirect('/admin/');}
                if ($result==='recovery') {$new=$this->auth->user((int)$user['id']);$this->clear();$_SESSION['pending_id']=(int)$user['id'];$_SESSION['pending_since']=time();$_SESSION['pending_version']=(int)$new['session_version'];$this->redirect('/admin/enroll');}
                http_response_code(422);$error='The code could not be accepted. Use a fresh code, or wait before trying again.';
            }
            $this->render('Verify your identity','<p>Enter the current six-digit authenticator code, or one of your saved recovery codes. A recovery code will require you to set up your authenticator again.</p><form method="post">'.$this->formToken().'<label>Authenticator or recovery code<input name="code" maxlength="64" autocomplete="one-time-code" required></label><button>Verify</button></form><a href="/admin/login">Back to sign in</a>',null,$error);return;
        }
        if ($path==='/admin/enroll') {
            $user=$this->pending();if ($user['mfa_enabled_at']) {$this->redirect('/admin/mfa');}
            $_SESSION['enroll_secret'] ??= Mfa::generate();$secret=$_SESSION['enroll_secret'];$error='';
            if ($method==='POST') {
                $codes=$this->auth->enroll((int)$user['id'],$secret,$this->input('code',6),$this->source);
                if ($codes) {$this->finish((int)$user['id']);$_SESSION['new_codes']=$codes;$_SESSION['codes_since']=time();$this->redirect('/admin/recovery-codes');}
                http_response_code(422);$error='The authenticator code could not be accepted. Check the account key and your device time, then use a fresh code.';
            }
            $this->render('Set up your authenticator','<p>In your authenticator app, add an account manually with these settings. This key is shown only during setup; keep it private.</p><dl><dt>Account</dt><dd>'.self::e($user['email']).'</dd><dt>Issuer</dt><dd>EdFund Uganda Foundation</dd><dt>Setup key</dt><dd><code class="setup-key">'.self::e($secret).'</code></dd><dt>Type</dt><dd>Time-based · 6 digits · 30 seconds · SHA-1</dd></dl><form method="post">'.$this->formToken().'<label>Current authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><button>Confirm authenticator</button></form>',null,$error);return;
        }
        $user=$this->signedIn();
        if (isset($_SESSION['new_codes']) && $path!=='/admin/recovery-codes' && $path!=='/admin/logout') {$this->redirect('/admin/recovery-codes');}
        if (in_array($path,['/admin/posts-data','/admin/media-data'],true) || str_starts_with($path,'/admin/media-file/')) {PublishingAdmin::api($this->config,$path,$method,$user);return;}
        if ($method==='GET' && in_array($path,['/admin/posts','/admin/post','/admin/media'],true)) {$title=match($path){'/admin/posts'=>'Posts','/admin/post'=>'Story editor',default=>'Media'};$content=match($path){'/admin/posts'=>PublishingAdmin::postsScreen(),'/admin/post'=>PublishingAdmin::editorScreen(),default=>PublishingAdmin::mediaScreen()};$this->render($title,$this->formToken().$content,$user);return;}
        if (in_array($path,['/admin/content','/admin/preview'],true)) {Editor::handle($this->config,$path,$method,(int)$user['id']);return;}
        if ($path==='/admin/pages' && $method==='GET') {$this->render('Pages',$this->formToken().Editor::screen(),$user);return;}
        if ($path==='/admin/logout') {
            if ($method!=='POST') {header('Allow: POST');$this->abort(405,'Use the sign-out button.');}
            $this->auth->audit('logout',(int)$user['id'],$this->source);$_SESSION=[];session_destroy();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/admin','secure'=>parse_url($this->config['base_url'],PHP_URL_SCHEME)==='https','httponly'=>true,'samesite'=>'Strict']);$this->redirect('/admin/login');
        }
        if ($path==='/admin/recovery-codes') {
            if (!isset($_SESSION['new_codes']) || time()-(int)($_SESSION['codes_since'] ?? 0)>600) {unset($_SESSION['new_codes'],$_SESSION['codes_since']);$this->redirect('/admin/security');}
            if ($method==='POST') {unset($_SESSION['new_codes'],$_SESSION['codes_since']);$this->rotate();$this->redirect('/admin/');}
            $list=implode('',array_map(fn($code)=>'<li><code>'.self::e($code).'</code></li>',$_SESSION['new_codes']));
            $this->render('Save your recovery codes','<p>Store these codes in your password manager or another secure place. Each works once, together with your password. They will not be displayed again after you continue.</p><ul class="recovery-list">'.$list.'</ul><form method="post">'.$this->formToken().'<button>I have saved my codes</button></form>',$user);return;
        }
        if ($path==='/admin/security') {
            $error='';
            if ($method==='POST') {
                $codes=$this->auth->regenerateCodes((int)$user['id'],$this->input('password',128),$this->input('code',6),$this->source);
                if ($codes) {$_SESSION['new_codes']=$codes;$_SESSION['codes_since']=time();$this->rotate();$this->redirect('/admin/recovery-codes');}
                http_response_code(422);$error='Your password or code could not be accepted. Wait for a fresh authenticator code before trying again.';
            }
            $this->render('Account security','<p>Two-factor authentication is active. You have <strong>'.$this->auth->remainingCodes((int)$user['id']).'</strong> unused recovery codes.</p><h2>Replace recovery codes</h2><p>This immediately invalidates the old set. Enter your password and a fresh authenticator code.</p><form method="post">'.$this->formToken().'<label>Current password<input type="password" name="password" maxlength="128" autocomplete="current-password" required></label><label>Authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><button>Generate new codes</button></form>',$user,$error);return;
        }
        if ($path==='/admin/health') {
            if ($method!=='GET') {$this->abort(405,'Method not allowed.');}
            $health=Preflight::report($this->config);$rows='';foreach ($health as $key=>$value) {$rows.='<tr><th scope="row">'.self::e($key).'</th><td>'.self::e(is_scalar($value)?(string)$value:json_encode($value)).'</td></tr>';}
            $this->render('System status','<table>'.$rows.'</table>',$user);return;
        }
        if ($path==='/admin/' && $method==='GET') {
            $this->render('Welcome, '.$user['name'],'<p>Your owner account is protected with two-factor authentication.</p><div class="status-card"><h2>Manage your website</h2><p><a class="button" href="/admin/pages">Open visual page editor</a></p><p>Keep your recovery codes in a secure place and check your account security settings when you change devices.</p><a class="button" href="/admin/security">Manage account security</a></div><p><a href="/admin/health">View system status</a></p>',$user);return;
        }
        $this->abort(404,'Page not found.');
    }
}
