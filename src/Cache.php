<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

class Cache {
	/**
	 * Deletes all local copies. They are downloaded again on the next page view.
	 */
	public static function flush_all() {
		Storage::flush();
		delete_option( Localizer::MAP_OPTION );
		delete_option( Localizer::ERRORS_OPTION );
		Localizer::reset();
		Storage::ensure_dir();
		self::purge_page_caches();
	}

	/**
	 * Cached pages still contain the old URLs, so common page cache plugins are purged as well.
	 */
	public static function purge_page_caches() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		if ( function_exists( 'wpo_cache_flush' ) ) {
			wpo_cache_flush();
		}

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}

		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
		}

		do_action( 'litespeed_purge_all' );
		do_action( 'cache_enabler_clear_complete_cache' );
		do_action( 'breeze_clear_all_cache' );
		do_action( 'wphb_clear_page_cache' );
		do_action( 'wpdf_purge_page_caches' );
	}
}
