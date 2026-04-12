# Product Addons & Bundles Admin UI Audit

## Current Surfaces
- `WooCommerce > Product edit > Add-ons & Composite` tab is the main builder.
- `WooCommerce > Product Addons & Bundles` settings screen is now available for plugin-level controls and future reusable bundle management.

## Existing/Updated Architecture
- PHP shell renderer: `admin/class-pab-admin.php` and `admin/class-pab-product-tab.php`.
- Shared data normalization: `includes/class-pab-data.php`.
- Save sanitization: `admin/class-pab-save-fields.php`.
- AJAX for variations: `includes/class-pab-ajax.php`.
- Admin interactions: `assets/js/admin.js`.

## High-Risk Areas Addressed
- Rules previously depended on array indexes; this now uses stable IDs.
- Variation checkboxes were rendered in both PHP and JS; now one canonical JS renderer consumes JSON from both initial markup and AJAX.
- Repeater rows lacked ordering and quick filtering; drag handles, sortable behavior, and field search were added.

## Remaining Iteration Targets
- Add reusable bundle CRUD (list table + actions) in settings.
- Add help onboarding cards/screenshots in the Help tab.
- Add integration tests for save/migration on complex product configs.
