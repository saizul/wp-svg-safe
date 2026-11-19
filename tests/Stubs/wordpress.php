<?php
/**
 * Minimal WordPress stubs for unit tests.
 *
 * Only what the plugin touches is implemented. State lives in WpStubs so tests
 * can reset and inspect it.
 *
 * @package Saizul\SvgSafe
 */

declare(strict_types=1);

// phpcs:ignoreFile

define( 'ABSPATH', __DIR__ . '/' );

final class WpDie extends RuntimeException {
}

final class WpStubs {
	/** @var array<string,array<int,array<int,callable>>> */
	public static $filters = array();
	/** @var array<string,mixed> */
	public static $options = array();
	/** @var bool */
	public static $admin = false;
	/** @var bool */
	public static $logged_in = true;
	/** @var string[] */
	public static $roles = array( 'administrator' );
	/** @var bool|array<string,bool> */
	public static $can = true;
	/** @var bool */
	public static $super_admin = false;
	/** @var array<int,array<string,mixed>> Attachments: mime, file, url, meta. */
	public static $attachments = array();
	/** @var array<string,string[]> */
	public static $calls = array();
	/** @var array<string,mixed> */
	public static $registry = array();

	public static function reset(): void {
		self::$filters     = array();
		self::$options     = array();
		self::$admin       = false;
		self::$logged_in   = true;
		self::$roles       = array( 'administrator' );
		self::$can         = true;
		self::$super_admin = false;
		self::$attachments = array();
		self::$calls       = array();
		self::$registry    = array();
	}

	public static function record( string $what, string $value ): void {
		self::$calls[ $what ][] = $value;
	}

	/** @return string[] */
	public static function calls( string $what ): array {
		return self::$calls[ $what ] ?? array();
	}
}

final class WP_Roles_Stub {
	/** @return array<string,string> */
	public function get_names() {
		return array(
			'administrator' => 'Administrator',
			'editor'        => 'Editor',
			'author'        => 'Author',
			'contributor'   => 'Contributor',
			'subscriber'    => 'Subscriber',
		);
	}
}

// Hooks.
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	WpStubs::$filters[ $hook ][ $priority ][] = $callback;
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}

function has_filter( $hook, $callback = false ) {
	foreach ( WpStubs::$filters[ $hook ] ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $registered ) {
			if ( false === $callback || $registered == $callback ) {
				return $priority;
			}
		}
	}
	return false;
}

function apply_filters( $hook, $value, ...$args ) {
	$callbacks = WpStubs::$filters[ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $list ) {
		foreach ( $list as $callback ) {
			$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
		}
	}
	return $value;
}

