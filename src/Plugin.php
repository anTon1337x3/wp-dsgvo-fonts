<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

class Plugin {
	/**
	 * Wires up all components. Runs on plugins_loaded.
	 */
	public static function boot() {
		( new Processor() )->init();

		add_action( 'init', [ self::class, 'load_translations' ] );
		add_action( 'init', [ self::class, 'maybe_upgrade' ] );

		if ( is_admin() ) {
			( new Admin() )->init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'dsgvo-fonts', Cli::class );
		}
	}

	/**
	 * Bundled translations, used until (or instead of) a language pack from translate.wordpress.org.
	 */
	public static function load_translations() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled German translation.
		load_plugin_textdomain( 'dsgvo-fonts', false, dirname( plugin_basename( WPDF_FILE ) ) . '/languages' );
	}

	/**
	 * Copies stored under an older folder layout are dropped and downloaded again under the current one.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'wpdf_layout' ) !== Storage::LAYOUT ) {
			Cache::flush_all();
			update_option( 'wpdf_layout', Storage::LAYOUT );
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
