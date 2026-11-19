<?php
declare(strict_types=1);

namespace Saizul\SvgSafe\Tests\Unit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Saizul\SvgSafe\Sanitizer;
use Saizul\SvgSafe\Tests\WpTestCase;

/**
 * The malicious corpus in tests/fixtures/malicious must come out harmless,
 * the corpus in tests/fixtures/rejected must be refused, and the icons in
 * tests/fixtures/valid must come out unchanged.
 */
final class SanitizerTest extends WpTestCase {

	/** @return array<string,array{0:string}> */
	private static function files( string $dir ): array {
		$out = array();
		foreach ( glob( dirname( __DIR__ ) . '/fixtures/' . $dir . '/*.svg' ) ?: array() as $file ) {
			$out[ basename( $file ) ] = array( $file );
		}
		self::assertNotEmpty( $out );

		return $out;
	}

	/** @return array<string,array{0:string}> */
	public static function malicious(): array {
		return self::files( 'malicious' );
	}

	/** @return array<string,array{0:string}> */
	public static function valid(): array {
		return self::files( 'valid' );
	}

	/** @return array<string,array{0:string,1:string}> */
	public static function rejected(): array {
		return array(
			'xxe-file'            => array( 'xxe-file.svg', 'entities' ),
			'xxe-parameter'       => array( 'xxe-parameter.svg', 'entities' ),
			'billion-laughs'      => array( 'billion-laughs.svg', 'entities' ),
			'not-svg-root'        => array( 'not-svg-root.svg', 'root' ),
			'svg-wrong-namespace' => array( 'svg-wrong-namespace.svg', 'root' ),
			'html-not-xml'        => array( 'html-not-xml.svg', 'parse' ),
			'broken-xml'          => array( 'broken-xml.svg', 'parse' ),
		);
	}

	/**
	 * Independent safety check of sanitized output.
	 */
	private static function assert_safe( string $svg, string $label ): void {
		$flat = strtolower( (string) preg_replace( '/[\x00-\x20]+/', '', $svg ) );
		foreach ( array( '<script', 'javascript:', 'vbscript:', 'foreignobject', '<iframe', '<embed', '<object', '<!entity', '<!doctype', '<?xml-stylesheet', '@import', 'expression(', '<!--', 'data:text', 'data:image/svg', 'url(http', 'url(//', 'url(\'http', 'http-equiv' ) as $needle ) {
			self::assertStringNotContainsString( $needle, $flat, $label . ': output contains ' . $needle );
		}

		$doc = new DOMDocument();
		self::assertTrue( $doc->loadXML( $svg, LIBXML_NONET ), $label . ': output is not well-formed' );
		$allowed = array_flip( Sanitizer::default_elements() );
		foreach ( ( new DOMXPath( $doc ) )->query( '//*' ) ?: array() as $element ) {
			self::assertInstanceOf( DOMElement::class, $element );
			self::assertArrayHasKey( $element->localName, $allowed, $label . ': element ' . $element->localName );
			self::assertSame( Sanitizer::SVG_NS, $element->namespaceURI, $label . ': namespace of ' . $element->localName );
			foreach ( $element->attributes as $attribute ) {
				$name = strtolower( (string) $attribute->localName );
				self::assertStringStartsNotWith( 'on', $name, $label . ': event handler ' . $name );
				if ( 'href' === $name ) {
					self::assertMatchesRegularExpression( '#^(\#[\w.:-]+|data:image/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=\s]+)$#', (string) $attribute->value, $label . ': href' );
				}
				self::assertNotContains( (string) $attribute->namespaceURI, array( 'http://www.w3.org/2001/xml-events', 'http://www.w3.org/1999/xhtml' ), $label );
			}
		}
		foreach ( ( new DOMXPath( $doc ) )->query( '//comment() | //processing-instruction()' ) ?: array() as $node ) {
			self::fail( $label . ': comment or processing instruction left: ' . $node->nodeName );
		}
	}

	/**
	 * @dataProvider malicious
	 */
	public function test_malicious_payloads_are_neutralised( string $file ): void {
		$result = ( new Sanitizer() )->sanitize( (string) file_get_contents( $file ) );

		self::assertTrue( $result->ok(), basename( $file ) . ' should be sanitized, not rejected: ' . $result->error() );
		self::assert_safe( $result->svg(), basename( $file ) );
		if ( 'malicious/30-entity-in-text-only.svg' !== 'malicious/' . basename( $file ) ) {
			self::assertNotEmpty( $result->removed(), basename( $file ) . ' should report what was removed' );
		}
	}

	/**
	 * @dataProvider malicious
	 */
	public function test_sanitizing_is_idempotent( string $file ): void {
		$sanitizer = new Sanitizer();
		$once      = $sanitizer->sanitize( (string) file_get_contents( $file ) );
		$twice     = $sanitizer->sanitize( $once->svg() );

		self::assertSame( $once->svg(), $twice->svg() );
		self::assertSame( array(), $twice->removed() );
		self::assertTrue( $sanitizer->is_clean( $once->svg() ) );
	}

	/**
	 * @dataProvider rejected
	 */
	public function test_dangerous_or_broken_documents_are_rejected( string $file, string $code ): void {
		$result = ( new Sanitizer() )->sanitize( self::fixture( 'rejected/' . $file ) );

		self::assertFalse( $result->ok() );
		self::assertSame( $code, $result->error() );
		self::assertSame( '', $result->svg() );
	}

