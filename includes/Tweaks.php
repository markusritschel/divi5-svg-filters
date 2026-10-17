<?php
/**
 * Tuning hints → controls, and Divi field values → attribute values.
 *
 * Hints (kit hint syntax 2): "[min, max unit] {type} Label | Description";
 * range, unit (px, deg, s, %), type (pair, levels, matrix) and description
 * are optional. A hex default gives a color, one number a range, a {pair}
 * two ranges, {levels} a level count (+ a shared darkest/brightest range),
 * {matrix} a 3×3 grid, anything else a number list.
 *
 * Each control maps to one or more Divi fields inside svgTune[<filter id>]:
 * '<key>' (range, color, matrix, text), '<key>@x' / '<key>@y' (pair),
 * '<key>@count' plus the filter-wide '@min' / '@max' (levels). compose()
 * turns those field values into the attribute value, or for global colors
 * into a CSS declaration for the primitive's style.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

use ET\Builder\Packages\GlobalData\GlobalData;

defined( 'ABSPATH' ) || exit;

final class Tweaks {

	/** Longest accepted number list (characters), e.g. a 5×4 color matrix. */
	const MAX_TEXT = 200;

	/** Level counts offered for {levels}. */
	const LEVELS_MIN = 2;
	const LEVELS_MAX = 10;

	/**
	 * @param array $tweak One svgf_filters() tweak: key, attr, default, hint.
	 * @return array Control: key, attr, label, description, type, default, and per type:
	 *               range: min, max, step (float|'any'), unit (display), suffix (in the SVG value);
	 *               pair: the same plus default_x, default_y; levels: count, lo, hi.
	 */
	public static function control( array $tweak ): array {
		$default = trim( preg_replace( '/\s+/', ' ', (string) ( $tweak['default'] ?? '' ) ) );
		preg_match(
			'/^(?:\[\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)(?:\s+(px|deg|s|%))?\s*\]\s*)?(?:\{(\w+)\}\s*)?(.*)$/s',
			(string) ( $tweak['hint'] ?? '' ),
			$hint
		);
		$text  = explode( ' | ', trim( $hint[5] ), 2 );
		$label = trim( $text[0] );

		$control = array(
			'key'         => (string) ( $tweak['key'] ?? '' ),
			'attr'        => (string) ( $tweak['attr'] ?? '' ),
			'label'       => '' !== $label ? $label : (string) ( $tweak['attr'] ?? '' ),
			'description' => trim( $text[1] ?? '' ),
		);
		$type = $hint[4] ?? '';
		$unit = $hint[3] ?? '';

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $default ) ) {
			return $control + array( 'type' => 'color', 'default' => strtolower( $default ) );
		}

		if ( 'matrix' === $type ) {
			return $control + array( 'type' => 'matrix', 'default' => $default );
		}

		$numbers = self::numbers( $default );
		if ( 'levels' === $type && $numbers && count( $numbers ) >= self::LEVELS_MIN ) {
			return $control + array(
				'type'    => 'levels',
				'default' => $default,
				'count'   => count( $numbers ),
				'lo'      => (float) min( $numbers ),
				'hi'      => (float) max( $numbers ),
			);
		}

		if ( 'pair' === $type && $numbers && 2 === count( $numbers ) ) {
			return $control + self::range( $hint, max( $numbers ), $numbers[0], $unit ) + array(
				'type'      => 'pair',
				'default'   => $default,
				'default_x' => (float) $numbers[0],
				'default_y' => (float) $numbers[1],
				'suffix'    => '',
			);
		}

		if ( preg_match( '/^(-?\d*\.?\d+)([a-z%]*)$/i', $default, $num ) ) {
			return $control + self::range( $hint, (float) $num[1], (float) $num[1], '' !== $unit ? $unit : $num[2] ) + array(
				'type'    => 'range',
				'default' => (float) $num[1],
				'suffix'  => $num[2],
			);
		}

		return $control + array( 'type' => 'text', 'default' => $default );
	}

	/** min, max, step, unit for a slider; without a hint range the demo site's default range. */
	private static function range( array $hint, float $scale, float $value, string $unit ): array {
		if ( '' !== $hint[1] ) {
			$min = (float) $hint[1];
			$max = (float) $hint[2];
		} else {
			$min = min( 0.0, $scale * 3 );
			$max = max( 1.0, $scale * 3 );
		}

		$step = 'any';
		if ( $max > $min ) {
			$step  = pow( 10, floor( log10( $max - $min ) ) - 2 );
			$steps = ( $value - $min ) / $step;
			if ( abs( $steps - round( $steps ) ) > 1e-6 ) {
				$step = 'any'; // never snap the file's own value
			}
		}
		return array( 'min' => $min, 'max' => $max, 'step' => $step, 'unit' => $unit );
	}

	/**
	 * The attribute value (or style declaration) for a control from the filter's field values.
	 *
	 * @param array $control From control().
	 * @param array $tune    Field values of the filter (svgTune[<filter id>]).
	 * @return array{value:?string, style:?string}|null Null: nothing valid or only the default.
	 */
	public static function compose( array $control, array $tune ) {
		$key = $control['key'];

		switch ( $control['type'] ) {
			case 'color':
				$raw = $tune[ $key ] ?? null;
				if ( ! is_string( $raw ) ) {
					return null;
				}
				// Builder: '$variable(…)$'; frontend: Divi's block parser has already resolved it to CSS.
				$css = self::color_variable( $raw ) ?? self::resolved_global_color( $raw );
				if ( null !== $css ) {
					return array( 'value' => null, 'style' => $control['attr'] . ':' . $css );
				}
				$color = self::sanitize( $control, $raw );
				return null !== $color && $color !== $control['default'] ? array( 'value' => $color, 'style' => null ) : null;

			case 'range':
				if ( ! array_key_exists( $key, $tune ) ) {
					return null;
				}
				$value = self::sanitize( $control, $tune[ $key ] );
				return null !== $value && ! self::is_default( $control, $value ) ? array( 'value' => $value, 'style' => null ) : null;

			case 'pair':
				$legacy = array_key_exists( $key, $tune ) ? self::numbers( (string) self::sanitize( array( 'type' => 'text' ), $tune[ $key ] ) ) : array();
				$x      = self::pair_part( $control, $tune[ $key . '@x' ] ?? null, $legacy[0] ?? $control['default_x'] );
				$y      = self::pair_part( $control, $tune[ $key . '@y' ] ?? null, $legacy[1] ?? $control['default_y'] );
				$value  = self::number( $x ) . ' ' . self::number( $y );
				return abs( $x - $control['default_x'] ) < 1e-9 && abs( $y - $control['default_y'] ) < 1e-9 ? null : array( 'value' => $value, 'style' => null );

			case 'levels':
				$given = array_intersect_key( $tune, array_flip( array( $key . '@count', '@min', '@max' ) ) );
				if ( ! $given ) {
					// Saved by 0.1/0.2 as a plain list.
					$value = array_key_exists( $key, $tune ) ? self::sanitize( array( 'type' => 'text' ), $tune[ $key ] ) : null;
					return null !== $value && $value !== self::sanitize( array( 'type' => 'text' ), $control['default'] ) ? array( 'value' => $value, 'style' => null ) : null;
				}
				$count = self::clamp_number( $tune[ $key . '@count' ] ?? null, self::LEVELS_MIN, self::LEVELS_MAX, (float) $control['count'] );
				$lo    = self::clamp_number( $tune['@min'] ?? null, 0.0, 1.0, $control['lo'] );
				$hi    = self::clamp_number( $tune['@max'] ?? null, 0.0, 1.0, $control['hi'] );
				$count = (int) round( $count );
				$list  = array();
				for ( $i = 0; $i < $count; $i++ ) {
					$list[] = self::number( $lo + ( $hi - $lo ) * $i / ( $count - 1 ) );
				}
				return array( 'value' => implode( ' ', $list ), 'style' => null );

			case 'matrix':
				$value = array_key_exists( $key, $tune ) ? self::sanitize( $control, $tune[ $key ] ) : null;
				if ( null === $value || 9 !== count( self::numbers( $value ) ) ) {
					return null;
				}
				return $value !== self::sanitize( $control, $control['default'] ) ? array( 'value' => $value, 'style' => null ) : null;

			default:
				$value = array_key_exists( $key, $tune ) ? self::sanitize( $control, $tune[ $key ] ) : null;
				return null !== $value && $value !== self::sanitize( $control, $control['default'] ) ? array( 'value' => $value, 'style' => null ) : null;
		}
	}

	/**
	 * Validate one value for a control (range: number with the display unit or the suffix; color: hex or rgb(a);
	 * others: a number list).
	 *
	 * @param array $control From control().
	 * @param mixed $value   Field value.
	 * @return string|null Attribute value, or null if invalid.
	 */
	public static function sanitize( array $control, $value ) {
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		switch ( $control['type'] ) {
			case 'color':
				if ( ! is_string( $value ) ) {
					return null;
				}
				if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
					return strtolower( $value );
				}
				// Divi's color picker stores rgba() once the opacity is below 100%.
				if ( ! preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(\d*\.?\d+)\s*)?\)$/i', $value, $m )
					|| max( (int) $m[1], (int) $m[2], (int) $m[3] ) > 255 ) {
					return null;
				}
				$alpha = isset( $m[4] ) && '' !== $m[4] ? (float) $m[4] : 1.0;
				return $alpha > 1 ? null : sprintf( 'rgba(%d, %d, %d, %s)', $m[1], $m[2], $m[3], self::number( $alpha ) );

			case 'range':
				$number = self::parse_number( $value, $control );
				return null === $number ? null : self::number( min( $control['max'], max( $control['min'], $number ) ) ) . $control['suffix'];

			default:
				$tokens = preg_split( '/[\s,]+/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
				$list   = implode( ' ', $tokens );
				if ( ! $tokens || strlen( $list ) > self::MAX_TEXT ) {
					return null;
				}
				foreach ( $tokens as $token ) {
					if ( ! is_numeric( $token ) ) {
						return null;
					}
				}
				return $list;
		}
	}

	/**
	 * CSS value for a Divi global color reference ('$variable({"type":"color",…})$'), or null.
	 * Uses Divi's own transform (hue/saturation/lightness/opacity adjustments) when available.
	 */
	public static function color_variable( string $raw ) {
		if ( ! preg_match( '/^\$variable\((.*)\)\$$/s', $raw, $m ) ) {
			return null;
		}
		$data = json_decode( $m[1], true );
		$name = is_array( $data ) ? (string) ( $data['value']['name'] ?? '' ) : '';
		if ( 'color' !== ( $data['type'] ?? '' ) || ! preg_match( '/^gcid-[a-z0-9-]+$/i', $name ) ) {
			return null;
		}

		$settings = array();
		foreach ( (array) ( $data['value']['settings'] ?? array() ) as $setting => $value ) {
			if ( in_array( $setting, array( 'hue', 'saturation', 'lightness', 'opacity' ), true ) && is_numeric( $value ) ) {
				$settings[ $setting ] = 0 + $value;
			}
		}

		$css = 'var(--' . $name . ')';
		if ( $settings && method_exists( GlobalData::class, 'transform_state_into_global_color_value' ) ) {
			$css = (string) GlobalData::transform_state_into_global_color_value( $css, $settings );
		}
		return preg_match( '/^[a-z0-9\s(),.%+\-\/]+$/i', $css ) ? $css : null;
	}

	/** A global color as Divi resolves it (var(--gcid-…) or hsl(from var(--gcid-…) …)), or null. */
	private static function resolved_global_color( string $raw ) {
		return preg_match( '/^(?:var\(--gcid-[a-z0-9-]+\)|hsl\(from var\(--gcid-[a-z0-9-]+\)[a-z0-9\s(),.%+\-\/]*\))$/i', $raw ) ? $raw : null;
	}

	/** Whether a sanitized range value equals the control's default. */
	public static function is_default( array $control, string $value ): bool {
		return abs( (float) $value - $control['default'] ) < 1e-9;
	}

	/** Number from a field value: plain, with the display unit, or with the SVG suffix. */
	private static function parse_number( $value, array $control ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		if ( ! preg_match( '/^\s*(-?\d*\.?\d+(?:e-?\d+)?)\s*([a-z%]*)\s*$/i', $value, $m ) ) {
			return null;
		}
		$unit = strtolower( $m[2] );
		if ( '' !== $unit && $unit !== ( $control['unit'] ?? '' ) && $unit !== ( $control['suffix'] ?? '' ) ) {
			return null;
		}
		return (float) $m[1];
	}

	private static function pair_part( array $control, $value, float $fallback ): float {
		$number = null === $value ? null : self::parse_number( $value, $control );
		return min( $control['max'], max( $control['min'], null === $number ? $fallback : $number ) );
	}

	private static function clamp_number( $value, float $min, float $max, float $fallback ): float {
		$number = is_numeric( $value ) ? (float) $value : $fallback;
		return min( $max, max( $min, $number ) );
	}

	/** @return float[] Numbers of a space/comma separated list (empty if any token is not numeric). */
	private static function numbers( string $list ): array {
		$out = array();
		foreach ( preg_split( '/[\s,]+/', trim( $list ), -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
			if ( ! is_numeric( $token ) ) {
				return array();
			}
			$out[] = (float) $token;
		}
		return $out;
	}

	/** Locale-independent number without float noise or trailing zeros. */
	private static function number( float $number ): string {
		$out = rtrim( rtrim( sprintf( '%.6F', $number ), '0' ), '.' );
		return '-0' === $out ? '0' : $out;
	}
}
