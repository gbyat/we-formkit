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
	 * @param int                  $submission_id Submission ID.
	 * @param int                  $form_id       Form ID.
	 * @param array<string, mixed> $schema        Schema.
	 * @param array<string, mixed> $data          Sanitized values.
	 * @param array<string, mixed>       $notification  Notification config.
	 * @param list<array<string, mixed>> $matched_docs  Resolved info documents.
	 * @return void
	 */
	private static function send_one( $submission_id, $form_id, array $schema, array $data, array $notification, array $matched_docs = array() ) {
		$to = self::resolve_recipients( $notification, $data, $schema );
		if ( empty( $to ) ) {
			return;
		}

		$vars    = self::merge_vars( $submission_id, $form_id, $schema, $data, $notification, $matched_docs );
		$subject = self::replace_tags( (string) $notification['subject'], $vars );
		$body    = self::replace_tags( (string) $notification['message'], $vars );

		if ( '' !== trim( (string) $notification['footer'] ) ) {
			$body .= "\n\n---\n" . self::replace_tags( (string) $notification['footer'], $vars );
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$from_email = (string) $notification['from_email'];
		if ( ! is_email( $from_email ) ) {
			$from_email = (string) get_option( 'admin_email' );
		}
		$from_name = (string) $notification['from_name'];
		if ( '' === $from_name ) {
			$from_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
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
			return;
		}

		wp_mail(
			(string) $mail['to'],
			(string) $mail['subject'],
			(string) $mail['body'],
			isset( $mail['headers'] ) && is_array( $mail['headers'] ) ? $mail['headers'] : array(),
			isset( $mail['attachments'] ) && is_array( $mail['attachments'] ) ? $mail['attachments'] : array()
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
			$settings = Settings::get();
			$fallback = isset( $settings['notify_email'] ) ? (string) $settings['notify_email'] : '';
			if ( ! is_email( $fallback ) ) {
				$fallback = (string) get_option( 'admin_email' );
			}
			$email_list = $fallback;
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
	 * @param int                  $submission_id Submission ID.
	 * @param int                  $form_id       Form ID.
	 * @param array<string, mixed> $schema        Schema.
	 * @param array<string, mixed> $data          Values.
	 * @param array<string, mixed>       $notification  Notification.
	 * @param list<array<string, mixed>> $matched_docs  Resolved info documents.
	 * @return array<string, string>
	 */
	private static function merge_vars( $submission_id, $form_id, array $schema, array $data, array $notification, array $matched_docs = array() ) {
		$form_title = get_the_title( $form_id );
		$edit_link  = admin_url( 'admin.php?page=we-formkit-submissions&action=edit&submission_id=' . (int) $submission_id );

		$vars = array(
			'form_title'     => is_string( $form_title ) ? $form_title : '',
			'submission_url' => $edit_link,
			'submission_id'  => (string) (int) $submission_id,
			'form_id'        => (string) (int) $form_id,
			'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'admin_email'    => (string) get_option( 'admin_email' ),
			'date'           => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'all_fields'     => self::format_fields_block( $schema, $data, $notification ),
			'footer'         => (string) $notification['footer'],
			'info_links'     => Form_Info_Documents::links_as_text( $matched_docs ),
		);

		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			$vars[ 'field:' . $field_id ] = self::format_field_value( $field, $data[ $field_id ] ?? null );
		}

		return $vars;
	}

	/**
	 * @param array<string, mixed> $schema       Schema.
	 * @param array<string, mixed> $data         Values.
	 * @param array<string, mixed> $notification Notification.
	 * @return string
	 */
	private static function format_fields_block( array $schema, array $data, array $notification ) {
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

		$lines = array();
		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( in_array( $type, array( 'html', 'hidden' ), true ) ) {
				continue;
			}
			if ( 'selected' === $mode && empty( $selected[ $field_id ] ) ) {
				continue;
			}
			$label   = isset( $field['label'] ) ? (string) $field['label'] : $field_id;
			$value   = self::format_field_value( $field, $data[ $field_id ] ?? null );
			$lines[] = $label . ': ' . $value;
		}

		return implode( "\n", $lines );
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
		if ( $type_obj ) {
			$display = $type_obj->format_for_display( $value, $field );
			if ( is_string( $display ) ) {
				return $display;
			}
		}

		if ( null === $value || '' === $value ) {
			return '—';
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		if ( is_array( $value ) ) {
			// Uploads.
			if ( isset( $value[0] ) && is_array( $value[0] ) && ( isset( $value[0]['url'] ) || isset( $value[0]['name'] ) ) ) {
				$names = array();
				foreach ( $value as $file ) {
					if ( ! is_array( $file ) ) {
						continue;
					}
					$names[] = isset( $file['name'] ) ? (string) $file['name'] : ( isset( $file['url'] ) ? (string) $file['url'] : '' );
				}
				return implode( ', ', array_filter( $names ) );
			}
			$flat = array();
			array_walk_recursive(
				$value,
				static function ( $item ) use ( &$flat ) {
					if ( is_scalar( $item ) ) {
						$flat[] = (string) $item;
					}
				}
			);
			return implode( ', ', $flat );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $data   Values.
	 * @return list<string>
	 */
	private static function collect_attachment_paths( array $schema, array $data ) {
		$paths = array();
		foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( 'upload' !== $type && 'signature' !== $type ) {
				continue;
			}
			$raw = isset( $data[ $field_id ] ) ? $data[ $field_id ] : null;
			if ( 'signature' === $type && is_array( $raw ) && isset( $raw['token'] ) ) {
				$files = array( $raw );
			} else {
				$files = is_array( $raw ) ? $raw : array();
			}
			foreach ( $files as $file ) {
				if ( ! is_array( $file ) ) {
					continue;
				}
				$path = '';
				if ( ! empty( $file['path'] ) && is_string( $file['path'] ) ) {
					$path = $file['path'];
				} elseif ( ! empty( $file['token'] ) && ! empty( $file['name'] ) ) {
					$subdir = 'signature' === $type ? Private_Files::SIGNATURES_SUBDIR : Private_Files::UPLOADS_SUBDIR;
					$path   = Private_Files::absolute_path( $subdir, (string) $file['token'], (string) $file['name'] );
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
	 * @param string                $text Text with {tags}.
	 * @param array<string, string> $vars Vars.
	 * @return string
	 */
	private static function replace_tags( $text, array $vars ) {
		return (string) preg_replace_callback(
			'/\{([a-z0-9_:\-]+)\}/i',
			static function ( $m ) use ( $vars ) {
				$key = $m[1];
				return array_key_exists( $key, $vars ) ? $vars[ $key ] : $m[0];
			},
			(string) $text
		);
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
