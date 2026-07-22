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

	public function get_type(): string {
		return 'matrix';
	}

	public function get_label(): string {
		return __( 'Matrix', 'we-formkit' );
	}

	public function get_admin_schema(): array {
		return array(
			'row_select' => array(
				'label'       => __( 'Row select checkbox', 'we-formkit' ),
				'type'        => 'boolean',
				'description' => __( 'Show a checkbox to mark each row as selected.', 'we-formkit' ),
				'default'     => true,
			),
			'rows'       => array(
				'label' => __( 'Rows', 'we-formkit' ),
				'type'  => 'matrix_rows',
			),
			'columns'    => array(
				'label' => __( 'Columns', 'we-formkit' ),
				'type'  => 'matrix_columns',
			),
		);
	}

	public function normalize_config( array $field ): array {
		$field = parent::normalize_config( $field );
		$opts  = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();

		$field['type_options']['row_select'] = ! array_key_exists( 'row_select', $opts ) || ! empty( $opts['row_select'] );
		$field['type_options']['rows']       = $this->normalize_rows( $opts['rows'] ?? array() );
		$field['type_options']['columns']    = $this->normalize_columns( $opts['columns'] ?? array() );

		return $field;
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
			if ( ! in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
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
			if ( 'on' === $id ) {
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
	 * @return array{row_select:bool,rows:array<int,array{value:string,label:string}>,columns:array<int,array{id:string,type:string,label:string,options:array}>}
	 */
	public static function config( array $field ): array {
		$opts = isset( $field['type_options'] ) && is_array( $field['type_options'] ) ? $field['type_options'] : array();
		return array(
			'row_select' => ! array_key_exists( 'row_select', $opts ) || ! empty( $opts['row_select'] ),
			'rows'       => isset( $opts['rows'] ) && is_array( $opts['rows'] ) ? $opts['rows'] : array(),
			'columns'    => isset( $opts['columns'] ) && is_array( $opts['columns'] ) ? $opts['columns'] : array(),
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

		$cfg     = self::config( $field );
		$row_ids = array_column( $cfg['rows'], 'value' );
		$col_map = self::column_map( $field );
		$out     = array();

		foreach ( $value as $row_id => $row_val ) {
			$row_id = sanitize_key( (string) $row_id );
			if ( '' === $row_id || ! in_array( $row_id, $row_ids, true ) || ! is_array( $row_val ) ) {
				continue;
			}

			$clean = array();
			if ( $cfg['row_select'] ) {
				$clean['on'] = ! empty( $row_val['on'] ) && '0' !== (string) $row_val['on'];
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
				$choice = sanitize_key( (string) $raw );
				if ( '' === $choice || ! isset( $col['options'][ $choice ] ) ) {
					continue;
				}
				$clean[ $col_id ] = $choice;
			}

			if ( empty( $clean ) ) {
				continue;
			}
			// Drop empty rows when row_select is off and nothing meaningful was chosen.
			if ( ! $cfg['row_select'] && self::row_answers_empty( $clean, $col_map ) ) {
				continue;
			}
			if ( $cfg['row_select'] && empty( $clean['on'] ) && self::row_answers_empty( $clean, $col_map ) ) {
				continue;
			}

			$out[ $row_id ] = $clean;
		}

		return $out;
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
		foreach ( $value as $row_val ) {
			if ( ! is_array( $row_val ) ) {
				continue;
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
		foreach ( $data as $row_val ) {
			if ( ! is_array( $row_val ) ) {
				continue;
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

		$row_labels = self::row_label_map( $field );
		$col_map    = self::column_map( $field );
		$cfg        = self::config( $field );
		$lines      = array();

		foreach ( $cfg['rows'] as $row ) {
			$row_id = (string) $row['value'];
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
			} elseif ( self::row_answers_empty( $row_val, $col_map ) ) {
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
				$choice = (string) $row_val[ $col_id ];
				if ( '' === $choice ) {
					continue;
				}
				$opt_label = $col['options'][ $choice ] ?? $choice;
				$parts[]   = $col['label'] . ': ' . $opt_label;
			}

			if ( empty( $parts ) ) {
				continue;
			}
			$lines[] = esc_html( ( $row_labels[ $row_id ] ?? $row_id ) . ' — ' . implode( '; ', $parts ) );
		}

		return implode( '<br />', $lines );
	}
}
