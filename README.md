# WP DSGVO Fonts

WordPress-Plugin, das **Google Fonts** und **Font Awesome** automatisch lokal hostet und alle Aufrufe an Google- und Font-Awesome-Server unterbindet. Besucher stellen keine Verbindung mehr zu `fonts.googleapis.com`, `fonts.gstatic.com`, `use.fontawesome.com`, `kit.fontawesome.com` & Co. her: Es wird keine IP-Adresse übertragen und kein Cookie gesetzt.

## Funktionsweise

Das Plugin puffert die HTML-Ausgabe jeder Frontend-Seite (inkl. Login-Seite) und schreibt sie um, bevor sie gesendet wird:

| Einbindung | Was passiert |
|---|---|
| `<link href="//fonts.googleapis.com/css?family=…">` (API v1 und `css2`) | CSS wird serverseitig geladen, auf die gewählten Zeichensätze gekürzt, `font-display` ergänzt; alle WOFF2-Dateien werden lokal gespeichert |
| Font Awesome über `use.fontawesome.com`, `pro.fontawesome.com`, `cdnjs`, `jsdelivr`, `unpkg`, `maxcdn/stackpath/netdna.bootstrapcdn.com` (CSS und JS) | CSS + Webfonts werden gespiegelt, `integrity`-Attribute entfernt |
| Font Awesome Kit `<script src="https://kit.fontawesome.com/xxxx.js">` | wird durch eine lokale Font Awesome Free in der Version des Kits ersetzt (+ v4-Kompatibilität) |
| `@import` in Theme-/Plugin-CSS-Dateien (auch verschachtelt) | bereinigte Kopie der CSS-Datei wird ausgeliefert |
| Inline `<style>@import …</style>`, URLs in JS/JSON (`https:\/\/fonts…`) | werden durch lokale URLs ersetzt |
| Web Font Loader (`WebFont.load({google:{families:[…]}})`) | wird auf das `custom`-Modul mit lokaler CSS umgestellt |
| `preconnect` / `dns-prefetch` zu Google / Font Awesome | werden entfernt, ebenso Hinweise auf CDNs, die danach nicht mehr genutzt werden |
| Per JavaScript nachgeladene Fonts (Slider, Widgets, Google Maps …) | werden im Browser blockiert (JS-Schutz, abschaltbar) |

Downloads passieren einmalig beim ersten Seitenaufruf, danach kommt alles aus `wp-content/uploads/wp-dsgvo-fonts/`. Kann eine Datei nicht geladen werden, wird die Einbindung blockiert (Ersatzschrift statt Datenleck) und nach 15 Minuten erneut versucht.

## Installation

1. Ordner `wp-dsgvo-fonts` nach `wp-content/plugins/` kopieren (oder als ZIP hochladen).
2. Plugin aktivieren. **OMGF / OMGF Pro deaktivieren**, damit sich die Plugins nicht in die Quere kommen.
3. Unter **Einstellungen → DSGVO Fonts** mit „Seite prüfen“ kontrollieren, dass keine Verweise mehr übrig sind.
4. Seiten-Cache leeren (WP Rocket, W3TC, LiteSpeed, WP Super Cache, Autoptimize u. a. werden automatisch geleert).

## Einstellungen

- Google Fonts / Font Awesome einzeln aktivierbar
- Zeichensätze (Standard: `latin` + `latin-ext`)
- `font-display` (Standard: `swap`)
- Lokale CSS-Dateien nach `@import` durchsuchen
- JavaScript-Schutz gegen nachgeladene Fonts
- Font-Awesome-Version für Kits (Standard: automatisch, Ersatz 6.7.2) und v4-Kompatibilität

Nach dem Speichern wird der Font-Cache automatisch geleert.

## WP-CLI

```bash
wp dsgvo-fonts flush          # lokale Kopien löschen, Seiten-Caches leeren
wp dsgvo-fonts scan [<url>]   # Seite auf verbliebene Verweise prüfen
```

## Debugging

- `?nowpdf` an eine URL hängen, um die Seite ohne Plugin-Verarbeitung zu sehen.
- In Page-Builder-Vorschauen (Elementor, Divi, Beaver, Bricks, Oxygen, WPBakery …) läuft das Plugin nicht.
- Filter: `wpdf_should_process`, `wpdf_processed_html`, `wpdf_user_agent`, Action `wpdf_purge_page_caches`.

## Grenzen

- **Font Awesome Pro Kits:** lokal wird die Free-Version bereitgestellt. Pro-Icons und eigene Kit-Icons fehlen. Für Pro die Pro-Dateien selbst ins Theme einbinden.
- Google-Dienste, die keine Fonts sind (Maps, reCAPTCHA, YouTube, jQuery von `ajax.googleapis.com`), werden nicht verändert. Dafür ist ein Consent-Tool zuständig. Von Google Maps nachgeladene Roboto-Schriften blockiert der JS-Schutz.
- Das WordPress-Backend wird nicht umgeschrieben, nur das Frontend und die Login-Seite.

## Tests

End-to-End-Test mit Docker (WordPress + MariaDB + Fixture-Seite mit allen Einbindungsarten):

```bash
tests/e2e/run.sh        # Seite läuft danach auf http://localhost:8089 (admin / admin)
tests/e2e/run.sh down   # aufräumen
```

## Lizenz

GPL-2.0-or-later
