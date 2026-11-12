# Apache / Plesk hosting

See DEPLOYMENT-HANDOFF.md for the approval and build sequence. No upload was performed.

Set the intended document root to contain `public/` contents; preserve `.htaccess`. Enable HTTPS for edfundfoundation.org and www.edfundfoundation.org. The rules canonicalise only those production hosts, leaving local preview alone. Enable AllowOverride and relevant modules. Preserve trailing-slash routes and real HTTP 404 responses.

For direct Nginx hosting, configure `index index.html;`, `try_files $uri $uri/ =404;`, `error_page 404 /404.html;` and equivalent per-route permanent redirects listed in `.htaccess`. Do not route missing requests to the homepage.

Check email and telephone links, external social destinations, cache/compression headers, redirect chains and sitemap on staging. Contact links open the visitor’s apps; no sending backend exists. Donation actions currently direct visitors to giving enquiries.

## Ready-to-upload production ZIP

`artifacts/edfund-plesk-ready.zip` contains only document-root files, including `.htaccess`, with no wrapping directory. Generate it with `python3 source/package.py --production`. The command stages a production copy, enables indexing there, runs build/link/SEO checks and validates ZIP integrity. Local configuration and public output remain in their existing review mode.

In Plesk File Manager, back up the existing site, upload the ZIP to the domain’s document root (usually `httpdocs`) and extract it there. `index.html`, `.htaccess` and `assets/` must sit directly in that root. Remove the uploaded ZIP after extraction. Confirm Apache serves `.htaccess` and check HTTPS, redirects and nested pages. No upload or deployment was performed here.

## Preview hosted in /edfund/

For https://swavemedia.co/edfund/, use `artifacts/edfund-subfolder-preview.zip`, generated with:

```sh
python3 source/package.py --base-path /edfund --origin https://swavemedia.co
```

Extract this archive directly inside `httpdocs/edfund/`, replacing the previous site files including hidden `.htaccess`. Do not create a second edfund folder inside it. Asset paths, navigation, responsive images, canonical/social/schema URLs, sitemap and redirects are configured for this location. The preview emits noindex. The CSS font uses a stylesheet-relative URL. The root production archive is for a domain document root and is not the subfolder preview archive.

Diagnosis of the initial unstyled upload: live page returned 200, `/assets/css/styles.css` returned 404, and `/edfund/assets/css/styles.css` returned 200. The original root-only package’s CSS path resolved outside the upload folder. No remote files were changed by the assistant.
