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

	public const PRESET_THEME  = 'theme';
	public const PRESET_CUSTOM = 'custom';

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
			'input'       => __( 'Input fill', 'we-formkit' ),
			'on_accent'   => __( 'Button text', 'we-formkit' ),
			'danger'      => __( 'Errors', 'we-formkit' ),
		);
	}

	/**
	 * Named built-in schemes (preset slug => label + palette).
	 * Theme and Custom are separate; Formkit Teal is the first named scheme.
	 *
	 * @return array<string, array{label:string,colors:array<string,string>}>
	 */
	public static function named_schemes(): array {
		return array(
			'formkit'       => array(
				'label'  => __( 'Formkit Teal', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#0f5c4c',
					'accent_soft' => '#e4f2ee',
					'surface'     => '#ffffff',
					'bg'          => '#f7f5f2',
					'ink'         => '#1c1b19',
					'muted'       => '#5c574f',
					'line'        => '#d9d3c8',
					'input'       => '#ffffff',
					'on_accent'   => '#ffffff',
					'danger'      => '#8a1f1f',
				),
			),
			'slate-harbor'  => array(
				'label'  => __( 'Slate Harbor', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#1e4a6e',
					'accent_soft' => '#e6eef5',
					'surface'     => '#ffffff',
					'bg'          => '#f3f5f8',
					'ink'         => '#152033',
					'muted'       => '#5a6578',
					'line'        => '#c9d2de',
					'input'       => '#ffffff',
					'on_accent'   => '#ffffff',
					'danger'      => '#9b2c2c',
				),
			),
			'forest-moss'   => array(
				'label'  => __( 'Forest Moss', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#355e3b',
					'accent_soft' => '#e8f0e9',
					'surface'     => '#ffffff',
					'bg'          => '#f4f6f2',
					'ink'         => '#1a2219',
					'muted'       => '#5a6458',
					'line'        => '#cfd6ca',
					'input'       => '#ffffff',
					'on_accent'   => '#ffffff',
					'danger'      => '#8b2e2e',
				),
			),
			'warm-brick'    => array(
				'label'  => __( 'Warm Brick', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#9a3412',
					'accent_soft' => '#f6ebe6',
					'surface'     => '#ffffff',
					'bg'          => '#f6f3f0',
					'ink'         => '#271c18',
					'muted'       => '#6b5750',
					'line'        => '#ddd2cb',
					'input'       => '#ffffff',
					'on_accent'   => '#ffffff',
					'danger'      => '#7f1d1d',
				),
			),
			'saffron-stone' => array(
				'label'  => __( 'Saffron Stone', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#92400e',
					'accent_soft' => '#fef3e8',
					'surface'     => '#ffffff',
					'bg'          => '#f7f5f2',
					'ink'         => '#1c1917',
					'muted'       => '#6f6760',
					'line'        => '#e0dbd4',
					'input'       => '#ffffff',
					'on_accent'   => '#ffffff',
					'danger'      => '#b91c1c',
				),
			),
			'graphite'      => array(
				'label'  => __( 'Graphite', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#2c3338',
					'accent_soft' => '#eceeef',
					'surface'     => '#ffffff',
					'bg'          => '#f2f3f4',
					'ink'         => '#16191b',
					'muted'       => '#5f676c',
					'line'        => '#d3d7da',
					'input'       => '#ffffff',
					'on_accent'   => '#ffffff',
					'danger'      => '#b42318',
				),
			),
			'plum-ink'      => array(
				'label'  => __( 'Plum Ink', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#5b3a6e',
					'accent_soft' => '#f0e8f4',
					'surface'     => '#ffffff',
					'bg'          => '#f6f3f7',
					'ink'         => '#1f1824',
					'muted'       => '#6a5d73',
					'line'        => '#d8d0df',
					'input'       => '#ffffff',
					'on_accent'   => '#ffffff',
					'danger'      => '#8a1f3a',
				),
			),
			'midnight'      => array(
				'label'  => __( 'Midnight', 'we-formkit' ),
				'colors' => array(
					'accent'      => '#7dd3c0',
					'accent_soft' => '#1e2c2a',
					'surface'     => '#1a222c',
					'bg'          => '#11161d',
					'ink'         => '#e8ecf2',
					'muted'       => '#9aa3b2',
					'line'        => '#2e3848',
					'input'       => '#141b24',
					'on_accent'   => '#0d1a17',
					'danger'      => '#f07178',
				),
			),
		);
	}

	/**
	 * Preset keys allowed in storage (theme, named schemes, custom).
	 *
	 * @return list<string>
	 */
	public static function allowed_presets(): array {
		return array_merge(
			array( self::PRESET_THEME ),
			array_keys( self::named_schemes() ),
			array( self::PRESET_CUSTOM )
		);
	}

	/**
	 * Built-in Formkit teal palette (alias for the formkit named scheme).
	 *
	 * @return array<string, string>
	 */
	public static function formkit_defaults(): array {
		$schemes = self::named_schemes();
		return $schemes['formkit']['colors'];
	}

	/**
	 * Colors for a named scheme, or empty array if unknown.
	 *
	 * @param string $preset Preset slug.
	 * @return array<string, string>
	 */
	public static function scheme_colors( $preset ): array {
		$schemes = self::named_schemes();
		$preset  = sanitize_key( (string) $preset );
		if ( ! isset( $schemes[ $preset ] ) ) {
			return array();
		}
		return $schemes[ $preset ]['colors'];
	}

	/**
	 * Admin boot payload: schemes list for the picker.
	 *
	 * @return list<array{id:string,label:string,colors:array<string,string>}>
	 */
	public static function schemes_for_admin(): array {
		$out = array();
		foreach ( self::named_schemes() as $id => $scheme ) {
			$out[] = array(
				'id'     => $id,
				'label'  => $scheme['label'],
				'colors' => $scheme['colors'],
			);
		}
		return $out;
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
		if ( ! in_array( $preset, self::allowed_presets(), true ) ) {
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
		$preset = $stored['preset'];

		if ( self::PRESET_CUSTOM === $preset ) {
			$base = self::theme_defaults();
			foreach ( $stored['colors'] as $key => $hex ) {
				$base[ $key ] = $hex;
			}
		} elseif ( self::PRESET_THEME === $preset ) {
			$base = self::theme_defaults();
		} else {
			$scheme = self::scheme_colors( $preset );
			$base   = ! empty( $scheme ) ? $scheme : self::theme_defaults();
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
			'input'       => '--wek-input',
			'on_accent'   => '--wek-on-accent',
			'danger'      => '--wek-danger',
		);
		$parts  = array();
		foreach ( $map as $key => $var ) {
			if ( empty( $colors[ $key ] ) ) {
				continue;
			}
			$parts[] = $var . ':' . $colors[ $key ];
		}

		$font_css = self::resolve_font_family_css( $form_id );
		if ( '' !== $font_css ) {
			$parts[] = '--wek-font-family:' . $font_css;
		}

		return implode( ';', $parts );
	}

	/**
	 * CSS value for --wek-font-family, or empty to inherit the theme.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function resolve_font_family_css( $form_id ) {
		$appear = Form_Schema::get_appearance( $form_id );
		$slug   = isset( $appear['font_family'] ) ? (string) $appear['font_family'] : 'inherit';
		if ( 'inherit' === $slug || '' === $slug ) {
			return '';
		}

		$stack = '';
		foreach ( self::theme_font_families() as $font ) {
			if ( $slug === (string) $font['slug'] ) {
				$stack = (string) $font['fontFamily'];
				break;
			}
		}
		if ( '' === $stack ) {
			return '';
		}

		// Prefer the Global Styles preset variable; fall back to the theme.json stack.
		return 'var(--wp--preset--font-family--' . $slug . ',' . $stack . ')';
	}

	/**
	 * Font families from theme.json + Site Editor Font Library (merged global settings).
	 *
	 * @return list<array{slug:string,name:string,fontFamily:string}>
	 */
	public static function theme_font_families(): array {
		$out  = array();
		$seen = array();

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return $out;
		}

		$raw = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		if ( ! is_array( $raw ) ) {
			return $out;
		}

		$groups = array();
		if ( isset( $raw['theme'] ) || isset( $raw['custom'] ) || isset( $raw['default'] ) ) {
			foreach ( array( 'theme', 'custom', 'default' ) as $origin ) {
				if ( ! empty( $raw[ $origin ] ) && is_array( $raw[ $origin ] ) ) {
					$groups[] = $raw[ $origin ];
				}
			}
		} else {
			$groups[] = $raw;
		}

		foreach ( $groups as $group ) {
			foreach ( $group as $font ) {
				if ( ! is_array( $font ) ) {
					continue;
				}
				$slug  = isset( $font['slug'] ) ? (string) $font['slug'] : '';
				$name  = isset( $font['name'] ) ? sanitize_text_field( (string) $font['name'] ) : '';
				$stack = isset( $font['fontFamily'] ) ? trim( (string) $font['fontFamily'] ) : '';
				if ( '' === $slug || '' === $stack || isset( $seen[ $slug ] ) ) {
					continue;
				}
				if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/i', $slug ) ) {
					continue;
				}
				$seen[ $slug ] = true;
				if ( '' === $name ) {
					$name = $slug;
				}
				$out[] = array(
					'slug'       => $slug,
					'name'       => $name,
					'fontFamily' => $stack,
				);
			}
		}

		return $out;
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
		$base['on_accent']   = '#ffffff';
		$base['input']       = '#ffffff';
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
