<?php
/**
 * Email notifications for new submissions.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends admin notifications.
 */
final class Notifications {

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'we_formkit_submission_created', array( __CLASS__, 'send' ), 10, 2 );
	}

	/**
	 * @param int                  $submission_id Submission ID.
	 * @param array<string, mixed> $context       Context with form_id and data.
	 * @return void
	 */
	public static function send( $submission_id, array $context ) {
		$form_id = isset( $context['form_id'] ) ? (int) $context['form_id'] : 0;
		if ( $form_id <= 0 ) {
			return;
		}

		$email = (string) get_post_meta( $form_id, Form_Schema::META_NOTIFY_EMAIL, true );
		if ( '' === $email || ! is_email( $email ) ) {
			$settings = Settings::get();
			$email    = isset( $settings['notify_email'] ) ? (string) $settings['notify_email'] : '';
		}
		if ( '' === $email || ! is_email( $email ) ) {
			$email = (string) get_option( 'admin_email' );
		}
		if ( ! is_email( $email ) ) {
			return;
		}

		$form_title = get_the_title( $form_id );
		$edit_link  = admin_url( 'admin.php?page=we-formkit-submissions&action=edit&submission_id=' . (int) $submission_id );

		$subject = sprintf(
			/* translators: %s: form title */
			__( '[Formkit] New submission: %s', 'we-formkit' ),
			$form_title
		);

		$body  = __( 'A new form was submitted.', 'we-formkit' ) . "\n\n";
		$body .= sprintf(
			/* translators: %s: form title */
			__( 'Form: %s', 'we-formkit' ),
			$form_title
		) . "\n";
		$body .= sprintf(
			/* translators: %s: admin URL */
			__( 'Open submission: %s', 'we-formkit' ),
			$edit_link
		) . "\n";

		wp_mail( $email, $subject, $body );
	}
}
