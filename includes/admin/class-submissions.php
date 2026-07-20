<?php
/**
 * Submission list and edit (including internal notes).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Form_Notifications;
use Webentwicklerin\WeFormkit\Form_Schema;
use Webentwicklerin\WeFormkit\Notifications;
use Webentwicklerin\WeFormkit\Post_Types;
use Webentwicklerin\WeFormkit\Submission_Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submissions admin UI.
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
		if ( ! isset( $_GET['page'] ) || 'we-formkit-submissions' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_POST['we_formkit_save_submission'] ) ) {
			self::save_submission();
		}

		if ( isset( $_GET['wek_delete_submission'] ) && isset( $_GET['submission_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$id = absint( $_GET['submission_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wek_delete_sub_' . $id ) ) {
				$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
				wp_delete_post( $id, true );
				$redirect = $form_id > 0
					? admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=entries&deleted=1' )
					: admin_url( 'admin.php?page=we-formkit-submissions&deleted=1' );
				wp_safe_redirect( $redirect );
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
			'action'        => 'edit',
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

		if ( 'edit' === $action && $submission_id ) {
			self::render_edit( $submission_id );
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
	 * @param int  $form_id Optional form filter.
	 * @param bool $embedded Inside form editor chrome.
	 * @return void
	 */
	private static function render_list( $form_id = 0, $embedded = false ) {
		$args = array(
			'post_type'      => Post_Types::SUBMISSION,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $form_id > 0 ) {
			$args['meta_key']   = Form_Schema::SUB_FORM_ID; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = (string) (int) $form_id; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$query = new \WP_Query( $args );

		$back_url = $form_id > 0
			? admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $form_id . '&view=entries' )
			: admin_url( 'admin.php?page=we-formkit-submissions' );
		?>
		<?php if ( ! $embedded ) : ?>
		<div class="wrap wek-admin">
			<h1><?php esc_html_e( 'Submissions', 'we-formkit' ); ?></h1>
		<?php else : ?>
		<div class="wek-admin__entries-panel">
			<h2><?php esc_html_e( 'Entries', 'we-formkit' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Submissions for this form only.', 'we-formkit' ); ?></p>
		<?php endif; ?>
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
			<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'we-formkit' ); ?></th>
						<?php if ( $form_id < 1 ) : ?>
							<th><?php esc_html_e( 'Form', 'we-formkit' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Date', 'we-formkit' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'we-formkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $query->posts ) ) : ?>
						<tr><td colspan="<?php echo $form_id < 1 ? '4' : '3'; ?>"><?php esc_html_e( 'No submissions yet.', 'we-formkit' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $query->posts as $post ) : ?>
							<?php $row_form_id = (int) get_post_meta( $post->ID, Form_Schema::SUB_FORM_ID, true ); ?>
							<tr>
								<td><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong></td>
								<?php if ( $form_id < 1 ) : ?>
									<td><?php echo esc_html( $row_form_id ? get_the_title( $row_form_id ) : '—' ); ?></td>
								<?php endif; ?>
								<td><?php echo esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-submissions&action=edit&submission_id=' . (int) $post->ID . ( $form_id > 0 ? '&form_id=' . (int) $form_id : '' ) ) ); ?>"><?php esc_html_e( 'Open', 'we-formkit' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit-submissions&wek_export_pdf=1&autoprint=1&submission_id=' . (int) $post->ID ), 'wek_export_pdf_' . (int) $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PDF', 'we-formkit' ); ?></a>
									|
									<a class="submitdelete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit-submissions&wek_delete_submission=1&submission_id=' . (int) $post->ID . ( $form_id > 0 ? '&form_id=' . (int) $form_id : '' ) ), 'wek_delete_sub_' . (int) $post->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this submission permanently?', 'we-formkit' ) ); ?>');"><?php esc_html_e( 'Delete', 'we-formkit' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		unset( $back_url );
	}

	/**
	 * @param int $submission_id Submission ID.
	 * @return void
	 */
	private static function render_edit( $submission_id ) {
		$post = get_post( $submission_id );
		if ( ! $post || Post_Types::SUBMISSION !== $post->post_type ) {
			wp_die( esc_html__( 'Submission not found.', 'we-formkit' ) );
		}

		$form_id = (int) get_post_meta( $submission_id, Form_Schema::SUB_FORM_ID, true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$return_form = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : $form_id;
		$raw         = (string) get_post_meta( $submission_id, Form_Schema::SUB_DATA, true );
		$data        = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$notes  = (string) get_post_meta( $submission_id, Form_Schema::SUB_NOTES, true );
		$schema = $form_id ? Form_Schema::get( $form_id ) : Form_Schema::normalize( array() );
		$fields = Form_Schema::fields_by_id( $schema );
		$notifs = $form_id > 0 ? Form_Notifications::get( $form_id ) : array();
		?>
		<div class="wrap wek-admin">
			<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
			<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission saved.', 'we-formkit' ); ?></p></div>
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

			<p>
				<?php if ( $return_form > 0 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $return_form . '&view=entries' ) ); ?>">&larr; <?php esc_html_e( 'Back to form entries', 'we-formkit' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-submissions' ) ); ?>">&larr; <?php esc_html_e( 'Back to submissions', 'we-formkit' ); ?></a>
				<?php endif; ?>
				|
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit-submissions&wek_export_pdf=1&autoprint=1&submission_id=' . (int) $submission_id ), 'wek_export_pdf_' . (int) $submission_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Export PDF', 'we-formkit' ); ?></a>
			</p>

			<?php if ( ! empty( $notifs ) ) : ?>
				<div class="wek-admin__settings-panel" style="margin:1rem 0 1.5rem;">
					<h2><?php esc_html_e( 'Resend notifications', 'we-formkit' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Resend uses the current notification templates and this entry’s answers — handy to check HTML layout. “To me” sends only to your WordPress account email.', 'we-formkit' ); ?></p>
					<table class="widefat striped" style="max-width:48rem;">
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
									'page'            => 'we-formkit-submissions',
									'action'          => 'edit',
									'submission_id'   => (int) $submission_id,
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
						'action'          => 'edit',
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

			<form method="post">
				<?php wp_nonce_field( 'we_formkit_save_submission', 'we_formkit_submission_nonce' ); ?>
				<input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $submission_id ); ?>" />

				<h2><?php esc_html_e( 'Answers', 'we-formkit' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $data as $key => $value ) : ?>
						<?php
						$label   = isset( $fields[ $key ]['label'] ) ? $fields[ $key ]['label'] : $key;
						$display = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
						?>
						<tr>
							<th><label for="wek_ans_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<?php if ( isset( $fields[ $key ]['type'] ) && 'textarea' === $fields[ $key ]['type'] ) : ?>
									<textarea class="large-text" rows="3" name="answers[<?php echo esc_attr( $key ); ?>]" id="wek_ans_<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $display ); ?></textarea>
								<?php else : ?>
									<input class="large-text" type="text" name="answers[<?php echo esc_attr( $key ); ?>]" id="wek_ans_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $display ); ?>" />
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Internal notes', 'we-formkit' ); ?></h2>
				<textarea class="large-text" rows="6" name="wek_notes"><?php echo esc_textarea( $notes ); ?></textarea>

				<?php submit_button( __( 'Save submission', 'we-formkit' ), 'primary', 'we_formkit_save_submission' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	private static function save_submission() {
		if ( ! isset( $_POST['we_formkit_submission_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['we_formkit_submission_nonce'] ) ), 'we_formkit_save_submission' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		$post          = get_post( $submission_id );
		if ( ! $post || Post_Types::SUBMISSION !== $post->post_type ) {
			wp_die( esc_html__( 'Submission not found.', 'we-formkit' ) );
		}

		$raw_answers = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$answers     = array();
		foreach ( $raw_answers as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_array( $value ) ) {
				$answers[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$answers[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		$notes = isset( $_POST['wek_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['wek_notes'] ) ) : '';
		update_post_meta( $submission_id, Form_Schema::SUB_DATA, wp_json_encode( $answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		update_post_meta( $submission_id, Form_Schema::SUB_NOTES, $notes );

		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-submissions&action=edit&submission_id=' . $submission_id . '&saved=1' ) );
		exit;
	}
}
