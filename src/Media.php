<?php
/**
 * Media library integration.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the real width and height of SVG attachments, answers
 * `wp_get_attachment_image_src()` for them, and gives the media grid
 * something to preview.
 */
final class Media {

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
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_metadata' ), 10, 2 );
		add_filter( 'image_downsize', array( $this, 'downsize' ), 10, 3 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_for_js' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
	}

	/**
	 * Whether an attachment is an SVG.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function is_svg( int $attachment_id ): bool {
		return Plugin::MIME === get_post_mime_type( $attachment_id );
	}

	/**
	 * Metadata of an SVG file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|null Null when the file cannot be read.
	 */
	public function metadata_for( int $attachment_id ): ?array {
		$path = (string) get_attached_file( $attachment_id );
		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in the uploads folder.
		$plain = false === $bytes ? null : $this->plugin->processor()->plain( $bytes );
		$size  = null === $plain ? array(
			'width'  => 0,
			'height' => 0,
		) : Dimensions::from_svg( $plain );

		return array(
			'width'    => $size['width'],
			'height'   => $size['height'],
			'file'     => _wp_relative_upload_path( $path ),
			'filesize' => false === $bytes ? 0 : strlen( $bytes ),
			'sizes'    => array(),
		);
	}

	/**
	 * Add width and height to the metadata of new SVG attachments.
	 *
	 * @param mixed $metadata      Metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return mixed
	 */
	public function generate_metadata( $metadata, $attachment_id ) {
		if ( ! self::is_svg( (int) $attachment_id ) ) {
			return $metadata;
		}
		$svg = $this->metadata_for( (int) $attachment_id );
		if ( null === $svg ) {
			return $metadata;
		}

		return array_merge( is_array( $metadata ) ? $metadata : array(), $svg );
	}

	/**
	 * Width and height of an SVG attachment, from the metadata or the file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int[] Width and height.
	 */
	public function dimensions( int $attachment_id ): array {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			return array( (int) $meta['width'], (int) $meta['height'] );
		}
		$svg = $this->metadata_for( $attachment_id );

		return null === $svg ? array( 0, 0 ) : array( (int) $svg['width'], (int) $svg['height'] );
	}

	/**
	 * Answer `image_downsize` for SVG attachments: the same file at the
	 * requested size, keeping the aspect ratio.
	 *
	 * @param mixed        $out           Short-circuit value.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Size name or [width, height].
	 * @return mixed
	 */
	public function downsize( $out, $attachment_id, $size ) {
		if ( false !== $out || ! self::is_svg( (int) $attachment_id ) ) {
			return $out;
		}
		$url = wp_get_attachment_url( (int) $attachment_id );
		if ( ! $url ) {
			return $out;
		}
		list( $width, $height ) = $this->dimensions( (int) $attachment_id );
		list( $max_w, $max_h )  = self::size_box( $size );
		list( $width, $height ) = self::fit( $width, $height, $max_w, $max_h );

		return array( $url, $width, $height, false );
	}

	/**
	 * Add sizes and dimensions to SVG attachments in the media modal and grid.
	 *
	 * @param mixed  $response   Attachment data for JavaScript.
	 * @param object $attachment Attachment post.
	 * @return mixed
	 */
	public function prepare_for_js( $response, $attachment ) {
		if ( ! is_array( $response ) || Plugin::MIME !== ( $response['mime'] ?? '' ) || empty( $response['url'] ) ) {
			return $response;
		}
		$id                     = (int) ( $response['id'] ?? ( $attachment->ID ?? 0 ) );
		list( $width, $height ) = $this->dimensions( $id );
		$url                    = (string) $response['url'];

		$sizes = array();
		foreach ( array_merge( array( 'full' ), array_keys( wp_get_registered_image_subsizes() ) ) as $name ) {
			list( $max_w, $max_h ) = self::size_box( $name );
			list( $w, $h )         = self::fit( $width, $height, $max_w, $max_h );
			$sizes[ $name ]        = array(
				'url'         => $url,
				'width'       => $w,
				'height'      => $h,
				'orientation' => $h > $w ? 'portrait' : 'landscape',
			);
		}

		$response['width']       = $width;
		$response['height']      = $height;
		$response['orientation'] = $height > $width ? 'portrait' : 'landscape';
		$response['sizes']       = $sizes;
		$response['icon']        = $url;

		return $response;
	}

	/**
	 * Make SVG thumbnails fill their box in the media screens (SVGs without a
	 * width attribute would otherwise collapse).
	 */
	public function admin_styles(): void {
		$css = '.attachment-preview .thumbnail img[src$=".svg"],.attachment-preview .thumbnail img[src$=".svgz"]{width:100%;height:auto;padding:8px;box-sizing:border-box}'
			. '.media-icon img[src$=".svg"],.media-icon img[src$=".svgz"]{width:60px;height:auto}'
			. '.attachment-info .thumbnail img[src$=".svg"],.attachment-details .thumbnail img[src$=".svg"],.media-frame img.details-image[src$=".svg"]{width:100%;max-width:100%;height:auto}';
		wp_register_style( 'wp-svg-safe-admin', false, array(), Plugin::VERSION );
		wp_enqueue_style( 'wp-svg-safe-admin' );
		wp_add_inline_style( 'wp-svg-safe-admin', $css );
	}

	/**
	 * Bounding box of a size.
	 *
	 * @param mixed $size Size name or [width, height].
	 * @return int[] Width and height, 0 for no limit.
	 */
	public static function size_box( $size ): array {
		if ( is_array( $size ) ) {
			return array( max( 0, (int) ( $size[0] ?? 0 ) ), max( 0, (int) ( $size[1] ?? 0 ) ) );
		}
		$sizes = wp_get_registered_image_subsizes();
		if ( is_string( $size ) && isset( $sizes[ $size ] ) ) {
			return array( max( 0, (int) $sizes[ $size ]['width'] ), max( 0, (int) $sizes[ $size ]['height'] ) );
		}

		return array( 0, 0 );
	}

	/**
	 * Fit a size into a box, keeping the aspect ratio, without upscaling.
	 *
	 * @param int $width  Width, 0 if unknown.
	 * @param int $height Height, 0 if unknown.
	 * @param int $max_w  Box width, 0 for no limit.
	 * @param int $max_h  Box height, 0 for no limit.
	 * @return int[] Width and height.
	 */
	public static function fit( int $width, int $height, int $max_w, int $max_h ): array {
		if ( $width <= 0 || $height <= 0 ) {
			return array( $max_w, $max_h > 0 ? $max_h : $max_w );
		}
		$ratio = 1.0;
		if ( $max_w > 0 && $width > $max_w ) {
			$ratio = $max_w / $width;
		}
		if ( $max_h > 0 && $height * $ratio > $max_h ) {
			$ratio = $max_h / $height;
		}

		return array( max( 1, (int) round( $width * $ratio ) ), max( 1, (int) round( $height * $ratio ) ) );
	}
}
