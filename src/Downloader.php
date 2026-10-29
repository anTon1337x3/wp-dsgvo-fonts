<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * All requests to Google / Font Awesome happen here, server-side. Visitors never talk to those hosts.
 */
class Downloader {
	/**
	 * A current desktop browser, so Google Fonts serves WOFF2 files and subset comments.
	 */
	const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

	const MAX_BYTES = 10485760;

	/**
	 * @return array{body: string, type: string}|\WP_Error
	 */
	public static function fetch( $url ) {
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'             => 20,
				'redirection'         => 3,
				'user-agent'          => apply_filters( 'wpdf_user_agent', self::USER_AGENT ),
				'limit_response_size' => self::MAX_BYTES,
				'headers'             => [
					'Accept'  => '*/*',
					// Font Awesome Pro CDN and Kits only answer for the domains registered with them.
					'Referer' => home_url( '/' ),
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code !== 200 ) {
			return new \WP_Error( 'wpdf_http', sprintf( 'HTTP %d', $code ) );
		}

		if ( $body === '' ) {
			return new \WP_Error( 'wpdf_empty', __( 'Empty response', 'knuspermedia-local-fonts-icons' ) );
		}

		return [
			'body' => $body,
			'type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
		];
	}
}
