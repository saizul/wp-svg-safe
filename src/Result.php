<?php
/**
 * Sanitizer result.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * Outcome of a sanitizer run: the clean markup and what was removed, or an error code.
 */
final class Result {

	/**
	 * Clean SVG markup, empty on failure.
	 *
	 * @var string
	 */
	private $svg;

	/**
	 * Removed items such as `element:script` or `attribute:onload`.
	 *
	 * @var string[]
	 */
	private $removed;

	/**
	 * Error code, empty on success: too_large, empty, entities, parse, root, gzip.
	 *
	 * @var string
	 */
	private $error;

	/**
	 * Constructor.
	 *
	 * @param string   $svg     Clean markup.
	 * @param string[] $removed Removed items.
	 * @param string   $error   Error code.
	 */
	private function __construct( string $svg, array $removed, string $error ) {
		$this->svg     = $svg;
		$this->removed = $removed;
		$this->error   = $error;
	}

	/**
	 * Successful result.
	 *
	 * @param string   $svg     Clean markup.
	 * @param string[] $removed Removed items.
	 */
	public static function success( string $svg, array $removed = array() ): self {
		return new self( $svg, $removed, '' );
	}

	/**
	 * Failed result.
	 *
	 * @param string $error Error code.
	 */
	public static function failure( string $error ): self {
		return new self( '', array(), $error );
	}

	/**
	 * Copy with other markup (for example gzipped again).
	 *
	 * @param string $svg Markup.
	 */
	public function with_svg( string $svg ): self {
		return new self( $svg, $this->removed, $this->error );
	}

	/**
	 * Whether the document could be sanitized.
	 */
	public function ok(): bool {
		return '' === $this->error;
	}

	/**
	 * Clean markup.
	 */
	public function svg(): string {
		return $this->svg;
	}

	/**
	 * Removed items.
	 *
	 * @return string[]
	 */
	public function removed(): array {
		return $this->removed;
	}

	/**
	 * Error code.
	 */
	public function error(): string {
		return $this->error;
	}
}
