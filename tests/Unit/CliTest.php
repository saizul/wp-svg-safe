<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Cli\Command;
use Saizul\SvgSafe\Svgz;
use Saizul\SvgSafe\Tests\WpTestCase;
use WP_CLI;
use WpCliHalt;
use WpStubs;

final class CliTest extends WpTestCase {

	/** @var Command */
	private $command;

	protected function setUp(): void {
		parent::setUp();
		$this->command = new Command( self::plugin() );
	}

	private function attachment( int $id, string $contents, string $ext = 'svg' ): string {
		$path                        = $this->temp_file( $contents, '.' . $ext );
		WpStubs::$attachments[ $id ] = array(
			'mime' => 'image/svg+xml',
			'file' => $path,
			'url'  => 'https://example.org/' . basename( $path ),
			'meta' => array( 'image_meta' => array( 'kept' => true ) ),
		);

		return $path;
	}

	private function last( string $type ): string {
		$messages = array_values(
			array_filter(
				WP_CLI::$log,
				static function ( array $entry ) use ( $type ): bool {
					return $type === $entry[0];
				}
			)
		);

		return $messages ? (string) end( $messages )[1] : '';
	}

	/** @return array<int,array<string,mixed>> */
	private function rows(): array {
		return WP_CLI::$items['items'];
	}

	public function test_sanitize_all(): void {
		$dirty = $this->attachment( 1, self::fixture( 'malicious/01-script-element.svg' ) );
		$clean = $this->attachment( 2, ( new \Saizul\SvgSafe\Sanitizer() )->sanitize( self::fixture( 'valid/heart.svg' ) )->svg() );
		$gz    = $this->attachment( 3, Svgz::encode( self::fixture( 'malicious/03-onload-root.svg' ) ), 'svgz' );
		WpStubs::$attachments[4] = array( 'mime' => 'image/png' );

		$this->command->sanitize( array(), array() );

		self::assertSame( 'image/svg+xml', WpStubs::$registry['get_posts']['post_mime_type'] );
		self::assertSame( array( 'sanitized', 'clean', 'sanitized' ), array_column( $this->rows(), 'status' ) );
		self::assertStringNotContainsString( 'script', (string) file_get_contents( $dirty ) );
		self::assertStringNotContainsString( 'onload', (string) Svgz::decode( (string) file_get_contents( $gz ), 100000 ) );
		self::assertSame( '1 clean, 2 sanitized, 0 failed.', $this->last( 'success' ) );

		// Metadata refreshed, existing keys kept.
		self::assertSame( 24, WpStubs::$attachments[2]['meta']['width'] );
		self::assertSame( array( 'kept' => true ), WpStubs::$attachments[2]['meta']['image_meta'] );
		self::assertSame( 10, WpStubs::$attachments[3]['meta']['width'] );
		self::assertNotEmpty( $clean );
	}

	public function test_dry_run_writes_nothing(): void {
		$original = self::fixture( 'malicious/01-script-element.svg' );
		$path     = $this->attachment( 1, $original );

		$this->command->sanitize( array( '1' ), array( 'dry-run' => true, 'format' => 'json' ) );

		self::assertSame( $original, file_get_contents( $path ) );
		self::assertSame( 'json', WP_CLI::$items['format'] );
		self::assertSame( 'would sanitize', $this->rows()[0]['status'] );
		self::assertSame( 'element:script', $this->rows()[0]['removed'] );
		self::assertSame( array(), WpStubs::calls( 'update_metadata' ) );
		self::assertSame( '0 clean, 1 would be sanitized, 0 failed.', $this->last( 'success' ) );
	}

	public function test_failures_are_reported(): void {
		$this->attachment( 1, self::fixture( 'rejected/xxe-file.svg' ) );
		WpStubs::$attachments[2] = array(
			'mime' => 'image/svg+xml',
			'file' => '/missing/file.svg',
		);
		WpStubs::$attachments[3] = array( 'mime' => 'image/png' );

		$this->command->sanitize( array( '1', '2', '3' ), array() );

		self::assertSame( array( 'error: entities', 'file missing', 'not an SVG attachment' ), array_column( $this->rows(), 'removed' ) );
		self::assertSame( '0 clean, 0 sanitized, 3 failed.', $this->last( 'warning' ) );
	}

	public function test_nothing_to_do(): void {
		$this->command->sanitize( array(), array() );
		self::assertSame( 'No SVG attachments found.', $this->last( 'success' ) );
	}

	public function test_check_clean_file(): void {
		$path = $this->temp_file( ( new \Saizul\SvgSafe\Sanitizer() )->sanitize( self::fixture( 'valid/heart.svg' ) )->svg() );
		$this->command->check( array( $path ), array() );

		self::assertSame( $path . ' is clean.', $this->last( 'success' ) );
	}

	public function test_check_unsafe_file_exits_1_and_writes_output(): void {
		$path   = $this->temp_file( self::fixture( 'malicious/04-onclick-child.svg' ) );
		$output = $this->temp_file( '' );
		try {
			$this->command->check( array( $path ), array( 'output' => $output ) );
			self::fail( 'Expected exit code 1' );
		} catch ( WpCliHalt $halt ) {
			self::assertSame( 1, $halt->getCode() );
		}

		self::assertSame( 'removed attribute:onclick', WP_CLI::$log[0][1] );
		self::assertStringNotContainsString( 'onclick', (string) file_get_contents( $output ) );
		self::assertStringContainsString( '3 unsafe or unsupported item(s)', $this->last( 'warning' ) );
	}

	public function test_check_errors(): void {
		try {
			$this->command->check( array( '/no/such.svg' ), array() );
			self::fail( 'Expected an error' );
		} catch ( WpCliHalt $halt ) {
			self::assertSame( 'File not found: /no/such.svg', $this->last( 'error' ) );
		}
		try {
			$this->command->check( array( $this->temp_file( 'nope' ) ), array() );
			self::fail( 'Expected an error' );
		} catch ( WpCliHalt $halt ) {
			self::assertSame( 'Cannot sanitize: parse', $this->last( 'error' ) );
		}
	}
}
