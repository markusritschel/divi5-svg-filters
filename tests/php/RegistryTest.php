<?php
/**
 * Registry: dropdown groups, exclusions, uploads, tuned and instance copies.
 */

use D5SVGF\Registry;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/Tweaks.php';
require_once __DIR__ . '/../../includes/Registry.php';

final class RegistryTest extends TestCase {

	/** svgf_filters()-shaped fixture. */
	private function filters(): array {
		$f = static function ( $id, $title, $category, $markup, $tweaks = array() ) {
			return compact( 'id', 'title', 'category', 'markup', 'tweaks' ) + array( 'desc' => '' );
		};
		return array(
			'fx-duotone'   => $f( 'fx-duotone', 'Duotone', 'color',
				'<filter id="fx-duotone"><feComponentTransfer><feFuncR type="table" tableValues="0.10 0.99"/></feComponentTransfer></filter>',
				array( array( 'key' => 'feFuncR|tableValues', 'attr' => 'tableValues', 'default' => '0.10 0.99', 'hint' => 'Red' ) ) ),
			'fx-blur'      => $f( 'fx-blur', 'Soft blur', 'distortion',
				'<filter id="fx-blur"><feGaussianBlur stdDeviation="8"/><feGaussianBlur stdDeviation="2"/></filter>',
				array(
					array( 'key' => 'feGaussianBlur|stdDeviation', 'attr' => 'stdDeviation', 'default' => '8', 'hint' => '[0, 30] Radius' ),
					array( 'key' => 'feGaussianBlur:2|stdDeviation', 'attr' => 'stdDeviation', 'default' => '2', 'hint' => '[0, 30] Second' ),
				) ),
			'fx-liquid'    => $f( 'fx-liquid', 'Liquid glass', 'glass', '<filter id="fx-liquid"/>' ),
			'fx-frost'     => $f( 'fx-frost', 'Frost', 'glass', '<filter id="fx-frost"/>' ),
			'fx-spotlight' => $f( 'fx-spotlight', 'Pointer light', 'motion',
				'<filter id="fx-spotlight"><feSpecularLighting lighting-color="#ffe6b8"><fePointLight id="fx-spot-light" z="70"/></feSpecularLighting></filter>',
				array( array( 'key' => '#fx-spot-light|z', 'attr' => 'z', 'default' => '70', 'hint' => '[10, 300] Height' ) ) ),
			'fx-themeown'  => $f( 'fx-themeown', '', '', '<filter id="fx-themeown"/>' ),
			'fx-uploaded'  => $f( 'fx-uploaded', 'My upload', 'color', '<filter id="fx-uploaded"/>' ),
		);
	}

	private function registry(): Registry {
		return new Registry( $this->filters(), array( 'fx-uploaded' ) );
	}

	public function test_glass_is_excluded(): void {
		$r = $this->registry();

		$this->assertFalse( $r->has( 'fx-liquid' ) );
		$this->assertFalse( $r->has( 'fx-frost' ) );
		$this->assertTrue( $r->has( 'fx-duotone' ) );
	}

	public function test_groups_follow_the_spec_order_with_custom_last(): void {
		$groups = $this->registry()->grouped();

		$this->assertSame( array( 'Color', 'Blur & distortion', 'Motion', 'Custom' ), array_keys( $groups ) );
		$this->assertSame( array( 'fx-duotone' => 'Duotone' ), $groups['Color'] );
		$this->assertSame( array( 'fx-themeown' => 'fx-themeown', 'fx-uploaded' => 'My upload' ), $groups['Custom'],
			'uploads go to Custom whatever their category; no title shows the ID' );
	}

	public function test_untuned_or_default_tuning_uses_the_shared_filter(): void {
		$r = $this->registry();

		$this->assertNull( $r->copy( 'fx-blur', array() ) );
		$this->assertNull( $r->copy( 'fx-blur', array( 'feGaussianBlur|stdDeviation' => '8' ) ) );
		$this->assertNull( $r->copy( 'fx-blur', array( 'unknown|key' => '3' ) ), 'unknown keys are ignored' );
		$this->assertNull( $r->copy( 'fx-blur', array( 'feGaussianBlur|stdDeviation' => 'evil' ) ), 'invalid values are ignored' );
	}

	public function test_tuned_copy_gets_a_stable_unique_id_and_the_values(): void {
		$r    = $this->registry();
		$copy = $r->copy( 'fx-blur', array( 'feGaussianBlur:2|stdDeviation' => '5', 'feGaussianBlur|stdDeviation' => '8' ) );

		$this->assertMatchesRegularExpression( '/^fx-blur--[0-9a-f]{8}$/', $copy['id'] );
		$this->assertSame( $copy, $r->copy( 'fx-blur', array( 'feGaussianBlur:2|stdDeviation' => '5' ) ), 'defaults do not change the hash' );

		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadXML( $copy['markup'] ), 'copy is well-formed XML' );
		$blurs = $doc->getElementsByTagName( 'feGaussianBlur' );
		$this->assertSame( '8', $blurs->item( 0 )->getAttribute( 'stdDeviation' ) );
		$this->assertSame( '5', $blurs->item( 1 )->getAttribute( 'stdDeviation' ) );
		$this->assertSame( $copy['id'], $doc->documentElement->getAttribute( 'id' ) );
	}

	public function test_copy_renames_inner_ids_and_salt_forces_an_instance(): void {
		$r = $this->registry();

		$a = $r->copy( 'fx-spotlight', array(), 'et_pb_text_0' );
		$b = $r->copy( 'fx-spotlight', array(), 'et_pb_text_1' );
		$this->assertNotNull( $a, 'salt gives a copy even untuned' );
		$this->assertNotSame( $a['id'], $b['id'] );

		$hash = substr( $a['id'], strlen( 'fx-spotlight--' ) );
		$this->assertStringContainsString( 'id="fx-spot-light--' . $hash . '"', $a['markup'] );
		$this->assertStringNotContainsString( 'id="fx-spot-light"', $a['markup'] );

		$tuned = $r->copy( 'fx-spotlight', array( '#fx-spot-light|z' => '120' ), 'et_pb_text_0' );
		$this->assertStringContainsString( 'z="120"', $tuned['markup'] );
		$this->assertNotSame( $a['id'], $tuned['id'] );
	}

	public function test_unknown_or_excluded_ids_have_no_copy(): void {
		$r = $this->registry();

		$this->assertNull( $r->copy( 'fx-nope', array( 'a|b' => '1' ), 'salt' ) );
		$this->assertNull( $r->copy( 'fx-liquid', array(), 'salt' ) );
	}

	public function test_controls_per_filter(): void {
		$controls = $this->registry()->get( 'fx-blur' )['controls'];

		$this->assertSame( array( 'feGaussianBlur|stdDeviation', 'feGaussianBlur:2|stdDeviation' ), array_keys( $controls ) );
		$this->assertSame( 'range', $controls['feGaussianBlur|stdDeviation']['type'] );
	}
}
