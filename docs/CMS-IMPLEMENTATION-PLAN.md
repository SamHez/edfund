# EdFund Uganda Foundation — CMS implementation plan

Prepared 12 November 2026. Planning document for the existing EdFund website and its current Apache/PHP/MariaDB hosting. The CMS is proposed work; it has not been implemented or deployed.

## 1. Recommended approach

Build a small, purpose-specific PHP CMS around the existing site design. Keep the public pages server-rendered and fast, and provide a private administration area at /admin/. Use MariaDB for content, accounts, revisions and publishing state. Retain the current CSS, Georgia headings, imagery and frontend interactions where practical.

The client should manage content through labelled fields, a small rich-text toolbar, a media library and approved section templates. They should be able to select a section, fill its fields, reorder it, preview and publish. Layout rules, fonts, responsive behaviour and executable code remain developer-managed. This provides a familiar WordPress-style workflow without adding an unrestricted Elementor-style design surface.

A custom application still needs security updates, backups and a maintainer. Vanilla PHP is not automatically safer than WordPress. The benefit here is a deliberately smaller feature set and fewer integration points, with explicit responsibility for maintaining them.

## 2. Hosting fit and first checks

The supplied screenshot reports Apache 2.4.69, main PHP 8.2.35, available PHP 7.4/8.1/8.2/8.3, and MariaDB 10.11.19. These are reported capabilities, not a completed server audit.

Use the latest provider-patched PHP 8.3 available on this account. PHP 8.2 security support ends 31 December 2026; PHP 8.3 security support ends 31 December 2027. Request PHP 8.4 or a newer supported stable branch if the provider can offer it, with compatibility tests before switching. Do not start this project on PHP 7.4 or 8.1. Agree an upgrade path before 8.3 reaches end of support. Confirm Apache, MariaDB and image-processing packages receive provider security updates as well.

The application needs ordinary PHP web requests, one MariaDB database and writable storage. It does not need a running Node process, Docker, Redis, SSH access, a queue worker or Python on the hosting account. Development tools and Composer can run locally; tested production dependencies ship in the release. A cron job is useful for backup housekeeping and optional scheduling, but publishing immediately must work without cron.

Before implementation, verify on a protected staging installation:

- The actual domain document root and ability to put application/config/storage outside it.
- The PHP version serving web requests, PDO MySQL, sessions, fileinfo, mbstring, OpenSSL and GD with JPEG/PNG/WebP support. DOM/XML may be needed by a chosen sanitizer; Imagick is optional, not required.
- Memory limit, execution timeout, upload/post limits, disk/inode quota and free storage. Set image pixel/file limits from measured memory usage, including decoding and encoding overhead.
- Database privileges, transactions with InnoDB, utf8mb4 and database size limits. Use an application database user without server-wide administrative privileges; separate migration privileges when practical.
- HTTPS, mod_rewrite and allowed .htaccess directives; confirm how to disable execution in any public media directory.
- SMTP credentials and reset-email delivery, provider backups and whether cron is available.
- Host resource limits under a small concurrent request/upload test. Record the results without leaving a public phpinfo or diagnostic page.

If private storage or required upload protections are unavailable, resolve that hosting constraint before enabling uploads. Do not silently weaken the design.

## 3. Client workflow

Navigation: Dashboard, Pages, Blog Posts, Media, Site Settings and Users. Users is available only to administrators.

A typical page edit:

1. Open Home or another assigned page.
2. Select an existing section, or choose Add Section from an approved list.
3. Edit its text, choose images, provide alt text, paste a YouTube URL, or edit a button destination.
4. Reorder using Move Up/Down, with optional drag handles and keyboard support.
5. Save Draft and inspect a desktop/mobile preview.
6. Publish when ready, or restore a previous revision.

Show which page is being edited, its draft/published state, validation errors beside fields, save state and last editor. Keep draft changes separate from live content. Warn before leaving unsaved edits. Detect concurrent edits using a version number and offer a conflict resolution screen rather than silently overwriting another editor's work.

Start with a sidebar form plus live preview of the existing design. Click-to-select a section in that preview is a useful later enhancement; unrestricted inline editing is not necessary for the first release.

## 4. What clients can edit

