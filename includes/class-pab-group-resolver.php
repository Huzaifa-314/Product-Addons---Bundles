<?php
defined( 'ABSPATH' ) || exit;

class PAB_Group_Resolver {

	const GROUP_POST_TYPE          = 'pab_addon_group';
	const GROUP_META_FIELDS        = '_pab_group_addon_fields';
	const GROUP_LOCATION_RULES_META = '_pab_group_location_rules';
	const ASSIGNMENTS_OPTION       = 'pab_group_assignments';
	const PRODUCT_ASSIGNMENTS_META = '_pab_product_group_assignments';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
	}

	public static function register_post_type() {
		register_post_type(
			self::GROUP_POST_TYPE,
			[
				'labels'             => [
					'name'               => __( 'Addon Groups', 'pab' ),
					'singular_name'      => __( 'Addon Group', 'pab' ),
					'add_new'            => __( 'Add New Group', 'pab' ),
					'add_new_item'       => __( 'Add New Addon Group', 'pab' ),
					'edit_item'          => __( 'Edit Addon Group', 'pab' ),
					'new_item'           => __( 'New Addon Group', 'pab' ),
					'view_item'          => __( 'View Addon Group', 'pab' ),
					'search_items'       => __( 'Search Addon Groups', 'pab' ),
					'not_found'          => __( 'No addon groups found.', 'pab' ),
					'not_found_in_trash' => __( 'No addon groups found in Trash.', 'pab' ),
				],
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => 'pab-settings',
				'supports'           => [ 'title' ],
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'publicly_queryable' => false,
				'exclude_from_search'=> true,
			]
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all_groups( array $args = [] ): array {
		$query = new WP_Query(
			wp_parse_args(
				$args,
				[
					'post_type'      => self::GROUP_POST_TYPE,
					'post_status'    => [ 'publish', 'draft' ],
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				]
			)
		);

		$rows = [];
		foreach ( $query->posts as $post ) {
			$rows[] = self::get_group( (int) $post->ID );
		}

		return array_values( array_filter( $rows ) );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_group( int $group_id ) {
		$post = get_post( $group_id );
		if ( ! $post || self::GROUP_POST_TYPE !== $post->post_type ) {
			return null;
		}

		$raw_fields = PAB_Data::decode_json_meta( $group_id, self::GROUP_META_FIELDS );
		$fields     = PAB_Data::normalize_addon_fields( $raw_fields );

		$raw_location = PAB_Data::decode_json_meta( $group_id, self::GROUP_LOCATION_RULES_META );

		return [
			'id'              => $group_id,
			'title'           => $post->post_title,
			'status'          => $post->post_status,
			'priority'        => (int) get_post_meta( $group_id, '_pab_group_priority', true ),
			'product_ids'     => array_map( 'absint', (array) get_post_meta( $group_id, '_pab_group_products', true ) ),
			'location_rules'  => self::sanitize_location_rules( $raw_location ),
			'addon_fields'    => $fields,
		];
	}

	/**
	 * Taxonomies allowed in location rule "Parameter" dropdown.
	 *
	 * @return array<string,string> taxonomy => label
	 */
	public static function get_allowed_location_taxonomies(): array {
		$taxes = get_object_taxonomies( 'product', 'objects' );
		if ( ! is_array( $taxes ) ) {
			$taxes = [];
		}

		$out = [];
		foreach ( $taxes as $tax_name => $tax_obj ) {
			if ( ! $tax_obj instanceof WP_Taxonomy ) {
				continue;
			}
			if ( ! $tax_obj->public && 'product_type' !== $tax_name ) {
				continue;
			}
			$out[ $tax_name ] = $tax_obj->labels->singular_name ? $tax_obj->labels->singular_name : $tax_name;
		}

		/**
		 * Filter which product taxonomies appear in addon group location rules.
		 *
		 * @param array<string,string> $out taxonomy_name => admin label
		 */
		return apply_filters( 'pab_group_location_taxonomies', $out );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array{match:string,rules:array<int,array{param:string,operator:string,value:int}>}
	 */
	public static function sanitize_location_rules( $raw ): array {
		$defaults = [
			'match' => 'all',
			'rules' => [],
		];

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$match = isset( $raw['match'] ) && 'any' === $raw['match'] ? 'any' : 'all';

		$allowed_tax = self::get_allowed_location_taxonomies();
		$allowed_tax = array_fill_keys( array_keys( $allowed_tax ), true );

		$rules_in = isset( $raw['rules'] ) && is_array( $raw['rules'] ) ? $raw['rules'] : [];
		$rules    = [];

		foreach ( $rules_in as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$param = isset( $row['param'] ) ? sanitize_key( (string) $row['param'] ) : '';
			if ( '' === $param || ! isset( $allowed_tax[ $param ] ) ) {
				continue;
			}

			$operator = isset( $row['operator'] ) ? (string) $row['operator'] : '==';
			if ( ! in_array( $operator, [ '==', '!=' ], true ) ) {
				continue;
			}

			$term_id = absint( $row['value'] ?? 0 );
			if ( ! $term_id ) {
				continue;
			}

			$term = get_term( $term_id, $param );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$rules[] = [
				'param'    => $param,
				'operator' => $operator,
				'value'    => $term_id,
			];
		}

		return [
			'match' => $match,
			'rules' => $rules,
		];
	}

	/**
	 * @param array{param:string,operator:string,value:int} $rule
	 */
	public static function evaluate_location_rule( int $product_id, array $rule ): bool {
		$taxonomy = isset( $rule['param'] ) ? sanitize_key( (string) $rule['param'] ) : '';
		if ( '' === $taxonomy ) {
			return false;
		}

		$term_id = absint( $rule['value'] ?? 0 );
		if ( ! $term_id ) {
			return false;
		}

		$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '==';
		if ( ! in_array( $operator, [ '==', '!=' ], true ) ) {
			return false;
		}

		$term_ids = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'ids' ] );
		if ( is_wp_error( $term_ids ) ) {
			return false;
		}

		$has = in_array( $term_id, array_map( 'absint', $term_ids ), true );

		return ( '==' === $operator ) ? $has : ! $has;
	}

	/**
	 * @param array{match:string,rules:array<int,array<string,mixed>>} $rules_struct
	 */
	public static function evaluate_location_rules( int $product_id, array $rules_struct ): bool {
		$rules = isset( $rules_struct['rules'] ) && is_array( $rules_struct['rules'] ) ? $rules_struct['rules'] : [];
		if ( empty( $rules ) ) {
			return false;
		}

		$match = isset( $rules_struct['match'] ) && 'any' === $rules_struct['match'] ? 'any' : 'all';

		if ( 'any' === $match ) {
			foreach ( $rules as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				if ( self::evaluate_location_rule( $product_id, $rule ) ) {
					return true;
				}
			}
			return false;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				return false;
			}
			if ( ! self::evaluate_location_rule( $product_id, $rule ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether this addon group applies to the product (explicit IDs OR location rules, per plan).
	 *
	 * @param array<string,mixed> $group From get_group()
	 */
	public static function group_applies_to_product( array $group, int $product_id ): bool {
		$product_ids = array_map( 'absint', (array) ( $group['product_ids'] ?? [] ) );
		$product_ids = array_values( array_filter( $product_ids ) );

		$location   = isset( $group['location_rules'] ) && is_array( $group['location_rules'] ) ? $group['location_rules'] : [];
		$rules      = isset( $location['rules'] ) && is_array( $location['rules'] ) ? $location['rules'] : [];
		$has_rules  = ! empty( $rules );

		if ( empty( $product_ids ) && ! $has_rules ) {
			return false;
		}

		$list_match  = ! empty( $product_ids ) && in_array( $product_id, $product_ids, true );
		$rules_match = $has_rules && self::evaluate_location_rules( $product_id, $location );

		if ( ! empty( $product_ids ) && $has_rules ) {
			return $list_match || $rules_match;
		}
		if ( ! empty( $product_ids ) ) {
			return $list_match;
		}

		return $rules_match;
	}

	/**
	 * @param array<int,array<string,mixed>> $addon_fields
	 */
	public static function save_group( int $group_id, string $title, array $addon_fields, string $status = 'publish' ): int {
		$post_args = [
			'post_type'   => self::GROUP_POST_TYPE,
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => ( 'publish' === $status ) ? 'publish' : 'draft',
		];

		if ( $group_id > 0 ) {
			$post_args['ID'] = $group_id;
			$group_id        = (int) wp_update_post( $post_args );
		} else {
			$group_id = (int) wp_insert_post( $post_args );
		}

		if ( $group_id > 0 ) {
			$clean_fields = self::sanitize_group_fields( $addon_fields );
			update_post_meta( $group_id, self::GROUP_META_FIELDS, wp_json_encode( $clean_fields ) );
		}

		return $group_id;
	}

	public static function delete_group( int $group_id ): bool {
		$post = get_post( $group_id );
		if ( ! $post || self::GROUP_POST_TYPE !== $post->post_type ) {
			return false;
		}

		wp_delete_post( $group_id, true );
		return true;
	}

	/**
	 * @param array<string,mixed>|array<int,mixed> $raw
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_group_fields( $raw ): array {
		$fields = is_array( $raw ) ? $raw : [];
		return PAB_Data::normalize_addon_fields( $fields );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_global_assignments(): array {
		$raw = get_option( self::ASSIGNMENTS_OPTION, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean = [];
		foreach ( $raw as $row ) {
			$sanitized = self::sanitize_assignment_row( $row );
			if ( ! empty( $sanitized ) ) {
				$clean[] = $sanitized;
			}
		}

		return $clean;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	public static function save_global_assignments( array $rows ): void {
		$clean = [];
		foreach ( $rows as $row ) {
			$sanitized = self::sanitize_assignment_row( $row );
			if ( ! empty( $sanitized ) ) {
				$clean[] = $sanitized;
			}
		}
		update_option( self::ASSIGNMENTS_OPTION, $clean, false );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_product_assignments( int $product_id ): array {
		$raw = get_post_meta( $product_id, self::PRODUCT_ASSIGNMENTS_META, true );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean = [];
		foreach ( $raw as $row ) {
			$row['target_type'] = 'product';
			$row['target_id']   = $product_id;
			$sanitized          = self::sanitize_assignment_row( $row );
			if ( ! empty( $sanitized ) ) {
				$clean[] = $sanitized;
			}
		}

		return $clean;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	public static function save_product_assignments( int $product_id, array $rows ): void {
		$clean = [];
		foreach ( $rows as $row ) {
			$row['target_type'] = 'product';
			$row['target_id']   = $product_id;
			$sanitized          = self::sanitize_assignment_row( $row );
			if ( ! empty( $sanitized ) ) {
				$clean[] = [
					'group_id' => $sanitized['group_id'],
					'priority' => $sanitized['priority'],
					'status'   => $sanitized['status'],
				];
			}
		}
		update_post_meta( $product_id, self::PRODUCT_ASSIGNMENTS_META, $clean );
	}

	/**
	 * @param array<string,mixed>|mixed $row
	 * @return array<string,mixed>
	 */
	public static function sanitize_assignment_row( $row ): array {
		if ( ! is_array( $row ) ) {
			return [];
		}

		$group_id = absint( $row['group_id'] ?? 0 );
		if ( ! $group_id || self::GROUP_POST_TYPE !== get_post_type( $group_id ) ) {
			return [];
		}

		$target_type = sanitize_key( $row['target_type'] ?? '' );
		if ( ! in_array( $target_type, [ 'product', 'product_cat', 'product_tag' ], true ) ) {
			return [];
		}

		$target_id = absint( $row['target_id'] ?? 0 );
		if ( ! $target_id ) {
			return [];
		}

		$priority = (int) ( $row['priority'] ?? 100 );
		if ( $priority < -100000 ) {
			$priority = -100000;
		}
		if ( $priority > 100000 ) {
			$priority = 100000;
		}

		$status = ! empty( $row['status'] ) ? 'enabled' : 'disabled';

		return [
			'group_id'    => $group_id,
			'target_type' => $target_type,
			'target_id'   => $target_id,
			'priority'    => $priority,
			'status'      => $status,
		];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function resolve_addon_fields( int $product_id ): array {
		$direct_product_assignments = self::get_product_assignments( $product_id );
		$candidates                 = [];
		$position                   = 0;

		$groups = self::get_all_groups( [ 'post_status' => [ 'publish' ] ] );
		foreach ( $groups as $group ) {
			if ( ! self::group_applies_to_product( $group, $product_id ) ) {
				continue;
			}
			$candidates[] = [
				'group_id' => (int) $group['id'],
				'priority' => (int) ( $group['priority'] ?? 100 ),
				'order'    => $position++,
			];
		}

		// Keep support for product-specific assignments made in the product editor.
		foreach ( $direct_product_assignments as $assignment ) {
			if ( 'enabled' !== ( $assignment['status'] ?? '' ) ) {
				continue;
			}
			$candidates[] = [
				'group_id' => (int) $assignment['group_id'],
				'priority' => (int) $assignment['priority'],
				'order'    => $position++,
			];
		}

		usort(
			$candidates,
			static function( array $a, array $b ): int {
				if ( $a['priority'] === $b['priority'] ) {
					return $a['order'] <=> $b['order'];
				}
				return $a['priority'] <=> $b['priority'];
			}
		);

		$merged = [];
		foreach ( $candidates as $candidate ) {
			$group = self::get_group( (int) $candidate['group_id'] );
			if ( empty( $group['addon_fields'] ) || ! is_array( $group['addon_fields'] ) ) {
				continue;
			}
			$merged = self::merge_fields_by_id( $merged, $group['addon_fields'] );
		}

		$product_fields = PAB_Data::normalize_addon_fields( PAB_Data::decode_json_meta( $product_id, '_addon_fields' ) );
		$merged         = self::merge_fields_by_id( $merged, $product_fields );

		return array_values( $merged );
	}

	/**
	 * @param array<int,array<string,mixed>> $existing
	 * @param array<int,array<string,mixed>> $incoming
	 * @return array<int,array<string,mixed>>
	 */
	private static function merge_fields_by_id( array $existing, array $incoming ): array {
		$lookup = [];
		foreach ( $existing as $index => $field ) {
			$field_id = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
			if ( '' !== $field_id ) {
				$lookup[ $field_id ] = (int) $index;
			}
		}

		foreach ( $incoming as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field_id = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
			if ( '' === $field_id ) {
				$existing[] = $field;
				continue;
			}

			if ( isset( $lookup[ $field_id ] ) ) {
				$idx  = $lookup[ $field_id ];
				$prev = $existing[ $idx ];
				if ( 'popup' === ( $prev['type'] ?? '' ) && 'popup' === ( $field['type'] ?? '' ) ) {
					$prev_nested = isset( $prev['nested_fields'] ) && is_array( $prev['nested_fields'] ) ? $prev['nested_fields'] : [];
					$new_nested  = isset( $field['nested_fields'] ) && is_array( $field['nested_fields'] ) ? $field['nested_fields'] : [];
					$field['nested_fields'] = self::merge_fields_by_id( $prev_nested, $new_nested );
				}
				$existing[ $idx ] = $field;
			} else {
				$lookup[ $field_id ] = count( $existing );
				$existing[]          = $field;
			}
		}

		return $existing;
	}
}
