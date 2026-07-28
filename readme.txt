=== WE Formkit ===
Contributors: webentwicklerin
Tags: forms, form-builder, block, entries, upload, gdpr
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular WordPress form builder with typed fields, entries, and a developer module API.

== Description ==

WE Formkit is a modular form builder for the Block Editor (plus shortcode). Visual fields canvas, Form Settings and Design, notifications with Smart Tags, entries with CSV/JSON export and print, private uploads, Save & Resume, multipage sections, URL field prefill, spam controls, and an optional Modules API (e.g. Akismet). Add-ons can register Integrations panels and use the spam quarantine hook (e.g. WE Spamfighterin).

Field types include text, email, select, radio, radio image, checkboxes, matrix, consent, upload, signature, repeater, and more. Name/Address mini-templates, park fields without deleting them (Show on frontend), and choice keys auto-generated from labels (ä→ae, ß→ss). Embed with the Formkit Form block or [we_formkit id|slug].

No captcha, no foreign CDNs, no jQuery in Formkit assets. Block Editor only (no Elementor).

Author: webentwicklerin, Gabriele Laesser — https://webentwicklerin.at
GitHub: https://github.com/gbyat/we-formkit

== Planned ==

* Server PDF download (TCPDF); print dialog works today
* Calculation and Range field types
* Survey analytics and payment gateways as later add-ons

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/we-formkit/ or install the release ZIP.
2. Activate WE Formkit through the Plugins screen.
3. Open WE Formkit → Forms to create a form, then embed it with the block or shortcode.

== Changelog ==

See CHANGELOG.md in the plugin package for the full history.

= 1.1.0 =
* Builder sidebar scopes (Field / Form / Integrations), Name/Address templates, spam quarantine path, global Entries list, radio image and matrix improvements, Show on frontend, PDF print settings.

= 1.0.5 =
* Name/Address templates, pack groups, Spamfighterin Integrations when hub is active, prefill scroll-to-form.

= 1.0.4 =
* Smart Tags, URL prefill polish, multipage and Save & Resume refinements.

= 1.0.3 =
* Matrix custom rows, submit button width options.

= 1.0.2 =
* Checkboxes custom options; admin smoke autofill improvements.

= 1.0.1 =
* Multipage step deep link (?wek_page=N).

= 1.0.0 =
* Initial public release.
