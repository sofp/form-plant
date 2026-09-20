<?php
/**
 * Shortcode processing class
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FPLANT_Shortcode class
 */
class FPLANT_Shortcode {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register shortcodes
		add_shortcode( 'fplant', array( $this, 'render_form' ) );
		add_shortcode( 'fplant_field', array( $this, 'render_field' ) );
		add_shortcode( 'fplant_submit', array( $this, 'render_submit' ) );
		add_shortcode( 'fplant_errors', array( $this, 'render_errors' ) );
		add_shortcode( 'fplant_success', array( $this, 'render_success' ) );
		add_shortcode( 'fplant_field_error', array( $this, 'render_field_error' ) );
		add_shortcode( 'fplant_complete', array( $this, 'render_complete' ) );

		// Redirect-page guard for [fplant_complete] (see guard_complete_page()).
		add_action( 'template_redirect', array( $this, 'guard_complete_page' ) );
		add_action( 'save_post', array( __CLASS__, 'flush_form_page_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_form_page_cache' ) );
	}

	/**
	 * Render form
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'fplant'
		);

		$form_id = absint( $atts['id'] );

		if ( ! $form_id ) {
			return '<p>' . esc_html__( 'Form ID is not specified', 'form-plant' ) . '</p>';
		}

		// Get form data
		$form = FPLANT_Database::get_form( $form_id );

		if ( ! $form ) {
			return '<p>' . esc_html__( 'Form not found', 'form-plant' ) . '</p>';
		}

		// Respect the form's publish status. Non-published forms (private/
		// draft/pending) are hidden from visitors; only users who can edit
		// the form can preview them.
		if ( ! FPLANT_Database::is_form_viewable( $form ) ) {
			return '';
		}

		// Load template
		ob_start();
		$this->load_template( 'form-wrapper', array( 'form' => $form ) );
		$output = ob_get_clean();

		// Show a preview notice to editors when the form is not published.
		if ( 'publish' !== $form['status'] ) {
			$output = $this->get_preview_notice( $form ) . $output;
		}

		return self::filter_form_output( $output, $form, 'shortcode' );
	}

	/**
	 * Pass a rendered form through the fplant_form_output filter.
	 *
	 * Shared by the three rendering routes (shortcode, iframe embed, REST /
	 * JS embed). Never reached for forms that are not viewable, so a filter
	 * cannot reveal an unpublished form to visitors.
	 *
	 * @since 1.5.2
	 * @param string $html    Rendered form HTML (wrapper element included).
	 * @param array  $form    Form data.
	 * @param string $context 'shortcode', 'iframe' or 'rest'.
	 * @return string
	 */
	public static function filter_form_output( $html, $form, $context ) {
		/**
		 * Filters the rendered form.
		 *
		 * Replace the markup to show something else instead of the form (for
		 * example a "registration is closed" message), or add markup before /
		 * after it. Rejecting the submission itself is a separate concern:
		 * see fplant_form_is_submittable and fplant_submission_gate.
		 *
		 * @since 1.5.2
		 * @param string $html    Rendered form HTML (wrapper element included).
		 * @param array  $form    Form data.
		 * @param string $context Rendering route: 'shortcode', 'iframe' or 'rest'.
		 */
		$filtered = apply_filters( 'fplant_form_output', $html, $form, $context );

		return is_string( $filtered ) ? $filtered : $html;
	}

	/**
	 * Completion payloads already resolved in this request, keyed by form ID
	 * (the token is consumed once even if the shortcode renders twice).
	 *
	 * @var array<int, array|null>
	 */
	private static $complete_payloads = array();

	/**
	 * Transient caching form ID => permalink of the page embedding the form.
	 *
	 * @since 1.5.0
	 */
	const FORM_PAGES_TRANSIENT = 'fplant_form_pages';

	/**
	 * Render the completion screen on the redirect page: [fplant_complete id="123"].
	 *
	 * Works only with the one-time token the form appends to its redirect URL
	 * (form setting "Use the completion shortcode on the redirect page").
	 * Shows the form's Completion Page HTML, or its success message, with the
	 * same tags as emails. Outputs nothing without a valid token.
	 *
	 * @since 1.5.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_complete( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'fplant_complete' );
		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return '';
		}

		$payload = self::get_complete_payload( $form_id );
		if ( null === $payload ) {
			return '';
		}

		$form = FPLANT_Database::get_form( $form_id );
		if ( ! $form ) {
			return '';
		}

		$settings      = isset( $form['settings'] ) ? $form['settings'] : array();
		$data          = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
		$submission_id = isset( $payload['submission_id'] ) ? (int) $payload['submission_id'] : 0;

		if ( ! empty( $settings['success_page_html'] ) ) {
			$html = wp_kses_post( FPLANT_Submission_Manager::render_completion_html( $settings['success_page_html'], $form, $data, $submission_id ) );
		} else {
			$message = ! empty( $settings['success_message'] ) ? $settings['success_message'] : __( 'Submission completed', 'form-plant' );
			$message = FPLANT_Email_Handler::replace_tags(
				$message,
				$data,
				$form,
				$submission_id,
				array(
					'mask_password' => true,
					'context'       => 'completion',
				)
			);
			$html    = '<p>' . esc_html( $message ) . '</p>';
		}

		/**
		 * Filters the HTML rendered by the [fplant_complete] shortcode.
		 *
		 * @since 1.5.0
		 * @param string $html          Completion HTML (tags expanded, escaped).
		 * @param int    $form_id       Form ID.
		 * @param array  $data          Submitted data (passwords masked).
		 * @param int    $submission_id Submission ID (0 when not saved).
		 * @param array  $form          Form data.
		 */
		$html = apply_filters( 'fplant_complete_shortcode_html', $html, $form_id, $data, $submission_id, $form );

		return '<div class="fplant-complete" data-form-id="' . esc_attr( $form_id ) . '">' . $html . '</div>';
	}

	/**
	 * Send visitors who open a redirect page without a valid completion
	 * token (direct access, reload after the one-time token was used) to the
	 * URL configured on the form, so conversion tags placed on the page fire
	 * only after a real submission. Only pages whose content contains
	 * [fplant_complete] for a form with the feature enabled are guarded.
	 *
	 * @since 1.5.0
	 */
	public function guard_complete_page() {
		if ( is_admin() || is_preview() || ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! has_shortcode( (string) $post->post_content, 'fplant_complete' ) ) {
			return;
		}

		$decision = self::evaluate_complete_page_access( $post->post_content, self::get_request_token() );
		if ( $decision['allowed'] ) {
			return;
		}

		$redirect_url = $decision['redirect_url'];
		// Never redirect a page to itself (e.g. a page that holds both the form
		// and its completion shortcode would loop).
		if ( untrailingslashit( $redirect_url ) === untrailingslashit( (string) get_permalink( $post ) ) ) {
			$redirect_url = home_url( '/' );
		}

		wp_safe_redirect( $redirect_url, 302 );
		exit;
	}

	/**
	 * Decide whether a page containing [fplant_complete] may be shown with the
	 * given token. Pure function so the guard is testable.
	 *
	 * @since 1.5.0
	 * @param string $content Post content.
	 * @param string $token   Token from the request ('' when absent).
	 * @return array{allowed: bool, redirect_url: string}
	 */
	public static function evaluate_complete_page_access( $content, $token ) {
		$fallback      = '';
		$first_form_id = 0;

		foreach ( self::extract_complete_form_ids( $content ) as $form_id ) {
			$form = FPLANT_Database::get_form( $form_id );
			if ( ! $form || empty( $form['settings']['complete_shortcode_enabled'] ) ) {
				continue; // Feature off for this form: the shortcode renders nothing, no guard.
			}
			if ( ! $first_form_id ) {
				$first_form_id = (int) $form_id;
			}
			if ( '' === $fallback && ! empty( $form['settings']['complete_shortcode_fallback_url'] ) ) {
				$fallback = (string) $form['settings']['complete_shortcode_fallback_url'];
			}
			if ( '' !== $token && null !== FPLANT_Submission_Manager::peek_complete_token( $token, $form_id ) ) {
				return array(
					'allowed'      => true,
					'redirect_url' => '',
				);
			}
		}

		if ( ! $first_form_id ) {
			return array(
				'allowed'      => true,
				'redirect_url' => '',
			);
		}

		// Redirect target: the URL set on the form, else the page the form is
		// placed on (auto-detected), else the site home.
		if ( '' !== $fallback ) {
			$redirect_url = esc_url_raw( $fallback );
		} else {
			$redirect_url = self::find_form_page_url( $first_form_id );
			if ( '' === $redirect_url ) {
				$redirect_url = home_url( '/' );
			}
		}

		return array(
			'allowed'      => false,
			'redirect_url' => $redirect_url,
		);
	}

	/**
	 * Permalink of a published page/post that embeds the form (via the
	 * [fplant] shortcode or the Form Plant block in form view). Pages are
	 * preferred over other post types; '' when nothing is found (e.g. the
	 * form lives in a widget or a theme template).
	 *
	 * Cached (transient) until any post is saved or deleted.
	 *
	 * @since 1.5.0
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function find_form_page_url( $form_id ) {
		global $wpdb;

		$form_id = (int) $form_id;
		if ( ! $form_id ) {
			return '';
		}

		$map = get_transient( self::FORM_PAGES_TRANSIENT );
		if ( is_array( $map ) && isset( $map[ $form_id ] ) && is_string( $map[ $form_id ] ) ) {
			return $map[ $form_id ];
		}

		$post_types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'fplant_form' ) ) );
		$url        = '';

		if ( ! empty( $post_types ) ) {
			$type_list       = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";
			$like_shortcode  = '%' . $wpdb->esc_like( '[fplant id="' . $form_id . '"' ) . '%';
			$like_shortcode2 = '%' . $wpdb->esc_like( '[fplant id=' . $form_id . ']' ) . '%';
			$like_block      = '%' . $wpdb->esc_like( '"formId":' . $form_id ) . '%';

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Content search across the public post types (registered slugs, each escaped with esc_sql); the LIKE values go through prepare(). Result cached in a transient.
			$candidates = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$type_list}) AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s ) ORDER BY ( post_type = 'page' ) DESC, ID ASC LIMIT 20",
					$like_shortcode,
					$like_shortcode2,
					$like_block
				)
			);
			// phpcs:enable

			foreach ( (array) $candidates as $candidate_id ) {
				$candidate = get_post( $candidate_id );
				if ( $candidate && self::content_embeds_form( $candidate->post_content, $form_id ) ) {
					$url = (string) get_permalink( $candidate );
					break;
				}
			}
		}

		$map             = is_array( $map ) ? $map : array();
		$map[ $form_id ] = $url;
		set_transient( self::FORM_PAGES_TRANSIENT, $map, DAY_IN_SECONDS );

		return $url;
	}

	/**
	 * Drop the cached form-page lookups (any post may have gained or lost a
	 * form placement).
	 *
	 * @since 1.5.0
	 */
	public static function flush_form_page_cache() {
		delete_transient( self::FORM_PAGES_TRANSIENT );
	}

	/**
	 * Whether content embeds the form's input screen ([fplant id="N"] or the
	 * block with view "form"). A completion-only placement does not count.
	 *
	 * @param string $content Post content.
	 * @param int    $form_id Form ID.
	 * @return bool
	 */
	public static function content_embeds_form( $content, $form_id ) {
		$content = (string) $content;
		$form_id = (int) $form_id;

		if ( preg_match_all( '/' . get_shortcode_regex( array( 'fplant' ) ) . '/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = shortcode_parse_atts( isset( $match[3] ) ? $match[3] : '' );
				if ( is_array( $atts ) && isset( $atts['id'] ) && absint( $atts['id'] ) === $form_id ) {
					return true;
				}
			}
		}

		if ( function_exists( 'has_block' ) && has_block( 'form-plant/form', $content ) ) {
			foreach ( parse_blocks( $content ) as $block ) {
				if ( self::block_embeds_form( $block, $form_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Recursive block check for content_embeds_form().
	 *
	 * @param array $block   Parsed block.
	 * @param int   $form_id Form ID.
	 * @return bool
	 */
	private static function block_embeds_form( $block, $form_id ) {
		if ( ! is_array( $block ) ) {
			return false;
		}
		if ( 'form-plant/form' === ( $block['blockName'] ?? '' ) ) {
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( (int) ( $attrs['formId'] ?? 0 ) === $form_id && 'complete' !== ( $attrs['view'] ?? 'form' ) ) {
				return true;
			}
		}
		foreach ( ( $block['innerBlocks'] ?? array() ) as $inner ) {
			if ( self::block_embeds_form( $inner, $form_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Form IDs referenced by [fplant_complete] shortcodes in content.
	 *
	 * @param string $content Post content.
	 * @return int[]
	 */
	private static function extract_complete_form_ids( $content ) {
		$ids = array();
		if ( preg_match_all( '/' . get_shortcode_regex( array( 'fplant_complete' ) ) . '/', (string) $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = shortcode_parse_atts( isset( $match[3] ) ? $match[3] : '' );
				$id   = is_array( $atts ) && isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
				if ( $id ) {
					$ids[] = $id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Completion token from the current request.
	 *
	 * @return string
	 */
	public static function get_request_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; the token itself is validated against the transient store.
		$token = isset( $_GET[ FPLANT_Submission_Manager::COMPLETE_TOKEN_PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ FPLANT_Submission_Manager::COMPLETE_TOKEN_PARAM ] ) ) : '';
		return is_string( $token ) ? $token : '';
	}

	/**
	 * Resolve (and consume) the completion payload for a form once per request.
	 *
	 * @param int $form_id Form ID.
	 * @return array|null
	 */
	private static function get_complete_payload( $form_id ) {
		$form_id = (int) $form_id;
		if ( array_key_exists( $form_id, self::$complete_payloads ) ) {
			return self::$complete_payloads[ $form_id ];
		}
		$token   = self::get_request_token();
		$payload = '' !== $token ? FPLANT_Submission_Manager::consume_complete_token( $token, $form_id ) : null;

		self::$complete_payloads[ $form_id ] = $payload;
		return $payload;
	}

	/**
	 * Forget resolved payloads (tests).
	 *
	 * @since 1.5.0
	 */
	public static function reset_complete_payloads() {
		self::$complete_payloads = array();
	}

	/**
	 * Preview notice shown to editors when a non-published form is rendered.
	 *
	 * @param array $form Form data.
	 * @return string
	 */
	private function get_preview_notice( $form ) {
		$notice = '<div class="fplant-preview-notice" role="status" style="margin:0 0 12px;padding:10px 14px;border-left:4px solid #d98300;background:#fff8ec;color:#5d4200;font-size:14px;border-radius:2px;">'
			. esc_html__( 'Preview: This form is not published yet, so it is hidden from visitors. Only users who can edit it can see it.', 'form-plant' )
			. '</div>';

		/**
		 * Filter the preview notice shown to editors for non-published forms.
		 *
		 * Return an empty string to hide the notice, or replace it with your
		 * own markup — for example when granting members access to a private
		 * form via the fplant_form_is_viewable filter.
		 *
		 * @param string $notice The notice HTML.
		 * @param array  $form   Form data.
		 */
		return apply_filters( 'fplant_preview_notice', $notice, $form );
	}

	/**
	 * Render field
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_field( $atts ) {
		$atts = shortcode_atts(
			array(
				'name'        => '',
				'class'       => '',
				'placeholder' => '',
			),
			$atts,
			'fplant_field'
		);

		if ( empty( $atts['name'] ) ) {
			return '';
		}

		// Get form info from global variable
		global $fplant_current_form;

		if ( ! $fplant_current_form ) {
			return '';
		}

		// Get field configuration
		$field_manager = new FPLANT_Field_Manager();
		$field         = $field_manager->get_field_by_name( $atts['name'], $fplant_current_form['fields'] );

		if ( ! $field ) {
			return '';
		}

		// Override field settings with shortcode attributes
		if ( ! empty( $atts['class'] ) ) {
			$field['class'] = $field['class'] ? $field['class'] . ' ' . $atts['class'] : $atts['class'];
		}

		if ( ! empty( $atts['placeholder'] ) ) {
			$field['placeholder'] = $atts['placeholder'];
		}

		// Get form settings
		$form_settings = isset( $fplant_current_form['settings'] ) ? $fplant_current_form['settings'] : array();

		// Render field
		return $field_manager->render_field( $field, '', $fplant_current_form['id'], $form_settings );
	}

	/**
	 * Render submit button
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_submit( $atts ) {
		// Get default values from form settings if available.
		global $fplant_current_form;
		$settings = isset( $fplant_current_form['settings'] ) ? $fplant_current_form['settings'] : array();

		$default_text = isset( $settings['input_submit_text'] ) && '' !== $settings['input_submit_text']
			? $settings['input_submit_text']
			: __( 'Submit', 'form-plant' );
		$default_class = isset( $settings['input_submit_class'] ) ? $settings['input_submit_class'] : '';
		$default_id    = isset( $settings['input_submit_id'] ) ? $settings['input_submit_id'] : '';

		$atts = shortcode_atts(
			array(
				'text'  => $default_text,
				'class' => $default_class,
				'id'    => $default_id,
			),
			$atts,
			'fplant_submit'
		);

		// Variables exposed to the template (form-fields/submit.php).
		$submit_text  = $atts['text'];
		$submit_class = $atts['class'];
		$submit_id    = $atts['id'];

		// Locate the submit button template (allows theme overrides).
		$template_loader = new FPLANT_Template_Loader();
		$template        = $template_loader->locate_template( 'form-fields/submit.php' );

		ob_start();
		if ( ! empty( $template ) && file_exists( $template ) ) {
			include $template;
		} else {
			// Fallback in case the template cannot be located.
			$class = 'fplant-submit-button';
			if ( ! empty( $submit_class ) ) {
				$class .= ' ' . esc_attr( $submit_class );
			}
			$id_attr = ! empty( $submit_id ) ? ' id="' . esc_attr( $submit_id ) . '"' : '';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $id_attr is already escaped with esc_attr().
			echo '<button type="submit"' . $id_attr . ' class="' . esc_attr( $class ) . '">' . esc_html( $submit_text ) . '</button>';
		}
		return ob_get_clean();
	}

	/**
	 * Error message display position
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_errors( $atts ) {
		return '<div class="fplant-errors"></div>';
	}

	/**
	 * Success message display position
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_success( $atts ) {
		return '<div class="fplant-success"></div>';
	}

	/**
	 * Individual field error message display position
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_field_error( $atts ) {
		$atts = shortcode_atts(
			array(
				'name'  => '',
				'class' => '',
			),
			$atts,
			'fplant_field_error'
		);

		if ( empty( $atts['name'] ) ) {
			return '';
		}

		$class = 'fplant-field-error';
		if ( ! empty( $atts['class'] ) ) {
			$class .= ' ' . esc_attr( $atts['class'] );
		}

		return sprintf(
			'<div class="%s" data-field-error="%s" style="display: none;"></div>',
			esc_attr( $class ),
			esc_attr( $atts['name'] )
		);
	}

	/**
	 * Load template file
	 *
	 * @param string $template_name Template name
	 * @param array  $args          Arguments
	 */
	private function load_template( $template_name, $args = array() ) {
		// Explicitly define required variables (don't use extract() as it's discouraged)
		$form = isset( $args['form'] ) ? $args['form'] : array();

		$template_path = FPLANT_PLUGIN_DIR . 'templates/' . $template_name . '.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}
}