| Area | Editable fields | Layout protections |
| --- | --- | --- |
| Hero | Slide heading, text, image, focal point, button labels/approved links | Established height, typography and mobile behaviour |
| Who We Are | Narrative, image, caption, mission and vision | Existing layout and card styles |
| Impact figures | Number, label, source/reporting note | Formatting and semantic structure |
| Why Uganda | Chapter labels/text, grouped images and captions | Tab navigation, sticky arrows and group structure |
| Reconstruction stories | Kitchen, dining, dormitory and classroom text; before/after groups | Existing comparison layout and isolated photo modal |
| Image + text | Heading, body, image, left/right arrangement, optional button | Approved responsive layout variants |
| Other sections | Text, image gallery, YouTube video, call to action, team cards | Fixed field schemas and approved appearance |
| Blog listing | Automatically populated published posts, optional featured selection | Pagination and link structure |
| Shared settings | Contact details, social links, navigation and footer content | Validated destinations; no injected scripts |

Each template has a versioned, server-validated field definition. Limit section count, text length, item count and nesting. Permit only sensible section types on each page; keep mandatory page structure and one page-level H1. Add an approved design variation only when there is a real client need.

The initial conversion must cover every section the client expects to maintain. Avoid migrating just the homepage while leaving About, Team or shared footer text dependent on developer code.

## 5. Blog posts

Include title, stable slug, excerpt, featured image, image alt text, category, author, body, SEO fields, actual publication time and modification time. Support Draft, Published and Archived states, revision history, trash/restore and a homepage featured selection.

Use one maintained, locally bundled editor with a limited toolbar: paragraphs, H2/H3 headings, bold, italic, lists and links. Images and YouTube embeds use explicit content blocks. Prefer a validated document structure that the server renders through approved templates. If the chosen editor stores HTML, use a maintained server-side allowlist sanitizer. Browser-side cleaning is not the security boundary. Select the editor after checking its licence and PHP/frontend integration requirements.

Preview requires an authenticated session in the first release and sends no-store/noindex headers. Drafts must never appear in public APIs, the sitemap, previews available to anonymous visitors or the blog listing. Scheduled publishing is a second-stage feature after cron reliability is verified. Comments, user registration, arbitrary forms and plugin installation are outside the initial scope.

Keep current /stories-updates/{slug}/ URLs. A slug change creates a reviewed 301 redirect from the former URL; never automatically rename an existing URL because the title changes.

## 6. Images and YouTube

Media library features: upload, thumbnail grid, filename search, alt text, caption, dimensions, usage locations, focal point selection and safe replacement. Warn before deleting an image in use, including references in published revisions; use soft deletion and retention before removing files.

Accept only a small raster allowlist, initially JPEG/PNG/WebP. On the server, verify upload success, byte limits, file MIME and successful decoding; enforce pixel/dimension limits before processing. Decode/re-encode accepted images, apply orientation, strip unnecessary metadata and generate responsive sizes. Randomize stored names and never turn a submitted filename into a filesystem path. Reject SVG, PHP, HTML, archives and executable content in this editor. Use explicit storage quotas and handle memory/time/disk failures gracefully.

Keep originals and private/draft images outside public_html. Only approved public derivatives may be served directly from an execution-disabled media directory, after verifying the host rules. If that cannot be guaranteed, serve derivatives from private storage through a controlled read-only media route. Database media IDs, not arbitrary path strings, select files. Publication must account for draft-media visibility; a predictable uploaded URL is not a private preview.

For video, accept YouTube URLs only. Parse allowlisted YouTube hosts and supported URL shapes, validate and store the video ID, and generate the iframe in our own template. Do not accept iframe HTML or fetch arbitrary submitted URLs. Use click-to-load youtube-nocookie.com embeds and a local poster image. No video-file uploads or server-side video conversion.

## 7. Application and storage design

Use plain PHP with a small router, controllers/services, PDO repositories, validated content schemas and PHP view templates. Add only a few maintained libraries when needed, such as the rich-text sanitizer, SMTP transport or TOTP implementation. Pin dependencies with a lockfile, review advisories locally and ship vendor files in deployment. The client has no library/plugin installation interface.

Suggested layout:

```text
/home/account/edfund-app/        application, vendor files and templates
/home/account/edfund-private/    credentials, session files, uploads, logs, backups/cache
/home/account/public_html/      index.php, .htaccess and public assets only
```

Use per-environment configuration and keep secrets out of source control and archives intended for sharing. The final deployment package can contain separate private/public folders and a documented mapping to the account layout; the current static ZIP is not a CMS installer.

Suggested data model:

- users, recovery_tokens, authentication_attempts and second-factor recovery records;
- pages and immutable page_revisions, with a pointer to the published revision;
- posts and immutable post_revisions, categories and post-category links;
- media plus usage references and lifecycle status;
- shared_settings with versioned values and the same publishing semantics where needed;
- redirects and an audit log of administrative actions.

