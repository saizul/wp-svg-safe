<?php
/**
 * Settings > SVG Safe screen.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe\Admin;

use Saizul\SvgSafe\Plugin;
use Saizul\SvgSafe\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen built on the Settings API. The form posts to options.php,
 * which checks the nonce and the capability; values go through
 * Settings::sanitize().
 */
final class SettingsPage {

	const SLUG  = 'wp-svg-safe';
	const GROUP = 'wp_svg_safe';

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( Plugin::class, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin->file() ), array( $this, 'action_links' ) );
	}

	/**
	 * URL of the screen.
	 */
	public static function url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( 'options-general.php' ) );
	}

	/**
	 * Add Settings > SVG Safe.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'SVG Safe', 'wp-svg-safe' ),
			__( 'SVG Safe', 'wp-svg-safe' ),
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param mixed $links Links.
	 * @return mixed
	 */
	public function action_links( $links ) {
		if ( ! is_array( $links ) || ! current_user_can( Plugin::capability() ) ) {
			return $links;
		}
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'wp-svg-safe' ) . '</a>' );

		return $links;
	}

	/**
	 * Register the setting, the section and the fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section( 'svg_safe_uploads', __( 'Uploads', 'wp-svg-safe' ), array( $this, 'section_intro' ), self::SLUG );

		$fields = array(
			'roles'      => __( 'Roles that may upload SVG', 'wp-svg-safe' ),
			'allow_svgz' => __( 'SVGZ', 'wp-svg-safe' ),
			'max_kb'     => __( 'Largest file', 'wp-svg-safe' ),
		);
		foreach ( $fields as $key => $title ) {
			add_settings_field(
				'svg_safe_' . $key,
				$title,
				array( $this, 'field' ),
				self::SLUG,
				'svg_safe_uploads',
				array(
					'key'       => $key,
					'label_for' => 'max_kb' === $key ? 'svg-safe-max_kb' : '',
				)
			);
		}
	}

	/**
	 * Section intro.
	 */
	public function section_intro(): void {
		echo '<p>' . esc_html__( 'Every SVG and SVGZ file is sanitized on upload with a strict allowlist: scripts, event handlers, external references and other unsafe content are removed. These settings decide who may upload them.', 'wp-svg-safe' ) . '</p>';
	}

	/**
	 * Render one field.
	 *
	 * @param array<string,string> $args Field arguments.
	 */
	public function field( array $args ): void {
		$settings = $this->plugin->settings();
		$name     = Settings::OPTION;

		switch ( $args['key'] ) {
			case 'roles':
				echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Roles that may upload SVG', 'wp-svg-safe' ) . '</legend>';
				foreach ( wp_roles()->get_names() as $role => $label ) {
					printf(
						'<label><input type="checkbox" name="%1$s[roles][]" value="%2$s"%3$s> %4$s</label><br>',
						esc_attr( $name ),
						esc_attr( (string) $role ),
						in_array( (string) $role, $settings['roles'], true ) ? ' checked="checked"' : '',
						esc_html( translate_user_role( (string) $label ) )
					);
				}
				echo '<p class="description">' . esc_html__( 'Users also need the upload_files capability (Authors and above by default).', 'wp-svg-safe' ) . '</p></fieldset>';
				break;

			case 'allow_svgz':
				printf(
					'<label><input type="checkbox" name="%1$s[allow_svgz]" value="1"%2$s> %3$s</label>',
					esc_attr( $name ),
					$settings['allow_svgz'] ? ' checked="checked"' : '',
					esc_html__( 'Allow gzip-compressed SVGZ files (decompressed, sanitized and compressed again)', 'wp-svg-safe' )
				);
				break;

			case 'max_kb':
				printf(
					'<input type="number" class="small-text" id="svg-safe-max_kb" name="%1$s[max_kb]" value="%2$d" min="%3$d" max="%4$d" step="1"> %5$s',
					esc_attr( $name ),
					(int) $settings['max_kb'],
					(int) Settings::MIN_KB,
					(int) Settings::MAX_KB,
					esc_html__( 'KB (for SVGZ, the decompressed size)', 'wp-svg-safe' )
				);
				break;
		}
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'wp-svg-safe' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form>';
		echo '<h2>' . esc_html__( 'Existing files', 'wp-svg-safe' ) . '</h2>';
		echo '<p>' . esc_html__( 'SVG files uploaded before this plugin was active, or added over FTP, are not sanitized yet. Run this WP-CLI command to sanitize them and fix their dimensions:', 'wp-svg-safe' ) . '</p>';
		echo '<p><code>wp svg-safe sanitize --dry-run</code> <code>wp svg-safe sanitize</code></p>';
		echo '</div>';
	}
}
