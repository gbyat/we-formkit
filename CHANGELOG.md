# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.3] - 2026-07-19

- Admin form builder, submissions UI, settings, frontend block, REST submit (incl. uploads), print export, module registry.
- Local versioned ZIPs under `releases/` plus `npm run release:*:local`.
- Changelog auto-draft from commit messages when `[Unreleased]` is empty; validate notes before version bump.
- POT via WP-CLI when `@wp-blocks/make-pot` is not installed (`hasBlocks` tooling flag false for this plugin).
## [0.1.1] - 2026-07-19

- Sighthound SAST as standard dev/CI check (`scripts/run-sighthound.js`, workflow, `npm run scan:sighthound`).
- Git pre-push hook for local Sighthound scan (`npm run hooks:install`).

- Initial development.
