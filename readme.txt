=== WE Formkit ===
Contributors: webentwicklerin
Tags: forms, form-builder, block, entries, upload, gdpr
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular WordPress form builder with typed fields, entries, and a developer module API.

== Description ==

WE Formkit is a modular form builder for the Block Editor (plus shortcode). It includes a visual fields canvas, Form Settings and Design, notifications with Smart Tags, entries with CSV/JSON export and print, private uploads, Save & Resume, multipage sections (step deep link via ?wek_page=N), URL field prefill (query params / shortcode), spam controls, and an optional Modules API (e.g. Akismet).

Field types include text, email, select, radio, checkboxes, matrix (visitor-added rows, self-fill example, min answered / per-row / per-column required), consent (inline {link} placeholder), upload, signature, repeater, and more. Choice option keys can be left empty and are auto-generated from the label (German-friendly: ä→ae, ß→ss). Embed with the Formkit Form block or [we_formkit id|slug].

Author: webentwicklerin, Gabriele Laesser — https://webentwicklerin.at

== Changelog ==

= 1.0.3 =
* Matrix: custom rows (max 20), self-fill example row, min answered / per-row / per-column required, clearer column labels.
* Submit button: width options (auto / full / two thirds / half / third).
* Docs and translations updated for matrix and submit width.

= 1.0.2 =
* Checkboxes: visitor-added custom options with limits and validation.
* Admin smoke autofill covers checkboxes, matrix rows, and signatures.
* Layout/CSS fixes for custom checkbox and matrix actions.

= 1.0.1 =
* Multipage: keep current step in the URL (?wek_page=N) so refresh stays on that page.

= 1.0.0 =
* Initial public release.
