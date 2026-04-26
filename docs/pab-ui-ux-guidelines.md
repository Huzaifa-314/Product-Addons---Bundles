# PAB UI/UX Guidelines — Infinia Product Addons and Bundles

> Audit findings, design system rules, component standards, and implementation guidance for the PAB admin interface.

---

## Part 1: UI/UX Audit Findings

### Screen A: Product Edit → Add-ons & Composite Tab

#### A1: Group Assignments

- ❌ **No remove button per row** — Users must change the dropdown to "Select group" (value 0) to remove an assignment. This is not discoverable. (`admin/class-pab-product-tab.php:59`)
- ❌ **Inline style on table** — `style="margin-bottom:16px;"` on the `<table>` element (`admin/class-pab-product-tab.php:46`)
- ❌ **Trailing empty row as "add" mechanism** — Not obvious; no "Add group" button exists
- ✅ Add a red "Remove" link per row (matching the pattern in location rules). Replace the trailing empty row with an explicit "Add group assignment" button.
- 💡 *Visibility of system status* — Users need a clear affordance for adding and removing items.
- 🔧 (1) Add a `pab-remove-assignment-row` button to each `<tr>`. (2) Replace the appended empty-row with a button below the table. (3) Move `margin-bottom:16px` to the CSS class `pab-assignments-table`.

#### A2: Add-on Fields (Field Builder)

- ❌ **Inline style on field type select** — `style="width: 260px;"` (`admin/class-pab-product-tab.php:91`)
- ❌ **No confirmation on delete** — Clicking "Delete" immediately removes the addon row (`assets/js/admin.js:502-509`). This is the highest-risk deletion (entire field + all options).
- ❌ **Inconsistent accordion** — Adding a new addon row collapses all others (`admin.js:261-262`), but adding a child/rule row does not collapse siblings.
- ❌ **Drag handle has no keyboard alternative** — `aria-hidden="true"` with no up/down buttons (`admin/class-pab-product-tab.php:198`)
- ✅ (1) Add `window.confirm()` before `.remove()` on `.pab-remove-row` for addon rows. (2) Move `width: 260px` to CSS class `pab-field-builder__type-select`. (3) Standardize accordion: new row opens expanded, all others collapse — apply same behavior to child/rule rows.
- 💡 *Error prevention* — Destructive actions need confirmation. Consistency reduces cognitive load.
- 🔧 In `admin.js`, the `.pab-remove-row` click handler at line 502 should check `$(this).closest('.pab-addon-row')` and prompt before removal. Add a CSS class for the type-select width.

#### A3: Child Products (Composite)

- ❌ **Inline style on product select** — `style="width: 50%; max-width: 400px;"` (`admin/class-pab-product-tab.php:481`)
- ❌ **No confirmation on delete** — "Remove child product" button removes immediately (`admin/class-pab-product-tab.php:534`)
- ❌ **No loading state on AJAX** — `pab_get_variations` AJAX call has no spinner or feedback (`admin.js:370-388`)
- ❌ **No error handling on AJAX failure** — No `.fail()` handler on the `$.post()` call (`admin.js:370`)
- ✅ (1) Move inline width to CSS. (2) Add confirmation dialog. (3) Show a spinner next to the variation section during AJAX. (4) Add `.fail()` with an admin notice.
- 💡 *Visibility of system status* — Users must see that something is loading.
- 🔧 Wrap the `$.post()` call: show spinner before, hide on success/fail, add `.fail()` callback.

#### A4: Conditional Rules

- ❌ **No confirmation on delete** — "Remove rule" button removes immediately (`admin/class-pab-product-tab.php:656`)
- ❌ **Rule body always expanded** — No collapsible behavior; rules with long condition text clutter the view
- ✅ (1) Add confirmation dialog. (2) Make rule bodies collapsible like addon rows, defaulting to collapsed when there are 3+ rules.
- 💡 *Progressive disclosure* — Collapse secondary details to reduce visual noise.
- 🔧 Add `pab-settings-card__toggle` button to rule headers and wire up the same toggle logic.

