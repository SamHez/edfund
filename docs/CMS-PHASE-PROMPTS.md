# EdFund CMS — five implementation prompts

Prepared 12 November 2026. Run these prompts in order, one phase at a time. They describe future implementation; creating this guide does not implement the CMS or deploy the website.

## Agreed basic scope

Keep the existing EdFund design and provide one owner account. Add another role only if the client later needs it. The dashboard needs Pages, Posts, Media and Settings. There is no plugin installer, freeform design canvas, public registration, comments, video upload, arbitrary code injection or scheduled publishing in the initial release.

The page editor is visual: a small form panel beside a preview of the actual page. Clicking a section in the preview selects its fields. Text/image changes refresh the preview before publication. Provide a desktop/mobile width toggle, Move Up/Down, Add Approved Section, Save Draft, Preview, Publish and Restore Revision. The first release edits whole sections through fields; it does not need text selection directly inside the rendered page.

Use only existing layouts: hero, text/image, mission/vision, impact figures, Why Uganda chapters, before/after comparisons, gallery, team, YouTube and call to action. Blog listings are populated from published posts. Retain URLs, design, current edited content and image choices, including all reconstruction comparisons.

Use server-rendered PHP and MariaDB, with plain JavaScript for the editor and existing public interactions. Keep dependencies few, pinned, maintained and locally bundled. Do not add a frontend build system, application framework, background worker or third-party CMS service without a demonstrated need. Development dependencies can run locally and be packaged for cPanel; the host does not need Composer, Node or Python.

Keep important protections: password hashing, login throttling, secure sessions, CSRF, owner-only authorization, TOTP/recovery codes, validated rich text, strict image uploads, drafts/revisions, publish isolation and recoverable backups. A small interface is not a reason to remove these protections. Use tested libraries/primitives rather than custom cryptography or a handwritten HTML sanitizer.

## Current local environment

- Repository workspace: the current EdFund checkout; do not touch sibling projects.
- GitHub target: https://github.com/SamHez/edfund.git.
- XAMPP PHP executable: /Applications/XAMPP/xamppfiles/bin/php. Its CLI reported PHP 8.2.4 on 12 November 2026.
- XAMPP MySQL client: /Applications/XAMPP/xamppfiles/bin/mysql.
- Existing database: edfund. It was confirmed through a read-only local socket query on 12 November 2026. No tables or user data were changed.
- MySQL socket: /Applications/XAMPP/xamppfiles/var/mysql/mysql.sock. The first TCP connection to 127.0.0.1:3306 did not connect; the socket did.
- The local socket check succeeded as the local root account. Do not put account credentials into Git or reuse that administrative account in production. Phase 1 chooses an untracked local configuration and, where access permits, a dedicated application database user.
- Relevant PHP CLI modules were present: PDO/pdo_mysql, fileinfo, GD, EXIF, mbstring, DOM, OpenSSL and sessions. Phase 1 must still verify the PHP version/extensions handling browser requests, GD format support and actual resource limits.
- Production screenshot reported PHP 8.3 available. Target a supported, fully provider-patched runtime and arrange the local version/production compatibility check before launch. The older local CLI version is not approval to deploy an outdated runtime.

## Rules shared by all phases

Read AGENTS.md, this guide and docs/CMS-IMPLEMENTATION-PLAN.md first. This guide is the scope refinement: prefer a basic owner-only CMS and visual section forms over optional features in the older plan.

Inspect working-tree changes and preserve all user edits. Work only on EdFund. Never recreate or wipe the edfund database. Check the schema before applying versioned, repeat-safe migrations. Preserve current HTML content when it differs from the generator. Never rerun initial seeding over edited content.

Keep secrets in ignored/private local files. Do not print credentials, commit runtime files or request passwords in chat; explain where the user should enter them locally. Stop dependent work only when a genuinely required value or host capability is missing, while completing independent work.

For each phase, implement the work, run relevant checks, update docs/CMS-PROGRESS.md with what works, what was tested, environment requirements and the next phase, and make a focused commit/push to origin/main when access is available. Preserve unrelated uncommitted changes. Do not force-push. Do not claim a push or deployment succeeded without verifying it.

Complete only the requested phase. Leave later phases as documented next steps. No production deployment or release ZIP during phases 1–4.

## Prompt 1 — Local foundation and secure owner login

