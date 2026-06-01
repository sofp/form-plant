/**
 * Form Plant - Frontend JavaScript
 *
 * @package Form_Plant
 */

(function() {
	'use strict';

	// Global API for custom validators
	window.fplant = window.fplant || {};
	window.fplant.validators = window.fplant.validators || {};
	window.fplant.addValidator = function(fieldName, callback) {
		if (typeof fieldName !== 'string' || typeof callback !== 'function') {
			return;
		}
		if (!this.validators[fieldName]) {
			this.validators[fieldName] = [];
		}
		this.validators[fieldName].push(callback);
	};
	window.fplant.removeValidator = function(fieldName, callback) {
		if (!this.validators[fieldName]) {
			return;
		}
		if (callback) {
			this.validators[fieldName] = this.validators[fieldName].filter(function(v) {
				return v !== callback;
			});
		} else {
			delete this.validators[fieldName];
		}
	};

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
			// i18n messages
			this.i18n = (window.fplantData && window.fplantData.i18n) ? window.fplantData.i18n : {};
			// CAPTCHA settings
			this.captchaConfig = window.fplantCaptchaConfig && window.fplantCaptchaConfig[this.formId]
				? window.fplantCaptchaConfig[this.formId]
				: { type: 'none' };
			// Backward compatibility
			if (!this.captchaConfig.type && this.captchaConfig.enabled) {
				this.captchaConfig.type = 'recaptcha';
			}
			this.init();
		}

		init() {
			this.form.addEventListener('submit', this.handleSubmit.bind(this));
			this.turnstileWidgetId = null;
			this.recaptchaV2WidgetId = null;

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

			// Combine values when name parts input changes
			this.form.addEventListener('input', (e) => {
				if (e.target.matches('.fplant-name-input')) {
					this.handleNamePartsChange(e);
				}
				// Combine postal code split values
				if (e.target.matches('.fplant-postal-code-part1, .fplant-postal-code-part2')) {
					this.handlePostalCodeSplitChange(e);
				}
				// Combine tel split values
				if (e.target.matches('.fplant-tel-part1, .fplant-tel-part2, .fplant-tel-part3')) {
					this.handleTelSplitChange(e);
				}
			});

			// Postal code search button
			this.form.addEventListener('click', (e) => {
				if (e.target.matches('.fplant-postal-code-search')) {
					this.handlePostalCodeSearch(e);
				}
			});

			// Auto-search when 7 digits entered
			this.form.addEventListener('input', (e) => {
				if (e.target.matches('.fplant-postal-code-full')) {
					this.handlePostalCodeAutoSearch(e);
				}
				if (e.target.matches('.fplant-postal-code-part2')) {
					this.handlePostalCodeSplitAutoSearch(e);
				}
			});

			// Auto-focus from part1 to part2
			this.form.addEventListener('input', (e) => {
				if (e.target.matches('.fplant-postal-code-part1') && e.target.value.length >= 3) {
					const container = e.target.closest('.fplant-postal-code-split');
					if (container) {
						const part2 = container.querySelector('.fplant-postal-code-part2');
						if (part2) part2.focus();
					}
				}
			});

			// Auto-focus between tel split boxes when a box is filled to its maxlength
			this.form.addEventListener('input', (e) => {
				const container = e.target.closest('.fplant-tel-split');
				if (!container) return;
				const maxlength = parseInt(e.target.getAttribute('maxlength'), 10);
				if (!maxlength || e.target.value.length < maxlength) return;
				if (e.target.matches('.fplant-tel-part1')) {
					const part2 = container.querySelector('.fplant-tel-part2');
					if (part2) part2.focus();
				} else if (e.target.matches('.fplant-tel-part2')) {
					const part3 = container.querySelector('.fplant-tel-part3');
					if (part3) part3.focus();
				}
			});

			// Set timestamp for time-based spam check
			const tsField = this.form.querySelector('.fplant-form-ts');
			if (tsField) {
				tsField.value = Math.floor(Date.now() / 1000);
			}

			// Render reCAPTCHA v2 widget on page load (when no confirmation screen)
			if (this.captchaConfig.type === 'recaptcha_v2' && !this.useConfirmation) {
				this.renderRecaptchaV2Widget(this.form);
			}

			// Render Turnstile widget on page load (when no confirmation screen)
			if (this.captchaConfig.type === 'turnstile' && !this.useConfirmation) {
				this.renderTurnstileWidget(this.form);
			}

			// Initialize password features
			this.initPasswordToggles();
			this.initPasswordStrengthMeters();

			// Dispatch init event
			this.dispatchFplantEvent('fplant:init', {
				formId: this.formId,
				form: this.form
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

		handleNamePartsChange(e) {
			const input = e.target;
			const nameGroup = input.closest('.fplant-field-name-parts, .fplant-field-name-kana');
			if (!nameGroup) return;

			const fieldGroup = nameGroup.closest('.fplant-field-group');
			const fieldName = fieldGroup ? fieldGroup.dataset.fieldName : null;
			if (!fieldName) return;

			const hiddenInput = nameGroup.querySelector('.fplant-name-parts-value');
			if (!hiddenInput) return;

			// Collect values in DOM order
			const inputs = nameGroup.querySelectorAll('.fplant-name-input');
			const parts = [];
			inputs.forEach(inp => {
				const val = inp.value.trim();
				if (val) {
					parts.push(val);
				}
			});

			hiddenInput.value = parts.join(' ');

			// Validation
			this.validateField(fieldName);
		}

		/**
		 * Handle postal code split input change — combine part1+part2 into hidden field
		 */
		handlePostalCodeSplitChange(e) {
			const input = e.target;
			const container = input.closest('.fplant-postal-code-split');
			if (!container) return;

			const wrapper = container.closest('.fplant-field-postal-code, .fplant-address-postal-code');
			if (!wrapper) return;

			const part1 = container.querySelector('.fplant-postal-code-part1');
			const part2 = container.querySelector('.fplant-postal-code-part2');
			const hidden = wrapper.querySelector('.fplant-postal-code-value, .fplant-address-postal-code-value');
			if (!hidden) return;

			const v1 = (part1 ? part1.value : '').replace(/[^0-9]/g, '');
			const v2 = (part2 ? part2.value : '').replace(/[^0-9]/g, '');

			if (v1 && v2) {
				hidden.value = v1 + '-' + v2;
			} else if (v1) {
				hidden.value = v1;
			} else {
				hidden.value = '';
			}
		}

		/**
		 * Handle tel split input change — combine part1-part2-part3 into hidden field
		 */
		handleTelSplitChange(e) {
			const input = e.target;
			const wrapper = input.closest('.fplant-field-tel');
			if (!wrapper) return;

			const part1 = wrapper.querySelector('.fplant-tel-part1');
			const part2 = wrapper.querySelector('.fplant-tel-part2');
			const part3 = wrapper.querySelector('.fplant-tel-part3');
			const hidden = wrapper.querySelector('.fplant-tel-value');
			if (!hidden) return;

			const v1 = (part1 ? part1.value : '').replace(/[^0-9]/g, '');
			const v2 = (part2 ? part2.value : '').replace(/[^0-9]/g, '');
			const v3 = (part3 ? part3.value : '').replace(/[^0-9]/g, '');

			if (v1 || v2 || v3) {
				hidden.value = [v1, v2, v3].join('-');
			} else {
				hidden.value = '';
			}
		}

		/**
		 * Handle postal code search button click
		 */
		handlePostalCodeSearch(e) {
			const button = e.target;
			const wrapper = button.closest('.fplant-field-postal-code, .fplant-address-postal-code');
			if (!wrapper) return;

			this.searchPostalCode(wrapper);
		}

		/**
		 * Auto-search when single input reaches 7 digits
		 */
		handlePostalCodeAutoSearch(e) {
			const input = e.target;
			const clean = input.value.replace(/[^0-9]/g, '');
			if (clean.length === 7) {
				const wrapper = input.closest('.fplant-field-postal-code, .fplant-address-postal-code');
				if (wrapper) {
					this.searchPostalCode(wrapper);
				}
			}
		}

		/**
		 * Auto-search when split part2 reaches 4 digits
		 */
		handlePostalCodeSplitAutoSearch(e) {
			const input = e.target;
			if (input.value.length === 4) {
				const container = input.closest('.fplant-postal-code-split');
				const part1 = container ? container.querySelector('.fplant-postal-code-part1') : null;
				if (part1 && part1.value.length === 3) {
					const wrapper = container.closest('.fplant-field-postal-code, .fplant-address-postal-code');
					if (wrapper) {
						this.searchPostalCode(wrapper);
					}
				}
			}
		}

		/**
		 * Search address by postal code using zipcloud API
		 */
		searchPostalCode(wrapper) {
			// Get postal code value
			let zipcode = '';
			const fullInput = wrapper.querySelector('.fplant-postal-code-full, .fplant-address-postal-code-value');
			const splitContainer = wrapper.querySelector('.fplant-postal-code-split');

			if (splitContainer) {
				const part1 = splitContainer.querySelector('.fplant-postal-code-part1');
				const part2 = splitContainer.querySelector('.fplant-postal-code-part2');
				zipcode = (part1 ? part1.value : '') + (part2 ? part2.value : '');
			} else if (fullInput) {
				zipcode = fullInput.value;
			}

			zipcode = zipcode.replace(/[^0-9]/g, '');
			if (zipcode.length !== 7) return;

			const msgEl = wrapper.querySelector('.fplant-postal-code-message');
			const searchBtn = wrapper.querySelector('.fplant-postal-code-search');

			if (searchBtn) searchBtn.disabled = true;
			if (msgEl) {
				msgEl.textContent = this.i18n.searchingAddress || 'Searching...';
				msgEl.style.display = 'inline';
				msgEl.className = 'fplant-postal-code-message';
			}

			fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + zipcode)
				.then(res => res.json())
				.then(data => {
					if (searchBtn) searchBtn.disabled = false;

					if (data.results && data.results.length > 0) {
						const result = data.results[0];
						this.fillAddressFromPostalCode(wrapper, result);
						if (msgEl) msgEl.style.display = 'none';
					} else {
						if (msgEl) {
							msgEl.textContent = this.i18n.addressNotFound || 'Address not found';
							msgEl.className = 'fplant-postal-code-message fplant-postal-code-error';
						}
					}
				})
				.catch(() => {
					if (searchBtn) searchBtn.disabled = false;
					if (msgEl) {
						msgEl.textContent = this.i18n.searchError || 'Search failed';
						msgEl.className = 'fplant-postal-code-message fplant-postal-code-error';
					}
				});
		}

		/**
		 * Fill address fields from postal code search result
		 */
		fillAddressFromPostalCode(wrapper, result) {
			// Check if this is inside an address composite field
			const addressField = wrapper.closest('.fplant-field-address');

			if (addressField) {
				// Address composite field — fill sub-fields directly
				const prefInput = addressField.querySelector('.fplant-address-prefecture-input');
				if (prefInput) {
					prefInput.value = result.address1;
				}
				const cityInput = addressField.querySelector('.fplant-address-city-input');
				if (cityInput) cityInput.value = result.address2;

				const streetInput = addressField.querySelector('.fplant-address-street-input');
				if (streetInput && result.address3) streetInput.value = result.address3;
			} else {
				// Standalone postal_code field — fill linked fields via data attributes
				const fieldEl = wrapper.closest('.fplant-field-postal-code');
				if (!fieldEl) return;

				const targetsAttr = fieldEl.getAttribute('data-autofill-targets');
				if (!targetsAttr) return;

				let targets;
				try {
					targets = JSON.parse(targetsAttr);
				} catch (e) {
					return;
				}

				// Find the form container
				const formEl = fieldEl.closest('form') || fieldEl.closest('.fplant-form');
				if (!formEl) return;

				const hasPref  = !!targets.pref;
				const hasAddr1 = !!targets.addr1;
				const hasAddr2 = !!targets.addr2;

				if (hasPref && !hasAddr1 && !hasAddr2) {
					// Field 1 only
					const prefField = formEl.querySelector('[name="' + targets.pref + '"]');
					if (!prefField) {
						// Try radio buttons — select type, prefecture only
						const prefRadio = formEl.querySelector('[name="' + targets.pref + '"][value="' + result.address1 + '"]');
						if (prefRadio) prefRadio.checked = true;
					} else if (prefField.tagName === 'SELECT') {
						// Select type — prefecture only
						prefField.value = result.address1;
					} else if (prefField.tagName === 'INPUT' && (prefField.type === 'text' || prefField.type === 'search' || !prefField.type)) {
						// Text input — all address info combined
						const parts = [result.address1, result.address2, result.address3].filter(Boolean);
						prefField.value = parts.join('');
					}
				} else if (hasPref && hasAddr1 && !hasAddr2) {
					// Fields 1+2: field1 = prefecture, field2 = city + street
					this.fillTargetField(formEl, targets.pref, result.address1);
					const remaining = [result.address2, result.address3].filter(Boolean).join('');
					const addr1Field = formEl.querySelector('[name="' + targets.addr1 + '"]');
					if (addr1Field) addr1Field.value = remaining;
				} else if (hasPref && hasAddr1 && hasAddr2) {
					// All 3 fields: field1 = prefecture, field2 = city, field3 = street
					this.fillTargetField(formEl, targets.pref, result.address1);
					const addr1Field = formEl.querySelector('[name="' + targets.addr1 + '"]');
					if (addr1Field) addr1Field.value = result.address2;
					if (result.address3) {
						const addr2Field = formEl.querySelector('[name="' + targets.addr2 + '"]');
						if (addr2Field) addr2Field.value = result.address3;
					}
				}
			}
		}

		/**
		 * Fill a target field by name — handles select, radio, and text inputs
		 */
		fillTargetField(formEl, fieldName, value) {
			const field = formEl.querySelector('[name="' + fieldName + '"]');
			if (field) {
				if (field.tagName === 'SELECT') {
					field.value = value;
				} else {
					field.value = value;
				}
			} else {
				// Try radio buttons
				const radio = formEl.querySelector('[name="' + fieldName + '"][value="' + value + '"]');
				if (radio) radio.checked = true;
			}
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

			// Dispatch beforeSubmit event (cancelable)
			if (!this.dispatchFplantEvent('fplant:beforeSubmit', {
				formId: this.formId,
				formData: this.getFormData()
			}, true)) {
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
					this.showErrors(fplantData.i18n.validationError, fieldErrors);
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

		validateAddressField(fieldName, group, addressField, isRequired) {
			let hasError = false;

			if (isRequired) {
				const locale = addressField.dataset.addressLocale;
				const requiredSubs = locale === 'ja'
					? ['postal_code', 'prefecture', 'city', 'street']
					: ['street', 'city', 'postal_code', 'country'];

				const formId = this.form.dataset.formId;
				const fieldsConfig = window.fplantFieldsConfig && window.fplantFieldsConfig[formId];
				const fieldConfig = fieldsConfig && fieldsConfig.find(f => f.name === fieldName);
				const addrValMsgs = fieldConfig && fieldConfig.address_validation_messages || {};
				const addrLabels = fieldConfig && fieldConfig.address_labels || {};

				requiredSubs.forEach(subKey => {
					let subValue = '';
					if (subKey === 'postal_code') {
						const postalInput = addressField.querySelector('.fplant-address-postal-code-value');
						subValue = postalInput ? postalInput.value : '';
					} else if (subKey === 'prefecture') {
						const prefSelect = addressField.querySelector('[name="' + fieldName + '[prefecture]"]');
						if (prefSelect) {
							if (prefSelect.tagName === 'SELECT') {
								subValue = prefSelect.value;
							} else {
								// Radio/checkbox
								const checked = addressField.querySelector('[name="' + fieldName + '[prefecture]"]:checked, [name="' + fieldName + '[prefecture][]"]:checked');
								subValue = checked ? checked.value : '';
							}
						}
					} else {
						const input = addressField.querySelector('[name="' + fieldName + '[' + subKey + ']"]');
						subValue = input ? input.value : '';
					}

					if (!subValue || subValue.trim() === '') {
						hasError = true;
						const subLabel = addrLabels[subKey] || subKey;
						const subMsg = addrValMsgs[subKey] || fplantData.i18n.requiredText;
						const subErrorEl = this.form.querySelector('[data-field-error="' + fieldName + '.' + subKey + '"]');
						if (subErrorEl) {
							subErrorEl.textContent = subMsg;
							subErrorEl.style.display = 'block';
						}
					}
				});
			}

			// Postal code format check (Japanese locale)
			if (!hasError && addressField.dataset.addressLocale === 'ja') {
				const postalInput = addressField.querySelector('.fplant-address-postal-code-value');
				if (postalInput && postalInput.value) {
					const cleaned = postalInput.value.replace(/[^0-9]/g, '');
					if (cleaned.length !== 7) {
						hasError = true;
						const formId = this.form.dataset.formId;
						const fieldsConfig = window.fplantFieldsConfig && window.fplantFieldsConfig[formId];
						const fieldConfig = fieldsConfig && fieldsConfig.find(f => f.name === fieldName);
						const addrValMsgs = fieldConfig && fieldConfig.address_validation_messages || {};
						const subMsg = addrValMsgs['postal_code'] || fplantData.i18n.invalidPostalCode || fplantData.i18n.requiredText;
						const subErrorEl = this.form.querySelector('[data-field-error="' + fieldName + '.postal_code"]');
						if (subErrorEl) {
							subErrorEl.textContent = subMsg;
							subErrorEl.style.display = 'block';
						}
					}
				}
			}

			if (hasError) {
				group.classList.add('fplant-field-has-error');
			}
			return !hasError;
		}

		validateNamePartsField(fieldName, group, nameGroup, isRequired) {
			let hasError = false;

			if (isRequired) {
				const formId = this.form.dataset.formId;
				const fieldsConfig = window.fplantFieldsConfig && window.fplantFieldsConfig[formId];
				const fieldConfig = fieldsConfig && fieldsConfig.find(f => f.name === fieldName);
				const nameFormat = fieldConfig && fieldConfig.name_format || '2';
				const nameLabels = fieldConfig && fieldConfig.name_labels || {};
				const nameValMsgs = fieldConfig && fieldConfig.name_validation_messages || {};

				// Format '1' (single input) is handled by normal validation
				if (nameFormat === '1') {
					return true;
				}

				const requiredParts = ['family', 'given'];

				requiredParts.forEach(partKey => {
					const input = nameGroup.querySelector('.fplant-name-part-' + partKey);
					const subValue = input ? input.value.trim() : '';

					if (!subValue) {
						hasError = true;
						// Use custom validation message if set, otherwise generate from label
						let subMsg;
						if (nameValMsgs[partKey]) {
							subMsg = nameValMsgs[partKey];
						} else {
							const namePartDiv = input && input.closest('.fplant-name-part');
							const sublabelEl = namePartDiv && namePartDiv.querySelector('.fplant-name-sublabel');
							const subLabel = (sublabelEl && sublabelEl.textContent.trim()) || nameLabels[partKey] || partKey;
							subMsg = fplantData.i18n.requiredSubField
								? fplantData.i18n.requiredSubField.replace('%s', subLabel)
								: fplantData.i18n.requiredText;
						}
						const subErrorEl = this.form.querySelector(
							'[data-field-error="' + fieldName + '.' + partKey + '"]'
						);
						if (subErrorEl) {
							subErrorEl.textContent = subMsg;
							subErrorEl.style.display = 'block';
						}
					}
				});
			}

			// Kana validation (for name_kana fields)
			if (!hasError) {
				const kanaValidation = nameGroup.dataset.kanaValidation;
				if (kanaValidation && kanaValidation !== 'none') {
					const kanaInputs = nameGroup.querySelectorAll('.fplant-name-input');
					let kanaPattern;
					if (kanaValidation === 'katakana') {
						kanaPattern = /^[\u30A0-\u30FF\u30FC\s]*$/;
					} else if (kanaValidation === 'hiragana') {
						kanaPattern = /^[\u3040-\u309F\u30FC\s]*$/;
					}
					if (kanaPattern) {
						for (const kInput of kanaInputs) {
							if (kInput.value.trim() && !kanaPattern.test(kInput.value.trim())) {
								hasError = true;
								const customKanaMsg = nameGroup.dataset.kanaErrorMessage;
								const errorMessage = customKanaMsg ||
									(kanaValidation === 'katakana'
										? fplantData.i18n.kanaKatakanaOnly
										: fplantData.i18n.kanaHiraganaOnly);
								const errorContainer = group.querySelector('.fplant-field-error:not(.fplant-name-sub-error)');
								if (errorContainer) {
									errorContainer.textContent = errorMessage;
									errorContainer.style.display = 'block';
								}
								break;
							}
						}
					}
				}
			}

			if (hasError) {
				group.classList.add('fplant-field-has-error');
			}
			return !hasError;
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
			// Clear address sub-field errors
			group.querySelectorAll('.fplant-address-sub-error').forEach(el => {
				el.style.display = 'none';
				el.textContent = '';
			});
			// Clear name-parts sub-field errors
			group.querySelectorAll('.fplant-name-sub-error').forEach(el => {
				el.style.display = 'none';
				el.textContent = '';
			});
			group.classList.remove('fplant-field-has-error');

			// Address composite field validation
			const addressField = group.querySelector('.fplant-field-address');
			if (addressField) {
				return this.validateAddressField(fieldName, group, addressField, isRequired);
			}

			// Name parts / name kana composite field validation
			const nameGroup = group.querySelector('.fplant-field-name-parts, .fplant-field-name-kana');
			if (nameGroup && nameGroup.querySelector('.fplant-name-parts-value')) {
				return this.validateNamePartsField(fieldName, group, nameGroup, isRequired);
			}

			// Get fields
			const fields = this.form.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');

			if (!fields.length) return true;

			const firstField = fields[0];
			const fieldType = firstField.getAttribute('type') || firstField.tagName.toLowerCase();
			let value = null;
			let errorMessage = null;

			// Required field validation
			if (isRequired) {
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
						errorMessage = customMessage || fplantData.i18n.requiredCheckbox;
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
						errorMessage = customMessage || fplantData.i18n.requiredRadio;
					}
				} else if (fieldType === 'select' || fieldType === 'SELECT') {
					// Select box
					value = firstField.value;
					if (!value || value === '') {
						errorMessage = customMessage || fplantData.i18n.requiredSelect;
					}
				} else if (fieldType === 'file') {
					// File upload
					const file = firstField.files && firstField.files[0];
					if (!file) {
						errorMessage = customMessage || fplantData.i18n.requiredFile;
					} else {
						// File size check
						const maxSize = parseInt(firstField.dataset.maxSize) || 2097152; // Default 2MB
						if (file.size > maxSize) {
							const maxSizeMB = (maxSize / 1048576).toFixed(1);
							errorMessage = fplantData.i18n.fileTooLarge.replace('%s', maxSizeMB);
						}

						// File type check
						const accept = firstField.getAttribute('accept');
						if (accept && accept.indexOf('image/*') !== -1) {
							// If image only
							if (!file.type.startsWith('image/')) {
								errorMessage = fplantData.i18n.imageRequired;
							}
						}
					}
				} else {
					// Text fields
					value = firstField.value;
					if (!value || value.trim() === '') {
						errorMessage = customMessage || fplantData.i18n.requiredText;
					}
				}
			}

			// File size/type validation for non-required file fields
			if (!errorMessage && !isRequired && fieldType === 'file') {
				const file = firstField.files && firstField.files[0];
				if (file) {
					const maxSize = parseInt(firstField.dataset.maxSize) || 2097152;
					if (file.size > maxSize) {
						const maxSizeMB = (maxSize / 1048576).toFixed(1);
						errorMessage = fplantData.i18n.fileTooLarge.replace('%s', maxSizeMB);
					}
					if (!errorMessage) {
						const accept = firstField.getAttribute('accept');
						if (accept && accept.indexOf('image/*') !== -1) {
							if (!file.type.startsWith('image/')) {
								errorMessage = fplantData.i18n.imageRequired;
							}
						}
					}
				}
			}

			// Kana validation (name_kana type)
			if (!errorMessage) {
				const kanaGroup = group.querySelector('.fplant-field-name-kana');
				if (kanaGroup) {
					const kanaValidation = kanaGroup.dataset.kanaValidation;
					if (kanaValidation && kanaValidation !== 'none') {
						const kanaInputs = kanaGroup.querySelectorAll('.fplant-name-input');
						let kanaPattern;
						if (kanaValidation === 'katakana') {
							kanaPattern = /^[\u30A0-\u30FF\u30FC\s]*$/;
						} else if (kanaValidation === 'hiragana') {
							kanaPattern = /^[\u3040-\u309F\u30FC\s]*$/;
						}
						if (kanaPattern) {
							for (const kInput of kanaInputs) {
								if (kInput.value.trim() && !kanaPattern.test(kInput.value.trim())) {
									const customKanaMsg = kanaGroup.dataset.kanaErrorMessage;
									errorMessage = customKanaMsg ||
										(kanaValidation === 'katakana'
											? fplantData.i18n.kanaKatakanaOnly
											: fplantData.i18n.kanaHiraganaOnly);
									break;
								}
							}
						}
					}
				}
			}

			// Password validation
			if (!errorMessage) {
				const passwordWrapper = group.querySelector('.fplant-password-wrapper');
				if (passwordWrapper) {
					const passwordInput = passwordWrapper.querySelector('input[type="password"], input[type="text"]');
					if (passwordInput && passwordInput.value) {
						// Minimum length check
						const minLength = parseInt(passwordInput.getAttribute('minlength'));
						if (minLength && passwordInput.value.length < minLength) {
							errorMessage = fplantData.i18n.passwordMinLength
								? fplantData.i18n.passwordMinLength.replace('%s', minLength)
								: 'Password must be at least ' + minLength + ' characters';
						}

						// Strength level check
						if (!errorMessage) {
							const strengthLevel = passwordWrapper.dataset.strengthLevel;
							if (strengthLevel && strengthLevel !== 'none' && typeof zxcvbn !== 'undefined') {
								const result = zxcvbn(passwordInput.value);
								const requiredScore = { 'weak': 1, 'fair': 2, 'strong': 3 }[strengthLevel] || 0;
								if (result.score < requiredScore) {
									errorMessage = fplantData.i18n.passwordTooWeak || 'Password is not strong enough';
								}
							}
						}
					}
				}
			}

			// Run custom validators (callback API)
			if (!errorMessage && window.fplant && window.fplant.validators) {
				const validators = window.fplant.validators[fieldName];
				if (validators && validators.length > 0) {
					const currentValue = this.getFieldValue(fieldName, fields, fieldType);
					for (const validator of validators) {
						try {
							const result = validator(currentValue, fieldName, this.getFormData());
							if (typeof result === 'string' && result.length > 0) {
								errorMessage = result;
								break;
							}
						} catch (e) {
							// Silently ignore validator errors
						}
					}
				}
			}

			// Dispatch validateField event (cancelable)
			if (!errorMessage) {
				const currentValue = this.getFieldValue(fieldName, fields, fieldType);
				const eventDetail = {
					fieldName: fieldName,
					value: currentValue,
					field: fields[0],
					group: group,
					errorMessage: null
				};
				const event = new CustomEvent('fplant:validateField', {
					detail: eventDetail,
					bubbles: true,
					cancelable: true
				});
				if (!this.form.dispatchEvent(event)) {
					errorMessage = eventDetail.errorMessage || fplantData.i18n.requiredText;
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
				body.append('nonce', fplantData.nonce);
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
					nonce: fplantData.nonce,
					form_id: this.formId,
					data: JSON.stringify(formData)
				});
			}

			// Fetch submission
			fetch(fplantData.ajaxUrl, {
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
					const message = response.data.message || fplantData.i18n.validationError;
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
				this.showErrors(fplantData.i18n.serverError);
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

				// For dot-separated keys (e.g. address.postal_code), also mark parent group
				if (!group && fieldName.indexOf('.') !== -1) {
					const parentName = fieldName.split('.')[0];
					const parentGroup = this.form.querySelector('.fplant-field-group[data-field-name="' + parentName + '"]');
					if (parentGroup) {
						parentGroup.classList.add('fplant-field-has-error');
					}
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

			// Render reCAPTCHA v2 widget on confirmation screen
			if (this.captchaConfig.type === 'recaptcha_v2') {
				this.renderRecaptchaV2Widget(this.confirmation);
			}

			// Render Turnstile widget on confirmation screen
			if (this.captchaConfig.type === 'turnstile') {
				this.renderTurnstileWidget(this.confirmation);
			}

			// Scroll to top
			this.scrollToMessage(this.confirmation);

			// Dispatch confirmationShow event
			this.dispatchFplantEvent('fplant:confirmationShow', {
				formId: this.formId,
				confirmationEl: this.confirmation
			});
		}

		hideConfirmation() {
			// Remove reCAPTCHA v2 widget before removing confirmation
			if (this.captchaConfig.type === 'recaptcha_v2') {
				this.removeRecaptchaV2Widget();
			}
			// Remove Turnstile widget before removing confirmation
			if (this.captchaConfig.type === 'turnstile') {
				this.removeTurnstileWidget();
			}
			if (this.confirmation) {
				this.confirmation.remove();
				this.confirmation = null;
			}
			this.form.style.display = '';

			// Dispatch confirmationHide event
			this.dispatchFplantEvent('fplant:confirmationHide', {
				formId: this.formId
			});
		}

		async submitForm() {
			// Get form data
			const formData = this.getFormData();

			// Set loading state
			this.setLoading(true);

			// Get CAPTCHA token
			let captchaToken = '';
			if (this.captchaConfig.type === 'recaptcha_v2') {
				// v2: token is already set by the widget callback
				const tokenInput = this.form.querySelector('.fplant-captcha-token');
				captchaToken = tokenInput ? tokenInput.value : '';
				if (!captchaToken) {
					this.setLoading(false);
					this.showErrors(fplantData.i18n.captchaError || fplantData.i18n.recaptchaError);
					return;
				}
			} else if (this.captchaConfig.type === 'recaptcha') {
				try {
					captchaToken = await this.getRecaptchaV3Token();
					const tokenInput = this.form.querySelector('.fplant-captcha-token');
					if (tokenInput) {
						tokenInput.value = captchaToken;
					}
				} catch (error) {
					this.setLoading(false);
					this.showErrors(fplantData.i18n.captchaError || fplantData.i18n.recaptchaError);
					return;
				}
			} else if (this.captchaConfig.type === 'turnstile') {
				try {
					captchaToken = await this.getTurnstileToken();
					const tokenInput = this.form.querySelector('.fplant-captcha-token');
					if (tokenInput) {
						tokenInput.value = captchaToken;
					}
				} catch (error) {
					this.setLoading(false);
					this.showErrors(fplantData.i18n.captchaError || fplantData.i18n.recaptchaError);
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
				body.append('nonce', fplantData.nonce);
				body.append('form_id', this.formId);
				body.append('data', JSON.stringify(formData));

				// Add CAPTCHA token
				if (captchaToken) {
					body.append('fplant_captcha_token', captchaToken);
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
					nonce: fplantData.nonce,
					form_id: this.formId,
					data: JSON.stringify(formData)
				};

				// Add CAPTCHA token
				if (captchaToken) {
					params.fplant_captcha_token = captchaToken;
				}

				body = new URLSearchParams(params);
			}

			// Fetch submission
			fetch(fplantData.ajaxUrl, {
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
					grecaptcha.execute(this.captchaConfig.recaptchaSiteKey, { action: 'submit' })
						.then(token => resolve(token))
						.catch(error => reject(error));
				});
			});
		}

		/**
		 * Render reCAPTCHA v2 checkbox widget
		 * @param {HTMLElement} parentElement - The form or confirmation element
		 */
		renderRecaptchaV2Widget(parentElement) {
			if (typeof grecaptcha === 'undefined') {
				// api.js not yet loaded, retry after load
				window.addEventListener('load', () => {
					if (typeof grecaptcha !== 'undefined') {
						this._doRenderRecaptchaV2(parentElement);
					}
				});
				return;
			}
			this._doRenderRecaptchaV2(parentElement);
		}

		_doRenderRecaptchaV2(parentElement) {
			// Remove existing widget
			this.removeRecaptchaV2Widget();

			// Create container
			const container = document.createElement('div');
			container.className = 'fplant-recaptcha-v2-container';
			container.style.marginBottom = '15px';

			// Insert before submit button
			const submitButton = parentElement.querySelector('.fplant-confirm-submit-button, .fplant-submit-button, button[type="submit"]');
			const footer = parentElement.querySelector('.fplant-confirmation-footer');
			if (footer) {
				footer.parentNode.insertBefore(container, footer);
			} else if (submitButton) {
				submitButton.parentNode.insertBefore(container, submitButton);
			} else {
				parentElement.appendChild(container);
			}

			// Disable submit button until checkbox is checked
			if (submitButton) {
				submitButton.disabled = true;
			}

			this.recaptchaV2WidgetId = grecaptcha.render(container, {
				sitekey: this.captchaConfig.recaptchaV2SiteKey,
				callback: (token) => {
					const tokenInput = this.form.querySelector('.fplant-captcha-token');
					if (tokenInput) {
						tokenInput.value = token;
					}
					if (submitButton) {
						submitButton.disabled = false;
					}
				},
				'expired-callback': () => {
					const tokenInput = this.form.querySelector('.fplant-captcha-token');
					if (tokenInput) {
						tokenInput.value = '';
					}
					if (submitButton) {
						submitButton.disabled = true;
					}
				}
			});
		}

		/**
		 * Remove existing reCAPTCHA v2 widget
		 */
		removeRecaptchaV2Widget() {
			if (this.recaptchaV2WidgetId !== null && typeof grecaptcha !== 'undefined') {
				try {
					grecaptcha.reset(this.recaptchaV2WidgetId);
				} catch (e) {
					// Widget may already be removed
				}
				this.recaptchaV2WidgetId = null;
			}
			// Remove container elements
			this.form.querySelectorAll('.fplant-recaptcha-v2-container').forEach(el => el.remove());
			if (this.confirmation) {
				this.confirmation.querySelectorAll('.fplant-recaptcha-v2-container').forEach(el => el.remove());
			}
		}

		/**
		 * Render Turnstile widget visibly inside a container
		 * @param {HTMLElement} parentElement - The form or confirmation element
		 */
		renderTurnstileWidget(parentElement) {
			if (typeof turnstile === 'undefined') {
				return;
			}

			// Remove existing widget if any
			this.removeTurnstileWidget();

			// Create container for Turnstile widget
			const container = document.createElement('div');
			container.className = 'fplant-turnstile-container';
			container.style.marginBottom = '15px';

			// Insert before submit button
			const submitButton = parentElement.querySelector('.fplant-confirm-submit-button, .fplant-submit-button, button[type="submit"]');
			const footer = parentElement.querySelector('.fplant-confirmation-footer');
			if (footer) {
				footer.parentNode.insertBefore(container, footer);
			} else if (submitButton) {
				submitButton.parentNode.insertBefore(container, submitButton);
			} else {
				parentElement.appendChild(container);
			}

			this.turnstileWidgetId = turnstile.render(container, {
				sitekey: this.captchaConfig.turnstileSiteKey,
				callback: (token) => {
					const tokenInput = this.form.querySelector('.fplant-captcha-token');
					if (tokenInput) {
						tokenInput.value = token;
					}
				},
				'error-callback': () => {
					const tokenInput = this.form.querySelector('.fplant-captcha-token');
					if (tokenInput) {
						tokenInput.value = '';
					}
				}
			});
		}

		/**
		 * Remove existing Turnstile widget
		 */
		removeTurnstileWidget() {
			if (this.turnstileWidgetId !== null && typeof turnstile !== 'undefined') {
				try {
					turnstile.remove(this.turnstileWidgetId);
				} catch (e) {
					// Widget may already be removed
				}
				this.turnstileWidgetId = null;
			}
			// Remove container elements scoped to this form instance only
			this.form.querySelectorAll('.fplant-turnstile-container').forEach(el => el.remove());
			if (this.confirmation) {
				this.confirmation.querySelectorAll('.fplant-turnstile-container').forEach(el => el.remove());
			}
		}

		/**
		 * Get Cloudflare Turnstile token from rendered widget
		 * @returns {Promise<string>}
		 */
		getTurnstileToken() {
			return new Promise((resolve, reject) => {
				if (typeof turnstile === 'undefined') {
					reject(new Error('Cloudflare Turnstile not loaded'));
					return;
				}

				// Get token from already-rendered widget
				if (this.turnstileWidgetId !== null) {
					const token = turnstile.getResponse(this.turnstileWidgetId);
					if (token) {
						resolve(token);
						return;
					}
				}

				// Fallback: render a hidden widget if no visible widget exists
				const container = document.createElement('div');
				container.className = 'fplant-turnstile-container';
				container.style.position = 'absolute';
				container.style.left = '-9999px';
				this.form.appendChild(container);

				try {
					turnstile.render(container, {
						sitekey: this.captchaConfig.turnstileSiteKey,
						callback: (token) => {
							container.remove();
							resolve(token);
						},
						'error-callback': () => {
							container.remove();
							reject(new Error('Turnstile verification failed'));
						}
					});
				} catch (error) {
					container.remove();
					reject(error);
				}
			});
		}

		buildConfirmationHtml(formData) {
			const title = this.form.dataset.confirmationTitle || fplantData.i18n.confirmationTitle;
			const message = this.form.dataset.confirmationMessage || fplantData.i18n.confirmationMessage;

			// Get button text and attributes
			const buttonTexts = window.fplantConfirmationButtons && window.fplantConfirmationButtons[this.formId];
			const backText = buttonTexts ? buttonTexts.back : fplantData.i18n.back;
			const backClass = buttonTexts ? buttonTexts.back_class : '';
			const backId = buttonTexts ? buttonTexts.back_id : '';
			const submitText = buttonTexts ? buttonTexts.submit : fplantData.i18n.submitForm;
			const submitClass = buttonTexts ? buttonTexts.submit_class : '';
			const submitId = buttonTexts ? buttonTexts.submit_id : '';

			// Use custom template if available
			const customTemplate = window.fplantConfirmationTemplate && window.fplantConfirmationTemplate[this.formId];
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

				// Mask password field in confirmation
				const passwordInput = this.form.querySelector(`input[name="${fieldName}"]`);
				if (passwordInput && passwordInput.closest('.fplant-password-wrapper')) {
					fieldValue = fieldValue ? '\u25CF'.repeat(fieldValue.length) : '';
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
			// First, try to get label from fplantFieldsConfig
			const fieldsConfig = window.fplantFieldsConfig && window.fplantFieldsConfig[this.formId];
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
			const fieldsConfig = window.fplantFieldsConfig && window.fplantFieldsConfig[this.formId];
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
			const fieldsConfig = window.fplantFieldsConfig && window.fplantFieldsConfig[this.formId];
			if (!fieldsConfig) {
				return false;
			}

			const fieldConfig = fieldsConfig.find(f => f.name === fieldName);
			return fieldConfig && ['select', 'radio', 'checkbox'].includes(fieldConfig.type);
		}

		getFormData() {
			const data = {};
			const formData = new FormData(this.form);

			// Detect honeypot field name from DOM for server-side check
			const honeypotInput = this.form.querySelector('.fplant-field-url input[type="text"]');
			const honeypotFieldName = honeypotInput ? honeypotInput.name : 'fplant_website_url';

			for (const [key, value] of formData.entries()) {
				// Skip internal fields starting with fplant_ (but keep honeypot and timestamp for server-side check)
				if (key.indexOf('fplant_') === 0 && key !== honeypotFieldName && key !== 'fplant_form_ts') {
					continue;
				}

				// Skip individual date dropdown fields (fieldname[year], etc.)
				if (key.match(/\[(year|month|day)\]$/)) {
					continue;
				}

				// Send individual name part fields as flat keys for server-side validation
				const namePartMatch = key.match(/^([^\[]+)\[(family|given|middle)\]$/);
				if (namePartMatch) {
					data[namePartMatch[1] + '_' + namePartMatch[2]] = value;
					continue;
				}

				// Skip individual postal code / tel split fields (fieldname[part1], [part2], [part3])
				if (key.match(/\[(part1|part2|part3|postal_code_part1|postal_code_part2)\]$/)) {
					continue;
				}

				// Handle address composite sub-fields (fieldname[sub_key])
				const addressMatch = key.match(/^([^\[]+)\[(postal_code|prefecture|city|street|building|address2|state|country)\]$/);
				if (addressMatch) {
					const parentName = addressMatch[1];
					const subKey = addressMatch[2];
					if (!data[parentName]) {
						data[parentName] = {};
					}
					if (typeof data[parentName] === 'object' && !Array.isArray(data[parentName])) {
						data[parentName][subKey] = value;
					}
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
				this.form.dispatchEvent(new CustomEvent('fplant:success', { detail: response.data }));
			} else {
				this.showErrors(response.data.message, response.data.errors);
				// Show field-specific errors (works even in HTML template mode without a
				// .fplant-errors container, via the dynamic fallback in showFieldErrors).
				this.showFieldErrors(response.data.errors || {});

				// Dispatch submitError event
				this.dispatchFplantEvent('fplant:submitError', {
					message: response.data.message,
					errors: response.data.errors || {}
				});
			}
		}

		handleError(error) {
			this.showErrors(fplantData.i18n.errorOccurred);

			// Dispatch submitError event
			this.dispatchFplantEvent('fplant:submitError', {
				message: fplantData.i18n.errorOccurred,
				errors: {}
			});
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

			// Dispatch error event
			this.dispatchFplantEvent('fplant:error', {
				fieldErrors: fieldErrors,
				message: message
			});
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

			// Dispatch loading event
			this.dispatchFplantEvent('fplant:loading', { loading: loading });
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

		dispatchFplantEvent(eventName, detail, cancelable) {
			const event = new CustomEvent(eventName, {
				detail: detail || {},
				bubbles: true,
				cancelable: cancelable || false
			});
			return this.form.dispatchEvent(event);
		}

		getFieldValue(fieldName, fields, fieldType) {
			if (!fields || !fields.length) return null;
			if (fieldType === 'checkbox') {
				const checked = [];
				fields.forEach(field => { if (field.checked) checked.push(field.value); });
				return checked;
			} else if (fieldType === 'radio') {
				for (const field of fields) { if (field.checked) return field.value; }
				return null;
			} else if (fieldType === 'file') {
				return fields[0].files && fields[0].files[0] ? fields[0].files[0] : null;
			}
			return fields[0].value;
		}

		getCustomValidationMessage(fieldName) {
			const formId = this.form.dataset.formId;
			if (!formId || !window.fplantFieldsConfig || !window.fplantFieldsConfig[formId]) {
				return null;
			}

			const fields = window.fplantFieldsConfig[formId];
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
			const backText = buttonTexts ? buttonTexts.back : fplantData.i18n.back;
			const backClass = buttonTexts ? buttonTexts.back_class : '';
			const backId = buttonTexts ? buttonTexts.back_id : '';
			const submitText = buttonTexts ? buttonTexts.submit : fplantData.i18n.submitForm;
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
			let fieldsHtml = '';

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

				// Mask password field in confirmation
				const passwordInput = this.form.querySelector(`input[name="${fieldName}"]`);
				if (passwordInput && passwordInput.closest('.fplant-password-wrapper')) {
					fieldValue = fieldValue ? '\u25CF'.repeat(fieldValue.length) : '';
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
						<div class="fplant-field-group" data-field-name="${this.escapeHtml(fieldName)}">
							<label>${this.escapeHtml(fieldLabel)}</label>
							<div class="fplant-field-value">${displayValue}</div>
						</div>
					`;
			});
			return fieldsHtml;
		}

		initPasswordToggles() {
			const toggles = this.form.querySelectorAll('.fplant-password-toggle');
			toggles.forEach(toggle => {
				toggle.addEventListener('click', () => {
					const wrapper = toggle.closest('.fplant-password-input-wrapper');
					const input = wrapper ? wrapper.querySelector('input') : null;
					if (!input) return;

					const icon = toggle.querySelector('.dashicons');
					if (input.type === 'password') {
						input.type = 'text';
						if (icon) {
							icon.classList.remove('dashicons-visibility');
							icon.classList.add('dashicons-hidden');
						}
						toggle.setAttribute('aria-label', fplantData.i18n.hidePassword || 'Hide password');
					} else {
						input.type = 'password';
						if (icon) {
							icon.classList.remove('dashicons-hidden');
							icon.classList.add('dashicons-visibility');
						}
						toggle.setAttribute('aria-label', fplantData.i18n.showPassword || 'Show password');
					}
				});
			});
		}

		initPasswordStrengthMeters() {
			const wrappers = this.form.querySelectorAll('.fplant-password-wrapper[data-strength-meter="1"]');
			wrappers.forEach(wrapper => {
				const input = wrapper.querySelector('input');
				const bar = wrapper.querySelector('.fplant-password-strength-bar');
				const text = wrapper.querySelector('.fplant-password-strength-text');
				if (!input || !bar || !text) return;

				input.addEventListener('input', () => {
					if (!input.value) {
						bar.style.width = '0%';
						bar.className = 'fplant-password-strength-bar';
						text.textContent = '';
						return;
					}

					if (typeof zxcvbn === 'undefined') return;

					const result = zxcvbn(input.value);
					const labels = [
						fplantData.i18n.strengthVeryWeak || 'Very Weak',
						fplantData.i18n.strengthWeak || 'Weak',
						fplantData.i18n.strengthFair || 'Fair',
						fplantData.i18n.strengthStrong || 'Strong',
						fplantData.i18n.strengthVeryStrong || 'Very Strong'
					];
					const classes = ['very-weak', 'weak', 'fair', 'strong', 'very-strong'];
					const widths = ['20%', '40%', '60%', '80%', '100%'];

					bar.style.width = widths[result.score];
					bar.className = 'fplant-password-strength-bar fplant-strength-' + classes[result.score];
					text.textContent = labels[result.score];
				});
			});
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
