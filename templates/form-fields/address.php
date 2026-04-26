<?php
/**
 * Address composite field template - International layout
 *
 * For Japanese locale, see address-ja.php.
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
$fplant_field_class = 'fplant-field fplant-field-address';
if ( ! empty( $field['custom_class'] ) ) {
	$fplant_field_class .= ' ' . esc_attr( $field['custom_class'] );
}

$fplant_labels       = isset( $field['address_labels'] ) ? $field['address_labels'] : array();
$fplant_placeholders = isset( $field['address_placeholders'] ) ? $field['address_placeholders'] : array();

// Get sub-field values from form data (stored as {field_name}_{sub_key})
$fplant_form_data  = isset( $_POST ) ? $_POST : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
$fplant_sub_values = array();
$fplant_sub_keys   = array( 'street', 'address2', 'city', 'state', 'postal_code', 'country' );

foreach ( $fplant_sub_keys as $fplant_key ) {
	$fplant_data_key = $field['name'] . '_' . $fplant_key;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$fplant_sub_values[ $fplant_key ] = isset( $fplant_form_data[ $fplant_data_key ] ) ? sanitize_text_field( wp_unslash( $fplant_form_data[ $fplant_data_key ] ) ) : '';
}

// Also check array-style submission
if ( empty( $fplant_sub_values['street'] ) && isset( $fplant_form_data[ $field['name'] ] ) && is_array( $fplant_form_data[ $field['name'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$fplant_arr = wp_unslash( $fplant_form_data[ $field['name'] ] );
	foreach ( $fplant_sub_keys as $fplant_key ) {
		if ( isset( $fplant_arr[ $fplant_key ] ) ) {
			$fplant_sub_values[ $fplant_key ] = sanitize_text_field( $fplant_arr[ $fplant_key ] );
		}
	}
}

$fplant_countries = FPLANT_Field_Manager::get_countries();

// Default labels
$fplant_default_labels = array(
	'postal_code' => __( 'Postal Code', 'form-plant' ),
	'city'        => __( 'City', 'form-plant' ),
	'street'      => __( 'Street Address', 'form-plant' ),
	'country'     => __( 'Country', 'form-plant' ),
	'state'       => __( 'State / Province', 'form-plant' ),
	'address2'    => __( 'Address Line 2', 'form-plant' ),
);
// Remove empty labels so defaults are used
$fplant_labels = array_filter( $fplant_labels, function( $v ) { return '' !== $v; } );
$fplant_labels = wp_parse_args( $fplant_labels, $fplant_default_labels );
?>

<div class="<?php echo esc_attr( $fplant_field_class ); ?>" data-address-locale="intl" data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>">

	<!-- Street Address -->
	<div class="fplant-address-part fplant-address-street">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['street'] ); ?></label>
		<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[street]"
			id="<?php echo esc_attr( $fplant_field_id ); ?>"
			class="fplant-address-street-input"
			value="<?php echo esc_attr( $fplant_sub_values['street'] ); ?>"
			autocomplete="street-address"
			placeholder="<?php echo esc_attr( $fplant_placeholders['street'] ?? '' ); ?>">
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.street' ); ?>" style="display: none;"></div>
	</div>

	<!-- Address Line 2 -->
	<div class="fplant-address-part fplant-address-address2">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['address2'] ); ?></label>
		<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[address2]"
			class="fplant-address-address2-input"
			value="<?php echo esc_attr( $fplant_sub_values['address2'] ); ?>"
			autocomplete="address-line2"
			placeholder="<?php echo esc_attr( $fplant_placeholders['address2'] ?? '' ); ?>">
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.address2' ); ?>" style="display: none;"></div>
	</div>

	<!-- City -->
	<div class="fplant-address-part fplant-address-city">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['city'] ); ?></label>
		<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[city]"
			class="fplant-address-city-input"
			value="<?php echo esc_attr( $fplant_sub_values['city'] ); ?>"
			autocomplete="address-level2"
			placeholder="<?php echo esc_attr( $fplant_placeholders['city'] ?? '' ); ?>">
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.city' ); ?>" style="display: none;"></div>
	</div>

	<!-- State / Province -->
	<div class="fplant-address-part fplant-address-state">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['state'] ); ?></label>
		<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[state]"
			class="fplant-address-state-input"
			value="<?php echo esc_attr( $fplant_sub_values['state'] ); ?>"
			autocomplete="address-level1"
			placeholder="<?php echo esc_attr( $fplant_placeholders['state'] ?? '' ); ?>">
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.state' ); ?>" style="display: none;"></div>
	</div>

	<!-- ZIP / Postal Code -->
	<div class="fplant-address-part fplant-address-postal-code">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['postal_code'] ); ?></label>
		<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[postal_code]"
			class="fplant-postal-code-input fplant-address-postal-code-value"
			value="<?php echo esc_attr( $fplant_sub_values['postal_code'] ); ?>"
			autocomplete="postal-code"
			placeholder="<?php echo esc_attr( $fplant_placeholders['postal_code'] ?? '' ); ?>">
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.postal_code' ); ?>" style="display: none;"></div>
	</div>

	<!-- Country -->
	<div class="fplant-address-part fplant-address-country">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['country'] ); ?></label>
		<select name="<?php echo esc_attr( $fplant_field_name ); ?>[country]"
			class="fplant-address-country-input" autocomplete="country-name">
			<option value=""><?php esc_html_e( 'Please select', 'form-plant' ); ?></option>
			<?php foreach ( $fplant_countries as $fplant_code => $fplant_country_name ) : ?>
				<option value="<?php echo esc_attr( $fplant_country_name ); ?>" <?php selected( $fplant_sub_values['country'], $fplant_country_name ); ?>>
					<?php echo esc_html( $fplant_country_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.country' ); ?>" style="display: none;"></div>
	</div>

</div>
