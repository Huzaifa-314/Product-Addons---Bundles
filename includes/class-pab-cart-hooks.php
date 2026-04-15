<?php
defined( 'ABSPATH' ) || exit;

class PAB_Cart_Hooks {

	/**
	 * @return string[]
	 */
	private function choice_field_types() {
		return [ 'select', 'radio', 'image_swatch', 'text_swatch' ];
	}

	/**
	 * Match a posted choice to an option: by stable option id first, then by label (legacy / visible labels).
	 *
	 * @param array<string,mixed> $field Field config.
	 * @param string              $posted Raw POST value (already unslashed; may be label or opt id).
	 * @return array{opt:array<string,mixed>,index:int}|null
	 */
	private function resolve_pab_choice_option( array $field, string $posted ): ?array {
		$options = $field['options'] ?? [];
		if ( ! is_array( $options ) || $options === [] ) {
			return null;
		}
		$key = sanitize_key( $posted );
		if ( $key !== '' ) {
			foreach ( $options as $idx => $opt ) {
				if ( ! is_array( $opt ) ) {
					continue;
				}
				$oid = isset( $opt['id'] ) ? sanitize_key( (string) $opt['id'] ) : '';
				if ( $oid !== '' && $oid === $key ) {
					return [ 'opt' => $opt, 'index' => (int) $idx ];
				}
			}
		}
		foreach ( $options as $idx => $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			if ( (string) ( $opt['label'] ?? '' ) === $posted ) {
				return [ 'opt' => $opt, 'index' => (int) $idx ];
			}
		}
		return null;
	}

	/**
	 * Human-readable cart line for a choice option (image-only swatches often have empty labels).
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $opt
	 */
	private function choice_option_display_label( array $field, array $opt, int $index ): string {
		$lbl = trim( (string) ( $opt['label'] ?? '' ) );
		if ( $lbl !== '' ) {
			return $lbl;
		}
		if ( 'image_swatch' === ( $field['type'] ?? '' ) && ! empty( $opt['image'] ) ) {
			$path = (string) $opt['image'];
			$path_only = wp_parse_url( $path, PHP_URL_PATH );
			$base      = $path_only ? basename( $path_only ) : basename( $path );
			if ( $base !== '' && $base !== '.' && $base !== '..' ) {
				return $base;
			}
		}
		return sprintf(
			/* translators: %d: 1-based option index */
			__( 'Choice %d', 'pab' ),
			$index + 1
		);
	}

	/**
	 * Monetary amount this add-on adds for one unit of the logic (matches before_calculate_totals).
	 *
	 * @param array<string,mixed> $addon      Cart addon row.
	 * @param float               $base_price Product base price (catalog).
	 * @param int                 $qty        Cart line quantity.
	 */
	private function compute_addon_surcharge( array $addon, float $base_price, int $qty ): float {
		$field_price = (float) ( $addon['price'] ?? 0 );
		switch ( $addon['price_type'] ?? 'flat' ) {
			case 'flat':
				return $field_price;
			case 'percentage':
				return $base_price * $field_price / 100;
			case 'per_qty':
				return $field_price * $qty;
			default:
				return 0.0;
		}
	}

	/**
	 * Resolve stored price + price_type for an add-on from field config and submitted value.
	 *
	 * @param array<string,mixed> $field        Field from _addon_fields.
	 * @param string              $clean_value  Sanitized POST value.
	 * @return array{price:float,price_type:string}
	 */
	private function resolve_addon_pricing( array $field, string $clean_value ): array {
		$type       = $field['type'] ?? 'text';
		$price_type = $field['price_type'] ?? 'flat';

		if ( in_array( $type, $this->choice_field_types(), true ) ) {
			$mode = $field['choice_price_mode'] ?? 'per_option';
			if ( 'uniform' === $mode ) {
				return [
					'price'      => (float) ( $field['price'] ?? 0 ),
					'price_type' => $price_type,
				];
			}
			if ( 'image_swatch' === $type && ! empty( $field['swatch_allow_custom_upload'] ) && $clean_value === PAB_Data::SWATCH_CUSTOM_POST_VALUE ) {
				return [
					'price'      => (float) ( $field['swatch_custom_price'] ?? 0 ),
					'price_type' => 'flat',
				];
			}
			$resolved = $this->resolve_pab_choice_option( $field, $clean_value );
			$price    = $resolved ? (float) ( $resolved['opt']['price'] ?? 0 ) : 0.0;
			return [
				'price'      => $price,
				'price_type' => 'flat',
			];
		}

		if ( 'checkbox' === $type ) {
			return [
				'price'      => $clean_value ? (float) ( $field['price'] ?? 0 ) : 0.0,
				'price_type' => $price_type,
			];
		}

		return [
			'price'      => (float) ( $field['price'] ?? 0 ),
			'price_type' => $price_type,
		];
	}

