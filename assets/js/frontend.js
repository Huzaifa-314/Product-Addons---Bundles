/* global pabData */
(function ($) {
    'use strict';

    if (typeof pabData === 'undefined') return;

    var basePrice      = parseFloat(pabData.basePrice) || 0;
    var addonFields    = pabData.addonFields    || [];
    var childProducts  = pabData.childProducts  || [];
    var rules          = pabData.conditionalRules || [];
    var priceFormat    = pabData.priceFormat    || {};
    var currency       = pabData.currency       || '$';
    var settings       = pabData.settings       || {};

    // -------------------------------------------------------------------------
    // Price formatting
    // -------------------------------------------------------------------------
    function formatPrice(amount) {
        var decimals  = parseInt(priceFormat.decimals, 10) || 2;
        var decSep    = priceFormat.decimal_separator  || '.';
        var thouSep   = priceFormat.thousand_separator || ',';
        var fmt       = priceFormat.price_format       || '%s%v';

        var fixed     = parseFloat(amount).toFixed(decimals);
        var parts     = fixed.split('.');
        var intPart   = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thouSep);
        var decPart   = parts[1] || '';
        var numStr    = intPart + (decPart ? decSep + decPart : '');

        return fmt.replace('%s', currency).replace('%v', numStr);
    }

    /** Same structure as PHP `wc_price()` output so theme styles the amount like other addon hints. */
    function formatPriceWcAmountHtml(amount) {
        var inner = formatPrice(amount);
        return '<span class="woocommerce-Price-amount amount"><bdi>' + inner + '</bdi></span>';
    }

    function freeLabelParenHtml() {
        var w = (pabData.i18n && pabData.i18n.freeLabel) ? String(pabData.i18n.freeLabel) : 'Free';
        return '(' + w + ')';
    }

    function imageSwatchUniformLabelHtml(price, priceType) {
        var p = parseFloat(price) || 0;
        if (p <= 0) {
            return freeLabelParenHtml();
        }
        if (priceType === 'percentage') {
            var decimals = parseInt(priceFormat.decimals, 10) || 2;
            return '(+' + parseFloat(p).toFixed(decimals) + '%)';
        }
        if (priceType === 'per_qty') {
            var suffix = (pabData.i18n && pabData.i18n.perQtySuffix) ? pabData.i18n.perQtySuffix : ' × quantity';
            return '(+' + formatPriceWcAmountHtml(p) + ' ' + suffix + ')';
        }
        return '(+' + formatPriceWcAmountHtml(p) + ')';
    }

    /** Snapshot PHP-rendered label hint (range / single / hidden) before JS overwrites it; restored when all swatches are cleared. */
    function cacheImageSwatchDefaultLabel($field) {
        if (!$field || !$field.length || !$field.is('.pab-field-type-image_swatch')) {
            return;
        }
        var $priceSpan = $field.find('.pab-image-swatch-label-price').first();
        if (!$priceSpan.length || $priceSpan.data('pab-default-label-cached')) {
            return;
        }
        $priceSpan.data('pab-default-label-html', $priceSpan.html());
        $priceSpan.data('pab-default-label-hidden', !!$priceSpan.prop('hidden'));
        $priceSpan.data('pab-default-label-cached', true);
    }

    function restoreImageSwatchDefaultLabel($priceSpan) {
        if (!$priceSpan || !$priceSpan.length) {
            return;
        }
        var defHtml = $priceSpan.data('pab-default-label-html');
        if (defHtml === undefined) {
            return;
        }
        $priceSpan.html(defHtml);
        var hid = !!$priceSpan.data('pab-default-label-hidden');
        $priceSpan.prop('hidden', hid);
        if (hid) {
            $priceSpan.attr('aria-hidden', 'true');
        } else {
            $priceSpan.removeAttr('aria-hidden');
        }
    }

    function syncImageSwatchLabelPrice($field) {
        if (!$field || !$field.length || !$field.is('.pab-field-type-image_swatch')) {
            return;
        }
        var $priceSpan = $field.find('.pab-image-swatch-label-price');
        if (!$priceSpan.length) {
            return;
        }
        var $checked = $field.find('.pab-image-swatch-wrap .pab-swatch-radio:checked');
        if (!$checked.length) {
            restoreImageSwatchDefaultLabel($priceSpan);
            return;
        }
        var cmode = String($field.attr('data-choice-price-mode') || '');

        if (cmode === 'uniform') {
            if ($field.hasClass('pab-field-wrap--nested')) {
                $priceSpan.empty().prop('hidden', true).attr('aria-hidden', 'true');
                return;
            }
            var fp = parseFloat($field.attr('data-price')) || 0;
            var pt = String($field.attr('data-price-type') || 'flat');
            var html = imageSwatchUniformLabelHtml(fp, pt);
            $priceSpan.html(html).prop('hidden', false).removeAttr('aria-hidden');
            return;
        }

        if (cmode !== 'per_option') {
            return;
        }

        var optPrice = parseFloat($checked.attr('data-option-price')) || 0;

        // Label follows data-option-price (incl. custom upload surcharge). Live total still uses
        // shouldZeroImageSwatchCustomOptionPrice() until a file is chosen.

        if (optPrice <= 0) {
            $priceSpan.html(freeLabelParenHtml()).prop('hidden', false).removeAttr('aria-hidden');
            return;
        }
        $priceSpan.html('(+' + formatPriceWcAmountHtml(optPrice) + ')').prop('hidden', false).removeAttr('aria-hidden');
    }

    /** Custom image swatch: live total stays 0 until a file is chosen (server charges on add-to-cart). */
    function shouldZeroImageSwatchCustomOptionPrice($field, $radio) {
        if (!$field.length || !$field.is('.pab-field-type-image_swatch')) {
            return false;
        }
        var customVal = (pabData && pabData.swatchCustomValue) ? String(pabData.swatchCustomValue) : '';
        var isCustom = String($radio.attr('data-pab-custom-upload') || '') === '1'
            || (customVal !== '' && String($radio.val() || '') === customVal);
        if (!isCustom) {
            return false;
        }
        var $fileWrap = $field.find('.pab-swatch-custom-upload .pab-file-upload--image').first();
        return !$fileWrap.length || !$fileWrap.hasClass('pab-has-file');
    }

    // -------------------------------------------------------------------------
    // Collect current values of all addon fields
    // -------------------------------------------------------------------------
    function collectAddonValues() {
        var values = {};
        $('.pab-field-wrap').each(function () {
            var $fieldWrap = $(this);
            if ($fieldWrap.hasClass('pab-field-type-popup')) {
                return;
            }
            var fieldId = String($fieldWrap.data('field-id') || '');
            var $input;
            var index = $fieldWrap.data('index');
            var isNested = $fieldWrap.hasClass('pab-field-wrap--nested');
            var topIdx = $fieldWrap.data('pab-popup-top-index');
            var storeKey = index;
            if (isNested && topIdx !== undefined && topIdx !== '') {
                storeKey = 'popup:' + String(topIdx) + ':' + String(index);
            }

            $input = $fieldWrap.find('input[type="radio"]:checked, input[type="checkbox"]:checked');
            if ($input.length) {
                var optPrice = parseFloat($input.attr('data-option-price')) || 0;
                if (shouldZeroImageSwatchCustomOptionPrice($fieldWrap, $input)) {
                    optPrice = 0;
                }
                values[storeKey] = {
                    fieldId: fieldId,
                    value: $input.val() || ($input.is(':checked') ? '1' : ''),
                    optionPrice: optPrice,
                    popupNested: isNested,
                    topIndex: isNested ? parseInt(topIdx, 10) : undefined,
                    nestIndex: isNested ? parseInt(index, 10) : undefined,
                };
                return;
            }

            $input = $fieldWrap.find('select.pab-field-input');
            if ($input.length) {
                var $selectedOpt = $input.find('option:selected');
                values[storeKey] = {
                    fieldId: fieldId,
                    value: $input.val() || '',
                    optionPrice: parseFloat($selectedOpt.data('option-price')) || 0,
                    popupNested: isNested,
                    topIndex: isNested ? parseInt(topIdx, 10) : undefined,
                    nestIndex: isNested ? parseInt(index, 10) : undefined,
                };
                return;
            }

            if ($fieldWrap.is('.pab-field-type-file, .pab-field-type-image_upload')) {
                var $fileInp = $fieldWrap.find('input.pab-file-upload-input').first();
                if ($fileInp.length) {
                    var fileEl = $fileInp.get(0);
                    var $fwrap = $fileInp.closest('.pab-file-upload');
                    var hasFile = $fwrap.hasClass('pab-has-file')
                        || !!(fileEl && fileEl.files && fileEl.files.length);
                    values[storeKey] = {
                        fieldId: fieldId,
                        value: hasFile ? ($fileInp.val() || '1') : '',
                        optionPrice: 0,
                        popupNested: isNested,
                        topIndex: isNested ? parseInt(topIdx, 10) : undefined,
                        nestIndex: isNested ? parseInt(index, 10) : undefined,
                    };
                    return;
                }
            }

            $input = $fieldWrap.find('input.pab-field-input, textarea.pab-field-input');
            if ($input.length) {
                values[storeKey] = {
                    fieldId: fieldId,
                    value: $input.val() || '',
                    optionPrice: 0,
                    popupNested: isNested,
                    topIndex: isNested ? parseInt(topIdx, 10) : undefined,
                    nestIndex: isNested ? parseInt(index, 10) : undefined,
                };
            }
        });
        return values;
    }

    function valuesByFieldId(addonValues) {
        var mapped = {};
        $.each(addonValues, function (_index, data) {
            if (data && data.fieldId && !data.popupNested) {
                mapped[data.fieldId] = data;
            }
        });
        return mapped;
    }

    function nestedPopupUniformShouldCharge(data, subField) {
        if (!data || !subField) {
            return false;
        }
        if (subField.type === 'checkbox') {
            return !!data.value && String(data.value) !== '0';
        }
        if (!String(data.value || '').trim()) {
            return false;
        }
        if (subField.type === 'image_swatch' && subField.swatch_allow_custom_upload) {
            var customVal = (pabData && pabData.swatchCustomValue) ? String(pabData.swatchCustomValue) : '';
            var val = String(data.value || '');
            if (customVal && val === customVal) {
                var $wrap = $('.pab-field-wrap--nested').filter(function () {
                    return String($(this).data('pab-popup-top-index')) === String(data.topIndex)
                        && String($(this).data('index')) === String(data.nestIndex);
                });
                var $radio = $wrap.find('.pab-swatch-radio:checked');
                if (shouldZeroImageSwatchCustomOptionPrice($wrap, $radio)) {
                    return false;
                }
            }
        }
        return true;
    }

    function addonFieldSurcharge(field, data) {
        var fieldPrice = 0;
        var priceType  = field.price_type || 'flat';
        var choiceTypes = ['select', 'radio', 'image_swatch', 'text_swatch'];
        if (choiceTypes.indexOf(field.type) !== -1) {
            var cmode = field.choice_price_mode || 'per_option';
            if (cmode === 'uniform') {
                fieldPrice = parseFloat(field.price) || 0;
                priceType  = field.price_type || 'flat';
            } else {
                fieldPrice = data.optionPrice || 0;
                priceType  = 'flat';
            }
        } else {
            fieldPrice = parseFloat(field.price) || 0;
        }
        return { fieldPrice: fieldPrice, priceType: priceType };
    }

    // -------------------------------------------------------------------------
    // Calculate extra price from addon fields
    // -------------------------------------------------------------------------
    function calcAddonPrice(addonValues, baseP, qty) {
        var extra = 0;
        var uniformPopupCharged = {};
        $.each(addonValues, function (index, data) {
            if (!data || !data.value) {
                return;
            }
            var fieldPrice = 0;
            var priceType  = 'flat';

            if (data.popupNested) {
                var pf = addonFields[data.topIndex];
                if (!pf || pf.type !== 'popup' || !pf.nested_fields) {
                    return;
                }
                var subField = pf.nested_fields[data.nestIndex];
                if (!subField) {
                    return;
                }
                if (pf.nested_price_mode === 'uniform') {
                    if (!nestedPopupUniformShouldCharge(data, subField)) {
                        return;
                    }
                    var popupKey = pf.id ? String(pf.id) : ('top_' + data.topIndex);
                    if (uniformPopupCharged[popupKey]) {
                        return;
                    }
                    uniformPopupCharged[popupKey] = true;
                    fieldPrice = parseFloat(pf.price) || 0;
                    priceType  = pf.price_type || 'flat';
                } else {
                    var np = addonFieldSurcharge(subField, data);
                    fieldPrice = np.fieldPrice;
                    priceType = np.priceType;
                }
            } else {
                var field = addonFields[index];
                if (!field) {
                    return;
                }
                var top = addonFieldSurcharge(field, data);
                fieldPrice = top.fieldPrice;
                priceType = top.priceType;
            }

            switch (priceType) {
                case 'flat':
                    extra += fieldPrice;
                    break;
                case 'percentage':
                    extra += baseP * fieldPrice / 100;
                    break;
                case 'per_qty':
                    extra += fieldPrice * qty;
                    break;
            }
        });
        return extra;
    }

    // -------------------------------------------------------------------------
    // Child extras: qty bounds + visual state
    // -------------------------------------------------------------------------
    function parseChildBounds($wrap) {
        var min = parseInt($wrap.data('min-qty'), 10);
        var max = parseInt($wrap.data('max-qty'), 10);
        if (isNaN(min)) min = 0;
        if (isNaN(max) || max < 1) max = 1;
        return { min: min, max: max };
    }

    function clampChildQty($wrap, raw) {
        var b = parseChildBounds($wrap);
        var rawStr = String(raw == null ? '' : raw).trim();
        if (rawStr === '') {
            return b.min;
        }
        var v = parseInt(rawStr, 10);
        if (isNaN(v)) v = b.min;
        if (v < b.min) v = b.min;
        if (v > b.max) v = b.max;
        return v;
    }

    /** Live total: empty field counts as 0; does not mutate the input. */
    function readChildQtyForPricing($wrap, $qtyInput) {
        var raw = String($qtyInput.val()).trim();
        if (raw === '') return 0;
        return clampChildQty($wrap, raw);
    }

    function childHasVariationSelected($wrap) {
        if (String($wrap.data('is-variable')) !== '1') return true;
        var $sel = $wrap.find('.pab-child-variation-select');
        return $sel.length && !!$sel.val();
    }

    function syncChildWrapVisual($wrap) {
        var $qty = $wrap.find('.pab-child-qty').first();
        var raw = String($qty.val()).trim();
        var qty = raw === '' ? 0 : (parseInt(raw, 10) || 0);
        var max = parseInt($wrap.data('max-qty'), 10);
        var min = parseInt($wrap.data('min-qty'), 10);
        if (isNaN(max) || max < 1) max = 1;
        if (isNaN(min)) min = 0;

        var $ctrl = $wrap.find('.pab-child-control--buttons');
        var confirmed = $wrap.hasClass('pab-child--extras-confirmed');

        /* Optional extra (min 0): qty back to 0 — show Add again */
        if ($ctrl.length && min === 0 && qty === 0) {
            $wrap.removeClass('pab-child--extras-confirmed');
            confirmed = false;
        }

        if (qty > 0) {
            $wrap.addClass('pab-child-selected');
        } else {
            $wrap.removeClass('pab-child-selected');
        }

        var $qwrap = $wrap.find('.pab-child-quantity-wrap');
        if ($ctrl.length && $qwrap.length) {
            var hasVar = childHasVariationSelected($wrap);
            var needVar = String($wrap.data('is-variable')) === '1';
            var blockPlus = needVar && !hasVar;
            var $plus = $qwrap.find('.plus');
            var $minus = $qwrap.find('.minus');
            var $add = $wrap.find('.pab-child-add-btn');

            if (!confirmed && min === 0) {
                if (qty > 0) {
                    $qty.val(0);
                    qty = 0;
                    $wrap.removeClass('pab-child-selected');
                }
                $qty.prop('readonly', true);
                $plus.prop('disabled', true);
                $minus.prop('disabled', true);
                $add.prop('disabled', blockPlus);
            } else {
                $qty.prop('readonly', false);
                $plus.prop('disabled', blockPlus || qty >= max);
                $minus.prop('disabled', qty <= min);
                $add.prop('disabled', true);
            }

            var $rim = $wrap.find('.pab-child-remove-wrap');
            $rim.toggle(qty > min);
            $rim.find('.pab-child-remove-btn').prop('disabled', qty <= min);
        }
    }

    function syncAllChildWraps() {
        $('.pab-child-wrap').each(function () {
            syncChildWrapVisual($(this));
        });
    }

    // Exclusive image swatch: one radio → qty 0/1 on each row; no Add / stepper.
    function syncExclusiveSwatchGroup($group) {
        $group.find('.pab-child-wrap.pab-child--swatch-exclusive').each(function () {
            var $w = $(this);
            var on = $w.find('.pab-child-swatch-radio').is(':checked');
            $w.find('.pab-child-qty').val(on ? '1' : '0');
            var $sel = $w.find('.pab-child-variation-select');
            $sel.prop('disabled', !on);
            if (!on && $sel.length) {
                $sel.val('');
            }
            syncChildWrapVisual($w);
            if ($sel.length) {
                refreshChildVariationPrice($sel);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Calculate extra price from child products
    // -------------------------------------------------------------------------
    function calcChildPrice() {
        var extra = 0;
        $('.pab-child-wrap').each(function () {
            var index      = $(this).data('index');
            var $qtyInput  = $(this).find('.pab-child-qty').first();
            var qty        = readChildQtyForPricing($(this), $qtyInput);

            if (qty <= 0) return;

            var childData = childProducts[index];
            if (!childData) return;

            var unitPrice = childData.price;

            // If variable, use the selected variation's price
            if (childData.is_variable) {
                var $varSel     = $(this).find('.pab-child-variation-select');
                var $selOpt     = $varSel.find('option:selected');
                var varPrice    = parseFloat($selOpt.data('price'));
                if (!isNaN(varPrice)) {
                    unitPrice = varPrice;
                } else {
                    return; // No variation selected, skip
                }
            }

            extra += unitPrice * qty;
        });
        return extra;
    }

    // -------------------------------------------------------------------------
    // Evaluate conditional rules
    // -------------------------------------------------------------------------
    function evaluateRules(addonValues) {
        var priceAdjust = 0;

        // Reset all hidden-by-rules fields first (top-level only; nested fields are not rule targets in v1)
        $('.pab-field-wrap').not('.pab-field-wrap--nested').removeClass('pab-rule-hidden').show();

        var byFieldId = valuesByFieldId(addonValues);
        $.each(rules, function (_ri, rule) {
            var triggerFieldId = String(rule.trigger_field_id || '');
            if (!triggerFieldId) return;

            var currentData = byFieldId[triggerFieldId];
            var currentVal  = currentData ? currentData.value : '';
            var ruleVal     = rule.value;
            var matches     = false;

            switch (rule.operator) {
                case 'equals':
                    matches = (String(currentVal) === String(ruleVal));
                    break;
                case 'not_equals':
                    matches = (String(currentVal) !== String(ruleVal));
                    break;
                case 'greater_than':
                    matches = (parseFloat(currentVal) > parseFloat(ruleVal));
                    break;
                case 'less_than':
                    matches = (parseFloat(currentVal) < parseFloat(ruleVal));
                    break;
            }

            if (!matches) return;

            var actionAmount = parseFloat(rule.action_amount) || 0;
            var targetFieldId = String(rule.action_target_field_id || '');

            switch (rule.action) {
                case 'show_field':
                    // Field is already shown; this is handled by hide_field logic
                    break;
                case 'hide_field':
                    if (targetFieldId) {
                        $('.pab-field-wrap[data-field-id="' + targetFieldId + '"]').addClass('pab-rule-hidden').hide();
                    }
                    break;
                case 'add_price':
                    priceAdjust += actionAmount;
                    break;
                case 'subtract_price':
                    priceAdjust -= actionAmount;
                    break;
                case 'percentage_discount':
                    // Applied as % of basePrice
                    priceAdjust -= basePrice * actionAmount / 100;
                    break;
            }
        });

        return priceAdjust;
    }

    // -------------------------------------------------------------------------
    // Main price update function
    // -------------------------------------------------------------------------
    function updatePrice() {
        var $qtyInput  = $('input.qty[name="quantity"]');
        var qty        = parseInt($qtyInput.val(), 10) || 1;

        var addonValues  = collectAddonValues();
        var ruleAdjust   = evaluateRules(addonValues);
        var addonExtra   = calcAddonPrice(addonValues, basePrice, qty);
        var childExtra   = calcChildPrice();
        var totalExtra   = addonExtra + childExtra + ruleAdjust;
        var totalPrice   = (basePrice + totalExtra) * qty;

        if (totalPrice < 0) totalPrice = 0;

        var $liveWrap = $('#pab-live-total-wrap');
        var $liveSpan = $('#pab-live-total');

        if (!settings.enableLiveTotal) {
            $liveWrap.hide();
            syncAllChildWraps();
            return;
        }

        if (totalExtra !== 0) {
            $liveSpan.html(formatPrice(totalPrice));
            $liveWrap.show();
        } else {
            $liveWrap.hide();
        }

        syncAllChildWraps();
    }

    // -------------------------------------------------------------------------
    // Swatch selection visual feedback
    // -------------------------------------------------------------------------
    // Child extras (image swatch): first click selects; second click on the same tile clears (radios alone cannot do that).
    $(document).on('mousedown', '.pab-child--swatch-exclusive .pab-child-swatch-hitarea', function () {
        var $radio = $(this).find('.pab-child-swatch-radio');
        $radio.data('pab-was-checked', $radio.prop('checked'));
    });
    $(document).on('click', '.pab-child--swatch-exclusive .pab-child-swatch-hitarea', function (e) {
        var $radio = $(this).find('.pab-child-swatch-radio');
        if ($radio.data('pab-was-checked')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            $radio.prop('checked', false);
            syncExclusiveSwatchGroup($radio.closest('.pab-child-swatch-group'));
            updatePrice();
            return false;
        }
    });

    // Product addon image/text swatches: second click on the selected tile clears (native radios cannot unset).
    $(document).on('mousedown', '.pab-image-swatch-wrap .pab-swatch-item, .pab-text-swatch-wrap .pab-text-swatch-item', function () {
        var $radio = $(this).find('.pab-swatch-radio');
        $radio.data('pab-was-checked', $radio.prop('checked'));
    });
    $(document).on('click', '.pab-image-swatch-wrap .pab-swatch-item, .pab-text-swatch-wrap .pab-text-swatch-item', function (e) {
        var $radio = $(this).find('.pab-swatch-radio');
        if ($radio.data('pab-was-checked')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            $radio.prop('checked', false);
            $radio.removeData('pab-was-checked');
            var $wrap = $(this).closest('.pab-image-swatch-wrap, .pab-text-swatch-wrap');
            $wrap.find('.pab-swatch-item, .pab-text-swatch-item').removeClass('pab-swatch-active');
            var $fld = $(this).closest('.pab-field-wrap');
            syncImageSwatchCustomerUpload($fld);
            syncImageSwatchLabelPrice($fld);
            updatePrice();
            return false;
        }
    });

    $(document).on('change', '.pab-swatch-radio, .pab-child-swatch-radio', function () {
        var $ex = $(this).closest('.pab-child-swatch-group');
        if ($ex.length) {
            syncExclusiveSwatchGroup($ex);
            updatePrice();
            return;
        }
        var $wrap = $(this).closest('.pab-image-swatch-wrap, .pab-text-swatch-wrap');
        $wrap.find('.pab-swatch-item, .pab-text-swatch-item').removeClass('pab-swatch-active');
        $(this).closest('.pab-swatch-item, .pab-text-swatch-item').addClass('pab-swatch-active');
        var $fld2 = $(this).closest('.pab-field-wrap');
        syncImageSwatchCustomerUpload($fld2);
        syncImageSwatchLabelPrice($fld2);
        updatePrice();
    });

    // -------------------------------------------------------------------------
    // Bind all field change events
    // -------------------------------------------------------------------------
    function revokePabPreviewUrl($wrap) {
        var prev = $wrap.data('pab-preview-url');
        if (prev) {
            URL.revokeObjectURL(prev);
            $wrap.removeData('pab-preview-url');
        }
    }

    function syncPabFileUpload($wrap) {
        var $field = $wrap.closest('.pab-field-wrap');
        var $inp = $wrap.find('.pab-file-upload-input');
        var $name = $wrap.find('.pab-file-upload-filename');
        var $prev = $wrap.find('.pab-file-upload-preview');
        var $img = $wrap.find('.pab-file-upload-preview-img');
        var $clear = $field.find('.pab-file-upload-clear');
        var $subIdle = $wrap.find('.pab-file-upload-sub--idle');
        var $subReplace = $wrap.find('.pab-file-upload-sub--replace');
        var emptyLbl = $name.attr('data-empty-label') || '';
        var el = $inp[0];
        var file = el && el.files && el.files[0];

        revokePabPreviewUrl($wrap);

        if (!file) {
            $wrap.removeClass('pab-has-file');
            $name.text(emptyLbl);
            if ($clear.length) {
                $clear.removeClass('pab-file-upload-clear--shown');
            }
            if ($prev.length) {
                $prev.prop('hidden', true);
                $img.attr('src', '').attr('alt', '');
            }
            if ($subReplace.length) {
                $subReplace.prop('hidden', true);
                $subIdle.prop('hidden', false);
            }
            syncImageSwatchLabelPrice($field);
            return;
        }

        $wrap.addClass('pab-has-file');
        $name.text(file.name);
        if ($clear.length) {
            $clear.addClass('pab-file-upload-clear--shown');
        }

        if ($subReplace.length && $inp.hasClass('pab-file-upload-input--image')) {
            $subIdle.prop('hidden', true);
            $subReplace.prop('hidden', false);
        }

        if ($prev.length && $inp.hasClass('pab-file-upload-input--image') && file.type.indexOf('image/') === 0) {
            var url = URL.createObjectURL(file);
            $wrap.data('pab-preview-url', url);
            $img.attr('src', url).attr('alt', file.name);
            $prev.prop('hidden', false);
        } else if ($prev.length) {
            $prev.prop('hidden', true);
            $img.attr('src', '').attr('alt', '');
        }
        syncImageSwatchLabelPrice($field);
    }

    function syncImageSwatchCustomerUpload($field) {
        if (!$field || !$field.length || !$field.is('.pab-field-type-image_swatch')) {
            return;
        }
        var $upload = $field.find('.pab-swatch-custom-upload').first();
        if (!$upload.length) {
            return;
        }
        var $sw = $field.find('.pab-image-swatch-wrap').first();
        var $checked = $sw.find('.pab-swatch-radio:checked');
        var $fileWrap = $upload.find('.pab-file-upload');
        var $inp = $fileWrap.find('.pab-file-upload-input');
        var needFile = $checked.length && String($checked.attr('data-pab-custom-upload') || '') === '1';
        if (needFile) {
            $upload.removeClass('pab-is-hidden');
            $inp.prop('required', true);
        } else {
            $upload.addClass('pab-is-hidden');
            $inp.prop('required', false);
            if ($inp.val()) {
                $inp.val('');
                syncPabFileUpload($fileWrap);
            }
        }
        syncImageSwatchLabelPrice($field);
    }

    $(document).on('change', '.pab-file-upload-input', function () {
        syncPabFileUpload($(this).closest('.pab-file-upload'));
    });

    $(document).on('click', '.pab-file-upload-clear', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $field = $(this).closest('.pab-field-wrap');
        var $wrap = $field.find('.pab-file-upload').first();
        var $inp = $wrap.find('.pab-file-upload-input');
        $inp.val('');
        syncPabFileUpload($wrap);
    });

    $(document).on('dragenter', '.pab-file-upload', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $z = $(this);
        var n = (parseInt($z.data('pab-dnd-n'), 10) || 0) + 1;
        $z.data('pab-dnd-n', n);
        $z.addClass('pab-is-dragover');
    });

    $(document).on('dragleave', '.pab-file-upload', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $z = $(this);
        var n = (parseInt($z.data('pab-dnd-n'), 10) || 1) - 1;
        if (n <= 0) {
            $z.removeData('pab-dnd-n');
            $z.removeClass('pab-is-dragover');
        } else {
            $z.data('pab-dnd-n', n);
        }
    });

    $(document).on('dragover', '.pab-file-upload', function (e) {
        e.preventDefault();
        e.stopPropagation();
    });

    $(document).on('drop', '.pab-file-upload', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeData('pab-dnd-n').removeClass('pab-is-dragover');
    });

    $(document).on('change input', '.pab-field-wrap input, .pab-field-wrap select, .pab-field-wrap textarea', updatePrice);
    $(document).on('change', 'input.qty[name="quantity"]', updatePrice);

    $(document).on('input', '.pab-child-qty', function () {
        var $inp = $(this);
        if ($inp.attr('type') === 'hidden') return;
        syncChildWrapVisual($inp.closest('.pab-child-wrap'));
        updatePrice();
    });

    $(document).on('change', '.pab-child-qty', function () {
        var $inp = $(this);
        var $wrap = $inp.closest('.pab-child-wrap');
        if ($inp.attr('type') === 'hidden') {
            updatePrice();
            return;
        }
        var clamped = clampChildQty($wrap, $inp.val());
        $inp.val(clamped);
        syncChildWrapVisual($wrap);
        updatePrice();
    });

    function refreshChildVariationPrice($select) {
        var $opt = $select.find('option:selected');
        var varPrice = parseFloat($opt.data('price'));
        var $wrap = $select.closest('.pab-child-wrap');
        var $price = $wrap.find('.pab-child-swatch-price-row .pab-child-price').length
            ? $wrap.find('.pab-child-swatch-price-row .pab-child-price')
            : $wrap.find('.pab-child-price');
        if ($opt.val() && !isNaN(varPrice)) {
            $price.html('+' + formatPrice(varPrice)).show().removeAttr('aria-hidden');
        } else if ($wrap.hasClass('pab-child--swatch-exclusive')) {
            var fb = $wrap.attr('data-pab-swatch-price-fallback');
            if (fb) {
                $price.html(fb).show().removeAttr('aria-hidden');
            } else {
                $price.empty().hide().attr('aria-hidden', 'true');
            }
        } else {
            $price.empty().hide().attr('aria-hidden', 'true');
        }
    }

    // -------------------------------------------------------------------------
    // Add = confirm intent to include extra; then only +/- changes qty / live total
    // -------------------------------------------------------------------------
    $(document).on('click', '.pab-child-add-btn', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.pab-child-wrap');
        if (String($wrap.data('is-variable')) === '1' && !childHasVariationSelected($wrap)) {
            return;
        }
        var $qty = $wrap.find('.pab-child-qty').first();
        var min = parseInt($wrap.data('min-qty'), 10);
        if (isNaN(min)) min = 0;
        var start = Math.max(1, min);
        $qty.val(clampChildQty($wrap, start));
        $wrap.addClass('pab-child--extras-confirmed');
        syncChildWrapVisual($wrap);
        updatePrice();
    });

    $(document).on('click', '.pab-child-remove-btn', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.pab-child-wrap');
        $wrap.find('.pab-child-quantity-wrap .minus').trigger('click');
    });

    $(document).on('change', '.pab-child-variation-select', function () {
        refreshChildVariationPrice($(this));
        syncChildWrapVisual($(this).closest('.pab-child-wrap'));
        updatePrice();
    });

    $(document.body).on('click', 'form.cart .single_add_to_cart_button', function () {
        var $g = $('.pab-child-swatch-group[data-pab-swatch-required="1"]');
        if (!$g.length) {
            return;
        }
        if (!$g.find('.pab-child-swatch-radio:checked').length) {
            var msg = (pabData.i18n && pabData.i18n.chooseExtra) ? pabData.i18n.chooseExtra : 'Please choose an extra.';
            window.alert(msg);
            return false;
        }
        var ok = true;
        $g.find('.pab-child-wrap').each(function () {
            var $w = $(this);
            if (!$w.find('.pab-child-swatch-radio').is(':checked')) {
                return;
            }
            if (String($w.data('is-variable')) === '1') {
                var $s = $w.find('.pab-child-variation-select');
                if ($s.length && !$s.val()) {
                    ok = false;
                }
            }
        });
        if (!ok) {
            var msg2 = (pabData.i18n && pabData.i18n.chooseVariation) ? pabData.i18n.chooseVariation : 'Please choose options for the selected extra.';
            window.alert(msg2);
            return false;
        }
    });

    // -------------------------------------------------------------------------
    // Popup overlays — keep dialogs inside form.cart so pab_popup / pab_popup_file submit.
    // HTML5 form= on popup fields helps AJAX quick-view / odd DOM layouts.
    // -------------------------------------------------------------------------
    var pabInjectTimer = null;

    /**
     * Point popup field controls at the cart form so POST and FormData(form) always include them.
     */
    function pabEnsurePopupFieldsCartForm() {
        var $form = $('form.cart').filter(function () {
            return $(this).find('#pab-product-addons, .pab-product-addons').length > 0;
        }).first();
        if (!$form.length) {
            $form = $('form.cart').first();
        }
        if (!$form.length) {
            return;
        }
        var fid = $form.attr('id');
        if (!fid || fid === '') {
            var pid = (pabData && pabData.productId != null) ? String(pabData.productId) : '0';
            fid = 'pab-wc-cart-' + pid;
            $form.attr('id', fid);
        }
        document.querySelectorAll('.pab-popup-dialog input, .pab-popup-dialog select, .pab-popup-dialog textarea').forEach(function (el) {
            var n = el.getAttribute('name');
            if (!n || n.indexOf('pab_popup') !== 0) {
                return;
            }
            el.setAttribute('form', fid);
        });
    }

    function pabScheduleInitInjectedAddons() {
        clearTimeout(pabInjectTimer);
        pabInjectTimer = window.setTimeout(function () {
            pabInitInjectedAddons();
        }, 40);
    }

    function pabInitInjectedAddons() {
        $('.pab-child-swatch-group').each(function () {
            syncExclusiveSwatchGroup($(this));
        });
        $('.pab-child-wrap').each(function () {
            var $wrap = $(this);
            var $qty = $wrap.find('.pab-child-qty').first();
            if ($qty.attr('type') !== 'hidden') {
                var c = clampChildQty($wrap, $qty.val());
                $qty.val(c);
            }
            syncChildWrapVisual($wrap);
        });
        $('.pab-child-variation-select').each(function () {
            refreshChildVariationPrice($(this));
        });
        $('.pab-field-wrap.pab-field-type-image_swatch').each(function () {
            cacheImageSwatchDefaultLabel($(this));
        });
        $('.pab-file-upload').each(function () {
            syncPabFileUpload($(this));
        });
        $('.pab-field-wrap.pab-field-type-image_swatch[data-pab-swatch-customer-upload="1"]').each(function () {
            syncImageSwatchCustomerUpload($(this));
        });
        $('.pab-field-wrap.pab-field-type-image_swatch').each(function () {
            syncImageSwatchLabelPrice($(this));
        });
        pabEnsurePopupFieldsCartForm();
        updatePrice();
    }

    /* Woodmart quick view / AJAX product injects markup after load */
    $(document).on('ajaxComplete.pabPopups', function () {
        pabScheduleInitInjectedAddons();
    });

    function pabClosePopupOverlay(dlg) {
        if (!dlg || !dlg.classList || !dlg.classList.contains('pab-popup-dialog')) {
            return;
        }
        dlg.setAttribute('hidden', '');
        if (!document.querySelector('.pab-popup-dialog:not([hidden])')) {
            document.documentElement.classList.remove('pab-popup-modal-open');
        }
    }

    function pabFocusPopupTriggerByDialogId(id) {
        if (!id) {
            return;
        }
        var $btn = $('.pab-popup-open[aria-controls="' + id + '"]');
        if ($btn.length) {
            $btn.trigger('focus');
        }
    }

    $(document).on('click', '.pab-popup-open', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).attr('aria-controls');
        var el = id ? document.getElementById(id) : null;
        if (!el || !el.classList.contains('pab-popup-dialog')) {
            return;
        }
        el.removeAttribute('hidden');
        document.documentElement.classList.add('pab-popup-modal-open');
        window.setTimeout(function () {
            var $focus = $(el).find('input, select, textarea, button').filter(':visible').not('.pab-popup-close').first();
            if (!$focus.length) {
                $focus = $(el).find('button:visible').first();
            }
            if ($focus.length) {
                $focus.trigger('focus');
            }
        }, 0);
    });

    $(document).on('click', '.pab-popup-dialog', function (e) {
        if (e.target !== this) {
            return;
        }
        var dlg = this;
        pabClosePopupOverlay(dlg);
        pabFocusPopupTriggerByDialogId(dlg.id);
    });

    $(document).on('click', '.pab-popup-close, .pab-popup-done', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var dlg = $(this).closest('.pab-popup-dialog')[0];
        if (dlg) {
            var did = dlg.id;
            pabClosePopupOverlay(dlg);
            pabFocusPopupTriggerByDialogId(did);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' && e.keyCode !== 27) {
            return;
        }
        var open = document.querySelector('.pab-popup-dialog:not([hidden])');
        if (!open) {
            return;
        }
        e.preventDefault();
        var oid = open.id;
        pabClosePopupOverlay(open);
        pabFocusPopupTriggerByDialogId(oid);
    });

    // -------------------------------------------------------------------------
    // Woodmart: AJAX add-to-cart uses $form.serialize(), which omits file inputs.
    // Submit via FormData in capture phase so $_FILES reaches WooCommerce / PAB.
    // -------------------------------------------------------------------------
    function pabCartFormHasPabFileWithData(form) {
        if (!form || !form.querySelectorAll) {
            return false;
        }
        var inputs = form.querySelectorAll('input[type="file"][name^="pab_addon_file"], input[type="file"][name^="pab_popup_file"]');
        var i;
        for (i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length > 0) {
                return true;
            }
        }
        return false;
    }

    function pabRunWoodmartAddToCartSuccessWorkflow(response, $thisbutton) {
        if (!response) {
            return;
        }
        if (response.error && response.product_url) {
            window.location = response.product_url;
            return;
        }
        if (typeof woodmart_settings === 'undefined') {
            return;
        }
        if (woodmart_settings.cart_redirect_after_add === 'yes') {
            window.location = woodmart_settings.cart_url;
            return;
        }
        $thisbutton.removeClass('loading');
        var fragments = response.fragments || {};
        var cart_hash = response.cart_hash;
        if (fragments) {
            $.each(fragments, function (key) {
                $(key).addClass('updating');
            });
            $.each(fragments, function (key, value) {
                $(key).replaceWith(value);
            });
        }
        var $noticeWrapper = $('.woocommerce-notices-wrapper');
        $noticeWrapper.empty();
        if (response.notices && response.notices.indexOf('error') > 0) {
            $noticeWrapper.append(response.notices);
            $thisbutton.addClass('not-added');
            if (typeof woodmartThemeModule !== 'undefined' && woodmartThemeModule.$body) {
                woodmartThemeModule.$body.trigger('not_added_to_cart', [fragments, cart_hash, $thisbutton]);
            }
        } else {
            if (typeof $.fn.magnificPopup !== 'undefined' && woodmart_settings.add_to_cart_action === 'widget') {
                $.magnificPopup.close();
            }
            $thisbutton.addClass('added');
            if (typeof woodmartThemeModule !== 'undefined' && woodmartThemeModule.$body) {
                woodmartThemeModule.$body.trigger('added_to_cart', [fragments, cart_hash, $thisbutton]);
            }
        }
    }

    function pabWoodmartMultipartSubmitCapture(e) {
        if (typeof woodmart_settings === 'undefined' || !woodmart_settings.ajaxurl) {
            return;
        }
        if (woodmart_settings.ajax_add_to_cart == false) {
            return;
        }
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || !form.classList.contains('cart')) {
            return;
        }
        if (!pabCartFormHasPabFileWithData(form)) {
            return;
        }
        var $form = $(form);
        var $productWrapper = $form.parents('.single-product-page');
        if ($productWrapper.length === 0) {
            $productWrapper = $form.parents('.product-quick-view');
        }
        if ($productWrapper.hasClass('product-type-external') || $productWrapper.hasClass('product-type-zakeke') || $productWrapper.hasClass('product-type-gift-card')) {
            return;
        }
        var sub = e.submitter;
        if (sub && $(sub).hasClass('wd-buy-now-btn')) {
            return;
        }
        if ($form.parents('.wd-sticky-btn-cart').length > 0) {
            var $stickyBtnWrap = $form.parents('.wd-sticky-btn-cart');
            if ($stickyBtnWrap.hasClass('wd-product-type-external')) {
                return;
            }
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        var $thisbutton = $form.find('.single_add_to_cart_button');
        var fd = new FormData(form);
        fd.append('action', 'woodmart_ajax_add_to_cart');
        if ($thisbutton.val()) {
            fd.set('add-to-cart', $thisbutton.val());
        }

        $thisbutton.removeClass('added not-added');
        $thisbutton.addClass('loading');

        if (typeof woodmartThemeModule !== 'undefined' && woodmartThemeModule.$body) {
            woodmartThemeModule.$body.trigger('adding_to_cart', [$thisbutton, fd]);
        }

        $.ajax({
            url: woodmart_settings.ajaxurl,
            data: fd,
            method: 'POST',
            processData: false,
            contentType: false,
            success: function (response) {
                pabRunWoodmartAddToCartSuccessWorkflow(response, $thisbutton);
            },
            error: function () {
                if (typeof console !== 'undefined' && console.log) {
                    console.log('PAB: multipart add-to-cart request failed');
                }
            }
        });
    }

    // -------------------------------------------------------------------------
    // Init on DOM ready
    // -------------------------------------------------------------------------
    $(function () {
        if (document.body) {
            document.body.addEventListener('submit', pabWoodmartMultipartSubmitCapture, true);
        }
        pabInitInjectedAddons();
    });

})(jQuery);
