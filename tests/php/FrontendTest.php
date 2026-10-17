<?php
/**
 * Frontend: combined rules per module, presets, copies, background scope, guiding rule.
 */

use D5SVGF\Frontend;
use D5SVGF\Registry;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/divi-fakes.php';
require_once __DIR__ . '/../../includes/Tweaks.php';
require_once __DIR__ . '/../../includes/Registry.php';
require_once __DIR__ . '/../../includes/Frontend.php';

final class FrontendTest extends TestCase {

	/** @var Frontend */
	private $frontend;

	protected function setUp(): void {
		$GLOBALS['wp_hooks']           = array();
		$GLOBALS['wp_options']         = array();
		$GLOBALS['fake_module_preset'] = null;
		$GLOBALS['fake_group_presets'] = array();
		$GLOBALS['fake_throw']         = false;
		$GLOBALS['fake_blocks']        = array(
			'divi/text'    => array(
				'module'  => array( 'selector' => '{{selector}}' ),
				'content' => array( 'selector' => '{{selector}} .et_pb_text_inner' ),
			),
			'divi/section' => array( 'module' => array( 'selector' => '{{selector}}' ) ),
		);

		$registry = new Registry( array(
			'fx-duotone'   => array(
				'title'    => 'Duotone',
				'category' => 'color',
				'markup'   => '<filter id="fx-duotone"><feFuncR tableValues="0.1 0.9"/></filter>',
				'tweaks'   => array( array( 'key' => 'feFuncR|tableValues', 'attr' => 'tableValues', 'default' => '0.1 0.9', 'hint' => 'Red' ) ),
			),
			'fx-wave'      => array( 'title' => 'Wave', 'category' => 'distortion', 'markup' => '<filter id="fx-wave"/>' ),
			'fx-spotlight' => array(
				'title'    => 'Pointer light',
				'category' => 'motion',
				'markup'   => '<filter id="fx-spotlight"><fePointLight id="fx-spot-light" z="70"/></filter>',
			),
		) );
		$this->frontend = new Frontend( static function () use ( $registry ) { return $registry; } );
		$this->frontend->register();
	}

	/** Render one module through the hook, as Divi does. */
	private function render( string $id, array $attrs, ?object $elements = null ): string {
		$name = 'divi/' . preg_replace( '/-\d+$/', '', substr( $id, 5 ) );
		return apply_filters( 'divi_module_wrapper_render', '<div class="m">x</div>', array(
			'id'            => $id,
			'name'          => $name,
			'attrs'         => $attrs,
			'elements'      => $elements,
			'storeInstance' => 0,
		) );
	}

	/** wp_footer as the kit does it: defs (with svgf_extra_defs) at priority 1, then ours. */
	private function footer( bool $kit_prints_defs = true ): array {
		$defs = $kit_prints_defs ? apply_filters( 'svgf_extra_defs', '' ) : '';
		ob_start();
		do_action( 'wp_footer' );
		return array( 'defs' => $defs, 'out' => (string) ob_get_clean() );
	}

	private static function filters( array $desktop, array $more = array() ): array {
		return array( 'module' => array( 'decoration' => array( 'filters' => array( 'desktop' => array( 'value' => $desktop ) + $more ) + array() ) ) );
	}

	public function test_combined_declaration_for_every_divi_variant(): void {
		$attrs = array( 'module' => array( 'decoration' => array( 'filters' => array(
			'desktop' => array( 'value' => array( 'saturate' => '150%', 'svgFilter' => 'fx-duotone' ), 'hover' => array( 'brightness' => '120%' ) ),
			'tablet'  => array( 'value' => array( 'saturate' => '50%' ) ),
		) ) ) );

		$this->assertSame( '<div class="m">x</div>', $this->render( 'divi/text-0', $attrs ) );
		$out = $this->footer()['out'];

		$this->assertStringContainsString( '.et_pb_text_0.et_pb_text_0{filter:url(#fx-duotone) saturate(150%);}', $out );
		$this->assertStringContainsString( '.et_pb_text_0.et_pb_text_0:hover{filter:url(#fx-duotone) saturate(150%) brightness(120%);}', $out );
		$this->assertStringContainsString( '@media(tablet){.et_pb_text_0.et_pb_text_0{filter:url(#fx-duotone) saturate(50%);}}', $out );
	}

