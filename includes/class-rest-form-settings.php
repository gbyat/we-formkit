<?php
/**
 * Admin REST: form settings (DataForm UI).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form settings REST controller helpers.
 */
final class Rest_Form_Settings {

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			Rest_Api::NAMESPACE,
			'/forms/(?P<id>\d+)/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			Rest_Api::NAMESPACE,
			'/forms/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_and_save' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage() {
		return Capabilities::can_manage();
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_settings( $request ) {
		$form_id = (int) $request['id'];
		$form    = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type ) {
			return new \WP_Error( 'we_formkit_not_found', __( 'Form not found.', 'we-formkit' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( self::payload_for_form( $form_id ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_settings( $request ) {
		$form_id = (int) $request['id'];
		$form    = get_post( $form_id );
		if ( ! $form || Post_Types::FORM !== $form->post_type ) {
			return new \WP_Error( 'we_formkit_not_found', __( 'Form not found.', 'we-formkit' ), array( 'status' => 404 ) );
		}

		$result = self::persist( $form_id, $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( self::payload_for_form( $form_id ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_and_save( $request ) {
		$params = $request->get_json_params();
		$title  = isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : '';
		if ( '' === $title ) {
			return new \WP_Error( 'we_formkit_title_required', __( 'Title is required.', 'we-formkit' ), array( 'status' => 400 ) );
		}

		$form_id = (int) wp_insert_post(
			array(
				'post_type'   => Post_Types::FORM,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( ! $form_id || is_wp_error( $form_id ) ) {
			return new \WP_Error( 'we_formkit_create_failed', __( 'Could not create form.', 'we-formkit' ), array( 'status' => 500 ) );
		}

		Form_Schema::save(
			$form_id,
			array(
				'version'  => 1,
				'title'    => $title,
				'intro'    => '',
				'sections' => array(),
			)
		);

		$result = self::persist( $form_id, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$payload             = self::payload_for_form( $form_id );
		$payload['edit_url'] = admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=settings&saved=1' );
		return rest_ensure_response( $payload );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $params  Request body.
	 * @return true|\WP_Error
	 */
	private static function persist( $form_id, array $params ) {
		$title = isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : '';
		if ( '' === $title ) {
			return new \WP_Error( 'we_formkit_title_required', __( 'Title is required.', 'we-formkit' ), array( 'status' => 400 ) );
		}

		$slug    = isset( $params['slug'] ) ? sanitize_title( (string) $params['slug'] ) : '';
		$intro   = isset( $params['intro'] ) ? sanitize_textarea_field( (string) $params['intro'] ) : '';
		$privacy = isset( $params['privacy_url'] ) ? esc_url_raw( (string) $params['privacy_url'] ) : '';
		$secret  = ! empty( $params['secret_enabled'] );

		wp_update_post(
			array(
				'ID'         => $form_id,
				'post_title' => $title,
			)
		);

		$schema          = Form_Schema::get( $form_id );
		$schema['title'] = $title;
		$schema['intro'] = $intro;
		Form_Schema::save( $form_id, $schema );

		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}
		update_post_meta( $form_id, Form_Schema::META_SLUG, $slug );
		update_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, $privacy );
		Form_Schema::set_secret( $form_id, $secret );

		$style_in = isset( $params['style'] ) && is_array( $params['style'] ) ? $params['style'] : array();
		Form_Style::save( $form_id, Form_Style::sanitize_from_request( $style_in ) );

		return true;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<string, mixed>
	 */
	public static function payload_for_form( $form_id ) {
		$schema = Form_Schema::get( $form_id );
		$secret = Form_Schema::get_secret( $form_id );
		$style  = Form_Style::get( $form_id );
		$colors = Form_Style::editable_colors( $form_id );

		$secret_url = '';
		if ( ! empty( $secret['enabled'] ) && ! empty( $secret['token'] ) ) {
			$slug = (string) get_post_meta( $form_id, Form_Schema::META_SLUG, true );
			if ( '' !== $slug ) {
				$secret_url = add_query_arg(
					array(
						'wek_form' => $slug,
						'token'    => $secret['token'],
					),
					home_url( '/' )
				);
			}
		}

		return array(
			'form_id'      => (int) $form_id,
			'secret_url'   => $secret_url,
			'secret_token' => (string) ( $secret['token'] ?? '' ),
			'settings'     => array(
				'title'          => (string) ( $schema['title'] ?? get_the_title( $form_id ) ),
				'slug'           => (string) get_post_meta( $form_id, Form_Schema::META_SLUG, true ),
				'intro'          => (string) ( $schema['intro'] ?? '' ),
				'privacy_url'    => (string) get_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, true ),
				'secret_enabled' => ! empty( $secret['enabled'] ),
				'style_preset'   => (string) $style['preset'],
				'colors'         => $colors,
			),
		);
	}
}
