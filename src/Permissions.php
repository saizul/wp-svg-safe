<?php
/**
 * Who may upload SVG files.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * Role-based upload permission.
 */
final class Permissions {

	/**
	 * Whether the current user may upload SVG files.
	 *
	 * The user needs the `upload_files` capability and one of the roles chosen
	 * in the settings. Super admins on multisite are always allowed.
	 *
	 * @param array{roles:string[],allow_svgz:bool,max_kb:int} $settings Settings.
	 */
	public static function current_user_can_upload( array $settings ): bool {
		$allowed = false;
		if ( is_user_logged_in() && current_user_can( 'upload_files' ) ) {
			$user    = wp_get_current_user();
			$roles   = isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : array();
			$allowed = ( is_multisite() && is_super_admin() ) || array() !== array_intersect( $roles, $settings['roles'] );
		}

		/**
		 * Filters whether the current user may upload SVG and SVGZ files.
		 *
		 * Files are always sanitized; this only decides who may upload them.
		 *
		 * @param bool                $allowed  Allowed by the role settings.
		 * @param array<string,mixed> $settings Plugin settings.
		 */
		return (bool) apply_filters( 'svg_safe_user_can_upload', $allowed, $settings );
	}
}