	public function test_svg_filter_alone_and_none(): void {
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-wave' ) ) );
		$this->render( 'divi/text-1', self::filters( array( 'svgFilter' => 'none', 'blur' => '2px' ) ) );
		$this->render( 'divi/text-2', self::filters( array( 'blur' => '2px' ) ) );
		$out = $this->footer()['out'];

		$this->assertStringContainsString( '.et_pb_text_0.et_pb_text_0{filter:url(#fx-wave);}', $out );
		$this->assertStringNotContainsString( 'et_pb_text_1', $out );
		$this->assertStringNotContainsString( 'et_pb_text_2', $out );
	}

	public function test_tuned_copy_goes_into_the_defs_and_the_rule_uses_it(): void {
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-duotone', 'svgTune' => array( 'fx-duotone' => array( 'feFuncR|tableValues' => '0.3 0.7' ), 'fx-wave' => array( 'x|y' => '1' ) ) ) ) );
		$footer = $this->footer();

		$this->assertMatchesRegularExpression( '/<filter id="(fx-duotone--[0-9a-f]{8})"><feFuncR tableValues="0.3 0.7"\/><\/filter>/', $footer['defs'] );
		preg_match( '/id="(fx-duotone--[0-9a-f]{8})"/', $footer['defs'], $m );
		$this->assertStringContainsString( '{filter:url(#' . $m[1] . ');}', $footer['out'] );
	}

	public function test_tuning_of_another_filter_does_not_carry_over(): void {
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-duotone', 'svgTune' => array( 'fx-blur' => array( 'feFuncR|tableValues' => '0.3 0.7' ) ) ) ) );
		$footer = $this->footer();

		$this->assertSame( '', $footer['defs'] );
		$this->assertStringContainsString( '{filter:url(#fx-duotone);}', $footer['out'] );
	}

	public function test_copies_are_not_referenced_when_the_defs_never_printed(): void {
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-duotone', 'svgTune' => array( 'fx-duotone' => array( 'feFuncR|tableValues' => '0.3 0.7' ), 'fx-wave' => array( 'x|y' => '1' ) ) ) ) );
		$this->render( 'divi/text-1', self::filters( array( 'svgFilter' => 'fx-wave' ) ) );
		$out = $this->footer( false )['out'];

		$this->assertStringNotContainsString( 'fx-duotone--', $out );
		$this->assertStringContainsString( 'url(#fx-wave)', $out, 'kit filters are printed by the kit itself' );
	}

	public function test_modules_rendered_after_the_defs_are_left_alone(): void {
		apply_filters( 'svgf_extra_defs', '' );
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-wave' ) ) );
		ob_start();
		do_action( 'wp_footer' );

		$this->assertStringNotContainsString( 'et_pb_text_0', (string) ob_get_clean() );
	}

	public function test_missing_filter_applies_nothing_and_warns(): void {
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-deleted', 'blur' => '2px' ) ) );
		$this->render( 'divi/text-1', self::filters( array( 'svgFilter' => '"></style><script>x</script>' ) ) );
		$out = $this->footer()['out'];

		$this->assertStringNotContainsString( 'url(#', $out );
		$this->assertStringContainsString( 'console.warn(', $out );
		$this->assertStringContainsString( 'fx-deleted', $out );
		$this->assertStringNotContainsString( '</script><script>', $out, 'IDs are JSON-escaped' );
		$this->assertArrayHasKey( 'fx-deleted', $GLOBALS['wp_options']['d5svgf_missing'] );
	}

	public function test_background_scope_filters_a_background_copy_only(): void {
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-wave', 'svgScope' => 'background', 'blur' => '2px' ) ) );
		$out = $this->footer()['out'];

		$s = '.et_pb_text_0.et_pb_text_0';
		$this->assertStringContainsString( "{$s}::before{content:'';position:absolute;inset:-5%;z-index:-1;background:inherit;filter:url(#fx-wave);pointer-events:none}", $out, 'distortion filters get the enlarged copy' );
		$this->assertStringContainsString( "{$s} .et_parallax_bg,{$s} .et-pb-parallax-background{filter:url(#fx-wave)}", $out );
		$this->assertStringNotContainsString( 'blur(2px)', $out, "Divi's own filter stays Divi's" );
	}

