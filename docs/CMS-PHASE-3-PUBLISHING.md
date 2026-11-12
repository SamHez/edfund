# Phase 3 — stories, pictures and YouTube

This phase adds Posts and Media to the existing owner-only CMS. It does not create a release ZIP or change production Apache routing. Keep using the documented local PHP router. Other editable pages, complete synchronized SEO, backup/restore tooling and cPanel cutover remain later phases.

## Setup / existing installation

Keep XAMPP MySQL running. From the EdFund repository, using the separate PHP 8.3+ CLI:

```sh
composer install
php bin/cms.php migrate
php bin/cms.php posts:import
php bin/cms.php media:index
php -S 127.0.0.1:8089 -t public router.php
```

The real local database has already been migrated and its three current static stories imported. Repeating these commands does not replace client content. `posts:import` reads current public article files plus current published homepage card labels/excerpts. It refuses route collisions, preserves existing imported posts, and indexes media references. `media:index` adds usage records for historical page/post revisions and existing static HTML; it never resets content. Neither import nor indexing runs automatically on deployment or an HTTP request. Do not recreate the database or rerun `local:configure`.

The real owner remains unprovisioned unless you have privately created it. Use `php bin/cms.php owner:create`, choose credentials in your terminal, sign in and complete authenticator enrollment. Never put credentials in Git or chat. The local PHP 8.5.7 patch is still for loopback development only; the existing production patch guard remains. Verify patched PHP, GD/WebP, extensions and hosting limits before launch.

## Basic editing

Open `/admin/posts` and choose **New story**. Fill title, excerpt, category and actual author. The stable slug determines `/stories-updates/{slug}/`; changing a title does not change the slug. An optional listing title preserves the existing distinction between card headings and article headings.

Choose a featured image and describe it. Open **Search and sharing details** for SEO title and meta description. An empty SEO field falls back to the title/excerpt. Dates are UTC: leave the first publication date blank to record the real publication time; an existing date is retained when the field is cleared. You may deliberately enter a real past/current date. Future dates and scheduling are not supported. Imported stories retain their existing date; where only a day was supplied, midnight UTC is a date-only representation, not a claimed verified time. Imported publication credits use the known organisation rather than inventing an individual author.

The content area uses a locally bundled Quill 2.0.3 editor, limited to paragraphs, H2/H3, bold, italic, links and numbered/bullet lists. Add separate picture or YouTube blocks and move them up/down. Raw HTML, styles, code, arbitrary iframe input and editor image embeds are not accepted. Picture blocks take a library image, description and caption. YouTube blocks take a supported HTTPS YouTube link, a video title and a local poster image. Videos load only when a visitor selects the poster. No video files or remote image fetching are supported.

**Save draft** keeps the live story unchanged. **Preview** uses the actual public design in an owner-authenticated frame; desktop/mobile widths are available. **Publish** confirms publication. Deliberately changing a previously published slug requires an extra confirmation and creates a permanent redirect from every previous URL directly to the current URL. Reserved previous slugs cannot be assigned to another post.

**Restore as draft** copies an old revision without changing the published story. **Move to trash** removes a story from public listings and returns 410 at its public route. **Restore from trash as draft** returns it to editing without republication; its route returns 404 until Publish. All historical revisions remain retained; the latest 50 are displayed. Stale edits from another tab return a conflict and leave unsaved fields intact.

Published stories populate the homepage/listing. Existing imported article/card HTML is retained until an editor changes publication state. Existing static HTML files and their manually edited source content remain unchanged for comparison. New CMS content is stored only in the database. No historical WordPress articles beyond the three current project stories were imported in this phase.

## Images

Open `/admin/media`, upload a JPEG/PNG/WebP, select it to set its description/caption, and inspect its saved usage references. New uploads are private until referenced by published content. Images are available in post selectors and the homepage section picker; the page picker also offers **Upload and select**. Refresh pictures in an already open post editor after uploading in the Media tab. Changing library metadata does not silently rewrite old page/post revision captions or alt text; change those fields in the relevant draft and publish it.

