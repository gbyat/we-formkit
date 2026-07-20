<?php
/**
 * Submission list and read-only entry view.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Form_Notifications;
use Webentwicklerin\WeFormkit\Form_Schema;
use Webentwicklerin\WeFormkit\Notifications;
use Webentwicklerin\WeFormkit\Plugin;
use Webentwicklerin\WeFormkit\Post_Types;
use Webentwicklerin\WeFormkit\Submission_Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submissions admin UI (Gravity-style tabs, search, read-only detail).
 */
final class Submissions {

	/**
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! Capabilities::can_manage() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'we-formkit-submissions' !== $page ) {
			return;
		}

		if ( isset( $_POST['we_formkit_save_notes'] ) ) {
			self::save_notes();
		}

		if ( isset( $_GET['wek_entry_action'] ) && isset( $_GET['submission_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			self::handle_entry_action();
		}

		if ( isset( $_GET['wek_delete_submission'] ) && isset( $_GET['submission_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$id = absint( $_GET['submission_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wek_delete_sub_' . $id ) ) {
				$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
				wp_trash_post( $id );
				wp_safe_redirect( self::list_redirect_url( $form_id, array( 'trashed' => 1 ) ) );
				exit;
			}
		}

		if ( isset( $_GET['wek_export_pdf'] ) && isset( $_GET['submission_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$id = absint( $_GET['submission_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wek_export_pdf_' . $id ) ) {
				Submission_Export::stream_print_document( $id );
			}
		}

		if ( isset( $_GET['wek_resend_mail'] ) && isset( $_GET['submission_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			self::handle_resend_mail();
		}
	}

	/**
	 * Trash / restore / spam / delete permanently / mark read.
	 *
	 * @return void
	 */
	private static function handle_entry_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = absint( $_GET['submission_id'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( wp_unslash( (string) $_GET['wek_entry_action'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		if ( ! wp_verify_nonce( $nonce, 'wek_entry_' . $action . '_' . $id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$post = get_post( $id );
		if ( ! $post || Post_Types::SUBMISSION !== $post->post_type ) {
			wp_die( esc_html__( 'Submission not found.', 'we-formkit' ) );
		}

		$notice = 'updated';
		switch ( $action ) {
			case 'trash':
				wp_trash_post( $id );
				$notice = 'trashed';
				break;
			case 'restore':
				wp_untrash_post( $id );
				$notice = 'restored';
				break;
			case 'delete':
				wp_delete_post( $id, true );
				$notice = 'deleted';
				break;
			case 'spam':
				update_post_meta( $id, Form_Schema::SUB_SPAM, 1 );
				$notice = 'spammed';
				break;
			case 'unspam':
				update_post_meta( $id, Form_Schema::SUB_SPAM, 0 );
				$notice = 'unspammed';
				break;
			case 'read':
				update_post_meta( $id, Form_Schema::SUB_READ, 1 );
				$notice = 'marked_read';
				break;
			case 'unread':
				update_post_meta( $id, Form_Schema::SUB_READ, 0 );
				$notice = 'marked_unread';
				break;
			default:
				wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$tab = 'all';
		if ( 'spam' === $action || 'spammed' === $notice ) {
			$tab = 'spam';
		} elseif ( 'trash' === $action || 'trashed' === $notice ) {
			$tab = 'trash';
		} elseif ( in_array( $action, array( 'unspam', 'restore', 'unread' ), true ) ) {
			$tab = 'all';
		}

		wp_safe_redirect(
			self::list_redirect_url(
				$form_id,
				array(
					$notice => 1,
					'tab'   => $tab,
				)
			)
		);
		exit;
	}

	/**
	 * @param int                  $form_id Form ID or 0.
	 * @param array<string, mixed> $extra   Query args.
	 * @return string
	 */
	private static function list_redirect_url( $form_id, array $extra = array() ) {
		$form_id = (int) $form_id;
		if ( $form_id > 0 ) {
			$args = array_merge(
				array(
					'page'    => 'we-formkit-form',
					'form_id' => $form_id,
					'view'    => 'entries',
				),
				$extra
			);
			return add_query_arg( $args, admin_url( 'admin.php' ) );
		}
		$args = array_merge(
			array(
				'page' => 'we-formkit-submissions',
			),
			$extra
		);
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Resend notification email(s) for a submission.
	 *
	 * @return void
	 */
	private static function handle_resend_mail() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$submission_id = absint( $_GET['submission_id'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notify_id = isset( $_GET['notification_id'] ) ? sanitize_key( wp_unslash( (string) $_GET['notification_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to_me = ! empty( $_GET['to_me'] );

		$action_key = 'wek_resend_' . $submission_id . '_' . ( '' !== $notify_id ? $notify_id : 'all' ) . ( $to_me ? '_me' : '' );
		if ( ! wp_verify_nonce( $nonce, $action_key ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$override = '';
		if ( $to_me ) {
			$user = wp_get_current_user();
			if ( $user && is_email( $user->user_email ) ) {
				$override = $user->user_email;
			} else {
				$override = (string) get_option( 'admin_email' );
			}
		}

		$result = Notifications::resend(
			$submission_id,
			'' !== $notify_id ? $notify_id : null,
			$override
		);

		$args = array(
			'page'          => 'we-formkit-submissions',
			'action'        => 'view',
			'submission_id' => $submission_id,
			'mail_sent'     => (int) $result['sent'],
			'mail_failed'   => (int) $result['failed'],
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['form_id'] ) ) {
			$args['form_id'] = absint( $_GET['form_id'] );
		}
		if ( ! empty( $result['messages'] ) ) {
			$args['mail_msg'] = rawurlencode( implode( ' | ', array_slice( $result['messages'], 0, 5 ) ) );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'we-formkit' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;

		if ( in_array( $action, array( 'view', 'edit' ), true ) && $submission_id ) {
			self::render_view( $submission_id );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		self::render_list( $form_id );
	}

	/**
	 * Entries list for a single form (embedded in form editor).
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	public static function render_list_for_form( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id < 1 ) {
			echo '<p>' . esc_html__( 'Save the form first to view entries.', 'we-formkit' ) . '</p>';
			return;
		}
		self::render_list( $form_id, true );
	}

	/**
	 * @param int  $form_id  Optional form filter.
	 * @param bool $embedded Inside form editor chrome.
	 * @return void
	 */
	private static function render_list( $form_id = 0, $embedded = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'all';
		if ( ! in_array( $tab, array( 'all', 'unread', 'spam', 'trash' ), true ) ) {
			$tab = 'all';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';

		$counts = self::count_tabs( $form_id );
		$query  = self::query_entries( $form_id, $tab, $search );

		$base_args = $form_id > 0
			? array(
				'page'    => 'we-formkit-form',
				'form_id' => (int) $form_id,
				'view'    => 'entries',
			)
			: array(
				'page' => 'we-formkit-submissions',
			);

		$list_url = add_query_arg( $base_args, admin_url( 'admin.php' ) );
		?>
		<?php if ( ! $embedded ) : ?>
		<div class="wrap wek-admin">
			<h1><?php esc_html_e( 'Submissions', 'we-formkit' ); ?></h1>
		<?php else : ?>
		<div class="wek-admin__entries-panel wek-admin__entries-panel--wide">
			<h2><?php esc_html_e( 'Entries', 'we-formkit' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Submissions for this form only.', 'we-formkit' ); ?></p>
		<?php endif; ?>

		<?php self::render_list_notices(); ?>

		<?php
		$csv_url  = wp_nonce_url(
			admin_url( 'admin.php?page=we-formkit-submissions&wek_export_entries=1&format=csv&form_id=' . (int) $form_id ),
			'wek_export_entries_' . (int) $form_id
		);
		$json_url = wp_nonce_url(
			admin_url( 'admin.php?page=we-formkit-submissions&wek_export_entries=1&format=json&form_id=' . (int) $form_id ),
			'wek_export_entries_' . (int) $form_id
		);
		?>
		<p class="wek-admin__export-actions">
			<a class="button" href="<?php echo esc_url( $csv_url ); ?>"><?php esc_html_e( 'Export CSV', 'we-formkit' ); ?></a>
			<a class="button" href="<?php echo esc_url( $json_url ); ?>"><?php esc_html_e( 'Export JSON', 'we-formkit' ); ?></a>
		</p>

		<ul class="subsubsub wek-entries-tabs">
			<?php
			$tabs = array(
				'all'    => __( 'All', 'we-formkit' ),
				'unread' => __( 'Unread', 'we-formkit' ),
				'spam'   => __( 'Spam', 'we-formkit' ),
				'trash'  => __( 'Trash', 'we-formkit' ),
			);
			$i    = 0;
			foreach ( $tabs as $key => $label ) :
				++$i;
				$url   = add_query_arg(
					array_filter(
						array(
							'tab' => $key,
							's'   => '' !== $search ? $search : null,
						)
					),
					$list_url
				);
				$count = isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;
				?>
				<li class="<?php echo esc_attr( $key ); ?>">
					<a href="<?php echo esc_url( $url ); ?>" class="<?php echo $tab === $key ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?>
						<span class="count">(<?php echo esc_html( (string) $count ); ?>)</span>
					</a>
					<?php echo $i < count( $tabs ) ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<form method="get" class="wek-entries-search" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<?php foreach ( $base_args as $k => $v ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( (string) $v ); ?>" />
			<?php endforeach; ?>
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
			<label class="screen-reader-text" for="wek-entries-s"><?php esc_html_e( 'Search entries', 'we-formkit' ); ?></label>
			<input type="search" id="wek-entries-s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by ID, title, answers, or source URL…', 'we-formkit' ); ?>" />
			<?php submit_button( __( 'Search', 'we-formkit' ), '', '', false ); ?>
			<?php if ( '' !== $search ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'tab', $tab, $list_url ) ); ?>"><?php esc_html_e( 'Clear', 'we-formkit' ); ?></a>
			<?php endif; ?>
		</form>

		<p class="wek-entries-count">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of entries shown */
					_n( '%d item', '%d items', (int) $query->found_posts, 'we-formkit' ),
					(int) $query->found_posts
				)
			);
			?>
		</p>

		<table class="widefat striped wek-entries-table">
			<thead>
				<tr>
					<th class="column-id" scope="col"><?php esc_html_e( 'Entry ID', 'we-formkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Summary', 'we-formkit' ); ?></th>
					<?php if ( $form_id < 1 ) : ?>
						<th scope="col"><?php esc_html_e( 'Form', 'we-formkit' ); ?></th>
					<?php endif; ?>
					<th scope="col"><?php esc_html_e( 'Source URL', 'we-formkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Submitted', 'we-formkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Notifications', 'we-formkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'we-formkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $query->posts ) ) : ?>
					<tr>
						<td colspan="<?php echo $form_id < 1 ? '7' : '6'; ?>">
							<?php esc_html_e( 'No submissions yet.', 'we-formkit' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $query->posts as $post ) : ?>
						<?php
						$row_form_id = (int) get_post_meta( $post->ID, Form_Schema::SUB_FORM_ID, true );
						$is_read     = (int) get_post_meta( $post->ID, Form_Schema::SUB_READ, true ) !== 0;
						$source      = (string) get_post_meta( $post->ID, Form_Schema::SUB_SOURCE_URL, true );
						$notify_sum  = self::notify_summary( $post->ID );
						$view_url    = add_query_arg(
							array(
								'page'          => 'we-formkit-submissions',
								'action'        => 'view',
								'submission_id' => (int) $post->ID,
								'form_id'       => $form_id > 0 ? (int) $form_id : $row_form_id,
							),
							admin_url( 'admin.php' )
						);
						$row_class   = $is_read ? '' : 'wek-entry--unread';
						?>
						<tr class="<?php echo esc_attr( $row_class ); ?>">
							<td class="column-id">
								<a href="<?php echo esc_url( $view_url ); ?>"><strong>#<?php echo esc_html( (string) (int) $post->ID ); ?></strong></a>
							</td>
							<td>
								<a href="<?php echo esc_url( $view_url ); ?>">
									<?php if ( ! $is_read && 'trash' !== $tab && 'spam' !== $tab ) : ?>
										<span class="wek-entry-unread-dot" title="<?php esc_attr_e( 'Unread', 'we-formkit' ); ?>" aria-hidden="true"></span>
									<?php endif; ?>
									<?php echo esc_html( get_the_title( $post ) ); ?>
								</a>
							</td>
							<?php if ( $form_id < 1 ) : ?>
								<td><?php echo esc_html( $row_form_id ? get_the_title( $row_form_id ) : '—' ); ?></td>
							<?php endif; ?>
							<td class="wek-entry-source">
								<?php if ( '' !== $source ) : ?>
									<a href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $source ); ?>">
										<?php echo esc_html( self::shorten_url( $source ) ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ); ?></td>
							<td><?php echo esc_html( $notify_sum ); ?></td>
							<td class="wek-entry-actions">
								<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'we-formkit' ); ?></a>
								|
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit-submissions&wek_export_pdf=1&autoprint=1&submission_id=' . (int) $post->ID ), 'wek_export_pdf_' . (int) $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PDF', 'we-formkit' ); ?></a>
								<?php echo self::row_status_actions_html( $post->ID, $form_id > 0 ? $form_id : $row_form_id, $tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* below. ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	private static function render_list_notices() {
		$map = array(
			'trashed'       => __( 'Entry moved to Trash.', 'we-formkit' ),
			'restored'      => __( 'Entry restored.', 'we-formkit' ),
			'deleted'       => __( 'Entry permanently deleted.', 'we-formkit' ),
			'spammed'       => __( 'Entry marked as spam.', 'we-formkit' ),
			'unspammed'     => __( 'Entry removed from spam.', 'we-formkit' ),
			'marked_read'   => __( 'Entry marked as read.', 'we-formkit' ),
			'marked_unread' => __( 'Entry marked as unread.', 'we-formkit' ),
		);

		foreach ( $map as $key => $message ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET[ $key ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
	}

	/**
	 * @param int    $submission_id Submission ID.
	 * @param int    $form_id       Form ID for redirects.
	 * @param string $tab           Current tab.
	 * @return string HTML fragment (escaped).
	 */
	private static function row_status_actions_html( $submission_id, $form_id, $tab ) {
		$submission_id = (int) $submission_id;
		$form_id       = (int) $form_id;
		$html          = '';

		$link = static function ( $action, $label, $confirm = '' ) use ( $submission_id, $form_id ) {
			$url   = wp_nonce_url(
				add_query_arg(
					array(
						'page'             => 'we-formkit-submissions',
						'wek_entry_action' => $action,
						'submission_id'    => $submission_id,
						'form_id'          => $form_id,
					),
					admin_url( 'admin.php' )
				),
				'wek_entry_' . $action . '_' . $submission_id
			);
			$attrs = '';
			if ( '' !== $confirm ) {
				$attrs = ' onclick="return confirm(\'' . esc_js( $confirm ) . '\');"';
			}
			return ' | <a href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( $label ) . '</a>';
		};

		if ( 'trash' === $tab ) {
			$html .= $link( 'restore', __( 'Restore', 'we-formkit' ) );
			$html .= $link( 'delete', __( 'Delete permanently', 'we-formkit' ), __( 'Delete this entry permanently?', 'we-formkit' ) );
			return $html;
		}

		if ( 'spam' === $tab ) {
			$html .= $link( 'unspam', __( 'Not spam', 'we-formkit' ) );
			$html .= $link( 'trash', __( 'Trash', 'we-formkit' ) );
			return $html;
		}

		$html .= $link( 'spam', __( 'Spam', 'we-formkit' ) );
		$html .= $link( 'trash', __( 'Trash', 'we-formkit' ) );
		return $html;
	}

	/**
	 * @param int $form_id Form filter.
	 * @return array{all:int,unread:int,spam:int,trash:int}
	 */
	private static function count_tabs( $form_id ) {
		return array(
			'all'    => self::count_entries( $form_id, 'all' ),
			'unread' => self::count_entries( $form_id, 'unread' ),
			'spam'   => self::count_entries( $form_id, 'spam' ),
			'trash'  => self::count_entries( $form_id, 'trash' ),
		);
	}

	/**
	 * @param int    $form_id Form filter.
	 * @param string $tab     Tab key.
	 * @return int
	 */
	private static function count_entries( $form_id, $tab ) {
		$q = self::query_entries( $form_id, $tab, '', true );
		return (int) $q->found_posts;
	}

	/**
	 * @param int    $form_id Form filter.
	 * @param string $tab     Tab.
	 * @param string $search  Search string.
	 * @param bool   $ids_only Count-friendly query.
	 * @return \WP_Query
	 */
	private static function query_entries( $form_id, $tab, $search = '', $ids_only = false ) {
		$form_id = (int) $form_id;
		$tab     = sanitize_key( $tab );
		$search  = trim( (string) $search );

		$args = array(
			'post_type'              => Post_Types::SUBMISSION,
			'posts_per_page'         => $ids_only ? 1 : 50,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => ! $ids_only,
			'update_post_term_cache' => false,
			'fields'                 => $ids_only ? 'ids' : 'all',
		);

		if ( 'trash' === $tab ) {
			$args['post_status'] = 'trash';
		} else {
			$args['post_status'] = 'publish';
		}

		$meta_query = array();

		if ( $form_id > 0 ) {
			$meta_query[] = array(
				'key'   => Form_Schema::SUB_FORM_ID,
				'value' => (string) $form_id,
			);
		}

		if ( 'spam' === $tab ) {
			$meta_query[] = array(
				'key'   => Form_Schema::SUB_SPAM,
				'value' => '1',
			);
		} elseif ( 'trash' !== $tab ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => Form_Schema::SUB_SPAM,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => Form_Schema::SUB_SPAM,
					'value' => '0',
				),
			);
		}

		if ( 'unread' === $tab ) {
			$meta_query[] = array(
				'key'   => Form_Schema::SUB_READ,
				'value' => '0',
			);
		}

		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$search_cb = null;
		if ( '' !== $search ) {
			$search_cb = static function ( $where ) use ( $search ) {
				global $wpdb;
				$like  = '%' . $wpdb->esc_like( $search ) . '%';
				$id    = absint( $search );
				$extra = $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.ID = %d OR {$wpdb->posts}.ID IN (
						SELECT post_id FROM {$wpdb->postmeta}
						WHERE meta_key IN (%s, %s) AND meta_value LIKE %s
					) )",
					$like,
					$id,
					Form_Schema::SUB_DATA,
					Form_Schema::SUB_SOURCE_URL,
					$like
				);
				return $where . $extra;
			};
			add_filter( 'posts_where', $search_cb, 10, 1 );
		}

		$query = new \WP_Query( $args );

		if ( null !== $search_cb ) {
			remove_filter( 'posts_where', $search_cb, 10 );
		}

		return $query;
	}

	/**
	 * @param int $submission_id Submission ID.
	 * @return string
	 */
	private static function notify_summary( $submission_id ) {
		$log = self::get_notify_log( $submission_id );
		if ( empty( $log ) ) {
			return '—';
		}
		$ok = 0;
		$to = array();
		foreach ( $log as $row ) {
			if ( ! empty( $row['ok'] ) ) {
				++$ok;
				if ( ! empty( $row['to'] ) ) {
					$to[] = (string) $row['to'];
				}
			}
		}
		if ( $ok < 1 ) {
			return __( 'None sent', 'we-formkit' );
		}
		$unique  = array_values( array_unique( $to ) );
		$preview = implode( ', ', array_slice( $unique, 0, 2 ) );
		if ( count( $unique ) > 2 ) {
			$preview .= '…';
		}
		return sprintf(
			/* translators: 1: success count, 2: recipient preview */
			_n( '%1$d sent (%2$s)', '%1$d sent (%2$s)', $ok, 'we-formkit' ),
			$ok,
			$preview
		);
	}

	/**
	 * @param int $submission_id Submission ID.
	 * @return list<array<string, mixed>>
	 */
	private static function get_notify_log( $submission_id ) {
		$raw = (string) get_post_meta( (int) $submission_id, Form_Schema::SUB_NOTIFY_LOG, true );
		$log = json_decode( $raw, true );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function shorten_url( $url ) {
		$url  = (string) $url;
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? $path : $url;
		if ( strlen( $path ) > 48 ) {
			return '…' . substr( $path, -45 );
		}
		return '' !== $path ? $path : $url;
	}

	/**
	 * @param int $submission_id Submission ID.
	 * @return void
	 */
	private static function render_view( $submission_id ) {
		$post = get_post( $submission_id );
		if ( ! $post || Post_Types::SUBMISSION !== $post->post_type ) {
			wp_die( esc_html__( 'Submission not found.', 'we-formkit' ) );
		}

		// Opening an entry marks it read (except when viewing from trash).
		if ( 'trash' !== $post->post_status ) {
			update_post_meta( $submission_id, Form_Schema::SUB_READ, 1 );
		}

		$form_id = (int) get_post_meta( $submission_id, Form_Schema::SUB_FORM_ID, true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$return_form = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : $form_id;
		$raw         = (string) get_post_meta( $submission_id, Form_Schema::SUB_DATA, true );
		$data        = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$notes      = (string) get_post_meta( $submission_id, Form_Schema::SUB_NOTES, true );
		$source     = (string) get_post_meta( $submission_id, Form_Schema::SUB_SOURCE_URL, true );
		$is_spam    = (int) get_post_meta( $submission_id, Form_Schema::SUB_SPAM, true ) === 1;
		$schema     = $form_id ? Form_Schema::get( $form_id ) : Form_Schema::normalize( array() );
		$fields     = Form_Schema::fields_by_id( $schema );
		$notifs     = $form_id > 0 ? Form_Notifications::get( $form_id ) : array();
		$notify_log = self::get_notify_log( $submission_id );
		$submitted  = get_the_date( '', $post ) . ' ' . get_the_time( '', $post );
		?>
		<div class="wrap wek-admin wek-entry-view">
			<h1>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: entry ID */
						__( 'Entry #%d', 'we-formkit' ),
						(int) $submission_id
					)
				);
				?>
			</h1>

			<?php if ( ! empty( $_GET['notes_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notes saved.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['mail_sent'] ) || isset( $_GET['mail_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php
				$sent   = isset( $_GET['mail_sent'] ) ? absint( $_GET['mail_sent'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$failed = isset( $_GET['mail_failed'] ) ? absint( $_GET['mail_failed'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$detail = isset( $_GET['mail_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['mail_msg'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$class  = $failed > 0 && $sent < 1 ? 'notice-error' : 'notice-success';
				?>
				<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: sent count, 2: failed count */
								__( 'Notification resend finished. Sent: %1$d, failed: %2$d.', 'we-formkit' ),
								$sent,
								$failed
							)
						);
						?>
					</p>
					<?php if ( '' !== $detail ) : ?>
						<p class="description"><?php echo esc_html( $detail ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p class="wek-entry-view__nav">
				<?php if ( $return_form > 0 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $return_form . '&view=entries' ) ); ?>">&larr; <?php esc_html_e( 'Back to form entries', 'we-formkit' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-submissions' ) ); ?>">&larr; <?php esc_html_e( 'Back to submissions', 'we-formkit' ); ?></a>
				<?php endif; ?>
				|
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit-submissions&wek_export_pdf=1&autoprint=1&submission_id=' . (int) $submission_id ), 'wek_export_pdf_' . (int) $submission_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Export PDF', 'we-formkit' ); ?></a>
				<?php
				echo self::row_status_actions_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$submission_id,
					$return_form,
					$is_spam ? 'spam' : ( 'trash' === $post->post_status ? 'trash' : 'all' )
				);
				?>
			</p>

			<div class="wek-entry-layout">
				<div class="wek-entry-layout__main">
					<div class="wek-admin__settings-panel">
						<h2><?php esc_html_e( 'Answers', 'we-formkit' ); ?></h2>
						<table class="widefat striped wek-entry-answers">
							<tbody>
								<?php if ( empty( $data ) ) : ?>
									<tr><td><?php esc_html_e( 'No answers stored.', 'we-formkit' ); ?></td></tr>
								<?php else : ?>
									<?php foreach ( $data as $key => $value ) : ?>
										<?php
										$field   = isset( $fields[ $key ] ) && is_array( $fields[ $key ] ) ? $fields[ $key ] : array();
										$label   = isset( $field['label'] ) ? $field['label'] : $key;
										$display = self::format_answer_value( $value, $field );
										?>
										<tr>
											<th scope="row"><?php echo esc_html( $label ); ?></th>
											<td><?php echo wp_kses_post( $display ); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>

					<?php if ( ! empty( $notifs ) ) : ?>
						<div class="wek-admin__settings-panel" style="margin-top:1.25rem;">
							<h2><?php esc_html_e( 'Resend notifications', 'we-formkit' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Resend uses the current notification templates and this entry’s answers — handy to check HTML layout. “To me” sends only to your WordPress account email.', 'we-formkit' ); ?></p>
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Notification', 'we-formkit' ); ?></th>
										<th><?php esc_html_e( 'Status', 'we-formkit' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'we-formkit' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $notifs as $n ) : ?>
										<?php
										$nid    = (string) $n['id'];
										$active = ! empty( $n['enabled'] );
										$base   = array(
											'page'   => 'we-formkit-submissions',
											'action' => 'view',
											'submission_id' => (int) $submission_id,
											'wek_resend_mail' => '1',
											'notification_id' => $nid,
										);
										if ( $return_form > 0 ) {
											$base['form_id'] = (int) $return_form;
										}
										$resend_url = wp_nonce_url(
											add_query_arg( $base, admin_url( 'admin.php' ) ),
											'wek_resend_' . (int) $submission_id . '_' . $nid,
											'_wpnonce'
										);
										$to_me_url  = wp_nonce_url(
											add_query_arg( array_merge( $base, array( 'to_me' => '1' ) ), admin_url( 'admin.php' ) ),
											'wek_resend_' . (int) $submission_id . '_' . $nid . '_me',
											'_wpnonce'
										);
										?>
										<tr>
											<td><strong><?php echo esc_html( (string) $n['name'] ); ?></strong></td>
											<td>
												<span class="wek-notify-status <?php echo $active ? 'wek-notify-status--active' : 'wek-notify-status--inactive'; ?>">
													<span class="wek-notify-status__dot" aria-hidden="true"></span>
													<?php echo $active ? esc_html__( 'Active', 'we-formkit' ) : esc_html__( 'Inactive', 'we-formkit' ); ?>
												</span>
											</td>
											<td>
												<a class="button button-small" href="<?php echo esc_url( $resend_url ); ?>"><?php esc_html_e( 'Resend', 'we-formkit' ); ?></a>
												<a class="button button-small" href="<?php echo esc_url( $to_me_url ); ?>"><?php esc_html_e( 'Send to me', 'we-formkit' ); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<?php
							$all_base = array(
								'page'            => 'we-formkit-submissions',
								'action'          => 'view',
								'submission_id'   => (int) $submission_id,
								'wek_resend_mail' => '1',
							);
							if ( $return_form > 0 ) {
								$all_base['form_id'] = (int) $return_form;
							}
							$all_url = wp_nonce_url(
								add_query_arg( $all_base, admin_url( 'admin.php' ) ),
								'wek_resend_' . (int) $submission_id . '_all',
								'_wpnonce'
							);
							?>
							<p style="margin-top:0.75rem;">
								<a class="button" href="<?php echo esc_url( $all_url ); ?>"><?php esc_html_e( 'Resend all active', 'we-formkit' ); ?></a>
							</p>
						</div>
					<?php endif; ?>

					<div class="wek-admin__settings-panel" style="margin-top:1.25rem;">
						<form method="post">
							<?php wp_nonce_field( 'we_formkit_save_notes', 'we_formkit_notes_nonce' ); ?>
							<input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>" />
							<?php if ( $return_form > 0 ) : ?>
								<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $return_form ); ?>" />
							<?php endif; ?>
							<h2><?php esc_html_e( 'Internal notes', 'we-formkit' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Answers cannot be edited. Notes are for your team only.', 'we-formkit' ); ?></p>
							<textarea class="large-text" rows="5" name="wek_notes"><?php echo esc_textarea( $notes ); ?></textarea>
							<?php submit_button( __( 'Save notes', 'we-formkit' ), 'secondary', 'we_formkit_save_notes' ); ?>
						</form>
					</div>
				</div>

				<aside class="wek-entry-layout__meta">
					<div class="wek-admin__settings-panel">
						<h2><?php esc_html_e( 'Entry details', 'we-formkit' ); ?></h2>
						<table class="form-table wek-entry-meta" role="presentation">
							<tr>
								<th><?php esc_html_e( 'Entry ID', 'we-formkit' ); ?></th>
								<td>#<?php echo esc_html( (string) (int) $submission_id ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Submitted', 'we-formkit' ); ?></th>
								<td><?php echo esc_html( $submitted ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Form', 'we-formkit' ); ?></th>
								<td><?php echo esc_html( $form_id ? get_the_title( $form_id ) : '—' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Source URL', 'we-formkit' ); ?></th>
								<td>
									<?php if ( '' !== $source ) : ?>
										<a href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $source ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Status', 'we-formkit' ); ?></th>
								<td>
									<?php
									if ( 'trash' === $post->post_status ) {
										esc_html_e( 'Trash', 'we-formkit' );
									} elseif ( $is_spam ) {
										esc_html_e( 'Spam', 'we-formkit' );
									} else {
										esc_html_e( 'Inbox', 'we-formkit' );
									}
									?>
								</td>
							</tr>
						</table>
					</div>

					<div class="wek-admin__settings-panel" style="margin-top:1.25rem;">
						<h2><?php esc_html_e( 'Notifications sent', 'we-formkit' ); ?></h2>
						<?php if ( empty( $notify_log ) ) : ?>
							<p class="description"><?php esc_html_e( 'No notification delivery has been recorded for this entry yet.', 'we-formkit' ); ?></p>
						<?php else : ?>
							<ul class="wek-notify-log">
								<?php foreach ( array_reverse( $notify_log ) as $row ) : ?>
									<?php
									$name = isset( $row['name'] ) ? (string) $row['name'] : '';
									$to   = isset( $row['to'] ) ? (string) $row['to'] : '';
									$at   = isset( $row['at'] ) ? (string) $row['at'] : '';
									$ok   = ! empty( $row['ok'] );
									$err  = isset( $row['error'] ) ? (string) $row['error'] : '';
									?>
									<li class="<?php echo $ok ? 'is-ok' : 'is-fail'; ?>">
										<strong><?php echo esc_html( '' !== $name ? $name : __( 'Notification', 'we-formkit' ) ); ?></strong>
										<br />
										<?php if ( '' !== $to ) : ?>
											<span class="wek-notify-log__to"><?php echo esc_html( $to ); ?></span><br />
										<?php endif; ?>
										<span class="description">
											<?php echo esc_html( $at ); ?>
											—
											<?php echo $ok ? esc_html__( 'Sent', 'we-formkit' ) : esc_html__( 'Failed', 'we-formkit' ); ?>
											<?php if ( ! $ok && '' !== $err ) : ?>
												(<?php echo esc_html( $err ); ?>)
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Human-readable answer for the entry view (option labels, not stored keys).
	 *
	 * @param mixed                     $value Stored value.
	 * @param array<string, mixed>|null $field Field config when known.
	 * @return string Already escaped HTML when using field formatters.
	 */
	private static function format_answer_value( $value, $field = null ) {
		if ( is_array( $field ) && ! empty( $field['type'] ) ) {
			$registry = Plugin::instance()->field_registry();
			$type_obj = $registry ? $registry->get( (string) $field['type'] ) : null;
			if ( $type_obj ) {
				$display = $type_obj->format_for_display( $value, $field );
				if ( is_string( $display ) && '' !== $display ) {
					return $display;
				}
			}
		}

		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					$flat[] = isset( $item['url'] ) ? (string) $item['url'] : ( isset( $item['name'] ) ? (string) $item['name'] : wp_json_encode( $item ) );
				} else {
					$flat[] = (string) $item;
				}
			}
			return esc_html( implode( ', ', $flat ) );
		}
		if ( null === $value || '' === $value ) {
			return '—';
		}
		return esc_html( (string) $value );
	}

	/**
	 * @return void
	 */
	private static function save_notes() {
		if ( ! isset( $_POST['we_formkit_notes_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['we_formkit_notes_nonce'] ) ), 'we_formkit_save_notes' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		$post          = get_post( $submission_id );
		if ( ! $post || Post_Types::SUBMISSION !== $post->post_type ) {
			wp_die( esc_html__( 'Submission not found.', 'we-formkit' ) );
		}

		$notes = isset( $_POST['wek_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['wek_notes'] ) ) : '';
		update_post_meta( $submission_id, Form_Schema::SUB_NOTES, $notes );

		$args = array(
			'page'          => 'we-formkit-submissions',
			'action'        => 'view',
			'submission_id' => $submission_id,
			'notes_saved'   => 1,
		);
		if ( ! empty( $_POST['form_id'] ) ) {
			$args['form_id'] = absint( $_POST['form_id'] );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
