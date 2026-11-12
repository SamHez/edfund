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
        OwnerSession::start($config);
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
    private function clear(): void {OwnerSession::clear();}
    private function redirect(string $path): never {header('Location: '.$path,true,303);exit;}
    private function abort(int $status,string $message): never {http_response_code($status);echo htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');exit;}
    private function csrf(): void
    {
        if (($_SERVER['HTTP_ORIGIN'] ?? $this->config['base_url'])!==$this->config['base_url'] || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) {$this->abort(403,'This form expired. Reload the page and try again.');}
    }
    private function input(string $key,int $max=200): string { $value=$_POST[$key] ?? '';return is_string($value) && strlen($value)<=$max ? $value : ''; }
    private function signedIn(): array
    {
        $user=OwnerSession::user($this->auth);
        if(!$user){$this->clear();$this->redirect('/admin/login');}
        return $user;
    }
    private function finish(array $user): void
    {
        // Keep the verified version: a concurrent password reset must revoke this login.
        $this->clear();$_SESSION['owner_id']=(int)$user['id'];$_SESSION['version']=(int)$user['session_version'];$_SESSION['auth_method']='password';
    }
    private function formToken(): string {return '<input type="hidden" name="csrf" value="'.self::e($_SESSION['csrf']).'">';}
    private static function e(string $value): string {return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private function render(string $title,string $content,?array $user=null,string $error=''): void
    {
        AdminLayout::render($title,$content,$user,$this->formToken(),$error,$user?EditingContext::editor(Database::connect($this->config['db']),$this->config,parse_url($_SERVER['REQUEST_URI'] ?? '',PHP_URL_PATH) ?: ''):null,$user?Database::connect($this->config['db'])->query("SELECT slug,label FROM cms_documents WHERE slug<>'shared' ORDER BY slug='home' DESC,label")->fetchAll():[]);
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
                $user=$this->auth->passwordLogin($this->input('name',400),$this->input('password',128),$this->source);
                if ($user) {$this->auth->audit('login.password',(int)$user['id'],$this->source);$this->finish($user);$this->redirect('/admin/');}
                http_response_code(422);$error='Sign-in could not be completed. Check your details or wait 15 minutes before trying again.';
            }
            $this->render('Sign in','<form method="post">'.$this->formToken().'<label>Name<input type="text" name="name" maxlength="100" autocomplete="username" required></label><label>Password<input type="password" name="password" maxlength="128" autocomplete="current-password" required></label><button>Continue</button></form>',null,$error);return;
        }
        // First-run owner setup for hosts without a terminal. Needs the private setup_key from config.php and closes itself once an owner exists.
        if ($path==='/admin/setup') {
            $key=(string)($this->config['setup_key'] ?? '');$db=Database::connect($this->config['db']);
            $owner=$db->query('SELECT id FROM cms_users ORDER BY id LIMIT 1')->fetch();
            $reset=($this->config['allow_owner_reset'] ?? false)===true;
            if (strlen($key)<12 || ($owner && !$reset)) {$this->abort(404,'Page not found.');}
            $error='';
            if ($method==='POST') {
                $this->csrf();usleep(1500000);
                $name=trim($this->input('name',200));$password=$this->input('password',128);
                if (!hash_equals($key,$this->input('setup_key',300))) {$error='The setup key does not match the one in your configuration file.';}
                elseif ($name==='' || !mb_check_encoding($name,'UTF-8') || mb_strlen($name,'UTF-8')>100 || preg_match('/[\x00-\x1f\x7f]/',$name)) {$error='Enter a name between 1 and 100 characters.';}
                elseif (!hash_equals($password,$this->input('confirm',128))) {$error='The two passwords do not match.';}
                else {
                    try {
                        $hash=Passwords::hash($password);$db->beginTransaction();
                        if ($owner) {$s=$db->prepare('UPDATE cms_users SET name=?,password_hash=?,mfa_secret=NULL,mfa_enabled_at=NULL,last_totp_step=-1,session_version=session_version+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$name,$hash,$owner['id']]);$id=(int)$owner['id'];}
                        else {$s=$db->prepare('INSERT INTO cms_users (email,name,password_hash,created_at,updated_at) VALUES (?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$s->execute(['',$name,$hash]);$id=(int)$db->lastInsertId();}
                        $this->auth->audit($owner?'owner.web_reset':'owner.web_setup',$id,$this->source);$db->commit();$this->redirect('/admin/login');
                    } catch (\InvalidArgumentException $e) {if($db->inTransaction()){$db->rollBack();}$error=$e->getMessage();}
                }
                http_response_code(422);
            }
            $this->render($owner?'Reset owner password':'Set up your owner account','<p>'.($owner?'Choose a new name and password for the existing owner.':'Create the one account used to manage this website.').'</p><form method="post">'.$this->formToken().'<label>Setup key<input type="password" name="setup_key" maxlength="300" autocomplete="off" required></label><label>Your name<input type="text" name="name" maxlength="100" autocomplete="username" required></label><label>Password (at least 4 characters)<input type="password" name="password" maxlength="128" autocomplete="new-password" required></label><label>Confirm password<input type="password" name="confirm" maxlength="128" autocomplete="new-password" required></label><button>Create account</button></form><p class="help">The setup key is the phrase you typed into <code>edfund-data/config.php</code>.</p>',null,$error);return;
        }
        // Retired MFA URLs never authorize a legacy pending session.
        if (in_array($path,['/admin/mfa','/admin/enroll','/admin/recovery-codes'],true)) {
            if(isset($_SESSION['owner_id'])){$this->signedIn();$this->redirect('/admin/');}
            $this->clear();$this->redirect('/admin/login');
        }
        $user=$this->signedIn();
        if($path==='/admin/new-story'){
            if($method!=='POST'){header('Allow: POST');$this->abort(405,'Use the create story button.');}
            $state=(new Posts(Database::connect($this->config['db']),$this->config))->create((int)$user['id'],$user['name']);$this->redirect('/admin/post?id='.$state['id']);
        }
        if (in_array($path,['/admin/posts-data','/admin/media-data'],true) || str_starts_with($path,'/admin/media-file/')) {PublishingAdmin::api($this->config,$path,$method,$user);return;}
        if ($method==='GET' && in_array($path,['/admin/posts','/admin/post','/admin/media'],true)) {$title=match($path){'/admin/posts'=>'Posts','/admin/post'=>'Story editor',default=>'Media'};$content=match($path){'/admin/posts'=>PublishingAdmin::postsScreen(),'/admin/post'=>PublishingAdmin::editorScreen(),default=>PublishingAdmin::mediaScreen()};$this->render($title,$this->formToken().$content,$user);return;}
        if (in_array($path,['/admin/content','/admin/preview'],true)) {Editor::handle($this->config,$path,$method,(int)$user['id']);return;}
        if ($path==='/admin/pages' && $method==='GET') {
            $document=$_GET['document'] ?? null;
            if($document===null){$this->render('Pages',Editor::index($this->config),$user);return;}
            $row=is_string($document)?Database::connect($this->config['db'])->prepare('SELECT slug,label FROM cms_documents WHERE slug=?'):null;
            if($row){$row->execute([$document]);$row=$row->fetch();}
            if(!$row){$this->abort(404,'Page not found.');}
            $this->render('Pages',$this->formToken().Editor::screen($row['slug'],$row['slug']==='shared'?'Website Settings':$row['label']),$user);return;
        }
        if ($path==='/admin/logout') {
            if ($method!=='POST') {header('Allow: POST');$this->abort(405,'Use the sign-out button.');}
            $this->auth->audit('logout',(int)$user['id'],$this->source);OwnerSession::logout();$this->redirect('/admin/login');
        }
        if ($path==='/admin/health') {
            if ($method!=='GET') {$this->abort(405,'Method not allowed.');}
            $health=Preflight::report($this->config);$rows='';foreach ($health as $key=>$value) {$rows.='<tr><th scope="row">'.self::e($key).'</th><td>'.self::e(is_scalar($value)?(string)$value:json_encode($value)).'</td></tr>';}
            $this->render('System status','<table>'.$rows.'</table>',$user);return;
        }
        if ($path==='/admin/' && $method==='GET') {
            $this->render('Dashboard',AdminLayout::dashboard($user['name'],Analytics::summary(Database::connect($this->config['db']))),$user);return;
        }
        $this->abort(404,'Page not found.');
    }
}
