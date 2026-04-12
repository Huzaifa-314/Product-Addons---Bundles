# Minimal WooCommerce Product Add‑on + Composite Plugin (Buildable in ~5 Hours with AI)

## Goal
Build a **minimal but user‑friendly WooCommerce plugin** that allows:
1. Add simple product add‑ons (extra options with price)
2. Add child products under a main product (simple composite)
3. Conditional pricing (basic rules only)

This is **NOT a full competitor** to big plugins. This is a **lean MVP** that works reliably and can be extended later.

---

# Core Features (Only What Matters for MVP)

## 1. Product Add‑ons (Per Product)
User can add fields to a product:

**Field Types (MVP but powerful):**
- Text input
- Textarea
- Select dropdown
- Checkbox
- Radio button
- Number input
- **File upload**
- **Image upload**
- **Image swatch (select by image)**
- **Text swatch (button style options)**

**Pricing Types:**
- Flat price
- Percentage price
- Per quantity price

**Other Options:**
- Required field toggle
- Field label
- Price label
- Image preview

---

## 2. Simple Composite Products (Child Products)
Inside a product, admin can add **child products**.
Customer can select them before adding to cart.

**IMPORTANT:** Must support:
- Simple products
- **Variable products**
- Customer must be able to choose **variation** before adding to cart

**Child Product Options:**
- Select product
- If variable → Select variation attributes
- Set min quantity
- Set max quantity
- Optional / Required
- Override price (optional)

This is NOT a multi‑step composite. Just a simple "Add extra products with this product" system.

---

## 3. Basic Conditional Logic (Simple Version Only)
Only support simple rules like:

| Condition | Action |
|----------|--------|
| If Checkbox A checked | Show Field B |
| If Select = Option X | Add $10 |
| If Quantity > 5 | Apply 10% discount |

**Rule Structure:**
```
IF field/operator/value
THEN action (show/hide/change price)
```

Operators:
- equals
- not equals
- greater than
- less than

Actions:
- Show field
- Hide field
- Add price
- Subtract price
- Percentage discount

---

# Admin UI (Keep It Very Simple)
Add a new tab in product edit page:

**Product Edit Page → "Add‑ons & Composite" Tab**

Sections:
1. Add‑on Fields
2. Child Products
3. Conditional Rules

Use **repeater fields** style UI:

```
[ Add Field ]
Field Type: Select
Label: Size
Options: Small | Medium | Large
Price: 5

[ Add Child Product ]
Product: T‑Shirt
Min Qty: 0
Max Qty: 2

[ Add Rule ]
IF: Size = Large
THEN: Add Price = 10
```

No drag‑drop builder for MVP.
Just repeater rows.

---

# Frontend UI (Product Page)
Show before Add to Cart button:

```
Product Name
Price: $100

---- Customize ----
Size: (Small / Medium / Large)
Custom Text: [________]
Gift Wrap: [x] +$5

---- Add Extras ----
Extra Battery (+$20) [Qty]
Carry Bag (+$15) [Qty]

Total Price: $145

[ Add to Cart ]
```

Price updates using JavaScript.

---

# Database (Keep SIMPLE – Use Post Meta Only)
Do NOT create custom tables for MVP.

Save everything in:
```
_post_meta:
_addon_fields
_child_products
_conditional_rules
```

## Addon Field JSON Example
```json
_addon_fields = [
  {
    "type": "image_swatch",
    "label": "Choose Color",
    "options": [
      {"label": "Red", "price": 5, "image": "url"},
      {"label": "Blue", "price": 0, "image": "url"}
    ]
  },
  {
    "type": "file_upload",
    "label": "Upload Logo",
    "price": 10
  }
]
```

## Child Products JSON Example
```json
_child_products = [
  {
    "product_id": 123,
    "is_variable": true,
    "allowed_variations": [12,13,14],
    "min_qty": 0,
    "max_qty": 2,
    "override_price": ""
  }
]
```

This keeps development FAST.

---
_post_meta:
_addon_fields
_child_products
_conditional_rules
```

Use JSON structure like:

```json
_addon_fields = [
  {
    "type": "select",
    "label": "Size",
    "options": [
      {"label": "Small", "price": 0},
      {"label": "Large", "price": 10}
    ]
  }
]
```

This keeps development FAST.

---

# WooCommerce Hooks Needed

| Purpose | Hook |
|--------|------|
| Show fields on product page | woocommerce_before_add_to_cart_button |
| Save addon data | woocommerce_add_cart_item_data |
| Load cart data | woocommerce_get_cart_item_from_session |
| Change price | woocommerce_before_calculate_totals |
| Show in cart | woocommerce_get_item_data |
| Save in order | woocommerce_checkout_create_order_line_item |

These 6 hooks are enough for MVP.

---

# File Structure (Simple Plugin)

```
product-addon-composite/
│
├── product-addon-composite.php
├── admin/
│   ├── product-tab.php
│   └── save-fields.php
├── frontend/
│   ├── display-fields.php
│   ├── price-calculation.php
│   └── scripts.js
├── includes/
│   └── cart-hooks.php
└── assets/
    ├── js/
    └── css/
```

---

# 5 Hour Build Timeline (Very Important)

| Time | Task |
|-----|------|
| Hour 1 | Create plugin, add product tab, save fields |
| Hour 2 | Show fields on frontend |
| Hour 3 | Add to cart + save addon data |
| Hour 4 | Price calculation + child products |
| Hour 5 | Conditional logic (basic) + testing |

---

# MVP Limitations (Accept These)

To finish in 5 hours, DO NOT include:
- Drag & drop builder
- Multi‑step composite
- File upload
- Color swatches
- Formula pricing
- Global add‑ons
- Bundle stock sync
- REST API
- Multi-language

These can be added later.

---

# Final MVP Feature Summary

| Feature | Included |
|--------|----------|
| Text field | Yes |
| Select field | Yes |
| Checkbox | Yes |
| File upload | Yes |
| Image upload | Yes |
| Image swatch | Yes |
| Text swatch | Yes |
| Price add | Yes |
| Percentage price | Yes |
| Child products | Yes |
| Variable child products | Yes |
| Variation selection | Yes |
| Quantity for child | Yes |
| Conditional show/hide | Yes |
| Conditional pricing | Yes |
| Live price update | Yes |
| Save in order | Yes |

--------|----------|
| Text field | Yes |
| Select field | Yes |
| Checkbox | Yes |
| Price add | Yes |
| Percentage price | Yes |
| Child products | Yes |
| Quantity for child | Yes |
| Conditional show/hide | Yes |
| Conditional pricing | Yes |
| Live price update | Yes |
| Save in order | Yes |

---

# After MVP (Version 2)
Add later:
- Drag drop builder
- Global add-ons
- Swatches
- File upload
- Multi-step composite
- Discount rules
- Import/export
- Template builder

---

# Simple Architecture

```
Admin → Save JSON
Frontend → Load JSON
JS → Update Price
Cart → Add extra price
Order → Save metadata
```

Keep everything JSON-based for flexibility.

---

# Recommendation

If using AI (Claude/Cursor), build in this order:
1. Product custom tab
2. Repeater fields
3. Frontend display
4. Add to cart data
5. Price calculation
6. Child products
7. Conditional logic

**Do NOT try to build everything at once. Build feature by feature.**

