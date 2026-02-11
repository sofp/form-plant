/**
 * Form Plant - JavaScript SDK for embedding
 *
 * SDK for embedding forms from external sites
 *
 * @package Form_Plant
 */

(function() {
	'use strict';

	/**
	 * WPFPlantEmbed - Embed SDK
	 */
	window.WPFPlantEmbed = {
		/**
		 * Store initialized forms
		 */
		forms: {},

		/**
		 * Whether CSS has been loaded
		 */
		cssLoaded: false,

		/**
		 * Whether reCAPTCHA script has been loaded
		 */
		recaptchaLoaded: false,

		/**
		 * reCAPTCHA configuration
		 */
		recaptchaConfig: {},

		/**
		 * Render form
		 *
		 * @param {number} formId Form ID
		 * @param {string} targetSelector Target selector for embedding
		 * @param {string} siteUrl WordPress site URL
		 * @param {object} options Options
		 */
		render: function(formId, targetSelector, siteUrl, options) {
			var self = this;
			options = options || {};

			// Get target element
			var target = document.querySelector(targetSelector);
			if (!target) {
				console.error('WPFPlantEmbed: Target element not found:', targetSelector);
				return;
			}

			// Normalize trailing slash of site URL
			siteUrl = siteUrl.replace(/\/$/, '');

			// Show loading
			target.innerHTML = '<div class="fplant-embed-loading">Loading...</div>';

			// Load CSS
			if (!this.cssLoaded && options.loadCss !== false) {
				this.loadCss(siteUrl);
			}

			// Get form data from REST API
			var apiUrl = siteUrl + '/wp-json/form-plant/v1/embed/' + formId;

			this.fetchJson(apiUrl, function(error, response) {
				if (error) {
					target.innerHTML = '<div class="fplant-embed-error">Failed to load form</div>';
					console.error('WPFPlantEmbed:', error);
					return;
				}

				if (!response.success || !response.data) {
					target.innerHTML = '<div class="fplant-embed-error">' + (response.message || 'An error occurred') + '</div>';
					return;
				}

				// Render form HTML
				target.innerHTML = response.data.html;

				// Save reCAPTCHA configuration
				if (response.recaptcha && response.recaptcha.enabled) {
					self.recaptchaConfig[formId] = response.recaptcha;
					// Load reCAPTCHA script
					self.loadRecaptcha(response.recaptcha.siteKey);
				}

				// Save form info
				self.forms[formId] = {
					target: target,
					siteUrl: siteUrl,
					formData: response.data,
					options: options
				};

				// Set up event listeners
				self.attachEventListeners(formId);
			});
		},

		/**
		 * Load CSS
		 *
		 * @param {string} siteUrl Site URL
		 */
		loadCss: function(siteUrl) {
			var link = document.createElement('link');
			link.rel = 'stylesheet';
			link.href = siteUrl + '/wp-content/plugins/form-plant/assets/css/form.css';
			document.head.appendChild(link);

			// Additional CSS for embedding
			var embedLink = document.createElement('link');
			embedLink.rel = 'stylesheet';
			embedLink.href = siteUrl + '/wp-content/plugins/form-plant/assets/css/embed.css';
			document.head.appendChild(embedLink);

			this.cssLoaded = true;
		},

		/**
		 * Load reCAPTCHA script
		 *
		 * @param {string} siteKey reCAPTCHA site key
		 */
		loadRecaptcha: function(siteKey) {
			if (this.recaptchaLoaded || !siteKey) {
				return;
			}

			var script = document.createElement('script');
			script.src = 'https://www.google.com/recaptcha/api.js?render=' + siteKey;
			script.async = true;
			script.defer = true;
			document.head.appendChild(script);

			this.recaptchaLoaded = true;
		},

		/**
		 * Get reCAPTCHA token
		 *
		 * @param {number} formId Form ID
		 * @param {function} callback Callback(error, token)
		 */
		getRecaptchaToken: function(formId, callback) {
			var config = this.recaptchaConfig[formId];
			if (!config || !config.enabled || !config.siteKey) {
				callback(null, null);
				return;
			}

			// Check if grecaptcha is loaded
			if (typeof grecaptcha === 'undefined' || typeof grecaptcha.ready === 'undefined') {
				callback(new Error('reCAPTCHA is not loaded'), null);
				return;
			}

			grecaptcha.ready(function() {
				grecaptcha.execute(config.siteKey, { action: 'fplant_submit' })
					.then(function(token) {
						callback(null, token);
					})
					.catch(function(error) {
						callback(error, null);
					});
			});
		},

		/**
		 * Fetch JSON
		 *
		 * @param {string} url URL
		 * @param {function} callback Callback(error, data)
		 */
		fetchJson: function(url, callback) {
			var xhr = new XMLHttpRequest();
			xhr.open('GET', url, true);
			xhr.setRequestHeader('Content-Type', 'application/json');

			xhr.onreadystatechange = function() {
				if (xhr.readyState === 4) {
					if (xhr.status >= 200 && xhr.status < 300) {
						try {
							var data = JSON.parse(xhr.responseText);
							callback(null, data);
						} catch (e) {
							callback(e, null);
						}
					} else {
						callback(new Error('HTTP ' + xhr.status), null);
					}
				}
			};

			xhr.onerror = function() {
				callback(new Error('Network error'), null);
			};

			xhr.send();
		},

		/**
		 * POST JSON
		 *
		 * @param {string} url URL
		 * @param {object} data Data
		 * @param {function} callback Callback(error, data)
		 */
		postJson: function(url, data, callback) {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', url, true);
			xhr.setRequestHeader('Content-Type', 'application/json');

			xhr.onreadystatechange = function() {
				if (xhr.readyState === 4) {
					if (xhr.status >= 200 && xhr.status < 300) {
						try {
							var responseData = JSON.parse(xhr.responseText);
							callback(null, responseData);
						} catch (e) {
							callback(e, null);
						}
					} else {
						try {
							var errorData = JSON.parse(xhr.responseText);
							callback(errorData, null);
						} catch (e) {
							callback(new Error('HTTP ' + xhr.status), null);
						}
					}
				}
			};

			xhr.onerror = function() {
				callback(new Error('Network error'), null);
			};

			xhr.send(JSON.stringify(data));
		},

		/**
		 * Set up event listeners
		 *
		 * @param {number} formId Form ID
		 */
		attachEventListeners: function(formId) {
			var self = this;
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var form = formInfo.target.querySelector('.fplant-form');
			if (!form) return;

			// Form submission
			form.addEventListener('submit', function(e) {
				e.preventDefault();
				self.handleSubmit(formId);
			});
		},

		/**
		 * Handle form submission
		 *
		 * @param {number} formId Form ID
		 */
		handleSubmit: function(formId) {
			var self = this;
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var form = formInfo.target.querySelector('.fplant-form');
			var submitButton = form.querySelector('.fplant-submit-button');

			// Check if confirmation screen is enabled
			var useConfirmation = form.getAttribute('data-use-confirmation') === '1';

			// Set to submitting state
			submitButton.disabled = true;
			submitButton.textContent = 'Submitting...';

			// Clear errors
			this.clearErrors(formId);

			// Collect form data
			var data = this.collectFormData(form);

			// Save form data (used when submitting from confirmation screen)
			formInfo.pendingData = data;

			if (useConfirmation) {
				// Confirmation screen enabled: validation and get confirmation HTML
				var apiUrl = formInfo.siteUrl + '/wp-json/form-plant/v1/embed/validate';

				var postData = {
					form_id: formId,
					data: data
				};

				self.postJson(apiUrl, postData, function(error, response) {
					submitButton.disabled = false;
					submitButton.textContent = formInfo.formData.settings.input_submit_text || 'Confirm';

					if (error) {
						if (error.data && error.data.errors) {
							self.showFieldErrors(formId, error.data.errors);
						} else {
							self.showError(formId, error.message || 'An error occurred');
						}
						return;
					}

					if (response.success && response.confirmation_html) {
						// Show confirmation screen
						self.showConfirmation(formId, response.confirmation_html);
					} else {
						self.showError(formId, response.message || 'An error occurred');
					}
				});
			} else {
				// Confirmation screen disabled: submit directly
				self.handleFinalSubmit(formId);
			}
		},

		/**
		 * Show confirmation screen
		 *
		 * @param {number} formId Form ID
		 * @param {string} confirmationHtml Confirmation screen HTML
		 */
		showConfirmation: function(formId, confirmationHtml) {
			var self = this;
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var form = formInfo.target.querySelector('.fplant-form');

			// Hide form
			form.style.display = 'none';

			// Create confirmation screen
			var confirmation = document.createElement('div');
			confirmation.className = 'fplant-confirmation';
			confirmation.innerHTML = confirmationHtml;
			form.insertAdjacentElement('afterend', confirmation);

			// Save reference to confirmation screen
			formInfo.confirmation = confirmation;

			// Back button event listener
			var backButton = confirmation.querySelector('.fplant-back-button');
			if (backButton) {
				backButton.addEventListener('click', function(e) {
					e.preventDefault();
					self.hideConfirmation(formId);
				});
			}

			// Submit button event listener
			var confirmSubmitButton = confirmation.querySelector('.fplant-confirm-submit-button');
			if (confirmSubmitButton) {
				confirmSubmitButton.addEventListener('click', function(e) {
					e.preventDefault();
					self.handleFinalSubmit(formId);
				});
			}
		},

		/**
		 * Hide confirmation screen and return to form
		 *
		 * @param {number} formId Form ID
		 */
		hideConfirmation: function(formId) {
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var form = formInfo.target.querySelector('.fplant-form');

			// Remove confirmation screen
			if (formInfo.confirmation) {
				formInfo.confirmation.remove();
				formInfo.confirmation = null;
			}

			// Show form
			form.style.display = '';
		},

		/**
		 * Final submission (from confirmation screen or when no confirmation)
		 *
		 * @param {number} formId Form ID
		 */
		handleFinalSubmit: function(formId) {
			var self = this;
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var form = formInfo.target.querySelector('.fplant-form');
			var submitButton = form.querySelector('.fplant-submit-button');

			// Disable confirmation screen submit button if exists
			var confirmSubmitButton = formInfo.confirmation ? formInfo.confirmation.querySelector('.fplant-confirm-submit-button') : null;
			if (confirmSubmitButton) {
				confirmSubmitButton.disabled = true;
				confirmSubmitButton.textContent = 'Submitting...';
			} else {
				submitButton.disabled = true;
				submitButton.textContent = 'Submitting...';
			}

			// Use saved form data
			var data = formInfo.pendingData || this.collectFormData(form);

			// Get reCAPTCHA token before submitting
			this.getRecaptchaToken(formId, function(recaptchaError, recaptchaToken) {
				if (recaptchaError) {
					if (confirmSubmitButton) {
						confirmSubmitButton.disabled = false;
						confirmSubmitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					} else {
						submitButton.disabled = false;
						submitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					}
					self.showError(formId, 'reCAPTCHA verification failed. Please reload the page.');
					return;
				}

				// Submit to REST API
				var apiUrl = formInfo.siteUrl + '/wp-json/form-plant/v1/embed/submit';

				var postData = {
					form_id: formId,
					data: data
				};

				// Add reCAPTCHA token if available
				if (recaptchaToken) {
					postData.recaptcha_token = recaptchaToken;
				}

				self.postJson(apiUrl, postData, function(error, response) {
					if (confirmSubmitButton) {
						confirmSubmitButton.disabled = false;
						confirmSubmitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					} else {
						submitButton.disabled = false;
						submitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					}

					if (error) {
						// Hide confirmation screen and return to form
						self.hideConfirmation(formId);

						if (error.data && error.data.errors) {
							self.showFieldErrors(formId, error.data.errors);
						} else {
							self.showError(formId, error.message || 'An error occurred');
						}
						return;
					}

					if (response.success) {
						// Remove confirmation screen
						if (formInfo.confirmation) {
							formInfo.confirmation.remove();
							formInfo.confirmation = null;
						}

						// Handle based on action type
						var actionType = response.action_type || 'message';

						if (actionType === 'redirect' && response.redirect_url) {
							// Redirect
							window.location.href = response.redirect_url;
						} else if (actionType === 'custom_page' && response.complete_html) {
							// Show completion page HTML
							self.showSuccess(formId, response.complete_html, true);
						} else {
							// Show simple message
							self.showSuccess(formId, response.message || 'Submission completed', false);
						}

						// Call optional callback
						if (formInfo.options.onSuccess) {
							formInfo.options.onSuccess(response);
						}
					} else {
						self.showError(formId, response.message || 'An error occurred');
					}
				});
			});
		},

		/**
		 * Collect form data
		 *
		 * @param {HTMLFormElement} form Form element
		 * @return {object} Form data
		 */
		collectFormData: function(form) {
			var data = {};
			var formData = new FormData(form);

			formData.forEach(function(value, key) {
				// Array case (checkboxes, etc.)
				if (key.endsWith('[]')) {
					var arrayKey = key.slice(0, -2);
					if (!data[arrayKey]) {
						data[arrayKey] = [];
					}
					data[arrayKey].push(value);
				} else {
					data[key] = value;
				}
			});

			return data;
		},

		/**
		 * Clear errors
		 *
		 * @param {number} formId Form ID
		 */
		clearErrors: function(formId) {
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			// Hide global errors
			var errorBox = formInfo.target.querySelector('.fplant-errors');
			if (errorBox) {
				errorBox.style.display = 'none';
				errorBox.innerHTML = '';
			}

			// Hide success message
			var successBox = formInfo.target.querySelector('.fplant-success');
			if (successBox) {
				successBox.style.display = 'none';
			}

			// Clear field errors (default layout)
			var fieldGroups = formInfo.target.querySelectorAll('.fplant-field-group');
			fieldGroups.forEach(function(group) {
				group.classList.remove('fplant-field-has-error');
				var errorEl = group.querySelector('.fplant-field-error');
				if (errorEl) {
					errorEl.style.display = 'none';
					errorEl.textContent = '';
				}
			});

			// Clear field errors (for HTML template [fplant_field_error] shortcode)
			var fieldErrorEls = formInfo.target.querySelectorAll('[data-field-error]');
			fieldErrorEls.forEach(function(errorEl) {
				// Remove dynamically created error elements from DOM
				if (errorEl.classList.contains('fplant-field-error-dynamic')) {
					errorEl.remove();
				} else {
					errorEl.style.display = 'none';
					errorEl.textContent = '';
				}
			});
		},

		/**
		 * Show field errors
		 *
		 * Same behavior as form.js: display errors below fields
		 *
		 * @param {number} formId Form ID
		 * @param {object} errors Error object {fieldName: 'error message'}
		 */
		showFieldErrors: function(formId, errors) {
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			for (var fieldName in errors) {
				if (errors.hasOwnProperty(fieldName)) {
					var errorDisplayed = false;

					// Method 1: Find .fplant-field-group[data-field-name] for default layout
					var group = formInfo.target.querySelector('.fplant-field-group[data-field-name="' + fieldName + '"]');
					if (group) {
						var errorEl = group.querySelector('.fplant-field-error');
						if (errorEl) {
							errorEl.textContent = errors[fieldName];
							errorEl.style.display = 'block';
							errorDisplayed = true;
						}
						group.classList.add('fplant-field-has-error');
					}

					// Method 2: Find [data-field-error] element for HTML template
					var standaloneErrors = formInfo.target.querySelectorAll('[data-field-error="' + fieldName + '"]');
					if (standaloneErrors.length) {
						standaloneErrors.forEach(function(el) {
							el.textContent = errors[fieldName];
							el.style.display = 'block';
						});
						errorDisplayed = true;
					}

					// Method 3: If neither found, dynamically create error element after input field
					if (!errorDisplayed) {
						var fields = formInfo.target.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');
						var lastField = fields[fields.length - 1];
						if (lastField) {
							// Reuse existing dynamic error element if present
							var dynamicError = lastField.nextElementSibling;
							if (!dynamicError || !dynamicError.classList.contains('fplant-field-error-dynamic')) {
								dynamicError = document.createElement('div');
								dynamicError.className = 'fplant-field-error fplant-field-error-dynamic';
								dynamicError.setAttribute('data-field-error', fieldName);
								lastField.insertAdjacentElement('afterend', dynamicError);
							}
							dynamicError.textContent = errors[fieldName];
							dynamicError.style.display = 'block';
						}
					}
				}
			}

			// Scroll to first error field
			var firstError = formInfo.target.querySelector('.fplant-field-error[style*="block"]');
			if (firstError) {
				firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		},

		/**
		 * Show global error
		 *
		 * @param {number} formId Form ID
		 * @param {string} message Error message
		 */
		showError: function(formId, message) {
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var errorBox = formInfo.target.querySelector('.fplant-errors');
			if (errorBox) {
				errorBox.innerHTML = '<p>' + this.escapeHtml(message) + '</p>';
				errorBox.style.display = 'block';
			}
		},

		/**
		 * Show global error in HTML format
		 *
		 * @param {number} formId Form ID
		 * @param {string} html Error HTML (already escaped)
		 */
		showErrorHtml: function(formId, html) {
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var errorBox = formInfo.target.querySelector('.fplant-errors');
			if (errorBox) {
				errorBox.innerHTML = html;
				errorBox.style.display = 'block';
			}
		},

		/**
		 * Show success message
		 *
		 * @param {number} formId Form ID
		 * @param {string} content Success message or HTML
		 * @param {boolean} isHtml Whether to display as HTML (default: false)
		 */
		showSuccess: function(formId, content, isHtml) {
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			// Hide form
			var form = formInfo.target.querySelector('.fplant-form');
			if (form) {
				form.style.display = 'none';
			}

			if (isHtml) {
				// Show custom completion page HTML (same behavior as form.js)
				var customPage = document.createElement('div');
				customPage.className = 'fplant-custom-success-page';
				customPage.innerHTML = content;
				// Insert after form
				if (form) {
					form.insertAdjacentElement('afterend', customPage);
				} else {
					formInfo.target.appendChild(customPage);
				}
			} else {
				// Show simple message
				var successBox = formInfo.target.querySelector('.fplant-success');
				if (successBox) {
					successBox.innerHTML = '<p>' + this.escapeHtml(content) + '</p>';
					successBox.style.display = 'block';
				}
			}
		},

		/**
		 * HTML escape
		 *
		 * @param {string} str String
		 * @return {string} Escaped string
		 */
		escapeHtml: function(str) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		}
	};
})();
