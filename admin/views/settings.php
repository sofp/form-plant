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
	<h1><?php esc_html_e( 'Form Plant Settings', 'form-plant' ); ?></h1>
	<hr class="wp-header-end">

	<?php settings_errors(); ?>

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

			<h3 style="margin-top: 10px;"><?php esc_html_e( 'reCAPTCHA v2 (Checkbox)', 'form-plant' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fplant_recaptcha_v2_site_key">
							<?php esc_html_e( 'Site Key', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="fplant_recaptcha_v2_site_key"
							name="fplant_recaptcha_v2_site_key"
							value="<?php echo esc_attr( get_option( 'fplant_recaptcha_v2_site_key', '' ) ); ?>"
							class="regular-text"
						>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fplant_recaptcha_v2_secret_key">
							<?php esc_html_e( 'Secret Key', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="fplant_recaptcha_v2_secret_key"
							name="fplant_recaptcha_v2_secret_key"
							value="<?php echo esc_attr( get_option( 'fplant_recaptcha_v2_secret_key', '' ) ); ?>"
							class="regular-text"
						>
					</td>
				</tr>
			</table>

			<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">

			<h3><?php esc_html_e( 'reCAPTCHA v3 (Score-based)', 'form-plant' ); ?></h3>
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
					'<a href="https://cloud.google.com/security/products/recaptcha" target="_blank" rel="noopener noreferrer">Google reCAPTCHA</a>'
				);
				?>
			</p>

			<?php submit_button( __( 'Save Settings', 'form-plant' ) ); ?>
		</form>
	</div>

	<div class="fplant-card">
		<div class="fplant-card-header">
			<?php esc_html_e( 'Cloudflare Turnstile Settings', 'form-plant' ); ?>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'fplant_turnstile_settings' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fplant_turnstile_site_key">
							<?php esc_html_e( 'Site Key', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="fplant_turnstile_site_key"
							name="fplant_turnstile_site_key"
							value="<?php echo esc_attr( get_option( 'fplant_turnstile_site_key', '' ) ); ?>"
							class="regular-text"
						>
						<p class="description">
							<?php esc_html_e( 'Enter your Cloudflare Turnstile site key.', 'form-plant' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fplant_turnstile_secret_key">
							<?php esc_html_e( 'Secret Key', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="fplant_turnstile_secret_key"
							name="fplant_turnstile_secret_key"
							value="<?php echo esc_attr( get_option( 'fplant_turnstile_secret_key', '' ) ); ?>"
							class="regular-text"
						>
						<p class="description">
							<?php esc_html_e( 'Enter your Cloudflare Turnstile secret key.', 'form-plant' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="description" style="margin-top: 15px;">
				<?php
				printf(
					/* translators: %s: Cloudflare Dashboard URL */
					esc_html__( 'You can get Turnstile keys from %s.', 'form-plant' ),
					'<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">Cloudflare Dashboard</a>'
				);
				?>
			</p>

			<?php submit_button( __( 'Save Settings', 'form-plant' ) ); ?>
		</form>
	</div>

	<div class="fplant-card">
		<div class="fplant-card-header">
			<?php esc_html_e( 'Blocklist Settings', 'form-plant' ); ?>
		</div>

		<p class="description" style="margin: 15px 0 0; padding: 0 12px;">
			<?php
			printf(
				/* translators: %s: GitHub repository URL */
				esc_html__( 'This plugin includes a built-in list of disposable email domains. The list is based on %s (CC0 1.0 / Public Domain). Form submissions using email addresses from these domains are automatically blocked. Use the fields below to add additional domains or keywords to block.', 'form-plant' ),
				'<a href="https://github.com/disposable-email-domains/disposable-email-domains" target="_blank" rel="noopener noreferrer">disposable-email-domains</a>'
			);
			?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'fplant_blocklist_settings' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fplant_blocked_email_domains">
							<?php esc_html_e( 'Blocked Email Domains (one per line)', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="fplant_blocked_email_domains"
							name="fplant_blocked_email_domains"
							rows="6"
							class="large-text"
						><?php echo esc_textarea( get_option( 'fplant_blocked_email_domains', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'e.g., tempmail.com, throwaway.email', 'form-plant' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fplant_blocked_keywords">
							<?php esc_html_e( 'Blocked Keywords (one per line)', 'form-plant' ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="fplant_blocked_keywords"
							name="fplant_blocked_keywords"
							rows="6"
							class="large-text"
						><?php echo esc_textarea( get_option( 'fplant_blocked_keywords', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Submissions containing these keywords will be blocked.', 'form-plant' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'form-plant' ) ); ?>
		</form>
	</div>

</div>
