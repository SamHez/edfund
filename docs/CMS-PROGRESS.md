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

## Next

Phase 2 is the basic visual page/section editor, draft/published revisions and one-time current-content import. Preserve the extra team profiles and the Phase 1 security foundation. Read AGENTS.md and CMS-PHASE-PROMPTS.md; do not overwrite current manual content when converting templates. Do not package/deploy until the corresponding phase is requested.
