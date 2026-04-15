<?php
defined( 'ABSPATH' ) || exit;

class PAB_Save_Fields {

	public function __construct( $register_hooks = true ) {
		if ( $register_hooks ) {
			add_action( 'woocommerce_process_product_meta', [ $this, 'save' ] );
		}
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST['pab_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['pab_nonce'] ), 'pab_save_product_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$addon_fields      = $this->sanitize_addon_fields( $_POST['pab_addon_fields'] ?? [] );
		$child_products    = $this->sanitize_child_products( $_POST['pab_child_products'] ?? [] );
		$conditional_rules = $this->sanitize_conditional_rules( $_POST['pab_conditional_rules'] ?? [], $addon_fields );
		$group_assignments = $this->sanitize_product_group_assignments( $_POST['pab_product_group_assignments'] ?? [] );
		$layout_raw        = $_POST['pab_child_layout'] ?? 'default';
		$child_layout      = PAB_Display_Children::sanitize_layout( is_string( $layout_raw ) ? $layout_raw : 'default' );

		update_post_meta( $post_id, '_addon_fields', wp_json_encode( $addon_fields ) );
		update_post_meta( $post_id, '_child_products', wp_json_encode( $child_products ) );
		update_post_meta( $post_id, '_conditional_rules', wp_json_encode( $conditional_rules ) );
		update_post_meta( $post_id, '_pab_child_layout', $child_layout );
		PAB_Group_Resolver::save_product_assignments( $post_id, $group_assignments );
	}

	public function sanitize_addon_fields( $raw ) {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean = [];
		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$clean[] = $this->sanitize_one_addon_field( $field, true );
		}

		return $clean;
	}

	/**
	 * @param array<string,mixed> $field
	 * @return array<string,mixed>
	 */
	private function sanitize_one_addon_field( array $field, bool $allow_popup_type ) {
		$base_allowed_types     = [ 'text', 'textarea', 'select', 'checkbox', 'radio', 'number', 'file', 'image_upload', 'image_swatch', 'text_swatch' ];
		$allowed_types          = $allow_popup_type ? array_merge( $base_allowed_types, [ 'popup' ] ) : $base_allowed_types;
		$allowed_price_types    = [ 'flat', 'percentage', 'per_qty' ];
		$allowed_choice_modes   = [ 'uniform', 'per_option' ];
		$choice_field_types     = [ 'select', 'radio', 'image_swatch', 'text_swatch' ];

		$type = sanitize_text_field( $field['type'] ?? 'text' );
		$type = in_array( $type, $allowed_types, true ) ? $type : 'text';

		if ( 'popup' === $type ) {
			$btn = sanitize_text_field( $field['popup_button_label'] ?? '' );
			if ( $btn === '' ) {
				$btn = __( 'Customize', 'pab' );
			}
			$nested_clean = [];
			if ( ! empty( $field['nested_fields'] ) && is_array( $field['nested_fields'] ) ) {
				foreach ( $field['nested_fields'] as $child ) {
					if ( ! is_array( $child ) ) {
						continue;
					}
					$nested_clean[] = $this->sanitize_one_addon_field( $child, false );
				}
			}

			$popup_side_image = esc_url_raw( trim( (string) ( $field['popup_side_image'] ?? '' ) ) );

			$nested_price_mode = PAB_Data::sanitize_nested_price_mode( $field['nested_price_mode'] ?? 'per_field' );
			$popup_price_type  = sanitize_text_field( $field['price_type'] ?? 'flat' );
			$popup_price_type  = in_array( $popup_price_type, $allowed_price_types, true ) ? $popup_price_type : 'flat';
			$popup_price       = (float) ( $field['price'] ?? 0 );
			if ( 'per_field' === $nested_price_mode ) {
				$popup_price      = 0.0;
				$popup_price_type = 'flat';
			}

			return [
				'id'                  => $this->sanitize_or_generate_id( $field['id'] ?? '', 'field' ),
				'type'                => 'popup',
				'label'               => sanitize_text_field( $field['label'] ?? '' ),
				'required'            => ! empty( $field['required'] ),
				'popup_button_label'  => $btn,
				'popup_title'         => sanitize_text_field( $field['popup_title'] ?? '' ),
				'popup_description'   => wp_kses_post( (string) ( $field['popup_description'] ?? '' ) ),
				'popup_side_image'    => $popup_side_image,
				'nested_fields'       => $nested_clean,
				'nested_price_mode'   => $nested_price_mode,
				'options'             => [],
				'price'               => $popup_price,
				'price_type'          => $popup_price_type,
				'choice_price_mode'   => 'per_option',
			];
		}

		$price_type = sanitize_text_field( $field['price_type'] ?? 'flat' );
		$price_type = in_array( $price_type, $allowed_price_types, true ) ? $price_type : 'flat';

		$choice_mode = sanitize_text_field( $field['choice_price_mode'] ?? 'per_option' );
		if ( ! in_array( $choice_mode, $allowed_choice_modes, true ) ) {
			$choice_mode = 'per_option';
		}
		if ( ! in_array( $type, $choice_field_types, true ) ) {
			$choice_mode = 'per_option';
		}

		$item = [
			'id'                 => $this->sanitize_or_generate_id( $field['id'] ?? '', 'field' ),
			'type'               => $type,
			'label'              => sanitize_text_field( $field['label'] ?? '' ),
			'required'           => ! empty( $field['required'] ),
			'price'              => (float) ( $field['price'] ?? 0 ),
			'price_type'         => $price_type,
			'choice_price_mode'  => $choice_mode,
			'options'            => [],
		];

		if ( in_array( $type, [ 'text', 'textarea', 'number' ], true ) ) {
			$item['placeholder'] = sanitize_text_field( $field['placeholder'] ?? '' );
		}

		if ( 'image_swatch' === $type ) {
			$item['image_swatch_size']          = PAB_Data::sanitize_image_swatch_size( $field['image_swatch_size'] ?? 'medium' );
			$item['image_swatch_shape']         = PAB_Data::sanitize_image_swatch_shape( $field['image_swatch_shape'] ?? 'square' );
			$item['swatch_allow_custom_upload'] = ! empty( $field['swatch_allow_custom_upload'] );
			$custom_lbl                         = sanitize_text_field( $field['swatch_custom_label'] ?? '' );
			$item['swatch_custom_label']        = $custom_lbl !== '' ? $custom_lbl : 'Upload your own';
			$item['swatch_custom_price']        = (float) ( $field['swatch_custom_price'] ?? 0 );
		}

		if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			foreach ( $field['options'] as $opt ) {
				if ( ! is_array( $opt ) ) {
					continue;
				}
				$opt_item = [
					'id'    => $this->sanitize_or_generate_id( $opt['id'] ?? '', 'opt' ),
					'label' => sanitize_text_field( $opt['label'] ?? '' ),
					'price' => (float) ( $opt['price'] ?? 0 ),
				];
				if ( isset( $opt['image'] ) ) {
					$opt_item['image'] = esc_url_raw( $opt['image'] );
				}
				$item['options'][] = $opt_item;
			}
		}

