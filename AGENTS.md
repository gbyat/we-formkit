# WE Formkit — agent handoff

Use this file to continue development without re-deriving decisions from chat history.

## Status (honest)

**Core product usable** — fields, private uploads, signature, confirmations ×3, multipage, Save & Resume, shortcode, entry CSV/JSON/print, configurable spam, global email footer WYSIWYG, builder sidebar scope tabs. Live WP smoke exercised by Gabriele (2026-07).

| Area | State |
|------|--------|
| Field types (20) + signature | Done |
| Private uploads (`we-formkit-uploads/{token}/`) + gated download | Done |
| Confirmations message / redirect / page | Done |
| Multipage (`per_section`) | Done |
| Save & Resume | Done — Form Settings (enable, TTL, min filled, calendar .ics); 5 min mail cooldown; token reuse; draft prune/cap |
| Shortcode `[we_formkit]` / `[we-formkit]` | Done |
| Entry CSV + JSON export | Done |
| Entry print → PDF | Done (print dialog; server PDF later) |
| Builder Preview + Duplicate | Done |
| Form Settings (title, slug, privacy, secret, Save & Resume) | Done (DataForm) |
| Design (labels, type, density, colors + preview) | Done — own editor tab |
| Sidebar scope tabs (Field / Form / Integrations) | Done (Integrations = module stub) |
| Spam (honeypot / timing / rate / IP hash) | Done — Formkit Settings toggles + limits |
| Per-field link/email block (text/textarea) | Done — type_options `block_links` / `block_emails` |
| Global email footer | Done — TinyMCE in Formkit Settings |
| Modules submenu | Done — activate + dependency check; first module: Akismet spam adapter |
| Live WP smoke | **Done** (manual) |

## Locked product decisions

- **Name / slug:** WE Formkit / `we-formkit`
- **Do not absorb `fro-anamnese`.**
- **No captcha / foreign CDNs / jQuery** in Formkit assets.
- **Block Editor line only** — Gutenberg block + shortcode; no Elementor/page builders.
- **DataForm** = Form Settings + Design tabs (React, `panel` general|design). Fields canvas = vanilla JS.
- **Uploads:** private Formkit folder by default (not Media Library).
- **Author:** webentwicklerin, Gabriele Laesser — https://webentwicklerin.at
- **GitHub:** `gbyat/we-formkit`
- **Source language:** English only in PHP/JS strings.
- **Core field catalog:** `text`, `email`, `tel`, `url`, `textarea`, `number`, `select`, `radio`, `radio_image`, `checkbox`, `checkboxes`, `date`, `time`, `datetime`, `consent`, `html`, `hidden`, `upload`, `signature`, `repeater`.

## Naming conventions

| Concern | Value |
|---------|--------|
| Namespace | `Webentwicklerin\WeFormkit` |
| Text domain | `we-formkit` |
| CPT | `wek_form` / `wek_submission` |
| REST | `we-formkit/v1/submit`, `/file`, `/drafts` |
| Block | `we-formkit/form` |
| Shortcode | `[we_formkit id\|slug]` |

## Next implementation order (resume here)

1. More modules as needed (Spamfighter adapter if distinct from Akismet, Subscribe-to-Posts deeper hooks, smart-tag picker, server PDF).
2. Keep PHPCS / i18n clean on further changes.
3. Re-smoke after larger changes (checklist below).
4. Optional: wire Integrations sidebar tab to the Modules screen.

## Live smoke checklist

Run on a real WP install (not only the builder UI):

1. Create form → Fields canvas → save.
2. Embed via block and/or shortcode `[we_formkit id|slug]`.
3. Submit: text + upload + signature; confirm private file download (gated).
4. Multipage (`per_section`) + Save & Resume draft restore.
5. Confirmations: message / redirect / page.
6. Entry: list, detail, CSV/JSON export, print→PDF.
7. **Notifications / mail** (required for a complete smoke):
   - Form → **Notifications**: Admin notification **enabled**; recipient set (or empty → Formkit Settings “Default notification email” / `admin_email`).
   - Submit once → open the entry → **Notification delivery log** (ok / failed + recipient).
   - Use entry **Send to me** / **Resend** if inbox is empty (layout check without waiting for real delivery).
   - Local/dev: prefer Formkit Settings → Mail transport (SMTP), or Mailpit; still verify the delivery log shows `ok` or a clear error (`No valid recipient`, `wp_mail failed`).

## Hard rules for agents

- Never modify `fro-anamnese` unless asked.
- No German UI strings in code.
- Prefer Node scripts over shell for tooling.
- Follow WPCS; run PHPCS on changed PHP when `vendor/` exists.
- Do not commit unless asked.

## Quick continue prompt

> Continue WE Formkit from AGENTS.md. Modules submenu + Akismet adapter done. Next: more modules / smart-tag picker / server PDF when ready. Leave fro-anamnese untouched.
