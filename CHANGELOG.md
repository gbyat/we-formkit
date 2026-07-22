# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.6.1] - 2026-07-22

- Update admin menu page title to 'WE Formkit' for improved branding consistency.
## [0.6.0] - 2026-07-21

- Enhance Formkit with smart tag picker for email and confirmation messages, allowing users to insert dynamic content easily. Update package.json to include new PHP script for internationalization. Add filters for managing MIME types and submission data, improving extensibility and customization options. Update translations for consistency.
## [0.5.1] - 2026-07-21

- Update email notification templates and validation messages to use sprintf for better localization support. Adjust admin menu items for modules and settings, and update translations accordingly. Add AGENTS.md to .gitignore.
- Remove AGENTS.md file, which contained development notes and product decisions for WE Formkit. This file is no longer needed as development progresses.
## [0.5.0] - 2026-07-21

- Implement modules submenu and Akismet spam adapter; enhance module registry with activation and dependency checks. Update AGENTS.md and translations for new features.
## [0.4.8] - 2026-07-21

- Add content guard feature to text and textarea fields to reject submissions containing links or email addresses. Update translations and UI elements for spam protection settings.
## [0.4.7] - 2026-07-21

- Fix URL formatting in calendar event properties by removing unnecessary text escaping for improved clarity and functionality.
## [0.4.6] - 2026-07-21

- Update calendar event properties to include METHOD:PUBLISH for improved compatibility with email clients. This change ensures that personal appointments are handled correctly, allowing recipients to save events without prompts to accept or decline.
## [0.4.5] - 2026-07-21

- Add inline image support for email notifications. Implement functionality to embed images using CID in outgoing emails, enhancing the display of signatures. Update email formatting methods to accommodate inline images and ensure proper handling of allowed protocols.
## [0.4.4] - 2026-07-21

- Refactor draft email content and update translations for improved clarity. Simplify the message format for saved progress notifications and adjust related German translations. Update calendar event properties for better compatibility with email clients.
## [0.4.3] - 2026-07-21

- Add hidden attribute to page progress element on form reset
## [0.4.2] - 2026-07-21

- Refactor and enhance form functionality and UI. Update AGENTS.md to reflect the current product status and features, including sidebar scope tabs for field, form, and integrations. Improve CSS for admin and frontend, adding styles for new sidebar elements and ensuring responsive design. Modify JavaScript to manage sidebar interactions and improve form validation logic. Update notifications handling to allow HTML in emails for better formatting. Ensure all changes are documented and consistent with previous updates.
## [0.4.1] - 2026-07-21

- Enhance email footer settings in admin panel by integrating a rich text editor for better formatting options. Update CSS to ensure proper styling of the email footer editor. Adjust translations for improved clarity and consistency in the German language files.
## [0.4.0] - 2026-07-21

- Enhance save and resume functionality by adding email footer settings and spam protection options. Update CSS for admin and frontend to reflect new UI elements. Modify JavaScript to manage new draft settings, including cooldown for resume emails and maximum drafts stored. Update backend logic for email handling and spam validation. Improve documentation with new settings descriptions.
## [0.3.6] - 2026-07-21

- Enhance save and resume functionality for forms. Update CSS for admin and frontend to style new save progress options, including minimum filled fields and calendar reminder settings. Modify JavaScript to manage the new UI elements and validation logic for reminders. Add backend support for minimum filled fields and reminder options in draft settings. Update translations for new strings related to the save and resume feature.
## [0.3.5] - 2026-07-21

- Implement save and resume feature for forms. Update CSS for admin and frontend to style save progress actions and email input. Enhance JavaScript to handle email prefill and validation for resume link requests. Add backend support for email sending and draft expiration settings. Update translations for new strings related to the save and resume functionality.
## [0.3.4] - 2026-07-21

- Refactor form styling and validation logic. Update CSS for form labels and control rows to enhance appearance and alignment. Modify JavaScript to improve inline control row handling for radio and checkbox inputs, ensuring proper structure and styling consistency.
## [0.3.3] - 2026-07-21

- Update CSS styles for form elements to improve consistency and appearance. Change background properties to use 'background-color' for better clarity. Add vendor prefixes for select elements and ensure border-radius is applied consistently across inputs and selects. Adjust layout properties in admin form settings for enhanced responsiveness.
## [0.3.2] - 2026-07-21

- Refactor form styling and remove theme import functionality. Update CSS to utilize new variables for padding and border radii across input elements. Remove the Theme Import panel from the admin settings, streamlining the form appearance settings. Adjust translations for improved clarity on input padding and corner settings.
## [0.3.1] - 2026-07-21

- Enhance form validation and styling features. Introduce new CSS variables for input, button, and section border radii. Implement inline validation feedback in JavaScript, allowing real-time user input validation. Update Form_Schema and Form_Style classes to support new appearance options, including optional markers and radius settings. Refactor related CSS and JavaScript for improved consistency and usability across the admin and frontend interfaces.
## [0.3.0] - 2026-07-21

- Enhance form appearance settings by introducing new CSS variables for spacing, control padding, and font sizes. Update the Form_Schema class to include new appearance options and adjust the admin panel to support these settings. Refactor related CSS files for improved styling consistency across the admin and frontend interfaces. Update translations for new appearance-related strings.
## [0.2.18] - 2026-07-21

