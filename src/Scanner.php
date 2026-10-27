<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Loads a page of this site like an anonymous visitor would and reports every Google Fonts / Font Awesome URL
 * that's still in the HTML or in one of its local stylesheets.
 */
class Scanner {
	/**
	 * @return array{url: string, time: int, error?: string, findings: array<array{url: string, where: string}>}
	 */
	public static function scan( $url ) {
		$result = [
			'url'      => $url,
			'time'     => time(),
			'findings' => [],
		];

		$response = wp_remote_get(
			add_query_arg( 'wpdf_scan', time(), $url ),
			[
				'timeout'     => 90,
				'redirection' => 3,
				// Core filter for loopback requests, as used by WordPress itself.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'headers'     => [ 'Cache-Control' => 'no-cache' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();

			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$html = wp_remote_retrieve_body( $response );

		if ( $code >= 400 ) {
			$result['error'] = sprintf( 'Die Seite antwortet mit HTTP %d.', $code );

			return $result;
		}

		foreach ( Hosts::find_urls( $html, false ) as $found ) {
			$result['findings'][] = [ 'url' => $found, 'where' => 'HTML' ];
		}

		preg_match_all( '~<link\b[^>]*>~i', $html, $links );

		foreach ( $links[0] as $tag ) {
			$href = Processor::get_attribute( $tag, 'href' );
			$path = $href ? Localizer::url_to_path( $href ) : null;

			if ( ! $path ) {
				continue;
			}

			foreach ( Hosts::find_urls( (string) file_get_contents( $path ), false ) as $found ) {
				$result['findings'][] = [ 'url' => $found, 'where' => Hosts::shorten( Hosts::normalize( $href ) ) ];
			}
		}

		return $result;
	}
}
