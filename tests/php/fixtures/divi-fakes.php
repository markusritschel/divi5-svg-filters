<?php
/**
 * Minimal stand-ins for the Divi 5 classes the frontend uses. They mimic the
 * signatures and data flow, not Divi's inheritance or media queries (those
 * were verified against Divi 5.15.1 in the spike, see docs/spike-findings.md).
 *
 * Controlled through globals: fake_blocks, fake_module_preset, fake_group_presets, fake_throw.
 */

namespace ET\Builder\Packages\Module\Layout\Components\Style\Utils {
	class Utils {
		/** One rule per breakpoint/state present in attr; non-desktop wrapped in "@media(<bp>){…}". */
		public static function style_statements( array $args ) {
			if ( ! empty( $GLOBALS['fake_throw'] ) ) {
				throw new \RuntimeException( 'fake failure' );
			}
			$selector = $args['selectors']['desktop']['value'];
			$out      = '';
			foreach ( $args['attr'] as $breakpoint => $states ) {
				foreach ( $states as $state => $value ) {
					$declaration = $args['declarationFunction']( array( 'attrValue' => $value, 'attr' => $args['attr'], 'breakpoint' => $breakpoint, 'state' => $state ) );
					if ( ! $declaration ) {
						continue; // like Divi: empty declarations print nothing
					}
					$rule = ( 'value' === $state ? $selector : $selector . ':' . $state ) . '{' . $declaration . '}';
					$out .= 'desktop' === $breakpoint ? $rule : "@media({$breakpoint}){{$rule}}";
				}
			}
			return $out;
		}
	}
}

namespace ET\Builder\Packages\ModuleUtils {
	class ModuleUtils {
		/** Desktop value, then the breakpoint's value, then its state (simplified getAndInheritAll). */
		public static function use_attr_value( array $args ) {
			$attr = $args['attr'];
			return array_replace(
				(array) ( $attr['desktop']['value'] ?? array() ),
				(array) ( $attr[ $args['breakpoint'] ]['value'] ?? array() ),
				(array) ( $attr[ $args['breakpoint'] ][ $args['state'] ] ?? array() )
			);
		}

		/** 'divi/text-0' → 'et_pb_text_0'. */
		public static function get_module_order_class_name( string $id, $store_instance = null ): string {
			return 'et_pb_' . str_replace( array( 'divi/', '-' ), array( '', '_' ), $id );
		}

		public static function module_preset_merges_style_attrs( string $name ): bool {
			return true;
		}
	}
}

namespace ET\Builder\Packages\StyleLibrary\Declarations\Filters {
	class Filters {
		/** Same logic as Divi's Filters::value(). */
		public static function value( array $attr_value, array $filter_functions ): string {
			$out = array();
			foreach ( $filter_functions as $key => $fn ) {
				if ( ! empty( $attr_value[ $key ] ) ) {
					$out[] = $fn . '(' . $attr_value[ $key ] . ')';
				}
			}
			return implode( ' ', $out );
		}
	}
}

namespace ET\Builder\Packages\GlobalData {
	class GlobalPresetItem {
		private $data;
		public function __construct( $data ) { $this->data = $data; }
		public function is_exist(): bool { return null !== $this->data; }
		public function has_data_attrs(): bool { return ! empty( $this->data['attrs'] ); }
		public function get_data_attrs(): array { return $this->data['attrs'] ?? array(); }
		public function has_data_style_attrs(): bool { return isset( $this->data['styleAttrs'] ); }
		public function get_data_style_attrs(): array { return $this->data['styleAttrs'] ?? array(); }
		public function get_selector_class_name(): string { return $this->data['class'] ?? ''; }
		public function is_nested(): bool { return ! empty( $this->data['nested'] ); }
	}

	class GlobalPreset {
		public static function get_selected_preset( array $args ): GlobalPresetItem {
			return new GlobalPresetItem( $GLOBALS['fake_module_preset'] ?? null );
		}
		public static function get_selected_group_presets( array $args ): array {
			return array_map( function ( $d ) { return new GlobalPresetItem( $d ); }, $GLOBALS['fake_group_presets'] ?? array() );
		}
	}
}

namespace {
	class WP_Block_Type_Registry {
		public static function get_instance() { return new self(); }
		public function get_registered( $name ) {
			return isset( $GLOBALS['fake_blocks'][ $name ] ) ? (object) array( 'attributes' => $GLOBALS['fake_blocks'][ $name ] ) : null;
		}
	}
}
