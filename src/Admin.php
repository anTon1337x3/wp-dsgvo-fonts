<?php

namespace WPDsgvoFonts;

defined( 'ABSPATH' ) || exit;

class Admin {
	const PAGE = 'dsgvo-fonts';

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
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $this->page_url() ), esc_html__( 'Settings', 'dsgvo-fonts' ) ) );

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
					'<div class="notice notice-warning"><p><strong>DSGVO Fonts:</strong> %s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: name of another font plugin, e.g. OMGF */
							__( '%s is active as well. Please deactivate it, so the two plugins don\'t interfere with each other.', 'dsgvo-fonts' ),
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
			wp_die( esc_html__( 'You are not allowed to do this.', 'dsgvo-fonts' ) );
		}

		Cache::flush_all();

		wp_safe_redirect( $this->page_url( [ 'wpdf_notice' => 'flushed' ] ) );
		exit;
	}

	public function handle_scan() {
		check_admin_referer( 'wpdf_scan' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'dsgvo-fonts' ) );
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
			<h1>DSGVO Fonts</h1>
			<p><?php esc_html_e( 'Google Fonts and Font Awesome are downloaded by your server on the first page view and served locally afterwards. Visitors\' browsers don\'t connect to Google or Font Awesome.', 'dsgvo-fonts' ); ?></p>

			<?php if ( $notice === 'flushed' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The cache was cleared. Fonts are downloaded again on the next page view.', 'dsgvo-fonts' ); ?></p></div>
			<?php elseif ( $notice === 'scanned' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The page check is complete.', 'dsgvo-fonts' ); ?></p></div>
			<?php elseif ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Set by options.php. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved. The font cache was cleared.', 'dsgvo-fonts' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Status', 'dsgvo-fonts' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: number of files, 2: total size, 3: folder */
					esc_html( _n( '%1$d local file (%2$s) in %3$s', '%1$d local files (%2$s) in %3$s', (int) $stats['files'], 'dsgvo-fonts' ) ),
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
				<?php submit_button( __( 'Clear cache', 'dsgvo-fonts' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2 id="wpdf-scan"><?php esc_html_e( 'Check page', 'dsgvo-fonts' ); ?></h2>
			<p><?php esc_html_e( 'Loads a page like a visitor who isn\'t logged in and lists all remaining references to Google Fonts or Font Awesome in its HTML and stylesheets.', 'dsgvo-fonts' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpdf_scan" />
				<?php wp_nonce_field( 'wpdf_scan' ); ?>
				<input type="url" name="wpdf_scan_url" class="regular-text" value="<?php echo esc_attr( $scan['url'] ?? home_url( '/' ) ); ?>" />
				<?php submit_button( __( 'Check', 'dsgvo-fonts' ), 'primary', 'submit', false ); ?>
			</form>
			<?php $this->render_scan( $scan ); ?>

			<h2><?php esc_html_e( 'Settings', 'dsgvo-fonts' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Services', 'dsgvo-fonts' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[google]" value="1" <?php checked( $settings['google'] ); ?> /> <?php esc_html_e( 'Self-host Google Fonts', 'dsgvo-fonts' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fontawesome]" value="1" <?php checked( $settings['fontawesome'] ); ?> /> <?php esc_html_e( 'Self-host Font Awesome', 'dsgvo-fonts' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Subsets (Google Fonts)', 'dsgvo-fonts' ); ?></th>
						<td>
							<?php foreach ( Settings::subset_labels() as $subset => $label ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[subsets][]" value="<?php echo esc_attr( $subset ); ?>" <?php checked( in_array( $subset, $settings['subsets'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'If a font offers none of the selected subsets, all of its files are loaded.', 'dsgvo-fonts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpdf-font-display">font-display</label></th>
						<td>
							<select id="wpdf-font-display" name="<?php echo esc_attr( $name ); ?>[font_display]">
								<?php foreach ( Settings::FONT_DISPLAY as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_display'], $value ); ?>><?php echo esc_html( $value === 'keep' ? __( 'Don\'t change', 'dsgvo-fonts' ) : $value ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( '"swap" shows text immediately in a fallback font and prevents invisible text while fonts load.', 'dsgvo-fonts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Local stylesheets', 'dsgvo-fonts' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[local_css]" value="1" <?php checked( $settings['local_css'] ); ?> /> <?php esc_html_e( 'Search theme and plugin stylesheets for imports of Google Fonts / Font Awesome and serve cleaned copies', 'dsgvo-fonts' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'JavaScript guard', 'dsgvo-fonts' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[js_guard]" value="1" <?php checked( $settings['js_guard'] ); ?> /> <?php esc_html_e( 'Block Google Fonts and Font Awesome files that are added by JavaScript', 'dsgvo-fonts' ); ?></label>
							<p class="description"><?php esc_html_e( 'Applies e.g. to sliders or widgets that add fonts in the browser. These elements then show the fallback font.', 'dsgvo-fonts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpdf-kit-version"><?php esc_html_e( 'Font Awesome Kits', 'dsgvo-fonts' ); ?></label></th>
						<td>
							<input id="wpdf-kit-version" type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[fa_kit_version]" value="<?php echo esc_attr( $settings['fa_kit_version'] ); ?>" placeholder="auto" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: Font Awesome version number */
									esc_html__( 'Font Awesome Kits (kit scripts) are replaced by a local copy of Font Awesome Free. Leave empty to use the kit\'s version (fallback: %s). Pro icons and custom kit icons are not included.', 'dsgvo-fonts' ),
									esc_html( Settings::FA_FALLBACK_VERSION )
								);
								?>
							</p>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fa_kit_shims]" value="1" <?php checked( $settings['fa_kit_shims'] ); ?> /> <?php esc_html_e( 'Also load compatibility for Font Awesome 4 classes (fa fa-…)', 'dsgvo-fonts' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save and clear cache', 'dsgvo-fonts' ) ); ?>
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
			echo '<p><em>' . esc_html__( 'No files localized yet. Visit a page or use "Check page".', 'dsgvo-fonts' ) . '</em></p>';

			return;
		}

		$labels = [
			'google'      => 'Google Fonts',
			'fontawesome' => 'Font Awesome',
			'local'       => __( 'Local stylesheet copy', 'dsgvo-fonts' ),
		];
		?>
		<details>
			<summary>
				<?php
				/* translators: %d: number of localized sources */
				echo esc_html( sprintf( _n( 'Show %d localized source', 'Show %d localized sources', count( $rows ), 'dsgvo-fonts' ), count( $rows ) ) );
				?>
			</summary>
			<table class="widefat striped" style="margin-top:8px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'dsgvo-fonts' ); ?></th>
						<th><?php esc_html_e( 'Source', 'dsgvo-fonts' ); ?></th>
						<th><?php esc_html_e( 'Local file', 'dsgvo-fonts' ); ?></th>
						<th><?php esc_html_e( 'Date', 'dsgvo-fonts' ); ?></th>
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
				<strong><?php esc_html_e( 'Notices and errors:', 'dsgvo-fonts' ); ?></strong>
				<?php
				/* translators: %d: minutes */
				echo esc_html( sprintf( __( 'Files that can\'t be downloaded are blocked (the page uses the fallback font) and retried after %d minutes.', 'dsgvo-fonts' ), (int) ( Localizer::RETRY_AFTER / MINUTE_IN_SECONDS ) ) );
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
				esc_html__( 'Result for %1$s from %2$s:', 'dsgvo-fonts' ),
				sprintf( '<a href="%1$s" target="_blank" rel="noopener">%1$s</a>', esc_url( $scan['url'] ) ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $scan['time'] ) )
			)
		);

		if ( ! empty( $scan['error'] ) ) {
			/* translators: %s: error message */
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( sprintf( __( 'The page could not be checked: %s', 'dsgvo-fonts' ), $scan['error'] ) ) );

			return;
		}

		if ( ! $scan['findings'] ) {
			echo '<div class="notice notice-success inline"><p>✅ ' . esc_html__( 'No references to Google Fonts or Font Awesome found.', 'dsgvo-fonts' ) . '</p></div>';

			return;
		}
		?>
		<div class="notice notice-error inline">
			<p>
				<strong>
					<?php
					/* translators: %d: number of references */
					echo esc_html( sprintf( _n( '%d remaining reference found:', '%d remaining references found:', count( $scan['findings'] ), 'dsgvo-fonts' ), count( $scan['findings'] ) ) );
					?>
				</strong>
			</p>
			<ul>
				<?php foreach ( $scan['findings'] as $finding ) : ?>
					<li><code style="word-break:break-all"><?php echo esc_html( $finding['url'] ); ?></code> – <?php echo esc_html( $finding['where'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'References of a service that is disabled in the settings are kept on purpose. If a page cache is active, clear it and check again.', 'dsgvo-fonts' ); ?></p>
		</div>
		<?php
	}
}
