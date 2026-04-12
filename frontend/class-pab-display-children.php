<?php
defined( 'ABSPATH' ) || exit;

class PAB_Display_Children {

	private const LAYOUT_DEFAULT      = 'default';
	private const LAYOUT_IMAGE_SWATCH = 'image_swatch';
	private const LAYOUT_PRODUCT_CARD = 'product_card';

	/** @var int */
	private $product_id;

	/** @var array<int, array<string, mixed>> */
	private $children;

	/** @var string */
	private $layout;

	public function __construct( $product_id, $children ) {
		$this->product_id = (int) $product_id;
		$this->children   = is_array( $children ) ? $children : [];
		$this->layout     = self::sanitize_layout( get_post_meta( $this->product_id, '_pab_child_layout', true ) );
	}

	/**
	 * @param mixed $value Meta or POST value.
	 */
	public static function sanitize_layout( $value ): string {
		$slug = is_string( $value ) ? sanitize_key( $value ) : '';
		if ( self::LAYOUT_IMAGE_SWATCH === $slug || self::LAYOUT_PRODUCT_CARD === $slug ) {
			return $slug;
		}
		return self::LAYOUT_DEFAULT;
	}

	public function render() {
		if ( empty( $this->children ) ) {
			return;
		}

		$section_class = 'pab-children-section pab-children-layout--' . esc_attr( $this->layout );

		echo '<div class="' . $section_class . '">';

		if ( self::LAYOUT_IMAGE_SWATCH === $this->layout ) {
			echo '<h4 class="pab-section-heading">' . esc_html__( 'Choose an extra', 'pab' ) . '</h4>';
			$any_required = false;
			foreach ( $this->children as $ch ) {
				if ( ! empty( $ch['required'] ) ) {
					$any_required = true;
					break;
				}
			}
			$initial = $this->get_initial_swatch_index();
			$group_attrs = 'class="pab-child-swatch-group" role="radiogroup" aria-label="' . esc_attr__( 'Choose an extra', 'pab' ) . '"';
			if ( $any_required ) {
				$group_attrs .= ' data-pab-swatch-required="1"';
			}
			echo '<div ' . $group_attrs . '>';
			foreach ( $this->children as $index => $child ) {
				$this->render_child( (int) $index, $child, $initial );
			}
			echo '</div>';
			echo '</div>';
			return;
		}

		echo '<h4 class="pab-section-heading">' . esc_html__( 'Add Extras', 'pab' ) . '</h4>';
		foreach ( $this->children as $index => $child ) {
			$this->render_child( (int) $index, $child );
		}

		echo '</div>';
	}

	/**
	 * First “always included” child, else first with min_qty &gt; 0; otherwise none (optional group).
	 *
	 * @return int|null Index or null when nothing is pre-selected.
	 */
	private function get_initial_swatch_index(): ?int {
		foreach ( $this->children as $i => $child ) {
			$min = (int) ( $child['min_qty'] ?? 0 );
			$max = (int) ( $child['max_qty'] ?? 1 );
			if ( $max < 1 ) {
				$max = 1;
			}
			if ( $max <= 1 && $min >= 1 ) {
				return (int) $i;
			}
		}
		foreach ( $this->children as $i => $child ) {
			$min = (int) ( $child['min_qty'] ?? 0 );
			if ( $min > 0 ) {
				return (int) $i;
			}
		}
		return null;
	}

	/**
	 * @param int                           $index                 Zero-based row index (must match cart/JS).
	 * @param array<string, mixed>          $child                 Saved child product config.
	 * @param int|null|false                $swatch_selected_index `false` = not exclusive swatch flow; `null|int` = selected row for image_swatch.
	 */
	private function render_child( $index, $child, $swatch_selected_index = false ) {
		$ctx = $this->build_child_context( $index, $child, $swatch_selected_index );
		if ( null === $ctx ) {
			return;
		}

		switch ( $this->layout ) {
			case self::LAYOUT_IMAGE_SWATCH:
				$this->render_layout_image_swatch( $ctx );
				break;
			case self::LAYOUT_PRODUCT_CARD:
				$this->render_layout_product_card( $ctx );
				break;
			default:
				$this->render_layout_default( $ctx );
				break;
		}
	}

