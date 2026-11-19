<?php
/**
 * Width and height of an SVG document.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the intrinsic size of an SVG from its root `width`, `height` and
 * `viewBox` attributes, in CSS pixels.
 */
final class Dimensions {

	/**
	 * Pixels per unit (CSS reference pixel: 96 per inch).
	 */
	const UNITS = array(
		''   => 1.0,
		'px' => 1.0,
		'pt' => 96 / 72,
		'pc' => 16.0,
		'in' => 96.0,
		'cm' => 96 / 2.54,
		'mm' => 96 / 25.4,
		'q'  => 96 / 101.6,
		'em' => 16.0,
		'ex' => 8.0,
	);

	/**
	 * Size of an SVG document.
	 *
	 * Uses width and height when both are absolute lengths. When one is
	 * missing or relative (`100%`, `auto`), it is derived from the viewBox
	 * aspect ratio. Without usable attributes the size is 0 x 0.
	 *
	 * @param string $svg Sanitized SVG markup (not gzipped).
	 * @return array{width:int,height:int}
	 */
	public static function from_svg( string $svg ): array {
		$none = array(
			'width'  => 0,
			'height' => 0,
		);
		if ( ! preg_match( '/<svg\b([^>]*)>/i', $svg, $match ) ) {
			return $none;
		}
		$attributes = self::attributes( $match[1] );

		$width  = self::length( $attributes['width'] ?? '' );
		$height = self::length( $attributes['height'] ?? '' );
		$box    = self::view_box( $attributes['viewbox'] ?? '' );

		if ( null !== $box ) {
			if ( null === $width && null === $height ) {
				$width  = $box[0];
				$height = $box[1];
			} elseif ( null === $width ) {
				$width = $height * $box[0] / $box[1];
			} elseif ( null === $height ) {
				$height = $width * $box[1] / $box[0];
			}
		}
		if ( null === $width || null === $height ) {
			return $none;
		}

		return array(
			'width'  => max( 1, (int) round( $width ) ),
			'height' => max( 1, (int) round( $height ) ),
		);
	}

	/**
	 * Attributes of the root tag, with lower-case names.
	 *
	 * @param string $tag Text between `<svg` and `>`.
	 * @return array<string,string>
	 */
	private static function attributes( string $tag ): array {
		$out = array();
		if ( preg_match_all( '/([A-Za-z_:][\w:.-]*)\s*=\s*("([^"]*)"|\'([^\']*)\')/', $tag, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$out[ strtolower( $m[1] ) ] = html_entity_decode( isset( $m[4] ) ? $m[4] : $m[3], ENT_QUOTES | ENT_XML1, 'UTF-8' );
			}
		}

		return $out;
	}

	/**
	 * Absolute length in pixels.
	 *
	 * @param string $value Length such as `24`, `24px`, `2.5cm`.
	 */
	public static function length( string $value ): ?float {
		if ( ! preg_match( '/^\s*(\d*\.?\d+(?:e[+-]?\d+)?)\s*([a-z]*)\s*$/i', $value, $m ) ) {
			return null;
		}
		$unit = strtolower( $m[2] );
		if ( ! isset( self::UNITS[ $unit ] ) ) {
			return null;
		}
		$px = (float) $m[1] * self::UNITS[ $unit ];

		return $px > 0 && $px < 1000000 ? $px : null;
	}

	/**
	 * Width and height of a viewBox.
	 *
	 * @param string $value `min-x min-y width height`.
	 * @return float[]|null
	 */
	private static function view_box( string $value ): ?array {
		$parts = preg_split( '/[\s,]+/', trim( $value ) );
		if ( ! is_array( $parts ) || 4 !== count( $parts ) ) {
			return null;
		}
		foreach ( $parts as $part ) {
			if ( ! is_numeric( $part ) ) {
				return null;
			}
		}
		$width  = (float) $parts[2];
		$height = (float) $parts[3];

		return $width > 0 && $height > 0 && $width < 1000000 && $height < 1000000 ? array( $width, $height ) : null;
	}
}
