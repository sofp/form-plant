<?php
/**
 * MW WP Form Field Mapper
 *
 * Converts parsed shortcodes into Form Plant field arrays.
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_MWWPForm_Field_Mapper
 *
 * @since 1.2.0
 */
class FPLANT_MWWPForm_Field_Mapper {

	/**
	 * Name translator.
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
	 * @param FPLANT_Name_Translator $translator Name translator.
	 * @param FPLANT_Migrator_Base   $logger     Logger for collecting warnings.
	 */
	public function __construct( FPLANT_Name_Translator $translator, FPLANT_Migrator_Base $logger ) {
		$this->translator = $translator;
		$this->logger     = $logger;
	}

	/**
	 * Converts a single parsed shortcode entry into a Form Plant field array.
	 *
	 * @param array $parsed_shortcode ['shortcode' => string, 'attrs' => array<string,string>].
	 * @return array|null Form Plant field array, or null if the shortcode cannot be mapped.
	 */
	public function map( array $parsed_shortcode ) {
		$shortcode = $parsed_shortcode['shortcode'] ?? '';
		$attrs     = $parsed_shortcode['attrs'] ?? array();

		switch ( $shortcode ) {
			case 'mwform_text':
				$field = $this->build_field( 'text', $attrs );
				return $this->apply_size_attributes( $attrs, $field, 'text' );

			case 'mwform_textarea':
				$field = $this->build_field( 'textarea', $attrs );
				return $this->apply_size_attributes( $attrs, $field, 'textarea' );

			case 'mwform_email':
				$field = $this->build_field( 'email', $attrs );
				return $this->apply_size_attributes( $attrs, $field, 'email' );

			case 'mwform_password':
				$field = $this->build_field( 'password', $attrs );
				return $this->apply_size_attributes( $attrs, $field, 'password' );

			case 'mwform_tel':
				// MW WP Form phone numbers always use 3-part split input, so reproduce with the split3 type.
				$field               = $this->build_field( 'tel', $attrs );
				$field['tel_format'] = 'split3';
				return $field;

			case 'mwform_url':
				$field = $this->build_field( 'url', $attrs );
				return $this->apply_size_attributes( $attrs, $field, 'url' );

			case 'mwform_zip':
				// MW WP Form postal codes use 2 input boxes, so reproduce with split format (3 + 4 digits).
				$field                  = $this->build_field( 'postal_code', $attrs );
				$field['postal_format'] = 'split';
				return $field;

			case 'mwform_number':
				$field = $this->build_field( 'number', $attrs );
				if ( isset( $attrs['min'] ) && '' !== $attrs['min'] ) {
					$field['min'] = $attrs['min'];
				}
				if ( isset( $attrs['max'] ) && '' !== $attrs['max'] ) {
					$field['max'] = $attrs['max'];
				}
				return $field;

			case 'mwform_range':
				$this->logger->add_warning(
					FPLANT_Migrator_Base::LEVEL_WARNING,
					'range_converted_to_number',
					sprintf(
						/* translators: %s: field name. */
						__( '[mwform_range] field "%s" was converted to a number field. The slider UI is not reproduced.', 'form-plant' ),
						isset( $attrs['name'] ) ? $attrs['name'] : ''
					),
					array( 'shortcode' => $shortcode, 'name' => $attrs['name'] ?? '' )
				);
				$field = $this->build_field( 'number', $attrs );
				if ( isset( $attrs['min'] ) && '' !== $attrs['min'] ) {
					$field['min'] = $attrs['min'];
				}
				if ( isset( $attrs['max'] ) && '' !== $attrs['max'] ) {
					$field['max'] = $attrs['max'];
				}
				if ( isset( $attrs['step'] ) && '' !== $attrs['step'] ) {
					$field['step'] = $attrs['step'];
				}
				return $field;

			case 'mwform_select':
				$field            = $this->build_field( 'select', $attrs );
				$field['options'] = $this->parse_children( $attrs );
				return $field;

			case 'mwform_radio':
				$field            = $this->build_field( 'radio', $attrs );
				$field['options'] = $this->parse_children( $attrs );
				$field['layout']  = ( ! empty( $attrs['vertically'] ) && 'true' === (string) $attrs['vertically'] )
					? 'vertical'
					: 'horizontal';
				return $field;

			case 'mwform_checkbox':
				$field            = $this->build_field( 'checkbox', $attrs );
				$field['options'] = $this->parse_children( $attrs );
				$field['layout']  = ( ! empty( $attrs['vertically'] ) && 'true' === (string) $attrs['vertically'] )
					? 'vertical'
					: 'horizontal';
				if ( isset( $attrs['separator'] ) && '' !== $attrs['separator'] ) {
					$field['delimiter'] = $attrs['separator'];
				}
				return $field;

			case 'mwform_hidden':
				return $this->build_field( 'hidden', $attrs );

			case 'mwform_file':
				$field = $this->build_field( 'file', $attrs );
				return $field;

			case 'mwform_image':
				$this->logger->add_warning(
					FPLANT_Migrator_Base::LEVEL_INFO,
					'image_converted_to_file',
					sprintf(
						/* translators: %s: field name. */
						__( '[mwform_image] field "%s" was converted to a file field (images only).', 'form-plant' ),
						isset( $attrs['name'] ) ? $attrs['name'] : ''
					),
					array( 'shortcode' => $shortcode, 'name' => $attrs['name'] ?? '' )
				);
				$field                  = $this->build_field( 'file', $attrs );
				$field['allowed_types'] = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
				return $field;

			case 'mwform_datepicker':
				$field           = $this->build_field( 'date', $attrs );
				$field['format'] = 'Y-m-d';
				return $field;

			case 'mwform_monthpicker':
				$this->logger->add_warning(
					FPLANT_Migrator_Base::LEVEL_INFO,
					'monthpicker_converted_to_date',
					sprintf(
						/* translators: %s: field name. */
						__( '[mwform_monthpicker] field "%s" was converted to a date field (Y-m format).', 'form-plant' ),
						isset( $attrs['name'] ) ? $attrs['name'] : ''
					),
					array( 'shortcode' => $shortcode, 'name' => $attrs['name'] ?? '' )
				);
				$field           = $this->build_field( 'date', $attrs );
				$field['format'] = 'Y-m';
				return $field;

			case 'mwform_custom_mail_tag':
				$field                    = $this->build_field( 'custom_mail_tag', $attrs );
				$field['display_in_form'] = true;
				$field['display_wrapper'] = 'span';
				$this->logger->add_warning(
					FPLANT_Migrator_Base::LEVEL_WARNING,
					'custom_mail_tag_filter_rename',
					sprintf(
						/* translators: %s: field name. */
						__( 'Custom Mail Tag "%s" was migrated. Please update the filter hook that provides its value from mwform_custom_mail_tag_* to fplant_custom_mail_tag_value_*.', 'form-plant' ),
						isset( $attrs['name'] ) ? $attrs['name'] : ''
					),
					array( 'shortcode' => $shortcode, 'name' => $attrs['name'] ?? '' )
				);
				return $field;
		}

		return null;
	}

