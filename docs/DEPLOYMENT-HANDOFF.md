# Local demonstration handoff

Nothing has been published or deployed. Preview from the project root:

```sh
python3 -m http.server 8088 --bind 127.0.0.1 --directory public
```

## Generated pages

- /
- /about-us/
- /our-work/
- /our-team/
- /stories-updates/
- /stories-updates/teachers-holiday-digital-training/
- /stories-updates/community-digital-training/
- /stories-updates/nabakazi-petralina-digital-learning/
- /gallery/
- /volunteer/
- /contact/
- /privacy-policy/
- /404.html

13 HTML pages; 12 sitemap routes; the error page is excluded. Nine main content routes and three story details.

## Source changes

Rewritten: `source/build.py`, `source/site.json`, `source/package.py`, shared CSS/JS, `.htaccess`, README and existing documentation. Updated validators: `source/check_site.py` (image dimensions and srcset checks), `source/check_seo.py` (validates staging as well as production). Added: `source/images.json`; image inventory, migration, browser results and client confirmation documentation; six original SVG identity assets; 14 supplied photo selections with mobile copies; four official team portraits. Retained unchanged: `source/icons.py`, `source/format_html.py`, local Manrope/OFL. All generated pages, sitemap, robots and SEO report rebuilt. Superseded generated source images and preview assets removed.

## Future deployment, after approval

1. Resolve the confirmation items in CONTENT-AND-IMAGES.md. Confirm payment link ownership and flow before configuring it. Approve copy, logo, imagery and privacy details.
2. Keep `publish_ready` false for local review/staging. For an authorised production release, set it true and rebuild; this switches meta robots to index. A staging build is not access control.
3. Run build.py, check_site.py and check_seo.py. Optionally run `python3 source/package.py` to create local archives under `artifacts/`; this command does not upload anything.
4. Back up the existing production document root, then upload only the contents of `public/`, including `.htaccess`, when deployment is authorised. Do not publish source, docs, originals or source archives.
5. Configure Apache with AllowOverride and rewrite/header/deflate support. Enable HTTPS. For Nginx use equivalent redirects, directory index resolution and a true 404 response; `.htaccess` is Apache-specific.
6. Verify legacy redirects, www/HTTPS, nested routes, assets and true HTTP 404 on staging before switching traffic. Python preview serves pages but does not validate Apache rules or production mail delivery.
7. After release, confirm canonical origin, remove staging noindex only on production, submit sitemap in Search Console and measure real performance. See SEO-LAUNCH.md.

Screenshots: `docs/previews/home-desktop.png` (1440px) and `docs/previews/home-mobile.png` (390px). Validation limits and actual results are in VALIDATION.md.

## Visual update

Additional changes: source/site.json (sourced KPIs, hero slides/timing, gallery preview), source/build.py (full-background carousel, KPI band, About layout, gallery mosaics), shared CSS/JS (Georgia headings, continuous zoom/crossfade, pause/keyboard controls). Updated screenshots cover homepage, About and gallery at desktop/mobile widths. No reference-project files edited; no deployment performed. See redesign-checks.json for the latest browser/a11y results.

## Current release command

Use `python3 build_deployment.py` from the project root. It creates the single root-domain upload archive `deployment/edfund-cpanel-ready.zip` for https://edfundfoundation.org/. The build is staged with indexing enabled, validates all generated pages and ZIP integrity, and preserves local settings. Upload and extract directly into the domain document root (`public_html` on typical cPanel hosting). Source and documentation are excluded. Earlier subfolder preview instructions are historical and do not apply to this release. No deployment performed.
