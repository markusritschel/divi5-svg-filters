<?php
/**
 * Tuning hints (syntax 2) → controls, and Divi field values → attribute values.
 */

use D5SVGF\Tweaks;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/divi-fakes.php';
require_once __DIR__ . '/../../includes/Tweaks.php';

final class TweaksTest extends TestCase {

	private function control( string $default, string $hint, string $key = 'feGaussianBlur|stdDeviation' ): array {
		return Tweaks::control( array( 'key' => $key, 'attr' => explode( '|', $key )[1], 'default' => $default, 'hint' => $hint ) );
	}

	/* ---------- control() ---------- */

	public function test_label_description_unit_and_range(): void {
		$c = $this->control( '8', '[0, 30 px] Blur radius | Higher is softer' );

		$this->assertSame( 'range', $c['type'] );
		$this->assertSame( 'Blur radius', $c['label'] );
		$this->assertSame( 'Higher is softer', $c['description'] );
		$this->assertSame( array( 0.0, 30.0, 0.1 ), array( $c['min'], $c['max'], $c['step'] ) );
		$this->assertSame( 'px', $c['unit'], 'display unit from the hint' );
		$this->assertSame( '', $c['suffix'], 'the SVG value has no unit' );
		$this->assertSame( 8.0, $c['default'] );
	}

	public function test_suffix_comes_from_the_default_value(): void {
		$c = $this->control( '.8s', '[0.2, 3 s] Flicker cycle', 'animate|dur' );

		$this->assertSame( array( 's', 's' ), array( $c['unit'], $c['suffix'] ) );
	}

	public function test_plain_label_without_description(): void {
		$c = $this->control( '#000000', 'Shadow color', 'feFlood|flood-color' );

		$this->assertSame( array( 'color', 'Shadow color', '' ), array( $c['type'], $c['label'], $c['description'] ) );
	}

	public function test_old_style_hint_still_works(): void {
		$c = $this->control( '8', '[0, 30] Blur radius in px; higher is softer' );

		$this->assertSame( array( 'range', 'Blur radius in px; higher is softer', 30.0 ), array( $c['type'], $c['label'], $c['max'] ) );
	}

	public function test_pair_levels_matrix(): void {
		$pair = $this->control( '.008 .03', '[0, 0.1] {pair} Ripple frequency | Horizontal and vertical', 'feTurbulence|baseFrequency' );
		$this->assertSame( array( 'pair', 0.008, 0.03, 0.0, 0.1 ), array( $pair['type'], $pair['default_x'], $pair['default_y'], $pair['min'], $pair['max'] ) );

		$levels = $this->control( '0 .35 .7 1', '{levels} Red levels', 'feFuncR|tableValues' );
		$this->assertSame( array( 'levels', 4, 0.0, 1.0 ), array( $levels['type'], $levels['count'], $levels['lo'], $levels['hi'] ) );

		$matrix = $this->control( '-2 -1 0  -1 1 1  0 1 2', '{matrix} Kernel', 'feConvolveMatrix|kernelMatrix' );
		$this->assertSame( array( 'matrix', '-2 -1 0 -1 1 1 0 1 2' ), array( $matrix['type'], $matrix['default'] ) );
	}

	/* ---------- compose(): field values → attribute value / style ---------- */

	/** @dataProvider compose_cases */
	public function test_compose( string $default, string $hint, string $key, array $tune, $expected ): void {
		$this->assertSame( $expected, Tweaks::compose( $this->control( $default, $hint, $key ), $tune ) );
	}

