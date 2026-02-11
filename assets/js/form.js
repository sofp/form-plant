/**
 * Form Plant - Frontend JavaScript
 *
 * @package Form_Plant
 */

(function() {
	'use strict';

	/**
	 * Form handler
	 */
	class WPFPLANTFormHandler {
		constructor(form) {
			this.form = form;
			this.formId = form.dataset.formId;
			this.useConfirmation = form.dataset.useConfirmation === 'true' || form.dataset.useConfirmation === '1';
			this.isConfirmationStep = false;
			this.confirmation = null;
			// reCAPTCHA settings
			this.recaptchaConfig = window.wpfplantRecaptchaConfig && window.wpfplantRecaptchaConfig[this.formId]
				? window.wpfplantRecaptchaConfig[this.formId]
				: { enabled: false };
			this.init();
		}

		init() {
			this.form.addEventListener('submit', this.handleSubmit.bind(this));

			// Real-time validation (on blur)
			this.form.addEventListener('blur', (e) => {
				if (e.target.matches('input, textarea, select')) {
					this.handleFieldBlur(e);
				}
			}, true);

			this.form.addEventListener('change', (e) => {
				if (e.target.matches('input[type="checkbox"], input[type="radio"], input[type="file"]')) {
					this.handleFieldChange(e);
				}
				// Combine values when date (dropdown) changes
				if (e.target.matches('.fplant-date-select-year, .fplant-date-select-month, .fplant-date-select-day')) {
					this.handleDateSelectChange(e);
				}
			});
		}

		handleFieldBlur(e) {
			const field = e.target;
			const fieldName = this.getFieldName(field);

			if (fieldName) {
				this.validateField(fieldName);
			}
		}

		handleFieldChange(e) {
			const field = e.target;
			const fieldName = this.getFieldName(field);

			if (fieldName) {
				this.validateField(fieldName);
			}
		}

		getFieldName(field) {
			let name = field.getAttribute('name');
			if (!name) return null;

			// Remove [] from array-style names (name[])
			return name.replace('[]', '');
		}

		handleDateSelectChange(e) {
			const select = e.target;
			const fieldName = select.dataset.fieldName;

			if (!fieldName) return;

			// Get year/month/day selects with the same field name
			const dateGroup = select.closest('.fplant-field-date-select');
			const yearSelect = dateGroup.querySelector('.fplant-date-select-year');
			const monthSelect = dateGroup.querySelector('.fplant-date-select-month');
			const daySelect = dateGroup.querySelector('.fplant-date-select-day');
			const hiddenInput = dateGroup.querySelector('.fplant-date-select-value');

			// Get year/month/day values
			const year = yearSelect.value;
			const month = monthSelect.value;
			const day = daySelect.value;

			// Combine values only if all are selected
			if (year && month && day) {
				const dateValue = year + '-' + month + '-' + day;
				hiddenInput.value = dateValue;
			} else {
				hiddenInput.value = '';
			}

			// Validation
			this.validateField(fieldName);
		}

		handleSubmit(e) {
			e.preventDefault();

			// Do nothing if already loading
			if (this.form.classList.contains('fplant-loading')) {
				return false;
			}

			// Clear error messages
			this.clearMessages();

			// Run client-side validation
			if (!this.validateForm()) {
				return false;
			}

			// If confirmation is enabled and not yet in confirmation step
			if (this.useConfirmation && !this.isConfirmationStep) {
				// Run server-side validation then show confirmation screen
				this.validateServerSide();
				return false;
			}

			// Execute submission
			this.submitForm();

			return false;
		}

		validateForm() {
			let isValid = true;
			const fieldErrors = {};

			// Loop through all field groups
			const fieldGroups = this.form.querySelectorAll('.fplant-field-group');
			fieldGroups.forEach((group) => {
				const fieldName = group.dataset.fieldName;

				if (fieldName && !this.validateField(fieldName)) {
					isValid = false;

					// Collect error messages
					const errorEl = group.querySelector('.fplant-field-error');
					const errorMsg = errorEl ? errorEl.textContent : '';
					if (errorMsg) {
						fieldErrors[fieldName] = errorMsg;
					}
				}
			});

			// If there are errors
			if (!isValid) {
				// Only show error list if [fplant_errors] exists
				if (Object.keys(fieldErrors).length > 0 && this.form.querySelector('.fplant-errors')) {
					this.showErrors(wpfplantData.i18n.validationError, fieldErrors);
				}

				// Scroll to first error field
				const firstError = this.form.querySelector('.fplant-field-error[style*="block"]');
				if (firstError) {
					const fieldGroup = firstError.closest('.fplant-field-group');
					if (fieldGroup) {
						this.scrollToMessage(fieldGroup);
					}
				}
			}

			return isValid;
		}

		validateField(fieldName) {
			const group = this.form.querySelector('.fplant-field-group[data-field-name="' + fieldName + '"]');
			if (!group) return true;

			const errorContainer = group.querySelector('.fplant-field-error');
			// Also get standalone error display elements
			const standaloneErrors = this.form.querySelectorAll('[data-field-error="' + fieldName + '"]');
			const label = group.querySelector('label');
			const isRequired = label && label.querySelector('.required');

			// Clear errors
			if (errorContainer) {
				errorContainer.style.display = 'none';
				errorContainer.textContent = '';
			}
			standaloneErrors.forEach(el => {
				el.style.display = 'none';
				el.textContent = '';
			});
			group.classList.remove('fplant-field-has-error');

			if (!isRequired) {
				return true;
			}

			// Get fields
			const fields = this.form.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');

			if (!fields.length) return true;

			const firstField = fields[0];
			const fieldType = firstField.getAttribute('type') || firstField.tagName.toLowerCase();
			let value = null;
			let errorMessage = null;

			// Get custom validation message
			const customMessage = this.getCustomValidationMessage(fieldName);

			// Validate based on field type
			if (fieldType === 'checkbox') {
				// Checkbox: at least one checked
				let checked = 0;
				fields.forEach((field) => {
					if (field.checked) {
						checked++;
					}
				});
				if (checked === 0) {
					errorMessage = customMessage || wpfplantData.i18n.requiredCheckbox;
				}
			} else if (fieldType === 'radio') {
				// Radio button: one selected
				let checked = 0;
				fields.forEach((field) => {
					if (field.checked) {
						checked++;
					}
				});
				if (checked === 0) {
					errorMessage = customMessage || wpfplantData.i18n.requiredRadio;
				}
			} else if (fieldType === 'select' || fieldType === 'SELECT') {
				// Select box
				value = firstField.value;
				if (!value || value === '') {
					errorMessage = customMessage || wpfplantData.i18n.requiredSelect;
				}
			} else if (fieldType === 'file') {
				// File upload
				const file = firstField.files && firstField.files[0];
				if (!file) {
					errorMessage = customMessage || wpfplantData.i18n.requiredFile;
				} else {
					// File size check
					const maxSize = parseInt(firstField.dataset.maxSize) || 2097152; // Default 2MB
					if (file.size > maxSize) {
						const maxSizeMB = (maxSize / 1048576).toFixed(1);
						errorMessage = wpfplantData.i18n.fileTooLarge.replace('%s', maxSizeMB);
					}

					// File type check
					const accept = firstField.getAttribute('accept');
					if (accept && accept.indexOf('image/*') !== -1) {
						// If image only
						if (!file.type.startsWith('image/')) {
							errorMessage = wpfplantData.i18n.imageRequired;
						}
					}
				}
			} else {
				// Text fields
				value = firstField.value;
				if (!value || value.trim() === '') {
					errorMessage = customMessage || wpfplantData.i18n.requiredText;
				}
			}

			// Display error message
			if (errorMessage) {
				if (errorContainer) {
					errorContainer.textContent = errorMessage;
					errorContainer.style.display = 'block';
				}
				standaloneErrors.forEach(el => {
					el.textContent = errorMessage;
					el.style.display = 'block';
				});
				group.classList.add('fplant-field-has-error');
				return false;
			}

			return true;
		}

		validateServerSide() {
			// Set loading state
			this.setLoading(true);

			// Get form data
			const formData = this.getFormData();

			// Use FormData if there are file uploads
			const hasFiles = this.form.querySelectorAll('input[type="file"]').length > 0;

			let body;
			let headers = {};

			if (hasFiles) {
				// Create FormData object
				body = new FormData();
				body.append('action', 'fplant_validate_form');
				body.append('nonce', wpfplantData.nonce);
				body.append('form_id', this.formId);
				body.append('data', JSON.stringify(formData));

				// Add file fields
				this.form.querySelectorAll('input[type="file"]').forEach((input) => {
					const file = input.files[0];
					if (file) {
						body.append(input.name, file);
					}
				});
			} else {
				// Normal data submission
				headers['Content-Type'] = 'application/x-www-form-urlencoded';
				body = new URLSearchParams({
					action: 'fplant_validate_form',
					nonce: wpfplantData.nonce,
					form_id: this.formId,
					data: JSON.stringify(formData)
				});
			}

			// Fetch submission
			fetch(wpfplantData.ajaxUrl, {
				method: 'POST',
				headers: headers,
				body: body
			})
			.then(response => response.json())
			.then(response => {
				this.setLoading(false);
				if (response.success) {
					// Validation success, show confirmation screen with server-generated HTML
					this.showConfirmation(response.data.confirmation_html);
				} else {
					// Validation error
					const errors = response.data.errors || {};
					const message = response.data.message || wpfplantData.i18n.validationError;
					// Only show overall error if [fplant_errors] exists
					if (this.form.querySelector('.fplant-errors')) {
						this.showErrors(message, errors);
					}
					// Show field-specific errors
					this.showFieldErrors(errors);
				}
			})
			.catch(error => {
				this.setLoading(false);
				this.showErrors(wpfplantData.i18n.serverError);
			});
		}

		showFieldErrors(fieldErrors) {
			// Display individual field errors
			Object.keys(fieldErrors).forEach(fieldName => {
				let errorDisplayed = false;

				// Legacy method: .fplant-field-error inside .fplant-field-group
				const group = this.form.querySelector('.fplant-field-group[data-field-name="' + fieldName + '"]');
				if (group) {
					const errorContainer = group.querySelector('.fplant-field-error');
					if (errorContainer) {
						errorContainer.textContent = fieldErrors[fieldName];
						errorContainer.style.display = 'block';
						errorDisplayed = true;
					}
					group.classList.add('fplant-field-has-error');
				}

				// New method: elements with data-field-error attribute
				const standaloneErrors = this.form.querySelectorAll('[data-field-error="' + fieldName + '"]');
				if (standaloneErrors.length) {
					standaloneErrors.forEach(el => {
						el.textContent = fieldErrors[fieldName];
						el.style.display = 'block';
					});
					errorDisplayed = true;
				}

				// If no error display element exists, dynamically add one after the input field
				if (!errorDisplayed) {
					const fields = this.form.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');
					const lastField = fields[fields.length - 1];
					if (lastField) {
						// Reuse existing dynamically added error element if present
						let dynamicError = lastField.nextElementSibling;
						if (!dynamicError || !dynamicError.classList.contains('fplant-field-error-dynamic')) {
							dynamicError = document.createElement('div');
							dynamicError.className = 'fplant-field-error fplant-field-error-dynamic';
							dynamicError.dataset.fieldError = fieldName;
							lastField.insertAdjacentElement('afterend', dynamicError);
						}
						dynamicError.textContent = fieldErrors[fieldName];
						dynamicError.style.display = 'block';
					}
				}
			});

			// Scroll to first error field
			const firstError = this.form.querySelector('.fplant-field-error[style*="block"]');
			if (firstError) {
				const fieldGroup = firstError.closest('.fplant-field-group');
				this.scrollToMessage(fieldGroup || firstError);
			}
		}

		showConfirmation(serverHtml) {
			// Use server-generated HTML if provided, otherwise fallback to client-side generation
			let confirmationHtml;
			if (serverHtml) {
				confirmationHtml = serverHtml;
			} else {
				// Fallback: generate confirmation screen HTML on client-side
				const formData = this.getFormData();
				confirmationHtml = this.buildConfirmationHtml(formData);
			}

			// Create and display confirmation screen
			this.confirmation = document.createElement('div');
			this.confirmation.className = 'fplant-confirmation';
			this.confirmation.innerHTML = confirmationHtml;
			this.form.insertAdjacentElement('afterend', this.confirmation);
			this.form.style.display = 'none';

			// Set up event listeners for confirmation screen buttons
			const backButton = this.confirmation.querySelector('.fplant-back-button');
			if (backButton) {
				backButton.addEventListener('click', () => {
					this.hideConfirmation();
				});
			}

			const submitButton = this.confirmation.querySelector('.fplant-confirm-submit-button');
			if (submitButton) {
				submitButton.addEventListener('click', () => {
					// Set confirmation step to true before executing submission
					this.isConfirmationStep = true;
					// Execute submission while keeping confirmation screen (removed on completion)
					this.submitForm();
				});
			}

			// Scroll to top
			this.scrollToMessage(this.confirmation);
		}

		hideConfirmation() {
			if (this.confirmation) {
				this.confirmation.remove();
				this.confirmation = null;
			}
			this.form.style.display = '';
		}

		async submitForm() {
			// Get form data
			const formData = this.getFormData();

			// Set loading state
			this.setLoading(true);

			// Get reCAPTCHA v3 token
			let recaptchaToken = '';
			if (this.recaptchaConfig.enabled && this.recaptchaConfig.version === 'v3') {
				try {
					recaptchaToken = await this.getRecaptchaV3Token();
					// Set token in hidden field
					const tokenInput = this.form.querySelector('.fplant-recaptcha-token');
					if (tokenInput) {
						tokenInput.value = recaptchaToken;
					}
				} catch (error) {
					this.setLoading(false);
					this.showErrors(wpfplantData.i18n.recaptchaError);
					return;
				}
			}

			// Use FormData if there are file uploads
			const hasFiles = this.form.querySelectorAll('input[type="file"]').length > 0;

			let body;
			let headers = {};

			if (hasFiles) {
				// Create FormData object
				body = new FormData();
				body.append('action', 'fplant_submit_form');
				body.append('nonce', wpfplantData.nonce);
				body.append('form_id', this.formId);
				body.append('data', JSON.stringify(formData));

				// Add reCAPTCHA token
				if (recaptchaToken) {
					body.append('fplant_recaptcha_token', recaptchaToken);
				}

				// Add file fields
				this.form.querySelectorAll('input[type="file"]').forEach((input) => {
					const file = input.files[0];
					if (file) {
						body.append(input.name, file);
					}
				});
			} else {
				// Normal data submission
				headers['Content-Type'] = 'application/x-www-form-urlencoded';
				const params = {
					action: 'fplant_submit_form',
					nonce: wpfplantData.nonce,
					form_id: this.formId,
					data: JSON.stringify(formData)
				};

				// Add reCAPTCHA token
				if (recaptchaToken) {
					params.fplant_recaptcha_token = recaptchaToken;
				}

				body = new URLSearchParams(params);
			}

			// Fetch submission
			fetch(wpfplantData.ajaxUrl, {
				method: 'POST',
				headers: headers,
				body: body
			})
			.then(response => response.json())
			.then(response => {
				this.setLoading(false);
				this.isConfirmationStep = false;
				this.handleSuccess(response);
			})
			.catch(error => {
				this.setLoading(false);
				this.isConfirmationStep = false;
				this.handleError(error);
			});
		}

		/**
		 * Get reCAPTCHA v3 token
		 * @returns {Promise<string>}
		 */
		getRecaptchaV3Token() {
			return new Promise((resolve, reject) => {
				if (typeof grecaptcha === 'undefined') {
					reject(new Error('reCAPTCHA not loaded'));
					return;
				}

				grecaptcha.ready(() => {
					grecaptcha.execute(this.recaptchaConfig.siteKey, { action: 'submit' })
						.then(token => resolve(token))
						.catch(error => reject(error));
				});
			});
		}

		buildConfirmationHtml(formData) {
			const title = this.form.dataset.confirmationTitle || wpfplantData.i18n.confirmationTitle;
			const message = this.form.dataset.confirmationMessage || wpfplantData.i18n.confirmationMessage;

			// Get button text and attributes
			const buttonTexts = window.wpfplantConfirmationButtons && window.wpfplantConfirmationButtons[this.formId];
			const backText = buttonTexts ? buttonTexts.back : wpfplantData.i18n.back;
			const backClass = buttonTexts ? buttonTexts.back_class : '';
			const backId = buttonTexts ? buttonTexts.back_id : '';
			const submitText = buttonTexts ? buttonTexts.submit : wpfplantData.i18n.submitForm;
			const submitClass = buttonTexts ? buttonTexts.submit_class : '';
			const submitId = buttonTexts ? buttonTexts.submit_id : '';

			// Use custom template if available
			const customTemplate = window.wpfplantConfirmationTemplate && window.wpfplantConfirmationTemplate[this.formId];
			if (customTemplate) {
				return this.renderConfirmationTemplate(customTemplate, formData, title, message, buttonTexts);
			}

			// Default template
			let html = `
				<div class="fplant-confirmation-header">
					<h3>${this.escapeHtml(title)}</h3>
					<p>${this.escapeHtml(message)}</p>
				</div>
				<div class="fplant-confirmation-body">
					<table class="fplant-confirmation-table">
			`;

			// Display each field value
			Object.keys(formData).forEach(fieldName => {
				// Skip WordPress internal fields and nonce fields
				if (fieldName.startsWith('_wp') || fieldName.startsWith('_wpnonce')) {
					return;
				}

				const fieldLabel = this.getFieldLabel(fieldName);
				let fieldValue = formData[fieldName];

				// Get filename for file fields
				const fileField = this.form.querySelector(`input[type="file"][name="${fieldName}"]`);
				if (fileField) {
					// File field: show filename if selected, otherwise empty
					if (fileField.files && fileField.files.length > 0) {
						fieldValue = fileField.files[0].name;
					} else {
						fieldValue = '';
					}
				}

				// Convert value to label for select/radio/checkbox fields
				if (this.isChoiceField(fieldName)) {
					fieldValue = this.getOptionLabel(fieldName, fieldValue);
				}

				// Join array values with comma
				if (Array.isArray(fieldValue)) {
					fieldValue = fieldValue.join(', ');
				}

				// Escape value then convert newlines to <br>
				const escapedValue = this.escapeHtml(fieldValue || '-');
				const displayValue = escapedValue.replace(/\n/g, '<br>');

				html += `
					<tr>
						<th>${this.escapeHtml(fieldLabel)}</th>
						<td>${displayValue}</td>
					</tr>
				`;
			});

			html += `
					</table>
				</div>
				<div class="fplant-confirmation-footer">
					${this.buildButtonHtml('fplant-back-button', backText, backClass, backId)}
					${this.buildButtonHtml('fplant-confirm-submit-button', submitText, submitClass, submitId)}
				</div>
			`;

			return html;
		}

		getFieldLabel(fieldName) {
			// First, try to get label from wpfplantFieldsConfig
			const fieldsConfig = window.wpfplantFieldsConfig && window.wpfplantFieldsConfig[this.formId];
			if (fieldsConfig) {
				const fieldConfig = fieldsConfig.find(f => f.name === fieldName);
				if (fieldConfig && fieldConfig.label) {
					return fieldConfig.label;
				}
			}

			const field = this.form.querySelector(`[name="${fieldName}"], [name="${fieldName}[]"]`);
			if (!field) return fieldName;

			// Find label tag
			const fieldId = field.getAttribute('id');
			if (fieldId) {
				const label = this.form.querySelector(`label[for="${fieldId}"]`);
				if (label) {
					return label.textContent.trim();
				}
			}

			// Find parent label element
			const parentLabel = field.closest('label');
			if (parentLabel) {
				// Get only direct text nodes from label
				const clone = parentLabel.cloneNode(true);
				// Remove child elements
				while (clone.firstElementChild) {
					clone.removeChild(clone.firstElementChild);
				}
				return clone.textContent.trim();
			}

			// Use field name as-is
			return fieldName;
		}

		/**
		 * Convert option value(s) to label(s) for select/radio/checkbox fields
		 */
		getOptionLabel(fieldName, value) {
			const fieldsConfig = window.wpfplantFieldsConfig && window.wpfplantFieldsConfig[this.formId];
			if (!fieldsConfig) {
				return value;
			}

			const fieldConfig = fieldsConfig.find(f => f.name === fieldName);
			if (!fieldConfig || !fieldConfig.options || !Array.isArray(fieldConfig.options)) {
				return value;
			}

			// Handle array values (checkbox)
			if (Array.isArray(value)) {
				return value.map(v => {
					// Compare as strings to avoid type mismatch
					const option = fieldConfig.options.find(opt => String(opt.value) === String(v));
					return option ? option.label : v;
				});
			}

			// Handle single value (select/radio)
			// Compare as strings to avoid type mismatch
			const option = fieldConfig.options.find(opt => String(opt.value) === String(value));
			return option ? option.label : value;
		}

		/**
		 * Check if field is select, radio, or checkbox type
		 */
		isChoiceField(fieldName) {
			const fieldsConfig = window.wpfplantFieldsConfig && window.wpfplantFieldsConfig[this.formId];
			if (!fieldsConfig) {
				return false;
			}

			const fieldConfig = fieldsConfig.find(f => f.name === fieldName);
			return fieldConfig && ['select', 'radio', 'checkbox'].includes(fieldConfig.type);
		}

		getFormData() {
			const data = {};
			const formData = new FormData(this.form);

			for (const [key, value] of formData.entries()) {
				// Skip fields starting with wpfplant
				if (key.indexOf('fplant_') === 0) {
					continue;
				}

				// Skip individual date dropdown fields (fieldname[year], etc.)
				if (key.match(/\[(year|month|day)\]$/)) {
					continue;
				}

				// Remove [] from field name
				let fieldName = key;
				const isArray = fieldName.endsWith('[]');
				if (isArray) {
					fieldName = fieldName.replace('[]', '');
				}

				// For array-style names like checkboxes
				if (isArray) {
					if (!data[fieldName]) {
						data[fieldName] = [];
					}
					data[fieldName].push(value);
				} else if (data[fieldName] !== undefined) {
					// When multiple fields have the same name (radio buttons, etc.)
					if (!Array.isArray(data[fieldName])) {
						data[fieldName] = [data[fieldName]];
					}
					data[fieldName].push(value);
				} else {
					data[fieldName] = value;
				}
			}

			return data;
		}

		handleSuccess(response) {
			if (response.success) {
				// Branch processing based on action type
				const actionType = response.data.action_type || 'message';

				if (actionType === 'redirect') {
					// Redirect
					if (response.data.redirect_url) {
						window.location.href = response.data.redirect_url;
					} else {
						// Show message if URL is not set
						this.showSuccess(response.data.message);
					}
				} else if (actionType === 'custom_page') {
					// Show custom HTML page
					if (response.data.success_page_html) {
						this.showCustomSuccessPage(response.data.success_page_html);
					} else {
						// Show message if HTML is not set
						this.showSuccess(response.data.message);
					}
				} else {
					// Show message only
					this.showSuccess(response.data.message);
				}

				// Clear form
				this.form.reset();

				// Dispatch custom event
				this.form.dispatchEvent(new CustomEvent('wpfplant:success', { detail: response.data }));
			} else {
				this.showErrors(response.data.message, response.data.errors);
			}
		}

		handleError(error) {
			this.showErrors(wpfplantData.i18n.errorOccurred);
		}

		showSuccess(message) {
			// Remove confirmation screen if present
			if (this.confirmation) {
				this.confirmation.remove();
				this.confirmation = null;
			}

			// Show form
			this.form.style.display = '';

			const successEl = this.form.querySelector('.fplant-success');
			if (successEl) {
				successEl.innerHTML = '<p>' + this.escapeHtml(message) + '</p>';
				successEl.style.display = 'block';

				// Scroll to top
				this.scrollToMessage(successEl);
			}
		}

		showCustomSuccessPage(html) {
			// Remove confirmation screen if present
			if (this.confirmation) {
				this.confirmation.remove();
				this.confirmation = null;
			}

			// Hide form
			this.form.style.display = 'none';

			// Show custom success page
			const customPage = document.createElement('div');
			customPage.className = 'fplant-custom-success-page';
			customPage.innerHTML = html;
			this.form.insertAdjacentElement('afterend', customPage);

			// Scroll to top
			this.scrollToMessage(customPage);
		}

		showErrors(message, fieldErrors = {}) {
			// Remove confirmation screen if present and show form
			if (this.confirmation) {
				this.confirmation.remove();
				this.confirmation = null;
				this.form.style.display = '';
			}

			const errorsEl = this.form.querySelector('.fplant-errors');
			if (!errorsEl) return;

			let html = '<p>' + this.escapeHtml(message) + '</p>';

			// Don't show field error list if data-show-field-errors="false"
			// (For default layout: individual errors are shown below each field)
			const showFieldErrors = errorsEl.getAttribute('data-show-field-errors') !== 'false';

			if (showFieldErrors && Object.keys(fieldErrors).length > 0) {
				html += '<ul>';
				Object.keys(fieldErrors).forEach(fieldName => {
					html += '<li>' + this.escapeHtml(fieldErrors[fieldName]) + '</li>';
				});
				html += '</ul>';
			}

			errorsEl.innerHTML = html;
			errorsEl.style.display = 'block';

			// Scroll to top
			this.scrollToMessage(errorsEl);
		}

		clearMessages() {
			const successEl = this.form.querySelector('.fplant-success');
			const errorsEl = this.form.querySelector('.fplant-errors');

			if (successEl) {
				successEl.style.display = 'none';
				successEl.innerHTML = '';
			}
			if (errorsEl) {
				errorsEl.style.display = 'none';
				errorsEl.innerHTML = '';
			}

			// Clear individual field errors
			this.form.querySelectorAll('.fplant-field-error').forEach(el => {
				el.style.display = 'none';
				el.textContent = '';
			});
			this.form.querySelectorAll('.fplant-field-group').forEach(el => {
				el.classList.remove('fplant-field-has-error');
			});
			// Clear elements with data-field-error attribute
			this.form.querySelectorAll('[data-field-error]').forEach(el => {
				el.style.display = 'none';
				el.textContent = '';
			});
			// Remove dynamically added error elements
			this.form.querySelectorAll('.fplant-field-error-dynamic').forEach(el => {
				el.remove();
			});
		}

		setLoading(loading) {
			if (loading) {
				this.form.classList.add('fplant-loading');
				const submitBtn = this.form.querySelector('.fplant-submit-button');
				if (submitBtn) {
					submitBtn.disabled = true;
				}
			} else {
				this.form.classList.remove('fplant-loading');
				const submitBtn = this.form.querySelector('.fplant-submit-button');
				if (submitBtn) {
					submitBtn.disabled = false;
				}
			}
		}

		scrollToMessage(element) {
			if (!element) return;
			element.scrollIntoView({ behavior: 'smooth', block: 'start' });
			// Adjust offset (for fixed headers, etc.)
			setTimeout(() => {
				window.scrollBy(0, -100);
			}, 100);
		}

		escapeHtml(text) {
			if (text === null || text === undefined) return '';
			const div = document.createElement('div');
			div.textContent = String(text);
			return div.innerHTML;
		}

		getCustomValidationMessage(fieldName) {
			const formId = this.form.dataset.formId;
			if (!formId || !window.wpfplantFieldsConfig || !window.wpfplantFieldsConfig[formId]) {
				return null;
			}

			const fields = window.wpfplantFieldsConfig[formId];
			const field = fields.find(f => f.name === fieldName);

			if (field && field.validation_message && field.validation_message.trim() !== '') {
				return field.validation_message;
			}

			return null;
		}

		renderConfirmationTemplate(template, formData, title, message, buttonTexts) {
			let html = template;

			// Replace [fplant_confirmation_title] tag
			html = html.replace(/\[fplant_confirmation_title\]/g, this.escapeHtml(title));

			// Replace [fplant_confirmation_message] tag
			html = html.replace(/\[fplant_confirmation_message\]/g, this.escapeHtml(message));

			// Replace [fplant_all_fields] tag
			html = html.replace(/\[fplant_all_fields\]/g, this.buildAllFieldsHtml(formData));

			// Replace [fplant_value name="fieldname"] tags
			const valueMatches = html.match(/\[fplant_value\s+name="([^"]+)"\]/g);
			if (valueMatches) {
				valueMatches.forEach(match => {
					const nameMatch = match.match(/name="([^"]+)"/);
					if (nameMatch) {
						const fieldName = nameMatch[1];
						let fieldValue = formData[fieldName] || '';

						// Get filename for file fields
						const fileField = this.form.querySelector(`input[type="file"][name="${fieldName}"]`);
						if (fileField) {
							// File field: show filename if selected, otherwise empty
							if (fileField.files && fileField.files.length > 0) {
								fieldValue = fileField.files[0].name;
							} else {
								fieldValue = '';
							}
						}

						// Convert value to label for select/radio/checkbox fields
						if (this.isChoiceField(fieldName)) {
							fieldValue = this.getOptionLabel(fieldName, fieldValue);
						}

						if (Array.isArray(fieldValue)) {
							fieldValue = fieldValue.join(', ');
						}

						html = html.replace(match, this.escapeHtml(fieldValue));
					}
				});
			}

			// Get button text and attributes
			const backText = buttonTexts ? buttonTexts.back : wpfplantData.i18n.back;
			const backClass = buttonTexts ? buttonTexts.back_class : '';
			const backId = buttonTexts ? buttonTexts.back_id : '';
			const submitText = buttonTexts ? buttonTexts.submit : wpfplantData.i18n.submitForm;
			const submitClass = buttonTexts ? buttonTexts.submit_class : '';
			const submitId = buttonTexts ? buttonTexts.submit_id : '';

			// Replace [fplant_back] tag (with optional text attribute)
			html = html.replace(/\[fplant_back(\s+text="([^"]*)")?\]/g, (match, _, customText) => {
				const text = customText || backText;
				return this.buildButtonHtml('fplant-back-button', text, backClass, backId);
			});

			// Replace [fplant_confirm_submit] tag (with optional text attribute)
			html = html.replace(/\[fplant_confirm_submit(\s+text="([^"]*)")?\]/g, (match, _, customText) => {
				const text = customText || submitText;
				return this.buildButtonHtml('fplant-confirm-submit-button', text, submitClass, submitId);
			});

			return html;
		}

		buildButtonHtml(baseClass, text, customClass, customId) {
			let classList = baseClass;
			if (customClass) {
				classList += ' ' + this.escapeHtml(customClass);
			}

			let html = '<button type="button" class="' + classList + '"';
			if (customId) {
				html += ' id="' + this.escapeHtml(customId) + '"';
			}
			html += '>' + this.escapeHtml(text) + '</button>';

			return html;
		}

		buildAllFieldsHtml(formData) {
			let fieldsHtml = '<table class="fplant-confirmation-table">';

			Object.keys(formData).forEach(fieldName => {
				// Skip WordPress internal fields and nonce fields
				if (fieldName.startsWith('_wp') || fieldName.startsWith('_wpnonce')) {
					return;
				}

				const fieldLabel = this.getFieldLabel(fieldName);
				let fieldValue = formData[fieldName];

				// Get filename for file fields
				const fileField = this.form.querySelector(`input[type="file"][name="${fieldName}"]`);
				if (fileField) {
					// File field: show filename if selected, otherwise empty
					if (fileField.files && fileField.files.length > 0) {
						fieldValue = fileField.files[0].name;
					} else {
						fieldValue = '';
					}
				}

				// Convert value to label for select/radio/checkbox fields
				if (this.isChoiceField(fieldName)) {
					fieldValue = this.getOptionLabel(fieldName, fieldValue);
				}

				// Join array values with comma
				if (Array.isArray(fieldValue)) {
					fieldValue = fieldValue.join(', ');
				}

				// Escape value then convert newlines to <br>
				const escapedValue = this.escapeHtml(fieldValue || '-');
				const displayValue = escapedValue.replace(/\n/g, '<br>');

				fieldsHtml += `
					<tr>
						<th>${this.escapeHtml(fieldLabel)}</th>
						<td>${displayValue}</td>
					</tr>
				`;
			});

			fieldsHtml += '</table>';
			return fieldsHtml;
		}
	}

	/**
	 * Initialize
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initForms);
	} else {
		initForms();
	}

	function initForms() {
		document.querySelectorAll('.fplant-form').forEach(function(form) {
			new WPFPLANTFormHandler(form);
		});
	}

})();
