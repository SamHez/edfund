# Phase 5 release and deployment status

Date: 12 November 2026.

**Source update after the packaged release:** owner setup/login now uses name/password with a four-character minimum, and authenticator/recovery-code steps have been removed. The existing ZIP still represents its earlier tested commit; it has not been rebuilt for this adjustment. The Phase 1 admin UI redesign and Phase 2 authenticated public toolbar also exist only in source. A requested future rebuild and archive test must include all of these updates. **Production deployment has not been performed.** The requested domain is known, but no confirmed hosting account/document-root mapping or secure SSH/SFTP/cPanel connection is available in this workspace. No remote writes, owner provisioning, indexing change or production database migration has occurred.

## Deliverables

- Build with `python3 build_deployment.py`; output `deployment/edfund-cpanel-ready.zip`, companion `.sha256` and `release-summary.json`. These generated artifacts are ignored by Git. The ZIP includes locked production PHP dependencies, migrations, exact original import snapshots, public bridge/assets, private configuration example, an integrity manifest and installation/editing/recovery instructions.
- [Installation, cutover, upgrade, rollback and maintenance](CMS-CPANEL-RELEASE.md).
- [Client editing guide](CMS-EDITING-GUIDE.md).
- [Encrypted backup/restore details](CMS-PHASE-4-OPERATIONS.md).
- CLI `release:check` is fail-closed. Its optional `--local-rehearsal` works only in loopback local mode and never reports production ready. Applied migration checksums, restricted DB privileges, file integrity, password owner, backup key, stale entry points and outside-root storage are checked.

## Verification performed locally

The actual extracted ZIP was installed into new private/public directories and exercised using an isolated Apache 2.4.68 / PHP-CGI 8.5.7 instance and disposable MariaDB databases. No global Apache/PHP configuration or sibling project was changed. It ran using only bundled vendor code and PHP/MariaDB; no server-side Node, Composer, Python or worker was needed for application requests/installation commands.

Checks cover ZIP CRC/SHA256/manifest and secret/runtime exclusions; explicit migrations and repeat-safe imports; Apache .htaccess routing/public bridge/redirects/404/private-path denial; owner login and actual draft/preview/publish/restore; validated image upload and draft-only media privacy; content, revision and uploaded-image survival after code-folder update and public-path rollback; encrypted backup and isolated restoration with retained MFA/app keys and revoked sessions; hero, Why Uganda lightbox and team dialogs at 390/320px. Tests clean up their own databases/server instances. Full Phase 1–4 regressions are also run against isolated databases.

This does **not** verify the actual provider’s patched PHP, HTTPS/reverse-proxy behaviour, production filesystem/DB privileges, client credentials or scheduled/offsite backups. PHP 8.3 execution was not available here. Development PHP 8.5.7 fails the production patch floor; no guard was bypassed to claim a production test. A successful local package test is not a live login/editing/backup test.

Current artifact-specific report: `deployment/phase5-validation.json` records the tested ZIP hash, source commit, local versions/scope and `deployed:false`. Rebuild and rerun it if changing the release inputs; do not claim an older report validates different bytes.

## Read-only public comparison

Verified-HTTPS GETs returned 200 for nine top-level public pages. Their main narrative and image order match this checkout on eight pages. On Team, existing core narrative and four portrait cards match after excluding the **new, explicitly requested recovered-biography/modal UI**; the live site does not yet have that addition. This expected difference was not imported over or mistaken for a manual edit. Recheck for subsequent live edits immediately before cutover.

The live homepage still has `Education and Digital Skills in Kamuli | EdFund Uganda Foundation`; the release has the agreed `ED Fund Uganda Foundation | Education & Digital Skills` metadata. This is another pending deployment change, not a verified live SEO fix.

A read-only response showed Nginx with `X-Cache: HIT from Backend`, HSTS and HTTPS 200. The hosting panel/account and cache policy remain unconfirmed. Require provider cache bypass/no-store handling for admin, private previews, media, authentication and dynamic publication, plus a public-cache purge/check during cutover. These public observations do not grant hosting access or establish the contents of the production database. Raw comparison snapshots remain ignored/private in local storage.

## Exactly what is still required privately

1. Confirm the hosting panel/account, domain’s absolute document root, all overlapping account web roots, and a secure SSH/SFTP alias or ignored private access-file path. Verify the host fingerprint and account permissions. If only File Manager is available, arrange provider/cPanel Terminal execution of the private PHP CLI steps. Do not paste passwords, API tokens or private keys into chat.
2. Provide/configure a patched supported PHP web and CLI path (8.3.36 / 8.4.27 / 8.5.12 minimum floors plus current provider updates), required extensions, Apache/equivalent rewrites, HTTPS, account-owned private storage, PHP limits and Nginx/CDN cache rules. Confirm that the actual handler can read the private application/data directories.
3. Privately configure database host/name/user/password, distinguishing existing CMS data from WordPress data. Back up first. Use a restricted runtime user and separate migration/restore privilege. Do not recreate or replace a populated CMS database or upload the local database.
4. Have the actual owner choose name/password credentials through private `owner:create`. If an owner already exists, preserve that identity/app key. No owner account or credential is included in the ZIP.
5. Secure the independent backup key, choose/configure the offsite destination, daily cron and monitoring, and perform a real host restore rehearsal. Retain the current website/account/database backup for rollback.
6. Review the preserved static-demo Privacy Policy wording, donation link destinations and any additional historical WordPress articles before public launch. This release deliberately does not rewrite client prose.

Once those are available, use the installation runbook to verify protected staging, compare current production content, back up, perform the controlled cutover and run live acceptance checks. Only then record the actual deployed URL/commit/hash, owner editing verification and successful host backup/restore. Search Console submission requires its own account access after launch.
