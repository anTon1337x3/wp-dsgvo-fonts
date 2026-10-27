<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

class Admin {
	const PAGE = 'wp-dsgvo-fonts';

	const CONFLICTING_PLUGINS = [
		'host-webfonts-local/host-webfonts-local.php' => 'OMGF',
		'host-google-fonts-pro/host-google-fonts-pro.php' => 'OMGF Pro',
	];

	public function init() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_wpdf_flush', [ $this, 'handle_flush' ] );
		add_action( 'admin_post_wpdf_scan', [ $this, 'handle_scan' ] );
		add_action( 'admin_notices', [ $this, 'conflict_notice' ] );
		// Changed subsets or font-display only apply to newly downloaded stylesheets.
		add_action( 'add_option_' . Settings::OPTION, [ Cache::class, 'flush_all' ] );
		add_action( 'update_option_' . Settings::OPTION, [ Cache::class, 'flush_all' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WPDF_FILE ), [ $this, 'action_links' ] );
	}

	public function menu() {
		add_options_page( 'DSGVO Fonts', 'DSGVO Fonts', 'manage_options', self::PAGE, [ $this, 'render' ] );
	}

	public function register_settings() {
		register_setting( self::PAGE, Settings::OPTION, [ 'sanitize_callback' => [ Settings::class, 'sanitize' ] ] );
	}

	public function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">Einstellungen</a>', esc_url( $this->page_url() ) ) );

		return $links;
	}

	private function page_url( $args = [] ) {
		return add_query_arg( array_merge( [ 'page' => self::PAGE ], $args ), admin_url( 'options-general.php' ) );
	}

	public function conflict_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			return;
		}

		foreach ( self::CONFLICTING_PLUGINS as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				printf(
					'<div class="notice notice-warning"><p><strong>WP DSGVO Fonts:</strong> %s ist ebenfalls aktiv. Bitte deaktiviere %s, damit sich die beiden Plugins nicht gegenseitig in die Quere kommen.</p></div>',
					esc_html( $name ),
					esc_html( $name )
				);
			}
		}
	}

	public function handle_flush() {
		check_admin_referer( 'wpdf_flush' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		Cache::flush_all();

		wp_safe_redirect( $this->page_url( [ 'wpdf_notice' => 'flushed' ] ) );
		exit;
	}

	public function handle_scan() {
		check_admin_referer( 'wpdf_scan' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$url = esc_url_raw( wp_unslash( $_POST['wpdf_scan_url'] ?? '' ) );

		if ( ! $url || strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== Hosts::site_host() ) {
			$url = home_url( '/' );
		}

		set_transient( 'wpdf_scan_' . get_current_user_id(), Scanner::scan( $url ), HOUR_IN_SECONDS );

		wp_safe_redirect( $this->page_url( [ 'wpdf_notice' => 'scanned' ] ) . '#wpdf-scan' );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::all();
		$entries  = Localizer::instance()->entries();
		$errors   = Localizer::instance()->errors();
		$stats    = Storage::stats();
		$scan     = get_transient( 'wpdf_scan_' . get_current_user_id() );
		$notice   = sanitize_key( $_GET['wpdf_notice'] ?? '' );
		?>
		<div class="wrap">
			<h1>WP DSGVO Fonts</h1>
			<p>Google Fonts und Font Awesome werden beim ersten Seitenaufruf vom Server heruntergeladen und anschließend lokal ausgeliefert. Besucher stellen keine Verbindung zu Google oder Font Awesome her.</p>

			<?php if ( $notice === 'flushed' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Der Cache wurde geleert. Fonts werden beim nächsten Seitenaufruf neu heruntergeladen.</p></div>
			<?php elseif ( $notice === 'scanned' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Die Seitenprüfung ist abgeschlossen.</p></div>
			<?php elseif ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert. Der Font-Cache wurde geleert.</p></div>
			<?php endif; ?>

			<h2>Status</h2>
			<p>
				<?php
				printf(
					'%d lokale Dateien (%s) in <code>%s</code>',
					(int) $stats['files'],
					esc_html( size_format( $stats['bytes'] ) ?: '0 B' ),
					esc_html( Storage::base_url() )
				);
				?>
			</p>
			<?php $this->render_entries( $entries ); ?>
			<?php $this->render_errors( $errors ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpdf_flush" />
				<?php wp_nonce_field( 'wpdf_flush' ); ?>
				<?php submit_button( 'Cache leeren', 'secondary', 'submit', false ); ?>
			</form>

			<h2 id="wpdf-scan">Seite prüfen</h2>
			<p>Ruft eine Seite wie ein nicht angemeldeter Besucher ab und listet alle verbliebenen Verweise auf Google Fonts oder Font Awesome im HTML und in den eingebundenen CSS-Dateien.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpdf_scan" />
				<?php wp_nonce_field( 'wpdf_scan' ); ?>
				<input type="url" name="wpdf_scan_url" class="regular-text" value="<?php echo esc_attr( $scan['url'] ?? home_url( '/' ) ); ?>" />
				<?php submit_button( 'Prüfen', 'primary', 'submit', false ); ?>
			</form>
			<?php $this->render_scan( $scan ); ?>

			<h2>Einstellungen</h2>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<?php $name = Settings::OPTION; ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Dienste</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[google]" value="1" <?php checked( $settings['google'] ); ?> /> Google Fonts lokal hosten</label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fontawesome]" value="1" <?php checked( $settings['fontawesome'] ); ?> /> Font Awesome lokal hosten</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Zeichensätze (Google Fonts)</th>
						<td>
							<?php foreach ( Settings::SUBSETS as $subset => $label ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[subsets][]" value="<?php echo esc_attr( $subset ); ?>" <?php checked( in_array( $subset, $settings['subsets'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
							<?php endforeach; ?>
							<p class="description">Bietet eine Schrift keinen der gewählten Zeichensätze an, werden alle ihre Dateien geladen.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpdf-font-display">font-display</label></th>
						<td>
							<select id="wpdf-font-display" name="<?php echo esc_attr( $name ); ?>[font_display]">
								<?php foreach ( Settings::FONT_DISPLAY as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_display'], $value ); ?>><?php echo esc_html( $value === 'keep' ? 'Nicht ändern' : $value ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">„swap“ zeigt Text sofort in einer Ersatzschrift an und verhindert unsichtbaren Text während des Ladens.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Lokale CSS-Dateien</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[local_css]" value="1" <?php checked( $settings['local_css'] ); ?> /> CSS-Dateien von Theme und Plugins nach <code>@import</code> von Google Fonts / Font Awesome durchsuchen und bereinigte Kopien ausliefern</label>
						</td>
					</tr>
					<tr>
						<th scope="row">JavaScript-Schutz</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[js_guard]" value="1" <?php checked( $settings['js_guard'] ); ?> /> Per JavaScript nachgeladene Google-Fonts- und Font-Awesome-Dateien blockieren</label>
							<p class="description">Greift z. B. bei Slidern oder Widgets, die Schriften erst im Browser einfügen. Diese Elemente zeigen dann die Ersatzschrift.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpdf-kit-version">Font Awesome Kits</label></th>
						<td>
							<input id="wpdf-kit-version" type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[fa_kit_version]" value="<?php echo esc_attr( $settings['fa_kit_version'] ); ?>" placeholder="auto" />
							<p class="description">Kits (<code>kit.fontawesome.com/….js</code>) werden durch eine lokale Font Awesome Free ersetzt. Leer lassen, um die Version des Kits zu übernehmen (Ersatz: <?php echo esc_html( Settings::FA_FALLBACK_VERSION ); ?>). Pro-Icons und eigene Kit-Icons sind nicht enthalten.</p>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fa_kit_shims]" value="1" <?php checked( $settings['fa_kit_shims'] ); ?> /> Kompatibilität für Font-Awesome-4-Klassen (<code>fa fa-…</code>) mitladen</label>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Speichern und Cache leeren' ); ?>
			</form>
		</div>
		<?php
	}

	private function render_entries( $entries ) {
		$rows = array_filter(
			$entries,
			static function ( $entry ) {
				return $entry['type'] !== 'local' || $entry['file'] !== '';
			}
		);

		if ( ! $rows ) {
			echo '<p><em>Noch keine Dateien lokalisiert. Rufe eine Seite auf oder nutze „Seite prüfen“.</em></p>';

			return;
		}

		$labels = [
			'google'      => 'Google Fonts',
			'fontawesome' => 'Font Awesome',
			'local'       => 'Lokale CSS-Kopie',
		];
		?>
		<details>
			<summary><?php echo esc_html( sprintf( '%d lokalisierte Quellen anzeigen', count( $rows ) ) ); ?></summary>
			<table class="widefat striped" style="margin-top:8px">
				<thead><tr><th>Typ</th><th>Quelle</th><th>Lokale Datei</th><th>Datum</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $source => $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $labels[ $entry['type'] ] ?? $entry['type'] ); ?></td>
						<td style="word-break:break-all"><code><?php echo esc_html( preg_replace( '~^local:(.*):\d+$~', '$1', $source ) ); ?></code></td>
						<td style="word-break:break-all">
							<?php if ( ! empty( $entry['version'] ) ) : ?>
								Font Awesome Free <?php echo esc_html( $entry['version'] ); ?>
							<?php else : ?>
								<code><?php echo esc_html( $entry['file'] ); ?></code>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', $entry['time'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</details>
		<?php
	}

	private function render_errors( $errors ) {
		if ( ! $errors ) {
			return;
		}
		?>
		<div class="notice notice-warning inline">
			<p><strong>Hinweise und Fehler:</strong> Nicht ladbare Dateien werden blockiert (die Seite nutzt dann die Ersatzschrift) und nach <?php echo (int) ( Localizer::RETRY_AFTER / MINUTE_IN_SECONDS ); ?> Minuten erneut versucht.</p>
			<ul>
				<?php foreach ( array_reverse( $errors, true ) as $url => $error ) : ?>
					<li><code style="word-break:break-all"><?php echo esc_html( $url ); ?></code>: <?php echo esc_html( $error['message'] ); ?> (<?php echo esc_html( wp_date( 'd.m.Y H:i', $error['time'] ) ); ?>)</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private function render_scan( $scan ) {
		if ( ! is_array( $scan ) ) {
			return;
		}

		printf( '<p>Ergebnis für <a href="%1$s" target="_blank" rel="noopener">%1$s</a> vom %2$s:</p>', esc_url( $scan['url'] ), esc_html( wp_date( 'd.m.Y H:i', $scan['time'] ) ) );

		if ( ! empty( $scan['error'] ) ) {
			printf( '<div class="notice notice-error inline"><p>Die Seite konnte nicht geprüft werden: %s</p></div>', esc_html( $scan['error'] ) );

			return;
		}

		if ( ! $scan['findings'] ) {
			echo '<div class="notice notice-success inline"><p>✅ Keine Verweise auf Google Fonts oder Font Awesome gefunden.</p></div>';

			return;
		}
		?>
		<div class="notice notice-error inline">
			<p><strong><?php echo esc_html( sprintf( '%d verbliebene Verweise gefunden:', count( $scan['findings'] ) ) ); ?></strong></p>
			<ul>
				<?php foreach ( $scan['findings'] as $finding ) : ?>
					<li><code style="word-break:break-all"><?php echo esc_html( $finding['url'] ); ?></code> in <?php echo esc_html( $finding['where'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>Ist ein Dienst in den Einstellungen deaktiviert, bleiben seine Verweise bewusst erhalten. Bei aktivem Seiten-Cache diesen ggf. leeren und erneut prüfen.</p>
		</div>
		<?php
	}
}
