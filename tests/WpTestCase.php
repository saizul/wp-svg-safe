<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests;

use PHPUnit\Framework\TestCase;
use Saizul\SvgSafe\Plugin;
use WP_CLI;
use WpStubs;

/**
 * Base class: resets the WordPress and WP-CLI stubs before every test and
 * cleans up temporary files.
 */
abstract class WpTestCase extends TestCase {

	/** @var string[] */
	private $temp_files = array();

	protected function setUp(): void {
		WpStubs::reset();
		WP_CLI::reset();
		Plugin::reset();
	}

	protected function tearDown(): void {
		foreach ( $this->temp_files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_files = array();
	}

	/**
	 * Write a temporary file and remove it after the test.
	 */
	protected function temp_file( string $contents, string $suffix = '.svg' ): string {
		$file = tempnam( sys_get_temp_dir(), 'svgsafe' );
		self::assertIsString( $file );
		$path = $file . $suffix;
		rename( $file, $path );
		file_put_contents( $path, $contents );
		$this->temp_files[] = $path;

		return $path;
	}

	protected static function fixture( string $path ): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/' . $path );
	}

	protected static function plugin(): Plugin {
		return new Plugin( '/var/www/wp-content/plugins/wp-svg-safe/wp-svg-safe.php' );
	}
}
