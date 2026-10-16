<?php
/**
 * Frontend: one combined filter declaration per element, breakpoint and state.
 *
 * For every module Divi renders (divi_module_wrapper_render), each Filters
 * attribute with an svgFilter gets rules built by Divi's own
 * Utils::style_statements(), so breakpoints, hover and sticky follow Divi
 * exactly; the declaration is "url(#id) <Divi's filter functions>". Presets
 * get rules for their own class, in Divi's cascade order. Tuned and
 * per-element copies go into the kit's defs (svgf_extra_defs). Everything is
 * printed in wp_footer after the defs.
 *
 * Guiding rule: unknown filters, missing Divi pieces or any error leave the
 * module untouched; rules for copies only print if the defs included them.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Filters\Filters;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	/** Copy of the map in Divi's Filters::style_declaration() (5.15.1). */
	const FILTER_FUNCTIONS = array(
		'hueRotate'  => 'hue-rotate',
		'saturate'   => 'saturate',
		'brightness' => 'brightness',
		'contrast'   => 'contrast',
		'invert'     => 'invert',
		'sepia'      => 'sepia',
		'opacity'    => 'opacity',
		'blur'       => 'blur',
	);

	/** Filters whose kit JS reacts to the pointer: one copy per element (spec goal 10). */
	const INTERACTIVE = array( 'fx-spotlight' => 'spotlight', 'fx-melt' => 'melt' );

	/** Same list as the kit: their background copy is enlarged so edges stay covered. */
	const EDGE_FILTERS = array( 'fx-wave', 'fx-wave-anim', 'fx-glitch', 'fx-melt', 'fx-rough', 'fx-blur' );

	/** Divi's style groups in print order. */
	const GROUPS = array( 'presetNested', 'preset', 'presetGroup', 'module' );

	/** @var callable(): Registry */
	private $registry_factory;

	/** @var Registry|null */
	private $registry;

	/** @var array<int, array{group:string, copy:bool, css:string}> */
	private $chunks = array();

	/** @var array<string, string> copy ID => markup */
	private $defs = array();

	/** @var array<string, true> */
	private $missing = array();

	/** @var array<string, array<string, true>> preset class => elements that got a raised-specificity filter rule */
	private $raised = array();

	/** @var array<string, array<string, string>> module name => element => selector template */
	private $templates = array();

	/** @var bool */
	private $defs_printed = false;

	/** @param callable $registry_factory Returns the Registry; called on first use. */
	public function __construct( callable $registry_factory ) {
		$this->registry_factory = $registry_factory;
	}

	/** Whether the running Divi has everything this class calls. */
	public static function supported(): bool {
		return method_exists( Utils::class, 'style_statements' )
			&& method_exists( ModuleUtils::class, 'use_attr_value' )
			&& method_exists( ModuleUtils::class, 'get_module_order_class_name' )
			&& method_exists( Filters::class, 'value' )
			&& class_exists( 'WP_Block_Type_Registry' );
	}

	public function register() {
		add_filter( 'divi_module_wrapper_render', array( $this, 'wrapper' ), 10, 2 );
		add_filter( 'svgf_extra_defs', array( $this, 'defs' ) );
		add_action( 'wp_footer', array( $this, 'footer' ), 2 );
	}

	/**
	 * divi_module_wrapper_render: collect rules, mark interactive elements.
	 *
	 * @param mixed $html Rendered module.
	 * @param mixed $args Divi's module render args.
	 * @return mixed
	 */
	public function wrapper( $html, $args ) {
		if ( $this->defs_printed || ! is_string( $html ) || ! is_array( $args ) ) {
			return $html; // after the defs, new copies could not be printed any more
		}

		$chunks  = $this->chunks;
		$defs    = $this->defs;
		$missing = $this->missing;
		$raised  = $this->raised;
		try {
			return $this->process( $html, $args );
		} catch ( \Throwable $e ) {
			// Roll back anything this module added; the page renders as without the plugin.
			$this->chunks  = $chunks;
			$this->defs    = $defs;
			$this->missing = $missing;
			$this->raised  = $raised;
			return $html;
		}
	}

	/** svgf_extra_defs: append the copies; from now on no new ones. */
	public function defs( $markup ) {
		$this->defs_printed = true;
		return (string) $markup . implode( '', $this->defs );
	}

	/** wp_footer (after the kit's defs at priority 1). */
	public function footer() {
		$css = '';
		foreach ( self::GROUPS as $group ) {
			foreach ( $this->chunks as $chunk ) {
				if ( $chunk['group'] === $group && ( ! $chunk['copy'] || $this->defs_printed ) ) {
					$css .= $chunk['css'];
				}
			}
		}
		if ( '' !== $css ) {
			echo "\n<style id=\"d5svgf-css\">" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- built from validated IDs and Divi's own declarations
		}

		if ( $this->missing ) {
			$ids = array_keys( $this->missing );
			echo '<script>console.warn(' . wp_json_encode( 'SVG Filters for Divi 5: filter not found, not applied: ' . implode( ', ', $ids ) ) . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			$this->record_missing( $ids );
		}
	}

	private function process( string $html, array $args ): string {
		$name      = (string) ( $args['name'] ?? '' );
		$attrs     = is_array( $args['attrs'] ?? null ) ? $args['attrs'] : array();
		$templates = $this->templates( $name );
		if ( ! $templates ) {
			return $html;
		}

		$order = (string) ModuleUtils::get_module_order_class_name( (string) ( $args['id'] ?? '' ), $args['storeInstance'] ?? null );
		if ( ! self::valid_class( $order ) ) {
			return $html;
		}
		$prefix = self::prefix( $args['elements'] ?? null );

		// Divi's layers for this module, in cascade order. Our rules use a doubled class (so they beat
		// Divi's equal rules even if Divi's CSS loads later). Once a layer has such a raised rule for an
		// element, every later layer with Divi filter values is restated raised too, so "later layer
		// wins" still holds exactly as in Divi.
		$covered     = array();
		$interactive = array();
		foreach ( $this->layers( $name, $attrs, $order ) as $layer ) {
			list( $group, $class, $layer_attrs ) = $layer;
			$shared                              = 'module' !== $group;
			foreach ( $templates as $element => $template ) {
				if ( $shared && isset( $this->raised[ $class ][ $element ] ) ) {
					$covered[ $element ] = true; // done for an earlier module with this preset
					continue;
				}
				$selector = $prefix . str_replace( '{{selector}}', '.' . $class . '.' . $class, $template );
				$kind     = '';
				if ( $this->element( $group, $layer_attrs[ $element ]['decoration']['filters'] ?? null, $selector, $class, isset( $covered[ $element ] ), $kind ) ) {
					$covered[ $element ] = true;
					if ( $shared ) {
						$this->raised[ $class ][ $element ] = true;
					}
				}
				if ( '' !== $kind && ! $shared && 'module' === $element ) {
					$interactive = $kind;
				}
			}
		}

		if ( $interactive ) {
			list( $kind, $filter_id ) = $interactive;
			$html                     = (string) preg_replace( '/^(\s*<[a-zA-Z][\w-]*)/', '$1 data-svgf-' . $kind . '="' . $filter_id . '"', $html, 1 );
		}
		return $html;
	}

	/**
	 * Rules for one Filters attribute of one layer.
	 *
	 * @param string     $group    Divi style group.
	 * @param mixed      $filters  The Filters attribute (breakpoint => state => value).
	 * @param string     $selector Raised selector for the element.
	 * @param string     $class    Layer class (order class or preset class), salt for per-element copies.
	 * @param bool       $covered  An earlier layer already has a raised filter rule for this element.
	 * @param string|array $kind   Out: [kind, filter ID] for interactive filters.
	 * @return bool Whether a raised `filter` rule was added for the element.
	 */
	private function element( string $group, $filters, string $selector, string $class, bool $covered, &$kind ): bool {
		if ( ! is_array( $filters ) ) {
			return false;
		}
		$value = $filters['desktop']['value'] ?? null;
		$svg   = is_array( $value ) && is_string( $value['svgFilter'] ?? null ) ? $value['svgFilter'] : '';

		if ( '' !== $svg && 'none' !== $svg && ! $this->registry()->has( $svg ) ) {
			$this->missing[ $svg ] = true;
			$svg                   = '';
		}

		// No SVG filter here: only restate Divi's own values when an earlier raised rule would beat them.
		$divi_only = $covered ? self::element_rules( $filters, $selector, '' ) : '';
		if ( '' === $svg || 'none' === $svg ) {
			if ( '' === $divi_only ) {
				return false;
			}
			$this->chunks[] = array( 'group' => $group, 'copy' => false, 'css' => $divi_only );
			return true;
		}

		$interactive = self::INTERACTIVE[ $svg ] ?? '';
		$copy        = $this->registry()->copy( $svg, is_array( $value['svgTune'] ?? null ) ? $value['svgTune'] : array(), '' !== $interactive ? $class : '' );
		$filter_id   = $copy ? $copy['id'] : $svg;
		if ( ! self::valid_class( $filter_id ) ) {
			return false;
		}
		if ( $copy ) {
			$this->defs[ $filter_id ] = $copy['markup'];
		}
		if ( '' !== $interactive ) {
			$kind = array( $interactive, $filter_id );
		}

		if ( 'background' === ( $value['svgScope'] ?? 'element' ) ) {
			// Divi's own filter stays on the element; restated only if an earlier raised rule would beat it.
			$this->chunks[] = array( 'group' => $group, 'copy' => (bool) $copy, 'css' => self::background_rules( $selector, $filter_id, $svg ) . $divi_only );
			return '' !== $divi_only;
		}

		$this->chunks[] = array( 'group' => $group, 'copy' => (bool) $copy, 'css' => self::element_rules( $filters, $selector, $filter_id ) );
		return true;
	}

	/**
	 * Divi's rules for a Filters attribute; with a filter ID, url(#id) goes in front of Divi's functions.
	 * Without one (and without Divi values) nothing is printed, as Divi skips empty declarations.
	 */
	private static function element_rules( array $filters, string $selector, string $filter_id ): string {
		return (string) Utils::style_statements(
			array(
				'selectors'           => array( 'desktop' => array( 'value' => $selector ) ),
				'attr'                => $filters,
				'returnType'          => 'string',
				'declarationFunction' => static function ( $params ) use ( $filter_id ) {
					$resolved = ModuleUtils::use_attr_value(
						array(
							'attr'         => $params['attr'] ?? array(),
							'breakpoint'   => $params['breakpoint'] ?? 'desktop',
							'state'        => $params['state'] ?? 'value',
							'mode'         => 'getAndInheritAll',
							'defaultValue' => $params['attrValue'] ?? array(),
						)
					);
					$divi     = Filters::value( is_array( $resolved ) ? $resolved : array(), self::FILTER_FUNCTIONS );
					$value    = trim( ( '' !== $filter_id ? 'url(#' . $filter_id . ') ' : '' ) . $divi );
					return '' !== $value ? 'filter:' . $value . ';' : '';
				},
			)
		);
	}

	/**
	 * The kit's --bg mechanics for one selector: a filtered copy of the background behind the content.
	 * The positioning rule has zero specificity (:where) so Divi's own Position settings always win.
	 */
	public static function background_rules( string $s, string $filter_id, string $base_id ): string {
		$inset = in_array( $base_id, self::EDGE_FILTERS, true ) ? '-5%' : '0';
		return ":where({$s}){position:relative;isolation:isolate;overflow:hidden}"
			. "{$s}::before{content:'';position:absolute;inset:{$inset};z-index:-1;background:inherit;filter:url(#{$filter_id});pointer-events:none}"
			. "{$s} .et_parallax_bg,{$s} .et-pb-parallax-background{filter:url(#{$filter_id})}";
	}

	/**
	 * The module's layers in Divi's cascade order: nested group presets, module preset,
	 * explicit group presets, the module itself.
	 *
	 * @return array<int, array{0:string, 1:string, 2:array}> [group, class, attrs]
	 */
	private function layers( string $name, array $attrs, string $order ): array {
		$all   = $this->presets( $name, $attrs );
		$all[] = array( 'module', $order, $attrs );
		$out   = array();
		foreach ( self::GROUPS as $group ) { // stable on PHP 7.4, unlike usort
			foreach ( $all as $layer ) {
				if ( $layer[0] === $group && self::valid_class( $layer[1] ) ) {
					$out[] = $layer;
				}
			}
		}
		return $out;
	}

	/**
	 * Presets selected for the module, with their Divi style group.
	 *
	 * @return array<int, array{0:string, 1:string, 2:array}> [group, class, attrs]
	 */
	private function presets( string $name, array $attrs ): array {
		if ( ! method_exists( GlobalPreset::class, 'get_selected_preset' ) ) {
			return array();
		}
		$args = array( 'moduleName' => $name, 'moduleAttrs' => $attrs );
		$out  = array();

		$module = GlobalPreset::get_selected_preset( $args );
		if ( $module->is_exist() && $module->has_data_attrs() ) {
			$preset_attrs = $module->get_data_attrs();
			if ( $module->has_data_style_attrs() && method_exists( ModuleUtils::class, 'module_preset_merges_style_attrs' ) && ModuleUtils::module_preset_merges_style_attrs( $name ) ) {
				$preset_attrs = array_replace_recursive( $preset_attrs, $module->get_data_style_attrs() );
			}
			$out[] = array( 'preset', $module->get_selector_class_name(), $preset_attrs );
		}

		if ( method_exists( GlobalPreset::class, 'get_selected_group_presets' ) ) {
			foreach ( GlobalPreset::get_selected_group_presets( $args ) as $item ) {
				if ( is_object( $item ) && $item->is_exist() && $item->has_data_attrs() ) {
					$nested = method_exists( $item, 'is_nested' ) && $item->is_nested();
					$out[]  = array( $nested ? 'presetNested' : 'presetGroup', $item->get_selector_class_name(), $item->get_data_attrs() );
				}
			}
		}
		return $out;
	}

	/** element => selector template, from the module's block registration (module.json). */
	private function templates( string $name ): array {
		if ( ! isset( $this->templates[ $name ] ) ) {
			$templates = array();
			$block     = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
			foreach ( (array) ( $block->attributes ?? array() ) as $element => $attribute ) {
				$selector = $attribute['selector'] ?? null;
				// Only plain single selectors: '{{selector}}' plus an optional descendant part.
				if ( is_string( $selector ) && 0 === strpos( $selector, '{{selector}}' ) && false === strpos( $selector, ',' ) ) {
					$templates[ (string) $element ] = $selector;
				}
			}
			$this->templates[ $name ] = $templates;
		}
		return $this->templates[ $name ];
	}

	/** The prefix Divi put in front of this module's order class ('.et-db #et-boc .et-l ' in some contexts). */
	private static function prefix( $elements ): string {
		$order = is_object( $elements ) && isset( $elements->order_class ) ? (string) $elements->order_class : '';
		$base  = is_object( $elements ) && isset( $elements->base_order_class ) ? (string) $elements->base_order_class : '';
		if ( '' === $base || strlen( $order ) <= strlen( $base ) || substr( $order, -strlen( $base ) ) !== $base ) {
			return '';
		}
		$prefix = substr( $order, 0, -strlen( $base ) );
		return preg_match( '/^[\w\s.#-]+$/', $prefix ) ? $prefix : '';
	}

	/** IDs and classes that are safe in CSS selectors, url(#…) and attributes as they are. */
	private static function valid_class( string $name ): bool {
		return (bool) preg_match( '/^[A-Za-z][\w-]*$/', $name );
	}

	private function registry(): Registry {
		if ( null === $this->registry ) {
			$this->registry = call_user_func( $this->registry_factory );
		}
		return $this->registry;
	}

	/** Remember missing filters for the admin page (writes only when something new appears). */
	private function record_missing( array $ids ) {
		$list    = get_option( 'd5svgf_missing', array() );
		$list    = is_array( $list ) ? $list : array();
		$post_id = (int) get_queried_object_id();
		$changed = false;
		foreach ( $ids as $id ) {
			if ( ! isset( $list[ $id ][ $post_id ] ) ) {
				$list[ $id ][ $post_id ] = time();
				$changed                 = true;
			}
		}
		if ( $changed ) {
			update_option( 'd5svgf_missing', $list, false );
		}
	}
}
