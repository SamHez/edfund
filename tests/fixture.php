<?php
declare(strict_types=1);
// Isolated MariaDB fixtures only. Never drops, truncates or seeds the edfund database.
require dirname(__DIR__).'/vendor/autoload.php';
use EdFund\{Database,Migrations,Passwords,Crypto,Auth,Config,Mfa};
$root=dirname(__DIR__);$action=$argv[1] ?? '';
if ($action==='create') {
    $id='edfund_test_'.bin2hex(random_bytes(6));$directory=$root.'/storage/testing/'.$id;umask(0077);mkdir($directory,0700,true);
    $admin=require $root.'/config/migrations.local.php';$db=Database::connect($admin);
    $db->exec('CREATE DATABASE `'.$id.'` CHARACTER SET utf8mb4');
    $dsn=preg_replace('/dbname=edfund(?=;|$)/','dbname='.$id,$admin['dsn']);$connection=['dsn'=>$dsn,'user'=>$admin['user'],'password'=>$admin['password']];
    $fixture=Database::connect($connection);
    $fixture->exec('CREATE TABLE legacy_sentinel (id INT PRIMARY KEY, content VARCHAR(100))');$fixture->exec("INSERT INTO legacy_sentinel VALUES (1,'preserve existing content')");
    $applied=Migrations::run($fixture);if(count($applied)!==count(glob($root.'/database/migrations/*.sql')) || Migrations::run($fixture)!==[] || $fixture->query('SELECT content FROM legacy_sentinel')->fetchColumn()!=='preserve existing content'){throw new RuntimeException('Migrations failed preservation/idempotence.');}
    $user='edfund_test_'.bin2hex(random_bytes(4));$pass=bin2hex(random_bytes(24));
    $db->exec('CREATE USER '.$db->quote($user)."@'localhost' IDENTIFIED BY ".$db->quote($pass));
    foreach (['cms_users','cms_recovery_codes','cms_auth_attempts','cms_audit','cms_templates','cms_media','cms_documents','cms_revisions','cms_media_details','cms_posts','cms_post_revisions','cms_post_routes','cms_media_usage'] as $table) {$db->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$id.'`.`'.$table.'` TO '.$db->quote($user)."@'localhost'");}
    $db->exec('GRANT SELECT ON `'.$id.'`.`cms_migrations` TO '.$db->quote($user)."@'localhost'");
    $runtime=['dsn'=>$dsn,'user'=>$user,'password'=>$pass];$app=Database::connect($runtime);
    try {$app->exec('CREATE TABLE forbidden_probe (id INT)');throw new RuntimeException('Runtime user unexpectedly has DDL rights');}catch(PDOException $expected){}
    $config=['environment'=>'local','base_url'=>'http://127.0.0.1:'.(int)$argv[2],'app_key'=>base64_encode(random_bytes(32)),'storage_path'=>$directory.'/runtime','db'=>$runtime];
    file_put_contents($directory.'/config.php',"<?php\nreturn ".var_export($config,true).";\n");
    $password=bin2hex(random_bytes(12)).'!';$email='phase1-test@example.invalid';
    $s=$app->prepare('INSERT INTO cms_users (email,name,password_hash,created_at,updated_at) VALUES (?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$s->execute([$email,'Phase 1 test owner',Passwords::hash($password)]);
    file_put_contents($directory.'/fixture.json',json_encode(['database'=>$id,'runtime_user'=>$user,'email'=>$email,'password'=>$password]));
    echo $directory;exit;
}
$directory=realpath($argv[2] ?? '');$base=realpath($root.'/storage/testing');
if(!$directory || !$base || !str_starts_with($directory,$base.'/')){throw new RuntimeException('Not an isolated fixture directory');}
$info=json_decode(file_get_contents($directory.'/fixture.json'),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/\Aedfund_test_[a-f0-9]{12}\z/',$info['database'])){throw new RuntimeException('Invalid test database');}
$config=require $directory.'/config.php';$db=Database::connect($config['db']);
if (in_array($action,['media-quota-low','media-quota-reset'],true)) {if($action==='media-quota-low'){$config['media_quota_bytes']=1024;}else{unset($config['media_quota_bytes']);}file_put_contents($directory.'/config.php',"<?php\nreturn ".var_export($config,true).";\n");exit;}
if ($action==='empty-owner-for-cli-test') {$db->exec('DELETE FROM cms_users');exit;}
if ($action==='concurrent-bad-password') {
    $auth=new Auth($db,new Crypto(base64_decode($config['app_key'])));
    if($auth->passwordLogin($info['email'],'invalid-fixture-password','127.0.0.2')!==null){throw new RuntimeException('Bad password accepted');}exit;
}
if ($action==='attempt-count') {echo (int)$db->query('SELECT COALESCE(MAX(total),0) FROM (SELECT COUNT(*) AS total FROM cms_auth_attempts GROUP BY bucket) counts')->fetchColumn();exit;}
if ($action==='reset-factor-step') {$db->exec('UPDATE cms_users SET last_totp_step=-1');exit;}
if ($action==='clear-attempts') {$db->exec('DELETE FROM cms_auth_attempts');exit;}
if ($action==='snapshot') {
    $user=$db->query('SELECT id,mfa_enabled_at,mfa_secret,last_totp_step,session_version FROM cms_users LIMIT 1')->fetch();
    $crypto=new Crypto(base64_decode($config['app_key']));
    // No secrets are returned: prove storage protection without exposing values.
    echo json_encode(['enrolled'=>$user['mfa_enabled_at']!==null,'encrypted'=>$user['mfa_secret'] ? str_starts_with($user['mfa_secret'],'v1:') : false,'codes'=>$db->query('SELECT COUNT(*) FROM cms_recovery_codes WHERE used_at IS NULL')->fetchColumn(),'version'=>$user['session_version']]);exit;
}
if ($action==='destroy') {
    $admin=Database::connect(require $root.'/config/migrations.local.php');$admin->exec('DROP DATABASE `'.$info['database'].'`');$admin->exec('DROP USER '.$admin->quote($info['runtime_user'])."@'localhost'");echo 'Isolated fixture removed';exit;
}
throw new InvalidArgumentException('Unknown fixture action');
