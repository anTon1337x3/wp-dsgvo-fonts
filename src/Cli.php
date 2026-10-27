<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Lokal gehostete Google Fonts und Font Awesome verwalten.
 */
class Cli {
	/**
	 * Löscht alle lokalen Kopien und leert gängige Seiten-Caches.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgvo-fonts flush
	 */
	public function flush() {
		Cache::flush_all();
		\WP_CLI::success( 'Cache geleert.' );
	}

	/**
	 * Prüft eine Seite auf verbliebene Verweise auf Google Fonts oder Font Awesome.
	 *
	 * ## OPTIONS
	 *
	 * [<url>]
	 * : Zu prüfende URL dieser Website. Standard: Startseite.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgvo-fonts scan https://example.com/kontakt/
	 */
	public function scan( $args ) {
		$result = Scanner::scan( $args[0] ?? home_url( '/' ) );

		if ( ! empty( $result['error'] ) ) {
			\WP_CLI::error( $result['error'] );
		}

		if ( ! $result['findings'] ) {
			\WP_CLI::success( 'Keine Verweise auf Google Fonts oder Font Awesome gefunden.' );

			return;
		}

		\WP_CLI\Utils\format_items( 'table', $result['findings'], [ 'url', 'where' ] );
		\WP_CLI::error( sprintf( '%d verbliebene Verweise gefunden.', count( $result['findings'] ) ) );
	}
}
