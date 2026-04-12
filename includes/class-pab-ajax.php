<?php
defined( 'ABSPATH' ) || exit;

class PAB_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_pab_get_variations', [ $this, 'get_variations' ] );
	}

	public function get_variations() {
		check_ajax_referer( 'pab_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pab' ) ] );
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'pab' ) ] );
		}

		$variations = PAB_Data::get_variation_payload( $product_id );
		if ( empty( $variations ) ) {
			wp_send_json_error( [ 'message' => __( 'Not a variable product.', 'pab' ) ] );
		}
		wp_send_json_success( $variations );
	}
}