### Screen B: PAB Settings Page

- ❌ **Only 3 settings** — The General tab feels sparse; the Help tab is a single static notice
- ❌ **No inline validation** — The image upload dropzone title field has `maxlength="240"` but no character counter
- ✅ Add a character counter below the text input showing remaining characters. Consider merging Help content into a collapsible section on the General tab.
- 💡 *Reduce cognitive load* — Fewer tabs with richer content beats many sparse tabs.
- 🔧 Add a `<span class="pab-char-count">0/240</span>` and wire it with JS `input` event.

### Screen C: Addon Group CPT Editor

#### Metabox C1: Display / Location

- ❌ **Inline style on match label** — `style="margin-right:12px;"` (`admin/class-pab-admin.php:280`)
- ❌ **Inline style on term search select** — `style="width:100%;min-width:200px;"` (`admin/class-pab-admin.php:347`, also in `admin.js:728`)
- ❌ **Inline style on products select** — `style="width:100%;"` (`admin/class-pab-admin.php:266`)
- ❌ **Location rules built via string concatenation** — `buildLocationRuleRowHtml()` in `admin.js:684-733` constructs HTML via string concatenation instead of using the `cloneTemplate()` pattern used everywhere else
- ✅ (1) Move all inline styles to CSS classes. (2) Refactor `buildLocationRuleRowHtml()` to use a `<script type="text/html">` template like addon/child/rule rows.
- 💡 *Maintainability* — Templates are easier to audit and less prone to XSS than string concatenation.
- 🔧 Create a `pab-tmpl-location-rule-row` template in the metabox PHP. Replace `buildLocationRuleRowHtml()` with `cloneTemplate()`.

#### Metabox C2: Product Addons

- Reuses the same field builder as Screen A2 — same issues apply (inline style on type select, no delete confirmation, etc.)

### Cross-Cutting Issues

#### 1. Dual Style Systems (CRITICAL)

Same `.pab-settings-card` component styled differently inside vs outside `.pab-field-builder`:

| Property | Inside builder | Outside builder |
|---|---|---|
| Card border-radius | `var(--pab-radius)` = 6px | 0 (unset) |
| Card box-shadow | `var(--pab-shadow)` = `0 1px 2px rgba(0,0,0,0.06)` | `0 1px 1px rgba(0,0,0,0.04)` |
| Header background | `linear-gradient(180deg, #fafafa, #f0f0f1)` | `#f6f7f7` (flat) |
| Header min-height | 44px | 40px |
| Header gap | 10px | 8px |
| Body padding | `14px 14px 10px` | `12px 12px 4px` |
| Title line-height | 1.35 | 1.4 |

**Fix:** Unify on the builder values (they are more polished). Apply the CSS custom properties to the outer-card selectors too. Remove the duplicate rule blocks at `admin.css:497-563`.

#### 2. Inline Styles in PHP Templates

Six instances found. All should move to CSS classes:

| File:Line | Inline style | CSS class to create |
|---|---|---|
| `class-pab-product-tab.php:46` | `margin-bottom:16px` | `.pab-assignments-table` |
| `class-pab-product-tab.php:91` | `width: 260px` | `.pab-field-builder__type-select` |
| `class-pab-product-tab.php:481` | `width: 50%; max-width: 400px` | `.pab-child-product-select` |
| `class-pab-admin.php:266` | `width:100%` | `.pab-group-products-select` |
| `class-pab-admin.php:280` | `margin-right:12px` | `.pab-location-rules-match__label` |
| `class-pab-admin.php:347` | `width:100%;min-width:200px` | `.pab-location-rule-value` |

Also in JS: `admin.js:728` injects `style="width:100%;min-width:200px;"` — this will be resolved by the template refactor.

#### 3. Hardcoded Colors (20+ instances)

