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

		foreach ( $r->all() as $id => $entry ) {
			foreach ( $entry['controls'] as $key => $c ) {
				$value = 'color' === $c['type'] ? '#123456' : ( 'range' === $c['type'] ? (string) $c['max'] : '0.5 0.25' );
				$copy  = $r->copy( $id, array( $key => $value ) );

				$this->assertNotNull( $copy, "{$id} {$key} tuned" );
				$doc = new DOMDocument();
				$this->assertTrue( $doc->loadXML( $copy['markup'] ), "{$id} {$key} copy is well-formed" );
				$this->assertSame( 0, substr_count( $copy['markup'], 'data-' ), "{$id} copy has no data-*" );
			}
		}
	}

	public function test_old_kit_without_metadata_lists_ids_without_tuning(): void {
		require_once __DIR__ . '/../../includes/Tweaks.php';
		require_once __DIR__ . '/../../includes/Registry.php';
		$r = Registry::from_ids( array( 'fx-duotone', 'fx-liquid' ) );

		$this->assertSame( array( 'Custom' => array( 'fx-duotone' => 'fx-duotone' ) ), $r->grouped() );
		$this->assertNull( $r->copy( 'fx-duotone', array(), 'salt' ) );
	}
}
