# Editing components

Update organisation data, team, focus areas, stories and gallery selections in `source/site.json`. Set `donation_url` only after confirming a real, owned payment destination. Keep it null for the current enquiry workflow.

Impact entries render only when `verified` is true and `value` is non-null. Record the approval source and reporting period before changing these fields. Do not publish estimates.

Hero text, photo selection and focal positions live in `hero_slides` in `source/site.json`. Slides use one H1 on the first slide and H2 on the others; inactive slides are inert and hidden from assistive technology. Timing: 7000ms per slide, 1400ms crossfade, 8400ms slow zoom. Later images decode before the transition. Autoplay continues on pointer hover, matching the reference; it pauses on keyboard focus, hidden document or the pause button. Reduced-motion users get no automatic movement or zoom. Manual controls and arrow keys remain available. Georgia headings pair with local Manrope navigation/body copy.

`source/images.json` records intrinsic dimensions, descriptive alt text and gallery categories. New photographs need desktop/mobile WebP copies, no enlargement, consent and a provenance entry. Never edit generated HTML for lasting changes. Shared header, footer, cards, photo helper, CTA and SEO output live in build.py.

After edits run the three commands in README. Re-test interactions when JavaScript changes. All CSS uses the local font; primary actions use a darker teal for accessible white text, with the requested base teal retained in the palette.

## Compact hero controls (latest)

Edit these fields in `source/site.json`, then run `python3 source/build.py`:

```json
"hero_height_desktop_px": 504,
"hero_height_mobile_px": 550
```

Mobile height applies at 600px and below. These values are passed to `--hero-height-desktop` and `--hero-height-mobile` on the slider; the final “Client controls” CSS block consumes them. Unlike the earlier viewport-filling slider, height is fixed in pixels at each breakpoint. Leave enough room for every slide and its controls if reducing further. Desktop copy is inset within a 1200px maximum container with 80px minimum gutters, 48px tablet gutters and 24px mobile gutters. Heading sizes are in that final CSS block.

The visible pause/play button was removed at the client’s request. Autoplay, manual slide selection and arrow keys remain. Keyboard focus, hidden-document state and reduced-motion preference still suspend autoplay; reduced motion also disables zoom. Earlier notes about a visible pause button describe the previous revision.

`who_we_are` holds the client-supplied organisation narrative. `mission` and `vision` hold the paired panels. `videos` holds the two IDs, titles, descriptions and local poster selections. Video players load only on selection, using youtube-nocookie.com, with ordinary YouTube links as fallback. `page_banners` controls concise inner-page titles and photo choices. Story short titles live under each story’s `short_title`.

Latest visual refinement: hero height and headline sizing reduced approximately 10% to match the requested 90%-zoom feel at normal browser zoom. Desktop height 504px, mobile 550px; maximum desktop headline 58px. Browser zoom is unchanged.

Latest Why Uganda layout: chapter `paragraphs` arrays control the prominent copy. Preserve each chapter’s image order: the first is the wide feature photograph in three-image collages, followed by two supporting images; two-image chapters use equal-height side-by-side frames. All groups use CSS cover cropping. The footer and header both use `navigation_logo` from site.json; the footer adds the white brand-panel class.
