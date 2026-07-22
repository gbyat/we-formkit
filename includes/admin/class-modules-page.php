<?php
/**
 * Modules screen — activate optional extensions and see their dependencies.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit\Admin;

use Webentwicklerin\WeFormkit\Capabilities;
use Webentwicklerin\WeFormkit\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Modules submenu and handles activation changes.
 */
final class Modules_Page {

	/**
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! Capabilities::can_manage() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
		if ( ! isset( $_GET['page'] ) || 'we-formkit-modules' !== $_GET['page'] ) {
			return;
		}
		if ( ! isset( $_POST['we_formkit_save_modules'] ) ) {
			return;
		}
		if ( ! isset( $_POST['we_formkit_modules_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['we_formkit_modules_nonce'] ) ), 'we_formkit_save_modules' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'we-formkit' ) );
		}

		$registry  = Plugin::instance()->module_registry();
		$submitted = isset( $_POST['wek_modules'] ) && is_array( $_POST['wek_modules'] )
			? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['wek_modules'] ) ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		// Never keep a module active when its dependencies are unmet: this avoids a
		// module appearing "on" while it silently cannot run.
		$active = array();
		foreach ( $submitted as $id ) {
			if ( $registry->has( $id ) && $registry->dependencies_met( $id ) ) {
				$active[] = $id;
			}
		}

		$registry->set_active( $active );

		wp_safe_redirect( admin_url( 'admin.php?page=we-formkit-modules&saved=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'we-formkit' ) );
		}

		$registry = Plugin::instance()->module_registry();
		$modules  = $registry->all();
		$scheme   = \Webentwicklerin\WeFormkit\Settings::admin_scheme();
		?>
		<div class="wrap wek-admin wek-admin--modules" data-wek-scheme="<?php echo esc_attr( $scheme ); ?>">
			<h1><?php esc_html_e( 'Formkit Modules', 'we-formkit' ); ?></h1>
			<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Modules updated.', 'we-formkit' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Optional extensions for Formkit. Core spam protection (honeypot, timing, rate limit, per-field link/email blocking) always runs — modules add integrations that rely on other plugins or services.', 'we-formkit' ); ?>
			</p>

			<?php if ( empty( $modules ) ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'No modules are registered yet.', 'we-formkit' ); ?></p></div>
				<?php return; ?>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'we_formkit_save_modules', 'we_formkit_modules_nonce' ); ?>
				<table class="widefat striped wek-modules-table">
					<thead>
						<tr>
							<th scope="col" class="wek-modules-table__toggle"><?php esc_html_e( 'Active', 'we-formkit' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Module', 'we-formkit' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Requirements', 'we-formkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $modules as $id => $definition ) : ?>
							<?php
							$deps      = $registry->dependency_status( $id );
							$met       = $registry->dependencies_met( $id );
							$is_active = $registry->is_active( $id );
							$running   = $is_active && $met;
							$name      = isset( $definition['name'] ) ? (string) $definition['name'] : $id;
							$desc      = isset( $definition['description'] ) ? (string) $definition['description'] : '';
							$version   = isset( $definition['version'] ) ? (string) $definition['version'] : '';
							$docs_url  = isset( $definition['docs_url'] ) ? (string) $definition['docs_url'] : '';
							?>
							<tr>
								<td class="wek-modules-table__toggle">
									<label class="wek-module-switch">
										<input
											type="checkbox"
											name="wek_modules[<?php echo esc_attr( $id ); ?>]"
											value="1"
											<?php checked( $is_active && $met ); ?>
											<?php disabled( ! $met ); ?>
										/>
										<span class="screen-reader-text"><?php echo esc_html( $name ); ?></span>
									</label>
									<?php if ( $is_active && ! $met ) : ?>
										<span class="wek-module-badge wek-module-badge--paused"><?php esc_html_e( 'Paused', 'we-formkit' ); ?></span>
									<?php elseif ( $running ) : ?>
										<span class="wek-module-badge wek-module-badge--on"><?php esc_html_e( 'On', 'we-formkit' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( $name ); ?></strong>
									<?php if ( '' !== $version ) : ?>
										<span class="wek-module-version">v<?php echo esc_html( $version ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $desc ) : ?>
										<p class="description"><?php echo esc_html( $desc ); ?></p>
									<?php endif; ?>
									<?php if ( '' !== $docs_url ) : ?>
										<p class="description">
											<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'we-formkit' ); ?></a>
										</p>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( empty( $deps ) ) : ?>
										<span class="wek-module-dep wek-module-dep--met"><?php esc_html_e( 'No extra requirements', 'we-formkit' ); ?></span>
									<?php else : ?>
										<ul class="wek-module-deps">
											<?php foreach ( $deps as $dep ) : ?>
												<li class="wek-module-dep <?php echo ! empty( $dep['met'] ) ? 'wek-module-dep--met' : 'wek-module-dep--missing'; ?>">
													<span class="wek-module-dep__icon dashicons <?php echo ! empty( $dep['met'] ) ? 'dashicons-yes' : 'dashicons-no-alt'; ?>" aria-hidden="true"></span>
													<span class="wek-module-dep__label"><?php echo esc_html( (string) $dep['label'] ); ?></span>
												</li>
											<?php endforeach; ?>
										</ul>
										<?php if ( ! $met ) : ?>
											<p class="description"><?php esc_html_e( 'Install and configure the missing requirement to enable this module.', 'we-formkit' ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save modules', 'we-formkit' ), 'primary', 'we_formkit_save_modules' ); ?>
			</form>
		</div>
		<?php
	}
}
