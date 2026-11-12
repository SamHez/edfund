<?php
// ===================================================================================
//  EDFUND SITE SETTINGS  -  the ONLY file you need to edit before the site goes live.
//  Change the three values marked with a star. Keep the quotation marks and semicolons.
//  This file lives in edfund-data (outside the public website) so nobody can download it.
// ===================================================================================

// STAR 1 - Database details, exactly as created in cPanel > MySQL Databases.
$DB_NAME     = 'cpanelusername_edfund';          // database name (includes the cPanel prefix)
$DB_USER     = 'cpanelusername_edfund';          // database user (includes the cPanel prefix)
$DB_PASSWORD = 'PASTE_THE_DATABASE_PASSWORD_HERE';

// STAR 2 - A private "setup key": any long phrase you invent (at least 12 characters).
// You type it once on https://edfundfoundation.org/admin/setup to create your login.
$SETUP_KEY   = 'TYPE_A_LONG_PRIVATE_PHRASE_HERE';

// ------------- Everything below is already correct; leave it alone -------------
return [
    'environment'     => 'production',
    'base_url'        => 'https://edfundfoundation.org',
    'public_indexing' => true,          // lets Google index the site
    'app_key'         => 'AUTO',        // created automatically in this folder (app.key) - never delete it
    'setup_key'       => $SETUP_KEY,
    'allow_owner_reset' => false,       // set to true ONLY to reset a forgotten password via /admin/setup
    // Optional: paste the code from Google Search Console > "HTML tag" (just the content value).
    'google_site_verification' => '',
    'bing_site_verification'   => '',
    'backup_keep'       => 7,
    'media_quota_bytes' => 536870912,
    'db' => [
        'dsn'      => 'mysql:host=localhost;dbname=' . $DB_NAME . ';charset=utf8mb4',
        'user'     => $DB_USER,
        'password' => $DB_PASSWORD,
    ],
];
