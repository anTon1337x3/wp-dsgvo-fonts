<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites the HTML of every frontend page before it's sent: no Google Fonts / Font Awesome URL leaves the server.
 */
class Processor {
	/**
	 * Replaces URLs that couldn't be localized. An empty data URI is valid everywhere and never hits the network.
	 */
	const BLOCKED = 'data:,';

	/**
	 * Query parameters set by page builders while their editor preview is open.
	 */
	const BUILDER_PREVIEWS = [
		'elementor-preview',
		'fl_builder',
		'et_fb',
		'ct_builder',
		'vc_editable',
		'vcv-editable',
		'tve',
		'brizy-edit-iframe',
		'bricks',
		'fb-edit',
		'op3editor',
		'siteorigin_panels_live_editor',
	];

	const ATTRIBUTES = [ 'href', 'src', 'data-href', 'data-src' ];

	public function init() {
		add_action( 'template_redirect', [ $this, 'start_buffer' ], 1 );
		add_action( 'login_init', [ $this, 'start_buffer' ], 1 );

		// The guard is enqueued first, so it runs before any other script; kits are replaced after everything is enqueued.
		foreach ( [ 'wp_enqueue_scripts', 'login_enqueue_scripts' ] as $hook ) {
			add_action( $hook, [ $this, 'enqueue_guard' ], PHP_INT_MIN );
			add_action( $hook, [ $this, 'enqueue_kits' ], PHP_INT_MAX );
		}
	}

	/**
	 * Blocks Google Fonts / Font Awesome stylesheets and scripts that are added later by JavaScript (e.g. Google Maps,
	 * sliders, page builder widgets).
	 */
	public function enqueue_guard() {
		if ( ! Settings::get( 'js_guard' ) || ! $this->should_process() ) {
			return;
		}

		$config = wp_json_encode(
			[
				'google'      => (bool) Settings::get( 'google' ),
				'fontawesome' => (bool) Settings::get( 'fontawesome' ),
			]
		);

		wp_register_script( 'wpdf-guard', false, [], WPDF_VERSION, false );
		wp_add_inline_script( 'wpdf-guard', str_replace( '__WPDF_CONFIG__', $config, trim( (string) file_get_contents( WPDF_DIR . 'assets/js/guard.js' ) ) ) );
		wp_enqueue_script( 'wpdf-guard' );
	}

	/**
	 * Font Awesome Kits are replaced by the matching Font Awesome Free release, stored locally. Kits enqueued through
	 * WordPress are emptied; kits written directly into a theme are removed from the HTML and remembered, so their
	 * replacement is enqueued from then on.
	 */
	public function enqueue_kits() {
		if ( ! Settings::get( 'fontawesome' ) || ! $this->should_process() ) {
			return;
		}

		$kits    = Localizer::instance()->known_kits();
		$scripts = wp_scripts();

		foreach ( $scripts->registered as $script ) {
			if ( is_string( $script->src ) && Hosts::is_kit_script( $script->src ) ) {
				$kits[] = $script->src;
				// Keeps the handle, so scripts that depend on the kit still load.
				$script->src = false;
			}
		}

		foreach ( array_unique( $kits ) as $kit ) {
			foreach ( Localizer::instance()->kit_stylesheets( $kit ) as $url ) {
				wp_enqueue_style( 'wpdf-fa-kit-' . substr( md5( $url ), 0, 8 ), $url, [], WPDF_VERSION );
			}
		}
	}

