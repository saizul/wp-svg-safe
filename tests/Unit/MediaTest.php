<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Media;
use Saizul\SvgSafe\Svgz;
use Saizul\SvgSafe\Tests\WpTestCase;
use WpStubs;

final class MediaTest extends WpTestCase {

	/** @var Media */
	private $media;

	protected function setUp(): void {
		parent::setUp();
		$this->media = new Media( self::plugin() );
	}

	private function attachment( int $id, string $contents, string $ext = 'svg', $meta = false ): void {
		WpStubs::$attachments[ $id ] = array(
			'mime' => 'image/svg+xml',
			'file' => $this->temp_file( $contents, '.' . $ext ),
			'url'  => 'https://example.org/wp-content/uploads/2026/11/logo-' . $id . '.' . $ext,
			'meta' => $meta,
		);
	}

	public function test_registers_hooks(): void {
		$this->media->register();
		foreach ( array( 'wp_generate_attachment_metadata', 'image_downsize', 'wp_prepare_attachment_for_js', 'admin_enqueue_scripts' ) as $hook ) {
			self::assertNotFalse( has_filter( $hook ), $hook );
		}
	}

	public function test_generate_metadata(): void {
		$this->attachment( 5, self::fixture( 'valid/gradient-logo.svg' ) );
		$meta = $this->media->generate_metadata( array( 'image_meta' => array() ), 5 );

		self::assertSame( 120, $meta['width'] );
		self::assertSame( 40, $meta['height'] );
		self::assertSame( array(), $meta['sizes'] );
		self::assertSame( array(), $meta['image_meta'] );
		self::assertStringStartsWith( '2026/11/', $meta['file'] );
		self::assertGreaterThan( 0, $meta['filesize'] );
	}

	public function test_generate_metadata_for_svgz(): void {
		$this->attachment( 6, Svgz::encode( self::fixture( 'valid/heart.svg' ) ), 'svgz' );
		$meta = $this->media->generate_metadata( array(), 6 );

		self::assertSame( 24, $meta['width'] );
		self::assertSame( 24, $meta['height'] );
	}

	public function test_generate_metadata_leaves_other_types_alone(): void {
		WpStubs::$attachments[7] = array( 'mime' => 'image/png' );
		self::assertSame( array( 'width' => 3 ), $this->media->generate_metadata( array( 'width' => 3 ), 7 ) );
		WpStubs::$attachments[8] = array(
			'mime' => 'image/svg+xml',
			'file' => '/nope.svg',
		);
		self::assertSame( array(), $this->media->generate_metadata( array(), 8 ) );
	}

	public function test_downsize(): void {
		$meta = array(
			'width'  => 600,
			'height' => 300,
		);
		$this->attachment( 9, '<svg/>', 'svg', $meta );
		$url = WpStubs::$attachments[9]['url'];

		self::assertSame( array( $url, 600, 300, false ), $this->media->downsize( false, 9, 'full' ) );
		self::assertSame( array( $url, 150, 75, false ), $this->media->downsize( false, 9, 'thumbnail' ) );
		self::assertSame( array( $url, 300, 150, false ), $this->media->downsize( false, 9, 'medium' ) );
		self::assertSame( array( $url, 600, 300, false ), $this->media->downsize( false, 9, 'large' ) );
		self::assertSame( array( $url, 40, 20, false ), $this->media->downsize( false, 9, array( 40, 40 ) ) );
		self::assertSame( array( 'x' ), $this->media->downsize( array( 'x' ), 9, 'full' ) );

		WpStubs::$attachments[10] = array( 'mime' => 'image/png' );
		self::assertFalse( $this->media->downsize( false, 10, 'full' ) );
	}

	public function test_downsize_reads_file_when_metadata_is_missing(): void {
		$this->attachment( 11, self::fixture( 'valid/text-label.svg' ) );

		self::assertSame( 200, $this->media->downsize( false, 11, 'full' )[1] );
	}

	public function test_prepare_for_js_adds_sizes(): void {
		$this->attachment(
			12,
			'<svg/>',
			'svg',
			array(
				'width'  => 100,
				'height' => 400,
			)
		);
		$url      = WpStubs::$attachments[12]['url'];
		$response = $this->media->prepare_for_js(
			array(
				'id'   => 12,
				'mime' => 'image/svg+xml',
				'type' => 'image',
				'url'  => $url,
			),
			(object) array( 'ID' => 12 )
		);

		self::assertSame( 100, $response['width'] );
		self::assertSame( 400, $response['height'] );
		self::assertSame( 'portrait', $response['orientation'] );
		self::assertSame( $url, $response['icon'] );
		self::assertSame(
			array(
				'url'         => $url,
				'width'       => 38,
				'height'      => 150,
				'orientation' => 'portrait',
			),
			$response['sizes']['thumbnail']
		);
		self::assertSame( 400, $response['sizes']['full']['height'] );
		self::assertSame( array( 'full', 'thumbnail', 'medium', 'medium_large', 'large' ), array_keys( $response['sizes'] ) );
	}

	public function test_prepare_for_js_leaves_other_types_alone(): void {
		$png = array(
			'id'   => 1,
			'mime' => 'image/png',
			'url'  => 'x',
		);
		self::assertSame( $png, $this->media->prepare_for_js( $png, (object) array( 'ID' => 1 ) ) );
		self::assertSame( 'x', $this->media->prepare_for_js( 'x', (object) array() ) );
	}

	public function test_fit(): void {
		self::assertSame( array( 50, 25 ), Media::fit( 100, 50, 50, 0 ) );
		self::assertSame( array( 20, 10 ), Media::fit( 100, 50, 0, 10 ) );
		self::assertSame( array( 10, 5 ), Media::fit( 10, 5, 100, 100 ) );
		self::assertSame( array( 1, 150 ), Media::fit( 1, 1000, 150, 150 ) );
		self::assertSame( array( 150, 150 ), Media::fit( 0, 0, 150, 150 ) );
		self::assertSame( array( 768, 768 ), Media::fit( 0, 0, 768, 0 ) );
		self::assertSame( array( 0, 0 ), Media::fit( 0, 0, 0, 0 ) );
	}

	public function test_admin_styles(): void {
		$this->media->admin_styles();

		self::assertSame( array( 'wp-svg-safe-admin' ), WpStubs::calls( 'enqueue_style' ) );
		self::assertStringContainsString( 'img[src$=".svg"]', WpStubs::$registry['inline_style']['wp-svg-safe-admin'] );
	}
}
