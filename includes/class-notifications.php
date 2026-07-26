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
 * Sends configured form notifications (admin + auto-reply, etc.).
 */
final class Notifications {

	/**
	 * Inline images (CID => absolute file path) for the email currently sending.
	 *
	 * @var array<string, string>
	 */
	private static $inline_images = array();

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'we_formkit_submission_created', array( __CLASS__, 'send' ), 10, 2 );
	}

	/**
	 * Embed collected inline images into the outgoing PHPMailer message.
	 *
	 * Registered on phpmailer_init only while a notification with inline images
	 * is being sent, so private signature files render inline (cid:) in clients
	 * that cannot fetch the gated download URL.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public static function attach_inline_images( $phpmailer ) {
		foreach ( self::$inline_images as $cid => $path ) {
			if ( is_string( $path ) && '' !== $path && is_readable( $path ) ) {
				$phpmailer->addEmbeddedImage( $path, $cid, basename( $path ), 'base64', 'image/png' );
			}
		}
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

		// Quarantined spam: store the entry but do not notify.
		if ( ! empty( $context['is_spam'] ) ) {
			return;
		}
		if ( (int) get_post_meta( (int) $submission_id, Form_Schema::SUB_SPAM, true ) === 1 ) {
			return;
		}

		$data         = isset( $context['data'] ) && is_array( $context['data'] ) ? $context['data'] : array();
		$schema       = Form_Schema::get( $form_id );
		$list         = Form_Notifications::get( $form_id );
		$matched_docs = Form_Info_Documents::resolve_matching( $form_id, $data );

		foreach ( $list as $notification ) {
			if ( empty( $notification['enabled'] ) ) {
				continue;
			}
			self::send_one( (int) $submission_id, $form_id, $schema, $data, $notification, $matched_docs );
		}
	}

	/**
	 * Resend one or all notifications for an existing submission (admin).
	 *
	 * Disabled notifications can still be sent when a specific ID is requested
	 * (useful for layout checks). Pass null / empty to resend all enabled ones.
	 *
	 * @param int         $submission_id   Submission ID.
	 * @param string|null $notification_id Notification ID or null for all enabled.
	 * @param string      $to_override     Optional email override (e.g. current admin).
	 * @return array{sent:int,failed:int,messages:list<string>}
	 */
	public static function resend( $submission_id, $notification_id = null, $to_override = '' ) {
		$submission_id = (int) $submission_id;
		$post          = get_post( $submission_id );
		if ( ! $post || Post_Types::SUBMISSION !== $post->post_type ) {
			return array(
				'sent'     => 0,
				'failed'   => 1,
				'messages' => array( __( 'Submission not found.', 'we-formkit' ) ),
			);
		}

		$form_id = (int) get_post_meta( $submission_id, Form_Schema::SUB_FORM_ID, true );
		if ( $form_id <= 0 ) {
			return array(
				'sent'     => 0,
				'failed'   => 1,
				'messages' => array( __( 'Form not found for this submission.', 'we-formkit' ) ),
			);
		}

		$raw  = (string) get_post_meta( $submission_id, Form_Schema::SUB_DATA, true );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$schema       = Form_Schema::get( $form_id );
		$list         = Form_Notifications::get( $form_id );
		$matched_docs = Form_Info_Documents::resolve_matching( $form_id, $data );
		$want_id      = null !== $notification_id && '' !== $notification_id ? sanitize_key( (string) $notification_id ) : '';
		$override     = is_email( $to_override ) ? sanitize_email( $to_override ) : '';

		$sent     = 0;
		$failed   = 0;
		$messages = array();

		foreach ( $list as $notification ) {
			$id = (string) ( $notification['id'] ?? '' );
			if ( '' !== $want_id ) {
				if ( $id !== $want_id ) {
					continue;
				}
			} elseif ( empty( $notification['enabled'] ) ) {
				continue;
			}

			$result = self::send_one( $submission_id, $form_id, $schema, $data, $notification, $matched_docs, $override );
			$name   = (string) ( $notification['name'] ?? $id );
			if ( true === $result ) {
				++$sent;
				/* translators: %s: notification name */
				$messages[] = sprintf( __( 'Sent: %s', 'we-formkit' ), $name );
			} else {
				++$failed;
				$reason = is_string( $result ) && '' !== $result ? $result : __( 'Could not send.', 'we-formkit' );
				/* translators: 1: notification name, 2: error */
				$messages[] = sprintf( __( 'Failed (%1$s): %2$s', 'we-formkit' ), $name, $reason );
			}
		}

		if ( '' !== $want_id && 0 === $sent && 0 === $failed ) {
			$failed     = 1;
			$messages[] = __( 'Notification not found.', 'we-formkit' );
		}

		return array(
			'sent'     => $sent,
			'failed'   => $failed,
			'messages' => $messages,
		);
	}

	/**
	 * @param int                        $submission_id Submission ID.
	 * @param int                        $form_id       Form ID.
	 * @param array<string, mixed>       $schema        Schema.
	 * @param array<string, mixed>       $data          Sanitized values.
	 * @param array<string, mixed>       $notification  Notification config.
	 * @param list<array<string, mixed>> $matched_docs  Resolved info documents.
	 * @param string                     $to_override   Optional recipient override.
	 * @return true|string True on success, error message on failure.
	 */
	private static function send_one( $submission_id, $form_id, array $schema, array $data, array $notification, array $matched_docs = array(), $to_override = '' ) {
		if ( is_email( $to_override ) ) {
			$to = array( sanitize_email( $to_override ) );
		} else {
			$to = self::resolve_recipients( $notification, $data, $schema );
		}
		if ( empty( $to ) ) {
			$err = __( 'No valid recipient email.', 'we-formkit' );
			self::append_notify_log( $submission_id, $notification, '', false, $err );
			return $err;
		}

		self::$inline_images = array();

		$vars         = self::merge_vars( $submission_id, $form_id, $schema, $data, $notification, $matched_docs );
		$subject_vars = Merge_Tags::plain_vars( $vars );
		$subject      = Merge_Tags::replace( (string) $notification['subject'], $subject_vars );
		$body         = self::compose_html_body( $notification, $vars );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$from_email = (string) $notification['from_email'];
		if ( ! is_email( $from_email ) ) {
			$from_email = Settings::default_from_email();
		}
		$from_name = (string) $notification['from_name'];
		if ( '' === $from_name ) {
			$from_name = Settings::default_from_name();
		}
		if ( is_email( $from_email ) ) {
			$headers[] = 'From: ' . self::format_address( $from_name, $from_email );
		}

		$reply = self::resolve_reply_to( $notification, $data, $schema );
		if ( is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		if ( '' !== $notification['cc'] ) {
			$headers[] = 'Cc: ' . $notification['cc'];
		}
		if ( '' !== $notification['bcc'] ) {
			$headers[] = 'Bcc: ' . $notification['bcc'];
		}

		$attachments = array();
		if ( ! empty( $notification['attach_uploads'] ) ) {
			$attachments = self::collect_attachment_paths( $schema, $data );
		}

		$info_paths = Form_Info_Documents::attachment_paths_for_notification( $matched_docs, (string) $notification['id'] );
		if ( ! empty( $info_paths ) ) {
			$attachments = array_values( array_unique( array_merge( $attachments, $info_paths ) ) );
		}

		/**
		 * Filter outbound Formkit notification before wp_mail.
		 *
		 * @param array{to:string,subject:string,body:string,headers:list<string>,attachments:list<string>} $mail Mail args.
		 * @param array<string, mixed>                                                                      $notification Notification.
		 * @param int                                                                                       $submission_id Submission ID.
		 * @param int                                                                                       $form_id Form ID.
		 */
		$mail = apply_filters(
			'we_formkit_notification_mail',
			array(
				'to'          => implode( ', ', $to ),
				'subject'     => $subject,
				'body'        => $body,
				'headers'     => $headers,
				'attachments' => $attachments,
			),
			$notification,
			$submission_id,
			$form_id
		);

		if ( empty( $mail['to'] ) ) {
			return __( 'No valid recipient email.', 'we-formkit' );
		}

		$has_inline = ! empty( self::$inline_images );
		if ( $has_inline ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'attach_inline_images' ) );
		}

		$ok = Mailer::wp_mail(
			(string) $mail['to'],
			(string) $mail['subject'],
			(string) $mail['body'],
			isset( $mail['headers'] ) && is_array( $mail['headers'] ) ? $mail['headers'] : array(),
			isset( $mail['attachments'] ) && is_array( $mail['attachments'] ) ? $mail['attachments'] : array()
		);

		if ( $has_inline ) {
			remove_action( 'phpmailer_init', array( __CLASS__, 'attach_inline_images' ) );
			self::$inline_images = array();
		}

		self::append_notify_log(
			$submission_id,
			$notification,
			isset( $mail['to'] ) ? (string) $mail['to'] : implode( ', ', $to ),
			(bool) $ok,
			$ok ? '' : __( 'wp_mail failed.', 'we-formkit' )
		);

		return $ok ? true : __( 'wp_mail failed.', 'we-formkit' );
	}

	/**
	 * Append a notification delivery record to the submission.
	 *
	 * @param int                  $submission_id Submission ID.
	 * @param array<string, mixed> $notification  Notification config.
	 * @param string               $to            Recipient list (comma-separated).
	 * @param bool                 $ok            Whether mail was accepted.
	 * @param string               $error         Optional error message.
	 * @return void
	 */
	private static function append_notify_log( $submission_id, array $notification, $to, $ok, $error = '' ) {
		$submission_id = (int) $submission_id;
		if ( $submission_id < 1 ) {
			return;
		}

		$raw = (string) get_post_meta( $submission_id, Form_Schema::SUB_NOTIFY_LOG, true );
		$log = json_decode( $raw, true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'id'    => sanitize_key( (string) ( $notification['id'] ?? '' ) ),
			'name'  => sanitize_text_field( (string) ( $notification['name'] ?? '' ) ),
			'to'    => sanitize_text_field( (string) $to ),
			'ok'    => (bool) $ok,
			'error' => sanitize_text_field( (string) $error ),
			'at'    => current_time( 'mysql' ),
		);

		if ( count( $log ) > 40 ) {
			$log = array_slice( $log, -40 );
		}

		update_post_meta(
			$submission_id,
			Form_Schema::SUB_NOTIFY_LOG,
			wp_json_encode( array_values( $log ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	/**
	 * @param array<string, mixed> $notification Notification.
	 * @param array<string, mixed> $data         Values.
	 * @param array<string, mixed> $schema       Schema.
	 * @return list<string>
	 */
	private static function resolve_recipients( array $notification, array $data, array $schema ) {
		if ( 'field' === $notification['to_mode'] ) {
			$field = (string) $notification['to_field'];
			if ( '' === $field ) {
				$field = Form_Schema::first_email_field_id( $schema );
			}
			$email = self::value_as_email( $data, $field );
			return is_email( $email ) ? array( $email ) : array();
		}

		$email_list = (string) $notification['to'];
		if ( '' === $email_list ) {
			$email_list = Settings::default_notify_email();
		}

		$parts = preg_split( '/[,;]+/', $email_list );
		if ( ! is_array( $parts ) ) {
			$parts = array();
		}
		$out = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( $part ) );
			if ( is_email( $email ) ) {
				$out[] = $email;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<string, mixed> $notification Notification.
	 * @param array<string, mixed> $data         Values.
	 * @param array<string, mixed> $schema       Schema.
	 * @return string
	 */
	private static function resolve_reply_to( array $notification, array $data, array $schema ) {
		$mode = (string) $notification['reply_to_mode'];
		if ( 'field' === $mode ) {
			$field = (string) $notification['reply_to_field'];
			if ( '' === $field ) {
				$field = Form_Schema::first_email_field_id( $schema );
			}
			if ( '' !== $field ) {
				return self::value_as_email( $data, $field );
			}
			foreach ( $data as $value ) {
				if ( is_string( $value ) && is_email( $value ) ) {
					return sanitize_email( $value );
				}
			}
			return '';
		}
		if ( 'email' === $mode ) {
			$email = sanitize_email( (string) $notification['reply_to'] );
			return is_email( $email ) ? $email : '';
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $data     Values.
	 * @param string               $field_id Field id.
	 * @return string
	 */
	private static function value_as_email( array $data, $field_id ) {
		if ( '' === $field_id || ! isset( $data[ $field_id ] ) ) {
			return '';
		}
		$value = $data[ $field_id ];
		if ( is_string( $value ) ) {
			$email = sanitize_email( $value );
			return is_email( $email ) ? $email : '';
		}
		return '';
	}

	/**
	 * Public wrapper for confirmation / external merge contexts.
	 *
	 * @param int                        $submission_id Submission ID.
	 * @param int                        $form_id       Form ID.
	 * @param array<string, mixed>       $schema        Schema.
	 * @param array<string, mixed>       $data          Values.
	 * @param array<string, mixed>       $notification  Notification-like include_fields config.
	 * @param list<array<string, mixed>> $matched_docs  Docs.
	 * @return array<string, string>
	 */
	public static function merge_vars_public( $submission_id, $form_id, array $schema, array $data, array $notification, array $matched_docs = array() ) {
		return self::merge_vars( $submission_id, $form_id, $schema, $data, $notification, $matched_docs );
	}

	/**
	 * @param int                        $submission_id Submission ID.
	 * @param int                        $form_id       Form ID.
	 * @param array<string, mixed>       $schema        Schema.
	 * @param array<string, mixed>       $data          Values.
	 * @param array<string, mixed>       $notification  Notification.
	 * @param list<array<string, mixed>> $matched_docs  Resolved info documents.
	 * @return array<string, string>
	 */
	private static function merge_vars( $submission_id, $form_id, array $schema, array $data, array $notification, array $matched_docs = array() ) {
		$vars = Merge_Tags::meta_vars( $submission_id, $form_id );

		$vars['all_fields'] = self::format_fields_block_html( $schema, $data, $notification );
		$vars['footer']     = (string) ( $notification['footer'] ?? '' );
		$vars['info_links'] = Form_Info_Documents::links_as_html( $matched_docs );

		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			$vars[ 'field:' . $field_id ] = nl2br(
				wp_kses(
					self::format_field_value_email( $field, $data[ $field_id ] ?? null ),
					self::email_value_allowed_html(),
					self::email_allowed_protocols()
				),
				false
			);
		}

		/**
		 * Filter merge variables before tag replacement.
		 *
		 * @param array<string, string> $vars          Vars.
		 * @param int                   $submission_id Submission ID.
		 * @param int                   $form_id       Form ID.
		 * @param array<string, mixed>  $notification  Notification.
		 */
		$filtered = apply_filters( 'we_formkit_merge_vars', $vars, (int) $submission_id, (int) $form_id, $notification );

		return is_array( $filtered ) ? $filtered : $vars;
	}

	/**
	 * Allowed URL protocols for email field values (adds cid: for inline images).
	 *
	 * @return list<string>
	 */
	private static function email_allowed_protocols() {
		return array_values( array_unique( array_merge( wp_allowed_protocols(), array( 'cid' ) ) ) );
	}

	/**
	 * Field value for emails; signatures render as an inline (cid) image so they
	 * display even when the gated download URL is unreachable by mail clients.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @param mixed                $value Value.
	 * @return string
	 */
	private static function format_field_value_email( array $field, $value ) {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		if ( 'signature' === $type ) {
			$img = self::signature_inline_img( $value );
			if ( '' !== $img ) {
				return \Webentwicklerin\WeFormkit\Fields\Abstract_Field_Type::apply_format_filter( $img, $value, $field, 'email' );
			}
		}
		return self::format_field_value( $field, $value );
	}

	/**
	 * Build an inline (cid) <img> for a stored signature and register the file
	 * for embedding. Returns '' when the signature file is missing.
	 *
	 * @param mixed $raw Stored signature value.
	 * @return string
	 */
	private static function signature_inline_img( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['token'] ) || empty( $raw['name'] ) ) {
			return '';
		}
		$path = '';
		if ( ! empty( $raw['path'] ) && is_string( $raw['path'] ) ) {
			$path = $raw['path'];
		} else {
			$path = Private_Files::absolute_path(
				Private_Files::SIGNATURES_SUBDIR,
				(string) $raw['token'],
				(string) $raw['name']
			);
		}
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}

		$cid                         = 'wek-sig-' . md5( $path . (string) wp_rand() );
		self::$inline_images[ $cid ] = $path;

		return sprintf(
			'<img src="cid:%s" alt="%s" style="max-width:280px;height:auto;border:1px solid #ddd;" />',
			esc_attr( $cid ),
			esc_attr__( 'Signature', 'we-formkit' )
		);
	}

	/**
	 * Allowed HTML for field values in emails.
	 *
	 * Values come from field `format_for_display()`, which already escapes
	 * attributes/text. wp_kses here is a safety net that keeps only the links
	 * and inline markup we intentionally emit (upload links, signature image).
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function email_value_allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
				'title'  => true,
			),
			'img'    => array(
				'src'    => true,
				'alt'    => true,
				'class'  => true,
				'style'  => true,
				'width'  => true,
				'height' => true,
			),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'span'   => array( 'style' => true ),
		);
	}

	/**
	 * Build a full HTML email from header / message / footer.
	 *
	 * @param array<string, mixed>  $notification Notification.
	 * @param array<string, string> $vars         Merge vars.
	 * @return string
	 */
	private static function compose_html_body( array $notification, array $vars ) {
		$header  = self::prepare_rich_part( (string) ( $notification['header'] ?? '' ), $vars );
		$message = self::prepare_rich_part( (string) ( $notification['message'] ?? '' ), $vars );
		$footer  = self::prepare_rich_part( (string) ( $notification['footer'] ?? '' ), $vars );
		if ( '' === trim( wp_strip_all_tags( $footer ) ) ) {
			$footer = Settings::email_footer_html();
		}

		$parts = array();
		if ( '' !== trim( wp_strip_all_tags( $header ) ) ) {
			$parts[] = '<div style="margin:0 0 1.25rem;padding-bottom:1rem;border-bottom:1px solid #e5e5e5;">' . $header . '</div>';
		}
		$parts[] = '<div>' . $message . '</div>';
		if ( '' !== trim( wp_strip_all_tags( $footer ) ) ) {
			$parts[] = '<div style="margin:1.5rem 0 0;padding-top:1rem;border-top:1px solid #e5e5e5;color:#666;font-size:13px;">' . $footer . '</div>';
		}

		$inner = implode( "\n", $parts );
		$site  = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

		return '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head>'
			. '<body style="margin:0;padding:0;background:#f4f4f5;font-family:Segoe UI,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;color:#1f1d1c;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;">'
			. '<tr><td align="center">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #e4e4e7;border-radius:8px;">'
			. '<tr><td style="padding:24px 28px;">' . $inner . '</td></tr>'
			. '<tr><td style="padding:12px 28px 20px;font-size:12px;color:#8a8580;">' . $site . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * Convert legacy plain text to HTML, then apply merge tags.
	 *
	 * @param string                $raw  Stored fragment.
	 * @param array<string, string> $vars Merge vars.
	 * @return string
	 */
	private static function prepare_rich_part( $raw, array $vars ) {
		$raw = (string) $raw;
		if ( '' === trim( $raw ) ) {
			return '';
		}
		$raw = Settings::prepare_email_html( $raw );
		return Merge_Tags::replace( $raw, $vars );
	}

	/**
	 * @param array<string, mixed> $schema       Schema.
	 * @param array<string, mixed> $data         Values.
	 * @param array<string, mixed> $notification Notification.
	 * @return string
	 */
	private static function format_fields_block_html( array $schema, array $data, array $notification ) {
		$mode = (string) $notification['include_fields'];
		if ( 'none' === $mode ) {
			return '';
		}

		$selected = array();
		if ( 'selected' === $mode && ! empty( $notification['field_ids'] ) && is_array( $notification['field_ids'] ) ) {
			foreach ( $notification['field_ids'] as $fid ) {
				$selected[ (string) $fid ] = true;
			}
		}

		$rows = array();
		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( in_array( $type, array( 'html', 'hidden' ), true ) ) {
				continue;
			}
			if ( 'selected' === $mode && empty( $selected[ $field_id ] ) ) {
				continue;
			}
			$label  = isset( $field['label'] ) ? (string) $field['label'] : $field_id;
			$value  = self::format_field_value_email( $field, $data[ $field_id ] ?? null );
			$value  = nl2br( wp_kses( $value, self::email_value_allowed_html(), self::email_allowed_protocols() ), false );
			$rows[] = '<tr>'
				. '<td style="padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top;font-weight:600;width:35%;">' . esc_html( $label ) . '</td>'
				. '<td style="padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top;">' . $value . '</td>'
				. '</tr>';
		}

		if ( empty( $rows ) ) {
			return '';
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0.75rem 0;border:1px solid #e5e5e5;">'
			. implode( '', $rows )
			. '</table>';
	}

	/**
	 * @param array<string, mixed> $schema       Schema.
	 * @param array<string, mixed> $data         Values.
	 * @param array<string, mixed> $notification Notification.
	 * @return string
	 */
	private static function format_fields_block( array $schema, array $data, array $notification ) {
		$html = self::format_fields_block_html( $schema, $data, $notification );
		return wp_strip_all_tags( str_replace( array( '</tr>', '<br />', '<br/>', '<br>' ), array( "\n", "\n", "\n", "\n" ), $html ) );
	}

	/**
	 * @param array<string, mixed> $field Field config.
	 * @param mixed                $value Value.
	 * @return string
	 */
	private static function format_field_value( array $field, $value ) {
		$type     = isset( $field['type'] ) ? (string) $field['type'] : '';
		$registry = Plugin::instance()->field_registry();
		$type_obj = $registry ? $registry->get( $type ) : null;
		$display  = '';
		if ( $type_obj ) {
			$formatted = $type_obj->format_for_display( $value, $field );
			if ( is_string( $formatted ) ) {
				$display = $formatted;
			}
		}

		if ( '' === $display ) {
			if ( null === $value || '' === $value ) {
				$display = '—';
			} elseif ( is_bool( $value ) ) {
				$display = $value ? '1' : '0';
			} elseif ( is_scalar( $value ) ) {
				$display = (string) $value;
			} elseif ( is_array( $value ) ) {
				// Uploads.
				if ( isset( $value[0] ) && is_array( $value[0] ) && ( isset( $value[0]['url'] ) || isset( $value[0]['name'] ) ) ) {
					$names = array();
					foreach ( $value as $file ) {
						if ( ! is_array( $file ) ) {
							continue;
						}
						$names[] = isset( $file['name'] ) ? (string) $file['name'] : ( isset( $file['url'] ) ? (string) $file['url'] : '' );
					}
					$display = implode( ', ', array_filter( $names ) );
				} else {
					$flat = array();
					array_walk_recursive(
						$value,
						static function ( $item ) use ( &$flat ) {
							if ( is_scalar( $item ) ) {
								$flat[] = (string) $item;
							}
						}
					);
					$display = implode( ', ', $flat );
				}
			}
		}

		return \Webentwicklerin\WeFormkit\Fields\Abstract_Field_Type::apply_format_filter( $display, $value, $field, 'email' );
	}

	/**
	 * Collect filesystem paths for upload fields only.
	 *
	 * Signatures are embedded inline (cid:) in the HTML body — attaching the
	 * same PNG again makes many clients (e.g. Outlook) show the file twice.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $data   Values.
	 * @return list<string>
	 */
	private static function collect_attachment_paths( array $schema, array $data ) {
		$paths = array();
		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( 'upload' !== $type ) {
				continue;
			}
			$raw   = isset( $data[ $field_id ] ) ? $data[ $field_id ] : null;
			$files = is_array( $raw ) ? $raw : array();
			foreach ( $files as $file ) {
				if ( ! is_array( $file ) ) {
					continue;
				}
				$path = '';
				if ( ! empty( $file['path'] ) && is_string( $file['path'] ) ) {
					$path = $file['path'];
				} elseif ( ! empty( $file['token'] ) && ! empty( $file['name'] ) ) {
					$path = Private_Files::absolute_path(
						Private_Files::UPLOADS_SUBDIR,
						(string) $file['token'],
						(string) $file['name']
					);
				} elseif ( ! empty( $file['attachment_id'] ) ) {
					$attached = get_attached_file( (int) $file['attachment_id'] );
					if ( is_string( $attached ) ) {
						$path = $attached;
					}
				}
				if ( '' !== $path && is_readable( $path ) ) {
					$paths[] = $path;
				}
			}
		}
		return array_values( array_unique( $paths ) );
	}

	/**
	 * @param string $name  Display name.
	 * @param string $email Email.
	 * @return string
	 */
	private static function format_address( $name, $email ) {
		$name = trim( str_replace( array( "\r", "\n" ), '', $name ) );
		if ( '' === $name ) {
			return $email;
		}
		return sprintf( '%s <%s>', $name, $email );
	}
}