Current conservative limits adapt down to PHP's actual upload/post/memory limits: at most 2 MiB, 4 million pixels and 6,000 pixels on either side, with a 512 MiB retained-media quota. The local runtime reported 128 MiB memory, 2 MiB upload and 8 MiB POST limits. The Media screen displays the effective limits. Production checks must verify actual browser-handler limits, writable private storage, disk availability and supported GD formats. Do not raise limits without measuring decoding memory. A private `media_quota_bytes` configuration can lower the quota. Restart the local PHP process (or reload the hosting PHP handler/opcode cache) after a private configuration change so the new settings take effect.

Upload validation checks PHP's upload status/provenance, a simple single-extension filename, file size, fileinfo MIME, declared MIME, dimensions and successful GD decoding. Filenames never become storage paths. A random private directory holds the original and three freshly encoded WebP derivatives (large, small, thumbnail). JPEG orientation is applied; public derivatives strip metadata and trailing payloads. Animated input is represented as a still derivative. The private original is never served. Uploaded files are never written to an executable public directory.

Owner thumbnails use `/admin/media-file/{id}/{variant}.webp`, protected by the existing MFA session. Public `/media/{id}/{variant}.webp` checks the current published page/post references on every request and serves only fixed WebP variants with nosniff and a sandbox policy. Drafts, old unpublished revisions and trashed posts cannot by themselves authorize public image access. Removing all public references revokes the endpoint; copies a visitor already downloaded cannot be recalled.

Deletion is soft: unused uploaded pictures can be removed and restored. Pictures referenced by any saved draft, published revision or revision history cannot be removed, so restoration remains possible. Bundled website assets are protected during migration. Retained originals/derivatives continue counting toward the quota; soft deletion does not free disk. Physical retention/purge and full backups belong to the reliability phase. Never delete media files or database records manually to make space.

## Security / developer boundaries

Migration 003 adds post revisions/routes, media details and usage references. It does not alter or clear existing content/auth tables. Publication, pointer updates, media references and audit records commit transactionally. Route changes are serialized, version-checked and collision-protected. Media reference writers and deletion take row locks to prevent a removal/reference race; upload quota accounting uses a private filesystem lock.

The server validates a bounded Quill Delta structure and renders allowlisted tags while escaping every text run and link attribute. No handwritten HTML sanitizer is used. Embed objects, unsupported formats, script URLs, invalid media IDs and excessive block/document sizes are rejected. Server-rendered previews stay session-bound, no-store/noindex and same-origin framed; other admin screens continue denying framing. Uploads, metadata, saves, publishing, trash and restoration require owner authorization and CSRF/origin checks.

Only parsed IDs are stored for YouTube. Supported URL forms are youtube.com watch/shorts/live/embed, youtu.be and youtube-nocookie embed; credentials, non-HTTPS, unexpected hosts/ports and iframe strings are rejected. Existing internal video IDs remain supported for revision compatibility. The application never fetches a supplied video URL. The existing frontend creates its own youtube-nocookie.com iframe on a user click.

Quill is locally bundled only on the private post editor; it never enters the public frontend bundle. Its npm tarball SHA-512 integrity and shipped file SHA-256 values are recorded in `public/assets/vendor/quill-2.0.3/PROVENANCE.json`, alongside its BSD licence. No host-side Node build is needed. Sources: [Quill installation](https://quilljs.com/docs/installation), [Delta structure](https://quilljs.com/docs/delta), [toolbar configuration](https://quilljs.com/docs/modules/toolbar).

The old static ZIP builders remain disabled. Uploading the public folder alone is not a CMS deployment: Phase 5 must switch Apache routing and retire stale static index files so they cannot bypass the database. Never deploy a replacement database, reset imports, copy over private storage or remove client uploads in a code update.

## Verification

```sh
php tests/phase1_core.php
python3 tests/phase1_cli.py
python3 tests/phase1_browser.py
python3 tests/phase2_browser.py
python3 tests/phase3_browser.py
python3 source/check_site.py
python3 source/check_seo.py
composer validate
composer audit
```

Browser suites need local Python Playwright, Pillow, BeautifulSoup and installed Chrome. They use separate disposable `edfund_test_*` databases/users/configuration/storage, never the real edfund content. The Phase 3 suite exercises rich-text and YouTube validation, the real formatting/editor flow, publishing/listings, metadata, slug redirects, conflicts, restoration/trash, upload CSRF and malformed files, dimensions/size/quota failures, private originals/derivatives, reference deletion guards, page upload publication and mobile public layouts. Production host testing remains required; no live deployment or archive is part of this phase.
