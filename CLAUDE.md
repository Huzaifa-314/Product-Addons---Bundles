# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Product Addons & Bundles (PAB) is a WooCommerce extension that adds configurable add-on fields, composite/child product selection, and conditional pricing rules to WooCommerce products. Text domain: `pab`. Version is defined in both the plugin header and `PAB_VERSION` constant in `product-addons-bundles.php` — update both together.

## Requirements

- WordPress 6.8+, PHP 7.4+, WooCommerce 7.0+
- The plugin exits early with an admin notice if WooCommerce is inactive

## Build & Development

There is **no build tooling** — no package.json, composer.json, webpack, or minification. JS and CSS files in `assets/` are served directly. Edit them as-is.

There is **no test suite** — no PHPUnit, no JS tests. Manual test checklist lives at `docs/pab-addon-groups-test-checklist.md`.

There is **no autoloader** — all class files are manually `require_once`d in `pab_load()`. New classes must be added there.

## Architecture

Class-based procedural pattern. Each class hooks into WordPress/WooCommerce filters and actions in its constructor. No routing layer, no template engine, no inheritance between PAB classes.

### Boot Sequence

`plugins_loaded` → `pab_check_woocommerce()` → `pab_load()` which requires and instantiates all classes. HPOS compatibility is declared on `before_woocommerce_init`.

### Data Flow

1. **Admin saves** field/child/rule configs as JSON into post meta
2. **Frontend** reads JSON from post meta, passes it to JS via `wp_localize_script` as `pabData`
3. **frontend.js** computes live prices client-side
4. **PAB_Cart_Hooks** validates and applies surcharges server-side on cart operations

### Class Map

| Class | Location | Role |
|-------|----------|------|
| `PAB_Data` | `includes/` | Shared helpers: JSON decode, UUID generation, field normalization, variation payloads |
| `PAB_Group_Resolver` | `includes/` | `pab_addon_group` CPT, location rule evaluation, field resolution/merge by priority. Singleton via `::init()` |
| `PAB_Cart_Hooks` | `includes/` | 6 WooCommerce cart hooks: validation, item data, session restore, price calculation, cart display, order meta |
| `PAB_Ajax` | `includes/` | Single admin AJAX endpoint `pab_get_variations` |
| `PAB_Admin` | `admin/` | Settings page under WooCommerce menu, CPT metaboxes, asset enqueue |
| `PAB_Product_Tab` | `admin/` | Product data tab UI: field builder, child products, conditional rules. Uses `<script type="text/html">` templates |
| `PAB_Save_Fields` | `admin/` | POST sanitization and JSON meta persistence on product save |
| `PAB_Frontend` | `frontend/` | Conditional asset enqueue, builds `pabData` JS object, renders addon/child containers |
| `PAB_Display_Fields` | `frontend/` | Renders addon field HTML (text, select, checkbox, radio, number, file/image upload, swatches) |
| `PAB_Display_Children` | `frontend/` | Renders composite product cards (3 layouts: default, image_swatch, product_card) |

### Post Meta Keys

All data is stored as JSON in post meta — no custom database tables.

| Meta Key | On | Content |
|----------|----|---------|
| `_addon_fields` | Product | Addon field configs |
| `_child_products` | Product | Child product configs |
| `_conditional_rules` | Product | Conditional rule configs |
| `_pab_child_layout` | Product | Layout mode (default/image_swatch/product_card) |
| `_pab_product_group_assignments` | Product | Group assignments with priority |
| `_pab_group_addon_fields` | Addon Group CPT | Field configs (same schema as `_addon_fields`) |
| `_pab_group_location_rules` | Addon Group CPT | ACF-style location rules |
| `_pab_group_products` | Addon Group CPT | Assigned product IDs |

Global settings stored in option `pab_settings`.

### Group Resolution

`PAB_Group_Resolver` merges addon fields from multiple sources: explicit group assignments on the product + location rules on groups (matching taxonomy terms with == / != operators). Fields are sorted by group priority (lower number first); product-specific `_addon_fields` merge last by ID.

### Frontend JS (`assets/js/frontend.js`)

- jQuery-based live price calculator
- Conditional rule evaluation (show/hide fields based on other field values)
- File upload drag-and-drop with `URL.createObjectURL` previews
- Image swatch toggle (click-to-deselect on radio buttons)
- Currency formatting matches WooCommerce's `wc_price()` output
- Receives all data via `wp_localize_script('pab-frontend', 'pabData', {...})`

### Admin JS (`assets/js/admin.js`)

- jQuery repeater UI with `<script type="text/html">` template cloning
- jQuery UI Sortable for drag-and-drop reordering
- Select2/SelectWoo for product search and taxonomy term search
- WP Media modal for swatch image selection

### CSS Notes

- Admin CSS scoped to `#pab_addons_data` and `#pab_group_addons`, uses CSS custom properties (`--pab-surface`, `--pab-border`, etc.)
- Frontend CSS intentionally inherits WooCommerce/theme styles; uses WoodMart CSS variables (`--wd-primary-color`, etc.)
- Responsive breakpoint at 480px

## WooCommerce Hook Integration

The plugin integrates entirely through WooCommerce hooks:

| Hook | Purpose |
|------|---------|
| `woocommerce_product_data_tabs` | Add product data tab |
| `woocommerce_product_data_panels` | Render tab panel |
| `woocommerce_process_product_meta` | Save addon/child/rule data |
| `woocommerce_before_add_to_cart_button` | Render addon fields and child products |
| `woocommerce_add_to_cart_validation` | Validate swatch exclusivity, custom uploads |
| `woocommerce_add_cart_item_data` | Capture addon values and child selections |
| `woocommerce_get_cart_item_from_session` | Restore data from session |
| `woocommerce_before_calculate_totals` | Apply addon surcharges and child prices |
| `woocommerce_get_item_data` | Display in cart |
| `woocommerce_checkout_create_order_line_item` | Persist to order meta |
