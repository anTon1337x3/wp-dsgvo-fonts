<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Turns remote Google Fonts / Font Awesome URLs into local copies and remembers the result.
 */
class Localizer {
	const MAP_OPTION    = 'wpdf_map';
	const ERRORS_OPTION = 'wpdf_errors';

	/**
	 * Don't retry a failed download on every page view.
	 */
	const RETRY_AFTER = 15 * MINUTE_IN_SECONDS;

	const MAX_ERRORS = 50;

	const MAX_LOCAL_CSS_BYTES = 5242880;

	/**
	 * Matches url(...) references, @import url(...) and @import "...".
	 */
	const CSS_REFERENCE = '~(@import\s+)?url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s]*))\s*\)|@import\s+(["\'])([^"\']+)\5~i';

	/** @var self|null */
	private static $instance;

	/** @var array Source URL (or local:{path}:{mtime}) => entry. */
	private $map;

	/** @var array Entries added or replaced during this request. */
	private $changes = [];

	/** @var string[] Keys removed during this request. */
	private $removed = [];

	/** @var array|null Lazy loaded, it's only needed when something goes wrong. */
	private $errors;

	private $errors_dirty = false;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function reset() {
		self::$instance = null;
	}

	private function __construct() {
		$map       = get_option( self::MAP_OPTION, [] );
		$this->map = is_array( $map ) ? $map : [];
	}

	public function entries() {
		return $this->map;
	}

	/**
	 * @return string[] URLs of the Font Awesome Kits seen on this site so far.
	 */
	public function known_kits() {
		return array_keys(
			array_filter(
				$this->map,
				static function ( $entry ) {
					return ! empty( $entry['version'] );
				}
			)
		);
	}

	/**
	 * @return string|false Local URL, or false if the file couldn't be downloaded.
	 */
	public function localize( $url ) {
		$url = Hosts::normalize( preg_replace( '~#.*$~', '', $url ) );

		// Kits are JavaScript that loads more from Font Awesome; Processor swaps the whole <script> tag instead.
		if ( Hosts::is_kit_script( $url ) ) {
			return false;
		}

		if ( isset( $this->map[ $url ] ) && Storage::exists( $this->map[ $url ]['file'] ) ) {
			return Storage::url( $this->map[ $url ]['file'] );
		}

		if ( $this->recently_failed( $url ) ) {
			return false;
		}

		Storage::ensure_dir();

		$rel = $this->download( $url );

		if ( is_wp_error( $rel ) ) {
			$this->log_error( $url, $rel->get_error_message() );

			return false;
		}

		$this->remember( $url, [ 'file' => $rel, 'type' => Hosts::classify( $url ) ] );
		$this->clear_error( $url );

		return Storage::url( $rel );
	}

