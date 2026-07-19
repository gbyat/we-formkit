<?php
/**
 * Per-form color style (presets + optional overrides).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves CSS color tokens for a form.
 */
final class Form_Style {

	public const META = '_wek_form_style';

	public const PRESET_THEME   = 'theme';
	public const PRESET_FORMKIT = 'formkit';
	public const PRESET_CUSTOM  = 'custom';

	/**
	 * Editable color roles (CSS variable suffix after --wek-).
	 *
	 * @return array<string, string> role => label
	 */
	public static function color_labels(): array {
		return array(
			'accent'      => __( 'Accent', 'we-formkit' ),
			'accent_soft' => __( 'Soft background', 'we-formkit' ),
			'surface'     => __( 'Surface', 'we-formkit' ),
			'bg'          => __( 'Background', 'we-formkit' ),
			'ink'         => __( 'Text', 'we-formkit' ),
			'muted'       => __( 'Muted text', 'we-formkit' ),
			'line'        => __( 'Borders', 'we-formkit' ),
			'danger'      => __( 'Errors', 'we-formkit' ),
		);
	}

	/**
	 * Built-in Formkit teal palette.
	 *
	 * @return array<string, string>
	 */
	public static function formkit_defaults(): array {
		return array(
			'accent'      => '#0f5c4c',
			'accent_soft' => '#e4f2ee',
			'surface'     => '#ffffff',
			'bg'          => '#f7f5f2',
			'ink'         => '#1c1b19',
			'muted'       => '#5c574f',
			'line'        => '#d9d3c8',
			'danger'      => '#8a1f1f',
		);
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array{preset:string,colors:array<string,string>}
	 */
	public static function get( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize( $decoded );
			}
		}
		if ( is_array( $raw ) ) {
			return self::normalize( $raw );
		}
		return self::normalize( array() );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $style   Style.
	 * @return bool
	 */
	public static function save( $form_id, array $style ) {
		$normalized = self::normalize( $style );
		$json       = wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		update_post_meta( (int) $form_id, self::META, $json );
		return true;
	}

	/**
	 * @param array<string, mixed> $style Raw.
	 * @return array{preset:string,colors:array<string,string>}
	 */
	public static function normalize( array $style ) {
		$preset = sanitize_key( (string) ( $style['preset'] ?? self::PRESET_THEME ) );
		if ( ! in_array( $preset, array( self::PRESET_THEME, self::PRESET_FORMKIT, self::PRESET_CUSTOM ), true ) ) {
			$preset = self::PRESET_THEME;
		}

		$colors = array();
		$raw    = isset( $style['colors'] ) && is_array( $style['colors'] ) ? $style['colors'] : array();
		foreach ( array_keys( self::color_labels() ) as $key ) {
			$hex = self::sanitize_hex( $raw[ $key ] ?? '' );
			if ( '' !== $hex ) {
				$colors[ $key ] = $hex;
			}
		}

		return array(
			'preset' => $preset,
			'colors' => $colors,
		);
	}