	public function should_process() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only debug switch, nothing is changed.
		if ( isset( $_GET['nowpdf'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() ) {
			return false;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return false;
		}

		foreach ( self::BUILDER_PREVIEWS as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only detects a page builder preview.
			if ( isset( $_GET[ $param ] ) ) {
				return false;
			}
		}

		if ( ! Settings::get( 'google' ) && ! Settings::get( 'fontawesome' ) ) {
			return false;
		}

		return (bool) apply_filters( 'wpdf_should_process', true );
	}

	public function start_buffer() {
		if ( $this->should_process() ) {
			ob_start( [ $this, 'process_buffer' ] );
		}
	}

	public function process_buffer( $html ) {
		if ( ! is_string( $html ) || ( stripos( $html, '<html' ) === false && stripos( $html, '<head' ) === false ) || ! $this->is_html_response() ) {
			return $html;
		}

		try {
			$html = $this->process( $html );
		} catch ( \Throwable $e ) {
			// Shown under Settings → Local Fonts & Icons.
			Localizer::instance()->log_error( home_url( add_query_arg( [] ) ), $e->getMessage() );
		}

		Localizer::instance()->save();

		return $html;
	}

	private function is_html_response() {
		foreach ( headers_list() as $header ) {
			if ( stripos( $header, 'content-type:' ) === 0 ) {
				return stripos( $header, 'text/html' ) !== false;
			}
		}

		return true;
	}

	public function process( $html ) {
		$html = $this->remove_resource_hints( $html );
		$html = $this->replace_kits( $html );
		$html = $this->process_tags( $html );
		$html = $this->process_webfont_loader( $html );
		$html = $this->replace_remaining( $html );
		$html = $this->remove_orphaned_hints( $html );

		return apply_filters( 'wpdf_processed_html', $html );
	}

	/**
	 * Preconnect / DNS prefetch hints alone already open a connection to Google / Font Awesome.
	 */
	private function remove_resource_hints( $html ) {
		return preg_replace_callback(
			'~<link\b[^>]*>~i',
			static function ( $match ) {
				$rel  = strtolower( (string) self::get_attribute( $match[0], 'rel' ) );
				$href = (string) self::get_attribute( $match[0], 'href' );

				if ( preg_match( '~\b(preconnect|dns-prefetch)\b~', $rel ) && Hosts::is_handled( $href ) ) {
					return '';
				}

				return $match[0];
			},
			$html
		);
	}

	/**
	 * WordPress adds dns-prefetch hints for the hosts of all enqueued files, e.g. //ajax.googleapis.com or
	 * //maxcdn.bootstrapcdn.com. Once their files are local, those hints point to hosts the page no longer uses.
	 */
	private function remove_orphaned_hints( $html ) {
		return preg_replace_callback(
			'~<link\b[^>]*>\s*~i',
			static function ( $match ) use ( $html ) {
				$rel  = strtolower( (string) self::get_attribute( $match[0], 'rel' ) );
				$href = Hosts::normalize( (string) self::get_attribute( $match[0], 'href' ) );
				$host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );

				if ( ! preg_match( '~\b(preconnect|dns-prefetch)\b~', $rel ) || ! preg_match( '~^(?:' . Hosts::HOST_PATTERN . ')$~i', $host ) ) {
					return $match[0];
				}

				// Still in use by something that isn't ours to touch, e.g. jQuery from cdnjs.
				if ( preg_match( '~(?:https?:)?(?:\\\\?/){2}' . preg_quote( $host, '~' ) . '(?:\\\\?/)~i', $html ) ) {
					return $match[0];
				}

				return '';
			},
			$html
		);
	}

