# Phase 4 operations and hosting handover

Phase 4 adds editable remaining pages, per-page SEO, dynamic routing/sitemap, bounded private HTML caches and encrypted backup/restore. It does not build a release ZIP or deploy production. Source HTML, public design CSS/JavaScript, selected pictures, captions, IDs and recovered biographies remain intact; mutable content lives in MariaDB.

## Local operation

Use XAMPP **MySQL only**, plus the project-compatible PHP binary:

```sh
cd '/Users/samhez/Documents/Swave Media/ED Fund Foundation/edfund'
/usr/local/bin/php -S 127.0.0.1:8089 -t public router.php
```

Open `http://127.0.0.1:8089/admin/login`. The real owner still needs to provision credentials privately with `php bin/cms.php owner:create` and complete MFA enrollment. No credentials were invented. See [the editing guide](CMS-EDITING-GUIDE.md).

The existing `edfund` database was preserved. Migration 004 adds `cms_pages` and `cms_redirects`; it does not rewrite earlier migrations or alter legacy tables. Eight remaining pages and nine page-route/SEO mappings were explicitly imported. The original home/shared revisions remain unchanged. Rerunning `pages:import` skips existing mappings and revisions. Normal HTTP requests, code upgrades and migrations never import/reset content. `layouts:sync` only registers approved templates, and `media:index` adds historical usage references.

For a genuinely new environment, installation is explicit and ordered:

```sh
composer install --no-dev --prefer-dist --no-interaction
php bin/cms.php migrate
php bin/cms.php content:import
php bin/cms.php posts:import
php bin/cms.php pages:import
php bin/cms.php media:index
```

Use private per-environment configurations; do not copy XAMPP credentials to cPanel. Back up before migrations. Do not run the retired static build/ZIP scripts or regenerate public HTML over manual content. A code update must not replace the database, private configuration, backup key or runtime storage.

## SEO, routing and cache behaviour

Per-page SEO lives with page revisions; old snapshots inherit their original defaults until edited. Unique nonempty titles/descriptions, the homepage title prefix, one initial H1 and meaningful image descriptions are validated. Metadata, canonical, Open Graph, Twitter and JSON-LD are rendered together from published snapshots. Social images are selectable per page; defaults use existing relevant content photographs (or that page’s banner when it has no other photograph). No content or picture changes are required to update metadata.

Structured data describes the actual NGO, website, page, breadcrumbs and published articles. Organisation contact details/logo follow the shared header. No invented ratings or impact claims are added. Published-only `/sitemap.xml` excludes drafts, aliases and trashed posts. `lastmod` uses published revision times, with known source-file modification times for imported pages and recorded publication dates for imported stories; shared publication changes affect all pages. Draft saves do not advance these dates. UTC is used consistently. These are technical checks, not a claim of search rankings or Search Console validation.

The PHP controller handles canonical slash/index aliases, verified old navigation/story aliases, real 404 and trashed-story 410 responses. Unknown historical URLs are not indiscriminately redirected to the homepage. Stale HTML files cannot bypass PHP under the new Apache rules. `public/.htaccess` and `public/index.php` are code only at this stage; the actual cPanel rewrite/cutover test belongs to Phase 5.

Local pages are noindex. In production, `public_indexing=false` defaults to protected staging: a private `staging_user` and `staging_password_hash` must be supplied, otherwise access is refused. Canonicals still point to the agreed production root. Only an authorised release enables `public_indexing=true`. Admin/private previews remain MFA/session protected, no-store and noindex regardless. Do not treat robots.txt as authentication.

The public HTML cache is outside the web root: five-minute lifetime, at most 64 entries/32 MiB, at most 1 MiB per entry. Keys cover published page/shared/post pointers and application/frontend code hashes. Draft saves do not replace live HTML. Cookie, Authorization and query-string requests bypass caching; admin/previews never enter it. Failed cache writes fall back to rendering. Publication locks and repeatable-read snapshots keep cache keys/content and page/shared pointers coherent. `X-EdFund-Cache` reports HIT, MISS or BYPASS. Public responses revalidate; there is no long browser cache hiding published edits.

Errors return generic references; detailed exception class/location is logged privately without credentials or submitted text. Page/post publication and restoration have owner audit records. Original upload security, throttling, CSRF/origin checks and MFA/recovery policies remain active.

## Encrypted backup and recovery

The backup is an application recovery file, **not a deployable release ZIP**. PHP Sodium’s authenticated secretstream encrypts CMS table snapshots, private application configuration/key and all stored uploaded originals/derivatives. Legacy unrelated tables, code/vendor, bundled Git assets, sessions, caches and logs are excluded. Keep the matching Git commit, PHP dependencies and separate legacy/database backups too.

Create the independent backup key **once**:

```sh
php bin/cms.php backup:key
php bin/cms.php backup:create
```

The local key already exists at ignored `config/backup.key`; that command refuses to overwrite it. Save a separate secure copy of the key and encrypted `.efb` files outside this hosting account. Losing the key makes those backups unreadable. No usable key or backup belongs in Git, public folders or a release archive.

A consistent CMS database snapshot is captured while publication/media locks prevent partially published or uploaded assets. Files include lengths and SHA256 hashes inside the authenticated stream. Frames/records and restore totals are bounded (2 GiB of decoded records); missing media or oversized records fail rather than yielding a misleading complete backup. Partial `.pending` files are removed on normal failure; only completed random `.efb` files are eligible for retention cleanup. After successful creation, retain the newest seven backups by default (`backup_keep` private setting, bounded 2–30). Backups are never pruned before a new one completes. Monitor disk space and job exit status.

