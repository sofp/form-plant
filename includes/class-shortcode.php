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

		return $output;
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

		$class = 'fplant-submit-button';
		if ( ! empty( $atts['class'] ) ) {
			$class .= ' ' . esc_attr( $atts['class'] );
		}

		$id_attr = '';
		if ( ! empty( $atts['id'] ) ) {
			$id_attr = ' id="' . esc_attr( $atts['id'] ) . '"';
		}

		ob_start();
		?>
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $id_attr is already escaped with esc_attr() ?>
		<button type="submit"<?php echo $id_attr; ?> class="<?php echo esc_attr( $class ); ?>">
			<?php echo esc_html( $atts['text'] ); ?>
		</button>
		<?php
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
