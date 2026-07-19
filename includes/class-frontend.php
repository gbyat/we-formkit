<?php
/**
 * Frontend form rendering and assets (Gutenberg block only).
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders forms on the front end via block render callback.
 */
final class Frontend {

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			'we-formkit-form',
			WE_FORMKIT_URL . 'assets/css/frontend.css',
			array(),
			WE_FORMKIT_VERSION
		);
		wp_register_script(
			'we-formkit-form',
			WE_FORMKIT_URL . 'assets/js/frontend.js',
			array(),
			WE_FORMKIT_VERSION,
			true
		);
		wp_set_script_translations( 'we-formkit-form', 'we-formkit', WE_FORMKIT_PATH . 'languages' );
	}

	/**
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		$forms = self::form_choices_for_editor();
		wp_localize_script(
			'we-formkit-form-editor',
			'weFormkitBlock',
			array(
				'forms' => $forms,
				'i18n'  => array(
					'title'             => __( 'Formkit Form', 'we-formkit' ),
					'description'       => __( 'Embed a form. Secret-link access is enforced on the front end when enabled for the form.', 'we-formkit' ),
					'selectForm'        => __( 'Form', 'we-formkit' ),
					'selectPlaceholder' => __( 'Select a form…', 'we-formkit' ),
					'noForms'           => __( 'No forms found. Create one under Formkit → Forms.', 'we-formkit' ),
					'preview'           => __( 'The selected form will render on the front end.', 'we-formkit' ),
					'selectInSidebar'   => __( 'Select a form in the block sidebar.', 'we-formkit' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_block() {
		self::register_assets();

		wp_register_script(
			'we-formkit-form-editor',
			WE_FORMKIT_URL . 'assets/js/block-editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-data',
			),
			WE_FORMKIT_VERSION,
			true
		);

		wp_set_script_translations( 'we-formkit-form-editor', 'we-formkit', WE_FORMKIT_PATH . 'languages' );

		wp_register_style(
			'we-formkit-form-editor',
			WE_FORMKIT_URL . 'assets/css/block-editor.css',
			array( 'wp-edit-blocks' ),
			WE_FORMKIT_VERSION
		);

		register_block_type(
			'we-formkit/form',
			array(
				'api_version'     => 3,
				'title'           => __( 'Formkit Form', 'we-formkit' ),
				'description'     => __( 'Embed an editable form.', 'we-formkit' ),
				'category'        => 'widgets',
				'icon'            => 'clipboard',
				'keywords'        => array( 'form', 'formkit', 'contact' ),
				'textdomain'      => 'we-formkit',
				'attributes'      => array(
					'formId' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'slug'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'        => array(
					'html'   => false,
					'align'  => array( 'wide', 'full' ),
					'anchor' => true,
				),
				'editor_script'   => 'we-formkit-form-editor',
				'editor_style'    => 'we-formkit-form-editor',
				'style'           => 'we-formkit-form',
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * @return list<array{id:int,slug:string,title:string}>
	 */
	public static function form_choices_for_editor() {
		$query = new \WP_Query(
			array(
				'post_type'      => Post_Types::FORM,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$out   = array();
		foreach ( $query->posts as $post ) {
			$out[] = array(
				'id'    => (int) $post->ID,
				'slug'  => (string) get_post_meta( $post->ID, Form_Schema::META_SLUG, true ),
				'title' => get_the_title( $post ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$form_id = isset( $attributes['formId'] ) ? absint( $attributes['formId'] ) : 0;
		$slug    = isset( $attributes['slug'] ) ? sanitize_title( (string) $attributes['slug'] ) : '';

		if ( $form_id <= 0 && '' !== $slug ) {
			$form_id = Form_Schema::find_by_slug( $slug );
		}

		// Secret-link query can select form + supply token.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public form routing.
		if ( isset( $_GET['wek_form'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query_slug = sanitize_title( wp_unslash( (string) $_GET['wek_form'] ) );
			$found      = Form_Schema::find_by_slug( $query_slug );
			if ( $found > 0 ) {
				$form_id = $found;
			}
		}

		$token = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['token'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$token = sanitize_text_field( wp_unslash( (string) $_GET['token'] ) );
		}

		if ( $form_id <= 0 ) {
			return '<p class="we-formkit-error">' . esc_html__( 'Form not found.', 'we-formkit' ) . '</p>';
		}

		$secret = Form_Schema::get_secret( $form_id );
		if ( $secret['enabled'] ) {
			if ( '' === $secret['token'] || ! hash_equals( $secret['token'], $token ) ) {
				return '<p class="we-formkit-error" role="alert">' . esc_html__( 'This form is only available via a private link.', 'we-formkit' ) . '</p>';
			}
		}

		$schema = Form_Schema::get( $form_id );
		wp_enqueue_style( 'we-formkit-form' );
		wp_enqueue_script( 'we-formkit-form' );

		$settings    = Settings::get();
		$privacy_url = (string) get_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, true );
		if ( '' === $privacy_url ) {
			$privacy_url = (string) $settings['privacy_policy_url'];
		}
		if ( '' === $privacy_url ) {
			$privacy_url = get_privacy_policy_url();
		}

		wp_localize_script(
			'we-formkit-form',
			'weFormkit',
			array(
				'restUrl'  => esc_url_raw( rest_url( Rest_Api::NAMESPACE . '/submit' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'formId'   => $form_id,
				'token'    => $token,
				'started'  => time(),
				'autofill' => Capabilities::can_manage(),
				'i18n'     => array(
					'submitting'    => __( 'Submitting…', 'we-formkit' ),
					'error'         => __( 'Something went wrong. Please try again.', 'we-formkit' ),
					'required'      => __( 'This field is required.', 'we-formkit' ),
					'addRow'        => __( 'Add another', 'we-formkit' ),
					'removeRow'     => __( 'Remove', 'we-formkit' ),
					/* translators: %d: row number (1-based). */
					'rowLabel'      => __( 'Row %d', 'we-formkit' ),
					'autofillReady' => __( 'Test fill applied. Submitting automatically…', 'we-formkit' ),
					'autofillManual' => __( 'Test fill applied. Click Submit form when ready.', 'we-formkit' ),
					'autofillDone'  => __( 'Smoke test submitted. Check Formkit → Entries.', 'we-formkit' ),
				),
			)
		);

		ob_start();
		self::render_form( $form_id, $schema, $privacy_url );
		return (string) ob_get_clean();
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $schema Schema.
	 * @param string               $privacy_url Privacy URL.
	 * @return void
	 */
	public static function render_form( $form_id, array $schema, $privacy_url ) {
		$title = $schema['title'] ? $schema['title'] : get_the_title( $form_id );
		?>
		<div class="we-formkit" data-we-formkit data-form-id="<?php echo esc_attr( (string) $form_id ); ?>" style="<?php echo esc_attr( Form_Style::css_variables_attr( $form_id ) ); ?>">
			<header class="we-formkit__header">
				<h2 class="we-formkit__title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( ! empty( $schema['intro'] ) ) : ?>
					<p class="we-formkit__intro"><?php echo esc_html( $schema['intro'] ); ?></p>
				<?php endif; ?>
			</header>

			<div class="we-formkit__status" data-wek-status role="status" aria-live="polite"></div>

			<form class="we-formkit__form" data-wek-form novalidate>
				<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
				<input type="text" name="website_url" value="" class="we-formkit__honeypot" tabindex="-1" autocomplete="off" aria-hidden="true" />

				<?php foreach ( $schema['sections'] as $section ) : ?>
					<?php
					$section_hidden = ! Conditional::is_visible( $section['show_when'] ?? null, array() );
					$section_attrs  = self::rule_attrs( $section['show_when'] ?? null );
					?>
					<section
						class="we-formkit__section<?php echo $section_hidden ? ' is-hidden' : ''; ?>"
						data-wek-section
						<?php echo $section_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped helpers. ?>
						aria-hidden="<?php echo $section_hidden ? 'true' : 'false'; ?>"
					>
						<?php if ( ! empty( $section['title'] ) ) : ?>
							<h3 class="we-formkit__section-title"><?php echo esc_html( $section['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( ! empty( $section['intro'] ) ) : ?>
							<p class="we-formkit__section-intro"><?php echo esc_html( $section['intro'] ); ?></p>
						<?php endif; ?>

						<div class="we-formkit__fields">
						<?php foreach ( $section['fields'] as $field ) : ?>
							<?php self::render_field( $field, $privacy_url ); ?>
						<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>

				<p class="we-formkit__actions">
					<button type="submit" class="we-formkit__submit"><?php esc_html_e( 'Submit form', 'we-formkit' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $field Field.
	 * @param string               $privacy_url Privacy URL.
	 * @return void
	 */
	private static function render_field( array $field, $privacy_url ) {
		$id       = $field['id'];
		$type     = $field['type'];
		$width    = isset( $field['width'] ) ? (string) $field['width'] : 'full';
		$hidden   = ! Conditional::is_visible( $field['show_when'] ?? null, array() );
		$attrs    = self::rule_attrs( $field['show_when'] ?? null );
		$req      = ! empty( $field['required'] );
		$input_id = 'wek-field-' . $id;
		$desc_id  = $input_id . '-help';
		$error_id = $input_id . '-error';
		$registry = Plugin::instance()->field_registry();
		$type_obj = $registry ? $registry->get( $type ) : null;
		?>
		<div
			class="we-formkit__field we-formkit__field--<?php echo esc_attr( $type ); ?> we-formkit__field--width-<?php echo esc_attr( $width ); ?><?php echo $hidden ? ' is-hidden' : ''; ?>"
			data-wek-field
			data-field-id="<?php echo esc_attr( $id ); ?>"
			data-field-type="<?php echo esc_attr( $type ); ?>"
			data-required="<?php echo $req ? '1' : '0'; ?>"
			<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			aria-hidden="<?php echo $hidden ? 'true' : 'false'; ?>"
		>
			<?php if ( 'checkbox' === $type || 'consent' === $type ) : ?>
				<div class="we-formkit__control we-formkit__control--choice">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $id ); ?>"
						value="1"
						<?php echo $req ? 'required' : ''; ?>
						aria-describedby="<?php echo esc_attr( trim( $desc_id . ' ' . $error_id ) ); ?>"
					/>
					<label for="<?php echo esc_attr( $input_id ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php
						if ( $req ) :
							?>
							<span class="we-formkit__req" aria-hidden="true">*</span><?php endif; ?>
					</label>
				</div>
				<?php if ( 'consent' === $type && $privacy_url ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>">
						<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy policy', 'we-formkit' ); ?></a>
					</p>
				<?php elseif ( ! empty( $field['help'] ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php elseif ( in_array( $type, array( 'radio', 'checkboxes' ), true ) ) : ?>
				<fieldset class="we-formkit__fieldset">
					<legend class="we-formkit__label">
						<?php echo esc_html( $field['label'] ); ?>
						<?php
						if ( $req ) :
							?>
							<span class="we-formkit__req" aria-hidden="true">*</span><?php endif; ?>
					</legend>
					<?php if ( ! empty( $field['help'] ) ) : ?>
						<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
					<?php endif; ?>
					<div class="we-formkit__choices" role="group" aria-describedby="<?php echo esc_attr( $desc_id . ' ' . $error_id ); ?>">
						<?php foreach ( $field['options'] as $option ) : ?>
							<?php
							$oid   = $input_id . '-' . $option['value'];
							$iname = 'checkboxes' === $type ? $id . '[]' : $id;
							$itype = 'checkboxes' === $type ? 'checkbox' : 'radio';
							?>
							<label class="we-formkit__choice" for="<?php echo esc_attr( $oid ); ?>">
								<input
									type="<?php echo esc_attr( $itype ); ?>"
									id="<?php echo esc_attr( $oid ); ?>"
									name="<?php echo esc_attr( $iname ); ?>"
									value="<?php echo esc_attr( $option['value'] ); ?>"
								/>
								<span><?php echo esc_html( $option['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php elseif ( 'radio_image' === $type ) : ?>
				<fieldset class="we-formkit__fieldset">
					<legend class="we-formkit__label">
						<?php echo esc_html( $field['label'] ); ?>
						<?php
						if ( $req ) :
							?>
							<span class="we-formkit__req" aria-hidden="true">*</span><?php endif; ?>
					</legend>
					<?php if ( ! empty( $field['help'] ) ) : ?>
						<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
					<?php endif; ?>
					<div class="we-formkit__choices" role="group" aria-describedby="<?php echo esc_attr( $desc_id . ' ' . $error_id ); ?>">
						<?php foreach ( ( $field['options'] ?? array() ) as $option ) : ?>
							<?php
							$oid       = $input_id . '-' . $option['value'];
							$image_url = isset( $option['image_url'] ) ? (string) $option['image_url'] : '';
							if ( '' === $image_url && ! empty( $option['image_id'] ) ) {
								$from_id = wp_get_attachment_image_url( (int) $option['image_id'], 'medium' );
								if ( is_string( $from_id ) ) {
									$image_url = $from_id;
								}
							}
							?>
							<label class="we-formkit__choice we-formkit__choice--image" for="<?php echo esc_attr( $oid ); ?>">
								<input
									type="radio"
									id="<?php echo esc_attr( $oid ); ?>"
									name="<?php echo esc_attr( $id ); ?>"
									value="<?php echo esc_attr( $option['value'] ); ?>"
									<?php echo $req ? 'required' : ''; ?>
								/>
								<?php if ( '' !== $image_url ) : ?>
									<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="we-formkit__choice-image" />
								<?php endif; ?>
								<span><?php echo esc_html( $option['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php elseif ( 'select' === $type ) : ?>
				<label class="we-formkit__label" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php
					if ( $req ) :
						?>
						<span class="we-formkit__req" aria-hidden="true">*</span><?php endif; ?>
				</label>
				<select
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					<?php echo $req ? 'required' : ''; ?>
					aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
				>
					<option value=""><?php esc_html_e( 'Please select…', 'we-formkit' ); ?></option>
					<?php foreach ( $field['options'] as $option ) : ?>
						<option value="<?php echo esc_attr( $option['value'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'textarea' === $type ) : ?>
				<label class="we-formkit__label" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php
					if ( $req ) :
						?>
						<span class="we-formkit__req" aria-hidden="true">*</span><?php endif; ?>
				</label>
				<textarea
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					rows="4"
					placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
					<?php echo $req ? 'required' : ''; ?>
					aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
				></textarea>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'html' === $type ) : ?>
				<div class="we-formkit__html">
					<?php
					$content = isset( $field['type_options']['content'] ) ? (string) $field['type_options']['content'] : '';
					echo wp_kses_post( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd HTML block.
					?>
				</div>
			<?php elseif ( 'hidden' === $type ) : ?>
				<?php
				$default = isset( $field['type_options']['default_value'] ) ? (string) $field['type_options']['default_value'] : '';
				?>
				<input
					type="hidden"
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					value="<?php echo esc_attr( $default ); ?>"
				/>
			<?php elseif ( 'upload' === $type ) : ?>
				<label class="we-formkit__label" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php
					if ( $req ) :
						?>
						<span class="we-formkit__req" aria-hidden="true">*</span><?php endif; ?>
				</label>
				<?php
				$upload_attrs = $type_obj ? $type_obj->render_attributes( $field ) : array( 'type' => 'file' );
				$max_files    = isset( $field['type_options']['max_files'] ) ? max( 1, (int) $field['type_options']['max_files'] ) : 1;
				$file_name    = $max_files > 1 ? $id . '[]' : $id;
				$accept       = '';
				if ( $type_obj instanceof Fields\Upload_Field ) {
					$mimes = $type_obj->get_allowed_mime_types( $field );
					if ( ! empty( $mimes ) ) {
						$accept = implode( ',', $mimes );
					}
				}
				?>
				<input
					<?php echo self::html_attrs( $upload_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $file_name ); ?>"
					<?php echo '' !== $accept ? 'accept="' . esc_attr( $accept ) . '"' : ''; ?>
					aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
				/>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'repeater' === $type ) : ?>
				<?php self::render_repeater( $field, $input_id, $desc_id, $error_id, $req ); ?>
			<?php else : ?>
				<label class="we-formkit__label" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php
					if ( $req ) :
						?>
						<span class="we-formkit__req" aria-hidden="true">*</span><?php endif; ?>
				</label>
				<?php
				$input_attrs = $type_obj ? $type_obj->render_attributes( $field ) : array( 'type' => $type );
				if ( 'datetime' === $type && ( ! isset( $input_attrs['type'] ) || 'datetime' === $input_attrs['type'] ) ) {
					$input_attrs['type'] = 'datetime-local';
				}
				?>
				<input
					<?php echo self::html_attrs( $input_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
				/>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( 'html' !== $type && 'hidden' !== $type ) : ?>
				<p class="we-formkit__error" id="<?php echo esc_attr( $error_id ); ?>" data-wek-error hidden></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a repeater (clonable field group).
	 *
	 * @param array<string, mixed> $field    Field.
	 * @param string               $input_id Base input id.
	 * @param string               $desc_id  Help id.
	 * @param string               $error_id Error id.
	 * @param bool                 $req      Required.
	 * @return void
	 */
	private static function render_repeater( array $field, $input_id, $desc_id, $error_id, $req ) {
		$id        = (string) $field['id'];
		$min_items = isset( $field['type_options']['min_items'] ) ? max( 0, (int) $field['type_options']['min_items'] ) : 1;
		$max_items = isset( $field['type_options']['max_items'] ) ? max( 1, (int) $field['type_options']['max_items'] ) : 5;
		$min_items = min( $min_items, $max_items );
		$initial   = max( 1, $min_items > 0 ? $min_items : 1 );
		$add_label = (string) ( $field['type_options']['add_button_label'] ?? '' );
		if ( '' === $add_label ) {
			$add_label = __( 'Add another', 'we-formkit' );
		}
		$item_fields = array();
		if ( $field['type_options']['fields'] ?? null ) {
			$item_fields = is_array( $field['type_options']['fields'] ) ? $field['type_options']['fields'] : array();
		}
		?>
		<div class="we-formkit__label we-formkit__label--repeater" id="<?php echo esc_attr( $input_id . '-label' ); ?>">
			<?php echo esc_html( $field['label'] ); ?>
			<?php if ( $req ) : ?>
				<span class="we-formkit__req" aria-hidden="true">*</span>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $field['help'] ) ) : ?>
			<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
		<?php endif; ?>

		<?php if ( empty( $item_fields ) ) : ?>
			<p class="we-formkit__help"><?php esc_html_e( 'This repeater has no fields configured yet.', 'we-formkit' ); ?></p>
			<?php
			return;
		endif;
		?>

		<div
			class="we-formkit__repeater"
			data-wek-repeater
			data-field-id="<?php echo esc_attr( $id ); ?>"
			data-min-items="<?php echo esc_attr( (string) $min_items ); ?>"
			data-max-items="<?php echo esc_attr( (string) $max_items ); ?>"
			aria-labelledby="<?php echo esc_attr( $input_id . '-label' ); ?>"
			aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
		>
			<div class="we-formkit__repeater-rows" data-wek-repeater-rows>
				<?php for ( $i = 0; $i < $initial; $i++ ) : ?>
					<?php self::render_repeater_row( $field, $item_fields, $i, false ); ?>
				<?php endfor; ?>
			</div>
			<p class="we-formkit__repeater-actions">
				<button type="button" class="we-formkit__repeater-add" data-wek-repeater-add>
					<?php echo esc_html( $add_label ); ?>
				</button>
			</p>
			<template data-wek-repeater-template>
				<?php self::render_repeater_row( $field, $item_fields, '__INDEX__', true ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed>        $field       Parent repeater field.
	 * @param array<int, array<string, mixed>> $item_fields Nested fields.
	 * @param int|string                  $index       Row index or placeholder.
	 * @param bool                        $is_template Whether rendering inside a template.
	 * @return void
	 */
	private static function render_repeater_row( array $field, array $item_fields, $index, $is_template ) {
		$parent_id = (string) $field['id'];
		$index_str = (string) $index;
		?>
		<div class="we-formkit__repeater-row" data-wek-repeater-row>
			<div class="we-formkit__repeater-row-head">
				<span class="we-formkit__repeater-row-label" data-wek-repeater-row-label>
					<?php
					if ( $is_template ) {
						esc_html_e( 'Row', 'we-formkit' );
					} else {
						printf(
							/* translators: %d: row number (1-based). */
							esc_html__( 'Row %d', 'we-formkit' ),
							(int) $index + 1
						);
					}
					?>
				</span>
				<button type="button" class="we-formkit__repeater-remove" data-wek-repeater-remove>
					<?php esc_html_e( 'Remove', 'we-formkit' ); ?>
				</button>
			</div>
			<div class="we-formkit__repeater-fields">
				<?php foreach ( $item_fields as $child ) : ?>
					<?php self::render_repeater_control( $parent_id, $child, $index_str ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string               $parent_id Parent field id.
	 * @param array<string, mixed> $child     Nested field.
	 * @param string               $index     Row index string.
	 * @return void
	 */
	private static function render_repeater_control( $parent_id, array $child, $index ) {
		$cid   = (string) ( $child['id'] ?? '' );
		$ctype = (string) ( $child['type'] ?? 'text' );
		if ( '' === $cid ) {
			return;
		}

		$registry = Plugin::instance()->field_registry();
		$type_obj = $registry ? $registry->get( $ctype ) : null;
		$name     = sprintf( '%s[%s][%s]', $parent_id, $index, $cid );
		$input_id = sprintf( 'wek-field-%s-%s-%s', $parent_id, $index, $cid );
		$req      = ! empty( $child['required'] );
		$label    = (string) ( $child['label'] ?? $cid );
		?>
		<div class="we-formkit__repeater-control we-formkit__repeater-control--<?php echo esc_attr( $ctype ); ?>">
			<label class="we-formkit__label we-formkit__label--nested" for="<?php echo esc_attr( $input_id ); ?>">
				<?php echo esc_html( $label ); ?>
				<?php if ( $req ) : ?>
					<span class="we-formkit__req" aria-hidden="true">*</span>
				<?php endif; ?>
			</label>
			<?php if ( 'textarea' === $ctype ) : ?>
				<textarea
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					rows="3"
					placeholder="<?php echo esc_attr( (string) ( $child['placeholder'] ?? '' ) ); ?>"
					data-wek-repeater-input
					data-sub-id="<?php echo esc_attr( $cid ); ?>"
					<?php echo $req ? 'required' : ''; ?>
				></textarea>
			<?php elseif ( 'select' === $ctype ) : ?>
				<select
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					data-wek-repeater-input
					data-sub-id="<?php echo esc_attr( $cid ); ?>"
					<?php echo $req ? 'required' : ''; ?>
				>
					<option value=""><?php esc_html_e( 'Please select…', 'we-formkit' ); ?></option>
					<?php foreach ( ( $child['options'] ?? array() ) as $option ) : ?>
						<option value="<?php echo esc_attr( (string) ( $option['value'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) ( $option['label'] ?? $option['value'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<?php
				$input_attrs = $type_obj ? $type_obj->render_attributes( $child ) : array( 'type' => $ctype );
				// Nested required is handled below; avoid double attrs from type class when parent row is empty.
				unset( $input_attrs['required'], $input_attrs['aria-required'] );
				if ( 'datetime' === $ctype && ( ! isset( $input_attrs['type'] ) || 'datetime' === $input_attrs['type'] ) ) {
					$input_attrs['type'] = 'datetime-local';
				}
				?>
				<input
					<?php echo self::html_attrs( $input_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					data-wek-repeater-input
					data-sub-id="<?php echo esc_attr( $cid ); ?>"
					<?php echo $req ? 'required' : ''; ?>
				/>
			<?php endif; ?>
			<?php if ( ! empty( $child['help'] ) ) : ?>
				<p class="we-formkit__help"><?php echo esc_html( (string) $child['help'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build escaped HTML attributes from a key => value map.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	private static function html_attrs( array $attrs ) {
		$parts = array();
		foreach ( $attrs as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key || null === $value || false === $value ) {
				continue;
			}
			if ( true === $value || $key === (string) $value ) {
				$parts[] = esc_attr( $key );
				continue;
			}
			$parts[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
		}
		return implode( ' ', $parts );
	}

	/**
	 * @param array<string, mixed>|null $rule Rule.
	 * @return string HTML attributes.
	 */
	private static function rule_attrs( $rule ) {
		if ( empty( $rule ) || empty( $rule['field'] ) ) {
			return '';
		}
		return sprintf(
			' data-show-field="%s" data-show-op="%s" data-show-value="%s"',
			esc_attr( (string) $rule['field'] ),
			esc_attr( (string) $rule['op'] ),
			esc_attr( (string) ( $rule['value'] ?? '' ) )
		);
	}
}
