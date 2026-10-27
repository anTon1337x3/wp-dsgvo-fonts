<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wpdf_settings' );
delete_option( 'wpdf_map' );
delete_option( 'wpdf_errors' );

$wpdf_dir = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'wp-dsgvo-fonts';

if ( is_dir( $wpdf_dir ) ) {
	$wpdf_files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $wpdf_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $wpdf_files as $wpdf_file ) {
		$wpdf_file->isDir() ? rmdir( $wpdf_file->getPathname() ) : unlink( $wpdf_file->getPathname() );
	}

	rmdir( $wpdf_dir );
}
