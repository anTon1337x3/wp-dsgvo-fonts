<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Manage self-hosted Google Fonts and Font Awesome.
 */
class Cli {
	/**
	 * Deletes all local copies and purges common page caches.
	 *
	 * ## EXAMPLES
	 *
	 *     wp local-fonts-icons flush
	 */
	public function flush() {
		Cache::flush_all();
		\WP_CLI::success( 'Cache cleared.' );
	}

	/**
	 * Checks a page for remaining references to Google Fonts or Font Awesome.
	 *
	 * ## OPTIONS
	 *
	 * [<url>]
	 * : URL of this site to check. Default: homepage.
	 *
	 * ## EXAMPLES
	 *
	 *     wp local-fonts-icons scan https://example.com/contact/
	 */
	public function scan( $args ) {
		$result = Scanner::scan( $args[0] ?? home_url( '/' ) );

		if ( ! empty( $result['error'] ) ) {
			\WP_CLI::error( $result['error'] );
		}

		if ( ! $result['findings'] ) {
			\WP_CLI::success( 'No references to Google Fonts or Font Awesome found.' );

			return;
		}

		\WP_CLI\Utils\format_items( 'table', $result['findings'], [ 'url', 'where' ] );
		\WP_CLI::error( sprintf( '%d remaining references found.', count( $result['findings'] ) ) );
	}
}