Colors like `#f6f7f7`, `#c3c4c7`, `#50575e`, `#8c8f94`, `#1d2327` appear in `admin.css` outside the custom property block (lines 17-33). Replace each with the corresponding `--pab-*` token:

| Hardcoded | Token | Lines in admin.css |
|---|---|---|
| `#f6f7f7` | `var(--pab-surface-muted)` | 518, 572, 680 |
| `#c3c4c7` | `var(--pab-border)` | 506, 519, 573, 681 |
| `#50575e` | `var(--pab-muted)` | 199, 318, 587 |
| `#8c8f94` | `var(--pab-muted)` | 100, 461, 526 |
| `#1d2327` | `var(--pab-text)` | 538 |
| `#dcdcde` | `var(--pab-border-subtle)` | 666 |

#### 4. Inconsistent Confirmation Dialogs

| Action | Has confirmation? | Risk level |
|---|---|---|
| Assignment row removal | ✅ Yes (`admin.js:611`) | Low |
| Location rule removal | ✅ Yes (`admin.js:773`) | Low |
| Addon field removal | ❌ No (`admin.js:502`) | **High** |
| Child product removal | ❌ No (`admin.js:502`) | Medium |
| Rule removal | ❌ No (`admin.js:502`) | Medium |
| Option removal | ❌ No (`admin.js:527`) | Low |

**Fix:** Add `window.confirm()` to all `.pab-remove-row` and `.pab-remove-option` handlers. Use i18n strings from `pabAdmin.i18n`.

#### 5. Inconsistent Accordion Behavior

- Addon rows: new row added → all others collapse (`admin.js:261-262`)
- Child rows: new row added → no collapsing (`admin.js:392-399`)
- Rule rows: new row added → no collapsing (`admin.js:445-456`)

**Fix:** Standardize — when a new row is added, collapse all siblings and expand the new row. Apply the same pattern from `addAddonRow()` to `addChildRow()` and `addRuleRow()`.

#### 6. No Loading States on AJAX

- `pab_get_variations` call (`admin.js:370-388`) has no spinner or loading indicator
- No error handling on AJAX failures

**Fix:** Before `$.post()`, show a `.spinner` element. On `.always()`, hide it. Add `.fail()` with a console warning and a dismissible admin notice.

#### 7. Media Frame Closure Bug

Shared `mediaFrame` variable (`admin.js:544`) captures `$hidden`/`$preview` in a closure. Reopening for a different option row writes to the wrong row's fields because the `select` callback still references the old `$hidden`/`$preview`.

**Fix:** Set `mediaFrame = null` after each `select` event (already done at line 559). But the early return at line 551 (`mediaFrame.open(); return;`) skips re-binding. Fix: always set `mediaFrame = null` before creating a new frame, or re-open the existing frame only if the same row is clicked.

#### 8. No Client-Side Validation

- No required field indicators (no asterisk or visual cue on required checkboxes)
- No unsaved changes warning when navigating away
- No validation before form submit (empty labels, invalid prices)

**Fix:** (1) Add `required` attribute visual styling. (2) Add a `beforeunload` listener that checks if any inputs have changed. (3) Validate on submit: highlight empty labels with a red border.

#### 9. Accessibility Gaps

- Drag handles use `aria-hidden="true"` with no keyboard alternative (`class-pab-product-tab.php:198`, `class-pab-product-tab.php:471`, `class-pab-product-tab.php:604`)
- Option remove buttons lack `aria-label` (partially fixed — `class-pab-product-tab.php:403` has it, but the JS-built location rule remove button uses `&times;` with only `aria-label`)
- No keyboard-accessible reorder mechanism

**Fix:** (1) Add "Move up" / "Move down" buttons next to drag handles, hidden by default, visible on focus. (2) Ensure all remove buttons have descriptive `aria-label`. (3) Add `role="listbox"` and `aria-roledescription` to sortable lists.

#### 10. No Undo/Trash for Deleted Rows

