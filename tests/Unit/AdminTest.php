<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use Saizul\SvgSafe\Admin\SettingsPage;
use Saizul\SvgSafe\Settings;
use Saizul\SvgSafe\Tests\WpTestCase;
use WpDie;
use WpStubs;

final class AdminTest extends WpTestCase {

	/** @var SettingsPage */
	private $page;

	protected function setUp(): void {
		parent::setUp();
		$this->page = new SettingsPage( self::plugin() );
	}

	public function test_register_and_menu(): void {
		$this->page->register();
		self::assertNotFalse( has_filter( 'admin_menu' ) );
		self::assertNotFalse( has_filter( 'option_page_capability_wp_svg_safe' ) );
		self::assertNotFalse( has_filter( 'plugin_action_links_wp-svg-safe/wp-svg-safe.php' ) );

		$this->page->add_page();
		self::assertSame( array( 'wp-svg-safe:manage_options' ), WpStubs::calls( 'options_page' ) );
	}

	public function test_capability_filter(): void {
		add_filter(
			'svg_safe_capability',
			static function () {
				return 'edit_theme_options';
			}
		);
		$this->page->add_page();
		self::assertSame( array( 'wp-svg-safe:edit_theme_options' ), WpStubs::calls( 'options_page' ) );
	}

	public function test_register_settings(): void {
		$this->page->register_settings();

		$setting = WpStubs::$registry['settings'][ Settings::OPTION ];
		self::assertSame( array( Settings::class, 'sanitize' ), $setting['sanitize_callback'] );
		self::assertSame( array( 'svg_safe_roles', 'svg_safe_allow_svgz', 'svg_safe_max_kb' ), WpStubs::calls( 'field' ) );
	}

	public function test_fields_render_saved_values(): void {
		WpStubs::$options[ Settings::OPTION ] = array(
			'roles'      => array( 'author' ),
			'allow_svgz' => false,
			'max_kb'     => 512,
		);
		ob_start();
		$this->page->field( array( 'key' => 'roles' ) );
		$this->page->field( array( 'key' => 'allow_svgz' ) );
		$this->page->field( array( 'key' => 'max_kb' ) );
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'value="author" checked="checked"', $html );
		self::assertStringContainsString( 'value="editor"> Editor', $html );
		self::assertStringContainsString( 'name="wp_svg_safe_settings[allow_svgz]" value="1">', $html );
		self::assertStringContainsString( 'value="512"', $html );
	}

	public function test_render_checks_capability(): void {
		WpStubs::$can = false;
		$this->expectException( WpDie::class );
		$this->page->render();
	}

	public function test_render(): void {
		ob_start();
		$this->page->render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'action="options.php"', $html );
		self::assertStringContainsString( 'value="wp_svg_safe"', $html );
		self::assertStringContainsString( 'wp svg-safe sanitize', $html );
	}

	public function test_action_links(): void {
		$links = $this->page->action_links( array( 'deactivate' => 'x' ) );
		self::assertStringContainsString( 'options-general.php?page=wp-svg-safe', $links[0] );

		WpStubs::$can = false;
		self::assertSame( array( 'x' ), $this->page->action_links( array( 'x' ) ) );
	}
}