	/**
	 * @param array<string, mixed> $child Saved child product config.
	 * @param int|null|false       $swatch_selected_index See render_child().
	 * @return array<string, mixed>|null
	 */
	private function build_child_context( $index, $child, $swatch_selected_index = false ) {
		$pid          = absint( $child['product_id'] ?? 0 );
		$is_variable  = ! empty( $child['is_variable'] );
		$min_qty      = (int) ( $child['min_qty'] ?? 0 );
		$max_qty      = (int) ( $child['max_qty'] ?? 1 );
		if ( $max_qty < 1 ) {
			$max_qty = 1;
		}
		$required     = ! empty( $child['required'] );
		$override     = $child['override_price'] ?? '';
		$allowed_vars = (array) ( $child['allowed_variations'] ?? [] );

		if ( ! $pid ) {
			return null;
		}

		$product = wc_get_product( $pid );
		if ( ! $product ) {
			return null;
		}

		$display_price = $override !== '' ? (float) $override : (float) $product->get_price();
		$req_star      = $required ? ' <span class="required">*</span>' : '';

		$short_raw = $product->get_short_description();
		$excerpt   = $short_raw
			? wp_trim_words( wp_strip_all_tags( wp_specialchars_decode( $short_raw, ENT_QUOTES | ENT_HTML5 ) ), 10, '…' )
			: '';

		$fixed_included = ( $max_qty <= 1 && $min_qty >= 1 );
		$use_toggle     = ( $max_qty <= 1 && $min_qty < 1 );
		$use_stepper    = ( $max_qty > 1 );

		if ( $fixed_included ) {
			$initial_qty = 1;
		} elseif ( $use_toggle ) {
			$initial_qty = 0;
		} else {
			$initial_qty = max( 0, $min_qty );
		}

		$wrap_classes = [ 'pab-child-wrap', 'pab-child--layout-' . $this->layout ];
		if ( $initial_qty > 0 || $fixed_included ) {
			$wrap_classes[] = 'pab-child-selected';
		}
		if ( ( $use_toggle || $use_stepper ) && $initial_qty > 0 ) {
			$wrap_classes[] = 'pab-child--extras-confirmed';
		}

		$ctx = [
			'index'            => $index,
			'child'            => $child,
			'pid'              => $pid,
			'product'          => $product,
			'is_variable'      => $is_variable,
			'min_qty'          => $min_qty,
			'max_qty'          => $max_qty,
			'required'         => $required,
			'req_star'         => $req_star,
			'display_price'    => $display_price,
			'override'         => $override,
			'allowed_vars'     => $allowed_vars,
			'excerpt'          => $excerpt,
			'fixed_included'   => $fixed_included,
			'use_toggle'       => $use_toggle,
			'use_stepper'      => $use_stepper,
			'initial_qty'      => $initial_qty,
			'wrap_classes'     => $wrap_classes,
		];

		// Exclusive storefront swatch: exactly one optional line item, chosen by radio (qty 0 or 1 only).
		if ( self::LAYOUT_IMAGE_SWATCH === $this->layout && false !== $swatch_selected_index ) {
			$sel               = null === $swatch_selected_index ? -1 : (int) $swatch_selected_index;
			$initial_exclusive = ( (int) $index === $sel ) ? 1 : 0;
			$ctx['initial_qty']  = $initial_exclusive;
			$ctx['wrap_classes'] = [ 'pab-child-wrap', 'pab-child--layout-image_swatch', 'pab-child--swatch-exclusive' ];
			if ( $initial_exclusive > 0 ) {
				$ctx['wrap_classes'][] = 'pab-child-selected';
			}
			// When no variation is chosen yet, JS restores this formatted snippet on the price line.
			$ctx['swatch_price_fallback'] = '';
			if ( $is_variable && $product->is_type( 'variable' ) ) {
				$min_raw = (float) $product->get_variation_price( 'min', true );
				if ( $min_raw > 0 ) {
					$ctx['swatch_price_fallback'] = '+' . wp_strip_all_tags( wc_price( $min_raw ) );
				}
			}
		}

		return $ctx;
	}

