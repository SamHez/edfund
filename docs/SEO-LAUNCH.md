# SEO and launch

**CMS update (Phase 4):** the PHP CMS now controls SEO, robots, sitemap and publication. Follow [Phase 4 operations](CMS-PHASE-4-OPERATIONS.md) and the client editing guide. The static `publish_ready`/rebuild workflow below is historical and must not be used to publish or overwrite CMS content.

Implemented: unique titles and descriptions, one H1, semantic landmarks, canonical URLs, Open Graph/Twitter metadata, NGO/WebSite/WebPage JSON-LD, Article schema on story detail pages, local font, responsive WebP, image dimensions and alt text, lazy-loaded secondary images, sitemap and robots.txt. No ratings, donor claims or unverified statistics in structured data.

The local demo emits `noindex,follow` on all pages. check_seo.py checks this intended staging state along with all metadata and sitemap membership. Set publish_ready true and rebuild only for an authorised release. It is not a privacy or access-control mechanism.

After a future release, verify canonical host, redirect responses, real 404 and indexability. Verify domain ownership in Search Console, submit https://edfundfoundation.org/sitemap.xml and inspect priority URLs. Measure Lighthouse in the actual hosting environment and monitor Core Web Vitals after field data is available. Local technical checks do not establish indexing or rankings.

## November 2026 polish
Live pages now also emit: robots `max-snippet`/`max-video-preview`, `og:locale`, `theme-color`, square favicons (`/favicon.ico`, 192px PNG, Apple touch icon), optional Google/Bing site-verification meta tags from `edfund-data/config.php`, and richer NGO structured data (alternate name, description, Eastern Uganda service area, Kamuli address). Canonical host/HTTPS redirects, one H1 per page, unique titles/descriptions, sitemap with `lastmod`, robots pointing to the sitemap and a real 404 were already in place. Production sets `public_indexing` to true so pages emit `index,follow`; staging keeps `noindex`.