		return $item;
	}

	private function sanitize_child_products( $raw ) {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean = [];

		foreach ( $raw as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$product_id = absint( $child['product_id'] ?? 0 );
			if ( ! $product_id ) {
				continue;
			}

			$allowed_vars = [];
			if ( ! empty( $child['allowed_variations'] ) && is_array( $child['allowed_variations'] ) ) {
				$allowed_vars = array_map( 'absint', $child['allowed_variations'] );
			}

			$override = $child['override_price'] ?? '';
			$override = $override !== '' ? (float) $override : '';

			$product    = wc_get_product( $product_id );
			$is_variable = $product && $product->is_type( 'variable' );

			$clean[] = [
				'product_id'         => $product_id,
				'is_variable'        => $is_variable,
				'allowed_variations' => $allowed_vars,
				'min_qty'            => absint( $child['min_qty'] ?? 0 ),
				'max_qty'            => absint( $child['max_qty'] ?? 1 ),
				'required'           => ! empty( $child['required'] ),
				'override_price'     => $override,
			];
		}

		return $clean;
	}

	private function sanitize_conditional_rules( $raw, $addon_fields ) {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean            = [];
		$allowed_operators = [ 'equals', 'not_equals', 'greater_than', 'less_than' ];
		$allowed_actions   = [ 'show_field', 'hide_field', 'add_price', 'subtract_price', 'percentage_discount' ];
		$field_ids         = [];
		foreach ( $addon_fields as $field ) {
			if ( is_array( $field ) && ! empty( $field['id'] ) ) {
				$field_ids[] = sanitize_key( (string) $field['id'] );
			}
		}

		foreach ( $raw as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$trigger_field_id = sanitize_key( $rule['trigger_field_id'] ?? '' );
			if ( '' === $trigger_field_id || ! in_array( $trigger_field_id, $field_ids, true ) ) {
				continue;
			}

			$operator = sanitize_text_field( $rule['operator'] ?? 'equals' );
			$operator = in_array( $operator, $allowed_operators, true ) ? $operator : 'equals';
			$action   = sanitize_text_field( $rule['action'] ?? 'show_field' );
			$action   = in_array( $action, $allowed_actions, true ) ? $action : 'show_field';

			$action_target_field_id = sanitize_key( $rule['action_target_field_id'] ?? '' );
			if ( in_array( $action, [ 'show_field', 'hide_field' ], true ) ) {
				if ( '' === $action_target_field_id || ! in_array( $action_target_field_id, $field_ids, true ) ) {
					continue;
				}
			} else {
				$action_target_field_id = '';
			}

			$action_amount = sanitize_text_field( $rule['action_amount'] ?? '' );

			$clean[] = [
				'id'                     => $this->sanitize_or_generate_id( $rule['id'] ?? '', 'rule' ),
				'trigger_field_id'       => $trigger_field_id,
				'operator'               => $operator,
				'value'                  => sanitize_text_field( $rule['value'] ?? '' ),
				'action'                 => $action,
				'action_target_field_id' => $action_target_field_id,
				'action_amount'          => $action_amount,
			];
		}

		return $clean;
	}

	private function sanitize_or_generate_id( $raw_id, $prefix ) {
		$id = sanitize_key( (string) $raw_id );
		if ( '' === $id ) {
			$id = PAB_Data::generate_id( $prefix );
		}
		return $id;
	}

	private function sanitize_product_group_assignments( $raw ) {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean[] = [
				'group_id' => absint( $row['group_id'] ?? 0 ),
				'priority' => (int) ( $row['priority'] ?? 100 ),
				'status'   => ! empty( $row['status'] ) ? 'enabled' : 'disabled',
			];
		}

		return $clean;
	}
}
