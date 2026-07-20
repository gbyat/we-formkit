<?php
/**
 * Plugin settings (GDPR retention, notifications, uninstall).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Settings;
use Webentwicklerin\WeFormkit\Validation_Messages;

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
		$settings       = Settings::get();
		$schemes        = Settings::admin_schemes();
		$scheme         = Settings::admin_scheme();
		$admin_email    = (string) get_option( 'admin_email' );
		$site_name      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$notify_display = (string) $settings['notify_email'];
		if ( '' === $notify_display ) {
			$notify_display = $admin_email;
		}
		$from_display = (string) $settings['from_name'];
		if ( '' === $from_display ) {
			$from_display = $site_name;
		}
		$privacy_mode  = (string) $settings['privacy_policy_mode'];
		$wp_page_label = Settings::wp_privacy_page_label();
		$wp_page_url   = get_privacy_policy_url();
		?>
		<div class="wrap wek-admin wek-admin--plugin-settings" data-wek-scheme="<?php echo esc_attr( $scheme ); ?>">
			<h1><?php esc_html_e( 'Formkit Settings', 'we-formkit' ); ?></h1>
			<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>

			<form method="post" id="wek-plugin-settings-form">
				<?php wp_nonce_field( 'we_formkit_save_settings', 'we_formkit_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin color scheme', 'we-formkit' ); ?></th>
						<td>
							<fieldset class="wek-admin-scheme">
								<legend class="screen-reader-text"><?php esc_html_e( 'Admin color scheme', 'we-formkit' ); ?></legend>
								<?php foreach ( $schemes as $slug => $label ) : ?>
									<label class="wek-admin-scheme__option">
										<input
											type="radio"
											name="wek_settings[admin_scheme]"
											value="<?php echo esc_attr( $slug ); ?>"
											<?php checked( $scheme, $slug ); ?>
										/>
										<span class="wek-admin-scheme__swatch" data-wek-scheme="<?php echo esc_attr( $slug ); ?>" aria-hidden="true"></span>
										<span class="wek-admin-scheme__label"><?php echo esc_html( $label ); ?></span>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Accent color for the Formkit admin UI (builder, settings). Does not change frontend form colors.', 'we-formkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_notify_email"><?php esc_html_e( 'Default notification email', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="email" name="wek_settings[notify_email]" id="wek_notify_email" value="<?php echo esc_attr( $notify_display ); ?>" />
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: site admin email */
										__( 'Prefilled with the WordPress admin email (%s). Change it to use a different default; leave as the admin email to follow that address.', 'we-formkit' ),
										$admin_email
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_from_name"><?php esc_html_e( 'Default from name', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" name="wek_settings[from_name]" id="wek_from_name" value="<?php echo esc_attr( $from_display ); ?>" />
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: site title */
										__( 'Used as the From name when a notification leaves it empty. Prefilled with the site title (%s).', 'we-formkit' ),
										$site_name
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_privacy_mode"><?php esc_html_e( 'Default privacy policy', 'we-formkit' ); ?></label></th>
						<td>
							<select name="wek_settings[privacy_policy_mode]" id="wek_privacy_mode">
								<?php if ( '' !== $wp_page_label && is_string( $wp_page_url ) && '' !== $wp_page_url ) : ?>
									<option value="wp" <?php selected( $privacy_mode, 'wp' ); ?>>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: privacy page title */
												__( 'WordPress privacy page: %s', 'we-formkit' ),
												$wp_page_label
											)
										);
										?>
									</option>
								<?php else : ?>
									<option value="wp" <?php selected( $privacy_mode, 'wp' ); ?>>
										<?php esc_html_e( 'WordPress privacy page (not set yet)', 'we-formkit' ); ?>
									</option>
								<?php endif; ?>
								<option value="custom" <?php selected( $privacy_mode, 'custom' ); ?>><?php esc_html_e( 'Custom URL', 'we-formkit' ); ?></option>
							</select>
							<?php if ( '' === $wp_page_label ) : ?>
								<p class="description">
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %s: link to WP privacy settings */
											__( 'No privacy policy page is set in WordPress. Configure one under %s, or choose a custom URL.', 'we-formkit' ),
											'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings → Privacy', 'we-formkit' ) . '</a>'
										),
										array(
											'a' => array(
												'href' => true,
											),
										)
									);
									?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php
									echo wp_kses(
										sprintf(
											/* translators: 1: privacy page URL, 2: link to WP privacy settings */
											__( 'Uses the page from WordPress Privacy settings (%1$s). Change it under %2$s.', 'we-formkit' ),
											'<a href="' . esc_url( $wp_page_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $wp_page_url ) . '</a>',
											'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings → Privacy', 'we-formkit' ) . '</a>'
										),
										array(
											'a' => array(
												'href'   => true,
												'target' => true,
												'rel'    => true,
											),
										)
									);
									?>
								</p>
							<?php endif; ?>
							<p class="wek-reveal" id="wek-privacy-custom-wrap" <?php echo 'custom' === $privacy_mode ? '' : 'hidden'; ?>>
								<label for="wek_privacy_url" class="screen-reader-text"><?php esc_html_e( 'Custom privacy policy URL', 'we-formkit' ); ?></label>
								<input class="regular-text" type="url" name="wek_settings[privacy_policy_url]" id="wek_privacy_url" value="<?php echo esc_attr( (string) $settings['privacy_policy_url'] ); ?>" placeholder="https://" />
							</p>
						</td>
					</tr>
					<tr>
						<th colspan="2"><h2 class="title" style="margin:1.5rem 0 0;"><?php esc_html_e( 'Validation', 'we-formkit' ); ?></h2></th>
					</tr>
					<tr>
						<th><label for="wek_validation_required"><?php esc_html_e( 'Default required message', 'we-formkit' ); ?></label></th>
						<td>
							<input
								class="regular-text"
								type="text"
								name="wek_settings[validation_required]"
								id="wek_validation_required"
								value="<?php echo esc_attr( '' !== (string) $settings['validation_required'] ? (string) $settings['validation_required'] : Validation_Messages::builtin_required_template() ); ?>"
							/>
							<p class="description"><?php esc_html_e( 'Used when a field has no own required message. Include {label} for the field label.', 'we-formkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_validation_invalid"><?php esc_html_e( 'Default invalid message', 'we-formkit' ); ?></label></th>
						<td>
							<input
								class="regular-text"
								type="text"
								name="wek_settings[validation_invalid]"
								id="wek_validation_invalid"
								value="<?php echo esc_attr( '' !== (string) $settings['validation_invalid'] ? (string) $settings['validation_invalid'] : Validation_Messages::builtin_invalid_template() ); ?>"
							/>
							<p class="description"><?php esc_html_e( 'Used when a field has no own invalid message. Include {label} for the field label.', 'we-formkit' ); ?></p>
							<p class="description"><?php esc_html_e( 'Errors appear under each field with an icon; color is never the only cue.', 'we-formkit' ); ?></p>
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
		<script>
		(function () {
			var mode = document.getElementById('wek_privacy_mode');
			var wrap = document.getElementById('wek-privacy-custom-wrap');
			if (!mode || !wrap) return;
			function sync() {
				wrap.hidden = mode.value !== 'custom';
			}
			mode.addEventListener('change', sync);
			sync();
		})();
		</script>
		<?php
	}
}
