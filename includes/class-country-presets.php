<?php
/**
 * Country select presets for Address pack.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ISO country lists by preset.
 */
final class Country_Presets {

	public const OTHER_VALUE = '__other__';

	/**
	 * Preset definitions for the builder.
	 *
	 * @return array<string, array{label:string,include_other_default:bool}>
	 */
	public static function catalog() {
		$catalog = array(
			'dach'          => array(
				'label'                 => __( 'DACH (+ Liechtenstein)', 'we-formkit' ),
				'include_other_default' => true,
			),
			'eu'            => array(
				'label'                 => __( 'European Union', 'we-formkit' ),
				'include_other_default' => true,
			),
			'europe'        => array(
				'label'                 => __( 'Europe', 'we-formkit' ),
				'include_other_default' => true,
			),
			'north_america' => array(
				'label'                 => __( 'North America', 'we-formkit' ),
				'include_other_default' => true,
			),
			'south_america' => array(
				'label'                 => __( 'South America', 'we-formkit' ),
				'include_other_default' => true,
			),
			'africa'        => array(
				'label'                 => __( 'Africa', 'we-formkit' ),
				'include_other_default' => true,
			),
			'asia'          => array(
				'label'                 => __( 'Asia', 'we-formkit' ),
				'include_other_default' => true,
			),
			'oceania'       => array(
				'label'                 => __( 'Oceania', 'we-formkit' ),
				'include_other_default' => true,
			),
			'world'         => array(
				'label'                 => __( 'World (all countries)', 'we-formkit' ),
				'include_other_default' => false,
			),
		);

		/**
		 * Filters country preset catalog (id => meta).
		 *
		 * @param array<string, array{label:string,include_other_default:bool}> $catalog Catalog.
		 */
		return apply_filters( 'we_formkit_country_presets', $catalog );
	}

	/**
	 * ISO codes for a preset.
	 *
	 * @param string $preset Preset id.
	 * @return list<string>
	 */
	public static function codes_for( $preset ) {
		$preset = sanitize_key( (string) $preset );
		$map    = self::code_map();
		$codes  = isset( $map[ $preset ] ) ? $map[ $preset ] : $map['dach'];

		/**
		 * Filters ISO codes for a country preset.
		 *
		 * @param list<string> $codes  ISO alpha-2 codes.
		 * @param string       $preset Preset id.
		 */
		return apply_filters( 'we_formkit_country_preset_codes', $codes, $preset );
	}

