<?php
/**
 * Plugin settings (GDPR retention, notifications, uninstall).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen.
 */
final class Settings_Page {

	/**
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! Capabilities::can_manage() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'we-formkit-settings' !== $_GET['page'] ) {
			return;
		}
		if ( ! isset( $_POST['we_formkit_save_settings'] ) ) {
			return;
		}
		if ( ! isset( $_POST['we_formkit_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['we_formkit_settings_nonce'] ) ), 'we_formkit_save_settings' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$input = isset( $_POST['wek_settings'] ) && is_array( $_POST['wek_settings'] ) ? wp_unslash( $_POST['wek_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean = Settings::sanitize( $input );
		update_option( Settings::OPTION, $clean );
		update_option( 'we_formkit_delete_data_on_uninstall', ! empty( $clean['delete_data_on_uninstall'] ) );

		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-settings&saved=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'we-formkit' ) );
		}
		$settings = Settings::get();
		?>
		<div class="wrap wek-admin">
			<h1><?php esc_html_e( 'Formkit Settings', 'we-formkit' ); ?></h1>
			<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'we_formkit_save_settings', 'we_formkit_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="wek_notify_email"><?php esc_html_e( 'Default notification email', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="email" name="wek_settings[notify_email]" id="wek_notify_email" value="<?php echo esc_attr( (string) $settings['notify_email'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Used when a form has no own notification address. Falls back to the site admin email.', 'we-formkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_privacy_url"><?php esc_html_e( 'Default privacy policy URL', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="url" name="wek_settings[privacy_policy_url]" id="wek_privacy_url" value="<?php echo esc_attr( (string) $settings['privacy_policy_url'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label for="wek_retention"><?php esc_html_e( 'Retention (days)', 'we-formkit' ); ?></label></th>
						<td>
							<input class="small-text" type="number" min="0" max="3650" name="wek_settings[retention_days]" id="wek_retention" value="<?php echo esc_attr( (string) (int) $settings['retention_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Submissions older than this are deleted automatically. Set 0 to disable automatic deletion.', 'we-formkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Uninstall', 'we-formkit' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wek_settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
								<?php esc_html_e( 'Delete all forms and submissions when the plugin is uninstalled', 'we-formkit' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'we-formkit' ), 'primary', 'we_formkit_save_settings' ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Privacy notes', 'we-formkit' ); ?></h2>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Form submissions are stored in this WordPress site only (no third-party form SaaS).', 'we-formkit' ); ?></li>
				<li><?php esc_html_e( 'Spam protection uses a honeypot, timing check, and rate limiting — not reCAPTCHA.', 'we-formkit' ); ?></li>
				<li><?php esc_html_e( 'IP addresses are not stored in plain text; only a salted hash may be kept for abuse handling.', 'we-formkit' ); ?></li>
				<li><?php esc_html_e( 'Embed forms with the Formkit Form block. Optional secret-link tokens limit casual discovery.', 'we-formkit' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
