# Product Addons & Bundles Admin UI Pattern Guide

## Navigation
- Use WooCommerce-native tabs (`nav-tab-wrapper`) for top-level plugin settings sections.
- Keep product-level builder inside WooCommerce product data tabs for continuity.

## Section Structure
- Start each section with a concise title and one-line description.
- Use card rows for repeatable entities (addon field, child product, rule).
- Keep row actions consistent: collapse, remove, drag to reorder.

## Form Patterns
- Use native WooCommerce field styles and spacing.
- Use inline helper text under advanced controls only.
- Use searchable/filterable repeaters when rows can exceed 5 items.

## Validation and Safety
- Sanitize all incoming values server-side in one place.
- Always persist stable IDs (`field`, `option`, `rule`) for reorder-safe rule mapping.
- Gate admin AJAX endpoints with capability + nonce checks.

## Conditional Rules
- Rule trigger/target selectors must store field IDs, not row indexes.
- Price action values are numeric amounts; field actions use target-field selectors.
- Hide/show behavior should map by `data-field-id` in frontend and admin previews.

## Accessibility
- Keep collapse buttons keyboard focusable with `aria-expanded`.
- Use screen-reader labels for option-row form controls.
- Ensure drag handles remain optional; all actions must still be possible without drag.