function do_action( $hook, ...$args ) {
	WpStubs::record( 'do_action', (string) $hook );
	$callbacks = WpStubs::$filters[ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $list ) {
		foreach ( $list as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}

function __return_false() {
	return false;
}

// Options.
function get_option( $name, $default = false ) {
	return array_key_exists( $name, WpStubs::$options ) ? WpStubs::$options[ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	WpStubs::$options[ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( WpStubs::$options[ $name ] );
	return true;
}

// Users and roles.
function wp_roles() {
	return new WP_Roles_Stub();
}

function translate_user_role( $name ) {
	return $name;
}

function is_user_logged_in() {
	return WpStubs::$logged_in;
}

function wp_get_current_user() {
	$user        = new stdClass();
	$user->ID    = WpStubs::$logged_in ? 1 : 0;
	$user->roles = WpStubs::$logged_in ? WpStubs::$roles : array();
	return $user;
}

function current_user_can( $capability, ...$args ) {
	if ( is_array( WpStubs::$can ) ) {
		return WpStubs::$can[ $capability ] ?? false;
	}
	return WpStubs::$can;
}

function is_super_admin( $user_id = false ) {
	return WpStubs::$super_admin;
}

function is_admin() {
	return WpStubs::$admin;
}

function is_multisite() {
	return ! empty( WpStubs::$registry['multisite'] );
}

// Sanitizing and escaping.
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return esc_html( $text );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function wp_die( $message = '', $title = '', $args = array() ) {
	throw new WpDie( (string) $message );
}

// i18n.
function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return esc_html( $text );
}

function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
	WpStubs::record( 'load_plugin_textdomain', (string) $domain );
	return true;
}

// URLs.
function admin_url( $path = '' ) {
	return 'https://example.org/wp-admin/' . ltrim( (string) $path, '/' );
}

function plugin_basename( $file ) {
	return basename( dirname( (string) $file ) ) . '/' . basename( (string) $file );
}

function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
}

// Attachments.
function get_post_mime_type( $post = null ) {
	return WpStubs::$attachments[ (int) $post ]['mime'] ?? false;
}

function get_attached_file( $attachment_id, $unfiltered = false ) {
	return WpStubs::$attachments[ (int) $attachment_id ]['file'] ?? false;
}

function wp_get_attachment_url( $attachment_id = 0 ) {
	return WpStubs::$attachments[ (int) $attachment_id ]['url'] ?? false;
}

function wp_get_attachment_metadata( $attachment_id = 0, $unfiltered = false ) {
	return WpStubs::$attachments[ (int) $attachment_id ]['meta'] ?? false;
}

function wp_update_attachment_metadata( $attachment_id, $data ) {
	WpStubs::$attachments[ (int) $attachment_id ]['meta'] = $data;
	WpStubs::record( 'update_metadata', (string) $attachment_id );
	return true;
}

function _wp_relative_upload_path( $path ) {
	return '2026/11/' . basename( (string) $path );
}

function wp_get_registered_image_subsizes() {
	return array(
		'thumbnail'    => array( 'width' => 150, 'height' => 150, 'crop' => true ),
		'medium'       => array( 'width' => 300, 'height' => 300, 'crop' => false ),
		'medium_large' => array( 'width' => 768, 'height' => 0, 'crop' => false ),
		'large'        => array( 'width' => 1024, 'height' => 1024, 'crop' => false ),
	);
}

function get_posts( $args = array() ) {
	WpStubs::$registry['get_posts'] = $args;
	$ids = array();
	foreach ( WpStubs::$attachments as $id => $attachment ) {
		if ( ( $args['post_mime_type'] ?? '' ) === $attachment['mime'] ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

// Styles.
function wp_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
	WpStubs::record( 'register_style', (string) $handle );
	return true;
}

function wp_enqueue_style( $handle ) {
	WpStubs::record( 'enqueue_style', (string) $handle );
}

function wp_add_inline_style( $handle, $css ) {
	WpStubs::$registry['inline_style'][ $handle ] = $css;
	return true;
}

// Settings API and admin screens.
function register_setting( $group, $name, $args = array() ) {
	WpStubs::$registry['settings'][ $name ] = $args;
}

function add_settings_section( $id, $title, $callback, $page ) {
	WpStubs::record( 'section', (string) $id );
}

function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
	WpStubs::record( 'field', (string) $id );
}

function add_options_page( $page_title, $menu_title, $capability, $slug, $callback = '' ) {
	WpStubs::record( 'options_page', (string) $slug . ':' . $capability );
	return 'settings_page_' . $slug;
}

function settings_fields( $group ) {
	echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">';
}

function do_settings_sections( $page ) {
}

function submit_button( $text = null ) {
	echo '<button type="submit">Save Changes</button>';
}

function get_admin_page_title() {
	return 'SVG Safe';
}

// Multisite.
function get_sites( $args = array() ) {
	return WpStubs::$registry['sites'] ?? array();
}

function switch_to_blog( $site_id ) {
	WpStubs::record( 'switch_to_blog', (string) $site_id );
	WpStubs::$options                  = WpStubs::$registry[ 'site_options:' . $site_id ] ?? array();
	WpStubs::$registry['current_site'] = (int) $site_id;
	return true;
}

function restore_current_blog() {
	$site                                         = WpStubs::$registry['current_site'] ?? 0;
	WpStubs::$registry[ 'site_options:' . $site ] = WpStubs::$options;
	return true;
}