	/**
	 * @return string|\WP_Error Relative storage path.
	 */
	private function download( $url ) {
		$response = Downloader::fetch( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rel = Storage::path_for( $url, $response['type'] );

		if ( ! $rel ) {
			return new \WP_Error( 'wpdf_type', __( 'Unknown file type', 'knuspermedia-local-fonts-icons' ) );
		}

		$body = $response['body'];

		if ( substr( $rel, -4 ) === '.css' ) {
			$body = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === 'fonts.googleapis.com'
				? $this->prepare_google_css( $body )
				: $body;

			if ( is_wp_error( $body ) ) {
				return $body;
			}

			$body = $this->rewrite_css( $body, $url )[0];
		}

		if ( ! Storage::write( $rel, $body ) ) {
			/* translators: %s: file path */
			return new \WP_Error( 'wpdf_write', sprintf( __( 'File could not be saved: %s', 'knuspermedia-local-fonts-icons' ), $rel ) );
		}

		return $rel;
	}

	/**
	 * Drops @font-face rules for unwanted subsets and adds font-display.
	 */
	private function prepare_google_css( $css ) {
		if ( stripos( $css, '@font-face' ) === false ) {
			return new \WP_Error( 'wpdf_google_css', __( 'No @font-face rules in the response from Google', 'knuspermedia-local-fonts-icons' ) );
		}

		$allowed  = (array) Settings::get( 'subsets' );
		$filtered = preg_replace_callback(
			'~(?:/\*\s*([^*]*?)\s*\*/\s*)?@font-face\s*\{[^}]*\}\s*~i',
			static function ( $match ) use ( $allowed ) {
				$subset = $match[1] ?? '';

				// Unlabeled blocks and numbered slices (e.g. [12] for CJK fonts) are always kept.
				if ( ! in_array( $subset, Settings::SUBSETS, true ) || in_array( $subset, $allowed, true ) ) {
					return $match[0];
				}

				return '';
			},
			$css
		);

		// None of the selected subsets exist for this font: better keep everything than show no font at all.
		if ( stripos( $filtered, '@font-face' ) !== false ) {
			$css = $filtered;
		}

		$display = Settings::get( 'font_display' );

		if ( $display !== 'keep' ) {
			$css = preg_replace_callback(
				'~@font-face\s*\{([^}]*)\}~i',
				static function ( $match ) use ( $display ) {
					if ( stripos( $match[1], 'font-display' ) !== false ) {
						return $match[0];
					}

					return '@font-face {' . rtrim( $match[1] ) . "\n  font-display: " . $display . ";\n}";
				},
				$css
			);
		}

		return $css;
	}

	/**
	 * Replaces Google Fonts / Font Awesome references with local copies and makes every other reference absolute,
	 * because the rewritten stylesheet is stored in a different location than the original.
	 *
	 * @param string        $css
	 * @param string        $base         URL the stylesheet was loaded from.
	 * @param callable|null $local_import Gets the URL of an @import on this site, returns a replacement URL or null.
	 *
	 * @return array{0: string, 1: bool} Rewritten CSS and whether a Google Fonts / Font Awesome reference was replaced.
	 */
	public function rewrite_css( $css, $base, $local_import = null ) {
		$changed = false;

		$css = preg_replace_callback(
			self::CSS_REFERENCE,
			function ( $match ) use ( $base, $local_import, &$changed ) {
				// Only one of the alternative groups matched, the others are empty or missing.
				$is_url = ! isset( $match[6] );
				$ref    = trim( $is_url ? ( $match[2] ?? '' ) . ( $match[3] ?? '' ) . ( $match[4] ?? '' ) : $match[6] );
				$import = $is_url ? ! empty( $match[1] ) : true;

				if ( $ref === '' || preg_match( '~^(data:|#|about:)~i', $ref ) ) {
					return $match[0];
				}

				$abs = Hosts::resolve( $base, $ref );

				if ( Hosts::is_handled( $abs ) ) {
					$changed  = true;
					$fragment = strpos( $abs, '#' ) !== false ? substr( $abs, strpos( $abs, '#' ) ) : '';
					$local    = $this->localize( $abs );
					$new      = $local ? $local . $fragment : Processor::BLOCKED;
				} elseif ( $import && $local_import && ( $replacement = $local_import( $abs ) ) ) {
					$changed = true;
					$new     = $replacement;
				} else {
					$new = Hosts::shorten( $abs );
				}

				if ( ! $is_url ) {
					return '@import "' . $new . '"';
				}

				return ( $import ? $match[1] : '' ) . 'url("' . str_replace( '"', '%22', $new ) . '")';
			},
			$css
		);

		return [ $css, $changed ];
	}

	/**
	 * Font Awesome Kits are replaced by the matching Font Awesome Free release, downloaded server-side.
	 *
	 * @return string[] Local stylesheet URLs.
	 */
	public function kit_stylesheets( $kit_url ) {
		$kit_url = preg_replace( '~[?#].*$~', '', Hosts::normalize( $kit_url ) );
		// Also remembers the kit, so it is replaced on every page view even if its tag is printed by the theme.
		if ( empty( $this->map[ $kit_url ]['version'] ) ) {
			$this->remember( $kit_url, [ 'file' => '', 'type' => 'fontawesome', 'version' => $this->detect_kit_version( $kit_url ) ] );
		}

		$version = Settings::get( 'fa_kit_version' ) ?: $this->map[ $kit_url ]['version'];

		// Downloaded server-side and served locally, like every other file.
		$base  = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@' . $version . '/css/'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent
		$files = Settings::get( 'fa_kit_shims' ) ? [ 'all.min.css', 'v4-shims.min.css' ] : [ 'all.min.css' ];
		$urls  = [];

		foreach ( $files as $file ) {
			$local = $this->localize( $base . $file );

			if ( $local ) {
				$urls[] = $local;
			}
		}

		return $urls;
	}

	private function detect_kit_version( $kit_url ) {
		$response = Downloader::fetch( $kit_url );

		if ( is_wp_error( $response ) ) {
			$this->log_error(
				$kit_url,
				/* translators: 1: error message, 2: Font Awesome version number */
				sprintf( __( 'Kit version could not be detected (%1$s), using Font Awesome Free %2$s', 'knuspermedia-local-fonts-icons' ), $response->get_error_message(), Settings::FA_FALLBACK_VERSION )
			);

			return Settings::FA_FALLBACK_VERSION;
		}

		if ( preg_match( '~"license"\s*:\s*"pro"~i', $response['body'] ) ) {
			$this->log_error( $kit_url, __( 'Pro kit detected: Font Awesome Free is provided locally, Pro icons are missing.', 'knuspermedia-local-fonts-icons' ) );
		}

		if ( preg_match( '~"version"\s*:\s*"(\d+\.\d+\.\d+)"~', $response['body'], $match ) ) {
			return $match[1];
		}

		return Settings::FA_FALLBACK_VERSION;
	}

	/**
	 * Local stylesheets that @import Google Fonts or Font Awesome are copied and the copy is rewritten.
	 *
	 * @return string|null URL of the rewritten copy, or null if the original can stay.
	 */
	public function local_css( $url, $depth = 0 ) {
		$path = $depth > 3 ? null : self::url_to_path( $url );

		if ( ! $path ) {
			return null;
		}

		$key = 'local:' . $path . ':' . filemtime( $path );

		if ( isset( $this->map[ $key ] ) ) {
			if ( $this->map[ $key ]['file'] === '' ) {
				return null;
			}

			if ( Storage::exists( $this->map[ $key ]['file'] ) ) {
				return Storage::url( $this->map[ $key ]['file'] );
			}
		}

		$css = filesize( $path ) <= self::MAX_LOCAL_CSS_BYTES ? file_get_contents( $path ) : false;

		if ( $css === false ) {
			return null;
		}

		$rel     = '';
		$changed = false;

		if ( Hosts::find_urls( $css ) || stripos( $css, '@import' ) !== false ) {
			$absolute = Hosts::resolve( home_url( '/' ), $url );

			[ $rewritten, $changed ] = $this->rewrite_css(
				$css,
				$absolute,
				function ( $import ) use ( $depth ) {
					return $this->local_css( $import, $depth + 1 );
				}
			);

			if ( $changed ) {
				$rel = 'local-css/' . md5( $path ) . '.css';
				Storage::ensure_dir();

				if ( ! Storage::write( $rel, "/* Copy of " . Hosts::shorten( $absolute ) . " (knuspermedia Local Fonts & Icons) */\n" . $rewritten ) ) {
					return null;
				}
			}
		}

		// Forget earlier versions of this file.
		foreach ( array_keys( $this->map ) as $existing ) {
			if ( strpos( $existing, 'local:' . $path . ':' ) === 0 && $existing !== $key ) {
				unset( $this->map[ $existing ] );
				$this->removed[] = $existing;
			}
		}

		$this->remember( $key, [ 'file' => $rel, 'type' => 'local' ] );

		return $rel ? Storage::url( $rel ) : null;
	}

	/**
	 * Maps a URL of a stylesheet on this site to its file, if it's inside the WordPress installation.
	 */
	public static function url_to_path( $url ) {
		$parts = wp_parse_url( Hosts::normalize( $url ) );

		if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== Hosts::site_host() ) {
			return null;
		}

		$path = rawurldecode( $parts['path'] ?? '' );

		if ( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) !== 'css' ) {
			return null;
		}

