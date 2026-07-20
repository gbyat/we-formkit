# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.2] - 2026-07-20

- Refactor notification handling in the admin form editor. Introduce new methods for managing notifications, including duplication, deletion, and toggling status. Update CSS for notification UI elements, enhancing layout and usability. Implement schema hydration for notifications to ensure proper field linking. Improve overall notification management experience in the editor.
## [0.2.1] - 2026-07-20

- Enhance admin form editor with new field toolbar functionality, allowing users to edit, duplicate, move, and delete fields directly. Update CSS for improved UI elements, including the addition of a field toolbar and adjustments to button styles. Modify plugin configuration to exclude build and dist directories from POT generation.
## [0.2.0] - 2026-07-20

- Expand product surface in AGENTS.md, adding features like private uploads, signature fields, multipage forms, and Save & Resume functionality. Update CSS for admin and frontend to enhance UI elements, including navigation buttons and field previews. Implement JavaScript for multipage navigation and signature handling, ensuring improved user experience. Introduce REST API enhancements for file handling and confirmation settings. Refactor form schema to support new features and improve data handling.
## [0.1.19] - 2026-07-20

- Implement conditional visibility rules in the admin form editor. Introduce a new CPO-style conditions container for managing visibility based on field values, enhancing user experience. Update CSS for condition-related UI elements and refine JavaScript logic for evaluating and rendering conditions. Ensure backward compatibility with legacy single rule format.
## [0.1.18] - 2026-07-20

- Enhance admin form editor UI and CSS for improved usability and layout. Update grid layout for builder components, refine responsive behavior, and adjust styles for empty states and drag-and-drop functionality. Modify text hints for clarity in field interactions, ensuring a more intuitive user experience.
## [0.1.17] - 2026-07-20

- Refactor admin CSS for improved layout and visual consistency. Update styles for fields bar badges, actions, and entries, enhancing alignment and responsiveness. Modify form editor logic to ensure proper title handling for new and existing forms, improving user experience.
## [0.1.16] - 2026-07-20

- Implement admin color scheme selection in settings, enhancing UI customization. Update CSS for improved layout and visual consistency across admin components, including new accent color schemes. Refactor field icons in the form editor for better clarity and accessibility.
## [0.1.15] - 2026-07-20

- Refactor admin UI components and enhance form editor functionality. Update CSS for improved layout and visual consistency, including a new toggle input for field settings. Introduce a delete button for fields and streamline the form navigation. Add viewport meta tag for better responsiveness in exported submissions.
## [0.1.14] - 2026-07-20

- Enhance admin form editor UI with a new search feature for fields, allowing users to filter available fields. Update CSS styles for improved visual consistency and accessibility, including new color tokens and layout adjustments. Introduce a sticky action bar for better usability when editing forms.
## [0.1.13] - 2026-07-19

- Refactor notification handling in the form editor and enhance admin notifications UI. Update AGENTS.md to reflect changes in notification features. Add new CSS styles for notification cards in admin interface. Improve developer documentation with new notification merge tags and filter for mail arguments.
## [0.1.12] - 2026-07-19

- Enhance form submission handling in frontend.js by adding autosubmit functionality and preventing duplicate submissions. Update localization strings in class-frontend.php for improved user guidance during manual submission.
## [0.1.11] - 2026-07-19

- Update nonce handling in frontend.js and class files to ensure consistent security verification for API requests.
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
