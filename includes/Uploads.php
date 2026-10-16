<?php
/**
 * Uploaded filters, stored in one option: id => {title, markup, file, time}.
 *
 * The markup is the sanitized <filter> (with its <title>/<desc>/data-*
 * metadata; the kit strips data-* before printing). Uploads reach the page
 * through the kit's svgf_extra_sources, so they get defs and .fx-style
 * classes like kit filters.
 *
 * @package D5SVGF
 */

namespace D5SVGF;

defined( 'ABSPATH' ) || exit;

final class Uploads {

	const OPTION = 'd5svgf_uploads';

	/** @return array<string, array{title:string, markup:string, file:string, time:int}> */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$out    = array();
		foreach ( is_array( $stored ) ? $stored : array() as $id => $item ) {
			if ( is_string( $id ) && is_array( $item ) && is_string( $item['markup'] ?? null ) ) {
				$out[ $id ] = array(
					'title'  => (string) ( $item['title'] ?? '' ),
					'markup' => $item['markup'],
					'file'   => (string) ( $item['file'] ?? '' ),
					'time'   => (int) ( $item['time'] ?? 0 ),
				);
			}
		}
		return $out;
	}

	/** @return string[] */
	public static function ids(): array {
		return array_keys( self::all() );
	}

	/** svgf_extra_sources: the uploads' markup. */
	public static function sources( $sources ) {
		$sources = is_array( $sources ) ? $sources : array();
		foreach ( self::all() as $item ) {
			$sources[] = $item['markup'];
		}
		return $sources;
	}

	/** Every filter ID and inner primitive ID in use (kit, theme files, uploads). */
	public static function taken_ids(): array {
		$markups = array_column( self::all(), 'markup' );
		if ( function_exists( 'svgf_collect' ) ) {
			$data    = svgf_collect();
			$markups = array_merge( $markups, array_values( (array) ( $data['raw'] ?? array() ) ) );
			$ids     = (array) ( $data['ids'] ?? array() );
		} else {
			$ids = array();
		}
		foreach ( $markups as $markup ) {
			if ( preg_match_all( '/\sid="([^"]+)"/', (string) $markup, $m ) ) {
				$ids = array_merge( $ids, $m[1] );
			}
		}
		return array_values( array_unique( array_merge( $ids, self::ids() ) ) );
	}

	/**
	 * Sanitize an uploaded file and store its valid filters.
	 *
	 * @return array Sanitizer result (filters, skipped, error).
	 */
	public static function import( string $svg, string $file ): array {
		$result = Sanitizer::filters( $svg, self::taken_ids() );
		if ( $result['filters'] ) {
			$stored = get_option( self::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();
			foreach ( $result['filters'] as $filter ) {
				$stored[ $filter['id'] ] = array(
					'title'  => $filter['title'],
					'markup' => $filter['markup'],
					'file'   => $file,
					'time'   => time(),
				);
			}
			update_option( self::OPTION, $stored, true ); // autoloaded: read on every page through the kit
		}
		return $result;
	}

	public static function delete( string $id ): bool {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || ! isset( $stored[ $id ] ) ) {
			return false;
		}
		unset( $stored[ $id ] );
		update_option( self::OPTION, $stored, true );
		return true;
	}
}
