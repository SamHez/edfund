# Phase 1: local setup and account recovery

Completed 12 November 2026. This is the secure foundation and owner dashboard. Page editing is Phase 2; production hosting/cPanel packaging is Phase 5. Public pages continue using the existing HTML and Apache routing.

## Run the configured local project

From the EdFund repository:

```sh
composer install --no-interaction --prefer-dist
php bin/cms.php owner:create
php -d session.upload_progress.enabled=0 -S 127.0.0.1:8089 -t public router.php
```

Open http://127.0.0.1:8089/admin/login. The public site is at http://127.0.0.1:8089/ and recovered biographies are on /our-team/.

Use `/usr/local/bin/php`, currently PHP 8.5.7, or another supported project-compatible PHP 8.3+ binary. The XAMPP PHP executable is 8.2.4 and does not meet this application's minimum. The database can still be the existing XAMPP MariaDB service reached through its Unix socket. The dev router serves this project only; no global XAMPP/Apache configuration was changed.

The real owner account has deliberately not been preassigned a developer-chosen email/password. Run owner:create in your own terminal. It asks for name, email and passphrase, with password echo disabled. Do not paste credentials in chat, shell arguments or Git. The command refuses a second owner. If you interrupt it, no partial owner is committed.

After password sign-in, add the displayed key manually to your authenticator (issuer EdFund Uganda Foundation, time-based, SHA-1, 6 digits, 30 seconds). Confirm a current code, then save the ten recovery codes in your password manager. They appear only until acknowledged, for at most ten minutes. No key is sent to an external QR service.

Use the same clock/time synchronization on the server and phone. The login accepts the adjacent time step for ordinary clock drift but refuses reuse of an accepted step. Use a fresh code when regenerating recovery codes.

Stop the local server with Ctrl+C. Bind it to 127.0.0.1 only. Do not expose this local development server over a public tunnel or network interface.

## Database/configuration already prepared in this workspace

The existing `edfund` database was inspected before changes: it contained no tables. Only these additive tables were created:

- cms_migrations
- cms_users
- cms_recovery_codes
- cms_auth_attempts
- cms_audit

No WordPress dump was imported, and no existing database/table was recreated, truncated or deleted. A randomly named local runtime user was granted DML only on these CMS tables (SELECT only for cms_migrations). It has no schema-wide CREATE, DROP or ALTER rights.

Private configuration is in ignored `config/local.php`; CLI migration credentials are separately in ignored `config/migrations.local.php`. Files are mode 0600. Storage/sessions/logs are outside public in ignored `storage/`, with private directories. Never commit these files. Back up the app key securely along with the eventual database backups; it protects MFA secrets and recovery-code digests.

For a fresh checkout on the same local environment, run:

```sh
composer install --no-interaction --prefer-dist
php bin/cms.php local:configure
php bin/cms.php migrate
php bin/cms.php preflight
php bin/cms.php owner:create
```

local:configure requires that the database already exists. It prompts privately for a local DB administrator, runs only cms_ migrations and creates a dedicated runtime user. The --defaults option is for this known local XAMPP setup with its local default administrator; it is not a production setup mode. Existing private configuration is not overwritten.

For a different environment, use config/local.example.php as the field guide and provide a separate private migration configuration with dsn/user/password. Set EDFUND_CONFIG or EDFUND_MIGRATION_CONFIG only in the trusted process environment if using another private path. These paths are never selected from a web request. The base_url is an origin without a path, and must match the preview host and port. Do not display private configuration contents in terminal output or commit them.

Migrations are numbered SQL files with SHA256 checksums, run under a database advisory lock. Reruns skip matching applied versions; modifying an applied file raises an error. MariaDB DDL is not transactional, so migrations use repeat-safe CREATE IF NOT EXISTS statements and are recorded only after completion. Use new migrations for schema evolution. Runtime content is never seeded by normal application startup.

## Recovery

### Lost authenticator, password still known

Sign in with the password and an unused recovery code. It is consumed atomically, previous sessions are revoked and the account must enroll a new authenticator before reaching the dashboard. Enrollment replaces the remaining old recovery codes with a new set. Save the new codes; old codes will no longer work.

### Lost password or no recovery codes

A maintainer with legitimate filesystem/CLI access can run:

```sh
php bin/cms.php owner:recover
```

Type RESET when asked and choose a new passphrase privately. This updates the existing owner, revokes sessions and pending sign-ins, deletes old recovery codes and requires fresh authenticator enrollment. It does not provide a public bypass or print credentials. Store host/CLI access securely.

Email password recovery is disabled until SMTP is configured and tested in a later agreed phase. There is no public reset token/installer endpoint in Phase 1. Login and MFA throttles persist in MariaDB for fifteen minutes, including across browser restarts.

## Current runtime findings and production requirements

Web-request and CLI checks used PHP 8.5.7 with PDO MySQL, OpenSSL, sessions, fileinfo, mbstring, GD JPEG/PNG/WebP and EXIF available. CLI reported 128M memory, 2M upload_max_filesize, 8M post_max_size and execution time 0; actual host limits need rechecking before uploads in Phase 3. Local MariaDB reports 10.4.27, separate from the supplied production-host screenshot showing 10.11.19.

This local PHP patch predates PHP's 21 October 2026 security fixes. Update before any public hosting; the required minimum security floors in the current preflight are 8.3.36, 8.4.27 or 8.5.12, and the provider must confirm all current patches. Production mode refuses to run below these floors and requires HTTPS, a matching fixed origin and secure cookies. Vendor backports without a version bump need explicit provider verification and a reviewed policy adjustment; do not bypass the guard blindly. PHP 8.3 runtime execution was not available here; dependencies are locked against PHP 8.3, while actual browser/service tests ran on PHP 8.5.7.

Reference: https://www.php.net/archive/2026.php#2026-10-21-5 and https://www.php.net/supported-versions.php.

The local MariaDB service and old XAMPP PHP are development facilities; do not expose them as the production CMS. No global package upgrade, hosting change or website deployment was performed.

## Verification

```sh
php tests/phase1_core.php
python3 tests/phase1_cli.py
python3 tests/phase1_browser.py
composer audit --no-interaction
python3 source/check_site.py
python3 source/check_seo.py
```

The browser suite needs Python Playwright and the existing Google Chrome application. It creates a randomly named isolated edfund_test_* database and runtime user, verifies an existing-content sentinel survives migrations, runs auth/recovery tests, then drops only that newly created test database/user. It never creates a real owner in edfund. Secrets remain in private temporary fixture files and are removed after tests; they are not printed.

Coverage: authenticated encryption/tampering, passwords, login/MFA throttling (including eight concurrent password attempts bounded to six reservations), TOTP replay, CSRF/origin rejection, cookie flags/session renewal, protected routes, one-time codes, mandatory re-enrollment, private CLI reset, old-session rejection, private-path denial and all four team modals at desktop/mobile widths with no-JavaScript fallback. This is application verification, not an independent penetration test or a guarantee about untested hosting configuration.