		$roots = [
			rtrim( (string) wp_parse_url( content_url(), PHP_URL_PATH ), '/' ) => WP_CONTENT_DIR,
			rtrim( (string) wp_parse_url( site_url(), PHP_URL_PATH ), '/' )    => ABSPATH,
		];

		foreach ( $roots as $prefix => $dir ) {
			if ( $prefix !== '' && strpos( $path, $prefix . '/' ) !== 0 ) {
				continue;
			}

			$file = realpath( rtrim( $dir, '/' ) . '/' . ltrim( substr( $path, strlen( $prefix ) ), '/' ) );

			if ( ! $file || ! is_file( $file ) ) {
				return null;
			}

			$inside_wp = strpos( $file, realpath( ABSPATH ) ) === 0 || strpos( $file, realpath( WP_CONTENT_DIR ) ) === 0;
			$storage   = realpath( Storage::base_dir() );

			if ( ! $inside_wp || ( $storage && strpos( $file, $storage ) === 0 ) ) {
				return null;
			}

			return $file;
		}

		return null;
	}

	private function remember( $key, $entry ) {
		$entry['time']         = time();
		$this->map[ $key ]     = $entry;
		$this->changes[ $key ] = $entry;
	}

	private function load_errors() {
		if ( $this->errors === null ) {
			$errors       = get_option( self::ERRORS_OPTION, [] );
			$this->errors = is_array( $errors ) ? $errors : [];
		}
	}

	public function errors() {
		$this->load_errors();

		return $this->errors;
	}

	private function recently_failed( $url ) {
		$this->load_errors();

		return isset( $this->errors[ $url ] ) && time() - $this->errors[ $url ]['time'] < self::RETRY_AFTER;
	}

	public function log_error( $url, $message ) {
		$this->load_errors();
		unset( $this->errors[ $url ] );

		$this->errors[ $url ] = [ 'message' => $message, 'time' => time() ];
		$this->errors         = array_slice( $this->errors, -self::MAX_ERRORS, null, true );
		$this->errors_dirty   = true;
	}

	private function clear_error( $url ) {
		$this->load_errors();

		if ( isset( $this->errors[ $url ] ) ) {
			unset( $this->errors[ $url ] );
			$this->errors_dirty = true;
		}
	}

	/**
	 * Persists what was learned during this request. Re-reads the stored map first, so parallel requests that
	 * localized other files don't overwrite each other.
	 */
	public function save() {
		if ( $this->changes || $this->removed ) {
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( self::MAP_OPTION, 'options' );

			$stored = get_option( self::MAP_OPTION, [] );
			$stored = array_merge( is_array( $stored ) ? $stored : [], $this->changes );

			foreach ( $this->removed as $key ) {
				unset( $stored[ $key ] );
			}

			update_option( self::MAP_OPTION, $stored, true );

			$this->map     = $stored;
			$this->changes = [];
			$this->removed = [];
		}

		if ( $this->errors_dirty ) {
			update_option( self::ERRORS_OPTION, $this->errors, false );
			$this->errors_dirty = false;
		}
	}
}
