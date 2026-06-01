<?php
/**
 * Migration tab content (rendered inside the Tools page).
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fplant_mwwpform_version   = FPLANT_Migration_Admin::get_mwwpform_version();
$fplant_mwwpform_supported = FPLANT_Migration_Admin::is_supported_version();
?>

<div class="fplant-card fplant-migration">
	<div class="fplant-card-header">
		<?php esc_html_e( 'MW WP Form Migration', 'form-plant' ); ?>
	</div>

	<div class="fplant-card-body" style="padding: 16px 20px;">
		<p>
			<?php esc_html_e( 'Converts MW WP Form form definitions into Form Plant forms. Fields, mail settings, and merge tags are migrated automatically; items that could not be migrated are shown in the warning report.', 'form-plant' ); ?>
		</p>

		<?php if ( ! $fplant_mwwpform_supported ) : ?>
			<div class="notice notice-error inline">
				<p>
					<?php
					printf(
						esc_html(
							/* translators: 1: installed version, 2: minimum supported version. */
							__( 'The installed MW WP Form %1$s does not meet the required version for the migration tool (v%2$s or later). Please update MW WP Form to the latest version before running a migration.', 'form-plant' )
						),
						'' === $fplant_mwwpform_version ? '(unknown)' : esc_html( $fplant_mwwpform_version ),
						esc_html( FPLANT_Migration_Admin::MWWPFORM_MIN_VER )
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<h3 style="margin-top: 24px;"><?php esc_html_e( 'MW WP Form List', 'form-plant' ); ?></h3>

		<div id="fplant-migration-list-status" style="margin-bottom: 8px;">
			<span class="spinner is-active" style="float: none; margin: 0 4px 0 0;"></span>
			<?php esc_html_e( 'Loading list…', 'form-plant' ); ?>
		</div>

		<table class="widefat striped fplant-migration-table" id="fplant-migration-table" style="display: none;">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" id="fplant-migration-check-all" /></td>
					<th><?php esc_html_e( 'Title', 'form-plant' ); ?></th>
					<th><?php esc_html_e( 'Fields', 'form-plant' ); ?></th>
					<th><?php esc_html_e( 'Migration Status', 'form-plant' ); ?></th>
				</tr>
			</thead>
			<tbody id="fplant-migration-tbody"></tbody>
		</table>

		<p style="margin-top: 12px;">
			<button type="button" id="fplant-migration-run-btn" class="button button-primary" disabled<?php echo $fplant_mwwpform_supported ? '' : ' style="display:none"'; ?>>
				<?php esc_html_e( 'Migrate selected forms', 'form-plant' ); ?>
			</button>
			<button type="button" id="fplant-migration-refresh-btn" class="button">
				<?php esc_html_e( 'Refresh list', 'form-plant' ); ?>
			</button>
		</p>

		<h3 style="margin-top: 24px;"><?php esc_html_e( 'Migration Report', 'form-plant' ); ?></h3>
		<div id="fplant-migration-report">
			<p class="description"><?php esc_html_e( 'Results will appear here once a migration is run.', 'form-plant' ); ?></p>
		</div>
	</div>
</div>

<!-- Migration log modal (opened from the "View log" link in the list) -->
<div id="fplant-migration-log-modal" class="fplant-migration-modal-backdrop" style="display: none;">
	<div class="fplant-migration-modal-content">
		<div class="fplant-migration-modal-header">
			<h2 id="fplant-migration-log-modal-title"><?php esc_html_e( 'Migration Log', 'form-plant' ); ?></h2>
			<button type="button" class="button-link fplant-migration-modal-close" id="fplant-migration-log-modal-close" aria-label="<?php esc_attr_e( 'Close', 'form-plant' ); ?>">&times;</button>
		</div>
		<div class="fplant-migration-modal-body" id="fplant-migration-log-modal-body">
		</div>
	</div>
</div>