	/**
	 * Resolved colors for rendering (always complete).
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	public static function resolve( $form_id ) {
		$stored = self::get( $form_id );
		$base   = self::PRESET_FORMKIT === $stored['preset']
			? self::formkit_defaults()
			: self::theme_defaults();

		if ( self::PRESET_CUSTOM === $stored['preset'] ) {
			foreach ( $stored['colors'] as $key => $hex ) {
				$base[ $key ] = $hex;
			}
		}

		/**
		 * Filter resolved form colors.
		 *
		 * @param array<string, string> $colors  Resolved colors.
		 * @param int                   $form_id Form ID.
		 * @param array                 $stored  Stored style.
		 */
		return apply_filters( 'we_formkit_form_style_colors', $base, (int) $form_id, $stored );
	}

	/**
	 * Inline style attribute for the form root.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function css_variables_attr( $form_id ) {
		$colors = self::resolve( $form_id );
		$map    = array(
			'accent'      => '--wek-accent',
			'accent_soft' => '--wek-accent-soft',
			'surface'     => '--wek-surface',
			'bg'          => '--wek-bg',
			'ink'         => '--wek-ink',
			'muted'       => '--wek-muted',
			'line'        => '--wek-line',
			'danger'      => '--wek-danger',
		);
		$parts  = array();
		foreach ( $map as $key => $var ) {
			if ( empty( $colors[ $key ] ) ) {
				continue;
			}
			$parts[] = $var . ':' . $colors[ $key ];
		}
		return implode( ';', $parts );
	}

	/**
	 * Colors shown in the settings form (resolved for preset, stored for custom).
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	public static function editable_colors( $form_id ) {
		$stored = self::get( $form_id );
		if ( self::PRESET_CUSTOM === $stored['preset'] ) {
			return array_merge( self::theme_defaults(), $stored['colors'] );
		}
		return self::resolve( $form_id );
	}

	/**
	 * Theme-derived defaults (accent from theme.json when possible).
	 *
	 * @return array<string, string>
	 */
	public static function theme_defaults(): array {
		$base                = self::formkit_defaults();
		$accent              = self::pick_accent_color( self::theme_color_palette() );
		$base['accent']      = $accent;
		$base['accent_soft'] = self::soft_tint( $accent );
		return $base;
	}

	/**
	 * @return list<array{slug:string,name:string,color:string}>
	 */
	public static function theme_color_palette(): array {
		$out = array();

		if ( class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			$settings = \WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
			$sources  = array();
			if ( ! empty( $settings['color']['palette']['theme'] ) && is_array( $settings['color']['palette']['theme'] ) ) {
				$sources[] = $settings['color']['palette']['theme'];
			}
			if ( ! empty( $settings['color']['palette']['custom'] ) && is_array( $settings['color']['palette']['custom'] ) ) {
				$sources[] = $settings['color']['palette']['custom'];
			}
			foreach ( $sources as $group ) {
				foreach ( $group as $color ) {
					if ( empty( $color['color'] ) ) {
						continue;
					}
					$out[] = array(
						'slug'  => isset( $color['slug'] ) ? sanitize_key( (string) $color['slug'] ) : '',
						'name'  => isset( $color['name'] ) ? sanitize_text_field( (string) $color['name'] ) : '',
						'color' => sanitize_hex_color( (string) $color['color'] ) ? (string) $color['color'] : sanitize_text_field( (string) $color['color'] ),
					);
				}
			}
		}

		if ( empty( $out ) ) {
			$support = get_theme_support( 'editor-color-palette' );
			if ( is_array( $support ) && ! empty( $support[0] ) && is_array( $support[0] ) ) {
				foreach ( $support[0] as $color ) {
					if ( empty( $color['color'] ) ) {
						continue;
					}
					$out[] = array(
						'slug'  => isset( $color['slug'] ) ? sanitize_key( (string) $color['slug'] ) : '',
						'name'  => isset( $color['name'] ) ? sanitize_text_field( (string) $color['name'] ) : '',
						'color' => (string) $color['color'],
					);
				}
			}
		}

		return $out;
	}

	/**
	 * @param list<array{slug:string,name:string,color:string}> $palette Palette.
	 * @return string
	 */
	public static function pick_accent_color( array $palette ): string {
		$prefer = array( 'primary', 'accent', 'brand', 'main', 'secondary' );
		foreach ( $prefer as $slug ) {
			foreach ( $palette as $color ) {
				if ( ( $color['slug'] ?? '' ) === $slug && ! empty( $color['color'] ) ) {
					return (string) $color['color'];
				}
			}
		}
		foreach ( $palette as $color ) {
			$hex = strtolower( (string) ( $color['color'] ?? '' ) );
			if ( '' === $hex || preg_match( '/^#?(fff|ffffff|000|000000|f[5-9a-f]{5}|[0-3]{6})$/', $hex ) ) {
				continue;
			}
			return (string) $color['color'];
		}
		return '#0f5c4c';
	}

	/**
	 * @param string $hex Hex color.
	 * @return string
	 */
	public static function soft_tint( $hex ): string {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return '#e4f2ee';
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$r = (int) round( $r * 0.12 + 255 * 0.88 );
		$g = (int) round( $g * 0.12 + 255 * 0.88 );
		$b = (int) round( $b * 0.12 + 255 * 0.88 );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * @param mixed $value Raw color.
	 * @return string Hex or empty.
	 */
	private static function sanitize_hex( $value ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$sanitized = sanitize_hex_color( $value );
		return is_string( $sanitized ) ? $sanitized : '';
	}

	/**
	 * Sanitize style from POST.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{preset:string,colors:array<string,string>}
	 */
	public static function sanitize_from_request( array $input ): array {
		$preset = sanitize_key( (string) ( $input['preset'] ?? self::PRESET_THEME ) );
		$colors = isset( $input['colors'] ) && is_array( $input['colors'] ) ? $input['colors'] : array();
		return self::normalize(
			array(
				'preset' => $preset,
				'colors' => $colors,
			)
		);
	}
}
