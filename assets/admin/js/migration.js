/**
 * Form Plant — MW WP Form Migration UI
 *
 * @package Form_Plant
 * @since 1.2.0
 */

/* global jQuery, fplantMigrationData */

jQuery(function ($) {
	'use strict';

	var data = window.fplantMigrationData || {};
	var i18n = data.i18n || {};

	var $tbody     = $('#fplant-migration-tbody');
	var $table     = $('#fplant-migration-table');
	var $status    = $('#fplant-migration-list-status');
	var $runBtn    = $('#fplant-migration-run-btn');
	var $checkAll  = $('#fplant-migration-check-all');
	var $report    = $('#fplant-migration-report');
	var $modal     = $('#fplant-migration-log-modal');
	var $modalBody = $('#fplant-migration-log-modal-body');
	var $modalTitle = $('#fplant-migration-log-modal-title');

	if (!$tbody.length) {
		return;
	}

	function escapeHtml(str) {
		if (str === null || typeof str === 'undefined') {
			return '';
		}
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function loadForms() {
		$table.hide();
		$status.show().html(
			'<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span>' +
			escapeHtml(i18n.loading || 'Loading list…')
		);
		$tbody.empty();
		$runBtn.prop('disabled', true);

		$.ajax({
			url: data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fplant_list_mwwpform_forms',
				nonce: data.nonce
			},
			dataType: 'json'
		})
			.done(function (resp) {
				if (!resp || !resp.success) {
					renderLoadError(resp && resp.data && resp.data.message);
					return;
				}
				renderForms(resp.data.forms || []);
			})
			.fail(function () {
				renderLoadError(i18n.networkError || 'A network error occurred.');
			});
	}

	function renderLoadError(message) {
		$status.html('<div class="notice notice-error inline"><p>' + escapeHtml(message || i18n.loadError || 'Failed to load the list.') + '</p></div>');
	}

	function renderForms(forms) {
		$status.hide();
		$tbody.empty();

		if (!forms.length) {
			$tbody.append(
				'<tr><td colspan="4"><em>' +
					escapeHtml(i18n.noForms || 'No MW WP Form forms were found.') +
					'</em></td></tr>'
			);
			$table.show();
			return;
		}

		forms.forEach(function (form) {
			var statusHtml;
			var migrations = form.migrations || [];
			if (migrations.length) {
				statusHtml = '<span class="fplant-migration-status fplant-migration-status-migrated">' +
					escapeHtml(i18n.statusMigrated || 'Migrated') +
					'</span>';
				statusHtml += '<ul class="fplant-migration-derived-list">';
				migrations.forEach(function (m) {
					var logLink = m.has_log
						? ' <a href="#" class="fplant-migration-view-log" data-form-id="' + escapeHtml(m.form_id) + '">' +
							escapeHtml(i18n.viewLog || 'View log') + '</a>'
						: '';
					statusHtml += '<li>' +
						'ID: <a href="' + escapeHtml(data.editFormUrlBase + m.form_id) + '">' +
						escapeHtml(m.form_id) +
						'</a>' +
						logLink +
					'</li>';
				});
				statusHtml += '</ul>';
			} else if (form.migrated_to) {
				// Backward compat: fallback when the migrations array is absent.
				statusHtml = '<span class="fplant-migration-status fplant-migration-status-migrated">' +
					escapeHtml(i18n.statusMigrated || 'Migrated') +
					' (ID: <a href="' + escapeHtml(data.editFormUrlBase + form.migrated_to) + '">' +
					escapeHtml(form.migrated_to) +
					'</a>)</span>';
			} else {
				statusHtml = '<span class="fplant-migration-status fplant-migration-status-pending">' +
					escapeHtml(i18n.statusPending || 'Not migrated') +
					'</span>';
			}

			$tbody.append(
				'<tr data-mw-id="' + escapeHtml(form.id) + '">' +
					'<th scope="row" class="check-column">' +
						'<input type="checkbox" class="fplant-migration-check" value="' + escapeHtml(form.id) + '" />' +
					'</th>' +
					'<td>' + escapeHtml(form.title) +
						' <span class="description">(ID: ' + escapeHtml(form.id) + ')</span></td>' +
					'<td>' + escapeHtml(form.field_count) + '</td>' +
					'<td>' + statusHtml + '</td>' +
				'</tr>'
			);
		});

		$table.show();
	}

	function getSelectedIds() {
		return $('.fplant-migration-check:checked').map(function () {
			return $(this).val();
		}).get();
	}

	function updateRunBtnState() {
		$runBtn.prop('disabled', !getSelectedIds().length);
	}

	function migrateAll(ids) {
		$report.empty();
		$runBtn.prop('disabled', true);

		var queue = ids.slice();
		var results = [];

		function next() {
			if (!queue.length) {
				renderReport(results);
				updateRunBtnState();
				loadForms();
				return;
			}

			var mwId = queue.shift();
			var $row = $('tr[data-mw-id="' + mwId + '"]');
			var $statusCell = $row.find('td:nth-child(4)');
			$statusCell.html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span>' + escapeHtml(i18n.statusRunning || 'Migrating…'));

			$.ajax({
				url: data.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fplant_run_mwwpform_migration',
					nonce: data.nonce,
					mw_form_id: mwId
				},
				dataType: 'json'
			})
				.done(function (resp) {
					if (!resp || !resp.success) {
						results.push({
							mw_form_id: mwId,
							status: 'failed',
							form_id: null,
							mw_title: '',
							warnings: [{
								level: 'error',
								code: 'request_failed',
								message: (resp && resp.data && resp.data.message) || (i18n.networkError || 'A network error occurred.')
							}]
						});
					} else {
						results.push(resp.data);
					}
					next();
				})
				.fail(function () {
					results.push({
						mw_form_id: mwId,
						status: 'failed',
						form_id: null,
						mw_title: '',
						warnings: [{ level: 'error', code: 'network', message: i18n.networkError || 'A network error occurred.' }]
					});
					next();
				});
		}

		next();
	}

	function renderReport(results) {
		if (!results.length) {
			$report.html('<p class="description">' + escapeHtml(i18n.noResults || 'No results.') + '</p>');
			return;
		}

		var html = '<div class="fplant-migration-report-summary"><strong>' +
			escapeHtml((i18n.summaryLabel || 'Result summary') + ': ') + '</strong>' +
			results.length + ' ' + escapeHtml(i18n.formsLabel || 'forms') +
		'</div>';

		results.forEach(function (r) {
			var statusClass = 'fplant-migration-result-' + r.status;
			var statusLabel;
			switch (r.status) {
				case 'success': statusLabel = i18n.statusSuccess || 'Success'; break;
				case 'partial': statusLabel = i18n.statusPartial || 'With warnings'; break;
				case 'failed':  statusLabel = i18n.statusFailed || 'Failed'; break;
				default:        statusLabel = r.status;
			}

			var title  = r.mw_title || ('MW Form ID: ' + r.mw_form_id);
			var formLink = r.form_id
				? ' → <a href="' + escapeHtml(data.editFormUrlBase + r.form_id) + '">' +
					escapeHtml(i18n.openNewForm || 'Open the generated Form Plant form') + '</a>'
				: '';

			var warningsHtml = '';
			if (r.warnings && r.warnings.length) {
				warningsHtml = '<ul class="fplant-migration-warnings">';
				r.warnings.forEach(function (w) {
					warningsHtml += '<li class="fplant-migration-warning fplant-migration-warning-' + escapeHtml(w.level) + '">' +
						'<span class="fplant-migration-warning-level">' + escapeHtml(w.level) + '</span> ' +
						escapeHtml(w.message) +
						'</li>';
				});
				warningsHtml += '</ul>';
			} else {
				warningsHtml = '<p class="description">' + escapeHtml(i18n.noWarnings || 'No warnings.') + '</p>';
			}

			html += '<div class="fplant-migration-result ' + escapeHtml(statusClass) + '">' +
				'<h4>' +
					'<span class="fplant-migration-result-status">[' + escapeHtml(statusLabel) + ']</span> ' +
					escapeHtml(title) +
					formLink +
				'</h4>' +
				warningsHtml +
				'</div>';
		});

		$report.html(html);
	}

	$(document).on('change', '#fplant-migration-check-all', function () {
		$('.fplant-migration-check').prop('checked', $(this).is(':checked'));
		updateRunBtnState();
	});

	$(document).on('change', '.fplant-migration-check', function () {
		updateRunBtnState();
	});

	$('#fplant-migration-refresh-btn').on('click', function (e) {
		e.preventDefault();
		loadForms();
	});

	$('#fplant-migration-run-btn').on('click', function (e) {
		e.preventDefault();
		var ids = getSelectedIds();
		if (!ids.length) {
			return;
		}

		var confirmMsg = i18n.confirmRun || 'Migrate the selected forms to Form Plant. Are you sure? (Existing migrated forms are kept as-is, and a new Form Plant form is added.)';
		// eslint-disable-next-line no-alert
		if (!window.confirm(confirmMsg)) {
			return;
		}
		migrateAll(ids);
	});

	// Click on the "View log" link → open the modal.
	$(document).on('click', '.fplant-migration-view-log', function (e) {
		e.preventDefault();
		var formId = $(this).data('form-id');
		if (!formId) {
			return;
		}
		openLogModal(formId);
	});

	function openLogModal(formId) {
		$modalTitle.text((i18n.logTitle || 'Migration Log') + ' (Form Plant ID: ' + formId + ')');
		$modalBody.html(
			'<p><span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span>' +
			escapeHtml(i18n.logLoading || 'Loading the log…') + '</p>'
		);
		$modal.show();

		$.ajax({
			url: data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fplant_get_migration_log',
				nonce: data.nonce,
				form_id: formId
			},
			dataType: 'json'
		})
			.done(function (resp) {
				if (!resp || !resp.success) {
					$modalBody.html('<div class="notice notice-error inline"><p>' +
						escapeHtml((resp && resp.data && resp.data.message) || i18n.logLoadError || 'Failed to load the log.') +
						'</p></div>');
					return;
				}
				renderLog(resp.data);
			})
			.fail(function () {
				$modalBody.html('<div class="notice notice-error inline"><p>' +
					escapeHtml(i18n.logLoadError || 'Failed to load the log.') +
					'</p></div>');
			});
	}

	function renderLog(payload) {
		var log = payload.log || {};
		var warnings = log.warnings || [];

		var statusLabel;
		switch (log.status) {
			case 'success': statusLabel = i18n.statusSuccess || 'Success'; break;
			case 'partial': statusLabel = i18n.statusPartial || 'With warnings'; break;
			case 'failed':  statusLabel = i18n.statusFailed || 'Failed'; break;
			default:        statusLabel = log.status || '';
		}

		var html = '<dl class="fplant-migration-log-meta">' +
			'<dt>' + escapeHtml(i18n.logMigratedAt || 'Migrated at') + '</dt>' +
			'<dd>' + escapeHtml(log.migrated_at || '') + '</dd>' +
			'<dt>' + escapeHtml(i18n.logStatus || 'Status') + '</dt>' +
			'<dd><span class="fplant-migration-result-status fplant-migration-result-' + escapeHtml(log.status || '') + '">' +
				escapeHtml(statusLabel) + '</span></dd>' +
			'<dt>' + escapeHtml(i18n.logSourceForm || 'Source form') + '</dt>' +
			'<dd>' + escapeHtml(log.source_post_title || '') + ' (MW ID: ' + escapeHtml(log.source_post_id || '') + ')</dd>' +
			'</dl>';

		html += '<h4 style="margin-top:16px;">' + escapeHtml(i18n.logWarnings || 'Warnings') +
			' (' + warnings.length + ')</h4>';

		if (warnings.length) {
			html += '<ul class="fplant-migration-warnings">';
			warnings.forEach(function (w) {
				html += '<li class="fplant-migration-warning fplant-migration-warning-' + escapeHtml(w.level) + '">' +
					'<span class="fplant-migration-warning-level">' + escapeHtml(w.level) + '</span> ' +
					escapeHtml(w.message) +
					'</li>';
			});
			html += '</ul>';
		} else {
			html += '<p class="description">' + escapeHtml(i18n.noWarnings || 'No warnings.') + '</p>';
		}

		$modalBody.html(html);
	}

	function closeLogModal() {
		$modal.hide();
		$modalBody.empty();
	}

	$(document).on('click', '#fplant-migration-log-modal-close, .fplant-migration-modal-backdrop', function (e) {
		e.preventDefault();
		closeLogModal();
	});

	// Stop propagation so that clicking the modal body does not close it.
	$(document).on('click', '.fplant-migration-modal-content', function (e) {
		e.stopPropagation();
	});

	$(document).on('keydown', function (e) {
		if (27 === e.keyCode && $modal.is(':visible')) {
			closeLogModal();
		}
	});

	loadForms();
});
