# PAB Addon Groups Test Checklist

## Admin settings

- Confirm `WooCommerce -> Infinia Product Addons and Bundles` shows tabs: `General`, `Addon Groups`, `Assignments`, and `Help`.
- Create a new addon group with valid addon-fields JSON and verify it appears in the group list.
- Edit an existing group and confirm status changes between enabled and disabled.
- Delete a group and confirm it is removed from list and assignment options.

## Assignment rules

- In `Assignments`, create one product-level assignment and confirm it saves.
- Create one `product_cat` assignment and one `product_tag` assignment with different priorities.
- Disable one assignment and confirm it is ignored by runtime resolution.
- Set conflicting priorities and confirm lower numeric priority is applied earlier.

## Product editor

- Open a product, assign one or more groups in `Add-ons & Composite -> Applied addon groups`, set custom priorities, and save.
- Reload product edit screen and confirm direct assignments persisted.

## Storefront behavior

- Product with only legacy `_addon_fields` still renders correctly (backward compatibility).
- Product with direct group assignment renders group fields.
- Product matching category and tag assignments resolves fields according to priority.
- Confirm product-level addon fields still merge in and remain functional.

## Cart and pricing

- Add product with group-driven addon selections to cart and verify addon metadata is stored.
- Confirm addon surcharges from resolved group fields are reflected in cart totals.
- Verify file/image addon uploads from group fields are accepted and shown in cart line item metadata.
