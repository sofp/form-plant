<?php
/**
 * Confirmation field template - All Fields (div-based)
 *
 * This template displays all fields using div-based layout (same structure as input form).
 * Used for design type variations (simple1, simple2, normal) instead of table layout.
 *
 * @package Form_Plant
 * @var array $fields Form field definitions
 * @var array $values Submitted values
 * @var array $filenames Optional. Array of filenames for file fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<?php foreach ( $fields as $field ) :
	// Skip hidden and html fields.
	if ( in_array( $field['type'], array( 'hidden', 'html' ), true ) ) {
		continue;
	}

	$field_name  = $field['name'];
	$field_label = ! empty( $field['label'] ) ? $field['label'] : $field_name;
	$value       = isset( $values[ $field_name ] ) ? $values[ $field_name ] : '';

	// Get display value based on field type.
	$display_value = '-';
	switch ( $field['type'] ) {
		case 'textarea':
			$display_value = ! empty( $value ) ? nl2br( esc_html( $value ) ) : '-';
			break;

		case 'select':
		case 'radio':
			if ( ! empty( $value ) && ! empty( $field['options'] ) ) {
				foreach ( $field['options'] as $option ) {
					if ( isset( $option['value'] ) && (string) $option['value'] === (string) $value ) {
						$display_value = esc_html( $option['label'] );
						break;
					}
				}
			} elseif ( ! empty( $value ) ) {
				$display_value = esc_html( $value );
			}
			break;

		case 'checkbox':
			$checkbox_values = is_array( $value ) ? $value : ( ! empty( $value ) ? array( $value ) : array() );
			$display_labels  = array();
			if ( ! empty( $checkbox_values ) && ! empty( $field['options'] ) ) {
				foreach ( $checkbox_values as $val ) {
					foreach ( $field['options'] as $option ) {
						if ( isset( $option['value'] ) && (string) $option['value'] === (string) $val ) {
							$display_labels[] = $option['label'];
							break;
						}
					}
				}
			}
			// Fallback to values if no labels found.
			if ( empty( $display_labels ) && ! empty( $checkbox_values ) ) {
				$display_labels = $checkbox_values;
			}
			$delimiter     = isset( $field['delimiter'] ) ? $field['delimiter'] : ', ';
			$display_value = ! empty( $display_labels ) ? esc_html( implode( $delimiter, $display_labels ) ) : '-';
			break;

		case 'file':
			// Check for filename in filenames array.
			$filename = isset( $filenames[ $field_name ] ) ? $filenames[ $field_name ] : '';
			if ( ! empty( $filename ) ) {
				$display_value = esc_html( $filename );
			} elseif ( ! empty( $value ) ) {
				$display_value = esc_html( $value );
			}
			break;

		case 'address':
			$fplant_is_ja_confirm = ( 0 === strpos( get_locale(), 'ja' ) );
			if ( $fplant_is_ja_confirm ) {
				$fplant_c_postal = isset( $values[ $field_name . '_postal_code' ] ) ? $values[ $field_name . '_postal_code' ] : '';
				$fplant_c_pref   = isset( $values[ $field_name . '_prefecture' ] ) ? $values[ $field_name . '_prefecture' ] : '';
				$fplant_c_city   = isset( $values[ $field_name . '_city' ] ) ? $values[ $field_name . '_city' ] : '';
				$fplant_c_street = isset( $values[ $field_name . '_street' ] ) ? $values[ $field_name . '_street' ] : '';
				$fplant_c_bldg   = isset( $values[ $field_name . '_building' ] ) ? $values[ $field_name . '_building' ] : '';
				// Format postal code
				if ( ! empty( $fplant_c_postal ) ) {
					$fplant_c_clean = preg_replace( '/[^0-9]/', '', $fplant_c_postal );
					if ( 7 === strlen( $fplant_c_clean ) ) {
						$fplant_c_postal = substr( $fplant_c_clean, 0, 3 ) . '-' . substr( $fplant_c_clean, 3 );
					}
				}
				$fplant_c_lines = array();
				if ( ! empty( $fplant_c_postal ) ) {
					$fplant_c_lines[] = $fplant_c_postal;
				}
				$fplant_c_addr = $fplant_c_pref . $fplant_c_city . $fplant_c_street;
				if ( ! empty( $fplant_c_addr ) ) {
					$fplant_c_lines[] = $fplant_c_addr;
				}
				if ( ! empty( $fplant_c_bldg ) ) {
					$fplant_c_lines[] = $fplant_c_bldg;
				}
				$display_value = ! empty( $fplant_c_lines ) ? nl2br( esc_html( implode( "\n", $fplant_c_lines ) ) ) : '-';
			} else {
				$fplant_c_street  = isset( $values[ $field_name . '_street' ] ) ? $values[ $field_name . '_street' ] : '';
				$fplant_c_addr2   = isset( $values[ $field_name . '_address2' ] ) ? $values[ $field_name . '_address2' ] : '';
				$fplant_c_city    = isset( $values[ $field_name . '_city' ] ) ? $values[ $field_name . '_city' ] : '';
				$fplant_c_state   = isset( $values[ $field_name . '_state' ] ) ? $values[ $field_name . '_state' ] : '';
				$fplant_c_postal  = isset( $values[ $field_name . '_postal_code' ] ) ? $values[ $field_name . '_postal_code' ] : '';
				$fplant_c_country = isset( $values[ $field_name . '_country' ] ) ? $values[ $field_name . '_country' ] : '';
				$fplant_c_lines   = array();
				if ( ! empty( $fplant_c_street ) ) {
					$fplant_c_lines[] = $fplant_c_street;
				}
				if ( ! empty( $fplant_c_addr2 ) ) {
					$fplant_c_lines[] = $fplant_c_addr2;
				}
				$fplant_c_city_line = '';
				if ( ! empty( $fplant_c_city ) ) {
					$fplant_c_city_line .= $fplant_c_city;
				}
				if ( ! empty( $fplant_c_state ) ) {
					$fplant_c_city_line .= ( ! empty( $fplant_c_city_line ) ? ', ' : '' ) . $fplant_c_state;
				}
				if ( ! empty( $fplant_c_postal ) ) {
					$fplant_c_city_line .= ( ! empty( $fplant_c_city_line ) ? ' ' : '' ) . $fplant_c_postal;
				}
				if ( ! empty( $fplant_c_city_line ) ) {
					$fplant_c_lines[] = $fplant_c_city_line;
				}
				if ( ! empty( $fplant_c_country ) ) {
					$fplant_c_lines[] = $fplant_c_country;
				}
				$display_value = ! empty( $fplant_c_lines ) ? nl2br( esc_html( implode( "\n", $fplant_c_lines ) ) ) : '-';
			}
			break;

		case 'postal_code':
			if ( ! empty( $value ) && 0 === strpos( get_locale(), 'ja' ) ) {
				$fplant_c_pc_clean = preg_replace( '/[^0-9]/', '', $value );
				if ( 7 === strlen( $fplant_c_pc_clean ) ) {
					$display_value = esc_html( substr( $fplant_c_pc_clean, 0, 3 ) . '-' . substr( $fplant_c_pc_clean, 3 ) );
				} else {
					$display_value = esc_html( $value );
				}
			} elseif ( ! empty( $value ) ) {
				$display_value = esc_html( $value );
			}
			break;

		default:
			// text, email, date, date_select, tel, url, number, time, prefecture, etc.
			$display_value = ! empty( $value ) ? esc_html( $value ) : '-';
			break;
	}
	?>
	<div class="fplant-field-group" data-field-name="<?php echo esc_attr( $field_name ); ?>">
		<label><?php echo esc_html( $field_label ); ?></label>
		<div class="fplant-field-value"><?php echo $display_value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above ?></div>
	</div>
<?php endforeach; ?>
