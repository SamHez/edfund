# Migration map

Audit completed before editing. Retained: Python generator, shared HTML formatting/icon helpers, local Manrope, static hosting architecture and technical validators. Rebuilt all page content and shared components. Retired source assets, old generated routes, obsolete preview files and superseded documentation were removed.

| Previous page role | Replacement | Content source | Photo source |
| --- | --- | --- | --- |
| Home `/` | `/` | Current official homepage/about and actual reports | Supplied technology/training photographs |
| Organisation `/about-us/` | `/about-us/` | Current about page and founder profile | Supplied classroom and school exterior |
| Service directory and six detail routes | `/our-work/` | School construction and technology reports | Supplied exterior, computers and group photo |
| Profile directory | `/our-team/` | Current `/meet-the-team/` names and roles | Matching official portraits |
| Visitor information | `/volunteer/` | Volunteer page; enquiries only | Supplied group photograph |
| Resource listing and three guides | `/stories-updates/` plus three articles | Three September 2026 primary reports | Supplied programme photographs |
| No equivalent | `/gallery/` | Supplied activity collection | 12 distinct supplied photographs |
| Contact `/contact/` | `/contact/` | Current official contact details | No decorative photo required |
| Privacy `/privacy-policy/` | `/privacy-policy/` | Actual static implementation | None |
| Error page | `/404.html` | Original navigation assistance | None |

## Redirect verification

All destinations are locally generated and link-checked. Confirmed equivalent content: `/new-about-us/` to `/about-us/`; `/meet-the-team/` to `/our-team/`; `/our-blog/` to `/stories-updates/`; `/become-a-volunteer/` to `/volunteer/`. Older `/ourteam/`, `/new-contact-us/` and `/contact-us/` currently return not-found pages; their intended people/contact destinations are confirmed by the explicit client migration brief and current navigation, not by claims of retained live content. They point to `/our-team/` or `/contact/` respectively. Rules accept optional trailing slash and preserve query strings. No unrelated retired routes were repurposed as redirects. Apache execution must be tested on a staging host; Python’s preview does not execute `.htaccess`.
