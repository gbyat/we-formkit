<?php
/**
 * Per-form email notification configs (Gravity-inspired).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read/write notification definitions for a form.
 */
final class Form_Notifications {

	public const META = '_wek_form_notifications';

	/**
	 * @param int $form_id Form ID.
	 * @return list<array<string, mixed>>
	 */
	public static function get( $form_id ) {
		$form_id = (int) $form_id;
		$raw     = get_post_meta( $form_id, self::META, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::hydrate_with_schema( self::normalize_list( $decoded ), Form_Schema::get( $form_id ) );
			}
		}
		if ( is_array( $raw ) ) {
			return self::hydrate_with_schema( self::normalize_list( $raw ), Form_Schema::get( $form_id ) );
		}

		return self::hydrate_with_schema( self::normalize_list( self::migrate_legacy( $form_id ) ), Form_Schema::get( $form_id ) );
	}

	/**
	 * @param int                        $form_id Form ID.
	 * @param list<array<string, mixed>> $notifications Notifications.
	 * @return void
	 */
	public static function save( $form_id, array $items ) {
		$form_id = (int) $form_id;
		$clean   = self::normalize_list( $items );
		update_post_meta( $form_id, self::META, wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

		// Keep legacy single-email meta in sync with first admin-style static recipient.
		$legacy = '';
		foreach ( $clean as $item ) {
			if ( ! empty( $item['enabled'] ) && 'email' === $item['to_mode'] && '' !== $item['to'] ) {
				$legacy = self::first_email( $item['to'] );
				break;
			}
		}
		update_post_meta( $form_id, Form_Schema::META_NOTIFY_EMAIL, $legacy );
	}

	/**
	 * Blank template for a new notification.
	 *
	 * @return array<string, mixed>
	 */
	public static function blank() {
		$base            = self::defaults()[0];
		$base['id']      = 'n_' . wp_generate_password( 8, false, false );
		$base['name']    = __( 'New notification', 'we-formkit' );
		$base['enabled'] = true;
		return $base;
	}

	/**
	 * @param list<array<string, mixed>> $notifications Notification list.
	 * @param string                     $id            Notification ID.
	 * @return int|false
	 */
	public static function find_index( array $notifications, $id ) {
		$id = sanitize_key( (string) $id );
		foreach ( $notifications as $index => $item ) {
			if ( isset( $item['id'] ) && (string) $item['id'] === $id ) {
				return (int) $index;
			}
		}
		return false;
	}

	/**
	 * @param list<array<string, mixed>> $notifications Notification list.
	 * @param string                     $id     Notification ID.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_id( array $notifications, $id ) {
		$index = self::find_index( $notifications, $id );
		if ( false === $index ) {
			return null;
		}
		return $notifications[ $index ];
	}

	/**
	 * Replace or append a notification by ID.
	 *
	 * @param list<array<string, mixed>> $notifications Notification list.
	 * @param array<string, mixed>       $one  Notification.
	 * @return list<array<string, mixed>>
	 */
	public static function upsert( array $notifications, array $one ) {
		$one   = self::normalize_one( $one );
		$index = self::find_index( $notifications, (string) $one['id'] );
		if ( false === $index ) {
			$notifications[] = $one;
			return $notifications;
		}
		$notifications[ $index ] = $one;
		return $notifications;
	}

	/**
	 * @param list<array<string, mixed>> $notifications Notification list.
	 * @param string                     $id     Notification ID.
	 * @return list<array<string, mixed>>
	 */
	public static function remove_by_id( array $notifications, $id ) {
		$index = self::find_index( $notifications, $id );
		if ( false === $index ) {
			return $notifications;
		}
		array_splice( $notifications, $index, 1 );
		return $notifications;
	}

	/**
	 * @param list<array<string, mixed>> $notifications Notification list.
	 * @param string                     $id     Notification ID.
	 * @return list<array<string, mixed>>
	 */
	public static function toggle_enabled( array $notifications, $id ) {
		$index = self::find_index( $notifications, $id );
		if ( false === $index ) {
			return $notifications;
		}
		$notifications[ $index ]['enabled'] = empty( $notifications[ $index ]['enabled'] );
		return $notifications;
	}

	/**
	 * @param list<array<string, mixed>> $notifications Notification list.
	 * @param string                     $id     Notification ID.
	 * @return array<string, mixed>|null
	 */
	public static function duplicate_by_id( array $notifications, $id ) {
		$item = self::find_by_id( $notifications, $id );
		if ( null === $item ) {
			return null;
		}
		$copy         = $item;
		$copy['id']   = 'n_' . wp_generate_password( 8, false, false );
		$copy['name'] = trim( (string) $copy['name'] ) . ' ' . __( '(copy)', 'we-formkit' );
		return self::normalize_one( $copy );
	}

	/**
	 * Fill empty field targets from the form schema (first email field).
	 *
	 * Does not override an explicitly chosen field ID.
	 *
	 * @param list<array<string, mixed>> $items  Notifications.
	 * @param array<string, mixed>       $schema Form schema.
	 * @return list<array<string, mixed>>
	 */
	public static function hydrate_with_schema( array $items, array $schema ) {
		$email_id = Form_Schema::first_email_field_id( $schema );
		$fields   = Form_Schema::fields_by_id( $schema );

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( 'field' === ( $item['to_mode'] ?? '' ) ) {
				$to_field = sanitize_key( (string) ( $item['to_field'] ?? '' ) );
				if ( '' !== $to_field && ! isset( $fields[ $to_field ] ) ) {
					$to_field = '';
				}
				if ( '' === $to_field && '' !== $email_id ) {
					$to_field = $email_id;
				}
				$items[ $index ]['to_field'] = $to_field;
			}

			if ( 'field' === ( $item['reply_to_mode'] ?? '' ) ) {
				$reply_field = sanitize_key( (string) ( $item['reply_to_field'] ?? '' ) );
				if ( '' !== $reply_field && ! isset( $fields[ $reply_field ] ) ) {
					$reply_field = '';
				}
				if ( '' === $reply_field && '' !== $email_id ) {
					$reply_field = $email_id;
				}
				$items[ $index ]['reply_to_field'] = $reply_field;
			}
		}

		return $items;
	}

	/**
	 * Default templates (admin + submitter confirmation).
	 *
	 * @param array<string, mixed>|null $schema Optional schema to pre-link email fields.
	 * @return list<array<string, mixed>>
	 */
	public static function defaults( $schema = null ) {
		$items = array(
			array(
				'id'             => 'admin',
				'name'           => __( 'Admin notification', 'we-formkit' ),
				'enabled'        => true,
				'to_mode'        => 'email',
				'to'             => '',
				'to_field'       => '',
				'from_name'      => '',
				'from_email'     => '',
				'reply_to_mode'  => 'field',
				'reply_to'       => '',
				'reply_to_field' => '',
				'cc'             => '',
				'bcc'            => '',
				'subject'        => sprintf(
					/* translators: %s: merge tag {form_title} (do not translate the tag). */
					__( '[Formkit] New submission: %s', 'we-formkit' ),
					'{form_title}'
				),
				'header'         => '',
				'message'        => sprintf(
					'<p>%1$s</p>' . "\n" . '<p>{all_fields}</p>' . "\n" . '<p>%2$s <a href="{submission_url}">{submission_url}</a></p>',
					__( 'A new form was submitted.', 'we-formkit' ),
					__( 'Open submission:', 'we-formkit' )
				),
				'include_fields' => 'all',
				'field_ids'      => array(),
				'footer'         => '',
				'attach_uploads' => true,
			),
			array(
				'id'             => 'user',
				'name'           => __( 'Submitter confirmation', 'we-formkit' ),
				'enabled'        => false,
				'to_mode'        => 'field',
				'to'             => '',
				'to_field'       => '',
				'from_name'      => '',
				'from_email'     => '',
				'reply_to_mode'  => 'email',
				'reply_to'       => '',
				'reply_to_field' => '',
				'cc'             => '',
				'bcc'            => '',
				'subject'        => sprintf(
					/* translators: %s: merge tag {form_title} (do not translate the tag). */
					__( 'We received your submission: %s', 'we-formkit' ),
					'{form_title}'
				),
				'header'         => '',
				'message'        => sprintf(
					'<p>%s</p>' . "\n" . '<p>{all_fields}</p>',
					__( 'Thank you. We have received your submission.', 'we-formkit' )
				),
				'include_fields' => 'all',
				'field_ids'      => array(),
				'footer'         => '',
				'attach_uploads' => false,
			),
		);

		if ( is_array( $schema ) ) {
			return self::hydrate_with_schema( $items, $schema );
		}

		return $items;
	}

	/**
	 * @param list<array<string, mixed>> $items Raw list.
	 * @return list<array<string, mixed>>
	 */
	public static function normalize_list( array $items ) {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[] = self::normalize_one( $item );
		}
		if ( empty( $out ) ) {
			return self::defaults();
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function normalize_one( array $input ) {
		$id = sanitize_key( (string) ( $input['id'] ?? '' ) );
		if ( '' === $id ) {
			$id = 'n_' . wp_generate_password( 8, false, false );
		}

		$to_mode = sanitize_key( (string) ( $input['to_mode'] ?? 'email' ) );
		if ( ! in_array( $to_mode, array( 'email', 'field' ), true ) ) {
			$to_mode = 'email';
		}

		$reply_mode = sanitize_key( (string) ( $input['reply_to_mode'] ?? 'none' ) );
		if ( ! in_array( $reply_mode, array( 'none', 'email', 'field' ), true ) ) {
			$reply_mode = 'none';
		}

		$include = sanitize_key( (string) ( $input['include_fields'] ?? 'all' ) );
		if ( ! in_array( $include, array( 'all', 'selected', 'none' ), true ) ) {
			$include = 'all';
		}

		$field_ids = array();
		if ( isset( $input['field_ids'] ) && is_array( $input['field_ids'] ) ) {
			foreach ( $input['field_ids'] as $fid ) {
				$fid = sanitize_key( (string) $fid );
				if ( '' !== $fid ) {
					$field_ids[] = $fid;
				}
			}
		}

		return array(
			'id'             => $id,
			'name'           => sanitize_text_field( (string) ( $input['name'] ?? __( 'Notification', 'we-formkit' ) ) ),
			'enabled'        => ! empty( $input['enabled'] ),
			'to_mode'        => $to_mode,
			'to'             => self::sanitize_email_list( (string) ( $input['to'] ?? '' ) ),
			'to_field'       => sanitize_key( (string) ( $input['to_field'] ?? '' ) ),
			'from_name'      => sanitize_text_field( (string) ( $input['from_name'] ?? '' ) ),
			'from_email'     => sanitize_email( (string) ( $input['from_email'] ?? '' ) ),
			'reply_to_mode'  => $reply_mode,
			'reply_to'       => sanitize_email( (string) ( $input['reply_to'] ?? '' ) ),
			'reply_to_field' => sanitize_key( (string) ( $input['reply_to_field'] ?? '' ) ),
			'cc'             => self::sanitize_email_list( (string) ( $input['cc'] ?? '' ) ),
			'bcc'            => self::sanitize_email_list( (string) ( $input['bcc'] ?? '' ) ),
			'subject'        => sanitize_text_field( (string) ( $input['subject'] ?? '' ) ),
			'header'         => self::sanitize_html_body( (string) ( $input['header'] ?? '' ) ),
			'message'        => self::sanitize_html_body( (string) ( $input['message'] ?? '' ) ),
			'include_fields' => $include,
			'field_ids'      => $field_ids,
			'footer'         => self::sanitize_html_body( (string) ( $input['footer'] ?? '' ) ),
			'attach_uploads' => ! empty( $input['attach_uploads'] ),
		);
	}

	/**
	 * Allow safe HTML for WYSIWYG notification bodies.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public static function sanitize_html_body( $html ) {
		$html = (string) $html;
		// Legacy bug: "\r\n" sometimes stored as literal "rnrn" after escaping.
		if ( false === strpos( $html, '<' ) && false !== strpos( $html, 'rnrn' ) ) {
			$html = str_replace( 'rnrn', "\n\n", $html );
		}
		return wp_kses_post( $html );
	}

	/**
	 * Content for the WYSIWYG editor (upgrade legacy plain text).
	 *
	 * @param string $html Stored body.
	 * @return string
	 */
	public static function editor_content( $html ) {
		$html = self::sanitize_html_body( $html );
		if ( '' === trim( $html ) ) {
			return '';
		}
		if ( false === strpos( $html, '<' ) ) {
			return wpautop( $html );
		}
		return $html;
	}

	/**
	 * Sanitize POST payload for notifications.
	 *
	 * @param array<string, mixed> $posted Posted `wek_notifications` array.
	 * @return list<array<string, mixed>>
	 */
	public static function from_request( array $posted ) {
		$list = array();
		foreach ( $posted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( empty( $row['enabled'] ) ) {
				$row['enabled'] = false;
			} else {
				$row['enabled'] = true;
			}
			if ( empty( $row['attach_uploads'] ) ) {
				$row['attach_uploads'] = false;
			} else {
				$row['attach_uploads'] = true;
			}
			if ( isset( $row['field_ids'] ) && ! is_array( $row['field_ids'] ) ) {
				$row['field_ids'] = array_filter( array_map( 'strval', (array) $row['field_ids'] ) );
			}
			$list[] = $row;
		}
		return self::normalize_list( $list );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return list<array<string, mixed>>
	 */
	private static function migrate_legacy( $form_id ) {
		$defaults = self::defaults();
		$legacy   = (string) get_post_meta( $form_id, Form_Schema::META_NOTIFY_EMAIL, true );
		if ( is_email( $legacy ) ) {
			$defaults[0]['to'] = $legacy;
		}
		return $defaults;
	}

	/**
	 * @param string $list Comma-separated emails.
	 * @return string
	 */
	public static function sanitize_email_list( $emails ) {
		$parts = preg_split( '/[,;]+/', (string) $emails );
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
		return implode( ', ', array_unique( $out ) );
	}

	/**
	 * @param string $emails Email list.
	 * @return string
	 */
	private static function first_email( $emails ) {
		$parts = preg_split( '/[,;]+/', (string) $emails );
		if ( ! is_array( $parts ) ) {
			$parts = array();
		}
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( $part ) );
			if ( is_email( $email ) ) {
				return $email;
			}
		}
		return '';
	}
}
