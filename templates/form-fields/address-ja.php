<?php
/**
 * Address composite field template - Japanese locale
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

$fplant_postal_format   = isset( $field['postal_format'] ) ? $field['postal_format'] : 'single';
$fplant_pref_type       = isset( $field['pref_display_type'] ) ? $field['pref_display_type'] : 'select';
$fplant_show_search_btn = ! empty( $field['postal_show_search_btn'] );
$fplant_labels          = isset( $field['address_labels'] ) ? $field['address_labels'] : array();
$fplant_placeholders    = isset( $field['address_placeholders'] ) ? $field['address_placeholders'] : array();

// Get sub-field values from form data (stored as {field_name}_{sub_key})
$fplant_form_data  = isset( $_POST ) ? $_POST : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
$fplant_sub_values = array();
$fplant_sub_keys   = array( 'postal_code', 'prefecture', 'city', 'street', 'building' );

foreach ( $fplant_sub_keys as $fplant_key ) {
	$fplant_data_key = $field['name'] . '_' . $fplant_key;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$fplant_sub_values[ $fplant_key ] = isset( $fplant_form_data[ $fplant_data_key ] ) ? sanitize_text_field( wp_unslash( $fplant_form_data[ $fplant_data_key ] ) ) : '';
}

// Also check array-style submission
if ( empty( $fplant_sub_values['postal_code'] ) && isset( $fplant_form_data[ $field['name'] ] ) && is_array( $fplant_form_data[ $field['name'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$fplant_arr = wp_unslash( $fplant_form_data[ $field['name'] ] );
	foreach ( $fplant_sub_keys as $fplant_key ) {
		if ( isset( $fplant_arr[ $fplant_key ] ) ) {
			$fplant_sub_values[ $fplant_key ] = sanitize_text_field( $fplant_arr[ $fplant_key ] );
		}
	}
}

$fplant_prefectures = FPLANT_Field_Manager::get_prefectures();

// Default labels
$fplant_default_labels = array(
	'postal_code' => __( 'Postal Code', 'form-plant' ),
	'prefecture'  => __( 'Prefecture', 'form-plant' ),
	'city'        => __( 'City', 'form-plant' ),
	'street'      => __( 'Street Address', 'form-plant' ),
	'building'    => __( 'Building / Apartment', 'form-plant' ),
);
// Remove empty labels so defaults are used
$fplant_labels = array_filter( $fplant_labels, function( $v ) { return '' !== $v; } );
$fplant_labels = wp_parse_args( $fplant_labels, $fplant_default_labels );
?>

<div class="<?php echo esc_attr( $fplant_field_class ); ?>" data-address-locale="ja" data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>">

	<!-- Postal code -->
	<div class="fplant-address-part fplant-address-postal-code">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['postal_code'] ); ?></label>
		<?php if ( 'split' === $fplant_postal_format ) : ?>
			<?php
			$fplant_pc_clean = preg_replace( '/[^0-9]/', '', $fplant_sub_values['postal_code'] );
			$fplant_pc_part1 = strlen( $fplant_pc_clean ) >= 3 ? substr( $fplant_pc_clean, 0, 3 ) : $fplant_pc_clean;
			$fplant_pc_part2 = strlen( $fplant_pc_clean ) > 3 ? substr( $fplant_pc_clean, 3 ) : '';
			?>
			<div class="fplant-postal-code-split">

				<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[postal_code_part1]"
					id="<?php echo esc_attr( $fplant_field_id ); ?>"
					class="fplant-postal-code-input fplant-postal-code-part1"
					data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
					value="<?php echo esc_attr( $fplant_pc_part1 ); ?>"
					maxlength="3" inputmode="numeric" autocomplete="postal-code"
					placeholder="<?php echo esc_attr( ! empty( $fplant_placeholders['postal_code'] ) ? substr( $fplant_placeholders['postal_code'], 0, 3 ) : '' ); ?>">
				<span class="fplant-postal-code-separator">-</span>
				<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[postal_code_part2]"
					class="fplant-postal-code-input fplant-postal-code-part2"
					data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
					value="<?php echo esc_attr( $fplant_pc_part2 ); ?>"
					maxlength="4" inputmode="numeric"
					placeholder="<?php echo esc_attr( ! empty( $fplant_placeholders['postal_code'] ) ? substr( $fplant_placeholders['postal_code'], 3, 4 ) : '' ); ?>">
				<?php if ( $fplant_show_search_btn ) : ?>
					<button type="button" class="fplant-postal-code-search"><?php esc_html_e( 'Search Address', 'form-plant' ); ?></button>
				<?php endif; ?>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $fplant_field_name ); ?>[postal_code]"
				class="fplant-address-postal-code-value"
				value="<?php echo esc_attr( $fplant_sub_values['postal_code'] ); ?>">
		<?php else : ?>
			<div class="fplant-postal-code-single">

				<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[postal_code]"
					id="<?php echo esc_attr( $fplant_field_id ); ?>"
					class="fplant-postal-code-input fplant-postal-code-full fplant-address-postal-code-value"
					value="<?php echo esc_attr( $fplant_sub_values['postal_code'] ); ?>"
					maxlength="8" inputmode="numeric" autocomplete="postal-code"
					placeholder="<?php echo esc_attr( ! empty( $fplant_placeholders['postal_code'] ) ? $fplant_placeholders['postal_code'] : '' ); ?>">
				<?php if ( $fplant_show_search_btn ) : ?>
					<button type="button" class="fplant-postal-code-search"><?php esc_html_e( 'Search Address', 'form-plant' ); ?></button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<span class="fplant-postal-code-message" style="display: none;"></span>
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.postal_code' ); ?>" style="display: none;"></div>
	</div>

	<!-- Prefecture -->
	<div class="fplant-address-part fplant-address-prefecture">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['prefecture'] ); ?></label>
		<?php if ( 'text' === $fplant_pref_type ) : ?>
			<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[prefecture]"
				class="fplant-address-prefecture-input"
				value="<?php echo esc_attr( $fplant_sub_values['prefecture'] ); ?>"
				autocomplete="address-level1"
				placeholder="<?php echo esc_attr( $fplant_placeholders['prefecture'] ?? '' ); ?>">
		<?php else : ?>
			<select name="<?php echo esc_attr( $fplant_field_name ); ?>[prefecture]"
				class="fplant-address-prefecture-input" autocomplete="address-level1">
				<option value=""><?php esc_html_e( 'Please select', 'form-plant' ); ?></option>
				<?php foreach ( $fplant_prefectures as $fplant_pref ) : ?>
					<option value="<?php echo esc_attr( $fplant_pref ); ?>" <?php selected( $fplant_sub_values['prefecture'], $fplant_pref ); ?>>
						<?php echo esc_html( $fplant_pref ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.prefecture' ); ?>" style="display: none;"></div>
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

	<!-- Street -->
	<div class="fplant-address-part fplant-address-street">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['street'] ); ?></label>
		<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[street]"
			class="fplant-address-street-input"
			value="<?php echo esc_attr( $fplant_sub_values['street'] ); ?>"
			autocomplete="address-line1"
			placeholder="<?php echo esc_attr( $fplant_placeholders['street'] ?? '' ); ?>">
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.street' ); ?>" style="display: none;"></div>
	</div>

	<!-- Building -->
	<div class="fplant-address-part fplant-address-building">
		<label class="fplant-address-sublabel"><?php echo esc_html( $fplant_labels['building'] ); ?></label>
		<input type="text" name="<?php echo esc_attr( $fplant_field_name ); ?>[building]"
			class="fplant-address-building-input"
			value="<?php echo esc_attr( $fplant_sub_values['building'] ); ?>"
			autocomplete="address-line2"
			placeholder="<?php echo esc_attr( $fplant_placeholders['building'] ?? '' ); ?>">
		<div class="fplant-field-error fplant-address-sub-error" data-field-error="<?php echo esc_attr( $fplant_field_name . '.building' ); ?>" style="display: none;"></div>
	</div>

</div>
