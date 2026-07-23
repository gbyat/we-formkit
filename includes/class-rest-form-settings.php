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
		$intro   = isset( $params['intro'] ) ? wp_kses_post( (string) $params['intro'] ) : '';
		$privacy = isset( $params['privacy_url'] ) ? esc_url_raw( (string) $params['privacy_url'] ) : '';
		$secret  = ! empty( $params['secret_enabled'] );

		wp_update_post(
			array(
				'ID'         => $form_id,
				'post_title' => $title,
			)
		);

		$schema               = Form_Schema::get( $form_id );
		$schema['title']      = $title;
		$schema['intro']      = $intro;
		$schema['show_title'] = ! array_key_exists( 'show_title', $params ) || ! empty( $params['show_title'] );
		$schema['show_intro'] = ! array_key_exists( 'show_intro', $params ) || ! empty( $params['show_intro'] );
		Form_Schema::save( $form_id, $schema );

		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}
		update_post_meta( $form_id, Form_Schema::META_SLUG, $slug );
		update_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, $privacy );
		Form_Schema::set_secret( $form_id, $secret );

		$style_in = isset( $params['style'] ) && is_array( $params['style'] ) ? $params['style'] : array();
		Form_Style::save( $form_id, Form_Style::sanitize_from_request( $style_in ) );

		// Submit button is edited on the Fields canvas; only update if explicitly sent.
		if ( isset( $params['submit_label'] ) || isset( $params['submit_icon_svg'] ) || isset( $params['submit_icon_position'] ) || isset( $params['submit_width'] ) ) {
			$current = Form_Schema::get_submit_button( $form_id );
			Form_Schema::set_submit_button(
				$form_id,
				array(
					'label'         => isset( $params['submit_label'] ) ? (string) $params['submit_label'] : $current['label'],
					'icon_svg'      => isset( $params['submit_icon_svg'] ) ? (string) $params['submit_icon_svg'] : $current['icon_svg'],
					'icon_position' => isset( $params['submit_icon_position'] ) ? (string) $params['submit_icon_position'] : $current['icon_position'],
					'width'         => isset( $params['submit_width'] ) ? (string) $params['submit_width'] : $current['width'],
				)
			);
		}

		Form_Schema::set_appearance(
			$form_id,
			array(
				'label_weight'      => isset( $params['label_weight'] ) ? (string) $params['label_weight'] : 'bold',
				'required_mark'     => isset( $params['required_mark'] ) ? (string) $params['required_mark'] : 'asterisk',
				'optional_mark'     => isset( $params['optional_mark'] ) ? (string) $params['optional_mark'] : 'text',
				'inline_validation' => isset( $params['inline_validation'] ) ? (string) $params['inline_validation'] : 'both',
				'inline_scope'      => isset( $params['inline_scope'] ) ? (string) $params['inline_scope'] : 'required',
				'help_placement'    => isset( $params['help_placement'] ) ? (string) $params['help_placement'] : 'below_label',
				'help_style'        => isset( $params['help_style'] ) ? (string) $params['help_style'] : 'muted',
				'font_family'       => isset( $params['font_family'] ) ? (string) $params['font_family'] : 'inherit',
				'spacing'           => isset( $params['spacing'] ) ? (string) $params['spacing'] : 'cozy',
				'chrome_gap'        => isset( $params['chrome_gap'] ) ? (string) $params['chrome_gap'] : 'none',
				'control_padding'   => isset( $params['control_padding'] ) ? (string) $params['control_padding'] : 'cozy',
				'size_section'      => isset( $params['size_section'] ) ? (string) $params['size_section'] : 'md',
				'size_label'        => isset( $params['size_label'] ) ? (string) $params['size_label'] : 'md',
				'size_input'        => isset( $params['size_input'] ) ? (string) $params['size_input'] : 'md',
				'radius_input'      => isset( $params['radius_input'] ) ? (string) $params['radius_input'] : 'md',
				'radius_button'     => isset( $params['radius_button'] ) ? (string) $params['radius_button'] : 'pill',
				'radius_section'    => isset( $params['radius_section'] ) ? (string) $params['radius_section'] : 'md',
			)
		);

		Drafts::set_enabled( $form_id, ! empty( $params['save_resume'] ) );
		Drafts::set_ttl_days(
			$form_id,
			isset( $params['save_resume_ttl'] ) ? absint( $params['save_resume_ttl'] ) : Drafts::TTL_DAYS
		);
		if ( array_key_exists( 'save_resume_min', $params ) && null !== $params['save_resume_min'] && '' !== $params['save_resume_min'] ) {
			Drafts::set_min_filled( $form_id, absint( $params['save_resume_min'] ) );
		} elseif ( ! empty( $params['save_resume'] ) && '' === get_post_meta( $form_id, Drafts::META_MIN_FILLED, true ) ) {
			// First enable without an explicit min: use product default (not 0).
			Drafts::set_min_filled( $form_id, Drafts::MIN_FILLED );
		}
		Drafts::set_reminders_allowed( $form_id, ! empty( $params['save_resume_reminders'] ) );

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
		$appear = Form_Schema::get_appearance( $form_id );

		$secret_url = Form_Schema::get_secret_query( $form_id );

		return array(
			'form_id'      => (int) $form_id,
			'secret_url'   => $secret_url,
			'secret_token' => (string) ( $secret['token'] ?? '' ),
			'settings'     => array(
				'title'                 => (string) ( $schema['title'] ?? get_the_title( $form_id ) ),
				'show_title'            => ! array_key_exists( 'show_title', $schema ) || ! empty( $schema['show_title'] ),
				'slug'                  => (string) get_post_meta( $form_id, Form_Schema::META_SLUG, true ),
				'intro'                 => (string) ( $schema['intro'] ?? '' ),
				'show_intro'            => ! array_key_exists( 'show_intro', $schema ) || ! empty( $schema['show_intro'] ),
				'privacy_url'           => (string) get_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, true ),
				'secret_enabled'        => ! empty( $secret['enabled'] ),
				'style_preset'          => (string) $style['preset'],
				'colors'                => $colors,
				'label_weight'          => (string) $appear['label_weight'],
				'required_mark'         => (string) $appear['required_mark'],
				'optional_mark'         => (string) $appear['optional_mark'],
				'inline_validation'     => (string) $appear['inline_validation'],
				'inline_scope'          => (string) $appear['inline_scope'],
				'help_placement'        => (string) $appear['help_placement'],
				'help_style'            => (string) $appear['help_style'],
				'font_family'           => (string) $appear['font_family'],
				'spacing'               => (string) $appear['spacing'],
				'chrome_gap'            => (string) $appear['chrome_gap'],
				'control_padding'       => (string) $appear['control_padding'],
				'size_section'          => (string) $appear['size_section'],
				'size_label'            => (string) $appear['size_label'],
				'size_input'            => (string) $appear['size_input'],
				'radius_input'          => (string) $appear['radius_input'],
				'radius_button'         => (string) $appear['radius_button'],
				'radius_section'        => (string) $appear['radius_section'],
				'has_custom'            => ! empty( Form_Style::saved_custom_colors( $form_id ) ),
				'save_resume'           => Drafts::is_enabled( $form_id ),
				'save_resume_ttl'       => Drafts::get_ttl_days( $form_id ),
				'save_resume_min'       => Drafts::get_min_filled( $form_id ),
				'save_resume_reminders' => Drafts::reminders_allowed( $form_id ),
			),
		);
	}
}
