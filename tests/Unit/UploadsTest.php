<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Settings;
use Saizul\SvgSafe\Svgz;
use Saizul\SvgSafe\Tests\WpTestCase;
use Saizul\SvgSafe\Uploads;
use WpStubs;

final class UploadsTest extends WpTestCase {

	/** @var Uploads */
	private $uploads;

	protected function setUp(): void {
		parent::setUp();
		$this->uploads = new Uploads( self::plugin() );
	}

	/** @return array<string,mixed> */
	private function upload( string $contents, string $name = 'icon.svg', string $type = 'image/svg+xml' ): array {
		$path = $this->temp_file( $contents, '.' . pathinfo( $name, PATHINFO_EXTENSION ) );

		return array(
			'name'     => $name,
			'type'     => $type,
			'tmp_name' => $path,
			'error'    => 0,
			'size'     => strlen( $contents ),
		);
	}

	public function test_registers_filters(): void {
		$this->uploads->register();
		foreach ( array( 'upload_mimes', 'wp_check_filetype_and_ext', 'wp_handle_upload_prefilter', 'wp_handle_sideload_prefilter' ) as $hook ) {
			self::assertNotFalse( has_filter( $hook ), $hook );
		}
	}

	public function test_mimes_for_allowed_user(): void {
		$mimes = $this->uploads->mimes( array( 'jpg|jpeg|jpe' => 'image/jpeg' ) );

		self::assertSame( 'image/svg+xml', $mimes['svg'] );
		self::assertSame( 'image/svg+xml', $mimes['svgz'] );
		self::assertSame( 'image/jpeg', $mimes['jpg|jpeg|jpe'] );
	}

	public function test_mimes_respect_roles_and_svgz_setting(): void {
		WpStubs::$roles = array( 'author' );
		self::assertArrayNotHasKey( 'svg', $this->uploads->mimes( array() ) );

		WpStubs::$roles                       = array( 'editor' );
		WpStubs::$options[ Settings::OPTION ] = array( 'allow_svgz' => false );
		$mimes                                = $this->uploads->mimes( array( 'svgz' => 'image/svg+xml' ) );
		self::assertArrayHasKey( 'svg', $mimes );
		self::assertArrayNotHasKey( 'svgz', $mimes );

		WpStubs::$logged_in = false;
		self::assertSame( array(), $this->uploads->mimes( array() ) );
		self::assertSame( 'x', $this->uploads->mimes( 'x' ) );
	}

	public function test_check_filetype(): void {
		$data = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		self::assertSame( array( 'ext' => 'svg', 'type' => 'image/svg+xml', 'proper_filename' => false ), $this->uploads->check_filetype( $data, '/tmp/x', 'Logo.SVG' ) );
		self::assertSame( $data, $this->uploads->check_filetype( $data, '/tmp/x', 'photo.png' ) );

		WpStubs::$roles = array( 'subscriber' );
		self::assertSame( $data, $this->uploads->check_filetype( $data, '/tmp/x', 'logo.svg' ) );

		WpStubs::$roles                       = array( 'administrator' );
		WpStubs::$options[ Settings::OPTION ] = array( 'allow_svgz' => false );
		self::assertSame( $data, $this->uploads->check_filetype( $data, '/tmp/x', 'logo.svgz' ) );
	}

	public function test_prefilter_sanitizes_file_in_place(): void {
		$removed = array();
		add_action(
			'svg_safe_removed',
			static function ( $items, $name ) use ( &$removed ) {
				$removed = array( $items, $name );
			}
		);
		$file   = $this->upload( self::fixture( 'malicious/03-onload-root.svg' ), 'evil.svg', 'text/plain' );
		$result = $this->uploads->prefilter( $file );

		self::assertSame( 0, $result['error'] );
		self::assertSame( 'image/svg+xml', $result['type'] );
		$stored = (string) file_get_contents( $file['tmp_name'] );
		self::assertStringNotContainsString( 'onload', $stored );
		self::assertSame( strlen( $stored ), $result['size'] );
		self::assertSame( array( array( 'attribute:onload' ), 'evil.svg' ), $removed );
	}

	public function test_prefilter_runs_even_for_users_without_permission(): void {
		WpStubs::$roles = array( 'subscriber' );
		$file           = $this->upload( self::fixture( 'malicious/01-script-element.svg' ) );
		$this->uploads->prefilter( $file );

		self::assertStringNotContainsString( 'script', (string) file_get_contents( $file['tmp_name'] ) );
	}

	public function test_prefilter_detects_svg_by_mime_type(): void {
		$file = $this->upload( self::fixture( 'malicious/01-script-element.svg' ), 'upload.tmp', 'image/svg+xml' );
		$this->uploads->prefilter( $file );

		self::assertStringNotContainsString( 'script', (string) file_get_contents( $file['tmp_name'] ) );
	}

	public function test_prefilter_rejects_dangerous_files(): void {
		$result = $this->uploads->prefilter( $this->upload( self::fixture( 'rejected/xxe-file.svg' ) ) );
		self::assertSame( 'The SVG file declares XML entities, which are not allowed.', $result['error'] );

		$result = $this->uploads->prefilter( $this->upload( 'not xml' ) );
		self::assertSame( 'The SVG file is not well-formed XML.', $result['error'] );
	}

	public function test_prefilter_handles_svgz(): void {
		$file   = $this->upload( Svgz::encode( self::fixture( 'malicious/04-onclick-child.svg' ) ), 'icon.svgz', 'application/x-gzip' );
		$result = $this->uploads->prefilter( $file );

		self::assertSame( 0, $result['error'] );
		$stored = (string) file_get_contents( $file['tmp_name'] );
		self::assertTrue( Svgz::is_gzip( $stored ) );
		self::assertStringNotContainsString( 'onclick', (string) Svgz::decode( $stored, 100000 ) );
	}

	public function test_prefilter_refuses_svgz_when_switched_off(): void {
		WpStubs::$options[ Settings::OPTION ] = array( 'allow_svgz' => false );
		$result                               = $this->uploads->prefilter( $this->upload( Svgz::encode( '<svg/>' ), 'icon.svgz' ) );

		self::assertSame( 'SVGZ uploads are switched off in Settings > SVG Safe.', $result['error'] );
	}

	public function test_prefilter_enforces_size_limit(): void {
		WpStubs::$options[ Settings::OPTION ] = array( 'max_kb' => 16 );
		$big                                  = '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat( '<g/>', 5000 ) . '</svg>';
		$result                               = $this->uploads->prefilter( $this->upload( $big ) );

		self::assertSame( 'The SVG file is larger than the limit set in Settings > SVG Safe.', $result['error'] );
	}

	public function test_prefilter_ignores_other_files_and_earlier_errors(): void {
		$png = $this->upload( 'PNG', 'photo.png', 'image/png' );
		self::assertSame( $png, $this->uploads->prefilter( $png ) );

		$failed          = $this->upload( '<svg onload="x()"/>' );
		$failed['error'] = 'Too big';
		self::assertSame( $failed, $this->uploads->prefilter( $failed ) );
		self::assertSame( 'x', $this->uploads->prefilter( 'x' ) );
	}

	public function test_clean_file_is_not_rewritten(): void {
		$clean = ( new \Saizul\SvgSafe\Sanitizer() )->sanitize( self::fixture( 'valid/heart.svg' ) )->svg();
		$file  = $this->upload( $clean );
		$this->uploads->prefilter( $file );

		self::assertSame( $clean, file_get_contents( $file['tmp_name'] ) );
		self::assertNotContains( 'svg_safe_removed', WpStubs::calls( 'do_action' ) );
	}
}
