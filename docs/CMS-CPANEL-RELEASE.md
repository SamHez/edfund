> Current source uses name/password only (minimum four characters); no authenticator is required. The earlier generated ZIP has not been rebuilt for this change. Follow the current source policy during the next explicitly requested build.

# EdFund CMS cPanel installation, release and recovery

Target: **https://edfundfoundation.org/** at the domain root. This package is PHP/MariaDB, not the retired static ZIP. Read this before uploading. A package test is not a live deployment report.

## Package map

Extract the ZIP into a **new private staging folder outside all web roots**, not into public_html or over the existing site. It contains:

| Package path | Destination / purpose |
| --- | --- |
| `public_html/` | Copy contents into the confirmed domain document root during cutover: assets, .htaccess, public index.php bridge and optional path example only. |
| `edfund-app/` | Private application code, CLI, migrations, original HTML import snapshots, bundled dependencies, documentation and RELEASE.json integrity manifest. Must remain outside every account document root. |
| `INSTALL.md` | This runbook. Keep privately. |
| `edfund-data/` | **Not in the ZIP.** Create privately for configuration, application key, uploads, backups, sessions and logs. Never replace it during code updates. |

Default main-domain layout: `/home/ACCOUNT/public_html`, `/home/ACCOUNT/edfund-app`, `/home/ACCOUNT/edfund-data`. If the domain root differs or using versioned private code folders, copy `public_html/edfund-paths.example.php` to **edfund-paths.php** in the actual document root and privately edit its two absolute paths. That public path file contains **paths only, never secrets**. The public bridge resolves them, verifies they are outside its document root and defines the real document root before loading private code. Set `document_root` in the private configuration to exactly the same resolved folder, plus `additional_public_roots` for overlapping account web roots. Do not place private code/data inside another domain’s public_html.

The private `public/` directory contains the exact current source HTML solely for explicit first import and trusted assets. It is not a second document root and must never be exposed. Public .html pages, robots.txt and sitemap.xml are deliberately absent from the live payload; PHP serves their published equivalents. No usable public installer, user/password, database export, live configuration, backup key or runtime upload is included.

## Required private information / host setup

Provide the maintainer with an existing SSH alias or secure private access configuration, verified host fingerprint, SSH/SFTP host and port, account/user and the domain’s absolute document root. Passwords/tokens/private keys/database values must go through the host’s private configuration or secure credential channel, never chat, Git or a public file. SFTP alone still needs cPanel Terminal/SSH or provider execution of the CLI steps below. No plain FTP.

In cPanel confirm:

- A fully patched PHP **8.3+** for both this domain and CLI, with matching extensions. The application’s minimum security floors are 8.3.36, 8.4.27 or 8.5.12; obtain all current provider security updates. PHP 8.2 defaults and this development machine’s PHP 8.5.7 are not production approval. Do not bypass the guard.
- PDO MySQL, DOM/libxml, Sodium, OpenSSL, mbstring, fileinfo, sessions, EXIF, GD JPEG/PNG/WebP. At least 128 MiB PHP memory; confirm actual upload/POST/time/disk limits. Uploads normally cap at 2 MiB/4 million pixels/512 MiB retained media.
- MariaDB/InnoDB transactions and advisory locks. Create a separate CMS database if the current database is WordPress; preserve the WordPress database untouched. Before selecting any existing CMS database, inspect/back up its contents. cPanel prefixes names: note the exact database host, database name, runtime username and password privately.
- Runtime account: SELECT/INSERT/UPDATE/DELETE on CMS tables (SELECT on cms_migrations); no CREATE/DROP/ALTER. A separate temporary migration/restore account needs schema privileges on that database. If cPanel cannot grant this separation, ask the provider to apply the grants. Never reuse XAMPP/root credentials.
- HTTPS certificate, canonical domain, mod_rewrite/mod_headers, AllowOverride permitting the supplied rules, DirectoryIndex index.php and MultiViews disabled. The stock rules preserve existing .well-known ACME/PKI challenge files for certificate renewal; retain any additional provider-specific validation rules. PHP runs as the account owner and can access private code/data. Confirm open_basedir permits those private paths. Configure web PHP with display_errors=Off, log_errors=On, expose_php=Off and session.upload_progress.enabled=Off; verify settings through the authenticated system-status/provider tools. CLI owner provisioning needs a real terminal and exec/stty for hidden passphrase input, or provider assistance using that same private CLI.
- Private storage writable by the account (0700 directories, 0600 configuration/keys); ordinary public code/assets 0644/0755 as needed. No 0777. Daily cron/CLI, offsite backup transfer, disk monitoring and owner recovery-key custody.
- Protected staging hostname/directory. Enable cPanel Directory Privacy for the whole staging document root, including static assets. PHP also protects staging content while `public_indexing=false`. No staging URL should be indexed. Check any Nginx/CDN/provider cache: bypass `/admin` (including previews/APIs), `/media`, authenticated/Authorization/cookie requests and query requests; respect dynamic Cache-Control/no-store, and purge old public HTML during cutover. Prove same-URL edits appear after Publish. If the host is Nginx-only, .htaccess is ineffective: the provider must install equivalent routing/deny/cache rules before deployment.