Immediate DOM removal with no recovery mechanism. All `.pab-remove-row` and `.pab-remove-option` handlers call `.remove()` directly.

**Fix:** Instead of immediate removal, hide the row with a class and show an "Undo" toast for 5 seconds. On timeout, remove from DOM. This is a medium-term improvement; for now, confirmation dialogs are sufficient.

#### 11. Location Rules Use String Concatenation in JS

`buildLocationRuleRowHtml()` at `admin.js:684-733` constructs HTML via string concatenation. Every other repeater uses the `cloneTemplate()` pattern.

**Fix:** Create a `<script type="text/html" id="pab-tmpl-location-rule-row">` template in the metabox PHP. Replace `buildLocationRuleRowHtml()` with `cloneTemplate('pab-tmpl-location-rule-row', ...)`.

#### 12. Deprecated Code Still Present

`handle_save_assignments()` at `admin/class-pab-admin.php:419-421` is an empty method marked deprecated. Assignment row JS code (`reindexAssignmentRows`, `toggleAssignmentRowTargets`, `initAssignmentRow`, `addAssignmentRow`) still exists in `admin.js:564-618` but is only used on the product tab.

**Fix:** Remove the empty `handle_save_assignments()` method. The assignment JS is still active and should remain, but add a comment clarifying its scope.

#### 13. Spacing Inconsistencies

No consistent spacing scale is used. Current ad-hoc values:

| Context | Current value | Nearest scale value |
|---|---|---|
| Toolbar padding | 10px 12px | 12px |
| Toolbar margin-bottom | 14px | 16px |
| Card margin-bottom | 12px (via --pab-rhythm) | 12px ✅ |
| Card header padding | 10px 12px | 12px |
| Card body padding (builder) | 14px 14px 10px | 12px 12px 8px |
| Card body padding (outside) | 12px 12px 4px | 12px 12px 4px |
| Settings table cell padding | 10px 12px | 12px |
| Settings heading margin | 16px 0 8px | 16px 0 8px ✅ |
| Options panel padding | 12px | 12px ✅ |
| Option line padding | 10px | 12px |
| Option line margin-bottom | 8px | 8px ✅ |
| Variation section padding | 10px 12px | 12px |
| Location rule row padding | 10px 12px | 12px |
| Location rule row margin-bottom | 10px | 12px |

**Fix:** Adopt the 4px-based scale defined in Part 2. Replace all `10px` padding with `12px`. Replace `14px` with `16px` or `12px` depending on context.

#### 14. Typography Inconsistencies

Multiple font sizes with no clear hierarchy:

| Element | Size | Weight | Transform | Proposed level |
|---|---|---|---|---|
| Card title | 14px | 600 | none | **Card title** |
| Settings heading | 12px | 600 | uppercase | **Section label** |
| Options head | 11px | 600 | uppercase | **Caption** |
| Type badge | 11px | 600 | uppercase | **Caption** |
| Rule inline label | 11px | 600 | uppercase | **Caption** |
| Settings table label | 13px | 600 | none | **Label** |
| Settings table control | 13px | normal | none | **Body** |
| Field key code | 11px | normal | none | **Caption** |

**Fix:** Reduce to 4 levels: Section label (12px/600/uppercase), Card title (14px/600), Label (13px/600), Caption (11px/normal). See Part 2 for the full hierarchy.

---

## Part 2: Design System Rules

### Spacing Scale

4px-based scale: **4, 8, 12, 16, 20, 24, 32, 40, 48**

| Token | Value | Usage |
|---|---|---|
| `--pab-space-1` | 4px | Tight gaps (icon-text, inline label spacing) |
| `--pab-space-2` | 8px | Small gaps (option line margin, checkbox gaps) |
| `--pab-space-3` | 12px | Default padding (card body, table cells, toolbar) |
| `--pab-space-4` | 16px | Section spacing (heading margins, section dividers) |
| `--pab-space-5` | 20px | Large section gaps |
| `--pab-space-6` | 24px | Page-level spacing |
| `--pab-space-7` | 32px | Major section breaks |
| `--pab-space-8` | 40px | Top-level page padding |

