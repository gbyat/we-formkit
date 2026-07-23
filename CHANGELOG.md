# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
