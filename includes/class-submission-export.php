<?php
/**
 * Printable / PDF-oriented export of a filled submission.
 *
 * @package Webentwicklerin\WeFormkit
 */

namespace Webentwicklerin\WeFormkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a clean clinical document for print / Save as PDF.
 */
final class Submission_Export {

	/**
	 * Stream a printable HTML document (browser: Print → Save as PDF).
	 *
	 * @param int $submission_id Submission post ID.
	 * @return void
	 */
	public static function stream_print_document( $submission_id ) {
		$post = get_post( (int) $submission_id );
		if ( ! $post || Post_Types::SUBMISSION !== $post->post_type ) {
			wp_die( esc_html__( 'Submission not found.', 'we-formkit' ) );
		}

		$form_id = (int) get_post_meta( $post->ID, Form_Schema::SUB_FORM_ID, true );
		$raw     = (string) get_post_meta( $post->ID, Form_Schema::SUB_DATA, true );
		$data    = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$notes  = (string) get_post_meta( $post->ID, Form_Schema::SUB_NOTES, true );
		$schema = $form_id ? Form_Schema::get( $form_id ) : Form_Schema::normalize( array() );
		$fields = Form_Schema::fields_by_id( $schema );
		$title  = get_the_title( $post );
		$form_t = $form_id ? get_the_title( $form_id ) : '';
		$date   = get_the_date( '', $post ) . ' ' . get_the_time( '', $post );

		// Drop WP/plugin buffers so the browser can finish the document (avoids stuck tab spinner).
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Connection: close' );

		$css = self::print_css();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $title ); ?></title>
	<style><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS. ?></style>
</head>
<body>
	<div class="wek-export no-print">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'we-formkit' ); ?></button>
		<button type="button" onclick="window.close()"><?php esc_html_e( 'Close', 'we-formkit' ); ?></button>
		<p><?php esc_html_e( 'In the print dialog choose “Save as PDF” (or similar) to download a PDF file.', 'we-formkit' ); ?></p>
	</div>

	<article class="wek-export-doc">
		<header class="wek-export-doc__header">
			<p class="wek-export-doc__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<h1><?php echo esc_html( $form_t ? $form_t : $title ); ?></h1>
			<p class="wek-export-doc__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: submission title, 2: date */
						__( 'Entry: %1$s · %2$s', 'we-formkit' ),
						$title,
						$date
					)
				);
				?>
			</p>
		</header>

		<?php if ( ! empty( $schema['sections'] ) && is_array( $schema['sections'] ) ) : ?>
			<?php foreach ( $schema['sections'] as $section ) : ?>
				<?php
				if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
					continue;
				}
				$has_answer = false;
				foreach ( $section['fields'] as $field ) {
					$fid = isset( $field['id'] ) ? (string) $field['id'] : '';
					if ( $fid && array_key_exists( $fid, $data ) && '' !== self::format_value( $data[ $fid ] ) ) {
						$has_answer = true;
						break;
					}
				}
				if ( ! $has_answer ) {
					continue;
				}
				?>
				<section class="wek-export-doc__section">
					<?php if ( ! empty( $section['title'] ) ) : ?>
						<h2><?php echo esc_html( (string) $section['title'] ); ?></h2>
					<?php endif; ?>
					<dl>
						<?php foreach ( $section['fields'] as $field ) : ?>
							<?php
							$fid = isset( $field['id'] ) ? (string) $field['id'] : '';
							if ( '' === $fid || ! array_key_exists( $fid, $data ) ) {
								continue;
							}
							$display = self::format_value( $data[ $fid ] );
							if ( '' === $display ) {
								continue;
							}
							$label = isset( $field['label'] ) ? (string) $field['label'] : $fid;
							?>
							<div class="wek-export-doc__row">
								<dt><?php echo esc_html( $label ); ?></dt>
								<dd><?php echo esc_html( $display ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</section>
			<?php endforeach; ?>
		<?php else : ?>
			<section class="wek-export-doc__section">
				<dl>
					<?php foreach ( $data as $key => $value ) : ?>
						<?php
						$label   = isset( $fields[ $key ]['label'] ) ? (string) $fields[ $key ]['label'] : (string) $key;
						$display = self::format_value( $value );
						if ( '' === $display ) {
							continue;
						}
						?>
						<div class="wek-export-doc__row">
							<dt><?php echo esc_html( $label ); ?></dt>
							<dd><?php echo esc_html( $display ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endif; ?>

		<?php if ( '' !== trim( $notes ) ) : ?>
			<section class="wek-export-doc__section wek-export-doc__notes">
				<h2><?php esc_html_e( 'Internal notes', 'we-formkit' ); ?></h2>
				<p><?php echo esc_html( $notes ); ?></p>
			</section>
		<?php endif; ?>

		<footer class="wek-export-doc__footer">
			<p><?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'Generated by %s', 'we-formkit' ), get_bloginfo( 'name' ) ) ); ?></p>
		</footer>
	</article>
	<script>
		(function () {
			function stripAutoprint() {
				try {
					var url = new URL(window.location.href);
					if (!url.searchParams.has('autoprint')) {
						return;
					}
					url.searchParams.delete('autoprint');
					var next = url.pathname + url.search + url.hash;
					window.history.replaceState(null, '', next);
				} catch (e) {
					/* ignore */
				}
			}

			// afterprint clears Chrome/Edge “loading” state after Save as PDF / cancel.
			window.addEventListener('afterprint', stripAutoprint);

			var params = new URLSearchParams(window.location.search);
			if (params.get('autoprint') !== '1') {
				return;
			}

			// Defer print until after load finishes — calling print() inside load keeps the tab spinner forever in Chromium.
			window.addEventListener('load', function () {
				window.setTimeout(function () {
					window.print();
				}, 100);
			});
		})();
	</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * @param mixed $value Field value.
	 * @return string
	 */
	private static function format_value( $value ) {
		if ( is_array( $value ) ) {
			$value = array_filter( array_map( 'strval', $value ) );
			return implode( ', ', $value );
		}
		return trim( (string) $value );
	}

	/**
	 * @return string
	 */
	private static function print_css() {
		return <<<'CSS'
		@page { margin: 18mm 16mm; }
		* { box-sizing: border-box; }
		body {
			margin: 0;
			font-family: "Segoe UI", "Source Sans 3", sans-serif;
			color: #1c1b19;
			line-height: 1.45;
			background: #e8e6e1;
		}
		.wek-export {
			max-width: 52rem;
			margin: 1rem auto;
			padding: 0 1rem;
		}
		.wek-export button {
			margin: 0 0.5rem 0.5rem 0;
			padding: 0.5rem 0.9rem;
			font: inherit;
			cursor: pointer;
		}
		.wek-export-doc {
			max-width: 52rem;
			margin: 0 auto 2rem;
			padding: 2rem 2.25rem;
			background: #fff;
			border: 1px solid #d9d3c8;
		}
		.wek-export-doc__site {
			margin: 0 0 0.25rem;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			font-size: 0.75rem;
			color: #5c574f;
		}
		.wek-export-doc h1 {
			margin: 0 0 0.35rem;
			font-size: 1.55rem;
			font-weight: 600;
		}
		.wek-export-doc__meta {
			margin: 0 0 1.5rem;
			color: #5c574f;
			font-size: 0.92rem;
		}
		.wek-export-doc__section {
			margin: 0 0 1.25rem;
			padding-top: 0.75rem;
			border-top: 1px solid #d9d3c8;
		}
		.wek-export-doc__section h2 {
			margin: 0 0 0.65rem;
			font-size: 1.05rem;
		}
		.wek-export-doc dl { margin: 0; }
		.wek-export-doc__row {
			display: grid;
			grid-template-columns: minmax(8rem, 34%) 1fr;
			gap: 0.35rem 1rem;
			padding: 0.35rem 0;
			border-bottom: 1px dotted #e4dfd6;
		}
		.wek-export-doc__row dt {
			margin: 0;
			font-weight: 600;
			color: #5c574f;
			font-size: 0.9rem;
		}
		.wek-export-doc__row dd {
			margin: 0;
			white-space: pre-wrap;
		}
		.wek-export-doc__notes p {
			margin: 0;
			white-space: pre-wrap;
		}
		.wek-export-doc__footer {
			margin-top: 1.5rem;
			padding-top: 0.75rem;
			border-top: 1px solid #d9d3c8;
			font-size: 0.8rem;
			color: #5c574f;
		}
		@media print {
			body { background: #fff; }
			.no-print { display: none !important; }
			.wek-export-doc {
				border: 0;
				margin: 0;
				padding: 0;
				max-width: none;
			}
		}
CSS;
	}
}
