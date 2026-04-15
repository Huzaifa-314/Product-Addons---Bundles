<?php
/**
 * Plugin Name: Product Addons & Bundles
 * Plugin URI:  #
 * Description: Add product add-on fields, composite/child products, and conditional pricing to WooCommerce products.
 * Version:     1.0.35
 * Author:      Custom
 * Text Domain: pab
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.6
 */

defined( 'ABSPATH' ) || exit;

define( 'PAB_VERSION', '1.0.35' );
define( 'PAB_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check WooCommerce is active before loading anything.
 */
function pab_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="error"><p><strong>Product Addons &amp; Bundles</strong> requires WooCommerce to be installed and active.</p></div>';
		} );
		return;
	}

	pab_load();
}
add_action( 'plugins_loaded', 'pab_check_woocommerce' );

function pab_load() {
	// Includes
	require_once PAB_PATH . 'includes/class-pab-data.php';
	require_once PAB_PATH . 'includes/class-pab-group-resolver.php';
	require_once PAB_PATH . 'includes/class-pab-cart-hooks.php';
	require_once PAB_PATH . 'includes/class-pab-ajax.php';

	// Admin
	require_once PAB_PATH . 'admin/class-pab-admin.php';
	require_once PAB_PATH . 'admin/class-pab-product-tab.php';
	require_once PAB_PATH . 'admin/class-pab-save-fields.php';

	// Frontend
	require_once PAB_PATH . 'frontend/class-pab-frontend.php';
	require_once PAB_PATH . 'frontend/class-pab-display-fields.php';
	require_once PAB_PATH . 'frontend/class-pab-display-children.php';

	// Instantiate
	PAB_Group_Resolver::init();
	new PAB_Admin();
	new PAB_Frontend();
	new PAB_Cart_Hooks();
	new PAB_Ajax();
}

// Declare HPOS compatibility
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
