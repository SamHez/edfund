# SEO and launch

**CMS update (Phase 4):** the PHP CMS now controls SEO, robots, sitemap and publication. Follow [Phase 4 operations](CMS-PHASE-4-OPERATIONS.md) and the client editing guide. The static `publish_ready`/rebuild workflow below is historical and must not be used to publish or overwrite CMS content.

Implemented: unique titles and descriptions, one H1, semantic landmarks, canonical URLs, Open Graph/Twitter metadata, NGO/WebSite/WebPage JSON-LD, Article schema on story detail pages, local font, responsive WebP, image dimensions and alt text, lazy-loaded secondary images, sitemap and robots.txt. No ratings, donor claims or unverified statistics in structured data.

The local demo emits `noindex,follow` on all pages. check_seo.py checks this intended staging state along with all metadata and sitemap membership. Set publish_ready true and rebuild only for an authorised release. It is not a privacy or access-control mechanism.

After a future release, verify canonical host, redirect responses, real 404 and indexability. Verify domain ownership in Search Console, submit https://edfundfoundation.org/sitemap.xml and inspect priority URLs. Measure Lighthouse in the actual hosting environment and monitor Core Web Vitals after field data is available. Local technical checks do not establish indexing or rankings.
