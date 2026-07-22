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
	 * Active appearance while rendering a form.
	 *
	 * @var array{label_weight:string,required_mark:string,optional_mark:string,inline_validation:string,help_placement:string,help_style:string,font_family:string}|null
	 */
	private static $appearance = null;

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_preview' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Admin preview: ?wek_preview=1&form_id=N
	 *
	 * @return void
	 */
	public static function maybe_render_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['wek_preview'] ) || '1' !== (string) $_GET['wek_preview'] ) {
			return;
		}
		if ( ! current_user_can( 'edit_wek_forms' ) && ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You cannot preview this form.', 'we-formkit' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		if ( $form_id <= 0 ) {
			wp_die( esc_html__( 'Form not found.', 'we-formkit' ), 404 );
		}

		status_header( 200 );
		nocache_headers();
		echo '<!DOCTYPE html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>';
		echo esc_html__( 'Form preview', 'we-formkit' );
		echo '</title>';
		wp_enqueue_style( 'we-formkit-form' );
		wp_enqueue_script( 'we-formkit-form' );
		wp_print_styles( array( 'we-formkit-form' ) );
		echo '</head><body class="we-formkit-preview-body" style="margin:0;padding:2rem;background:#f5f5f5;">';
		echo self::render_block( array( 'formId' => $form_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_print_scripts( array( 'we-formkit-form' ) );
		echo '</body></html>';
		exit;
	}

	/**
	 * Shortcode: [we_formkit id="123"] or [we_formkit slug="contact"].
	 *
	 * @return void
	 */
	public static function register_shortcode() {
		add_shortcode( 'we_formkit', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'we-formkit', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => '',
				'slug' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'we_formkit'
		);

		return self::render_block(
			array(
				'formId' => absint( $atts['id'] ),
				'slug'   => sanitize_title( $atts['slug'] ),
			)
		);
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

		$privacy_url = (string) get_post_meta( $form_id, Form_Schema::META_PRIVACY_URL, true );
		if ( '' === $privacy_url ) {
			$privacy_url = Settings::privacy_policy_url();
		}

		wp_localize_script(
			'we-formkit-form',
			'weFormkit',
			array(
				'restUrl'       => esc_url_raw( rest_url( Rest_Api::NAMESPACE . '/submit' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'formId'        => $form_id,
				'token'         => $token,
				'started'       => time(),
				'autofill'      => Capabilities::can_manage(),
				'pagination'    => Form_Schema::get_pagination( $form_id ),
				'saveResume'    => Drafts::is_enabled( $form_id ),
				'saveMinFilled' => Drafts::get_min_filled( $form_id ),
				'draftUrl'      => esc_url_raw( rest_url( Rest_Api::NAMESPACE . '/drafts' ) ),
				'i18n'          => array(
					'submitting'        => __( 'Submitting…', 'we-formkit' ),
					'submit'            => __( 'Submit form', 'we-formkit' ),
					'error'             => __( 'Something went wrong. Please try again.', 'we-formkit' ),
					'required'          => __( 'This field is required.', 'we-formkit' ),
					'correctFields'     => __( 'Please correct the highlighted fields.', 'we-formkit' ),
					'addRow'            => __( 'Add another', 'we-formkit' ),
					'removeRow'         => __( 'Remove', 'we-formkit' ),
					/* translators: %d: row number (1-based). */
					'rowLabel'          => __( 'Row %d', 'we-formkit' ),
					'autofillReady'     => __( 'Test fill applied. Submitting automatically…', 'we-formkit' ),
					'autofillManual'    => __( 'Test fill applied. Click Submit form when ready.', 'we-formkit' ),
					'autofillDone'      => __( 'Smoke test submitted. Check Formkit → Entries.', 'we-formkit' ),
					'infoDocuments'     => __( 'Downloads', 'we-formkit' ),
					'next'              => __( 'Next', 'we-formkit' ),
					'previous'          => __( 'Previous', 'we-formkit' ),
					/* translators: 1: current page, 2: total pages. */
					'pageOf'            => __( 'Step %1$d of %2$d', 'we-formkit' ),
					'saveProgress'      => __( 'Save progress', 'we-formkit' ),
					'saveEmailNeeded'   => __( 'Enter an email address to receive your resume link.', 'we-formkit' ),
					'savingProgress'    => __( 'Saving…', 'we-formkit' ),
					/* translators: %s: email address. */
					'savedProgress'     => __( 'Progress saved. We sent a resume link to %s.', 'we-formkit' ),
					'saveUpdated'       => __( 'Progress updated. Use the resume link from your earlier email.', 'we-formkit' ),
					'saveMailFailed'    => __( 'Progress was saved, but the resume email could not be sent. Please try again or contact the site owner.', 'we-formkit' ),
					'saveTooEarly'      => __( 'Fill in a few fields before saving your progress.', 'we-formkit' ),
					'resumeLoaded'      => __( 'Your saved progress was restored.', 'we-formkit' ),
					'saveEmailPh'       => __( 'Email for resume link', 'we-formkit' ),
					'saveRemind'        => __( 'Add a calendar reminder (.ics) before this link expires', 'we-formkit' ),
					/* translators: 1: field label, 2: minimum number of selections. */
					'checkboxesMin'     => __( 'Please select at least %2$d option(s) for %1$s.', 'we-formkit' ),
					/* translators: 1: field label, 2: maximum number of selections. */
					'checkboxesMax'     => __( 'Please select at most %2$d option(s) for %1$s.', 'we-formkit' ),
					'otherTextRequired' => __( 'Please enter text for Other.', 'we-formkit' ),
					'matrixAddRow'      => __( 'Add other row', 'we-formkit' ),
					'matrixRemoveRow'   => __( 'Remove row', 'we-formkit' ),
					'matrixRowLabelPh'  => __( 'Your label', 'we-formkit' ),
					'clearSelection'    => __( 'Clear selection', 'we-formkit' ),
				),
				'validation'    => Validation_Messages::global_templates_for_js(),
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
		$title            = $schema['title'] ? $schema['title'] : get_the_title( $form_id );
		$pagination       = Form_Schema::get_pagination( $form_id );
		self::$appearance = Form_Schema::get_appearance( $form_id );
		$appearance_class = Form_Schema::appearance_root_classes( $form_id );
		?>
		<div
			class="we-formkit <?php echo esc_attr( $appearance_class ); ?>"
			data-we-formkit
			data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
			data-pagination="<?php echo esc_attr( $pagination ); ?>"
			data-inline-validation="<?php echo esc_attr( (string) ( self::$appearance['inline_validation'] ?? 'both' ) ); ?>"
			data-inline-scope="<?php echo esc_attr( (string) ( self::$appearance['inline_scope'] ?? 'required' ) ); ?>"
			style="<?php echo esc_attr( Form_Style::css_variables_attr( $form_id ) ); ?>"
		>
			<header class="we-formkit__header">
				<h2 class="we-formkit__title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( ! empty( $schema['intro'] ) ) : ?>
					<div class="we-formkit__intro"><?php echo wp_kses_post( $schema['intro'] ); ?></div>
				<?php endif; ?>
			</header>

			<div class="we-formkit__status" data-wek-status role="status" aria-live="polite"></div>
			<div class="we-formkit__info-docs" data-wek-info-docs hidden></div>
			<?php if ( 'per_section' === $pagination ) : ?>
				<p class="we-formkit__progress" data-wek-progress hidden></p>
			<?php endif; ?>

			<form class="we-formkit__form" data-wek-form novalidate>
				<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
				<?php if ( Spam::honeypot_enabled() ) : ?>
					<input type="text" name="website_url" value="" class="we-formkit__honeypot" tabindex="-1" autocomplete="off" aria-hidden="true" />
				<?php endif; ?>

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
						<?php
						$show_section_title = ! array_key_exists( 'show_title', $section ) || ! empty( $section['show_title'] );
						if ( ! empty( $section['title'] ) && $show_section_title ) :
							?>
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

				<?php if ( 'per_section' === $pagination ) : ?>
					<p class="we-formkit__actions we-formkit__actions--nav" data-wek-nav hidden>
						<button type="button" class="we-formkit__nav-btn" data-wek-prev><?php esc_html_e( 'Previous', 'we-formkit' ); ?></button>
						<button type="button" class="we-formkit__nav-btn we-formkit__nav-btn--primary" data-wek-next><?php esc_html_e( 'Next', 'we-formkit' ); ?></button>
					</p>
				<?php endif; ?>

				<?php
				$save_enabled = Drafts::is_enabled( $form_id );
				$save_hidden  = '';
				if ( $save_enabled && Drafts::get_min_filled( $form_id ) > 0 ) {
					$save_hidden = ' hidden';
				}
				?>
				<?php if ( $save_enabled ) : ?>
					<?php
					$save_ttl       = Drafts::get_ttl_days( $form_id );
					$remind_allowed = Drafts::reminders_allowed( $form_id );
					$remind_leads   = Drafts::allowed_reminder_lead_days( $save_ttl );
					$remind_default = Drafts::reminder_lead_days( $save_ttl );
					?>
					<div class="we-formkit__actions we-formkit__actions--save-email" data-wek-save-wrap data-wek-save-ui<?php echo $save_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal " hidden" or empty. ?>>
						<label class="we-formkit__save-email-label">
							<span class="screen-reader-text"><?php esc_html_e( 'Email for resume link', 'we-formkit' ); ?></span>
							<input
								type="email"
								class="we-formkit__save-email"
								name="wek_save_email"
								data-wek-save-email
								autocomplete="email"
								inputmode="email"
								placeholder="<?php esc_attr_e( 'Email for resume link', 'we-formkit' ); ?>"
							/>
						</label>
						<?php if ( $remind_allowed ) : ?>
							<label class="we-formkit__save-remind">
								<input type="checkbox" name="wek_save_remind" value="1" data-wek-save-remind />
								<span><?php esc_html_e( 'Add a calendar reminder (.ics) before this link expires', 'we-formkit' ); ?></span>
							</label>
							<label class="we-formkit__save-remind-lead" data-wek-save-remind-lead-wrap hidden>
								<span class="we-formkit__save-remind-lead-label"><?php esc_html_e( 'Remind me', 'we-formkit' ); ?></span>
								<select name="wek_save_remind_lead" data-wek-save-remind-lead disabled>
									<?php foreach ( $remind_leads as $lead_days ) : ?>
										<option value="<?php echo esc_attr( (string) $lead_days ); ?>" <?php selected( $remind_default, $lead_days ); ?>>
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: number of days before the resume link expires. */
													_n( '%d day before expiry', '%d days before expiry', $lead_days, 'we-formkit' ),
													$lead_days
												)
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<p class="we-formkit__actions we-formkit__actions--buttons">
					<span class="we-formkit__submit-slot" data-wek-submit-wrap>
						<?php self::render_submit_button( $form_id ); ?>
					</span>
					<?php if ( $save_enabled ) : ?>
						<button type="button" class="we-formkit__save-progress" data-wek-save-progress data-wek-save-ui<?php echo $save_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal " hidden" or empty. ?>><?php esc_html_e( 'Save progress', 'we-formkit' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
		self::$appearance = null;
	}

	/**
	 * Whether the field label should be visibly shown.
	 *
	 * Checkbox/consent keep their label (it is the control text). Html/hidden have no label UI.
	 *
	 * @param array<string, mixed> $field Field.
	 */
	private static function field_shows_label( array $field ): bool {
		$type = sanitize_key( (string) ( $field['type'] ?? '' ) );
		if ( in_array( $type, array( 'checkbox', 'consent', 'html', 'hidden' ), true ) ) {
			return true;
		}
		return ! array_key_exists( 'show_label', $field ) || ! empty( $field['show_label'] );
	}

	/**
	 * CSS classes for a field label / legend.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param string               $extra Extra classes.
	 */
	private static function label_classes( array $field, $extra = '' ): string {
		$parts = array( 'we-formkit__label' );
		$extra = trim( (string) $extra );
		if ( '' !== $extra ) {
			foreach ( preg_split( '/\s+/', $extra ) as $class ) {
				if ( is_string( $class ) && '' !== $class ) {
					$parts[] = $class;
				}
			}
		}
		if ( ! self::field_shows_label( $field ) ) {
			$parts[] = 'screen-reader-text';
		}
		return implode( ' ', array_unique( $parts ) );
	}

	/**
	 * Required / optional indicator based on form appearance settings.
	 *
	 * @param bool   $required   Whether the field is required.
	 * @param string $field_type Field type (html/hidden skip marks).
	 * @return void
	 */
	private static function echo_requirement_mark( $required, $field_type = '' ) {
		$type = sanitize_key( (string) $field_type );
		if ( in_array( $type, array( 'html', 'hidden' ), true ) ) {
			return;
		}

		$appear = is_array( self::$appearance ) ? self::$appearance : array();

		if ( $required ) {
			$mark = (string) ( $appear['required_mark'] ?? 'asterisk' );
			if ( 'none' === $mark ) {
				return;
			}
			if ( 'text' === $mark ) {
				echo ' <span class="we-formkit__req we-formkit__req--text">' . esc_html__( '(required)', 'we-formkit' ) . '</span>';
				return;
			}
			echo ' <span class="we-formkit__req" aria-hidden="true">*</span>';
			return;
		}

		$optional = (string) ( $appear['optional_mark'] ?? 'text' );
		if ( 'none' === $optional ) {
			return;
		}
		echo ' <span class="we-formkit__opt">' . esc_html__( '(optional)', 'we-formkit' ) . '</span>';
	}

	/**
	 * Small circular-arrow control to clear radio / matrix selections.
	 *
	 * @return void
	 */
	private static function echo_field_reset_button() {
		$label = __( 'Clear selection', 'we-formkit' );
		?>
		<button
			type="button"
			class="we-formkit__field-reset"
			data-wek-field-reset
			aria-label="<?php echo esc_attr( $label ); ?>"
			title="<?php echo esc_attr( $label ); ?>"
			hidden
		>
			<svg class="we-formkit__field-reset-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
				<path fill="currentColor" d="M12 5V2.21c0-.45-.54-.67-.85-.35l-3.8 3.79c-.2.2-.2.51 0 .71l3.8 3.79c.31.32.85.1.85-.35V7c3.73 0 6.68 3.42 5.86 7.29-.47 2.27-2.31 4.1-4.57 4.57-3.57.75-6.75-1.7-7.23-5.01-.07-.48-.48-.85-.98-.85-.61 0-1.09.54-1 1.14.73 4.2 4.8 7.11 9.28 6.25 2.92-.56 5.25-2.9 5.81-5.81C20.29 9.44 16.72 5 12 5z"/>
			</svg>
		</button>
		<?php
	}

	/**
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private static function render_submit_button( $form_id ) {
		$submit   = Form_Schema::get_submit_button( $form_id );
		$label    = (string) $submit['label'];
		$icon     = (string) $submit['icon_svg'];
		$position = (string) $submit['icon_position'];
		?>
		<button
			type="submit"
			class="we-formkit__submit<?php echo '' !== $icon ? ' we-formkit__submit--has-icon' : ''; ?>"
			data-wek-submit
			data-wek-submit-label="<?php echo esc_attr( $label ); ?>"
		>
			<?php if ( '' !== $icon && 'before' === $position ) : ?>
				<span class="we-formkit__submit-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized SVG subset. ?></span>
			<?php endif; ?>
			<span class="we-formkit__submit-text" data-wek-submit-text><?php echo esc_html( $label ); ?></span>
			<?php if ( '' !== $icon && 'after' === $position ) : ?>
				<span class="we-formkit__submit-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized SVG subset. ?></span>
			<?php endif; ?>
		</button>
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
		$messages = isset( $field['messages'] ) && is_array( $field['messages'] ) ? $field['messages'] : array();
		$msg_req  = isset( $messages['required'] ) ? (string) $messages['required'] : '';
		$msg_inv  = isset( $messages['invalid'] ) ? (string) $messages['invalid'] : '';
		$min_sel  = 0;
		$max_sel  = 0;
		if ( 'checkboxes' === $type ) {
			$limits  = Fields\Checkboxes_Field::selection_limits( $field );
			$min_sel = $limits['min'];
			$max_sel = $limits['max'];
		}
		$css_class = isset( $field['css_class'] ) ? trim( (string) $field['css_class'] ) : '';
		$field_class = trim(
			'we-formkit__field we-formkit__field--' . $type . ' we-formkit__field--width-' . $width .
			( $hidden ? ' is-hidden' : '' ) .
			( '' !== $css_class ? ' ' . $css_class : '' )
		);
		?>
		<div
			class="<?php echo esc_attr( $field_class ); ?>"
			data-wek-field
			data-field-id="<?php echo esc_attr( $id ); ?>"
			data-field-type="<?php echo esc_attr( $type ); ?>"
			data-field-label="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>"
			data-required="<?php echo $req ? '1' : '0'; ?>"
			data-msg-required="<?php echo esc_attr( $msg_req ); ?>"
			data-msg-invalid="<?php echo esc_attr( $msg_inv ); ?>"
			<?php if ( 'checkboxes' === $type ) : ?>
				data-min-selected="<?php echo esc_attr( (string) $min_sel ); ?>"
				data-max-selected="<?php echo esc_attr( (string) $max_sel ); ?>"
			<?php endif; ?>
			<?php if ( 'matrix' === $type ) : ?>
				<?php
				$matrix_field_cfg = Fields\Matrix_Field::config( $field );
				if ( ! empty( $matrix_field_cfg['allow_custom_rows'] ) ) :
					?>
					data-wek-matrix-custom="1"
					data-max-custom-rows="<?php echo esc_attr( (string) $matrix_field_cfg['max_custom_rows'] ); ?>"
				<?php endif; ?>
			<?php endif; ?>
			<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			aria-hidden="<?php echo $hidden ? 'true' : 'false'; ?>"
		>
			<?php if ( 'checkbox' === $type || 'consent' === $type ) : ?>
				<?php
				$consent_privacy = '';
				if ( 'consent' === $type ) {
					$field_privacy   = isset( $field['type_options']['privacy_url'] ) ? trim( (string) $field['type_options']['privacy_url'] ) : '';
					$consent_privacy = '' !== $field_privacy ? $field_privacy : (string) $privacy_url;
				}
				?>
				<div class="we-formkit__control we-formkit__control--choice<?php echo 'consent' === $type ? ' we-formkit__control--consent' : ''; ?>">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $id ); ?>"
						value="1"
						<?php echo $req ? 'required' : ''; ?>
						aria-describedby="<?php echo esc_attr( trim( ( ( 'consent' === $type && $consent_privacy ) || ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
					/>
					<div class="we-formkit__consent-copy">
						<label for="<?php echo esc_attr( $input_id ); ?>">
							<?php echo esc_html( $field['label'] ); ?>
							<?php self::echo_requirement_mark( $req, $type ); ?>
						</label>
						<?php if ( 'consent' === $type && $consent_privacy ) : ?>
							<a
								class="we-formkit__privacy-link"
								id="<?php echo esc_attr( $desc_id ); ?>"
								href="<?php echo esc_url( $consent_privacy ); ?>"
								target="_blank"
								rel="noopener noreferrer"
							><?php esc_html_e( 'Privacy policy', 'we-formkit' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( ! empty( $field['help'] ) && ! ( 'consent' === $type && $consent_privacy ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php elseif ( ! empty( $field['help'] ) && 'consent' === $type && $consent_privacy ) : ?>
					<p class="we-formkit__help"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php elseif ( in_array( $type, array( 'radio', 'checkboxes' ), true ) ) : ?>
				<?php
				$choice_help = ! empty( $field['help'] ) ? (string) $field['help'] : '';
				$limit_hint  = '';
				if ( 'checkboxes' === $type && ( $min_sel > 0 || $max_sel > 0 ) ) {
					if ( $min_sel > 0 && $max_sel > 0 ) {
						$limit_hint = sprintf(
							/* translators: 1: minimum selections, 2: maximum selections. */
							__( 'Select between %1$d and %2$d options.', 'we-formkit' ),
							$min_sel,
							$max_sel
						);
					} elseif ( $min_sel > 0 ) {
						$limit_hint = sprintf(
							/* translators: %d: minimum selections. */
							__( 'Select at least %d option(s).', 'we-formkit' ),
							$min_sel
						);
					} else {
						$limit_hint = sprintf(
							/* translators: %d: maximum selections. */
							__( 'Select at most %d option(s).', 'we-formkit' ),
							$max_sel
						);
					}
				}
				$help_parts = array_filter( array( $choice_help, $limit_hint ) );
				$help_text  = implode( ' ', $help_parts );
				?>
				<fieldset class="we-formkit__fieldset">
					<legend class="<?php echo esc_attr( self::label_classes( $field ) ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php self::echo_requirement_mark( $req || $min_sel > 0, $type ); ?>
					</legend>
					<?php if ( 'radio' === $type && ! $req ) : ?>
						<?php self::echo_field_reset_button(); ?>
					<?php endif; ?>
					<?php if ( '' !== $help_text ) : ?>
						<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $help_text ); ?></p>
					<?php endif; ?>
					<div class="we-formkit__choices" role="group" aria-describedby="<?php echo esc_attr( trim( ( '' !== $help_text ? $desc_id . ' ' : '' ) . $error_id ) ); ?>">
						<?php
						$default     = isset( $field['default_value'] ) ? (string) $field['default_value'] : '';
						$allow_other = 'checkboxes' === $type && Fields\Checkboxes_Field::allows_other( $field );
						$other_label = $allow_other ? Fields\Checkboxes_Field::other_label( $field ) : '';
						foreach ( $field['options'] as $option ) :
							$oid    = $input_id . '-' . $option['value'];
							$iname  = 'checkboxes' === $type ? $id . '[]' : $id;
							$itype  = 'checkboxes' === $type ? 'checkbox' : 'radio';
							$is_def = '' !== $default && (string) $option['value'] === $default;
							?>
							<label class="we-formkit__choice" for="<?php echo esc_attr( $oid ); ?>">
								<input
									type="<?php echo esc_attr( $itype ); ?>"
									id="<?php echo esc_attr( $oid ); ?>"
									name="<?php echo esc_attr( $iname ); ?>"
									value="<?php echo esc_attr( $option['value'] ); ?>"
									<?php checked( $is_def ); ?>
								/>
								<span><?php echo esc_html( $option['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
						<?php if ( $allow_other ) : ?>
							<?php
							$other_oid     = $input_id . '-other';
							$other_text_id = $input_id . '-other-text';
							?>
							<div class="we-formkit__choice we-formkit__choice--other">
								<label class="we-formkit__choice-main" for="<?php echo esc_attr( $other_oid ); ?>">
									<input
										type="checkbox"
										id="<?php echo esc_attr( $other_oid ); ?>"
										name="<?php echo esc_attr( $id . '[]' ); ?>"
										value="<?php echo esc_attr( Fields\Checkboxes_Field::OTHER_TOKEN ); ?>"
										data-wek-other
									/>
									<span><?php echo esc_html( $other_label ); ?></span>
								</label>
								<input
									type="text"
									id="<?php echo esc_attr( $other_text_id ); ?>"
									class="we-formkit__other-text"
									value=""
									placeholder="<?php echo esc_attr__( 'Please specify…', 'we-formkit' ); ?>"
									autocomplete="off"
									data-wek-other-text
									aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Other option label */ __( '%s text', 'we-formkit' ), $other_label ) ); ?>"
								/>
							</div>
						<?php endif; ?>
					</div>
				</fieldset>
			<?php elseif ( 'radio_image' === $type ) : ?>
				<fieldset class="we-formkit__fieldset">
					<legend class="<?php echo esc_attr( self::label_classes( $field ) ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php self::echo_requirement_mark( $req, $type ); ?>
					</legend>
					<?php if ( ! $req ) : ?>
						<?php self::echo_field_reset_button(); ?>
					<?php endif; ?>
					<?php if ( ! empty( $field['help'] ) ) : ?>
						<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
					<?php endif; ?>
					<div class="we-formkit__choices" role="group" aria-describedby="<?php echo esc_attr( $desc_id . ' ' . $error_id ); ?>">
						<?php
						$default = isset( $field['default_value'] ) ? (string) $field['default_value'] : '';
						foreach ( ( $field['options'] ?? array() ) as $option ) :
							$oid       = $input_id . '-' . $option['value'];
							$image_url = isset( $option['image_url'] ) ? (string) $option['image_url'] : '';
							if ( '' === $image_url && ! empty( $option['image_id'] ) ) {
								$from_id = wp_get_attachment_image_url( (int) $option['image_id'], 'medium' );
								if ( is_string( $from_id ) ) {
									$image_url = $from_id;
								}
							}
							$is_def = '' !== $default && (string) $option['value'] === $default;
							?>
							<label class="we-formkit__choice we-formkit__choice--image" for="<?php echo esc_attr( $oid ); ?>">
								<input
									type="radio"
									id="<?php echo esc_attr( $oid ); ?>"
									name="<?php echo esc_attr( $id ); ?>"
									value="<?php echo esc_attr( $option['value'] ); ?>"
									<?php echo $req ? 'required' : ''; ?>
									<?php checked( $is_def ); ?>
								/>
								<?php if ( '' !== $image_url ) : ?>
									<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="we-formkit__choice-image" />
								<?php endif; ?>
								<span><?php echo esc_html( $option['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php elseif ( 'matrix' === $type ) : ?>
				<?php
				$matrix_cfg        = Fields\Matrix_Field::config( $field );
				$rows              = $matrix_cfg['rows'];
				$columns           = $matrix_cfg['columns'];
				$row_select        = $matrix_cfg['row_select'];
				$row_label_align   = $matrix_cfg['row_label_align'];
				$allow_custom_rows = ! empty( $matrix_cfg['allow_custom_rows'] );
				$max_custom_rows   = (int) $matrix_cfg['max_custom_rows'];
				$show_matrix_label = self::field_shows_label( $field );
				?>
				<fieldset class="we-formkit__fieldset we-formkit__fieldset--matrix">
					<legend class="screen-reader-text">
						<?php echo esc_html( $field['label'] ); ?>
						<?php self::echo_requirement_mark( $req, $type ); ?>
					</legend>
					<?php self::echo_field_reset_button(); ?>
					<?php if ( $show_matrix_label ) : ?>
						<p class="we-formkit__matrix-field-label" aria-hidden="true">
							<?php echo esc_html( $field['label'] ); ?>
							<?php self::echo_requirement_mark( $req, $type ); ?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $field['help'] ) ) : ?>
						<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
					<?php endif; ?>
					<?php if ( empty( $rows ) || ( empty( $columns ) && ! $row_select ) ) : ?>
						<p class="we-formkit__help"><?php esc_html_e( 'This matrix has no rows or columns yet.', 'we-formkit' ); ?></p>
					<?php else : ?>
						<div class="we-formkit__matrix-scroll" aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>">
							<table class="we-formkit__matrix we-formkit__matrix--row-labels-<?php echo esc_attr( $row_label_align ); ?>">
								<thead>
									<?php
									$needs_option_headers = false;
									foreach ( $columns as $col_check ) {
										$col_type_check = (string) ( $col_check['type'] ?? 'radio' );
										$opts_check     = isset( $col_check['options'] ) && is_array( $col_check['options'] ) ? $col_check['options'] : array();
										if ( 'radio' === $col_type_check && count( $opts_check ) > 0 ) {
											$needs_option_headers = true;
											break;
										}
									}
									?>
									<tr class="we-formkit__matrix-head-groups">
										<th scope="col" class="we-formkit__matrix-corner"<?php echo $needs_option_headers ? ' rowspan="2"' : ''; ?>>
											<span class="we-formkit__matrix-corner-label<?php echo $show_matrix_label ? '' : ' screen-reader-text'; ?>">
												<?php echo esc_html( $field['label'] ); ?>
												<?php self::echo_requirement_mark( $req, $type ); ?>
											</span>
										</th>
										<?php if ( $row_select ) : ?>
											<th scope="col" class="we-formkit__matrix-col we-formkit__matrix-col--select"<?php echo $needs_option_headers ? ' rowspan="2"' : ''; ?>>
												<span class="screen-reader-text"><?php esc_html_e( 'Select', 'we-formkit' ); ?></span>
											</th>
										<?php endif; ?>
										<?php foreach ( $columns as $col ) : ?>
											<?php
											$col_type = (string) ( $col['type'] ?? 'radio' );
											$opts     = isset( $col['options'] ) && is_array( $col['options'] ) ? $col['options'] : array();
											$col_lab  = (string) ( $col['label'] ?? $col['id'] ?? '' );
											if ( 'radio' === $col_type && ! empty( $opts ) ) :
												$span = count( $opts );
												?>
												<th
													scope="colgroup"
													class="we-formkit__matrix-col we-formkit__matrix-col--group we-formkit__matrix-col--block-start"
													colspan="<?php echo esc_attr( (string) $span ); ?>"
												>
													<span class="we-formkit__matrix-col-label"><?php echo esc_html( $col_lab ); ?></span>
												</th>
												<?php
											elseif ( $needs_option_headers ) :
												$col_mod = in_array( $col_type, array( 'text', 'number', 'checkbox' ), true ) ? $col_type : 'checkbox';
												?>
												<th
													scope="col"
													class="we-formkit__matrix-col we-formkit__matrix-col--<?php echo esc_attr( $col_mod ); ?> we-formkit__matrix-col--block-start"
													rowspan="2"
												>
													<span class="we-formkit__matrix-col-label"><?php echo esc_html( $col_lab ); ?></span>
												</th>
												<?php
											else :
												$col_mod = in_array( $col_type, array( 'text', 'number' ), true ) ? $col_type : 'checkbox';
												?>
												<th scope="col" class="we-formkit__matrix-col we-formkit__matrix-col--<?php echo esc_attr( $col_mod ); ?> we-formkit__matrix-col--block-start">
													<span class="we-formkit__matrix-col-label"><?php echo esc_html( $col_lab ); ?></span>
												</th>
												<?php
											endif;
											?>
										<?php endforeach; ?>
									</tr>
									<?php if ( $needs_option_headers ) : ?>
										<tr class="we-formkit__matrix-head-options">
											<?php foreach ( $columns as $col ) : ?>
												<?php
												$col_type = (string) ( $col['type'] ?? 'radio' );
												$opts     = isset( $col['options'] ) && is_array( $col['options'] ) ? $col['options'] : array();
												if ( 'radio' !== $col_type || empty( $opts ) ) {
													continue;
												}
												$opt_i = 0;
												foreach ( $opts as $opt ) :
													$opt_block = 0 === $opt_i ? ' we-formkit__matrix-col--block-start' : '';
													++$opt_i;
													?>
													<th scope="col" class="we-formkit__matrix-col we-formkit__matrix-col--radio<?php echo esc_attr( $opt_block ); ?>">
														<span class="we-formkit__matrix-col-label"><?php echo esc_html( (string) ( $opt['label'] ?? $opt['value'] ) ); ?></span>
													</th>
													<?php
												endforeach;
												?>
											<?php endforeach; ?>
										</tr>
									<?php endif; ?>
								</thead>
								<tbody data-wek-matrix-body>
									<?php foreach ( $rows as $row ) : ?>
										<?php
										self::render_matrix_row(
											array(
												'field_id' => $id,
												'input_id' => $input_id,
												'row_id'   => (string) $row['value'],
												'row_label' => (string) ( $row['label'] ?? $row['value'] ),
												'row_select' => $row_select,
												'columns'  => $columns,
												'is_custom' => false,
											)
										);
										?>
									<?php endforeach; ?>
								</tbody>
								<?php if ( $allow_custom_rows ) : ?>
									<template data-wek-matrix-custom-template>
										<?php
										self::render_matrix_row(
											array(
												'field_id' => $id,
												'input_id' => $input_id,
												'row_id'   => '__CUSTOM_ID__',
												'row_label' => '',
												'row_select' => $row_select,
												'columns'  => $columns,
												'is_custom' => true,
											)
										);
										?>
									</template>
								<?php endif; ?>
							</table>
						</div>
						<?php if ( $allow_custom_rows ) : ?>
							<p class="we-formkit__matrix-custom-actions">
								<button
									type="button"
									class="we-formkit__add-btn we-formkit__matrix-add-row"
									data-wek-matrix-add-row
									data-max-custom-rows="<?php echo esc_attr( (string) $max_custom_rows ); ?>"
								>
									<?php esc_html_e( 'Add other row', 'we-formkit' ); ?>
								</button>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</fieldset>
			<?php elseif ( 'select' === $type ) : ?>
				<?php
				$default     = isset( $field['default_value'] ) ? (string) $field['default_value'] : '';
				$placeholder = isset( $field['placeholder'] ) ? trim( (string) $field['placeholder'] ) : '';
				if ( '' === $placeholder ) {
					$placeholder = __( 'Please select…', 'we-formkit' );
				}
				$show_empty = '' === $default;
				?>
				<label class="<?php echo esc_attr( self::label_classes( $field ) ); ?>" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php self::echo_requirement_mark( $req, $type ); ?>
				</label>
				<select
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					<?php echo $req ? 'required' : ''; ?>
					aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
				>
					<?php if ( $show_empty ) : ?>
						<option value="" selected><?php echo esc_html( $placeholder ); ?></option>
					<?php endif; ?>
					<?php foreach ( $field['options'] as $option ) : ?>
						<option
							value="<?php echo esc_attr( $option['value'] ); ?>"
							<?php selected( $default, (string) $option['value'] ); ?>
						><?php echo esc_html( $option['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'textarea' === $type ) : ?>
				<label class="<?php echo esc_attr( self::label_classes( $field ) ); ?>" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php self::echo_requirement_mark( $req, $type ); ?>
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
				<label class="<?php echo esc_attr( self::label_classes( $field ) ); ?>" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php self::echo_requirement_mark( $req, $type ); ?>
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
			<?php elseif ( 'signature' === $type ) : ?>
				<label class="<?php echo esc_attr( self::label_classes( $field ) ); ?>" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php self::echo_requirement_mark( $req, $type ); ?>
				</label>
				<?php
				$pen = isset( $field['type_options']['pen_color'] ) ? (string) $field['type_options']['pen_color'] : '#222222';
				$bg  = isset( $field['type_options']['background_color'] ) ? (string) $field['type_options']['background_color'] : '#ffffff';
				?>
				<div
					class="we-formkit__signature"
					data-wek-signature="<?php echo esc_attr( $id ); ?>"
					data-pen="<?php echo esc_attr( $pen ); ?>"
					data-bg="<?php echo esc_attr( $bg ); ?>"
				>
					<canvas
						id="<?php echo esc_attr( $input_id ); ?>"
						class="we-formkit__signature-canvas"
						width="480"
						height="180"
						aria-describedby="<?php echo esc_attr( trim( ( ! empty( $field['help'] ) ? $desc_id . ' ' : '' ) . $error_id ) ); ?>"
					></canvas>
					<input type="hidden" name="<?php echo esc_attr( $id ); ?>" value="" data-wek-signature-input />
					<button type="button" class="we-formkit__signature-clear"><?php esc_html_e( 'Clear', 'we-formkit' ); ?></button>
				</div>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="we-formkit__help" id="<?php echo esc_attr( $desc_id ); ?>"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'repeater' === $type ) : ?>
				<?php self::render_repeater( $field, $input_id, $desc_id, $error_id, $req ); ?>
			<?php else : ?>
				<label class="<?php echo esc_attr( self::label_classes( $field ) ); ?>" for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php self::echo_requirement_mark( $req, $type ); ?>
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
				<span class="we-formkit__validity" data-wek-validity aria-hidden="true"></span>
				<p class="we-formkit__error" id="<?php echo esc_attr( $error_id ); ?>" data-wek-error role="alert" hidden>
					<span class="we-formkit__error-icon" aria-hidden="true"></span>
					<span class="we-formkit__error-text" data-wek-error-text></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one matrix table row (fixed or custom template).
	 *
	 * @param array{
	 *   field_id:string,
	 *   input_id:string,
	 *   row_id:string,
	 *   row_label:string,
	 *   row_select:bool,
	 *   columns:array<int,array<string,mixed>>,
	 *   is_custom:bool
	 * } $args Row args.
	 * @return void
	 */
	private static function render_matrix_row( array $args ) {
		$field_id   = (string) $args['field_id'];
		$input_id   = (string) $args['input_id'];
		$row_id     = (string) $args['row_id'];
		$row_label  = (string) $args['row_label'];
		$row_select = ! empty( $args['row_select'] );
		$columns    = isset( $args['columns'] ) && is_array( $args['columns'] ) ? $args['columns'] : array();
		$is_custom  = ! empty( $args['is_custom'] );
		$row_attrs  = 'data-wek-matrix-row="' . esc_attr( $row_id ) . '"';
		if ( $is_custom ) {
			$row_attrs .= ' data-wek-matrix-custom-row="1"';
		}
		?>
		<tr <?php echo $row_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
			<th scope="row" class="we-formkit__matrix-row-label<?php echo $is_custom ? ' we-formkit__matrix-row-label--custom' : ''; ?>">
				<?php if ( $is_custom ) : ?>
					<div class="we-formkit__matrix-custom-label-row">
						<label class="we-formkit__matrix-custom-label-wrap">
							<span class="screen-reader-text"><?php esc_html_e( 'Row label', 'we-formkit' ); ?></span>
							<input
								type="text"
								class="we-formkit__matrix-custom-label"
								name="<?php echo esc_attr( $field_id . '[' . $row_id . '][label]' ); ?>"
								value=""
								placeholder="<?php echo esc_attr__( 'Your label', 'we-formkit' ); ?>"
								autocomplete="off"
								data-wek-matrix-label
							/>
						</label>
						<button
							type="button"
							class="we-formkit__matrix-remove-row"
							data-wek-matrix-remove-row
							aria-label="<?php echo esc_attr__( 'Remove row', 'we-formkit' ); ?>"
							title="<?php echo esc_attr__( 'Remove row', 'we-formkit' ); ?>"
						>
							<span class="we-formkit__matrix-remove-icon" aria-hidden="true">×</span>
						</button>
					</div>
				<?php else : ?>
					<?php echo esc_html( $row_label ); ?>
				<?php endif; ?>
			</th>
			<?php if ( $row_select ) : ?>
				<td class="we-formkit__matrix-cell we-formkit__matrix-cell--select">
					<label class="we-formkit__matrix-choice we-formkit__matrix-choice--select">
						<span class="screen-reader-text">
							<?php
							echo esc_html(
								$is_custom
									? __( 'Select this row', 'we-formkit' )
									: sprintf(
										/* translators: %s: row label */
										__( 'Select %s', 'we-formkit' ),
										$row_label
									)
							);
							?>
						</span>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $field_id . '[' . $row_id . '][on]' ); ?>"
							value="1"
							data-wek-matrix-on
						/>
					</label>
				</td>
			<?php endif; ?>
			<?php foreach ( $columns as $col ) : ?>
				<?php
				$col_id   = (string) ( $col['id'] ?? '' );
				$col_type = (string) ( $col['type'] ?? 'radio' );
				$col_lab  = (string) ( $col['label'] ?? $col_id );
				$opts     = isset( $col['options'] ) && is_array( $col['options'] ) ? $col['options'] : array();
				if ( 'radio' === $col_type && ! empty( $opts ) ) :
					$opt_i = 0;
					foreach ( $opts as $opt ) :
						$oval      = (string) ( $opt['value'] ?? '' );
						$olab      = (string) ( $opt['label'] ?? $oval );
						$oid       = $input_id . '-' . $row_id . '-' . $col_id . '-' . $oval;
						$opt_block = 0 === $opt_i ? ' we-formkit__matrix-cell--block-start we-formkit__matrix-cell--group-start' : '';
						++$opt_i;
						?>
						<td
							class="we-formkit__matrix-cell we-formkit__matrix-cell--radio<?php echo esc_attr( $opt_block ); ?>"
							data-wek-col-label="<?php echo esc_attr( $col_lab ); ?>"
							data-wek-opt-label="<?php echo esc_attr( $olab ); ?>"
						>
							<?php if ( '' !== $opt_block ) : ?>
								<span class="we-formkit__matrix-mobile-group" aria-hidden="true"><?php echo esc_html( $col_lab ); ?></span>
							<?php endif; ?>
							<label class="we-formkit__matrix-choice" for="<?php echo esc_attr( $oid ); ?>">
								<span class="screen-reader-text"><?php echo esc_html( $olab ); ?></span>
								<input
									type="radio"
									id="<?php echo esc_attr( $oid ); ?>"
									name="<?php echo esc_attr( $field_id . '[' . $row_id . '][' . $col_id . ']' ); ?>"
									value="<?php echo esc_attr( $oval ); ?>"
									data-wek-matrix-col="<?php echo esc_attr( $col_id ); ?>"
								/>
								<span class="we-formkit__matrix-mobile-opt" aria-hidden="true"><?php echo esc_html( $olab ); ?></span>
							</label>
						</td>
						<?php
					endforeach;
				elseif ( 'text' === $col_type || 'number' === $col_type ) :
					$oid = $input_id . '-' . $row_id . '-' . $col_id;
					?>
					<td
						class="we-formkit__matrix-cell we-formkit__matrix-cell--<?php echo esc_attr( $col_type ); ?> we-formkit__matrix-cell--block-start"
						data-wek-col-label="<?php echo esc_attr( $col_lab ); ?>"
					>
						<label class="we-formkit__matrix-input-wrap" for="<?php echo esc_attr( $oid ); ?>">
							<span class="we-formkit__matrix-mobile-label" aria-hidden="true"><?php echo esc_html( $col_lab ); ?></span>
							<span class="screen-reader-text"><?php echo esc_html( ( $is_custom ? __( 'Custom row', 'we-formkit' ) : $row_label ) . ' — ' . $col_lab ); ?></span>
							<input
								type="<?php echo esc_attr( $col_type ); ?>"
								id="<?php echo esc_attr( $oid ); ?>"
								class="we-formkit__matrix-input"
								name="<?php echo esc_attr( $field_id . '[' . $row_id . '][' . $col_id . ']' ); ?>"
								value=""
								<?php echo 'number' === $col_type ? 'inputmode="decimal" step="any"' : ''; ?>
								data-wek-matrix-col="<?php echo esc_attr( $col_id ); ?>"
							/>
						</label>
					</td>
					<?php
				else :
					$oid = $input_id . '-' . $row_id . '-' . $col_id;
					?>
					<td
						class="we-formkit__matrix-cell we-formkit__matrix-cell--checkbox we-formkit__matrix-cell--block-start"
						data-wek-col-label="<?php echo esc_attr( $col_lab ); ?>"
					>
						<label class="we-formkit__matrix-choice" for="<?php echo esc_attr( $oid ); ?>">
							<span class="screen-reader-text"><?php echo esc_html( $col_lab ); ?></span>
							<input
								type="checkbox"
								id="<?php echo esc_attr( $oid ); ?>"
								name="<?php echo esc_attr( $field_id . '[' . $row_id . '][' . $col_id . ']' ); ?>"
								value="1"
								data-wek-matrix-col="<?php echo esc_attr( $col_id ); ?>"
							/>
							<span class="we-formkit__matrix-mobile-opt" aria-hidden="true"><?php echo esc_html( $col_lab ); ?></span>
						</label>
					</td>
					<?php
				endif;
				?>
			<?php endforeach; ?>
		</tr>
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
		<div class="<?php echo esc_attr( self::label_classes( $field, 'we-formkit__label--repeater' ) ); ?>" id="<?php echo esc_attr( $input_id . '-label' ); ?>">
			<?php echo esc_html( $field['label'] ); ?>
			<?php self::echo_requirement_mark( $req, 'repeater' ); ?>
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
				<button type="button" class="we-formkit__add-btn we-formkit__repeater-add" data-wek-repeater-add>
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
	 * @param array<string, mixed>             $field       Parent repeater field.
	 * @param array<int, array<string, mixed>> $item_fields Nested fields.
	 * @param int|string                       $index       Row index or placeholder.
	 * @param bool                             $is_template Whether rendering inside a template (kept for call-site symmetry).
	 * @return void
	 */
	private static function render_repeater_row( array $field, array $item_fields, $index, $is_template ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- template flag reserved if row chrome diverges again.
		unset( $is_template );
		$parent_id = (string) $field['id'];
		$index_str = (string) $index;
		?>
		<div class="we-formkit__repeater-row" data-wek-repeater-row>
			<div class="we-formkit__repeater-fields">
				<?php foreach ( $item_fields as $child ) : ?>
					<?php self::render_repeater_control( $parent_id, $child, $index_str ); ?>
				<?php endforeach; ?>
			</div>
			<button
				type="button"
				class="we-formkit__repeater-remove"
				data-wek-repeater-remove
				aria-label="<?php echo esc_attr__( 'Remove row', 'we-formkit' ); ?>"
				title="<?php echo esc_attr__( 'Remove row', 'we-formkit' ); ?>"
			>
				<svg class="we-formkit__remove-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M4 7h16" />
					<path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
					<path d="M10 11v6" />
					<path d="M14 11v6" />
					<path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
				</svg>
			</button>
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
		$css_class = isset( $child['css_class'] ) ? trim( (string) $child['css_class'] ) : '';
		$wrap_class = trim(
			'we-formkit__repeater-control we-formkit__repeater-control--' . $ctype .
			( '' !== $css_class ? ' ' . $css_class : '' )
		);
		?>
		<div class="<?php echo esc_attr( $wrap_class ); ?>">
			<label class="<?php echo esc_attr( self::label_classes( $child, 'we-formkit__label--nested' ) ); ?>" for="<?php echo esc_attr( $input_id ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php self::echo_requirement_mark( $req, $ctype ); ?>
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
				<?php
				$child_default = isset( $child['default_value'] ) ? (string) $child['default_value'] : '';
				$child_ph      = isset( $child['placeholder'] ) ? trim( (string) $child['placeholder'] ) : '';
				if ( '' === $child_ph ) {
					$child_ph = __( 'Please select…', 'we-formkit' );
				}
				$child_show_empty = '' === $child_default;
				?>
				<select
					id="<?php echo esc_attr( $input_id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					data-wek-repeater-input
					data-sub-id="<?php echo esc_attr( $cid ); ?>"
					<?php echo $req ? 'required' : ''; ?>
				>
					<?php if ( $child_show_empty ) : ?>
						<option value="" selected><?php echo esc_html( $child_ph ); ?></option>
					<?php endif; ?>
					<?php foreach ( ( $child['options'] ?? array() ) as $option ) : ?>
						<option
							value="<?php echo esc_attr( (string) ( $option['value'] ?? '' ) ); ?>"
							<?php selected( $child_default, (string) ( $option['value'] ?? '' ) ); ?>
						>
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
		 * @param array<string, mixed>|null $rule Rule or conditions container.
		 * @return string HTML attributes.
		 */
	private static function rule_attrs( $rule ) {
		if ( empty( $rule ) || ! is_array( $rule ) ) {
			return '';
		}

		// Prefer JSON container (multi-rule). Keep legacy attrs when single rule for older caches.
		$json = wp_json_encode( $rule );
		if ( false === $json ) {
			return '';
		}

		$attrs = ' data-show-when="' . esc_attr( $json ) . '"';

		$rules = array();
		if ( isset( $rule['field'] ) && ! isset( $rule['rules'] ) ) {
			$rules = array( $rule );
		} elseif ( ! empty( $rule['rules'] ) && is_array( $rule['rules'] ) ) {
			$rules = $rule['rules'];
		}

		if ( 1 === count( $rules ) && ! empty( $rules[0]['field'] ) ) {
			$one    = $rules[0];
			$attrs .= sprintf(
				' data-show-field="%s" data-show-op="%s" data-show-value="%s"',
				esc_attr( (string) $one['field'] ),
				esc_attr( (string) ( $one['op'] ?? 'equals' ) ),
				esc_attr( (string) ( $one['value'] ?? '' ) )
			);
		}

		return $attrs;
	}
}
