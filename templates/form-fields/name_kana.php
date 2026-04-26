<?php
/**
 * Name kana field template
 *
 * @package Form_Plant
 * @var array  $field Field configuration
 * @var string $value Field value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$fplant_field_name  = esc_attr( $field['name'] );
$fplant_field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . $fplant_field_name;
$fplant_field_class = 'fplant-field fplant-field-name-kana';
if ( ! empty( $field['custom_class'] ) ) {
	$fplant_field_class .= ' ' . esc_attr( $field['custom_class'] );
}

$fplant_name_format       = isset( $field['name_format'] ) ? $field['name_format'] : '2';
$fplant_name_labels       = isset( $field['name_labels'] ) ? $field['name_labels'] : array();
$fplant_name_placeholders = isset( $field['name_placeholders'] ) ? $field['name_placeholders'] : array();
$fplant_kana_validation   = isset( $field['kana_validation'] ) ? $field['kana_validation'] : 'katakana';
$fplant_kana_error_message = isset( $field['kana_error_message'] ) ? $field['kana_error_message'] : '';

// Determine display order based on locale.
$fplant_is_ja = ( 0 === strpos( get_locale(), 'ja' ) );

// Autocomplete attribute mapping.
$fplant_autocomplete_map = array(
	'family' => 'family-name',
	'given'  => 'given-name',
	'middle' => 'additional-name',
);

if ( '3' === $fplant_name_format ) {
	$fplant_parts_order = $fplant_is_ja
		? array( 'family', 'middle', 'given' )
		: array( 'given', 'middle', 'family' );
} elseif ( '1' === $fplant_name_format ) {
	$fplant_parts_order = array( 'family' );
} else {
	$fplant_parts_order = $fplant_is_ja
		? array( 'family', 'given' )
		: array( 'given', 'family' );
}

// Parse existing value (space-separated) into parts.
$fplant_part_values = array( 'family' => '', 'given' => '', 'middle' => '' );
if ( ! empty( $value ) && '1' !== $fplant_name_format ) {
	$fplant_value_parts = explode( ' ', $value );
	foreach ( $fplant_parts_order as $fplant_idx => $fplant_part_key ) {
		if ( isset( $fplant_value_parts[ $fplant_idx ] ) ) {
			$fplant_part_values[ $fplant_part_key ] = $fplant_value_parts[ $fplant_idx ];
		}
	}
}
?>

<div class="<?php echo esc_attr( $fplant_field_class ); ?>"
	data-kana-validation="<?php echo esc_attr( $fplant_kana_validation ); ?>"
	<?php if ( ! empty( $fplant_kana_error_message ) ) : ?>
		data-kana-error-message="<?php echo esc_attr( $fplant_kana_error_message ); ?>"
	<?php endif; ?>
>
<?php if ( '1' === $fplant_name_format ) : ?>
	<div class="fplant-name-part">
		<?php if ( ! empty( $fplant_name_labels['family'] ) ) : ?>
			<span class="fplant-name-sublabel"><?php echo esc_html( $fplant_name_labels['family'] ); ?></span>
		<?php endif; ?>
		<input
			type="text"
			id="<?php echo esc_attr( $fplant_field_id ); ?>"
			name="<?php echo esc_attr( $fplant_field_name ); ?>"
			class="fplant-name-input"
			value="<?php echo esc_attr( $value ); ?>"
			autocomplete="name"
			<?php if ( ! empty( $fplant_name_placeholders['family'] ) ) : ?>
				placeholder="<?php echo esc_attr( $fplant_name_placeholders['family'] ); ?>"
			<?php endif; ?>
		>
	</div>
<?php else : ?>
	<?php $fplant_first_part = true; ?>
	<?php foreach ( $fplant_parts_order as $fplant_part_key ) : ?>
		<div class="fplant-name-part">
			<?php if ( ! empty( $fplant_name_labels[ $fplant_part_key ] ) ) : ?>
				<span class="fplant-name-sublabel"><?php echo esc_html( $fplant_name_labels[ $fplant_part_key ] ); ?></span>
			<?php endif; ?>
			<input
				type="text"
				<?php if ( $fplant_first_part ) : ?>
					id="<?php echo esc_attr( $fplant_field_id ); ?>"
				<?php endif; ?>
				name="<?php echo esc_attr( $fplant_field_name ); ?>[<?php echo esc_attr( $fplant_part_key ); ?>]"
				class="fplant-name-input fplant-name-part-<?php echo esc_attr( $fplant_part_key ); ?>"
				data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
				value="<?php echo esc_attr( $fplant_part_values[ $fplant_part_key ] ); ?>"
				autocomplete="<?php echo esc_attr( $fplant_autocomplete_map[ $fplant_part_key ] ); ?>"
				<?php if ( ! empty( $fplant_name_placeholders[ $fplant_part_key ] ) ) : ?>
					placeholder="<?php echo esc_attr( $fplant_name_placeholders[ $fplant_part_key ] ); ?>"
				<?php endif; ?>
			>
			<div class="fplant-field-error fplant-name-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.' . $fplant_part_key ); ?>" style="display: none;"></div>
		</div>
	<?php $fplant_first_part = false; ?>
	<?php endforeach; ?>

	<!-- Hidden field to store combined value -->
	<input
		type="hidden"
		name="<?php echo esc_attr( $fplant_field_name ); ?>"
		class="fplant-name-parts-value"
		value="<?php echo esc_attr( $value ); ?>"
	>
<?php endif; ?>
</div>
