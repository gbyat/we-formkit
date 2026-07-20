<?php
/**
 * Outbound mail transport (optional SMTP for Formkit only).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configures PHPMailer for Formkit notification / test mails.
 */
final class Mailer {

	/**
	 * Whether the current wp_mail() call belongs to Formkit.
	 *
	 * @var bool
	 */
	private static $in_context = false;

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ) );
	}

	/**
	 * Whether WE Subscribe to Posts is loaded.
	 *
	 * @return bool
	 */
	public static function subscribe_to_posts_active() {
		return defined( 'WSTP_VERSION' );
	}

	/**
	 * Normalized mail settings from WE Subscribe to Posts (if present).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function subscribe_to_posts_mail_settings() {
		if ( ! self::subscribe_to_posts_active() ) {
			return null;
		}

		$defaults = array(
			'transport'       => 'wp_default',
			'apply_globally'  => 'no',
			'from_email'      => '',
			'from_name'       => '',
			'smtp_host'       => '',
			'smtp_port'       => 587,
			'smtp_encryption' => 'tls',
			'smtp_auth'       => 'yes',
			'smtp_username'   => '',
			'smtp_password'   => '',
		);

		$stored = get_option( 'wstp_mail_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $defaults );
	}

	/**
	 * Whether Subscribe to Posts has a usable SMTP / Gmail transport configured.
	 *
	 * @return bool
	 */
	public static function subscribe_to_posts_smtp_ready() {
		$mail = self::subscribe_to_posts_mail_settings();
		if ( null === $mail ) {
			return false;
		}

		$transport = (string) $mail['transport'];
		if ( 'smtp' !== $transport && 'gmail' !== $transport ) {
			return false;
		}

		$config = self::resolve_smtp_config_from_wstp( $mail );
		return null !== $config;
	}

	/**
	 * Run wp_mail while marking Formkit context (so SMTP applies only here).
	 *
	 * @param string|array<int, string> $to          Recipients.
	 * @param string                    $subject     Subject.
	 * @param string                    $message     Body.
	 * @param string|array<int, string> $headers     Headers.
	 * @param array<int, string>        $attachments Attachments.
	 * @return bool
	 */
	public static function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		self::$in_context = true;
		try {
			return wp_mail( $to, $subject, $message, $headers, $attachments );
		} finally {
			self::$in_context = false;
		}
	}

	/**
	 * Send a transport test email to the given address.
	 *
	 * @param string $email Recipient.
	 * @return bool
	 */
	public static function send_test_email( $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}

		$subject = __( '[Test] Formkit mail transport', 'we-formkit' );
		$body    = '<html><body style="font-family:Arial,sans-serif;line-height:1.5;"><p>'
			. esc_html__( 'This is a test email from WE Formkit mail transport settings.', 'we-formkit' )
			. '</p></body></html>';

		return self::wp_mail(
			$email,
			$subject,
			$body,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * Apply SMTP when Formkit is sending and transport is not WordPress default.
	 *
	 * @param mixed $phpmailer PHPMailer instance.
	 * @return void
	 */
	public static function configure_phpmailer( $phpmailer ) {
		if ( ! self::$in_context || ! is_object( $phpmailer ) ) {
			return;
		}

		$config = self::resolve_active_smtp_config();
		if ( null === $config ) {
			return;
		}

		self::apply_smtp_to_phpmailer( $phpmailer, $config );
	}

	/**
	 * SMTP config for the currently selected Formkit transport, or null to leave PHPMailer alone.
	 *
	 * @return array{host:string,port:int,encryption:string,auth:bool,username:string,password:string}|null
	 */
	private static function resolve_active_smtp_config() {
		$settings  = Settings::get();
		$transport = isset( $settings['mail_transport'] ) ? (string) $settings['mail_transport'] : 'wp_default';

		if ( 'wp_default' === $transport ) {
			return null;
		}

		if ( 'subscribe_to_posts' === $transport ) {
			$wstp = self::subscribe_to_posts_mail_settings();
			return null === $wstp ? null : self::resolve_smtp_config_from_wstp( $wstp );
		}

		$host       = isset( $settings['smtp_host'] ) ? (string) $settings['smtp_host'] : '';
		$port       = isset( $settings['smtp_port'] ) ? (int) $settings['smtp_port'] : 587;
		$encryption = isset( $settings['smtp_encryption'] ) ? (string) $settings['smtp_encryption'] : 'tls';
		$auth       = ! empty( $settings['smtp_auth'] );
		$username   = isset( $settings['smtp_username'] ) ? (string) $settings['smtp_username'] : '';
		$password   = isset( $settings['smtp_password'] ) ? (string) $settings['smtp_password'] : '';

		if ( 'gmail' === $transport ) {
			$host       = 'smtp.gmail.com';
			$port       = 587;
			$encryption = 'tls';
			$auth       = true;
		}

		if ( '' === $host || $port < 1 ) {
			return null;
		}

		return array(
			'host'       => $host,
			'port'       => $port,
			'encryption' => $encryption,
			'auth'       => $auth,
			'username'   => $username,
			'password'   => $password,
		);
	}

	/**
	 * @param array<string, mixed> $mail WSTP mail settings.
	 * @return array{host:string,port:int,encryption:string,auth:bool,username:string,password:string}|null
	 */
	private static function resolve_smtp_config_from_wstp( array $mail ) {
		$transport = (string) $mail['transport'];
		if ( 'smtp' !== $transport && 'gmail' !== $transport ) {
			return null;
		}

		$host       = (string) $mail['smtp_host'];
		$port       = (int) $mail['smtp_port'];
		$encryption = (string) $mail['smtp_encryption'];
		$auth       = 'yes' === (string) $mail['smtp_auth'];
		$username   = (string) $mail['smtp_username'];
		$password   = (string) $mail['smtp_password'];

		if ( 'gmail' === $transport ) {
			$host       = 'smtp.gmail.com';
			$port       = 587;
			$encryption = 'tls';
			$auth       = true;
		}

		if ( '' === $host || $port < 1 ) {
			return null;
		}

		return array(
			'host'       => $host,
			'port'       => $port,
			'encryption' => $encryption,
			'auth'       => $auth,
			'username'   => $username,
			'password'   => $password,
		);
	}

	/**
	 * @param mixed                                                                                          $phpmailer PHPMailer instance.
	 * @param array{host:string,port:int,encryption:string,auth:bool,username:string,password:string} $config     SMTP config.
	 * @return void
	 */
	private static function apply_smtp_to_phpmailer( $phpmailer, array $config ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer public API.
		$phpmailer->isSMTP();
		$phpmailer->Host = $config['host'];
		$phpmailer->Port = $config['port'];

		if ( 'none' === $config['encryption'] ) {
			$phpmailer->SMTPSecure = '';
		} else {
			$phpmailer->SMTPSecure = $config['encryption'];
		}

		$phpmailer->SMTPAuth = $config['auth'];
		if ( $config['auth'] ) {
			$phpmailer->Username = $config['username'];
			$phpmailer->Password = $config['password'];
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
