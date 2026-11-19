<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Dimensions;
use Saizul\SvgSafe\Tests\WpTestCase;

final class DimensionsTest extends WpTestCase {

	/** @return array<string,array{0:string,1:int,2:int}> */
	public static function documents(): array {
		return array(
			'width and height'         => array( '<svg width="24" height="16">', 24, 16 ),
			'px units'                 => array( '<svg width="24px" height="16.4px">', 24, 16 ),
			'pt units'                 => array( '<svg width="72pt" height="36pt">', 96, 48 ),
			'mm units'                 => array( '<svg width="210mm" height="297mm" viewBox="0 0 210 297">', 794, 1123 ),
			'inches'                   => array( "<svg height='1in' width='2in'>", 192, 96 ),
			'viewBox only'             => array( '<svg viewBox="0 0 120 40">', 120, 40 ),
			'viewBox with commas'      => array( '<svg viewBox="0,0,48,32">', 48, 32 ),
			'percent uses viewBox'     => array( '<svg width="100%" height="100%" viewBox="0 0 64 32">', 64, 32 ),
			'width only uses ratio'    => array( '<svg width="200" viewBox="0 0 100 50">', 200, 100 ),
			'height only uses ratio'   => array( '<svg height="10" viewBox="0 0 100 50">', 20, 10 ),
			'prefixed root'            => array( '<svg:svg xmlns:svg="http://www.w3.org/2000/svg" width="5" height="6">', 5, 6 ),
			'nothing'                  => array( '<svg>', 0, 0 ),
			'bad viewBox'              => array( '<svg viewBox="0 0 -10 abc">', 0, 0 ),
			'zero size'                => array( '<svg width="0" height="0">', 0, 0 ),
			'tiny rounds up to 1'      => array( '<svg width="0.2" height="0.2">', 1, 1 ),
			'xml declaration first'    => array( "<?xml version=\"1.0\"?>\n<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"8\" height=\"9\">", 8, 9 ),
		);
	}

	/**
	 * @dataProvider documents
	 */
	public function test_from_svg( string $tag, int $width, int $height ): void {
		self::assertSame(
			array(
				'width'  => $width,
				'height' => $height,
			),
			Dimensions::from_svg( $tag . '</svg>' )
		);
	}

	public function test_length(): void {
		self::assertSame( 16.0, Dimensions::length( '1em' ) );
		self::assertSame( 1.5, Dimensions::length( ' 1.5 ' ) );
		self::assertNull( Dimensions::length( 'auto' ) );
		self::assertNull( Dimensions::length( '50%' ) );
		self::assertNull( Dimensions::length( '10furlongs' ) );
		self::assertNull( Dimensions::length( '-5' ) );
	}
}
