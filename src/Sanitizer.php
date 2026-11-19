<?php
/**
 * SVG sanitizer.
 *
 * @package Saizul\SvgSafe
 */

namespace Saizul\SvgSafe;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

defined( 'ABSPATH' ) || exit;

/**
 * Strict allowlist SVG sanitizer built on DOMDocument.
 *
 * Pure PHP, no WordPress calls, so it can be tested and reused on its own.
 *
 * - The document is parsed with LIBXML_NONET and without entity substitution
 *   or DTD loading. Documents that declare entities are rejected outright
 *   (XXE, billion laughs); a plain DOCTYPE is dropped.
 * - Only allowlisted SVG elements survive. Elements in other namespaces
 *   (XHTML, Inkscape, Sodipodi...) are removed, `<a>` is unwrapped (its
 *   children are kept, the link is not).
 * - Only allowlisted attributes survive; every `on*` handler is removed.
 * - `href` / `xlink:href` may only point to a fragment in the same document
 *   (`#id`), and on `<image>` also to a base64 PNG, JPEG, GIF or WebP data URL.
 * - Attribute values and CSS may not contain `javascript:`, `vbscript:`,
 *   `data:`, `expression(`, `@import`, backslash escapes or a `url()` that
 *   does not point to a fragment.
 * - Comments, processing instructions and entity references are removed.
 */
final class Sanitizer {

	const SVG_NS   = 'http://www.w3.org/2000/svg';
	const XLINK_NS = 'http://www.w3.org/1999/xlink';
	const XML_NS   = 'http://www.w3.org/XML/1998/namespace';

	/**
	 * Elements that are never allowed, whatever the filters say.
	 */
	const FORBIDDEN_ELEMENTS = array(
		'script',
		'foreignObject',
		'iframe',
		'embed',
		'object',
		'handler',
		'listener',
		'animate',
		'animateColor',
		'animateMotion',
		'animateTransform',
		'set',
		'mpath',
		'feImage',
		'a',
		'html',
		'body',
		'meta',
		'link',
		'base',
		'form',
		'input',
		'button',
	);

	/**
	 * Attributes that are never allowed (lower case), besides every `on*`
	 * handler. `href` is handled by its own rule.
	 */
	const FORBIDDEN_ATTRIBUTES = array( 'href', 'attributename', 'xmlns', 'from', 'to', 'by', 'begin', 'formaction', 'srcdoc', 'src' );

	/**
	 * Elements whose children are kept when the element itself is removed.
	 */
	const UNWRAP_ELEMENTS = array( 'a' );

	/**
	 * Raster data URLs allowed in `<image href>`.
	 */
	const DATA_URL_PATTERN = '#^data:image/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=\s]+$#';

	/**
	 * Allowed elements (case-sensitive local names).
	 *
	 * @var array<string,bool>
	 */
	private $elements;

	/**
	 * Allowed attributes without a namespace.
	 *
	 * @var array<string,bool>
	 */
	private $attributes;

	/**
	 * Allowed CSS properties in `style` attributes.
	 *
	 * @var array<string,bool>
	 */
	private $properties;

	/**
	 * Largest input in bytes.
	 *
	 * @var int
	 */
	private $max_bytes;

	/**
	 * What was removed during the last run.
	 *
	 * @var string[]
	 */
	private $removed = array();

	/**
	 * Constructor.
	 *
	 * @param string[]|null $elements   Allowed elements, defaults to self::default_elements().
	 * @param string[]|null $attributes Allowed attributes, defaults to self::default_attributes().
	 * @param int           $max_bytes  Largest input in bytes.
	 */
	public function __construct( ?array $elements = null, ?array $attributes = null, int $max_bytes = 5242880 ) {
		$elements   = null === $elements ? self::default_elements() : $elements;
		$attributes = null === $attributes ? self::default_attributes() : $attributes;

		$forbidden      = array_map( 'strtolower', self::FORBIDDEN_ELEMENTS );
		$this->elements = array();
		foreach ( $elements as $element ) {
			$element = (string) $element;
			if ( '' !== $element && ! in_array( strtolower( $element ), $forbidden, true ) ) {
				$this->elements[ $element ] = true;
			}
		}
		$this->attributes = array();
		foreach ( $attributes as $attribute ) {
			$attribute = (string) $attribute;
			$lower     = strtolower( $attribute );
			if ( '' !== $attribute && 0 !== strpos( $lower, 'on' ) && ! in_array( $lower, self::FORBIDDEN_ATTRIBUTES, true ) ) {
				$this->attributes[ $attribute ] = true;
			}
		}
		$this->properties = array_fill_keys( self::css_properties(), true );
		$this->max_bytes  = max( 1, $max_bytes );
	}

