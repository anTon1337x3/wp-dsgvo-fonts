# WP DSGVO Fonts

WordPress plugin that automatically self-hosts **Google Fonts** and **Font Awesome** and prevents every request to Google's and Font Awesome's servers. Visitors no longer connect to `fonts.googleapis.com`, `fonts.gstatic.com`, `use.fontawesome.com`, `kit.fontawesome.com` and the like: no IP address is transmitted and no cookie is set. This makes the site compliant with the GDPR (DSGVO) rulings of German courts on Google Fonts.

## How it works

The plugin buffers the HTML output of every frontend page (including the login page) and rewrites it before it is sent:

| Embedding | What happens |
|---|---|
| `<link href="//fonts.googleapis.com/css?family=…">` (API v1 and `css2`) | The stylesheet is fetched server-side, reduced to the selected subsets, `font-display` is added, all WOFF2 files are stored locally |
| Font Awesome via `use.fontawesome.com`, `pro.fontawesome.com`, `cdnjs`, `jsdelivr`, `unpkg`, `maxcdn/stackpath/netdna.bootstrapcdn.com` (CSS and JS) | CSS and webfonts are mirrored, `integrity` attributes are removed |
| Font Awesome Kit `<script src="https://kit.fontawesome.com/xxxx.js">` | Replaced by a local Font Awesome Free in the kit's version (plus v4 shims) |
| `@import` in theme / plugin stylesheets (also nested) | A cleaned copy of the stylesheet is served |
| Inline `<style>@import …</style>`, URLs in JS / JSON (`https:\/\/fonts…`) | Replaced by local URLs |
| Web Font Loader (`WebFont.load({google:{families:[…]}})`) | Switched to the `custom` module with a local stylesheet |
| `preconnect` / `dns-prefetch` to Google / Font Awesome | Removed, as are hints for CDNs that are no longer used afterwards |
| Fonts injected by JavaScript (sliders, widgets, Divi sticky elements, Google Maps …) | Blocked in the browser (JS guard, can be disabled): via `href`/`src`, `setAttribute`, `appendChild`/`append` & co. (e.g. jQuery `.append('<link …>')`), `innerHTML`, `insertAdjacentHTML` and `document.write` |

Files are downloaded once on the first page view; afterwards everything is served from `wp-content/uploads/wp-dsgvo-fonts/` (subfolders `google-fonts/` and `font-awesome/`). The providers' host names appear neither in the local paths nor in the JS guard, so GDPR scanners that search the page source for `fonts.googleapis.com` don't raise false alarms.

If a file can't be downloaded, the embedding is blocked (fallback font instead of a data leak) and retried after 15 minutes.

## Installation

1. Copy the `wp-dsgvo-fonts` folder to `wp-content/plugins/` (or upload it as a ZIP).
2. Activate the plugin. **Deactivate OMGF / OMGF Pro** so the two plugins don't interfere.
3. Under **Settings → DSGVO Fonts**, use "Seite prüfen" (check page) to verify that no references are left.
4. Clear the page cache (WP Rocket, W3TC, LiteSpeed, WP Super Cache, Autoptimize and others are purged automatically).

## Settings

- Google Fonts / Font Awesome can be enabled separately
- Subsets (default: `latin` + `latin-ext`)
- `font-display` (default: `swap`)
- Scan local stylesheets for `@import`
- JavaScript guard against injected fonts
- Font Awesome version for kits (default: detected automatically, fallback 6.7.2) and v4 compatibility

The font cache is cleared automatically when the settings are saved. The admin UI is in German.

## WP-CLI

```bash
wp dsgvo-fonts flush          # delete local copies, purge page caches
wp dsgvo-fonts scan [<url>]   # check a page for remaining references
```

## Debugging

- Append `?nowpdf` to a URL to see the page without the plugin's processing.
- The plugin doesn't run in page builder previews (Elementor, Divi, Beaver, Bricks, Oxygen, WPBakery …).
- Filters: `wpdf_should_process`, `wpdf_processed_html`, `wpdf_user_agent`; action `wpdf_purge_page_caches`.

## Limitations

- **Font Awesome Pro kits:** the Free version is provided locally. Pro icons and custom kit icons are missing. For Pro, bundle the Pro files with the theme yourself.
- Google services other than fonts (Maps, reCAPTCHA, YouTube, jQuery from `ajax.googleapis.com`) are left untouched; that's the job of a consent tool. Roboto fonts injected by Google Maps are blocked by the JS guard.
- The WordPress admin is not rewritten, only the frontend and the login page.

## Tests

End-to-end test with Docker (WordPress + MariaDB + a fixture page that uses every kind of embedding):

```bash
tests/e2e/run.sh        # the site keeps running on http://localhost:8089 (admin / admin)
tests/e2e/run.sh down   # clean up
```

## License

GPL-2.0-or-later
