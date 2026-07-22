<?php
/**
 * Matrix field type — fixed rows × radio/checkbox columns.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Fields;

use Webentwicklerin\WeFormkit\Validation_Messages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table-style matrix (e.g. Sehhilfen: row select + usage radio + tolerance radio + “new”).
 */
class Matrix_Field extends Abstract_Field_Type {

	public const CUSTOM_ROW_PREFIX = 'custom_';

	public const MAX_CUSTOM_ROWS_CAP = 5;

	public function get_type(): string {
		return 'matrix';
	}

	public function get_label(): string {
		return __( 'Matrix', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'row_select'        => array(
				'label'       => __( 'Row select checkbox', 'we-formkit' ),
				'type'        => 'boolean',
				'description' => __( 'Show a checkbox to mark each row as selected.', 'we-formkit' ),
				'default'     => true,
			),
			'row_label_align'   => array(
				'label'   => __( 'Row label alignment', 'we-formkit' ),
				'type'    => 'select',
				'options' => array(
					'left'   => __( 'Left', 'we-formkit' ),
					'center' => __( 'Center', 'we-formkit' ),
					'right'  => __( 'Right', 'we-formkit' ),
				),
				'default' => 'left',
			),
			'entries_label'     => array(
				'label'       => __( 'Entries label', 'we-formkit' ),
				'type'        => 'text',
				'description' => __( 'Optional header for the row labels column.', 'we-formkit' ),
				'default'     => '',
			),
			'allow_custom_rows' => array(
				'label'       => __( 'Allow visitor-added rows', 'we-formkit' ),
				'type'        => 'boolean',
				'description' => __( 'Visitors can add their own rows with a typed label (e.g. instead of a static “Other” row).', 'we-formkit' ),
				'default'     => false,
			),
			'max_custom_rows'   => array(
				'label'   => __( 'Max custom rows', 'we-formkit' ),
				'type'    => 'number',
				'default' => 2,
			),
			'rows'              => array(
				'label' => __( 'Rows', 'we-formkit' ),
				'type'  => 'matrix_rows',
			),
			'columns'           => array(
				'label' => __( 'Columns', 'we-formkit' ),
				'type'  => 'matrix_columns',
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$opts  = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();

		$field['type_options']['row_select']        = ! array_key_exists( 'row_select', $opts ) || ! empty( $opts['row_select'] );
		$field['type_options']['row_label_align']   = self::normalize_row_label_align( $opts['row_label_align'] ?? 'left' );
		$field['type_options']['entries_label']     = isset( $opts['entries_label'] ) ? sanitize_text_field( (string) $opts['entries_label'] ) : '';
		$field['type_options']['allow_custom_rows'] = ! empty( $opts['allow_custom_rows'] );
		$field['type_options']['max_custom_rows']   = self::normalize_max_custom_rows( $opts['max_custom_rows'] ?? 2 );
		$field['type_options']['rows']              = $this->normalize_rows( $opts['rows'] ?? array() );
		$field['type_options']['columns']           = $this->normalize_columns( $opts['columns'] ?? array() );

		return $field;
	}

	/**
	 * @param mixed $raw Alignment token.
	 * @return string left|center|right
	 */
	public static function normalize_row_label_align( $raw ): string {
		$align = sanitize_key( (string) $raw );
		return in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'left';
	}

	/**
	 * @param mixed $raw Max custom rows.
	 */
	public static function normalize_max_custom_rows( $raw ): int {
		$n = absint( $raw );
		if ( $n < 1 ) {
			$n = 2;
		}
		return min( self::MAX_CUSTOM_ROWS_CAP, $n );
	}

	/**
	 * Whether a row id is a visitor-added custom row key.
	 *
	 * @param string $row_id Row id.
	 */
	public static function is_custom_row_id( $row_id ): bool {
		$row_id = (string) $row_id;
		return (bool) preg_match( '/^' . preg_quote( self::CUSTOM_ROW_PREFIX, '/' ) . '[a-z0-9]+$/', $row_id );
	}

	/**
	 * @param mixed $raw Raw rows.
	 * @return array<int, array{value:string,label:string}>
	 */
	private function normalize_rows( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out   = array();
		$seen  = array();
		$index = 0;
		foreach ( $raw as $row ) {
			++$index;
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$value = isset( $row['value'] ) ? sanitize_key( (string) $row['value'] ) : '';
			if ( '' === $value ) {
				$value = sanitize_key( $label );
			}
			if ( '' === $value ) {
				$value = 'row_' . $index;
			}
			// Catalog rows must not collide with custom_* runtime ids.
			if ( self::is_custom_row_id( $value ) ) {
				$value = 'row_' . $index;
			}
			$base = $value;
			$n    = 2;
			while ( isset( $seen[ $value ] ) ) {
				$value = $base . '_' . $n;
				++$n;
			}
			$seen[ $value ] = true;
			if ( '' === $label ) {
				$label = $value;
			}
			$out[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * @param mixed $raw Raw columns.
	 * @return array<int, array{id:string,type:string,label:string,options:array<int,array{value:string,label:string}>}>
	 */
	private function normalize_columns( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out   = array();
		$seen  = array();
		$index = 0;
		foreach ( $raw as $col ) {
			++$index;
			if ( ! is_array( $col ) ) {
				continue;
			}
			$type = isset( $col['type'] ) ? sanitize_key( (string) $col['type'] ) : 'radio';
			if ( ! in_array( $type, array( 'radio', 'checkbox', 'text', 'number' ), true ) ) {
				$type = 'radio';
			}
			$label = isset( $col['label'] ) ? sanitize_text_field( (string) $col['label'] ) : '';
			$id    = isset( $col['id'] ) ? sanitize_key( (string) $col['id'] ) : '';
			if ( '' === $id ) {
				$id = sanitize_key( $label );
			}
			if ( '' === $id ) {
				$id = 'col_' . $index;
			}
			if ( 'on' === $id || 'label' === $id ) {
				$id = 'col_' . $index;
			}
			$base = $id;
			$n    = 2;
			while ( isset( $seen[ $id ] ) ) {
				$id = $base . '_' . $n;
				++$n;
			}
			$seen[ $id ] = true;

			$options = array();
			if ( 'radio' === $type ) {
				$options = $this->normalize_options_list( $col['options'] ?? array() );
			}

			$out[] = array(
				'id'      => $id,
				'type'    => $type,
				'label'   => '' !== $label ? $label : $id,
				'options' => $options,
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $field Field config.
	 * @return array{row_select:bool,row_label_align:string,entries_label:string,allow_custom_rows:bool,max_custom_rows:int,rows:array<int,array{value:string,label:string}>,columns:array<int,array{id:string,type:string,label:string,options:array}>}
	 */
	public static function config( array $field ): array {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		return array(
			'row_select'        => ! array_key_exists( 'row_select', $opts ) || ! empty( $opts['row_select'] ),
			'row_label_align'   => self::normalize_row_label_align( $opts['row_label_align'] ?? 'left' ),
			'entries_label'     => isset( $opts['entries_label'] ) ? sanitize_text_field( (string) $opts['entries_label'] ) : '',
			'allow_custom_rows' => ! empty( $opts['allow_custom_rows'] ),
			'max_custom_rows'   => self::normalize_max_custom_rows( $opts['max_custom_rows'] ?? 2 ),
			'rows'              => isset( $opts['rows'] ) && is_array( $opts['rows'] ) ? $opts['rows'] : array(),
			'columns'           => isset( $opts['columns'] ) && is_array( $opts['columns'] ) ? $opts['columns'] : array(),
		);
	}

	/**
	 * @param array<string, mixed> $field Field config.
	 * @return array<string, string> Row value => label.
	 */
	public static function row_label_map( array $field ): array {
		$map = array();
		foreach ( self::config( $field )['rows'] as $row ) {
			if ( ! empty( $row['value'] ) ) {
				$map[ (string) $row['value'] ] = (string) ( $row['label'] ?? $row['value'] );
			}
		}
		return $map;
	}

	/**
	 * Display label for a stored row (catalog or custom).
	 *
	 * @param string               $row_id  Row id.
	 * @param array<string, mixed> $row_val Sanitized row value.
	 * @param array<string, mixed> $field   Field config.
	 */
	public static function display_row_label( $row_id, array $row_val, array $field ): string {
		$row_id = (string) $row_id;
		if ( self::is_custom_row_id( $row_id ) ) {
			$label = isset( $row_val['label'] ) ? trim( (string) $row_val['label'] ) : '';
			return '' !== $label ? $label : $row_id;
		}
		$map = self::row_label_map( $field );
		return $map[ $row_id ] ?? $row_id;
	}

	/**
	 * @param array<string, mixed> $field Field config.
	 * @return array<string, array{type:string,label:string,options:array<string,string>}>
	 */
	public static function column_map( array $field ): array {
		$map = array();
		foreach ( self::config( $field )['columns'] as $col ) {
			$id = (string) ( $col['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$opt_map = array();
			foreach ( $col['options'] ?? array() as $opt ) {
				if ( ! is_array( $opt ) || empty( $opt['value'] ) ) {
					continue;
				}
				$opt_map[ (string) $opt['value'] ] = (string) ( $opt['label'] ?? $opt['value'] );
			}
			$map[ $id ] = array(
				'type'    => (string) ( $col['type'] ?? 'radio' ),
				'label'   => (string) ( $col['label'] ?? $id ),
				'options' => $opt_map,
			);
		}
		return $map;
	}

	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$cfg      = self::config( $field );
		$row_ids  = array_column( $cfg['rows'], 'value' );
		$col_map  = self::column_map( $field );
		$out      = array();
		$customs  = 0;
		$max_cust = $cfg['allow_custom_rows'] ? $cfg['max_custom_rows'] : 0;

		foreach ( $value as $row_id => $row_val ) {
			$row_id = sanitize_key( (string) $row_id );
			if ( '' === $row_id || ! is_array( $row_val ) ) {
				continue;
			}

			$is_custom = self::is_custom_row_id( $row_id );
			if ( $is_custom ) {
				if ( $customs >= $max_cust ) {
					continue;
				}
			} elseif ( ! in_array( $row_id, $row_ids, true ) ) {
				continue;
			}

			$clean = $this->sanitize_row_cells( $row_val, $cfg, $col_map, $is_custom );
			if ( null === $clean ) {
				continue;
			}

			if ( $is_custom ) {
				++$customs;
			}
			$out[ $row_id ] = $clean;
		}

		return $out;
	}

	/**
	 * Sanitize one matrix row; return null to drop.
	 *
	 * @param array<string, mixed>                                                          $row_val   Raw row.
	 * @param array<string, mixed>                                                          $cfg       Matrix config.
	 * @param array<string, array{type:string,label:string,options:array<string,string>}> $col_map   Columns.
	 * @param bool                                                                          $is_custom Whether custom row.
	 * @return array<string, mixed>|null
	 */
	private function sanitize_row_cells( array $row_val, array $cfg, array $col_map, $is_custom ) {
		$clean = array();
		if ( $is_custom ) {
			$label = isset( $row_val['label'] ) ? sanitize_text_field( (string) $row_val['label'] ) : '';
			if ( '' !== $label ) {
				$clean['label'] = $label;
			}
		}

		if ( $cfg['row_select'] ) {
			$clean['on'] = ! empty( $row_val['on'] ) && '0' !== (string) $row_val['on'];
			if ( empty( $clean['on'] ) ) {
				return null;
			}
		}

		foreach ( $col_map as $col_id => $col ) {
			if ( ! array_key_exists( $col_id, $row_val ) ) {
				continue;
			}
			$raw = $row_val[ $col_id ];
			if ( 'checkbox' === $col['type'] ) {
				$clean[ $col_id ] = ! empty( $raw ) && '0' !== (string) $raw;
				continue;
			}
			if ( 'text' === $col['type'] ) {
				$text = sanitize_text_field( (string) $raw );
				if ( '' !== $text ) {
					$clean[ $col_id ] = $text;
				}
				continue;
			}
			if ( 'number' === $col['type'] ) {
				$num = is_numeric( $raw ) ? (string) $raw : trim( (string) $raw );
				if ( '' === $num || ! is_numeric( $num ) ) {
					continue;
				}
				$clean[ $col_id ] = $num;
				continue;
			}
			$choice = sanitize_key( (string) $raw );
			if ( '' === $choice || ! isset( $col['options'][ $choice ] ) ) {
				continue;
			}
			$clean[ $col_id ] = $choice;
		}

		$has_cells = ! self::row_answers_empty( $clean, $col_map );
		$has_label = ! empty( $clean['label'] );

		if ( $is_custom ) {
			// Drop empty custom rows; keep incomplete ones so validate can require a label.
			if ( ! $has_label && ! $has_cells ) {
				return null;
			}
			return $clean;
		}

		if ( empty( $clean ) ) {
			return null;
		}
		if ( ! $cfg['row_select'] && ! $has_cells ) {
			return null;
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed>                                                          $clean   Sanitized row.
	 * @param array<string, array{type:string,label:string,options:array<string,string>}> $col_map Columns.
	 */
	private static function row_answers_empty( array $clean, array $col_map ): bool {
		foreach ( $col_map as $col_id => $col ) {
			if ( ! array_key_exists( $col_id, $clean ) ) {
				continue;
			}
			if ( 'checkbox' === $col['type'] ) {
				if ( ! empty( $clean[ $col_id ] ) ) {
					return false;
				}
				continue;
			}
			if ( '' !== (string) $clean[ $col_id ] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a stored matrix value has at least one active / answered row.
	 *
	 * @param mixed                $value Sanitized value.
	 * @param array<string, mixed> $field Field config.
	 */
	public static function has_answer( $value, array $field ): bool {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return false;
		}
		$cfg     = self::config( $field );
		$col_map = self::column_map( $field );
		foreach ( $value as $row_id => $row_val ) {
			if ( ! is_array( $row_val ) ) {
				continue;
			}
			if ( self::is_custom_row_id( (string) $row_id ) && ! empty( $row_val['label'] ) ) {
				return true;
			}
			if ( $cfg['row_select'] && ! empty( $row_val['on'] ) ) {
				return true;
			}
			if ( ! self::row_answers_empty( $row_val, $col_map ) ) {
				return true;
			}
		}
		return false;
	}

	public function validate( $value, array $field ) {
		$data = is_array( $value ) ? $value : array();

		if ( ! empty( $field['required'] ) && ! self::has_answer( $data, $field ) ) {
			return new \WP_Error(
				'we_formkit_matrix_required',
				Validation_Messages::required_for_field( $field )
			);
		}

		$col_map = self::column_map( $field );
		$cfg     = self::config( $field );

		foreach ( $data as $row_id => $row_val ) {
			if ( ! is_array( $row_val ) ) {
				continue;
			}

			if ( self::is_custom_row_id( (string) $row_id ) ) {
				$label     = isset( $row_val['label'] ) ? trim( (string) $row_val['label'] ) : '';
				$has_cells = ! self::row_answers_empty( $row_val, $col_map );
				$selected  = ! empty( $cfg['row_select'] ) && ! empty( $row_val['on'] );
				if ( ( $selected || $has_cells ) && '' === $label ) {
					return new \WP_Error(
						'we_formkit_matrix_custom_label',
						sprintf(
							/* translators: %s: field label. */
							__( '%s: please enter a label for each added row.', 'we-formkit' ),
							(string) ( $field['label'] ?? '' )
						)
					);
				}
			}

			foreach ( $col_map as $col_id => $col ) {
				if ( ! array_key_exists( $col_id, $row_val ) ) {
					continue;
				}
				if ( 'radio' === $col['type'] ) {
					$choice = (string) $row_val[ $col_id ];
					if ( '' !== $choice && ! isset( $col['options'][ $choice ] ) ) {
						return new \WP_Error(
							'we_formkit_matrix_invalid',
							Validation_Messages::invalid_for_field( $field )
						);
					}
				}
				if ( 'number' === $col['type'] ) {
					$num = (string) $row_val[ $col_id ];
					if ( '' !== $num && ! is_numeric( $num ) ) {
						return new \WP_Error(
							'we_formkit_matrix_invalid',
							Validation_Messages::invalid_for_field( $field )
						);
					}
				}
			}
		}

		return true;
	}

	public function is_empty_value( $value ): bool {
		return ! is_array( $value ) || empty( $value );
	}

	public function format_for_display( $value, array $field ): string {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return '';
		}

		$col_map = self::column_map( $field );
		$cfg     = self::config( $field );
		$lines   = array();

		// Preserve submission order: fixed catalog order first, then remaining custom keys.
		$ordered_ids = array();
		foreach ( $cfg['rows'] as $row ) {
			$rid = (string) ( $row['value'] ?? '' );
			if ( '' !== $rid && isset( $value[ $rid ] ) ) {
				$ordered_ids[] = $rid;
			}
		}
		foreach ( $value as $row_id => $_row_val ) {
			$row_id = (string) $row_id;
			if ( ! in_array( $row_id, $ordered_ids, true ) ) {
				$ordered_ids[] = $row_id;
			}
		}

		foreach ( $ordered_ids as $row_id ) {
			if ( ! isset( $value[ $row_id ] ) || ! is_array( $value[ $row_id ] ) ) {
				continue;
			}
			$row_val = $value[ $row_id ];
			$parts   = array();
			if ( $cfg['row_select'] ) {
				if ( empty( $row_val['on'] ) && self::row_answers_empty( $row_val, $col_map ) ) {
					continue;
				}
				$parts[] = ! empty( $row_val['on'] ) ? __( 'selected', 'we-formkit' ) : __( 'not selected', 'we-formkit' );
			} elseif ( self::row_answers_empty( $row_val, $col_map ) && empty( $row_val['label'] ) ) {
				continue;
			}

			foreach ( $col_map as $col_id => $col ) {
				if ( ! array_key_exists( $col_id, $row_val ) ) {
					continue;
				}
				if ( 'checkbox' === $col['type'] ) {
					if ( empty( $row_val[ $col_id ] ) ) {
						continue;
					}
					$parts[] = $col['label'];
					continue;
				}
				$cell = (string) $row_val[ $col_id ];
				if ( '' === $cell ) {
					continue;
				}
				if ( 'radio' === $col['type'] ) {
					$opt_label = $col['options'][ $cell ] ?? $cell;
					$parts[]   = $col['label'] . ': ' . $opt_label;
					continue;
				}
				$parts[] = $col['label'] . ': ' . $cell;
			}

			if ( empty( $parts ) && empty( $row_val['label'] ) ) {
				continue;
			}
			$title   = self::display_row_label( $row_id, $row_val, $field );
			$lines[] = esc_html( $title . ( empty( $parts ) ? '' : ' — ' . implode( '; ', $parts ) ) );
		}

		return implode( '<br />', $lines );
	}
}
