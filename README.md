# EdFund Uganda Foundation

A purpose-specific PHP/MariaDB CMS for the existing EdFund website. Phases 1–4 are implemented and verified locally: owner name/password login, visual page editing, posts/media/YouTube blocks, SEO and encrypted recovery. The Phase 5 cPanel release can now be built and validated; production deployment remains pending confirmed secure hosting access. Nothing has been deployed.

Start with the [client editing guide](docs/CMS-EDITING-GUIDE.md), [local operation and hosting requirements](docs/CMS-PHASE-4-OPERATIONS.md), and [progress record](docs/CMS-PROGRESS.md).

```sh
composer install --no-interaction --prefer-dist
php -S 127.0.0.1:8089 -t public router.php
```

Keep XAMPP MySQL running. Existing local database/configuration are preserved; provision the real owner privately with `php bin/cms.php owner:create` if needed. Open `http://127.0.0.1:8089/admin/login` using the supported project PHP binary.

Build the CMS package with `python3 build_deployment.py`. Download `deployment/edfund-cpanel-ready.zip` and its `.sha256`; read [the installation/cutover/rollback runbook](docs/CMS-CPANEL-RELEASE.md) **before uploading**. Private application code/data belong outside every document root. See [release status and missing private setup](docs/CMS-RELEASE-STATUS.md). Server requests and CLI installation require PHP/MariaDB only; production dependencies are included.

Validate the extracted archive with `python3 tests/phase5_release.py` on this development machine (isolated Apache/CGI and disposable DBs). This is not production verification.

**The static instructions below are historical. Do not rebuild source HTML over manual edits or CMS content. The old static ZIP workflow is retired. The current build_deployment.py creates the PHP CMS package without regenerating HTML or copying runtime data. Production upload/cutover requires confirmed secure target access and host verification.**

## Preview and validate

Run from this project directory:

```sh
python3 source/build.py
python3 source/check_site.py
python3 source/check_seo.py
python3 -m http.server 8088 --bind 127.0.0.1 --directory public
```

Open http://127.0.0.1:8088/. Stop the server with Ctrl+C.

## Edit

- `source/site.json`: organisation, contacts, social links, mission, vision, team, programmes, stories, gallery selections and impact approval flags.
- `source/images.json`: actual image dimensions, alt text, categories and provenance.
- `source/build.py`: shared components, page narratives, metadata and page generation.
- `public/assets/css/styles.css`: locally hosted Manrope, responsive visual system and reduced motion.
- `public/assets/js/main.js`: navigation, slideshow, gallery filtering and accessible native dialog.

Rebuilding replaces generated HTML, sitemap and robots.txt, removing retired routes. Keep lasting edits in the source files. Photos are authentic supplied assets; four portraits come from the current official team page. See `docs/CONTENT-AND-IMAGES.md` for full provenance.

## Demonstration status

`publish_ready` is false: all pages emit noindex. This is not access control. A donation payment destination could not be verified, so giving links lead to clear email enquiries; there is no payment processing or submission form. Headline KPIs reproduce the current official homepage and are visibly attributed to EdFund; metric definitions and reporting periods still need launch confirmation. The proposed SVG identity and privacy wording require client approval.

See `docs/DEPLOYMENT-HANDOFF.md` for the full generated-page list, source changes and future upload instructions. See `docs/VALIDATION.md` for measured checks and limits.

Latest controls: `hero_height_desktop_px` and `hero_height_mobile_px` in `source/site.json`; rebuild after changing. See docs/HERO-AND-CARD-EDITING.md. Homepage videos use click-to-load YouTube players; no provider requests are made before selection.

## Build the cPanel upload ZIP

```sh
python3 build_deployment.py
```

This single command builds fresh HTML for `https://edfundfoundation.org/`, enables production indexing, runs link/asset and SEO checks and validates ZIP integrity. Output: `deployment/edfund-cpanel-ready.zip`. Only one ZIP is written to deployment; source files and local review settings stay unchanged. Requires Python 3 only.

Upload and extract the ZIP directly into the domain’s document root (usually `public_html`). `index.html`, `.htaccess` and `assets/` must be directly inside that root. No wrapping folder or subfolder configuration is needed. Nothing is uploaded automatically.

## Historical PHP CMS plan

The next stage is a basic PHP/MariaDB CMS with a visual section preview and owner-managed pages/posts/media. See [the five phase prompts](docs/CMS-PHASE-PROMPTS.md) and [the implementation plan](docs/CMS-IMPLEMENTATION-PLAN.md). Phase 1 owner authentication is implemented; visual page editing begins in Phase 2. The existing XAMPP database is `edfund`; setup checks and its local socket are documented in the phase guide. Preserve manual content edits and keep runtime data/secrets outside Git.

## Initial local PHP administration

The Phase 1 owner login/dashboard is implemented. See [local setup and recovery](docs/CMS-PHASE-1-SETUP.md), [CMS progress](docs/CMS-PROGRESS.md) and [recovered team profiles](docs/TEAM-PROFILE-RECOVERY.md).

From this checkout, after `composer install`:

```sh
php bin/cms.php owner:create
php -d session.upload_progress.enabled=0 -S 127.0.0.1:8089 -t public router.php
```

Open http://127.0.0.1:8089/admin/login. Use the project-compatible `php` binary, not the old XAMPP PHP executable. The existing local database is configured privately; choose your own owner credentials in the terminal and sign in with name/password. Page editing begins in Phase 2. The static release command above remains the legacy static-site packager; it is not a PHP CMS deployment package. Public CMS hosting requires the supported patched runtime and separate private/public deployment documented for Phase 5.
