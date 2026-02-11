<?php
/**
 * Date (dropdown) field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_name  = esc_attr( $field['name'] );
$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . $field_name;
$field_class = 'fplant-field fplant-field-date-select';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}

// Parse existing value (expected format: YYYY-MM-DD)
$year  = '';
$month = '';
$day   = '';

if ( ! empty( $value ) ) {
	$date_parts = explode( '-', $value );
	if ( count( $date_parts ) === 3 ) {
		$year  = $date_parts[0];
		$month = $date_parts[1];
		$day   = $date_parts[2];
	}
}

// Set year range (default: 100 years in the past to 10 years in the future)
$current_year = (int) gmdate( 'Y' );
$year_start_offset = isset( $field['year_start'] ) ? (int) $field['year_start'] : 100;
$year_end_offset   = isset( $field['year_end'] ) ? (int) $field['year_end'] : 10;
$start_year = $current_year - $year_start_offset;
$end_year   = $current_year + $year_end_offset;
?>

<div id="<?php echo esc_attr( $field_id ); ?>" class="<?php echo esc_attr( $field_class ); ?>">
	<select
		name="<?php echo esc_attr( $field_name ); ?>[year]"
		class="fplant-date-select-year"
		data-field-name="<?php echo esc_attr( $field_name ); ?>"
	>
		<option value=""><?php esc_html_e( 'Year', 'form-plant' ); ?></option>
		<?php for ( $y = $end_year; $y >= $start_year; $y-- ) : ?>
			<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $year, $y ); ?>>
				<?php echo esc_html( $y ); ?>
			</option>
		<?php endfor; ?>
	</select>
	<span class="fplant-date-separator">/</span>

	<select
		name="<?php echo esc_attr( $field_name ); ?>[month]"
		class="fplant-date-select-month"
		data-field-name="<?php echo esc_attr( $field_name ); ?>"
	>
		<option value=""><?php esc_html_e( 'Month', 'form-plant' ); ?></option>
		<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
			<option value="<?php echo esc_attr( sprintf( '%02d', $m ) ); ?>" <?php selected( $month, sprintf( '%02d', $m ) ); ?>>
				<?php echo esc_html( $m ); ?>
			</option>
		<?php endfor; ?>
	</select>
	<span class="fplant-date-separator">/</span>

	<select
		name="<?php echo esc_attr( $field_name ); ?>[day]"
		class="fplant-date-select-day"
		data-field-name="<?php echo esc_attr( $field_name ); ?>"
	>
		<option value=""><?php esc_html_e( 'Day', 'form-plant' ); ?></option>
		<?php for ( $d = 1; $d <= 31; $d++ ) : ?>
			<option value="<?php echo esc_attr( sprintf( '%02d', $d ) ); ?>" <?php selected( $day, sprintf( '%02d', $d ) ); ?>>
				<?php echo esc_html( $d ); ?>
			</option>
		<?php endfor; ?>
	</select>

	<!-- Hidden field to store combined value -->
	<input
		type="hidden"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fplant-date-select-value"
		value="<?php echo esc_attr( $value ); ?>"
	>
</div>
