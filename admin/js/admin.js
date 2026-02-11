/**
 * Form Plant - Admin JavaScript
 *
 * @package Form_Plant
 */

(function($) {
	'use strict';

	// Hold field data
	let formFields = [];

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
			'<button type="button" class="notice-dismiss"><span class="screen-reader-text">' + wpfplantAdminData.i18n.dismissNotice + '</span></button>' +
			'</div>');

		// Insert after .fplant-page-header
		$('.fplant-page-header').first().after(notice);

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
	}

	/**
	 * Add field
	 */
	function initFieldAdd() {
		$('.fplant-add-field').on('click', function(e) {
			e.preventDefault();
			openFieldModal();
		});
	}

	/**
	 * Display error in field modal
	 */
	function showFieldModalError(message) {
		$('#fplant-field-modal-errors').text(message).show();
		// Scroll to top of modal
		$('.fplant-field-modal-content .fplant-modal-body').scrollTop(0);
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

	function openFieldModal(index = null) {
		// Clear error display
		clearFieldModalErrors();
		currentEditingIndex = index;
		const isEdit = index !== null;
		const field = isEdit ? formFields[index] : null;

		// Set modal title
		$('#fplant-field-modal-title').text(isEdit ? wpfplantAdminData.i18n.editField : wpfplantAdminData.i18n.addField);

		// Set field data
		$('#fplant-field-type').val(field ? field.type : 'text');
		$('#fplant-field-name').val(field ? field.name : '').prop('disabled', isEdit);
		$('#fplant-field-label').val(field ? field.label : '');
		$('#fplant-field-placeholder').val(field ? field.placeholder : '');
		$('#fplant-field-required').prop('checked', field ? field.required : false);
		$('#fplant-field-validation-message').val(field && field.validation_message ? field.validation_message : '');
		$('#fplant-field-custom-id').val(field && field.custom_id ? field.custom_id : '');
		$('#fplant-field-custom-class').val(field && field.custom_class ? field.custom_class : '');

		// Set date range
		$('#fplant-field-year-start').val(field && field.year_start ? field.year_start : '');
		$('#fplant-field-year-end').val(field && field.year_end ? field.year_end : '');

		// Set file size
		$('#fplant-field-max-size').val(field && field.max_size ? field.max_size : '');

		// Set text field settings (size, maxlength)
		$('#fplant-field-size').val(field && field.size ? field.size : '');
		$('#fplant-field-maxlength').val(field && field.maxlength ? field.maxlength : '');

		// Set default value
		$('#fplant-field-default-value').val(field && field.default ? field.default : '');

		// Set HTML content
		$('#fplant-field-html-content').val(field && field.content ? field.content : '');

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

		// Show modal
		$('#fplant-field-modal').addClass('active');
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

		// Text field settings visibility (size, maxlength)
		if (fieldType === 'text') {
			$('#fplant-field-text-settings-section').show();
		} else {
			$('#fplant-field-text-settings-section').hide();
		}

		// HTML content section (for html type)
		if (fieldType === 'html') {
			$('#fplant-field-html-section').show();
		} else {
			$('#fplant-field-html-section').hide();
		}

		// Default value setting (show for all types except file and html)
		if (fieldType !== 'file' && fieldType !== 'html') {
			$('#fplant-field-default-value-section').show();
		} else {
			$('#fplant-field-default-value-section').hide();
		}

		// Hide unnecessary fields for hidden and html types
		const isHiddenOrHtml = fieldType === 'hidden' || fieldType === 'html';
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

		// Placeholder is only for text input types and select
		const hasPlaceholder = ['text', 'email', 'tel', 'url', 'number', 'textarea', 'select'].includes(fieldType);
		const $placeholderGroup = $('#fplant-field-placeholder').closest('.fplant-form-group');
		if (hasPlaceholder) {
			$placeholderGroup.show();
		} else {
			$placeholderGroup.hide();
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
	}

	/**
	 * Auto-generate field name from field label
	 */
	function initAutoGenerateFieldName() {
		$(document).on('input', '#fplant-field-label', function() {
			// Only auto-generate for new fields (disabled during edit)
			if (currentEditingIndex === null && !$('#fplant-field-name').val()) {
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
	function initSaveField() {
		$(document).on('click', '#fplant-save-field', function(e) {
			e.preventDefault();

			const fieldType = $('#fplant-field-type').val();
			const fieldName = $('#fplant-field-name').val().trim();
			const fieldLabel = $('#fplant-field-label').val().trim();
			const fieldPlaceholder = $('#fplant-field-placeholder').val().trim();
			const fieldRequired = $('#fplant-field-required').is(':checked');
			const validationMessage = $('#fplant-field-validation-message').val().trim();
			const customId = $('#fplant-field-custom-id').val().trim();
			const customClass = $('#fplant-field-custom-class').val().trim();

			// Validation
			if (!fieldName) {
				showFieldModalError(wpfplantAdminData.i18n.fieldNameRequired);
				return;
			}

			// Field name format check (alphanumeric and underscores only)
			if (!/^[a-zA-Z0-9_]+$/.test(fieldName)) {
				showFieldModalError(wpfplantAdminData.i18n.fieldNameAlphanumeric);
				return;
			}

			// Label required for non-hidden and non-html types
			if (fieldType !== 'hidden' && fieldType !== 'html' && !fieldLabel) {
				showFieldModalError(wpfplantAdminData.i18n.fieldLabelRequired);
				return;
			}

			// Duplicate field name check (except during edit)
			if (currentEditingIndex === null) {
				const exists = formFields.some(f => f.name === fieldName);
				if (exists) {
					showFieldModalError(wpfplantAdminData.i18n.fieldNameExists);
					return;
				}
			}

			// Create field object
			const field = {
				type: fieldType,
				name: fieldName,
				label: fieldLabel,
				placeholder: fieldPlaceholder,
				required: fieldRequired,
				validation_message: validationMessage,
				custom_id: customId,
				custom_class: customClass,
				validation: {}
			};

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

			// For text field types (size, maxlength)
			if (fieldType === 'text') {
				const size = $('#fplant-field-size').val();
				const maxlength = $('#fplant-field-maxlength').val();
				if (size) {
					field.size = parseInt(size);
				}
				if (maxlength) {
					field.maxlength = parseInt(maxlength);
				}
			}

			// Default value setting (except file)
			if (fieldType !== 'file') {
				const defaultValue = $('#fplant-field-default-value').val().trim();
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
					showFieldModalError(wpfplantAdminData.i18n.htmlContentRequired || 'HTML content is required');
					return;
				}
				field.content = htmlContent;
				if (!field.label) {
					field.label = field.name;
				}
			}

			// For types that need options
			if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
				const options = parseOptionsFromTextarea();

				if (options.length === 0) {
					showFieldModalError(wpfplantAdminData.i18n.addOneOption);
					return;
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

			// Add or update field
			if (currentEditingIndex !== null) {
				formFields[currentEditingIndex] = field;
			} else {
				formFields.push(field);
			}

			// Close modal
			$('#fplant-field-modal').removeClass('active');

			// Re-render list
			renderFieldList();

			// Save to database
			saveFormToDatabase();
		});
	}

	/**
	 * Render field list
	 */
	function renderFieldList() {
		const $list = $('.fplant-field-list');
		$list.empty();

		if (formFields.length === 0) {
			$list.html('<p class="fplant-no-fields">' + wpfplantAdminData.i18n.noFieldsYet + '</p>');
			return;
		}

		formFields.forEach((field, index) => {
			const $item = $(`
				<div class="fplant-field-item" data-field-index="${index}">
					<div class="fplant-field-item-header">
						<span class="fplant-drag-handle dashicons dashicons-move" style="cursor: move; color: #8c8f94; margin-right: 8px;"></span>
						<div class="fplant-field-item-title">
							${field.label}
							<span style="color: #646970; font-weight: normal;">(${field.type})</span>
							<br>
							<span style="color: #787c82; font-size: 12px; font-weight: normal;">${wpfplantAdminData.i18n.fieldNameLabel} ${field.name}</span>
						</div>
						<div class="fplant-field-item-actions">
							<button type="button" class="button fplant-edit-field" data-index="${index}">${wpfplantAdminData.i18n.edit}</button>
							<button type="button" class="button fplant-delete-field" data-index="${index}" style="color: #d63638;">${wpfplantAdminData.i18n.delete}</button>
						</div>
					</div>
				</div>
			`);

			$list.append($item);
		});

		// Initialize sorting
		initFieldSort();
	}

	/**
	 * Delete field
	 */
	function initFieldDelete() {
		$(document).on('click', '.fplant-delete-field', function(e) {
			e.preventDefault();

			if (!confirm(wpfplantAdminData.i18n.confirmDeleteField)) {
				return;
			}

			const index = $(this).data('index');
			formFields.splice(index, 1);
			renderFieldList();
		});
	}

	/**
	 * Edit field
	 */
	function initFieldEdit() {
		$(document).on('click', '.fplant-edit-field', function(e) {
			e.preventDefault();

			const index = $(this).data('index');
			openFieldModal(index);
		});
	}

	/**
	 * Delete form
	 */
	function initFormDelete() {
		$(document).on('click', '.fplant-delete-form', function(e) {
			e.preventDefault();

			if (!confirm(wpfplantAdminData.i18n.confirmDeleteForm)) {
				return;
			}

			const formId = $(this).data('form-id');
			const $row = $(this).closest('tr');

			$.ajax({
				url: wpfplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_delete_form',
					nonce: wpfplantAdminData.nonce,
					form_id: formId
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || wpfplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					alert(wpfplantAdminData.i18n.errorOccurred);
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
			},
			update: function(e, ui) {
				// Update order
				const newOrder = [];
				$('.fplant-field-item').each(function() {
					const index = $(this).data('field-index');
					newOrder.push(formFields[index]);
				});
				formFields = newOrder;
				renderFieldList();
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
				url: wpfplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_duplicate_form',
					nonce: wpfplantAdminData.nonce,
					form_id: formId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || wpfplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					alert(wpfplantAdminData.i18n.errorOccurred);
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
				$(this).closest('.fplant-modal').removeClass('active');
			}
		});

		// Close modal with ESC key
		$(document).on('keyup', function(e) {
			if (e.key === 'Escape') {
				$('.fplant-modal').removeClass('active');
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
			$(this).text(wpfplantAdminData.i18n.copied);
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

		// reCAPTCHA settings toggle
		$('.fplant-setting-recaptcha-enabled').on('change', function() {
			const $settings = $('.fplant-recaptcha-settings');
			if ($(this).is(':checked')) {
				$settings.removeClass('fplant-disabled');
				$settings.find('input[type="radio"]').prop('disabled', false);
			} else {
				$settings.addClass('fplant-disabled');
				$settings.find('input[type="radio"]').prop('disabled', true);
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
				$(this).text(wpfplantAdminData.i18n.copied);
				setTimeout(() => {
					$(this).text(originalText);
				}, 2000);
			}
		});
	}

	/**
	 * Save form to database
	 */
	function saveFormToDatabase() {
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

		const formData = {
			title: $('.fplant-form-title-input').val(),
			status: $('.fplant-form-status').val() || 'publish',
			fields: formFields,
			html_template: $('.fplant-html-template').val(),
			settings: {
				use_html_template: $('.fplant-setting-use-html-template').is(':checked'),
				input_submit_text: $('#fplant-input-submit-text').val(),
				input_submit_class: $('#fplant-input-submit-class').val(),
				input_submit_id: $('#fplant-input-submit-id').val(),
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
				custom_css_mode: $('input[name="custom_css_mode"]:checked').val() || 'none',
				custom_css_file_url: $('.fplant-custom-css-file-url').val() || '',
				custom_css_inline: $('.fplant-custom-css-inline').val() || '',
				// Embed settings
				embed_iframe_enabled: $('.fplant-setting-embed-iframe-enabled').is(':checked'),
				embed_iframe_allowed_urls: parseUrls($('.fplant-setting-embed-iframe-allowed-urls').val()),
				embed_js_enabled: $('.fplant-setting-embed-js-enabled').is(':checked'),
				embed_js_allowed_urls: parseUrls($('.fplant-setting-embed-js-allowed-urls').val()),
				// reCAPTCHA settings
				recaptcha_enabled: $('.fplant-setting-recaptcha-enabled').is(':checked'),
				recaptcha_version: $('input[name="recaptcha_version"]:checked').val() || 'v3',
				// URL parameter settings
				allow_url_params: $('.fplant-setting-allow-url-params').is(':checked')
			},
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

		const formId = $('.fplant-save-form').data('form-id') || 0;

		$.ajax({
			url: wpfplantAdminData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fplant_save_form',
				nonce: wpfplantAdminData.nonce,
				form_id: formId,
				form_data: JSON.stringify(formData)
			},
			success: function(response) {
				if (response.success) {
					// Reload page on save success
					if (response.data.form_id && !formId) {
						// Redirect to edit page for new forms
						window.location.href = wpfplantAdminData.editUrl + '&id=' + response.data.form_id + '&message=created';
					} else {
						// Reload for existing form updates
						var url = new URL(window.location.href);
						url.searchParams.set('message', 'updated');
						window.location.href = url.toString();
					}
				} else {
					showAdminNotice(response.data.message || wpfplantAdminData.i18n.errorOccurred, 'error');
				}
			},
			error: function() {
				showAdminNotice(wpfplantAdminData.i18n.errorOccurred, 'error');
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

			if (!confirm(wpfplantAdminData.i18n.confirmTrashForm)) {
				return;
			}

			const formId = $(this).data('form-id');

			$.ajax({
				url: wpfplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_trash_form',
					nonce: wpfplantAdminData.nonce,
					form_id: formId
				},
				success: function(response) {
					if (response.success) {
						window.location.href = wpfplantAdminData.listUrl;
					} else {
						alert(response.data.message || wpfplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					alert(wpfplantAdminData.i18n.errorOccurred);
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
				message: wpfplantAdminData.i18n.templateEmpty || 'HTML template is empty. Please add the required tags or uncheck "Use HTML template".'
			};
		}

		var missingItems = [];

		// Check for submit button
		if (template.indexOf('[fplant_submit') === -1) {
			missingItems.push(wpfplantAdminData.i18n.submitButton || 'Submit button');
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
				message: wpfplantAdminData.i18n.missingRequiredFields + '\n' + missingItems.join(', ')
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
				message: wpfplantAdminData.i18n.confirmationTemplateEmpty || 'Confirmation HTML template is empty. Please add the required tags or uncheck "Use confirmation screen HTML template".'
			};
		}

		// Check for submit button (required)
		if (template.indexOf('[fplant_confirm_submit') === -1) {
			return {
				success: false,
				message: wpfplantAdminData.i18n.confirmationSubmitRequired || 'Submit button [fplant_confirm_submit] is required in the confirmation template.'
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
			var text = $('#fplant-input-submit-text').val() || wpfplantAdminData.i18n.submit;
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
			var text = $('#fplant-confirmation-back-text').val() || wpfplantAdminData.i18n.back;
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
			var text = $('#fplant-confirmation-submit-text').val() || wpfplantAdminData.i18n.submitForm;
			$('.fplant-confirmation-submit-preview').text(text);
			$('#fplant-confirmation-submit-modal').removeClass('active');
			saveFormToDatabase();
		});
	}

	/**
	 * Custom CSS settings toggle
	 */
	function initCustomCssToggle() {
		$('input[name="custom_css_mode"]').on('change', function() {
			var mode = $(this).val();
			var $fields = $('.fplant-custom-css-fields');

			if (mode === 'none') {
				$fields.addClass('fplant-disabled');
				$fields.find('textarea').prop('readonly', true);
				$fields.find('button').prop('disabled', true);
			} else {
				$fields.removeClass('fplant-disabled');
				$fields.find('textarea').prop('readonly', false);
				$fields.find('button').prop('disabled', false);
			}
		});
	}

	/**
	 * Custom CSS file uploader (dedicated directory method)
	 */
	function initCustomCssFileUploader() {
		// Upload on file selection
		$('.fplant-css-file-input').on('change', function() {
			var $input = $(this);
			var file = this.files[0];

			if (!file) {
				return;
			}

			// Check if CSS file
			if (!file.name.endsWith('.css')) {
				alert(wpfplantAdminData.i18n.cssFileRequired);
				$input.val('');
				return;
			}

			var $status = $input.siblings('.fplant-css-upload-status');
			$status.text(wpfplantAdminData.i18n.uploading);

			// Get form ID
			var formId = 0;
			var urlParams = new URLSearchParams(window.location.search);
			if (urlParams.has('id')) {
				formId = parseInt(urlParams.get('id'), 10);
			}

			// Create FormData
			var formData = new FormData();
			formData.append('action', 'fplant_upload_css');
			formData.append('nonce', wpfplantAdminData.cssNonce);
			formData.append('css_file', file);
			formData.append('form_id', formId);

			// Ajax upload
			$.ajax({
				url: wpfplantAdminData.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						$('.fplant-custom-css-file-url').val(response.data.url);
						$status.text(wpfplantAdminData.i18n.uploadComplete + ' ' + response.data.filename);

						// Update current file display
						var $currentFile = $('.fplant-css-current-file');
						if ($currentFile.length === 0) {
							$currentFile = $('<div class="fplant-css-current-file" style="margin-top: 10px;"><strong>' + wpfplantAdminData.i18n.currentFile + '</strong> <code class="fplant-custom-css-file-url-display"></code> <button type="button" class="button button-small fplant-remove-css-file">' + wpfplantAdminData.i18n.delete + '</button></div>');
							$input.closest('.fplant-form-group').find('.fplant-css-upload-wrapper').after($currentFile);
							// Re-bind delete button event
							$currentFile.find('.fplant-remove-css-file').on('click', handleCssDelete);
						}
						$currentFile.find('.fplant-custom-css-file-url-display').text(response.data.filename);
						$currentFile.show();
					} else {
						$status.text(wpfplantAdminData.i18n.errorPrefix + ' ' + (response.data.message || wpfplantAdminData.i18n.uploadFailed));
					}
					$input.val('');
				},
				error: function() {
					$status.text(wpfplantAdminData.i18n.errorPrefix + ' ' + wpfplantAdminData.i18n.networkError);
					$input.val('');
				}
			});
		});

		// File delete button
		function handleCssDelete(e) {
			e.preventDefault();

			var fileUrl = $('.fplant-custom-css-file-url').val();
			if (!fileUrl) {
				return;
			}

			if (!confirm(wpfplantAdminData.i18n.confirmDeleteCss)) {
				return;
			}

			$.ajax({
				url: wpfplantAdminData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_delete_css',
					nonce: wpfplantAdminData.cssNonce,
					file_url: fileUrl
				},
				success: function(response) {
					if (response.success) {
						$('.fplant-custom-css-file-url').val('');
						$('.fplant-css-current-file').hide();
						$('.fplant-css-upload-status').text('');
					} else {
						alert(wpfplantAdminData.i18n.errorPrefix + ' ' + (response.data.message || wpfplantAdminData.i18n.deleteFailed));
					}
				},
				error: function() {
					alert(wpfplantAdminData.i18n.networkError);
				}
			});
		}

		$('.fplant-remove-css-file').on('click', handleCssDelete);
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
				url: wpfplantAdminData.ajaxUrl,
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
						alert(response.data.message || wpfplantAdminData.i18n.errorOccurred);
					}
				},
				error: function() {
					$spinner.removeClass('is-active');
					alert(wpfplantAdminData.i18n.errorOccurred);
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
		initFieldDelete();
		initFieldEdit();
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
		initCustomCssToggle();
		initCustomCssFileUploader();
		initEmbedSettings();
		initQuickEdit();

		// Load existing fields if any
		if (typeof wpfplantAdminData.formData !== 'undefined' && wpfplantAdminData.formData.fields) {
			formFields = wpfplantAdminData.formData.fields;
			renderFieldList();
		}
	});

})(jQuery);
