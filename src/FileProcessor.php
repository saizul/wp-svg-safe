<?php
/**
 * Sanitizes the bytes of an uploaded SVG or SVGZ file.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * Inflates gzip data if needed, sanitizes, and deflates again for `.svgz`
 * files. Pure: works on strings, so uploads, sideloads and WP-CLI share it.
 */
final class FileProcessor {

	/**
	 * Sanitizer.
	 *
	 * @var Sanitizer
	 */
	private $sanitizer;

	/**
	 * Largest inflated size of an SVGZ file.
	 *
	 * @var int
	 */
	private $max_bytes;

	/**
	 * Constructor.
	 *
	 * @param Sanitizer $sanitizer Sanitizer.
	 * @param int       $max_bytes Largest (inflated) size in bytes.
	 */
	public function __construct( Sanitizer $sanitizer, int $max_bytes ) {
		$this->sanitizer = $sanitizer;
		$this->max_bytes = $max_bytes;
	}

	/**
	 * Whether a file name has an SVG or SVGZ extension.
	 *
	 * @param string $filename File name or path.
	 */
	public static function is_svg_name( string $filename ): bool {
		return in_array( self::extension( $filename ), array( 'svg', 'svgz' ), true );
	}

	/**
	 * Lower-case extension of a file name.
	 *
	 * @param string $filename File name or path.
	 */
	public static function extension( string $filename ): string {
		return strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
	}

	/**
	 * Sanitize file contents.
	 *
	 * The result is gzipped when the file name ends in `.svgz`, and plain
	 * otherwise (a gzipped `.svg` would not display without a
	 * Content-Encoding header).
	 *
	 * @param string $bytes    File contents.
	 * @param string $filename File name, used for the extension.
	 */
	public function process( string $bytes, string $filename ): Result {
		$plain = $bytes;
		if ( Svgz::is_gzip( $bytes ) ) {
			$plain = Svgz::decode( $bytes, $this->max_bytes );
			if ( null === $plain ) {
				return Result::failure( 'gzip' );
			}
		}

		$result = $this->sanitizer->sanitize( $plain );
		if ( ! $result->ok() ) {
			return $result;
		}
		if ( 'svgz' === self::extension( $filename ) ) {
			return $result->with_svg( Svgz::encode( $result->svg() ) );
		}

		return $result;
	}

	/**
	 * Plain markup of file contents, inflating SVGZ data.
	 *
	 * @param string $bytes File contents.
	 */
	public function plain( string $bytes ): ?string {
		return Svgz::is_gzip( $bytes ) ? Svgz::decode( $bytes, $this->max_bytes ) : $bytes;
	}
}
