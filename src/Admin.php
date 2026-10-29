<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

class Admin {
	const PAGE = 'knuspermedia-local-fonts-icons';

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
		add_options_page( 'knuspermedia Local Fonts & Icons', 'Local Fonts & Icons', 'manage_options', self::PAGE, [ $this, 'render' ] );
	}

	public function register_settings() {
		register_setting( self::PAGE, Settings::OPTION, [ 'sanitize_callback' => [ Settings::class, 'sanitize' ] ] );
	}

	public function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $this->page_url() ), esc_html__( 'Settings', 'knuspermedia-local-fonts-icons' ) ) );

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
					'<div class="notice notice-warning"><p><strong>knuspermedia Local Fonts & Icons:</strong> %s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: name of another font plugin, e.g. OMGF */
							__( '%s is active as well. Please deactivate it, so the two plugins don\'t interfere with each other.', 'knuspermedia-local-fonts-icons' ),
							$name
						)
					)
				);
			}
		}
	}

	public function handle_flush() {
		check_admin_referer( 'wpdf_flush' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'knuspermedia-local-fonts-icons' ) );
		}

		Cache::flush_all();

		wp_safe_redirect( $this->page_url( [ 'wpdf_notice' => 'flushed' ] ) );
		exit;
	}

	public function handle_scan() {
		check_admin_referer( 'wpdf_scan' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'knuspermedia-local-fonts-icons' ) );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which notice to show.
		$notice = sanitize_key( wp_unslash( $_GET['wpdf_notice'] ?? '' ) );
		$name   = Settings::OPTION;
		?>
		<div class="wrap">
			<h1>knuspermedia Local Fonts & Icons</h1>
			<p><?php esc_html_e( 'Google Fonts and Font Awesome are downloaded by your server on the first page view and served locally afterwards. Visitors\' browsers don\'t connect to Google or Font Awesome.', 'knuspermedia-local-fonts-icons' ); ?></p>

			<?php if ( $notice === 'flushed' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The cache was cleared. Fonts are downloaded again on the next page view.', 'knuspermedia-local-fonts-icons' ); ?></p></div>
			<?php elseif ( $notice === 'scanned' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The page check is complete.', 'knuspermedia-local-fonts-icons' ); ?></p></div>
			<?php elseif ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Set by options.php. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved. The font cache was cleared.', 'knuspermedia-local-fonts-icons' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Status', 'knuspermedia-local-fonts-icons' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: number of files, 2: total size, 3: folder */
					esc_html( _n( '%1$d local file (%2$s) in %3$s', '%1$d local files (%2$s) in %3$s', (int) $stats['files'], 'knuspermedia-local-fonts-icons' ) ),
					(int) $stats['files'],
					esc_html( size_format( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : '0 B' ),
					'<code>' . esc_html( Storage::base_url() ) . '</code>'
				);
				?>
			</p>
			<?php $this->render_entries( $entries ); ?>
			<?php $this->render_errors( $errors ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpdf_flush" />
				<?php wp_nonce_field( 'wpdf_flush' ); ?>
				<?php submit_button( __( 'Clear cache', 'knuspermedia-local-fonts-icons' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2 id="wpdf-scan"><?php esc_html_e( 'Check page', 'knuspermedia-local-fonts-icons' ); ?></h2>
			<p><?php esc_html_e( 'Loads a page like a visitor who isn\'t logged in and lists all remaining references to Google Fonts or Font Awesome in its HTML and stylesheets.', 'knuspermedia-local-fonts-icons' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpdf_scan" />
				<?php wp_nonce_field( 'wpdf_scan' ); ?>
				<input type="url" name="wpdf_scan_url" class="regular-text" value="<?php echo esc_attr( $scan['url'] ?? home_url( '/' ) ); ?>" />
				<?php submit_button( __( 'Check', 'knuspermedia-local-fonts-icons' ), 'primary', 'submit', false ); ?>
			</form>
			<?php $this->render_scan( $scan ); ?>

			<h2><?php esc_html_e( 'Settings', 'knuspermedia-local-fonts-icons' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Services', 'knuspermedia-local-fonts-icons' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[google]" value="1" <?php checked( $settings['google'] ); ?> /> <?php esc_html_e( 'Self-host Google Fonts', 'knuspermedia-local-fonts-icons' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fontawesome]" value="1" <?php checked( $settings['fontawesome'] ); ?> /> <?php esc_html_e( 'Self-host Font Awesome', 'knuspermedia-local-fonts-icons' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Subsets (Google Fonts)', 'knuspermedia-local-fonts-icons' ); ?></th>
						<td>
							<?php foreach ( Settings::subset_labels() as $subset => $label ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[subsets][]" value="<?php echo esc_attr( $subset ); ?>" <?php checked( in_array( $subset, $settings['subsets'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'If a font offers none of the selected subsets, all of its files are loaded.', 'knuspermedia-local-fonts-icons' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpdf-font-display">font-display</label></th>
						<td>
							<select id="wpdf-font-display" name="<?php echo esc_attr( $name ); ?>[font_display]">
								<?php foreach ( Settings::FONT_DISPLAY as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_display'], $value ); ?>><?php echo esc_html( $value === 'keep' ? __( 'Don\'t change', 'knuspermedia-local-fonts-icons' ) : $value ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( '"swap" shows text immediately in a fallback font and prevents invisible text while fonts load.', 'knuspermedia-local-fonts-icons' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Local stylesheets', 'knuspermedia-local-fonts-icons' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[local_css]" value="1" <?php checked( $settings['local_css'] ); ?> /> <?php esc_html_e( 'Search theme and plugin stylesheets for imports of Google Fonts / Font Awesome and serve cleaned copies', 'knuspermedia-local-fonts-icons' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'JavaScript guard', 'knuspermedia-local-fonts-icons' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[js_guard]" value="1" <?php checked( $settings['js_guard'] ); ?> /> <?php esc_html_e( 'Block Google Fonts and Font Awesome files that are added by JavaScript', 'knuspermedia-local-fonts-icons' ); ?></label>
							<p class="description"><?php esc_html_e( 'Applies e.g. to sliders or widgets that add fonts in the browser. These elements then show the fallback font.', 'knuspermedia-local-fonts-icons' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpdf-kit-version"><?php esc_html_e( 'Font Awesome Kits', 'knuspermedia-local-fonts-icons' ); ?></label></th>
						<td>
							<input id="wpdf-kit-version" type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[fa_kit_version]" value="<?php echo esc_attr( $settings['fa_kit_version'] ); ?>" placeholder="auto" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: Font Awesome version number */
									esc_html__( 'Font Awesome Kits (kit scripts) are replaced by a local copy of Font Awesome Free. Leave empty to use the kit\'s version (fallback: %s). Pro icons and custom kit icons are not included.', 'knuspermedia-local-fonts-icons' ),
									esc_html( Settings::FA_FALLBACK_VERSION )
								);
								?>
							</p>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fa_kit_shims]" value="1" <?php checked( $settings['fa_kit_shims'] ); ?> /> <?php esc_html_e( 'Also load compatibility for Font Awesome 4 classes (fa fa-…)', 'knuspermedia-local-fonts-icons' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save and clear cache', 'knuspermedia-local-fonts-icons' ) ); ?>
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
			echo '<p><em>' . esc_html__( 'No files localized yet. Visit a page or use "Check page".', 'knuspermedia-local-fonts-icons' ) . '</em></p>';

			return;
		}

		$labels = [
			'google'      => 'Google Fonts',
			'fontawesome' => 'Font Awesome',
			'local'       => __( 'Local stylesheet copy', 'knuspermedia-local-fonts-icons' ),
		];
		?>
		<details>
			<summary>
				<?php
				/* translators: %d: number of localized sources */
				echo esc_html( sprintf( _n( 'Show %d localized source', 'Show %d localized sources', count( $rows ), 'knuspermedia-local-fonts-icons' ), count( $rows ) ) );
				?>
			</summary>
			<table class="widefat striped" style="margin-top:8px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'knuspermedia-local-fonts-icons' ); ?></th>
						<th><?php esc_html_e( 'Source', 'knuspermedia-local-fonts-icons' ); ?></th>
						<th><?php esc_html_e( 'Local file', 'knuspermedia-local-fonts-icons' ); ?></th>
						<th><?php esc_html_e( 'Date', 'knuspermedia-local-fonts-icons' ); ?></th>
					</tr>
				</thead>
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
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ); ?></td>
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
			<p>
				<strong><?php esc_html_e( 'Notices and errors:', 'knuspermedia-local-fonts-icons' ); ?></strong>
				<?php
				/* translators: %d: minutes */
				echo esc_html( sprintf( __( 'Files that can\'t be downloaded are blocked (the page uses the fallback font) and retried after %d minutes.', 'knuspermedia-local-fonts-icons' ), (int) ( Localizer::RETRY_AFTER / MINUTE_IN_SECONDS ) ) );
				?>
			</p>
			<ul>
				<?php foreach ( array_reverse( $errors, true ) as $url => $error ) : ?>
					<li><code style="word-break:break-all"><?php echo esc_html( $url ); ?></code>: <?php echo esc_html( $error['message'] ); ?> (<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $error['time'] ) ); ?>)</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private function render_scan( $scan ) {
		if ( ! is_array( $scan ) ) {
			return;
		}

		printf(
			'<p>%s</p>',
			sprintf(
				/* translators: 1: checked URL, 2: date and time */
				esc_html__( 'Result for %1$s from %2$s:', 'knuspermedia-local-fonts-icons' ),
				sprintf( '<a href="%1$s" target="_blank" rel="noopener">%1$s</a>', esc_url( $scan['url'] ) ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $scan['time'] ) )
			)
		);

		if ( ! empty( $scan['error'] ) ) {
			/* translators: %s: error message */
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( sprintf( __( 'The page could not be checked: %s', 'knuspermedia-local-fonts-icons' ), $scan['error'] ) ) );

			return;
		}

		if ( ! $scan['findings'] ) {
			echo '<div class="notice notice-success inline"><p>✅ ' . esc_html__( 'No references to Google Fonts or Font Awesome found.', 'knuspermedia-local-fonts-icons' ) . '</p></div>';

			return;
		}
		?>
		<div class="notice notice-error inline">
			<p>
				<strong>
					<?php
					/* translators: %d: number of references */
					echo esc_html( sprintf( _n( '%d remaining reference found:', '%d remaining references found:', count( $scan['findings'] ), 'knuspermedia-local-fonts-icons' ), count( $scan['findings'] ) ) );
					?>
				</strong>
			</p>
			<ul>
				<?php foreach ( $scan['findings'] as $finding ) : ?>
					<li><code style="word-break:break-all"><?php echo esc_html( $finding['url'] ); ?></code> – <?php echo esc_html( $finding['where'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'References of a service that is disabled in the settings are kept on purpose. If a page cache is active, clear it and check again.', 'knuspermedia-local-fonts-icons' ); ?></p>
		</div>
		<?php
	}
}