SMTP is not used: contact links open the visitor’s mail app and account recovery uses the private CLI. Search Console ownership is needed separately for verification/submitting the final sitemap, not for running the CMS.

## Back up and compare before touching production

1. Take/download a provider full-account or website backup, current document-root archive, current database dump(s), configuration and uploaded files. Store these outside every public root and offsite. Test that you can restore to a separate location. The CMS `.efb` backup does not include old WordPress tables or server configuration.
2. Record current domain/document root, PHP handler/version, rewrite rules, live URL/content/image inventory and current code commit. Compare live content with this package; the original source HTML is preserved, not regenerated. Reconcile any live/manual changes before importing. Do not assume a new local database represents current production edits.
3. If an EdFund CMS is already installed, take `backup:create`, copy its independent key securely, retain its private config/app key and uploads, and follow **Upgrade**, not fresh import. Never replace production with the developer’s database.

## Fresh installation in protected staging

Use the correct provider PHP CLI path (`PHP=/opt/cpanel/ea-php83/root/usr/bin/php` is an example only; verify actual patched version). Commands below are examples with private paths; edit ACCOUNT and code-folder names without putting passwords on command lines.

1. Verify the downloaded ZIP SHA256 against its companion checksum. Extract privately and place `edfund-app` outside every web root. Create `/home/ACCOUNT/edfund-data` with 0700 permissions. Copy `config/production.example.php` to `/home/ACCOUNT/edfund-data/config.php`, 0600, and edit privately: environment, staging origin, actual `document_root`, any overlapping public roots, private storage/backup-key paths and database array. Create a separate 0600 `migration.php` returning only `dsn`, `user`, `password` for the migration account.
2. On a genuinely fresh install, generate `app_key` using `base64_encode(random_bytes(32))` **privately**, and staging password hash using `password_hash()` privately. Never regenerate the app key on an existing site or restore. Existing Legacy encrypted information and audit digests depend on it. Configure staging username/hash. Keep `public_indexing=false`.
3. Install only the public payload into an empty protected staging root. Keep the actual live root unchanged. Configure the public path mapping if not using the default sibling folders. Production dependencies are already bundled; no Composer, Node, Python, worker or build process is needed on the host.
4. Set trusted CLI environment paths and run explicit operations:

```sh
export EDFUND_CONFIG=/home/ACCOUNT/edfund-data/config.php
export EDFUND_MIGRATION_CONFIG=/home/ACCOUNT/edfund-data/migration.php
PHP=/path/to/verified/patched/php
"$PHP" /home/ACCOUNT/edfund-app/bin/cms.php migrate
"$PHP" /home/ACCOUNT/edfund-app/bin/cms.php content:import
"$PHP" /home/ACCOUNT/edfund-app/bin/cms.php posts:import
"$PHP" /home/ACCOUNT/edfund-app/bin/cms.php pages:import
"$PHP" /home/ACCOUNT/edfund-app/bin/cms.php media:index
"$PHP" /home/ACCOUNT/edfund-app/bin/cms.php owner:create
"$PHP" /home/ACCOUNT/edfund-app/bin/cms.php backup:key
```

