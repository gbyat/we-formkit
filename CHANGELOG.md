# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.10] - 2026-07-19

- Remove unnecessary headers from fetch options in frontend.js to streamline API requests.
## [0.1.9] - 2026-07-19

- Add autofill functionality to forms, including support for various input types and automatic submission. Enhance frontend script with new utility functions for setting input values and filling file inputs. Update localization strings for autofill messages in PHP class.
## [0.1.8] - 2026-07-19

- Enhance frontend form styles with improved spacing, hover effects, and focus states for choices and controls. Introduce custom styles for checkboxes and radio buttons, including animations and accessibility features. Update media queries for reduced motion preferences.
## [0.1.7] - 2026-07-19

- Refactor admin styles and scripts to improve accessibility and remove theme dependency. Update CSS variables for better contrast and remove unused theme token logic from JavaScript and PHP files.
## [0.1.6] - 2026-07-19

- Enhance form settings functionality by adding support for Form Settings in AGENTS.md. Update build process in package.json to include a new script for copying styles. Refactor code in class-rest-form-settings.php and class-form-editor.php for improved readability and style management, including conditional loading of styles based on file existence.
- Update dependencies in package.json and package-lock.json, add new build scripts, and enhance admin styles. Adjust minimum WordPress version requirement in we-formkit.php. Include new color filtering hook in developer documentation. Update frontend rendering to support dynamic CSS variables. Modify plugin configuration to include build directory in zip packaging.
## [0.1.5] - 2026-07-19

- Implement two-thirds width option for fields in admin and frontend styles. Enhance repeater field functionality with updated JavaScript for width normalization and improved UI components. Update localization strings for new width options and adjust CSS for better layout management.
## [0.1.4] - 2026-07-19

- Add repeater field functionality, including UI components and backend support. Updated AGENTS.md to reflect new core field classes. Enhanced admin and frontend styles for repeater elements, and added necessary JavaScript for handling repeater logic. Updated documentation to include new hooks and features.
## [0.1.3] - 2026-07-19

- Admin form builder, submissions UI, settings, frontend block, REST submit (incl. uploads), print export, module registry.
- Local versioned ZIPs under `releases/` plus `npm run release:*:local`.
- Changelog auto-draft from commit messages when `[Unreleased]` is empty; validate notes before version bump.
- POT via WP-CLI when `@wp-blocks/make-pot` is not installed (`hasBlocks` tooling flag false for this plugin).
## [0.1.1] - 2026-07-19

- Sighthound SAST as standard dev/CI check (`scripts/run-sighthound.js`, workflow, `npm run scan:sighthound`).
- Git pre-push hook for local Sighthound scan (`npm run hooks:install`).

- Initial development.
