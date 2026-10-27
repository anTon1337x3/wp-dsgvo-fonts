<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

// This class lists the hosts this plugin looks for in order to replace them with local copies; nothing is loaded
// from these hosts in the browser.
// phpcs:disable PluginCheck.CodeAnalysis.Offloading.OffloadedContent

/**
 * Knows which third-party hosts serve Google Fonts and Font Awesome, and how to find their URLs in markup.
 */
class Hosts {
	const GOOGLE_HOSTS = [ 'fonts.googleapis.com', 'fonts.gstatic.com' ];

	/**
	 * Font Awesome's own asset hosts. fontawesome.com itself is left alone, it only serves documentation links.
	 */
	const FONTAWESOME_HOSTS = [
		'use.fontawesome.com',
		'pro.fontawesome.com',
		'kit.fontawesome.com',
		'ka-f.fontawesome.com',
		'ka-p.fontawesome.com',
		'kit-free.fontawesome.com',
		'kit-pro.fontawesome.com',
		'free.fontawesome.com',
	];

	/**
	 * General purpose CDNs: only paths that belong to Font Awesome count.
	 */
	const SHARED_CDNS = [
		'cdnjs.cloudflare.com',
		'cdn.jsdelivr.net',
		'maxcdn.bootstrapcdn.com',
		'stackpath.bootstrapcdn.com',
		'netdna.bootstrapcdn.com',
		'unpkg.com',
	];

	const FONTAWESOME_PATH = '~font-?awesome|@fortawesome~i';

	const HOST_PATTERN = 'fonts\.googleapis\.com|fonts\.gstatic\.com|ajax\.googleapis\.com|(?:use|pro|kit|ka-f|ka-p|kit-free|kit-pro|free)\.fontawesome\.com|cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|(?:maxcdn|stackpath|netdna)\.bootstrapcdn\.com|unpkg\.com';

	/**
	 * @return string|null 'google', 'fontawesome' or null if the URL isn't ours to handle.
	 */
	public static function classify( $url ) {
		$parts = wp_parse_url( self::normalize( $url ) );
		$host  = strtolower( $parts['host'] ?? '' );
		$path  = $parts['path'] ?? '';

		if ( in_array( $host, self::GOOGLE_HOSTS, true ) ) {
			return 'google';
		}

		// The Web Font Loader is the only Google Fonts related library on Google's general CDN.
		if ( $host === 'ajax.googleapis.com' && strpos( $path, '/ajax/libs/webfont/' ) === 0 ) {
			return 'google';
		}

		if ( in_array( $host, self::FONTAWESOME_HOSTS, true ) ) {
			return 'fontawesome';
		}

		if ( in_array( $host, self::SHARED_CDNS, true ) && preg_match( self::FONTAWESOME_PATH, $path ) ) {
			return 'fontawesome';
		}

		return null;
	}

	/**
	 * Whether the URL belongs to a service that is enabled in the settings.
	 */
	public static function is_handled( $url ) {
		$type = self::classify( $url );

		return $type && Settings::get( $type );
	}

	public static function is_kit_script( $url ) {
		$parts = wp_parse_url( self::normalize( $url ) );

		return strtolower( $parts['host'] ?? '' ) === 'kit.fontawesome.com' && substr( $parts['path'] ?? '', -3 ) === '.js';
	}

	/**
	 * Decodes HTML entities and makes protocol-relative and http URLs absolute https URLs.
	 */
	public static function normalize( $url ) {
		$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( strpos( $url, '//' ) === 0 ) {
			return 'https:' . $url;
		}

		if ( stripos( $url, 'http://' ) === 0 && self::matches_host_pattern( $url ) ) {
			return 'https://' . substr( $url, 7 );
		}

		return $url;
	}

	private static function matches_host_pattern( $url ) {
		return (bool) preg_match( '~^https?://(?:' . self::HOST_PATTERN . ')(?:[/?#]|$)~i', $url );
	}

	/**
	 * Matches URLs as they appear in HTML attributes, CSS and JS strings. An ampersand is only allowed as an
	 * HTML entity, so a URL never swallows a following &quot;.
	 */
	public static function url_regex() {
		return '~(?:https?:)?//(?:' . self::HOST_PATTERN . ')(?:/(?:&amp;|&#0?38;|[^\s"\'<>()\\\\&])*)?~i';
	}

	/**
	 * Same as url_regex(), but for JSON encoded strings, e.g. https:\/\/fonts.googleapis.com\/css?family=...
	 */
	public static function escaped_url_regex() {
		return '~(?:https?:)?\\\\/\\\\/(?:' . self::HOST_PATTERN . ')(?:\\\\/(?:\\\\/|\\\\u0026|&amp;|[^\s"\'<>()\\\\&])*)?~i';
	}

	public static function unescape_json_url( $url ) {
		return str_replace( [ '\\/', '\\u0026' ], [ '/', '&' ], $url );
	}

	/**
	 * Finds all Google Fonts / Font Awesome URLs in a piece of HTML, CSS or JS.
	 *
	 * @param bool $only_handled Ignore URLs of services that are disabled in the settings.
	 *
	 * @return string[] Normalized, unique URLs.
	 */
	public static function find_urls( $text, $only_handled = true ) {
		$found = [];

		if ( preg_match_all( self::url_regex(), $text, $plain ) ) {
			$found = array_merge( $found, $plain[0] );
		}

		if ( preg_match_all( self::escaped_url_regex(), $text, $escaped ) ) {
			$found = array_merge( $found, array_map( [ self::class, 'unescape_json_url' ], $escaped[0] ) );
		}

		$urls = [];

		foreach ( $found as $url ) {
			$url = self::normalize( $url );

			if ( $only_handled ? self::is_handled( $url ) : self::classify( $url ) ) {
				$urls[ $url ] = true;
			}
		}

		return array_keys( $urls );
	}

	/**
	 * Resolves a (relative) reference against the URL of the stylesheet it was found in.
	 */
	public static function resolve( $base, $ref ) {
		if ( preg_match( '~^[a-z][a-z0-9+.-]*:~i', $ref ) ) {
			return $ref;
		}

		$base_parts = wp_parse_url( $base );
		$scheme     = $base_parts['scheme'] ?? 'https';

		if ( strpos( $ref, '//' ) === 0 ) {
			return $scheme . ':' . $ref;
		}

		$authority = $scheme . '://' . ( $base_parts['host'] ?? '' ) . ( isset( $base_parts['port'] ) ? ':' . $base_parts['port'] : '' );
		$suffix    = '';

		if ( preg_match( '~^([^?#]*)([?#].*)$~', $ref, $match ) ) {
			$ref    = $match[1];
			$suffix = $match[2];
		}

		if ( $ref === '' ) {
			return $authority . ( $base_parts['path'] ?? '/' ) . $suffix;
		}

		if ( $ref[0] === '/' ) {
			$path = $ref;
		} else {
			$path = preg_replace( '~/[^/]*$~', '/', $base_parts['path'] ?? '/' ) . $ref;
		}

		$segments = [];

		foreach ( explode( '/', $path ) as $segment ) {
			if ( $segment === '..' ) {
				if ( count( $segments ) > 1 ) {
					array_pop( $segments );
				}
			} elseif ( $segment !== '.' ) {
				$segments[] = $segment;
			}
		}

		return $authority . implode( '/', $segments ) . $suffix;
	}

	/**
	 * Turns an absolute URL on this site into a root-relative one, so it survives domain and protocol changes.
	 */
	public static function shorten( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) || strtolower( $parts['host'] ) !== self::site_host() ) {
			return $url;
		}

		return ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}

	public static function site_host() {
		return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}
}
