<?php
/**
 * Uploaded SVG files → clean <filter> elements.
 *
 * Every filter is rebuilt in a fresh document from an allow-list: SVG filter
 * primitives and their attributes, <title>/<desc> as plain text, data-*
 * hints, and <animate>/<set> on an allowed attribute of their parent.
 * Everything else (scripts, event handlers, style, foreignObject, images,
 * href/xlink:href, url(…)/javascript: values, unknown elements) is dropped.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	const MAX_BYTES = 524288; // 512 KB

	/** Allowed elements (filter primitives without feImage, which loads external content). */
	const ELEMENTS = array(
		'filter', 'title', 'desc', 'animate', 'set',
		'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix',
		'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood',
		'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR', 'feGaussianBlur', 'feMerge', 'feMergeNode',
		'feMorphology', 'feOffset', 'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile',
		'feTurbulence',
	);

	/** Attributes allowed on filters and primitives (and animatable through <animate>/<set>). */
	const ATTRIBUTES = array(
		'id', 'x', 'y', 'z', 'width', 'height', 'result', 'in', 'in2',
		'filterUnits', 'primitiveUnits', 'color-interpolation-filters', 'color-interpolation',
		'lighting-color', 'flood-color', 'flood-opacity', 'mode', 'type', 'values', 'tableValues',
		'slope', 'intercept', 'amplitude', 'exponent', 'offset', 'operator', 'k1', 'k2', 'k3', 'k4',
		'order', 'kernelMatrix', 'divisor', 'bias', 'targetX', 'targetY', 'edgeMode', 'kernelUnitLength',
		'preserveAlpha', 'surfaceScale', 'diffuseConstant', 'specularConstant', 'specularExponent',
		'scale', 'xChannelSelector', 'yChannelSelector', 'azimuth', 'elevation', 'dx', 'dy',
		'stdDeviation', 'radius', 'pointsAtX', 'pointsAtY', 'pointsAtZ', 'limitingConeAngle',
		'baseFrequency', 'numOctaves', 'seed', 'stitchTiles',
	);

	/** Timing attributes of <animate>/<set> (besides attributeName). */
	const ANIMATION = array(
		'attributeName', 'values', 'from', 'to', 'by', 'dur', 'begin', 'end', 'repeatCount',
		'repeatDur', 'fill', 'calcMode', 'keyTimes', 'keySplines', 'additive', 'accumulate',
		'restart', 'min', 'max',
	);

	/** IDs usable as CSS class (.fx-<id>, .fx-<id>--bg) and url(#id) as they are; '--' is reserved for copies. */
	const ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/';

	/**
	 * @param string   $svg   File contents.
	 * @param string[] $taken IDs already in use (filters and inner primitive IDs).
	 * @return array{filters: array<int, array{id:string, title:string, markup:string}>,
	 *               skipped: array<int, array{id:string, reason:string}>, error:string}
	 */
	public static function filters( string $svg, array $taken ): array {
		$result = array( 'filters' => array(), 'skipped' => array(), 'error' => '' );

		if ( strlen( $svg ) > self::MAX_BYTES ) {
			$result['error'] = __( 'The file is larger than 512 KB.', 'divi5-svg-filters' );
			return $result;
		}
		if ( false !== stripos( $svg, '<!DOCTYPE' ) || false !== stripos( $svg, '<!ENTITY' ) ) {
			$result['error'] = __( 'SVG files with a DOCTYPE or entities are not accepted.', 'divi5-svg-filters' );
			return $result;
		}

		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $doc->loadXML( $svg, LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok ) {
			$result['error'] = __( 'The file is not valid SVG/XML.', 'divi5-svg-filters' );
			return $result;
		}

		$taken = array_fill_keys( $taken, true );
		$found = 0;
		foreach ( $doc->getElementsByTagNameNS( '*', 'filter' ) as $source ) {
			++$found;
			$id     = $source->getAttribute( 'id' );
			$reason = self::check_id( $id, $taken );
			if ( '' === $reason ) {
				$out    = new \DOMDocument();
				$filter = self::rebuild( $source, $out );
				$inner  = array();
				foreach ( $filter->getElementsByTagName( '*' ) as $el ) {
					if ( $el->hasAttribute( 'id' ) ) {
						$inner[] = $el->getAttribute( 'id' );
					}
				}
				$primitives = 0;
				foreach ( $filter->childNodes as $child ) {
					$primitives += ( $child instanceof \DOMElement && 0 === strpos( $child->localName, 'fe' ) ) ? 1 : 0;
				}

				if ( ! $primitives ) {
					$reason = __( 'no filter primitives left after cleaning', 'divi5-svg-filters' );
				}
				foreach ( $inner as $inner_id ) {
					if ( '' === $reason ) {
						$reason = self::check_id( $inner_id, $taken, true );
					}
				}
			}

			if ( '' !== $reason ) {
				$result['skipped'][] = array( 'id' => $id, 'reason' => $reason );
				continue;
			}

			$taken[ $id ] = true;
			foreach ( $inner as $inner_id ) {
				$taken[ $inner_id ] = true;
			}
			$title               = $filter->getElementsByTagName( 'title' )->item( 0 );
			$result['filters'][] = array(
				'id'     => $id,
				'title'  => $title ? trim( $title->textContent ) : '',
				'markup' => $out->saveXML( $filter ),
			);
		}

		if ( ! $found ) {
			$result['error'] = __( 'The file contains no <filter> element.', 'divi5-svg-filters' );
		}
		return $result;
	}

	/** '' if the ID is usable and free, else the reason. */
	private static function check_id( string $id, array $taken, bool $inner = false ): string {
		if ( '' === $id && ! $inner ) {
			return __( 'the filter has no id', 'divi5-svg-filters' );
		}
		if ( ! preg_match( self::ID_PATTERN, $id ) || false !== strpos( $id, '--' ) ) {
			/* translators: %s: element ID */
			return sprintf( __( 'invalid id "%s" (letters, digits, - and _, starting with a letter, no "--")', 'divi5-svg-filters' ), $id );
		}
		if ( isset( $taken[ $id ] ) ) {
			/* translators: %s: element ID */
			return sprintf( __( 'the id "%s" is already used by another filter', 'divi5-svg-filters' ), $id );
		}
		return '';
	}

	/** Allow-list copy of an element into $out (null if the element is not allowed). */
	private static function rebuild( \DOMElement $source, \DOMDocument $out, ?\DOMElement $parent = null ) {
		$name = $source->localName;
		if ( ! in_array( $name, self::ELEMENTS, true ) ) {
			return null;
		}
		$el = $out->createElement( $name );

		if ( in_array( $name, array( 'title', 'desc' ), true ) ) {
			$el->appendChild( $out->createTextNode( trim( $source->textContent ) ) );
			return $el;
		}

		$animation = in_array( $name, array( 'animate', 'set' ), true );
		if ( $animation ) {
			// Only on an attribute the parent primitive may carry anyway.
			$target = $source->getAttribute( 'attributeName' );
			if ( ! $parent || 'filter' === $parent->localName || 'id' === $target || ! in_array( $target, self::ATTRIBUTES, true ) ) {
				return null;
			}
		}

		foreach ( $source->attributes as $attr ) {
			$attr_name = $attr->nodeName; // keeps prefixes (xlink:href) so they never match the allow-list
			$allowed   = $animation ? in_array( $attr_name, self::ANIMATION, true ) : ( in_array( $attr_name, self::ATTRIBUTES, true ) || preg_match( '/^data-[a-z][\w.:-]*$/i', $attr_name ) );
			$value     = $attr->value;
			if ( $allowed && ! preg_match( '/url\s*\(|javascript\s*:|[<>]/i', $value ) ) {
				$el->setAttribute( $attr_name, $value );
			}
		}

		foreach ( $source->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$copy = self::rebuild( $child, $out, $el );
				if ( $copy ) {
					$el->appendChild( $copy );
				}
			}
		}
		return $el;
	}
}
