<?php
/**
 * Smart / merge tags for notifications and confirmations.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalog, whitelist replace, and shared merge-variable helpers.
 */
final class Merge_Tags {

	/**
	 * Replace `{tag}` placeholders. Unknown tags are left unchanged.
	 *
	 * @param string                $text Text with tags.
	 * @param array<string, string> $vars Map of tag key => replacement (no braces).
	 * @return string
	 */
	public static function replace( $text, array $vars ) {
		return (string) preg_replace_callback(
			'/\{([a-z0-9_:\-]+)\}/i',
			static function ( $m ) use ( $vars ) {
				$key = $m[1];
				return array_key_exists( $key, $vars ) ? (string) $vars[ $key ] : $m[0];
			},
			(string) $text
		);
	}

	/**
	 * Strip HTML from vars (subjects, confirmation message, redirect URLs).
	 *
	 * @param array<string, string> $vars Vars.
	 * @return array<string, string>
	 */
	public static function plain_vars( array $vars ) {
		$out = array();
		foreach ( $vars as $key => $value ) {
			$out[ $key ] = trim( wp_strip_all_tags( (string) $value ) );
		}
		return $out;
	}

	/**
	 * Catalog for the admin picker.
	 *
	 * @param array<string, mixed>|null $schema  Form schema (for field tags).
	 * @param string                    $context `email` or `confirmation`.
	 * @return list<array{tag:string,label:string,group:string,group_label:string}>
	 */
	public static function catalog( $schema = null, $context = 'email' ) {
		$context = 'confirmation' === $context ? 'confirmation' : 'email';

		$items = array(
			array(
				'tag'         => '{form_title}',
				'label'       => __( 'Form title', 'we-formkit' ),
				'group'       => 'form',
				'group_label' => __( 'Form', 'we-formkit' ),
			),
			array(
				'tag'         => '{form_id}',
				'label'       => __( 'Form ID', 'we-formkit' ),
				'group'       => 'form',
				'group_label' => __( 'Form', 'we-formkit' ),
			),
			array(
				'tag'         => '{submission_id}',
				'label'       => __( 'Entry ID', 'we-formkit' ),
				'group'       => 'entry',
				'group_label' => __( 'Entry', 'we-formkit' ),
			),
			array(
				'tag'         => '{submission_url}',
				'label'       => __( 'Entry admin URL', 'we-formkit' ),
				'group'       => 'entry',
				'group_label' => __( 'Entry', 'we-formkit' ),
			),
			array(
				'tag'         => '{date}',
				'label'       => __( 'Submission date', 'we-formkit' ),
				'group'       => 'entry',
				'group_label' => __( 'Entry', 'we-formkit' ),
			),
			array(
				'tag'         => '{all_fields}',
				'label'       => __( 'All fields', 'we-formkit' ),
				'group'       => 'entry',
				'group_label' => __( 'Entry', 'we-formkit' ),
			),
			array(
				'tag'         => '{source_url}',
				'label'       => __( 'Source page URL', 'we-formkit' ),
				'group'       => 'source',
				'group_label' => __( 'Source', 'we-formkit' ),
			),
			array(
				'tag'         => '{referrer}',
				'label'       => __( 'HTTP referrer', 'we-formkit' ),
				'group'       => 'source',
				'group_label' => __( 'Source', 'we-formkit' ),
			),
			array(
				'tag'         => '{user_agent}',
				'label'       => __( 'User agent', 'we-formkit' ),
				'group'       => 'source',
				'group_label' => __( 'Source', 'we-formkit' ),
			),
			array(
				'tag'         => '{site_name}',
				'label'       => __( 'Site name', 'we-formkit' ),
				'group'       => 'site',
				'group_label' => __( 'Site', 'we-formkit' ),
			),
			array(
				'tag'         => '{admin_email}',
				'label'       => __( 'Admin email', 'we-formkit' ),
				'group'       => 'site',
				'group_label' => __( 'Site', 'we-formkit' ),
			),
			array(
				'tag'         => '{user_login}',
				'label'       => __( 'Logged-in user login', 'we-formkit' ),
				'group'       => 'user',
				'group_label' => __( 'User', 'we-formkit' ),
			),
			array(
				'tag'         => '{user_email}',
				'label'       => __( 'Logged-in user email', 'we-formkit' ),
				'group'       => 'user',
				'group_label' => __( 'User', 'we-formkit' ),
			),
			array(
				'tag'         => '{user_display_name}',
				'label'       => __( 'Logged-in user display name', 'we-formkit' ),
				'group'       => 'user',
				'group_label' => __( 'User', 'we-formkit' ),
			),
		);

		if ( 'email' === $context ) {
			$items[] = array(
				'tag'         => '{info_links}',
				'label'       => __( 'Info document links', 'we-formkit' ),
				'group'       => 'entry',
				'group_label' => __( 'Entry', 'we-formkit' ),
			);
		}

		if ( is_array( $schema ) ) {
			foreach ( Form_Schema::fields_by_id( $schema ) as $field_id => $field ) {
				$type = isset( $field['type'] ) ? (string) $field['type'] : '';
				if ( in_array( $type, array( 'html', 'hidden' ), true ) ) {
					continue;
				}
				$label   = isset( $field['label'] ) ? (string) $field['label'] : $field_id;
				$items[] = array(
					'tag'         => '{field:' . $field_id . '}',
					'label'       => $label,
					'group'       => 'fields',
					'group_label' => __( 'Fields', 'we-formkit' ),
				);
			}
		}

		/**
		 * Filter the smart-tag catalog for the admin picker.
		 *
		 * @param list<array{tag:string,label:string,group:string,group_label:string}> $items   Catalog.
		 * @param array<string, mixed>|null                                            $schema  Schema.
		 * @param string                                                               $context Context.
		 */
		$filtered = apply_filters( 'we_formkit_merge_tag_catalog', $items, $schema, $context );

		return is_array( $filtered ) ? array_values( $filtered ) : $items;
	}