	/**
	 * Builds a base field array populated with common attributes.
	 *
	 * @param string $type  Form Plant field type.
	 * @param array  $attrs MW WP Form shortcode attributes.
	 * @return array Form Plant field array.
	 */
	private function build_field( $type, array $attrs ) {
		$original_name  = isset( $attrs['name'] ) ? (string) $attrs['name'] : '';
		$translated     = $this->translator->translate( $original_name, $type );

		if ( '' !== $original_name && $translated !== $original_name ) {
			$this->logger->add_warning(
				FPLANT_Migrator_Base::LEVEL_INFO,
				'name_translated',
				sprintf(
					/* translators: 1: original name, 2: translated name. */
					__( 'Field name "%1$s" was converted to "%2$s".', 'form-plant' ),
					$original_name,
					$translated
				),
				array( 'original' => $original_name, 'translated' => $translated )
			);
		}

		$field = array(
			'type'         => $type,
			'name'         => $translated,
			'label'        => '' !== $original_name ? $original_name : $translated,
			'placeholder'  => isset( $attrs['placeholder'] ) ? (string) $attrs['placeholder'] : '',
			'default'      => isset( $attrs['value'] ) ? (string) $attrs['value'] : '',
			'required'     => false,
			'class'        => '',
			'custom_id'    => isset( $attrs['id'] ) ? (string) $attrs['id'] : '',
			'custom_class' => isset( $attrs['class'] ) ? (string) $attrs['class'] : '',
			'validation'   => array(),
			'conditional'  => array(
				'enabled' => false,
				'field'   => '',
				'value'   => '',
			),
		);

		$this->maybe_warn_unsupported_attrs( $attrs, $original_name );

		return $field;
	}