	/**
	 * Default allowed elements.
	 *
	 * @return string[]
	 */
	public static function default_elements(): array {
		return array(
			'svg',
			'g',
			'defs',
			'symbol',
			'use',
			'title',
			'desc',
			'switch',
			'view',
			'style',
			'path',
			'rect',
			'circle',
			'ellipse',
			'line',
			'polyline',
			'polygon',
			'text',
			'tspan',
			'textPath',
			'linearGradient',
			'radialGradient',
			'stop',
			'pattern',
			'clipPath',
			'mask',
			'marker',
			'image',
			'filter',
			'feBlend',
			'feColorMatrix',
			'feComponentTransfer',
			'feComposite',
			'feConvolveMatrix',
			'feDiffuseLighting',
			'feDisplacementMap',
			'feDistantLight',
			'feDropShadow',
			'feFlood',
			'feFuncA',
			'feFuncB',
			'feFuncG',
			'feFuncR',
			'feGaussianBlur',
			'feMerge',
			'feMergeNode',
			'feMorphology',
			'feOffset',
			'fePointLight',
			'feSpecularLighting',
			'feSpotLight',
			'feTile',
			'feTurbulence',
		);
	}

	/**
	 * Default allowed attributes (without namespace). `href` and `style` are
	 * handled separately.
	 *
	 * @return string[]
	 */
	public static function default_attributes(): array {
		return array_merge(
			array(
				'id',
				'class',
				'style',
				'lang',
				'role',
				'aria-label',
				'aria-labelledby',
				'aria-describedby',
				'aria-hidden',
				'focusable',
				'version',
				'baseProfile',
				'viewBox',
				'preserveAspectRatio',
				'width',
				'height',
				'x',
				'y',
				'x1',
				'y1',
				'x2',
				'y2',
				'cx',
				'cy',
				'r',
				'rx',
				'ry',
				'fx',
				'fy',
				'fr',
				'd',
				'points',
				'pathLength',
				'transform',
				'dx',
				'dy',
				'rotate',
				'textLength',
				'lengthAdjust',
				'startOffset',
				'method',
				'spacing',
				'side',
				'clipPathUnits',
				'maskUnits',
				'maskContentUnits',
				'filterUnits',
				'primitiveUnits',
				'markerWidth',
				'markerHeight',
				'markerUnits',
				'refX',
				'refY',
				'orient',
				'gradientUnits',
				'gradientTransform',
				'spreadMethod',
				'offset',
				'patternUnits',
				'patternContentUnits',
				'patternTransform',
				'requiredFeatures',
				'requiredExtensions',
				'systemLanguage',
				'type',
				'media',
				'in',
				'in2',
				'result',
				'stdDeviation',
				'mode',
				'operator',
				'k1',
				'k2',
				'k3',
				'k4',
				'values',
				'tableValues',
				'slope',
				'intercept',
				'amplitude',
				'exponent',
				'kernelMatrix',
				'order',
				'divisor',
				'bias',
				'targetX',
				'targetY',
				'edgeMode',
				'kernelUnitLength',
				'preserveAlpha',
				'surfaceScale',
				'diffuseConstant',
				'specularConstant',
				'specularExponent',
				'scale',
				'xChannelSelector',
				'yChannelSelector',
				'azimuth',
				'elevation',
				'pointsAtX',
				'pointsAtY',
				'pointsAtZ',
				'limitingConeAngle',
				'radius',
				'baseFrequency',
				'numOctaves',
				'seed',
				'stitchTiles',
			),
			self::css_properties()
		);
	}