	public function __construct() {
		add_filter( 'woocommerce_add_to_cart_validation',          [ $this, 'validate_image_swatch_children' ], 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation',          [ $this, 'validate_image_swatch_customer_upload' ], 11, 3 );
		add_filter( 'woocommerce_add_to_cart_validation',          [ $this, 'validate_popup_nested_required' ], 12, 3 );
		add_filter( 'woocommerce_add_cart_item_data',             [ $this, 'add_cart_item_data' ], 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session',     [ $this, 'get_cart_item_from_session' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals',        [ $this, 'before_calculate_totals' ], 20 );
		add_filter( 'woocommerce_get_item_data',                  [ $this, 'get_item_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item',[ $this, 'checkout_create_order_line_item' ], 10, 4 );
	}

	/**
	 * Image swatch extras: at most one child with qty &gt; 0; required group needs a choice; variable needs variation.
	 *
	 * @param bool $passed     Whether validation passed.
	 * @param int  $product_id Product being added.
	 * @param int  $quantity   Requested quantity.
	 */
	public function validate_image_swatch_children( $passed, $product_id, $quantity ) {
		if ( ! $passed ) {
			return false;
		}
		$layout = PAB_Display_Children::sanitize_layout( get_post_meta( $product_id, '_pab_child_layout', true ) );
		if ( 'image_swatch' !== $layout ) {
			return $passed;
		}

		$posted = ( ! empty( $_POST['pab_child'] ) && is_array( $_POST['pab_child'] ) ) ? wp_unslash( $_POST['pab_child'] ) : [];
		$picked = 0;
		foreach ( $posted as $row ) {
			if ( absint( $row['qty'] ?? 0 ) > 0 ) {
				$picked++;
			}
		}
		if ( $picked > 1 ) {
			wc_add_notice( __( 'Please select only one extra product.', 'pab' ), 'error' );
			return false;
		}

		$raw     = get_post_meta( $product_id, '_child_products', true );
		$configs = $raw ? json_decode( $raw, true ) : [];
		if ( ! is_array( $configs ) ) {
			$configs = [];
		}

		$any_required = false;
		foreach ( $configs as $cfg ) {
			if ( ! empty( $cfg['required'] ) ) {
				$any_required = true;
				break;
			}
		}
		if ( $any_required && $picked < 1 ) {
			wc_add_notice( __( 'Please choose an extra.', 'pab' ), 'error' );
			return false;
		}

		foreach ( $posted as $i => $row ) {
			$i = (int) $i;
			if ( absint( $row['qty'] ?? 0 ) < 1 ) {
				continue;
			}
			$cfg = $configs[ $i ] ?? [];
			if ( ! empty( $cfg['is_variable'] ) && ! absint( $row['variation_id'] ?? 0 ) ) {
				wc_add_notice( __( 'Please choose options for the selected extra.', 'pab' ), 'error' );
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Require a valid image upload when an image swatch field uses the custom-upload choice.
	 *
	 * @param bool $passed     Whether validation passed.
	 * @param int  $product_id Product being added.
	 * @param int  $quantity   Requested quantity.
	 */
	public function validate_image_swatch_customer_upload( $passed, $product_id, $quantity ) {
		if ( ! $passed ) {
			return false;
		}

		$addon_fields = PAB_Group_Resolver::resolve_addon_fields( (int) $product_id );
		$image_mimes  = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

		if ( ! empty( $_POST['pab_addon'] ) && is_array( $_POST['pab_addon'] ) ) {
			foreach ( wp_unslash( $_POST['pab_addon'] ) as $i => $value ) {
				$i = (int) $i;
				if ( ! isset( $addon_fields[ $i ] ) ) {
					continue;
				}
				$field = $addon_fields[ $i ];
				if ( 'image_swatch' !== ( $field['type'] ?? '' ) ) {
					continue;
				}
				if ( empty( $field['swatch_allow_custom_upload'] ) ) {
					continue;
				}

				$posted = sanitize_text_field( (string) $value );
				if ( $posted !== PAB_Data::SWATCH_CUSTOM_POST_VALUE ) {
					continue;
				}

				if ( empty( $_FILES['pab_addon_file']['name'][ $i ] ) || ! is_array( $_FILES['pab_addon_file']['tmp_name'] ) ) {
					wc_add_notice( __( 'Please upload an image for your swatch selection.', 'pab' ), 'error' );
					return false;
				}

				$tmp = $_FILES['pab_addon_file']['tmp_name'][ $i ];
				if ( empty( $tmp ) || ! is_uploaded_file( $tmp ) ) {
					wc_add_notice( __( 'Please upload an image for your swatch selection.', 'pab' ), 'error' );
					return false;
				}

				$finfo = new finfo( FILEINFO_MIME_TYPE );
				$mime  = $finfo->file( $tmp );
				if ( ! in_array( $mime, $image_mimes, true ) ) {
					wc_add_notice( __( 'Please upload a valid image file (JPEG, PNG, GIF, or WebP).', 'pab' ), 'error' );
					return false;
				}
			}
		}

		if ( ! empty( $_POST['pab_popup'] ) && is_array( $_POST['pab_popup'] ) ) {
			foreach ( wp_unslash( $_POST['pab_popup'] ) as $pid_raw => $rows ) {
				$pid = sanitize_key( (string) $pid_raw );
				if ( '' === $pid || ! is_array( $rows ) ) {
					continue;
				}
				$popup_cfg = $this->find_popup_field_by_id( $addon_fields, $pid );
				if ( ! $popup_cfg ) {
					continue;
				}
				$nested = $popup_cfg['nested_fields'] ?? [];
				foreach ( $rows as $ci => $value ) {
					$ci = (int) $ci;
					if ( ! isset( $nested[ $ci ] ) ) {
						continue;
					}
					$field = $nested[ $ci ];
					if ( 'image_swatch' !== ( $field['type'] ?? '' ) || empty( $field['swatch_allow_custom_upload'] ) ) {
						continue;
					}
					$posted = sanitize_text_field( (string) $value );
					if ( $posted !== PAB_Data::SWATCH_CUSTOM_POST_VALUE ) {
						continue;
					}
					if ( empty( $_FILES['pab_popup_file']['name'][ $pid ][ $ci ] ) ) {
						wc_add_notice( __( 'Please upload an image for your swatch selection.', 'pab' ), 'error' );
						return false;
					}
					$tmp = $_FILES['pab_popup_file']['tmp_name'][ $pid ][ $ci ] ?? '';
					if ( empty( $tmp ) || ! is_uploaded_file( $tmp ) ) {
						wc_add_notice( __( 'Please upload an image for your swatch selection.', 'pab' ), 'error' );
						return false;
					}
					$finfo = new finfo( FILEINFO_MIME_TYPE );
					$mime  = $finfo->file( $tmp );
					if ( ! in_array( $mime, $image_mimes, true ) ) {
						wc_add_notice( __( 'Please upload a valid image file (JPEG, PNG, GIF, or WebP).', 'pab' ), 'error' );
						return false;
					}
				}
			}
		}

		return $passed;
	}

	/**
	 * @param array<string,mixed> $addon_fields
	 * @return array<string,mixed>|null
	 */
	private function find_popup_field_by_id( array $addon_fields, string $popup_id ) {
		foreach ( $addon_fields as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			if ( 'popup' === ( $f['type'] ?? '' ) && sanitize_key( (string) ( $f['id'] ?? '' ) ) === $popup_id ) {
				return $f;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $file_array Single file entry for wp_handle_upload.
	 */
	private function upload_customer_image_file_array( array $file_array ): string {
		if ( ! empty( $file_array['error'] ) && UPLOAD_ERR_OK !== (int) $file_array['error'] ) {
			return '';
		}
		$allowed_mime = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		$finfo        = new finfo( FILEINFO_MIME_TYPE );
		$mime         = $finfo->file( $file_array['tmp_name'] );
		if ( ! in_array( $mime, $allowed_mime, true ) ) {
			return '';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_upload( $file_array, [ 'test_form' => false ] );
		return ! empty( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '';
	}

	/**
	 * Upload customer image for image_swatch field index; returns attachment URL or empty string.
	 *
	 * @param int $index Field index in pab_addon_file.
	 */
	private function upload_swatch_customer_image( int $index ): string {
		if ( empty( $_FILES['pab_addon_file']['name'][ $index ] ) ) {
			return '';
		}
		$file_array = [
			'name'     => $_FILES['pab_addon_file']['name'][ $index ],
			'type'     => $_FILES['pab_addon_file']['type'][ $index ],
			'tmp_name' => $_FILES['pab_addon_file']['tmp_name'][ $index ],
			'error'    => $_FILES['pab_addon_file']['error'][ $index ],
			'size'     => $_FILES['pab_addon_file']['size'][ $index ],
		];
		return $this->upload_customer_image_file_array( $file_array );
	}

	/**
	 * Swatch custom upload inside a popup nested field.
	 */
	private function upload_popup_swatch_customer_image( string $popup_field_id, int $child_index ): string {
		if ( empty( $_FILES['pab_popup_file']['name'][ $popup_field_id ][ $child_index ] ) ) {
			return '';
		}
		$file_array = [
			'name'     => $_FILES['pab_popup_file']['name'][ $popup_field_id ][ $child_index ],
			'type'     => $_FILES['pab_popup_file']['type'][ $popup_field_id ][ $child_index ],
			'tmp_name' => $_FILES['pab_popup_file']['tmp_name'][ $popup_field_id ][ $child_index ],
			'error'    => $_FILES['pab_popup_file']['error'][ $popup_field_id ][ $child_index ],
			'size'     => $_FILES['pab_popup_file']['size'][ $popup_field_id ][ $child_index ],
		];
		return $this->upload_customer_image_file_array( $file_array );
	}

	/**
	 * Require popup nested fields marked required.
	 *
	 * @param bool $passed     Whether validation passed.
	 * @param int  $product_id Product being added.
	 * @param int  $quantity   Requested quantity.
	 */
	public function validate_popup_nested_required( $passed, $product_id, $quantity ) {
		if ( ! $passed ) {
			return false;
		}
		$addon_fields = PAB_Group_Resolver::resolve_addon_fields( (int) $product_id );
		$posted       = ( ! empty( $_POST['pab_popup'] ) && is_array( $_POST['pab_popup'] ) ) ? wp_unslash( $_POST['pab_popup'] ) : [];

		foreach ( $addon_fields as $popup_cfg ) {
			if ( ! is_array( $popup_cfg ) || 'popup' !== ( $popup_cfg['type'] ?? '' ) ) {
				continue;
			}
			$pid = sanitize_key( (string) ( $popup_cfg['id'] ?? '' ) );
			if ( '' === $pid ) {
				continue;
			}
			foreach ( $popup_cfg['nested_fields'] ?? [] as $ci => $child ) {
				if ( ! is_array( $child ) || empty( $child['required'] ) ) {
					continue;
				}
				$ci = (int) $ci;
				$type = $child['type'] ?? 'text';
				$val  = isset( $posted[ $pid ][ $ci ] ) ? sanitize_text_field( (string) $posted[ $pid ][ $ci ] ) : '';

				if ( 'checkbox' === $type && $val !== '1' ) {
					wc_add_notice( __( 'Please complete all required options in the popup.', 'pab' ), 'error' );
					return false;
				}
				if ( 'image_swatch' === $type && $val === '' ) {
					wc_add_notice( __( 'Please complete all required options in the popup.', 'pab' ), 'error' );
					return false;
				}
				if ( 'image_swatch' === $type && ! empty( $child['swatch_allow_custom_upload'] ) && $val === PAB_Data::SWATCH_CUSTOM_POST_VALUE ) {
					if ( empty( $_FILES['pab_popup_file']['name'][ $pid ][ $ci ] ) ) {
						wc_add_notice( __( 'Please upload an image for your swatch selection.', 'pab' ), 'error' );
						return false;
					}
					continue;
				}
				if ( in_array( $type, [ 'file', 'image_upload' ], true ) ) {
					if ( empty( $_FILES['pab_popup_file']['name'][ $pid ][ $ci ] ) ) {
						wc_add_notice( __( 'Please upload the required file.', 'pab' ), 'error' );
						return false;
					}
					continue;
				}
				if ( $val === '' && 'checkbox' !== $type ) {
					wc_add_notice( __( 'Please complete all required options in the popup.', 'pab' ), 'error' );
					return false;
				}
			}
		}

		return $passed;
	}

	// -------------------------------------------------------------------------
	// 1. Capture addon + child data on add-to-cart
	// -------------------------------------------------------------------------
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$addon_data = [];
		$child_data = [];

		// --- Add-on fields ---
		if ( ! empty( $_POST['pab_addon'] ) && is_array( $_POST['pab_addon'] ) ) {
			$addon_fields = PAB_Group_Resolver::resolve_addon_fields( (int) $product_id );

			foreach ( $_POST['pab_addon'] as $i => $value ) {
				$i = (int) $i;
				if ( ! isset( $addon_fields[ $i ] ) ) {
					continue;
				}
				$field       = $addon_fields[ $i ];
				$posted      = sanitize_text_field( wp_unslash( $value ) );
				$label       = $field['label'] ?? '';
				$price       = 0.0;
				$price_type  = $field['price_type'] ?? 'flat';

				if ( 'image_swatch' === ( $field['type'] ?? '' ) && $posted === PAB_Data::SWATCH_CUSTOM_POST_VALUE && empty( $field['swatch_allow_custom_upload'] ) ) {
					continue;
				}

				$file_url       = '';
				$display_value  = $posted;
				$is_swatch_post = ( 'image_swatch' === ( $field['type'] ?? '' ) );
				$is_custom_path = $is_swatch_post && ! empty( $field['swatch_allow_custom_upload'] ) && $posted === PAB_Data::SWATCH_CUSTOM_POST_VALUE;

				if ( $is_custom_path ) {
					$mode = $field['choice_price_mode'] ?? 'per_option';
					if ( 'uniform' === $mode ) {
						$price      = (float) ( $field['price'] ?? 0 );
						$price_type = $field['price_type'] ?? 'flat';
					} else {
						$price      = (float) ( $field['swatch_custom_price'] ?? 0 );
						$price_type = 'flat';
					}
					$file_url = $this->upload_swatch_customer_image( $i );
					$lbl      = isset( $field['swatch_custom_label'] ) ? trim( (string) $field['swatch_custom_label'] ) : '';
					if ( $lbl === '' ) {
						$lbl = __( 'Custom image', 'pab' );
					}
					$display_value = $lbl;
				} elseif ( in_array( $field['type'], [ 'select', 'radio', 'image_swatch', 'text_swatch' ], true ) ) {
					$choice_mode = $field['choice_price_mode'] ?? 'per_option';
					$resolved    = $this->resolve_pab_choice_option( $field, $posted );
					if ( 'uniform' === $choice_mode ) {
						// Match storefront + JS: one field-level price for every choice (ignore stale per-option amounts in data).
						$price      = (float) ( $field['price'] ?? 0 );
						$price_type = $field['price_type'] ?? 'flat';
					} elseif ( $resolved ) {
						$price      = (float) ( $resolved['opt']['price'] ?? 0 );
						$price_type = 'flat';
					}
					if ( $resolved ) {
						$display_value = $this->choice_option_display_label( $field, $resolved['opt'], $resolved['index'] );
					}
				} elseif ( $field['type'] === 'checkbox' ) {
					$price = $posted ? (float) ( $field['price'] ?? 0 ) : 0;
				} else {
					$price = (float) ( $field['price'] ?? 0 );
				}

				if ( $posted === '' && ! in_array( $field['type'], [ 'checkbox' ], true ) ) {
					// Skip empty optional fields
					if ( empty( $field['required'] ) ) {
						continue;
					}
				}

				$row = [
					'label'      => $label,
					'value'      => $display_value,
					'price'      => $price,
					'price_type' => $price_type,
					'type'       => $field['type'],
				];
				if ( $file_url !== '' ) {
					$row['file_url'] = $file_url;
				}
				$addon_data[] = $row;
			}
		}

		// --- File uploads ---
		if ( ! empty( $_FILES['pab_addon_file'] ) ) {
			$addon_fields = PAB_Group_Resolver::resolve_addon_fields( (int) $product_id );

			foreach ( $_FILES['pab_addon_file']['name'] as $i => $filename ) {
				if ( empty( $filename ) ) {
					continue;
				}
				$i = (int) $i;
				if ( ! isset( $addon_fields[ $i ] ) ) {
					continue;
				}
				$field = $addon_fields[ $i ];
				if ( 'image_swatch' === ( $field['type'] ?? '' ) ) {
					// Customer swatch uploads are merged in the addon row above.
					continue;
				}
				$label = $field['label'] ?? '';
				$price = (float) ( $field['price'] ?? 0 );

				$file_array = [
					'name'     => $_FILES['pab_addon_file']['name'][ $i ],
					'type'     => $_FILES['pab_addon_file']['type'][ $i ],
					'tmp_name' => $_FILES['pab_addon_file']['tmp_name'][ $i ],
					'error'    => $_FILES['pab_addon_file']['error'][ $i ],
					'size'     => $_FILES['pab_addon_file']['size'][ $i ],
				];

				// Validate MIME
				$allowed_mime = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain' ];
				$finfo = new finfo( FILEINFO_MIME_TYPE );
				$mime  = $finfo->file( $file_array['tmp_name'] );
				if ( ! in_array( $mime, $allowed_mime, true ) ) {
					continue;
				}

				require_once ABSPATH . 'wp-admin/includes/file.php';
				$upload = wp_handle_upload( $file_array, [ 'test_form' => false ] );

				if ( ! empty( $upload['url'] ) ) {
					$addon_data[] = [
						'label'      => $label,
						'value'      => $upload['url'],
						'price'      => $price,
						'price_type' => 'flat',
						'type'       => $field['type'],
					];
				}
			}
		}

		// --- Popup nested add-on fields ---
		$popup_uniform_charged = [];
		if ( ! empty( $_POST['pab_popup'] ) && is_array( $_POST['pab_popup'] ) ) {
			$addon_fields = PAB_Group_Resolver::resolve_addon_fields( (int) $product_id );
			foreach ( wp_unslash( $_POST['pab_popup'] ) as $pid_raw => $rows ) {
				$pid = sanitize_key( (string) $pid_raw );
				if ( '' === $pid || ! is_array( $rows ) ) {
					continue;
				}
				$popup_cfg = $this->find_popup_field_by_id( $addon_fields, $pid );
				if ( ! $popup_cfg ) {
					continue;
				}
				$popup_head = trim( (string) ( $popup_cfg['popup_title'] ?? $popup_cfg['label'] ?? '' ) );
				if ( $popup_head === '' ) {
					$popup_head = __( 'Popup', 'pab' );
				}
				$nested = $popup_cfg['nested_fields'] ?? [];
				foreach ( $rows as $ci => $value ) {
					$ci = (int) $ci;
					if ( ! isset( $nested[ $ci ] ) ) {
						continue;
					}
					$field  = $nested[ $ci ];
					$posted = sanitize_text_field( is_string( $value ) ? $value : '' );
					$label  = sprintf( '%1$s — %2$s', $popup_head, $field['label'] ?? __( 'Option', 'pab' ) );
					$price      = 0.0;
					$price_type = $field['price_type'] ?? 'flat';

					if ( 'image_swatch' === ( $field['type'] ?? '' ) && $posted === PAB_Data::SWATCH_CUSTOM_POST_VALUE && empty( $field['swatch_allow_custom_upload'] ) ) {
						continue;
					}

					$file_url       = '';
					$display_value  = $posted;
					$is_swatch_post = ( 'image_swatch' === ( $field['type'] ?? '' ) );
					$is_custom_path = $is_swatch_post && ! empty( $field['swatch_allow_custom_upload'] ) && $posted === PAB_Data::SWATCH_CUSTOM_POST_VALUE;

					if ( $is_custom_path ) {
						$mode = $field['choice_price_mode'] ?? 'per_option';
						if ( 'uniform' === $mode ) {
							$price      = (float) ( $field['price'] ?? 0 );
							$price_type = $field['price_type'] ?? 'flat';
						} else {
							$price      = (float) ( $field['swatch_custom_price'] ?? 0 );
							$price_type = 'flat';
						}
						$file_url = $this->upload_popup_swatch_customer_image( $pid, $ci );
						$lbl      = isset( $field['swatch_custom_label'] ) ? trim( (string) $field['swatch_custom_label'] ) : '';
						if ( $lbl === '' ) {
							$lbl = __( 'Custom image', 'pab' );
						}
						$display_value = $lbl;
					} elseif ( in_array( $field['type'], [ 'select', 'radio', 'image_swatch', 'text_swatch' ], true ) ) {
						$choice_mode = $field['choice_price_mode'] ?? 'per_option';
						$resolved    = $this->resolve_pab_choice_option( $field, $posted );
						if ( 'uniform' === $choice_mode ) {
							$price      = (float) ( $field['price'] ?? 0 );
							$price_type = $field['price_type'] ?? 'flat';
						} elseif ( $resolved ) {
							$price      = (float) ( $resolved['opt']['price'] ?? 0 );
							$price_type = 'flat';
						}
						if ( $resolved ) {
							$display_value = $this->choice_option_display_label( $field, $resolved['opt'], $resolved['index'] );
						}
					} elseif ( $field['type'] === 'checkbox' ) {
						$price = $posted ? (float) ( $field['price'] ?? 0 ) : 0;
					} else {
						$price = (float) ( $field['price'] ?? 0 );
					}

					if ( 'uniform' === PAB_Data::sanitize_nested_price_mode( $popup_cfg['nested_price_mode'] ?? 'per_field' ) ) {
						$charge = false;
						if ( 'checkbox' === ( $field['type'] ?? '' ) ) {
							$charge = (bool) $posted;
						} elseif ( in_array( $field['type'], [ 'select', 'radio', 'image_swatch', 'text_swatch' ], true ) ) {
							$charge = ( $posted !== '' );
						} else {
							$charge = ( $posted !== '' );
						}
						if ( $charge ) {
							if ( empty( $popup_uniform_charged[ $pid ] ) ) {
								$price      = (float) ( $popup_cfg['price'] ?? 0 );
								$price_type = $popup_cfg['price_type'] ?? 'flat';
								$popup_uniform_charged[ $pid ] = true;
							} else {
								$price      = 0.0;
								$price_type = 'flat';
							}
						}
					}

					if ( $posted === '' && ! in_array( $field['type'], [ 'checkbox' ], true ) ) {
						if ( empty( $field['required'] ) ) {
							continue;
						}
					}

					$row = [
						'label'      => $label,
						'value'      => $display_value,
						'price'      => $price,
						'price_type' => $price_type,
						'type'       => $field['type'],
					];
					if ( $file_url !== '' ) {
						$row['file_url'] = $file_url;
					}
					$addon_data[] = $row;
				}
			}
		}

// --- Popup file uploads (excluding image swatch custom merge above) ---
		if ( ! empty( $_FILES['pab_popup_file']['name'] ) && is_array( $_FILES['pab_popup_file']['name'] ) ) {
			$addon_fields = PAB_Group_Resolver::resolve_addon_fields( (int) $product_id );
			foreach ( $_FILES['pab_popup_file']['name'] as $pid_raw => $inner ) {
				if ( ! is_array( $inner ) ) {
					continue;
				}
				$pid = sanitize_key( (string) $pid_raw );
				if ( '' === $pid ) {
					continue;
				}
				$popup_cfg = $this->find_popup_field_by_id( $addon_fields, $pid );
				if ( ! $popup_cfg ) {
					continue;
				}
				$popup_head = trim( (string) ( $popup_cfg['popup_title'] ?? $popup_cfg['label'] ?? '' ) );
				if ( $popup_head === '' ) {
					$popup_head = __( 'Popup', 'pab' );
				}
				$nested = $popup_cfg['nested_fields'] ?? [];
				foreach ( $inner as $ci => $filename ) {
					if ( empty( $filename ) ) {
						continue;
					}
					$ci = (int) $ci;
					if ( ! isset( $nested[ $ci ] ) ) {
						continue;
					}
					$field = $nested[ $ci ];
					if ( 'image_swatch' === ( $field['type'] ?? '' ) ) {
						continue;
					}
					if ( ! in_array( $field['type'], [ 'file', 'image_upload' ], true ) ) {
						continue;
					}
					$label = sprintf( '%1$s — %2$s', $popup_head, $field['label'] ?? '' );
					$price = (float) ( $field['price'] ?? 0 );
					$pu_pt = 'flat';
					if ( 'uniform' === PAB_Data::sanitize_nested_price_mode( $popup_cfg['nested_price_mode'] ?? 'per_field' ) ) {
						if ( empty( $popup_uniform_charged[ $pid ] ) ) {
							$price = (float) ( $popup_cfg['price'] ?? 0 );
							$pu_pt = $popup_cfg['price_type'] ?? 'flat';
							$popup_uniform_charged[ $pid ] = true;
						} else {
							$price = 0.0;
							$pu_pt = 'flat';
						}
					}
					$file_array = [
						'name'     => $_FILES['pab_popup_file']['name'][ $pid ][ $ci ],
						'type'     => $_FILES['pab_popup_file']['type'][ $pid ][ $ci ],
						'tmp_name' => $_FILES['pab_popup_file']['tmp_name'][ $pid ][ $ci ],
						'error'    => $_FILES['pab_popup_file']['error'][ $pid ][ $ci ],
						'size'     => $_FILES['pab_popup_file']['size'][ $pid ][ $ci ],
					];
					$allowed_mime = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain' ];
					$finfo        = new finfo( FILEINFO_MIME_TYPE );
					$mime         = $finfo->file( $file_array['tmp_name'] );
					if ( ! in_array( $mime, $allowed_mime, true ) ) {
						continue;
					}
					require_once ABSPATH . 'wp-admin/includes/file.php';
					$upload = wp_handle_upload( $file_array, [ 'test_form' => false ] );
					if ( ! empty( $upload['url'] ) ) {
						$addon_data[] = [
							'label'      => $label,
							'value'      => $upload['url'],
							'price'      => $price,
							'price_type' => $pu_pt,
							'type'       => $field['type'],
						];
					}
				}
			}
		}

		// --- Child products ---
		if ( ! empty( $_POST['pab_child'] ) && is_array( $_POST['pab_child'] ) ) {
			$raw_children  = get_post_meta( $product_id, '_child_products', true );
			$child_configs = $raw_children ? json_decode( $raw_children, true ) : [];

			foreach ( $_POST['pab_child'] as $i => $child_post ) {
				$i          = (int) $i;
				$child_pid  = absint( $child_post['product_id'] ?? 0 );
				$child_qty  = absint( $child_post['qty'] ?? 0 );
				$var_id     = absint( $child_post['variation_id'] ?? 0 );

				if ( ! $child_pid || $child_qty === 0 ) {
					continue;
				}

				$config = $child_configs[ $i ] ?? [];
				$override_price = $config['override_price'] ?? '';
				$child_product  = wc_get_product( $var_id ?: $child_pid );

				if ( ! $child_product ) {
					continue;
				}

				$unit_price = $override_price !== '' ? (float) $override_price : (float) $child_product->get_price();

				$child_data[] = [
					'product_id'   => $child_pid,
					'variation_id' => $var_id,
					'qty'          => $child_qty,
					'name'         => $child_product->get_name(),
					'unit_price'   => $unit_price,
				];
			}
		}

		if ( ! empty( $addon_data ) ) {
			$cart_item_data['pab_addons'] = $addon_data;
		}
		if ( ! empty( $child_data ) ) {
			$cart_item_data['pab_children'] = $child_data;
		}
		if ( ! empty( $addon_data ) || ! empty( $child_data ) ) {
			// Unique key prevents cart item merging
			$cart_item_data['pab_unique_key'] = md5( microtime() . wp_rand() );
		}

		return $cart_item_data;
	}

	// -------------------------------------------------------------------------
	// 2. Restore from session
	// -------------------------------------------------------------------------
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( isset( $values['pab_addons'] ) ) {
			$cart_item['pab_addons'] = $values['pab_addons'];
		}
		if ( isset( $values['pab_children'] ) ) {
			$cart_item['pab_children'] = $values['pab_children'];
		}
		return $cart_item;
	}

	// -------------------------------------------------------------------------
	// 3. Recalculate price
	// -------------------------------------------------------------------------
	public function before_calculate_totals( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['pab_addons'] ) && empty( $cart_item['pab_children'] ) ) {
				continue;
			}

			$extra = 0.0;
			$qty   = $cart_item['quantity'];

			/** @var WC_Product $product */
			$product = $cart_item['data'];

			// Always fetch a fresh price from DB to prevent double-adding when
			// before_calculate_totals fires more than once in the same request.
			$fresh      = wc_get_product( $product->get_id() );
			$base_price = $fresh ? (float) $fresh->get_price() : 0;

			// Addon prices
			if ( ! empty( $cart_item['pab_addons'] ) ) {
				foreach ( $cart_item['pab_addons'] as $addon ) {
					$extra += $this->compute_addon_surcharge( $addon, $base_price, $qty );
				}
			}

			// Child product prices
			if ( ! empty( $cart_item['pab_children'] ) ) {
				foreach ( $cart_item['pab_children'] as $child ) {
					$extra += (float) $child['unit_price'] * (int) $child['qty'];
				}
			}

			$new_price = $base_price + $extra;
			$product->set_price( $new_price );
		}
	}

	// -------------------------------------------------------------------------
	// 4. Show choices in cart
	// -------------------------------------------------------------------------
	public function get_item_data( $item_data, $cart_item ) {
		$line_qty = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
		/** @var WC_Product $line_product */
		$line_product = $cart_item['data'] ?? null;
		$fresh        = $line_product ? wc_get_product( $line_product->get_id() ) : null;
		$base_for_display = $fresh ? (float) $fresh->get_price() : 0.0;

		if ( ! empty( $cart_item['pab_addons'] ) ) {
			foreach ( $cart_item['pab_addons'] as $addon ) {
				$label = isset( $addon['label'] ) ? trim( (string) $addon['label'] ) : '';
				if ( $label === '' ) {
					$label = __( 'Add-on', 'pab' );
				}
				$raw_value = isset( $addon['value'] ) ? (string) $addon['value'] : '';

				if ( in_array( $addon['type'] ?? '', [ 'file', 'image_upload' ], true ) ) {
					$display_value = '<a href="' . esc_url( $raw_value ) . '" target="_blank">' . esc_html__( 'View File', 'pab' ) . '</a>';
				} elseif ( 'image_swatch' === ( $addon['type'] ?? '' ) && ! empty( $addon['file_url'] ) ) {
					$display_value = esc_html( $raw_value );
					$display_value .= ' — <a href="' . esc_url( $addon['file_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View image', 'pab' ) . '</a>';
				} else {
					$display_value = esc_html( $raw_value );
				}
				$surcharge = $this->compute_addon_surcharge( $addon, $base_for_display, $line_qty );
				if ( $surcharge > 0 ) {
					$display_value .= ' (+' . wc_price( $surcharge ) . ')';
				}
				// WooCommerce treats empty 'key' as missing and reads 'name' instead; always send a non-empty key.
				$item_data[] = [
					'key'   => esc_html( $label ),
					'value' => $display_value,
				];
			}
		}

		if ( ! empty( $cart_item['pab_children'] ) ) {
			foreach ( $cart_item['pab_children'] as $child ) {
				$cname = isset( $child['name'] ) ? trim( (string) $child['name'] ) : '';
				if ( $cname === '' ) {
					$cname = __( 'Extra product', 'pab' );
				}
				$cqty = isset( $child['qty'] ) ? (int) $child['qty'] : 0;
				$cline = (float) ( $child['unit_price'] ?? 0 ) * $cqty;
				$item_data[] = [
					'key'   => esc_html__( 'Extra', 'pab' ),
					'value' => esc_html( $cname ) . ' × ' . esc_html( (string) $cqty ) . ' (+' . wc_price( $cline ) . ')',
				];
			}
		}

		return $item_data;
	}

	// -------------------------------------------------------------------------
	// 5. Save to order line item
	// -------------------------------------------------------------------------
	public function checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['pab_addons'] ) ) {
			foreach ( $values['pab_addons'] as $addon ) {
				$label = isset( $addon['label'] ) ? trim( (string) $addon['label'] ) : '';
				if ( $label === '' ) {
					$label = __( 'Add-on', 'pab' );
				}
				$type      = $addon['type'] ?? '';
				$raw_value = isset( $addon['value'] ) ? (string) $addon['value'] : '';

				if ( in_array( $type, [ 'file', 'image_upload' ], true ) && $raw_value !== '' ) {
					$display_value = '<a href="' . esc_url( $raw_value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View File', 'pab' ) . '</a>';
				} elseif ( 'image_swatch' === $type && ! empty( $addon['file_url'] ) ) {
					$display_value = esc_html( $raw_value );
					$display_value .= ' — <a href="' . esc_url( $addon['file_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View image', 'pab' ) . '</a>';
				} else {
					$display_value = esc_html( $raw_value );
				}

				$item->add_meta_data(
					esc_html( $label ),
					wp_kses_post( $display_value ),
					true
				);
			}
		}

		if ( ! empty( $values['pab_children'] ) ) {
			foreach ( $values['pab_children'] as $child ) {
				$cname = isset( $child['name'] ) ? trim( (string) $child['name'] ) : '';
				if ( $cname === '' ) {
					$cname = __( 'Extra product', 'pab' );
				}
				$cqty = isset( $child['qty'] ) ? (int) $child['qty'] : 0;
				$item->add_meta_data(
					esc_html__( 'Extra', 'pab' ),
					esc_html( $cname ) . ' × ' . $cqty,
					false
				);
			}
		}
	}
}