	/**
	 * @param array<string, mixed> $c Context from build_child_context.
	 */
	private function render_layout_default( array $c ) {
		$this->render_child_wrap_open( $c );

		echo '<div class="pab-child-card">';

		echo '<div class="pab-child-media">';
		$this->output_product_thumb( $c['product'] );
		echo '</div>';

		echo '<div class="pab-child-main">';
		$this->output_child_text_column( $c );

		echo '<div class="pab-child-actions">';
		$this->output_price_row( $c );
		$this->output_quantity_controls( $c );
		echo '</div>'; // .pab-child-actions

		echo '</div>'; // .pab-child-main
		echo '</div>'; // .pab-child-card

		$this->render_child_wrap_close();
	}

	/**
	 * @param array<string, mixed> $c Context.
	 */
	private function render_layout_image_swatch( array $c ) {
		$this->render_child_wrap_open( $c );

		echo '<div class="pab-child-card pab-child-card--swatch pab-child-card--swatch-only">';

		$checked = $c['initial_qty'] > 0 ? ' checked' : '';
		$tip     = $c['product']->get_name();
		if ( $c['required'] ) {
			$tip .= ' *';
		}
		echo '<label class="pab-child-swatch-hitarea" title="' . esc_attr( $tip ) . '">';
		echo '<input type="radio" class="pab-child-swatch-radio" name="pab_child_swatch_choice" value="' . esc_attr( (string) $c['index'] ) . '"' . $checked . ' />';
		echo '<span class="pab-child-media pab-child-media--swatch" aria-hidden="true">';
		$this->output_product_thumb( $c['product'] );
		echo '</span>';
		$this->output_swatch_price_row( $c );
		echo '<span class="pab-sr-only">' . esc_html( $c['product']->get_name() ) . wp_kses_post( $c['req_star'] ) . '</span>';
		echo '</label>';

		echo '<div class="pab-child-swatch-meta">';
		$this->output_variation_block( $c );
		echo '</div>';

		$this->output_swatch_qty_hidden( $c );

		echo '</div>'; // .pab-child-card
		$this->render_child_wrap_close();
	}

	/**
	 * Hidden qty for exclusive swatch layout (0 or 1; synced from radio in JS).
	 *
	 * @param array<string, mixed> $c Context.
	 */
	private function output_swatch_qty_hidden( array $c ) {
		$index   = $c['index'];
		$initial = (int) $c['initial_qty'];
		echo '<input type="hidden" name="pab_child[' . esc_attr( (string) $index ) . '][qty]" class="pab-child-qty" value="' . esc_attr( (string) $initial ) . '" />';
	}

	/**
	 * Extra price under swatch image (visible; name stays sr-only in hitarea).
	 *
	 * @param array<string, mixed> $c Context.
	 */
	private function output_swatch_price_row( array $c ) {
		echo '<span class="pab-child-swatch-price-row">';
		if ( $c['is_variable'] && $c['product']->is_type( 'variable' ) ) {
			$min_raw = (float) $c['product']->get_variation_price( 'min', true );
			if ( $min_raw > 0 ) {
				echo '<span class="pab-child-price">+' . wp_kses_post( wc_price( $min_raw ) ) . '</span>';
			} else {
				echo '<span class="pab-child-price"></span>';
			}
		} elseif ( $c['display_price'] > 0 ) {
			echo '<span class="pab-child-price">+' . wp_kses_post( wc_price( $c['display_price'] ) ) . '</span>';
		} else {
			echo '<span class="pab-child-price"></span>';
		}
		echo '</span>';
	}

	/**
	 * @param array<string, mixed> $c Context.
	 */
	private function render_layout_product_card( array $c ) {
		$this->render_child_wrap_open( $c );

		echo '<div class="pab-child-card pab-child-card--product">';

		echo '<div class="pab-child-media pab-child-media--card">';
		$this->output_product_thumb( $c['product'] );
		echo '</div>';

		echo '<div class="pab-child-card-body">';
		echo '<div class="pab-child-title-row">';
		echo '<span class="pab-child-name">' . esc_html( $c['product']->get_name() ) . wp_kses_post( $c['req_star'] ) . '</span>';
		echo '</div>';

		$this->output_price_row( $c );

		if ( $c['excerpt'] ) {
			echo '<p class="pab-child-excerpt">' . esc_html( $c['excerpt'] ) . '</p>';
		}

		$this->output_variation_block( $c );

		echo '<div class="pab-child-card-footer">';
		$this->output_quantity_controls( $c );
		echo '</div>';

		echo '</div>'; // .pab-child-card-body
		echo '</div>'; // .pab-child-card
		$this->render_child_wrap_close();
	}

