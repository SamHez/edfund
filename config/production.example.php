<?php
// Copy PRIVATELY to /home/ACCOUNT/edfund-data/config.php (0600), outside all web roots.
// Never put credentials into the ZIP, a public path file, chat, or Git.
return [
    'environment' => 'production',
    'base_url' => 'https://edfundfoundation.org',
    'document_root' => '/home/CPANEL_ACCOUNT/public_html',
    // List any other account document roots that contain/overlap installation paths.
    'additional_public_roots' => [],
    'public_indexing' => false,
    'staging_user' => 'ENTER_PRIVATELY',
    'staging_password_hash' => 'GENERATE_WITH_PASSWORD_HASH_PRIVATELY',
    'app_key' => 'GENERATE_32_RANDOM_BYTES_BASE64_PRIVATELY_OR_KEEP_EXISTING_CMS_KEY',
    'storage_path' => '/home/CPANEL_ACCOUNT/edfund-data/storage',
    'backup_key_file' => '/home/CPANEL_ACCOUNT/edfund-data/backup.key',
    'backup_keep' => 7,
    'media_quota_bytes' => 536870912,
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=CPANEL_PREFIX_DATABASE;charset=utf8mb4',
        'user' => 'CPANEL_PREFIX_RUNTIME_USER',
        'password' => 'ENTER_PRIVATELY',
    ],
];
