<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\FileProcessor;
use Saizul\SvgSafe\Sanitizer;
use Saizul\SvgSafe\Svgz;
use Saizul\SvgSafe\Tests\WpTestCase;

final class FileProcessorTest extends WpTestCase {

	const DIRTY = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>';

	private static function processor( int $max = 100000 ): FileProcessor {
		return new FileProcessor( new Sanitizer(), $max );
	}

	public function test_names(): void {
		self::assertTrue( FileProcessor::is_svg_name( 'Logo.SVG' ) );
		self::assertTrue( FileProcessor::is_svg_name( '/tmp/a.svgz' ) );
		self::assertFalse( FileProcessor::is_svg_name( 'a.svg.php' ) );
		self::assertFalse( FileProcessor::is_svg_name( 'svg' ) );
	}

	public function test_gzip_round_trip(): void {
		$gz = Svgz::encode( 'hello' );
		self::assertTrue( Svgz::is_gzip( $gz ) );
		self::assertFalse( Svgz::is_gzip( 'hello' ) );
		self::assertSame( 'hello', Svgz::decode( $gz, 10 ) );
		self::assertNull( Svgz::decode( 'hello', 10 ) );
		self::assertNull( Svgz::decode( "\x1f\x8bbroken", 10 ) );
	}

	public function test_gzip_bomb_is_capped(): void {
		$bomb = Svgz::encode( '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat( ' ', 5000000 ) . '</svg>' );
		self::assertLessThan( 20000, strlen( $bomb ) );

		self::assertNull( Svgz::decode( $bomb, 100000 ) );
		$result = self::processor()->process( $bomb, 'bomb.svgz' );
		self::assertSame( 'gzip', $result->error() );
	}

	public function test_svgz_is_sanitized_and_compressed_again(): void {
		$result = self::processor()->process( Svgz::encode( self::DIRTY ), 'icon.svgz' );

		self::assertTrue( $result->ok() );
		self::assertTrue( Svgz::is_gzip( $result->svg() ) );
		$plain = (string) Svgz::decode( $result->svg(), 100000 );
		self::assertStringNotContainsString( 'onload', $plain );
		self::assertStringContainsString( '<rect width="1" height="1"/>', $plain );
		self::assertSame( array( 'attribute:onload' ), $result->removed() );
	}

	public function test_gzipped_data_with_svg_name_becomes_plain(): void {
		$result = self::processor()->process( Svgz::encode( self::DIRTY ), 'icon.svg' );

		self::assertFalse( Svgz::is_gzip( $result->svg() ) );
		self::assertStringStartsWith( '<?xml', $result->svg() );
	}

	public function test_plain_svg_with_svgz_name_is_compressed(): void {
		$result = self::processor()->process( self::DIRTY, 'icon.svgz' );

		self::assertTrue( Svgz::is_gzip( $result->svg() ) );
	}

	public function test_errors_pass_through(): void {
		self::assertSame( 'entities', self::processor()->process( self::fixture( 'rejected/xxe-file.svg' ), 'x.svg' )->error() );
	}

	public function test_plain(): void {
		self::assertSame( 'abc', self::processor()->plain( 'abc' ) );
		self::assertSame( 'abc', self::processor()->plain( Svgz::encode( 'abc' ) ) );
	}
}