	/**
	 * Presentation attributes, also the CSS properties allowed in `style`.
	 *
	 * @return string[]
	 */
	public static function css_properties(): array {
		return array(
			'alignment-baseline',
			'baseline-shift',
			'clip',
			'clip-path',
			'clip-rule',
			'color',
			'color-interpolation',
			'color-interpolation-filters',
			'color-rendering',
			'direction',
			'display',
			'dominant-baseline',
			'enable-background',
			'fill',
			'fill-opacity',
			'fill-rule',
			'filter',
			'flood-color',
			'flood-opacity',
			'font',
			'font-family',
			'font-size',
			'font-size-adjust',
			'font-stretch',
			'font-style',
			'font-variant',
			'font-weight',
			'image-rendering',
			'isolation',
			'letter-spacing',
			'lighting-color',
			'marker',
			'marker-end',
			'marker-mid',
			'marker-start',
			'mask',
			'mix-blend-mode',
			'opacity',
			'overflow',
			'paint-order',
			'pointer-events',
			'shape-rendering',
			'stop-color',
			'stop-opacity',
			'stroke',
			'stroke-dasharray',
			'stroke-dashoffset',
			'stroke-linecap',
			'stroke-linejoin',
			'stroke-miterlimit',
			'stroke-opacity',
			'stroke-width',
			'text-anchor',
			'text-decoration',
			'text-rendering',
			'transform',
			'transform-origin',
			'unicode-bidi',
			'vector-effect',
			'visibility',
			'word-spacing',
			'writing-mode',
		);
	}