**Before/After mapping:**

| Current | Maps to token | New value |
|---|---|---|
| 10px (padding) | `--pab-space-3` | 12px |
| 14px (margin/padding) | `--pab-space-4` | 16px |
| 10px 12px (padding) | `--pab-space-3` | 12px |
| 14px 14px 10px (body padding) | `--pab-space-4` `--pab-space-3` | 16px 16px 8px → simplified to 12px uniform |

### Typography Hierarchy

| Level | Size | Weight | Transform | Line-height | Usage |
|---|---|---|---|---|---|
| Section label | 12px | 600 | uppercase | 1.4 | Settings headings, options head |
| Card title | 14px | 600 | none | 1.35 | Row headers, panel titles |
| Label | 13px | 600 | none | 1.4 | Table labels, form labels |
| Caption | 11px | normal | none | 1.4 | Field keys, badges, helper text |

Badge/inline labels (type badge, rule IF/THEN) use caption size + 600 weight + uppercase.

### Color Usage

| Token | Value | WP equivalent | Usage |
|---|---|---|---|
| `--pab-surface` | #fff | `$white` | Card backgrounds, table backgrounds |
| `--pab-surface-muted` | #f3f4f6 | `$gray-100` / `#f6f7f7` | Toolbar bg, panel bg, header bg |
| `--pab-border` | #c3c4c7 | `$gray-500` | Card borders, strong dividers |
| `--pab-border-subtle` | #dcdcde | `$gray-200` | Table row dividers, inner borders |
| `--pab-accent` | #2271b1 | `$wp-blue` | Primary actions, badges, links |
| `--pab-accent-hover` | #135e96 | `$wp-blue-dark-600` | Hover state on primary actions |
| `--pab-text` | #1d2327 | `$gray-900` | Headings, labels, body text |
| `--pab-muted` | #646970 | `$gray-700` | Helper text, captions, disabled text |
| `--pab-error` | #d63638 | `$alert-red` | Validation errors, delete buttons |
| `--pab-success` | #00a32a | `$alert-green` | Success notices |

**Rules:**
- Never hardcode hex values in CSS — always use tokens
- The 6 tokens already defined in `admin.css:17-33` are the canonical set; extend them, don't bypass them
- `--pab-surface-muted` should replace all instances of `#f6f7f7` and `#fafafa`/`#f0f0f1` gradient

### Component States

| State | Border | Background | Text | Shadow |
|---|---|---|---|---|
| Default | `--pab-border` | `--pab-surface` | `--pab-text` | `--pab-shadow` |
| Hover | `--pab-accent` | `--pab-surface-muted` | `--pab-accent` | `0 0 0 1px var(--pab-accent)` |
| Focus | `--pab-accent` | `--pab-surface` | `--pab-text` | `0 0 0 2px var(--pab-accent)` |
| Active | `--pab-accent-hover` | `--pab-surface-muted` | `--pab-accent-hover` | inset shadow |
| Disabled | `--pab-border-subtle` | `--pab-surface-muted` | `--pab-muted` | none |
| Error | `--pab-error` | `#fef7f7` | `--pab-error` | `0 0 0 1px var(--pab-error)` |

---

## Part 3: Component Standards

### Product Selector (Search + Select)

- **Structure:** `<select>` with `wc-product-search` / `wc-enhanced-select` class + Select2/SelectWoo initialization
- **Behavior:** AJAX search with `minimumInputLength: 1`, placeholder text, selected option pre-populated
- ✅ **Do:** Use `data-placeholder` and `data-action` attributes for WC enhanced select
- ❌ **Don't:** Set width via inline `style` — use CSS class with `width: 100%` and `max-width` constraint
- ❌ **Don't:** Use different width patterns for the same component type (currently: `width:260px` vs `width:50%;max-width:400px` vs `width:100%`)

