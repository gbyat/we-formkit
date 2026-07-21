<?php
/**
 * Plugin settings (GDPR retention, notifications, uninstall).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Mailer;
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

		if ( isset( $_POST['we_formkit_send_test_mail'] ) ) {
			self::handle_test_mail();
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
	 * Send a one-off transport test email.
	 *
	 * @return void
	 */
	private static function handle_test_mail() {
		if ( ! isset( $_POST['we_formkit_test_mail_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['we_formkit_test_mail_nonce'] ) ), 'we_formkit_send_test_mail' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$recipient = isset( $_POST['wek_test_mail_recipient'] ) ? sanitize_email( wp_unslash( (string) $_POST['wek_test_mail_recipient'] ) ) : '';
		if ( ! is_email( $recipient ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-settings&test_mail=invalid' ) );
			exit;
		}

		$ok = Mailer::send_test_email( $recipient );
		wp_safe_redirect(
			admin_url(
				'admin.php?page=we-formkit-settings&test_mail=' . ( $ok ? 'sent' : 'failed' )
			)
		);
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
		$from_email_display = (string) ( $settings['from_email'] ?? '' );
		if ( '' === $from_email_display ) {
			$from_email_display = $admin_email;
		}
		$privacy_mode = (string) $settings['privacy_policy_mode'];
		$wp_privacy   = Settings::wp_privacy_page();
		$privacy_link = '<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings → Privacy', 'we-formkit' ) . '</a>';
		$transport    = isset( $settings['mail_transport'] ) ? (string) $settings['mail_transport'] : 'wp_default';
		$smtp_enc     = isset( $settings['smtp_encryption'] ) ? (string) $settings['smtp_encryption'] : 'tls';
		$has_smtp_pw  = '' !== (string) ( $settings['smtp_password'] ?? '' );
		$test_status  = isset( $_GET['test_mail'] ) ? sanitize_key( (string) wp_unslash( $_GET['test_mail'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap wek-admin wek-admin--plugin-settings" data-wek-scheme="<?php echo esc_attr( $scheme ); ?>">
			<h1><?php esc_html_e( 'Formkit Settings', 'we-formkit' ); ?></h1>
			<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'sent' === $test_status ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test email sent. Check the inbox (and spam folder).', 'we-formkit' ); ?></p></div>
			<?php elseif ( 'failed' === $test_status ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Test email failed. Check SMTP settings and server logs.', 'we-formkit' ); ?></p></div>
			<?php elseif ( 'invalid' === $test_status ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Enter a valid recipient email for the transport test.', 'we-formkit' ); ?></p></div>
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
						<th colspan="2"><h2 class="title" style="margin:1.5rem 0 0;"><?php esc_html_e( 'Mail transport', 'we-formkit' ); ?></h2></th>
					</tr>
					<tr>
						<th><label for="wek_from_name"><?php esc_html_e( 'From name', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" name="wek_settings[from_name]" id="wek_from_name" value="<?php echo esc_attr( $from_display ); ?>" />
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: site title */
										__( 'Used when a notification leaves From name empty. Prefilled with the site title (%s).', 'we-formkit' ),
										$site_name
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_from_email"><?php esc_html_e( 'From email', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="email" name="wek_settings[from_email]" id="wek_from_email" value="<?php echo esc_attr( $from_email_display ); ?>" />
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: site admin email */
										__( 'Used when a notification leaves From email empty. Prefilled with the WordPress admin email (%s). For SMTP, use an address your provider allows.', 'we-formkit' ),
										$admin_email
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_email_footer"><?php esc_html_e( 'Email footer', 'we-formkit' ); ?></label></th>
						<td>
							<textarea
								class="large-text"
								rows="4"
								name="wek_settings[email_footer]"
								id="wek_email_footer"
							><?php echo esc_textarea( (string) ( $settings['email_footer'] ?? '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'HTML allowed. Appended to Save & Resume emails, and used as the default notification footer when a notification leaves Footer empty (including admin notifications).', 'we-formkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wek_mail_transport"><?php esc_html_e( 'Transport', 'we-formkit' ); ?></label></th>
						<td>
							<?php
							$wstp_active = Mailer::subscribe_to_posts_active();
							$wstp_ready  = Mailer::subscribe_to_posts_smtp_ready();
							$wstp_mail   = Mailer::subscribe_to_posts_mail_settings();
							$wstp_global = is_array( $wstp_mail ) && 'yes' === (string) ( $wstp_mail['apply_globally'] ?? 'no' );
							?>
							<select name="wek_settings[mail_transport]" id="wek_mail_transport">
								<option value="wp_default" <?php selected( $transport, 'wp_default' ); ?>><?php esc_html_e( 'WordPress default (wp_mail)', 'we-formkit' ); ?></option>
								<?php if ( $wstp_active ) : ?>
									<option value="subscribe_to_posts" <?php selected( $transport, 'subscribe_to_posts' ); ?>><?php esc_html_e( 'WE Subscribe to Posts (shared SMTP)', 'we-formkit' ); ?></option>
								<?php endif; ?>
								<option value="smtp" <?php selected( $transport, 'smtp' ); ?>><?php esc_html_e( 'Custom SMTP', 'we-formkit' ); ?></option>
								<option value="gmail" <?php selected( $transport, 'gmail' ); ?>><?php esc_html_e( 'Gmail (SMTP preset)', 'we-formkit' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Applies only to Formkit notification and test emails — not to other plugins (unless Subscribe to Posts is set to apply globally).', 'we-formkit' ); ?></p>
							<?php if ( $wstp_active ) : ?>
								<p class="description wek-mail-wstp-hint" id="wek-mail-wstp-hint" <?php echo 'subscribe_to_posts' === $transport ? '' : 'hidden'; ?>>
									<?php if ( $wstp_ready ) : ?>
										<?php
										echo wp_kses(
											sprintf(
												/* translators: %s: link to WSTP mail settings */
												__( 'Uses the SMTP settings from %s. Change host, credentials, or Gmail preset there.', 'we-formkit' ),
												'<a href="' . esc_url( admin_url( 'admin.php?page=wstp-mail-settings' ) ) . '">' . esc_html__( 'Post Subscriptions → Mail Transport', 'we-formkit' ) . '</a>'
											),
											array(
												'a' => array(
													'href' => true,
												),
											)
										);
										?>
									<?php else : ?>
										<?php
										echo wp_kses(
											sprintf(
												/* translators: %s: link to WSTP mail settings */
												__( 'Subscribe to Posts is active, but SMTP is not configured yet. Set Custom SMTP or Gmail under %s.', 'we-formkit' ),
												'<a href="' . esc_url( admin_url( 'admin.php?page=wstp-mail-settings' ) ) . '">' . esc_html__( 'Post Subscriptions → Mail Transport', 'we-formkit' ) . '</a>'
											),
											array(
												'a' => array(
													'href' => true,
												),
											)
										);
										?>
									<?php endif; ?>
									<?php if ( $wstp_global ) : ?>
										<br />
										<?php esc_html_e( 'Note: Subscribe to Posts already applies its transport to all WordPress emails — Formkit can stay on WordPress default in that case.', 'we-formkit' ); ?>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr class="wek-mail-smtp-only">
						<th><label for="wek_smtp_host"><?php esc_html_e( 'SMTP host', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" name="wek_settings[smtp_host]" id="wek_smtp_host" value="<?php echo esc_attr( (string) $settings['smtp_host'] ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr class="wek-mail-smtp-only">
						<th><label for="wek_smtp_port"><?php esc_html_e( 'SMTP port', 'we-formkit' ); ?></label></th>
						<td>
							<input class="small-text" type="number" min="1" max="65535" name="wek_settings[smtp_port]" id="wek_smtp_port" value="<?php echo esc_attr( (string) (int) $settings['smtp_port'] ); ?>" />
						</td>
					</tr>
					<tr class="wek-mail-smtp-only">
						<th><label for="wek_smtp_encryption"><?php esc_html_e( 'SMTP encryption', 'we-formkit' ); ?></label></th>
						<td>
							<select name="wek_settings[smtp_encryption]" id="wek_smtp_encryption">
								<option value="none" <?php selected( $smtp_enc, 'none' ); ?>><?php esc_html_e( 'None', 'we-formkit' ); ?></option>
								<option value="tls" <?php selected( $smtp_enc, 'tls' ); ?>>TLS</option>
								<option value="ssl" <?php selected( $smtp_enc, 'ssl' ); ?>>SSL</option>
							</select>
						</td>
					</tr>
					<tr class="wek-mail-smtp-only">
						<th><label for="wek_smtp_auth"><?php esc_html_e( 'SMTP authentication', 'we-formkit' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="wek_settings[smtp_auth]" id="wek_smtp_auth" value="1" <?php checked( ! empty( $settings['smtp_auth'] ) ); ?> />
								<?php esc_html_e( 'Use username and password', 'we-formkit' ); ?>
							</label>
						</td>
					</tr>
					<tr class="wek-mail-smtp-only">
						<th><label for="wek_smtp_username"><?php esc_html_e( 'SMTP username', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" name="wek_settings[smtp_username]" id="wek_smtp_username" value="<?php echo esc_attr( (string) $settings['smtp_username'] ); ?>" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" />
						</td>
					</tr>
					<tr class="wek-mail-smtp-only">
						<th><label for="wek_smtp_password"><?php esc_html_e( 'SMTP password / app password', 'we-formkit' ); ?></label></th>
						<td>
							<input
								class="regular-text"
								type="password"
								name="wek_settings[smtp_password]"
								id="wek_smtp_password"
								value=""
								placeholder="<?php echo $has_smtp_pw ? esc_attr( '••••••••••••' ) : ''; ?>"
								autocomplete="new-password"
								data-lpignore="true"
								data-1p-ignore="true"
							/>
						</td>
					</tr>
					<tr>
						<th><label for="wek_privacy_mode"><?php esc_html_e( 'Default privacy policy', 'we-formkit' ); ?></label></th>
						<td>
							<select name="wek_settings[privacy_policy_mode]" id="wek_privacy_mode">
								<?php if ( $wp_privacy['configured'] ) : ?>
									<option value="wp" <?php selected( $privacy_mode, 'wp' ); ?>>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: privacy page title */
												__( 'WordPress privacy page: %s', 'we-formkit' ),
												$wp_privacy['title']
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
							<?php if ( ! $wp_privacy['configured'] ) : ?>
								<p class="description">
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %s: link to WP privacy settings */
											__( 'No privacy policy page is set in WordPress. Configure one under %s, or choose a custom URL.', 'we-formkit' ),
											$privacy_link
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
									$page_ref = esc_html( $wp_privacy['title'] );
									if ( '' !== $wp_privacy['url'] ) {
										$page_ref = '<a href="' . esc_url( $wp_privacy['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $wp_privacy['title'] ) . '</a>';
									}
									echo wp_kses(
										sprintf(
											/* translators: 1: privacy page title (linked when URL exists), 2: link to WP privacy settings */
											__( 'Uses the page from WordPress Privacy settings (%1$s). Change it under %2$s.', 'we-formkit' ),
											$page_ref,
											$privacy_link
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
						<th colspan="2"><h2 class="title" style="margin:1.5rem 0 0;"><?php esc_html_e( 'Spam protection', 'we-formkit' ); ?></h2></th>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Honeypot', 'we-formkit' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wek_settings[spam_honeypot]" value="1" <?php checked( ! empty( $settings['spam_honeypot'] ) ); ?> />
								<?php esc_html_e( 'Reject submissions that fill a hidden decoy field', 'we-formkit' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'No captcha and no third-party scripts.', 'we-formkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Timing check', 'we-formkit' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wek_settings[spam_timing]" id="wek_spam_timing" value="1" <?php checked( ! empty( $settings['spam_timing'] ) ); ?> />
								<?php esc_html_e( 'Reject submissions that are sent too quickly after the page loads', 'we-formkit' ); ?>
							</label>
							<p class="description wek-reveal" id="wek-spam-timing-wrap" <?php echo ! empty( $settings['spam_timing'] ) ? '' : 'hidden'; ?>>
								<label for="wek_spam_timing_min"><?php esc_html_e( 'Minimum seconds', 'we-formkit' ); ?></label>
								<input class="small-text" type="number" min="1" max="60" name="wek_settings[spam_timing_min]" id="wek_spam_timing_min" value="<?php echo esc_attr( (string) (int) $settings['spam_timing_min'] ); ?>" />
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rate limit', 'we-formkit' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wek_settings[spam_rate_limit]" id="wek_spam_rate_limit" value="1" <?php checked( ! empty( $settings['spam_rate_limit'] ) ); ?> />
								<?php esc_html_e( 'Limit how many submissions one IP can send per hour', 'we-formkit' ); ?>
							</label>
							<p class="description wek-reveal" id="wek-spam-rate-wrap" <?php echo ! empty( $settings['spam_rate_limit'] ) ? '' : 'hidden'; ?>>
								<label for="wek_spam_rate_max"><?php esc_html_e( 'Max submissions per IP / hour', 'we-formkit' ); ?></label>
								<input class="small-text" type="number" min="1" max="100" name="wek_settings[spam_rate_max]" id="wek_spam_rate_max" value="<?php echo esc_attr( (string) (int) $settings['spam_rate_max'] ); ?>" />
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'IP hash', 'we-formkit' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wek_settings[spam_store_ip_hash]" value="1" <?php checked( ! empty( $settings['spam_store_ip_hash'] ) ); ?> />
								<?php esc_html_e( 'Store a salted IP hash on each entry (not the plain IP)', 'we-formkit' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Useful for abuse handling. Turn off if you prefer not to keep any IP-derived data.', 'we-formkit' ); ?></p>
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

			<form method="post" class="wek-mail-test" style="margin-top:1.5rem;">
				<?php wp_nonce_field( 'we_formkit_send_test_mail', 'we_formkit_test_mail_nonce' ); ?>
				<h2><?php esc_html_e( 'Test mail transport', 'we-formkit' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Saves nothing — uses the currently stored transport settings. Save transport changes first.', 'we-formkit' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wek_test_mail_recipient"><?php esc_html_e( 'Recipient', 'we-formkit' ); ?></label></th>
						<td>
							<input class="regular-text" type="email" name="wek_test_mail_recipient" id="wek_test_mail_recipient" value="<?php echo esc_attr( $admin_email ); ?>" required />
							<?php submit_button( __( 'Send test email', 'we-formkit' ), 'secondary', 'we_formkit_send_test_mail', false ); ?>
						</td>
					</tr>
				</table>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Privacy notes', 'we-formkit' ); ?></h2>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Form submissions are stored in this WordPress site only (no third-party form SaaS).', 'we-formkit' ); ?></li>
				<li><?php esc_html_e( 'Spam protection (honeypot, timing, rate limit) and IP hashing are optional — configure them under Spam protection above. No reCAPTCHA.', 'we-formkit' ); ?></li>
				<li><?php esc_html_e( 'Embed forms with the Formkit Form block. Optional secret-link tokens limit casual discovery.', 'we-formkit' ); ?></li>
			</ul>
		</div>
		<script>
		(function () {
			var mode = document.getElementById('wek_privacy_mode');
			var wrap = document.getElementById('wek-privacy-custom-wrap');
			if (mode && wrap) {
				function syncPrivacy() {
					wrap.hidden = mode.value !== 'custom';
				}
				mode.addEventListener('change', syncPrivacy);
				syncPrivacy();
			}

			function syncReveal(toggleId, wrapId) {
				var toggle = document.getElementById(toggleId);
				var reveal = document.getElementById(wrapId);
				if (!toggle || !reveal) return;
				function sync() {
					reveal.hidden = !toggle.checked;
				}
				toggle.addEventListener('change', sync);
				sync();
			}
			syncReveal('wek_spam_timing', 'wek-spam-timing-wrap');
			syncReveal('wek_spam_rate_limit', 'wek-spam-rate-wrap');

			var transport = document.getElementById('wek_mail_transport');
			var smtpRows = document.querySelectorAll('.wek-mail-smtp-only');
			var wstpHint = document.getElementById('wek-mail-wstp-hint');
			var host = document.getElementById('wek_smtp_host');
			var port = document.getElementById('wek_smtp_port');
			var encryption = document.getElementById('wek_smtp_encryption');
			var auth = document.getElementById('wek_smtp_auth');
			var previous = transport ? transport.value : 'wp_default';

			function applyGmailPreset() {
				if (host) host.value = 'smtp.gmail.com';
				if (port) port.value = '587';
				if (encryption) encryption.value = 'tls';
				if (auth) auth.checked = true;
			}

			function syncTransport() {
				if (!transport) return;
				var value = transport.value;
				var showSmtp = value === 'smtp' || value === 'gmail';
				smtpRows.forEach(function (row) {
					row.hidden = !showSmtp;
				});
				if (wstpHint) {
					wstpHint.hidden = value !== 'subscribe_to_posts';
				}
				if (value === 'gmail') {
					applyGmailPreset();
					if (host) host.readOnly = true;
					if (port) port.readOnly = true;
				} else {
					if (host) host.readOnly = false;
					if (port) port.readOnly = false;
				}
				previous = value;
			}

			if (transport) {
				transport.addEventListener('change', syncTransport);
				syncTransport();
			}
		})();
		</script>
		<?php
	}
}
