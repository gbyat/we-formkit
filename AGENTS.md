# WE Formkit — agent handoff

Use this file to continue development without re-deriving decisions from chat history.

## Status (honest)

**Product surface expanded beyond v0.1** — private uploads, signature, confirmations ×3, multipage, Save & Resume, shortcode, entry CSV/JSON, builder preview/duplicate. Live WP smoke still not verified.

| Area | State |
|------|--------|
| Field types (20) + signature | Done |
| Private uploads (`we-formkit-uploads/{token}/`) + gated download | Done |
| Confirmations message / redirect / page | Done |
| Multipage (`per_section`) | Done |
| Save & Resume drafts | Done (option store + REST; TTL 14d) |
| Shortcode `[we_formkit]` / `[we-formkit]` | Done |
| Entry CSV + JSON export | Done |
| Entry print → PDF | Done (print dialog) |
| Builder Preview + Duplicate form/field | Done (sidebar scope tabs polish still open) |
| Module registry | Empty; integrations later |
| Live WP smoke | **Not verified** (mail now in checklist) |

## Locked product decisions

- **Name / slug:** WE Formkit / `we-formkit`
- **Do not absorb `fro-anamnese`.**
- **No captcha / foreign CDNs / jQuery** in Formkit assets.
- **Block Editor line only** — Gutenberg block + shortcode; no Elementor/page builders.
- **DataForm** = Form Settings tab only (React). Fields canvas = vanilla JS.
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

1. **Live smoke** on WP (checklist below) — include notifications / mail.
2. Sidebar always-visible scope tabs (Field / Form / Integrations stub) — polish.
3. Modules: Spamfighter adapter, Subscribe-to-Posts, smart-tag picker, server PDF.
4. PHPCS remaining warnings; i18n POT.

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

> Continue WE Formkit from AGENTS.md. Smoke-test private uploads + signature + multipage + Save & Resume + confirmations + CSV + **notifications (delivery log / Send to me)**. Then polish sidebar scope tabs. Leave fro-anamnese untouched.