Migrations are additive and checksum-verified. The content commands import current project snapshots once and skip existing data; they are **not** automatic startup/deployment hooks. Do not bulk import the WordPress SQL dump. Four recovered team biographies and the three agreed stories are already in the trusted snapshots. Additional historical articles require explicit editorial selection.

5. Sign in to staging with the actual owner using name/password and complete the editing checks below. No seeded/demo owner is included. Runtime DB permissions must remain DML-only; migration privileges are not required by the web application.
6. Run `preflight`, then the fail-closed `release:check`. It must exit zero on the **actual patched host**. Review its booleans; do not bypass failed checks. It verifies PHP/extensions, outside-root writable storage, backup key, nine published pages, owner login, unchanged applied migrations, advisory locks, retired stale entries and the private/public release file hashes. It does not replace HTTPS/Apache/browser checks or an actual backup restore. It is CLI-only and does not create an owner or import content.
7. Run `backup:create`. Download the `.efb` and independent backup key securely. Test `backup:restore` into a **different empty staging database and new private folder**; verify the restored content/media/owner login. See [Phase 4 recovery](CMS-PHASE-4-OPERATIONS.md). The restore command works without source-database/config access and refuses source/edfund/nonempty targets. Keep matching code/dependencies. Re-provision a restricted runtime account after restore.

## Browser and HTTP acceptance checks

Record date, release commit/checksum, host PHP web/CLI versions, database version and results. Use real HTTPS and mobile/desktop browsers:

- Home, About, Our Work, Team, Volunteer, Contact, Gallery, Privacy Policy and Stories & Updates load at the domain root. Homepage title begins `ED Fund Uganda Foundation | Education`; preserve exact narrative, image choices/order and captions.
- Hero slideshow/arrows, sticky Why Uganda chapter controls, grouped lightboxes, kitchen/dining/dormitory/classroom before/after images, team biography dialogs, gallery and click-to-load YouTube work. Check 390px and 320px layouts and keyboard close/focus behaviour.
- Owner name/password login works with secure cookies. Anonymous callers cannot fetch admin APIs, draft previews or draft-only uploads. Originals/private configuration/storage/code paths are inaccessible. Reject unsafe image types/oversized uploads; a valid JPEG/PNG/WebP upload and publication works.
- Perform an actual page **edit → draft → private preview → publish → restore as draft → publish** cycle. Confirm draft isolation and that public caches show the published version. Use a reversible agreed test field and restore it afterwards.
- Publish a test post with image/YouTube/basic formatting, verify home/listing/article, then restore/trash as agreed so no unwanted test content stays live. Confirm title/description/social metadata, article dates and image access. Restore a revision and confirm previous URL redirects after a deliberate slug rename.
- Verify HTTPS/www/index/slash redirects, preserved legacy aliases, unknown-page 404 and trashed-post 410. `/sitemap.xml` contains published canonical routes with accurate UTC dates, excludes drafts/trashed content, and changes only for publication. `/robots.txt`, OG/Twitter and JSON-LD match intended launch state. Admin/previews remain no-store/noindex.
- Run a code-update rehearsal in staging: save/publish a unique field and upload, install the same/new code into a different private code folder, switch the bridge path, rerun migrations/preflight, and confirm revisions, published field, media hash and owner login survive. Do not run fresh imports on updates.

A local machine rehearsal is useful, but does not establish production PHP patches, TLS, reverse-proxy behaviour, filesystem permissions, provider backups or Search Console validation.

## Cutover

After protected staging and recovery pass, freeze client editing briefly and take another complete backup. Confirm target/access and that production has not changed since the comparison. Preserve existing CMS config, app key, database and storage; do not transplant a test database. For first migration, only the reconciled/imported CMS data becomes the new source of truth; retain the old site and database privately for rollback.

Stage the new public payload in a separate sibling folder and prepare the correct bridge mapping. Move the old document root contents into a **private timestamped backup**, including hidden files, rather than deleting them. Do not leave old WordPress directories or per-directory .htaccess files live. Retire all stale index.html/route HTML/robots/sitemap files so they cannot bypass PHP. Keep cPanel-generated PHP-handler directives required by the provider when merging .htaccess, and verify the resulting PHP handler/security settings. Do not blindly replace provider-managed rules. A reviewed provider-specific .htaccess differs from the stock hash; review the final file and record its SHA256 privately as `'release_web_overrides' => ['.htaccess' => 'REVIEWED_SHA256']` in config.php. Only the exact `.htaccess` filename is permitted as an override; record the reason/host handler in the deployment record. All application/dependency/bridge hashes remain mandatory. Do not silently ignore other differences.