	public function compose_cases(): array {
		$blur = array( '8', '[0, 30 px] Blur radius', 'feGaussianBlur|stdDeviation' );
		$dur  = array( '.8s', '[0.2, 3 s] Flicker cycle', 'animate|dur' );
		$col  = array( '#000000', 'Shadow color', 'feFlood|flood-color' );
		$pair = array( '.008 .03', '[0, 0.1] {pair} Ripple frequency', 'feTurbulence|baseFrequency' );
		$lev  = array( '0 .35 .7 1', '{levels} Red levels', 'feFuncR|tableValues' );
		$mat  = array( '-2 -1 0 -1 1 1 0 1 2', '{matrix} Kernel', 'feConvolveMatrix|kernelMatrix' );
		$gc   = '$variable({"type":"color","value":{"name":"gcid-primary-color","settings":{}}})$';
		$gcs  = '$variable({"type":"color","value":{"name":"gcid-primary-color","settings":{"lightness":-10,"opacity":50}}})$';

		$c = static function ( array $base, array $tune, $expected ) {
			return array_merge( $base, array( $tune, $expected ) );
		};
		return array(
			'range with display unit'   => $c( $blur, array( 'feGaussianBlur|stdDeviation' => '12px' ), array( 'value' => '12', 'style' => null ) ),
			'range unitless'            => $c( $blur, array( 'feGaussianBlur|stdDeviation' => '12' ), array( 'value' => '12', 'style' => null ) ),
			'range clamped'             => $c( $blur, array( 'feGaussianBlur|stdDeviation' => '99px' ), array( 'value' => '30', 'style' => null ) ),
			'range wrong unit'          => $c( $blur, array( 'feGaussianBlur|stdDeviation' => '2em' ), null ),
			'range default'             => $c( $blur, array( 'feGaussianBlur|stdDeviation' => '8px' ), null ),
			'range keeps suffix'        => $c( $dur, array( 'animate|dur' => '1.5s' ), array( 'value' => '1.5s', 'style' => null ) ),
			'color hex'                 => $c( $col, array( 'feFlood|flood-color' => '#FF0000' ), array( 'value' => '#ff0000', 'style' => null ) ),
			'color global'              => $c( $col, array( 'feFlood|flood-color' => $gc ), array( 'value' => null, 'style' => 'flood-color:var(--gcid-primary-color)' ) ),
			'color global adjusted'     => $c( $col, array( 'feFlood|flood-color' => $gcs ), array( 'value' => null, 'style' => 'flood-color:hsl(from var(--gcid-primary-color) calc(h + 0) calc(s + 0) calc(l - 10) / 0.5)' ) ),
			'color bad variable'        => $c( $col, array( 'feFlood|flood-color' => '$variable({"type":"color","value":{"name":"x;}body{"}})$' ), null ),
			// On the frontend Divi's block parser has already resolved the reference (seen with 5.15.1).
			'color global resolved'     => $c( $col, array( 'feFlood|flood-color' => 'var(--gcid-primary-color)' ), array( 'value' => null, 'style' => 'flood-color:var(--gcid-primary-color)' ) ),
			'color global resolved hsl' => $c( $col, array( 'feFlood|flood-color' => 'hsl(from var(--gcid-primary-color) calc(h + 0) calc(s + 0) calc(l - 10) / 0.5)' ), array( 'value' => null, 'style' => 'flood-color:hsl(from var(--gcid-primary-color) calc(h + 0) calc(s + 0) calc(l - 10) / 0.5)' ) ),
			'color other variable'      => $c( $col, array( 'feFlood|flood-color' => 'var(--wp-admin-color)' ), null ),
			'color resolved injection'  => $c( $col, array( 'feFlood|flood-color' => 'var(--gcid-x);x:url(y)' ), null ),
			'color injection'           => $c( $col, array( 'feFlood|flood-color' => '#fff" onload="x' ), null ),
			'color with opacity'        => $c( $col, array( 'feFlood|flood-color' => 'rgba(51, 102, 153, 0.5)' ), array( 'value' => 'rgba(51, 102, 153, 0.5)', 'style' => null ) ),
			'color rgb'                 => $c( $col, array( 'feFlood|flood-color' => 'rgb(51,102,153)' ), array( 'value' => 'rgba(51, 102, 153, 1)', 'style' => null ) ),
			'color rgba out of range'   => $c( $col, array( 'feFlood|flood-color' => 'rgba(300, 0, 0, 0.5)' ), null ),
			'color rgba injection'      => $c( $col, array( 'feFlood|flood-color' => 'rgba(1, 2, 3, 0.5);x:y' ), null ),
			'pair both'                 => $c( $pair, array( 'feTurbulence|baseFrequency@x' => '0.02', 'feTurbulence|baseFrequency@y' => '0.05' ), array( 'value' => '0.02 0.05', 'style' => null ) ),
			'pair one, other default'   => $c( $pair, array( 'feTurbulence|baseFrequency@y' => '0.09' ), array( 'value' => '0.008 0.09', 'style' => null ) ),
			'pair clamped'              => $c( $pair, array( 'feTurbulence|baseFrequency@x' => '5' ), array( 'value' => '0.1 0.03', 'style' => null ) ),
			'pair saved by 0.2 as list' => $c( $pair, array( 'feTurbulence|baseFrequency' => '0.02 0.04' ), array( 'value' => '0.02 0.04', 'style' => null ) ),
			'levels count'              => $c( $lev, array( 'feFuncR|tableValues@count' => '3' ), array( 'value' => '0 0.5 1', 'style' => null ) ),
			'levels shared range'       => $c( $lev, array( 'feFuncR|tableValues@count' => '3', '@min' => '0.2', '@max' => '0.8' ), array( 'value' => '0.2 0.5 0.8', 'style' => null ) ),
			'levels range only'         => $c( $lev, array( '@max' => '0.6' ), array( 'value' => '0 0.2 0.4 0.6', 'style' => null ) ),
			'levels count clamped'      => $c( $lev, array( 'feFuncR|tableValues@count' => '99' ), array( 'value' => '0 0.111111 0.222222 0.333333 0.444444 0.555556 0.666667 0.777778 0.888889 1', 'style' => null ) ),
			'levels untouched'          => $c( $lev, array(), null ),
			'levels saved by 0.2'       => $c( $lev, array( 'feFuncR|tableValues' => '0 1' ), array( 'value' => '0 1', 'style' => null ) ),
			'matrix'                    => $c( $mat, array( 'feConvolveMatrix|kernelMatrix' => '0 -1 0 -1 5 -1 0 -1 0' ), array( 'value' => '0 -1 0 -1 5 -1 0 -1 0', 'style' => null ) ),
			'matrix wrong size'         => $c( $mat, array( 'feConvolveMatrix|kernelMatrix' => '1 2 3' ), null ),
			'matrix default'            => $c( $mat, array( 'feConvolveMatrix|kernelMatrix' => '-2 -1 0  -1 1 1  0 1 2' ), null ),
			'not a string'              => $c( $blur, array( 'feGaussianBlur|stdDeviation' => array( '1' ) ), null ),
		);
	}

	/* ---------- sanitize(): single text/number lists ---------- */

	public function test_text_lists(): void {
		$c = $this->control( '0.1 0.9', 'Values', 'feFuncA|tableValues' );

		$this->assertSame( '0.2 0.8', Tweaks::sanitize( $c, ' 0.2   0.8 ' ) );
		$this->assertNull( Tweaks::sanitize( $c, '0.2 "><script>' ) );
		$this->assertNull( Tweaks::sanitize( $c, str_repeat( '1 ', 200 ) ) );
	}
}
