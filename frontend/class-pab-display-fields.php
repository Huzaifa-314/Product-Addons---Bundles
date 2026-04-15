<?php
defined( 'ABSPATH' ) || exit;

class PAB_Display_Fields {

	private $product_id;
	private $fields;

	/** @var string[] */
	private static function choice_types() {
		return [ 'select', 'radio', 'image_swatch', 'text_swatch' ];
	}

	public function __construct( $product_id, $fields ) {
		$this->product_id = $product_id;
		$this->fields     = $fields;
	}

	public function render() {
		if ( empty( $this->fields ) ) {
			return;
		}
		echo '<div class="pab-addons-section">';
		echo '<h4 class="pab-section-heading">' . esc_html__( 'Customize', 'pab' ) . '</h4>';

		foreach ( $this->fields as $index => $field ) {
			$this->render_field( $index, $field );
		}

		echo '</div>';
	}

	/**
	 * Shown next to the field label when choice fields use uniform pricing.
	 */
	private function uniform_price_hint_html( float $price, string $price_type ): string {
		if ( $price <= 0 ) {
			return '';
		}
		if ( 'flat' === $price_type ) {
			return ' <span class="pab-opt-price">(+' . wc_price( $price ) . ')</span>';
		}
		if ( 'percentage' === $price_type ) {
			return ' <span class="pab-opt-price">(+' . esc_html( wc_format_decimal( $price, wc_get_price_decimals() ) ) . '%)</span>';
		}
		if ( 'per_qty' === $price_type ) {
			return ' <span class="pab-opt-price">(+' . wc_price( $price ) . ' ' . esc_html__( '× quantity', 'pab' ) . ')</span>';
		}
		return '';
	}

