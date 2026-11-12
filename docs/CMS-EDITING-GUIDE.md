# EdFund client editing guide

Open `/admin/login` and enter your owner name and password. Email and authenticator setup/codes are not required. New passwords have a minimum of four characters. One owner account manages the site. If you forget the password, ask the maintainer to use the private owner recovery command; it sets a new password and revokes old sessions.

## Finding your way around

The left sidebar contains **Dashboard**, **Pages**, **Stories & Updates**, **Media Library** and **Settings**. The highlighted item shows where you are. **Collapse sidebar** gives the preview more room; your browser remembers that preference. On a phone or tablet, use the menu button at the top left to open navigation and × or Escape to close it.

The header shows your current screen. **View website** opens the public site in another tab. Open the account menu at the top right for **Account security** and **Sign out**. The Dashboard has shortcuts to the homepage editor, stories and pictures.

Loading and saving messages appear beside the screen’s actions. Wait for them to finish before continuing. Errors explain what needs attention; they do not publish content automatically. While switching pages, the previous fields are locked until the selected page has loaded.

## Edit while browsing the website

After signing in, open **View website**. A compact navy toolbar stays at the top of the real website, including individual stories. It is visible only to the signed-in owner. Existing sessions from the previous version gain toolbar access after visiting any admin screen once.

Use **Edit Home**, **Edit About**, the current page’s Edit link, or **Edit Story** to go straight to its editor. The page editor selects the section occupying the most space in your current view when it can match it to the saved page. Your Why Uganda chapter is retained where possible. Save a draft or Publish, then use **Return to page** / **Return to story** in the editor header. This opens that public page in the same tab and restores the saved browsing position/chapter when browser storage is available. If a story URL changes on publication, the return link uses its new URL. New/unpublished stories return to the story listing.

The toolbar also opens the Dashboard or Media Library. **Create New Story** creates a private draft and opens its editor directly. On narrow screens, Media Library is in **Account**. Account also contains security and Sign out. Closing the browser’s session, session expiry or owner recovery removes toolbar authorization; public visitors never receive it.

## Pages

1. Open **Pages** and choose a page; **Back to Pages** returns to the list.
2. The editor has three areas: **Page Sections** (left), the website preview (centre) and **Section Settings** (right). Click a section in the preview or select it in the list; the preview scrolls to it and its name is shown on the highlighted section. Settings show only that section's fields, grouped into collapsible parts.
3. Text, picture and link edits appear in the preview immediately, without reloading it. Use **Change Image** to pick or upload a picture and describe meaningful pictures. Link controls pair a button label with its destination (a page such as /contact/, an https:// address, mailto: or tel:). A picture that appears twice, such as a team portrait on its card and full profile, has separate fields.
4. Use the up/down arrows in the section list to reorder, **Add Section** to insert an approved layout below the selected section, and **Remove Section** (confirmed) in the settings panel. The banner/hero stays first; navigation/footer structure stays fixed. Desktop/Mobile switches the preview width.
5. **Search & Sharing** in the section list holds this page's title, description and share picture. Keep these specific and distinct. The homepage title must begin `ED Fund Uganda Foundation | Education`.
6. The toolbar shows **Unsaved changes**, **Draft saved** or **All changes saved**, and whether the page is **Published** or has **Changes not published**. **Save Draft** (also Ctrl/Cmd+S) keeps the public site unchanged; **Preview** opens a clean private tab; **Publish** asks for confirmation then goes live. The browser warns before leaving with unsaved changes. If another tab saved first, load the newest version rather than overwriting it.
7. **More > Revision History** lists versions with their times, marks the one that is live now and the current draft, and offers **Restore as Draft**. Restoring never changes the live site; publish it as a separate step.

Choose **Navigation, contact & footer** to edit the common contact bar, navigation labels/destinations, footer text, logo and links. Publish shared changes independently; they apply to every page and article. Page-specific content remains in each page. Updating an email address in shared content does not silently change separately written Contact-page paragraphs or enquiry links: review those fields too. Keep the footer’s white logo background.

The homepage Why Uganda chapter titles, narrative, reconstruction captions and selected images remain grouped under their existing headings. Preserve before/after order when replacing photographs. Existing chapter navigation, sticky controls, grouped lightboxes, hero controls and team dialogs continue to work.

## Stories, pictures and videos

Open **Stories & Updates**, choose **New story**, enter a title, short excerpt, URL slug, category, byline, featured image and image description. Use the small formatting toolbar for paragraphs, headings, bold, italic, lists and links. Add text, image or YouTube blocks; paste a normal YouTube link rather than iframe code. Uploading video files is not supported.

Preview the post, Save draft and Publish when ready. Its publication time is recorded when first published. It appears in Stories & Updates and the homepage’s story listing. Page-editor story cards are managed through Stories & Updates, so you do not have to edit both places. URL changes require confirmation and keep an automatic permanent redirect from the previous slug. Trash removes a story from listings/sitemap; restoring from trash requires publishing again.

**Media Library** searches existing assets and uploaded pictures. JPEG, PNG and WebP are accepted within the limits shown in the interface (normally 2 MiB/4 million pixels). Resize large photographs first. Add useful descriptions/captions. Uploads are safely re-encoded; originals are private. Draft-only uploads are unavailable to anonymous visitors. Images referenced by saved revisions are protected from deletion so restoration continues to work. Unused uploads can be removed/restored; bundled website images cannot be deleted here.

## Before finishing

Review desktop and mobile previews, check links and image descriptions, and Publish only the intended page/post/shared changes. Public canonical URLs use `https://edfundfoundation.org/`. The local preview is deliberately noindex. The maintainer handles backups, security updates, hosting and release configuration; editing content requires no code changes or ZIP uploads.
