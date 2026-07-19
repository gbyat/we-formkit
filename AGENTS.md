# WE Formkit — agent handoff

Use this file to continue development without re-deriving decisions from chat history.

## Status (honest)

**v0.1.0 product surface is in place** (admin builder, block, REST submit, export). Not yet smoke-tested on a live WP install.

| Area | State |
|------|--------|
| Template scaffold (scripts, CI, preflight, Sighthound) | Done |
| Field type registry + 19 core field classes (incl. repeater) | Done |
| Date relative constraints (`days\|weeks\|months\|years`, `past\|future`) | Done |
| CPTs, capabilities, settings, uninstall | Done |
| Form_Schema + Conditional | Done |
| Spam + Rest_Api (JSON + multipart upload) + Notifications + Retention | Done |
| Admin builder / tabs / entries UI | Done |
| Frontend + Gutenberg block | Done |
| Entry PDF print export | Done |
| Module registry + `docs/developer/` | Done |
| GF importer / AI PDF assist | Deferred (modules) |
| Live WP smoke (create form → submit → entry) | **Not verified** |

## Locked product decisions

- **Name / slug:** WE Formkit / `we-formkit` (do not use `we-forms` — taken on wordpress.org).
- **Location:** `Projekte-Plugins/we-formkit` — sibling of `fro-anamnese`.
- **Do not rename or absorb `fro-anamnese`.** Customer anamnesis plugin stays separate.
- **No anamnesis templates in core** (blank forms only).
- **Author:** webentwicklerin, Gabriele Laesser — https://webentwicklerin.at
- **GitHub:** `gbyat/we-formkit`
- **Source language:** English only in PHP/JS strings; German via PO later.
- **Field model:** one PHP class per type + registry.
- **Core field catalog:** `text`, `email`, `tel`, `url`, `textarea`, `number`, `select`, `radio`, `radio_image`, `checkbox`, `checkboxes`, `date`, `time`, `datetime`, `consent`, `html`, `hidden`, `upload`, `repeater` (clonable nested field group).
- **Security tooling:** preflight + Sighthound — keep; do not ship `vendor/` / `node_modules/` in release ZIPs.

## Naming conventions

| Concern | Value |
|---------|--------|
| Namespace | `Webentwicklerin\WeFormkit` |
| Admin namespace | `Webentwicklerin\WeFormkit\Admin` |
| Text domain | `we-formkit` |
| CPT form / submission | `wek_form` / `wek_submission` |
| Meta prefix | `_wek_*` |
| Caps | `wek_form(s)`, `wek_submission(s)`, `manage_we_formkit` |
| Admin menu | Formkit |
| JS globals / handles | `weFormkit*`, `we-formkit-*` |
| Hooks | `we_formkit_*` |
| REST | `we-formkit/v1/submit` |
| Block | `we-formkit/form` |

## What exists

```
includes/class-plugin.php
includes/class-post-types.php
includes/class-capabilities.php
includes/class-settings.php
includes/class-form-schema.php
includes/class-conditional.php
includes/class-spam.php
includes/class-rest-api.php
includes/class-notifications.php
includes/class-retention.php
includes/class-frontend.php
includes/class-submission-export.php
includes/class-module-registry.php
includes/class-field-type-registry.php
includes/fields/*
includes/admin/class-admin.php
includes/admin/class-form-editor.php
includes/admin/class-submissions.php
includes/admin/class-settings-page.php
assets/{css,js}/*
docs/developer/README.md
uninstall.php
```

## Next implementation order (resume here)

1. ~~Backend + schema + REST pipeline~~ Done.
2. ~~Admin + Frontend + Export + Modules docs~~ Done.
3. **Verify on WP** — activate plugin, create blank form, embed block, submit (with upload), open entry + PDF print.
4. **Polish** — PHPCS autofix remaining field alignment warnings; i18n POT; optional multipart edge cases.
5. **Later modules** — GF import, AI PDF assist via `we_formkit_register_modules`.

## Hard rules for agents

- Never modify `fro-anamnese` unless the user explicitly asks.
- Do not add German UI strings in code.
- Prefer Node scripts over shell for tooling.
- Follow WPCS; run PHPCS on changed PHP when `vendor/` is available.
- Do not commit unless the user asks.

## Quick continue prompt

> Continue WE Formkit from AGENTS.md. Status: product surface done; live smoke missing. Activate on a WP site, create a blank form with upload + date constraints, submit via block, verify entry + print export. Leave fro-anamnese untouched.