Atomically switch the prepared root/directory mapping where supported; otherwise show a brief maintenance response while replacing only the public payload. Confirm all private paths/permissions and run the host preflight/browser checks before reopening editing. Change private `base_url` to `https://edfundfoundation.org`, set the final document root, remove staging protection on production only and set `public_indexing=true` only when approved checks pass. Enable the provider’s canonical HTTPS redirect for all resources. Verify public pages and admin immediately; never claim live deployment based only on extraction or Git push.

## Upgrade without losing client content

Back up CMS/config/media and provider site files first. Extract a new release privately into a **new code folder**, leaving `edfund-data` intact. Run migrations with the private migration account, inspect/check compatibility, prepare new bundled assets atomically while retaining previously bundled assets still referenced by CMS content (compare existing asset hashes before replacing any same-path image) and change only the public bridge’s application path via `edfund-paths.php`. Keep its configuration path unchanged. Do not overwrite private configuration/backup keys/storage or run content import/layout registration automatically. Existing page/post revision pointers remain authoritative. Release hashes are checked against the corresponding private code and public payload. Only the public path mapping is an installation-specific file outside the release manifest.

Retain the previous code/public release for rollback. Restrict database credentials, preserve uploaded media and test that actual client content survives as described above. If schema changes are not backward-compatible, do not simply point old code at a new schema: use the tested restoration/cutover plan.

## Backups, cron and rollback

The encrypted CMS key is separate from application keys and lives in `backup_key_file`, outside release code. Keep a secure offsite copy. Example daily cron (verify all paths and monitor errors privately):

```sh
EDFUND_CONFIG=/home/ACCOUNT/edfund-data/config.php /path/to/patched/php /home/ACCOUNT/edfund-app/bin/cms.php backup:create >> /home/ACCOUNT/edfund-data/backup-job.log 2>&1
```

Run it manually first. Configure independent offsite transfer/retention and failed-job alerts through the provider, then verify a scheduled run actually happened. Local retention keeps seven completed encrypted files by default; it is not offsite protection. Keep provider full-site/database backups as well. No cron, offsite target or production backup can be claimed configured until tested on the real account.

For **code-only rollback** with compatible schema: freeze editing, back up current state, switch to the retained private code/public assets and bridge mapping, leaving config/database/uploads in place; verify login, public content, media and metadata, then reopen. For **database/media recovery**: restore the selected encrypted backup into a different empty database/new private folder, validate with a protected process, assign a restricted runtime account, then privately switch config to the restored database/storage/app key. Do not erase live data to test recovery. Capture any edits made after the backup before switching. Old sessions are revoked by restore; the retained app key keeps legacy encrypted data usable. For an initial WordPress/static cutover rollback, restore the archived old root/handler/config and retained old database from the provider backup, without deleting the new CMS data. Recheck HTTPS/URLs and stop writes until ownership is clear.

## Maintenance and handover

Use [the editing guide](CMS-EDITING-GUIDE.md) for client training. Keep PHP security updates, dependency audits, TLS renewal, storage quotas, private log rotation and backup monitoring under an assigned maintainer. Review backup restore regularly and before major updates. Keep the deployment record and exact code commit/ZIP hash. Verify Search Console ownership and submit `https://edfundfoundation.org/sitemap.xml` after confirmed launch; indexing/rankings are not guaranteed.

The existing Privacy Policy still contains the client’s earlier static-demo wording; have the client approve suitable final wording and donation destinations. Additional historical WordPress content remains an editorial choice. No website prose, image selections or manual edits were silently replaced by this release build.

References: [cPanel Domains/document roots](https://docs.cpanel.net/cpanel/domains/domains/), [MultiPHP Manager](https://docs.cpanel.net/cpanel/software/multiphp-manager-for-cpanel/), [PHP supported versions](https://www.php.net/supported-versions.php).
