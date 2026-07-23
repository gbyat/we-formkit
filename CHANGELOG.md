# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- URL multi-field prefill via query params (Field ID / custom param), shortcode/block `prefill`, and builder copy-links for choice options.
- Form Settings: show/hide form title and intro independently on the public form; fix missing intro field label; Design live preview matches front-end header vs section card.
- Text / Textarea: maximum character limit (builder + HTML maxlength + server validate); new text defaults to 200, textarea to 5000.
- Required validation skips fields that are hidden by field or section conditions (front-end + server).
- Choice options: label-first editing; optional keys auto-generated with German-friendly transliteration (ä→ae, ß→ss, …); unique keys on clash.
- Align readme “Requires at least” / requirements with plugin header (WordPress 6.9); “Tested up to” 7.1; sync wordpress.org changelog through 1.0.3.

## [1.0.3] - 2026-07-23

- Enhance submit button customization by introducing new CSS styles for button widths and updating the admin form editor to support width selection. Implement JavaScript adjustments to ensure proper rendering of button widths in the frontend. Revise translation strings for clarity on button width options and descriptions.
- Implement submit button width customization by adding a new width property to the submit button configuration. Introduce normalization function for width values and update related frontend components to reflect changes. Enhance admin form editor to support width selection and ensure proper rendering in the frontend. Update REST API to handle width parameter in submit button settings.
- Refactor matrix field features by introducing a new function to format matrix index labels, enhancing user experience with clearer labeling. Update CSS for improved visual presentation of matrix example status. Revise translation strings for better clarity on visitor-added rows and example row behavior.
- Enhance matrix field capabilities by increasing the maximum custom rows limit to 20 and improving the user interface for matrix options. Update JavaScript to manage new button functionalities for removing and reordering matrix options, and adjust CSS for better visual presentation of matrix elements. Revise translation strings for clarity on new features and requirements.
- Enhance matrix field functionality by allowing custom rows and adding minimum answered rows requirement. Update JavaScript to manage row and column required flags, improve validation messages, and ensure proper handling of user inputs. Adjust CSS for matrix example rows to improve user experience and visual feedback. Update documentation for clarity on new features and requirements.
- Remove automatic SAST workflow from GitHub

[1.0.2]: https://github.com/gbyat/we-formkit/releases/tag/v1.0.2

## [1.0.2] - 2026-07-23

- Enhance frontend functionality by adding autofill capabilities for various input types, including checkboxes, matrix rows, and signature fields. Update JavaScript to streamline form autofill processes and improve user experience. Adjust CSS for checkbox styles to ensure consistent visual feedback when checked.
- Update frontend styles for custom checkbox actions to ensure proper ordering and spacing. Adjust CSS rules for `.we-formkit__checkboxes-custom-actions` and `.we-formkit__matrix-custom-actions` to enhance layout consistency.
- Enhance checkbox functionality by allowing custom options with improved UI and validation. Update styles for custom checkbox choices and integrate max options limit. Modify JavaScript to manage custom option visibility and input handling. Update translations for clarity on custom option requirements.
- Refactor frontend styles and JavaScript for improved checkbox handling and layout. Update CSS for `.we-formkit__choice--other` and related elements to enhance responsiveness and visual consistency. Implement visibility toggle for other text input based on checkbox state in JavaScript. Ensure hidden attributes are correctly applied in the HTML structure.
- Update Sighthound integration to output JSON and convert to SARIF format. Modify GitHub Actions workflow to include Node.js setup and update upload actions. Enhance run-sighthound script to handle JSON output and conversion. Update .gitignore to include JSON files.

[1.0.1]: https://github.com/gbyat/we-formkit/releases/tag/v1.0.1

## [1.0.1] - 2026-07-23

- Enhance multipage functionality with deep linking support and URL state management. Update README and documentation to reflect new features. Improve CSS for add buttons to ensure better visibility and accessibility.

[1.0.0]: https://github.com/gbyat/we-formkit/releases/tag/v1.0.0

## [1.0.0] - 2026-07-23

### Added

- Initial public release of WE Formkit: typed fields (including matrix and consent), Block Editor + shortcode embed, Form Settings / Design, notifications with Smart Tags, entries (CSV/JSON/print), private uploads, Save & Resume, multipage sections, spam controls, and Modules API (Akismet adapter).
