<?php
/**
 * Tuning hint → control mapping (ported from the kit's demo site build.mjs) and value sanitizing.
 */

use D5SVGF\Tweaks;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/Tweaks.php';

final class TweaksTest extends TestCase {

	private function tweak( string $default, string $hint, string $attr = 'stdDeviation', string $key = 'feGaussianBlur|stdDeviation' ): array {
		return array( 'key' => $key, 'attr' => $attr, 'default' => $default, 'hint' => $hint );
	}

	public function test_hex_default_gives_a_color_picker(): void {
		$c = Tweaks::control( $this->tweak( '#FFE6B8', 'Light color', 'lighting-color' ) );

		$this->assertSame( 'color', $c['type'] );
		$this->assertSame( '#ffe6b8', $c['default'] );
		$this->assertSame( 'Light color', $c['label'] );
	}

	public function test_number_with_range_gives_a_slider_with_unit_and_step(): void {
		$c = Tweaks::control( $this->tweak( '8', '[0, 30] Blur radius in px; higher is softer' ) );

		$this->assertSame( 'range', $c['type'] );
		$this->assertSame( array( 0.0, 30.0 ), array( $c['min'], $c['max'] ) );
		$this->assertSame( 0.1, $c['step'] );
		$this->assertSame( '', $c['unit'] );
		$this->assertSame( 8.0, $c['default'] );
		$this->assertSame( 'Blur radius in px; higher is softer', $c['label'] );
	}

	public function test_unit_is_kept_and_off_grid_defaults_get_step_any(): void {
		$c = Tweaks::control( $this->tweak( '0.65s', '[0.2, 3] Time for the 10 noise frames', 'dur', 'animate|dur' ) );

		$this->assertSame( 's', $c['unit'] );
		$this->assertSame( 0.01, $c['step'] );

		$off = Tweaks::control( $this->tweak( '0.123', '[0, 1] Opacity', 'flood-opacity' ) );
		$this->assertSame( 'any', $off['step'] );
	}

	public function test_number_without_range_gets_the_demo_default_range(): void {
		$c = Tweaks::control( $this->tweak( '5', 'Something' ) );

		$this->assertSame( array( 0.0, 15.0 ), array( $c['min'], $c['max'] ) );
	}

	public function test_lists_give_a_text_field(): void {
		$c = Tweaks::control( $this->tweak( '0.10 0.99', 'Red of the shadow and the highlight color, 0–1 each', 'tableValues', 'feFuncR|tableValues' ) );

		$this->assertSame( 'text', $c['type'] );
		$this->assertSame( '0.10 0.99', $c['default'] );
	}

	/** @dataProvider sanitize_cases */
	public function test_sanitize( string $default, string $hint, $input, ?string $expected ): void {
		$this->assertSame( $expected, Tweaks::sanitize( Tweaks::control( $this->tweak( $default, $hint ) ), $input ) );
	}

	public function sanitize_cases(): array {
		return array(
			'color ok'                => array( '#ffe6b8', 'Light', '#AABBCC', '#aabbcc' ),
			'color short form'        => array( '#ffe6b8', 'Light', '#abc', null ),
			'color injection'         => array( '#ffe6b8', 'Light', '#aabbcc" onload="x', null ),
			'range keeps unit'        => array( '0.65s', '[0.2, 3] Time', '1.5', '1.5s' ),
			'range clamps high'       => array( '8', '[0, 30] Radius', '99', '30' ),
			'range clamps low'        => array( '8', '[0, 30] Radius', '-4', '0' ),
			'range no float noise'    => array( '8', '[0, 30] Radius', 0.1 + 0.2, '0.3' ),
			'range rejects text'      => array( '8', '[0, 30] Radius', '8px; x', null ),
			'range accepts own unit'  => array( '0.65s', '[0.2, 3] Time', '2s', '2s' ),
			'text number list'        => array( '0.10 0.99', 'Red', ' 0.2   0.8 ', '0.2 0.8' ),
			'text negative and exp'   => array( '0 -1 0', 'Kernel', '0 -1 1e-2', '0 -1 1e-2' ),
			'text rejects other text' => array( '0.10 0.99', 'Red', '0.2 "><script>', null ),
			'text rejects too long'   => array( '0.10 0.99', 'Red', str_repeat( '1 ', 200 ), null ),
			'not a string'            => array( '0.10 0.99', 'Red', array( '1' ), null ),
		);
	}
}
