<?php
/**
 * Bulk export of submissions as CSV or JSON.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams filtered submission exports.
 */
final class Submission_List_Export {

	/**
	 * Handle admin export request.
	 *
	 * @return void
	 */
	public static function maybe_export() {
		if ( ! is_admin() || empty( $_GET['wek_export_entries'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'edit_wek_submissions' ) && ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You cannot export entries.', 'we-formkit' ), 403 );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$format  = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( (string) $_GET['format'] ) ) : 'csv'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, 'wek_export_entries_' . $form_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ), 403 );
		}

		$entries = self::query_entries( $form_id );
		if ( 'json' === $format ) {
			self::stream_json( $form_id, $entries );
		}
		self::stream_csv( $form_id, $entries );
	}

	/**
	 * @param int $form_id Form ID (0 = all).
	 * @return list<\WP_Post>
	 */
	private static function query_entries( $form_id ) {
		$args = array(
			'post_type'      => Post_Types::SUBMISSION,
			'post_status'    => 'publish',
			'posts_per_page' => 2000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- export cap for admin CSV/JSON dump.
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( $form_id > 0 ) {
			$args['meta_key']   = Form_Schema::SUB_FORM_ID;
			$args['meta_value'] = (string) $form_id;
		}
		$query = new \WP_Query( $args );
		return is_array( $query->posts ) ? $query->posts : array();
	}

	/**
	 * @param int             $form_id Form ID.
	 * @param list<\WP_Post>  $entries Posts.
	 * @return void
	 */
	private static function stream_json( $form_id, array $entries ) {
		$out = array();
		foreach ( $entries as $post ) {
			$fid   = (int) get_post_meta( $post->ID, Form_Schema::SUB_FORM_ID, true );
			$raw   = (string) get_post_meta( $post->ID, Form_Schema::SUB_DATA, true );
			$data  = json_decode( $raw, true );
			$out[] = array(
				'id'      => (int) $post->ID,
				'form_id' => $fid,
				'date'    => get_post_time( 'c', true, $post ),
				'title'   => get_the_title( $post ),
				'answers' => is_array( $data ) ? $data : array(),
				'notes'   => (string) get_post_meta( $post->ID, Form_Schema::SUB_NOTES, true ),
			);
		}

		$filename = 'we-formkit-entries' . ( $form_id ? '-' . $form_id : '' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * @param int            $form_id Form ID.
	 * @param list<\WP_Post> $entries Posts.
	 * @return void
	 */
	private static function stream_csv( $form_id, array $entries ) {
		$field_ids    = array();
		$field_labels = array();

		if ( $form_id > 0 ) {
			$schema = Form_Schema::get( $form_id );
			foreach ( Form_Schema::fields_by_id( $schema ) as $fid => $field ) {
				if ( isset( $field['type'] ) && 'html' === $field['type'] ) {
					continue;
				}
				$field_ids[]          = $fid;
				$field_labels[ $fid ] = isset( $field['label'] ) ? (string) $field['label'] : $fid;
			}
		} else {
			foreach ( $entries as $post ) {
				$raw  = (string) get_post_meta( $post->ID, Form_Schema::SUB_DATA, true );
				$data = json_decode( $raw, true );
				if ( ! is_array( $data ) ) {
					continue;
				}
				foreach ( array_keys( $data ) as $fid ) {
					if ( ! in_array( $fid, $field_ids, true ) ) {
						$field_ids[]          = $fid;
						$field_labels[ $fid ] = $fid;
					}
				}
			}
		}

		$filename = 'we-formkit-entries' . ( $form_id ? '-' . $form_id : '' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $out ) {
			exit;
		}

		// UTF-8 BOM for Excel.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		$header = array_merge( array( 'id', 'form_id', 'date', 'title' ), $field_ids, array( 'notes' ) );
		fputcsv( $out, $header );

		foreach ( $entries as $post ) {
			$fid  = (int) get_post_meta( $post->ID, Form_Schema::SUB_FORM_ID, true );
			$raw  = (string) get_post_meta( $post->ID, Form_Schema::SUB_DATA, true );
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) {
				$data = array();
			}
			$row = array(
				(string) $post->ID,
				(string) $fid,
				get_post_time( 'Y-m-d H:i:s', false, $post ),
				get_the_title( $post ),
			);
			foreach ( $field_ids as $field_id ) {
				$row[] = self::flatten_value( isset( $data[ $field_id ] ) ? $data[ $field_id ] : '' );
			}
			$row[] = (string) get_post_meta( $post->ID, Form_Schema::SUB_NOTES, true );
			fputcsv( $out, $row );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function flatten_value( $value ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['token'] ) || isset( $value['attachment_id'] ) ) {
				return isset( $value['name'] ) ? (string) $value['name'] : wp_json_encode( $value );
			}
			$parts = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					$parts[] = isset( $item['name'] ) ? (string) $item['name'] : wp_json_encode( $item );
				} else {
					$parts[] = (string) $item;
				}
			}
			return implode( '; ', $parts );
		}
		return (string) $value;
	}
}
