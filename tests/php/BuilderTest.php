<?php
/**
 * Builder assets: script registration (app window only) and the data handed to JS.
 */

use D5SVGF\Builder;
use D5SVGF\Registry;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/divi-fakes.php';
require_once __DIR__ . '/../../includes/Tweaks.php';
require_once __DIR__ . '/../../includes/Registry.php';
require_once __DIR__ . '/../../includes/Frontend.php';
require_once __DIR__ . '/../../includes/Builder.php';

final class BuilderTest extends TestCase {

	protected function setUp(): void {
		if ( ! defined( 'D5SVGF_URL' ) ) {
			define( 'D5SVGF_URL', 'https://example.test/wp-content/plugins/divi5-svg-filters/' );
			define( 'D5SVGF_VERSION', '0.1.0' );
		}
		$GLOBALS['wp_hooks']            = array();
		$GLOBALS['fake_package_builds'] = array();
		$GLOBALS['wp_inline_scripts']   = array();
		$GLOBALS['fake_blocks']         = array(
			'divi/text' => array(
				'module'  => array( 'selector' => '{{selector}}' ),
				'content' => array( 'selector' => '{{selector}} .et_pb_text_inner' ),
				'title'   => array( 'selector' => array( 'desktop' => '{{selector}} h1' ) ),
			),
			'core/paragraph' => array( 'content' => array( 'selector' => 'p' ) ),
		);
	}

	private function registry(): Registry {
		return new Registry( array(
			'fx-duotone' => array(
				'title'    => 'Duotone',
				'category' => 'color',
				'markup'   => '<filter id="fx-duotone"/>',
				'tweaks'   => array( array( 'key' => 'feFuncR|tableValues', 'attr' => 'tableValues', 'default' => '0.1 0.9', 'hint' => 'Red' ) ),
			),
			'fx-wave'    => array( 'title' => 'Wave', 'category' => 'distortion', 'markup' => '<filter id="fx-wave"/>' ),
		) );
	}

	public function test_registers_once_for_the_app_window_with_a_top_window_style(): void {
		$builder = new Builder( function () { return $this->registry(); } );
		$builder->register();
		do_action( 'divi_visual_builder_assets_before_enqueue_styles' );
		do_action( 'divi_visual_builder_assets_before_enqueue_scripts' );

		$this->assertCount( 1, $GLOBALS['fake_package_builds'], 'registered once although both hooks fired' );
		$build = $GLOBALS['fake_package_builds'][0];
		$this->assertFalse( $build['script']['enqueue_top_window'] );
		$this->assertTrue( $build['script']['enqueue_app_window'] );
		$this->assertSame( array( 'divi-vendor-wp-hooks', 'divi-field-library', 'divi-module', 'd5svgf-core' ), $build['script']['deps'] );
		$this->assertTrue( $build['style']['enqueue_top_window'] );
		$this->assertFalse( $build['style']['enqueue_app_window'] );
		$this->assertSame( 'before', $GLOBALS['wp_inline_scripts']['d5svgf-core'][0][0] );
		$this->assertStringStartsWith( 'window.D5SVGF=', $GLOBALS['wp_inline_scripts']['d5svgf-core'][0][1] );
	}

	public function test_data_for_the_builder(): void {
		$data = ( new Builder( function () { return $this->registry(); } ) )->data();

		$this->assertSame(
			array(
				array( 'label' => 'Color', 'options' => array( 'fx-duotone' => 'Duotone' ) ),
				array( 'label' => 'Blur & distortion', 'options' => array( 'fx-wave' => 'Wave' ) ),
			),
			$data['groups']
		);
		$this->assertSame( 'Duotone', $data['filters']['fx-duotone']['label'] );
		$this->assertSame( '<filter id="fx-duotone"/>', $data['filters']['fx-duotone']['markup'] );
		$this->assertSame( 'text', $data['filters']['fx-duotone']['controls'][0]['type'] );
		$this->assertSame( array( 'fx-spotlight' => 'spotlight', 'fx-melt' => 'melt' ), $data['interactive'] );
		$this->assertContains( 'fx-wave', $data['edge'] );
		$this->assertArrayNotHasKey( 'templates', $data, 'selectors come from the builder (modules are not registered server-side there)' );
	}
}
