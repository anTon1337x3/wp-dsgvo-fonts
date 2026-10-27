<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

class Plugin {
	/**
	 * Wires up all components. Runs on plugins_loaded.
	 */
	public static function boot() {
		( new Processor() )->init();

		if ( is_admin() ) {
			( new Admin() )->init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'dsgvo-fonts', Cli::class );
		}
	}

	public static function activate() {
		Storage::ensure_dir();
		Cache::purge_page_caches();
	}

	/**
	 * Pages cached while the plugin was active contain local URLs that keep working, but we purge anyway so the
	 * site's state is consistent with the plugin being off.
	 */
	public static function deactivate() {
		Cache::purge_page_caches();
	}
}
