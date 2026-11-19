<?php
/**
 * WP-CLI stubs for unit tests.
 *
 * @package Saizul\SvgSafe
 */

// phpcs:ignoreFile

namespace {
	final class WpCliHalt extends RuntimeException {
	}

	class WP_CLI {
		/** @var array<int,array{0:string,1:string}> */
		public static $log = array();
		/** @var string */
		public static $out = '';
		/** @var array<int,array<string,mixed>> */
		public static $items = array();
		/** @var bool */
		public static $confirm = true;

		public static function reset(): void {
			self::$log     = array();
			self::$out     = '';
			self::$items   = array();
			self::$confirm = true;
		}

		public static function line( $message = '' ) {
			self::$log[] = array( 'line', (string) $message );
		}

		public static function out( $message ) {
			self::$out .= (string) $message;
		}

		public static function success( $message ) {
			self::$log[] = array( 'success', (string) $message );
		}

		public static function warning( $message ) {
			self::$log[] = array( 'warning', (string) $message );
		}

		public static function error( $message ) {
			self::$log[] = array( 'error', (string) $message );
			throw new WpCliHalt( (string) $message, 1 );
		}

		public static function halt( $code ) {
			throw new WpCliHalt( 'halt', (int) $code );
		}

		public static function confirm( $question, $assoc_args = array() ) {
			if ( isset( $assoc_args['yes'] ) ) {
				return;
			}
			self::$log[] = array( 'confirm', (string) $question );
			if ( ! self::$confirm ) {
				throw new WpCliHalt( 'aborted', 0 );
			}
		}

		public static function add_command( $name, $callable, $args = array() ) {
			self::$log[] = array( 'command', (string) $name );
		}
	}
}

namespace WP_CLI\Utils {
	function format_items( $format, $items, $fields ) {
		\WP_CLI::$items = array(
			'format' => $format,
			'items'  => $items,
			'fields' => $fields,
		);
	}
}
