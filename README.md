# WE Formkit

Modular WordPress form builder with typed fields, entries, and a developer module API.

**Author:** [webentwicklerin, Gabriele Laesser](https://webentwicklerin.at) · **GitHub:** [gbyat/we-formkit](https://github.com/gbyat/we-formkit)

## Features

- **Block + shortcode** — Gutenberg block `we-formkit/form`, shortcodes `[we_formkit]` / `[we-formkit]` (`id` or `slug`; optional `prefill="anliegen:angebot,email:a@b.c"`)
- **URL prefill** — query params match Field ID (or custom param): `?anliegen=angebot&email=name@example.com`; Select/Radio copy-links in the builder; works with page cache via JS
- **Field types** — text, email, tel, url, textarea, number, select, radio, radio image, checkbox, checkboxes, **matrix**, date/time/datetime, **consent**, html, hidden, upload, signature, repeater
- **Radio image** — Media Library images per option; columns, image size, option placement (under/beside/above); selection style radio or frame + checkmark
- **Choice options** — enter labels; keys auto-slug from the label (ä→ae, ß→ss, …); override only when you need a custom value
- **Matrix** — rows × radio / checkbox / text / number columns; optional row select; visitor-added rows; self-fill example row; min answered / per-row / per-column required; conditionals per row
- **Name / Address templates** — builder mini-templates (toggle slots, reorder); Address country presets; canvas pack groups; semantic `field.role`
- **Consent / checkbox** — optional field label + text beside the control; consent supports inline `{link}`
- **Show on frontend** — park a field without deleting it (settings stay; re-enable later)
- **Submit button** — label, optional SVG icon, width (auto / full / two thirds / half / third)
- **Private uploads** — Formkit folder by default (not Media Library), gated download
- **Confirmations** — message, redirect, or page
- **Multipage** — section-based (`per_section`); step deep link `?wek_page=2` (with several forms: also `wek_page_form={formId}`)
- **Save & Resume** — drafts, email link, optional calendar `.ics`, TTL / min-filled
- **Entries** — global list (all forms) + per-form filter; detail; CSV + JSON export; print → PDF (browser; server PDF planned)
- **Notifications** — Smart Tags, delivery log, global email footer (WYSIWYG)
- **Design** — labels, density, colors (Form Settings → Design)
- **Spam** — honeypot, timing, rate limit, IP hash; optional **Modules** (e.g. Akismet); quarantine hook for add-ons (e.g. WE Spamfighterin)
- **Integrations** — builder Integrations scope via `we_formkit_builder_integrations` (add-ons register their own panels)
- **Secret access** — query suffix `?wek_form=…&token=…` for embed pages

No captcha, no foreign CDNs, no jQuery in Formkit assets. Block Editor line only (no Elementor).

## Planned

Not blocking a first public release — documented so expectations stay clear:

- **Server PDF** — real download via TCPDF (print dialog works today; PDF header/footer settings already exist)
- **Calculation** field — formula over other fields (server-side recompute on submit)
- **Range** field — single value via `<input type="range">`
- **Pro / addon later** — matrix/survey analytics (tables → charts); payment gateways one at a time
- Optional: more Modules, more mini-templates once Name/Address prove the pattern

## Requirements

- WordPress 6.9+
- PHP 8.0+

## Usage

1. **WE Formkit → Forms** — create a form, edit Fields / Form Settings / Design / Notifications.
2. Embed with the **Formkit Form** block or `[we_formkit id="123"]` / `[we_formkit slug="my-form"]`.
3. Entries under **WE Formkit → Entries** (all forms by default; form filter when you have more than one).
4. Global defaults under **WE Formkit → Settings**; optional integrations under **Modules**.

## Development

```bash
composer install
npm install
npm run wp-cli:install
```

### Translations

```bash
npm run i18n
```

Generates/updates `.pot`, `.po`, `.mo`, `.l10n.php`, and block-editor `.json` files in `languages/`.

### Release

```bash
npm run release:patch
```

Bumps the version, updates changelog/readme files, refreshes translations, commits, tags, and pushes. GitHub Actions builds the distributable ZIP and publishes the release.

Local installable ZIP:

```bash
npm run build
```

## Developer docs

Hooks, field types, modules, and Smart Tags: [`docs/developer/README.md`](docs/developer/README.md)

## Changelog

See [`CHANGELOG.md`](CHANGELOG.md).
