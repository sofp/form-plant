<?php
/**
 * MW WP Form Template Builder
 *
 * Preserves the HTML markup of MW WP Form's post_content while replacing
 * only shortcodes with Form Plant placeholders (R3).
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_MWWPForm_Template_Builder
 *
 * @since 1.2.0
 */
class FPLANT_MWWPForm_Template_Builder {

	/**
	 * Name translator (references the finalized map).
	 *
	 * @var FPLANT_Name_Translator
	 */
	private $translator;

	/**
	 * Logger for collecting warnings.
	 *
	 * @var FPLANT_Migrator_Base
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param FPLANT_Name_Translator $translator Field name translator (state after Field_Mapper has run).
	 * @param FPLANT_Migrator_Base   $logger     Logger for collecting warnings.
	 */
	public function __construct( FPLANT_Name_Translator $translator, FPLANT_Migrator_Base $logger ) {
		$this->translator = $translator;
		$this->logger     = $logger;
	}

	/**
	 * Generates input screen / confirmation screen HTML from post_content and tokens.
	 *
	 * @param string $post_content     Original post_content from MW WP Form.
	 * @param array  $tokens           List of shortcodes with position info from the parser.
	 * @param bool   $use_confirmation Whether a confirmation screen is used.
	 * @return array{
	 *     input_template:string,
	 *     confirmation_template:string,
	 *     has_input_submit:bool,
	 *     has_confirm_back:bool,
	 *     has_confirm_submit:bool
	 * }
	 */
	public function build( $post_content, array $tokens, $use_confirmation ) {
		if ( ! is_string( $post_content ) ) {
			$post_content = '';
		}

		$result = array(
			'input_template'        => $post_content,
			'confirmation_template' => $use_confirmation ? $post_content : '',
			'has_input_submit'      => false,
			'has_confirm_back'      => false,
			'has_confirm_submit'    => false,
		);

		if ( '' === $post_content || empty( $tokens ) ) {
			return $this->ensure_buttons( $result, $use_confirmation );
		}

		// Sort by offset descending so replacements start from the end (no offset correction needed).
		usort(
			$tokens,
			static function ( $a, $b ) {
				if ( $a['offset'] === $b['offset'] ) {
					return 0;
				}
				return ( $a['offset'] > $b['offset'] ) ? -1 : 1;
			}
		);

		$name_map = $this->translator->get_map();
		$unresolved_warned = array();

		foreach ( $tokens as $token ) {
			$offset = (int) $token['offset'];
			$length = (int) $token['length'];
			$role   = isset( $token['role'] ) ? (string) $token['role'] : 'unknown';
			$attrs  = isset( $token['attrs'] ) ? (array) $token['attrs'] : array();

			$input_replacement   = '';
			$confirm_replacement = '';

			switch ( $role ) {
				case 'field':
					$original_name   = isset( $attrs['name'] ) ? (string) $attrs['name'] : '';
					$translated_name = isset( $name_map[ $original_name ] ) ? $name_map[ $original_name ] : '';
					if ( '' === $translated_name ) {
						if ( '' !== $original_name && ! isset( $unresolved_warned[ $original_name ] ) ) {
							$this->logger->add_warning(
								FPLANT_Migrator_Base::LEVEL_WARNING,
								'template_field_unresolved',
								sprintf(
									/* translators: %s: original field name. */
									__( 'The translated name for field "%s" in the template was not found; the field has been removed from the template.', 'form-plant' ),
									$original_name
								),
								array( 'original_name' => $original_name )
							);
							$unresolved_warned[ $original_name ] = true;
						}
						$input_replacement   = '';
						$confirm_replacement = '';
					} else {
						$input_replacement   = sprintf( '[fplant_field name="%s"]', $translated_name );
						$confirm_replacement = sprintf( '[fplant_value name="%s"]', $translated_name );
					}
					break;

				case 'button_submit':
					$shortcode       = isset( $token['shortcode'] ) ? (string) $token['shortcode'] : '';
					$display_input   = isset( $attrs['display_input'] ) ? (string) $attrs['display_input'] : '';
					$is_button_tag   = ( 0 === strpos( $shortcode, 'mwform_b' ) );
					$is_plain_submit = ( 'mwform_submit' === $shortcode );

					if ( $is_plain_submit && $use_confirmation ) {
						// When a confirmation screen is used, mwform_submit is treated as the final submit button on the confirmation screen.
						// The transition from input to confirmation is handled by confirmButton etc., so it is not shown on the input screen.
						$input_replacement = '';
					} elseif ( $is_button_tag && 'true' !== $display_input ) {
						// bsubmit with display_input != true is not shown on the input screen
						$input_replacement = '';
					} else {
						$input_replacement       = '[fplant_submit]';
						$result['has_input_submit'] = true;
					}
					if ( $use_confirmation ) {
						$confirm_replacement         = '[fplant_confirm_submit]';
						$result['has_confirm_submit'] = true;
					}
					break;

				case 'button_confirm':
					$input_replacement       = '[fplant_submit]';
					$result['has_input_submit'] = true;
					$confirm_replacement     = '';
					break;

				case 'button_back':
					$input_replacement = '';
					if ( $use_confirmation ) {
						$confirm_replacement      = '[fplant_back]';
						$result['has_confirm_back'] = true;
					}
					break;

				case 'button_generic':
					// mwform_button / mwform_bbutton are generic buttons (input/button type="button")
					// with no submit action even in MW WP Form, so they are removed from the template.
					$this->logger->add_warning(
						FPLANT_Migrator_Base::LEVEL_WARNING,
						'button_not_migrated',
						sprintf(
							/* translators: %s: shortcode name. */
							__( '[%s] is a generic button with no submit action in MW WP Form and will not be migrated. If you had custom behavior attached to it, please reconfigure it in Form Plant.', 'form-plant' ),
							isset( $token['shortcode'] ) ? $token['shortcode'] : ''
						),
						array( 'shortcode' => isset( $token['shortcode'] ) ? $token['shortcode'] : '' )
					);
					$input_replacement   = '';
					$confirm_replacement = '';
					break;

				case 'error':
					$keys           = isset( $attrs['keys'] ) ? (string) $attrs['keys'] : '';
					$translated_key = '';
					if ( '' !== $keys && isset( $name_map[ $keys ] ) ) {
						$translated_key = $name_map[ $keys ];
					}
					if ( '' === $translated_key ) {
						$input_replacement   = '';
						$confirm_replacement = '';
					} else {
						$input_replacement = sprintf( '[fplant_field_error name="%s"]', $translated_key );
						// The confirmation screen has no error display placeholder, so remove it
						$confirm_replacement = '';
					}
					break;

				case 'akismet_error':
				case 'skip_other':
				case 'unknown':
				default:
					$this->logger->add_warning(
						FPLANT_Migrator_Base::LEVEL_INFO,
						'template_shortcode_removed',
						sprintf(
							/* translators: %s: shortcode name. */
							__( '[%s] has been removed from the template.', 'form-plant' ),
							isset( $token['shortcode'] ) ? $token['shortcode'] : ''
						),
						array( 'shortcode' => isset( $token['shortcode'] ) ? $token['shortcode'] : '' )
					);
					$input_replacement   = '';
					$confirm_replacement = '';
					break;
			}

			$result['input_template'] = substr_replace( $result['input_template'], $input_replacement, $offset, $length );
			if ( $use_confirmation ) {
				$result['confirmation_template'] = substr_replace( $result['confirmation_template'], $confirm_replacement, $offset, $length );
			}
		}

		return $this->ensure_buttons( $result, $use_confirmation );
	}

