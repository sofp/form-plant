<?php
/**
 * Tel (phone) field template
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . esc_attr( $field['name'] );
$field_class = 'fplant-field fplant-field-tel';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}

$fplant_tel_format = isset( $field['tel_format'] ) ? $field['tel_format'] : 'single';
$fplant_field_name = esc_attr( $field['name'] );

// Resolve the effective value (submitted value takes precedence over default).
$fplant_tel_value = '';
if ( '' !== (string) $value ) {
	$fplant_tel_value = (string) $value;
} elseif ( ! empty( $field['default'] ) ) {
	$fplant_tel_value = (string) $field['default'];
}
?>

<?php if ( 'split3' === $fplant_tel_format ) : ?>
	<?php
	// Split the combined value (e.g. "090-1234-5678") back into 3 parts for redisplay.
	$fplant_tel_parts = array( '', '', '' );
	if ( '' !== $fplant_tel_value ) {
		$fplant_tel_segments = explode( '-', $fplant_tel_value );
		if ( count( $fplant_tel_segments ) >= 3 ) {
			$fplant_tel_parts[0] = $fplant_tel_segments[0];
			$fplant_tel_parts[1] = $fplant_tel_segments[1];
			// Any extra segments collapse into the third box.
			$fplant_tel_parts[2] = implode( '-', array_slice( $fplant_tel_segments, 2 ) );
		} else {
			// Not hyphen-delimited (e.g. legacy single value): put everything in the first box.
			$fplant_tel_parts[0] = $fplant_tel_value;
		}
	}
	?>
	<div class="<?php echo esc_attr( $field_class ); ?>" data-tel-format="split3">
		<div class="fplant-tel-split">
			<input
				type="tel"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $fplant_field_name ); ?>[part1]"
				class="fplant-tel-input fplant-tel-part1"
				data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
				value="<?php echo esc_attr( $fplant_tel_parts[0] ); ?>"
				maxlength="5"
				inputmode="numeric"
				autocomplete="tel-area-code"
			>
			<span class="fplant-tel-separator">-</span>
			<input
				type="tel"
				name="<?php echo esc_attr( $fplant_field_name ); ?>[part2]"
				class="fplant-tel-input fplant-tel-part2"
				data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
				value="<?php echo esc_attr( $fplant_tel_parts[1] ); ?>"
				maxlength="4"
				inputmode="numeric"
				autocomplete="tel-local-prefix"
			>
			<span class="fplant-tel-separator">-</span>
			<input
				type="tel"
				name="<?php echo esc_attr( $fplant_field_name ); ?>[part3]"
				class="fplant-tel-input fplant-tel-part3"
				data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
				value="<?php echo esc_attr( $fplant_tel_parts[2] ); ?>"
				maxlength="4"
				inputmode="numeric"
				autocomplete="tel-local-suffix"
			>
		</div>
		<!-- Hidden field to store combined value -->
		<input
			type="hidden"
			name="<?php echo esc_attr( $fplant_field_name ); ?>"
			class="fplant-tel-value"
			value="<?php echo esc_attr( $fplant_tel_value ); ?>"
		>
	</div>
<?php else : ?>
	<input
		type="tel"
		id="<?php echo esc_attr( $field_id ); ?>"
		name="<?php echo esc_attr( $field['name'] ); ?>"
		class="<?php echo esc_attr( $field_class ); ?>"
		<?php if ( ! empty( $field['placeholder'] ) ) : ?>
			placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
		<?php endif; ?>
		value="<?php echo esc_attr( $fplant_tel_value ); ?>"
	>
<?php endif; ?>
