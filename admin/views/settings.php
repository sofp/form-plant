<?php
/**
 * Settings page
 *
 * @package Form_Plant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap fplant-admin-page">
	<div class="fplant-page-header">
		<h1><?php esc_html_e( 'Form Plant Settings', 'form-plant' ); ?></h1>
	</div>

	<div class="fplant-card">
		<div class="fplant-card-header">
			<?php esc_html_e( 'Version Information', 'form-plant' ); ?>
		</div>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Version', 'form-plant' ); ?></th>
				<td><?php echo esc_html( FPLANT_VERSION ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'PHP Version', 'form-plant' ); ?></th>
				<td><?php echo esc_html( phpversion() ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'WordPress Version', 'form-plant' ); ?></th>
				<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
			</tr>
		</table>
	</div>

	<div class="fplant-card">
		<div class="fplant-card-header">
			<?php esc_html_e( 'reCAPTCHA Settings', 'form-plant' ); ?>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'fplant_recaptcha_settings' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fplant_recaptcha_site_key">
							<?php esc_html_e( 'Site Key', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="fplant_recaptcha_site_key"
							name="fplant_recaptcha_site_key"
							value="<?php echo esc_attr( get_option( 'fplant_recaptcha_site_key', '' ) ); ?>"
							class="regular-text"
						>
						<p class="description">
							<?php esc_html_e( 'Enter your Google reCAPTCHA site key.', 'form-plant' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fplant_recaptcha_secret_key">
							<?php esc_html_e( 'Secret Key', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="fplant_recaptcha_secret_key"
							name="fplant_recaptcha_secret_key"
							value="<?php echo esc_attr( get_option( 'fplant_recaptcha_secret_key', '' ) ); ?>"
							class="regular-text"
						>
						<p class="description">
							<?php esc_html_e( 'Enter your Google reCAPTCHA secret key.', 'form-plant' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fplant_recaptcha_v3_threshold">
							<?php esc_html_e( 'v3 Score Threshold', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="fplant_recaptcha_v3_threshold"
							name="fplant_recaptcha_v3_threshold"
							value="<?php echo esc_attr( get_option( 'fplant_recaptcha_v3_threshold', '0.5' ) ); ?>"
							min="0"
							max="1"
							step="0.1"
							style="width: 80px;"
						>
						<p class="description">
							<?php esc_html_e( 'When using reCAPTCHA v3, submissions below this score will be flagged as spam (0.0-1.0, recommended: 0.5)', 'form-plant' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="description" style="margin-top: 15px;">
				<?php
				printf(
					/* translators: %s: Google reCAPTCHA admin URL */
					esc_html__( 'You can get reCAPTCHA keys from %s.', 'form-plant' ),
					'<a href="https://cloud.google.com/security/products/recaptcha" target="_blank">Google reCAPTCHA</a>'
				);
				?>
			</p>

			<?php submit_button( __( 'Save Settings', 'form-plant' ) ); ?>
		</form>
	</div>

</div>
