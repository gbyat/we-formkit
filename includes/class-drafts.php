<?php
/**
 * Save & Resume drafts.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists in-progress form payloads keyed by resume token.
 */
final class Drafts {

	public const META_ENABLED    = '_wek_form_save_resume';
	public const META_TTL        = '_wek_form_save_resume_ttl';
	public const META_MIN_FILLED = '_wek_form_save_resume_min_filled';
	public const META_REMINDERS  = '_wek_form_save_resume_reminders';
	public const OPTION_KEY      = 'wek_form_drafts';
	public const CRON_HOOK       = 'we_formkit_drafts_cleanup';
	public const TTL_DAYS        = 14;
	public const MIN_FILLED      = 1;
	/** Default seconds between resume emails (same email + form + IP). */
	public const MAIL_COOLDOWN = 300;
	/** Soft cap for drafts stored in the options table. */
	public const MAX_STORE = 200;

	/**
	 * Days before expiry to send an opt-in reminder (default when user does not pick).
	 *
	 * @param int $ttl_days Draft lifetime in days.
	 * @return int
	 */
	public static function reminder_lead_days( $ttl_days ) {
		$ttl_days = max( 1, (int) $ttl_days );
		$allowed  = self::allowed_reminder_lead_days( $ttl_days );
		if ( $ttl_days <= 7 ) {
			$prefer = 2;
		} elseif ( $ttl_days <= 14 ) {
			$prefer = 3;
		} else {
			$prefer = 7;
		}
		$lead = in_array( $prefer, $allowed, true ) ? $prefer : (int) $allowed[0];

		/**
		 * Filter default days before draft expiry for the reminder.
		 *
		 * @param int $lead     Days before expiry.
		 * @param int $ttl_days Draft TTL in days.
		 */
		$lead = (int) apply_filters( 'we_formkit_draft_reminder_lead_days', $lead, $ttl_days );

		return in_array( $lead, $allowed, true ) ? $lead : (int) $allowed[0];
	}

