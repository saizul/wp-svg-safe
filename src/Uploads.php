<?php
/**
 * Upload filters.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the SVG MIME types for allowed users and sanitizes every SVG or SVGZ
 * file before WordPress moves it into the uploads folder.
 */
final class Uploads {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'mimes' ), 10, 1 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_filetype' ), 10, 3 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'prefilter' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'prefilter' ) );
	}

	/**
	 * Add `svg` (and `svgz`) to the allowed MIME types for allowed users.
	 *
	 * @param mixed $mimes Extension regex => MIME type.
	 * @return mixed
	 */
	public function mimes( $mimes ) {
		if ( ! is_array( $mimes ) || ! $this->plugin->current_user_can_upload() ) {
			return $mimes;
		}
		$mimes['svg'] = Plugin::MIME;
		if ( $this->plugin->settings()['allow_svgz'] ) {
			$mimes['svgz'] = Plugin::MIME;
		} else {
			unset( $mimes['svgz'] );
		}

		return $mimes;
	}

	/**
	 * Let SVG files through the real MIME type check. fileinfo reports SVG
	 * as `image/svg+xml`, `text/xml`, `text/plain` or `application/x-gzip`
	 * depending on the system; the prefilter has already checked the content.
	 *
	 * @param mixed  $data     ext, type and proper_filename.
	 * @param string $file     Full path to the file.
	 * @param string $filename File name.
	 * @return mixed
	 */
	public function check_filetype( $data, $file, $filename ) {
		$ext = FileProcessor::extension( (string) $filename );
		if ( ! in_array( $ext, array( 'svg', 'svgz' ), true ) || ! $this->plugin->current_user_can_upload() ) {
			return $data;
		}
		if ( 'svgz' === $ext && ! $this->plugin->settings()['allow_svgz'] ) {
			return $data;
		}
		$data                    = is_array( $data ) ? $data : array();
		$data['ext']             = $ext;
		$data['type']            = Plugin::MIME;
		$data['proper_filename'] = $data['proper_filename'] ?? false;

		return $data;
	}

	/**
	 * Sanitize an SVG upload in place, or reject it.
	 *
	 * Runs for every file with an SVG or SVGZ extension or MIME type,
	 * whoever uploads it, so SVG files allowed by another plugin are
	 * sanitized too.
	 *
	 * @param mixed $file Upload array (name, type, tmp_name, error, size).
	 * @return mixed
	 */
	public function prefilter( $file ) {
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			return $file;
		}
		$name = (string) ( $file['name'] ?? '' );
		$type = strtolower( (string) ( $file['type'] ?? '' ) );
		if ( ! FileProcessor::is_svg_name( $name ) && Plugin::MIME !== $type ) {
			return $file;
		}
		if ( 'svgz' === FileProcessor::extension( $name ) && ! $this->plugin->settings()['allow_svgz'] ) {
			$file['error'] = __( 'SVGZ uploads are switched off in Settings > SVG Safe.', 'wp-svg-safe' );
			return $file;
		}

		$path  = (string) $file['tmp_name'];
		$bytes = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temporary upload file.
		if ( false === $bytes ) {
			$file['error'] = __( 'The uploaded SVG file could not be read.', 'wp-svg-safe' );
			return $file;
		}

		$result = $this->plugin->processor()->process( $bytes, $name );
		if ( ! $result->ok() ) {
			$file['error'] = Plugin::error_message( $result->error() );
			return $file;
		}

		if ( $result->svg() !== $bytes ) {
			$written = file_put_contents( $path, $result->svg() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local temporary upload file.
			if ( false === $written ) {
				$file['error'] = __( 'The sanitized SVG file could not be saved.', 'wp-svg-safe' );
				return $file;
			}
			$file['size'] = $written;
		}
		$file['type'] = Plugin::MIME;

		if ( array() !== $result->removed() ) {
			/**
			 * Fires when the sanitizer removed something from an uploaded SVG.
			 *
			 * @param string[] $removed Removed items such as `element:script`.
			 * @param string   $name    Original file name.
			 */
			do_action( 'svg_safe_removed', $result->removed(), $name );
		}

		return $file;
	}
}