```text
Implement Phase 1 from docs/CMS-PHASE-PROMPTS.md in this EdFund repository. Read AGENTS.md and preserve every manual edit. Keep the agreed basic scope.

Inspect the existing PHP/XAMPP environment and connect to the existing edfund database through the documented local socket if needed. Inspect current tables first; never recreate or wipe the database. Check actual PHP browser/CLI versions and extensions, image support and resource limits. Establish a supported-runtime upgrade/compatibility path. If a local value is missing, document exactly where I should enter it in an ignored configuration file; do not put credentials into Git or chat.

Create a small PHP application structure with public entry point, private application/config/storage, PDO connection, repeat-safe schema migrations and environment-specific configuration. Preserve the existing static website for comparison and do not switch production routing. Set up the documented local PHP preview without altering other XAMPP projects or global server settings.

Implement one owner account with secure initial setup, password hashing, CSRF protection, secure sessions, login throttling, TOTP/recovery codes and logout. Provision credentials through a private local setup flow with no permanent public installer or hardcoded account. Provide a documented local recovery process; email recovery only becomes enabled after SMTP is configured and tested. No public registration or role-management screen.

Verify login, failed-login throttling, session renewal/logout, CSRF rejection, MFA and access protection. Leave a working basic admin shell. Document the exact local preview/setup commands and any runtime changes needed from me. Update CMS-PROGRESS.md, commit/push the phase and stop. Do not generate a cPanel ZIP or implement the next phase.
```

## Prompt 2 — Visual page editing and publishing

```text
Implement Phase 2 from docs/CMS-PHASE-PROMPTS.md, continuing the existing EdFund CMS. Read AGENTS.md and CMS-PROGRESS.md, preserve manual edits and verify Phase 1 works before building on it.

Create the basic Pages editor: a small field panel beside an authenticated preview of the actual website. Clicking a rendered section selects its fields; edits refresh the preview, with desktop/mobile widths. Use a validated same-origin preview message protocol and server-rendered templates. Draft previews must not expose unpublished data publicly, run submitted scripts or enter public caches.

Create typed, server-validated section schemas for the site's existing layouts, starting with the homepage and shared settings. Cover hero, text/image, mission/vision, KPIs, Why Uganda, kitchen/dining/dormitory/classroom comparisons, gallery, YouTube, team and calls to action. Allow adding approved sections and moving them up/down. Keep typography, responsive rules and overall layout developer-controlled; no raw HTML/CSS/JS or arbitrary layout fields. Use an existing-media picker initially; uploads come in Phase 3.

Implement separate draft and published revisions, preview, explicit publication, revision restoration and concurrent-edit conflict detection. Import the exact current homepage and shared content once, preserving all latest text, captions, images and URLs. Import must not overwrite subsequent client changes. The PHP frontend must render the published data while drafts remain private.

Verify editing/preview/publication with a real section, ordering, conflict handling, revision restoration, all existing frontend interactions and mobile layout. Ensure code redeployment does not reset content. Update CMS-PROGRESS.md, commit/push and stop. No ZIP or production deployment.
```

## Prompt 3 — Posts, image library and YouTube

```text
Implement Phase 3 from docs/CMS-PHASE-PROMPTS.md in the existing EdFund CMS. Follow AGENTS.md and the progress record, preserving all current content and code edits.

Add a small Posts editor with title, excerpt, stable slug, category, actual author/publication dates, featured image, basic rich text and SEO fields. Include drafts, authenticated preview, publishing, revisions, restore and trash. Use a maintained locally bundled editor with a limited toolbar and server-validated document structure or a maintained server-side HTML sanitizer. Preserve /stories-updates/{slug}/ routes, show published posts in the listing/homepage and create redirects for deliberate slug changes. No comments or scheduling.

Add the media library and integrate it into page/post editing: safe image upload, thumbnails, alt text/captions, usage references, image selection and soft deletion. Allow JPEG/PNG/WebP only, verify content and successful decoding, bound pixel/file/storage limits to the actual host, re-encode and produce responsive derivatives. Keep private/draft originals outside the webroot and make public derivatives non-executable. Never treat user filenames as filesystem paths or overwrite existing media unexpectedly.

Add YouTube blocks accepting validated YouTube URLs only, storing the video ID and generating our own click-to-load embed. No video uploads, iframe input or server fetching of arbitrary URLs. Keep frontend bundles separate from the editor.

Verify the full create/edit/preview/publish/restore post flow, permissions, stored-XSS protection, invalid/oversized/disguised uploads, media deletion safeguards and YouTube validation. Update CMS-PROGRESS.md, commit/push and stop. No ZIP or production deployment.
```