	/**
	 * Lead-day choices for the frontend dropdown (days before expiry).
	 *
	 * @param int $ttl_days Draft lifetime in days.
	 * @return list<int>
	 */
	public static function allowed_reminder_lead_days( $ttl_days ) {
		$ttl_days   = max( 1, (int) $ttl_days );
		$max        = max( 1, $ttl_days - 1 );
		$candidates = array( 1, 2, 3, 7, 14, 30 );
		$out        = array();
		foreach ( $candidates as $day ) {
			if ( $day <= $max ) {
				$out[] = $day;
			}
		}
		if ( empty( $out ) ) {
			$out[] = 1;
		}

		/**
		 * Filter reminder lead options (days before expiry).
		 *
		 * @param list<int> $days     Allowed lead days.
		 * @param int       $ttl_days Draft TTL in days.
		 */
		$filtered = apply_filters( 'we_formkit_draft_reminder_lead_options', $out, $ttl_days );
		$clean    = array();
		foreach ( (array) $filtered as $day ) {
			$day = (int) $day;
			if ( $day >= 1 && $day <= $max ) {
				$clean[] = $day;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		sort( $clean, SORT_NUMERIC );

		return ! empty( $clean ) ? $clean : array( 1 );
	}

	/**
	 * Sanitize a user-picked lead; fall back to default.
	 *
	 * @param int $lead     Requested days before expiry.
	 * @param int $ttl_days TTL days.
	 * @return int
	 */
	public static function sanitize_reminder_lead( $lead, $ttl_days ) {
		$allowed = self::allowed_reminder_lead_days( $ttl_days );
		$lead    = (int) $lead;
		if ( in_array( $lead, $allowed, true ) ) {
			return $lead;
		}
		return self::reminder_lead_days( $ttl_days );
	}

	/**
	 * Unix timestamp when a reminder should fire (or 0 if not applicable).
	 *
	 * @param int      $expires  Expiry unix time.
	 * @param int      $updated  Save unix time.
	 * @param int      $ttl_days TTL days.
	 * @param int|null $lead     Days before expiry (null = default).
	 * @return int
	 */
	public static function compute_remind_at( $expires, $updated, $ttl_days, $lead = null ) {
		$expires   = (int) $expires;
		$updated   = (int) $updated;
		$lead      = null === $lead
			? self::reminder_lead_days( $ttl_days )
			: self::sanitize_reminder_lead( $lead, $ttl_days );
		$remind_at = $expires - ( $lead * DAY_IN_SECONDS );
		// Not immediately after save; leave at least ~1 day gap when possible.
		$earliest = $updated + DAY_IN_SECONDS;
		if ( $remind_at < $earliest ) {
			$remind_at = $earliest;
		}
		if ( $remind_at >= $expires ) {
			return 0;
		}
		return $remind_at;
	}

	/**
	 * Allowed draft lifetimes in days.
	 *
	 * @return list<int>
	 */
	public static function allowed_ttl_days() {
		$days = array( 7, 14, 30, 60, 90 );

		/**
		 * Filter allowed Save & Resume draft lifetimes (days) for the builder UI and validation.
		 *
		 * @param list<int> $days Day counts (clamped to 1–365 after filter).
		 */
		$filtered = apply_filters( 'we_formkit_draft_ttl_days', $days );
		$out      = array();
		foreach ( (array) $filtered as $day ) {
			$day = (int) $day;
			if ( $day >= 1 && $day <= 365 ) {
				$out[] = $day;
			}
		}
		$out = array_values( array_unique( $out ) );
		sort( $out, SORT_NUMERIC );

		return ! empty( $out ) ? $out : array( self::TTL_DAYS );
	}

	/**
	 * Seconds between resume emails for the same email + form + IP.
	 *
	 * @return int
	 */
	public static function mail_cooldown_seconds() {
		/**
		 * Filter resume-mail cooldown in seconds.
		 *
		 * @param int $seconds Cooldown length (clamped 30–3600).
		 */
		$seconds = (int) apply_filters( 'we_formkit_draft_mail_cooldown', self::MAIL_COOLDOWN );
		return max( 30, min( HOUR_IN_SECONDS, $seconds ) );
	}

	/**
	 * Maximum drafts kept in the option store.
	 *
	 * @return int
	 */
	public static function max_store() {
		/**
		 * Filter maximum number of Save & Resume drafts in storage.
		 *
		 * @param int $max Soft cap (clamped 20–5000).
		 */
		$max = (int) apply_filters( 'we_formkit_draft_max_store', self::MAX_STORE );
		return max( 20, min( 5000, $max ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public static function get_ttl_days( $form_id ) {
		$form_id  = (int) $form_id;
		$allowed  = self::allowed_ttl_days();
		$fallback = in_array( self::TTL_DAYS, $allowed, true ) ? self::TTL_DAYS : (int) $allowed[0];
		$raw      = (int) get_post_meta( $form_id, self::META_TTL, true );
		$days     = in_array( $raw, $allowed, true ) ? $raw : $fallback;

		/**
		 * Filter the effective draft TTL in days for a form (after stored meta / defaults).
		 *
		 * @param int $days    Lifetime in days.
		 * @param int $form_id Form ID.
		 */
		$days = (int) apply_filters( 'we_formkit_form_draft_ttl_days', $days, $form_id );

		return max( 1, min( 365, $days ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @param int $days    Days.
	 * @return void
	 */
	public static function set_ttl_days( $form_id, $days ) {
		$days = (int) $days;
		if ( ! in_array( $days, self::allowed_ttl_days(), true ) ) {
			$days = self::TTL_DAYS;
		}
		update_post_meta( (int) $form_id, self::META_TTL, $days );
	}

	/**
	 * Minimum filled fields before Save & Resume UI unlocks (0 = always).
	 *
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public static function get_min_filled( $form_id ) {
		$form_id = (int) $form_id;
		$raw     = get_post_meta( $form_id, self::META_MIN_FILLED, true );
		if ( '' === $raw || false === $raw ) {
			$min = self::MIN_FILLED;
		} else {
			$min = (int) $raw;
		}

		/**
		 * Filter minimum filled fields required before Save & Resume unlocks.
		 *
		 * @param int $min     Minimum count (0 = always show).
		 * @param int $form_id Form ID.
		 */
		$min = (int) apply_filters( 'we_formkit_form_save_min_filled', $min, $form_id );

		return max( 0, min( 100, $min ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @param int $min     Minimum filled fields (0–100).
	 * @return void
	 */
	public static function set_min_filled( $form_id, $min ) {
		update_post_meta( (int) $form_id, self::META_MIN_FILLED, max( 0, min( 100, (int) $min ) ) );
	}

	/**
	 * Count meaningfully filled values in a draft payload.
	 *
	 * @param array<string, mixed> $values Field id => value.
	 * @return int
	 */
	public static function count_filled_values( array $values ) {
		$count = 0;
		foreach ( $values as $val ) {
			if ( self::value_is_filled( $val ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param mixed $val Field value.
	 * @return bool
	 */
	private static function value_is_filled( $val ) {
		if ( is_array( $val ) ) {
			if ( empty( $val ) ) {
				return false;
			}
			foreach ( $val as $item ) {
				if ( is_array( $item ) ) {
					foreach ( $item as $sub ) {
						if ( self::value_is_filled( $sub ) ) {
							return true;
						}
					}
					continue;
				}
				if ( is_string( $item ) && '' !== trim( $item ) ) {
					return true;
				}
				if ( ! is_string( $item ) && null !== $item && false !== $item && '' !== $item ) {
					return true;
				}
			}
			return false;
		}
		if ( is_string( $val ) ) {
			return '' !== trim( $val );
		}
		if ( is_bool( $val ) ) {
			return $val;
		}
		if ( is_numeric( $val ) ) {
			return true;
		}
		return null !== $val && false !== $val;
	}

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_tick' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function is_enabled( $form_id ) {
		return (bool) get_post_meta( (int) $form_id, self::META_ENABLED, true );
	}

	/**
	 * @param int  $form_id Form ID.
	 * @param bool $enabled Enabled.
	 * @return void
	 */
	public static function set_enabled( $form_id, $enabled ) {
		update_post_meta( (int) $form_id, self::META_ENABLED, $enabled ? 1 : 0 );
	}

	/**
	 * Whether visitors may opt in to a calendar (.ics) reminder with the resume email.
	 * No further reminder emails are sent (GDPR-friendly).
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function reminders_allowed( $form_id ) {
		$form_id = (int) $form_id;
		if ( ! self::is_enabled( $form_id ) ) {
			return false;
		}
		$allowed = (bool) get_post_meta( $form_id, self::META_REMINDERS, true );

		/**
		 * Filter whether Save & Resume expiry reminders are allowed for a form.
		 *
		 * @param bool $allowed Whether reminders are allowed.
		 * @param int  $form_id Form ID.
		 */
		return (bool) apply_filters( 'we_formkit_form_reminders_allowed', $allowed, $form_id );
	}

	/**
	 * @param int  $form_id Form ID.
	 * @param bool $allowed Allowed.
	 * @return void
	 */
	public static function set_reminders_allowed( $form_id, $allowed ) {
		update_post_meta( (int) $form_id, self::META_REMINDERS, $allowed ? 1 : 0 );
	}

	/**
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			Rest_Api::NAMESPACE,
			'/drafts',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_save' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Rest_Api::NAMESPACE,
			'/drafts/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_save( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$nonce = isset( $params['nonce'] ) ? (string) $params['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'we_formkit_forbidden', __( 'Invalid security token.', 'we-formkit' ), array( 'status' => 403 ) );
		}

		$form_id = isset( $params['form_id'] ) ? absint( $params['form_id'] ) : 0;
		if ( $form_id <= 0 || ! self::is_enabled( $form_id ) ) {
			return new \WP_Error( 'we_formkit_drafts_disabled', __( 'Save & Resume is not enabled for this form.', 'we-formkit' ), array( 'status' => 400 ) );
		}

		$email = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return new \WP_Error(
				'we_formkit_draft_email',
				__( 'Enter a valid email address to receive your resume link.', 'we-formkit' ),
				array( 'status' => 400 )
			);
		}

		$values     = isset( $params['values'] ) && is_array( $params['values'] ) ? $params['values'] : array();
		$min_filled = self::get_min_filled( $form_id );
		if ( $min_filled > 0 && self::count_filled_values( $values ) < $min_filled ) {
			return new \WP_Error(
				'we_formkit_draft_too_early',
				__( 'Fill in a few fields before saving your progress.', 'we-formkit' ),
				array( 'status' => 400 )
			);
		}

		$all = self::all();
		self::prune_expired( $all );

		$token = isset( $params['token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $params['token'] ) : '';
		if ( '' !== $token && ( empty( $all[ $token ] ) || ! is_array( $all[ $token ] ) || (int) ( $all[ $token ]['form_id'] ?? 0 ) !== $form_id ) ) {
			$token = '';
		}
		if ( '' === $token ) {
			$token = self::find_token_for_email( $all, $form_id, $email );
		}
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false, false );
		}

		$ttl_days = self::get_ttl_days( $form_id );
		$expires  = time() + ( $ttl_days * DAY_IN_SECONDS );
		$updated  = time();
		$remind   = ! empty( $params['remind'] ) && self::reminders_allowed( $form_id );
		$lead     = isset( $params['remind_lead'] ) ? (int) $params['remind_lead'] : self::reminder_lead_days( $ttl_days );
		$lead     = self::sanitize_reminder_lead( $lead, $ttl_days );

		$page_url = isset( $params['page_url'] ) ? esc_url_raw( (string) $params['page_url'] ) : home_url( '/' );
		if ( '' === $page_url ) {
			$page_url = home_url( '/' );
		}

		$payload = array(
			'form_id'     => $form_id,
			'email'       => $email,
			'values'      => $values,
			'page_index'  => isset( $params['page_index'] ) ? max( 0, (int) $params['page_index'] ) : 0,
			'page_url'    => $page_url,
			'updated'     => $updated,
			'expires'     => $expires,
			'remind'      => $remind,
			'remind_lead' => $remind ? $lead : 0,
			'remind_at'   => $remind ? self::compute_remind_at( $expires, $updated, $ttl_days, $lead ) : 0,
		);

		$all[ $token ] = $payload;
		self::prune_siblings( $all, $form_id, $email, $token );
		self::cap_store( $all, $token );
		update_option( self::OPTION_KEY, $all, false );

		$rate_key     = self::mail_rate_key( $email, $form_id );
		$mail_blocked = (bool) get_transient( $rate_key );
		$email_sent   = false;
		$email_skip   = false;

		if ( $mail_blocked ) {
			$email_skip = true;
		} else {
			$resume_url = add_query_arg(
				array(
					'wek_resume' => $token,
				),
				$page_url
			);
			$email_sent = self::send_resume_email(
				$form_id,
				$email,
				$resume_url,
				$expires,
				$ttl_days,
				$remind ? $lead : 0
			);
			if ( $email_sent ) {
				set_transient( $rate_key, 1, self::mail_cooldown_seconds() );
			}
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'token'         => $token,
				'email'         => $email,
				'email_sent'    => $email_sent,
				'email_skipped' => $email_skip,
				'expires'       => $expires,
				'ttl_days'      => $ttl_days,
				'remind'        => $remind,
				'remind_lead'   => $remind ? $lead : 0,
				// Intentionally omit resume_url — the link is only delivered by email.
			)
		);
	}

	/**
	 * @param int    $form_id     Form ID.
	 * @param string $email       Recipient.
	 * @param string $resume_url  Resume URL.
	 * @param int    $expires     Unix expiry.
	 * @param int    $ttl_days    Lifetime in days.
	 * @param int    $remind_lead Days before expiry for optional calendar invite (0 = none).
	 * @return bool
	 */
	private static function send_resume_email( $form_id, $email, $resume_url, $expires, $ttl_days, $remind_lead = 0 ) {
		$title = get_the_title( $form_id );
		if ( ! is_string( $title ) || '' === $title ) {
			$title = __( 'your form', 'we-formkit' );
		}

		$date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires );
		if ( ! is_string( $date ) || '' === $date ) {
			$date = (string) $expires;
		}

		$subject = sprintf(
			/* translators: %s: form title. */
			__( 'Resume your progress: %s', 'we-formkit' ),
			$title
		);

		$safe_title  = esc_html( $title );
		$safe_url    = esc_url( $resume_url );
		$safe_date   = esc_html( $date );
		$safe_days   = (int) $ttl_days;
		$remind_lead = max( 0, (int) $remind_lead );

		$body  = '<html><body style="font-family:Arial,Helvetica,sans-serif;line-height:1.5;color:#1c1b19;">';
		$body .= '<p>' . esc_html__( 'Hello,', 'we-formkit' ) . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: %s: form title. */
			esc_html__( 'You saved your progress on “%s”. Use the link below to continue where you left off.', 'we-formkit' ),
			$safe_title
		) . '</p>';
		$body .= '<p><a href="' . $safe_url . '" style="display:inline-block;padding:0.7rem 1.1rem;background:#0f766e;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">'
			. esc_html__( 'Continue form', 'we-formkit' )
			. '</a></p>';
		$body .= '<p style="word-break:break-all;font-size:0.9em;color:#5c574f;">' . $safe_url . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: 1: expiry datetime, 2: number of days. */
			esc_html__( 'This link expires on %1$s (about %2$d days).', 'we-formkit' ),
			$safe_date,
			$safe_days
		) . '</p>';
		if ( $remind_lead > 0 ) {
			/* translators: %d: number of days before expiry. */
			$cal_note = _n(
				'We attached a calendar file so you can set a reminder %d day before this link expires. No further emails will be sent.',
				'We attached a calendar file so you can set a reminder %d days before this link expires. No further emails will be sent.',
				$remind_lead,
				'we-formkit'
			);
			$body    .= '<p>' . esc_html( sprintf( $cal_note, $remind_lead ) ) . '</p>';
		}
		$body .= '<p>' . esc_html__( 'If you did not request this, you can ignore this email.', 'we-formkit' ) . '</p>';
		$body .= Settings::email_footer_block();
		$body .= '</body></html>';

		$headers    = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_email = Settings::default_from_email();
		$from_name  = Settings::default_from_name();
		if ( is_email( $from_email ) ) {
			$headers[] = 'From: ' . ( '' !== $from_name ? sprintf( '%s <%s>', $from_name, $from_email ) : $from_email );
		}

		$attachments = array();
		$ics_path    = '';
		if ( $remind_lead > 0 ) {
			$event_at = self::compute_remind_at( $expires, time(), $ttl_days, $remind_lead );
			if ( $event_at > 0 ) {
				$ics_path = self::write_reminder_ics( $form_id, $title, $resume_url, $event_at, $expires );
				if ( is_string( $ics_path ) && '' !== $ics_path && is_readable( $ics_path ) ) {
					$attachments[] = $ics_path;
				}
			}
		}

		/**
		 * Filter Save & Resume email before wp_mail.
		 *
		 * @param array{to:string,subject:string,body:string,headers:list<string>,attachments:list<string>} $mail Mail args.
		 * @param int                                                                                      $form_id Form ID.
		 * @param string                                                                                   $resume_url Resume URL.
		 * @param int                                                                                      $expires Unix expiry.
		 */
		$mail = apply_filters(
			'we_formkit_resume_mail',
			array(
				'to'          => $email,
				'subject'     => $subject,
				'body'        => $body,
				'headers'     => $headers,
				'attachments' => $attachments,
			),
			(int) $form_id,
			$resume_url,
			(int) $expires
		);

		$sent = false;
		if ( ! empty( $mail['to'] ) && is_email( (string) $mail['to'] ) ) {
			$atts = isset( $mail['attachments'] ) && is_array( $mail['attachments'] ) ? $mail['attachments'] : $attachments;
			$sent = Mailer::wp_mail(
				(string) $mail['to'],
				(string) ( $mail['subject'] ?? $subject ),
				(string) ( $mail['body'] ?? $body ),
				isset( $mail['headers'] ) && is_array( $mail['headers'] ) ? $mail['headers'] : $headers,
				$atts
			);
		}

		if ( '' !== $ics_path && is_string( $ics_path ) && file_exists( $ics_path ) ) {
			wp_delete_file( $ics_path );
		}

		return $sent;
	}

	/**
	 * Build a temporary .ics file for an opt-in calendar reminder (no further emails).
	 *
	 * @param int    $form_id    Form ID.
	 * @param string $title      Form title.
	 * @param string $resume_url Resume URL.
	 * @param int    $event_at   Event start unix time.
	 * @param int    $expires    Link expiry unix time.
	 * @return string Absolute path or empty string.
	 */
	private static function write_reminder_ics( $form_id, $title, $resume_url, $event_at, $expires ) {
		$event_at = (int) $event_at;
		$expires  = (int) $expires;
		if ( $event_at <= 0 ) {
			return '';
		}

		$summary = sprintf(
			/* translators: %s: form title. */
			__( 'Finish form: %s', 'we-formkit' ),
			$title
		);
		$desc = sprintf(
			/* translators: %s: form title. */
			__( 'Continue “%s” before your saved progress link expires.', 'we-formkit' ),
			$title
		) . "\n" . $resume_url;

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			$host = 'localhost';
		}
		$uid     = sprintf( 'wek-resume-%d-%s@%s', (int) $form_id, wp_generate_password( 12, false, false ), $host );
		$stamp   = gmdate( 'Ymd\THis\Z' );
		$start   = gmdate( 'Ymd\THis\Z', $event_at );
		$end     = gmdate( 'Ymd\THis\Z', $event_at + ( 30 * MINUTE_IN_SECONDS ) );
		$exp_txt = gmdate( 'Y-m-d H:i', $expires ) . ' UTC';

		// No METHOD and no ORGANIZER/ATTENDEE: this is a personal appointment for
		// the recipient only, so Outlook/clients let them save it instead of
		// prompting to accept/decline a meeting invitation.
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//WE Formkit//EN',
			'CALSCALE:GREGORIAN',
			'BEGIN:VEVENT',
			'UID:' . self::ics_escape_text( (string) $uid ),
			'DTSTAMP:' . $stamp,
			'DTSTART:' . $start,
			'DTEND:' . $end,
			'SUMMARY:' . self::ics_escape_text( $summary ),
			'DESCRIPTION:' . self::ics_escape_text(
				$desc . "\n" . sprintf(
				/* translators: %s: expiry datetime (UTC). */
					__( 'Link expires: %s', 'we-formkit' ),
					$exp_txt
				)
			),
			'URL:' . self::ics_escape_text( $resume_url ),
			'TRANSP:TRANSPARENT',
			'SEQUENCE:0',
			'CLASS:PRIVATE',
			'X-MICROSOFT-CDO-BUSYSTATUS:FREE',
			'END:VEVENT',
			'END:VCALENDAR',
		);

		$content = implode( "\r\n", $lines ) . "\r\n";
		$ics     = trailingslashit( get_temp_dir() ) . 'formkit-reminder-' . wp_generate_password( 8, false, false ) . '.ics';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temp attachment for wp_mail.
		if ( false === file_put_contents( $ics, $content ) ) {
			return '';
		}

		return $ics;
	}

	/**
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function ics_escape_text( $text ) {
		$text = str_replace( array( '\\', ';', ',', "\r\n", "\n", "\r" ), array( '\\\\', '\\;', '\\,', '\\n', '\\n', '\\n' ), (string) $text );
		return $text;
	}

	/**
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return sanitize_text_field( $ip );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_get( $request ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $request['token'] );
		$all   = self::all();
		if ( empty( $all[ $token ] ) || ! is_array( $all[ $token ] ) ) {
			return new \WP_Error( 'we_formkit_draft_missing', __( 'Draft not found or expired.', 'we-formkit' ), array( 'status' => 404 ) );
		}
		$draft = $all[ $token ];
		if ( empty( $draft['expires'] ) || (int) $draft['expires'] < time() ) {
			unset( $all[ $token ] );
			update_option( self::OPTION_KEY, $all, false );
			return new \WP_Error( 'we_formkit_draft_missing', __( 'Draft not found or expired.', 'we-formkit' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'form_id'    => (int) ( $draft['form_id'] ?? 0 ),
				'values'     => isset( $draft['values'] ) && is_array( $draft['values'] ) ? $draft['values'] : array(),
				'page_index' => isset( $draft['page_index'] ) ? (int) $draft['page_index'] : 0,
				'expires'    => (int) ( $draft['expires'] ?? 0 ),
			)
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function all() {
		$raw = get_option( self::OPTION_KEY, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param string $email   Email.
	 * @param int    $form_id Form ID.
	 * @return string
	 */
	private static function mail_rate_key( $email, $form_id ) {
		return 'wek_draft_mail_' . md5( strtolower( (string) $email ) . '|' . (string) (int) $form_id . '|' . self::client_ip() );
	}

	/**
	 * @param array<string, array<string, mixed>> $all     Drafts by token.
	 * @param int                                 $form_id Form ID.
	 * @param string                              $email   Email.
	 * @return string Token or empty.
	 */
	private static function find_token_for_email( array $all, $form_id, $email ) {
		$form_id = (int) $form_id;
		$needle  = strtolower( (string) $email );
		$best    = '';
		$best_ts = 0;
		foreach ( $all as $token => $draft ) {
			if ( ! is_array( $draft ) ) {
				continue;
			}
			if ( (int) ( $draft['form_id'] ?? 0 ) !== $form_id ) {
				continue;
			}
			if ( strtolower( (string) ( $draft['email'] ?? '' ) ) !== $needle ) {
				continue;
			}
			$ts = (int) ( $draft['updated'] ?? 0 );
			if ( $ts >= $best_ts ) {
				$best_ts = $ts;
				$best    = (string) $token;
			}
		}
		return $best;
	}

	/**
	 * @param array<string, array<string, mixed>> $all Drafts by token (by ref).
	 * @return void
	 */
	private static function prune_expired( array &$all ) {
		$now = time();
		foreach ( $all as $token => $draft ) {
			if ( ! is_array( $draft ) || empty( $draft['expires'] ) || (int) $draft['expires'] < $now ) {
				unset( $all[ $token ] );
			}
		}
	}

	/**
	 * Keep a single active draft per form + email.
	 *
	 * @param array<string, array<string, mixed>> $all        Drafts by token (by ref).
	 * @param int                                 $form_id    Form ID.
	 * @param string                              $email      Email.
	 * @param string                              $keep_token Token to keep.
	 * @return void
	 */
	private static function prune_siblings( array &$all, $form_id, $email, $keep_token ) {
		$form_id = (int) $form_id;
		$needle  = strtolower( (string) $email );
		foreach ( $all as $token => $draft ) {
			if ( (string) $token === (string) $keep_token || ! is_array( $draft ) ) {
				continue;
			}
			if ( (int) ( $draft['form_id'] ?? 0 ) !== $form_id ) {
				continue;
			}
			if ( strtolower( (string) ( $draft['email'] ?? '' ) ) !== $needle ) {
				continue;
			}
			unset( $all[ $token ] );
		}
	}

	/**
	 * Drop oldest drafts when over the soft cap.
	 *
	 * @param array<string, array<string, mixed>> $all        Drafts by token (by ref).
	 * @param string                              $keep_token Token that must survive.
	 * @return void
	 */
	private static function cap_store( array &$all, $keep_token = '' ) {
		$max = self::max_store();
		if ( count( $all ) <= $max ) {
			return;
		}

		$ranked = array();
		foreach ( $all as $token => $draft ) {
			$ranked[ (string) $token ] = is_array( $draft ) ? (int) ( $draft['updated'] ?? 0 ) : 0;
		}
		asort( $ranked, SORT_NUMERIC );

		foreach ( array_keys( $ranked ) as $token ) {
			if ( count( $all ) <= $max ) {
				break;
			}
			if ( '' !== $keep_token && (string) $token === (string) $keep_token ) {
				continue;
			}
			unset( $all[ $token ] );
		}
	}

	/**
	 * Collapse duplicate form+email drafts to the newest token.
	 *
	 * @param array<string, array<string, mixed>> $all Drafts by token (by ref).
	 * @return void
	 */
	private static function prune_duplicate_emails( array &$all ) {
		/** @var array<string, array{token:string,updated:int}> $newest */
		$newest = array();
		foreach ( $all as $token => $draft ) {
			if ( ! is_array( $draft ) ) {
				continue;
			}
			$form_id = (int) ( $draft['form_id'] ?? 0 );
			$email   = strtolower( (string) ( $draft['email'] ?? '' ) );
			if ( $form_id <= 0 || '' === $email ) {
				continue;
			}
			$key = $form_id . '|' . $email;
			$ts  = (int) ( $draft['updated'] ?? 0 );
			if ( empty( $newest[ $key ] ) || $ts >= (int) $newest[ $key ]['updated'] ) {
				$newest[ $key ] = array(
					'token'   => (string) $token,
					'updated' => $ts,
				);
			}
		}
		foreach ( $newest as $key => $row ) {
			$parts = explode( '|', $key, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			self::prune_siblings( $all, (int) $parts[0], $parts[1], $row['token'] );
		}
	}

	/**
	 * Daily cron: remove expired / duplicate / excess drafts.
	 *
	 * @return void
	 */
	public static function cron_tick() {
		self::cleanup();
	}

	/**
	 * @return void
	 */
	public static function cleanup() {
		$all    = self::all();
		$before = $all;
		self::prune_expired( $all );
		self::prune_duplicate_emails( $all );
		self::cap_store( $all );
		if ( $all !== $before ) {
			update_option( self::OPTION_KEY, $all, false );
		}
	}
}