	/**
	 * Removes <script src="https://kit.fontawesome.com/abc123.js"> tags that a theme prints directly. The kit is
	 * remembered, so enqueue_kits() adds the local replacement from the next page view on.
	 */
	private function replace_kits( $html ) {
		if ( ! Settings::get( 'fontawesome' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'~<script\b[^>]*>\s*</script>~i',
			static function ( $match ) {
				$src = self::get_attribute( $match[0], 'src' );

				if ( ! $src || ! Hosts::is_kit_script( $src ) ) {
					return $match[0];
				}

				Localizer::instance()->kit_stylesheets( $src );

				return '';
			},
			$html
		);
	}

	/**
	 * Rewrites href / src of <link> and <script> tags. Subresource integrity hashes are dropped where a URL changed,
	 * because rewritten stylesheets no longer match them.
	 */
	private function process_tags( $html ) {
		return preg_replace_callback(
			'~<(link|script)\b[^>]*>~i',
			function ( $match ) {
				$tag     = $match[0];
				$changed = false;

				foreach ( self::ATTRIBUTES as $attribute ) {
					$value = self::get_attribute( $tag, $attribute );

					if ( $value === null || $value === '' ) {
						continue;
					}

					$new = null;

					if ( Hosts::is_handled( $value ) ) {
						$new = Localizer::instance()->localize( $value ) ?: self::BLOCKED;
					} elseif ( strtolower( $match[1] ) === 'link' && Settings::get( 'local_css' ) ) {
						$new = Localizer::instance()->local_css( Hosts::normalize( $value ) );
					}

					if ( $new ) {
						$tag     = self::set_attribute( $tag, $attribute, $new );
						$changed = true;
					}
				}

				if ( $changed ) {
					$tag = preg_replace( '~\sintegrity\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $tag );
				}

				return $tag;
			},
			$html
		);
	}

	/**
	 * WebFontConfig / WebFont.load() with a "google" module: the families are loaded from a local stylesheet
	 * through the loader's "custom" module instead.
	 */
	private function process_webfont_loader( $html ) {
		if ( ! Settings::get( 'google' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'~\bgoogle\s*:\s*\{\s*families\s*:\s*\[([^\]]*)\]\s*,?\s*\}~',
			static function ( $match ) {
				preg_match_all( '~["\']([^"\']+)["\']~', $match[1], $families );

				$names = [];
				$query = [];

				foreach ( $families[1] as $family ) {
					$parts   = explode( ':', $family );
					$names[] = trim( $parts[0] );
					$query[] = str_replace( ' ', '+', trim( $parts[0] ) ) . ( ! empty( $parts[1] ) ? ':' . $parts[1] : '' );
				}

				$local = $query ? Localizer::instance()->localize( 'https://fonts.googleapis.com/css?family=' . implode( '|', $query ) ) : false;

				return 'custom:{families:' . wp_json_encode( $local ? $names : [] ) . ',urls:' . wp_json_encode( $local ? [ $local ] : [] ) . '}';
			},
			$html
		);
	}

	/**
	 * Everything outside <link>/<script> attributes: inline CSS (@import, @font-face), JS strings and JSON.
	 */
	private function replace_remaining( $html ) {
		$html = preg_replace_callback(
			Hosts::url_regex(),
			static function ( $match ) {
				return self::replacement( $match[0] ) ?? $match[0];
			},
			$html
		);

		return preg_replace_callback(
			Hosts::escaped_url_regex(),
			static function ( $match ) {
				$new = self::replacement( Hosts::unescape_json_url( $match[0] ) );

				return $new === null ? $match[0] : str_replace( '/', '\\/', $new );
			},
			$html
		);
	}

	/**
	 * @return string|null Null if the URL belongs to a disabled service and stays as is.
	 */
	private static function replacement( $url ) {
		if ( ! Hosts::is_handled( $url ) ) {
			return null;
		}

		if ( Hosts::is_kit_script( $url ) ) {
			return self::BLOCKED;
		}

		return Localizer::instance()->localize( $url ) ?: self::BLOCKED;
	}

	/**
	 * @return string|null Raw (still HTML-encoded) attribute value, or null if the attribute isn't set.
	 */
	public static function get_attribute( $tag, $name ) {
		if ( preg_match( '~\s' . preg_quote( $name, '~' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $match ) ) {
			return ( $match[1] ?? '' ) . ( $match[2] ?? '' ) . ( $match[3] ?? '' );
		}

		return null;
	}

	private static function set_attribute( $tag, $name, $value ) {
		return preg_replace_callback(
			'~(\s' . preg_quote( $name, '~' ) . '\s*=\s*)(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i',
			static function ( $match ) use ( $value ) {
				return $match[1] . '"' . esc_attr( $value ) . '"';
			},
			$tag,
			1
		);
	}
}
