<?php
/**
 * Plugin settings.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * The single option row and its sanitizer.
 */
final class Settings {

	const OPTION = 'wp_svg_safe_settings';

	/**
	 * Smallest and largest upload size setting, in KB.
	 */
	const MIN_KB = 16;
	const MAX_KB = 20480;

	/**
	 * Defaults.
	 *
	 * @return array{roles:string[],allow_svgz:bool,max_kb:int}
	 */
	public static function defaults(): array {
		return array(
			'roles'      => array( 'administrator', 'editor' ),
			'allow_svgz' => true,
			'max_kb'     => 2048,
		);
	}

	/**
	 * Stored settings merged with the defaults.
	 *
	 * @return array{roles:string[],allow_svgz:bool,max_kb:int}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );

		return self::normalize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Sanitize callback for the Settings API.
	 *
	 * @param mixed $input Raw input.
	 * @return array{roles:string[],allow_svgz:bool,max_kb:int}
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$known = array_keys( wp_roles()->get_names() );
		$roles = array();
		foreach ( (array) ( $input['roles'] ?? array() ) as $role ) {
			$role = sanitize_key( (string) $role );
			if ( in_array( $role, $known, true ) ) {
				$roles[] = $role;
			}
		}

		return self::normalize(
			array(
				'roles'      => array_values( array_unique( $roles ) ),
				'allow_svgz' => ! empty( $input['allow_svgz'] ),
				'max_kb'     => isset( $input['max_kb'] ) ? absint( $input['max_kb'] ) : self::defaults()['max_kb'],
			)
		);
	}

	/**
	 * Fill in and clamp values.
	 *
	 * @param array<string,mixed> $values Values.
	 * @return array{roles:string[],allow_svgz:bool,max_kb:int}
	 */
	private static function normalize( array $values ): array {
		$defaults = self::defaults();
		$roles    = isset( $values['roles'] ) && is_array( $values['roles'] ) ? array_values( array_filter( array_map( 'strval', $values['roles'] ) ) ) : $defaults['roles'];

		return array(
			'roles'      => $roles,
			'allow_svgz' => isset( $values['allow_svgz'] ) ? (bool) $values['allow_svgz'] : $defaults['allow_svgz'],
			'max_kb'     => isset( $values['max_kb'] ) ? min( self::MAX_KB, max( self::MIN_KB, (int) $values['max_kb'] ) ) : $defaults['max_kb'],
		);
	}
}
