<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Permissions;
use Saizul\SvgSafe\Settings;
use Saizul\SvgSafe\Tests\WpTestCase;
use WpStubs;

final class PermissionsSettingsTest extends WpTestCase {

	public function test_defaults_allow_admins_and_editors(): void {
		$settings = Settings::get();
		self::assertSame( array( 'administrator', 'editor' ), $settings['roles'] );

		foreach ( array( 'administrator' => true, 'editor' => true, 'author' => false, 'subscriber' => false ) as $role => $expected ) {
			WpStubs::$roles = array( $role );
			self::assertSame( $expected, Permissions::current_user_can_upload( $settings ), $role );
		}
	}

	public function test_requires_upload_files_and_login(): void {
		WpStubs::$can = array( 'upload_files' => false );
		self::assertFalse( Permissions::current_user_can_upload( Settings::get() ) );

		WpStubs::$can       = true;
		WpStubs::$logged_in = false;
		self::assertFalse( Permissions::current_user_can_upload( Settings::get() ) );
	}

	public function test_super_admin_on_multisite(): void {
		WpStubs::$roles                 = array();
		WpStubs::$super_admin           = true;
		WpStubs::$registry['multisite'] = true;

		self::assertTrue( Permissions::current_user_can_upload( Settings::get() ) );
	}

	public function test_filter_decides_last(): void {
		WpStubs::$roles = array( 'author' );
		add_filter(
			'svg_safe_user_can_upload',
			static function ( $allowed, $settings ) {
				return ! $allowed && in_array( 'editor', $settings['roles'], true );
			},
			10,
			2
		);

		self::assertTrue( Permissions::current_user_can_upload( Settings::get() ) );
	}

	public function test_sanitize(): void {
		$clean = Settings::sanitize(
			array(
				'roles'      => array( 'Editor', 'author', 'ghost', 'author', '<script>' ),
				'allow_svgz' => '',
				'max_kb'     => '999999',
			)
		);

		self::assertSame(
			array(
				'roles'      => array( 'editor', 'author' ),
				'allow_svgz' => false,
				'max_kb'     => Settings::MAX_KB,
			),
			$clean
		);
		self::assertSame( Settings::MIN_KB, Settings::sanitize( array( 'max_kb' => '-3' ) )['max_kb'] );
		self::assertSame( array(), Settings::sanitize( 'junk' )['roles'] );
		self::assertSame( Settings::defaults()['max_kb'], Settings::sanitize( array() )['max_kb'] );
	}

	public function test_get_ignores_broken_option(): void {
		WpStubs::$options[ Settings::OPTION ] = 'broken';
		self::assertSame( Settings::defaults(), Settings::get() );
	}
}
