<?php
/**
 * Tools page (tab container).
 *
 * @package Form_Plant
 *
 * @var array $fplant_forms List of forms passed from FPLANT_Admin::render_tools_page().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
$fplant_current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
// phpcs:enable

$fplant_tabs = array(
	'general' => __( 'Import / Export', 'form-plant' ),
);

$fplant_show_mwwpform_tab = class_exists( 'FPLANT_Migration_Admin' ) && FPLANT_Migration_Admin::is_mwwpform_active();
if ( $fplant_show_mwwpform_tab ) {
	$fplant_tabs['mwwpform-migration'] = __( 'MW WP Form Migration', 'form-plant' );
}

if ( ! isset( $fplant_tabs[ $fplant_current_tab ] ) ) {
	$fplant_current_tab = 'general';
}
?>

<div class="wrap fplant-admin-page">
	<h1><?php esc_html_e( 'Tools', 'form-plant' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( count( $fplant_tabs ) > 1 ) : ?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $fplant_tabs as $fplant_tab_key => $fplant_tab_label ) : ?>
				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=fplant-tools&tab=' . $fplant_tab_key ) ); ?>"
					class="nav-tab <?php echo $fplant_tab_key === $fplant_current_tab ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $fplant_tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<div class="fplant-tools-tab-content" style="margin-top: 16px;">
		<?php
		switch ( $fplant_current_tab ) {
			case 'mwwpform-migration':
				include FPLANT_PLUGIN_DIR . 'admin/views/migration-page.php';
				break;
			case 'general':
			default:
				include FPLANT_PLUGIN_DIR . 'admin/views/tools-general-tab.php';
				break;
		}
		?>
	</div>
</div>