	/**
	 * @dataProvider valid
	 */
	public function test_valid_icons_are_preserved( string $file ): void {
		$input  = (string) file_get_contents( $file );
		$result = ( new Sanitizer() )->sanitize( $input );

		self::assertTrue( $result->ok(), $result->error() );
		self::assertSame( array(), $result->removed(), basename( $file ) . ': nothing should be removed' );

		$before = new DOMDocument();
		$before->loadXML( $input );
		$after = new DOMDocument();
		$after->loadXML( $result->svg() );
		self::assertSame( $before->documentElement->C14N(), $after->documentElement->C14N() );
		self::assert_safe( $result->svg(), basename( $file ) );
	}

	public function test_specific_removals_are_reported(): void {
		$result = ( new Sanitizer() )->sanitize( self::fixture( 'malicious/04-onclick-child.svg' ) );
		self::assertSame( array( 'attribute:onclick', 'attribute:onmouseover', 'attribute:ONFOCUS' ), $result->removed() );

		$result = ( new Sanitizer() )->sanitize( self::fixture( 'malicious/26-external-dtd.svg' ) );
		self::assertSame( array( 'doctype' ), $result->removed() );
	}

	public function test_links_are_unwrapped_and_children_kept(): void {
		$result = ( new Sanitizer() )->sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://example.org"><circle r="1" onclick="x()"/><a><rect width="1" height="1"/></a></a></svg>' );

		self::assertStringContainsString( '<circle r="1"/><rect width="1" height="1"/>', $result->svg() );
		self::assertStringNotContainsString( '<a', $result->svg() );
		self::assertContains( 'attribute:onclick', $result->removed() );
	}

	public function test_only_unsafe_style_declarations_are_removed(): void {
		$result = ( new Sanitizer() )->sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill: #f00; behavior: url(x.htc); stroke:url(#g); position:fixed" width="1" height="1"/></svg>' );

		self::assertStringContainsString( 'style="fill:#f00;stroke:url(#g)"', $result->svg() );
		self::assertSame( array( 'style:rect' ), $result->removed() );
	}

	public function test_style_comments_cannot_hide_a_payload(): void {
		$result = ( new Sanitizer() )->sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:u/**/rl(https://evil.example)" width="1" height="1"/><style>rect{fill:u/**/rl(//evil.example)}</style></svg>' );

		self::assertStringNotContainsString( 'evil', $result->svg() );
	}

	public function test_filters_cannot_allow_scripts_or_event_handlers(): void {
		$sanitizer = new Sanitizer(
			array_merge( Sanitizer::default_elements(), array( 'script', 'foreignObject', 'SET', 'a' ) ),
			array_merge( Sanitizer::default_attributes(), array( 'onload', 'OnClick', 'href', 'attributeName' ) )
		);
		$result    = $sanitizer->sanitize( '<svg xmlns="http://www.w3.org/2000/svg" onload="x()"><script>x()</script><foreignObject/><rect OnClick="x()" width="1" height="1"/></svg>' );

		self::assertSame( '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', trim( substr( $result->svg(), (int) strpos( $result->svg(), '<svg' ) ) ) );
	}

	public function test_custom_allowlist_is_respected(): void {
		$sanitizer = new Sanitizer( array( 'svg', 'path' ), array( 'd', 'viewBox' ) );
		$result    = $sanitizer->sanitize( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1" width="1"><path d="M0 0" fill="red"/><rect/></svg>' );

		self::assertStringContainsString( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0"/></svg>', $result->svg() );
		self::assertSame( array( 'attribute:width', 'attribute:fill', 'element:rect' ), $result->removed() );
	}

	public function test_size_limit_bom_and_empty_input(): void {
		$sanitizer = new Sanitizer( null, null, 100 );
		self::assertSame( 'too_large', $sanitizer->sanitize( '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat( ' ', 200 ) . '</svg>' )->error() );
		self::assertSame( 'empty', $sanitizer->sanitize( "  \n" )->error() );
		self::assertTrue( $sanitizer->sanitize( "\xEF\xBB\xBF<svg xmlns=\"http://www.w3.org/2000/svg\"/>" )->ok() );
	}

	public function test_missing_namespace_is_added(): void {
		$result = ( new Sanitizer() )->sanitize( '<svg width="5" height="5"><rect width="5" height="5"/><script>x()</script></svg>' );

		self::assertStringContainsString( '<svg xmlns="http://www.w3.org/2000/svg" width="5" height="5"><rect width="5" height="5"/></svg>', $result->svg() );
		self::assert_safe( $result->svg(), 'no namespace' );
	}

	public function test_output_is_utf8_and_keeps_text(): void {
		$result = ( new Sanitizer() )->sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><title>Café – বাংলা</title></svg>' );

		self::assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>', $result->svg() );
		self::assertStringContainsString( '<title>Café – বাংলা</title>', $result->svg() );
	}

	public function test_raster_data_urls_only_on_image(): void {
		$png    = 'data:image/png;base64,iVBORw0KGgo=';
		$result = ( new Sanitizer() )->sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><image href="' . $png . '"/><use href="' . $png . '"/><rect fill="' . $png . '"/></svg>' );

		self::assertStringContainsString( '<image href="' . $png . '"/>', $result->svg() );
		self::assertSame( array( 'attribute:href', 'attribute:fill' ), $result->removed() );
	}
}
