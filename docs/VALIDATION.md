# Local validation — 27 October 2026

## Build and static checks

- `python3 source/build.py`: 13 HTML pages, including the error page. All organisation pages and three original-source story summaries rebuilt.
- `python3 source/check_site.py`: 539 links checked; zero errors. One title/H1/main per page, unique IDs, image alt text and dimensions, internal links/fragments, local assets and responsive srcset targets checked.
- `python3 source/check_seo.py`: 12 content pages; zero errors. Unique metadata, canonical/social URLs, sitemap membership, structured data and intended local noindex state validated. Error page excluded from sitemap.
- Six SVG identity assets parse as valid XML. All selected image copies decode; photograph originals were not altered. Image folder totals approximately 2.6 MB across all selected photos, responsive variants and identities (not the transfer size of any one page).

## Browser checks

Chrome with Playwright, using an isolated temporary profile. `browser-checks.json` records 48 route/viewport combinations: all 12 content pages at 320, 390, 768 and 1440 pixels. All returned 200; zero horizontal overflow, broken images or JavaScript page errors.

Verified: mobile menu opens/closes; Escape closes and restores focus; manual slideshow selection; gallery category filtering; native dialog opens and closes with Escape and returns focus to its trigger; contact telephone links. Final checks also cover reduced-motion default pause, explicit slideshow opt-in and a no-JavaScript 320px navigation fallback.

Homepage screenshots captured with all images decoded, every section scrolled into view for painting, local font ready and reduced motion enabled. Files in `docs/previews/`: full desktop/mobile and first-screen desktop/mobile. First captures with incomplete offscreen lazy loads were replaced.

Seven representative route/viewport axe audits passed with zero WCAG 2 A/AA or 2.1 AA violations and zero JavaScript page errors; full findings and any checks requiring manual review are recorded in `accessibility-checks.json`. These automated scans and keyboard checks do not constitute a complete assistive-technology audit. White text actions and the final CTA use #11766E, a darker teal with accessible contrast; the requested base teal remains in the visual palette.

## Limits

No production deployment, live redirect execution, mailbox delivery, payment flow, Google indexing or field performance tested. Python preview does not execute Apache rules. Lighthouse is not installed; no Lighthouse score is claimed. Public launch requires the confirmation items in CONTENT-AND-IMAGES.md and an authorised production rebuild. All content pages currently emit noindex because this is a local demo.

## Georgia / slideshow / gallery update

Rebuilt 13 pages and validated 539 links with no errors. Updated homepage, About and gallery tested at 320, 390, 768 and 1440px: no horizontal overflow, broken images or console errors. Twelve automated axe WCAG A/AA audits passed without violations. Additional final checks cover every content page at 320px and the final photographic About banner. Latest results: redesign-checks.json and redesign-final-checks.json. Earlier browser reports describe the preceding design.

Verified slow image zoom by comparing computed transforms over time; verified autoplay continues with pointer over hero, manual slide selection, arrow-key navigation, outgoing-slide inert state, pause state, reduced-motion default pause, gallery filters and modal focus restoration. All three slide copy blocks fit above their controls on desktop and mobile. Screenshots were refreshed after all image decoding and section painting.

The KPI strip uses the current official homepage’s counter endpoints, matching the client-supplied screenshot: 2,500+, 100+, 200+, 5+. These are visibly attributed organisation-reported figures. The reference project was only read, never modified. Georgia is a system font, so there is no external font request. Manrope remains locally hosted for body/navigation. No deployment.

## Compact slider and content revision

Latest build: 13 HTML pages, 555 links and 12 SEO-checked content routes. Slider height is controlled in site.json (560px desktop / 610px mobile); visible pause button removed. Who We Are, mission and vision panels use client-supplied content. Homepage videos load only on selection. Every inner page has a concise photo banner. Latest responsive, accessibility, animation and video-wiring results are recorded in compact-layout-checks.json; earlier reports describe previous revisions. Live YouTube playback, restrictions and availability remain provider-dependent; local video tests isolate the provider and validate iframe URLs, titles and direct-link fallback.

Final result: 48 layout combinations passed with no missing images or overflow; 12 automated WCAG A/AA audits found no violations; no JavaScript page errors. Desktop/mobile hero measurements and all three slide/control spacing checks passed. Both video players are created only on click and retain direct YouTube links. Screenshots refreshed for homepage, About and Contact.

Latest slider scale adjustment: desktop height reduced from 560px to 504px; mobile from 610px to 550px. Headline sizes and desktop supporting text/buttons reduced approximately 10%, using ordinary CSS at 100% browser zoom. Build, 555-link check and 12-page SEO validation pass. See slider-scale-checks.json for four-width, all-slide spacing checks.

## Why Uganda photo story — 31 October 2026

