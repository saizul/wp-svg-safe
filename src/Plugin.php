<?php
/**
 * Plugin bootstrap.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

use Saizul\SvgSafe\Admin\SettingsPage;
use Saizul\SvgSafe\Cli\Command;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the upload filters, the media library, the settings screen and WP-CLI.
 */
final class Plugin {

	const VERSION = '0.1.0';
	const MIME    = 'image/svg+xml';

	/**
	 * Running instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance;

	/**
	 * Main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Constructor.
	 *
	 * @param string $file Main plugin file.
	 */
	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Create the instance and register the hooks.
	 *
	 * @param string $file Main plugin file.
	 */
	public static function boot( string $file ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Running instance, if booted.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Forget the instance (tests).
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Uploads( $this ) )->register();
		( new Media( $this ) )->register();

		if ( is_admin() ) {
			( new SettingsPage( $this ) )->register();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'svg-safe', new Command( $this ) );
		}
	}

	/**
	 * Load translations for installs outside WordPress.org.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wp-svg-safe', false, dirname( plugin_basename( $this->file ) ) . '/languages' );
	}

	/**
	 * Current settings.
	 *
	 * @return array{roles:string[],allow_svgz:bool,max_kb:int}
	 */
	public function settings(): array {
		return Settings::get();
	}

	/**
	 * Largest SVG size in bytes (inflated size for SVGZ).
	 */
	public function max_bytes(): int {
		/**
		 * Filters the largest SVG size in bytes. For SVGZ files this is the
		 * inflated size, which protects against gzip bombs.
		 *
		 * @param int $bytes Bytes, from the "Largest file" setting.
		 */
		$bytes = (int) apply_filters( 'svg_safe_max_bytes', $this->settings()['max_kb'] * 1024 );

		return max( 1024, $bytes );
	}

	/**
	 * Sanitizer with the filtered allowlists.
	 */
	public function sanitizer(): Sanitizer {
		/**
		 * Filters the allowed SVG elements (case-sensitive local names).
		 * `script`, `foreignObject`, animation elements and a few others can
		 * never be allowed.
		 *
		 * @param string[] $elements Element names.
		 */
		$elements = apply_filters( 'svg_safe_allowed_elements', Sanitizer::default_elements() );

		/**
		 * Filters the allowed SVG attributes (without namespace). Event
		 * handlers (`on*`) can never be allowed; `href` has its own rule.
		 *
		 * @param string[] $attributes Attribute names.
		 */
		$attributes = apply_filters( 'svg_safe_allowed_attributes', Sanitizer::default_attributes() );

		return new Sanitizer(
			is_array( $elements ) ? $elements : Sanitizer::default_elements(),
			is_array( $attributes ) ? $attributes : Sanitizer::default_attributes(),
			$this->max_bytes()
		);
	}

	/**
	 * File processor (gzip handling + sanitizer).
	 */
	public function processor(): FileProcessor {
		return new FileProcessor( $this->sanitizer(), $this->max_bytes() );
	}

	/**
	 * Whether the current user may upload SVG files.
	 */
	public function current_user_can_upload(): bool {
		return Permissions::current_user_can_upload( $this->settings() );
	}

	/**
	 * Capability needed to change the settings.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability needed to change the settings.
		 *
		 * @param string $capability Capability, `manage_options` by default.
		 */
		$capability = apply_filters( 'svg_safe_capability', 'manage_options' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}

	/**
	 * Human-readable message for a sanitizer error code.
	 *
	 * @param string $code Error code.
	 */
	public static function error_message( string $code ): string {
		switch ( $code ) {
			case 'too_large':
				return __( 'The SVG file is larger than the limit set in Settings > SVG Safe.', 'wp-svg-safe' );
			case 'empty':
				return __( 'The SVG file is empty.', 'wp-svg-safe' );
			case 'entities':
				return __( 'The SVG file declares XML entities, which are not allowed.', 'wp-svg-safe' );
			case 'root':
				return __( 'The file is not an SVG image (the root element must be <svg>).', 'wp-svg-safe' );
			case 'gzip':
				return __( 'The SVGZ file could not be decompressed, or it is too large once decompressed.', 'wp-svg-safe' );
			default:
				return __( 'The SVG file is not well-formed XML.', 'wp-svg-safe' );
		}
	}

	/**
	 * Main plugin file.
	 */
	public function file(): string {
		return $this->file;
	}
}
