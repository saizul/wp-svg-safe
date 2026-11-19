<?php
/**
 * Gzip helpers for SVGZ files.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * Detects, inflates (with a size cap against gzip bombs) and deflates SVGZ data.
 */
final class Svgz {

	/**
	 * Whether the data starts with the gzip magic bytes.
	 *
	 * @param string $data Raw bytes.
	 */
	public static function is_gzip( string $data ): bool {
		return 0 === strncmp( $data, "\x1f\x8b", 2 );
	}

	/**
	 * Inflate gzip data.
	 *
	 * @param string $data      Gzip bytes.
	 * @param int    $max_bytes Largest inflated size.
	 * @return string|null Null when the data is broken or inflates past the cap.
	 */
	public static function decode( string $data, int $max_bytes ): ?string {
		if ( ! self::is_gzip( $data ) || ! function_exists( 'gzdecode' ) ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- gzdecode() warns on broken data and on the size cap; both are handled by the return value.
		$out = @gzdecode( $data, max( 1, $max_bytes ) + 1 );
		if ( false === $out || strlen( $out ) > $max_bytes ) {
			return null;
		}

		return $out;
	}

	/**
	 * Deflate data.
	 *
	 * @param string $data Plain bytes.
	 */
	public static function encode( string $data ): string {
		$out = gzencode( $data, 9 );

		return false === $out ? '' : $out;
	}
}
