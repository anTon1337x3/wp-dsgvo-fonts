<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Local copies live in wp-content/uploads/local-fonts/{folder}/{path}.
 */
class Storage {
	const DIR = 'local-fonts';

	/**
	 * Folders used under the plugin's earlier names "WP DSGVO Fonts" and "DSGVO Fonts".
	 */
	const LEGACY_DIRS = [ 'wp-dsgvo-fonts', 'dsgvo-fonts' ];

	/**
	 * Bumped whenever path_for() changes, so copies stored under the old layout get replaced.
	 */
	const LAYOUT = 4;

	// The host names below are what this plugin looks for in order to replace them with local copies; nothing is
	// loaded from these hosts in the browser.
	// phpcs:disable PluginCheck.CodeAnalysis.Offloading.OffloadedContent

	/**
	 * Neutral folder names: GDPR scanners search the page source for the third-party host names, and a local URL
	 * like /uploads/…/fonts.googleapis.com/… would be reported as a request to Google.
	 */
	const FOLDERS = [
		'fonts.googleapis.com'       => 'google-fonts/css',
		'fonts.gstatic.com'          => 'google-fonts/files',
		'ajax.googleapis.com'        => 'google-fonts/loader',
		'cdnjs.cloudflare.com'       => 'font-awesome/cdnjs',
		'cdn.jsdelivr.net'           => 'font-awesome/jsdelivr',
		'maxcdn.bootstrapcdn.com'    => 'font-awesome/bootstrapcdn',
		'stackpath.bootstrapcdn.com' => 'font-awesome/bootstrapcdn',
		'netdna.bootstrapcdn.com'    => 'font-awesome/bootstrapcdn',
	];

	// phpcs:enable PluginCheck.CodeAnalysis.Offloading.OffloadedContent

	const EXTENSIONS = [ 'css', 'js', 'woff2', 'woff', 'ttf', 'otf', 'eot', 'svg' ];

	const CONTENT_TYPES = [
		'font/woff2'                    => 'woff2',
		'application/font-woff2'        => 'woff2',
		'font/woff'                     => 'woff',
		'application/font-woff'         => 'woff',
		'font/ttf'                      => 'ttf',
		'font/sfnt'                     => 'ttf',
		'application/x-font-ttf'        => 'ttf',
		'font/otf'                      => 'otf',
		'application/vnd.ms-fontobject' => 'eot',
		'image/svg+xml'                 => 'svg',
		'text/css'                      => 'css',
	];

	public static function base_dir() {
		return trailingslashit( wp_get_upload_dir()['basedir'] ) . self::DIR;
	}

	/**
	 * Root-relative when the uploads folder is on this site's domain, absolute otherwise.
	 */
	public static function base_url() {
		return Hosts::shorten( trailingslashit( wp_get_upload_dir()['baseurl'] ) . self::DIR );
	}

	public static function url( $rel ) {
		return self::base_url() . '/' . $rel;
	}

	public static function exists( $rel ) {
		return $rel && is_file( self::base_dir() . '/' . $rel );
	}

	public static function ensure_dir() {
		$dir = self::base_dir();

		if ( wp_mkdir_p( $dir ) && ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Maps a remote URL to a relative path inside the storage directory.
	 *
	 * @return string|null Null if the file type isn't one we're willing to store.
	 */
	public static function path_for( $url, $content_type = '' ) {
		$parts  = wp_parse_url( $url );
		$host   = strtolower( $parts['host'] ?? '' );
		$path   = $parts['path'] ?? '';
		$folder = self::folder_for( $host );

		// Google Fonts stylesheets are identified by their query string, not their path.
		if ( $host === 'fonts.googleapis.com' ) {
			return $folder . '/' . md5( $url ) . '.css';
		}

		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, self::EXTENSIONS, true ) ) {
			$type = strtolower( trim( explode( ';', $content_type )[0] ) );
			$ext  = self::CONTENT_TYPES[ $type ] ?? '';

			return $ext ? $folder . '/' . md5( $url ) . '.' . $ext : null;
		}

		$segments = [];

		foreach ( explode( '/', $path ) as $segment ) {
			if ( $segment === '' || $segment === '.' || $segment === '..' ) {
				continue;
			}

			$segments[] = preg_replace( '~[^A-Za-z0-9._@-]~', '_', rawurldecode( $segment ) );
		}

		return $folder . '/' . implode( '/', $segments );
	}

	private static function folder_for( $host ) {
		if ( isset( self::FOLDERS[ $host ] ) ) {
			return self::FOLDERS[ $host ];
		}

		// use.fontawesome.com → font-awesome/use, ka-f.fontawesome.com → font-awesome/ka-f, …
		if ( preg_match( '~^([a-z0-9-]+)\.fontawesome\.com$~', $host, $match ) ) {
			return 'font-awesome/' . $match[1];
		}

		return 'other/' . substr( md5( $host ), 0, 10 );
	}

	public static function write( $rel, $contents ) {
		$file = self::base_dir() . '/' . $rel;

		if ( ! wp_mkdir_p( dirname( $file ) ) ) {
			return false;
		}

		return file_put_contents( $file, $contents, LOCK_EX ) !== false;
	}

	/**
	 * @return array{files: int, bytes: int}
	 */
	public static function stats() {
		$stats = [ 'files' => 0, 'bytes' => 0 ];

		foreach ( self::iterate() as $file ) {
			if ( $file->isFile() && $file->getFilename() !== 'index.php' ) {
				$stats['files']++;
				$stats['bytes'] += $file->getSize();
			}
		}

		return $stats;
	}

	public static function flush() {
		self::delete_dir( self::base_dir() );

		foreach ( self::LEGACY_DIRS as $legacy ) {
			self::delete_dir( trailingslashit( wp_get_upload_dir()['basedir'] ) . $legacy );
		}
	}

	private static function delete_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		// Always the direct method: these are our own files in the uploads folder, and flushing also runs on
		// frontend requests where no FTP credentials could be asked for.
		( new \WP_Filesystem_Direct( null ) )->rmdir( $dir, true );
	}

	private static function iterate() {
		$dir = self::base_dir();

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		return new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
	}
}
