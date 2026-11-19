<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Plugin;
use Saizul\SvgSafe\Sanitizer;
use Saizul\SvgSafe\Settings;
use Saizul\SvgSafe\Tests\WpTestCase;
use WpStubs;

final class PluginTest extends WpTestCase {

	public function test_boot_registers_hooks_once(): void {
		$plugin = Plugin::boot( '/p/wp-svg-safe/wp-svg-safe.php' );

		self::assertSame( $plugin, Plugin::boot( '/other.php' ) );
		self::assertSame( $plugin, Plugin::instance() );
		self::assertNotFalse( has_filter( 'upload_mimes' ) );
		self::assertNotFalse( has_filter( 'image_downsize' ) );
		self::assertFalse( has_filter( 'admin_menu' ) );
	}

	public function test_admin_screens_only_in_admin(): void {
		WpStubs::$admin = true;
		Plugin::boot( '/p/wp-svg-safe/wp-svg-safe.php' );

		self::assertNotFalse( has_filter( 'admin_menu' ) );
	}

	public function test_textdomain(): void {
		self::plugin()->load_textdomain();
		self::assertSame( array( 'wp-svg-safe' ), WpStubs::calls( 'load_plugin_textdomain' ) );
	}

	public function test_sanitizer_uses_filters(): void {
		add_filter(
			'svg_safe_allowed_elements',
			static function ( array $elements ) {
				return array_diff( $elements, array( 'text' ) );
			}
		);
		add_filter(
			'svg_safe_allowed_attributes',
			static function ( array $attributes ) {
				return array_merge( $attributes, array( 'data-name' ) );
			}
		);
		$result = self::plugin()->sanitizer()->sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><text>a</text><g data-name="x"/></svg>' );

		self::assertSame( array( 'element:text' ), $result->removed() );
		self::assertStringContainsString( '<g data-name="x"/>', $result->svg() );
	}

	public function test_broken_filters_fall_back_to_defaults(): void {
		add_filter( 'svg_safe_allowed_elements', '__return_false' );
		add_filter( 'svg_safe_allowed_attributes', '__return_false' );

		self::assertTrue( self::plugin()->sanitizer()->is_clean( ( new Sanitizer() )->sanitize( self::fixture( 'valid/clip-mask.svg' ) )->svg() ) );
	}

	public function test_max_bytes(): void {
		WpStubs::$options[ Settings::OPTION ] = array( 'max_kb' => 100 );
		self::assertSame( 102400, self::plugin()->max_bytes() );

		add_filter(
			'svg_safe_max_bytes',
			static function () {
				return 10;
			}
		);
		self::assertSame( 1024, self::plugin()->max_bytes() );
	}

	public function test_error_messages(): void {
		foreach ( array( 'too_large', 'empty', 'entities', 'root', 'gzip', 'parse', 'other' ) as $code ) {
			self::assertNotSame( '', Plugin::error_message( $code ) );
		}
		self::assertSame( Plugin::error_message( 'parse' ), Plugin::error_message( 'other' ) );
	}

	public function test_capability_fallback(): void {
		add_filter( 'svg_safe_capability', '__return_false' );
		self::assertSame( 'manage_options', Plugin::capability() );
	}
}