## Prompt 4 — Complete migration, SEO and reliability

```text
Implement Phase 4 from docs/CMS-PHASE-PROMPTS.md. Review AGENTS.md and the progress record, preserving all manual and CMS-edited content.

Finish converting every agreed editable page and shared setting, including About, Our Work, Team, Volunteer, Contact, navigation and footer. Use the exact current content and approved layouts. Preserve existing blog routes, anchors, reconstruction image order, modal groups, hero interactions, sticky controls and mobile behaviour. If an old WordPress export is available, import its agreed content/URLs once without resetting existing CMS data. List any missing export explicitly rather than blocking the current-site migration.

Complete technical SEO: homepage title starts ED Fund Uganda Foundation | Education, unique editable titles/descriptions, canonical URLs, correct status codes and redirects, page-specific social images, published-only sitemap with accurate dates, robots settings, Organization/WebSite/WebPage/Breadcrumb/Article data as appropriate and heading/alt-text rules. Keep drafts/admin/staging private and noindex. Retain current responsive images and frontend performance.

Complete bounded public caching, atomic publishing, private errors/logging, audit records and backup/restore tooling compatible with ordinary cPanel hosting. No cache of authenticated or draft pages. Code/schema updates must preserve content/media. Test a restore into an isolated local database, never overwrite edfund while exercising recovery.

Run relevant public-page, publishing, permissions, upload, security, mobile, accessibility and SEO checks. Verify the owner can edit a page and publish a post without code changes. Provide a short editing guide, local run instructions and a concrete list of any cPanel requirements still needed. Update CMS-PROGRESS.md, commit/push and stop. No production ZIP or deployment yet.
```

## Prompt 5 — cPanel release, deployment and handover

```text
Complete Phase 5 from docs/CMS-PHASE-PROMPTS.md for EdFund. Follow AGENTS.md and CMS-PROGRESS.md, inspect the current implementation and resolve any launch-blocking failures before deployment.

Prepare a production release for https://edfundfoundation.org/ at the domain root. Confirm the cPanel account/document root, supported patched PHP version, required extensions, database details, HTTPS, writable private storage, media execution protection, SMTP where used and backup access. The production database/user must be created in cPanel if they do not already exist; explain the exact fields I need to enter privately. Do not reuse XAMPP credentials, embed secrets in the archive or print them in chat.

Build and test one cPanel-ready release ZIP with clear public/private folder mapping, required production dependencies, migrations, an explicit one-time content import and installation/upgrade instructions. Include a deployment preflight, backup, validation and rollback process. Never reset an existing production database or replace client uploads/content during upgrades. Do not carry demo credentials or a usable public installer into the release.

This phase requests deployment to the confirmed cPanel target when access is available. Before cutover, verify staging, a recoverable backup and database migrations; then perform the documented deployment and migrate the agreed content. Replace the old DirectoryIndex/index.html routing with the PHP entry point and retire stale generated HTML route files so they cannot bypass CMS content. Preserve existing URLs and redirects. If an indispensable credential, target or access method is unavailable, complete the release ZIP/runbook and identify precisely what is still needed; do not claim the live site is deployed.

Verify the live homepage, reconstruction sections, posts, media, redirects, sitemap, canonical URLs, owner login/MFA, draft privacy and an actual edit/publish/restore cycle. Verify the release works without server-side Node, Composer, Python or a worker. Check that published content survives a code update. Configure/test available backups and document any provider tasks the client must perform.

Finish with the validated ZIP link, deployment result/URL, private credential setup instructions, editing guide, backup/restore instructions, maintenance requirements and rollback details. Commit/push final source/docs without credentials, database exports or runtime uploads. Do not say completion/deployment succeeded for any step that remains unverified.
```

## What the user will need

For phases 1–4, the existing edfund database is sufficient. Keep XAMPP services available and provide any changed local connection details through an ignored local configuration file when requested. The initial read-only database check already succeeded via its socket.

For phase 5, production is a separate environment: cPanel access through an available secure method, confirmation of the domain document root, a production database and assigned application user, a patched supported PHP version, storage/backup access and SMTP settings if email recovery is used. cPanel commonly prefixes database/user names, so production names may differ from edfund. Enter secrets privately in the host configuration; do not paste them into a prompt or commit them.

If migrating historical posts beyond the current project, provide a WordPress export or agreed source backup when available. Search Console access is needed only for verification/submission tasks that require that account.
