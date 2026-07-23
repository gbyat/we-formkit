<?php
/**
 * Form list/editor with JSON import/export and secret links.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Drafts;
use Webentwicklerin\WeFormkit\Fields\Repeater_Field;
use Webentwicklerin\WeFormkit\Fields\Upload_Field;
use Webentwicklerin\WeFormkit\Form_Info_Documents;
use Webentwicklerin\WeFormkit\Form_Notifications;
use Webentwicklerin\WeFormkit\Form_Schema;
use Webentwicklerin\WeFormkit\Form_Style;
use Webentwicklerin\WeFormkit\Merge_Tags;
use Webentwicklerin\WeFormkit\Plugin;
use Webentwicklerin\WeFormkit\Post_Types;
use Webentwicklerin\WeFormkit\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin form screens.
 */
final class Form_Editor {

	/**
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! Capabilities::can_manage() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( ! in_array( $page, array( 'we-formkit', 'we-formkit-form' ), true ) ) {
			return;
		}

		if ( isset( $_POST['we_formkit_save_form'] ) ) {
			self::save_form();
		}

		if ( isset( $_POST['we_formkit_import_form'] ) ) {
			self::import_form();
		}

		if ( isset( $_GET['wek_export'] ) && isset( $_GET['form_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$form_id = absint( $_GET['form_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wek_export_' . $form_id ) ) {
				self::export_form( $form_id );
			}
		}

		if ( isset( $_GET['wek_regen_token'] ) && isset( $_GET['form_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$form_id = absint( $_GET['form_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wek_regen_' . $form_id ) ) {
				$token = wp_generate_password( 32, false, false );
				Form_Schema::set_secret( $form_id, true, $token );
				wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=settings&token_regenerated=1' ) );
				exit;
			}
		}

		if ( isset( $_GET['wek_delete_form'] ) && isset( $_GET['form_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$form_id = absint( $_GET['form_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wek_delete_form_' . $form_id ) ) {
				wp_delete_post( $form_id, true );
				wp_safe_redirect( admin_url( 'admin.php?page=we-formkit&deleted=1' ) );
				exit;
			}
		}

		if ( isset( $_GET['wek_clone_form'] ) && isset( $_GET['form_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$form_id = absint( $_GET['form_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wek_clone_form_' . $form_id ) ) {
				$new_id = self::clone_form( $form_id );
				if ( $new_id ) {
					wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $new_id . '&view=fields&cloned=1' ) );
					exit;
				}
				wp_safe_redirect( admin_url( 'admin.php?page=we-formkit&clone_failed=1' ) );
				exit;
			}
		}

		if ( isset( $_GET['wek_notify_action'] ) && isset( $_GET['form_id'] ) && isset( $_GET['notification_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			self::handle_notification_action();
		}

		if ( isset( $_GET['wek_doc_action'] ) && isset( $_GET['form_id'] ) && isset( $_GET['document_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			self::handle_document_action();
		}
	}

	/**
	 * Duplicate, delete, or toggle a notification from the list view.
	 *
	 * @return void
	 */
	private static function handle_notification_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = absint( $_GET['form_id'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notify_id = sanitize_key( wp_unslash( (string) $_GET['notification_id'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( wp_unslash( (string) $_GET['wek_notify_action'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) );

		if ( ! in_array( $action, array( 'toggle', 'duplicate', 'delete' ), true ) || '' === $notify_id ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'wek_notify_' . $action . '_' . $form_id . '_' . $notify_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$post = $form_id ? get_post( $form_id ) : null;
		if ( ! $post || Post_Types::FORM !== $post->post_type ) {
			wp_die( esc_html__( 'Form not found.', 'we-formkit' ) );
		}

		$list = Form_Notifications::get( $form_id );
		$base = admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=notifications' );

		if ( 'toggle' === $action ) {
			$list = Form_Notifications::toggle_enabled( $list, $notify_id );
			Form_Notifications::save( $form_id, $list );
			wp_safe_redirect( add_query_arg( 'notify_toggled', '1', $base ) );
			exit;
		}

		if ( 'duplicate' === $action ) {
			$copy = Form_Notifications::duplicate_by_id( $list, $notify_id );
			if ( null === $copy ) {
				wp_safe_redirect( add_query_arg( 'notify_error', 'missing', $base ) );
				exit;
			}
			$list[] = $copy;
			Form_Notifications::save( $form_id, $list );
			wp_safe_redirect(
				add_query_arg(
					array(
						'notification'      => (string) $copy['id'],
						'notify_duplicated' => '1',
					),
					$base
				)
			);
			exit;
		}

		if ( 'delete' === $action ) {
			$list = Form_Notifications::remove_by_id( $list, $notify_id );
			Form_Notifications::save( $form_id, $list );
			wp_safe_redirect( add_query_arg( 'notify_deleted', '1', $base ) );
			exit;
		}
	}

	/**
	 * Toggle or delete an info document from the list view.
	 *
	 * @return void
	 */
	private static function handle_document_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = absint( $_GET['form_id'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$doc_id = sanitize_key( wp_unslash( (string) $_GET['document_id'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( wp_unslash( (string) $_GET['wek_doc_action'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) );

		if ( ! in_array( $action, array( 'toggle', 'delete' ), true ) || '' === $doc_id ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'wek_doc_' . $action . '_' . $form_id . '_' . $doc_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$post = $form_id ? get_post( $form_id ) : null;
		if ( ! $post || Post_Types::FORM !== $post->post_type ) {
			wp_die( esc_html__( 'Form not found.', 'we-formkit' ) );
		}

		$list = Form_Info_Documents::get( $form_id );
		$base = admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=documents' );

		if ( 'toggle' === $action ) {
			$list = Form_Info_Documents::toggle_enabled( $list, $doc_id );
			Form_Info_Documents::save( $form_id, $list );
			wp_safe_redirect( add_query_arg( 'doc_toggled', '1', $base ) );
			exit;
		}

		if ( 'delete' === $action ) {
			$list = Form_Info_Documents::remove_by_id( $list, $doc_id );
			Form_Info_Documents::save( $form_id, $list );
			wp_safe_redirect( add_query_arg( 'doc_deleted', '1', $base ) );
			exit;
		}
	}

	/**
	 * @return void
	 */
	public static function render_list() {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'we-formkit' ) );
		}

		$query = new \WP_Query(
			array(
				'post_type'      => Post_Types::FORM,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap wek-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'we-formkit' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-form' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Form', 'we-formkit' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-form&blank=1' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Blank Form', 'we-formkit' ); ?></a>
			<hr class="wp-header-end" />

			<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form deleted.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['clone_failed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not duplicate the form.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['import_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flash message. ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( (string) $_GET['import_error'] ) ) ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'we-formkit' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'we-formkit' ); ?></th>
						<th><?php esc_html_e( 'Secret link', 'we-formkit' ); ?></th>
						<th><?php esc_html_e( 'Shortcode-free usage', 'we-formkit' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'we-formkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $query->posts ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No forms yet.', 'we-formkit' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $query->posts as $post ) : ?>
							<?php
							$slug   = (string) get_post_meta( $post->ID, Form_Schema::META_SLUG, true );
							$secret = Form_Schema::get_secret( $post->ID );
							?>
							<tr>
								<td><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong></td>
								<td><code><?php echo esc_html( $slug ); ?></code></td>
								<td>
									<?php
									echo $secret['enabled']
										? esc_html__( 'Enabled', 'we-formkit' )
										: esc_html__( 'Open', 'we-formkit' );
									?>
								</td>
								<td><?php esc_html_e( 'Block: Formkit Form', 'we-formkit' ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $post->ID ) ); ?>"><?php esc_html_e( 'Edit', 'we-formkit' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit&wek_clone_form=1&form_id=' . (int) $post->ID ), 'wek_clone_form_' . (int) $post->ID ) ); ?>"><?php esc_html_e( 'Duplicate', 'we-formkit' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit&wek_export=1&form_id=' . (int) $post->ID ), 'wek_export_' . (int) $post->ID ) ); ?>"><?php esc_html_e( 'Export JSON', 'we-formkit' ); ?></a>
									|
									<a class="submitdelete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit&wek_delete_form=1&form_id=' . (int) $post->ID ), 'wek_delete_form_' . (int) $post->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this form?', 'we-formkit' ) ); ?>');"><?php esc_html_e( 'Delete', 'we-formkit' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Import form JSON', 'we-formkit' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Accepts WE Formkit JSON (title + schema, or a raw schema object with sections).', 'we-formkit' ); ?></p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'we_formkit_import_form', 'we_formkit_import_nonce' ); ?>
				<input type="file" name="wek_import_file" accept="application/json,.json" required />
				<?php submit_button( __( 'Import JSON', 'we-formkit' ), 'secondary', 'we_formkit_import_form', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue builder assets and inject the current schema for JS.
	 *
	 * @param array<string,mixed> $schema  Normalized form schema.
	 * @param int                 $form_id Form ID (0 when new).
	 * @return void
	 */
	public static function enqueue_builder_assets( array $schema, $form_id = 0 ) {
		wp_enqueue_media();

		wp_enqueue_script(
			'we-formkit-admin-form',
			WE_FORMKIT_URL . 'assets/js/admin-form-editor.js',
			array(),
			WE_FORMKIT_VERSION,
			true
		);
		wp_set_script_translations( 'we-formkit-admin-form', 'we-formkit', WE_FORMKIT_PATH . 'languages' );

		$schema_json = wp_json_encode(
			$schema,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);
		if ( false === $schema_json ) {
			$schema_json = '{"version":1,"title":"","intro":"","sections":[]}';
		}

		wp_add_inline_script(
			'we-formkit-admin-form',
			'window.weFormkitFormSchema=' . $schema_json . ';',
			'before'
		);

		$field_types = array();
		foreach ( Plugin::instance()->field_registry()->all() as $type ) {
			$field_types[] = array(
				'type'        => $type->get_type(),
				'label'       => $type->get_label(),
				'adminSchema' => $type->get_admin_schema(),
			);
		}

		$submit = $form_id > 0
			? Form_Schema::get_submit_button( (int) $form_id )
			: array(
				'label'         => __( 'Submit form', 'we-formkit' ),
				'icon_svg'      => '',
				'icon_position' => 'before',
			);

		$slug         = $form_id > 0 ? (string) get_post_meta( (int) $form_id, Form_Schema::META_SLUG, true ) : '';
		$modules_boot = array();
		foreach ( Plugin::instance()->module_registry()->all() as $module_id => $definition ) {
			$modules_boot[] = array(
				'id'          => (string) $module_id,
				'name'        => (string) ( $definition['name'] ?? $module_id ),
				'description' => (string) ( $definition['description'] ?? '' ),
				'version'     => (string) ( $definition['version'] ?? '' ),
			);
		}

		wp_localize_script(
			'we-formkit-admin-form',
			'weFormkitAdmin',
			array(
				'formId'            => (int) $form_id,
				'slug'              => $slug,
				'settingsUrl'       => $form_id > 0
					? admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $form_id . '&view=settings' )
					: '',
				'designUrl'         => $form_id > 0
					? admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $form_id . '&view=design' )
					: '',
				'modules'           => $modules_boot,
				'fieldTypes'        => $field_types,
				'submitButton'      => array(
					'label'         => (string) $submit['label'],
					'icon_svg'      => (string) ( $submit['icon_svg'] ?? '' ),
					'icon_position' => (string) ( $submit['icon_position'] ?? 'before' ),
					'width'         => (string) ( $submit['width'] ?? 'auto' ),
				),
				'submitLabel'       => (string) $submit['label'],
				'i18n'              => array(
					'section'                   => __( 'Section', 'we-formkit' ),
					'field'                     => __( 'Field', 'we-formkit' ),
					'scopeField'                => __( 'Field', 'we-formkit' ),
					'scopeForm'                 => __( 'Form', 'we-formkit' ),
					'scopeIntegrations'         => __( 'Integrations', 'we-formkit' ),
					'scopeTabs'                 => __( 'Settings scope', 'we-formkit' ),
					'formScopeTitle'            => __( 'Form', 'we-formkit' ),
					'formScopeLead'             => __( 'Quick links for this form. Save & Resume and privacy live under Form Settings; colors and layout under Design.', 'we-formkit' ),
					'formTitle'                 => __( 'Title', 'we-formkit' ),
					'formIdLabel'               => __( 'Form ID', 'we-formkit' ),
					'shortcode'                 => __( 'Shortcode', 'we-formkit' ),
					'slugLabel'                 => __( 'Slug', 'we-formkit' ),
					'saveFormFirst'             => __( 'Save the form once to unlock the shortcode and Form Settings link.', 'we-formkit' ),
					'openFormSettings'          => __( 'Open Form Settings', 'we-formkit' ),
					'openDesign'                => __( 'Open Design', 'we-formkit' ),
					'formScopePaginationHint'   => __( 'Pagination (single page / one section per page) is in the toolbar above the canvas.', 'we-formkit' ),
					'integrationsTitle'         => __( 'Integrations', 'we-formkit' ),
					'integrationsEmpty'         => __( 'No modules connected yet. Add-ons register here via we_formkit_register_modules.', 'we-formkit' ),
					'integrationsHint'          => __( 'Planned modules include Spamfighter, Subscribe to Posts, and server-side PDF.', 'we-formkit' ),
					'untitledForm'              => __( 'Untitled form', 'we-formkit' ),
					'remove'                    => __( 'Remove', 'we-formkit' ),
					'addField'                  => __( 'Add field', 'we-formkit' ),
					'addSection'                => __( 'Add section', 'we-formkit' ),
					'fieldsLibrary'             => __( 'Fields', 'we-formkit' ),
					'searchFields'              => __( 'Search fields…', 'we-formkit' ),
					'noFieldsMatch'             => __( 'No matching fields.', 'we-formkit' ),
					'fieldSettings'             => __( 'Field settings', 'we-formkit' ),
					'sectionSettings'           => __( 'Section settings', 'we-formkit' ),
					'submitPreview'             => __( 'Submit', 'we-formkit' ),
					'label'                     => __( 'Label', 'we-formkit' ),
					'id'                        => __( 'Field ID', 'we-formkit' ),
					'idPrefillHint'             => __( 'Also the URL query parameter for prefill (e.g. ?anliegen=angebot). Prefer short lowercase IDs.', 'we-formkit' ),
					'prefillTitle'              => __( 'URL prefill', 'we-formkit' ),
					'prefillAllow'              => __( 'Allow URL / embed prefill', 'we-formkit' ),
					'prefillParam'              => __( 'Custom query parameter', 'we-formkit' ),
					'prefillParamHint'          => __( 'Optional. Leave empty to use the Field ID. Example: anliegen', 'we-formkit' ),
					'prefillBaseUrl'            => __( 'Page URL (for copy links)', 'we-formkit' ),
					'prefillBaseUrlHint'        => __( 'Paste the public page where this form is embedded. Used only to build copyable links in the editor.', 'we-formkit' ),
					'prefillLinks'              => __( 'Prefill links', 'we-formkit' ),
					'prefillCopy'               => __( 'Copy', 'we-formkit' ),
					'prefillCopied'             => __( 'Copied', 'we-formkit' ),
					'prefillExample'            => __( 'Example', 'we-formkit' ),
					'prefillMultiHint'          => __( 'Several fields: append more parameters, e.g. ?anliegen=angebot&email=name@example.com', 'we-formkit' ),
					'prefillCheckboxesHint'     => __( 'Checkboxes: comma-separated values, e.g. ?topics=a,b', 'we-formkit' ),
					'type'                      => __( 'Type', 'we-formkit' ),
					'required'                  => __( 'Required', 'we-formkit' ),
					'showLabel'                 => __( 'Show label', 'we-formkit' ),
					'showLabelHint'             => __( 'When off, the label stays available to screen readers but is hidden visually (useful for repeater item fields with placeholders).', 'we-formkit' ),
					'cssClass'                  => __( 'CSS class', 'we-formkit' ),
					'cssClassHint'              => __( 'Optional extra class on the field wrapper (space-separated).', 'we-formkit' ),
					'help'                      => __( 'Help text', 'we-formkit' ),
					'contentGuard'              => __( 'Spam protection', 'we-formkit' ),
					'contentGuardHint'          => __( 'Reject submissions where this field contains links or an email address (helps against bots).', 'we-formkit' ),
					'blockLinks'                => __( 'Reject links (URLs)', 'we-formkit' ),
					'blockEmails'               => __( 'Reject email addresses', 'we-formkit' ),
					'maxLength'                 => __( 'Maximum characters', 'we-formkit' ),
					'maxLengthHint'             => __( '0 = no limit. Caps input length (helps against spam dumps in single-line fields).', 'we-formkit' ),
					'textareaRows'              => __( 'Height (rows)', 'we-formkit' ),
					'textareaRowsHint'          => __( 'Visible height of the box. Independent of maximum characters.', 'we-formkit' ),
					'options'                   => __( 'Options', 'we-formkit' ),
					'optionsDefaultHint'        => __( 'Enter labels; keys are filled in automatically (ä→ae, ß→ss, …). Override a key only when you need a custom value. Drag to reorder; mark one option as default, or leave unset for the placeholder.', 'we-formkit' ),
					'dragToReorder'             => __( 'Drag to reorder', 'we-formkit' ),
					'defaultOption'             => __( 'Default', 'we-formkit' ),
					'clearDefault'              => __( 'Clear default (use placeholder)', 'we-formkit' ),
					'optionValue'               => __( 'Key (optional)', 'we-formkit' ),
					'optionValueAuto'           => __( 'auto from label', 'we-formkit' ),
					'optionValueHint'           => __( 'Optional. Leave empty or auto-generated from the label. Edit to set a custom key.', 'we-formkit' ),
					'optionLabel'               => __( 'Label', 'we-formkit' ),
					'collapseLibrary'           => __( 'Collapse fields library', 'we-formkit' ),
					'expandLibrary'             => __( 'Expand fields library', 'we-formkit' ),
					'collapseSettings'          => __( 'Collapse field settings', 'we-formkit' ),
					'expandSettings'            => __( 'Expand field settings', 'we-formkit' ),
					'resizeLibrary'             => __( 'Drag to resize', 'we-formkit' ),
					'resizeSettings'            => __( 'Drag to resize', 'we-formkit' ),
					'showWhen'                  => __( 'Show when', 'we-formkit' ),
					'showField'                 => __( 'Depends on field', 'we-formkit' ),
					'showOp'                    => __( 'Operator', 'we-formkit' ),
					'showValue'                 => __( 'Value', 'we-formkit' ),
					'none'                      => __( 'Always visible', 'we-formkit' ),
					'matchAll'                  => __( 'Match all of the following (AND)', 'we-formkit' ),
					'matchAny'                  => __( 'Match any of the following (OR)', 'we-formkit' ),
					'addCondition'              => __( 'Add condition', 'we-formkit' ),
					'ruleLabel'                 => __( 'Rule', 'we-formkit' ),
					'selectField'               => __( '— Select field —', 'we-formkit' ),
					'selectValue'               => __( '— Select value —', 'we-formkit' ),
					'conditionsEmpty'           => __( 'No conditions — this item is always visible. Add a rule to show it only when…', 'we-formkit' ),
					'conditionsNoFields'        => __( 'Add other fields first — conditions depend on another field’s value.', 'we-formkit' ),
					'checkboxPreview'           => __( 'Single checkbox', 'we-formkit' ),
					'consentPreview'            => __( 'Consent checkbox', 'we-formkit' ),
					'consentDefaultText'        => __( 'I agree to the {link}', 'we-formkit' ),
					'checkboxDefaultText'       => __( 'Yes', 'we-formkit' ),
					'checkboxText'              => __( 'Checkbox text', 'we-formkit' ),
					'checkboxTextHint'          => __( 'Text shown beside the checkbox.', 'we-formkit' ),
					'consentText'               => __( 'Consent text', 'we-formkit' ),
					'consentTextHint'           => __( 'Text beside the checkbox. Use {link} where the linked phrase should appear.', 'we-formkit' ),
					'consentLinkHint'           => __( 'Put {link} in the consent text where the linked phrase should appear. Without {link}, no link is shown.', 'we-formkit' ),
					'consentLinkText'           => __( 'Link text', 'we-formkit' ),
					'consentLinkTextHint'       => __( 'Defaults to “Privacy policy” when empty.', 'we-formkit' ),
					'consentLinkUrl'            => __( 'Link URL', 'we-formkit' ),
					'consentLinkUrlHint'        => __( 'Leave empty to use the form privacy URL, then the site default.', 'we-formkit' ),
					'privacyPolicy'             => __( 'Privacy policy', 'we-formkit' ),
					'selectPreview'             => __( 'Select…', 'we-formkit' ),
					'optionPreview'             => __( 'Option', 'we-formkit' ),
					'htmlPreview'               => __( 'HTML block', 'we-formkit' ),
					'hiddenPreview'             => __( 'Hidden field', 'we-formkit' ),
					'uploadPreview'             => __( 'Choose file…', 'we-formkit' ),
					'opEquals'                  => __( 'equals', 'we-formkit' ),
					'opNotEquals'               => __( 'not equals', 'we-formkit' ),
					'opContains'                => __( 'contains', 'we-formkit' ),
					'opIsChecked'               => __( 'is checked', 'we-formkit' ),
					'opIsNotChecked'            => __( 'is not checked', 'we-formkit' ),
					'opIsNotEmpty'              => __( 'is not empty', 'we-formkit' ),
					'opIsEmpty'                 => __( 'is empty', 'we-formkit' ),
					'confirmDel'                => __( 'Remove this item?', 'we-formkit' ),
					'duplicate'                 => __( 'Duplicate', 'we-formkit' ),
					'copySection'               => __( 'Copy section', 'we-formkit' ),
					'pasteSection'              => __( 'Paste section', 'we-formkit' ),
					'sectionCopied'             => __( 'Section copied. Paste it in this or another form.', 'we-formkit' ),
					'sectionPasted'             => __( 'Section pasted. Review field IDs and conditions, then save.', 'we-formkit' ),
					'sectionPasteEmpty'         => __( 'No section in the clipboard. Copy a section first.', 'we-formkit' ),
					'sectionPasteInvalid'       => __( 'Clipboard does not contain a Formkit section.', 'we-formkit' ),
					'collapseSection'           => __( 'Collapse section', 'we-formkit' ),
					'expandSection'             => __( 'Expand section', 'we-formkit' ),
					'collapseAllSections'       => __( 'Collapse all', 'we-formkit' ),
					'expandAllSections'         => __( 'Expand all', 'we-formkit' ),
					'sectionActions'            => __( 'Section actions', 'we-formkit' ),
					'edit'                      => __( 'Edit', 'we-formkit' ),
					'moveUp'                    => __( 'Move up', 'we-formkit' ),
					'moveDown'                  => __( 'Move down', 'we-formkit' ),
					'fieldActions'              => __( 'Field actions', 'we-formkit' ),
					'signaturePreview'          => __( 'Signature pad', 'we-formkit' ),
					'matrixPreviewEmpty'        => __( 'Matrix — add rows and columns', 'we-formkit' ),
					'matrixPreviewSelfFill'     => __( 'Matrix — visitors add their own rows', 'we-formkit' ),
					'matrixHint'                => __( 'Preset rows and columns define the grid. Optional visitor-added rows and validation sit below.', 'we-formkit' ),
					'matrixMinAnsweredRows'     => __( 'Minimum answered rows', 'we-formkit' ),
					'matrixMinAnsweredRowsHint' => __( '0 = optional. Per-row / per-column Required is set on each item above.', 'we-formkit' ),
					'matrixColRequiredHint'     => __( 'Only when the row is selected or filled.', 'we-formkit' ),
					'matrixRowsSelfFillHint'    => __( 'No preset rows — visitors fill the matrix themselves (example row until they add one).', 'we-formkit' ),
					'matrixVisitorSection'      => __( 'Visitor-added rows', 'we-formkit' ),
					'matrixValidationSection'   => __( 'Validation', 'we-formkit' ),
					'matrixMaxCustomRows'       => __( 'Max visitor-added rows', 'we-formkit' ),
					'matrixMaxCustomRowsHint'   => __( '1–20. How many rows visitors may add on the form (not the catalog “Rows” list above).', 'we-formkit' ),
					'matrixExampleOn'           => __( 'Inactive example row: on. Front end shows a non-editable Example row until the visitor adds a real row. Clear all preset rows under “Rows” to keep this mode.', 'we-formkit' ),
					'matrixExampleOffPresets'   => __( 'Inactive example row: off. You still have preset rows. Delete every catalog row under “Rows” if you want a self-fill matrix with an example row only.', 'we-formkit' ),
					'matrixExampleOff'          => __( 'Inactive example row: off. Turn on “Allow visitor-added rows” (and clear preset rows) to show a non-editable Example row.', 'we-formkit' ),
					'matrixRowSelect'           => __( 'Show row select checkbox', 'we-formkit' ),
					'matrixRowLabelAlign'       => __( 'Row label alignment', 'we-formkit' ),
					'matrixAllowCustomRows'     => __( 'Allow visitor-added rows', 'we-formkit' ),
					'matrixPreviewCustom'       => __( '+ Other', 'we-formkit' ),
					'alignLeft'                 => __( 'Left', 'we-formkit' ),
					'alignCenter'               => __( 'Center', 'we-formkit' ),
					'alignRight'                => __( 'Right', 'we-formkit' ),
					'matrixRows'                => __( 'Rows', 'we-formkit' ),
					'matrixColumns'             => __( 'Columns', 'we-formkit' ),
					'matrixAddRow'              => __( 'Add row', 'we-formkit' ),
					'matrixAddColumn'           => __( 'Add column', 'we-formkit' ),
					'matrixReorderHint'         => __( 'Drag the handle or use the arrows to reorder.', 'we-formkit' ),
					'matrixColRadio'            => __( 'Radio', 'we-formkit' ),
					'matrixColCheckbox'         => __( 'Checkbox', 'we-formkit' ),
					'matrixColText'             => __( 'Text', 'we-formkit' ),
					'matrixColNumber'           => __( 'Number', 'we-formkit' ),
					/* translators: %d: 1-based column index. */
					'matrixColumnSample'        => __( 'Column %d', 'we-formkit' ),
					'matrixRadioOptions'        => __( 'Radio options (column headers)', 'we-formkit' ),
					/* translators: %d: 1-based row index. */
					'matrixRowSample'           => __( 'Row %d', 'we-formkit' ),
					'matrixRowSample1'          => __( 'Row 1', 'we-formkit' ),
					'matrixRowSample2'          => __( 'Row 2', 'we-formkit' ),
					'matrixOptA'                => __( 'Option A', 'we-formkit' ),
					'matrixOptB'                => __( 'Option B', 'we-formkit' ),
					'matrixOptC'                => __( 'Option C', 'we-formkit' ),
					'empty'                     => __( 'No sections yet. Add a section or pick a field from the library.', 'we-formkit' ),
					'loadError'                 => __( 'Form builder failed to load. Hard-refresh the page or check the browser console.', 'we-formkit' ),
					'width'                     => __( 'Columns', 'we-formkit' ),
					'widthFull'                 => __( 'Full', 'we-formkit' ),
					'widthTwoThirds'            => __( 'Two thirds', 'we-formkit' ),
					'widthHalf'                 => __( 'Half', 'we-formkit' ),
					'widthThird'                => __( 'One third', 'we-formkit' ),
					'widthHint'                 => __( 'Choose how many columns this field spans in the row.', 'we-formkit' ),
					'addOption'                 => __( 'Add option', 'we-formkit' ),
					'tabGeneral'                => __( 'General', 'we-formkit' ),
					'tabAppearance'             => __( 'Appearance', 'we-formkit' ),
					'tabConditional'            => __( 'Conditional', 'we-formkit' ),
					'selectHint'                => __( 'Select a field, section, or the submit button to edit its settings.', 'we-formkit' ),
					'submitSettings'            => __( 'Submit button', 'we-formkit' ),
					'submitSettingsHint'        => __( 'Shown at the end of the form. Save the form to apply changes on the front end.', 'we-formkit' ),
					'submitButtonText'          => __( 'Submit button text', 'we-formkit' ),
					'submitButtonWidth'         => __( 'Button width', 'we-formkit' ),
					'submitButtonWidthHint'     => __( 'Same column widths as fields. Auto fits the label (recommended).', 'we-formkit' ),
					'widthAuto'                 => __( 'Auto', 'we-formkit' ),
					'submitIconSvg'             => __( 'SVG icon (optional)', 'we-formkit' ),
					'submitIconSvgHint'         => __( 'Paste inline SVG markup (no scripts). Leave empty for text only.', 'we-formkit' ),
					'iconPosition'              => __( 'Icon position', 'we-formkit' ),
					'iconBefore'                => __( 'Before text', 'we-formkit' ),
					'iconAfter'                 => __( 'After text', 'we-formkit' ),
					'editSubmit'                => __( 'Edit submit button', 'we-formkit' ),
					'dragHandle'                => __( 'Drag to reorder', 'we-formkit' ),
					'resizeHandle'              => __( 'Drag to resize', 'we-formkit' ),
					'resizeHint'                => __( 'You can also drag the right edge of a field on the canvas to change its width.', 'we-formkit' ),
					'sectionTitle'              => __( 'Title', 'we-formkit' ),
					'showSectionTitle'          => __( 'Show title on form', 'we-formkit' ),
					'showSectionTitleHint'      => __( 'When off, the title stays in the builder for reference but is hidden on the front end.', 'we-formkit' ),
					'sectionIntro'              => __( 'Section intro', 'we-formkit' ),
					'sectionIntroHint'          => __( 'Optional text under the section title. Use this for a question or short explanation — not a second title.', 'we-formkit' ),
					'titleHiddenBadge'          => __( 'Hidden on form', 'we-formkit' ),
					'sectionId'                 => __( 'Section ID', 'we-formkit' ),
					'moved'                     => __( 'Item moved.', 'we-formkit' ),
					'placeholder'               => __( 'Placeholder', 'we-formkit' ),
					'selectPlaceholderHint'     => __( 'Shown as the first empty choice (empty value) when no default option is selected. Leave blank to use “Please select…”.', 'we-formkit' ),
					'pleaseSelect'              => __( 'Please select…', 'we-formkit' ),
					'validationMessages'        => __( 'Validation messages', 'we-formkit' ),
					'msgRequired'               => __( 'Required message', 'we-formkit' ),
					'msgInvalid'                => __( 'Invalid message', 'we-formkit' ),
					'msgHint'                   => sprintf(
						/* translators: %s: literal merge tag {label} (do not translate the tag). */
						__( 'Leave empty to use Formkit Settings defaults. Use %s for the field label.', 'we-formkit' ),
						'{label}'
					),
					'maxFiles'                  => __( 'Max files', 'we-formkit' ),
					'maxFileSize'               => __( 'Max file size (MB)', 'we-formkit' ),
					'allowedMime'               => __( 'Allowed MIME types', 'we-formkit' ),
					'allowedMimeHint'           => __( 'Leave empty to use the WordPress default whitelist.', 'we-formkit' ),
					'addMimeType'               => __( 'Add type…', 'we-formkit' ),
					'clearMimeTypes'            => __( 'Clear all', 'we-formkit' ),
					'removeMimeType'            => __( 'Remove', 'we-formkit' ),
					'storageMode'               => __( 'Storage mode', 'we-formkit' ),
					'storagePrivate'            => __( 'Private Formkit folder (recommended)', 'we-formkit' ),
					'storageMedia'              => __( 'Media Library (not recommended for personal data)', 'we-formkit' ),
					'htmlContent'               => __( 'HTML content', 'we-formkit' ),
					'defaultValue'              => __( 'Default value', 'we-formkit' ),
					'constraints'               => __( 'Date constraints', 'we-formkit' ),
					'minConstraint'             => __( 'Minimum', 'we-formkit' ),
					'maxConstraint'             => __( 'Maximum', 'we-formkit' ),
					'repeaterFields'            => __( 'Fields in each row', 'we-formkit' ),
					'repeaterHint'              => __( 'Click or drag fields from the library into the repeater. Min/max control how many rows visitors can add.', 'we-formkit' ),
					'minRows'                   => __( 'Minimum rows', 'we-formkit' ),
					'maxRows'                   => __( 'Maximum rows', 'we-formkit' ),
					'minSelected'               => __( 'Minimum selections', 'we-formkit' ),
					'maxSelected'               => __( 'Maximum selections', 'we-formkit' ),
					'checkboxesLimitsHint'      => __( '0 minimum = no minimum (unless Required). 0 maximum = unlimited.', 'we-formkit' ),
					'allowOther'                => __( 'Allow custom options', 'we-formkit' ),
					'otherLabel'                => __( 'Add button label', 'we-formkit' ),
					'otherLabelDefault'         => __( 'Add other', 'we-formkit' ),
					'maxOther'                  => __( 'Max custom options', 'we-formkit' ),
					'otherTextRequired'         => __( 'Please enter text for each custom option.', 'we-formkit' ),
					'numberMin'                 => __( 'Minimum', 'we-formkit' ),
					'numberMax'                 => __( 'Maximum', 'we-formkit' ),
					'numberStep'                => __( 'Step', 'we-formkit' ),
					'numberStepHint'            => __( 'Examples: any, 1, 0.5, 0.01. Leave empty to use decimal places.', 'we-formkit' ),
					'numberDecimals'            => __( 'Decimal places', 'we-formkit' ),
					'numberOptionsHint'         => __( 'Optional limits for the number input. Decimal places set the step when Step is left empty (0 → 1, 2 → 0.01).', 'we-formkit' ),
					'addRowLabel'               => __( 'Add row button label', 'we-formkit' ),
					'repeaterDropHint'          => __( 'Drop fields here — they repeat together on the front end.', 'we-formkit' ),
					'repeaterEmpty'             => __( 'Drop fields here from the library.', 'we-formkit' ),
					'sectionEmpty'              => __( 'Click or drag a field from the library.', 'we-formkit' ),
					'repeaterNoNest'            => __( 'A repeater cannot be placed inside another repeater.', 'we-formkit' ),
					'repeaterTypeBlocked'       => __( 'This field type cannot be used inside a repeater.', 'we-formkit' ),
				),
				'repeaterItemTypes' => Repeater_Field::allowed_item_types(),
				'mimeChoices'       => Upload_Field::common_mime_choices(),
			)
		);
	}

	/**
	 * Blank schema with one empty section.
	 *
	 * @param string $title Optional schema title.
	 * @return array<string,mixed>
	 */
	private static function blank_schema( $title = '' ) {
		return Form_Schema::normalize(
			array(
				'version'  => 1,
				'title'    => $title,
				'intro'    => '',
				'sections' => array(
					array(
						'id'        => 'section_1',
						'title'     => __( 'Section', 'we-formkit' ),
						'intro'     => '',
						'show_when' => null,
						'fields'    => array(),
					),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render_edit() {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'we-formkit' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view          = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : 'fields';
		$allowed_views = array( 'fields', 'settings', 'design', 'documents', 'notifications', 'confirmations', 'entries' );
		if ( ! in_array( $view, $allowed_views, true ) ) {
			$view = 'fields';
		}

		$post   = $form_id ? get_post( $form_id ) : null;
		$is_new = ! $post || Post_Types::FORM !== $post->post_type;

		if ( $is_new ) {
			$schema  = self::blank_schema();
			$title   = __( 'New form', 'we-formkit' );
			$slug    = '';
			$secret  = array(
				'enabled' => false,
				'token'   => '',
			);
			$notify  = '';
			$privacy = '';
			$confirm = Form_Schema::get_confirmation( 0 );
			$form_id = 0;
			if ( 'entries' === $view ) {
				$view = 'fields';
			}
		} else {
			$schema  = Form_Schema::get( $form_id );
			$title   = get_the_title( $post );
			$slug    = (string) get_post_meta( $form_id, Form_Schema::META_SLUG, true );
			$secret  = Form_Schema::get_secret( $form_id );
			$notify  = (string) get_post_meta( $form_id, Form_Schema::META_NOTIFY_EMAIL, true );
			$privacy = (string) get_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, true );
			$confirm = Form_Schema::get_confirmation( $form_id );
		}

		$notifications = $form_id > 0
			? Form_Notifications::get( $form_id )
			: Form_Notifications::defaults( is_array( $schema ) ? $schema : array() );
		$documents     = $form_id > 0 ? Form_Info_Documents::get( $form_id ) : array();

		$schema_json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $schema_json ) {
			$schema_json = '{"version":1,"title":"","intro":"","sections":[]}';
		}

		if ( 'fields' === $view ) {
			self::enqueue_builder_assets( is_array( $schema ) ? $schema : array(), $form_id );
		}

		$secret_url = '';
		if ( $secret['enabled'] && $slug && $secret['token'] ) {
			$secret_url = Form_Schema::secret_query_string( $slug, $secret['token'] );
		}

		if ( 'settings' === $view || 'design' === $view ) {
			self::enqueue_settings_assets(
				$form_id,
				$title,
				$slug,
				$secret,
				$privacy,
				$schema,
				$secret_url,
				'design' === $view ? 'design' : 'general'
			);
		}

		if ( in_array( $view, array( 'notifications', 'confirmations' ), true ) ) {
			self::enqueue_smart_tags_assets(
				is_array( $schema ) ? $schema : array(),
				'confirmations' === $view ? 'confirmation' : 'email'
			);
		}

		$heading = $is_new ? __( 'Add Form', 'we-formkit' ) : $title;
		$wrap    = 'wrap wek-admin wek-admin--form-edit';
		if ( 'fields' === $view ) {
			$wrap .= ' wek-admin--fields';
		}
		?>
	<div class="<?php echo esc_attr( $wrap ); ?>">
		<h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
		<?php if ( ! $is_new ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=we-formkit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'All Forms', 'we-formkit' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end" />

		<?php if ( ! empty( $_GET['cloned'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form duplicated. Review the slug and secret link, then save.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form saved.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['token_regenerated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Secret token regenerated. Old links no longer work.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['notify_toggled'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification status updated.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['notify_deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification deleted.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['notify_duplicated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification duplicated. Review the copy, then save.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['doc_toggled'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Document status updated.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['doc_deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Document deleted.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['error'] ) && 'no_file' === sanitize_key( wp_unslash( (string) $_GET['error'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Please select a file from the Media Library before saving.', 'we-formkit' ); ?></p></div>
		<?php endif; ?>

		<?php if ( 'fields' !== $view ) : ?>
			<?php self::render_form_nav( $form_id, $view, $is_new ); ?>
		<?php endif; ?>

		<?php
		switch ( $view ) {
			case 'settings':
				self::render_view_settings( $form_id, $title, $slug, $secret, $privacy, $schema, $secret_url, $is_new );
				break;
			case 'design':
				self::render_view_design( $form_id );
				break;
			case 'documents':
				self::render_view_documents( $form_id, $documents, $notifications, is_array( $schema ) ? $schema : array(), $is_new );
				break;
			case 'notifications':
				self::render_view_notifications( $form_id, $notifications, is_array( $schema ) ? $schema : array(), $is_new );
				break;
			case 'confirmations':
				self::render_view_confirmations( $form_id, $confirm, $is_new );
				break;
			case 'entries':
				Submissions::render_list_for_form( $form_id );
				break;
			case 'fields':
			default:
				self::render_view_fields( $form_id, $schema_json, $title, $is_new );
				break;
		}
		?>
	</div>
		<?php
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $view Current view.
	 * @param bool   $is_new Whether form is unsaved.
	 * @return void
	 */
	private static function render_form_nav( $form_id, $view, $is_new ) {
		$base = admin_url( 'admin.php?page=we-formkit-form' );
		if ( $form_id > 0 ) {
			$base = add_query_arg( 'form_id', $form_id, $base );
		} elseif ( ! empty( $_GET['blank'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$base = add_query_arg( 'blank', '1', $base );
		}

		$tabs = array(
			'fields'        => __( 'Fields', 'we-formkit' ),
			'settings'      => __( 'Form Settings', 'we-formkit' ),
			'design'        => __( 'Design', 'we-formkit' ),
			'documents'     => __( 'Documents', 'we-formkit' ),
			'notifications' => __( 'Notifications', 'we-formkit' ),
			'confirmations' => __( 'Confirmations', 'we-formkit' ),
			'entries'       => __( 'Entries', 'we-formkit' ),
		);
		?>
	<nav class="wek-form-nav" aria-label="<?php esc_attr_e( 'Form editor sections', 'we-formkit' ); ?>">
		<ul class="wek-form-nav__list">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<?php
				$disabled = ( 'entries' === $key && ( $is_new || $form_id < 1 ) );
				$url      = add_query_arg( 'view', $key, $base );
				$classes  = 'wek-form-nav__link';
				if ( $view === $key ) {
					$classes .= ' is-active';
				}
				if ( $disabled ) {
					$classes .= ' is-disabled';
				}
				?>
				<li class="wek-form-nav__item">
					<?php if ( $disabled ) : ?>
						<span class="<?php echo esc_attr( $classes ); ?>" aria-disabled="true" title="<?php esc_attr_e( 'Save the form first to view entries.', 'we-formkit' ); ?>"><?php echo esc_html( $label ); ?></span>
					<?php else : ?>
						<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $view === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
		<?php
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $schema_json Schema JSON.
	 * @param string $title Title.
	 * @param bool   $is_new Whether new.
	 * @return void
	 */
	private static function render_view_fields( $form_id, $schema_json, $title, $is_new ) {
		$forms_url   = admin_url( 'admin.php?page=we-formkit' );
		$entries_url = '';
		$preview_url = '';
		$clone_url   = '';
		$pagination  = 'single';
		$shortcode   = '';
		if ( ! $is_new && $form_id > 0 ) {
			$entries_url = add_query_arg(
				array(
					'page'    => 'we-formkit-form',
					'form_id' => $form_id,
					'view'    => 'entries',
				),
				admin_url( 'admin.php' )
			);
			$preview_url = add_query_arg(
				array(
					'wek_preview' => '1',
					'form_id'     => $form_id,
				),
				home_url( '/' )
			);
			$clone_url   = wp_nonce_url( admin_url( 'admin.php?page=we-formkit&wek_clone_form=1&form_id=' . $form_id ), 'wek_clone_form_' . $form_id );
			$pagination  = Form_Schema::get_pagination( $form_id );
			$slug        = (string) get_post_meta( $form_id, Form_Schema::META_SLUG, true );
			$shortcode   = $slug
				? sprintf( '[we_formkit slug="%s"]', $slug )
				: sprintf( '[we_formkit id="%d"]', $form_id );
		}
		?>
	<form method="post" id="wek-form-editor" class="wek-admin__fields-only">
		<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
		<input type="hidden" name="wek_save_view" value="fields" />
		<textarea name="wek_schema_json" id="wek_schema_json" class="wek-admin__schema-input" hidden><?php echo esc_textarea( $schema_json ); ?></textarea>
		<?php
		$submit_boot = $form_id > 0
			? Form_Schema::get_submit_button( $form_id )
			: array(
				'label'         => __( 'Submit form', 'we-formkit' ),
				'icon_svg'      => '',
				'icon_position' => 'before',
				'width'         => 'auto',
			);
		?>
		<input type="hidden" name="wek_submit_label" id="wek_submit_label" value="<?php echo esc_attr( (string) $submit_boot['label'] ); ?>" />
		<input type="hidden" name="wek_submit_icon_svg" id="wek_submit_icon_svg" value="<?php echo esc_attr( (string) $submit_boot['icon_svg'] ); ?>" />
		<input type="hidden" name="wek_submit_icon_position" id="wek_submit_icon_position" value="<?php echo esc_attr( (string) $submit_boot['icon_position'] ); ?>" />
		<input type="hidden" name="wek_submit_width" id="wek_submit_width" value="<?php echo esc_attr( (string) ( $submit_boot['width'] ?? 'auto' ) ); ?>" />

		<div class="wek-fields-bar">
			<a class="wek-fields-bar__back" href="<?php echo esc_url( $forms_url ); ?>" title="<?php esc_attr_e( 'All Forms', 'we-formkit' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'All Forms', 'we-formkit' ); ?></span>
			</a>
			<label class="wek-fields-bar__title-wrap">
				<span class="screen-reader-text"><?php esc_html_e( 'Form title', 'we-formkit' ); ?></span>
				<input
					type="text"
					name="wek_title"
					id="wek_title"
					class="wek-fields-bar__title"
					value="<?php echo esc_attr( $title ); ?>"
					placeholder="<?php esc_attr_e( 'Untitled form', 'we-formkit' ); ?>"
				/>
			</label>
			<?php if ( $form_id > 0 ) : ?>
				<code class="wek-fields-bar__badge" title="<?php esc_attr_e( 'Form ID for the Formkit block', 'we-formkit' ); ?>">ID <?php echo esc_html( (string) $form_id ); ?></code>
			<?php endif; ?>
			<label class="wek-fields-bar__pagination">
				<span class="screen-reader-text"><?php esc_html_e( 'Pagination', 'we-formkit' ); ?></span>
				<select name="wek_pagination" id="wek_pagination" title="<?php esc_attr_e( 'Pagination', 'we-formkit' ); ?>">
					<option value="single" <?php selected( $pagination, 'single' ); ?>><?php esc_html_e( 'Single page', 'we-formkit' ); ?></option>
					<option value="per_section" <?php selected( $pagination, 'per_section' ); ?>><?php esc_html_e( 'One section per page', 'we-formkit' ); ?></option>
				</select>
			</label>
			<div class="wek-fields-bar__actions">
				<?php if ( $preview_url ) : ?>
					<a class="button" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview', 'we-formkit' ); ?></a>
				<?php endif; ?>
				<?php if ( $clone_url ) : ?>
					<a class="button" href="<?php echo esc_url( $clone_url ); ?>"><?php esc_html_e( 'Duplicate', 'we-formkit' ); ?></a>
				<?php endif; ?>
				<?php if ( $entries_url ) : ?>
					<a class="button wek-fields-bar__entries" href="<?php echo esc_url( $entries_url ); ?>"><?php esc_html_e( 'Entries', 'we-formkit' ); ?></a>
				<?php endif; ?>
				<?php submit_button( __( 'Save Form', 'we-formkit' ), 'primary', 'we_formkit_save_form', false ); ?>
			</div>
		</div>
		<?php if ( $shortcode ) : ?>
			<p class="wek-fields-bar__shortcode description">
				<?php esc_html_e( 'Shortcode:', 'we-formkit' ); ?>
				<code><?php echo esc_html( $shortcode ); ?></code>
			</p>
		<?php endif; ?>

		<?php self::render_form_nav( $form_id, 'fields', $is_new ); ?>

		<p class="screen-reader-text"><?php esc_html_e( 'Build sections and fields here. Form settings, notifications, and confirmations are on the other tabs.', 'we-formkit' ); ?></p>
		<div id="wek-builder" class="wek-builder"></div>
	</form>
		<?php
	}

	/**
	 * Enqueue DataForm settings / design screen assets.
	 *
	 * @param int                  $form_id Form ID.
	 * @param string               $title Title.
	 * @param string               $slug Slug.
	 * @param array{enabled:bool,token:string} $secret Secret.
	 * @param string               $privacy Privacy URL.
	 * @param array<string,mixed>  $schema Schema.
	 * @param string               $secret_url Share URL.
	 * @param string               $panel Panel: general|design.
	 * @return void
	 */
	private static function enqueue_settings_assets( $form_id, $title, $slug, array $secret, $privacy, array $schema, $secret_url, $panel = 'general' ) {
		$panel = 'design' === $panel ? 'design' : 'general';

		$asset_file = WE_FORMKIT_PATH . 'build/admin-form-settings.asset.php';
		$script     = WE_FORMKIT_URL . 'build/admin-form-settings.js';
		$style_file = WE_FORMKIT_PATH . 'build/style-admin-form-settings.css';
		$style_url  = WE_FORMKIT_URL . 'build/style-admin-form-settings.css';

		if ( ! file_exists( $asset_file ) || ! file_exists( WE_FORMKIT_PATH . 'build/admin-form-settings.js' ) ) {
			return;
		}

		$asset = include $asset_file;
		$deps  = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$ver   = isset( $asset['version'] ) ? (string) $asset['version'] : WE_FORMKIT_VERSION;

		if ( 'general' === $panel ) {
			wp_enqueue_editor();
			$deps[] = 'editor';
		}

		wp_enqueue_script(
			'we-formkit-admin-form-settings',
			$script,
			array_values( array_unique( $deps ) ),
			$ver,
			true
		);
		wp_set_script_translations( 'we-formkit-admin-form-settings', 'we-formkit', WE_FORMKIT_PATH . 'languages' );

		if ( file_exists( $style_file ) ) {
			$style_deps = array( 'wp-components' );
			$dv_style   = WE_FORMKIT_PATH . 'build/dataviews.css';
			if ( file_exists( $dv_style ) ) {
				wp_enqueue_style(
					'we-formkit-dataviews',
					WE_FORMKIT_URL . 'build/dataviews.css',
					array( 'wp-components' ),
					$ver
				);
				$style_deps[] = 'we-formkit-dataviews';
			}
			wp_enqueue_style(
				'we-formkit-admin-form-settings',
				$style_url,
				$style_deps,
				$ver
			);
		}

		$style_stored = $form_id > 0 ? Form_Style::get( $form_id ) : Form_Style::normalize( array() );
		$colors       = $form_id > 0 ? Form_Style::editable_colors( $form_id ) : Form_Style::theme_defaults();
		$appear       = $form_id > 0
			? Form_Schema::get_appearance( $form_id )
			: Form_Schema::normalize_appearance( array() );

		wp_localize_script(
			'we-formkit-admin-form-settings',
			'weFormkitFormSettings',
			array(
				'formId'        => (int) $form_id,
				'panel'         => $panel,
				'secretUrl'     => (string) $secret_url,
				'secretToken'   => (string) ( $secret['token'] ?? '' ),
				'regenUrl'      => $form_id > 0
					? wp_nonce_url(
						admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=settings&wek_regen_token=1' ),
						'wek_regen_' . $form_id
					)
					: '',
				'themeColors'   => Form_Style::theme_defaults(),
				'formkitColors' => Form_Style::formkit_defaults(),
				'schemes'       => Form_Style::schemes_for_admin(),
				'customColors'  => Form_Style::saved_custom_colors( $form_id ),
				'fontFamilies'  => Form_Style::theme_font_families(),
				'draftTtlDays'  => Drafts::allowed_ttl_days(),
				'settings'      => array(
					'title'                 => (string) $title,
					'show_title'            => ! array_key_exists( 'show_title', $schema ) || ! empty( $schema['show_title'] ),
					'slug'                  => (string) $slug,
					'intro'                 => (string) ( $schema['intro'] ?? '' ),
					'show_intro'            => ! array_key_exists( 'show_intro', $schema ) || ! empty( $schema['show_intro'] ),
					'privacy_url'           => (string) $privacy,
					'secret_enabled'        => ! empty( $secret['enabled'] ),
					'style_preset'          => (string) $style_stored['preset'],
					'colors'                => $colors,
					'label_weight'          => (string) $appear['label_weight'],
					'required_mark'         => (string) $appear['required_mark'],
					'optional_mark'         => (string) $appear['optional_mark'],
					'inline_validation'     => (string) $appear['inline_validation'],
					'inline_scope'          => (string) $appear['inline_scope'],
					'help_placement'        => (string) $appear['help_placement'],
					'help_style'            => (string) $appear['help_style'],
					'font_family'           => (string) $appear['font_family'],
					'spacing'               => (string) $appear['spacing'],
					'chrome_gap'            => (string) $appear['chrome_gap'],
					'control_padding'       => (string) $appear['control_padding'],
					'size_section'          => (string) $appear['size_section'],
					'size_label'            => (string) $appear['size_label'],
					'size_input'            => (string) $appear['size_input'],
					'radius_input'          => (string) $appear['radius_input'],
					'radius_button'         => (string) $appear['radius_button'],
					'radius_section'        => (string) $appear['radius_section'],
					'save_resume'           => $form_id > 0 && Drafts::is_enabled( $form_id ),
					'save_resume_ttl'       => $form_id > 0 ? Drafts::get_ttl_days( $form_id ) : Drafts::TTL_DAYS,
					'save_resume_min'       => $form_id > 0 ? Drafts::get_min_filled( $form_id ) : Drafts::MIN_FILLED,
					'save_resume_reminders' => $form_id > 0 && Drafts::reminders_allowed( $form_id ),
				),
			)
		);
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param string               $title Title.
	 * @param string               $slug Slug.
	 * @param array{enabled:bool,token:string} $secret Secret.
	 * @param string               $privacy Privacy URL.
	 * @param array<string,mixed>  $schema Schema.
	 * @param string               $secret_url Share URL.
	 * @param bool                 $is_new Whether new.
	 * @return void
	 */
	private static function render_view_settings( $form_id, $title, $slug, array $secret, $privacy, array $schema, $secret_url, $is_new ) {
		unset( $title, $slug, $secret, $privacy, $schema, $secret_url, $is_new );

		$asset_file = WE_FORMKIT_PATH . 'build/admin-form-settings.asset.php';
		$built      = file_exists( $asset_file ) && file_exists( WE_FORMKIT_PATH . 'build/admin-form-settings.js' );
		?>
		<div class="wek-admin__settings-panel wek-admin__settings-panel--dataform">
			<?php if ( ! $built ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						echo esc_html__(
							'Modern settings UI is not built yet. Run npm install && npm run build:assets in the plugin folder.',
							'we-formkit'
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<div id="wek-form-settings-root"></div>
		</div>
		<?php
	}

	/**
	 * Design / appearance panel (same React app, design panel).
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private static function render_view_design( $form_id ) {
		unset( $form_id );
		$asset_file = WE_FORMKIT_PATH . 'build/admin-form-settings.asset.php';
		$built      = file_exists( $asset_file ) && file_exists( WE_FORMKIT_PATH . 'build/admin-form-settings.js' );
		?>
		<div class="wek-admin__settings-panel wek-admin__settings-panel--dataform wek-admin__settings-panel--design">
			<?php if ( ! $built ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						echo esc_html__(
							'Modern settings UI is not built yet. Run npm install && npm run build:assets in the plugin folder.',
							'we-formkit'
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<div id="wek-form-settings-root"></div>
		</div>
		<?php
	}

	/**
	 * @param int                        $form_id        Form ID.
	 * @param list<array<string, mixed>> $notifications  Notifications.
	 * @param array<string, mixed>       $schema         Schema.
	 * @param bool                       $is_new         Whether new.
	 * @return void
	 */
	private static function render_view_notifications( $form_id, array $notifications, array $schema, $is_new ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['notification'] ) ? sanitize_key( wp_unslash( (string) $_GET['notification'] ) ) : '';

		if ( 'new' === $edit_id ) {
			self::render_notification_edit( $form_id, Form_Notifications::blank(), $schema, true );
			return;
		}
		if ( '' !== $edit_id ) {
			$current = Form_Notifications::find_by_id( $notifications, $edit_id );
			if ( null === $current ) {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Notification not found.', 'we-formkit' );
				echo '</p></div>';
				self::render_notifications_list( $form_id, $notifications, $is_new );
				return;
			}
			self::render_notification_edit( $form_id, $current, $schema, false );
			return;
		}

		self::render_notifications_list( $form_id, $notifications, $is_new );
	}

	/**
	 * @param int                        $form_id       Form ID.
	 * @param list<array<string, mixed>> $notifications Notifications.
	 * @param bool                       $is_new        Whether new.
	 * @return void
	 */
	private static function render_notifications_list( $form_id, array $notifications, $is_new ) {
		$list_url = admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $form_id . '&view=notifications' );
		$add_url  = add_query_arg( 'notification', 'new', $list_url );
		?>
		<div class="wek-admin__settings-panel wek-admin__notifications">
			<div class="wek-notify-list__header">
				<h2><?php esc_html_e( 'Notifications', 'we-formkit' ); ?></h2>
				<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'we-formkit' ); ?></a>
			</div>

			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1–7: merge tags (do not translate the tags). */
						__( 'Configure who receives emails after a submission. Use %1$s, %2$s, %3$s, %4$s, %5$s, %6$s, or %7$s in subject and message.', 'we-formkit' ),
						'{all_fields}',
						'{form_title}',
						'{submission_url}',
						'{date}',
						'{site_name}',
						'{info_links}',
						'{field:field_id}'
					)
				);
				?>
			</p>

			<?php if ( $is_new ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Default admin and submitter notifications are shown below. Save a notification to create the form first.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped table-view-list wek-notify-table">
				<thead>
					<tr>
						<th scope="col" class="wek-notify-table__status"><?php esc_html_e( 'Status', 'we-formkit' ); ?></th>
						<th scope="col" class="column-primary"><?php esc_html_e( 'Name', 'we-formkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Subject', 'we-formkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $notifications ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'No notifications yet.', 'we-formkit' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $notifications as $n ) : ?>
							<?php
							$nid       = (string) $n['id'];
							$edit_url  = add_query_arg( 'notification', $nid, $list_url );
							$active    = ! empty( $n['enabled'] );
							$can_quick = $form_id > 0 && ! $is_new;
							?>
							<tr>
								<td class="wek-notify-table__status">
									<?php if ( $can_quick ) : ?>
										<a
											href="<?php echo esc_url( self::notification_action_url( $form_id, $nid, 'toggle' ) ); ?>"
											class="wek-notify-status <?php echo $active ? 'wek-notify-status--active' : 'wek-notify-status--inactive'; ?>"
											title="<?php echo esc_attr( $active ? __( 'Click to deactivate', 'we-formkit' ) : __( 'Click to activate', 'we-formkit' ) ); ?>"
										>
											<span class="wek-notify-status__dot" aria-hidden="true"></span>
											<?php echo $active ? esc_html__( 'Active', 'we-formkit' ) : esc_html__( 'Inactive', 'we-formkit' ); ?>
										</a>
									<?php else : ?>
										<span class="wek-notify-status <?php echo $active ? 'wek-notify-status--active' : 'wek-notify-status--inactive'; ?>">
											<span class="wek-notify-status__dot" aria-hidden="true"></span>
											<?php echo $active ? esc_html__( 'Active', 'we-formkit' ) : esc_html__( 'Inactive', 'we-formkit' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="column-primary">
									<strong><a class="row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) $n['name'] ); ?></a></strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'we-formkit' ); ?></a>
											<?php if ( $can_quick ) : ?>
												|
											<?php endif; ?>
										</span>
										<?php if ( $can_quick ) : ?>
											<span class="duplicate">
												<a href="<?php echo esc_url( self::notification_action_url( $form_id, $nid, 'duplicate' ) ); ?>"><?php esc_html_e( 'Duplicate', 'we-formkit' ); ?></a> |
											</span>
											<span class="delete">
												<a
													class="submitdelete"
													href="<?php echo esc_url( self::notification_action_url( $form_id, $nid, 'delete' ) ); ?>"
													onclick="return confirm('<?php echo esc_js( __( 'Delete this notification?', 'we-formkit' ) ); ?>');"
												><?php esc_html_e( 'Delete', 'we-formkit' ); ?></a>
											</span>
										<?php endif; ?>
									</div>
									<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'we-formkit' ); ?></span></button>
								</td>
								<td><?php echo esc_html( (string) $n['subject'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="col" class="wek-notify-table__status"><?php esc_html_e( 'Status', 'we-formkit' ); ?></th>
						<th scope="col" class="column-primary"><?php esc_html_e( 'Name', 'we-formkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Subject', 'we-formkit' ); ?></th>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $n       Notification.
	 * @param array<string, mixed> $schema  Schema.
	 * @param bool                 $is_new  Whether this is a new notification row.
	 * @return void
	 */
	private static function render_notification_edit( $form_id, array $n, array $schema, $is_new ) {
		$list_url = admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $form_id . '&view=notifications' );
		$nid      = (string) $n['id'];
		$prefix   = 'wek_notification';
		?>
		<form method="post" class="wek-admin__settings-panel wek-admin__notifications" id="wek-notifications-form">
			<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<input type="hidden" name="wek_save_view" value="notifications" />
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $nid ); ?>" />

			<p>
				<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to notifications', 'we-formkit' ); ?></a>
			</p>

			<h2><?php echo $is_new ? esc_html__( 'Add notification', 'we-formkit' ) : esc_html( (string) $n['name'] ); ?></h2>

			<?php self::render_notification_fields_table( $n, $schema, $prefix, $nid ); ?>

			<?php submit_button( $is_new ? __( 'Add notification', 'we-formkit' ) : __( 'Update notification', 'we-formkit' ), 'primary', 'we_formkit_save_form' ); ?>
		</form>
		<script>
		(function () {
			var form = document.getElementById('wek-notifications-form');
			if (!form) return;
			function syncCard(card) {
				card.querySelectorAll('[data-wek-reveal]').forEach(function (sel) {
					var key = sel.getAttribute('data-wek-reveal');
					var val = sel.value;
					card.querySelectorAll('[data-wek-when^="' + key + ':"]').forEach(function (el) {
						var when = el.getAttribute('data-wek-when');
						el.hidden = when !== (key + ':' + val);
					});
				});
			}
			syncCard(form);
			form.querySelectorAll('[data-wek-reveal]').forEach(function (sel) {
				sel.addEventListener('change', function () { syncCard(form); });
			});
		})();
		</script>
		<?php
	}

	/**
	 * TinyMCE editor for notification HTML bodies.
	 *
	 * @param string $editor_id Editor DOM id.
	 * @param string $name      Textarea name.
	 * @param string $content   HTML content.
	 * @param int    $rows      Rows.
	 * @return void
	 */
	private static function render_notification_editor( $editor_id, $name, $content, $rows = 10 ) {
		$settings = array(
			'textarea_name' => $name,
			'textarea_rows' => max( 4, (int) $rows ),
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => true,
			'editor_class'  => 'wek-notify-editor',
			'tinymce'       => array(
				'toolbar1'      => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo,removeformat',
				'toolbar2'      => '',
				'content_css'   => false,
				'body_class'    => 'wek-notify-mail-body',
				'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3',
			),
		);
		wp_editor( (string) $content, $editor_id, $settings );
	}

	/**
	 * Enqueue smart-tag picker assets.
	 *
	 * @param array<string, mixed> $schema  Schema.
	 * @param string               $context `email` or `confirmation`.
	 * @return void
	 */
	private static function enqueue_smart_tags_assets( array $schema, $context = 'email' ) {
		wp_enqueue_script(
			'we-formkit-admin-smart-tags',
			WE_FORMKIT_URL . 'assets/js/admin-smart-tags.js',
			array(),
			WE_FORMKIT_VERSION,
			true
		);
		wp_localize_script(
			'we-formkit-admin-smart-tags',
			'weFormkitSmartTags',
			array(
				'i18n' => array(
					'insert' => __( 'Insert smart tag', 'we-formkit' ),
				),
			)
		);
		unset( $schema, $context );
	}

	/**
	 * Render a smart-tag insert control for an input / textarea / TinyMCE id.
	 *
	 * @param string               $target_id Target field or editor id.
	 * @param string               $mode      `input`, `textarea`, or `tinymce`.
	 * @param array<string, mixed> $schema    Schema for field tags.
	 * @param string               $context   `email` or `confirmation`.
	 * @return void
	 */
	private static function render_smart_tag_picker( $target_id, $mode, array $schema, $context = 'email' ) {
		$catalog = Merge_Tags::catalog( $schema, $context );
		if ( empty( $catalog ) ) {
			return;
		}

		$groups = array();
		foreach ( $catalog as $item ) {
			$group = isset( $item['group'] ) ? (string) $item['group'] : 'other';
			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array(
					'label' => isset( $item['group_label'] ) ? (string) $item['group_label'] : $group,
					'items' => array(),
				);
			}
			$groups[ $group ]['items'][] = $item;
		}

		$uid = 'wek-st-' . sanitize_html_class( (string) $target_id );
		?>
		<div
			class="wek-smart-tags"
			data-wek-smart-tags
			data-target="<?php echo esc_attr( (string) $target_id ); ?>"
			data-mode="<?php echo esc_attr( (string) $mode ); ?>"
			id="<?php echo esc_attr( $uid ); ?>"
		>
			<button type="button" class="button button-small wek-smart-tags__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-panel">
				<?php esc_html_e( 'Insert smart tag', 'we-formkit' ); ?>
			</button>
			<div class="wek-smart-tags__panel" id="<?php echo esc_attr( $uid ); ?>-panel" hidden>
				<?php foreach ( $groups as $group ) : ?>
					<div class="wek-smart-tags__group">
						<span class="wek-smart-tags__group-title"><?php echo esc_html( (string) $group['label'] ); ?></span>
						<ul class="wek-smart-tags__list">
							<?php foreach ( $group['items'] as $item ) : ?>
								<li>
									<button type="button" data-wek-tag="<?php echo esc_attr( (string) $item['tag'] ); ?>" title="<?php echo esc_attr( (string) $item['tag'] ); ?>">
										<?php echo esc_html( (string) $item['label'] ); ?>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $n       Notification.
	 * @param array<string, mixed> $schema  Schema.
	 * @param string               $prefix  Input name prefix.
	 * @param string               $nid     Notification ID (for field IDs).
	 * @return void
	 */
	private static function render_notification_fields_table( array $n, array $schema, $prefix, $nid ) {
		$email_fields = array();
		$value_fields = array();
		foreach ( Form_Schema::fields_by_id( $schema ) as $fid => $field ) {
			$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
			$label = isset( $field['label'] ) ? (string) $field['label'] : $fid;
			if ( 'email' === $type ) {
				$email_fields[ $fid ] = $label;
			}
			if ( ! in_array( $type, array( 'html', 'hidden' ), true ) ) {
				$value_fields[ $fid ] = $label . ' (' . $type . ')';
			}
		}
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Enabled', 'we-formkit' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="1" <?php checked( ! empty( $n['enabled'] ) ); ?> />
						<?php esc_html_e( 'Send this notification', 'we-formkit' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_name"><?php esc_html_e( 'Name', 'we-formkit' ); ?></label></th>
				<td><input class="regular-text" type="text" name="<?php echo esc_attr( $prefix ); ?>[name]" id="wek_n_<?php echo esc_attr( $nid ); ?>_name" value="<?php echo esc_attr( (string) $n['name'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Send to', 'we-formkit' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $prefix ); ?>[to_mode]" data-wek-reveal="to">
						<option value="email" <?php selected( $n['to_mode'], 'email' ); ?>><?php esc_html_e( 'Email address(es)', 'we-formkit' ); ?></option>
						<option value="field" <?php selected( $n['to_mode'], 'field' ); ?>><?php esc_html_e( 'Email field from form', 'we-formkit' ); ?></option>
					</select>
					<p class="wek-reveal" data-wek-when="to:email">
						<input class="regular-text" type="text" name="<?php echo esc_attr( $prefix ); ?>[to]" value="<?php echo esc_attr( (string) $n['to'] ); ?>" placeholder="admin@example.com, team@example.com" />
						<span class="description"><?php esc_html_e( 'Comma-separated. Empty uses the plugin default, then the site admin email.', 'we-formkit' ); ?></span>
					</p>
					<p class="wek-reveal" data-wek-when="to:field">
						<select name="<?php echo esc_attr( $prefix ); ?>[to_field]">
							<option value=""><?php esc_html_e( '— Select email field —', 'we-formkit' ); ?></option>
							<?php foreach ( $email_fields as $fid => $label ) : ?>
								<option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $n['to_field'], $fid ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="description"><?php esc_html_e( 'Typical for submitter confirmations.', 'we-formkit' ); ?></span>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_from_name"><?php esc_html_e( 'From name', 'we-formkit' ); ?></label></th>
				<td><input class="regular-text" type="text" name="<?php echo esc_attr( $prefix ); ?>[from_name]" id="wek_n_<?php echo esc_attr( $nid ); ?>_from_name" value="<?php echo esc_attr( (string) $n['from_name'] ); ?>" placeholder="<?php echo esc_attr( Settings::default_from_name() ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_from_email"><?php esc_html_e( 'From email', 'we-formkit' ); ?></label></th>
				<td>
					<input class="regular-text" type="email" name="<?php echo esc_attr( $prefix ); ?>[from_email]" id="wek_n_<?php echo esc_attr( $nid ); ?>_from_email" value="<?php echo esc_attr( (string) $n['from_email'] ); ?>" placeholder="<?php echo esc_attr( Settings::default_from_email() ); ?>" />
					<p class="description"><?php esc_html_e( 'Prefer an address on your site domain for deliverability.', 'we-formkit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Reply-To', 'we-formkit' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $prefix ); ?>[reply_to_mode]" data-wek-reveal="reply">
						<option value="none" <?php selected( $n['reply_to_mode'], 'none' ); ?>><?php esc_html_e( 'None', 'we-formkit' ); ?></option>
						<option value="email" <?php selected( $n['reply_to_mode'], 'email' ); ?>><?php esc_html_e( 'Fixed email', 'we-formkit' ); ?></option>
						<option value="field" <?php selected( $n['reply_to_mode'], 'field' ); ?>><?php esc_html_e( 'Email field from form', 'we-formkit' ); ?></option>
					</select>
					<p class="wek-reveal" data-wek-when="reply:email">
						<input class="regular-text" type="email" name="<?php echo esc_attr( $prefix ); ?>[reply_to]" value="<?php echo esc_attr( (string) $n['reply_to'] ); ?>" />
					</p>
					<p class="wek-reveal" data-wek-when="reply:field">
						<select name="<?php echo esc_attr( $prefix ); ?>[reply_to_field]">
							<option value=""><?php esc_html_e( '— First email field / any email value —', 'we-formkit' ); ?></option>
							<?php foreach ( $email_fields as $fid => $label ) : ?>
								<option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $n['reply_to_field'], $fid ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="description"><?php esc_html_e( 'Useful so admins can reply directly to the submitter.', 'we-formkit' ); ?></span>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_cc"><?php esc_html_e( 'CC', 'we-formkit' ); ?></label></th>
				<td><input class="regular-text" type="text" name="<?php echo esc_attr( $prefix ); ?>[cc]" id="wek_n_<?php echo esc_attr( $nid ); ?>_cc" value="<?php echo esc_attr( (string) $n['cc'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_bcc"><?php esc_html_e( 'BCC', 'we-formkit' ); ?></label></th>
				<td><input class="regular-text" type="text" name="<?php echo esc_attr( $prefix ); ?>[bcc]" id="wek_n_<?php echo esc_attr( $nid ); ?>_bcc" value="<?php echo esc_attr( (string) $n['bcc'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_subject"><?php esc_html_e( 'Subject', 'we-formkit' ); ?></label></th>
				<td>
					<input class="large-text" type="text" name="<?php echo esc_attr( $prefix ); ?>[subject]" id="wek_n_<?php echo esc_attr( $nid ); ?>_subject" value="<?php echo esc_attr( (string) $n['subject'] ); ?>" />
					<?php self::render_smart_tag_picker( 'wek_n_' . $nid . '_subject', 'input', $schema, 'email' ); ?>
				</td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_header"><?php esc_html_e( 'Header', 'we-formkit' ); ?></label></th>
				<td>
					<?php
					self::render_notification_editor(
						'wek_n_' . $nid . '_header',
						$prefix . '[header]',
						Form_Notifications::editor_content( (string) ( $n['header'] ?? '' ) ),
						6
					);
					self::render_smart_tag_picker( 'wek_n_' . $nid . '_header', 'tinymce', $schema, 'email' );
					?>
					<p class="description"><?php esc_html_e( 'Shown above the message (logo text, greeting). Supports merge tags. Formatting is kept in the email.', 'we-formkit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_message"><?php esc_html_e( 'Message', 'we-formkit' ); ?></label></th>
				<td>
					<?php
					self::render_notification_editor(
						'wek_n_' . $nid . '_message',
						$prefix . '[message]',
						Form_Notifications::editor_content( (string) $n['message'] ),
						14
					);
					self::render_smart_tag_picker( 'wek_n_' . $nid . '_message', 'tinymce', $schema, 'email' );
					?>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1–4: merge tags (do not translate the tags). */
								__( 'HTML email body. Use %1$s, %2$s, %3$s, %4$s, and other merge tags.', 'we-formkit' ),
								'{all_fields}',
								'{info_links}',
								'{form_title}',
								'{submission_url}'
							)
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: merge tag {all_fields} (do not translate the tag). */
							__( 'Fields in %s', 'we-formkit' ),
							'{all_fields}'
						)
					);
					?>
				</th>
				<td>
					<select name="<?php echo esc_attr( $prefix ); ?>[include_fields]" data-wek-reveal="fields">
						<option value="all" <?php selected( $n['include_fields'], 'all' ); ?>><?php esc_html_e( 'All fields', 'we-formkit' ); ?></option>
						<option value="selected" <?php selected( $n['include_fields'], 'selected' ); ?>><?php esc_html_e( 'Selected fields', 'we-formkit' ); ?></option>
						<option value="none" <?php selected( $n['include_fields'], 'none' ); ?>><?php esc_html_e( 'None (message only)', 'we-formkit' ); ?></option>
					</select>
					<div class="wek-reveal wek-notify-field-pick" data-wek-when="fields:selected">
						<?php if ( empty( $value_fields ) ) : ?>
							<p class="description"><?php esc_html_e( 'Add fields on the Fields tab first.', 'we-formkit' ); ?></p>
						<?php else : ?>
							<?php foreach ( $value_fields as $fid => $label ) : ?>
								<label class="wek-notify-field-pick__item">
									<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[field_ids][]" value="<?php echo esc_attr( $fid ); ?>" <?php checked( in_array( $fid, $n['field_ids'], true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th><label for="wek_n_<?php echo esc_attr( $nid ); ?>_footer"><?php esc_html_e( 'Footer', 'we-formkit' ); ?></label></th>
				<td>
					<?php
					self::render_notification_editor(
						'wek_n_' . $nid . '_footer',
						$prefix . '[footer]',
						Form_Notifications::editor_content( (string) $n['footer'] ),
						8
					);
					self::render_smart_tag_picker( 'wek_n_' . $nid . '_footer', 'tinymce', $schema, 'email' );
					?>
					<p class="description"><?php esc_html_e( 'Shown below the message (signature, legal line, contact). Supports merge tags and formatting.', 'we-formkit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Attachments', 'we-formkit' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[attach_uploads]" value="1" <?php checked( ! empty( $n['attach_uploads'] ) ); ?> />
						<?php esc_html_e( 'Attach uploaded files to this email', 'we-formkit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Signatures are shown inline in the message (not attached again as a file).', 'we-formkit' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param int    $form_id   Form ID.
	 * @param string $notify_id Notification ID.
	 * @param string $action    Action slug.
	 * @return string
	 */
	private static function notification_action_url( $form_id, $notify_id, $action ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'              => 'we-formkit-form',
					'form_id'           => (int) $form_id,
					'view'              => 'notifications',
					'wek_notify_action' => sanitize_key( $action ),
					'notification_id'   => sanitize_key( $notify_id ),
				),
				admin_url( 'admin.php' )
			),
			'wek_notify_' . sanitize_key( $action ) . '_' . (int) $form_id . '_' . sanitize_key( $notify_id ),
			'_wpnonce'
		);
	}

	/**
	 * @param int                        $form_id        Form ID.
	 * @param list<array<string, mixed>> $documents      Documents.
	 * @param list<array<string, mixed>> $notifications  Notifications (for attach targets).
	 * @param array<string, mixed>       $schema         Schema.
	 * @param bool                       $is_new         Whether new.
	 * @return void
	 */
	private static function render_view_documents( $form_id, array $documents, array $notifications, array $schema, $is_new ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['document'] ) ? sanitize_key( wp_unslash( (string) $_GET['document'] ) ) : '';

		if ( 'new' === $edit_id ) {
			self::render_document_edit( $form_id, Form_Info_Documents::blank(), $notifications, $schema, true );
			return;
		}
		if ( '' !== $edit_id ) {
			$current = Form_Info_Documents::find_by_id( $documents, $edit_id );
			if ( null === $current ) {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Document not found.', 'we-formkit' );
				echo '</p></div>';
				self::render_documents_list( $form_id, $documents, $is_new );
				return;
			}
			self::render_document_edit( $form_id, $current, $notifications, $schema, false );
			return;
		}

		self::render_documents_list( $form_id, $documents, $is_new );
	}

	/**
	 * @param int                        $form_id   Form ID.
	 * @param list<array<string, mixed>> $documents Documents.
	 * @param bool                       $is_new    Whether new.
	 * @return void
	 */
	private static function render_documents_list( $form_id, array $documents, $is_new ) {
		$list_url = admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $form_id . '&view=documents' );
		$add_url  = add_query_arg( 'document', 'new', $list_url );
		?>
		<div class="wek-admin__settings-panel wek-admin__documents">
			<div class="wek-notify-list__header">
				<h2><?php esc_html_e( 'Info documents', 'we-formkit' ); ?></h2>
				<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'we-formkit' ); ?></a>
			</div>

			<p class="description">
				<?php esc_html_e( 'Offer download links and/or email attachments when conditions match (for example a selected radio or checkbox option). The same file is only delivered once even if multiple rules match.', 'we-formkit' ); ?>
			</p>

			<?php if ( $is_new ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Save a document to create the form first.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped table-view-list wek-notify-table">
				<thead>
					<tr>
						<th scope="col" class="wek-notify-table__status"><?php esc_html_e( 'Status', 'we-formkit' ); ?></th>
						<th scope="col" class="column-primary"><?php esc_html_e( 'Name', 'we-formkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'When', 'we-formkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Delivery', 'we-formkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $documents ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'No documents yet. Add one to attach info PDFs to options or always.', 'we-formkit' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $documents as $doc ) : ?>
							<?php
							$did       = (string) $doc['id'];
							$edit_url  = add_query_arg( 'document', $did, $list_url );
							$active    = ! empty( $doc['enabled'] );
							$can_quick = $form_id > 0 && ! $is_new;
							$when_lbl  = empty( $doc['when'] ) ? __( 'Always', 'we-formkit' ) : __( 'When conditions match', 'we-formkit' );
							$delivery  = array();
							if ( ! empty( $doc['show_download'] ) ) {
								$delivery[] = __( 'Download link', 'we-formkit' );
							}
							if ( ! empty( $doc['attach_to_email'] ) ) {
								$delivery[] = __( 'Email attachment', 'we-formkit' );
							}
							?>
							<tr>
								<td class="wek-notify-table__status">
									<?php if ( $can_quick ) : ?>
										<a
											href="<?php echo esc_url( self::document_action_url( $form_id, $did, 'toggle' ) ); ?>"
											class="wek-notify-status <?php echo $active ? 'wek-notify-status--active' : 'wek-notify-status--inactive'; ?>"
											title="<?php echo esc_attr( $active ? __( 'Click to deactivate', 'we-formkit' ) : __( 'Click to activate', 'we-formkit' ) ); ?>"
										>
											<span class="wek-notify-status__dot" aria-hidden="true"></span>
											<?php echo $active ? esc_html__( 'Active', 'we-formkit' ) : esc_html__( 'Inactive', 'we-formkit' ); ?>
										</a>
									<?php else : ?>
										<span class="wek-notify-status <?php echo $active ? 'wek-notify-status--active' : 'wek-notify-status--inactive'; ?>">
											<span class="wek-notify-status__dot" aria-hidden="true"></span>
											<?php echo $active ? esc_html__( 'Active', 'we-formkit' ) : esc_html__( 'Inactive', 'we-formkit' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="column-primary">
									<strong><a class="row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) $doc['name'] ); ?></a></strong>
									<div class="row-actions">
										<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'we-formkit' ); ?></a><?php echo $can_quick ? ' |' : ''; ?></span>
										<?php if ( $can_quick ) : ?>
											<span class="delete">
												<a
													class="submitdelete"
													href="<?php echo esc_url( self::document_action_url( $form_id, $did, 'delete' ) ); ?>"
													onclick="return confirm('<?php echo esc_js( __( 'Delete this document?', 'we-formkit' ) ); ?>');"
												><?php esc_html_e( 'Delete', 'we-formkit' ); ?></a>
											</span>
										<?php endif; ?>
									</div>
								</td>
								<td><?php echo esc_html( $when_lbl ); ?></td>
								<td><?php echo esc_html( $delivery ? implode( ' · ', $delivery ) : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @param int                        $form_id       Form ID.
	 * @param array<string, mixed>       $doc           Document.
	 * @param list<array<string, mixed>> $notifications Notifications.
	 * @param array<string, mixed>       $schema        Schema.
	 * @param bool                       $is_new        Whether new document.
	 * @return void
	 */
	private static function render_document_edit( $form_id, array $doc, array $notifications, array $schema, $is_new ) {
		$list_url  = admin_url( 'admin.php?page=we-formkit-form&form_id=' . (int) $form_id . '&view=documents' );
		$did       = (string) $doc['id'];
		$prefix    = 'wek_document';
		$aid       = (int) $doc['attachment_id'];
		$file_url  = $aid > 0 ? wp_get_attachment_url( $aid ) : '';
		$file_url  = is_string( $file_url ) ? $file_url : '';
		$when      = isset( $doc['when'] ) && is_array( $doc['when'] ) ? $doc['when'] : null;
		$rules     = $when && ! empty( $when['rules'] ) && is_array( $when['rules'] ) ? $when['rules'] : array();
		$relation  = $when && isset( $when['relation'] ) && 'OR' === $when['relation'] ? 'OR' : 'AND';
		$when_mode = empty( $rules ) ? 'always' : 'conditional';

		$fields = Form_Schema::fields_by_id( $schema );
		?>
		<form method="post" class="wek-admin__settings-panel wek-admin__documents" id="wek-documents-form">
			<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<input type="hidden" name="wek_save_view" value="documents" />
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $did ); ?>" />

			<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to documents', 'we-formkit' ); ?></a></p>
			<h2><?php echo $is_new ? esc_html__( 'Add document', 'we-formkit' ) : esc_html( (string) $doc['name'] ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Enabled', 'we-formkit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="1" <?php checked( ! empty( $doc['enabled'] ) ); ?> />
							<?php esc_html_e( 'Use this document when conditions match', 'we-formkit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="wek_d_<?php echo esc_attr( $did ); ?>_name"><?php esc_html_e( 'Name', 'we-formkit' ); ?></label></th>
					<td><input class="regular-text" type="text" name="<?php echo esc_attr( $prefix ); ?>[name]" id="wek_d_<?php echo esc_attr( $did ); ?>_name" value="<?php echo esc_attr( (string) $doc['name'] ); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'File', 'we-formkit' ); ?></th>
					<td>
						<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[attachment_id]" id="wek_doc_attachment_id" value="<?php echo esc_attr( (string) $aid ); ?>" />
						<p id="wek_doc_file_label" class="description">
							<?php
							if ( $aid > 0 && '' !== $file_url ) {
								echo esc_html( basename( $file_url ) ) . ' ';
								echo '<a href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open', 'we-formkit' ) . '</a>';
							} else {
								esc_html_e( 'No file selected.', 'we-formkit' );
							}
							?>
						</p>
						<p>
							<button type="button" class="button" id="wek_doc_pick"><?php esc_html_e( 'Select from Media Library', 'we-formkit' ); ?></button>
							<button type="button" class="button" id="wek_doc_clear" <?php disabled( $aid <= 0 ); ?>><?php esc_html_e( 'Clear', 'we-formkit' ); ?></button>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Delivery', 'we-formkit' ); ?></th>
					<td>
						<label style="display:block;margin:0.2rem 0;">
							<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[show_download]" value="1" <?php checked( ! empty( $doc['show_download'] ) ); ?> />
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: merge tag {info_links} (do not translate the tag). */
									__( 'Show download link after submit (and via %s in emails)', 'we-formkit' ),
									'{info_links}'
								)
							);
							?>
						</label>
						<label style="display:block;margin:0.2rem 0;">
							<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[attach_to_email]" value="1" <?php checked( ! empty( $doc['attach_to_email'] ) ); ?> data-wek-reveal="attach" />
							<?php esc_html_e( 'Attach file to email notification(s)', 'we-formkit' ); ?>
						</label>
						<div class="wek-reveal" data-wek-when="attach:1" style="margin-top:0.5rem;padding:0.5rem;border:1px solid #c3c4c7;background:#fff;">
							<p class="description"><?php esc_html_e( 'Leave all unchecked to attach to every enabled notification. Prefer the submitter confirmation.', 'we-formkit' ); ?></p>
							<?php foreach ( $notifications as $n ) : ?>
								<label style="display:block;margin:0.2rem 0;">
									<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[notification_ids][]" value="<?php echo esc_attr( (string) $n['id'] ); ?>" <?php checked( in_array( (string) $n['id'], $doc['notification_ids'], true ) ); ?> />
									<?php echo esc_html( (string) $n['name'] ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'When', 'we-formkit' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $prefix ); ?>[when_mode]" id="wek_doc_when_mode" data-wek-reveal="when">
							<option value="always" <?php selected( $when_mode, 'always' ); ?>><?php esc_html_e( 'Always', 'we-formkit' ); ?></option>
							<option value="conditional" <?php selected( $when_mode, 'conditional' ); ?>><?php esc_html_e( 'When conditions match', 'we-formkit' ); ?></option>
						</select>
						<div class="wek-reveal" data-wek-when="when:conditional" style="margin-top:0.75rem;">
							<p>
								<label>
									<input type="radio" name="<?php echo esc_attr( $prefix ); ?>[when][relation]" value="AND" <?php checked( $relation, 'AND' ); ?> />
									<?php esc_html_e( 'Match all of the following (AND)', 'we-formkit' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="<?php echo esc_attr( $prefix ); ?>[when][relation]" value="OR" <?php checked( $relation, 'OR' ); ?> />
									<?php esc_html_e( 'Match any of the following (OR)', 'we-formkit' ); ?>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Pick a field and value. For checkboxes / multi-select, use “contains”.', 'we-formkit' ); ?></p>
							<?php
							$field_options_map = array();
							foreach ( $fields as $fid => $field ) {
								$opts = array();
								if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
									foreach ( $field['options'] as $opt ) {
										if ( ! is_array( $opt ) ) {
											continue;
										}
										$ov = isset( $opt['value'] ) ? (string) $opt['value'] : '';
										$ol = isset( $opt['label'] ) ? (string) $opt['label'] : $ov;
										if ( '' === $ov && '' === $ol ) {
											continue;
										}
										$opts[] = array(
											'value' => $ov,
											'label' => '' !== $ol ? $ol : $ov,
										);
									}
								}
								$field_options_map[ $fid ] = $opts;
							}
							$rule_rows = $rules ? $rules : array(
								array(
									'field' => '',
									'op'    => 'equals',
									'value' => '',
								),
							);
							foreach ( $rule_rows as $r_index => $rule ) :
								$rule_field = (string) ( $rule['field'] ?? '' );
								$rule_op    = (string) ( $rule['op'] ?? 'equals' );
								$rule_val   = (string) ( $rule['value'] ?? '' );
								$rule_opts  = isset( $field_options_map[ $rule_field ] ) ? $field_options_map[ $rule_field ] : array();
								$use_select = ! empty( $rule_opts ) && in_array( $rule_op, array( 'equals', 'not_equals', 'contains' ), true );
								$hide_value = in_array( $rule_op, array( 'is_checked', 'is_not_checked', 'is_not_empty', 'is_empty' ), true );
								?>
								<div class="wek-doc-rule" data-wek-doc-rule>
									<select class="wek-doc-rule__field" name="<?php echo esc_attr( $prefix ); ?>[when][rules][<?php echo (int) $r_index; ?>][field]" aria-label="<?php esc_attr_e( 'Depends on field', 'we-formkit' ); ?>">
										<option value=""><?php esc_html_e( '— Select field —', 'we-formkit' ); ?></option>
										<?php foreach ( $fields as $fid => $field ) : ?>
											<?php
											$type = isset( $field['type'] ) ? (string) $field['type'] : '';
											if ( in_array( $type, array( 'html', 'hidden' ), true ) ) {
												continue;
											}
											$label = isset( $field['label'] ) ? (string) $field['label'] : $fid;
											?>
											<option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $rule_field, $fid ); ?>><?php echo esc_html( $label . ' (' . $type . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
									<select class="wek-doc-rule__op" name="<?php echo esc_attr( $prefix ); ?>[when][rules][<?php echo (int) $r_index; ?>][op]" aria-label="<?php esc_attr_e( 'Operator', 'we-formkit' ); ?>">
										<option value="equals" <?php selected( $rule_op, 'equals' ); ?>><?php esc_html_e( 'equals', 'we-formkit' ); ?></option>
										<option value="not_equals" <?php selected( $rule_op, 'not_equals' ); ?>><?php esc_html_e( 'not equals', 'we-formkit' ); ?></option>
										<option value="contains" <?php selected( $rule_op, 'contains' ); ?>><?php esc_html_e( 'contains', 'we-formkit' ); ?></option>
										<option value="is_checked" <?php selected( $rule_op, 'is_checked' ); ?>><?php esc_html_e( 'is checked', 'we-formkit' ); ?></option>
										<option value="is_not_checked" <?php selected( $rule_op, 'is_not_checked' ); ?>><?php esc_html_e( 'is not checked', 'we-formkit' ); ?></option>
										<option value="is_not_empty" <?php selected( $rule_op, 'is_not_empty' ); ?>><?php esc_html_e( 'is not empty', 'we-formkit' ); ?></option>
										<option value="is_empty" <?php selected( $rule_op, 'is_empty' ); ?>><?php esc_html_e( 'is empty', 'we-formkit' ); ?></option>
									</select>
									<div class="wek-doc-rule__value" <?php echo $hide_value ? 'hidden' : ''; ?>>
										<?php if ( $use_select ) : ?>
											<select name="<?php echo esc_attr( $prefix ); ?>[when][rules][<?php echo (int) $r_index; ?>][value]" aria-label="<?php esc_attr_e( 'Value', 'we-formkit' ); ?>">
												<option value=""><?php esc_html_e( '— Select value —', 'we-formkit' ); ?></option>
												<?php foreach ( $rule_opts as $opt ) : ?>
													<option value="<?php echo esc_attr( $opt['value'] ); ?>" <?php selected( $rule_val, $opt['value'] ); ?>><?php echo esc_html( $opt['label'] ); ?></option>
												<?php endforeach; ?>
											</select>
										<?php else : ?>
											<input type="text" name="<?php echo esc_attr( $prefix ); ?>[when][rules][<?php echo (int) $r_index; ?>][value]" value="<?php echo esc_attr( $rule_val ); ?>" placeholder="<?php esc_attr_e( 'Value', 'we-formkit' ); ?>" aria-label="<?php esc_attr_e( 'Value', 'we-formkit' ); ?>" />
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
							<script type="application/json" id="wek-doc-field-options"><?php echo wp_json_encode( $field_options_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON blob for admin JS. ?></script>
						</div>
					</td>
				</tr>
			</table>

			<?php submit_button( $is_new ? __( 'Add document', 'we-formkit' ) : __( 'Update document', 'we-formkit' ), 'primary', 'we_formkit_save_form' ); ?>
		</form>
		<script>
		(function () {
			var form = document.getElementById('wek-documents-form');
			if (!form) return;

			function syncReveal() {
				var whenMode = form.querySelector('#wek_doc_when_mode');
				form.querySelectorAll('[data-wek-when]').forEach(function (el) {
					var when = el.getAttribute('data-wek-when') || '';
					var parts = when.split(':');
					var key = parts[0];
					var val = parts[1];
					var ok = false;
					if (key === 'when' && whenMode) {
						ok = whenMode.value === val;
					} else if (key === 'attach') {
						var cb = form.querySelector('[data-wek-reveal="attach"]');
						ok = cb && cb.checked;
					}
					el.hidden = !ok;
				});
			}
			form.querySelectorAll('[data-wek-reveal], #wek_doc_when_mode').forEach(function (el) {
				el.addEventListener('change', syncReveal);
			});
			syncReveal();

			var fieldOptions = {};
			var optionsNode = document.getElementById('wek-doc-field-options');
			if (optionsNode && optionsNode.textContent) {
				try {
					fieldOptions = JSON.parse(optionsNode.textContent) || {};
				} catch (e) {
					fieldOptions = {};
				}
			}
			var i18nSelectValue = <?php echo wp_json_encode( __( '— Select value —', 'we-formkit' ) ); ?>;
			var i18nValue = <?php echo wp_json_encode( __( 'Value', 'we-formkit' ) ); ?>;

			function paintDocRuleValue(rule) {
				var fieldSel = rule.querySelector('.wek-doc-rule__field');
				var opSel = rule.querySelector('.wek-doc-rule__op');
				var valueWrap = rule.querySelector('.wek-doc-rule__value');
				if (!fieldSel || !opSel || !valueWrap) return;

				var op = opSel.value || 'equals';
				var fieldId = fieldSel.value || '';
				var nameAttr = fieldSel.getAttribute('name') || '';
				var valueName = nameAttr.replace('[field]', '[value]');
				var previous = '';
				var existing = valueWrap.querySelector('select, input');
				if (existing) {
					previous = existing.value || '';
				}

				if (op === 'is_checked' || op === 'is_not_checked' || op === 'is_not_empty' || op === 'is_empty') {
					valueWrap.hidden = true;
					valueWrap.innerHTML = '';
					var hidden = document.createElement('input');
					hidden.type = 'hidden';
					hidden.name = valueName;
					hidden.value = '';
					valueWrap.appendChild(hidden);
					return;
				}

				valueWrap.hidden = false;
				valueWrap.innerHTML = '';
				var options = fieldOptions[fieldId] || [];
				if (options.length && (op === 'equals' || op === 'not_equals' || op === 'contains')) {
					var sel = document.createElement('select');
					sel.name = valueName;
					sel.setAttribute('aria-label', i18nValue);
					var placeholder = document.createElement('option');
					placeholder.value = '';
					placeholder.textContent = i18nSelectValue;
					sel.appendChild(placeholder);
					options.forEach(function (opt) {
						var o = document.createElement('option');
						o.value = opt.value != null ? String(opt.value) : '';
						o.textContent = opt.label != null ? String(opt.label) : o.value;
						if (previous && previous === o.value) {
							o.selected = true;
						}
						sel.appendChild(o);
					});
					valueWrap.appendChild(sel);
					return;
				}

				var input = document.createElement('input');
				input.type = 'text';
				input.name = valueName;
				input.value = previous;
				input.placeholder = i18nValue;
				input.setAttribute('aria-label', i18nValue);
				valueWrap.appendChild(input);
			}

			form.querySelectorAll('[data-wek-doc-rule]').forEach(function (rule) {
				paintDocRuleValue(rule);
				rule.querySelectorAll('.wek-doc-rule__field, .wek-doc-rule__op').forEach(function (el) {
					el.addEventListener('change', function () {
						paintDocRuleValue(rule);
					});
				});
			});

			var pick = document.getElementById('wek_doc_pick');
			var clear = document.getElementById('wek_doc_clear');
			var idInput = document.getElementById('wek_doc_attachment_id');
			var label = document.getElementById('wek_doc_file_label');
			var frame;
			var mediaMissing = <?php echo wp_json_encode( __( 'Media Library could not be loaded. Please refresh the page.', 'we-formkit' ) ); ?>;
			var openLabel = <?php echo wp_json_encode( __( 'Open', 'we-formkit' ) ); ?>;
			var noFile = <?php echo wp_json_encode( __( 'No file selected.', 'we-formkit' ) ); ?>;
			var needFile = <?php echo wp_json_encode( __( 'Please select a file from the Media Library.', 'we-formkit' ) ); ?>;

			if (pick && idInput && label) {
				pick.addEventListener('click', function (e) {
					e.preventDefault();
					if (typeof wp === 'undefined' || !wp.media) {
						window.alert(mediaMissing);
						return;
					}
					if (frame) {
						frame.open();
						return;
					}
					frame = wp.media({
						title: <?php echo wp_json_encode( __( 'Select document', 'we-formkit' ) ); ?>,
						button: { text: <?php echo wp_json_encode( __( 'Use this file', 'we-formkit' ) ); ?> },
						multiple: false
					});
					frame.on('select', function () {
						var file = frame.state().get('selection').first().toJSON();
						idInput.value = String(file.id || 0);
						var name = file.filename || file.title || ('#' + file.id);
						label.innerHTML = '';
						label.appendChild(document.createTextNode(name + ' '));
						if (file.url) {
							var a = document.createElement('a');
							a.href = file.url;
							a.target = '_blank';
							a.rel = 'noopener noreferrer';
							a.textContent = openLabel;
							label.appendChild(a);
						}
						if (clear) clear.disabled = false;
					});
					frame.open();
				});
			}
			if (clear && idInput && label) {
				clear.addEventListener('click', function (e) {
					e.preventDefault();
					idInput.value = '0';
					label.textContent = noFile;
					clear.disabled = true;
				});
			}
			form.addEventListener('submit', function (e) {
				var aid = idInput ? parseInt(idInput.value || '0', 10) : 0;
				if (!aid) {
					e.preventDefault();
					window.alert(needFile);
					if (pick) pick.focus();
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $doc_id  Document ID.
	 * @param string $action  Action slug.
	 * @return string
	 */
	private static function document_action_url( $form_id, $doc_id, $action ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'           => 'we-formkit-form',
					'form_id'        => (int) $form_id,
					'view'           => 'documents',
					'wek_doc_action' => sanitize_key( $action ),
					'document_id'    => sanitize_key( $doc_id ),
				),
				admin_url( 'admin.php' )
			),
			'wek_doc_' . sanitize_key( $action ) . '_' . (int) $form_id . '_' . sanitize_key( $doc_id ),
			'_wpnonce'
		);
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $confirm Confirmation settings.
	 * @param bool                 $is_new  Whether new.
	 * @return void
	 */
	private static function render_view_confirmations( $form_id, $confirm, $is_new ) {
		unset( $is_new );
		if ( ! is_array( $confirm ) ) {
			$confirm = Form_Schema::get_confirmation( $form_id );
		}
		$mode        = isset( $confirm['mode'] ) ? (string) $confirm['mode'] : 'message';
		$message     = isset( $confirm['message'] ) ? (string) $confirm['message'] : '';
		$redirect    = isset( $confirm['redirect_url'] ) ? (string) $confirm['redirect_url'] : '';
		$page_id     = isset( $confirm['page_id'] ) ? (int) $confirm['page_id'] : 0;
		$placeholder = __( 'Thank you. Your form was submitted successfully.', 'we-formkit' );
		$schema      = $form_id > 0 ? Form_Schema::get( $form_id ) : self::blank_schema();
		if ( ! is_array( $schema ) ) {
			$schema = self::blank_schema();
		}
		?>
	<form method="post" class="wek-admin__settings-panel" id="wek-confirmations-form">
		<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
		<input type="hidden" name="wek_save_view" value="confirmations" />

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wek_confirmation_mode"><?php esc_html_e( 'After submit', 'we-formkit' ); ?></label></th>
				<td>
					<select name="wek_confirmation_mode" id="wek_confirmation_mode">
						<option value="message" <?php selected( $mode, 'message' ); ?>><?php esc_html_e( 'Show message', 'we-formkit' ); ?></option>
						<option value="redirect" <?php selected( $mode, 'redirect' ); ?>><?php esc_html_e( 'Redirect to URL', 'we-formkit' ); ?></option>
						<option value="page" <?php selected( $mode, 'page' ); ?>><?php esc_html_e( 'Show a WordPress page', 'we-formkit' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="wek-confirm-row wek-confirm-row--message">
				<th><label for="wek_confirmation_message"><?php esc_html_e( 'Confirmation message', 'we-formkit' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="4" name="wek_confirmation_message" id="wek_confirmation_message" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
					<?php self::render_smart_tag_picker( 'wek_confirmation_message', 'textarea', $schema, 'confirmation' ); ?>
					<p class="description"><?php esc_html_e( 'Shown on the page after a successful submission. Leave empty to use the default message. Smart tags are replaced on submit.', 'we-formkit' ); ?></p>
				</td>
			</tr>
			<tr class="wek-confirm-row wek-confirm-row--redirect">
				<th><label for="wek_confirmation_redirect"><?php esc_html_e( 'Redirect URL', 'we-formkit' ); ?></label></th>
				<td>
					<input type="text" class="large-text" name="wek_confirmation_redirect" id="wek_confirmation_redirect" value="<?php echo esc_attr( $redirect ); ?>" placeholder="https://" autocomplete="off" />
					<?php self::render_smart_tag_picker( 'wek_confirmation_redirect', 'input', $schema, 'confirmation' ); ?>
					<p class="description"><?php esc_html_e( 'Smart tags in the URL are replaced on submit (use with care).', 'we-formkit' ); ?></p>
				</td>
			</tr>
			<tr class="wek-confirm-row wek-confirm-row--page">
				<th><label for="wek_confirmation_page"><?php esc_html_e( 'Thank-you page', 'we-formkit' ); ?></label></th>
				<td>
					<?php
					echo wp_dropdown_pages( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes options.
						array(
							'name'              => 'wek_confirmation_page',
							'id'                => 'wek_confirmation_page',
							'selected'          => $page_id,
							'show_option_none'  => __( '— Select a page —', 'we-formkit' ),
							'option_none_value' => '0',
							'echo'              => 0,
						)
					);
					?>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save confirmation', 'we-formkit' ), 'primary', 'we_formkit_save_form' ); ?>
	</form>
	<script>
	(function () {
		var form = document.getElementById('wek-confirmations-form');
		if (!form) return;
		var select = form.querySelector('#wek_confirmation_mode');
		function sync() {
			var mode = select ? select.value : 'message';
			form.querySelectorAll('.wek-confirm-row').forEach(function (row) {
				var show = row.classList.contains('wek-confirm-row--' + mode) ||
					(mode === 'message' && row.classList.contains('wek-confirm-row--message'));
				if (mode === 'redirect') {
					show = row.classList.contains('wek-confirm-row--redirect');
				} else if (mode === 'page') {
					show = row.classList.contains('wek-confirm-row--page');
				} else {
					show = row.classList.contains('wek-confirm-row--message');
				}
				row.hidden = !show;
			});
		}
		if (select) select.addEventListener('change', sync);
		sync();
	})();
	</script>
		<?php
	}

	/**
	 * @return void
	 */
	private static function save_form() {
		if ( ! isset( $_POST['we_formkit_save_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['we_formkit_save_nonce'] ) ), 'we_formkit_save_form' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$view    = isset( $_POST['wek_save_view'] ) ? sanitize_key( wp_unslash( (string) $_POST['wek_save_view'] ) ) : 'fields';
		if ( ! in_array( $view, array( 'fields', 'settings', 'notifications', 'documents', 'confirmations' ), true ) ) {
			$view = 'fields';
		}

		$existing = $form_id ? get_post( $form_id ) : null;
		if ( $form_id && ( ! $existing || Post_Types::FORM !== $existing->post_type ) ) {
			wp_die( esc_html__( 'Form not found.', 'we-formkit' ) );
		}

		if ( 'fields' === $view ) {
			self::save_view_fields( $form_id );
			return;
		}
		if ( 'settings' === $view ) {
			self::save_view_settings( $form_id );
			return;
		}
		if ( 'notifications' === $view ) {
			self::save_view_notifications( $form_id );
			return;
		}
		if ( 'documents' === $view ) {
			self::save_view_documents( $form_id );
			return;
		}
		self::save_view_confirmations( $form_id );
	}

	/**
	 * @param int $form_id Form ID (0 = create).
	 * @return void
	 */
	private static function save_view_fields( $form_id ) {
		// Nonce verified in save_form().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$title   = isset( $_POST['wek_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wek_title'] ) ) : '';
		$raw     = isset( $_POST['wek_schema_json'] ) ? wp_unslash( (string) $_POST['wek_schema_json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded = json_decode( $raw, true );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		if ( $form_id > 0 ) {
			$current = Form_Schema::get( $form_id );
			if ( ! isset( $decoded['intro'] ) && isset( $current['intro'] ) ) {
				$decoded['intro'] = $current['intro'];
			}
			if ( ! isset( $decoded['show_title'] ) && array_key_exists( 'show_title', $current ) ) {
				$decoded['show_title'] = $current['show_title'];
			}
			if ( ! isset( $decoded['show_intro'] ) && array_key_exists( 'show_intro', $current ) ) {
				$decoded['show_intro'] = $current['show_intro'];
			}
		}

		$schema = Form_Schema::normalize( $decoded );

		if ( $form_id > 0 ) {
			$post_title = '' !== $title ? $title : get_the_title( $form_id );
			if ( '' === $post_title ) {
				$post_title = __( 'Untitled form', 'we-formkit' );
			}
			$schema['title'] = $post_title;
			wp_update_post(
				array(
					'ID'         => $form_id,
					'post_title' => $post_title,
				)
			);
			Form_Schema::save( $form_id, $schema );
		} else {
			$post_title      = '' !== $title ? $title : ( ! empty( $decoded['title'] ) ? (string) $decoded['title'] : __( 'New form', 'we-formkit' ) );
			$schema['title'] = $post_title;
			$form_id         = (int) wp_insert_post(
				array(
					'post_type'   => Post_Types::FORM,
					'post_status' => 'publish',
					'post_title'  => $post_title,
				),
				true
			);
			if ( ! $form_id || is_wp_error( $form_id ) ) {
				wp_die( esc_html__( 'Could not create form.', 'we-formkit' ) );
			}
			Form_Schema::save( $form_id, $schema );
			$slug = sanitize_title( $post_title );
			if ( '' === $slug ) {
				$slug = 'form-' . $form_id;
			}
			update_post_meta( $form_id, Form_Schema::META_SLUG, $slug );
			Form_Schema::set_secret( $form_id, false );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pagination = isset( $_POST['wek_pagination'] ) ? sanitize_key( wp_unslash( (string) $_POST['wek_pagination'] ) ) : 'single';
		Form_Schema::set_pagination( $form_id, $pagination );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		Form_Schema::set_submit_button(
			$form_id,
			array(
				'label'         => isset( $_POST['wek_submit_label'] ) ? wp_unslash( (string) $_POST['wek_submit_label'] ) : '',
				'icon_svg'      => isset( $_POST['wek_submit_icon_svg'] ) ? wp_unslash( (string) $_POST['wek_submit_icon_svg'] ) : '',
				'icon_position' => isset( $_POST['wek_submit_icon_position'] ) ? wp_unslash( (string) $_POST['wek_submit_icon_position'] ) : 'before',
				'width'         => isset( $_POST['wek_submit_width'] ) ? wp_unslash( (string) $_POST['wek_submit_width'] ) : 'auto',
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=fields&saved=1' ) );
		exit;
	}

	/**
	 * @param int $form_id Form ID (0 = create).
	 * @return void
	 */
	private static function save_view_settings( $form_id ) {
		// Nonce verified in save_form().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$title          = isset( $_POST['wek_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wek_title'] ) ) : '';
		$slug           = isset( $_POST['wek_slug'] ) ? sanitize_title( wp_unslash( (string) $_POST['wek_slug'] ) ) : '';
		$intro          = isset( $_POST['wek_intro'] ) ? wp_kses_post( wp_unslash( (string) $_POST['wek_intro'] ) ) : '';
		$privacy        = isset( $_POST['wek_privacy_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['wek_privacy_url'] ) ) : '';
		$secret_enabled = ! empty( $_POST['wek_secret_enabled'] );
		$style_input    = isset( $_POST['wek_style'] ) && is_array( $_POST['wek_style'] ) ? wp_unslash( $_POST['wek_style'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $title ) {
			wp_die( esc_html__( 'Title is required.', 'we-formkit' ) );
		}

		if ( $form_id > 0 ) {
			wp_update_post(
				array(
					'ID'         => $form_id,
					'post_title' => $title,
				)
			);
			$schema          = Form_Schema::get( $form_id );
			$schema['title'] = $title;
			$schema['intro'] = $intro;
			Form_Schema::save( $form_id, $schema );
		} else {
			$form_id = (int) wp_insert_post(
				array(
					'post_type'   => Post_Types::FORM,
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				true
			);
			if ( ! $form_id || is_wp_error( $form_id ) ) {
				wp_die( esc_html__( 'Could not create form.', 'we-formkit' ) );
			}
			$schema          = self::blank_schema( $title );
			$schema['intro'] = $intro;
			Form_Schema::save( $form_id, $schema );
		}

		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}
		update_post_meta( $form_id, Form_Schema::META_SLUG, $slug );
		update_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, $privacy );
		Form_Schema::set_secret( $form_id, $secret_enabled );
		Form_Style::save( $form_id, Form_Style::sanitize_from_request( is_array( $style_input ) ? $style_input : array() ) );

		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=settings&saved=1' ) );
		exit;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private static function save_view_notifications( $form_id ) {
		$form_id = self::ensure_form_exists( $form_id );
		// Nonce verified in save_form().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted = isset( $_POST['wek_notification'] ) && is_array( $_POST['wek_notification'] ) ? wp_unslash( $_POST['wek_notification'] ) : array();
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}
		if ( empty( $posted['enabled'] ) ) {
			$posted['enabled'] = false;
		} else {
			$posted['enabled'] = true;
		}
		if ( empty( $posted['attach_uploads'] ) ) {
			$posted['attach_uploads'] = false;
		} else {
			$posted['attach_uploads'] = true;
		}
		if ( isset( $posted['field_ids'] ) && ! is_array( $posted['field_ids'] ) ) {
			$posted['field_ids'] = array_filter( array_map( 'strval', (array) $posted['field_ids'] ) );
		}

		$list       = Form_Notifications::get( $form_id );
		$normalized = Form_Notifications::normalize_one( $posted );
		$list       = Form_Notifications::upsert( $list, $normalized );
		Form_Notifications::save( $form_id, $list );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'we-formkit-form',
					'form_id'      => $form_id,
					'view'         => 'notifications',
					'notification' => (string) $normalized['id'],
					'saved'        => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private static function save_view_documents( $form_id ) {
		$form_id = self::ensure_form_exists( $form_id );
		// Nonce verified in save_form().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted = isset( $_POST['wek_document'] ) && is_array( $_POST['wek_document'] ) ? wp_unslash( $_POST['wek_document'] ) : array();
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		$posted['enabled']         = ! empty( $posted['enabled'] );
		$posted['show_download']   = ! empty( $posted['show_download'] );
		$posted['attach_to_email'] = ! empty( $posted['attach_to_email'] );
		$posted['attachment_id']   = isset( $posted['attachment_id'] ) ? absint( $posted['attachment_id'] ) : 0;

		if ( $posted['attachment_id'] <= 0 ) {
			$doc_id = isset( $posted['id'] ) ? sanitize_key( (string) $posted['id'] ) : '';
			$list   = Form_Info_Documents::get( $form_id );
			$exists = '' !== $doc_id && null !== Form_Info_Documents::find_by_id( $list, $doc_id );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'we-formkit-form',
						'form_id'  => $form_id,
						'view'     => 'documents',
						'document' => $exists ? $doc_id : 'new',
						'error'    => 'no_file',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( isset( $posted['notification_ids'] ) && ! is_array( $posted['notification_ids'] ) ) {
			$posted['notification_ids'] = array_filter( array_map( 'strval', (array) $posted['notification_ids'] ) );
		}
		if ( empty( $posted['attach_to_email'] ) ) {
			$posted['notification_ids'] = array();
		}

		$when_mode = isset( $posted['when_mode'] ) ? sanitize_key( (string) $posted['when_mode'] ) : 'always';
		if ( 'conditional' !== $when_mode ) {
			$posted['when'] = null;
		} elseif ( isset( $posted['when'] ) && is_array( $posted['when'] ) ) {
			$posted['when'] = Form_Schema::normalize_condition( $posted['when'] );
		} else {
			$posted['when'] = null;
		}
		unset( $posted['when_mode'] );

		$list       = Form_Info_Documents::get( $form_id );
		$normalized = Form_Info_Documents::normalize_one( $posted );
		$list       = Form_Info_Documents::upsert( $list, $normalized );
		Form_Info_Documents::save( $form_id, $list );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'we-formkit-form',
					'form_id'  => $form_id,
					'view'     => 'documents',
					'document' => (string) $normalized['id'],
					'saved'    => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private static function save_view_confirmations( $form_id ) {
		$form_id = self::ensure_form_exists( $form_id );
		// Nonce verified in save_form().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mode = isset( $_POST['wek_confirmation_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['wek_confirmation_mode'] ) ) : 'message';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$message = isset( $_POST['wek_confirmation_message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['wek_confirmation_message'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$redirect = isset( $_POST['wek_confirmation_redirect'] ) ? esc_url_raw( wp_unslash( (string) $_POST['wek_confirmation_redirect'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page_id = isset( $_POST['wek_confirmation_page'] ) ? absint( $_POST['wek_confirmation_page'] ) : 0;
		Form_Schema::set_confirmation(
			$form_id,
			array(
				'mode'         => $mode,
				'message'      => $message,
				'redirect_url' => $redirect,
				'page_id'      => $page_id,
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=confirmations&saved=1' ) );
		exit;
	}

	/**
	 * Ensure a form post exists when saving secondary tabs on a new form.
	 *
	 * @param int $form_id Form ID.
	 * @return int
	 */
	private static function ensure_form_exists( $form_id ) {
		if ( $form_id > 0 ) {
			return $form_id;
		}
		$title   = __( 'New form', 'we-formkit' );
		$form_id = (int) wp_insert_post(
			array(
				'post_type'   => Post_Types::FORM,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( ! $form_id || is_wp_error( $form_id ) ) {
			wp_die( esc_html__( 'Could not create form.', 'we-formkit' ) );
		}
		Form_Schema::save( $form_id, self::blank_schema( $title ) );
		update_post_meta( $form_id, Form_Schema::META_SLUG, sanitize_title( $title . '-' . $form_id ) );
		Form_Schema::set_secret( $form_id, false );
		return $form_id;
	}

	/**
	 * @return void
	 */
	private static function import_form() {
		if ( ! isset( $_POST['we_formkit_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['we_formkit_import_nonce'] ) ), 'we_formkit_import_form' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		if ( empty( $_FILES['wek_import_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'No file uploaded.', 'we-formkit' ) );
		}

		$tmp = (string) $_FILES['wek_import_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_uploaded_file( $tmp ) ) {
			wp_die( esc_html__( 'Invalid upload.', 'we-formkit' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading verified PHP upload tmp.
		$json = file_get_contents( $tmp );
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			wp_die( esc_html__( 'Invalid JSON file.', 'we-formkit' ) );
		}

		if ( self::looks_like_gravity_forms( $data ) ) {
			wp_safe_redirect(
				admin_url(
					'admin.php?page=we-formkit&import_error=' . rawurlencode(
						__( 'Gravity Forms import is available as a future module. Please import WE Formkit JSON for now.', 'we-formkit' )
					)
				)
			);
			exit;
		}

		$schema         = isset( $data['schema'] ) && is_array( $data['schema'] ) ? $data['schema'] : $data;
		$schema         = Form_Schema::normalize( $schema );
		$title          = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : ( ! empty( $schema['title'] ) ? $schema['title'] : __( 'Imported form', 'we-formkit' ) );
		$slug           = isset( $data['slug'] ) ? sanitize_title( (string) $data['slug'] ) : sanitize_title( $title . '-' . wp_generate_password( 4, false, false ) );
		$secret_enabled = ! empty( $data['secret_enabled'] );
		$secret_token   = isset( $data['secret_token'] ) ? (string) $data['secret_token'] : null;

		$form_id = (int) wp_insert_post(
			array(
				'post_type'   => Post_Types::FORM,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( ! $form_id || is_wp_error( $form_id ) ) {
			wp_die( esc_html__( 'Could not import form.', 'we-formkit' ) );
		}

		Form_Schema::save( $form_id, $schema );
		update_post_meta( $form_id, Form_Schema::META_SLUG, $slug );
		Form_Schema::set_secret( $form_id, $secret_enabled, $secret_token );

		if ( isset( $data['notify_email'] ) ) {
			update_post_meta( $form_id, Form_Schema::META_NOTIFY_EMAIL, sanitize_email( (string) $data['notify_email'] ) );
		}
		if ( isset( $data['privacy_url'] ) ) {
			update_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, esc_url_raw( (string) $data['privacy_url'] ) );
		}
		if ( isset( $data['confirmation_message'] ) ) {
			update_post_meta( $form_id, Form_Schema::META_CONFIRMATION_MESSAGE, sanitize_textarea_field( (string) $data['confirmation_message'] ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=fields&saved=1' ) );
		exit;
	}

	/**
	 * Detect Gravity Forms export JSON (top-level fields, no Formkit schema/sections).
	 *
	 * @param array<string,mixed> $data Decoded JSON.
	 * @return bool
	 */
	private static function looks_like_gravity_forms( array $data ) {
		if ( isset( $data['schema'] ) || isset( $data['sections'] ) ) {
			return false;
		}
		if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return false;
		}
		$first = reset( $data['fields'] );
		if ( ! is_array( $first ) ) {
			return false;
		}
		return isset( $first['type'] ) && ( isset( $first['id'] ) || isset( $first['label'] ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private static function export_form( $form_id ) {
		$post = get_post( $form_id );
		if ( ! $post || Post_Types::FORM !== $post->post_type ) {
			wp_die( esc_html__( 'Form not found.', 'we-formkit' ) );
		}
		$secret   = Form_Schema::get_secret( $form_id );
		$payload  = array(
			'version'        => 1,
			'title'          => get_the_title( $post ),
			'slug'           => (string) get_post_meta( $form_id, Form_Schema::META_SLUG, true ),
			'secret_enabled' => ! empty( $secret['enabled'] ),
			'schema'         => Form_Schema::get( $form_id ),
		);
		$filename = 'we-formkit-' . ( ! empty( $payload['slug'] ) ? $payload['slug'] : $form_id ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Duplicate a form including schema and settings (new secret token).
	 *
	 * @param int $form_id Source form ID.
	 * @return int New form ID or 0.
	 */
	private static function clone_form( $form_id ) {
		$post = get_post( $form_id );
		if ( ! $post || Post_Types::FORM !== $post->post_type ) {
			return 0;
		}

		$schema = Form_Schema::get( $form_id );
		$slug   = (string) get_post_meta( $form_id, Form_Schema::META_SLUG, true );
		$title  = sprintf(
			/* translators: %s: original form title */
			__( '%s (Copy)', 'we-formkit' ),
			get_the_title( $post )
		);
		$new_slug = sanitize_title( $slug ? $slug . '-copy' : $title );
		$suffix   = 2;
		while ( Form_Schema::find_by_slug( $new_slug ) > 0 ) {
			$new_slug = sanitize_title( ( $slug ? $slug : 'form' ) . '-copy-' . $suffix );
			++$suffix;
		}

		$new_id = (int) wp_insert_post(
			array(
				'post_type'   => Post_Types::FORM,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( ! $new_id || is_wp_error( $new_id ) ) {
			return 0;
		}

		Form_Schema::save( $new_id, $schema );
		update_post_meta( $new_id, Form_Schema::META_SLUG, $new_slug );
		Form_Notifications::save( $new_id, Form_Notifications::get( $form_id ) );
		Form_Info_Documents::save( $new_id, Form_Info_Documents::get( $form_id ) );
		update_post_meta( $new_id, Form_Schema::META_PRIVACY_URL, (string) get_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, true ) );
		update_post_meta( $new_id, Form_Schema::META_CONFIRMATION_MESSAGE, (string) get_post_meta( $form_id, Form_Schema::META_CONFIRMATION_MESSAGE, true ) );
		Form_Style::save( $new_id, Form_Style::get( $form_id ) );

		$secret = Form_Schema::get_secret( $form_id );
		Form_Schema::set_secret( $new_id, ! empty( $secret['enabled'] ), wp_generate_password( 32, false, false ) );

		return $new_id;
	}
}
