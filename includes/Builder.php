<?php
/**
 * Visual Builder assets: the builder script (app window, where Divi's React
 * app, the fields hook and the module rendering run) and a small stylesheet
 * for the settings panel (drawn into the top window).
 *
 * window.D5SVGF carries the dropdown groups, each filter's tuning controls
 * and markup (for live tuned copies). Selector templates come from the
 * builder's own module metadata (assets/builder.js).
 *
 * @package D5SVGF
 */

namespace D5SVGF;

use ET\Builder\VisualBuilder\Assets\PackageBuildManager;

defined( 'ABSPATH' ) || exit;

final class Builder {

	/** @var callable(): Registry */
	private $registry_factory;

	/** @var bool */
	private $registered = false;

	public function __construct( callable $registry_factory ) {
		$this->registry_factory = $registry_factory;
	}

	public function register() {
		// Styles are enqueued before scripts; register on whichever fires first.
		add_action( 'divi_visual_builder_assets_before_enqueue_styles', array( $this, 'enqueue' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue() {
		if ( $this->registered || ! method_exists( PackageBuildManager::class, 'register_package_build' ) ) {
			return;
		}
		$this->registered = true;

		wp_register_script( 'd5svgf-core', D5SVGF_URL . 'assets/core.js', array(), D5SVGF_VERSION, true );
		wp_add_inline_script( 'd5svgf-core', 'window.D5SVGF=' . wp_json_encode( $this->data() ) . ';', 'before' );

		PackageBuildManager::register_package_build(
			array(
				'name'    => 'd5svgf-builder',
				'version' => D5SVGF_VERSION,
				'script'  => array(
					'src'                => D5SVGF_URL . 'assets/builder.js',
					// Without field-library and module the script runs before window.divi.fieldLibrary exists.
					'deps'               => array( 'divi-vendor-wp-hooks', 'divi-field-library', 'divi-module', 'd5svgf-core' ),
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				),
				'style'   => array(
					'src'                => D5SVGF_URL . 'assets/builder.css',
					'enqueue_top_window' => true,
					'enqueue_app_window' => false,
				),
			)
		);
	}

	/** Data for window.D5SVGF. */
	public function data(): array {
		$registry = call_user_func( $this->registry_factory );

		$groups = array();
		foreach ( $registry->grouped() as $label => $options ) {
			$groups[] = array( 'label' => $label, 'options' => $options );
		}

		$filters = array();
		foreach ( $registry->all() as $id => $entry ) {
			$filters[ $id ] = array(
				'label'    => $entry['label'],
				'controls' => array_values( $entry['controls'] ),
				'presets'  => (object) $entry['presets'], // object even when empty, for JS
				'markup'   => $entry['markup'],
			);
		}

		return array(
			'groups'      => $groups,
			'filters'     => $filters,
			'interactive' => Frontend::INTERACTIVE,
			'edge'        => Frontend::EDGE_FILTERS,
			'functions'   => Frontend::FILTER_FUNCTIONS,
			'i18n'        => array(
				'svgFilter' => __( 'SVG Filter', 'divi5-svg-filters' ),
				'none'      => __( 'None', 'divi5-svg-filters' ),
				'applyTo'   => __( 'Apply to', 'divi5-svg-filters' ),
				'element'   => __( 'Element', 'divi5-svg-filters' ),
				'bg'        => __( 'Background only', 'divi5-svg-filters' ),
				/* translators: %s: filter name */
				'tune'      => __( 'Tune %s', 'divi5-svg-filters' ),
				'reset'     => __( 'Reset all', 'divi5-svg-filters' ),
				'presets'   => __( 'Presets', 'divi5-svg-filters' ),
				'horiz'     => __( 'horizontal', 'divi5-svg-filters' ),
				'vert'      => __( 'vertical', 'divi5-svg-filters' ),
				'darkest'   => __( 'Darkest level', 'divi5-svg-filters' ),
				'brightest' => __( 'Brightest level', 'divi5-svg-filters' ),
				'levelsDesc' => __( 'Shared by all channels; the levels are spread evenly between darkest and brightest.', 'divi5-svg-filters' ),
				'desc'      => __( 'An SVG filter from the SVG Filter Kit or your uploads. Combined with the filters above in one declaration.', 'divi5-svg-filters' ),
			),
		);
	}
}
