/**
 * Form Plant - Tools page scripts
 *
 * @package Form_Plant
 */

(function ($) {
	'use strict';

	$(function () {
		// Toggle form checklist visibility
		$('input[name="fplant_export_scope"]').on('change', function () {
			if ($(this).val() === 'selected') {
				$('#fplant-export-form-list').show();
			} else {
				$('#fplant-export-form-list').hide();
			}
		});

		// Export
		$('#fplant-export-btn').on('click', function () {
			var $btn = $(this);
			var scope = $('input[name="fplant_export_scope"]:checked').val();
			var formIds = [];

			if (scope === 'selected') {
				$('input[name="fplant_export_form_ids[]"]:checked').each(function () {
					formIds.push($(this).val());
				});

				if (formIds.length === 0) {
					showMessage('#fplant-export-message', fplantTools.i18n.selectForm, 'error');
					return;
				}
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: fplantTools.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_export_forms',
					nonce: fplantTools.nonce,
					form_ids: formIds,
				},
				success: function (response) {
					if (response.success) {
						var json = JSON.stringify(response.data, null, 2);
						var blob = new Blob([json], { type: 'application/json' });
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						var now = new Date();
						var timestamp =
							now.getFullYear() +
							String(now.getMonth() + 1).padStart(2, '0') +
							String(now.getDate()).padStart(2, '0') +
							String(now.getHours()).padStart(2, '0') +
							String(now.getMinutes()).padStart(2, '0');

						a.href = url;
						a.download = 'form-plant-export-' + timestamp + '.json';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);

						showMessage('#fplant-export-message', fplantTools.i18n.exportSuccess, 'success');
					} else {
						showMessage('#fplant-export-message', response.data.message, 'error');
					}
				},
				error: function () {
					showMessage('#fplant-export-message', fplantTools.i18n.errorOccurred, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false);
				},
			});
		});

		// Import
		$('#fplant-import-btn').on('click', function () {
			var $btn = $(this);
			var fileInput = document.getElementById('fplant-import-file');
			var file = fileInput.files[0];

			if (!file) {
				showMessage('#fplant-import-message', fplantTools.i18n.selectFile, 'error');
				return;
			}

			if (!file.name.endsWith('.json')) {
				showMessage('#fplant-import-message', fplantTools.i18n.invalidFile, 'error');
				return;
			}

			if (!confirm(fplantTools.i18n.confirmImport)) {
				return;
			}

			$btn.prop('disabled', true);

			var formData = new FormData();
			formData.append('action', 'fplant_import_forms');
			formData.append('nonce', fplantTools.nonce);
			formData.append('import_file', file);

			$.ajax({
				url: fplantTools.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						showMessage('#fplant-import-message', response.data.message, 'success');
						fileInput.value = '';
					} else {
						showMessage('#fplant-import-message', response.data.message, 'error');
					}
				},
				error: function () {
					showMessage('#fplant-import-message', fplantTools.i18n.errorOccurred, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false);
				},
			});
		});

		/**
		 * Show a notice message
		 */
		function showMessage(selector, message, type) {
			var cssClass =
				type === 'success' ? 'notice-success' : 'notice-error';
			$(selector)
				.removeClass('notice-success notice-error')
				.addClass(cssClass)
				.html('<p>' + $('<span>').text(message).html() + '</p>')
				.show();
		}
	});
})(jQuery);
