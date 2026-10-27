<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wpdf_settings' );
delete_option( 'wpdf_map' );
delete_option( 'wpdf_errors' );
delete_option( 'wpdf_layout' );

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

$wpdf_filesystem = new WP_Filesystem_Direct( null );
$wpdf_uploads    = trailingslashit( wp_get_upload_dir()['basedir'] );

// Current folder and the one used before the plugin was renamed from "WP DSGVO Fonts".
foreach ( [ 'dsgvo-fonts', 'wp-dsgvo-fonts' ] as $wpdf_dir ) {
	if ( is_dir( $wpdf_uploads . $wpdf_dir ) ) {
		$wpdf_filesystem->rmdir( $wpdf_uploads . $wpdf_dir, true );
	}
}