	public function test_sub_element_uses_the_block_selector(): void {
		$this->render( 'divi/text-0', array( 'content' => array( 'decoration' => array( 'filters' => array( 'desktop' => array( 'value' => array( 'svgFilter' => 'fx-wave' ) ) ) ) ) ) );

		$this->assertStringContainsString( '.et_pb_text_0.et_pb_text_0 .et_pb_text_inner{filter:url(#fx-wave);}', $this->footer()['out'] );
	}

	public function test_divi_selector_prefix_is_kept(): void {
		$elements = (object) array( 'order_class' => '.et-db #et-boc .et-l .et_pb_text_0', 'base_order_class' => '.et_pb_text_0' );
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-wave' ) ), $elements );

		$this->assertStringContainsString( '.et-db #et-boc .et-l .et_pb_text_0.et_pb_text_0{filter:url(#fx-wave);}', $this->footer()['out'] );
	}

	public function test_interactive_filters_get_a_copy_per_element_and_the_data_attribute(): void {
		$a = $this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-spotlight' ) ) );
		$b = $this->render( 'divi/text-1', self::filters( array( 'svgFilter' => 'fx-spotlight' ) ) );
		$this->footer();

		preg_match( '/data-svgf-spotlight="(fx-spotlight--[0-9a-f]{8})"/', $a, $ma );
		preg_match( '/data-svgf-spotlight="(fx-spotlight--[0-9a-f]{8})"/', $b, $mb );
		$this->assertNotEmpty( $ma );
		$this->assertNotEmpty( $mb );
		$this->assertNotSame( $ma[1], $mb[1] );
		$this->assertStringStartsWith( '<div data-svgf-spotlight=', $a );
	}

	public function test_presets_get_their_own_rules_once_and_before_the_module(): void {
		$GLOBALS['fake_module_preset'] = array(
			'class' => 'preset--module--divi-text--p1',
			'attrs' => self::filters( array( 'svgFilter' => 'fx-wave', 'sepia' => '40%' ) ),
		);
		$GLOBALS['fake_group_presets'] = array(
			array( 'class' => 'preset--group--filters--g1', 'attrs' => self::filters( array( 'svgFilter' => 'fx-duotone' ) ) ),
		);
		$this->render( 'divi/text-0', array() );
		$this->render( 'divi/text-1', self::filters( array( 'svgFilter' => 'fx-duotone', 'blur' => '3px' ) ) );
		$out = $this->footer()['out'];

		$preset = '.preset--module--divi-text--p1.preset--module--divi-text--p1{filter:url(#fx-wave) sepia(40%);}';
		$this->assertSame( 1, substr_count( $out, $preset ), 'one rule per preset class' );
		$this->assertLessThan( strpos( $out, 'preset--group--filters--g1' ), strpos( $out, $preset ), 'module preset before explicit group preset' );
		$this->assertLessThan( strpos( $out, '.et_pb_text_1.et_pb_text_1' ), strpos( $out, 'preset--group--filters--g1' ), 'presets before the module' );
	}

	public function test_a_presets_svg_filter_stays_when_the_module_sets_own_divi_values(): void {
		// Decision (Markus, 2026-10-17): the module's own Divi values replace the preset's Divi values
		// (as in Divi), but the preset's SVG filter is kept.
		$GLOBALS['fake_module_preset'] = array(
			'class' => 'preset--module--divi-text--p1',
			'attrs' => self::filters( array( 'svgFilter' => 'fx-wave', 'sepia' => '40%' ) ),
		);
		$this->render( 'divi/text-0', array() );
		$this->render( 'divi/text-1', self::filters( array( 'blur' => '3px' ) ) );
		$out = $this->footer()['out'];

		$this->assertStringNotContainsString( '.et_pb_text_0.et_pb_text_0', $out, 'no own values: the preset rule applies' );
		$this->assertStringContainsString( '.et_pb_text_1.et_pb_text_1{filter:url(#fx-wave) blur(3px);}', $out );
		$this->assertSame( 1, substr_count( $out, 'preset--module--divi-text--p1.preset--module--divi-text--p1{' ), 'shared preset rule printed once' );
		$this->assertLessThan( strpos( $out, '.et_pb_text_1.et_pb_text_1' ), strpos( $out, 'preset--module--divi-text--p1' ) );
	}

