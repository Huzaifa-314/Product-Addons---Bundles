# Product Addons & Bundles (PAB)

WooCommerce extension that adds configurable add-on fields, composite/child product behavior, and conditional pricing. Text domain: `pab`.

## Requirements

- WordPress 6.8+
- PHP 7.4+
- WooCommerce 7.0+ (tested up to WooCommerce 10.6)

The plugin does not load if WooCommerce is inactive; an admin notice is shown instead.

## Installation

1. Copy the `product-addons-bundles` folder into `wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Ensure **WooCommerce** is installed and active.

## Configuration

- **Global settings:** **WooCommerce → Product Addons & Bundles** (found under the PAB menu item) — general options, addon groups, assignments (e.g. by product, category, tag, priority), and help.
- **Per product:** edit a product and open the **Add-ons & Composite** tab to apply addon groups and priorities.

Addon groups are managed as their own post type in the admin; assignments control which groups apply to which products or taxonomies.

## Storefront & cart

- Resolved add-on fields render on the product page (including legacy `_addon_fields` where applicable).
- Composite/child product display and AJAX behavior are handled on the frontend.
- Cart hooks apply add-on surcharges and preserve add-on metadata (including file/image uploads where configured).

## High-Performance Order Storage (HPOS)

The plugin declares compatibility with WooCommerce custom order tables (HPOS).

## Development

Additional notes and checklists live under `docs/` (for example `docs/pab-addon-groups-test-checklist.md`).

## Version

See the plugin header in `product-addons-bundles.php` for the current version constant (`PAB_VERSION`).
