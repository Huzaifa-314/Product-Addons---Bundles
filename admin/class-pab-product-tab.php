<?php
defined( 'ABSPATH' ) || exit;

class PAB_Product_Tab {

	private $product_id;
	private $addon_fields      = [];
	private $child_products    = [];
	private $conditional_rules = [];
	private $group_assignments = [];
	private $available_groups  = [];

	public function __construct( $product_id, $addon_fields = null, $child_products = null, $conditional_rules = null ) {
		$this->product_id        = (int) $product_id;
		$this->addon_fields      = is_array( $addon_fields ) ? PAB_Data::normalize_addon_fields( $addon_fields ) : PAB_Data::normalize_addon_fields( PAB_Data::decode_json_meta( $this->product_id, '_addon_fields' ) );
		$this->child_products    = is_array( $child_products ) ? $child_products : PAB_Data::decode_json_meta( $this->product_id, '_child_products' );
		$this->conditional_rules = is_array( $conditional_rules ) ? $conditional_rules : PAB_Data::normalize_conditional_rules( PAB_Data::decode_json_meta( $this->product_id, '_conditional_rules' ), $this->addon_fields );
		$this->group_assignments = PAB_Group_Resolver::get_product_assignments( $this->product_id );
		$this->available_groups  = PAB_Group_Resolver::get_all_groups( [ 'post_status' => [ 'publish', 'draft' ] ] );
	}

	public function render() {
		wp_nonce_field( 'pab_save_product_meta', 'pab_nonce' );
		$this->render_group_assignments_section();
		$this->render_addon_fields_section();
		$this->render_child_products_section();
		$this->render_conditional_rules_section();
	}

