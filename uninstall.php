<?php
/**
 * Plugin uninstall routine.
 *
 * @package Webentwicklerin\WeFormkit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$we_formkit_delete_data = get_option( 'we_formkit_delete_data_on_uninstall', false );
if ( ! $we_formkit_delete_data ) {
	$we_formkit_settings = get_option( 'we_formkit_settings', array() );
	if ( is_array( $we_formkit_settings ) && ! empty( $we_formkit_settings['delete_data_on_uninstall'] ) ) {
		$we_formkit_delete_data = true;
	}
}

if ( ! $we_formkit_delete_data ) {
	return;
}

$we_formkit_form_ids = get_posts(
	array(
		'post_type'      => 'wek_form',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $we_formkit_form_ids as $we_formkit_form_id ) {
	wp_delete_post( (int) $we_formkit_form_id, true );
}

$we_formkit_submission_ids = get_posts(
	array(
		'post_type'      => 'wek_submission',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $we_formkit_submission_ids as $we_formkit_submission_id ) {
	wp_delete_post( (int) $we_formkit_submission_id, true );
}

delete_option( 'we_formkit_settings' );
delete_option( 'we_formkit_delete_data_on_uninstall' );
delete_option( 'we_formkit_version' );

$we_formkit_roles = array( 'administrator', 'editor' );
$we_formkit_caps  = array(
	'edit_wek_forms',
	'edit_others_wek_forms',
	'publish_wek_forms',
	'read_private_wek_forms',
	'delete_wek_forms',
	'delete_others_wek_forms',
	'edit_wek_submissions',
	'edit_others_wek_submissions',
	'publish_wek_submissions',
	'read_private_wek_submissions',
	'delete_wek_submissions',
	'delete_others_wek_submissions',
	'manage_we_formkit',
);
foreach ( $we_formkit_roles as $we_formkit_role_name ) {
	$we_formkit_role = get_role( $we_formkit_role_name );
	if ( ! $we_formkit_role ) {
		continue;
	}
	foreach ( $we_formkit_caps as $we_formkit_cap ) {
		$we_formkit_role->remove_cap( $we_formkit_cap );
	}
}