Store typed page sections as validated JSON within revisions; keep identity, publication state, slugs and ownership in relational columns. Use InnoDB transactions, foreign keys and unique route/slug constraints. Never deserialize PHP objects from submitted content or include a template path supplied by an editor.

The database becomes the content source of truth. Releases change application code and templates; they do not reseed production text, overwrite media or replace the live database. Schema migrations are versioned and run once. Initial content import is an explicit migration, not an action repeated on every deployment.

## 8. Publishing, caching and reliability

A failed draft save or invalid section cannot affect the live page. Publishing validates a complete revision and atomically changes the published-revision pointer in a database transaction. Unpublishing, deletion and revision restoration go through the same controlled workflow.

Render published content on the server and cache public HTML/fragments using revision-based keys. Invalidate affected pages, shared navigation/footer fragments, listings and sitemap on publication. Write cache files atomically. If cache creation fails, render the published revision directly; avoid a half-written page. Never cache admin sessions, password-reset responses, draft previews or user-specific data in the public cache. A last-known published cache may cover a database interruption, but it cannot guarantee availability if the hosting account itself fails.

Use bounded synchronous operations for the first release. Return useful upload/save errors without corrupting data. Production errors are logged privately; visitors see a generic error page with a request reference. Monitor disk usage and failed jobs. Back up database, originals, approved derivatives and required configuration; keep encrypted copies outside the hosting account. Set retention and recovery targets with the client and demonstrate a restore onto staging before launch. Revisions are not a replacement for backups.

## 9. Security baseline

- Enforce HTTPS. Secure/HttpOnly/SameSite session cookies, strict session mode, session-ID regeneration after login, idle/absolute timeouts and logout invalidation. Keep session files private.
- Use PHP password_hash/password_verify and rehash when appropriate. Prefer supported Argon2id with benchmarked parameters; choose an appropriate supported PHP fallback without silently truncating passwords. Do not implement custom password cryptography.
- Require TOTP two-factor authentication for content-management accounts, with hashed recovery codes and protected/encrypted TOTP secrets. Establish a documented recovery process without a password backdoor.
- Persistent throttling for login, reset and second-factor attempts, keyed appropriately to account and source; generic failure messages. Single-use, hashed, short-lived reset tokens with tested SMTP delivery and invalidation after use.
- Server-side authorization on every action and record lookup. Administrators manage accounts/settings; editors manage assigned content, with publishing permission granted explicitly. Hiding a button is not authorization.
- CSRF tokens for every state-changing request, including uploads; no mutation through GET links. Parameterized database queries, field validation and context-appropriate escaping on output.
- Strict rich-text and URL rules. Reject unapproved link schemes, raw iframe/script/code blocks and arbitrary template inputs. Validate structured document size and depth.
- Private credentials, backups, logs and migration scripts; no 0777 permissions, public installer, debug output or exposed repository files. Restrict writable directories to runtime storage.
- Security headers: nosniff, appropriate referrer policy and a tested Content Security Policy. Admin pages deny framing; same-origin content preview can use an explicitly permitted frame policy. Adapt existing inline code/styles and YouTube allowances before enforcing CSP.
- Audit login/security actions, publishing, deletion and restoration without recording passwords, tokens or SMTP/database secrets. Bound retention and access.

Security acceptance checks include cross-account/role access attempts, stored-XSS payloads, CSRF failures, SQL injection attempts, invalid/double-extension/oversized uploads, path traversal, malformed YouTube URLs, stale reset tokens, MFA recovery, concurrent edits and restoring a backup. Use established PHP primitives and reviewed libraries rather than writing cryptographic or sanitization algorithms from scratch.

## 10. SEO work

The immediate local correction sets the homepage title to:

**ED Fund Uganda Foundation | Education & Digital Skills**

Keep this in the page title, Open Graph title and WebPage structured-data name. Public body content and other manually edited titles are preserved. This change is local; the live host and the previous ZIP do not change until a future deployment.

The CMS should provide page/post SEO title, meta description and social preview image, with sensible defaults. Display guidance, not a misleading SEO score or a rigid character-limit guarantee. Prevent accidental duplicate titles, maintain one meaningful H1 and sensible heading order, and validate internal links and descriptive image alt text.

Preserve current routes, anchors and approved redirects. Inventory old WordPress URLs before cutover and map genuine equivalents; retain post slugs, original publication dates, images and metadata. Do not redirect every missing URL to the homepage. Return real 404/410 responses for removed content where appropriate.

Provide canonical URLs, HTTPS/hostname consistency, XML sitemap with accurate modification dates for published canonical pages only, and robots controls for staging/admin/previews. Protect staging with authentication as well as noindex. Existing local preview files intentionally use noindex; verify the production build/config separately.

