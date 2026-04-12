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
	var OPTION_TYPES = ['select', 'radio', 'image_swatch', 'text_swatch'];
	var FIELD_NAME_RE = /pab_addon_fields\[\d+]/g;
	var OPTION_NAME_RE = /pab_addon_fields\[\d+]\[options]\[\d+]/g;
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

	function initIndexes() {
		$('#pab-addon-fields-list .pab-addon-row').each(function (i) {
			$(this).attr('data-index', i);
		});
		$('#pab-child-products-list .pab-child-row').each(function (i) {
			$(this).attr('data-index', i);
		});
		$('#pab-rules-list .pab-rule-row').each(function (i) {
			$(this).attr('data-index', i);
		});
		addonIndex = $('#pab-addon-fields-list .pab-addon-row').length;
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

	function reindexAddonRows() {
		reindexList($('#pab-addon-fields-list'), '.pab-addon-row', function (value, idx) {
			return value.replace(FIELD_NAME_RE, 'pab_addon_fields[' + idx + ']');
		});
		$('#pab-addon-fields-list .pab-addon-row').each(function () {
			var $row = $(this);
			var rowIndex = $row.data('index');
			$row.find('.pab-option-line').each(function (optIndex) {
				$(this).find('input,select,textarea,label').each(function () {
					var $el = $(this);
					['name', 'id', 'for'].forEach(function (attr) {
						var current = $el.attr(attr);
						if (!current) {
							return;
						}
						$el.attr(attr, current.replace(OPTION_NAME_RE, 'pab_addon_fields[' + rowIndex + '][options][' + optIndex + ']'));
					});
				});
			});
		});
		addonIndex = $('#pab-addon-fields-list .pab-addon-row').length;
	}

	function reindexChildRows() {
		reindexList($('#pab-child-products-list'), '.pab-child-row', function (value, idx) {
			return value.replace(CHILD_NAME_RE, 'pab_child_products[' + idx + ']');
		});
		childIndex = $('#pab-child-products-list .pab-child-row').length;
	}

	function reindexRuleRows() {
		reindexList($('#pab-rules-list'), '.pab-rule-row', function (value, idx) {
			return value.replace(RULE_NAME_RE, 'pab_conditional_rules[' + idx + ']');
		});
		$('#pab-rules-list .pab-rule-row').each(function (i) {
			$(this).find('.pab-row-label').text('Rule #' + (i + 1));
		});
		ruleIndex = $('#pab-rules-list .pab-rule-row').length;
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
		var hasOptions = OPTION_TYPES.indexOf(type) !== -1;
		$row.find('.pab-options-section').toggleClass('pab-is-hidden', !hasOptions);
		$row.find('.pab-choice-pricing-section').toggleClass('pab-is-hidden', !hasOptions);
		if (hasOptions) {
			ensureOptionsHead($row);
			toggleChoicePricingMode($row);
		} else {
			$row.find('.pab-field-level-pricing').removeClass('pab-is-hidden');
		}
		syncSwatchFieldSettings($row);
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
		var $type = $row.find('.pab-field-type');
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
		var newFieldId = randId('field');
		$dup.attr('data-field-id', newFieldId);
		$dup.find('.pab-field-id').val(newFieldId);
		$dup.find('.pab-option-line').each(function () {
			$(this).find('.pab-option-id').val(randId('opt'));
		});
		$src.after($dup);
		reindexAddonRows();
		initAddonRow($dup);
		syncOptionsLayout($dup);
		syncSwatchFieldSettings($dup);
		var $body = $dup.children('.pab-settings-card__body');
		$body.show();
		$dup.find('.pab-settings-card__toggle').attr('aria-expanded', 'true').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
		updateAddonRowChrome($dup);
		updateRuleFieldDropdowns();
		$dup.find('.pab-field-label').trigger('focus');
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
		$('#pab-addon-fields-list .pab-addon-row').children('.pab-settings-card__body').hide();
		$('#pab-addon-fields-list .pab-addon-row').find('.pab-settings-card__toggle').attr('aria-expanded', 'false').find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
		$('#pab-addon-fields-list').append($row);
		var $body = $row.children('.pab-settings-card__body');
		$body.show();
		$row.find('.pab-settings-card__toggle').attr('aria-expanded', 'true').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
		$row.find('.pab-field-label').val('');
		initAddonRow($row);
		reindexAddonRows();
		updateRuleFieldDropdowns();
		$row.find('.pab-field-label').trigger('focus');
	}

	function addOptionRow($addonRow) {
		var fieldIndex = $addonRow.data('index');
		var optIndex = $addonRow.find('.pab-option-line').length;
		ensureOptionsHead($addonRow);
		var $opt = cloneTemplate('pab-tmpl-option-row', {
			'__PAB_FIELD_INDEX__': fieldIndex,
			'__PAB_OPT_INDEX__': optIndex,
			'__PAB_OPT_ID__': randId('opt'),
		});
		$opt.find('input, select, textarea').prop('disabled', false);
		$addonRow.find('.pab-options-list').append($opt);
		syncOptionsLayout($addonRow);
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
			$.post(
				pabAdmin.ajaxUrl,
				{
					action: 'pab_get_variations',
					product_id: e.params.data.id,
					nonce: pabAdmin.nonce,
				},
				function (response) {
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
			);
		});
	}

	function addChildRow() {
		var $row = cloneTemplate('pab-tmpl-child-row', { '__PAB_CHILD_INDEX__': childIndex });
		$row.attr('data-index', childIndex);
		$row.find('input,select,textarea').prop('disabled', false);
		$('#pab-child-products-list').append($row);
		initChildProductSearch($row);
		childIndex++;
	}

	function addonFieldModels() {
		var models = [];
		$('#pab-addon-fields-list .pab-addon-row').each(function (i) {
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
		initRuleRow($row);
		ruleIndex++;
	}

	function initSearchFilter() {
		if ($.fn.selectWoo) {
			$('#pab-addon-new-field-type').filter(':not(.enhanced)').trigger('wc-enhanced-select-init');
		}
	}

	function initSortable() {
		if (!$.fn.sortable) {
			return;
		}
		$('#pab-addon-fields-list').sortable({
			items: '.pab-addon-row',
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

	$(document).on('click', '.pab-add-option', function () {
		addOptionRow($(this).closest('.pab-addon-row'));
	});
	$(document).on('click', '.pab-add-addon-field', addAddonRow);
	$(document).on('click', '.pab-add-child-product', addChildRow);
	$(document).on('click', '.pab-add-rule', addRuleRow);

	$(document).on('click', '.pab-remove-row', function (e) {
		e.preventDefault();
		$(this).closest('.pab-settings-card').remove();
		reindexAddonRows();
		reindexChildRows();
		reindexRuleRows();
		updateRuleFieldDropdowns();
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
		$(this).closest('.pab-option-line').remove();
		reindexAddonRows();
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

	var mediaFrame;
	$(document).on('click', '.pab-select-image', function (e) {
		e.preventDefault();
		var $row = $(this).closest('.pab-option-line');
		var $hidden = $row.find('.pab-option-image-url');
		var $preview = $row.find('.pab-option-preview');
		if (mediaFrame) {
			mediaFrame.open();
			return;
		}
		mediaFrame = wp.media({ title: 'Select Swatch Image', button: { text: 'Use this image' }, multiple: false });
		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			$hidden.val(attachment.url);
			$preview.attr('src', attachment.url).removeClass('is-empty').show();
			mediaFrame = null;
		});
		mediaFrame.open();
	});

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
		if (!window.confirm((pabAdmin.i18n && pabAdmin.i18n.removeAssignmentConfirm) || 'Remove this assignment row?')) {
			return;
		}
		$(this).closest('.pab-assignment-row').remove();
		if (!$('#pab-assignments-rows .pab-assignment-row').length) {
			addAssignmentRow();
		}
		reindexAssignmentRows();
	});

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
		// WooCommerce reads $(el).data('taxonomy') in the AJAX callback; jQuery caches
		// data-* and does not refresh when only .attr('data-taxonomy') changes — clear cache.
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

	function buildLocationRuleRowHtml(index) {
		var taxes = pabAdmin.groupLocationTaxonomies || {};
		var defaultTax = pabAdmin.defaultLocationTaxonomy || 'product_cat';
		var i18n = pabAdmin.i18n || {};
		var opEq = i18n.opEqual || 'is equal to';
		var opNe = i18n.opNotEqual || 'is not equal to';
		var ph = i18n.searchTerms || 'Search for a term…';
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
				'" data-minimum_input_length="2" data-return_id="true" style="width:100%;min-width:200px;"></select></div>' +
				'<div class="pab-location-rule-col pab-location-rule-col-actions">' +
				'<button type="button" class="button-link pab-remove-location-rule" aria-label="' +
				pabEscapeHtml(i18n.removeLocationRule || 'Remove rule') +
				'">&times;</button></div></div>'
		);
	}

	function addLocationRuleRow() {
		var idx = $('#pab-group-location-rules-rows .pab-location-rule-row').length;
		var $row = $(buildLocationRuleRowHtml(idx));
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
			});
		// Run after current stack so WooCommerce's wc-enhanced-select-init handler is ready.
		window.setTimeout(function () {
			$(document.body).trigger('wc-enhanced-select-init');
		}, 0);
	}

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
			return;
		}

		initIndexes();
		initSearchFilter();

		$('#pab-addon-fields-list .pab-addon-row').each(function () {
			initAddonRow($(this));
		});
		$('#pab-child-products-list .pab-child-row').each(function () {
			initChildProductSearch($(this));
			hydrateVariationList($(this));
		});
		$('#pab-rules-list .pab-rule-row').each(function () {
			initRuleRow($(this));
		});
		initSortable();
		updateRuleFieldDropdowns();

		$('#post').on('submit', function () {
			reindexAddonRows();
			reindexChildRows();
			reindexRuleRows();
			reindexLocationRuleRows();
		});

		if ($('#pab-assignments-table').length) {
			$('#pab-assignments-rows .pab-assignment-row').each(function () {
				initAssignmentRow($(this));
			});
			reindexAssignmentRows();
		}
	});
})(jQuery);