	/**
	 * Select options [{value,label}] for a preset.
	 *
	 * @param string $preset        Preset id.
	 * @param bool   $include_other Whether to append Other.
	 * @return list<array{value:string,label:string}>
	 */
	public static function options_for( $preset, $include_other = true ) {
		$names   = self::country_names();
		$options = array();
		foreach ( self::codes_for( $preset ) as $code ) {
			$code = strtoupper( sanitize_key( $code ) );
			if ( 2 !== strlen( $code ) ) {
				continue;
			}
			$options[] = array(
				'value' => $code,
				'label' => isset( $names[ $code ] ) ? $names[ $code ] : $code,
			);
		}

		usort(
			$options,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		if ( $include_other ) {
			$options[] = array(
				'value' => self::OTHER_VALUE,
				'label' => __( 'Other', 'we-formkit' ),
			);
		}

		return $options;
	}

	/**
	 * Put priority ISO codes first; keep remaining A–Z; Other stays last if present.
	 *
	 * @param list<array{value:string,label:string}> $options  Options.
	 * @param list<string>                           $priority ISO codes in desired order.
	 * @return list<array{value:string,label:string}>
	 */
	public static function prioritize_options( array $options, array $priority ) {
		$other   = null;
		$by_code = array();
		foreach ( $options as $opt ) {
			if ( ! is_array( $opt ) || empty( $opt['value'] ) ) {
				continue;
			}
			$code = (string) $opt['value'];
			if ( self::OTHER_VALUE === $code ) {
				$other = $opt;
				continue;
			}
			$by_code[ strtoupper( $code ) ] = $opt;
		}

		$out  = array();
		$used = array();
		foreach ( $priority as $code ) {
			$code = strtoupper( sanitize_key( (string) $code ) );
			if ( 2 !== strlen( $code ) || empty( $by_code[ $code ] ) || isset( $used[ $code ] ) ) {
				continue;
			}
			$out[]         = $by_code[ $code ];
			$used[ $code ] = true;
		}

		$rest = array();
		foreach ( $by_code as $code => $opt ) {
			if ( isset( $used[ $code ] ) ) {
				continue;
			}
			$rest[] = $opt;
		}
		usort(
			$rest,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		$out = array_merge( $out, $rest );
		if ( null !== $other ) {
			$out[] = $other;
		}

		/**
		 * Filters ordered country options after priority sort.
		 *
		 * @param list<array{value:string,label:string}> $out      Ordered options.
		 * @param list<string>                           $priority Priority ISO codes.
		 */
		return apply_filters( 'we_formkit_country_options_ordered', $out, $priority );
	}

	/**
	 * Guess home country from site locale (e.g. de_AT → AT).
	 *
	 * @return string ISO alpha-2 or empty.
	 */
	public static function guess_home_country() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$locale = str_replace( '-', '_', (string) $locale );
		$parts  = explode( '_', $locale );
		if ( count( $parts ) >= 2 ) {
			$region = strtoupper( sanitize_key( $parts[1] ) );
			if ( 2 === strlen( $region ) && isset( self::english_names()[ $region ] ) ) {
				return $region;
			}
		}
		$lang = strtolower( sanitize_key( $parts[0] ?? '' ) );
		$map  = array(
			'de' => 'DE',
			'at' => 'AT', // rare
			'en' => 'GB',
			'fr' => 'FR',
			'it' => 'IT',
			'nl' => 'NL',
			'es' => 'ES',
			'pt' => 'PT',
			'pl' => 'PL',
		);
		return isset( $map[ $lang ] ) ? $map[ $lang ] : '';
	}

	/**
	 * Default priority list for a preset (home first when present).
	 *
	 * @param string $preset Preset id.
	 * @return list<string>
	 */
	public static function default_priority_for( $preset ) {
		$home  = self::guess_home_country();
		$codes = self::codes_for( $preset );
		$upper = array_map( 'strtoupper', $codes );
		$out   = array();

		if ( '' !== $home && in_array( $home, $upper, true ) ) {
			$out[] = $home;
		}

		// Sensible World / EU starters when home missing or for world list.
		$hints = array( 'AT', 'DE', 'CH', 'LI', 'US', 'GB', 'FR' );
		if ( 'dach' === $preset ) {
			$hints = array( 'AT', 'DE', 'CH', 'LI' );
		} elseif ( 'eu' === $preset || 'europe' === $preset ) {
			$hints = array( 'AT', 'DE', 'CH', 'FR', 'IT', 'ES', 'NL', 'PL' );
		} elseif ( 'world' === $preset ) {
			$hints = array( 'AT', 'DE', 'CH', 'LI', 'US', 'GB', 'FR', 'IT', 'ES', 'NL' );
		}

		foreach ( $hints as $code ) {
			if ( in_array( $code, $upper, true ) && ! in_array( $code, $out, true ) ) {
				$out[] = $code;
			}
		}

		/**
		 * Filters default country priority ISO list for a preset.
		 *
		 * @param list<string> $out    Priority codes.
		 * @param string       $preset Preset id.
		 * @param string       $home   Guessed home country.
		 */
		return apply_filters( 'we_formkit_country_default_priority', $out, $preset, $home );
	}

	/**
	 * Builder boot payload.
	 *
	 * @return array{catalog:array,otherValue:string,defaultPreset:string,homeCountry:string}
	 */
	public static function boot() {
		$catalog = self::catalog();
		$out     = array();
		foreach ( $catalog as $id => $meta ) {
			$out[ $id ] = array(
				'label'                 => (string) ( $meta['label'] ?? $id ),
				'include_other_default' => ! empty( $meta['include_other_default'] ),
				'options'               => self::options_for( $id, false ),
				'default_priority'      => self::default_priority_for( $id ),
			);
		}

		return array(
			'catalog'       => $out,
			'otherValue'    => self::OTHER_VALUE,
			'defaultPreset' => 'dach',
			'homeCountry'   => self::guess_home_country(),
		);
	}

	/**
	 * @return array<string, list<string>>
	 */
	private static function code_map() {
		$eu = array(
			'AT',
			'BE',
			'BG',
			'HR',
			'CY',
			'CZ',
			'DK',
			'EE',
			'FI',
			'FR',
			'DE',
			'GR',
			'HU',
			'IE',
			'IT',
			'LV',
			'LT',
			'LU',
			'MT',
			'NL',
			'PL',
			'PT',
			'RO',
			'SK',
			'SI',
			'ES',
			'SE',
		);

		$europe = array_values(
			array_unique(
				array_merge(
					$eu,
					array( 'AL', 'AD', 'BA', 'BY', 'CH', 'FO', 'GB', 'GG', 'GI', 'IS', 'IM', 'JE', 'XK', 'LI', 'MK', 'MD', 'MC', 'ME', 'NO', 'RU', 'SM', 'RS', 'SJ', 'UA', 'VA' )
				)
			)
		);

		return array(
			'dach'          => array( 'DE', 'AT', 'CH', 'LI' ),
			'eu'            => $eu,
			'europe'        => $europe,
			'north_america' => array( 'CA', 'US', 'MX', 'GT', 'BZ', 'HN', 'SV', 'NI', 'CR', 'PA', 'CU', 'DO', 'HT', 'JM', 'TT', 'BB', 'BS', 'AG', 'DM', 'GD', 'KN', 'LC', 'VC', 'PR' ),
			'south_america' => array( 'AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PY', 'PE', 'SR', 'UY', 'VE' ),
			'africa'        => array( 'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CV', 'CM', 'CF', 'TD', 'KM', 'CG', 'CD', 'CI', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ', 'TG', 'TN', 'UG', 'ZM', 'ZW' ),
			'asia'          => array( 'AF', 'AM', 'AZ', 'BH', 'BD', 'BT', 'BN', 'KH', 'CN', 'GE', 'HK', 'IN', 'ID', 'IR', 'IQ', 'IL', 'JP', 'JO', 'KZ', 'KW', 'KG', 'LA', 'LB', 'MO', 'MY', 'MV', 'MN', 'MM', 'NP', 'KP', 'OM', 'PK', 'PS', 'PH', 'QA', 'SA', 'SG', 'KR', 'LK', 'SY', 'TW', 'TJ', 'TH', 'TL', 'TR', 'TM', 'AE', 'UZ', 'VN', 'YE' ),
			'oceania'       => array( 'AU', 'FJ', 'KI', 'MH', 'FM', 'NR', 'NZ', 'PW', 'PG', 'WS', 'SB', 'TO', 'TV', 'VU', 'NC', 'PF', 'GU', 'MP', 'AS', 'CK', 'NU', 'TK' ),
			'world'         => array_keys( self::country_names() ),
		);
	}

	/**
	 * Localized country names keyed by ISO alpha-2.
	 *
	 * @return array<string, string>
	 */
	private static function country_names() {
		static $names = null;
		if ( null !== $names ) {
			return $names;
		}

		$english = self::english_names();
		$locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$names   = array();

		foreach ( $english as $code => $label ) {
			$display = '';
			if ( class_exists( '\Locale' ) ) {
				$display = (string) \Locale::getDisplayRegion( '-' . $code, $locale );
			}
			$names[ $code ] = '' !== $display && $display !== $code ? $display : $label;
		}

		return $names;
	}

	/**
	 * English fallback names (source language).
	 *
	 * @return array<string, string>
	 */
	private static function english_names() {
		return array(
			'AD' => 'Andorra',
			'AE' => 'United Arab Emirates',
			'AF' => 'Afghanistan',
			'AG' => 'Antigua and Barbuda',
			'AL' => 'Albania',
			'AM' => 'Armenia',
			'AO' => 'Angola',
			'AR' => 'Argentina',
			'AS' => 'American Samoa',
			'AT' => 'Austria',
			'AU' => 'Australia',
			'AZ' => 'Azerbaijan',
			'BA' => 'Bosnia and Herzegovina',
			'BB' => 'Barbados',
			'BD' => 'Bangladesh',
			'BE' => 'Belgium',
			'BF' => 'Burkina Faso',
			'BG' => 'Bulgaria',
			'BH' => 'Bahrain',
			'BI' => 'Burundi',
			'BJ' => 'Benin',
			'BN' => 'Brunei',
			'BO' => 'Bolivia',
			'BR' => 'Brazil',
			'BS' => 'Bahamas',
			'BT' => 'Bhutan',
			'BW' => 'Botswana',
			'BY' => 'Belarus',
			'BZ' => 'Belize',
			'CA' => 'Canada',
			'CD' => 'Congo (DRC)',
			'CF' => 'Central African Republic',
			'CG' => 'Congo (Republic)',
			'CH' => 'Switzerland',
			'CI' => 'Côte d’Ivoire',
			'CK' => 'Cook Islands',
			'CL' => 'Chile',
			'CM' => 'Cameroon',
			'CN' => 'China',
			'CO' => 'Colombia',
			'CR' => 'Costa Rica',
			'CU' => 'Cuba',
			'CV' => 'Cabo Verde',
			'CY' => 'Cyprus',
			'CZ' => 'Czechia',
			'DE' => 'Germany',
			'DJ' => 'Djibouti',
			'DK' => 'Denmark',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'DZ' => 'Algeria',
			'EC' => 'Ecuador',
			'EE' => 'Estonia',
			'EG' => 'Egypt',
			'ER' => 'Eritrea',
			'ES' => 'Spain',
			'ET' => 'Ethiopia',
			'FI' => 'Finland',
			'FJ' => 'Fiji',
			'FK' => 'Falkland Islands',
			'FM' => 'Micronesia',
			'FO' => 'Faroe Islands',
			'FR' => 'France',
			'GA' => 'Gabon',
			'GB' => 'United Kingdom',
			'GD' => 'Grenada',
			'GE' => 'Georgia',
			'GF' => 'French Guiana',
			'GG' => 'Guernsey',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GM' => 'Gambia',
			'GN' => 'Guinea',
			'GQ' => 'Equatorial Guinea',
			'GR' => 'Greece',
			'GT' => 'Guatemala',
			'GU' => 'Guam',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HK' => 'Hong Kong',
			'HN' => 'Honduras',
			'HR' => 'Croatia',
			'HT' => 'Haiti',
			'HU' => 'Hungary',
			'ID' => 'Indonesia',
			'IE' => 'Ireland',
			'IL' => 'Israel',
			'IM' => 'Isle of Man',
			'IN' => 'India',
			'IQ' => 'Iraq',
			'IR' => 'Iran',
			'IS' => 'Iceland',
			'IT' => 'Italy',
			'JE' => 'Jersey',
			'JM' => 'Jamaica',
			'JO' => 'Jordan',
			'JP' => 'Japan',
			'KE' => 'Kenya',
			'KG' => 'Kyrgyzstan',
			'KH' => 'Cambodia',
			'KI' => 'Kiribati',
			'KM' => 'Comoros',
			'KN' => 'Saint Kitts and Nevis',
			'KP' => 'North Korea',
			'KR' => 'South Korea',
			'KW' => 'Kuwait',
			'KZ' => 'Kazakhstan',
			'LA' => 'Laos',
			'LB' => 'Lebanon',
			'LC' => 'Saint Lucia',
			'LI' => 'Liechtenstein',
			'LK' => 'Sri Lanka',
			'LR' => 'Liberia',
			'LS' => 'Lesotho',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'LV' => 'Latvia',
			'LY' => 'Libya',
			'MA' => 'Morocco',
			'MC' => 'Monaco',
			'MD' => 'Moldova',
			'ME' => 'Montenegro',
			'MG' => 'Madagascar',
			'MH' => 'Marshall Islands',
			'MK' => 'North Macedonia',
			'ML' => 'Mali',
			'MM' => 'Myanmar',
			'MN' => 'Mongolia',
			'MO' => 'Macao',
			'MP' => 'Northern Mariana Islands',
			'MR' => 'Mauritania',
			'MT' => 'Malta',
			'MU' => 'Mauritius',
			'MV' => 'Maldives',
			'MW' => 'Malawi',
			'MX' => 'Mexico',
			'MY' => 'Malaysia',
			'MZ' => 'Mozambique',
			'NA' => 'Namibia',
			'NC' => 'New Caledonia',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NI' => 'Nicaragua',
			'NL' => 'Netherlands',
			'NO' => 'Norway',
			'NP' => 'Nepal',
			'NR' => 'Nauru',
			'NU' => 'Niue',
			'NZ' => 'New Zealand',
			'OM' => 'Oman',
			'PA' => 'Panama',
			'PE' => 'Peru',
			'PF' => 'French Polynesia',
			'PG' => 'Papua New Guinea',
			'PH' => 'Philippines',
			'PK' => 'Pakistan',
			'PL' => 'Poland',
			'PR' => 'Puerto Rico',
			'PS' => 'Palestine',
			'PT' => 'Portugal',
			'PW' => 'Palau',
			'PY' => 'Paraguay',
			'QA' => 'Qatar',
			'RO' => 'Romania',
			'RS' => 'Serbia',
			'RU' => 'Russia',
			'RW' => 'Rwanda',
			'SA' => 'Saudi Arabia',
			'SB' => 'Solomon Islands',
			'SC' => 'Seychelles',
			'SD' => 'Sudan',
			'SE' => 'Sweden',
			'SG' => 'Singapore',
			'SI' => 'Slovenia',
			'SJ' => 'Svalbard and Jan Mayen',
			'SK' => 'Slovakia',
			'SL' => 'Sierra Leone',
			'SM' => 'San Marino',
			'SN' => 'Senegal',
			'SO' => 'Somalia',
			'SR' => 'Suriname',
			'SS' => 'South Sudan',
			'ST' => 'São Tomé and Príncipe',
			'SV' => 'El Salvador',
			'SY' => 'Syria',
			'SZ' => 'Eswatini',
			'TD' => 'Chad',
			'TG' => 'Togo',
			'TH' => 'Thailand',
			'TJ' => 'Tajikistan',
			'TK' => 'Tokelau',
			'TL' => 'Timor-Leste',
			'TM' => 'Turkmenistan',
			'TN' => 'Tunisia',
			'TO' => 'Tonga',
			'TR' => 'Türkiye',
			'TT' => 'Trinidad and Tobago',
			'TV' => 'Tuvalu',
			'TW' => 'Taiwan',
			'TZ' => 'Tanzania',
			'UA' => 'Ukraine',
			'UG' => 'Uganda',
			'US' => 'United States',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VA' => 'Vatican City',
			'VC' => 'Saint Vincent and the Grenadines',
			'VE' => 'Venezuela',
			'VN' => 'Vietnam',
			'VU' => 'Vanuatu',
			'WS' => 'Samoa',
			'XK' => 'Kosovo',
			'YE' => 'Yemen',
			'ZA' => 'South Africa',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);
	}
}
