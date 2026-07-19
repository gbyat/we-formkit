<?php
/**
 * Form list/editor with JSON import/export and secret links.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Form_Schema;
use Webentwicklerin\WeFormkit\Plugin;
use Webentwicklerin\WeFormkit\Post_Types;

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
	 * @param array<string,mixed> $schema Normalized form schema.
	 * @return void
	 */
	public static function enqueue_builder_assets( array $schema ) {
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

		wp_localize_script(
			'we-formkit-admin-form',
			'weFormkitAdmin',
			array(
				'fieldTypes' => $field_types,
				'i18n'       => array(
					'section'        => __( 'Section', 'we-formkit' ),
					'field'          => __( 'Field', 'we-formkit' ),
					'remove'         => __( 'Remove', 'we-formkit' ),
					'addField'       => __( 'Add field', 'we-formkit' ),
					'addSection'     => __( 'Add section', 'we-formkit' ),
					'label'          => __( 'Label', 'we-formkit' ),
					'id'             => __( 'Field ID', 'we-formkit' ),
					'type'           => __( 'Type', 'we-formkit' ),
					'required'       => __( 'Required', 'we-formkit' ),
					'help'           => __( 'Help text', 'we-formkit' ),
					'options'        => __( 'Options (value + label)', 'we-formkit' ),
					'showWhen'       => __( 'Show when', 'we-formkit' ),
					'showField'      => __( 'Depends on field ID', 'we-formkit' ),
					'showOp'         => __( 'Operator', 'we-formkit' ),
					'showValue'      => __( 'Value', 'we-formkit' ),
					'none'           => __( 'Always visible', 'we-formkit' ),
					'confirmDel'     => __( 'Remove this item?', 'we-formkit' ),
					'empty'          => __( 'No sections yet. Click “Add section” to start.', 'we-formkit' ),
					'loadError'      => __( 'Form builder failed to load. Hard-refresh the page or check the browser console.', 'we-formkit' ),
					'width'          => __( 'Width', 'we-formkit' ),
					'widthFull'      => __( 'Full width', 'we-formkit' ),
					'widthHalf'      => __( 'Half width', 'we-formkit' ),
					'widthThird'     => __( 'One third', 'we-formkit' ),
					'addOption'      => __( 'Add option', 'we-formkit' ),
					'tabGeneral'     => __( 'General', 'we-formkit' ),
					'tabAppearance'  => __( 'Appearance', 'we-formkit' ),
					'tabConditional' => __( 'Conditional', 'we-formkit' ),
					'selectHint'     => __( 'Select a field or section to edit its settings.', 'we-formkit' ),
					'dragHandle'     => __( 'Drag to reorder', 'we-formkit' ),
					'resizeHandle'   => __( 'Drag to resize', 'we-formkit' ),
					'resizeHint'     => __( 'You can also drag the right edge of a field on the canvas to change its width.', 'we-formkit' ),
					'sectionTitle'   => __( 'Title', 'we-formkit' ),
					'sectionId'      => __( 'Section ID', 'we-formkit' ),
					'moved'          => __( 'Item moved.', 'we-formkit' ),
				),
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
		$allowed_views = array( 'fields', 'settings', 'notifications', 'confirmations', 'entries' );
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
			$confirm = '';
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
			$confirm = (string) get_post_meta( $form_id, Form_Schema::META_CONFIRMATION_MESSAGE, true );
		}

		$schema_json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $schema_json ) {
			$schema_json = '{"version":1,"title":"","intro":"","sections":[]}';
		}

		if ( 'fields' === $view ) {
			self::enqueue_builder_assets( is_array( $schema ) ? $schema : array() );
		}

		$public_page = home_url( '/' );
		$secret_url  = '';
		if ( $secret['enabled'] && $slug && $secret['token'] ) {
			$secret_url = add_query_arg(
				array(
					'wek_form' => $slug,
					'token'    => $secret['token'],
				),
				$public_page
			);
		}

		$heading = $is_new ? __( 'Add Form', 'we-formkit' ) : $title;
		?>
	<div class="wrap wek-admin wek-admin--form-edit">
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

		<?php self::render_form_nav( $form_id, $view, $is_new ); ?>

		<?php
		switch ( $view ) {
			case 'settings':
				self::render_view_settings( $form_id, $title, $slug, $secret, $privacy, $schema, $secret_url, $is_new );
				break;
			case 'notifications':
				self::render_view_notifications( $form_id, $notify, $is_new );
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
		unset( $is_new );
		?>
	<form method="post" id="wek-form-editor" class="wek-admin__fields-only">
		<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
		<input type="hidden" name="wek_save_view" value="fields" />
		<input type="hidden" name="wek_title" id="wek_title" value="<?php echo esc_attr( $title ); ?>" />
		<textarea name="wek_schema_json" id="wek_schema_json" class="wek-admin__schema-input" hidden><?php echo esc_textarea( $schema_json ); ?></textarea>

		<p class="description wek-admin__fields-hint"><?php esc_html_e( 'Build sections and fields here. Form settings, notifications, and confirmations are on the other tabs.', 'we-formkit' ); ?></p>
		<div id="wek-builder" class="wek-builder"></div>

		<?php submit_button( __( 'Save fields', 'we-formkit' ), 'primary', 'we_formkit_save_form' ); ?>
	</form>
		<?php
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
		unset( $is_new );
		?>
	<form method="post" class="wek-admin__settings-panel">
		<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
		<input type="hidden" name="wek_save_view" value="settings" />

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wek_title"><?php esc_html_e( 'Title', 'we-formkit' ); ?></label></th>
				<td><input class="regular-text" type="text" name="wek_title" id="wek_title" value="<?php echo esc_attr( $title ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="wek_slug"><?php esc_html_e( 'Slug', 'we-formkit' ); ?></label></th>
				<td>
					<input class="regular-text" type="text" name="wek_slug" id="wek_slug" value="<?php echo esc_attr( $slug ); ?>" placeholder="contact" />
					<p class="description"><?php esc_html_e( 'Used in secret links and as a stable form key.', 'we-formkit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Secret link access', 'we-formkit' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wek_secret_enabled" value="1" <?php checked( ! empty( $secret['enabled'] ) ); ?> />
						<?php esc_html_e( 'Require secret token in URL', 'we-formkit' ); ?>
					</label>
					<?php if ( $form_id && $secret['token'] ) : ?>
						<p><code><?php echo esc_html( $secret['token'] ); ?></code></p>
						<?php if ( $secret_url ) : ?>
							<p>
								<label><?php esc_html_e( 'Shareable link (open the page that contains the Formkit Form block):', 'we-formkit' ); ?></label><br />
								<input class="large-text" type="text" readonly value="<?php echo esc_attr( $secret_url ); ?>" onclick="this.select();" />
							</p>
						<?php endif; ?>
						<p>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=settings&wek_regen_token=1' ), 'wek_regen_' . $form_id ) ); ?>"><?php esc_html_e( 'Regenerate token', 'we-formkit' ); ?></a>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="wek_privacy_url"><?php esc_html_e( 'Privacy policy URL', 'we-formkit' ); ?></label></th>
				<td>
					<input class="regular-text" type="url" name="wek_privacy_url" id="wek_privacy_url" value="<?php echo esc_attr( $privacy ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. Falls back to the plugin default, then the site privacy policy.', 'we-formkit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="wek_intro"><?php esc_html_e( 'Intro text', 'we-formkit' ); ?></label></th>
				<td><textarea class="large-text" rows="4" name="wek_intro" id="wek_intro"><?php echo esc_textarea( (string) ( $schema['intro'] ?? '' ) ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'we-formkit' ), 'primary', 'we_formkit_save_form' ); ?>
	</form>
		<?php
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $notify Notify email.
	 * @param bool   $is_new Whether new.
	 * @return void
	 */
	private static function render_view_notifications( $form_id, $notify, $is_new ) {
		unset( $is_new );
		?>
	<form method="post" class="wek-admin__settings-panel">
		<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
		<input type="hidden" name="wek_save_view" value="notifications" />

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wek_notify_email"><?php esc_html_e( 'Notification email', 'we-formkit' ); ?></label></th>
				<td>
					<input class="regular-text" type="email" name="wek_notify_email" id="wek_notify_email" value="<?php echo esc_attr( $notify ); ?>" />
					<p class="description"><?php esc_html_e( 'Receives an email when this form is submitted. Leave empty to use the plugin default notification email.', 'we-formkit' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save notifications', 'we-formkit' ), 'primary', 'we_formkit_save_form' ); ?>
	</form>
		<?php
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $confirm Confirmation message.
	 * @param bool   $is_new Whether new.
	 * @return void
	 */
	private static function render_view_confirmations( $form_id, $confirm, $is_new ) {
		unset( $is_new );
		$placeholder = __( 'Thank you. Your form was submitted successfully.', 'we-formkit' );
		?>
	<form method="post" class="wek-admin__settings-panel">
		<?php wp_nonce_field( 'we_formkit_save_form', 'we_formkit_save_nonce' ); ?>
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
		<input type="hidden" name="wek_save_view" value="confirmations" />

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wek_confirmation_message"><?php esc_html_e( 'Confirmation message', 'we-formkit' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="4" name="wek_confirmation_message" id="wek_confirmation_message" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $confirm ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown on the page after a successful submission. Leave empty to use the default message.', 'we-formkit' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save confirmation', 'we-formkit' ), 'primary', 'we_formkit_save_form' ); ?>
	</form>
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
		if ( ! in_array( $view, array( 'fields', 'settings', 'notifications', 'confirmations' ), true ) ) {
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
			if ( empty( $decoded['title'] ) && ! empty( $current['title'] ) ) {
				$decoded['title'] = $current['title'];
			}
			if ( ! isset( $decoded['intro'] ) && isset( $current['intro'] ) ) {
				$decoded['intro'] = $current['intro'];
			}
			$post_title = get_the_title( $form_id );
		} else {
			$post_title       = ! empty( $decoded['title'] ) ? (string) $decoded['title'] : ( $title ? $title : __( 'New form', 'we-formkit' ) );
			$decoded['title'] = $post_title;
		}

		$schema = Form_Schema::normalize( $decoded );

		if ( $form_id > 0 ) {
			Form_Schema::save( $form_id, $schema );
		} else {
			$form_id = (int) wp_insert_post(
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
		$intro          = isset( $_POST['wek_intro'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['wek_intro'] ) ) : '';
		$privacy        = isset( $_POST['wek_privacy_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['wek_privacy_url'] ) ) : '';
		$secret_enabled = ! empty( $_POST['wek_secret_enabled'] );
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
		$notify = isset( $_POST['wek_notify_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['wek_notify_email'] ) ) : '';
		update_post_meta( $form_id, Form_Schema::META_NOTIFY_EMAIL, $notify );
		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-form&form_id=' . $form_id . '&view=notifications&saved=1' ) );
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
		$message = isset( $_POST['wek_confirmation_message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['wek_confirmation_message'] ) ) : '';
		update_post_meta( $form_id, Form_Schema::META_CONFIRMATION_MESSAGE, $message );
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

		$tmp  = (string) $_FILES['wek_import_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$json = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
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
		update_post_meta( $new_id, Form_Schema::META_NOTIFY_EMAIL, (string) get_post_meta( $form_id, Form_Schema::META_NOTIFY_EMAIL, true ) );
		update_post_meta( $new_id, Form_Schema::META_PRIVACY_URL, (string) get_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, true ) );
		update_post_meta( $new_id, Form_Schema::META_CONFIRMATION_MESSAGE, (string) get_post_meta( $form_id, Form_Schema::META_CONFIRMATION_MESSAGE, true ) );

		$secret = Form_Schema::get_secret( $form_id );
		Form_Schema::set_secret( $new_id, ! empty( $secret['enabled'] ), wp_generate_password( 32, false, false ) );

		return $new_id;
	}
}
