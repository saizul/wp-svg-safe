<?php
/**
 * PHPUnit bootstrap. Runs without WordPress, on light stubs.
 *
 * @package Saizul\SvgSafe
 */

declare(strict_types=1);

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( is_readable( $autoload ) ) {
	require_once $autoload;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$map = array(
				'Saizul\\SvgSafe\\Tests\\' => __DIR__ . '/',
				'Saizul\\SvgSafe\\'        => dirname( __DIR__ ) . '/src/',
			);
			foreach ( $map as $prefix => $dir ) {
				if ( 0 === strpos( $class_name, $prefix ) ) {
					$file = $dir . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
					if ( is_readable( $file ) ) {
						require_once $file;
					}
					return;
				}
			}
		}
	);
}

require_once __DIR__ . '/Stubs/wordpress.php';
require_once __DIR__ . '/Stubs/wpcli.php';