- Implement theme color import functionality in the admin panel. Enhance the Form_Style class to support importing theme colors and managing color roles with contrast checks. Update the admin interface to include a new Theme Import panel, allowing users to map theme colors to form roles. Adjust translations for new strings related to theme color management.
## [0.2.17] - 2026-07-21

- Enhance form styling and color scheme management. Introduce new CSS variables for input and button text colors, update existing styles for improved consistency, and refactor the Form_Style class to support named color schemes. Update admin panel to include scheme options and ensure proper handling of color roles in the frontend. Adjust translations for new color-related strings.
## [0.2.16] - 2026-07-20

- Fix print/PDF export to show option labels and allow safe HTML in answers.
- Refactor submission export and answer formatting functions to improve HTML escaping and field handling. Update format_value and format_answer_value methods to accept field configuration, ensuring proper display of values and enhanced security against XSS vulnerabilities.
## [0.2.15] - 2026-07-20

- Enhance form appearance settings by adding font family options in the admin panel. Update related CSS for improved styling consistency across admin and frontend interfaces. Refactor JavaScript to support new typography settings and ensure proper handling of font family selections. Update translations for new font-related strings.
## [0.2.14] - 2026-07-20

- Enhance email configuration settings in the admin panel. Introduce default 'From' email and name options, and update related UI elements for better user experience. Improve CSS for SMTP password status display. Update translations for new email settings strings.
## [0.2.13] - 2026-07-20

- Enhance email notification system by introducing SMTP settings in the admin panel. Implement test email functionality to verify SMTP configurations. Update CSS for improved layout in the admin interface. Revise AGENTS.md to include mail notifications in the live smoke checklist.
## [0.2.12] - 2026-07-20

- Enhance admin form editor and frontend functionality. Update CSS for improved field label display and required indicators. Refactor JavaScript to implement debounced synchronization for hidden inputs, improve conditional visibility handling, and streamline field width application. Introduce caching for show-when attributes to optimize performance.
## [0.2.11] - 2026-07-20

- Enhance admin form editor with MIME type management features. Introduce multi-select for allowed MIME types, allowing users to specify file types for uploads. Update CSS for improved UI consistency in the admin interface, including adjustments to button styles and section visibility. Refactor JavaScript to support new MIME type functionalities and improve validation message handling.
## [0.2.10] - 2026-07-20

- Refactor admin CSS and JavaScript for improved layout and responsiveness. Adjust button dimensions, positioning, and opacity for better UI consistency. Update width settings for collapsed states in the admin form editor to enhance user experience.
## [0.2.9] - 2026-07-20

- Implement form appearance customization features. Introduce methods to get and set form-wide appearance settings, including label weight, required mark, help placement, and help style. Update frontend rendering to apply these settings, enhancing the visual consistency of forms. Refactor related JavaScript and CSS for improved layout and user experience. Update translations for new appearance-related strings.
## [0.2.8] - 2026-07-20

- Implement customizable submit button functionality in the form schema. Introduce methods to get and set submit button properties, including label, icon SVG, and icon position. Update frontend rendering to utilize the new submit button configuration, enhancing user experience with customizable form submissions. Update related validation messages for checkboxes to improve user guidance on selection limits.
## [0.2.7] - 2026-07-20

- Enhance validation message handling in the form editor and frontend. Introduce customizable required and invalid messages for form fields, allowing users to define default messages in settings. Update CSS for improved error display and layout. Refactor JavaScript to ensure proper rendering of validation messages and error handling. Update German translations for new validation message strings.
## [0.2.6] - 2026-07-20

- Enhance admin UI and functionality for form submissions. Introduce new features for managing submission actions, including trashing, restoring, and marking entries as read or spam. Update the submissions interface to support a read-only view and improve the layout with CSS adjustments. Add new metadata handling for source URLs and notification logs, ensuring better tracking and management of submissions. Update German translations for consistency.
## [0.2.5] - 2026-07-20

- Update German translations in we-formkit plugin. Add missing translations for various plugin strings, including form-related actions and settings. Update the POT file creation date and ensure consistency in the localization files.
- Implement resend notification functionality in the admin submissions interface. Add a method to resend one or all notifications for a submission, including an option to override the recipient email. Enhance the UI to allow users to resend notifications directly from the submission details page, displaying success and failure messages accordingly. Update related CSS for improved layout of the notification resend options.
## [0.2.4] - 2026-07-20

- Enhance notification handling in the admin form editor by introducing HTML support for email bodies. Implement a WYSIWYG editor for notification headers, messages, and footers, allowing for rich text formatting and the use of merge tags. Update CSS for improved layout of notification elements. Refactor notification body composition to support HTML formatting, ensuring a better user experience in email communications.
- Enhance admin form editor UI and functionality. Update CSS to enforce consistent box-sizing and width for form elements, improving layout within the admin interface. Refactor rule display logic to dynamically manage field options and values based on selected conditions, enhancing user experience. Introduce JavaScript for handling field options and value selection, ensuring a more intuitive interaction in the form editor.
## [0.2.3] - 2026-07-20

- Implement info documents feature in the admin form editor. Add functionality for managing documents, including toggling and deleting. Update CSS for document-related UI elements and enhance frontend JavaScript to display downloadable links. Modify notification handling to include document links in emails. Update documentation to reflect new features and usage of `{info_links}` tag.
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