### Addon Blocks / Cards

- **Structure:** `.pab-settings-card` wrapper → `.pab-settings-card__header` → `.pab-settings-card__body`
- **Header contains:** drag handle, title/key summary, type badge, action buttons (duplicate/delete/toggle)
- **Body contains:** settings table, options panel
- ✅ **Do:** Use the same card structure everywhere (inside and outside `.pab-field-builder`)
- ❌ **Don't:** Apply different border-radius, shadow, padding, or header background based on context
- ❌ **Don't:** Use gradient headers in one context and flat headers in another

### Form Fields (Inputs, Selects, Toggles)

- **Structure:** Settings table with `__label` (th) and `__control` (td) cells
- **Behavior:** Labels right-aligned at 160px width (36% max), controls fill remaining space
- ✅ **Do:** Use `pab-field-settings-table` for all field layouts
- ❌ **Don't:** Mix `p.form-field` WooCommerce layout with table layout in the same section
- ❌ **Don't:** Float labels inside settings tables (already reset at `admin.css:250-261`)

### Repeaters (ACF-style)

- **Structure:** Toolbar + `.pab-repeater-list` container + sortable card rows
- **Behavior:** Add button appends from template; drag handles for reorder; reindex on sort/remove
- ✅ **Do:** Use `cloneTemplate()` for all new-row creation
- ❌ **Don't:** Build HTML via string concatenation (as in `buildLocationRuleRowHtml`)
- ❌ **Don't:** Forget to reindex after add/remove/sort operations

### Action Buttons

- **Primary:** `.button.button-primary` — "Add field", "Add child product", "Add rule"
- **Secondary:** `.button.button-secondary` — "Add option", "Choose image"
- **Destructive:** `.button-link-delete` — "Delete", "Remove child product", "Remove rule"
- **Toggle:** `.button.button-small.pab-settings-card__toggle` — expand/collapse chevron
- ✅ **Do:** Always pair destructive buttons with confirmation dialogs
- ❌ **Don't:** Use `.button-link-delete` without a confirmation step

### Option Rows

- **Structure:** `.pab-option-line.pab-option-row-flex` with flex columns: label, price, image, actions
- **Behavior:** Columns show/hide based on field type (swatch shows image, uniform hides price)
- ✅ **Do:** Use `pab-is-hidden` class for visibility toggling
- ❌ **Don't:** Remove option rows without confirmation when they contain user-entered data

### Location Rule Rows

- **Structure:** `.pab-location-rule-row` with flex columns: param, operator, value, actions
- **Behavior:** Param change refreshes the value select2; remove button with confirmation
- ✅ **Do:** Use the same template pattern as addon/child/rule rows
- ❌ **Don't:** Build via string concatenation in JS
- ❌ **Don't:** Use inline styles for width constraints

---

## Part 4: Layout Guidelines

### Page Structure Rules

1. Each screen uses WooCommerce's `.woocommerce_options_panel` or `.wrap` container
2. Sections within a panel use `.options_group` dividers
3. Section intros use `<p class="form-field pab-section-intro">` with `<strong>` title + `<span class="description">`
4. All PAB-specific styles are scoped to `#pab_addons_data` or `#pab_group_addons`

### Section Grouping

- **Group Assignments** → separate `.options_group` (table-based, no cards)
- **Add-on Fields** → separate `.options_group` with `.pab-field-builder` wrapper
- **Child Products** → separate `.options_group` with card repeater
- **Conditional Rules** → separate `.options_group` with card repeater
- Each section has a clear intro paragraph explaining its purpose

### When to Use Tabs vs Cards vs Lists

| Content type | Pattern | Reason |
|---|---|---|
| Independent configuration areas | WooCommerce panel tabs | Already used for the top-level "Add-ons & Composite" tab |
| Repeating structured items | Card repeater with collapsible bodies | Addon fields, child products, rules |
| Simple key-value pairs | Settings table (`.pab-field-settings-table`) | Field properties (type, label, required, price) |
| Flat tabular data | Widefat striped table | Group assignments (simple, no nested editing) |
| Conditional logic | Inline card with horizontal flow | Rules (IF/THEN on one line) |

