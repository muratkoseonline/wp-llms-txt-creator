<?php
/**
 * Settings storage + admin settings screen.
 *
 * @package LLMs_Txt_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin options and the Settings → LLMs.txt page.
 */
class LLMs_Txt_Settings {

	/**
	 * Cached options array.
	 *
	 * @var array|null
	 */
	private $options = null;

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( LLMS_TXT_FILE ),
			array( $this, 'action_links' )
		);
	}

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'site_title'     => '',
			'site_summary'   => '',
			'intro'          => '',
			'post_types'     => array( 'page', 'post' ),
			'max_per_type'   => 100,
			'enable_full'    => 1,
			'cache_ttl'      => 12 * HOUR_IN_SECONDS,
			'optional_links' => '',
			'log_bots'       => 0,
		);
	}

	/**
	 * Read a single setting with default fallback.
	 *
	 * @param string $key      Option key.
	 * @param mixed  $fallback Optional fallback if missing.
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		if ( null === $this->options ) {
			$stored        = get_option( LLMS_TXT_OPTION, array() );
			$this->options = wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults() );
		}
		if ( array_key_exists( $key, $this->options ) ) {
			return $this->options[ $key ];
		}
		return $fallback;
	}

	/**
	 * Add the settings page under Settings.
	 */
	public function add_menu() {
		add_options_page(
			__( 'LLMs.txt', 'llms-txt-generator' ),
			__( 'LLMs.txt', 'llms-txt-generator' ),
			'manage_options',
			'llms-txt-generator',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting + sanitizer.
	 */
	public function register_settings() {
		register_setting(
			'llms_txt_group',
			LLMS_TXT_OPTION,
			array( $this, 'sanitize' )
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = $this->defaults();

		$out['site_title']     = isset( $input['site_title'] ) ? sanitize_text_field( $input['site_title'] ) : '';
		$out['site_summary']   = isset( $input['site_summary'] ) ? sanitize_text_field( $input['site_summary'] ) : '';
		$out['intro']          = isset( $input['intro'] ) ? sanitize_textarea_field( $input['intro'] ) : '';
		$out['optional_links'] = isset( $input['optional_links'] ) ? sanitize_textarea_field( $input['optional_links'] ) : '';
		$out['max_per_type']   = isset( $input['max_per_type'] ) ? max( 0, (int) $input['max_per_type'] ) : 100;
		$out['cache_ttl']      = isset( $input['cache_ttl'] ) ? max( 0, (int) $input['cache_ttl'] ) : 12 * HOUR_IN_SECONDS;
		$out['enable_full']    = empty( $input['enable_full'] ) ? 0 : 1;
		$out['log_bots']       = empty( $input['log_bots'] ) ? 0 : 1;

		$valid_types        = $this->available_post_types();
		$out['post_types']  = array();
		$submitted_types    = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? $input['post_types'] : array();
		foreach ( $submitted_types as $type ) {
			$type = sanitize_key( $type );
			if ( isset( $valid_types[ $type ] ) ) {
				$out['post_types'][] = $type;
			}
		}
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = array( 'page', 'post' );
		}

		return $out;
	}

	/**
	 * Public post types that can be listed.
	 *
	 * @return array<string,string> slug => label
	 */
	public function available_post_types() {
		$types  = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $types as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$result[ $type->name ] = $type->labels->name;
		}
		return $result;
	}

	/**
	 * Add a quick "Settings" link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url   = admin_url( 'options-general.php?page=llms-txt-generator' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'llms-txt-generator' ) . '</a>';
		return $links;
	}

	/**
	 * Render the settings screen.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$selected   = (array) $this->get( 'post_types' );
		$all_types  = $this->available_post_types();
		$basic_url  = home_url( '/llms.txt' );
		$full_url   = home_url( '/llms-full.txt' );
		$ttl_hours  = round( (int) $this->get( 'cache_ttl' ) / HOUR_IN_SECONDS, 2 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LLMs.txt Generator', 'llms-txt-generator' ); ?></h1>
			<p>
				<?php esc_html_e( 'Your files are served live at:', 'llms-txt-generator' ); ?>
				<a href="<?php echo esc_url( $basic_url ); ?>" target="_blank"><code><?php echo esc_html( $basic_url ); ?></code></a>
				&nbsp;·&nbsp;
				<a href="<?php echo esc_url( $full_url ); ?>" target="_blank"><code><?php echo esc_html( $full_url ); ?></code></a>
			</p>

			<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					printf(
						/* translators: %s: Permalinks settings URL */
						wp_kses_post( __( 'Plain permalinks are enabled. The endpoints need pretty permalinks — please pick any other option under <a href="%s">Settings → Permalinks</a> and save.', 'llms-txt-generator' ) ),
						esc_url( admin_url( 'options-permalink.php' ) )
					);
					?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'llms_txt_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="llms_site_title"><?php esc_html_e( 'Site title', 'llms-txt-generator' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[site_title]" id="llms_site_title" type="text" class="regular-text" value="<?php echo esc_attr( $this->get( 'site_title' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Used as the H1. Leave blank to use your WordPress site title.', 'llms-txt-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms_site_summary"><?php esc_html_e( 'Summary', 'llms-txt-generator' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[site_summary]" id="llms_site_summary" type="text" class="large-text" value="<?php echo esc_attr( $this->get( 'site_summary' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'One-line blockquote summary describing what the site is about.', 'llms-txt-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms_intro"><?php esc_html_e( 'Intro (optional)', 'llms-txt-generator' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[intro]" id="llms_intro" rows="3" class="large-text"><?php echo esc_textarea( $this->get( 'intro' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Free-form paragraph shown under the summary. Good place for key context or guidance for AI agents.', 'llms-txt-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include content types', 'llms-txt-generator' ); ?></th>
						<td>
							<?php foreach ( $all_types as $slug => $label ) : ?>
								<label style="display:inline-block;margin:0 16px 6px 0;">
									<input type="checkbox" name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?> />
									<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Each selected type becomes its own section in the file.', 'llms-txt-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms_max"><?php esc_html_e( 'Max items per type', 'llms-txt-generator' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[max_per_type]" id="llms_max" type="number" min="0" class="small-text" value="<?php echo esc_attr( $this->get( 'max_per_type' ) ); ?>" />
							<p class="description"><?php esc_html_e( '0 = no limit. Most recently modified items are listed first.', 'llms-txt-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms_ttl"><?php esc_html_e( 'Cache lifetime (hours)', 'llms-txt-generator' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[cache_ttl]" id="llms_ttl" type="number" min="0" step="3600" class="small-text" value="<?php echo esc_attr( (int) $this->get( 'cache_ttl' ) ); ?>" />
							<p class="description">
								<?php
								/* translators: %s: current TTL in hours */
								printf( esc_html__( 'Stored in seconds. Currently ~%s hour(s). Cache also clears automatically when you save content. 0 = no caching.', 'llms-txt-generator' ), esc_html( (string) $ttl_hours ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'llms-full.txt', 'llms-txt-generator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[enable_full]" value="1" <?php checked( (int) $this->get( 'enable_full' ), 1 ); ?> />
								<?php esc_html_e( 'Also serve /llms-full.txt with full post content inline', 'llms-txt-generator' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'AI agents fetch llms-full.txt notably more often than llms.txt — recommended.', 'llms-txt-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms_optional"><?php esc_html_e( 'Optional links', 'llms-txt-generator' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[optional_links]" id="llms_optional" rows="4" class="large-text" placeholder="Documentation | https://example.com/docs | API reference and guides"><?php echo esc_textarea( $this->get( 'optional_links' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line as "Title | URL | description". Rendered under an "## Optional" heading.', 'llms-txt-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'AI bot logging', 'llms-txt-generator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LLMS_TXT_OPTION ); ?>[log_bots]" value="1" <?php checked( (int) $this->get( 'log_bots' ), 1 ); ?> />
								<?php esc_html_e( 'Log requests to the llms files (user agent, time) for the last 100 hits', 'llms-txt-generator' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php $this->render_log(); ?>
		</div>
		<?php
	}

	/**
	 * Show the recent bot-hit log, if any.
	 */
	private function render_log() {
		$log = get_option( LLMS_TXT_Bot_Logger::LOG_OPTION, array() );
		if ( empty( $log ) || ! is_array( $log ) ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Recent requests', 'llms-txt-generator' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px;"><thead><tr>';
		echo '<th>' . esc_html__( 'Time (UTC)', 'llms-txt-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'File', 'llms-txt-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'User agent', 'llms-txt-generator' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( array_reverse( $log ) as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( isset( $row['time'] ) ? $row['time'] : '' ) . '</td>';
			echo '<td><code>' . esc_html( isset( $row['file'] ) ? $row['file'] : '' ) . '</code></td>';
			echo '<td>' . esc_html( isset( $row['ua'] ) ? $row['ua'] : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