Four chapters preserve client image groups 1/2/7, 3/4, 6/10, 8/9/5. Ten source checksums verified unchanged; twenty optimised WebP variants added (approximately 2.5 MB total across desktop and mobile copies). Build, 556-link check and 12-page SEO check pass. Sixteen chapter/viewport combinations at 320, 390, 768 and 1440px pass, with no page overflow, missing assets or JavaScript errors. Keyboard Arrow/Home/End, previous/next wrapping, visible-panel state, mobile photo scrolling and no-JavaScript fallback verified. Narrative sits below the grouped images in every chapter. Results: why-uganda-checks.json. Section screenshots: docs/previews/why-uganda-{1,4}-{desktop,mobile}.png. No new automated WCAG audit was run for this revision.

Why Uganda sizing refinement: four tab titles enlarged to 20px desktop, 17px tablet and 14px mobile. Chapters 02/03 use cover cropping with a 55% vertical focal point; responsive candidates sized for their two-column layout. Sixteen chapter/viewport checks at 320/390/768/1440px pass with no overflow or console errors. See why-uganda-sizing-checks.json. The root-domain cPanel ZIP was rebuilt successfully (13 pages, 556 checked links, 12 SEO routes).

## Why Uganda split layout, KPI placement and footer — 9 November 2026

Sixteen chapter/viewport checks at 320, 390, 768 and 1440px pass. Desktop/tablet text and photography align side by side; mobile text leads above a consistent collage. No horizontal overflow, missing requests or JavaScript page errors. Previous/next wrapping, Arrow/Home/End navigation, focus handling and no-JavaScript visibility verified. Source paragraphs remain unchanged in meaning. Checks confirm the homepage sequence is Hero → Who We Are → KPIs → Videos. Footer uses exactly the header’s supplied PNG and a computed white background. See why-uganda-redesign-checks.json. Updated review screenshots cover all four chapters at desktop/mobile widths, homepage and footer. No new full automated accessibility audit or field performance measurement was performed.

## Hero photograph and edge controls — 9 November 2026

First slide now uses the supplied HERO MAIN PICTURE.jpeg via responsive WebP copies, with an upper focal position on desktop to show the school buildings. First-image preload follows site.json rather than a hardcoded prior photo. Original JPEG checksum verified unchanged. Previous/next arrows tested at 320/390/768/1440px, including wrapping, native Enter/Space activation and all three slides staying clear of controls. No overflow or JavaScript errors. Source/metadata checks pass. See hero-arrow-checks.json; hero-main-desktop.png and hero-main-mobile.png show the latest view. Mobile arrows flank the centred lower indicator row to avoid the copy. No deployment performed.

## Who We Are and chapter lightbox — 9 November 2026

Who We Are now pairs an existing classroom photograph on the left with the organisation narrative on the right; mission and vision have distinct cards below. KPIs remain after this section. Why Uganda has a centred introduction and chapter arrows at the card edges, moving to the lower control row on mobile. All ten photographs open a native dialog restricted to the selected chapter, with thumbnails, previous/next, keyboard navigation, Escape and focus return. Plain image links provide the no-JavaScript fallback.

Chrome checks at 320, 390, 768 and 1440px passed for every photograph and chapter, group isolation, wrapping, thumbnail selection, keyboard and focus return, and page/dialog overflow. No JavaScript page errors. Build and source checks pass: 13 HTML pages, 566 internal links, 12 SEO routes. Review images: who-we-are-desktop.png, why-uganda-centred-desktop.png, why-uganda-centred-mobile.png and why-uganda-lightbox.png in docs/previews. No full accessibility audit performed. Existing deployment ZIP SHA256 and modification time verified unchanged; no ZIP generated or deployment performed.

## Vertical Why Uganda navigation and kitchen comparison — 9 November 2026

Compact chapter pills occupy a left rail on desktop, with a two-column mobile layout. EdFund’s response includes dated kitchen and dining before/after pairs plus five supporting images, using all nine supplied kitchen photographs. Source originals are copied unchanged to source/assets/kitchen-originals; responsive WebP copies are served locally. Their SHA256 values match the originals. Dates follow the supplied filenames.

The homepage gallery, main gallery and Why Uganda photographs now use one native modal with previous/next, thumbnails, keyboard controls, Escape, focus return and touch navigation. Why Uganda groups stay isolated; kitchen images form a separate nine-photo group. Filtered gallery navigation includes only visible photos. CSS and JavaScript URLs have content fingerprints to avoid stale assets. The link checker now parses query strings correctly.

Chrome checks passed at 320, 390, 768 and 1440px for modal opening without URL navigation, chapter isolation, all nine kitchen photographs, close/focus return, keyboard wrapping and page/dialog overflow. Main gallery filtering and homepage gallery navigation pass. No browser JavaScript errors. Build and validators pass: 13 HTML pages, 575 internal links, 12 SEO routes. Existing authored paragraphs/headings were compared with the pre-change snapshot. Manual homepage caption, chapter title, unbolded organisation name and mission-card styling were preserved in the generator. All pre-existing CSS was retained. About page content was kept distinct from homepage overrides.