---

## Part 5: Interaction Patterns

### Modal vs Inline vs Separate Page

| Action | Pattern | Current | Recommended |
|---|---|---|---|
| Add addon field | Inline (template clone) | ✅ Correct | Keep |
| Select swatch image | WP Media Modal | ✅ Correct (but buggy) | Fix closure bug |
| Delete row | Inline with confirmation | ❌ No confirmation | Add confirmation |
| Edit addon group | Separate CPT editor | ✅ Correct | Keep |
| Configure settings | Separate settings page | ✅ Correct | Keep |

### Feedback Patterns

- **Success:** WP admin notices (`.notice.notice-success.is-dismissible`) — already used for save confirmations
- **Error:** Red border + `.notice.notice-error` — not yet implemented for AJAX failures
- **Loading:** WP `.spinner` class with `.is-active` — not yet implemented for AJAX calls
- **Validation:** Red border on invalid fields + inline error message — not yet implemented

### Click Reduction Strategies

1. **Accordion defaults:** New rows open expanded (user can start editing immediately)
2. **Smart focus:** After adding a row, auto-focus the first input (already done for addon rows at `admin.js:271`)
3. **Type pre-selection:** Toolbar select remembers last chosen field type (not yet implemented)
4. **Batch operations:** Not needed at current scale (< 20 items typical)

### Accordion Behavior

**Standard rule:** When a new card is added, collapse all siblings and expand the new card. This applies to addon rows, child rows, and rule rows consistently.

**Toggle interaction:** Clicking the header row or the toggle button collapses/expands the body. The chevron icon rotates between `dashicons-arrow-down-alt2` (open) and `dashicons-arrow-right-alt2` (closed).

### Drag & Drop

- Uses jQuery UI Sortable with `.pab-drag-handle` as the handle
- Placeholder: `.pab-sortable-placeholder` (dashed border, 56px height)
- After sort stop: reindex all rows and update rule field dropdowns
- **Gap:** No keyboard alternative for reordering. Add "Move up/down" buttons visible on focus.

### Confirmation Patterns

All destructive actions must use `window.confirm()` with i18n strings:

| Action | i18n key | Default text |
|---|---|---|
| Remove addon field | `removeAddonConfirm` | "Delete this add-on field? Its options will be lost." |
| Remove child product | `removeChildConfirm` | "Remove this child product?" |
| Remove rule | `removeRuleConfirm` | "Remove this conditional rule?" |
| Remove option | `removeOptionConfirm` | "Remove this choice?" |

Assignment and location rule removals already have confirmations.

### Undo/Recovery

**Short-term:** Confirmation dialogs prevent accidental deletion.
**Medium-term:** Implement a soft-delete pattern — hide rows with a class, show an "Undo" admin notice for 8 seconds, then remove from DOM on timeout.

---

## Part 6: UX Best Practices

### Reduce Cognitive Load

- Collapse inactive sections (accordion) so users see only what they're editing
- Use progressive disclosure: hide swatch settings until field type = image_swatch (already done)
- Limit visible form fields: settings tables show only relevant rows per field type
- Group related settings under labeled headings ("Pricing", "Choices", "Image swatch appearance")

### Maintain Consistency

- Same card component = same visual treatment everywhere (fix the dual style system)
- Same interaction = same behavior (fix accordion inconsistency)
- Same deletion = same confirmation pattern (fix missing confirmations)
- Use CSS custom properties for all colors, spacing, and radii

### Prevent User Errors

- Add confirmation dialogs before all destructive actions
- Add client-side validation: required fields highlighted, empty labels warned
- Add unsaved changes warning (`beforeunload` event)
- Disable "Add field" button when no type is selected (edge case)

### Progressive Disclosure