	/**
	 * @param array<string, mixed> $c Context.
	 */
	private function render_child_wrap_open( array $c ) {
		$attrs = [
			'class'           => implode( ' ', $c['wrap_classes'] ),
			'data-index'      => $c['index'],
			'data-product-id' => $c['pid'],
			'data-price'      => $c['display_price'],
			'data-is-variable' => $c['is_variable'] ? '1' : '0',
			'data-min-qty'    => $c['min_qty'],
			'data-max-qty'    => $c['max_qty'],
		];
		if ( ! empty( $c['swatch_price_fallback'] ) ) {
			$attrs['data-pab-swatch-price-fallback'] = $c['swatch_price_fallback'];
		}
		$parts = [];
		foreach ( $attrs as $k => $v ) {
			$parts[] = sprintf( '%s="%s"', esc_attr( $k ), esc_attr( (string) $v ) );
		}
		echo '<div ' . implode( ' ', $parts ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped.
	}

	private function render_child_wrap_close() {
		echo '</div>';
	}

	/**
	 * @param WC_Product $product Product.
	 */
	private function output_product_thumb( $product ) {
		$thumb = $product->get_image(
			'woocommerce_thumbnail',
			[
				'class' => 'pab-child-thumb-img',
				'alt'   => esc_attr( $product->get_name() ),
			]
		);
		if ( ! $thumb && function_exists( 'wc_placeholder_img' ) ) {
			$thumb = wc_placeholder_img( 'woocommerce_thumbnail', [ 'class' => 'pab-child-thumb-img' ] );
		}
		echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string, mixed> $c Context.
	 */
	private function output_child_text_column( array $c ) {
		echo '<div class="pab-child-text">';
		echo '<div class="pab-child-title-row">';
		echo '<span class="pab-child-name">' . esc_html( $c['product']->get_name() ) . wp_kses_post( $c['req_star'] ) . '</span>';
		echo '</div>';

		if ( $c['excerpt'] ) {
			echo '<p class="pab-child-excerpt">' . esc_html( $c['excerpt'] ) . '</p>';
		}

		$this->output_variation_block( $c );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $c Context.
	 */
	private function output_variation_block( array $c ) {
		$index    = $c['index'];
		$product  = $c['product'];
		$pid      = $c['pid'];
		$is_variable = $c['is_variable'];
		$allowed_vars = $c['allowed_vars'];
		$override = $c['override'];

		if ( $is_variable && $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();
			echo '<div class="pab-child-variation-wrap">';
			echo '<label class="pab-child-variation-label"><span class="pab-sr-only">' . esc_html__( 'Options', 'pab' ) . '</span>';
			echo '<select name="pab_child[' . esc_attr( (string) $index ) . '][variation_id]" class="pab-child-variation-select">';
			echo '<option value="">' . esc_html__( 'Choose options…', 'pab' ) . '</option>';
			foreach ( $variations as $var ) {
				$var_id = $var['variation_id'];
				if ( ! empty( $allowed_vars ) && ! in_array( $var_id, $allowed_vars, true ) ) {
					continue;
				}
				$var_price  = $override !== '' ? (float) $override : (float) $var['display_price'];
				$attr_label = implode( ' / ', array_filter( array_values( $var['attributes'] ) ) );
				$price_text = $var_price > 0 ? ' (+' . wc_format_decimal( $var_price, wc_get_price_decimals() ) . ')' : '';
				echo '<option value="' . esc_attr( (string) $var_id ) . '" data-price="' . esc_attr( (string) $var_price ) . '">';
				echo esc_html( ( $attr_label ? $attr_label : '#' . $var_id ) . $price_text );
				echo '</option>';
			}
			echo '</select>';
			echo '</label>';
			echo '</div>';

			echo '<input type="hidden" name="pab_child[' . esc_attr( (string) $index ) . '][product_id]" value="' . esc_attr( (string) $pid ) . '" />';
		} else {
			echo '<input type="hidden" name="pab_child[' . esc_attr( (string) $index ) . '][product_id]" value="' . esc_attr( (string) $pid ) . '" />';
		}
	}

	/**
	 * @param array<string, mixed> $c Context.
	 */
	private function output_price_row( array $c ) {
		echo '<div class="pab-child-price-row">';
		if ( $c['display_price'] > 0 && ! $c['is_variable'] ) {
			echo '<span class="pab-child-price">+' . wp_kses_post( wc_price( $c['display_price'] ) ) . '</span>';
		} elseif ( $c['is_variable'] ) {
			echo '<span class="pab-child-price" style="display:none;" aria-hidden="true"></span>';
		}
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $c Context.
	 */
	private function output_quantity_controls( array $c ) {
		$index           = $c['index'];
		$product         = $c['product'];
		$min_qty         = $c['min_qty'];
		$max_qty         = $c['max_qty'];
		$initial_qty     = $c['initial_qty'];
		$fixed_included  = $c['fixed_included'];
		$use_toggle      = $c['use_toggle'];
		$use_stepper     = $c['use_stepper'];

		if ( $fixed_included ) {
			echo '<div class="pab-child-control">';
			echo '<span class="pab-child-included">' . esc_html__( 'Included', 'pab' ) . '</span>';
			$qty_hidden_attrs = 'name="pab_child[' . esc_attr( (string) $index ) . '][qty]" class="pab-child-qty input-text qty text" '
				. 'min="' . esc_attr( (string) $min_qty ) . '" '
				. 'max="' . esc_attr( (string) $max_qty ) . '" '
				. 'step="1" '
				. 'value="' . esc_attr( (string) $initial_qty ) . '" ';
			echo '<input type="hidden" ' . $qty_hidden_attrs . '/>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			return;
		}

		if ( ! $use_toggle && ! $use_stepper ) {
			return;
		}

		$qty_id    = 'pab-child-qty-' . (int) $index;
		$qty_label = sprintf(
			/* translators: %s: product name */
			esc_html__( '%s quantity', 'woocommerce' ),
			$product->get_name()
		);
		$add_class = 'single_add_to_cart_button button alt pab-child-add-btn';
		if ( function_exists( 'wc_wp_theme_get_element_class_name' ) ) {
			$wp_btn = wc_wp_theme_get_element_class_name( 'button' );
			if ( $wp_btn ) {
				$add_class .= ' ' . $wp_btn;
			}
		}

		echo '<div class="pab-child-control pab-child-control--buttons wd-pab-child-actions">';
		echo '<div class="quantity pab-child-quantity-wrap">';
		echo '<input type="button" value="-" class="minus btn" aria-label="' . esc_attr__( 'Decrease quantity', 'woodmart' ) . '" />';
		echo '<label class="screen-reader-text" for="' . esc_attr( $qty_id ) . '">' . esc_html( $qty_label ) . '</label>';
		echo '<input type="number" id="' . esc_attr( $qty_id ) . '" name="pab_child[' . esc_attr( (string) $index ) . '][qty]" class="pab-child-qty input-text qty text" ';
		echo 'min="' . esc_attr( (string) $min_qty ) . '" max="' . esc_attr( (string) $max_qty ) . '" step="1" ';
		echo 'value="' . esc_attr( (string) $initial_qty ) . '" inputmode="numeric" pattern="[0-9]*" ';
		echo 'aria-label="' . esc_attr__( 'Product quantity', 'woocommerce' ) . '" />';
		echo '<input type="button" value="+" class="plus btn" aria-label="' . esc_attr__( 'Increase quantity', 'woodmart' ) . '" />';
		echo '</div>';

		echo '<button type="button" class="' . esc_attr( $add_class ) . '">' . esc_html__( 'Add', 'pab' ) . '</button>';

		echo '<span class="wd-action-btn wd-style-icon pab-child-remove-wrap">';
		echo '<button type="button" class="button pab-child-remove-btn" aria-label="' . esc_attr__( 'Remove', 'pab' ) . '">';
		echo '<svg class="pab-child-remove-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>';
		echo '</button></span>';

		echo '</div>';
	}
}
