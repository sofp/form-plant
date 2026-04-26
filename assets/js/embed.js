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
	 * FPlantEmbed - Embed SDK
	 */
	window.FPlantEmbed = {
		/**
		 * Store initialized forms
		 */
		forms: {},

		/**
		 * Whether CSS has been loaded
		 */
		cssLoaded: false,

		/**
		 * Whether reCAPTCHA v3 script has been loaded
		 */
		recaptchaLoaded: false,

		/**
		 * reCAPTCHA v3 configuration
		 */
		recaptchaConfig: {},

		/**
		 * Whether reCAPTCHA v2 script has been loaded
		 */
		recaptchaV2Loaded: false,

		/**
		 * reCAPTCHA v2 configuration per form
		 */
		recaptchaV2Config: {},

		/**
		 * reCAPTCHA v2 widget IDs per form
		 */
		recaptchaV2WidgetIds: {},

		/**
		 * Whether zxcvbn script has been loaded
		 */
		zxcvbnLoaded: false,

		/**
		 * Whether Turnstile script has been loaded
		 */
		turnstileLoaded: false,

		/**
		 * Turnstile configuration per form
		 */
		turnstileConfig: {},

		/**
		 * Turnstile widget IDs per form
		 */
		turnstileWidgetIds: {},

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
				console.error('FPlantEmbed: Target element not found:', targetSelector);
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
					console.error('FPlantEmbed:', error);
					return;
				}

				if (!response.success || !response.data) {
					target.innerHTML = '<div class="fplant-embed-error">' + (response.message || 'An error occurred') + '</div>';
					return;
				}

				// Render form HTML
				target.innerHTML = response.data.html;

				// Save CAPTCHA configuration
				if (response.captcha) {
					if (response.captcha.type === 'turnstile' && response.captcha.turnstileSiteKey) {
						self.turnstileConfig[formId] = response.captcha;
						self.loadTurnstile();
					} else if (response.captcha.type === 'recaptcha_v2' && response.captcha.recaptchaV2SiteKey) {
						self.recaptchaV2Config[formId] = {
							enabled: true,
							siteKey: response.captcha.recaptchaV2SiteKey
						};
						self.loadRecaptchaV2();
					} else if (response.captcha.type === 'recaptcha' && response.captcha.recaptchaSiteKey) {
						self.recaptchaConfig[formId] = {
							enabled: true,
							siteKey: response.captcha.recaptchaSiteKey
						};
						self.loadRecaptcha(response.captcha.recaptchaSiteKey);
					}
				} else if (response.recaptcha && response.recaptcha.enabled) {
					// Backward compatibility
					self.recaptchaConfig[formId] = response.recaptcha;
					self.loadRecaptcha(response.recaptcha.siteKey);
				}

				// Save form info
				self.forms[formId] = {
					target: target,
					siteUrl: siteUrl,
					formData: response.data,
					options: options
				};

				// Set timestamp for time-based spam check
				var tsField = target.querySelector('.fplant-form-ts');
				if (tsField) {
					tsField.value = Math.floor(Date.now() / 1000);
				}

				// Set up event listeners
				self.attachEventListeners(formId);

				// Initialize password features
				self.initPasswordToggles(formId);
				self.initPasswordStrengthMeters(formId);

				// Render reCAPTCHA v2 widget for non-confirmation forms (if script already loaded)
				if (self.recaptchaV2Config[formId]) {
					var formElV2 = target.querySelector('.fplant-form');
					var useConfV2 = formElV2 ? formElV2.getAttribute('data-use-confirmation') === '1' : false;
					if (!useConfV2 && typeof grecaptcha !== 'undefined') {
						self.renderRecaptchaV2Widget(formId, formElV2);
					}
				}

				// Render Turnstile widget for non-confirmation forms (if script already loaded)
				if (self.turnstileConfig[formId]) {
					var formEl = target.querySelector('.fplant-form');
					var useConf = formEl ? formEl.getAttribute('data-use-confirmation') === '1' : false;
					if (!useConf && typeof turnstile !== 'undefined') {
						self.renderTurnstileWidget(formId, formEl);
					}
				}

				// Dispatch init event
				self.dispatchFplantEvent(formId, 'fplant:init', { formId: formId });
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
		 * Load reCAPTCHA v2 script
		 */
		loadRecaptchaV2: function() {
			if (this.recaptchaV2Loaded) {
				return;
			}

			var self = this;
			var script = document.createElement('script');
			script.src = 'https://www.google.com/recaptcha/api.js?onload=fplantRecaptchaV2Ready&render=explicit';
			script.async = true;
			script.defer = true;
			document.head.appendChild(script);

			// Global callback for when reCAPTCHA v2 is ready
			window.fplantRecaptchaV2Ready = function() {
				self.onRecaptchaV2Ready();
			};

			this.recaptchaV2Loaded = true;
		},

		/**
		 * Called when reCAPTCHA v2 script has loaded
		 * Renders widgets for forms that need them (non-confirmation mode)
		 */
		onRecaptchaV2Ready: function() {
			for (var formId in this.recaptchaV2Config) {
				if (this.recaptchaV2Config.hasOwnProperty(formId) && this.forms[formId]) {
					var form = this.forms[formId].target.querySelector('.fplant-form');
					if (form) {
						var useConfirmation = form.getAttribute('data-use-confirmation') === '1';
						if (!useConfirmation && !this.recaptchaV2WidgetIds[formId]) {
							this.renderRecaptchaV2Widget(formId, form);
						}
					}
				}
			}
		},

		/**
		 * Render reCAPTCHA v2 widget inside a container
		 *
		 * @param {number} formId Form ID
		 * @param {HTMLElement} parentElement The form or confirmation element
		 */
		renderRecaptchaV2Widget: function(formId, parentElement) {
			if (typeof grecaptcha === 'undefined') {
				return;
			}

			var config = this.recaptchaV2Config[formId];
			if (!config || !config.siteKey) {
				return;
			}

			// Remove existing widget if any
			this.removeRecaptchaV2Widget(formId);

			// Create container
			var container = document.createElement('div');
			container.className = 'fplant-recaptcha-v2-container';
			container.style.marginBottom = '15px';

			// Insert before submit button
			var submitButton = parentElement.querySelector('.fplant-confirm-submit-button, .fplant-submit-button, button[type="submit"]');
			var footer = parentElement.querySelector('.fplant-confirmation-footer');
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

			var form = this.forms[formId].target.querySelector('.fplant-form');
			this.recaptchaV2WidgetIds[formId] = grecaptcha.render(container, {
				sitekey: config.siteKey,
				callback: function(token) {
					var tokenInput = form ? form.querySelector('.fplant-captcha-token') : null;
					if (tokenInput) {
						tokenInput.value = token;
					}
					if (submitButton) {
						submitButton.disabled = false;
					}
				},
				'expired-callback': function() {
					var tokenInput = form ? form.querySelector('.fplant-captcha-token') : null;
					if (tokenInput) {
						tokenInput.value = '';
					}
					if (submitButton) {
						submitButton.disabled = true;
					}
				}
			});
		},

		/**
		 * Remove existing reCAPTCHA v2 widget
		 *
		 * @param {number} formId Form ID
		 */
		removeRecaptchaV2Widget: function(formId) {
			if (this.recaptchaV2WidgetIds[formId] != null && typeof grecaptcha !== 'undefined') {
				try {
					grecaptcha.reset(this.recaptchaV2WidgetIds[formId]);
				} catch (e) {
					// Widget may already be removed
				}
				delete this.recaptchaV2WidgetIds[formId];
			}
			// Remove container elements
			var formInfo = this.forms[formId];
			if (formInfo && formInfo.target) {
				formInfo.target.querySelectorAll('.fplant-recaptcha-v2-container').forEach(function(el) { el.remove(); });
			}
			if (formInfo && formInfo.confirmation) {
				formInfo.confirmation.querySelectorAll('.fplant-recaptcha-v2-container').forEach(function(el) { el.remove(); });
			}
		},

		/**
		 * Get reCAPTCHA v2 token
		 *
		 * @param {number} formId Form ID
		 * @param {function} callback Callback(error, token)
		 */
		getRecaptchaV2Token: function(formId, callback) {
			var config = this.recaptchaV2Config[formId];
			if (!config || !config.siteKey) {
				callback(null, null);
				return;
			}

			if (typeof grecaptcha === 'undefined') {
				callback(new Error('reCAPTCHA v2 is not loaded'), null);
				return;
			}

			// Get token from already-rendered widget
			if (this.recaptchaV2WidgetIds[formId] != null) {
				var token = grecaptcha.getResponse(this.recaptchaV2WidgetIds[formId]);
				if (token) {
					callback(null, token);
					return;
				}
			}

			// Also check hidden token input
			var formInfo = this.forms[formId];
			var form = formInfo ? formInfo.target.querySelector('.fplant-form') : null;
			if (form) {
				var tokenInput = form.querySelector('.fplant-captcha-token');
				if (tokenInput && tokenInput.value) {
					callback(null, tokenInput.value);
					return;
				}
			}

			callback(new Error('Please complete the reCAPTCHA checkbox'), null);
		},

		/**
		 * Load Turnstile script
		 */
		loadTurnstile: function() {
			if (this.turnstileLoaded) {
				return;
			}

			var self = this;
			var script = document.createElement('script');
			script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
			script.async = true;
			script.defer = true;
			script.onload = function() {
				self.onTurnstileReady();
			};
			document.head.appendChild(script);

			this.turnstileLoaded = true;
		},

		/**
		 * Called when Turnstile script has loaded
		 * Renders widgets for forms that need them (non-confirmation mode)
		 */
		onTurnstileReady: function() {
			for (var formId in this.turnstileConfig) {
				if (this.turnstileConfig.hasOwnProperty(formId) && this.forms[formId]) {
					var form = this.forms[formId].target.querySelector('.fplant-form');
					if (form) {
						var useConfirmation = form.getAttribute('data-use-confirmation') === '1';
						if (!useConfirmation && !this.turnstileWidgetIds[formId]) {
							this.renderTurnstileWidget(formId, form);
						}
					}
				}
			}
		},

		/**
		 * Render Turnstile widget inside a container
		 *
		 * @param {number} formId Form ID
		 * @param {HTMLElement} parentElement The form or confirmation element
		 */
		renderTurnstileWidget: function(formId, parentElement) {
			if (typeof turnstile === 'undefined') {
				return;
			}

			var config = this.turnstileConfig[formId];
			if (!config || !config.turnstileSiteKey) {
				return;
			}

			// Remove existing widget if any
			this.removeTurnstileWidget(formId);

			// Create container for Turnstile widget
			var container = document.createElement('div');
			container.className = 'fplant-turnstile-container';
			container.style.marginBottom = '15px';

			// Insert before submit button
			var submitButton = parentElement.querySelector('.fplant-confirm-submit-button, .fplant-submit-button, button[type="submit"]');
			var footer = parentElement.querySelector('.fplant-confirmation-footer');
			if (footer) {
				footer.parentNode.insertBefore(container, footer);
			} else if (submitButton) {
				submitButton.parentNode.insertBefore(container, submitButton);
			} else {
				parentElement.appendChild(container);
			}

			this.turnstileWidgetIds[formId] = turnstile.render(container, {
				sitekey: config.turnstileSiteKey
			});
		},

		/**
		 * Remove existing Turnstile widget
		 *
		 * @param {number} formId Form ID
		 */
		removeTurnstileWidget: function(formId) {
			if (this.turnstileWidgetIds[formId] != null && typeof turnstile !== 'undefined') {
				try {
					turnstile.remove(this.turnstileWidgetIds[formId]);
				} catch (e) {
					// Widget may already be removed
				}
				this.turnstileWidgetIds[formId] = null;
			}

			var formInfo = this.forms[formId];
			if (formInfo) {
				formInfo.target.querySelectorAll('.fplant-turnstile-container').forEach(function(el) {
					el.remove();
				});
			}
		},

		/**
		 * Get Turnstile token from rendered widget
		 *
		 * @param {number} formId Form ID
		 * @param {function} callback Callback(error, token)
		 */
		getTurnstileToken: function(formId, callback) {
			var config = this.turnstileConfig[formId];
			if (!config || !config.turnstileSiteKey) {
				callback(null, null);
				return;
			}

			if (typeof turnstile === 'undefined') {
				callback(new Error('Cloudflare Turnstile not loaded'), null);
				return;
			}

			// Get token from already-rendered widget
			if (this.turnstileWidgetIds[formId] != null) {
				var token = turnstile.getResponse(this.turnstileWidgetIds[formId]);
				if (token) {
					callback(null, token);
					return;
				}
			}

			// Fallback: render a hidden widget
			var formInfo = this.forms[formId];
			var form = formInfo ? formInfo.target.querySelector('.fplant-form') : null;
			if (!form) {
				callback(new Error('Form not found'), null);
				return;
			}

			var container = document.createElement('div');
			container.className = 'fplant-turnstile-container';
			container.style.position = 'absolute';
			container.style.left = '-9999px';
			form.appendChild(container);

			try {
				turnstile.render(container, {
					sitekey: config.turnstileSiteKey,
					callback: function(token) {
						container.remove();
						callback(null, token);
					},
					'error-callback': function() {
						container.remove();
						callback(new Error('Turnstile verification failed'), null);
					}
				});
			} catch (error) {
				container.remove();
				callback(error, null);
			}
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

			// Dispatch beforeSubmit event (cancelable)
			if (!this.dispatchFplantEvent(formId, 'fplant:beforeSubmit', { formId: formId }, true)) {
				return;
			}

			// Set to submitting state
			submitButton.disabled = true;
			submitButton.textContent = 'Submitting...';

			// Dispatch loading event (loading started)
			this.dispatchFplantEvent(formId, 'fplant:loading', { formId: formId, loading: true });

			// Clear errors
			this.clearErrors(formId);

			// Collect form data (separating files from text data)
			var collected = this.collectFormData(form);

			// Save form data (used when submitting from confirmation screen)
			formInfo.pendingData = collected.data;
			formInfo.pendingFiles = collected.files;

			if (useConfirmation) {
				// Confirmation screen enabled: validation and get confirmation HTML
				var apiUrl = formInfo.siteUrl + '/wp-json/form-plant/v1/embed/validate';

				var fd = self.buildFormData(formId, collected.data, collected.files);

				self.postFormData(apiUrl, fd, function(error, response) {
					submitButton.disabled = false;
					submitButton.textContent = formInfo.formData.settings.input_submit_text || 'Confirm';

					// Dispatch loading event (loading ended)
					self.dispatchFplantEvent(formId, 'fplant:loading', { formId: formId, loading: false });

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

			// Render reCAPTCHA v2 widget on confirmation screen
			if (self.recaptchaV2Config[formId]) {
				self.renderRecaptchaV2Widget(formId, confirmation);
			}

			// Render Turnstile widget on confirmation screen
			if (self.turnstileConfig[formId]) {
				self.renderTurnstileWidget(formId, confirmation);
			}

			// Dispatch confirmationShow event
			self.dispatchFplantEvent(formId, 'fplant:confirmationShow', { formId: formId });
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

			// Remove reCAPTCHA v2 widget
			if (this.recaptchaV2Config[formId]) {
				this.removeRecaptchaV2Widget(formId);
			}

			// Remove Turnstile widget
			if (this.turnstileConfig[formId]) {
				this.removeTurnstileWidget(formId);
			}

			// Remove confirmation screen
			if (formInfo.confirmation) {
				formInfo.confirmation.remove();
				formInfo.confirmation = null;
			}

			// Show form
			form.style.display = '';

			// Re-render reCAPTCHA v2 widget on form (for non-confirmation retry)
			if (this.recaptchaV2Config[formId]) {
				var useConfirmationV2 = form.getAttribute('data-use-confirmation') === '1';
				if (!useConfirmationV2) {
					this.renderRecaptchaV2Widget(formId, form);
				}
			}

			// Re-render Turnstile widget on form (for non-confirmation retry)
			if (this.turnstileConfig[formId]) {
				var useConfirmation = form.getAttribute('data-use-confirmation') === '1';
				if (!useConfirmation) {
					this.renderTurnstileWidget(formId, form);
				}
			}

			// Dispatch confirmationHide event
			this.dispatchFplantEvent(formId, 'fplant:confirmationHide', { formId: formId });
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

			// Dispatch loading event (loading started)
			this.dispatchFplantEvent(formId, 'fplant:loading', { formId: formId, loading: true });

			// Use saved form data
			var data = formInfo.pendingData;
			var files = formInfo.pendingFiles || {};
			if (!data) {
				var collected = this.collectFormData(form);
				data = collected.data;
				files = collected.files;
			}

			// Get CAPTCHA token before submitting
			var getCaptchaToken;
			if (self.turnstileConfig[formId]) {
				getCaptchaToken = function(cb) { self.getTurnstileToken(formId, cb); };
			} else if (self.recaptchaV2Config[formId]) {
				getCaptchaToken = function(cb) { self.getRecaptchaV2Token(formId, cb); };
			} else if (self.recaptchaConfig[formId]) {
				getCaptchaToken = function(cb) { self.getRecaptchaToken(formId, cb); };
			} else {
				getCaptchaToken = function(cb) { cb(null, null); };
			}

			getCaptchaToken(function(captchaError, captchaToken) {
				if (captchaError) {
					if (confirmSubmitButton) {
						confirmSubmitButton.disabled = false;
						confirmSubmitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					} else {
						submitButton.disabled = false;
						submitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					}
					self.showError(formId, 'CAPTCHA verification failed. Please reload the page.');
					return;
				}

				// Submit to REST API
				var apiUrl = formInfo.siteUrl + '/wp-json/form-plant/v1/embed/submit';

				var fd = self.buildFormData(formId, data, files);

				// Add CAPTCHA token if available
				if (captchaToken) {
					fd.append('captcha_token', captchaToken);
				}

				self.postFormData(apiUrl, fd, function(error, response) {
					if (confirmSubmitButton) {
						confirmSubmitButton.disabled = false;
						confirmSubmitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					} else {
						submitButton.disabled = false;
						submitButton.textContent = formInfo.formData.settings.input_submit_text || 'Submit';
					}

					// Dispatch loading event (loading ended)
					self.dispatchFplantEvent(formId, 'fplant:loading', { formId: formId, loading: false });

					if (error) {
						// Dispatch submitError event
						self.dispatchFplantEvent(formId, 'fplant:submitError', { formId: formId, error: error });

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

						// Dispatch success event
						self.dispatchFplantEvent(formId, 'fplant:success', { formId: formId, response: response });

						// Call optional callback
						if (formInfo.options.onSuccess) {
							formInfo.options.onSuccess(response);
						}
					} else {
						// Dispatch submitError event
						self.dispatchFplantEvent(formId, 'fplant:submitError', { formId: formId, error: response });

						self.showError(formId, response.message || 'An error occurred');
					}
				});
			});
		},

		/**
		 * Collect form data (separating files from text data)
		 *
		 * @param {HTMLFormElement} form Form element
		 * @return {object} { data: {key: value}, files: {key: File} }
		 */
		collectFormData: function(form) {
			var data = {};
			var files = {};
			var formData = new FormData(form);

			formData.forEach(function(value, key) {
				if (value instanceof File) {
					// Store file references separately (File objects can't be JSON-serialized)
					if (value.name && value.size > 0) {
						files[key] = value;
					}
				} else if (key.endsWith('[]')) {
					// Array case (checkboxes, etc.)
					var arrayKey = key.slice(0, -2);
					if (!data[arrayKey]) {
						data[arrayKey] = [];
					}
					data[arrayKey].push(value);
				} else {
					data[key] = value;
				}
			});

			return { data: data, files: files };
		},

		/**
		 * Build FormData for API request (supports file uploads)
		 *
		 * @param {number} formId Form ID
		 * @param {object} data Text field data
		 * @param {object} files File field data {key: File}
		 * @return {FormData}
		 */
		buildFormData: function(formId, data, files) {
			var fd = new FormData();
			fd.append('form_id', formId);
			fd.append('data', JSON.stringify(data));

			// Add file fields
			for (var key in files) {
				if (files.hasOwnProperty(key)) {
					fd.append(key, files[key]);
				}
			}

			return fd;
		},

		/**
		 * POST FormData (multipart/form-data, supports file uploads)
		 *
		 * @param {string} url URL
		 * @param {FormData} formData FormData object
		 * @param {function} callback Callback(error, data)
		 */
		postFormData: function(url, formData, callback) {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', url, true);
			// Do not set Content-Type - browser sets it with multipart boundary automatically

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

			xhr.send(formData);
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

			// Dispatch error event
			this.dispatchFplantEvent(formId, 'fplant:error', { formId: formId, errors: errors });
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

			// Dispatch error event
			this.dispatchFplantEvent(formId, 'fplant:error', { formId: formId, message: message });
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
		},

		/**
		 * Initialize password toggle buttons
		 *
		 * @param {number} formId Form ID
		 */
		initPasswordToggles: function(formId) {
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var toggles = formInfo.target.querySelectorAll('.fplant-password-toggle');
			toggles.forEach(function(toggle) {
				toggle.addEventListener('click', function() {
					var wrapper = toggle.closest('.fplant-password-input-wrapper');
					var input = wrapper ? wrapper.querySelector('input') : null;
					if (!input) return;

					var icon = toggle.querySelector('.dashicons');
					if (input.type === 'password') {
						input.type = 'text';
						if (icon) {
							icon.classList.remove('dashicons-visibility');
							icon.classList.add('dashicons-hidden');
						}
						toggle.setAttribute('aria-label', 'Hide password');
					} else {
						input.type = 'password';
						if (icon) {
							icon.classList.remove('dashicons-hidden');
							icon.classList.add('dashicons-visibility');
						}
						toggle.setAttribute('aria-label', 'Show password');
					}
				});
			});
		},

		/**
		 * Initialize password strength meters
		 *
		 * @param {number} formId Form ID
		 */
		initPasswordStrengthMeters: function(formId) {
			var self = this;
			var formInfo = this.forms[formId];
			if (!formInfo) return;

			var wrappers = formInfo.target.querySelectorAll('.fplant-password-wrapper[data-strength-meter="1"]');
			if (!wrappers.length) return;

			// Load zxcvbn script then initialize
			this.loadZxcvbn(formInfo.siteUrl, function() {
				wrappers.forEach(function(wrapper) {
					var input = wrapper.querySelector('input');
					var bar = wrapper.querySelector('.fplant-password-strength-bar');
					var text = wrapper.querySelector('.fplant-password-strength-text');
					if (!input || !bar || !text) return;

					input.addEventListener('input', function() {
						if (!input.value) {
							bar.style.width = '0%';
							bar.className = 'fplant-password-strength-bar';
							text.textContent = '';
							return;
						}

						if (typeof zxcvbn === 'undefined') return;

						var result = zxcvbn(input.value);
						var labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
						var classes = ['very-weak', 'weak', 'fair', 'strong', 'very-strong'];
						var widths = ['20%', '40%', '60%', '80%', '100%'];

						bar.style.width = widths[result.score];
						bar.className = 'fplant-password-strength-bar fplant-strength-' + classes[result.score];
						text.textContent = labels[result.score];
					});
				});
			});
		},

		/**
		 * Dispatch custom event on the form element
		 *
		 * @param {number} formId Form ID
		 * @param {string} eventName Event name
		 * @param {object} detail Event detail
		 * @param {boolean} cancelable Whether the event is cancelable
		 * @return {boolean} Whether the event was not cancelled
		 */
		dispatchFplantEvent: function(formId, eventName, detail, cancelable) {
			var formInfo = this.forms[formId];
			if (!formInfo) return true;
			var form = formInfo.target.querySelector('.fplant-form');
			if (!form) return true;
			var event = new CustomEvent(eventName, {
				detail: detail || {},
				bubbles: true,
				cancelable: cancelable || false
			});
			return form.dispatchEvent(event);
		},

		/**
		 * Load zxcvbn script from WordPress
		 *
		 * @param {string} siteUrl WordPress site URL
		 * @param {function} callback Callback when loaded
		 */
		loadZxcvbn: function(siteUrl, callback) {
			if (typeof zxcvbn !== 'undefined') {
				callback();
				return;
			}

			if (this.zxcvbnLoaded) {
				// Already loading, wait for it
				var checkInterval = setInterval(function() {
					if (typeof zxcvbn !== 'undefined') {
						clearInterval(checkInterval);
						callback();
					}
				}, 100);
				return;
			}

			this.zxcvbnLoaded = true;

			var script = document.createElement('script');
			script.src = siteUrl + '/wp-includes/js/zxcvbn.min.js';
			script.async = true;
			script.onload = function() {
				callback();
			};
			script.onerror = function() {
				console.error('FPlantEmbed: Failed to load zxcvbn');
			};
			document.head.appendChild(script);
		}
	};
})();