- Settings headings collapse sections that are rarely needed
- Swatch-specific settings only appear for `image_swatch` type
- Custom upload settings only appear when "Allow upload" is checked
- Choice pricing section only appears for choice-type fields (select, radio, swatch)

### Smart Defaults

- New addon rows default to "Text Input" type (most common)
- New child rows default to max_qty = 1
- New rules default to "equals" operator and "show_field" action
- Priority defaults to 100 for group assignments
- Choice pricing mode defaults to "per_option"

### Empty States

- **No groups available:** Shows descriptive message with link to create groups (`class-pab-product-tab.php:44`) ✅
- **No addon fields:** Empty repeater list — should show a placeholder message ("Add your first add-on field using the toolbar above")
- **No child products:** Empty repeater list — should show a placeholder message
- **No rules:** Empty repeater list — should show a placeholder message

### Helper Text & Labels

- Section intros explain purpose and behavior (already present) ✅
- `.description` paragraphs explain individual settings (already present) ✅
- Screen-reader-text labels on selects in option rows (already present) ✅
- **Missing:** No helper text explaining that drag handles are for reordering
- **Missing:** No character counter on the 240-char upload title field

---

## Part 7: Implementation Notes

### WordPress-Friendly Patterns

- Use WooCommerce's `.woocommerce_options_panel` structure for product tab panels
- Use WP's `.nav-tab-wrapper` for settings page tabs (already done)
- Use `wp.media()` for image selection (already done, but fix the closure bug)
- Use `wc-enhanced-select` / SelectWoo for product and term search (already done)
- Use `wp_localize_script()` for i18n strings (already done)
- Use `.notice.notice-success.is-dismissible` for admin notices (already done)

### Dev-Friendly Approach

- All PAB styles scoped to `#pab_addons_data` or `#pab_group_addons` — no global leaks
- CSS custom properties defined once at the builder root — easy to theme
- Template pattern (`<script type="text/html" id="pab-tmpl-*">`) for all repeaters — consistent and maintainable
- `cloneTemplate()` utility centralizes template instantiation
- Reindex functions are modular and called after every mutation

### Scalability

- The card/repeater pattern scales to ~20 items before needing pagination or search
- CSS custom properties allow easy theming overrides by third parties
- The template pattern allows adding new field types without JS changes
- The rule system's `populateFieldSelect()` dynamically reads current addon fields

### Migration Strategy

Apply changes incrementally without breaking existing UI:

1. **Phase 1 — Zero-risk CSS fixes** (no markup/JS changes):
   - Replace hardcoded colors with CSS custom properties in `admin.css`
   - Unify dual style system (apply builder styles to outer cards)
   - Move inline styles from PHP to CSS classes
   - Normalize spacing to the 4px scale

2. **Phase 2 — Confirmation dialogs** (small JS additions):
   - Add `window.confirm()` to `.pab-remove-row` and `.pab-remove-option` handlers
   - Add i18n strings to `pabAdmin` localization in `class-pab-admin.php`

3. **Phase 3 — Accordion standardization** (JS behavior change):
   - Apply collapse-others pattern to `addChildRow()` and `addRuleRow()`
   - Add toggle buttons to rule row headers

4. **Phase 4 — Template refactor** (JS + PHP changes):
   - Create `pab-tmpl-location-rule-row` template in PHP
   - Replace `buildLocationRuleRowHtml()` with `cloneTemplate()`
   - Remove inline styles from location rule JS

5. **Phase 5 — Accessibility & validation** (markup + JS additions):
   - Add keyboard reorder buttons (move up/down)
   - Add `aria-label` to all remove buttons
   - Add `beforeunload` unsaved changes warning
   - Add client-side validation on form submit

6. **Phase 6 — Polish** (enhancements):
   - Add AJAX loading spinners
   - Fix media frame closure bug
   - Add empty state placeholders
   - Add character counter on settings page
   - Remove deprecated `handle_save_assignments()` method
