# CMS progress

Updated 12 November 2026.

## Phase 1 — implemented and verified locally

- Small PHP 8.3+ application entry point, development router and private app/config/runtime directories. Existing static website and production Apache routing preserved; no Phase 2 editor implemented.
- Existing XAMPP edfund database inspected before changes (empty). Added five cms_ tables through checksum-tracked, locked, repeat-safe migrations. Dedicated randomly named local runtime user limited to CMS table DML; migration credentials separate and private.
- Single-owner private CLI provisioning, Argon2id/native password hashing, strict session cookies and expiration, CSRF/origin validation, persistent password/MFA throttles, encrypted TOTP secrets, accepted-step replay rejection, one-time hashed recovery codes, forced re-enrollment on recovery, session-version revocation and CLI account reset.
- Basic dashboard, account-security/recovery-code screen and authenticated system-status page. Admin/preauth responses are no-store/noindex with restrictive headers. SMTP reset and public owner setup/registration are absent by design.
- Four full published team biographies recovered offline from the supplied WordPress SQL and exposed through accessible team-card dialogs. Existing card names, roles, portraits and other manual page content preserved. No WordPress database/account import.
- Pinned OTPHP and three small supporting dependencies; composer.lock targets PHP 8.3 compatibility. Composer audit reported no advisories at verification time.

## Verification completed

- Core PHP security checks passed: native hashing, secret encryption/tamper/wrong-key rejection, TOTP and replay, malformed inputs and random recovery codes.
- Private CLI bootstrap and concurrent-rate test passed: owner creation works through hidden terminal prompts, a second owner is refused, and eight simultaneous bad-password requests reserve at most six attempts.
- Full browser integration suite passed against an isolated, disposable MariaDB test database. Includes migration/content preservation, table-scoped runtime privileges, single-owner guard, CSRF/origin denial, header/cookie checks, throttles, session rotation, partial-auth denial, enrollment, recovery-code rotation, TOTP replay rejection, one-time recovery/re-enrollment, CLI reset and stale session/code rejection.
- All four recovered team biographies match extracted text, with working native dialogs, focus return, private-path denial and no-JavaScript fallback. Tested at 1440/390/320px.
- Public site checks: 13 HTML pages, 586 links, 12 SEO routes; no errors at the last run. Browser runtime and CLI use PHP 8.5.7; local database MariaDB 10.4.27. PHP 8.3 was not available for direct runtime testing; Composer compatibility is pinned to 8.3.
- No deployment ZIP generated, no live deployment and no global XAMPP settings altered.

## Needed from the owner

1. Run `php bin/cms.php owner:create` privately from the repository, choose the real owner email/name/passphrase, then complete authenticator enrollment and save recovery codes.
2. Start the local preview as documented in CMS-PHASE-1-SETUP.md. Keep XAMPP MySQL running; use the documented Unix socket configuration.
3. Update to a currently patched supported PHP runtime before public hosting. The local 8.5.7 and XAMPP 8.2.4 installations are older than current security fixes. The production configuration guard refuses outdated runtimes; provider version/extensions/HTTPS/storage checks remain Phase 5 requirements.

No passwords, MFA keys, recovery codes, private configs, database exports, runtime files or release archives belong in Git.

## Phase 2 — implemented and verified locally

