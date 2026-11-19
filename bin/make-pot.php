<?php
/**
 * Regenerate languages/wp-svg-safe.pot without WP-CLI.
 *
 * Reads the PHP files of the plugin.
 *
 * Usage: php bin/make-pot.php
 *
 * `wp i18n make-pot . languages/wp-svg-safe.pot` produces an equivalent
 * file and can be used instead when WP-CLI is available.
 *
 * @package Saizul\SvgSafe
 */

// phpcs:ignoreFile -- Development script, not shipped in the release zip.

$root   = dirname( __DIR__ );
$domain = 'wp-svg-safe';
$files  = array( $root . '/wp-svg-safe.php', $root . '/uninstall.php' );
foreach ( array( 'src' ) as $dir ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}
}
sort( $files );

$functions = array(
	'__'         => array( 'single' ),
	'_e'         => array( 'single' ),
	'esc_html__' => array( 'single' ),
	'esc_html_e' => array( 'single' ),
	'esc_attr__' => array( 'single' ),
	'esc_attr_e' => array( 'single' ),
	'_n'         => array( 'single', 'plural' ),
);

/** @var array<string,array{msgid:string,plural:?string,context:?string,refs:string[],comments:string[]}> $entries */
$entries = array();

$add = static function ( string $msgid, ?string $plural, string $ref, ?string $comment, ?string $context = null ) use ( &$entries ): void {
	$key = (string) $context . "\4" . $msgid . "\0" . (string) $plural;
	if ( ! isset( $entries[ $key ] ) ) {
		$entries[ $key ] = array(
			'msgid'    => $msgid,
			'plural'   => $plural,
			'context'  => $context,
			'refs'     => array(),
			'comments' => array(),
		);
	}
	$entries[ $key ]['refs'][] = $ref;
	if ( null !== $comment && ! in_array( $comment, $entries[ $key ]['comments'], true ) ) {
		$entries[ $key ]['comments'][] = $comment;
	}
};

// Plugin header strings, as WordPress translates them.
$header = (string) file_get_contents( $root . '/wp-svg-safe.php' );
foreach ( array( 'Plugin Name', 'Plugin URI', 'Description', 'Author', 'Author URI' ) as $field ) {
	if ( preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*(.+)$/mi', $header, $m ) ) {
		$add( trim( $m[1] ), null, 'wp-svg-safe.php', $field . ' of the plugin' );
	}
}

foreach ( $files as $path ) {
	$relative = ltrim( str_replace( $root, '', $path ), '/' );
	$tokens   = token_get_all( (string) file_get_contents( $path ) );
	$count    = count( $tokens );
	$comment  = null;
	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];
		if ( is_array( $token ) && T_COMMENT === $token[0] && preg_match( '#/\*\s*translators:\s*(.*?)\s*\*/#s', $token[1], $m ) ) {
			$comment = 'translators: ' . $m[1];
			continue;
		}
		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( $functions[ $token[1] ] ) ) {
			continue;
		}
		$line = $token[2];
		$j    = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( '(' !== $tokens[ $j ] ) {
			continue;
		}
		// Collect top-level arguments as lists of tokens.
		$args  = array( array() );
		$depth = 0;
		for ( $k = $j + 1; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( '(' === $t || '[' === $t ) {
				$depth++;
			} elseif ( ')' === $t || ']' === $t ) {
				if ( 0 === $depth ) {
					break;
				}
				$depth--;
			} elseif ( ',' === $t && 0 === $depth ) {
				$args[] = array();
				continue;
			}
			$args[ count( $args ) - 1 ][] = $t;
		}
		$strings = array();
		foreach ( $args as $arg ) {
			$arg = array_values(
				array_filter(
					$arg,
					static function ( $t ) {
						return ! ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT ), true ) );
					}
				)
			);
			$strings[] = ( 1 === count( $arg ) && is_array( $arg[0] ) && T_CONSTANT_ENCAPSED_STRING === $arg[0][0] )
				? stripcslashes( substr( $arg[0][1], 1, -1 ) )
				: null;
		}
		$shape     = $functions[ $token[1] ];
		$domain_at = count( $shape ) + ( 'plural' === end( $shape ) ? 1 : 0 );
		if ( ! isset( $strings[ $domain_at ] ) || $domain !== $strings[ $domain_at ] || null === $strings[0] ) {
			$comment = null;
			continue;
		}
		$plural = in_array( 'plural', $shape, true ) ? $strings[1] : null;
		$add( $strings[0], $plural, $relative . ':' . $line, $comment );
		$comment = null;
	}
}

$escape = static function ( string $text ): string {
	return addcslashes( $text, "\\\"\n\t" );
};

$out   = array();
$out[] = '# Copyright (C) 2026 Saizul Amin';
$out[] = '# This file is distributed under the GPL-2.0-or-later.';
$out[] = 'msgid ""';
$out[] = 'msgstr ""';
$out[] = '"Project-Id-Version: SVG Safe 0.1.0\n"';
$out[] = '"Report-Msgid-Bugs-To: https://github.com/saizul/wp-svg-safe/issues\n"';
$out[] = '"MIME-Version: 1.0\n"';
$out[] = '"Content-Type: text/plain; charset=UTF-8\n"';
$out[] = '"Content-Transfer-Encoding: 8bit\n"';
$out[] = '"Language-Team: LANGUAGE <LL@li.org>\n"';
$out[] = '"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\n"';
$out[] = '"X-Domain: ' . $domain . '\n"';
$out[] = '';
foreach ( $entries as $entry ) {
	foreach ( $entry['comments'] as $comment ) {
		$out[] = '#. ' . $comment;
	}
	$out[] = '#: ' . implode( ' ', $entry['refs'] );
	if ( false !== strpos( $entry['msgid'], '%' ) ) {
		$js    = array() === array_filter( $entry['refs'], static function ( $ref ) { return false === strpos( $ref, '.js:' ); } );
		$out[] = $js ? '#, js-format' : '#, php-format';
	}
	if ( null !== $entry['context'] ) {
		$out[] = 'msgctxt "' . $escape( $entry['context'] ) . '"';
	}
	$out[] = 'msgid "' . $escape( $entry['msgid'] ) . '"';
	if ( null !== $entry['plural'] ) {
		$out[] = 'msgid_plural "' . $escape( $entry['plural'] ) . '"';
		$out[] = 'msgstr[0] ""';
		$out[] = 'msgstr[1] ""';
	} else {
		$out[] = 'msgstr ""';
	}
	$out[] = '';
}

file_put_contents( $root . '/languages/' . $domain . '.pot', implode( "\n", $out ) );
fwrite( STDOUT, count( $entries ) . " strings written to languages/{$domain}.pot\n" );