Generate accurate NGO/Organization, WebSite, WebPage and BreadcrumbList data. Published blog posts use Article/BlogPosting with a real author, headline, publication/modification dates, representative image and publisher. Do not fabricate review ratings, authors or dates. Social metadata should reflect the actual page, rather than every page using the same image/type.

Retain responsive images, fixed image dimensions, lazy-loading below the fold, local fonts and limited JavaScript. Measure representative pages for performance and accessibility; the CMS must not make public rendering depend on an editor bundle. Verify Search Console ownership/sitemap submission with client access, check indexing and broken links after launch, and compare against a saved baseline. Search engines decide their displayed titles and snippets; metadata improvements do not guarantee rankings.

## 11. Migration and cutover

1. Snapshot current HTML, source, assets, existing redirects and the live database if WordPress remains in use. Treat current edited HTML as authoritative when it differs from the Python generator.
2. Inventory every editable section and import the exact current content into typed records. Preserve all kitchen/dining/dormitory/classroom photo selections, captions and gallery groups. Review the import with the client.
3. Build on protected staging with a separate database. Keep the public static site serving normally during development.
4. Import blog content from an available WordPress export/database, retaining URLs and dates. Review Elementor content manually where it contains design/plugin markup rather than usable article text.
5. Test owner/editor workflows and have the client create a post, edit a page, upload an image and restore a revision without developer help.
6. Take a final backup, pause edits briefly for the final content delta, run migrations and switch the document root/router.
7. The existing .htaccess currently uses DirectoryIndex index.html. Change this to the PHP entry point, and remove/retire stale generated HTML route files during cutover so they cannot bypass the CMS and serve outdated content. Preserve media, intentional static assets and redirects.
8. Verify public pages, status codes, media permissions, canonical URLs, sitemap, authentication and a sample publish on the actual host. Keep a documented rollback to the previous code/content snapshot, including how to preserve any post-cutover client edits before rolling back.

After launch, routine code deployments must leave database content and uploaded media intact. Once migrated, retire the Python static generator as the production content publishing path so it cannot overwrite CMS pages.

## 12. Delivery phases and completion criteria

| Phase | Deliverable | Indicative effort |
| --- | --- | --- |
| 1. Preflight and editor prototype | Hosting checks, content inventory, editor screen using a real EdFund section, frozen MVP scope | 1–2 working days |
| 2. Secure foundation | Routing, migrations, accounts, sessions, permissions, MFA, recovery and audit records | 3–4 days |
| 3. Blog and media | Draft/publish/revisions, editor, media processing and YouTube blocks | 4–5 days |
| 4. Pages and templates | Current layouts mapped into editable templates, preview, reorder and shared settings | 5–7 days |
| 5. Migration and SEO | Exact content import, old-URL mapping, metadata, sitemap and structured data | 3–4 days |
| 6. Hardening and handover | Security/host tests, restore exercise, client testing, deployment and editing guide | 4–5 days |

Planning estimate: approximately 20–27 developer working days, commonly 4–6 calendar weeks with timely client reviews. Re-estimate after preflight and the editor prototype, especially if WordPress recovery/export is difficult. This is not a fixed quotation or a guarantee of host performance.

The first release is complete when the client can independently edit approved page sections, publish/restore a blog post, manage images and add a YouTube video; permission and upload protections pass; published pages preserve the current design and URLs; backups restore successfully; and deployment preserves client edits. Provide a short written guide and a recorded editing walkthrough.

Defer scheduling, public comments, multilingual pages, freeform layout tools, arbitrary plugins and integrations until there is a concrete need. Agree a maintenance arrangement covering security updates, hosting compatibility, backup checks, periodic restore tests and editor support.

## References checked 12 November 2026

- PHP support dates: https://www.php.net/supported-versions.php
- PHP password hashing: https://www.php.net/manual/en/function.password-hash.php
- OWASP authentication: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- OWASP file uploads: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- Google title guidance: https://developers.google.com/search/docs/appearance/title-link
- Google Article structured data: https://developers.google.com/search/docs/appearance/structured-data/article
- Google sitemap guidance: https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap

## Basic scope refinement — 12 November 2026

The client has requested the smallest useful implementation with a visual editing preview. Follow docs/CMS-PHASE-PROMPTS.md for the five delivery phases: one owner account, Pages/Posts/Media/Settings, labelled section fields beside the actual page preview and click-to-select sections. Additional roles, scheduling and freeform layout editing are deferred. The documented security baseline remains required. The local XAMPP edfund database has been confirmed through a read-only Unix-socket query; it was not recreated or modified. Runtime data and credentials must stay outside the repository.
