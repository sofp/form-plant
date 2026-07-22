/**
 * Form Plant - Admin JavaScript
 *
 * @package Form_Plant
 */

(function($) {
	'use strict';

	// Hold field data
	let formFields = [];

	// Original form-level settings, kept verbatim so unknown (Pro/future) settings
	// keys survive a save (GAP-3). Populated from fplantAdminData.formData.settings.
	let originalSettings = {};

	// Names of fields that were already saved to the DB (from the initial load). Fields
	// NOT in this set are new this session, so their field-name input stays editable
	// (renaming a saved field would break stored entries / mail tags). Rebuilt on the
	// page reload that follows a save.
	let originalFieldNames = new Set();

	// Every field key the editor UI owns, grouped to mirror the type-specific blocks in
	// commitEditor(). On save we strip these from the cloned original field, then re-apply
	// from the UI — so a cleared core value does not linger (and an old type's keys are
	// dropped when the type changes), while any key NOT listed here (Pro keys, future
	// keys) is preserved (GAP-3).
	//
	// INVARIANT: every key that commitEditor() writes onto `field` MUST appear here. When
	// you add a key to a type block in commitEditor(), add it to the matching group below.
	// (A key written but missing here would linger with a stale value after a type switch;
	// a key listed here but treated as a Pro key elsewhere would be wiped on every save.)
	const CORE_FIELD_KEYS = [
		// Always-present core (every type)
		'type', 'name', 'label', 'placeholder', 'required', 'validation_message',
		'custom_id', 'custom_class', 'validation', 'default',
		'desc_after_label', 'desc_before_input', 'desc_after_input',
		// select / radio / checkbox
		'options', 'layout', 'delimiter',
		// date / date_select
		'year_start', 'year_end',
		// file
		'max_size',
		// text / email / url / password (size, maxlength) + textarea (rows, cols)
		'size', 'maxlength', 'rows', 'cols',
		// html
		'content',
		// custom_mail_tag
		'display_in_form', 'display_wrapper',
		// name_parts / name_kana
		'name_format', 'name_labels', 'name_placeholders', 'name_validation_messages',
		'kana_validation', 'kana_error_message',
		// password
		'password_min_length', 'password_mask_email', 'password_mask_save',
		'password_strength_meter', 'password_strength_level',
		// tel
		'tel_format',
		// postal_code (postal_target_* shared with address)
		'postal_format', 'postal_show_search_btn', 'postal_autofill',
		'postal_target_pref', 'postal_target_addr1', 'postal_target_addr2',
		// prefecture / address
		'pref_display_type',
		'address_labels', 'address_placeholders', 'address_validation_messages',
		// acceptance
		'acceptance_text', 'acceptance_show_label', 'acceptance_show_confirmation',
		'acceptance_show_email', 'acceptance_save_submission'
	];

	// Pro field-editor tab registry (extension socket §5-A). Pro registers additional
	// tabs/sections on the field editor; the accordion renders them for matching field
	// types and their read() merges values onto the field. Because those keys are NOT in
	// CORE_FIELD_KEYS, they ride the GAP-3 merge path and are preserved on save.
	window.fplant = window.fplant || {};
	window.fplant.fields = window.fplant.fields || {};
	window.fplant.fields._tabs = window.fplant.fields._tabs || [];
	window.fplant.fields.version = '1.0';
	// registerTab({ id, label, types:['*']|['select',...], render($panel, field), read($panel, field), priority })
	window.fplant.fields.registerTab = function(config) {
		if (!config || !config.id || typeof config.render !== 'function') {
			return;
		}
		this._tabs = this._tabs.filter(function(t) { return t.id !== config.id; }); // last registration wins
		this._tabs.push(config);
		this._tabs.sort(function(a, b) { return (a.priority || 100) - (b.priority || 100); });
	};
	window.fplant.fields.getTabsForType = function(type) {
		return (this._tabs || []).filter(function(t) {
			return !t.types || t.types.indexOf('*') !== -1 || t.types.indexOf(type) !== -1;
		});
	};

	/**
	 * Get the dashicon class for a field type from the single source of truth
	 * (fplantAdminData.fieldTypes, generated from FPLANT_Field_Manager::get_field_types()).
	 * Falls back to a generic icon for unknown / Pro types without an icon.
	 */
	function getFieldTypeIcon(type) {
		const types = (typeof fplantAdminData !== 'undefined' && fplantAdminData.fieldTypes) || {};
		return (types[type] && types[type].icon) ? types[type].icon : 'dashicons-forms';
	}

	/**
	 * Get the human-readable label for a field type from the single source of truth.
	 * Falls back to the raw type slug for unknown types.
	 */
	function getFieldTypeLabel(type) {
		const types = (typeof fplantAdminData !== 'undefined' && fplantAdminData.fieldTypes) || {};
		return (types[type] && types[type].label) ? types[type].label : type;
	}

	/**
	 * Display WordPress-style admin notice
	 */
	function showAdminNotice(message, type) {
		type = type || 'success';

		// Remove existing notices
		$('.fplant-admin-notice').remove();

		// Create WordPress-style notice
		var notice = $('<div class="notice notice-' + type + ' is-dismissible fplant-admin-notice">' +
			'<p>' + message + '</p>' +
			'<button type="button" class="notice-dismiss"><span class="screen-reader-text">' + fplantAdminData.i18n.dismissNotice + '</span></button>' +
			'</div>');

		// Insert after .wp-header-end
		$('.wp-header-end').first().after(notice);

		// Dismiss button handler
		notice.find('.notice-dismiss').on('click', function() {
			notice.fadeOut(300, function() { $(this).remove(); });
		});

		// Scroll to page top
		$('html, body').animate({ scrollTop: 0 }, 300);
	}

	/**
	 * Tab switching
	 */
	function initTabs() {
		$('.fplant-tab').on('click', function() {
			const tabId = $(this).data('tab');

			// Switch tabs
			$(this).addClass('active').siblings().removeClass('active');

			// Switch content
			$('.fplant-tab-content').removeClass('active');
			$('#' + tabId).addClass('active');
		});

		// Restore active tab from URL parameter
		var params = new URLSearchParams(window.location.search);
		var activeTab = params.get('active_tab');
		if (activeTab && /^[a-zA-Z0-9_-]+$/.test(activeTab)) {
			var $tab = $('.fplant-tab[data-tab="' + activeTab + '"]');
			if ($tab.length) {
				$tab.trigger('click');
			}
		}
	}

	/**
	 * Add field
	 */
	function initFieldAdd() {
		// Open the field-type picker; the actual field is created when a type is chosen.
		// Commit/park any open row first (synchronously, so the picker's later
		// renderFieldList cannot destroy the in-row editor). If that row is invalid,
		// keep it open and do NOT open the picker.
		$('.fplant-add-field').on('click', function(e) {
			e.preventDefault();
			if (!commitOpenAccordion()) {
				return;
			}
			$('#fplant-field-type-picker-modal').addClass('active');
		});
	}

	// Field-type picker: clicking an icon creates a provisional field of that type and
	// opens it expanded for editing (committing/collapsing any currently open row first).
	function initFieldTypePicker() {
		$(document).on('click', '.fplant-type-picker-option', function(e) {
			e.preventDefault();
			const type = $(this).data('type');
			if (!type) {
				return;
			}
			$('#fplant-field-type-picker-modal').removeClass('active');
			// Any previously open row was already committed/parked when the picker
			// opened (initFieldAdd), so just create the field and open it.
			formFields.push({ type: type, name: '', label: '', required: false, validation: {} });
			renderFieldList();
			openFieldAccordion(formFields.length - 1, true);
		});
	}

	/**
	 * Display error in field modal
	 */
	function showFieldModalError(message) {
		const $err = $('#fplant-field-modal-errors').text(message).show();
		// Bring the error (top of the open editor) into view.
		if ($err.length && $err[0].scrollIntoView) {
			$err[0].scrollIntoView({ block: 'nearest' });
		}
	}

	/**
	 * Clear field modal errors
	 */
	function clearFieldModalErrors() {
		$('#fplant-field-modal-errors').hide().empty();
	}

	/**
	 * Open field modal
	 */
	let currentEditingIndex = null;

	// The accordion body currently sliding up (collapse animation in flight), or null.
	// Used to force-complete a pending collapse before the next action so rapid clicks
	// within the ~160ms animation window cannot re-enter the collapse path.
	let collapseInFlight = null;

	// While a drag reorders fields, the index of the row that was open when the drag
	// started (or null). If the drag ends without reordering (cancel / drop-in-place),
	// that row is re-opened so the drag does not silently collapse the editor.
	let sortReopenIndex = null;

	function openFieldModal(index, isNew) {
		// Clear error display
		clearFieldModalErrors();
		currentEditingIndex = index;
		const field = (index !== null && index !== undefined) ? formFields[index] : null;

		// Set field data. The name input is locked for existing fields (renaming a
		// saved field would break stored entries / mail tags); editable while new.
		$('#fplant-field-type').val(field ? field.type : 'text');
		$('#fplant-field-name').val(field ? field.name : '').prop('disabled', !isNew);
		$('#fplant-field-label').val(field ? field.label : '');
		$('#fplant-field-placeholder').val(field ? field.placeholder : '');
		$('#fplant-field-placeholder-textarea').val(field ? field.placeholder : '');
		$('#fplant-field-required').prop('checked', field ? field.required : false);
		$('#fplant-field-validation-message').val(field && field.validation_message ? field.validation_message : '');
		$('#fplant-field-custom-id').val(field && field.custom_id ? field.custom_id : '');
		$('#fplant-field-custom-class').val(field && field.custom_class ? field.custom_class : '');
		$('#fplant-field-desc-after-label').val(field && field.desc_after_label ? field.desc_after_label : '');
		$('#fplant-field-desc-before-input').val(field && field.desc_before_input ? field.desc_before_input : '');
		$('#fplant-field-desc-after-input').val(field && field.desc_after_input ? field.desc_after_input : '');

		// Set date range
		$('#fplant-field-year-start').val(field && field.year_start ? field.year_start : '');
		$('#fplant-field-year-end').val(field && field.year_end ? field.year_end : '');

		// Set file size
		$('#fplant-field-max-size').val(field && field.max_size ? field.max_size : '');

		// Set input field settings (size, maxlength) - text / email / url / password
		$('#fplant-field-size').val(field && field.size ? field.size : '');
		$('#fplant-field-maxlength').val(field && field.type !== 'textarea' && field.maxlength ? field.maxlength : '');

		// Set textarea field settings (rows, cols)
		$('#fplant-field-rows').val(field && field.rows ? field.rows : '');
		$('#fplant-field-cols').val(field && field.cols ? field.cols : '');

		// Textarea max length now lives in the Validation tab as a validation rule
		// (validation.max_length). Fall back to the legacy top-level maxlength so
		// fields saved before this change still show their value.
		const fieldValidation = (field && field.validation) ? field.validation : {};
		const textareaMaxlength = fieldValidation.max_length || (field && field.type === 'textarea' && field.maxlength) || '';
		$('#fplant-field-textarea-maxlength').val(textareaMaxlength);
		$('#fplant-field-maxlength-message').val(fieldValidation.max_length_message || '');

		// Set default value (input + multi-line variant for textarea)
		$('#fplant-field-default-value').val(field && field.default ? field.default : '');
		$('#fplant-field-default-value-textarea').val(field && field.default ? field.default : '');

		// Set HTML content
		$('#fplant-field-html-content').val(field && field.content ? field.content : '');

		// Set custom mail tag settings
		$('#fplant-field-cmt-display-in-form').prop('checked', field && typeof field.display_in_form !== 'undefined' ? !!field.display_in_form : true);
		$('#fplant-field-cmt-display-wrapper').val(field && field.display_wrapper ? field.display_wrapper : 'span');

		// Set acceptance settings (every visibility/save toggle defaults to OFF)
		$('#fplant-field-acceptance-text').val(field && field.acceptance_text ? field.acceptance_text : '');
		$('#fplant-field-acceptance-show-label').prop('checked', field ? !!field.acceptance_show_label : false);
		$('#fplant-field-acceptance-show-confirmation').prop('checked', field ? !!field.acceptance_show_confirmation : false);
		$('#fplant-field-acceptance-show-email').prop('checked', field ? !!field.acceptance_show_email : false);
		$('#fplant-field-acceptance-save-submission').prop('checked', field ? !!field.acceptance_save_submission : false);

		// Set name parts settings
		$('#fplant-field-name-format').val(field && field.name_format ? field.name_format : '2');
		if (field && field.name_labels) {
			$('#fplant-field-name-label-family').val(field.name_labels.family || '');
			$('#fplant-field-name-label-given').val(field.name_labels.given || '');
			$('#fplant-field-name-label-middle').val(field.name_labels.middle || '');
		} else {
			$('#fplant-field-name-label-family').val('');
			$('#fplant-field-name-label-given').val('');
			$('#fplant-field-name-label-middle').val('');
		}
		if (field && field.name_placeholders) {
			$('#fplant-field-name-placeholder-family').val(field.name_placeholders.family || '');
			$('#fplant-field-name-placeholder-given').val(field.name_placeholders.given || '');
			$('#fplant-field-name-placeholder-middle').val(field.name_placeholders.middle || '');
		} else {
			$('#fplant-field-name-placeholder-family').val('');
			$('#fplant-field-name-placeholder-given').val('');
			$('#fplant-field-name-placeholder-middle').val('');
		}
		if (field && field.name_validation_messages) {
			$('#fplant-field-name-validation-family').val(field.name_validation_messages.family || '');
			$('#fplant-field-name-validation-given').val(field.name_validation_messages.given || '');
			$('#fplant-field-name-validation-middle').val(field.name_validation_messages.middle || '');
		} else {
			$('#fplant-field-name-validation-family').val('');
			$('#fplant-field-name-validation-given').val('');
			$('#fplant-field-name-validation-middle').val('');
		}
		updateNameFormatVisibility($('#fplant-field-name-format').val());

		// Set name kana settings
		$('#fplant-field-kana-format').val(field && field.name_format ? field.name_format : '2');
		if (field && field.name_labels) {
			$('#fplant-field-kana-label-family').val(field.name_labels.family || '');
			$('#fplant-field-kana-label-given').val(field.name_labels.given || '');
			$('#fplant-field-kana-label-middle').val(field.name_labels.middle || '');
		} else {
			$('#fplant-field-kana-label-family').val('');
			$('#fplant-field-kana-label-given').val('');
			$('#fplant-field-kana-label-middle').val('');
		}
		if (field && field.name_placeholders) {
			$('#fplant-field-kana-placeholder-family').val(field.name_placeholders.family || '');
			$('#fplant-field-kana-placeholder-given').val(field.name_placeholders.given || '');
			$('#fplant-field-kana-placeholder-middle').val(field.name_placeholders.middle || '');
		} else {
			$('#fplant-field-kana-placeholder-family').val('');
			$('#fplant-field-kana-placeholder-given').val('');
			$('#fplant-field-kana-placeholder-middle').val('');
		}
		if (field && field.name_validation_messages) {
			$('#fplant-field-kana-validation-family').val(field.name_validation_messages.family || '');
			$('#fplant-field-kana-validation-given').val(field.name_validation_messages.given || '');
			$('#fplant-field-kana-validation-middle').val(field.name_validation_messages.middle || '');
		} else {
			$('#fplant-field-kana-validation-family').val('');
			$('#fplant-field-kana-validation-given').val('');
			$('#fplant-field-kana-validation-middle').val('');
		}
		// Set kana validation
		var kanaValidation = field && field.kana_validation ? field.kana_validation : 'katakana';
		$('input[name="fplant-field-kana-validation"][value="' + kanaValidation + '"]').prop('checked', true);
		$('#fplant-field-kana-error-message').val(field && field.kana_error_message ? field.kana_error_message : '');
		updateNameKanaFormatVisibility($('#fplant-field-kana-format').val());
		updateKanaValidationVisibility(kanaValidation);

		// Set password settings
		$('#fplant-field-password-min-length').val(field && field.password_min_length ? field.password_min_length : '');
		$('#fplant-field-password-mask-email').prop('checked', field ? !!field.password_mask_email : false);
		$('#fplant-field-password-mask-save').prop('checked', field ? !!field.password_mask_save : false);
		$('#fplant-field-password-strength-meter').prop('checked', field ? !!field.password_strength_meter : false);
		var strengthLevel = field && field.password_strength_level ? field.password_strength_level : 'none';
		$('input[name="fplant-field-password-strength-level"][value="' + strengthLevel + '"]').prop('checked', true);
		if (field && field.password_strength_meter) {
			$('#fplant-field-password-strength-level-section').show();
		} else {
			$('#fplant-field-password-strength-level-section').hide();
		}

		// Set tel settings
		$('#fplant-field-tel-format').val(field && field.tel_format ? field.tel_format : 'single');

		// Set postal code settings
		$('#fplant-field-postal-format').val(field && field.postal_format ? field.postal_format : 'single');
		$('#fplant-field-postal-show-search-btn').prop('checked', field ? !!field.postal_show_search_btn : false);
		$('#fplant-field-postal-autofill').prop('checked', field ? !!field.postal_autofill : false);
		updatePostalAutofillVisibility();
		if (field && field.postal_autofill) {
			populatePostalTargetSelects(field);
		}

		// Set prefecture settings
		$('#fplant-field-pref-display-type').val(field && field.pref_display_type ? field.pref_display_type : 'select');
		var prefLayout = field && field.layout ? field.layout : 'vertical';
		$('input[name="fplant-field-pref-layout"][value="' + prefLayout + '"]').prop('checked', true);
		updatePrefLayoutVisibility();
		if (field && field.options && field.options.length > 0) {
			renderPrefectureOptionsList(field.options);
		} else {
			// Set default prefectures
			renderPrefectureOptionsList(fplantAdminData.defaultPrefectures || []);
		}

		// Set address settings
		$('#fplant-field-address-postal-format').val(field && field.postal_format ? field.postal_format : 'single');
		$('#fplant-field-address-show-search-btn').prop('checked', field ? !!field.postal_show_search_btn : false);
		$('#fplant-field-address-pref-type').val(field && field.pref_display_type ? field.pref_display_type : 'select');
		if (field && field.address_labels) {
			$('.fplant-address-label-row').each(function() {
				var subKey = $(this).data('sub-key');
				$(this).find('input[id$="-label-' + subKey + '"]').val(field.address_labels[subKey] || '');
				$(this).find('input[id$="-placeholder-' + subKey + '"]').val(
					field.address_placeholders ? field.address_placeholders[subKey] || '' : ''
				);
				// Validation messages now live on the Validation tab (global id).
				$('#fplant-field-address-validation-' + subKey).val(
					field.address_validation_messages ? field.address_validation_messages[subKey] || '' : ''
				);
			});
		} else {
			$('.fplant-address-label-row input, .fplant-address-validation-row input').val('');
		}

		// Show/hide options area
		updateOptionsVisibility($('#fplant-field-type').val());

		// Set options
		if (field && field.options) {
			renderOptionsList(field.options);
		} else {
			$('#fplant-field-options-list').empty();
		}

		// Set layout
		const layout = field && field.layout ? field.layout : 'vertical';
		$('input[name="fplant-field-layout"][value="' + layout + '"]').prop('checked', true);

		// Set delimiter
		$('#fplant-field-delimiter').val(field && field.delimiter ? field.delimiter : ', ');
	}

	/**
	 * Toggle visibility of input fields based on field type
	 */
	function updateOptionsVisibility(fieldType) {
		// Options section (for select/radio/checkbox)
		if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
			$('#fplant-field-options-section').show();
		} else {
			$('#fplant-field-options-section').hide();
		}

		// Layout settings visibility (for radio/checkbox only)
		if (fieldType === 'radio' || fieldType === 'checkbox') {
			$('#fplant-field-layout-section').show();
		} else {
			$('#fplant-field-layout-section').hide();
		}

		// Delimiter settings visibility (for checkbox only)
		if (fieldType === 'checkbox') {
			$('#fplant-field-delimiter-section').show();
		} else {
			$('#fplant-field-delimiter-section').hide();
		}

		// Date range settings visibility
		if (fieldType === 'date' || fieldType === 'date_select') {
			$('#fplant-field-date-range-section').show();
		} else {
			$('#fplant-field-date-range-section').hide();
		}

		// File upload settings visibility
		if (fieldType === 'file') {
			$('#fplant-field-file-section').show();
		} else {
			$('#fplant-field-file-section').hide();
		}

		// Input field settings visibility (size, maxlength) - text / email / url / password
		if (['text', 'email', 'url', 'password'].indexOf(fieldType) !== -1) {
			$('#fplant-field-text-settings-section').show();
		} else {
			$('#fplant-field-text-settings-section').hide();
		}

		// Textarea field settings visibility (rows, cols, maxlength)
		if (fieldType === 'textarea') {
			$('#fplant-field-textarea-settings-section').show();
		} else {
			$('#fplant-field-textarea-settings-section').hide();
		}

		// HTML content section (for html type)
		if (fieldType === 'html') {
			$('#fplant-field-html-section').show();
		} else {
			$('#fplant-field-html-section').hide();
		}

		// Name parts settings visibility
		if (fieldType === 'name_parts') {
			$('#fplant-field-name-parts-section').show();
		} else {
			$('#fplant-field-name-parts-section').hide();
		}

		// Name kana settings visibility
		if (fieldType === 'name_kana') {
			$('#fplant-field-name-kana-section').show();
		} else {
			$('#fplant-field-name-kana-section').hide();
		}

		// Password settings visibility
		if (fieldType === 'password') {
			$('#fplant-field-password-section').show();
		} else {
			$('#fplant-field-password-section').hide();
		}

		// Tel settings visibility
		if (fieldType === 'tel') {
			$('#fplant-field-tel-section').show();
		} else {
			$('#fplant-field-tel-section').hide();
		}

		// Postal code settings visibility
		if (fieldType === 'postal_code') {
			$('#fplant-field-postal-code-section').show();
		} else {
			$('#fplant-field-postal-code-section').hide();
		}

		// Prefecture settings visibility
		if (fieldType === 'prefecture') {
			$('#fplant-field-prefecture-section').show();
		} else {
			$('#fplant-field-prefecture-section').hide();
		}

		// Address composite settings visibility
		if (fieldType === 'address') {
			$('#fplant-field-address-section').show();
		} else {
			$('#fplant-field-address-section').hide();
		}

		// Validation-tab sections (relocated from the Basic tab) — shown per type.
		$('#fplant-field-maxlength-text-section').toggle(['text', 'email', 'url', 'password'].indexOf(fieldType) !== -1);
		$('#fplant-field-name-parts-validation-section').toggle(fieldType === 'name_parts');
		$('#fplant-field-name-kana-validation-section').toggle(fieldType === 'name_kana');
		$('#fplant-field-address-validation-section').toggle(fieldType === 'address');
		$('#fplant-field-password-validation-section').toggle(fieldType === 'password');

		// Default value setting (show for all types except file, html, name_parts, name_kana, password, address, postal_code, prefecture, acceptance)
		// acceptance: a pre-checked consent box would defeat explicit consent.
		const noDefaultTypes = ['file', 'html', 'name_parts', 'name_kana', 'password', 'address', 'postal_code', 'prefecture', 'acceptance'];
		if (!noDefaultTypes.includes(fieldType)) {
			$('#fplant-field-default-value-section').show();
		} else {
			$('#fplant-field-default-value-section').hide();
		}

		// Custom Mail Tag settings visibility
		if (fieldType === 'custom_mail_tag') {
			$('#fplant-field-custom-mail-tag-section').show();
		} else {
			$('#fplant-field-custom-mail-tag-section').hide();
		}

		// Hide unnecessary fields for hidden/html/custom_mail_tag types
		const isHiddenOrHtml = fieldType === 'hidden' || fieldType === 'html' || fieldType === 'custom_mail_tag';
		const $labelGroup = $('#fplant-field-label').closest('.fplant-form-group');
		const $requiredGroup = $('#fplant-field-required').closest('.fplant-checkbox');
		const $validationGroup = $('#fplant-field-validation-message').closest('.fplant-form-group');

		if (isHiddenOrHtml) {
			$labelGroup.hide();
			$requiredGroup.hide();
			$validationGroup.hide();
		} else {
			$labelGroup.show();
			$requiredGroup.show();
			$validationGroup.show();
		}

		// Acceptance: consent is always required — force the toggle ON and lock it.
		// Re-enable for every other type (commitEditor also forces required=true).
		const isAcceptance = fieldType === 'acceptance';
		if (isAcceptance) {
			$('#fplant-field-required').prop('checked', true).prop('disabled', true);
		} else {
			$('#fplant-field-required').prop('disabled', false);
		}
		$('#fplant-field-required-fixed-note').toggle(isAcceptance);
		// The acceptance default message differs from the generic one; reflect it
		// in the validation-message placeholder.
		$('#fplant-field-validation-message').attr('placeholder', isAcceptance
			? (fplantAdminData.i18n.acceptanceDefaultMessage || '')
			: (fplantAdminData.i18n.validationMessagePlaceholder || ''));
		// Acceptance settings section (consent text + display/save toggles).
		$('#fplant-field-acceptance-section').toggle(isAcceptance);
		// The label is the item name here (e.g. "Consent"); hint at that.
		$('#fplant-field-label').attr('placeholder', isAcceptance
			? (fplantAdminData.i18n.acceptanceLabelPlaceholder || '')
			: (fplantAdminData.i18n.labelPlaceholder || ''));

		// Placeholder is only for text input types and select (name_parts/address have own per-part placeholders)
		const hasPlaceholder = ['text', 'email', 'tel', 'url', 'number', 'textarea', 'select', 'password', 'postal_code'].includes(fieldType);
		const $placeholderGroup = $('#fplant-field-placeholder').closest('.fplant-form-group');
		if (hasPlaceholder) {
			$placeholderGroup.show();
		} else {
			$placeholderGroup.hide();
		}

		// Textarea uses multi-line inputs for Placeholder and Default Value; every
		// other type keeps the single-line <input>. Toggle which variant is shown.
		const isTextarea = fieldType === 'textarea';
		$('#fplant-field-placeholder').toggle(!isTextarea);
		$('#fplant-field-placeholder-textarea').toggle(isTextarea);
		$('#fplant-field-default-value').toggle(!isTextarea);
		$('#fplant-field-default-value-textarea').toggle(isTextarea);

		// Max Length (validation rule) is offered for the textarea type only.
		$('#fplant-field-maxlength-section').toggle(isTextarea);
	}

	/**
	 * Toggle visibility of name parts sublabel rows based on format
	 */
	function updateNameFormatVisibility(format) {
		// Toggle both the basic-tab sub-label rows and the validation-tab message
		// rows (the validation messages now live on the Validation tab).
		var $familyHeading = $('.fplant-name-sublabel-row[data-part="family"] .fplant-name-part-heading, .fplant-name-validation-row[data-part="family"] .fplant-name-part-heading');
		var rows = function(part) { return $('.fplant-name-sublabel-row[data-part="' + part + '"], .fplant-name-validation-row[data-part="' + part + '"]'); };

		if (format === '1') {
			rows('family').show();
			rows('given').hide();
			rows('middle').hide();
			$familyHeading.text($familyHeading.data('label-single'));
		} else if (format === '3') {
			rows('family').show();
			rows('given').show();
			rows('middle').show();
			$familyHeading.text($familyHeading.data('label-default'));
		} else {
			// Default: 2 parts
			rows('family').show();
			rows('given').show();
			rows('middle').hide();
			$familyHeading.text($familyHeading.data('label-default'));
		}
	}

	/**
	 * Toggle visibility of name kana sublabel rows based on format
	 */
	function updateNameKanaFormatVisibility(format) {
		// Toggle both the basic-tab sub-label rows and the validation-tab message rows.
		var $familyHeading = $('.fplant-kana-sublabel-row[data-part="family"] .fplant-kana-part-heading, .fplant-kana-validation-row[data-part="family"] .fplant-kana-part-heading');
		var rows = function(part) { return $('.fplant-kana-sublabel-row[data-part="' + part + '"], .fplant-kana-validation-row[data-part="' + part + '"]'); };

		if (format === '1') {
			rows('family').show();
			rows('given').hide();
			rows('middle').hide();
			$familyHeading.text($familyHeading.data('label-single'));
		} else if (format === '3') {
			rows('family').show();
			rows('given').show();
			rows('middle').show();
			$familyHeading.text($familyHeading.data('label-default'));
		} else {
			// Default: 2 parts
			rows('family').show();
			rows('given').show();
			rows('middle').hide();
			$familyHeading.text($familyHeading.data('label-default'));
		}
	}

	/**
	 * Toggle visibility of kana error message section based on validation type
	 */
	function updateKanaValidationVisibility(validation) {
		if (validation === 'none') {
			$('#fplant-field-kana-error-message-section').hide();
		} else {
			$('#fplant-field-kana-error-message-section').show();
		}
	}

	/**
	 * Render options list - convert options array to textarea text
	 */
	function renderOptionsList(options = []) {
		const $textarea = $('#fplant-field-options-textarea');

		if (options.length === 0) {
			$textarea.val('');
			return;
		}

		// Convert options array to text lines
		const lines = options.map(option => {
			// If value equals label, output value only
			if (option.value === option.label) {
				return option.value;
			}
			// Otherwise use value:label format
			return option.value + ':' + option.label;
		});

		$textarea.val(lines.join('\n'));
	}

	/**
	 * Parse options from textarea
	 * Format: one option per line
	 * - "value:label" -> { value: "value", label: "label" }
	 * - "text" (no colon) -> { value: "text", label: "text" }
	 */
	function parseOptionsFromTextarea() {
		const text = $('#fplant-field-options-textarea').val();
		const lines = text.split('\n');
		const options = [];

		lines.forEach(line => {
			const trimmedLine = line.trim();
			if (trimmedLine === '') {
				return; // Skip empty lines
			}

			// Find the first colon
			const colonIndex = trimmedLine.indexOf(':');

			if (colonIndex > 0) {
				// Has colon - split into value and label
				const value = trimmedLine.substring(0, colonIndex).trim();
				const label = trimmedLine.substring(colonIndex + 1).trim();
				options.push({
					value: value,
					label: label || value // If label is empty after colon, use value
				});
			} else {
				// No colon - use same text for both value and label
				options.push({
					value: trimmedLine,
					label: trimmedLine
				});
			}
		});

		return options;
	}

	/**
	 * Render prefecture options into the prefecture textarea
	 */
	function renderPrefectureOptionsList(options) {
		var $textarea = $('#fplant-field-pref-options-textarea');
		if (options.length === 0) {
			$textarea.val('');
			return;
		}
		var lines = options.map(function(option) {
			if (option.value === option.label) {
				return option.value;
			}
			return option.value + ':' + option.label;
		});
		$textarea.val(lines.join('\n'));
	}

	/**
	 * Parse prefecture options from textarea
	 */
	function parsePrefectureOptionsFromTextarea() {
		var text = $('#fplant-field-pref-options-textarea').val();
		var lines = text.split('\n');
		var options = [];
		lines.forEach(function(line) {
			var trimmed = line.trim();
			if (trimmed === '') return;
			var colonIndex = trimmed.indexOf(':');
			if (colonIndex > 0) {
				options.push({
					value: trimmed.substring(0, colonIndex).trim(),
					label: trimmed.substring(colonIndex + 1).trim() || trimmed.substring(0, colonIndex).trim()
				});
			} else {
				options.push({ value: trimmed, label: trimmed });
			}
		});
		return options;
	}

	/**
	 * HTML escape
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}


	/**
	 * Field type change handler
	 */
	function initFieldTypeChange() {
		$(document).on('change', '#fplant-field-type', function() {
			updateOptionsVisibility($(this).val());
		});

		$(document).on('change', '#fplant-field-name-format', function() {
			updateNameFormatVisibility($(this).val());
		});

		$(document).on('change', '#fplant-field-kana-format', function() {
			updateNameKanaFormatVisibility($(this).val());
		});

		$(document).on('change', 'input[name="fplant-field-kana-validation"]', function() {
			updateKanaValidationVisibility($(this).val());
		});

		$(document).on('change', '#fplant-field-password-strength-meter', function() {
			if ($(this).is(':checked')) {
				$('#fplant-field-password-strength-level-section').show();
			} else {
				$('#fplant-field-password-strength-level-section').hide();
			}
		});

		// Prefecture display type change — show/hide layout option
		$(document).on('change', '#fplant-field-pref-display-type', function() {
			updatePrefLayoutVisibility();
		});

		// Postal code autofill checkbox toggle
		$(document).on('change', '#fplant-field-postal-autofill', function() {
			updatePostalAutofillVisibility();
		});
	}

	/**
	 * Toggle postal code autofill targets visibility
	 */
	function updatePrefLayoutVisibility() {
		var type = $('#fplant-field-pref-display-type').val();
		if (type === 'radio' || type === 'checkbox') {
			$('#fplant-field-pref-layout-section').show();
		} else {
			$('#fplant-field-pref-layout-section').hide();
		}
	}

	function updatePostalAutofillVisibility() {
		if ($('#fplant-field-postal-autofill').is(':checked')) {
			$('#fplant-postal-autofill-targets').show();
			populatePostalTargetSelects();
		} else {
			$('#fplant-postal-autofill-targets').hide();
		}
	}

	/**
	 * Populate postal code autofill target field selects
	 */
	function populatePostalTargetSelects(currentField) {
		var currentFieldName = $('#fplant-field-name').val();
		var $selects = $('.fplant-postal-target-select');

		$selects.each(function() {
			var $select = $(this);
			var currentVal = $select.val();
			$select.find('option:not(:first)').remove();

			formFields.forEach(function(f) {
				if (f.name !== currentFieldName) {
					$select.append(
						$('<option>').val(f.name).text(f.label ? f.label + ' (' + f.name + ')' : f.name)
					);
				}
			});

			// Restore selected value
			if (currentVal) {
				$select.val(currentVal);
			}
		});

		// Set saved target values when editing
		if (currentField) {
			if (currentField.postal_target_pref) {
				$('#fplant-field-postal-target-pref').val(currentField.postal_target_pref);
			}
			if (currentField.postal_target_addr1) {
				$('#fplant-field-postal-target-addr1').val(currentField.postal_target_addr1);
			}
			if (currentField.postal_target_addr2) {
				$('#fplant-field-postal-target-addr2').val(currentField.postal_target_addr2);
			}
		}
	}

	/**
	 * Auto-generate field name from field label
	 */
	function initAutoGenerateFieldName() {
		$(document).on('input', '#fplant-field-label', function() {
			// Only auto-generate while the name input is editable (new field) and empty.
			if (!$('#fplant-field-name').prop('disabled') && !$('#fplant-field-name').val()) {
				let fieldName = $(this).val()
					.toLowerCase()
					.replace(/[^a-z0-9]/g, '_')
					.replace(/_+/g, '_')
					.replace(/^_|_$/g, '');
				$('#fplant-field-name').val(fieldName);
			}
		});
	}

	/**
	 * Save field
	 */
	// Read the editor inputs, validate, and write the merged field back into
	// formFields[currentEditingIndex] (memory only — the DB save happens on the
	// explicit Update button). Returns true on success, false if validation failed
	// (an error is shown and the caller should keep the editor open).
	function commitEditor() {
		if (currentEditingIndex === null) {
			return true;
		}

		const fieldType = $('#fplant-field-type').val();
		const fieldName = $('#fplant-field-name').val().trim();
		const fieldLabel = $('#fplant-field-label').val().trim();
		// Textarea reads the multi-line variant so newlines are preserved.
		const fieldPlaceholder = (fieldType === 'textarea'
			? $('#fplant-field-placeholder-textarea')
			: $('#fplant-field-placeholder')).val().trim();
		const fieldRequired = $('#fplant-field-required').is(':checked');
		const validationMessage = $('#fplant-field-validation-message').val().trim();
		const customId = $('#fplant-field-custom-id').val().trim();
		const customClass = $('#fplant-field-custom-class').val().trim();
		const descAfterLabel = $('#fplant-field-desc-after-label').val().trim();
		const descBeforeInput = $('#fplant-field-desc-before-input').val().trim();
		const descAfterInput = $('#fplant-field-desc-after-input').val().trim();

		// Validation
		if (!fieldName) {
			showFieldModalError(fplantAdminData.i18n.fieldNameRequired);
			return false;
		}

		// Field name format check (alphanumeric and underscores only)
		if (!/^[a-zA-Z0-9_]+$/.test(fieldName)) {
			showFieldModalError(fplantAdminData.i18n.fieldNameAlphanumeric);
			return false;
		}

		// Duplicate field name check (against every OTHER field)
		{
			const exists = formFields.some((f, i) => i !== currentEditingIndex && f.name === fieldName);
			if (exists) {
				showFieldModalError(fplantAdminData.i18n.fieldNameExists);
				return false;
			}
		}

		// Label is required for every type that shows a label field. hidden / html /
		// custom_mail_tag hide the label input (updateOptionsVisibility) and fall back
		// to the field name, so they are exempt.
		if (fieldType !== 'hidden' && fieldType !== 'html' && fieldType !== 'custom_mail_tag' && !fieldLabel) {
			showFieldModalError(fplantAdminData.i18n.fieldLabelRequired);
			return false;
		}

			// Create field object (GAP-3: merge over the original instead of rebuilding
			// from scratch, so keys the editor does not know about — Pro keys, future
			// keys — are preserved). Start from a deep clone of the existing field, strip
			// every core-managed key (so a cleared value does not linger from the clone),
			// then re-apply core values from the UI below. Keys NOT in CORE_FIELD_KEYS are
			// never touched here and therefore survive untouched.
			const field = (currentEditingIndex !== null && formFields[currentEditingIndex])
				? $.extend(true, {}, formFields[currentEditingIndex])
				: {};
			CORE_FIELD_KEYS.forEach(function(coreKey) { delete field[coreKey]; });

			field.type = fieldType;
			field.name = fieldName;
			field.label = fieldLabel;
			field.placeholder = fieldPlaceholder;
			field.required = fieldRequired;
			field.validation_message = validationMessage;
			field.custom_id = customId;
			field.custom_class = customClass;
			field.desc_after_label = descAfterLabel;
			field.desc_before_input = descBeforeInput;
			field.desc_after_input = descAfterInput;
			field.validation = {};

			// For date types that need range settings
			if (fieldType === 'date' || fieldType === 'date_select') {
				const yearStart = $('#fplant-field-year-start').val();
				const yearEnd = $('#fplant-field-year-end').val();
				if (yearStart) field.year_start = parseInt(yearStart);
				if (yearEnd) field.year_end = parseInt(yearEnd);
			}

			// For file upload types
			if (fieldType === 'file') {
				const maxSize = $('#fplant-field-max-size').val();
				if (maxSize) {
					field.max_size = parseFloat(maxSize);
				}
			}

			// For single-line input field types (size, maxlength) - text / email / url / password
			if (['text', 'email', 'url', 'password'].indexOf(fieldType) !== -1) {
				const size = $('#fplant-field-size').val();
				const maxlength = $('#fplant-field-maxlength').val();
				if (size) {
					field.size = parseInt(size);
				}
				if (maxlength) {
					field.maxlength = parseInt(maxlength);
				}
			}

			// For textarea field type (rows, cols). Max length is a validation rule
			// (validation.max_length), set in the Validation tab handling below.
			if (fieldType === 'textarea') {
				const rows = $('#fplant-field-rows').val();
				const cols = $('#fplant-field-cols').val();
				if (rows) {
					field.rows = parseInt(rows);
				}
				if (cols) {
					field.cols = parseInt(cols);
				}

				// Max length + its optional custom error message. Stored under
				// field.validation so the server enforces it (and lets the user
				// exceed it to actually trigger the message) instead of a hard
				// HTML maxlength cap.
				const textareaMaxlength = $('#fplant-field-textarea-maxlength').val();
				if (textareaMaxlength) {
					field.validation.max_length = parseInt(textareaMaxlength);
					const maxlengthMessage = $('#fplant-field-maxlength-message').val().trim();
					if (maxlengthMessage) {
						field.validation.max_length_message = maxlengthMessage;
					}
				}
			}

			// Default value setting (except file). Textarea reads its multi-line variant.
			if (fieldType !== 'file') {
				const defaultValue = (fieldType === 'textarea'
					? $('#fplant-field-default-value-textarea')
					: $('#fplant-field-default-value')).val().trim();
				if (defaultValue) {
					field.default = defaultValue;
				}
			}

			// For hidden type, use field name if label is empty
			if (fieldType === 'hidden' && !field.label) {
				field.label = field.name;
			}

			// For html type
			if (fieldType === 'html') {
				const htmlContent = $('#fplant-field-html-content').val();
				if (!htmlContent.trim()) {
					showFieldModalError(fplantAdminData.i18n.htmlContentRequired || 'HTML content is required');
					return false;
				}
				field.content = htmlContent;
				if (!field.label) {
					field.label = field.name;
				}
			}

			// For custom_mail_tag type
			if (fieldType === 'custom_mail_tag') {
				field.display_in_form = $('#fplant-field-cmt-display-in-form').is(':checked');
				field.display_wrapper = $('#fplant-field-cmt-display-wrapper').val() || 'span';
				if (!field.label) {
					field.label = field.name;
				}
			}

			// For acceptance type: consent is always required — force the flag so
			// the stored value never depends on the (locked) toggle state.
			if (fieldType === 'acceptance') {
				field.required = true;
				field.acceptance_text = $('#fplant-field-acceptance-text').val().trim();
				field.acceptance_show_label = $('#fplant-field-acceptance-show-label').is(':checked');
				field.acceptance_show_confirmation = $('#fplant-field-acceptance-show-confirmation').is(':checked');
				field.acceptance_show_email = $('#fplant-field-acceptance-show-email').is(':checked');
				field.acceptance_save_submission = $('#fplant-field-acceptance-save-submission').is(':checked');
			}

			// For name parts types
			if (fieldType === 'name_parts') {
				field.name_format = $('#fplant-field-name-format').val() || '2';
				field.name_labels = {
					family: $('#fplant-field-name-label-family').val().trim(),
					given: $('#fplant-field-name-label-given').val().trim(),
					middle: $('#fplant-field-name-label-middle').val().trim()
				};
				field.name_placeholders = {
					family: $('#fplant-field-name-placeholder-family').val().trim(),
					given: $('#fplant-field-name-placeholder-given').val().trim(),
					middle: $('#fplant-field-name-placeholder-middle').val().trim()
				};
				field.name_validation_messages = {
					family: $('#fplant-field-name-validation-family').val().trim(),
					given: $('#fplant-field-name-validation-given').val().trim(),
					middle: $('#fplant-field-name-validation-middle').val().trim()
				};
			}

			// For name kana types
			if (fieldType === 'name_kana') {
				field.name_format = $('#fplant-field-kana-format').val() || '2';
				field.name_labels = {
					family: $('#fplant-field-kana-label-family').val().trim(),
					given: $('#fplant-field-kana-label-given').val().trim(),
					middle: $('#fplant-field-kana-label-middle').val().trim()
				};
				field.name_placeholders = {
					family: $('#fplant-field-kana-placeholder-family').val().trim(),
					given: $('#fplant-field-kana-placeholder-given').val().trim(),
					middle: $('#fplant-field-kana-placeholder-middle').val().trim()
				};
				field.name_validation_messages = {
					family: $('#fplant-field-kana-validation-family').val().trim(),
					given: $('#fplant-field-kana-validation-given').val().trim(),
					middle: $('#fplant-field-kana-validation-middle').val().trim()
				};
				field.kana_validation = $('input[name="fplant-field-kana-validation"]:checked').val() || 'katakana';
				field.kana_error_message = $('#fplant-field-kana-error-message').val().trim();
			}

			// For password types
			if (fieldType === 'password') {
				const minLength = $('#fplant-field-password-min-length').val();
				if (minLength) {
					field.password_min_length = parseInt(minLength);
				}
				field.password_mask_email = $('#fplant-field-password-mask-email').is(':checked');
				field.password_mask_save = $('#fplant-field-password-mask-save').is(':checked');
				field.password_strength_meter = $('#fplant-field-password-strength-meter').is(':checked');
				field.password_strength_level = $('input[name="fplant-field-password-strength-level"]:checked').val() || 'none';
			}

			// For tel type
			if (fieldType === 'tel') {
				field.tel_format = $('#fplant-field-tel-format').val() || 'single';
			}

			// For postal code type
			if (fieldType === 'postal_code') {
				field.postal_format = $('#fplant-field-postal-format').val() || 'single';
				field.postal_show_search_btn = $('#fplant-field-postal-show-search-btn').is(':checked');
				field.postal_autofill = $('#fplant-field-postal-autofill').is(':checked');
				if (field.postal_autofill) {
					field.postal_target_pref = $('#fplant-field-postal-target-pref').val() || '';
					field.postal_target_addr1 = $('#fplant-field-postal-target-addr1').val() || '';
					field.postal_target_addr2 = $('#fplant-field-postal-target-addr2').val() || '';
				}
			}

			// For prefecture type
			if (fieldType === 'prefecture') {
				field.pref_display_type = $('#fplant-field-pref-display-type').val() || 'select';
				if (field.pref_display_type === 'radio' || field.pref_display_type === 'checkbox') {
					field.layout = $('input[name="fplant-field-pref-layout"]:checked').val() || 'vertical';
				}
				field.options = parsePrefectureOptionsFromTextarea();
			}

			// For address type
			if (fieldType === 'address') {
				field.postal_format = $('#fplant-field-address-postal-format').val() || 'single';
				field.postal_show_search_btn = $('#fplant-field-address-show-search-btn').is(':checked');
				field.pref_display_type = $('#fplant-field-address-pref-type').val() || 'select';
				field.address_labels = {};
				field.address_placeholders = {};
				field.address_validation_messages = {};
				$('.fplant-address-label-row').each(function() {
					var subKey = $(this).data('sub-key');
					field.address_labels[subKey] = $(this).find('input[id$="-label-' + subKey + '"]').val().trim();
					field.address_placeholders[subKey] = $(this).find('input[id$="-placeholder-' + subKey + '"]').val().trim();
					// Validation messages now live on the Validation tab (global id).
					field.address_validation_messages[subKey] = ($('#fplant-field-address-validation-' + subKey).val() || '').trim();
				});
			}

			// For types that need options
			if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
				const options = parseOptionsFromTextarea();

				if (options.length === 0) {
					showFieldModalError(fplantAdminData.i18n.addOneOption);
					return false;
				}

				field.options = options;
			}

			// For types that need layout (radio/checkbox)
			if (fieldType === 'radio' || fieldType === 'checkbox') {
				field.layout = $('input[name="fplant-field-layout"]:checked').val() || 'vertical';
			}

			// For checkbox, add delimiter
			if (fieldType === 'checkbox') {
				const delimiter = $('#fplant-field-delimiter').val();
				field.delimiter = delimiter !== '' ? delimiter : ', ';
			}

			// Let Pro-registered tabs merge their values onto the field (extension
		// socket §5-A). These keys are not in CORE_FIELD_KEYS, so they are preserved.
		readProTabs(field);

		// Write the merged field back to memory. The DB save is deferred to the
		// explicit Update button (saveFormToDatabase), matching the accordion
		// "edit in memory, save on Update" model.
		formFields[currentEditingIndex] = field;
		return true;
	}

	// Done button: commit the open editor and collapse its row.
	function initSaveField() {
		$(document).on('click', '#fplant-save-field', function(e) {
			e.preventDefault();
			commitAndCollapse();
		});
	}

	// Generate a field name not already used by another field.
	function uniqueFieldName(base) {
		base = (base || 'field').toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '') || 'field';
		let name = base;
		let n = 1;
		while (formFields.some(f => f.name === name)) {
			n++;
			name = base + '_' + n;
		}
		return name;
	}

	// Move the single editor into the given row, populate it and expand the row.
	// isNew=true keeps the field-name input editable (existing fields lock the name).
	function openFieldAccordion(index, isNew) {
		flushCollapse(); // ensure no collapse is mid-flight before moving the editor
		const $item = $('.fplant-field-item[data-field-index="' + index + '"]');
		const $body = $item.find('.fplant-field-accordion-body');
		$('#fplant-field-editor').appendTo($body);
		// Keep the name editable for fields not yet saved to the DB (new this session),
		// even when re-opened later via the accordion header (isNew=false).
		const nameEditable = !!isNew || !originalFieldNames.has(formFields[index].name);
		openFieldModal(index, nameEditable);
		renderProTabs(formFields[index]);
		setEditorTab('basic');
		$item.addClass('open').find('.fplant-field-accordion-header').attr('aria-expanded', 'true');
		$item.find('.fplant-field-toggle .dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
		// Smooth expand.
		$body.stop(true, true).prop('hidden', false).hide().slideDown(160);
	}

	// Commit the open editor to memory and smoothly collapse its row. Runs `callback`
	// after the collapse completes (or immediately if nothing is open). Returns false
	// without collapsing if validation failed (the row stays open).
	function commitAndCollapse(callback) {
		flushCollapse(); // settle any in-flight collapse before starting a new one
		if (currentEditingIndex === null) {
			if (callback) { callback(); }
			return true;
		}
		if (!commitEditor()) {
			return false;
		}
		animatedCollapse(currentEditingIndex, callback);
		return true;
	}

	// Slide the given row's body up, then park the editor and refresh the list.
	function animatedCollapse(index, callback) {
		const $item = $('.fplant-field-item[data-field-index="' + index + '"]');
		$item.find('.fplant-field-toggle .dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
		const $body = $item.find('.fplant-field-accordion-body');
		const finish = function() {
			collapseInFlight = null;
			closeAccordionDom();
			renderFieldList();
			if (callback) { callback(); }
		};
		if (!$body.length) {
			finish();
			return;
		}
		collapseInFlight = $body;
		$body.stop(true, true).slideUp(160, finish);
	}

	// If a collapse animation is mid-flight, finish it immediately (synchronously) so the
	// editor is parked and the list rebuilt before the next action runs. Entry points call
	// this first to avoid re-entrancy from rapid clicks within the animation window.
	function flushCollapse() {
		if (collapseInFlight) {
			collapseInFlight.stop(true, true); // jumps to end and fires `finish` synchronously
		}
	}

	// Park the editor back in its hidden host and collapse every row (no re-render).
	function closeAccordionDom() {
		// Drop any Pro-rendered tabs/panels so the parked editor is clean for next use.
		$('#fplant-field-editor').find('.fplant-field-tab[data-pro-tab], .fplant-field-tab-panel[data-pro-tab]').remove();
		$('#fplant-field-editor').appendTo('#fplant-field-editor-host');
		$('.fplant-field-accordion-body').prop('hidden', true);
		$('.fplant-field-item').removeClass('open');
		$('.fplant-field-accordion-header').attr('aria-expanded', 'false');
		$('.fplant-field-toggle .dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
		currentEditingIndex = null;
	}

	// Commit the open editor to memory, then collapse and refresh the list.
	// Returns false if validation failed (keep the row open).
	function commitOpenAccordion() {
		flushCollapse(); // settle any in-flight collapse first
		if (currentEditingIndex === null) {
			return true;
		}
		if (!commitEditor()) {
			return false;
		}
		closeAccordionDom();
		renderFieldList();
		return true;
	}

	// Switch the active field-editor tab (basic / validation / advanced).
	function setEditorTab(tab) {
		const $editor = $('#fplant-field-editor');
		$editor.find('.fplant-field-tab').removeClass('active').attr('aria-selected', 'false');
		$editor.find('.fplant-field-tab[data-ftab="' + tab + '"]').addClass('active').attr('aria-selected', 'true');
		$editor.find('.fplant-field-tab-panel').prop('hidden', true).removeClass('active');
		$editor.find('.fplant-field-tab-panel[data-ftab="' + tab + '"]').prop('hidden', false).addClass('active');
	}

	// Render Pro-registered tabs for the given field's type into the editor. Existing
	// Pro tabs/panels are cleared first so the editor (reused across rows) never carries
	// stale Pro UI from a previous field.
	function renderProTabs(field) {
		const $editor = $('#fplant-field-editor');
		$editor.find('.fplant-field-tab[data-pro-tab], .fplant-field-tab-panel[data-pro-tab]').remove();
		if (!window.fplant || !window.fplant.fields || typeof window.fplant.fields.getTabsForType !== 'function') {
			return;
		}
		const $tablist = $editor.find('.fplant-field-tabs');
		const $footer = $editor.find('.fplant-field-editor-footer');
		window.fplant.fields.getTabsForType(field.type).forEach(function(t) {
			const ftab = 'pro-' + t.id;
			$('<button type="button" class="fplant-field-tab" role="tab" aria-selected="false"></button>')
				.attr('data-ftab', ftab).attr('data-pro-tab', t.id).text(t.label || t.id)
				.appendTo($tablist);
			const $panel = $('<div class="fplant-field-tab-panel" role="tabpanel" hidden></div>')
				.attr('data-ftab', ftab).attr('data-pro-tab', t.id);
			$panel.insertBefore($footer);
			try {
				t.render($panel, field);
			} catch (err) {
				/* isolate Pro render errors so core editing keeps working */
			}
		});
	}

	// Let each rendered Pro tab merge its values onto the field before it is committed.
	function readProTabs(field) {
		$('#fplant-field-editor .fplant-field-tab-panel[data-pro-tab]').each(function() {
			const id = $(this).data('pro-tab');
			const cfg = (window.fplant.fields._tabs || []).filter(function(t) { return t.id === id; })[0];
			if (cfg && typeof cfg.read === 'function') {
				try {
					cfg.read($(this), field);
				} catch (err) {
					/* isolate Pro read errors so core save keeps working */
				}
			}
		});
	}

	// Field-editor tab interactions (click + left/right arrow keys).
	function initEditorTabs() {
		$(document).on('click', '#fplant-field-editor .fplant-field-tab', function() {
			setEditorTab($(this).data('ftab'));
		});
		$(document).on('keydown', '#fplant-field-editor .fplant-field-tab', function(e) {
			if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
				return;
			}
			e.preventDefault();
			const $tabs = $('#fplant-field-editor .fplant-field-tab');
			let i = $tabs.index(this);
			i = e.key === 'ArrowRight' ? (i + 1) % $tabs.length : (i - 1 + $tabs.length) % $tabs.length;
			const $next = $tabs.eq(i);
			setEditorTab($next.data('ftab'));
			$next.trigger('focus');
		});
	}

	/**
	 * Render field list
	 */
	function renderFieldList() {
		const $list = $('.fplant-field-list');
		$list.empty();

		// The bottom "+ Add Field" button is redundant for short lists (the top
		// one is already in view), so only show it once the list gets long.
		$('.fplant-add-field-bottom').toggle(formFields.length > 5);

		if (formFields.length === 0) {
			$list.html('<p class="fplant-no-fields">' + fplantAdminData.i18n.noFieldsYet + '</p>');
			return;
		}

		formFields.forEach((field, index) => {
			const labelText = escapeHtml(field.label || field.name || getFieldTypeLabel(field.type));
			const typeName = escapeHtml(getFieldTypeLabel(field.type));
			const nameText = escapeHtml(field.name || '');
			const bodyId = 'fplant-field-body-' + index;
			const dupLabel = escapeHtml(fplantAdminData.i18n.duplicate || 'Duplicate');
			const delLabel = escapeHtml(fplantAdminData.i18n.delete);
			// Red required mark so required fields are spottable at a glance.
			const requiredMark = field.required ? '<span class="fplant-required" aria-hidden="true">*</span>' : '';
			const $item = $(
				'<div class="fplant-field-item" data-field-index="' + index + '">' +
					'<div class="fplant-field-accordion-header" role="button" tabindex="0" aria-expanded="false" aria-controls="' + bodyId + '">' +
						'<span class="fplant-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>' +
						'<span class="fplant-field-type-icon dashicons ' + getFieldTypeIcon(field.type) + '" aria-hidden="true"></span>' +
						'<span class="fplant-field-item-title">' +
							'<span class="fplant-field-label-text">' + labelText + requiredMark + '</span>' +
							'<span class="fplant-field-type-name">' + typeName + '</span>' +
							'<span class="fplant-field-name-text">' + escapeHtml(fplantAdminData.i18n.fieldNameLabel) + ' ' + nameText + '</span>' +
						'</span>' +
						'<span class="fplant-field-item-actions">' +
							'<button type="button" class="button-link fplant-field-duplicate" aria-label="' + dupLabel + '" title="' + dupLabel + '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>' +
							'<button type="button" class="button-link fplant-field-delete" aria-label="' + delLabel + '" title="' + delLabel + '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' +
							'<span class="fplant-field-toggle" aria-hidden="true"><span class="dashicons dashicons-arrow-down"></span></span>' +
						'</span>' +
					'</div>' +
					'<div class="fplant-field-accordion-body" id="' + bodyId + '" hidden></div>' +
				'</div>'
			);

			$list.append($item);
		});

		// Initialize sorting
		initFieldSort();
	}

	// Expand/collapse a field row. Opening commits the previously open row first.
	function toggleFieldAccordion(index) {
		if (currentEditingIndex === index) {
			commitAndCollapse(); // clicking the open row again collapses it
			return;
		}
		// Collapse the currently open row (if any), then open the requested one.
		// If the open row is invalid, commitAndCollapse aborts and it stays open.
		commitAndCollapse(function() {
			openFieldAccordion(index, false);
		});
	}

	/**
	 * Accordion header: open/close the row (click + Enter/Space).
	 */
	function initFieldEdit() {
		$(document).on('click', '.fplant-field-accordion-header', function(e) {
			// Ignore clicks on the action buttons / drag handle.
			if ($(e.target).closest('.fplant-field-item-actions, .fplant-drag-handle').length) {
				return;
			}
			const index = $(this).closest('.fplant-field-item').data('field-index');
			toggleFieldAccordion(index);
		});
		$(document).on('keydown', '.fplant-field-accordion-header', function(e) {
			if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') {
				return;
			}
			e.preventDefault();
			const index = $(this).closest('.fplant-field-item').data('field-index');
			toggleFieldAccordion(index);
		});
	}

	/**
	 * Delete a field row.
	 */
	function initFieldDelete() {
		$(document).on('click', '.fplant-field-delete', function(e) {
			e.preventDefault();
			e.stopPropagation();

			if (!confirm(fplantAdminData.i18n.confirmDeleteField)) {
				return;
			}

			// Read the index before flushing — flushCollapse() rebuilds the list and
			// would detach the clicked element.
			const index = $(this).closest('.fplant-field-item').data('field-index');
			flushCollapse();
			// If a DIFFERENT row is open with unsaved edits, commit them first so they
			// are not lost (abort the delete if that row is invalid, so the user can fix
			// it). The row being deleted itself needs no commit.
			if (currentEditingIndex !== null) {
				if (currentEditingIndex !== index && !commitEditor()) {
					return;
				}
				closeAccordionDom();
			}
			formFields.splice(index, 1);
			renderFieldList();
		});
	}

	/**
	 * Duplicate a field row, preserving unknown (Pro/future) keys.
	 */
	function initFieldDuplicate() {
		$(document).on('click', '.fplant-field-duplicate', function(e) {
			e.preventDefault();
			e.stopPropagation();

			const index = $(this).closest('.fplant-field-item').data('field-index');
			// Commit/collapse the open row first, then insert the copy below the source.
			commitAndCollapse(function() {
				const copy = $.extend(true, {}, formFields[index]);
				copy.name = uniqueFieldName((copy.name || 'field') + '_copy');
				copy.custom_id = ''; // avoid duplicate DOM ids
				formFields.splice(index + 1, 0, copy);
				renderFieldList();
			});
		});
	}

	/**
	 * Delete form
	 */
	function initFormDelete() {
		$(document).on('click', '.fplant-delete-form', function(e) {
			e.preventDefault();

			if (!confirm(fplantAdminData.i18n.confirmDeleteForm)) {
				return;
			}

			const formId = $(this).data('form-id');
			const $row = $(this).closest('tr');

			$.ajax({
				url: fplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_delete_form',
					nonce: fplantAdminData.nonce,
					form_id: formId
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || fplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					alert(fplantAdminData.i18n.errorOccurred);
				}
			});
		});
	}

	/**
	 * Field order change
	 */
	function initFieldSort() {
		const $list = $('.fplant-field-list');

		// Skip if jQuery UI Sortable not available
		if (typeof $list.sortable !== 'function') {
			return;
		}

		$list.sortable({
			handle: '.fplant-drag-handle',
			placeholder: 'fplant-field-placeholder',
			start: function(e, ui) {
				ui.placeholder.height(ui.item.height());
				// If a row is open, commit its edits to memory (valid edits are kept) and
				// park the editor in its host so it is not carried around / destroyed by
				// the reorder. No re-render here — that would break the in-progress drag.
				// Remember the row so it can be re-opened if the drag is cancelled.
				if (currentEditingIndex !== null) {
					commitEditor();
					sortReopenIndex = currentEditingIndex;
					closeAccordionDom();
				} else {
					sortReopenIndex = null;
				}
			},
			update: function(e, ui) {
				// Order actually changed — indices shift, so do not re-open by old index.
				sortReopenIndex = null;
				const newOrder = [];
				$('.fplant-field-item').each(function() {
					const index = $(this).data('field-index');
					newOrder.push(formFields[index]);
				});
				formFields = newOrder;
				renderFieldList();
			},
			stop: function(e, ui) {
				// Drag ended without reordering (cancel / drop-in-place): restore the row
				// that was open before the drag, so cancelling does not lose the open state.
				if (sortReopenIndex !== null) {
					const idx = sortReopenIndex;
					sortReopenIndex = null;
					openFieldAccordion(idx, false);
				}
			}
		});
	}

	/**
	 * Duplicate form
	 */
	function initFormDuplicate() {
		$(document).on('click', '.fplant-duplicate-form', function(e) {
			e.preventDefault();

			const formId = $(this).data('form-id');

			$.ajax({
				url: fplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_duplicate_form',
					nonce: fplantAdminData.nonce,
					form_id: formId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || fplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					alert(fplantAdminData.i18n.errorOccurred);
				}
			});
		});
	}

	/**
	 * Modal
	 */
	function initModal() {
		// Open modal
		$(document).on('click', '[data-modal]', function(e) {
			e.preventDefault();
			const modalId = $(this).data('modal');
			$('#' + modalId).addClass('active');
		});

		// Close modal
		$(document).on('click', '.fplant-modal-close, .fplant-modal', function(e) {
			if (e.target === this) {
				var $modal = $(this).closest('.fplant-modal');
				// Confirm before closing field edit modal
				if ($modal.attr('id') === 'fplant-field-modal') {
					if (!confirm(fplantAdminData.i18n.confirmCloseModal)) {
						return;
					}
				}
				$modal.removeClass('active');
			}
		});

		// Close modal with ESC key
		$(document).on('keyup', function(e) {
			if (e.key === 'Escape') {
				var $modal = $('.fplant-modal.active');
				if ($modal.length && $modal.attr('id') === 'fplant-field-modal') {
					if (!confirm(fplantAdminData.i18n.confirmCloseModal)) {
						return;
					}
				}
				$modal.removeClass('active');
			}
		});
	}

	/**
	 * Copy button
	 */
	function initCopyButton() {
		$(document).on('click', '.fplant-copy-button', function(e) {
			e.preventDefault();

			const text = $(this).data('copy');
			const $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			document.execCommand('copy');
			$temp.remove();

			// Feedback
			const originalText = $(this).text();
			$(this).text(fplantAdminData.i18n.copied);
			setTimeout(() => {
				$(this).text(originalText);
			}, 2000);
		});
	}

	/**
	 * Convert URL string to array
	 * @param {string} text Newline-separated URL string
	 * @return {array} Array of URLs
	 */
	function parseUrls(text) {
		if (!text) return [];
		return text.split('\n')
			.map(url => url.trim())
			.filter(url => url.length > 0);
	}

	/**
	 * Initialize the form preview modal. Opens an iframe pointing at the theme-context
	 * preview route (/fplant-preview/{id}/?_fplant_preview={nonce}), so the admin sees
	 * the saved form rendered with the active theme + design preset + custom CSS.
	 */
	/**
	 * Apply the preview viewport: the iframe keeps a fixed CSS viewport width
	 * (the real device width, or the stage width at 100% for desktop) so media
	 * queries fire consistently, and zoom scales the whole frame — form and the
	 * surrounding page chrome together. The frame carries the scaled width, so
	 * the stage scrolls horizontally once a zoomed-in preview outgrows it.
	 */
	function applyPreviewViewport() {
		const $stage = $('#fplant-preview-modal .fplant-preview-stage');
		if (!$stage.length || !$('#fplant-preview-modal').hasClass('active')) {
			return;
		}
		const zoom = parseFloat($('.fplant-preview-zoom').val() || '1') || 1;
		const deviceWidth = parseInt($('.fplant-preview-device.active').data('width'), 10) || 0; // 0 = desktop

		const stageStyle = window.getComputedStyle($stage[0]);
		const padX = parseFloat(stageStyle.paddingLeft) + parseFloat(stageStyle.paddingRight);
		const padY = parseFloat(stageStyle.paddingTop) + parseFloat(stageStyle.paddingBottom);
		const stageW = $stage[0].clientWidth - padX;
		const stageH = $stage[0].clientHeight - padY;

		// Base (100%) viewport: the real device width, or the stage width for
		// desktop. Zoom magnifies the whole frame from this fixed base so the
		// form and its surroundings grow together instead of the form reflowing.
		const baseW   = deviceWidth || Math.round(stageW);
		const frameW  = Math.round(baseW * zoom);
		const iframeW = baseW;

		$('.fplant-preview-frame').css({
			width: frameW + 'px',
			height: stageH + 'px'
		});
		$('.fplant-preview-iframe').css({
			width: iframeW + 'px',
			height: Math.round(stageH / zoom) + 'px',
			transform: 'scale(' + zoom + ')'
		});
	}

	function initFormPreview() {
		$(document).on('click', '.fplant-preview-form', function(e) {
			e.preventDefault();

			const formId = $(this).data('form-id');
			const base = fplantAdminData.previewUrlBase || '';
			const nonce = fplantAdminData.previewNonce || '';

			if (!formId || !base || !nonce) {
				return;
			}

			// Cache-buster so the iframe reflects the most recently saved version.
			const url = base + formId + '/?_fplant_preview=' + encodeURIComponent(nonce) + '&t=' + (new Date().getTime());
			$('#fplant-preview-modal').find('.fplant-preview-iframe').attr('src', url);
			$('#fplant-preview-modal').addClass('active');
			// Size the viewport once the modal is visible (clientWidth needs layout).
			window.setTimeout(applyPreviewViewport, 0);
		});

		// Clear the iframe when the preview modal closes (stop background loading/audio).
		$(document).on('click', '#fplant-preview-modal .fplant-modal-close', function() {
			$('#fplant-preview-modal').find('.fplant-preview-iframe').attr('src', 'about:blank');
		});

		// Device switch (PC / tablet / mobile)
		$(document).on('click', '.fplant-preview-device', function() {
			$('.fplant-preview-device').removeClass('active');
			$(this).addClass('active');
			applyPreviewViewport();
		});

		// Zoom select
		$(document).on('change', '.fplant-preview-zoom', applyPreviewViewport);

		// Keep the viewport sized while the modal is open
		$(window).on('resize', applyPreviewViewport);
	}

	/* ==========================================================================
	   Design adjustments (Layout tab)
	   ========================================================================== */

	// Design sections that are edited together inside one accordion frame but
	// stored/generated as separate schema sections. The key is a DOM-only group
	// id (never a schema section, never persisted); the value lists the real
	// sections it bundles, in display order.
	const DESIGN_GROUPS = { confirm_buttons: ['back', 'confirm'] };

	function designGroupMembers(section) {
		return DESIGN_GROUPS[section] || null;
	}

	/**
	 * Read one section's design adjustment values from the DOM. Keys come from
	 * the localized schema (FPLANT_Design_Options::get_schema()) so the collected
	 * object always matches what the PHP CSS generator understands. The two
	 * confirmation buttons share one accordion, so their controls live in a
	 * per-section sub-scope — match either an accordion or a sub-scope.
	 */
	function collectDesignSectionValues(section) {
		const schema = (fplantAdminData.designSchema || {})[section];
		const values = {};
		if (!schema) {
			return values;
		}
		const $scope = $('.fplant-design-accordion[data-design-section="' + section + '"], .fplant-design-subscope[data-design-section="' + section + '"]');
		Object.keys(schema.props).forEach(function(key) {
			const $input = $scope.find('.fplant-design-input[data-design-key="' + key + '"]');
			values[key] = $input.length ? String($input.val() || '').trim() : '';
		});
		return values;
	}

	/**
	 * Collect all sections into the settings.design_options shape
	 * ({ form: {...}, submit: {...}, ... }). Unset values (empty strings) are
	 * dropped — safe because saving replaces the whole design_options object.
	 */
	function collectDesignOptions() {
		const options = {};
		Object.keys(fplantAdminData.designSchema || {}).forEach(function(section) {
			const values = collectDesignSectionValues(section);
			const set = {};
			Object.keys(values).forEach(function(key) {
				if (values[key] !== '') {
					set[key] = values[key];
				}
			});
			if (Object.keys(set).length) {
				options[section] = set;
			}
		});
		return options;
	}

	/**
	 * Hex color validation / darkening — JS mirrors of
	 * FPLANT_Design_Options::sanitize_color() / darken(). Keep in sync.
	 */
	function designColor(value) {
		const v = String(value || '').trim();
		return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v) ? v.toLowerCase() : '';
	}

	function designDarken(hex, ratio) {
		let h = designColor(hex).replace('#', '');
		if (!h) {
			return '';
		}
		if (h.length === 3) {
			h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
		}
		let out = '#';
		[0, 2, 4].forEach(function(offset) {
			let c = Math.round(parseInt(h.substr(offset, 2), 16) * (1 - ratio));
			c = Math.max(0, Math.min(255, c));
			out += ('0' + c.toString(16)).slice(-2);
		});
		return out;
	}

	function designPx(value, min, max) {
		const v = String(value || '').trim();
		if (v === '' || !isFinite(Number(v))) {
			return null;
		}
		return Math.max(min, Math.min(max, Math.round(Number(v))));
	}

	// Legacy keyword values from the pre-slider shadow select, mapped onto the
	// 0-10 intensity scale by strength (sm=1 / md=3 / lg=8).
	const DESIGN_SHADOW_LEGACY = { none: '0', sm: '1', md: '3', lg: '8' };

	/**
	 * Box-shadow for a shadow intensity step (0 = none). Equal x/y offsets so
	 * the shadow extends evenly to the right and bottom. JS mirror of
	 * FPLANT_Design_Options::shadow_css() — keep in sync (positive half-way
	 * values round up in both).
	 */
	function designShadowCss(intensity) {
		if (intensity <= 0) {
			return 'none';
		}
		const offset = Math.round(0.75 * intensity);
		// Negative spread keeps the blur from spilling past the top/left edges.
		const spread = Math.round(0.5 * intensity);
		// Alpha built as the integer digits of 0.XX (12-25) — mirrors the PHP integer math.
		const alpha = Math.round(10 + 1.5 * intensity);
		return offset + 'px ' + offset + 'px ' + Math.round(2.5 * intensity) + 'px -' + spread + 'px rgba(0,0,0,0.' + alpha + ')';
	}

	/**
	 * Build the CSS for one design section. JS mirror of
	 * FPLANT_Design_Options::build_section_css() — keep in sync. The schema is
	 * localized from PHP, so only the assembly logic lives here.
	 */
	function buildSectionCss(prefix, section, values) {
		const schema = (fplantAdminData.designSchema || {})[section];
		if (!schema) {
			return '';
		}
		const decls = {};
		const border = {};
		const push = function(rule, decl) {
			(decls[rule] = decls[rule] || []).push(decl);
		};

		Object.keys(schema.props).forEach(function(key) {
			const def = schema.props[key];
			const raw = values[key] === undefined || values[key] === null ? '' : String(values[key]).trim();
			if (raw === '') {
				return;
			}
			let color;
			let number;
			switch (def.type) {
				case 'color':
					color = designColor(raw);
					if (color) {
						push(def.rule, def.css + ':' + color);
						// Secondary outputs (e.g. error text color → errored input border)
						if (def.also) {
							def.also.forEach(function(extra) {
								push(extra.rule, extra.css + ':' + color);
							});
						}
					}
					break;
				case 'px':
					number = designPx(raw, def.min, def.max);
					if (number !== null) {
						push(def.rule, def.css + ':' + number + 'px');
					}
					break;
				case 'px-pair':
					number = designPx(raw, def.min, def.max);
					if (number !== null) {
						def.css.forEach(function(prop) {
							push(def.rule, prop + ':' + number + 'px');
						});
					}
					break;
				case 'shadow':
					// Legacy keyword values (pre-slider selects) map onto the intensity scale.
					number = designPx(
						Object.prototype.hasOwnProperty.call(DESIGN_SHADOW_LEGACY, raw) ? DESIGN_SHADOW_LEGACY[raw] : raw,
						def.min,
						def.max
					);
					if (number !== null) {
						push(def.rule, def.css + ':' + designShadowCss(number));
					}
					break;
				case 'weight':
					if (raw === 'bold' || raw === 'normal') {
						push(def.rule, def.css + ':' + (raw === 'bold' ? '700' : '400'));
					}
					break;
				case 'btn-width':
					if (raw === 'auto' || raw === 'full') {
						push(def.rule, 'width:' + (raw === 'full' ? '100%' : 'auto'));
					}
					break;
				case 'border-width':
					number = designPx(raw, def.min, def.max);
					if (number !== null) {
						(border[def.rule] = border[def.rule] || {}).width = number;
					}
					break;
				case 'border-color':
					color = designColor(raw);
					if (color) {
						(border[def.rule] = border[def.rule] || {}).color = color;
					}
					break;
				case 'bg-padded':
					color = designColor(raw);
					if (color) {
						push(def.rule, def.css + ':' + color);
						push(def.rule, 'padding:' + def.pad);
						push(def.rule, 'border-radius:' + def.radius);
					}
					break;
			}
		});

		// Compose the border shorthand (width or color alone gets a default for the other half).
		Object.keys(border).forEach(function(rule) {
			const width = border[rule].width === undefined ? 1 : border[rule].width;
			const color = border[rule].color || '#dcdcde';
			push(rule, 'border:' + width + 'px solid ' + color);
		});

		// Hover fallback: derive a darker hover background so a custom background
		// does not freeze the button on hover (see PHP counterpart).
		if (schema.props.hover_background) {
			const baseBg = designColor(values.background);
			if (baseBg && !designColor(values.hover_background)) {
				push('hover', 'background:' + designDarken(baseBg, 0.12));
			}
		}

		let css = '';
		Object.keys(schema.rules).forEach(function(rule) {
			if (!decls[rule] || !decls[rule].length) {
				return;
			}
			const selectors = schema.rules[rule].map(function(suffix) {
				return prefix + suffix;
			});
			css += selectors.join(', ') + '{' + decls[rule].join(';') + '}';
		});
		return css;
	}

	/**
	 * Minimal sample markup per section, mirroring the real front-end class
	 * structure so the preset CSS inside the shadow root styles it like the
	 * real form. Generated CSS targets #fplant-sample-{section}.
	 */
	function designSampleHtml(section) {
		const i18n = fplantAdminData.i18n || {};
		const sampleLabel = escapeHtml(i18n.designSampleLabel || 'Sample Label');
		const sampleText = escapeHtml(i18n.designSampleText || 'Sample text');
		const open = '<div id="fplant-sample-' + section + '" class="fplant-form-wrapper">';

		// Shared multi-field markup (text + radio + textarea) so the form/input
		// previews mirror a realistic form rather than a single text field. The
		// class names match the real front-end templates so the preset CSS in the
		// shadow root styles them like the live form.
		const label1 = escapeHtml(i18n.designSampleLabel1 || 'Field 1');
		const label2 = escapeHtml(i18n.designSampleLabel2 || 'Field 2');
		const label3 = escapeHtml(i18n.designSampleLabel3 || 'Field 3');
		const sampleDesc = escapeHtml(i18n.designSampleDesc || 'This is a sample field description.');
		const choice1 = escapeHtml(i18n.designSampleChoice1 || 'Choice 1');
		const choice2 = escapeHtml(i18n.designSampleChoice2 || 'Choice 2');
		const choice3 = escapeHtml(i18n.designSampleChoice3 || 'Choice 3');
		const radioGroup =
			'<div class="fplant-field-group">' +
				'<label>' + label2 + '</label>' +
				'<div class="fplant-field fplant-field-radio fplant-layout-vertical">' +
					'<label class="fplant-radio-label"><input type="radio" disabled><span>' + choice1 + '</span></label>' +
					'<label class="fplant-radio-label"><input type="radio" disabled><span>' + choice2 + '</span></label>' +
					'<label class="fplant-radio-label"><input type="radio" disabled><span>' + choice3 + '</span></label>' +
				'</div>' +
			'</div>';
		const textareaGroup =
			'<div class="fplant-field-group">' +
				'<label>' + label3 + '</label>' +
				'<textarea class="fplant-field fplant-field-textarea" rows="3" readonly>' + sampleText + '</textarea>' +
			'</div>';

		switch (section) {
			case 'form':
				return '<div class="fplant-design-sample-scale">' + open +
					'<div class="fplant-form">' +
						'<div class="fplant-field-group">' +
							'<label>' + label1 + '</label>' +
							'<div class="fplant-field-desc">' + sampleDesc + '</div>' +
							'<input type="text" class="fplant-field fplant-field-text" value="' + sampleText + '" readonly>' +
						'</div>' +
						radioGroup +
						textareaGroup +
					'</div>' +
				'</div></div>';
			case 'input': {
				// Only the input boxes themselves — no frame, labels, radio or
				// textarea — at full size so the text field styling reads clearly.
				// A normal field (placeholder visible) plus an errored field (error
				// border) cover every input option, each prefixed with a caption
				// explaining the state it demonstrates. The field groups are forced
				// to block so the input fills the width regardless of the preset's
				// label/field grid. Inputs stay focusable (readonly, not disabled)
				// so the focus border color shows on click.
				const caption = function(text) {
					return '<div style="font-size:12px;color:#646970;margin:0 0 4px;font-weight:600;">' + escapeHtml(text) + '</div>';
				};
				return open +
					'<div class="fplant-form">' +
						'<div class="fplant-field-group" style="display:block;margin:0 0 16px;">' +
							caption(i18n.designSampleStateNormal || 'Empty / no error state') +
							'<input type="text" class="fplant-field fplant-field-text" placeholder="' + sampleText + '" readonly>' +
						'</div>' +
						'<div class="fplant-field-group fplant-field-has-error" style="display:block;margin:0;">' +
							caption(i18n.designSampleStateError || 'Entered / error state') +
							'<input type="text" class="fplant-field fplant-field-text" value="' + sampleText + '" readonly>' +
						'</div>' +
					'</div>' +
				'</div>';
			}
			case 'submit':
				return open +
					'<div class="fplant-form">' +
						'<div class="fplant-submit-wrapper">' +
							'<button type="button" class="fplant-submit-button"></button>' +
						'</div>' +
					'</div>' +
				'</div>';
			case 'confirm_buttons':
				// The real confirmation footer holds both buttons side by side
				// (templates/confirmation.php), so render both in the merged
				// preview. Each section's CSS is scoped to this wrapper id
				// (#fplant-sample-confirm_buttons) in updateDesignPreview().
				return open +
					'<div class="fplant-confirmation">' +
						'<div class="fplant-confirmation-footer">' +
							'<button type="button" class="fplant-back-button"></button>' +
							'<button type="button" class="fplant-confirm-submit-button"></button>' +
						'</div>' +
					'</div>' +
				'</div>';
			case 'error':
				return open +
					'<div class="fplant-form">' +
						'<div class="fplant-messages">' +
							'<div class="fplant-errors" style="display: block;"><ul><li>' + escapeHtml(i18n.designSampleError || 'This is a sample error message.') + '</li></ul></div>' +
						'</div>' +
						'<div class="fplant-field-group fplant-field-has-error">' +
							'<input type="text" class="fplant-field fplant-field-text" value="' + sampleText + '" readonly>' +
							'<div class="fplant-field-error" style="display: block;">' + escapeHtml(i18n.designSampleFieldError || 'This field is required.') + '</div>' +
						'</div>' +
					'</div>' +
				'</div>';
		}
		return open + '</div>';
	}

	/**
	 * The preset CSS URL for the currently selected design type.
	 */
	function currentDesignCssUrl() {
		let type = $('input[name="design_type"]:checked').val() || 'simple1';
		if (type === 'default') {
			type = 'simple1';
		}
		return (fplantAdminData.designCssUrls || {})[type] || '';
	}

	/**
	 * Build one preview host: a shadow root isolates the front-end preset CSS
	 * from the admin styles (and vice versa).
	 */
	function initDesignPreviewHost(host, section) {
		const root = host.attachShadow({ mode: 'open' });

		const link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = currentDesignCssUrl();
		link.addEventListener('load', function() {
			// Re-measure the scaled sample once the preset CSS is applied.
			updateDesignPreview(section);
			// Preset defaults are read from this now-styled preview, so refresh
			// the default-color hints of the controls it backs (initial load and
			// every preset swap re-fire this).
			updateDesignDefaultHints($('.fplant-design-preview-host[data-design-section="' + section + '"]').closest('.fplant-design-accordion'));
		});

		const baseStyle = document.createElement('style');
		baseStyle.textContent = ':host{display:block;}' +
			'.fplant-design-sample-scale{width:200%;transform:scale(0.5);transform-origin:0 0;}';

		const genStyle = document.createElement('style');
		genStyle.setAttribute('data-gen', '');

		const content = document.createElement('div');
		content.innerHTML = designSampleHtml(section);

		root.appendChild(link);
		root.appendChild(baseStyle);
		root.appendChild(genStyle);
		root.appendChild(content);
	}

	/**
	 * Regenerate the preview CSS for one section from the current input values.
	 */
	function updateDesignPreview(section) {
		const host = document.querySelector('.fplant-design-preview-host[data-design-section="' + section + '"]');
		if (!host || !host.shadowRoot) {
			return;
		}
		// A group host (e.g. confirm_buttons) renders several real sections in
		// one preview; concatenate each member's CSS under the host's sample id.
		const members = designGroupMembers(section);
		const css = members
			? members.map(function(member) {
				return buildSectionCss('#fplant-sample-' + section, member, collectDesignSectionValues(member));
			}).join('')
			: buildSectionCss('#fplant-sample-' + section, section, collectDesignSectionValues(section));
		host.shadowRoot.querySelector('style[data-gen]').textContent = css;

		// The 'form' sample renders at 200% width scaled down to 50% so max-width
		// changes stay visible; clip the host to the visual (transformed) height.
		// Height is 0 while the accordion body is hidden — skip and measure again
		// when the section opens.
		if (section === 'form') {
			const scaleEl = host.shadowRoot.querySelector('.fplant-design-sample-scale');
			if (scaleEl) {
				const visualHeight = Math.ceil(scaleEl.getBoundingClientRect().height);
				if (visualHeight > 0) {
					// + allowance: the rect excludes the frame's box-shadow
					// (up to ~16px at intensity 10, halved by the 0.5 scale),
					// which would otherwise be clipped at the bottom.
					host.style.height = (visualHeight + 12) + 'px';
					host.style.overflow = 'hidden';
				}
			}
		}

	}

	function updateAllDesignPreviews() {
		// Iterate the actual preview hosts (not schema keys) so group hosts like
		// confirm_buttons are refreshed and the now-hostless back/confirm sections
		// are skipped.
		document.querySelectorAll('.fplant-design-preview-host').forEach(function(host) {
			updateDesignPreview(host.getAttribute('data-design-section'));
		});
	}

	// --- Default-color hint shown in each color control's label ------------
	// An empty color picker renders as a white swatch, which reads as "white
	// selected" rather than "using the preset default". Append the preset default
	// to the label as "Label (■ Default)", always visible so the picker never
	// shifts. Defaults track the selected preset (read from the part preview);
	// the two state-only colors an unfocused preview can't expose are pinned to
	// their cross-preset constants (all three presets share these values).
	const DESIGN_DEFAULT_COLOR_OVERRIDES = {
		input: { focus_border_color: '#2271b1', error_border_color: '#d63638' }
	};

	function designHostSectionFor(section) {
		for (const group in DESIGN_GROUPS) {
			if (DESIGN_GROUPS[group].indexOf(section) !== -1) {
				return group;
			}
		}
		return section;
	}

	function isTransparentColor(value) {
		if (!value) {
			return true;
		}
		const v = value.replace(/\s+/g, '').toLowerCase();
		return v === 'transparent' || /^rgba\(\d+,\d+,\d+,0(\.0+)?\)$/.test(v);
	}

	/**
	 * The preset default color for one color option, resolved from the live part
	 * preview (or a pinned constant for focus/error states). Returns '' when it
	 * can't be determined.
	 */
	function resolveDesignDefaultColor(section, key) {
		const override = (DESIGN_DEFAULT_COLOR_OVERRIDES[section] || {})[key];
		if (override) {
			return override;
		}
		const schema = (fplantAdminData.designSchema || {})[section];
		if (!schema || !schema.props || !schema.props[key]) {
			return '';
		}
		const def = schema.props[key];
		if (def.type !== 'color' && def.type !== 'bg-padded' && def.type !== 'border-color') {
			return '';
		}
		const host = document.querySelector('.fplant-design-preview-host[data-design-section="' + designHostSectionFor(section) + '"]');
		if (!host || !host.shadowRoot) {
			return '';
		}
		const rawSel = ((schema.rules[def.rule] || [])[0] || '');
		const isPlaceholder = rawSel.indexOf('::placeholder') !== -1;
		// Strip pseudo bits so the selector matches a real element to measure.
		let sel = rawSel.replace('::placeholder', '').replace(/:(focus|hover|active)/g, '').trim();
		if (sel === '') {
			sel = '.fplant-form'; // the root rule targets the wrapper itself
		}
		let el = null;
		try {
			el = host.shadowRoot.querySelector(sel);
		} catch (e) {
			el = null;
		}
		if (!el) {
			return '';
		}
		const cs = isPlaceholder ? window.getComputedStyle(el, '::placeholder') : window.getComputedStyle(el);
		// The 'border-color' type composes a border shorthand and carries no css
		// key, so read the resolved border color directly.
		if (def.type === 'border-color') {
			return cs.borderTopColor;
		}
		const prop = def.css || 'color';
		if (prop === 'border-color') {
			return cs.borderTopColor;
		}
		if (prop === 'background') {
			return cs.backgroundColor;
		}
		return cs.getPropertyValue(prop) || cs.color;
	}

	/**
	 * Build (once) and refresh the "(■ Default)" suffix inside a color control's
	 * label. Caller must have neutralized the preview's override <style> first so
	 * the read reflects the preset default rather than the field's own value.
	 */
	function updateDesignDefaultHint(input) {
		const $input = $(input);
		const $control = $input.closest('.fplant-design-control');
		const $label = $control.children('label').first();
		if (!$label.length) {
			return;
		}
		let $hint = $label.find('.fplant-design-default-inline');
		if (!$hint.length) {
			const label = (fplantAdminData.i18n && fplantAdminData.i18n.designDefaultLabel) || 'Default';
			$hint = $('<span class="fplant-design-default-inline">（<span class="fplant-design-default-swatch"></span>' +
				escapeHtml(label) +
				'）</span>');
			$label.append($hint);
		}
		const section = $control.closest('[data-design-section]').data('design-section');
		const color = resolveDesignDefaultColor(section, $input.data('design-key'));
		const $swatch = $hint.find('.fplant-design-default-swatch');
		if (!color || isTransparentColor(color)) {
			$swatch.css('background-color', '').addClass('is-none');
		} else {
			$swatch.css('background-color', color).removeClass('is-none');
		}
	}

	/**
	 * Refresh the default-color label suffixes within one or more accordions.
	 * Defaults are read from the part preview with its override <style> blanked
	 * out (restored synchronously, before any paint) so a field that already has
	 * a value still shows the preset default rather than its own color.
	 */
	function updateDesignDefaultHints($scope) {
		$scope = $scope || $(document);
		const $accordions = $scope.is('.fplant-design-accordion') ? $scope : $scope.find('.fplant-design-accordion');
		$accordions.each(function() {
			const $acc = $(this);
			const $colors = $acc.find('.fplant-design-color');
			if (!$colors.length) {
				return;
			}
			const host = $acc.find('.fplant-design-preview-host')[0];
			const gen = host && host.shadowRoot ? host.shadowRoot.querySelector('style[data-gen]') : null;
			const saved = gen ? gen.textContent : null;
			if (gen) {
				gen.textContent = ''; // drop overrides so reads return preset defaults
			}
			$colors.each(function() {
				updateDesignDefaultHint(this);
			});
			if (gen) {
				gen.textContent = saved; // restore before the browser paints
			}
		});
	}

	/**
	 * Sync the sample button captions with the configured button texts.
	 */
	function updateDesignSampleLabels() {
		const i18n = fplantAdminData.i18n || {};
		const labels = {
			submit: $('#fplant-input-submit-text').val() || i18n.submit || 'Submit',
			back: $('#fplant-confirmation-back-text').val() || i18n.back || 'Back',
			confirm: $('#fplant-confirmation-submit-text').val() || i18n.submitForm || 'Submit'
		};
		// The submit button has its own preview host; the back and confirm
		// buttons share the merged confirm_buttons host, so target each by class.
		const targets = [
			{ section: 'submit', selector: '.fplant-submit-button', text: labels.submit },
			{ section: 'confirm_buttons', selector: '.fplant-back-button', text: labels.back },
			{ section: 'confirm_buttons', selector: '.fplant-confirm-submit-button', text: labels.confirm }
		];
		targets.forEach(function(target) {
			const host = document.querySelector('.fplant-design-preview-host[data-design-section="' + target.section + '"]');
			if (host && host.shadowRoot) {
				const btn = host.shadowRoot.querySelector(target.selector);
				if (btn) {
					btn.textContent = target.text;
				}
			}
		});
	}

	/**
	 * Open one design accordion section. Exclusive: any other open section is
	 * closed first, mirroring the field list accordion behavior.
	 */
	function openDesignAccordion($acc) {
		$('.fplant-design-accordion.open').not($acc).each(function() {
			closeDesignAccordion($(this));
		});
		$acc.addClass('open');
		$acc.find('.fplant-design-accordion-header').attr('aria-expanded', 'true')
			.find('.dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
		$acc.find('.fplant-design-accordion-body')
			.stop(true, true).prop('hidden', false).hide().slideDown(160, function() {
				// Measure-dependent bits (scaled sample height) need a visible body.
				updateDesignPreview($acc.data('design-section'));
				updateDesignSampleLabels();
				updateDesignDefaultHints($acc);
			});
	}

	function closeDesignAccordion($acc) {
		const $body = $acc.find('.fplant-design-accordion-body');
		$acc.removeClass('open');
		$acc.find('.fplant-design-accordion-header').attr('aria-expanded', 'false')
			.find('.dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
		$body.stop(true, true).slideUp(160, function() {
			$body.prop('hidden', true);
		});
	}

	/**
	 * Reset every input of one section to "unset" (= the design preset's
	 * defaults). Only touches the editor and the preview — nothing is
	 * persisted until the section's Save button is clicked.
	 */
	function resetDesignSection($acc) {
		$acc.find('.fplant-design-color').each(function() {
			// The picker's own clear button resets both the value and the swatch.
			$(this).closest('.wp-picker-container').find('.wp-picker-clear').trigger('click');
		});
		$acc.find('.fplant-design-input').not('.fplant-design-color').val('');
		$acc.find('.fplant-design-range').each(function() {
			// Park at the known preset default (if any) — same as the unset state.
			this.value = this.getAttribute('data-default') || this.min;
		});
		updateDesignPreview($acc.data('design-section'));
	}

	/**
	 * Show a short-lived status message next to the section's Save button.
	 */
	function showDesignSaveStatus($status, message, isError) {
		$status.text(message).toggleClass('error', !!isError).addClass('visible');
		window.clearTimeout($status.data('fplantStatusTimer'));
		$status.data('fplantStatusTimer', window.setTimeout(function() {
			$status.removeClass('visible');
		}, 3000));
	}

	/**
	 * Collect a section's non-empty values into a plain object. Unset keys are
	 * dropped so an emptied control falls back to the design preset default.
	 */
	function collectDesignSectionPayload(section) {
		const all = collectDesignSectionValues(section);
		const values = {};
		Object.keys(all).forEach(function(key) {
			if (all[key] !== '') {
				values[key] = all[key];
			}
		});
		return values;
	}

	/**
	 * Persist an accordion's section(s) via AJAX (fplant_save_design_options).
	 * This is a partial save: the server merges only the posted sections into the
	 * stored design_options, so other sections, unsaved field edits and the rest
	 * of the form are untouched and the page does not reload. A group accordion
	 * (e.g. confirm_buttons) sends all its member sections in one atomic request
	 * — saving them as separate concurrent requests would race on the shared
	 * settings meta and lose a section.
	 */
	function saveDesignSection($acc) {
		const i18n = fplantAdminData.i18n || {};
		const section = $acc.data('design-section');
		const $status = $acc.find('.fplant-design-save-status');
		const formId = fplantAdminData.formData && fplantAdminData.formData.id ? fplantAdminData.formData.id : 0;

		if (!formId) {
			showDesignSaveStatus($status, i18n.designSaveFirst || 'Please save the form first.', true);
			return;
		}

		const members = designGroupMembers(section);
		const request = {
			action: 'fplant_save_design_options',
			nonce: fplantAdminData.nonce,
			form_id: formId
		};
		if (members) {
			// Bulk save: a { section: values, ... } map written in one request.
			const sections = {};
			members.forEach(function(member) {
				sections[member] = collectDesignSectionPayload(member);
			});
			request.sections = JSON.stringify(sections);
		} else {
			request.section = section;
			request.values = JSON.stringify(collectDesignSectionPayload(section));
		}

		const $btn = $acc.find('.fplant-design-save').prop('disabled', true);
		$.post(fplantAdminData.ajaxUrl, request).done(function(response) {
			if (response && response.success) {
				showDesignSaveStatus($status, (response.data && response.data.message) || i18n.designSaved || 'Saved.', false);
			} else {
				showDesignSaveStatus($status, (response && response.data && response.data.message) || i18n.errorOccurred || 'An error occurred', true);
			}
		}).fail(function() {
			showDesignSaveStatus($status, i18n.networkError || 'A network error occurred', true);
		}).always(function() {
			$btn.prop('disabled', false);
		});
	}

	/**
	 * Schedule a preview refresh for the section containing $el. The timeout
	 * lets the color picker write its value to the input first.
	 */
	function scheduleDesignPreviewUpdate($el) {
		const $acc = $el.closest('.fplant-design-accordion');
		const section = $acc.length ? $acc.data('design-section') : null;
		window.setTimeout(function() {
			if (section) {
				updateDesignPreview(section);
			} else {
				updateAllDesignPreviews();
			}
		}, 0);
	}

	/**
	 * Initialize the design adjustments UI (Layout tab).
	 */
	function initDesignOptions() {
		if (!$('.fplant-design-adjust').length || !fplantAdminData.designSchema) {
			return;
		}

		// Accordion open/close: all sections start closed and only one is open
		// at a time — opening a section collapses the previous one.
		$(document).on('click', '.fplant-design-accordion-header', function() {
			const $acc = $(this).closest('.fplant-design-accordion');
			if ($acc.hasClass('open')) {
				closeDesignAccordion($acc);
			} else {
				openDesignAccordion($acc);
			}
		});

		// Per-section reset / partial AJAX save
		$(document).on('click', '.fplant-design-reset', function() {
			resetDesignSection($(this).closest('.fplant-design-accordion'));
		});
		$(document).on('click', '.fplant-design-save', function() {
			saveDesignSection($(this).closest('.fplant-design-accordion'));
		});

		// Part previews (shadow roots)
		document.querySelectorAll('.fplant-design-preview-host').forEach(function(host) {
			initDesignPreviewHost(host, host.getAttribute('data-design-section'));
		});

		// Color pickers (clear button = back to the preset default)
		$('.fplant-design-color').wpColorPicker({
			change: function() {
				scheduleDesignPreviewUpdate($(this));
			},
			clear: function() {
				scheduleDesignPreviewUpdate($(this));
			}
		});

		// Number/select inputs (and direct hex typing) → live preview.
		$(document).on('input change', '.fplant-design-input', function() {
			scheduleDesignPreviewUpdate($(this));
		});

		// Slider companions: dragging the range writes into the paired number
		// input (the single data-bound control), typing in the number moves the
		// slider. An emptied number = unset; the slider just parks at the known
		// preset default (data-default) or, failing that, its min.
		$(document).on('input', '.fplant-design-range', function() {
			$(this).closest('.fplant-design-slider-group')
				.find('.fplant-design-input').val(this.value).trigger('input');
		});
		$(document).on('input change', '.fplant-design-slider-group .fplant-design-input', function() {
			const $range = $(this).closest('.fplant-design-slider-group').find('.fplant-design-range');
			if ($range.length) {
				const raw = String($(this).val() || '').trim();
				$range.val(raw === '' ? ($range.attr('data-default') || $range.attr('min')) : raw);
			}
		});

		// Design type switch: show/hide the whole block and swap the preset CSS
		// inside every preview.
		$('input[name="design_type"]').on('change', function() {
			const none = $(this).val() === 'none';
			$('.fplant-design-adjust').toggle(!none);
			if (!none) {
				const url = currentDesignCssUrl();
				if (url) {
					document.querySelectorAll('.fplant-design-preview-host').forEach(function(host) {
						const link = host.shadowRoot && host.shadowRoot.querySelector('link[rel="stylesheet"]');
						if (link && link.href !== url) {
							link.href = url;
						}
					});
				}
			}
		});

		// Confirmation toggle: the back/confirm button sections only make sense
		// when the confirmation screen is enabled.
		$(document).on('change', '.fplant-setting-use-confirmation', function() {
			$('.fplant-design-confirmation-only').toggle($(this).is(':checked'));
		});

		// Re-sync sample captions when a button text modal is saved.
		$(document).on('click', '#fplant-save-input-submit, #fplant-save-confirmation-back, #fplant-save-confirmation-submit', function() {
			window.setTimeout(updateDesignSampleLabels, 0);
		});

		updateDesignSampleLabels();
		updateAllDesignPreviews();
		updateDesignDefaultHints();
	}

	/**
	 * Initialize embed settings
	 */
	function initEmbedSettings() {
		// iframe embed settings toggle
		$('.fplant-setting-embed-iframe-enabled').on('change', function() {
			const $settings = $('.fplant-embed-iframe-settings');
			if ($(this).is(':checked')) {
				$settings.removeClass('fplant-disabled');
				$settings.find('textarea').prop('readonly', false);
			} else {
				$settings.addClass('fplant-disabled');
				$settings.find('textarea').prop('readonly', true);
			}
		});

		// JS embed settings toggle
		$('.fplant-setting-embed-js-enabled').on('change', function() {
			const $settings = $('.fplant-embed-js-settings');
			if ($(this).is(':checked')) {
				$settings.removeClass('fplant-disabled');
				$settings.find('textarea').not('.fplant-embed-js-code').prop('readonly', false);
			} else {
				$settings.addClass('fplant-disabled');
				$settings.find('textarea').not('.fplant-embed-js-code').prop('readonly', true);
			}
		});

		// CAPTCHA type selection toggle
		$('.fplant-setting-captcha-type').on('change', function() {
			var selectedType = $(this).val();
			$('.fplant-captcha-details').hide();
			if (selectedType !== 'none') {
				$('.fplant-captcha-details-' + selectedType).show();
			}
		});

		// Honeypot toggle
		$('.fplant-setting-spam-honeypot').on('change', function() {
			const $settings = $('.fplant-spam-honeypot-settings');
			if ($(this).is(':checked')) {
				$settings.removeClass('fplant-disabled');
				$settings.find('input[type="text"]').prop('readonly', false);
			} else {
				$settings.addClass('fplant-disabled');
				$settings.find('input[type="text"]').prop('readonly', true);
			}
		});

		// Rate limit toggle
		$('.fplant-setting-spam-rate-limit').on('change', function() {
			const $settings = $('.fplant-spam-rate-limit-settings');
			if ($(this).is(':checked')) {
				$settings.removeClass('fplant-disabled');
				$settings.find('input[type="number"]').prop('readonly', false);
			} else {
				$settings.addClass('fplant-disabled');
				$settings.find('input[type="number"]').prop('readonly', true);
			}
		});

		// Time check toggle
		$('.fplant-setting-spam-time-check').on('change', function() {
			const $settings = $('.fplant-spam-time-check-settings');
			if ($(this).is(':checked')) {
				$settings.removeClass('fplant-disabled');
				$settings.find('input[type="number"]').prop('readonly', false);
			} else {
				$settings.addClass('fplant-disabled');
				$settings.find('input[type="number"]').prop('readonly', true);
			}
		});

		// Generate JS embed code in textarea from data attributes
		$('.fplant-embed-js-code').each(function() {
			var formId = $(this).data('formId');
			var embedJsUrl = $(this).data('embedJsUrl');
			var homeUrl = $(this).data('homeUrl');
			if (formId && embedJsUrl && homeUrl) {
				$(this).val(
					'<div id="fplant-form-' + formId + '"></div>\n' +
					'<script src="' + embedJsUrl + '"></script>\n' +
					'<script>FPlantEmbed.render(' + formId + ', \'#fplant-form-' + formId + '\', \'' + homeUrl + '\');</script>'
				);
			}
		});

		// Embed code copy button
		$(document).on('click', '.fplant-copy-embed-code', function(e) {
			e.preventDefault();
			const targetSelector = $(this).data('target');
			const $textarea = $(targetSelector);

			if ($textarea.length) {
				$textarea.select();
				document.execCommand('copy');

				// Feedback
				const originalText = $(this).text();
				$(this).text(fplantAdminData.i18n.copied);
				setTimeout(() => {
					$(this).text(originalText);
				}, 2000);
			}
		});
	}

	/**
	 * Get custom CSS file URLs from the file list
	 */
	function getCustomCssFileUrls() {
		var urls = [];
		$('.fplant-css-file-list .fplant-css-file-item').each(function() {
			var url = $(this).data('url');
			if (url) {
				urls.push(url);
			}
		});
		return urls;
	}

	/**
	 * Save form to database
	 */
	function saveFormToDatabase() {
		// Commit the currently open field row to memory first; abort the save if its
		// values are invalid (keep the row open so the user can fix them).
		if (!commitOpenAccordion()) {
			return;
		}

		// Validate HTML template for required fields
		var validation = validateHtmlTemplate();
		if (!validation.success) {
			alert(validation.message);
			return;
		}

		// Validate confirmation template for required submit button
		var confirmValidation = validateConfirmationTemplate();
		if (!confirmValidation.success) {
			alert(confirmValidation.message);
			return;
		}

		// Core settings owned by this editor, rebuilt from the UI. Merged (shallow)
		// over a clone of the original settings so unknown (Pro/future) top-level
		// settings keys survive the save (GAP-3). Shallow merge overwrites array-valued
		// core keys wholesale, avoiding the $.extend deep array index-merge hazard.
		const coreSettings = {
			use_html_template: $('.fplant-setting-use-html-template').is(':checked'),
			input_submit_text: $('#fplant-input-submit-text').val(),
			input_submit_class: $('#fplant-input-submit-class').val(),
			input_submit_id: $('#fplant-input-submit-id').val(),
			form_tag_class: $('#fplant-form-tag-class').val(),
			form_tag_id: $('#fplant-form-tag-id').val(),
			use_confirmation: $('.fplant-setting-use-confirmation').is(':checked'),
			confirmation_title: $('.fplant-setting-confirmation-title').val(),
			confirmation_message: $('.fplant-setting-confirmation-message').val(),
			use_confirmation_template: $('.fplant-setting-use-confirmation-template').is(':checked'),
			confirmation_template: $('.fplant-confirmation-template').val(),
			confirmation_back_text: $('#fplant-confirmation-back-text').val(),
			confirmation_back_class: $('#fplant-confirmation-back-class').val(),
			confirmation_back_id: $('#fplant-confirmation-back-id').val(),
			confirmation_submit_text: $('#fplant-confirmation-submit-text').val(),
			confirmation_submit_class: $('#fplant-confirmation-submit-class').val(),
			confirmation_submit_id: $('#fplant-confirmation-submit-id').val(),
			action_type: $('.fplant-setting-action-type').val(),
			success_message: $('.fplant-setting-success-message').val(),
			success_page_html: $('.fplant-setting-success-page-html').val(),
			redirect_url: $('.fplant-setting-redirect-url').val(),
			save_submission: $('.fplant-setting-save-submission:checked').val() || 'none',
			required_mark_text: $('.fplant-setting-required-mark').val() || '*',
			design_type: $('input[name="design_type"]:checked').val() || 'simple1',
			design_options: collectDesignOptions(),
			custom_css_file_urls: getCustomCssFileUrls(),
			custom_css_inline: $('.fplant-custom-css-inline').val() || '',
			// Embed settings
			embed_iframe_enabled: $('.fplant-setting-embed-iframe-enabled').is(':checked'),
			embed_iframe_allowed_urls: parseUrls($('.fplant-setting-embed-iframe-allowed-urls').val()),
			embed_js_enabled: $('.fplant-setting-embed-js-enabled').is(':checked'),
			embed_js_allowed_urls: parseUrls($('.fplant-setting-embed-js-allowed-urls').val()),
			// CAPTCHA settings
			captcha_type: $('input[name="captcha_type"]:checked').val() || 'none',
			// Spam protection settings
			spam_honeypot_enabled: $('.fplant-setting-spam-honeypot').is(':checked'),
			spam_honeypot_field_name: $('.fplant-setting-spam-honeypot-field-name').val() || 'fplant_website_url',
			spam_rate_limit_enabled: $('.fplant-setting-spam-rate-limit').is(':checked'),
			spam_rate_limit_minutes: parseInt($('.fplant-setting-spam-rate-limit-minutes').val()) || 5,
			spam_rate_limit_count: parseInt($('.fplant-setting-spam-rate-limit-count').val()) || 3,
			spam_time_check_enabled: $('.fplant-setting-spam-time-check').is(':checked'),
			spam_time_check_seconds: parseInt($('.fplant-setting-spam-time-check-seconds').val()) || 3,
			// Disposable email blocking
			spam_disposable_email_block: $('.fplant-setting-spam-disposable-email-block').is(':checked'),
			// URL parameter settings
			allow_url_params: $('.fplant-setting-allow-url-params').is(':checked'),
			// Webhooks (Integrations tab)
			webhooks: fplantCollectWebhooks()
		};

		const formData = {
			title: $('.fplant-form-title-input').val(),
			status: $('.fplant-form-status').val() || 'publish',
			fields: formFields,
			html_template: $('.fplant-html-template').val(),
			settings: $.extend({}, originalSettings, coreSettings),
			email_admin: {
				enabled: $('.fplant-email-admin-enabled').is(':checked'),
				to: $('.fplant-email-admin-to').val(),
				from_name: $('.fplant-email-admin-from-name').val(),
				from_email: $('.fplant-email-admin-from-email').val(),
				subject: $('.fplant-email-admin-subject').val(),
				body: $('.fplant-email-admin-body').val(),
				cc: $('.fplant-email-admin-cc').val(),
				bcc: $('.fplant-email-admin-bcc').val(),
				reply_to: $('.fplant-email-admin-reply-to').val()
			},
			email_user: {
				enabled: $('.fplant-email-user-enabled').is(':checked'),
				to_field: $('.fplant-email-user-to-field').val(),
				from_name: $('.fplant-email-user-from-name').val(),
				from_email: $('.fplant-email-user-from-email').val(),
				subject: $('.fplant-email-user-subject').val(),
				body: $('.fplant-email-user-body').val(),
				cc: $('.fplant-email-user-cc').val(),
				bcc: $('.fplant-email-user-bcc').val(),
				reply_to: $('.fplant-email-user-reply-to').val()
			}
		};

		// Collect custom settings fields (fplant_custom_settings_fields).
		// Skip keys that collide with built-in settings to avoid clobbering them.
		// Note: compare against coreSettings (built-in keys only), NOT the merged
		// formData.settings — otherwise a previously-saved custom/Pro key carried over
		// from originalSettings would block its own DOM-edited value from being collected.
		const fplantCoreSettingKeys = Object.keys(coreSettings);
		$('#tab-settings [data-fplant-setting]').each(function () {
			const $csEl = $(this);
			const csKey = $csEl.attr('data-fplant-setting');
			if (!csKey || fplantCoreSettingKeys.indexOf(csKey) !== -1) {
				return;
			}
			formData.settings[csKey] = $csEl.is(':checkbox') ? $csEl.is(':checked') : $csEl.val();
		});

		const formId = $('.fplant-save-form').data('form-id') || 0;

		$.ajax({
			url: fplantAdminData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fplant_save_form',
				nonce: fplantAdminData.nonce,
				form_id: formId,
				form_data: JSON.stringify(formData)
			},
			success: function(response) {
				if (response.success) {
					// Remember current active tab
					var activeTab = $('.fplant-tab.active').data('tab');

					// Reload page on save success
					if (response.data.form_id && !formId) {
						// Redirect to edit page for new forms
						var newUrl = fplantAdminData.editUrl + '&id=' + response.data.form_id + '&message=created';
						if (activeTab) {
							newUrl += '&active_tab=' + activeTab;
						}
						window.location.href = newUrl;
					} else {
						// Reload for existing form updates
						var url = new URL(window.location.href);
						url.searchParams.set('message', 'updated');
						if (activeTab) {
							url.searchParams.set('active_tab', activeTab);
						}
						window.location.href = url.toString();
					}
				} else {
					showAdminNotice(response.data.message || fplantAdminData.i18n.errorOccurred, 'error');
				}
			},
			error: function() {
				showAdminNotice(fplantAdminData.i18n.errorOccurred, 'error');
			}
		});
	}

	/**
	 * Initialize form save button
	 */
	function initFormSave() {
		$('.fplant-save-form').off('click').on('click', function() {
			saveFormToDatabase();
		});
	}

	/**
	 * Move form to trash from edit screen
	 */
	function initFormDeleteFromEdit() {
		$(document).on('click', '.fplant-delete-form-edit', function(e) {
			e.preventDefault();

			if (!confirm(fplantAdminData.i18n.confirmTrashForm)) {
				return;
			}

			const formId = $(this).data('form-id');

			$.ajax({
				url: fplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_trash_form',
					nonce: fplantAdminData.nonce,
					form_id: formId
				},
				success: function(response) {
					if (response.success) {
						window.location.href = fplantAdminData.listUrl;
					} else {
						alert(response.data.message || fplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					alert(fplantAdminData.i18n.errorOccurred);
				}
			});
		});
	}

	/**
	 * Action type toggle
	 */
	function initActionTypeToggle() {
		function toggleActionFields() {
			const actionType = $('.fplant-setting-action-type').val();

			// Hide all
			$('.fplant-action-message, .fplant-action-custom-page, .fplant-action-redirect').hide();

			// Show selected
			if (actionType === 'message') {
				$('.fplant-action-message').show();
			} else if (actionType === 'custom_page') {
				$('.fplant-action-custom-page').show();
			} else if (actionType === 'redirect') {
				$('.fplant-action-redirect').show();
			}
		}

		// Initial display
		toggleActionFields();

		// On change
		$('.fplant-setting-action-type').on('change', toggleActionFields);
	}

	/**
	 * HTML template enable/disable toggle
	 */
	function initHtmlTemplateToggle() {
		$('.fplant-setting-use-html-template').on('change', function() {
			const $fields = $('.fplant-html-template-fields');
			if ($(this).is(':checked')) {
				$fields.removeClass('fplant-disabled');
				$fields.find('textarea').prop('readonly', false);
				$fields.find('.fplant-tag-select').prop('disabled', false);
				$fields.find('.fplant-insert-tag').prop('disabled', false);
			} else {
				$fields.addClass('fplant-disabled');
				$fields.find('textarea').prop('readonly', true);
				$fields.find('.fplant-tag-select').prop('disabled', true);
				$fields.find('.fplant-insert-tag').prop('disabled', true);
			}
		});
	}

	/**
	 * Tag inserter functionality
	 */
	function initTagInserter() {
		$(document).on('click', '.fplant-insert-tag', function(e) {
			e.preventDefault();

			var $select = $(this).siblings('.fplant-tag-select');
			var tag = $select.val();

			if (!tag) {
				return;
			}

			var $textarea = $('.fplant-html-template');
			var textarea = $textarea[0];

			// Insert tag at cursor position
			if (typeof textarea.selectionStart !== 'undefined') {
				var startPos = textarea.selectionStart;
				var endPos = textarea.selectionEnd;
				var text = $textarea.val();

				// Replace selection or insert at cursor
				$textarea.val(
					text.substring(0, startPos) +
					tag +
					text.substring(endPos)
				);

				// Move cursor to after inserted tag
				textarea.selectionStart = textarea.selectionEnd = startPos + tag.length;
				textarea.focus();
			} else {
				// Fallback: append to end
				$textarea.val($textarea.val() + tag);
			}

			// Reset dropdown
			$select.val('');
		});
	}

	/**
	 * Validate HTML template for required fields
	 * @returns {Object} Validation result with success flag and error message
	 */
	function validateHtmlTemplate() {
		var useTemplate = $('.fplant-setting-use-html-template').is(':checked');

		// If template is not enabled, skip validation
		if (!useTemplate) {
			return { success: true };
		}

		var template = $('.fplant-html-template').val();

		// If template is empty, show error
		if (!template.trim()) {
			return {
				success: false,
				message: fplantAdminData.i18n.templateEmpty || 'HTML template is empty. Please add the required tags or uncheck "Use HTML template".'
			};
		}

		var missingItems = [];

		// Check for submit button
		if (template.indexOf('[fplant_submit') === -1) {
			missingItems.push(fplantAdminData.i18n.submitButton || 'Submit button');
		}

		// Get required fields from formFields array
		for (var i = 0; i < formFields.length; i++) {
			var field = formFields[i];
			if (field.required) {
				var tagPattern = '[fplant_field name="' + field.name + '"]';
				if (template.indexOf(tagPattern) === -1) {
					missingItems.push(field.label || field.name);
				}
			}
		}

		if (missingItems.length > 0) {
			return {
				success: false,
				message: fplantAdminData.i18n.missingRequiredFields + '\n' + missingItems.join(', ')
			};
		}

		return { success: true };
	}

	/**
	 * Confirmation screen settings enable/disable toggle
	 */
	function initConfirmationToggle() {
		$('.fplant-setting-use-confirmation').on('change', function() {
			const $fields = $('.fplant-confirmation-fields');
			const $templateCheckbox = $('.fplant-setting-use-confirmation-template');

			if ($(this).is(':checked')) {
				$fields.removeClass('fplant-disabled');
				$fields.find('input:not(.fplant-setting-use-confirmation-template), textarea:not(.fplant-confirmation-template)').prop('readonly', false);
				$fields.find('button:not(.fplant-insert-confirmation-tag)').prop('disabled', false);
				$templateCheckbox.prop('disabled', false);
			} else {
				$fields.addClass('fplant-disabled');
				$fields.find('input, textarea').prop('readonly', true);
				$fields.find('button').prop('disabled', true);
				$templateCheckbox.prop('disabled', true);
				// Also disable confirmation template
				$templateCheckbox.prop('checked', false).trigger('change');
			}
		});
	}

	/**
	 * Confirmation template enable/disable toggle
	 */
	function initConfirmationTemplateToggle() {
		$('.fplant-setting-use-confirmation-template').on('change', function() {
			const $fields = $('.fplant-confirmation-template-fields');
			if ($(this).is(':checked')) {
				$fields.removeClass('fplant-disabled');
				$fields.find('textarea').prop('readonly', false);
				$fields.find('.fplant-confirmation-tag-select').prop('disabled', false);
				$fields.find('.fplant-insert-confirmation-tag').prop('disabled', false);
			} else {
				$fields.addClass('fplant-disabled');
				$fields.find('textarea').prop('readonly', true);
				$fields.find('.fplant-confirmation-tag-select').prop('disabled', true);
				$fields.find('.fplant-insert-confirmation-tag').prop('disabled', true);
			}
		});
	}

	/**
	 * Confirmation tag inserter functionality
	 */
	function initConfirmationTagInserter() {
		$(document).on('click', '.fplant-insert-confirmation-tag', function(e) {
			e.preventDefault();

			var $select = $(this).siblings('.fplant-confirmation-tag-select');
			var tag = $select.val();

			if (!tag) {
				return;
			}

			var $textarea = $('.fplant-confirmation-template');
			var textarea = $textarea[0];

			// Insert tag at cursor position
			if (typeof textarea.selectionStart !== 'undefined') {
				var startPos = textarea.selectionStart;
				var endPos = textarea.selectionEnd;
				var text = $textarea.val();

				$textarea.val(
					text.substring(0, startPos) +
					tag +
					text.substring(endPos)
				);

				textarea.selectionStart = textarea.selectionEnd = startPos + tag.length;
				textarea.focus();
			} else {
				$textarea.val($textarea.val() + tag);
			}

			$select.val('');
		});
	}

	/**
	 * Validate confirmation HTML template for required submit button
	 * @returns {Object} Validation result with success flag and error message
	 */
	function validateConfirmationTemplate() {
		var useConfirmation = $('.fplant-setting-use-confirmation').is(':checked');
		var useTemplate = $('.fplant-setting-use-confirmation-template').is(':checked');

		// If confirmation or template is not enabled, skip validation
		if (!useConfirmation || !useTemplate) {
			return { success: true };
		}

		var template = $('.fplant-confirmation-template').val();

		// If template is empty, show error
		if (!template.trim()) {
			return {
				success: false,
				message: fplantAdminData.i18n.confirmationTemplateEmpty || 'Confirmation HTML template is empty. Please add the required tags or uncheck "Use confirmation screen HTML template".'
			};
		}

		// Check for submit button (required)
		if (template.indexOf('[fplant_confirm_submit') === -1) {
			return {
				success: false,
				message: fplantAdminData.i18n.confirmationSubmitRequired || 'Submit button [fplant_confirm_submit] is required in the confirmation template.'
			};
		}

		return { success: true };
	}

	/**
	 * Submit button settings modal
	 */
	function initInputSubmitModal() {
		// Edit button click shows modal
		$('.fplant-edit-input-submit').on('click', function() {
			$('#fplant-input-submit-modal').addClass('active');
		});

		// Close modal
		$('#fplant-input-submit-modal .fplant-modal-close').on('click', function() {
			$('#fplant-input-submit-modal').removeClass('active');
		});

		// OK button updates preview and closes modal, saves to database
		$('#fplant-save-input-submit').on('click', function() {
			var text = $('#fplant-input-submit-text').val() || fplantAdminData.i18n.submit;
			$('.fplant-input-submit-preview').text(text);
			$('#fplant-input-submit-modal').removeClass('active');
			saveFormToDatabase();
		});
	}

	/**
	 * Confirmation screen button settings modals
	 */
	function initConfirmationButtonModals() {
		// Back button modal
		$('.fplant-edit-confirmation-back').on('click', function() {
			$('#fplant-confirmation-back-modal').addClass('active');
		});

		$('#fplant-confirmation-back-modal .fplant-modal-close').on('click', function() {
			$('#fplant-confirmation-back-modal').removeClass('active');
		});

		$('#fplant-save-confirmation-back').on('click', function() {
			var text = $('#fplant-confirmation-back-text').val() || fplantAdminData.i18n.back;
			$('.fplant-confirmation-back-preview').text(text);
			$('#fplant-confirmation-back-modal').removeClass('active');
			saveFormToDatabase();
		});

		// Submit button modal
		$('.fplant-edit-confirmation-submit').on('click', function() {
			$('#fplant-confirmation-submit-modal').addClass('active');
		});

		$('#fplant-confirmation-submit-modal .fplant-modal-close').on('click', function() {
			$('#fplant-confirmation-submit-modal').removeClass('active');
		});

		$('#fplant-save-confirmation-submit').on('click', function() {
			var text = $('#fplant-confirmation-submit-text').val() || fplantAdminData.i18n.submitForm;
			$('.fplant-confirmation-submit-preview').text(text);
			$('#fplant-confirmation-submit-modal').removeClass('active');
			saveFormToDatabase();
		});
	}

	/**
	 * Design CSS download handler
	 */
	function initDesignCssDownload() {
		$(document).on('click', '.fplant-download-design-css', function(e) {
			e.preventDefault();
			var designType = $(this).data('design-type');
			if (designType && fplantAdminData.pluginUrl) {
				// simple1 uses form.css; others use design-{type}.css
				var url, filename;
				if (designType === 'simple1') {
					url = fplantAdminData.pluginUrl + 'assets/css/form.css';
					filename = 'form.css';
				} else {
					url = fplantAdminData.pluginUrl + 'assets/css/design-' + designType + '.css';
					filename = 'design-' + designType + '.css';
				}
				var a = document.createElement('a');
				a.href = url;
				a.download = filename;
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
			}
		});
	}

	/**
	 * Custom CSS file uploader (multiple files)
	 */
	function initCustomCssFileUploader() {
		// Enable/disable upload button on file selection
		$(document).on('change', '.fplant-css-file-input', function() {
			var $btn = $(this).closest('.fplant-css-upload-wrapper').find('.fplant-css-upload-button');
			$btn.prop('disabled', !this.files.length);
		});

		// Upload on button click
		$(document).on('click', '.fplant-css-upload-button', function() {
			var $btn = $(this);
			var $wrapper = $btn.closest('.fplant-css-upload-wrapper');
			var $input = $wrapper.find('.fplant-css-file-input');
			var file = $input[0].files[0];

			if (!file) {
				return;
			}

			// Check if CSS file
			if (!file.name.endsWith('.css')) {
				alert(fplantAdminData.i18n.cssFileRequired);
				$input.val('');
				$btn.prop('disabled', true);
				return;
			}

			// Check file limit
			if ($('.fplant-css-file-list .fplant-css-file-item').length >= 10) {
				alert(fplantAdminData.i18n.cssFileLimit);
				$input.val('');
				$btn.prop('disabled', true);
				return;
			}

			var $status = $wrapper.find('.fplant-css-upload-status');
			$status.text(fplantAdminData.i18n.uploading);
			$btn.prop('disabled', true);

			// Get form ID
			var formId = 0;
			var urlParams = new URLSearchParams(window.location.search);
			if (urlParams.has('id')) {
				formId = parseInt(urlParams.get('id'), 10);
			}

			// Create FormData
			var formData = new FormData();
			formData.append('action', 'fplant_upload_css');
			formData.append('nonce', fplantAdminData.cssNonce);
			formData.append('css_file', file);
			formData.append('form_id', formId);

			// Ajax upload
			$.ajax({
				url: fplantAdminData.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						$status.text(fplantAdminData.i18n.uploadComplete + ' ' + response.data.filename);

						// Add to file list
						var $fileItem = $('<div class="fplant-css-file-item"></div>');
						$fileItem.attr('data-url', response.data.url);
						$fileItem.append('<code>' + $('<span>').text(response.data.filename).html() + '</code>');
						$fileItem.append(' <button type="button" class="button button-small fplant-remove-css-file">' + fplantAdminData.i18n.delete + '</button>');
						$('.fplant-css-file-list').append($fileItem);
					} else {
						$status.text(fplantAdminData.i18n.errorPrefix + ' ' + (response.data.message || fplantAdminData.i18n.uploadFailed));
					}
					$input.val('');
					$btn.prop('disabled', true);
				},
				error: function() {
					$status.text(fplantAdminData.i18n.errorPrefix + ' ' + fplantAdminData.i18n.networkError);
					$input.val('');
					$btn.prop('disabled', true);
				}
			});
		});

		// File delete button (event delegation)
		$(document).on('click', '.fplant-remove-css-file', function(e) {
			e.preventDefault();

			var $item = $(this).closest('.fplant-css-file-item');
			var fileUrl = $item.data('url');

			if (!fileUrl) {
				return;
			}

			if (!confirm(fplantAdminData.i18n.confirmDeleteCss)) {
				return;
			}

			$.ajax({
				url: fplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_delete_css',
					nonce: fplantAdminData.cssNonce,
					file_url: fileUrl
				},
				success: function(response) {
					if (response.success) {
						$item.remove();
						$('.fplant-css-upload-status').text('');
					} else {
						alert(fplantAdminData.i18n.errorPrefix + ' ' + (response.data.message || fplantAdminData.i18n.deleteFailed));
					}
				},
				error: function() {
					alert(fplantAdminData.i18n.networkError);
				}
			});
		});
	}

	/**
	 * Form list quick edit
	 */
	function initQuickEdit() {
		var $inlineEdit = $('#fplant-inline-edit');
		if (!$inlineEdit.length) {
			return;
		}

		var $inlineEditRow = $inlineEdit.clone();
		var currentFormId = null;
		var $currentRow = null;

		// Quick edit button click
		$(document).on('click', '.editinline', function(e) {
			e.preventDefault();

			var $button = $(this);
			var formId = $button.data('form-id');
			var formTitle = $button.data('form-title');
			var formStatus = $button.data('form-status');

			// Remove existing quick edit row
			$('.inline-edit-row:visible').remove();

			// Get target row
			$currentRow = $button.closest('tr');
			currentFormId = formId;

			// Create quick edit row
			var $editRow = $inlineEditRow.clone();
			$editRow.find('input[name="post_title"]').val(formTitle);
			$editRow.find('select[name="post_status"]').val(formStatus);

			// Insert after target row
			$currentRow.hide();
			$currentRow.after($('<tr class="inline-edit-row"></tr>').append($editRow.find('td')));
		});

		// Cancel button
		$(document).on('click', '.inline-edit-row .cancel', function(e) {
			e.preventDefault();
			$('.inline-edit-row').remove();
			if ($currentRow) {
				$currentRow.show();
			}
			currentFormId = null;
			$currentRow = null;
		});

		// Save button
		$(document).on('click', '.inline-edit-row .save', function(e) {
			e.preventDefault();

			if (!currentFormId) {
				return;
			}

			var $row = $(this).closest('.inline-edit-row');
			var $spinner = $row.find('.spinner');
			var postTitle = $row.find('input[name="post_title"]').val();
			var postStatus = $row.find('select[name="post_status"]').val();

			$spinner.addClass('is-active');

			$.ajax({
				url: fplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_quick_edit_form',
					nonce: $('input[name="fplant_quick_edit_nonce"]').val(),
					form_id: currentFormId,
					post_title: postTitle,
					post_status: postStatus
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					if (response.success) {
						// Reload page to reflect changes
						location.reload();
					} else {
						alert(response.data.message || fplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					$spinner.removeClass('is-active');
					alert(fplantAdminData.i18n.errorOccurred);
				}
			});
		});
	}

	/**
	 * Initialize
	 */
	$(document).ready(function() {
		initTabs();
		initFieldAdd();
		initFieldTypePicker();
		initFieldDelete();
		initFieldEdit();
		initFieldDuplicate();
		initEditorTabs();
		initFormDelete();
		initFormDuplicate();
		initModal();
		initCopyButton();
		initFormSave();
		initFormDeleteFromEdit();
		initFieldTypeChange();
		initSaveField();
		initAutoGenerateFieldName();
		initActionTypeToggle();
		initHtmlTemplateToggle();
		initTagInserter();
		initConfirmationToggle();
		initConfirmationTemplateToggle();
		initConfirmationTagInserter();
		initInputSubmitModal();
		initConfirmationButtonModals();
		initCustomCssFileUploader();
		initDesignCssDownload();
		initEmbedSettings();
		initFormPreview();
		initDesignOptions();
		initQuickEdit();

		// Load existing fields and settings. Deep-clone both so the editor's working
		// state is independent of the localized source, and so the merge paths in
		// initSaveField()/saveFormToDatabase() can preserve unknown (Pro/future) keys
		// from the originals (GAP-3).
		if (typeof fplantAdminData.formData !== 'undefined') {
			if (fplantAdminData.formData.fields) {
				formFields = $.extend(true, [], fplantAdminData.formData.fields);
				originalFieldNames = new Set(formFields.map(function(f) { return f.name; }));
				renderFieldList();
			}
			originalSettings = $.extend(true, {}, fplantAdminData.formData.settings || {});
		}
	});

	// ==========================================
	// Webhooks (Integrations tab)
	// ==========================================

	function fplantGenerateWebhookSecret() {
		const bytes = new Uint8Array(16);
		window.crypto.getRandomValues(bytes);
		return Array.prototype.map.call(bytes, function (b) {
			return ('0' + b.toString(16)).slice(-2);
		}).join('');
	}

	// Collect webhook rows for the save payload. Rows without a URL are
	// dropped server-side (FPLANT_Webhook::sanitize_settings).
	function fplantCollectWebhooks() {
		const rows = [];
		$('#tab-integrations .fplant-webhook-rows .fplant-webhook-row').each(function () {
			const $row = $(this);
			rows.push({
				enabled: $row.find('.fplant-webhook-enabled').is(':checked'),
				url: ($row.find('.fplant-webhook-url').val() || '').trim(),
				secret: $row.find('.fplant-webhook-secret').val() || ''
			});
		});
		return rows;
	}

	function fplantUpdateWebhookAddButton() {
		const $rows = $('#tab-integrations .fplant-webhook-rows');
		if (!$rows.length) {
			return;
		}
		const max = parseInt($rows.data('max'), 10) || 3;
		$('.fplant-webhook-add').toggle($rows.find('.fplant-webhook-row').length < max);
	}

	$(document).on('click', '.fplant-webhook-add', function () {
		const tpl = document.getElementById('fplant-webhook-row-template');
		if (!tpl) {
			return;
		}
		const $row = $(tpl.content.firstElementChild.cloneNode(true));
		$row.find('.fplant-webhook-secret').val(fplantGenerateWebhookSecret());
		$('#tab-integrations .fplant-webhook-rows').append($row);
		fplantUpdateWebhookAddButton();
	});

	$(document).on('click', '.fplant-webhook-remove', function () {
		$(this).closest('.fplant-webhook-row').remove();
		fplantUpdateWebhookAddButton();
	});

	$(document).on('click', '.fplant-webhook-regenerate', function () {
		if (!window.confirm($(this).data('confirm'))) {
			return;
		}
		$(this).closest('.fplant-webhook-row').find('.fplant-webhook-secret').val(fplantGenerateWebhookSecret());
	});

	$(document).on('click', '.fplant-webhook-test', function () {
		const $btn = $(this);
		const $row = $btn.closest('.fplant-webhook-row');
		const $result = $row.find('.fplant-webhook-test-result');
		$btn.prop('disabled', true);
		$result.text('…').css('color', '');
		$.ajax({
			url: fplantAdminData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fplant_webhook_test',
				nonce: fplantAdminData.nonce,
				form_id: $('.fplant-save-form').data('form-id') || 0,
				url: ($row.find('.fplant-webhook-url').val() || '').trim(),
				secret: $row.find('.fplant-webhook-secret').val() || ''
			}
		}).done(function (response) {
			const data = (response && response.data) || {};
			$result.text(data.message || '').css('color', response && response.success ? '#1a7f37' : '#b32d2e');
		}).fail(function () {
			$result.text('Request failed').css('color', '#b32d2e');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

})(jQuery);