- Owner-only Pages/Settings editor with existing website preview beside labelled section fields. Click-to-select, nested field groups, searchable existing-media picker, desktop/mobile widths, add approved existing layouts, move up/down and explicit removal. The first hero and shared structure stay protected; typography/markup remain developer-controlled.
- Typed, bounded server-owned field schemas cover the current homepage hero, Who We Are and mission/vision, KPIs, videos, Why Uganda and all kitchen/dining/dormitory/classroom comparisons, focus/featured content, stories, gallery and calls to action. Approved additive layouts also include a team card. Inputs contain plain escaped text, validated destinations/video IDs and registered image IDs; arbitrary HTML/CSS/JS, template paths and uploads are not accepted.
- Separate immutable draft/published revision snapshots, atomic publication/audit, version conflict refusal, unsaved-leave warning and restoration into a new draft. Removed sections remain restorable. Preview is MFA-authenticated, session-bound, no-store/noindex, ten-minute expiry/five-token limit, with origin/source-validated section-selection messages and same-origin framing only.
- Explicit repeat-safe import snapshots the current rendered public/index.html rather than regenerating from Python. The real edfund database now has nine tables, two imported documents and two original import revisions; existing authentication data preserved. No owner credentials were created. Published shared contact/header/footer changes appear across local PHP-served existing page bodies; those page bodies remain for Phase 4 migration.
- Homepage/manual content and asset files were not rebuilt or reverted. The exact headings, narrative, captions, image ordering, alt text, responsive sources/sizes and destinations match the current HTML baseline. Four recovered team biographies remain intact.
- HTTP requests and code updates never import/reset content. Explicit layouts:sync registers developer-approved layout versions without replacing revisions. Legacy static ZIP builders now refuse to run because their static output could bypass CMS-managed content. Production Apache routing remains unchanged; no ZIP or live deployment.
- PHP DOM is an explicit extension dependency; no additional third-party package was needed. Composer lock retains PHP 8.3 compatibility. Runtime verification remains PHP 8.5.7 / MariaDB 10.4.27 locally, with the existing production patch guard.

## Phase 2 verification

- Phase 1 core, private CLI/concurrent throttling and full browser security regression suites pass.
- Phase 2 service/browser checks pass in disposable isolated databases: current-content equality, repeat import, escaped script-like text, unknown-field/media/layout and unsafe-URL refusal, mandatory H1/hero, all eight approved additive layouts, draft isolation, explicit publish, stale conflict, restore/publish round trip including removed sections, and preservation across layout/code registration and repeated import.
- Real Chrome owner workflow passes: clicking sections, changing text, editing again after Save, image picking, ordering, adding a section, desktop/mobile preview, save/publish/restore, private preview denial to anonymous visitors, forged message rejection, independent shared publication and invalid-publication rejection.
- PHP-rendered hero arrows, Why Uganda chapter navigation/grouped lightboxes, recovered team modals, YouTube click-to-load and mobile navigation/layout at 390/320px pass. Static link/asset and SEO checks still pass (13 HTML pages, 586 links, 12 SEO routes). Composer validation/audit pass; the existing unselected project-licence warning remains informational.
- See CMS-PHASE-2-EDITOR.md for exact commands, editing instructions, security/preservation details and the deployment boundary. No PHP 8.3 runtime was available for direct execution; production compatibility/host validation remain required.

## Needed from the owner after Phase 2

The real owner is still unprovisioned. Run `php bin/cms.php owner:create` privately, choose real credentials, then complete authenticator enrollment and save recovery codes. Keep XAMPP MySQL running and start `php -S 127.0.0.1:8089 -t public router.php`; open `/admin/pages`. The real local database has already been migrated and imported; do not reset it. Upgrade to a currently patched supported PHP runtime before public hosting.

## Phase 3 — implemented and verified locally