	/**
	 * Shared meta vars (form / entry / site / user / source) without field blocks.
	 *
	 * @param int $submission_id Submission ID.
	 * @param int $form_id       Form ID.
	 * @return array<string, string>
	 */
	public static function meta_vars( $submission_id, $form_id ) {
		$submission_id = (int) $submission_id;
		$form_id       = (int) $form_id;
		$form_title    = get_the_title( $form_id );
		$edit_link     = admin_url( 'admin.php?page=we-formkit-submissions&action=edit&submission_id=' . $submission_id );

		$user_login = '';
		$user_email = '';
		$user_name  = '';
		$source_url = '';
		$referrer   = '';
		$user_agent = '';

		if ( $submission_id > 0 ) {
			$user_login = (string) get_post_meta( $submission_id, Form_Schema::SUB_USER_LOGIN, true );
			$user_email = (string) get_post_meta( $submission_id, Form_Schema::SUB_USER_EMAIL, true );
			$user_name  = (string) get_post_meta( $submission_id, Form_Schema::SUB_USER_DISPLAY_NAME, true );
			$source_url = (string) get_post_meta( $submission_id, Form_Schema::SUB_SOURCE_URL, true );
			$referrer   = (string) get_post_meta( $submission_id, Form_Schema::SUB_REFERRER, true );
			$user_agent = (string) get_post_meta( $submission_id, Form_Schema::SUB_USER_AGENT, true );
		} elseif ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user && $user->exists() ) {
				$user_login = (string) $user->user_login;
				$user_email = (string) $user->user_email;
				$user_name  = (string) $user->display_name;
			}
		}
		$date = '';
		if ( $submission_id > 0 ) {
			$post = get_post( $submission_id );
			if ( $post ) {
				$date = (string) wp_date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					strtotime( $post->post_date_gmt . ' GMT' )
				);
			}
		}
		if ( '' === $date ) {
			$date = (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		}

		return array(
			'form_title'        => esc_html( is_string( $form_title ) ? $form_title : '' ),
			'form_id'           => (string) $form_id,
			'submission_id'     => (string) $submission_id,
			'submission_url'    => esc_url( $edit_link ),
			'site_name'         => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'admin_email'       => esc_html( (string) get_option( 'admin_email' ) ),
			'date'              => esc_html( $date ),
			'source_url'        => esc_url( $source_url ),
			'referrer'          => esc_url( $referrer ),
			'user_agent'        => esc_html( $user_agent ),
			'user_login'        => esc_html( $user_login ),
			'user_email'        => esc_html( $user_email ),
			'user_display_name' => esc_html( $user_name ),
		);
	}

	/**
	 * Capture request / user snapshot for a new submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return void
	 */
	public static function persist_request_meta( $submission_id ) {
		$submission_id = (int) $submission_id;
		if ( $submission_id <= 0 ) {
			return;
		}

		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
		$ref = isset( $_SERVER['HTTP_REFERER'] )
			? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) )
			: '';

		update_post_meta( $submission_id, Form_Schema::SUB_USER_AGENT, $ua );
		update_post_meta( $submission_id, Form_Schema::SUB_REFERRER, $ref );

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user && $user->exists() ) {
				update_post_meta( $submission_id, Form_Schema::SUB_USER_LOGIN, (string) $user->user_login );
				update_post_meta( $submission_id, Form_Schema::SUB_USER_EMAIL, (string) $user->user_email );
				update_post_meta( $submission_id, Form_Schema::SUB_USER_DISPLAY_NAME, (string) $user->display_name );
			}
		}
	}

	/**
	 * Apply plain merge tags to confirmation message / redirect.
	 *
	 * @param string                    $text           Template.
	 * @param int                       $submission_id  Submission ID.
	 * @param int                       $form_id        Form ID.
	 * @param array<string, mixed>      $schema         Schema.
	 * @param array<string, mixed>      $data           Values.
	 * @param list<array<string,mixed>> $matched_docs   Docs.
	 * @return string
	 */
	public static function apply_confirmation( $text, $submission_id, $form_id, array $schema, array $data, array $matched_docs = array() ) {
		$vars = Notifications::merge_vars_public( $submission_id, $form_id, $schema, $data, array( 'include_fields' => 'all' ), $matched_docs );
		return self::replace( (string) $text, self::plain_vars( $vars ) );
	}
}
