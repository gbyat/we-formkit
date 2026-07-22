# WE Formkit

Modular WordPress form builder with typed fields, entries, and a developer module API.

**Author:** [webentwicklerin, Gabriele Laesser](https://webentwicklerin.at) · **GitHub:** [gbyat/we-formkit](https://github.com/gbyat/we-formkit)

## Features

- **Block + shortcode** — Gutenberg block `we-formkit/form`, shortcodes `[we_formkit]` / `[we-formkit]` (`id` or `slug`)
- **Field types** — text, email, tel, url, textarea, number, select, radio, radio image, checkbox, checkboxes, **matrix**, date/time/datetime, **consent**, html, hidden, upload, signature, repeater
- **Matrix** — rows × radio / checkbox / text / number columns, optional row select, visitor-added rows, conditionals per row
- **Consent** — field label (optional) + consent text beside the checkbox; optional inline `{link}` (link text + URL; empty URL → form privacy URL, then site default)
- **Checkbox** — same split: optional field label + checkbox text beside the control
- **Private uploads** — Formkit folder by default (not Media Library), gated download
- **Confirmations** — message, redirect, or page
- **Multipage** — section-based (`per_section`)
- **Save & Resume** — drafts, email link, optional calendar `.ics`, TTL / min-filled
- **Entries** — list/detail, CSV + JSON export, print → PDF (browser print; server PDF later)
- **Notifications** — templates with Smart Tags, delivery log, global email footer
- **Design** — labels, density, colors (Form Settings / Design)
- **Spam** — honeypot, timing, rate limit, IP hash; optional **Modules** (e.g. Akismet)
- **Secret access** — query suffix `?wek_form=…&token=…` for embed pages

No captcha, no foreign CDNs, no jQuery in Formkit assets. Block Editor line only (no Elementor).

## Requirements

- WordPress 6.5+
- PHP 8.0+

## Usage

1. **WE Formkit → Forms** — create a form, edit Fields / Form Settings / Design / Notifications.
2. Embed with the **Formkit Form** block or `[we_formkit id="123"]` / `[we_formkit slug="my-form"]`.
3. Entries under **WE Formkit → Submissions**.
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

Generates/updates `.pot`, `.po`, `.mo`, and block-editor `.json` files in `languages/`.

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