	public function test_a_later_preset_layer_keeps_the_earlier_svg_filter_with_its_own_values(): void {
		$GLOBALS['fake_module_preset'] = array(
			'class' => 'preset--module--divi-text--p1',
			'attrs' => self::filters( array( 'svgFilter' => 'fx-wave' ) ),
		);
		$GLOBALS['fake_group_presets'] = array(
			array( 'class' => 'preset--group--filters--g2', 'attrs' => self::filters( array( 'contrast' => '130%' ) ) ),
		);
		$this->render( 'divi/text-0', array() );
		$out = $this->footer()['out'];

		$this->assertStringContainsString( '.preset--group--filters--g2.preset--group--filters--g2{filter:url(#fx-wave) contrast(130%);}', $out );
		$this->assertLessThan( strpos( $out, 'preset--group--filters--g2' ), strpos( $out, 'preset--module--divi-text--p1' ) );
	}

	public function test_none_switches_an_inherited_svg_filter_off(): void {
		$GLOBALS['fake_module_preset'] = array(
			'class' => 'preset--module--divi-text--p1',
			'attrs' => self::filters( array( 'svgFilter' => 'fx-wave', 'sepia' => '40%' ) ),
		);
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'none', 'blur' => '3px' ) ) );
		$this->render( 'divi/text-1', self::filters( array( 'svgFilter' => 'none' ) ) );
		$out = $this->footer()['out'];

		$this->assertStringContainsString( '.et_pb_text_0.et_pb_text_0{filter:blur(3px);}', $out, 'own Divi values only' );
		$this->assertStringContainsString( '.et_pb_text_1.et_pb_text_1{filter:sepia(40%);}', $out, "the preset's Divi values without the SVG filter" );
	}

	public function test_none_with_nothing_left_prints_filter_none(): void {
		$GLOBALS['fake_module_preset'] = array(
			'class' => 'preset--module--divi-text--p1',
			'attrs' => self::filters( array( 'svgFilter' => 'fx-duotone' ) ),
		);
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'none' ) ) );

		$this->assertStringContainsString( '.et_pb_text_0.et_pb_text_0{filter:none}', $this->footer()['out'] );
	}

	public function test_the_modules_own_svg_filter_replaces_the_presets(): void {
		$GLOBALS['fake_module_preset'] = array(
			'class' => 'preset--module--divi-text--p1',
			'attrs' => self::filters( array( 'svgFilter' => 'fx-wave', 'sepia' => '40%' ) ),
		);
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-duotone' ) ) );

		$this->assertStringContainsString( '.et_pb_text_0.et_pb_text_0{filter:url(#fx-duotone);}', $this->footer()['out'] );
	}

	public function test_background_scope_never_overrides_divis_positioning(): void {
		$this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-wave', 'svgScope' => 'background' ) ) );

		$this->assertStringContainsString( ':where(.et_pb_text_0.et_pb_text_0){position:relative;isolation:isolate;overflow:hidden}', $this->footer()['out'] );
	}

	public function test_a_divi_failure_leaves_the_module_untouched(): void {
		$GLOBALS['fake_throw'] = true;

		$this->assertSame( '<div class="m">x</div>', $this->render( 'divi/text-0', self::filters( array( 'svgFilter' => 'fx-wave' ) ) ) );
		$this->assertStringNotContainsString( 'url(#', $this->footer()['out'] );
	}

	public function test_unknown_module_type_is_skipped(): void {
		$this->render( 'divi/unknown-0', self::filters( array( 'svgFilter' => 'fx-wave' ) ) );

		$this->assertStringNotContainsString( 'url(#', $this->footer()['out'] );
	}
}
