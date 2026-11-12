# Phase 2 — visual page editor

The local CMS edits the homepage and shared contact bar, navigation, footer and homepage metadata. The existing design, plain JavaScript interactions and static source HTML remain preserved. Other page bodies become editable in Phase 4; posts and uploads come in Phase 3.

## Run locally

Keep XAMPP MySQL running. From this repository use the separate supported-major PHP CLI, not XAMPP's old PHP:

```sh
composer install
php bin/cms.php migrate
php bin/cms.php content:import
php -S 127.0.0.1:8089 -t public router.php
```

The current local database has already been migrated and imported. Repeating `migrate` verifies checksums; repeating `content:import` refuses to change existing content. Never recreate `edfund` or rerun `local:configure`. Private configuration remains in ignored `config/local.php` and `config/migrations.local.php`.

Open `http://127.0.0.1:8089/admin/pages`. If no owner has been provisioned, run `php bin/cms.php owner:create` privately, sign in, enroll an authenticator and save recovery codes. No credentials were created for the real owner during Phase 2.

The installed PHP 8.5.7 is permitted for loopback development only. The existing production security patch guard remains; a patched supported runtime is required before hosting. PHP DOM is now an explicit extension requirement in addition to Phase 1 requirements.

## Editing

1. Choose **Homepage** or **Shared content & homepage SEO**.
2. Click a section in the actual preview, or choose it in the left panel. Choose **Part of this section** for headings, mission/vision, Why Uganda chapters and reconstruction groups. Text fields retain the exact imported text, including intentionally formatted whitespace.
3. Edit labelled text/link/alt/caption fields; **Choose picture** opens the searchable existing-image library. New uploads are intentionally unavailable in this phase. A replacement updates responsive image dimensions/variants and the image's enlargement destination. Image descriptions and captions remain explicit fields.
4. Inspect **Desktop** / **Mobile**. Changes refresh the authenticated preview before saving; **Preview** refreshes it immediately. Links cannot take the preview out of the editor. Existing tabs, slides and lightboxes remain interactive.
5. **Move up/down** reorders sections after the mandatory first hero. **Add approved section** offers existing text/picture, mission/vision, impact, before/after, gallery, YouTube, team-card and call-to-action layouts. Styles and markup stay developer-controlled. Remove an accidental section with **Remove**; the hero and shared header/footer structure stay fixed.
6. **Save draft** persists a revision without changing the public website. **Publish** confirms and atomically publishes the submitted page. Unsaved-leave warnings and save status distinguish edits from published content.
7. Select a revision, then **Restore as draft**. Restoration makes a new revision; the public site stays unchanged until **Publish**. The last 50 revisions are shown, with all earlier records retained in the database.
8. If another tab has saved first, the stale action returns a conflict. Current fields stay on screen. Copy anything needed, then **Reload saved content** to review the latest revision. A stale tab never silently overwrites it.

Shared content is published independently. Published navigation/contact/footer changes also appear on existing local PHP-served page bodies. Their current navigation link remains highlighted. Homepage search title/description/sharing fields are included; complete automatic SEO synchronization and all-page SEO belong to Phase 4.

YouTube fields currently take the 11-character video ID already used by the website. The existing click-to-load privacy-enhanced player remains. URL parsing and the complete video/post workflow belong to Phase 3.

## Content model and preservation

Migration `002_content.sql` adds templates, existing-media registry, documents and immutable revision snapshots alongside all existing tables. Document rows contain separate draft and published pointers plus a version counter. Writes use row locks, validation and a transaction; revision restoration copies an old snapshot into a new draft. Publication and the audit event commit together.

The explicit CLI importer reads the **current `public/index.html`**, not the Python source configuration. It snapshots the shell, 11 homepage sections, shared fragments and every editable text/image/link binding. Trusted template HTML and server-owned field paths stay private in the database. The browser submits section IDs, registered template IDs and bounded typed plain-text/image/link/video fields only. Submitted markup is escaped as text; it cannot become scripts, styles or arbitrary template markup. There are no raw HTML fields or handwritten rich-text sanitizer.

All current kitchen, dining, dormitory and classroom comparisons remain within Why Uganda, with their exact image ordering, captions and isolated gallery groups. The four recovered team biographies and static page bodies remain intact. Import refuses to overwrite *any* existing document, even if repository HTML later changes. Review additional migrations explicitly rather than editing database snapshots or resetting import state.

`php bin/cms.php layouts:sync` explicitly registers updated developer-approved additive layouts. It never replaces page revisions, draft/published pointers or values. Prior layout versions remain renderable for old revisions. This command is not called by ordinary web requests or deployments.

The public PHP path reads **published pointers only**. It never imports or seeds. Admin/preview responses require completed MFA, use CSRF/origin checks for mutations and carry no-store/noindex headers. Preview tokens are session-bound, expire after ten minutes and retain at most five private rendered previews. Framing is permitted only for same-origin previews; other admin screens deny framing. Preview messages accept only section IDs from the expected iframe and origin.

## Deployment boundary

This phase changes the local development router, not production Apache cutover. `public/.htaccess` and current static HTML are preserved for comparison. Do not upload the `public` folder alone as a CMS release: static index files can bypass CMS content until the Phase 5 cutover retires them and changes Apache routing.

The old static ZIP entry points now refuse to run, because their generated HTML can bypass edited CMS content. No ZIP was generated and no live deployment was performed. Phase 5 will provide the dedicated PHP release package, private/public folder mapping, backup, migrations, explicit first import and cPanel routing. Updates must never ship a replacement database, run the importer automatically or overwrite client media. Database snapshots, private config and media must be backed up separately; revisions are not a complete backup.

## Verification

Run from the repository:

```sh
php tests/phase1_core.php
python3 tests/phase1_cli.py
python3 tests/phase1_browser.py
python3 tests/phase2_browser.py
python3 source/check_site.py
python3 source/check_seo.py
composer validate
composer audit
```

Browser suites require local Python Playwright, BeautifulSoup and installed Google Chrome. They create random isolated `edfund_test_*` databases and restricted users, preserve an existing-content sentinel, and drop only their own test database/user. They do not edit `edfund`, owner credentials or current public HTML. Screenshots and test secrets stay ignored under `storage/testing/`.
