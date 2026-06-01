<?php
/**
 * Password field template
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$field_id    = ! empty( $field['custom_id'] ) ? esc_attr( $field['custom_id'] ) : 'fplant-field-' . esc_attr( $field['name'] );
$field_class = 'fplant-field fplant-field-password';
if ( ! empty( $field['class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['class'] );
}
if ( ! empty( $field['custom_class'] ) ) {
	$field_class .= ' ' . esc_attr( $field['custom_class'] );
}

$strength_meter = ! empty( $field['password_strength_meter'] );
$strength_level = isset( $field['password_strength_level'] ) ? $field['password_strength_level'] : 'none';
?>

<div class="fplant-password-wrapper"
	<?php if ( $strength_meter ) : ?>
		data-strength-meter="1"
		data-strength-level="<?php echo esc_attr( $strength_level ); ?>"
	<?php endif; ?>
>
	<div class="fplant-password-input-wrapper">
		<input
			type="password"
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field['name'] ); ?>"
			class="<?php echo esc_attr( $field_class ); ?>"
			<?php if ( ! empty( $field['placeholder'] ) ) : ?>
				placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
			<?php endif; ?>
			<?php if ( ! empty( $field['size'] ) ) : ?>
				size="<?php echo esc_attr( $field['size'] ); ?>"
			<?php endif; ?>
			<?php if ( ! empty( $field['password_min_length'] ) ) : ?>
				minlength="<?php echo esc_attr( $field['password_min_length'] ); ?>"
			<?php endif; ?>
			<?php if ( ! empty( $field['maxlength'] ) ) : ?>
				maxlength="<?php echo esc_attr( $field['maxlength'] ); ?>"
			<?php endif; ?>
			value="<?php echo esc_attr( $value ); ?>"
			autocomplete="new-password"
		>
		<button type="button" class="fplant-password-toggle" aria-label="<?php esc_attr_e( 'Show password', 'form-plant' ); ?>">
			<span class="dashicons dashicons-visibility"></span>
		</button>
	</div>
	<?php if ( $strength_meter ) : ?>
		<div class="fplant-password-strength-meter" aria-live="polite">
			<div class="fplant-password-strength-bar-container">
				<div class="fplant-password-strength-bar"></div>
			</div>
			<span class="fplant-password-strength-text"></span>
		</div>
	<?php endif; ?>
</div>
