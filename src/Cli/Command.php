<?php
/**
 * WP-CLI command.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe\Cli;

use Saizul\SvgSafe\Media;
use Saizul\SvgSafe\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize SVG files in the media library or on disk.
 *
 * ## EXAMPLES
 *
 *     wp svg-safe sanitize --dry-run
 *     wp svg-safe sanitize
 *     wp svg-safe sanitize 42 57
 *     wp svg-safe check logo.svg
 *     wp svg-safe check logo.svg --output=logo.clean.svg
 */
final class Command {

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
	 * Re-sanitize SVG attachments and refresh their width and height.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Attachment IDs. All SVG attachments when left out.
	 *
	 * [--dry-run]
	 * : Report what would change without writing files.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp svg-safe sanitize --dry-run
	 *     wp svg-safe sanitize 42
	 *
	 * @param string[]             $args       Attachment IDs.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function sanitize( array $args, array $assoc_args ): void {
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$format  = (string) ( $assoc_args['format'] ?? 'table' );
		$ids     = array() === $args ? $this->all_svg_ids() : array_map( 'absint', $args );

		if ( array() === $ids ) {
			\WP_CLI::success( 'No SVG attachments found.' );
			return;
		}

		$processor = $this->plugin->processor();
		$media     = new Media( $this->plugin );
		$rows      = array();
		$counts    = array(
			'clean'     => 0,
			'sanitized' => 0,
			'failed'    => 0,
		);

		foreach ( $ids as $id ) {
			$row = array(
				'id'      => $id,
				'file'    => '',
				'status'  => 'failed',
				'removed' => '',
			);
			if ( ! Media::is_svg( $id ) ) {
				$row['removed'] = 'not an SVG attachment';
				$rows[]         = $row;
				++$counts['failed'];
				continue;
			}
			$path        = (string) get_attached_file( $id );
			$row['file'] = basename( $path );
			$bytes       = '' !== $path && is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in the uploads folder.
			if ( false === $bytes ) {
				$row['removed'] = 'file missing';
				$rows[]         = $row;
				++$counts['failed'];
				continue;
			}

			$result = $processor->process( $bytes, $path );
			if ( ! $result->ok() ) {
				$row['removed'] = 'error: ' . $result->error();
				$rows[]         = $row;
				++$counts['failed'];
				continue;
			}

			$changed        = array() !== $result->removed();
			$row['status']  = $changed ? ( $dry_run ? 'would sanitize' : 'sanitized' ) : 'clean';
			$row['removed'] = implode( ', ', $result->removed() );
			++$counts[ $changed ? 'sanitized' : 'clean' ];

			if ( ! $dry_run ) {
				if ( $changed && false === file_put_contents( $path, $result->svg() ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local file in the uploads folder.
					$row['status']  = 'failed';
					$row['removed'] = 'file not writable';
					++$counts['failed'];
					--$counts['sanitized'];
					$rows[] = $row;
					continue;
				}
				$meta = $media->metadata_for( $id );
				if ( null !== $meta ) {
					$old = wp_get_attachment_metadata( $id );
					wp_update_attachment_metadata( $id, array_merge( is_array( $old ) ? $old : array(), $meta ) );
				}
			}
			$rows[] = $row;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'file', 'status', 'removed' ) );

		$summary = sprintf(
			'%d clean, %d %s, %d failed.',
			$counts['clean'],
			$counts['sanitized'],
			$dry_run ? 'would be sanitized' : 'sanitized',
			$counts['failed']
		);
		if ( $counts['failed'] > 0 ) {
			\WP_CLI::warning( $summary );
			return;
		}
		\WP_CLI::success( $summary );
	}

	/**
	 * Check a local SVG or SVGZ file and optionally write a sanitized copy.
	 *
	 * Exits with status 1 when the file had unsafe content or could not be
	 * sanitized, so it can be used in build scripts.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to an .svg or .svgz file.
	 *
	 * [--output=<file>]
	 * : Write the sanitized file here (use the input path to fix it in place).
	 *
	 * ## EXAMPLES
	 *
	 *     wp svg-safe check icon.svg
	 *     wp svg-safe check icon.svg --output=icon.svg
	 *
	 * @param string[]             $args       File path.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function check( array $args, array $assoc_args ): void {
		$path = (string) ( $args[0] ?? '' );
		if ( '' === $path || ! is_readable( $path ) ) {
			\WP_CLI::error( 'File not found: ' . $path );
		}
		$bytes  = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file named on the command line.
		$output = (string) ( $assoc_args['output'] ?? '' );
		$result = $this->plugin->processor()->process( $bytes, '' !== $output ? $output : $path );
		if ( ! $result->ok() ) {
			\WP_CLI::error( 'Cannot sanitize: ' . $result->error() );
		}

		foreach ( $result->removed() as $item ) {
			\WP_CLI::line( 'removed ' . $item );
		}
		if ( '' !== $output ) {
			if ( false === file_put_contents( $output, $result->svg() ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local file named on the command line.
				\WP_CLI::error( 'Cannot write ' . $output );
			}
			\WP_CLI::line( 'Wrote ' . $output );
		}
		if ( array() !== $result->removed() ) {
			\WP_CLI::warning( sprintf( '%d unsafe or unsupported item(s) found in %s.', count( $result->removed() ), $path ) );
			\WP_CLI::halt( 1 );
		}
		\WP_CLI::success( $path . ' is clean.' );
	}

	/**
	 * IDs of every SVG attachment.
	 *
	 * @return int[]
	 */
	private function all_svg_ids(): array {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'post_mime_type'   => Plugin::MIME,
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}
}
