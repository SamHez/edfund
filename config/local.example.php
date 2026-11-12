<?php
// Copy to config/local.php privately, or use: php bin/cms.php local:configure
// Never commit real credentials. Prefer the generated local setup.
return [
    'environment' => 'local',
    'base_url' => 'http://127.0.0.1:8089',
    // Production remains protected staging until the authorised release sets this true.
    'public_indexing' => false,
    // In production staging, supply a username and password_hash() result privately.
    'staging_user' => '',
    'staging_password_hash' => '',
    // Completed encrypted backups retained locally (2–30); copy offsite separately.
    'backup_keep' => 7,
    'app_key' => 'REPLACE_WITH_BASE64_ENCODED_32_RANDOM_BYTES',
    'storage_path' => dirname(__DIR__).'/storage',
    'db' => [
        'dsn' => 'mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=edfund;charset=utf8mb4',
        'user' => 'ENTER_LOCAL_APPLICATION_USER',
        'password' => 'ENTER_LOCALLY',
    ],
];
