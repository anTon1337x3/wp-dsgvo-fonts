=== DSGVO Fonts ===
Contributors: knuspermedia
Tags: google fonts, font awesome, dsgvo, gdpr, local fonts
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosts Google Fonts and Font Awesome automatically, so your visitors' browsers no longer connect to Google or Font Awesome servers.

== Description ==

When a page loads Google Fonts or Font Awesome from their servers, every visitor's browser sends its IP address to Google or Font Awesome. German courts have ruled that this can violate the GDPR (DSGVO).

DSGVO Fonts finds these fonts on your pages, downloads them once to your own server and rewrites the page, so visitors only load them from your domain. It works automatically after activation, no configuration needed.

= What it handles =

* Google Fonts stylesheets (CSS API v1 and v2), reduced to the subsets you need, with `font-display: swap`
* Font Awesome from its own CDN and from cdnjs, jsDelivr, unpkg and BootstrapCDN (CSS and JavaScript)
* Font Awesome Kits, replaced by a local copy of Font Awesome Free in the kit's version
* `@import` of Google Fonts / Font Awesome inside theme and plugin stylesheets, also nested
* Inline styles, URLs inside inline scripts and JSON, and the Web Font Loader
* `preconnect` / `dns-prefetch` hints to these servers
* Fonts that scripts add later in the browser (sliders, page builders, maps), blocked by an optional JavaScript guard
* Local file paths contain no third-party host names, so source code scanners don't report false positives

= Check your pages =

Under *Settings → DSGVO Fonts* you can check any page of your site for remaining references to Google Fonts or Font Awesome, see which files were stored locally and clear the cache. Common page cache plugins (WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache, Autoptimize and others) are purged automatically.

This plugin is a technical tool. It does not provide legal advice and cannot guarantee that your website complies with the GDPR or any other law.

= WP-CLI =

* `wp dsgvo-fonts flush` deletes all local copies and purges page caches
* `wp dsgvo-fonts scan [<url>]` checks a page for remaining references

== External services ==

To create the local copies, your **server** (never your visitors' browsers) downloads the stylesheets, font files and scripts that your pages reference. This happens once per file, on the first page view after the file appears on your site, after the cache is cleared or when you use "Check page". Each download sends your server's IP address and the requested file URL to the respective service. No data about your visitors is sent.

**Google Fonts** (fonts.googleapis.com, fonts.gstatic.com, ajax.googleapis.com for the Web Font Loader), provided by Google LLC: used to download Google Fonts stylesheets and font files that your pages use.
[Terms of Service](https://policies.google.com/terms), [Privacy Policy](https://policies.google.com/privacy)

**Font Awesome** (use.fontawesome.com, pro.fontawesome.com, kit.fontawesome.com, ka-f.fontawesome.com), provided by Fonticons, Inc.: used to download Font Awesome stylesheets, fonts and scripts that your pages use, and to read the version of a Font Awesome Kit.
[Terms of Service](https://fontawesome.com/tos), [Privacy Policy](https://fontawesome.com/privacy)

**jsDelivr** (cdn.jsdelivr.net, maxcdn/stackpath/netdna.bootstrapcdn.com): used to download Font Awesome files that your pages load from jsDelivr or BootstrapCDN, and the Font Awesome Free files that replace a Font Awesome Kit.
[Terms of Service](https://www.jsdelivr.com/terms), [Privacy Policy](https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net)

**cdnjs** (cdnjs.cloudflare.com), provided by Cloudflare, Inc.: used to download Font Awesome files that your pages load from cdnjs.
[Terms of Service](https://www.cloudflare.com/website-terms/), [Privacy Policy](https://www.cloudflare.com/privacypolicy/)

**unpkg** (unpkg.com): used to download Font Awesome files that your pages load from unpkg.
[Website](https://unpkg.com)

You can switch off Google Fonts or Font Awesome handling separately in the settings; the plugin then doesn't contact that service.

== Installation ==

1. Upload the plugin through *Plugins → Add New* or copy the `dsgvo-fonts` folder to `wp-content/plugins/`.
2. Activate the plugin. If you use OMGF, deactivate it, so the two plugins don't interfere.
3. Visit your site once, then go to *Settings → DSGVO Fonts* and use "Check page".
4. Clear your page cache if your cache plugin isn't purged automatically.

== Frequently Asked Questions ==

= Does it work with page builders like Divi or Elementor? =

Yes. Fonts the builder adds to the page are localized, and fonts its scripts add later in the browser are blocked by the JavaScript guard. The plugin doesn't run inside the builder's editor preview.

= What about Font Awesome Pro? =

Pro stylesheets loaded from pro.fontawesome.com are mirrored if your domain is allowed in your Font Awesome account. Pro Kits are replaced by Font Awesome Free, so Pro icons and custom kit icons are missing; bundle the Pro files with your theme instead.

= Does it handle Google Maps, reCAPTCHA or YouTube? =

No. Only fonts are handled. Other Google services need consent from your visitors, e.g. through a cookie consent tool. Fonts that Google Maps adds in the browser are blocked by the JavaScript guard.

= How can I see a page without the plugin? =

Append `?nowpdf` to the URL.

== Changelog ==

= 1.1.0 =
* Admin interface in English, with a German translation included.

= 1.0.2 =
* The JavaScript guard also blocks fonts that are inserted as HTML strings (e.g. Divi sticky elements).

= 1.0.1 =
* Local file paths no longer contain third-party host names.

= 1.0.0 =
* First release.
