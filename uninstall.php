<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 *
 * Uploaded SVG files and their attachments are content and stay in place.
 *
 * @package Saizul\SvgSafe
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete the option of the current site.
 */
function wp_svg_safe_uninstall_site() {
	delete_option( 'wp_svg_safe_settings' );
}

if ( is_multisite() ) {
	$wp_svg_safe_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wp_svg_safe_site_ids as $wp_svg_safe_site_id ) {
		switch_to_blog( (int) $wp_svg_safe_site_id );
		wp_svg_safe_uninstall_site();
		restore_current_blog();
	}
} else {
	wp_svg_safe_uninstall_site();
}
