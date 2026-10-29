<?php
/**
 * Plugin Name:       knuspermedia Local Fonts & Icons
 * Description:       Self-hosts Google Fonts and Font Awesome automatically, so visitors' browsers no longer connect to Google or Font Awesome servers.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Anton Lapko
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       knuspermedia-local-fonts-icons
 */

defined( 'ABSPATH' ) || exit;

define( 'WPDF_VERSION', '1.2.0' );
define( 'WPDF_FILE', __FILE__ );
define( 'WPDF_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPDsgvoFonts\\';

		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$file = WPDF_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, [ 'WPDsgvoFonts\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WPDsgvoFonts\\Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'WPDsgvoFonts\\Plugin', 'boot' ] );
