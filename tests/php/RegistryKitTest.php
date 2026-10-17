<?php
/**
 * Registry against the real bundled kit: every filter and every tuning hint works.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */

use D5SVGF\Registry;
use PHPUnit\Framework\TestCase;

final class RegistryKitTest extends TestCase {

	private function registry(): Registry {
		require_once __DIR__ . '/../../kit/svg-filters.php';
		require_once __DIR__ . '/../../includes/Tweaks.php';
		require_once __DIR__ . '/../../includes/Registry.php';
		return Registry::from_kit();
	}

	public function test_every_kit_filter_except_glass_is_listed_with_a_title(): void {
		$r = $this->registry();

		$this->assertSame( 22, count( $r->all() ), '23 kit filters minus fx-liquid' );
		$this->assertFalse( $r->has( 'fx-liquid' ) );
		foreach ( $r->all() as $id => $entry ) {
			$this->assertNotSame( $id, $entry['label'], "{$id} has a title" );
			$this->assertNotSame( 'Custom', $entry['group'], "{$id} has a known category" );
		}
	}

	public function test_every_hint_gives_a_control_and_a_well_formed_tuned_copy(): void {
		$r = $this->registry();

		$global = '$variable({"type":"color","value":{"name":"gcid-primary-color","settings":{}}})$';
		foreach ( $r->all() as $id => $entry ) {
			foreach ( $entry['controls'] as $key => $c ) {
				switch ( $c['type'] ) {
					case 'color':
						$tune = array( $key => $global );
						break;
					case 'range':
						$tune = array( $key => ( $c['default'] === $c['max'] ? $c['min'] : $c['max'] ) . $c['unit'] );
						break;
					case 'pair':
						$tune = array( $key . '@y' => (string) $c['max'] );
						break;
					case 'levels':
						$tune = array( '@channels' => 'each', $key . '@count' => '3' );
						break;
					case 'alpha': // set through the linked color's opacity
						$tune = array( $c['color']['key'] => 'rgba(10, 20, 30, 0.3)' );
						break;
					case 'matrix':
						$tune = array( $key => '0 -1 0 -1 5 -1 0 -1 0' );
						break;
					default:
						$tune = array( $key => '0.5 0.25' );
				}
				$copy = $r->copy( $id, $tune );

				$this->assertNotNull( $copy, "{$id} {$key} ({$c['type']}) tuned" );
				$doc = new DOMDocument();
				$this->assertTrue( $doc->loadXML( $copy['markup'] ), "{$id} {$key} copy is well-formed" );
				$this->assertSame( 0, substr_count( $copy['markup'], 'data-' ), "{$id} copy has no data-*" );
				if ( 'color' === $c['type'] ) {
					$this->assertStringContainsString( 'style="' . $c['attr'] . ':var(--gcid-primary-color)"', $copy['markup'], "{$id} global color as CSS" );
				}
			}
		}
	}

	public function test_kit_controls_use_the_new_types_and_labels(): void {
		$r = $this->registry();

		$this->assertSame( array( 'color', 'color' ), array_column( $r->get( 'fx-duotone' )['controls'], 'type' ) );
		$this->assertSame( 'pair', $r->get( 'fx-wave' )['controls']['feTurbulence|baseFrequency']['type'] );
		$this->assertSame( 'levels', $r->get( 'fx-posterize' )['controls']['feFuncR|tableValues']['type'] );
		$this->assertSame( 'matrix', $r->get( 'fx-emboss' )['controls']['feConvolveMatrix|kernelMatrix']['type'] );
		$blur = $r->get( 'fx-blur' )['controls']['feGaussianBlur|stdDeviation'];
		$this->assertSame( array( 'Blur radius', 'Higher is softer', 'px' ), array( $blur['label'], $blur['description'], $blur['unit'] ) );
		$this->assertArrayHasKey( 'Sharpen', $r->get( 'fx-emboss' )['presets'] );
	}

	public function test_old_kit_without_metadata_lists_ids_without_tuning(): void {
		require_once __DIR__ . '/../../includes/Tweaks.php';
		require_once __DIR__ . '/../../includes/Registry.php';
		$r = Registry::from_ids( array( 'fx-duotone', 'fx-liquid' ) );

		$this->assertSame( array( 'Custom' => array( 'fx-duotone' => 'fx-duotone' ) ), $r->grouped() );
		$this->assertNull( $r->copy( 'fx-duotone', array(), 'salt' ) );
	}
}
