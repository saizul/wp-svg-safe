<?php
/**
 * Plugin Name:       SVG Safe
 * Plugin URI:        https://github.com/saizul/wp-svg-safe
 * Description:       Allow SVG and SVGZ uploads safely. Role-based permission, a strict allowlist sanitizer with no external library, correct dimensions, media library previews and WP-CLI re-sanitizing.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Saizul Amin
 * Author URI:        https://saizul.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-svg-safe
 * Domain Path:       /languages
 *
 * @package Saizul\SvgSafe
 */

defined( 'ABSPATH' ) || exit;

if ( PHP_VERSION_ID < 70400 || ! class_exists( 'DOMDocument' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'SVG Safe requires PHP 7.4 or newer with the DOM extension.', 'wp-svg-safe' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'WP_SVG_SAFE_FILE', __FILE__ );
define( 'WP_SVG_SAFE_VERSION', '0.1.0' );

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// PSR-4 autoloader for installs without Composer (the release zip).
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'Saizul\\SvgSafe\\';
			if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
				return;
			}
			$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

\Saizul\SvgSafe\Plugin::boot( __FILE__ );