	/**
	 * Sanitize an SVG document.
	 *
	 * @param string $svg SVG markup (not gzipped).
	 */
	public function sanitize( string $svg ): Result {
		$this->removed = array();

		if ( strlen( $svg ) > $this->max_bytes ) {
			return Result::failure( 'too_large' );
		}
		// UTF-8 byte order mark.
		if ( 0 === strncmp( $svg, "\xEF\xBB\xBF", 3 ) ) {
			$svg = substr( $svg, 3 );
		}
		if ( '' === trim( $svg ) ) {
			return Result::failure( 'empty' );
		}
		// Entity declarations: XXE and entity expansion attacks. Never needed in an icon.
		if ( preg_match( '/<!ENTITY/i', $svg ) ) {
			return Result::failure( 'entities' );
		}

		$doc = $this->load( $svg );
		if ( null === $doc ) {
			return Result::failure( 'parse' );
		}

		$root = $doc->documentElement;
		if ( ! $root instanceof DOMElement || 'svg' !== $root->localName || ! in_array( $root->namespaceURI, array( self::SVG_NS, null ), true ) ) {
			return Result::failure( 'root' );
		}

		// Drop the DOCTYPE, comments and processing instructions around the root.
		foreach ( iterator_to_array( $doc->childNodes ) as $node ) {
			if ( $node !== $root ) {
				$this->note( $node );
				$doc->removeChild( $node );
			}
		}

		$ns = $root->namespaceURI;
		$this->clean_element( $root, $ns );
		if ( null === $ns ) {
			// Without the SVG namespace, browsers do not render the file as an image.
			$root->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns', self::SVG_NS );
		}

		$out = $doc->saveXML( $root );
		if ( false === $out || '' === $out ) {
			return Result::failure( 'parse' );
		}

		return Result::success( '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out . "\n", array_values( array_unique( $this->removed ) ) );
	}

	/**
	 * Whether a document is already clean (nothing would be removed).
	 *
	 * @param string $svg SVG markup.
	 */
	public function is_clean( string $svg ): bool {
		$result = $this->sanitize( $svg );

		return $result->ok() && array() === $result->removed();
	}

	/**
	 * Parse without network access, entity substitution or DTD loading.
	 *
	 * @param string $svg SVG markup.
	 */
	private function load( string $svg ): ?DOMDocument {
		$internal = libxml_use_internal_errors( true );
		$loader   = null;
		if ( PHP_VERSION_ID < 80000 ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.libxml_disable_entity_loaderDeprecated, Generic.PHP.DeprecatedFunctions.Deprecated -- Only called on PHP 7, where it is still needed.
			$loader = libxml_disable_entity_loader( true );
		}

		$doc                     = new DOMDocument();
		$doc->resolveExternals   = false;
		$doc->substituteEntities = false;
		$doc->validateOnParse    = false;
		$loaded                  = $doc->loadXML( $svg, LIBXML_NONET );

		if ( null !== $loader ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.libxml_disable_entity_loaderDeprecated, Generic.PHP.DeprecatedFunctions.Deprecated -- Restores the PHP 7 setting.
			libxml_disable_entity_loader( $loader );
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );

		if ( ! $loaded ) {
			return null;
		}
		$doc->encoding = 'UTF-8';

		return $doc;
	}

	/**
	 * Clean an element: its attributes, then its children.
	 *
	 * @param DOMElement  $element Element.
	 * @param string|null $ns      Namespace of the root element.
	 */
	private function clean_element( DOMElement $element, ?string $ns ): void {
		$this->clean_attributes( $element );

		if ( 'style' === $element->localName && ! $this->safe_stylesheet( (string) $element->textContent ) ) {
			$this->removed[] = 'element:style';
			$this->remove( $element );
			return;
		}

		foreach ( iterator_to_array( $element->childNodes ) as $child ) {
			$this->clean_child( $child, $element, $ns );
		}
	}

	/**
	 * Clean or remove one child node.
	 *
	 * @param DOMNode     $child  Child.
	 * @param DOMElement  $container Parent element.
	 * @param string|null $ns     Namespace of the root element.
	 */
	private function clean_child( DOMNode $child, DOMElement $container, ?string $ns ): void {
		if ( $child instanceof DOMElement ) {
			$name = (string) $child->localName;
			if ( $child->namespaceURI !== $ns || ! isset( $this->elements[ $name ] ) ) {
				$this->removed[] = 'element:' . ( $child->prefix ? $child->prefix . ':' : '' ) . $name;
				if ( $child->namespaceURI === $ns && in_array( $name, self::UNWRAP_ELEMENTS, true ) ) {
					$moved = iterator_to_array( $child->childNodes );
					foreach ( $moved as $grandchild ) {
						$container->insertBefore( $grandchild, $child );
					}
					$this->remove( $child );
					foreach ( $moved as $grandchild ) {
						$this->clean_child( $grandchild, $container, $ns );
					}
					return;
				}
				$this->remove( $child );
				return;
			}
			$this->clean_element( $child, $ns );
			return;
		}
		// Text and CDATA are kept (DOMCdataSection extends DOMText). Everything else goes.
		if ( $child instanceof DOMText ) {
			return;
		}
		$this->note( $child );
		$this->remove( $child );
	}

	/**
	 * Clean the attributes of an element.
	 *
	 * @param DOMElement $element Element.
	 */
	private function clean_attributes( DOMElement $element ): void {
		$attributes = array();
		foreach ( $element->attributes as $attribute ) {
			$attributes[] = $attribute;
		}

		foreach ( $attributes as $attribute ) {
			if ( ! $attribute instanceof DOMAttr ) {
				continue;
			}
			$ns    = $attribute->namespaceURI;
			$name  = (string) $attribute->localName;
			$value = (string) $attribute->value;
			$label = ( $attribute->prefix ? $attribute->prefix . ':' : '' ) . $name;
			$keep  = false;

			if ( ( null === $ns && 'href' === $name ) || ( self::XLINK_NS === $ns && 'href' === $name ) ) {
				$keep = $this->safe_href( $value, (string) $element->localName );
			} elseif ( self::XML_NS === $ns ) {
				$keep = in_array( $name, array( 'space', 'lang' ), true ) && $this->safe_value( $value );
			} elseif ( null === $ns && 'style' === $name ) {
				$clean = $this->clean_style_attribute( $value );
				if ( null === $clean ) {
					$keep = true;
				} elseif ( '' !== $clean ) {
					$element->setAttribute( 'style', $clean );
					$this->removed[] = 'style:' . $element->localName;
					continue;
				}
			} elseif ( null === $ns && isset( $this->attributes[ $name ] ) ) {
				$keep = $this->safe_value( $value );
			}

			if ( ! $keep ) {
				$this->removed[] = 'attribute:' . $label;
				$element->removeAttributeNode( $attribute );
			}
		}
	}

	/**
	 * Whether an href is allowed on an element.
	 *
	 * @param string $value   Value.
	 * @param string $element Element local name.
	 */
	private function safe_href( string $value, string $element ): bool {
		$value = trim( $value );
		if ( preg_match( '/^#[A-Za-z_][\w.:-]*$/', $value ) ) {
			return true;
		}

		return 'image' === $element && 1 === preg_match( self::DATA_URL_PATTERN, $value );
	}

	/**
	 * Whether an attribute value is free of script and external references.
	 *
	 * @param string $value Value.
	 */
	private function safe_value( string $value ): bool {
		$flat = self::flatten( $value );
		foreach ( array( 'javascript:', 'vbscript:', 'livescript:', 'data:', 'expression(', '@import', '-moz-binding', 'behavior:', '<', '\\' ) as $needle ) {
			if ( false !== strpos( $flat, $needle ) ) {
				return false;
			}
		}

		return $this->safe_urls( $flat );
	}

	/**
	 * Every `url()` must point to a fragment in the same document.
	 *
	 * @param string $flat Lower-cased value without whitespace.
	 */
	private function safe_urls( string $flat ): bool {
		if ( false === strpos( $flat, 'url(' ) ) {
			return true;
		}
		if ( ! preg_match_all( '/url\(([^)]*)\)?/', $flat, $matches ) ) {
			return false;
		}
		foreach ( $matches[1] as $target ) {
			$target = trim( $target, '"\'' );
			if ( ! preg_match( '/^#[a-z_][\w.:-]*$/', $target ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clean a `style` attribute.
	 *
	 * @param string $css Declarations.
	 * @return string|null Null when the value is clean as it is, otherwise the cleaned value (may be empty).
	 */
	private function clean_style_attribute( string $css ): ?string {
		$css   = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$kept  = array();
		$dirty = false;
		foreach ( explode( ';', $css ) as $declaration ) {
			if ( '' === trim( $declaration ) ) {
				continue;
			}
			$parts = explode( ':', $declaration, 2 );
			$prop  = strtolower( trim( $parts[0] ) );
			$value = isset( $parts[1] ) ? trim( $parts[1] ) : '';
			if ( '' === $value || ! isset( $this->properties[ $prop ] ) || ! $this->safe_value( $value ) ) {
				$dirty = true;
				continue;
			}
			$kept[] = $prop . ':' . $value;
		}
		if ( ! $dirty && false === strpos( $css, '/*' ) ) {
			return null;
		}

		return implode( ';', $kept );
	}

	/**
	 * Whether a `<style>` element is safe. Unsafe stylesheets are removed as a whole.
	 *
	 * @param string $css Stylesheet.
	 */
	private function safe_stylesheet( string $css ): bool {
		$css  = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$flat = self::flatten( $css );
		foreach ( array( 'javascript:', 'vbscript:', 'data:', 'expression(', '@import', '@font-face', '@namespace', '-moz-binding', 'behavior:', '<', '\\', '/*' ) as $needle ) {
			if ( false !== strpos( $flat, $needle ) ) {
				return false;
			}
		}

		return $this->safe_urls( $flat );
	}

	/**
	 * Lower-case a value and strip whitespace and control characters, so that
	 * `java\tscript:` and `JaVaScRiPt :` are caught.
	 *
	 * @param string $value Value.
	 */
	private static function flatten( string $value ): string {
		return strtolower( (string) preg_replace( '/[\x00-\x20\x7f]+/', '', $value ) );
	}

	/**
	 * Record a removed non-element node.
	 *
	 * @param DOMNode $node Node.
	 */
	private function note( DOMNode $node ): void {
		switch ( $node->nodeType ) {
			case XML_COMMENT_NODE:
				$this->removed[] = 'comment';
				break;
			case XML_PI_NODE:
				$this->removed[] = 'processing-instruction:' . $node->nodeName;
				break;
			case XML_DOCUMENT_TYPE_NODE:
				$this->removed[] = 'doctype';
				break;
			case XML_ENTITY_REF_NODE:
				$this->removed[] = 'entity:' . $node->nodeName;
				break;
			default:
				$this->removed[] = 'node:' . $node->nodeName;
		}
	}

	/**
	 * Remove a node from its parent.
	 *
	 * @param DOMNode $node Node.
	 */
	private function remove( DOMNode $node ): void {
		if ( null !== $node->parentNode ) {
			$node->parentNode->removeChild( $node );
		}
	}
}