	/**
	 * Converts MW WP Form's children="A:1,B:2,C" attribute into a Form Plant options array.
	 *
	 * @param array $attrs Shortcode attributes.
	 * @return array<int, array{value:string,label:string}>
	 */
	private function parse_children( array $attrs ) {
		$children = isset( $attrs['children'] ) ? trim( (string) $attrs['children'] ) : '';
		if ( '' === $children ) {
			return array();
		}

		$options = array();
		$entries = explode( ',', $children );
		foreach ( $entries as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( false !== strpos( $entry, ':' ) ) {
				list( $label, $value ) = array_map( 'trim', explode( ':', $entry, 2 ) );
			} else {
				$label = $entry;
				$value = $entry;
			}
			$options[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $options;
	}

	/**
	 * Detects attributes that will not be migrated and logs a warning.
	 *
	 * size / maxlength / cols / rows are handled individually by apply_size_attributes() and are excluded here.
	 *
	 * @param array  $attrs         Shortcode attributes.
	 * @param string $original_name Original field name (used in the warning message).
	 */
	private function maybe_warn_unsupported_attrs( array $attrs, $original_name ) {
		$skipped_attrs = array();
		foreach ( array_keys( $attrs ) as $attr_key ) {
			if ( 'show_error' === $attr_key || 0 === strpos( $attr_key, 'conv_' ) ) {
				$skipped_attrs[] = $attr_key;
			}
		}

		if ( empty( $skipped_attrs ) ) {
			return;
		}

		$this->logger->add_warning(
			FPLANT_Migrator_Base::LEVEL_INFO,
			'attrs_skipped',
			sprintf(
				/* translators: 1: field name, 2: skipped attribute list. */
				__( 'The following attributes of field "%1$s" will not be migrated: %2$s', 'form-plant' ),
				$original_name,
				implode( ', ', $skipped_attrs )
			),
			array( 'name' => $original_name, 'skipped' => $skipped_attrs )
		);
	}

	/**
	 * Applies size / maxlength / cols / rows attributes to text / textarea fields.
	 *
	 * Form Plant support matrix:
	 * - text / email / url / password: size, maxlength
	 * - textarea: rows, cols, maxlength
	 *
	 * Value normalisation: cast to int; keys with a value of 0 or below are not set.
	 *
	 * @param array  $attrs      Shortcode attributes.
	 * @param array  $field      Already-built Form Plant field array.
	 * @param string $field_type Form Plant field type.
	 * @return array Field array with size attributes applied.
	 */
	private function apply_size_attributes( array $attrs, array $field, $field_type ) {
		$supported_attrs = array(
			'text'     => array( 'size', 'maxlength' ),
			'email'    => array( 'size', 'maxlength' ),
			'url'      => array( 'size', 'maxlength' ),
			'password' => array( 'size', 'maxlength' ),
			'textarea' => array( 'rows', 'cols', 'maxlength' ),
		);

		$original_name = isset( $attrs['name'] ) ? (string) $attrs['name'] : '';
		$candidates    = array( 'size', 'maxlength', 'cols', 'rows' );

		foreach ( $candidates as $attr_key ) {
			if ( ! array_key_exists( $attr_key, $attrs ) ) {
				continue;
			}
			$raw_value = $attrs[ $attr_key ];
			if ( '' === $raw_value || null === $raw_value ) {
				continue;
			}

			$is_supported = isset( $supported_attrs[ $field_type ] )
				&& in_array( $attr_key, $supported_attrs[ $field_type ], true );

			if ( $is_supported ) {
				$int_value = (int) $raw_value;
				if ( $int_value > 0 ) {
					$field[ $attr_key ] = $int_value;
				}
				continue;
			}

			$this->logger->add_warning(
				FPLANT_Migrator_Base::LEVEL_INFO,
				'attribute_not_supported',
				sprintf(
					/* translators: 1: field type, 2: original field name, 3: attribute name, 4: attribute value. */
					__( 'The %3$s attribute (%4$s) of the %1$s field "%2$s" is not supported in Form Plant and will not be applied.', 'form-plant' ),
					$field_type,
					$original_name,
					$attr_key,
					(string) $raw_value
				),
				array(
					'field_type'    => $field_type,
					'original_name' => $original_name,
					'attribute'     => $attr_key,
					'value'         => (string) $raw_value,
				)
			);
		}

		return $field;
	}
}