In Phase 5, schedule a daily cPanel cron using the host’s correct PHP CLI path and private code directory, plus independent offsite transfer/retention and failure notification. No cron or external transfer has been configured here. Take an additional backup before each update. Retention is local housekeeping, not offsite protection.

The restore command does not connect to the source database or require its private configuration, so it also works after source-database loss. Restore requires a maintainer, the independent key, matching code/migrations, a **different existing empty database**, and a **new private output folder**. The command always refuses `edfund`, the source database, nonempty targets and existing/public output folders. It never overwrites a live database to rehearse recovery.

Create a private target PHP configuration outside `public/` with a `db` array (`dsn`, `user`, `password`), optional `environment` and `base_url`. That temporary account needs schema privileges on the empty alternate database. Then:

```sh
php bin/cms.php backup:restore \
  --file=/private/path/backup.efb \
  --key-file=/private/path/backup.key \
  --target-config=/private/path/restore-target.php \
  --output=/private/path/new-restore-folder
```

The entire encrypted stream authenticates before any target schema/data writes. Migration checksums must match versioned code. Schemas come from those migrations, never arbitrary SQL in the archive. Data pointers/relationships and media hashes are verified. The new private folder contains restored media and `restored-config.php`; it uses alternate DB credentials, retains the original application key for MFA, disables indexing and revokes prior sessions. Protect it as a secret. On failed restore, the new isolated output/target may contain incomplete recovery artifacts: inspect and remove only that disposable target before retrying. The source is untouched.

Point a separate loopback/staging process at that configuration and verify pages, owner MFA/recovery, editing, media and publication. Set up a dedicated runtime DML account rather than retaining the restore administrator. Production recovery cutover is a separately authorised operation with a fresh pre-cutover backup. Do not replace the live application key with a random one after restore: it protects MFA/recovery digests.

## WordPress export review

The supplied `edfundf_wp718.sql` was available and read offline, never executed or committed. [Public inventory](wordpress-public-inventory.json) records only published page/post/team titles and slugs plus export checksum. Four agreed team biographies were recovered in Phase 1; the three current stories and their existing routes were imported in Phase 3. Phase 4 imports the current agreed pages rather than old Elementor markup, duplicate home drafts or plugin data. Verified current equivalents receive repeat-safe redirect records.

The dump additionally contains published stories about refresher training for technicians/patrons, the August–September 2026 teachers’ training and Nabakazi Petralina. These are outside the three current-site articles agreed for migration and have not been published or redirected to unrelated content. They can be selected for a later explicit historical-content import. The export also includes obsolete home variants, donation confirmation/donation pages and an Elementor Kitchen and Dining page. Do not recreate obsolete donation/payment behaviour without checking the intended destination. There is no missing export; additional historical scope requires an editorial decision, not a database blocker.

## Required before cPanel release

- Confirm the domain/account, document-root mapping and authorised access method. Only `public/` is web-facing; `app/`, config, vendor, migrations, CLI and storage must be outside it. Verify Apache rewrite/headers/DirectoryIndex settings and that old static files cannot win routing. No global local Apache settings were changed.
- Select a fully patched supported PHP 8.3+ runtime for **both web and CLI**. The supplied hosting screenshot’s PHP 8.2 default is unsuitable. Current preflight enforces security floors 8.3.36 / 8.4.27 / 8.5.12. Local tests used PHP 8.5.7; production is intentionally refused at that patch. PHP 8.3 execution and the final provider patch level remain to be checked on staging.
- Enable PDO MySQL, OpenSSL, Sodium, DOM/libxml, mbstring, fileinfo, sessions, GD with JPEG/PNG/WebP, and EXIF for orientation handling. Confirm 128 MiB+ memory and actual upload/POST/time/disk limits. Current defaults cap pictures at 2 MiB/4 million pixels/512 MiB retained storage, adapting down to server limits.
- Create the cPanel database and scoped runtime user privately; provide database name/host/user/password via the private configuration. Use a separate temporary migration/restore credential. Verify InnoDB transactions, advisory locks and MariaDB compatibility on the host (local 10.4.27; screenshot 10.11.19). Never ship root/XAMPP credentials.
- Enable cPanel Directory Privacy on a staging hostname if bundled static assets must also require sign-in (the PHP staging gate protects dynamic content; Apache serves trusted bundled assets directly). Confirm HTTPS, canonical root `https://edfundfoundation.org`, secure cookies, protected noindex staging, writable private storage (0700 directories/0600 secret files where supported), and no execution/direct access to uploads/originals.
- Provide cron/CLI access, independent backup-key custody, offsite backup destination, disk monitoring and a successful host restore rehearsal. If CLI/cron is unavailable, agree a secure hosting-supported backup/migration method before release; do not add a public installer.
- Provision the actual owner privately and retain recovery codes. SMTP remains unused: there is no public email password-reset or server-side contact-mail feature. Existing contact links open the visitor’s email app. SMTP is required only if a separately agreed feature needs it.
- Review the editable Privacy Policy (its current manually supplied text still describes the earlier static demonstration), outstanding historical articles and donation links with the client before launch. Content was preserved rather than silently rewritten.
- Phase 5 still needs the production release package, staging/Apache/media/extension verification, protected import/cutover, real HTTPS/redirect/SEO checks, Search Console access/submission and production rollback rehearsal. No ZIP or deployment is included in Phase 4.

References: [PHP secretstream](https://www.php.net/sodium-crypto-secretstream-xchacha20poly1305-push), [Google article data](https://developers.google.com/search/docs/appearance/structured-data/article), [Google sitemap dates](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).
