=== knuspermedia Local Fonts & Icons ===
Contributors: knuspermedia
Tags: google fonts, font awesome, gdpr, dsgvo, local fonts
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosts the Google Fonts and Font Awesome files your site already uses, so your visitors' browsers no longer connect to Google or Font Awesome.

== Description ==

When a page loads Google Fonts or Font Awesome from their servers, every visitor's browser sends its IP address to Google or Font Awesome. German courts have ruled that this can violate the GDPR (DSGVO).

knuspermedia Local Fonts & Icons finds these fonts and icons on your pages, downloads them once to your own server and rewrites the page, so visitors only load them from your domain. It works automatically after activation, no configuration needed.

This plugin is not affiliated with Google or Fonticons, Inc. (Font Awesome).

= What it handles =

* Google Fonts stylesheets (CSS API v1 and v2), reduced to the subsets you need, with `font-display: swap`
* Font Awesome from its own CDN and from cdnjs, jsDelivr and BootstrapCDN (CSS and JavaScript)
* Font Awesome Kits, replaced by a local copy of Font Awesome Free in the kit's version
* `@import` of Google Fonts / Font Awesome inside theme and plugin stylesheets, also nested
* Inline styles, URLs inside inline scripts and JSON, and the Web Font Loader
* `preconnect` / `dns-prefetch` hints to these servers
* Fonts that scripts add later in the browser (sliders, page builders, maps), blocked by an optional JavaScript guard
* Local file paths contain no third-party host names, so source code scanners don't report false positives

= Check your pages =

Under *Settings → Local Fonts & Icons* you can check any page of your site for remaining references to Google Fonts or Font Awesome, see which files were stored locally and clear the cache. Common page cache plugins (WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache, Autoptimize and others) are purged automatically.

This plugin is a technical tool. It does not provide legal advice and cannot guarantee that your website complies with the GDPR or any other law.

= WP-CLI =

* `wp local-fonts-icons flush` deletes all local copies and purges page caches
* `wp local-fonts-icons scan [<url>]` checks a page for remaining references

== External services ==

This plugin works as a download service for files your site already loads from third-party servers. It contains the host names of these servers only to recognize their URLs in your pages and replace them with local copies. Visitors' browsers never request anything from these servers because of this plugin; that is what the plugin prevents. No account is needed for any of the services.

Your server contacts a service only when one of your pages references a file from it: once per file, on the first page view after the file appears on your site, after the cache is cleared, or when you use "Check page". Each request sends the URL of the requested file, your server's IP address, a browser user agent string and your site's address as referrer (Font Awesome Pro and Kits only deliver files to registered domains). No data about your visitors is sent.

**Google Fonts** (fonts.googleapis.com, fonts.gstatic.com, ajax.googleapis.com for the Web Font Loader), provided by Google LLC: used to download the Google Fonts stylesheets and font files your pages use.
[Terms of Service](https://policies.google.com/terms), [Privacy Policy](https://policies.google.com/privacy)

**Font Awesome** (use.fontawesome.com, pro.fontawesome.com, kit.fontawesome.com, ka-f.fontawesome.com), provided by Fonticons, Inc.: used to download the Font Awesome stylesheets, fonts and scripts your pages use, and to read the version of a Font Awesome Kit.
[Terms of Service](https://fontawesome.com/tos), [Privacy Policy](https://fontawesome.com/privacy)

**jsDelivr** (cdn.jsdelivr.net; BootstrapCDN at maxcdn, stackpath and netdna.bootstrapcdn.com is served by jsDelivr): used to download Font Awesome files your pages load from jsDelivr or BootstrapCDN, and the Font Awesome Free files that replace a Font Awesome Kit.
[Terms of Service](https://www.jsdelivr.com/terms), [Privacy Policy](https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net)

**cdnjs** (cdnjs.cloudflare.com), provided by Cloudflare, Inc.: used to download Font Awesome files your pages load from cdnjs.
[Terms of Service](https://www.cloudflare.com/website-terms/), [Privacy Policy](https://www.cloudflare.com/privacypolicy/)

You can switch off Google Fonts or Font Awesome handling separately in the settings; the plugin then doesn't contact that service.

== Installation ==

1. Upload the plugin through *Plugins → Add New* or copy the `knuspermedia-local-fonts-icons` folder to `wp-content/plugins/`.
2. Activate the plugin. If you use OMGF, deactivate it, so the two plugins don't interfere.
3. Visit your site once, then go to *Settings → Local Fonts & Icons* and use "Check page".
4. Clear your page cache if your cache plugin isn't purged automatically.

== Frequently Asked Questions ==

= Does it work with page builders like Divi or Elementor? =

Yes. Fonts the builder adds to the page are localized, and fonts its scripts add later in the browser are blocked by the JavaScript guard. The plugin doesn't run inside the builder's editor preview.

= What about Font Awesome Kits and Pro? =

A kit that is enqueued through WordPress is replaced right away. A kit whose script tag a theme prints directly is removed and replaced from the next page view on. Kits are replaced by Font Awesome Free, so Pro icons and custom kit icons are missing; bundle the Pro files with your theme instead. Pro stylesheets loaded from pro.fontawesome.com are mirrored if your domain is allowed in your Font Awesome account.

= Does it handle Google Maps, reCAPTCHA or YouTube? =

No. Only fonts and icons are handled. Other Google services need consent from your visitors, e.g. through a cookie consent tool. Fonts that Google Maps adds in the browser are blocked by the JavaScript guard.

= How can I see a page without the plugin? =

Append `?nowpdf` to the URL.

== Changelog ==

= 1.2.0 =
* Renamed to knuspermedia Local Fonts & Icons.
* Font Awesome Kits are replaced through wp_enqueue_style, the JavaScript guard is added through wp_add_inline_script.
* Translations come from translate.wordpress.org instead of bundled files.
* Removed support for Font Awesome from unpkg.

= 1.1.0 =
* Admin interface in English and translatable.

= 1.0.2 =
* The JavaScript guard also blocks fonts that are inserted as HTML strings (e.g. Divi sticky elements).

= 1.0.1 =
* Local file paths no longer contain third-party host names.

= 1.0.0 =
* First release.
