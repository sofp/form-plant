<?php
/**
 * Admin form preview class
 *
 * Renders a saved form inside the active theme (theme CSS + design preset + custom
 * CSS) so administrators can preview the production appearance of their form. Served
 * at /fplant-preview/{id}/ and embedded in an iframe on the form edit screen.
 *
 * Unlike the embed route (a standalone document with no theme styling), this route
 * renders within the active theme — get_header()/get_footer() for classic themes,
 * a self-built shell with the header/footer template parts for block themes — and
 * is gated by the edit_post capability plus a nonce; for the form author, not visitors.
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Preview class
 */
class FPLANT_Preview extends FPLANT_Rewrite_Endpoint {

	/**
	 * Rewrite regex for the preview endpoint.
	 *
	 * @return string
	 */
	protected function rewrite_regex() {
		return '^fplant-preview/([0-9]+)/?$';
	}

	/**
	 * Query var for the preview endpoint.
	 *
	 * @return string
	 */
	protected function query_var() {
		return 'fplant_preview_form';
	}

	/**
	 * Run before the default template loader takes over.
	 *
	 * @return int
	 */
	protected function render_priority() {
		return 1;
	}

	/**
	 * Register the endpoint, plus a one-time flush so installs that update (rather than
	 * re-activate) pick up the new rule. Guarded by an option so it only happens once.
	 */
	public function register_endpoint() {
		parent::register_endpoint();

		if ( ! get_option( 'fplant_preview_rewrite_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'fplant_preview_rewrite_flushed', '1' );
		}
	}

	/**
	 * Render the preview page when the preview query var is present.
	 */
	public function maybe_render() {
		$form_id = get_query_var( 'fplant_preview_form' );

		if ( '' === $form_id || null === $form_id ) {
			return;
		}

		$form_id = absint( $form_id );

		if ( ! $form_id ) {
			return;
		}

		// Authorization: only users who can edit this form may preview it.
		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You are not allowed to preview this form.', 'form-plant' ), '', array( 'response' => 403 ) );
		}

		// CSRF protection.
		$nonce = isset( $_GET['_fplant_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['_fplant_preview'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fplant_preview_' . $form_id ) ) {
			wp_die( esc_html__( 'This preview link has expired. Please reload the form editor and try again.', 'form-plant' ), '', array( 'response' => 403 ) );
		}

		$form = FPLANT_Database::get_form( $form_id );
		if ( ! $form || 'fplant_form' !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Form not found.', 'form-plant' ), '', array( 'response' => 404 ) );
		}

		// Allow previewing unpublished (draft/pending/private) forms — the capability
		// check above already restricts this to the form's editor.
		add_filter( 'fplant_form_is_viewable', '__return_true' );

		// Enqueue the exact same front-end assets the production [fplant] output uses,
		// driven by the form ID (the global $post / shortcode detection does not apply
		// on this virtual route). Hooked to wp_enqueue_scripts so it fires during the
		// wp_head() call inside get_header() below.
		add_action(
			'wp_enqueue_scripts',
			function () use ( $form_id ) {
				FPLANT_Form_Plant::get_instance()->enqueue_form_assets( array( $form_id ) );
			}
		);

		// Render through the standard shortcode so the output is identical to a normal
		// [fplant id="X"] placement on a themed page.
		$fplant_preview_html = do_shortcode( '[fplant id="' . absint( $form_id ) . '"]' );
		// Mark the form as a preview: the submission handler validates this flag
		// (capability-gated) and skips saving, emails and integrations, so editors
		// can exercise the whole flow without side effects.
		$fplant_preview_html = preg_replace(
			'/<\/form>/',
			'<input type="hidden" name="fplant_preview" value="1"></form>',
			$fplant_preview_html,
			1
		);
		$fplant_preview_html = '<main class="fplant-preview-main" style="padding:40px 20px;max-width:800px;margin:0 auto;">'
			. $fplant_preview_html . '</main>';

		if ( wp_is_block_theme() ) {
			// Block themes have no header.php / footer.php: get_header() would fall
			// back to wp-includes/theme-compat/ and emit "Theme without header.php is
			// deprecated" (visible when WP_DEBUG display is on) while losing the
			// theme's real markup. Build the document shell ourselves and render the
			// theme's header/footer template parts inside .wp-site-blocks so the
			// theme.json global/layout styles apply as on a real page.
			?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php block_template_part( 'header' ); ?>
<?php echo $fplant_preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped during rendering; only a static hidden input and the <main> wrapper are appended. ?>
<?php block_template_part( 'footer' ); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
			<?php
		} else {
			get_header();
			echo $fplant_preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped during rendering; only a static hidden input and the <main> wrapper are appended.
			get_footer();
		}

		remove_filter( 'fplant_form_is_viewable', '__return_true' );
		exit;
	}
}
