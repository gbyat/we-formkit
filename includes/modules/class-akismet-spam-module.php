<?php
/**
 * Akismet spam filter module.
 *
 * Optional adapter that checks validated submissions against Akismet. It only
 * runs when the admin activates it AND the Akismet plugin is active with a
 * configured API key. It fails open: any transport error accepts the entry.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Modules;

use Webentwicklerin\WeFormkit\Form_Schema;
use Webentwicklerin\WeFormkit\Module_Registry;
use Webentwicklerin\WeFormkit\Spam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and runs the Akismet adapter.
 */
final class Akismet_Spam_Module {

	/**
	 * Module id.
	 *
	 * @var string
	 */
	const ID = 'akismet_spam';

	/**
	 * Hook the module declaration into the registry.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'we_formkit_register_modules', array( __CLASS__, 'declare_module' ) );
	}

	/**
	 * Declare the module definition (name, deps, bootstrap).
	 *
	 * @param Module_Registry $registry Registry instance.
	 * @return void
	 */
	public static function declare_module( Module_Registry $registry ) {
		$registry->add(
			self::ID,
			array(
				'name'         => __( 'Akismet spam filter', 'we-formkit' ),
				'description'  => __( 'Check submissions against Akismet and reject the ones it classifies as spam. Uses the API key from the Akismet plugin; fails open if Akismet cannot be reached.', 'we-formkit' ),
				'version'      => '1.0.0',
				'dependencies' => array(
					array(
						'label' => __( 'Akismet plugin active', 'we-formkit' ),
						'check' => static function () {
							return class_exists( 'Akismet' );
						},
					),
					array(
						'label' => __( 'Akismet API key configured', 'we-formkit' ),
						'check' => array( __CLASS__, 'has_api_key' ),
					),
				),
				'bootstrap'    => array( __CLASS__, 'boot' ),
			)
		);
	}

	/**
	 * Attach the spam check once the module is active + satisfied.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'we_formkit_spam_check', array( __CLASS__, 'check' ), 10, 4 );
	}

	/**
	 * Whether Akismet has a usable API key.
	 *
	 * @return bool
	 */
	public static function has_api_key() {
		if ( class_exists( 'Akismet' ) && method_exists( 'Akismet', 'get_api_key' ) ) {
			return '' !== (string) \Akismet::get_api_key();
		}

		return '' !== (string) get_option( 'wordpress_api_key' );
	}

	/**
	 * Reject a submission Akismet flags as spam.
	 *
	 * @param mixed                $result  Incoming result (WP_Error to reject).
	 * @param array<string, mixed> $data    Validated submission values.
	 * @param array<string, mixed> $schema  Form schema.
	 * @param int                  $form_id Form ID.
	 * @return mixed Unchanged $result, or WP_Error when spam.
	 */
	public static function check( $result, $data, $schema, $form_id ) {
		unset( $form_id );

		// A previous filter already rejected — don't override it.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$key = self::api_key();
		if ( '' === $key ) {
			return $result;
		}

		$content = self::build_request( (array) $schema, (array) $data );
		if ( '' === trim( (string) ( $content['comment_content'] ?? '' ) ) ) {
			return $result;
		}

		$is_spam = self::is_spam( $key, $content );
		if ( true === $is_spam ) {
			return new \WP_Error(
				'we_formkit_spam',
				__( 'Your submission was flagged as spam. If this is a mistake, please try again or contact us directly.', 'we-formkit' ),
				array( 'status' => 422 )
			);
		}

		return $result;
	}

	/**
	 * @return string
	 */
	private static function api_key() {
		if ( class_exists( 'Akismet' ) && method_exists( 'Akismet', 'get_api_key' ) ) {
			return (string) \Akismet::get_api_key();
		}

		return (string) get_option( 'wordpress_api_key' );
	}

	/**
	 * Build the Akismet comment-check payload from form values.
	 *
	 * @param array<string, mixed> $schema Form schema.
	 * @param array<string, mixed> $data   Validated values.
	 * @return array<string, string>
	 */
	private static function build_request( array $schema, array $data ) {
		$author  = '';
		$email   = '';
		$content = array();

		$fields = Form_Schema::fields_by_id( $schema );
		foreach ( $fields as $field_id => $field ) {
			$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
			$label = isset( $field['label'] ) ? strtolower( (string) $field['label'] ) : '';
			$value = isset( $data[ $field_id ] ) ? $data[ $field_id ] : '';
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			if ( 'email' === $type && '' === $email ) {
				$email = $value;
				continue;
			}
			if ( '' === $author && ( 'text' === $type ) && false !== strpos( $label, 'name' ) ) {
				$author = $value;
			}
			if ( in_array( $type, array( 'text', 'textarea' ), true ) ) {
				$content[] = $value;
			}
		}

		return array(
			'blog'                 => home_url(),
			'user_ip'              => Spam::client_ip(),
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) ) : '',
			'comment_type'         => 'contact-form',
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'comment_content'      => implode( "\n\n", $content ),
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_bloginfo( 'charset' ),
		);
	}

	/**
	 * Query the Akismet comment-check endpoint.
	 *
	 * @param string                $key     API key.
	 * @param array<string, string> $request Payload.
	 * @return bool|null True = spam, false = ham, null = undetermined (fail open).
	 */
	private static function is_spam( $key, array $request ) {
		$endpoint = 'https://' . rawurlencode( $key ) . '.rest.akismet.com/1.1/comment-check';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'    => 5,
				'body'       => $request,
				'headers'    => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'user-agent' => 'WE Formkit/' . ( defined( 'WE_FORMKIT_VERSION' ) ? WE_FORMKIT_VERSION : '0' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		if ( 'true' === $body ) {
			return true;
		}
		if ( 'false' === $body ) {
			return false;
		}

		return null;
	}
}
