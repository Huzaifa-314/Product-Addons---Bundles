<?php
defined( 'ABSPATH' ) || exit;

class PAB_Data {

	/**
	 * POST value for image swatch fields when the buyer chooses “upload your own” instead of a preset option.
	 */
	public const SWATCH_CUSTOM_POST_VALUE = '__pab_custom_image__';

	/**
	 * Global storefront size for product addon image swatch tiles (admin: PAB → Settings).
	 *
	 * @param mixed $value Raw setting.
	 * @return string small|medium|large
	 */
	public static function sanitize_image_swatch_size( $value ): string {
		$v = sanitize_key( (string) $value );
		return in_array( $v, [ 'small', 'medium', 'large' ], true ) ? $v : 'medium';
	}

	/**
	 * Image swatch thumbnail shape for a field (Product → Add-ons & Composite → Image swatch appearance).
	 *
	 * @param mixed $value Raw value.
	 * @return string circle|square
	 */
	public static function sanitize_image_swatch_shape( $value ): string {
		$v = sanitize_key( (string) $value );
		return 'circle' === $v ? 'circle' : 'square';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function decode_json_meta( int $product_id, string $meta_key ): array {
		$raw = get_post_meta( $product_id, $meta_key, true );
		if ( ! $raw ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	public static function generate_id( string $prefix ): string {
		return sanitize_key( $prefix . '_' . wp_generate_uuid4() );
	}

	/**
	 * @param array<string,mixed> $field
	 * @return array<string,mixed>
	 */
	private static function normalize_one_addon_field( array $field ): array {
		$id = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
		if ( '' === $id ) {
			$id = self::generate_id( 'field' );
		}
		$field['id'] = $id;
		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			$opts = [];
			foreach ( $field['options'] as $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}
				$opt_id = isset( $option['id'] ) ? sanitize_key( (string) $option['id'] ) : '';
				if ( '' === $opt_id ) {
					$opt_id = self::generate_id( 'opt' );
				}
				$option['id'] = $opt_id;
				$opts[]       = $option;
			}
			$field['options'] = $opts;
		} else {
			$field['options'] = [];
		}
		$type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
		if ( 'popup' === $type && isset( $field['nested_fields'] ) && is_array( $field['nested_fields'] ) ) {
			$nested = [];
			foreach ( $field['nested_fields'] as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$nested[] = self::normalize_one_addon_field( $child );
			}
			$field['nested_fields'] = $nested;
		} elseif ( isset( $field['nested_fields'] ) ) {
			unset( $field['nested_fields'] );
		}
		return $field;
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_addon_fields( array $fields ): array {
		$normalized = [];
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$normalized[] = self::normalize_one_addon_field( $field );
		}
		return $normalized;
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,array<string,mixed>>
	 */
	public static function field_lookup( array $fields ): array {
		$lookup = [];
		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field_id = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
			if ( '' === $field_id ) {
				continue;
			}
			$lookup[ $field_id ] = [
				'index' => (int) $index,
				'label' => isset( $field['label'] ) ? (string) $field['label'] : '',
			];
		}
		return $lookup;
	}

	/**
	 * @param array<int,array<string,mixed>> $rules
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_conditional_rules( array $rules, array $fields ): array {
		$normalized    = [];
		$field_by_idx  = [];
		$field_ids     = [];
		foreach ( $fields as $idx => $field ) {
			if ( ! is_array( $field ) || empty( $field['id'] ) ) {
				continue;
			}
			$field_by_idx[ (string) $idx ] = sanitize_key( (string) $field['id'] );
			$field_ids[]                   = sanitize_key( (string) $field['id'] );
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$trigger_id = isset( $rule['trigger_field_id'] ) ? sanitize_key( (string) $rule['trigger_field_id'] ) : '';
			if ( '' === $trigger_id && isset( $rule['trigger_field'] ) ) {
				$legacy = (string) $rule['trigger_field'];
				if ( isset( $field_by_idx[ $legacy ] ) ) {
					$trigger_id = $field_by_idx[ $legacy ];
				} else {
					$maybe = sanitize_key( $legacy );
					if ( in_array( $maybe, $field_ids, true ) ) {
						$trigger_id = $maybe;
					}
				}
			}
			if ( '' === $trigger_id ) {
				continue;
			}

			$action   = isset( $rule['action'] ) ? sanitize_text_field( (string) $rule['action'] ) : 'show_field';
			$target_id = isset( $rule['action_target_field_id'] ) ? sanitize_key( (string) $rule['action_target_field_id'] ) : '';

			if ( '' === $target_id && isset( $rule['action_value'] ) && in_array( $action, [ 'show_field', 'hide_field' ], true ) ) {
				$legacy_target = (string) $rule['action_value'];
				if ( isset( $field_by_idx[ $legacy_target ] ) ) {
					$target_id = $field_by_idx[ $legacy_target ];
				} else {
					$maybe_target = sanitize_key( $legacy_target );
					if ( in_array( $maybe_target, $field_ids, true ) ) {
						$target_id = $maybe_target;
					}
				}
			}

			$normalized[] = [
				'id'                     => isset( $rule['id'] ) ? sanitize_key( (string) $rule['id'] ) : self::generate_id( 'rule' ),
				'trigger_field_id'       => $trigger_id,
				'operator'               => isset( $rule['operator'] ) ? sanitize_text_field( (string) $rule['operator'] ) : 'equals',
				'value'                  => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
				'action'                 => $action,
				'action_target_field_id' => $target_id,
				'action_amount'          => isset( $rule['action_amount'] ) ? sanitize_text_field( (string) $rule['action_amount'] ) : sanitize_text_field( (string) ( $rule['action_value'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_variation_payload( int $product_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return [];
		}
		$variations = $product->get_available_variations();
		$out        = [];
		foreach ( $variations as $variation ) {
			$label = implode( ' / ', array_filter( array_values( (array) $variation['attributes'] ) ) );
			$out[] = [
				'variation_id' => (int) $variation['variation_id'],
				'label'        => $label ? $label : '#' . (int) $variation['variation_id'],
				'price'        => (float) $variation['display_price'],
			];
		}
		return $out;
	}
}