- Added basic Posts/Media navigation, post list and labelled post editor with an actual authenticated website preview. Locally bundled Quill 2.0.3 provides limited paragraph/H2/H3/bold/italic/link/list formatting; separate picture and YouTube blocks can be added, moved and removed. Excerpt, listing title, stable slug, category, actual author/date, featured picture/alt/caption and SEO fields are editable.
- Post drafts/publications have separate pointers, immutable revisions, optimistic version checks, transactional publishing and audits, restoration into a draft, trash and restore-from-trash without republication. Deliberate slug changes require confirmation and retain direct 301 redirects; previous URLs remain reserved. Published posts feed the homepage and story listing. Future scheduling/comments remain absent.
- Explicit repeat-safe posts:import recovered the current three static articles, their source links/text, existing card labels, image selections/captions, metadata, publication dates and routes. Original imported article/card HTML remains in use until publication state changes. The source HTML/content files and public CSS/JavaScript were not regenerated or reverted. No bulk historical WordPress post migration was performed.
- Secure image library supports JPEG/PNG/WebP uploads, thumbnail search, descriptions/captions, readable usage references, selection, soft deletion and restoration. It is integrated into post fields/blocks and the existing page picker with Upload and select. Existing bundled assets and all saved revision references are protected from removal.
- Uploads require owner/MFA/CSRF/origin checks, correct upload provenance/status, a simple single extension, matching declared/fileinfo/decoded MIME, bounded bytes/dimensions/pixels and successful GD decode. Random private directories hold originals and re-encoded WebP derivatives; no uploaded file is written to an executable public folder. Public delivery checks current published references on every request; originals are never served and drafts/trashed posts cannot authorize access. Quota writes and media reference/deletion checks use locks.
- Effective limits adapt down to actual PHP settings: up to 2 MiB, 4 million pixels, 6,000 pixels per side, default 512 MiB retained storage. Local PHP reports 128 MiB memory / 2 MiB upload / 8 MiB POST. Private quota configuration can lower the cap. Provider limits and safe media delivery still require final hosting verification.
- YouTube links are parsed against fixed HTTPS hosts/paths, normalized to IDs and rendered through our own local poster/click-to-load privacy-enhanced player. Raw iframe/code/video uploads and arbitrary URL fetching are absent. Existing page video-ID revisions remain compatible; their editor fields now accept YouTube links.
- Migration 003 only adds five tables. The real edfund database now has 14 tables, its original two page revisions, three imported stories/three import revisions, no uploaded test files and no owner credentials. HTTP requests/deployments never import/reset data. Runtime images/configuration, fixtures and credentials remain ignored.

## Phase 3 verification

- All Phase 1 core/CLI/MFA/security/browser regressions and the complete Phase 2 visual-editor/content-preservation suite passed in isolated databases.
- Phase 3 service tests pass: repeat-safe current-story import, supported/unsafe YouTube URLs, allowlisted Delta formatting and escaped script-like text, embed/style/script-link rejection, draft isolation, concurrency conflicts, actual publication timestamps, explicit slug confirmation/reserved routes, restored revisions and trash restoration, and preservation of client revisions across repeated import.
- Real Chrome tests pass for the locally bundled formatting toolbar, private preview, featured image/SEO fields, publishing into public article/home/listing, stale/invalid writes, slug redirects, revision restore, trash/untrash, image metadata management, homepage image upload/preview/publication and 390/320px public layouts.
- Upload tests cover CSRF, disguised/double extensions, PHP/SVG/archive payloads, corrupt/truncated content, MIME mismatch, oversized files, pixel bombs, lowered storage quota, valid JPEG/PNG/WebP, unique non-overwriting storage, safe re-encoding of trailing payloads, inaccessible originals/private paths and anonymous draft-image denial. Saved-reference deletion is refused; unused uploaded pictures can be softly removed/restored. Public image access is revoked when the only publishing story is trashed.
- Static site link/asset and SEO checks still pass (13 HTML pages, 586 links, 12 SEO routes). The real imported homepage still matches all original headings, paragraphs, captions, image selections and destinations. Composer audit reports no advisories; Quill tarball/file integrity and licence are bundled. Runtime remains PHP 8.5.7 / MariaDB 10.4.27; PHP 8.3 compatibility is pinned but not directly runtime-tested here.
- See CMS-PHASE-3-PUBLISHING.md for setup, client editing instructions, measured limits, storage/privacy behavior, verification and remaining deployment constraints. No ZIP generated, no live deployment and no sibling project/global XAMPP changes.

## Needed from the owner after Phase 3

The real owner must still run `php bin/cms.php owner:create` privately and complete authenticator enrollment if not already done. Keep XAMPP MySQL running; start the documented local PHP router and open `/admin/posts`, `/admin/media` or `/admin/pages`. Migration/current-story import/media indexing have already been performed locally. A patched supported PHP runtime and final host/storage checks remain required before public deployment. Do not upload the public folder alone or use the retired static ZIP builders.

## Next

Stop after Phase 3 for this request. Phase 4 finishes all other editable pages, synchronized site-wide SEO, caching and backup/restore reliability. Phase 5 alone builds the CMS release package and performs the authorized cPanel routing/cutover. Preserve all manual and CMS changes; do not reset existing imports or storage.
