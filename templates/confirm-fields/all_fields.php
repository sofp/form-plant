<?php
/**
 * Confirmation field template - All Fields
 *
 * This template displays all fields in a table format with labels.
 * Used for default confirmation screen and [fplant_all_fields] shortcode.
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
<table class="fplant-confirmation-table">
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

		default:
			// text, email, date, date_select, tel, url, number, time, etc.
			$display_value = ! empty( $value ) ? esc_html( $value ) : '-';
			break;
	}
	?>
	<tr>
		<th><?php echo esc_html( $field_label ); ?></th>
		<td><?php echo $display_value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above ?></td>
	</tr>
<?php endforeach; ?>
</table>
