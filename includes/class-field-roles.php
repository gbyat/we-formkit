<?php
/**
 * Semantic field roles (name / address / …) for Formkit + Spamfighterin.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whitelist and labels for field.role.
 */
final class Field_Roles {

	/**
	 * Allowed role slugs.
	 *
	 * @return list<string>
	 */
	public static function all() {
		$roles = array(
			'given_name',
			'family_name',
			'additional_name',
			'honorific_prefix',
			'organization',
			'street',
			'street_2',
			'postal_code',
			'locality',
			'region',
			'country',
			'country_other',
			'message',
		);

		/**
		 * Filters allowed semantic field roles.
		 *
		 * @param list<string> $roles Role slugs.
		 */
		return apply_filters( 'we_formkit_field_roles', $roles );
	}

	/**
	 * Human labels (English source).
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			'given_name'       => __( 'First name', 'we-formkit' ),
			'family_name'      => __( 'Last name', 'we-formkit' ),
			'additional_name'  => __( 'Middle name', 'we-formkit' ),
			'honorific_prefix' => __( 'Title / prefix', 'we-formkit' ),
			'organization'     => __( 'Organization', 'we-formkit' ),
			'street'           => __( 'Street address', 'we-formkit' ),
			'street_2'         => __( 'Address line 2', 'we-formkit' ),
			'postal_code'      => __( 'Postal code', 'we-formkit' ),
			'locality'         => __( 'City', 'we-formkit' ),
			'region'           => __( 'State / province', 'we-formkit' ),
			'country'          => __( 'Country', 'we-formkit' ),
			'country_other'    => __( 'Other country', 'we-formkit' ),
			'message'          => __( 'Message', 'we-formkit' ),
		);
	}

	/**
	 * HTML autocomplete token for a role (empty if none).
	 *
	 * @param string $role Role.
	 * @return string
	 */
	public static function autocomplete( $role ) {
		$map  = array(
			'given_name'       => 'given-name',
			'family_name'      => 'family-name',
			'additional_name'  => 'additional-name',
			'honorific_prefix' => 'honorific-prefix',
			'organization'     => 'organization',
			'street'           => 'street-address',
			'street_2'         => 'address-line2',
			'postal_code'      => 'postal-code',
			'locality'         => 'address-level2',
			'region'           => 'address-level1',
			'country'          => 'country',
			'country_other'    => 'country-name',
		);
		$role = sanitize_key( (string) $role );
		return isset( $map[ $role ] ) ? $map[ $role ] : '';
	}

	/**
	 * Sanitize role or return empty string.
	 *
	 * @param mixed $raw Raw role.
	 * @return string
	 */
	public static function normalize( $raw ) {
		$role = sanitize_key( (string) $raw );
		if ( '' === $role || ! in_array( $role, self::all(), true ) ) {
			return '';
		}
		return $role;
	}

	/**
	 * Name pack slot defaults (enabled + order).
	 *
	 * @return list<array{role:string,enabled:bool,width:string}>
	 */
	public static function name_pack_slots() {
		$slots = array(
			array(
				'role'    => 'honorific_prefix',
				'enabled' => false,
				'width'   => 'third',
			),
			array(
				'role'    => 'given_name',
				'enabled' => true,
				'width'   => 'half',
			),
			array(
				'role'    => 'additional_name',
				'enabled' => false,
				'width'   => 'half',
			),
			array(
				'role'    => 'family_name',
				'enabled' => true,
				'width'   => 'half',
			),
		);

		/**
		 * Filters default Name pack slots.
		 *
		 * @param list<array{role:string,enabled:bool,width:string}> $slots Slots.
		 */
		return apply_filters( 'we_formkit_name_field_slots', $slots );
	}

	/**
	 * Address pack slot defaults (DACH layout seed).
	 *
	 * @return list<array{role:string,enabled:bool,width:string}>
	 */
	public static function address_pack_slots() {
		$slots = array(
			array(
				'role'    => 'street',
				'enabled' => true,
				'width'   => 'full',
			),
			array(
				'role'    => 'street_2',
				'enabled' => false,
				'width'   => 'full',
			),
			array(
				'role'    => 'postal_code',
				'enabled' => true,
				'width'   => 'third',
			),
			array(
				'role'    => 'locality',
				'enabled' => true,
				'width'   => 'two_thirds',
			),
			array(
				'role'    => 'region',
				'enabled' => false,
				'width'   => 'half',
			),
			array(
				'role'    => 'country',
				'enabled' => false,
				'width'   => 'full',
			),
		);

		/**
		 * Filters default Address pack slots (order = layout).
		 *
		 * @param list<array{role:string,enabled:bool,width:string}> $slots Slots.
		 */
		return apply_filters( 'we_formkit_address_field_slots', $slots );
	}
}