	private function render_group_assignments_section() {
		$rows = $this->group_assignments;
		?>
		<div class="options_group">
			<p class="form-field pab-section-intro">
				<strong><?php esc_html_e( 'Applied addon groups', 'pab' ); ?></strong>
				<span class="description"><?php esc_html_e( 'Attach reusable groups directly to this product. Lower priority runs first.', 'pab' ); ?></span>
			</p>
			<?php if ( empty( $this->available_groups ) ) : ?>
				<p class="form-field"><span class="description"><?php esc_html_e( 'No groups available yet. Create them in WooCommerce -> Product Addons & Bundles -> Addon Groups.', 'pab' ); ?></span></p>
			<?php else : ?>
				<table class="widefat striped pab-assignments-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Group', 'pab' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'pab' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'pab' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $index => $row ) : ?>
							<tr>
								<td>
									<select name="pab_product_group_assignments[<?php echo esc_attr( (string) $index ); ?>][group_id]">
										<option value="0"><?php esc_html_e( 'Select group', 'pab' ); ?></option>
										<?php foreach ( $this->available_groups as $group ) : ?>
											<option value="<?php echo esc_attr( (string) $group['id'] ); ?>" <?php selected( (int) ( $row['group_id'] ?? 0 ), (int) $group['id'] ); ?>>
												<?php echo esc_html( $group['title'] ?: __( '(no title)', 'pab' ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="number" class="small-text" name="pab_product_group_assignments[<?php echo esc_attr( (string) $index ); ?>][priority]" value="<?php echo esc_attr( (string) ( $row['priority'] ?? 100 ) ); ?>" /></td>
								<td><input type="checkbox" name="pab_product_group_assignments[<?php echo esc_attr( (string) $index ); ?>][status]" value="1" <?php checked( ( $row['status'] ?? 'enabled' ), 'enabled' ); ?> /></td>
								<td><a href="#" class="pab-remove-assignment-row" aria-label="<?php esc_attr_e( 'Remove this assignment', 'pab' ); ?>"><?php esc_html_e( 'Remove', 'pab' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="form-field">
					<button type="button" class="button button-secondary" id="pab-add-group-assignment"><?php esc_html_e( 'Add group assignment', 'pab' ); ?></button>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Section 1: Add-on Fields
	// -------------------------------------------------------------------------
	public function render_addon_fields_section() {
		?>
		<div class="options_group">
			<p class="form-field pab-section-intro">
				<strong><?php esc_html_e( 'Add-on fields', 'pab' ); ?></strong>
				<span class="description"><?php esc_html_e( 'Extra inputs shown on the product page (text, choices, uploads, etc.).', 'pab' ); ?></span>
			</p>
			<div class="pab-field-builder">
				<div class="pab-field-builder__toolbar">
					<label for="pab-addon-new-field-type" class="screen-reader-text"><?php esc_html_e( 'Field type to add', 'pab' ); ?></label>
					<select id="pab-addon-new-field-type" class="wc-enhanced-select pab-field-builder__type-select">
						<option value="text"><?php esc_html_e( 'Text Input', 'pab' ); ?></option>
						<option value="textarea"><?php esc_html_e( 'Textarea', 'pab' ); ?></option>
						<option value="select"><?php esc_html_e( 'Select Dropdown', 'pab' ); ?></option>
						<option value="checkbox"><?php esc_html_e( 'Checkbox', 'pab' ); ?></option>
						<option value="radio"><?php esc_html_e( 'Radio Button', 'pab' ); ?></option>
						<option value="number"><?php esc_html_e( 'Number Input', 'pab' ); ?></option>
						<option value="file"><?php esc_html_e( 'File Upload', 'pab' ); ?></option>
						<option value="image_upload"><?php esc_html_e( 'Image Upload', 'pab' ); ?></option>
						<option value="image_swatch"><?php esc_html_e( 'Image Swatch', 'pab' ); ?></option>
						<option value="text_swatch"><?php esc_html_e( 'Text Swatch', 'pab' ); ?></option>
						<option value="popup"><?php esc_html_e( 'Popup', 'pab' ); ?></option>
					</select>
					<button type="button" class="button button-primary pab-add-addon-field"><?php esc_html_e( 'Add field', 'pab' ); ?></button>
				</div>
				<div id="pab-addon-fields-list" class="pab-repeater-list">
					<?php foreach ( $this->addon_fields as $index => $field ) : ?>
						<?php $this->render_addon_row( $index, $field ); ?>
					<?php endforeach; ?>
				</div>
				<?php $this->render_addon_templates(); ?>
			</div>
		</div>
		<?php
	}

	public function render_addon_templates() {
		?>
		<div id="pab-tmpl-addon-row" style="display:none">
			<?php $this->render_addon_row( '__PAB_FIELD_INDEX__', [], true ); ?>
		</div>
		<div id="pab-tmpl-nested-addon-row" style="display:none">
			<?php $this->render_addon_row( '__PAB_NESTED_INDEX__', [], true, '__PAB_PARENT_INDEX__' ); ?>
		</div>
		<div id="pab-tmpl-option-row" style="display:none">
			<?php
			/* Full structure (image column present); JS syncs visibility per field type. */
			$this->render_option_row( '__PAB_FIELD_NAME_ROOT__', '__PAB_OPT_INDEX__', [], 'image_swatch', true, 'per_option' );
			?>
		</div>
		<div id="pab-tmpl-options-head" style="display:none">
			<?php $this->render_options_table_header( 'image_swatch', 'per_option' ); ?>
		</div>
		<?php
	}

	/**
	 * Column headings for choice-field options (aligned with option rows).
	 *
	 * @param string $type         Field type (choice types only).
	 * @param string $choice_mode uniform|per_option.
	 */
	private function render_options_table_header( $type, $choice_mode ) {
		$is_swatch  = ( 'image_swatch' === $type );
		$show_price = ( 'uniform' !== $choice_mode );
		?>
		<div class="pab-options-head pab-option-row-flex" role="row">
			<span class="pab-option-col pab-option-head-label"><?php esc_html_e( 'Choice label', 'pab' ); ?></span>
			<span class="pab-option-col pab-option-head-price <?php echo $show_price ? '' : 'pab-is-hidden'; ?>"><?php esc_html_e( 'Price', 'pab' ); ?></span>
			<span class="pab-option-col pab-option-head-image <?php echo $is_swatch ? '' : 'pab-is-hidden'; ?>"><?php esc_html_e( 'Swatch image', 'pab' ); ?></span>
			<span class="pab-option-col pab-option-head-actions" aria-hidden="true"></span>
		</div>
		<?php
	}

	private function render_addon_row( $index, $field, $is_template = false, $nested_parent_index = null ) {
		$field_id        = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
		if ( '' === $field_id ) {
			$field_id = '__PAB_FIELD_ID__';
		}
		$type            = isset( $field['type'] ) ? $field['type'] : 'text';
		$label           = isset( $field['label'] ) ? $field['label'] : '';
		$required        = isset( $field['required'] ) && $field['required'] ? 'checked' : '';
		$price           = isset( $field['price'] ) ? $field['price'] : '';
		$price_type      = isset( $field['price_type'] ) ? $field['price_type'] : 'flat';
		$choice_mode     = isset( $field['choice_price_mode'] ) ? $field['choice_price_mode'] : 'per_option';
		$options         = isset( $field['options'] ) ? $field['options'] : [];
		$image_swatch_size   = PAB_Data::sanitize_image_swatch_size( $field['image_swatch_size'] ?? 'medium' );
		$swatch_allow_custom = ! empty( $field['swatch_allow_custom_upload'] );
		$swatch_custom_label = isset( $field['swatch_custom_label'] ) ? (string) $field['swatch_custom_label'] : '';
		$swatch_custom_price = isset( $field['swatch_custom_price'] ) ? $field['swatch_custom_price'] : '';
		$popup_button_label  = isset( $field['popup_button_label'] ) ? (string) $field['popup_button_label'] : '';
		$popup_title         = isset( $field['popup_title'] ) ? (string) $field['popup_title'] : '';
		$popup_description   = isset( $field['popup_description'] ) ? (string) $field['popup_description'] : '';
		$popup_side_image    = isset( $field['popup_side_image'] ) ? (string) $field['popup_side_image'] : '';
		$placeholder         = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
		$nested_fields       = isset( $field['nested_fields'] ) && is_array( $field['nested_fields'] ) ? $field['nested_fields'] : [];

		if ( null === $nested_parent_index ) {
			$field_name_root = 'pab_addon_fields[' . $index . ']';
		} else {
			$field_name_root = 'pab_addon_fields[' . $nested_parent_index . '][nested_fields][' . $index . ']';
		}

		$field_types = [
			'text'         => __( 'Text Input', 'pab' ),
			'textarea'     => __( 'Textarea', 'pab' ),
			'select'       => __( 'Select Dropdown', 'pab' ),
			'checkbox'     => __( 'Checkbox', 'pab' ),
			'radio'        => __( 'Radio Button', 'pab' ),
			'number'       => __( 'Number Input', 'pab' ),
			'file'         => __( 'File Upload', 'pab' ),
			'image_upload' => __( 'Image Upload', 'pab' ),
			'image_swatch' => __( 'Image Swatch', 'pab' ),
			'text_swatch'  => __( 'Text Swatch', 'pab' ),
			'popup'        => __( 'Popup', 'pab' ),
		];

		$price_types = [
			'flat'       => __( 'Flat Price', 'pab' ),
			'percentage' => __( 'Percentage (%)', 'pab' ),
			'per_qty'    => __( 'Per Quantity', 'pab' ),
		];

		$id_suffix = preg_replace( '/[^a-zA-Z0-9_-]+/', '-', (string) $field_name_root );

		$has_options    = in_array( $type, [ 'select', 'radio', 'image_swatch', 'text_swatch' ], true );
		$is_popup       = ( 'popup' === $type );
		$has_placeholder = in_array( $type, [ 'text', 'textarea', 'number' ], true );
		$type_label     = $field_types[ $type ] ?? $field_types['text'];
		$display_title    = $label ? $label : __( 'New field', 'pab' );
		$display_key      = ( $is_template || '__PAB_FIELD_ID__' === $field_id ) ? '' : $field_id;
		$body_hidden_attr = $is_template ? '' : ' style="' . esc_attr( 'display: none;' ) . '"';
		$toggle_expanded  = $is_template ? 'true' : 'false';
		$toggle_icon_cls  = $is_template ? 'dashicons dashicons-arrow-down-alt2' : 'dashicons dashicons-arrow-right-alt2';
		$row_classes      = 'pab-settings-card pab-addon-row' . ( null !== $nested_parent_index ? ' pab-addon-row--nested' : '' );
		$standard_sections_hidden = $is_popup && null === $nested_parent_index;
		?>
		<div class="<?php echo esc_attr( $row_classes ); ?>" data-index="<?php echo esc_attr( $index ); ?>" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-pab-name-root="<?php echo esc_attr( $field_name_root ); ?>">
			<div class="pab-settings-card__header pab-addon-row__header">
				<span class="dashicons dashicons-move pab-drag-handle" aria-hidden="true"></span><button type="button" class="pab-move-btn pab-move-up" aria-label="Move up" title="Move up">▲</button><button type="button" class="pab-move-btn pab-move-down" aria-label="Move down" title="Move down">▼</button>
				<div class="pab-addon-row__summary">
					<span class="pab-field-builder__title pab-row-label"><?php echo esc_html( $display_title ); ?></span>
					<code class="pab-field-builder__key"<?php echo $display_key === '' ? ' style="display: none;"' : ''; ?>><?php echo $display_key !== '' ? esc_html( $display_key ) : ''; ?></code>
				</div>
				<span class="pab-field-type-badge"><?php echo esc_html( $type_label ); ?></span>
				<div class="pab-addon-row__actions">
					<?php if ( null === $nested_parent_index ) : ?>
						<button type="button" class="button-link pab-duplicate-addon-row"><?php esc_html_e( 'Duplicate', 'pab' ); ?></button>
					<?php endif; ?>
					<button type="button" class="button-link-delete pab-remove-row" aria-label="<?php esc_attr_e( 'Delete add-on field', 'pab' ); ?>"><?php esc_html_e( 'Delete', 'pab' ); ?></button>
					<button type="button" class="button button-small pab-settings-card__toggle" aria-expanded="<?php echo esc_attr( $toggle_expanded ); ?>" aria-label="<?php esc_attr_e( 'Expand or collapse field settings', 'pab' ); ?>">
						<span class="<?php echo esc_attr( $toggle_icon_cls ); ?>" aria-hidden="true"></span>
					</button>
				</div>
			</div>
			<div class="pab-settings-card__body pab-row-body"<?php echo $body_hidden_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr ?>>
				<input type="hidden" class="pab-field-id" name="<?php echo esc_attr( $field_name_root ); ?>[id]" value="<?php echo esc_attr( $field_id ); ?>"<?php disabled( $is_template, true ); ?> />

				<table class="pab-field-settings-table widefat" role="presentation">
					<tbody>
						<tr class="pab-field-settings-table__row">
							<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Field type', 'pab' ); ?></th>
							<td class="pab-field-settings-table__control">
								<select name="<?php echo esc_attr( $field_name_root ); ?>[type]" class="pab-field-type regular-text"<?php disabled( $is_template, true ); ?>>
									<?php foreach ( $field_types as $val => $lbl ) : ?>
										<?php
										if ( null !== $nested_parent_index && 'popup' === $val ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr class="pab-field-settings-table__row">
							<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Label', 'pab' ); ?></th>
							<td class="pab-field-settings-table__control">
								<input type="text" name="<?php echo esc_attr( $field_name_root ); ?>[label]"
									value="<?php echo esc_attr( $label ); ?>" class="pab-field-label regular-text"<?php disabled( $is_template, true ); ?> />
							</td>
						</tr>
						<tr class="pab-field-settings-table__row">
							<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Required', 'pab' ); ?></th>
							<td class="pab-field-settings-table__control">
								<label><input type="checkbox" class="checkbox" name="<?php echo esc_attr( $field_name_root ); ?>[required]"
									value="1" <?php echo $required; ?><?php disabled( $is_template, true ); ?> /> <?php esc_html_e( 'Required on the product page', 'pab' ); ?></label>
							</td>
						</tr>
						<tr class="pab-field-settings-table__row pab-field-placeholder-row <?php echo $has_placeholder ? '' : 'pab-is-hidden'; ?>">
							<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Placeholder', 'pab' ); ?></th>
							<td class="pab-field-settings-table__control">
								<input type="text" class="regular-text pab-field-placeholder" name="<?php echo esc_attr( $field_name_root ); ?>[placeholder]"
									value="<?php echo esc_attr( $placeholder ); ?>"<?php disabled( $is_template, true ); ?> />
								<p class="description"><?php esc_html_e( 'Optional hint text shown inside the field until the customer types.', 'pab' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php if ( null === $nested_parent_index ) : ?>
				<div class="pab-popup-settings <?php echo $is_popup ? '' : 'pab-is-hidden'; ?>">
					<h4 class="pab-field-settings-heading"><?php esc_html_e( 'Popup', 'pab' ); ?></h4>
					<table class="pab-field-settings-table widefat" role="presentation">
						<tbody>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Button label', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<input type="text" class="regular-text" name="<?php echo esc_attr( $field_name_root ); ?>[popup_button_label]"
										value="<?php echo esc_attr( $popup_button_label ); ?>" placeholder="<?php echo esc_attr__( 'e.g. Customize', 'pab' ); ?>"<?php disabled( $is_template, true ); ?> />
								</td>
							</tr>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Popup title', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<input type="text" class="regular-text" name="<?php echo esc_attr( $field_name_root ); ?>[popup_title]"
										value="<?php echo esc_attr( $popup_title ); ?>"<?php disabled( $is_template, true ); ?> />
								</td>
							</tr>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Popup description', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<textarea class="large-text" rows="4" name="<?php echo esc_attr( $field_name_root ); ?>[popup_description]"<?php disabled( $is_template, true ); ?>><?php echo esc_textarea( $popup_description ); ?></textarea>
									<p class="description"><?php esc_html_e( 'HTML is allowed (filtered on save).', 'pab' ); ?></p>
								</td>
							</tr>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Popup side image', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<input type="hidden" class="pab-popup-side-image-url" name="<?php echo esc_attr( $field_name_root ); ?>[popup_side_image]" value="<?php echo esc_attr( $popup_side_image ); ?>"<?php disabled( $is_template, true ); ?> />
									<div class="pab-popup-side-image-tools">
										<?php if ( $popup_side_image ) : ?>
											<img src="<?php echo esc_url( $popup_side_image ); ?>" alt="" class="pab-popup-side-image-preview" />
										<?php else : ?>
											<img src="" alt="" class="pab-popup-side-image-preview is-empty" role="presentation" />
										<?php endif; ?>
										<button type="button" class="button button-secondary pab-select-popup-side-image"><?php esc_html_e( 'Choose image', 'pab' ); ?></button>
										<button type="button" class="button button-link pab-clear-popup-side-image <?php echo $popup_side_image ? '' : 'pab-is-hidden'; ?>"><?php esc_html_e( 'Remove', 'pab' ); ?></button>
									</div>
									<p class="description"><?php esc_html_e( 'Optional image shown on the left side of the popup (cover). Leave empty for a single-column popup.', 'pab' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					<div class="pab-popup-nested-builder">
						<h4 class="pab-field-settings-heading"><?php esc_html_e( 'Add-ons inside popup', 'pab' ); ?></h4>
						<p class="pab-popup-nested-toolbar">
							<label class="screen-reader-text" for="pab-nested-new-type-<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Nested field type', 'pab' ); ?></label>
							<select id="pab-nested-new-type-<?php echo esc_attr( $id_suffix ); ?>" class="pab-nested-new-field-type regular-text">
								<option value="text"><?php esc_html_e( 'Text Input', 'pab' ); ?></option>
								<option value="textarea"><?php esc_html_e( 'Textarea', 'pab' ); ?></option>
								<option value="select"><?php esc_html_e( 'Select Dropdown', 'pab' ); ?></option>
								<option value="checkbox"><?php esc_html_e( 'Checkbox', 'pab' ); ?></option>
								<option value="radio"><?php esc_html_e( 'Radio Button', 'pab' ); ?></option>
								<option value="number"><?php esc_html_e( 'Number Input', 'pab' ); ?></option>
								<option value="file"><?php esc_html_e( 'File Upload', 'pab' ); ?></option>
								<option value="image_upload"><?php esc_html_e( 'Image Upload', 'pab' ); ?></option>
								<option value="image_swatch"><?php esc_html_e( 'Image Swatch', 'pab' ); ?></option>
								<option value="text_swatch"><?php esc_html_e( 'Text Swatch', 'pab' ); ?></option>
							</select>
							<button type="button" class="button button-secondary pab-add-nested-addon-field"><?php esc_html_e( 'Add field', 'pab' ); ?></button>
						</p>
						<div class="pab-popup-nested-list pab-repeater-list">
							<?php if ( $is_popup ) : ?>
								<?php foreach ( $nested_fields as $ni => $nf ) : ?>
									<?php $this->render_addon_row( $ni, $nf, false, $index ); ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<div class="pab-choice-pricing-section <?php echo $standard_sections_hidden ? 'pab-is-hidden' : ''; ?> <?php echo $has_options ? '' : 'pab-is-hidden'; ?>">
					<h4 class="pab-field-settings-heading"><?php esc_html_e( 'Choice pricing', 'pab' ); ?></h4>
					<table class="pab-field-settings-table widefat" role="presentation">
						<tbody>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Mode', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<select name="<?php echo esc_attr( $field_name_root ); ?>[choice_price_mode]" class="pab-choice-price-mode"<?php disabled( $is_template, true ); ?>>
										<option value="uniform" <?php selected( $choice_mode, 'uniform' ); ?>><?php esc_html_e( 'Same price for all choices', 'pab' ); ?></option>
										<option value="per_option" <?php selected( $choice_mode, 'per_option' ); ?>><?php esc_html_e( 'Individual price per choice', 'pab' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Use a single field price when every option should cost the same, or set a different amount on each option row.', 'pab' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="pab-field-level-pricing <?php echo $standard_sections_hidden ? 'pab-is-hidden' : ''; ?> <?php echo ( $has_options && $choice_mode === 'per_option' ) ? 'pab-is-hidden' : ''; ?>">
					<h4 class="pab-field-settings-heading"><?php esc_html_e( 'Pricing', 'pab' ); ?></h4>
					<table class="pab-field-settings-table widefat" role="presentation">
						<tbody>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Price type', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<select name="<?php echo esc_attr( $field_name_root ); ?>[price_type]"<?php disabled( $is_template, true ); ?>>
										<?php foreach ( $price_types as $val => $lbl ) : ?>
											<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $price_type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr class="pab-field-settings-table__row pab-price-row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Price', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<input type="number" step="0.01" min="0" class="short wc_input_price"
										name="<?php echo esc_attr( $field_name_root ); ?>[price]"
										value="<?php echo esc_attr( $price ); ?>"<?php disabled( $is_template, true ); ?> />
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="pab-image-swatch-display-settings <?php echo $standard_sections_hidden ? 'pab-is-hidden' : ''; ?> <?php echo 'image_swatch' === $type ? '' : 'pab-is-hidden'; ?>">
					<h4 class="pab-field-settings-heading"><?php esc_html_e( 'Image swatch appearance', 'pab' ); ?></h4>
					<table class="pab-field-settings-table widefat" role="presentation">
						<tbody>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><label for="pab-field-swatch-size-<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Swatch size', 'pab' ); ?></label></th>
								<td class="pab-field-settings-table__control">
									<select id="pab-field-swatch-size-<?php echo esc_attr( $id_suffix ); ?>" class="pab-field-image-swatch-size" name="<?php echo esc_attr( $field_name_root ); ?>[image_swatch_size]"<?php disabled( $is_template, true ); ?>>
										<option value="small" <?php selected( $image_swatch_size, 'small' ); ?>><?php esc_html_e( 'Small', 'pab' ); ?></option>
										<option value="medium" <?php selected( $image_swatch_size, 'medium' ); ?>><?php esc_html_e( 'Medium', 'pab' ); ?></option>
										<option value="large" <?php selected( $image_swatch_size, 'large' ); ?>><?php esc_html_e( 'Large', 'pab' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Tile size for preset images and the custom-upload option on the product page.', 'pab' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="pab-swatch-custom-field-settings <?php echo $standard_sections_hidden ? 'pab-is-hidden' : ''; ?> <?php echo 'image_swatch' === $type ? '' : 'pab-is-hidden'; ?>">
					<h4 class="pab-field-settings-heading"><?php esc_html_e( 'Custom image upload', 'pab' ); ?></h4>
					<table class="pab-field-settings-table widefat" role="presentation">
						<tbody>
							<tr class="pab-field-settings-table__row">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Allow upload', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<label>
										<input type="checkbox" class="pab-swatch-allow-custom-upload" name="<?php echo esc_attr( $field_name_root ); ?>[swatch_allow_custom_upload]" value="1" <?php checked( $swatch_allow_custom ); ?><?php disabled( $is_template, true ); ?> />
										<?php esc_html_e( 'Let buyers upload their own image instead of choosing a preset swatch', 'pab' ); ?>
									</label>
								</td>
							</tr>
							<tr class="pab-field-settings-table__row pab-swatch-custom-label-row <?php echo $swatch_allow_custom ? '' : 'pab-is-hidden'; ?>">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Label for upload choice', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<input type="text" class="regular-text" name="<?php echo esc_attr( $field_name_root ); ?>[swatch_custom_label]"
										value="<?php echo esc_attr( $swatch_custom_label ); ?>" placeholder="<?php echo esc_attr__( 'e.g. Upload your own', 'pab' ); ?>"<?php disabled( $is_template, true ); ?> />
									<p class="description"><?php esc_html_e( 'Shown as an extra swatch tile next to your preset images.', 'pab' ); ?></p>
								</td>
							</tr>
							<tr class="pab-field-settings-table__row pab-swatch-custom-price-row <?php echo ( 'per_option' === $choice_mode && $swatch_allow_custom ) ? '' : 'pab-is-hidden'; ?>">
								<th scope="row" class="pab-field-settings-table__label"><?php esc_html_e( 'Price for upload choice', 'pab' ); ?></th>
								<td class="pab-field-settings-table__control">
									<input type="number" step="0.01" min="0" class="short wc_input_price" name="<?php echo esc_attr( $field_name_root ); ?>[swatch_custom_price]"
										value="<?php echo esc_attr( $swatch_custom_price ); ?>"<?php disabled( $is_template, true ); ?> />
									<p class="description"><?php esc_html_e( 'Used when “Individual price per choice” is selected. Otherwise the field price above applies to all choices.', 'pab' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="pab-options-section <?php echo $standard_sections_hidden ? 'pab-is-hidden' : ''; ?> <?php echo $has_options ? '' : 'pab-is-hidden'; ?>">
					<div class="pab-options-panel">
						<h4 class="pab-field-settings-heading"><?php esc_html_e( 'Choices', 'pab' ); ?></h4>
						<p class="description pab-option-prices-desc <?php echo ( $choice_mode === 'uniform' ) ? 'pab-is-hidden' : ''; ?>"><?php esc_html_e( 'Optional flat price for each choice (only used when “Individual price per choice” is selected).', 'pab' ); ?></p>
						<div class="pab-options-list">
							<?php if ( $has_options ) : ?>
								<?php $this->render_options_table_header( $type, $choice_mode ); ?>
							<?php endif; ?>
							<?php foreach ( $options as $opt_i => $opt ) : ?>
								<?php $this->render_option_row( $field_name_root, $opt_i, $opt, $type, $is_template, $choice_mode ); ?>
							<?php endforeach; ?>
						</div>
						<p class="form-field pab-options-actions">
							<button type="button" class="button button-secondary pab-add-option"><?php esc_html_e( 'Add option', 'pab' ); ?></button>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_option_row( $field_name_root, $opt_index, $opt, $type, $is_template = false, $choice_mode = 'per_option' ) {
		$opt_id           = isset( $opt['id'] ) ? sanitize_key( (string) $opt['id'] ) : '';
		if ( '' === $opt_id ) {
			$opt_id = '__PAB_OPT_ID__';
		}
		$opt_label        = isset( $opt['label'] ) ? $opt['label'] : '';
		$opt_price        = isset( $opt['price'] ) ? $opt['price'] : '';
		$opt_image        = isset( $opt['image'] ) ? $opt['image'] : '';
		$is_swatch        = ( 'image_swatch' === $type );
		$hide_opt_price   = ( 'uniform' === $choice_mode );
		$placeholder_lbl  = __( 'e.g. Medium', 'pab' );
		$placeholder_amt  = __( 'Amount', 'pab' );
		$opt_id_suffix    = preg_replace( '/[^a-zA-Z0-9_-]+/', '-', (string) $field_name_root . '-' . (string) $opt_index );
		?>
		<div class="pab-option-line pab-option-row-flex" role="row">
			<input type="hidden" class="pab-option-id" name="<?php echo esc_attr( $field_name_root ); ?>[options][<?php echo esc_attr( $opt_index ); ?>][id]" value="<?php echo esc_attr( $opt_id ); ?>"<?php disabled( $is_template, true ); ?> />
			<div class="pab-option-col pab-option-col-label">
				<label class="screen-reader-text" for="pab-opt-lbl-<?php echo esc_attr( $opt_id_suffix ); ?>"><?php esc_html_e( 'Choice label', 'pab' ); ?></label>
				<input type="text" id="pab-opt-lbl-<?php echo esc_attr( $opt_id_suffix ); ?>" class="regular-text pab-option-label-input" placeholder="<?php echo esc_attr( $placeholder_lbl ); ?>"
					name="<?php echo esc_attr( $field_name_root ); ?>[options][<?php echo esc_attr( $opt_index ); ?>][label]"
					value="<?php echo esc_attr( $opt_label ); ?>"<?php disabled( $is_template, true ); ?> />
			</div>
			<div class="pab-option-col pab-option-col-price <?php echo $hide_opt_price ? 'pab-is-hidden' : ''; ?>">
				<label class="screen-reader-text" for="pab-opt-prc-<?php echo esc_attr( $opt_id_suffix ); ?>"><?php esc_html_e( 'Price', 'pab' ); ?></label>
				<input type="number" id="pab-opt-prc-<?php echo esc_attr( $opt_id_suffix ); ?>" step="0.01" min="0" class="small-text pab-option-price-input wc_input_price" placeholder="<?php echo esc_attr( $placeholder_amt ); ?>"
					name="<?php echo esc_attr( $field_name_root ); ?>[options][<?php echo esc_attr( $opt_index ); ?>][price]"
					value="<?php echo esc_attr( $opt_price ); ?>"<?php disabled( $is_template, true ); ?> />
			</div>
			<div class="pab-option-col pab-option-col-image <?php echo $is_swatch ? '' : 'pab-is-hidden'; ?>">
				<input type="hidden" class="pab-option-image-url"
					name="<?php echo esc_attr( $field_name_root ); ?>[options][<?php echo esc_attr( $opt_index ); ?>][image]"
					value="<?php echo esc_attr( $opt_image ); ?>"<?php disabled( $is_template, true ); ?> />
				<div class="pab-option-image-tools">
					<?php if ( $opt_image ) : ?>
						<img src="<?php echo esc_url( $opt_image ); ?>" alt="" class="pab-option-preview" />
					<?php else : ?>
						<img src="" alt="" class="pab-option-preview is-empty" role="presentation" />
					<?php endif; ?>
					<button type="button" class="button button-small button-secondary pab-select-image"><?php esc_html_e( 'Choose image', 'pab' ); ?></button>
				</div>
			</div>
			<div class="pab-option-col pab-option-col-actions">
				<button type="button" class="button-link-delete pab-remove-option" title="<?php esc_attr_e( 'Remove this choice', 'pab' ); ?>" aria-label="<?php esc_attr_e( 'Remove option', 'pab' ); ?>">&times;</button>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Section 2: Child Products
	// -------------------------------------------------------------------------
	private function render_child_products_section() {
		$child_layout = get_post_meta( $this->product_id, '_pab_child_layout', true );
		$child_layout = PAB_Display_Children::sanitize_layout( $child_layout );
		?>
		<div class="options_group">
			<p class="form-field pab-section-intro">
				<strong><?php esc_html_e( 'Child products (composite)', 'pab' ); ?></strong>
				<span class="description"><?php esc_html_e( 'Optional extra products or variations bundled with this product.', 'pab' ); ?></span>
			</p>

			<p class="form-field pab-child-layout-field">
				<label><?php esc_html_e( 'Storefront layout for extras', 'pab' ); ?></label>
				<span class="pab-child-layout-field__controls">
					<span class="description"><?php esc_html_e( 'How bundled extras appear on the product page.', 'pab' ); ?></span>
					<span class="pab-layout-radios" role="radiogroup" aria-label="<?php esc_attr_e( 'Storefront layout for extras', 'pab' ); ?>">
						<span class="pab-layout-radio-row">
							<input id="pab-child-layout-default" type="radio" name="pab_child_layout" value="default" <?php checked( $child_layout, 'default' ); ?> />
							<label class="pab-layout-radio-label" for="pab-child-layout-default"><?php esc_html_e( 'List row (default)', 'pab' ); ?></label>
						</span>
						<span class="pab-layout-radio-row">
							<input id="pab-child-layout-swatch" type="radio" name="pab_child_layout" value="image_swatch" <?php checked( $child_layout, 'image_swatch' ); ?> />
							<label class="pab-layout-radio-label" for="pab-child-layout-swatch"><?php esc_html_e( 'Image swatch', 'pab' ); ?></label>
						</span>
						<span class="pab-layout-radio-row">
							<input id="pab-child-layout-card" type="radio" name="pab_child_layout" value="product_card" <?php checked( $child_layout, 'product_card' ); ?> />
							<label class="pab-layout-radio-label" for="pab-child-layout-card"><?php esc_html_e( 'Product card', 'pab' ); ?></label>
						</span>
					</span>
				</span>
			</p>

			<div id="pab-child-products-list" class="pab-repeater-list">
				<?php foreach ( $this->child_products as $index => $child ) : ?>
					<?php $this->render_child_row( $index, $child ); ?>
				<?php endforeach; ?>
			</div>
			<p class="form-field">
				<button type="button" class="button button-primary pab-add-child-product"><?php esc_html_e( 'Add child product', 'pab' ); ?></button>
			</p>
		</div>

		<div id="pab-tmpl-child-row" style="display:none">
			<?php $this->render_child_row( '__PAB_CHILD_INDEX__', [], true ); ?>
		</div>
		<?php
	}

	private function render_child_row( $index, $child, $is_template = false ) {
		$product_id   = isset( $child['product_id'] ) ? (int) $child['product_id'] : 0;
		$is_variable  = isset( $child['is_variable'] ) ? (bool) $child['is_variable'] : false;
		$min_qty      = isset( $child['min_qty'] ) ? (int) $child['min_qty'] : 0;
		$max_qty      = isset( $child['max_qty'] ) ? (int) $child['max_qty'] : 1;
		$required     = isset( $child['required'] ) && $child['required'] ? 'checked' : '';
		$override     = isset( $child['override_price'] ) ? $child['override_price'] : '';
		$product_name = $product_id ? get_the_title( $product_id ) : '';
		$allowed_vars = isset( $child['allowed_variations'] ) ? $child['allowed_variations'] : [];
		?>
		<div class="pab-settings-card pab-child-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="pab-settings-card__header">
				<span class="dashicons dashicons-move pab-drag-handle" aria-hidden="true"></span><button type="button" class="pab-move-btn pab-move-up" aria-label="Move up" title="Move up">▲</button><button type="button" class="pab-move-btn pab-move-down" aria-label="Move down" title="Move down">▼</button>
				<span class="pab-settings-card__title pab-row-label"><?php echo $product_name ? esc_html( $product_name ) : esc_html__( 'New Child Product', 'pab' ); ?></span>
				<button type="button" class="button button-small pab-settings-card__toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Expand or collapse this panel', 'pab' ); ?>">
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</button>
			</div>
			<div class="pab-settings-card__body pab-row-body">
				<p class="form-field pab-field-line">
					<label><?php esc_html_e( 'Product', 'pab' ); ?></label>
					<select class="pab-child-product-select wc-product-search short"
						name="pab_child_products[<?php echo esc_attr( $index ); ?>][product_id]"
						data-placeholder="<?php esc_attr_e( 'Search for a product…', 'pab' ); ?>"
						data-action="woocommerce_json_search_products_and_variations"
						data-index="<?php echo esc_attr( $index ); ?>"<?php disabled( $is_template, true ); ?>>
						<?php if ( $product_id ) : ?>
							<option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( $product_name ); ?></option>
						<?php endif; ?>
					</select>
					<input type="hidden" name="pab_child_products[<?php echo esc_attr( $index ); ?>][is_variable]"
						class="pab-child-is-variable" value="<?php echo $is_variable ? '1' : '0'; ?>"<?php disabled( $is_template, true ); ?> />
				</p>

				<div class="pab-variation-section <?php echo $is_variable ? '' : 'pab-is-hidden'; ?>">
					<p class="form-field">
						<label><?php esc_html_e( 'Allowed variations', 'pab' ); ?></label>
						<span class="description"><?php esc_html_e( 'Restrict which variations buyers can add.', 'pab' ); ?></span>
					</p>
					<?php $variation_payload = ( $product_id && $is_variable ) ? PAB_Data::get_variation_payload( $product_id ) : []; ?>
					<div class="pab-variation-list"
						data-variations="<?php echo esc_attr( wp_json_encode( $variation_payload ) ); ?>"
						data-selected="<?php echo esc_attr( wp_json_encode( array_map( 'absint', (array) $allowed_vars ) ) ); ?>">
					</div>
				</div>

				<p class="form-field pab-field-line">
					<label><?php esc_html_e( 'Min quantity', 'pab' ); ?></label>
					<input type="number" min="0" class="short"
						name="pab_child_products[<?php echo esc_attr( $index ); ?>][min_qty]"
						value="<?php echo esc_attr( $min_qty ); ?>"<?php disabled( $is_template, true ); ?> />
				</p>

				<p class="form-field pab-field-line">
					<label><?php esc_html_e( 'Max quantity', 'pab' ); ?></label>
					<input type="number" min="1" class="short"
						name="pab_child_products[<?php echo esc_attr( $index ); ?>][max_qty]"
						value="<?php echo esc_attr( $max_qty ); ?>"<?php disabled( $is_template, true ); ?> />
				</p>

				<p class="form-field pab-field-line">
					<label><?php esc_html_e( 'Required', 'pab' ); ?></label>
					<input type="checkbox" class="checkbox" name="pab_child_products[<?php echo esc_attr( $index ); ?>][required]"
						value="1" <?php echo $required; ?><?php disabled( $is_template, true ); ?> />
				</p>

				<p class="form-field pab-field-line">
					<label><?php esc_html_e( 'Override price', 'pab' ); ?></label>
					<input type="number" step="0.01" min="0" class="short wc_input_price" placeholder="<?php esc_attr_e( 'Leave empty to use product price', 'pab' ); ?>"
						name="pab_child_products[<?php echo esc_attr( $index ); ?>][override_price]"
						value="<?php echo esc_attr( $override ); ?>"<?php disabled( $is_template, true ); ?> />
				</p>

				<p class="form-field pab-remove-field-wrap">
					<button type="button" class="button-link-delete pab-remove-row" aria-label="<?php esc_attr_e( 'Remove child product', 'pab' ); ?>"><?php esc_html_e( 'Remove child product', 'pab' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Section 3: Conditional Rules
	// -------------------------------------------------------------------------
	private function render_conditional_rules_section() {
		?>
		<div class="options_group">
			<p class="form-field pab-section-intro">
				<strong><?php esc_html_e( 'Conditional rules', 'pab' ); ?></strong>
				<span class="description"><?php esc_html_e( 'Adjust visibility or price on the storefront based on other field values.', 'pab' ); ?></span>
			</p>
			<div id="pab-rules-list" class="pab-repeater-list">
				<?php foreach ( $this->conditional_rules as $index => $rule ) : ?>
					<?php $this->render_rule_row( $index, $rule ); ?>
				<?php endforeach; ?>
			</div>
			<p class="form-field">
				<button type="button" class="button button-primary pab-add-rule"><?php esc_html_e( 'Add rule', 'pab' ); ?></button>
			</p>
		</div>

		<div id="pab-tmpl-rule-row" style="display:none">
			<?php $this->render_rule_row( '__PAB_RULE_INDEX__', [], true ); ?>
		</div>

		<div id="pab-addon-field-labels-data"
			data-fields="<?php echo esc_attr( wp_json_encode( array_map( function( $f ) {
				return [
					'id'    => isset( $f['id'] ) ? sanitize_key( (string) $f['id'] ) : '',
					'label' => isset( $f['label'] ) ? (string) $f['label'] : '',
				];
			}, array_values( $this->addon_fields ) ) ) ); ?>">
		</div>
		<?php
	}

	private function render_rule_row( $index, $rule, $is_template = false ) {
		$rule_id       = isset( $rule['id'] ) ? sanitize_key( (string) $rule['id'] ) : '';
		if ( '' === $rule_id ) {
			$rule_id = '__PAB_RULE_ID__';
		}
		$trigger_field = isset( $rule['trigger_field_id'] ) ? sanitize_key( (string) $rule['trigger_field_id'] ) : '';
		$operator      = isset( $rule['operator'] ) ? $rule['operator'] : 'equals';
		$value         = isset( $rule['value'] ) ? $rule['value'] : '';
		$action        = isset( $rule['action'] ) ? $rule['action'] : 'show_field';
		$action_target = isset( $rule['action_target_field_id'] ) ? sanitize_key( (string) $rule['action_target_field_id'] ) : '';
		$action_amount = isset( $rule['action_amount'] ) ? $rule['action_amount'] : '';

		$operators = [
			'equals'       => __( 'equals', 'pab' ),
			'not_equals'   => __( 'not equals', 'pab' ),
			'greater_than' => __( 'greater than', 'pab' ),
			'less_than'    => __( 'less than', 'pab' ),
		];
		$actions = [
			'show_field'          => __( 'Show Field', 'pab' ),
			'hide_field'          => __( 'Hide Field', 'pab' ),
			'add_price'           => __( 'Add Price', 'pab' ),
			'subtract_price'      => __( 'Subtract Price', 'pab' ),
			'percentage_discount' => __( 'Percentage Discount', 'pab' ),
		];
		?>
		<div class="pab-settings-card pab-rule-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="pab-settings-card__header">
				<span class="dashicons dashicons-move pab-drag-handle" aria-hidden="true"></span><button type="button" class="pab-move-btn pab-move-up" aria-label="Move up" title="Move up">▲</button><button type="button" class="pab-move-btn pab-move-down" aria-label="Move down" title="Move down">▼</button>
				<span class="pab-settings-card__title pab-row-label"><?php echo esc_html__( 'Rule', 'pab' ) . ' #' . esc_html( is_numeric( $index ) ? $index : '' ); ?></span>
				<button type="button" class="button button-small pab-settings-card__toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Expand or collapse this panel', 'pab' ); ?>">
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</button>
			</div>
			<div class="pab-settings-card__body pab-row-body pab-rule-row-body">
				<input type="hidden" class="pab-rule-id" name="pab_conditional_rules[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $rule_id ); ?>"<?php disabled( $is_template, true ); ?> />
				<p class="form-field form-field-wide">
					<span class="pab-rule-inline-label"><?php esc_html_e( 'IF', 'pab' ); ?></span>
					<select name="pab_conditional_rules[<?php echo esc_attr( $index ); ?>][trigger_field_id]" class="pab-rule-trigger-field"<?php disabled( $is_template, true ); ?>>
						<option value=""><?php esc_html_e( '— Select field —', 'pab' ); ?></option>
						<?php foreach ( $this->addon_fields as $fi => $f ) : ?>
							<?php $field_id = isset( $f['id'] ) ? sanitize_key( (string) $f['id'] ) : ''; ?>
							<option value="<?php echo esc_attr( $field_id ); ?>" <?php selected( (string) $trigger_field, (string) $field_id ); ?>>
								<?php echo esc_html( isset( $f['label'] ) && '' !== (string) $f['label'] ? $f['label'] : __( 'Field', 'pab' ) . ' ' . $fi ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<select name="pab_conditional_rules[<?php echo esc_attr( $index ); ?>][operator]"<?php disabled( $is_template, true ); ?>>
						<?php foreach ( $operators as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $operator, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>

					<input type="text" class="short" placeholder="<?php esc_attr_e( 'Value', 'pab' ); ?>"
						name="pab_conditional_rules[<?php echo esc_attr( $index ); ?>][value]"
						value="<?php echo esc_attr( $value ); ?>"<?php disabled( $is_template, true ); ?> />

					<span class="pab-rule-inline-label"><?php esc_html_e( 'THEN', 'pab' ); ?></span>

					<select name="pab_conditional_rules[<?php echo esc_attr( $index ); ?>][action]" class="pab-rule-action"<?php disabled( $is_template, true ); ?>>
						<?php foreach ( $actions as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $action, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>

					<select name="pab_conditional_rules[<?php echo esc_attr( $index ); ?>][action_target_field_id]" class="pab-rule-action-target <?php echo in_array( $action, [ 'show_field', 'hide_field' ], true ) ? '' : 'pab-is-hidden'; ?>"<?php disabled( $is_template, true ); ?>>
						<option value=""><?php esc_html_e( '— Target field —', 'pab' ); ?></option>
						<?php foreach ( $this->addon_fields as $fi => $f ) : ?>
							<?php $field_id = isset( $f['id'] ) ? sanitize_key( (string) $f['id'] ) : ''; ?>
							<option value="<?php echo esc_attr( $field_id ); ?>" <?php selected( (string) $action_target, (string) $field_id ); ?>>
								<?php echo esc_html( isset( $f['label'] ) && '' !== (string) $f['label'] ? $f['label'] : __( 'Field', 'pab' ) . ' ' . $fi ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="text" class="short pab-rule-action-amount <?php echo in_array( $action, [ 'show_field', 'hide_field' ], true ) ? 'pab-is-hidden' : ''; ?>" placeholder="<?php esc_attr_e( 'Amount', 'pab' ); ?>"
						name="pab_conditional_rules[<?php echo esc_attr( $index ); ?>][action_amount]"
						value="<?php echo esc_attr( $action_amount ); ?>"<?php disabled( $is_template, true ); ?> />
				</p>
				<p class="form-field pab-remove-field-wrap">
					<button type="button" class="button-link-delete pab-remove-row" aria-label="<?php esc_attr_e( 'Remove rule', 'pab' ); ?>"><?php esc_html_e( 'Remove rule', 'pab' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

}
