<?php
/**
 * Postal code field template
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
$fplant_field_class = 'fplant-field fplant-field-postal-code';
if ( ! empty( $field['custom_class'] ) ) {
	$fplant_field_class .= ' ' . esc_attr( $field['custom_class'] );
}

$fplant_postal_format     = isset( $field['postal_format'] ) ? $field['postal_format'] : 'single';
$fplant_postal_autofill   = ! empty( $field['postal_autofill'] );
$fplant_show_search_btn   = ! empty( $field['postal_show_search_btn'] );
$fplant_is_ja             = ( 0 === strpos( get_locale(), 'ja' ) );

// Parse split value
$fplant_postal_part1 = '';
$fplant_postal_part2 = '';
if ( ! empty( $value ) && 'split' === $fplant_postal_format ) {
	$fplant_clean = preg_replace( '/[^0-9]/', '', $value );
	if ( strlen( $fplant_clean ) >= 3 ) {
		$fplant_postal_part1 = substr( $fplant_clean, 0, 3 );
		$fplant_postal_part2 = substr( $fplant_clean, 3 );
	}
}

$fplant_autofill_attrs = '';
if ( $fplant_postal_autofill && $fplant_is_ja ) {
	$fplant_autofill_targets = array();
	if ( ! empty( $field['postal_target_pref'] ) ) {
		$fplant_autofill_targets['pref'] = $field['postal_target_pref'];
	}
	if ( ! empty( $field['postal_target_addr1'] ) ) {
		$fplant_autofill_targets['addr1'] = $field['postal_target_addr1'];
	}
	if ( ! empty( $field['postal_target_addr2'] ) ) {
		$fplant_autofill_targets['addr2'] = $field['postal_target_addr2'];
	}
	$fplant_autofill_attrs = ' data-autofill-targets="' . esc_attr( wp_json_encode( $fplant_autofill_targets ) ) . '"';
}
?>

<div class="<?php echo esc_attr( $fplant_field_class ); ?>"<?php echo $fplant_autofill_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above ?> data-postal-format="<?php echo esc_attr( $fplant_postal_format ); ?>">
<?php if ( 'split' === $fplant_postal_format ) : ?>
	<div class="fplant-postal-code-split">
		<?php if ( $fplant_is_ja ) : ?>

		<?php endif; ?>
		<input
			type="text"
			id="<?php echo esc_attr( $fplant_field_id ); ?>"
			name="<?php echo esc_attr( $fplant_field_name ); ?>[part1]"
			class="fplant-postal-code-input fplant-postal-code-part1"
			data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
			value="<?php echo esc_attr( $fplant_postal_part1 ); ?>"
			maxlength="3"
			inputmode="numeric"
			autocomplete="postal-code"
			<?php if ( ! empty( $field['placeholder'] ) ) : ?>
				placeholder="<?php echo esc_attr( substr( $field['placeholder'], 0, 3 ) ); ?>"
			<?php else : ?>
				placeholder=""
			<?php endif; ?>
		>
		<span class="fplant-postal-code-separator">-</span>
		<input
			type="text"
			name="<?php echo esc_attr( $fplant_field_name ); ?>[part2]"
			class="fplant-postal-code-input fplant-postal-code-part2"
			data-field-name="<?php echo esc_attr( $fplant_field_name ); ?>"
			value="<?php echo esc_attr( $fplant_postal_part2 ); ?>"
			maxlength="4"
			inputmode="numeric"
			<?php if ( ! empty( $field['placeholder'] ) ) : ?>
				placeholder="<?php echo esc_attr( substr( $field['placeholder'], 3, 4 ) ); ?>"
			<?php else : ?>
				placeholder=""
			<?php endif; ?>
		>
		<?php if ( $fplant_show_search_btn && $fplant_is_ja ) : ?>
			<button type="button" class="fplant-postal-code-search"><?php esc_html_e( 'Search Address', 'form-plant' ); ?></button>
		<?php endif; ?>
	</div>
	<!-- Hidden field to store combined value -->
	<input
		type="hidden"
		name="<?php echo esc_attr( $fplant_field_name ); ?>"
		class="fplant-postal-code-value"
		value="<?php echo esc_attr( $value ); ?>"
	>
<?php else : ?>
	<div class="fplant-postal-code-single">
		<?php if ( $fplant_is_ja ) : ?>

		<?php endif; ?>
		<input
			type="text"
			id="<?php echo esc_attr( $fplant_field_id ); ?>"
			name="<?php echo esc_attr( $fplant_field_name ); ?>"
			class="fplant-postal-code-input fplant-postal-code-full"
			value="<?php echo esc_attr( $value ); ?>"
			maxlength="<?php echo $fplant_is_ja ? '8' : '20'; ?>"
			inputmode="<?php echo $fplant_is_ja ? 'numeric' : 'text'; ?>"
			autocomplete="postal-code"
			<?php if ( ! empty( $field['placeholder'] ) ) : ?>
				placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
			<?php elseif ( $fplant_is_ja ) : ?>
				placeholder=""
			<?php endif; ?>
		>
		<?php if ( $fplant_show_search_btn && $fplant_is_ja ) : ?>
			<button type="button" class="fplant-postal-code-search"><?php esc_html_e( 'Search Address', 'form-plant' ); ?></button>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $fplant_postal_autofill && $fplant_is_ja ) : ?>
	<span class="fplant-postal-code-message" style="display: none;"></span>
<?php endif; ?>
</div>
