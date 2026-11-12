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

## Next

Stop here for this request. Phase 3 adds posts, safe image uploads/library management and YouTube URL parsing. Phase 4 finishes other-page editing, synchronized SEO and backup/reliability tooling. Phase 5 alone packages and performs the authorized cPanel cutover, including retiring static index files. Read AGENTS.md and this progress record before continuing; preserve all manual and CMS edits.
