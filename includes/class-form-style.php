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
		$schemes = self::builtin_named_schemes();

		/**
		 * Filter named color schemes available in Form Settings.
		 *
		 * Return an associative array of scheme_id => [ 'label' => string, 'colors' => role => hex ].
		 * Color roles: accent, accent_soft, surface, bg, ink, muted, line, input, on_accent, danger.
		 * Slugs `theme` and `custom` are reserved.
		 *
		 * @param array<string, array{label:string,colors:array<string,string>}> $schemes Schemes.
		 */
		$schemes = apply_filters( 'we_formkit_color_schemes', $schemes );

		return self::normalize_named_schemes( $schemes );
	}

	/**
	 * Built-in schemes before the `we_formkit_color_schemes` filter.
	 *
	 * @return array<string, array{label:string,colors:array<string,string>}>
	 */
	private static function builtin_named_schemes(): array {
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
	 * @param mixed $schemes Raw schemes from filter.
	 * @return array<string, array{label:string,colors:array<string,string>}>
	 */
	private static function normalize_named_schemes( $schemes ): array {
		if ( ! is_array( $schemes ) ) {
			return self::builtin_named_schemes();
		}

		$roles = array_keys( self::color_labels() );
		$out   = array();

		foreach ( $schemes as $id => $scheme ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || self::PRESET_THEME === $id || self::PRESET_CUSTOM === $id ) {
				continue;
			}
			if ( ! is_array( $scheme ) ) {
				continue;
			}

			$label     = isset( $scheme['label'] ) ? sanitize_text_field( (string) $scheme['label'] ) : $id;
			$colors_in = isset( $scheme['colors'] ) && is_array( $scheme['colors'] ) ? $scheme['colors'] : array();
			$colors    = array();
			$complete  = true;

			foreach ( $roles as $role ) {
				$hex = self::sanitize_hex( $colors_in[ $role ] ?? '' );
				if ( '' === $hex ) {
					$complete = false;
					break;
				}
				$colors[ $role ] = $hex;
			}

			if ( ! $complete || '' === $label ) {
				continue;
			}

			$out[ $id ] = array(
				'label'  => $label,
				'colors' => $colors,
			);
		}

		return ! empty( $out ) ? $out : self::builtin_named_schemes();
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
		if ( isset( $schemes['formkit']['colors'] ) && is_array( $schemes['formkit']['colors'] ) ) {
			return $schemes['formkit']['colors'];
		}
		$first = reset( $schemes );
		if ( is_array( $first ) && isset( $first['colors'] ) && is_array( $first['colors'] ) ) {
			return $first['colors'];
		}
		$builtin = self::builtin_named_schemes();
		return $builtin['formkit']['colors'];
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
		$form_id    = (int) $form_id;
		$existing   = self::get( $form_id );
		$normalized = self::normalize( $style );

		if ( self::PRESET_CUSTOM === $normalized['preset'] ) {
			// Persist a fixed custom snapshot (not overwritten by named schemes).
			$normalized['custom_colors'] = $normalized['colors'];
		} else {
			$normalized['custom_colors'] = $existing['custom_colors'];
		}

		$json = wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		update_post_meta( $form_id, self::META, $json );
		return true;
	}

	/**
	 * @param array<string, mixed> $style Raw.
	 * @return array{preset:string,colors:array<string,string>,custom_colors:array<string,string>}
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

		$custom_colors = array();
		$custom_raw    = isset( $style['custom_colors'] ) && is_array( $style['custom_colors'] ) ? $style['custom_colors'] : array();
		foreach ( array_keys( self::color_labels() ) as $key ) {
			$hex = self::sanitize_hex( $custom_raw[ $key ] ?? '' );
			if ( '' !== $hex ) {
				$custom_colors[ $key ] = $hex;
			}
		}
		// Legacy: custom preset stored only in colors — seed custom_colors once.
		if ( empty( $custom_colors ) && self::PRESET_CUSTOM === $preset && ! empty( $colors ) ) {
			$custom_colors = $colors;
		}

		return array(
			'preset'        => $preset,
			'colors'        => $colors,
			'custom_colors' => $custom_colors,
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
			$base = self::formkit_defaults();
			$from = ! empty( $stored['custom_colors'] ) ? $stored['custom_colors'] : $stored['colors'];
			foreach ( $from as $key => $hex ) {
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

		$appear_css = Form_Schema::appearance_css_variables( $form_id );
		if ( '' !== $appear_css ) {
			$parts[] = $appear_css;
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
			$base = self::formkit_defaults();
			$from = ! empty( $stored['custom_colors'] ) ? $stored['custom_colors'] : $stored['colors'];
			return array_merge( $base, $from );
		}
		return self::resolve( $form_id );
	}

	/**
	 * Last saved custom palette (may be empty).
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	public static function saved_custom_colors( $form_id ): array {
		$stored = self::get( $form_id );
		if ( ! empty( $stored['custom_colors'] ) ) {
			return array_merge( self::formkit_defaults(), $stored['custom_colors'] );
		}
		if ( self::PRESET_CUSTOM === $stored['preset'] && ! empty( $stored['colors'] ) ) {
			return array_merge( self::formkit_defaults(), $stored['colors'] );
		}
		return array();
	}

	/**
	 * Theme-derived defaults (contrast-aware palette import when available).
	 *
	 * @return array<string, string>
	 */
	public static function theme_defaults(): array {
		$palette = self::theme_color_palette();
		if ( empty( $palette ) ) {
			return self::formkit_defaults();
		}
		return self::import_from_theme( 'auto' );
	}

	/**
	 * Build a complete Formkit palette from the active theme colors.
	 *
	 * Mapped roles use contrast heuristics. Gaps are filled from a named scheme
	 * (`auto` picks the scheme whose accent is closest in hue to the theme accent).
	 *
	 * @param string $fill_from `auto` or a named scheme id.
	 * @return array<string, string>
	 */
	public static function import_from_theme( $fill_from = 'auto' ): array {
		$palette = self::normalize_palette_entries( self::theme_color_palette() );
		$global  = self::theme_global_colors();
		if ( ! empty( $global['background'] ) ) {
			$palette[] = array(
				'slug'  => 'global-background',
				'name'  => 'Background',
				'color' => $global['background'],
				'hex'   => $global['background'],
				'lum'   => self::relative_luminance( $global['background'] ),
			);
		}
		if ( ! empty( $global['text'] ) ) {
			$palette[] = array(
				'slug'  => 'global-text',
				'name'  => 'Text',
				'color' => $global['text'],
				'hex'   => $global['text'],
				'lum'   => self::relative_luminance( $global['text'] ),
			);
		}

		$mapped = self::map_palette_to_roles( $palette );
		$fill   = self::resolve_fill_scheme_colors( (string) $fill_from, $mapped );

		$out = $fill;
		foreach ( $mapped as $role => $hex ) {
			if ( is_string( $hex ) && '' !== $hex ) {
				$out[ $role ] = $hex;
			}
		}

		return self::unique_role_colors( self::ensure_contrast_roles( $out ) );
	}

	/**
	 * Admin boot payload for theme import UI.
	 *
	 * @return array{hasPalette:bool,fills:list<array{id:string,label:string}>,byFill:array<string,array<string,string>>,palette:list<array{slug:string,name:string,color:string}>}
	 */
	public static function theme_import_for_admin(): array {
		$raw_palette = self::theme_color_palette();
		$fills       = array(
			array(
				'id'    => 'auto',
				'label' => __( 'Auto (closest scheme)', 'we-formkit' ),
			),
		);
		foreach ( self::named_schemes() as $id => $scheme ) {
			$fills[] = array(
				'id'    => $id,
				'label' => $scheme['label'],
			);
		}

		$by_fill = array();
		if ( ! empty( $raw_palette ) ) {
			foreach ( $fills as $fill ) {
				$by_fill[ $fill['id'] ] = self::import_from_theme( $fill['id'] );
			}
		}

		$swatches = array();
		foreach ( $raw_palette as $color ) {
			$hex = self::sanitize_hex( $color['color'] ?? '' );
			if ( '' === $hex ) {
				continue;
			}
			$swatches[] = array(
				'slug'  => (string) ( $color['slug'] ?? '' ),
				'name'  => (string) ( $color['name'] ?? '' ),
				'color' => $hex,
			);
		}

		return array(
			'hasPalette' => ! empty( $swatches ),
			'fills'      => $fills,
			'byFill'     => $by_fill,
			'palette'    => $swatches,
		);
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
					$hex = self::sanitize_hex( (string) $color['color'] );
					if ( '' === $hex ) {
						continue;
					}
					$out[] = array(
						'slug'  => isset( $color['slug'] ) ? sanitize_key( (string) $color['slug'] ) : '',
						'name'  => isset( $color['name'] ) ? sanitize_text_field( (string) $color['name'] ) : '',
						'color' => $hex,
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
					$hex = self::sanitize_hex( (string) $color['color'] );
					if ( '' === $hex ) {
						continue;
					}
					$out[] = array(
						'slug'  => isset( $color['slug'] ) ? sanitize_key( (string) $color['slug'] ) : '',
						'name'  => isset( $color['name'] ) ? sanitize_text_field( (string) $color['name'] ) : '',
						'color' => $hex,
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Global Styles text / background when set (hex only).
	 *
	 * @return array{background?:string,text?:string}
	 */
	public static function theme_global_colors(): array {
		$out = array();
		if ( ! function_exists( 'wp_get_global_styles' ) ) {
			return $out;
		}
		$styles = wp_get_global_styles( array( 'color' ) );
		if ( ! is_array( $styles ) ) {
			return $out;
		}
		if ( ! empty( $styles['background'] ) ) {
			$hex = self::sanitize_hex( (string) $styles['background'] );
			if ( '' !== $hex ) {
				$out['background'] = $hex;
			}
		}
		if ( ! empty( $styles['text'] ) ) {
			$hex = self::sanitize_hex( (string) $styles['text'] );
			if ( '' !== $hex ) {
				$out['text'] = $hex;
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
				$hex = self::sanitize_hex( $color['color'] ?? ( $color['hex'] ?? '' ) );
				if ( ( $color['slug'] ?? '' ) === $slug && '' !== $hex ) {
					return $hex;
				}
			}
		}
		foreach ( $palette as $color ) {
			$hex = self::sanitize_hex( $color['color'] ?? ( $color['hex'] ?? '' ) );
			if ( '' === $hex ) {
				continue;
			}
			$lum = self::relative_luminance( $hex );
			if ( $lum > 0.85 || $lum < 0.08 ) {
				continue;
			}
			return $hex;
		}
		return '#0f5c4c';
	}

	/**
	 * @param string $hex Hex color.
	 * @return string
	 */
	public static function soft_tint( $hex ): string {
		$hex = self::sanitize_hex( $hex );
		if ( '' === $hex ) {
			return '#e4f2ee';
		}
		$hex = ltrim( $hex, '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) );
		$g   = hexdec( substr( $hex, 2, 2 ) );
		$b   = hexdec( substr( $hex, 4, 2 ) );
		$r   = (int) round( $r * 0.12 + 255 * 0.88 );
		$g   = (int) round( $g * 0.12 + 255 * 0.88 );
		$b   = (int) round( $b * 0.12 + 255 * 0.88 );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * @param list<array{slug:string,name:string,color:string}> $palette Raw palette.
	 * @return list<array{slug:string,name:string,color:string,hex:string,lum:float}>
	 */
	private static function normalize_palette_entries( array $palette ): array {
		$out = array();
		foreach ( $palette as $color ) {
			$hex = self::sanitize_hex( $color['color'] ?? '' );
			if ( '' === $hex ) {
				continue;
			}
			$out[] = array(
				'slug'  => isset( $color['slug'] ) ? (string) $color['slug'] : '',
				'name'  => isset( $color['name'] ) ? (string) $color['name'] : '',
				'color' => $hex,
				'hex'   => $hex,
				'lum'   => self::relative_luminance( $hex ),
			);
		}
		return $out;
	}

	/**
	 * Partial role map from palette heuristics (empty string = unmapped).
	 *
	 * @param list<array{slug:string,name:string,color:string,hex:string,lum:float}> $palette Palette.
	 * @return array<string, string>
	 */
	private static function map_palette_to_roles( array $palette ): array {
		$roles = array_fill_keys( array_keys( self::color_labels() ), '' );
		if ( empty( $palette ) ) {
			return $roles;
		}

		$by_slug = array(
			'bg'      => array( 'global-background', 'background', 'base', 'canvas', 'white', 'light', 'pale' ),
			'surface' => array( 'surface', 'paper', 'card', 'white', 'base' ),
			'input'   => array( 'white', 'surface', 'paper', 'input', 'field' ),
			'ink'     => array( 'global-text', 'foreground', 'contrast', 'text', 'ink', 'black', 'dark', 'heading' ),
			'muted'   => array( 'secondary', 'muted', 'subtle', 'gray', 'grey', 'tertiary', 'caption' ),
			'accent'  => array( 'primary', 'accent', 'brand', 'main', 'vivid', 'button' ),
			'line'    => array( 'border', 'line', 'stroke', 'divider', 'subtle', 'gray', 'grey' ),
			'danger'  => array( 'error', 'danger', 'alert', 'red', 'vivid-red', 'destructive' ),
		);

		foreach ( $by_slug as $role => $slugs ) {
			foreach ( $slugs as $slug ) {
				foreach ( $palette as $color ) {
					if ( ( $color['slug'] ?? '' ) === $slug && '' !== $color['hex'] ) {
						$roles[ $role ] = $color['hex'];
						break 2;
					}
				}
			}
		}

		$sorted = $palette;
		usort(
			$sorted,
			static function ( $a, $b ) {
				return $a['lum'] <=> $b['lum'];
			}
		);
		$lightest = end( $sorted );
		$darkest  = $sorted[0];

		if ( '' === $roles['bg'] && is_array( $lightest ) ) {
			$roles['bg'] = $lightest['hex'];
		}
		if ( '' === $roles['ink'] && is_array( $darkest ) ) {
			$roles['ink'] = $darkest['hex'];
		}

		if ( '' === $roles['surface'] ) {
			foreach ( array_reverse( $sorted ) as $color ) {
				if ( $color['hex'] !== $roles['bg'] && $color['lum'] >= 0.75 ) {
					$roles['surface'] = $color['hex'];
					break;
				}
			}
			if ( '' === $roles['surface'] ) {
				$roles['surface'] = $roles['bg'];
			}
		}

		if ( '' === $roles['input'] ) {
			$roles['input'] = '' !== $roles['surface'] ? $roles['surface'] : $roles['bg'];
		}

		if ( '' === $roles['accent'] ) {
			$roles['accent'] = self::pick_accent_color( $palette );
		}

		if ( '' === $roles['accent_soft'] ) {
			$soft = self::soft_tint( $roles['accent'] );
			foreach ( $palette as $color ) {
				if ( $color['lum'] >= 0.85 && $color['hex'] !== $roles['bg'] && $color['hex'] !== $roles['surface'] ) {
					$soft = $color['hex'];
					break;
				}
			}
			$roles['accent_soft'] = $soft;
		}

		if ( '' === $roles['muted'] ) {
			$bg_lum = '' !== $roles['bg'] ? self::relative_luminance( $roles['bg'] ) : 1.0;
			$best   = '';
			$best_d = PHP_FLOAT_MAX;
			foreach ( $palette as $color ) {
				if ( $color['hex'] === $roles['ink'] || $color['hex'] === $roles['accent'] ) {
					continue;
				}
				$target = $bg_lum > 0.5 ? 0.35 : 0.65;
				$d      = abs( $color['lum'] - $target );
				if ( $d < $best_d ) {
					$best_d = $d;
					$best   = $color['hex'];
				}
			}
			$roles['muted'] = $best;
		}

		if ( '' === $roles['line'] ) {
			foreach ( $palette as $color ) {
				if ( $color['lum'] >= 0.65 && $color['lum'] <= 0.9 ) {
					$roles['line'] = $color['hex'];
					break;
				}
			}
		}

		if ( '' === $roles['danger'] ) {
			foreach ( $palette as $color ) {
				$rgb = self::hex_to_rgb( $color['hex'] );
				if ( null === $rgb ) {
					continue;
				}
				if ( $rgb['r'] > 140 && $rgb['r'] > $rgb['g'] + 40 && $rgb['r'] > $rgb['b'] + 40 ) {
					$roles['danger'] = $color['hex'];
					break;
				}
			}
		}

		if ( '' === $roles['on_accent'] && '' !== $roles['accent'] ) {
			$roles['on_accent'] = self::best_on_color( $roles['accent'] );
		}

		return $roles;
	}

	/**
	 * @param string               $fill_from Fill key.
	 * @param array<string,string> $mapped    Partial mapped roles.
	 * @return array<string, string>
	 */
	private static function resolve_fill_scheme_colors( $fill_from, array $mapped ): array {
		$fill_from = sanitize_key( (string) $fill_from );
		if ( 'auto' === $fill_from || '' === $fill_from ) {
			$accent    = ! empty( $mapped['accent'] ) ? $mapped['accent'] : self::pick_accent_color( self::theme_color_palette() );
			$fill_from = self::closest_scheme_id( $accent );
		}
		$scheme = self::scheme_colors( $fill_from );
		return ! empty( $scheme ) ? $scheme : self::formkit_defaults();
	}

	/**
	 * Named scheme whose accent hue is closest to $accent.
	 *
	 * @param string $accent Hex.
	 * @return string
	 */
	private static function closest_scheme_id( $accent ): string {
		$target = self::hex_to_hsl( $accent );
		$best   = 'formkit';
		$best_d = PHP_FLOAT_MAX;
		foreach ( self::named_schemes() as $id => $scheme ) {
			$hsl = self::hex_to_hsl( $scheme['colors']['accent'] ?? '' );
			if ( null === $target || null === $hsl ) {
				continue;
			}
			$dh = abs( $target['h'] - $hsl['h'] );
			if ( $dh > 180 ) {
				$dh = 360 - $dh;
			}
			$d = $dh + abs( $target['s'] - $hsl['s'] ) * 0.25;
			if ( $d < $best_d ) {
				$best_d = $d;
				$best   = $id;
			}
		}
		return $best;
	}

	/**
	 * Guarantee readable ink / muted / button text.
	 *
	 * @param array<string, string> $colors Colors.
	 * @return array<string, string>
	 */
	private static function ensure_contrast_roles( array $colors ): array {
		$defaults = self::formkit_defaults();
		foreach ( array_keys( self::color_labels() ) as $key ) {
			if ( empty( $colors[ $key ] ) ) {
				$colors[ $key ] = $defaults[ $key ];
			}
		}

		$bg  = $colors['bg'];
		$ink = $colors['ink'];
		if ( self::contrast_ratio( $ink, $bg ) < 4.5 ) {
			$colors['ink'] = self::contrast_ratio( '#1c1b19', $bg ) >= 4.5 ? '#1c1b19' : '#ffffff';
			if ( self::contrast_ratio( $colors['ink'], $bg ) < 4.5 ) {
				$colors['ink'] = self::best_on_color( $bg );
			}
		}

		if ( self::contrast_ratio( $colors['muted'], $bg ) < 4.5 ) {
			$colors['muted'] = self::mix_hex( $colors['ink'], $bg, 0.45 );
			if ( self::contrast_ratio( $colors['muted'], $bg ) < 4.5 ) {
				$colors['muted'] = $colors['ink'];
			}
		}

		foreach ( array( 'surface', 'input' ) as $role ) {
			if ( self::contrast_ratio( $colors['ink'], $colors[ $role ] ) < 4.5 ) {
				$colors[ $role ] = $bg;
			}
		}

		$colors['on_accent'] = self::best_on_color( $colors['accent'] );
		if ( self::contrast_ratio( $colors['on_accent'], $colors['accent'] ) < 4.5 ) {
			$colors['on_accent'] = self::contrast_ratio( '#ffffff', $colors['accent'] ) >= self::contrast_ratio( '#0d1a17', $colors['accent'] )
				? '#ffffff'
				: '#0d1a17';
		}

		if ( self::contrast_ratio( $colors['danger'], $bg ) < 3.0 ) {
			$colors['danger'] = $defaults['danger'];
		}

		return $colors;
	}

	/**
	 * Avoid identical hex values across roles (theme import / match site theme).
	 *
	 * @param array<string, string> $colors Colors.
	 * @return array<string, string>
	 */
	private static function unique_role_colors( array $colors ): array {
		$order = array( 'accent', 'ink', 'danger', 'on_accent', 'muted', 'line', 'accent_soft', 'bg', 'surface', 'input' );
		$used  = array();

		foreach ( $order as $role ) {
			if ( empty( $colors[ $role ] ) ) {
				continue;
			}
			$key = strtolower( (string) $colors[ $role ] );
			if ( ! isset( $used[ $key ] ) ) {
				$used[ $key ] = $role;
				continue;
			}
			$colors[ $role ] = self::nudge_duplicate_role( $role, $colors, $used );
			$nkey            = strtolower( (string) $colors[ $role ] );
			$guard           = 0;
			while ( isset( $used[ $nkey ] ) && $guard < 5 ) {
				$colors[ $role ] = self::mix_hex( $colors[ $role ], '#808080', 0.12 + ( 0.08 * $guard ) );
				$nkey            = strtolower( (string) $colors[ $role ] );
				++$guard;
			}
			$used[ $nkey ] = $role;
		}

		return $colors;
	}

	/**
	 * @param string                $role   Role key.
	 * @param array<string, string> $colors Full palette.
	 * @param array<string, string> $used   hex => role already claimed.
	 * @return string
	 */
	private static function nudge_duplicate_role( $role, array $colors, array $used ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- reserved for future conflict scoring.
		unset( $used );
		$accent  = $colors['accent'] ?? '#0f5c4c';
		$ink     = $colors['ink'] ?? '#1c1b19';
		$bg      = $colors['bg'] ?? '#f7f5f2';
		$surface = $colors['surface'] ?? '#ffffff';

		switch ( $role ) {
			case 'accent_soft':
				return self::soft_tint( $accent );
			case 'bg':
				return self::mix_hex( $surface, $ink, 0.06 );
			case 'surface':
				return self::mix_hex( $bg, '#ffffff', 0.65 );
			case 'input':
				return self::mix_hex( $surface, $ink, 0.03 );
			case 'line':
				return self::mix_hex( $bg, $ink, 0.18 );
			case 'muted':
				return self::mix_hex( $ink, $bg, 0.42 );
			case 'on_accent':
				$alt = ( '#ffffff' === strtolower( (string) ( $colors['on_accent'] ?? '' ) ) ) ? '#0d1a17' : '#ffffff';
				return $alt;
			case 'danger':
				return '#8a1f1f';
			default:
				return self::mix_hex( $colors[ $role ] ?? $accent, $ink, 0.2 );
		}
	}

	/**
	 * @param string $hex Hex.
	 * @return string White or near-black for best contrast.
	 */
	private static function best_on_color( $hex ): string {
		$white = self::contrast_ratio( '#ffffff', $hex );
		$black = self::contrast_ratio( '#0d1a17', $hex );
		return $white >= $black ? '#ffffff' : '#0d1a17';
	}

	/**
	 * Relative luminance (sRGB), 0–1.
	 *
	 * @param string $hex Hex color.
	 * @return float
	 */
	public static function relative_luminance( $hex ): float {
		$rgb = self::hex_to_rgb( $hex );
		if ( null === $rgb ) {
			return 0.0;
		}
		$chan = static function ( $c ) {
			$c = $c / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * $chan( $rgb['r'] ) + 0.7152 * $chan( $rgb['g'] ) + 0.0722 * $chan( $rgb['b'] );
	}

	/**
	 * WCAG contrast ratio.
	 *
	 * @param string $a Hex.
	 * @param string $b Hex.
	 * @return float
	 */
	public static function contrast_ratio( $a, $b ): float {
		$l1 = self::relative_luminance( $a );
		$l2 = self::relative_luminance( $b );
		$hi = max( $l1, $l2 );
		$lo = min( $l1, $l2 );
		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	/**
	 * @param string $hex Hex.
	 * @return array{r:int,g:int,b:int}|null
	 */
	private static function hex_to_rgb( $hex ) {
		$hex = self::sanitize_hex( $hex );
		if ( '' === $hex ) {
			return null;
		}
		$hex = ltrim( $hex, '#' );
		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * @param string $hex Hex.
	 * @return array{h:float,s:float,l:float}|null
	 */
	private static function hex_to_hsl( $hex ) {
		$rgb = self::hex_to_rgb( $hex );
		if ( null === $rgb ) {
			return null;
		}
		$r   = $rgb['r'] / 255;
		$g   = $rgb['g'] / 255;
		$b   = $rgb['b'] / 255;
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;
		$d   = $max - $min;
		if ( $d < 0.00001 ) {
			return array(
				'h' => 0.0,
				's' => 0.0,
				'l' => $l,
			);
		}
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
		if ( $max === $r ) {
			$h = ( ( $g - $b ) / $d ) + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( ( $b - $r ) / $d ) + 2;
		} else {
			$h = ( ( $r - $g ) / $d ) + 4;
		}
		$h *= 60;
		return array(
			'h' => $h,
			's' => $s,
			'l' => $l,
		);
	}

	/**
	 * Mix two hex colors (t = weight of $a).
	 *
	 * @param string $a Hex.
	 * @param string $b Hex.
	 * @param float  $t 0–1.
	 * @return string
	 */
	private static function mix_hex( $a, $b, $t ): string {
		$ra = self::hex_to_rgb( $a );
		$rb = self::hex_to_rgb( $b );
		if ( null === $ra || null === $rb ) {
			$fallback = self::sanitize_hex( $a );
			return '' !== $fallback ? $fallback : '#5c574f';
		}
		$t  = max( 0.0, min( 1.0, (float) $t ) );
		$r  = (int) round( $ra['r'] * $t + $rb['r'] * ( 1 - $t ) );
		$g  = (int) round( $ra['g'] * $t + $rb['g'] * ( 1 - $t ) );
		$bl = (int) round( $ra['b'] * $t + $rb['b'] * ( 1 - $t ) );
		return sprintf( '#%02x%02x%02x', $r, $g, $bl );
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
		if ( preg_match( '/^#([0-9a-fA-F]{3})$/', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
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
