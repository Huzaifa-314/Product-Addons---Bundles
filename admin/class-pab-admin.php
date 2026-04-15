<?php
defined( 'ABSPATH' ) || exit;

class PAB_Admin {

	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_product_tab' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_group_metaboxes' ] );
		add_action( 'save_post_' . PAB_Group_Resolver::GROUP_POST_TYPE, [ $this, 'save_group_meta_from_editor' ], 10, 2 );

		new PAB_Save_Fields();
	}

	public function add_product_tab( $tabs ) {
		// Show for all standard product types.
		// Add show_if_* so WooCommerce JS handles visibility correctly per type.
		$show_classes = [ 'show_if_simple', 'show_if_variable', 'show_if_grouped', 'show_if_external' ];

		// Also show for any custom product types registered by other plugins.
		$all_types = array_keys( wc_get_product_types() );
		foreach ( $all_types as $type ) {
			$show_classes[] = 'show_if_' . $type;
		}
		$show_classes = array_unique( $show_classes );

		$tabs['pab_addons'] = [
			'label'    => __( 'Add-ons & Composite', 'pab' ),
			'target'   => 'pab_addons_data',
			'class'    => $show_classes,
			'priority' => 70,
		];
		return $tabs;
	}

	public function render_product_tab() {
		global $post, $thepostid, $product_object;

		if ( $product_object instanceof WC_Product ) {
			$product_id = $product_object->get_id();
		} else {
			$product_id = $thepostid ? $thepostid : ( $post->ID ?? 0 );
		}

		if ( ! $product_id ) {
			return;
		}

		// Same structure as core WC panels (incl. `hidden`) so tab JS can show/hide correctly.
		echo '<div id="pab_addons_data" class="panel woocommerce_options_panel hidden">';
		$tab = new PAB_Product_Tab( $product_id );
		$tab->render();
		echo '</div>';
	}

	public function enqueue_assets( $hook ) {
		$is_product_editor = false;
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			global $post;
			$is_product_editor = ( $post && 'product' === $post->post_type );
		}

		$is_settings_page = ( 'woocommerce_page_pab-settings' === $hook );
		$is_group_editor  = in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && isset( $_GET['post_type'] ) && PAB_Group_Resolver::GROUP_POST_TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) );
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && ! $is_group_editor ) {
			global $post;
			$is_group_editor = ( $post && PAB_Group_Resolver::GROUP_POST_TYPE === $post->post_type );
		}
		if ( ! $is_product_editor && ! $is_settings_page && ! $is_group_editor ) {
			return;
		}

		if ( $is_product_editor || $is_group_editor ) {
			wp_enqueue_media();
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( 'wc-enhanced-select' );

		wp_enqueue_style(
			'pab-admin',
			PAB_URL . 'assets/css/admin.css',
			[ 'woocommerce_admin_styles', 'dashicons' ],
			PAB_VERSION
		);

		wp_enqueue_script(
			'pab-admin',
			PAB_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable', 'wp-util', 'wc-enhanced-select' ],
			PAB_VERSION,
			true
		);

		$localize = [
			'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'pab_admin_nonce' ),
			'searchProductsNonce' => wp_create_nonce( 'search-products' ),
			'i18n'                => [
				'removeAssignmentConfirm' => __( 'Remove this assignment row?', 'pab' ),
				'removeAddonConfirm'      => __( 'Delete this add-on field? Its options will be lost.', 'pab' ),
				'removeChildConfirm'      => __( 'Remove this child product?', 'pab' ),
				'removeRuleConfirm'       => __( 'Remove this conditional rule?', 'pab' ),
				'removeOptionConfirm'     => __( 'Remove this choice?', 'pab' ),
				'unsavedChanges'          => __( 'You have unsaved changes. Are you sure you want to leave?', 'pab' ),
				'requiredField'           => __( 'This field is required.', 'pab' ),
				'ajaxError'               => __( 'An error occurred. Please try again.', 'pab' ),
			],
		];

		if ( $is_group_editor ) {
			$taxonomies = PAB_Group_Resolver::get_allowed_location_taxonomies();
			$first_tax  = array_key_first( $taxonomies );
			$localize['groupLocationTaxonomies'] = $taxonomies;
			$localize['defaultLocationTaxonomy'] = $first_tax ? $first_tax : 'product_cat';
			$localize['i18n']['addLocationRule']    = __( 'Add rule', 'pab' );
			$localize['i18n']['removeLocationRule']  = __( 'Remove rule', 'pab' );
			$localize['i18n']['removeLocationRuleConfirm'] = __( 'Remove this location rule?', 'pab' );
			$localize['i18n']['searchTerms']         = __( 'Search for a term…', 'pab' );
			$localize['i18n']['opEqual']             = __( 'is equal to', 'pab' );
			$localize['i18n']['opNotEqual']          = __( 'is not equal to', 'pab' );
		}

		wp_localize_script( 'pab-admin', 'pabAdmin', $localize );
	}

	public function register_settings_page() {
		add_menu_page(
			__( 'Product Addons & Bundles', 'pab' ),
			__( 'PAB', 'pab' ),
			'manage_woocommerce',
			'pab-settings',
			[ $this, 'render_settings_page' ],
			'dashicons-screenoptions',
			56
		);

		add_submenu_page(
			'pab-settings',
			__( 'Settings', 'pab' ),
			__( 'Settings', 'pab' ),
			'manage_woocommerce',
			'pab-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting(
			'pab_settings_group',
			'pab_settings',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [
					'enable_live_total'       => 'yes',
					'enable_tooltips'         => 'yes',
					'upload_image_drop_title' => '',
				],
			]
		);
	}

	public function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : [];
		$title    = isset( $settings['upload_image_drop_title'] ) ? sanitize_text_field( wp_unslash( $settings['upload_image_drop_title'] ) ) : '';
		if ( strlen( $title ) > 240 ) {
			$title = substr( $title, 0, 240 );
		}
		return [
			'enable_live_total'       => ! empty( $settings['enable_live_total'] ) ? 'yes' : 'no',
			'enable_tooltips'         => ! empty( $settings['enable_tooltips'] ) ? 'yes' : 'no',
			'upload_image_drop_title' => $title,
		];
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$settings    = get_option( 'pab_settings', [] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Product Addons & Bundles', 'pab' ); ?></h1>
			<?php $this->render_admin_notice(); ?>
			<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pab-settings&tab=general' ) ); ?>" class="nav-tab <?php echo ( 'general' === $current_tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'pab' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pab-settings&tab=help' ) ); ?>" class="nav-tab <?php echo ( 'help' === $current_tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Help', 'pab' ); ?></a>
			</h2>
			<?php if ( 'general' === $current_tab ) : ?>
				<form method="post" action="options.php">
					<?php settings_fields( 'pab_settings_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Live total on product page', 'pab' ); ?></th>
							<td><label><input type="checkbox" name="pab_settings[enable_live_total]" value="1" <?php checked( ( $settings['enable_live_total'] ?? 'yes' ), 'yes' ); ?> /> <?php esc_html_e( 'Show dynamic total as options change', 'pab' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Inline help text', 'pab' ); ?></th>
							<td><label><input type="checkbox" name="pab_settings[enable_tooltips]" value="1" <?php checked( ( $settings['enable_tooltips'] ?? 'yes' ), 'yes' ); ?> /> <?php esc_html_e( 'Show helper descriptions in admin builders', 'pab' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="pab-upload-image-drop-title"><?php esc_html_e( 'Image upload dropzone title', 'pab' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="pab-upload-image-drop-title" name="pab_settings[upload_image_drop_title]" value="<?php echo esc_attr( $settings['upload_image_drop_title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Drop an image here', 'pab' ); ?>" maxlength="240" />
								<p class="description"><?php esc_html_e( 'Main line of text inside the image upload area on the product page. Leave empty to use the default.', 'pab' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			<?php else : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Build addons from Product Edit -> Add-ons & Composite tab. You can also create reusable addon groups and assign them to products.', 'pab' ); ?></p>
					<p><?php esc_html_e( 'Priority rules: lower numbers are applied first. Product-specific addon fields remain supported and merge with resolved group fields.', 'pab' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function register_group_metaboxes( $post_type ) {
		if ( PAB_Group_Resolver::GROUP_POST_TYPE !== $post_type ) {
			return;
		}

		add_meta_box(
			'pab_group_targeting',
			__( 'Display / Location', 'pab' ),
			[ $this, 'render_group_targeting_metabox' ],
			PAB_Group_Resolver::GROUP_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'pab_group_addons',
			__( 'Product Addons', 'pab' ),
			[ $this, 'render_group_addons_metabox' ],
			PAB_Group_Resolver::GROUP_POST_TYPE,
			'normal',
			'default'
		);
	}

	public function render_group_targeting_metabox( $post ) {
		$product_ids = array_map( 'absint', (array) get_post_meta( $post->ID, '_pab_group_products', true ) );
		$priority    = (int) get_post_meta( $post->ID, '_pab_group_priority', true );
		$priority    = $priority ?: 100;

		$location_rules = PAB_Group_Resolver::sanitize_location_rules(
			PAB_Data::decode_json_meta( $post->ID, PAB_Group_Resolver::GROUP_LOCATION_RULES_META )
		);
		$taxonomies     = PAB_Group_Resolver::get_allowed_location_taxonomies();

		wp_nonce_field( 'pab_group_editor_meta', 'pab_group_editor_nonce' );
		?>
		<p class="description pab-group-targeting-intro"><?php esc_html_e( 'Choose specific products and/or taxonomy rules. If both are set, the group applies when either the product is listed or the rules match (OR). Within rules, use Match all / Match any.', 'pab' ); ?></p>
		<div class="pab-group-targeting-priority">
			<label for="pab-group-priority"><strong><?php esc_html_e( 'Priority', 'pab' ); ?></strong></label>
			<div class="pab-group-targeting-priority__row">
				<input id="pab-group-priority" type="number" class="small-text" name="pab_group_priority" value="<?php echo esc_attr( (string) $priority ); ?>" />
				<span class="description"><?php esc_html_e( 'Lower number applies earlier when multiple groups match a product.', 'pab' ); ?></span>
			</div>
		</div>
		<p class="pab-group-targeting-products">
			<label for="pab-group-products"><strong><?php esc_html_e( 'Products', 'pab' ); ?></strong></label>
			<select id="pab-group-products" class="wc-product-search pab-group-products-select" name="pab_group_products[]" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Search for products…', 'pab' ); ?>" data-action="woocommerce_json_search_products_and_variations">
				<?php foreach ( $product_ids as $product_id ) : ?>
					<?php $product = wc_get_product( $product_id ); ?>
					<?php if ( $product ) : ?>
						<option value="<?php echo esc_attr( (string) $product_id ); ?>" selected="selected"><?php echo esc_html( $product->get_name() ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</p>

		<div id="pab-group-location-rules" class="pab-group-location-rules">
			<p class="pab-group-location-rules__heading"><strong><?php esc_html_e( 'Location rules', 'pab' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'Optional: target products by category, tag, type, or other product taxonomies (ACF-style).', 'pab' ); ?></p>
			<p class="pab-location-rules-match" role="group" aria-label="<?php esc_attr_e( 'Rule matching', 'pab' ); ?>">
				<span class="description pab-location-rules-match__label"><?php esc_html_e( 'Rule matching:', 'pab' ); ?></span>
				<span class="pab-location-rules-match__options">
					<label class="pab-location-rules-match__option"><input type="radio" name="pab_group_location_rules[match]" value="all" <?php checked( $location_rules['match'], 'all' ); ?> /> <?php esc_html_e( 'Match all rules', 'pab' ); ?></label>
					<label class="pab-location-rules-match__option"><input type="radio" name="pab_group_location_rules[match]" value="any" <?php checked( $location_rules['match'], 'any' ); ?> /> <?php esc_html_e( 'Match any rule', 'pab' ); ?></label>
				</span>
			</p>
			<div id="pab-group-location-rules-rows" class="pab-group-location-rules-rows">
				<?php
				$rule_index = 0;
				foreach ( $location_rules['rules'] as $rule ) :
					$param    = isset( $rule['param'] ) ? sanitize_key( (string) $rule['param'] ) : '';
					$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '==';
					$term_id  = absint( $rule['value'] ?? 0 );
					if ( '' === $param || ! $term_id ) {
						continue;
					}
					$term = get_term( $term_id, $param );
					if ( ! $term || is_wp_error( $term ) ) {
						continue;
					}
					$this->render_location_rule_row( $rule_index, $param, $operator, $term_id, $term->name, $taxonomies );
					++$rule_index;
				endforeach;
				?>
			</div>
			<p class="pab-group-location-rules__footer">
				<button type="button" class="button" id="pab-add-location-rule" <?php disabled( empty( $taxonomies ) ); ?>><?php esc_html_e( 'Add rule', 'pab' ); ?></button>
			</p>
			<?php if ( empty( $taxonomies ) ) : ?>
				<p class="description"><?php esc_html_e( 'No product taxonomies are available for location rules.', 'pab' ); ?></p>
			<?php endif; ?>
		</div>
		<?php $this->render_location_rule_template( $taxonomies ); ?>
		<?php
	}

	/**
	 * Render the JS template for location rule rows.
	 * Uses cloneTemplate() in admin.js instead of string concatenation.
	 *
	 * @param array<string,string> $taxonomies
	 */
	private function render_location_rule_template( array $taxonomies ): void {
		if ( empty( $taxonomies ) ) {
			return;
		}
		$default_tax = array_key_first( $taxonomies ) ?: 'product_cat';
		?>
		<script type="text/html" id="pab-tmpl-location-rule-row">
			<?php $this->render_location_rule_row( '__PAB_LOCATION_RULE_INDEX__', $default_tax, '==', 0, '', $taxonomies ); ?>
		</script>
		<?php
	}

	private function render_location_rule_row( $index, string $param, string $operator, int $term_id, string $term_label, array $taxonomies ): void {
		if ( empty( $taxonomies ) ) {
			return;
		}
		?>
		<div class="pab-location-rule-row">
			<div class="pab-location-rule-col pab-location-rule-col-param">
				<label class="screen-reader-text"><?php esc_html_e( 'Parameter', 'pab' ); ?></label>
				<select name="pab_group_location_rules[rules][<?php echo esc_attr( (string) $index ); ?>][param]" class="pab-location-rule-param">
					<?php foreach ( $taxonomies as $tax_name => $tax_label ) : ?>
						<option value="<?php echo esc_attr( $tax_name ); ?>" <?php selected( $param, $tax_name ); ?>><?php echo esc_html( $tax_label . ' (' . $tax_name . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="pab-location-rule-col pab-location-rule-col-operator">
				<label class="screen-reader-text"><?php esc_html_e( 'Operator', 'pab' ); ?></label>
				<select name="pab_group_location_rules[rules][<?php echo esc_attr( (string) $index ); ?>][operator]" class="pab-location-rule-operator">
					<option value="==" <?php selected( $operator, '==' ); ?>><?php esc_html_e( 'is equal to', 'pab' ); ?></option>
					<option value="!=" <?php selected( $operator, '!=' ); ?>><?php esc_html_e( 'is not equal to', 'pab' ); ?></option>
				</select>
			</div>
			<div class="pab-location-rule-col pab-location-rule-col-value">
				<label class="screen-reader-text"><?php esc_html_e( 'Value', 'pab' ); ?></label>
				<select
					name="pab_group_location_rules[rules][<?php echo esc_attr( (string) $index ); ?>][value]"
					class="wc-taxonomy-term-search pab-location-rule-value"
					data-placeholder="<?php esc_attr_e( 'Search for a term…', 'pab' ); ?>"
					data-taxonomy="<?php echo esc_attr( $param ); ?>"
					data-minimum_input_length="2"
					data-return_id="true"
				>
					<?php if ( $term_id && $term_label !== '' ) : ?>
						<option value="<?php echo esc_attr( (string) $term_id ); ?>" selected="selected"><?php echo esc_html( $term_label ); ?></option>
					<?php endif; ?>
				</select>
			</div>
			<div class="pab-location-rule-col pab-location-rule-col-actions">
				<button type="button" class="button-link pab-remove-location-rule" aria-label="<?php esc_attr_e( 'Remove rule', 'pab' ); ?>">&times;</button>
			</div>
		</div>
		<?php
	}

	public function render_group_addons_metabox( $post ) {
		$fields = PAB_Data::normalize_addon_fields( PAB_Data::decode_json_meta( $post->ID, PAB_Group_Resolver::GROUP_META_FIELDS ) );
		?>
		<p class="description"><?php esc_html_e( 'Configure addon fields exactly like product edit addon builder. These fields will be reused on targeted products.', 'pab' ); ?></p>
		<?php
		$tab = new PAB_Product_Tab( 0, $fields, [], [] );
		$tab->render_addon_fields_section();
		?>
		<?php
	}

	public function save_group_meta_from_editor( $post_id, $post ) {
		if ( ! isset( $_POST['pab_group_editor_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['pab_group_editor_nonce'] ), 'pab_group_editor_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$priority = (int) ( $_POST['pab_group_priority'] ?? 100 );
		update_post_meta( $post_id, '_pab_group_priority', $priority );

		$product_ids = isset( $_POST['pab_group_products'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['pab_group_products'] ) ) : [];
		$product_ids = array_values( array_filter( $product_ids ) );
		update_post_meta( $post_id, '_pab_group_products', $product_ids );

		$raw_location = isset( $_POST['pab_group_location_rules'] ) && is_array( $_POST['pab_group_location_rules'] ) ? wp_unslash( $_POST['pab_group_location_rules'] ) : [];
		$raw_location = $this->parse_location_rules_post( $raw_location );
		$location     = PAB_Group_Resolver::sanitize_location_rules( $raw_location );
		update_post_meta( $post_id, PAB_Group_Resolver::GROUP_LOCATION_RULES_META, wp_json_encode( $location ) );

		$raw_fields   = $_POST['pab_addon_fields'] ?? [];
		$helper       = new PAB_Save_Fields( false );
		$addon_fields = $helper->sanitize_addon_fields( $raw_fields );
		update_post_meta( $post_id, PAB_Group_Resolver::GROUP_META_FIELDS, wp_json_encode( $addon_fields ) );
	}

	public function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
	}

	private function render_admin_notice() {
		if ( isset( $_GET['pab_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Addon group saved.', 'pab' ) . '</p></div>';
		}
		if ( isset( $_GET['pab_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Addon group deleted.', 'pab' ) . '</p></div>';
		}
		if ( isset( $_GET['message'] ) && '1' === (string) $_GET['message'] && isset( $_GET['post_type'] ) && PAB_Group_Resolver::GROUP_POST_TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Addon group saved.', 'pab' ) . '</p></div>';
		}
	}


	/**
	 * @param array<string,mixed> $post_rules
	 * @return array<string,mixed>
	 */
	private function parse_location_rules_post( array $post_rules ): array {
		$match = isset( $post_rules['match'] ) && 'any' === $post_rules['match'] ? 'any' : 'all';
		$rules = [];

		if ( isset( $post_rules['rules'] ) && is_array( $post_rules['rules'] ) ) {
			foreach ( $post_rules['rules'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$rules[] = [
					'param'    => isset( $row['param'] ) ? sanitize_key( (string) $row['param'] ) : '',
					'operator' => isset( $row['operator'] ) ? (string) $row['operator'] : '==',
					'value'    => isset( $row['value'] ) ? absint( $row['value'] ) : 0,
				];
			}
		}

		return [
			'match' => $match,
			'rules' => $rules,
		];
	}
}