No deployment archive generated. The existing cPanel ZIP’s checksum and modification time are unchanged. Review screenshots: why-vertical-desktop.png, kitchen-response-desktop.png, kitchen-modal-desktop.png, kitchen-modal-mobile.png and kitchen-390.png. No full accessibility audit performed.

## Response comparison refinement — 9 November 2026

Kitchen and dining each follow the chapter text-left/photos-right layout. Each has three narrative paragraphs and exactly two before/two after photos. The extra project gallery is removed; its assets remain available. Each four-photo story has its own modal group. The older dining images are accurately captioned as outdoor meals and meal preparation, rather than implying two distinct old dining-room photographs.

Previous/next chapter arrows are sticky along the card edges at mid-viewport, constrained to the active chapter. Switching from deep within the long response chapter returns the selected shorter chapter to view. Desktop/mobile checks at 320/390/768/1440px passed for photo counts, modal groups, keyboard wrapping, overflow and sticky positions at two scroll depths. Additional 1440/390px checks confirm the chapter-switch scroll correction. Existing authored page text outside the requested comparison rewrite is preserved, including newly edited infrastructure/school-building headings migrated from HTML into the generator. Build, 574 internal-link checks and 12 SEO routes pass. Screenshots: response-refined-desktop.png, response-refined-mobile.png, response-sticky-desktop.png. No deployment ZIP generated.

## Dormitory reconstruction — 9 November 2026

Added a third response story matching the kitchen/dining text-left, before/after-right layout. Two older girls’ accommodation interiors precede the new girls’ dormitory interior and 2025 opening ceremony. The provided photographs all depict girls’ accommodation; captions state this explicitly within the broader girls’/boys’ dormitory topic. All five originals copied unchanged into source/assets/dormitory-originals, with optimised local WebP copies and source checksums in images.json. Four photos appear in the comparison and its isolated modal group.

All existing authored page content compared against the pre-change snapshot and preserved. Source-file checksums match all five copied originals. Build, 578-link check and 12-route SEO check pass. Chrome tests at 320/390/768/1440px pass for two before/two after photos, every modal image, keyboard wrapping, close/focus return, and no horizontal overflow or JavaScript errors. Screenshots: dormitory-1440.png and dormitory-390.png. Existing deployment ZIP checksum and modification time unchanged; no ZIP generated.

## Classroom before/after story — 9 November 2026

Added classroom reconstruction directly below the dormitory story within EdFund’s response. Uses the supplied Classrooms before.jpg and Classroom Building Renovation 1.jpg, with unchanged originals copied into source/assets/classroom-originals and optimised local WebP assets. Both views in the combined renovation photograph are preserved using contain fitting. Existing authored content retained; only the new story was inserted in current HTML and the generator.

Link checks pass for 13 HTML pages and 582 links; 12 SEO routes pass. Chrome checks at 1440/390/320px confirm no horizontal overflow, one before/one after photo, an isolated two-photo modal, keyboard wrapping and focus return. No JavaScript errors. Screenshots: classrooms-1440.png and classrooms-390.png. No deployment archive generated.

## PHP CMS Phase 1 and recovered team profiles — 12 November 2026

Added private PHP foundation, checksum-tracked additive cms_ migrations, table-scoped local runtime database user, one-owner CLI provisioning/recovery, password hashing, strict sessions, CSRF/origin protection, persistent atomic throttles, encrypted TOTP enrollment, step replay rejection, one-use recovery codes, session revocation and an authenticated dashboard. Public Apache routing remains static. Core security checks, actual private CLI owner creation, concurrent-attempt checks and full isolated-MariaDB browser tests passed. Real edfund content was not replaced; the real owner remains unprovisioned for the user's private credential choice. The separate CLI/web runtime is PHP 8.5.7; preflight explicitly requires a patch update before public hosting. PHP 8.3 execution was not directly tested, though Composer dependencies target it. Composer audit found no advisories at verification time; Composer schema is valid with an informational missing-project-license warning.

Recovered all four published WordPress lif_team biographies as plain text from the supplied SQL without executing/importing it. Modal behaviour, complete paragraph matching, long-content close visibility, Escape/focus return and no-JavaScript fallback passed at 1440/390/320px. Existing team names, roles, images and all other manual public copy retained. Link checks: 13 pages/586 links; SEO checks: 12 routes; no errors. See CMS-PROGRESS.md, CMS-PHASE-1-SETUP.md and TEAM-PROFILE-RECOVERY.md. No independent penetration test performed. No deployment ZIP built or production deployment performed.
