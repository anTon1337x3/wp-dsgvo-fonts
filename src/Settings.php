<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

class Settings {
	const OPTION = 'wpdf_settings';

	/**
	 * Subset names as Google Fonts labels them in its stylesheets.
	 */
	const SUBSETS = [ 'latin', 'latin-ext', 'cyrillic', 'cyrillic-ext', 'greek', 'greek-ext', 'vietnamese', 'math', 'symbols' ];

	public static function subset_labels() {
		return array_combine(
			self::SUBSETS,
			[
				__( 'Latin', 'knuspermedia-local-fonts-icons' ),
				__( 'Latin Extended (e.g. ő, ł, ş)', 'knuspermedia-local-fonts-icons' ),
				__( 'Cyrillic', 'knuspermedia-local-fonts-icons' ),
				__( 'Cyrillic Extended', 'knuspermedia-local-fonts-icons' ),
				__( 'Greek', 'knuspermedia-local-fonts-icons' ),
				__( 'Greek Extended', 'knuspermedia-local-fonts-icons' ),
				__( 'Vietnamese', 'knuspermedia-local-fonts-icons' ),
				__( 'Math symbols', 'knuspermedia-local-fonts-icons' ),
				__( 'Symbols', 'knuspermedia-local-fonts-icons' ),
			]
		);
	}

	const FONT_DISPLAY = [ 'swap', 'fallback', 'optional', 'block', 'auto', 'keep' ];

	/**
	 * Free version used to replace Font Awesome Kits whose version can't be detected.
	 */
	const FA_FALLBACK_VERSION = '6.7.2';

	public static function defaults() {
		return [
			'google'         => 1,
			'fontawesome'    => 1,
			'subsets'        => [ 'latin', 'latin-ext' ],
			'font_display'   => 'swap',
			'local_css'      => 1,
			'js_guard'       => 1,
			'fa_kit_version' => '',
			'fa_kit_shims'   => 1,
		];
	}

	public static function all() {
		$saved = get_option( self::OPTION, [] );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	public static function get( $key ) {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : [];
		$clean   = [];
		$subsets = array_intersect( (array) ( $input['subsets'] ?? [] ), self::SUBSETS );

		foreach ( [ 'google', 'fontawesome', 'local_css', 'js_guard', 'fa_kit_shims' ] as $flag ) {
			$clean[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$clean['subsets']        = $subsets ? array_values( $subsets ) : [ 'latin' ];
		$clean['font_display']   = in_array( $input['font_display'] ?? '', self::FONT_DISPLAY, true ) ? $input['font_display'] : 'swap';
		$clean['fa_kit_version'] = preg_match( '/^\d+\.\d+\.\d+$/', trim( $input['fa_kit_version'] ?? '' ) ) ? trim( $input['fa_kit_version'] ) : '';

		return $clean;
	}
}
