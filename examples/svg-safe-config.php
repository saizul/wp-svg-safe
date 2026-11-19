<?php
/**
 * Plugin Name: SVG Safe configuration (example)
 * Description: Example must-use plugin showing the SVG Safe developer hooks. Copy to wp-content/mu-plugins/ and edit.
 *
 * @package Saizul\SvgSafe
 */

defined( 'ABSPATH' ) || exit;

/*
 * Let Authors upload SVG files too, but only on the main site of a network.
 * The role setting is checked first; the filter has the last word.
 */
add_filter(
	'svg_safe_user_can_upload',
	static function ( $allowed ) {
		$user = wp_get_current_user();
		if ( in_array( 'author', (array) $user->roles, true ) && is_main_site() ) {
			return true;
		}
		return $allowed;
	}
);

/*
 * Keep the `data-name` attribute that Adobe Illustrator writes on layers.
 * Event handlers (on*) can never be allowed this way.
 */
add_filter(
	'svg_safe_allowed_attributes',
	static function ( array $attributes ) {
		$attributes[] = 'data-name';
		return $attributes;
	}
);

/*
 * Drop `<text>` elements: some brand guidelines require outlined text only.
 */
add_filter(
	'svg_safe_allowed_elements',
	static function ( array $elements ) {
		return array_values( array_diff( $elements, array( 'text', 'tspan', 'textPath' ) ) );
	}
);

/*
 * Allow larger files (10 MB) than the settings screen default.
 */
add_filter(
	'svg_safe_max_bytes',
	static function () {
		return 10 * 1024 * 1024;
	}
);

/*
 * Log what was removed from uploads, for example to spot a design tool that
 * exports unsupported features.
 */
add_action(
	'svg_safe_removed',
	static function ( array $removed, $filename ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'SVG Safe cleaned %s: %s', $filename, implode( ', ', $removed ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	},
	10,
	2
);

/*
 * Sanitize SVG markup from your own code (for example a theme option that
 * stores inline SVG).
 */
function svg_safe_example_clean_inline_svg( $markup ) {
	if ( ! class_exists( '\Saizul\SvgSafe\Plugin' ) || null === \Saizul\SvgSafe\Plugin::instance() ) {
		return '';
	}
	$result = \Saizul\SvgSafe\Plugin::instance()->sanitizer()->sanitize( (string) $markup );

	return $result->ok() ? $result->svg() : '';
}
