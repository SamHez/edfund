#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {exit(1);}
require dirname(__DIR__).'/vendor/autoload.php';
use EdFund\{Config,Database,Migrations,Passwords,Auth,Crypto,Preflight};
$root=dirname(__DIR__);$command=$argv[1] ?? 'help';
function prompt(string $label, bool $hidden=false): string {
    if (!stream_isatty(STDIN)) {throw new RuntimeException('Interactive setup requires a terminal. Do not pipe account passwords into commands.');}
    fwrite(STDOUT,$label);
    if ($hidden) {exec('stty -echo');}
    try {$line=fgets(STDIN);if ($line===false) {throw new RuntimeException('Input cancelled.');}return rtrim($line,"\r\n");}
    finally {if($hidden){exec('stty echo');fwrite(STDOUT,PHP_EOL);}}
}
function privateFile(string $path,array $value): void {
    $mask=umask(0077);
    try {if (file_exists($path)) {throw new RuntimeException('Refusing to overwrite an existing private configuration.');}if(file_put_contents($path,"<?php\nreturn ".var_export($value,true).";\n",LOCK_EX)===false){throw new RuntimeException('Cannot write private configuration.');}chmod($path,0600);}
    finally {umask($mask);}
}
function grantLocal(PDO $db,array $config): void {
    $user=$db->quote($config['db']['user']);
    foreach ($db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='edfund' AND TABLE_NAME LIKE 'cms\\_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\Acms_[a-z_]+\z/',$table)) {throw new RuntimeException('Unexpected CMS table name.');}
        $privileges=$table==='cms_migrations'?'SELECT':'SELECT,INSERT,UPDATE,DELETE';
        $db->exec("GRANT {$privileges} ON `edfund`.`{$table}` TO {$user}@'localhost'");
    }
}
try {
    if ($command==='local:configure') {
        if (is_file($root.'/config/local.php')) {echo "Private local configuration already exists; it was not changed.\n";exit(0);}
        if (PHP_VERSION_ID<80300) {throw new RuntimeException('Use PHP 8.3+ for this project, not the old XAMPP PHP binary.');}
        $defaults=in_array('--defaults',$argv,true);
        $adminUser=$defaults?'root':(prompt('Local database administrator [root]: ') ?: 'root');
        $adminPassword=$defaults?'':prompt('Local database administrator password (hidden; empty allowed locally): ',true);
        $dsn='mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=edfund;charset=utf8mb4';
        $admin=['dsn'=>$dsn,'user'=>$adminUser,'password'=>$adminPassword];$db=Database::connect($admin);
        $existing=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo count($existing)." existing table(s) found. Only additive cms_ migrations will run.\n";
        Migrations::run($db);
        $user='edfund_cms_'.bin2hex(random_bytes(4));$password=bin2hex(random_bytes(24));
        $db->exec('CREATE USER '.$db->quote($user)."@'localhost' IDENTIFIED BY ".$db->quote($password));
        $config=['environment'=>'local','base_url'=>'http://127.0.0.1:8089','app_key'=>base64_encode(random_bytes(32)),'storage_path'=>$root.'/storage','db'=>['dsn'=>$dsn,'user'=>$user,'password'=>$password]];
        grantLocal($db,$config);
        privateFile($root.'/config/migrations.local.php',$admin);privateFile($root.'/config/local.php',$config);
        Config::load();Database::connect($config['db']);
        echo "Local application configured with a dedicated, table-scoped database user.\nPrivate credentials were saved locally and were not printed.\nNext: php bin/cms.php owner:create\n";exit(0);
    }
    if ($command==='help') {
        echo "EdFund CMS CLI\n  local:configure [--defaults]  Configure only the local XAMPP edfund database\n  migrate                     Apply repeat-safe migrations with private migration credentials\n  owner:create                Privately provision the single owner\n  owner:recover               Privately reset owner password and require new MFA\n  content:import              Import current HTML once; never reset client content\n  layouts:sync                Register approved layouts without changing revisions\n  posts:import                Import the current three stories once, without resets\n  pages:import                Import remaining current pages and verified aliases once\n  media:index                 Index existing content references for deletion protection\n  backup:key                  Create a private independent backup key once\n  backup:create               Create an encrypted private CMS/media backup\n  backup:restore              Restore only to an empty alternate database/folder\n  preflight                   Inspect this runtime/configuration without revealing secrets\n";exit(0);
    }
    // Recovery must work even when the source database/configuration is unavailable.
    if ($command==='backup:restore') {$options=[];foreach(array_slice($argv,2) as $arg){if(preg_match('/\A--(file|key-file|target-config|output)=(.+)\z/',$arg,$m)){$options[$m[1]]=$m[2];}else{throw new InvalidArgumentException('Use --file= --key-file= --target-config= --output=');}}if(count($options)!==4){throw new InvalidArgumentException('Restore needs file, key-file, private target-config and new output folder.');}$result=(new \EdFund\Backup(null,[]))->restore($options['file'],$options['key-file'],$options['target-config'],$options['output']);echo "Restored into the empty alternate database. Private configuration: ".$result.PHP_EOL;exit(0);}
    $config=Config::load();$db=Database::connect($config['db']);$auth=new Auth($db,new Crypto($config['key_bytes']));
    if ($command==='migrate') {
        $path=getenv('EDFUND_MIGRATION_CONFIG') ?: $root.'/config/migrations.local.php';
        $migration=realpath($path);if(!$migration || str_starts_with($migration,$root.'/public/')){throw new RuntimeException('Private migration configuration is missing.');}
        $admin=Database::connect(require $migration);$done=Migrations::run($admin);
        if ($config['environment']==='local') {grantLocal($admin,$config);}
        echo count($done)." migration(s) applied.\n";exit(0);
    }
    if ($command==='backup:key') {$path=$root.'/config/backup.key';\EdFund\Backup::keyFile($path);echo "Private backup key created. Keep an independent secure copy; it is not inside backups.\n";exit(0);}
    if ($command==='backup:create') {$file=(new \EdFund\Backup($db,$config))->create($root.'/config/backup.key');echo "Encrypted private backup: ".$file.PHP_EOL;exit(0);}
    if ($command==='pages:import') {echo (new \EdFund\Pages($db))->import()." remaining pages imported; existing content untouched.\n";(new \EdFund\Media($db,$config))->indexExisting();exit(0);}
    if ($command==='posts:import') {echo (new \EdFund\PostsImport($db,$config))->run()." current stories imported; existing posts untouched.\n";(new \EdFund\Media($db,$config))->indexExisting();exit(0);}
    if ($command==='media:index') {(new \EdFund\Media($db,$config))->indexExisting();echo "Existing media references indexed without changing content.\n";exit(0);}
    if ($command==='layouts:sync') {(new \EdFund\ContentImport($db))->registerLayouts();echo "Approved layouts registered; page revisions untouched.\n";exit(0);}
    if ($command==='content:import') {echo (new \EdFund\ContentImport($db))->run() ? "Current homepage and shared content imported once.\n" : "Content already exists; nothing was overwritten.\n";exit(0);}
    if ($command==='preflight') {echo json_encode(Preflight::report($config),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(0);}
    if ($command==='owner:create' || $command==='owner:recover') {
        $lock='edfund-owner-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,20);
        $s=$db->prepare('SELECT GET_LOCK(?,10)');$s->execute([$lock]);if((int)$s->fetchColumn()!==1){throw new RuntimeException('Owner setup is locked.');}
        try {
            $owner=$db->query('SELECT * FROM cms_users ORDER BY id LIMIT 1')->fetch();
            if ($command==='owner:create' && $owner) {throw new RuntimeException('An owner already exists; it was not changed.');}
            if ($command==='owner:recover' && !$owner) {throw new RuntimeException('No owner exists yet; use owner:create.');}
            $name=$owner['name'] ?? trim(prompt('Owner name: '));$email=$owner['email'] ?? strtolower(trim(prompt('Owner email: ')));
            if ($name==='' || mb_strlen($name)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) {throw new InvalidArgumentException('Enter a valid name and email.');}
            if ($command==='owner:recover' && prompt('Type RESET to revoke all sessions and require a new authenticator: ')!=='RESET') {throw new RuntimeException('Recovery cancelled.');}
            $password=prompt('New passphrase (at least 12 characters, hidden): ',true);$confirm=prompt('Confirm passphrase (hidden): ',true);
            if (!hash_equals($password,$confirm)) {throw new InvalidArgumentException('Passphrases do not match.');}
            $hash=Passwords::hash($password);unset($password,$confirm);$db->beginTransaction();
            if ($owner) {
                $s=$db->prepare('UPDATE cms_users SET password_hash=?,mfa_secret=NULL,mfa_enabled_at=NULL,last_totp_step=-1,session_version=session_version+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$hash,$owner['id']]);
                $s=$db->prepare('DELETE FROM cms_recovery_codes WHERE user_id=?');$s->execute([$owner['id']]);$id=(int)$owner['id'];
                $auth->audit('owner.cli_recovered',$id,'cli');
            } else {
                $s=$db->prepare('INSERT INTO cms_users (email,name,password_hash,created_at,updated_at) VALUES (?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$s->execute([$email,$name,$hash]);$id=(int)$db->lastInsertId();$auth->audit('owner.cli_created',$id,'cli');
            }
            $db->commit();echo "Owner credentials saved. Sign in locally and complete authenticator enrollment.\nNo credentials were printed.\n";
        } finally {if($db->inTransaction()){$db->rollBack();}$s=$db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
        exit(0);
    }
    throw new InvalidArgumentException('Unknown command. Run php bin/cms.php help.');
} catch (Throwable $error) {
    if ($error instanceof PDOException) {fwrite(STDERR,"Database setup failed. Check the private configuration and database permissions. No credentials were displayed.\n");}
    else {fwrite(STDERR,$error->getMessage().PHP_EOL);}
    exit(1);
}