	/**
	 * Primary line for image upload dropzone (WooCommerce → Product Addons & Bundles → General).
	 */
	private function get_upload_image_drop_title(): string {
		$settings = get_option( 'pab_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$custom = isset( $settings['upload_image_drop_title'] ) ? trim( (string) $settings['upload_image_drop_title'] ) : '';
		if ( $custom !== '' ) {
			return $custom;
		}
		return __( 'Drop an image here', 'pab' );
	}

	/**
	 * Styled dropzone + native file input (pricing/cart logic unchanged).
	 *
	 * @param string $file_input_name Full name attribute, e.g. pab_addon_file[0] or pab_popup_file[id][0].
	 * @param string $extra_wrap_class Optional extra class(es) on the upload root (e.g. swatch variant).
	 */
	private function render_file_upload_field( string $file_input_name, string $type, string $req_attr, string $extra_wrap_class = '' ): void {
		$is_image = ( 'image_upload' === $type );
		$accept   = $is_image ? ' accept="image/*"' : '';
		$inp_cls  = 'pab-field-file pab-file-upload-input' . ( $is_image ? ' pab-file-upload-input--image' : '' );
		$wrap_cls = 'pab-file-upload' . ( $is_image ? ' pab-file-upload--image' : '' );
		if ( $extra_wrap_class !== '' ) {
			$wrap_cls .= ' ' . sanitize_html_class( $extra_wrap_class, '' );
		}

		echo '<div class="' . esc_attr( $wrap_cls ) . '">';
		echo '<div class="pab-file-upload-drop">';
		echo '<input type="file" name="' . esc_attr( $file_input_name ) . '" class="' . esc_attr( $inp_cls ) . '" ' . $req_attr . $accept . ' />';
		echo '<div class="pab-file-upload-panel">';
		if ( $is_image ) {
			echo '<div class="pab-file-upload-preview" hidden>';
			echo '<img src="" alt="" class="pab-file-upload-preview-img" loading="lazy" decoding="async" />';
			echo '</div>';
		}
		echo '<div class="pab-file-upload-body">';
		echo '<span class="pab-file-upload-icon">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG, fixed markup.
		echo '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
		echo '</span>';
		$drop_title = $is_image ? $this->get_upload_image_drop_title() : __( 'Drop a file here', 'pab' );
		if ( $is_image ) {
			echo '<div class="pab-file-upload-cta">';
			echo '<span class="pab-file-upload-cta-line">';
		}
		echo '<span class="pab-file-upload-title">' . esc_html( $drop_title ) . '</span>';
		echo '<span class="pab-file-upload-sub pab-file-upload-sub--idle">' . esc_html( __( 'or click to browse', 'pab' ) ) . '</span>';
		if ( $is_image ) {
			echo '</span>';
			echo '<span class="pab-file-upload-sub pab-file-upload-sub--replace" hidden>' . esc_html__( 'Drop or click to replace', 'pab' ) . '</span>';
		}
		echo '<span class="pab-file-upload-filename" data-empty-label="' . esc_attr__( 'No file chosen', 'pab' ) . '">' . esc_html__( 'No file chosen', 'pab' ) . '</span>';
		if ( $is_image ) {
			echo '</div>';
		}
		echo '</div></div></div>';
		echo '</div>';
	}

	/**
	 * Label hint when each choice has its own price (min–max or single amount).
	 * Preset options only — not image swatch “custom upload” (that price shows on its tile only).
	 *
	 * @param array<int,array<string,mixed>> $options
	 */
	private function per_option_field_label_prices_html( array $options, string $price_type ): string {
		$amounts = [];
		foreach ( $options as $opt ) {
			$p = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
			if ( $p > 0 ) {
				$amounts[] = $p;
			}
		}
		if ( empty( $amounts ) ) {
			return '';
		}
		$min = min( $amounts );
		$max = max( $amounts );
		if ( $min === $max ) {
			return $this->uniform_price_hint_html( $min, $price_type );
		}
		$dec = wc_get_price_decimals();
		if ( 'flat' === $price_type ) {
			return ' <span class="pab-opt-price">(+' . wp_kses_post( wc_price( $min ) ) . ' – +' . wp_kses_post( wc_price( $max ) ) . ')</span>';
		}
		if ( 'percentage' === $price_type ) {
			return ' <span class="pab-opt-price">(+' . esc_html( wc_format_decimal( $min, $dec ) ) . '% – +' . esc_html( wc_format_decimal( $max, $dec ) ) . '%)</span>';
		}
		if ( 'per_qty' === $price_type ) {
			return ' <span class="pab-opt-price">(+' . wp_kses_post( wc_price( $min ) ) . ' – +' . wp_kses_post( wc_price( $max ) ) . ' ' . esc_html__( '× quantity', 'pab' ) . ')</span>';
		}
		return '';
	}

	private function render_field( $index, $field ) {
		if ( ! is_array( $field ) ) {
			return;
		}
		$type = $field['type'] ?? 'text';
		if ( 'popup' === $type ) {
			$this->render_popup_field( (int) $index, $field );
			return;
		}
		$input_name = 'pab_addon[' . (int) $index . ']';
		$file_name  = 'pab_addon_file[' . (int) $index . ']';
		$this->render_field_inner( $field, $input_name, $file_name, (int) $index, false, -1, '', false, 0.0, 'flat' );
	}

	/**
	 * @param array<string,mixed> $field
	 */
	private function render_popup_field( int $list_index, array $field ): void {
		$field_id = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
		if ( '' === $field_id ) {
			return;
		}
		$btn = isset( $field['popup_button_label'] ) ? trim( (string) $field['popup_button_label'] ) : '';
		$btn   = $btn !== '' ? $btn : __( 'Customize', 'pab' );
		$title = isset( $field['popup_title'] ) ? trim( (string) $field['popup_title'] ) : '';
		$desc = isset( $field['popup_description'] ) ? (string) $field['popup_description'] : '';
		$desc = str_replace( [ "\r\n", "\r" ], "\n", $desc );
		$desc = str_ireplace( 'rnrn', "\n\n", $desc );
		$popup_side_raw = isset( $field['popup_side_image'] ) ? trim( (string) $field['popup_side_image'] ) : '';
		$popup_side_url = $popup_side_raw !== '' ? esc_url( $popup_side_raw ) : '';
		$has_popup_side = ( $popup_side_url !== '' );
		$label = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
		if ( $label === '' ) {
			$label = $title !== '' ? $title : $btn;
		}
		$required = ! empty( $field['required'] );
		$btn_id   = 'pab-popup-trigger-' . $field_id;

		$dialog_id = 'pab-popup-' . $field_id;
		$nested    = isset( $field['nested_fields'] ) && is_array( $field['nested_fields'] ) ? $field['nested_fields'] : [];
		$n_mode    = PAB_Data::sanitize_nested_price_mode( $field['nested_price_mode'] ?? 'per_field' );
		$n_uniform = ( 'uniform' === $n_mode );
		$pop_price = (float) ( $field['price'] ?? 0 );
		$pop_pt    = $field['price_type'] ?? 'flat';

		echo '<div class="pab-field-wrap pab-field-type-popup" data-index="' . esc_attr( (string) $list_index ) . '" data-field-id="' . esc_attr( $field_id ) . '" data-nested-price-mode="' . esc_attr( $n_mode ) . '" data-price="' . esc_attr( $n_uniform ? $pop_price : '0' ) . '" data-price-type="' . esc_attr( $n_uniform ? $pop_pt : 'flat' ) . '" data-choice-price-mode="">';
		echo '<label class="pab-field-label" for="' . esc_attr( $btn_id ) . '">';
		echo '<span class="pab-field-label__main">' . esc_html( $label );
		if ( $required ) {
			echo '<span class="required pab-required-star" aria-hidden="true">*</span>';
		}
		echo '</span></label>';
		echo '<div class="pab-popup-trigger">';
		echo '<button type="button" id="' . esc_attr( $btn_id ) . '" class="button pab-popup-open" aria-haspopup="dialog" aria-controls="' . esc_attr( $dialog_id ) . '">' . esc_html( $btn ) . '</button>';
		echo '</div>';

		// Div overlay (not <dialog>): Woodmart / stacked layouts often break native showModal().
		echo '<div id="' . esc_attr( $dialog_id ) . '" class="pab-popup-dialog" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $dialog_id ) . '-title" hidden>';
		echo '<div class="pab-popup-dialog__panel' . ( $has_popup_side ? ' pab-popup-dialog__panel--split' : '' ) . '">';
		if ( $has_popup_side ) {
			echo '<div class="pab-popup-dialog__media" style="' . esc_attr( 'background-image:url(' . $popup_side_url . ')' ) . '" aria-hidden="true"></div>';
		}
		echo '<div class="pab-popup-dialog__main">';
		$has_visible_title = ( $title !== '' );
		echo '<div class="pab-popup-header' . ( ! $has_visible_title ? ' pab-popup-header--close-end' : '' ) . '">';
		if ( $has_visible_title ) {
			echo '<h3 id="' . esc_attr( $dialog_id ) . '-title" class="pab-popup-title">' . esc_html( $title ) . '</h3>';
		} else {
			echo '<h3 id="' . esc_attr( $dialog_id ) . '-title" class="pab-popup-title screen-reader-text">' . esc_html( $btn ) . '</h3>';
		}
		echo '<button type="button" class="pab-popup-close" aria-label="' . esc_attr__( 'Close', 'pab' ) . '"></button>';
		echo '</div>';
		if ( $desc !== '' ) {
			echo '<div class="pab-popup-description">' . wp_kses_post( wpautop( $desc, true ) ) . '</div>';
		}
		echo '<div class="pab-popup-fields">';
		foreach ( $nested as $ni => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$input_name = 'pab_popup[' . $field_id . '][' . (int) $ni . ']';
			$file_name  = 'pab_popup_file[' . $field_id . '][' . (int) $ni . ']';
			$this->render_field_inner( $child, $input_name, $file_name, (int) $ni, true, $list_index, $field_id, $n_uniform, $pop_price, $pop_pt );
		}
		echo '</div>';
		// `.button.alt` for Woo; Woodmart primary look via CSS variables on `.pab-popup-done` (avoid `.single_add_to_cart_button` — cart ::before icon).
		echo '<p class="pab-popup-actions"><button type="button" class="button alt pab-popup-done">' . esc_html__( 'Done', 'pab' ) . '</button></p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * @param array<string,mixed> $field
	 */
	private function render_field_inner( array $field, string $input_name, string $file_input_name, int $data_index, bool $is_nested, int $popup_top_index = -1, string $popup_container_id = '', bool $popup_parent_uniform = false, float $popup_parent_price = 0.0, string $popup_parent_price_type = 'flat' ): void {
		$field_id   = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
		$type       = $field['type'] ?? 'text';
		$label      = $field['label'] ?? '';
		$required   = ! empty( $field['required'] );
		$price      = isset( $field['price'] ) ? (float) $field['price'] : 0;
		$price_type = $field['price_type'] ?? 'flat';
		$choice_mode = $field['choice_price_mode'] ?? 'per_option';
		$options    = $field['options'] ?? [];
		$req_attr   = $required ? 'required' : '';
		$placeholder = isset( $field['placeholder'] ) ? trim( (string) $field['placeholder'] ) : '';
		$ph_attr    = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';

		$is_choice = in_array( $type, self::choice_types(), true );

		$effective_price      = ( $is_nested && $popup_parent_uniform ) ? $popup_parent_price : $price;
		$effective_price_type = ( $is_nested && $popup_parent_uniform ) ? $popup_parent_price_type : $price_type;
		$choice_mode_effective = ( $is_nested && $popup_parent_uniform && $is_choice ) ? 'uniform' : $choice_mode;

		$uniform_price = $is_choice && 'uniform' === $choice_mode_effective;
		$per_option    = $is_choice && 'per_option' === $choice_mode_effective;

		$data_price_attr  = ( $is_nested && $popup_parent_uniform ) ? $popup_parent_price : $price;
		$data_pt_attr     = ( $is_nested && $popup_parent_uniform ) ? $popup_parent_price_type : $price_type;
		$data_cmode_attr  = $is_choice ? $choice_mode_effective : '';

		$swatch_allow_custom = ( 'image_swatch' === $type && ! empty( $field['swatch_allow_custom_upload'] ) );

		$swatch_size_class  = '';
		$swatch_shape_class = '';
		if ( 'image_swatch' === $type ) {
			$sz = PAB_Data::sanitize_image_swatch_size( $field['image_swatch_size'] ?? 'medium' );
			$swatch_size_class = ' pab-swatch-size--' . esc_attr( $sz );
			$sh = PAB_Data::sanitize_image_swatch_shape( $field['image_swatch_shape'] ?? 'square' );
			$swatch_shape_class = ' pab-swatch-shape--' . esc_attr( $sh );
		}

		$wrap_class = 'pab-field-wrap pab-field-type-' . esc_attr( $type ) . $swatch_size_class . $swatch_shape_class;
		if ( $is_nested ) {
			$wrap_class .= ' pab-field-wrap--nested';
		}

		$ctx_attr = '';
		if ( $is_nested && $popup_top_index >= 0 && $popup_container_id !== '' ) {
			$ctx_attr = ' data-pab-popup-top-index="' . esc_attr( (string) $popup_top_index ) . '" data-pab-popup-container-id="' . esc_attr( $popup_container_id ) . '"';
		}

		echo '<div class="' . esc_attr( $wrap_class ) . '" data-index="' . esc_attr( (string) $data_index ) . '" data-field-id="' . esc_attr( $field_id ) . '" data-price="' . esc_attr( $data_price_attr ) . '" data-price-type="' . esc_attr( $data_pt_attr ) . '" data-choice-price-mode="' . esc_attr( $data_cmode_attr ) . '"' . ( $swatch_allow_custom ? ' data-pab-swatch-customer-upload="1"' : '' ) . $ctx_attr . '>';

		echo '<label class="pab-field-label"><span class="pab-field-label__main">';
		echo esc_html( $label );
		if ( $required ) {
			echo '<span class="required pab-required-star" aria-hidden="true">*</span>';
		}
		echo '</span>';
		if ( 'image_swatch' === $type ) {
			echo '<span class="pab-opt-price pab-image-swatch-label-price" hidden></span>';
		} elseif ( $uniform_price ) {
			echo wp_kses_post( $this->uniform_price_hint_html( $effective_price, $effective_price_type ) );
		} elseif ( $per_option && $is_choice ) {
			echo wp_kses_post( $this->per_option_field_label_prices_html( $options, $effective_price_type ) );
		} elseif ( ! $is_choice && $effective_price > 0 ) {
			echo wp_kses_post( $this->uniform_price_hint_html( $effective_price, $effective_price_type ) );
		}
		if ( in_array( $type, [ 'file', 'image_upload' ], true ) || $swatch_allow_custom ) {
			echo '<button type="button" class="pab-file-upload-clear">' . esc_html__( 'Remove', 'pab' ) . '</button>';
		}
		echo '</label>';

		$iname = $input_name;
		switch ( $type ) {
			case 'text':
				echo '<input type="text" name="' . esc_attr( $iname ) . '" class="pab-field-input input-text" ' . $req_attr . $ph_attr . ' />';
				break;

			case 'textarea':
				echo '<textarea name="' . esc_attr( $iname ) . '" class="pab-field-input input-text" ' . $req_attr . $ph_attr . '></textarea>';
				break;

			case 'number':
				echo '<input type="number" min="0" name="' . esc_attr( $iname ) . '" class="pab-field-input input-text" ' . $req_attr . $ph_attr . ' />';
				break;

			case 'select':
				echo '<select name="' . esc_attr( $iname ) . '" class="pab-field-input" ' . $req_attr . '>';
				echo '<option value="">' . esc_html__( '— Select —', 'pab' ) . '</option>';
				foreach ( $options as $opt ) {
					$opt_label = $opt['label'] ?? '';
					$opt_price = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
					$data_price = $uniform_price ? 0 : $opt_price;
					$price_text = ( $per_option && $opt_price > 0 ) ? ' (+' . wc_format_decimal( $opt_price, wc_get_price_decimals() ) . ')' : '';
					echo '<option value="' . esc_attr( $opt_label ) . '" data-option-price="' . esc_attr( $data_price ) . '">' . esc_html( $opt_label . $price_text ) . '</option>';
				}
				echo '</select>';
				break;

			case 'radio':
				foreach ( $options as $oi => $opt ) {
					$opt_label = $opt['label'] ?? '';
					$opt_price = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
					$data_price = $uniform_price ? 0 : $opt_price;
					echo '<label class="pab-radio-label">';
					echo '<input type="radio" name="' . esc_attr( $iname ) . '" value="' . esc_attr( $opt_label ) . '" class="pab-field-radio" data-option-price="' . esc_attr( $data_price ) . '" ' . $req_attr . ' />';
					echo esc_html( $opt_label );
					if ( $per_option && $opt_price > 0 ) {
						echo ' <span class="pab-opt-price">(+' . wc_price( $opt_price ) . ')</span>';
					}
					echo '</label>';
				}
				break;

			case 'checkbox':
				echo '<label class="pab-checkbox-label">';
				echo '<input type="checkbox" name="' . esc_attr( $iname ) . '" value="1" class="pab-field-checkbox" />';
				echo '</label>';
				break;

			case 'file':
				$this->render_file_upload_field( $file_input_name, 'file', $req_attr );
				break;

			case 'image_upload':
				$this->render_file_upload_field( $file_input_name, 'image_upload', $req_attr );
				break;

			case 'image_swatch':
				$custom_label = isset( $field['swatch_custom_label'] ) ? trim( (string) $field['swatch_custom_label'] ) : '';
				if ( $custom_label === '' ) {
					$custom_label = __( 'Upload your own', 'pab' );
				}
				$custom_opt_price = $uniform_price ? 0 : (float) ( $field['swatch_custom_price'] ?? 0 );
				$custom_price_lbl = ( $per_option && $custom_opt_price > 0 ) ? ' (+' . wc_format_decimal( $custom_opt_price, wc_get_price_decimals() ) . ')' : '';

				$swatch_any_choice_label = false;
				foreach ( $options as $opt ) {
					if ( isset( $opt['label'] ) && trim( (string) $opt['label'] ) !== '' ) {
						$swatch_any_choice_label = true;
						break;
					}
				}

				echo '<div class="pab-image-swatch-wrap">';
				foreach ( $options as $oi => $opt ) {
					$opt_label   = $opt['label'] ?? '';
					$opt_image   = $opt['image'] ?? '';
					$opt_price   = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
					$data_price  = $uniform_price ? 0 : $opt_price;
					$price_label = ( $per_option && $opt_price > 0 ) ? ' (+' . wc_format_decimal( $opt_price, wc_get_price_decimals() ) . ')' : '';
					$label_trim    = trim( (string) $opt_label );
					$img_alt       = $label_trim !== '' ? $label_trim : '';
					echo '<label class="pab-swatch-item" title="' . esc_attr( $opt_label . $price_label ) . '">';
					echo '<input type="radio" name="' . esc_attr( $iname ) . '" value="' . esc_attr( $opt_label ) . '" class="pab-swatch-radio" data-option-price="' . esc_attr( $data_price ) . '" ' . $req_attr . ' />';
					if ( $opt_image ) {
						echo '<img src="' . esc_url( $opt_image ) . '" alt="' . esc_attr( $img_alt ) . '" class="pab-swatch-img" />';
					}
					if ( $label_trim !== '' ) {
						echo '<span class="pab-swatch-label">' . esc_html( $opt_label ) . '</span>';
					}
					echo '</label>';
				}
				if ( $swatch_allow_custom ) {
					echo '<label class="pab-swatch-item pab-swatch-item--custom" title="' . esc_attr( $custom_label . $custom_price_lbl ) . '">';
					echo '<input type="radio" name="' . esc_attr( $iname ) . '" value="' . esc_attr( PAB_Data::SWATCH_CUSTOM_POST_VALUE ) . '" class="pab-swatch-radio" data-option-price="' . esc_attr( $custom_opt_price ) . '" data-pab-custom-upload="1" ' . $req_attr . ' />';
					echo '<span class="pab-swatch-custom-cell">' . esc_html( $custom_label ) . '</span>';
					if ( $swatch_any_choice_label ) {
						echo '<span class="pab-swatch-label pab-swatch-label--custom-rail" aria-hidden="true">&nbsp;</span>';
					}
					echo '</label>';
				}
				echo '</div>';
				if ( $swatch_allow_custom ) {
					echo '<div class="pab-swatch-custom-upload pab-is-hidden">';
					echo '<p class="pab-swatch-custom-upload__hint">' . esc_html__( 'Upload your image for this option.', 'pab' ) . '</p>';
					$this->render_file_upload_field( $file_input_name, 'image_upload', '', 'pab-file-upload--swatch-addon' );
					echo '</div>';
				}
				break;

			case 'text_swatch':
				echo '<div class="pab-text-swatch-wrap">';
				foreach ( $options as $oi => $opt ) {
					$opt_label  = $opt['label'] ?? '';
					$opt_price  = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
					$data_price = $uniform_price ? 0 : $opt_price;
					echo '<label class="pab-text-swatch-item">';
					echo '<input type="radio" name="' . esc_attr( $iname ) . '" value="' . esc_attr( $opt_label ) . '" class="pab-swatch-radio" data-option-price="' . esc_attr( $data_price ) . '" ' . $req_attr . ' />';
					echo '<span class="pab-text-swatch-btn">' . esc_html( $opt_label );
					if ( $per_option && $opt_price > 0 ) {
						echo ' <small>(+' . wc_price( $opt_price ) . ')</small>';
					}
					echo '</span>';
					echo '</label>';
				}
				echo '</div>';
				break;
		}

		echo '</div>';
	}
}
