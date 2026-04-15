/* global pabAdmin */
(function ($) {
	'use strict';

	var addonIndex = 0;
	var childIndex = 0;
	var ruleIndex = 0;

	var PH_FIELD = '__PAB_FIELD_INDEX__';
	var PH_OPT = '__PAB_OPT_INDEX__';
	var PH_CHILD = '__PAB_CHILD_INDEX__';
	var PH_RULE = '__PAB_RULE_INDEX__';
	var PH_FIELD_ID = '__PAB_FIELD_ID__';
	var PH_OPT_ID = '__PAB_OPT_ID__';
	var PH_RULE_ID = '__PAB_RULE_ID__';
	var PH_LOCATION_RULE = '__PAB_LOCATION_RULE_INDEX__';
	var PH_FIELD_NAME_ROOT = '__PAB_FIELD_NAME_ROOT__';
	var OPTION_TYPES = ['select', 'radio', 'image_swatch', 'text_swatch'];
	var FIELD_NAME_RE = /pab_addon_fields\[\d+]/g;
	var OPTION_NAME_RE = /pab_addon_fields\[\d+]\[options]\[\d+]/g;
	var NESTED_OPTION_NAME_RE = /pab_addon_fields\[\d+]\[nested_fields]\[\d+]\[options]\[\d+]/g;
	var CHILD_NAME_RE = /pab_child_products\[\d+]/g;
	var RULE_NAME_RE = /pab_conditional_rules\[\d+]/g;

	function randId(prefix) {
		return prefix + '_' + Math.random().toString(36).slice(2, 11);
	}

	function escapeRegExp(str) {
		return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	function cloneTemplate(templateId, replacements) {
		var $tmpl = $('#' + templateId);
		if (!$tmpl.length) {
			return $();
		}
		var html = $tmpl.html();
		$.each(replacements, function (ph, value) {
			html = html.replace(new RegExp(escapeRegExp(ph), 'g'), value);
		});
		return $(html);
	}

	function i18n(key, fallback) {
		return (pabAdmin.i18n && pabAdmin.i18n[key]) || fallback;
	}

	function initIndexes() {
		$('#pab-addon-fields-list > .pab-addon-row').each(function (i) {
			$(this).attr('data-index', i);
		});
		$('#pab-child-products-list .pab-child-row').each(function (i) {
			$(this).attr('data-index', i);
		});
		$('#pab-rules-list .pab-rule-row').each(function (i) {
			$(this).attr('data-index', i);
		});
		addonIndex = $('#pab-addon-fields-list > .pab-addon-row').length;
		childIndex = $('#pab-child-products-list .pab-child-row').length;
		ruleIndex = $('#pab-rules-list .pab-rule-row').length;
	}

	function reindexList($list, rowSelector, replaceFn) {
		$list.find(rowSelector).each(function (newIndex) {
			var $row = $(this);
			$row.attr('data-index', newIndex);
			$row.find('input,select,textarea,label').each(function () {
				var $el = $(this);
				['name', 'id', 'for'].forEach(function (attr) {
					var current = $el.attr(attr);
					if (!current) {
						return;
					}
					$el.attr(attr, replaceFn(current, newIndex));
				});
			});
		});
	}

	function reindexNestedAddonRows($topRow) {
		var parentIdx = $topRow.data('index');
		$topRow.find('.pab-popup-settings .pab-popup-nested-list').first().children('.pab-addon-row--nested').each(function (nidx) {
			var $nrow = $(this);
			$nrow.attr('data-index', nidx);
			var root = 'pab_addon_fields[' + parentIdx + '][nested_fields][' + nidx + ']';
			$nrow.attr('data-pab-name-root', root);
			$nrow.children('.pab-settings-card__body').find('input,select,textarea,label').each(function () {
				var $el = $(this);
				['name', 'id', 'for'].forEach(function (attr) {
					var current = $el.attr(attr);
					if (!current || current.indexOf('pab_addon_fields[') !== 0) {
						return;
					}
					if (current.indexOf('[nested_fields]') === -1) {
						return;
					}
					if (current.indexOf('[options]') !== -1) {
						return;
					}
					var suffix = current.replace(/^pab_addon_fields\[[^\]]+\]\[nested_fields\]\[[^\]]+\]/, '');
					$el.attr(attr, root + suffix);
				});
			});
			var idSafe = root.replace(/[^\w]+/g, '-');
			$nrow.find('.pab-field-image-swatch-size').attr('id', 'pab-field-swatch-size-' + idSafe);
			$nrow.find('label[for^="pab-field-swatch-size"]').attr('for', 'pab-field-swatch-size-' + idSafe);
			$nrow.find('.pab-option-line').each(function (optIndex) {
				$(this).find('input,select,textarea,label').each(function () {
					var $el = $(this);
					['name', 'id', 'for'].forEach(function (attr) {
						var current = $el.attr(attr);
						if (!current) {
							return;
						}
						$el.attr(attr, current.replace(NESTED_OPTION_NAME_RE, 'pab_addon_fields[' + parentIdx + '][nested_fields][' + nidx + '][options][' + optIndex + ']'));
					});
				});
			});
		});
	}

	function reindexAddonRows() {
		$('#pab-addon-fields-list > .pab-addon-row').each(function (newIdx) {
			var $row = $(this);
			$row.attr('data-index', newIdx);
			$row.attr('data-pab-name-root', 'pab_addon_fields[' + newIdx + ']');
			$row.children('.pab-settings-card__body').find('input,select,textarea,label').each(function () {
				var $el = $(this);
				if ($el.closest('.pab-popup-nested-list').length) {
					return;
				}
				['name', 'id', 'for'].forEach(function (attr) {
					var current = $el.attr(attr);
					if (!current || current.indexOf('pab_addon_fields[') !== 0) {
						return;
					}
					if (current.indexOf('[nested_fields]') !== -1) {
						return;
					}
					$el.attr(attr, current.replace(/^pab_addon_fields\[\d+]/, 'pab_addon_fields[' + newIdx + ']'));
				});
			});
			var $topOpts = $row.find('.pab-options-list').not($row.find('.pab-popup-nested-list .pab-options-list'));
			$topOpts.find('.pab-option-line').each(function (optIndex) {
				$(this).find('input,select,textarea,label').each(function () {
					var $el = $(this);
					['name', 'id', 'for'].forEach(function (attr) {
						var current = $el.attr(attr);
						if (!current) {
							return;
						}
						$el.attr(attr, current.replace(OPTION_NAME_RE, 'pab_addon_fields[' + newIdx + '][options][' + optIndex + ']'));
					});
				});
			});
			reindexNestedAddonRows($row);
		});
		addonIndex = $('#pab-addon-fields-list > .pab-addon-row').length;
		updateEmptyStates();
	}

	function reindexChildRows() {
		reindexList($('#pab-child-products-list'), '.pab-child-row', function (value, idx) {
			return value.replace(CHILD_NAME_RE, 'pab_child_products[' + idx + ']');
		});
		childIndex = $('#pab-child-products-list .pab-child-row').length;
		updateEmptyStates();
	}

	function reindexRuleRows() {
		reindexList($('#pab-rules-list'), '.pab-rule-row', function (value, idx) {
			return value.replace(RULE_NAME_RE, 'pab_conditional_rules[' + idx + ']');
		});
		$('#pab-rules-list .pab-rule-row').each(function (i) {
			$(this).find('.pab-row-label').text('Rule #' + (i + 1));
		});
		ruleIndex = $('#pab-rules-list .pab-rule-row').length;
		updateEmptyStates();
	}

	function ensureOptionsHead($addonRow) {
		var $list = $addonRow.find('.pab-options-list');
		if (!$list.find('.pab-options-head').length) {
			var $tmpl = $('#pab-tmpl-options-head');
			if ($tmpl.length) {
				$list.prepend($.trim($tmpl.html()));
			}
		}
	}

	function syncOptionsLayout($addonRow) {
		var type = $addonRow.find('.pab-field-type').val();
		var uniform = $addonRow.find('.pab-choice-price-mode').val() === 'uniform';
		var isSwatch = type === 'image_swatch';
		var $head = $addonRow.find('.pab-options-head');
		$head.find('.pab-option-head-price').toggleClass('pab-is-hidden', uniform);
		$head.find('.pab-option-head-image').toggleClass('pab-is-hidden', !isSwatch);
		$addonRow.find('.pab-option-line').each(function () {
			var $line = $(this);
			$line.find('.pab-option-col-price').toggleClass('pab-is-hidden', uniform);
			$line.find('.pab-option-col-image').toggleClass('pab-is-hidden', !isSwatch);
		});
	}

	function syncSwatchFieldSettings($row) {
		var type = $row.find('.pab-field-type').val();
		var isSwatch = type === 'image_swatch';
		$row.find('.pab-image-swatch-display-settings').toggleClass('pab-is-hidden', !isSwatch);
		$row.find('.pab-swatch-custom-field-settings').toggleClass('pab-is-hidden', !isSwatch);
		if (!isSwatch) {
			return;
		}
		var uniform = $row.find('.pab-choice-price-mode').val() === 'uniform';
		var allow = $row.find('.pab-swatch-allow-custom-upload').is(':checked');
		$row.find('.pab-swatch-custom-label-row').toggleClass('pab-is-hidden', !allow);
		$row.find('.pab-swatch-custom-price-row').toggleClass('pab-is-hidden', !allow || uniform);
	}

	function toggleChoicePricingMode($row) {
		if (OPTION_TYPES.indexOf($row.find('.pab-field-type').val()) === -1) {
			return;
		}
		var uniform = $row.find('.pab-choice-price-mode').val() === 'uniform';
		$row.find('.pab-field-level-pricing').toggleClass('pab-is-hidden', !uniform);
		$row.find('.pab-option-prices-desc').toggleClass('pab-is-hidden', uniform);
		syncOptionsLayout($row);
		syncSwatchFieldSettings($row);
	}

	function toggleOptionsSection($row, type) {
		var isNested = $row.hasClass('pab-addon-row--nested');
		var isTopPopup = !isNested && type === 'popup';
		var hasOptions = OPTION_TYPES.indexOf(type) !== -1 && !isTopPopup;
		var textPlaceholderTypes = ['text', 'textarea', 'number'];
		$row.find('.pab-field-placeholder-row').toggleClass('pab-is-hidden', textPlaceholderTypes.indexOf(type) === -1);
		$row.find('.pab-options-section').toggleClass('pab-is-hidden', !hasOptions);
		$row.find('.pab-choice-pricing-section').toggleClass('pab-is-hidden', !hasOptions || isTopPopup);
		if (!isNested) {
			$row.find('.pab-popup-settings').toggleClass('pab-is-hidden', type !== 'popup');
		}
		if (isTopPopup) {
			$row.find('.pab-field-level-pricing').addClass('pab-is-hidden');
			$row.find('.pab-image-swatch-display-settings').addClass('pab-is-hidden');
			$row.find('.pab-swatch-custom-field-settings').addClass('pab-is-hidden');
		} else if (hasOptions) {
			ensureOptionsHead($row);
			toggleChoicePricingMode($row);
		} else {
			$row.find('.pab-field-level-pricing').removeClass('pab-is-hidden');
		}
		if (!isTopPopup) {
			syncSwatchFieldSettings($row);
		}
	}

	function updateAddonRowChrome($row) {
		var label = String($row.find('.pab-field-label').val() || '').trim();
		var $type = $row.find('.pab-field-type');
		var typeLabel = String($type.find('option:selected').text() || '').trim();
		var fieldId = String($row.find('.pab-field-id').val() || '').trim();
		var $title = $row.find('.pab-addon-row__header .pab-row-label').first();
		if ($title.length) {
			$title.text(label || 'New field');
		}
		var $badge = $row.find('.pab-field-type-badge').first();
		if ($badge.length) {
			$badge.text(typeLabel);
		}
		var $key = $row.find('.pab-field-builder__key').first();
		if ($key.length) {
			var showKey = fieldId && fieldId.indexOf('__') === -1;
			if (showKey) {
				$key.text(fieldId).show();
			} else {
				$key.text('').hide();
			}
		}
	}

	function initAddonRow($row) {
		var $type = $row.find('.pab-field-type').first();
		toggleOptionsSection($row, $type.val());
		updateAddonRowChrome($row);
		$type.off('change.pabAddon');
		$type.on('change.pabAddon', function () {
			var currentType = $(this).val();
			toggleOptionsSection($row, currentType);
			updateAddonRowChrome($row);
			updateRuleFieldDropdowns();
		});
		$row.find('.pab-field-label').off('input.pabAddon');
		$row.find('.pab-field-label').on('input.pabAddon', function () {
			updateAddonRowChrome($row);
			updateRuleFieldDropdowns();
		});
		$row.find('.pab-choice-price-mode').off('change.pabAddon');
		$row.find('.pab-choice-price-mode').on('change.pabAddon', function () {
			toggleChoicePricingMode($row);
		});
		$row.find('.pab-swatch-allow-custom-upload').off('change.pabAddon');
		$row.find('.pab-swatch-allow-custom-upload').on('change.pabAddon', function () {
			syncSwatchFieldSettings($row);
		});
		syncSwatchFieldSettings($row);
	}

	function duplicateAddonRow($src) {
		var $dup = $src.clone();
		$dup.find('input, select, textarea').prop('disabled', false);
		$dup.add($dup.find('.pab-addon-row')).each(function () {
			var nid = randId('field');
			var $r = $(this);
			$r.attr('data-field-id', nid);
			$r.find('.pab-field-id').first().val(nid);
		});
		$dup.find('.pab-option-id').each(function () {
			$(this).val(randId('opt'));
		});
		$src.after($dup);
		reindexAddonRows();
		initAddonRow($dup);
		syncOptionsLayout($dup);
		syncSwatchFieldSettings($dup);
		// Expand new row, collapse others
		$('#pab-addon-fields-list > .pab-addon-row').not($dup).children('.pab-settings-card__body').hide();
		$('#pab-addon-fields-list > .pab-addon-row').not($dup).find('.pab-settings-card__toggle').attr('aria-expanded', 'false').find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
		var $body = $dup.children('.pab-settings-card__body');
		$body.show();
		$dup.find('.pab-settings-card__toggle').attr('aria-expanded', 'true').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
		updateAddonRowChrome($dup);
		updateRuleFieldDropdowns();
		$dup.find('.pab-field-label').trigger('focus');
		markFormDirty();
	}

	function addNestedAddonRow($top) {
		var $list = $top.find('.pab-popup-settings .pab-popup-nested-list').first();
		if (!$list.length) {
			return;
		}
		var nidx = $list.find('> .pab-addon-row--nested').length;
		var fieldId = randId('field');
		var sel = String($top.find('.pab-nested-new-field-type').first().val() || 'text');
		var $row = cloneTemplate('pab-tmpl-nested-addon-row', {
			'__PAB_PARENT_INDEX__': String($top.data('index')),
			'__PAB_NESTED_INDEX__': String(nidx),
			'__PAB_FIELD_ID__': fieldId,
		});
		$row.find('input, select, textarea').prop('disabled', false);
		$row.attr('data-index', nidx);
		$row.attr('data-field-id', fieldId);
		$row.find('.pab-field-type').first().val(sel);
		$list.append($row);
		initAddonRow($row);
		reindexNestedAddonRows($top);
		$list.find('> .pab-addon-row--nested').not($row).children('.pab-settings-card__body').hide();
		$list.find('> .pab-addon-row--nested').not($row).find('.pab-settings-card__toggle').attr('aria-expanded', 'false').find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
		$row.children('.pab-settings-card__body').show();
		$row.find('.pab-settings-card__toggle').attr('aria-expanded', 'true').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
		markFormDirty();
	}

	function addAddonRow() {
		var selectedType = String($('#pab-addon-new-field-type').val() || 'text');
		var fieldId = randId('field');
		var $row = cloneTemplate('pab-tmpl-addon-row', {
			'__PAB_FIELD_INDEX__': addonIndex,
			'__PAB_FIELD_ID__': fieldId,
		});
		$row.attr('data-index', addonIndex);
		$row.attr('data-field-id', fieldId);
		$row.find('input, select, textarea').prop('disabled', false);
		$row.find('.pab-field-type').val(selectedType);
		toggleOptionsSection($row, selectedType);
		// Collapse all existing rows, expand the new one
		$('#pab-addon-fields-list > .pab-addon-row').children('.pab-settings-card__body').hide();
		$('#pab-addon-fields-list > .pab-addon-row').find('.pab-settings-card__toggle').attr('aria-expanded', 'false').find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
		$('#pab-addon-fields-list').append($row);
		var $body = $row.children('.pab-settings-card__body');
		$body.show();
		$row.find('.pab-settings-card__toggle').attr('aria-expanded', 'true').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
		$row.find('.pab-field-label').val('');
		initAddonRow($row);
		reindexAddonRows();
		updateRuleFieldDropdowns();
		$row.find('.pab-field-label').trigger('focus');
		markFormDirty();
	}

	function addOptionRow($addonRow) {
		var nameRoot = $addonRow.attr('data-pab-name-root');
		if (!nameRoot) {
			nameRoot = 'pab_addon_fields[' + $addonRow.data('index') + ']';
		}
		ensureOptionsHead($addonRow);
		var optIndex = $addonRow.find('.pab-options-list').first().find('.pab-option-line').length;
		var html = $('#pab-tmpl-option-row').html();
		html = html.replace(new RegExp(escapeRegExp(PH_FIELD_NAME_ROOT), 'g'), nameRoot);
		html = html.replace(new RegExp(escapeRegExp('__PAB_OPT_INDEX__'), 'g'), String(optIndex));
		html = html.replace(new RegExp(escapeRegExp('__PAB_OPT_ID__'), 'g'), randId('opt'));
		var $opt = $(html);
		$opt.find('input, select, textarea').prop('disabled', false);
		$addonRow.find('.pab-options-list').first().append($opt);
		syncOptionsLayout($addonRow);
		if ($addonRow.hasClass('pab-addon-row--nested')) {
			var $top = $addonRow.closest('.pab-popup-nested-list').closest('.pab-addon-row');
			reindexNestedAddonRows($top);
		} else {
			reindexAddonRows();
		}
		markFormDirty();
	}

	function productSearchNonce() {
		if (typeof wc_enhanced_select_params !== 'undefined' && wc_enhanced_select_params.search_products_nonce) {
			return wc_enhanced_select_params.search_products_nonce;
		}
		return pabAdmin.searchProductsNonce || '';
	}

	function renderVariationChecklist($row, variations, selected) {
		var rowIndex = $row.data('index');
		var $list = $row.find('.pab-variation-list');
		$list.empty();
		var selectedSet = {};
		(selected || []).forEach(function (id) {
			selectedSet[String(id)] = true;
		});
		(variations || []).forEach(function (v) {
			var isChecked = !!selectedSet[String(v.variation_id)];
			var label = v.label || ('#' + v.variation_id);
			var pretty = label + ' (' + (parseFloat(v.price) || 0).toFixed(2) + ')';
			var $label = $('<label>');
			var $input = $('<input>', {
				type: 'checkbox',
				name: 'pab_child_products[' + rowIndex + '][allowed_variations][]',
				value: v.variation_id,
			});
			if (isChecked) {
				$input.prop('checked', true);
			}
			$label.append($input).append(' ' + pretty);
			$list.append($label);
		});
		$list.attr('data-variations', JSON.stringify(variations || []));
		$list.attr('data-selected', JSON.stringify(selected || []));
	}

	function hydrateVariationList($row) {
		var $varList = $row.find('.pab-variation-list');
		var variations = [];
		var selected = [];
		try {
			variations = JSON.parse($varList.attr('data-variations') || '[]');
		} catch (e) {}
		try {
			selected = JSON.parse($varList.attr('data-selected') || '[]');
		} catch (e2) {}
		if (variations.length) {
			renderVariationChecklist($row, variations, selected);
			$row.find('.pab-child-is-variable').val('1');
			$row.find('.pab-variation-section').removeClass('pab-is-hidden');
		}
	}

	function initChildProductSearch($row) {
		if (!$.fn.select2) {
			return;
		}
		$row.find('.pab-child-product-select').select2({
			ajax: {
				url: typeof wc_enhanced_select_params !== 'undefined' ? wc_enhanced_select_params.ajax_url : pabAdmin.ajaxUrl,
				dataType: 'json',
				delay: 250,
				data: function (params) {
					return {
						term: params.term,
						action: 'woocommerce_json_search_products_and_variations',
						security: productSearchNonce(),
					};
				},
				processResults: function (data) {
					var out = [];
					$.each(data, function (id, text) {
						out.push({ id: id, text: text });
					});
					return { results: out };
				},
				cache: true,
			},
			minimumInputLength: 1,
			placeholder: 'Search for a product…',
		}).on('select2:select', function (e) {
			var $thisRow = $(this).closest('.pab-child-row');
			$thisRow.find('.pab-row-label').text(e.params.data.text || 'New Child Product');
			// Show spinner before AJAX
			var $spinnerWrap = $thisRow.find('.pab-variation-spinner');
			if (!$spinnerWrap.length) {
				$spinnerWrap = $('<span class="pab-spinner-wrap"><span class="spinner is-active"></span></span>');
				$thisRow.find('.pab-variation-section').before($spinnerWrap);
			} else {
				$spinnerWrap.find('.spinner').addClass('is-active');
				$spinnerWrap.show();
			}
			$.post(
				pabAdmin.ajaxUrl,
				{
					action: 'pab_get_variations',
					product_id: e.params.data.id,
					nonce: pabAdmin.nonce,
				},
				function (response) {
					$spinnerWrap.hide();
					if (response.success && response.data && response.data.length) {
						$thisRow.find('.pab-child-is-variable').val('1');
						$thisRow.find('.pab-variation-section').removeClass('pab-is-hidden');
						renderVariationChecklist($thisRow, response.data, []);
					} else {
						$thisRow.find('.pab-child-is-variable').val('0');
						$thisRow.find('.pab-variation-section').addClass('pab-is-hidden');
						$thisRow.find('.pab-variation-list').empty();
					}
				}
			).fail(function () {
				$spinnerWrap.hide();
				var $notice = $('<div class="notice notice-error is-dismissible pab-ajax-error-notice"><p>' + i18n('ajaxError', 'An error occurred. Please try again.') + '</p></div>');
				$thisRow.find('.pab-variation-section').before($notice);
				setTimeout(function () { $notice.fadeOut(function () { $notice.remove(); }); }, 5000);
			});
			markFormDirty();
		});
	}

	/**
	 * Collapse all siblings and expand the new row — consistent accordion for all row types.
	 */
	function collapseSiblingsAndExpand($newRow, listSelector, bodySelector) {
		$(listSelector).find('.pab-settings-card').not($newRow).each(function () {
			var $card = $(this);
			$card.children(bodySelector).hide();
			$card.find('.pab-settings-card__toggle').attr('aria-expanded', 'false').find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
		});
		var $body = $newRow.children(bodySelector);
		$body.show();
		$newRow.find('.pab-settings-card__toggle').attr('aria-expanded', 'true').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
	}

	function addChildRow() {
		var $row = cloneTemplate('pab-tmpl-child-row', { '__PAB_CHILD_INDEX__': childIndex });
		$row.attr('data-index', childIndex);
		$row.find('input,select,textarea').prop('disabled', false);
		$('#pab-child-products-list').append($row);
		// Standardized accordion: collapse siblings, expand new row
		collapseSiblingsAndExpand($row, '#pab-child-products-list', '.pab-settings-card__body');
		initChildProductSearch($row);
		childIndex++;
		updateEmptyStates();
		markFormDirty();
	}

	function addonFieldModels() {
		var models = [];
		$('#pab-addon-fields-list > .pab-addon-row').each(function (i) {
			var $row = $(this);
			var fieldId = String($row.find('.pab-field-id').val() || '');
			var label = String($row.find('.pab-field-label').val() || ('Field ' + (i + 1)));
			if (fieldId) {
				models.push({ id: fieldId, label: label });
			}
		});
		return models;
	}

	function populateFieldSelect($select) {
		var current = $select.val();
		$select.find('option:not(:first)').remove();
		addonFieldModels().forEach(function (f) {
			$select.append($('<option>', { value: f.id, text: f.label }));
		});
		$select.val(current);
	}

	function updateRuleActionInputs($row) {
		var action = $row.find('.pab-rule-action').val();
		var isFieldAction = action === 'show_field' || action === 'hide_field';
		$row.find('.pab-rule-action-target').toggleClass('pab-is-hidden', !isFieldAction);
		$row.find('.pab-rule-action-amount').toggleClass('pab-is-hidden', isFieldAction);
	}

	function updateRuleFieldDropdowns() {
		$('#pab-rules-list .pab-rule-trigger-field, #pab-rules-list .pab-rule-action-target').each(function () {
			populateFieldSelect($(this));
		});
	}

	function initRuleRow($row) {
		populateFieldSelect($row.find('.pab-rule-trigger-field'));
		populateFieldSelect($row.find('.pab-rule-action-target'));
		updateRuleActionInputs($row);
		$row.find('.pab-rule-action').on('change', function () {
			updateRuleActionInputs($row);
		});
	}

	function addRuleRow() {
		var $row = cloneTemplate('pab-tmpl-rule-row', {
			'__PAB_RULE_INDEX__': ruleIndex,
			'__PAB_RULE_ID__': randId('rule'),
		});
		$row.attr('data-index', ruleIndex);
		$row.find('input,select,textarea').prop('disabled', false);
		$row.find('.pab-row-label').text('Rule #' + (ruleIndex + 1));
		$('#pab-rules-list').append($row);
		// Standardized accordion: collapse siblings, expand new row
		collapseSiblingsAndExpand($row, '#pab-rules-list', '.pab-settings-card__body');
		initRuleRow($row);
		ruleIndex++;
		updateEmptyStates();
		markFormDirty();
	}

	function initSearchFilter() {
		if ($.fn.selectWoo) {
			$('#pab-addon-new-field-type').filter(':not(.enhanced)').trigger('wc-enhanced-select-init');
		}
	}

	/* ---- Keyboard reorder (move up / down) ---- */

	function moveRowUp($row) {
		if ($row.hasClass('pab-addon-row--nested')) {
			var $prev = $row.prev('.pab-addon-row--nested');
			if ($prev.length) {
				$prev.before($row);
				var $top = $row.closest('.pab-popup-nested-list').closest('.pab-addon-row');
				reindexNestedAddonRows($top);
				markFormDirty();
			}
			return;
		}
		var $prev = $row.prev('.pab-addon-row, .pab-child-row, .pab-rule-row');
		if ($prev.length) {
			$prev.before($row);
			reindexAfterSort($row);
		}
	}

	function moveRowDown($row) {
		if ($row.hasClass('pab-addon-row--nested')) {
			var $next = $row.next('.pab-addon-row--nested');
			if ($next.length) {
				$next.after($row);
				var $top = $row.closest('.pab-popup-nested-list').closest('.pab-addon-row');
				reindexNestedAddonRows($top);
				markFormDirty();
			}
			return;
		}
		var $next = $row.next('.pab-addon-row, .pab-child-row, .pab-rule-row');
		if ($next.length) {
			$next.after($row);
			reindexAfterSort($row);
		}
	}

	function reindexAfterSort($row) {
		if ($row.hasClass('pab-addon-row')) {
			reindexAddonRows();
			updateRuleFieldDropdowns();
		} else if ($row.hasClass('pab-child-row')) {
			reindexChildRows();
		} else if ($row.hasClass('pab-rule-row')) {
			reindexRuleRows();
		}
		markFormDirty();
	}

	function initSortable() {
		if (!$.fn.sortable) {
			return;
		}
		$('#pab-addon-fields-list').sortable({
			items: '> .pab-addon-row',
			handle: '.pab-drag-handle',
			placeholder: 'pab-sortable-placeholder',
			stop: function () {
				reindexAddonRows();
				updateRuleFieldDropdowns();
			},
		});
		$('#pab-child-products-list').sortable({
			items: '.pab-child-row',
			handle: '.pab-drag-handle',
			placeholder: 'pab-sortable-placeholder',
			stop: function () {
				reindexChildRows();
			},
		});
		$('#pab-rules-list').sortable({
			items: '.pab-rule-row',
			handle: '.pab-drag-handle',
			placeholder: 'pab-sortable-placeholder',
			stop: function () {
				reindexRuleRows();
			},
		});
	}

	/* ---- Empty state placeholders ---- */

	function updateEmptyStates() {
		// Addon fields
		var $addonList = $('#pab-addon-fields-list');
		var $addonEmpty = $addonList.find('.pab-empty-state');
		if (!$addonList.find('> .pab-addon-row').length) {
			if (!$addonEmpty.length) {
				$addonList.append('<div class="pab-empty-state">' + i18n('emptyAddonFields', 'Add your first add-on field using the toolbar above.') + '</div>');
			}
		} else {
			$addonEmpty.remove();
		}

		// Child products
		var $childList = $('#pab-child-products-list');
		var $childEmpty = $childList.find('.pab-empty-state');
		if (!$childList.find('.pab-child-row').length) {
			if (!$childEmpty.length) {
				$childList.append('<div class="pab-empty-state">' + i18n('emptyChildProducts', 'Add a child product using the button below.') + '</div>');
			}
		} else {
			$childEmpty.remove();
		}

		// Conditional rules
		var $ruleList = $('#pab-rules-list');
		var $ruleEmpty = $ruleList.find('.pab-empty-state');
		if (!$ruleList.find('.pab-rule-row').length) {
			if (!$ruleEmpty.length) {
				$ruleList.append('<div class="pab-empty-state">' + i18n('emptyRules', 'Add a conditional rule using the button below.') + '</div>');
			}
		} else {
			$ruleEmpty.remove();
		}
	}

	/* ---- Unsaved changes tracking ---- */

	var formDirty = false;

	function markFormDirty() {
		formDirty = true;
	}

	function initUnsavedChangesWarning() {
		$(document).on('input change', '#pab_addons_data input, #pab_addons_data select, #pab_addons_data textarea, ' +
			'#pab_group_addons input, #pab_group_addons select, #pab_group_addons textarea', function () {
			formDirty = true;
		});

		$(window).on('beforeunload', function (e) {
			if (formDirty) {
				e.preventDefault();
				return i18n('unsavedChanges', 'You have unsaved changes. Are you sure you want to leave?');
			}
		});

		// Clear dirty flag on form submit (saving)
		$('#post').on('submit', function () {
			formDirty = false;
		});
	}

	/* ---- Client-side validation ---- */

	function validateRequiredFields() {
		var valid = true;
		$('#pab_addons_data .pab-field-label, #pab_group_addons .pab-field-label').each(function () {
			var $input = $(this);
			if (!$input.val() || !$input.val().trim()) {
				$input.addClass('pab-field-error');
				valid = false;
			} else {
				$input.removeClass('pab-field-error');
			}
		});
		return valid;
	}

	/* ---- Click handlers ---- */

	$(document).on('click', '.pab-add-option', function () {
		addOptionRow($(this).closest('.pab-addon-row'));
	});
	$(document).on('click', '.pab-add-addon-field', addAddonRow);
	$(document).on('click', '.pab-add-child-product', addChildRow);
	$(document).on('click', '.pab-add-rule', addRuleRow);
	$(document).on('click', '.pab-add-nested-addon-field', function (e) {
		e.preventDefault();
		var $top = $(this).closest('.pab-addon-row');
		if ($top.hasClass('pab-addon-row--nested')) {
			return;
		}
		addNestedAddonRow($top);
	});

	$(document).on('click', '.pab-remove-row', function (e) {
		e.preventDefault();
		var $card = $(this).closest('.pab-settings-card');
		var confirmMsg;

		if ($card.hasClass('pab-addon-row--nested')) {
			var $topRm = $card.closest('.pab-popup-nested-list').closest('.pab-addon-row');
			if (!window.confirm(i18n('removeAddonConfirm', 'Delete this add-on field? Its options will be lost.'))) {
				return;
			}
			$card.remove();
			reindexNestedAddonRows($topRm);
			updateRuleFieldDropdowns();
			markFormDirty();
			return;
		}
		if ($card.hasClass('pab-addon-row')) {
			confirmMsg = i18n('removeAddonConfirm', 'Delete this add-on field? Its options will be lost.');
		} else if ($card.hasClass('pab-child-row')) {
			confirmMsg = i18n('removeChildConfirm', 'Remove this child product?');
		} else if ($card.hasClass('pab-rule-row')) {
			confirmMsg = i18n('removeRuleConfirm', 'Remove this conditional rule?');
		}

		if (confirmMsg && !window.confirm(confirmMsg)) {
			return;
		}

		$card.remove();
		reindexAddonRows();
		reindexChildRows();
		reindexRuleRows();
		updateRuleFieldDropdowns();
		markFormDirty();
	});

	$(document).on('click', '.pab-duplicate-addon-row', function (e) {
		e.preventDefault();
		e.stopPropagation();
		duplicateAddonRow($(this).closest('.pab-addon-row'));
	});

	$(document).on('click', '.pab-addon-row__header', function (e) {
		if ($(e.target).closest('button, a, .pab-drag-handle, input, select, textarea').length) {
			return;
		}
		var $toggle = $(this).find('.pab-settings-card__toggle').first();
		if ($toggle.length) {
			$toggle.trigger('click');
		}
	});

	$(document).on('click', '.pab-remove-option', function (e) {
		e.preventDefault();
		if (!window.confirm(i18n('removeOptionConfirm', 'Remove this choice?'))) {
			return;
		}
		$(this).closest('.pab-option-line').remove();
		reindexAddonRows();
		markFormDirty();
	});

	$(document).on('click', '#pab_addons_data .pab-settings-card__toggle, #pab_group_addons .pab-settings-card__toggle', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $card = $btn.closest('.pab-settings-card');
		var $body = $card.children('.pab-settings-card__body');
		$body.slideToggle(120);
		var open = $body.is(':visible');
		$btn.attr('aria-expanded', open ? 'true' : 'false');
		$btn.find('.dashicons').toggleClass('dashicons-arrow-down-alt2', open).toggleClass('dashicons-arrow-right-alt2', !open);
	});

	/* ---- Media frame (fixed closure bug) ---- */

	$(document).on('click', '.pab-select-image', function (e) {
		e.preventDefault();
		var $row = $(this).closest('.pab-option-line');
		var $hidden = $row.find('.pab-option-image-url');
		var $preview = $row.find('.pab-option-preview');

		// Always create a new frame to avoid closure bugs with stale $hidden/$preview
		var mediaFrame = wp.media({ title: 'Select Swatch Image', button: { text: 'Use this image' }, multiple: false });
		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			$hidden.val(attachment.url);
			$preview.attr('src', attachment.url).removeClass('is-empty').show();
			mediaFrame = null;
			markFormDirty();
		});
		mediaFrame.open();
	});

	$(document).on('click', '.pab-select-popup-side-image', function (e) {
		e.preventDefault();
		var $td = $(this).closest('td');
		var $hidden = $td.find('.pab-popup-side-image-url');
		var $preview = $td.find('.pab-popup-side-image-preview');
		var $clear = $td.find('.pab-clear-popup-side-image');

		var mediaFrame = wp.media({ title: 'Select popup image', button: { text: 'Use this image' }, multiple: false });
		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			var url = attachment.url || '';
			$hidden.val(url);
			if (url) {
				$preview.attr('src', url).removeClass('is-empty').show();
				$clear.removeClass('pab-is-hidden');
			}
			mediaFrame = null;
			markFormDirty();
		});
		mediaFrame.open();
	});

	$(document).on('click', '.pab-clear-popup-side-image', function (e) {
		e.preventDefault();
		var $td = $(this).closest('td');
		var $hidden = $td.find('.pab-popup-side-image-url');
		var $preview = $td.find('.pab-popup-side-image-preview');
		$hidden.val('');
		$preview.attr('src', '').addClass('is-empty').hide();
		$(this).addClass('pab-is-hidden');
		markFormDirty();
	});

	/* ---- Keyboard reorder buttons ---- */

	$(document).on('click', '.pab-move-up', function (e) {
		e.preventDefault();
		e.stopPropagation();
		moveRowUp($(this).closest('.pab-addon-row, .pab-child-row, .pab-rule-row'));
	});

	$(document).on('click', '.pab-move-down', function (e) {
		e.preventDefault();
		e.stopPropagation();
		moveRowDown($(this).closest('.pab-addon-row, .pab-child-row, .pab-rule-row'));
	});

	/* ---- Assignment row management (product tab) ---- */

	function reindexAssignmentRows() {
		$('#pab-assignments-rows .pab-assignment-row').each(function (idx) {
			$(this).find('input, select').each(function () {
				var name = $(this).attr('name');
				if (!name) {
					return;
				}
				$(this).attr('name', name.replace(/pab_assignments\[\d+]/g, 'pab_assignments[' + idx + ']'));
			});
		});
	}

	function toggleAssignmentRowTargets($row) {
		var type = String($row.find('.pab-assignment-target-type').val() || 'product');
		$row.find('.pab-assignment-target').closest('td').show();
		$row.find('.pab-assignment-target-product').closest('td').toggle(type === 'product');
		$row.find('.pab-assignment-target-product_cat').closest('td').toggle(type === 'product_cat');
		$row.find('.pab-assignment-target-product_tag').closest('td').toggle(type === 'product_tag');
	}

	function initAssignmentRow($row) {
		toggleAssignmentRowTargets($row);
		$row.find('.pab-assignment-target-type').on('change', function () {
			toggleAssignmentRowTargets($row);
		});

		if ($.fn.selectWoo) {
			$row.find('.wc-enhanced-select').filter(':not(.enhanced)').filter(':visible').trigger('wc-enhanced-select-init');
		}
	}

	function addAssignmentRow() {
		var idx = $('#pab-assignments-rows .pab-assignment-row').length;
		var tmpl = wp.template('pab-assignment-row');
		var $row = $(tmpl({ index: idx }));
		$('#pab-assignments-rows').append($row);
		initAssignmentRow($row);
		reindexAssignmentRows();
	}

	$(document).on('click', '#pab-add-assignment-row', function (e) {
		e.preventDefault();
		addAssignmentRow();
	});

	$(document).on('click', '.pab-remove-assignment-row', function (e) {
		e.preventDefault();
		if (!window.confirm(i18n('removeAssignmentConfirm', 'Remove this assignment row?'))) {
			return;
		}
		$(this).closest('.pab-assignment-row').remove();
		if (!$('#pab-assignments-rows .pab-assignment-row').length) {
			addAssignmentRow();
		}
		reindexAssignmentRows();
		markFormDirty();
	});

	/* ---- Group assignment table (product tab) ---- */

	function reindexGroupAssignmentRows() {
		var idx = 0;
		$('.pab-assignments-table tbody tr').each(function () {
			$(this).find('input, select').each(function () {
				var name = $(this).attr('name');
				if (!name) {
					return;
				}
				$(this).attr('name', name.replace(/pab_product_group_assignments\[\d+]/g, 'pab_product_group_assignments[' + idx + ']'));
			});
			idx++;
		});
	}

	$(document).on('click', '.pab-assignments-table .pab-remove-assignment-row', function (e) {
		e.preventDefault();
		$(this).closest('tr').remove();
		reindexGroupAssignmentRows();
		markFormDirty();
	});

	$(document).on('click', '#pab-add-group-assignment', function (e) {
		e.preventDefault();
		var $tbody = $('.pab-assignments-table tbody');
		var idx = $tbody.find('tr').length;
		var $select = $tbody.find('tr:first select').clone();
		// Reset the select to default value
		$select.find('option').prop('selected', false);
		$select.val('0');
		var $row = $('<tr>' +
			'<td></td>' +
			'<td><input type="number" class="small-text" name="pab_product_group_assignments[' + idx + '][priority]" value="100" /></td>' +
			'<td><input type="checkbox" name="pab_product_group_assignments[' + idx + '][status]" value="1" checked /></td>' +
			'<td><a href="#" class="pab-remove-assignment-row" aria-label="' + i18n('removeAssignment', 'Remove') + '">' + i18n('removeText', 'Remove') + '</a></td>' +
			'</tr>');
		$row.find('td:first').append($select);
		$tbody.append($row);
		reindexGroupAssignmentRows();
		markFormDirty();
	});

	/* ---- Location rules (Addon Group CPT) ---- */

	function pabEscapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function reindexLocationRuleRows() {
		$('#pab-group-location-rules-rows .pab-location-rule-row').each(function (idx) {
			$(this)
				.find('select[name^="pab_group_location_rules[rules]"]')
				.each(function () {
					var $el = $(this);
					var name = $el.attr('name');
					if (name) {
						$el.attr(
							'name',
							name.replace(/pab_group_location_rules\[rules\]\[\d+\]/, 'pab_group_location_rules[rules][' + idx + ']')
						);
					}
				});
		});
	}

	function destroyPabTermSelect($select) {
		if ($select.hasClass('enhanced')) {
			try {
				if ($select.data('select2')) {
					$select.selectWoo('destroy');
				}
			} catch (err) {
				// ignore
			}
		}
		$select.removeClass('enhanced');
	}

	function pabSyncTermSelectData($val, tax) {
		$val.removeData('taxonomy');
		$val.removeData('minimum_input_length');
		$val.attr('data-taxonomy', tax);
		$val.data('taxonomy', tax);
		$val.attr('data-minimum_input_length', '2');
		$val.data('minimum_input_length', 2);
	}

	function refreshLocationTermSelect($row) {
		var $val = $row.find('.pab-location-rule-value');
		var tax = $row.find('.pab-location-rule-param').val() || '';
		destroyPabTermSelect($val);
		$val.empty();
		pabSyncTermSelectData($val, tax);
		$val.attr('data-return_id', 'true');
		$val.data('return_id', true);
		if (pabAdmin.i18n && pabAdmin.i18n.searchTerms) {
			$val.attr('data-placeholder', pabAdmin.i18n.searchTerms);
		}
		$(document.body).trigger('wc-enhanced-select-init');
	}

	/**
	 * Build a new location rule row from the PHP-rendered template.
	 * Phase 4: Replaced string concatenation with cloneTemplate().
	 */
	function addLocationRuleRow() {
		var idx = $('#pab-group-location-rules-rows .pab-location-rule-row').length;
		var $row = cloneTemplate('pab-tmpl-location-rule-row', {
			'__PAB_LOCATION_RULE_INDEX__': idx
		});
		if (!$row.length) {
			// Fallback: if template doesn't exist (shouldn't happen), use legacy method
			$row = $(buildLocationRuleRowHtmlFallback(idx));
		}
		$('#pab-group-location-rules-rows').append($row);
		var tax = $row.find('.pab-location-rule-param').val() || '';
		pabSyncTermSelectData($row.find('.pab-location-rule-value'), tax);
		$row.find('.pab-location-rule-param').off('change.pabLoc').on('change.pabLoc', function () {
			refreshLocationTermSelect($row);
		});
		reindexLocationRuleRows();
		window.setTimeout(function () {
			$(document.body).trigger('wc-enhanced-select-init');
		}, 0);
		markFormDirty();
	}

	/**
	 * Legacy fallback — only used if the PHP template is missing.
	 */
	function buildLocationRuleRowHtmlFallback(index) {
		var taxes = pabAdmin.groupLocationTaxonomies || {};
		var defaultTax = pabAdmin.defaultLocationTaxonomy || 'product_cat';
		var i18nLocal = pabAdmin.i18n || {};
		var opEq = i18nLocal.opEqual || 'is equal to';
		var opNe = i18nLocal.opNotEqual || 'is not equal to';
		var ph = i18nLocal.searchTerms || 'Search for a term…';
		var opts = '';
		$.each(taxes, function (k, v) {
			opts +=
				'<option value="' +
				pabEscapeHtml(k) +
				'"' +
				(k === defaultTax ? ' selected' : '') +
				'>' +
				pabEscapeHtml(v + ' (' + k + ')') +
				'</option>';
		});
		return (
			'<div class="pab-location-rule-row">' +
				'<div class="pab-location-rule-col pab-location-rule-col-param">' +
				'<select name="pab_group_location_rules[rules][' +
				index +
				'][param]" class="pab-location-rule-param">' +
				opts +
				'</select></div>' +
				'<div class="pab-location-rule-col pab-location-rule-col-operator">' +
				'<select name="pab_group_location_rules[rules][' +
				index +
				'][operator]" class="pab-location-rule-operator">' +
				'<option value="==">' +
				pabEscapeHtml(opEq) +
				'</option>' +
				'<option value="!=">' +
				pabEscapeHtml(opNe) +
				'</option>' +
				'</select></div>' +
				'<div class="pab-location-rule-col pab-location-rule-col-value">' +
				'<select name="pab_group_location_rules[rules][' +
				index +
				'][value]" class="wc-taxonomy-term-search pab-location-rule-value" data-placeholder="' +
				pabEscapeHtml(ph) +
				'" data-taxonomy="' +
				pabEscapeHtml(defaultTax) +
				'" data-minimum_input_length="2" data-return_id="true"></select></div>' +
				'<div class="pab-location-rule-col pab-location-rule-col-actions">' +
				'<button type="button" class="button-link pab-remove-location-rule" aria-label="' +
				pabEscapeHtml(i18nLocal.removeLocationRule || 'Remove rule') +
				'">&times;</button></div></div>'
		);
	}

	function initPabLocationRules() {
		$('#pab-group-location-rules-rows .pab-location-rule-row').each(function () {
			var $row = $(this);
			var tax = $row.find('.pab-location-rule-param').val() || '';
			var $val = $row.find('.pab-location-rule-value');
			pabSyncTermSelectData($val, tax);
			$val.attr('data-return_id', 'true');
			$val.data('return_id', true);
			$row.find('.pab-location-rule-param').off('change.pabLoc').on('change.pabLoc', function () {
				refreshLocationTermSelect($row);
			});
		});
		$('#pab-add-location-rule')
			.off('click.pabLoc')
			.on('click.pabLoc', function (e) {
				e.preventDefault();
				addLocationRuleRow();
			});
		$(document)
			.off('click.pabLocRm', '.pab-remove-location-rule')
			.on('click.pabLocRm', '.pab-remove-location-rule', function (e) {
				e.preventDefault();
				if (
					!window.confirm(
						(pabAdmin.i18n && pabAdmin.i18n.removeLocationRuleConfirm) || 'Remove this location rule?'
					)
				) {
					return;
				}
				var $row = $(this).closest('.pab-location-rule-row');
				destroyPabTermSelect($row.find('.pab-location-rule-value'));
				$row.remove();
				reindexLocationRuleRows();
				markFormDirty();
			});
		// Run after current stack so WooCommerce's wc-enhanced-select-init handler is ready.
		window.setTimeout(function () {
			$(document.body).trigger('wc-enhanced-select-init');
		}, 0);
	}

	/* ---- Character counter for settings page ---- */

	function initCharCounter() {
		var $input = $('#pab-upload-image-drop-title');
		if (!$input.length) {
			return;
		}
		var maxLen = parseInt($input.attr('maxlength'), 10) || 240;
		var $counter = $('<span class="pab-char-count"></span>');
		$input.after($counter);

		function updateCounter() {
			var remaining = maxLen - $input.val().length;
			$counter.text($input.val().length + '/' + maxLen);
			$counter.toggleClass('is-warning', remaining < 20);
		}

		$input.on('input', updateCounter);
		updateCounter();
	}

	/* ---- Document ready ---- */

	$(function () {
		if ($('#pab-group-location-rules').length) {
			initPabLocationRules();
		}

		var hasAddonBuilder = $('#pab_addons_data').length || $('#pab_group_addons').length;
		if (!hasAddonBuilder) {
			if ($('#pab-assignments-table').length) {
				$('#pab-assignments-rows .pab-assignment-row').each(function () {
					initAssignmentRow($(this));
				});
				reindexAssignmentRows();
			}
			// Settings page
			initCharCounter();
			return;
		}

		initIndexes();
		initSearchFilter();

		$('#pab-addon-fields-list .pab-addon-row').each(function () {
			initAddonRow($(this));
		});
		reindexAddonRows();
		$('#pab-child-products-list .pab-child-row').each(function () {
			initChildProductSearch($(this));
			hydrateVariationList($(this));
		});
		$('#pab-rules-list .pab-rule-row').each(function () {
			initRuleRow($(this));
		});
		initSortable();
		updateRuleFieldDropdowns();
		updateEmptyStates();

		// Validate on submit
		$('#post').on('submit', function () {
			reindexAddonRows();
			reindexChildRows();
			reindexRuleRows();
			reindexLocationRuleRows();
			validateRequiredFields();
		});

		// Unsaved changes warning
		initUnsavedChangesWarning();

		// Character counter on settings page (also runs here since settings can be within product editor)
		initCharCounter();

		if ($('#pab-assignments-table').length) {
			$('#pab-assignments-rows .pab-assignment-row').each(function () {
				initAssignmentRow($(this));
			});
			reindexAssignmentRows();
		}
	});
})(jQuery);
