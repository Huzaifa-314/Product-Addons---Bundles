<?php
defined( 'ABSPATH' ) || exit;

class PAB_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_fields' ], 10 );
	}

	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		global $post;
		$product_id = $post->ID;

		$addon_fields      = PAB_Group_Resolver::resolve_addon_fields( $product_id );
		$child_products    = PAB_Data::decode_json_meta( $product_id, '_child_products' );
		$conditional_rules = PAB_Data::normalize_conditional_rules( PAB_Data::decode_json_meta( $product_id, '_conditional_rules' ), $addon_fields );
		$pab_settings      = get_option( 'pab_settings', [] );

		if ( ! is_array( $addon_fields ) ) {
			$addon_fields = [];
		}
		if ( ! is_array( $child_products ) ) {
			$child_products = [];
		}
		if ( ! is_array( $conditional_rules ) ) {
			$conditional_rules = [];
		}

		// No need to load scripts if no addons or children configured
		if ( empty( $addon_fields ) && empty( $child_products ) ) {
			return;
		}

		wp_enqueue_style(
			'pab-frontend',
			PAB_URL . 'assets/css/frontend.css',
			[],
			PAB_VERSION
		);

		wp_enqueue_script(
			'pab-frontend',
			PAB_URL . 'assets/js/frontend.js',
			[ 'jquery' ],
			PAB_VERSION,
			true
		);

		// Build child product data for JS
		$children_data = [];
		foreach ( $child_products as $i => $child ) {
			$pid = absint( $child['product_id'] ?? 0 );
			if ( ! $pid ) {
				continue;
			}
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}

			$override_price = $child['override_price'] ?? '';
			$price          = $override_price !== '' ? (float) $override_price : (float) $product->get_price();

			$child_entry = [
				'index'       => $i,
				'product_id'  => $pid,
				'name'        => $product->get_name(),
				'price'       => $price,
				'min_qty'     => (int) ( $child['min_qty'] ?? 0 ),
				'max_qty'     => (int) ( $child['max_qty'] ?? 1 ),
				'required'    => ! empty( $child['required'] ),
				'is_variable' => ! empty( $child['is_variable'] ),
				'variations'  => [],
			];

			if ( ! empty( $child['is_variable'] ) && $product->is_type( 'variable' ) ) {
				$vars = $product->get_available_variations();
				foreach ( $vars as $var ) {
					$var_id = $var['variation_id'];
					if ( ! empty( $child['allowed_variations'] ) && ! in_array( $var_id, (array) $child['allowed_variations'], true ) ) {
						continue;
					}
					$var_price = (float) $var['display_price'];
					if ( $override_price !== '' ) {
						$var_price = (float) $override_price;
					}
					$child_entry['variations'][] = [
						'variation_id' => $var_id,
						'attributes'   => $var['attributes'],
						'price'        => $var_price,
						'label'        => implode( ' / ', array_filter( array_values( $var['attributes'] ) ) ),
					];
				}
			}

			$children_data[] = $child_entry;
		}

		$wc_product = wc_get_product( $product_id );
		$base_price = $wc_product ? (float) $wc_product->get_price() : 0;

		// WooCommerce returns sprintf placeholders (%1$s / %2$s); JS uses %s / %v like wc admin assets.
		$price_format = str_replace(
			[ '%1$s', '%2$s' ],
			[ '%s', '%v' ],
			html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES | ENT_HTML5, 'UTF-8' )
		);

		wp_localize_script( 'pab-frontend', 'pabData', [
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'pab_frontend_nonce' ),
			'swatchCustomValue' => PAB_Data::SWATCH_CUSTOM_POST_VALUE,
			'basePrice'        => $base_price,
			'currency'         => get_woocommerce_currency_symbol(),
			'addonFields'      => $addon_fields,
			'childProducts'    => $children_data,
			'conditionalRules' => $conditional_rules,
			'i18n'             => [
				'chooseExtra'     => __( 'Please choose an extra.', 'pab' ),
				'chooseVariation' => __( 'Please choose options for the selected extra.', 'pab' ),
				'perQtySuffix'      => __( '× quantity', 'pab' ),
			],
			'settings'         => [
				'enableLiveTotal' => ( $pab_settings['enable_live_total'] ?? 'yes' ) === 'yes',
			],
			'priceFormat'      => [
				'decimals'           => wc_get_price_decimals(),
				'decimal_separator'  => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'price_format'       => $price_format,
			],
		] );
	}

	public function render_fields() {
		global $post;
		$product_id = $post->ID;

		$addon_fields   = PAB_Group_Resolver::resolve_addon_fields( $product_id );
		$child_products = PAB_Data::decode_json_meta( $product_id, '_child_products' );

		if ( empty( $addon_fields ) && empty( $child_products ) ) {
			return;
		}

		$pab_settings   = get_option( 'pab_settings', [] );
		$swatch_shape   = PAB_Data::sanitize_image_swatch_shape( $pab_settings['image_swatch_shape'] ?? 'square' );
		$shape_class    = 'pab-swatch-shape--' . $swatch_shape;

		echo '<div class="pab-product-addons ' . esc_attr( $shape_class ) . '" id="pab-product-addons">';

		if ( ! empty( $addon_fields ) ) {
			$display = new PAB_Display_Fields( $product_id, $addon_fields );
			$display->render();
		}

		if ( ! empty( $child_products ) ) {
			$display_children = new PAB_Display_Children( $product_id, $child_products );
			$display_children->render();
		}

		echo '<div class="pab-live-total-wrap" id="pab-live-total-wrap" style="display:none;">';
		echo '<strong class="pab-live-total-label">' . esc_html__( 'Total:', 'pab' ) . '</strong> ';
		echo '<span id="pab-live-total"></span>';
		echo '</div>';

		echo '</div>';
	}
}
