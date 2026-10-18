<?php
/**
 * The filters the plugin offers: kit filters (via svgf_filters()) plus
 * uploads, grouped for the dropdown, with tuning controls and tuned copies.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

defined( 'ABSPATH' ) || exit;

final class Registry {

	/** Kit category => dropdown group, in dropdown order. Uploads and unknown categories go to 'custom'. */
	const GROUPS = array( 'color', 'texture', 'distortion', 'shape', 'light', 'fill', 'motion', 'glass', 'custom' );

	/** @var array<string, array{id:string, label:string, group:string, backdrop:bool, controls:array, presets:array, markup:string}> */
	private $entries = array();

	/**
	 * @param array    $filters    svgf_filters()-shaped: id => {title, category, tweaks, presets, markup}.
	 * @param string[] $upload_ids IDs that come from uploads (always in "Custom").
	 */
	public function __construct( array $filters, array $upload_ids = array() ) {
		foreach ( $filters as $id => $filter ) {
			$id       = (string) $id;
			$category = (string) ( $filter['category'] ?? '' );
			if ( in_array( $id, $upload_ids, true ) || ! in_array( $category, self::GROUPS, true ) ) {
				$category = 'custom';
			}

			$controls = array();
			foreach ( (array) ( $filter['tweaks'] ?? array() ) as $tweak ) {
				$control                      = Tweaks::control( (array) $tweak );
				$controls[ $control['key'] ] = $control;
			}
			$controls = Tweaks::link( $controls );

			$title              = trim( (string) ( $filter['title'] ?? '' ) );
			$this->entries[ $id ] = array(
				'id'       => $id,
				'label'    => '' !== $title ? $title : $id,
				'group'    => self::group_label( $category ),
				// Glass refracts what is behind the element: always applied as backdrop-filter.
				'backdrop' => 'glass' === $category,
				'controls' => $controls,
				// Preset values are raw attribute values; they only reach a page through the fields (compose()).
				'presets'  => is_array( $filter['presets'] ?? null ) ? $filter['presets'] : array(),
				'markup'   => (string) ( $filter['markup'] ?? '' ),
			);
		}
	}

	/** From the loaded kit (1.3.0+); an older kit only gives IDs. */
	public static function from_kit( array $upload_ids = array() ): self {
		if ( function_exists( 'svgf_filters' ) ) {
			return new self( svgf_filters(), $upload_ids );
		}
		return self::from_ids( function_exists( 'svgf_collect' ) ? (array) ( svgf_collect()['ids'] ?? array() ) : array() );
	}

	/** IDs only: no titles, no tuning, no copies; without a category the glass filter is left out. */
	public static function from_ids( array $ids ): self {
		$filters = array();
		foreach ( $ids as $id ) {
			if ( 'fx-liquid' !== (string) $id ) {
				$filters[ (string) $id ] = array();
			}
		}
		return new self( $filters );
	}

	public function all(): array {
		return $this->entries;
	}

	public function has( string $id ): bool {
		return isset( $this->entries[ $id ] );
	}

	/** @return array|null */
	public function get( string $id ) {
		return $this->entries[ $id ] ?? null;
	}

	/** @return array<string, array<string, string>> group label => [id => label], groups in dropdown order, empty groups left out. */
	public function grouped(): array {
		$out = array();
		foreach ( self::GROUPS as $category ) {
			$label = self::group_label( $category );
			foreach ( $this->entries as $id => $entry ) {
				if ( $entry['group'] === $label ) {
					$out[ $label ][ $id ] = $entry['label'];
				}
			}
		}
		return $out;
	}

	/**
	 * A copy of a filter with tuned values and a unique ID.
	 *
	 * Unknown keys, invalid values and values equal to the default are ignored.
	 * Without remaining values and without $salt the shared filter applies (null).
	 * $salt forces a per-instance copy (spotlight/melt react per element).
	 * Global colors become a CSS declaration in the primitive's style attribute.
	 *
	 * @param string $id   Filter ID.
	 * @param array  $tune Field values of the filter (svgTune[<filter id>], see Tweaks).
	 * @param string $salt Optional instance discriminator, e.g. the element's order class.
	 * @return array{id:string, markup:string}|null
	 */
	public function copy( string $id, array $tune, string $salt = '' ) {
		$entry = $this->entries[ $id ] ?? null;
		if ( ! $entry || '' === $entry['markup'] ) {
			return null;
		}

		$values = array();
		foreach ( $entry['controls'] as $key => $control ) {
			$composed = Tweaks::compose( $control, $tune );
			if ( null !== $composed ) {
				$values[ $key ] = $composed;
			}
		}
		if ( ! $values && '' === $salt ) {
			return null;
		}
		ksort( $values );

		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $doc->loadXML( $entry['markup'], LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok || 'filter' !== $doc->documentElement->localName ) {
			return null;
		}

		$hash   = substr( md5( (string) json_encode( array( $id, $values, $salt ) ) ), 0, 8 );
		$filter = $doc->documentElement;

		// Same primitive keys as the kit's svgf_filters(): '#id', else tag with ':n' from the second occurrence.
		$primitives = array();
		$seen       = array();
		foreach ( $filter->getElementsByTagName( '*' ) as $el ) {
			$tag          = $el->localName;
			$seen[ $tag ] = ( $seen[ $tag ] ?? 0 ) + 1;
			$primitives[ $el->hasAttribute( 'id' ) ? '#' . $el->getAttribute( 'id' ) : ( 1 === $seen[ $tag ] ? $tag : $tag . ':' . $seen[ $tag ] ) ] = $el;
		}
		foreach ( $values as $key => $composed ) {
			list( $primitive, $attr ) = explode( '|', $key, 2 );
			if ( ! isset( $primitives[ $primitive ] ) ) {
				continue;
			}
			$el = $primitives[ $primitive ];
			if ( null !== $composed['value'] ) {
				$el->setAttribute( $attr, $composed['value'] );
			}
			if ( null !== $composed['style'] ) {
				$style = trim( $el->getAttribute( 'style' ) );
				$el->setAttribute( 'style', ( '' !== $style ? rtrim( $style, ';' ) . ';' : '' ) . $composed['style'] );
			}
		}

		// Inner IDs (e.g. the spotlight's fePointLight) must stay unique on the page.
		foreach ( $filter->getElementsByTagName( '*' ) as $el ) {
			if ( $el->hasAttribute( 'id' ) ) {
				$el->setAttribute( 'id', $el->getAttribute( 'id' ) . '--' . $hash );
			}
		}
		$filter->setAttribute( 'id', $id . '--' . $hash );

		return array(
			'id'     => $id . '--' . $hash,
			'markup' => $doc->saveXML( $filter ),
		);
	}

	private static function group_label( string $category ): string {
		$labels = array(
			'color'      => __( 'Color', 'divi5-svg-filters' ),
			'texture'    => __( 'Texture', 'divi5-svg-filters' ),
			'distortion' => __( 'Blur & distortion', 'divi5-svg-filters' ),
			'shape'      => __( 'Shape', 'divi5-svg-filters' ),
			'light'      => __( 'Light & depth', 'divi5-svg-filters' ),
			'fill'       => __( 'Fill & compositing', 'divi5-svg-filters' ),
			'motion'     => __( 'Motion', 'divi5-svg-filters' ),
			'glass'      => __( 'Glass', 'divi5-svg-filters' ),
			'custom'     => __( 'Custom', 'divi5-svg-filters' ),
		);
		return $labels[ $category ] ?? $labels['custom'];
	}
}
