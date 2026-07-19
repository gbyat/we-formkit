<?php
/**
 * Custom post types for forms and submissions.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers CPTs.
 */
final class Post_Types {

	public const FORM       = 'wek_form';
	public const SUBMISSION = 'wek_submission';

	/**
	 * Hook CPT registration onto init.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_types' ) );
	}

	/**
	 * Register form and submission post types.
	 *
	 * @return void
	 */
	public static function register_types() {
		register_post_type(
			self::FORM,
			array(
				'labels'           => array(
					'name'          => __( 'Forms', 'we-formkit' ),
					'singular_name' => __( 'Form', 'we-formkit' ),
					'add_new_item'  => __( 'Add New Form', 'we-formkit' ),
					'edit_item'     => __( 'Edit Form', 'we-formkit' ),
					'search_items'  => __( 'Search Forms', 'we-formkit' ),
					'not_found'     => __( 'No forms found.', 'we-formkit' ),
				),
				'public'           => false,
				'show_ui'          => true,
				'show_in_menu'     => false,
				'show_in_rest'     => false,
				'capability_type'  => array( 'wek_form', 'wek_forms' ),
				'map_meta_cap'     => true,
				'hierarchical'     => false,
				'supports'         => array( 'title' ),
				'has_archive'      => false,
				'rewrite'          => false,
				'query_var'        => false,
				'delete_with_user' => false,
			)
		);

		register_post_type(
			self::SUBMISSION,
			array(
				'labels'           => array(
					'name'          => __( 'Submissions', 'we-formkit' ),
					'singular_name' => __( 'Submission', 'we-formkit' ),
					'edit_item'     => __( 'Edit Submission', 'we-formkit' ),
					'search_items'  => __( 'Search Submissions', 'we-formkit' ),
					'not_found'     => __( 'No submissions found.', 'we-formkit' ),
				),
				'public'           => false,
				'show_ui'          => true,
				'show_in_menu'     => false,
				'show_in_rest'     => false,
				'capability_type'  => array( 'wek_submission', 'wek_submissions' ),
				'map_meta_cap'     => true,
				'hierarchical'     => false,
				'supports'         => array( 'title' ),
				'has_archive'      => false,
				'rewrite'          => false,
				'query_var'        => false,
				'delete_with_user' => false,
			)
		);
	}
}
