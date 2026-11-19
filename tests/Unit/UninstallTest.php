<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Settings;
use Saizul\SvgSafe\Tests\WpTestCase;
use WpStubs;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UninstallTest extends WpTestCase {

	public function test_single_site(): void {
		WpStubs::$options[ Settings::OPTION ] = array( 'roles' => array() );
		WpStubs::$options['other']            = 1;
		define( 'WP_UNINSTALL_PLUGIN', 'wp-svg-safe/wp-svg-safe.php' );
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		self::assertSame( array( 'other' => 1 ), WpStubs::$options );
	}

	public function test_multisite(): void {
		WpStubs::$registry['multisite']      = true;
		WpStubs::$registry['sites']          = array( 1, 2 );
		WpStubs::$registry['site_options:1'] = array( Settings::OPTION => array() );
		WpStubs::$registry['site_options:2'] = array( Settings::OPTION => array(), 'keep' => 1 );
		define( 'WP_UNINSTALL_PLUGIN', 'wp-svg-safe/wp-svg-safe.php' );
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		self::assertSame( array(), WpStubs::$registry['site_options:1'] );
		self::assertSame( array( 'keep' => 1 ), WpStubs::$registry['site_options:2'] );
	}
}
