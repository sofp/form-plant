<?php
/**
 * Date (dropdown) field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fplant_field_name  = esc_attr( $field['name'] );
$fplant_field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . $fplant_field_name;
$fplant_field_class = 'fplant-field fplant-field-date-select';
if ( ! empty( $field['class'] ) ) {
	$fplant_field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$fplant_field_class .= ' ' . esc_attr( $field['custom_class'] );
}

// Parse existing value (expected format: YYYY-MM-DD)
$fplant_year  = '';
$fplant_month = '';
$fplant_day   = '';

if ( ! empty( $value ) ) {
	$fplant_date_parts = explode( '-', $value );
	if ( count( $fplant_date_parts ) === 3 ) {
		$fplant_year  = $fplant_date_parts[0];
		$fplant_month = $fplant_date_parts[1];
		$fplant_day   = $fplant_date_parts[2];
	}
}

// Set year range (default: 100 years in the past to 10 years in the future)
$fplant_current_year = (int) gmdate( 'Y' );
$fplant_year_start_offset = isset( $field['year_start'] ) ? (int) $field['year_start'] : 100;
$fplant_year_end_offset   = isset( $field['year_end'] ) ? (int) $field['year_end'] : 10;
$fplant_start_year = $fplant_current_year - $fplant_year_start_offset;
$fplant_end_year   = $fplant_current_year + $fplant_year_end_offset;
?>

<div class="<?php echo esc_attr( $fplant_field_class ); ?>">
	<select
		id="<?php echo esc_attr( $fplant_field_id ); ?>"
		name="<?php echo esc_attr( $fplant_field_name ); ?>[year]"
		class="fplant-date-select-year"
		data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
	>
		<option value=""><?php esc_html_e( 'Year', 'form-plant' ); ?></option>
		<?php for ( $fplant_y = $fplant_end_year; $fplant_y >= $fplant_start_year; $fplant_y-- ) : ?>
			<option value="<?php echo esc_attr( $fplant_y ); ?>" <?php selected( $fplant_year, $fplant_y ); ?>>
				<?php echo esc_html( $fplant_y ); ?>
			</option>
		<?php endfor; ?>
	</select>
	<span class="fplant-date-separator">/</span>

	<select
		name="<?php echo esc_attr( $fplant_field_name ); ?>[month]"
		class="fplant-date-select-month"
		data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
	>
		<option value=""><?php esc_html_e( 'Month', 'form-plant' ); ?></option>
		<?php for ( $fplant_m = 1; $fplant_m <= 12; $fplant_m++ ) : ?>
			<option value="<?php echo esc_attr( sprintf( '%02d', $fplant_m ) ); ?>" <?php selected( $fplant_month, sprintf( '%02d', $fplant_m ) ); ?>>
				<?php echo esc_html( $fplant_m ); ?>
			</option>
		<?php endfor; ?>
	</select>
	<span class="fplant-date-separator">/</span>

	<select
		name="<?php echo esc_attr( $fplant_field_name ); ?>[day]"
		class="fplant-date-select-day"
		data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
	>
		<option value=""><?php esc_html_e( 'Day', 'form-plant' ); ?></option>
		<?php for ( $fplant_d = 1; $fplant_d <= 31; $fplant_d++ ) : ?>
			<option value="<?php echo esc_attr( sprintf( '%02d', $fplant_d ) ); ?>" <?php selected( $fplant_day, sprintf( '%02d', $fplant_d ) ); ?>>
				<?php echo esc_html( $fplant_d ); ?>
			</option>
		<?php endfor; ?>
	</select>

	<!-- Hidden field to store combined value -->
	<input
		type="hidden"
		name="<?php echo esc_attr( $fplant_field_name ); ?>"
		class="fplant-date-select-value"
		value="<?php echo esc_attr( $value ); ?>"
	>
</div>
