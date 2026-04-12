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
	 * @param string|int $index Field index in form.
	 * @param string     $extra_wrap_class Optional extra class(es) on the upload root (e.g. swatch variant).
	 */
	private function render_file_upload_field( $index, string $type, string $req_attr, string $extra_wrap_class = '' ): void {
		$is_image = ( 'image_upload' === $type );
		$accept   = $is_image ? ' accept="image/*"' : '';
		$inp_cls  = 'pab-field-file pab-file-upload-input' . ( $is_image ? ' pab-file-upload-input--image' : '' );
		$wrap_cls = 'pab-file-upload' . ( $is_image ? ' pab-file-upload--image' : '' );
		if ( $extra_wrap_class !== '' ) {
			$wrap_cls .= ' ' . sanitize_html_class( $extra_wrap_class, '' );
		}

		echo '<div class="' . esc_attr( $wrap_cls ) . '">';
		echo '<div class="pab-file-upload-drop">';
		echo '<input type="file" name="pab_addon_file[' . esc_attr( (string) $index ) . ']" class="' . esc_attr( $inp_cls ) . '" ' . $req_attr . $accept . ' />';
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
		$field_id   = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
		$type       = $field['type'] ?? 'text';
		$label      = $field['label'] ?? '';
		$required   = ! empty( $field['required'] );
		$price      = isset( $field['price'] ) ? (float) $field['price'] : 0;
		$price_type = $field['price_type'] ?? 'flat';
		$choice_mode = $field['choice_price_mode'] ?? 'per_option';
		$options    = $field['options'] ?? [];
		$req_attr   = $required ? 'required' : '';

		$is_choice     = in_array( $type, self::choice_types(), true );
		$uniform_price = $is_choice && 'uniform' === $choice_mode;
		$per_option    = $is_choice && 'per_option' === $choice_mode;

		$swatch_allow_custom = ( 'image_swatch' === $type && ! empty( $field['swatch_allow_custom_upload'] ) );

		/* Per-option custom-upload price: shown after the field label when that swatch is selected (not in the upload dropzone). */
		$swatch_size_class = '';
		if ( 'image_swatch' === $type ) {
			$sz = PAB_Data::sanitize_image_swatch_size( $field['image_swatch_size'] ?? 'medium' );
			$swatch_size_class = ' pab-swatch-size--' . esc_attr( $sz );
		}

		echo '<div class="pab-field-wrap pab-field-type-' . esc_attr( $type ) . $swatch_size_class . '" data-index="' . esc_attr( $index ) . '" data-field-id="' . esc_attr( $field_id ) . '" data-price="' . esc_attr( $price ) . '" data-price-type="' . esc_attr( $price_type ) . '" data-choice-price-mode="' . esc_attr( $is_choice ? $choice_mode : '' ) . '"' . ( $swatch_allow_custom ? ' data-pab-swatch-customer-upload="1"' : '' ) . '>';

		echo '<label class="pab-field-label"><span class="pab-field-label__main">';
		echo esc_html( $label );
		if ( $required ) {
			echo '<span class="required pab-required-star" aria-hidden="true">*</span>';
		}
		echo '</span>';
		if ( 'image_swatch' === $type ) {
			/* Filled by JS: selected option + upload state (no static range / duplicate hints). */
			echo '<span class="pab-opt-price pab-image-swatch-label-price" hidden></span>';
		} elseif ( $uniform_price ) {
			echo wp_kses_post( $this->uniform_price_hint_html( $price, $price_type ) );
		} elseif ( $per_option && $is_choice ) {
			echo wp_kses_post( $this->per_option_field_label_prices_html( $options, $price_type ) );
		} elseif ( ! $is_choice && $price > 0 ) {
			echo wp_kses_post( $this->uniform_price_hint_html( $price, $price_type ) );
		}
		if ( in_array( $type, [ 'file', 'image_upload' ], true ) || $swatch_allow_custom ) {
			echo '<button type="button" class="pab-file-upload-clear">' . esc_html__( 'Remove', 'pab' ) . '</button>';
		}
		echo '</label>';

		switch ( $type ) {
			case 'text':
				echo '<input type="text" name="pab_addon[' . esc_attr( $index ) . ']" class="pab-field-input input-text" ' . $req_attr . ' />';
				break;

			case 'textarea':
				echo '<textarea name="pab_addon[' . esc_attr( $index ) . ']" class="pab-field-input input-text" ' . $req_attr . '></textarea>';
				break;

			case 'number':
				echo '<input type="number" min="0" name="pab_addon[' . esc_attr( $index ) . ']" class="pab-field-input input-text" ' . $req_attr . ' />';
				break;

			case 'select':
				echo '<select name="pab_addon[' . esc_attr( $index ) . ']" class="pab-field-input" ' . $req_attr . '>';
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
					echo '<input type="radio" name="pab_addon[' . esc_attr( $index ) . ']" value="' . esc_attr( $opt_label ) . '" class="pab-field-radio" data-option-price="' . esc_attr( $data_price ) . '" ' . $req_attr . ' />';
					echo esc_html( $opt_label );
					if ( $per_option && $opt_price > 0 ) {
						echo ' <span class="pab-opt-price">(+' . wc_price( $opt_price ) . ')</span>';
					}
					echo '</label>';
				}
				break;

			case 'checkbox':
				echo '<label class="pab-checkbox-label">';
				echo '<input type="checkbox" name="pab_addon[' . esc_attr( $index ) . ']" value="1" class="pab-field-checkbox" />';
				echo '</label>';
				break;

			case 'file':
				$this->render_file_upload_field( $index, 'file', $req_attr );
				break;

			case 'image_upload':
				$this->render_file_upload_field( $index, 'image_upload', $req_attr );
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
					echo '<input type="radio" name="pab_addon[' . esc_attr( $index ) . ']" value="' . esc_attr( $opt_label ) . '" class="pab-swatch-radio" data-option-price="' . esc_attr( $data_price ) . '" ' . $req_attr . ' />';
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
					echo '<input type="radio" name="pab_addon[' . esc_attr( $index ) . ']" value="' . esc_attr( PAB_Data::SWATCH_CUSTOM_POST_VALUE ) . '" class="pab-swatch-radio" data-option-price="' . esc_attr( $custom_opt_price ) . '" data-pab-custom-upload="1" ' . $req_attr . ' />';
					echo '<span class="pab-swatch-custom-cell">' . esc_html( $custom_label ) . '</span>';
					/* Invisible rail only when at least one preset shows a caption row (keeps custom tile height aligned). */
					if ( $swatch_any_choice_label ) {
						echo '<span class="pab-swatch-label pab-swatch-label--custom-rail" aria-hidden="true">&nbsp;</span>';
					}
					echo '</label>';
				}
				echo '</div>';
				if ( $swatch_allow_custom ) {
					echo '<div class="pab-swatch-custom-upload pab-is-hidden">';
					echo '<p class="pab-swatch-custom-upload__hint">' . esc_html__( 'Upload your image for this option.', 'pab' ) . '</p>';
					$this->render_file_upload_field( $index, 'image_upload', '', 'pab-file-upload--swatch-addon' );
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
					echo '<input type="radio" name="pab_addon[' . esc_attr( $index ) . ']" value="' . esc_attr( $opt_label ) . '" class="pab-swatch-radio" data-option-price="' . esc_attr( $data_price ) . '" ' . $req_attr . ' />';
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

		echo '</div>'; // .pab-field-wrap
	}
}
