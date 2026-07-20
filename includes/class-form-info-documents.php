<?php
/**
 * Per-form info documents with conditional delivery.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merchant documents: download links and/or email attachments, gated by conditions.
 */
final class Form_Info_Documents {

	public const META = '_wek_form_info_documents';

	/**
	 * @param int $form_id Form ID.
	 * @return list<array<string, mixed>>
	 */
	public static function get( $form_id ) {
		$form_id = (int) $form_id;
		$raw     = get_post_meta( $form_id, self::META, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize_list( $decoded );
			}
		}
		if ( is_array( $raw ) ) {
			return self::normalize_list( $raw );
		}

		return array();
	}

	/**
	 * @param int                        $form_id Form ID.
	 * @param list<array<string, mixed>> $items   Documents.
	 * @return void
	 */
	public static function save( $form_id, array $items ) {
		$form_id = (int) $form_id;
		$clean   = self::normalize_list( $items );
		update_post_meta( $form_id, self::META, wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function blank() {
		return self::normalize_one(
			array(
				'id'               => 'd_' . wp_generate_password( 8, false, false ),
				'name'             => __( 'New document', 'we-formkit' ),
				'enabled'          => true,
				'attachment_id'    => 0,
				'show_download'    => true,
				'attach_to_email'  => true,
				'notification_ids' => array( 'user' ),
				'when'             => null,
			)
		);
	}

	/**
	 * @param list<array<string, mixed>> $items Documents.
	 * @param string                     $id    Document ID.
	 * @return int|false
	 */
	public static function find_index( array $items, $id ) {
		$id = sanitize_key( (string) $id );
		foreach ( $items as $index => $item ) {
			if ( isset( $item['id'] ) && (string) $item['id'] === $id ) {
				return (int) $index;
			}
		}
		return false;
	}

	/**
	 * @param list<array<string, mixed>> $items Documents.
	 * @param string                     $id    Document ID.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_id( array $items, $id ) {
		$index = self::find_index( $items, $id );
		if ( false === $index ) {
			return null;
		}
		return $items[ $index ];
	}

	/**
	 * @param list<array<string, mixed>> $items Documents.
	 * @param array<string, mixed>       $one   Document.
	 * @return list<array<string, mixed>>
	 */
	public static function upsert( array $items, array $one ) {
		$one   = self::normalize_one( $one );
		$index = self::find_index( $items, (string) $one['id'] );
		if ( false === $index ) {
			$items[] = $one;
			return $items;
		}
		$items[ $index ] = $one;
		return $items;
	}

	/**
	 * @param list<array<string, mixed>> $items Documents.
	 * @param string                     $id    Document ID.
	 * @return list<array<string, mixed>>
	 */
	public static function remove_by_id( array $items, $id ) {
		$index = self::find_index( $items, $id );
		if ( false === $index ) {
			return $items;
		}
		array_splice( $items, $index, 1 );
		return $items;
	}

	/**
	 * @param list<array<string, mixed>> $items Documents.
	 * @param string                     $id    Document ID.
	 * @return list<array<string, mixed>>
	 */
	public static function toggle_enabled( array $items, $id ) {
		$index = self::find_index( $items, $id );
		if ( false === $index ) {
			return $items;
		}
		$items[ $index ]['enabled'] = empty( $items[ $index ]['enabled'] );
		return $items;
	}

	/**
	 * Matching docs for a submission (deduped by attachment ID).
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Submitted values.
	 * @return list<array<string, mixed>>
	 */
	public static function resolve_matching( $form_id, array $data ) {
		$matched  = array();
		$seen_ids = array();

		foreach ( self::get( $form_id ) as $doc ) {
			if ( empty( $doc['enabled'] ) ) {
				continue;
			}
			$attachment_id = (int) ( $doc['attachment_id'] ?? 0 );
			if ( $attachment_id <= 0 ) {
				continue;
			}
			if ( isset( $seen_ids[ $attachment_id ] ) ) {
				continue;
			}
			if ( ! Conditional::is_visible( $doc['when'] ?? null, $data ) ) {
				continue;
			}

			$url = wp_get_attachment_url( $attachment_id );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$path = get_attached_file( $attachment_id );
			if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
				$path = '';
			}

			$title = trim( (string) ( $doc['name'] ?? '' ) );
			if ( '' === $title ) {
				$post_title = get_the_title( $attachment_id );
				$title      = is_string( $post_title ) && '' !== $post_title ? $post_title : __( 'Document', 'we-formkit' );
			}

			$seen_ids[ $attachment_id ] = true;
			$matched[]                  = array(
				'id'               => (string) $doc['id'],
				'name'             => $title,
				'attachment_id'    => $attachment_id,
				'url'              => $url,
				'path'             => $path,
				'show_download'    => ! empty( $doc['show_download'] ),
				'attach_to_email'  => ! empty( $doc['attach_to_email'] ),
				'notification_ids' => isset( $doc['notification_ids'] ) && is_array( $doc['notification_ids'] ) ? $doc['notification_ids'] : array(),
			);
		}

		return $matched;
	}

	/**
	 * Download links for the on-page confirmation (deduped).
	 *
	 * @param list<array<string, mixed>> $matched Resolved docs.
	 * @return list<array{title:string,url:string}>
	 */
	public static function download_links( array $matched ) {
		$out = array();
		foreach ( $matched as $doc ) {
			if ( empty( $doc['show_download'] ) || empty( $doc['url'] ) ) {
				continue;
			}
			$out[] = array(
				'title' => (string) $doc['name'],
				'url'   => (string) $doc['url'],
			);
		}
		return $out;
	}

	/**
	 * Filesystem paths to attach to a given notification (deduped).
	 *
	 * @param list<array<string, mixed>> $matched         Resolved docs.
	 * @param string                     $notification_id Notification ID.
	 * @return list<string>
	 */
	public static function attachment_paths_for_notification( array $matched, $notification_id ) {
		$notification_id = sanitize_key( (string) $notification_id );
		$paths           = array();
		$seen            = array();

		foreach ( $matched as $doc ) {
			if ( empty( $doc['attach_to_email'] ) ) {
				continue;
			}
			$ids = isset( $doc['notification_ids'] ) && is_array( $doc['notification_ids'] ) ? $doc['notification_ids'] : array();
			if ( ! empty( $ids ) && ! in_array( $notification_id, $ids, true ) ) {
				continue;
			}
			$path = isset( $doc['path'] ) ? (string) $doc['path'] : '';
			if ( '' === $path || ! is_readable( $path ) ) {
				continue;
			}
			$key = wp_normalize_path( $path );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$paths[]      = $path;
		}

		return $paths;
	}

	/**
	 * Plain-text list of download URLs for mail bodies ({info_links}).
	 *
	 * @param list<array<string, mixed>> $matched Resolved docs.
	 * @return string
	 */
	public static function links_as_text( array $matched ) {
		$lines = array();
		foreach ( self::download_links( $matched ) as $link ) {
			$lines[] = $link['title'] . ': ' . $link['url'];
		}
		return implode( "\n", $lines );
	}

	/**
	 * HTML list of download links for mail bodies ({info_links}).
	 *
	 * @param list<array<string, mixed>> $matched Resolved docs.
	 * @return string
	 */
	public static function links_as_html( array $matched ) {
		$items = self::download_links( $matched );
		if ( empty( $items ) ) {
			return '';
		}
		$lis = array();
		foreach ( $items as $link ) {
			$lis[] = '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['title'] ) . '</a></li>';
		}
		return '<ul style="margin:0.5rem 0;padding-left:1.25rem;">' . implode( '', $lis ) . '</ul>';
	}

	/**
	 * @param list<array<string, mixed>> $items Raw list.
	 * @return list<array<string, mixed>>
	 */
	public static function normalize_list( array $items ) {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[] = self::normalize_one( $item );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function normalize_one( array $input ) {
		$id = sanitize_key( (string) ( $input['id'] ?? '' ) );
		if ( '' === $id ) {
			$id = 'd_' . wp_generate_password( 8, false, false );
		}

		$notification_ids = array();
		if ( isset( $input['notification_ids'] ) && is_array( $input['notification_ids'] ) ) {
			foreach ( $input['notification_ids'] as $nid ) {
				$nid = sanitize_key( (string) $nid );
				if ( '' !== $nid ) {
					$notification_ids[] = $nid;
				}
			}
		}
		$notification_ids = array_values( array_unique( $notification_ids ) );

		return array(
			'id'               => $id,
			'name'             => sanitize_text_field( (string) ( $input['name'] ?? __( 'Document', 'we-formkit' ) ) ),
			'enabled'          => ! empty( $input['enabled'] ),
			'attachment_id'    => absint( $input['attachment_id'] ?? 0 ),
			'show_download'    => ! empty( $input['show_download'] ),
			'attach_to_email'  => ! empty( $input['attach_to_email'] ),
			'notification_ids' => $notification_ids,
			'when'             => Form_Schema::normalize_condition( $input['when'] ?? null ),
		);
	}
}
