<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Local copies live in wp-content/uploads/wp-dsgvo-fonts/{host}/{path}.
 */
class Storage {
	const DIR = 'wp-dsgvo-fonts';

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
		$parts = wp_parse_url( $url );
		$host  = preg_replace( '~[^a-z0-9.-]~', '_', strtolower( $parts['host'] ?? '' ) );
		$path  = $parts['path'] ?? '';

		// Google Fonts stylesheets are identified by their query string, not their path.
		if ( $host === 'fonts.googleapis.com' ) {
			return $host . '/' . md5( $url ) . '.css';
		}

		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, self::EXTENSIONS, true ) ) {
			$type = strtolower( trim( explode( ';', $content_type )[0] ) );
			$ext  = self::CONTENT_TYPES[ $type ] ?? '';

			return $ext ? $host . '/' . md5( $url ) . '.' . $ext : null;
		}

		$segments = [];

		foreach ( explode( '/', $path ) as $segment ) {
			if ( $segment === '' || $segment === '.' || $segment === '..' ) {
				continue;
			}

			$segments[] = preg_replace( '~[^A-Za-z0-9._@-]~', '_', rawurldecode( $segment ) );
		}

		return $host . '/' . implode( '/', $segments );
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
		foreach ( self::iterate() as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
	}

	/**
	 * Children before parents, so directories can be removed after their contents.
	 */
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
