(function($) {
	'use strict';

	$(document).ready(function() {
		// Apply all filters and navigate
		function applyFilters() {
			let url = fplantSubmissionList.adminUrl;
			const formId = $('#fplant-form-filter').val();
			const dateFrom = $('#fplant-date-from').val();
			const dateTo = $('#fplant-date-to').val();
			const search = $('#fplant-search').val();

			if (formId) url += '&form_id=' + formId;
			if (dateFrom) url += '&date_from=' + dateFrom;
			if (dateTo) url += '&date_to=' + dateTo;
			if (search) url += '&search=' + encodeURIComponent(search);

			window.location.href = url;
		}

		// Search button click - applies all filters
		$('#fplant-search-button').on('click', function() {
			applyFilters();
		});

		// Search on Enter key in any filter input
		$('#fplant-search, #fplant-date-from, #fplant-date-to').on('keypress', function(e) {
			if (e.which === 13) {
				applyFilters();
			}
		});

		// Select all / Deselect all
		$('#fplant-select-all').on('change', function() {
			$('.fplant-submission-checkbox').prop('checked', $(this).prop('checked'));
		});

		// Individual checkbox
		$('.fplant-submission-checkbox').on('change', function() {
			// Check select-all if all checkboxes are selected
			const allChecked = $('.fplant-submission-checkbox:checked').length === $('.fplant-submission-checkbox').length;
			$('#fplant-select-all').prop('checked', allChecked);
		});

		// Individual delete
		$('.fplant-delete-submission').on('click', function() {
			const submissionId = $(this).data('submission-id');

			if (!confirm(fplantSubmissionList.i18n.deleteConfirm)) {
				return;
			}

			deleteSubmissions([submissionId]);
		});

		// Bulk delete
		$('#fplant-bulk-delete').on('click', function() {
			const submissionIds = [];
			$('.fplant-submission-checkbox:checked').each(function() {
				submissionIds.push($(this).val());
			});

			if (submissionIds.length === 0) {
				alert(fplantSubmissionList.i18n.selectItems);
				return;
			}

			const message = fplantSubmissionList.i18n.deleteSelected + ' ' + submissionIds.length + ' ' + fplantSubmissionList.i18n.submissions;
			if (!confirm(message)) {
				return;
			}

			deleteSubmissions(submissionIds);
		});

		// Delete process
		function deleteSubmissions(submissionIds) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fplant_delete_submissions',
					nonce: fplantSubmissionList.nonce,
					submission_ids: submissionIds
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || fplantSubmissionList.i18n.deleteFailed);
					}
				},
				error: function() {
					alert(fplantSubmissionList.i18n.errorOccurred);
				}
			});
		}
	});
})(jQuery);