	/**
	 * Appends any missing required buttons to the end of the template and logs a warning.
	 *
	 * @param array $result            Intermediate result from build().
	 * @param bool  $use_confirmation  Whether a confirmation screen is used.
	 * @return array Result with buttons appended as needed.
	 */
	private function ensure_buttons( array $result, $use_confirmation ) {
		if ( ! $result['has_input_submit'] ) {
			$result['input_template'] .= "\n[fplant_submit]";
			$this->logger->add_warning(
				FPLANT_Migrator_Base::LEVEL_WARNING,
				'input_submit_appended',
				__( 'The input screen HTML template did not contain a submit button, so [fplant_submit] was automatically appended.', 'form-plant' )
			);
		}

		if ( $use_confirmation ) {
			if ( ! $result['has_confirm_back'] ) {
				$result['confirmation_template'] .= "\n[fplant_back]";
				$this->logger->add_warning(
					FPLANT_Migrator_Base::LEVEL_WARNING,
					'confirm_back_appended',
					__( 'The confirmation screen HTML template did not contain a back button, so [fplant_back] was automatically appended.', 'form-plant' )
				);
			}
			if ( ! $result['has_confirm_submit'] ) {
				$result['confirmation_template'] .= "\n[fplant_confirm_submit]";
				$this->logger->add_warning(
					FPLANT_Migrator_Base::LEVEL_WARNING,
					'confirm_submit_appended',
					__( 'The confirmation screen HTML template did not contain a submit button, so [fplant_confirm_submit] was automatically appended.', 'form-plant' )
				);
			}
		}

		return $result;
	}

	/**
	 * Extracts the display label from MW WP Form button attributes.
	 *
	 * - input-tag types (submitButton/backButton etc.): uses the value attribute
	 * - button-tag types (bsubmit/bback etc.): prefers element_content, falls back to value
	 *
	 * @param string $shortcode Shortcode name.
	 * @param array  $attrs     Attribute array.
	 * @return string Display label, or empty string if not found.
	 */
	public static function extract_button_label( $shortcode, array $attrs ) {
		$is_button_tag = ( 0 === strpos( (string) $shortcode, 'mwform_b' ) );
		if ( $is_button_tag ) {
			if ( isset( $attrs['element_content'] ) && '' !== $attrs['element_content'] ) {
				return (string) $attrs['element_content'];
			}
			if ( isset( $attrs['value'] ) && '' !== $attrs['value'] ) {
				return (string) $attrs['value'];
			}
			return '';
		}

		// input-tag types: submitButton is expected to use confirm_value / submit_value in a separate function.
		// All others (backButton, confirmButton, button) use value.
		if ( isset( $attrs['value'] ) && '' !== $attrs['value'] ) {
			return (string) $attrs['value'];
		}
		return '';
	}
}
