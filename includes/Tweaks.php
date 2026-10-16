<?php
/**
 * Tuning hints → builder controls, and validation of tuned values.
 *
 * The mapping follows the kit's demo site (docs branch, build.mjs): a hex
 * default gives a color picker, one number (optional unit) a slider, anything
 * else a text field. Hints read "[min, max] description"; the range is
 * optional.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

defined( 'ABSPATH' ) || exit;

final class Tweaks {

	/** Longest accepted number list (characters), e.g. a 5×4 color matrix. */
	const MAX_TEXT = 200;

	/**
	 * @param array $tweak One svgf_filters() tweak: key, attr, default, hint.
	 * @return array Control: key, attr, label, type ('color'|'range'|'text'), default;
	 *               ranges add min, max, step (float|'any'), unit.
	 */
	public static function control( array $tweak ): array {
		$default = (string) ( $tweak['default'] ?? '' );
		preg_match( '/^(?:\[\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*\]\s*)?(.*)$/s', (string) ( $tweak['hint'] ?? '' ), $hint );

		$label   = trim( $hint[3] );
		$control = array(
			'key'   => (string) ( $tweak['key'] ?? '' ),
			'attr'  => (string) ( $tweak['attr'] ?? '' ),
			'label' => '' !== $label ? $label : (string) ( $tweak['attr'] ?? '' ),
		);

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $default ) ) {
			return $control + array( 'type' => 'color', 'default' => strtolower( $default ) );
		}

		if ( preg_match( '/^(-?\d*\.?\d+)([a-z%]*)$/i', $default, $num ) ) {
			$value = (float) $num[1];
			if ( '' !== $hint[1] ) {
				$min = (float) $hint[1];
				$max = (float) $hint[2];
			} else {
				$min = min( 0.0, $value * 3 );
				$max = max( 1.0, $value * 3 );
			}

			$step = 'any';
			if ( $max > $min ) {
				$step  = pow( 10, floor( log10( $max - $min ) ) - 2 );
				$steps = ( $value - $min ) / $step;
				if ( abs( $steps - round( $steps ) ) > 1e-6 ) {
					$step = 'any'; // never snap the file's own value
				}
			}

			return $control + array(
				'type'    => 'range',
				'default' => $value,
				'min'     => $min,
				'max'     => $max,
				'step'    => $step,
				'unit'    => $num[2],
			);
		}

		return $control + array( 'type' => 'text', 'default' => $default );
	}

	/**
	 * Validate a tuned value for a control.
	 *
	 * @param array $control From control().
	 * @param mixed $value   User input.
	 * @return string|null Attribute value ready for the markup, or null if invalid.
	 */
	public static function sanitize( array $control, $value ) {
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		switch ( $control['type'] ) {
			case 'color':
				return is_string( $value ) && preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtolower( $value ) : null;

			case 'range':
				if ( is_string( $value ) ) {
					if ( ! preg_match( '/^\s*(-?\d*\.?\d+(?:e-?\d+)?)\s*([a-z%]*)\s*$/i', $value, $m )
						|| ( '' !== $m[2] && $m[2] !== $control['unit'] ) ) {
						return null;
					}
					$value = $m[1];
				}
				$number = min( $control['max'], max( $control['min'], (float) $value ) );
				return self::number( $number ) . $control['unit'];

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

	/** Whether a sanitized value equals the control's default. */
	public static function is_default( array $control, string $value ): bool {
		if ( 'range' === $control['type'] ) {
			return abs( (float) $value - $control['default'] ) < 1e-9;
		}
		if ( 'text' === $control['type'] ) {
			return self::sanitize( $control, $control['default'] ) === $value;
		}
		return $control['default'] === $value;
	}

	/** Locale-independent number without float noise or trailing zeros. */
	private static function number( float $number ): string {
		$out = rtrim( rtrim( sprintf( '%.6F', $number ), '0' ), '.' );
		return '-0' === $out ? '0' : $out;
	}
}
